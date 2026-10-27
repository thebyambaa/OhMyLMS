<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * CourseListElement class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\WPBakery\Elements;

/**
 * WPBakery Course List Element
 *
 * @package OhMyLMS\WPBakery\Elements
 * @since 1.0.0
 */


defined( 'ABSPATH' ) || exit;

/**
 * CourseListElement class
 */
class CourseListElement {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->register_element();

		// Add filter to decode param_group data.
		add_filter( 'shortcode_atts_ohmylms_course_list', array( $this, 'decode_course_rows' ), 10, 3 );

		// Disable WPBakery shortcode caching for real-time preview updates.
		add_filter( 'vc_shortcode_content_filter_after', array( $this, 'disable_cache_for_preview' ), 10, 2 );
	}

	/**
	 * Disable caching for this shortcode in WPBakery editor
	 *
	 * @param string $output Shortcode output.
	 * @param string $shortcode Shortcode tag.
	 * @return string Modified output.
	 */
	public function disable_cache_for_preview( $output, $shortcode ) {
		if ( 'ohmylms_course_list' === $shortcode ) {
			// Add a timestamp to force cache bypass in editor.
			if ( function_exists( 'vc_is_inline' ) && vc_is_inline() ) {
				$output .= '<!-- vc-no-cache:' . time() . ' -->';
			}
		}
		return $output;
	}


	/**
	 * Dropdown values for WPBakery: label => value, with an "All" entry first. Equal labels (the same
	 * item name under different parents) get their value appended so none is lost.
	 *
	 * @param array<string,string> $options Slug => label.
	 *
	 * @return array<string,string>
	 */
	private static function dropdown_values( array $options ) {
		$values = array( __( 'All', 'ohmylms' ) => '' );
		foreach ( $options as $slug => $label ) {
			$key            = isset( $values[ $label ] ) ? $label . ' (' . $slug . ')' : $label;
			$values[ $key ] = $slug;
		}
		return $values;
	}

	/**
	 * Decode course_rows param_group data from WPBakery
	 *
	 * @param array $out The output array of shortcode attributes.
	 * @param array $pairs The supported attributes and their defaults.
	 * @param array $atts The user defined shortcode attributes.
	 * @return array Modified attributes
	 */
	public function decode_course_rows( $out, $pairs, $atts ) {
		// Only sync settings to main options if in WPBakery editor/admin, not on frontend/default listing.
		if ( is_admin() && function_exists( 'vc_is_inline' ) && vc_is_inline() ) {
			self::sync_settings_to_options( $atts );
		}
		// Fallback to global options if not set in shortcode/element.
		if ( empty( $out['layout'] ) ) {
			$out['layout'] = get_option( 'ohmylms_archive_page_layout', 'grid' );
		}
		if ( empty( $out['layout_style'] ) ) {
			$out['layout_style'] = get_option( 'ohmylms_archive_page_layout_style', 'grid-style1' );
		}

		if ( empty( $out['posts_per_page'] ) ) {
			$out['posts_per_page'] = get_option( 'ohmylms_archive_page_per_page', 10 );
		}
		if ( empty( $out['show_filter'] ) ) {
			$out['show_filter'] = get_option( 'ohmylms_archive_page_filter_is_enabled', 'no' );
		}
		// Course rows (for carousel/grid-style3/4).
		if ( isset( $atts['course_rows'] ) && is_string( $atts['course_rows'] ) && ! empty( $atts['course_rows'] ) ) {
			$decoded = json_decode( urldecode( $atts['course_rows'] ), true );
			if ( is_array( $decoded ) ) {
				$out['course_rows'] = $decoded;
			}
		} elseif ( empty( $out['course_rows'] ) ) {
			$out['course_rows'] = get_option( 'ohmylms_archive_page_row', array() );
		}

		// Filter out show_filter, show_search, show_sort for Layout 3 and 4.
		if ( isset( $out['layout_style'] ) && in_array( $out['layout_style'], array( 'grid-style3', 'grid-style4' ) ) ) {
			$out['show_filter'] = 'no';
			$out['show_search'] = 'no';
			$out['show_sort']   = 'no';
		}

		return $out;
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

		// Build layout style options based on pro license.
		$is_pro_active = true;

		$layout_style_options = array(
			__( 'Layout 1', 'ohmylms' ) => 'grid-style1',
		);

		// Add Layout 3 & 4 only if pro is active.
		if ( $is_pro_active ) {
			$layout_style_options[ __( 'Layout 2 (Pro)', 'ohmylms' ) ] = 'grid-style2';
			$layout_style_options[ __( 'Layout 3 (Pro)', 'ohmylms' ) ] = 'grid-style3';
			$layout_style_options[ __( 'Layout 4 (Pro)', 'ohmylms' ) ] = 'grid-style4';
		}

		// Build params array.
		$params = array(
			// Layout.
			array(
				'type'        => 'dropdown',
				'heading'     => __( 'Layout', 'ohmylms' ),
				'param_name'  => 'layout',
				'value'       => array(
					__( 'Grid', 'ohmylms' ) => 'grid',
					__( 'List', 'ohmylms' ) => 'list',
				),
				'std'         => get_option( 'ohmylms_archive_page_layout', 'grid' ),
				'description' => __( 'Choose layout type', 'ohmylms' ),
				'admin_label' => true,
			),
			// Layout Style.
			array(
				'type'             => 'dropdown',
				'heading'          => __( 'Layout Style', 'ohmylms' ),
				'param_name'       => 'layout_style',
				'value'            => $layout_style_options,
				'std'              => get_option( 'ohmylms_archive_page_layout_style', 'grid-style1' ),
				'description'      => __( 'Choose layout style', 'ohmylms' ),
				'dependency'       => array(
					'element' => 'layout',
					'value'   => array( 'grid' ),
				),
				'edit_field_class' => 'vc_col-sm-6 vc_column',
			),

			// Columns.
				array(
					'type'        => 'dropdown',
					'heading'     => __( 'Columns Per Row', 'ohmylms' ),
					'param_name'  => 'columns',
					'value'       => array(
						__( '1 Column', 'ohmylms' )  => '1',
						__( '2 Columns', 'ohmylms' ) => '2',
						__( '3 Columns', 'ohmylms' ) => '3',
						__( '4 Columns', 'ohmylms' ) => '4',
					),
					'std'         => get_option( 'ohmylms_archive_page_columns', '3' ),
					'description' => __( 'Number of columns per row', 'ohmylms' ),
					'dependency'  => array(
						'element' => 'layout',
						'value'   => array( 'grid' ),
					),
				),

			// Feature Toggles.
				array(
					'type'        => 'checkbox',
					'heading'     => __( 'Show Filter', 'ohmylms' ),
					'param_name'  => 'show_filter',
					'value'       => array( __( 'Enable', 'ohmylms' ) => 'yes' ),
					'std'         => get_option( 'ohmylms_archive_page_filter_is_enabled', 'no' ),
					'description' => __( 'Show course filter (Layout 1/2, Grid only)', 'ohmylms' ),
					'dependency'  => array(
						'element' => 'layout_style',
						'value'   => array( 'grid-style1', 'grid-style2' ),
					),
				),
			array(
				'type'        => 'checkbox',
				'heading'     => __( 'Show Search', 'ohmylms' ),
				'param_name'  => 'show_search',
				'value'       => array( __( 'Enable', 'ohmylms' ) => 'yes' ),
				'std'         => get_option( 'ohmylms_archive_page_search_is_enabled', 'no' ),
				'description' => __( 'Show course search (Layout 1/2, Grid only)', 'ohmylms' ),
				'dependency'  => array(
					'element' => 'layout_style',
					'value'   => array( 'grid-style1', 'grid-style2' ),
				),
			),
			array(
				'type'        => 'checkbox',
				'heading'     => __( 'Show Sort', 'ohmylms' ),
				'param_name'  => 'show_sort',
				'value'       => array( __( 'Enable', 'ohmylms' ) => 'yes' ),
				'std'         => get_option( 'ohmylms_archive_page_sorting_is_enabled', 'no' ),
				'description' => __( 'Show course sorting (Layout 1/2, Grid only)', 'ohmylms' ),
				'dependency'  => array(
					'element' => 'layout_style',
					'value'   => array( 'grid-style1', 'grid-style2' ),
				),
			),
			array(
				'type'        => 'checkbox',
				'heading'     => __( 'Show Curriculum Tabs', 'ohmylms' ),
				'param_name'  => 'is_enable_category',
				'value'       => array( __( 'Enable', 'ohmylms' ) => 'yes' ),
				'std'         => get_option( 'ohmylms_archive_page_category_is_enabled', 'no' ),
				'description' => __( 'Show curriculum tabs (Layout 3/4, Grid only, Pro)', 'ohmylms' ),
				'dependency'  => array(
					'element' => 'layout_style',
					'value'   => array( 'grid-style3', 'grid-style4' ),
				),
			),
			array(
				'type'        => 'dropdown',
				'heading'     => __( 'Curriculum', 'ohmylms' ),
				'param_name'  => 'curriculum',
				'value'       => self::dropdown_values( \OhMyLMS\Curriculum\Placement::item_options() ),
				'description' => __( 'Only show courses placed under this curriculum item and everything below it.', 'ohmylms' ),
			),
			array(
				'type'        => 'dropdown',
				'heading'     => __( 'Course bundle', 'ohmylms' ),
				'param_name'  => 'bundle',
				'value'       => self::dropdown_values( \OhMyLMS\Curriculum\Placement::bundle_options() ),
				'description' => __( 'Only show courses in this Course Bundle.', 'ohmylms' ),
			),
			// Row Settings (Pro).

						array(
							'type'        => 'param_group',
							'heading'     => __( 'Course Rows', 'ohmylms' ),
							'param_name'  => 'course_rows',
							'std'         => get_option( 'ohmylms_archive_page_course_rows', '' ),
							'description' => __( 'Add custom rows for Layout 3/4 (Pro)', 'ohmylms' ),
							'dependency'  => array(
								'element' => 'layout_style',
								'value'   => array( 'grid-style3', 'grid-style4' ),
							),
							'params'      => array(
								array(
									'type'       => 'dropdown',
									'heading'    => __( 'Display Criteria', 'ohmylms' ),
									'param_name' => 'row_display_criteria',
									'value'      => array(
										__( 'All Courses', 'ohmylms' )          => 'all',
										__( 'Recent Courses', 'ohmylms' )       => 'recent',
										__( 'Top Rated Courses', 'ohmylms' )    => 'top_rated',
										__( 'Free Courses', 'ohmylms' )         => 'free',
										__( 'Paid Courses', 'ohmylms' )         => 'paid',
										__( 'Best Selling Courses', 'ohmylms' ) => 'best_selling',
									),
									'std'        => 'all',
								),
								array(
									'type'       => 'textfield',
									'heading'    => __( 'Row Heading', 'ohmylms' ),
									'param_name' => 'row_heading',
									'value'      => __( 'All Courses', 'ohmylms' ),
								),
							),
						),
			// --- Style Controls ---
			array(
				'type'       => 'colorpicker',
				'heading'    => __( 'Wrapper Background', 'ohmylms' ),
				'param_name' => 'wrapper_background',
				'group'      => __( 'Style', 'ohmylms' ),
			),
			array(
				'type'        => 'textfield',
				'heading'     => __( 'Wrapper Padding', 'ohmylms' ),
				'param_name'  => 'wrapper_padding',
				'group'       => __( 'Style', 'ohmylms' ),
				'description' => __( 'CSS padding value (e.g., 20px 30px)', 'ohmylms' ),
			),
			array(
				'type'        => 'textfield',
				'heading'     => __( 'Wrapper Margin', 'ohmylms' ),
				'param_name'  => 'wrapper_margin',
				'group'       => __( 'Style', 'ohmylms' ),
				'description' => __( 'CSS margin value (e.g., 0 0 30px 0)', 'ohmylms' ),
			),
			array(
				'type'       => 'colorpicker',
				'heading'    => __( 'Card Background', 'ohmylms' ),
				'param_name' => 'card_background',
				'group'      => __( 'Style', 'ohmylms' ),
			),
			array(
				'type'       => 'colorpicker',
				'heading'    => __( 'Title Color', 'ohmylms' ),
				'param_name' => 'title_color',
				'group'      => __( 'Style', 'ohmylms' ),
			),
			array(
				'type'       => 'colorpicker',
				'heading'    => __( 'Price Color', 'ohmylms' ),
				'param_name' => 'price_color',
				'group'      => __( 'Style', 'ohmylms' ),
			),
			array(
				'type'       => 'colorpicker',
				'heading'    => __( 'Button Background', 'ohmylms' ),
				'param_name' => 'button_background',
				'group'      => __( 'Style', 'ohmylms' ),
			),
			array(
				'type'       => 'colorpicker',
				'heading'    => __( 'Button Text Color', 'ohmylms' ),
				'param_name' => 'button_color',
				'group'      => __( 'Style', 'ohmylms' ),
			),
			// Advanced.
			array(
				'type'        => 'textfield',
				'heading'     => __( 'Extra CSS Class', 'ohmylms' ),
				'param_name'  => 'class',
				'group'       => __( 'Advanced', 'ohmylms' ),
				'description' => __( 'Add custom CSS class for styling', 'ohmylms' ),
			),
		);

		// Add Pro-only fields if Pro is active.
		if ( $is_pro_active ) {
			// Add Carousel Layout Notice after layout_style.
			$layout_style_index = 0;
			foreach ( $params as $index => $param ) {
				if ( isset( $param['param_name'] ) && 'layout_style' === $param['param_name'] ) {
					$layout_style_index = $index + 1;
					break;
				}
			}

			if ( $layout_style_index > 0 ) {
				array_splice(
					$params,
					$layout_style_index,
					0,
					array(
						array(
							'type'       => 'custom_markup',
							'param_name' => 'carousel_notice_dummy',
							'value'      => __( '<div style="padding: 12px; background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; font-size: 13px; line-height: 1.6; margin-top: 10px;"><strong>Carousel Layout Selected</strong><br>For the best view of Layout 3 or Layout 4 carousel, please <strong>save your changes and reload the page</strong>.</div>', 'ohmylms' ),
							'dependency' => array(
								'element' => 'layout_style',
								'value'   => array( 'grid-style3', 'grid-style4' ),
							),
						),
					)
				);
			}

			// Add "Courses Per Page" field after "columns".
			$columns_index = 0;
			foreach ( $params as $index => $param ) {
				if ( isset( $param['param_name'] ) && 'columns' === $param['param_name'] ) {
					$columns_index = $index + 1;
					break;
				}
			}

			if ( $columns_index > 0 ) {
				array_splice(
					$params,
					$columns_index,
					0,
					array(
						array(
							'type'        => 'textfield',
							'heading'     => __( 'Courses Per Page', 'ohmylms' ),
							'param_name'  => 'posts_per_page',
							'std'         => get_option( 'ohmylms_archive_page_per_page', 10 ),
							'value'       => '10',
							'description' => __( 'Number of courses to display per page', 'ohmylms' ),
						),
					)
				);
			}
		}

		// Add pro notices if pro is not active.
		if ( ! $is_pro_active ) {
			// Layout 3/4 Pro Notice.
			array_splice(
				$params,
				2,
				0,
				array(
					array(
						'type'       => 'custom_markup',
						'param_name' => 'pro_notice_dummy',
						'value'      => __( '<div style="padding: 12px; background: #e3f2fd; border-left: 4px solid #2196f3; color: #1565c0; font-size: 13px; line-height: 1.6; margin-top: 10px;"><strong>Pro Feature</strong><br>Layout 3 and Layout 4 require OhMyLMS Pro. <a href="https://coderex.co/ohmylms-pro/" target="_blank" style="color: #1565c0; text-decoration: underline;">Upgrade to Pro</a> to unlock carousel layouts and advanced features.</div>', 'ohmylms' ),
						'dependency' => array(
							'element' => 'layout',
							'value'   => array( 'grid' ),
						),
					),
				)
			);

			// Add Courses Per Page Pro Notice after columns field.
			$columns_index = 0;
			foreach ( $params as $index => $param ) {
				if ( isset( $param['param_name'] ) && 'columns' === $param['param_name'] ) {
					$columns_index = $index + 1;
					break;
				}
			}

			if ( $columns_index > 0 ) {
				array_splice(
					$params,
					$columns_index,
					0,
					array(
						array(
							'type'       => 'custom_markup',
							'param_name' => 'posts_per_page_pro_notice',
							'value'      => __( '<div style="padding: 12px; background: #e3f2fd; border-left: 4px solid #2196f3; color: #1565c0; font-size: 13px; line-height: 1.6; margin-top: 10px;"><strong>Pro Feature</strong><br>"Courses Per Page" customization requires OhMyLMS Pro. <a href="https://coderex.co/ohmylms-pro/" target="_blank" style="color: #1565c0; text-decoration: underline;">Upgrade to Pro</a> to control the number of courses displayed per page.</div>', 'ohmylms' ),
						),
					)
				);
			}
		}

		vc_map(
			array(
				'name'             => __( 'Course List', 'ohmylms' ),
				'base'             => 'ohmylms_course_list',
				'icon'             => 'icon-wpb-ohmylms',
				'category'         => __( 'OhMyLMS', 'ohmylms' ),
				'description'      => __( 'Display a list of courses', 'ohmylms' ),
				'front_enqueue_js' => true,
				'js_view'          => 'VcColumnView',
				'params'           => $params,
			)
		);
	}

		/**
		 * Sync WPBakery element settings to main OhMyLMS options after save
		 *
		 * @param array $atts The user defined shortcode attributes.
		 */
	public static function sync_settings_to_options( $atts ) {
		if ( isset( $atts['layout'] ) ) {
			update_option( 'ohmylms_archive_page_layout', $atts['layout'] );
		}
		if ( isset( $atts['layout_style'] ) ) {
			update_option( 'ohmylms_archive_page_layout_style', $atts['layout_style'] );
		}
		// Only allow changing posts_per_page if pro is active.
		if ( isset( $atts['posts_per_page'] ) && true ) {
			update_option( 'ohmylms_archive_page_per_page', $atts['posts_per_page'] );
		}
		if ( isset( $atts['show_filter'] ) ) {
			update_option( 'ohmylms_archive_page_filter_is_enabled', $atts['show_filter'] );
		}
		if ( isset( $atts['show_search'] ) ) {
			update_option( 'ohmylms_archive_page_search_is_enabled', $atts['show_search'] );
		}
		if ( isset( $atts['show_sort'] ) ) {
			update_option( 'ohmylms_archive_page_sorting_is_enabled', $atts['show_sort'] );
		}
		if ( isset( $atts['is_enable_category'] ) ) {
			update_option( 'ohmylms_archive_page_category_is_enabled', $atts['is_enable_category'] );
		}
		if ( isset( $atts['columns'] ) ) {
			update_option( 'ohmylms_archive_page_columns', $atts['columns'] );
		}
		if ( isset( $atts['course_rows'] ) && is_array( $atts['course_rows'] ) ) {
			update_option( 'ohmylms_archive_page_row', $atts['course_rows'] );
		}
	}
}
