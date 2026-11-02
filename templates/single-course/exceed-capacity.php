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

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ui::button() escapes its label and attributes.
echo \OhMyLMS\Design\Ui::button(
	__( 'No Seat Available', 'ohmylms' ),
	array(
		'variant'  => 'primary',
		'href'     => '#',
		'class'    => 'ohmylms-button ohmylms-button-disabled',
		'disabled' => true,
	)
);
