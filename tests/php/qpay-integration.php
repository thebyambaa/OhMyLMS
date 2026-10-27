<?php
/** Run only against the disposable WordPress test database; all HTTP and email are intercepted. */
if ( PHP_SAPI !== 'cli' ) { exit; }
set_exception_handler( function ( $error ) { fwrite( STDERR, $error->getMessage() . "\n" . $error->getTraceAsString() . "\n" ); exit( 1 ); } );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$config = json_decode( file_get_contents( getenv( 'OHMYLMS_TEST_CREDENTIALS' ) ), true );
require $config['site'] . '/wp-load.php';
if ( ! defined( 'OHMYLMS_TEST_SITE' ) || DB_NAME !== 'ohmylms_source_test' ) { throw new RuntimeException( 'Requires disposable test site' ); }

use OhMyLMS\Commerce\Gateways\QPay\GatewayQPay;
use OhMyLMS\Commerce\Gateways\QPay\PaymentService;
use OhMyLMS\Commerce\Data\Order;

$checks = 0;
function qcheck( $ok, $message ) { global $checks; if ( ! $ok ) { throw new RuntimeException( $message ); } $checks++; }
$old = get_option( 'ohmylms_qpay_settings', null );
$orders = array(); $enrollments = array(); $content = array(); $calls = array(); $events = array(); $scenario = 'pending';
$old_currency = get_option( 'ohmylms_currency', null );
class QPayTestResponse extends Error {}
$row = array( 'payment_id' => 'test-payment', 'payment_status' => 'PAID', 'payment_amount' => '100.00', 'payment_currency' => 'MNT' );
add_filter( 'pre_wp_mail', '__return_true' );
$http = function ( $pre, $args, $url ) use ( &$calls, &$scenario, &$row ) {
    $calls[] = array( $url, $args['method'], $args['body'] ?? '' );
    $body = array(); $status = 200;
    if ( strpos( $url, '/v2/auth/token' ) !== false ) { $body = array( 'access_token' => 'fixture-token', 'expires_in' => time() + 7200 ); }
    elseif ( strpos( $url, '/v2/payment/check' ) !== false ) {
        if ( 'network' === $scenario ) { return new WP_Error( 'timeout', 'fixture timeout' ); }
        $body = array( 'count' => 'pending' === $scenario ? 0 : 1, 'paid_amount' => 100, 'rows' => 'pending' === $scenario ? array() : array( $row ) );
    } elseif ( preg_match( '#/v2/invoice$#', $url ) ) {
        if ( 'create-timeout' === $scenario ) { return new WP_Error( 'timeout', 'fixture timeout' ); }
        $body = array( 'invoice_id' => 'invoice-' . json_decode( $args['body'], true )['sender_invoice_no'], 'qr_image' => 'aGVsbG8=', 'urls' => array( array( 'name' => 'Bank', 'link' => 'bankapp://pay/test' ) ) );
    } elseif ( strpos( $url, '/v2/invoice/' ) !== false ) { $body = array( 'invoice_status' => 'expired' === $scenario ? 'EXPIRED' : 'OPEN', 'qr_image' => 'aGVsbG8=' ); }
    else { return new WP_Error( 'blocked', 'No external HTTP in this test.' ); }
    return array( 'headers' => array(), 'response' => array( 'code' => $status ), 'body' => wp_json_encode( $body ), 'cookies' => array() );
};
add_filter( 'pre_http_request', $http, PHP_INT_MAX, 3 );
foreach ( array( 'ohmylms_payment_completed', 'ohmylms_checkout_after_create_order', 'ohmylms_after_checkout_process' ) as $hook ) {
    add_action( $hook, function () use ( &$events, $hook ) { $events[$hook] = ( $events[$hook] ?? 0 ) + 1; }, 999 );
}
function qorder() {
    global $orders;
    $order = new Order(); $order->set_status( 'pending' ); $order->set_total( '100.00' ); $order->set_currency( 'MNT' ); $order->set_payment_method( 'qpay' );
    $order->set_student_id( get_current_user_id() ); $order->save(); $orders[] = $order->get_id(); return $order;
}
try {
    $settings = array( 'enabled' => 'on', 'testmode' => 'yes', 'invoice_code' => 'FIXTURE', 'test_client_id' => 'fixture', 'test_client_secret' => 'fixture-secret', 'live_client_id' => 'fixture-live', 'live_client_secret' => 'fixture-live-secret' );
    update_option( 'ohmylms_qpay_settings', $settings );
    $gateway = new GatewayQPay();
    qcheck( 'yes' === $gateway->enabled && 'qpay' === $gateway->get_settings()['id'], 'Native settings/normalization' );
    qcheck( isset( \OhMyLMS\Commerce\ecommerce()->gateways()->get_payment_gateways()['qpay'] ), 'Native registry missing QPay' );
    $admin = get_user_by( 'login', $config['username'] ); wp_set_current_user( $admin->ID );
    $controller = new OhMyLMS\Rest\V1\SettingsController();
    $request = new WP_REST_Request( 'POST', '/ohmylms/v1/settings/payment-gateway' );
    $request->set_param( 'group_id', 'payment-gateway' ); $request->set_header( 'content-type', 'application/json' );
    $request->set_body( wp_json_encode( array( 'ohmylms_qpay_settings' => array( 'value' => $settings ) ) ) );
    $controller->update_items( $request );
    qcheck( get_option( 'ohmylms_qpay_settings' ) === $settings, 'Settings REST save lost values' );
    $order = qorder(); $id = $order->get_id();
    $result = $gateway->process_payment( $id );
    qcheck( ! is_wp_error( $result ) && 'pending' === $result['payment_status'], 'Invoice must return pending: ' . ( is_wp_error( $result ) ? $result->get_error_code() . ' ' . $result->get_error_message() : wp_json_encode( $result ) ) );
    qcheck( 'pending' === ohmylms_ecommerce_get_order( $id )->get_status() && empty( $events ), 'Invoice creation fulfilled order' );
    qcheck( ! empty( $result['payment_token'] ), 'Missing order capability' );
    $created = count( array_filter( $calls, function ( $call ) { return preg_match( '#/v2/invoice$#', $call[0] ); } ) );
    qcheck( ! is_wp_error( $gateway->process_payment( $id ) ), 'Resume failed' );
    qcheck( $created === count( array_filter( $calls, function ( $call ) { return preg_match( '#/v2/invoice$#', $call[0] ); } ) ), 'Resume created duplicate invoice' );
    wp_set_current_user( 0 );
    qcheck( ! $gateway->can_read_order( $order, '' ), 'Anonymous access accepted without token' );
    qcheck( ! $gateway->can_read_order( $order, 'wrong' ), 'Wrong token accepted' );
    qcheck( $gateway->can_read_order( $order, $result['payment_token'] ), 'Correct capability rejected' );
    wp_set_current_user( $admin->ID ); qcheck( $gateway->can_read_order( $order ), 'Owner access rejected' );
    $before = count( $calls ); PaymentService::settle( $id, $gateway );
    qcheck( count( $calls ) === $before, 'Local settlement unexpectedly called QPay' );
    PaymentService::settle( $id, $gateway, 'poll' );
    qcheck( count( $calls ) > $before, 'Automatic polling did not verify with QPay' );
    $before = count( $calls ); PaymentService::settle( $id, $gateway, 'poll' );
    qcheck( count( $calls ) === $before, 'Automatic polling ignored its shared rate limit' );
    $callback = new WP_REST_Request( 'GET' ); $callback->set_param( 'order_id', $id );
    qcheck( 403 === $gateway->handle_callback( $callback )->get_status(), 'Missing callback token accepted' );
    $callback->set_param( 'qpay_token', get_post_meta( $id, '_qpay_callback_token', true ) );
    $scenario = 'paid';
    $row['payment_amount'] = '99.99';
    qcheck( 'pending' === $gateway->handle_callback( $callback )->get_data()['status'], 'Underpayment fulfilled' );
    $row['payment_amount'] = '100.00'; $row['payment_currency'] = 'USD';
    qcheck( 503 === $gateway->handle_callback( $callback )->get_status(), 'Wrong currency fulfilled' );
    $row['payment_currency'] = 'MNT'; $row['payment_status'] = 'FAILED';
    qcheck( 'pending' === $gateway->handle_callback( $callback )->get_data()['status'], 'Failed payment fulfilled' );
    $row['payment_status'] = 'PAID'; $row['object_id'] = 'wrong-invoice';
    qcheck( 503 === $gateway->handle_callback( $callback )->get_status(), 'Wrong invoice fulfilled' ); unset( $row['object_id'] );
    $scenario = 'network'; qcheck( 503 === $gateway->handle_callback( $callback )->get_status(), 'Network failure not retryable' );
    $scenario = 'paid';
    qcheck( 'pending' === $gateway->handle_callback( $callback )->get_data()['status'], 'Early callback raced enrollment' );
    $wpdb->insert( $wpdb->prefix . 'ohmylms_user_enrollment', array( 'order_id' => $id, 'user_id' => $admin->ID, 'course_id' => 999999, 'status' => 'pending', 'progress' => 'running', 'start_date' => current_time( 'mysql' ) ) );
    qcheck( (bool) $wpdb->insert_id, 'Could not create enrollment fixture' ); $enrollments[] = $wpdb->insert_id;
    update_post_meta( $id, '_qpay_checkout_ready', 1 );
    qcheck( 'paid' === PaymentService::settle( $id, $gateway )['status'], 'Verified payment did not complete' );
    qcheck( 'enrolled' === $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE id=%d", end( $enrollments ) ) ), 'Pending enrollment not activated' );
    qcheck( 'completed' === ohmylms_ecommerce_get_order( $id )->get_status(), 'Order not completed' );
    $snapshot = $events;
    $gateway->handle_callback( $callback ); PaymentService::settle( $id, $gateway );
    qcheck( $events === $snapshot && 1 === $events['ohmylms_checkout_after_create_order'], 'Duplicate completion events' );
    $auto = qorder(); $gateway->process_payment( $auto->get_id() );
    update_post_meta( $auto->get_id(), '_qpay_checkout_ready', 1 );
    qcheck( 'paid' === PaymentService::settle( $auto->get_id(), $gateway, 'poll' )['status'], 'Paid invoice did not complete without callback' );
    $snapshot = $events; $before = count( $calls );
    PaymentService::settle( $auto->get_id(), $gateway, 'poll' );
    qcheck( $events === $snapshot && count( $calls ) === $before, 'Completed polling repeated verification or fulfillment' );
    // Separate DB connection holds the lock, exactly as a concurrent worker would.
    $other = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
    $key = 'ohmylms_qpay_' . md5( $wpdb->prefix . ':' . $id );
    $other->get_var( $other->prepare( 'SELECT GET_LOCK(%s,0)', $key ) );
    qcheck( is_wp_error( PaymentService::settle( $id, $gateway ) ), 'Concurrent worker bypassed lock' );
    $other->get_var( $other->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) ); $other->close();
    $legacy = qorder(); update_post_meta( $legacy->get_id(), '_qpay_invoice_id', 'legacy-invoice' );
    $legacy_callback = new WP_REST_Request( 'GET' ); $legacy_callback->set_param( 'order_id', $legacy->get_id() );
    qcheck( 'paid' === $gateway->handle_callback( $legacy_callback )->get_data()['status'], 'Historical callback stopped working' );
    $pending = qorder(); $gateway->process_payment( $pending->get_id() );
    $settings['testmode'] = 'no'; update_option( 'ohmylms_qpay_settings', $settings ); $live = new GatewayQPay();
    $before = count( $calls ); $scenario = 'pending'; PaymentService::settle( $pending->get_id(), $live, true );
    qcheck( strpos( end( $calls )[0], 'merchant-sandbox.qpay.mn' ) !== false, 'Mode change redirected historical sandbox invoice to live' );
    $live_order = qorder(); $live->process_payment( $live_order->get_id() );
    qcheck( strpos( end( $calls )[0], 'merchant.qpay.mn' ) !== false, 'Live invoice used sandbox' );
    $settings['live_client_secret'] = 'rotated'; update_option( 'ohmylms_qpay_settings', $settings );
    qcheck( is_wp_error( $live->api_for_order( $live_order->get_id() ) ), 'Credential rotation silently changed merchant' );
    $scenario = 'expired'; qcheck( is_wp_error( $gateway->process_payment( $pending->get_id() ) ), 'Expired invoice reused' );
    $scenario = 'create-timeout'; $uncertain = qorder(); $new = new GatewayQPay();
    qcheck( is_wp_error( $new->process_payment( $uncertain->get_id() ) ), 'Timeout not reported' );
    $before = count( $calls ); qcheck( is_wp_error( $new->process_payment( $uncertain->get_id() ) ) && count( $calls ) === $before, 'Uncertain retry duplicated invoice' );
    $recurring = qorder(); qcheck( is_wp_error( $new->process_payment( $recurring->get_id(), true ) ), 'Recurring QPay accepted' );
    qcheck( null === PaymentService::minor_units( 'NaN' ) && null === PaymentService::minor_units( '-1' ), 'Invalid money accepted' );
    $settings['enabled'] = 'no'; update_option( 'ohmylms_qpay_settings', $settings ); qcheck( ! ( new GatewayQPay() )->is_available(), 'Disabled gateway available' );
    $legacy_plugin = dirname( __DIR__, 3 ) . '/ohmylms-qpay/ohmylms-qpay.php';
    if ( is_file( $legacy_plugin ) ) {
        require_once $legacy_plugin;
        ohmylms_qpay_bootstrap();
        qcheck( ! class_exists( 'GatewayQPay', false ), 'Legacy add-on registered duplicate gateway' );
    } else {
        echo "Legacy QPay add-on compatibility check skipped: the optional add-on is not installed.\n";
    }

    // Exercise the real checkout orchestration, including enrollment creation and the JSON response.
    $settings['enabled'] = 'yes'; $settings['testmode'] = 'yes'; update_option( 'ohmylms_qpay_settings', $settings );
    update_option( 'ohmylms_currency', 'MNT' );
    $gateway = new GatewayQPay();
    $registry = \OhMyLMS\Commerce\ecommerce()->gateways();
    foreach ( $registry->payment_gateways as $key => $value ) { if ( 'qpay' === $value->id ) { $registry->payment_gateways[$key] = $gateway; } }
    $course = new OhMyLMS\Data\Course(); $course->set_name( 'QPay checkout fixture' ); $course->set_status( 'publish' );
    $course->set_price_type( 'paid' ); $course->set_regular_price( 100 ); $course->set_price( 100 ); $course->save(); $content[] = $course->get_id();
    $cart = \OhMyLMS\Commerce\ecommerce()->cart; $cart->empty_cart(); $cart->add_to_cart( $course->get_id() ); $cart->calculate_totals();
    qcheck( 100.0 === (float) $cart->get_total( 'edit' ), 'Checkout cart price fixture failed' );
    $_POST = array( 'payment_method' => 'qpay', 'email' => $admin->user_email, 'first_name' => 'QPay', 'last_name' => 'Fixture', 'country' => 'MN', 'phone' => '99999999', 'address' => 'Test', 'city' => 'Test', 'postcode' => '10000', 'state' => 'Test' );
    $scenario = 'pending'; $before = $events;
    add_filter( 'wp_doing_ajax', '__return_true' );
    $die = function () { return function () { throw new QPayTestResponse(); }; };
    add_filter( 'wp_die_ajax_handler', $die );
    add_action( 'ohmylms_checkout_order_created', function ( $order ) use ( &$orders ) { $orders[] = $order->get_id(); } );
    ob_start();
    try { \OhMyLMS\Commerce\Checkout::instance()->process_checkout(); } catch ( QPayTestResponse $response ) {}
    $output = ob_get_clean();
    remove_filter( 'wp_die_ajax_handler', $die ); remove_filter( 'wp_doing_ajax', '__return_true' );
    $checkout = json_decode( $output, true );
    qcheck( is_array( $checkout ) && 'pending' === ( $checkout['payment_status'] ?? '' ), 'Real checkout did not return pending: ' . $output );
    $checkout_id = $checkout['order_id'];
    qcheck( 'pending' === ohmylms_ecommerce_get_order( $checkout_id )->get_status(), 'Checkout marked unpaid order complete' );
    qcheck( $events === $before, 'Checkout fired paid events before payment' );
    qcheck( ! $cart->is_empty(), 'Pending checkout discarded cart' );
    $enrollment = $wpdb->get_row( $wpdb->prepare( "SELECT id,status FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE order_id=%d", $checkout_id ) );
    if ( $enrollment ) { $enrollments[] = $enrollment->id; }
    qcheck( $enrollment && 'pending' === $enrollment->status, 'Checkout enrollment was not pending' );
    $scenario = 'paid';
    qcheck( 'paid' === PaymentService::settle( $checkout_id, $gateway, true )['status'], 'Checkout invoice failed to fulfill' );
    qcheck( 'enrolled' === $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE id=%d", $enrollment->id ) ), 'Checkout enrollment did not activate' );

    $membership = new OhMyLMS\Data\Membership(); $membership->set_name( 'QPay one-time fixture' ); $membership->set_status( 'publish' );
    $membership->set_subscription_period( 'one_time' ); $membership->set_regular_price( 100 ); $membership->set_price( 100 ); $membership->set_products( array() ); $membership->save(); $content[] = $membership->get_id();
    $cart->empty_cart(); $cart->add_to_cart( $membership->get_id() ); $cart->calculate_totals();
    qcheck( $gateway->is_available(), 'One-time membership hides QPay' );
    $member_order = qorder(); update_post_meta( $member_order->get_id(), '_membership_id', $membership->get_id() );
    $scenario = 'pending'; qcheck( ! is_wp_error( $gateway->process_payment( $member_order->get_id() ) ), 'One-time membership rejected' );
    $wpdb->insert( $wpdb->prefix . 'ohmylms_user_membership', array( 'order_id' => $member_order->get_id(), 'user_id' => $admin->ID, 'membership_id' => $membership->get_id(), 'status' => 'pending', 'progress' => 'running', 'start_date' => current_time( 'mysql' ) ) );
    update_post_meta( $member_order->get_id(), '_qpay_checkout_ready', 1 ); $scenario = 'paid';
    qcheck( 'paid' === PaymentService::settle( $member_order->get_id(), $gateway, true )['status'], 'One-time membership payment failed' );
    qcheck( 'enrolled' === $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}ohmylms_user_membership WHERE order_id=%d", $member_order->get_id() ) ), 'Membership access not activated' );
    $membership->set_subscription_period( 'month' ); $membership->save(); $cart->empty_cart(); $cart->add_to_cart( $membership->get_id() ); $cart->calculate_totals();
    qcheck( ! $gateway->is_available(), 'Recurring membership exposes QPay' );
    $recurring_order = qorder(); update_post_meta( $recurring_order->get_id(), '_membership_id', $membership->get_id() );
    qcheck( is_wp_error( $gateway->process_payment( $recurring_order->get_id() ) ), 'Recurring membership bypassed backend validation' );
    update_option( 'ohmylms_currency', 'USD' ); qcheck( ! $gateway->is_available(), 'Non-MNT cart exposes QPay' );
    update_option( 'ohmylms_currency', 'MNT' );
    // A provider callback can beat enrollment insertion; completion must not need a browser poll.
    $course = new OhMyLMS\Data\Course(); $course->set_name( 'QPay early callback fixture' ); $course->set_status( 'publish' );
    $course->set_price_type( 'paid' ); $course->set_regular_price( 100 ); $course->set_price( 100 ); $course->save(); $content[] = $course->get_id();
    $cart->empty_cart(); $cart->add_to_cart( $course->get_id() ); $cart->calculate_totals();
    $early = function ( $id ) use ( $gateway ) { PaymentService::settle( $id, $gateway, true ); };
    add_action( 'ohmylms_after_order_payment', $early ); $scenario = 'paid';
    add_filter( 'wp_doing_ajax', '__return_true' ); add_filter( 'wp_die_ajax_handler', $die );
    ob_start();
    try { \OhMyLMS\Commerce\Checkout::instance()->process_checkout(); } catch ( QPayTestResponse $response ) {}
    $early_response = json_decode( ob_get_clean(), true );
    remove_action( 'ohmylms_after_order_payment', $early );
    remove_filter( 'wp_die_ajax_handler', $die ); remove_filter( 'wp_doing_ajax', '__return_true' );
    qcheck( ! empty( $early_response['order_id'] ), 'Early callback checkout failed' );
    qcheck( 'completed' === ohmylms_ecommerce_get_order( $early_response['order_id'] )->get_status(), 'Early callback still required a browser poll' );
    qcheck( 'enrolled' === $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE order_id=%d", $early_response['order_id'] ) ), 'Early callback missed enrollment' );
    echo "QPay integration: {$checks} checks passed.\n";
} finally {
    foreach ( $enrollments as $id ) { $wpdb->delete( $wpdb->prefix . 'ohmylms_user_enrollment', array( 'id' => $id ) ); }
    foreach ( $orders as $id ) {
        $wpdb->delete( $wpdb->prefix . 'ohmylms_user_membership', array( 'order_id' => $id ) );
        $wpdb->delete( $wpdb->prefix . 'ohmylms_user_enrollment', array( 'order_id' => $id ) );
        wp_delete_post( $id, true );
    }
    foreach ( $content as $id ) { wp_delete_post( $id, true ); }
    if ( isset( $cart ) ) { $cart->empty_cart(); }
    if ( null === $old_currency ) { delete_option( 'ohmylms_currency' ); } else { update_option( 'ohmylms_currency', $old_currency ); }
    if ( null === $old ) { delete_option( 'ohmylms_qpay_settings' ); } else { update_option( 'ohmylms_qpay_settings', $old ); }
    remove_filter( 'pre_http_request', $http, PHP_INT_MAX );
}
