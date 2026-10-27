<?php

/**
 * Authorize.Net Payment Gateway for OhMyLMS
 *
 * This file registers the Authorize.Net payment gateway with OhMyLMS.
 *
 * @version 1.0.0
 *
 * @package OhMyLMS
 */

define( 'OHMYLMS_AUTHORIZENET_VERSION', '1.0.0' );
define( 'OHMYLMS_AUTHORIZENET_MAIN_FILE', __FILE__ );
define( 'OHMYLMS_AUTHORIZENET_ABSPATH', __DIR__ . '/' );

/**
 * Register Authorize.Net Gateway
 *
 * This filter adds the Authorize.Net payment gateway to the list of available gateways in OhMyLMS.
 *
 * @param array $gateways The existing list of payment gateways.
 * @return array The updated list of payment gateways including Authorize.Net.
 * @since 1.0.0
 */
add_filter(
	'ohmylms_payment_gateways',
	function ( $gateways ) {
		$gateways[] = GatewayAuthorizenet::class;
		return $gateways;
	}
);

/**
 * Register Authorize.Net Settings
 *
 * This filter initializes the Authorize.Net settings array in OhMyLMS.
 *
 * @param array $settings The existing settings array.
 * @return array The updated settings array including Authorize.Net settings.
 */
add_filter(
	'ohmylms_payment_gateways_settings',
	function ( $settings ) {
		$settings['authorizenet'] = array();
		return $settings;
	}
);
