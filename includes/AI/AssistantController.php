<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping.
/**
 * Administrator-only chat UI and REST entry points.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\AI;

use OhMyLMS\MCP\Server;

defined( 'ABSPATH' ) || exit;

/** Separate from learner feedback and public MCP bearer access. */
final class AssistantController {
	/** Register hooks. */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_footer', array( __CLASS__, 'root' ) );
	}

	/**
	 * Verify both administrative access and a fresh REST nonce.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return bool|\WP_Error
	 */
	public static function permission( $request ) {
		if ( ! Server::can_manage() || ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
			return new \WP_Error( 'assistant_forbidden', __( 'Administrator access and a valid session are required.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/** Register a small explicit route surface. */
	public static function routes() {
		register_rest_route(
			'ohmylms/v1',
			'/admin-assistant/attachments',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( __CLASS__, 'permission' ),
				'callback'            => array( AssistantAttachments::class, 'upload' ),
			)
		);
		register_rest_route(
			'ohmylms/v1',
			'/admin-assistant/attachments/(?P<id>[a-f0-9-]{36})',
			array(
				'methods'             => 'DELETE',
				'permission_callback' => array( __CLASS__, 'permission' ),
				'callback'            => array( AssistantAttachments::class, 'remove' ),
			)
		);
		foreach ( array(
			''          => 'GET',
			'/settings' => 'POST',
			'/message'  => 'POST',
		) as $suffix => $method ) {
			register_rest_route(
				'ohmylms/v1',
				'/admin-assistant' . $suffix,
				array(
					'methods'             => $method,
					'permission_callback' => array( __CLASS__, 'permission' ),
					'callback'            => array( __CLASS__, 'dispatch' ),
				)
			);
		}
	}

	/**
	 * Dispatch only JSON objects, with bounded size.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return array|\WP_Error
	 */
	public static function dispatch( $request ) {
		if ( 'GET' === $request->get_method() ) {
			return array(
				'settings'    => AssistantSettings::summary(),
				'mcp'         => Server::readiness(),
				'tools'       => array_keys( AssistantTools::definitions() ),
				'attachments' => AssistantAttachments::limits(),
			);
		}
		$data = $request->get_json_params();
		if ( ! is_array( $data ) || strlen( $request->get_body() ) > 30000 ) {
			return new \WP_Error( 'assistant_body', __( 'Invalid or oversized request.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		return str_ends_with( $request->get_route(), '/settings' ) ? AssistantSettings::save( $data ) : AssistantChat::message( $data );
	}

	/**
	 * Restrict the launcher to the LMS administrator screen.
	 *
	 * @return bool Visibility.
	 */
	private static function visible() {
		$screen = get_current_screen();
		return $screen && 'toplevel_page_ohmylms' === $screen->id && Server::can_manage();
	}

	/** Load scoped styles only on the LMS admin screen. */
	public static function assets() {
		if ( self::visible() ) {
			wp_enqueue_style( 'ohmylms-admin-assistant', plugins_url( 'assets/css/admin-assistant.css', OHMYLMS_FILE ), array(), (string) filemtime( dirname( OHMYLMS_FILE ) . '/assets/css/admin-assistant.css' ) );
		}
	}

	/** The launcher is absent for learners and other WordPress screens. */
	public static function root() {
		if ( self::visible() ) {
			echo '<div id="ohmylms-admin-assistant-root"></div>';
		}
	}
}
