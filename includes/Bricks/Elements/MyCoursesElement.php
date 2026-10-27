<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * OhMyLMS My Courses Element for Bricks Builder
 *
 * Styling is handled entirely by ShortCodeMyCourses::output_custom_styles(),
 * which injects a scoped <style> block using the shortcode attrs. No Bricks
 * native CSS generation ('css' key) is used for style controls — that would
 * conflict with the shortcode's !important inline styles.
 *
 * @package OhMyLMS\Bricks\Elements
 * @since 1.0.0
 */

namespace OhMyLMS\Bricks\Elements;

use OhMyLMS\Shortcodes\ShortCodeMyCourses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MyCoursesElement class
 */
class MyCoursesElement extends \Bricks\Element {

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
	public $name = 'ohmylms-my-courses';
	/**
	 * Icon.
	 *
	 * @var mixed
	 */
	public $icon = 'ti-id-badge';
	/**
	 * Keywords.
	 *
	 * @var mixed
	 */
	public $keywords = array( 'my-courses', 'student', 'dashboard', 'creator', 'lms' );
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
	 * Get label for My courses element.
	 */
	public function get_label() {
		return esc_html__( 'OhMyLMS My Courses', 'ohmylms' );
	}

	// -------------------------------------------------------------------------
	// Controls
	// -------------------------------------------------------------------------

	/**
	 * Set controls for My courses element.
	 */
	public function set_controls() {
		$this->controls       = array();
		$this->control_groups = array();
		$this->set_content_controls();
		$this->set_style_controls();
	}

	/**
	 * Set content controls for My courses element.
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
	 * Style controls — NO 'css' key on any control.
	 * Values are passed to ShortCodeMyCourses::output() which handles all styling
	 * via its own scoped <style> block (output_custom_styles).
	 */
	private function set_style_controls() {

		// ── Groups ────────────────────────────────────────────────────────────

		$this->control_groups['header_style']          = array(
			'title'    => esc_html__( 'Header Style', 'ohmylms' ),
			'tab'      => 'style',
			'required' => array( array( 'show_header', '=', true ) ),
		);
		$this->control_groups['user_menu_style']       = array(
			'title' => esc_html__( 'User Menu Style', 'ohmylms' ),
			'tab'   => 'style',
		);
		$this->control_groups['layout_style']          = array(
			'title' => esc_html__( 'Layout Style', 'ohmylms' ),
			'tab'   => 'style',
		);
		$this->control_groups['title_typography']      = array(
			'title' => esc_html__( 'Title Typography', 'ohmylms' ),
			'tab'   => 'style',
		);
		$this->control_groups['text_typography']       = array(
			'title' => esc_html__( 'Text Typography', 'ohmylms' ),
			'tab'   => 'style',
		);
		$this->control_groups['button_style']          = array(
			'title' => esc_html__( 'Button Style', 'ohmylms' ),
			'tab'   => 'style',
		);
		$this->control_groups['card_style']            = array(
			'title' => esc_html__( 'Card Style', 'ohmylms' ),
			'tab'   => 'style',
		);
		$this->control_groups['progress_bar_style']    = array(
			'title' => esc_html__( 'Progress Bar Style', 'ohmylms' ),
			'tab'   => 'style',
		);
		$this->control_groups['tab_style']             = array(
			'title' => esc_html__( 'Tab Style', 'ohmylms' ),
			'tab'   => 'style',
		);
		$this->control_groups['no_courses_card_style'] = array(
			'title' => esc_html__( 'No Courses Card Style', 'ohmylms' ),
			'tab'   => 'style',
		);

		// ── Header Style ──────────────────────────────────────────────────────

		$this->controls['header_bg_color'] = array(
			'tab'     => 'style',
			'group'   => 'header_style',
			'label'   => esc_html__( 'Header Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#000D2C' ),
		);

		// ── User Menu Style ───────────────────────────────────────────────────

		$this->controls['user_menu_color']            = array(
			'tab'     => 'style',
			'group'   => 'user_menu_style',
			'label'   => esc_html__( 'Text Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#7A8B9A' ),
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
			'default' => array( 'hex' => '#000D25' ),
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
			'default' => array( 'hex' => '#7A8B9A' ),
		);
		$this->controls['user_menu_icon_hover_color'] = array(
			'tab'     => 'style',
			'group'   => 'user_menu_style',
			'label'   => esc_html__( 'Icon Hover Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#4361EE' ),
		);

		// ── Layout Style ──────────────────────────────────────────────────────

		$this->controls['section_bg_color'] = array(
			'tab'     => 'style',
			'group'   => 'layout_style',
			'label'   => esc_html__( 'Section Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#F9FAFD' ),
		);
		$this->controls['wrapper_bg_color'] = array(
			'tab'     => 'style',
			'group'   => 'layout_style',
			'label'   => esc_html__( 'Wrapper Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#FFFFFF' ),
		);
		$this->controls['wrapper_padding']  = array(
			'tab'     => 'style',
			'group'   => 'layout_style',
			'label'   => esc_html__( 'Wrapper Padding (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 30,
			'min'     => 0,
			'max'     => 200,
		);

		// ── Title Typography ──────────────────────────────────────────────────

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
			'default' => 24,
			'min'     => 1,
			'max'     => 200,
		);
		$this->controls['title_font_weight'] = array(
			'tab'     => 'style',
			'group'   => 'title_typography',
			'label'   => esc_html__( 'Font Weight', 'ohmylms' ),
			'type'    => 'number',
			'default' => 600,
			'min'     => 100,
			'max'     => 900,
		);

		// ── Text Typography ───────────────────────────────────────────────────

		$this->controls['text_color']       = array(
			'tab'     => 'style',
			'group'   => 'text_typography',
			'label'   => esc_html__( 'Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#52525B' ),
		);
		$this->controls['text_font_size']   = array(
			'tab'     => 'style',
			'group'   => 'text_typography',
			'label'   => esc_html__( 'Font Size (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 14,
			'min'     => 1,
			'max'     => 200,
		);
		$this->controls['text_font_weight'] = array(
			'tab'     => 'style',
			'group'   => 'text_typography',
			'label'   => esc_html__( 'Font Weight', 'ohmylms' ),
			'type'    => 'number',
			'default' => 400,
			'min'     => 100,
			'max'     => 900,
		);

		// ── Button Style ──────────────────────────────────────────────────────

		$this->controls['button_text_color']       = array(
			'tab'     => 'style',
			'group'   => 'button_style',
			'label'   => esc_html__( 'Text Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#FFFFFF' ),
		);
		$this->controls['button_bg_color']         = array(
			'tab'     => 'style',
			'group'   => 'button_style',
			'label'   => esc_html__( 'Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#6E42D3' ),
		);
		$this->controls['button_font_size']        = array(
			'tab'     => 'style',
			'group'   => 'button_style',
			'label'   => esc_html__( 'Font Size (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 14,
			'min'     => 1,
			'max'     => 200,
		);
		$this->controls['button_font_weight']      = array(
			'tab'     => 'style',
			'group'   => 'button_style',
			'label'   => esc_html__( 'Font Weight', 'ohmylms' ),
			'type'    => 'number',
			'default' => 600,
			'min'     => 100,
			'max'     => 900,
		);
		$this->controls['button_border_width']     = array(
			'tab'     => 'style',
			'group'   => 'button_style',
			'label'   => esc_html__( 'Border Width (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 0,
			'min'     => 0,
			'max'     => 50,
		);
		$this->controls['button_border_color']     = array(
			'tab'     => 'style',
			'group'   => 'button_style',
			'label'   => esc_html__( 'Border Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#6E42D3' ),
		);
		$this->controls['button_border_radius']    = array(
			'tab'     => 'style',
			'group'   => 'button_style',
			'label'   => esc_html__( 'Border Radius (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 6,
			'min'     => 0,
			'max'     => 100,
		);
		$this->controls['button_padding']          = array(
			'tab'     => 'style',
			'group'   => 'button_style',
			'label'   => esc_html__( 'Padding (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 12,
			'min'     => 0,
			'max'     => 100,
		);
		$this->controls['button_hover_bg_color']   = array(
			'tab'     => 'style',
			'group'   => 'button_style',
			'label'   => esc_html__( 'Hover Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#5a32c2' ),
		);
		$this->controls['button_hover_text_color'] = array(
			'tab'     => 'style',
			'group'   => 'button_style',
			'label'   => esc_html__( 'Hover Text Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#FFFFFF' ),
		);

		// ── Card Style ────────────────────────────────────────────────────────

		$this->controls['card_bg_color']      = array(
			'tab'     => 'style',
			'group'   => 'card_style',
			'label'   => esc_html__( 'Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#FFFFFF' ),
		);
		$this->controls['card_padding']       = array(
			'tab'     => 'style',
			'group'   => 'card_style',
			'label'   => esc_html__( 'Padding (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 20,
			'min'     => 0,
			'max'     => 100,
		);
		$this->controls['card_border_radius'] = array(
			'tab'     => 'style',
			'group'   => 'card_style',
			'label'   => esc_html__( 'Border Radius (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 8,
			'min'     => 0,
			'max'     => 100,
		);

		// ── Progress Bar Style ────────────────────────────────────────────────

		$this->controls['progress_bar_bg_color']   = array(
			'tab'     => 'style',
			'group'   => 'progress_bar_style',
			'label'   => esc_html__( 'Track Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#E5E7EB' ),
		);
		$this->controls['progress_bar_fill_color'] = array(
			'tab'     => 'style',
			'group'   => 'progress_bar_style',
			'label'   => esc_html__( 'Fill Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#6E42D3' ),
		);

		// ── Tab Style ─────────────────────────────────────────────────────────

		$this->controls['tab_normal_color'] = array(
			'tab'     => 'style',
			'group'   => 'tab_style',
			'label'   => esc_html__( 'Normal Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#666666' ),
		);
		$this->controls['tab_active_color'] = array(
			'tab'     => 'style',
			'group'   => 'tab_style',
			'label'   => esc_html__( 'Active Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#6E42D3' ),
		);

		// ── No Courses Card Style ─────────────────────────────────────────────

		$this->controls['no_course_card_bg_color'] = array(
			'tab'     => 'style',
			'group'   => 'no_courses_card_style',
			'label'   => esc_html__( 'Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#F9FAFB' ),
		);
		$this->controls['no_course_card_padding']  = array(
			'tab'     => 'style',
			'group'   => 'no_courses_card_style',
			'label'   => esc_html__( 'Padding (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 40,
			'min'     => 0,
			'max'     => 300,
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Detect Bricks Builder edit mode.
	 *
	 * @return bool
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
	 * Extract a plain CSS color string from a Bricks color control value.
	 *
	 * Bricks stores colors as: array( 'hex' => '#rrggbb', 'rgb' => 'rgba(...)' )
	 * Falls back gracefully to a plain string or empty string.
	 *
	 * @param mixed $color Color.
	 * @return string
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
	 * Build the shortcode attrs array from Bricks settings.
	 *
	 * Passes all content and style values to ShortCodeMyCourses::output() so
	 * that output_custom_styles() can generate the correct scoped <style> block.
	 *
	 * @param array $settings Configuration settings.
	 * @return array
	 */
	private function build_shortcode_attrs( $settings ) {
		// Content controls.
		$attrs = array(
			'show_header'    => ( isset( $settings['show_header'] ) && true === $settings['show_header'] ) ? 'yes' : 'no',
			'my_profile_url' => isset( $settings['my_profile_url'] ) ? $settings['my_profile_url'] : '',
			'my_courses_url' => isset( $settings['my_courses_url'] ) ? $settings['my_courses_url'] : '',
		);

		// Color controls — extract hex/rgb from Bricks color array format.
		$color_keys = array(
			'header_bg_color',
			'user_menu_color',
			'user_menu_bg_color',
			'user_menu_hover_color',
			'user_menu_hover_bg_color',
			'user_menu_icon_color',
			'user_menu_icon_hover_color',
			'section_bg_color',
			'wrapper_bg_color',
			'title_color',
			'text_color',
			'button_text_color',
			'button_bg_color',
			'button_border_color',
			'button_hover_bg_color',
			'button_hover_text_color',
			'card_bg_color',
			'progress_bar_bg_color',
			'progress_bar_fill_color',
			'tab_normal_color',
			'tab_active_color',
			'no_course_card_bg_color',
		);

		foreach ( $color_keys as $key ) {
			if ( isset( $settings[ $key ] ) ) {
				$value = $this->extract_color( $settings[ $key ] );
				if ( '' !== $value ) {
					$attrs[ $key ] = $value;
				}
			}
		}

		// Number controls — pass through as plain scalars.
		$number_keys = array(
			'user_menu_font_size',
			'user_menu_font_weight',
			'wrapper_padding',
			'title_font_size',
			'title_font_weight',
			'text_font_size',
			'text_font_weight',
			'button_font_size',
			'button_font_weight',
			'button_border_width',
			'button_border_radius',
			'button_padding',
			'card_padding',
			'card_border_radius',
			'no_course_card_padding',
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
	 * Render element output.
	 *
	 * Wraps output in .ohmylms-page so the shortcode's CSS selectors match.
	 *
	 * @return void
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
			ShortCodeMyCourses::output( $shortcode_attrs );
			$output = ob_get_clean();

			if ( '' !== $output ) {
				echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} elseif ( $is_edit_mode ) {
				echo '<div class="ohmylms-bricks-preview-notice">';
				echo '<p>' . esc_html__( 'OhMyLMS My Courses — preview requires a logged-in student account.', 'ohmylms' ) . '</p>';
				echo '</div>';
			}
		} catch ( \Throwable $e ) {
			if ( ob_get_level() > 0 ) {
				ob_end_clean();
			}
			if ( $is_edit_mode ) {
				echo '<div class="ohmylms-bricks-preview-notice">';
				echo '<p>' . esc_html__( 'OhMyLMS My Courses — render error.', 'ohmylms' ) . '</p>';
				echo '</div>';
			}
		} finally {
			if ( $is_edit_mode ) {
				remove_filter( 'ohmylms_gutenberg_preview_mode', '__return_true' );
				remove_filter( 'ohmylms_bricks_preview_mode', '__return_true' );
			}
		}

		echo '</div>'; // .ohmylms-page
	}
}
