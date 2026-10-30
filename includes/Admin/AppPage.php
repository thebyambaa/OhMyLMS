<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Admin page that hosts the typed React admin app.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Admin;

use OhMyLMS\Assets\AdminAppAssets;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the admin app page and prints the element the app mounts into (see docs/ARCHITECTURE.md).
 *
 * The page is registered below the OhMyLMS menu but has no menu entry of its own: the OhMyLMS menu opens
 * it, and the legacy page (`admin.php?page=ohmylms`) redirects to it. See docs/CUTOVER.md.
 */
final class AppPage {

	/**
	 * Admin page slug (`admin.php?page=ohmylms-app`).
	 */
	const SLUG = 'ohmylms-app';

	/**
	 * Capability required to open the page and to receive the app's scripts.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Id of the element the app mounts into. Keep in sync with assets/src/app/main.tsx.
	 */
	const MOUNT_ID = 'ohmylms-app-root';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 20 );
		add_action( 'admin_head', array( __CLASS__, 'hide_menu_entry' ) );
		add_action( 'admin_enqueue_scripts', array( LegacyRoutes::class, 'enqueue_landing' ), 1 );
		add_filter( 'removable_query_args', array( LegacyRoutes::class, 'removable_query_args' ) );
	}

	/**
	 * Add the page below the OhMyLMS top-level menu, and the redirect of the legacy page (OHMYLMS_SLUG is
	 * defined after this class is initialised).
	 */
	public static function register_menu() {
		if ( ! has_action( 'load-toplevel_page_' . OHMYLMS_SLUG, array( LegacyRoutes::class, 'redirect_legacy_page' ) ) ) {
			add_action( 'load-toplevel_page_' . OHMYLMS_SLUG, array( LegacyRoutes::class, 'redirect_legacy_page' ) );
		}
		add_submenu_page(
			OHMYLMS_SLUG,
			__( 'OhMyLMS', 'ohmylms' ),
			__( 'OhMyLMS', 'ohmylms' ),
			self::CAPABILITY,
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Drop the page's own menu entry; the OhMyLMS menu entries open the app.
	 *
	 * Runs on `admin_head`, after WordPress resolved the page hook from the menu: removing the entry on
	 * `admin_menu` would make `admin.php?page=ohmylms-app` unloadable.
	 */
	public static function hide_menu_entry() {
		remove_submenu_page( OHMYLMS_SLUG, self::SLUG );
	}

	/**
	 * Whether a hook suffix (as passed to admin_enqueue_scripts) is this page.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return bool
	 */
	public static function is_app_screen( $hook_suffix ) {
		$suffix = '_page_' . self::SLUG;
		return is_string( $hook_suffix ) && substr( $hook_suffix, -strlen( $suffix ) ) === $suffix;
	}

	/**
	 * Print the page: a landmark-free wrapper and the mount element. The app renders its own landmarks.
	 */
	public static function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'ohmylms' ), '', array( 'response' => 403 ) );
		}
		?>
		<div class="wrap ohmylms-app-wrap">
			<?php if ( null === AdminAppAssets::locate() ) : ?>
				<div class="notice notice-error">
					<p>
						<?php
						echo esc_html__( 'The OhMyLMS admin app has not been built yet. Run "npm run build:sdk" in the plugin directory.', 'ohmylms' );
						?>
					</p>
				</div>
			<?php else : ?>
				<?php self::render_unavailable_notice(); ?>
				<div id="<?php echo esc_attr( self::MOUNT_ID ); ?>" class="ohmylms-app">
					<noscript>
						<p><?php echo esc_html__( 'OhMyLMS needs JavaScript to be enabled.', 'ohmylms' ); ?></p>
					</noscript>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Hidden notice for a legacy address the new app cannot open. Only the browser knows the address (it
	 * is in the `#` fragment), so assets/js/legacy-redirects.js fills in the route and shows it.
	 */
	private static function render_unavailable_notice() {
		if ( ! LegacyRoutes::from_legacy() ) {
			return;
		}
		?>
		<div id="<?php echo esc_attr( LegacyRoutes::NOTICE_ID ); ?>" class="notice notice-warning inline" role="status" hidden>
			<p>
				<?php echo esc_html__( 'This screen is not available in OhMyLMS:', 'ohmylms' ); ?>
				<code data-ohmylms-legacy-route></code>
			</p>
			<?php $unavailable = LegacyRoutes::unavailable(); ?>
			<?php if ( $unavailable ) : ?>
				<p>
					<?php echo esc_html__( 'Not available:', 'ohmylms' ); ?>
					<?php echo esc_html( implode( ', ', array_values( $unavailable ) ) ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}
}
