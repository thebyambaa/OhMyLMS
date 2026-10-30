<?php
/**
 * Subscriptions REST API integration test (`ohmylms/v1/subscriptions`).
 *
 * Runs against a WordPress install (the Local site by default) with real database tables. It creates
 * a tagged membership plan, offline orders, subscriptions and users, and removes everything in
 * `finally` (posts, order items, notes, scheduled renewals and locks). No gateway is contacted and
 * no email is sent (wp_mail is short-circuited).
 *
 * Usage (set OHMYLMS_SUBSCRIPTIONS_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/subscriptions-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, Generic.Formatting.MultipleStatementAlignment, WordPress.DB.DirectDatabaseQuery

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_SUBSCRIPTIONS_TEST' ) ) {
	exit( "Set OHMYLMS_SUBSCRIPTIONS_TEST=1 to run the subscriptions REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Commerce\Data\Order;
use OhMyLMS\Commerce\Data\OrderItemCourse;
use OhMyLMS\Commerce\SubscriptionManager;
use OhMyLMS\Rest\V1\SubscriptionsController;

global $wpdb;

$checks    = 0;
$post_ids  = array();
$sub_ids   = array();
$user_ids  = array();
$tag       = 'sbtest' . strtolower( wp_generate_password( 6, false, false ) );
$base      = '/ohmylms/v1/subscriptions';
$mail_sent = 0;
$exit_code = 0;
$fired     = array(
	'on_hold'   => 0,
	'cancelled' => 0,
	'to_active' => 0,
	'to_cancel' => 0,
	'rest'      => array(),
);

add_filter(
	'pre_wp_mail',
	static function () use ( &$mail_sent ) {
		++$mail_sent;
		return true;
	}
);
foreach ( array(
	'ohmylms_subscription_on_hold'                    => 'on_hold',
	'ohmylms_subscription_cancelled'                  => 'cancelled',
	'ohmylms_update_subscription_status_to_active'    => 'to_active',
	'ohmylms_update_subscription_status_to_cancelled' => 'to_cancel',
) as $hook => $key ) {
	add_action(
		$hook,
		static function () use ( &$fired, $key ) {
			++$fired[ $key ];
		}
	);
}
add_action(
	'ohmylms_rest_subscription_action',
	static function ( $id, $action, $previous ) use ( &$fired ) {
		$fired['rest'][] = array( $id, $action, $previous );
	},
	10,
	3
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
 * @param string $path   Path below /ohmylms/v1/subscriptions.
 * @param array  $data   Body (JSON) or query (GET).
 * @return WP_REST_Response
 */
function call_api( $method, $path = '', array $data = array() ) {
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
 * Assert an error response.
 *
 * @param WP_REST_Response $response Response.
 * @param int              $status   Expected status.
 * @param string           $code     Expected error code.
 * @param string           $label    Description.
 */
function ok_error( $response, $status, $code, $label ) {
	$data = $response->get_data();
	$got  = isset( $data['code'] ) ? $data['code'] : '';
	ok( $status === $response->get_status() && $code === $got, "$label: expected $status $code, got " . $response->get_status() . ' ' . $got );
}

/**
 * Create an offline order with one line for the plan.
 *
 * @param int    $student Customer.
 * @param int    $plan    Membership plan.
 * @param string $status  Order status.
 * @return Order
 */
function make_order( $student, $plan, $status = 'completed' ) {
	global $post_ids, $tag;
	$order = new Order();
	$order->set_status( $status );
	$order->set_first_name( 'Ada' );
	$order->set_last_name( 'Lovelace ' . $tag );
	$order->set_email( $tag . '-buyer@example.invalid' );
	$order->set_student_id( $student );
	$order->set_currency( 'USD' );
	$order->set_payment_method( 'offline' );
	$order->set_payment_method_title( 'Offline' );
	$order->set_total( 25 );
	$item = new OrderItemCourse();
	$item->set_props(
		array(
			'name'      => 'Plan ' . $tag,
			'course_id' => $plan,
			'quantity'  => 1,
			'subtotal'  => 25,
			'total'     => 25,
		)
	);
	$order->add_item( $item );
	$post_ids[] = (int) $order->save();
	return $order;
}

/**
 * Create a subscription for an order and put it in a status.
 *
 * @param int    $student Customer.
 * @param int    $plan    Membership plan.
 * @param string $status  Subscription status.
 * @return int Subscription ID.
 */
function make_subscription( $student, $plan, $status ) {
	global $sub_ids;
	$id = SubscriptionManager::create_subscription( make_order( $student, $plan ), $plan );
	ok( is_int( $id ) && $id > 0, 'subscription created: ' . ( is_wp_error( $id ) ? $id->get_error_message() : '' ) );
	$sub_ids[] = $id;
	if ( 'pending' !== $status ) {
		ok( SubscriptionManager::update_subscription_status( $id, $status ), "fixture status $status" );
	}
	return $id;
}

/**
 * Current subscription status without the prefix, read from the database.
 *
 * @param int $id Subscription ID.
 * @return string
 */
function status_of( $id ) {
	clean_post_cache( $id );
	return substr( (string) get_post_status( $id ), 8 );
}

/**
 * Number of notes on a subscription.
 *
 * @param int $id Subscription ID.
 * @return int
 */
function note_count( $id ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_type = 'subscription_note'", $id ) );
}

/**
 * Snapshot of everything an action may change.
 *
 * @param int $id Subscription ID.
 * @return array
 */
function state_of( $id ) {
	clean_post_cache( $id );
	wp_cache_delete( $id, 'post_meta' );
	$post = get_post( $id );
	return array(
		'status'   => $post->post_status,
		'modified' => $post->post_modified,
		'meta'     => get_post_meta( $id ),
		'notes'    => note_count( $id ),
	);
}

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

	$users = array();
	foreach ( array(
		'sub' => 'subscriber',
		'con' => 'contributor',
		'edi' => 'editor',
	) as $suffix => $role ) {
		$users[ $suffix ] = wp_insert_user(
			array(
				'user_login' => $tag . '_' . $suffix,
				'user_pass'  => wp_generate_password( 24 ),
				'user_email' => $tag . '-' . $suffix . '@example.invalid',
				'role'       => $role,
			)
		);
		ok( ! is_wp_error( $users[ $suffix ] ), "temporary $role created" );
		$user_ids[] = $users[ $suffix ];
	}
	$customer = wp_insert_user(
		array(
			'user_login'   => $tag . '_customer',
			'user_pass'    => wp_generate_password( 24 ),
			'user_email'   => $tag . '-customer@example.invalid',
			'display_name' => 'Display ' . $tag,
			'first_name'   => 'Grace',
			'last_name'    => 'Hopper' . $tag,
			'role'         => 'subscriber',
		)
	);
	ok( ! is_wp_error( $customer ), 'temporary customer created' );
	$user_ids[] = $customer;

	$plan = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_MEMBERSHIP_CPT,
			'post_status' => 'publish',
			'post_title'  => 'Plan ' . $tag,
		)
	);
	ok( $plan > 0, 'membership plan created' );
	$post_ids[] = $plan;
	update_post_meta( $plan, '_regular_price', '25' );
	update_post_meta( $plan, '_subscription_period', 'month' );
	update_post_meta( $plan, '_subscription_period_interval', 1 );
	update_post_meta( $plan, '_subscription_length', '0' );

	// Routes.
	$routes = rest_get_server()->get_routes( 'ohmylms/v1' );
	foreach ( array( '', '/(?P<id>[\d]+)', '/(?P<id>[\d]+)/(?P<action>pause|resume|cancel)' ) as $route ) {
		ok( isset( $routes[ $base . $route ] ), 'route registered: ' . $route );
		foreach ( $routes[ $base . $route ] as $endpoint ) {
			ok( is_callable( $endpoint['permission_callback'] ) && '__return_true' !== $endpoint['permission_callback'], 'real permission_callback on ' . $route );
		}
	}
	ok( $routes[ $base ][0]['callback'][0] instanceof SubscriptionsController, 'list route belongs to SubscriptionsController' );
	ok( ! in_array( 'POST', array_keys( $routes[ $base ][0]['methods'] ), true ) && 1 === count( $routes[ $base ] ), 'no create route' );

	// Fixtures.
	$active    = make_subscription( $customer, $plan, 'active' );
	$on_hold   = make_subscription( $customer, $plan, 'on-hold' );
	$pending   = make_subscription( $customer, $plan, 'pending' );
	$cancelled = make_subscription( $customer, $plan, 'cancelled' );
	$expired   = make_subscription( $customer, $plan, 'expired' );
	$ended     = make_subscription( $customer, $plan, 'on-hold' );
	// `ohmylms-pending-cancel` (22 characters) does not fit post_status (varchar 20), so the
	// manager cannot store it; the API never produces it either.
	ok( ! SubscriptionManager::update_subscription_status( $pending, 'pending-cancel' ) && 'pending' === status_of( $pending ), 'pending-cancel cannot be stored' );
	update_post_meta( $ended, '_schedule_end_date', gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - DAY_IN_SECONDS ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
	$next = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + 20 * DAY_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
	update_post_meta( $on_hold, '_schedule_next_payment_date', $next );
	$renewal = make_order( $customer, $plan, 'processing' );
	update_post_meta( $renewal->get_id(), '_subscription_renewal_id', $active );

	// Anonymous, subscriber, contributor and editor: every route denied, nothing changes.
	$before = array();
	foreach ( $sub_ids as $id ) {
		$before[ $id ] = state_of( $id );
	}
	$denied = array(
		array( 'GET', '' ),
		array( 'GET', "/$active" ),
		array( 'POST', "/$active/pause" ),
		array( 'POST', "/$on_hold/resume" ),
		array( 'POST', "/$active/cancel" ),
	);
	foreach ( array(
		0             => 401,
		$users['sub'] => 403,
		$users['con'] => 403,
		$users['edi'] => 403,
	) as $who => $status ) {
		wp_set_current_user( $who );
		foreach ( $denied as $case ) {
			$response = call_api( $case[0], $case[1], 'POST' === $case[0] ? array( 'confirm' => true ) : array() );
			ok( $status === $response->get_status(), "{$case[0]} {$case[1]} as user $who: expected $status, got " . $response->get_status() );
			ok( 0 === strpos( (string) $response->get_data()['code'], 'ohmylms_subscription_cannot_' ), "{$case[0]} {$case[1]} as user $who uses the subscription error code" );
		}
	}
	foreach ( $sub_ids as $id ) {
		ok( state_of( $id ) === $before[ $id ], "denied requests changed nothing on $id" );
	}
	ok( array() === $fired['rest'] && 0 === $fired['on_hold'] && 0 === $fired['cancelled'], 'denied requests fired no hook' );

	wp_set_current_user( $admin );

	// List.
	$list = call_api(
		'GET',
		'',
		array(
			'search'   => $tag,
			'per_page' => 4,
		)
	);
	ok( 200 === $list->get_status(), 'list 200' );
	ok( '6' === $list->get_headers()['X-WP-Total'] && '2' === $list->get_headers()['X-WP-TotalPages'], 'totals: ' . wp_json_encode( $list->get_headers() ) );
	ok( 4 === count( $list->get_data() ), 'four rows on the first page' );
	$row = $list->get_data()[0];
	foreach ( array( 'id', 'status', 'plan_id', 'plan_name', 'customer_id', 'customer_name', 'start_date', 'next_payment_date', 'end_date', 'billing_period', 'billing_interval', 'recurring_amount', 'currency', 'allowed_actions' ) as $key ) {
		ok( array_key_exists( $key, $row ), "row has $key" );
	}
	foreach ( array( 'customer_email', 'payment_method', 'notes', 'related_orders', 'gateway_customer_id', 'student_email' ) as $private ) {
		ok( ! array_key_exists( $private, $row ), "row has no $private" );
	}
	ok( false === strpos( wp_json_encode( $list->get_data() ), '@example.invalid' ), 'list carries no email' );
	ok( 'Grace Hopper' . $tag === $row['customer_name'] && 'Plan ' . $tag === $row['plan_name'] && $plan === $row['plan_id'], 'row names: ' . wp_json_encode( $row ) );
	ok( '25.00' === $row['recurring_amount'] && 'USD' === $row['currency'] && 'month' === $row['billing_period'] && 1 === $row['billing_interval'], 'row money and billing' );
	ok( false !== strpos( (string) $list->get_headers()['Link'], 'rel="next"' ), 'next link' );

	$page3 = call_api(
		'GET',
		'',
		array(
			'search'   => $tag,
			'per_page' => 4,
			'page'     => 2,
		)
	);
	ok( 200 === $page3->get_status() && 2 === count( $page3->get_data() ), 'last page has two rows' );
	ok_error(
		call_api(
			'GET',
			'',
			array(
				'search'   => $tag,
				'per_page' => 4,
				'page'     => 3,
			)
		),
		400,
		'ohmylms_subscription_invalid_page',
		'page beyond the last'
	);
	ok( 400 === call_api( 'GET', '', array( 'per_page' => 101 ) )->get_status(), 'per_page over 100 is 400' );
	ok( 400 === call_api( 'GET', '', array( 'status' => 'bogus' ) )->get_status(), 'unknown status is 400' );
	ok( 400 === call_api( 'GET', '', array( 'orderby' => 'meta_value' ) )->get_status(), 'unknown orderby is 400' );
	ok( 400 === call_api( 'GET', '', array( 'search' => str_repeat( 'x', 101 ) ) )->get_status(), 'search over 100 characters is 400' );

	$by_status = call_api(
		'GET',
		'',
		array(
			'search' => $tag,
			'status' => 'on-hold',
		)
	);
	$ids       = wp_list_pluck( $by_status->get_data(), 'id' );
	sort( $ids );
	$expect = array( $on_hold, $ended );
	sort( $expect );
	ok( $expect === $ids, 'status filter: ' . wp_json_encode( $ids ) );
	foreach ( $by_status->get_data() as $item ) {
		ok( array( 'resume', 'cancel' ) === $item['allowed_actions'], 'on-hold allows resume and cancel' );
	}
	$by_email = call_api( 'GET', '', array( 'search' => $tag . '-customer@' ) );
	ok( 6 === count( $by_email->get_data() ), 'search by customer email' );
	$by_id = call_api( 'GET', '', array( 'search' => (string) $pending ) );
	ok( in_array( $pending, wp_list_pluck( $by_id->get_data(), 'id' ), true ), 'search by subscription number' );
	$order_of_pending = (int) get_post_meta( $pending, '_original_order_id', true );
	$by_order         = call_api( 'GET', '', array( 'search' => (string) $order_of_pending ) );
	ok( in_array( $pending, wp_list_pluck( $by_order->get_data(), 'id' ), true ), 'search by order number' );
	$none = call_api( 'GET', '', array( 'search' => 'nothing-' . $tag ) );
	ok( 200 === $none->get_status() && array() === $none->get_data() && '0' === $none->get_headers()['X-WP-Total'], 'no match is an empty 200' );
	$ascending = call_api(
		'GET',
		'',
		array(
			'search'  => $tag,
			'orderby' => 'id',
			'order'   => 'asc',
		)
	);
	ok( $active === $ascending->get_data()[0]['id'], 'orderby id asc' );

	// Detail.
	$detail = call_api( 'GET', "/$active" );
	$data   = $detail->get_data();
	ok( 200 === $detail->get_status() && $active === $data['id'] && 'active' === $data['status'], 'detail 200' );
	ok( $tag . '-customer@example.invalid' === $data['customer_email'] && 'Grace Hopper' . $tag === $data['customer_name'], 'detail shows the customer name and email' );
	ok( 'offline' === $data['payment_method'] && 'Offline' === $data['payment_method_title'], 'detail payment method' );
	ok( array( 'pause', 'cancel' ) === $data['allowed_actions'], 'active allows pause and cancel' );
	ok( 2 === count( $data['related_orders'] ), 'parent and renewal order: ' . wp_json_encode( $data['related_orders'] ) );
	$relationships = wp_list_pluck( $data['related_orders'], 'relationship', 'id' );
	ok( 'renewal' === $relationships[ $renewal->get_id() ] && 'parent' === $relationships[ (int) $data['original_order_id'] ], 'relationships' );
	ok( '25.00' === $data['related_orders'][0]['total'] && 'processing' === $data['related_orders'][0]['status'], 'related order money and status' );
	ok( ! empty( $data['notes'] ) && is_string( $data['notes'][0]['content'] ) && false === strpos( $data['notes'][0]['content'], '<' ), 'notes are plain text' );
	ok( 1 === preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', (string) $data['start_date'] ), 'ISO start date' );
	foreach ( array( '_gateway_payment_method_token', 'gateway_customer_id', 'student_profile', 'subscription_notes' ) as $private ) {
		ok( ! array_key_exists( $private, $data ), "detail has no $private" );
	}
	ok( isset( $detail->get_links()['self'] ), 'self link' );
	ok_error( call_api( 'GET', '/999999999' ), 404, 'ohmylms_subscription_not_found', 'unknown subscription' );
	ok_error( call_api( 'GET', '/' . $renewal->get_id() ), 404, 'ohmylms_subscription_not_found', 'an order is not a subscription' );
	ok_error( call_api( 'POST', '/' . $renewal->get_id() . '/cancel', array( 'confirm' => true ) ), 404, 'ohmylms_subscription_not_found', 'cannot cancel an order' );
	ok( 404 === call_api( 'POST', "/$active/expire", array( 'confirm' => true ) )->get_status(), 'unknown action has no route' );
	ok( 404 === call_api( 'PUT', "/$active", array( 'status' => 'cancelled' ) )->get_status(), 'no generic update route' );
	ok( 'active' === status_of( $active ), 'PUT changed nothing' );

	// Confirmation.
	$snapshot = state_of( $active );
	ok_error( call_api( 'POST', "/$active/pause" ), 400, 'ohmylms_subscription_confirm_required', 'pause without confirm' );
	ok_error( call_api( 'POST', "/$active/cancel", array( 'confirm' => false ) ), 400, 'ohmylms_subscription_confirm_required', 'cancel with confirm=false' );
	ok( 400 === call_api( 'POST', "/$active/cancel", array( 'confirm' => 'maybe' ) )->get_status(), 'confirm must be a boolean' );
	ok( state_of( $active ) === $snapshot, 'unconfirmed requests changed nothing' );

	// Invalid transitions: 409, nothing changes.
	$invalid = array(
		array( $active, 'resume' ),
		array( $on_hold, 'pause' ),
		array( $pending, 'pause' ),
		array( $pending, 'resume' ),
		array( $cancelled, 'cancel' ),
		array( $cancelled, 'resume' ),
		array( $cancelled, 'pause' ),
		array( $expired, 'cancel' ),
		array( $expired, 'resume' ),
		array( $expired, 'pause' ),
	);
	foreach ( $invalid as $case ) {
		$snapshot = state_of( $case[0] );
		$response = call_api( 'POST', "/{$case[0]}/{$case[1]}", array( 'confirm' => true ) );
		ok_error( $response, 409, 'ohmylms_subscription_invalid_transition', "{$case[1]} from " . status_of( $case[0] ) );
		ok( status_of( $case[0] ) === $response->get_data()['data']['current_status'] && is_array( $response->get_data()['data']['allowed_actions'] ), 'conflict carries the current status and allowed actions' );
		ok( state_of( $case[0] ) === $snapshot, "{$case[1]} on {$case[0]} changed nothing" );
	}
	$snapshot = state_of( $ended );
	ok_error( call_api( 'POST', "/$ended/resume", array( 'confirm' => true ) ), 409, 'ohmylms_subscription_ended', 'cannot resume past the end date' );
	ok( state_of( $ended ) === $snapshot, 'ended subscription unchanged' );
	ok( array() === $fired['rest'], 'conflicts fired no hook' );

	// A parallel request: the lock answers 409 and changes nothing.
	add_option( 'ohmylms_lock_subscription-' . $active, time(), '', 'no' );
	$snapshot = state_of( $active );
	ok_error( call_api( 'POST', "/$active/pause", array( 'confirm' => true ) ), 409, 'ohmylms_subscription_busy', 'locked subscription' );
	ok( state_of( $active ) === $snapshot, 'locked subscription unchanged' );
	delete_option( 'ohmylms_lock_subscription-' . $active );

	// Pause, resume, cancel.
	$notes    = note_count( $active );
	$response = call_api( 'POST', "/$active/pause", array( 'confirm' => true ) );
	ok( 200 === $response->get_status() && 'on-hold' === $response->get_data()['status'], 'pause 200: ' . wp_json_encode( $response->get_data() ) );
	ok( 'on-hold' === status_of( $active ) && 'on-hold' === get_post_meta( $active, '_status', true ), 'paused in the database' );
	ok( array( 'resume', 'cancel' ) === $response->get_data()['allowed_actions'], 'paused allows resume and cancel' );
	ok( 1 === $fired['on_hold'] && note_count( $active ) > $notes, 'pause fired ohmylms_subscription_on_hold and added a note' );
	ok( false === get_option( 'ohmylms_lock_subscription-' . $active ), 'lock released' );

	$response = call_api( 'POST', "/$on_hold/resume", array( 'confirm' => true ) );
	ok( 200 === $response->get_status() && 'active' === $response->get_data()['status'], 'resume 200' );
	ok( 'active' === status_of( $on_hold ) && 1 === $fired['to_active'], 'resumed and fired the legacy active hook' );
	ok( str_replace( ' ', 'T', $next ) === $response->get_data()['next_payment_date'], 'resume keeps the next payment date' );

	$response = call_api( 'POST', "/$pending/cancel", array( 'confirm' => true ) );
	ok( 200 === $response->get_status() && 'cancelled' === $response->get_data()['status'] && array() === $response->get_data()['allowed_actions'], 'cancel 200' );
	ok( 'cancelled' === status_of( $pending ) && 1 === $fired['cancelled'] && 1 === $fired['to_cancel'], 'cancelled and fired both hooks' );
	ok( null !== $response->get_data()['cancelled_date'], 'cancellation date recorded' );
	ok_error( call_api( 'POST', "/$pending/cancel", array( 'confirm' => true ) ), 409, 'ohmylms_subscription_invalid_transition', 'cancelling twice' );

	ok(
		array(
			array( $active, 'pause', 'active' ),
			array( $on_hold, 'resume', 'on-hold' ),
			array( $pending, 'cancel', 'pending' ),
		) === $fired['rest'],
		'ohmylms_rest_subscription_action fired once per change: ' . wp_json_encode( $fired['rest'] )
	);

	// Schema.
	$schema = rest_do_request( new WP_REST_Request( 'OPTIONS', $base ) )->get_data()['schema'];
	ok( 'http://json-schema.org/draft-04/schema#' === $schema['$schema'] && 'subscription' === $schema['title'], 'schema header' );
	ok( ! empty( $schema['properties']['id']['readonly'] ) && array( 'pending', 'active', 'on-hold', 'pending-cancel', 'cancelled', 'expired' ) === $schema['properties']['status']['enum'], 'schema status enum' );

	ok( 0 === $mail_sent, 'no email was sent' );
	fwrite( STDOUT, "OK subscriptions-rest-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	wp_set_current_user( 0 );
	foreach ( array_unique( array_map( 'intval', $sub_ids ) ) as $leftover ) {
		SubscriptionManager::clear_scheduled_renewal_actions( $leftover );
		delete_option( 'ohmylms_lock_subscription-' . $leftover );
		$wpdb->delete( $wpdb->comments, array( 'comment_post_ID' => $leftover ), array( '%d' ) );
		wp_delete_post( $leftover, true );
	}
	foreach ( array_unique( array_filter( array_map( 'intval', $post_ids ) ) ) as $leftover ) {
		$order_items = $wpdb->get_col( $wpdb->prepare( "SELECT order_item_id FROM {$wpdb->prefix}ohmylms_order_items WHERE order_id = %d", $leftover ) );
		foreach ( $order_items as $item_id ) {
			$wpdb->delete( $wpdb->prefix . 'ohmylms_order_itemmeta', array( 'order_item_id' => $item_id ), array( '%d' ) );
		}
		$wpdb->delete( $wpdb->prefix . 'ohmylms_order_items', array( 'order_id' => $leftover ), array( '%d' ) );
		$wpdb->delete( $wpdb->comments, array( 'comment_post_ID' => $leftover ), array( '%d' ) );
		wp_delete_post( $leftover, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
}

exit( $exit_code );
