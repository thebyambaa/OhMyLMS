<?php
/**
 * Account Hub REST API integration test.
 *
 * Runs against a WordPress install (the Local site by default) with real database tables. It only
 * creates tagged schools, classes, memberships, invitations, guardian links and temporary users, and
 * removes all of them in `finally`.
 *
 * Usage (set OHMYLMS_ACCOUNT_HUB_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/account-hub-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, Generic.Formatting.MultipleStatementAlignment, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_ACCOUNT_HUB_TEST' ) ) {
	exit( "Set OHMYLMS_ACCOUNT_HUB_TEST=1 to run the Account Hub REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Schools\Schema;
use OhMyLMS\Schools\Service;

global $wpdb;

$checks     = 0;
$school_ids = array();
$user_ids   = array();
$tag        = 'ahtest' . strtolower( wp_generate_password( 6, false, false ) );

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
 * Dispatch a REST request below /ohmylms/v1/account-hub.
 *
 * @param string $method HTTP method.
 * @param string $path   Path.
 * @param array  $data   Body (JSON) or query (GET, DELETE).
 * @return WP_REST_Response
 */
function hub_api( $method, $path, array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/account-hub' . $path );
	if ( in_array( $method, array( 'GET', 'DELETE' ), true ) ) {
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
 * Create a temporary user.
 *
 * @param string $name  Login suffix.
 * @param string $role  Primary role.
 * @return int
 */
function hub_user( $name, $role = 'subscriber' ) {
	global $tag, $user_ids;
	$id = wp_insert_user(
		array(
			'user_login'   => $tag . '_' . $name,
			'user_pass'    => wp_generate_password( 24 ),
			'user_email'   => $tag . '_' . $name . '@example.invalid',
			'display_name' => $tag . ' ' . $name,
			'role'         => $role,
		)
	);
	ok( ! is_wp_error( $id ), "user $name created" );
	$user_ids[] = $id;
	return $id;
}

/**
 * Put a user on a school roster.
 *
 * @param int    $school School id.
 * @param int    $user   User id.
 * @param string $role   Membership role.
 */
function hub_roster( $school, $user, $role ) {
	global $wpdb;
	$wpdb->insert(
		Schema::table( 'school_memberships' ),
		array(
			'school_id' => $school,
			'user_id'   => $user,
			'role'      => $role,
			'status'    => 'active',
			'joined_at' => gmdate( 'Y-m-d H:i:s' ),
		)
	);
	ok( $wpdb->insert_id > 0, "roster $role $user" );
}

try {
	Schema::install();
	ok( get_option( 'ohmylms_school_schema' ) === Schema::VERSION, 'school schema installed' );
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

	$subscriber = hub_user( 'sub' );
	$manager    = hub_user( 'manager' );
	$teacher    = hub_user( 'teacher', 'ohmylms_teacher' );
	$outsider   = hub_user( 'outsider', 'ohmylms_teacher' );
	$student    = hub_user( 'student' );
	$student2   = hub_user( 'student2' );
	$student_b  = hub_user( 'studentb' );
	$parent     = hub_user( 'parent', 'ohmylms_parent' );

	// Routes and permission callbacks.
	$routes = rest_get_server()->get_routes( 'ohmylms/v1' );
	$paths  = array(
		'/schools',
		'/schools/(?P<id>[\d]+)',
		'/schools/(?P<id>[\d]+)/years',
		'/schools/(?P<id>[\d]+)/classes',
		'/schools/(?P<id>[\d]+)/students',
		'/classes',
		'/classes/(?P<id>[\d]+)',
		'/classes/(?P<id>[\d]+)/archive',
		'/classes/(?P<id>[\d]+)/restore',
		'/classes/(?P<id>[\d]+)/members',
		'/classes/(?P<id>[\d]+)/members/(?P<user_id>[\d]+)',
		'/classes/(?P<id>[\d]+)/candidates',
		'/teachers',
		'/parents',
		'/parents/(?P<id>[\d]+)/children',
		'/parents/(?P<id>[\d]+)/children/(?P<link>[\d]+)',
		'/parents/(?P<id>[\d]+)/invitations/(?P<invitation>[\d]+)',
	);
	foreach ( $paths as $route ) {
		$key = '/ohmylms/v1/account-hub' . $route;
		ok( isset( $routes[ $key ] ), 'route registered: ' . $route );
		foreach ( $routes[ $key ] as $endpoint ) {
			ok( is_callable( $endpoint['permission_callback'] ) && '__return_true' !== $endpoint['permission_callback'], "real permission_callback on $route" );
		}
	}

	$denied = array(
		array( 'GET', '/schools' ),
		array( 'POST', '/schools', array( 'name' => $tag . ' denied' ) ),
		array( 'GET', '/schools/1' ),
		array( 'PATCH', '/schools/1', array( 'name' => 'x' ) ),
		array(
			'POST',
			'/schools/1/years',
			array(
				'label'     => 'x',
				'starts_at' => '2026-09-01',
				'ends_at'   => '2027-06-30',
			),
		),
		array( 'GET', '/schools/1/classes' ),
		array(
			'POST',
			'/schools/1/classes',
			array(
				'name'             => 'x',
				'academic_year_id' => 1,
			),
		),
		array( 'GET', '/schools/1/students' ),
		array( 'GET', '/classes' ),
		array( 'GET', '/classes/1' ),
		array( 'PATCH', '/classes/1', array( 'name' => 'x' ) ),
		array( 'POST', '/classes/1/archive' ),
		array( 'POST', '/classes/1/restore' ),
		array( 'GET', '/classes/1/members' ),
		array(
			'POST',
			'/classes/1/members',
			array(
				'user_id' => 1,
				'role'    => 'student',
			),
		),
		array( 'DELETE', '/classes/1/members/1', array( 'role' => 'student' ) ),
		array( 'GET', '/classes/1/candidates', array( 'role' => 'student' ) ),
		array( 'GET', '/teachers' ),
		array( 'GET', '/parents' ),
		array( 'GET', '/parents/1/children' ),
		array(
			'POST',
			'/parents/1/children',
			array(
				'school_id'  => 1,
				'student_id' => 1,
			),
		),
		array( 'DELETE', '/parents/1/children/1' ),
		array( 'DELETE', '/parents/1/invitations/1' ),
	);

	// Anonymous: 401. Subscriber and a teacher without a school-administrator role: 403.
	foreach ( array(
		0           => 401,
		$subscriber => 403,
		$teacher    => 403,
	) as $user => $status ) {
		wp_set_current_user( $user );
		foreach ( $denied as $case ) {
			$response = hub_api( $case[0], $case[1], $case[2] ?? array() );
			ok( $status === $response->get_status(), "user $user {$case[0]} {$case[1]} is $status, got " . $response->get_status() );
		}
	}
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE name=%s', Schema::table( 'schools' ), $tag . ' denied' ) ), 'forbidden create wrote nothing' );

	wp_set_current_user( $admin );

	// Schools: validation, create, read, list.
	ok_error( hub_api( 'POST', '/schools', array() ), 400, 'rest_missing_callback_param', 'name required' );
	ok_error( hub_api( 'POST', '/schools', array( 'name' => '   ' ) ), 422, 'ohmylms_account_hub_invalid_name', 'blank name' );
	ok_error(
		hub_api(
			'POST',
			'/schools',
			array(
				'name'     => $tag . ' bad',
				'timezone' => 'Mars/Olympus',
			)
		),
		422,
		'ohmylms_account_hub_invalid_timezone',
		'unknown time zone'
	);
	ok_error( hub_api( 'POST', '/schools', array( 'name' => str_repeat( 'x', 191 ) ) ), 400, 'rest_invalid_param', 'name too long' );

	$created = hub_api(
		'POST',
		'/schools',
		array(
			'name'     => $tag . ' Alpha',
			'timezone' => 'Asia/Ulaanbaatar',
		)
	);
	ok( 201 === $created->get_status(), 'school created: ' . wp_json_encode( $created->get_data() ) );
	$alpha        = $created->get_data();
	$school_ids[] = $alpha['id'];
	ok( rest_url( 'ohmylms/v1/account-hub/schools/' . $alpha['id'] ) === $created->get_headers()['Location'], 'Location header' );
	ok( 'Asia/Ulaanbaatar' === $alpha['timezone'] && 'active' === $alpha['status'] && array() === $alpha['years'] && 16 === strlen( $alpha['version'] ), 'school detail shape' );
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE school_id=%d', Schema::table( 'school_memberships' ), $alpha['id'] ) ), 'a platform-created school gets no implicit administrator' );

	$beta         = hub_api( 'POST', '/schools', array( 'name' => $tag . ' Beta' ) )->get_data();
	$school_ids[] = $beta['id'];
	ok( 'UTC' === $beta['timezone'], 'time zone defaults to UTC' );

	hub_roster( $alpha['id'], $manager, 'school_admin' );
	hub_roster( $alpha['id'], $teacher, 'teacher' );
	hub_roster( $alpha['id'], $student, 'student' );
	hub_roster( $alpha['id'], $student2, 'student' );
	hub_roster( $beta['id'], $student_b, 'student' );
	hub_roster( $beta['id'], $outsider, 'teacher' );

	$list = hub_api(
		'GET',
		'/schools',
		array(
			'search'   => $tag,
			'per_page' => 1,
		)
	);
	ok( 200 === $list->get_status() && '2' === $list->get_headers()['X-WP-Total'] && '2' === $list->get_headers()['X-WP-TotalPages'] && 1 === count( $list->get_data() ), 'paged school list with totals' );
	$row = $list->get_data()[0];
	ok( array( 'id', 'name', 'slug', 'timezone', 'status', 'created_at', 'class_count', 'student_count', 'teacher_count', 'version' ) === array_keys( $row ), 'school list fields are exactly the documented ones' );
	ok( $alpha['id'] === $row['id'] && 2 === $row['student_count'] && 1 === $row['teacher_count'] && 0 === $row['class_count'], 'school counts' );
	ok_error(
		hub_api(
			'GET',
			'/schools',
			array(
				'search' => $tag,
				'page'   => 3,
			)
		),
		400,
		'ohmylms_account_hub_invalid_page',
		'school page past the end'
	);
	ok_error( hub_api( 'GET', '/schools', array( 'per_page' => 101 ) ), 400, 'rest_invalid_param', 'per_page above 100' );
	ok_error( hub_api( 'GET', '/schools/999999999' ), 404, 'ohmylms_account_hub_school_not_found', 'unknown school' );

	// School update with stale-save protection.
	$version = $alpha['version'];
	$renamed = hub_api(
		'PATCH',
		'/schools/' . $alpha['id'],
		array(
			'name'    => $tag . ' Alpha renamed',
			'version' => $version,
		)
	);
	ok( 200 === $renamed->get_status() && $tag . ' Alpha renamed' === $renamed->get_data()['name'] && 'Asia/Ulaanbaatar' === $renamed->get_data()['timezone'], 'school renamed, time zone kept' );
	ok( $alpha['slug'] === $renamed->get_data()['slug'] && $version !== $renamed->get_data()['version'], 'slug stable, version changed' );
	ok( $tag . ' Alpha renamed' === $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM %i WHERE id=%d', Schema::table( 'schools' ), $alpha['id'] ) ), 'rename persisted' );
	$stale = hub_api(
		'PATCH',
		'/schools/' . $alpha['id'],
		array(
			'name'    => $tag . ' lost update',
			'version' => $version,
		)
	);
	ok_error( $stale, 409, 'ohmylms_account_hub_conflict', 'stale school save' );
	ok( $renamed->get_data()['version'] === $stale->get_data()['data']['version'], 'conflict reports the current version' );
	ok( $tag . ' Alpha renamed' === $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM %i WHERE id=%d', Schema::table( 'schools' ), $alpha['id'] ) ), 'stale save changed nothing' );
	ok_error( hub_api( 'PATCH', '/schools/' . $alpha['id'], array( 'timezone' => 'Nowhere' ) ), 422, 'ohmylms_account_hub_invalid_timezone', 'update time zone validated' );
	ok_error( hub_api( 'PATCH', '/schools/' . $alpha['id'], array( 'version' => 'zz' ) ), 400, 'rest_invalid_param', 'version format' );

	// Academic years and classes.
	ok_error(
		hub_api(
			'POST',
			'/schools/' . $alpha['id'] . '/years',
			array(
				'label'     => '2026',
				'starts_at' => '2026-02-30',
				'ends_at'   => '2027-06-30',
			)
		),
		422,
		'ohmylms_account_hub_invalid_dates',
		'impossible date'
	);
	ok_error(
		hub_api(
			'POST',
			'/schools/' . $alpha['id'] . '/years',
			array(
				'label'     => '2026',
				'starts_at' => '2027-06-30',
				'ends_at'   => '2026-09-01',
			)
		),
		422,
		'ohmylms_account_hub_invalid_dates',
		'dates out of order'
	);
	$with_year = hub_api(
		'POST',
		'/schools/' . $alpha['id'] . '/years',
		array(
			'label'     => '2026-2027',
			'starts_at' => '2026-09-01',
			'ends_at'   => '2027-06-30',
		)
	);
	ok( 201 === $with_year->get_status() && 1 === count( $with_year->get_data()['years'] ), 'academic year created' );
	$year = $with_year->get_data()['years'][0];
	ok( '2026-2027' === $year['label'] && 'active' === $year['status'], 'year shape' );
	$beta_year = hub_api(
		'POST',
		'/schools/' . $beta['id'] . '/years',
		array(
			'label'     => 'B',
			'starts_at' => '2026-09-01',
			'ends_at'   => '2027-06-30',
		)
	)->get_data()['years'][0];

	ok_error(
		hub_api(
			'POST',
			'/schools/' . $alpha['id'] . '/classes',
			array(
				'name'             => $tag . ' wrong year',
				'academic_year_id' => $beta_year['id'],
			)
		),
		422,
		'ohmylms_account_hub_invalid_year',
		'another school\'s year'
	);
	$class_response = hub_api(
		'POST',
		'/schools/' . $alpha['id'] . '/classes',
		array(
			'name'             => $tag . ' Math',
			'subject'          => 'Math',
			'grade'            => '5',
			'academic_year_id' => $year['id'],
		)
	);
	ok( 201 === $class_response->get_status(), 'class created: ' . wp_json_encode( $class_response->get_data() ) );
	$class = $class_response->get_data();
	ok( rest_url( 'ohmylms/v1/account-hub/classes/' . $class['id'] ) === $class_response->get_headers()['Location'], 'class Location header' );
	ok( array( 'id', 'name', 'subject', 'grade', 'status', 'school_id', 'school_name', 'academic_year_id', 'academic_year_label', 'student_count', 'teacher_count', 'version' ) === array_keys( $class ), 'class fields are exactly the documented ones' );
	ok( $alpha['id'] === $class['school_id'] && $tag . ' Alpha renamed' === $class['school_name'] && '2026-2027' === $class['academic_year_label'] && 'active' === $class['status'], 'class shape' );
	$beta_class = hub_api(
		'POST',
		'/schools/' . $beta['id'] . '/classes',
		array(
			'name'             => $tag . ' Beta class',
			'academic_year_id' => $beta_year['id'],
		)
	)->get_data();

	$classes = hub_api( 'GET', '/classes', array( 'search' => $tag ) );
	ok( '2' === $classes->get_headers()['X-WP-Total'], 'admin lists classes of every school' );
	ok( '1' === hub_api( 'GET', '/schools/' . $alpha['id'] . '/classes', array( 'search' => $tag ) )->get_headers()['X-WP-Total'], 'school class list' );
	ok(
		'1' === hub_api(
			'GET',
			'/classes',
			array(
				'search'    => $tag,
				'school_id' => $beta['id'],
			)
		)->get_headers()['X-WP-Total'],
		'school filter on the class list'
	);

	$edited = hub_api(
		'PUT',
		'/classes/' . $class['id'],
		array(
			'name'    => $tag . ' Algebra',
			'version' => $class['version'],
		)
	);
	ok( 200 === $edited->get_status() && $tag . ' Algebra' === $edited->get_data()['name'] && 'Math' === $edited->get_data()['subject'] && '5' === $edited->get_data()['grade'], 'class renamed, other fields kept' );
	ok_error(
		hub_api(
			'PUT',
			'/classes/' . $class['id'],
			array(
				'name'    => $tag . ' lost',
				'version' => $class['version'],
			)
		),
		409,
		'ohmylms_account_hub_conflict',
		'stale class save'
	);
	ok( $tag . ' Algebra' === $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM %i WHERE id=%d', Schema::table( 'classes' ), $class['id'] ) ), 'class rename persisted once' );

	$archived = hub_api( 'POST', '/classes/' . $class['id'] . '/archive' );
	ok( 200 === $archived->get_status() && 'archived' === $archived->get_data()['status'], 'class archived' );
	ok( null !== Service::row( 'classes', $class['id'] ), 'archived class still exists' );
	ok_error( hub_api( 'POST', '/classes/' . $class['id'] . '/archive' ), 409, 'ohmylms_account_hub_class_archived', 'archive twice' );
	ok_error( hub_api( 'PATCH', '/classes/' . $class['id'], array( 'name' => 'x' ) ), 409, 'ohmylms_account_hub_class_archived', 'archived class is read only' );
	ok(
		'1' === hub_api(
			'GET',
			'/classes',
			array(
				'search' => $tag,
				'status' => 'archived',
			)
		)->get_headers()['X-WP-Total'],
		'status filter'
	);
	$restored = hub_api( 'POST', '/classes/' . $class['id'] . '/restore' );
	ok( 200 === $restored->get_status() && 'active' === $restored->get_data()['status'], 'class restored' );
	ok_error( hub_api( 'POST', '/classes/' . $class['id'] . '/restore' ), 409, 'ohmylms_account_hub_class_active', 'restore an active class' );
	ok_error( hub_api( 'GET', '/classes/999999999' ), 404, 'ohmylms_account_hub_class_not_found', 'unknown class' );

	// Class membership from the school roster only.
	$candidates = hub_api( 'GET', '/classes/' . $class['id'] . '/candidates', array( 'role' => 'student' ) );
	$ids        = wp_list_pluck( $candidates->get_data(), 'id' );
	ok( '2' === $candidates->get_headers()['X-WP-Total'] && in_array( $student, $ids, true ) && in_array( $student2, $ids, true ) && ! in_array( $student_b, $ids, true ), 'student candidates come from this school only' );
	ok( array( 'id', 'display_name' ) === array_keys( $candidates->get_data()[0] ), 'candidate fields are only id and display name' );
	ok_error( hub_api( 'GET', '/classes/' . $class['id'] . '/candidates' ), 400, 'rest_missing_callback_param', 'candidates need a role' );

	$added = hub_api(
		'POST',
		'/classes/' . $class['id'] . '/members',
		array(
			'user_id' => $student,
			'role'    => 'student',
		)
	);
	ok( 201 === $added->get_status() && $student === $added->get_data()['user_id'] && 'student' === $added->get_data()['role'], 'student added' );
	ok_error(
		hub_api(
			'POST',
			'/classes/' . $class['id'] . '/members',
			array(
				'user_id' => $student,
				'role'    => 'student',
			)
		),
		409,
		'ohmylms_account_hub_already_member',
		'add twice'
	);
	ok_error(
		hub_api(
			'POST',
			'/classes/' . $class['id'] . '/members',
			array(
				'user_id' => $student_b,
				'role'    => 'student',
			)
		),
		422,
		'ohmylms_account_hub_not_on_roster',
		'student of another school'
	);
	ok_error(
		hub_api(
			'POST',
			'/classes/' . $class['id'] . '/members',
			array(
				'user_id' => $student2,
				'role'    => 'teacher',
			)
		),
		422,
		'ohmylms_account_hub_not_on_roster',
		'a student cannot join as a teacher'
	);
	ok(
		201 === hub_api(
			'POST',
			'/classes/' . $class['id'] . '/members',
			array(
				'user_id' => $teacher,
				'role'    => 'teacher',
			)
		)->get_status(),
		'teacher added'
	);
	$members = hub_api( 'GET', '/classes/' . $class['id'] . '/members' );
	ok( '2' === $members->get_headers()['X-WP-Total'] && 'teacher' === $members->get_data()[0]['role'], 'members listed, teachers first' );
	ok( array( 'id', 'user_id', 'display_name', 'role', 'joined_at' ) === array_keys( $members->get_data()[0] ), 'member fields contain no email or login' );
	ok( 1 === hub_api( 'GET', '/classes/' . $class['id'] )->get_data()['student_count'], 'student_count follows the roster' );
	ok( '1' === hub_api( 'GET', '/classes/' . $class['id'] . '/members', array( 'role' => 'student' ) )->get_headers()['X-WP-Total'], 'member role filter' );

	$removed = hub_api( 'DELETE', '/classes/' . $class['id'] . '/members/' . $student, array( 'role' => 'student' ) );
	ok( 200 === $removed->get_status() && true === $removed->get_data()['removed'], 'student removed' );
	ok( 'inactive' === $wpdb->get_var( $wpdb->prepare( "SELECT status FROM %i WHERE class_id=%d AND user_id=%d AND role='student'", Schema::table( 'class_memberships' ), $class['id'], $student ) ), 'removal keeps the row as inactive' );
	ok_error( hub_api( 'DELETE', '/classes/' . $class['id'] . '/members/' . $student, array( 'role' => 'student' ) ), 404, 'ohmylms_account_hub_member_not_found', 'remove twice' );
	ok(
		201 === hub_api(
			'POST',
			'/classes/' . $class['id'] . '/members',
			array(
				'user_id' => $student,
				'role'    => 'student',
			)
		)->get_status(),
		'a removed student can rejoin'
	);

	$students = hub_api( 'GET', '/schools/' . $alpha['id'] . '/students', array( 'search' => $tag ) );
	ok( '2' === $students->get_headers()['X-WP-Total'] && array( 'id', 'display_name' ) === array_keys( $students->get_data()[0] ), 'school student search is minimal' );

	// Teachers.
	$teachers = hub_api( 'GET', '/teachers', array( 'search' => $tag ) );
	$by_id    = array_column( $teachers->get_data(), null, 'id' );
	ok( '2' === $teachers->get_headers()['X-WP-Total'] && isset( $by_id[ $teacher ], $by_id[ $outsider ] ), 'admin sees every teacher' );
	ok( array( 'id', 'display_name', 'email', 'schools', 'class_count' ) === array_keys( $by_id[ $teacher ] ), 'teacher fields are exactly the documented ones' );
	ok( $tag . '_teacher@example.invalid' === $by_id[ $teacher ]['email'] && array( $alpha['id'] ) === wp_list_pluck( $by_id[ $teacher ]['schools'], 'id' ) && 1 === $by_id[ $teacher ]['class_count'], 'teacher row' );
	ok(
		'1' === hub_api(
			'GET',
			'/teachers',
			array(
				'search'    => $tag,
				'school_id' => $beta['id'],
			)
		)->get_headers()['X-WP-Total'],
		'teacher school filter'
	);

	// Parents and parent-child linking through a guardian invitation.
	$parents = hub_api( 'GET', '/parents', array( 'search' => $tag ) );
	ok( '1' === $parents->get_headers()['X-WP-Total'] && $parent === $parents->get_data()[0]['id'] && 0 === $parents->get_data()[0]['child_count'], 'parent listed by role' );
	ok( array( 'id', 'display_name', 'email', 'child_count' ) === array_keys( $parents->get_data()[0] ), 'parent fields are exactly the documented ones' );
	ok_error( hub_api( 'GET', '/parents/' . $subscriber . '/children' ), 404, 'ohmylms_account_hub_parent_not_found', 'a non-parent is not a parent' );
	ok_error(
		hub_api(
			'POST',
			'/parents/' . $parent . '/children',
			array(
				'school_id'  => $alpha['id'],
				'student_id' => $student_b,
			)
		),
		422,
		'ohmylms_account_hub_not_on_roster',
		'link a student of another school'
	);
	$invite = hub_api(
		'POST',
		'/parents/' . $parent . '/children',
		array(
			'school_id'  => $alpha['id'],
			'student_id' => $student,
		)
	);
	ok( 201 === $invite->get_status() && false !== strpos( $invite->get_data()['url'], 'ohmylms_invite=' ) && false === $invite->get_data()['email_sent'], 'guardian invitation created, not mailed' );
	$invitation = Service::row( 'school_invitations', $invite->get_data()['id'] );
	ok( 'guardian' === $invitation['role'] && $tag . '_parent@example.invalid' === $invitation['email'] && $student === (int) $invitation['student_user_id'], 'invitation addressed to the parent account' );
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE guardian_user_id=%d', Schema::table( 'guardian_links' ), $parent ) ), 'no link before the parent accepts' );
	ok_error(
		hub_api(
			'POST',
			'/parents/' . $parent . '/children',
			array(
				'school_id'  => $alpha['id'],
				'student_id' => $student,
			)
		),
		409,
		'ohmylms_account_hub_invitation_pending',
		'one pending invitation per pair'
	);
	$children = hub_api( 'GET', '/parents/' . $parent . '/children' )->get_data();
	ok( array() === $children['children'] && 1 === count( $children['pending'] ) && $student === $children['pending'][0]['student_id'], 'pending invitation listed' );

	parse_str( (string) wp_parse_url( $invite->get_data()['url'], PHP_URL_QUERY ), $query );
	wp_set_current_user( $parent );
	Service::accept( $query['ohmylms_invite'] );
	wp_set_current_user( $admin );
	$children = hub_api( 'GET', '/parents/' . $parent . '/children' )->get_data();
	ok( 1 === count( $children['children'] ) && $student === $children['children'][0]['student_id'] && array() === $children['pending'], 'accepted invitation becomes a link' );
	ok( array( 'id', 'school_id', 'school_name', 'student_id', 'student_name', 'linked_at' ) === array_keys( $children['children'][0] ), 'child fields' );
	ok( 1 === hub_api( 'GET', '/parents', array( 'search' => $tag ) )->get_data()[0]['child_count'], 'child_count' );
	ok_error(
		hub_api(
			'POST',
			'/parents/' . $parent . '/children',
			array(
				'school_id'  => $alpha['id'],
				'student_id' => $student,
			)
		),
		409,
		'ohmylms_account_hub_already_linked',
		'link twice'
	);
	$link     = $children['children'][0]['id'];
	$unlinked = hub_api( 'DELETE', '/parents/' . $parent . '/children/' . $link );
	ok( 200 === $unlinked->get_status() && true === $unlinked->get_data()['revoked'], 'link revoked' );
	ok( 'inactive' === $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM %i WHERE id=%d', Schema::table( 'guardian_links' ), $link ) ), 'revoked link stays as inactive' );
	ok_error( hub_api( 'DELETE', '/parents/' . $parent . '/children/' . $link ), 404, 'ohmylms_account_hub_link_not_found', 'revoke twice' );

	$second = hub_api(
		'POST',
		'/parents/' . $parent . '/children',
		array(
			'school_id'  => $alpha['id'],
			'student_id' => $student2,
		)
	)->get_data();
	ok( 200 === hub_api( 'DELETE', '/parents/' . $parent . '/invitations/' . $second['id'] )->get_status(), 'pending invitation revoked' );
	ok( null !== Service::row( 'school_invitations', $second['id'] )['revoked_at'], 'revocation persisted' );
	ok_error( hub_api( 'DELETE', '/parents/' . $parent . '/invitations/' . $second['id'] ), 404, 'ohmylms_account_hub_invitation_not_found', 'revoke invitation twice' );

	// A school administrator is limited to their own school.
	wp_set_current_user( $manager );
	$mine = hub_api( 'GET', '/schools', array( 'search' => $tag ) );
	ok( 200 === $mine->get_status() && '1' === $mine->get_headers()['X-WP-Total'] && $alpha['id'] === $mine->get_data()[0]['id'], 'manager lists only their school' );
	ok_error( hub_api( 'GET', '/schools/' . $beta['id'] ), 403, 'ohmylms_account_hub_forbidden', 'manager reads another school' );
	ok_error( hub_api( 'PATCH', '/schools/' . $beta['id'], array( 'name' => 'x' ) ), 403, 'ohmylms_account_hub_forbidden', 'manager edits another school' );
	ok_error( hub_api( 'GET', '/classes/' . $beta_class['id'] ), 403, 'ohmylms_account_hub_forbidden', 'manager reads another school\'s class' );
	ok_error( hub_api( 'POST', '/classes/' . $beta_class['id'] . '/archive' ), 403, 'ohmylms_account_hub_forbidden', 'manager archives another school\'s class' );
	ok_error( hub_api( 'GET', '/classes', array( 'school_id' => $beta['id'] ) ), 403, 'ohmylms_account_hub_forbidden', 'manager filters by another school' );
	ok_error( hub_api( 'POST', '/schools', array( 'name' => $tag . ' manager school' ) ), 403, 'ohmylms_account_hub_cannot_create', 'manager cannot create schools here' );
	ok( '1' === hub_api( 'GET', '/classes', array( 'search' => $tag ) )->get_headers()['X-WP-Total'], 'manager lists only their classes' );
	$manager_teachers = hub_api( 'GET', '/teachers', array( 'search' => $tag ) );
	ok( '1' === $manager_teachers->get_headers()['X-WP-Total'] && $teacher === $manager_teachers->get_data()[0]['id'], 'manager sees only their school\'s teachers' );
	ok( null === $manager_teachers->get_data()[0]['email'], 'no email without list_users' );
	ok( '0' === hub_api( 'GET', '/parents', array( 'search' => $tag ) )->get_headers()['X-WP-Total'], 'manager sees no parent without a link in their school' );
	ok_error( hub_api( 'GET', '/parents/' . $parent . '/children' ), 404, 'ohmylms_account_hub_parent_not_found', 'manager cannot open an unrelated parent' );
	$manager_edit = hub_api( 'PATCH', '/classes/' . $class['id'], array( 'grade' => '6' ) );
	ok( 200 === $manager_edit->get_status() && '6' === $manager_edit->get_data()['grade'], 'manager edits their class' );
	ok( 200 === hub_api( 'DELETE', '/classes/' . $class['id'] . '/members/' . $student, array( 'role' => 'student' ) )->get_status(), 'manager changes their class roster' );

	// Schema.
	wp_set_current_user( $admin );
	$schema = rest_do_request( new WP_REST_Request( 'OPTIONS', '/ohmylms/v1/account-hub/schools' ) )->get_data()['schema'];
	ok( 'account-hub-school' === $schema['title'] && ! empty( $schema['properties']['version']['readonly'] ), 'school schema' );

	fwrite( STDOUT, "OK account-hub-rest-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	// Remove only what this run created.
	$school_ids = array_map( 'intval', array_merge( $school_ids, (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE name LIKE %s', Schema::table( 'schools' ), $wpdb->esc_like( $tag ) . '%' ) ) ) );
	$user_ids   = array_map( 'intval', $user_ids );
	if ( $school_ids ) {
		$in      = implode( ',', array_unique( $school_ids ) );
		$classes = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM %i WHERE school_id IN ($in)", Schema::table( 'classes' ) ) ) );
		if ( $classes ) {
			$class_in = implode( ',', $classes );
			$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE class_id IN ($class_in)", Schema::table( 'class_memberships' ) ) );
		}
		foreach ( array( 'classes', 'academic_years', 'school_memberships', 'guardian_links', 'school_invitations', 'school_student_profiles', 'school_audit_log' ) as $table ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE school_id IN ($in)", Schema::table( $table ) ) );
		}
		$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE id IN ($in)", Schema::table( 'schools' ) ) );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
}

exit( isset( $exit_code ) ? $exit_code : 0 );
