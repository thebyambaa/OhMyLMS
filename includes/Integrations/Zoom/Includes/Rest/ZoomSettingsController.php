<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * ZoomSettingsController class.
 *
 * @package ohmylms-pro
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\Zoom\Includes\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class ZoomSettingsController
 *
 * @package OhMyLMS\Rest\V1
 * @since 1.0.0
 */
class ZoomSettingsController {
	/**
	 * The single instance of the class.
	 *
	 * @var ZoomSettingsController
	 * @since 1.0.0
	 */
	protected static $instance = null;

	/**
	 * Path
	 *
	 * @var string
	 */
	protected $base = 'zoom/settings';

	/**
	 * REST API namespace
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * Get instance
	 *
	 * @since 1.0.0
	 *
	 * @return ZoomSettingsController
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register routes
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->base . '/credentials',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_credentials' ),
					'permission_callback' => array( $this, 'admin_permission' ),
					'args'                => $this->get_save_credentials_args(),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_credentials' ),
					'permission_callback' => array( $this, 'admin_permission' ),
				),
			)
		);
	}

	/**
	 * Check if the user has admin permission.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function admin_permission() {
		return \current_user_can( 'manage_options' );
	}

	/**
	 * Save zoom settings
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function save_credentials( WP_REST_Request $request ) {
		$account_id    = \sanitize_text_field( $request->get_param( 'account_id' ) );
		$client_id     = \sanitize_text_field( $request->get_param( 'client_id' ) );
		$client_secret = \sanitize_text_field( $request->get_param( 'client_secret' ) );

		$user_id = \get_current_user_id();

		// Save all settings as a single array under one user meta key.
		$settings = array(
			'account_id'    => $account_id,
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
		);
		\update_user_meta( $user_id, 'ohmylms_zoom_api_credentials', $settings );

		// Webhook secret token is site-wide (verifies an unauthenticated
		// endpoint), so it's stored as an option rather than user meta.
		if ( null !== $request->get_param( 'webhook_secret_token' ) ) {
			\update_option(
				'ohmylms_zoom_webhook_secret_token',
				\sanitize_text_field( $request->get_param( 'webhook_secret_token' ) )
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(),
				'message' => __( 'Settings saved successfully', 'ohmylms' ),
			),
			200
		);
	}

	/**
	 * Get zoom settings
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function get_credentials( $request ) {
		$user_id  = \get_current_user_id();
		$settings = \get_user_meta( $user_id, 'ohmylms_zoom_api_credentials', true );
		if ( empty( $settings ) ) {
			$settings = array();
		}
		$settings['webhook_secret_token'] = \get_option( 'ohmylms_zoom_webhook_secret_token', '' );
		$settings['webhook_url']          = \rest_url( 'ohmylms/v1/zoom/webhook' );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $settings,
			),
			200
		);
	}

	/**
	 * Get save settings args
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_save_credentials_args() {
		return array(
			'account_id'           => array(
				'description' => __( 'Zoom Account ID', 'ohmylms' ),
				'type'        => 'string',
				'required'    => true,
			),
			'client_id'            => array(
				'description' => __( 'Zoom Client ID', 'ohmylms' ),
				'type'        => 'string',
				'required'    => true,
			),
			'client_secret'        => array(
				'description' => __( 'Zoom Client Secret', 'ohmylms' ),
				'type'        => 'string',
				'required'    => true,
			),
			'webhook_secret_token' => array(
				'description' => __( 'Zoom Webhook Secret Token (from the app\'s Event Subscriptions page)', 'ohmylms' ),
				'type'        => 'string',
				'required'    => false,
			),
		);
	}
}
