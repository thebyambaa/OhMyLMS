<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Shared assignment grading and completion pipeline.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Assignment;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/** Domain operation shared by REST, school workflows and compatibility adapters. */
final class Grading {

	/**
	 * Review submission rows, preserving completion, notification and review hooks.
	 *
	 * Callers authorize the assignment, learner and attempt scope and validate scores first.
	 *
	 * @param int   $id         Assignment post ID.
	 * @param int   $student_id Learner ID.
	 * @param array $get_data   Validated attempt rows (id, score, status and note).
	 * @return array|WP_Error Report and additional data, or the established invalid-ID error.
	 */
	public static function review( $id, $student_id, array $get_data ) {
		$id         = (int) $id;
		$student_id = (int) $student_id;

		$post = get_post( $id );
		if ( empty( $id ) || empty( $post->ID ) || OHMYLMS_ASSIGNMENT_CPT !== $post->post_type ) {
			return new WP_Error( 'ohmylms_rest_invalid_assignemnt_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		$quiz                     = ohmylms_get_assignment( $post->ID );
		$pass_marks               = get_post_meta( $post->ID, '_maximum_pass_points', true );
		$course_id                = ohmylms_get_course_by_content_id( $post->ID );
		$previous_completion_rate = 0;
		if ( isset( $get_data[0]['score'], $get_data[0]['status'] ) ) {
			if ( $get_data[0]['score'] >= $pass_marks ) {
				$get_data[0]['status'] = 'passed';

				$student = new \OhMyLMS\Data\Student( $student_id );
				if ( $student ) {
					$previous_completion_rate = $student->get_over_all_completion_rate( $course_id );
					$student->complete_lesson( $post->ID, $course_id );
				}
			} else {
				$get_data[0]['status'] = 'failed';
			}
		}
		$quiz->update_assignment_attempts( $student_id, $get_data );

		$report = $quiz->get_assignment_attempts( $student_id );

		$data = array(
			'report' => $report,
		);

		$total_marks = get_post_meta( $post->ID, '_total_points', true );

		$course                  = ohmylms_get_course( $course_id );
		$data['additional_data'] = array(
			'course_id'   => $course->get_id(),
			'course_name' => $course->get_name(),
		);

		$data['additional_data']['total_marks'] = $total_marks;
		$data['additional_data']['pass_marks']  = $pass_marks;

		/**
		 * Fires once after a learner's assignment submission is graded, from every grading route.
		 *
		 * @param int    $assignment_id Assignment.
		 * @param int    $course_id     Course.
		 * @param int    $student_id    Learner (not an attempt ID).
		 * @param string $status        `passed` or `failed`.
		 * @param int    $attempt_id    The graded submission row (0 when the body named none).
		 */
		do_action( 'ohmylms_after_assignment_review', $post->ID, $course_id, $student_id, $get_data[0]['status'], (int) ( $get_data[0]['id'] ?? 0 ) );

		if ( class_exists( '\OhMyLMS\Engagement\Leaderboard' ) ) {
			$avg_marks = \OhMyLMS\Engagement\Leaderboard::get_student_avg_assignment_marks( $student_id, $post->ID );
			do_action( 'ohmylms_pro_after_assignment_review', $post->ID, $course_id, $student_id, $avg_marks );
		}
		$student                 = new \OhMyLMS\Data\Student( $student_id );
		$maybe_course_completion = $student && $student->is_course_completed( $course_id ) ? 'yes' : 'no';

		if ( 'yes' === $maybe_course_completion ) {
			do_action( 'ohmylms_student_completed_course_after_reviewing_assignment', $student_id, $course_id );
		}

		$completion_rate = $student->get_over_all_completion_rate( $course_id );
		if ( (int) ( $completion_rate ) === 100 && (int) ( $previous_completion_rate ) !== 100 && ! \OhMyLMS\Learning\CourseProgram::managed( $student_id, $course_id ) ) {
			$enroll_data = \OhMyLMS\Student\Enrollments::legacy_row( $student_id, $course_id );
			if ( isset( $enroll_data['order_id'] ) ) {
				do_action( 'ohmylms_course_completed', $student_id, $course_id, $enroll_data['order_id'] );
			}
		}

		return $data;
	}
}
