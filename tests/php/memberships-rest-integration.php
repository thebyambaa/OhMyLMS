<?php
/**
 * Memberships REST API integration test.
 *
 * Runs against a WordPress install (the Local site by default) with real database tables. It only
 * creates tagged test plans, courses, member rows and temporary users, and removes everything in
 * `finally`. The shutdown course-sync that touches every plan is detached, so no other plan or
 * enrolment on the site is modified.
 *
 * Usage (set OHMYLMS_MEMBERSHIPS_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/memberships-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, Generic.Formatting.MultipleStatementAlignment, WordPress.DB.DirectDatabaseQuery

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_MEMBERSHIPS_TEST' ) ) {
	exit( "Set OHMYLMS_MEMBERSHIPS_TEST=1 to run the memberships REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

global $wpdb;

// Do not let the shutdown sync touch plans this test does not own.
remove_action( 'shutdown', array( \OhMyLMS\Membership\CourseSelection::class, 'flush' ) );

$checks     = 0;
$plan_ids   = array();
$course_ids = array();
$user_ids   = array();
$tag        = 'mbtest' . strtolower( wp_generate_password( 6, false, false ) );
$fired      = array(
	'insert'  => 0,
	'created' => 0,
	'deleted' => 0,
	'courses' => array(),
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
 * @param string $path   Path below /ohmylms/v1/memberships.
 * @param array  $data   Body (JSON) or query (GET).
 * @return WP_REST_Response
 */
function call_api( $method, $path = '', array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/memberships' . $path );
	if ( 'GET' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

/**
 * Create a plan and remember it for cleanup.
 *
 * @param array $data Payload.
 * @return array Response data.
 * @throws RuntimeException When creation fails.
 */
function make_plan( array $data ) {
	global $plan_ids;
	$response = call_api( 'POST', '', $data );
	ok( 201 === $response->get_status(), 'plan created: ' . wp_json_encode( $response->get_data() ) );
	$plan_ids[] = $response->get_data()['id'];
	return $response->get_data();
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
 * Insert a test course.
 *
 * @param string $title  Title.
 * @param string $status Post status.
 * @return int
 */
function make_course( $title, $status = 'publish' ) {
	global $course_ids;
	$id = wp_insert_post(
		array(
			'post_type'   => 'ohmylms-course',
			'post_title'  => $title,
			'post_status' => $status,
		)
	);
	ok( $id && ! is_wp_error( $id ), 'course created' );
	$course_ids[] = $id;
	return $id;
}

foreach ( array(
	'ohmylms_rest_insert_membership' => 'insert',
	'ohmylms_membership_created'     => 'created',
	'ohmylms_rest_delete_membership' => 'deleted',
) as $hook => $key ) {
	add_action(
		$hook,
		static function () use ( &$fired, $key ) {
			++$fired[ $key ];
		}
	);
}
add_action(
	'ohmylms_rest_after_adding_products_on_membership',
	static function ( $products, $id ) use ( &$fired ) {
		$fired['courses'][] = array(
			'id'    => $id,
			'count' => count( $products ),
		);
	},
	5,
	2
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
			'user_email' => $tag . '@example.invalid',
			'role'       => 'subscriber',
		)
	);
	ok( ! is_wp_error( $subscriber ), 'temporary subscriber created' );
	$user_ids[] = $subscriber;

	$member = wp_insert_user(
		array(
			'user_login'   => $tag . '_member',
			'user_pass'    => wp_generate_password( 24 ),
			'user_email'   => $tag . '_member@example.invalid',
			'display_name' => $tag . ' Member',
			'role'         => 'subscriber',
		)
	);
	ok( ! is_wp_error( $member ), 'temporary member created' );
	$user_ids[] = $member;

	// Routes and permission callbacks.
	$routes = rest_get_server()->get_routes( 'ohmylms/v1' );
	foreach ( array( '/memberships', '/memberships/(?P<id>[\d]+)', '/memberships/(?P<id>[\d]+)/duplicate', '/memberships/(?P<id>[\d]+)/members', '/memberships/options', '/memberships/courses' ) as $route ) {
		ok( isset( $routes[ '/ohmylms/v1' . $route ] ), 'route registered: ' . $route );
		foreach ( $routes[ '/ohmylms/v1' . $route ] as $endpoint ) {
			ok( is_callable( $endpoint['permission_callback'] ) && '__return_true' !== $endpoint['permission_callback'], "real permission_callback on $route" );
		}
	}
	ok( ! isset( $routes['/ohmylms/v1/membership'] ), 'the old singular route is gone' );

	$denied = array(
		array( 'GET', '' ),
		array( 'POST', '' ),
		array( 'GET', '/options' ),
		array( 'GET', '/courses' ),
		array( 'GET', '/1' ),
		array( 'PUT', '/1' ),
		array( 'PATCH', '/1' ),
		array( 'DELETE', '/1' ),
		array( 'POST', '/1/duplicate' ),
		array( 'GET', '/1/members' ),
	);

	// Anonymous: 401. Subscriber: 403. Neither writes anything.
	foreach ( array(
		0           => 401,
		$subscriber => 403,
	) as $user => $status ) {
		wp_set_current_user( $user );
		foreach ( $denied as $case ) {
			$response = call_api( $case[0], $case[1], array( 'name' => $tag . ' denied' ) );
			ok( $status === $response->get_status(), "user $user {$case[0]} {$case[1]} is $status, got " . $response->get_status() );
		}
	}
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'ohmylms-membership' AND post_title = %s", $tag . ' denied' ) ), 'forbidden create wrote nothing' );

	wp_set_current_user( $admin );

	// Options.
	$options = call_api( 'GET', '/options' )->get_data();
	ok( get_ohmylms_currency() === $options['currency']['code'], 'currency comes from the site settings' );
	ok( ohmylms_get_price_decimals() === $options['currency']['decimals'] && '' !== $options['currency']['position'], 'currency decimals and position' );
	ok( array( 'draft', 'publish' ) === wp_list_pluck( $options['statuses'], 'value' ), 'statuses' );
	ok( in_array( 'one_time', wp_list_pluck( $options['billing_periods'], 'value' ), true ) && 500 === $options['limits']['max_courses'], 'billing periods and limits' );

	// Create with only a name: safe defaults.
	$alpha = make_plan( array( 'name' => $tag . ' alpha' ) );
	ok( 'draft' === $alpha['status'] && '0.00' === $alpha['regular_price'] && null === $alpha['sale_price'] && 'one_time' === $alpha['billing_period'] && 1 === $alpha['billing_interval'], 'defaults: draft, 0.00, no sale, one time' );
	ok( array() === $alpha['course_ids'] && 0 === $alpha['member_count'] && 0 === $alpha['course_count'] && false === $alpha['all_learning_content'], 'defaults: no courses or members' );
	ok( get_ohmylms_currency() === $alpha['currency'] && '' !== $alpha['permalink'] && '' !== $alpha['created_at'], 'currency, permalink, created_at' );
	$created    = call_api( 'POST', '', array( 'name' => $tag . ' header' ) );
	$plan_ids[] = $created->get_data()['id'];
	ok( rest_url( 'ohmylms/v1/memberships/' . $created->get_data()['id'] ) === $created->get_headers()['Location'], 'Location header' );
	ok( $fired['insert'] >= 2 && $fired['created'] >= 2, 'create hooks fired' );
	ok( count( $fired['courses'] ) >= 2, 'ohmylms_rest_after_adding_products_on_membership fired on create' );
	ok( '0.00' === (string) get_post_meta( $alpha['id'], '_regular_price', true ), 'regular price stored as a fixed decimal' );

	// Validation. None of these may write.
	ok_error( call_api( 'POST', '', array() ), 400, 'rest_missing_callback_param', 'name required' );
	ok_error( call_api( 'POST', '', array( 'name' => '   ' ) ), 422, 'ohmylms_membership_invalid_name', 'blank name' );
	ok_error( call_api( 'POST', '', array( 'name' => str_repeat( 'x', 201 ) ) ), 400, 'rest_invalid_param', 'name too long' );
	$base = array( 'name' => $tag . ' s' );
	ok_error( call_api( 'POST', '', $base + array( 'status' => 'trash' ) ), 400, 'rest_invalid_param', 'status enum' );
	ok_error( call_api( 'POST', '', $base + array( 'regular_price' => '-5' ) ), 400, 'rest_invalid_param', 'negative price' );
	ok_error( call_api( 'POST', '', $base + array( 'regular_price' => 'abc' ) ), 400, 'rest_invalid_param', 'non numeric price' );
	ok_error( call_api( 'POST', '', $base + array( 'regular_price' => '10.001' ) ), 422, 'ohmylms_membership_invalid_price', 'too many decimals' );
	ok_error( call_api( 'POST', '', $base + array( 'regular_price' => '100000000' ) ), 400, 'rest_invalid_param', 'price above the maximum' );
	ok_error(
		call_api(
			'POST',
			'',
			$base + array(
				'regular_price' => '10',
				'sale_price'    => '11',
			)
		),
		422,
		'ohmylms_membership_sale_price_too_high',
		'sale above regular'
	);
	ok_error(
		call_api(
			'POST',
			'',
			$base + array(
				'regular_price'   => '10',
				'sale_price'      => '5',
				'sale_start_date' => '2026-12-31',
				'sale_end_date'   => '2026-12-01',
			)
		),
		422,
		'ohmylms_membership_invalid_sale_dates',
		'dates out of order'
	);
	ok_error(
		call_api(
			'POST',
			'',
			$base + array(
				'regular_price'   => '10',
				'sale_price'      => '5',
				'sale_start_date' => '2026-02-30',
				'sale_end_date'   => '2026-12-01',
			)
		),
		422,
		'ohmylms_membership_invalid_sale_dates',
		'impossible date'
	);
	ok_error(
		call_api(
			'POST',
			'',
			$base + array(
				'regular_price'   => '10',
				'sale_start_date' => '2026-12-01',
				'sale_end_date'   => '2026-12-31',
			)
		),
		422,
		'ohmylms_membership_sale_dates_without_price',
		'dates without a sale price'
	);
	ok_error(
		call_api(
			'POST',
			'',
			$base + array(
				'regular_price' => '10',
				'sale_price'    => '5',
				'sale_end_date' => '2026-12-31',
			)
		),
		422,
		'ohmylms_membership_sale_dates_incomplete',
		'half a schedule'
	);
	ok_error(
		call_api(
			'POST',
			'',
			$base + array(
				'regular_price' => '10',
				'sale_price'    => '0',
			)
		),
		422,
		'ohmylms_membership_free_needs_end',
		'free promotion needs an end'
	);
	ok_error( call_api( 'POST', '', $base + array( 'billing_period' => 'decade' ) ), 400, 'rest_invalid_param', 'billing period enum' );
	ok_error( call_api( 'POST', '', $base + array( 'billing_interval' => 0 ) ), 400, 'rest_invalid_param', 'billing interval minimum' );
	ok_error( call_api( 'POST', '', $base + array( 'course_ids' => array( 1, 1 ) ) ), 400, 'rest_invalid_param', 'duplicate course ids' );
	ok_error( call_api( 'POST', '', $base + array( 'course_ids' => range( 1, 501 ) ) ), 400, 'rest_invalid_param', 'too many courses' );
	ok_error( call_api( 'POST', '', $base + array( 'curriculum_item_ids' => array( 999999999 ) ) ), 422, 'ohmylms_membership_invalid_curriculum', 'unknown curriculum item' );
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'ohmylms-membership' AND post_title = %s", $tag . ' s' ) ), 'rejected creates wrote nothing' );

	// Pricing and the sale schedule round trip.
	$tomorrow  = wp_date( 'Y-m-d', time() + DAY_IN_SECONDS );
	$yesterday = wp_date( 'Y-m-d', time() - DAY_IN_SECONDS );
	$priced    = make_plan(
		array(
			'name'             => $tag . ' priced',
			'status'           => 'publish',
			'regular_price'    => '29.5',
			'sale_price'       => '19',
			'sale_start_date'  => $yesterday,
			'sale_end_date'    => $tomorrow,
			'sign_up_fee'      => '5',
			'billing_period'   => 'month',
			'billing_interval' => 3,
			'description'      => '<p>Hello</p><script>alert(1)</script>',
		)
	);
	ok( '29.50' === $priced['regular_price'] && '19.00' === $priced['sale_price'] && '5.00' === $priced['sign_up_fee'], 'prices normalised to fixed decimals' );
	ok( $yesterday === $priced['sale_start_date'] && $tomorrow === $priced['sale_end_date'], 'sale dates round trip in the site timezone' );
	ok( '19.00' === $priced['price'], 'the sale price applies inside the window' );
	ok( 'month' === $priced['billing_period'] && 3 === $priced['billing_interval'] && 'publish' === $priced['status'], 'billing and status saved' );
	ok( false === strpos( $priced['description'], '<script' ) && false !== strpos( $priced['description'], '<p>Hello</p>' ), 'description is sanitised' );
	$slug = $priced['slug'];

	$later = call_api(
		'PATCH',
		'/' . $priced['id'],
		array(
			'sale_start_date' => wp_date( 'Y-m-d', time() + 5 * DAY_IN_SECONDS ),
			'sale_end_date'   => wp_date( 'Y-m-d', time() + 9 * DAY_IN_SECONDS ),
		)
	)->get_data();
	ok( '29.50' === $later['price'], 'outside the window the regular price applies' );

	// Partial update keeps everything else; the slug is stable across a rename.
	$renamed = call_api( 'PATCH', '/' . $priced['id'], array( 'name' => $tag . ' priced renamed' ) );
	ok( 200 === $renamed->get_status() && $slug === $renamed->get_data()['slug'], 'rename keeps the slug' );
	$kept = call_api( 'PUT', '/' . $priced['id'], array( 'status' => 'draft' ) )->get_data();
	ok( 'draft' === $kept['status'] && '29.50' === $kept['regular_price'] && '19.00' === $kept['sale_price'] && 'month' === $kept['billing_period'] && 3 === $kept['billing_interval'], 'partial update does not reset other fields' );
	ok_error( call_api( 'PATCH', '/' . $priced['id'], array( 'regular_price' => '10' ) ), 422, 'ohmylms_membership_sale_price_too_high', 'lowering the price under the stored sale is rejected' );
	$check = call_api( 'GET', '/' . $priced['id'], array( 'context' => 'edit' ) )->get_data();
	ok( $tag . ' priced renamed' === $check['name'] && '29.50' === $check['regular_price'], 'rejected update changed nothing' );
	$cleared = call_api(
		'PATCH',
		'/' . $priced['id'],
		array(
			'sale_price'      => null,
			'sale_start_date' => null,
			'sale_end_date'   => null,
			'sign_up_fee'     => null,
		)
	)->get_data();
	ok( null === $cleared['sale_price'] && null === $cleared['sale_start_date'] && null === $cleared['sale_end_date'] && null === $cleared['sign_up_fee'] && '29.50' === $cleared['price'], 'sale and fee can be cleared' );

	// Free promotion.
	$free = make_plan(
		array(
			'name'          => $tag . ' free',
			'status'        => 'publish',
			'regular_price' => '40',
			'sale_price'    => '0',
			'sale_end_date' => $tomorrow,
		)
	);
	ok( '0.00' === $free['sale_price'] && '0.00' === $free['price'] && $tomorrow === $free['sale_end_date'] && null === $free['sale_start_date'], 'free promotion until a date' );

	// Courses: validated ids, atomic replace, exclusions.
	$c1  = make_course( $tag . ' course one' );
	$c2  = make_course( $tag . ' course two' );
	$c3  = make_course( $tag . ' course draft', 'draft' );
	$bad = $c3 + 100000;

	$with = call_api(
		'PATCH',
		'/' . $alpha['id'],
		array(
			'course_ids' => array( $c1, $c2, $c3 ),
			'status'     => 'publish',
		)
	);
	$data = $with->get_data();
	ok( 200 === $with->get_status() && array( $c1, $c2, $c3 ) === $data['course_ids'], 'courses assigned, order kept' );
	ok( array( $tag . ' course one', $tag . ' course two', $tag . ' course draft' ) === wp_list_pluck( $data['courses'], 'title' ) && 'draft' === $data['courses'][2]['status'], 'course titles and status in edit context' );
	ok( 2 === $data['course_count'], 'course_count counts only published courses' );
	$last = end( $fired['courses'] );
	ok( $alpha['id'] === $last['id'] && 2 === $last['count'], 'ohmylms_rest_after_adding_products_on_membership received the granted courses' );

	ok_error( call_api( 'PATCH', '/' . $alpha['id'], array( 'course_ids' => array( $c1, $bad ) ) ), 422, 'ohmylms_membership_invalid_courses', 'unknown course' );
	$unchanged = call_api( 'GET', '/' . $alpha['id'], array( 'context' => 'edit' ) )->get_data();
	ok( array( $c1, $c2, $c3 ) === $unchanged['course_ids'], 'a rejected replace leaves the course list unchanged' );
	ok_error( call_api( 'PATCH', '/' . $alpha['id'], array( 'course_ids' => array( (int) $alpha['id'] ) ) ), 422, 'ohmylms_membership_invalid_courses', 'a non-course post id is rejected' );
	$error_data = call_api( 'PATCH', '/' . $alpha['id'], array( 'course_ids' => array( $c1, $bad ) ) )->get_data();
	ok( array( $bad ) === $error_data['data']['invalid_ids'], 'invalid ids are reported' );

	$replaced = call_api(
		'PATCH',
		'/' . $alpha['id'],
		array(
			'course_ids'          => array( $c2 ),
			'excluded_course_ids' => array( $c1 ),
		)
	)->get_data();
	ok( array( $c2 ) === $replaced['course_ids'] && array( $c1 ) === $replaced['excluded_course_ids'] && 1 === $replaced['course_count'], 'replace and exclude' );
	ok_error( call_api( 'PATCH', '/' . $alpha['id'], array( 'course_ids' => array( $c1 ) ) ), 422, 'ohmylms_membership_course_conflict', 'included and excluded' );
	$omitted = call_api( 'PATCH', '/' . $alpha['id'], array( 'name' => $tag . ' alpha' ) )->get_data();
	ok( array( $c2 ) === $omitted['course_ids'], 'omitting course_ids keeps the courses' );
	$view = call_api( 'GET', '/' . $alpha['id'] )->get_data();
	ok( ! isset( $view['course_ids'] ) && ! isset( $view['description'] ) && isset( $view['course_count'] ), 'view context omits edit-only fields' );
	$all       = call_api( 'PATCH', '/' . $alpha['id'], array( 'all_learning_content' => true ) )->get_data();
	$published = (int) wp_count_posts( 'ohmylms-course' )->publish;
	ok( true === $all['all_learning_content'] && $published - 1 === $all['course_count'], 'all learning content counts every published course minus exclusions' );
	call_api(
		'PATCH',
		'/' . $alpha['id'],
		array(
			'all_learning_content' => false,
			'course_ids'           => array(),
		)
	);
	$emptied = call_api( 'GET', '/' . $alpha['id'], array( 'context' => 'edit' ) )->get_data();
	ok( array() === $emptied['course_ids'], 'the course list can be emptied' );

	// Members: counted in bulk, listed read-only, enrolment follows the plan, deletion is blocked.
	$beta = make_plan(
		array(
			'name'          => $tag . ' beta',
			'status'        => 'publish',
			'regular_price' => '10',
			'course_ids'    => array( $c1 ),
		)
	);
	$wpdb->insert(
		$wpdb->prefix . 'ohmylms_user_membership',
		array(
			'user_id'       => $member,
			'membership_id' => $beta['id'],
			'status'        => 'enrolled',
			'progress'      => 'running',
			'start_date'    => gmdate( 'Y-m-d H:i:s' ),
		)
	);
	$member_row = (int) $wpdb->insert_id;
	ok( $member_row > 0, 'member row inserted' );
	call_api( 'PATCH', '/' . $beta['id'], array( 'course_ids' => array( $c1, $c2 ) ) );
	$enrolled = $wpdb->get_col( $wpdb->prepare( "SELECT course_id FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE user_id = %d AND membership_id = %d AND status = 'enrolled' ORDER BY course_id", $member, $beta['id'] ) );
	ok( array( (string) $c1, (string) $c2 ) === $enrolled, 'members gain the plan courses when it is saved' );
	call_api( 'PATCH', '/' . $beta['id'], array( 'course_ids' => array( $c2 ) ) );
	$enrolled = $wpdb->get_col( $wpdb->prepare( "SELECT course_id FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE user_id = %d AND membership_id = %d AND status = 'enrolled'", $member, $beta['id'] ) );
	ok( array( (string) $c2 ) === $enrolled, 'removed courses lose the plan access' );

	$members = call_api( 'GET', '/' . $beta['id'] . '/members' );
	$rows    = $members->get_data();
	ok( 200 === $members->get_status() && '1' === $members->get_headers()['X-WP-Total'] && '1' === $members->get_headers()['X-WP-TotalPages'], 'members list with totals' );
	ok( $member === $rows[0]['user_id'] && 'enrolled' === $rows[0]['status'] && $tag . ' Member' === $rows[0]['display_name'] && $tag . '_member@example.invalid' === $rows[0]['email'] && null !== $rows[0]['start_date'] && null === $rows[0]['end_date'], 'member row shape' );
	ok( 0 === count( call_api( 'GET', '/' . $beta['id'] . '/members', array( 'status' => 'cancelled' ) )->get_data() ), 'members status filter' );
	ok_error( call_api( 'GET', '/' . $beta['id'] . '/members', array( 'page' => 2 ) ), 400, 'ohmylms_membership_invalid_page', 'members page past the end' );
	ok_error( call_api( 'GET', '/' . $beta['id'] . '/members', array( 'status' => 'bogus' ) ), 400, 'rest_invalid_param', 'members status enum' );
	ok_error( call_api( 'GET', '/999999999/members' ), 404, 'ohmylms_membership_not_found', 'members of an unknown plan' );
	ok( 1 === call_api( 'GET', '/' . $beta['id'] )->get_data()['member_count'], 'member_count on the item' );

	$list = call_api( 'GET', '', array( 'search' => $tag . ' beta' ) )->get_data();
	ok( 1 === count( $list ) && 1 === $list[0]['member_count'], 'member_count in the list' );

	ok_error( call_api( 'DELETE', '/' . $beta['id'] ), 409, 'ohmylms_membership_has_members', 'delete blocked by members' );
	ok( null !== get_post( $beta['id'] ), 'plan still exists' );

	// Duplicate.
	$copy_response = call_api( 'POST', '/' . $beta['id'] . '/duplicate' );
	$copy          = $copy_response->get_data();
	$plan_ids[]    = $copy['id'];
	ok( 201 === $copy_response->get_status() && $copy['id'] !== $beta['id'] && $tag . ' beta (copy)' === $copy['name'] && 'draft' === $copy['status'], 'duplicate is a draft copy' );
	ok( array( $c2 ) === $copy['course_ids'] && '10.00' === $copy['regular_price'] && 0 === $copy['member_count'], 'duplicate keeps courses and price, not members' );
	ok_error( call_api( 'POST', '/999999999/duplicate' ), 404, 'ohmylms_membership_not_found', 'duplicate unknown plan' );

	// Delete: after the member is cancelled it succeeds, with hooks, and is gone.
	$wpdb->update( $wpdb->prefix . 'ohmylms_user_membership', array( 'status' => 'cancelled' ), array( 'id' => $member_row ) );
	$before  = $fired['deleted'];
	$deleted = call_api( 'DELETE', '/' . $beta['id'] );
	ok( 200 === $deleted->get_status() && true === $deleted->get_data()['deleted'] && $beta['id'] === $deleted->get_data()['previous']['id'], 'delete 200 with previous' );
	ok( $fired['deleted'] === $before + 1, 'ohmylms_rest_delete_membership fired' );
	ok_error( call_api( 'GET', '/' . $beta['id'] ), 404, 'ohmylms_membership_not_found', 'deleted plan is gone' );
	ok_error( call_api( 'DELETE', '/' . $beta['id'] ), 404, 'ohmylms_membership_not_found', 'delete twice' );
	ok_error( call_api( 'GET', '/' . $c1 ), 404, 'ohmylms_membership_not_found', 'a course id is not a plan' );
	ok_error( call_api( 'PATCH', '/' . $c1, array( 'name' => 'x' ) ), 404, 'ohmylms_membership_not_found', 'update of a non-plan post' );
	ok( 'ohmylms-course' === get_post_type( $c1 ), 'the course was not modified' );

	// List: paging, headers, filters, ordering, errors.
	for ( $i = 1; $i <= 3; $i++ ) {
		make_plan(
			array(
				'name'   => $tag . ' page ' . $i,
				'status' => 2 === $i ? 'draft' : 'publish',
			)
		);
	}
	$query = array(
		'search'   => $tag . ' page',
		'per_page' => 2,
		'orderby'  => 'name',
		'order'    => 'asc',
	);
	$page  = call_api( 'GET', '', $query );
	ok( 200 === $page->get_status() && 2 === count( $page->get_data() ) && '3' === $page->get_headers()['X-WP-Total'] && '2' === $page->get_headers()['X-WP-TotalPages'], 'paged list with totals' );
	ok( array( $tag . ' page 1', $tag . ' page 2' ) === wp_list_pluck( $page->get_data(), 'name' ), 'ordered by name ascending' );
	ok( ! empty( $page->get_headers()['Link'] ) && false !== strpos( $page->get_headers()['Link'], 'rel="next"' ), 'Link header' );
	$page2 = call_api( 'GET', '', $query + array( 'page' => 2 ) );
	ok( array( $tag . ' page 3' ) === wp_list_pluck( $page2->get_data(), 'name' ), 'second page' );
	ok_error( call_api( 'GET', '', $query + array( 'page' => 3 ) ), 400, 'ohmylms_membership_invalid_page', 'page past the end' );
	ok(
		1 === count(
			call_api(
				'GET',
				'',
				array(
					'search' => $tag . ' page',
					'status' => 'draft',
				)
			)->get_data()
		),
		'status filter'
	);
	$included = call_api( 'GET', '', array( 'include' => array( $alpha['id'] ) ) )->get_data();
	ok( 1 === count( $included ) && $alpha['id'] === $included[0]['id'], 'include filter' );
	ok_error( call_api( 'GET', '', array( 'per_page' => 101 ) ), 400, 'rest_invalid_param', 'per_page above 100' );
	ok_error( call_api( 'GET', '', array( 'per_page' => 0 ) ), 400, 'rest_invalid_param', 'per_page zero' );
	ok_error( call_api( 'GET', '', array( 'page' => 0 ) ), 400, 'rest_invalid_param', 'page zero' );
	ok_error( call_api( 'GET', '', array( 'orderby' => 'meta_value' ) ), 400, 'rest_invalid_param', 'orderby enum' );
	ok_error( call_api( 'GET', '', array( 'status' => 'trash' ) ), 400, 'rest_invalid_param', 'status enum on the list' );
	$empty = call_api( 'GET', '', array( 'search' => $tag . ' no such plan' ) );
	ok( 200 === $empty->get_status() && array() === $empty->get_data() && '0' === $empty->get_headers()['X-WP-TotalPages'], 'empty result is 200 with zero pages' );

	// Course search for the picker: bounded.
	$found = call_api(
		'GET',
		'/courses',
		array(
			'search'   => $tag . ' course',
			'per_page' => 2,
		)
	);
	ok( 200 === $found->get_status() && 2 === count( $found->get_data() ) && '3' === $found->get_headers()['X-WP-Total'] && '2' === $found->get_headers()['X-WP-TotalPages'], 'course search is paged' );
	ok( array( 'id', 'title', 'status' ) === array_keys( $found->get_data()[0] ), 'course choice shape' );
	ok_error( call_api( 'GET', '/courses', array( 'per_page' => 51 ) ), 400, 'rest_invalid_param', 'course per_page above 50' );
	ok( 2 === count( call_api( 'GET', '/courses', array( 'include' => array( $c1, $c3 ) ) )->get_data() ), 'courses by id' );

	// Extension settings: declared per context in PHP, validated before anything is written.
	$ext    = $tag . '-benefit';
	$course = $tag . '-course-only';
	ohmylms_register_extension_settings(
		$ext,
		array(
			'contexts' => array( 'membership' ),
			'schema'   => array(
				'type'       => 'object',
				'properties' => array(
					'benefit' => array(
						'type'      => 'string',
						'maxLength' => 20,
					),
					'seats'   => array(
						'type'    => 'integer',
						'minimum' => 0,
					),
				),
			),
			'sanitize' => static function ( $value ) {
				if ( isset( $value['benefit'] ) ) {
					$value['benefit'] = trim( $value['benefit'] );
				}
				return 'blocked' === ( $value['benefit'] ?? '' ) ? new WP_Error( 'acme_blocked', 'This benefit is not allowed.' ) : $value;
			},
		)
	);
	ohmylms_register_extension_settings(
		$course,
		array(
			'contexts' => array( 'course' ),
			'schema'   => array( 'type' => 'object' ),
		)
	);
	$threw = false;
	try {
		ohmylms_register_extension_settings(
			$tag . '-bad-sanitize',
			array(
				'contexts' => array( 'membership' ),
				'schema'   => array( 'type' => 'object' ),
				'sanitize' => 'ohmylms_no_such_function',
			)
		);
	} catch ( InvalidArgumentException $e ) {
		$threw = true;
	}
	ok( $threw, 'a sanitize option that is not callable is refused' );
	ok( ! isset( \OhMyLMS\Extensions\Settings::manifest()[ $ext ]['sanitize'] ), 'the public manifest carries no sanitize callback' );

	$ext_options = call_api( 'GET', '/options' )->get_data();
	ok( in_array( $ext, $ext_options['extension_settings'], true ) && ! in_array( $course, $ext_options['extension_settings'], true ), 'options list only the namespaces registered for memberships' );

	$gamma = make_plan(
		array(
			'name'               => $tag . ' gamma',
			'regular_price'      => '10',
			'extension_settings' => array(
				$ext => array(
					'benefit' => '  Tutoring  ',
					'seats'   => 3,
				),
			),
		)
	);
	ok(
		array(
			'benefit' => 'Tutoring',
			'seats'   => 3,
		) === $gamma['extension_settings'][ $ext ],
		'create stores settings through the schema and the sanitize callback'
	);
	$gamma_view = call_api( 'GET', '/' . $gamma['id'] )->get_data();
	ok( ! array_key_exists( 'extension_settings', $gamma_view ), 'view context omits extension settings' );
	$gamma_edit = call_api( 'GET', '/' . $gamma['id'], array( 'context' => 'edit' ) )->get_data();
	ok( 'Tutoring' === $gamma_edit['extension_settings'][ $ext ]['benefit'], 'edit context reads extension settings back' );
	$listed = call_api(
		'GET',
		'',
		array(
			'include' => array( $gamma['id'] ),
			'context' => 'edit',
		)
	)->get_data();
	ok( 3 === $listed[0]['extension_settings'][ $ext ]['seats'], 'edit-context list includes extension settings' );

	// Values stored by a namespace that is no longer registered are kept but not exposed.
	$stored            = get_post_meta( $gamma['id'], '_ohmylms_extension_settings', true );
	$stored['retired'] = array( 'kept' => true );
	update_post_meta( $gamma['id'], '_ohmylms_extension_settings', $stored );
	$renamed_gamma = call_api( 'PATCH', '/' . $gamma['id'], array( 'name' => $tag . ' gamma renamed' ) )->get_data();
	ok( ! isset( $renamed_gamma['extension_settings']['retired'] ) && 'Tutoring' === $renamed_gamma['extension_settings'][ $ext ]['benefit'], 'omitting extension_settings keeps them; unregistered namespaces are hidden' );
	$replaced_ext = call_api( 'PATCH', '/' . $gamma['id'], array( 'extension_settings' => array( $ext => array( 'seats' => 5 ) ) ) )->get_data();
	ok( array( 'seats' => 5 ) === $replaced_ext['extension_settings'][ $ext ], 'a namespace is replaced as a whole' );
	ok( array( 'kept' => true ) === get_post_meta( $gamma['id'], '_ohmylms_extension_settings', true )['retired'], 'other namespaces survive an update' );

	$snapshot = get_post_meta( $gamma['id'], '_ohmylms_extension_settings', true );
	$rejected = call_api(
		'PATCH',
		'/' . $gamma['id'],
		array(
			'name'               => $tag . ' must not save',
			'extension_settings' => array( $ext => array( 'benefit' => str_repeat( 'x', 21 ) ) ),
		)
	);
	ok_error( $rejected, 400, 'ohmylms_membership_invalid_extension_settings', 'too long extension value' );
	ok( 'extension_settings' === $rejected->get_data()['data']['field'] && $ext === $rejected->get_data()['data']['namespace'], 'the error names the field and the extension' );
	ok_error( call_api( 'PATCH', '/' . $gamma['id'], array( 'extension_settings' => array( $ext => array( 'price' => 0 ) ) ) ), 400, 'ohmylms_membership_invalid_extension_settings', 'unknown nested property' );
	ok_error( call_api( 'PATCH', '/' . $gamma['id'], array( 'extension_settings' => array( $ext => array( 'seats' => -1 ) ) ) ), 400, 'ohmylms_membership_invalid_extension_settings', 'value below the schema minimum' );
	$unknown = call_api( 'PATCH', '/' . $gamma['id'], array( 'extension_settings' => array( $course => array( 'x' => 1 ) ) ) );
	ok_error( $unknown, 400, 'ohmylms_membership_invalid_extension_settings', 'a namespace registered for another context' );
	ok( 'ohmylms_unknown_settings' === $unknown->get_data()['data']['reason'], 'the reason is the unregistered namespace' );
	$blocked = call_api( 'PATCH', '/' . $gamma['id'], array( 'extension_settings' => array( $ext => array( 'benefit' => ' blocked ' ) ) ) );
	ok_error( $blocked, 400, 'ohmylms_membership_invalid_extension_settings', 'sanitize callback rejection' );
	ok( 'acme_blocked' === $blocked->get_data()['data']['reason'], 'the sanitize error code is reported' );
	ok_error( call_api( 'PATCH', '/' . $gamma['id'], array( 'extension_settings' => 'text' ) ), 400, 'rest_invalid_param', 'extension_settings must be an object' );
	ok_error(
		call_api(
			'PATCH',
			'/' . $gamma['id'],
			array(
				'sale_price'         => '50',
				'extension_settings' => array( $ext => array( 'seats' => 9 ) ),
			)
		),
		422,
		'ohmylms_membership_sale_price_too_high',
		'valid extension values with an invalid plan'
	);
	ok( get_post_meta( $gamma['id'], '_ohmylms_extension_settings', true ) === $snapshot && get_the_title( $gamma['id'] ) === $tag . ' gamma renamed', 'rejected requests wrote neither the plan nor its extension settings' );
	$rejected_create = call_api(
		'POST',
		'',
		array(
			'name'               => $tag . ' rejected ext',
			'extension_settings' => array( $ext => array( 'seats' => 'many' ) ),
		)
	);
	ok_error( $rejected_create, 400, 'ohmylms_membership_invalid_extension_settings', 'create with invalid extension values' );
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'ohmylms-membership' AND post_title = %s", $tag . ' rejected ext' ) ), 'a rejected create wrote no plan' );

	$generic = rest_do_request( new WP_REST_Request( 'GET', '/ohmylms/v1/extension-settings/membership/' . $gamma['id'] ) );
	ok( 200 === $generic->get_status() && 5 === ( (array) $generic->get_data()['settings'] )[ $ext ]['seats'], 'the SDK extension-settings endpoint reads the same values' );

	$gamma_copy = call_api( 'POST', '/' . $gamma['id'] . '/duplicate' )->get_data();
	$plan_ids[] = $gamma_copy['id'];
	ok( array( 'seats' => 5 ) === $gamma_copy['extension_settings'][ $ext ], 'duplicate copies extension settings' );

	wp_set_current_user( $subscriber );
	$denied_ext = call_api( 'PATCH', '/' . $gamma['id'], array( 'extension_settings' => array( $ext => array( 'seats' => 99 ) ) ) );
	ok( 403 === $denied_ext->get_status(), 'a subscriber cannot write extension settings' );
	ok( 403 === rest_do_request( new WP_REST_Request( 'GET', '/ohmylms/v1/extension-settings/membership/' . $gamma['id'] ) )->get_status(), 'a subscriber cannot read them through the SDK endpoint' );
	wp_set_current_user( 0 );
	ok( 401 === call_api( 'PATCH', '/' . $gamma['id'], array( 'extension_settings' => array( $ext => array( 'seats' => 99 ) ) ) )->get_status(), 'anonymous writes are refused' );
	wp_set_current_user( $admin );
	ok( get_post_meta( $gamma['id'], '_ohmylms_extension_settings', true ) === $snapshot, 'refused writes changed nothing' );

	// Schema.
	$schema = rest_do_request( new WP_REST_Request( 'OPTIONS', '/ohmylms/v1/memberships' ) )->get_data()['schema'];
	ok( 'http://json-schema.org/draft-04/schema#' === $schema['$schema'] && 'membership' === $schema['title'], 'schema header' );
	foreach ( array( 'id', 'name', 'slug', 'status', 'description', 'permalink', 'regular_price', 'sale_price', 'sale_start_date', 'sale_end_date', 'price', 'sign_up_fee', 'billing_period', 'billing_interval', 'currency', 'all_learning_content', 'course_ids', 'courses', 'excluded_course_ids', 'excluded_courses', 'curriculum_item_ids', 'course_count', 'member_count', 'extension_settings', 'created_at', 'updated_at' ) as $property ) {
		ok( isset( $schema['properties'][ $property ] ), "schema property $property" );
	}
	ok( ! empty( $schema['properties']['id']['readonly'] ) && ! empty( $schema['properties']['price']['readonly'] ), 'schema marks computed fields readonly' );

	fwrite( STDOUT, "OK memberships-rest-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	// Remove only what this run created.
	$plan_ids = array_map( 'intval', $plan_ids );
	$plan_ids = array_merge( $plan_ids, array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'ohmylms-membership' AND post_title LIKE %s", $wpdb->esc_like( $tag ) . '%' ) ) ) );
	foreach ( array_unique( $plan_ids ) as $leftover ) {
		$wpdb->delete( $wpdb->prefix . 'ohmylms_user_membership', array( 'membership_id' => $leftover ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_user_enrollment', array( 'membership_id' => $leftover ), array( '%d' ) );
		wp_delete_post( $leftover, true );
	}
	foreach ( $course_ids as $course_id ) {
		wp_delete_post( $course_id, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		$wpdb->delete( $wpdb->prefix . 'ohmylms_user_enrollment', array( 'user_id' => $user_id ), array( '%d' ) );
		wp_delete_user( $user_id );
	}
}

exit( isset( $exit_code ) ? $exit_code : 0 );
