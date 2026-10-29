<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Scripts, styles and bootstrap data for the typed React admin app.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Assets;

use OhMyLMS\Admin\AppPage;
use OhMyLMS\Design\Ui;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the built `admin-app` entry on its own admin page only.
 */
final class AdminAppAssets {

	/**
	 * Script and style handle.
	 */
	const HANDLE = 'ohmylms-admin-app';

	/**
	 * Register hooks. Runs after the design system stylesheet (priority 30) so it can depend on it.
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 40 );
	}

	/**
	 * Find the built entry.
	 *
	 * Source builds (OHMYLMS_SOURCE_ASSETS) load build/sdk, like the other webpack entries; otherwise the
	 * synced assets/dist/admin directory is used.
	 *
	 * @return array{url:string,dir:string,asset:array{dependencies:array,version:string}}|null Null when not built.
	 */
	public static function locate() {
		$directory  = defined( 'OHMYLMS_SOURCE_ASSETS' ) && OHMYLMS_SOURCE_ASSETS ? 'build/sdk' : 'assets/dist/admin';
		$asset_file = OHMYLMS_DIR . '/' . $directory . '/admin-app.asset.php';
		if ( ! is_file( $asset_file ) || ! is_file( OHMYLMS_DIR . '/' . $directory . '/admin-app.js' ) ) {
			return null;
		}
		$asset = require $asset_file;
		if ( ! is_array( $asset ) || ! isset( $asset['dependencies'], $asset['version'] ) ) {
			return null;
		}
		return array(
			'url'   => plugins_url( $directory, OHMYLMS_FILE ),
			'dir'   => OHMYLMS_DIR . '/' . $directory,
			'asset' => $asset,
		);
	}

	/**
	 * Enqueue the app's script, stylesheet and bootstrap data.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public static function enqueue( $hook_suffix ) {
		if ( ! AppPage::is_app_screen( $hook_suffix ) || ! current_user_can( AppPage::CAPABILITY ) ) {
			return;
		}
		$build = self::locate();
		if ( null === $build ) {
			return;
		}

		$style_deps = array( 'wp-components' );
		foreach ( array( Ui::STYLE_HANDLE, 'ohmylms-admin-design-system' ) as $handle ) {
			if ( wp_style_is( $handle, 'registered' ) ) {
				$style_deps[] = $handle;
			}
		}
		if ( is_file( $build['dir'] . '/admin-app.css' ) ) {
			wp_enqueue_style( self::HANDLE, $build['url'] . '/admin-app.css', $style_deps, $build['asset']['version'] );
		}

		wp_enqueue_script( self::HANDLE, $build['url'] . '/admin-app.js', $build['asset']['dependencies'], $build['asset']['version'], true );
		wp_set_script_translations( self::HANDLE, 'ohmylms', OHMYLMS_DIR . '/languages' );
		wp_add_inline_script(
			self::HANDLE,
			'window.ohmylmsApp = ' . wp_json_encode( self::bootstrap_data(), JSON_HEX_TAG | JSON_HEX_AMP ) . ';',
			'before'
		);
	}

	/**
	 * Data the app reads at startup (window.ohmylmsApp). Keep in sync with assets/src/app/bootstrap/config.ts.
	 *
	 * The REST nonce authenticates requests through the wp-api-fetch nonce middleware, which the app registers.
	 *
	 * @return array<string,mixed>
	 */
	public static function bootstrap_data() {
		/**
		 * Capabilities the app may check on the client to hide navigation. The server still authorises every request.
		 *
		 * @param string[] $capabilities Capability names.
		 */
		$names        = apply_filters( 'ohmylms_app_capabilities', array( 'manage_options', 'edit_posts' ) );
		$capabilities = array();
		foreach ( (array) $names as $name ) {
			if ( is_string( $name ) && '' !== $name ) {
				$capabilities[ $name ] = current_user_can( $name );
			}
		}

		return array(
			'restRoot'  => esc_url_raw( rest_url() ),
			'restNonce' => wp_create_nonce( 'wp_rest' ),
			'locale'    => determine_locale(),
			'adminUrl'  => esc_url_raw( admin_url() ),
			'version'   => OHMYLMS_VERSION,
			'user'      => array(
				'id'           => get_current_user_id(),
				'capabilities' => (object) $capabilities,
			),
		);
	}
}
