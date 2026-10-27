<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * OhMyLMS Course List Widget for Elementor
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
use OhMyLMS\Shortcodes\ShortcodeCourseList;

defined( 'ABSPATH' ) || exit;

/**
 * CourseListWidget class
 */
class CourseListWidget extends Widget_Base {

	/**
	 * Get widget name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'ohmylms-course-list';
	}

	/**
	 * Get widget title
	 *
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'OhMyLMS Course List', 'ohmylms' );
	}

	/**
	 * Get widget icon
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-posts-grid';
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
		return array( 'course', 'list', 'grid', 'courses', 'creator', 'lms' );
	}

	/**
	 * Get script dependencies
	 *
	 * @return array
	 */
	public function get_script_depends() {
		return array( 'ohmylms-frontend', 'ohmylms-add-to-cart' );
	}

	/**
	 * Get style dependencies
	 *
	 * @return array
	 */
	public function get_style_depends() {
		return array( 'ohmylms-frontend' );
	}

	/**
	 * Register widget controls
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->register_content_controls();
		$this->register_wrapper_style_controls();
		$this->register_card_style_controls();
		$this->register_card_header_style_controls();
		$this->register_title_style_controls();
		$this->register_description_style_controls();
		$this->register_course_meta_style_controls();
		$this->register_cohort_meta_style_controls();
		$this->register_price_style_controls();
		$this->register_button_style_controls();
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

		// Layout Settings Section.
		$this->add_control(
			'layout_heading',
			array(
				'label'     => esc_html__( 'Layout Settings', 'ohmylms' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'   => esc_html__( 'Layout', 'ohmylms' ),
				'type'    => Controls_Manager::SELECT,
				'default' => get_option( 'ohmylms_archive_page_layout', 'grid' ),
				'options' => array(
					'grid' => esc_html__( 'Grid', 'ohmylms' ),
					'list' => esc_html__( 'List', 'ohmylms' ),
				),
			)
		);

		// Build layout style options.
		$layout_options = array(
			'grid-style1' => esc_html__( 'Layout 1', 'ohmylms' ),
		);

		// Layouts 2-4.
			$layout_options['grid-style2'] = esc_html__( 'Layout 2', 'ohmylms' );
			$layout_options['grid-style3'] = esc_html__( 'Layout 3', 'ohmylms' );
			$layout_options['grid-style4'] = esc_html__( 'Layout 4', 'ohmylms' );

		$this->add_control(
			'layout_style',
			array(
				'label'     => esc_html__( 'Layout Style', 'ohmylms' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => get_option( 'ohmylms_archive_page_layout_style', 'grid-style1' ),
				'options'   => $layout_options,
				'condition' => array(
					'layout' => 'grid',
				),
			)
		);

		$this->add_control(
			'columns',
			array(
				'label'     => esc_html__( 'Columns Per Row', 'ohmylms' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => get_option( 'ohmylms_columns_per_row', '3' ),
				'options'   => array(
					'1' => esc_html__( '1 Column', 'ohmylms' ),
					'2' => esc_html__( '2 Columns', 'ohmylms' ),
					'3' => esc_html__( '3 Columns', 'ohmylms' ),
					'4' => esc_html__( '4 Columns', 'ohmylms' ),
				),
				'condition' => array(
					'layout' => 'grid',
				),
			)
		);

		$this->add_control(
			'posts_per_page',
			array(
				'label'   => esc_html__( 'Courses Per Page', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => get_option( 'ohmylms_courses_per_page', 10 ),
				'min'     => 1,
				'max'     => 100,
			)
		);

		$this->add_control(
			'curriculum',
			array(
				'label'       => esc_html__( 'Curriculum', 'ohmylms' ),
				'description' => esc_html__( 'Only show courses placed under these curriculum items. Leave empty to show all.', 'ohmylms' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => \OhMyLMS\Curriculum\Placement::item_options(),
			)
		);

		$this->add_control(
			'bundle',
			array(
				'label'       => esc_html__( 'Course bundles', 'ohmylms' ),
				'description' => esc_html__( 'Only show courses in these Course Bundles. Leave empty to show all.', 'ohmylms' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => \OhMyLMS\Curriculum\Placement::bundle_options(),
			)
		);

		// Feature Toggles Section.
		$this->add_control(
			'features_heading',
			array(
				'label'     => esc_html__( 'Feature Toggles', 'ohmylms' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array(
					'layout' => 'grid',
				),
			)
		);

		$this->add_control(
			'show_filter',
			array(
				'label'        => esc_html__( 'Show Filter', 'ohmylms' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Show', 'ohmylms' ),
				'label_off'    => esc_html__( 'Hide', 'ohmylms' ),
				'return_value' => 'yes',
				'default'      => get_option( 'ohmylms_archive_page_filter_is_enabled', 'no' ),
				'condition'    => array(
					'layout_style' => array( 'grid-style1', 'grid-style2' ),
					'layout'       => 'grid',
				),
			)
		);

		$this->add_control(
			'show_search',
			array(
				'label'        => esc_html__( 'Show Search', 'ohmylms' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Show', 'ohmylms' ),
				'label_off'    => esc_html__( 'Hide', 'ohmylms' ),
				'return_value' => 'yes',
				'default'      => get_option( 'ohmylms_archive_page_search_is_enabled', 'no' ),
				'condition'    => array(
					'layout_style' => array( 'grid-style1', 'grid-style2' ),
					'layout'       => 'grid',
				),
			)
		);

		$this->add_control(
			'show_sort',
			array(
				'label'        => esc_html__( 'Show Sort', 'ohmylms' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Show', 'ohmylms' ),
				'label_off'    => esc_html__( 'Hide', 'ohmylms' ),
				'return_value' => 'yes',
				'default'      => get_option( 'ohmylms_archive_page_sorting_is_enabled', 'no' ),
				'condition'    => array(
					'layout_style' => array( 'grid-style1', 'grid-style2' ),
					'layout'       => 'grid',
				),
			)
		);

		$this->add_control(
			'is_enable_category',
			array(
				'label'        => esc_html__( 'Show Curriculum Tabs', 'ohmylms' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Show', 'ohmylms' ),
				'label_off'    => esc_html__( 'Hide', 'ohmylms' ),
				'return_value' => 'yes',
				'default'      => get_option( 'ohmylms_archive_page_category_is_enabled', 'no' ),
				'condition'    => array(
					'layout_style' => array( 'grid-style3', 'grid-style4' ),
					'layout'       => 'grid',
				),
			)
		);

		// Row Settings Section for grid-style3 and grid-style4.
		$this->add_control(
			'row_settings_heading',
			array(
				'label'     => esc_html__( 'Row Settings', 'ohmylms' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array(
					'layout_style' => array( 'grid-style3', 'grid-style4' ),
					'layout'       => 'grid',
				),
			)
		);

		$this->add_control(
			'course_rows',
			array(
				'label'       => esc_html__( 'Course Rows', 'ohmylms' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => array(
					array(
						'name'    => 'row_display_criteria',
						'label'   => esc_html__( 'Select Course Display Criteria', 'ohmylms' ),
						'type'    => Controls_Manager::SELECT,
						'default' => 'all',
						'options' => array(
							'all'          => esc_html__( 'All Courses', 'ohmylms' ),
							'recent'       => esc_html__( 'Recent Courses', 'ohmylms' ),
							'top_rated'    => esc_html__( 'Top Rated Courses', 'ohmylms' ),
							'free'         => esc_html__( 'Free Courses', 'ohmylms' ),
							'paid'         => esc_html__( 'Paid Courses', 'ohmylms' ),
							'best_selling' => esc_html__( 'Best Selling Courses', 'ohmylms' ),
						),
					),
					array(
						'name'        => 'row_heading',
						'label'       => esc_html__( 'Row Heading', 'ohmylms' ),
						'type'        => Controls_Manager::TEXT,
						'default'     => esc_html__( 'All Course', 'ohmylms' ),
						'placeholder' => esc_html__( 'Enter Row Heading', 'ohmylms' ),
					),
				),
				'default'     => array(
					array(
						'row_display_criteria' => 'all',
						'row_heading'          => esc_html__( 'All Course', 'ohmylms' ),
					),
				),
				'title_field' => '{{{ row_heading }}}',
				'condition'   => array(
					'layout_style' => array( 'grid-style3', 'grid-style4' ),
					'layout'       => 'grid',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register wrapper style controls
	 *
	 * @return void
	 */
	private function register_wrapper_style_controls() {
		$this->start_controls_section(
			'wrapper_section',
			array(
				'label' => esc_html__( 'Wrapper Style', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'wrapper_background',
			array(
				'label'     => esc_html__( 'Background Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .ohmylms-container, {{WRAPPER}} .ohmylms-course-cards' => 'background: {{VALUE}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'wrapper_margin',
			array(
				'label'      => esc_html__( 'Margin', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ohmylms-container, {{WRAPPER}} .ohmylms-course-cards' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'wrapper_padding',
			array(
				'label'      => esc_html__( 'Padding', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .ohmylms-container, {{WRAPPER}} .ohmylms-course-cards' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'grid_gap',
			array(
				'label'      => esc_html__( 'Grid Gap', 'ohmylms' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .ohmylms-course-cards' => 'gap: {{SIZE}}{{UNIT}} !important;',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register card style controls
	 *
	 * @return void
	 */
	private function register_card_style_controls() {
		$this->start_controls_section(
			'card_section',
			array(
				'label' => esc_html__( 'Card Style', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'card_background',
			array(
				'label'     => esc_html__( 'Background Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .course-card' => 'background: {{VALUE}} !important;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'card_border',
				'selector' => '{{WRAPPER}} .course-card',
			)
		);

		$this->add_responsive_control(
			'card_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .course-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'card_padding',
			array(
				'label'      => esc_html__( 'Padding', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .course-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'card_margin',
			array(
				'label'      => esc_html__( 'Margin', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .course-card' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'card_box_shadow',
				'selector' => '{{WRAPPER}} .course-card',
			)
		);

		// Hover Effects.
		$this->add_control(
			'card_hover_heading',
			array(
				'label'     => esc_html__( 'Hover Effects', 'ohmylms' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'card_hover_background',
			array(
				'label'     => esc_html__( 'Hover Background Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .course-card:hover' => 'background: {{VALUE}} !important;',
				),
			)
		);

		$this->add_control(
			'card_hover_border_color',
			array(
				'label'     => esc_html__( 'Hover Border Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .course-card:hover' => 'border-color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'card_hover_box_shadow',
				'selector' => '{{WRAPPER}} .course-card:hover',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register card header style controls
	 *
	 * @return void
	 */
	private function register_card_header_style_controls() {
		$this->start_controls_section(
			'card_header_section',
			array(
				'label' => esc_html__( 'Card Header Style', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'card_header_margin',
			array(
				'label'      => esc_html__( 'Margin', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .course-card .course-thumbnail, {{WRAPPER}} .course-card .course-header' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'card_header_padding',
			array(
				'label'      => esc_html__( 'Padding', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .course-card .course-thumbnail, {{WRAPPER}} .course-card .course-header' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'card_header_image_border_radius',
			array(
				'label'      => esc_html__( 'Image Border Radius', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .course-card .course-thumbnail img, {{WRAPPER}} .course-card .course-header img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register title style controls
	 *
	 * @return void
	 */
	private function register_title_style_controls() {
		$this->start_controls_section(
			'title_section',
			array(
				'label' => esc_html__( 'Title Style', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'title_color',
			array(
				'label'     => esc_html__( 'Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .course-card h1, {{WRAPPER}} .course-card h2, {{WRAPPER}} .course-card h3, {{WRAPPER}} .course-card .course-title, {{WRAPPER}} .course-card .course-name' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'title_typography',
				'selector' => '{{WRAPPER}} .course-card h1, {{WRAPPER}} .course-card h2, {{WRAPPER}} .course-card h3, {{WRAPPER}} .course-card .course-title, {{WRAPPER}} .course-card .course-name',
			)
		);

		$this->add_responsive_control(
			'title_margin',
			array(
				'label'      => esc_html__( 'Margin', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .course-card h1, {{WRAPPER}} .course-card h2, {{WRAPPER}} .course-card h3, {{WRAPPER}} .course-card .course-title, {{WRAPPER}} .course-card .course-name' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register description style controls
	 *
	 * @return void
	 */
	private function register_description_style_controls() {
		$this->start_controls_section(
			'description_section',
			array(
				'label' => esc_html__( 'Description Style', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'description_color',
			array(
				'label'     => esc_html__( 'Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .course-card .course-excerpt, {{WRAPPER}} .course-card .course-description, {{WRAPPER}} .course-card .course-content p' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'description_typography',
				'selector' => '{{WRAPPER}} .course-card .course-excerpt, {{WRAPPER}} .course-card .course-description, {{WRAPPER}} .course-card .course-content p',
			)
		);

		$this->add_responsive_control(
			'description_margin',
			array(
				'label'      => esc_html__( 'Margin', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .course-card .course-excerpt, {{WRAPPER}} .course-card .course-description, {{WRAPPER}} .course-card .course-content p' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register course meta style controls
	 *
	 * @return void
	 */
	private function register_course_meta_style_controls() {
		$this->start_controls_section(
			'course_meta_section',
			array(
				'label' => esc_html__( 'Course Meta Style', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'course_meta_background',
			array(
				'label'     => esc_html__( 'Background Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .course-card .course-meta, {{WRAPPER}} .course-card .course-info' => 'background: {{VALUE}} !important;',
				),
			)
		);

		$this->add_control(
			'course_meta_color',
			array(
				'label'     => esc_html__( 'Text Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .course-card .course-meta, {{WRAPPER}} .course-card .course-info' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'course_meta_typography',
				'selector' => '{{WRAPPER}} .course-card .course-meta, {{WRAPPER}} .course-card .course-info',
			)
		);

		$this->add_responsive_control(
			'course_meta_padding',
			array(
				'label'      => esc_html__( 'Padding', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .course-card .course-meta, {{WRAPPER}} .course-card .course-info' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'course_meta_margin',
			array(
				'label'      => esc_html__( 'Margin', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .course-card .course-meta, {{WRAPPER}} .course-card .course-info' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'course_meta_border',
				'selector' => '{{WRAPPER}} .course-card .course-meta, {{WRAPPER}} .course-card .course-info',
			)
		);

		$this->add_responsive_control(
			'course_meta_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .course-card .course-meta, {{WRAPPER}} .course-card .course-info' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'course_meta_row_gap',
			array(
				'label'      => esc_html__( 'Row Gap', 'ohmylms' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 50,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .course-card .course-meta, {{WRAPPER}} .course-card .course-info' => 'row-gap: {{SIZE}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'course_meta_column_gap',
			array(
				'label'      => esc_html__( 'Column Gap', 'ohmylms' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 50,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .course-card .course-meta, {{WRAPPER}} .course-card .course-info' => 'column-gap: {{SIZE}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'course_meta_icon_size',
			array(
				'label'      => esc_html__( 'Icon Size', 'ohmylms' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 10,
						'max' => 50,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .course-card .course-meta i, {{WRAPPER}} .course-card .course-meta .icon, {{WRAPPER}} .course-card .course-info i, {{WRAPPER}} .course-card .course-info .icon' => 'font-size: {{SIZE}}{{UNIT}} !important; width: {{SIZE}}{{UNIT}} !important; height: {{SIZE}}{{UNIT}} !important;',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register cohort meta style controls
	 *
	 * @return void
	 */
	private function register_cohort_meta_style_controls() {
		$this->start_controls_section(
			'cohort_meta_section',
			array(
				'label' => esc_html__( 'Cohort Meta Style', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'cohort_meta_background',
			array(
				'label'     => esc_html__( 'Background Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .course-card .cohort-meta, {{WRAPPER}} .course-card .cohort-info' => 'background: {{VALUE}} !important;',
				),
			)
		);

		$this->add_control(
			'cohort_meta_color',
			array(
				'label'     => esc_html__( 'Text Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .course-card .cohort-meta, {{WRAPPER}} .course-card .cohort-info' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'cohort_meta_typography',
				'selector' => '{{WRAPPER}} .course-card .cohort-meta, {{WRAPPER}} .course-card .cohort-info',
			)
		);

		$this->add_responsive_control(
			'cohort_meta_padding',
			array(
				'label'      => esc_html__( 'Padding', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .course-card .cohort-meta, {{WRAPPER}} .course-card .cohort-info' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'cohort_meta_margin',
			array(
				'label'      => esc_html__( 'Margin', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .course-card .cohort-meta, {{WRAPPER}} .course-card .cohort-info' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'cohort_meta_border',
				'selector' => '{{WRAPPER}} .course-card .cohort-meta, {{WRAPPER}} .course-card .cohort-info',
			)
		);

		$this->add_responsive_control(
			'cohort_meta_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .course-card .cohort-meta, {{WRAPPER}} .course-card .cohort-info' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'cohort_meta_row_gap',
			array(
				'label'      => esc_html__( 'Row Gap', 'ohmylms' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 50,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .course-card .cohort-meta, {{WRAPPER}} .course-card .cohort-info' => 'row-gap: {{SIZE}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'cohort_meta_icon_size',
			array(
				'label'      => esc_html__( 'Icon Size', 'ohmylms' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 10,
						'max' => 50,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .course-card .cohort-meta i, {{WRAPPER}} .course-card .cohort-meta .icon, {{WRAPPER}} .course-card .cohort-info i, {{WRAPPER}} .course-card .cohort-info .icon' => 'font-size: {{SIZE}}{{UNIT}} !important; width: {{SIZE}}{{UNIT}} !important; height: {{SIZE}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'cohort_meta_icon_spacing',
			array(
				'label'      => esc_html__( 'Icon Spacing', 'ohmylms' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 30,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .course-card .cohort-meta i, {{WRAPPER}} .course-card .cohort-meta .icon, {{WRAPPER}} .course-card .cohort-info i, {{WRAPPER}} .course-card .cohort-info .icon' => 'margin-right: {{SIZE}}{{UNIT}} !important;',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register price style controls
	 *
	 * @return void
	 */
	private function register_price_style_controls() {
		$this->start_controls_section(
			'price_section',
			array(
				'label' => esc_html__( 'Price Style', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'price_background',
			array(
				'label'     => esc_html__( 'Background Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .course-card .course-price, {{WRAPPER}} .course-card .price' => 'background: {{VALUE}} !important;',
				),
			)
		);

		$this->add_control(
			'price_color',
			array(
				'label'     => esc_html__( 'Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .course-card .course-price, {{WRAPPER}} .course-card .price' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_control(
			'price_regular_color',
			array(
				'label'     => esc_html__( 'Regular Price Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .course-card .course-price .regular-price, {{WRAPPER}} .course-card .price .regular-price, {{WRAPPER}} .course-card .course-price del, {{WRAPPER}} .course-card .price del' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'price_typography',
				'selector' => '{{WRAPPER}} .course-card .course-price, {{WRAPPER}} .course-card .price',
			)
		);

		$this->add_responsive_control(
			'price_padding',
			array(
				'label'      => esc_html__( 'Padding', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .course-card .course-price, {{WRAPPER}} .course-card .price' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'price_margin',
			array(
				'label'      => esc_html__( 'Margin', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .course-card .course-price, {{WRAPPER}} .course-card .price' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'price_border',
				'selector' => '{{WRAPPER}} .course-card .course-price, {{WRAPPER}} .course-card .price',
			)
		);

		$this->add_responsive_control(
			'price_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .course-card .course-price, {{WRAPPER}} .course-card .price' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register button style controls
	 *
	 * @return void
	 */
	private function register_button_style_controls() {
		$this->start_controls_section(
			'button_section',
			array(
				'label' => esc_html__( 'Button Style', 'ohmylms' ),
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
				'selectors' => array(
					'{{WRAPPER}} .course-card .ohmylms-button, {{WRAPPER}} .course-card .enroll-button, {{WRAPPER}} .course-card .add_to_cart_button, {{WRAPPER}} .course-card .ohmylms-button-outline, {{WRAPPER}} .course-card .continue-course, {{WRAPPER}} .course-card a.ohmylms-button' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_control(
			'button_background',
			array(
				'label'     => esc_html__( 'Background Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .course-card .ohmylms-button, {{WRAPPER}} .course-card .enroll-button, {{WRAPPER}} .course-card .add_to_cart_button, {{WRAPPER}} .course-card .ohmylms-button-outline, {{WRAPPER}} .course-card .continue-course, {{WRAPPER}} .course-card a.ohmylms-button' => 'background: {{VALUE}} !important;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'button_typography',
				'selector' => '{{WRAPPER}} .course-card .ohmylms-button, {{WRAPPER}} .course-card .enroll-button, {{WRAPPER}} .course-card .add_to_cart_button, {{WRAPPER}} .course-card .ohmylms-button-outline, {{WRAPPER}} .course-card .continue-course, {{WRAPPER}} .course-card a.ohmylms-button',
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'button_border',
				'selector' => '{{WRAPPER}} .course-card .ohmylms-button, {{WRAPPER}} .course-card .enroll-button, {{WRAPPER}} .course-card .add_to_cart_button, {{WRAPPER}} .course-card .ohmylms-button-outline, {{WRAPPER}} .course-card .continue-course, {{WRAPPER}} .course-card a.ohmylms-button',
			)
		);

		$this->add_responsive_control(
			'button_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .course-card .ohmylms-button, {{WRAPPER}} .course-card .enroll-button, {{WRAPPER}} .course-card .add_to_cart_button, {{WRAPPER}} .course-card .ohmylms-button-outline, {{WRAPPER}} .course-card .continue-course, {{WRAPPER}} .course-card a.ohmylms-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'button_padding',
			array(
				'label'      => esc_html__( 'Padding', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .course-card .ohmylms-button, {{WRAPPER}} .course-card .enroll-button, {{WRAPPER}} .course-card .add_to_cart_button, {{WRAPPER}} .course-card .ohmylms-button-outline, {{WRAPPER}} .course-card .continue-course, {{WRAPPER}} .course-card a.ohmylms-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_responsive_control(
			'button_margin',
			array(
				'label'      => esc_html__( 'Margin', 'ohmylms' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .course-card .ohmylms-button, {{WRAPPER}} .course-card .enroll-button, {{WRAPPER}} .course-card .add_to_cart_button, {{WRAPPER}} .course-card .ohmylms-button-outline, {{WRAPPER}} .course-card .continue-course, {{WRAPPER}} .course-card a.ohmylms-button' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}} !important;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'button_box_shadow',
				'selector' => '{{WRAPPER}} .course-card .ohmylms-button, {{WRAPPER}} .course-card .enroll-button, {{WRAPPER}} .course-card .add_to_cart_button, {{WRAPPER}} .course-card .ohmylms-button-outline, {{WRAPPER}} .course-card .continue-course, {{WRAPPER}} .course-card a.ohmylms-button',
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
				'selectors' => array(
					'{{WRAPPER}} .course-card .ohmylms-button:hover, {{WRAPPER}} .course-card .enroll-button:hover, {{WRAPPER}} .course-card .add_to_cart_button:hover, {{WRAPPER}} .course-card .ohmylms-button-outline:hover, {{WRAPPER}} .course-card .continue-course:hover, {{WRAPPER}} .course-card a.ohmylms-button:hover' => 'color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_control(
			'button_hover_background',
			array(
				'label'     => esc_html__( 'Background Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .course-card .ohmylms-button:hover, {{WRAPPER}} .course-card .enroll-button:hover, {{WRAPPER}} .course-card .add_to_cart_button:hover, {{WRAPPER}} .course-card .ohmylms-button-outline:hover, {{WRAPPER}} .course-card .continue-course:hover, {{WRAPPER}} .course-card a.ohmylms-button:hover' => 'background: {{VALUE}} !important;',
				),
			)
		);

		$this->add_control(
			'button_hover_border_color',
			array(
				'label'     => esc_html__( 'Border Color', 'ohmylms' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .course-card .ohmylms-button:hover, {{WRAPPER}} .course-card .enroll-button:hover, {{WRAPPER}} .course-card .add_to_cart_button:hover, {{WRAPPER}} .course-card .ohmylms-button-outline:hover, {{WRAPPER}} .course-card .continue-course:hover, {{WRAPPER}} .course-card a.ohmylms-button:hover' => 'border-color: {{VALUE}} !important;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'button_hover_box_shadow',
				'selector' => '{{WRAPPER}} .course-card .ohmylms-button:hover, {{WRAPPER}} .course-card .enroll-button:hover, {{WRAPPER}} .course-card .add_to_cart_button:hover, {{WRAPPER}} .course-card .ohmylms-button-outline:hover, {{WRAPPER}} .course-card .continue-course:hover, {{WRAPPER}} .course-card a.ohmylms-button:hover',
			)
		);

		$this->end_controls_tab();
		$this->end_controls_tabs();

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

		// Use the same wrapper class as shortcode for consistency.
		echo '<div class="ohmylms ohmylms-page">';

		// Use the shortcode class to render the course list.
		ShortcodeCourseList::output( $shortcode_attrs );

		echo '</div>';
	}

	/**
	 * Convert Elementor settings to shortcode attributes
	 *
	 * @param array $settings Elementor settings.
	 * @return array Shortcode attributes
	 */
	private function convert_settings_to_shortcode_attrs( $settings ) {
		$attrs = array();

		// Content controls (these are the main attributes).
		if ( isset( $settings['posts_per_page'] ) && '' !== $settings['posts_per_page'] ) {
			$attrs['posts_per_page'] = $settings['posts_per_page'];
		}

		if ( isset( $settings['orderby'] ) && '' !== $settings['orderby'] ) {
			$attrs['orderby'] = $settings['orderby'];
		}

		if ( isset( $settings['order'] ) && '' !== $settings['order'] ) {
			$attrs['order'] = $settings['order'];
		}

		// Curriculum items and Course Bundles the list is limited to (comma-separated slugs).
		foreach ( array( 'curriculum', 'bundle' ) as $group ) {
			if ( ! empty( $settings[ $group ] ) ) {
				$attrs[ $group ] = implode( ',', array_map( 'sanitize_key', (array) $settings[ $group ] ) );
			}
		}

		if ( isset( $settings['layout'] ) && '' !== $settings['layout'] ) {
			$attrs['layout'] = $settings['layout'];
		}

		if ( isset( $settings['layout_style'] ) && '' !== $settings['layout_style'] ) {
			$attrs['layout_style'] = $settings['layout_style'];
		}

		if ( isset( $settings['columns'] ) && '' !== $settings['columns'] ) {
			$attrs['columns'] = $settings['columns'];
		}

		// Toggle controls - these return 'yes' when enabled, empty when disabled.
		if ( isset( $settings['show_filter'] ) && 'yes' === $settings['show_filter'] ) {
			$attrs['show_filter'] = 'yes';
		} else {
			$attrs['show_filter'] = 'no';
		}

		if ( isset( $settings['show_search'] ) && 'yes' === $settings['show_search'] ) {
			$attrs['show_search'] = 'yes';
		} else {
			$attrs['show_search'] = 'no';
		}

		if ( isset( $settings['show_sort'] ) && 'yes' === $settings['show_sort'] ) {
			$attrs['show_sort'] = 'yes';
		} else {
			$attrs['show_sort'] = 'no';
		}

		if ( isset( $settings['is_enable_category'] ) && 'yes' === $settings['is_enable_category'] ) {
			$attrs['is_enable_category'] = 'yes';
		} else {
			$attrs['is_enable_category'] = 'no';
		}

		// Handle course rows for grid-style3 and grid-style4.
		if ( isset( $settings['course_rows'] ) && ! empty( $settings['course_rows'] ) ) {
			$attrs['course_rows'] = $settings['course_rows'];
		}

		// Style controls are automatically handled by Elementor CSS selectors
		// No need to convert them to shortcode attributes since they directly target the DOM.

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
}
