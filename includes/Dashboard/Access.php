<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Who may open the dashboard.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard;

use WP_Error;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * The `ohmylms_view_dashboard` meta capability and the wp-admin opening for school staff.
 *
 * - Site administrators hold it through `manage_options`.
 * - School administrators and teachers hold it (as `read`) while they have at least one scope
 *   ({@see ScopeResolver::available()}); everyone else is denied.
 *
 * Holding it never widens what a user sees: every dashboard endpoint resolves the user's scopes again.
 */
final class Access {

	/**
	 * Meta capability of the Dashboard page and its endpoints.
	 */
	const CAPABILITY = 'ohmylms_view_dashboard';

	/**
	 * Capability of site administrators (every scope, commerce figures).
	 */
	const SITE_CAPABILITY = 'manage_options';

	/**
	 * Whether a user is a site administrator.
	 *
	 * @param int $user_id User.
	 * @return bool
	 */
	public static function is_site_admin( $user_id ) {
		return $user_id > 0 && user_can( (int) $user_id, self::SITE_CAPABILITY );
	}

	/**
	 * Whether a user may open the dashboard.
	 *
	 * @param int $user_id User.
	 * @return bool
	 */
	public static function can_view( $user_id ) {
		return $user_id > 0 && user_can( (int) $user_id, self::CAPABILITY );
	}

	/**
	 * Map the meta capability (`map_meta_cap`).
	 *
	 * @param string[] $caps    Primitive capabilities.
	 * @param string   $cap     Requested capability.
	 * @param int      $user_id User.
	 * @return string[]
	 */
	public static function map_meta_cap( $caps, $cap, $user_id ) {
		if ( self::CAPABILITY !== $cap ) {
			return $caps;
		}
		if ( self::is_site_admin( (int) $user_id ) ) {
			return array( self::SITE_CAPABILITY );
		}
		return ScopeResolver::available( (int) $user_id ) ? array( 'read' ) : array( 'do_not_allow' );
	}

	/**
	 * Expose the capability to the admin app (`ohmylms_app_capabilities`).
	 *
	 * @param string[] $names Capability names.
	 * @return string[]
	 */
	public static function app_capabilities( $names ) {
		$names   = (array) $names;
		$names[] = self::CAPABILITY;
		return array_values( array_unique( $names ) );
	}

	/**
	 * Keep the Dashboard page (`admin.php?page=ohmylms`), and OhMyLMS pages with a staff tab they can see,
	 * open to school staff, who are otherwise kept out of wp-admin (`ohmylms_hide_admin_for_user`). Every
	 * other admin screen stays closed to them.
	 *
	 * @param bool    $hub_only Whether the user is kept out of wp-admin.
	 * @param WP_User $user     User.
	 * @return bool
	 */
	public static function keep_dashboard_open( $hub_only, $user ) {
		global $pagenow;
		if ( ! $hub_only || ! $user instanceof WP_User || ! is_admin() || wp_doing_ajax() || 'admin.php' !== $pagenow ) {
			return $hub_only;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation only.
		$page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( OHMYLMS_SLUG === $page ) {
			return ! self::can_view( (int) $user->ID );
		}
		// Other OhMyLMS pages open when one of their staff tabs (for example the Gradebook) is visible.
		$group = \OhMyLMS\Admin\AdminNavigation::group_of_page( $page );
		if ( null === $group || get_current_user_id() !== (int) $user->ID ) {
			return $hub_only;
		}
		return ! \OhMyLMS\Admin\AdminNavigation::can_open( $group );
	}

	/**
	 * Error for a user without dashboard access.
	 *
	 * @param int $user_id User (0 when signed out).
	 * @return WP_Error 401 when signed out, 403 otherwise.
	 */
	public static function denied( $user_id ) {
		return new WP_Error(
			'ohmylms_dashboard_cannot_view',
			__( 'Sorry, you are not allowed to view the dashboard.', 'ohmylms' ),
			array( 'status' => $user_id > 0 ? 403 : 401 )
		);
	}
}
