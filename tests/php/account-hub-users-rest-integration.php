<?php
/**
 * Account Hub "All accounts" REST integration test (list, filters, create, edit, privilege limits).
 *
 * Runs against a WordPress install (the Local site by default). It creates tagged users and two
 * tagged roles only, intercepts all mail, and removes everything it created in `finally`.
 *
 * Usage (set OHMYLMS_ACCOUNT_HUB_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/account-hub-users-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, Generic.Formatting.MultipleStatementAlignment, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_ACCOUNT_HUB_TEST' ) ) {
	exit( "Set OHMYLMS_ACCOUNT_HUB_TEST=1 to run the Account Hub users tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Schools\Schema;

global $wpdb;

$checks   = 0;
$user_ids = array();
$roles    = array();
$mails    = array();
$tag      = 'ahusers' . strtolower( wp_generate_password( 6, false, false ) );

add_filter(
	'pre_wp_mail',
	static function ( $short, $atts ) {
		global $mails;
		$mails[] = $atts['to'];
		return true;
	},
	10,
	2
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
 * Dispatch a REST request below /ohmylms/v1/account-hub/users.
 *
 * @param string $method HTTP method.
 * @param string $path   Path after /users.
 * @param array  $data   Body (JSON) or query (GET).
 * @return WP_REST_Response
 */
function users_api( $method, $path = '', array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/account-hub/users' . $path );
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
	ok( $status === $response->get_status() && $code === $got, "$label: expected $status $code, got " . $response->get_status() . ' ' . $got . ' ' . wp_json_encode( $data ) );
}

/**
 * Create a temporary user.
 *
 * @param string $name Login suffix.
 * @param string $role Primary role.
 * @return int
 */
function users_fixture( $name, $role = 'subscriber' ) {
	global $tag, $user_ids;
	$id = wp_insert_user(
		array(
			'user_login'   => $tag . '_' . $name,
			'user_pass'    => wp_generate_password( 24 ),
			'user_email'   => $tag . '_' . $name . '@example.invalid',
			'first_name'   => $tag,
			'last_name'    => $name,
			'display_name' => $tag . ' ' . $name,
			'role'         => $role,
		)
	);
	ok( ! is_wp_error( $id ), "user $name created" );
	$user_ids[] = $id;
	return $id;
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

	// A user manager without manage_options, and a role with a capability the manager lacks.
	$manager_role = $tag . '_manager';
	$rich_role    = $tag . '_rich';
	add_role(
		$manager_role,
		'Manager ' . $tag,
		array(
			'read'         => true,
			'list_users'   => true,
			'edit_users'   => true,
			'create_users' => true,
		)
	);
	add_role(
		$rich_role,
		'Rich ' . $tag,
		array(
			'read'       => true,
			'edit_posts' => true,
		)
	);
	$roles = array( $manager_role, $rich_role );

	$subscriber = users_fixture( 'sub' );
	$manager    = users_fixture( 'manager', $manager_role );
	$teacher    = users_fixture( 'teacher', 'ohmylms_teacher' );
	$rich       = users_fixture( 'rich', $rich_role );
	$admin_two  = users_fixture( 'admin', 'administrator' );
	$old        = users_fixture( 'old' );
	$blocked    = users_fixture( 'blocked' );
	$wpdb->update( $wpdb->users, array( 'user_registered' => '2001-02-03 04:05:06' ), array( 'ID' => $old ) );
	clean_user_cache( $old );
	update_user_meta( $blocked, '_ohmylms_banned_student', 'yes' );
	update_user_meta( $teacher, '_ohmylms_last_login', '2026-10-01 10:00:00' );

	// Routes and permission callbacks.
	$routes = rest_get_server()->get_routes( 'ohmylms/v1' );
	foreach ( array( '', '/options', '/(?P<id>[\d]+)' ) as $route ) {
		$key = '/ohmylms/v1/account-hub/users' . $route;
		ok( isset( $routes[ $key ] ), 'route registered: ' . $route );
		foreach ( $routes[ $key ] as $endpoint ) {
			ok( is_callable( $endpoint['permission_callback'] ) && '__return_true' !== $endpoint['permission_callback'], "real permission_callback on users$route" );
		}
	}

	// Anonymous 401, subscribers 403, everywhere.
	$denied = array(
		array( 'GET', '', array() ),
		array( 'GET', '/options', array() ),
		array(
			'POST',
			'',
			array(
				'type'       => 'student',
				'first_name' => 'X',
				'email'      => $tag . '_denied@example.invalid',
			),
		),
		array( 'GET', "/$teacher", array() ),
		array( 'PATCH', "/$teacher", array( 'first_name' => 'X' ) ),
	);
	foreach ( array( 0 => 401, $subscriber => 403 ) as $who => $status ) {
		wp_set_current_user( $who );
		foreach ( $denied as $call ) {
			$response = users_api( $call[0], $call[1], $call[2] );
			ok( $status === $response->get_status(), "{$call[0]} users{$call[1]} answers $status for user $who, got " . $response->get_status() );
		}
	}
	ok( ! email_exists( $tag . '_denied@example.invalid' ), 'denied create wrote nothing' );
	ok( $tag === get_user_meta( $teacher, 'first_name', true ), 'denied edit wrote nothing' );

	// Listing as an administrator.
	wp_set_current_user( $admin );
	$list = users_api(
		'GET',
		'',
		array(
			'search'   => $tag,
			'per_page' => 3,
		)
	);
	ok( 200 === $list->get_status(), 'list answers 200' );
	$headers = $list->get_headers();
	ok( '7' === $headers['X-WP-Total'] && '3' === $headers['X-WP-TotalPages'], 'totals: ' . wp_json_encode( $headers ) );
	ok( 'private, no-store' === $headers['Cache-Control'], 'list is not cached' );
	ok( 3 === count( $list->get_data() ), 'one page of three' );
	$keys = array_keys( $list->get_data()[0] );
	sort( $keys );
	ok( array( 'blocked', 'can_edit', 'display_name', 'email', 'id', 'last_login', 'registered_at', 'roles', 'username' ) === $keys, 'minimal fields: ' . implode( ',', $keys ) );
	ok_error( users_api( 'GET', '', array( 'search' => $tag, 'per_page' => 3, 'page' => 4 ) ), 400, 'ohmylms_account_hub_invalid_page', 'page past the end' );
	ok_error( users_api( 'GET', '', array( 'per_page' => 101 ) ), 400, 'rest_invalid_param', 'per_page capped' );
	ok_error( users_api( 'GET', '', array( 'role' => 'nope_' . $tag ) ), 400, 'rest_invalid_param', 'unknown role filter' );
	ok_error( users_api( 'GET', '', array( 'registered' => 'yesterday' ) ), 400, 'rest_invalid_param', 'unknown period' );

	$all = array();
	foreach ( users_api( 'GET', '', array( 'search' => $tag, 'per_page' => 100 ) )->get_data() as $row ) {
		$all[ $row['id'] ] = $row;
	}
	ok( 7 === count( $all ), 'seven tagged accounts' );
	ok( 'ohmylms_teacher' === $all[ $teacher ]['roles'][0]['slug'] && '' !== $all[ $teacher ]['roles'][0]['label'], 'roles carry slug and label' );
	ok( preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $all[ $teacher ]['last_login'] ) && null === $all[ $subscriber ]['last_login'], 'last login is UTC ISO or null' );
	ok( '2001-02-03T04:05:06Z' === $all[ $old ]['registered_at'], 'registration time is UTC ISO' );
	ok( true === $all[ $blocked ]['blocked'] && false === $all[ $teacher ]['blocked'], 'blocked flag' );
	ok( true === $all[ $admin_two ]['can_edit'] && true === $all[ $rich ]['can_edit'], 'an administrator can edit other administrators and richer roles' );

	$teachers = users_api(
		'GET',
		'',
		array(
			'search' => $tag,
			'role'   => 'ohmylms_teacher',
		)
	)->get_data();
	ok( 1 === count( $teachers ) && $teacher === $teachers[0]['id'], 'role filter' );
	$recent = wp_list_pluck(
		users_api(
			'GET',
			'',
			array(
				'search'     => $tag,
				'registered' => 'last_30_days',
			)
		)->get_data(),
		'id'
	);
	ok( 6 === count( $recent ) && ! in_array( $old, $recent, true ), 'registration period filter' );
	$only_blocked = wp_list_pluck( users_api( 'GET', '', array( 'search' => $tag, 'status' => 'blocked' ) )->get_data(), 'id' );
	ok( array( $blocked ) === $only_blocked, 'blocked filter' );
	$active = wp_list_pluck( users_api( 'GET', '', array( 'search' => $tag, 'status' => 'active' ) )->get_data(), 'id' );
	ok( 6 === count( $active ) && ! in_array( $blocked, $active, true ), 'active filter' );
	$by_email = users_api( 'GET', '', array( 'search' => $tag . '_rich@' ) )->get_data();
	ok( 1 === count( $by_email ) && $rich === $by_email[0]['id'], 'search matches email' );

	$options = users_api( 'GET', '/options' )->get_data();
	ok( array( 'student', 'teacher', 'parent' ) === wp_list_pluck( $options['types'], 'value' ) && true === $options['can_create'], 'administrator may create every type' );
	ok( in_array( $rich_role, wp_list_pluck( $options['roles'], 'slug' ), true ), 'role filter choices list site roles' );

	// Create.
	$created = users_api(
		'POST',
		'',
		array(
			'type'       => 'teacher',
			'first_name' => ' New ',
			'last_name'  => 'Teacher',
			'email'      => $tag . '_new@example.invalid',
			'send_email' => true,
		)
	);
	ok( 201 === $created->get_status(), 'create answers 201: ' . wp_json_encode( $created->get_data() ) );
	$new_id     = $created->get_data()['id'];
	$user_ids[] = $new_id;
	ok( false !== strpos( $created->get_headers()['Location'], '/account-hub/users/' . $new_id ), 'Location header' );
	ok( 'New Teacher' === $created->get_data()['display_name'] && $tag . '_new' === $created->get_data()['username'], 'name trimmed, username derived from the email' );
	ok( in_array( 'ohmylms_teacher', get_userdata( $new_id )->roles, true ) && 1 === count( get_userdata( $new_id )->roles ), 'teacher role only' );
	ok( in_array( $tag . '_new@example.invalid', $mails, true ), 'password setup email sent' );
	$twin = users_api(
		'POST',
		'',
		array(
			'type'       => 'student',
			'first_name' => 'Twin',
			'email'      => $tag . '_new@example.org',
			'send_email' => false,
		)
	);
	ok( 201 === $twin->get_status() && $tag . '_new2' === $twin->get_data()['username'], 'derived usernames get a suffix' );
	$user_ids[] = $twin->get_data()['id'];
	ok_error( users_api( 'POST', '', array( 'type' => 'administrator', 'first_name' => 'A', 'email' => $tag . '_a@example.invalid' ) ), 400, 'rest_invalid_param', 'administrator is not a type' );
	$smuggled = users_api(
		'POST',
		'',
		array(
			'type'       => 'student',
			'first_name' => 'Smuggled',
			'email'      => $tag . '_smuggled@example.invalid',
			'role'       => 'administrator',
			'send_email' => false,
		)
	);
	ok( 201 === $smuggled->get_status(), 'student created' );
	$user_ids[] = $smuggled->get_data()['id'];
	ok( ! user_can( $smuggled->get_data()['id'], 'manage_options' ) && ! in_array( 'administrator', get_userdata( $smuggled->get_data()['id'] )->roles, true ), 'a smuggled role parameter is ignored' );
	ok_error( users_api( 'POST', '', array( 'first_name' => 'A', 'email' => $tag . '_a@example.invalid' ) ), 400, 'rest_missing_callback_param', 'type required' );
	ok_error( users_api( 'POST', '', array( 'type' => 'student', 'first_name' => 'A', 'email' => 'not-an-email' ) ), 400, 'rest_invalid_param', 'email format' );
	ok_error( users_api( 'POST', '', array( 'type' => 'student', 'first_name' => '   ', 'email' => $tag . '_a@example.invalid' ) ), 422, 'ohmylms_account_hub_invalid_name', 'blank first name' );
	ok_error( users_api( 'POST', '', array( 'type' => 'student', 'first_name' => 'A', 'email' => $tag . '_teacher@example.invalid' ) ), 409, 'ohmylms_account_hub_email_exists', 'duplicate email' );
	ok_error( users_api( 'POST', '', array( 'type' => 'student', 'first_name' => 'A', 'email' => $tag . '_a@example.invalid', 'username' => $tag . '_teacher' ) ), 409, 'ohmylms_account_hub_username_exists', 'explicit username taken' );
	ok_error( users_api( 'POST', '', array( 'type' => 'student', 'first_name' => 'A', 'email' => $tag . '_a@example.invalid', 'username' => '<b>x</b>' ) ), 422, 'ohmylms_account_hub_invalid_username', 'invalid username' );
	ok( ! email_exists( $tag . '_a@example.invalid' ), 'rejected creates wrote nothing' );

	// Edit.
	$loaded = users_api( 'GET', "/$teacher" );
	ok( 200 === $loaded->get_status() && $tag . '_teacher' === $loaded->get_data()['username'] && preg_match( '/^[a-f0-9]{16}$/', $loaded->get_data()['version'] ), 'read for editing' );
	$version = $loaded->get_data()['version'];
	$saved   = users_api(
		'PATCH',
		"/$teacher",
		array(
			'first_name' => 'Edited',
			'email'      => $tag . '_teacher2@example.invalid',
			'version'    => $version,
			'send_reset' => true,
		)
	);
	ok( 200 === $saved->get_status() && 'Edited' === $saved->get_data()['first_name'] && 'teacher' === $saved->get_data()['last_name'], 'partial update keeps the last name: ' . wp_json_encode( $saved->get_data() ) );
	ok( 'Edited teacher' === get_userdata( $teacher )->display_name && $tag . '_teacher2@example.invalid' === get_userdata( $teacher )->user_email, 'name and email stored' );
	ok( $version !== $saved->get_data()['version'], 'version changes after a save' );
	ok( in_array( $tag . '_teacher2@example.invalid', $mails, true ), 'password reset sent to the new address' );
	ok_error( users_api( 'PATCH', "/$teacher", array( 'first_name' => 'Stale', 'version' => $version ) ), 409, 'ohmylms_account_hub_conflict', 'stale version' );
	ok( 'Edited' === get_user_meta( $teacher, 'first_name', true ), 'stale save wrote nothing' );
	ok_error( users_api( 'PATCH', "/$teacher", array( 'email' => $tag . '_rich@example.invalid' ) ), 409, 'ohmylms_account_hub_email_exists', 'email of another account' );
	ok_error( users_api( 'PATCH', "/$teacher", array( 'version' => 'nothex' ) ), 400, 'rest_invalid_param', 'version format' );
	ok_error( users_api( 'GET', "/$admin" ), 403, 'ohmylms_account_hub_own_account', 'own account is edited on the profile' );
	ok_error( users_api( 'GET', '/99999999' ), 404, 'ohmylms_account_hub_user_not_found', 'unknown account' );

	// A user manager cannot reach beyond their own capabilities.
	wp_set_current_user( $manager );
	$seen = array();
	foreach ( users_api( 'GET', '', array( 'search' => $tag, 'per_page' => 100 ) )->get_data() as $row ) {
		$seen[ $row['id'] ] = $row['can_edit'];
	}
	ok( false === $seen[ $admin_two ] && false === $seen[ $rich ] && false === $seen[ $manager ] && true === $seen[ $teacher ], 'can_edit reflects the privilege limit' );
	$admin_email = get_userdata( $admin_two )->user_email;
	ok_error( users_api( 'GET', "/$admin_two" ), 403, 'ohmylms_account_hub_cannot_manage_user', 'manager cannot open an administrator' );
	ok_error( users_api( 'PATCH', "/$admin_two", array( 'email' => $tag . '_takeover@example.invalid' ) ), 403, 'ohmylms_account_hub_cannot_manage_user', 'manager cannot change an administrator email' );
	ok_error( users_api( 'PATCH', "/$rich", array( 'first_name' => 'X' ) ), 403, 'ohmylms_account_hub_cannot_manage_user', 'manager cannot edit a richer role' );
	ok( get_userdata( $admin_two )->user_email === $admin_email, 'administrator email unchanged' );
	ok( 200 === users_api( 'PATCH', "/$teacher", array( 'last_name' => 'Managed' ) )->get_status(), 'manager edits a teacher' );
	$manager_options = users_api( 'GET', '/options' )->get_data();
	ok( in_array( 'teacher', wp_list_pluck( $manager_options['types'], 'value' ), true ), 'manager may create teachers (role holds only read)' );
	$strip = static function ( $editable ) {
		unset( $editable['ohmylms_parent'] );
		return $editable;
	};
	add_filter( 'editable_roles', $strip );
	ok( ! in_array( 'parent', wp_list_pluck( users_api( 'GET', '/options' )->get_data()['types'], 'value' ), true ), 'non-editable roles are not offered' );
	ok_error( users_api( 'POST', '', array( 'type' => 'parent', 'first_name' => 'P', 'email' => $tag . '_p@example.invalid' ) ), 403, 'ohmylms_account_hub_type_not_allowed', 'non-editable role refused' );
	remove_filter( 'editable_roles', $strip );
	get_role( 'ohmylms_parent' )->add_cap( 'manage_options' );
	ok_error( users_api( 'POST', '', array( 'type' => 'parent', 'first_name' => 'P', 'email' => $tag . '_p@example.invalid' ) ), 403, 'ohmylms_account_hub_type_not_allowed', 'a role richer than the caller is refused' );
	get_role( 'ohmylms_parent' )->remove_cap( 'manage_options' );
	ok( ! email_exists( $tag . '_p@example.invalid' ), 'refused parent not created' );

	fwrite( STDOUT, "OK account-hub-users-rest-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	if ( get_role( 'ohmylms_parent' ) ) {
		get_role( 'ohmylms_parent' )->remove_cap( 'manage_options' );
	}
	foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT ID FROM %i WHERE user_email LIKE %s OR user_login LIKE %s', $wpdb->users, $wpdb->esc_like( $tag ) . '%', $wpdb->esc_like( $tag ) . '%' ) ) as $found ) {
		$user_ids[] = (int) $found;
	}
	$user_ids = array_unique( array_map( 'intval', $user_ids ) );
	if ( $user_ids && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Schema::table( 'school_audit_log' ) ) ) ) {
		$in = implode( ',', $user_ids );
		$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE school_id=0 AND object_id IN ($in) AND action IN ('account_created_by_admin','account_updated_by_admin')", Schema::table( 'school_audit_log' ) ) );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
	foreach ( $roles as $role ) {
		remove_role( $role );
	}
}

exit( isset( $exit_code ) ? $exit_code : 0 );
