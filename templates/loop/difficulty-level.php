<?php
/**
 * OhMyLMS Loop Difficulty Level
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/loop/difficulty-level.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $course;
?>
<span class="difficulty beginner"><?php echo esc_html__( 'Beginner', 'ohmylms' ); ?></span>
