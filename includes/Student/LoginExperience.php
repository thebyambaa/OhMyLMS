<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping.
/**
 * Sign-in experience for learning-hub users.
 *
 * Learners, parents, teachers and school administrators only ever use the hub.
 * After signing in they land in their workspace, never in the WordPress
 * dashboard, and the admin bar and wp-admin are closed to them. Users who can
 * edit content or manage the site (administrators, instructors) are untouched.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use WP_User;

defined( 'ABSPATH' ) || exit;

/** Hub sign-in redirects and wp-admin gating. */
final class LoginExperience {

	/** Query flag set on the hub sign-in page after a failed attempt. */
	const FAILED_FLAG = 'signin';

	/** Hidden field marking a sign-in that started on the hub form. */
	const FIELD = 'oml_hub_login';

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 99, 3 );
		add_filter( 'show_admin_bar', array( __CLASS__, 'show_admin_bar' ), 99 );
		// On init (this class registers during init at priority 10), because WordPress refuses plugin admin pages with a bare error before admin_init runs.
		add_action( 'init', array( __CLASS__, 'close_admin' ), 11 );
		add_action( 'wp_login_failed', array( __CLASS__, 'login_failed' ), 10, 1 );
	}

	/**
	 * Whether a user belongs to the hub only. Users who can edit content or
	 * manage the site are never treated as hub-only.
	 *
	 * @param WP_User|int|null $user User; the current user when omitted.
	 * @return bool
	 */
	public static function is_hub_user( $user = null ) {
		$user = $user instanceof WP_User ? $user : ( $user ? get_userdata( (int) $user ) : wp_get_current_user() );
		if ( ! $user || ! $user->exists() ) {
			return false;
		}
		$hub_only = ! user_can( $user, 'edit_posts' ) && ! user_can( $user, 'manage_options' ) && ! user_can( $user, 'upload_files' );
		/**
		 * Filters whether a user is kept out of wp-admin and the admin bar.
		 *
		 * @param bool    $hub_only Whether the user is hub-only.
		 * @param WP_User $user     The user.
		 */
		return (bool) apply_filters( 'ohmylms_hide_admin_for_user', $hub_only, $user );
	}

	/**
	 * The workspace a hub user starts in.
	 *
	 * @param WP_User $user User who signed in.
	 * @return string Absolute URL.
	 */
	public static function workspace_url( WP_User $user ) {
		if ( ! Hub::base_url() ) {
			$page = function_exists( 'ohmylms_get_page_permalink' ) ? ohmylms_get_page_permalink( 'profile' ) : '';
			return $page ? $page : home_url( '/' );
		}
		$roles = (array) $user->roles;
		if ( in_array( 'ohmylms_school_admin', $roles, true ) ) {
			return SchoolWorkspace::url();
		}
		if ( in_array( 'ohmylms_teacher', $roles, true ) ) {
			return TeacherWorkspace::url();
		}
		if ( in_array( 'ohmylms_parent', $roles, true ) ) {
			return ParentWorkspace::url();
		}
		return Hub::url();
	}

	/**
	 * Where to send a user after a social sign-in that has no stored return page.
	 *
	 * @param WP_User $user     User who signed in.
	 * @param string  $fallback URL for users who are not hub-only.
	 * @return string
	 */
	public static function landing_url( WP_User $user, $fallback ) {
		return self::is_hub_user( $user ) ? self::workspace_url( $user ) : $fallback;
	}

	/**
	 * Send hub users to their workspace. A requested page is kept when it is a
	 * front-end URL; anything inside wp-admin is replaced.
	 *
	 * @param string            $redirect_to           Destination WordPress chose.
	 * @param string            $requested_redirect_to Destination the form asked for.
	 * @param WP_User|\WP_Error $user                 Signed-in user, or an error.
	 * @return string
	 */
	public static function login_redirect( $redirect_to, $requested_redirect_to, $user ) {
		if ( ! $user instanceof WP_User || ! self::is_hub_user( $user ) ) {
			return $redirect_to;
		}
		$requested = is_string( $requested_redirect_to ) ? $requested_redirect_to : '';
		if ( '' !== $requested && ! self::is_admin_url( $requested ) ) {
			return $requested;
		}
		return self::workspace_url( $user );
	}

	/**
	 * Whether a URL points into wp-admin or the login screen.
	 *
	 * @param string $url URL to test.
	 * @return bool
	 */
	private static function is_admin_url( $url ) {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		return false !== strpos( $path, '/wp-admin' ) || false !== strpos( $path, 'wp-login.php' );
	}

	/**
	 * Hide the admin bar from hub users.
	 *
	 * @param bool $show Whether WordPress would show it.
	 * @return bool
	 */
	public static function show_admin_bar( $show ) {
		return self::is_hub_user() ? false : $show;
	}

	/**
	 * Keep hub users out of wp-admin. AJAX and form handlers stay open because
	 * the hub posts to admin-post.php.
	 *
	 * @return void
	 */
	public static function close_admin() {
		global $pagenow;
		if ( ! is_admin() ) {
			return;
		}
		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || in_array( $pagenow, array( 'admin-post.php', 'admin-ajax.php' ), true ) ) {
			return;
		}
		if ( ! is_user_logged_in() || ! self::is_hub_user() ) {
			return;
		}
		wp_safe_redirect( self::workspace_url( wp_get_current_user() ) );
		exit;
	}

	/**
	 * Return to the hub form, not wp-login.php, when a hub sign-in fails.
	 *
	 * @return void
	 */
	public static function login_failed() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Sign-in is not a nonce-protected action; this only chooses where to show the error.
		$origin = isset( $_POST[ self::FIELD ] ) && is_string( $_POST[ self::FIELD ] ) ? esc_url_raw( wp_unslash( $_POST[ self::FIELD ] ) ) : '';
		$origin = $origin ? wp_validate_redirect( $origin, '' ) : '';
		if ( '' === $origin ) {
			return;
		}
		wp_safe_redirect( add_query_arg( self::FAILED_FLAG, 'failed', $origin ) );
		exit;
	}
}
