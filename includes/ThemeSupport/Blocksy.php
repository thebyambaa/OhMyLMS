<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class Blocksy.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\ThemeSupport;

defined( 'ABSPATH' ) || exit;

/**
 * Class Blocksy
 *
 * Handles the theme support for Blocksy theme.
 */
class Blocksy {


	/**
	 * Init for Blocksy.
	 */
	public function init() {
		add_filter( 'ohmylms_enqueue_styles', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Enqueue styles for Blocksy.
	 *
	 * @param mixed $styles Styles.
	 */
	public function enqueue_styles( $styles ) {
		$styles['ohmylms-general'] = array(
			'src'     => OHMYLMS_ASSETS_URL . ( '/theme-support/theme-blocksy.css' ),
			'deps'    => array(),
			'version' => OHMYLMS_VERSION,
			'media'   => 'all',
			'has_rtl' => true,
		);
		return is_array( $styles ) ? array_filter( $styles ) : array();
	}
}
