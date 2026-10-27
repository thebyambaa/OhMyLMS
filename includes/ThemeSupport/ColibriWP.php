<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class ColibriWP.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\ThemeSupport;

defined( 'ABSPATH' ) || exit;

/**
 * Class ColibriWP
 *
 * Handles the theme support for ColibriWP theme.
 */
class ColibriWP {
	/**
	 * Init for Colibri wp.
	 */
	public function init() {
		add_filter( 'ohmylms_enqueue_styles', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Enqueue styles for Colibri wp.
	 *
	 * @param mixed $styles Styles.
	 */
	public function enqueue_styles( $styles ) {
		$styles['ohmylms-general'] = array(
			'src'     => OHMYLMS_ASSETS_URL . ( '/theme-support/theme-colibriwp.css' ),
			'deps'    => array(),
			'version' => OHMYLMS_VERSION,
			'media'   => 'all',
			'has_rtl' => true,
		);
		return is_array( $styles ) ? array_filter( $styles ) : array();
	}
}
