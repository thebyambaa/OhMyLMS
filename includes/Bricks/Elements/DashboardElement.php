<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * OhMyLMS Dashboard Element for Bricks Builder
 *
 * Styling is handled entirely by ShortCodeDashboard output styles,
 * generated via shortcode attrs passed from Bricks controls.
 *
 * @package OhMyLMS\Bricks\Elements
 * @since 1.0.0
 */

namespace OhMyLMS\Bricks\Elements;

use OhMyLMS\Shortcodes\ShortCodeDashboard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DashboardElement class
 */
class DashboardElement extends \Bricks\Element {

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
	public $name = 'ohmylms-dashboard';
	/**
	 * Icon.
	 *
	 * @var mixed
	 */
	public $icon = 'ti-dashboard';
	/**
	 * Keywords.
	 *
	 * @var mixed
	 */
	public $keywords = array( 'dashboard', 'student', 'creator', 'lms' );
	/**
	 * Scripts.
	 *
	 * @var mixed
	 */
	public $scripts = array( 'ohmylms-frontend' );
	/**
	 * Styles.
	 *
	 * @var mixed
	 */
	public $styles = array( 'ohmylms-frontend' );

	/**
	 * Get label for Dashboard element.
	 */
	public function get_label() {
		return esc_html__( 'OhMyLMS Dashboard', 'ohmylms' );
	}

	// -------------------------------------------------------------------------
	// Controls
	// -------------------------------------------------------------------------

	/**
	 * Set controls for Dashboard element.
	 */
	public function set_controls() {
		$this->controls       = array();
		$this->control_groups = array();
		$this->set_content_controls();
		$this->set_style_controls();
	}

	/**
	 * Set content controls for Dashboard element.
	 */
	private function set_content_controls() {
		$this->controls['show_header'] = array(
			'tab'     => 'content',
			'label'   => esc_html__( 'Show Header', 'ohmylms' ),
			'type'    => 'checkbox',
			'default' => true,
		);

		$this->controls['my_profile_url'] = array(
			'tab'     => 'content',
			'label'   => esc_html__( 'My Profile URL', 'ohmylms' ),
			'type'    => 'text',
			'default' => '',
		);

		$this->controls['my_courses_url'] = array(
			'tab'     => 'content',
			'label'   => esc_html__( 'My Courses URL', 'ohmylms' ),
			'type'    => 'text',
			'default' => '',
		);
	}

	/**
	 * Set style controls for Dashboard element.
	 */
	private function set_style_controls() {

		$this->control_groups['header_style']        = array(
			'title'    => esc_html__( 'Header Style', 'ohmylms' ),
			'tab'      => 'style',
			'required' => array( array( 'show_header', '=', true ) ),
		);
		$this->control_groups['user_menu_style']     = array(
			'title' => esc_html__( 'User Menu Style', 'ohmylms' ),
			'tab'   => 'style',
		);
		$this->control_groups['dashboard_style']     = array(
			'title' => esc_html__( 'Dashboard Style', 'ohmylms' ),
			'tab'   => 'style',
		);
		$this->control_groups['course_button_style'] = array(
			'title' => esc_html__( 'Course Button Style', 'ohmylms' ),
			'tab'   => 'style',
		);
		$this->control_groups['card_style']          = array(
			'title' => esc_html__( 'Card Style', 'ohmylms' ),
			'tab'   => 'style',
		);
		$this->control_groups['title_typography']    = array(
			'title' => esc_html__( 'Title Typography', 'ohmylms' ),
			'tab'   => 'style',
		);

		$this->controls['header_bg_color'] = array(
			'tab'     => 'style',
			'group'   => 'header_style',
			'label'   => esc_html__( 'Header Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#000D2C' ),
		);

		$this->controls['user_menu_color']            = array(
			'tab'     => 'style',
			'group'   => 'user_menu_style',
			'label'   => esc_html__( 'Text Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#000000' ),
		);
		$this->controls['user_menu_bg_color']         = array(
			'tab'     => 'style',
			'group'   => 'user_menu_style',
			'label'   => esc_html__( 'Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#FFFFFF' ),
		);
		$this->controls['user_menu_font_size']        = array(
			'tab'     => 'style',
			'group'   => 'user_menu_style',
			'label'   => esc_html__( 'Font Size (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 14,
			'min'     => 1,
			'max'     => 200,
		);
		$this->controls['user_menu_font_weight']      = array(
			'tab'     => 'style',
			'group'   => 'user_menu_style',
			'label'   => esc_html__( 'Font Weight', 'ohmylms' ),
			'type'    => 'number',
			'default' => 400,
			'min'     => 100,
			'max'     => 900,
		);
		$this->controls['user_menu_hover_color']      = array(
			'tab'     => 'style',
			'group'   => 'user_menu_style',
			'label'   => esc_html__( 'Hover Text Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#000000' ),
		);
		$this->controls['user_menu_hover_bg_color']   = array(
			'tab'     => 'style',
			'group'   => 'user_menu_style',
			'label'   => esc_html__( 'Hover Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#F5F5F5' ),
		);
		$this->controls['user_menu_icon_color']       = array(
			'tab'     => 'style',
			'group'   => 'user_menu_style',
			'label'   => esc_html__( 'Icon Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#000000' ),
		);
		$this->controls['user_menu_icon_hover_color'] = array(
			'tab'     => 'style',
			'group'   => 'user_menu_style',
			'label'   => esc_html__( 'Icon Hover Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#4361EE' ),
		);

		$this->controls['dashboard_bg_color'] = array(
			'tab'     => 'style',
			'group'   => 'dashboard_style',
			'label'   => esc_html__( 'Dashboard Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#F9FAFD' ),
		);

		$this->controls['course_button_text_color']       = array(
			'tab'     => 'style',
			'group'   => 'course_button_style',
			'label'   => esc_html__( 'Text Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#FFFFFF' ),
		);
		$this->controls['course_button_bg_color']         = array(
			'tab'     => 'style',
			'group'   => 'course_button_style',
			'label'   => esc_html__( 'Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#4361EE' ),
		);
		$this->controls['course_button_font_size']        = array(
			'tab'     => 'style',
			'group'   => 'course_button_style',
			'label'   => esc_html__( 'Font Size (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 15,
			'min'     => 1,
			'max'     => 200,
		);
		$this->controls['course_button_font_weight']      = array(
			'tab'     => 'style',
			'group'   => 'course_button_style',
			'label'   => esc_html__( 'Font Weight', 'ohmylms' ),
			'type'    => 'number',
			'default' => 500,
			'min'     => 100,
			'max'     => 900,
		);
		$this->controls['course_button_border_radius']    = array(
			'tab'     => 'style',
			'group'   => 'course_button_style',
			'label'   => esc_html__( 'Border Radius (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 10,
			'min'     => 0,
			'max'     => 100,
		);
		$this->controls['course_button_hover_text_color'] = array(
			'tab'     => 'style',
			'group'   => 'course_button_style',
			'label'   => esc_html__( 'Hover Text Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#4361EE' ),
		);
		$this->controls['course_button_hover_bg_color']   = array(
			'tab'     => 'style',
			'group'   => 'course_button_style',
			'label'   => esc_html__( 'Hover Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => 'transparent' ),
		);

		$this->controls['card_bg_color']           = array(
			'tab'     => 'style',
			'group'   => 'card_style',
			'label'   => esc_html__( 'Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#FFFFFF' ),
		);
		$this->controls['card_text_color']         = array(
			'tab'     => 'style',
			'group'   => 'card_style',
			'label'   => esc_html__( 'Text Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#52525B' ),
		);
		$this->controls['card_text_font_size']     = array(
			'tab'     => 'style',
			'group'   => 'card_style',
			'label'   => esc_html__( 'Text Font Size (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 14,
			'min'     => 1,
			'max'     => 200,
		);
		$this->controls['card_text_font_weight']   = array(
			'tab'     => 'style',
			'group'   => 'card_style',
			'label'   => esc_html__( 'Text Font Weight', 'ohmylms' ),
			'type'    => 'number',
			'default' => 400,
			'min'     => 100,
			'max'     => 900,
		);
		$this->controls['card_number_color']       = array(
			'tab'     => 'style',
			'group'   => 'card_style',
			'label'   => esc_html__( 'Number Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#1E1E1E' ),
		);
		$this->controls['card_number_font_size']   = array(
			'tab'     => 'style',
			'group'   => 'card_style',
			'label'   => esc_html__( 'Number Font Size (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 30,
			'min'     => 1,
			'max'     => 200,
		);
		$this->controls['card_number_font_weight'] = array(
			'tab'     => 'style',
			'group'   => 'card_style',
			'label'   => esc_html__( 'Number Font Weight', 'ohmylms' ),
			'type'    => 'number',
			'default' => 700,
			'min'     => 100,
			'max'     => 900,
		);

		$this->controls['title_color']       = array(
			'tab'     => 'style',
			'group'   => 'title_typography',
			'label'   => esc_html__( 'Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#1E1E1E' ),
		);
		$this->controls['title_font_size']   = array(
			'tab'     => 'style',
			'group'   => 'title_typography',
			'label'   => esc_html__( 'Font Size (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 22,
			'min'     => 1,
			'max'     => 200,
		);
		$this->controls['title_font_weight'] = array(
			'tab'     => 'style',
			'group'   => 'title_typography',
			'label'   => esc_html__( 'Font Weight', 'ohmylms' ),
			'type'    => 'number',
			'default' => 700,
			'min'     => 100,
			'max'     => 900,
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Is bricks edit mode for Dashboard element.
	 */
	private function is_bricks_edit_mode() {
		if ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) {
			return true;
		}
		if ( function_exists( 'bricks_is_builder_main' ) && bricks_is_builder_main() ) {
			return true;
		}
		if ( function_exists( 'bricks_is_rest_call' ) && bricks_is_rest_call() ) {
			return true;
		}
		return false;
	}

	/**
	 * Extract color for Dashboard element.
	 *
	 * @param mixed $color Color.
	 */
	private function extract_color( $color ) {
		if ( is_array( $color ) ) {
			if ( ! empty( $color['hex'] ) ) {
				return $color['hex'];
			}
			if ( ! empty( $color['rgb'] ) ) {
				return $color['rgb'];
			}
			return '';
		}
		return is_string( $color ) ? $color : '';
	}

	/**
	 * Build shortcode attrs for Dashboard element.
	 *
	 * @param mixed $settings Configuration settings.
	 */
	private function build_shortcode_attrs( $settings ) {
		$attrs = array(
			'show_header'    => ( isset( $settings['show_header'] ) && true === $settings['show_header'] ) ? 'yes' : 'no',
			'my_profile_url' => isset( $settings['my_profile_url'] ) ? $settings['my_profile_url'] : '',
			'my_courses_url' => isset( $settings['my_courses_url'] ) ? $settings['my_courses_url'] : '',
		);

		$color_keys = array(
			'header_bg_color',
			'user_menu_color',
			'user_menu_bg_color',
			'user_menu_hover_color',
			'user_menu_hover_bg_color',
			'user_menu_icon_color',
			'user_menu_icon_hover_color',
			'dashboard_bg_color',
			'course_button_text_color',
			'course_button_bg_color',
			'course_button_hover_text_color',
			'course_button_hover_bg_color',
			'card_bg_color',
			'card_text_color',
			'card_number_color',
			'title_color',
		);

		foreach ( $color_keys as $key ) {
			if ( isset( $settings[ $key ] ) ) {
				$value = $this->extract_color( $settings[ $key ] );
				if ( '' !== $value ) {
					$attrs[ $key ] = $value;
				}
			}
		}

		$number_keys = array(
			'user_menu_font_size',
			'user_menu_font_weight',
			'course_button_font_size',
			'course_button_font_weight',
			'course_button_border_radius',
			'card_text_font_size',
			'card_text_font_weight',
			'card_number_font_size',
			'card_number_font_weight',
			'title_font_size',
			'title_font_weight',
		);

		foreach ( $number_keys as $key ) {
			if ( isset( $settings[ $key ] ) && '' !== $settings[ $key ] ) {
				$attrs[ $key ] = $settings[ $key ];
			}
		}

		return $attrs;
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	/**
	 * Render for Dashboard element.
	 */
	public function render() {
		$settings        = $this->settings;
		$is_edit_mode    = $this->is_bricks_edit_mode();
		$shortcode_attrs = $this->build_shortcode_attrs( $settings );

		if ( $is_edit_mode ) {
			add_filter( 'ohmylms_gutenberg_preview_mode', '__return_true' );
			add_filter( 'ohmylms_bricks_preview_mode', '__return_true' );
		}

		echo '<div class="ohmylms-page ohmylms">';

		try {
			ob_start();
			ShortCodeDashboard::output( $shortcode_attrs );
			$output = ob_get_clean();

			if ( '' !== $output ) {
				echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} elseif ( $is_edit_mode ) {
				echo '<div class="ohmylms-bricks-preview-notice">';
				echo '<p>' . esc_html__( 'OhMyLMS Dashboard — preview requires a logged-in student account.', 'ohmylms' ) . '</p>';
				echo '</div>';
			}
		} catch ( \Throwable $e ) {
			if ( ob_get_level() > 0 ) {
				ob_end_clean();
			}
			if ( $is_edit_mode ) {
				echo '<div class="ohmylms-bricks-preview-notice">';
				echo '<p>' . esc_html__( 'OhMyLMS Dashboard — render error.', 'ohmylms' ) . '</p>';
				echo '</div>';
			}
		} finally {
			if ( $is_edit_mode ) {
				remove_filter( 'ohmylms_gutenberg_preview_mode', '__return_true' );
				remove_filter( 'ohmylms_bricks_preview_mode', '__return_true' );
			}
		}

		echo '</div>';
	}
}
