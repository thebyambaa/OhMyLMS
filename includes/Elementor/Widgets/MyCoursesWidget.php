<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * OhMyLMS My Courses Widget for Elementor
 *
 * @package OhMyLMS\Elementor\Widgets
 * @since 1.0.0
 */

namespace OhMyLMS\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use OhMyLMS\Shortcodes\ShortCodeMyCourses;

defined( 'ABSPATH' ) || exit;

/**
 * MyCoursesWidget class
 */
class MyCoursesWidget extends Widget_Base {

	/**
	 * Get widget name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'ohmylms-my-courses';
	}

	/**
	 * Get widget title
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'OhMyLMS My Courses', 'ohmylms' );
	}

	/**
	 * Get widget icon
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-my-account';
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
		return array( 'my-courses', 'student', 'dashboard', 'creator', 'lms' );
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
	 * Register widget controls
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->register_content_controls();
		$this->register_header_style_controls();
		$this->register_user_menu_style_controls();
		$this->register_layout_style_controls();
		$this->register_title_typography_controls();
		$this->register_text_typography_controls();
		$this->register_button_style_controls();
		$this->register_card_style_controls();
		$this->register_progress_bar_controls();
		$this->register_tab_style_controls();
		$this->register_no_courses_card_controls();
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
				'label' => esc_html__( 'Content Settings', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_header',
			array(
				'label'        => esc_html__( 'Show Header', 'ohmylms' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Show', 'ohmylms' ),
				'label_off'    => esc_html__( 'Hide', 'ohmylms' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'my_profile_url',
			array(
				'label'   => esc_html__( 'My Profile URL', 'ohmylms' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
			)
		);

		$this->add_control(
			'my_courses_url',
			array(
				'label'   => esc_html__( 'My Courses URL', 'ohmylms' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register header style section controls
	 *
	 * @return void
	 */
	private function register_header_style_controls() {
		$this->start_controls_section(
			'header_style_section',
			array(
				'label'     => esc_html__( 'Header Style', 'ohmylms' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'show_header' => 'yes',
				),
			)
		);

		$this->add_control(
			'header_bg_color',
			array(
				'label'   => esc_html__( 'Header Background Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#000D2C',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register user menu style section controls
	 *
	 * @return void
	 */
	private function register_user_menu_style_controls() {
		$this->start_controls_section(
			'user_menu_style_section',
			array(
				'label' => esc_html__( 'User Menu Style', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'user_menu_color',
			array(
				'label'   => esc_html__( 'Text Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#000000',
			)
		);

		$this->add_control(
			'user_menu_bg_color',
			array(
				'label'   => esc_html__( 'Background Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#FFFFFF',
			)
		);

		$this->add_control(
			'user_menu_font_size',
			array(
				'label'   => esc_html__( 'Font Size (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 14,
				'min'     => 1,
				'max'     => 200,
			)
		);

		$this->add_control(
			'user_menu_font_weight',
			array(
				'label'   => esc_html__( 'Font Weight', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 400,
				'min'     => 100,
				'max'     => 900,
			)
		);

		$this->add_control(
			'user_menu_hover_color',
			array(
				'label'   => esc_html__( 'Hover Text Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#000000',
			)
		);

		$this->add_control(
			'user_menu_hover_bg_color',
			array(
				'label'   => esc_html__( 'Hover Background Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#F5F5F5',
			)
		);

		$this->add_control(
			'user_menu_icon_color',
			array(
				'label'   => esc_html__( 'Icon Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#000000',
			)
		);

		$this->add_control(
			'user_menu_icon_hover_color',
			array(
				'label'   => esc_html__( 'Icon Hover Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#4361EE',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register layout style section controls
	 *
	 * @return void
	 */
	private function register_layout_style_controls() {
		$this->start_controls_section(
			'layout_style_section',
			array(
				'label' => esc_html__( 'Layout Style', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'section_bg_color',
			array(
				'label'   => esc_html__( 'Section Background Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#F9FAFD',
			)
		);

		$this->add_control(
			'wrapper_bg_color',
			array(
				'label'   => esc_html__( 'Wrapper Background Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#FFFFFF',
			)
		);

		$this->add_control(
			'wrapper_padding',
			array(
				'label'   => esc_html__( 'Wrapper Padding (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 30,
				'min'     => 0,
				'max'     => 200,
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register title typography section controls
	 *
	 * @return void
	 */
	private function register_title_typography_controls() {
		$this->start_controls_section(
			'title_typography_section',
			array(
				'label' => esc_html__( 'Title Typography', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'title_color',
			array(
				'label'   => esc_html__( 'Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#1E1E1E',
			)
		);

		$this->add_control(
			'title_font_size',
			array(
				'label'   => esc_html__( 'Font Size (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 24,
				'min'     => 1,
				'max'     => 200,
			)
		);

		$this->add_control(
			'title_font_weight',
			array(
				'label'   => esc_html__( 'Font Weight', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 600,
				'min'     => 100,
				'max'     => 900,
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register text typography section controls
	 *
	 * @return void
	 */
	private function register_text_typography_controls() {
		$this->start_controls_section(
			'text_typography_section',
			array(
				'label' => esc_html__( 'Text Typography', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'text_color',
			array(
				'label'   => esc_html__( 'Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#52525B',
			)
		);

		$this->add_control(
			'text_font_size',
			array(
				'label'   => esc_html__( 'Font Size (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 14,
				'min'     => 1,
				'max'     => 200,
			)
		);

		$this->add_control(
			'text_font_weight',
			array(
				'label'   => esc_html__( 'Font Weight', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 400,
				'min'     => 100,
				'max'     => 900,
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register button style section controls
	 *
	 * @return void
	 */
	private function register_button_style_controls() {
		$this->start_controls_section(
			'button_style_section',
			array(
				'label' => esc_html__( 'Button Style', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'button_text_color',
			array(
				'label'   => esc_html__( 'Text Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#FFFFFF',
			)
		);

		$this->add_control(
			'button_bg_color',
			array(
				'label'   => esc_html__( 'Background Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#6E42D3',
			)
		);

		$this->add_control(
			'button_font_size',
			array(
				'label'   => esc_html__( 'Font Size (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 14,
				'min'     => 1,
				'max'     => 200,
			)
		);

		$this->add_control(
			'button_font_weight',
			array(
				'label'   => esc_html__( 'Font Weight', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 600,
				'min'     => 100,
				'max'     => 900,
			)
		);

		$this->add_control(
			'button_border_width',
			array(
				'label'   => esc_html__( 'Border Width (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 0,
				'min'     => 0,
				'max'     => 50,
			)
		);

		$this->add_control(
			'button_border_color',
			array(
				'label'   => esc_html__( 'Border Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#6E42D3',
			)
		);

		$this->add_control(
			'button_border_radius',
			array(
				'label'   => esc_html__( 'Border Radius (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 6,
				'min'     => 0,
				'max'     => 100,
			)
		);

		$this->add_control(
			'button_padding',
			array(
				'label'   => esc_html__( 'Padding (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 12,
				'min'     => 0,
				'max'     => 100,
			)
		);

		$this->add_control(
			'button_hover_bg_color',
			array(
				'label'   => esc_html__( 'Hover Background Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#5a32c2',
			)
		);

		$this->add_control(
			'button_hover_text_color',
			array(
				'label'   => esc_html__( 'Hover Text Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#FFFFFF',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register course card style section controls
	 *
	 * @return void
	 */
	private function register_card_style_controls() {
		$this->start_controls_section(
			'card_style_section',
			array(
				'label' => esc_html__( 'Course Card Style', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'card_bg_color',
			array(
				'label'   => esc_html__( 'Background Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#FFFFFF',
			)
		);

		$this->add_control(
			'card_padding',
			array(
				'label'   => esc_html__( 'Padding (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 20,
				'min'     => 0,
				'max'     => 100,
			)
		);

		$this->add_control(
			'card_border_radius',
			array(
				'label'   => esc_html__( 'Border Radius (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 8,
				'min'     => 0,
				'max'     => 100,
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register progress bar style section controls
	 *
	 * @return void
	 */
	private function register_progress_bar_controls() {
		$this->start_controls_section(
			'progress_bar_section',
			array(
				'label' => esc_html__( 'Progress Bar Style', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'progress_bar_bg_color',
			array(
				'label'   => esc_html__( 'Track Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#E5E7EB',
			)
		);

		$this->add_control(
			'progress_bar_fill_color',
			array(
				'label'   => esc_html__( 'Fill Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#6E42D3',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register tab style section controls
	 *
	 * @return void
	 */
	private function register_tab_style_controls() {
		$this->start_controls_section(
			'tab_style_section',
			array(
				'label' => esc_html__( 'Tab Style', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'tab_normal_color',
			array(
				'label'   => esc_html__( 'Normal Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#666666',
			)
		);

		$this->add_control(
			'tab_active_color',
			array(
				'label'   => esc_html__( 'Active Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#6E42D3',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register no courses card style section controls
	 *
	 * @return void
	 */
	private function register_no_courses_card_controls() {
		$this->start_controls_section(
			'no_courses_card_section',
			array(
				'label' => esc_html__( 'No Courses Card Style', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'no_course_card_bg_color',
			array(
				'label'   => esc_html__( 'Background Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#F9FAFB',
			)
		);

		$this->add_control(
			'no_course_card_padding',
			array(
				'label'   => esc_html__( 'Padding (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 40,
				'min'     => 0,
				'max'     => 300,
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Convert Elementor settings array to shortcode attribute array
	 *
	 * Performs a direct pass-through for all control keys except `show_header`,
	 * which is transformed from the switcher value ('yes'/'') to 'yes'/'no'.
	 *
	 * @param array $settings Elementor settings from get_settings_for_display().
	 * @return array Shortcode attributes array.
	 */
	private function convert_settings_to_shortcode_attrs( array $settings ): array {
		$attrs = array();
		$keys  = array(
			'my_profile_url',
			'my_courses_url',
			'header_bg_color',
			'user_menu_color',
			'user_menu_bg_color',
			'user_menu_font_size',
			'user_menu_font_weight',
			'user_menu_hover_color',
			'user_menu_hover_bg_color',
			'user_menu_icon_color',
			'user_menu_icon_hover_color',
			'section_bg_color',
			'wrapper_bg_color',
			'wrapper_padding',
			'title_color',
			'title_font_size',
			'title_font_weight',
			'text_color',
			'text_font_size',
			'text_font_weight',
			'button_text_color',
			'button_bg_color',
			'button_font_size',
			'button_font_weight',
			'button_border_width',
			'button_border_color',
			'button_border_radius',
			'button_padding',
			'button_hover_bg_color',
			'button_hover_text_color',
			'card_bg_color',
			'card_padding',
			'card_border_radius',
			'progress_bar_bg_color',
			'progress_bar_fill_color',
			'tab_normal_color',
			'tab_active_color',
			'no_course_card_bg_color',
			'no_course_card_padding',
		);

		foreach ( $keys as $key ) {
			if ( isset( $settings[ $key ] ) ) {
				$attrs[ $key ] = $settings[ $key ];
			}
		}

		// Transform show_header switcher: 'yes' stays 'yes'; '' (off) becomes 'no'.
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
		// Enqueue assets directly here — the most reliable approach for Elementor
		// widgets. FrontendAssets only enqueues ohmylms-frontend when page-detection
		// helpers return true (shortcode/block in post content), which is never the
		// case on an Elementor page. Calling wp_enqueue_* from render() works on
		// both the published frontend and the editor preview iframe.
		wp_enqueue_style( 'ohmylms-frontend' );
		wp_enqueue_script( 'ohmylms-frontend' );

		$settings = $this->get_settings_for_display();

		if ( ! is_array( $settings ) ) {
			if ( current_user_can( 'edit_posts' ) ) {
				echo '<div class="ohmylms-widget-error">';
				echo '<p>' . esc_html__( 'MyCoursesWidget: could not retrieve widget settings.', 'ohmylms' ) . '</p>';
				echo '</div>';
			}
			return;
		}

		$shortcode_attrs = $this->convert_settings_to_shortcode_attrs( $settings );

		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			echo '<div class="ohmylms-preview-notice">';
			echo '<p>' . esc_html__( 'My Courses Preview', 'ohmylms' ) . '</p>';
			echo '</div>';

			add_filter( 'ohmylms_gutenberg_preview_mode', '__return_true' );

			try {
				ob_start();
				ShortCodeMyCourses::output( $shortcode_attrs );
				$output = ob_get_clean();
				if ( '' === $output ) {
					throw new \RuntimeException( 'Empty output from ShortCodeMyCourses::output()' );
				}
				echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} catch ( \Throwable $e ) {
				ob_end_clean();
				echo '<div class="ohmylms-preview-error">';
				echo '<p>' . esc_html__( 'My Courses preview could not be rendered.', 'ohmylms' ) . '</p>';
				echo '</div>';
			} finally {
				remove_filter( 'ohmylms_gutenberg_preview_mode', '__return_true' );
			}
		} else {
			ShortCodeMyCourses::output( $shortcode_attrs );
		}
	}
}
