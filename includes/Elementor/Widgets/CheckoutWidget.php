<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * OhMyLMS Checkout Widget for Elementor
 *
 * @package OhMyLMS\Elementor\Widgets
 * @since 1.0.0
 */

namespace OhMyLMS\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use OhMyLMS\Shortcodes\ShortCodeCheckout;
use function OhMyLMS\Commerce\ecommerce;

defined( 'ABSPATH' ) || exit;

/**
 * CheckoutWidget class
 */
class CheckoutWidget extends Widget_Base {

	/**
	 * Get widget name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'ohmylms-checkout';
	}

	/**
	 * Get widget title
	 *
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'OhMyLMS Checkout', 'ohmylms' );
	}

	/**
	 * Get widget icon
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-cart-medium';
	}

	/**
	 * Get widget categories
	 *
	 * @return array
	 */
	public function get_categories() {
		return array( 'ohmylms' );
	}

	/**
	 * Get widget keywords
	 *
	 * @return array
	 */
	public function get_keywords() {
		return array( 'checkout', 'cart', 'purchase', 'creator', 'lms' );
	}


	/**
	 * Get script dependencies
	 *
	 * @return array
	 */
	public function get_script_depends() {
		return array( 'ohmylms-frontend', 'ohmylms-checkout', 'ohmylms-tax-calculation', 'ohmylms-slick' );
	}

	/**
	 * Get style dependencies
	 *
	 * @return array
	 */
	public function get_style_depends() {
		return array( 'ohmylms-frontend', 'ohmylms-general' );
	}


	/**
	 * Register widget controls
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->register_content_controls();
		$this->register_title_style_controls();
		$this->register_input_style_controls();
		$this->register_button_style_controls();
		$this->register_privacy_style_controls();
		$this->register_checkout_box_style_controls();
		$this->register_order_summary_style_controls();
		$this->register_empty_cart_controls();
	}

	/**
	 * Register empty cart styling controls
	 *
	 * @return void
	 */
	private function register_empty_cart_controls() {
		$this->start_controls_section(
			'empty_cart_section',
			array(
				'label'     => esc_html__( 'Empty Cart Message', 'ohmylms' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'show_empty_cart_message' => 'yes',
				),
			)
		);

		$this->add_control(
			'empty_cart_background_color',
			array(
				'label'     => esc_html__( 'Background Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#f8f9fa',
				'selectors' => array(
					'{{WRAPPER}} .ohmylms-empty-cart-message' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'empty_cart_padding',
			array(
				'label'      => esc_html__( 'Padding', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'default'    => array(
					'top'    => '40',
					'right'  => '30',
					'bottom' => '40',
					'left'   => '30',
					'unit'   => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .ohmylms-empty-cart-message' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'empty_cart_margin',
			array(
				'label'      => esc_html__( 'Margin', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ohmylms-empty-cart-message' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'empty_cart_text_align',
			array(
				'label'     => esc_html__( 'Text Alignment', 'ohmylms' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array(
						'title' => esc_html__( 'Left', 'ohmylms' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Center', 'ohmylms' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => esc_html__( 'Right', 'ohmylms' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'default'   => 'center',
				'selectors' => array(
					'{{WRAPPER}} .ohmylms-empty-cart-message' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'empty_cart_border',
				'selector' => '{{WRAPPER}} .ohmylms-empty-cart-message',
			)
		);

		$this->add_responsive_control(
			'empty_cart_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ohmylms-empty-cart-message' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		// Title styling.
		$this->add_control(
			'empty_cart_title_heading',
			array(
				'label'     => esc_html__( 'Title', 'ohmylms' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'empty_cart_title_color',
			array(
				'label'     => esc_html__( 'Title Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => 'var(--ohmylms-heading-color)',
				'selectors' => array(
					'{{WRAPPER}} .ohmylms-empty-cart-message h3' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'empty_cart_title_typography',
				'selector' => '{{WRAPPER}} .ohmylms-empty-cart-message h3',
			)
		);

		// Message styling.
		$this->add_control(
			'empty_cart_message_heading',
			array(
				'label'     => esc_html__( 'Message', 'ohmylms' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'empty_cart_message_color',
			array(
				'label'     => esc_html__( 'Message Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => 'var(--ohmylms-text-color)',
				'selectors' => array(
					'{{WRAPPER}} .ohmylms-empty-cart-message p' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'empty_cart_message_typography',
				'selector' => '{{WRAPPER}} .ohmylms-empty-cart-message p',
			)
		);

		// Button styling.
		$this->add_control(
			'empty_cart_button_heading',
			array(
				'label'     => esc_html__( 'Browse Courses Button', 'ohmylms' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->start_controls_tabs( 'empty_cart_button_style_tabs' );

		$this->start_controls_tab(
			'empty_cart_button_normal',
			array(
				'label' => esc_html__( 'Normal', 'ohmylms' ),
			)
		);

		$this->add_control(
			'empty_cart_button_color',
			array(
				'label'     => esc_html__( 'Text Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#FFF',
				'selectors' => array(
					'{{WRAPPER}} .ohmylms-browse-courses-btn' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'empty_cart_button_background_color',
			array(
				'label'     => esc_html__( 'Background Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#6e42d3',
				'selectors' => array(
					'{{WRAPPER}} .ohmylms-browse-courses-btn' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'empty_cart_button_typography',
				'selector' => '{{WRAPPER}} .ohmylms-browse-courses-btn',
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'empty_cart_button_border',
				'selector' => '{{WRAPPER}} .ohmylms-browse-courses-btn',
			)
		);

		$this->add_responsive_control(
			'empty_cart_button_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ohmylms-browse-courses-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'empty_cart_button_padding',
			array(
				'label'      => esc_html__( 'Padding', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ohmylms-browse-courses-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'empty_cart_button_hover',
			array(
				'label' => esc_html__( 'Hover', 'ohmylms' ),
			)
		);

		$this->add_control(
			'empty_cart_button_hover_color',
			array(
				'label'     => esc_html__( 'Text Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ohmylms-browse-courses-btn:hover' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'empty_cart_button_hover_background_color',
			array(
				'label'     => esc_html__( 'Background Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ohmylms-browse-courses-btn:hover' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'empty_cart_button_hover_border_color',
			array(
				'label'     => esc_html__( 'Border Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ohmylms-browse-courses-btn:hover' => 'border-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/**
	 * Register content controls
	 *
	 * @return void
	 */
	private function register_content_controls() {
		$this->start_controls_section(
			'content_section',
			array(
				'label' => esc_html__( 'Content Settings', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_empty_cart_message',
			array(
				'label'        => esc_html__( 'Show Empty Cart Message', 'ohmylms' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Show', 'ohmylms' ),
				'label_off'    => esc_html__( 'Hide', 'ohmylms' ),
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => esc_html__( 'Show a message when the cart is empty with a link to browse courses.', 'ohmylms' ),
			)
		);

		$this->add_control(
			'empty_cart_title',
			array(
				'label'     => esc_html__( 'Empty Cart Title', 'ohmylms' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => esc_html__( 'Your cart is empty', 'ohmylms' ),
				'condition' => array(
					'show_empty_cart_message' => 'yes',
				),
			)
		);

		$this->add_control(
			'empty_cart_message',
			array(
				'label'     => esc_html__( 'Empty Cart Message', 'ohmylms' ),
				'type'      => Controls_Manager::TEXTAREA,
				'default'   => esc_html__( 'Add some courses to your cart to proceed with checkout.', 'ohmylms' ),
				'condition' => array(
					'show_empty_cart_message' => 'yes',
				),
			)
		);

		$this->add_control(
			'browse_courses_text',
			array(
				'label'     => esc_html__( 'Browse Courses Button Text', 'ohmylms' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => esc_html__( 'Browse Courses', 'ohmylms' ),
				'condition' => array(
					'show_empty_cart_message' => 'yes',
				),
			)
		);

		$this->add_control(
			'layout_type_divider',
			array(
				'type' => Controls_Manager::DIVIDER,
			)
		);

		$this->add_control(
			'layout_type',
			array(
				'label'       => esc_html__( 'Layout Type', 'ohmylms' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => '',
				'options'     => array(
					''        => esc_html__( 'Use Global Setting', 'ohmylms' ),
					'default' => esc_html__( 'Default Layout (with header/footer)', 'ohmylms' ),
					'canvas'  => esc_html__( 'Canvas Layout (no header/footer)', 'ohmylms' ),
				),
				'description' => esc_html__( 'Choose the layout type for this checkout page. Canvas layout removes the header and footer for a focused checkout experience.', 'ohmylms' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register title styling controls
	 *
	 * @return void
	 */
	private function register_title_style_controls() {
		$this->start_controls_section(
			'title_section',
			array(
				'label' => esc_html__( 'Title Typography', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'title_color',
			array(
				'label'     => esc_html__( 'Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => 'var(--ohmylms-heading-color)',
				'selectors' => array(
					'{{WRAPPER}} .ohmylms-checkout-title' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'           => 'title_typography',
				'selector'       => '{{WRAPPER}} .ohmylms-checkout-title',
				'fields_options' => array(
					'typography'      => array(
						'default' => 'yes',
					),
					'font_size'       => array(
						'default' => array(
							'size' => 22,
							'unit' => 'px',
						),
					),
					'font_weight'     => array(
						'default' => '600',
					),
					'line_height'     => array(
						'default' => array(
							'size' => 1.3,
							'unit' => 'em',
						),
					),
					'text_transform'  => array(
						'default' => 'none',
					),
					'text_decoration' => array(
						'default' => 'none',
					),
					'letter_spacing'  => array(
						'default' => array(
							'size' => 0,
							'unit' => 'px',
						),
					),
				),
			)
		);

		$this->add_responsive_control(
			'title_margin',
			array(
				'label'      => esc_html__( 'Margin', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ohmylms-checkout-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register input styling controls
	 *
	 * @return void
	 */
	private function register_input_style_controls() {
		$this->start_controls_section(
			'input_section',
			array(
				'label' => esc_html__( 'Input Styling', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		// Input Labels Group.
		$this->add_control(
			'input_label_heading',
			array(
				'label' => esc_html__( 'Input Labels', 'ohmylms' ),
				'type'  => Controls_Manager::HEADING,
			)
		);

		$this->add_control(
			'input_label_color',
			array(
				'label'     => esc_html__( 'Label Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => 'var(--ohmylms-heading-color)',
				'selectors' => array(
					'{{WRAPPER}} .ohmylms-form-row label' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'           => 'input_label_typography',
				'selector'       => '{{WRAPPER}} .ohmylms-form-row label',
				'fields_options' => array(
					'typography'  => array(
						'default' => 'yes',
					),
					'font_size'   => array(
						'default' => array(
							'size' => 14,
							'unit' => 'px',
						),
					),
					'font_weight' => array(
						'default' => '500',
					),
				),
			)
		);

		// Input Fields Group.
		$this->add_control(
			'input_field_heading',
			array(
				'label'     => esc_html__( 'Input Fields', 'ohmylms' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'input_color',
			array(
				'label'     => esc_html__( 'Text Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => 'var(--ohmylms-heading-color)',
				'selectors' => array(
					'{{WRAPPER}} .ohmylms-input-text, {{WRAPPER}} .ohmylms-input-select' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'input_background_color',
			array(
				'label'     => esc_html__( 'Background Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#FFF',
				'selectors' => array(
					'{{WRAPPER}} .ohmylms-input-text, {{WRAPPER}} .ohmylms-input-select' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'           => 'input_typography',
				'selector'       => '{{WRAPPER}} .ohmylms-input-text, {{WRAPPER}} .ohmylms-input-select',
				'fields_options' => array(
					'typography'  => array(
						'default' => 'yes',
					),
					'font_size'   => array(
						'default' => array(
							'size' => 14,
							'unit' => 'px',
						),
					),
					'font_weight' => array(
						'default' => '400',
					),
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'           => 'input_border',
				'selector'       => '{{WRAPPER}} .ohmylms-input-text, {{WRAPPER}} .ohmylms-input-select',
				'fields_options' => array(
					'border' => array(
						'default' => 'solid',
					),
					'width'  => array(
						'default' => array(
							'top'    => 1,
							'right'  => 1,
							'bottom' => 1,
							'left'   => 1,
							'unit'   => 'px',
						),
					),
					'color'  => array(
						'default' => '#EBEBEF',
					),
				),
			)
		);

		$this->add_responsive_control(
			'input_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'default'    => array(
					'top'    => 10,
					'right'  => 10,
					'bottom' => 10,
					'left'   => 10,
					'unit'   => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .ohmylms-input-text, {{WRAPPER}} .ohmylms-input-select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'input_padding',
			array(
				'label'      => esc_html__( 'Padding', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ohmylms-input-text, {{WRAPPER}} .ohmylms-input-select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register button styling controls
	 *
	 * @return void
	 */
	private function register_button_style_controls() {
		$this->start_controls_section(
			'button_section',
			array(
				'label' => esc_html__( 'Checkout Button', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->start_controls_tabs( 'button_style_tabs' );

		// Normal state.
		$this->start_controls_tab(
			'button_normal',
			array(
				'label' => esc_html__( 'Normal', 'ohmylms' ),
			)
		);

		$this->add_control(
			'button_color',
			array(
				'label'     => esc_html__( 'Text Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#FFF',
				'selectors' => array(
					'{{WRAPPER}} .ohmylms-place-order-button' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_control(
			'button_background_color',
			array(
				'label'     => esc_html__( 'Background Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#6e42d3',
				'selectors' => array(
					'{{WRAPPER}} .ohmylms-place-order-button' => 'background-color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'           => 'button_typography',
				'selector'       => '{{WRAPPER}} .ohmylms-place-order-button',
				'fields_options' => array(
					'typography'      => array(
						'default' => 'yes',
					),
					'font_size'       => array(
						'default' => array(
							'size' => 18,
							'unit' => 'px',
						),
					),
					'font_weight'     => array(
						'default' => '700',
					),
					'line_height'     => array(
						'default' => array(
							'size' => 1.2,
							'unit' => 'em',
						),
					),
					'text_transform'  => array(
						'default' => 'none',
					),
					'text_decoration' => array(
						'default' => 'none',
					),
					'letter_spacing'  => array(
						'default' => array(
							'size' => 0,
							'unit' => 'px',
						),
					),
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'           => 'button_border',
				'selector'       => '{{WRAPPER}} .ohmylms-place-order-button',
				'fields_options' => array(
					'border' => array(
						'default' => 'solid',
					),
					'width'  => array(
						'default' => array(
							'top'    => 1,
							'right'  => 1,
							'bottom' => 1,
							'left'   => 1,
							'unit'   => 'px',
						),
					),
					'color'  => array(
						'default' => '#6e42d3',
					),
				),
			)
		);

		$this->add_responsive_control(
			'button_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'default'    => array(
					'top'    => 8,
					'right'  => 8,
					'bottom' => 8,
					'left'   => 8,
					'unit'   => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .ohmylms-place-order-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'button_padding',
			array(
				'label'      => esc_html__( 'Padding', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'default'    => array(
					'top'    => 16,
					'right'  => 24,
					'bottom' => 16,
					'left'   => 24,
					'unit'   => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .ohmylms-place-order-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'button_margin',
			array(
				'label'      => esc_html__( 'Margin', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'default'    => array(
					'top'    => 0,
					'right'  => 0,
					'bottom' => 0,
					'left'   => 0,
					'unit'   => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .ohmylms-place-order-button' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'button_box_shadow',
				'selector' => '{{WRAPPER}} .ohmylms-place-order-button',
			)
		);

		$this->end_controls_tab();

		// Hover state.
		$this->start_controls_tab(
			'button_hover',
			array(
				'label' => esc_html__( 'Hover', 'ohmylms' ),
			)
		);

		$this->add_control(
			'button_hover_color',
			array(
				'label'     => esc_html__( 'Text Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#6e42d3',
				'selectors' => array(
					'{{WRAPPER}} .ohmylms-place-order-button:hover' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_hover_background_color',
			array(
				'label'     => esc_html__( 'Background Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => 'transparent',
				'selectors' => array(
					'{{WRAPPER}} .ohmylms-place-order-button:hover' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_hover_border_color',
			array(
				'label'     => esc_html__( 'Border Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#6e42d3',
				'selectors' => array(
					'{{WRAPPER}} .ohmylms-place-order-button:hover' => 'border-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'button_hover_box_shadow',
				'selector' => '{{WRAPPER}} .ohmylms-place-order-button:hover',
			)
		);

		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/**
	 * Register privacy text controls
	 *
	 * @return void
	 */
	private function register_privacy_style_controls() {
		$this->start_controls_section(
			'privacy_section',
			array(
				'label' => esc_html__( 'Privacy Text', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'privacy_text_color',
			array(
				'label'     => esc_html__( 'Text Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => 'var(--ohmylms-heading-color)',
				'selectors' => array(
					'{{WRAPPER}} .ohmylms-terms-and-conditions-wrapper' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'           => 'privacy_text_typography',
				'selector'       => '{{WRAPPER}} .ohmylms-terms-and-conditions-wrapper',
				'fields_options' => array(
					'typography'      => array(
						'default' => 'yes',
					),
					'font_size'       => array(
						'default' => array(
							'size' => 14,
							'unit' => 'px',
						),
					),
					'font_weight'     => array(
						'default' => '400',
					),
					'line_height'     => array(
						'default' => array(
							'size' => 1.3,
							'unit' => 'em',
						),
					),
					'text_transform'  => array(
						'default' => 'none',
					),
					'text_decoration' => array(
						'default' => 'none',
					),
					'letter_spacing'  => array(
						'default' => array(
							'size' => 0,
							'unit' => 'px',
						),
					),
				),
			)
		);

		$this->add_responsive_control(
			'privacy_text_margin',
			array(
				'label'      => esc_html__( 'Margin', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ohmylms-terms-and-conditions-wrapper' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register checkout box controls
	 *
	 * @return void
	 */
	private function register_checkout_box_style_controls() {
		$this->start_controls_section(
			'checkout_box_section',
			array(
				'label' => esc_html__( 'Checkout Form Container', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'checkout_box_background_color',
			array(
				'label'     => esc_html__( 'Background Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#fff',
				'selectors' => array(
					'{{WRAPPER}} .ohmylms-checkout-form-left' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'           => 'checkout_box_border',
				'selector'       => '{{WRAPPER}} .ohmylms-checkout-form-left',
				'fields_options' => array(
					'border' => array(
						'default' => 'solid',
					),
					'width'  => array(
						'default' => array(
							'top'    => 0,
							'right'  => 0,
							'bottom' => 0,
							'left'   => 0,
							'unit'   => 'px',
						),
					),
					'color'  => array(
						'default' => 'transparent',
					),
				),
			)
		);

		$this->add_responsive_control(
			'checkout_box_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'default'    => array(
					'top'    => 0,
					'right'  => 0,
					'bottom' => 0,
					'left'   => 0,
					'unit'   => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .ohmylms-checkout-form-left' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'checkout_box_padding',
			array(
				'label'      => esc_html__( 'Padding', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'default'    => array(
					'top'    => 30,
					'right'  => 50,
					'bottom' => 30,
					'left'   => 0,
					'unit'   => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .ohmylms-checkout-form-left' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'checkout_box_margin',
			array(
				'label'      => esc_html__( 'Margin', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'default'    => array(
					'top'    => 0,
					'right'  => 0,
					'bottom' => 0,
					'left'   => 0,
					'unit'   => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .ohmylms-checkout-form-left' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'checkout_box_shadow',
				'selector' => '{{WRAPPER}} .ohmylms-checkout-form-left',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register order summary controls
	 *
	 * @return void
	 */
	private function register_order_summary_style_controls() {
		$this->start_controls_section(
			'order_summary_section',
			array(
				'label' => esc_html__( 'Order Summary Container', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'order_summary_background_color',
			array(
				'label'     => esc_html__( 'Background Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .ohmylms-checkout-form-right' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'           => 'order_summary_border',
				'selector'       => '{{WRAPPER}} .ohmylms-checkout-form-right',
				'fields_options' => array(
					'border' => array(
						'default' => '',
					),
					'width'  => array(
						'default' => array(
							'top'    => '',
							'right'  => '',
							'bottom' => '',
							'left'   => '',
							'unit'   => 'px',
						),
					),
					'color'  => array(
						'default' => '',
					),
				),
			)
		);

		$this->add_responsive_control(
			'order_summary_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'default'    => array(
					'top'    => '',
					'right'  => '',
					'bottom' => '',
					'left'   => '',
					'unit'   => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .ohmylms-checkout-form-right' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'order_summary_padding',
			array(
				'label'      => esc_html__( 'Padding', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'default'    => array(
					'top'    => 30,
					'right'  => 0,
					'bottom' => 30,
					'left'   => 30,
					'unit'   => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .ohmylms-checkout-form-right' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'order_summary_margin',
			array(
				'label'      => esc_html__( 'Margin', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'default'    => array(
					'top'    => 0,
					'right'  => 0,
					'bottom' => 0,
					'left'   => 0,
					'unit'   => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .ohmylms-checkout-form-right' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'order_summary_box_shadow',
				'selector' => '{{WRAPPER}} .ohmylms-checkout-form-right',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render widget output
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		// Convert Elementor settings to shortcode attributes.
		$shortcode_attrs = $this->convert_settings_to_shortcode_attrs( $settings );

		// Use the same wrapper class as shortcode for consistency
		// The shortcode handles all styling through its built-in CSS system with ohmylms-checkout-custom-styles.
		echo '<div class="ohmylms-page ohmylms-checkout">';
		echo '<div class="ohmylms">';

		// Check if we're in Elementor edit mode.
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			$this->render_edit_mode();
		} else {
			// Set preview mode to false for frontend.
			add_filter( 'ohmylms_elementor_preview_mode', '__return_false' );
			ShortCodeCheckout::output( $shortcode_attrs );
			remove_filter( 'ohmylms_elementor_preview_mode', '__return_false' );
		}

		echo '</div>';
		echo '</div>';
	}

	/**
	 * Check if checkout should be displayed based on cart status
	 *
	 * @return bool
	 */
	private function should_display_checkout() {
		// Check if cart system is available.
		if ( is_null( ecommerce()->cart ) ) {
			return false;
		}

		// Check if cart has items.
		if ( ! ecommerce()->cart->is_empty() ) {
			return true;
		}
		return false;
	}

	/**
	 * Render empty cart message
	 *
	 * @return void
	 */
	private function render_empty_cart_message() {
		$settings = $this->get_settings_for_display();

		// Always show empty cart message by default (even if setting is not explicitly set).
		$show_message = isset( $settings['show_empty_cart_message'] ) ? $settings['show_empty_cart_message'] : 'yes';

		if ( 'yes' !== $show_message ) {
			// If message is disabled, show a simple notice for administrators.
			if ( current_user_can( 'edit_posts' ) ) {
				echo '<div class="ohmylms-admin-notice">';
				echo '<p><em>' . esc_html__( 'Empty cart message is disabled. Enable it in widget settings to show a message when cart is empty.', 'ohmylms' ) . '</em></p>';
				echo '</div>';
			}
			return;
		}

		$archive_page_id  = get_option( 'ohmylms_course_page_id', 0 );
		$archive_page_url = home_url();
		if ( $archive_page_id ) {
			$archive_page_url = get_permalink( $archive_page_id );
		}

		$empty_cart_title    = ! empty( $settings['empty_cart_title'] ) ? $settings['empty_cart_title'] : esc_html__( 'Your cart is empty', 'ohmylms' );
		$empty_cart_message  = ! empty( $settings['empty_cart_message'] ) ? $settings['empty_cart_message'] : esc_html__( 'Add some courses to your cart to proceed with checkout.', 'ohmylms' );
		$browse_courses_text = ! empty( $settings['browse_courses_text'] ) ? $settings['browse_courses_text'] : esc_html__( 'Browse Courses', 'ohmylms' );

		echo '<div class="ohmylms-empty-cart-message">';
		echo '<h3>' . esc_html( $empty_cart_title ) . '</h3>';
		echo '<p>' . esc_html( $empty_cart_message ) . '</p>';
		echo '<a href="' . esc_url( $archive_page_url ) . '" class="ohmylms-browse-courses-btn">';
		echo esc_html( $browse_courses_text );
		echo '</a>';
		echo '</div>';
	}

	/**
	 * Render widget in edit mode (show actual form for styling)
	 *
	 * Always uses the ShortCodeCheckout class with preview mode enabled.
	 * This ensures 100% consistency between widget and shortcode output,
	 * and allows all Elementor styling controls to work on real elements.
	 *
	 * @return void
	 */
	private function render_edit_mode() {
		$settings        = $this->get_settings_for_display();
		$shortcode_attrs = $this->convert_settings_to_shortcode_attrs( $settings );

		echo '<div class="ohmylms-elementor-edit-mode">';
		echo '<div class="ohmylms-edit-mode-notice">';
		echo '<small>' . esc_html__( 'Preview Mode: This is how the checkout will appear to users with items in their cart.', 'ohmylms' ) . '</small>';
		echo '</div>';

		// Always render the shortcode in edit mode with preview mode enabled
		// This ensures consistency and proper styling preview.
		add_filter( 'ohmylms_elementor_preview_mode', '__return_true' );
		ShortCodeCheckout::output( $shortcode_attrs );
		remove_filter( 'ohmylms_elementor_preview_mode', '__return_true' );

		echo '</div>';
	}

	/**
	 * Convert Elementor settings to shortcode attributes
	 *
	 * @param array $settings Elementor settings.
	 * @return array Shortcode attributes
	 */
	private function convert_settings_to_shortcode_attrs( $settings ) {
		// Start with default attributes from shortcode (ensures consistent defaults).
		$attrs = array(
			// Title defaults.
			'title_color'                    => 'var(--ohmylms-heading-color)',
			'title_font_size'                => '22px',
			'title_font_weight'              => '600',
			'title_font_family'              => '',
			'title_text_transform'           => 'none',
			'title_text_decoration'          => 'none',
			'title_line_height'              => '1.3',
			'title_letter_spacing'           => '0',

			// Input label defaults.
			'input_label_color'              => 'var(--ohmylms-heading-color)',
			'input_label_font_size'          => '14px',
			'input_label_font_weight'        => '500',
			'input_label_font_family'        => '',

			// Input field defaults.
			'input_font_size'                => '14px',
			'input_font_weight'              => '400',
			'input_color'                    => 'var(--ohmylms-heading-color)',
			'input_font_family'              => '',
			'input_background_color'         => '#FFF',
			'input_border_color'             => '#EBEBEF',
			'input_border_width'             => '1px',
			'input_border_style'             => 'solid',
			'input_border_radius'            => '10px',

			// Button defaults.
			'button_background_color'        => '#6e42d3',
			'button_color'                   => '#FFF',
			'button_font_size'               => '18px',
			'button_font_weight'             => '700',
			'button_font_family'             => '',
			'button_text_transform'          => 'none',
			'button_text_decoration'         => 'none',
			'button_line_height'             => '1.2',
			'button_letter_spacing'          => '0',
			'button_padding_top'             => '16px',
			'button_padding_right'           => '24px',
			'button_padding_bottom'          => '16px',
			'button_padding_left'            => '24px',
			'button_margin_top'              => '0',
			'button_margin_right'            => '0',
			'button_margin_bottom'           => '0',
			'button_margin_left'             => '0',
			'button_border_radius'           => '8px',
			'button_border_color'            => '#6e42d3',
			'button_border_width'            => '1px',
			'button_border_style'            => 'solid',
			'button_box_shadow'              => 'none',

			// Button hover defaults.
			'button_hover_background_color'  => 'transparent',
			'button_hover_color'             => '#6e42d3',
			'button_hover_border_color'      => '#6e42d3',
			'button_hover_box_shadow'        => 'none',

			// Privacy text defaults.
			'privacy_text_color'             => 'var(--ohmylms-heading-color)',
			'privacy_text_font_size'         => '14px',
			'privacy_text_font_weight'       => '400',
			'privacy_text_font_family'       => '',
			'privacy_text_transform'         => 'none',
			'privacy_text_decoration'        => 'none',
			'privacy_text_line_height'       => '1.3',
			'privacy_text_letter_spacing'    => '0',

			// Checkout box defaults.
			'checkout_box_background_color'  => '#fff',
			'checkout_box_padding_top'       => '30px',
			'checkout_box_padding_right'     => '50px',
			'checkout_box_padding_bottom'    => '30px',
			'checkout_box_padding_left'      => '0',
			'checkout_box_margin_top'        => '0',
			'checkout_box_margin_right'      => '0',
			'checkout_box_margin_bottom'     => '0',
			'checkout_box_margin_left'       => '0',
			'checkout_box_border_color'      => 'transparent',
			'checkout_box_border_width'      => '0',
			'checkout_box_border_style'      => 'solid',
			'checkout_box_border_radius'     => '0',

			// Order summary defaults.
			'order_summary_background_color' => '',
			'order_summary_padding_top'      => '30px',
			'order_summary_padding_right'    => '0',
			'order_summary_padding_bottom'   => '30px',
			'order_summary_padding_left'     => '30px',
			'order_summary_margin_top'       => '0',
			'order_summary_margin_right'     => '0',
			'order_summary_margin_bottom'    => '0',
			'order_summary_margin_left'      => '0',
			'order_summary_border_color'     => '',
			'order_summary_border_width'     => '',
			'order_summary_border_style'     => '',
			'order_summary_border_radius'    => '',
		);

		// Override defaults with any custom settings from Elementor
		// Title settings.
		if ( ! empty( $settings['title_color'] ) ) {
			$attrs['title_color'] = $settings['title_color'];
		}
		if ( ! empty( $settings['title_typography_font_size']['size'] ) ) {
			$attrs['title_font_size'] = $settings['title_typography_font_size']['size'] . ( $settings['title_typography_font_size']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['title_typography_font_weight'] ) ) {
			$attrs['title_font_weight'] = $settings['title_typography_font_weight'];
		}
		if ( ! empty( $settings['title_typography_font_family'] ) ) {
			$attrs['title_font_family'] = $settings['title_typography_font_family'];
		}
		if ( ! empty( $settings['title_typography_text_transform'] ) ) {
			$attrs['title_text_transform'] = $settings['title_typography_text_transform'];
		}
		if ( ! empty( $settings['title_typography_text_decoration'] ) ) {
			$attrs['title_text_decoration'] = $settings['title_typography_text_decoration'];
		}
		if ( ! empty( $settings['title_typography_line_height']['size'] ) ) {
			$attrs['title_line_height'] = $settings['title_typography_line_height']['size'] . ( $settings['title_typography_line_height']['unit'] ?? '' );
		}
		if ( ! empty( $settings['title_typography_letter_spacing']['size'] ) ) {
			$attrs['title_letter_spacing'] = $settings['title_typography_letter_spacing']['size'] . ( $settings['title_typography_letter_spacing']['unit'] ?? 'px' );
		}

		// Input label settings.
		if ( ! empty( $settings['input_label_color'] ) ) {
			$attrs['input_label_color'] = $settings['input_label_color'];
		}
		if ( ! empty( $settings['input_label_typography_font_size']['size'] ) ) {
			$attrs['input_label_font_size'] = $settings['input_label_typography_font_size']['size'] . ( $settings['input_label_typography_font_size']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['input_label_typography_font_weight'] ) ) {
			$attrs['input_label_font_weight'] = $settings['input_label_typography_font_weight'];
		}
		if ( ! empty( $settings['input_label_typography_font_family'] ) ) {
			$attrs['input_label_font_family'] = $settings['input_label_typography_font_family'];
		}

		// Input field settings.
		if ( ! empty( $settings['input_color'] ) ) {
			$attrs['input_color'] = $settings['input_color'];
		}
		if ( ! empty( $settings['input_background_color'] ) ) {
			$attrs['input_background_color'] = $settings['input_background_color'];
		}
		if ( ! empty( $settings['input_typography_font_size']['size'] ) ) {
			$attrs['input_font_size'] = $settings['input_typography_font_size']['size'] . ( $settings['input_typography_font_size']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['input_typography_font_weight'] ) ) {
			$attrs['input_font_weight'] = $settings['input_typography_font_weight'];
		}
		if ( ! empty( $settings['input_typography_font_family'] ) ) {
			$attrs['input_font_family'] = $settings['input_typography_font_family'];
		}
		if ( ! empty( $settings['input_border_border'] ) ) {
			$attrs['input_border_style'] = $settings['input_border_border'];
		}
		if ( ! empty( $settings['input_border_width']['top'] ) ) {
			$attrs['input_border_width'] = $settings['input_border_width']['top'] . ( $settings['input_border_width']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['input_border_color'] ) ) {
			$attrs['input_border_color'] = $settings['input_border_color'];
		}
		if ( ! empty( $settings['input_border_radius']['top'] ) ) {
			$attrs['input_border_radius'] = $settings['input_border_radius']['top'] . ( $settings['input_border_radius']['unit'] ?? 'px' );
		}

		// Button settings.
		if ( ! empty( $settings['button_color'] ) ) {
			$attrs['button_color'] = $settings['button_color'];
		}
		if ( ! empty( $settings['button_background_color'] ) ) {
			$attrs['button_background_color'] = $settings['button_background_color'];
		}
		if ( ! empty( $settings['button_typography_font_size']['size'] ) ) {
			$attrs['button_font_size'] = $settings['button_typography_font_size']['size'] . ( $settings['button_typography_font_size']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['button_typography_font_weight'] ) ) {
			$attrs['button_font_weight'] = $settings['button_typography_font_weight'];
		}
		if ( ! empty( $settings['button_typography_font_family'] ) ) {
			$attrs['button_font_family'] = $settings['button_typography_font_family'];
		}
		if ( ! empty( $settings['button_typography_text_transform'] ) ) {
			$attrs['button_text_transform'] = $settings['button_typography_text_transform'];
		}
		if ( ! empty( $settings['button_typography_text_decoration'] ) ) {
			$attrs['button_text_decoration'] = $settings['button_typography_text_decoration'];
		}
		if ( ! empty( $settings['button_typography_line_height']['size'] ) ) {
			$attrs['button_line_height'] = $settings['button_typography_line_height']['size'] . ( $settings['button_typography_line_height']['unit'] ?? '' );
		}
		if ( ! empty( $settings['button_typography_letter_spacing']['size'] ) ) {
			$attrs['button_letter_spacing'] = $settings['button_typography_letter_spacing']['size'] . ( $settings['button_typography_letter_spacing']['unit'] ?? 'px' );
		}

		// Button padding.
		if ( ! empty( $settings['button_padding']['top'] ) ) {
			$attrs['button_padding_top'] = $settings['button_padding']['top'] . ( $settings['button_padding']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['button_padding']['right'] ) ) {
			$attrs['button_padding_right'] = $settings['button_padding']['right'] . ( $settings['button_padding']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['button_padding']['bottom'] ) ) {
			$attrs['button_padding_bottom'] = $settings['button_padding']['bottom'] . ( $settings['button_padding']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['button_padding']['left'] ) ) {
			$attrs['button_padding_left'] = $settings['button_padding']['left'] . ( $settings['button_padding']['unit'] ?? 'px' );
		}

		// Button margin.
		if ( ! empty( $settings['button_margin']['top'] ) ) {
			$attrs['button_margin_top'] = $settings['button_margin']['top'] . ( $settings['button_margin']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['button_margin']['right'] ) ) {
			$attrs['button_margin_right'] = $settings['button_margin']['right'] . ( $settings['button_margin']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['button_margin']['bottom'] ) ) {
			$attrs['button_margin_bottom'] = $settings['button_margin']['bottom'] . ( $settings['button_margin']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['button_margin']['left'] ) ) {
			$attrs['button_margin_left'] = $settings['button_margin']['left'] . ( $settings['button_margin']['unit'] ?? 'px' );
		}

		// Button border.
		if ( ! empty( $settings['button_border_radius']['top'] ) ) {
			$attrs['button_border_radius'] = $settings['button_border_radius']['top'] . ( $settings['button_border_radius']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['button_border_color'] ) ) {
			$attrs['button_border_color'] = $settings['button_border_color'];
		}
		if ( ! empty( $settings['button_border_width']['top'] ) ) {
			$attrs['button_border_width'] = $settings['button_border_width']['top'] . ( $settings['button_border_width']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['button_border_border'] ) ) {
			$attrs['button_border_style'] = $settings['button_border_border'];
		}

		// Button hover states.
		if ( ! empty( $settings['button_hover_color'] ) ) {
			$attrs['button_hover_color'] = $settings['button_hover_color'];
		}
		if ( ! empty( $settings['button_hover_background_color'] ) ) {
			$attrs['button_hover_background_color'] = $settings['button_hover_background_color'];
		}
		if ( ! empty( $settings['button_hover_border_color'] ) ) {
			$attrs['button_hover_border_color'] = $settings['button_hover_border_color'];
		}

		// Privacy text settings.
		if ( ! empty( $settings['privacy_text_color'] ) ) {
			$attrs['privacy_text_color'] = $settings['privacy_text_color'];
		}
		if ( ! empty( $settings['privacy_text_typography_font_size']['size'] ) ) {
			$attrs['privacy_text_font_size'] = $settings['privacy_text_typography_font_size']['size'] . ( $settings['privacy_text_typography_font_size']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['privacy_text_typography_font_weight'] ) ) {
			$attrs['privacy_text_font_weight'] = $settings['privacy_text_typography_font_weight'];
		}
		if ( ! empty( $settings['privacy_text_typography_font_family'] ) ) {
			$attrs['privacy_text_font_family'] = $settings['privacy_text_typography_font_family'];
		}
		if ( ! empty( $settings['privacy_text_typography_text_transform'] ) ) {
			$attrs['privacy_text_transform'] = $settings['privacy_text_typography_text_transform'];
		}
		if ( ! empty( $settings['privacy_text_typography_text_decoration'] ) ) {
			$attrs['privacy_text_decoration'] = $settings['privacy_text_typography_text_decoration'];
		}
		if ( ! empty( $settings['privacy_text_typography_line_height']['size'] ) ) {
			$attrs['privacy_text_line_height'] = $settings['privacy_text_typography_line_height']['size'] . ( $settings['privacy_text_typography_line_height']['unit'] ?? '' );
		}
		if ( ! empty( $settings['privacy_text_typography_letter_spacing']['size'] ) ) {
			$attrs['privacy_text_letter_spacing'] = $settings['privacy_text_typography_letter_spacing']['size'] . ( $settings['privacy_text_typography_letter_spacing']['unit'] ?? 'px' );
		}

		// Checkout box settings.
		if ( ! empty( $settings['checkout_box_background_color'] ) ) {
			$attrs['checkout_box_background_color'] = $settings['checkout_box_background_color'];
		}
		if ( ! empty( $settings['checkout_box_padding']['top'] ) ) {
			$attrs['checkout_box_padding_top'] = $settings['checkout_box_padding']['top'] . ( $settings['checkout_box_padding']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['checkout_box_padding']['right'] ) ) {
			$attrs['checkout_box_padding_right'] = $settings['checkout_box_padding']['right'] . ( $settings['checkout_box_padding']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['checkout_box_padding']['bottom'] ) ) {
			$attrs['checkout_box_padding_bottom'] = $settings['checkout_box_padding']['bottom'] . ( $settings['checkout_box_padding']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['checkout_box_padding']['left'] ) ) {
			$attrs['checkout_box_padding_left'] = $settings['checkout_box_padding']['left'] . ( $settings['checkout_box_padding']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['checkout_box_margin']['top'] ) ) {
			$attrs['checkout_box_margin_top'] = $settings['checkout_box_margin']['top'] . ( $settings['checkout_box_margin']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['checkout_box_margin']['right'] ) ) {
			$attrs['checkout_box_margin_right'] = $settings['checkout_box_margin']['right'] . ( $settings['checkout_box_margin']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['checkout_box_margin']['bottom'] ) ) {
			$attrs['checkout_box_margin_bottom'] = $settings['checkout_box_margin']['bottom'] . ( $settings['checkout_box_margin']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['checkout_box_margin']['left'] ) ) {
			$attrs['checkout_box_margin_left'] = $settings['checkout_box_margin']['left'] . ( $settings['checkout_box_margin']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['checkout_box_border_color'] ) ) {
			$attrs['checkout_box_border_color'] = $settings['checkout_box_border_color'];
		}
		if ( ! empty( $settings['checkout_box_border_width']['top'] ) ) {
			$attrs['checkout_box_border_width'] = $settings['checkout_box_border_width']['top'] . ( $settings['checkout_box_border_width']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['checkout_box_border_border'] ) ) {
			$attrs['checkout_box_border_style'] = $settings['checkout_box_border_border'];
		}
		if ( ! empty( $settings['checkout_box_border_radius']['top'] ) ) {
			$attrs['checkout_box_border_radius'] = $settings['checkout_box_border_radius']['top'] . ( $settings['checkout_box_border_radius']['unit'] ?? 'px' );
		}

		// Order summary settings.
		if ( ! empty( $settings['order_summary_background_color'] ) ) {
			$attrs['order_summary_background_color'] = $settings['order_summary_background_color'];
		}
		if ( ! empty( $settings['order_summary_padding']['top'] ) ) {
			$attrs['order_summary_padding_top'] = $settings['order_summary_padding']['top'] . ( $settings['order_summary_padding']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['order_summary_padding']['right'] ) ) {
			$attrs['order_summary_padding_right'] = $settings['order_summary_padding']['right'] . ( $settings['order_summary_padding']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['order_summary_padding']['bottom'] ) ) {
			$attrs['order_summary_padding_bottom'] = $settings['order_summary_padding']['bottom'] . ( $settings['order_summary_padding']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['order_summary_padding']['left'] ) ) {
			$attrs['order_summary_padding_left'] = $settings['order_summary_padding']['left'] . ( $settings['order_summary_padding']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['order_summary_margin']['top'] ) ) {
			$attrs['order_summary_margin_top'] = $settings['order_summary_margin']['top'] . ( $settings['order_summary_margin']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['order_summary_margin']['right'] ) ) {
			$attrs['order_summary_margin_right'] = $settings['order_summary_margin']['right'] . ( $settings['order_summary_margin']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['order_summary_margin']['bottom'] ) ) {
			$attrs['order_summary_margin_bottom'] = $settings['order_summary_margin']['bottom'] . ( $settings['order_summary_margin']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['order_summary_margin']['left'] ) ) {
			$attrs['order_summary_margin_left'] = $settings['order_summary_margin']['left'] . ( $settings['order_summary_margin']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['order_summary_border_color'] ) ) {
			$attrs['order_summary_border_color'] = $settings['order_summary_border_color'];
		}
		if ( ! empty( $settings['order_summary_border_width']['top'] ) ) {
			$attrs['order_summary_border_width'] = $settings['order_summary_border_width']['top'] . ( $settings['order_summary_border_width']['unit'] ?? 'px' );
		}
		if ( ! empty( $settings['order_summary_border_border'] ) ) {
			$attrs['order_summary_border_style'] = $settings['order_summary_border_border'];
		}
		if ( ! empty( $settings['order_summary_border_radius']['top'] ) ) {
			$attrs['order_summary_border_radius'] = $settings['order_summary_border_radius']['top'] . ( $settings['order_summary_border_radius']['unit'] ?? 'px' );
		}

		// Layout type.
		if ( isset( $settings['layout_type'] ) ) {
			$attrs['layout_type'] = $settings['layout_type'];
		}

		return $attrs;
	}
}
