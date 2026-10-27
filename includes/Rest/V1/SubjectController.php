<?php // phpcs:ignore WordPress.Files.FileName -- Required Composer PSR-4 class/file mapping.
/**
 * Subject course ordering administration.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Curriculum\Access;
use OhMyLMS\Curriculum\SubjectCourses;

defined( 'ABSPATH' ) || exit;

/** Placement uses the existing curriculum link routes; this endpoint orders those placements. */
class SubjectController extends RestController {

	/** Register private administration endpoints. */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/subjects/(?P<id>\d+)/courses',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => array( Access::class, 'admin' ),
					'callback'            => array( $this, 'show' ),
				),
				array(
					'methods'             => 'PUT',
					'permission_callback' => array( Access::class, 'admin' ),
					'callback'            => array( $this, 'save' ),
				),
			)
		);
	}

	/**
	 * Read the complete authoring order.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function show( $request ) {
		return SubjectCourses::view( (int) $request['id'] );
	}

	/**
	 * Save an order without creating/removing memberships.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function save( $request ) {
		return SubjectCourses::save( (int) $request['id'], $request->get_param( 'course_ids' ), $request->get_param( 'revision' ) );
	}
}
