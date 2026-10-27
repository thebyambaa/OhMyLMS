<?php
/**
 * Template for displaying course layout within loop.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/layouts/grid-style4.php.
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
	$layout       = $atts['layout'];
	$layout_style = $atts['layout_style'];
} else {
	$layout       = get_option( 'ohmylms_archive_page_layout', 'grid' );
	$layout_style = get_option( 'ohmylms_archive_page_layout_style', 'grid-style1' );
}
/**
 * Hook: ohmylms_courses_loop_item_title.
 *
 * @hooked ohmylms_loop_course_title - 5
 */
do_action( 'ohmylms_courses_loop_item_title', $layout, $layout_style );


/**
 * Hook: ohmylms_courses_loop_item_description.
 *
 * @hooked ohmylms_loop_course_description - 5
 */
do_action( 'ohmylms_courses_loop_item_description', $layout, $layout_style );


/**
 * Hook: ohmylms_courses_loop_item_certified_tag.
 *
 * @hooked ohmylms_loop_course_certified_tag - 5
 */
do_action( 'ohmylms_courses_loop_item_certified_tag', $layout, $layout_style );


/**
 * Hook: ohmylms_courses_loop_item_meta.
 *
 * @hooked ohmylms_loop_course_meta - 5
 */
do_action( 'ohmylms_courses_loop_item_meta', $layout, $layout_style );
