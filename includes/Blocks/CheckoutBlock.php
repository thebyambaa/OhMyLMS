<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Checkout Block
 *
 * Gutenberg block for OhMyLMS checkout functionality
 *
 * @package OhMyLMS\Blocks
 * @since 1.0.0
 */

namespace OhMyLMS\Blocks;

use OhMyLMS\Shortcodes\ShortCodeCheckout;
use function OhMyLMS\Commerce\ecommerce;

defined( 'ABSPATH' ) || exit;

/**
 * CheckoutBlock class
 */
class CheckoutBlock {

	/**
	 * Block name
	 *
	 * @var string
	 */
	const BLOCK_NAME = 'ohmylms/checkout';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->register_block();
	}

	/**
	 * Register the block
	 *
	 * @return void
	 */
	private function register_block() {
		register_block_type(
			self::BLOCK_NAME,
			array(
				'attributes'      => $this->get_block_attributes(),
				'render_callback' => array( $this, 'render_block' ),
				'editor_script'   => 'ohmylms-blocks-editor',
				'editor_style'    => 'ohmylms-blocks-editor',
				'style'           => 'ohmylms-blocks-frontend',
			)
		);
	}

	/**
	 * Get block attributes
	 *
	 * @return array
	 */
	private function get_block_attributes() {
		return array(
			// Title styling.
			'titleColor'                  => array(
				'type'    => 'string',
				'default' => '',
			),
			'titleFontSize'               => array(
				'type'    => 'string',
				'default' => '',
			),
			'titleFontWeight'             => array(
				'type'    => 'string',
				'default' => '',
			),
			'titleFontFamily'             => array(
				'type'    => 'string',
				'default' => '',
			),
			'titleTextTransform'          => array(
				'type'    => 'string',
				'default' => '',
			),
			'titleTextDecoration'         => array(
				'type'    => 'string',
				'default' => '',
			),
			'titleLineHeight'             => array(
				'type'    => 'string',
				'default' => '',
			),
			'titleLetterSpacing'          => array(
				'type'    => 'string',
				'default' => '',
			),

			// Input styling.
			'inputLabelColor'             => array(
				'type'    => 'string',
				'default' => '',
			),
			'inputLabelFontSize'          => array(
				'type'    => 'string',
				'default' => '',
			),
			'inputLabelFontWeight'        => array(
				'type'    => 'string',
				'default' => '',
			),
			'inputLabelFontFamily'        => array(
				'type'    => 'string',
				'default' => '',
			),
			'inputFontSize'               => array(
				'type'    => 'string',
				'default' => '',
			),
			'inputFontWeight'             => array(
				'type'    => 'string',
				'default' => '',
			),
			'inputColor'                  => array(
				'type'    => 'string',
				'default' => '',
			),
			'inputFontFamily'             => array(
				'type'    => 'string',
				'default' => '',
			),
			'inputBackgroundColor'        => array(
				'type'    => 'string',
				'default' => '',
			),
			'inputBorderColor'            => array(
				'type'    => 'string',
				'default' => '',
			),
			'inputBorderWidth'            => array(
				'type'    => 'string',
				'default' => '',
			),
			'inputBorderStyle'            => array(
				'type'    => 'string',
				'default' => '',
			),
			'inputBorderRadius'           => array(
				'type'    => 'string',
				'default' => '',
			),

			// Button styling.
			'buttonBackgroundColor'       => array(
				'type'    => 'string',
				'default' => '',
			),
			'buttonColor'                 => array(
				'type'    => 'string',
				'default' => '',
			),
			'buttonFontSize'              => array(
				'type'    => 'string',
				'default' => '',
			),
			'buttonFontWeight'            => array(
				'type'    => 'string',
				'default' => '',
			),
			'buttonFontFamily'            => array(
				'type'    => 'string',
				'default' => '',
			),
			'buttonTextTransform'         => array(
				'type'    => 'string',
				'default' => '',
			),
			'buttonTextDecoration'        => array(
				'type'    => 'string',
				'default' => '',
			),
			'buttonLineHeight'            => array(
				'type'    => 'string',
				'default' => '',
			),
			'buttonLetterSpacing'         => array(
				'type'    => 'string',
				'default' => '',
			),
			'buttonPaddingTop'            => array(
				'type'    => 'string',
				'default' => '',
			),
			'buttonPaddingRight'          => array(
				'type'    => 'string',
				'default' => '',
			),
			'buttonPaddingBottom'         => array(
				'type'    => 'string',
				'default' => '',
			),
			'buttonPaddingLeft'           => array(
				'type'    => 'string',
				'default' => '',
			),
			'buttonMarginTop'             => array(
				'type'    => 'string',
				'default' => '',
			),
			'buttonMarginRight'           => array(
				'type'    => 'string',
				'default' => '',
			),
			'buttonMarginBottom'          => array(
				'type'    => 'string',
				'default' => '',
			),
			'buttonMarginLeft'            => array(
				'type'    => 'string',
				'default' => '',
			),
			'buttonBorderRadius'          => array(
				'type'    => 'string',
				'default' => '',
			),
			'buttonBorderColor'           => array(
				'type'    => 'string',
				'default' => '',
			),
			'buttonBorderWidth'           => array(
				'type'    => 'string',
				'default' => '',
			),
			'buttonBorderStyle'           => array(
				'type'    => 'string',
				'default' => '',
			),
			'buttonBoxShadow'             => array(
				'type'    => 'string',
				'default' => '',
			),

			// Button hover states.
			'buttonHoverBackgroundColor'  => array(
				'type'    => 'string',
				'default' => '',
			),
			'buttonHoverColor'            => array(
				'type'    => 'string',
				'default' => '',
			),
			'buttonHoverBorderColor'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'buttonHoverBoxShadow'        => array(
				'type'    => 'string',
				'default' => '',
			),

			// Privacy text styling.
			'privacyTextColor'            => array(
				'type'    => 'string',
				'default' => '',
			),
			'privacyTextFontSize'         => array(
				'type'    => 'string',
				'default' => '',
			),
			'privacyTextFontWeight'       => array(
				'type'    => 'string',
				'default' => '',
			),
			'privacyTextFontFamily'       => array(
				'type'    => 'string',
				'default' => '',
			),
			'privacyTextTransform'        => array(
				'type'    => 'string',
				'default' => '',
			),
			'privacyTextDecoration'       => array(
				'type'    => 'string',
				'default' => '',
			),
			'privacyTextLineHeight'       => array(
				'type'    => 'string',
				'default' => '',
			),
			'privacyTextLetterSpacing'    => array(
				'type'    => 'string',
				'default' => '',
			),

			// Checkout box styling.
			'checkoutBoxBackgroundColor'  => array(
				'type'    => 'string',
				'default' => '',
			),
			'checkoutBoxPaddingTop'       => array(
				'type'    => 'string',
				'default' => '',
			),
			'checkoutBoxPaddingRight'     => array(
				'type'    => 'string',
				'default' => '',
			),
			'checkoutBoxPaddingBottom'    => array(
				'type'    => 'string',
				'default' => '',
			),
			'checkoutBoxPaddingLeft'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'checkoutBoxMarginTop'        => array(
				'type'    => 'string',
				'default' => '',
			),
			'checkoutBoxMarginRight'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'checkoutBoxMarginBottom'     => array(
				'type'    => 'string',
				'default' => '',
			),
			'checkoutBoxMarginLeft'       => array(
				'type'    => 'string',
				'default' => '',
			),
			'checkoutBoxBorderColor'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'checkoutBoxBorderWidth'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'checkoutBoxBorderStyle'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'checkoutBoxBorderRadius'     => array(
				'type'    => 'string',
				'default' => '',
			),

			// Order summary styling.
			'orderSummaryBackgroundColor' => array(
				'type'    => 'string',
				'default' => '',
			),
			'orderSummaryPaddingTop'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'orderSummaryPaddingRight'    => array(
				'type'    => 'string',
				'default' => '',
			),
			'orderSummaryPaddingBottom'   => array(
				'type'    => 'string',
				'default' => '',
			),
			'orderSummaryPaddingLeft'     => array(
				'type'    => 'string',
				'default' => '',
			),
			'orderSummaryMarginTop'       => array(
				'type'    => 'string',
				'default' => '',
			),
			'orderSummaryMarginRight'     => array(
				'type'    => 'string',
				'default' => '',
			),
			'orderSummaryMarginBottom'    => array(
				'type'    => 'string',
				'default' => '',
			),
			'orderSummaryMarginLeft'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'orderSummaryBorderColor'     => array(
				'type'    => 'string',
				'default' => '',
			),
			'orderSummaryBorderWidth'     => array(
				'type'    => 'string',
				'default' => '',
			),
			'orderSummaryBorderStyle'     => array(
				'type'    => 'string',
				'default' => '',
			),
			'orderSummaryBorderRadius'    => array(
				'type'    => 'string',
				'default' => '',
			),

			// Empty cart settings.
			'showEmptyCartMessage'        => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'emptyCartTitle'              => array(
				'type'    => 'string',
				'default' => '',
			),
			'emptyCartMessage'            => array(
				'type'    => 'string',
				'default' => '',
			),
			'browseCoursesText'           => array(
				'type'    => 'string',
				'default' => '',
			),
			'align'                       => array(
				'type'    => 'string',
				'default' => 'wide',
			),
			// Layout Type.
			'layoutType'                  => array(
				'type'    => 'string',
				'default' => '',
			),
		);
	}

	/**
	 * Convert block attributes to shortcode attributes
	 *
	 * @param array $attributes Block attributes.
	 * @return array Shortcode attributes.
	 */
	private function convert_attributes_to_shortcode_attrs( $attributes ) {
		$shortcode_attrs = array();

		// Define attribute mapping (camelCase to snake_case).
		$attribute_map = array(
			'titleColor'                  => 'title_color',
			'titleFontSize'               => 'title_font_size',
			'titleFontWeight'             => 'title_font_weight',
			'titleFontFamily'             => 'title_font_family',
			'titleTextTransform'          => 'title_text_transform',
			'titleTextDecoration'         => 'title_text_decoration',
			'titleLineHeight'             => 'title_line_height',
			'titleLetterSpacing'          => 'title_letter_spacing',

			'inputLabelColor'             => 'input_label_color',
			'inputLabelFontSize'          => 'input_label_font_size',
			'inputLabelFontWeight'        => 'input_label_font_weight',
			'inputLabelFontFamily'        => 'input_label_font_family',
			'inputFontSize'               => 'input_font_size',
			'inputFontWeight'             => 'input_font_weight',
			'inputColor'                  => 'input_color',
			'inputFontFamily'             => 'input_font_family',
			'inputBackgroundColor'        => 'input_background_color',
			'inputBorderColor'            => 'input_border_color',
			'inputBorderWidth'            => 'input_border_width',
			'inputBorderStyle'            => 'input_border_style',
			'inputBorderRadius'           => 'input_border_radius',

			'buttonBackgroundColor'       => 'button_background_color',
			'buttonColor'                 => 'button_color',
			'buttonFontSize'              => 'button_font_size',
			'buttonFontWeight'            => 'button_font_weight',
			'buttonFontFamily'            => 'button_font_family',
			'buttonTextTransform'         => 'button_text_transform',
			'buttonTextDecoration'        => 'button_text_decoration',
			'buttonLineHeight'            => 'button_line_height',
			'buttonLetterSpacing'         => 'button_letter_spacing',
			'buttonPaddingTop'            => 'button_padding_top',
			'buttonPaddingRight'          => 'button_padding_right',
			'buttonPaddingBottom'         => 'button_padding_bottom',
			'buttonPaddingLeft'           => 'button_padding_left',
			'buttonMarginTop'             => 'button_margin_top',
			'buttonMarginRight'           => 'button_margin_right',
			'buttonMarginBottom'          => 'button_margin_bottom',
			'buttonMarginLeft'            => 'button_margin_left',
			'buttonBorderRadius'          => 'button_border_radius',
			'buttonBorderColor'           => 'button_border_color',
			'buttonBorderWidth'           => 'button_border_width',
			'buttonBorderStyle'           => 'button_border_style',
			'buttonBoxShadow'             => 'button_box_shadow',

			'buttonHoverBackgroundColor'  => 'button_hover_background_color',
			'buttonHoverColor'            => 'button_hover_color',
			'buttonHoverBorderColor'      => 'button_hover_border_color',
			'buttonHoverBoxShadow'        => 'button_hover_box_shadow',

			'privacyTextColor'            => 'privacy_text_color',
			'privacyTextFontSize'         => 'privacy_text_font_size',
			'privacyTextFontWeight'       => 'privacy_text_font_weight',
			'privacyTextFontFamily'       => 'privacy_text_font_family',
			'privacyTextTransform'        => 'privacy_text_transform',
			'privacyTextDecoration'       => 'privacy_text_decoration',
			'privacyTextLineHeight'       => 'privacy_text_line_height',
			'privacyTextLetterSpacing'    => 'privacy_text_letter_spacing',

			'checkoutBoxBackgroundColor'  => 'checkout_box_background_color',
			'checkoutBoxPaddingTop'       => 'checkout_box_padding_top',
			'checkoutBoxPaddingRight'     => 'checkout_box_padding_right',
			'checkoutBoxPaddingBottom'    => 'checkout_box_padding_bottom',
			'checkoutBoxPaddingLeft'      => 'checkout_box_padding_left',
			'checkoutBoxMarginTop'        => 'checkout_box_margin_top',
			'checkoutBoxMarginRight'      => 'checkout_box_margin_right',
			'checkoutBoxMarginBottom'     => 'checkout_box_margin_bottom',
			'checkoutBoxMarginLeft'       => 'checkout_box_margin_left',
			'checkoutBoxBorderColor'      => 'checkout_box_border_color',
			'checkoutBoxBorderWidth'      => 'checkout_box_border_width',
			'checkoutBoxBorderStyle'      => 'checkout_box_border_style',
			'checkoutBoxBorderRadius'     => 'checkout_box_border_radius',

			'orderSummaryBackgroundColor' => 'order_summary_background_color',
			'orderSummaryPaddingTop'      => 'order_summary_padding_top',
			'orderSummaryPaddingRight'    => 'order_summary_padding_right',
			'orderSummaryPaddingBottom'   => 'order_summary_padding_bottom',
			'orderSummaryPaddingLeft'     => 'order_summary_padding_left',
			'orderSummaryMarginTop'       => 'order_summary_margin_top',
			'orderSummaryMarginRight'     => 'order_summary_margin_right',
			'orderSummaryMarginBottom'    => 'order_summary_margin_bottom',
			'orderSummaryMarginLeft'      => 'order_summary_margin_left',
			'orderSummaryBorderColor'     => 'order_summary_border_color',
			'orderSummaryBorderWidth'     => 'order_summary_border_width',
			'orderSummaryBorderStyle'     => 'order_summary_border_style',
			'orderSummaryBorderRadius'    => 'order_summary_border_radius',
			'layoutType'                  => 'layout_type',
		);

		// Convert attributes.
		foreach ( $attribute_map as $block_attr => $shortcode_attr ) {
			if ( ! empty( $attributes[ $block_attr ] ) ) {
				$shortcode_attrs[ $shortcode_attr ] = $attributes[ $block_attr ];
			}
		}

		return $shortcode_attrs;
	}

	/**
	 * Render the block
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content Block content.
	 * @return string
	 */
	public function render_block( $attributes, $content = '' ) {
		// Validate and sanitize attributes.
		$attributes = $this->validate_attributes( $attributes );

		// Convert block attributes to shortcode attributes.
		$shortcode_attrs = $this->convert_attributes_to_shortcode_attrs( $attributes );

		// Check if we're in the editor context (ServerSideRender).
		$is_editor = defined( 'REST_REQUEST' ) && REST_REQUEST;

		// Start output buffering.
		ob_start();

		// Enable preview mode for Gutenberg editor to show checkout form even with empty cart.
		if ( $is_editor ) {
			add_filter( 'ohmylms_gutenberg_preview_mode', '__return_true' );
		}

		// Add editor-specific styling for proper checkout form rendering.
		if ( $is_editor ) {
			?>
			<style>
				.wp-block-ohmylms-checkout .ohmylms {
					max-width: 100% !important;
					background-color: #F9FAFD !important;
					width: 100% !important;
				}
				.wp-block-ohmylms-checkout .ohmylms-checkout-form-wrapper {
					display: flex !important;
					flex-flow: row wrap !important;
					align-items: flex-start !important;
					position: relative !important;
					z-index: 1 !important;
				}
				.wp-block-ohmylms-checkout .ohmylms-checkout-form-left {
					width: 60% !important;
					position: relative !important;
					background-color: #fff !important;
					padding: 30px 50px 30px 0 !important;
					min-height: auto !important;
				}
				.wp-block-ohmylms-checkout .ohmylms-checkout-form-right {
					width: 40% !important;
					padding: 30px 0 30px 30px !important;
					position: relative !important;
				}
				.wp-block-ohmylms-checkout .ohmylms-checkout-title {
					color: var(--ohmylms-heading-color, #1e1e1e) !important;
					font-size: 22px !important;
					font-weight: 600 !important;
					line-height: 1.3 !important;
					margin: 0 0 22px !important;
					letter-spacing: 0 !important;
				}
				.wp-block-ohmylms-checkout .ohmylms-input-wrapper {
					position: relative !important;
					display: block !important;
				}
				.wp-block-ohmylms-checkout .ohmylms-input-label {
					color: var(--ohmylms-body-text-color, #333) !important;
					font-size: 14px !important;
					font-weight: 400 !important;
					line-height: 1.14 !important;
					display: block !important;
					position: absolute !important;
					left: 13px !important;
					top: 50% !important;
					transform: translateY(-50%) !important;
					transition: all 0.3s ease !important;
					background: #ffffff !important;
					padding: 0 4px !important;
					z-index: 2 !important;
				}
				.wp-block-ohmylms-checkout .ohmylms-input-text,
				.wp-block-ohmylms-checkout .ohmylms-input-select {
					color: var(--ohmylms-body-text-color, #333) !important;
					font-size: 14px !important;
					font-weight: 400 !important;
					line-height: 1.25 !important;
					border-radius: 8px !important;
					border: 1px solid rgba(200, 210, 233, 0.5) !important;
					margin: 0 !important;
					box-shadow: none !important;
					outline: none !important;
					width: 100% !important;
					min-height: auto !important;
					padding: 26px 16px 8px !important;
					background-color: #fff !important;
					transition: all 0.3s ease !important;
					height: auto !important;
				}
				.wp-block-ohmylms-checkout .ohmylms-folded .ohmylms-input-label {
					top: calc(50% - 10px) !important;
					color: #7A8B9A !important;
					font-size: 12px !important;
				}
				.wp-block-ohmylms-checkout .ohmylms-form-group {
					width: 100% !important;
					margin-bottom: 20px !important;
				}
				.wp-block-ohmylms-checkout .ohmylms-form-group.half-width {
					width: calc(50% - 10px) !important;
				}
				.wp-block-ohmylms-checkout .ohmylms-form-wrapper {
					display: flex !important;
					flex-flow: row wrap !important;
					align-items: flex-start !important;
					gap: 20px !important;
					row-gap: 24px !important;
				}
				@media (max-width: 768px) {
					.wp-block-ohmylms-checkout .ohmylms-checkout-form-left,
					.wp-block-ohmylms-checkout .ohmylms-checkout-form-right {
						width: 100% !important;
						padding: 20px !important;
					}
					.wp-block-ohmylms-checkout .ohmylms-form-group.half-width {
						width: 100% !important;
					}
				}
			</style>
			<?php
		}

		// Add proper wrapper classes for consistency with frontend.
		$wrapper_classes = array( 'ohmylms' );
		if ( $is_editor ) {
			$wrapper_classes[] = 'ohmylms-page';
			$wrapper_classes[] = 'ohmylms-checkout';
		}

		echo '<div class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '">';

		// Add preview notice in editor mode.
		if ( $is_editor ) {
			echo '<div class="ohmylms-gutenberg-edit-mode">';
			echo '<small>' . esc_html__( 'Gutenberg Preview Mode: This is how the checkout will appear to users with items in their cart.', 'ohmylms' ) . '</small>';
			echo '</div>';
		}

		// Output the checkout form.
		ShortCodeCheckout::output( $shortcode_attrs );

		echo '</div>';

		// Remove preview mode filter if it was set.
		if ( $is_editor ) {
			remove_filter( 'ohmylms_gutenberg_preview_mode', '__return_true' );
		}

		return ob_get_clean();
	}

	/**
	 * Validate and sanitize block attributes
	 *
	 * @param array $attributes Raw attributes.
	 * @return array Validated attributes
	 */
	private function validate_attributes( $attributes ) {
		$validated          = array();
		$default_attributes = $this->get_block_attributes();

		foreach ( $default_attributes as $key => $config ) {
			if ( isset( $attributes[ $key ] ) ) {
				$value = $attributes[ $key ];

				// Validate based on type.
				switch ( $config['type'] ) {
					case 'boolean':
						$validated[ $key ] = (bool) $value;
						break;
					case 'string':
						$validated[ $key ] = is_string( $value ) ? sanitize_text_field( $value ) : '';
						break;
					default:
						$validated[ $key ] = $config['default'];
				}
			} else {
				$validated[ $key ] = $config['default'];
			}
		}

		return $validated;
	}

	/**
	 * Render empty cart message
	 *
	 * @param array $attributes Block attributes.
	 * @return void
	 */
	private function render_empty_cart_message( $attributes ) {
		$archive_page_id  = get_option( 'ohmylms_course_page_id', 0 );
		$archive_page_url = home_url();
		if ( $archive_page_id ) {
			$archive_page_url = get_permalink( $archive_page_id );
		}

		$empty_cart_title    = ! empty( $attributes['emptyCartTitle'] ) ? $attributes['emptyCartTitle'] : esc_html__( 'Your cart is empty', 'ohmylms' );
		$empty_cart_message  = ! empty( $attributes['emptyCartMessage'] ) ? $attributes['emptyCartMessage'] : esc_html__( 'Add some courses to your cart to proceed with checkout.', 'ohmylms' );
		$browse_courses_text = ! empty( $attributes['browseCoursesText'] ) ? $attributes['browseCoursesText'] : esc_html__( 'Browse Courses', 'ohmylms' );

		echo '<div class="ohmylms-empty-cart-message">';
		echo '<h3>' . esc_html( $empty_cart_title ) . '</h3>';
		echo '<p>' . esc_html( $empty_cart_message ) . '</p>';
		echo '<a href="' . esc_url( $archive_page_url ) . '" class="ohmylms-browse-courses-btn">';
		echo esc_html( $browse_courses_text );
		echo '</a>';
		echo '</div>';
	}
}
