<?php

/**
 * The template for displaying single course sidebar's pricebox
 *
 * This template can be overridden by copying it to yourtheme/single-course/continue-course.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $course;
$url = $course->get_permalink();

// Check if course should show add to cart button.
$should_show_add_to_cart = true;

// If course is cohort-based, check enrollment deadline.
if ( $course && $course->get_type() === 'cohort-based' ) {
	$cohorts               = $course->get_cohort();
	$has_active_enrollment = false;
	$current_time          = current_time( 'mysql' );

	foreach ( $cohorts as $cohort ) {
		if ( ! empty( $cohort['enrollment_deadline'] ) ) {
			$enrollment_end = $cohort['enrollment_deadline'];
			if ( $enrollment_end > $current_time ) {
				$has_active_enrollment = true;
				break;
			}
		}
	}

	$should_show_add_to_cart = $has_active_enrollment;
}

// Only show add to cart button if enrollment is active.
if ( $should_show_add_to_cart ) {
	echo apply_filters(
		'ohmylms_loop_add_to_cart_link', // WPCS: XSS ok.
		sprintf(
			'<a href="%s" data-quantity="%s" class="%s" %s>%s</a>',
			esc_url( $course->add_to_cart_url() ),
			esc_attr( isset( $args['quantity'] ) ? $args['quantity'] : 1 ),
			esc_attr( isset( $args['class'] ) ? $args['class'] : 'button' ),
			isset( $args['attributes'] ) ? ohmylms_implode_html_attributes( $args['attributes'] ) : '',
			wp_kses_post( $course->add_to_cart_text() )
		),
		$course,
		$args
	);
}
?>
<!-- <a href="<?php echo esc_url( $url ); ?>" class="ohmylms-button">
	<?php echo wp_kses_post( $course->add_to_cart_text() ); ?>
</a> -->
