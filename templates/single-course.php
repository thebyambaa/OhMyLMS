<?php
/**
 * Single Course
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-course.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$syllabus_id = \OhMyLMS\Curriculum\SyllabusCourse::owner( get_queried_object_id() );
if ( $syllabus_id && ! post_password_required() ) {
	$directory = \OhMyLMS\Curriculum\Directory::render( $syllabus_id );
	if ( '' !== $directory ) {
		ohmylms_get_header();
		echo $directory;
		ohmylms_get_footer();
		return;
	}
}

ohmylms_get_header();

/**
 * Hook: ohmylms_before_main_content.
 *
 * @hooked: ohmylms_show_toast_notices (5)
 * @hooked: ohmylms_output_content_wrapper_start (10)
 * @hooked: ohmylms_breadcrumb (15)
 */
do_action( 'ohmylms_before_main_content' );

while ( have_posts() ) {
	the_post();
	ohmylms_get_template_part( 'content', 'single-course' );
}

/**
 * Hook: ohmylms_after_main_content.
 */
do_action( 'ohmylms_after_main_content' );

ohmylms_get_footer();
