<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * The question list of a quiz: placements, version pins, marks overrides, sections and its revision.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Quiz;

use OhMyLMS\Assessment\AssessmentSettings;
use OhMyLMS\Assessment\ErrorException;
use OhMyLMS\Assessment\Pools;
use OhMyLMS\Utility\Transaction;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Storage of a quiz's questions behind `ohmylms/v1/quizzes/{id}/questions` (docs/api/quizzes.md): the
 * placement rows in `ohmylms_quiz_questions_relationship`, the pinned version of each question, the
 * per-question marks overrides (`slot_marks` in `_quiz_settings`) and the sections of the assessment
 * settings. State used for the revision is read from the database, never the meta cache, so a cached
 * copy cannot hide a concurrent change. `change()` locks the quiz row, compares the revision and runs a
 * write in one transaction.
 */
final class Questions {

	/**
	 * Quiz meta holding the pinned version of each question.
	 */
	const PINS_META = '_ohmylms_version_pins';

	/**
	 * Quiz meta of the legacy settings blob, which holds `slot_marks`.
	 */
	const SETTINGS_META = '_quiz_settings';

	/**
	 * Placement table.
	 *
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . OHMYLMS_QUIZ_QUESTION_RELATIONSHIP;
	}

	/**
	 * Run a write on the question list of a quiz: lock the quiz row, compare the revision the editor
	 * loaded with the stored one and run the work, all in one transaction. A different revision raises
	 * `ohmylms_quiz_questions_conflict` (409, with the current revision) as an `ErrorException`.
	 *
	 * @param int      $quiz_id  Quiz ID.
	 * @param string   $revision Revision the editor loaded.
	 * @param callable $work     Receives the current question ids and pins; its result is returned.
	 * @return mixed
	 * @throws ErrorException When the revision is stale, or when the work raises one.
	 */
	public static function change( $quiz_id, $revision, callable $work ) {
		return Transaction::run(
			static function () use ( $quiz_id, $revision, $work ) {
				global $wpdb;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Row lock that serialises writers of this quiz; must not be cached.
				$wpdb->query( $wpdb->prepare( 'SELECT ID FROM %i WHERE ID = %d FOR UPDATE', $wpdb->posts, $quiz_id ) );
				$ids     = self::ids( $quiz_id );
				$pins    = self::pins( $quiz_id );
				$current = self::revision( $ids, $pins, self::layout( $quiz_id ) );
				if ( ! hash_equals( $current, (string) $revision ) ) {
					ErrorException::raise(
						new WP_Error(
							'ohmylms_quiz_questions_conflict',
							__( 'The questions of this quiz were changed by someone else. Reload them before making more changes.', 'ohmylms' ),
							array(
								'status'   => 409,
								'revision' => $current,
							)
						)
					);
				}

				return $work( $ids, $pins );
			}
		);
	}

	/**
	 * Question IDs of a quiz in order, read from the database (each question once).
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return int[]
	 */
	public static function ids( $quiz_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Read fresh for the revision check.
		$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT question_id FROM %i WHERE quiz_id = %d GROUP BY question_id ORDER BY MIN(order_number) ASC, MIN(id) ASC', self::table(), $quiz_id ) );
		return array_map( 'intval', (array) $rows );
	}

	/**
	 * Number of placement rows of each quiz, with one query.
	 *
	 * @param int[] $quiz_ids Quiz IDs.
	 * @return array<int,int> Quizzes without questions are absent.
	 */
	public static function counts( array $quiz_ids ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'absint', $quiz_ids ) ) );
		if ( ! $ids ) {
			return array();
		}
		$holders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- Placeholders are generated from the id count.
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT quiz_id, COUNT(*) AS total FROM %i WHERE quiz_id IN ({$holders}) GROUP BY quiz_id", array_merge( array( self::table() ), $ids ) ) );
		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row->quiz_id ] = (int) $row->total;
		}
		return $counts;
	}

	/**
	 * Store a new order (call inside `change()`).
	 *
	 * @param int   $quiz_id Quiz ID.
	 * @param int[] $order   Every question ID of the quiz, in the new order.
	 * @throws \RuntimeException When a row cannot be written.
	 */
	public static function reorder( $quiz_id, array $order ) {
		global $wpdb;
		foreach ( $order as $index => $question_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Placement table has no API; written inside the locked transaction.
			$updated = $wpdb->update(
				self::table(),
				array( 'order_number' => $index + 1 ),
				array(
					'quiz_id'     => (int) $quiz_id,
					'question_id' => (int) $question_id,
				),
				array( '%d' ),
				array( '%d', '%d' )
			);
			if ( false === $updated ) {
				throw new \RuntimeException( 'Order update failed' );
			}
		}
	}

	/**
	 * Put another question in the placement of a question (call inside `change()`).
	 *
	 * @param int $quiz_id   Quiz ID.
	 * @param int $source_id Question being replaced.
	 * @param int $copy_id   Question taking its place.
	 * @throws \RuntimeException When the placement cannot be written.
	 */
	public static function replace( $quiz_id, $source_id, $copy_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Placement table has no API; written inside the locked transaction.
		$updated = $wpdb->update(
			self::table(),
			array( 'question_id' => (int) $copy_id ),
			array(
				'quiz_id'     => (int) $quiz_id,
				'question_id' => (int) $source_id,
			),
			array( '%d' ),
			array( '%d', '%d' )
		);
		if ( ! $updated ) {
			throw new \RuntimeException( 'Placement update failed' );
		}
	}

	/**
	 * A quiz meta value read from the database, so a cached copy cannot hide a concurrent change.
	 *
	 * @param int    $quiz_id Quiz ID.
	 * @param string $key     Meta key.
	 * @return mixed Unserialized value, or null when unset.
	 */
	private static function fresh_meta( $quiz_id, $key ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Deliberately bypasses the meta cache (see the method description).
		$raw = $wpdb->get_var( $wpdb->prepare( 'SELECT meta_value FROM %i WHERE post_id = %d AND meta_key = %s ORDER BY meta_id ASC LIMIT 1', $wpdb->postmeta, $quiz_id, $key ) );
		return null === $raw ? null : maybe_unserialize( $raw );
	}

	/**
	 * Pins of a quiz, read from the database.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return array<int,int> Version ID by question ID.
	 */
	public static function pins( $quiz_id ) {
		$raw  = self::fresh_meta( $quiz_id, self::PINS_META );
		$pins = array();
		foreach ( is_array( $raw ) ? $raw : array() as $question_id => $version_id ) {
			if ( (int) $question_id > 0 && (int) $version_id > 0 ) {
				$pins[ (int) $question_id ] = (int) $version_id;
			}
		}
		ksort( $pins );
		return $pins;
	}

	/**
	 * Store the pins of a quiz; no pins removes the meta.
	 *
	 * @param int   $quiz_id Quiz ID.
	 * @param array $pins    Version ID by question ID.
	 */
	public static function save_pins( $quiz_id, array $pins ) {
		$pins = array_filter( array_map( 'intval', $pins ) );
		ksort( $pins );
		if ( $pins ) {
			update_post_meta( $quiz_id, self::PINS_META, $pins );
		} else {
			delete_post_meta( $quiz_id, self::PINS_META );
		}
	}

	/**
	 * Marks overrides by question (`slot_marks` of the quiz settings), read from the database.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return array<int,float>
	 */
	public static function slot_marks( $quiz_id ) {
		$settings = self::fresh_meta( $quiz_id, self::SETTINGS_META );
		$marks    = array();
		foreach ( (array) ( is_array( $settings ) ? ( $settings['slot_marks'] ?? array() ) : array() ) as $question_id => $value ) {
			if ( (int) $question_id > 0 && is_numeric( $value ) ) {
				$marks[ (int) $question_id ] = round( max( 0, (float) $value ), 4 );
			}
		}
		ksort( $marks );
		return $marks;
	}

	/**
	 * Store or clear the marks override of one question.
	 *
	 * @param int        $quiz_id     Quiz ID.
	 * @param int        $question_id Question ID.
	 * @param float|null $marks       Marks, or null to clear.
	 */
	public static function save_slot_marks( $quiz_id, $question_id, $marks ) {
		$settings = self::fresh_meta( $quiz_id, self::SETTINGS_META );
		$settings = is_array( $settings ) ? $settings : array();
		$current  = is_array( $settings['slot_marks'] ?? null ) ? $settings['slot_marks'] : array();
		if ( null === $marks && ! array_key_exists( $question_id, $current ) ) {
			return;
		}
		if ( null === $marks ) {
			unset( $current[ $question_id ] );
		} else {
			$current[ $question_id ] = round( $marks, 4 );
		}
		if ( $current ) {
			$settings['slot_marks'] = $current;
		} else {
			unset( $settings['slot_marks'] );
		}
		update_post_meta( $quiz_id, self::SETTINGS_META, $settings );
	}

	/**
	 * Sections of the quiz as stored in the assessment settings, normalized and read from the database.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return array[] {title, questions: int[], marks: array<int,float>, new_page: bool}
	 */
	public static function sections( $quiz_id ) {
		$settings = self::fresh_meta( $quiz_id, AssessmentSettings::META );
		$sections = array();
		foreach ( (array) ( is_array( $settings ) ? ( $settings['sections'] ?? array() ) : array() ) as $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}
			$marks = array();
			foreach ( (array) ( $section['marks'] ?? array() ) as $question_id => $value ) {
				if ( is_numeric( $value ) ) {
					$marks[ (int) $question_id ] = (float) $value;
				}
			}
			$sections[] = array(
				'title'     => (string) ( $section['title'] ?? '' ),
				'questions' => array_values( array_map( 'intval', (array) ( $section['questions'] ?? array() ) ) ),
				'marks'     => $marks,
				'new_page'  => ! empty( $section['new_page'] ),
			);
		}
		return $sections;
	}

	/**
	 * Per-question marks, sections and pools of a quiz, read from the database.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return array{marks:array<int,float>,sections:array[],pools:array}
	 */
	public static function layout( $quiz_id ) {
		$pools = self::fresh_meta( $quiz_id, Pools::META );
		return array(
			'marks'    => self::slot_marks( $quiz_id ),
			'sections' => self::sections( $quiz_id ),
			'pools'    => is_array( $pools ) ? array_values( $pools ) : array(),
		);
	}

	/**
	 * Revision token of a question list: changes whenever the order, the members, the pins, the
	 * per-question marks, the sections or the random pools change, whichever route or writer changed them.
	 *
	 * @param int[]          $ids    Question IDs in order.
	 * @param array<int,int> $pins   Pins.
	 * @param array          $layout Marks, sections and pools ({@see layout()}).
	 * @return string 16 hexadecimal characters.
	 */
	public static function revision( array $ids, array $pins, array $layout ) {
		$pairs = array();
		foreach ( $pins as $question_id => $version_id ) {
			$pairs[] = array( (int) $question_id, (int) $version_id );
		}
		$marks = array();
		foreach ( $layout['marks'] as $question_id => $value ) {
			$marks[] = array( (int) $question_id, (float) $value );
		}
		return substr( hash( 'sha256', (string) wp_json_encode( array( array_map( 'intval', $ids ), $pairs, $marks, $layout['sections'], $layout['pools'] ) ) ), 0, 16 );
	}
}
