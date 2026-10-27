<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * OhMyLMS Offer Button Block (PHP registration)
 *
 * @package OhMyLMS\Blocks\Blocks
 */

namespace OhMyLMS\Blocks\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin offer button block implementation.
 */
class OfferButtonBlock {
	/**
	 * Register for Offer button block.
	 */
	public static function register() {
		register_block_type(
			'ohmylms/offer-button',
			array(
				'attributes'      => array(
					'action'        => array(
						'type'    => 'string',
						'default' => 'accept',
					),
					'text'          => array(
						'type'    => 'string',
						'default' => 'Accept Offer',
					),
					'background'    => array(
						'type'    => 'string',
						'default' => '#0073aa',
					),
					'color'         => array(
						'type'    => 'string',
						'default' => '#fff',
					),
					'border'        => array(
						'type'    => 'string',
						'default' => '',
					),
					'padding'       => array(
						'type'    => 'string',
						'default' => '12px 24px',
					),
					'margin'        => array(
						'type'    => 'string',
						'default' => '',
					),
					'font_size'     => array(
						'type'    => 'string',
						'default' => '16px',
					),
					'font_weight'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'border_radius' => array(
						'type'    => 'string',
						'default' => '4px',
					),
					'width'         => array(
						'type'    => 'string',
						'default' => '',
					),
					'height'        => array(
						'type'    => 'string',
						'default' => '',
					),
					'class'         => array(
						'type'    => 'string',
						'default' => '',
					),
					'id'            => array(
						'type'    => 'string',
						'default' => '',
					),
					'style'         => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'render_callback' => array( __CLASS__, 'render' ),
				'editor_script'   => 'ohmylms-blocks-editor',
				'editor_style'    => 'ohmylms-blocks-editor',
				'style'           => 'ohmylms-blocks-frontend',
			)
		);
	}

	/**
	 * Render for Offer button block.
	 *
	 * @param mixed $atts Atts.
	 */
	public static function render( $atts ) {
		// Use the pro shortcode handler for output.
		if ( class_exists( '\OhMyLMS\Shortcodes\ShortCodeOfferButton' ) ) {
			ob_start();
			\OhMyLMS\Shortcodes\ShortCodeOfferButton::output( $atts );
			return ob_get_clean();
		}
		return '<div class="ohmylms-offer-button-missing">Offer Button not available.</div>';
	}
}

// Register on init.
add_action( 'init', array( '\OhMyLMS\Blocks\Blocks\OfferButtonBlock', 'register' ) );
