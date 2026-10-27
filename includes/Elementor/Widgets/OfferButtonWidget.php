<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * OhMyLMS Offer Button Widget for Elementor
 *
 * @package OhMyLMS\Elementor\Widgets
 */

namespace OhMyLMS\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Plugin offer button widget implementation.
 */
class OfferButtonWidget extends Widget_Base {

	/**
	 * Get name for Offer button widget.
	 */
	public function get_name() {
		return 'ohmylms-offer-button';
	}

	/**
	 * Get title for Offer button widget.
	 */
	public function get_title() {
		return __( 'Offer Button', 'ohmylms' );
	}

	/**
	 * Get icon for Offer button widget.
	 */
	public function get_icon() {
		return 'eicon-badge';
	}

	/**
	 * Get categories for Offer button widget.
	 */
	public function get_categories() {
		return array( 'ohmylms' );
	}

	/**
	 * Register controls for Offer button widget.
	 */
	protected function _register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'ohmylms' ),
			)
		);

		$this->add_control(
			'action',
			array(
				'label'   => __( 'Action', 'ohmylms' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'accept'  => __( 'Accept Offer', 'ohmylms' ),
					'decline' => __( 'Decline Offer', 'ohmylms' ),
				),
				'default' => 'accept',
			)
		);
		$this->add_control(
			'text',
			array(
				'label'   => __( 'Button Text', 'ohmylms' ),
				'type'    => Controls_Manager::TEXT,
				'default' => 'Accept Offer',
			)
		);
		$this->add_control(
			'background',
			array(
				'label'   => __( 'Background Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#0073aa',
			)
		);
		$this->add_control(
			'color',
			array(
				'label'   => __( 'Text Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#fff',
			)
		);
		$this->add_control(
			'border',
			array(
				'label'   => __( 'Border', 'ohmylms' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
			)
		);
		$this->add_control(
			'padding',
			array(
				'label'   => __( 'Padding', 'ohmylms' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '12px 24px',
			)
		);
		$this->add_control(
			'margin',
			array(
				'label'   => __( 'Margin', 'ohmylms' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
			)
		);
		$this->add_control(
			'font_size',
			array(
				'label'   => __( 'Font Size', 'ohmylms' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '16px',
			)
		);
		$this->add_control(
			'font_weight',
			array(
				'label'   => __( 'Font Weight', 'ohmylms' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
			)
		);
		$this->add_control(
			'border_radius',
			array(
				'label'   => __( 'Border Radius', 'ohmylms' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '4px',
			)
		);
		$this->add_control(
			'width',
			array(
				'label'   => __( 'Width', 'ohmylms' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
			)
		);
		$this->add_control(
			'height',
			array(
				'label'   => __( 'Height', 'ohmylms' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
			)
		);
		$this->add_control(
			'class',
			array(
				'label'   => __( 'Extra CSS Class', 'ohmylms' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
			)
		);
		$this->add_control(
			'id',
			array(
				'label'   => __( 'CSS ID', 'ohmylms' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
			)
		);
		$this->add_control(
			'style',
			array(
				'label'   => __( 'Inline CSS Styles', 'ohmylms' ),
				'type'    => Controls_Manager::TEXTAREA,
				'default' => '',
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Render for Offer button widget.
	 */
	protected function render() {
		$settings  = $this->get_settings_for_display();
		$atts      = array(
			'action'        => $settings['action'],
			'text'          => $settings['text'],
			'background'    => $settings['background'],
			'color'         => $settings['color'],
			'border'        => $settings['border'],
			'padding'       => $settings['padding'],
			'margin'        => $settings['margin'],
			'font_size'     => $settings['font_size'],
			'font_weight'   => $settings['font_weight'],
			'border_radius' => $settings['border_radius'],
			'width'         => $settings['width'],
			'height'        => $settings['height'],
			'class'         => $settings['class'],
			'id'            => $settings['id'],
			'style'         => $settings['style'],
		);
		$shortcode = '[ohmylms_offer_button';
		foreach ( $atts as $k => $v ) {
			if ( '' !== $v && null !== $v ) {
				$shortcode .= ' ' . $k . '="' . esc_attr( $v ) . '"';
			}
		}
		$shortcode .= ']';
		echo do_shortcode( $shortcode );
	}
}
