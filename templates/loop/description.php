<?php
/**
 * OhMyLMS Loop Description
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/loop/description.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $course;
$description = $course->get_description();
