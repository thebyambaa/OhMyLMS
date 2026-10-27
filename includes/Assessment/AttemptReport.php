<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Teacher-facing attempt report built from what the learner actually saw:
 * the frozen items, versions and marks of the attempt, never current content.
 * The output keeps the legacy report shape used by the quiz report UI.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Assessment;

use OhMyLMS\QuestionBank\VersionPublisher;

defined( 'ABSPATH' ) || exit;

/**
 * Teacher-facing attempt report built from what the learner actually saw:
 * the frozen items, versions and marks of the attempt, never current content.
 * The output keeps the legacy report shape used by the quiz report UI.
 */
final class AttemptReport {
	/**
	 * Maximum marks and passing mark an attempt is judged against: the frozen revision
	 * for versioned attempts, the current quiz settings for legacy attempts.
	 *
	 * @param mixed $attempt_id Attempt identifier.
	 * @param mixed $quiz Quiz.
	 */
	public static function basis( $attempt_id, $quiz ) {
		$context = Schema::ready() ? AttemptItems::context( $attempt_id ) : null;
		if ( $context ) {
			$revision = RevisionPublisher::revision( (int) $context['revision_id'] );
			if ( $revision ) {
				return array(
					'max'       => (float) $revision['total_marks'],
					'passing'   => (float) ( $revision['settings']['passing_mark'] ?? 0 ),
					'versioned' => true,
				); }
		}
		return array(
			'max'       => (float) $quiz->get_total_marks(),
			'passing'   => (float) $quiz->get_passing_grade(),
			'versioned' => false,
		);
	}

	/**
	 * Parts for Attempt report.
	 *
	 * @param mixed            $attempt_id Attempt identifier.
	 * @param array            $item Item.
	 * @param QuestionSnapshot $snapshot Snapshot.
	 */
	public static function parts( $attempt_id, array $item, QuestionSnapshot $snapshot ) {
		global $wpdb;
		$weights = Structured::weights( $snapshot->get_settings() );
		$awards  = array();
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT part_id, awarded, grader FROM %i WHERE source_type=\'quiz\' AND source_id=%d AND item_id=%d AND superseded_by=0 AND part_id<>\'\'', Schema::table( 'grade_events' ), (int) $attempt_id, (int) $item['id'] ), ARRAY_A ) as $event ) {
			$awards[ $event['part_id'] ] = array(
				'awarded' => (float) $event['awarded'],
				'grader'  => $event['grader'],
			);
		}
		$parts = array();
		foreach ( Structured::parts( $snapshot->get_settings() ) as $part ) {
			$parts[ $part['id'] ] = array(
				'max'     => round( (float) $item['marks'] * ( $weights[ $part['id'] ] ?? 0 ), 4 ),
				'awarded' => $awards[ $part['id'] ]['awarded'] ?? null,
				'grader'  => $awards[ $part['id'] ]['grader'] ?? null,
				'kind'    => $part['kind'],
			);
		}
		return $parts;
	}

	/**
	 * Versioned for Attempt report.
	 *
	 * @param mixed $quiz_id Quiz identifier.
	 * @param mixed $attempt_id Attempt identifier.
	 */
	public static function versioned( $quiz_id, $attempt_id ) {
		global $wpdb;
		$answers = array();
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE quiz_attempt_id=%d', $wpdb->prefix . 'ohmylms_quiz_attempts_answers', $attempt_id ), ARRAY_A ) as $row ) {
			$answers[ (int) $row['question_id'] ] = $row;
		}
		$context   = AttemptItems::context( $attempt_id );
		$questions = array();
		$total     = 0.0;
		foreach ( AttemptItems::items( $attempt_id ) as $item ) {
			$snapshot = AttemptItems::snapshot( $item );
			if ( ! $snapshot ) {
				continue; }
			$row     = $answers[ (int) $item['question_id'] ] ?? null;
			$options = array();
			$by_id   = array();
			foreach ( $snapshot->get_questions() as $option ) {
				$by_id[ (int) $option['id'] ] = $option + array( 'question_id' => $snapshot->get_id() ); }
			foreach ( $item['option_order'] ? $item['option_order'] : array_keys( $by_id ) as $option_id ) {
				if ( isset( $by_id[ (int) $option_id ] ) ) {
					$options[] = $by_id[ (int) $option_id ]; }
			}
			$settings = $snapshot->get_settings();
			if ( $snapshot->get_type() === 'fill-in-the-blank' ) {
				$inline = InlineBlanks::parse( $snapshot->get_name() );
				if ( $inline['answers'] ) {
					$options = array_map(
						static function ( $answer ) {
							return array(
								'answer'     => $answer,
								'is_correct' => '1',
							);
						},
						$inline['answers']
					);
				}
			}
			$settings['score'] = array(
				'enabled' => true,
				'value'   => (float) $item['marks'],
			);
			$identity          = VersionPublisher::identity( $snapshot->get_id(), false );
			$status            = 'needs-review' === $item['status'] && empty( $row['is_manually_reviewed'] ) ? 'in-review' : ( $row ? 'graded' : 'in-review' );
			$achieved          = $row ? (float) $row['achive_mark'] : 0.0;
			$total            += $achieved;
			$questions[]       = array(
				'id'                       => $snapshot->get_id(),
				'quiz_id'                  => (int) $quiz_id,
				'name'                     => $snapshot->get_name(),
				'description'              => $snapshot->get_description(),
				'order_number'             => (int) $item['position'],
				'thumbnail_id'             => $snapshot->get_thumbnail_id(),
				'video_id'                 => $snapshot->get_video_id(),
				'video_src'                => $snapshot->get_video_url(),
				'image_src'                => $snapshot->get_image_url(),
				'settings'                 => $settings,
				'questions'                => $options,
				'given_answer'             => $row ? maybe_unserialize( $row['given_answer'] ) : null,
				'status'                   => $status,
				'image'                    => $snapshot->get_image_url(),
				'video'                    => $snapshot->get_video_url(),
				'achive_mark'              => $achieved,
				// Server verdict (null while awaiting review) so reports need not re-grade client-side.
				'correct'                  => null === $item['correct'] ? null : (bool) (int) $item['correct'],
				// Structured questions: current mark and maximum for each part.
				'parts'                    => in_array( $snapshot->get_type(), array( 'structured', 'passage' ), true ) ? self::parts( $attempt_id, $item, $snapshot ) : null,
				'quiz_attempts_answers_id' => $row ? (int) $row['id'] : null,
				'item_id'                  => (int) $item['id'],
				'section'                  => (string) ( $item['display']['section'] ?? '' ),
				// The numbers this learner was given, for a randomised template question.
					'instance'             => $snapshot->get_instance(),
				'version'                  => array(
					'id'                 => $snapshot->get_version_id(),
					'number'             => $snapshot->get_version_no(),
					'uuid'               => $snapshot->get_uuid(),
					'migration_snapshot' => $snapshot->is_migration_snapshot(),
					'is_current'         => $identity && (int) $identity['current_version_id'] === $snapshot->get_version_id(),
				),
				'grade_history'            => array_map(
					static function ( $event ) {
						return array_intersect_key( $event, array_flip( array( 'id', 'awarded', 'max_marks', 'grader', 'reviewer_id', 'reason', 'created_at', 'superseded_by' ) ) );
					},
					GradeEvents::history( 'quiz', (int) $attempt_id, (int) $item['id'] )
				),
			);
		}
		$revision = RevisionPublisher::revision( (int) $context['revision_id'] );
		return array(
			'questions'            => $questions,
			'quiz_attempt_id'      => (int) $attempt_id,
			'status'               => $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM %i WHERE id=%d AND quiz_id=%d', $wpdb->prefix . 'ohmylms_quiz_attempts', $attempt_id, $quiz_id ) ),
			'total_achieved_marks' => round( $total, 4 ),
			'engine'               => 'versioned',
			'scoring'              => $context['scoring'],
			'revision'             => $revision ? array(
				'id'           => (int) $revision['id'],
				'number'       => (int) $revision['revision_no'],
				'total_marks'  => (float) $revision['total_marks'],
				'passing_mark' => (float) ( $revision['settings']['passing_mark'] ?? 0 ),
			) : null,
			'deadline_at'          => $context['deadline_at'],
			'finalize_reason'      => $context['finalize_reason'],
		);
	}
}
