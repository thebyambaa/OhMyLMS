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

echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Link markup escaped in the sprintf() below.
	'ohmylms_loop_add_to_cart_link', // WPCS: XSS ok.
	sprintf(
		'<a style="margin-top:8px" href="%s" data-quantity="%s" class="%s" %s>%s</a>',
		esc_url( $course->add_to_cart_url() ),
		esc_attr( isset( $args['quantity'] ) ? $args['quantity'] : 1 ),
		esc_attr( isset( $args['class'] ) ? $args['class'] : 'button' ),
		isset( $args['attributes'] ) ? ohmylms_implode_html_attributes( $args['attributes'] ) : '',
		wp_kses_post( $course->add_to_cart_using_point_text() )
	),
	$course,
	$args
);
