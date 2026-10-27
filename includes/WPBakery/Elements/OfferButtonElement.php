<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * WPBakery Offer Button Element
 *
 * @package OhMyLMS\WPBakery\Elements
 * @since 1.0.0
 */

namespace OhMyLMS\WPBakery\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * OfferButtonElement class
 */
class OfferButtonElement {

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
		if ( 'ohmylms_offer_button' === $shortcode ) {
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
				'name'        => __( 'Offer Button', 'ohmylms' ),
				'base'        => 'ohmylms_offer_button',
				'icon'        => 'icon-wpb-ohmylms',
				'category'    => __( 'OhMyLMS', 'ohmylms' ),
				'description' => __( 'Add an offer accept/decline button for funnels', 'ohmylms' ),
				'params'      => array(
					// Action.
					array(
						'type'        => 'dropdown',
						'heading'     => __( 'Action', 'ohmylms' ),
						'param_name'  => 'action',
						'value'       => array(
							__( 'Accept Offer', 'ohmylms' )  => 'accept',
							__( 'Decline Offer', 'ohmylms' ) => 'decline',
						),
						'std'         => 'accept',
						'description' => __( 'Button action type', 'ohmylms' ),
						'admin_label' => true,
					),
					// Button Text.
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Button Text', 'ohmylms' ),
						'param_name'  => 'text',
						'value'       => __( 'Accept Offer', 'ohmylms' ),
						'description' => __( 'Text to display on the button', 'ohmylms' ),
					),

					// Style Group.
					array(
						'type'        => 'colorpicker',
						'heading'     => __( 'Background Color', 'ohmylms' ),
						'param_name'  => 'background',
						'value'       => '#0073aa',
						'description' => __( 'Button background color', 'ohmylms' ),
						'group'       => __( 'Style', 'ohmylms' ),
					),
					array(
						'type'        => 'colorpicker',
						'heading'     => __( 'Text Color', 'ohmylms' ),
						'param_name'  => 'color',
						'value'       => '#fff',
						'description' => __( 'Button text color', 'ohmylms' ),
						'group'       => __( 'Style', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Border', 'ohmylms' ),
						'param_name'  => 'border',
						'value'       => '',
						'description' => __( 'CSS border property (e.g., 1px solid #000)', 'ohmylms' ),
						'group'       => __( 'Style', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Padding', 'ohmylms' ),
						'param_name'  => 'padding',
						'value'       => '12px 24px',
						'description' => __( 'Button padding (e.g., 12px 24px)', 'ohmylms' ),
						'group'       => __( 'Style', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Margin', 'ohmylms' ),
						'param_name'  => 'margin',
						'value'       => '',
						'description' => __( 'Button margin (e.g., 10px 0)', 'ohmylms' ),
						'group'       => __( 'Style', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Font Size', 'ohmylms' ),
						'param_name'  => 'font_size',
						'value'       => '16px',
						'description' => __( 'Button text font size', 'ohmylms' ),
						'group'       => __( 'Style', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Font Weight', 'ohmylms' ),
						'param_name'  => 'font_weight',
						'value'       => '',
						'description' => __( 'Font weight (e.g., 400, 600, bold)', 'ohmylms' ),
						'group'       => __( 'Style', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Border Radius', 'ohmylms' ),
						'param_name'  => 'border_radius',
						'value'       => '4px',
						'description' => __( 'Border radius for rounded corners', 'ohmylms' ),
						'group'       => __( 'Style', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Width', 'ohmylms' ),
						'param_name'  => 'width',
						'value'       => '',
						'description' => __( 'Button width (e.g., 200px, 100%, auto)', 'ohmylms' ),
						'group'       => __( 'Style', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Height', 'ohmylms' ),
						'param_name'  => 'height',
						'value'       => '',
						'description' => __( 'Button height (e.g., 50px)', 'ohmylms' ),
						'group'       => __( 'Style', 'ohmylms' ),
					),

					// Advanced Group.
					array(
						'type'        => 'textfield',
						'heading'     => __( 'CSS ID', 'ohmylms' ),
						'param_name'  => 'id',
						'value'       => '',
						'description' => __( 'Unique CSS ID for the button', 'ohmylms' ),
						'group'       => __( 'Advanced', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Extra CSS Class', 'ohmylms' ),
						'param_name'  => 'class',
						'value'       => '',
						'description' => __( 'Add custom CSS class for styling', 'ohmylms' ),
						'group'       => __( 'Advanced', 'ohmylms' ),
					),
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Inline CSS Styles', 'ohmylms' ),
						'param_name'  => 'style',
						'value'       => '',
						'description' => __( 'Additional inline CSS styles', 'ohmylms' ),
						'group'       => __( 'Advanced', 'ohmylms' ),
					),
				),
			)
		);
	}
}
