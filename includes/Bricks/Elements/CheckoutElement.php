<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
namespace OhMyLMS\Bricks\Elements;

use OhMyLMS\Shortcodes\ShortCodeCheckout;
use function OhMyLMS\Commerce\ecommerce;
use Bricks\Element;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Plugin checkout element implementation.
 */
class CheckoutElement extends Element {

	/**
	 * Element category
	 *
	 * @var string
	 */
	public $category = 'ohmylms';

	/**
	 * Element name
	 *
	 * @var string
	 */
	public $name = 'ohmylms-checkout-2';

	/**
	 * Element icon
	 *
	 * @var string
	 */
	public $icon = 'ti-shopping-cart';

	/**
	 * Element keywords
	 *
	 * @var array
	 */
	public $keywords = array( 'checkout', 'cart', 'purchase', 'creator', 'lms' );

	/**
	 * Element scripts
	 *
	 * @var array
	 */
	public $scripts = array( 'ohmylms-frontend', 'ohmylms-checkout', 'ohmylms-tax-calculation' );

	/**
	 * Element styles
	 *
	 * @var array
	 */
	public $styles = array( 'ohmylms-frontend', 'ohmylms-general' );

	/**
	 * Get element label
	 *
	 * @return string
	 */
	public function get_label() {
		return esc_html__( 'OhMyLMS Checkout', 'ohmylms' );
	}

	/**
	 * Set element controls
	 *
	 * @return void
	 */
	public function set_controls() {
		$this->controls       = array();
		$this->control_groups = array();
		$this->set_content_controls();
		$this->set_style_controls();
	}

	/**
	 * Set content controls
	 *
	 * @return void
	 */
	private function set_content_controls() {
		// Content Settings Group.
		$this->controls['content_heading'] = array(
			'tab'   => 'content',
			'label' => esc_html__( 'Content Settings', 'ohmylms' ),
			'type'  => 'separator',
		);

		$this->controls['show_empty_cart_message'] = array(
			'tab'         => 'content',
			'label'       => esc_html__( 'Show Empty Cart Message', 'ohmylms' ),
			'type'        => 'checkbox',
			'default'     => true,
			'description' => esc_html__( 'Show a message when the cart is empty with a link to browse courses.', 'ohmylms' ),
		);

		$this->controls['empty_cart_title'] = array(
			'tab'      => 'content',
			'label'    => esc_html__( 'Empty Cart Title', 'ohmylms' ),
			'type'     => 'text',
			'default'  => esc_html__( 'Your cart is empty', 'ohmylms' ),
			'required' => array( 'show_empty_cart_message', '=', true ),
		);

		$this->controls['empty_cart_message'] = array(
			'tab'      => 'content',
			'label'    => esc_html__( 'Empty Cart Message', 'ohmylms' ),
			'type'     => 'textarea',
			'default'  => esc_html__( 'Add some courses to your cart to proceed with checkout.', 'ohmylms' ),
			'required' => array( 'show_empty_cart_message', '=', true ),
		);

		$this->controls['browse_courses_text'] = array(
			'tab'      => 'content',
			'label'    => esc_html__( 'Browse Courses Button Text', 'ohmylms' ),
			'type'     => 'text',
			'default'  => esc_html__( 'Browse Courses', 'ohmylms' ),
			'required' => array( 'show_empty_cart_message', '=', true ),
		);

		$this->controls['layout_type'] = array(
			'tab'         => 'content',
			'label'       => esc_html__( 'Layout Type', 'ohmylms' ),
			'type'        => 'select',
			'options'     => array(
				''        => esc_html__( 'Use Global Setting', 'ohmylms' ),
				'default' => esc_html__( 'Default Layout (with header/footer)', 'ohmylms' ),
				'canvas'  => esc_html__( 'Canvas Layout (no header/footer)', 'ohmylms' ),
			),
			'default'     => '',
			'description' => esc_html__( 'Choose the layout type for this checkout page. Canvas layout removes the header and footer for a focused checkout experience.', 'ohmylms' ),
		);
	}

	/**
	 * Set style controls
	 *
	 * @return void
	 */
	private function set_style_controls() {

		// -------- Step 1: Define Groups --------
		$groups = array(
			'title'         => esc_html__( 'Title Style', 'ohmylms' ),
			'form_row'      => esc_html__( 'Form Row Style', 'ohmylms' ),
			'input_label'   => esc_html__( 'Input Label Style', 'ohmylms' ),
			'input_field'   => esc_html__( 'Input Field Style', 'ohmylms' ),
			'button'        => esc_html__( 'Checkout Button Style', 'ohmylms' ),
			'privacy_text'  => esc_html__( 'Privacy Text Style', 'ohmylms' ),
			'checkout_box'  => esc_html__( 'Checkout Form Container', 'ohmylms' ),
			'order_summary' => esc_html__( 'Order Summary Container', 'ohmylms' ),
		);

		foreach ( $groups as $group => $label ) {
			$this->control_groups[ $group ] = array(
				'title' => $label,
				'tab'   => 'style',
			);
		}

		// -------- Step 2: Add Controls --------

		$this->controls['title_typography'] = array(
			'group' => 'title',
			'label' => esc_html__( 'Typography', 'ohmylms' ),
			'type'  => 'typography',
			'css'   => array(
				array(
					'property' => 'font',
					'selector' => '{{WRAPPER}} .ohmylms-checkout-title',
				),
			),
		);

		$this->controls['title_padding'] = array(
			'group' => 'title',
			'label' => esc_html__( 'Padding', 'ohmylms' ),
			'type'  => 'spacing',
			'css'   => array(
				array(
					'property' => 'padding',
					'selector' => '{{WRAPPER}} .ohmylms-checkout-title',
				),
			),
		);

		$this->controls['title_background_color'] = array(
			'group' => 'title',
			'label' => esc_html__( 'Background Color', 'ohmylms' ),
			'type'  => 'color',
			'css'   => array(
				array(
					'property' => 'background-color',
					'selector' => '{{WRAPPER}} .ohmylms-checkout-title',
				),
			),
		);

		// === FORM ROW STYLING ===

		$this->controls['form_row_padding'] = array(
			'group' => 'form_row',
			'label' => esc_html__( 'Padding', 'ohmylms' ),
			'type'  => 'spacing',
			'css'   => array(
				array(
					'property' => 'padding',
					'selector' => '{{WRAPPER}} .ohmylms-form-row',
				),
			),
		);

		$this->controls['form_row_background'] = array(
			'group' => 'form_row',
			'label' => esc_html__( 'Background Color', 'ohmylms' ),
			'type'  => 'color',
			'css'   => array(
				array(
					'property' => 'background-color',
					'selector' => '{{WRAPPER}} .ohmylms-form-row',
				),
			),
		);

		$this->controls['form_row_border'] = array(
			'group' => 'form_row',
			'label' => esc_html__( 'Border', 'ohmylms' ),
			'type'  => 'border',
			'css'   => array(
				array(
					'property' => 'border',
					'selector' => '{{WRAPPER}} .ohmylms-form-row',
				),
			),
		);

		// Input Label Controls.
		$this->controls['input_label_typography'] = array(
			'group' => 'input_label',
			'label' => esc_html__( 'Typography', 'ohmylms' ),
			'type'  => 'typography',
			'css'   => array(
				array(
					'property' => 'font',
					'selector' => '{{WRAPPER}} .ohmylms-form-row label',
				),
			),
		);

		$this->controls['input_label_margin'] = array(
			'group' => 'input_label',
			'label' => esc_html__( 'Margin', 'ohmylms' ),
			'type'  => 'spacing',
			'css'   => array(
				array(
					'property' => 'margin',
					'selector' => '{{WRAPPER}} .ohmylms-form-row label',
				),
			),
		);

		// Input Field Controls.
		$this->controls['input_color'] = array(
			'group'   => 'input_field',
			'label'   => esc_html__( 'Text Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => 'var(--ohmylms-heading-color)',
			'css'     => array(
				array(
					'property' => 'color',
					'selector' => '{{WRAPPER}} .ohmylms-input-text, {{WRAPPER}} .ohmylms-input-select',
				),
			),
		);

		$this->controls['input_background_color'] = array(
			'group'   => 'input_field',
			'label'   => esc_html__( 'Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => '#FFF',
			'css'     => array(
				array(
					'property' => 'background-color',
					'selector' => '{{WRAPPER}} .ohmylms-input-text, {{WRAPPER}} .ohmylms-input-select',
				),
			),
		);

		$this->controls['input_typography'] = array(
			'group' => 'input_field',
			'label' => esc_html__( 'Typography', 'ohmylms' ),
			'type'  => 'typography',
			'css'   => array(
				array(
					'property' => 'font',
					'selector' => '{{WRAPPER}} .ohmylms-page .ohmylms-input-text, {{WRAPPER}} .ohmylms-page .ohmylms-input-select',
				),
			),
		);

		$this->controls['input_border'] = array(
			'group' => 'input_field',
			'label' => esc_html__( 'Border', 'ohmylms' ),
			'type'  => 'border',
			'css'   => array(
				array(
					'property' => 'border',
					'selector' => '{{WRAPPER}} .ohmylms-input-text, {{WRAPPER}} .ohmylms-input-select',
				),
			),
		);

		$this->controls['input_padding'] = array(
			'group' => 'input_field',
			'label' => esc_html__( 'Padding', 'ohmylms' ),
			'type'  => 'spacing',
			'css'   => array(
				array(
					'property' => 'padding',
					'selector' => '{{WRAPPER}} .ohmylms-input-text, {{WRAPPER}} .ohmylms-input-select',
				),
			),
		);

		$this->controls['input_margin'] = array(
			'group' => 'input_field',
			'label' => esc_html__( 'Margin', 'ohmylms' ),
			'type'  => 'spacing',
			'css'   => array(
				array(
					'property' => 'margin',
					'selector' => '{{WRAPPER}} .ohmylms-input-text, {{WRAPPER}} .ohmylms-input-select',
				),
			),
		);

		$this->controls['input_box_shadow'] = array(
			'group' => 'input_field',
			'label' => esc_html__( 'Box Shadow', 'ohmylms' ),
			'type'  => 'box-shadow',
			'css'   => array(
				array(
					'property' => 'box-shadow',
					'selector' => '{{WRAPPER}} .ohmylms-input-text, {{WRAPPER}} .ohmylms-input-select',
				),
			),
		);

		// Input Focus States.
		$this->controls['input_focus_color'] = array(
			'group' => 'input_field',
			'label' => esc_html__( 'Focus Border Color', 'ohmylms' ),
			'type'  => 'color',
			'css'   => array(
				array(
					'property' => 'border-color',
					'selector' => '{{WRAPPER}} .ohmylms-input-text:focus, {{WRAPPER}} .ohmylms-input-select:focus',
				),
			),
		);

		$this->controls['input_focus_box_shadow'] = array(
			'group' => 'input_field',
			'label' => esc_html__( 'Focus Box Shadow', 'ohmylms' ),
			'type'  => 'box-shadow',
			'css'   => array(
				array(
					'property' => 'box-shadow',
					'selector' => '{{WRAPPER}} .ohmylms-input-text:focus, {{WRAPPER}} .ohmylms-input-select:focus',
				),
			),
		);

		// Button Controls.
		$this->controls['button_background_color'] = array(
			'group'   => 'button',
			'label'   => esc_html__( 'Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => '#6e42d3',
			'css'     => array(
				array(
					'property'  => 'background-color',
					'selector'  => '{{WRAPPER}} .ohmylms-page .ohmylms-checkout-payment button.ohmylms-place-order-button',
					'important' => true,
				),
			),
		);

		$this->controls['button_typography'] = array(
			'group' => 'button',
			'label' => esc_html__( 'Typography', 'ohmylms' ),
			'type'  => 'typography',
			'css'   => array(
				array(
					'property' => 'font',
					'selector' => '{{WRAPPER}} .ohmylms-page .ohmylms-checkout-payment button.ohmylms-place-order-button',
				),
			),
		);

		$this->controls['button_border'] = array(
			'group' => 'button',
			'label' => esc_html__( 'Border', 'ohmylms' ),
			'type'  => 'border',
			'css'   => array(
				array(
					'property' => 'border',
					'selector' => '{{WRAPPER}} .ohmylms-page .ohmylms-checkout-payment button.ohmylms-place-order-button',
				),
			),
		);

		$this->controls['button_padding'] = array(
			'group' => 'button',
			'label' => esc_html__( 'Padding', 'ohmylms' ),
			'type'  => 'spacing',
			'css'   => array(
				array(
					'property' => 'padding',
					'selector' => '{{WRAPPER}} .ohmylms-page .ohmylms-checkout-payment button.ohmylms-place-order-button',
				),
			),
		);

		$this->controls['button_margin'] = array(
			'group' => 'button',
			'label' => esc_html__( 'Margin', 'ohmylms' ),
			'type'  => 'spacing',
			'css'   => array(
				array(
					'property' => 'margin',
					'selector' => '{{WRAPPER}} .ohmylms-page .ohmylms-checkout-payment button.ohmylms-place-order-button',
				),
			),
		);

		$this->controls['button_hover_color'] = array(
			'group'   => 'button',
			'label'   => esc_html__( 'Hover Text Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => '#6e42d3',
			'css'     => array(
				array(
					'property' => 'color',
					'selector' => '{{WRAPPER}} .ohmylms-page .ohmylms-checkout-payment button.ohmylms-place-order-button:hover',
				),
			),
		);

		$this->controls['button_hover_background_color'] = array(
			'group'   => 'button',
			'label'   => esc_html__( 'Hover Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => 'transparent',
			'css'     => array(
				array(
					'property' => 'background-color',
					'selector' => '{{WRAPPER}} .ohmylms-page .ohmylms-checkout-payment button.ohmylms-place-order-button:hover',
				),
			),
		);

		$this->controls['button_hover_border_color'] = array(
			'group'   => 'button',
			'label'   => esc_html__( 'Hover Border Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => '#6e42d3',
			'css'     => array(
				array(
					'property' => 'border-color',
					'selector' => '{{WRAPPER}} .ohmylms-page .ohmylms-checkout-payment button.ohmylms-place-order-button:hover',
				),
			),
		);

		$this->controls['privacy_text_typography'] = array(
			'group' => 'privacy_text',
			'label' => esc_html__( 'Typography', 'ohmylms' ),
			'type'  => 'typography',
			'css'   => array(
				array(
					'property' => 'font',
					'selector' => '{{WRAPPER}} .ohmylms-page .ohmylms-checkbox .ohmylms-checkbox-text',
				),
			),
		);

		$this->controls['privacy_text_margin'] = array(
			'group' => 'privacy_text',
			'label' => esc_html__( 'Margin', 'ohmylms' ),
			'type'  => 'spacing',
			'css'   => array(
				array(
					'property' => 'margin',
					'selector' => '{{WRAPPER}} .ohmylms-page .ohmylms-checkbox .ohmylms-checkbox-text',
				),
			),
		);

		$this->controls['privacy_text_padding'] = array(
			'group' => 'privacy_text',
			'label' => esc_html__( 'Padding', 'ohmylms' ),
			'type'  => 'spacing',
			'css'   => array(
				array(
					'property' => 'padding',
					'selector' => '{{WRAPPER}} .ohmylms-page .ohmylms-checkbox .ohmylms-checkbox-text',
				),
			),
		);

		$this->controls['checkout_box_background_color'] = array(
			'group'   => 'checkout_box',
			'label'   => esc_html__( 'Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => '#fff',
			'css'     => array(
				array(
					'property' => 'background-color',
					'selector' => '{{WRAPPER}} .ohmylms-page .ohmylms-checkout-form-left',
				),
			),
		);

		$this->controls['checkout_box_border'] = array(
			'group' => 'checkout_box',
			'label' => esc_html__( 'Border', 'ohmylms' ),
			'type'  => 'border',
			'css'   => array(
				array(
					'property' => 'border',
					'selector' => '{{WRAPPER}} .ohmylms-page .ohmylms-checkout-form-left',
				),
			),
		);

		$this->controls['checkout_box_padding'] = array(
			'group' => 'checkout_box',
			'label' => esc_html__( 'Padding', 'ohmylms' ),
			'type'  => 'spacing',
			'css'   => array(
				array(
					'property' => 'padding',
					'selector' => '{{WRAPPER}} .ohmylms-page .ohmylms-checkout-form-left',
				),
			),
		);

		$this->controls['checkout_box_margin'] = array(
			'group' => 'checkout_box',
			'label' => esc_html__( 'Margin', 'ohmylms' ),
			'type'  => 'spacing',
			'css'   => array(
				array(
					'property' => 'margin',
					'selector' => '{{WRAPPER}} .ohmylms-page .ohmylms-checkout-form-left',
				),
			),
		);

		$this->controls['order_summary_background_color'] = array(
			'group' => 'order_summary',
			'label' => esc_html__( 'Background Color', 'ohmylms' ),
			'type'  => 'color',
			'css'   => array(
				array(
					'property' => 'background-color',
					'selector' => '{{WRAPPER}} .ohmylms-checkout-form-right',
				),
			),
		);

		$this->controls['order_summary_border'] = array(
			'group' => 'order_summary',
			'label' => esc_html__( 'Border', 'ohmylms' ),
			'type'  => 'border',
			'css'   => array(
				array(
					'property' => 'border',
					'selector' => '{{WRAPPER}} .ohmylms-checkout-form-right',
				),
			),
		);

		$this->controls['order_summary_padding'] = array(
			'group' => 'order_summary',
			'label' => esc_html__( 'Padding', 'ohmylms' ),
			'type'  => 'spacing',
			'css'   => array(
				array(
					'property' => 'padding',
					'selector' => '{{WRAPPER}} .ohmylms-checkout-form-right',
				),
			),
		);

		$this->controls['order_summary_margin'] = array(
			'group' => 'order_summary',
			'label' => esc_html__( 'Margin', 'ohmylms' ),
			'type'  => 'spacing',
			'css'   => array(
				array(
					'property' => 'margin',
					'selector' => '{{WRAPPER}} .ohmylms-checkout-form-right',
				),
			),
		);

		$this->controls['order_summary_box_shadow'] = array(
			'group' => 'order_summary',
			'label' => esc_html__( 'Box Shadow', 'ohmylms' ),
			'type'  => 'box-shadow',
			'css'   => array(
				array(
					'property' => 'box-shadow',
					'selector' => '{{WRAPPER}} .ohmylms-checkout-form-right',
				),
			),
		);
	}

	/**
	 * Enqueue scripts for Checkout element.
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'ohmylms-checkout' );
		wp_enqueue_script( 'ohmylms-tax-calculation' );
		wp_enqueue_script( 'ohmylms-frontend' );

		wp_enqueue_style( 'ohmylms-frontend' );
		wp_enqueue_style( 'ohmylms-general' );
		$is_bricks_edit_mode = false;
		if ( ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) ||
			( function_exists( 'bricks_is_builder_main' ) && bricks_is_builder_main() ) ||
			( function_exists( 'bricks_is_rest_call' ) && bricks_is_rest_call() )
		) {
			$is_bricks_edit_mode = true;
		}

		// Only add the inline script in edit mode.
		if ( ! $is_bricks_edit_mode ) {
			return;
		}

		$inline_script = "
			jQuery(document).ready(function($) {
				function checkInputValues() {
					$('.ohmylms-input-text').each(function (index) {
						var row = $(this).parents('.ohmylms-form-row');
						var inputValue = $(this).val();

						// Check if the input has a value
						if (inputValue && inputValue.trim() !== '') {
							row.addClass('ohmylms-folded');
						} else {
							row.removeClass('ohmylms-folded');
						}
					});
				}
				
				// Run immediately
				checkInputValues();
				
				// Also run after a short delay to catch dynamically loaded content
				setTimeout(checkInputValues, 500);
				
				// Bind to input changes
				$(document).on('input change', '.ohmylms-input-text', function() {
					var row = $(this).parents('.ohmylms-form-row');
					var inputValue = $(this).val();
					
					if (inputValue && inputValue.trim() !== '') {
						row.addClass('ohmylms-folded');
					} else {
						row.removeClass('ohmylms-folded');
					}
				});
			});
		";
		wp_add_inline_script( 'ohmylms-frontend', $inline_script );
	}

	/**
	 * Render for Checkout element.
	 */
	public function render() {
		$settings = $this->settings;
		// Convert Bricks settings to shortcode attributes.
		$shortcode_attrs = $this->convert_settings_to_shortcode_attrs( $settings );

		// Check if we're in Bricks builder mode.
		$is_bricks_edit_mode = false;
		if ( ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) ||
			( function_exists( 'bricks_is_builder_main' ) && bricks_is_builder_main() ) ||
			( function_exists( 'bricks_is_rest_call' ) && bricks_is_rest_call() )
		) {
			$is_bricks_edit_mode = true;
		}

		// Render the checkout form via shortcode
		// Wrapping divs for styling consistency
		// Use the same wrapper class as shortcode for consistency.
		echo '<div class="ohmylms-page ohmylms-checkout">';
		echo '<div class="ohmylms">';

		// Enable preview mode for Bricks builder to show checkout form even with empty cart.
		if ( $is_bricks_edit_mode ) {
			add_filter( 'ohmylms_bricks_preview_mode', '__return_true' );
		}

		ShortCodeCheckout::output( $shortcode_attrs );

		// Remove preview mode filter if it was set.
		if ( $is_bricks_edit_mode ) {
			remove_filter( 'ohmylms_bricks_preview_mode', '__return_true' );
		}

		echo '</div></div>';
	}


	/**
	 * Convert Bricks settings to shortcode attributes
	 *
	 * @param array $settings Bricks settings.
	 * @return array Shortcode attributes
	 */
	public function convert_settings_to_shortcode_attrs( $settings ) {
		$attrs = array();

		// Content settings.
		if ( isset( $settings['show_empty_cart_message'] ) ) {
			$attrs['show_empty_cart_message'] = $settings['show_empty_cart_message'] ? 'yes' : 'no';
		}

		if ( ! empty( $settings['empty_cart_title'] ) ) {
			$attrs['empty_cart_title'] = $settings['empty_cart_title'];
		}

		if ( ! empty( $settings['empty_cart_message'] ) ) {
			$attrs['empty_cart_message'] = $settings['empty_cart_message'];
		}

		if ( ! empty( $settings['browse_courses_text'] ) ) {
			$attrs['browse_courses_text'] = $settings['browse_courses_text'];
		}

		// === TITLE STYLING ===

		if ( ! empty( $settings['title_typography'] ) ) {
			$typography = $settings['title_typography'];
			// for color also.
			if ( ! empty( $typography['color'] ) ) {
				$attrs['title_color'] = $this->extract_color_value( $typography['color'] );
			}
			if ( ! empty( $typography['font-size'] ) ) {
				$attrs['title_font_size'] = $this->extract_size_value( $typography['font-size'] );
			}
			if ( ! empty( $typography['font-weight'] ) ) {
				$attrs['title_font_weight'] = $typography['font-weight'];
			}
			if ( ! empty( $typography['font-family'] ) ) {
				$attrs['title_font_family'] = $typography['font-family'];
			}
			if ( ! empty( $typography['text-transform'] ) ) {
				$attrs['title_text_transform'] = $typography['text-transform'];
			}
			if ( ! empty( $typography['text-decoration'] ) ) {
				$attrs['title_text_decoration'] = $typography['text-decoration'];
			}
			if ( ! empty( $typography['line-height'] ) ) {
				$attrs['title_line_height'] = $typography['line-height'];
			}
			if ( ! empty( $typography['letter-spacing'] ) ) {
				$attrs['title_letter_spacing'] = $this->extract_size_value( $typography['letter-spacing'] );
			}
		}

		if ( ! empty( $settings['title_padding'] ) ) {
			$padding = $settings['title_padding'];
			if ( ! empty( $padding['top'] ) ) {
				$attrs['title_padding_top'] = $this->extract_size_value( $padding['top'] );
			}
			if ( ! empty( $padding['right'] ) ) {
				$attrs['title_padding_right'] = $this->extract_size_value( $padding['right'] );
			}
			if ( ! empty( $padding['bottom'] ) ) {
				$attrs['title_padding_bottom'] = $this->extract_size_value( $padding['bottom'] );
			}
			if ( ! empty( $padding['left'] ) ) {
				$attrs['title_padding_left'] = $this->extract_size_value( $padding['left'] );
			}
		}

		// Title background and border.
		if ( ! empty( $settings['title_background_color'] ) ) {
			$attrs['title_background_color'] = $this->extract_color_value( $settings['title_background_color'] );
		}

		// === FORM ROW STYLING ===
		if ( ! empty( $settings['form_row_gap'] ) ) {
			$attrs['form_row_gap'] = $this->extract_size_value( $settings['form_row_gap'] );
		}

		if ( ! empty( $settings['form_row_padding'] ) ) {
			$padding = $settings['form_row_padding'];
			if ( ! empty( $padding['top'] ) ) {
				$attrs['form_row_padding_top'] = $this->extract_size_value( $padding['top'] );
			}
			if ( ! empty( $padding['right'] ) ) {
				$attrs['form_row_padding_right'] = $this->extract_size_value( $padding['right'] );
			}
			if ( ! empty( $padding['bottom'] ) ) {
				$attrs['form_row_padding_bottom'] = $this->extract_size_value( $padding['bottom'] );
			}
			if ( ! empty( $padding['left'] ) ) {
				$attrs['form_row_padding_left'] = $this->extract_size_value( $padding['left'] );
			}
		}

		if ( ! empty( $settings['form_row_background'] ) ) {
			$attrs['form_row_background'] = $this->extract_color_value( $settings['form_row_background'] );
		}

		if ( ! empty( $settings['form_row_border'] ) ) {
			$border = $settings['form_row_border'];
			if ( ! empty( $border['color'] ) ) {
				$attrs['form_row_border_color'] = $this->extract_color_value( $border['color'] );
			}
			if ( ! empty( $border['width'] ) ) {
				$attrs['form_row_border_width'] = $this->extract_size_value( $border['width'] );
			}
			if ( ! empty( $border['style'] ) ) {
				$attrs['form_row_border_style'] = $border['style'];
			}
		}

		if ( ! empty( $settings['form_row_border_radius'] ) ) {
			$attrs['form_row_border_radius'] = $this->extract_dimensions_value( $settings['form_row_border_radius'], 'top' );
		}

		// === INPUT LABEL STYLING ===

		if ( ! empty( $settings['input_label_typography'] ) ) {
			$typography = $settings['input_label_typography'];
			if ( ! empty( $typography['color'] ) ) {
				$attrs['input_label_color'] = $this->extract_color_value( $typography['color'] );
			}
			if ( ! empty( $typography['font-size'] ) ) {
				$attrs['input_label_font_size'] = $this->extract_size_value( $typography['font-size'] );
			}
			if ( ! empty( $typography['font-weight'] ) ) {
				$attrs['input_label_font_weight'] = $typography['font-weight'];
			}
			if ( ! empty( $typography['font-family'] ) ) {
				$attrs['input_label_font_family'] = $typography['font-family'];
			}
		}

		// Input label padding and margin.
		if ( ! empty( $settings['input_label_margin'] ) ) {
			$margin = $settings['input_label_margin'];
			if ( ! empty( $margin['top'] ) ) {
				$attrs['input_label_margin_top'] = $this->extract_size_value( $margin['top'] );
			}
			if ( ! empty( $margin['right'] ) ) {
				$attrs['input_label_margin_right'] = $this->extract_size_value( $margin['right'] );
			}
			if ( ! empty( $margin['bottom'] ) ) {
				$attrs['input_label_margin_bottom'] = $this->extract_size_value( $margin['bottom'] );
			}
			if ( ! empty( $margin['left'] ) ) {
				$attrs['input_label_margin_left'] = $this->extract_size_value( $margin['left'] );
			}
		}

		if ( ! empty( $settings['input_label_padding'] ) ) {
			$padding = $settings['input_label_padding'];
			if ( ! empty( $padding['top'] ) ) {
				$attrs['input_label_padding_top'] = $this->extract_size_value( $padding['top'] );
			}
			if ( ! empty( $padding['right'] ) ) {
				$attrs['input_label_padding_right'] = $this->extract_size_value( $padding['right'] );
			}
			if ( ! empty( $padding['bottom'] ) ) {
				$attrs['input_label_padding_bottom'] = $this->extract_size_value( $padding['bottom'] );
			}
			if ( ! empty( $padding['left'] ) ) {
				$attrs['input_label_padding_left'] = $this->extract_size_value( $padding['left'] );
			}
		}

		// === INPUT FIELD STYLING ===

		if ( ! empty( $settings['input_color'] ) ) {
			$attrs['input_color'] = $this->extract_color_value( $settings['input_color'] );
		}

		if ( ! empty( $settings['input_background_color'] ) ) {
			$attrs['input_background_color'] = $this->extract_color_value( $settings['input_background_color'] );
		}

		if ( ! empty( $settings['input_typography'] ) ) {
			$typography = $settings['input_typography'];
			if ( ! empty( $typography['font-size'] ) ) {
				$attrs['input_font_size'] = $this->extract_size_value( $typography['font-size'] );
			}
			if ( ! empty( $typography['font-weight'] ) ) {
				$attrs['input_font_weight'] = $typography['font-weight'];
			}
			if ( ! empty( $typography['font-family'] ) ) {
				$attrs['input_font_family'] = $typography['font-family'];
			}
		}

		if ( ! empty( $settings['input_border'] ) ) {
			$border = $settings['input_border'];
			if ( ! empty( $border['color'] ) ) {
				$attrs['input_border_color'] = $this->extract_color_value( $border['color'] );
			}
			if ( ! empty( $border['width'] ) ) {
				$attrs['input_border_width'] = $this->extract_size_value( $border['width'] );
			}
			if ( ! empty( $border['style'] ) ) {
				$attrs['input_border_style'] = $border['style'];
			}
		}

		if ( ! empty( $settings['input_border_radius'] ) ) {
			$attrs['input_border_radius'] = $this->extract_dimensions_value( $settings['input_border_radius'], 'top' );
		}

		// Input padding and margin.
		if ( ! empty( $settings['input_padding'] ) ) {
			$padding = $settings['input_padding'];
			if ( ! empty( $padding['top'] ) ) {
				$attrs['input_padding_top'] = $this->extract_size_value( $padding['top'] );
			}
			if ( ! empty( $padding['right'] ) ) {
				$attrs['input_padding_right'] = $this->extract_size_value( $padding['right'] );
			}
			if ( ! empty( $padding['bottom'] ) ) {
				$attrs['input_padding_bottom'] = $this->extract_size_value( $padding['bottom'] );
			}
			if ( ! empty( $padding['left'] ) ) {
				$attrs['input_padding_left'] = $this->extract_size_value( $padding['left'] );
			}
		}

		if ( ! empty( $settings['input_margin'] ) ) {
			$margin = $settings['input_margin'];
			if ( ! empty( $margin['top'] ) ) {
				$attrs['input_margin_top'] = $this->extract_size_value( $margin['top'] );
			}
			if ( ! empty( $margin['right'] ) ) {
				$attrs['input_margin_right'] = $this->extract_size_value( $margin['right'] );
			}
			if ( ! empty( $margin['bottom'] ) ) {
				$attrs['input_margin_bottom'] = $this->extract_size_value( $margin['bottom'] );
			}
			if ( ! empty( $margin['left'] ) ) {
				$attrs['input_margin_left'] = $this->extract_size_value( $margin['left'] );
			}
		}

		// Input box shadow.
		if ( ! empty( $settings['input_box_shadow'] ) ) {
			$attrs['input_box_shadow'] = $this->extract_box_shadow_value( $settings['input_box_shadow'] );
		}

		// Input focus states.
		if ( ! empty( $settings['input_focus_color'] ) ) {
			$attrs['input_focus_border_color'] = $this->extract_color_value( $settings['input_focus_color'] );
		}

		if ( ! empty( $settings['input_focus_box_shadow'] ) ) {
			$attrs['input_focus_box_shadow'] = $this->extract_box_shadow_value( $settings['input_focus_box_shadow'] );
		}

		// === BUTTON STYLING ===
		// if ( ! empty( $settings['button_color'] ) ) {
		// $attrs['button_color'] = $this->extract_color_value( $settings['button_color'] );
		// }

		if ( ! empty( $settings['button_background_color'] ) ) {
			$attrs['button_background_color'] = $this->extract_color_value( $settings['button_background_color'] );
		}

		if ( ! empty( $settings['button_typography'] ) ) {
			$typography = $settings['button_typography'];
			// for color.
			if ( ! empty( $typography['color'] ) ) {
				$attrs['button_color'] = $this->extract_color_value( $typography['color'] );
			}

			if ( ! empty( $typography['font-size'] ) ) {
				$attrs['button_font_size'] = $this->extract_size_value( $typography['font-size'] );
			}
			if ( ! empty( $typography['font-weight'] ) ) {
				$attrs['button_font_weight'] = $typography['font-weight'];
			}
			if ( ! empty( $typography['font-family'] ) ) {
				$attrs['button_font_family'] = $typography['font-family'];
			}
			if ( ! empty( $typography['text-transform'] ) ) {
				$attrs['button_text_transform'] = $typography['text-transform'];
			}
			if ( ! empty( $typography['text-decoration'] ) ) {
				$attrs['button_text_decoration'] = $typography['text-decoration'];
			}
			if ( ! empty( $typography['line-height'] ) ) {
				$attrs['button_line_height'] = $typography['line-height'];
			}
			if ( ! empty( $typography['letter-spacing'] ) ) {
				$attrs['button_letter_spacing'] = $this->extract_size_value( $typography['letter-spacing'] );
			}
		}

		if ( ! empty( $settings['button_padding'] ) ) {
			$padding = $settings['button_padding'];
			if ( ! empty( $padding['top'] ) ) {
				$attrs['button_padding_top'] = $this->extract_size_value( $padding['top'] );
			}
			if ( ! empty( $padding['right'] ) ) {
				$attrs['button_padding_right'] = $this->extract_size_value( $padding['right'] );
			}
			if ( ! empty( $padding['bottom'] ) ) {
				$attrs['button_padding_bottom'] = $this->extract_size_value( $padding['bottom'] );
			}
			if ( ! empty( $padding['left'] ) ) {
				$attrs['button_padding_left'] = $this->extract_size_value( $padding['left'] );
			}
		}

		if ( ! empty( $settings['button_margin'] ) ) {
			$margin = $settings['button_margin'];
			if ( ! empty( $margin['top'] ) ) {
				$attrs['button_margin_top'] = $this->extract_size_value( $margin['top'] );
			}
			if ( ! empty( $margin['right'] ) ) {
				$attrs['button_margin_right'] = $this->extract_size_value( $margin['right'] );
			}
			if ( ! empty( $margin['bottom'] ) ) {
				$attrs['button_margin_bottom'] = $this->extract_size_value( $margin['bottom'] );
			}
			if ( ! empty( $margin['left'] ) ) {
				$attrs['button_margin_left'] = $this->extract_size_value( $margin['left'] );
			}
		}

		if ( ! empty( $settings['button_border'] ) ) {
			$border = $settings['button_border'];
			if ( ! empty( $border['color'] ) ) {
				$attrs['button_border_color'] = $this->extract_color_value( $border['color'] );
			}
			if ( ! empty( $border['width'] ) ) {
				$attrs['button_border_width'] = $this->extract_size_value( $border['width'] );
			}
			if ( ! empty( $border['style'] ) ) {
				$attrs['button_border_style'] = $border['style'];
			}
		}

		if ( ! empty( $settings['button_border_radius'] ) ) {
			$attrs['button_border_radius'] = $this->extract_dimensions_value( $settings['button_border_radius'], 'top' );
		}

		if ( ! empty( $settings['button_box_shadow'] ) ) {
			$attrs['button_box_shadow'] = $this->extract_box_shadow_value( $settings['button_box_shadow'] );
		}

		// Button hover states.
		if ( ! empty( $settings['button_hover_color'] ) ) {
			$attrs['button_hover_color'] = $this->extract_color_value( $settings['button_hover_color'] );
		}

		if ( ! empty( $settings['button_hover_background_color'] ) ) {
			$attrs['button_hover_background_color'] = $this->extract_color_value( $settings['button_hover_background_color'] );
		}

		if ( ! empty( $settings['button_hover_border_color'] ) ) {
			$attrs['button_hover_border_color'] = $this->extract_color_value( $settings['button_hover_border_color'] );
		}

		if ( ! empty( $settings['button_hover_box_shadow'] ) ) {
			$attrs['button_hover_box_shadow'] = $this->extract_box_shadow_value( $settings['button_hover_box_shadow'] );
		}

		// === PRIVACY TEXT STYLING ===
		if ( ! empty( $settings['privacy_text_color'] ) ) {
			$attrs['privacy_text_color'] = $this->extract_color_value( $settings['privacy_text_color'] ) . ' !important;';
		}

		if ( ! empty( $settings['privacy_text_typography'] ) ) {
			$typography = $settings['privacy_text_typography'];
			if ( ! empty( $typography['font-size'] ) ) {
				$attrs['privacy_text_font_size'] = $this->extract_size_value( $typography['font-size'] );
			}
			if ( ! empty( $typography['font-weight'] ) ) {
				$attrs['privacy_text_font_weight'] = $typography['font-weight'];
			}
			if ( ! empty( $typography['font-family'] ) ) {
				$attrs['privacy_text_font_family'] = $typography['font-family'];
			}
			if ( ! empty( $typography['text-transform'] ) ) {
				$attrs['privacy_text_transform'] = $typography['text-transform'];
			}
			if ( ! empty( $typography['text-decoration'] ) ) {
				$attrs['privacy_text_decoration'] = $typography['text-decoration'];
			}
			if ( ! empty( $typography['line-height'] ) ) {
				$attrs['privacy_text_line_height'] = $typography['line-height'];
			}
			if ( ! empty( $typography['letter-spacing'] ) ) {
				$attrs['privacy_text_letter_spacing'] = $this->extract_size_value( $typography['letter-spacing'] );
			}
		}

		// Privacy text margin.
		if ( ! empty( $settings['privacy_text_margin'] ) ) {
			$margin = $settings['privacy_text_margin'];
			if ( ! empty( $margin['top'] ) ) {
				$attrs['privacy_text_margin_top'] = $this->extract_size_value( $margin['top'] );
			}
			if ( ! empty( $margin['right'] ) ) {
				$attrs['privacy_text_margin_right'] = $this->extract_size_value( $margin['right'] );
			}
			if ( ! empty( $margin['bottom'] ) ) {
				$attrs['privacy_text_margin_bottom'] = $this->extract_size_value( $margin['bottom'] );
			}
			if ( ! empty( $margin['left'] ) ) {
				$attrs['privacy_text_margin_left'] = $this->extract_size_value( $margin['left'] );
			}
		}

		if ( ! empty( $settings['privacy_text_padding'] ) ) {
			$padding = $settings['privacy_text_padding'];
			if ( ! empty( $padding['top'] ) ) {
				$attrs['privacy_text_padding_top'] = $this->extract_size_value( $padding['top'] );
			}
			if ( ! empty( $padding['right'] ) ) {
				$attrs['privacy_text_padding_right'] = $this->extract_size_value( $padding['right'] );
			}
			if ( ! empty( $padding['bottom'] ) ) {
				$attrs['privacy_text_padding_bottom'] = $this->extract_size_value( $padding['bottom'] );
			}
			if ( ! empty( $padding['left'] ) ) {
				$attrs['privacy_text_padding_left'] = $this->extract_size_value( $padding['left'] );
			}
		}

		// === CHECKOUT BOX STYLING ===
		if ( ! empty( $settings['checkout_box_background_color'] ) ) {
			$attrs['checkout_box_background_color'] = $this->extract_color_value( $settings['checkout_box_background_color'] ) . ' !important;';
		}

		if ( ! empty( $settings['checkout_box_padding'] ) ) {
			$padding = $settings['checkout_box_padding'];
			if ( ! empty( $padding['top'] ) ) {
				$attrs['checkout_box_padding_top'] = $this->extract_size_value( $padding['top'] );
			}
			if ( ! empty( $padding['right'] ) ) {
				$attrs['checkout_box_padding_right'] = $this->extract_size_value( $padding['right'] );
			}
			if ( ! empty( $padding['bottom'] ) ) {
				$attrs['checkout_box_padding_bottom'] = $this->extract_size_value( $padding['bottom'] );
			}
			if ( ! empty( $padding['left'] ) ) {
				$attrs['checkout_box_padding_left'] = $this->extract_size_value( $padding['left'] );
			}
		}

		if ( ! empty( $settings['checkout_box_margin'] ) ) {
			$margin = $settings['checkout_box_margin'];
			if ( ! empty( $margin['top'] ) ) {
				$attrs['checkout_box_margin_top'] = $this->extract_size_value( $margin['top'] );
			}
			if ( ! empty( $margin['right'] ) ) {
				$attrs['checkout_box_margin_right'] = $this->extract_size_value( $margin['right'] );
			}
			if ( ! empty( $margin['bottom'] ) ) {
				$attrs['checkout_box_margin_bottom'] = $this->extract_size_value( $margin['bottom'] );
			}
			if ( ! empty( $margin['left'] ) ) {
				$attrs['checkout_box_margin_left'] = $this->extract_size_value( $margin['left'] );
			}
		}

		if ( ! empty( $settings['checkout_box_border'] ) ) {
			$border = $settings['checkout_box_border'];
			if ( ! empty( $border['color'] ) ) {
				$attrs['checkout_box_border_color'] = $this->extract_color_value( $border['color'] );
			}
			if ( ! empty( $border['width'] ) ) {
				$attrs['checkout_box_border_width'] = $this->extract_size_value( $border['width'] );
			}
			if ( ! empty( $border['style'] ) ) {
				$attrs['checkout_box_border_style'] = $border['style'];
			}
		}

		if ( ! empty( $settings['checkout_box_border_radius'] ) ) {
			$attrs['checkout_box_border_radius'] = $this->extract_dimensions_value( $settings['checkout_box_border_radius'], 'top' );
		}

		// Checkout box shadow.
		if ( ! empty( $settings['checkout_box_box_shadow'] ) ) {
			$attrs['checkout_box_box_shadow'] = $this->extract_box_shadow_value( $settings['checkout_box_box_shadow'] );
		}

		// === ORDER SUMMARY STYLING ===
		if ( ! empty( $settings['order_summary_background_color'] ) ) {
			$attrs['order_summary_background_color'] = $this->extract_color_value( $settings['order_summary_background_color'] );
		}

		if ( ! empty( $settings['order_summary_padding'] ) ) {
			$padding = $settings['order_summary_padding'];
			if ( ! empty( $padding['top'] ) ) {
				$attrs['order_summary_padding_top'] = $this->extract_size_value( $padding['top'] );
			}
			if ( ! empty( $padding['right'] ) ) {
				$attrs['order_summary_padding_right'] = $this->extract_size_value( $padding['right'] );
			}
			if ( ! empty( $padding['bottom'] ) ) {
				$attrs['order_summary_padding_bottom'] = $this->extract_size_value( $padding['bottom'] );
			}
			if ( ! empty( $padding['left'] ) ) {
				$attrs['order_summary_padding_left'] = $this->extract_size_value( $padding['left'] );
			}
		}

		if ( ! empty( $settings['order_summary_margin'] ) ) {
			$margin = $settings['order_summary_margin'];
			if ( ! empty( $margin['top'] ) ) {
				$attrs['order_summary_margin_top'] = $this->extract_size_value( $margin['top'] );
			}
			if ( ! empty( $margin['right'] ) ) {
				$attrs['order_summary_margin_right'] = $this->extract_size_value( $margin['right'] );
			}
			if ( ! empty( $margin['bottom'] ) ) {
				$attrs['order_summary_margin_bottom'] = $this->extract_size_value( $margin['bottom'] );
			}
			if ( ! empty( $margin['left'] ) ) {
				$attrs['order_summary_margin_left'] = $this->extract_size_value( $margin['left'] );
			}
		}

		if ( ! empty( $settings['order_summary_border'] ) ) {
			$border = $settings['order_summary_border'];
			if ( ! empty( $border['color'] ) ) {
				$attrs['order_summary_border_color'] = $this->extract_color_value( $border['color'] );
			}
			if ( ! empty( $border['width'] ) ) {
				$attrs['order_summary_border_width'] = $this->extract_size_value( $border['width'] );
			}
			if ( ! empty( $border['style'] ) ) {
				$attrs['order_summary_border_style'] = $border['style'];
			}
		}

		if ( ! empty( $settings['order_summary_border_radius'] ) ) {
			$attrs['order_summary_border_radius'] = $this->extract_dimensions_value( $settings['order_summary_border_radius'], 'top' );
		}

		// Order summary box shadow.
		if ( ! empty( $settings['order_summary_box_shadow'] ) ) {
			$attrs['order_summary_box_shadow'] = $this->extract_box_shadow_value( $settings['order_summary_box_shadow'] );
		}

		// === EMPTY CART STYLING ===
		if ( ! empty( $settings['empty_cart_color'] ) ) {
			$attrs['empty_cart_color'] = $this->extract_color_value( $settings['empty_cart_color'] );
		}

		if ( ! empty( $settings['empty_cart_background_color'] ) ) {
			$attrs['empty_cart_background_color'] = $this->extract_color_value( $settings['empty_cart_background_color'] );
		}

		if ( ! empty( $settings['empty_cart_typography'] ) ) {
			$typography = $settings['empty_cart_typography'];
			if ( ! empty( $typography['font-size'] ) ) {
				$attrs['empty_cart_font_size'] = $this->extract_size_value( $typography['font-size'] );
			}
			if ( ! empty( $typography['font-weight'] ) ) {
				$attrs['empty_cart_font_weight'] = $typography['font-weight'];
			}
			if ( ! empty( $typography['font-family'] ) ) {
				$attrs['empty_cart_font_family'] = $typography['font-family'];
			}
		}

		if ( ! empty( $settings['empty_cart_padding'] ) ) {
			$padding = $settings['empty_cart_padding'];
			if ( ! empty( $padding['top'] ) ) {
				$attrs['empty_cart_padding_top'] = $this->extract_size_value( $padding['top'] );
			}
			if ( ! empty( $padding['right'] ) ) {
				$attrs['empty_cart_padding_right'] = $this->extract_size_value( $padding['right'] );
			}
			if ( ! empty( $padding['bottom'] ) ) {
				$attrs['empty_cart_padding_bottom'] = $this->extract_size_value( $padding['bottom'] );
			}
			if ( ! empty( $padding['left'] ) ) {
				$attrs['empty_cart_padding_left'] = $this->extract_size_value( $padding['left'] );
			}
		}

		if ( ! empty( $settings['empty_cart_margin'] ) ) {
			$margin = $settings['empty_cart_margin'];
			if ( ! empty( $margin['top'] ) ) {
				$attrs['empty_cart_margin_top'] = $this->extract_size_value( $margin['top'] );
			}
			if ( ! empty( $margin['right'] ) ) {
				$attrs['empty_cart_margin_right'] = $this->extract_size_value( $margin['right'] );
			}
			if ( ! empty( $margin['bottom'] ) ) {
				$attrs['empty_cart_margin_bottom'] = $this->extract_size_value( $margin['bottom'] );
			}
			if ( ! empty( $margin['left'] ) ) {
				$attrs['empty_cart_margin_left'] = $this->extract_size_value( $margin['left'] );
			}
		}

		if ( ! empty( $settings['empty_cart_text_align'] ) ) {
			$attrs['empty_cart_text_align'] = $settings['empty_cart_text_align'];
		}

		if ( ! empty( $settings['empty_cart_border'] ) ) {
			$border = $settings['empty_cart_border'];
			if ( ! empty( $border['color'] ) ) {
				$attrs['empty_cart_border_color'] = $this->extract_color_value( $border['color'] );
			}
			if ( ! empty( $border['width'] ) ) {
				$attrs['empty_cart_border_width'] = $this->extract_size_value( $border['width'] );
			}
			if ( ! empty( $border['style'] ) ) {
				$attrs['empty_cart_border_style'] = $border['style'];
			}
		}

		if ( ! empty( $settings['empty_cart_border_radius'] ) ) {
			$attrs['empty_cart_border_radius'] = $this->extract_dimensions_value( $settings['empty_cart_border_radius'], 'top' );
		}

		// === ERROR MESSAGE STYLING ===
		if ( ! empty( $settings['error_message_color'] ) ) {
			$attrs['error_message_color'] = $this->extract_color_value( $settings['error_message_color'] );
		}

		if ( ! empty( $settings['error_message_background'] ) ) {
			$attrs['error_message_background'] = $this->extract_color_value( $settings['error_message_background'] );
		}

		if ( ! empty( $settings['error_message_typography'] ) ) {
			$typography = $settings['error_message_typography'];
			if ( ! empty( $typography['font-size'] ) ) {
				$attrs['error_message_font_size'] = $this->extract_size_value( $typography['font-size'] );
			}
			if ( ! empty( $typography['font-weight'] ) ) {
				$attrs['error_message_font_weight'] = $typography['font-weight'];
			}
			if ( ! empty( $typography['font-family'] ) ) {
				$attrs['error_message_font_family'] = $typography['font-family'];
			}
		}

		if ( ! empty( $settings['error_message_padding'] ) ) {
			$padding = $settings['error_message_padding'];
			if ( ! empty( $padding['top'] ) ) {
				$attrs['error_message_padding_top'] = $this->extract_size_value( $padding['top'] );
			}
			if ( ! empty( $padding['right'] ) ) {
				$attrs['error_message_padding_right'] = $this->extract_size_value( $padding['right'] );
			}
			if ( ! empty( $padding['bottom'] ) ) {
				$attrs['error_message_padding_bottom'] = $this->extract_size_value( $padding['bottom'] );
			}
			if ( ! empty( $padding['left'] ) ) {
				$attrs['error_message_padding_left'] = $this->extract_size_value( $padding['left'] );
			}
		}

		if ( ! empty( $settings['error_message_margin'] ) ) {
			$margin = $settings['error_message_margin'];
			if ( ! empty( $margin['top'] ) ) {
				$attrs['error_message_margin_top'] = $this->extract_size_value( $margin['top'] );
			}
			if ( ! empty( $margin['right'] ) ) {
				$attrs['error_message_margin_right'] = $this->extract_size_value( $margin['right'] );
			}
			if ( ! empty( $margin['bottom'] ) ) {
				$attrs['error_message_margin_bottom'] = $this->extract_size_value( $margin['bottom'] );
			}
			if ( ! empty( $margin['left'] ) ) {
				$attrs['error_message_margin_left'] = $this->extract_size_value( $margin['left'] );
			}
		}

		if ( ! empty( $settings['error_message_border'] ) ) {
			$border = $settings['error_message_border'];
			if ( ! empty( $border['color'] ) ) {
				$attrs['error_message_border_color'] = $this->extract_color_value( $border['color'] );
			}
			if ( ! empty( $border['width'] ) ) {
				$attrs['error_message_border_width'] = $this->extract_size_value( $border['width'] );
			}
			if ( ! empty( $border['style'] ) ) {
				$attrs['error_message_border_style'] = $border['style'];
			}
		}

		if ( ! empty( $settings['error_message_border_radius'] ) ) {
			$attrs['error_message_border_radius'] = $this->extract_dimensions_value( $settings['error_message_border_radius'], 'top' );
		}

		// Layout type.
		if ( isset( $settings['layout_type'] ) ) {
			$attrs['layout_type'] = $settings['layout_type'];
		}

		return $attrs;
	}

	/**
	 * Extract color value from Bricks color format
	 *
	 * @param mixed $color Color value from Bricks.
	 * @return string Extracted color value
	 */
	private function extract_color_value( $color ) {
		if ( is_array( $color ) ) {
			// Bricks color format: array with 'hex', 'rgb', etc.
			if ( ! empty( $color['hex'] ) ) {
				return $color['hex'] . ' !important';
			}
			if ( ! empty( $color['rgb'] ) ) {
				return $color['rgb'] . ' !important';
			}
		}

		// Fallback for string color values.
		return is_string( $color ) ? $color . ' !important' : '';
	}

	/**
	 * Extract size value from Bricks size format
	 *
	 * @param mixed $size Size value from Bricks.
	 * @return string Extracted size with unit
	 */
	private function extract_size_value( $size ) {
		if ( is_array( $size ) ) {
			// Bricks size format: array with 'size' and 'unit'.
			$value     = ! empty( $size['size'] ) ? $size['size'] : '';
			$unit      = ! empty( $size['unit'] ) ? $size['unit'] : 'px';
			$important = '';
			// $important = ' !important';
			return $value . $unit . $important;
		}

		// Fallback for string size values.
		return is_string( $size ) ? $size . 'px !important' : '';
	}

	/**
	 * Extract dimensions value (for border-radius, etc.)
	 *
	 * @param mixed  $dimensions Dimensions from Bricks.
	 * @param string $side Which side to extract (top, right, bottom, left).
	 * @return string Extracted dimension value
	 */
	private function extract_dimensions_value( $dimensions, $side = 'top' ) {
		if ( is_array( $dimensions ) ) {
			if ( ! empty( $dimensions[ $side ] ) ) {
				return $this->extract_size_value( $dimensions[ $side ] );
			}
		}

		return is_string( $dimensions ) ? $dimensions . ' !important' : '';
	}

	/**
	 * Extract box shadow value from Bricks box-shadow format
	 *
	 * @param mixed $shadow Box shadow from Bricks.
	 * @return string CSS box-shadow value
	 */
	private function extract_box_shadow_value( $shadow ) {
		if ( is_array( $shadow ) ) {
			$h_offset = ! empty( $shadow['offsetX'] ) ? $this->extract_size_value( $shadow['offsetX'] ) : '0px';
			$v_offset = ! empty( $shadow['offsetY'] ) ? $this->extract_size_value( $shadow['offsetY'] ) : '0px';
			$blur     = ! empty( $shadow['blur'] ) ? $this->extract_size_value( $shadow['blur'] ) : '0px';
			$spread   = ! empty( $shadow['spread'] ) ? $this->extract_size_value( $shadow['spread'] ) : '0px';
			$color    = ! empty( $shadow['color'] ) ? $this->extract_color_value( $shadow['color'] ) : 'rgba(0,0,0,0.1)';
			$inset    = ! empty( $shadow['inset'] ) ? 'inset ' : '';

			return $inset . $h_offset . ' ' . $v_offset . ' ' . $blur . ' ' . $spread . ' ' . $color;
		}

		return is_string( $shadow ) ? $shadow : 'none';
	}
}
