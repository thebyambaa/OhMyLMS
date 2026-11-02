<?php
/**
 * Template for displaying login form.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/checkout/form-login.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 * @global \OhMyLMS\Commerce\Checkout $checkout
 */

defined( 'ABSPATH' ) || exit;

if ( is_user_logged_in() || ! $checkout->is_login_enabled() ) {
	return;
}

?>

<?php

ohmylms_login_form(
	array(
		'message'     => '',
		'redirect_to' => ohmylms_get_checkout_url(),
		'hidden'      => true,
	)
);
