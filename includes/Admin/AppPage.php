<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * The retired admin app page (`admin.php?page=ohmylms-app`), kept as a redirect.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * The admin app is mounted by the six OhMyLMS pages ({@see AdminNavigation}). Its former page stays
 * registered, without a menu entry, so old `admin.php?page=ohmylms-app#/route` links keep working forever:
 * the server sends them to the Dashboard ({@see LegacyRoutes::redirect_app_page()}), the browser keeps the
 * hash, and the Dashboard opens the route's new address. See docs/LEGACY-ROUTES.md.
 */
final class AppPage {

	/**
	 * Slug of the retired page (`admin.php?page=ohmylms-app`).
	 */
	const SLUG = 'ohmylms-app';

	/**
	 * Capability required to open the OhMyLMS pages and to receive the app's scripts.
	 */
	const CAPABILITY = AdminNavigation::CAPABILITY;

	/**
	 * Id of the element the app mounts into.
	 */
	const MOUNT_ID = AdminNavigation::MOUNT_ID;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( LegacyRoutes::class, 'enqueue_landing' ), 1 );
		add_filter( 'removable_query_args', array( LegacyRoutes::class, 'removable_query_args' ) );
	}

	/**
	 * Register the retired page without a menu entry; loading it redirects before anything is printed.
	 */
	public static function register_menu() {
		$hook = add_submenu_page( 'options.php', __( 'OhMyLMS', 'ohmylms' ), __( 'OhMyLMS', 'ohmylms' ), self::CAPABILITY, self::SLUG, '__return_null' );
		if ( $hook && ! has_action( 'load-' . $hook, array( LegacyRoutes::class, 'redirect_app_page' ) ) ) {
			add_action( 'load-' . $hook, array( LegacyRoutes::class, 'redirect_app_page' ) );
		}
	}

	/**
	 * Whether a hook suffix (as passed to admin_enqueue_scripts) is one of the pages that mount the app.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return bool
	 */
	public static function is_app_screen( $hook_suffix ) {
		return AdminNavigation::is_screen( $hook_suffix );
	}
}
