<?php
/**
 * Course purchase with bonus points (`OhMyLMS\Engagement\PointPurchase`, `ohmylms/v1/point-purchases`).
 *
 * Runs against the Local site with real tables. Creates a tagged course, learners and points rows,
 * switches the Gamification add-on and the rewards purchase flag on for the run, and restores and
 * removes everything in `finally`. No gateway is contacted; mail is short-circuited.
 *
 * Usage (set OHMYLMS_POINTS_TEST=1):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/point-purchase-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_POINTS_TEST' ) ) {
	exit( "Set OHMYLMS_POINTS_TEST=1 to run the points purchase tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Engagement\Point;
use OhMyLMS\Engagement\PointPurchase;

global $wpdb;

$checks   = 0;
$tag      = 'pptest' . strtolower( wp_generate_password( 6, false, false ) );
$posts    = array();
$users    = array();
$fired    = array(
	'completed' => array(),
	'refunded'  => array(),
	'enrolled'  => 0,
);
$snapshot = array(
	'ohmylms_reward_settings' => get_option( 'ohmylms_reward_settings', null ),
	'ohmylms_integrations'    => get_option( 'ohmylms_integrations', null ),
	'ohmylms_point_settings'  => get_option( 'ohmylms_point_settings', null ),
);
$exit     = 0;

add_filter( 'pre_wp_mail', '__return_true' );
add_action(
	'ohmylms_point_purchase_completed',
	static function ( $order_id, $user_id, $course_ids, $points ) use ( &$fired ) {
		$fired['completed'][] = array( $order_id, $user_id, $course_ids, $points );
	},
	10,
	4
);
add_action(
	'ohmylms_point_purchase_refunded',
	static function ( $order_id, $user_id, $points ) use ( &$fired ) {
		$fired['refunded'][] = array( $order_id, $user_id, $points );
	},
	10,
	3
);
add_action(
	'ohmylms_after_enrolled_student',
	static function () use ( &$fired ) {
		++$fired['enrolled'];
	}
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
 * REST call.
 *
 * @param string $method Method.
 * @param string $path   Path below /ohmylms/v1.
 * @param array  $data   Body or query.
 * @return WP_REST_Response
 */
function pp_api( $method, $path, array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1' . $path );
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
 * @param int              $status   Status.
 * @param string           $code     Code.
 * @param string           $label    Label.
 */
function pp_error( $response, $status, $code, $label ) {
	$data = $response->get_data();
	ok( $status === $response->get_status() && $code === ( $data['code'] ?? '' ), "$label: expected $status $code, got " . $response->get_status() . ' ' . wp_json_encode( $data ) );
}

/**
 * Give points.
 *
 * @param int $user_id User.
 * @param int $points  Points.
 */
function pp_seed( $user_id, $points ) {
	global $wpdb, $tag;
	$wpdb->insert(
		$wpdb->prefix . 'ohmylms_user_achievement',
		array(
			'user_id'      => $user_id,
			'type'         => 'point',
			'points'       => $points,
			'reason'       => $tag . '_seed',
			'status'       => 'active',
			'date_created' => current_time( 'mysql' ),
		)
	);
}

/**
 * Rows of the points ledger for a user.
 *
 * @param int $user_id User.
 * @return array[]
 */
function pp_ledger( $user_id ) {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare( "SELECT points, reason, content_id FROM %i WHERE user_id = %d AND type = 'point' ORDER BY id", $wpdb->prefix . 'ohmylms_user_achievement', $user_id ), ARRAY_A );
}

/**
 * Create a published course.
 *
 * @param string $name  Title suffix.
 * @param int    $price Point price (0 for none).
 * @return int
 */
function pp_course( $name, $price ) {
	global $posts, $tag;
	$id = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_COURSE_CPT,
			'post_status' => 'publish',
			'post_title'  => $tag . ' ' . $name,
		)
	);
	$posts[] = $id;
	if ( $price ) {
		update_post_meta( $id, '_purchase_point', $price );
	}
	return (int) $id;
}

try {
	$admin = (int) get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	)[0];
	foreach ( array( 'buyer', 'poor', 'other' ) as $who ) {
		$users[ $who ] = wp_insert_user(
			array(
				'user_login' => $tag . '_' . $who,
				'user_pass'  => wp_generate_password( 24 ),
				'user_email' => $tag . '_' . $who . '@example.invalid',
				'role'       => 'subscriber',
			)
		);
		ok( ! is_wp_error( $users[ $who ] ), "$who created" );
	}
	$integrations                              = (array) get_option( 'ohmylms_integrations', array() );
	$integrations['gamification']['is_enable'] = true;
	update_option( 'ohmylms_integrations', $integrations );
	update_option(
		'ohmylms_reward_settings',
		array(
			'enabled'              => false,
			'purchase_with_points' => false,
		)
	);

	$course   = pp_course( 'Fifty', 50 );
	$second   = pp_course( 'Thirty', 30 );
	$cashonly = pp_course( 'Cash', 0 );
	$optout   = pp_course( 'Optout', 10 );
	update_post_meta( $optout, '_reward_disabled', 'yes' );
	pp_seed( $users['buyer'], 100 );
	pp_seed( $users['poor'], 20 );

	// Disabled rewards.
	ok( ! PointPurchase::enabled(), 'purchases off while the rewards flag is off' );
	$off = PointPurchase::quote( $users['buyer'], array( $course ) );
	ok( is_wp_error( $off ) && 'ohmylms_points_purchase_disabled' === $off->get_error_code(), 'quote refused while off' );
	update_option(
		'ohmylms_reward_settings',
		array(
			'enabled'              => true,
			'purchase_with_points' => true,
		)
	);
	ok( PointPurchase::enabled(), 'purchases on with the add-on, rewards and purchase flag' );

	// Permissions.
	wp_set_current_user( 0 );
	pp_error( pp_api( 'GET', '/point-purchases/quote', array( 'course_ids' => array( $course ) ) ), 401, 'ohmylms_points_not_logged_in', 'anonymous quote' );
	pp_error( pp_api( 'POST', '/point-purchases', array( 'course_ids' => array( $course ) ) ), 401, 'ohmylms_points_not_logged_in', 'anonymous purchase' );
	wp_set_current_user( $users['other'] );
	pp_error( pp_api( 'POST', '/point-purchases/1/refund', array( 'reason' => 'x', 'confirm' => true ) ), 403, 'ohmylms_refund_cannot_create', 'subscriber refund' );
	pp_error( pp_api( 'GET', '/point-purchases/courses/' . $course ), 403, 'ohmylms_points_cannot_edit', 'subscriber course price' );

	// Quote validation.
	wp_set_current_user( $users['buyer'] );
	$quote = pp_api( 'GET', '/point-purchases/quote', array( 'course_ids' => array( $course, $second ) ) );
	ok( 200 === $quote->get_status() && 80 === $quote->get_data()['points'] && 100 === $quote->get_data()['balance'] && true === $quote->get_data()['affordable'], 'quote sums prices: ' . wp_json_encode( $quote->get_data() ) );
	pp_error( pp_api( 'GET', '/point-purchases/quote', array( 'course_ids' => array( $cashonly ) ) ), 409, 'ohmylms_points_course_not_for_points', 'course without point price' );
	pp_error( pp_api( 'GET', '/point-purchases/quote', array( 'course_ids' => array( $optout ) ) ), 409, 'ohmylms_points_course_not_for_points', 'course opted out' );
	pp_error( pp_api( 'GET', '/point-purchases/quote', array( 'course_ids' => array( $course, $course ) ) ), 400, 'ohmylms_points_invalid_courses', 'duplicate courses' );
	pp_error( pp_api( 'GET', '/point-purchases/quote', array( 'course_ids' => array( 999999999 ) ) ), 409, 'ohmylms_points_course_not_for_points', 'unknown course' );

	// Insufficient balance: nothing written.
	wp_set_current_user( $users['poor'] );
	pp_error( pp_api( 'POST', '/point-purchases', array( 'course_ids' => array( $course ) ) ), 409, 'ohmylms_points_insufficient', 'balance too low' );
	ok( 1 === count( pp_ledger( $users['poor'] ) ), 'no debit for a refused purchase' );
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d', $wpdb->prefix . 'ohmylms_user_enrollment', $users['poor'] ) ), 'no enrolment for a refused purchase' );

	// Rollback: the enrolment insert fails inside the transaction.
	wp_set_current_user( $users['buyer'] );
	$break = static function ( $query ) use ( $wpdb ) {
		return 0 === stripos( ltrim( $query ), 'INSERT INTO `' . $wpdb->prefix . 'ohmylms_user_enrollment`' ) ? 'SELECT broken_column_for_test FROM nowhere_for_test' : $query;
	};
	add_filter( 'query', $break );
	$suppress = $wpdb->suppress_errors( true );
	$failed   = PointPurchase::purchase( $users['buyer'], array( $course ) );
	$wpdb->suppress_errors( $suppress );
	remove_filter( 'query', $break );
	ok( is_wp_error( $failed ) && 'ohmylms_points_purchase_failed' === $failed->get_error_code(), 'failed enrolment reports an error: ' . ( is_wp_error( $failed ) ? $failed->get_error_code() : wp_json_encode( $failed ) ) );
	ok( 100 === Point::get_total_points( $users['buyer'] ) && 1 === count( pp_ledger( $users['buyer'] ) ), 'rollback left the balance and ledger unchanged' );
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i p JOIN %i m ON m.post_id = p.ID AND m.meta_key = '_purchased_by' WHERE p.post_type = 'ohmylms-order' AND p.post_status <> 'trash' AND p.ID IN ( SELECT post_id FROM %i WHERE meta_key = '_student_id' AND meta_value = %s )", $wpdb->posts, $wpdb->postmeta, $wpdb->postmeta, (string) $users['buyer'] ) ), 'rollback removed the points order' );
	ok( ! $fired['completed'], 'no completion hook after a rollback' );

	// Purchase through REST.
	$bought = pp_api( 'POST', '/point-purchases', array( 'course_ids' => array( $course, $second ) ) );
	ok( 201 === $bought->get_status(), 'purchase created: ' . wp_json_encode( $bought->get_data() ) );
	$result   = $bought->get_data();
	$order_id = (int) $result['order_id'];
	$posts[]  = $order_id;
	ok( 80 === $result['points'] && 20 === $result['balance'] && 20 === Point::get_total_points( $users['buyer'] ), 'balance reduced by the price' );
	$order = ohmylms_ecommerce_get_order( $order_id );
	ok( 'completed' === $order->get_status() && 0.0 === (float) $order->get_total() && 'points' === $order->get_payment_method(), 'points order completed at zero total' );
	ok( 'point' === get_post_meta( $order_id, '_purchased_by', true ) && 80 === (int) get_post_meta( $order_id, '_purchased_point', true ), 'points order record' );
	ok( 2 === count( $order->get_items() ), 'one line item per course' );
	$debits = array_values( array_filter( pp_ledger( $users['buyer'] ), static fn( $row ) => 'purchase_course' === $row['reason'] ) );
	ok( 1 === count( $debits ) && -80 === (int) $debits[0]['points'] && $order_id === (int) $debits[0]['content_id'], 'one debit row for the order' );
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT course_id, status FROM %i WHERE order_id = %d ORDER BY course_id', $wpdb->prefix . 'ohmylms_user_enrollment', $order_id ), ARRAY_A );
	ok( 2 === count( $rows ) && 'enrolled' === $rows[0]['status'] && 'enrolled' === $rows[1]['status'], 'both enrolments active after completion' );
	ok( 2 === $fired['enrolled'], 'ohmylms_after_enrolled_student fired per enrolment' );
	if ( \OhMyLMS\Student\EnrolmentSchema::exists() ) {
		$sources = $wpdb->get_results( $wpdb->prepare( 'SELECT source, source_type, source_ref, status FROM %i WHERE user_id = %d ORDER BY course_id', $wpdb->prefix . 'ohmylms_enrolments', $users['buyer'] ), ARRAY_A );
		ok( 2 === count( $sources ) && 'purchase' === $sources[0]['source'] && 'order' === $sources[0]['source_type'] && $order_id === (int) $sources[0]['source_ref'] && 'active' === $sources[0]['status'], 'projection records source purchase/order: ' . wp_json_encode( $sources ) );
	}
	ok( array( array( $order_id, $users['buyer'], array( $course, $second ), 80 ) ) === $fired['completed'], 'completion hook fired once' );

	// Already enrolled and balance now too low.
	pp_error( pp_api( 'POST', '/point-purchases', array( 'course_ids' => array( $course ) ) ), 409, 'ohmylms_points_already_enrolled', 'second purchase of the same course' );

	// Order detail carries the points summary.
	wp_set_current_user( $admin );
	$detail = rest_do_request( new WP_REST_Request( 'GET', '/ohmylms-commerce/v1/orders/' . $order_id ) );
	ok( 200 === $detail->get_status() && array(
		'points'      => 80,
		'refunded'    => false,
		'refunded_at' => null,
		'refundable'  => true,
	) === $detail->get_data()['points'], 'order detail shows the points: ' . wp_json_encode( $detail->get_data()['points'] ?? null ) );
	$money = rest_do_request(
		( static function () use ( $order_id ) {
			$r = new WP_REST_Request( 'POST', '/ohmylms-commerce/v1/orders/' . $order_id . '/refunds' );
			$r->set_header( 'Content-Type', 'application/json' );
			$r->set_body( wp_json_encode( array( 'amount' => '1', 'reason' => 'x' ) ) );
			return $r;
		} )()
	);
	ok( 409 === $money->get_status(), 'the money refund route refuses a points order' );

	// Refund guards.
	pp_error( pp_api( 'POST', '/point-purchases/' . $order_id . '/refund', array( 'reason' => 'Changed mind', 'confirm' => false ) ), 400, 'ohmylms_points_confirm_required', 'confirmation required' );
	pp_error( pp_api( 'POST', '/point-purchases/' . $order_id . '/refund', array( 'reason' => '<b></b>', 'confirm' => true ) ), 422, 'ohmylms_points_refund_reason', 'empty reason after sanitising' );
	$cash_order = new \OhMyLMS\Commerce\Data\Order();
	$cash_order->set_student_id( $users['other'] );
	$cash_order->set_payment_method( 'offline' );
	$cash_order->set_total( 10 );
	$cash_id = (int) $cash_order->save();
	$posts[] = $cash_id;
	pp_error( pp_api( 'POST', '/point-purchases/' . $cash_id . '/refund', array( 'reason' => 'x', 'confirm' => true ) ), 409, 'ohmylms_points_not_point_order', 'cash order refused' );
	pp_error( pp_api( 'POST', '/point-purchases/999999999/refund', array( 'reason' => 'x', 'confirm' => true ) ), 404, 'ohmylms_order_not_found', 'unknown order' );
	add_option( 'ohmylms_lock_point-refund-' . $order_id, time(), '', 'no' );
	pp_error( pp_api( 'POST', '/point-purchases/' . $order_id . '/refund', array( 'reason' => 'x', 'confirm' => true ) ), 409, 'ohmylms_points_refund_in_progress', 'parallel refund refused' );
	delete_option( 'ohmylms_lock_point-refund-' . $order_id );
	ok( 20 === Point::get_total_points( $users['buyer'] ), 'refused refunds changed nothing' );

	// Refund restores points once.
	$refund = pp_api( 'POST', '/point-purchases/' . $order_id . '/refund', array( 'reason' => 'Changed mind', 'confirm' => true ) );
	ok( 200 === $refund->get_status() && true === $refund->get_data()['refunded'] && 100 === $refund->get_data()['balance'], 'refund restored the points: ' . wp_json_encode( $refund->get_data() ) );
	ok( 100 === Point::get_total_points( $users['buyer'] ), 'balance back to 100' );
	clean_post_cache( $order_id );
	ok( 'refunded' === ohmylms_ecommerce_get_order( $order_id )->get_status(), 'order marked refunded' );
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE order_id = %d AND status = 'enrolled'", $wpdb->prefix . 'ohmylms_user_enrollment', $order_id ) ), 'enrolments of the order cancelled' );
	ok( array( array( $order_id, $users['buyer'], 80 ) ) === $fired['refunded'], 'refund hook fired once' );
	pp_error( pp_api( 'POST', '/point-purchases/' . $order_id . '/refund', array( 'reason' => 'Again', 'confirm' => true ) ), 409, 'ohmylms_points_already_refunded', 'second refund refused' );
	ok( 100 === Point::get_total_points( $users['buyer'] ), 'second refund restored nothing' );
	$refunds = array_values( array_filter( pp_ledger( $users['buyer'] ), static fn( $row ) => 'purchase_course_refund' === $row['reason'] ) );
	ok( 1 === count( $refunds ) && 80 === (int) $refunds[0]['points'], 'exactly one refund row' );

	// Buy again after the refund (the cancelled enrolment does not block).
	wp_set_current_user( $users['buyer'] );
	$again = PointPurchase::purchase( $users['buyer'], array( $course ) );
	ok( is_array( $again ) && 50 === Point::get_total_points( $users['buyer'] ), 'repurchase after refund works' );
	$posts[] = (int) $again['order_id'];

	// Course point price endpoint.
	wp_set_current_user( $admin );
	$price = pp_api( 'GET', '/point-purchases/courses/' . $cashonly );
	ok( 200 === $price->get_status() && 0 === $price->get_data()['point_price'] && true === $price->get_data()['points_enabled'] && true === $price->get_data()['purchase_enabled'], 'course price read' );
	$set = pp_api( 'PATCH', '/point-purchases/courses/' . $cashonly, array( 'point_price' => 15, 'points_enabled' => false ) );
	ok( 200 === $set->get_status() && 15 === $set->get_data()['point_price'] && false === $set->get_data()['points_enabled'], 'course price saved' );
	ok( 0 === PointPurchase::price( $cashonly ), 'opted-out course has no point price' );
	pp_error( pp_api( 'PATCH', '/point-purchases/courses/' . $cashonly, array( 'point_price' => -1 ) ), 400, 'rest_invalid_param', 'negative price refused' );
	pp_error( pp_api( 'PATCH', '/point-purchases/courses/' . $cashonly, array( 'regular_price' => '5' ) ), 400, 'ohmylms_settings_unknown_field', 'unknown field refused' );
	pp_error( pp_api( 'GET', '/point-purchases/courses/' . $order_id ), 404, 'ohmylms_rest_invalid_id', 'not a course' );

	// Checkout point cart goes through the service.
	wp_set_current_user( $users['buyer'] );
	$cart = \OhMyLMS\Commerce\ecommerce()->cart;
	$cart->empty_cart();
	$cart->add_to_cart( $second, 1, array( 'purchase_by' => 'point' ) );
	add_filter( 'wp_doing_ajax', '__return_true' );
	$die = static function () {
		return static function () {
			throw new RuntimeException( 'json-sent' );
		};
	};
	add_filter( 'wp_die_ajax_handler', $die );
	$method = new ReflectionMethod( \OhMyLMS\Commerce\Checkout::class, 'process_point_checkout' );
	ob_start();
	try {
		$method->invoke( \OhMyLMS\Commerce\Checkout::instance(), $users['buyer'], $cart->get_cart_contents() );
	} catch ( RuntimeException $sent ) {
		ok( 'json-sent' === $sent->getMessage(), 'checkout answered' );
	}
	$answer = json_decode( (string) ob_get_clean(), true );
	remove_filter( 'wp_die_ajax_handler', $die );
	remove_filter( 'wp_doing_ajax', '__return_true' );
	ok( 'success' === ( $answer['result'] ?? '' ) && ! empty( $answer['order_id'] ) && 20 === Point::get_total_points( $users['buyer'] ), 'checkout point cart bought through the service: ' . wp_json_encode( $answer ) );
	$posts[] = (int) $answer['order_id'];
	ok( 'point' === get_post_meta( (int) $answer['order_id'], '_purchased_by', true ), 'checkout order is a points order' );

	echo "Points purchase integration: {$checks} checks passed.\n";
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
	$user_ids = array_values( array_filter( array_map( 'intval', $users ) ) );
	foreach ( $user_ids as $user_id ) {
		$wpdb->delete( $wpdb->prefix . 'ohmylms_user_achievement', array( 'user_id' => $user_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_user_enrollment', array( 'user_id' => $user_id ) );
		if ( \OhMyLMS\Student\EnrolmentSchema::exists() ) {
			$wpdb->delete( $wpdb->prefix . 'ohmylms_enrolments', array( 'user_id' => $user_id ) );
		}
	}
	$orders = $user_ids ? $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_student_id' AND meta_value IN ('" . implode( "','", $user_ids ) . "')" ) : array();
	foreach ( array_unique( array_merge( $posts, array_map( 'intval', $orders ) ) ) as $post_id ) {
		if ( 'ohmylms-order' === get_post_type( $post_id ) ) {
			do_action( 'ohmylms_rest_delete_order', $post_id );
			$wpdb->delete( $wpdb->comments, array( 'comment_post_ID' => $post_id ) );
		}
		wp_delete_post( $post_id, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
	\OhMyLMS\Commerce\ecommerce()->cart->empty_cart();
	exit( $exit );
}
