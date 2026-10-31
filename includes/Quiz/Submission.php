<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * One server-side submission path for HTML forms, timeout AJAX and deadline finalization.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Quiz;

use OhMyLMS\Assessment\AttemptItems;
use OhMyLMS\Assessment\Deadlines;
use OhMyLMS\Assessment\Engine;
use OhMyLMS\Assessment\ErrorException;
use OhMyLMS\Assessment\GradeEvents;
use OhMyLMS\Assessment\Grader;
use OhMyLMS\Assessment\Responses;
use OhMyLMS\Assessment\RevisionPublisher;
use OhMyLMS\Assessment\Scoring;
use OhMyLMS\Extensions\Registry;
use OhMyLMS\Data\Student;
use OhMyLMS\QuestionBank\VersionPublisher;
use OhMyLMS\Utility\Transaction;

/**
 * One server-side submission path for HTML forms, timeout AJAX and deadline finalization.
 *
 * New launches use the versioned engine: the attempt is bound to a published revision and
 * frozen items, and grading reads only those snapshots. Attempts started before the engine
 * (no attempt context) finish on the legacy path with their original semantics.
 */
final class Submission {
	/**
	 * Access for Submission.
	 *
	 * @param mixed $quiz_id Quiz identifier.
	 * @param mixed $student_id Student identifier.
	 */
	public static function access( $quiz_id, $student_id ) {
		if ( ! $student_id || get_post_type( $quiz_id ) !== 'ohmylms-quiz' ) {
			return new \WP_Error( 'quiz_access', 'Quiz access denied.', array( 'status' => 403 ) );
		}
		$course_id = (int) ohmylms_get_course_by_content_id( $quiz_id, $student_id );
		if ( ! $course_id || ( ! ( new Student( $student_id ) )->maybe_enrolled( $course_id ) && ! current_user_can( 'edit_post', $quiz_id ) ) ) {
			return new \WP_Error( 'quiz_access', 'Course enrollment is required.', array( 'status' => 403 ) );
		}
		if ( apply_filters( 'ohmylms_is_lesson_sequentially_locked', false, $quiz_id, $course_id, $student_id ) || apply_filters( 'ohmylms_is_lesson_locked', false, $quiz_id, $course_id, $student_id ) ) {
			return new \WP_Error( 'quiz_locked', 'This quiz is locked.', array( 'status' => 403 ) );
		}
		return $course_id;
	}
	/**
	 * Admins and a quiz's author are always previewing it, enrolled or not: their own testing must
	 * never become a learner record. Other users who can edit the quiz preview only until they enrol,
	 * when they become real learners. Previewing attempts are disposable, so they do not use up
	 * attempt limits, record grades or evidence, complete the lesson, or fire the events that email
	 * instructors, call webhooks and award achievements. Students and other non-editors never preview.
	 * Sites can override with the `ohmylms_quiz_is_preview` filter.
	 *
	 * @param int $quiz_id Quiz identifier.
	 * @param int $student_id Student identifier.
	 * @return bool Whether the submission is a preview.
	 */
	public static function is_preview( $quiz_id, $student_id ) {
		$quiz_id    = (int) $quiz_id;
		$student_id = (int) $student_id;
		if ( ! $quiz_id || ! $student_id || get_post_type( $quiz_id ) !== 'ohmylms-quiz' || ! user_can( $student_id, 'edit_post', $quiz_id ) ) {
			return false;
		}
		$preview = user_can( $student_id, 'manage_options' ) || (int) get_post_field( 'post_author', $quiz_id ) === $student_id;
		if ( ! $preview ) {
			$course_id = (int) ohmylms_get_course_by_content_id( $quiz_id, $student_id );
			$preview   = $course_id > 0 && ! ( new Student( $student_id ) )->maybe_enrolled( $course_id );
		}
		return (bool) apply_filters( 'ohmylms_quiz_is_preview', $preview, $quiz_id, $student_id );
	}
	/**
	 * Build the transient key for a user's preview result.
	 *
	 * @param int $quiz_id Quiz identifier.
	 * @param int $student_id Student identifier.
	 * @return string Transient key.
	 */
	private static function preview_key( $quiz_id, $student_id ) {
		return 'ohmylms_quiz_preview_' . (int) $student_id . '_' . (int) $quiz_id;
	}
	/**
	 * Retrieve the preview score once after the attempt itself is erased.
	 *
	 * @param int $quiz_id Quiz identifier.
	 * @param int $student_id Student identifier.
	 * @return array|null Preview score, when available.
	 */
	public static function take_preview_result( $quiz_id, $student_id ) {
		$key    = self::preview_key( $quiz_id, $student_id );
		$result = get_transient( $key );
		if ( false !== $result ) {
			delete_transient( $key );
		}
		return is_array( $result ) ? $result : null;
	}
	/**
	 * Earlier versions recorded staff attempts like learners': a pass, a completed quiz, used-up
	 * attempts. When an author opens their own quiz, clear what is left (keeping any attempt in
	 * progress) so a preview never opens on an old "passed". Other people's quizzes are only
	 * previewed, never wiped.
	 *
	 * @param int $quiz_id Quiz identifier.
	 * @param int $student_id Student identifier.
	 */
	public static function clear_preview_record( $quiz_id, $student_id ) {
		global $wpdb;
		$quiz_id    = (int) $quiz_id;
		$student_id = (int) $student_id;
		if ( ! self::is_preview( $quiz_id, $student_id ) || (int) get_post_field( 'post_author', $quiz_id ) !== $student_id ) {
			return;
		}
		$attempts = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}ohmylms_quiz_attempts WHERE quiz_id=%d AND student_id=%d AND status<>'in-progress'", $quiz_id, $student_id ) );
		foreach ( (array) $attempts as $attempt_id ) {
			self::erase_attempt( (int) $attempt_id );
		}
		$removed   = $wpdb->query( $wpdb->prepare( "DELETE p FROM {$wpdb->prefix}ohmylms_user_progress p JOIN {$wpdb->prefix}ohmylms_user_enrollment e ON p.enrollment_id=e.id WHERE e.user_id=%d AND p.content_id=%d AND p.status='completed'", $student_id, $quiz_id ) );
		$course_id = (int) ohmylms_get_course_by_content_id( $quiz_id, $student_id );
		if ( $removed && $course_id && (int) ( new Student( $student_id ) )->get_over_all_completion_rate( $course_id ) < 100 ) {
			// Completing this quiz had also completed the course; take that back with it.
			\OhMyLMS\Student\Enrollments::update(
				array(
					'progress' => 'running',
					'end_date' => '0000-00-00 00:00:00',
				),
				array(
					'user_id'   => $student_id,
					'course_id' => $course_id,
					'progress'  => 'completed',
				)
			);
		}
	}
	/**
	 * Remember the preview outcome, then erase its attempt.
	 *
	 * @param array $attempt Attempt data.
	 * @param array $graded Graded response data.
	 * @param array $event Submission event data.
	 * @return array Submission event data.
	 */
	private static function finish_preview( array $attempt, array $graded, array $event ) {
		$quiz_id    = (int) $attempt['quiz_id'];
		$student_id = (int) $attempt['student_id'];
		if ( 'exit' === $graded['reason'] ) {
			delete_transient( self::preview_key( $quiz_id, $student_id ) );
		} else {
			$max     = 0.0;
			$correct = 0;
			foreach ( $graded['rows'] as $row ) {
				$max     += (float) $row['question_marks'];
				$correct += ! empty( $row['is_correct'] ) ? 1 : 0;
			}
			set_transient(
				self::preview_key( $quiz_id, $student_id ),
				array(
					'total'     => (float) $graded['total'],
					'max'       => $max,
					'passing'   => (float) $graded['passing'],
					'status'    => $graded['status'],
					'questions' => count( $graded['rows'] ),
					'correct'   => $correct,
				),
				HOUR_IN_SECONDS
			);
		}
		self::erase_attempt( (int) $attempt['id'] );
		return $event;
	}
	/**
	 * Erase an attempt and its child records in one transaction.
	 *
	 * @param int $attempt_id Attempt identifier.
	 */
	private static function erase_attempt( $attempt_id ) {
		global $wpdb;
		$children = array( $wpdb->prefix . 'ohmylms_quiz_attempts_answers' => 'quiz_attempt_id' );
		if ( \OhMyLMS\Assessment\Schema::ready() ) {
			foreach ( array( 'attempt_context', 'attempt_items', 'response_drafts', 'response_events' ) as $name ) {
				$children[ \OhMyLMS\Assessment\Schema::table( $name ) ] = 'attempt_id';
			}
		}
		try {
			Transaction::run(
				static function () use ( $wpdb, $children, $attempt_id ) {
					foreach ( $children as $table => $column ) {
						if ( $wpdb->delete( $table, array( $column => $attempt_id ) ) === false ) {
							throw new \RuntimeException( 'Preview cleanup failed' );
						}
					}
					if ( $wpdb->delete( $wpdb->prefix . 'ohmylms_quiz_attempts', array( 'id' => $attempt_id ) ) === false ) {
						throw new \RuntimeException( 'Preview cleanup failed' );
					}
				}
			);
		} catch ( \Throwable $error ) {
			// Best effort: a leftover preview attempt is hidden from the previewer and is harmless.
			return;
		}
	}
	/**
	 * Lock for Submission.
	 *
	 * @param mixed $quiz_id Quiz identifier.
	 * @param mixed $student_id Student identifier.
	 */
	private static function lock( $quiz_id, $student_id ) {
		global $wpdb;
		$key = 'ohmylms-quiz-' . md5( $wpdb->prefix . ':' . $quiz_id . ':' . $student_id );
		return (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 3)', $key ) ) === '1' ? $key : false;
	}
	/**
	 * Unlock for Submission.
	 *
	 * @param mixed $key Lookup key.
	 */
	private static function unlock( $key ) {
		global $wpdb;
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) );
	}
	/**
	 * Start for Submission.
	 *
	 * @param mixed $quiz_id Quiz identifier.
	 * @param mixed $student_id Student identifier.
	 */
	public static function start( $quiz_id, $student_id ) {
		global $wpdb;
		$course_id = self::access( $quiz_id, $student_id );
		if ( is_wp_error( $course_id ) ) {
			return $course_id;
		}
		$key = self::lock( $quiz_id, $student_id );
		if ( ! $key ) {
			return new \WP_Error( 'quiz_busy', 'Please retry.', array( 'status' => 409 ) );
		}
		$preview = self::is_preview( $quiz_id, $student_id );
		try {
			$quiz     = ohmylms_get_quiz( $quiz_id );
			$existing = $quiz->get_quiz_attempt( $student_id );
			if ( $existing ) {
				// A versioned attempt whose deadline has passed is finalized, not resumed.
				$context = AttemptItems::context( (int) $existing['id'] );
				if ( ! $context || ! Deadlines::closed( $context ) ) {
					return (int) $existing['id'];
				}
				self::unlock( $key );
				$key    = null;
				$closed = self::finalize_expired( (int) $existing['id'] );
				$key    = self::lock( $quiz_id, $student_id );
				if ( ! $key ) {
					return new \WP_Error( 'quiz_busy', 'Please retry.', array( 'status' => 409 ) );
				}
				if ( is_wp_error( $closed ) && $closed->get_error_code() !== 'quiz_attempt' ) {
					return $closed;
				}
			}
			if ( Engine::versioned() ) {
				$open = \OhMyLMS\Assessment\AssessmentSettings::availability( $quiz_id );
				if ( is_wp_error( $open ) ) {
					return $open;
				}
			}
			if ( ! $preview && $quiz->count_total_attempt( $student_id, $course_id ) >= $quiz->get_take_attempts() ) {
				return new \WP_Error( 'quiz_attempt_limit', 'No attempts remaining.', array( 'status' => 403 ) );
			}
			foreach ( $quiz->get_questions() as $q ) {
				if ( ! Registry::get( 'question', $q['settings']['type'] ?? '' ) ) {
					return new \WP_Error( 'quiz_type_missing', 'A required question extension is unavailable.' );
				}
			}
			$row         = array(
				'quiz_id'    => $quiz_id,
				'student_id' => $student_id,
				'course_id'  => $course_id,
				'total'      => 0,
				'status'     => 'in-progress',
				'start_date' => current_time( 'mysql' ),
			);
			$revision_id = 0;
			if ( ! Engine::versioned() ) {
				if ( ! $wpdb->insert( $wpdb->prefix . 'ohmylms_quiz_attempts', $row ) ) {
					return new \WP_Error( 'quiz_storage', 'Could not start attempt.' );
				}
				$id = (int) $wpdb->insert_id;
			} else {
				try {
					[$id, $revision_id] = Transaction::run(
						static function () use ( $wpdb, $row, $quiz_id ) {
							if ( ! $wpdb->insert( $wpdb->prefix . 'ohmylms_quiz_attempts', $row ) ) {
								throw new \RuntimeException( 'Attempt write failed' );
							}
							$id       = (int) $wpdb->insert_id;
							$revision = ErrorException::raise( RevisionPublisher::publish( $quiz_id ) );
							AttemptItems::create( $id, $revision );
							return array( $id, (int) $revision['id'] );
						}
					);
				} catch ( ErrorException $error ) {
					return $error->error;
				} catch ( \Throwable $error ) {
					return new \WP_Error( 'quiz_storage', 'Could not start attempt.', array( 'status' => 500 ) );
				}
			}
			if ( ! $preview ) {
				do_action(
					'ohmylms_attempt_started',
					array(
						'quiz_id'     => (int) $quiz_id,
						'attempt_id'  => $id,
						'student_id'  => (int) $student_id,
						'course_id'   => $course_id,
						'revision_id' => $revision_id,
					)
				);
			}
			return $id;
		} finally {
			if ( $key ) {
				self::unlock( $key );
			}
		}
	}
	/**
	 * Submit for Submission.
	 *
	 * @param mixed  $quiz_id Quiz identifier.
	 * @param mixed  $attempt_id Attempt identifier.
	 * @param mixed  $student_id Student identifier.
	 * @param array  $answers Answers.
	 * @param string $reason Reason.
	 */
	public static function submit( $quiz_id, $attempt_id, $student_id, array $answers, $reason = 'submit' ) {
		global $wpdb;
		$course_id = self::access( $quiz_id, $student_id );
		if ( is_wp_error( $course_id ) ) {
			return $course_id;
		}
		$key = self::lock( $quiz_id, $student_id );
		if ( ! $key ) {
			return new \WP_Error( 'quiz_busy', 'Please retry.', array( 'status' => 409 ) );
		}
		try {
			$attempt = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ohmylms_quiz_attempts WHERE id=%d AND quiz_id=%d AND student_id=%d AND course_id=%d", $attempt_id, $quiz_id, $student_id, $course_id ), ARRAY_A );
			if ( ! $attempt || 'in-progress' !== $attempt['status'] ) {
				return new \WP_Error( 'quiz_attempt', 'Attempt unavailable or already submitted.', array( 'status' => 409 ) );
			}
			$context = AttemptItems::context( $attempt_id );
			$graded  = $context ? self::grade_versioned( $attempt, $context, $answers, $reason ) : self::grade_legacy( $attempt, $answers, $reason );
			if ( is_wp_error( $graded ) ) {
				return $graded;
			}
		} finally {
			self::unlock( $key ); }
		return self::after_commit( $attempt, $course_id, $graded );
	}

	/** Original engine for attempts started before versioning (unchanged semantics).
	 *
	 * @param array $attempt Attempt.
	 * @param array $answers Answers.
	 * @param mixed $reason Reason.
	 */
	private static function grade_legacy( array $attempt, array $answers, $reason ) {
		global $wpdb;
		$quiz_id    = (int) $attempt['quiz_id'];
		$attempt_id = (int) $attempt['id'];
		$student_id = (int) $attempt['student_id'];
		$rows       = array();
		$total      = 0;
		$manual     = false;
		$quiz       = ohmylms_get_quiz( $quiz_id );
		$questions  = $quiz->get_questions();
		$duration   = (int) $quiz->get_timer() * 60;
		$expired    = 0 < $duration && current_time( 'timestamp' ) >= strtotime( $attempt['start_date'] ) + $duration;
		if ( 'timeout' === $reason && ! $expired ) {
			return new \WP_Error( 'quiz_timer', 'The attempt has not reached its deadline.', array( 'status' => 400 ) );
		}
		if ( 'submit' === $reason && $expired ) {
			$reason = 'timeout';
		}
		if ( array_diff( array_map( 'strval', array_keys( $answers ) ), array_map( 'strval', array_column( $questions, 'id' ) ) ) ) {
			return new \WP_Error( 'quiz_question', 'Answer contains a question outside this quiz.', array( 'status' => 400 ) );
		}
		foreach ( $questions as $data ) {
			$question   = ohmylms_get_question( $data['id'] );
			$settings   = $question->get_settings();
			$definition = Registry::get( 'question', $settings['type'] ?? '' );
			if ( ! $definition ) {
				return new \WP_Error( 'quiz_type_missing', 'A required question extension is unavailable.' );
			}
			$answer = Grader::sanitize( $answers[ $data['id'] ] ?? array() );
			if ( ! call_user_func( $definition['validate'], $answer, $question ) ) {
				return new \WP_Error( 'quiz_answer', 'Invalid answer format.', array( 'status' => 400 ) );
			}
			$present = Grader::present( $answer );
			if ( 'submit' === $reason && ! empty( $settings['required'] ) && ( $settings['type'] ?? '' ) === 'fill-in-the-blank' && ! \OhMyLMS\Assessment\InlineBlanks::complete( $question, $answer ) ) {
				return new \WP_Error( 'quiz_required', 'Every blank in a required question must be answered.', array( 'status' => 400 ) );
			}
			if ( 'submit' === $reason && ! empty( $settings['required'] ) && ! $present ) {
				return new \WP_Error( 'quiz_required', 'A required question is unanswered.', array( 'status' => 400 ) );
			}
			$grade = call_user_func( $definition['grade'], $answer, $question );
			if ( is_wp_error( $grade ) ) {
				return $grade;
			}
			if ( ! is_array( $grade ) || ! isset( $grade['fraction'] ) || ! is_numeric( $grade['fraction'] ) || ! is_finite( (float) $grade['fraction'] ) ) {
				return new \WP_Error( 'quiz_grader', 'Invalid grading result.' );
			}
			$marks   = ! empty( $settings['score']['enabled'] ) ? max( 0, (float) ( $settings['score']['value'] ?? 0 ) ) : 0;
			$pending = 'exit' !== $reason && $present && ! empty( $grade['manual'] );
			// The legacy attempt total is an integer; extensions award whole points.
			$earned = $present && ! $pending && 'exit' !== $reason ? (int) round( $marks * max( 0, min( 1, (float) $grade['fraction'] ) ) ) : 0;
			$manual = $manual || $pending;
			$total += $earned;
			$rows[] = array(
				'quiz_id'         => $quiz_id,
				'student_id'      => $student_id,
				'question_id'     => $question->get_id(),
				'quiz_attempt_id' => $attempt_id,
				'given_answer'    => maybe_serialize( $answer ),
				'question_marks'  => $marks,
				'achive_mark'     => $earned,
				'minus_mark'      => 0,
				'is_correct'      => $present && ! empty( $grade['correct'] ) ? 1 : 0,
			);
		}
		$status = $manual ? 'in-review' : 'completed';
		try {
			Transaction::run(
				static function () use ( $wpdb, $rows, $total, $status, $attempt_id ) {
					foreach ( $rows as $row ) {
						if ( $wpdb->insert( $wpdb->prefix . 'ohmylms_quiz_attempts_answers', $row ) === false ) {
							throw new \RuntimeException( 'Answer write failed' );
						}
					}
					if ( $wpdb->update(
						$wpdb->prefix . 'ohmylms_quiz_attempts',
						array(
							'total'    => $total,
							'status'   => $status,
							'end_date' => current_time( 'mysql' ),
						),
						array(
							'id'     => $attempt_id,
							'status' => 'in-progress',
						)
					) !== 1 ) {
						throw new \RuntimeException( 'Attempt write failed' );
					}
				}
			);
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'quiz_storage', 'Could not save this attempt. Please retry.', array( 'status' => 500 ) );
		}
		return array(
			'rows'        => $rows,
			'total'       => $total,
			'manual'      => $manual,
			'status'      => $status,
			'reason'      => $reason,
			'passing'     => (float) $quiz->get_passing_grade(),
			'revision_id' => 0,
		);
	}

	/** Versioned engine: grade only the frozen items issued at start.
	 *
	 * @param array $attempt Attempt.
	 * @param array $context Context.
	 * @param array $answers Answers.
	 * @param mixed $reason Reason.
	 * @param mixed $now Now.
	 */
	private static function grade_versioned( array $attempt, array $context, array $answers, $reason, $now = null ) {
		global $wpdb;
		$quiz_id     = (int) $attempt['quiz_id'];
		$attempt_id  = (int) $attempt['id'];
		$student_id  = (int) $attempt['student_id'];
		$items       = AttemptItems::items( $attempt_id );
		$by_question = array();
		foreach ( $items as $item ) {
			$by_question[ (int) $item['question_id'] ] = $item;
		}
		if ( array_diff( array_map( 'intval', array_keys( $answers ) ), array_keys( $by_question ) ) ) {
			return new \WP_Error( 'quiz_question', 'Answer contains a question outside this attempt.', array( 'status' => 400 ) );
		}
		$expired = Deadlines::expired( $context, $now );
		if ( 'timeout' === $reason && ! $expired ) {
			return new \WP_Error( 'quiz_timer', 'The attempt has not reached its deadline.', array( 'status' => 400 ) );
		}
		if ( 'submit' === $reason && $expired ) {
			$reason = 'timeout';
		}
		// After deadline + grace, only responses the server received in time count.
		$late     = 'exit' !== $reason && Deadlines::closed( $context, $now );
		$saved    = Responses::saved( $attempt_id );
		$revision = RevisionPublisher::revision( (int) $context['revision_id'] );
		$policy   = $context['scoring'];
		$rows     = array();
		$updates  = array();
		$total    = 0.0;
		$manual   = false;
		foreach ( $items as $item ) {
			$question_id = (int) $item['question_id'];
			$snapshot    = AttemptItems::snapshot( $item );
			if ( ! $snapshot ) {
				return new \WP_Error( 'quiz_version_missing', 'A question version for this attempt is missing.', array( 'status' => 500 ) );
			}
			if ( ! $late && array_key_exists( $question_id, $answers ) ) {
				$answer = AttemptItems::untokenize( $attempt_id, $item, $answers[ $question_id ] );
			} else {
				$answer = $saved[ $question_id ] ?? array();
			}
			$grade = Grader::grade( $snapshot, $answer );
			if ( is_wp_error( $grade ) ) {
				return $grade;
			}
			if ( 'submit' === $reason && ! empty( $item['display']['required'] ) && $snapshot->get_type() === 'fill-in-the-blank' && ! \OhMyLMS\Assessment\InlineBlanks::complete( $snapshot, $answer ) ) {
				return new \WP_Error( 'quiz_required', 'Every blank in a required question must be answered.', array( 'status' => 400 ) );
			}
			if ( 'submit' === $reason && ! empty( $item['display']['required'] ) && ! $grade['present'] ) {
				return new \WP_Error( 'quiz_required', 'A required question is unanswered.', array( 'status' => 400 ) );
			}
			$marks   = (float) $item['marks'];
			$pending = 'exit' !== $reason && $grade['pending'];
			$earned  = $grade['present'] && ! $pending && 'exit' !== $reason ? Scoring::award( $marks, $grade['fraction'], $policy ) : 0.0;
			// Structured questions keep the credit of automatically marked parts while written parts await review.
			if ( $pending && null !== $grade['parts'] ) {
				$earned = Scoring::award( $marks, $grade['auto_fraction'], $policy );
			}
			$manual    = $manual || $pending;
			$total    += $earned;
			$correct   = $grade['present'] && $grade['correct'] && 'exit' !== $reason;
			$rows[]    = array(
				'quiz_id'         => $quiz_id,
				'student_id'      => $student_id,
				'question_id'     => $question_id,
				'quiz_attempt_id' => $attempt_id,
				'given_answer'    => maybe_serialize( $grade['answer'] ),
				'question_marks'  => $marks,
				'achive_mark'     => $earned,
				'minus_mark'      => 0,
				'is_correct'      => $correct ? 1 : 0,
			);
			$updates[] = array(
				'item'             => $item,
				'answer'           => $grade['answer'],
				'present'          => $grade['present'],
				'pending'          => $pending,
				'fraction'         => $pending ? null : ( 'exit' === $reason ? 0.0 : $grade['fraction'] ),
				'awarded'          => $pending ? null : $earned,
				'correct'          => $pending ? null : ( $correct ? 1 : 0 ),
				'marks'            => $marks,
				'parts'            => $grade['parts'],
				'version_settings' => null !== $grade['parts'] ? $snapshot->get_settings() : null,
			);
		}
		// Legacy-int attempts keep an integer total, exactly like the original engine.
		$total   = Scoring::DECIMAL === $policy ? Scoring::total( array( $total ) ) : (int) round( $total );
		$status  = $manual ? 'in-review' : 'completed';
		$preview = self::is_preview( $quiz_id, $student_id );
		try {
			Transaction::run(
				static function () use ( $wpdb, $rows, $updates, $total, $status, $attempt_id, $student_id, $reason, $late, $preview ) {
					foreach ( $rows as $row ) {
						if ( $wpdb->insert( $wpdb->prefix . 'ohmylms_quiz_attempts_answers', $row ) === false ) {
							throw new \RuntimeException( 'Answer write failed' );
						}
					}
					$now = current_time( 'mysql', true );
					foreach ( $updates as $update ) {
						$item        = $update['item'];
						$status_item = $update['pending'] ? 'needs-review' : ( $update['present'] ? 'graded' : 'unanswered' );
						$wpdb->update(
							\OhMyLMS\Assessment\Schema::table( 'attempt_items' ),
							array(
								'response'  => wp_json_encode( $update['answer'] ),
								'status'    => $status_item,
								'fraction'  => $update['fraction'],
								'awarded'   => $update['awarded'],
								'correct'   => $update['correct'],
								'graded_at' => $update['pending'] ? null : $now,
							),
							array( 'id' => (int) $item['id'] )
						);
						// Previews are graded for the score shown to the previewer but record no evidence.
						if ( 'exit' === $reason || $preview ) {
							continue;
						}
						if ( null !== $update['parts'] && $update['present'] ) {
							// One event per part: evidence is attributed to each part's own skill.
							$weights = \OhMyLMS\Assessment\Structured::weights( $update['version_settings'] );
							foreach ( $update['parts'] as $part_id => $part_fraction ) {
								if ( null === $part_fraction ) {
									continue;
								}
								$part_max = $update['marks'] * ( $weights[ $part_id ] ?? 0 );
								GradeEvents::record(
									array(
										'source_type' => 'quiz',
										'source_id'   => $attempt_id,
										'item_id'     => (int) $item['id'],
										'part_id'     => (string) $part_id,
										'student_id'  => $student_id,
										'question_id' => (int) $item['question_id'],
										'version_id'  => (int) $item['version_id'],
										'awarded'     => round( $part_max * $part_fraction, 4 ),
										'max_marks'   => $part_max,
										'fraction'    => $part_fraction,
										'correct'     => 1 <= $part_fraction ? 1 : 0,
										'grader'      => 'auto',
										'reason'      => $late ? 'deadline' : 'submitted',
									)
								);
							}
							continue;
						}
						if ( $update['pending'] ) {
							continue;
						}
						GradeEvents::record(
							array(
								'source_type' => 'quiz',
								'source_id'   => $attempt_id,
								'item_id'     => (int) $item['id'],
								'student_id'  => $student_id,
								'question_id' => (int) $item['question_id'],
								'version_id'  => (int) $item['version_id'],
								'awarded'     => $update['awarded'],
								'max_marks'   => $update['marks'],
								'fraction'    => $update['fraction'],
								'correct'     => $update['correct'],
								'grader'      => 'auto',
								'reason'      => $update['present'] ? ( $late ? 'deadline' : 'submitted' ) : 'unanswered',
							)
						);
					}
					if ( $wpdb->update(
						$wpdb->prefix . 'ohmylms_quiz_attempts',
						array(
							'total'    => $total,
							'status'   => $status,
							'end_date' => current_time( 'mysql' ),
						),
						array(
							'id'     => $attempt_id,
							'status' => 'in-progress',
						)
					) !== 1 ) {
						throw new \RuntimeException( 'Attempt write failed' );
					}
					$wpdb->update(
						\OhMyLMS\Assessment\Schema::table( 'attempt_context' ),
						array(
							'finalized_at'    => $now,
							'finalize_reason' => $reason,
						),
						array( 'attempt_id' => $attempt_id )
					);
				}
			);
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'quiz_storage', 'Could not save this attempt. Please retry.', array( 'status' => 500 ) );
		}
		return array(
			'rows'        => $rows,
			'total'       => $total,
			'manual'      => $manual,
			'status'      => $status,
			'reason'      => $reason,
			'passing'     => (float) ( $revision['settings']['passing_mark'] ?? 0 ),
			'revision_id' => (int) $context['revision_id'],
		);
	}

	/** Hooks, completion and course progress after the attempt is committed.
	 *
	 * @param array $attempt Attempt.
	 * @param mixed $course_id Course identifier.
	 * @param array $graded Graded.
	 */
	private static function after_commit( array $attempt, $course_id, array $graded ) {
		global $wpdb;
		$quiz_id    = (int) $attempt['quiz_id'];
		$attempt_id = (int) $attempt['id'];
		$student_id = (int) $attempt['student_id'];
		$total      = $graded['total'];
		$manual     = $graded['manual'];
		$status     = $graded['status'];
		$reason     = $graded['reason'];
		$event      = array(
			'quiz_id'     => $quiz_id,
			'attempt_id'  => $attempt_id,
			'student_id'  => $student_id,
			'course_id'   => $course_id,
			'total'       => $total,
			'status'      => $status,
			'reason'      => $reason,
			'revision_id' => $graded['revision_id'],
		);
		if ( self::is_preview( $quiz_id, $student_id ) ) {
			return self::finish_preview( $attempt, $graded, $event );
		}
		foreach ( $graded['rows'] as $row ) {
			do_action( 'ohmylms_answer_graded', $row + array( 'status' => $status ) );
		}
		if ( ! $manual && 'exit' !== $reason && $total >= $graded['passing'] ) {
			$student      = new Student( $student_id );
			$was_complete = $student->is_course_completed( $course_id );
			$student->complete_lesson( $quiz_id, $course_id );
			do_action( 'ohmylms_lesson_completed', $quiz_id, $course_id, $student_id );
			if ( ! \OhMyLMS\Learning\CourseProgram::managed( $student_id, $course_id ) && ! $was_complete && (int) $student->get_over_all_completion_rate( $course_id ) === 100 ) {
				$order = (int) $wpdb->get_var( $wpdb->prepare( "SELECT order_id FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE user_id=%d AND course_id=%d", $student_id, $course_id ) );
				do_action( 'ohmylms_course_completed', $student_id, $course_id, $order );
			}
		}
		if ( ! $manual ) {
			do_action( 'ohmylms_quiz_result', $quiz_id, $course_id, $student_id, $total );
		}
		do_action( 'ohmylms_quiz_submission', $quiz_id, $course_id, $student_id, $event );
		do_action( 'ohmylms_attempt_submitted', $event );
		if ( ! $manual ) {
			do_action( 'ohmylms_attempt_graded', $event );
		}
		return $event;
	}

	/**
	 * Finalize an expired versioned attempt that nobody submitted (closed browser).
	 * Grades only server-received responses. Runs without a current user.
	 *
	 * @param mixed $attempt_id Attempt identifier.
	 */
	public static function finalize_expired( $attempt_id ) {
		global $wpdb;
		$attempt = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ohmylms_quiz_attempts WHERE id=%d", $attempt_id ), ARRAY_A );
		$context = AttemptItems::context( $attempt_id );
		if ( ! $attempt || ! $context || 'in-progress' !== $attempt['status'] || ! Deadlines::closed( $context ) ) {
			return new \WP_Error( 'quiz_attempt', 'Attempt is not eligible for finalization.', array( 'status' => 409 ) );
		}
		$key = self::lock( (int) $attempt['quiz_id'], (int) $attempt['student_id'] );
		if ( ! $key ) {
			return new \WP_Error( 'quiz_busy', 'Please retry.', array( 'status' => 409 ) );
		}
		try {
			$attempt = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ohmylms_quiz_attempts WHERE id=%d AND status='in-progress'", $attempt_id ), ARRAY_A );
			if ( ! $attempt ) {
				return new \WP_Error( 'quiz_attempt', 'Attempt already submitted.', array( 'status' => 409 ) );
			}
			$graded = self::grade_versioned( $attempt, $context, array(), 'timeout' );
			if ( is_wp_error( $graded ) ) {
				return $graded;
			}
		} finally {
			self::unlock( $key ); }
		return self::after_commit( $attempt, (int) $attempt['course_id'], $graded );
	}
}
