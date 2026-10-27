<?php
/**
 * Paypal Payment Gateway for OhMyLMS
 *
 * This file registers the Paypal payment gateway with OhMyLMS.
 *
 * @version 1.0.0
 *
 * @package OhMyLMS
 */
define( 'OHMYLMS_PAYPAL_VERSION', '1.0.0' );
define( 'OHMYLMS_PAYPAL_MAIN_FILE', __FILE__ );
define( 'OHMYLMS_PAYPAL_ABSPATH', __DIR__ . '/' );

/**
 * Register Paypal Gateway
 *
 * This filter adds the Paypal payment gateway to the list of available gateways in OhMyLMS.
 *
 * @param array $gateways The existing list of payment gateways.
 * @return array The updated list of payment gateways including Paypal.
 * @since 1.0.0
 */
add_filter(
	'ohmylms_payment_gateways',
	function ( $gateways ) {
		$gateways[] = GatewayPaypal::class;
		return $gateways;
	}
);

/**
 * Register Paypal Settings
 *
 * This filter initializes the Paypal settings array in OhMyLMS.
 *
 * @param array $settings The existing settings array.
 * @return array The updated settings array including Paypal.
 * @since 1.0.0
 */
add_filter(
	'ohmylms_payment_gateways_settings',
	function ( $settings ) {
		$settings['paypal'] = array();
		return $settings;
	}
);
