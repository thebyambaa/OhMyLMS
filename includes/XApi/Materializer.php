<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Builds statements from queued events.
 *
 * @package OhMyLMS\XApi
 */

namespace OhMyLMS\XApi;

use OhMyLMS\Assessment\AttemptReport;
use OhMyLMS\Assessment\GradeEvents;
use OhMyLMS\Assessment\Schema as AssessmentSchema;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A background worker reads each plugin-owned, append-only row once while building its statement; caching would add nothing.

/**
 * Turns an outbox row (a kind and a few IDs) into a complete statement.
 *
 * Building happens when the row is sent, not when the event happens, so the learner's request
 * pays only for a small insert. Everything read here is frozen or append-only (grade events,
 * question versions, attempts), so a statement built later equals one built at the time.
 */
final class Materializer {

	/**
	 * Build the statement for an outbox row.
	 *
	 * @param array $row Outbox row.
	 * @return array
	 * @throws SkipStatement When the event no longer has anything to report.
	 */
	public static function statement( array $row ) {
		$ref     = json_decode( (string) $row['ref'], true );
		$ref     = is_array( $ref ) ? $ref : array();
		$student = (int) $row['student_id'];
		$actor   = Identity::actor( $student );
		if ( ! $actor ) {
			throw new SkipStatement( 'The learner no longer exists or cannot be identified.' );
		}
		$base = array(
			'id'        => $row['statement_uuid'],
			'actor'     => $actor,
			'timestamp' => strtotime( $row['occurred_at'] . ' UTC' ),
		);

		switch ( $row['kind'] ) {
			case 'enrolled':
				return self::enrolled( $base, $student, $ref );
			case 'lesson_completed':
				return self::lesson_completed( $base, $student, $ref );
			case 'course_completed':
				return self::course_completed( $base, $student, $ref );
			case 'attempt_completed':
				return self::attempt( $base, $ref, 'completed' );
			case 'attempt_graded':
				return self::attempt( $base, $ref, 'graded' );
			case 'answered':
				return self::answered( $base, $ref );
			case 'skill_mastered':
				return self::skill_mastered( $base, $ref );
		}
		throw new SkipStatement( 'Unknown statement kind ' . esc_html( $row['kind'] ) . '.' );
	}

	/**
	 * Learner enrolled in a course.
	 *
	 * @param array $base    id, actor, timestamp.
	 * @param int   $student Learner.
	 * @param array $ref     course_id.
	 * @return array
	 * @throws SkipStatement When the event no longer has anything to report.
	 */
	private static function enrolled( array $base, $student, array $ref ) {
		$course = self::course( $ref );

		return StatementBuilder::build(
			$base + array(
				'verb'    => 'registered',
				'object'  => $course,
				'context' => self::context( $student, (int) $ref['course_id'] ),
			)
		);
	}

	/**
	 * Learner completed a lesson.
	 *
	 * @param array $base    id, actor, timestamp.
	 * @param int   $student Learner.
	 * @param array $ref     lesson_id, course_id.
	 * @return array
	 * @throws SkipStatement When the event no longer has anything to report.
	 */
	private static function lesson_completed( array $base, $student, array $ref ) {
		$lesson = Activities::content( isset( $ref['lesson_id'] ) ? (int) $ref['lesson_id'] : 0 );
		if ( ! $lesson ) {
			throw new SkipStatement( 'The lesson no longer exists.' );
		}
		$course_id = isset( $ref['course_id'] ) ? (int) $ref['course_id'] : 0;

		return StatementBuilder::build(
			$base + array(
				'verb'    => 'completed',
				'object'  => $lesson,
				'result'  => array( 'completion' => true ),
				'context' => self::context( $student, $course_id, array( 'parent' => Activities::course( $course_id ) ) ),
			)
		);
	}

	/**
	 * Learner completed a course.
	 *
	 * @param array $base    id, actor, timestamp.
	 * @param int   $student Learner.
	 * @param array $ref     course_id.
	 * @return array
	 * @throws SkipStatement When the event no longer has anything to report.
	 */
	private static function course_completed( array $base, $student, array $ref ) {
		$course = self::course( $ref );

		return StatementBuilder::build(
			$base + array(
				'verb'    => 'completed',
				'object'  => $course,
				'result'  => array( 'completion' => true ),
				'context' => self::context( $student, (int) $ref['course_id'] ),
			)
		);
	}

	/**
	 * A quiz attempt: "completed" when it was submitted, "passed" or "failed" once it is graded.
	 *
	 * Completion and the outcome are separate statements, as in the ADL profile. Only the
	 * pass/fail statement carries success, so an LRS that counts successes counts each attempt once.
	 *
	 * @param array  $base Statement basics.
	 * @param array  $ref  attempt_id.
	 * @param string $mode completed|graded.
	 * @return array
	 * @throws SkipStatement When the event no longer has anything to report.
	 */
	private static function attempt( array $base, array $ref, $mode ) {
		global $wpdb;
		$attempt_id = isset( $ref['attempt_id'] ) ? (int) $ref['attempt_id'] : 0;
		$attempt    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ohmylms_quiz_attempts WHERE id=%d", $attempt_id ), ARRAY_A );
		if ( ! $attempt ) {
			throw new SkipStatement( 'The attempt no longer exists.' );
		}
		if ( 'in-progress' === $attempt['status'] ) {
			throw new SkipStatement( 'The attempt was not submitted.' );
		}
		$quiz = function_exists( 'ohmylms_get_quiz' ) ? ohmylms_get_quiz( (int) $attempt['quiz_id'] ) : null;
		if ( ! $quiz ) {
			throw new SkipStatement( 'The quiz no longer exists.' );
		}
		$object = Activities::content( (int) $attempt['quiz_id'] );
		if ( ! $object ) {
			throw new SkipStatement( 'The quiz no longer exists.' );
		}
		$student   = (int) $attempt['student_id'];
		$course_id = (int) $attempt['course_id'];
		$graded    = 'completed' === $attempt['status'];
		$basis     = AttemptReport::basis( $attempt_id, $quiz );
		$score     = array(
			'raw' => (float) $attempt['total'],
			'min' => 0,
			'max' => $basis['max'],
		);

		if ( 'graded' === $mode ) {
			if ( ! $graded ) {
				throw new SkipStatement( 'The attempt is still waiting for a teacher.' );
			}
			$passed = (float) $attempt['total'] >= $basis['passing'];
			$result = $score + array( 'success' => $passed );
			$verb   = $passed ? 'passed' : 'failed';
		} else {
			$result = array( 'completion' => true );
			if ( $graded ) {
				$result += $score;
			}
			$seconds = self::seconds_between( $attempt['start_date'], $attempt['end_date'] );
			if ( null !== $seconds ) {
				$result['seconds'] = $seconds;
			}
			$verb = 'completed';
		}

		return StatementBuilder::build(
			$base + array(
				'verb'    => $verb,
				'object'  => $object,
				'result'  => StatementBuilder::result( $result ),
				'context' => self::context( $student, $course_id, array( 'parent' => Activities::course( $course_id ) ) ),
			)
		);
	}

	/**
	 * One graded answer (a whole question, or one part of a structured question).
	 *
	 * @param array $base Statement basics.
	 * @param array $ref  grade_event_id.
	 * @return array
	 * @throws SkipStatement When the event no longer has anything to report.
	 */
	private static function answered( array $base, array $ref ) {
		global $wpdb;
		$event = GradeEvents::get( isset( $ref['grade_event_id'] ) ? (int) $ref['grade_event_id'] : 0 );
		if ( ! $event ) {
			throw new SkipStatement( 'The grade event no longer exists.' );
		}
		if ( 'unanswered' === $event['reason'] ) {
			throw new SkipStatement( 'The question was left unanswered.' );
		}
		$version = $wpdb->get_row(
			$wpdb->prepare( 'SELECT question_uuid, type, title, body, options FROM %i WHERE id=%d', AssessmentSchema::table( 'qb_question_versions' ), (int) $event['version_id'] ),
			ARRAY_A
		);
		if ( ! $version ) {
			throw new SkipStatement( 'The question version no longer exists.' );
		}
		$student   = (int) $event['student_id'];
		$part_id   = (string) $event['part_id'];
		$course_id = 0;
		$parent    = null;
		$response  = null;

		if ( 'quiz' === $event['source_type'] ) {
			$attempt = $wpdb->get_row( $wpdb->prepare( "SELECT quiz_id, course_id FROM {$wpdb->prefix}ohmylms_quiz_attempts WHERE id=%d", (int) $event['source_id'] ), ARRAY_A );
			if ( $attempt ) {
				$course_id = (int) $attempt['course_id'];
				$parent    = Activities::content( (int) $attempt['quiz_id'] );
			}
			$stored = $wpdb->get_var( $wpdb->prepare( 'SELECT response FROM %i WHERE id=%d AND attempt_id=%d', AssessmentSchema::table( 'attempt_items' ), (int) $event['item_id'], (int) $event['source_id'] ) );
		} else {
			$session = $wpdb->get_row( $wpdb->prepare( 'SELECT course_id FROM %i WHERE id=%d', AssessmentSchema::table( 'practice_sessions' ), (int) $event['source_id'] ), ARRAY_A );
			if ( $session ) {
				$course_id = (int) $session['course_id'];
			}
			$stored = $wpdb->get_var( $wpdb->prepare( 'SELECT response FROM %i WHERE id=%d AND session_id=%d', AssessmentSchema::table( 'practice_items' ), (int) $event['item_id'], (int) $event['source_id'] ) );
		}

		$settings = Settings::all();
		if ( ! empty( $settings['responses'] ) && null !== $stored ) {
			$decoded = json_decode( $stored, true );
			if ( '' !== $part_id && is_array( $decoded ) ) {
				$decoded = isset( $decoded[ $part_id ] ) ? $decoded[ $part_id ] : null;
			}
			$response = ResponseFormat::format( $decoded, self::option_labels( $version['options'] ) );
		}

		$extensions = array( Vocabulary::extension( 'grader' ) => $event['grader'] );
		if ( (int) $event['supersedes'] > 0 ) {
			$extensions[ Vocabulary::extension( 'regrade' ) ] = array(
				'supersedes' => (int) $event['supersedes'],
				'reason'     => $event['reason'],
			);
		}

		return StatementBuilder::build(
			$base + array(
				'verb'    => 'answered',
				'object'  => Activities::question( $version, $part_id ),
				'result'  => StatementBuilder::result(
					array(
						'raw'      => (float) $event['awarded'],
						'min'      => 0,
						'max'      => (float) $event['max_marks'],
						'success'  => (bool) $event['correct'],
						'response' => $response,
					)
				),
				'context' => self::context(
					$student,
					$course_id,
					array(
						'parent'     => $parent,
						'grouping'   => $course_id ? Activities::course( $course_id ) : null,
						'extensions' => $extensions,
					)
				),
			)
		);
	}

	/**
	 * A learner reached the "mastered" level for a skill.
	 *
	 * @param array $base Statement basics.
	 * @param array $ref  term_id.
	 * @return array
	 * @throws SkipStatement When the event no longer has anything to report.
	 */
	private static function skill_mastered( array $base, array $ref ) {
		$skill = Activities::skill( isset( $ref['term_id'] ) ? (int) $ref['term_id'] : 0 );
		if ( ! $skill ) {
			throw new SkipStatement( 'The skill no longer exists.' );
		}

		return StatementBuilder::build(
			$base + array(
				'verb'    => 'mastered',
				'object'  => $skill,
				'context' => StatementBuilder::context( array( 'language' => Activities::language() ) ),
			)
		);
	}

	/**
	 * Course activity for a ref, or skip.
	 *
	 * @param array $ref course_id.
	 * @return array
	 * @throws SkipStatement When the course is gone.
	 */
	private static function course( array $ref ) {
		$course = Activities::course( isset( $ref['course_id'] ) ? (int) $ref['course_id'] : 0 );
		if ( ! $course ) {
			throw new SkipStatement( 'The course no longer exists.' );
		}

		return $course;
	}

	/**
	 * Context for a statement that belongs to a course enrolment.
	 *
	 * @param int   $student   Learner.
	 * @param int   $course_id Course, or 0 when the statement is outside any course.
	 * @param array $extra     parent, grouping, extensions.
	 * @return array
	 */
	private static function context( $student, $course_id, array $extra = array() ) {
		return StatementBuilder::context(
			array(
				'registration' => $course_id > 0 ? Identity::registration( $student, $course_id ) : '',
				'language'     => Activities::language(),
				'parent'       => isset( $extra['parent'] ) ? $extra['parent'] : null,
				'grouping'     => isset( $extra['grouping'] ) ? $extra['grouping'] : null,
				'extensions'   => isset( $extra['extensions'] ) ? $extra['extensions'] : array(),
			)
		);
	}

	/**
	 * Option ID => visible text for a question version, so stored option IDs read as answers.
	 *
	 * @param string $json Version options JSON.
	 * @return array<string,string>
	 */
	private static function option_labels( $json ) {
		$labels = array();
		$items  = json_decode( (string) $json, true );
		foreach ( is_array( $items ) ? $items : array() as $item ) {
			if ( isset( $item['id'], $item['answer'] ) ) {
				$labels[ (string) $item['id'] ] = Activities::plain( $item['answer'] );
			}
		}

		return $labels;
	}

	/**
	 * Seconds between two stored site-time datetimes, or null when either is unset.
	 *
	 * @param string $start Start datetime.
	 * @param string $end   End datetime.
	 * @return int|null
	 */
	private static function seconds_between( $start, $end ) {
		$zero = '0000-00-00 00:00:00';
		if ( $start === $zero || $end === $zero || '' === (string) $start || '' === (string) $end ) {
			return null;
		}
		$from = strtotime( $start );
		$to   = strtotime( $end );

		return ( false === $from || false === $to || $to < $from ) ? null : $to - $from;
	}
}
