<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class Avada.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\ThemeSupport;

defined( 'ABSPATH' ) || exit;

/**
 * Class Avada
 *
 * Handles the theme support for Avada theme.
 */
class Avada {
	/**
	 * Init for Avada.
	 */
	public function init() {
		add_filter( 'ohmylms_enqueue_styles', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Enqueue styles for Avada.
	 *
	 * @param mixed $styles Styles.
	 */
	public function enqueue_styles( $styles ) {
		$styles['ohmylms-general'] = array(
			'src'     => OHMYLMS_ASSETS_URL . ( '/theme-support/theme-avada.css' ),
			'deps'    => array(),
			'version' => OHMYLMS_VERSION,
			'media'   => 'all',
			'has_rtl' => true,
		);
		return is_array( $styles ) ? array_filter( $styles ) : array();
	}
}
