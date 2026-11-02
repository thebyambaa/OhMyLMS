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
 * Compatibility adapter for the former `ohmylms/v1/assignment` grading method (routes removed; see
 * docs/api/assignments.md). Existing callers retain the response shape and alternate link.
 * Typed REST and school workflows use Assignment\Grading directly. This class registers no routes;
 * the domain operation owns completion, hooks and emails.
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
		$data = \OhMyLMS\Assignment\Grading::review( (int) $request['id'], (int) $request['user_id'], $request->get_json_params() );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$response = rest_ensure_response( $data );
		$response->link_header( 'alternate', get_permalink( (int) $request['id'] ), array( 'type' => 'text/html' ) );
		return $response;
	}
}
