<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Source asset overrides and the extension host of frontend slots.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Extensions;

use OhMyLMS\Assessment\MathLive;
use OhMyLMS\Assets\AdminAppAssets;

/** Source asset overrides and the extension host of frontend slots. */
final class SourceAssets {
	/** Script handle of the extension host (`window.ohmylms.extensions`), shared with the admin app. */
	const HOST_HANDLE = AdminAppAssets::EXTENSIONS_HANDLE;

	/** Script handle of the signup form's React fields (entry `admin-app-registration`). */
	const REGISTRATION_HANDLE = 'ohmylms-app-registration';

	/** Register asset hooks for the LMS application. */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_host' ), 11 );
		if ( ! defined( 'OHMYLMS_SOURCE_ASSETS' ) || ! OHMYLMS_SOURCE_ASSETS ) {
			return; }
		// The source-build signup form marks its fields for the registration form.
		add_action( 'ohmylms_signup_form_start', array( __CLASS__, 'enqueue_registration' ) );
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
	 * Directory of the webpack entries: build/sdk for source builds, the published assets otherwise.
	 *
	 * @return string Path relative to the plugin directory.
	 */
	private static function directory() {
		return defined( 'OHMYLMS_SOURCE_ASSETS' ) && OHMYLMS_SOURCE_ASSETS ? 'build/sdk' : 'assets/dist/admin';
	}

	/**
	 * Register a webpack entry of the authored app without loading it.
	 *
	 * @param string $handle Script handle.
	 * @param string $entry  Entry name, such as `admin-app-extensions`.
	 * @return bool Whether the entry is built.
	 */
	private static function register_entry( $handle, $entry ) {
		if ( wp_script_is( $handle, 'registered' ) ) {
			return true;
		}
		$directory  = self::directory();
		$asset_file = OHMYLMS_DIR . '/' . $directory . '/' . $entry . '.asset.php';
		if ( ! is_file( $asset_file ) || ! is_file( OHMYLMS_DIR . '/' . $directory . '/' . $entry . '.js' ) ) {
			return false;
		}
		$asset = require $asset_file;
		return wp_register_script( $handle, plugins_url( $directory . '/' . $entry . '.js', OHMYLMS_FILE ), $asset['dependencies'], $asset['version'], true );
	}

	/**
	 * Register the extension host (entry `admin-app-extensions`) without loading it.
	 *
	 * @return bool Whether the host is available.
	 */
	public static function register_host() {
		return self::register_entry( self::HOST_HANDLE, 'admin-app-extensions' );
	}

	/**
	 * Load the signup form's React fields, which replace the fields marked `[data-ohmylms-registration-fields]`.
	 * Hooked on `ohmylms_signup_form_start`, so only pages showing the form load them.
	 */
	public static function enqueue_registration() {
		if ( self::register_entry( self::REGISTRATION_HANDLE, 'admin-app-registration' ) ) {
			wp_enqueue_script( self::REGISTRATION_HANDLE );
			wp_set_script_translations( self::REGISTRATION_HANDLE, 'ohmylms', OHMYLMS_DIR . '/languages' );
		}
	}

	/**
	 * Load the extension host for frontend slots, with the scripts extensions add through
	 * `ohmylms_enqueue_extension_scripts`.
	 *
	 * Runs once per request. Frontend slots call this while rendering; the host prints in the footer and
	 * mounts every `[data-ohmylms-slot]` element once the page has loaded. The admin app loads the host
	 * itself ({@see AdminAppAssets}) and native block editors load their own bundle.
	 */
	public static function enqueue() {
		static $enqueued = false;
		if ( $enqueued || ! self::register_host() ) {
			return;
		}
		$enqueued = true;
		wp_enqueue_script( self::HOST_HANDLE );
		wp_localize_script( self::HOST_HANDLE, 'ohmylmsExtensionManifest', array_merge( Registry::manifest(), array( 'settings' => Settings::manifest() ) ) );
		wp_localize_script( self::HOST_HANDLE, 'ohmylmsMath', MathLive::config() );
		do_action( 'ohmylms_enqueue_extension_scripts', self::HOST_HANDLE );
	}
}
