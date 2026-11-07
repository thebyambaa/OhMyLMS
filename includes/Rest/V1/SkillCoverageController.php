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
use OhMyLMS\Learning\SkillGapFix;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * `GET ohmylms/v1/courses/{id}/skill-coverage` and `POST .../skill-coverage/lessons` (see docs/api/skill-coverage.md).
 *
 * The report is read-only and covers the saved draft program, for authors who can edit the course. The POST
 * fixes a missing-lesson gap in one step; it checks the draft revision like every other draft write.
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
		register_rest_route(
			$this->namespace,
			'/courses/(?P<id>\d+)/skill-coverage/lessons',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_lesson' ),
					'permission_callback' => array( $this, 'permission' ),
					'args'                => array(
						'skill_id' => array(
							'type'     => 'integer',
							'required' => true,
						),
						'unit_id'  => array(
							'type'     => 'integer',
							'required' => true,
						),
						'revision' => array(
							'type'     => 'string',
							'required' => true,
						),
						'title'    => array( 'type' => 'string' ),
					),
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

	/**
	 * Create a draft lesson for a skill and place it in a unit.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function add_lesson( WP_REST_Request $request ) {
		$result = SkillGapFix::add_lesson( (int) $request['id'], (int) $request['skill_id'], (int) $request['unit_id'], (string) $request['title'], (string) $request['revision'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$response = rest_ensure_response( $result );
		$response->set_status( 201 );
		return $response;
	}
}
