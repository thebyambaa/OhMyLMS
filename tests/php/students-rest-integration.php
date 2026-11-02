<?php
/**
 * Students REST API integration test.
 *
 * Runs against a WordPress install (the Local site by default) with real database tables. It only
 * creates tagged test users and courses (and their enrolments) and removes everything in `finally`.
 *
 * Usage (set OHMYLMS_STUDENTS_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/students-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, Generic.Formatting.MultipleStatementAlignment

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_STUDENTS_TEST' ) ) {
	exit( "Set OHMYLMS_STUDENTS_TEST=1 to run the students REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

global $wpdb;

$checks     = 0;
$user_ids   = array();
$course_ids = array();
$tag        = 'sttest' . strtolower( wp_generate_password( 6, false, false ) );
$fired      = array(
	'enrolled'   => array(),
	'unenrolled' => array(),
	'blocked'    => array(),
	'unblocked'  => array(),
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
 * @param string $path   Path below /ohmylms/v1/students.
 * @param array  $data   Body (JSON) or query (GET).
 * @return WP_REST_Response
 */
function call_api( $method, $path = '', array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/students' . $path );
	if ( 'GET' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

/**
 * Create a test user.
 *
 * @param string $suffix Login suffix.
 * @param string $role   Role.
 * @return int
 * @throws RuntimeException When WordPress refuses to create it.
 */
function make_user( $suffix, $role ) {
	global $tag, $user_ids;
	$id = wp_insert_user(
		array(
			'user_login'   => $tag . '_' . $suffix,
			'user_pass'    => wp_generate_password( 24 ),
			'user_email'   => $tag . '.' . $suffix . '@example.invalid',
			'display_name' => 'Student ' . $tag . ' ' . $suffix,
			'role'         => $role,
		)
	);
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( 'FAILED: could not create user ' . $suffix . ': ' . $id->get_error_message() );
	}
	$user_ids[] = $id;
	return $id;
}

/**
 * Create a test course.
 *
 * @param string $title  Title suffix.
 * @param string $status Post status.
 * @return int
 * @throws RuntimeException When WordPress refuses to create it.
 */
function make_course( $title, $status = 'publish' ) {
	global $tag, $course_ids;
	$id = wp_insert_post(
		array(
			'post_type'   => 'ohmylms-course',
			'post_status' => $status,
			'post_title'  => $tag . ' ' . $title,
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( 'FAILED: could not create course: ' . $id->get_error_message() );
	}
	$course_ids[] = $id;
	return $id;
}

add_action(
	'ohmylms_manual_student_enrollment',
	static function ( $user, $course ) use ( &$fired ) {
		$fired['enrolled'][] = array( $user, $course );
	},
	10,
	2
);
add_action(
	'ohmylms_student_unenrolled',
	static function ( $user, $course ) use ( &$fired ) {
		$fired['unenrolled'][] = array( $user, $course );
	},
	10,
	2
);
add_action(
	'ohmylms_student_blocked',
	static function ( $user ) use ( &$fired ) {
		$fired['blocked'][] = $user;
	}
);
add_action(
	'ohmylms_student_unblocked',
	static function ( $user ) use ( &$fired ) {
		$fired['unblocked'][] = $user;
	}
);

try {
	ok( post_type_exists( 'ohmylms-course' ), 'course post type is registered' );
	ok( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'ohmylms_user_enrollment' ) ) ) === $wpdb->prefix . 'ohmylms_user_enrollment', 'enrolment table exists' );

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

	$alice  = make_user( 'alice', 'subscriber' );
	$bob    = make_user( 'bob', 'subscriber' );
	$carol  = make_user( 'carol', 'subscriber' );
	$editor = make_user( 'editor', 'editor' );
	$course_a = make_course( 'Alpha course' );
	$course_b = make_course( 'Bravo course' );
	$draft    = make_course( 'Draft course', 'draft' );
	$page_id  = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => $tag . ' not a course',
		)
	);
	ok( $page_id && ! is_wp_error( $page_id ), 'a non-course post exists' );

	// Routes and schema.
	$routes = rest_get_server()->get_routes( 'ohmylms/v1' );
	foreach ( array( '/students', '/students/courses', '/students/(?P<id>[\d]+)', '/students/(?P<id>[\d]+)/enrollments', '/students/(?P<id>[\d]+)/enrollments/(?P<course_id>[\d]+)' ) as $route ) {
		ok( isset( $routes[ '/ohmylms/v1' . $route ] ), 'route registered: ' . $route );
		foreach ( $routes[ '/ohmylms/v1' . $route ] as $endpoint ) {
			ok( is_callable( $endpoint['permission_callback'] ) && '__return_true' !== $endpoint['permission_callback'], 'real permission_callback on ' . $route );
		}
	}
	ok( ! isset( $routes['/ohmylms/v1/students/unban'] ), 'the old unban route is gone' );

	// Anonymous and unprivileged callers.
	$cases = array(
		array( 'GET', '', array() ),
		array( 'GET', '/courses', array() ),
		array( 'GET', '/' . $alice, array() ),
		array( 'POST', '/' . $alice, array( 'blocked' => true ) ),
		array( 'GET', '/' . $alice . '/enrollments', array() ),
		array( 'POST', '/' . $alice . '/enrollments', array( 'course_id' => $course_a ) ),
		array( 'DELETE', '/' . $alice . '/enrollments/' . $course_a, array() ),
	);
	wp_set_current_user( 0 );
	foreach ( $cases as $case ) {
		$response = call_api( $case[0], $case[1], $case[2] );
		ok( 401 === $response->get_status(), 'anonymous ' . $case[0] . ' ' . $case[1] . ' is 401, got ' . $response->get_status() );
	}
	foreach ( array( $alice, $editor ) as $denied ) {
		wp_set_current_user( $denied );
		foreach ( $cases as $case ) {
			$response = call_api( $case[0], $case[1], $case[2] );
			ok( 403 === $response->get_status(), 'user ' . $denied . ' ' . $case[0] . ' ' . $case[1] . ' is 403, got ' . $response->get_status() );
		}
	}
	// A user who only has the legacy manage_ohmylms capability is not an administrator and must be refused.
	$manager = make_user( 'manager', 'subscriber' );
	get_userdata( $manager )->add_cap( 'manage_ohmylms' );
	wp_set_current_user( $manager );
	ok( 403 === call_api( 'GET', '' )->get_status(), 'manage_ohmylms alone no longer opens student data' );
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE user_id = %d", $alice ) ), 'forbidden enrol wrote nothing' );
	ok( 'yes' !== get_user_meta( $alice, '_ohmylms_banned_student', true ), 'forbidden block changed nothing' );

	// Administrator: directory.
	wp_set_current_user( $admin );

	$response = call_api( 'GET', '', array( 'search' => $tag ) );
	ok( 200 === $response->get_status(), 'list 200' );
	ok( '4' === $response->get_headers()['X-WP-Total'] && '1' === $response->get_headers()['X-WP-TotalPages'], 'list headers count the four subscribers, not the editor' );
	$names = wp_list_pluck( $response->get_data(), 'name' );
	ok( in_array( 'Student ' . $tag . ' alice', $names, true ) && ! in_array( 'Student ' . $tag . ' editor', $names, true ), 'only students are listed' );
	$keys = array_values( array_diff( array_keys( $response->get_data()[0] ), array( '_links' ) ) );
	sort( $keys );
	ok( array( 'avatar_url', 'blocked', 'completed_courses', 'email', 'enrolled_courses', 'id', 'last_login', 'name', 'registered' ) === $keys, 'list item exposes exactly the screen fields: ' . implode( ',', $keys ) );
	$json = wp_json_encode( $response->get_data() );
	ok( false === strpos( $json, 'user_pass' ) && false === strpos( $json, 'user_activation_key' ) && false === strpos( $json, '$P$' ), 'no password hash or token in the list' );
	ok( preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $response->get_data()[0]['registered'] ) === 1, 'registered is an ISO site-time string' );
	ok( 0 === $response->get_data()[0]['enrolled_courses'] && false === $response->get_data()[0]['blocked'], 'fresh student has no courses and is not blocked' );

	$response = call_api( 'GET', '', array( 'search' => $tag . '.bob@' ) );
	ok( 1 === count( $response->get_data() ) && $bob === $response->get_data()[0]['id'], 'search by email' );
	$response = call_api( 'GET', '', array( 'search' => $tag . "' OR 1=1 -- " ) );
	ok( 200 === $response->get_status() && array() === $response->get_data() && '0' === $response->get_headers()['X-WP-Total'], 'search is escaped, SQL meta characters match nothing' );
	$response = call_api( 'GET', '', array( 'search' => '%' ) );
	ok( 200 === $response->get_status() && 0 === count( array_filter( $response->get_data(), static fn( $row ) => false === strpos( strtolower( $row['name'] . $row['email'] ), '%' ) ) ), 'a literal % is not a wildcard' );

	$response = call_api(
		'GET',
		'',
		array(
			'search'   => $tag,
			'per_page' => 3,
			'page'     => 2,
		)
	);
	ok( 200 === $response->get_status() && 1 === count( $response->get_data() ), 'second page holds the remainder' );
	ok( '4' === $response->get_headers()['X-WP-Total'] && '2' === $response->get_headers()['X-WP-TotalPages'], 'pagination headers on page 2' );
	ok( false !== strpos( $response->get_headers()['Link'], 'rel="prev"' ), 'Link prev header' );
	ok(
		400 === call_api(
			'GET',
			'',
			array(
				'search' => $tag,
				'page'   => 9,
			)
		)->get_status(),
		'page past the end is 400'
	);
	ok(
		'ohmylms_student_invalid_page' === call_api(
			'GET',
			'',
			array(
				'search' => $tag,
				'page'   => 9,
			)
		)->get_data()['code'],
		'page past the end error code'
	);
	foreach ( array(
		array( 'per_page' => 101 ),
		array( 'per_page' => 0 ),
		array( 'page' => 0 ),
		array( 'orderby' => 'user_pass' ),
		array( 'order' => 'sideways' ),
		array( 'course' => 0 ),
		array( 'search' => str_repeat( 'x', 101 ) ),
	) as $bad ) {
		$response = call_api( 'GET', '', $bad );
		ok( 400 === $response->get_status() && 'rest_invalid_param' === $response->get_data()['code'], 'invalid collection arg rejected: ' . wp_json_encode( $bad ) );
	}
	$response = call_api(
		'GET',
		'',
		array(
			'search'  => $tag,
			'orderby' => 'email',
			'order'   => 'desc',
		)
	);
	ok( $tag . '.manager@example.invalid' === $response->get_data()[0]['email'], 'order by email desc' );

	// Course choices.
	$response = call_api( 'GET', '/courses', array( 'search' => $tag ) );
	ok( 200 === $response->get_status() && '3' === $response->get_headers()['X-WP-Total'], 'course choices list the three courses, not the page' );
	ok( array( $course_a, $course_b, $draft ) === wp_list_pluck( $response->get_data(), 'id' ), 'course choices sorted by title' );
	ok( $tag . ' Alpha course' === $response->get_data()[0]['title'] && 'draft' === $response->get_data()[2]['status'], 'course choice shape' );
	ok( 400 === call_api( 'GET', '/courses', array( 'per_page' => 101 ) )->get_status(), 'course choices per_page capped' );

	// Single student.
	$response = call_api( 'GET', '/' . $alice );
	ok( 200 === $response->get_status() && $alice === $response->get_data()['id'], 'read student' );
	ok( 404 === call_api( 'GET', '/' . $editor )->get_status() && 'ohmylms_student_not_found' === call_api( 'GET', '/' . $editor )->get_data()['code'], 'a non-student user is not a student resource' );
	ok( 404 === call_api( 'GET', '/999999999' )->get_status(), 'unknown student is 404' );
	ok( 404 === call_api( 'GET', '/' . $editor . '/enrollments' )->get_status(), 'enrolments of a non-student are 404' );

	// Enrol.
	$response = call_api( 'POST', '/' . $alice . '/enrollments', array( 'course_id' => $course_a ) );
	ok( 201 === $response->get_status(), 'enrol 201: ' . wp_json_encode( $response->get_data() ) );
	$enrolment = $response->get_data();
	ok( $course_a === $enrolment['course_id'] && $tag . ' Alpha course' === $enrolment['title'] && 'enrolled' === $enrolment['status'] && 'running' === $enrolment['progress'], 'enrolment shape' );
	ok( 0 === $enrolment['percent_complete'] && null === $enrolment['completed_at'] && 0 === $enrolment['lessons']['completed'], 'new enrolment has no progress' );
	ok( preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $enrolment['enrolled_at'] ) === 1, 'enrolled_at format' );
	ok( array( array( $alice, $course_a ) ) === $fired['enrolled'], 'ohmylms_manual_student_enrollment fired once' );
	ok( 'yes' === get_user_meta( $alice, '_is_ohmylms_student', true ), 'student meta set' );
	$dupe = call_api( 'POST', '/' . $alice . '/enrollments', array( 'course_id' => $course_a ) );
	ok( 409 === $dupe->get_status() && 'ohmylms_student_already_enrolled' === $dupe->get_data()['code'], 'double enrol is 409' );
	ok( 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE user_id = %d AND course_id = %d", $alice, $course_a ) ), 'double enrol created no second row' );
	ok( 1 === count( $fired['enrolled'] ), 'a rejected enrol fires no hook' );
	ok( 404 === call_api( 'POST', '/' . $alice . '/enrollments', array( 'course_id' => 999999999 ) )->get_status(), 'unknown course is 404' );
	$not_course = call_api( 'POST', '/' . $alice . '/enrollments', array( 'course_id' => $page_id ) );
	ok( 404 === $not_course->get_status() && 'ohmylms_course_not_found' === $not_course->get_data()['code'], 'a page is not a course' );
	ok( 404 === call_api( 'POST', '/999999999/enrollments', array( 'course_id' => $course_a ) )->get_status(), 'unknown student cannot be enrolled' );
	foreach ( array( array(), array( 'course_id' => 0 ), array( 'course_id' => 'abc' ) ) as $bad ) {
		ok( 400 === call_api( 'POST', '/' . $alice . '/enrollments', $bad )->get_status(), 'invalid enrol body rejected: ' . wp_json_encode( $bad ) );
	}
	ok( 201 === call_api( 'POST', '/' . $alice . '/enrollments', array( 'course_id' => $course_b ) )->get_status(), 'enrol in a second course' );
	ok( 201 === call_api( 'POST', '/' . $bob . '/enrollments', array( 'course_id' => $course_a ) )->get_status(), 'enrol a second student' );

	// Enrolments and progress.
	$enrollment_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE user_id = %d AND course_id = %d", $alice, $course_a ) );
	$wpdb->insert(
		$wpdb->prefix . 'ohmylms_user_progress',
		array(
			'enrollment_id' => $enrollment_id,
			'content_id'    => 424242,
			'content_type'  => 'quiz',
			'status'        => 'completed',
			'start_date'    => current_time( 'mysql' ),
		)
	);
	$response = call_api( 'GET', '/' . $alice . '/enrollments' );
	ok( 200 === $response->get_status() && '2' === $response->get_headers()['X-WP-Total'], 'enrolment list' );
	ok( 2 === count( $response->get_data() ) && isset( $response->get_data()[0]['lessons']['total'] ), 'enrolment items carry progress counters' );
	ok( 0 === $response->get_data()[0]['quizzes']['completed'] || 0 === $response->get_data()[1]['quizzes']['completed'], 'completed items never exceed the course total (course has no quizzes)' );
	$response = call_api( 'GET', '/' . $alice . '/enrollments', array( 'search' => 'bravo' ) );
	ok( 1 === count( $response->get_data() ) && $course_b === $response->get_data()[0]['course_id'], 'enrolment search by course title' );
	$response = call_api( 'GET', '/' . $alice . '/enrollments', array( 'progress' => 'completed' ) );
	ok( 0 === count( $response->get_data() ) && '0' === $response->get_headers()['X-WP-Total'], 'completed filter is empty before completion' );
	\OhMyLMS\Student\Enrollments::update(
		array(
			'progress' => 'completed',
			'end_date' => current_time( 'mysql' ),
		),
		array( 'id' => $enrollment_id )
	);
	$response = call_api( 'GET', '/' . $alice . '/enrollments', array( 'progress' => 'completed' ) );
	ok( 1 === count( $response->get_data() ) && 100 === $response->get_data()[0]['percent_complete'] && null !== $response->get_data()[0]['completed_at'], 'completed enrolment is 100 percent with a date' );
	ok( 400 === call_api( 'GET', '/' . $alice . '/enrollments', array( 'progress' => 'later' ) )->get_status(), 'invalid progress filter' );
	ok( 400 === call_api( 'GET', '/' . $alice . '/enrollments', array( 'per_page' => 101 ) )->get_status(), 'enrolment per_page capped' );
	ok( 400 === call_api( 'GET', '/' . $alice . '/enrollments', array( 'page' => 5 ) )->get_status(), 'enrolment page past the end is 400' );

	$response = call_api( 'GET', '', array( 'search' => $tag ) );
	$by_id    = array_column( $response->get_data(), null, 'id' );
	ok( 2 === $by_id[ $alice ]['enrolled_courses'] && 1 === $by_id[ $alice ]['completed_courses'], 'list counts enrolled and completed courses' );
	ok( 1 === $by_id[ $bob ]['enrolled_courses'], 'list counts per student' );
	$response = call_api(
		'GET',
		'',
		array(
			'course' => $course_a,
			'search' => $tag,
		)
	);
	ok( 2 === count( $response->get_data() ) && '2' === $response->get_headers()['X-WP-Total'], 'filter by course returns both enrolled students' );
	ok( 2 === array_column( $response->get_data(), null, 'id' )[ $alice ]['enrolled_courses'], 'course filter keeps the all-courses count' );
	$response = call_api(
		'GET',
		'',
		array(
			'search'  => $tag,
			'orderby' => 'courses_enrolled',
			'order'   => 'desc',
		)
	);
	ok( $alice === $response->get_data()[0]['id'], 'order by courses_enrolled' );

	// Block and unblock.
	$response = call_api( 'POST', '/' . $bob, array( 'blocked' => true ) );
	ok( 200 === $response->get_status() && true === $response->get_data()['blocked'], 'block' );
	ok( 'banned' === $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE user_id = %d", $bob ) ), 'block bans the enrolments' );
	ok( array( $bob ) === $fired['blocked'], 'ohmylms_student_blocked fired' );
	$response = call_api( 'GET', '/' . $bob . '/enrollments' );
	ok( 'banned' === $response->get_data()[0]['status'], 'banned status shows in the enrolment list' );
	$blocked = call_api( 'POST', '/' . $bob . '/enrollments', array( 'course_id' => $course_b ) );
	ok( 409 === $blocked->get_status() && 'ohmylms_student_blocked' === $blocked->get_data()['code'], 'a blocked student cannot be enrolled' );
	$response = call_api( 'POST', '/' . $bob, array( 'blocked' => false ) );
	ok( 200 === $response->get_status() && false === $response->get_data()['blocked'], 'unblock' );
	ok( 'enrolled' === $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE user_id = %d", $bob ) ), 'unblock restores the enrolments' );
	ok( array( $bob ) === $fired['unblocked'], 'ohmylms_student_unblocked fired' );
	ok( 400 === call_api( 'POST', '/' . $bob, array() )->get_status(), 'blocked is required' );
	ok( 400 === call_api( 'POST', '/' . $bob, array( 'blocked' => 'maybe' ) )->get_status(), 'blocked must be boolean' );
	$self = call_api( 'POST', '/' . $admin, array( 'blocked' => true ) );
	ok( in_array( $self->get_status(), array( 404, 422 ), true ) && 'yes' !== get_user_meta( $admin, '_ohmylms_banned_student', true ), 'an administrator cannot be blocked' );
	\OhMyLMS\Student\Enrollments::create(
		array(
			'user_id'    => $admin,
			'course_id'  => $course_b,
			'status'     => 'enrolled',
			'progress'   => 'running',
			'start_date' => current_time( 'mysql' ),
		),
		array( 'source' => 'manual' )
	);
	$self = call_api( 'POST', '/' . $admin, array( 'blocked' => true ) );
	ok( 422 === $self->get_status() && 'ohmylms_student_protected' === $self->get_data()['code'], 'an enrolled administrator cannot be blocked (422)' );
	\OhMyLMS\Student\Enrollments::delete(
		array(
			'user_id'   => $admin,
			'course_id' => $course_b,
		)
	);

	// Unenrol.
	$response = call_api( 'DELETE', '/' . $alice . '/enrollments/' . $course_a );
	ok( 200 === $response->get_status() && true === $response->get_data()['deleted'] && $course_a === $response->get_data()['previous']['course_id'], 'unenrol returns the previous enrolment' );
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE user_id = %d AND course_id = %d", $alice, $course_a ) ), 'enrolment row removed' );
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ohmylms_user_progress WHERE enrollment_id = %d", $enrollment_id ) ), 'progress rows removed with the enrolment' );
	ok( array( array( $alice, $course_a ) ) === $fired['unenrolled'], 'ohmylms_student_unenrolled fired' );
	ok( 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE user_id = %d AND course_id = %d", $alice, $course_b ) ), 'other enrolments untouched' );
	$again = call_api( 'DELETE', '/' . $alice . '/enrollments/' . $course_a );
	ok( 404 === $again->get_status() && 'ohmylms_enrollment_not_found' === $again->get_data()['code'], 'second unenrol is 404' );
	ok( 1 === count( $fired['unenrolled'] ), 'a rejected unenrol fires no hook' );
	ok( 201 === call_api( 'POST', '/' . $alice . '/enrollments', array( 'course_id' => $course_a ) )->get_status(), 're-enrol after unenrol' );

	// Schema.
	$schema = rest_do_request( new WP_REST_Request( 'OPTIONS', '/ohmylms/v1/students' ) )->get_data()['schema'];
	ok( 'student' === $schema['title'] && 'http://json-schema.org/draft-04/schema#' === $schema['$schema'], 'schema header' );
	foreach ( array( 'id', 'name', 'email', 'avatar_url', 'registered', 'last_login', 'blocked', 'enrolled_courses', 'completed_courses' ) as $property ) {
		ok( isset( $schema['properties'][ $property ] ), "schema property $property" );
	}
	ok( ! isset( $schema['properties']['user_pass'] ), 'schema has no secret fields' );
	$schema = rest_do_request( new WP_REST_Request( 'OPTIONS', '/ohmylms/v1/students/' . $alice . '/enrollments' ) )->get_data()['schema'];
	ok( 'student-enrollment' === $schema['title'] && isset( $schema['properties']['percent_complete'] ), 'enrolment schema' );

	fwrite( STDOUT, "OK students-rest-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	// Remove only what this run created.
	foreach ( $user_ids as $user_id ) {
		$leftovers = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE user_id = %d", $user_id ) );
		foreach ( $leftovers as $leftover ) {
			$wpdb->delete( $wpdb->prefix . 'ohmylms_user_progress', array( 'enrollment_id' => (int) $leftover ), array( '%d' ) );
		}
		\OhMyLMS\Student\Enrollments::delete( array( 'user_id' => (int) $user_id ), array( '%d' ) );
	}
	foreach ( $course_ids as $course_id ) {
		\OhMyLMS\Student\Enrollments::delete( array( 'course_id' => (int) $course_id ), array( '%d' ) );
		wp_delete_post( $course_id, true );
	}
	if ( isset( $page_id ) && $page_id && ! is_wp_error( $page_id ) ) {
		wp_delete_post( $page_id, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
}

exit( isset( $exit_code ) ? $exit_code : 0 );
