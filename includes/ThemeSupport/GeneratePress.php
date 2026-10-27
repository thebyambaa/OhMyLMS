<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class GeneratePress.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\ThemeSupport;

defined( 'ABSPATH' ) || exit;

/**
 * Class GeneratePress
 *
 * Handles the theme support for GeneratePress theme.
 */
class GeneratePress {


	/**
	 * Init for Generate press.
	 */
	public function init() {
		add_filter( 'ohmylms_enqueue_styles', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Enqueue styles for Generate press.
	 *
	 * @param mixed $styles Styles.
	 */
	public function enqueue_styles( $styles ) {
		$styles['ohmylms-general'] = array(
			'src'     => OHMYLMS_ASSETS_URL . ( '/theme-support/theme-generatepress.css' ),
			'deps'    => array(),
			'version' => OHMYLMS_VERSION,
			'media'   => 'all',
			'has_rtl' => true,
		);
		return is_array( $styles ) ? array_filter( $styles ) : array();
	}
}
