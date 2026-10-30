<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Source asset overrides and the SDK used by the shipped admin application.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Extensions;

/** Source asset overrides and the SDK used by the shipped admin application. */
final class SourceAssets {
	/** Script handle of the extension SDK. */
	const HANDLE = 'ohmylms-extension-sdk';

	/** Register asset hooks for the LMS application. */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin' ), 11 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ), 11 );
		if ( ! defined( 'OHMYLMS_SOURCE_ASSETS' ) || ! OHMYLMS_SOURCE_ASSETS ) {
			return; }
		// The source-build signup form marks its fields for the SDK's registration form.
		add_action( 'ohmylms_signup_form_start', array( __CLASS__, 'enqueue' ) );
		add_filter( 'script_loader_src', array( __CLASS__, 'url' ), 20 );
		add_filter( 'style_loader_src', array( __CLASS__, 'url' ), 20 );
	}
	/**
	 * Resolve source assets when the source build is enabled.
	 *
	 * @param string $url Published asset URL.
	 * @return string Resolved URL.
	 */
	public static function url( $url ) {
		$base = plugins_url( '/', OHMYLMS_FILE );
		if ( strpos( $url, $base . 'assets/' ) !== 0 ) {
			return $url; }
		$relative = strtok( substr( $url, strlen( $base ) ), '?' );
		$file     = OHMYLMS_DIR . '/build/' . $relative;
		if ( ! is_file( $file ) ) {
			return $url; }
		return $base . 'build/' . $relative . '?ver=' . substr( hash_file( 'sha256', $file ), 0, 12 );
	}

	/**
	 * Register the SDK without loading it, so data and dependants can attach to the handle on any screen.
	 *
	 * @return bool Whether the SDK is available.
	 */
	public static function register() {
		if ( wp_script_is( self::HANDLE, 'registered' ) ) {
			return true;
		}
		$directory  = defined( 'OHMYLMS_SOURCE_ASSETS' ) && OHMYLMS_SOURCE_ASSETS ? 'build/sdk' : 'assets/dist/admin';
		$asset_file = OHMYLMS_DIR . '/' . $directory . '/extensions.asset.php';
		if ( ! is_file( $asset_file ) ) {
			return false;
		}
		$asset = require $asset_file;
		return wp_register_script( self::HANDLE, plugins_url( $directory . '/extensions.js', OHMYLMS_FILE ), $asset['dependencies'], $asset['version'], true );
	}

	/**
	 * Load the SDK on the recovered LMS application only.
	 *
	 * The authored preview owns its bootstrap and native block editors load their own bundle, so
	 * neither receives this SDK; other wp-admin screens have no extension surfaces.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public static function admin( $hook_suffix ) {
		self::register();
		if ( 'toplevel_page_' . OHMYLMS_SLUG === $hook_suffix ) {
			self::enqueue();
		}
	}

	/**
	 * Load the SDK, its data and the scripts extensions add through `ohmylms_enqueue_extension_scripts`.
	 *
	 * Runs once per request. Frontend slots call this while rendering; the SDK prints in the footer.
	 */
	public static function enqueue() {
		static $enqueued = false;
		if ( $enqueued || ! self::register() ) {
			return;
		}
		$enqueued = true;
		wp_enqueue_script( self::HANDLE );
		wp_localize_script( self::HANDLE, 'ohmylmsTabPreferences', TabPreferences::read() );
		wp_localize_script( self::HANDLE, 'ohmylmsPlayerTemplates', \OhMyLMS\Quiz\PlayerTemplates::choices() );
		wp_localize_script( self::HANDLE, 'ohmylmsExtensionManifest', array_merge( Registry::manifest(), array( 'settings' => Settings::manifest() ) ) );
		wp_localize_script(
			self::HANDLE,
			'ohmylmsAssessment',
			array(
				'versioned'     => \OhMyLMS\Assessment\Engine::versioned(),
				'bankUi'        => \OhMyLMS\Assessment\Engine::bank_ui(),
				'skills'        => true,
				'practice'      => \OhMyLMS\Assessment\Engine::practice(),
				'formAuthoring' => defined( 'OHMYLMS_FORM_AUTHORING' ) && OHMYLMS_FORM_AUTHORING,
				'isAdmin'       => current_user_can( 'manage_options' ),
			)
		);
		$scripts = wp_scripts();
		if ( isset( $scripts->registered['ohmylms-vendor'] ) ) {
			$scripts->registered['ohmylms-vendor']->deps = array_values( array_unique( array_merge( $scripts->registered['ohmylms-vendor']->deps, array( 'wp-preferences', 'wp-keyboard-shortcuts' ) ) ) );
		}
		$before = $scripts->queue;
		do_action( 'ohmylms_enqueue_extension_scripts', self::HANDLE );
		if ( isset( $scripts->registered['ohmylms'] ) ) {
			$scripts->registered['ohmylms']->deps = array_values( array_unique( array_merge( $scripts->registered['ohmylms']->deps, array( self::HANDLE ), array_diff( $scripts->queue, $before ) ) ) );
		}
	}
}
