<?php
/**
 * Template for displaying all courses.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/archive-course.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

defined( 'ABSPATH' ) || exit();

// Handle both shortcode attributes and regular archive page.
if ( isset( $atts ) && is_array( $atts ) && ! empty( $atts ) ) {
	$layout            = $atts['layout'];
	$layout_style      = $atts['layout_style'];
	$is_filter_enabled = $atts['show_filter'];
	$posts_per_page    = $atts['posts_per_page'];
} else {
	$layout            = get_option( 'ohmylms_archive_page_layout', 'grid' );
	$layout_style      = get_option( 'ohmylms_archive_page_layout_style', 'grid-style1' );
	$is_filter_enabled = get_option( 'ohmylms_archive_page_filter_is_enabled', 'no' );
	$posts_per_page    = get_option( 'ohmylms_archive_page_per_page', 10 );
}

// Get custom classes for shortcode styling.
$container_class = '';
$card_class      = '';

// Use shortcode attributes if available (when called from shortcode).
if ( isset( $atts ) && is_array( $atts ) ) {
	$container_class = ! empty( $atts['container_class'] ) ? $atts['container_class'] : '';
	$card_class      = ! empty( $atts['course_card_class'] ) ? $atts['course_card_class'] : '';
}

$course_outer_classes = '';

if ( 'grid' === $layout ) {
	if ( 'yes' === $is_filter_enabled ) {
		$course_outer_classes .= ' ohmylms-filter-enabled';
	}

	$course_outer_classes .= ' ' . $layout_style . '';
}
if ( ! isset( $atts ) || ! is_array( $atts ) ) {
	ohmylms_get_header();
}

/**
 * Hook: ohmylms_before_main_content.
 */
do_action( 'ohmylms_before_main_content' );
?>

<div class="ohmylms-container <?php echo esc_attr( $container_class ); ?>">
	<?php
	do_action( 'ohmylms_course_loop_header' );
	if ( have_posts() ) {
		?>
		
		<div class="ohmylms-course-outer <?php echo esc_attr( $course_outer_classes ); ?>" >
			<?php if ( 'grid' === $layout && 'yes' === $is_filter_enabled ) { ?>
				<aside class="ohmylms-course-sidebar" >
					<?php
					/**
					 * Hook: ohmylms_course_filter.
					 *
					 * @hooked: ohmylms_course_filter_header (5).
					 * @hooked: ohmylms_course_filters (10).
					 */
					do_action( 'ohmylms_course_filter', isset( $atts ) && is_array( $atts ) ? $atts : null );
					?>
				</aside>
			<?php } ?>

			<div class="ohmylms-course-main">
				<?php
				/**
				 * Hook: ohmylms_before_course_loop.
				 *
				 * @hooked: ohmylms_course_loop_before_category_filter (5).
				 * @hooked: ohmylms_course_loop_before_filter (10).
				 */
				do_action( 'ohmylms_before_course_loop', isset( $atts ) && is_array( $atts ) ? $atts : null );

				if (
					'grid' === $layout &&
					( 'grid-style3' === $layout_style || 'grid-style4' === $layout_style )
				) {
					// Prepare template variables for carousel.
					$template_vars = isset( $atts ) && is_array( $atts ) ? $atts : array();
					if ( isset( $shortcode_atts ) && is_array( $shortcode_atts ) ) {
						$template_vars['card_class'] = $card_class;
					}
					ohmylms_get_template_part( 'content', 'course-carousel', $template_vars );

				} else {
					// Use the existing global $wp_query which is already set up properly
					// by the shortcode or archive page.
					ohmylms_course_loop_start( true, isset( $atts ) && is_array( $atts ) ? $atts : null );
					while ( have_posts() ) {
						the_post();
						// Prepare template variables for course content.
						$template_vars = isset( $atts ) && is_array( $atts ) ? $atts : array();
						if ( isset( $shortcode_atts ) && is_array( $shortcode_atts ) ) {
							$template_vars['card_class'] = $card_class;
						}
						ohmylms_get_template_part( 'content', 'course', $template_vars );
					}
					ohmylms_course_loop_end( true, isset( $atts ) && is_array( $atts ) ? $atts : null );
				}               /**
				 * Hook: ohmylms_after_course_loop.
				 *
				 * @hooked: ohmylms_course_carousel_item_hover (5).
				 */
				do_action( 'ohmylms_after_course_loop', isset( $atts ) && is_array( $atts ) ? $atts : null );

				?>
			</div>
		</div>

		<?php

		if ( 'list' === $layout || 'grid-style1' == $layout_style || 'grid-style2' == $layout_style ) {
			// pass variable to load-more template.
			ohmylms_get_template(
				'loop/load-more.php',
				array(
					'posts_per_page' => $posts_per_page,
				)
			);
		}
	} else {
		/**
		 * Hook: ohmylms_no_products_found.
		 *
		 * @hooked: ohmylms_no_products_found (5)
		 */
		do_action( 'ohmylms_no_products_found' );
	}
	?>
</div>

<?php

/**
 * Hook: ohmylms_after_main_content.
 */
do_action( 'ohmylms_after_main_content' );

if ( ! isset( $atts ) || ! is_array( $atts ) ) {
	ohmylms_get_footer();
}
