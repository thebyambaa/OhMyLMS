<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Tab preferences REST controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Extensions\Rest;

use OhMyLMS\Extensions\TabPreferences;
use WP_REST_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * `PUT ohmylms/v1/tab-preferences/<scope>`: the current manager's own tab layout for one admin section
 * ({@see TabPreferences}).
 */
class TabPreferencesController extends WP_REST_Controller {

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
	protected $rest_base = 'tab-preferences';

	/**
	 * Register the route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<scope>[a-z-]+)',
			array(
				'methods'             => 'PUT',
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'callback'            => array( $this, 'update_item' ),
			)
		);
	}

	/**
	 * Administrators and LMS managers save their own layout.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public function update_item_permissions_check( $request ) {
		return current_user_can( 'manage_options' ) || current_user_can( 'manage_ohmylms' );
	}

	/**
	 * Save the layout of `scope` from the JSON body.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		return TabPreferences::save( $request );
	}
}
