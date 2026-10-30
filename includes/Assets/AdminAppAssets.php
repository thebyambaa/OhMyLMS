<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Scripts, styles and bootstrap data for the typed React admin app.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Assets;

use OhMyLMS\Admin\AppPage;
use OhMyLMS\Assessment\Interactive;
use OhMyLMS\Assessment\MathLive;
use OhMyLMS\Design\Ui;
use OhMyLMS\Extensions\Registry;
use OhMyLMS\Extensions\Settings;

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
	 * Extension host handle, passed to `ohmylms_enqueue_extension_scripts` on this page.
	 */
	const EXTENSIONS_HANDLE = 'ohmylms-app-extensions';

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
		wp_enqueue_script( 'wp-format-library' );
		foreach ( array( 'wp-block-editor', 'wp-block-library', 'wp-edit-blocks', 'wp-format-library' ) as $block_style ) {
			wp_enqueue_style( $block_style );
		}
		foreach ( array( Ui::STYLE_HANDLE, 'ohmylms-admin-design-system' ) as $handle ) {
			if ( wp_style_is( $handle, 'registered' ) ) {
				$style_deps[] = $handle;
			}
		}
		if ( is_file( $build['dir'] . '/admin-app.css' ) ) {
			wp_enqueue_style( self::HANDLE, $build['url'] . '/admin-app.css', $style_deps, $build['asset']['version'] );
		}

		$host         = self::enqueue_extension_host( $build );
		$dependencies = array_merge( $build['asset']['dependencies'], $host );
		wp_enqueue_script( self::HANDLE, $build['url'] . '/admin-app.js', array_values( array_unique( $dependencies ) ), $build['asset']['version'], true );
		self::enqueue_question_editor_runtime( $host ? self::EXTENSIONS_HANDLE : self::HANDLE );
		wp_set_script_translations( self::HANDLE, 'ohmylms', OHMYLMS_DIR . '/languages' );
		wp_add_inline_script(
			self::HANDLE,
			'window.ohmylmsApp = ' . wp_json_encode( self::bootstrap_data(), JSON_HEX_TAG | JSON_HEX_AMP ) . ';',
			'before'
		);
	}

	/**
	 * Load the extension host (`window.ohmylms.extensions`) and the scripts extensions add for it.
	 *
	 * The host loads first, then everything queued on `ohmylms_enqueue_extension_scripts`, then the app, so
	 * extensions register before the app renders (docs/EXTENSIONS.md).
	 *
	 * @param array $build Located build, from {@see self::locate()}.
	 * @return string[] Handles the app must load after; empty when the host is not built.
	 */
	private static function enqueue_extension_host( array $build ) {
		$asset_file = $build['dir'] . '/admin-app-extensions.asset.php';
		if ( ! is_file( $asset_file ) || ! is_file( $build['dir'] . '/admin-app-extensions.js' ) ) {
			return array();
		}
		$asset = require $asset_file;
		// Extension editors may open the media library.
		wp_enqueue_media();
		wp_enqueue_script( self::EXTENSIONS_HANDLE, $build['url'] . '/admin-app-extensions.js', $asset['dependencies'], $asset['version'], true );
		wp_localize_script( self::EXTENSIONS_HANDLE, 'ohmylmsExtensionManifest', array_merge( Registry::manifest(), array( 'settings' => Settings::manifest() ) ) );
		$scripts = wp_scripts();
		$before  = $scripts->queue;
		do_action( 'ohmylms_enqueue_extension_scripts', self::EXTENSIONS_HANDLE );
		return array_merge( array( self::EXTENSIONS_HANDLE ), array_values( array_diff( $scripts->queue, $before ) ) );
	}

	/**
	 * What the built-in question editors need at runtime, as on the existing admin's screen: the
	 * learners' interactive widgets for previews (`window.OhMyLMSInteractive`, see
	 * {@see \OhMyLMS\Assessment\Interactive::enqueue()}), the media library, the math field styles, and
	 * the math configuration (`window.ohmylmsMath`) that the math input loader reads before it loads
	 * the runtime on demand.
	 *
	 * @param string $handle Script that carries `window.ohmylmsMath`: the extension host when it is
	 *                       built (so extension scripts can read it too), otherwise the app.
	 */
	private static function enqueue_question_editor_runtime( $handle ) {
		wp_enqueue_media();
		Interactive::enqueue();
		MathLive::enqueue_style();
		wp_localize_script( $handle, 'ohmylmsMath', MathLive::config() );
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
