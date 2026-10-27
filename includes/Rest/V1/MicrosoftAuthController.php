<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Microsoft Auth Controller
 *
 * Handles the "Sign in with Microsoft" REST endpoints for the core plugin.
 *
 * @package OhMyLMS\Rest\V1
 * @since 1.0.0
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Services\EmailVerificationService;
use OhMyLMS\Services\MicrosoftAuthService;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class MicrosoftAuthController
 */
class MicrosoftAuthController extends RestController {

	/**
	 * Register routes
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/auth/microsoft',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'microsoft_login' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/auth/microsoft/callback',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'microsoft_callback' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/auth/microsoft/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_microsoft_settings' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_microsoft_settings' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);
	}

	/**
	 * Check if user has permission to manage Microsoft Sign-In settings
	 *
	 * @return bool
	 */
	public function check_admin_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Redirect the browser to Microsoft's consent screen.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return void
	 */
	public function microsoft_login( WP_REST_Request $request ) {
		$redirect_to = $this->sanitize_redirect_to( $request->get_param( 'redirect_to' ) );
		$auth_url    = MicrosoftAuthService::build_authorization_url( $redirect_to );

		if ( is_wp_error( $auth_url ) ) {
			wp_safe_redirect( $this->microsoft_error_redirect_url( $auth_url->get_error_code() ) );
			exit;
		}

		// The authorization endpoint is on login.microsoftonline.com, so a safe redirect is not possible.
		wp_redirect( $auth_url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Fixed Microsoft sign-in host.
		exit;
	}

	/**
	 * Handle Microsoft's redirect back after the user grants (or denies) consent.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return void
	 */
	public function microsoft_callback( WP_REST_Request $request ) {
		$state = sanitize_text_field( (string) $request->get_param( 'state' ) );
		$code  = sanitize_text_field( (string) $request->get_param( 'code' ) );
		$error = sanitize_text_field( (string) $request->get_param( 'error' ) );

		// Consume the state unconditionally (even on deny) so it can never be replayed.
		$redirect_to = MicrosoftAuthService::consume_state( $state );

		if ( $error || false === $redirect_to || empty( $code ) ) {
			wp_safe_redirect( $this->microsoft_error_redirect_url( 'microsoft_auth_denied' ) );
			exit;
		}

		$token = MicrosoftAuthService::exchange_code_for_token( $code );
		if ( is_wp_error( $token ) ) {
			wp_safe_redirect( $this->microsoft_error_redirect_url( $token->get_error_code() ) );
			exit;
		}

		$profile = MicrosoftAuthService::get_userinfo( $token['access_token'] );
		if ( is_wp_error( $profile ) ) {
			wp_safe_redirect( $this->microsoft_error_redirect_url( $profile->get_error_code() ) );
			exit;
		}

		$user = MicrosoftAuthService::find_or_create_user( $profile );
		if ( is_wp_error( $user ) ) {
			wp_safe_redirect( $this->microsoft_error_redirect_url( $user->get_error_code() ) );
			exit;
		}

		if ( ! EmailVerificationService::is_verified( $user->ID ) ) {
			wp_safe_redirect( $this->microsoft_error_redirect_url( 'microsoft_email_unverified' ) );
			exit;
		}

		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );

		// Re-run the guest->user session merge now so the cart is not left behind.
		if ( function_exists( '\OhMyLMS\Commerce\ecommerce' ) ) {
			\OhMyLMS\Commerce\ecommerce()->session->init_session_cookie();
		}

		wp_safe_redirect( '' !== $redirect_to ? $redirect_to : \OhMyLMS\Student\LoginExperience::landing_url( $user, $this->dashboard_url() ) );
		exit;
	}

	/**
	 * Validate a caller-supplied redirect_to is a same-site URL before it rides
	 * along with the OAuth state (it is attacker-controlled input).
	 *
	 * @param mixed $redirect_to Raw redirect_to param.
	 * @return string Sanitized same-site URL, or '' if invalid/absent.
	 */
	private function sanitize_redirect_to( $redirect_to ) {
		$redirect_to = sanitize_text_field( (string) $redirect_to );

		$target = wp_parse_url( $redirect_to );
		$home   = wp_parse_url( home_url() );
		if ( '' === $redirect_to || ! $target || ! isset( $target['host'], $target['scheme'] ) || strtolower( $target['host'] ) !== strtolower( $home['host'] ) || $target['scheme'] !== $home['scheme'] || ( $target['port'] ?? null ) !== ( $home['port'] ?? null ) || isset( $target['user'] ) || isset( $target['pass'] ) ) {
			return '';
		}

		return esc_url_raw( $redirect_to );
	}

	/**
	 * Resolve the profile/dashboard URL, never returning a falsy value (a false
	 * URL would make add_query_arg() fall back to the current request).
	 *
	 * @return string
	 */
	private function dashboard_url() {
		return ohmylms_get_page_permalink( 'profile' );
	}

	/**
	 * Build the dashboard URL with an error flag, for Microsoft auth failures.
	 *
	 * @param string $error_code Error code to surface to the user.
	 * @return string
	 */
	private function microsoft_error_redirect_url( $error_code ) {
		return add_query_arg(
			array(
				'microsoft_error' => $error_code ? $error_code : 'microsoft_auth_failed',
			),
			$this->dashboard_url()
		);
	}

	/**
	 * Settings payload shared by the read and save responses. The client secret
	 * is never returned.
	 *
	 * @return array
	 */
	private function settings_payload() {
		$settings = MicrosoftAuthService::get_settings();

		return array(
			'enabled'           => (bool) $settings['enabled'],
			'client_id'         => $settings['client_id'],
			'tenant'            => $settings['tenant'],
			'has_client_secret' => ! empty( $settings['client_secret'] ),
			'redirect_uri'      => MicrosoftAuthService::get_redirect_uri(),
		);
	}

	/**
	 * Get Microsoft Sign-In settings (admin only).
	 *
	 * @return WP_REST_Response
	 */
	public function get_microsoft_settings() {
		return new WP_REST_Response( $this->settings_payload(), 200 );
	}

	/**
	 * Update Microsoft Sign-In settings (admin only).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function update_microsoft_settings( WP_REST_Request $request ) {
		$existing = MicrosoftAuthService::get_settings();

		// "enabled" is not stored here: it is the add-on's toggle on the Add-ons page.
		$settings = array(
			'client_id'     => sanitize_text_field( (string) $request->get_param( 'client_id' ) ),
			'tenant'        => MicrosoftAuthService::sanitize_tenant( sanitize_text_field( (string) $request->get_param( 'tenant' ) ) ),
			// A blank client_secret means "keep the current one" (it is never sent back to the client).
			'client_secret' => $existing['client_secret'],
		);

		$client_secret = $request->get_param( 'client_secret' );
		if ( ! empty( $client_secret ) ) {
			$settings['client_secret'] = sanitize_text_field( (string) $client_secret );
		}

		update_option( MicrosoftAuthService::OPTION_KEY, $settings );

		return new WP_REST_Response( array_merge( array( 'success' => true ), $this->settings_payload() ), 200 );
	}
}
