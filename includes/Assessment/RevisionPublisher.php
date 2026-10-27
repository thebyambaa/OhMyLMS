<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Publishes quiz revisions: frozen settings plus ordered slots that reference
 * exact question versions and carry their own marks.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Assessment;

use OhMyLMS\Extensions\Registry;
use OhMyLMS\QuestionBank\VersionPublisher;

defined( 'ABSPATH' ) || exit;

/**
 * Publishes quiz revisions: frozen settings plus ordered slots that reference
 * exact question versions and carry their own marks.
 *
 * Marks and placement belong to the slot; content belongs to the version.
 * Publishing is idempotent: unchanged content reuses the latest revision.
 */
final class RevisionPublisher {
	/** Quiz settings frozen into a revision; everything a delivery or grade depends on. */
	const FROZEN_SETTINGS = array( 'time_limit', 'passing_grade', 'allow_attempts', 'layout', 'presentation', 'player_template', 'question_in_one_page', 'randomize_questions', 'short_text_limit', 'assessment_kind', 'feedback_release', 'late_policy', 'grace_seconds', 'sections' );

	/**
	 * Build the revision that represents the quiz now, storing it if it changed.
	 *
	 * @return array|\WP_Error Revision row with decoded settings and slots.
	 * @param mixed $quiz_id Quiz identifier.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	public static function publish( $quiz_id ) {
		global $wpdb;
		$quiz = ohmylms_get_quiz( (int) $quiz_id );
		if ( ! $quiz || get_post_type( $quiz_id ) !== OHMYLMS_QUIZ_CPT ) {
			return new \WP_Error( 'ohmylms_quiz_missing', __( 'Quiz unavailable.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$raw                          = array_merge( (array) $quiz->get_settings(), AssessmentSettings::frozen( $quiz_id ) );
		$settings                     = array_intersect_key( $raw, array_flip( self::FROZEN_SETTINGS ) );
		$settings['paper_content']    = \OhMyLMS\Quiz\PaperPresentation::content( $quiz_id );
		$settings['timer_minutes']    = (float) $quiz->get_timer();
		$settings['passing_mark']     = (float) $quiz->get_passing_grade();
		$settings['attempts_allowed'] = (int) $quiz->get_take_attempts();
		$settings['kind']             = in_array( $raw['assessment_kind'] ?? 'quiz', array( 'quiz', 'practice', 'exam' ), true ) ? ( $raw['assessment_kind'] ?? 'quiz' ) : 'quiz';
		$sections                     = self::sections( $raw );
		$slots                        = array();
		$total                        = 0.0;
		foreach ( array_values( $quiz->get_questions() ) as $index => $link ) {
			$type = (string) ( $link['settings']['type'] ?? '' );
			if ( ! Registry::get( 'question', $type ) ) {
				return new \WP_Error(
					'quiz_type_missing',
					sprintf( /* translators: %s: text value. */
						__( 'Question "%s" uses an unavailable question type.', 'ohmylms' ),
						wp_strip_all_tags( (string) $link['name'] )
					),
					array( 'status' => 409 )
				);
			}
			if ( ! VersionPublisher::supports_snapshots( $type ) ) {
				return new \WP_Error(
					'quiz_type_unversioned',
					sprintf( /* translators: %s: text value. */
						__( 'The "%s" question type cannot grade frozen question versions yet, so this quiz cannot be published.', 'ohmylms' ),
						$type
					),
					array( 'status' => 409 )
				);
			}
			$version = self::slot_version( $quiz_id, (int) $link['id'] );
			if ( is_wp_error( $version ) ) {
				return $version; }
			$question_settings = json_decode( $version['settings'], true ) ?: array();
			$figure_check      = \OhMyLMS\ExamImport\FigureContent::publishable( $question_settings );
			if ( is_wp_error( $figure_check ) ) {
				return $figure_check;
			}
			$marks      = ! empty( $question_settings['score']['enabled'] ) ? max( 0, (float) ( $question_settings['score']['value'] ?? 0 ) ) : 0.0;
			$slot_marks = $sections['marks'][ (int) $link['id'] ] ?? null;
			if ( null !== $slot_marks ) {
				$marks = max( 0, (float) $slot_marks ); }
			$slots[] = array(
				'slot_no'         => $index + 1,
				'section'         => (string) ( $sections['by_question'][ (int) $link['id'] ] ?? '' ),
				'page'            => 0,
				'question_id'     => (int) $link['id'],
				'version_id'      => (int) $version['id'],
				'marks'           => round( $marks, 4 ),
				'required'        => ! empty( $question_settings['required'] ) ? 1 : 0,
				'shuffle_options' => ! empty( $question_settings['randomize'] ) ? 1 : 0,
				'pool'            => null,
			);
			$total  += $marks;
		}
		// Sections order the paper: unsectioned questions first, then each section in turn.
		$section_order = array();
		foreach ( (array) ( $raw['sections'] ?? array() ) as $index => $section ) {
			foreach ( (array) ( $section['questions'] ?? array() ) as $question_id ) {
				$section_order[ (int) $question_id ] = $index + 1; }
		}
		if ( $section_order ) {
			$position = array_flip( array_column( $slots, 'question_id' ) );
			usort(
				$slots,
				static function ( $left, $right ) use ( $section_order, $position ) {
					return array( $section_order[ $left['question_id'] ] ?? 0, $position[ $left['question_id'] ] ) <=> array( $section_order[ $right['question_id'] ] ?? 0, $position[ $right['question_id'] ] );
				}
			);
			foreach ( $slots as $index => &$slot ) {
				$slot['slot_no'] = $index + 1; }
			unset( $slot );
			// Page breaks: a section marked "new page" starts a new page of the paper.
			$breaks = array();
			foreach ( (array) ( $raw['sections'] ?? array() ) as $index => $section ) {
				if ( ! empty( $section['new_page'] ) ) {
					$breaks[ $index + 1 ] = true; }
			}
			if ( $breaks ) {
				$page     = 1;
				$previous = null;
				foreach ( $slots as $index => &$slot ) {
					$current = $section_order[ $slot['question_id'] ] ?? 0;
					if ( $index > 0 && $current !== $previous && isset( $breaks[ $current ] ) ) {
						++$page; }
					$slot['page'] = $page;
					$previous     = $current;
				}
				unset( $slot );
			}
		}
		$pools = Pools::slots_for( $quiz_id, count( $slots ) );
		if ( is_wp_error( $pools ) ) {
			return $pools; }
		foreach ( $pools as $pool_slot ) {
			$pool_slot['slot_no'] = count( $slots ) + 1;
			$slots[]              = $pool_slot;
			$total               += (float) $pool_slot['marks'];
		}
		if ( ! $slots ) {
			return new \WP_Error( 'quiz_empty', __( 'This quiz has no questions yet.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		$hash      = hash(
			'sha256',
			wp_json_encode(
				array(
					'settings' => $settings,
					'slots'    => $slots,
				)
			)
		);
		$revisions = Schema::table( 'quiz_revisions' );
		$latest    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE quiz_id=%d ORDER BY revision_no DESC LIMIT 1', $revisions, $quiz_id ), ARRAY_A );
		if ( $latest && $latest['content_hash'] === $hash ) {
			return self::revision( (int) $latest['id'] ); }
		$revision_no = $latest ? (int) $latest['revision_no'] + 1 : 1;
		$saved       = $wpdb->insert(
			$revisions,
			array(
				'quiz_id'      => (int) $quiz_id,
				'revision_no'  => $revision_no,
				'kind'         => $settings['kind'],
				'settings'     => wp_json_encode( $settings ),
				'total_marks'  => round( $total, 4 ),
				'content_hash' => $hash,
				'status'       => 'published',
				'created_by'   => get_current_user_id(),
				'created_at'   => current_time( 'mysql', true ),
			)
		);
		if ( ! $saved ) {
			$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT id FROM %i WHERE quiz_id=%d AND content_hash=%s ORDER BY revision_no DESC LIMIT 1', $revisions, $quiz_id, $hash ) );
			if ( $existing ) {
				return self::revision( (int) $existing->id ); }
			throw new \RuntimeException( 'Revision write failed' );
		}
		$revision_id = (int) $wpdb->insert_id;
		foreach ( $slots as $slot ) {
			$slot['revision_id'] = $revision_id;
			$slot['pool']        = null === $slot['pool'] ? null : wp_json_encode( $slot['pool'] );
			if ( ! $wpdb->insert( Schema::table( 'quiz_revision_slots' ), $slot ) ) {
				throw new \RuntimeException( 'Slot write failed' ); }
		}
		if ( $latest ) {
			$wpdb->update(
				$revisions,
				array( 'status' => 'superseded' ),
				array(
					'quiz_id' => (int) $quiz_id,
					'status'  => 'published',
					'id'      => (int) $latest['id'],
				)
			); }
		do_action( 'ohmylms_quiz_revision_published', (int) $quiz_id, $revision_id, $revision_no );
		return self::revision( $revision_id );
	}

	/**
	 * Which version a slot freezes:
	 *  1. a version pinned in this quiz ("use existing version"),
	 *  2. the current version when the quiz owner may edit the question,
	 *  3. otherwise (shared, use-only) the bank's approved version.
	 * Evaluated for the quiz owner, never for the learner starting the attempt.
	 *
	 * @param mixed $quiz_id Quiz identifier.
	 * @param mixed $question_id Question identifier.
	 */
	public static function slot_version( $quiz_id, $question_id ) {
		$pins   = get_post_meta( (int) $quiz_id, '_ohmylms_version_pins', true );
		$pinned = is_array( $pins ) ? (int) ( $pins[ $question_id ] ?? 0 ) : 0;
		if ( $pinned ) {
			$version = VersionPublisher::version( $pinned );
			if ( $version && (int) $version['question_id'] === (int) $question_id ) {
				return $version; }
		}
		$owner    = (int) get_post_field( 'post_author', (int) $quiz_id );
		$bank     = \OhMyLMS\QuestionBank\Banks::of_question( $question_id );
		$editable = user_can( $owner, 'edit_post', $question_id ) || ( $bank && \OhMyLMS\QuestionBank\Banks::can( $bank, 'edit', $owner ) );
		if ( $editable ) {
			$version = VersionPublisher::capture( $question_id );
			return $version ? $version : new \WP_Error( 'quiz_version_failed', __( 'A question version could not be captured.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		$identity = VersionPublisher::identity( $question_id );
		$approved = $identity ? VersionPublisher::version( (int) $identity['approved_version_id'] ) : null;
		if ( ! $approved ) {
			return new \WP_Error(
				'quiz_question_unapproved',
				sprintf( /* translators: %s: text value. */
					__( 'The shared question "%s" has no approved version yet.', 'ohmylms' ),
					wp_strip_all_tags( get_the_title( $question_id ) )
				),
				array(
					'status'      => 409,
					'question_id' => (int) $question_id,
				)
			);
		}
		return $approved;
	}

	/** Optional sectioning: settings.sections = [['title'=>..,'questions'=>[ids],'marks'=>[id=>marks]]].
	 *
	 * @param array $settings Configuration settings.
	 */
	private static function sections( array $settings ) {
		$by_question = array();
		$marks       = array();
		foreach ( (array) ( $settings['sections'] ?? array() ) as $section ) {
			if ( ! is_array( $section ) ) {
				continue; }
			foreach ( (array) ( $section['questions'] ?? array() ) as $question_id ) {
				$by_question[ (int) $question_id ] = sanitize_text_field( (string) ( $section['title'] ?? '' ) ); }
			foreach ( (array) ( $section['marks'] ?? array() ) as $question_id => $value ) {
				if ( is_numeric( $value ) ) {
					$marks[ (int) $question_id ] = (float) $value; }
			}
		}
		foreach ( (array) ( $settings['slot_marks'] ?? array() ) as $question_id => $value ) {
			if ( is_numeric( $value ) ) {
				$marks[ (int) $question_id ] = (float) $value; }
		}
		return array(
			'by_question' => $by_question,
			'marks'       => $marks,
		);
	}

	/**
	 * Revision for Revision publisher.
	 *
	 * @param mixed $revision_id Revision identifier.
	 */
	public static function revision( $revision_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', Schema::table( 'quiz_revisions' ), (int) $revision_id ), ARRAY_A );
		if ( ! $row ) {
			return null; }
		$row['settings'] = json_decode( $row['settings'], true ) ?: array();
		$row['slots']    = self::slots( (int) $row['id'] );
		return $row;
	}

	/**
	 * Slots for Revision publisher.
	 *
	 * @param mixed $revision_id Revision identifier.
	 */
	public static function slots( $revision_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE revision_id=%d ORDER BY slot_no', Schema::table( 'quiz_revision_slots' ), (int) $revision_id ), ARRAY_A );
		foreach ( $rows as &$row ) {
			$row['pool'] = $row['pool'] ? json_decode( $row['pool'], true ) : null; }
		return $rows;
	}

	/**
	 * Latest for Revision publisher.
	 *
	 * @param mixed $quiz_id Quiz identifier.
	 */
	public static function latest( $quiz_id ) {
		global $wpdb;
		$id = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE quiz_id=%d ORDER BY revision_no DESC LIMIT 1', Schema::table( 'quiz_revisions' ), (int) $quiz_id ) );
		return $id ? self::revision( (int) $id ) : null;
	}
}
