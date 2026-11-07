<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Skill coverage of a course.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Learning\Catalog;
use OhMyLMS\Learning\Schema;
use OhMyLMS\Learning\SkillCoverage;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * `GET ohmylms/v1/courses/{id}/skill-coverage` (see docs/api/skill-coverage.md).
 *
 * Read-only. It reports on the saved draft program, for authors who can edit the course.
 */
class SkillCoverageController extends RestController {

	/**
	 * Register the route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/courses/(?P<id>\d+)/skill-coverage',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => array( $this, 'permission' ),
				),
			)
		);
	}

	/**
	 * Course authors only, and only for a course.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permission( WP_REST_Request $request ) {
		if ( ! Schema::ready() ) {
			return new WP_Error( 'ohmylms_learning_unavailable', __( 'Learning storage is unavailable.', 'ohmylms' ), array( 'status' => 503 ) );
		}
		$id = (int) $request['id'];
		if ( OHMYLMS_COURSE_CPT !== get_post_type( $id ) ) {
			return new WP_Error( 'ohmylms_rest_invalid_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		return Catalog::can_edit( $id ) ? true : new WP_Error( 'ohmylms_rest_forbidden', __( 'Sorry, you are not allowed to manage this course.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * The coverage report.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function show( WP_REST_Request $request ) {
		return rest_ensure_response( SkillCoverage::report( (int) $request['id'] ) );
	}
}
