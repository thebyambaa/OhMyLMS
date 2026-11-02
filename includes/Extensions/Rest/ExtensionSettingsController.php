<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Extension settings REST controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Extensions\Rest;

use OhMyLMS\Extensions\Settings;
use WP_Error;
use WP_REST_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * `ohmylms/v1/extension-settings/<type>/<id>`: the namespaced extension settings of one LMS post
 * ({@see Settings}). Only users who can edit the post read or write them.
 */
class ExtensionSettingsController extends WP_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'extension-settings';

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<type>[a-z]+)/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
			)
		);
	}

	/**
	 * The post must be LMS content of the named type, and the user must be able to edit it.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! Settings::supports( $request['type'] ) || ! $post || 'ohmylms-' . $request['type'] !== $post->post_type ) {
			return new WP_Error( 'ohmylms_invalid_content', 'Invalid LMS content.', array( 'status' => 404 ) );
		}
		return current_user_can( 'edit_post', $post->ID ) ? true : new WP_Error( 'ohmylms_forbidden', 'You cannot edit this content.', array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Same rule as reading.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		return $this->get_item_permissions_check( $request );
	}

	/**
	 * Saved settings that apply to the type.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_item( $request ) {
		return rest_ensure_response( array( 'settings' => (object) Settings::stored( $request['type'], $request['id'] ) ) );
	}

	/**
	 * Validate and save `settings`, then return what is stored.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$stored = Settings::update( $request['type'], $request['id'], $request->get_param( 'settings' ) );
		return is_wp_error( $stored ) ? $stored : rest_ensure_response( array( 'settings' => (object) $stored ) );
	}
}
