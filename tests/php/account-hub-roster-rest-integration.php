<?php
/**
 * Account Hub roster intake REST integration test (invitations, managed students, CSV import).
 *
 * Runs against a WordPress install (the Local site by default). It creates tagged schools, users,
 * invitations and managed students only, and removes all of them in `finally`.
 *
 * Usage (set OHMYLMS_ACCOUNT_HUB_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/account-hub-roster-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, Generic.Formatting.MultipleStatementAlignment, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_ACCOUNT_HUB_TEST' ) ) {
	exit( "Set OHMYLMS_ACCOUNT_HUB_TEST=1 to run the Account Hub roster tests against the local site.\n" );
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
$tag        = 'ahroster' . strtolower( wp_generate_password( 6, false, false ) );

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
function roster_api( $method, $path, array $data = array() ) {
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
	ok( $status === $response->get_status() && $code === $got, "$label: expected $status $code, got " . $response->get_status() . ' ' . $got . ' ' . wp_json_encode( $data ) );
}

/**
 * Create a temporary user.
 *
 * @param string $name Login suffix.
 * @param string $role Primary role.
 * @return int
 */
function roster_user( $name, $role = 'subscriber' ) {
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
 * Count the school's rows in a table.
 *
 * @param string $table  Table suffix.
 * @param int    $school School id.
 * @return int
 */
function roster_count( $table, $school ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE school_id=%d', Schema::table( $table ), $school ) );
}

try {
	Schema::install();
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
	$subscriber = roster_user( 'sub' );
	$manager    = roster_user( 'manager' );
	$teacher    = roster_user( 'teacher', 'ohmylms_teacher' );
	$existing   = roster_user( 'member' );

	// Routes and permission callbacks.
	$routes = rest_get_server()->get_routes( 'ohmylms/v1' );
	foreach ( array( '/invitations', '/invitations/(?P<invitation>[\d]+)', '/managed-students', '/managed-students/(?P<user_id>[\d]+)/setup-link', '/roster-import' ) as $route ) {
		$key = '/ohmylms/v1/account-hub/schools/(?P<id>[\d]+)' . $route;
		ok( isset( $routes[ $key ] ), 'route registered: ' . $route );
		foreach ( $routes[ $key ] as $endpoint ) {
			ok( is_callable( $endpoint['permission_callback'] ) && '__return_true' !== $endpoint['permission_callback'], "real permission_callback on $route" );
		}
	}

	wp_set_current_user( $admin );
	$alpha = Service::create_school( array( 'name' => $tag . ' Alpha' ) );
	$beta  = Service::create_school( array( 'name' => $tag . ' Beta' ) );
	$school_ids[] = $alpha['id'];
	$school_ids[] = $beta['id'];
	$a = (int) $alpha['id'];
	$b = (int) $beta['id'];
	foreach ( array( array( $manager, 'school_admin' ), array( $existing, 'teacher' ) ) as $pair ) {
		$wpdb->insert(
			Schema::table( 'school_memberships' ),
			array(
				'school_id' => $a,
				'user_id'   => $pair[0],
				'role'      => $pair[1],
				'status'    => 'active',
				'joined_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}

	// Anonymous, subscribers and teachers are refused on every route.
	$denied = array(
		array( 'GET', "/schools/$a/invitations" ),
		array(
			'POST',
			"/schools/$a/invitations",
			array(
				'role'  => 'teacher',
				'email' => 'x@example.invalid',
			),
		),
		array( 'DELETE', "/schools/$a/invitations/1" ),
		array( 'GET', "/schools/$a/managed-students" ),
		array(
			'POST',
			"/schools/$a/managed-students",
			array(
				'name'                => 'x',
				'external_student_id' => 'x',
			),
		),
		array( 'POST', "/schools/$a/managed-students/1/setup-link" ),
		array( 'POST', "/schools/$a/roster-import", array( 'csv' => "name\nx" ) ),
	);
	foreach ( array(
		0           => 401,
		$subscriber => 403,
		$teacher    => 403,
	) as $user => $status ) {
		wp_set_current_user( $user );
		foreach ( $denied as $call ) {
			$response = roster_api( $call[0], $call[1], $call[2] ?? array() );
			ok( $status === $response->get_status(), "{$call[0]} {$call[1]} as user $user answers $status (got " . $response->get_status() . ')' );
		}
	}
	ok( 0 === roster_count( 'school_invitations', $a ) && 0 === roster_count( 'school_student_profiles', $a ), 'denied requests wrote nothing' );

	wp_set_current_user( $admin );

	// Invitations.
	ok_error(
		roster_api(
			'POST',
			"/schools/$a/invitations",
			array(
				'role'  => 'guardian',
				'email' => 'x@example.invalid',
			)
		),
		400,
		'rest_invalid_param',
		'guardian role rejected by schema'
	);
	ok_error(
		roster_api(
			'POST',
			"/schools/$a/invitations",
			array(
				'role'  => 'teacher',
				'email' => 'not-an-email',
			)
		),
		400,
		'rest_invalid_param',
		'invalid email rejected by schema'
	);
	ok_error(
		roster_api(
			'POST',
			"/schools/$a/invitations",
			array(
				'role'  => 'teacher',
				'email' => $tag . '_member@example.invalid',
			)
		),
		409,
		'ohmylms_account_hub_already_member',
		'existing teacher is not invited again'
	);
	ok_error(
		roster_api(
			'POST',
			"/schools/$a/invitations",
			array(
				'role'     => 'teacher',
				'email'    => 'x@example.invalid',
				'class_id' => 999999999,
			)
		),
		422,
		'ohmylms_account_hub_invalid_class',
		'unknown class rejected'
	);
	$invite = roster_api(
		'POST',
		"/schools/$a/invitations",
		array(
			'role'  => 'teacher',
			'email' => $tag . '.new@example.invalid',
		)
	);
	ok( 201 === $invite->get_status() && preg_match( '/^https?:/', $invite->get_data()['url'] ) && false === $invite->get_data()['email_sent'], 'invitation created with a one-time link and no mail' );
	ok( 'private, no-store' === ( $invite->get_headers()['Cache-Control'] ?? '' ), 'invitation link is not cacheable' );
	ok_error(
		roster_api(
			'POST',
			"/schools/$a/invitations",
			array(
				'role'  => 'teacher',
				'email' => $tag . '.new@example.invalid',
			)
		),
		409,
		'ohmylms_account_hub_invitation_pending',
		'duplicate pending invitation refused'
	);
	$list = roster_api( 'GET', "/schools/$a/invitations" );
	$row  = $list->get_data()[0];
	ok( 200 === $list->get_status() && '1' === $list->get_headers()['X-WP-Total'] && 'pending' === $row['status'] && ! isset( $row['url'] ) && ! isset( $row['token_hash'] ), 'invitations listed without links or tokens' );
	ok( $tag . '.new@example.invalid' === $row['email'], 'administrators see the email' );
	ok( 0 === strpos( $row['email_hint'], substr( $tag, 0, 1 ) . '***@' ), 'email hint is masked' );

	// School administrators: own school only, no email without list_users.
	wp_set_current_user( $manager );
	$mine = roster_api( 'GET', "/schools/$a/invitations" );
	ok( 200 === $mine->get_status() && null === $mine->get_data()[0]['email'], 'school administrator sees the masked hint only' );
	ok_error( roster_api( 'GET', "/schools/$b/invitations" ), 403, 'ohmylms_account_hub_forbidden', 'other school invitations' );
	ok_error(
		roster_api(
			'POST',
			"/schools/$b/managed-students",
			array(
				'name'                => 'x',
				'external_student_id' => 'x',
			)
		),
		403,
		'ohmylms_account_hub_forbidden',
		'other school managed student'
	);
	ok_error( roster_api( 'POST', "/schools/$b/roster-import", array( 'csv' => "name,external_student_id\nx,1" ) ), 403, 'ohmylms_account_hub_forbidden', 'other school import' );
	ok_error( roster_api( 'DELETE', "/schools/$b/invitations/" . $invite->get_data()['id'] ), 403, 'ohmylms_account_hub_forbidden', 'revoke through another school' );
	ok( 200 === roster_api( 'DELETE', "/schools/$a/invitations/" . $invite->get_data()['id'] )->get_status(), 'school administrator revokes their invitation' );
	ok_error( roster_api( 'DELETE', "/schools/$a/invitations/" . $invite->get_data()['id'] ), 404, 'ohmylms_account_hub_invitation_not_found', 'revoke twice' );
	ok( 'revoked' === roster_api( 'GET', "/schools/$a/invitations", array( 'status' => 'all' ) )->get_data()[0]['status'], 'revoked invitation stays listed' );
	ok( '0' === roster_api( 'GET', "/schools/$a/invitations" )->get_headers()['X-WP-Total'], 'pending filter hides revoked invitations' );

	// Managed students.
	$created = roster_api(
		'POST',
		"/schools/$a/managed-students",
		array(
			'name'                => $tag . ' Kid',
			'external_student_id' => $tag . '-001',
		)
	);
	ok( 201 === $created->get_status() && $created->get_data()['username'] && $tag . '-001' === $created->get_data()['external_student_id'], 'managed student created' );
	$kid = (int) $created->get_data()['id'];
	$user_ids[] = $kid;
	ok( '' === get_userdata( $kid )->user_email, 'managed student has no email' );
	ok_error(
		roster_api(
			'POST',
			"/schools/$a/managed-students",
			array(
				'name'                => 'Other',
				'external_student_id' => $tag . '-001',
			)
		),
		409,
		'ohmylms_account_hub_duplicate_student_id',
		'duplicate school student ID'
	);
	ok_error(
		roster_api(
			'POST',
			"/schools/$a/managed-students",
			array(
				'name'                => str_repeat( 'x', 191 ),
				'external_student_id' => 'x',
			)
		),
		400,
		'rest_invalid_param',
		'name length limit'
	);
	$students = roster_api( 'GET', "/schools/$a/managed-students", array( 'search' => $tag . '-001' ) );
	ok( '1' === $students->get_headers()['X-WP-Total'] && null === $students->get_data()[0]['setup_expires_at'] && ! isset( $students->get_data()[0]['email'] ), 'managed students searchable by school ID, without email' );
	$link = roster_api( 'POST', "/schools/$a/managed-students/$kid/setup-link" );
	ok( 201 === $link->get_status() && $link->get_data()['url'], 'setup link created' );
	$again = roster_api( 'POST', "/schools/$a/managed-students/$kid/setup-link" );
	ok( 201 === $again->get_status() && null !== Service::row( 'school_invitations', $link->get_data()['id'] )['revoked_at'], 'a new setup link revokes the previous one' );
	ok( null !== roster_api( 'GET', "/schools/$a/managed-students" )->get_data()[0]['setup_expires_at'], 'open setup link shown' );
	ok_error( roster_api( 'POST', "/schools/$a/managed-students/$manager/setup-link" ), 404, 'ohmylms_account_hub_student_not_found', 'staff are not managed students' );
	ok_error( roster_api( 'POST', "/schools/$b/managed-students/$kid/setup-link" ), 403, 'ohmylms_account_hub_forbidden', 'setup link through another school' );

	// CSV import: review, apply, idempotent re-apply.
	wp_set_current_user( $admin );
	$csv  = "\xEF\xBB\xBFRole,Name,Email,Student_ID,Notes\n";
	$csv .= "student,$tag Ana,,{$tag}-002,ignored\n";
	$csv .= "student,$tag Bat,,{$tag}-001,\n";
	$csv .= "teacher,$tag Tuya,{$tag}.tuya@example.invalid,,\n";
	$csv .= "teacher,$tag Member,{$tag}_member@example.invalid,,\n";
	$csv .= "student,$tag Dup,,{$tag}-002,\n";
	$csv .= "teacher,$tag NoMail,,,\n";
	$csv .= "parent,$tag Role,,x,\n";
	$csv .= "student,$tag Mail,{$tag}.mail@example.invalid,,\n";
	$before = roster_count( 'school_student_profiles', $a );
	$review = roster_api( 'POST', "/schools/$a/roster-import", array( 'csv' => $csv ) );
	$report = $review->get_data();
	ok( 200 === $review->get_status() && true === $report['dry_run'] && preg_match( '/^[a-f0-9]{64}$/', $report['review'] ), 'review answers a token: ' . wp_json_encode( $report ) );
	ok( roster_count( 'school_student_profiles', $a ) === $before, 'review writes nothing' );
	ok( array( 'notes' ) === $report['ignored'], 'unknown columns are reported' );
	ok( array( 'create_student', 'existing', 'invite', 'existing', 'error', 'error', 'error', 'invite' ) === array_column( $report['rows'], 'action' ), 'planned actions: ' . wp_json_encode( array_column( $report['rows'], 'action' ) ) );
	ok( 1 === $report['counts']['create_student'] && 2 === $report['counts']['invite'] && 3 === $report['counts']['error'], 'planned counts' );
	ok_error(
		roster_api(
			'POST',
			"/schools/$a/roster-import",
			array(
				'csv'     => $csv,
				'dry_run' => false,
			)
		),
		400,
		'ohmylms_account_hub_review_required',
		'apply without review'
	);
	ok_error(
		roster_api(
			'POST',
			"/schools/$a/roster-import",
			array(
				'csv'     => $csv . "student,$tag Late,,{$tag}-009,\n",
				'dry_run' => false,
				'review'  => $report['review'],
			)
		),
		409,
		'ohmylms_account_hub_review_stale',
		'changed file needs a new review'
	);
	wp_set_current_user( $manager );
	ok_error(
		roster_api(
			'POST',
			"/schools/$a/roster-import",
			array(
				'csv'     => $csv,
				'dry_run' => false,
				'review'  => $report['review'],
			)
		),
		409,
		'ohmylms_account_hub_review_stale',
		'review token is bound to the reviewer'
	);
	wp_set_current_user( $admin );
	$applied = roster_api(
		'POST',
		"/schools/$a/roster-import",
		array(
			'csv'     => $csv,
			'dry_run' => false,
			'review'  => $report['review'],
		)
	);
	$result  = $applied->get_data();
	ok( 200 === $applied->get_status() && 1 === $result['counts']['created'] && 2 === $result['counts']['invited'], 'apply creates the reviewed rows: ' . wp_json_encode( $result['counts'] ) );
	ok( 'private, no-store' === ( $applied->get_headers()['Cache-Control'] ?? '' ), 'apply result is not cacheable' );
	foreach ( $result['rows'] as $row ) {
		if ( $row['user_id'] ) {
			$user_ids[] = (int) $row['user_id'];
		}
	}
	ok( roster_count( 'school_student_profiles', $a ) === $before + 1, 'one managed student added' );
	ok( preg_match( '/^https?:/', (string) $result['rows'][2]['url'] ) && null === $result['rows'][0]['url'], 'invitation links returned once for invited rows' );
	ok_error(
		roster_api(
			'POST',
			"/schools/$a/roster-import",
			array(
				'csv'     => $csv,
				'dry_run' => false,
				'review'  => $report['review'],
			)
		),
		409,
		'ohmylms_account_hub_review_stale',
		'the same review cannot be applied twice'
	);
	$second = roster_api( 'POST', "/schools/$a/roster-import", array( 'csv' => $csv ) )->get_data();
	ok( 0 === $second['counts']['create_student'] && 0 === $second['counts']['invite'] && 2 === $second['counts']['pending'], 're-review plans nothing new' );
	$invites = roster_count( 'school_invitations', $a );
	$redo    = roster_api(
		'POST',
		"/schools/$a/roster-import",
		array(
			'csv'     => $csv,
			'dry_run' => false,
			'review'  => $second['review'],
		)
	)->get_data();
	ok( 0 === $redo['counts']['created'] && 0 === $redo['counts']['invited'] && roster_count( 'school_invitations', $a ) === $invites && roster_count( 'school_student_profiles', $a ) === $before + 1, 'applying the same file again is idempotent' );

	// File limits.
	ok_error( roster_api( 'POST', "/schools/$a/roster-import", array( 'csv' => str_repeat( 'x', 100001 ) ) ), 400, 'rest_invalid_param', 'CSV size limit' );
	ok_error( roster_api( 'POST', "/schools/$a/roster-import", array( 'csv' => "email\nx@example.invalid" ) ), 422, 'ohmylms_account_hub_csv_invalid', 'name column required' );
	ok_error( roster_api( 'POST', "/schools/$a/roster-import", array( 'csv' => "name,name\nx,y" ) ), 422, 'ohmylms_account_hub_csv_invalid', 'duplicate columns refused' );
	ok_error( roster_api( 'POST', "/schools/$a/roster-import", array( 'csv' => "name,external_student_id\n" ) ), 422, 'ohmylms_account_hub_csv_empty', 'empty file' );
	ok_error( roster_api( 'POST', "/schools/$a/roster-import", array( 'csv' => "name,external_student_id\n" . str_repeat( "x,1\n", 201 ) ) ), 422, 'ohmylms_account_hub_csv_too_long', 'row limit' );
	// JSON cannot carry invalid UTF-8; a form-encoded body can.
	$form = new WP_REST_Request( 'POST', "/ohmylms/v1/account-hub/schools/$a/roster-import" );
	$form->set_body_params( array( 'csv' => "name,external_student_id\n\xC3\x28,1" ) );
	ok_error( rest_do_request( $form ), 422, 'ohmylms_account_hub_csv_invalid', 'invalid UTF-8 refused' );

	fwrite( STDOUT, "OK account-hub-roster-rest-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	$school_ids = array_map( 'intval', array_merge( $school_ids, (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE name LIKE %s', Schema::table( 'schools' ), $wpdb->esc_like( $tag ) . '%' ) ) ) );
	if ( $school_ids ) {
		$in = implode( ',', array_unique( $school_ids ) );
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM %i WHERE school_id IN ($in)", Schema::table( 'school_student_profiles' ) ) ) as $managed ) {
			$user_ids[] = (int) $managed;
		}
		foreach ( array( 'classes', 'academic_years', 'school_memberships', 'guardian_links', 'school_invitations', 'school_student_profiles', 'school_audit_log' ) as $table ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE school_id IN ($in)", Schema::table( $table ) ) );
		}
		$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE id IN ($in)", Schema::table( 'schools' ) ) );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( array_unique( array_map( 'intval', $user_ids ) ) as $user_id ) {
		wp_delete_user( $user_id );
	}
}

exit( isset( $exit_code ) ? $exit_code : 0 );
