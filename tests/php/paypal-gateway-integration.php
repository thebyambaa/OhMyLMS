<?php
/**
 * PayPal gateway loading: `OhMyLMS\Gateways\Paypal\GatewayPaypal` autoloads, the gateway registry builds it
 * once, `ohmylms/v1/paypal-webhook` is registered and rejects GET, unsigned and foreign-certificate deliveries,
 * and the PayPal return handler ignores requests for a disabled gateway or a wrong order key.
 *
 * Never contacts PayPal: every outgoing HTTP request fails the suite. Changes no data.
 *
 * Usage (set OHMYLMS_PAYPAL_GATEWAY_TEST=1):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/paypal-gateway-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.WP.AlternativeFunctions, WordPress.Security.NonceVerification

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_PAYPAL_GATEWAY_TEST' ) ) {
	exit( "Set OHMYLMS_PAYPAL_GATEWAY_TEST=1 to run the PayPal gateway tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Gateways\Paypal\GatewayPaypal;

$checks    = 0;
$warnings  = array();
$requests  = array();
$exit_code = 0;

set_error_handler(
	static function ( $severity, $message, $file, $line ) use ( &$warnings ) {
		if ( false !== strpos( wp_normalize_path( $file ), wp_normalize_path( OHMYLMS_DIR ) ) ) {
			$warnings[] = "$message in $file:$line";
		}
		return false;
	}
);
add_filter(
	'pre_http_request',
	static function ( $response, $args, $url ) use ( &$requests ) {
		$requests[] = $url;
		return new WP_Error( 'blocked', 'HTTP is blocked in this suite.' );
	},
	1,
	3
);

/**
 * Record one check.
 *
 * @param bool   $condition Passes.
 * @param string $message   Failure message.
 * @throws RuntimeException When the check fails.
 */
function ok( $condition, $message ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$checks;
}

/**
 * Run a request against the webhook route.
 *
 * @param string $method  Method.
 * @param array  $headers Headers.
 * @return WP_REST_Response
 */
function webhook( $method, array $headers = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/paypal-webhook' );
	$request->set_body( '{"order_id":1,"data":{"id":"TEST"}}' );
	foreach ( $headers as $name => $value ) {
		$request->set_header( $name, $value );
	}
	return rest_do_request( $request );
}

/**
 * A gateway built with the given settings instead of the stored ones.
 *
 * @param array $settings Settings.
 * @return GatewayPaypal
 */
function gateway_with( array $settings ) {
	$filter = static function () use ( $settings ) {
		return $settings;
	};
	add_filter( 'pre_option_ohmylms_paypal_settings', $filter );
	$gateway = new GatewayPaypal();
	remove_filter( 'pre_option_ohmylms_paypal_settings', $filter );
	return $gateway;
}

try {
	ok( class_exists( GatewayPaypal::class ), 'GatewayPaypal autoloads under OhMyLMS\Gateways\Paypal' );
	ok( ! class_exists( 'GatewayPaypal', false ), 'no global GatewayPaypal class is declared' );

	$built = array_filter(
		\OhMyLMS\Commerce\ecommerce()->gateways()->payment_gateways,
		static function ( $gateway ) {
			return $gateway instanceof GatewayPaypal;
		}
	);
	ok( 1 === count( $built ), 'the registry builds the PayPal gateway once, not ' . count( $built ) );
	$gateways = \OhMyLMS\Commerce\ecommerce()->gateways()->get_payment_gateways();
	ok( isset( $gateways['paypal'] ) && $gateways['paypal'] instanceof GatewayPaypal, 'get_payment_gateways() has paypal' );

	$routes = rest_get_server()->get_routes();
	ok( isset( $routes['/ohmylms/v1/paypal-webhook'] ), '/ohmylms/v1/paypal-webhook is registered' );
	ok( 1 === count( $routes['/ohmylms/v1/paypal-webhook'] ), 'the webhook route has one handler' );
	ok( '__return_true' === $routes['/ohmylms/v1/paypal-webhook'][0]['permission_callback'], 'PayPal calls the webhook unauthenticated' );

	$get = webhook( 'GET' );
	ok( 405 === $get->get_status(), 'GET answers 405, not ' . $get->get_status() );

	// The stored gateway has no webhook ID on this site, so deliveries stop there.
	$unconfigured = webhook( 'POST' );
	ok( 401 === $unconfigured->get_status(), 'a delivery to an unconfigured webhook answers 401, not ' . $unconfigured->get_status() );

	// With a webhook ID the signature headers and the certificate host are checked before PayPal is asked.
	$configured = gateway_with(
		array(
			'enabled'    => 'no',
			'webhook_id' => 'WH-TEST',
		)
	);
	$request    = new WP_REST_Request( 'POST', '/ohmylms/v1/paypal-webhook' );
	$request->set_body( '{"order_id":1,"data":{"id":"TEST"}}' );
	$unsigned = $configured->handle_webhook( $request );
	ok( 401 === $unsigned->get_status() && 'Missing PayPal signature headers.' === $unsigned->get_data()['message'], 'an unsigned delivery answers 401' );
	foreach (
		array(
			'paypal-auth-algo'         => 'SHA256withRSA',
			'paypal-cert-url'          => 'https://paypal.com.example.invalid/cert.pem',
			'paypal-transmission-id'   => 'id',
			'paypal-transmission-sig'  => 'sig',
			'paypal-transmission-time' => gmdate( 'c' ),
		) as $name => $value
	) {
		$request->set_header( $name, $value );
	}
	$foreign = $configured->handle_webhook( $request );
	ok( 401 === $foreign->get_status() && 'Unexpected PayPal certificate URL.' === $foreign->get_data()['message'], 'a certificate outside paypal.com answers 401' );

	// The return handler: nothing happens for a disabled gateway or an order whose key does not match.
	$admins = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	);
	wp_set_current_user( (int) $admins[0] );
	$orders   = get_posts(
		array(
			'post_type'   => 'ohmylms-order',
			'post_status' => 'any',
			'numberposts' => 1,
			'fields'      => 'ids',
		)
	);
	$order_id = $orders ? (int) $orders[0] : 0;
	$_GET     = array(
		'token'    => 'PAYPAL-ORDER',
		'order_id' => (string) $order_id,
		'key'      => 'wrong-key',
	);
	$before   = get_post_meta( $order_id, '_paypal_order_id', true );
	gateway_with( array( 'enabled' => 'no' ) )->handle_paypal_return();
	gateway_with(
		array(
			'enabled'           => 'yes',
			'test_mode'         => 'yes',
			'sandbox_client_id' => '',
		)
	)->handle_paypal_return();
	$_GET = array( 'token' => 'PAYPAL-ORDER' );
	gateway_with( array( 'enabled' => 'yes' ) )->handle_paypal_return();
	ok( get_post_meta( $order_id, '_paypal_order_id', true ) === $before, 'the return handler left the order alone' );

	ok( ! $requests, 'no request left the site: ' . implode( ', ', $requests ) );
	ok( ! $warnings, 'no PHP warnings from the plugin: ' . implode( ' | ', $warnings ) );
	fwrite( STDOUT, "OK paypal-gateway-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	$_GET = array();
	wp_set_current_user( 0 );
	restore_error_handler();
}

exit( $exit_code );
