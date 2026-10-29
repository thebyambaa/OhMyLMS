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
 * Registers the "OhMyLMS (new)" submenu page and prints the element the app mounts into.
 *
 * The page is developed beside the recovered admin application (see docs/ARCHITECTURE.md). It does not
 * change the existing menu; it only appends one submenu item for administrators.
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
	}

	/**
	 * Add the submenu page below the OhMyLMS top-level menu.
	 */
	public static function register_menu() {
		add_submenu_page(
			OHMYLMS_SLUG,
			__( 'OhMyLMS (new)', 'ohmylms' ),
			__( 'OhMyLMS (new)', 'ohmylms' ),
			self::CAPABILITY,
			self::SLUG,
			array( __CLASS__, 'render' )
		);
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
				<div id="<?php echo esc_attr( self::MOUNT_ID ); ?>" class="ohmylms-app">
					<noscript>
						<p><?php echo esc_html__( 'OhMyLMS needs JavaScript to be enabled.', 'ohmylms' ); ?></p>
					</noscript>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
