<?php
/**
 * Courses REST API integration test.
 *
 * Runs against a WordPress install (the Local site by default) with real database tables. It only
 * creates tagged test courses, attachments, enrolment rows and temporary users, and removes everything
 * in `finally`.
 *
 * Usage (set OHMYLMS_COURSES_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/courses-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, Generic.Formatting.MultipleStatementAlignment, WordPress.DB.DirectDatabaseQuery

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_COURSES_TEST' ) ) {
	exit( "Set OHMYLMS_COURSES_TEST=1 to run the courses REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

global $wpdb;

$checks     = 0;
$course_ids = array();
$user_ids   = array();
$attachment = array();
$tag        = 'crtest' . strtolower( substr( str_shuffle( 'abcdefghijklmnopqrstuvwxyz' ), 0, 8 ) );
$fired      = array();
$baseline   = (int) $wpdb->get_var( "SELECT COALESCE(MAX(ID),0) FROM {$wpdb->posts}" );

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
 * @param string $path   Path below /ohmylms/v1/courses.
 * @param array  $data   Body (JSON) or query (GET).
 * @return WP_REST_Response
 */
function call_api( $method, $path = '', array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/courses' . $path );
	if ( 'GET' === $method || 'DELETE' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

/**
 * Create a course through the API and remember it for cleanup.
 *
 * @param array $data Payload.
 * @return array Response data.
 * @throws RuntimeException When creation fails.
 */
function make_course( array $data ) {
	global $course_ids;
	$response = call_api( 'POST', '', $data );
	ok( 201 === $response->get_status(), 'course created: ' . wp_json_encode( $response->get_data() ) );
	$course_ids[] = $response->get_data()['id'];
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
 * Create a temporary user with a role.
 *
 * @param string $tag  Test tag.
 * @param string $role Role.
 * @return int
 */
function make_user( $tag, $role ) {
	global $user_ids;
	$id = wp_insert_user(
		array(
			'user_login'   => $tag . '_' . $role . wp_generate_password( 4, false, false ),
			'user_pass'    => wp_generate_password( 24 ),
			'user_email'   => $tag . $role . wp_generate_password( 4, false, false ) . '@example.invalid',
			'display_name' => $tag . ' ' . $role,
			'role'         => $role,
		)
	);
	ok( ! is_wp_error( $id ), "temporary $role created" );
	$user_ids[] = $id;
	return $id;
}

/**
 * Create an attachment row owned by a user.
 *
 * @param int    $owner Owner ID.
 * @param string $mime  MIME type.
 * @return int
 */
function make_attachment( $owner, $mime ) {
	global $attachment;
	$id = wp_insert_attachment(
		array(
			'post_title'     => 'crtest image',
			'post_mime_type' => $mime,
			'post_status'    => 'inherit',
			'post_author'    => $owner,
			'guid'           => 'https://example.invalid/crtest-' . wp_generate_password( 6, false, false ) . '.png',
		)
	);
	ok( $id && ! is_wp_error( $id ), 'attachment created' );
	$attachment[] = $id;
	return $id;
}

foreach ( array(
	'ohmylms_rest_before_create_course',
	'ohmylms_rest_insert_course',
	'ohmylms_rest_after_create_course',
	'ohmylms_rest_before_update_course',
	'ohmylms_rest_update_course',
	'ohmylms_rest_delete_course',
	'ohmylms_new_course',
	'ohmylms_update_course',
	'ohmylms_trash_course',
	'ohmylms_delete_course',
) as $hook ) {
	$fired[ $hook ] = 0;
	add_action(
		$hook,
		static function () use ( &$fired, $hook ) {
			++$fired[ $hook ];
		},
		5,
		3
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
	$admin       = (int) $admins[0];
	$author_a    = make_user( $tag, 'author' );
	$author_b    = make_user( $tag, 'author' );
	$contributor = make_user( $tag, 'contributor' );
	$subscriber  = make_user( $tag, 'subscriber' );

	// Routes and permission callbacks.
	$routes = rest_get_server()->get_routes( 'ohmylms/v1' );
	foreach ( array( '/courses', '/courses/(?P<id>[\d]+)', '/courses/(?P<id>[\d]+)/duplicate', '/courses/(?P<id>[\d]+)/restore', '/courses/options', '/courses/export', '/courses/import', '/courses/(?P<id>\d+)/enroll', '/courses/(?P<id>\d+)/terms', '/courses/settings/(?P<id>\d+)' ) as $route ) {
		ok( isset( $routes[ '/ohmylms/v1' . $route ] ), 'route registered: ' . $route );
		foreach ( $routes[ '/ohmylms/v1' . $route ] as $endpoint ) {
			ok( is_callable( $endpoint['permission_callback'] ) && '__return_true' !== $endpoint['permission_callback'], "real permission_callback on $route" );
		}
	}
	foreach ( array( '/courses/(?P<id>\d+)/status', '/courses/trash-bulk/', '/courses/(?P<id>\d+)/clone', '/courses/featured_image/(?P<id>\d+)' ) as $gone ) {
		ok( ! isset( $routes[ '/ohmylms/v1' . $gone ] ), "removed route: $gone" );
	}

	// Anonymous callers.
	wp_set_current_user( 0 );
	$sample = 1;
	foreach ( array(
		array( 'GET', '' ),
		array( 'POST', '' ),
		array( 'GET', '/options' ),
		array( 'GET', "/$sample" ),
		array( 'PUT', "/$sample" ),
		array( 'DELETE', "/$sample" ),
		array( 'POST', "/$sample/duplicate" ),
		array( 'POST', "/$sample/restore" ),
	) as $call ) {
		$body = 'POST' === $call[0] && '' === $call[1] ? array( 'title' => 'x' ) : array();
		$resp = call_api( $call[0], $call[1], $body );
		ok( 401 === $resp->get_status(), "anonymous {$call[0]} {$call[1]} is 401, got " . $resp->get_status() );
	}

	// Subscribers.
	wp_set_current_user( $subscriber );
	ok_error( call_api( 'GET' ), 403, 'ohmylms_course_cannot_view', 'subscriber list' );
	ok_error( call_api( 'POST', '', array( 'title' => $tag . ' nope' ) ), 403, 'ohmylms_course_cannot_create', 'subscriber create' );
	ok_error( call_api( 'GET', '/options' ), 403, 'ohmylms_course_cannot_view', 'subscriber options' );
	ok_error( call_api( 'GET', '/export' ), 403, 'ohmylms_course_cannot_edit', 'subscriber legacy route' );

	// Admin: create with defaults.
	wp_set_current_user( $admin );
	$default = make_course( array( 'title' => $tag . ' Defaults' ) );
	ok( 'draft' === $default['status'] && 'beginner' === $default['level'] && 'free' === $default['price_type'] && 'public' === $default['access_type'], 'create defaults' );
	ok( '0.00' === $default['regular_price'] && '0.00' === $default['price'] && null === $default['sale_price'] && true === $default['enable_reviews'], 'free defaults' );
	ok( false === $default['has_capacity'] && null === $default['capacity'] && null === $default['duration'] && false === $default['has_password'], 'capacity, duration and password defaults' );
	ok( $admin === $default['author_id'] && 0 === $default['enrolled_count'], 'author and enrolment count' );
	$created = call_api( 'POST', '', array( 'title' => $tag . ' Defaults' ) );
	$course_ids[] = $created->get_data()['id'];
	ok( isset( $created->get_headers()['Location'] ) && str_ends_with( $created->get_headers()['Location'], '/courses/' . $created->get_data()['id'] ), 'Location header' );
	ok( $default['slug'] !== $created->get_data()['slug'] && 0 === strpos( $created->get_data()['slug'], $default['slug'] ), 'same title gets a unique slug while both are drafts' );
	ok( $fired['ohmylms_rest_insert_course'] >= 2, 'insert hook fired' );
	ok( $fired['ohmylms_new_course'] >= 2 && $fired['ohmylms_rest_before_create_course'] >= 2, 'create hooks fired' );

	// Full create and learner-side compatibility of the stored data.
	$paid = make_course(
		array(
			'title'             => $tag . ' Paid',
			'description'       => '<p>Hello <script>alert(1)</script><strong>world</strong></p>',
			'excerpt'           => 'Short',
			'level'             => 'expert',
			'price_type'        => 'paid',
			'regular_price'     => '49.5',
			'sale_price'        => '29',
			'sale_start_date'   => '2026-01-01',
			'sale_end_date'     => '2099-12-31',
			'available_date'    => '2026-02-01',
			'duration'          => array(
				'hours'   => 3,
				'minutes' => 30,
			),
			'has_capacity'      => true,
			'capacity'          => 25,
			'enable_reviews'    => false,
			'learning_outcomes' => 'Outcome',
			'status'            => 'publish',
		)
	);
	ok( '49.50' === $paid['regular_price'] && '29.00' === $paid['sale_price'] && '29.00' === $paid['price'], 'money is fixed decimal and the sale applies' );
	ok( 'publish' === $paid['status'] && 'expert' === $paid['level'] && 25 === $paid['capacity'] && false === $paid['enable_reviews'], 'saved fields' );
	ok( false === strpos( $paid['description'], '<script' ) && false !== strpos( $paid['description'], '<strong>world</strong>' ), 'description is sanitised' );
	ok( 3 === $paid['duration']['hours'] && 30 === $paid['duration']['minutes'], 'duration' );
	$course = ohmylms_get_course( $paid['id'] );
	ok( 'paid' === $course->get_price_type() && '49.50' === (string) $course->get_regular_price() && '29.00' === (string) $course->get_sale_price(), 'the learner-side Course object reads the stored prices' );
	ok( '29.00' === (string) $course->get_price() && 'expert' === $course->get_level() && 25 === (int) $course->get_capacity() && $course->get_has_capacity(), 'price, level and capacity readers' );
	ok( $course->is_on_sale() && $course->validate_on_sale(), 'sale window validates for the learner side' );
	ok( 3 === (int) $course->get_duration()['hour'] && '' !== ohmylms_format_duration( $course->get_duration() ), 'duration reader' );
	ok( ! $course->get_enable_reviews(), 'reviews flag reader' );
	ok( false !== strpos( $course->get_price_html(), '29' ), 'price html renders' );

	// Validation: nothing is written for a rejected request.
	$before = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'ohmylms-course' AND post_title LIKE %s", $wpdb->esc_like( $tag ) . '%' ) );
	ok_error( call_api( 'POST', '', array() ), 400, 'rest_missing_callback_param', 'title is required' );
	ok_error( call_api( 'POST', '', array( 'title' => '' ) ), 400, 'rest_invalid_param', 'empty title' );
	ok_error( call_api( 'POST', '', array( 'title' => '   ' ) ), 422, 'ohmylms_course_invalid_title', 'blank title' );
	ok_error( call_api( 'POST', '', array( 'title' => str_repeat( 'a', 201 ) ) ), 400, 'rest_invalid_param', 'title too long' );
	ok_error(
		call_api(
			'POST',
			'',
			array(
				'title'  => $tag . ' v',
				'status' => 'trash',
			)
		),
		400,
		'rest_invalid_param',
		'trash is not a write status'
	);
	ok_error(
		call_api(
			'POST',
			'',
			array(
				'title' => $tag . ' v',
				'level' => 'godlike',
			)
		),
		400,
		'rest_invalid_param',
		'level enum'
	);
	ok_error(
		call_api(
			'POST',
			'',
			array(
				'title'         => $tag . ' v',
				'price_type'    => 'paid',
				'regular_price' => 12,
			)
		),
		400,
		'rest_invalid_param',
		'numbers are not accepted for money'
	);
	ok_error(
		call_api(
			'POST',
			'',
			array(
				'title'         => $tag . ' v',
				'price_type'    => 'paid',
				'regular_price' => '-5',
			)
		),
		400,
		'rest_invalid_param',
		'negative price'
	);
	ok_error(
		call_api(
			'POST',
			'',
			array(
				'title'         => $tag . ' v',
				'price_type'    => 'paid',
				'regular_price' => '9.999',
			)
		),
		422,
		'ohmylms_course_invalid_price',
		'too many decimals'
	);
	ok_error(
		call_api(
			'POST',
			'',
			array(
				'title'         => $tag . ' v',
				'price_type'    => 'paid',
				'regular_price' => '999999999.99',
			)
		),
		400,
		'rest_invalid_param',
		'price above the maximum'
	);
	ok_error(
		call_api(
			'POST',
			'',
			array(
				'title'      => $tag . ' v',
				'price_type' => 'paid',
			)
		),
		422,
		'ohmylms_course_paid_needs_price',
		'paid without a price'
	);
	ok_error(
		call_api(
			'POST',
			'',
			array(
				'title'         => $tag . ' v',
				'price_type'    => 'paid',
				'regular_price' => '0.00',
			)
		),
		422,
		'ohmylms_course_paid_needs_price',
		'paid at zero'
	);
	ok_error(
		call_api(
			'POST',
			'',
			array(
				'title'         => $tag . ' v',
				'price_type'    => 'paid',
				'regular_price' => '10',
				'sale_price'    => '11',
			)
		),
		422,
		'ohmylms_course_sale_price_too_high',
		'sale above regular'
	);
	ok_error(
		call_api(
			'POST',
			'',
			array(
				'title'           => $tag . ' v',
				'price_type'      => 'paid',
				'regular_price'   => '10',
				'sale_price'      => '5',
				'sale_start_date' => '2026-02-30',
				'sale_end_date'   => '2026-03-01',
			)
		),
		422,
		'ohmylms_course_invalid_sale_dates',
		'impossible date'
	);
	ok_error(
		call_api(
			'POST',
			'',
			array(
				'title'           => $tag . ' v',
				'price_type'      => 'paid',
				'regular_price'   => '10',
				'sale_price'      => '5',
				'sale_start_date' => '2026-05-01',
				'sale_end_date'   => '2026-04-01',
			)
		),
		422,
		'ohmylms_course_invalid_sale_dates',
		'start after end'
	);
	ok_error(
		call_api(
			'POST',
			'',
			array(
				'title'           => $tag . ' v',
				'price_type'      => 'paid',
				'regular_price'   => '10',
				'sale_price'      => '5',
				'sale_start_date' => '2026-05-01',
			)
		),
		422,
		'ohmylms_course_sale_dates_incomplete',
		'only one sale date'
	);
	ok_error(
		call_api(
			'POST',
			'',
			array(
				'title'           => $tag . ' v',
				'price_type'      => 'paid',
				'regular_price'   => '10',
				'sale_start_date' => '2026-05-01',
				'sale_end_date'   => '2026-06-01',
			)
		),
		422,
		'ohmylms_course_sale_dates_without_price',
		'dates without a sale price'
	);
	ok_error(
		call_api(
			'POST',
			'',
			array(
				'title'      => $tag . ' v',
				'sale_price' => '5',
			)
		),
		422,
		'ohmylms_course_sale_on_free',
		'sale on a free course'
	);
	ok_error(
		call_api(
			'POST',
			'',
			array(
				'title'          => $tag . ' v',
				'available_date' => '2026-13-01',
			)
		),
		422,
		'ohmylms_course_invalid_date',
		'available date'
	);
	ok_error(
		call_api(
			'POST',
			'',
			array(
				'title'       => $tag . ' v',
				'access_type' => 'password_protected',
			)
		),
		422,
		'ohmylms_course_password_required',
		'password required'
	);
	ok_error(
		call_api(
			'POST',
			'',
			array(
				'title'       => $tag . ' v',
				'access_type' => 'password_protected',
				'password'    => 'pw',
				'status'      => 'private',
			)
		),
		422,
		'ohmylms_course_private_password',
		'private and password'
	);
	ok_error(
		call_api(
			'POST',
			'',
			array(
				'title'        => $tag . ' v',
				'has_capacity' => true,
			)
		),
		422,
		'ohmylms_course_invalid_capacity',
		'capacity needed'
	);
	ok_error(
		call_api(
			'POST',
			'',
			array(
				'title'        => $tag . ' v',
				'has_capacity' => true,
				'capacity'     => 0,
			)
		),
		400,
		'rest_invalid_param',
		'capacity minimum'
	);
	ok_error(
		call_api(
			'POST',
			'',
			array(
				'title'    => $tag . ' v',
				'duration' => array(
					'hours'   => 1,
					'minutes' => 75,
				),
			)
		),
		400,
		'rest_invalid_param',
		'duration minutes'
	);
	ok_error(
		call_api(
			'POST',
			'',
			array(
				'title'  => $tag . ' v',
				'status' => 'future',
			)
		),
		422,
		'ohmylms_course_invalid_schedule',
		'scheduled needs a time'
	);
	ok_error(
		call_api(
			'POST',
			'',
			array(
				'title'        => $tag . ' v',
				'status'       => 'future',
				'scheduled_at' => '2020-01-01T10:00',
			)
		),
		422,
		'ohmylms_course_invalid_schedule',
		'schedule in the past'
	);
	ok_error(
		call_api(
			'POST',
			'',
			array(
				'title'        => $tag . ' v',
				'scheduled_at' => '2099-01-01T10:00',
			)
		),
		422,
		'ohmylms_course_invalid_schedule',
		'schedule without the scheduled status'
	);
	ok_error(
		call_api(
			'POST',
			'',
			array(
				'title' => $tag . ' v',
				'slug'  => '!!!',
			)
		),
		422,
		'ohmylms_course_invalid_slug',
		'slug with no usable characters'
	);
	ok_error(
		call_api(
			'POST',
			'',
			array(
				'title'        => $tag . ' v',
				'thumbnail_id' => 99999999,
			)
		),
		422,
		'ohmylms_course_invalid_thumbnail',
		'unknown thumbnail'
	);
	$after = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'ohmylms-course' AND post_title LIKE %s", $wpdb->esc_like( $tag ) . '%' ) );
	ok( $before === $after, 'rejected creates wrote nothing' );
	ok_error(
		call_api(
			'PUT',
			'/' . $paid['id'],
			array(
				'title'      => $tag . ' CHANGED',
				'sale_price' => '99',
			)
		),
		422,
		'ohmylms_course_sale_price_too_high',
		'partial update validated against stored regular price'
	);
	$unchanged = call_api( 'GET', '/' . $paid['id'] )->get_data();
	ok( $tag . ' Paid' === $unchanged['title'], 'a rejected update changes nothing, not even the valid fields' );
	ok_error( call_api( 'PUT', '/' . $paid['id'], array( 'regular_price' => '20' ) ), 422, 'ohmylms_course_sale_price_too_high', 'lowering the regular price below the stored sale is rejected' );
	ok_error( call_api( 'GET', '/999999999' ), 404, 'ohmylms_course_not_found', 'unknown course' );
	ok_error(
		call_api(
			'GET',
			'/' . wp_insert_post(
				array(
					'post_title'  => 'not a course',
					'post_status' => 'draft',
				)
			)
		),
		404,
		'ohmylms_course_not_found',
		'other post type is not a course'
	);

	// Partial update keeps everything else; sale rules.
	$updated = call_api(
		'PUT',
		'/' . $paid['id'],
		array(
			'title'           => $tag . ' Paid 2',
			'regular_price'   => '60',
			'sale_price'      => null,
			'sale_start_date' => null,
			'sale_end_date'   => null,
		)
	);
	ok( 200 === $updated->get_status(), 'partial update ok: ' . wp_json_encode( $updated->get_data() ) );
	$data = $updated->get_data();
	ok( '60.00' === $data['regular_price'] && null === $data['sale_price'] && '60.00' === $data['price'] && 'expert' === $data['level'] && 25 === $data['capacity'], 'sale cleared, other fields kept' );
	ok( $paid['slug'] === $data['slug'], 'a title change does not rewrite the slug' );
	$free = call_api( 'PATCH', '/' . $paid['id'], array( 'price_type' => 'free' ) )->get_data();
	ok( 'free' === $free['price_type'] && '0.00' === $free['price'] && '0.00' === $free['regular_price'], 'switching to free' );
	ok( '0' === (string) get_post_meta( $paid['id'], '_price', true ) || '0.00' === get_post_meta( $paid['id'], '_price', true ), 'the stored _price follows' );
	$resp = call_api(
		'POST',
		'/' . $paid['id'],
		array(
			'price_type'    => 'paid',
			'regular_price' => '15',
			'sale_price'    => '15',
		)
	);
	ok( 200 === $resp->get_status() && '15.00' === $resp->get_data()['sale_price'], 'POST updates too; a sale equal to the price is allowed' );
	ok( 1 <= $fired['ohmylms_update_course'] && 1 <= $fired['ohmylms_rest_update_course'], 'update hooks fired' );

	// Status transitions.
	$flow = make_course( array( 'title' => $tag . ' Flow' ) );
	foreach ( array( 'pending', 'publish', 'private', 'draft', 'publish' ) as $step ) {
		$resp = call_api( 'PUT', '/' . $flow['id'], array( 'status' => $step ) );
		$now = get_post_status( $flow['id'] );
		ok( 200 === $resp->get_status() && $step === $resp->get_data()['status'] && $step === $now, "status to $step" );
	}
	$when = wp_date( 'Y-m-d\TH:i', time() + 3 * DAY_IN_SECONDS );
	$resp = call_api(
		'PUT',
		'/' . $flow['id'],
		array(
			'status'       => 'future',
			'scheduled_at' => $when,
		)
	);
	ok( 200 === $resp->get_status() && 'future' === $resp->get_data()['status'] && 0 === strpos( $resp->get_data()['scheduled_at'], $when ), 'scheduling: ' . wp_json_encode( $resp->get_data() ) );
	$resp = call_api( 'PUT', '/' . $flow['id'], array( 'status' => 'publish' ) );
	ok( 'publish' === $resp->get_data()['status'] && null === $resp->get_data()['scheduled_at'], 'publishing a scheduled course now' );

	// Slugs.
	$s1 = make_course(
		array(
			'title' => $tag . ' Slug one',
			'slug'  => $tag . '-shared',
		)
	);
	$s2 = make_course(
		array(
			'title' => $tag . ' Slug two',
			'slug'  => $tag . '-shared',
		)
	);
	ok( $tag . '-shared' === $s1['slug'] && $tag . '-shared-2' === $s2['slug'], 'explicit slug collision gets a suffix (drafts included)' );
	$resp = call_api( 'PUT', '/' . $s2['id'], array( 'slug' => $tag . '-shared' ) );
	ok( $tag . '-shared-2' === $resp->get_data()['slug'], 'updating to a taken slug stays unique' );
	$resp = call_api( 'PUT', '/' . $s2['id'], array( 'slug' => $tag . '-shared-2' ) );
	ok( $tag . '-shared-2' === $resp->get_data()['slug'], 'saving the same slug keeps it' );
	$resp = call_api( 'PUT', '/' . $s1['id'], array( 'slug' => 'Fancy Slug ÄÖ!' ) );
	ok( 0 === strpos( $resp->get_data()['slug'], 'fancy-slug' ), 'slug is sanitised' );

	// Password handling.
	$pw = make_course(
		array(
			'title'       => $tag . ' Locked',
			'access_type' => 'password_protected',
			'password'    => 's3cret-pw',
			'status'      => 'publish',
		)
	);
	ok( 'password_protected' === $pw['access_type'] && true === $pw['has_password'] && 's3cret-pw' === $pw['password'], 'the editor sees the password' );
	ok( 's3cret-pw' === get_post_field( 'post_password', $pw['id'], 'raw' ) && 'password_protected' === get_post_meta( $pw['id'], '_access_type', true ), 'stored as post_password and _access_type' );
	$view = call_api( 'GET', '/' . $pw['id'] )->get_data();
	ok( ! array_key_exists( 'password', $view ) && ! array_key_exists( 'description', $view ) && true === $view['has_password'], 'the view context never contains the password or the content' );
	$list = call_api( 'GET', '', array( 'search' => $tag . ' Locked' ) );
	ok( 1 === count( $list->get_data() ) && ! array_key_exists( 'password', $list->get_data()[0] ) && true === $list->get_data()[0]['has_password'], 'lists never contain passwords' );
	ok( false === strpos( wp_json_encode( $list->get_data() ), 's3cret-pw' ), 'password absent from the whole list payload' );
	$kept = call_api( 'PUT', '/' . $pw['id'], array( 'title' => $tag . ' Locked 2' ) )->get_data();
	ok( 's3cret-pw' === $kept['password'], 'unrelated updates keep the password' );
	$new_pw = call_api( 'PUT', '/' . $pw['id'], array( 'password' => 'another-pw' ) )->get_data();
	ok( 'another-pw' === $new_pw['password'], 'password change' );
	ok_error( call_api( 'PUT', '/' . $pw['id'], array( 'status' => 'private' ) ), 422, 'ohmylms_course_private_password', 'making a protected course private' );
	$pub = call_api( 'PUT', '/' . $pw['id'], array( 'access_type' => 'public' ) )->get_data();
	ok( 'public' === $pub['access_type'] && false === $pub['has_password'] && '' === get_post_field( 'post_password', $pw['id'], 'raw' ), 'switching to public clears the password' );
	ok_error( call_api( 'PUT', '/' . $pw['id'], array( 'access_type' => 'password_protected' ) ), 422, 'ohmylms_course_password_required', 'protection needs a password again' );
	ok_error(
		call_api(
			'PUT',
			'/' . $pw['id'],
			array(
				'access_type' => 'password_protected',
				'password'    => str_repeat( 'x', 101 ),
			)
		),
		400,
		'rest_invalid_param',
		'password length'
	);

	// Authors: own versus other's courses.
	wp_set_current_user( $author_a );
	$a1 = make_course( array( 'title' => $tag . ' A draft' ) );
	ok( $author_a === $a1['author_id'] && '' === $a1['author_name'], 'author owns the course; no author names for non-editors of others' );
	$a_pub = make_course(
		array(
			'title'  => $tag . ' A pub',
			'status' => 'publish',
		)
	);
	ok( 'publish' === $a_pub['status'], 'an author can publish' );
	wp_set_current_user( $author_b );
	$b1 = make_course( array( 'title' => $tag . ' B draft' ) );
	ok_error( call_api( 'GET', '/' . $a1['id'] ), 403, 'ohmylms_course_cannot_view', "author B cannot read A's draft" );
	ok_error( call_api( 'GET', '/' . $a1['id'], array( 'context' => 'edit' ) ), 403, 'ohmylms_course_cannot_edit', "author B cannot read A's draft in edit context" );
	ok_error( call_api( 'PUT', '/' . $a1['id'], array( 'title' => 'hijack' ) ), 403, 'ohmylms_course_cannot_edit', "author B cannot edit A's draft" );
	ok_error( call_api( 'PUT', '/' . $a_pub['id'], array( 'title' => 'hijack' ) ), 403, 'ohmylms_course_cannot_edit', "author B cannot edit A's published course" );
	ok_error( call_api( 'DELETE', '/' . $a1['id'] ), 403, 'ohmylms_course_cannot_delete', "author B cannot trash A's course" );
	ok_error( call_api( 'DELETE', '/' . $a1['id'], array( 'force' => true ) ), 403, 'ohmylms_course_cannot_delete', "author B cannot delete A's course" );
	ok_error( call_api( 'POST', '/' . $a1['id'] . '/duplicate' ), 403, 'ohmylms_course_cannot_edit', "author B cannot copy A's draft" );
	ok_error( call_api( 'POST', '/' . $a1['id'] . '/restore' ), 403, 'ohmylms_course_cannot_delete', "author B cannot restore A's course" );
	$view = call_api( 'GET', '/' . $a_pub['id'] );
	ok( 200 === $view->get_status() && ! array_key_exists( 'description', $view->get_data() ), "B can see A's published course in the view context only" );
	ok_error( call_api( 'GET', '/' . $a_pub['id'], array( 'context' => 'edit' ) ), 403, 'ohmylms_course_cannot_edit', 'edit context needs edit rights' );
	$own = call_api(
		'GET',
		'',
		array(
			'search'   => $tag,
			'per_page' => 100,
		)
	);
	$ids = wp_list_pluck( $own->get_data(), 'id' );
	ok( array( $b1['id'] ) === $ids, "the list only holds the author's own courses: " . wp_json_encode( $ids ) );
	$own = call_api(
		'GET',
		'',
		array(
			'search' => $tag,
			'author' => $admin,
		)
	);
	ok( array( $b1['id'] ) === wp_list_pluck( $own->get_data(), 'id' ), 'the author filter cannot widen an author list' );
	ok( 200 === call_api( 'PUT', '/' . $b1['id'], array( 'title' => $tag . ' B edited' ) )->get_status(), 'B edits own course' );
	ok_error( call_api( 'GET', '/' . $paid['id'], array( 'context' => 'edit' ) ), 403, 'ohmylms_course_cannot_edit', "admin's course is closed to authors" );
	$none = call_api( 'GET', '/999999999' );
	ok( 404 === $none->get_status(), 'unknown id is 404 for authors too' );

	// Thumbnails.
	$own_image   = make_attachment( $author_b, 'image/png' );
	$other_image = make_attachment( $admin, 'image/png' );
	$pdf         = make_attachment( $author_b, 'application/pdf' );
	$resp        = call_api( 'PUT', '/' . $b1['id'], array( 'thumbnail_id' => $own_image ) );
	$stored = (int) get_post_thumbnail_id( $b1['id'] );
	ok( 200 === $resp->get_status() && $resp->get_data()['thumbnail_id'] === $own_image && $own_image === $stored, 'author uses own image: ' . wp_json_encode( $resp->get_data() ) );
	ok_error( call_api( 'PUT', '/' . $b1['id'], array( 'thumbnail_id' => $other_image ) ), 403, 'ohmylms_course_thumbnail_forbidden', "another user's image" );
	ok_error( call_api( 'PUT', '/' . $b1['id'], array( 'thumbnail_id' => $pdf ) ), 422, 'ohmylms_course_invalid_thumbnail', 'not an image' );
	ok_error( call_api( 'PUT', '/' . $b1['id'], array( 'thumbnail_id' => $a1['id'] ) ), 422, 'ohmylms_course_invalid_thumbnail', 'a post is not an attachment' );
	ok( (int) get_post_thumbnail_id( $b1['id'] ) === $own_image, 'rejected thumbnails change nothing' );
	$resp = call_api( 'PUT', '/' . $b1['id'], array( 'thumbnail_id' => null ) );
	ok( null === $resp->get_data()['thumbnail_id'] && ! get_post_thumbnail_id( $b1['id'] ), 'thumbnail removed' );

	// Contributors.
	wp_set_current_user( $contributor );
	$opts = call_api( 'GET', '/options' )->get_data();
	ok( false === $opts['abilities']['publish'] && ! in_array( 'publish', wp_list_pluck( $opts['statuses'], 'value' ), true ), 'contributor options hide publishing' );
	$c1 = make_course( array( 'title' => $tag . ' C draft' ) );
	ok_error(
		call_api(
			'POST',
			'',
			array(
				'title'  => $tag . ' C pub',
				'status' => 'publish',
			)
		),
		403,
		'ohmylms_course_cannot_publish',
		'contributor cannot publish on create'
	);
	ok_error( call_api( 'PUT', '/' . $c1['id'], array( 'status' => 'publish' ) ), 403, 'ohmylms_course_cannot_publish', 'contributor cannot publish on update' );
	ok_error( call_api( 'PUT', '/' . $c1['id'], array( 'status' => 'private' ) ), 403, 'ohmylms_course_cannot_publish', 'contributor cannot make private' );
	ok( 'pending' === call_api( 'PUT', '/' . $c1['id'], array( 'status' => 'pending' ) )->get_data()['status'], 'contributor submits for review' );
	wp_set_current_user( $admin );
	wp_update_post(
		array(
			'ID'          => $c1['id'],
			'post_status' => 'publish',
		)
	);
	wp_set_current_user( $contributor );
	ok_error( call_api( 'PUT', '/' . $c1['id'], array( 'title' => 'late' ) ), 403, 'ohmylms_course_cannot_edit', 'contributor cannot edit a course once it is published' );
	ok_error( call_api( 'DELETE', '/' . $c1['id'] ), 403, 'ohmylms_course_cannot_delete', 'contributor cannot delete a published course' );

	// Admin sees everything and author names.
	wp_set_current_user( $admin );
	$all = call_api(
		'GET',
		'',
		array(
			'search'   => $tag,
			'per_page' => 100,
		)
	);
	ok( count( $all->get_data() ) >= 10, 'admin sees every author\'s courses' );
	$names = array_unique( wp_list_pluck( $all->get_data(), 'author_name' ) );
	ok( in_array( $tag . ' author', $names, true ), 'admin sees author names' );
	$only_a = call_api(
		'GET',
		'',
		array(
			'search'   => $tag,
			'author'   => $author_a,
			'per_page' => 100,
		)
	);
	ok( 2 === count( $only_a->get_data() ), 'admin can filter by author' );
	$edit = call_api( 'GET', '/' . $a1['id'], array( 'context' => 'edit' ) );
	ok( 200 === $edit->get_status() && array_key_exists( 'description', $edit->get_data() ), "admin reads A's draft in the edit context" );

	// Trash, restore, delete.
	$t = make_course(
		array(
			'title'  => $tag . ' Trash me',
			'status' => 'publish',
		)
	);
	$resp = call_api( 'DELETE', '/' . $t['id'] );
	ok( 200 === $resp->get_status() && 'trash' === $resp->get_data()['status'] && 'trash' === get_post_status( $t['id'] ), 'DELETE moves to the trash' );
	ok_error( call_api( 'DELETE', '/' . $t['id'] ), 409, 'ohmylms_course_already_trashed', 'second trash' );
	ok_error( call_api( 'PUT', '/' . $t['id'], array( 'title' => 'x' ) ), 409, 'ohmylms_course_trashed', 'cannot edit a trashed course' );
	ok_error( call_api( 'POST', '/' . $t['id'] . '/duplicate' ), 409, 'ohmylms_course_trashed', 'cannot copy a trashed course' );
	$in_trash = call_api(
		'GET',
		'',
		array(
			'status' => 'trash',
			'search' => $tag,
		)
	);
	ok( array( $t['id'] ) === wp_list_pluck( $in_trash->get_data(), 'id' ), 'the trash filter lists it' );
	ok(
		! in_array(
			$t['id'],
			wp_list_pluck(
				call_api(
					'GET',
					'',
					array(
						'search'   => $tag,
						'per_page' => 100,
					)
				)->get_data(),
				'id'
			),
			true
		),
		'status=all hides the trash'
	);
	ok_error( call_api( 'POST', '/' . $flow['id'] . '/restore' ), 409, 'ohmylms_course_not_trashed', 'restore a course that is not trashed' );
	$restored = call_api( 'POST', '/' . $t['id'] . '/restore' );
	ok( 200 === $restored->get_status() && 'draft' === $restored->get_data()['status'], 'restore returns a draft' );
	ok( 1 <= $fired['ohmylms_trash_course'], 'trash hook fired' );
	call_api( 'DELETE', '/' . $t['id'] );
	$deleted = call_api( 'DELETE', '/' . $t['id'], array( 'force' => true ) );
	ok( 200 === $deleted->get_status() && true === $deleted->get_data()['deleted'] && $t['id'] === $deleted->get_data()['previous']['id'], 'permanent delete' );
	ok( null === get_post( $t['id'] ), 'the post is gone' );
	ok_error( call_api( 'GET', '/' . $t['id'] ), 404, 'ohmylms_course_not_found', 'deleted course' );
	ok( 1 <= $fired['ohmylms_rest_delete_course'] && 1 <= $fired['ohmylms_delete_course'], 'delete hooks fired' );
	$enrolled = make_course(
		array(
			'title'  => $tag . ' Has students',
			'status' => 'publish',
		)
	);
	$wpdb->insert(
		$wpdb->prefix . 'ohmylms_user_enrollment',
		array(
			'course_id'  => $enrolled['id'],
			'user_id'    => $subscriber,
			'status'     => 'enrolled',
			'progress'   => 'running',
			'start_date' => current_time( 'mysql' ),
		)
	);
	$blocked = call_api( 'DELETE', '/' . $enrolled['id'], array( 'force' => true ) );
	ok_error( $blocked, 409, 'ohmylms_course_has_students', 'permanent delete with students' );
	ok( 1 === $blocked->get_data()['data']['students'] && get_post( $enrolled['id'] ), 'the course survives and the count is reported' );
	ok( 1 === call_api( 'GET', '/' . $enrolled['id'] )->get_data()['enrolled_count'], 'enrolled count' );
	ok( 'trash' === call_api( 'DELETE', '/' . $enrolled['id'] )->get_data()['status'], 'trashing a course with students is allowed' );

	// Duplicate.
	wp_set_current_user( $admin );
	$locked_copy_src = make_course(
		array(
			'title'       => $tag . ' Copy source',
			'status'      => 'publish',
			'access_type' => 'password_protected',
			'password'    => 'copy-pw',
			'level'       => 'all',
		)
	);
	$copy = call_api( 'POST', '/' . $locked_copy_src['id'] . '/duplicate' );
	ok( 201 === $copy->get_status(), 'duplicate: ' . wp_json_encode( $copy->get_data() ) );
	$copy_data    = $copy->get_data();
	$course_ids[] = $copy_data['id'];
	ok( $copy_data['id'] !== $locked_copy_src['id'] && 'draft' === $copy_data['status'] && $admin === $copy_data['author_id'], 'the copy is a new draft owned by the caller' );
	ok( 'copy-pw' === $copy_data['password'] && 'all' === $copy_data['level'] && $copy_data['slug'] !== $locked_copy_src['slug'], 'the copy keeps settings and password, with its own slug' );
	ok( isset( $copy->get_headers()['Location'] ), 'duplicate sets Location' );
	wp_set_current_user( $author_a );
	$own_copy = call_api( 'POST', '/' . $a1['id'] . '/duplicate' );
	ok( 201 === $own_copy->get_status() && $author_a === $own_copy->get_data()['author_id'], 'an author can copy their own course' );
	$course_ids[] = $own_copy->get_data()['id'];

	// Listing: filters, paging, sorting, no N+1.
	wp_set_current_user( $admin );
	$lp = array();
	for ( $i = 1; $i <= 5; $i++ ) {
		$lp[] = make_course(
			array(
				'title'         => $tag . ' List ' . $i,
				'price_type'    => $i % 2 ? 'paid' : 'free',
				'regular_price' => $i % 2 ? (string) ( $i * 10 ) : '0',
				'level'         => 3 === $i ? 'all' : 'beginner',
				'status'        => $i > 3 ? 'draft' : 'publish',
			)
		);
	}
	$page = call_api(
		'GET',
		'',
		array(
			'search'   => $tag . ' List',
			'per_page' => 2,
			'page'     => 1,
			'orderby'  => 'title',
			'order'    => 'asc',
		)
	);
	$hdr  = $page->get_headers();
	ok( 2 === count( $page->get_data() ) && '5' === $hdr['X-WP-Total'] && '3' === $hdr['X-WP-TotalPages'], 'paging headers' );
	ok( $tag . ' List 1' === $page->get_data()[0]['title'] && isset( $hdr['Link'] ) && false !== strpos( $hdr['Link'], 'rel="next"' ), 'ordering and the next link' );
	$last = call_api(
		'GET',
		'',
		array(
			'search'   => $tag . ' List',
			'per_page' => 2,
			'page'     => 3,
			'orderby'  => 'title',
			'order'    => 'asc',
		)
	);
	ok( 1 === count( $last->get_data() ) && false !== strpos( $last->get_headers()['Link'], 'rel="prev"' ), 'last page' );
	ok_error(
		call_api(
			'GET',
			'',
			array(
				'search'   => $tag . ' List',
				'per_page' => 2,
				'page'     => 9,
			)
		),
		400,
		'ohmylms_course_invalid_page',
		'page past the end'
	);
	ok_error( call_api( 'GET', '', array( 'per_page' => 101 ) ), 400, 'rest_invalid_param', 'per_page maximum' );
	ok_error( call_api( 'GET', '', array( 'page' => 0 ) ), 400, 'rest_invalid_param', 'page minimum' );
	ok_error( call_api( 'GET', '', array( 'orderby' => 'meta_value' ) ), 400, 'rest_invalid_param', 'orderby enum' );
	ok_error( call_api( 'GET', '', array( 'status' => 'any' ) ), 400, 'rest_invalid_param', 'status enum' );
	ok_error( call_api( 'GET', '', array( 'context' => 'edit' ) ), 400, 'rest_invalid_param', 'collections are view only' );
	ok_error( call_api( 'GET', '', array( 'created_after' => 'yesterday' ) ), 400, 'rest_invalid_param', 'date filter format' );
	$ignored = call_api(
		'GET',
		'',
		array(
			'meta_key'    => '_price',
			'post_status' => 'trash',
			'search'      => $tag . ' List 1',
		)
	);
	ok( 200 === $ignored->get_status() && 1 === count( $ignored->get_data() ) && 'publish' === $ignored->get_data()[0]['status'], 'raw query arguments are not forwarded to WP_Query' );
	$titles = static function ( $args ) use ( $tag ) {
		$resp = call_api(
			'GET',
			'',
			array_merge(
				array(
					'search'   => $tag . ' List',
					'per_page' => 100,
					'orderby'  => 'title',
					'order'    => 'asc',
				),
				$args
			)
		);
		return str_replace( $tag . ' List ', '', implode( ',', wp_list_pluck( $resp->get_data(), 'title' ) ) );
	};
	ok( '1,3,5' === $titles( array( 'price_type' => 'paid' ) ), 'paid filter: ' . $titles( array( 'price_type' => 'paid' ) ) );
	ok( '2,4' === $titles( array( 'price_type' => 'free' ) ), 'free filter' );
	ok( '3' === $titles( array( 'level' => 'all' ) ), 'level filter' );
	ok( '4,5' === $titles( array( 'status' => 'draft' ) ), 'status filter' );
	ok( '1,2,3' === $titles( array( 'status' => 'publish' ) ), 'published filter' );
	ok(
		'5,3,1' === $titles(
			array(
				'price_type' => 'paid',
				'orderby'    => 'price',
				'order'      => 'desc',
			)
		),
		'sorted by price'
	);
	ok( '2' === $titles( array( 'include' => array( $lp[1]['id'] ) ) ), 'include' );
	$today = current_time( 'Y-m-d' );
	ok(
		'1,2,3,4,5' === $titles(
			array(
				'created_after'  => $today,
				'created_before' => $today,
			)
		),
		'created today'
	);
	ok( '' === $titles( array( 'created_before' => '2001-01-01' ) ), 'created before the dawn of time' );
	$empty = call_api( 'GET', '', array( 'search' => $tag . ' nothing like this' ) );
	ok( 200 === $empty->get_status() && array() === $empty->get_data() && '0' === $empty->get_headers()['X-WP-TotalPages'], 'empty result is 200 with zero pages' );
	$search = call_api( 'GET', '', array( 'search' => $tag . ' List 4' ) );
	ok( 1 === count( $search->get_data() ), 'search' );
	$item = $page->get_data()[0];
	foreach ( array( 'id', 'title', 'slug', 'status', 'permalink', 'level', 'price_type', 'regular_price', 'price', 'currency', 'access_type', 'has_password', 'has_capacity', 'enrolled_count', 'created_at', 'updated_at', 'thumbnail_url' ) as $key ) {
		ok( array_key_exists( $key, $item ), "list item has $key" );
	}
	ok( ! array_key_exists( 'description', $item ) && ! array_key_exists( 'password', $item ), 'list items are slim' );
	// Query count must not grow with the page size.
	$q2 = $wpdb->num_queries;
	call_api(
		'GET',
		'',
		array(
			'search'   => $tag . ' List',
			'per_page' => 2,
		)
	);
	$small = $wpdb->num_queries - $q2;
	$q2    = $wpdb->num_queries;
	call_api(
		'GET',
		'',
		array(
			'search'   => $tag . ' List',
			'per_page' => 5,
		)
	);
	$big = $wpdb->num_queries - $q2;
	ok( $big <= $small + 2, "no per-row queries: $small queries for 2 rows, $big for 5" );

	// Options, schema.
	$opts = call_api( 'GET', '/options' )->get_data();
	foreach ( array( 'statuses', 'levels', 'price_types', 'access_types', 'currency', 'limits', 'abilities' ) as $key ) {
		ok( isset( $opts[ $key ] ), "options has $key" );
	}
	ok( true === $opts['abilities']['publish'] && true === $opts['abilities']['edit_others'] && 5 === count( $opts['statuses'] ), 'admin abilities' );
	$schema = rest_do_request( new WP_REST_Request( 'OPTIONS', '/ohmylms/v1/courses' ) )->get_data()['schema'];
	ok( 'http://json-schema.org/draft-04/schema#' === $schema['$schema'] && 'ohmylms_course' === $schema['title'], 'schema header' );
	foreach ( array( 'id', 'title', 'slug', 'status', 'scheduled_at', 'description', 'level', 'price_type', 'regular_price', 'sale_price', 'sale_start_date', 'sale_end_date', 'price', 'access_type', 'password', 'has_password', 'available_date', 'duration', 'has_capacity', 'capacity', 'thumbnail_id', 'enrolled_count' ) as $property ) {
		ok( isset( $schema['properties'][ $property ] ), "schema property $property" );
	}
	ok( ! empty( $schema['properties']['id']['readonly'] ) && ! empty( $schema['properties']['price']['readonly'] ), 'schema marks computed fields readonly' );
	ok( array( 'edit' ) === $schema['properties']['password']['context'], 'the password is edit context only' );

	// Auxiliary routes keep a real permission check.
	wp_set_current_user( $author_a );
	ok_error( call_api( 'POST', '/' . $a1['id'] . '/enroll' ), 403, 'ohmylms_course_cannot_enroll', 'author cannot enrol students' );
	ok_error( call_api( 'POST', '/' . $a1['id'] . '/enroll-by-email', array( 'email' => 'x@example.invalid' ) ), 403, 'ohmylms_course_cannot_create_users', 'author cannot create users' );
	ok_error( call_api( 'GET', '/export' ), 403, 'ohmylms_course_cannot_exchange', 'author cannot export' );
	ok_error( call_api( 'GET', '/settings/' . $b1['id'] ), 403, 'ohmylms_course_cannot_edit', "author cannot read B's builder settings" );
	ok_error(
		call_api(
			'POST',
			'/' . $b1['id'] . '/terms',
			array(
				'taxonomy' => 'category',
				'terms'    => array( 1 ),
			)
		),
		403,
		'ohmylms_course_cannot_edit',
		"author cannot tag B's course"
	);
	wp_set_current_user( $admin );
	ok_error(
		call_api(
			'POST',
			'/' . $a1['id'] . '/terms',
			array(
				'taxonomy' => 'category',
				'terms'    => array( 1 ),
			)
		),
		400,
		'ohmylms_course_invalid_taxonomy',
		'taxonomy must belong to courses'
	);

	fwrite( STDOUT, "OK courses-rest-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	// Remove only what this run created.
	$tagged = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'ohmylms-course' AND post_title LIKE %s", $wpdb->esc_like( $tag ) . '%' ) ) );
	$all    = array_unique( array_merge( array_map( 'intval', $course_ids ), $tagged ) );
	foreach ( $all as $leftover ) {
		$chapters = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT chapter_id FROM %i WHERE course_id = %d', $wpdb->prefix . 'ohmylms_chapter_relationship', $leftover ) );
		foreach ( $chapters as $chapter ) {
			wp_delete_post( (int) $chapter, true );
		}
		$wpdb->delete( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'course_id' => $leftover ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_user_enrollment', array( 'course_id' => $leftover ), array( '%d' ) );
		wp_delete_post( $leftover, true );
	}
	foreach ( array_merge( $attachment, array_map( 'intval', (array) $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_title = 'not a course' AND post_status = 'draft'" ) ) ) as $leftover ) {
		wp_delete_post( $leftover, true );
	}
	// Default chapters that the API-created courses received and that were left behind by deleting them.
	foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p WHERE p.post_type = 'ohmylms-chapter' AND p.ID > %d AND NOT EXISTS (SELECT 1 FROM %i r WHERE r.chapter_id = p.ID)", $baseline, $wpdb->prefix . 'ohmylms_chapter_relationship' ) ) as $orphan ) {
		wp_delete_post( (int) $orphan, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		$wpdb->delete( $wpdb->prefix . 'ohmylms_user_enrollment', array( 'user_id' => $user_id ), array( '%d' ) );
		wp_delete_user( $user_id );
	}
}

exit( isset( $exit_code ) ? $exit_code : 0 );
