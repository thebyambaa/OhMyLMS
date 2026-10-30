<?php
/**
 * Live sessions REST integration test.
 *
 * Runs against a WordPress install (the Local site by default). The provider hooks
 * (`ohmylms_{zoom,googlemeet}_session_{created,updated,deleted}`) are replaced by a recording fake, so
 * no request reaches Zoom or Google. Creates tagged users, a course and sessions and removes them.
 *
 * Usage (set OHMYLMS_SESSIONS_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/sessions-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery, Generic.Formatting.MultipleStatementAlignment

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_SESSIONS_TEST' ) ) {
	exit( "Set OHMYLMS_SESSIONS_TEST=1 to run the sessions REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

global $wpdb;

$checks   = 0;
$user_ids = array();
$post_ids = array();
$tag      = 'sstest' . strtolower( wp_generate_password( 6, false, false ) );
$calls    = array();
$fail     = false;

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
 * @param string $path   Path below /ohmylms/v1.
 * @param array  $data   Body (JSON) or query (GET).
 * @return WP_REST_Response
 */
function call_api( $method, $path, array $data = array() ) {
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
 * Error code of a response.
 *
 * @param WP_REST_Response $response Response.
 * @return string
 */
function code_of( $response ) {
	$data = $response->get_data();
	return is_array( $data ) && isset( $data['code'] ) ? (string) $data['code'] : '';
}

/**
 * Create a test user.
 *
 * @param string $suffix Login suffix.
 * @param string $role   Role.
 * @return int
 */
function make_user( $suffix, $role ) {
	global $tag, $user_ids;
	$id         = wp_insert_user(
		array(
			'user_login'   => $tag . '_' . $suffix,
			'user_pass'    => wp_generate_password( 24 ),
			'user_email'   => $tag . '.' . $suffix . '@example.invalid',
			'display_name' => 'User ' . $suffix,
			'role'         => $role,
		)
	);
	$user_ids[] = $id;
	return $id;
}

foreach ( array( 'zoom', 'googlemeet' ) as $provider ) {
	foreach ( array( 'created', 'updated', 'deleted' ) as $event ) {
		remove_all_actions( "ohmylms_{$provider}_session_{$event}" );
	}
	foreach ( array( 'created', 'updated' ) as $event ) {
		add_action(
			"ohmylms_{$provider}_session_{$event}",
			static function ( $post, $request ) use ( $provider, $event, &$calls, &$fail ) {
				$calls[] = array( $provider, $event, $request->get_params() );
				if ( $fail ) {
					return;
				}
				update_post_meta( $post->ID, "_is_{$provider}_session_created", 'yes' );
				if ( 'zoom' === $provider ) {
					update_post_meta( $post->ID, '_zoom_meeting_data', wp_json_encode( array( 'id' => 987, 'join_url' => 'https://zoom.us/j/987?pwd=abc', 'start_url' => 'https://zoom.us/s/987?zak=HOSTSECRET' ) ) );
				} else {
					update_post_meta( $post->ID, '_join_url', 'https://meet.google.com/abc-defg-hij' );
					update_post_meta( $post->ID, '_start_url', 'https://meet.google.com/abc-defg-hij' );
				}
			},
			10,
			2
		);
	}
	add_action(
		"ohmylms_{$provider}_session_deleted",
		static function ( $id ) use ( $provider, &$calls ) {
			$calls[] = array( $provider, 'deleted', $id );
		},
		10,
		2
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
	$admin       = (int) $admins[0];
	$contributor = make_user( 'contrib', 'contributor' );
	$subscriber  = make_user( 'sub', 'subscriber' );
	$learner     = make_user( 'learner', 'subscriber' );
	$course      = wp_insert_post( array( 'post_type' => 'ohmylms-course', 'post_status' => 'publish', 'post_title' => $tag . ' Course' ) );
	$post_ids[]  = $course;

	$valid = array(
		'title'    => $tag . ' Weekly review',
		'provider' => 'zoom',
		'start'    => '2030-03-04T10:00',
		'timezone' => 'Europe/Berlin',
		'duration' => 45,
		'course_id' => $course,
		'password' => 'abc123',
		'status'   => 'publish',
		'options'  => array( 'auto_record' => true ),
	);

	// Authorisation.
	wp_set_current_user( 0 );
	ok( 401 === call_api( 'GET', '/sessions' )->get_status(), 'anonymous list is 401' );
	wp_set_current_user( $subscriber );
	ok( 403 === call_api( 'GET', '/sessions' )->get_status(), 'subscriber list is 403' );
	ok( 403 === call_api( 'POST', '/sessions', $valid )->get_status(), 'subscriber create is 403' );
	wp_set_current_user( $contributor );
	ok( 403 === call_api( 'POST', '/sessions', $valid )->get_status(), 'contributor cannot publish' );

	wp_set_current_user( $admin );

	// Validation.
	ok( 400 === call_api( 'POST', '/sessions', array( 'title' => 'x' ) )->get_status(), 'missing fields are 400' );
	ok( 400 === call_api( 'POST', '/sessions', array( 'duration' => 2 ) + $valid )->get_status(), 'duration below 5 is 400' );
	ok( 400 === call_api( 'POST', '/sessions', array( 'start' => '2030-03-04 10:00' ) + $valid )->get_status(), 'malformed start is 400' );
	ok( 400 === call_api( 'POST', '/sessions', array( 'password' => 'way too long!' ) + $valid )->get_status(), 'bad passcode is 400' );
	ok( 400 === call_api( 'POST', '/sessions', array( 'provider' => 'teams' ) + $valid )->get_status(), 'unknown provider is 400' );
	$bad = call_api( 'POST', '/sessions', array( 'start' => '2030-02-30T10:00' ) + $valid );
	ok( 422 === $bad->get_status() && 'ohmylms_session_invalid_start' === code_of( $bad ), 'impossible date is 422' );
	ok( 'ohmylms_session_invalid_timezone' === code_of( call_api( 'POST', '/sessions', array( 'timezone' => 'Mars/Base' ) + $valid ) ), 'unknown time zone is 422' );
	ok( 'ohmylms_session_invalid_password' === code_of( call_api( 'POST', '/sessions', array( 'provider' => 'googlemeet' ) + $valid ) ), 'Meet takes no passcode' );
	ok( 'ohmylms_session_invalid_course' === code_of( call_api( 'POST', '/sessions', array( 'course_id' => $contributor ) + $valid ) ), 'non-course is 422' );
	ok( array() === $calls, 'invalid requests never reach the provider' );

	// Provider failure leaves nothing behind.
	$fail   = true;
	$failed = call_api( 'POST', '/sessions', array( 'title' => $tag . ' Failing' ) + $valid );
	ok( 502 === $failed->get_status() && 'ohmylms_session_provider_failed' === code_of( $failed ), 'provider failure is 502' );
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title = %s", $tag . ' Failing' ) ), 'failed create leaves no post' );
	$fail  = false;
	$calls = array();

	// Create.
	$created = call_api( 'POST', '/sessions', $valid );
	ok( 201 === $created->get_status(), 'create is 201' );
	$session    = $created->get_data();
	$post_ids[] = $session['id'];
	ok( 'no-store' === $created->get_headers()['Cache-Control'], 'session responses are not cached' );
	ok( '2030-03-04T10:00' === $session['start'] && 'Europe/Berlin' === $session['timezone'] && 45 === $session['duration'], 'schedule round-trips' );
	ok( $session['meeting']['created'] && 'https://zoom.us/j/987?pwd=abc' === $session['meeting']['join_url'], 'join link from the provider' );
	ok( $course === $session['course']['id'] && 'upcoming' === $session['state'], 'course and state' );
	ok( $session['options']['auto_record'] && ! $session['options']['join_before_host'], 'options stored' );
	$json = wp_json_encode( $session );
	ok( false === strpos( $json, 'HOSTSECRET' ) && false === strpos( $json, 'start_url' ), 'host start link never returned' );
	ok( 1 === count( $calls ) && 'created' === $calls[0][1], 'provider created once' );
	ok( '2030-03-04T10:00:00' === $calls[0][2]['date'] && '2030-03-04T10:45:00' === $calls[0][2]['endDate'] && 'abc123' === $calls[0][2]['password'], 'provider receives normalised schedule' );

	// Read and list.
	$read = call_api( 'GET', '/sessions/' . $session['id'] );
	ok( 200 === $read->get_status() && 'abc123' === $read->get_data()['password'], 'editor reads passcode' );
	$list = call_api( 'GET', '/sessions', array( 'search' => $tag, 'per_page' => 1 ) );
	ok( 200 === $list->get_status() && '1' === $list->get_headers()['X-WP-Total'], 'list is paged and searchable' );
	ok( ! isset( $list->get_data()[0]['password'] ) && ! isset( $list->get_data()[0]['description'] ), 'list rows are summaries' );
	ok( '0' === call_api( 'GET', '/sessions', array( 'search' => $tag, 'provider' => 'googlemeet' ) )->get_headers()['X-WP-Total'], 'provider filter' );
	ok( 400 === call_api( 'GET', '/sessions', array( 'per_page' => 51 ) )->get_status(), 'per_page above 50 is 400' );
	wp_set_current_user( $contributor );
	ok( '0' === call_api( 'GET', '/sessions', array( 'search' => $tag ) )->get_headers()['X-WP-Total'], 'contributors list only their own sessions' );
	ok( 403 === call_api( 'GET', '/sessions/' . $session['id'] )->get_status(), 'contributor cannot read another host session' );
	$legacy = call_api( 'GET', '/session/' . $session['id'] );
	ok( 200 !== $legacy->get_status() || '' === $legacy->get_data()['start_url'], 'legacy route hides the host link from non-editors' );
	wp_set_current_user( $admin );
	ok( false !== strpos( call_api( 'GET', '/session/' . $session['id'] )->get_data()['start_url'], 'zak=' ), 'legacy route keeps the host link for editors' );

	// Update.
	$calls   = array();
	$updated = call_api( 'PATCH', '/sessions/' . $session['id'], array( 'title' => $tag . ' Renamed', 'duration' => 60 ) );
	ok( 200 === $updated->get_status() && $tag . ' Renamed' === $updated->get_data()['title'] && 60 === $updated->get_data()['duration'], 'update merges fields' );
	ok( 'updated' === $calls[0][1] && 'abc123' === $calls[0][2]['password'], 'provider updated with stored passcode' );
	$locked = call_api( 'PATCH', '/sessions/' . $session['id'], array( 'provider' => 'googlemeet', 'password' => '' ) );
	ok( 409 === $locked->get_status() && 'ohmylms_session_provider_locked' === code_of( $locked ), 'provider cannot change' );
	$fail   = true;
	$broken = call_api( 'PATCH', '/sessions/' . $session['id'], array( 'title' => $tag . ' Lost' ) );
	$fail   = false;
	ok( 502 === $broken->get_status(), 'provider failure on update is 502' );
	$after = call_api( 'GET', '/sessions/' . $session['id'] )->get_data();
	ok( $tag . ' Renamed' === $after['title'] && $after['meeting']['created'], 'failed update restores the session' );

	// Attendees.
	$wpdb->insert( $wpdb->prefix . 'ohmylms_user_enrollment', array( 'user_id' => $learner, 'course_id' => $course, 'status' => 'enrolled', 'progress' => 'running', 'start_date' => current_time( 'mysql' ) ) );
	$enrolment = (int) $wpdb->insert_id;
	$wpdb->insert( $wpdb->prefix . 'ohmylms_user_progress', array( 'enrollment_id' => $enrolment, 'content_id' => $session['id'], 'content_type' => 'session', 'status' => 'completed', 'start_date' => current_time( 'mysql' ) ) );
	$attendees = call_api( 'GET', '/sessions/' . $session['id'] . '/attendees' );
	ok( 200 === $attendees->get_status() && '1' === $attendees->get_headers()['X-WP-Total'], 'attendees are the course students' );
	ok( array( 'user_id' => $learner, 'name' => 'User learner', 'status' => 'enrolled', 'completed' => true ) === $attendees->get_data()[0], 'attendee row is minimal' );

	// Providers.
	$providers = call_api( 'GET', '/sessions/providers' );
	ok( 200 === $providers->get_status() && 2 === count( $providers->get_data() ), 'providers listed' );
	foreach ( $providers->get_data() as $row ) {
		ok( array( 'id', 'label', 'available', 'configured' ) === array_keys( $row ) && is_bool( $row['configured'] ), 'provider row exposes booleans only: ' . $row['id'] );
	}

	$choices = call_api( 'GET', '/sessions/courses', array( 'search' => $tag ) )->get_data();
	ok( array( array( 'id' => $course, 'title' => $tag . ' Course' ) ) === $choices, 'course choices' );
	wp_set_current_user( $contributor );
	ok( array() === call_api( 'GET', '/sessions/courses', array( 'search' => $tag ) )->get_data(), 'contributors see only their own courses' );
	wp_set_current_user( $admin );

	// Delete.
	$calls   = array();
	$deleted = call_api( 'DELETE', '/sessions/' . $session['id'] );
	ok( 200 === $deleted->get_status() && true === $deleted->get_data()['deleted'], 'delete is 200' );
	ok( array( 'zoom', 'deleted', $session['id'] ) === $calls[0], 'provider asked to delete the meeting' );
	ok( 404 === call_api( 'GET', '/sessions/' . $session['id'] )->get_status(), 'deleted session is 404' );

	fwrite( STDOUT, "OK sessions-rest-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	foreach ( $user_ids as $user_id ) {
		foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE user_id = %d", $user_id ) ) as $leftover ) {
			$wpdb->delete( $wpdb->prefix . 'ohmylms_user_progress', array( 'enrollment_id' => (int) $leftover ) );
		}
		$wpdb->delete( $wpdb->prefix . 'ohmylms_user_enrollment', array( 'user_id' => $user_id ) );
	}
	foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE %s", $wpdb->esc_like( $tag ) . '%' ) ) as $id ) {
		wp_delete_post( (int) $id, true );
	}
	foreach ( $post_ids as $id ) {
		wp_delete_post( (int) $id, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
}

exit( isset( $exit_code ) ? $exit_code : 0 );
