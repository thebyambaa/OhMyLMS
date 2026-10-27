<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Listeners that queue xAPI events.
 *
 * @package OhMyLMS\XApi
 */

namespace OhMyLMS\XApi;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The enrolment lookup runs once per enrolment event and must see the row the caller just wrote, so it is deliberately uncached.

/**
 * Subscribes to OhMyLMS actions and queues one outbox row per event.
 *
 * Each listener only decides whether the event is reportable and what its stable key is. The
 * key is what makes queuing idempotent: a hook that fires twice, or an event that is replayed,
 * still produces one statement. Listeners never throw, because several of these hooks run inside
 * grading and enrolment code that xAPI must not be able to break.
 */
final class Listeners {

	/**
	 * Register the listeners.
	 */
	public static function init() {
		add_action( 'ohmylms_after_enrolled_student', array( __CLASS__, 'enrolled' ), 10, 2 );
		add_action( 'ohmylms_lesson_completed', array( __CLASS__, 'lesson_completed' ), 10, 3 );
		add_action( 'ohmylms_course_completed', array( __CLASS__, 'course_completed' ), 10, 2 );
		add_action( 'ohmylms_attempt_submitted', array( __CLASS__, 'attempt_submitted' ) );
		add_action( 'ohmylms_attempt_graded', array( __CLASS__, 'attempt_graded' ) );
		add_action( 'ohmylms_grade_event_recorded', array( __CLASS__, 'grade_event' ), 10, 2 );
		add_action( 'ohmylms_skill_state_updated', array( __CLASS__, 'skill_state' ), 10, 3 );
	}

	/**
	 * A learner was enrolled by an order. One statement per course on the order.
	 *
	 * The hook is fired with the order alone by some callers and with the learner too by others,
	 * so the enrolment rows are the source of truth.
	 *
	 * @param int $order_id Order ID.
	 * @param int $user_id  Learner, when the caller knows it.
	 */
	public static function enrolled( $order_id, $user_id = 0 ) {
		global $wpdb;
		try {
			if ( ! Settings::enabled() || ! Settings::kind_enabled( 'enrolled' ) || (int) $order_id < 1 ) {
				return;
			}
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT user_id, course_id FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE order_id=%d", (int) $order_id ),
				ARRAY_A
			);
			foreach ( $rows as $row ) {
				if ( $user_id && (int) $row['user_id'] !== (int) $user_id ) {
					continue;
				}
				self::queue( 'enrolled', (int) $row['user_id'], array( 'course_id' => (int) $row['course_id'] ), 'enrolled:' . (int) $row['user_id'] . ':' . (int) $row['course_id'] );
			}
		} catch ( \Throwable $error ) {
			return;
		}
	}

	/**
	 * A learner completed a lesson. Quizzes are reported through their attempts, which carry the score.
	 *
	 * @param int $lesson_id  Lesson or quiz ID.
	 * @param int $course_id  Course ID.
	 * @param int $student_id Learner.
	 */
	public static function lesson_completed( $lesson_id, $course_id, $student_id ) {
		try {
			if ( OHMYLMS_QUIZ_CPT === get_post_type( (int) $lesson_id ) ) {
				return;
			}
			self::queue(
				'lesson_completed',
				(int) $student_id,
				array(
					'lesson_id' => (int) $lesson_id,
					'course_id' => (int) $course_id,
				),
				'lesson-completed:' . (int) $student_id . ':' . (int) $lesson_id
			);
		} catch ( \Throwable $error ) {
			return;
		}
	}

	/**
	 * A learner completed a course.
	 *
	 * @param int $student_id Learner.
	 * @param int $course_id  Course ID.
	 */
	public static function course_completed( $student_id, $course_id ) {
		try {
			self::queue( 'course_completed', (int) $student_id, array( 'course_id' => (int) $course_id ), 'course-completed:' . (int) $student_id . ':' . (int) $course_id );
		} catch ( \Throwable $error ) {
			return;
		}
	}

	/**
	 * A learner submitted a quiz attempt. Abandoned attempts ("exit") are not reported.
	 *
	 * @param array $event attempt_id, student_id, reason.
	 */
	public static function attempt_submitted( $event ) {
		try {
			if ( ! is_array( $event ) || empty( $event['attempt_id'] ) || ( isset( $event['reason'] ) && 'exit' === $event['reason'] ) ) {
				return;
			}
			self::queue( 'attempt_completed', (int) $event['student_id'], array( 'attempt_id' => (int) $event['attempt_id'] ), 'attempt-completed:' . (int) $event['attempt_id'] );
		} catch ( \Throwable $error ) {
			return;
		}
	}

	/**
	 * An attempt was graded, automatically or by a teacher.
	 *
	 * The outcome is part of the key, so a teacher changing a result reports the new outcome
	 * while a repeated event for the same result is ignored.
	 *
	 * @param array $event attempt_id, student_id, total, reason.
	 */
	public static function attempt_graded( $event ) {
		try {
			if ( ! is_array( $event ) || empty( $event['attempt_id'] ) || ( isset( $event['reason'] ) && 'exit' === $event['reason'] ) ) {
				return;
			}
			$total = isset( $event['total'] ) ? (string) (float) $event['total'] : '';
			self::queue(
				'attempt_graded',
				(int) $event['student_id'],
				array( 'attempt_id' => (int) $event['attempt_id'] ),
				'attempt-graded:' . (int) $event['attempt_id'] . ':' . $total
			);
		} catch ( \Throwable $error ) {
			return;
		}
	}

	/**
	 * A question (or part) was graded. Runs inside the grading transaction.
	 *
	 * @param int   $event_id Grade event ID.
	 * @param array $row      The stored event.
	 */
	public static function grade_event( $event_id, $row = array() ) {
		try {
			if ( ! is_array( $row ) || empty( $row['student_id'] ) || ( isset( $row['reason'] ) && 'unanswered' === $row['reason'] ) ) {
				return;
			}
			self::queue( 'answered', (int) $row['student_id'], array( 'grade_event_id' => (int) $event_id ), 'answered:' . (int) $event_id );
		} catch ( \Throwable $error ) {
			return;
		}
	}

	/**
	 * A skill state changed. Only reaching "mastered" is reported, once per learner and skill.
	 *
	 * @param int   $student_id Learner.
	 * @param int   $term_id    Skill term ID.
	 * @param array $state      The new state.
	 */
	public static function skill_state( $student_id, $term_id, $state ) {
		try {
			if ( ! is_array( $state ) || ! isset( $state['level'] ) || 'mastered' !== $state['level'] ) {
				return;
			}
			self::queue( 'skill_mastered', (int) $student_id, array( 'term_id' => (int) $term_id ), 'skill-mastered:' . (int) $student_id . ':' . (int) $term_id );
		} catch ( \Throwable $error ) {
			return;
		}
	}

	/**
	 * Queue an event and ask for it to be sent soon.
	 *
	 * @param string $kind    Statement kind.
	 * @param int    $student Learner.
	 * @param array  $ref     What the materializer needs.
	 * @param string $key     Stable event key.
	 */
	private static function queue( $kind, $student, array $ref, $key ) {
		if ( Outbox::enqueue( $kind, $student, $ref, $key ) ) {
			Processor::soon();
		}
	}
}
