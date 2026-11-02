<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Assets;

use OhMyLMS\Abstracts\Assets;

/**
 * Plugin admin assets implementation: the admin menu stylesheet on every screen and the WordPress
 * packages the plugin's PHP-rendered screens use. The admin app loads its own assets ({@see AdminAppAssets}).
 */
class AdminAssets extends Assets {

	/**
	 * Init for Admin assets.
	 */
	public function init() {
		add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );
	}

	/**
	 * Get styles for Admin assets.
	 */
	public function get_styles() {
		return array(
			'ohmylms-global' => array(
				'src'     => self::get_asset_url( 'assets/css/global.css' ),
				'deps'    => '',
				'version' => OHMYLMS_VERSION,
				'media'   => 'all',
				'has_rtl' => false,
				'screens' => array( 'all' ),
			),
		);
	}

	/**
	 * Load scripts for Admin assets.
	 */
	public function load_scripts() {
		$this->register_styles();

		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';
		if ( \OhMyLMS\Design\DesignSystem::is_plugin_screen() ) {
			wp_enqueue_script( 'wp-element' );
			wp_enqueue_script( 'wp-components' );
			wp_enqueue_script( 'wp-data' );
		}

		foreach ( $this->get_styles() as $handle => $style ) {
			if ( $this->should_enqueue( $style, $screen_id ) ) {
				wp_enqueue_style( $handle );
			}
		}
	}
}


( new AdminAssets() )->init();
