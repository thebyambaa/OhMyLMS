<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class The7.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\ThemeSupport;

defined( 'ABSPATH' ) || exit;

/**
 * Class The7
 *
 * Handles the theme support for The7 theme.
 */
class The7 {
	/**
	 * Init for The7.
	 */
	public function init() {
		add_filter( 'ohmylms_enqueue_styles', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Enqueue styles for The7.
	 *
	 * @param mixed $styles Styles.
	 */
	public function enqueue_styles( $styles ) {
		$styles['ohmylms-general'] = array(
			'src'     => OHMYLMS_ASSETS_URL . ( '/theme-support/theme-the7.css' ),
			'deps'    => array(),
			'version' => OHMYLMS_VERSION,
			'media'   => 'all',
			'has_rtl' => true,
		);
		return is_array( $styles ) ? array_filter( $styles ) : array();
	}
}
