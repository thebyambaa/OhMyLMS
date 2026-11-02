<?php
/**
 * Commerce (orders, coupons, refunds) REST API integration test.
 *
 * Runs against a WordPress install (the Local site by default) with real database tables. It creates
 * tagged offline-gateway orders, coupons, a course and users, and removes everything in `finally`.
 * No payment gateway is contacted and no email is sent (wp_mail is short-circuited).
 *
 * Usage (set OHMYLMS_COMMERCE_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/commerce-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, Generic.Formatting.MultipleStatementAlignment

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_COMMERCE_TEST' ) ) {
	exit( "Set OHMYLMS_COMMERCE_TEST=1 to run the commerce REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Commerce\Data\Order;
use OhMyLMS\Commerce\Data\OrderItemCourse;
use OhMyLMS\Commerce\Rest\V1\OrdersController;

global $wpdb;

$checks    = 0;
$post_ids  = array();
$user_ids  = array();
$tag       = 'cmtest' . strtolower( wp_generate_password( 6, false, false ) );
$base      = '/ohmylms-commerce/v1';
$mail_sent = 0;

add_filter(
	'pre_wp_mail',
	static function () use ( &$mail_sent ) {
		++$mail_sent;
		return true;
	}
);

/**
 * Assert a condition.
 *
 * @param mixed  $value   Value that must be truthy.
 * @param string $message Failure message.
 * @throws RuntimeException When the condition is falsy.
 */
function ok( $value, $message ) {
	global $checks;
	if ( ! $value ) {
		throw new RuntimeException( 'FAILED: ' . $message );
	}
	++$checks;
}

/**
 * Dispatch a REST request.
 *
 * @param string $method HTTP method.
 * @param string $path   Path below the commerce namespace.
 * @param array  $data   Body (JSON) or query (GET).
 * @return WP_REST_Response
 */
function call_api( $method, $path, array $data = array() ) {
	global $base;
	$request = new WP_REST_Request( $method, $base . $path );
	if ( 'GET' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

/**
 * Create an offline order with one course line.
 *
 * @param array $args Overrides: status, total, first, last, email, student, course_id, method.
 * @return int Order ID.
 */
function make_order( array $args = array() ) {
	global $post_ids, $tag;
	$args  = array_merge(
		array(
			'status'    => 'completed',
			'total'     => 49.5,
			'first'     => 'Ada',
			'last'      => 'Lovelace ' . $tag,
			'email'     => $tag . '-buyer@example.invalid',
			'student'   => 0,
			'course_id' => 0,
			'method'    => 'offline',
		),
		$args
	);
	$order = new Order();
	$order->set_status( $args['status'] );
	$order->set_first_name( $args['first'] );
	$order->set_last_name( $args['last'] );
	$order->set_email( $args['email'] );
	$order->set_student_id( $args['student'] );
	$order->set_currency( 'USD' );
	$order->set_payment_method( $args['method'] );
	$order->set_payment_method_title( 'Offline' );
	$order->set_total( $args['total'] );
	$item = new OrderItemCourse();
	$item->set_props(
		array(
			'name'      => 'Course ' . $tag,
			'course_id' => $args['course_id'],
			'quantity'  => 1,
			'subtotal'  => $args['total'],
			'total'     => $args['total'],
		)
	);
	$order->add_item( $item );
	$id          = $order->save();
	$post_ids[]  = $id;
	return (int) $id;
}

$exit_code = 0;
$fired     = array(
	'completed' => 0,
	'cancelled' => 0,
	'before'    => 0,
	'deleted'   => 0,
);
add_action(
	'ohmylms_update_order_status_to_completed',
	static function () use ( &$fired ) {
		++$fired['completed'];
	}
);
add_action(
	'ohmylms_update_order_status_to_cancelled',
	static function () use ( &$fired ) {
		++$fired['cancelled'];
	}
);
add_action(
	'ohmylms_rest_before_delete_order',
	static function () use ( &$fired ) {
		++$fired['before'];
	}
);
add_action(
	'ohmylms_rest_delete_order',
	static function () use ( &$fired ) {
		++$fired['deleted'];
	}
);

try {
	$admins = get_users(
		array(
			'role'    => 'administrator',
			'number'  => 1,
			'orderby' => 'ID',
			'fields'  => 'ID',
		)
	);
	ok( ! empty( $admins ), 'an administrator exists' );
	$admin = (int) $admins[0];

	$subscriber = wp_insert_user(
		array(
			'user_login' => $tag . '_sub',
			'user_pass'  => wp_generate_password( 24 ),
			'user_email' => $tag . '-sub@example.invalid',
			'role'       => 'subscriber',
		)
	);
	ok( ! is_wp_error( $subscriber ), 'temporary subscriber created' );
	$user_ids[] = $subscriber;
	$contributor = wp_insert_user(
		array(
			'user_login' => $tag . '_con',
			'user_pass'  => wp_generate_password( 24 ),
			'user_email' => $tag . '-con@example.invalid',
			'role'       => 'contributor',
		)
	);
	ok( ! is_wp_error( $contributor ), 'temporary contributor created' );
	$user_ids[] = $contributor;

	$course = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_COURSE_CPT,
			'post_status' => 'publish',
			'post_title'  => 'Course ' . $tag,
		)
	);
	ok( $course > 0, 'test course created' );
	$post_ids[] = $course;

	// Routes.
	$routes = rest_get_server()->get_routes( 'ohmylms-commerce/v1' );
	foreach ( array( '/orders', '/orders/status-counts', '/orders/(?P<id>[\d]+)', '/orders/(?P<id>[\d]+)/notes', '/orders/(?P<order_id>[\d]+)/refunds', '/coupons', '/coupons/(?P<id>[\d]+)' ) as $route ) {
		ok( isset( $routes[ $base . $route ] ), 'route registered: ' . $route );
		foreach ( $routes[ $base . $route ] as $endpoint ) {
			ok( is_callable( $endpoint['permission_callback'] ) && '__return_true' !== $endpoint['permission_callback'], 'real permission_callback on ' . $route );
		}
	}
	ok( ! isset( $routes[ $base . '/orders/trash-bulk' ] ) && ! isset( $routes[ $base . '/orders/stats' ] ), 'removed routes are gone' );
	ok( $routes[ $base . '/orders' ][0]['callback'][0] instanceof OrdersController, 'orders route belongs to OrdersController' );

	// Fixtures.
	$buyer = make_order(
		array(
			'student'   => $subscriber,
			'course_id' => $course,
			'total'     => 49.5,
		)
	);
	$pending = make_order(
		array(
			'status' => 'pending',
			'total'  => 10,
			'first'  => 'Grace',
			'last'   => 'Hopper ' . $tag,
			'email'  => $tag . '-grace@example.invalid',
		)
	);
	$card    = make_order(
		array(
			'status' => 'completed',
			'total'  => 20,
			'method' => 'stripe',
			'first'  => 'Alan',
			'last'   => 'Turing ' . $tag,
		)
	);
	ok( $buyer && $pending && $card, 'three orders created' );

	// Anonymous, subscriber, contributor: every route denied (401 or 403), nothing executed.
	$denied = array(
		array( 'GET', '/orders' ),
		array( 'GET', '/orders/status-counts' ),
		array( 'GET', "/orders/$buyer" ),
		array( 'PATCH', "/orders/$buyer", array( 'status' => 'cancelled' ) ),
		array( 'DELETE', "/orders/$buyer" ),
		array( 'GET', "/orders/$buyer/notes" ),
		array( 'POST', "/orders/$buyer/notes", array( 'note' => 'x' ) ),
		array( 'GET', "/orders/$buyer/refunds" ),
		array(
			'POST',
			"/orders/$buyer/refunds",
			array(
				'amount' => 1,
				'reason' => 'x',
			),
		),
		array( 'GET', '/coupons' ),
		array(
			'POST',
			'/coupons',
			array(
				'code'          => 'X' . $tag,
				'discount_type' => 'percent',
				'amount'        => 10,
			),
		),
		array( 'GET', '/coupons/1' ),
		array( 'PATCH', '/coupons/1', array( 'amount' => 5 ) ),
		array( 'DELETE', '/coupons/1' ),
	);
	foreach ( array( 0, $subscriber, $contributor ) as $user ) {
		wp_set_current_user( $user );
		foreach ( $denied as $case ) {
			$response = call_api( $case[0], $case[1], isset( $case[2] ) ? $case[2] : array() );
			$expected = $user ? 403 : 401;
			ok( $expected === $response->get_status(), "user $user denied {$case[0]} {$case[1]} (got " . $response->get_status() . ')' );
		}
	}
	ok( 'completed' === ohmylms_ecommerce_get_order( $buyer )->get_status(), 'denied requests changed nothing' );

	wp_set_current_user( $admin );

	// Orders list: pagination headers, light rows, no personal data beyond the name.
	$response = call_api(
		'GET',
		'/orders',
		array(
			'search'   => $tag,
			'per_page' => 2,
			'orderby'  => 'id',
			'order'    => 'asc',
		)
	);
	$headers  = $response->get_headers();
	ok( 200 === $response->get_status() && 2 === count( $response->get_data() ), 'list returns a page of 2' );
	ok( '3' === (string) $headers['X-WP-Total'] && '2' === (string) $headers['X-WP-TotalPages'], 'list sends total headers' );
	$row = $response->get_data()[0];
	ok( $buyer === $row['id'] && 'completed' === $row['status'] && '49.50' === $row['total'] && 'USD' === $row['currency'], 'list row money and status' );
	ok( $subscriber === $row['student_id'] && 'Ada Lovelace ' . $tag === $row['student_name'], 'list row student' );
	foreach ( array( 'billing', 'email', 'line_items', 'student_email', 'address', 'phone' ) as $private ) {
		ok( ! array_key_exists( $private, $row ), "list row has no $private" );
	}
	ok(
		400 === call_api(
			'GET',
			'/orders',
			array(
				'search'   => $tag,
				'per_page' => 2,
				'page'     => 5,
			)
		)->get_status(),
		'page beyond the last is 400'
	);
	ok( 400 === call_api( 'GET', '/orders', array( 'per_page' => 1000 ) )->get_status(), 'per_page over 100 is 400' );
	ok( 400 === call_api( 'GET', '/orders', array( 'per_page' => 0 ) )->get_status(), 'per_page 0 is 400' );
	ok( 400 === call_api( 'GET', '/orders', array( 'status' => 'bogus' ) )->get_status(), 'unknown status is 400' );
	ok( 400 === call_api( 'GET', '/orders', array( 'orderby' => 'meta_value' ) )->get_status(), 'unknown orderby is 400' );
	ok( 400 === call_api( 'GET', '/orders', array( 'after' => '2026-02-31' ) )->get_status(), 'impossible date is 400' );
	ok(
		400 === call_api(
			'GET',
			'/orders',
			array(
				'after'  => '2026-05-02',
				'before' => '2026-05-01',
			)
		)->get_status(),
		'reversed range is 400'
	);
	ok( 400 === call_api( 'GET', '/orders', array( 'filter' => array( 'posts_per_page' => -1 ) ) )->get_status() || 200 === call_api( 'GET', '/orders', array( 'filter' => array( 'posts_per_page' => -1 ) ) )->get_status(), 'unknown filter arg is ignored or rejected' );
	ok(
		3 === (int) call_api(
			'GET',
			'/orders',
			array(
				'filter' => array( 'post_type' => 'post' ),
				'search' => $tag,
			)
		)->get_headers()['X-WP-Total'],
		'raw WP_Query filters are not accepted'
	);

	// Filters.
	$by_status = call_api(
		'GET',
		'/orders',
		array(
			'search' => $tag,
			'status' => 'pending',
		)
	);
	ok( 1 === count( $by_status->get_data() ) && $pending === $by_status->get_data()[0]['id'], 'status filter' );
	$by_method = call_api(
		'GET',
		'/orders',
		array(
			'search'         => $tag,
			'payment_method' => 'stripe',
		)
	);
	ok( 1 === count( $by_method->get_data() ) && $card === $by_method->get_data()[0]['id'], 'payment method filter' );
	ok( 1 === count( call_api( 'GET', '/orders', array( 'search' => 'Hopper ' . $tag ) )->get_data() ), 'search by full name' );
	ok( 1 === count( call_api( 'GET', '/orders', array( 'search' => $tag . '-grace@example' ) )->get_data() ), 'search by email' );
	ok( 1 === count( call_api( 'GET', '/orders', array( 'search' => (string) $card ) )->get_data() ), 'search by order number' );
	ok( 0 === count( call_api( 'GET', '/orders', array( 'search' => 'zzz-' . $tag . '-nomatch' ) )->get_data() ), 'search without a match is empty' );
	ok(
		0 === count(
			call_api(
				'GET',
				'/orders',
				array(
					'search' => $tag,
					'after'  => '2000-01-01',
					'before' => '2000-01-02',
				)
			)->get_data()
		),
		'date range excludes new orders'
	);
	$today = current_time( 'Y-m-d' );
	ok(
		3 === (int) call_api(
			'GET',
			'/orders',
			array(
				'search' => $tag,
				'after'  => $today,
				'before' => $today,
			)
		)->get_headers()['X-WP-Total'],
		'date range includes today'
	);
	$sorted = call_api(
		'GET',
		'/orders',
		array(
			'search'  => $tag,
			'orderby' => 'total',
			'order'   => 'desc',
		)
	)->get_data();
	ok( array( $buyer, $card, $pending ) === array_column( $sorted, 'id' ), 'sort by total desc' );
	// Single quote in a search must be harmless.
	ok( 200 === call_api( 'GET', '/orders', array( 'search' => "'; DROP TABLE x; --" ) )->get_status(), 'SQL metacharacters in search are safe' );

	// Status counts.
	$counts = call_api( 'GET', '/orders/status-counts' )->get_data();
	ok( $counts['pending'] >= 1 && $counts['completed'] >= 2 && array_key_exists( 'refunded', $counts ), 'status counts' );

	// Detail.
	$response = call_api( 'GET', "/orders/$buyer" );
	$detail   = $response->get_data();
	ok( 200 === $response->get_status() && $buyer === $detail['id'], 'detail 200' );
	ok( 'ada' === strtolower( $detail['billing']['first_name'] ) && $tag . '-buyer@example.invalid' === $detail['billing']['email'], 'detail billing' );
	ok( 1 === count( $detail['line_items'] ) && $course === $detail['line_items'][0]['course_id'] && '49.50' === $detail['line_items'][0]['total'], 'detail line item' );
	ok( '49.50' === $detail['remaining_refundable'] && '0.00' === $detail['total_refunded'] && array() === $detail['refunds'], 'detail refund totals' );
	ok( array( 'cancelled' ) === $detail['allowed_statuses'], 'completed order may only be cancelled' );
	ok( array_diff( array_keys( $detail ), array( 'id', 'number', 'status', 'currency', 'total', 'date_created', 'payment_method', 'payment_method_title', 'student_id', 'student_name', 'parent_id', 'subtotal', 'discount_total', 'tax_amount', 'date_paid', 'date_completed', 'transaction_id', 'billing', 'line_items', 'coupon_lines', 'refunds', 'total_refunded', 'remaining_refundable', 'related_orders', 'allowed_statuses', 'points' ) ) === array(), 'detail has only documented fields' );
	ok( null === $detail['points'], 'a cash order has no points summary' );
	ok( 'yes' === get_post_meta( $buyer, '_is_open', true ), 'opening an order marks it seen' );
	ok( 404 === call_api( 'GET', '/orders/999999999' )->get_status(), 'unknown order is 404' );
	$page = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'draft',
			'post_title'  => 'Not an order ' . $tag,
		)
	);
	$post_ids[] = $page;
	ok( 404 === call_api( 'GET', "/orders/$page" )->get_status(), 'a page ID is not an order (404)' );
	ok( 404 === call_api( 'PATCH', "/orders/$page", array( 'status' => 'cancelled' ) )->get_status(), 'cannot change a non-order' );
	ok( 404 === call_api( 'DELETE', "/orders/$page" )->get_status() && 'draft' === get_post_status( $page ), 'cannot trash a non-order' );

	// Status changes.
	ok( 400 === call_api( 'PATCH', "/orders/$pending", array( 'status' => 'refunded' ) )->get_status(), 'refunded cannot be set by hand' );
	ok( 400 === call_api( 'PATCH', "/orders/$pending", array( 'status' => 'bogus' ) )->get_status(), 'unknown status is 400' );
	ok( 400 === call_api( 'PATCH', "/orders/$pending", array() )->get_status(), 'missing status is 400' );
	$response = call_api( 'PATCH', "/orders/$buyer", array( 'status' => 'pending' ) );
	ok( 409 === $response->get_status() && 'ohmylms_order_invalid_transition' === $response->get_data()['code'], 'completed -> pending is refused' );
	ok( 'completed' === ohmylms_ecommerce_get_order( $buyer )->get_status(), 'refused change left the order alone' );
	$response = call_api( 'PATCH', "/orders/$pending", array( 'status' => 'completed' ) );
	ok( 200 === $response->get_status() && 'completed' === $response->get_data()['status'], 'pending -> completed' );
	ok( $fired['completed'] >= 1, 'ohmylms_update_order_status_to_completed fired' );
	$completed_count = $fired['completed'];
	$response        = call_api( 'PATCH', "/orders/$pending", array( 'status' => 'completed' ) );
	ok( 200 === $response->get_status() && $completed_count === $fired['completed'], 'same status again is a no-op without hooks (double click safe)' );
	$notes = call_api( 'GET', "/orders/$pending/notes" )->get_data();
	ok( count( $notes ) >= 1, 'status change wrote a system note' );
	$response = call_api( 'PATCH', "/orders/$pending", array( 'status' => 'cancelled' ) );
	ok( 200 === $response->get_status() && 'cancelled' === $response->get_data()['status'] && $fired['cancelled'] >= 1, 'completed -> cancelled fires the cancel hook' );
	ok( array( 'pending' ) === call_api( 'GET', "/orders/$pending" )->get_data()['allowed_statuses'], 'cancelled order may be reopened' );

	// A held lock refuses a parallel change.
	add_option( 'ohmylms_lock_order-' . $card, time(), '', 'no' );
	$response = call_api( 'PATCH', "/orders/$card", array( 'status' => 'cancelled' ) );
	ok( 409 === $response->get_status() && 'ohmylms_order_busy' === $response->get_data()['code'], 'a concurrent change is refused' );
	delete_option( 'ohmylms_lock_order-' . $card );
	ok( false === get_option( 'ohmylms_lock_order-' . $buyer ), 'locks are released after a change' );

	// Notes: plain text.
	$response = call_api( 'POST', "/orders/$buyer/notes", array( 'note' => "Called the student <script>alert(1)</script><b>today</b>\nSecond line" ) );
	$note     = $response->get_data();
	ok( 201 === $response->get_status() && false === strpos( $note['content'], '<' ), 'note markup is stripped: ' . $note['content'] );
	ok( false !== strpos( $note['content'], 'Second line' ) && 'system' !== $note['added_by'], 'note keeps text and shows the author' );
	ok( 422 === call_api( 'POST', "/orders/$buyer/notes", array( 'note' => '<b></b>' ) )->get_status(), 'markup-only note is empty (422)' );
	ok( 400 === call_api( 'POST', "/orders/$buyer/notes", array( 'note' => '' ) )->get_status(), 'empty note is 400' );
	ok( 400 === call_api( 'POST', "/orders/$buyer/notes", array( 'note' => str_repeat( 'a', 2001 ) ) )->get_status(), 'overlong note is 400' );
	ok( 400 === call_api( 'POST', "/orders/$buyer/notes", array() )->get_status(), 'missing note is 400' );
	ok( 404 === call_api( 'POST', '/orders/999999999/notes', array( 'note' => 'x' ) )->get_status(), 'note on an unknown order is 404' );
	$notes = call_api( 'GET', "/orders/$buyer/notes" )->get_data();
	ok( $note['id'] === $notes[0]['id'], 'notes are newest first' );

	// Refunds.
	$url = "/orders/$buyer/refunds";
	ok(
		422 === call_api(
			'POST',
			$url,
			array(
				'amount' => 0,
				'reason' => 'r',
			)
		)->get_status(),
		'zero refund is 422'
	);
	ok(
		422 === call_api(
			'POST',
			$url,
			array(
				'amount' => -5,
				'reason' => 'r',
			)
		)->get_status(),
		'negative refund is 422'
	);
	ok(
		422 === call_api(
			'POST',
			$url,
			array(
				'amount' => 'abc',
				'reason' => 'r',
			)
		)->get_status(),
		'non-numeric refund is 422'
	);
	ok( 400 === call_api( 'POST', $url, array( 'amount' => 5 ) )->get_status(), 'reason is required' );
	ok(
		422 === call_api(
			'POST',
			$url,
			array(
				'amount' => 49.51,
				'reason' => 'too much',
			)
		)->get_status(),
		'refund above the total is 422'
	);
	ok(
		409 === call_api(
			'POST',
			"/orders/$card/refunds",
			array(
				'amount' => 5,
				'reason' => 'card',
			)
		)->get_status() && 'ohmylms_refund_gateway_unsupported' === call_api(
			'POST',
			"/orders/$card/refunds",
			array(
				'amount' => 5,
				'reason' => 'card',
			)
		)->get_data()['code'],
		'non-offline gateways are not refunded here'
	);
	ok(
		409 === call_api(
			'POST',
			"/orders/$pending/refunds",
			array(
				'amount' => 5,
				'reason' => 'cancelled',
			)
		)->get_status(),
		'cancelled order is not refundable'
	);
	ok(
		404 === call_api(
			'POST',
			'/orders/999999999/refunds',
			array(
				'amount' => 5,
				'reason' => 'x',
			)
		)->get_status(),
		'refund of an unknown order is 404'
	);
	ok( 0 === count( call_api( 'GET', $url )->get_data() ), 'refused refunds created nothing' );

	$response = call_api(
		'POST',
		$url,
		array(
			'amount' => 20,
			'reason' => 'Partial <b>refund</b>',
		)
	);
	$refund   = $response->get_data();
	ok( 201 === $response->get_status() && '20.00' === $refund['amount'] && 'Partial refund' === $refund['reason'] && $buyer === $refund['order_id'], 'partial refund created ' . wp_json_encode( $response->get_data() ) );
	$detail = call_api( 'GET', "/orders/$buyer" )->get_data();
	ok( 'completed' === $detail['status'] && '20.00' === $detail['total_refunded'] && '29.50' === $detail['remaining_refundable'] && 1 === count( $detail['refunds'] ), 'order shows the partial refund' );
	ok(
		422 === call_api(
			'POST',
			$url,
			array(
				'amount' => 29.51,
				'reason' => 'again',
			)
		)->get_status(),
		'cannot refund more than what remains'
	);
	add_option( 'ohmylms_lock_refund-' . $buyer, time(), '', 'no' );
	$response = call_api(
		'POST',
		$url,
		array(
			'amount' => 1,
			'reason' => 'double click',
		)
	);
	ok( 409 === $response->get_status() && 'ohmylms_refund_in_progress' === $response->get_data()['code'], 'a refund in progress blocks a second one' );
	delete_option( 'ohmylms_lock_refund-' . $buyer );
	$response = call_api(
		'POST',
		$url,
		array(
			'amount' => 29.5,
			'reason' => 'Rest',
		)
	);
	ok( 201 === $response->get_status(), 'remaining amount refunded' );
	$detail = call_api( 'GET', "/orders/$buyer" )->get_data();
	ok( 'refunded' === $detail['status'] && '0.00' === $detail['remaining_refundable'] && 2 === count( $detail['refunds'] ), 'fully refunded order is refunded' );
	ok( array() === $detail['allowed_statuses'], 'refunded is terminal' );
	ok(
		409 === call_api(
			'POST',
			$url,
			array(
				'amount' => 1,
				'reason' => 'late',
			)
		)->get_status(),
		'a refunded order cannot be refunded again'
	);
	ok( 2 === count( call_api( 'GET', $url )->get_data() ), 'refund list' );

	// Trash.
	$extra = make_order( array( 'status' => 'pending' ) );
	$response = call_api( 'DELETE', "/orders/$extra" );
	ok( 200 === $response->get_status() && true === $response->get_data()['deleted'] && $extra === $response->get_data()['previous']['id'], 'delete 200 with previous' );
	ok( 'trash' === get_post_status( $extra ) && $fired['before'] >= 1 && $fired['deleted'] >= 1, 'order trashed and hooks fired' );
	ok( 404 === call_api( 'GET', "/orders/$extra" )->get_status(), 'trashed order is gone from the API' );
	ok( 404 === call_api( 'DELETE', "/orders/$extra" )->get_status(), 'deleting twice is 404' );

	// Coupons: create.
	$code     = strtoupper( $tag ) . 'A';
	$response = call_api(
		'POST',
		'/coupons',
		array(
			'code'           => ' ' . strtolower( $code ) . ' ',
			'title'          => 'Spring <b>sale</b>',
			'description'    => "Plain\n<script>x</script>text",
			'discount_type'  => 'percent',
			'amount'         => '15',
			'usage_limit'    => 10,
			'individual_use' => true,
			'date_start'     => '2030-01-01T00:00:00',
			'date_expires'   => '2030-12-31T23:59:59',
		)
	);
	$coupon   = $response->get_data();
	$post_ids[] = isset( $coupon['id'] ) ? $coupon['id'] : 0;
	ok( 201 === $response->get_status() && $coupon['id'] > 0, 'coupon created: ' . wp_json_encode( $coupon ) );
	ok( strtolower( $code ) === strtolower( $coupon['code'] ) && 'percent' === $coupon['discount_type'] && '15.00' === $coupon['amount'], 'coupon code trimmed, amount formatted' );
	ok( 'publish' === $coupon['status'] && 0 === $coupon['usage_count'] && 10 === $coupon['usage_limit'] && null === $coupon['usage_limit_per_user'], 'coupon defaults' );
	ok( true === $coupon['individual_use'] && 'all' === $coupon['course_id_type'] && array() === $coupon['course_ids'], 'coupon flags persisted' );
	ok( '2030-01-01T00:00:00' === $coupon['date_start'] && '2030-12-31T23:59:59' === $coupon['date_expires'], 'coupon dates round trip' );
	ok( 'Spring sale' === $coupon['title'] && false === strpos( $coupon['description'], '<' ), 'coupon text is plain: ' . $coupon['description'] );
	ok( false !== strpos( (string) $response->get_headers()['Location'], '/coupons/' . $coupon['id'] ), 'Location header' );
	$cid = $coupon['id'];

	$again = call_api( 'GET', "/coupons/$cid" )->get_data();
	ok( $again === $coupon, 'reopened coupon equals the created one' );

	// Coupons: validation. Nothing may be created.
	$before   = (int) call_api( 'GET', '/coupons', array( 'per_page' => 1 ) )->get_headers()['X-WP-Total'];
	$invalid  = array(
		'duplicate code'           => array(
			409,
			array(
				'code'          => strtolower( $code ),
				'discount_type' => 'percent',
				'amount'        => 5,
			),
		),
		'missing code'             => array(
			400,
			array(
				'discount_type' => 'percent',
				'amount'        => 5,
			),
		),
		'blank code'               => array(
			422,
			array(
				'code'          => '   ',
				'discount_type' => 'percent',
				'amount'        => 5,
			),
		),
		'long code'                => array(
			400,
			array(
				'code'          => str_repeat( 'a', 51 ),
				'discount_type' => 'percent',
				'amount'        => 5,
			),
		),
		'percent over 100'         => array(
			422,
			array(
				'code'          => $code . 'B',
				'discount_type' => 'percent',
				'amount'        => 100.01,
			),
		),
		'zero amount'              => array(
			422,
			array(
				'code'          => $code . 'B',
				'discount_type' => 'flat-rate',
				'amount'        => 0,
			),
		),
		'negative amount'          => array(
			422,
			array(
				'code'          => $code . 'B',
				'discount_type' => 'flat-rate',
				'amount'        => -3,
			),
		),
		'unknown type'             => array(
			400,
			array(
				'code'          => $code . 'B',
				'discount_type' => 'bogus',
				'amount'        => 3,
			),
		),
		'missing amount'           => array(
			400,
			array(
				'code'          => $code . 'B',
				'discount_type' => 'flat-rate',
			),
		),
		'expiry before start'      => array(
			422,
			array(
				'code'          => $code . 'B',
				'discount_type' => 'flat-rate',
				'amount'        => 3,
				'date_start'    => '2030-02-01T00:00:00',
				'date_expires'  => '2030-01-01T00:00:00',
			),
		),
		'bad date'                 => array(
			400,
			array(
				'code'          => $code . 'B',
				'discount_type' => 'flat-rate',
				'amount'        => 3,
				'date_expires'  => 'tomorrow',
			),
		),
		'per user above total'     => array(
			422,
			array(
				'code'                 => $code . 'B',
				'discount_type'        => 'flat-rate',
				'amount'               => 3,
				'usage_limit'          => 2,
				'usage_limit_per_user' => 3,
			),
		),
		'negative limit'           => array(
			400,
			array(
				'code'          => $code . 'B',
				'discount_type' => 'flat-rate',
				'amount'        => 3,
				'usage_limit'   => -1,
			),
		),
		'selected without courses' => array(
			422,
			array(
				'code'           => $code . 'B',
				'discount_type'  => 'flat-rate',
				'amount'         => 3,
				'course_id_type' => 'selected_course',
			),
		),
		'unknown course'           => array(
			422,
			array(
				'code'           => $code . 'B',
				'discount_type'  => 'flat-rate',
				'amount'         => 3,
				'course_id_type' => 'selected_course',
				'course_ids'     => array( 999999999 ),
			),
		),
		'a page as course'         => array(
			422,
			array(
				'code'           => $code . 'B',
				'discount_type'  => 'flat-rate',
				'amount'         => 3,
				'course_id_type' => 'selected_course',
				'course_ids'     => array( $page ),
			),
		),
		'bad status'               => array(
			400,
			array(
				'code'          => $code . 'B',
				'discount_type' => 'flat-rate',
				'amount'        => 3,
				'status'        => 'trash',
			),
		),
	);
	foreach ( $invalid as $label => $case ) {
		$response = call_api( 'POST', '/coupons', $case[1] );
		ok( $case[0] === $response->get_status(), "coupon $label -> {$case[0]} (got " . $response->get_status() . ' ' . wp_json_encode( $response->get_data() ) . ')' );
	}
	ok( (int) call_api( 'GET', '/coupons', array( 'per_page' => 1 ) )->get_headers()['X-WP-Total'] === $before, 'rejected coupons were not created' );

	// Coupons: update.
	$response = call_api( 'PATCH', "/coupons/$cid", array( 'amount' => '120' ) );
	ok( 422 === $response->get_status(), 'raising a percent coupon above 100 is 422' );
	ok( '15.00' === call_api( 'GET', "/coupons/$cid" )->get_data()['amount'], 'rejected update changed nothing' );
	$response = call_api(
		'PATCH',
		"/coupons/$cid",
		array(
			'discount_type'        => 'flat-rate',
			'amount'               => '12.5',
			'course_id_type'       => 'selected_course',
			'course_ids'           => array( $course ),
			'usage_limit_per_user' => 2,
			'date_expires'         => null,
		)
	);
	$updated  = $response->get_data();
	ok( 200 === $response->get_status() && 'flat-rate' === $updated['discount_type'] && '12.50' === $updated['amount'], 'update type and amount' );
	ok( array( $course ) === $updated['course_ids'] && 'selected_course' === $updated['course_id_type'] && 2 === $updated['usage_limit_per_user'] && null === $updated['date_expires'], 'update scope, limit and cleared expiry' );
	ok( '2030-01-01T00:00:00' === $updated['date_start'] && true === $updated['individual_use'] && 10 === $updated['usage_limit'], 'untouched fields kept' );
	$response = call_api(
		'PATCH',
		"/coupons/$cid",
		array(
			'status'         => 'draft',
			'individual_use' => false,
		)
	);
	ok( 'draft' === $response->get_data()['status'] && false === $response->get_data()['individual_use'], 'draft and individual_use=false' );
	$second = call_api(
		'POST',
		'/coupons',
		array(
			'code'          => $code . 'C',
			'discount_type' => 'flat-rate',
			'amount'        => 5,
		)
	)->get_data();
	$post_ids[] = $second['id'];
	ok( 'publish' === $second['status'] && false === $second['individual_use'], 'second coupon created' );
	ok( 409 === call_api( 'PATCH', '/coupons/' . $second['id'], array( 'code' => $code ) )->get_status(), 'renaming onto an existing code is 409' );
	ok( 200 === call_api( 'PATCH', "/coupons/$cid", array( 'code' => $code ) )->get_status(), 'keeping its own code is fine' );
	ok( 404 === call_api( 'PATCH', '/coupons/999999999', array( 'amount' => 5 ) )->get_status(), 'update of an unknown coupon is 404' );
	ok( 404 === call_api( 'GET', "/coupons/$page" )->get_status(), 'a page is not a coupon' );

	// Coupons: list.
	$list = call_api(
		'GET',
		'/coupons',
		array(
			'search'   => $tag,
			'per_page' => 1,
			'orderby'  => 'code',
			'order'    => 'asc',
		)
	);
	ok( 2 === (int) $list->get_headers()['X-WP-Total'] && 2 === (int) $list->get_headers()['X-WP-TotalPages'] && 1 === count( $list->get_data() ), 'coupon list headers' );
	ok(
		1 === count(
			call_api(
				'GET',
				'/coupons',
				array(
					'search' => $tag,
					'status' => 'draft',
				)
			)->get_data()
		),
		'coupon status filter'
	);
	ok(
		400 === call_api(
			'GET',
			'/coupons',
			array(
				'search' => $tag,
				'page'   => 9,
			)
		)->get_status(),
		'coupon page beyond the last is 400'
	);
	ok( 400 === call_api( 'GET', '/coupons', array( 'per_page' => 101 ) )->get_status(), 'coupon per_page over 100 is 400' );
	ok( 400 === call_api( 'GET', '/coupons', array( 'orderby' => 'bogus' ) )->get_status(), 'coupon unknown orderby is 400' );
	foreach ( array( 'used_by', 'email_restrictions', 'customer_email' ) as $private ) {
		ok( ! array_key_exists( $private, $list->get_data()[0] ), "coupon has no $private" );
	}

	// The cart still understands what the API stores.
	$stored = new \OhMyLMS\Commerce\Data\Coupon( $cid );
	ok( 'flat-rate' === $stored->get_discount_type() && 12.5 === (float) $stored->get_amount(), 'cart-side Coupon reads the stored type and amount' );
	ok( ohmylms_ecommerce_get_coupon_id_by_code( $code ) === 0 || ohmylms_ecommerce_get_coupon_id_by_code( $code ) === $cid, 'code lookup is consistent' );

	// Coupons: delete.
	$response = call_api( 'DELETE', "/coupons/$cid" );
	ok( 200 === $response->get_status() && true === $response->get_data()['deleted'] && $cid === $response->get_data()['previous']['id'], 'coupon delete 200 with previous' );
	ok( null === get_post( $cid ), 'coupon removed' );
	ok( 404 === call_api( 'GET', "/coupons/$cid" )->get_status() && 404 === call_api( 'DELETE', "/coupons/$cid" )->get_status(), 'deleted coupon is 404' );
	ok( 404 === call_api( 'DELETE', "/coupons/$page" )->get_status() && null !== get_post( $page ), 'cannot delete a non-coupon' );

	// Schema.
	foreach ( array( '/orders', '/coupons' ) as $route ) {
		$options = rest_do_request( new WP_REST_Request( 'OPTIONS', $base . $route ) );
		$schema  = $options->get_data()['schema'];
		ok( 'http://json-schema.org/draft-04/schema#' === $schema['$schema'] && ! empty( $schema['properties']['id']['readonly'] ), "schema of $route" );
	}
	$schema = rest_do_request( new WP_REST_Request( 'OPTIONS', $base . '/coupons' ) )->get_data()['schema'];
	ok( array( 'percent', 'flat-rate' ) === $schema['properties']['discount_type']['enum'], 'schema lists the discount types' );

	ok( 0 === $mail_sent, 'no email was sent' );
	fwrite( STDOUT, "OK commerce-rest-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	// Remove only what this run created.
	foreach ( array_unique( array_filter( array_map( 'intval', $post_ids ) ) ) as $leftover ) {
		$order_items = $wpdb->get_col( $wpdb->prepare( "SELECT order_item_id FROM {$wpdb->prefix}ohmylms_order_items WHERE order_id = %d", $leftover ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $order_items as $item_id ) {
			$wpdb->delete( $wpdb->prefix . 'ohmylms_order_itemmeta', array( 'order_item_id' => $item_id ), array( '%d' ) );
		}
		$wpdb->delete( $wpdb->prefix . 'ohmylms_order_items', array( 'order_id' => $leftover ), array( '%d' ) );
		foreach ( get_posts(
			array(
				'post_type'      => 'ohmylms_order_refund',
				'post_status'    => 'any',
				'post_parent'    => $leftover,
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		) as $refund_id ) {
			wp_delete_post( $refund_id, true );
		}
		$wpdb->delete( $wpdb->comments, array( 'comment_post_ID' => $leftover ), array( '%d' ) );
		wp_delete_post( $leftover, true );
	}
	// Coupons or orders created by a failed run (tagged by code / name).
	foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'ohmylms_coupon' AND post_title LIKE %s", $wpdb->esc_like( strtoupper( $tag ) ) . '%' ) ) as $leftover ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_delete_post( (int) $leftover, true );
	}
	foreach ( array( 'ohmylms_lock_order-', 'ohmylms_lock_refund-' ) as $prefix ) {
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $prefix ) . '%' ) ) as $name ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( in_array( (int) substr( $name, strlen( $prefix ) ), array_map( 'intval', $post_ids ), true ) ) {
				delete_option( $name );
			}
		}
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
}

exit( $exit_code );
