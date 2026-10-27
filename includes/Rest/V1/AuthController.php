<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Auth Controller
 *
 * Handles the "Sign in with Google" REST endpoints for the core plugin.
 *
 * @package OhMyLMS\Rest\V1
 * @since 1.0.0
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Services\GoogleAuthService;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class AuthController
 */
class AuthController extends RestController {

	/**
	 * Register routes
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/auth/google',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'google_login' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/auth/google/callback',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'google_callback' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/auth/google/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_google_settings' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_google_settings' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);
	}

	/**
	 * Check if user has permission to manage Google Sign-In settings
	 *
	 * @return bool
	 */
	public function check_admin_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Redirect the browser to Google's consent screen.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return void
	 */
	public function google_login( WP_REST_Request $request ) {
		$redirect_to = $this->sanitize_redirect_to( $request->get_param( 'redirect_to' ) );
		$auth_url    = GoogleAuthService::build_authorization_url( $redirect_to );

		if ( is_wp_error( $auth_url ) ) {
			wp_safe_redirect( $this->google_error_redirect_url( $auth_url->get_error_code() ) );
			exit;
		}

		wp_redirect( $auth_url );
		exit;
	}

	/**
	 * Handle Google's redirect back after the user grants (or denies) consent.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return void
	 */
	public function google_callback( WP_REST_Request $request ) {
		$state = sanitize_text_field( (string) $request->get_param( 'state' ) );
		$code  = sanitize_text_field( (string) $request->get_param( 'code' ) );
		$error = sanitize_text_field( (string) $request->get_param( 'error' ) );

		// Consume the state unconditionally (even on deny) so it can never be replayed.
		// Returns false if invalid/expired, otherwise the redirect_to stashed with it ('' if none).
		$redirect_to = GoogleAuthService::consume_state( $state );

		if ( $error || false === $redirect_to || empty( $code ) ) {
			wp_safe_redirect( $this->google_error_redirect_url( 'google_auth_denied' ) );
			exit;
		}

		$token = GoogleAuthService::exchange_code_for_token( $code );
		if ( is_wp_error( $token ) ) {
			wp_safe_redirect( $this->google_error_redirect_url( $token->get_error_code() ) );
			exit;
		}

		$profile = GoogleAuthService::get_userinfo( $token['access_token'] );
		if ( is_wp_error( $profile ) ) {
			wp_safe_redirect( $this->google_error_redirect_url( $profile->get_error_code() ) );
			exit;
		}

		$user = GoogleAuthService::find_or_create_user( $profile );
		if ( is_wp_error( $user ) ) {
			wp_safe_redirect( $this->google_error_redirect_url( $user->get_error_code() ) );
			exit;
		}

		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );

		// Re-run the guest->user session merge now, in this request, so the
		// cart isn't left behind waiting for the next page load's init hook.
		if ( function_exists( '\OhMyLMS\Commerce\ecommerce' ) ) {
			\OhMyLMS\Commerce\ecommerce()->session->init_session_cookie();
		}

		wp_safe_redirect( '' !== $redirect_to ? $redirect_to : \OhMyLMS\Student\LoginExperience::landing_url( $user, $this->dashboard_url() ) );
		exit;
	}

	/**
	 * Validate a caller-supplied redirect_to is a same-site URL before it's
	 * allowed to ride along with the OAuth state (never trust it otherwise —
	 * it's attacker-controlled input on an unauthenticated GET request).
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
	 * Resolve the profile/dashboard URL, never returning a falsy value.
	 *
	 * ohmylms_get_dashboard_url() (ohmylms_get_page_url('profile')) can
	 * return false when the "profile" page option isn't set — add_query_arg()
	 * treats a false $url as "not given" and falls back to the current
	 * REQUEST_URI, which would redirect this callback to itself in a loop.
	 * ohmylms_get_page_permalink() has the same fallback-to-home_url() safety
	 * net already used by Ajax::login() elsewhere in this plugin.
	 *
	 * @return string
	 */
	private function dashboard_url() {
		return ohmylms_get_page_permalink( 'profile' );
	}

	/**
	 * Build the login/dashboard page URL with an error flag, for Google auth failures.
	 *
	 * @param string $error_code Error code to surface to the user.
	 * @return string
	 */
	private function google_error_redirect_url( $error_code ) {
		return add_query_arg(
			array(
				'google_error' => $error_code ? $error_code : 'google_auth_failed',
			),
			$this->dashboard_url()
		);
	}

	/**
	 * Get Google Sign-In settings (admin only; client secret is never exposed).
	 *
	 * @return WP_REST_Response
	 */
	public function get_google_settings() {
		$settings = GoogleAuthService::get_settings();

		return new WP_REST_Response(
			array(
				'enabled'           => (bool) $settings['enabled'],
				'client_id'         => $settings['client_id'],
				'has_client_secret' => ! empty( $settings['client_secret'] ),
				'redirect_uri'      => GoogleAuthService::get_redirect_uri(),
			),
			200
		);
	}

	/**
	 * Update Google Sign-In settings (admin only).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function update_google_settings( WP_REST_Request $request ) {
		$existing = GoogleAuthService::get_settings();

		// "enabled" is not stored here — it's the addon's on/off toggle on the Addons page.
		$settings = array(
			'client_id'     => sanitize_text_field( (string) $request->get_param( 'client_id' ) ),
			// Blank client_secret in the request means "keep the current one" (it is never sent back to the client).
			'client_secret' => $existing['client_secret'],
		);

		$client_secret = $request->get_param( 'client_secret' );
		if ( ! empty( $client_secret ) ) {
			$settings['client_secret'] = sanitize_text_field( (string) $client_secret );
		}

		update_option( GoogleAuthService::OPTION_KEY, $settings );

		$settings = GoogleAuthService::get_settings();

		return new WP_REST_Response(
			array(
				'success'           => true,
				'enabled'           => $settings['enabled'],
				'client_id'         => $settings['client_id'],
				'has_client_secret' => ! empty( $settings['client_secret'] ),
				'redirect_uri'      => GoogleAuthService::get_redirect_uri(),
			),
			200
		);
	}
}
