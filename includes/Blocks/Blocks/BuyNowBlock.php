<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * BuyNowBlock
 *
 * @package OhMyLMS\Blocks\Blocks
 */

namespace OhMyLMS\Blocks\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin buy now block implementation.
 */
class BuyNowBlock {
	/**
	 * Register for Buy now block.
	 */
	public static function register() {
		register_block_type(
			'ohmylms/buy-now',
			array(
				'attributes'      => array(
					'courseId'       => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'btnText'        => array(
						'type'    => 'string',
						'default' => 'Buy Now',
					),
					'background'     => array(
						'type'    => 'string',
						'default' => '#0073aa',
					),
					'color'          => array(
						'type'    => 'string',
						'default' => '#fff',
					),
					'padding'        => array(
						'type'    => 'string',
						'default' => '12px 24px',
					),
					'borderRadius'   => array(
						'type'    => 'string',
						'default' => '4px',
					),
					'fontSize'       => array(
						'type'    => 'string',
						'default' => '16px',
					),
					'textDecoration' => array(
						'type'    => 'string',
						'default' => 'none',
					),
					'lineHeight'     => array(
						'type'    => 'string',
						'default' => '1.5',
					),
					'width'          => array(
						'type'    => 'string',
						'default' => 'auto',
					),
					'maxWidth'       => array(
						'type'    => 'string',
						'default' => '100%',
					),
					'minWidth'       => array(
						'type'    => 'string',
						'default' => '100px',
					),
					'height'         => array(
						'type'    => 'string',
						'default' => 'auto',
					),
					'className'      => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'render_callback' => array( __CLASS__, 'render' ),
			)
		);
	}

	/**
	 * Render for Buy now block.
	 *
	 * @param mixed $attributes Attributes.
	 */
	public static function render( $attributes ) {
		$atts      = array(
			'course_id'       => isset( $attributes['courseId'] ) ? $attributes['courseId'] : '',
			'btn_text'        => isset( $attributes['btnText'] ) ? $attributes['btnText'] : '',
			'background'      => isset( $attributes['background'] ) ? $attributes['background'] : '',
			'color'           => isset( $attributes['color'] ) ? $attributes['color'] : '',
			'padding'         => isset( $attributes['padding'] ) ? $attributes['padding'] : '',
			'border_radius'   => isset( $attributes['borderRadius'] ) ? $attributes['borderRadius'] : '',
			'font_size'       => isset( $attributes['fontSize'] ) ? $attributes['fontSize'] : '',
			'text_decoration' => isset( $attributes['textDecoration'] ) ? $attributes['textDecoration'] : '',
			'line_height'     => isset( $attributes['lineHeight'] ) ? $attributes['lineHeight'] : '',
			'width'           => isset( $attributes['width'] ) ? $attributes['width'] : '',
			'max_width'       => isset( $attributes['maxWidth'] ) ? $attributes['maxWidth'] : '',
			'min_width'       => isset( $attributes['minWidth'] ) ? $attributes['minWidth'] : '',
			'height'          => isset( $attributes['height'] ) ? $attributes['height'] : '',
			'class'           => isset( $attributes['className'] ) ? $attributes['className'] : '',
		);
		$shortcode = '[ohmylms_buy_now';
		foreach ( $atts as $k => $v ) {
			if ( '' !== $v && null !== $v ) {
				$shortcode .= ' ' . $k . '="' . esc_attr( $v ) . '"';
			}
		}
		$shortcode .= ']';
		return do_shortcode( $shortcode );
	}
}
