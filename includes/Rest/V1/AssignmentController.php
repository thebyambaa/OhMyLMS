<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Assignment grading pipeline.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * The grading pipeline of the former `ohmylms/v1/assignment` routes (removed; see
 * docs/api/assignments.md). `AssignmentsController` and `Schools\Service::grade()` grade through
 * it, so learners keep getting the same completion, hooks and emails. It registers no routes.
 */
class AssignmentController {

	/**
	 * Grade one learner's submission rows and run the completion and notification hooks.
	 *
	 * Callers check the permission and validate the score first.
	 *
	 * @param WP_REST_Request $request URL params `id` (assignment) and `user_id` (learner); JSON body
	 *                                 is a list of attempt rows (`id`, `score`, `status`, `note`).
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_attempt_report( $request ) {
		$id         = (int) $request['id'];
		$attempt_id = (int) $request['user_id'];

		$post = get_post( $id );
		if ( empty( $id ) || empty( $post->ID ) || OHMYLMS_ASSIGNMENT_CPT !== $post->post_type ) {
			return new WP_Error( 'ohmylms_rest_invalid_assignemnt_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		$get_data = $request->get_json_params();

		$quiz                     = ohmylms_get_assignment( $post->ID );
		$pass_marks               = get_post_meta( $post->ID, '_maximum_pass_points', true );
		$course_id                = ohmylms_get_course_by_content_id( $post->ID );
		$previous_completion_rate = 0;
		if ( isset( $get_data[0]['score'], $get_data[0]['status'] ) ) {
			if ( $get_data[0]['score'] >= $pass_marks ) {
				$get_data[0]['status'] = 'passed';

				$student = new \OhMyLMS\Data\Student( $attempt_id );
				if ( $student ) {
					$previous_completion_rate = $student->get_over_all_completion_rate( $course_id );
					$student->complete_lesson( $post->ID, $course_id );
				}
			} else {
				$get_data[0]['status'] = 'failed';
			}
		}
		$quiz->update_assignment_attempts( $attempt_id, $get_data );

		$report = $quiz->get_assignment_attempts( $attempt_id );

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
		$response                               = rest_ensure_response( $data );

		$response->link_header( 'alternate', get_permalink( $id ), array( 'type' => 'text/html' ) );

		do_action( 'ohmylms_after_assignment_review', $post->ID, $course_id, $attempt_id, $get_data[0]['status'] );

		if ( class_exists( '\OhMyLMS\Engagement\Leaderboard' ) ) {
			$avg_marks = \OhMyLMS\Engagement\Leaderboard::get_student_avg_assignment_marks( $attempt_id, $post->ID );
			do_action( 'ohmylms_pro_after_assignment_review', $post->ID, $course_id, $attempt_id, $avg_marks );
		}
		$student                 = new \OhMyLMS\Data\Student( $attempt_id );
		$maybe_course_completion = $student && $student->is_course_completed( $course_id ) ? 'yes' : 'no';

		if ( 'yes' === $maybe_course_completion ) {
			do_action( 'ohmylms_student_completed_course_after_reviewing_assignment', $attempt_id, $course_id );
		}

		$completion_rate = $student->get_over_all_completion_rate( $course_id );
		if ( (int) ( $completion_rate ) === 100 && (int) ( $previous_completion_rate ) !== 100 && ! \OhMyLMS\Learning\CourseProgram::managed( $attempt_id, $course_id ) ) {
			global $wpdb;
			$table_name  = $wpdb->prefix . 'ohmylms_user_enrollment';
			$enroll_data = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d AND course_id = %d', $table_name, $attempt_id, $course_id ), ARRAY_A );
			if ( isset( $enroll_data['order_id'] ) ) {
				do_action( 'ohmylms_course_completed', $attempt_id, $course_id, $enroll_data['order_id'] );
			}
		}

		return $response;
	}
}
