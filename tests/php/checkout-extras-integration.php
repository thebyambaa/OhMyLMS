<?php
/**
 * Checkout extras (`ohmylms/v1/settings/checkout`) and their enforcement in the real checkout.
 *
 * Checks the settings route (permissions, validation, revision, writes, read-only Stripe status),
 * then runs `Checkout::process_checkout()` with the offline gateway in test mode (no money moves)
 * for guest checkout on and off, an unknown or disabled gateway and the gateway order, plus the
 * checkout templates, the AJAX nonce and the retired legacy subscriptions routes. Options are
 * restored and tagged orders, users and courses removed in `finally`. Mail is captured.
 *
 * Usage (set OHMYLMS_CHECKOUT_TEST=1):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/checkout-extras-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery, WordPress.Security.NonceVerification

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_CHECKOUT_TEST' ) ) {
	exit( "Set OHMYLMS_CHECKOUT_TEST=1 to run the checkout extras tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Settings\CheckoutSettings;

global $wpdb;

$checks   = 0;
$tag      = 'cxtest' . strtolower( wp_generate_password( 6, false, false ) );
$posts    = array();
$users    = array();
$mails    = array();
$exit     = 0;
$options  = array( 'ohmylms_gateway_order', 'ohmylms_allow_purchase_without_login', 'ohmylms_enable_login', 'ohmylms_content_protection', 'ohmylms_integrations', 'ohmylms_offline_settings' );
$snapshot = array();
foreach ( $options as $option ) {
	$snapshot[ $option ] = get_option( $option, null );
}

add_filter(
	'pre_wp_mail',
	static function ( $short, $atts ) use ( &$mails ) {
		$mails[] = $atts;
		return true;
	},
	10,
	2
);

/**
 * Assert.
 *
 * @param mixed  $value   Truthy value.
 * @param string $message Failure message.
 * @throws RuntimeException On failure.
 */
function ok( $value, $message ) {
	global $checks;
	if ( ! $value ) {
		throw new RuntimeException( 'FAILED: ' . $message );
	}
	++$checks;
}

/**
 * Settings call.
 *
 * @param string $method Method.
 * @param mixed  $body   Body.
 * @return WP_REST_Response
 */
function cx_api( $method, $body = null ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/settings/checkout' );
	if ( null !== $body ) {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );
	}
	return rest_do_request( $request );
}

/**
 * Assert an error response.
 *
 * @param WP_REST_Response $response Response.
 * @param int              $status   Status.
 * @param string           $code     Code.
 * @param string           $label    Label.
 */
function cx_error( $response, $status, $code, $label ) {
	$data = $response->get_data();
	ok( $status === $response->get_status() && $code === ( $data['code'] ?? '' ), "$label: expected $status $code, got " . $response->get_status() . ' ' . wp_json_encode( $data ) );
}

/**
 * Thrown instead of exiting when the checkout answers (an Error, so the checkout's own
 * `catch ( \Exception )` does not swallow it, just as it cannot swallow `exit`).
 */
final class CheckoutAnswered extends Error {}

/**
 * Run the AJAX checkout and return its JSON answer.
 *
 * @param array    $post     Posted fields.
 * @param callable $callback What to run (defaults to the checkout).
 * @return array
 */
function cx_checkout( array $post, $callback = null ) {
	$_POST    = $post;
	$_REQUEST = $post;
	add_filter( 'wp_doing_ajax', '__return_true' );
	$die = static function () {
		return static function () {
			throw new CheckoutAnswered();
		};
	};
	add_filter( 'wp_die_ajax_handler', $die );
	ob_start();
	try {
		call_user_func( $callback ? $callback : array( \OhMyLMS\Commerce\Checkout::instance(), 'process_checkout' ) );
	} catch ( CheckoutAnswered $answered ) {
		unset( $answered );
	}
	$output = (string) ob_get_clean();
	remove_filter( 'wp_die_ajax_handler', $die );
	remove_filter( 'wp_doing_ajax', '__return_true' );
	$_POST    = array();
	$_REQUEST = array();
	\OhMyLMS\Commerce\ohmylmse_clear_notices();
	$json = json_decode( $output, true );
	return is_array( $json ) ? $json : array( 'raw' => $output );
}

/**
 * Put one course in the cart.
 *
 * @param int $course Course.
 */
function cx_cart( $course ) {
	$cart = \OhMyLMS\Commerce\ecommerce()->cart;
	$cart->empty_cart();
	$cart->add_to_cart( $course );
	$cart->calculate_totals();
}

/**
 * Checkout fields.
 *
 * @param string $email  Email.
 * @param string $method Payment method.
 * @return array
 */
function cx_fields( $email, $method = 'offline' ) {
	return array(
		'payment_method' => $method,
		'email'          => $email,
		'first_name'     => 'Guest',
		'last_name'      => 'Buyer',
		'address'        => '1 Test Street',
		'country'        => 'US',
		'city'           => 'Test',
		'postcode'       => '10000',
	);
}

/**
 * Orders of a student.
 *
 * @param int $user_id Student.
 * @return int[]
 */
function cx_orders( $user_id ) {
	global $wpdb;
	return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_student_id' AND meta_value = %s", (string) $user_id ) ) );
}

try {
	$admin = (int) get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	)[0];
	$users['sub'] = wp_insert_user(
		array(
			'user_login' => $tag . '_sub',
			'user_pass'  => wp_generate_password( 24 ),
			'user_email' => $tag . '_sub@example.invalid',
			'role'       => 'subscriber',
		)
	);

	// Permissions.
	wp_set_current_user( 0 );
	cx_error( cx_api( 'GET' ), 401, 'ohmylms_settings_cannot_view', 'anonymous read' );
	cx_error( cx_api( 'PATCH', array( 'guest_checkout' => true ) ), 401, 'ohmylms_settings_cannot_edit', 'anonymous write' );
	wp_set_current_user( $users['sub'] );
	cx_error( cx_api( 'GET' ), 403, 'ohmylms_settings_cannot_view', 'subscriber read' );
	cx_error( cx_api( 'PATCH', array( 'guest_checkout' => true ) ), 403, 'ohmylms_settings_cannot_edit', 'subscriber write' );
	ok( $snapshot['ohmylms_allow_purchase_without_login'] === get_option( 'ohmylms_allow_purchase_without_login', null ), 'denied write changed nothing' );

	// Read.
	wp_set_current_user( $admin );
	$read = cx_api( 'GET' );
	$data = $read->get_data();
	ok( 200 === $read->get_status() && 'no-store' === $read->get_headers()['Cache-Control'], 'read with no-store' );
	$ids = array_keys( \OhMyLMS\Settings\PaymentGatewaySettings::gateways() );
	$got = $data['gateway_order'];
	sort( $got );
	sort( $ids );
	ok( $got === $ids && count( $data['gateways'] ) === count( $ids ), 'gateway order lists every registered gateway' );
	ok( is_bool( $data['guest_checkout'] ) && is_bool( $data['login_form'] ) && is_bool( $data['content_protection'] ) && preg_match( '/^[a-f0-9]{16}$/', $data['revision'] ), 'flags and revision' );
	if ( null !== $data['stripe_webhook'] ) {
		ok( null === $data['stripe_webhook']['webhook_endpoint'] && 'return_url' === $data['stripe_webhook']['confirmation'] && in_array( $data['stripe_webhook']['mode'], array( 'test', 'live' ), true ), 'stripe status is read-only data' );
		ok( false === strpos( wp_json_encode( $data ), 'sk_' ) && false === strpos( wp_json_encode( $data['stripe_webhook'] ), 'secret' ), 'no Stripe secret in the response' );
	}

	// Validation.
	cx_error( cx_api( 'PATCH', array( 'stripe_webhook' => array() ) ), 400, 'ohmylms_settings_unknown_field', 'stripe status is read-only' );
	cx_error( cx_api( 'PATCH', array( 'ohmylms_guest_checkout' => 'yes' ) ), 400, 'ohmylms_settings_unknown_field', 'raw option names refused' );
	cx_error( cx_api( 'PATCH', array( 'guest_checkout' => 'yes' ) ), 400, 'rest_invalid_param', 'flag must be boolean' );
	cx_error( cx_api( 'PATCH', array( 'gateway_order' => 'offline' ) ), 400, 'rest_invalid_param', 'order must be a list' );
	cx_error( cx_api( 'PATCH', array( 'gateway_order' => array( 'offline' ) ) ), 422, 'ohmylms_settings_invalid_value', 'order must list every gateway' );
	cx_error( cx_api( 'PATCH', array( 'gateway_order' => array_merge( $ids, array( 'unknown' ) ) ) ), 422, 'ohmylms_settings_invalid_value', 'unknown gateway in the order' );
	cx_error( cx_api( 'PATCH', array( 'guest_checkout' => true, 'gateway_order' => array( 'x' ) ) ), 422, 'ohmylms_settings_invalid_value', 'all or nothing' );
	ok( $snapshot['ohmylms_allow_purchase_without_login'] === get_option( 'ohmylms_allow_purchase_without_login', null ), 'rejected write changed nothing' );
	cx_error( cx_api( 'PATCH', array( 'guest_checkout' => true, 'revision' => '0000000000000000' ) ), 409, 'ohmylms_settings_stale', 'stale revision' );
	cx_error( cx_api( 'PATCH', 'nope' ), 400, 'rest_invalid_param', 'body must be an object' );

	// Writes.
	$reversed = array_reverse( $data['gateway_order'] );
	$saved    = cx_api(
		'PATCH',
		array(
			'gateway_order'      => $reversed,
			'guest_checkout'     => false,
			'login_form'         => false,
			'content_protection' => true,
			'revision'           => $data['revision'],
		)
	);
	ok( 200 === $saved->get_status() && $reversed === $saved->get_data()['gateway_order'], 'order saved: ' . wp_json_encode( $saved->get_data() ) );
	ok( $saved->get_data()['revision'] !== $data['revision'], 'revision changed' );
	ok( array_flip( $reversed ) === get_option( 'ohmylms_gateway_order' ), 'order stored as id => position' );
	ok( 'no' === get_option( 'ohmylms_allow_purchase_without_login' ) && 'no' === get_option( 'ohmylms_enable_login' ) && 'yes' === get_option( 'ohmylms_content_protection' ), 'toggles stored as yes/no' );
	ok( 1 === (int) get_option( 'ohmylms_integrations' )['content_protection']['is_enable'], 'content protection mirrored into the integration switch' );
	$again = cx_api( 'PATCH', array( 'login_form' => false ) );
	ok( 200 === $again->get_status() && array() === $again->get_data()['changed'], 'same value is not a change' );

	// Gateway order reaches the checkout.
	update_option(
		'ohmylms_offline_settings',
		array_merge(
			(array) $snapshot['ohmylms_offline_settings'],
			array(
				'enabled'   => 'yes',
				'test_mode' => 'yes',
			)
		)
	);
	\OhMyLMS\Commerce\ecommerce()->gateways()->init();
	ok( $reversed === array_keys( \OhMyLMS\Commerce\ecommerce()->gateways()->get_payment_gateways() ), 'gateway registry follows the saved order' );
	ok( count( \OhMyLMS\Commerce\ecommerce()->gateways()->payment_gateways ) === count( $ids ), 're-initialising does not duplicate gateways' );

	// Enforcement: guest checkout off.
	$course  = (int) wp_insert_post(
		array(
			'post_type'   => OHMYLMS_COURSE_CPT,
			'post_status' => 'publish',
			'post_title'  => $tag . ' course',
		)
	);
	$posts[] = $course;
	update_post_meta( $course, '_price_type', 'paid' );
	update_post_meta( $course, '_regular_price', '10' );
	update_post_meta( $course, '_price', '10' );
	$checkout = \OhMyLMS\Commerce\Checkout::instance();
	ok( ! $checkout->is_allow_purchase_without_login() && ! $checkout->is_login_enabled(), 'checkout reads the saved flags' );

	wp_set_current_user( 0 );
	cx_cart( $course );
	$guest_email = $tag . '_guest@example.invalid';
	$answer      = cx_checkout( cx_fields( $guest_email ) );
	ok( 'failure' === ( $answer['result'] ?? '' ) && false !== strpos( (string) $answer['message'], 'log in' ), 'guest refused while guest checkout is off: ' . wp_json_encode( $answer ) );
	ok( ! email_exists( $guest_email ), 'no account created for a refused guest' );

	ob_start();
	ohmylms_get_template( 'checkout/form-checkout.php', array( 'checkout' => $checkout ) );
	$html = (string) ob_get_clean();
	ok( false !== strpos( $html, 'ohmylms-checkout-login-required' ) && false === strpos( $html, 'ohmylms-checkout-form"' ), 'checkout page asks to log in instead of showing the form' );
	ob_start();
	ohmylms_get_template( 'checkout/form-login.php', array( 'checkout' => $checkout ) );
	ok( '' === trim( (string) ob_get_clean() ), 'checkout login form hidden when the login form is off' );
	ob_start();
	ohmylms_checkout_form_contact_title();
	ok( false === strpos( (string) ob_get_clean(), 'showlogin' ), 'no log-in toggle when the login form is off' );

	// Guest checkout on: email-only purchase creates the account, no password is mailed.
	wp_set_current_user( $admin );
	ok( 200 === cx_api( 'PATCH', array( 'guest_checkout' => true, 'login_form' => true ) )->get_status(), 'guest checkout and login form on' );
	wp_set_current_user( 0 );
	ob_start();
	ohmylms_checkout_form_contact_title();
	ok( false !== strpos( (string) ob_get_clean(), 'showlogin' ), 'log-in toggle back when the login form is on' );
	cx_cart( $course );
	$mails  = array();
	$answer = cx_checkout( cx_fields( $guest_email ) );
	$guest  = get_user_by( 'email', $guest_email );
	ok( $guest && ! empty( $answer['redirect'] ), 'guest purchase succeeded: ' . wp_json_encode( $answer ) );
	$users['guest'] = (int) $guest->ID;
	$orders         = cx_orders( $guest->ID );
	ok( 1 === count( $orders ) && 'completed' === ohmylms_ecommerce_get_order( $orders[0] )->get_status(), 'one sandbox-paid order for the guest' );
	ok( 'offline' === ohmylms_ecommerce_get_order( $orders[0] )->get_payment_method(), 'paid with the offline test gateway' );
	ok( (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE user_id = %d AND course_id = %d AND status = 'enrolled'", $wpdb->prefix . 'ohmylms_user_enrollment', $guest->ID, $course ) ), 'guest enrolled' );
	$welcome = array_values( array_filter( $mails, static fn( $mail ) => 'Your account has been created' === $mail['subject'] ) );
	ok( 1 === count( $welcome ) && false !== strpos( $welcome[0]['message'], 'action=rp' ) && false === stripos( $welcome[0]['message'], 'Password:' ), 'welcome email carries a set-password link and no password' );

	// Existing email as a guest: refused, nothing attached to that account.
	wp_set_current_user( 0 );
	cx_cart( $course );
	$answer = cx_checkout( cx_fields( $tag . '_sub@example.invalid' ) );
	ok( 'failure' === ( $answer['result'] ?? '' ) && array() === cx_orders( $users['sub'] ), 'guest cannot buy into an existing account' );

	// Gateways: unknown and disabled methods are refused for a logged-in buyer.
	wp_set_current_user( $users['sub'] );
	cx_cart( $course );
	$answer = cx_checkout( cx_fields( $tag . '_sub@example.invalid', 'bitcoin' ) );
	ok( 'failure' === ( $answer['result'] ?? '' ) && false !== strpos( (string) $answer['message'], 'Invalid payment method' ), 'unknown gateway refused' );
	$disabled = array_values( array_diff( $ids, array( 'offline' ) ) );
	foreach ( $disabled as $gateway_id ) {
		$gateway = \OhMyLMS\Commerce\ecommerce()->gateways()->get_payment_gateways()[ $gateway_id ];
		if ( $gateway->is_available() ) {
			continue;
		}
		cx_cart( $course );
		$answer = cx_checkout( cx_fields( $tag . '_sub@example.invalid', $gateway_id ) );
		ok( 'failure' === ( $answer['result'] ?? '' ), "disabled gateway $gateway_id refused" );
	}
	ok( array() === cx_orders( $users['sub'] ), 'refused checkouts created no order' );

	// The AJAX entry checks the checkout nonce.
	cx_cart( $course );
	$answer = cx_checkout( cx_fields( $tag . '_sub@example.invalid' ), array( \OhMyLMS\Ajax::class, 'checkout' ) );
	ok( 'failure' === ( $answer['result'] ?? '' ) && ! empty( $answer['reload'] ) && array() === cx_orders( $users['sub'] ), 'checkout without nonce refused: ' . wp_json_encode( $answer ) );
	cx_cart( $course );
	$answer = cx_checkout( array_merge( cx_fields( $tag . '_sub@example.invalid' ), array( 'ohmylms-process-checkout-nonce' => wp_create_nonce( 'ohmylms-process_checkout' ) ) ), array( \OhMyLMS\Ajax::class, 'checkout' ) );
	ok( ! empty( $answer['redirect'] ) && 1 === count( cx_orders( $users['sub'] ) ), 'checkout with nonce accepted: ' . wp_json_encode( $answer ) );

	// Retired legacy subscription routes.
	wp_set_current_user( $admin );
	foreach ( array( '/ohmylms-commerce/v1/subscriptions', '/ohmylms-commerce/v1/subscriptions/1', '/cx-ecommerce/v1/subscriptions' ) as $route ) {
		cx_error( rest_do_request( new WP_REST_Request( 'GET', $route ) ), 404, 'rest_no_route', "retired $route" );
	}
	ok( 200 === rest_do_request( new WP_REST_Request( 'GET', '/ohmylms/v1/subscriptions' ) )->get_status(), 'typed subscriptions route still answers' );

	echo "Checkout extras integration: {$checks} checks passed.\n";
} catch ( Throwable $e ) {
	echo $e->getMessage(), "\n", $e->getTraceAsString(), "\n";
	$exit = 1;
} finally {
	foreach ( $snapshot as $option => $value ) {
		if ( null === $value ) {
			delete_option( $option );
		} else {
			update_option( $option, $value );
		}
	}
	\OhMyLMS\Commerce\ecommerce()->cart->empty_cart();
	foreach ( $users as $user_id ) {
		foreach ( cx_orders( $user_id ) as $order_id ) {
			do_action( 'ohmylms_rest_delete_order', $order_id );
			$wpdb->delete( $wpdb->comments, array( 'comment_post_ID' => $order_id ) );
			wp_delete_post( $order_id, true );
		}
		$wpdb->delete( $wpdb->prefix . 'ohmylms_user_enrollment', array( 'user_id' => $user_id ) );
		if ( \OhMyLMS\Student\EnrolmentSchema::exists() ) {
			$wpdb->delete( $wpdb->prefix . 'ohmylms_enrolments', array( 'user_id' => $user_id ) );
		}
	}
	foreach ( $posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $user_id ) {
		wp_delete_user( $user_id );
	}
	exit( $exit );
}
