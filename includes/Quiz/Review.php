<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Shared manual-review writer for both legacy report endpoints.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Quiz;

use OhMyLMS\Assessment\AttemptItems;
use OhMyLMS\Assessment\ErrorException;
use OhMyLMS\Assessment\GradeEvents;
use OhMyLMS\Assessment\Grader;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Assessment\Scoring;
use OhMyLMS\Assessment\Structured;
use OhMyLMS\Extensions\Registry;
use OhMyLMS\Data\Student;
use OhMyLMS\QuestionBank\VersionPublisher;
use OhMyLMS\Utility\Transaction;

/**
 * Shared manual-review writer for both legacy report endpoints.
 *
 * Versioned attempts are reviewed against their frozen items: maximum marks, question
 * type and scoring policy come from the attempt, and every changed item records a grade
 * event (with reviewer and reason) even when the attempt total does not change.
 */
final class Review {
	/**
	 * Save for Review.
	 *
	 * @param mixed  $quiz_id Quiz identifier.
	 * @param mixed  $attempt_id Attempt identifier.
	 * @param array  $marks Marks.
	 * @param string $reason Reason.
	 */
	public static function save( $quiz_id, $attempt_id, array $marks, $reason = '' ) {
		global $wpdb;
		if ( ! GradingPolicy::can_grade( $quiz_id ) ) {
			return new \WP_Error( 'quiz_permission', 'You cannot grade this quiz.', array( 'status' => rest_authorization_required_code() ) );
		}
		$quiz = ohmylms_get_quiz( $quiz_id );
		if ( ! $quiz ) {
			return new \WP_Error( 'quiz_missing', 'Quiz unavailable.', array( 'status' => 404 ) );
		}
		$reviewer = get_current_user_id();
		$reason   = sanitize_textarea_field( (string) $reason );
		$changed  = array();
		try {
			$result = Transaction::run(
				static function () use ( $wpdb, $quiz_id, $attempt_id, $marks, $reviewer, $reason, &$changed ) {
					$attempt = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d AND quiz_id=%d FOR UPDATE', $wpdb->prefix . 'ohmylms_quiz_attempts', $attempt_id, $quiz_id ), ARRAY_A );
					if ( ! $attempt || ! in_array( $attempt['status'], array( 'in-review', 'completed' ), true ) ) {
						throw new ErrorException( new \WP_Error( 'quiz_attempt', 'Submitted attempt unavailable.', array( 'status' => 409 ) ) );
					}
					$rows        = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE quiz_attempt_id=%d', $wpdb->prefix . 'ohmylms_quiz_attempts_answers', $attempt_id ), ARRAY_A );
					$by_question = array_column( $rows, null, 'question_id' );
					foreach ( $marks as $id => $mark ) {
						// Structured questions may be marked per part: [part_id => marks].
						$sum = is_array( $mark ) ? array_sum( array_map( 'floatval', array_filter( $mark, 'is_numeric' ) ) ) : $mark;
						if ( ! isset( $by_question[ $id ] ) || ! is_numeric( $sum ) || ! is_finite( (float) $sum ) || $sum < 0 || $sum > $by_question[ $id ]['question_marks'] + 0.00005 || ( is_array( $mark ) && count( array_filter( $mark, 'is_numeric' ) ) !== count( $mark ) ) ) {
							throw new ErrorException( new \WP_Error( 'quiz_marks', 'Invalid question or marks.', array( 'status' => 422 ) ) );
						}
					}
					$context = AttemptItems::context( $attempt_id );
					$policy  = $context['scoring'] ?? Scoring::LEGACY;
					$items   = array();
					if ( $context ) {
						foreach ( AttemptItems::items( $attempt_id ) as $item ) {
							$items[ (int) $item['question_id'] ] = $item;
						}
					}
					$total   = 0.0;
					$pending = false;
					foreach ( $rows as $row ) {
						$id   = (int) $row['question_id'];
						$item = $items[ $id ] ?? null;
						if ( array_key_exists( $id, $marks ) ) {
							$before       = (float) $row['achive_mark'];
							$was_reviewed = ! empty( $row['is_manually_reviewed'] );
							$part_marks   = null;
							if ( $item ) {
								$version = VersionPublisher::version( $item['version_id'] );
								if ( $version && in_array( $version['type'], array( 'structured', 'passage' ), true ) ) {
									$distribution = self::distribute_parts( $attempt_id, $item, ( AttemptItems::snapshot( $item ) ? AttemptItems::snapshot( $item )->get_settings() : array() ), $marks[ $id ], (float) $row['question_marks'], $policy );
									if ( is_wp_error( $distribution ) ) {
										throw new ErrorException( $distribution );
									}
									$part_marks   = $distribution['parts'];
									$marks[ $id ] = $distribution['total'];
								}
							}
							$row['achive_mark']          = Scoring::manual( $marks[ $id ], $row['question_marks'], $policy );
							$row['is_manually_reviewed'] = 1;
							$row['is_correct']           = $row['achive_mark'] > 0 && $row['achive_mark'] >= $row['question_marks'] ? 1 : 0;
							if ( $wpdb->update( $wpdb->prefix . 'ohmylms_quiz_attempts_answers', array_intersect_key( $row, array_flip( array( 'achive_mark', 'is_manually_reviewed', 'is_correct' ) ) ), array( 'id' => $row['id'] ) ) === false ) {
								throw new \RuntimeException( 'Answer update failed' );
							}
							$differs = $before !== (float) $row['achive_mark'] || ! $was_reviewed;
							if ( $item && null !== $part_marks ) {
								$max      = (float) $row['question_marks'];
								$fraction = $max > 0 ? min( 1, (float) $row['achive_mark'] / $max ) : 0;
								$wpdb->update(
									Schema::table( 'attempt_items' ),
									array(
										'awarded'   => $row['achive_mark'],
										'fraction'  => $fraction,
										'correct'   => $row['is_correct'],
										'status'    => 'graded',
										'graded_at' => current_time( 'mysql', true ),
									),
									array( 'id' => (int) $item['id'] )
								);
								$weights = \OhMyLMS\Assessment\Structured::weights( json_decode( VersionPublisher::version( $item['version_id'] )['settings'], true ) ?: array() );
								foreach ( $part_marks as $part_id => $awarded ) {
									$current = self::current_part( $attempt_id, (int) $item['id'], $part_id );
									if ( $current && abs( (float) $current['awarded'] - $awarded ) < 0.00005 && 'manual' === $current['grader'] ) {
										continue;
									}
									$part_max  = $max * ( $weights[ $part_id ] ?? 0 );
									$event_id  = GradeEvents::record(
										array(
											'source_type' => 'quiz',
											'source_id'   => (int) $attempt_id,
											'item_id'     => (int) $item['id'],
											'part_id'     => (string) $part_id,
											'student_id'  => (int) $attempt['student_id'],
											'question_id' => $id,
											'version_id'  => (int) $item['version_id'],
											'awarded'     => $awarded,
											'max_marks'   => $part_max,
											'fraction'    => $part_max > 0 ? min( 1, $awarded / $part_max ) : 0,
											'correct'     => $part_max > 0 && $awarded >= $part_max ? 1 : 0,
											'grader'      => 'manual',
											'reviewer_id' => $reviewer,
											'reason'      => $reason,
										)
									);
									$changed[] = array(
										'question_id'    => $id,
										'item_id'        => (int) $item['id'],
										'part_id'        => (string) $part_id,
										'before'         => $current ? (float) $current['awarded'] : null,
										'after'          => $awarded,
										'grade_event_id' => $event_id,
									);
								}
							} elseif ( $item && ( $differs || 'needs-review' === $item['status'] ) ) {
								$max      = (float) $row['question_marks'];
								$fraction = $max > 0 ? min( 1, (float) $row['achive_mark'] / $max ) : 0;
								$wpdb->update(
									Schema::table( 'attempt_items' ),
									array(
										'awarded'   => $row['achive_mark'],
										'fraction'  => $fraction,
										'correct'   => $row['is_correct'],
										'status'    => 'graded',
										'graded_at' => current_time( 'mysql', true ),
									),
									array( 'id' => (int) $item['id'] )
								);
								$event_id  = GradeEvents::record(
									array(
										'source_type' => 'quiz',
										'source_id'   => (int) $attempt_id,
										'item_id'     => (int) $item['id'],
										'student_id'  => (int) $attempt['student_id'],
										'question_id' => $id,
										'version_id'  => (int) $item['version_id'],
										'awarded'     => $row['achive_mark'],
										'max_marks'   => $max,
										'fraction'    => $fraction,
										'correct'     => $row['is_correct'],
										'grader'      => 'manual',
										'reviewer_id' => $reviewer,
										'reason'      => $reason,
									)
								);
								$changed[] = array(
									'question_id'    => $id,
									'item_id'        => (int) $item['id'],
									'before'         => $before,
									'after'          => (float) $row['achive_mark'],
									'grade_event_id' => $event_id,
								);
							} elseif ( ! $item && $differs ) {
								$changed[] = array(
									'question_id'    => $id,
									'item_id'        => 0,
									'before'         => $before,
									'after'          => (float) $row['achive_mark'],
									'grade_event_id' => 0,
								);
							}
						}
						$type    = $item ? (string) ( VersionPublisher::version( $item['version_id'] )['type'] ?? '' ) : (string) ( ( ohmylms_get_question( $id ) ? ohmylms_get_question( $id )->get_settings() : array() )['type'] ?? '' );
						$answer  = maybe_unserialize( $row['given_answer'] );
						$pending = $pending || ( Grader::present( $answer ) && empty( $row['is_manually_reviewed'] ) && Grader::manual_type( $type ) );
						$total  += (float) $row['achive_mark'];
					}
					$total  = Scoring::total( array( $total ) );
					$status = $pending ? 'in-review' : 'completed';
					if ( $wpdb->update(
						$wpdb->prefix . 'ohmylms_quiz_attempts',
						array(
							'total'  => $total,
							'status' => $status,
						),
						array( 'id' => $attempt_id )
					) === false ) {
						throw new \RuntimeException( 'Attempt update failed' );
					}
					return compact( 'attempt', 'total', 'pending', 'status', 'context' );
				}
			);
		} catch ( ErrorException $error ) {
			return $error->error;
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'quiz_storage', 'Could not save review.', array( 'status' => 500 ) );
		}
		$attempt    = $result['attempt'];
		$total      = $result['total'];
		$pending    = $result['pending'];
		$status     = $result['status'];
		$student_id = (int) $attempt['student_id'];
		$course_id  = (int) $attempt['course_id'];
		foreach ( $changed as $change ) {
			// Per-item regrade notification, even when the attempt total is unchanged.
			do_action(
				'ohmylms_answer_regraded',
				array(
					'quiz_id'     => (int) $quiz_id,
					'attempt_id'  => (int) $attempt_id,
					'student_id'  => $student_id,
					'course_id'   => $course_id,
					'reviewer_id' => $reviewer,
					'reason'      => $reason,
				) + $change
			);
		}
		if ( ! $pending && ( $attempt['status'] !== $status || (float) $attempt['total'] !== $total ) ) {
			$event = array(
				'quiz_id'    => (int) $quiz_id,
				'attempt_id' => (int) $attempt_id,
				'student_id' => $student_id,
				'course_id'  => $course_id,
				'total'      => $total,
				'status'     => $status,
				'reason'     => 'manual-review',
			);
			do_action( 'ohmylms_attempt_graded', $event );
			do_action( 'ohmylms_rest_review_quiz_attempt', $quiz_id, $course_id, $student_id, $total );
			$passing = $quiz->get_passing_grade();
			if ( $result['context'] ) {
				$revision = \OhMyLMS\Assessment\RevisionPublisher::revision( (int) $result['context']['revision_id'] );
				$passing  = (float) ( $revision['settings']['passing_mark'] ?? $passing );
			}
			if ( $total >= $passing ) {
				$student = new Student( $student_id );
				$before  = $student->get_over_all_completion_rate( $course_id );
				if ( ! $student->maybe_completed( $quiz_id ) ) {
					$student->complete_lesson( $quiz_id, $course_id );
					do_action( 'ohmylms_lesson_completed', $quiz_id, $course_id, $student_id );
				}
				if ( ! \OhMyLMS\Learning\CourseProgram::managed( $student_id, $course_id ) && 100 !== (int) $before && (int) $student->get_over_all_completion_rate( $course_id ) === 100 ) {
					$order = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT order_id FROM %i WHERE user_id=%d AND course_id=%d', $wpdb->prefix . 'ohmylms_user_enrollment', $student_id, $course_id ) );
					do_action( 'ohmylms_course_completed', $student_id, $course_id, $order );
				}
			}
		}
		return $quiz->get_attempt_report( $attempt_id );
	}

	/**
	 * Current part for Review.
	 *
	 * @param mixed $attempt_id Attempt identifier.
	 * @param mixed $item_id Item identifier.
	 * @param mixed $part_id Part identifier.
	 */
	private static function current_part( $attempt_id, $item_id, $part_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE source_type=\'quiz\' AND source_id=%d AND item_id=%d AND part_id=%s AND superseded_by=0 ORDER BY id DESC LIMIT 1', Schema::table( 'grade_events' ), $attempt_id, $item_id, (string) $part_id ), ARRAY_A ) ?: null;
	}

	/**
	 * Part marks for a structured question. An array assigns marks per part. A single
	 * number is the teacher's total: automatically marked parts keep their scores and the
	 * remainder is shared across written parts in proportion to their marks.
	 *
	 * @return array|\WP_Error ['parts' => part_id => awarded (changed parts), 'total' => question total]
	 * @param mixed $attempt_id Attempt identifier.
	 * @param array $item Item.
	 * @param array $settings Configuration settings.
	 * @param mixed $mark Mark.
	 * @param mixed $question_marks Question marks.
	 * @param mixed $policy Policy.
	 */
	private static function distribute_parts( $attempt_id, array $item, array $settings, $mark, $question_marks, $policy ) {
		$weights = \OhMyLMS\Assessment\Structured::weights( $settings );
		$parts   = \OhMyLMS\Assessment\Structured::parts( $settings );
		$result  = array();
		// Current award of every part (from its latest grade event).
		$current = array();
		foreach ( $parts as $part ) {
			$event                  = self::current_part( $attempt_id, (int) $item['id'], $part['id'] );
			$current[ $part['id'] ] = $event ? (float) $event['awarded'] : 0.0; }
		if ( is_array( $mark ) ) {
			foreach ( $mark as $part_id => $value ) {
				if ( ! isset( $weights[ $part_id ] ) ) {
					return new \WP_Error( 'quiz_marks', 'Unknown question part.', array( 'status' => 422 ) );
				}
				$max = $question_marks * $weights[ $part_id ];
				if ( (float) $value > $max + 0.00005 ) {
					return new \WP_Error( 'quiz_marks', 'Part marks exceed the part maximum.', array( 'status' => 422 ) );
				}
				$result[ $part_id ] = Scoring::manual( $value, $max, Scoring::DECIMAL ); // part marks are always decimal; the item total follows the attempt policy.
			}
			return array(
				'parts' => $result,
				'total' => round( array_sum( array_merge( $current, $result ) ), 4 ),
			);
		}
		$auto    = 0.0;
		$written = array();
		foreach ( $parts as $part ) {
			if ( Structured::has_manual( array( 'parts' => array( $part ) ) ) ) {
				$written[ $part['id'] ] = $question_marks * $weights[ $part['id'] ];
				continue; }
			$current = self::current_part( $attempt_id, (int) $item['id'], $part['id'] );
			$auto   += $current ? (float) $current['awarded'] : 0.0;
		}
		$written_max = array_sum( $written );
		$remainder   = max( 0.0, min( $written_max, (float) $mark - $auto ) );
		foreach ( $written as $part_id => $max ) {
			$result[ $part_id ] = $written_max > 0 ? round( $remainder * $max / $written_max, 4 ) : 0.0; }
		return array(
			'parts' => $result,
			'total' => round( $auto + array_sum( $result ), 4 ),
		);
	}
}
