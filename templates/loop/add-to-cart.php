<?php
/**
 * Loop Add to Cart
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/loop/add-to-cart.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $course;

// Check if course should show add to cart button.
$should_show_add_to_cart = true;

// If course is cohort-based, check enrollment deadline and course expiration.
if ( $course && $course->get_type() === 'cohort-based' ) {
	$cohorts               = $course->get_cohort();
	$has_active_enrollment = false;
	$all_expired           = true;
	$current_time          = current_time( 'timestamp' );
	foreach ( $cohorts as $cohort ) {
		// Check if any cohort has an active enrollment period.
		if ( ! empty( $cohort['enrollment_deadline'] ) ) {
			$enrollment_end = strtotime( $cohort['enrollment_deadline'] );

			if ( $enrollment_end > $current_time ) {
				$has_active_enrollment = true;
			}
		} else {
			// If enrollment deadline is not set, consider it as active enrollment.
			$has_active_enrollment = true;
		}

		// Check if any cohort is not expired.
		if ( ! empty( $cohort['end_date'] ) ) {
			if ( strtotime( $cohort['end_date'] ) >= $current_time ) {
				$all_expired = false;
			}
		} else {
			// If no end date, consider not expired.
			$all_expired = false;
		}
	}

	// Don't show add to cart if all cohorts are expired or no active enrollment.
	$should_show_add_to_cart = $has_active_enrollment && ! $all_expired;
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
