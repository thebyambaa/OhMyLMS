<?php
/**
 * Template for displaying course content within loop.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/content-course.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

defined( 'ABSPATH' ) || exit();
global $course;

if ( ! $course ) {
	return;
}

if ( isset( $atts ) && is_array( $atts ) && isset( $atts['layout'] ) && isset( $atts['layout_style'] ) ) {
	$layout       = isset( $atts['layout'] ) ? $atts['layout'] : get_option( 'ohmylms_archive_page_layout', 'grid' );
	$layout_style = isset( $atts['layout_style'] ) ? $atts['layout_style'] : get_option( 'ohmylms_archive_page_layout_style', 'grid-style1' );
} else {
	$layout       = get_option( 'ohmylms_archive_page_layout', 'grid' );
	$layout_style = get_option( 'ohmylms_archive_page_layout_style', 'grid-style1' );
}

// Get card class from shortcode attributes if available.
$card_class = isset( $card_class ) ? $card_class : '';

?>
<div id="course-<?php echo esc_attr( the_ID() ); ?>" class="course-card <?php echo esc_attr( $card_class ); ?>">
	<?php
	/**
	 * Hook: ohmylms_before_courses_loop_item.
	 */
	do_action( 'ohmylms_before_courses_loop_item', $layout, $layout_style );

	/**
	 * Hook: ohmylms_before_courses_loop_item_content.
	 *
	 * @hooked: ohmylms_template_loop_course_thumbnail - 5.
	 */
	do_action( 'ohmylms_before_courses_loop_item_content' );
	?>

	<div class="course-info <?php echo esc_attr( 'cohort-based' === $course->get_type() ? 'is-cohort-based' : '' ); ?>">
		<?php
		if ( 'grid' === $layout ) {
			$file_name = 'layouts/' . $layout_style . '.php';
			ohmylms_get_template( $file_name, isset( $atts ) ? array( 'atts' => $atts ) : null );
		} else {
			ohmylms_get_template( 'layouts/grid-style1', isset( $atts ) ? array( 'atts' => $atts ) : null );
		}
		?>
	</div>

	<?php
	/**
	 * Hook: ohmylms_after_courses_loop_item_content.
	 */
	do_action( 'ohmylms_after_courses_loop_item_content' );


	/**
	 * Hook: ohmylms_after_courses_loop_item.
	 *
	 * @hooked  ohmylms_loop_add_to_cart - 10
	 */
	do_action( 'ohmylms_after_courses_loop_item', $layout, $layout_style );
	?>
</div>

