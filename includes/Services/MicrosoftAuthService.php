<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Microsoft Auth Service
 *
 * Handles the "Sign in with Microsoft" OAuth 2.0 / OpenID Connect
 * authorization-code flow: building the consent URL, exchanging the code for
 * tokens, fetching the user's profile, and resolving/creating the matching
 * WordPress user. It mirrors GoogleAuthService, with the differences that
 * matter for Microsoft identities:
 *
 * - The directory (tenant) is configurable: "common", "organizations",
 *   "consumers", a tenant ID or a verified domain.
 * - Microsoft does not return a trusted "email_verified" claim, so an email
 *   address from Microsoft never proves ownership. It is never used to sign in
 *   to or take over an existing account, and a new account created from it is
 *   left to the normal email verification flow.
 *
 * @package OhMyLMS\Services
 * @since 1.0.0
 */

namespace OhMyLMS\Services;

use OhMyLMS\Integrations\MicrosoftSignIn\Hooks as MicrosoftSignInHooks;
use WP_Error;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class MicrosoftAuthService
 */
class MicrosoftAuthService {
	/**
	 * User ID of the signed-in user who started a "connect" flow, or 0.
	 *
	 * @var int
	 */
	private static $link_user_id = 0;

	const OPTION_KEY         = 'ohmylms_microsoft_oauth';
	const STATE_TRANSIENT    = 'ohmylms_lms_microsoft_state_';
	const STATE_COOKIE       = 'ohmylms_microsoft_state';
	const STATE_TTL          = 600; // 10 minutes.
	const META_MICROSOFT_ID  = '_ohmylms_microsoft_id';
	const DEFAULT_TENANT     = 'common';
	const AUTHORIZATION_PATH = '/oauth2/v2.0/authorize';
	const TOKEN_PATH         = '/oauth2/v2.0/token';
	const LOGIN_HOST         = 'https://login.microsoftonline.com/';
	const USERINFO_URL       = 'https://graph.microsoft.com/oidc/userinfo';

	/**
	 * Get the stored Microsoft OAuth settings.
	 *
	 * "enabled" is not stored here: it is the add-on's on/off toggle on the
	 * Add-ons page (see MicrosoftSignInHooks::is_enabled()).
	 *
	 * @return array{enabled: bool, client_id: string, client_secret: string, tenant: string}
	 */
	public static function get_settings() {
		$settings = get_option( self::OPTION_KEY, array() );

		$settings = wp_parse_args(
			is_array( $settings ) ? $settings : array(),
			array(
				'client_id'     => '',
				'client_secret' => '',
				'tenant'        => self::DEFAULT_TENANT,
			)
		);

		$settings['tenant']  = self::sanitize_tenant( $settings['tenant'] );
		$settings['enabled'] = MicrosoftSignInHooks::is_enabled();

		return $settings;
	}

	/**
	 * Normalize a tenant value; anything that is not a valid tenant segment
	 * falls back to "common" so it can never alter the URL path.
	 *
	 * @param mixed $tenant Raw tenant value.
	 * @return string
	 */
	public static function sanitize_tenant( $tenant ) {
		$tenant = is_string( $tenant ) ? trim( $tenant ) : '';

		return preg_match( '/^[A-Za-z0-9][A-Za-z0-9.-]{0,252}$/', $tenant ) ? $tenant : self::DEFAULT_TENANT;
	}

	/**
	 * Whether the add-on is turned on and has the credentials required to run.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		$settings = self::get_settings();
		return ! empty( $settings['enabled'] ) && ! empty( $settings['client_id'] ) && ! empty( $settings['client_secret'] );
	}

	/**
	 * The redirect URI to register in the Microsoft Entra app.
	 *
	 * @return string
	 */
	public static function get_redirect_uri() {
		return rest_url( 'ohmylms/v1/auth/microsoft/callback' );
	}

	/**
	 * Build the Microsoft consent URL and remember a one-time state value to
	 * protect the callback against CSRF. The page the visitor started from
	 * rides along in the same transient.
	 *
	 * @param string $redirect_to Same-site URL to return to after login, or ''.
	 * @return string|WP_Error
	 */
	public static function build_authorization_url( $redirect_to = '' ) {
		$settings = self::get_settings();

		if ( ! self::is_configured() ) {
			return new WP_Error( 'microsoft_not_configured', __( 'Microsoft Sign-In is not configured.', 'ohmylms' ) );
		}

		$state = wp_generate_password( 32, false );
		// Bind this authorization attempt to the browser that started it.
		setcookie(
			self::STATE_COOKIE,
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
			'response_mode' => 'query',
			'scope'         => 'openid email profile',
			'state'         => $state,
			'prompt'        => 'select_account',
		);

		return add_query_arg( $args, self::LOGIN_HOST . rawurlencode( $settings['tenant'] ) . self::AUTHORIZATION_PATH );
	}

	/**
	 * Validate and consume a state value returned by Microsoft.
	 *
	 * @param string $state State parameter from the callback request.
	 * @return string|false The redirect_to URL stashed alongside the state
	 *                       ('' if none was given), or false if the state is
	 *                       missing/expired/already used.
	 */
	public static function consume_state( $state ) {
		self::$link_user_id = 0;
		$cookie             = isset( $_COOKIE[ self::STATE_COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::STATE_COOKIE ] ) ) : '';
		if ( empty( $state ) || '' === $cookie || ! hash_equals( $cookie, (string) $state ) ) {
			return false;
		}

		$key   = self::STATE_TRANSIENT . $state;
		$value = get_transient( $key );
		delete_transient( $key );
		setcookie(
			self::STATE_COOKIE,
			'',
			array(
				'expires'  => time() - HOUR_IN_SECONDS,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		if ( ! is_array( $value ) ) {
			return false;
		}

		$link_user = (int) ( $value['link_user_id'] ?? 0 );
		if ( $link_user && (int) wp_validate_auth_cookie( '', 'logged_in' ) !== $link_user ) {
			return false;
		}
		self::$link_user_id = $link_user;

		return (string) ( $value['redirect_to'] ?? '' );
	}

	/**
	 * Exchange an authorization code for an access token.
	 *
	 * @param string $code Authorization code from Microsoft.
	 * @return array|WP_Error Token response array, or WP_Error on failure.
	 */
	public static function exchange_code_for_token( $code ) {
		$settings = self::get_settings();

		$response = wp_remote_post(
			self::LOGIN_HOST . rawurlencode( $settings['tenant'] ) . self::TOKEN_PATH,
			array(
				'timeout' => 15,
				'body'    => array(
					'code'          => $code,
					'client_id'     => $settings['client_id'],
					'client_secret' => $settings['client_secret'],
					'redirect_uri'  => self::get_redirect_uri(),
					'grant_type'    => 'authorization_code',
					'scope'         => 'openid email profile',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== wp_remote_retrieve_response_code( $response ) || empty( $body['access_token'] ) ) {
			return new WP_Error( 'microsoft_token_exchange_failed', __( 'Could not verify your Microsoft account. Please try again.', 'ohmylms' ) );
		}

		return $body;
	}

	/**
	 * Fetch the authenticated user's profile from Microsoft.
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

		if ( 200 !== wp_remote_retrieve_response_code( $response ) || ! is_array( $body ) || empty( $body['sub'] ) ) {
			return new WP_Error( 'microsoft_userinfo_failed', __( 'Could not retrieve your Microsoft profile. Please try again.', 'ohmylms' ) );
		}

		return $body;
	}

	/**
	 * Find the WordPress user matching a Microsoft profile, creating one if needed.
	 *
	 * @param array $profile Microsoft userinfo payload (sub, email, given_name, family_name, name).
	 * @return WP_User|WP_Error
	 */
	public static function find_or_create_user( array $profile ) {
		$email   = sanitize_email( $profile['email'] ?? '' );
		$subject = sanitize_text_field( $profile['sub'] ?? '' );
		if ( ! $subject ) {
			return new WP_Error( 'microsoft_identity_invalid', __( 'Microsoft did not return an identity.', 'ohmylms' ) );
		}

		$linked = get_users(
			array(
				'meta_key'   => self::META_MICROSOFT_ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Exact identity lookup.
				'meta_value' => $subject, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Exact identity lookup.
				'number'     => 2,
			)
		);
		if ( count( $linked ) > 1 ) {
			return new WP_Error( 'microsoft_identity_conflict', __( 'This Microsoft identity needs administrator review.', 'ohmylms' ) );
		}

		if ( $linked ) {
			$user = $linked[0];
			if ( self::$link_user_id && (int) $user->ID !== self::$link_user_id ) {
				return new WP_Error( 'microsoft_link_mismatch', __( 'This Microsoft account is already connected to another user.', 'ohmylms' ) );
			}
		} elseif ( self::$link_user_id ) {
			// Connecting from a signed-in session: attach to that user only.
			$user = get_userdata( self::$link_user_id );
			if ( ! $user || get_user_meta( $user->ID, self::META_MICROSOFT_ID, true ) ) {
				return new WP_Error( 'microsoft_link_mismatch', __( 'Choose the Microsoft account you want to connect.', 'ohmylms' ) );
			}
		} else {
			if ( ! is_email( $email ) ) {
				return new WP_Error( 'microsoft_invalid_email', __( 'Microsoft did not return a valid email address.', 'ohmylms' ) );
			}
			// An unverified email must never sign in to, or take over, an existing account.
			if ( email_exists( $email ) ) {
				return new WP_Error( 'microsoft_link_required', __( 'Sign in to your existing account first, then connect Microsoft from the learning portal.', 'ohmylms' ) );
			}
			$user = self::create_user( $email, $profile );
			if ( is_wp_error( $user ) ) {
				return $user;
			}
		}

		if ( 'yes' === get_user_meta( $user->ID, '_ohmylms_banned_student', true ) ) {
			return new WP_Error( 'microsoft_account_disabled', __( 'This account is disabled.', 'ohmylms' ) );
		}

		update_user_meta( $user->ID, self::META_MICROSOFT_ID, $subject );

		return $user;
	}

	/**
	 * Create a new WordPress user from a Microsoft profile. Only runs for
	 * brand-new accounts. The email is not marked verified, because Microsoft
	 * does not vouch for it.
	 *
	 * @param string $email   Email address returned by Microsoft.
	 * @param array  $profile Microsoft userinfo payload.
	 * @return WP_User|WP_Error
	 */
	private static function create_user( $email, array $profile ) {
		$username = self::generate_unique_username( $email );

		$first_name   = sanitize_text_field( $profile['given_name'] ?? '' );
		$last_name    = sanitize_text_field( $profile['family_name'] ?? '' );
		$display_name = sanitize_text_field( $profile['name'] ?? trim( $first_name . ' ' . $last_name ) );
		$display_name = '' !== $display_name ? $display_name : $username;
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

		if ( EmailVerificationService::is_required() ) {
			EmailVerificationService::generate_and_send( $user_id );
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
