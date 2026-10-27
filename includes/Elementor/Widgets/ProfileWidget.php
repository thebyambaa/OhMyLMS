<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * OhMyLMS Profile Widget for Elementor
 *
 * @package OhMyLMS\Elementor\Widgets
 * @since 1.0.0
 */

namespace OhMyLMS\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use OhMyLMS\Shortcodes\ShortCodeProfile;

defined( 'ABSPATH' ) || exit;

/**
 * ProfileWidget class
 */
class ProfileWidget extends Widget_Base {

	/**
	 * Get widget name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'ohmylms-profile';
	}

	/**
	 * Get widget title
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'OhMyLMS Profile', 'ohmylms' );
	}

	/**
	 * Get widget icon
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-user-circle-o';
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
		return array( 'profile', 'student', 'creator', 'lms' );
	}

	/**
	 * Get script dependencies
	 *
	 * @return array
	 */
	public function get_script_depends() {
		return array( 'ohmylms-frontend' );
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
	 * Register content section controls
	 *
	 * @return void
	 */
	private function register_content_controls() {
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Content Settings', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_header',
			array(
				'label'        => __( 'Show Header', 'ohmylms' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'widget_width',
			array(
				'label'   => __( 'Widget Width', 'ohmylms' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'full'  => __( 'Full Width', 'ohmylms' ),
					'fixed' => __( 'Fixed Width', 'ohmylms' ),
				),
				'default' => 'full',
			)
		);

		$this->add_control(
			'widget_max_width',
			array(
				'label'     => __( 'Max Width (px)', 'ohmylms' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 1200,
				'min'       => 200,
				'max'       => 3000,
				'condition' => array(
					'widget_width' => 'fixed',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register header style controls
	 *
	 * @return void
	 */
	private function register_header_style_controls() {
		$this->start_controls_section(
			'header_style_section',
			array(
				'label'     => __( 'Header Style', 'ohmylms' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'show_header' => 'yes',
				),
			)
		);

		$this->add_control(
			'header_bg_color',
			array(
				'label'   => __( 'Header Background Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#000D2C',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register user menu style controls
	 *
	 * @return void
	 */
	private function register_user_menu_style_controls() {
		$this->start_controls_section(
			'user_menu_style_section',
			array(
				'label' => __( 'User Menu Style', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'user_menu_color',
			array(
				'label'   => __( 'Text Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#000000',
			)
		);

		$this->add_control(
			'user_menu_bg_color',
			array(
				'label'   => __( 'Background Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#FFFFFF',
			)
		);

		$this->add_control(
			'user_menu_font_size',
			array(
				'label'   => __( 'Font Size (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 14,
				'min'     => 1,
				'max'     => 200,
			)
		);

		$this->add_control(
			'user_menu_font_weight',
			array(
				'label'   => __( 'Font Weight', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 400,
				'min'     => 100,
				'max'     => 900,
			)
		);

		$this->add_control(
			'user_menu_hover_color',
			array(
				'label'   => __( 'Hover Text Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#000000',
			)
		);

		$this->add_control(
			'user_menu_hover_bg_color',
			array(
				'label'   => __( 'Hover Background Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#F5F5F5',
			)
		);

		$this->add_control(
			'user_menu_icon_color',
			array(
				'label'   => __( 'Icon Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#000000',
			)
		);

		$this->add_control(
			'user_menu_icon_hover_color',
			array(
				'label'   => __( 'Icon Hover Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#4361EE',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register section style controls
	 *
	 * @return void
	 */
	private function register_section_style_controls() {
		$this->start_controls_section(
			'section_style_section',
			array(
				'label' => __( 'Section Style', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'section_bg_color',
			array(
				'label'   => __( 'Section Background Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#F9FAFD',
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
			'wrapper_style_section',
			array(
				'label' => __( 'Profile Wrapper Style', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'wrapper_border',
			array(
				'label'   => __( 'Border Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#EBECEF',
			)
		);

		$this->add_control(
			'wrapper_bg_color',
			array(
				'label'   => __( 'Background Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#F8F8F8',
			)
		);

		$this->add_control(
			'wrapper_shadow',
			array(
				'label'   => __( 'Box Shadow', 'ohmylms' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '0px 2px 8px 0px #ECECEC',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register sidebar items style controls
	 *
	 * @return void
	 */
	private function register_sidebar_items_style_controls() {
		$this->start_controls_section(
			'sidebar_items_style_section',
			array(
				'label' => __( 'Sidebar Items Style', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'sidebar_item_color',
			array(
				'label'   => __( 'Item Text Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#1E1E1E',
			)
		);

		$this->add_control(
			'sidebar_item_font_size',
			array(
				'label'   => __( 'Item Font Size (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 14,
				'min'     => 1,
				'max'     => 200,
			)
		);

		$this->add_control(
			'sidebar_item_font_weight',
			array(
				'label'   => __( 'Item Font Weight', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 400,
				'min'     => 100,
				'max'     => 900,
			)
		);

		$this->add_control(
			'sidebar_item_bg_color',
			array(
				'label'   => __( 'Item Background Color', 'ohmylms' ),
				'type'    => Controls_Manager::TEXT,
				'default' => 'transparent',
			)
		);

		$this->add_control(
			'sidebar_item_active_color',
			array(
				'label'   => __( 'Active Item Text Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#4361EE',
			)
		);

		$this->add_control(
			'sidebar_item_active_font_size',
			array(
				'label'   => __( 'Active Item Font Size (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 14,
				'min'     => 1,
				'max'     => 200,
			)
		);

		$this->add_control(
			'sidebar_item_active_font_weight',
			array(
				'label'   => __( 'Active Item Font Weight', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 400,
				'min'     => 100,
				'max'     => 900,
			)
		);

		$this->add_control(
			'sidebar_item_active_bg_color',
			array(
				'label'   => __( 'Active Item Background Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#FFFFFF',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register sidebar content style controls
	 *
	 * @return void
	 */
	private function register_sidebar_content_style_controls() {
		$this->start_controls_section(
			'sidebar_content_style_section',
			array(
				'label' => __( 'Sidebar Content Style', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'sidebar_content_bg_color',
			array(
				'label'   => __( 'Background Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#FFFFFF',
			)
		);

		$this->add_control(
			'sidebar_content_shadow',
			array(
				'label'   => __( 'Box Shadow', 'ohmylms' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '0px 1px 2px 0px #DBDDE1',
			)
		);

		$this->add_control(
			'sidebar_content_padding',
			array(
				'label'   => __( 'Padding (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 40,
				'min'     => 0,
				'max'     => 200,
			)
		);

		$this->add_control(
			'sidebar_content_title_color',
			array(
				'label'   => __( 'Title Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#1E1E1E',
			)
		);

		$this->add_control(
			'sidebar_content_title_font_size',
			array(
				'label'   => __( 'Title Font Size (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 24,
				'min'     => 1,
				'max'     => 200,
			)
		);

		$this->add_control(
			'sidebar_content_title_font_weight',
			array(
				'label'   => __( 'Title Font Weight', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 600,
				'min'     => 100,
				'max'     => 900,
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register profile info style controls
	 *
	 * @return void
	 */
	private function register_profile_info_style_controls() {
		$this->start_controls_section(
			'profile_info_style_section',
			array(
				'label' => __( 'Profile Info Style', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'profile_name_color',
			array(
				'label'   => __( 'Name Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#1E1E1E',
			)
		);

		$this->add_control(
			'profile_name_font_size',
			array(
				'label'   => __( 'Name Font Size (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 20,
				'min'     => 1,
				'max'     => 200,
			)
		);

		$this->add_control(
			'profile_name_font_weight',
			array(
				'label'   => __( 'Name Font Weight', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 700,
				'min'     => 100,
				'max'     => 900,
			)
		);

		$this->add_control(
			'profile_bio_color',
			array(
				'label'   => __( 'Bio Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#52525B',
			)
		);

		$this->add_control(
			'profile_bio_font_size',
			array(
				'label'   => __( 'Bio Font Size (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 15,
				'min'     => 1,
				'max'     => 200,
			)
		);

		$this->add_control(
			'profile_bio_font_weight',
			array(
				'label'   => __( 'Bio Font Weight', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 400,
				'min'     => 100,
				'max'     => 900,
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register profile edit button style controls
	 *
	 * @return void
	 */
	private function register_profile_edit_button_style_controls() {
		$this->start_controls_section(
			'profile_edit_button_style_section',
			array(
				'label' => __( 'Profile Edit Button Style', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'profile_edit_color',
			array(
				'label'   => __( 'Text Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#1E1E1E',
			)
		);

		$this->add_control(
			'profile_edit_bg_color',
			array(
				'label'   => __( 'Background Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#FFFFFF',
			)
		);

		$this->add_control(
			'profile_edit_border',
			array(
				'label'   => __( 'Border Color', 'ohmylms' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '#EBEBEF',
			)
		);

		$this->add_control(
			'profile_edit_font_size',
			array(
				'label'   => __( 'Font Size (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 14,
				'min'     => 1,
				'max'     => 200,
			)
		);

		$this->add_control(
			'profile_edit_font_weight',
			array(
				'label'   => __( 'Font Weight', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 500,
				'min'     => 100,
				'max'     => 900,
			)
		);

		$this->add_control(
			'profile_edit_hover_color',
			array(
				'label'   => __( 'Hover Text Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#1E1E1E',
			)
		);

		$this->add_control(
			'profile_edit_hover_bg_color',
			array(
				'label'   => __( 'Hover Background Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#f6f6f6',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register basic info style controls
	 *
	 * @return void
	 */
	private function register_basic_info_style_controls() {
		$this->start_controls_section(
			'basic_info_style_section',
			array(
				'label' => __( 'Basic Info Style', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'basic_info_bg_color',
			array(
				'label'   => __( 'Background Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#FFFFFF',
			)
		);

		$this->add_control(
			'basic_info_shadow',
			array(
				'label'   => __( 'Box Shadow', 'ohmylms' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '0px 1px 4px 0px #D3D6DD',
			)
		);

		$this->add_control(
			'basic_info_padding',
			array(
				'label'   => __( 'Padding (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 21,
				'min'     => 0,
				'max'     => 200,
			)
		);

		$this->add_control(
			'basic_info_title_color',
			array(
				'label'   => __( 'Title Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#1E1E1E',
			)
		);

		$this->add_control(
			'basic_info_title_font_size',
			array(
				'label'   => __( 'Title Font Size (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 18,
				'min'     => 1,
				'max'     => 200,
			)
		);

		$this->add_control(
			'basic_info_title_font_weight',
			array(
				'label'   => __( 'Title Font Weight', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 600,
				'min'     => 100,
				'max'     => 900,
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register input fields style controls
	 *
	 * @return void
	 */
	private function register_input_fields_style_controls() {
		$this->start_controls_section(
			'input_fields_style_section',
			array(
				'label' => __( 'Input Fields Style', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'input_bg_color',
			array(
				'label'   => __( 'Background Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#FFFFFF',
			)
		);

		$this->add_control(
			'input_border',
			array(
				'label'   => __( 'Border Color', 'ohmylms' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '#c8d2e980',
			)
		);

		$this->add_control(
			'input_text_color',
			array(
				'label'   => __( 'Text Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#52525B',
			)
		);

		$this->add_control(
			'input_placeholder_color',
			array(
				'label'   => __( 'Placeholder Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#7A8B9A',
			)
		);

		$this->add_control(
			'input_font_size',
			array(
				'label'   => __( 'Font Size (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 14,
				'min'     => 1,
				'max'     => 200,
			)
		);

		$this->add_control(
			'input_padding',
			array(
				'label'   => __( 'Padding (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 13,
				'min'     => 0,
				'max'     => 100,
			)
		);

		$this->add_control(
			'input_border_radius',
			array(
				'label'   => __( 'Border Radius (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 8,
				'min'     => 0,
				'max'     => 100,
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register labels style controls
	 *
	 * @return void
	 */
	private function register_labels_style_controls() {
		$this->start_controls_section(
			'labels_style_section',
			array(
				'label' => __( 'Labels Style', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'label_color',
			array(
				'label'   => __( 'Label Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#1E1E1E',
			)
		);

		$this->add_control(
			'label_font_size',
			array(
				'label'   => __( 'Font Size (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 14,
				'min'     => 1,
				'max'     => 200,
			)
		);

		$this->add_control(
			'label_font_weight',
			array(
				'label'   => __( 'Font Weight', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 500,
				'min'     => 100,
				'max'     => 900,
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register save button style controls
	 *
	 * @return void
	 */
	private function register_save_button_style_controls() {
		$this->start_controls_section(
			'save_button_style_section',
			array(
				'label' => __( 'Save Button Style', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'button_bg_color',
			array(
				'label'   => __( 'Background Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#4361EE',
			)
		);

		$this->add_control(
			'button_text_color',
			array(
				'label'   => __( 'Text Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#FFFFFF',
			)
		);

		$this->add_control(
			'button_font_size',
			array(
				'label'   => __( 'Font Size (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 15,
				'min'     => 1,
				'max'     => 200,
			)
		);

		$this->add_control(
			'button_font_weight',
			array(
				'label'   => __( 'Font Weight', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 500,
				'min'     => 100,
				'max'     => 900,
			)
		);

		$this->add_control(
			'button_border_radius',
			array(
				'label'   => __( 'Border Radius (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 8,
				'min'     => 0,
				'max'     => 100,
			)
		);

		$this->add_control(
			'button_hover_bg_color',
			array(
				'label'   => __( 'Hover Background Color', 'ohmylms' ),
				'type'    => Controls_Manager::TEXT,
				'default' => 'transparent',
			)
		);

		$this->add_control(
			'button_hover_text_color',
			array(
				'label'   => __( 'Hover Text Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#4361EE',
			)
		);

		$this->add_control(
			'button_hover_border',
			array(
				'label'   => __( 'Hover Border Color', 'ohmylms' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '#4361EE',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register widget controls
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->register_content_controls();
		$this->register_header_style_controls();
		$this->register_user_menu_style_controls();
		$this->register_section_style_controls();
		$this->register_wrapper_style_controls();
		$this->register_sidebar_items_style_controls();
		$this->register_sidebar_content_style_controls();
		$this->register_profile_info_style_controls();
		$this->register_profile_edit_button_style_controls();
		$this->register_basic_info_style_controls();
		$this->register_input_fields_style_controls();
		$this->register_labels_style_controls();
		$this->register_save_button_style_controls();
	}

	/**
	 * Convert Elementor settings array to shortcode attributes array
	 *
	 * Passes through all 64 shortcode attribute keys directly from settings,
	 * transforms the show_header switcher value, and excludes widget_width
	 * and widget_max_width (layout-only controls consumed by render()).
	 *
	 * @param array $settings Elementor settings array.
	 * @return array Shortcode attributes array with 65 keys.
	 */
	private function convert_settings_to_shortcode_attrs( array $settings ) {
		$passthrough_keys = array(
			// Header styling.
			'header_bg_color',
			// User menu styling.
			'user_menu_color',
			'user_menu_bg_color',
			'user_menu_font_size',
			'user_menu_font_weight',
			'user_menu_hover_color',
			'user_menu_hover_bg_color',
			'user_menu_icon_color',
			'user_menu_icon_hover_color',
			// Section background.
			'section_bg_color',
			// Profile wrapper styling.
			'wrapper_border',
			'wrapper_bg_color',
			'wrapper_shadow',
			// Sidebar items styling.
			'sidebar_item_color',
			'sidebar_item_font_size',
			'sidebar_item_font_weight',
			'sidebar_item_bg_color',
			'sidebar_item_active_color',
			'sidebar_item_active_font_size',
			'sidebar_item_active_font_weight',
			'sidebar_item_active_bg_color',
			// Sidebar content styling.
			'sidebar_content_bg_color',
			'sidebar_content_shadow',
			'sidebar_content_padding',
			'sidebar_content_title_color',
			'sidebar_content_title_font_size',
			'sidebar_content_title_font_weight',
			// Profile info styling.
			'profile_name_color',
			'profile_name_font_size',
			'profile_name_font_weight',
			'profile_bio_color',
			'profile_bio_font_size',
			'profile_bio_font_weight',
			// Profile edit button styling.
			'profile_edit_color',
			'profile_edit_bg_color',
			'profile_edit_border',
			'profile_edit_font_size',
			'profile_edit_font_weight',
			'profile_edit_hover_color',
			'profile_edit_hover_bg_color',
			// Basic info section styling.
			'basic_info_bg_color',
			'basic_info_shadow',
			'basic_info_padding',
			'basic_info_title_color',
			'basic_info_title_font_size',
			'basic_info_title_font_weight',
			// Input styling.
			'input_bg_color',
			'input_border',
			'input_text_color',
			'input_placeholder_color',
			'input_font_size',
			'input_padding',
			'input_border_radius',
			// Label styling.
			'label_color',
			'label_font_size',
			'label_font_weight',
			// Button styling.
			'button_bg_color',
			'button_text_color',
			'button_font_size',
			'button_font_weight',
			'button_border_radius',
			'button_hover_bg_color',
			'button_hover_text_color',
			'button_hover_border',
		);

		$attrs = array();

		foreach ( $passthrough_keys as $key ) {
			$attrs[ $key ] = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
		}

		// Transform show_header switcher: 'yes' stays 'yes', anything else becomes 'no'.
		$attrs['show_header'] = ( isset( $settings['show_header'] ) && 'yes' === $settings['show_header'] ) ? 'yes' : 'no';

		return $attrs;
	}

	/**
	 * Render widget output on the frontend
	 *
	 * @return void
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	protected function render() {
		// Enqueue frontend assets.
		wp_enqueue_style( 'ohmylms-frontend' );
		wp_enqueue_script( 'ohmylms-frontend' );

		// Retrieve settings; bail with admin notice if invalid.
		$settings = $this->get_settings_for_display();

		if ( ! is_array( $settings ) ) {
			if ( current_user_can( 'edit_posts' ) ) {
				echo '<div class="ohmylms-widget-error">';
				echo '<p>' . esc_html__( 'Profile widget: unable to load settings.', 'ohmylms' ) . '</p>';
				echo '</div>';
			}
			return;
		}

		// Build shortcode attributes (excludes widget_width and widget_max_width).
		$shortcode_attrs = $this->convert_settings_to_shortcode_attrs( $settings );

		// Build optional fixed-width inline style.
		if ( isset( $settings['widget_width'] ) && 'fixed' === $settings['widget_width'] ) {
			$style = 'max-width:' . intval( $settings['widget_max_width'] ) . 'px;';
		} else {
			$style = '';
		}

		// Output outer wrapper with optional inline style.
		if ( '' !== $style ) {
			echo '<div class="ohmylms-profile-widget-wrap" style="' . esc_attr( $style ) . '">';
		} else {
			echo '<div class="ohmylms-profile-widget-wrap">';
		}

		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			// Edit-mode path: show preview notice and render with preview filter.
			echo '<div class="ohmylms-preview-notice"><p>' . esc_html__( 'Profile Preview', 'ohmylms' ) . '</p></div>';

			add_filter( 'ohmylms_gutenberg_preview_mode', '__return_true' );

			try {
				ob_start();
				ShortCodeProfile::output( $shortcode_attrs );
				$output = ob_get_clean();

				if ( '' === $output ) {
					throw new \RuntimeException( 'Empty output from ShortCodeProfile::output()' );
				}

				echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} catch ( \Throwable $e ) {
				ob_end_clean();
				echo '<div class="ohmylms-preview-error">';
				echo '<p>' . esc_html__( 'Profile preview is not available.', 'ohmylms' ) . '</p>';
				echo '</div>';
			} finally {
				remove_filter( 'ohmylms_gutenberg_preview_mode', '__return_true' );
			}
		} else {
			// Frontend path: render directly.
			ShortCodeProfile::output( $shortcode_attrs );
		}

		echo '</div>';
	}
}
