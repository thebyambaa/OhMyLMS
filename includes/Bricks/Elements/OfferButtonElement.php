<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
namespace OhMyLMS\Bricks\Elements;

use Bricks\Element;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Plugin offer button element implementation.
 */
class OfferButtonElement extends Element {

	/**
	 * Category.
	 *
	 * @var mixed
	 */
	public $category = 'ohmylms';
	/**
	 * Name.
	 *
	 * @var mixed
	 */
	public $name = 'ohmylms-offer-button';
	/**
	 * Icon.
	 *
	 * @var mixed
	 */
	public $icon = 'ti-gift';
	/**
	 * Css selector.
	 *
	 * @var mixed
	 */
	public $css_selector = '.ohmylms-offer-btn';

	/**
	 * Get label for Offer button element.
	 */
	public function get_label() {
		return __( 'Offer Button', 'ohmylms' );
	}

	/**
	 * Set controls for Offer button element.
	 */
	public function set_controls() {
		// Content controls.
		$this->controls['action'] = array(
			'tab'     => 'content',
			'label'   => __( 'Action', 'ohmylms' ),
			'type'    => 'select',
			'options' => array(
				'accept'  => __( 'Accept Offer', 'ohmylms' ),
				'decline' => __( 'Decline Offer', 'ohmylms' ),
			),
			'default' => 'accept',
		);

		$this->controls['text'] = array(
			'tab'     => 'content',
			'label'   => __( 'Button Text', 'ohmylms' ),
			'type'    => 'text',
			'default' => __( 'Accept Offer', 'ohmylms' ),
		);

		// Style controls.
		$this->controls['background'] = array(
			'tab'     => 'style',
			'label'   => __( 'Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => '#6e42d3',
			'css'     => array(
				array(
					'selector' => '{{WRAPPER}} .ohmylms-offer-btn',
					'property' => 'background-color',
				),
			),
		);

		$this->controls['color'] = array(
			'tab'     => 'style',
			'label'   => __( 'Text Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => '#fff',
			'css'     => array(
				array(
					'selector' => '{{WRAPPER}} .ohmylms-offer-btn',
					'property' => 'color',
				),
			),
		);

		$this->controls['border'] = array(
			'tab'     => 'style',
			'label'   => __( 'Border', 'ohmylms' ),
			'type'    => 'text',
			'default' => '',
			'css'     => array(
				array(
					'selector' => '{{WRAPPER}} .ohmylms-offer-btn',
					'property' => 'border',
				),
			),
		);

		$this->controls['padding'] = array(
			'tab'     => 'style',
			'label'   => __( 'Padding', 'ohmylms' ),
			'type'    => 'dimensions',
			'units'   => array( 'px', 'em', '%' ),
			'default' => '12px 24px',
			'css'     => array(
				array(
					'selector' => '{{WRAPPER}} .ohmylms-offer-btn',
					'property' => 'padding',
				),
			),
		);

		$this->controls['margin'] = array(
			'tab'     => 'style',
			'label'   => __( 'Margin', 'ohmylms' ),
			'type'    => 'dimensions',
			'units'   => array( 'px', 'em', '%' ),
			'default' => '',
			'css'     => array(
				array(
					'selector' => '{{WRAPPER}} .ohmylms-offer-btn',
					'property' => 'margin',
				),
			),
		);

		$this->controls['font_size'] = array(
			'tab'     => 'style',
			'label'   => __( 'Font Size', 'ohmylms' ),
			'type'    => 'text',
			'default' => '16px',
			'css'     => array(
				array(
					'selector' => '{{WRAPPER}} .ohmylms-offer-btn',
					'property' => 'font-size',
				),
			),
		);

		$this->controls['font_weight'] = array(
			'tab'     => 'style',
			'label'   => __( 'Font Weight', 'ohmylms' ),
			'type'    => 'text',
			'default' => '',
			'css'     => array(
				array(
					'selector' => '{{WRAPPER}} .ohmylms-offer-btn',
					'property' => 'font-weight',
				),
			),
		);

		$this->controls['border_radius'] = array(
			'tab'     => 'style',
			'label'   => __( 'Border Radius', 'ohmylms' ),
			'type'    => 'text',
			'default' => '4px',
			'css'     => array(
				array(
					'selector' => '{{WRAPPER}} .ohmylms-offer-btn',
					'property' => 'border-radius',
				),
			),
		);

		$this->controls['width'] = array(
			'tab'     => 'style',
			'label'   => __( 'Width', 'ohmylms' ),
			'type'    => 'text',
			'default' => '',
			'css'     => array(
				array(
					'selector' => '{{WRAPPER}} .ohmylms-offer-btn',
					'property' => 'width',
				),
			),
		);

		$this->controls['height'] = array(
			'tab'     => 'style',
			'label'   => __( 'Height', 'ohmylms' ),
			'type'    => 'text',
			'default' => '',
			'css'     => array(
				array(
					'selector' => '{{WRAPPER}} .ohmylms-offer-btn',
					'property' => 'height',
				),
			),
		);

		// Positioning and alignment controls.
		$this->controls['display'] = array(
			'tab'     => 'style',
			'label'   => __( 'Display', 'ohmylms' ),
			'type'    => 'select',
			'options' => array(
				'inline-block' => __( 'Inline Block', 'ohmylms' ),
				'block'        => __( 'Block', 'ohmylms' ),
				'inline'       => __( 'Inline', 'ohmylms' ),
				'flex'         => __( 'Flex', 'ohmylms' ),
			),
			'default' => 'inline-block',
			'css'     => array(
				array(
					'selector' => '{{WRAPPER}} .ohmylms-offer-btn',
					'property' => 'display',
				),
			),
		);

		$this->controls['vertical_align'] = array(
			'tab'     => 'style',
			'label'   => __( 'Vertical Align', 'ohmylms' ),
			'type'    => 'select',
			'options' => array(
				'baseline'    => __( 'Baseline', 'ohmylms' ),
				'top'         => __( 'Top', 'ohmylms' ),
				'middle'      => __( 'Middle', 'ohmylms' ),
				'bottom'      => __( 'Bottom', 'ohmylms' ),
				'text-top'    => __( 'Text Top', 'ohmylms' ),
				'text-bottom' => __( 'Text Bottom', 'ohmylms' ),
			),
			'default' => 'baseline',
			'css'     => array(
				array(
					'selector' => '{{WRAPPER}} .ohmylms-offer-btn',
					'property' => 'vertical-align',
				),
			),
		);

		$this->controls['box_sizing'] = array(
			'tab'     => 'style',
			'label'   => __( 'Box Sizing', 'ohmylms' ),
			'type'    => 'select',
			'options' => array(
				'content-box' => __( 'Content Box', 'ohmylms' ),
				'border-box'  => __( 'Border Box', 'ohmylms' ),
			),
			'default' => 'border-box',
			'css'     => array(
				array(
					'selector' => '{{WRAPPER}} .ohmylms-offer-btn',
					'property' => 'box-sizing',
				),
			),
		);

		// Advanced controls.
		$this->controls['id'] = array(
			'tab'     => 'advanced',
			'label'   => __( 'CSS ID', 'ohmylms' ),
			'type'    => 'text',
			'default' => '',
		);

		$this->controls['class'] = array(
			'tab'     => 'advanced',
			'label'   => __( 'Additional CSS Class', 'ohmylms' ),
			'type'    => 'text',
			'default' => '',
		);

		$this->controls['style'] = array(
			'tab'     => 'advanced',
			'label'   => __( 'Inline CSS Styles', 'ohmylms' ),
			'type'    => 'text',
			'default' => '',
		);
	}

	/**
	 * Render for Offer button element.
	 */
	public function render() {
		$defaults = array(
			'action'          => 'accept',
			'text'            => __( 'Accept Offer', 'ohmylms' ),
			'background'      => '#6e42d3',
			'color'           => '#fff',
			'padding'         => '12px 24px',
			'border_radius'   => '4px',
			'font_size'       => '16px',
			'text_decoration' => 'none',
			'line_height'     => '1',
			'width'           => 'auto',
			'max_width'       => '100%',
			'min_width'       => '100px',
			'height'          => 'auto',
			'display'         => 'inline-block',
			'vertical_align'  => 'baseline',
			'margin'          => '0px',
			'box_sizing'      => 'border-box',
			'class'           => '.ohmylms-offer-button',
		);

		$settings = wp_parse_args( $this->settings, $defaults );

		// === COLORS ===
		if ( isset( $settings['_background']['color']['hex'] ) ) {
			$settings['background'] = $settings['_background']['color']['hex'];
		}

		if ( isset( $settings['_typography']['color']['hex'] ) ) {
			$settings['color'] = $settings['_typography']['color']['hex'];
		}

		// === TYPOGRAPHY ===
		if ( isset( $settings['_typography']['font-size'] ) ) {
			$settings['font_size'] = $settings['_typography']['font-size'] . 'px';
		}
		if ( isset( $settings['_typography']['line-height'] ) ) {
			$settings['line_height'] = $settings['_typography']['line-height'];
		}
		if ( isset( $settings['_typography']['text-decoration'] ) ) {
			$settings['text_decoration'] = $settings['_typography']['text-decoration'];
		}

		// === PADDING ===
		if ( is_array( $settings['padding'] ) ) {
			$settings['padding'] = implode(
				' ',
				array_filter(
					array(
						$settings['padding']['top'] ?? '',
						$settings['padding']['right'] ?? '',
						$settings['padding']['bottom'] ?? '',
						$settings['padding']['left'] ?? '',
					)
				)
			);
		}

		// === BORDER RADIUS ===
		if ( is_array( $settings['border_radius'] ) ) {
			$settings['border_radius'] = $settings['border_radius']['top'] ?? '0';
		}

		// === WIDTH / HEIGHT ===
		foreach ( array( 'width', 'max_width', 'min_width', 'height' ) as $dim ) {
			if ( is_array( $settings[ $dim ] ) && isset( $settings[ $dim ]['size'] ) ) {
				$settings[ $dim ] = $settings[ $dim ]['size'] . ( $settings[ $dim ]['unit'] ?? 'px' );
			}
		}

		// === MARGIN ===
		if ( is_array( $settings['margin'] ) ) {
			$settings['margin'] = implode(
				' ',
				array_filter(
					array(
						$settings['margin']['top'] ?? '',
						$settings['margin']['right'] ?? '',
						$settings['margin']['bottom'] ?? '',
						$settings['margin']['left'] ?? '',
					)
				)
			);
		}

		if ( isset( $settings['_border'] ) ) {
			$border = $settings['_border'];

			// Width.
			$width        = $border['width'] ?? array(
				'top'    => '0',
				'right'  => '0',
				'bottom' => '0',
				'left'   => '0',
			);
			$border_width = "{$width['top']} {$width['right']} {$width['bottom']} {$width['left']}";

			// Style.
			$border_style = $border['style'] ?? 'solid';

			// Color.
			$border_color = $border['color']['hex'] ?? '#000';

			// Radius.
			$radius        = $border['radius'] ?? array(
				'top'    => 0,
				'right'  => 0,
				'bottom' => 0,
				'left'   => 0,
			);
			$border_radius = "{$radius['top']}px {$radius['right']}px {$radius['bottom']}px {$radius['left']}px";

			$settings['border']        = "{$border_width} {$border_style} {$border_color}";
			$settings['border_radius'] = $border_radius;
		}

		// Generate offer action URL (similar to shortcode logic).
		$action_url = $this->get_offer_action_url( $settings['action'] );

		// Build button classes.
		$button_classes   = array( 'ohmylms-offer-btn' );
		$button_classes[] = 'ohmylms-offer-btn--' . sanitize_html_class( $settings['action'] );

		if ( ! empty( $settings['class'] ) ) {
			$custom_classes = explode( ' ', $settings['class'] );
			$button_classes = array_merge( $button_classes, array_map( 'sanitize_html_class', $custom_classes ) );
		}

		// Build inline styles (like Buy Now Element).
		$style = sprintf(
			'background:%s;color:%s;padding:%s;border-radius:%s;font-size:%s;border:none;cursor:pointer;text-decoration:%s;line-height:%s;width:%s;max-width:%s;min-width:%s;height:%s;display:%s;vertical-align:%s;margin:%s;box-sizing:%s;',
			esc_attr( $settings['background'] ),
			esc_attr( $settings['color'] ),
			esc_attr( $settings['padding'] ),
			esc_attr( $settings['border_radius'] ),
			esc_attr( $settings['font_size'] ),
			esc_attr( $settings['text_decoration'] ),
			esc_attr( $settings['line_height'] ),
			esc_attr( $settings['width'] ),
			esc_attr( $settings['max_width'] ),
			esc_attr( $settings['min_width'] ),
			esc_attr( $settings['height'] ),
			esc_attr( $settings['display'] ),
			esc_attr( $settings['vertical_align'] ),
			esc_attr( $settings['margin'] ),
			esc_attr( $settings['box_sizing'] )
		);

		// Add border if set.
		if ( ! empty( $settings['border'] ) ) {
			$style .= 'border:' . esc_attr( $settings['border'] ) . ';';
		}

		$this->set_attribute( '_root', 'class', 'ohmylms-offer-button ' . esc_attr( $settings['class'] ) );
		echo '<div ' . $this->render_attributes( '_root' ) . '>';

		printf(
			'<a href="%s" class="%s" style="%s" data-action="%s">%s</a>',
			esc_url( $action_url ),
			esc_attr( implode( ' ', $button_classes ) ),
			esc_attr( $style ),
			esc_attr( $settings['action'] ),
			esc_html( $settings['text'] )
		);

		echo '</div>';
	}

	/**
	 * Generate offer action URL
	 *
	 * @param mixed $action Action.
	 */
	private function get_offer_action_url( $action ) {
		// Get funnel session data.
		if ( class_exists( '\OhMyLMS\Integrations\Funnel\Includes\FunnelManager' ) ) {
			$funnel_data  = \OhMyLMS\Integrations\Funnel\Includes\FunnelManager::get_funnel_session();
			$order_id     = $funnel_data['order_id'] ?? '';
			$current_step = $funnel_data['current_step'] ?? '';

			// Also check URL parameters.
			if ( empty( $order_id ) && isset( $_GET['order_id'] ) ) {
				$order_id = absint( $_GET['order_id'] );
			}

			if ( empty( $current_step ) ) {
				$current_step = get_query_var( 'step' );
				if ( empty( $current_step ) && isset( $_GET['step'] ) ) {
					$current_step = sanitize_text_field( wp_unslash( $_GET['step'] ));
				}
			}

			if ( ! empty( $current_step ) && strpos( $current_step, 'step_' ) !== 0 ) {
				$current_step = 'step_' . $current_step;
			}

			if ( $order_id && $current_step ) {
				$step_number = strpos( $current_step, 'step_' ) === 0 ? substr( $current_step, 5 ) : $current_step;
				$base_url    = home_url( sprintf( '/post-checkout-funnel/order/%d/step/%s', $order_id, $step_number ) );
				$action_url  = add_query_arg( 'action', $action, $base_url );

				// Add nonce.
				$nonce = $_GET['funnel_nonce'] ?? wp_create_nonce( 'funnel_action_' . $order_id . '_' . $current_step );
				return add_query_arg( '_wpnonce', $nonce, $action_url );
			}
		}

		return '#';
	}

	/**
	 * Enqueue scripts for Offer button element.
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'ohmylms-frontend' );
		wp_enqueue_script( 'ohmylms-add-to-cart' );
	}
}
