<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Question export as import rows: the same CSV or JSON the bulk importer reads.
 *
 * Every question type is covered. The five common types use readable columns; every other type
 * is written as its settings and option rows, which the importer accepts for any registered type.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\QuestionBank;

use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/** Turns stored questions back into rows that import creates again. */
final class RowExporter {

	const MAX_QUESTIONS = 200;

	/** Column order of an exported file. */
	const COLUMNS = array( 'exam', 'paper', 'year', 'section', 'number', 'language', 'type', 'title', 'question', 'answers', 'correct', 'tolerance', 'tolerance_type', 'unit', 'marks', 'explanation', 'skills', 'family', 'difficulty', 'image', 'parts', 'settings', 'options_raw' );

	/** Types written with readable columns; their own columns are left out of `settings`. */
	const READABLE = array( 'single-choice', 'multiple-choice', 'true-false', 'numerical', 'fill-in-the-blank' );

	/**
	 * Export questions as a downloadable file.
	 *
	 * @param int[]  $ids      Question IDs in the order wanted.
	 * @param string $format   json or csv.
	 * @param string $label    Exam or collection name written into every row; it identifies the questions when the file is imported again.
	 * @param array  $sections Optional map of question ID to section name.
	 * @return array|\WP_Error filename, mime, content, count and warnings.
	 */
	public static function export( array $ids, $format, $label, array $sections = array() ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		if ( ! $ids || count( $ids ) > self::MAX_QUESTIONS ) {
			/* translators: %d: maximum questions per file. */
			return new \WP_Error( 'ohmylms_export_count', sprintf( __( 'Choose between 1 and %d questions to export.', 'ohmylms' ), self::MAX_QUESTIONS ), array( 'status' => 400 ) );
		}
		$format = 'csv' === $format ? 'csv' : 'json';
		foreach ( $ids as $id ) {
			if ( OHMYLMS_QUESTION_CPT !== get_post_type( $id ) ) {
				/* translators: %d: question ID. */
				return new \WP_Error( 'ohmylms_export_question', sprintf( __( 'Question %d does not exist.', 'ohmylms' ), $id ), array( 'status' => 404 ) );
			}
			// The file holds every answer key, so only people who may edit a question can export it.
			if ( ! AccessPolicy::can_edit_question( $id ) ) {
				/* translators: %d: question ID. */
				return AccessPolicy::denied( sprintf( __( 'You are not allowed to export question %d.', 'ohmylms' ), $id ) );
			}
		}
		$label    = '' === trim( (string) $label ) ? __( 'OhMyLMS export', 'ohmylms' ) : trim( (string) $label );
		$rows     = array();
		$warnings = array();
		foreach ( $ids as $index => $id ) {
			$row = self::row( $id, $label, $index + 1, (string) ( $sections[ $id ] ?? '' ), $warnings );
			if ( $row ) {
				$rows[] = $row;
			}
		}
		$name = sanitize_file_name( $label );
		$name = '' !== $name ? $name : 'questions';
		return array(
			'filename' => $name . '.' . $format,
			'mime'     => 'csv' === $format ? 'text/csv' : 'application/json',
			'content'  => 'csv' === $format ? self::csv( $rows ) : wp_json_encode( $rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			'count'    => count( $rows ),
			'warnings' => $warnings,
		);
	}

	/**
	 * One question as an import row.
	 *
	 * @param int      $id       Question ID.
	 * @param string   $label    Exam name written into the row.
	 * @param int      $number   Question number within the file.
	 * @param string   $section  Section name, or an empty string.
	 * @param string[] $warnings Problems found, added to.
	 * @return array|null Row, or null when the question cannot be read.
	 */
	public static function row( $id, $label, $number, $section, array &$warnings ) {
		$question = ohmylms_get_question( (int) $id );
		if ( ! $question ) {
			return null;
		}
		$settings = (array) $question->get_settings();
		$type     = (string) ( $settings['type'] ?? '' );
		$options  = (array) $question->get_questions();
		$bank     = BankAttributes::get( (int) $id );
		$name     = (string) $question->get_name();
		$body     = (string) $question->get_description();
		$map      = SkillMap::current( (int) $id );
		$row      = array(
			'exam'        => $label,
			'paper'       => '',
			'year'        => '',
			'section'     => $section,
			'number'      => (string) $number,
			'language'    => 'en',
			'type'        => $type,
			'title'       => $name,
			'question'    => 'fill-in-the-blank' === $type ? $name : ( '' !== $body ? $body : $name ),
			'explanation' => (string) ( $settings['explanation'] ?? '' ),
			'family'      => (string) ( $bank['family_id'] ?? '' ),
			'difficulty'  => (string) ( $bank['difficulty'] ?? 'standard' ),
		);
		$image    = (int) $question->get_thumbnail_id();
		if ( $image ) {
			$url = wp_get_attachment_url( $image );
			if ( $url ) {
				$row['image'] = $url;
			}
		}
		$extras = array_diff_key( $settings, array_flip( array( 'type', 'score', 'explanation' ) ) );
		if ( in_array( $type, self::READABLE, true ) ) {
			$value = $settings['score'] ?? null;
			$marks = is_array( $value ) ? ( ! empty( $value['enabled'] ) ? ( $value['value'] ?? '' ) : 0 ) : $value;
			if ( null !== $marks && '' !== $marks ) {
				$row['marks'] = (string) $marks;
			}
		}
		switch ( $type ) {
			case 'single-choice':
			case 'multiple-choice':
			case 'true-false':
				$texts   = array_map(
					static function ( $option ) {
						return (string) $option['answer'];
					},
					$options
				);
				$correct = array();
				$plain   = true;
				foreach ( array_values( $options ) as $index => $option ) {
					if ( ! empty( $option['is_correct'] ) ) {
						$correct[] = 'true-false' === $type ? ( 0 === $index ? 'true' : 'false' ) : (string) ( $index + 1 );
					}
					if ( false !== strpos( $texts[ $index ], '|' ) || ! empty( $option['thumbnail_id'] ) || ! empty( $option['image_url'] ) || ! empty( $option['matching_data'] ) ) {
						$plain = false;
					}
				}
				if ( $plain ) {
					$row['answers'] = implode( '|', $texts );
					$row['correct'] = implode( '|', $correct );
				} else {
					$row['options_raw'] = self::option_rows( $options );
				}
				break;
			case 'numerical':
				$row['correct'] = (string) ( $settings['answer'] ?? '' );
				if ( isset( $settings['tolerance'] ) ) {
					$row['tolerance'] = (string) $settings['tolerance'];
				}
				if ( isset( $settings['tolerance_type'] ) ) {
					$row['tolerance_type'] = (string) $settings['tolerance_type'];
				}
				if ( isset( $settings['unit'] ) && '' !== $settings['unit'] ) {
					$row['unit'] = (string) $settings['unit'];
				}
				unset( $extras['answer'], $extras['tolerance'], $extras['tolerance_type'], $extras['unit'] );
				break;
			case 'fill-in-the-blank':
				break;
			case 'structured':
				$parts = array();
				foreach ( (array) ( $settings['parts'] ?? array() ) as $part ) {
					if ( is_array( $part ) ) {
						$part['skills'] = self::tokens( $map[ $part['id'] ?? '' ] ?? array() );
						$parts[]        = $part;
					}
				}
				$row['parts'] = $parts;
				unset( $extras['parts'] );
				$extras = array_diff_key( $extras, array_flip( array( 'score' ) ) );
				break;
			default:
				// Any other type is its settings, score included, plus its option rows.
				$extras = $settings;
				unset( $extras['explanation'] );
				if ( $options ) {
					$row['options_raw'] = self::option_rows( $options );
				}
		}
		if ( 'structured' !== $type ) {
			$row['skills'] = implode( ';', self::tokens( $map['p1'] ?? ( $map ? reset( $map ) : array() ) ) );
			if ( '' === $row['skills'] ) {
				/* translators: %d: question number in the file. */
				$warnings[] = sprintf( __( 'Question %d has no skill. Add one before importing the file, because questions without skills are refused.', 'ohmylms' ), $number );
			}
		}
		if ( $extras ) {
			$row['settings'] = $extras;
		}
		return $row;
	}

	/**
	 * Skill codes for a part's mapping, primary first. A skill without a code is written by its UUID.
	 *
	 * @param array $roles primary and supporting skill term IDs.
	 * @return string[]
	 */
	private static function tokens( array $roles ) {
		$ids    = array_filter( array_merge( array( (int) ( $roles['primary'] ?? 0 ) ), array_map( 'intval', (array) ( $roles['supporting'] ?? array() ) ) ) );
		$tokens = array();
		foreach ( $ids as $term_id ) {
			$term = get_term( $term_id, Taxonomy::NAME );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}
			$code     = (string) get_term_meta( $term_id, SkillCatalogue::CODE_META, true );
			$uuid     = (string) get_term_meta( $term_id, SkillCatalogue::UUID_META, true );
			$tokens[] = '' !== $code && self::unique_code( $code ) ? $code : ( '' !== $uuid ? $uuid : '#' . $term_id );
		}
		return $tokens;
	}

	/**
	 * Whether one skill carries this code. The importer refuses a code shared by several skills.
	 *
	 * @param string $code Skill code.
	 * @return bool
	 */
	private static function unique_code( $code ) {
		global $wpdb;
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT m.term_id) FROM {$wpdb->termmeta} m JOIN {$wpdb->term_taxonomy} tt ON tt.term_id=m.term_id AND tt.taxonomy=%s WHERE m.meta_key IN (%s, %s) AND m.meta_value=%s", Taxonomy::NAME, SkillCatalogue::CODE_META, SkillCatalogue::UUID_META, $code ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only lookup that mirrors the importer's own.
		return 1 === $count;
	}

	/**
	 * Option rows with only what the importer accepts.
	 *
	 * @param array $options Stored options.
	 * @return array[]
	 */
	private static function option_rows( array $options ) {
		$rows = array();
		foreach ( array_values( $options ) as $option ) {
			$row = array(
				'answer'     => (string) $option['answer'],
				'is_correct' => ! empty( $option['is_correct'] ),
			);
			foreach ( array( 'thumbnail_id', 'image_url', 'matching_data' ) as $field ) {
				if ( ! empty( $option[ $field ] ) ) {
					$row[ $field ] = $option[ $field ];
				}
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	 * CSV text with a header row. Lists and objects are written as JSON cells.
	 *
	 * @param array[] $rows Export rows.
	 * @return string
	 */
	public static function csv( array $rows ) {
		$used = array();
		foreach ( $rows as $row ) {
			$used += array_fill_keys( array_keys( $row ), true );
		}
		$header = array_values(
			array_filter(
				self::COLUMNS,
				static function ( $column ) use ( $used ) {
					return isset( $used[ $column ] );
				}
			)
		);
		$handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- In-memory stream.
		fputcsv( $handle, $header, ',', '"', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- In-memory stream.
		foreach ( $rows as $row ) {
			$line = array();
			foreach ( $header as $column ) {
				$value  = $row[ $column ] ?? '';
				$line[] = is_array( $value ) ? wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : (string) $value;
			}
			fputcsv( $handle, $line, ',', '"', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- In-memory stream.
		}
		rewind( $handle );
		$text = stream_get_contents( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- In-memory stream.
		return "\xEF\xBB\xBF" . $text;
	}
}
