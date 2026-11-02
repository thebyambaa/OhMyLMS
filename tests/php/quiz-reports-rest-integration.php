<?php
/**
 * Quiz reports overview REST integration test (`GET /reports/quizzes`, `GET /reports/quizzes/summary`).
 *
 * Runs against a WordPress install (the Local site by default). Creates tagged users, a course, a chapter,
 * quizzes and attempt rows, checks scoping, figures, filters, sorting, paging and validation, and removes
 * everything it created in `finally`.
 *
 * Usage (set OHMYLMS_QUIZ_REPORTS_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/quiz-reports-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery, Generic.Formatting.MultipleStatementAlignment

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_QUIZ_REPORTS_TEST' ) ) {
	exit( "Set OHMYLMS_QUIZ_REPORTS_TEST=1 to run the quiz reports REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

global $wpdb;

$checks    = 0;
$exit_code = 0;
$user_ids  = array();
$post_ids  = array();
$tag       = 'qrtest' . strtolower( wp_generate_password( 6, false, false ) );

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
 * Dispatch a GET request.
 *
 * @param string $path  Path below /ohmylms/v1.
 * @param array  $query Query parameters.
 * @return WP_REST_Response
 */
function call_api( $path, array $query = array() ) {
	$request = new WP_REST_Request( 'GET', '/ohmylms/v1' . $path );
	$request->set_query_params( $query );
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
 * Create a tagged user.
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
			'display_name' => $tag . ' Person ' . $suffix,
			'role'         => $role,
		)
	);
	$user_ids[] = $id;
	return $id;
}

/**
 * Create a tagged post.
 *
 * @param string $type   Post type.
 * @param string $title  Title suffix.
 * @param int    $author Author.
 * @param string $status Status.
 * @return int
 */
function make_post( $type, $title, $author, $status = 'publish' ) {
	global $tag, $post_ids;
	$id         = wp_insert_post(
		array(
			'post_type'   => $type,
			'post_title'  => "$tag $title",
			'post_status' => $status,
			'post_author' => $author,
		)
	);
	$post_ids[] = $id;
	return $id;
}

/**
 * Insert an attempt row.
 *
 * @param int    $quiz    Quiz ID.
 * @param int    $course  Course ID.
 * @param int    $student Student ID.
 * @param string $status  Attempt status.
 * @param float  $total   Marks.
 * @param string $ended   Local end date.
 */
function make_attempt( $quiz, $course, $student, $status, $total, $ended ) {
	global $wpdb;
	$wpdb->insert(
		$wpdb->prefix . 'ohmylms_quiz_attempts',
		array(
			'course_id'  => $course,
			'quiz_id'    => $quiz,
			'student_id' => $student,
			'total'      => $total,
			'status'     => $status,
			'start_date' => '2026-10-01 09:00:00',
			'end_date'   => 'in-progress' === $status ? '0000-00-00 00:00:00' : $ended,
		)
	);
}

/**
 * IDs of a list response.
 *
 * @param WP_REST_Response $response Response.
 * @return int[]
 */
function ids_of( $response ) {
	return array_map( 'intval', wp_list_pluck( (array) $response->get_data(), 'id' ) );
}

try {
	$admins     = get_users(
		array(
			'role'    => 'administrator',
			'number'  => 1,
			'orderby' => 'ID',
			'fields'  => 'ID',
		)
	);
	$admin      = (int) $admins[0];
	$author_a   = make_user( 'authora', 'author' );
	$author_b   = make_user( 'authorb', 'author' );
	$author_c   = make_user( 'authorc', 'author' );
	$editor     = make_user( 'editor', 'editor' );
	$subscriber = make_user( 'sub', 'subscriber' );
	$students   = array( make_user( 's1', 'subscriber' ), make_user( 's2', 'subscriber' ), make_user( 's3', 'subscriber' ) );

	// Quiz 1 (author A) sits in author B's course and has a pass mark of 5 marks.
	$course  = make_post( OHMYLMS_COURSE_CPT, 'Course', $author_b );
	$chapter = make_post( 'ohmylms-chapter', 'Chapter', $author_b );
	$quiz1   = make_post( OHMYLMS_QUIZ_CPT, 'Alpha quiz', $author_a );
	$quiz2   = make_post( OHMYLMS_QUIZ_CPT, 'Beta quiz', $author_a, 'draft' );
	$quiz3   = make_post( OHMYLMS_QUIZ_CPT, 'Gamma quiz', $editor );
	$wpdb->insert(
		$wpdb->prefix . 'ohmylms_chapter_relationship',
		array(
			'course_id'    => $course,
			'chapter_id'   => $chapter,
			'order_number' => 0,
		)
	);
	$wpdb->insert(
		$wpdb->prefix . 'ohmylms_content_relationship',
		array(
			'chapter_id'   => $chapter,
			'content_id'   => $quiz1,
			'content_type' => 'quiz',
			'order_number' => 0,
		)
	);
	update_post_meta(
		$quiz1,
		'_quiz_settings',
		array(
			'passing_grade' => array(
				'enabled' => true,
				'value'   => 5,
			),
		)
	);
	make_attempt( $quiz1, $course, $students[0], 'completed', 7, '2026-10-02 10:00:00' );
	make_attempt( $quiz1, $course, $students[1], 'completed', 3, '2026-10-03 10:00:00' );
	make_attempt( $quiz1, $course, $students[2], 'in-review', 2, '2026-10-04 10:00:00' );
	make_attempt( $quiz1, $course, $students[0], 'in-progress', 0, '' );
	make_attempt( $quiz3, 0, $students[1], 'completed', 4, '2026-09-30 10:00:00' );

	// Authorisation.
	wp_set_current_user( 0 );
	$guest = call_api( '/reports/quizzes' );
	ok( 401 === $guest->get_status() && 'ohmylms_quiz_report_cannot_view' === code_of( $guest ), 'guest list is 401' );
	ok( 401 === call_api( '/reports/quizzes/summary' )->get_status(), 'guest summary is 401' );
	wp_set_current_user( $subscriber );
	ok( 403 === call_api( '/reports/quizzes' )->get_status(), 'subscriber list is 403' );
	ok( 403 === call_api( '/reports/quizzes/summary' )->get_status(), 'subscriber summary is 403' );

	// Administrator: rows and figures.
	wp_set_current_user( $admin );
	$list = call_api( '/reports/quizzes', array( 'search' => $tag ) );
	ok( 200 === $list->get_status(), 'admin list is 200' );
	ok( '3' === $list->get_headers()['X-WP-Total'] && '1' === $list->get_headers()['X-WP-TotalPages'], 'totals headers' );
	ok( 'private, no-store' === $list->get_headers()['Cache-Control'], 'no-store' );
	ok( array( $quiz1, $quiz3, $quiz2 ) === ids_of( $list ), 'default order is latest submission first, quizzes without submissions last' );
	$rows = array_column( $list->get_data(), null, 'id' );
	ok( array( 'id', 'title', 'status', 'course', 'attempts', 'learners', 'in_progress', 'to_grade', 'completed', 'average_score', 'passing_score', 'passed', 'pass_rate', 'last_submitted_at', 'can_edit', 'can_grade' ) === array_keys( $rows[ $quiz1 ] ), 'row shape' );
	$one = $rows[ $quiz1 ];
	ok( 4 === $one['attempts'] && 3 === $one['learners'] && 1 === $one['in_progress'] && 1 === $one['to_grade'] && 2 === $one['completed'], 'attempt figures of quiz 1' );
	ok( 5.0 === (float) $one['average_score'] && 5.0 === (float) $one['passing_score'] && 1 === $one['passed'] && 50.0 === (float) $one['pass_rate'], 'average and pass rate of quiz 1' );
	ok( $course === $one['course']['id'] && "$tag Course" === $one['course']['title'], 'course reference' );
	ok( 'publish' === $one['status'] && "$tag Alpha quiz" === $one['title'], 'status and title' );
	ok( 1 === preg_match( '/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', (string) $one['last_submitted_at'] ), 'last submission is ISO 8601 UTC' );
	ok( true === $one['can_grade'] && true === $one['can_edit'], 'admin may grade and edit' );
	$two = $rows[ $quiz2 ];
	ok( 0 === $two['attempts'] && null === $two['average_score'] && null === $two['passed'] && null === $two['pass_rate'] && null === $two['last_submitted_at'] && null === $two['course'] && 'draft' === $two['status'], 'quiz without attempts' );
	ok( 1 === $rows[ $quiz3 ]['completed'] && null === $rows[ $quiz3 ]['pass_rate'] && 0.0 === (float) $rows[ $quiz3 ]['passing_score'], 'quiz without a pass mark has no pass rate' );
	$json = wp_json_encode( $list->get_data() );
	ok( false === strpos( $json, '@example.invalid' ) && false === strpos( $json, 'Person' ), 'no student names or emails' );

	// Filters.
	ok(
		array( $quiz1 ) === ids_of(
			call_api(
				'/reports/quizzes',
				array(
					'search'   => $tag,
					'activity' => 'to_grade',
				)
			)
		),
		'activity to_grade'
	);
	ok(
		array( $quiz1, $quiz3 ) === ids_of(
			call_api(
				'/reports/quizzes',
				array(
					'search'   => $tag,
					'activity' => 'attempted',
				)
			)
		),
		'activity attempted'
	);
	ok(
		array( $quiz2 ) === ids_of(
			call_api(
				'/reports/quizzes',
				array(
					'search' => $tag,
					'status' => 'draft',
				)
			)
		),
		'status draft'
	);
	ok(
		array( $quiz1, $quiz3 ) === ids_of(
			call_api(
				'/reports/quizzes',
				array(
					'search' => $tag,
					'status' => 'publish',
				)
			)
		),
		'status publish'
	);
	ok( array( $quiz1 ) === ids_of( call_api( '/reports/quizzes', array( 'course' => $course ) ) ), 'course filter' );
	ok( array( $quiz2 ) === ids_of( call_api( '/reports/quizzes', array( 'search' => "$tag Beta" ) ) ), 'title search' );
	ok( array() === call_api( '/reports/quizzes', array( 'search' => "$tag nothing" ) )->get_data(), 'empty result' );

	// Sorting.
	ok(
		array( $quiz1, $quiz2, $quiz3 ) === ids_of(
			call_api(
				'/reports/quizzes',
				array(
					'search'  => $tag,
					'orderby' => 'title',
					'order'   => 'asc',
				)
			)
		),
		'title ascending'
	);
	ok(
		array( $quiz2, $quiz3, $quiz1 ) === ids_of(
			call_api(
				'/reports/quizzes',
				array(
					'search'  => $tag,
					'orderby' => 'attempts',
					'order'   => 'asc',
				)
			)
		),
		'attempts ascending'
	);
	$by_grading = ids_of(
		call_api(
			'/reports/quizzes',
			array(
				'search'  => $tag,
				'orderby' => 'to_grade',
			)
		)
	);
	ok( $by_grading[0] === $quiz1, 'to_grade descending' );

	// Paging.
	$paged = call_api(
		'/reports/quizzes',
		array(
			'search'   => $tag,
			'per_page' => 1,
			'page'     => 2,
			'orderby'  => 'title',
			'order'    => 'asc',
		)
	);
	ok( array( $quiz2 ) === ids_of( $paged ) && '3' === $paged->get_headers()['X-WP-TotalPages'], 'second page' );
	ok( false !== strpos( (string) $paged->get_headers()['Link'], 'rel="next"' ) && false !== strpos( (string) $paged->get_headers()['Link'], 'rel="prev"' ), 'Link header' );
	$past = call_api(
		'/reports/quizzes',
		array(
			'search' => $tag,
			'page'   => 9,
		)
	);
	ok( 400 === $past->get_status() && 'ohmylms_quiz_report_invalid_page' === code_of( $past ), 'page past the end' );

	// Validation.
	foreach ( array(
		'per_page' => 101,
		'orderby'  => 'meta_value',
		'status'   => 'trash',
		'activity' => 'everything',
		'course'   => 0,
		'search'   => str_repeat( 'x', 201 ),
	) as $name => $value ) {
		$bad = call_api( '/reports/quizzes', array( $name => $value ) );
		ok( 400 === $bad->get_status() && 'rest_invalid_param' === code_of( $bad ), "invalid $name is 400" );
	}
	ok( 400 === call_api( '/reports/quizzes/summary', array( 'activity' => 'x' ) )->get_status(), 'summary validates filters' );

	// Summary.
	$summary = call_api( '/reports/quizzes/summary', array( 'search' => $tag ) );
	ok(
		200 === $summary->get_status() && array(
			'quizzes'   => 3,
			'attempts'  => 5,
			'learners'  => 3,
			'to_grade'  => 1,
			'completed' => 3,
		) === $summary->get_data(),
		'summary over the filtered set: ' . wp_json_encode( $summary->get_data() )
	);
	ok(
		1 === call_api(
			'/reports/quizzes/summary',
			array(
				'search'   => $tag,
				'activity' => 'to_grade',
			)
		)->get_data()['quizzes'],
		'summary follows the filters'
	);

	// Scoping by GradingPolicy.
	wp_set_current_user( $author_a );
	ok( array( $quiz1, $quiz2 ) === ids_of( call_api( '/reports/quizzes', array( 'search' => $tag ) ) ), 'quiz author sees own quizzes only' );
	ok( 4 === call_api( '/reports/quizzes/summary', array( 'search' => $tag ) )->get_data()['attempts'], 'author summary counts own quizzes only' );
	wp_set_current_user( $author_b );
	$as_b = call_api( '/reports/quizzes', array( 'search' => $tag ) );
	ok( array( $quiz1 ) === ids_of( $as_b ) && true === $as_b->get_data()[0]['can_grade'] && false === $as_b->get_data()[0]['can_edit'], 'course author sees the course quiz, may grade, not edit' );
	wp_set_current_user( $editor );
	ok( array( $quiz3 ) === ids_of( call_api( '/reports/quizzes', array( 'search' => $tag ) ) ), 'editor sees only quizzes they may grade' );
	wp_set_current_user( $author_c );
	$none = call_api( '/reports/quizzes', array( 'search' => $tag ) );
	ok( 200 === $none->get_status() && array() === $none->get_data() && '0' === $none->get_headers()['X-WP-Total'], 'author without quizzes gets an empty list' );
	ok( 0 === call_api( '/reports/quizzes/summary' )->get_data()['quizzes'], 'author without quizzes gets an empty summary' );

	// Schema.
	$schema = rest_do_request( new WP_REST_Request( 'OPTIONS', '/ohmylms/v1/reports/quizzes' ) )->get_data()['schema'];
	ok( isset( $schema['properties']['pass_rate'], $schema['properties']['to_grade'] ), 'schema' );

	fwrite( STDOUT, "OK quiz-reports-rest-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	wp_set_current_user( 0 );
	foreach ( $user_ids as $user_id ) {
		$wpdb->delete( $wpdb->prefix . 'ohmylms_quiz_attempts', array( 'student_id' => $user_id ) );
	}
	foreach ( array_unique( array_map( 'intval', $post_ids ) ) as $post_id ) {
		$wpdb->delete( $wpdb->prefix . 'ohmylms_quiz_attempts', array( 'quiz_id' => $post_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_content_relationship', array( 'content_id' => $post_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'chapter_id' => $post_id ) );
		wp_delete_post( $post_id, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
}

exit( $exit_code );
