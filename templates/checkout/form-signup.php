<?php
/**
 * Template for displaying signup form.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/checkout/form-signup.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 * @global \OhMyLMS\Commerce\Checkout $checkout
 */

defined( 'ABSPATH' ) || exit;

ohmylms_get_template( 'global/ohmylms-celebration.php' );

ohmylms_signup_form(
	array(
		'message'     => '',
		'redirect_to' => ohmylms_get_checkout_url(),
		'hidden'      => true,
	)
);
