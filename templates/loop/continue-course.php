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
$student                 = new \OhMyLMS\Data\Student( get_current_user_id() );
$course_resume_url       = $student->get_course_resume_url( $course->get_id() );
$ohmylms_continue_button = \OhMyLMS\Design\Ui::button(
	__( 'Continue Course', 'ohmylms' ),
	array(
		'variant' => 'primary',
		'href'    => $course_resume_url,
		'class'   => 'ohmylms-button continue-course',
	)
);
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ui::button() escapes its label and attributes.
echo $ohmylms_continue_button;
