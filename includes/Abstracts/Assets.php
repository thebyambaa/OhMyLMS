<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Abstract class for managing assets.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Abstracts;

defined( 'ABSPATH' ) || exit();

/**
 * Abstract class for managing assets.
 *
 * This class provides methods for registering, enqueuing, and localizing
 * scripts and styles in a WordPress environment.
 *
 * @package OhMyLMS\Abstracts
 * @since 1.0.0
 */
abstract class Assets {

	/**
	 * Scripts.
	 *
	 * @var mixed
	 */
	public $scripts = array();

	/**
	 * Styles.
	 *
	 * @var mixed
	 */
	public $styles = array();

	/**
	 * Localized scripts.
	 *
	 * @var mixed
	 */
	public $localized_scripts = array();

	/**
	 * Initialize the assets.
	 */
	abstract public function init();

	/**
	 * Load and enqueue scripts and styles.
	 */
	abstract public function load_scripts();

	/**
	 * Get the styles to be enqueued.
	 *
	 * @return array Filtered array of styles to be enqueued.
	 *
	 * @since 1.0.0
	 */
	public function get_styles() {
		$styles = array();
		return is_array( $styles ) ? array_filter( $styles ) : array();
	}

	/**
	 * Get the scripts to be enqueued.
	 *
	 * @return array Filtered array of scripts to be enqueued.
	 *
	 * @since 1.0.0
	 */
	public function get_scripts() {
		$scripts = array();
		return is_array( $scripts ) ? array_filter( $scripts ) : array();
	}

	/**
	 * Get the localized data for a specific script handle.
	 *
	 * @param string $handle The script handle.
	 * @return mixed The localized data.
	 *
	 * @since 1.0.0
	 */
	public function get_script_data( $handle ) {}

	/**
	 * Get the URL of an asset.
	 *
	 * @param string $path The relative path to the asset.
	 * @return string The full URL to the asset.
	 *
	 * @since 1.0.0
	 */
	protected function get_asset_url( $path ) {
		return plugins_url( $path, OHMYLMS_FILE );
	}

	/**
	 * Register a script.
	 *
	 * @param string $handle Name of the script.
	 * @param string $path Full URL of the script.
	 * @param array  $deps An array of registered script handles this script depends on. Default 'jquery'.
	 * @param string $version String specifying the script version number. Default OHMYLMS_VERSION.
	 * @param array  $in_footer Optional. Whether to enqueue the script before </body> instead of in the <head>. Default array('strategy' => 'defer').
	 *
	 * @since 1.0.0
	 */
	public function register_script( $handle, $path, $deps = array( 'jquery' ), $version = OHMYLMS_VERSION, $in_footer = array( 'strategy' => 'defer' ) ) {
		$this->scripts[] = $handle;
		wp_register_script( $handle, $path, $deps, $version, $in_footer );
	}

	/**
	 * Register a style.
	 *
	 * @param string $handle Name of the stylesheet.
	 * @param string $path Full URL of the stylesheet.
	 * @param array  $deps An array of registered stylesheet handles this stylesheet depends on.
	 * @param string $version String specifying the stylesheet version number.
	 * @param string $media Optional. The media for which this stylesheet has been defined. Default 'all'.
	 * @param bool   $has_rtl Optional. Whether the stylesheet has an RTL version. Default false.
	 *
	 * @since 1.0.0
	 */
	public function register_style( $handle, $path, $deps = array(), $version = OHMYLMS_VERSION, $media = 'all', $has_rtl = false ) {
		$this->styles[] = $handle;
		wp_register_style( $handle, $path, $deps, $version, $media );
		if ( $has_rtl ) {
			wp_style_add_data( $handle, 'rtl', 'replace' );
		}
	}

	/**
	 * Register all scripts defined in the `get_scripts` method.
	 *
	 * @since 1.0.0
	 */
	public function register_scripts() {
		$register_scripts = $this->get_scripts();
		foreach ( $register_scripts as $name => $props ) {
			$this->register_script( $name, $props['src'], $props['deps'], $props['version'], $props['in_footer'] );
		}
	}

	/**
	 * Register all styles defined in the `get_styles` method.
	 *
	 * @since 1.0.0
	 */
	public function register_styles() {
		$register_styles = $this->get_styles();
		foreach ( $register_styles as $name => $props ) {
			$this->register_style( $name, $props['src'], $props['deps'], $props['version'], 'all', $props['has_rtl'] );
		}
	}

	/**
	 * Localize a script with data for a specific handle.
	 *
	 * @param string $handle The script handle to be localized.
	 *
	 * @since 1.0.0
	 */
	public function localize_script( $handle ) {
		if ( ! in_array( $handle, $this->localized_scripts, true ) && wp_script_is( $handle, 'enqueued' ) ) {
			$data = $this->get_script_data( $handle );

			if ( ! $data ) {
				return;
			}

			$name = str_replace( '-', '_', $handle ) . '_params';
			wp_localize_script( $handle, $name, apply_filters( $name, $data ) );
		}
	}

	/**
	 * Localize script mrm for Assets.
	 *
	 * @param mixed $handle Handle.
	 */
	public function localize_script_MRM( $handle ) {
		if ( ! in_array( $handle, $this->localized_scripts, true ) && wp_script_is( $handle ) ) {
			$data = $this->get_script_data( $handle );

			if ( ! $data ) {
				return;
			}

			$name = 'MRM_Vars';
			wp_localize_script( $handle, $name, apply_filters( $name, $data ) );
		}
	}

	/**
	 * Localize scripts only when enqueued.
	 */
	public function localize_printed_scripts() {
		foreach ( $this->scripts as $handle ) {
			$this->localize_script( $handle );
		}

		$this->localize_script_MRM( 'ohmylms' );
	}

	/**
	 * Determine if a script should be enqueued based on the screen ID.
	 *
	 * @param array  $script The script properties.
	 * @param string $screen_id The current screen ID.
	 * @return bool True if the script should be enqueued, false otherwise.
	 *
	 * @since 1.0.0
	 */
	public function should_enqueue( $script, $screen_id ): bool {
		$should_load = false;
		if ( in_array( 'all', $script['screens'], true ) ) {
			$should_load = true;
		} elseif ( ! empty( $script['screens'] ) ) {
			$should_load = in_array( $screen_id, $script['screens'] );
		} else {
			$should_load = true;
		}
		return $should_load;
	}
}
