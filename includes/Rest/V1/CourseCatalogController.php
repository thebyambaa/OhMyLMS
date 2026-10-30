<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Authored course list and draft creation API.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Learning\CourseCatalog;

defined( 'ABSPATH' ) || exit;

/** Keep discovery separate from legacy course metadata mutations. */
class CourseCatalogController extends \WP_REST_Controller {

	/** Register bounded collection and draft creation. */
	public function register_routes() {
		register_rest_route(
			'ohmylms/v1',
			'/authoring/courses',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => array( $this, 'permission' ),
					'callback'            => array( $this, 'get_items' ),
					'args'                => array(
						'page'   => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100000,
							'default' => 1,
						),
						'search' => array(
							'type'              => 'string',
							'maxLength'         => 100,
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => array( $this, 'permission' ),
					'callback'            => array( $this, 'create_item' ),
					'args'                => array(
						'title' => array(
							'type'      => 'string',
							'required'  => true,
							'minLength' => 1,
							'maxLength' => 190,
						),
					),
				),
			)
		);
	}

	/**
	 * Use the registered course type's creation capability.
	 *
	 * @return true|\WP_Error
	 */
	public function permission() {
		$type = get_post_type_object( OHMYLMS_COURSE_CPT );
		return $type && current_user_can( $type->cap->create_posts ) ? true : new \WP_Error( 'ohmylms_course_forbidden', __( 'You cannot author courses.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Bounded summaries, never pricing, secrets or learner data.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) {
		return rest_ensure_response( CourseCatalog::search( $request['search'], (int) $request['page'] ) );
	}

	/**
	 * Create only a named draft owned by the current author.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) || ! array_key_exists( 'title', $data ) || array_diff( array_keys( $data ), array( 'title' ) ) ) {
			return new \WP_Error( 'ohmylms_course_invalid', __( 'Send only a course title.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$title = trim( sanitize_text_field( $request['title'] ) );
		if ( '' === $title ) {
			return new \WP_Error( 'ohmylms_course_invalid', __( 'A course title is required.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$id = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => OHMYLMS_COURSE_CPT,
					'post_status' => 'draft',
					'post_title'  => $title,
					'post_author' => get_current_user_id(),
				)
			),
			true
		);
		return is_wp_error( $id ) ? $id : new \WP_REST_Response( CourseCatalog::describe( get_post( $id ) ), 201 );
	}
}
