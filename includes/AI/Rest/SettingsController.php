<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * AI tutor settings REST controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\AI\Rest;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\AI\AdminPage;
use OhMyLMS\AI\Models;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * `ohmylms/v1/ai`, `/ai/models` and `/ai/test`: the Settings → AI tutor tab.
 *
 * Administrators only. The saved API key is never returned; {@see AdminPage} owns the snapshot,
 * the save and the connection test.
 */
class SettingsController extends RestController {

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/ai/models',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'permissions_check' ),
				'callback'            => array( $this, 'get_models' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/ai',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => array( $this, 'permissions_check' ),
					'callback'            => array( $this, 'get_item' ),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => array( $this, 'permissions_check' ),
					'callback'            => array( $this, 'update_item' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/ai/test',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'permissions_check' ),
				'callback'            => array( $this, 'test_connection' ),
			)
		);
	}

	/**
	 * Provider credentials and tool policies are for administrators.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function permissions_check( $request ) {
		return AdminPage::can();
	}

	/**
	 * Models the saved provider offers.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function get_models( $request ) {
		return Models::listing();
	}

	/**
	 * Settings without the saved key.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_item( $request ) {
		return AdminPage::read();
	}

	/**
	 * Save tool policies and the shared connection.
	 *
	 * @param WP_REST_Request $request Administrator JSON input.
	 * @return \WP_REST_Response
	 */
	public function update_item( $request ) {
		return AdminPage::save( $request );
	}

	/**
	 * Test the saved credentials.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function test_connection( $request ) {
		return AdminPage::test();
	}
}
