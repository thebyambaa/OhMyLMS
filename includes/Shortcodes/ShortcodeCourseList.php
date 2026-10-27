<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Course List Shortcode.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Shortcodes;

use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Course List Shortcode
 *
 * Class ShortcodeCourseList
 *
 * @package OhMyLMS\Shortcodes
 * @since 1.0.0
 */
class ShortcodeCourseList {

	/**
	 * Render the course list
	 *
	 * @since 1.0.0
	 * @param mixed $atts Atts.
	 */
	public static function output( $atts ): void {
		// Enqueue shortcode styles.
		self::enqueue_styles();
		self::course_list( $atts );
	}

	/**
	 * Enqueue shortcode styles
	 *
	 * @since 1.0.0
	 */
	private static function enqueue_styles() {
		$css_file = plugin_dir_url( __FILE__ ) . '../../assets/css/shortcode-course-list.css';
		$css_path = plugin_dir_path( __FILE__ ) . '../../assets/css/shortcode-course-list.css';

		if ( file_exists( $css_path ) ) {
			wp_enqueue_style(
				'ohmylms-shortcode-course-list',
				$css_file,
				array(),
				filemtime( $css_path )
			);
		}
	}

	/**
	 * Get default attributes for the shortcode
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private static function get_default_atts() {
		return array(
			// Content settings.
			'posts_per_page'                         => get_option( 'ohmylms_courses_per_page', 10 ),
			'orderby'                                => 'date',
			'order'                                  => 'DESC',
			// Curriculum item and Course Bundle IDs, comma-separated (e.g. curriculum="12,14" bundle="3").
			'curriculum'                             => '',
			'bundle'                                 => '',

			// Layout settings.
			'layout'                                 => get_option( 'ohmylms_archive_page_layout', 'grid' ),
			'layout_style'                           => get_option( 'ohmylms_archive_page_layout_style', 'grid-style1' ),
			'show_filter'                            => get_option( 'ohmylms_archive_page_filter_is_enabled', 'no' ),
			'show_search'                            => get_option( 'ohmylms_archive_page_search_is_enabled', 'no' ),
			'show_sort'                              => get_option( 'ohmylms_archive_page_sorting_is_enabled', 'no' ),
			'columns'                                => get_option( 'ohmylms_columns_per_row', 'no' ),
			'is_enable_category'                     => get_option( 'ohmylms_archive_page_category_is_enabled', 'no' ),
			'course_rows'                            => get_option( 'ohmylms_archive_page_row', array() ),

			// 1. Wrapper Style
			'wrapper_background'                     => '',
			'wrapper_margin'                         => '',
			'wrapper_padding'                        => '',

			// 2. Card Style
			'card_background'                        => '',
			'card_border_radius'                     => '12px',
			'card_border_width'                      => '',
			'card_border_type'                       => '',
			'card_border_color'                      => '',
			'card_padding'                           => '',
			'card_box_shadow'                        => '',
			'card_hover_box_shadow'                  => '',
			'card_hover_border_color'                => '',
			'card_hover_background'                  => '',

			// 3. Card Header Style
			'card_header_image_border_radius'        => '12px 12px 0 0',
			'card_header_margin'                     => '',
			'card_header_padding'                    => '',

			// 4. Card Content Style
			'card_content_background'                => '',

			// 4.1 Title Typography (Elementor-style)
			'title_typography_font_family'           => '',
			'title_typography_font_size'             => '16px',
			'title_typography_font_weight'           => '600',
			'title_typography_text_transform'        => '',
			'title_typography_font_style'            => '',
			'title_typography_text_decoration'       => '',
			'title_typography_line_height'           => '1.4',
			'title_typography_letter_spacing'        => '',
			'title_typography_word_spacing'          => '',
			'title_color'                            => '#000D25',
			'title_margin'                           => '',

			// 4.2 Description Typography
			'description_typography_font_family'     => '',
			'description_typography_font_size'       => '',
			'description_typography_font_weight'     => '',
			'description_typography_text_transform'  => '',
			'description_typography_font_style'      => '',
			'description_typography_text_decoration' => '',
			'description_typography_line_height'     => '',
			'description_typography_letter_spacing'  => '',
			'description_typography_word_spacing'    => '',
			'description_color'                      => '',
			'description_margin'                     => '',

			// 4.3 Course Meta Style
			'course_meta_background'                 => '',
			'course_meta_padding'                    => '',
			'course_meta_margin'                     => '',
			'course_meta_border_radius'              => '',
			'course_meta_border'                     => '',
			'course_meta_row_gap'                    => '',
			'course_meta_column_gap'                 => '',
			'course_meta_typography_font_family'     => '',
			'course_meta_typography_font_size'       => '',
			'course_meta_typography_font_weight'     => '',
			'course_meta_typography_text_transform'  => '',
			'course_meta_typography_font_style'      => '',
			'course_meta_typography_text_decoration' => '',
			'course_meta_typography_line_height'     => '',
			'course_meta_typography_letter_spacing'  => '',
			'course_meta_typography_word_spacing'    => '',
			'course_meta_color'                      => '',
			'course_meta_icon_size'                  => '',

			// 4.4 Cohort Meta Style
			'cohort_meta_background'                 => '',
			'cohort_meta_padding'                    => '',
			'cohort_meta_margin'                     => '',
			'cohort_meta_border_radius'              => '',
			'cohort_meta_border'                     => '',
			'cohort_meta_row_gap'                    => '',
			'cohort_meta_typography_font_family'     => '',
			'cohort_meta_typography_font_size'       => '',
			'cohort_meta_typography_font_weight'     => '',
			'cohort_meta_typography_text_transform'  => '',
			'cohort_meta_typography_font_style'      => '',
			'cohort_meta_typography_text_decoration' => '',
			'cohort_meta_typography_line_height'     => '',
			'cohort_meta_typography_letter_spacing'  => '',
			'cohort_meta_typography_word_spacing'    => '',
			'cohort_meta_color'                      => '',
			'cohort_meta_icon_size'                  => '',
			'cohort_meta_icon_spacing'               => '',

			// 4.5 Price Style
			'price_background'                       => '',
			'price_padding'                          => '',
			'price_margin'                           => '',
			'price_border_radius'                    => '',
			'price_border'                           => '',
			'price_typography_font_family'           => '',
			'price_typography_font_size'             => '',
			'price_typography_font_weight'           => '',
			'price_typography_text_transform'        => '',
			'price_typography_font_style'            => '',
			'price_typography_text_decoration'       => '',
			'price_typography_line_height'           => '',
			'price_typography_letter_spacing'        => '',
			'price_typography_word_spacing'          => '',
			'price_color'                            => '',
			'price_regular_color'                    => '',

			// 4.6 Button Style
			'button_background'                      => '#6e42d3',
			'button_padding'                         => '',
			'button_margin'                          => '',
			'button_border_radius'                   => '',
			'button_border'                          => '',
			'button_typography_font_family'          => '',
			'button_typography_font_size'            => '',
			'button_typography_font_weight'          => '',
			'button_typography_text_transform'       => '',
			'button_typography_font_style'           => '',
			'button_typography_text_decoration'      => '',
			'button_typography_line_height'          => '',
			'button_typography_letter_spacing'       => '',
			'button_typography_word_spacing'         => '',
			'button_color'                           => '#FFF',
			'button_box_shadow'                      => '',
			'button_hover_background'                => 'transparent',
			'button_hover_color'                     => '#6e42d3',
			'button_hover_box_shadow'                => '',
			'button_hover_border_color'              => '#6e42d3',

			// Legacy attributes for backward compatibility.
			'container_class'                        => '',
			'course_card_class'                      => '',
			'grid_gap'                               => '',
			'card_bg_color'                          => '',
			'card_shadow'                            => '',
			'title_font_size'                        => '',
			'price_font_size'                        => '',
			'button_bg_color'                        => '',
			'button_text_color'                      => '',
			'container_padding'                      => '',
			'container_margin'                       => '',
			'card_margin'                            => '',
		);
	}

	/**
	 * Show the list.
	 *
	 * @since 1.0.0
	 * @param mixed $atts Atts.
	 */
	private static function course_list( $atts ) {
		// Parse attributes with defaults.
		$atts = shortcode_atts( self::get_default_atts(), $atts, 'ohmylms_course_list' );

		// Store attributes globally for CSS generation.
		global $ohmylms_course_list_attributes;
		$ohmylms_course_list_attributes = $atts;

		// Build query arguments.
		$args = array(
			'post_type'      => 'ohmylms-course',
			'post_status'    => 'publish',
			'posts_per_page' => intval( $atts['posts_per_page'] ),
			'orderby'        => sanitize_text_field( $atts['orderby'] ),
			'order'          => sanitize_text_field( $atts['order'] ),
		);

		// Limit to curriculum items (and everything below them) and Course Bundles when given.
		$args = \OhMyLMS\Curriculum\Placement::narrow_query(
			$args,
			\OhMyLMS\Curriculum\Placement::ids_from_list( $atts['curriculum'], 'item' ),
			\OhMyLMS\Curriculum\Placement::ids_from_list( $atts['bundle'], 'bundle' )
		);

		// Set up global query for template.
		global $wp_query;
		$original_query = $wp_query;
		$wp_query       = new WP_Query( $args );

		ob_start();

		// Inject custom styles.
		self::inject_custom_styles();

		// Make shortcode attributes available to template.
		$shortcode_atts = $atts;
		$atts           = $atts; // Also make $atts available for template compatibility.

		// Locate and render archive template directly.
		$template_path = self::locate_course_template();
		if ( $template_path && file_exists( $template_path ) ) {
			// Transform shortcode attributes to match template expectations
			// Use output buffering to safely capture template output.
			try {
				include $template_path;
			} catch ( Exception $e ) {
				echo '<p class="ohmylms-error">' . esc_html__( 'Error loading course template.', 'ohmylms' ) . '</p>';
			}
		} else {
			echo '<p class="ohmylms-no-template">' . esc_html__( 'Course template not found.', 'ohmylms' ) . '</p>';
		}

		// Restore original query.
		$wp_query = $original_query;
		wp_reset_postdata();

		echo ob_get_clean();
	}

	/**
	 * Generate and inject custom CSS styles from shortcode attributes
	 *
	 * @since 1.0.0
	 */
	private static function inject_custom_styles() {
		global $ohmylms_course_list_attributes;

		if ( empty( $ohmylms_course_list_attributes ) ) {
			return;
		}

		$css = self::generate_custom_css( $ohmylms_course_list_attributes );

		if ( ! empty( $css ) ) {
			echo '<style type="text/css" id="ohmylms-course-list-custom-styles">' . $css . '</style>';
		}
	}

	/**
	 * Generate CSS from shortcode attributes
	 *
	 * @since 1.0.0
	 * @param array $attrs Shortcode attributes.
	 * @return string Generated CSS
	 */
	private static function generate_custom_css( $attrs ) {
		$css = '';

		// 1. Wrapper Style
		$wrapper_styles = array();
		if ( ! empty( $attrs['wrapper_background'] ) ) {
			$wrapper_styles[] = 'background: ' . esc_attr( $attrs['wrapper_background'] ) . ' !important';
			$css             .= '.ohmylms-page.ohmylms-course-list-shortcode .ohmylms-courses { background: ' . esc_attr( $attrs['wrapper_background'] ) . ' !important; }' . "\n";
		}
		if ( ! empty( $attrs['wrapper_margin'] ) ) {
			$wrapper_styles[] = 'margin: ' . esc_attr( $attrs['wrapper_margin'] ) . ' !important';
		}
		if ( ! empty( $attrs['wrapper_padding'] ) ) {
			$wrapper_styles[] = 'padding: ' . esc_attr( $attrs['wrapper_padding'] ) . ' !important';
		}

		// Legacy container styles for backward compatibility.
		if ( ! empty( $attrs['container_padding'] ) ) {
			$wrapper_styles[] = 'padding: ' . esc_attr( $attrs['container_padding'] ) . ' !important';
		}
		if ( ! empty( $attrs['container_margin'] ) ) {
			$wrapper_styles[] = 'margin: ' . esc_attr( $attrs['container_margin'] ) . ' !important';
		}

		if ( ! empty( $wrapper_styles ) ) {
			$css .= '.ohmylms-container, .ohmylms-course-cards { ' . implode( '; ', $wrapper_styles ) . '; }' . "\n";
		}

		// Grid gap.
		if ( ! empty( $attrs['grid_gap'] ) ) {
			$css .= '.ohmylms-course-cards { gap: ' . esc_attr( $attrs['grid_gap'] ) . ' !important; }' . "\n";
		}

		// 2. Card Style
		$card_styles = array();
		if ( ! empty( $attrs['card_background'] ) ) {
			$card_styles[] = 'background: ' . esc_attr( $attrs['card_background'] ) . ' !important';
		}
		if ( ! empty( $attrs['card_border_radius'] ) ) {
			$card_styles[] = 'border-radius: ' . esc_attr( $attrs['card_border_radius'] ) . ' !important';
		}
		if ( ! empty( $attrs['card_border_width'] ) && ! empty( $attrs['card_border_type'] ) && ! empty( $attrs['card_border_color'] ) ) {
			$card_styles[] = 'border: ' . esc_attr( $attrs['card_border_width'] ) . ' ' . esc_attr( $attrs['card_border_type'] ) . ' ' . esc_attr( $attrs['card_border_color'] ) . ' !important';
		} elseif ( ! empty( $attrs['card_border_color'] ) ) {
			$card_styles[] = 'border: 1px solid ' . esc_attr( $attrs['card_border_color'] ) . ' !important';
		}
		if ( ! empty( $attrs['card_padding'] ) ) {
			$card_styles[] = 'padding: ' . esc_attr( $attrs['card_padding'] ) . ' !important';
		}
		if ( ! empty( $attrs['card_box_shadow'] ) ) {
			$card_styles[] = 'box-shadow: ' . esc_attr( $attrs['card_box_shadow'] ) . ' !important';
		}

		// Legacy card styles for backward compatibility.
		if ( ! empty( $attrs['card_bg_color'] ) ) {
			$card_styles[] = 'background-color: ' . esc_attr( $attrs['card_bg_color'] ) . ' !important';
		}
		if ( ! empty( $attrs['card_shadow'] ) ) {
			$card_styles[] = 'box-shadow: ' . esc_attr( $attrs['card_shadow'] ) . ' !important';
		}
		if ( ! empty( $attrs['card_margin'] ) ) {
			$card_styles[] = 'margin: ' . esc_attr( $attrs['card_margin'] ) . ' !important';
		}

		if ( ! empty( $card_styles ) ) {
			$css .= '.course-card { ' . implode( '; ', $card_styles ) . '; }' . "\n";
		}

		// Card Hover Styles.
		$card_hover_styles = array();
		if ( ! empty( $attrs['card_hover_box_shadow'] ) ) {
			$card_hover_styles[] = 'box-shadow: ' . esc_attr( $attrs['card_hover_box_shadow'] ) . ' !important';
		}
		if ( ! empty( $attrs['card_hover_border_color'] ) ) {
			$card_hover_styles[] = 'border-color: ' . esc_attr( $attrs['card_hover_border_color'] ) . ' !important';
		}
		if ( ! empty( $attrs['card_hover_background'] ) ) {
			$card_hover_styles[] = 'background: ' . esc_attr( $attrs['card_hover_background'] ) . ' !important';
		}

		if ( ! empty( $card_hover_styles ) ) {
			$css .= '.course-card:hover { ' . implode( '; ', $card_hover_styles ) . '; }' . "\n";
		}

		// 3. Card Header Style
		$card_header_styles = array();
		if ( ! empty( $attrs['card_header_margin'] ) ) {
			$card_header_styles[] = 'margin: ' . esc_attr( $attrs['card_header_margin'] );
		}
		if ( ! empty( $attrs['card_header_padding'] ) ) {
			$card_header_styles[] = 'padding: ' . esc_attr( $attrs['card_header_padding'] );
		}

		if ( ! empty( $card_header_styles ) ) {
			$css .= '.ohmylms-page.ohmylms-course-list-shortcode .ohmylms-course-cards .course-card .ohmylms-loop-course-thumbnail-link, .ohmylms-page.ohmylms-course-list-shortcode .ohmylms-course-cards .course-card .ohmylms-loop-course-thumbnail-link figure, .ohmylms-page.ohmylms-course-list-shortcode .ohmylms-course-cards .course-card .ohmylms-loop-course-thumbnail-link img { ' . implode( '; ', $card_header_styles ) . '; }' . "\n";
		}

		// Card Header Image Border Radius.
		if ( ! empty( $attrs['card_header_image_border_radius'] ) ) {
			$css .= '.ohmylms-page.ohmylms-course-list-shortcode .ohmylms-course-cards .course-card .ohmylms-loop-course-thumbnail-link, .ohmylms-page.ohmylms-course-list-shortcode .ohmylms-course-cards .course-card .ohmylms-loop-course-thumbnail-link figure, .ohmylms-page.ohmylms-course-list-shortcode .ohmylms-course-cards .course-card .ohmylms-loop-course-thumbnail-link img { border-radius: ' . esc_attr( $attrs['card_header_image_border_radius'] ) . '}' . "\n";
		}

		// 4. Card Content Style
		if ( ! empty( $attrs['card_content_background'] ) ) {
			$css .= '.ohmylms-page.ohmylms-course-list-shortcode .ohmylms-course-cards .course-card .course-info { background: ' . esc_attr( $attrs['card_content_background'] ) . '; }' . "\n";
		}

		// 4.1 Title Typography and Styles
		$title_styles = array();
		$title_styles = array_merge( $title_styles, self::build_typography_styles( $attrs, 'title_typography_' ) );
		if ( ! empty( $attrs['title_color'] ) ) {
			$title_styles[] = 'color: ' . esc_attr( $attrs['title_color'] ) . ' !important';
		}
		if ( ! empty( $attrs['title_margin'] ) ) {
			$title_styles[] = 'margin: ' . esc_attr( $attrs['title_margin'] ) . ' !important';
		}

		// Legacy title styles for backward compatibility.
		if ( ! empty( $attrs['title_font_size'] ) ) {
			$title_styles[] = 'font-size: ' . esc_attr( $attrs['title_font_size'] ) . ' !important';
		}

		if ( ! empty( $title_styles ) ) {
			$css .= '.ohmylms-page.ohmylms-course-list-shortcode .ohmylms-course-cards .course-card .course-info .ohmylms-loop-course-title { ' . implode( '; ', $title_styles ) . '; }' . "\n";
		}

		// 4.2 Description Typography and Styles
		$description_styles = array();
		$description_styles = array_merge( $description_styles, self::build_typography_styles( $attrs, 'description_typography_' ) );
		if ( ! empty( $attrs['description_color'] ) ) {
			$description_styles[] = 'color: ' . esc_attr( $attrs['description_color'] ) . ' !important';
		}
		if ( ! empty( $attrs['description_margin'] ) ) {
			$description_styles[] = 'margin: ' . esc_attr( $attrs['description_margin'] ) . ' !important';
		}

		if ( ! empty( $description_styles ) ) {
			$css .= '.course-card .course-excerpt, .course-card .course-description, .course-card .course-content p { ' . implode( '; ', $description_styles ) . '; }' . "\n";
		}

		// 4.3 Course Meta Style
		$course_meta_styles = array();
		if ( ! empty( $attrs['course_meta_background'] ) ) {
			$course_meta_styles[] = 'background: ' . esc_attr( $attrs['course_meta_background'] ) . ' !important';
		}
		if ( ! empty( $attrs['course_meta_padding'] ) ) {
			$course_meta_styles[] = 'padding: ' . esc_attr( $attrs['course_meta_padding'] ) . ' !important';
		}
		if ( ! empty( $attrs['course_meta_margin'] ) ) {
			$course_meta_styles[] = 'margin: ' . esc_attr( $attrs['course_meta_margin'] ) . ' !important';
		}
		if ( ! empty( $attrs['course_meta_border_radius'] ) ) {
			$course_meta_styles[] = 'border-radius: ' . esc_attr( $attrs['course_meta_border_radius'] ) . ' !important';
		}
		if ( ! empty( $attrs['course_meta_border'] ) ) {
			$course_meta_styles[] = 'border: ' . esc_attr( $attrs['course_meta_border'] ) . ' !important';
		}
		if ( ! empty( $attrs['course_meta_row_gap'] ) ) {
			$course_meta_styles[] = 'row-gap: ' . esc_attr( $attrs['course_meta_row_gap'] ) . ' !important';
		}
		if ( ! empty( $attrs['course_meta_column_gap'] ) ) {
			$course_meta_styles[] = 'column-gap: ' . esc_attr( $attrs['course_meta_column_gap'] ) . ' !important';
		}
		$course_meta_styles = array_merge( $course_meta_styles, self::build_typography_styles( $attrs, 'course_meta_typography_' ) );
		if ( ! empty( $attrs['course_meta_color'] ) ) {
			$course_meta_styles[] = 'color: ' . esc_attr( $attrs['course_meta_color'] ) . ' !important';
		}

		if ( ! empty( $course_meta_styles ) ) {
			$css .= '.course-card .course-meta, .course-card .course-info { ' . implode( '; ', $course_meta_styles ) . '; }' . "\n";
		}

		// Course Meta Icon Size.
		if ( ! empty( $attrs['course_meta_icon_size'] ) ) {
			$css .= '.course-card .course-meta i, .course-card .course-meta .icon, .course-card .course-info i, .course-card .course-info .icon { font-size: ' . esc_attr( $attrs['course_meta_icon_size'] ) . ' !important; width: ' . esc_attr( $attrs['course_meta_icon_size'] ) . ' !important; height: ' . esc_attr( $attrs['course_meta_icon_size'] ) . ' !important; }' . "\n";
		}

		// 4.4 Cohort Meta Style
		$cohort_meta_styles = array();
		if ( ! empty( $attrs['cohort_meta_background'] ) ) {
			$cohort_meta_styles[] = 'background: ' . esc_attr( $attrs['cohort_meta_background'] ) . ' !important';
		}
		if ( ! empty( $attrs['cohort_meta_padding'] ) ) {
			$cohort_meta_styles[] = 'padding: ' . esc_attr( $attrs['cohort_meta_padding'] ) . ' !important';
		}
		if ( ! empty( $attrs['cohort_meta_margin'] ) ) {
			$cohort_meta_styles[] = 'margin: ' . esc_attr( $attrs['cohort_meta_margin'] ) . ' !important';
		}
		if ( ! empty( $attrs['cohort_meta_border_radius'] ) ) {
			$cohort_meta_styles[] = 'border-radius: ' . esc_attr( $attrs['cohort_meta_border_radius'] ) . ' !important';
		}
		if ( ! empty( $attrs['cohort_meta_border'] ) ) {
			$cohort_meta_styles[] = 'border: ' . esc_attr( $attrs['cohort_meta_border'] ) . ' !important';
		}
		if ( ! empty( $attrs['cohort_meta_row_gap'] ) ) {
			$cohort_meta_styles[] = 'row-gap: ' . esc_attr( $attrs['cohort_meta_row_gap'] ) . ' !important';
		}
		$cohort_meta_styles = array_merge( $cohort_meta_styles, self::build_typography_styles( $attrs, 'cohort_meta_typography_' ) );
		if ( ! empty( $attrs['cohort_meta_color'] ) ) {
			$cohort_meta_styles[] = 'color: ' . esc_attr( $attrs['cohort_meta_color'] ) . ' !important';
		}

		if ( ! empty( $cohort_meta_styles ) ) {
			$css .= '.course-card .cohort-meta, .course-card .cohort-info { ' . implode( '; ', $cohort_meta_styles ) . '; }' . "\n";
		}

		// Cohort Meta Icon Size and Spacing.
		if ( ! empty( $attrs['cohort_meta_icon_size'] ) ) {
			$css .= '.course-card .cohort-meta i, .course-card .cohort-meta .icon, .course-card .cohort-info i, .course-card .cohort-info .icon { font-size: ' . esc_attr( $attrs['cohort_meta_icon_size'] ) . ' !important; width: ' . esc_attr( $attrs['cohort_meta_icon_size'] ) . ' !important; height: ' . esc_attr( $attrs['cohort_meta_icon_size'] ) . ' !important; }' . "\n";
		}
		if ( ! empty( $attrs['cohort_meta_icon_spacing'] ) ) {
			$css .= '.course-card .cohort-meta i, .course-card .cohort-meta .icon, .course-card .cohort-info i, .course-card .cohort-info .icon { margin-right: ' . esc_attr( $attrs['cohort_meta_icon_spacing'] ) . ' !important; }' . "\n";
		}

		// 4.5 Price Style
		$price_styles = array();
		if ( ! empty( $attrs['price_background'] ) ) {
			$price_styles[] = 'background: ' . esc_attr( $attrs['price_background'] ) . ' !important';
		}
		if ( ! empty( $attrs['price_padding'] ) ) {
			$price_styles[] = 'padding: ' . esc_attr( $attrs['price_padding'] ) . ' !important';
		}
		if ( ! empty( $attrs['price_margin'] ) ) {
			$price_styles[] = 'margin: ' . esc_attr( $attrs['price_margin'] ) . ' !important';
		}
		if ( ! empty( $attrs['price_border_radius'] ) ) {
			$price_styles[] = 'border-radius: ' . esc_attr( $attrs['price_border_radius'] ) . ' !important';
		}
		if ( ! empty( $attrs['price_border'] ) ) {
			$price_styles[] = 'border: ' . esc_attr( $attrs['price_border'] ) . ' !important';
		}
		$price_styles = array_merge( $price_styles, self::build_typography_styles( $attrs, 'price_typography_' ) );
		if ( ! empty( $attrs['price_color'] ) ) {
			$price_styles[] = 'color: ' . esc_attr( $attrs['price_color'] ) . ' !important';
		}

		// Legacy price styles for backward compatibility.
		if ( ! empty( $attrs['price_font_size'] ) ) {
			$price_styles[] = 'font-size: ' . esc_attr( $attrs['price_font_size'] ) . ' !important';
		}

		if ( ! empty( $price_styles ) ) {
			$css .= '.course-card .course-price, .course-card .price { ' . implode( '; ', $price_styles ) . '; }' . "\n";
		}

		// Regular Price Color.
		if ( ! empty( $attrs['price_regular_color'] ) ) {
			$css .= '.course-card .course-price .regular-price, .course-card .price .regular-price, .course-card .course-price del, .course-card .price del { color: ' . esc_attr( $attrs['price_regular_color'] ) . ' !important; }' . "\n";
		}

		// 4.6 Button Style
		$button_styles = array();

		if ( ! empty( $attrs['button_background'] ) ) {
			$button_styles[] = 'background: ' . esc_attr( $attrs['button_background'] ) . ' !important';
		}
		if ( ! empty( $attrs['button_padding'] ) ) {
			$button_styles[] = 'padding: ' . esc_attr( $attrs['button_padding'] ) . ' !important';
		}
		if ( ! empty( $attrs['button_margin'] ) ) {
			$button_styles[] = 'margin: ' . esc_attr( $attrs['button_margin'] ) . ' !important';
		}
		if ( ! empty( $attrs['button_border_radius'] ) ) {
			$button_styles[] = 'border-radius: ' . esc_attr( $attrs['button_border_radius'] ) . ' !important';
		}
		if ( ! empty( $attrs['button_border'] ) ) {
			$button_styles[] = 'border: ' . esc_attr( $attrs['button_border'] ) . ' !important';
		}
		$button_styles = array_merge( $button_styles, self::build_typography_styles( $attrs, 'button_typography_' ) );
		if ( ! empty( $attrs['button_color'] ) ) {
			$button_styles[] = 'color: ' . esc_attr( $attrs['button_color'] ) . ' !important';
		}
		if ( ! empty( $attrs['button_box_shadow'] ) ) {
			$button_styles[] = 'box-shadow: ' . esc_attr( $attrs['button_box_shadow'] ) . ' !important';
		}

		// Legacy button styles for backward compatibility.
		if ( ! empty( $attrs['button_bg_color'] ) ) {
			$button_styles[] = 'background-color: ' . esc_attr( $attrs['button_bg_color'] ) . ' !important';
		}
		if ( ! empty( $attrs['button_text_color'] ) ) {
			$button_styles[] = 'color: ' . esc_attr( $attrs['button_text_color'] ) . ' !important';
		}

		if ( ! empty( $button_styles ) ) {
			$css .= '.ohmylms-page.ohmylms-course-list-shortcode .ohmylms-course-cards .course-card .ohmylms-button, .ohmylms-page.ohmylms-course-list-shortcode .ohmylms-course-loadmore-area .ohmylms-button { ' . implode( '; ', $button_styles ) . '; }' . "\n";
		}

		// Button Hover Styles.
		$button_hover_styles = array();
		if ( ! empty( $attrs['button_hover_background'] ) ) {
			$button_hover_styles[] = 'background: ' . esc_attr( $attrs['button_hover_background'] ) . ' !important';
		}
		if ( ! empty( $attrs['button_hover_color'] ) ) {
			$button_hover_styles[] = 'color: ' . esc_attr( $attrs['button_hover_color'] ) . ' !important';
		}
		if ( ! empty( $attrs['button_hover_box_shadow'] ) ) {
			$button_hover_styles[] = 'box-shadow: ' . esc_attr( $attrs['button_hover_box_shadow'] ) . ' !important';
		}
		if ( ! empty( $attrs['button_hover_border_color'] ) ) {
			$button_hover_styles[] = 'border-color: ' . esc_attr( $attrs['button_hover_border_color'] ) . ' !important';
		}

		if ( ! empty( $button_hover_styles ) ) {
			$css .= '.ohmylms-page.ohmylms-course-list-shortcode .ohmylms-course-cards .course-card .ohmylms-button:hover, .ohmylms-page.ohmylms-course-list-shortcode .ohmylms-course-loadmore-area .ohmylms-button:hover { ' . implode( '; ', $button_hover_styles ) . '; }' . "\n";
		}

		return $css;
	}

	/**
	 * Build typography styles from attributes
	 *
	 * @since 1.0.0
	 * @param array  $attrs Shortcode attributes.
	 * @param string $prefix Attribute prefix (e.g., 'title_typography_').
	 * @return array Array of CSS style strings
	 */
	private static function build_typography_styles( $attrs, $prefix ) {
		$styles = array();

		if ( ! empty( $attrs[ $prefix . 'font_family' ] ) ) {
			$styles[] = 'font-family: ' . esc_attr( $attrs[ $prefix . 'font_family' ] ) . ' !important';
		}
		if ( ! empty( $attrs[ $prefix . 'font_size' ] ) ) {
			$styles[] = 'font-size: ' . esc_attr( $attrs[ $prefix . 'font_size' ] ) . ' !important';
		}
		if ( ! empty( $attrs[ $prefix . 'font_weight' ] ) ) {
			$styles[] = 'font-weight: ' . esc_attr( $attrs[ $prefix . 'font_weight' ] ) . ' !important';
		}
		if ( ! empty( $attrs[ $prefix . 'text_transform' ] ) ) {
			$styles[] = 'text-transform: ' . esc_attr( $attrs[ $prefix . 'text_transform' ] ) . ' !important';
		}
		if ( ! empty( $attrs[ $prefix . 'font_style' ] ) ) {
			$styles[] = 'font-style: ' . esc_attr( $attrs[ $prefix . 'font_style' ] ) . ' !important';
		}
		if ( ! empty( $attrs[ $prefix . 'text_decoration' ] ) ) {
			$styles[] = 'text-decoration: ' . esc_attr( $attrs[ $prefix . 'text_decoration' ] ) . ' !important';
		}
		if ( ! empty( $attrs[ $prefix . 'line_height' ] ) ) {
			$styles[] = 'line-height: ' . esc_attr( $attrs[ $prefix . 'line_height' ] ) . ' !important';
		}
		if ( ! empty( $attrs[ $prefix . 'letter_spacing' ] ) ) {
			$styles[] = 'letter-spacing: ' . esc_attr( $attrs[ $prefix . 'letter_spacing' ] ) . ' !important';
		}
		if ( ! empty( $attrs[ $prefix . 'word_spacing' ] ) ) {
			$styles[] = 'word-spacing: ' . esc_attr( $attrs[ $prefix . 'word_spacing' ] ) . ' !important';
		}

		return $styles;
	}

	/**
	 * Locate the course archive template
	 *
	 * @since 1.0.0
	 * @return string|false
	 */
	private static function locate_course_template() {
		$template_paths = array(
			get_stylesheet_directory() . '/archive-course.php',
			get_template_directory() . '/archive-course.php',
			plugin_dir_path( __FILE__ ) . '../../templates/archive-course.php',
		);

		foreach ( $template_paths as $path ) {
			if ( file_exists( $path ) ) {
				return $path;
			}
		}

		return false;
	}

	/**
	 * Block render callback for Gutenberg block
	 *
	 * @param mixed $atts Atts.
	 */
	public static function block_render( $atts ) {
		// Map camelCase block attributes to snake_case shortcode attributes.
		$map = array(
			'postsPerPage'                        => 'posts_per_page',
			'orderby'                             => 'orderby',
			'order'                               => 'order',
			'curriculum'                          => 'curriculum',
			'bundle'                              => 'bundle',
			'layout'                              => 'layout',
			'layoutStyle'                         => 'layout_style',
			'showFilter'                          => 'show_filter',
			'showSearch'                          => 'show_search',
			'showSort'                            => 'show_sort',
			'columns'                             => 'columns',
			'isEnableCategory'                    => 'is_enable_category',
			'courseRows'                          => 'course_rows',
			'wrapperBackground'                   => 'wrapper_background',
			'wrapperMargin'                       => 'wrapper_margin',
			'wrapperPadding'                      => 'wrapper_padding',
			'cardBackground'                      => 'card_background',
			'cardBorderRadius'                    => 'card_border_radius',
			'cardBorderWidth'                     => 'card_border_width',
			'cardBorderType'                      => 'card_border_type',
			'cardBorderColor'                     => 'card_border_color',
			'cardPadding'                         => 'card_padding',
			'cardBoxShadow'                       => 'card_box_shadow',
			'cardHoverBoxShadow'                  => 'card_hover_box_shadow',
			'cardHoverBorderColor'                => 'card_hover_border_color',
			'cardHoverBackground'                 => 'card_hover_background',
			'cardHeaderImageBorderRadius'         => 'card_header_image_border_radius',
			'cardHeaderMargin'                    => 'card_header_margin',
			'cardHeaderPadding'                   => 'card_header_padding',
			'cardContentBackground'               => 'card_content_background',
			'titleTypographyFontFamily'           => 'title_typography_font_family',
			'titleTypographyFontSize'             => 'title_typography_font_size',
			'titleTypographyFontWeight'           => 'title_typography_font_weight',
			'titleTypographyTextTransform'        => 'title_typography_text_transform',
			'titleTypographyFontStyle'            => 'title_typography_font_style',
			'titleTypographyTextDecoration'       => 'title_typography_text_decoration',
			'titleTypographyLineHeight'           => 'title_typography_line_height',
			'titleTypographyLetterSpacing'        => 'title_typography_letter_spacing',
			'titleTypographyWordSpacing'          => 'title_typography_word_spacing',
			'titleColor'                          => 'title_color',
			'titleMargin'                         => 'title_margin',
			'descriptionTypographyFontFamily'     => 'description_typography_font_family',
			'descriptionTypographyFontSize'       => 'description_typography_font_size',
			'descriptionTypographyFontWeight'     => 'description_typography_font_weight',
			'descriptionTypographyTextTransform'  => 'description_typography_text_transform',
			'descriptionTypographyFontStyle'      => 'description_typography_font_style',
			'descriptionTypographyTextDecoration' => 'description_typography_text_decoration',
			'descriptionTypographyLineHeight'     => 'description_typography_line_height',
			'descriptionTypographyLetterSpacing'  => 'description_typography_letter_spacing',
			'descriptionTypographyWordSpacing'    => 'description_typography_word_spacing',
			'descriptionColor'                    => 'description_color',
			'descriptionMargin'                   => 'description_margin',
			'courseMetaBackground'                => 'course_meta_background',
			'courseMetaPadding'                   => 'course_meta_padding',
			'courseMetaMargin'                    => 'course_meta_margin',
			'courseMetaBorderRadius'              => 'course_meta_border_radius',
			'courseMetaBorder'                    => 'course_meta_border',
			'courseMetaRowGap'                    => 'course_meta_row_gap',
			'courseMetaColumnGap'                 => 'course_meta_column_gap',
			'courseMetaTypographyFontFamily'      => 'course_meta_typography_font_family',
			'courseMetaTypographyFontSize'        => 'course_meta_typography_font_size',
			'courseMetaTypographyFontWeight'      => 'course_meta_typography_font_weight',
			'courseMetaTypographyTextTransform'   => 'course_meta_typography_text_transform',
			'courseMetaTypographyFontStyle'       => 'course_meta_typography_font_style',
			'courseMetaTypographyTextDecoration'  => 'course_meta_typography_text_decoration',
			'courseMetaTypographyLineHeight'      => 'course_meta_typography_line_height',
			'courseMetaTypographyLetterSpacing'   => 'course_meta_typography_letter_spacing',
			'courseMetaTypographyWordSpacing'     => 'course_meta_typography_word_spacing',
			'courseMetaColor'                     => 'course_meta_color',
			'courseMetaIconSize'                  => 'course_meta_icon_size',
			'cohortMetaBackground'                => 'cohort_meta_background',
			'cohortMetaPadding'                   => 'cohort_meta_padding',
			'cohortMetaMargin'                    => 'cohort_meta_margin',
			'cohortMetaBorderRadius'              => 'cohort_meta_border_radius',
			'cohortMetaBorder'                    => 'cohort_meta_border',
			'cohortMetaRowGap'                    => 'cohort_meta_row_gap',
			'cohortMetaTypographyFontFamily'      => 'cohort_meta_typography_font_family',
			'cohortMetaTypographyFontSize'        => 'cohort_meta_typography_font_size',
			'cohortMetaTypographyFontWeight'      => 'cohort_meta_typography_font_weight',
			'cohortMetaTypographyTextTransform'   => 'cohort_meta_typography_text_transform',
			'cohortMetaTypographyFontStyle'       => 'cohort_meta_typography_font_style',
			'cohortMetaTypographyTextDecoration'  => 'cohort_meta_typography_text_decoration',
			'cohortMetaTypographyLineHeight'      => 'cohort_meta_typography_line_height',
			'cohortMetaTypographyLetterSpacing'   => 'cohort_meta_typography_letter_spacing',
			'cohortMetaTypographyWordSpacing'     => 'cohort_meta_typography_word_spacing',
			'cohortMetaColor'                     => 'cohort_meta_color',
			'cohortMetaIconSize'                  => 'cohort_meta_icon_size',
			'cohortMetaIconSpacing'               => 'cohort_meta_icon_spacing',
			'priceBackground'                     => 'price_background',
			'pricePadding'                        => 'price_padding',
			'priceMargin'                         => 'price_margin',
			'priceBorderRadius'                   => 'price_border_radius',
			'priceBorder'                         => 'price_border',
			'priceTypographyFontFamily'           => 'price_typography_font_family',
			'priceTypographyFontSize'             => 'price_typography_font_size',
			'priceTypographyFontWeight'           => 'price_typography_font_weight',
			'priceTypographyTextTransform'        => 'price_typography_text_transform',
			'priceTypographyFontStyle'            => 'price_typography_font_style',
			'priceTypographyTextDecoration'       => 'price_typography_text_decoration',
			'priceTypographyLineHeight'           => 'price_typography_line_height',
			'priceTypographyLetterSpacing'        => 'price_typography_letter_spacing',
			'priceTypographyWordSpacing'          => 'price_typography_word_spacing',
			'priceColor'                          => 'price_color',
			'priceRegularColor'                   => 'price_regular_color',
			'buttonBackground'                    => 'button_background',
			'buttonPadding'                       => 'button_padding',
			'buttonMargin'                        => 'button_margin',
			'buttonBorderRadius'                  => 'button_border_radius',
			'buttonBorder'                        => 'button_border',
			'buttonTypographyFontFamily'          => 'button_typography_font_family',
			'buttonTypographyFontSize'            => 'button_typography_font_size',
			'buttonTypographyFontWeight'          => 'button_typography_font_weight',
			'buttonTypographyTextTransform'       => 'button_typography_text_transform',
			'buttonTypographyFontStyle'           => 'button_typography_font_style',
			'buttonTypographyTextDecoration'      => 'button_typography_text_decoration',
			'buttonTypographyLineHeight'          => 'button_typography_line_height',
			'buttonTypographyLetterSpacing'       => 'button_typography_letter_spacing',
			'buttonTypographyWordSpacing'         => 'button_typography_word_spacing',
			'buttonColor'                         => 'button_color',
			'buttonBoxShadow'                     => 'button_box_shadow',
			'buttonHoverBackground'               => 'button_hover_background',
			'buttonHoverColor'                    => 'button_hover_color',
			'buttonHoverBoxShadow'                => 'button_hover_box_shadow',
			'buttonHoverBorderColor'              => 'button_hover_border_color',
			'containerClass'                      => 'container_class',
			'courseCardClass'                     => 'course_card_class',
			'gridGap'                             => 'grid_gap',
			'cardBgColor'                         => 'card_bg_color',
			'cardShadow'                          => 'card_shadow',
			'titleFontSize'                       => 'title_font_size',
			'priceFontSize'                       => 'price_font_size',
			'buttonBgColor'                       => 'button_bg_color',
			'buttonTextColor'                     => 'button_text_color',
			'containerPadding'                    => 'container_padding',
			'containerMargin'                     => 'container_margin',
			'cardMargin'                          => 'card_margin',
			'align'                               => 'align',
		);

		$shortcode_atts = array();
		foreach ( $map as $js_key => $php_key ) {
			if ( isset( $atts[ $js_key ] ) ) {
				$shortcode_atts[ $php_key ] = $atts[ $js_key ];
			}
		}

		ob_start();
		self::output( $shortcode_atts );
		return ob_get_clean();
	}
}
