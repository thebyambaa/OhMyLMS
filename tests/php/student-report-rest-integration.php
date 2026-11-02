<?php
/**
 * Student report and course roster REST integration test.
 *
 * Runs against a WordPress install (the Local site by default) with real tables. It creates tagged
 * users, courses, attempts, skill states and reminder rows, intercepts every e-mail with `pre_wp_mail`
 * and removes everything in `finally`.
 *
 * Usage (set OHMYLMS_STUDENT_REPORT_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/student-report-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery, Generic.Formatting.MultipleStatementAlignment

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_STUDENT_REPORT_TEST' ) ) {
	exit( "Set OHMYLMS_STUDENT_REPORT_TEST=1 to run the student report REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

global $wpdb;

$checks     = 0;
$user_ids   = array();
$course_ids = array();
$post_ids   = array();
$tag        = 'srtest' . strtolower( wp_generate_password( 6, false, false ) );
$mails      = array();

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
 * @throws RuntimeException When WordPress refuses to create it.
 */
function make_user( $suffix, $role ) {
	global $tag, $user_ids;
	$id = wp_insert_user(
		array(
			'user_login'   => $tag . '_' . $suffix,
			'user_pass'    => wp_generate_password( 24 ),
			'user_email'   => $tag . '.' . $suffix . '@example.invalid',
			'display_name' => 'Student ' . $suffix,
			'role'         => $role,
		)
	);
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( 'FAILED: could not create user ' . $suffix );
	}
	$user_ids[] = $id;
	return $id;
}

/**
 * Create a test post.
 *
 * @param string $type  Post type.
 * @param string $title Title suffix.
 * @return int
 * @throws RuntimeException When WordPress refuses to create it.
 */
function make_post( $type, $title ) {
	global $tag, $post_ids;
	$id = wp_insert_post(
		array(
			'post_type'   => $type,
			'post_status' => 'publish',
			'post_title'  => $tag . ' ' . $title,
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( 'FAILED: could not create ' . $type );
	}
	$post_ids[] = $id;
	return $id;
}

add_filter(
	'pre_wp_mail',
	static function ( $short, $atts ) use ( &$mails ) {
		$mails[] = $atts;
		return true;
	},
	10,
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
	delete_transient( 'ohmylms_reminder_quota_' . $admin );

	$alice   = make_user( 'alice', 'subscriber' );
	$bob     = make_user( 'bob', 'subscriber' );
	$carol   = make_user( 'carol', 'subscriber' );
	$editor  = make_user( 'editor', 'editor' );
	$course  = make_post( 'ohmylms-course', 'Roster course' );
	$quiz    = make_post( 'ohmylms-quiz', 'Quiz' );
	$task    = make_post( 'ohmylms-assignment', 'Assignment' );
	$page_id = make_post( 'page', 'Not a course' );
	update_post_meta( $task, '_total_points', 80 );

	// Routes have real permission callbacks.
	$routes = rest_get_server()->get_routes( 'ohmylms/v1' );
	foreach ( array( '/students/(?P<id>[\d]+)/report', '/students/(?P<id>[\d]+)/quiz-attempts', '/students/(?P<id>[\d]+)/assignment-submissions', '/students/(?P<id>[\d]+)/skills', '/analytics/courses/(?P<id>[\d]+)/enrollments', '/analytics/courses/(?P<id>[\d]+)/enrollments/bulk', '/analytics/courses/(?P<id>[\d]+)/reminders' ) as $route ) {
		ok( isset( $routes[ '/ohmylms/v1' . $route ] ), 'route registered: ' . $route );
		foreach ( $routes[ '/ohmylms/v1' . $route ] as $endpoint ) {
			ok( is_callable( $endpoint['permission_callback'] ), 'permission_callback on ' . $route );
		}
	}

	// Authorisation.
	wp_set_current_user( 0 );
	ok( 401 === call_api( 'GET', '/students/' . $alice . '/report' )->get_status(), 'anonymous report is 401' );
	ok( 401 === call_api( 'POST', '/analytics/courses/' . $course . '/reminders', array( 'template' => 'continue', 'user_ids' => array( $alice ) ) )->get_status(), 'anonymous reminder is 401' );
	wp_set_current_user( $editor );
	$denied = call_api( 'GET', '/students/' . $alice . '/quiz-attempts' );
	ok( 403 === $denied->get_status() && 'ohmylms_student_report_cannot_view' === code_of( $denied ), 'editor report is 403' );
	ok( 403 === call_api( 'POST', '/analytics/courses/' . $course . '/enrollments', array( 'email' => $tag . '.alice@example.invalid' ) )->get_status(), 'editor enrol is 403' );
	ok( 403 === call_api( 'POST', '/notification/course/' . $course . '/student/' . $alice, array( 'email' => 'x@example.invalid', 'subject' => 's', 'message' => 'm' ) )->get_status(), 'editor cannot use the retired relay' );
	ok( array() === $mails, 'no mail sent to denied callers' );

	wp_set_current_user( $admin );

	// Enrol by e-mail.
	$enrolled = call_api( 'POST', '/analytics/courses/' . $course . '/enrollments', array( 'email' => $tag . '.alice@example.invalid' ) );
	ok( 201 === $enrolled->get_status(), 'enrol by email is 201' );
	ok( $alice === $enrolled->get_data()['user_id'] && $course === $enrolled->get_data()['enrollment']['course_id'], 'enrol response names user and course' );
	ok( ! isset( $enrolled->get_data()['email'] ), 'enrol response does not echo the email' );
	ok( 409 === call_api( 'POST', '/analytics/courses/' . $course . '/enrollments', array( 'email' => $tag . '.alice@example.invalid' ) )->get_status(), 'duplicate enrol is 409' );
	$missing = call_api( 'POST', '/analytics/courses/' . $course . '/enrollments', array( 'email' => $tag . '.nobody@example.invalid' ) );
	ok( 404 === $missing->get_status() && 'ohmylms_roster_user_not_found' === code_of( $missing ), 'unknown email is 404' );
	ok( 400 === call_api( 'POST', '/analytics/courses/' . $course . '/enrollments', array( 'email' => 'not an email' ) )->get_status(), 'malformed email is 400' );
	ok( 404 === call_api( 'POST', '/analytics/courses/' . $page_id . '/enrollments', array( 'email' => $tag . '.bob@example.invalid' ) )->get_status(), 'non-course is 404' );
	ok( 201 === call_api( 'POST', '/analytics/courses/' . $course . '/enrollments', array( 'email' => $tag . '.bob@example.invalid' ) )->get_status(), 'second student enrolled' );

	// Report data.
	$wpdb->insert(
		$wpdb->prefix . 'ohmylms_quiz_attempts',
		array(
			'course_id'  => $course,
			'quiz_id'    => $quiz,
			'student_id' => $alice,
			'total'      => 3,
			'status'     => 'completed',
			'start_date' => '2026-10-01 10:00:00',
			'end_date'   => '2026-10-01 10:20:00',
		)
	);
	$attempt = (int) $wpdb->insert_id;
	foreach ( array( 2, 2 ) as $marks ) {
		$wpdb->insert(
			$wpdb->prefix . 'ohmylms_quiz_attempts_answers',
			array(
				'student_id'      => $alice,
				'quiz_id'         => $quiz,
				'question_id'     => 0,
				'quiz_attempt_id' => $attempt,
				'given_answer'    => '',
				'question_marks'  => $marks,
			)
		);
	}
	$wpdb->insert(
		$wpdb->prefix . 'ohmylms_quiz_attempts',
		array(
			'course_id'  => $course,
			'quiz_id'    => $quiz,
			'student_id' => $alice,
			'total'      => 0,
			'status'     => 'in-progress',
			'start_date' => '2026-10-02 10:00:00',
		)
	);
	$wpdb->insert(
		$wpdb->prefix . 'ohmylms_assignment_attempts',
		array(
			'user_id'       => $alice,
			'course_id'     => $course,
			'assignment_id' => $task,
			'content'       => 'Private essay text',
			'files'         => '',
			'score'         => 60,
			'total_attempt' => 1,
			'status'        => 'passed',
			'start_date'    => '2026-10-03 09:00:00',
			'end_date'      => '2026-10-04 09:00:00',
		)
	);
	$skill_ready = \OhMyLMS\Assessment\Schema::ready();
	$skill_term  = null;
	if ( $skill_ready ) {
		$term       = wp_insert_term( $tag . ' Fractions', 'ohmylms_skill' );
		$skill_term = is_wp_error( $term ) ? null : (int) $term['term_id'];
		ok( null !== $skill_term, 'skill term created' );
		if ( $skill_term ) {
			$wpdb->insert(
				\OhMyLMS\Assessment\Schema::table( 'student_skill_state' ),
				array(
					'student_id'     => $alice,
					'term_id'        => $skill_term,
					'level'          => 'practising',
					'review_due'     => 1,
					'evidence_count' => 4,
					'accuracy'       => 0.75,
					'updated_at'     => current_time( 'mysql' ),
				)
			);
		}
	}

	$summary = call_api( 'GET', '/students/' . $alice . '/report' );
	ok( 200 === $summary->get_status(), 'summary is 200' );
	$data = $summary->get_data();
	ok( 1 === $data['courses']['enrolled'] && 1 === $data['courses']['in_progress'], 'summary counts courses' );
	ok( 1 === $data['quizzes']['attempts'] && 75.0 === $data['quizzes']['average_percent'], 'summary averages submitted attempts only' );
	ok( 1 === $data['assignments']['submitted'] && 1 === $data['assignments']['passed'], 'summary counts assignments' );
	if ( $skill_term ) {
		ok( 1 === $data['skills']['total'] && 1 === $data['skills']['review_due'], 'summary counts skills' );
	}
	ok( 404 === call_api( 'GET', '/students/' . $editor . '/report' )->get_status(), 'non-student report is 404' );
	ok( 404 === call_api( 'GET', '/students/999999999/report' )->get_status(), 'unknown user report is 404' );

	$attempts = call_api( 'GET', '/students/' . $alice . '/quiz-attempts', array( 'per_page' => 1 ) );
	ok( 200 === $attempts->get_status() && '2' === $attempts->get_headers()['X-WP-Total'] && '2' === $attempts->get_headers()['X-WP-TotalPages'], 'attempts are paged' );
	ok( 'in-progress' === $attempts->get_data()[0]['status'] && null === $attempts->get_data()[0]['submitted_at'], 'newest attempt first, unsubmitted has no date' );
	$second = call_api( 'GET', '/students/' . $alice . '/quiz-attempts', array( 'per_page' => 1, 'page' => 2 ) )->get_data()[0];
	ok( 75.0 === $second['percentage'] && 4.0 === $second['max_marks'] && $tag . ' Quiz' === $second['quiz_title'], 'attempt percentage and titles' );
	ok( ! isset( $second['student'] ), 'attempt rows carry no student identity' );
	ok( 400 === call_api( 'GET', '/students/' . $alice . '/quiz-attempts', array( 'page' => 3, 'per_page' => 1 ) )->get_status(), 'page past the end is 400' );
	ok( 400 === call_api( 'GET', '/students/' . $alice . '/quiz-attempts', array( 'per_page' => 51 ) )->get_status(), 'per_page above 50 is 400' );

	$submissions = call_api( 'GET', '/students/' . $alice . '/assignment-submissions' )->get_data();
	ok( 1 === count( $submissions ) && 'graded' === $submissions[0]['status'] && 'passed' === $submissions[0]['result'] && 60 === $submissions[0]['score'] && 80 === $submissions[0]['max_score'], 'submission row' );
	ok( ! isset( $submissions[0]['content'] ) && ! isset( $submissions[0]['files'] ), 'submission rows carry no text or files' );

	if ( $skill_term ) {
		$skills = call_api( 'GET', '/students/' . $alice . '/skills', array( 'filter' => 'review_due' ) )->get_data();
		ok( 1 === count( $skills ) && $skill_term === $skills[0]['skill_id'] && $skills[0]['review_due'], 'review-due skills' );
		ok( array() === call_api( 'GET', '/students/' . $alice . '/skills', array( 'filter' => 'struggling' ) )->get_data(), 'struggling filter' );
	}
	ok( 400 === call_api( 'GET', '/students/' . $alice . '/skills', array( 'filter' => 'bogus' ) )->get_status(), 'unknown skill filter is 400' );

	// Reminders.
	ok( 400 === call_api( 'POST', '/analytics/courses/' . $course . '/reminders', array( 'template' => 'custom', 'user_ids' => array( $alice ) ) )->get_status(), 'free template is 400' );
	ok( 400 === call_api( 'POST', '/analytics/courses/' . $course . '/reminders', array( 'template' => 'continue', 'user_ids' => range( 1, 21 ) ) )->get_status(), 'more than 20 recipients is 400' );
	$relay = call_api( 'POST', '/notification/course/' . $course . '/student/' . $alice, array( 'email' => 'victim@example.invalid', 'subject' => 'Spam', 'message' => '<a href="x">x</a>' ) );
	ok( 410 === $relay->get_status() && 'ohmylms_reminder_route_retired' === code_of( $relay ), 'retired relay answers 410' );
	ok( array() === $mails, 'retired relay sends nothing' );

	$sent = call_api(
		'POST',
		'/analytics/courses/' . $course . '/reminders',
		array(
			'template' => 'continue',
			'user_ids' => array( $alice, $carol ),
			'email'    => 'victim@example.invalid',
			'subject'  => 'Injected',
		)
	);
	ok( 200 === $sent->get_status(), 'reminder is 200' );
	ok( array( $alice ) === $sent->get_data()['sent'], 'only the enrolled student is reminded' );
	ok( array( array( 'user_id' => $carol, 'reason' => 'not_enrolled' ) ) === $sent->get_data()['skipped'], 'not enrolled student skipped' );
	ok( 1 === count( $mails ) && $tag . '.alice@example.invalid' === $mails[0]['to'], 'mail goes to the enrolled student only' );
	ok( false === strpos( $mails[0]['subject'], 'Injected' ) && false !== strpos( $mails[0]['subject'], 'Roster course' ), 'subject is the fixed template' );
	ok( false === strpos( (string) $mails[0]['message'], '<' ), 'body is plain text' );
	$again = call_api( 'POST', '/analytics/courses/' . $course . '/reminders', array( 'template' => 'start', 'user_ids' => array( $alice ) ) )->get_data();
	ok( array() === $again['sent'] && 'recently_reminded' === $again['skipped'][0]['reason'], 'second reminder within the interval is skipped' );

	$wpdb->update( $wpdb->prefix . 'ohmylms_user_enrollment', array( 'progress' => 'completed' ), array( 'user_id' => $bob, 'course_id' => $course ) );
	$done = call_api( 'POST', '/analytics/courses/' . $course . '/reminders', array( 'template' => 'complete', 'user_ids' => array( $bob ) ) )->get_data();
	ok( 'completed' === $done['skipped'][0]['reason'], 'completed students are not reminded' );

	$limit = static function () {
		return 1;
	};
	add_filter( 'ohmylms_reminder_hourly_limit', $limit );
	$limited = call_api( 'POST', '/analytics/courses/' . $course . '/reminders', array( 'template' => 'continue', 'user_ids' => array( $bob ) ) );
	ok( 429 === $limited->get_status() && 'ohmylms_reminder_rate_limited' === code_of( $limited ), 'hourly quota answers 429' );
	remove_filter( 'ohmylms_reminder_hourly_limit', $limit );
	ok( 1 === count( $mails ), 'exactly one mail sent overall' );

	// Bulk unenrol.
	$bulk = call_api( 'POST', '/analytics/courses/' . $course . '/enrollments/bulk', array( 'action' => 'unenroll', 'user_ids' => array( $bob, $carol ) ) );
	ok( 200 === $bulk->get_status(), 'bulk is 200' );
	$results = $bulk->get_data()['results'];
	ok( $results[0]['ok'] && ! $results[1]['ok'] && 'ohmylms_enrollment_not_found' === $results[1]['code'], 'bulk reports each student' );
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE user_id = %d AND course_id = %d", $bob, $course ) ), 'bulk removed the enrolment' );
	ok( 400 === call_api( 'POST', '/analytics/courses/' . $course . '/enrollments/bulk', array( 'action' => 'delete_user', 'user_ids' => array( $alice ) ) )->get_status(), 'unknown bulk action is 400' );
	ok( 400 === call_api( 'POST', '/analytics/courses/' . $course . '/enrollments/bulk', array( 'action' => 'unenroll', 'user_ids' => range( 1, 51 ) ) )->get_status(), 'more than 50 bulk ids is 400' );

	fwrite( STDOUT, "OK student-report-rest-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	foreach ( $user_ids as $user_id ) {
		foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE user_id = %d", $user_id ) ) as $leftover ) {
			$wpdb->delete( $wpdb->prefix . 'ohmylms_user_progress', array( 'enrollment_id' => (int) $leftover ), array( '%d' ) );
		}
		$wpdb->delete( $wpdb->prefix . 'ohmylms_user_enrollment', array( 'user_id' => $user_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_quiz_attempts', array( 'student_id' => $user_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_quiz_attempts_answers', array( 'student_id' => $user_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_assignment_attempts', array( 'user_id' => $user_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_notifications', array( 'student_id' => $user_id ), array( '%d' ) );
		if ( \OhMyLMS\Assessment\Schema::ready() ) {
			$wpdb->delete( \OhMyLMS\Assessment\Schema::table( 'student_skill_state' ), array( 'student_id' => $user_id ), array( '%d' ) );
		}
	}
	if ( ! empty( $skill_term ) ) {
		wp_delete_term( $skill_term, 'ohmylms_skill' );
	}
	foreach ( $post_ids as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	if ( isset( $admin ) ) {
		delete_transient( 'ohmylms_reminder_quota_' . $admin );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
}

exit( isset( $exit_code ) ? $exit_code : 0 );
