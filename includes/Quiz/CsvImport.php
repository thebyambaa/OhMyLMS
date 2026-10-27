<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Create a draft quiz and its questions from CSV text.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Quiz;

use OhMyLMS\Data\Quiz;
use OhMyLMS\QuestionBank\DraftWriter;

/**
 * Quiz CSV import.
 *
 * Header row: type, question, points, correct, option_1 ... option_6. Types are single-choice,
 * multiple-choice, true-false, short-text and long-text. "correct" holds option numbers joined
 * with "|" (for example "2" or "1|3"), or true / false for true-false questions.
 */
class CsvImport {

	const TYPES = array( 'single-choice', 'multiple-choice', 'true-false', 'short-text', 'long-text' );

	/**
	 * Parse CSV text into question payloads for DraftWriter.
	 *
	 * @param string $csv CSV text.
	 * @return array|\WP_Error List of question payloads, or an error naming the first bad row.
	 */
	public static function parse( $csv ) {
		$csv = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $csv );
		$fh  = fopen( 'php://memory', 'r+' );
		fwrite( $fh, $csv );
		rewind( $fh );
		$header = fgetcsv( $fh, 0, ',', '"', '' );
		if ( ! is_array( $header ) ) {
			fclose( $fh );
			return new \WP_Error( 'ohmylms_quiz_import_empty', __( 'The file is empty.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$columns = array_map(
			static function ( $name ) {
				return preg_replace( '/[^a-z0-9]+/', '_', strtolower( trim( (string) $name ) ) );
			},
			$header
		);
		if ( ! in_array( 'question', $columns, true ) ) {
			fclose( $fh );
			return new \WP_Error( 'ohmylms_quiz_import_header', __( 'The first row must be a header that includes a "question" column.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$items = array();
		$line  = 1;
		while ( false !== ( $cells = fgetcsv( $fh, 0, ',', '"', '' ) ) ) {
			++$line;
			if ( array( null ) === $cells || '' === trim( implode( '', $cells ) ) ) {
				continue;
			}
			$row = array();
			foreach ( $columns as $index => $name ) {
				$row[ $name ] = isset( $cells[ $index ] ) ? trim( (string) $cells[ $index ] ) : '';
			}
			$item = self::item( $row, $line );
			if ( is_wp_error( $item ) ) {
				fclose( $fh );
				return $item;
			}
			$items[] = $item;
		}
		fclose( $fh );

		if ( ! $items ) {
			return new \WP_Error( 'ohmylms_quiz_import_empty', __( 'The file has no questions.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		return $items;
	}

	/**
	 * Create the quiz and attach its questions. Nothing is kept if a question fails.
	 *
	 * @param string $name Quiz name.
	 * @param string $csv  CSV text.
	 * @return int|\WP_Error New quiz ID.
	 */
	public static function import( $name, $csv ) {
		$items = self::parse( $csv );
		if ( is_wp_error( $items ) ) {
			return $items;
		}
		$quiz = new Quiz();
		$quiz->set_name( sanitize_text_field( $name ) );
		$quiz->set_status( 'draft' );
		$quiz_id = (int) $quiz->save();
		if ( ! $quiz_id ) {
			return new \WP_Error( 'ohmylms_quiz_import_failed', __( 'The quiz could not be created.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		wp_update_post(
			array(
				'ID'          => $quiz_id,
				'post_status' => 'draft',
			)
		);
		foreach ( $items as $position => &$item ) {
			$item['order_number']              = $position + 1;
			$item['name']                      = sprintf( 'Question %d', $position + 1 );
			$item['settings']['question_code'] = 'Q-' . strtoupper( substr( wp_generate_uuid4(), 0, 8 ) );
		}
		unset( $item );
		$saved = DraftWriter::save_many( $items, $quiz_id );
		if ( is_wp_error( $saved ) ) {
			wp_delete_post( $quiz_id, true );
			return $saved;
		}
		return $quiz_id;
	}

	/**
	 * Build one question payload from a CSV row.
	 *
	 * @param array $row  Header-keyed cells.
	 * @param int   $line Line number in the file, for messages.
	 * @return array|\WP_Error
	 */
	private static function item( array $row, $line ) {
		$type = strtolower( str_replace( array( ' ', '_' ), '-', $row['type'] ?? '' ) );
		if ( '' === $type ) {
			$type = 'single-choice';
		}
		if ( ! in_array( $type, self::TYPES, true ) ) {
			/* translators: 1: line number, 2: question type */
			return self::fail( sprintf( __( 'Line %1$d: question type "%2$s" is not supported.', 'ohmylms' ), $line, $type ) );
		}
		$text = $row['question'] ?? '';
		if ( '' === $text ) {
			/* translators: %d: line number */
			return self::fail( sprintf( __( 'Line %d: the question is empty.', 'ohmylms' ), $line ) );
		}
		$points  = isset( $row['points'] ) && '' !== $row['points'] && is_numeric( $row['points'] ) ? max( 0, (float) $row['points'] ) : 1;
		$item    = array(
			// The editor stores the prompt in the description; the name is an automatic "Question N".
			'description' => '<p>' . wp_kses_post( $text ) . '</p>',
			'status'      => 'publish',
			'settings'    => array(
				'type'  => $type,
				'score' => array(
					'enabled' => true,
					'value'   => $points,
				),
			),
		);
		$correct = strtolower( $row['correct'] ?? '' );

		if ( 'true-false' === $type ) {
			if ( ! in_array( $correct, array( 'true', 'false' ), true ) ) {
				/* translators: %d: line number */
				return self::fail( sprintf( __( 'Line %d: "correct" must be true or false.', 'ohmylms' ), $line ) );
			}
			$item['questions'] = array(
				array(
					'answer'     => 'True',
					'is_correct' => 'true' === $correct ? 1 : 0,
				),
				array(
					'answer'     => 'False',
					'is_correct' => 'false' === $correct ? 1 : 0,
				),
			);
			return $item;
		}

		if ( in_array( $type, array( 'short-text', 'long-text' ), true ) ) {
			return $item;
		}

		$options = array();
		for ( $i = 1; $i <= 6; $i++ ) {
			$value = $row[ 'option_' . $i ] ?? '';
			if ( '' !== $value ) {
				$options[ $i ] = $value;
			}
		}
		if ( count( $options ) < 2 ) {
			/* translators: %d: line number */
			return self::fail( sprintf( __( 'Line %d: a choice question needs at least two options.', 'ohmylms' ), $line ) );
		}
		$numbers = array_filter( array_map( 'intval', preg_split( '/[|;]/', $correct ) ) );
		foreach ( $numbers as $number ) {
			if ( ! isset( $options[ $number ] ) ) {
				/* translators: 1: line number, 2: option number */
				return self::fail( sprintf( __( 'Line %1$d: correct option %2$d is empty or missing.', 'ohmylms' ), $line, $number ) );
			}
		}
		if ( ! $numbers || ( 'single-choice' === $type && count( $numbers ) > 1 ) ) {
			/* translators: %d: line number */
			return self::fail( sprintf( 'single-choice' === $type ? __( 'Line %d: a single-choice question needs exactly one correct option.', 'ohmylms' ) : __( 'Line %d: mark at least one correct option.', 'ohmylms' ), $line ) );
		}
		foreach ( $options as $number => $answer ) {
			$item['questions'][] = array(
				'answer'     => $answer,
				'is_correct' => in_array( $number, $numbers, true ) ? 1 : 0,
			);
		}
		return $item;
	}

	/**
	 * Error for a bad row.
	 *
	 * @param string $message Message.
	 * @return \WP_Error
	 */
	private static function fail( $message ) {
		return new \WP_Error( 'ohmylms_quiz_import_row', $message, array( 'status' => 400 ) );
	}
}
