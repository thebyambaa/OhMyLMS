<?php
/**
 * Template for displaying carousel courses.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

defined( 'ABSPATH' ) || exit();
if ( isset( $atts ) && is_array( $atts ) && isset( $atts['course_rows'] ) ) {
	$data = ! empty( $atts['course_rows'] ) ? $atts['course_rows'] : get_option( 'ohmylms_archive_page_row', array() );
} else {
	// Fallback to default option if shortcode attributes are not set.
	$data = get_option( 'ohmylms_archive_page_row', array() );
}

if ( empty( $data ) ) {
	return;
}
global $category;

?>

<div class="ohmylms-course-carousel-wrapper">
	<?php
	foreach ( $data as $row ) {

		// Validate required row fields.
		if ( empty( $row['row_heading'] ) || empty( $row['row_display_criteria'] ) ) {
			continue;
		}

		$function = "get_{$row['row_display_criteria']}_course_ids";

		// Skip if the function does not exist and criteria is not 'all'.
		if ( ! function_exists( $function ) && 'all' !== $row['row_display_criteria'] ) {
			continue;
		}

		echo '<div class="ohmylms-course-carousel-single">';
			echo '<h3 class="course-carousel-title">' . esc_html( $row['row_heading'] ) . '</h3>';

		if ( 'all' === $row['row_display_criteria'] ) {
			$args = array(
				'post_type'      => OHMYLMS_COURSE_CPT,
				'post_status'    => 'publish',
				'posts_per_page' => -1,  // Adjust as needed.
			);

			// The selected curriculum item (with everything below it) or Course Bundle.
			$group = ohmylms_course_ids_for_group( $category );
			if ( null !== $group ) {
				$args['post__in'] = $group ? $group : array( 0 );
			}
			$query = new \WP_Query( $args );

			if ( $query->have_posts() ) {
				ohmylms_course_loop_start( true, isset( $atts ) && is_array( $atts ) ? $atts : null );
				while ( $query->have_posts() ) {
					$query->the_post();
					ohmylms_get_template_part( 'content', 'course', isset( $atts ) && is_array( $atts ) ? $atts : null );
				}
				ohmylms_course_loop_end();
				wp_reset_postdata(); // Reset after custom query.
			}
		} else {

			$course_ids = $function( $category );

			if ( ! empty( $course_ids ) ) {
				// Create a WP_Query with the course IDs.
				$query = new \WP_Query(
					array(
						'post_type'      => 'ohmylms-course',
						'post__in'       => $course_ids,
						'orderby'        => 'post__in', // Maintain the order of IDs.
						'posts_per_page' => -1,  // Adjust as needed.
						'post_status'    => 'publish',
					)
				);

				if ( $query->have_posts() ) {
					ohmylms_course_loop_start( true, isset( $atts ) && is_array( $atts ) ? $atts : null );
					while ( $query->have_posts() ) {
						$query->the_post();
						ohmylms_get_template_part( 'content', 'course', isset( $atts ) && is_array( $atts ) ? $atts : null );
					}
					ohmylms_course_loop_end();
					wp_reset_postdata(); // Reset after custom query.
				}
			}
		}

		echo '</div>';
	}
	?>
</div>
