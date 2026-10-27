<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * OhMyLMS Dashboard Widget for Elementor
 *
 * @package OhMyLMS\Elementor\Widgets
 * @since 1.0.0
 */

namespace OhMyLMS\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use OhMyLMS\Shortcodes\ShortCodeDashboard;

defined( 'ABSPATH' ) || exit;

/**
 * DashboardWidget class
 */
class DashboardWidget extends Widget_Base {

	/**
	 * Get widget name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'ohmylms-dashboard';
	}

	/**
	 * Get widget title
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'OhMyLMS Dashboard', 'ohmylms' );
	}

	/**
	 * Get widget icon
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-dashboard';
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
		return array( 'dashboard', 'student', 'creator', 'lms' );
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
			'my_profile_url',
			array(
				'label'   => __( 'My Profile URL', 'ohmylms' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
			)
		);

		$this->add_control(
			'my_courses_url',
			array(
				'label'   => __( 'My Courses URL', 'ohmylms' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
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
	 * Register dashboard style controls
	 *
	 * @return void
	 */
	private function register_dashboard_style_controls() {
		$this->start_controls_section(
			'dashboard_style_section',
			array(
				'label' => __( 'Dashboard Style', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'dashboard_bg_color',
			array(
				'label'   => __( 'Dashboard Background Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#F9FAFD',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register course button style controls
	 *
	 * @return void
	 */
	private function register_course_button_style_controls() {
		$this->start_controls_section(
			'course_button_style_section',
			array(
				'label' => __( 'Course Button Style', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'course_button_text_color',
			array(
				'label'   => __( 'Text Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#FFFFFF',
			)
		);

		$this->add_control(
			'course_button_bg_color',
			array(
				'label'   => __( 'Background Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#4361EE',
			)
		);

		$this->add_control(
			'course_button_font_size',
			array(
				'label'   => __( 'Font Size (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 15,
				'min'     => 1,
				'max'     => 200,
			)
		);

		$this->add_control(
			'course_button_font_weight',
			array(
				'label'   => __( 'Font Weight', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 500,
				'min'     => 100,
				'max'     => 900,
			)
		);

		$this->add_control(
			'course_button_border_radius',
			array(
				'label'   => __( 'Border Radius (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 10,
				'min'     => 0,
				'max'     => 100,
			)
		);

		$this->add_control(
			'course_button_hover_text_color',
			array(
				'label'   => __( 'Hover Text Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#4361EE',
			)
		);

		$this->add_control(
			'course_button_hover_bg_color',
			array(
				'label'   => __( 'Hover Background Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => 'transparent',
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
			'card_style_section',
			array(
				'label' => __( 'Card Style', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'card_bg_color',
			array(
				'label'   => __( 'Background Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#FFFFFF',
			)
		);

		$this->add_control(
			'card_text_color',
			array(
				'label'   => __( 'Text Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#52525B',
			)
		);

		$this->add_control(
			'card_text_font_size',
			array(
				'label'   => __( 'Text Font Size (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 14,
				'min'     => 1,
				'max'     => 200,
			)
		);

		$this->add_control(
			'card_text_font_weight',
			array(
				'label'   => __( 'Text Font Weight', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 400,
				'min'     => 100,
				'max'     => 900,
			)
		);

		$this->add_control(
			'card_number_color',
			array(
				'label'   => __( 'Number Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#1E1E1E',
			)
		);

		$this->add_control(
			'card_number_font_size',
			array(
				'label'   => __( 'Number Font Size (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 30,
				'min'     => 1,
				'max'     => 200,
			)
		);

		$this->add_control(
			'card_number_font_weight',
			array(
				'label'   => __( 'Number Font Weight', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 700,
				'min'     => 100,
				'max'     => 900,
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Register title typography controls
	 *
	 * @return void
	 */
	private function register_title_typography_controls() {
		$this->start_controls_section(
			'title_typography_section',
			array(
				'label' => __( 'Title Typography', 'ohmylms' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'title_color',
			array(
				'label'   => __( 'Color', 'ohmylms' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#1E1E1E',
			)
		);

		$this->add_control(
			'title_font_size',
			array(
				'label'   => __( 'Font Size (px)', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 22,
				'min'     => 1,
				'max'     => 200,
			)
		);

		$this->add_control(
			'title_font_weight',
			array(
				'label'   => __( 'Font Weight', 'ohmylms' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 700,
				'min'     => 100,
				'max'     => 900,
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
		$this->register_dashboard_style_controls();
		$this->register_course_button_style_controls();
		$this->register_card_style_controls();
		$this->register_title_typography_controls();
	}

	/**
	 * Convert Elementor settings array to shortcode attributes array
	 *
	 * Passes through all 29 shortcode attribute keys directly from settings,
	 * transforms the show_header switcher value, and excludes widget_width
	 * and widget_max_width (layout-only controls consumed by render()).
	 *
	 * @param array $settings Elementor settings array.
	 * @return array Shortcode attributes array with 30 keys.
	 */
	private function convert_settings_to_shortcode_attrs( array $settings ) {
		$passthrough_keys = array(
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
			'dashboard_bg_color',
			'course_button_text_color',
			'course_button_bg_color',
			'course_button_font_size',
			'course_button_font_weight',
			'course_button_border_radius',
			'course_button_hover_text_color',
			'course_button_hover_bg_color',
			'card_bg_color',
			'card_text_color',
			'card_text_font_size',
			'card_text_font_weight',
			'card_number_color',
			'card_number_font_size',
			'card_number_font_weight',
			'title_color',
			'title_font_size',
			'title_font_weight',
		);

		$attrs = array();

		foreach ( $passthrough_keys as $key ) {
			$attrs[ $key ] = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
		}

		// Transform show_header switcher: 'yes' stays 'yes', '' becomes 'no'.
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
				echo '<p>' . esc_html__( 'Dashboard widget: unable to load settings.', 'ohmylms' ) . '</p>';
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
			echo '<div class="ohmylms-dashboard-widget-wrap" style="' . esc_attr( $style ) . '">';
		} else {
			echo '<div class="ohmylms-dashboard-widget-wrap">';
		}

		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			// Edit-mode path: show preview notice and render with preview filter.
			echo '<div class="ohmylms-preview-notice"><p>' . esc_html__( 'Dashboard Preview', 'ohmylms' ) . '</p></div>';

			add_filter( 'ohmylms_gutenberg_preview_mode', '__return_true' );

			try {
				ob_start();
				ShortCodeDashboard::output( $shortcode_attrs );
				$output = ob_get_clean();

				if ( '' === $output ) {
					throw new \RuntimeException( 'Empty output from ShortCodeDashboard::output()' );
				}

				echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} catch ( \Throwable $e ) {
				ob_end_clean();
				echo '<div class="ohmylms-preview-error">';
				echo '<p>' . esc_html__( 'Dashboard preview is not available.', 'ohmylms' ) . '</p>';
				echo '</div>';
			} finally {
				remove_filter( 'ohmylms_gutenberg_preview_mode', '__return_true' );
			}
		} else {
			// Frontend path: render directly.
			ShortCodeDashboard::output( $shortcode_attrs );
		}

		echo '</div>';
	}
}
