<?php
/**
 * Quizzes REST API integration test.
 *
 * Runs against a WordPress install (the Local site by default) with real database tables. It creates
 * only tagged quizzes, courses, chapters, questions, users, enrolments and attempts, and removes all of
 * them in `finally`. Nothing else on the site is read for assertions or changed.
 *
 * Usage (set OHMYLMS_QUIZZES_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/quizzes-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, Generic.Formatting.MultipleStatementAlignment, WordPress.DB.DirectDatabaseQuery

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_QUIZZES_TEST' ) ) {
	exit( "Set OHMYLMS_QUIZZES_TEST=1 to run the quizzes REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Assessment\Schema;
use OhMyLMS\Quiz\Submission;

global $wpdb;

$checks   = 0;
$post_ids = array();
$user_ids = array();
$attempts = array();
$tag      = 'qztest' . strtolower( wp_generate_password( 6, false, false ) );

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
 * Dispatch a REST request.
 *
 * @param string $method HTTP method.
 * @param string $path   Path below /ohmylms/v1.
 * @param array  $data   Body (JSON) or query (GET).
 * @return WP_REST_Response
 */
function call_api( $method, $path, array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/' . ltrim( $path, '/' ) );
	if ( 'GET' === $method ) {
		$request->set_query_params( $data );
	} elseif ( $data ) {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

/**
 * Create a tagged user.
 *
 * @param string $role Role.
 * @param string $name Display name.
 * @return int
 */
function make_user( $role, $name ) {
	global $user_ids, $tag;
	$id = wp_insert_user(
		array(
			'user_login'   => $tag . '-' . $name,
			'user_pass'    => wp_generate_password( 24 ),
			'user_email'   => $tag . '-' . $name . '@example.invalid',
			'display_name' => 'Zed ' . $tag . ' ' . $name,
			'role'         => $role,
		)
	);
	ok( $id && ! is_wp_error( $id ), 'user created' );
	$user_ids[] = $id;
	return $id;
}

/**
 * Create a tagged post.
 *
 * @param array $args wp_insert_post arguments.
 * @return int
 */
function make_post( array $args ) {
	global $post_ids;
	$id = wp_insert_post( $args );
	ok( $id && ! is_wp_error( $id ), 'post created' );
	$post_ids[] = $id;
	return $id;
}

$exit_code = 0;
$original_user = get_current_user_id();
try {
	$admin     = make_user( 'administrator', 'admin' );
	$author_a  = make_user( 'author', 'authora' );
	$author_b  = make_user( 'author', 'authorb' );
	$author_c  = make_user( 'author', 'authorc' );
	$student   = make_user( 'subscriber', 'student' );
	$stranger  = make_user( 'subscriber', 'stranger' );

	// Anonymous and unprivileged callers.
	wp_set_current_user( 0 );
	ok_error( call_api( 'GET', 'quizzes' ), 401, 'ohmylms_quiz_cannot_view', 'anonymous list' );
	ok_error( call_api( 'POST', 'quizzes', array( 'title' => 'x' ) ), 401, 'ohmylms_quiz_cannot_create', 'anonymous create' );
	wp_set_current_user( $student );
	ok_error( call_api( 'GET', 'quizzes' ), 403, 'ohmylms_quiz_cannot_view', 'student list' );
	ok_error( call_api( 'POST', 'quizzes', array( 'title' => 'x' ) ), 403, 'ohmylms_quiz_cannot_create', 'student create' );

	// Create as author A.
	wp_set_current_user( $author_a );
	ok_error( call_api( 'POST', 'quizzes', array() ), 400, 'rest_missing_callback_param', 'title required' );
	ok_error( call_api( 'POST', 'quizzes', array( 'title' => '' ) ), 400, 'rest_invalid_param', 'empty title' );
	ok_error( call_api( 'POST', 'quizzes', array( 'title' => str_repeat( 'a', 201 ) ) ), 400, 'rest_invalid_param', 'long title' );
	$bad_settings = array(
		array( 'time_limit_minutes' => -1 ),
		array( 'time_limit_minutes' => 10081 ),
		array( 'passing_score' => 100.5 ),
		array( 'passing_score' => -1 ),
		array( 'attempts_allowed' => -1 ),
		array( 'layout' => 'nonsense' ),
		array( 'feedback_mode' => 'sometimes' ),
		array( 'questions_per_page' => 0 ),
		array( 'randomize_questions' => 'maybe' ),
		array( 'available_from' => 'not a date' ),
		array( 'surprise' => 1 ),
	);
	foreach ( $bad_settings as $bad ) {
		ok_error(
			call_api(
				'POST',
				'quizzes',
				array(
					'title'    => "$tag bad",
					'settings' => $bad,
				)
			),
			400,
			'rest_invalid_param',
			'invalid settings ' . wp_json_encode( $bad )
		);
	}
	ok_error(
		call_api(
			'POST',
			'quizzes',
			array(
				'title'    => "$tag dates",
				'settings' => array(
					'available_from'  => '2026-12-02T10:00:00Z',
					'available_until' => '2026-12-01T10:00:00Z',
				),
			)
		),
		422,
		'ohmylms_quiz_invalid_availability',
		'availability dates must be ordered'
	);
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'ohmylms-quiz' AND post_title LIKE %s", $wpdb->esc_like( $tag ) . '%' ) ), 'rejected creates wrote nothing' );

	$created = call_api(
		'POST',
		'quizzes',
		array(
			'title'    => "$tag Quiz A",
			'settings' => array(
				'time_limit_minutes' => 45,
				'passing_score'      => 60,
				'attempts_allowed'   => 3,
				'feedback_mode'      => 'after_close',
				'available_from'     => '2026-12-01T09:00:00Z',
				'available_until'    => '2026-12-31T09:00:00Z',
			),
		)
	);
	ok( 201 === $created->get_status(), 'quiz created: ' . wp_json_encode( $created->get_data() ) );
	$quiz = $created->get_data();
	$post_ids[] = $quiz['id'];
	$quiz_id = $quiz['id'];
	ok( 'draft' === $quiz['status'] && "$tag Quiz A" === $quiz['title'], 'new quiz is a draft' );
	ok( false !== strpos( (string) $created->get_headers()['Location'], '/quizzes/' . $quiz_id ), 'Location header' );
	ok( 45 === $quiz['settings']['time_limit_minutes'] && 3 === $quiz['settings']['attempts_allowed'] && 'after_close' === $quiz['settings']['feedback_mode'], 'settings echoed' );
	ok( '2026-12-01T09:00:00Z' === $quiz['settings']['available_from'], 'availability stored as UTC' );
	ok( 'one_question_per_page' === $quiz['settings']['layout'] && true === $quiz['settings']['show_correct_answers'], 'defaults applied to omitted settings' );
	$blob = get_post_meta( $quiz_id, '_quiz_settings', true );
	ok( 45 === $blob['time_limit']['value'] && 'minutes' === $blob['time_limit']['type'] && 3 === $blob['allow_attempts'] && 60 === (int) $blob['passing_grade']['value'], 'stored keys unchanged in name' );
	ok( 45.0 === (float) ohmylms_get_quiz( $quiz_id )->get_timer() && 3 === (int) ohmylms_get_quiz( $quiz_id )->get_take_attempts() && 60 === (int) ohmylms_get_quiz( $quiz_id )->get_passing_grade(), 'learner-side readers see the settings' );
	ok( true === $quiz['can_edit'] && true === $quiz['can_grade'] && 0 === $quiz['attempt_count'], 'capability flags' );

	// Keys written by other screens are kept.
	$blob['player_template'] = 'playful';
	update_post_meta( $quiz_id, '_quiz_settings', $blob );
	$slug = get_post_field( 'post_name', $quiz_id );

	// Read, update, whole-request validation.
	$read = call_api( 'GET', "quizzes/$quiz_id", array( 'context' => 'edit' ) );
	ok( 200 === $read->get_status() && isset( $read->get_data()['settings'], $read->get_data()['total_marks'] ), 'edit context adds settings' );
	$view = call_api( 'GET', "quizzes/$quiz_id" )->get_data();
	ok( ! isset( $view['settings'] ) && ! isset( $view['description'] ), 'view context omits edit fields' );
	$before = get_post_field( 'post_title', $quiz_id );
	ok_error(
		call_api(
			'PATCH',
			"quizzes/$quiz_id",
			array(
				'title'    => "$tag renamed",
				'settings' => array( 'available_until' => '2026-11-01T09:00:00Z' ),
			)
		),
		422,
		'ohmylms_quiz_invalid_availability',
		'stored opening time is checked against a lone closing time'
	);
	ok( get_post_field( 'post_title', $quiz_id ) === $before, 'rejected update changed nothing' );
	$updated = call_api(
		'PATCH',
		"quizzes/$quiz_id",
		array(
			'title'    => "$tag Quiz A renamed",
			'status'   => 'publish',
			'settings' => array(
				'time_limit_minutes'   => 0,
				'randomize_questions'  => true,
				'show_correct_answers' => false,
			),
		)
	);
	ok( 200 === $updated->get_status(), 'update: ' . wp_json_encode( $updated->get_data() ) );
	$data = $updated->get_data();
	ok( 0 === $data['settings']['time_limit_minutes'] && true === $data['settings']['randomize_questions'] && false === $data['settings']['show_correct_answers'] && 3 === $data['settings']['attempts_allowed'] && 60.0 === (float) $data['settings']['passing_score'], 'partial settings update keeps other settings' );
	ok( 'publish' === $data['status'] && '' !== get_post_field( 'post_name', $quiz_id ), 'publishing assigns a slug' );
	$slug = get_post_field( 'post_name', $quiz_id );
	$blob = get_post_meta( $quiz_id, '_quiz_settings', true );
	ok( 'playful' === $blob['player_template'], 'unknown stored keys are preserved' );
	ok( ! empty( $blob['passing_grade']['enabled'] ), 'passing grade still enabled' );

	// Stale save is a conflict and changes nothing.
	ok_error(
		call_api(
			'PATCH',
			"quizzes/$quiz_id",
			array(
				'base_modified' => '2000-01-01T00:00:00Z',
				'title'         => 'stale again',
			)
		),
		409,
		'ohmylms_quiz_conflict',
		'stale base_modified'
	);
	ok( 'stale again' !== get_post_field( 'post_title', $quiz_id ), 'conflict changed nothing' );
	ok( 200 === call_api( 'PATCH', "quizzes/$quiz_id", array( 'title' => "$tag Quiz A" ) )->get_status(), 'title restored' );
	ok( get_post_field( 'post_name', $quiz_id ) === $slug, 'a title change does not rewrite the slug' );
	ok_error( call_api( 'PATCH', "quizzes/$quiz_id", array( 'title' => '   ' ) ), 422, 'ohmylms_quiz_invalid_title', 'blank title' );

	// Object-level permissions: author B is unrelated.
	wp_set_current_user( $author_b );
	ok_error( call_api( 'GET', "quizzes/$quiz_id" ), 403, 'ohmylms_quiz_cannot_view', 'unrelated author read' );
	ok_error( call_api( 'PATCH', "quizzes/$quiz_id", array( 'title' => 'hijack' ) ), 403, 'ohmylms_quiz_cannot_edit', 'unrelated author update' );
	ok_error( call_api( 'DELETE', "quizzes/$quiz_id" ), 403, 'ohmylms_quiz_cannot_delete', 'unrelated author delete' );
	ok_error( call_api( 'GET', "quizzes/$quiz_id/attempts" ), 403, 'ohmylms_quiz_cannot_grade', 'unrelated author attempts' );
	ok( ! in_array( $quiz_id, wp_list_pluck( call_api( 'GET', 'quizzes', array( 'search' => $tag ) )->get_data(), 'id' ), true ), 'unrelated author does not list the quiz' );
	ok_error( call_api( 'GET', 'quizzes/999999999' ), 404, 'ohmylms_quiz_not_found', 'unknown quiz' );
	ok_error(
		call_api(
			'GET',
			'quizzes/' . make_post(
				array(
					'post_type'   => 'page',
					'post_title'  => "$tag page",
					'post_status' => 'publish',
				)
			)
		),
		404,
		'ohmylms_quiz_not_found',
		'other post type or missing'
	);

	// Course with a chapter that contains the quiz; author B writes the course.
	$course  = make_post(
		array(
			'post_type'   => 'ohmylms-course',
			'post_title'  => "$tag course",
			'post_status' => 'publish',
			'post_author' => $author_b,
		)
	);
	$chapter = make_post(
		array(
			'post_type'   => 'ohmylms-chapter',
			'post_title'  => "$tag chapter",
			'post_status' => 'publish',
		)
	);
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
			'content_id'   => $quiz_id,
			'content_type' => 'quiz',
			'order_number' => 0,
		)
	);

	wp_set_current_user( $author_b );
	$as_b = call_api( 'GET', "quizzes/$quiz_id" );
	ok( 200 === $as_b->get_status() && false === $as_b->get_data()['can_edit'] && true === $as_b->get_data()['can_grade'], 'course author may read and grade, not edit' );
	ok( in_array( $quiz_id, wp_list_pluck( call_api( 'GET', 'quizzes', array( 'search' => $tag ) )->get_data(), 'id' ), true ), 'course author lists the quiz' );
	ok_error( call_api( 'PATCH', "quizzes/$quiz_id", array( 'title' => 'hijack' ) ), 403, 'ohmylms_quiz_cannot_edit', 'course author cannot edit settings' );
	wp_set_current_user( $author_c );
	ok_error( call_api( 'GET', "quizzes/$quiz_id/attempts" ), 403, 'ohmylms_quiz_cannot_grade', 'third author cannot see attempts' );

	// List: paging, headers, filters, validation.
	wp_set_current_user( $admin );
	$second = call_api( 'POST', 'quizzes', array( 'title' => "$tag Quiz B" ) );
	ok( 201 === $second->get_status(), 'second quiz' );
	$other_id = (int) $second->get_data()['id'];
	$post_ids[] = $other_id;
	$list = call_api(
		'GET',
		'quizzes',
		array(
			'search'   => $tag,
			'per_page' => 1,
			'orderby'  => 'id',
			'order'    => 'asc',
		)
	);
	ok( 200 === $list->get_status() && 1 === count( $list->get_data() ) && '2' === $list->get_headers()['X-WP-Total'] && '2' === $list->get_headers()['X-WP-TotalPages'], 'list is paged with totals' );
	ok( isset( $list->get_headers()['Link'] ) && false !== strpos( $list->get_headers()['Link'], 'rel="next"' ), 'Link header' );
	ok_error(
		call_api(
			'GET',
			'quizzes',
			array(
				'search' => $tag,
				'page'   => 9,
			)
		),
		400,
		'ohmylms_quiz_invalid_page',
		'page past the end'
	);
	ok_error( call_api( 'GET', 'quizzes', array( 'per_page' => 101 ) ), 400, 'rest_invalid_param', 'per_page maximum' );
	ok_error( call_api( 'GET', 'quizzes', array( 'orderby' => 'meta_value' ) ), 400, 'rest_invalid_param', 'orderby enum' );
	ok_error( call_api( 'GET', 'quizzes', array( 'status' => 'bogus' ) ), 400, 'rest_invalid_param', 'status enum' );
	ok(
		1 === count(
			call_api(
				'GET',
				'quizzes',
				array(
					'search' => $tag,
					'status' => 'draft',
				)
			)->get_data()
		),
		'status filter'
	);
	$in_course = call_api( 'GET', 'quizzes', array( 'course' => $course ) );
	ok( array( $quiz_id ) === wp_list_pluck( $in_course->get_data(), 'id' ) && $course === $in_course->get_data()[0]['course']['id'], 'course filter and course reference' );
	ok( array() === call_api( 'GET', 'quizzes', array( 'search' => $tag . ' nothing' ) )->get_data(), 'empty result' );
	$picker = call_api( 'GET', 'quizzes/courses', array( 'search' => $tag ) );
	ok( 200 === $picker->get_status() && array( 'id', 'title', 'status' ) === array_keys( $picker->get_data()[0] ), 'course picker shape' );

	// Schema.
	$schema = rest_do_request( new WP_REST_Request( 'OPTIONS', '/ohmylms/v1/quizzes' ) )->get_data()['schema'];
	foreach ( array( 'id', 'title', 'status', 'course', 'question_count', 'attempt_count', 'pending_review_count', 'settings', 'created_at', 'updated_at', 'can_grade' ) as $property ) {
		ok( isset( $schema['properties'][ $property ] ), "schema property $property" );
	}

	// Attempts need the versioned engine.
	ok( Schema::ready(), 'assessment schema installed on this site' );
	wp_set_current_user( $author_a );
	$cleared = call_api(
		'PATCH',
		"quizzes/$quiz_id",
		array(
			'settings' => array(
				'available_from'      => null,
				'available_until'     => null,
				'feedback_mode'       => 'immediate',
				'randomize_questions' => false,
			),
		)
	);
	ok( 200 === $cleared->get_status() && null === $cleared->get_data()['settings']['available_from'], 'availability can be cleared' );
	$essay = call_api(
		'POST',
		'question',
		array(
			'quiz_id'   => $quiz_id,
			'name'      => 'Explain why',
			'settings'  => array(
				'type'  => 'short-text',
				'score' => array(
					'enabled' => true,
					'value'   => 4,
				),
			),
			'questions' => array(
				array(
					'answer'     => 'model',
					'is_correct' => 1,
				),
			),
		)
	);
	ok( 201 === $essay->get_status(), 'essay question: ' . wp_json_encode( $essay->get_data() ) );
	$essay_id = $essay->get_data()['id'];
	$post_ids[] = $essay_id;
	$choice = call_api(
		'POST',
		'question',
		array(
			'quiz_id'   => $quiz_id,
			'name'      => 'Pick one',
			'settings'  => array(
				'type'  => 'single-choice',
				'score' => array(
					'enabled' => true,
					'value'   => 2,
				),
			),
			'questions' => array(
				array(
					'answer'       => 'Right',
					'is_correct'   => 1,
					'order_number' => 1,
				),
				array(
					'answer'       => 'Wrong',
					'is_correct'   => 0,
					'order_number' => 2,
				),
			),
		)
	);
	ok( 201 === $choice->get_status(), 'choice question' );
	$choice_id = $choice->get_data()['id'];
	$post_ids[] = $choice_id;
	ok( 2 === call_api( 'GET', "quizzes/$quiz_id" )->get_data()['question_count'], 'question count' );

	$wpdb->insert(
		$wpdb->prefix . 'ohmylms_user_enrollment',
		array(
			'user_id'    => $student,
			'course_id'  => $course,
			'status'     => 'enrolled',
			'progress'   => 'running',
			'start_date' => current_time( 'mysql' ),
		)
	);
	$cache = new ReflectionProperty( \OhMyLMS\DataStores\StudentStore::class, 'enrollment_cache' );
	$cache->setAccessible( true );
	$cache->setValue( null, array() );

	wp_set_current_user( $student );
	$attempt = Submission::start( $quiz_id, $student );
	ok( is_int( $attempt ), 'attempt started: ' . ( is_wp_error( $attempt ) ? $attempt->get_error_message() : '' ) );
	$attempts[] = $attempt;
	ok_error(
		call_api(
			'POST',
			"quizzes/$quiz_id/attempts/$attempt/grades",
			array(
				'grades' => array(
					array(
						'question_id' => $essay_id,
						'marks'       => 1,
					),
				),
			)
		),
		403,
		'ohmylms_quiz_cannot_grade',
		'student cannot grade'
	);
	ok_error( call_api( 'GET', "quizzes/$quiz_id/attempts" ), 403, 'ohmylms_quiz_cannot_grade', 'student cannot list attempts' );
	$submitted = Submission::submit( $quiz_id, $attempt, $student, array( $essay_id => array( 'Because it is so.' ) ) );
	ok( ! is_wp_error( $submitted ), 'attempt submitted' );

	wp_set_current_user( 0 );
	ok_error( call_api( 'GET', "quizzes/$quiz_id/attempts" ), 401, 'ohmylms_quiz_cannot_grade', 'anonymous attempts' );

	// Attempt list and detail as the quiz author.
	wp_set_current_user( $author_a );
	$rows = call_api( 'GET', "quizzes/$quiz_id/attempts" );
	ok( 200 === $rows->get_status() && 1 === count( $rows->get_data() ) && '1' === $rows->get_headers()['X-WP-Total'], 'attempt list' );
	$row = $rows->get_data()[0];
	ok( $student === $row['student']['id'] && false !== strpos( $row['student']['email'], $tag ) && 'in-review' === $row['status'] && true === $row['needs_grading'], 'row shape' );
	ok( array( 'id', 'name', 'email' ) === array_keys( $row['student'] ), 'student object is limited to id, name and email' );
	ok( 6.0 === (float) $row['max_marks'] && 0.0 === (float) $row['score'], 'maximum marks and score' );
	ok(
		1 === count(
			call_api(
				'GET',
				"quizzes/$quiz_id/attempts",
				array(
					'status' => 'in-review',
					'search' => 'zed ' . $tag,
				)
			)->get_data()
		),
		'status and name search'
	);
	ok( array() === call_api( 'GET', "quizzes/$quiz_id/attempts", array( 'status' => 'completed' ) )->get_data(), 'status filter excludes' );
	ok( array() === call_api( 'GET', "quizzes/$quiz_id/attempts", array( 'search' => "'; DROP TABLE x; --" ) )->get_data(), 'search is parameterised' );
	ok_error( call_api( 'GET', "quizzes/$quiz_id/attempts", array( 'orderby' => 'id; DROP' ) ), 400, 'rest_invalid_param', 'attempt orderby enum' );
	ok_error( call_api( 'GET', "quizzes/$quiz_id/attempts", array( 'page' => 5 ) ), 400, 'ohmylms_attempt_invalid_page', 'attempt page past the end' );
	ok( 1 === call_api( 'GET', "quizzes/$quiz_id" )->get_data()['pending_review_count'], 'pending review count' );

	$detail = call_api( 'GET', "quizzes/$quiz_id/attempts/$attempt" );
	ok( 200 === $detail->get_status() && true === $detail->get_data()['versioned'] && 2 === count( $detail->get_data()['questions'] ), 'attempt detail' );
	$by_id = array_column( $detail->get_data()['questions'], null, 'question_id' );
	ok( 4.0 === (float) $by_id[ $essay_id ]['max_marks'] && 'in-review' === $by_id[ $essay_id ]['status'] && array( 'Because it is so.' ) === $by_id[ $essay_id ]['answer'], 'essay awaiting review with the answer text' );
	ok( ! array_key_exists( 'questions', $by_id[ $essay_id ] ) && ! array_key_exists( 'is_correct', $by_id[ $essay_id ] ), 'no answer key in the detail' );
	ok_error( call_api( 'GET', "quizzes/$other_id/attempts/$attempt" ), 403, 'ohmylms_quiz_cannot_grade', 'a quiz the user may not grade' );
	wp_set_current_user( $admin );
	ok_error( call_api( 'GET', "quizzes/$other_id/attempts/$attempt" ), 404, 'ohmylms_attempt_not_found', 'attempt does not belong to the quiz' );

	// Grading.
	wp_set_current_user( $author_a );
	$grade = static function ( $quiz, $attempt_id, $grades, $extra = array() ) {
		return call_api( 'POST', "quizzes/$quiz/attempts/$attempt_id/grades", array_merge( array( 'grades' => $grades ), $extra ) );
	};
	ok_error( $grade( $quiz_id, $attempt, array() ), 400, 'rest_invalid_param', 'empty grades' );
	ok_error(
		$grade(
			$quiz_id,
			$attempt,
			array(
				array(
					'question_id' => $essay_id,
					'marks'       => 4.5,
				),
			)
		),
		422,
		'ohmylms_grade_exceeds_max',
		'above the maximum'
	);
	ok_error(
		$grade(
			$quiz_id,
			$attempt,
			array(
				array(
					'question_id' => $essay_id,
					'marks'       => -1,
				),
			)
		),
		400,
		'rest_invalid_param',
		'negative marks'
	);
	ok_error(
		$grade(
			$quiz_id,
			$attempt,
			array(
				array(
					'question_id' => 987654,
					'marks'       => 1,
				),
			)
		),
		422,
		'ohmylms_grade_unknown_question',
		'unknown question'
	);
	ok_error(
		$grade(
			$quiz_id,
			$attempt,
			array(
				array(
					'question_id' => $essay_id,
					'marks'       => 1,
				),
				array(
					'question_id' => $essay_id,
					'marks'       => 2,
				),
			)
		),
		422,
		'ohmylms_grade_duplicate_question',
		'duplicate question'
	);
	ok_error(
		$grade(
			$quiz_id,
			$attempt,
			array(
				array(
					'question_id' => $essay_id,
					'marks'       => 1,
					'total'       => 100,
				),
			)
		),
		400,
		'rest_invalid_param',
		'unexpected field in a grade'
	);
	ok_error(
		$grade(
			$quiz_id,
			$attempt,
			array(
				array(
					'question_id' => $essay_id,
					'marks'       => 1,
				),
			),
			array( 'total' => 6 )
		),
		400,
		'ohmylms_grade_unexpected_field',
		'client total refused'
	);
	ok_error(
		$grade(
			$quiz_id,
			$attempt,
			array(
				array(
					'question_id' => $essay_id,
					'marks'       => 'abc',
				),
			)
		),
		400,
		'rest_invalid_param',
		'non numeric marks'
	);
	ok( 0.0 === (float) $wpdb->get_var( $wpdb->prepare( "SELECT total FROM {$wpdb->prefix}ohmylms_quiz_attempts WHERE id = %d", $attempt ) ), 'rejected grades changed nothing' );

	$graded = $grade(
		$quiz_id,
		$attempt,
		array(
			array(
				'question_id' => $essay_id,
				'marks'       => 3,
			),
		),
		array( 'reason' => 'Good reasoning' )
	);
	ok( 200 === $graded->get_status(), 'grade: ' . wp_json_encode( $graded->get_data() ) );
	$result = $graded->get_data();
	ok( 3.0 === (float) $result['score'] && 'completed' === $result['status'] && 1 === $result['changed'], 'total recalculated on the server, attempt completed' );
	$essay_row = array_column( $result['questions'], null, 'question_id' )[ $essay_id ];
	ok( 3.0 === (float) $essay_row['awarded'] && 'graded' === $essay_row['status'], 'question graded' );
	$last = end( $essay_row['history'] );
	ok( $author_a === $last['reviewer_id'] && 'manual' === $last['grader'] && 'Good reasoning' === $last['reason'] && '' !== $last['created_at'], 'audit trail has reviewer, time and reason' );
	ok( 3.0 === (float) $wpdb->get_var( $wpdb->prepare( "SELECT total FROM {$wpdb->prefix}ohmylms_quiz_attempts WHERE id = %d", $attempt ) ), 'stored total' );

	$again = $grade(
		$quiz_id,
		$attempt,
		array(
			array(
				'question_id' => $essay_id,
				'marks'       => 3,
			),
		),
		array( 'reason' => 'Good reasoning' )
	);
	ok( 200 === $again->get_status() && 0 === $again->get_data()['changed'] && 3.0 === (float) $again->get_data()['score'], 'same grade twice is idempotent' );
	$events = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE source_type = %s AND source_id = %d AND grader = %s', Schema::table( 'grade_events' ), 'quiz', $attempt, 'manual' ) );
	ok( 1 === $events, 'no extra grade events written' );

	$regrade = $grade(
		$quiz_id,
		$attempt,
		array(
			array(
				'question_id' => $essay_id,
				'marks'       => 4,
			),
		)
	);
	ok( 200 === $regrade->get_status() && 4.0 === (float) $regrade->get_data()['score'] && 1 === $regrade->get_data()['changed'], 'regrade moves the total and adds history' );

	wp_set_current_user( $author_b );
	ok(
		200 === $grade(
			$quiz_id,
			$attempt,
			array(
				array(
					'question_id' => $essay_id,
					'marks'       => 4,
				),
			)
		)->get_status(),
		'course author may grade'
	);
	wp_set_current_user( $author_c );
	ok_error(
		$grade(
			$quiz_id,
			$attempt,
			array(
				array(
					'question_id' => $essay_id,
					'marks'       => 4,
				),
			)
		),
		403,
		'ohmylms_quiz_cannot_grade',
		'third author cannot grade'
	);
	wp_set_current_user( $stranger );
	ok_error(
		$grade(
			$quiz_id,
			$attempt,
			array(
				array(
					'question_id' => $essay_id,
					'marks'       => 4,
				),
			)
		),
		403,
		'ohmylms_quiz_cannot_grade',
		'unrelated subscriber cannot grade'
	);

	// An attempt still in progress cannot be graded.
	wp_set_current_user( $student );
	$running = Submission::start( $quiz_id, $student );
	ok( is_int( $running ), 'second attempt started' );
	$attempts[] = $running;
	wp_set_current_user( $admin );
	ok_error(
		$grade(
			$quiz_id,
			$running,
			array(
				array(
					'question_id' => $essay_id,
					'marks'       => 1,
				),
			)
		),
		409,
		'ohmylms_attempt_not_submitted',
		'in-progress attempt'
	);
	$paged = call_api(
		'GET',
		"quizzes/$quiz_id/attempts",
		array(
			'per_page' => 1,
			'page'     => 2,
		)
	);
	ok( 200 === $paged->get_status() && 1 === count( $paged->get_data() ) && '2' === $paged->get_headers()['X-WP-TotalPages'], 'second attempt on page two' );

	// Delete: attempts protect the quiz from permanent deletion.
	wp_set_current_user( $author_b );
	ok_error( call_api( 'DELETE', "quizzes/$quiz_id" ), 403, 'ohmylms_quiz_cannot_delete', 'course author cannot delete' );
	wp_set_current_user( $author_a );
	ok_error( call_api( 'DELETE', "quizzes/$quiz_id", array( 'force' => true ) ), 409, 'ohmylms_quiz_has_attempts', 'force delete with attempts' );
	$trashed = call_api( 'DELETE', "quizzes/$quiz_id" );
	ok( 200 === $trashed->get_status() && true === $trashed->get_data()['trashed'] && false === $trashed->get_data()['deleted'] && 'trash' === get_post_status( $quiz_id ), 'trash by default' );
	ok(
		in_array(
			$quiz_id,
			wp_list_pluck(
				call_api(
					'GET',
					'quizzes',
					array(
						'status' => 'trash',
						'search' => $tag,
					)
				)->get_data(),
				'id'
			),
			true
		),
		'trash view lists it'
	);
	$restored = call_api( 'PATCH', "quizzes/$quiz_id", array( 'status' => 'draft' ) );
	ok( 200 === $restored->get_status() && 'draft' === get_post_status( $quiz_id ), 'restore from the trash' );
	wp_set_current_user( $admin );
	$gone = call_api( 'DELETE', "quizzes/$other_id", array( 'force' => true ) );
	ok( 200 === $gone->get_status() && true === $gone->get_data()['deleted'] && null === get_post( $other_id ), 'permanent delete without attempts' );
	ok_error( call_api( 'GET', "quizzes/$other_id" ), 404, 'ohmylms_quiz_not_found', 'deleted quiz' );

	// Question-authoring routes still work and are object-level; replaced routes are gone.
	wp_set_current_user( $author_a );
	ok( 200 === call_api( 'GET', "quiz/$quiz_id" )->get_status(), 'authoring read' );
	wp_set_current_user( $author_c );
	ok( in_array( call_api( 'GET', "quiz/$quiz_id" )->get_status(), array( 401, 403 ), true ), 'authoring read by an unrelated author is refused' );
	foreach ( array( 'GET quiz', 'GET quiz/contents', 'DELETE quiz/trash-bulk', 'GET quiz/' . $quiz_id . '/report', 'DELETE quiz/' . $quiz_id ) as $removed ) {
		list( $method, $path ) = explode( ' ', $removed );
		ok( in_array( call_api( $method, $path )->get_status(), array( 404, 405 ), true ), "removed route $removed" );
	}

	fwrite( STDOUT, "OK quizzes-rest-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	// Remove only what this run created.
	wp_set_current_user( 0 );
	$post_ids = array_map( 'intval', $post_ids );
	$post_ids = array_merge( $post_ids, array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('ohmylms-quiz','ohmylms-course','ohmylms-chapter','ohmylms-question') AND post_title LIKE %s", $wpdb->esc_like( $tag ) . '%' ) ) ) );
	if ( Schema::ready() ) {
		foreach ( $user_ids as $user_id ) {
			foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE student_id = %d', Schema::table( 'grade_events' ), $user_id ) ) as $event ) {
				$wpdb->delete( Schema::table( 'evidence_outbox' ), array( 'grade_event_id' => (int) $event ) );
			}
			$wpdb->delete( Schema::table( 'grade_events' ), array( 'student_id' => $user_id ) );
			$wpdb->delete( Schema::table( 'skill_evidence' ), array( 'student_id' => $user_id ) );
			$wpdb->delete( Schema::table( 'student_skill_state' ), array( 'student_id' => $user_id ) );
			$attempts = array_merge( $attempts, array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE student_id = %d', $wpdb->prefix . 'ohmylms_quiz_attempts', $user_id ) ) ) );
		}
		foreach ( array_unique( $attempts ) as $attempt_id ) {
			foreach ( array( 'attempt_context', 'attempt_items', 'response_drafts', 'response_events' ) as $table ) {
				$wpdb->delete( Schema::table( $table ), array( 'attempt_id' => $attempt_id ) );
			}
		}
		foreach ( array_unique( $post_ids ) as $post_id ) {
			foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE question_id = %d', Schema::table( 'qb_question_versions' ), $post_id ) ) as $version ) {
				$wpdb->delete( Schema::table( 'qb_version_skills' ), array( 'version_id' => (int) $version ) );
			}
			$wpdb->delete( Schema::table( 'qb_question_versions' ), array( 'question_id' => $post_id ) );
			$wpdb->delete( Schema::table( 'qb_questions' ), array( 'question_id' => $post_id ) );
			foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE quiz_id = %d', Schema::table( 'quiz_revisions' ), $post_id ) ) as $revision ) {
				$wpdb->delete( Schema::table( 'quiz_revision_slots' ), array( 'revision_id' => (int) $revision ) );
			}
			$wpdb->delete( Schema::table( 'quiz_revisions' ), array( 'quiz_id' => $post_id ) );
		}
	}
	foreach ( $user_ids as $user_id ) {
		$wpdb->delete( $wpdb->prefix . 'ohmylms_quiz_attempts_answers', array( 'student_id' => $user_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_quiz_attempts', array( 'student_id' => $user_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_user_enrollment', array( 'user_id' => $user_id ) );
	}
	foreach ( array_unique( $post_ids ) as $post_id ) {
		$wpdb->delete( $wpdb->prefix . 'ohmylms_content_relationship', array( 'content_id' => $post_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'chapter_id' => $post_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'course_id' => $post_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_quiz_questions_relationship', array( 'quiz_id' => $post_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_quiz_questions_relationship', array( 'question_id' => $post_id ) );
		wp_delete_post( $post_id, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
}

exit( $exit_code );
