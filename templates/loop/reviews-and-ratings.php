<?php
/**
 * OhMyLMS Loop Duration
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/loop/duration.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $course;
$average_rating = $course->get_average_rating();
$total_review   = $course->get_review_count();
