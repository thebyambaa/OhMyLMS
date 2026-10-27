<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Rest Controller base class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Abstracts;

use WP_REST_Controller;

/**
 * Rest Controller base class.
 *
 * @since 0.3.0
 */
abstract class RestController extends WP_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * Authorize access to an existing object of the expected post type.
	 *
	 * @return true|\WP_Error
	 * @param mixed  $request REST request.
	 * @param mixed  $action Action.
	 * @param mixed  $post_types Post types.
	 * @param string $id_param Id param.
	 */
	protected function check_object_permission( $request, $action, $post_types, $id_param = 'id' ) {
		$post_id = isset( $request[ $id_param ] ) ? absint( $request[ $id_param ] ) : 0;
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $post || ! in_array( $post->post_type, (array) $post_types, true ) ) {
			return new \WP_Error( 'ohmylms_rest_invalid_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		if ( ! in_array( $action, array( 'read', 'edit', 'delete' ), true ) || ! current_user_can( $action . '_post', $post_id ) ) {
			return new \WP_Error( 'ohmylms_rest_forbidden', __( 'Sorry, you are not allowed to manage this resource.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Check default permission for rest routes.
	 *
	 * @since 0.3.0
	 *
	 * @TODO: manage permissions from capabilities.
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		return true;
        // phpcs:disable Squiz.PHP.CommentedOutCode.Found
		// return current_user_can( 'manage_jobs' );.
        //phpcs:enable
	}
}
