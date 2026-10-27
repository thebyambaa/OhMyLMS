<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * WPBakery Buy Now Element
 *
 * @package OhMyLMS\WPBakery\Elements
 * @since 1.0.0
 */

namespace OhMyLMS\WPBakery\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * BuyNowElement class
 */
class BuyNowElement {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->register_element();
	}

	/**
	 * Get list of published courses for dropdown
	 *
	 * @return array
	 */
	private function get_courses_dropdown() {
		$courses = get_posts(
			array(
				'post_type'   => 'ohmylms-course',
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);

		$options                                       = array();
		$options[ __( 'Select a course', 'ohmylms' ) ] = '';

		foreach ( $courses as $course ) {
			$options[ $course->post_title ] = $course->ID;
		}

		return $options;
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
				'name'        => __( 'Buy Now Button', 'ohmylms' ),
				'base'        => 'ohmylms_buy_now',
				'icon'        => 'icon-wpb-ohmylms',
				'category'    => __( 'OhMyLMS', 'ohmylms' ),
				'description' => __( 'Add a Buy Now button for a course', 'ohmylms' ),
				'params'      => array(
					// Course Selection.
					array(
						'type'        => 'dropdown',
						'heading'     => __( 'Course', 'ohmylms' ),
						'param_name'  => 'course_id',
						'value'       => $this->get_courses_dropdown(),
						'description' => __( 'Select the course for the Buy Now button', 'ohmylms' ),
						'admin_label' => true,
					),
					// Button Text.
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Button Text', 'ohmylms' ),
						'param_name'  => 'btn_text',
						'value'       => __( 'Buy Now', 'ohmylms' ),
						'description' => __( 'Text to display on the button', 'ohmylms' ),
					),
					// Background Color.
					array(
						'type'        => 'colorpicker',
						'heading'     => __( 'Background Color', 'ohmylms' ),
						'param_name'  => 'background',
						'value'       => '#0073aa',
						'description' => __( 'Button background color', 'ohmylms' ),
						'group'       => __( 'Style', 'ohmylms' ),
					),
					// Text Color.
					array(
						'type'        => 'colorpicker',
						'heading'     => __( 'Text Color', 'ohmylms' ),
						'param_name'  => 'color',
						'value'       => '#fff',
						'description' => __( 'Button text color', 'ohmylms' ),
						'group'       => __( 'Style', 'ohmylms' ),
					),
					// Padding.
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Padding', 'ohmylms' ),
						'param_name'  => 'padding',
						'value'       => '12px 24px',
						'description' => __( 'Button padding (e.g., 12px 24px)', 'ohmylms' ),
						'group'       => __( 'Style', 'ohmylms' ),
					),
					// Border Radius.
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Border Radius', 'ohmylms' ),
						'param_name'  => 'border_radius',
						'value'       => '4px',
						'description' => __( 'Border radius (e.g., 4px)', 'ohmylms' ),
						'group'       => __( 'Style', 'ohmylms' ),
					),
					// Font Size.
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Font Size', 'ohmylms' ),
						'param_name'  => 'font_size',
						'value'       => '16px',
						'description' => __( 'Font size (e.g., 16px)', 'ohmylms' ),
						'group'       => __( 'Style', 'ohmylms' ),
					),
					// Text Decoration
					// array(
					// 'type'        => 'dropdown',
					// 'heading'     => __( 'Text Decoration', 'ohmylms' ),
					// 'param_name'  => 'text_decoration',
					// 'value'       => array(
					// 'none'         => __( 'None', 'ohmylms' ),
					// 'underline'    => __( 'underline', 'ohmylms' ),
					// 'line-through' => __( 'line-through', 'ohmylms' ),
					// ),
					// 'description' => __( 'Text decoration style', 'ohmylms' ),
					// 'group'       => __( 'Style', 'ohmylms' ),
					// ),
					// Line Height.
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Line Height', 'ohmylms' ),
						'param_name'  => 'line_height',
						'value'       => '1.5',
						'description' => __( 'Line height (e.g., 1.5)', 'ohmylms' ),
						'group'       => __( 'Style', 'ohmylms' ),
					),
					// Width.
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Width', 'ohmylms' ),
						'param_name'  => 'width',
						'value'       => 'auto',
						'description' => __( 'Button width (e.g., auto, 100%, 200px)', 'ohmylms' ),
						'group'       => __( 'Dimensions', 'ohmylms' ),
					),
					// Max Width.
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Max Width', 'ohmylms' ),
						'param_name'  => 'max_width',
						'value'       => '100%',
						'description' => __( 'Maximum width (e.g., 100%, 300px)', 'ohmylms' ),
						'group'       => __( 'Dimensions', 'ohmylms' ),
					),
					// Min Width.
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Min Width', 'ohmylms' ),
						'param_name'  => 'min_width',
						'value'       => '100px',
						'description' => __( 'Minimum width (e.g., 100px)', 'ohmylms' ),
						'group'       => __( 'Dimensions', 'ohmylms' ),
					),
					// Height.
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Height', 'ohmylms' ),
						'param_name'  => 'height',
						'value'       => 'auto',
						'description' => __( 'Button height (e.g., auto, 50px)', 'ohmylms' ),
						'group'       => __( 'Dimensions', 'ohmylms' ),
					),
					// Extra CSS Class.
					array(
						'type'        => 'textfield',
						'heading'     => __( 'Extra CSS Class', 'ohmylms' ),
						'param_name'  => 'class',
						'value'       => '',
						'description' => __( 'Add custom CSS class for styling', 'ohmylms' ),
						'group'       => __( 'Advanced', 'ohmylms' ),
					),
				),
			)
		);
	}
}
