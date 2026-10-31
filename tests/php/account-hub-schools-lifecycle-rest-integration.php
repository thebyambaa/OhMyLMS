<?php
/**
 * Account Hub school workflows REST integration test: school archive/restore/delete, guardians, school
 * report and CSV, class work, year rollover, sample school, invitation acceptance and the retired
 * `ohmylms/v1/school/*` routes, with site administrator, school administrator, teacher and anonymous
 * callers.
 *
 * Runs against a WordPress install (the Local site by default). It creates tagged schools, users, one
 * course and its enrolments only, and removes all of them in `finally`. The sample school is created and
 * removed only when the site has none.
 *
 * Usage (set OHMYLMS_ACCOUNT_HUB_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/account-hub-schools-lifecycle-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, Generic.Formatting.MultipleStatementAlignment, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_ACCOUNT_HUB_TEST' ) ) {
	exit( "Set OHMYLMS_ACCOUNT_HUB_TEST=1 to run the Account Hub school workflow tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Schools\SamplePreview;
use OhMyLMS\Schools\Schema;
use OhMyLMS\Schools\Service;
use OhMyLMS\Student\EnrolmentSchema;
use OhMyLMS\Student\Enrollments;

global $wpdb;

$checks     = 0;
$school_ids = array();
$user_ids   = array();
$course_id  = 0;
$sample_new = false;
$tag        = 'ahlife' . strtolower( wp_generate_password( 6, false, false ) );

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
 * Dispatch a REST request below /ohmylms/v1.
 *
 * @param string $method HTTP method.
 * @param string $path   Path below /ohmylms/v1.
 * @param array  $data   Body (JSON) or query (GET, DELETE).
 * @param bool   $nonce  Send the REST nonce.
 * @return WP_REST_Response
 */
function life_api( $method, $path, array $data = array(), $nonce = false ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1' . $path );
	if ( in_array( $method, array( 'GET', 'DELETE' ), true ) ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	if ( $nonce ) {
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
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
	ok( $status === $response->get_status() && $code === $got, "$label: expected $status $code, got " . $response->get_status() . ' ' . $got . ' ' . wp_json_encode( $data ) );
}

/**
 * Assert a success status and return the body.
 *
 * @param WP_REST_Response $response Response.
 * @param int              $status   Expected status.
 * @param string           $label    Description.
 * @return mixed
 */
function ok_status( $response, $status, $label ) {
	ok( $status === $response->get_status(), "$label: expected $status, got " . $response->get_status() . ' ' . wp_json_encode( $response->get_data() ) );
	return $response->get_data();
}

/**
 * Create a temporary user.
 *
 * @param string $name Login suffix.
 * @param string $role Primary role.
 * @return int
 */
function life_user( $name, $role = 'subscriber' ) {
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
 * Add a school membership row.
 *
 * @param int    $school School.
 * @param int    $user   User.
 * @param string $role   Role.
 */
function life_member( $school, $user, $role ) {
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
}

/**
 * Token of an invitation URL.
 *
 * @param string $url Invitation URL.
 * @return string
 */
function life_token( $url ) {
	parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
	return (string) ( $query['ohmylms_invite'] ?? '' );
}

/**
 * Projection row of a legacy enrolment.
 *
 * @param int $legacy Legacy row id.
 * @return array|null
 */
function life_projection( $legacy ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE legacy_id=%d', EnrolmentSchema::table(), $legacy ), ARRAY_A );
}

/**
 * Legacy enrolment status.
 *
 * @param int $legacy Legacy row id.
 * @return string
 */
function life_legacy_status( $legacy ) {
	global $wpdb;
	return (string) $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM %i WHERE id=%d', $wpdb->prefix . 'ohmylms_user_enrollment', $legacy ) );
}

try {
	Schema::install();
	ok( Schema::VERSION === get_option( 'ohmylms_school_schema' ), 'school schema is current' );
	ok( (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', Schema::table( 'classes' ), 'source_class_id' ) ), 'classes.source_class_id exists' );
	if ( ! EnrolmentSchema::exists() ) {
		EnrolmentSchema::install();
	}
	ok( EnrolmentSchema::exists(), 'enrolments table exists' );
	add_filter( 'pre_wp_mail', '__return_true' );
	$admins = get_users(
		array(
			'role'    => 'administrator',
			'number'  => 1,
			'orderby' => 'ID',
			'fields'  => 'ID',
		)
	);
	ok( ! empty( $admins ), 'an administrator exists' );
	$admin      = (int) $admins[0];
	$subscriber = life_user( 'sub' );
	$manager    = life_user( 'manager', 'ohmylms_school_admin' );
	$teacher    = life_user( 'teacher', 'ohmylms_teacher' );
	$parent     = life_user( 'parent', 'ohmylms_parent' );

	// Retired routes are gone; View as stays.
	$routes = rest_get_server()->get_routes( 'ohmylms/v1' );
	foreach ( array( '/ohmylms/v1/school/schools', '/ohmylms/v1/school/users', '/ohmylms/v1/school/accept', '/ohmylms/v1/school/register', '/ohmylms/v1/school/sample-preview', '/ohmylms/v1/school/sample-preview/create' ) as $retired ) {
		ok( ! isset( $routes[ $retired ] ), "retired route removed: $retired" );
	}
	ok( isset( $routes['/ohmylms/v1/school/view-as/start'] ), 'View as route kept' );
	foreach ( array( '/archive', '/restore', '/removal', '/rollover/preview', '/rollover', '/guardians', '/guardians/(?P<link>[\d]+)', '/guardian-invitations/(?P<invitation>[\d]+)', '/report', '/report/export' ) as $suffix ) {
		$key = '/ohmylms/v1/account-hub/schools/(?P<id>[\d]+)' . $suffix;
		ok( isset( $routes[ $key ] ), "route registered: $suffix" );
		foreach ( $routes[ $key ] as $endpoint ) {
			ok( is_callable( $endpoint['permission_callback'] ) && '__return_true' !== $endpoint['permission_callback'], "real permission_callback on $suffix" );
		}
	}

	// Fixtures: two schools, two years, two classes, three managed students, one course.
	wp_set_current_user( $admin );
	$a            = (int) Service::create_school( array( 'name' => $tag . ' Alpha' ) )['id'];
	$b            = (int) Service::create_school( array( 'name' => $tag . ' Beta' ) )['id'];
	$school_ids[] = $a;
	$school_ids[] = $b;
	life_member( $a, $manager, 'school_admin' );
	life_member( $a, $teacher, 'teacher' );
	$y1      = (int) Service::create_year(
		$a,
		array(
			'label'     => '2025',
			'starts_at' => '2025-09-01',
			'ends_at'   => '2026-06-30',
		)
	)['id'];
	$y2      = (int) Service::create_year(
		$a,
		array(
			'label'     => '2026',
			'starts_at' => '2026-09-01',
			'ends_at'   => '2027-06-30',
		)
	)['id'];
	$c7      = (int) Service::create_class(
		$a,
		array(
			'name'             => '7A',
			'grade'            => '7',
			'subject'          => 'Maths',
			'academic_year_id' => $y1,
		)
	)['id'];
	$c12     = (int) Service::create_class(
		$a,
		array(
			'name'             => '=HYPERLINK("x") 12',
			'grade'            => '12',
			'academic_year_id' => $y1,
		)
	)['id'];
	$s1      = (int) Service::create_student( $a, array( 'name' => $tag . ' Ana', 'external_student_id' => $tag . '-1' ) )['id'];
	$s2      = (int) Service::create_student( $a, array( 'name' => $tag . ' Bat', 'external_student_id' => $tag . '-2' ) )['id'];
	$s3      = (int) Service::create_student( $a, array( 'name' => $tag . ' Cece', 'external_student_id' => $tag . '-3' ) )['id'];
	$user_ids = array_merge( $user_ids, array( $s1, $s2, $s3 ) );
	foreach ( array( array( $c7, $s1 ), array( $c7, $s2 ), array( $c12, $s3 ) ) as $pair ) {
		Service::class_member(
			$pair[0],
			array(
				'user_id' => $pair[1],
				'role'    => 'student',
			)
		);
	}
	Service::class_member(
		$c7,
		array(
			'user_id' => $teacher,
			'role'    => 'teacher',
		)
	);
	$course_id = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_COURSE_CPT,
			'post_status' => 'publish',
			'post_title'  => $tag . ' Course',
		)
	);
	ok( $course_id > 0, 'course created' );
	foreach ( array( $c7, $c12 ) as $class ) {
		$wpdb->insert(
			Schema::table( 'course_classes' ),
			array(
				'course_id'  => $course_id,
				'class_id'   => $class,
				'created_by' => $admin,
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}
	$enrol = static function ( $user, $origin ) use ( $course_id, $wpdb ) {
		Enrollments::create(
			array(
				'user_id'    => $user,
				'course_id'  => $course_id,
				'status'     => 'enrolled',
				'progress'   => 'running',
				'start_date' => current_time( 'mysql' ),
			),
			$origin
		);
		return (int) $wpdb->insert_id;
	};
	$roster = static function ( $class ) {
		return array(
			'source'      => 'roster',
			'source_type' => 'class',
			'source_ref'  => $class,
			'class_id'    => $class,
		);
	};
	$e1     = $enrol( $s1, $roster( $c7 ) );
	$e2     = $enrol( $s2, array( 'source' => 'manual' ) );
	$e3     = $enrol( $s3, $roster( $c12 ) );
	ok( $e1 && $e2 && $e3 && 'roster' === life_projection( $e1 )['source'] && (int) life_projection( $e1 )['class_id'] === $c7, 'roster enrolment fixtures projected' );

	// Anonymous, subscribers and teachers are refused on every new route.
	$denied = array(
		array( 'POST', "/account-hub/schools/$a/archive" ),
		array( 'GET', "/account-hub/schools/$a/removal" ),
		array( 'DELETE', "/account-hub/schools/$a", array( 'confirm' => 'x' ) ),
		array(
			'POST',
			"/account-hub/schools/$a/rollover/preview",
			array(
				'from_year_id' => $y1,
				'to_year_id'   => $y2,
			),
		),
		array( 'GET', "/account-hub/schools/$a/guardians" ),
		array(
			'POST',
			"/account-hub/schools/$a/guardians",
			array(
				'student_id' => $s1,
				'parent_id'  => $parent,
			),
		),
		array( 'GET', "/account-hub/schools/$a/report" ),
		array( 'GET', "/account-hub/schools/$a/report/export" ),
		array( 'GET', "/account-hub/classes/$c7/assignments" ),
		array( 'GET', '/account-hub/sample-school' ),
		array( 'POST', '/account-hub/sample-school' ),
	);
	foreach ( array(
		0           => 401,
		$subscriber => 403,
		$teacher    => 403,
	) as $user => $status ) {
		wp_set_current_user( $user );
		foreach ( $denied as $call ) {
			$response = life_api( $call[0], $call[1], $call[2] ?? array() );
			ok( $status === $response->get_status(), "{$call[0]} {$call[1]} as user $user answers $status (got " . $response->get_status() . ')' );
		}
	}

	// School administrators: no removal or sample school; their own school only.
	wp_set_current_user( $manager );
	ok_error( life_api( 'POST', "/account-hub/schools/$a/archive" ), 403, 'ohmylms_account_hub_cannot_remove', 'school admin cannot archive' );
	ok_error( life_api( 'GET', "/account-hub/schools/$a/removal" ), 403, 'ohmylms_account_hub_cannot_remove', 'school admin cannot see the removal impact' );
	ok_error( life_api( 'DELETE', "/account-hub/schools/$a", array( 'confirm' => $tag . ' Alpha' ) ), 403, 'ohmylms_account_hub_cannot_remove', 'school admin cannot delete' );
	ok_error( life_api( 'GET', '/account-hub/sample-school' ), 403, 'ohmylms_account_hub_cannot_sample', 'school admin cannot manage the sample school' );
	ok_error( life_api( 'GET', "/account-hub/schools/$b/guardians" ), 403, 'ohmylms_account_hub_forbidden', 'school admin outside scope: guardians' );
	ok_error( life_api( 'GET', "/account-hub/schools/$b/report" ), 403, 'ohmylms_account_hub_forbidden', 'school admin outside scope: report' );
	ok_error(
		life_api(
			'POST',
			"/account-hub/schools/$b/rollover/preview",
			array(
				'from_year_id' => $y1,
				'to_year_id'   => $y2,
			)
		),
		403,
		'ohmylms_account_hub_forbidden',
		'school admin outside scope: rollover'
	);
	ok_error( life_api( 'GET', '/account-hub/schools/999999999/report' ), 404, 'ohmylms_account_hub_school_not_found', 'unknown school' );

	// Guardians: invite an existing parent and an email, accept, list, filter, unlink, revoke.
	$invite = ok_status(
		life_api(
			'POST',
			"/account-hub/schools/$a/guardians",
			array(
				'student_id' => $s1,
				'parent_id'  => $parent,
			)
		),
		201,
		'school admin links an existing parent'
	);
	ok( 'invitation' === $invite['kind'] && preg_match( '/^https?:/', $invite['url'] ) && false === $invite['email_sent'], 'link answers a one-time invitation URL' );
	ok_error(
		life_api(
			'POST',
			"/account-hub/schools/$a/guardians",
			array(
				'student_id' => $s1,
				'parent_id'  => $parent,
			)
		),
		409,
		'ohmylms_account_hub_invitation_pending',
		'duplicate pending invitation'
	);
	$by_email = ok_status(
		life_api(
			'POST',
			"/account-hub/schools/$a/guardians",
			array(
				'student_id' => $s2,
				'email'      => $tag . '-newparent@example.invalid',
			)
		),
		201,
		'school admin invites a guardian by email'
	);
	ok_error(
		life_api(
			'POST',
			"/account-hub/schools/$a/guardians",
			array(
				'student_id' => $s2,
				'email'      => 'x@example.invalid',
				'parent_id'  => $parent,
			)
		),
		400,
		'ohmylms_account_hub_parent_required',
		'parent and email together refused'
	);
	ok_error(
		life_api(
			'POST',
			"/account-hub/schools/$a/guardians",
			array(
				'student_id' => $parent,
				'parent_id'  => $parent,
			)
		),
		422,
		'ohmylms_account_hub_not_on_roster',
		'student must be on the roster'
	);
	$pending = ok_status( life_api( 'GET', "/account-hub/schools/$a/guardians", array( 'status' => 'pending' ) ), 200, 'pending guardians' );
	ok( 2 === count( $pending ) && 'pending' === $pending[0]['status'] && ! array_key_exists( 'email', $pending[0] ), 'two pending invitations, no email field' );
	$hints = wp_list_pluck( $pending, 'email_hint' );
	ok( in_array( 'a***@example.invalid', $hints, true ) && in_array( $tag[0] . '***@example.invalid', $hints, true ), 'pending rows carry masked addresses only' );

	// Invitation acceptance through the typed route (the attempt counter of this client starts empty).
	$limit = 'ohmylms_school_invite_' . hash_hmac( 'sha256', isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : 'local', wp_salt( 'nonce' ) );
	delete_transient( $limit );
	$token = life_token( $invite['url'] );
	wp_set_current_user( 0 );
	ok_error( life_api( 'POST', '/account-hub/invitations/accept', array( 'token' => $token ), true ), 401, 'ohmylms_invitation_login', 'accept needs a signed-in account' );
	wp_set_current_user( $subscriber );
	ok_error( life_api( 'POST', '/account-hub/invitations/accept', array( 'token' => $token ) ), 403, 'ohmylms_invitation_nonce', 'accept needs the REST nonce' );
	ok_error( life_api( 'POST', '/account-hub/invitations/accept', array( 'token' => $token ), true ), 422, 'ohmylms_invitation_rejected', 'another account cannot accept' );
	wp_set_current_user( $admin );
	$setup = Service::invite(
		$a,
		array(
			'role'            => 'activation',
			'student_user_id' => $s3,
		)
	);
	wp_set_current_user( $subscriber );
	ok_error( life_api( 'POST', '/account-hub/invitations/accept', array( 'token' => life_token( $setup['url'] ) ), true ), 422, 'ohmylms_invitation_password_setup', 'password setup codes are refused here' );
	wp_set_current_user( $parent );
	$accepted = ok_status( life_api( 'POST', '/account-hub/invitations/accept', array( 'token' => $token ), true ), 200, 'parent accepts' );
	ok( true === $accepted['accepted'] && 'guardian' === $accepted['role'], 'acceptance answer' );
	ok_error( life_api( 'POST', '/account-hub/invitations/accept', array( 'token' => $token ), true ), 422, 'ohmylms_invitation_rejected', 'an invitation is single use' );
	set_transient( $limit, 15, 60 );
	ok_error( life_api( 'POST', '/account-hub/invitations/accept', array( 'token' => $token ), true ), 429, 'ohmylms_invitation_rate_limited', 'attempts are rate limited' );
	delete_transient( $limit );

	wp_set_current_user( $manager );
	$active = ok_status( life_api( 'GET', "/account-hub/schools/$a/guardians", array( 'status' => 'active' ) ), 200, 'active guardians' );
	ok( 1 === count( $active ) && 'link' === $active[0]['kind'] && $parent === $active[0]['parent']['id'] && $s1 === $active[0]['student']['id'] && null === $active[0]['email_hint'], 'the accepted link is listed' );
	ok( array( 'kind', 'id', 'status', 'student', 'parent', 'linked_at', 'expires_at', 'email_hint' ) === array_keys( $active[0] ), 'minimal guardian fields' );
	$in_7a = ok_status( life_api( 'GET', "/account-hub/schools/$a/guardians", array( 'class_id' => $c7 ) ), 200, 'class filter' );
	ok( 2 === count( $in_7a ), 'class 7A has the link and the email invitation' );
	ok( array() === ok_status( life_api( 'GET', "/account-hub/schools/$a/guardians", array( 'class_id' => $c12 ) ), 200, 'empty class filter' ), 'class 12 has no guardians' );
	$search = life_api( 'GET', "/account-hub/schools/$a/guardians", array( 'search' => 'Ana' ) );
	ok( 1 === count( $search->get_data() ) && '1' === (string) $search->get_headers()['X-WP-Total'], 'search by student name with totals' );
	wp_set_current_user( $admin );
	$foreign = (int) Service::create_class(
		$b,
		array(
			'name'             => 'B1',
			'academic_year_id' => (int) Service::create_year(
				$b,
				array(
					'label'     => 'B',
					'starts_at' => '2026-01-01',
					'ends_at'   => '2026-12-31',
				)
			)['id'],
		)
	)['id'];
	ok_error( life_api( 'GET', "/account-hub/schools/$a/guardians", array( 'class_id' => $foreign ) ), 404, 'ohmylms_account_hub_class_not_found', 'class of another school' );
	wp_set_current_user( $manager );
	ok_error(
		life_api(
			'POST',
			"/account-hub/schools/$a/guardians",
			array(
				'student_id' => $s1,
				'parent_id'  => $parent,
			)
		),
		409,
		'ohmylms_account_hub_already_linked',
		'already linked'
	);
	ok( true === ok_status( life_api( 'DELETE', "/account-hub/schools/$a/guardians/{$active[0]['id']}" ), 200, 'unlink' )['revoked'], 'unlinked' );
	ok_error( life_api( 'DELETE', "/account-hub/schools/$a/guardians/{$active[0]['id']}" ), 404, 'ohmylms_account_hub_link_not_found', 'unlink twice' );
	ok( true === ok_status( life_api( 'DELETE', "/account-hub/schools/$a/guardian-invitations/{$by_email['id']}" ), 200, 'revoke invitation' )['revoked'], 'invitation revoked' );
	ok( array() === life_api( 'GET', "/account-hub/schools/$a/guardians" )->get_data(), 'no guardians left' );

	// Class work: assign the course to 7A (one roster and one manual enrolment).
	$work = ok_status( life_api( 'POST', "/account-hub/classes/$c7/assignments", array( 'course_id' => $course_id ) ), 201, 'assign course' );
	ok( 2 === $work['recipients'], 'both 7A students received the work' );
	$listed = ok_status( life_api( 'GET', "/account-hub/classes/$c7/assignments" ), 200, 'class work list' );
	ok( 1 === count( $listed ) && 2 === $listed[0]['recipients'] && 0 === $listed[0]['completed'] && $tag . ' Course' === $listed[0]['course_title'], 'class work listed with completion' );
	$courses = ok_status( life_api( 'GET', "/account-hub/classes/$c7/courses", array( 'search' => $tag ) ), 200, 'course options' );
	ok( 1 === count( $courses ) && $course_id === $courses[0]['id'], 'course options search' );
	ok_error( life_api( 'POST', "/account-hub/classes/$c12/assignments", array( 'course_id' => $course_id + 999999 ) ), 422, 'ohmylms_account_hub_rejected', 'unknown course refused' );
	ok( array( 'items', 'has_more' ) === array_keys( ok_status( life_api( 'GET', "/account-hub/classes/$c7/submissions" ), 200, 'submissions' ) ), 'submissions shape' );

	// Report and CSV.
	$report = ok_status( life_api( 'GET', "/account-hub/schools/$a/report", array( 'period' => 30 ) ), 200, 'school report' );
	ok( 3 === $report['summary']['learners'] && 2 === $report['summary']['classes'] && 0 === $report['summary']['archived_classes'], 'report counts learners and classes' );
	ok( 3 === $report['summary']['enrolled'] && 0 === $report['summary']['completed'] && 0 === $report['summary']['completion'], 'report completion' );
	ok( $report['summary']['active_learners'] >= 3, 'enrolment starts count as activity' );
	ok( 2 === $report['summary']['assigned'] && 2 === count( $report['classes'] ) && 30 === $report['period']['days'], 'report work and class rows' );
	$row7 = array_values( array_filter( $report['classes'], static fn( $row ) => $row['id'] === $c7 ) )[0];
	ok( 2 === $row7['learners'] && 1 === $row7['teachers'] && 2 === $row7['assigned'], 'class row figures' );
	ok( isset( $report['mastery']['skills'] ) && array_key_exists( 'average', $report['mastery'] ), 'mastery overview present' );
	ok( false === strpos( wp_json_encode( $report ), '@example.invalid' ), 'report carries no email' );
	$export = ok_status( life_api( 'GET', "/account-hub/schools/$a/report/export" ), 200, 'report export' );
	ok( str_ends_with( $export['filename'], '.csv' ) && 0 === strpos( $export['content_type'], 'text/csv' ), 'export file name and type' );
	ok( false !== strpos( $export['csv'], '"\'=HYPERLINK(""x"") 12"' ), 'formula-looking class names are neutralised' );
	ok( false !== strpos( $export['csv'], '"7A","7","2"' ) && false !== strpos( $export['csv'], "\r\n" ), 'CSV rows' );

	// Rollover: 7 → 8, 12 leaves.
	$input   = array(
		'from_year_id' => $y1,
		'to_year_id'   => $y2,
		'grade_map'    => array(
			array(
				'from' => '7',
				'to'   => '8',
			),
			array(
				'from' => '12',
				'to'   => '',
			),
		),
	);
	ok_error( life_api( 'POST', "/account-hub/schools/$a/rollover/preview", array_merge( $input, array( 'to_year_id' => $y1 ) ) ), 422, 'ohmylms_account_hub_invalid_year', 'same year refused' );
	$plan = ok_status( life_api( 'POST', "/account-hub/schools/$a/rollover/preview", $input ), 200, 'rollover preview' );
	ok( 2 === $plan['totals']['classes'] && 1 === $plan['totals']['classes_created'] && 2 === $plan['totals']['students_promoted'] && 1 === $plan['totals']['students_leaving'], 'preview totals' );
	ok( 1 === $plan['totals']['enrolments_moved'] && 1 === $plan['totals']['enrolments_revoked'] && 1 === $plan['totals']['course_links'], 'preview enrolment totals' );
	$p7 = array_values( array_filter( $plan['classes'], static fn( $row ) => $row['class_id'] === $c7 ) )[0];
	ok( 'promote' === $p7['action'] && '8A' === $p7['target']['name'] && '8' === $p7['target']['grade'] && false === $p7['target']['exists'], 'successor named by grade' );
	ok( preg_match( '/^[a-f0-9]{64}$/', $plan['review'] ), 'review token' );
	ok( 'active' === Service::row( 'classes', $c7 )['status'] && 'active' === Service::row( 'academic_years', $y1 )['status'], 'preview wrote nothing' );
	ok_error( life_api( 'POST', "/account-hub/schools/$a/rollover", array_merge( $input, array( 'review' => str_repeat( 'a', 64 ) ) ) ), 409, 'ohmylms_account_hub_review_stale', 'wrong review refused' );
	$applied = ok_status( life_api( 'POST', "/account-hub/schools/$a/rollover", array_merge( $input, array( 'review' => $plan['review'] ) ) ), 200, 'school admin applies the rollover' );
	ok( false === $applied['already_applied'] && 'archived' === $applied['from']['status'], 'rollover applied' );
	$moved = array_values( array_filter( $applied['classes'], static fn( $row ) => $row['source_id'] === $c7 ) )[0];
	$c8    = (int) $moved['target_id'];
	$new   = Service::row( 'classes', $c8 );
	ok( $new && '8A' === $new['name'] && (int) $new['academic_year_id'] === $y2 && (int) $new['source_class_id'] === $c7 && 'active' === $new['status'], 'successor class created in the new year' );
	ok( 'archived' === Service::row( 'classes', $c7 )['status'] && 'archived' === Service::row( 'classes', $c12 )['status'] && 'archived' === Service::row( 'academic_years', $y1 )['status'], 'old classes and year archived' );
	$members = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM %i WHERE class_id=%d AND role='student' AND status='active' ORDER BY user_id", Schema::table( 'class_memberships' ), $c8 ) ) );
	ok( array( $s1, $s2 ) === $members, 'students promoted' );
	ok( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM %i WHERE class_id=%d AND role='teacher'", Schema::table( 'class_memberships' ), $c8 ) ), 'teachers are not copied unless asked' );
	$p1 = life_projection( $e1 );
	ok( 'roster' === $p1['source'] && (int) $p1['class_id'] === $c8 && (int) $p1['source_ref'] === $c8 && 'active' === $p1['status'], 'roster enrolment moved to the new class' );
	ok( 'cancelled' === life_legacy_status( $e3 ) && 'cancelled' === life_projection( $e3 )['status'] && 'roster' === life_projection( $e3 )['source'], 'leaving class roster enrolment cancelled, source kept' );
	ok( 'enrolled' === life_legacy_status( $e2 ) && 'manual' === life_projection( $e2 )['source'], 'manual enrolment untouched' );
	ok( (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM %i WHERE course_id=%d AND class_id=%d', Schema::table( 'course_classes' ), $course_id, $c8 ) ), 'course link copied' );
	ok( get_userdata( $s3 ) && \OhMyLMS\Schools\Access::student( $a, $s3 ), 'leaving student keeps account and school membership' );
	$again = ok_status( life_api( 'POST', "/account-hub/schools/$a/rollover", array_merge( $input, array( 'review' => $plan['review'] ) ) ), 200, 'repeat apply' );
	ok( true === $again['already_applied'] && (int) $again['classes'][0]['target_id'] === (int) $applied['classes'][0]['target_id'], 'repeat apply returns the first result' );
	ok( 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE source_class_id=%d', Schema::table( 'classes' ), $c7 ) ), 'no duplicate successor' );
	ok_error( life_api( 'POST', "/account-hub/schools/$a/rollover/preview", $input ), 409, 'ohmylms_account_hub_state_conflict', 'an archived year cannot roll over again' );

	// Removal: impact, archive, read only, restore, delete with confirmation; accounts stay.
	wp_set_current_user( $admin );
	$impact = ok_status( life_api( 'GET', "/account-hub/schools/$a/removal" ), 200, 'removal impact' );
	ok( false === $impact['can_delete'] && $tag . ' Alpha' === $impact['confirm'] && 3 === $impact['impact']['classes'] && 3 === $impact['impact']['students'] && 1 === $impact['impact']['roster_enrolments'] && 3 === $impact['impact']['managed_accounts'], 'impact counts' );
	ok_error( life_api( 'DELETE', "/account-hub/schools/$a", array( 'confirm' => $tag . ' Alpha' ) ), 409, 'ohmylms_account_hub_school_active', 'active school cannot be deleted' );
	$archived = ok_status( life_api( 'POST', "/account-hub/schools/$a/archive" ), 200, 'archive' );
	ok( 'archived' === $archived['status'], 'school archived' );
	ok_error( life_api( 'POST', "/account-hub/schools/$a/archive" ), 409, 'ohmylms_account_hub_school_archived', 'archive twice' );
	ok_error(
		life_api(
			'POST',
			"/account-hub/schools/$a/years",
			array(
				'label'     => 'x',
				'starts_at' => '2030-01-01',
				'ends_at'   => '2030-12-31',
			)
		),
		409,
		'ohmylms_account_hub_school_inactive',
		'archived school is read only'
	);
	ok( 'archived' === life_api( 'GET', '/account-hub/schools', array( 'status' => 'archived', 'search' => $tag ) )->get_data()[0]['status'], 'archived filter' );
	wp_set_current_user( $manager );
	ok_error( life_api( 'GET', "/account-hub/schools/$a" ), 403, 'ohmylms_account_hub_cannot_view', 'school admin loses an archived school' );
	wp_set_current_user( $admin );
	ok( 'active' === ok_status( life_api( 'POST', "/account-hub/schools/$a/restore" ), 200, 'restore' )['status'], 'school restored' );
	ok_error( life_api( 'POST', "/account-hub/schools/$a/restore" ), 409, 'ohmylms_account_hub_school_active', 'restore an active school' );
	ok( \OhMyLMS\Schools\Access::school( $a, array( 'school_admin' ), $manager ), 'school admin regains the restored school' );
	ok_status( life_api( 'POST', "/account-hub/schools/$a/archive" ), 200, 'archive again' );
	ok_error( life_api( 'DELETE', "/account-hub/schools/$a", array( 'confirm' => 'wrong' ) ), 422, 'ohmylms_account_hub_confirmation_mismatch', 'wrong confirmation' );
	ok( 'archived' === Service::row( 'schools', $a )['status'], 'nothing deleted on a wrong confirmation' );
	$deleted = ok_status( life_api( 'DELETE', "/account-hub/schools/$a", array( 'confirm' => $tag . ' Alpha' ) ), 200, 'delete' );
	ok( true === $deleted['deleted'] && 3 === $deleted['removed']['classes'] && 1 === $deleted['removed']['roster_enrolments'], 'delete answers what it removed' );
	ok( ! Service::row( 'schools', $a ), 'school row gone' );
	foreach ( array( 'classes', 'academic_years', 'school_memberships', 'school_student_profiles', 'guardian_links', 'school_invitations', 'learning_assignments' ) as $table ) {
		ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE school_id=%d', Schema::table( $table ), $a ) ), "no $table rows left" );
	}
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE class_id IN (%d,%d,%d)', Schema::table( 'class_memberships' ), $c7, $c12, $c8 ) ), 'class rosters gone' );
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE class_id IN (%d,%d,%d)', Schema::table( 'course_classes' ), $c7, $c12, $c8 ) ), 'course links gone' );
	foreach ( array( $s1, $s2, $s3, $teacher, $manager, $parent ) as $kept ) {
		ok( get_userdata( $kept ) instanceof WP_User, "account $kept kept" );
	}
	ok( '' === get_user_meta( $s1, '_ohmylms_managed_school', true ), 'managed marker cleared' );
	ok( 'cancelled' === life_legacy_status( $e1 ) && 'cancelled' === life_projection( $e1 )['status'] && 'roster' === life_projection( $e1 )['source'], 'roster enrolment revoked through Enrollments' );
	ok( 'enrolled' === life_legacy_status( $e2 ), 'manual enrolment kept' );
	ok( (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM %i WHERE action='school_deleted' AND object_id=%d", Schema::table( 'school_audit_log' ), $a ) ), 'deletion audited' );
	ok_error( life_api( 'GET', "/account-hub/schools/$a/removal" ), 404, 'ohmylms_account_hub_school_not_found', 'deleted school is gone' );

	// Sample school (only when the site has none).
	$before = ok_status( life_api( 'GET', '/account-hub/sample-school' ), 200, 'sample state' );
	if ( ! $before['exists'] ) {
		$sample_new = true;
		$created    = ok_status( life_api( 'POST', '/account-hub/sample-school' ), 201, 'create sample' );
		ok( $created['complete'] && 2 === count( $created['classes'] ) && 9 === count( $created['people'] ) && $created['removal']['allowed'], 'sample school with two classes and nine people' );
		$repeat = ok_status( life_api( 'POST', '/account-hub/sample-school' ), 200, 'create sample again' );
		ok( wp_list_pluck( $repeat['people'], 'id' ) === wp_list_pluck( $created['people'], 'id' ), 'second create reuses everything' );
		$listed = life_api( 'GET', '/account-hub/schools', array( 'search' => 'Sample Preview School' ) )->get_data();
		ok( 1 === count( $listed ) && true === $listed[0]['sample'], 'sample school is marked in lists' );
		$sid = (int) $created['school_id'];
		ok( 6 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE school_id=%d AND role='student' AND status='active'", Schema::table( 'school_memberships' ), $sid ) ), 'six sample learners' );
		foreach ( $created['people'] as $person ) {
			$account = get_userdata( $person['id'] );
			ok( str_ends_with( $account->user_email, '@example.invalid' ) && SamplePreview::is_sample_user( get_user_meta( $account->ID, SamplePreview::META, true ), $account->user_email ), 'sample accounts are marked and undeliverable' );
		}
		life_member( $sid, $subscriber, 'student' );
		$blocked = life_api( 'DELETE', '/account-hub/sample-school' );
		ok_error( $blocked, 409, 'ohmylms_account_hub_sample_in_use', 'a real member blocks removal' );
		ok( ! empty( $blocked->get_data()['data']['blockers'] ) && Service::row( 'schools', $sid ), 'blockers listed, nothing removed' );
		$wpdb->delete(
			Schema::table( 'school_memberships' ),
			array(
				'school_id' => $sid,
				'user_id'   => $subscriber,
			)
		);
		$removed = ok_status( life_api( 'DELETE', '/account-hub/sample-school' ), 200, 'remove sample in one step' );
		ok( true === $removed['removed'] && ! Service::row( 'schools', $sid ), 'sample school removed' );
		foreach ( $created['people'] as $person ) {
			ok( ! get_userdata( $person['id'] ), 'sample account removed' );
		}
		ok( false === life_api( 'GET', '/account-hub/sample-school' )->get_data()['exists'], 'nothing left' );
		ok_error( life_api( 'DELETE', '/account-hub/sample-school' ), 404, 'ohmylms_account_hub_sample_not_found', 'remove twice' );
		$sample_new = false;
	}

	fwrite( STDOUT, "OK account-hub-schools-lifecycle-rest-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	wp_set_current_user( $admin ?? 0 );
	if ( $sample_new && SamplePreview::status()['exists'] ) {
		$wpdb->delete( Schema::table( 'school_memberships' ), array( 'user_id' => $subscriber ?? 0 ) );
		SamplePreview::remove( true );
	}
	$school_ids = array_map( 'intval', array_merge( $school_ids, (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE name LIKE %s', Schema::table( 'schools' ), $wpdb->esc_like( $tag ) . '%' ) ) ) );
	if ( $school_ids ) {
		$in = implode( ',', array_unique( $school_ids ) );
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM %i WHERE school_id IN ($in)", Schema::table( 'school_student_profiles' ) ) ) as $managed ) {
			$user_ids[] = (int) $managed;
		}
		$classes = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM %i WHERE school_id IN ($in)", Schema::table( 'classes' ) ) ) );
		if ( $classes ) {
			$cin = implode( ',', $classes );
			foreach ( array( 'class_memberships', 'course_classes', 'course_class_students' ) as $table ) {
				$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE class_id IN ($cin)", Schema::table( $table ) ) );
			}
		}
		$wpdb->query( $wpdb->prepare( "DELETE r FROM %i r JOIN %i a ON a.id=r.assignment_id WHERE a.school_id IN ($in)", Schema::table( 'assignment_recipients' ), Schema::table( 'learning_assignments' ) ) );
		foreach ( array( 'learning_assignments', 'classes', 'academic_years', 'school_memberships', 'guardian_links', 'school_invitations', 'school_student_profiles', 'school_audit_log' ) as $table ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE school_id IN ($in)", Schema::table( $table ) ) );
		}
		$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE id IN ($in)", Schema::table( 'schools' ) ) );
		foreach ( array_unique( $school_ids ) as $school ) {
			delete_option( 'ohmylms_rollover_' . $school );
			$wpdb->delete(
				Schema::table( 'school_audit_log' ),
				array(
					'action'    => 'school_deleted',
					'object_id' => $school,
				)
			);
		}
	}
	if ( $course_id ) {
		$wpdb->query( $wpdb->prepare( 'DELETE r FROM %i r JOIN %i a ON a.id=r.assignment_id WHERE a.course_id=%d', Schema::table( 'assignment_recipients' ), Schema::table( 'learning_assignments' ), $course_id ) );
		$wpdb->delete( Schema::table( 'learning_assignments' ), array( 'course_id' => $course_id ) );
		$wpdb->delete( Schema::table( 'course_classes' ), array( 'course_id' => $course_id ) );
		$wpdb->delete( Schema::table( 'course_class_students' ), array( 'course_id' => $course_id ) );
		Enrollments::delete( array( 'course_id' => $course_id ) );
		wp_delete_post( $course_id, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( array_unique( array_map( 'intval', $user_ids ) ) as $user_id ) {
		$wpdb->delete(
			Schema::table( 'school_audit_log' ),
			array(
				'school_id' => 0,
				'object_id' => $user_id,
			)
		);
		wp_delete_user( $user_id );
	}
}

exit( isset( $exit_code ) ? $exit_code : 0 );
