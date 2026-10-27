<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Load the plugin-owned packages.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS;

/** Load packages after other plugins are available. */
class Packages {

	/**
	 * Package directories and entrypoint classes.
	 *
	 * @var array
	 */
	protected static $packages = array(
		'e-commerce' => 'OhMyLMS_Commerce_Package',
	);

	/**
	 * Register the package-loading hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'ohmylms_init', array( __CLASS__, 'on_init' ) );
	}

	/**
	 * Wait for other plugins before loading commerce.
	 *
	 * @return void
	 */
	public static function on_init(): void {
		// Delay package loading for compatibility with WooCommerce and other plugins.
		if ( did_action( 'plugins_loaded' ) ) {
			self::load_packages();
		} else {
			add_action( 'plugins_loaded', array( __CLASS__, 'load_packages' ), 20 );
		}
	}

	/**
	 * Check whether a package directory exists.
	 *
	 * @param string $package Package directory name.
	 * @return bool
	 */
	public static function package_exists( $package ): bool {
		return file_exists( OHMYLMS_DIR . '/packages/' . $package );
	}

	/**
	 * Load each package using its established entrypoint filename.
	 *
	 * @return void
	 */
	public static function load_packages(): void {
		foreach ( self::$packages as $package_name => $package_class ) {
			if ( ! self::package_exists( $package_name ) ) {
				continue;
			}
			if ( file_exists( OHMYLMS_DIR . '/packages/' . $package_name . '/Package.php' ) ) {
				require_once OHMYLMS_DIR . '/packages/' . $package_name . '/Package.php';
				call_user_func( array( $package_class, 'init' ) );
			}
		}
	}
}


Packages::init();
