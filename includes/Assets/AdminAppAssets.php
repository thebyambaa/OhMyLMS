<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Scripts, styles and bootstrap data for the typed React admin app.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Assets;

use OhMyLMS\Admin\AdminNavigation;
use OhMyLMS\Assessment\Interactive;
use OhMyLMS\Assessment\MathLive;
use OhMyLMS\Design\Ui;
use OhMyLMS\Extensions\Registry;
use OhMyLMS\Extensions\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the built `admin-app` entry (one bundle) on the six OhMyLMS admin pages, except on sections the
 * server renders itself.
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
	 * What each admin page needs besides the app. Pages that author courses, lessons, quizzes and
	 * questions get the authoring runtime; pages with image pickers get the media library; the
	 * dashboard, People and Add-ons need neither. Extension tabs always get the authoring runtime.
	 */
	const RUNTIME_BY_GROUP = array(
		'content'    => 'authoring',
		'bank'       => 'authoring',
		'curriculum' => 'authoring',
		'engagement' => 'media',
		'commerce'   => 'media',
		'settings'   => 'media',
	);

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
		if ( ! AdminNavigation::shows_app( $hook_suffix ) || ! current_user_can( AdminNavigation::hook_capability( $hook_suffix ) ) ) {
			return;
		}
		$build = self::locate();
		if ( null === $build ) {
			return;
		}

		$style_deps = array( 'wp-components' );
		if ( 'authoring' === self::runtime() ) {
			// The lesson editor's blocks.
			wp_enqueue_script( 'wp-format-library' );
			foreach ( array( 'wp-block-editor', 'wp-block-library', 'wp-edit-blocks', 'wp-format-library' ) as $block_style ) {
				wp_enqueue_style( $block_style );
			}
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
		self::preload();
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
		if ( '' !== self::runtime() ) {
			wp_enqueue_media();
		}
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
		$needs = self::runtime();
		if ( '' !== $needs ) {
			wp_enqueue_media();
		}
		if ( 'authoring' === $needs ) {
			Interactive::enqueue();
			MathLive::enqueue_style();
		}
		wp_localize_script( $handle, 'ohmylmsMath', MathLive::config() );
	}

	/**
	 * Answer the page's first REST requests while the page is built.
	 *
	 * The responses are handed to `wp.apiFetch` as preloaded data, the way the block editor does it,
	 * so the app's first GET of each path is served from the page instead of a request of its own.
	 * A path the app does not ask for is ignored, and one that is refused is not preloaded.
	 */
	private static function preload() {
		$place = AdminNavigation::current();

		/**
		 * Filters the REST paths whose responses are embedded in an admin app page.
		 *
		 * @param string[] $paths REST paths with their query strings.
		 * @param array    $place Current group, page, tab and section.
		 */
		$paths = array_values( array_unique( array_filter( (array) apply_filters( 'ohmylms_admin_app_preload_paths', 'dashboard' === $place['group'] ? self::dashboard_paths() : array(), $place ), 'is_string' ) ) );
		if ( ! $paths ) {
			return;
		}
		$preloaded = array_reduce( $paths, 'rest_preload_api_request', array() );
		if ( $preloaded ) {
			wp_add_inline_script( 'wp-api-fetch', sprintf( 'wp.apiFetch.use( wp.apiFetch.createPreloadingMiddleware( %s ) );', wp_json_encode( $preloaded, JSON_HEX_TAG | JSON_HEX_AMP ) ), 'after' );
		}
	}

	/**
	 * First requests of the dashboard: the viewer's scopes, then every widget for the scope and
	 * period the address names, or the defaults the app would choose.
	 *
	 * @return string[]
	 */
	private static function dashboard_paths() {
		$base   = '/' . OHMYLMS_API_URL . '/dashboard';
		$scopes = rest_do_request( new \WP_REST_Request( 'GET', $base . '/scopes' ) );
		$data   = $scopes->is_error() ? array() : (array) $scopes->get_data();
		$ids    = array_map( 'strval', array_column( (array) ( $data['scopes'] ?? array() ), 'id' ) );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only view parameters, checked against the allowed values.
		$scope  = isset( $_GET['scope'] ) && is_string( $_GET['scope'] ) ? sanitize_text_field( wp_unslash( $_GET['scope'] ) ) : '';
		$period = isset( $_GET['period'] ) && is_string( $_GET['period'] ) ? absint( $_GET['period'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $scope, $ids, true ) ) {
			$scope = isset( $data['default'] ) ? (string) $data['default'] : '';
		}
		$period = in_array( $period, array( 7, 30, 90 ), true ) ? $period : 30;
		$paths  = array( $base . '/scopes', $base . '?range=last_30_days' );
		if ( '' !== $scope ) {
			foreach ( array_keys( \OhMyLMS\Dashboard\Widgets::all() ) as $widget ) {
				$paths[] = $base . '/widgets/' . rawurlencode( $widget ) . '?' . http_build_query(
					array(
						'scope'  => $scope,
						'period' => $period,
					)
				);
			}
		}
		return $paths;
	}

	/**
	 * What the current admin page loads besides the app.
	 *
	 * @return string 'authoring' for the lesson and question editors' runtime (block editor,
	 *                interactive widgets, graph library, math styles, media library), 'media' for
	 *                the media library alone, or '' for neither.
	 */
	public static function runtime() {
		$place = AdminNavigation::current();
		$needs = preg_match( AdminNavigation::EXTENSION_TAB, $place['tab'] ) ? 'authoring' : ( self::RUNTIME_BY_GROUP[ $place['group'] ] ?? '' );

		/**
		 * Filters what an admin page loads besides the app: 'authoring', 'media' or ''.
		 *
		 * @param string $needs Runtime the page loads.
		 * @param array  $place Current group, page, tab and section.
		 */
		$needs = (string) apply_filters( 'ohmylms_admin_app_runtime', $needs, $place );
		return in_array( $needs, array( 'authoring', 'media' ), true ) ? $needs : '';
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
