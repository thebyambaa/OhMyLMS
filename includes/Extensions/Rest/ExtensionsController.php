<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Extension manifest REST controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Extensions\Rest;

use OhMyLMS\Extensions\Registry;
use OhMyLMS\Extensions\Settings;
use WP_REST_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * `GET ohmylms/v1/extensions`: the registered extensions (public API v1) and their settings definitions.
 */
class ExtensionsController extends WP_REST_Controller {

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
	protected $rest_base = 'extensions';

	/**
	 * Register the route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => 'GET',
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'callback'            => array( $this, 'get_items' ),
			)
		);
	}

	/**
	 * Authors read the manifest.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * The extension manifest.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) {
		return rest_ensure_response(
			array_merge(
				Registry::manifest(),
				array(
					'apiVersion' => 1,
					'settings'   => Settings::manifest(),
				)
			)
		);
	}
}
