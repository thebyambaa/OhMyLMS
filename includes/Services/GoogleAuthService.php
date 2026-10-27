<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Google Auth Service
 *
 * Handles the "Sign in with Google" OAuth 2.0 authorization-code flow for the
 * core OhMyLMS plugin: building the consent URL, exchanging the code for
 * tokens, fetching the user's profile, and resolving/creating the matching
 * WordPress user.
 *
 * The Community plugin has its own copy of this flow (its own callback route
 * and its own settings option) but automatically borrows these credentials
 * once they're configured here — see
 * OhMyLMS_Community\Services\GoogleAuthService::get_settings(). Both
 * plugins deliberately share the same Google-identity meta key
 * (META_GOOGLE_ID) so a user linked via either entry point is recognized by
 * the other and never re-triggers account creation or the reclaim flow below.
 *
 * @package OhMyLMS\Services
 * @since 1.0.0
 */

namespace OhMyLMS\Services;

use OhMyLMS\Integrations\GoogleSignIn\Hooks as GoogleSignInHooks;
use WP_Error;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class GoogleAuthService
 */
class GoogleAuthService {
	/**
	 * Link user id.
	 *
	 * @var mixed
	 */
	private static $link_user_id = 0;

	const OPTION_KEY        = 'ohmylms_google_oauth';
	const STATE_TRANSIENT   = 'ohmylms_lms_google_state_';
	const STATE_TTL         = 600; // 10 minutes.
	const META_GOOGLE_ID    = '_ohmylms_google_id'; // Shared with the Community plugin — do not change without updating both.
	const AUTHORIZATION_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
	const TOKEN_URL         = 'https://oauth2.googleapis.com/token';
	const USERINFO_URL      = 'https://www.googleapis.com/oauth2/v3/userinfo';

	/**
	 * Get the stored Google OAuth settings.
	 *
	 * "enabled" is not stored here — it's the addon's on/off toggle on the
	 * Addons page (see GoogleSignInHooks::is_enabled()). It's still returned
	 * in this shape so existing consumers (including the Community plugin's
	 * cross-plugin check) don't need to know that detail.
	 *
	 * @return array{enabled: bool, client_id: string, client_secret: string}
	 */
	public static function get_settings() {
		$settings = get_option( self::OPTION_KEY, array() );

		$settings = wp_parse_args(
			is_array( $settings ) ? $settings : array(),
			array(
				'client_id'     => '',
				'client_secret' => '',
			)
		);

		$settings['enabled'] = GoogleSignInHooks::is_enabled();

		return $settings;
	}

	/**
	 * Whether the Google Sign-In addon is turned on (Addons page) and has the
	 * credentials required to run.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		$settings = self::get_settings();
		return ! empty( $settings['enabled'] ) && ! empty( $settings['client_id'] ) && ! empty( $settings['client_secret'] );
	}

	/**
	 * The redirect URI registered with Google for this site.
	 *
	 * @return string
	 */
	public static function get_redirect_uri() {
		return rest_url( 'ohmylms/v1/auth/google/callback' );
	}

	/**
	 * Build the Google consent screen URL and remember a one-time state value
	 * to protect the callback against CSRF. The page the visitor started
	 * from (e.g. checkout) rides along in the same state transient so the
	 * callback can send them back there instead of the dashboard.
	 *
	 * @param string $redirect_to Same-site URL to return to after login, or ''.
	 * @return string|WP_Error
	 */
	public static function build_authorization_url( $redirect_to = '' ) {
		$settings = self::get_settings();

		if ( ! self::is_configured() ) {
			return new WP_Error( 'google_not_configured', __( 'Google Sign-In is not configured.', 'ohmylms' ) );
		}

		$state = wp_generate_password( 32, false );
		// Bind this authorization attempt to the browser that started it.
		setcookie(
			'ohmylms_google_state',
			$state,
			array(
				'expires'  => time() + self::STATE_TTL,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		set_transient(
			self::STATE_TRANSIENT . $state,
			array(
				'redirect_to'  => $redirect_to,
				'link_user_id' => get_current_user_id(),
			),
			self::STATE_TTL
		);

		$args = array(
			'client_id'     => $settings['client_id'],
			'redirect_uri'  => self::get_redirect_uri(),
			'response_type' => 'code',
			'scope'         => 'openid email profile',
			'state'         => $state,
			'prompt'        => 'select_account',
		);

		return add_query_arg( $args, self::AUTHORIZATION_URL );
	}

	/**
	 * Validate and consume a state value returned by Google.
	 *
	 * @param string $state State parameter from the callback request.
	 * @return string|false The redirect_to URL stashed alongside the state
	 *                       ('' if none was given), or false if the state is
	 *                       missing/expired/already used.
	 */
	public static function consume_state( $state ) {
		self::$link_user_id = 0;
		if ( empty( $state ) || empty( $_COOKIE['ohmylms_google_state'] ) || ! hash_equals( (string) $_COOKIE['ohmylms_google_state'], (string) $state ) ) {
			return false;
		}

		$key   = self::STATE_TRANSIENT . $state;
		$value = get_transient( $key );
		delete_transient( $key );
		setcookie(
			'ohmylms_google_state',
			'',
			array(
				'expires'  => time() - HOUR_IN_SECONDS,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		if ( false === $value ) {
			return false;
		}

		if ( is_array( $value ) ) {
			$link_user = (int) ( $value['link_user_id'] ?? 0 );
			if ( $link_user && (int) wp_validate_auth_cookie( '', 'logged_in' ) !== $link_user ) {
				return false; }
			self::$link_user_id = $link_user;
			return (string) ( $value['redirect_to'] ?? '' );
		}
		return '1' === $value ? '' : $value;
	}

	/**
	 * Exchange an authorization code for an access token.
	 *
	 * @param string $code Authorization code from Google.
	 * @return array|WP_Error Token response array, or WP_Error on failure.
	 */
	public static function exchange_code_for_token( $code ) {
		$settings = self::get_settings();

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 15,
				'body'    => array(
					'code'          => $code,
					'client_id'     => $settings['client_id'],
					'client_secret' => $settings['client_secret'],
					'redirect_uri'  => self::get_redirect_uri(),
					'grant_type'    => 'authorization_code',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== wp_remote_retrieve_response_code( $response ) || empty( $body['access_token'] ) ) {
			return new WP_Error( 'google_token_exchange_failed', __( 'Could not verify your Google account. Please try again.', 'ohmylms' ) );
		}

		return $body;
	}

	/**
	 * Fetch the authenticated user's profile from Google.
	 *
	 * @param string $access_token Access token from the token exchange.
	 * @return array|WP_Error
	 */
	public static function get_userinfo( $access_token ) {
		$response = wp_remote_get(
			self::USERINFO_URL,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== wp_remote_retrieve_response_code( $response ) || empty( $body['email'] ) ) {
			return new WP_Error( 'google_userinfo_failed', __( 'Could not retrieve your Google profile. Please try again.', 'ohmylms' ) );
		}

		if ( empty( $body['email_verified'] ) ) {
			return new WP_Error( 'google_email_unverified', __( 'Your Google email address is not verified.', 'ohmylms' ) );
		}

		return $body;
	}

	/**
	 * Find the WordPress user matching a Google profile, creating one if needed.
	 *
	 * @param array $profile Google userinfo payload (email, sub, given_name, family_name, name, picture).
	 * @return WP_User|WP_Error
	 */
	public static function find_or_create_user( array $profile ) {
		$email   = sanitize_email( $profile['email'] ?? '' );
		$subject = sanitize_text_field( $profile['sub'] ?? '' );
		if ( ! $subject || empty( $profile['email_verified'] ) ) {
			return new WP_Error( 'google_identity_invalid', __( 'Google did not return a verified identity.', 'ohmylms' ) );
		}

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'google_invalid_email', __( 'Google did not return a valid email address.', 'ohmylms' ) );
		}

		$linked = get_users(
			array(
				'meta_key'   => self::META_GOOGLE_ID,
				'meta_value' => $subject,
				'number'     => 2,
			)
		);
		if ( count( $linked ) > 1 ) {
			return new WP_Error( 'google_identity_conflict', __( 'This Google identity needs administrator review.', 'ohmylms' ) );
		}
		$user = $linked ? $linked[0] : get_user_by( 'email', $email );
		if ( self::$link_user_id && ( ! $user || (int) $user->ID !== self::$link_user_id ) ) {
			return new WP_Error( 'google_link_mismatch', __( 'Choose the Google account with the same email as your signed-in account.', 'ohmylms' ) );
		}
		if ( $user && 'yes' === get_user_meta( $user->ID, '_ohmylms_banned_student', true ) ) {
			return new WP_Error( 'google_account_disabled', __( 'This account is disabled.', 'ohmylms' ) );
		}
		if ( $user && ! $linked ) {
			// An email match alone must never sign in to, overwrite, or reclaim an account.
			if ( ( get_current_user_id() !== (int) $user->ID && self::$link_user_id !== (int) $user->ID ) || get_user_meta( $user->ID, self::META_GOOGLE_ID, true ) ) {
				return new WP_Error( 'google_link_required', __( 'Sign in to your existing account first, then connect Google from the learning portal.', 'ohmylms' ) );
			}
		}

		if ( ! $user ) {
			$user = self::create_user( $email, $profile );
			if ( is_wp_error( $user ) ) {
				return $user;
			}
		}

		if ( ! empty( $profile['sub'] ) ) {
			update_user_meta( $user->ID, self::META_GOOGLE_ID, sanitize_text_field( $profile['sub'] ) );
		}

		return $user;
	}

	/**
	 * Create a new WordPress user (and OhMyLMS student profile picture)
	 * from a Google profile. Only runs for brand-new accounts — never for a
	 * returning user, so this is the only place a Google login writes name,
	 * email, or avatar data.
	 *
	 * @param string $email Verified email address.
	 * @param array  $profile Google userinfo payload.
	 * @return WP_User|WP_Error
	 */
	private static function create_user( $email, array $profile ) {
		$username = self::generate_unique_username( $email );

		$first_name   = sanitize_text_field( $profile['given_name'] ?? '' );
		$last_name    = sanitize_text_field( $profile['family_name'] ?? '' );
		$display_name = sanitize_text_field( $profile['name'] ?? trim( $first_name . ' ' . $last_name ) ) ?: $username;
		$student_role = function_exists( 'ohmylms_get_assignable_student_role' ) ? ohmylms_get_assignable_student_role() : 'subscriber';

		$user_id = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 24, true, true ),
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => $display_name,
				'role'         => $student_role,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		// Google already verified this email address, skip our own verification flow.
		update_user_meta( $user_id, EmailVerificationService::META_VERIFIED, 'yes' );

		if ( ! empty( $profile['picture'] ) && class_exists( '\OhMyLMS\Data\Student' ) ) {
			$student = new \OhMyLMS\Data\Student( $user_id );
			$student->set_profile_image( esc_url_raw( $profile['picture'] ) );
			$student->save();
		}

		return get_user_by( 'id', $user_id );
	}

	/**
	 * Derive a unique WordPress username from an email address.
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	private static function generate_unique_username( $email ) {
		$base = sanitize_user( current( explode( '@', $email ) ), true );
		if ( '' === $base ) {
			$base = 'user';
		}

		$username = $base;
		$suffix   = 1;

		while ( username_exists( $username ) ) {
			$username = $base . $suffix;
			++$suffix;
		}

		return $username;
	}
}
