<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * WordPress post endpoints for lesson authoring.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Learning;

defined( 'ABSPATH' ) || exit;

/** Keep lesson content restricted to its editors on the new core REST endpoints. */
class NativeLessonController extends \WP_REST_Posts_Controller {

	/**
	 * Serialize native saves with the typed lesson authoring boundary.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function update_item( $request ) {
		return LessonAuthoring::locked(
			(int) $request['id'],
			function () use ( $request ) {
				return parent::update_item( $request );
			}
		);
	}

	/**
	 * Filter collection results by permission to edit each lesson.
	 *
	 * @param \WP_Post $post Post.
	 * @return bool Whether the post can be returned.
	 */
	public function check_read_permission( $post ) {
		return current_user_can( 'edit_post', $post->ID ) && parent::check_read_permission( $post );
	}

	/**
	 * Check collection access without exposing enrolled lesson content publicly.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error Permission result.
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error( 'rest_cannot_view', __( 'You cannot edit lessons.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return parent::get_items_permissions_check( $request );
	}

	/**
	 * Check access to a particular lesson.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error Permission result.
	 */
	public function get_item_permissions_check( $request ) {
		if ( ! current_user_can( 'edit_post', (int) $request['id'] ) ) {
			return new \WP_Error( 'rest_cannot_view', __( 'You cannot edit this lesson.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return parent::get_item_permissions_check( $request );
	}
}
