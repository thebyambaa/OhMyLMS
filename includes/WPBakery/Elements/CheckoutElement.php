<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * WPBakery Checkout Element
 *
 * @package OhMyLMS\WPBakery\Elements
 * @since 1.0.0
 */

namespace OhMyLMS\WPBakery\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * CheckoutElement class
 */
class CheckoutElement {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->register_element();

		// Disable WPBakery shortcode caching for real-time preview updates.
		add_filter( 'vc_shortcode_content_filter_after', array( $this, 'disable_cache_for_preview' ), 10, 2 );
	}

	/**
	 * Disable caching for this shortcode in WPBakery editor
	 *
	 * @param string $output Shortcode output.
	 * @param string $shortcode Shortcode tag.
	 * @return string Modified output.
	 */
	public function disable_cache_for_preview( $output, $shortcode ) {
		if ( 'ohmylms_checkout' === $shortcode ) {
			// Add a timestamp to force cache bypass in editor.
			if ( function_exists( 'vc_is_inline' ) && vc_is_inline() ) {
				$output .= '<!-- vc-no-cache:' . time() . ' -->';
			}
		}
		return $output;
	}

	/**
	 * Register the element with WPBakery
	 *
	 * @return void
	 */
	public function register_element() {
		if ( ! function_exists( 'vc_map' ) ) {
			return;
		}

		vc_map(
			array(
				'name'        => __( 'Checkout', 'ohmylms' ),
				'base'        => 'ohmylms_checkout',
				'icon'        => 'icon-wpb-ohmylms',
				'category'    => __( 'OhMyLMS', 'ohmylms' ),
				'description' => __( 'Display the checkout form', 'ohmylms' ),
				'params'      => array(
					// --- Layout Settings ---
					array(
						'type'        => 'dropdown',
						'heading'     => __( 'Layout Type', 'ohmylms' ),
						'param_name'  => 'layout_type',
						'value'       => array(
							__( 'Use Global Setting', 'ohmylms' ) => '',
							__( 'Default Layout', 'ohmylms' )     => 'default',
							__( 'Canvas Layout', 'ohmylms' )      => 'canvas',
						),
						'description' => __( 'Select the layout type for the checkout page. Canvas removes header and footer.', 'ohmylms' ),
						'group'       => __( 'Layout Settings', 'ohmylms' ),
					),

					// --- Title Style ---
					array(
						'type'       => 'colorpicker',
						'heading'    => __( 'Title Color', 'ohmylms' ),
						'param_name' => 'title_color',
						'group'      => __( 'Title Style', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Title Font Size', 'ohmylms' ),
						'param_name'  => 'title_font_size',
						'group'       => __( 'Title Style', 'ohmylms' ),
						'description' => __( 'e.g., 22px', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Title Font Weight', 'ohmylms' ),
						'param_name'  => 'title_font_weight',
						'group'       => __( 'Title Style', 'ohmylms' ),
						'description' => __( 'e.g., 600', 'ohmylms' ),
					),

					// --- Input Style ---
					array(
						'type'       => 'colorpicker',
						'heading'    => __( 'Input Background', 'ohmylms' ),
						'param_name' => 'input_background_color',
						'group'      => __( 'Input Style', 'ohmylms' ),
					),
					array(
						'type'       => 'colorpicker',
						'heading'    => __( 'Input Text Color', 'ohmylms' ),
						'param_name' => 'input_color',
						'group'      => __( 'Input Style', 'ohmylms' ),
					),
					array(
						'type'       => 'colorpicker',
						'heading'    => __( 'Input Label Color', 'ohmylms' ),
						'param_name' => 'input_label_color',
						'group'      => __( 'Input Style', 'ohmylms' ),
					),
					array(
						'type'       => 'colorpicker',
						'heading'    => __( 'Input Border Color', 'ohmylms' ),
						'param_name' => 'input_border_color',
						'group'      => __( 'Input Style', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Input Border Width', 'ohmylms' ),
						'param_name'  => 'input_border_width',
						'group'       => __( 'Input Style', 'ohmylms' ),
						'description' => __( 'e.g., 1px', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Input Border Radius', 'ohmylms' ),
						'param_name'  => 'input_border_radius',
						'group'       => __( 'Input Style', 'ohmylms' ),
						'description' => __( 'e.g., 10px', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Input Font Size', 'ohmylms' ),
						'param_name'  => 'input_font_size',
						'group'       => __( 'Input Style', 'ohmylms' ),
						'description' => __( 'e.g., 14px', 'ohmylms' ),
					),

					// --- Button Style ---
					array(
						'type'       => 'colorpicker',
						'heading'    => __( 'Button Background', 'ohmylms' ),
						'param_name' => 'button_background_color',
						'group'      => __( 'Button Style', 'ohmylms' ),
					),
					array(
						'type'       => 'colorpicker',
						'heading'    => __( 'Button Text Color', 'ohmylms' ),
						'param_name' => 'button_color',
						'group'      => __( 'Button Style', 'ohmylms' ),
					),
					array(
						'type'       => 'colorpicker',
						'heading'    => __( 'Button Hover Background', 'ohmylms' ),
						'param_name' => 'button_hover_background_color',
						'group'      => __( 'Button Style', 'ohmylms' ),
					),
					array(
						'type'       => 'colorpicker',
						'heading'    => __( 'Button Hover Color', 'ohmylms' ),
						'param_name' => 'button_hover_color',
						'group'      => __( 'Button Style', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Button Font Size', 'ohmylms' ),
						'param_name'  => 'button_font_size',
						'group'       => __( 'Button Style', 'ohmylms' ),
						'description' => __( 'e.g., 18px', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Button Font Weight', 'ohmylms' ),
						'param_name'  => 'button_font_weight',
						'group'       => __( 'Button Style', 'ohmylms' ),
						'description' => __( 'e.g., 700', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Button Border Radius', 'ohmylms' ),
						'param_name'  => 'button_border_radius',
						'group'       => __( 'Button Style', 'ohmylms' ),
						'description' => __( 'e.g., 8px', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Button Padding Top', 'ohmylms' ),
						'param_name'  => 'button_padding_top',
						'group'       => __( 'Button Style', 'ohmylms' ),
						'description' => __( 'e.g., 16px', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Button Padding Right', 'ohmylms' ),
						'param_name'  => 'button_padding_right',
						'group'       => __( 'Button Style', 'ohmylms' ),
						'description' => __( 'e.g., 24px', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Button Padding Bottom', 'ohmylms' ),
						'param_name'  => 'button_padding_bottom',
						'group'       => __( 'Button Style', 'ohmylms' ),
						'description' => __( 'e.g., 16px', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Button Padding Left', 'ohmylms' ),
						'param_name'  => 'button_padding_left',
						'group'       => __( 'Button Style', 'ohmylms' ),
						'description' => __( 'e.g., 24px', 'ohmylms' ),
					),

					// --- Checkout Box Style ---
					array(
						'type'       => 'colorpicker',
						'heading'    => __( 'Checkout Box Background', 'ohmylms' ),
						'param_name' => 'checkout_box_background_color',
						'group'      => __( 'Checkout Box Style', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Checkout Box Padding Top', 'ohmylms' ),
						'param_name'  => 'checkout_box_padding_top',
						'group'       => __( 'Checkout Box Style', 'ohmylms' ),
						'description' => __( 'e.g., 30px', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Checkout Box Padding Right', 'ohmylms' ),
						'param_name'  => 'checkout_box_padding_right',
						'group'       => __( 'Checkout Box Style', 'ohmylms' ),
						'description' => __( 'e.g., 50px', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Checkout Box Padding Bottom', 'ohmylms' ),
						'param_name'  => 'checkout_box_padding_bottom',
						'group'       => __( 'Checkout Box Style', 'ohmylms' ),
						'description' => __( 'e.g., 30px', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Checkout Box Padding Left', 'ohmylms' ),
						'param_name'  => 'checkout_box_padding_left',
						'group'       => __( 'Checkout Box Style', 'ohmylms' ),
						'description' => __( 'e.g., 0', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Checkout Box Border Radius', 'ohmylms' ),
						'param_name'  => 'checkout_box_border_radius',
						'group'       => __( 'Checkout Box Style', 'ohmylms' ),
						'description' => __( 'e.g., 0', 'ohmylms' ),
					),

					// --- Order Summary Style ---
					array(
						'type'       => 'colorpicker',
						'heading'    => __( 'Order Summary Background', 'ohmylms' ),
						'param_name' => 'order_summary_background_color',
						'group'      => __( 'Order Summary Style', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Order Summary Padding Top', 'ohmylms' ),
						'param_name'  => 'order_summary_padding_top',
						'group'       => __( 'Order Summary Style', 'ohmylms' ),
						'description' => __( 'e.g., 30px', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Order Summary Padding Right', 'ohmylms' ),
						'param_name'  => 'order_summary_padding_right',
						'group'       => __( 'Order Summary Style', 'ohmylms' ),
						'description' => __( 'e.g., 0', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Order Summary Padding Bottom', 'ohmylms' ),
						'param_name'  => 'order_summary_padding_bottom',
						'group'       => __( 'Order Summary Style', 'ohmylms' ),
						'description' => __( 'e.g., 30px', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Order Summary Padding Left', 'ohmylms' ),
						'param_name'  => 'order_summary_padding_left',
						'group'       => __( 'Order Summary Style', 'ohmylms' ),
						'description' => __( 'e.g., 30px', 'ohmylms' ),
					),

					// --- Privacy Text Style ---
					array(
						'type'       => 'colorpicker',
						'heading'    => __( 'Privacy Text Color', 'ohmylms' ),
						'param_name' => 'privacy_text_color',
						'group'      => __( 'Privacy Style', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Privacy Text Font Size', 'ohmylms' ),
						'param_name'  => 'privacy_text_font_size',
						'group'       => __( 'Privacy Style', 'ohmylms' ),
						'description' => __( 'e.g., 14px', 'ohmylms' ),
					),

					// Advanced.
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Extra CSS Class', 'ohmylms' ),
						'param_name'  => 'class',
						'group'       => __( 'Advanced', 'ohmylms' ),
						'description' => __( 'Add custom CSS class for styling', 'ohmylms' ),
					),
				),
			)
		);
	}
}
