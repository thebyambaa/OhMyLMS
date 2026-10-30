<?php
/**
 * Dashboard and analytics REST API integration test.
 *
 * Runs against a WordPress install (the Local site by default) with real database tables. It only
 * creates tagged test users, courses, orders (dated in 2001 so existing data cannot interfere),
 * enrolments and progress rows, and removes everything in `finally`. It never resets the database.
 *
 * Usage (set OHMYLMS_ANALYTICS_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/analytics-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, Generic.Formatting.MultipleStatementAlignment, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.DB.SlowDBQuery, WordPress.DB.PreparedSQLPlaceholders

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_ANALYTICS_TEST' ) ) {
	exit( "Set OHMYLMS_ANALYTICS_TEST=1 to run the analytics REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

use OhMyLMS\Analytics\Cache;
use OhMyLMS\Analytics\DateRange;

global $wpdb;

$checks   = 0;
$post_ids = array();
$user_ids = array();
$tag      = 'antest' . strtolower( wp_generate_password( 6, false, false ) );
$prefix   = $wpdb->prefix . 'ohmylms_';

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
 * Assert two numbers are equal within a cent.
 *
 * @param float  $expected Expected.
 * @param float  $actual   Actual.
 * @param string $message  Failure message.
 */
function money_is( $expected, $actual, $message ) {
	ok( abs( (float) $expected - (float) $actual ) < 0.005, $message . ' (expected ' . $expected . ', got ' . $actual . ')' );
}

/**
 * Dispatch a REST GET.
 *
 * @param string $path  Path below /ohmylms/v1.
 * @param array  $query Query parameters.
 * @return WP_REST_Response
 */
function get_api( $path, array $query = array() ) {
	$request = new WP_REST_Request( 'GET', '/ohmylms/v1' . $path );
	$request->set_query_params( $query );
	return rest_do_request( $request );
}

/**
 * Assert an error response.
 *
 * @param WP_REST_Response $response Response.
 * @param int              $status   Expected status.
 * @param string           $code     Expected error code ('' to skip).
 * @param string           $message  Failure message.
 */
function is_error( $response, $status, $code, $message ) {
	$data = $response->get_data();
	ok( $status === $response->get_status(), $message . ' status ' . $status . ' (got ' . $response->get_status() . ')' );
	if ( '' !== $code ) {
		ok( is_array( $data ) && ( $data['code'] ?? '' ) === $code, $message . ' code ' . $code . ' (got ' . ( is_array( $data ) ? ( $data['code'] ?? '?' ) : '?' ) . ')' );
	}
}

/**
 * Insert a tagged post and remember it.
 *
 * @param array $args wp_insert_post arguments.
 * @return int
 * @throws RuntimeException When the post cannot be created.
 */
function make_post( array $args ) {
	global $post_ids;
	$id = wp_insert_post( $args, true );
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( 'Could not create post: ' . $id->get_error_message() );
	}
	$post_ids[] = $id;
	return (int) $id;
}

/**
 * Insert an order with line items.
 *
 * @param string $date     Local post_date.
 * @param string $status   Order status.
 * @param float  $total    Order total.
 * @param array  $lines    course_id => line total.
 * @param string $method   Payment method id.
 * @param string $country  Country code.
 * @return int
 */
function make_order( $date, $status, $total, array $lines, $method, $country ) {
	global $wpdb, $prefix, $tag;
	$id = make_post(
		array(
			'post_type'     => 'ohmylms-order',
			'post_status'   => $status,
			'post_title'    => $tag . ' order',
			'post_date'     => $date,
			'post_date_gmt' => get_gmt_from_date( $date ),
		)
	);
	update_post_meta( $id, '_order_total', (string) $total );
	update_post_meta( $id, '_payment_method', $method );
	update_post_meta( $id, '_payment_method_title', strtoupper( $method ) );
	update_post_meta( $id, '_country', $country );
	foreach ( $lines as $course_id => $line ) {
		$wpdb->insert(
			$prefix . 'order_items',
			array(
				'order_item_name' => 'Item ' . $course_id,
				'order_item_type' => 'line_item',
				'order_id'        => $id,
			)
		);
		$item_id = (int) $wpdb->insert_id;
		$wpdb->insert(
			$prefix . 'order_itemmeta',
			array(
				'order_item_id' => $item_id,
				'meta_key'      => '_course_id',
				'meta_value'    => (string) $course_id,
			)
		);
		$wpdb->insert(
			$prefix . 'order_itemmeta',
			array(
				'order_item_id' => $item_id,
				'meta_key'      => '_line_total',
				'meta_value'    => (string) $line,
			)
		);
	}
	return $id;
}

/**
 * Insert an enrolment row.
 *
 * @param int    $user     User ID.
 * @param int    $course   Course ID.
 * @param string $status   Status.
 * @param string $progress Progress.
 * @param string $start    Start date.
 * @param string $end      End date.
 * @return int
 */
function make_enrollment( $user, $course, $status, $progress, $start, $end = '0000-00-00 00:00:00' ) {
	global $wpdb, $prefix;
	$wpdb->insert(
		$prefix . 'user_enrollment',
		array(
			'user_id'    => $user,
			'course_id'  => $course,
			'status'     => $status,
			'progress'   => $progress,
			'start_date' => $start,
			'end_date'   => $end,
		)
	);
	return (int) $wpdb->insert_id;
}

$fired_course_hook = 0;
add_action(
	'ohmylms_rest_analytics_course_data',
	static function () use ( &$fired_course_hook ) {
		++$fired_course_hook;
	}
);
// Tests read live data; the cache has its own test below.
$ttl = 0;
add_filter(
	'ohmylms_analytics_cache_ttl',
	static function () use ( &$ttl ) {
		return $ttl;
	}
);

try {
	foreach ( array( 'order_items', 'order_itemmeta', 'user_enrollment', 'user_progress', 'chapter_relationship', 'content_relationship' ) as $table ) {
		ok( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $prefix . $table ) ) ) === $prefix . $table, 'table exists: ' . $table );
	}

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

	foreach ( array( 'subscriber', 'author' ) as $role ) {
		$uid = wp_insert_user(
			array(
				'user_login' => $tag . '_' . $role,
				'user_pass'  => wp_generate_password( 24 ),
				'user_email' => $tag . '_' . $role . '@example.invalid',
				'role'       => $role,
			)
		);
		ok( ! is_wp_error( $uid ), 'temporary ' . $role . ' created' );
		$user_ids[ $role ] = $uid;
	}
	$students = array();
	foreach ( array( 'ada', 'bob', 'cy' ) as $name ) {
		$uid = wp_insert_user(
			array(
				'user_login'   => $tag . '_' . $name,
				'user_pass'    => wp_generate_password( 24 ),
				'user_email'   => $tag . '_' . $name . '@example.invalid',
				'display_name' => 'Student ' . ucfirst( $name ) . ' ' . $tag,
				'role'         => 'subscriber',
			)
		);
		ok( ! is_wp_error( $uid ), 'student ' . $name . ' created' );
		$user_ids[ 'student_' . $name ] = $uid;
		$students[ $name ]              = $uid;
	}

	// Routes and schema.
	$routes = rest_get_server()->get_routes( 'ohmylms/v1' );
	foreach ( array( '/dashboard', '/analytics/earnings', '/analytics/transactions', '/analytics/courses', '/analytics/courses/(?P<id>[\d]+)', '/analytics/courses/(?P<id>[\d]+)/students' ) as $route ) {
		ok( isset( $routes[ '/ohmylms/v1' . $route ] ), 'route registered: ' . $route );
		foreach ( $routes[ '/ohmylms/v1' . $route ] as $endpoint ) {
			ok( is_callable( $endpoint['permission_callback'] ) && '__return_true' !== $endpoint['permission_callback'], 'real permission_callback: ' . $route );
		}
	}
	ok( ! isset( $routes['/ohmylms/v1/dashboard/courses'] ), 'the recovered dashboard/courses route is gone' );

	// Fixtures: two courses with content.
	$course_a = make_post(
		array(
			'post_type'   => 'ohmylms-course',
			'post_status' => 'publish',
			'post_title'  => $tag . ' Alpha',
		)
	);
	$course_b = make_post(
		array(
			'post_type'   => 'ohmylms-course',
			'post_status' => 'publish',
			'post_title'  => $tag . ' Beta',
		)
	);
	$draft = make_post(
		array(
			'post_type'   => 'ohmylms-course',
			'post_status' => 'draft',
			'post_title'  => $tag . ' Draft',
		)
	);
	$chapter = make_post(
		array(
			'post_type'   => 'ohmylms-chapter',
			'post_status' => 'publish',
			'post_title'  => $tag . ' chapter',
		)
	);
	$lesson1 = make_post(
		array(
			'post_type'   => 'ohmylms-lesson',
			'post_status' => 'publish',
			'post_title'  => $tag . ' lesson 1',
		)
	);
	$lesson2 = make_post(
		array(
			'post_type'   => 'ohmylms-lesson',
			'post_status' => 'publish',
			'post_title'  => $tag . ' lesson 2',
		)
	);
	$quiz = make_post(
		array(
			'post_type'   => 'ohmylms-quiz',
			'post_status' => 'publish',
			'post_title'  => $tag . ' quiz',
		)
	);
	$wpdb->insert(
		$prefix . 'chapter_relationship',
		array(
			'course_id'  => $course_a,
			'chapter_id' => $chapter,
		)
	);
	foreach ( array(
		array( $lesson1, 'text' ),
		array( $lesson2, 'video' ),
		array( $quiz, 'quiz' ),
	) as $content ) {
		$wpdb->insert(
			$prefix . 'content_relationship',
			array(
				'chapter_id'   => $chapter,
				'content_id'   => $content[0],
				'content_type' => $content[1],
			)
		);
	}

	// Orders around the March 2001 range: boundaries, a partial refund, a full refund, a pending order.
	$o1 = make_order( '2001-03-05 12:00:00', 'ohmylms-completed', 100, array( $course_a => 100 ), 'stripe', 'US' );
	$o2 = make_order(
		'2001-03-05 18:00:00',
		'ohmylms-completed',
		150,
		array(
			$course_a => 50,
			$course_b => 100,
		),
		'paypal',
		'GB'
	);
	$o3 = make_order( '2001-03-31 23:59:59', 'ohmylms-refunded', 80, array( $course_b => 80 ), 'stripe', 'US' );
	make_order( '2001-04-01 00:00:00', 'ohmylms-completed', 40, array( $course_a => 40 ), 'stripe', 'US' );
	make_order( '2001-02-28 23:59:59', 'ohmylms-completed', 25, array( $course_a => 25 ), 'stripe', 'US' );
	make_order( '2001-03-10 10:00:00', 'ohmylms-pending', 999, array( $course_a => 999 ), 'stripe', 'US' );
	$refund = make_post(
		array(
			'post_type'   => 'ohmylms_order_refund',
			'post_status' => 'publish',
			'post_parent' => $o2,
			'post_title'  => $tag . ' refund',
		)
	);
	update_post_meta( $refund, '_refund_amount', '30' );
	$trashed_refund = make_post(
		array(
			'post_type'   => 'ohmylms_order_refund',
			'post_status' => 'trash',
			'post_parent' => $o1,
			'post_title'  => $tag . ' trashed refund',
		)
	);
	update_post_meta( $trashed_refund, '_refund_amount', '999' );

	// Enrolments: ada completed and bob running in A, cy banned in A, ada also in B.
	$ada_a = make_enrollment( $students['ada'], $course_a, 'enrolled', 'completed', '2001-03-02 09:00:00', '2001-03-20 09:00:00' );
	$bob_a = make_enrollment( $students['bob'], $course_a, 'enrolled', 'running', '2001-03-10 09:00:00' );
	make_enrollment( $students['cy'], $course_a, 'banned', 'running', '2001-03-12 09:00:00' );
	make_enrollment( $students['ada'], $course_b, 'enrolled', 'running', '2001-03-15 09:00:00' );
	foreach ( array(
		array( $lesson1, 'text' ),
		array( $quiz, 'quiz' ),
	) as $done ) {
		$wpdb->insert(
			$prefix . 'user_progress',
			array(
				'enrollment_id' => $bob_a,
				'content_id'    => $done[0],
				'content_type'  => $done[1],
				'status'        => 'completed',
			)
		);
	}

	$march = array(
		'range' => 'custom',
		'start' => '2001-03-01',
		'end'   => '2001-03-31',
	);

	// Permissions.
	$endpoints = array(
		'/dashboard',
		'/analytics/earnings',
		'/analytics/transactions',
		'/analytics/courses',
		'/analytics/courses/' . $course_a,
		'/analytics/courses/' . $course_a . '/students',
	);
	wp_set_current_user( 0 );
	foreach ( $endpoints as $path ) {
		is_error( get_api( $path ), 401, 'ohmylms_analytics_cannot_view', 'anonymous ' . $path );
	}
	foreach ( array( 'subscriber', 'author' ) as $role ) {
		wp_set_current_user( $user_ids[ $role ] );
		foreach ( $endpoints as $path ) {
			is_error( get_api( $path ), 403, 'ohmylms_analytics_cannot_view', $role . ' ' . $path );
		}
	}
	wp_set_current_user( $admin );

	// Date range unit checks (fixed clock).
	$clock = new DateTimeImmutable( '2026-03-15 10:00:00', wp_timezone() );
	$r     = DateRange::from_preset( 'last_30_days', '', '', $clock );
	ok( '2026-02-14' === $r->start && '2026-03-15' === $r->end && 30 === $r->days(), 'last_30_days covers 30 days ending today' );
	ok( '2026-01-15' === $r->previous()->start && '2026-02-13' === $r->previous()->end, 'previous period is the 30 days before' );
	$r = DateRange::from_preset( 'today', '', '', $clock );
	ok( '2026-03-15' === $r->start && '2026-03-15' === $r->end && 1 === $r->days(), 'today is one day' );
	$r = DateRange::from_preset( 'last_month', '', '', $clock );
	ok( '2026-02-01' === $r->start && '2026-02-28' === $r->end, 'last_month spans February' );
	$r = DateRange::from_preset( 'last_month', '', '', new DateTimeImmutable( '2024-03-01 00:00:00', wp_timezone() ) );
	ok( '2024-02-29' === $r->end, 'last_month honours leap years' );
	$r = DateRange::from_preset( 'this_month', '', '', $clock );
	ok( '2026-03-01' === $r->start && '2026-03-15' === $r->end, 'this_month runs to today' );
	$r = DateRange::from_preset( 'this_year', '', '', $clock );
	ok( '2026-01-01' === $r->start, 'this_year starts on 1 January' );
	$r = DateRange::from_preset( 'last_12_months', '', '', $clock );
	ok( '2025-04-01' === $r->start && 'month' === $r->granularity() && 12 === count( $r->bucket_keys() ), 'last_12_months is twelve monthly buckets' );
	$r = DateRange::from_preset( 'custom', '2001-03-01', '2001-03-31', $clock );
	ok( 'day' === $r->granularity() && 31 === count( $r->bucket_keys() ), 'a 31 day range has 31 daily buckets' );
	$r = DateRange::from_preset( 'custom', '2001-01-01', '2001-04-03', $clock );
	ok( 93 === $r->days() && 'month' === $r->granularity() && array( '2001-01', '2001-02', '2001-03', '2001-04' ) === $r->bucket_keys(), '93 days switch to monthly buckets' );
	$r = DateRange::from_preset( 'custom', '2001-01-01', '2001-04-02', $clock );
	ok( 92 === $r->days() && 'day' === $r->granularity(), '92 days stay daily' );
	ok( ! is_wp_error( DateRange::from_preset( 'custom', '2001-01-01', '2002-12-31', $clock ) ), '730 days are allowed' );
	ok( is_wp_error( DateRange::from_preset( 'custom', '2001-01-01', '2003-01-02', $clock ) ), '732 days are refused' );

	// Validation.
	is_error( get_api( '/dashboard', array( 'range' => 'bogus' ) ), 400, 'rest_invalid_param', 'unknown preset' );
	is_error( get_api( '/dashboard', array( 'range' => 'custom' ) ), 400, 'ohmylms_analytics_invalid_range', 'custom without dates' );
	is_error(
		get_api(
			'/dashboard',
			array(
				'range' => 'custom',
				'start' => '2001-03-31',
				'end'   => '2001-03-01',
			)
		),
		400,
		'ohmylms_analytics_invalid_range',
		'start after end'
	);
	is_error(
		get_api(
			'/dashboard',
			array(
				'range' => 'custom',
				'start' => '2001-02-30',
				'end'   => '2001-03-01',
			)
		),
		400,
		'ohmylms_analytics_invalid_range',
		'impossible calendar date'
	);
	is_error(
		get_api(
			'/dashboard',
			array(
				'range' => 'custom',
				'start' => '2001-01-01',
				'end'   => '2003-01-02',
			)
		),
		400,
		'ohmylms_analytics_invalid_range',
		'span over the maximum'
	);
	is_error(
		get_api(
			'/dashboard',
			array(
				'range' => 'custom',
				'start' => 'yesterday',
				'end'   => '2001-03-01',
			)
		),
		400,
		'rest_invalid_param',
		'malformed date'
	);
	is_error( get_api( '/analytics/transactions', array( 'per_page' => 0 ) ), 400, 'rest_invalid_param', 'per_page 0' );
	is_error( get_api( '/analytics/transactions', array( 'per_page' => 101 ) ), 400, 'rest_invalid_param', 'per_page 101' );
	is_error( get_api( '/analytics/transactions', array( 'orderby' => 'post_date; DROP TABLE x' ) ), 400, 'rest_invalid_param', 'orderby outside the allow-list' );
	is_error( get_api( '/analytics/courses', array( 'order' => 'sideways' ) ), 400, 'rest_invalid_param', 'bad order' );
	is_error( get_api( '/analytics/courses/' . $course_a . '/students', array( 'progress' => 'half' ) ), 400, 'rest_invalid_param', 'bad progress filter' );
	is_error( get_api( '/analytics/courses/0' ), 400, 'rest_invalid_param', 'course id 0' );
	is_error( get_api( '/analytics/courses/99999999' ), 404, 'ohmylms_analytics_course_not_found', 'unknown course' );
	is_error( get_api( '/analytics/courses/' . $chapter ), 404, 'ohmylms_analytics_course_not_found', 'a post that is not a course' );
	is_error( get_api( '/analytics/courses/99999999/students' ), 404, 'ohmylms_analytics_course_not_found', 'students of an unknown course' );

	// Dashboard overview for March 2001.
	$response = get_api( '/dashboard', $march );
	ok( 200 === $response->get_status(), 'dashboard 200' );
	$data = $response->get_data();
	ok( 'custom' === $data['range']['preset'] && '2001-03-01' === $data['range']['start'] && '2001-03-31' === $data['range']['end'] && 31 === $data['range']['days'] && 'day' === $data['range']['granularity'], 'range echoed' );
	ok( '2001-01-29' === $data['range']['previous_start'] && '2001-02-28' === $data['range']['previous_end'], 'previous range' );
	ok( isset( $data['currency']['code'] ) && is_int( $data['currency']['decimals'] ) && is_string( $data['currency']['symbol'] ), 'currency shape' );
	$cur = $data['summary']['current'];
	ok( 3 === $cur['orders'], 'three paid orders in March (pending, April and February excluded)' );
	money_is( 330, $cur['gross'], 'gross' );
	money_is( 110, $cur['refunds'], 'refunds = partial 30 + full 80, trashed refund ignored' );
	money_is( 220, $cur['net'], 'net' );
	ok( 4 === $cur['sales'], 'four course line items sold' );
	ok( 2 === $cur['enrollments'], 'two distinct enrolled students in March (banned excluded)' );
	$prev = $data['summary']['previous'];
	ok( 1 === $prev['orders'], 'the 28 February order is in the previous period' );
	money_is( 25, $prev['gross'], 'previous gross' );
	ok( 0 === $prev['enrollments'], 'no previous enrolments' );
	ok( 31 === count( $data['series'] ), 'one point per day' );
	$by_date = array();
	$sum     = 0;
	foreach ( $data['series'] as $point ) {
		$by_date[ $point['date'] ] = $point;
		$sum                      += $point['gross'];
	}
	ok( isset( $by_date['2001-03-01'], $by_date['2001-03-31'] ), 'series covers first and last day' );
	ok( 2 === $by_date['2001-03-05']['orders'], 'two orders on 5 March' );
	money_is( 250, $by_date['2001-03-05']['gross'], '5 March gross' );
	money_is( 30, $by_date['2001-03-05']['refunds'], '5 March refunds' );
	money_is( 80, $by_date['2001-03-31']['gross'], '31 March 23:59:59 belongs to 31 March' );
	money_is( 330, $sum, 'series gross sums to the summary gross' );
	money_is( 0, $by_date['2001-03-06']['gross'], 'empty days are zero' );
	ok( $data['totals']['courses'] >= 3 && $data['totals']['published_courses'] >= 2 && $data['totals']['students'] >= 2, 'catalogue totals count the fixtures' );
	$popular = wp_list_pluck( $data['popular_courses'], 'id' );
	ok( array( $course_a, $course_b ) === $popular, 'popular courses by enrolments in the range' );
	ok( 2 === $data['popular_courses'][0]['enrollments'] && 1 === $data['popular_courses'][1]['enrollments'], 'popular enrolment counts' );
	ok( count( $data['recent_courses'] ) <= 5 && in_array( $course_a, wp_list_pluck( $data['recent_courses'], 'id' ), true ), 'recent courses are limited to five and include the newest' );
	$recent_a = array_values( wp_filter_object_list( $data['recent_courses'], array( 'id' => $course_a ) ) )[0];
	money_is( 150, $recent_a['gross'], 'course A gross in the dashboard row' );
	ok( 'publish' === $recent_a['status'] && '' !== $recent_a['permalink'], 'published course has a permalink' );
	$recent_d = array_values( wp_filter_object_list( $data['recent_courses'], array( 'id' => $draft ) ) );
	ok( empty( $recent_d ) || '' === $recent_d[0]['permalink'], 'a draft course has no public permalink' );

	// Presets answer with the same shape; today's totals only grow by what we add now.
	foreach ( DateRange::PRESETS as $preset ) {
		if ( 'custom' === $preset ) {
			continue;
		}
		$res = get_api( '/dashboard', array( 'range' => $preset ) );
		ok( 200 === $res->get_status() && $preset === $res->get_data()['range']['preset'], 'preset ' . $preset );
	}
	$default = get_api( '/dashboard' )->get_data();
	ok( 'last_30_days' === $default['range']['preset'] && 30 === $default['range']['days'], 'default preset is last_30_days' );
	$before = get_api( '/dashboard', array( 'range' => 'today' ) )->get_data()['summary']['current'];
	make_order( current_time( 'mysql' ), 'ohmylms-completed', 12.5, array( $course_a => 12.5 ), 'stripe', 'US' );
	$after = get_api( '/dashboard', array( 'range' => 'today' ) )->get_data()['summary']['current'];
	money_is( 12.5, $after['gross'] - $before['gross'], 'a new order today raises today gross by its total' );
	ok( 1 === $after['orders'] - $before['orders'], 'and the order count by one' );

	// Hook on the overview.
	add_filter(
		'ohmylms_dashboard_data',
		static function ( $payload ) {
			$payload['extension'] = 'seen';
			return $payload;
		}
	);
	ok( 'seen' === get_api( '/dashboard', $march )->get_data()['extension'], 'ohmylms_dashboard_data filter still applies' );

	// Cache: short lived, versioned.
	$ttl = 60;
	Cache::flush();
	$cached = get_api( '/analytics/earnings', $march )->get_data();
	make_order( '2001-03-20 10:00:00', 'ohmylms-completed', 5, array( $course_a => 5 ), 'stripe', 'US' );
	$stale = get_api( '/analytics/earnings', $march )->get_data();
	ok( $stale['summary']['current']['orders'] === $cached['summary']['current']['orders'], 'a cached aggregate is served within the lifetime' );
	Cache::flush();
	$fresh = get_api( '/analytics/earnings', $march )->get_data();
	ok( 4 === $fresh['summary']['current']['orders'], 'flush() drops cached aggregates' );
	money_is( 335, $fresh['summary']['current']['gross'], 'fresh gross includes the new order' );
	$ttl = 0;
	Cache::flush();

	// Earnings report (now with the extra 5 order on 20 March).
	$earnings = get_api( '/analytics/earnings', $march )->get_data();
	ok( array( 'US', 'GB' ) === wp_list_pluck( $earnings['by_country'], 'country' ), 'countries ordered by gross' );
	money_is( 185, $earnings['by_country'][0]['gross'], 'US gross (100 + 80 + 5)' );
	ok( 3 === $earnings['by_country'][0]['orders'], 'US orders' );
	money_is( 150, $earnings['by_country'][1]['gross'], 'GB gross' );
	$methods = array();
	foreach ( $earnings['by_payment_method'] as $m ) {
		$methods[ $m['id'] ] = $m;
	}
	ok( 3 === $methods['stripe']['orders'] && 'STRIPE' === $methods['stripe']['title'] && 1 === $methods['paypal']['orders'], 'payment method breakdown' );
	ok( ! isset( $earnings['by_country'][0]['email'] ), 'breakdowns carry no customer data' );

	// Transactions.
	$tx = get_api(
		'/analytics/transactions',
		array_merge(
			$march,
			array(
				'orderby'  => 'id',
				'order'    => 'asc',
				'per_page' => 2,
			)
		)
	);
	ok( 200 === $tx->get_status(), 'transactions 200' );
	$headers = $tx->get_headers();
	ok( '4' === (string) $headers['X-WP-Total'] && '2' === (string) $headers['X-WP-TotalPages'], 'transactions pagination headers' );
	ok( isset( $headers['Link'] ) && false !== strpos( $headers['Link'], 'rel="next"' ), 'Link header names the next page' );
	$rows = $tx->get_data();
	ok( 2 === count( $rows ) && $o1 === $rows[0]['order_id'] && $o2 === $rows[1]['order_id'], 'transactions sorted by id ascending' );
	money_is( 150, $rows[1]['total'], 'order total' );
	money_is( 30, $rows[1]['refunded'], 'order refunded' );
	money_is( 120, $rows[1]['net'], 'order net' );
	ok( 'completed' === $rows[1]['status'] && 'paypal' === $rows[1]['payment_method'] && 'PAYPAL' === $rows[1]['payment_title'], 'order status and payment method' );
	ok( 2 === count( $rows[1]['items'] ) && $course_a === $rows[1]['items'][0]['course_id'], 'items of the order in one batch' );
	ok( '2001-03-05T18:00:00' === $rows[1]['date'], 'site-local ISO date' );
	money_is( 0, $rows[0]['refunded'], 'a trashed refund is ignored' );
	$page2 = get_api(
		'/analytics/transactions',
		array_merge(
			$march,
			array(
				'orderby'  => 'id',
				'order'    => 'asc',
				'per_page' => 2,
				'page'     => 2,
			)
		)
	)->get_data();
	ok( 2 === count( $page2 ) && $o3 === $page2[0]['order_id'] && 'refunded' === $page2[0]['status'], 'second page holds the remaining orders' );
	is_error(
		get_api(
			'/analytics/transactions',
			array_merge(
				$march,
				array(
					'per_page' => 2,
					'page'     => 3,
				)
			)
		),
		400,
		'ohmylms_analytics_invalid_page',
		'page beyond the last'
	);
	$paypal = get_api( '/analytics/transactions', array_merge( $march, array( 'payment_method' => 'paypal' ) ) );
	ok( 1 === count( $paypal->get_data() ) && '1' === (string) $paypal->get_headers()['X-WP-Total'], 'payment method filter' );
	$one = get_api( '/analytics/transactions', array_merge( $march, array( 'search' => (string) $o3 ) ) )->get_data();
	ok( 1 === count( $one ) && $o3 === $one[0]['order_id'] && 'refunded' === $one[0]['status'], 'search by order number' );
	money_is( 80, $one[0]['refunded'], 'a refunded order refunds its whole total' );
	$none = get_api( '/analytics/transactions', array_merge( $march, array( 'search' => "' OR 1=1 -- " ) ) );
	ok( 200 === $none->get_status() && array() === $none->get_data(), 'non-numeric search matches nothing and breaks nothing' );
	$by_total = get_api(
		'/analytics/transactions',
		array_merge(
			$march,
			array(
				'orderby' => 'total',
				'order'   => 'desc',
			)
		)
	)->get_data();
	ok( $o2 === $by_total[0]['order_id'], 'sorted by total descending' );
	$empty = get_api(
		'/analytics/transactions',
		array(
			'range' => 'custom',
			'start' => '2001-06-01',
			'end'   => '2001-06-30',
		)
	);
	ok( 200 === $empty->get_status() && array() === $empty->get_data() && '0' === (string) $empty->get_headers()['X-WP-Total'], 'an empty range is a normal empty page' );

	// Course report (collection).
	$list = get_api(
		'/analytics/courses',
		array_merge(
			$march,
			array(
				'search'  => $tag,
				'orderby' => 'title',
				'order'   => 'asc',
			)
		)
	);
	ok( 200 === $list->get_status() && '3' === (string) $list->get_headers()['X-WP-Total'], 'course report finds the three tagged courses' );
	$rows = array();
	foreach ( $list->get_data() as $row ) {
		$rows[ $row['id'] ] = $row;
	}
	ok( 3 === $rows[ $course_a ]['sales'], 'course A sales (including the 20 March order)' );
	money_is( 155, $rows[ $course_a ]['gross'], 'course A gross (50 + 100 + 5)' );
	money_is( 10, $rows[ $course_a ]['refunds'], 'course A refund is its pro rata share of the 30 refund' );
	money_is( 145, $rows[ $course_a ]['net'], 'course A net' );
	ok( 2 === $rows[ $course_a ]['enrollments'] && 1 === $rows[ $course_a ]['completed'], 'course A students and completions' );
	ok( 2 === $rows[ $course_b ]['sales'], 'course B sales' );
	money_is( 180, $rows[ $course_b ]['gross'], 'course B gross' );
	money_is( 100, $rows[ $course_b ]['refunds'], 'course B refunds (20 pro rata + 80 full)' );
	money_is( 80, $rows[ $course_b ]['net'], 'course B net' );
	ok( 0 === $rows[ $draft ]['sales'] && 0 === $rows[ $draft ]['enrollments'] && 'draft' === $rows[ $draft ]['status'], 'a draft course appears with zeros' );
	$titles = wp_list_pluck( $list->get_data(), 'title' );
	ok( array( $tag . ' Alpha', $tag . ' Beta', $tag . ' Draft' ) === $titles, 'sorted by title' );
	$by_net = get_api(
		'/analytics/courses',
		array_merge(
			$march,
			array(
				'search'   => $tag,
				'orderby'  => 'net',
				'order'    => 'desc',
				'per_page' => 1,
			)
		)
	);
	ok( $course_a === $by_net->get_data()[0]['id'] && '3' === (string) $by_net->get_headers()['X-WP-TotalPages'], 'sorted by net with paging' );
	is_error(
		get_api(
			'/analytics/courses',
			array_merge(
				$march,
				array(
					'search'   => $tag,
					'per_page' => 1,
					'page'     => 4,
				)
			)
		),
		400,
		'ohmylms_analytics_invalid_page',
		'course report page beyond the last'
	);
	$inject = get_api( '/analytics/courses', array_merge( $march, array( 'search' => "x' OR '1'='1" ) ) );
	ok( 200 === $inject->get_status() && array() === $inject->get_data(), 'search is escaped' );

	// Course report (single course).
	$single = get_api( '/analytics/courses/' . $course_a, $march );
	ok( 200 === $single->get_status(), 'course report 200' );
	$c = $single->get_data();
	ok( $course_a === $c['course']['id'] && $tag . ' Alpha' === $c['course']['title'], 'course identity' );
	ok( 1 === $c['content']['chapters'] && 2 === $c['content']['lessons'] && 1 === $c['content']['quizzes'] && 0 === $c['content']['assignments'], 'published content counts' );
	ok( 3 === $c['summary']['current']['sales'], 'course sales' );
	money_is( 155, $c['summary']['current']['gross'], 'course gross' );
	money_is( 10, $c['summary']['current']['refunds'], 'course refunds' );
	ok( 1 === $c['summary']['previous']['sales'], 'course previous sales (28 February)' );
	money_is( 25, $c['summary']['previous']['gross'], 'course previous gross' );
	ok( 2 === $c['students']['enrolled'] && 2 === $c['students']['in_range'] && 1 === $c['students']['completed'] && 1 === $c['students']['in_progress'], 'course student counts' );
	ok( 31 === count( $c['series'] ), 'course series is daily' );
	money_is( 150, array_sum( wp_list_pluck( array_slice( $c['series'], 4, 1 ), 'gross' ) ), '5 March course gross' );
	ok( $fired_course_hook >= 1, 'ohmylms_rest_analytics_course_data still fires' );
	$quiet = get_api(
		'/analytics/courses/' . $course_a,
		array(
			'range' => 'custom',
			'start' => '2001-06-01',
			'end'   => '2001-06-30',
		)
	)->get_data();
	ok( 0 === $quiet['summary']['current']['sales'] && 0 === $quiet['students']['in_range'] && 2 === $quiet['students']['enrolled'], 'a quiet range zeroes money but not the roster' );

	// Students of a course.
	$st = get_api( '/analytics/courses/' . $course_a . '/students', array( 'orderby' => 'name' ) );
	ok( 200 === $st->get_status() && '3' === (string) $st->get_headers()['X-WP-Total'], 'three enrolments incl. the banned one' );
	$s = array();
	foreach ( $st->get_data() as $row ) {
		$s[ $row['user_id'] ] = $row;
	}
	ok( 100 === $s[ $students['ada'] ]['percent_complete'] && 'completed' === $s[ $students['ada'] ]['progress'] && '2001-03-20T09:00:00' === $s[ $students['ada'] ]['completed_at'], 'completed student is 100 percent' );
	ok( 66 === $s[ $students['bob'] ]['percent_complete'] && 1 === $s[ $students['bob'] ]['lessons']['completed'] && 2 === $s[ $students['bob'] ]['lessons']['total'] && 1 === $s[ $students['bob'] ]['quizzes']['completed'], 'running student progress counters and percent' );
	ok( null === $s[ $students['bob'] ]['completed_at'] && '2001-03-10T09:00:00' === $s[ $students['bob'] ]['enrolled_at'], 'running student dates' );
	ok( 'banned' === $s[ $students['cy'] ]['status'] && 0 === $s[ $students['cy'] ]['percent_complete'], 'banned student' );
	ok( false !== strpos( $s[ $students['ada'] ]['email'], '@example.invalid' ), 'students route carries email for administrators only' );
	$running = get_api(
		'/analytics/courses/' . $course_a . '/students',
		array(
			'progress' => 'running',
			'orderby'  => 'percent',
			'order'    => 'desc',
		)
	)->get_data();
	ok( 2 === count( $running ) && $students['bob'] === $running[0]['user_id'], 'running filter sorted by percent' );
	$done = get_api( '/analytics/courses/' . $course_a . '/students', array( 'progress' => 'completed' ) )->get_data();
	ok( 1 === count( $done ) && $students['ada'] === $done[0]['user_id'], 'completed filter' );
	$found = get_api( '/analytics/courses/' . $course_a . '/students', array( 'search' => $tag . '_bob' ) )->get_data();
	ok( 1 === count( $found ) && $students['bob'] === $found[0]['user_id'], 'search by username' );
	$paged = get_api(
		'/analytics/courses/' . $course_a . '/students',
		array(
			'per_page' => 2,
			'orderby'  => 'enrolled_at',
			'order'    => 'asc',
		)
	);
	ok( '2' === (string) $paged->get_headers()['X-WP-TotalPages'] && $students['ada'] === $paged->get_data()[0]['user_id'], 'students paging and enrolment order' );
	is_error(
		get_api(
			'/analytics/courses/' . $course_a . '/students',
			array(
				'per_page' => 2,
				'page'     => 3,
			)
		),
		400,
		'ohmylms_analytics_invalid_page',
		'students page beyond the last'
	);

	// Schema via OPTIONS.
	$options = rest_do_request( new WP_REST_Request( 'OPTIONS', '/ohmylms/v1/dashboard' ) );
	$schema  = $options->get_data()['schema'] ?? array();
	ok( isset( $schema['properties']['summary'], $schema['properties']['series'], $schema['properties']['range'] ), 'dashboard schema is published' );
	$options = rest_do_request( new WP_REST_Request( 'OPTIONS', '/ohmylms/v1/analytics/earnings' ) );
	ok( isset( $options->get_data()['schema']['properties']['by_country'] ), 'earnings schema is published' );

	echo "OK: {$checks} checks passed.\n";
} catch ( Throwable $failure ) {
	echo $failure->getMessage() . "\n";
	echo $failure->getFile() . ':' . $failure->getLine() . "\n";
	$exit = 1;
} finally {
	wp_set_current_user( 0 );
	$ids = array_map( 'intval', $post_ids );
	if ( $ids ) {
		$holders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$items   = $wpdb->get_col( $wpdb->prepare( "SELECT order_item_id FROM {$prefix}order_items WHERE order_id IN ($holders)", $ids ) );
		if ( $items ) {
			$item_holders = implode( ',', array_fill( 0, count( $items ), '%d' ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$prefix}order_itemmeta WHERE order_item_id IN ($item_holders)", $items ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$prefix}order_items WHERE order_item_id IN ($item_holders)", $items ) );
		}
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$prefix}chapter_relationship WHERE course_id IN ($holders)", $ids ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$prefix}content_relationship WHERE chapter_id IN ($holders)", $ids ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$prefix}user_progress WHERE enrollment_id IN ( SELECT id FROM {$prefix}user_enrollment WHERE course_id IN ($holders) )", $ids ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$prefix}user_enrollment WHERE course_id IN ($holders)", $ids ) );
		foreach ( array_reverse( $ids ) as $post_id ) {
			wp_delete_post( $post_id, true );
		}
	}
	foreach ( $user_ids as $uid ) {
		if ( ! is_wp_error( $uid ) ) {
			wp_delete_user( (int) $uid );
		}
	}
	Cache::flush();
	if ( isset( $exit ) ) {
		exit( $exit );
	}
}
