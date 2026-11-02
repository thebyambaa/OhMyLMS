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

$ohmylms_capacity_button = \OhMyLMS\Design\Ui::button(
	__( 'No Seat Available', 'ohmylms' ),
	array(
		'variant'  => 'primary',
		'href'     => '#',
		'class'    => 'ohmylms-button ohmylms-button-disabled',
		'disabled' => true,
	)
);
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Ui::button() escapes its label and attributes.
echo $ohmylms_capacity_button;
