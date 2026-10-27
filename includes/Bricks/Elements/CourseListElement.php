<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * OhMyLMS Course List Element for Bricks
 *
 * @package OhMyLMS\Bricks\Elements
 * @since 1.0.0
 */

namespace OhMyLMS\Bricks\Elements;

use OhMyLMS\Shortcodes\ShortcodeCourseList;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CourseListElement class
 */
class CourseListElement extends \Bricks\Element {

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
	public $name = 'ohmylms-course-list';

	/**
	 * Css selector.
	 *
	 * @var mixed
	 */
	public $css_selector = ' .ohmylms-page .ohmylms-course-list-shortcode';
	/**
	 * Element icon
	 *
	 * @var string
	 */
	public $icon = 'ti-layout-grid3-alt';

	/**
	 * Element keywords
	 *
	 * @var array
	 */
	public $keywords = array( 'course', 'list', 'grid', 'courses', 'creator', 'lms' );

	/**
	 * Element scripts
	 *
	 * @var array
	 */
	public $scripts = array( 'ohmylms-frontend', 'ohmylms-add-to-cart', 'ohmylms-slick' );

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
		return esc_html__( 'OhMyLMS Course List', 'ohmylms' );
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
		// Layout Settings Group.
		$this->controls['layout_heading'] = array(
			'tab'   => 'content',
			'label' => esc_html__( 'Layout Settings', 'ohmylms' ),
			'type'  => 'separator',
		);

		$this->controls['layout'] = array(
			'tab'     => 'content',
			'label'   => esc_html__( 'Layout', 'ohmylms' ),
			'type'    => 'select',
			'options' => array(
				'grid' => esc_html__( 'Grid', 'ohmylms' ),
				'list' => esc_html__( 'List', 'ohmylms' ),
			),
			'default' => get_option( 'ohmylms_archive_page_layout', 'grid' ),
		);

		// Build layout style options.
		$layout_options = array(
			'grid-style1' => esc_html__( 'Layout 1', 'ohmylms' ),
		);

		// Layouts 2-4.
			$layout_options['grid-style2'] = esc_html__( 'Layout 2', 'ohmylms' );
			$layout_options['grid-style3'] = esc_html__( 'Layout 3', 'ohmylms' );
			$layout_options['grid-style4'] = esc_html__( 'Layout 4', 'ohmylms' );

		$this->controls['layout_style'] = array(
			'tab'      => 'content',
			'label'    => esc_html__( 'Layout Style', 'ohmylms' ),
			'type'     => 'select',
			'options'  => $layout_options,
			'default'  => get_option( 'ohmylms_archive_page_layout_style', 'grid-style1' ),
			'required' => array( array( 'layout', '=', 'grid' ) ),
		);

		$this->controls['columns'] = array(
			'tab'         => 'content',
			'label'       => esc_html__( 'Columns Per Row', 'ohmylms' ),
			'type'        => 'select',
			'options'     => array(
				'1' => esc_html__( '1 Column', 'ohmylms' ),
				'2' => esc_html__( '2 Columns', 'ohmylms' ),
				'3' => esc_html__( '3 Columns', 'ohmylms' ),
				'4' => esc_html__( '4 Columns', 'ohmylms' ),
			),
			'default'     => get_option( 'ohmylms_columns_per_row', '3' ),
			'required'    => array( array( 'layout', '=', 'grid' ) ),
			'description' => esc_html__( 'Note: If "Show Filter" is enabled for Layout Style 1 or 2, maximum columns allowed is 3.', 'ohmylms' ),
		);

		$this->controls['posts_per_page'] = array(
			'tab'     => 'content',
			'label'   => esc_html__( 'Courses Per Page', 'ohmylms' ),
			'type'    => 'number',
			'min'     => 1,
			'max'     => 100,
			'default' => get_option( 'ohmylms_courses_per_page', 10 ),
		);

		$this->controls['curriculum'] = array(
			'tab'         => 'content',
			'label'       => esc_html__( 'Curriculum', 'ohmylms' ),
			'type'        => 'select',
			'multiple'    => true,
			'searchable'  => true,
			'options'     => \OhMyLMS\Curriculum\Placement::item_options(),
			'description' => esc_html__( 'Only show courses placed under these curriculum items. Leave empty to show all.', 'ohmylms' ),
		);

		$this->controls['bundle'] = array(
			'tab'         => 'content',
			'label'       => esc_html__( 'Course bundles', 'ohmylms' ),
			'type'        => 'select',
			'multiple'    => true,
			'searchable'  => true,
			'options'     => \OhMyLMS\Curriculum\Placement::bundle_options(),
			'description' => esc_html__( 'Only show courses in these Course Bundles. Leave empty to show all.', 'ohmylms' ),
		);

		// Feature Toggles Group.
		$this->controls['features_heading'] = array(
			'tab'      => 'content',
			'label'    => esc_html__( 'Feature Toggles', 'ohmylms' ),
			'type'     => 'separator',
			'required' => array( array( 'layout', '=', 'grid' ) ),
		);

		$this->controls['show_filter'] = array(
			'tab'      => 'content',
			'label'    => esc_html__( 'Show Filter', 'ohmylms' ),
			'type'     => 'checkbox',
			'default'  => get_option( 'ohmylms_archive_page_filter_is_enabled', 'no' ) === 'yes',
			'required' => array(
				array( 'layout', '=', 'grid' ),
				array( 'layout_style', '=', array( 'grid-style1', 'grid-style2' ) ),
			),
		);

		$this->controls['show_search'] = array(
			'tab'      => 'content',
			'label'    => esc_html__( 'Show Search', 'ohmylms' ),
			'type'     => 'checkbox',
			'default'  => get_option( 'ohmylms_archive_page_search_is_enabled', 'no' ) === 'yes',
			'required' => array(
				array( 'layout', '=', 'grid' ),
				array( 'layout_style', '=', array( 'grid-style1', 'grid-style2' ) ),
			),
		);

		$this->controls['show_sort'] = array(
			'tab'      => 'content',
			'label'    => esc_html__( 'Show Sort', 'ohmylms' ),
			'type'     => 'checkbox',
			'default'  => get_option( 'ohmylms_archive_page_sorting_is_enabled', 'no' ) === 'yes',
			'required' => array(
				array( 'layout', '=', 'grid' ),
				array( 'layout_style', '=', array( 'grid-style1', 'grid-style2' ) ),
			),
		);

		$this->controls['is_enable_category'] = array(
			'tab'      => 'content',
			'label'    => esc_html__( 'Show Curriculum Tabs', 'ohmylms' ),
			'type'     => 'checkbox',
			'default'  => get_option( 'ohmylms_archive_page_category_is_enabled', 'no' ) === 'yes',
			'required' => array(
				array( 'layout', '=', 'grid' ),
				array( 'layout_style', '=', array( 'grid-style3', 'grid-style4' ) ),
			),
		);

		// Row Settings for grid-style3 and grid-style4.
		$this->controls['row_settings_heading'] = array(
			'tab'      => 'content',
			'label'    => esc_html__( 'Row Settings', 'ohmylms' ),
			'type'     => 'separator',
			'required' => array(
				array( 'layout', '=', 'grid' ),
				array( 'layout_style', '=', array( 'grid-style3', 'grid-style4' ) ),
			),
		);

		$this->controls['course_rows'] = array(
			'tab'      => 'content',
			'label'    => esc_html__( 'Course Rows', 'ohmylms' ),
			'type'     => 'repeater',
			'fields'   => array(
				'row_display_criteria' => array(
					'label'   => esc_html__( 'Select Course Display Criteria', 'ohmylms' ),
					'type'    => 'select',
					'options' => array(
						'all'          => esc_html__( 'All Courses', 'ohmylms' ),
						'recent'       => esc_html__( 'Recent Courses', 'ohmylms' ),
						'top_rated'    => esc_html__( 'Top Rated Courses', 'ohmylms' ),
						'free'         => esc_html__( 'Free Courses', 'ohmylms' ),
						'paid'         => esc_html__( 'Paid Courses', 'ohmylms' ),
						'best_selling' => esc_html__( 'Best Selling Courses', 'ohmylms' ),
					),
					'default' => 'all',
				),
				'row_heading'          => array(
					'label'       => esc_html__( 'Row Heading', 'ohmylms' ),
					'type'        => 'text',
					'default'     => esc_html__( 'All Course', 'ohmylms' ),
					'placeholder' => esc_html__( 'Enter Row Heading', 'ohmylms' ),
				),
			),
			'default'  => array(
				array(
					'row_display_criteria' => 'all',
					'row_heading'          => esc_html__( 'All Course', 'ohmylms' ),
				),
			),
			'required' => array(
				array( 'layout', '=', 'grid' ),
				array( 'layout_style', '=', array( 'grid-style3', 'grid-style4' ) ),
			),
		);
	}

	/**
	 * Set style controls grouped like Elementor
	 */
	private function set_style_controls() {

		// -------- Step 1: Define Groups --------
		$groups = array(
			'wrapper' => esc_html__( 'Wrapper Style', 'ohmylms' ),
			'card'    => esc_html__( 'Card Style', 'ohmylms' ),
			// 'card_header'  => esc_html__( 'Card Header', 'ohmylms' ),
			'title'   => esc_html__( 'Title Style', 'ohmylms' ),
			// 'description'  => esc_html__( 'Description Style', 'ohmylms' ),
			'price'   => esc_html__( 'Price Style', 'ohmylms' ),
			'button'  => esc_html__( 'Button Style', 'ohmylms' ),
		);

		foreach ( $groups as $group => $label ) {
			$this->control_groups[ $group ] = array(
				'title' => $label,
				'tab'   => 'style',
			);
		}

		// -------- Step 2: Add Controls under Groups --------
		// Wrapper
		$this->controls['wrapper_background'] = array(
			'group' => 'wrapper',
			'label' => esc_html__( 'Background', 'ohmylms' ),
			'type'  => 'color',
			'css'   => array(
				array(
					'property' => 'background',
					'selector' => '{{WRAPPER}} .ohmylms-container',
				),
			),
		);
		$this->controls['wrapper_padding']    = array(
			'group' => 'wrapper',
			'label' => esc_html__( 'Padding', 'ohmylms' ),
			'type'  => 'spacing',
			'css'   => array(
				array(
					'property' => 'padding',
					'selector' => '{{WRAPPER}} .ohmylms-container',
				),
			),
		);
		$this->controls['wrapper_margin']     = array(
			'group' => 'wrapper',
			'label' => esc_html__( 'Margin', 'ohmylms' ),
			'type'  => 'spacing',
			'css'   => array(
				array(
					'property' => 'margin',
					'selector' => '{{WRAPPER}} .ohmylms-container',
				),
			),
		);

		// Card.
		$this->controls['card_background'] = array(
			'group' => 'card',
			'label' => esc_html__( 'Background', 'ohmylms' ),
			'type'  => 'color',
			'css'   => array(
				array(
					'property' => 'background',
					'selector' => '{{WRAPPER}} .ohmylms-container .course-card',
				),
			),
		);

		$this->controls['card_border'] = array(
			'group' => 'card',
			'label' => esc_html__( 'Border', 'ohmylms' ),
			'type'  => 'border',
			'css'   => array( array( 'selector' => '{{WRAPPER}} .course-card' ) ),
		);

		$this->controls['card_padding'] = array(
			'group' => 'card',
			'label' => esc_html__( 'Padding', 'ohmylms' ),
			'type'  => 'spacing',
			'css'   => array(
				array(
					'property' => 'padding',
					'selector' => '{{WRAPPER}} .course-card',
				),
			),
		);

		$this->controls['card_box_shadow']       = array(
			'group' => 'card',
			'label' => esc_html__( 'Box Shadow', 'ohmylms' ),
			'type'  => 'box-shadow',
			'css'   => array( array( 'selector' => '{{WRAPPER}} .course-card' ) ),
		);
		$this->controls['card_hover_background'] = array(
			'group' => 'card',
			'label' => esc_html__( 'Hover Background', 'ohmylms' ),
			'type'  => 'color',
			'css'   => array(
				array(
					'property' => 'background',
					'selector' => '{{WRAPPER}} .course-card:hover',
				),
			),
		);

		// Card Header
		// $this->controls['card_header_image_radius'] = [
		// 'group'=>'card_header','label'=>esc_html__('Image Border Radius','ohmylms'),
		// 'type'=>'dimension','css'=>[['property'=>'border-radius','selector'=>'{{WRAPPER}} .course-card .card-header img']],
		// ];
		// $this->controls['card_header_margin'] = [
		// 'group'=>'card_header','label'=>esc_html__('Margin','ohmylms'),
		// 'type'=>'spacing','css'=>[['property'=>'margin','selector'=>'{{WRAPPER}} .course-card .card-header']],
		// ];
		// $this->controls['card_header_padding'] = [
		// 'group'=>'card_header','label'=>esc_html__('Padding','ohmylms'),
		// 'type'=>'spacing','css'=>[['property'=>'padding','selector'=>'{{WRAPPER}} .course-card .card-header']],
		// ];.

		// Title.
		$this->controls['title_typography'] = array(
			'group' => 'title',
			'label' => esc_html__( 'Typography', 'ohmylms' ),
			'type'  => 'typography',
			'css'   => array( array( 'selector' => '{{WRAPPER}} .ohmylms-course-cards .course-card .course-info .ohmylms-loop-course-title' ) ),
		);
		$this->controls['title_color']      = array(
			'group' => 'title',
			'label' => esc_html__( 'Color', 'ohmylms' ),
			'type'  => 'color',
			'css'   => array(
				array(
					'property' => 'color',
					'selector' => '{{WRAPPER}} .course-card .course-info .ohmylms-loop-course-link .ohmylms-loop-course-title',
				),
			),
		);

		$this->controls['title_margin'] = array(
			'group' => 'title',
			'label' => esc_html__( 'Margin', 'ohmylms' ),
			'type'  => 'spacing',
			'css'   => array(
				array(
					'property' => 'margin',
					'selector' => '{{WRAPPER}} .course-card .course-info .ohmylms-loop-course-link .ohmylms-loop-course-title',
				),
			),
		);

		// Description.
		$this->controls['description_typography'] = array(
			'group' => 'description',
			'label' => esc_html__( 'Typography', 'ohmylms' ),
			'type'  => 'typography',
			'css'   => array( array( 'selector' => '{{WRAPPER}} .course-card .course-description' ) ),
		);
		$this->controls['description_color']      = array(
			'group' => 'description',
			'label' => esc_html__( 'Color', 'ohmylms' ),
			'type'  => 'color',
			'css'   => array(
				array(
					'property' => 'color',
					'selector' => '{{WRAPPER}} .course-card .course-description',
				),
			),
		);
		$this->controls['description_margin']     = array(
			'group' => 'description',
			'label' => esc_html__( 'Margin', 'ohmylms' ),
			'type'  => 'spacing',
			'css'   => array(
				array(
					'property' => 'margin',
					'selector' => '{{WRAPPER}} .course-card .course-description',
				),
			),
		);

		// Price.
		$this->controls['price_typography'] = array(
			'group' => 'price',
			'label' => esc_html__( 'Typography', 'ohmylms' ),
			'type'  => 'typography',
			'css'   => array( array( 'selector' => '{{WRAPPER}} .course-card .price .ohmylms-price-amount bdi' ) ),
		);
		$this->controls['price_background'] = array(
			'group' => 'price',
			'label' => esc_html__( 'Background', 'ohmylms' ),
			'type'  => 'color',
			'css'   => array(
				array(
					'property' => 'background',
					'selector' => '{{WRAPPER}} .course-card .ohmylms-price-amount',
				),
			),
		);
		$this->controls['price_color']      = array(
			'group' => 'price',
			'label' => esc_html__( 'Sale Price Text Color', 'ohmylms' ),
			'type'  => 'color',
			'css'   => array(
				array(
					'property' => 'color',
					'selector' => '{{WRAPPER}} .course-card .price del .ohmylms-price-amount bdi',
				),
			),
		);

		$this->controls['price_regular_color'] = array(
			'group' => 'price',
			'label' => esc_html__( 'Regular Price Text Color', 'ohmylms' ),
			'type'  => 'color',
			'css'   => array(
				array(
					'property' => 'color',
					'selector' => '{{WRAPPER}} .course-card .ohmylms-price-amount bdi',
				),
			),
		);

		// Button.
		$this->controls['button_typography']       = array(
			'group' => 'button',
			'label' => esc_html__( 'Typography', 'ohmylms' ),
			'type'  => 'typography',
			'css'   => array( array( 'selector' => '{{WRAPPER}} .course-card .ohmylms-button' ) ),
		);
		$this->controls['button_background']       = array(
			'group' => 'button',
			'label' => esc_html__( 'Background', 'ohmylms' ),
			'type'  => 'color',
			'css'   => array(
				array(
					'property' => 'background',
					'selector' => '{{WRAPPER}} .course-card .ohmylms-button',
				),
			),
		);
		$this->controls['button_color']            = array(
			'group' => 'button',
			'label' => esc_html__( 'Text Color', 'ohmylms' ),
			'type'  => 'color',
			'css'   => array(
				array(
					'property' => 'color',
					'selector' => '{{WRAPPER}} .course-card .ohmylms-button',
				),
			),
		);
		$this->controls['button_padding']          = array(
			'group' => 'button',
			'label' => esc_html__( 'Padding', 'ohmylms' ),
			'type'  => 'spacing',
			'css'   => array(
				array(
					'property' => 'padding',
					'selector' => '{{WRAPPER}} .course-card .ohmylms-button',
				),
			),
		);
		$this->controls['button_border']           = array(
			'group' => 'button',
			'label' => esc_html__( 'Border', 'ohmylms' ),
			'type'  => 'border',
			'css'   => array( array( 'selector' => '{{WRAPPER}} .course-card .ohmylms-button' ) ),
		);
		$this->controls['button_border_radius']    = array(
			'group' => 'button',
			'label' => esc_html__( 'Border Radius', 'ohmylms' ),
			'type'  => 'dimension',
			'css'   => array(
				array(
					'property' => 'border-radius',
					'selector' => '{{WRAPPER}} .course-card .ohmylms-button',
				),
			),
		);
		$this->controls['button_hover_background'] = array(
			'group' => 'button',
			'label' => esc_html__( 'Hover Background', 'ohmylms' ),
			'type'  => 'color',
			'css'   => array(
				array(
					'property' => 'background',
					'selector' => '{{WRAPPER}} .course-card .ohmylms-button:hover',
				),
			),
		);
		$this->controls['button_hover_color']      = array(
			'group' => 'button',
			'label' => esc_html__( 'Hover Text Color', 'ohmylms' ),
			'type'  => 'color',
			'css'   => array(
				array(
					'property' => 'color',
					'selector' => '{{WRAPPER}} .course-card .ohmylms-button:hover',
				),
			),
		);
	}


	/**
	 * Render element on frontend
	 *
	 * @return void
	 */
	public function render() {
		$settings = $this->settings;
		// Convert Bricks settings to shortcode attributes.
		$shortcode_attrs = $this->convert_settings_to_shortcode_attrs( $settings );
		// Use the same wrapper class as shortcode for consistency.
		echo '<div class="ohmylms-container ohmylms ohmylms-page">';

		// Use the shortcode class to render the course list.
		ShortcodeCourseList::output( $shortcode_attrs );

		echo '</div>';
	}

	/**
	 * Convert Bricks settings to shortcode attributes
	 *
	 * @param array $settings Bricks settings.
	 * @return array Shortcode attributes
	 */
	private function convert_settings_to_shortcode_attrs( $settings ) {
		$attrs = array();

		// Content controls (these are the main attributes).
		if ( isset( $settings['posts_per_page'] ) && '' !== $settings['posts_per_page'] ) {
			$attrs['posts_per_page'] = $settings['posts_per_page'];
		}

		if ( isset( $settings['layout'] ) && '' !== $settings['layout'] ) {
			$attrs['layout'] = $settings['layout'];
		}

		if ( isset( $settings['layout_style'] ) && '' !== $settings['layout_style'] ) {
			$attrs['layout_style'] = $settings['layout_style'];
		}

		if ( isset( $settings['columns'] ) && '' !== $settings['columns'] ) {
			// Check if filter is enabled for layout styles 1 & 2.
			$layout_style         = $settings['layout_style'] ?? '';
			$show_filter          = isset( $settings['show_filter'] ) && $settings['show_filter'];
			$is_restricted_layout = in_array( $layout_style, array( 'grid-style1', 'grid-style2' ), true );

			// If filter is enabled for layout styles 1 & 2 and columns is set to 4, force it to 3.
			if ( $show_filter && $is_restricted_layout && '4' === $settings['columns'] ) {
				$attrs['columns']          = '3';
				$this->settings['columns'] = '3'; // Update default to avoid confusion in editor.
			} else {
				$attrs['columns'] = $settings['columns'];
			}
		}
		// Toggle controls - these return true when enabled, false/empty when disabled.
		$attrs['show_filter']        = isset( $settings['show_filter'] ) && $settings['show_filter'] ? 'yes' : 'no';
		$attrs['show_search']        = isset( $settings['show_search'] ) && $settings['show_search'] ? 'yes' : 'no';
		$attrs['show_sort']          = isset( $settings['show_sort'] ) && $settings['show_sort'] ? 'yes' : 'no';
		$attrs['is_enable_category'] = isset( $settings['is_enable_category'] ) && $settings['is_enable_category'] ? 'yes' : 'no';

		// Curriculum items and Course Bundles the list is limited to (comma-separated slugs).
		foreach ( array( 'curriculum', 'bundle' ) as $group ) {
			if ( ! empty( $settings[ $group ] ) ) {
				$attrs[ $group ] = implode( ',', array_map( 'sanitize_key', (array) $settings[ $group ] ) );
			}
		}

		if ( isset( $settings['layout_style'] ) && '' !== $settings['layout_style'] && in_array( $settings['layout_style'], array( 'grid-style3', 'grid-style4' ), true ) ) {
			$attrs['show_filter'] = 'no';
		}

		// Handle course rows for grid-style3 and grid-style4.
		if ( isset( $settings['course_rows'] ) && ! empty( $settings['course_rows'] ) ) {
			$attrs['course_rows'] = $settings['course_rows'];
		}

		// Convert style controls to shortcode attributes
		// Wrapper styles.
		$attrs['wrapper_background'] = $this->extract_color_value( $settings, 'wrapper_background' );
		$attrs['wrapper_padding']    = $this->extract_spacing_value( $settings, 'wrapper_padding' );
		$attrs['wrapper_margin']     = $this->extract_spacing_value( $settings, 'wrapper_margin' );

		// Card styles.
		$attrs['card_background']       = $this->extract_color_value( $settings, 'card_background' );
		$attrs['card_hover_background'] = $this->extract_color_value( $settings, 'card_hover_background' );
		$attrs['card_padding']          = $this->extract_spacing_value( $settings, 'card_padding' );
		$attrs['card_box_shadow']       = $this->extract_box_shadow_value( $settings, 'card_box_shadow' );

		// Extract border values (width, style, color, radius).
		$border_values = $this->extract_border_value( $settings, 'card_border' );
		if ( ! empty( $border_values ) ) {
			$attrs = array_merge( $attrs, $border_values );
		}

		// Title styles.
		$attrs['title_color']  = $this->extract_color_value( $settings, 'title_color' );
		$attrs['title_margin'] = $this->extract_spacing_value( $settings, 'title_margin' );
		$attrs                 = array_merge( $attrs, $this->extract_typography_value( $settings, 'title_typography', 'title_typography_' ) );

		// Price styles.
		$attrs['price_color']         = $this->extract_color_value( $settings, 'price_color' );
		$attrs['price_regular_color'] = $this->extract_color_value( $settings, 'price_regular_color' );
		$attrs['price_background']    = $this->extract_color_value( $settings, 'price_background' );
		$attrs                        = array_merge( $attrs, $this->extract_typography_value( $settings, 'price_typography', 'price_typography_' ) );

		// Button styles.
		$attrs['button_background']       = $this->extract_color_value( $settings, 'button_background' );
		$attrs['button_hover_background'] = $this->extract_color_value( $settings, 'button_hover_background' );
		$attrs['button_color']            = $this->extract_color_value( $settings, 'button_color' );
		$attrs['button_hover_color']      = $this->extract_color_value( $settings, 'button_hover_color' );
		$attrs['button_padding']          = $this->extract_spacing_value( $settings, 'button_padding' );
		$attrs['button_border_radius']    = $this->extract_dimension_value( $settings, 'button_border_radius' );

		// Extract button border.
		$button_border_values = $this->extract_button_border_value( $settings, 'button_border' );
		if ( ! empty( $button_border_values ) ) {
			$attrs = array_merge( $attrs, $button_border_values );
		}

		$attrs = array_merge( $attrs, $this->extract_typography_value( $settings, 'button_typography', 'button_typography_' ) );

		// Remove empty values.
		$attrs = array_filter(
			$attrs,
			function ( $value ) {
				return '' !== $value && null !== $value && $value !== array();
			}
		);

		return $attrs;
	}

	/**
	 * Extract color value from Bricks settings
	 *
	 * @param array  $settings Settings array.
	 * @param string $key Setting key.
	 * @return string Color value
	 */
	private function extract_color_value( $settings, $key ) {
		if ( isset( $settings[ $key ]['hex'] ) && ! empty( $settings[ $key ]['hex'] ) ) {
			return $settings[ $key ]['hex'];
		}
		if ( isset( $settings[ $key ]['rgb'] ) && ! empty( $settings[ $key ]['rgb'] ) ) {
			return $settings[ $key ]['rgb'];
		}
		return '';
	}

	/**
	 * Extract spacing value from Bricks settings
	 *
	 * @param array  $settings Settings array.
	 * @param string $key Setting key.
	 * @return string Spacing value
	 */
	private function extract_spacing_value( $settings, $key ) {
		if ( ! isset( $settings[ $key ] ) || ! is_array( $settings[ $key ] ) ) {
			return '';
		}

		$spacing = $settings[ $key ];
		$values  = array();

		// Extract top, right, bottom, left values.
		$sides = array( 'top', 'right', 'bottom', 'left' );
		foreach ( $sides as $side ) {
			if ( isset( $spacing[ $side ] ) && '' !== $spacing[ $side ] ) {
				$unit     = isset( $spacing['unit'] ) ? $spacing['unit'] : 'px';
				$values[] = $spacing[ $side ] . $unit;
			} else {
				$values[] = '0';
			}
		}

		return ! empty( $values ) ? implode( ' ', $values ) : '';
	}

	/**
	 * Extract dimension value from Bricks settings
	 *
	 * @param array  $settings Settings array.
	 * @param string $key Setting key.
	 * @return string Dimension value
	 */
	private function extract_dimension_value( $settings, $key ) {
		if ( ! isset( $settings[ $key ] ) ) {
			return '';
		}

		$dimension = $settings[ $key ];

		// If it's a simple value, return it.
		if ( ! is_array( $dimension ) ) {
			return $dimension;
		}

		// Handle dimension with unit.
		if ( isset( $dimension['top'] ) || isset( $dimension['right'] ) || isset( $dimension['bottom'] ) || isset( $dimension['left'] ) ) {
			$unit   = isset( $dimension['unit'] ) ? $dimension['unit'] : 'px';
			$values = array();
			$sides  = array( 'top', 'right', 'bottom', 'left' );
			foreach ( $sides as $side ) {
				if ( isset( $dimension[ $side ] ) && '' !== $dimension[ $side ] ) {
					$values[] = $dimension[ $side ] . $unit;
				} else {
					$values[] = '0';
				}
			}
			return ! empty( $values ) ? implode( ' ', $values ) : '';
		}

		return '';
	}

	/**
	 * Extract border value from Bricks settings
	 *
	 * @param array  $settings Settings array.
	 * @param string $key Setting key.
	 * @return array Border attributes
	 */
	private function extract_border_value( $settings, $key ) {
		$attrs = array();

		if ( ! isset( $settings[ $key ] ) || ! is_array( $settings[ $key ] ) ) {
			return $attrs;
		}

		$border = $settings[ $key ];

		// Extract border width.
		if ( isset( $border['width'] ) ) {
			$width = $border['width'];
			if ( is_array( $width ) ) {
				$unit   = isset( $width['unit'] ) ? $width['unit'] : 'px';
				$sides  = array( 'top', 'right', 'bottom', 'left' );
				$values = array();
				foreach ( $sides as $side ) {
					if ( isset( $width[ $side ] ) && '' !== $width[ $side ] ) {
						$values[] = $width[ $side ] . $unit;
					}
				}
				if ( ! empty( $values ) ) {
					$attrs['card_border_width'] = implode( ' ', $values );
				}
			} elseif ( ! empty( $width ) ) {
				$attrs['card_border_width'] = $width;
			}
		}

		// Extract border style.
		if ( isset( $border['style'] ) && ! empty( $border['style'] ) ) {
			$attrs['card_border_type'] = $border['style'];
		}

		// Extract border color.
		if ( isset( $border['color']['hex'] ) && ! empty( $border['color']['hex'] ) ) {
			$attrs['card_border_color'] = $border['color']['hex'];
		} elseif ( isset( $border['color']['rgb'] ) && ! empty( $border['color']['rgb'] ) ) {
			$attrs['card_border_color'] = $border['color']['rgb'];
		}

		// Extract border radius.
		if ( isset( $border['radius'] ) ) {
			$radius = $border['radius'];
			if ( is_array( $radius ) ) {
				$unit   = isset( $radius['unit'] ) ? $radius['unit'] : 'px';
				$sides  = array( 'top', 'right', 'bottom', 'left' );
				$values = array();
				foreach ( $sides as $side ) {
					if ( isset( $radius[ $side ] ) && '' !== $radius[ $side ] ) {
						$values[] = $radius[ $side ] . $unit;
					} else {
						$values[] = '0';
					}
				}
				if ( ! empty( $values ) ) {
					$attrs['card_border_radius'] = implode( ' ', $values );
				}
			} elseif ( ! empty( $radius ) ) {
				$attrs['card_border_radius'] = $radius;
			}
		}

		return $attrs;
	}

	/**
	 * Extract button border value from Bricks settings
	 *
	 * @param array  $settings Settings array.
	 * @param string $key Setting key.
	 * @return array Border attributes
	 */
	private function extract_button_border_value( $settings, $key ) {
		$attrs = array();

		if ( ! isset( $settings[ $key ] ) || ! is_array( $settings[ $key ] ) ) {
			return $attrs;
		}

		$border = $settings[ $key ];

		// Build complete border string (width style color).
		$border_parts = array();

		// Get width.
		$width = '1px';
		if ( isset( $border['width'] ) ) {
			if ( is_array( $border['width'] ) ) {
				$unit = isset( $border['width']['unit'] ) ? $border['width']['unit'] : 'px';
				if ( isset( $border['width']['top'] ) && '' !== $border['width']['top'] ) {
					$width = $border['width']['top'] . $unit;
				}
			} elseif ( ! empty( $border['width'] ) ) {
				$width = $border['width'];
			}
		}
		$border_parts[] = $width;

		// Get style.
		$style = 'solid';
		if ( isset( $border['style'] ) && ! empty( $border['style'] ) ) {
			$style = $border['style'];
		}
		$border_parts[] = $style;

		// Get color.
		if ( isset( $border['color']['hex'] ) && ! empty( $border['color']['hex'] ) ) {
			$border_parts[]                     = $border['color']['hex'];
			$attrs['button_hover_border_color'] = $border['color']['hex'];
		} elseif ( isset( $border['color']['rgb'] ) && ! empty( $border['color']['rgb'] ) ) {
			$border_parts[]                     = $border['color']['rgb'];
			$attrs['button_hover_border_color'] = $border['color']['rgb'];
		}

		if ( count( $border_parts ) >= 2 ) {
			$attrs['button_border'] = implode( ' ', $border_parts );
		}

		return $attrs;
	}

	/**
	 * Extract box shadow value from Bricks settings
	 *
	 * @param array  $settings Settings array.
	 * @param string $key Setting key.
	 * @return string Box shadow value
	 */
	private function extract_box_shadow_value( $settings, $key ) {
		if ( ! isset( $settings[ $key ] ) || ! is_array( $settings[ $key ] ) ) {
			return '';
		}

		$shadow = $settings[ $key ];
		$parts  = array();

		// Extract values.
		if ( isset( $shadow['x'] ) ) {
			$parts[] = $shadow['x'] . 'px';
		}
		if ( isset( $shadow['y'] ) ) {
			$parts[] = $shadow['y'] . 'px';
		}
		if ( isset( $shadow['blur'] ) ) {
			$parts[] = $shadow['blur'] . 'px';
		}
		if ( isset( $shadow['spread'] ) ) {
			$parts[] = $shadow['spread'] . 'px';
		}
		if ( isset( $shadow['color']['hex'] ) ) {
			$parts[] = $shadow['color']['hex'];
		}

		return ! empty( $parts ) ? implode( ' ', $parts ) : '';
	}

	/**
	 * Extract typography value from Bricks settings
	 *
	 * @param array  $settings Settings array.
	 * @param string $key Setting key.
	 * @param string $prefix Attribute prefix.
	 * @return array Typography attributes
	 */
	private function extract_typography_value( $settings, $key, $prefix ) {
		$attrs = array();

		if ( ! isset( $settings[ $key ] ) || ! is_array( $settings[ $key ] ) ) {
			return $attrs;
		}

		$typography = $settings[ $key ];

		// Map Bricks typography keys to shortcode keys.
		$mapping = array(
			'font-family'     => 'font_family',
			'font-size'       => 'font_size',
			'font-weight'     => 'font_weight',
			'text-transform'  => 'text_transform',
			'font-style'      => 'font_style',
			'text-decoration' => 'text_decoration',
			'line-height'     => 'line_height',
			'letter-spacing'  => 'letter_spacing',
			'word-spacing'    => 'word_spacing',
		);

		foreach ( $mapping as $bricks_key => $shortcode_key ) {
			if ( isset( $typography[ $bricks_key ] ) && '' !== $typography[ $bricks_key ] ) {
				$value = $typography[ $bricks_key ];
				// Add unit if it's a numeric value and doesn't have one.
				if ( in_array( $bricks_key, array( 'font-size', 'line-height', 'letter-spacing', 'word-spacing' ), true ) ) {
					if ( is_numeric( $value ) ) {
						$value .= 'px';
					}
				}
				$attrs[ $prefix . $shortcode_key ] = $value;
			}
		}

		return $attrs;
	}

	/**
	 * Get course instructors for dropdown options
	 *
	 * @return array
	 */
	private function get_course_instructors() {
		$instructors = get_users(
			array(
				'role__in' => array( 'ohmylms_instructor', 'administrator' ),
				'fields'   => array( 'ID', 'display_name' ),
			)
		);

		$options = array();
		foreach ( $instructors as $instructor ) {
			$options[ $instructor->ID ] = $instructor->display_name;
		}

		return $options;
	}

	/**
	 * Add CSS variables for OhMyLMS styling
	 * Ensures variables are available in Bricks builder
	 *
	 * @return void
	 */
	private function add_css_variables() {
		// Check if already added to avoid duplicates.
		if ( wp_style_is( 'ohmylms-css-variables', 'enqueued' ) ) {
			return;
		}

		$primary_color       = get_option( 'ohmylms_primary_color_scheme' );
		$primary_hover_color = get_option( 'ohmylms_primary_hover_color_scheme' );
		$heading_color       = get_option( 'ohmylms_heading_color_scheme' );
		$body_text_color     = get_option( 'ohmylms_body_text_color_scheme' );
		$progressbar_color   = get_option( 'ohmylms_body_progress_color_scheme' );

		$primary_color     = isset( $primary_color ) && ! empty( $primary_color ) ? $primary_color : '#6e42d3';
		$primary_color_rgb = ohmylms_hex_to_rgb( $primary_color );

		$css_variables = ':root {
			--ohmylms-primary-color: ' . esc_html( $primary_color ) . ';
			--ohmylms-primary-color-rgb: ' . esc_html( $primary_color_rgb ) . ';
			--ohmylms-heading-color: ' . ( isset( $heading_color ) && ! empty( $heading_color ) ? esc_html( $heading_color ) : '#000D25' ) . ';
			--ohmylms-body-text-color: ' . ( isset( $body_text_color ) && ! empty( $body_text_color ) ? esc_html( $body_text_color ) : '#52525B' ) . ';
			--ohmylms-progressbar-color: ' . ( $progressbar_color ? esc_html( $progressbar_color ) : '#F85656' ) . ';
			--ohmylms-outline-color: var(--ohmylms-primary-color);
		}';

		// Register and enqueue inline styles.
		wp_register_style( 'ohmylms-css-variables', false );
		wp_enqueue_style( 'ohmylms-css-variables' );
		wp_add_inline_style( 'ohmylms-css-variables', $css_variables );
	}


	/**
	 * Enqueue scripts for Course list element.
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'ohmylms-frontend' );
		wp_enqueue_script( 'ohmylms-add-to-cart' );

		wp_enqueue_style( 'ohmylms-frontend' );
		wp_enqueue_style( 'ohmylms-general' );
		wp_enqueue_script( 'ohmylms-slick' );

		// Ensure CSS variables are available in Bricks builder.
		$this->add_css_variables();

		$layout       = isset( $this->settings['layout'] ) ? $this->settings['layout'] : 'grid';
		$layout_style = isset( $this->settings['layout_style'] ) ? $this->settings['layout_style'] : 'grid-style1';

		if ( 'grid' === $layout && ( 'grid-style3' === $layout_style || 'grid-style4' === $layout_style ) && isset( $_GET['bricks'] ) && 'run' === $_GET['bricks'] ) {
			$columns       = isset( $this->settings['columns'] ) ? (int) $this->settings['columns'] : 4;
			$inline_script = "
			jQuery(document).ready(function($) {
				function initSlickCarousel() {
					if (typeof $.fn.slick !== 'undefined') {
						$('.ohmylms-course-cards-carousel').each(function(){
							if (!$(this).hasClass('slick-initialized')) {
								let colPerRow = $(this).data('col') || {$columns};
								$(this).slick({
									infinite: false,
									slidesToShow: colPerRow,
									slidesToScroll: 1,
									prevArrow: '<button class=\"slick-prev\" aria-label=\"Previous\" type=\"button\"><svg width=\"9\" height=\"18\" fill=\"none\" viewBox=\"0 0 9 18\" xmlns=\"http://www.w3.org/2000/svg\"><path fill=\"#A1A1AA\" d=\"M1.655 6.526L7.01 1.171a1.167 1.167 0 111.645 1.657L3.288 8.171a1.167 1.167 0 000 1.657l5.367 5.343a1.167 1.167 0 11-1.645 1.657l-5.355-5.355a3.5 3.5 0 010-4.947z\"/></svg></button>',
									nextArrow: '<button class=\"slick-next\" aria-label=\"Next\" type=\"button\"><svg width=\"9\" height=\"18\" fill=\"none\" viewBox=\"0 0 9 18\" xmlns=\"http://www.w3.org/2000/svg\"><path fill=\"#A1A1AA\" d=\"M7.345 6.526L1.99 1.171A1.167 1.167 0 10.345 2.828l5.367 5.343a1.167 1.167 0 010 1.657L.345 15.171a1.167 1.167 0 101.645 1.657l5.355-5.355a3.5 3.5 0 000-4.947z\"/></svg></button>',
									responsive: [
										{
											breakpoint: 1200,
											settings: {
												slidesToShow: colPerRow < 3 ? colPerRow : 3,
											}
										},
										{
											breakpoint: 768,
											settings: {
												slidesToShow: colPerRow < 2 ? colPerRow : 2,
											}
										},
										{
											breakpoint: 576,
											settings: {
												slidesToShow: 1,
											}
										}
									]
								}).addClass('ohmylms-initialized').css('display', 'block');
							}
						});
					} else {
						setTimeout(initSlickCarousel, 100);
					}
				}
				
				initSlickCarousel();
				
				if (window.wp && window.wp.data) {
					let timeoutId;
					window.wp.data.subscribe(function() {
						clearTimeout(timeoutId);
						timeoutId = setTimeout(initSlickCarousel, 500);
					});
				}
				
				var observer = new MutationObserver(function(mutations) {
					mutations.forEach(function(mutation) {
						if (mutation.type === 'childList') {
							var addedNodes = $(mutation.addedNodes);
							if (addedNodes.find('.ohmylms-course-cards-carousel').length || addedNodes.hasClass('ohmylms-course-cards-carousel')) {
								setTimeout(initSlickCarousel, 300);
							}
						}
					});
				});
				
				observer.observe(document.body, {
					childList: true,
					subtree: true
				});
			});
			";
			wp_add_inline_script( 'ohmylms-slick', $inline_script );
		}
	}
}
