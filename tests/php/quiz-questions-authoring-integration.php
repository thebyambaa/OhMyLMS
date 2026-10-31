<?php
/**
 * Quiz question authoring routes: per-question marks, sections and random pools under
 * `ohmylms/v1/quizzes/{id}/questions` (marks, sections, pools), their stale-revision checks, permissions,
 * validation, storage, what publishing freezes, and the cleanup when a question is removed.
 *
 * Runs against the Local site (`800.local`) and removes everything it creates.
 *
 * Usage (set OHMYLMS_QUIZZES_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/quiz-questions-authoring-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, Generic.Formatting.MultipleStatementAlignment, WordPress.DB.DirectDatabaseQuery, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI test script writing to STDOUT/STDERR.

use OhMyLMS\Assessment\AssessmentSettings;
use OhMyLMS\Assessment\Pools;
use OhMyLMS\Assessment\RevisionPublisher;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\QuestionBank\Banks;
use OhMyLMS\Skills\Taxonomy;

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_QUIZZES_TEST' ) ) {
	exit( "Set OHMYLMS_QUIZZES_TEST=1 to run the quiz question authoring tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

global $wpdb;

$checks       = 0;
$question_ids = array();
$post_ids     = array();
$user_ids     = array();
$term_ids     = array();
$bank_ids     = array();
$tag          = 'qauth' . strtolower( wp_generate_password( 6, false, false ) );

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
 * @param array  $data   JSON body (query for GET).
 * @return WP_REST_Response
 */
function call_api( $method, $path, $data = array() ) {
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
 * Assert an error response.
 *
 * @param WP_REST_Response $response Response.
 * @param int              $status   Expected status.
 * @param string           $code     Expected code.
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
 * @param string $suffix Login suffix.
 * @param string $role   Role.
 * @return int
 */
function make_test_user( $suffix, $role ) {
	global $tag, $user_ids;
	$id = wp_insert_user(
		array(
			'user_login' => $tag . '_' . $suffix,
			'user_pass'  => wp_generate_password( 24 ),
			'user_email' => $tag . '_' . $suffix . '@example.invalid',
			'role'       => $role,
		)
	);
	ok( ! is_wp_error( $id ), "temporary $role" );
	$user_ids[] = $id;
	return $id;
}

/**
 * Create a single-choice question worth one point.
 *
 * @param string $name   Name suffix.
 * @param array  $skills Skill map.
 * @return array Editor view.
 */
function make_choice( $name, array $skills = array() ) {
	global $question_ids, $tag;
	$body = array(
		'name'     => "$tag $name",
		'settings' => array(
			'type'  => 'single-choice',
			'score' => array(
				'enabled' => true,
				'value'   => 1,
			),
		),
		'options'  => array(
			array(
				'answer'     => 'Yes',
				'is_correct' => true,
			),
			array(
				'answer'     => 'No',
				'is_correct' => false,
			),
		),
	);
	if ( $skills ) {
		$body['skills'] = $skills;
	}
	$response = call_api( 'POST', '/questions', $body );
	ok( 201 === $response->get_status(), "question $name: " . wp_json_encode( $response->get_data() ) );
	$question_ids[] = (int) $response->get_data()['id'];
	return $response->get_data();
}

try {
	ok( Schema::ready(), 'question bank schema is installed' );
	$routes = rest_get_server()->get_routes( 'ohmylms/v1' );
	foreach ( array( '/quizzes/(?P<id>[\d]+)/questions/(?P<question_id>[\d]+)/marks', '/quizzes/(?P<id>[\d]+)/questions/sections', '/quizzes/(?P<id>[\d]+)/questions/pools' ) as $route ) {
		ok( isset( $routes[ '/ohmylms/v1' . $route ] ), "route $route" );
	}

	$author   = make_test_user( 'author', 'author' );
	$other    = make_test_user( 'other', 'author' );
	$student  = make_test_user( 'student', 'subscriber' );
	$quiz     = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_QUIZ_CPT,
			'post_title'  => "$tag quiz",
			'post_status' => 'draft',
			'post_author' => $author,
		)
	);
	$post_ids[] = $quiz;
	$skill      = wp_insert_term( "$tag skill", Taxonomy::NAME );
	ok( is_array( $skill ), 'temporary skill' );
	$term_ids[] = (int) $skill['term_id'];
	$skill_map  = array(
		'p1' => array(
			'primary'    => (int) $skill['term_id'],
			'supporting' => array(),
		),
	);

	wp_set_current_user( $author );
	$first  = make_choice( 'first' );
	$second = make_choice( 'second' );
	$third  = make_choice( 'third' );
	$list   = call_api( 'GET', "/quizzes/$quiz/questions" );
	$added  = call_api(
		'POST',
		"/quizzes/$quiz/questions",
		array(
			'question_ids' => array( $first['id'], $second['id'], $third['id'] ),
			'revision'     => $list->get_data()['revision'],
		)
	);
	ok( 200 === $added->get_status() && 3 === $added->get_data()['totals']['questions'], 'three questions placed' );
	ok( array() === $added->get_data()['sections'], 'no sections yet' );
	ok( null === $added->get_data()['questions'][0]['marks_override'] && 1.0 === (float) $added->get_data()['questions'][0]['question_marks'], 'slots carry the question points and no override' );
	$revision = $added->get_data()['revision'];

	// Permissions.
	wp_set_current_user( 0 );
	ok_error(
		call_api(
			'PUT',
			"/quizzes/$quiz/questions/{$first['id']}/marks",
			array(
				'marks'    => 2,
				'revision' => $revision,
			)
		),
		401,
		'ohmylms_quiz_cannot_edit',
		'guest marks'
	);
	wp_set_current_user( $student );
	ok_error(
		call_api(
			'PUT',
			"/quizzes/$quiz/questions/sections",
			array(
				'sections' => array(),
				'revision' => $revision,
			)
		),
		403,
		'ohmylms_quiz_cannot_edit',
		'student sections'
	);
	wp_set_current_user( $other );
	ok_error(
		call_api(
			'PUT',
			"/quizzes/$quiz/questions/pools",
			array(
				'pools'    => array(),
				'revision' => $revision,
			)
		),
		403,
		'ohmylms_quiz_cannot_edit',
		'unrelated author pools'
	);
	ok_error(
		call_api(
			'PUT',
			'/quizzes/999999999/questions/pools',
			array(
				'pools'    => array(),
				'revision' => $revision,
			)
		),
		404,
		'ohmylms_quiz_not_found',
		'unknown quiz'
	);
	$foreign_bank = Banks::create( "$tag private bank", 0, 'private' );
	ok( is_array( $foreign_bank ) || is_int( $foreign_bank ), 'private bank of another author: ' . wp_json_encode( $foreign_bank ) );
	$foreign_bank = is_array( $foreign_bank ) ? (int) $foreign_bank['id'] : (int) $foreign_bank;
	$bank_ids[]   = $foreign_bank;
	wp_set_current_user( $author );

	// Marks.
	$marks_path = "/quizzes/$quiz/questions/{$first['id']}/marks";
	ok_error(
		call_api(
			'PUT',
			$marks_path,
			array(
				'marks'    => -1,
				'revision' => $revision,
			)
		),
		400,
		'rest_invalid_param',
		'negative marks'
	);
	ok_error(
		call_api(
			'PUT',
			$marks_path,
			array(
				'marks'    => 10001,
				'revision' => $revision,
			)
		),
		400,
		'rest_invalid_param',
		'marks above the maximum'
	);
	ok_error( call_api( 'PUT', $marks_path, array( 'revision' => $revision ) ), 400, 'rest_missing_callback_param', 'marks missing' );
	ok_error(
		call_api(
			'PUT',
			$marks_path,
			array(
				'marks'    => 2,
				'revision' => $revision,
				'extra'    => 1,
			)
		),
		400,
		'ohmylms_quiz_questions_unknown_field',
		'unknown field'
	);
	ok_error(
		call_api(
			'PUT',
			$marks_path,
			array(
				'marks'    => 2,
				'revision' => str_repeat( 'a', 16 ),
			)
		),
		409,
		'ohmylms_quiz_questions_conflict',
		'stale revision on marks'
	);
	$outsider = make_choice( 'outsider' );
	ok_error(
		call_api(
			'PUT',
			"/quizzes/$quiz/questions/{$outsider['id']}/marks",
			array(
				'marks'    => 2,
				'revision' => $revision,
			)
		),
		404,
		'ohmylms_question_not_in_quiz',
		'marks of a question outside the quiz'
	);
	$marked = call_api(
		'PUT',
		$marks_path,
		array(
			'marks'    => 2.5,
			'revision' => $revision,
		)
	);
	ok( 200 === $marked->get_status(), 'marks saved: ' . wp_json_encode( $marked->get_data() ) );
	$slot = $marked->get_data()['questions'][0];
	ok( 2.5 === (float) $slot['marks'] && 2.5 === (float) $slot['marks_override'] && 1.0 === (float) $slot['question_marks'], 'slot uses the override' );
	ok( 4.5 === (float) $marked->get_data()['totals']['total_marks'], 'totals include the override' );
	ok( $revision !== $marked->get_data()['revision'], 'marks change the revision' );
	ok_error(
		call_api(
			'PUT',
			$marks_path,
			array(
				'marks'    => 3,
				'revision' => $revision,
			)
		),
		409,
		'ohmylms_quiz_questions_conflict',
		'old revision after a marks change'
	);
	$revision = $marked->get_data()['revision'];
	clean_post_cache( $quiz );
	wp_cache_delete( $quiz, 'post_meta' );
	$stored = get_post_meta( $quiz, '_quiz_settings', true );
	ok( 2.5 === (float) ( $stored['slot_marks'][ $first['id'] ] ?? 0 ), 'override stored in slot_marks' );
	$revision_row = RevisionPublisher::publish( $quiz );
	ok( ! is_wp_error( $revision_row ), 'quiz publishes: ' . ( is_wp_error( $revision_row ) ? $revision_row->get_error_message() : '' ) );
	$frozen = array_column( $revision_row['slots'], 'marks', 'question_id' );
	ok( 2.5 === (float) $frozen[ $first['id'] ] && 1.0 === (float) $frozen[ $second['id'] ], 'publishing freezes the override' );

	// Sections.
	$sections_path = "/quizzes/$quiz/questions/sections";
	ok_error(
		call_api(
			'PUT',
			$sections_path,
			array(
				'sections' => array(
					array(
						'title'        => 'A',
						'question_ids' => array( $outsider['id'] ),
					),
				),
				'revision' => $revision,
			)
		),
		422,
		'ohmylms_quiz_sections_invalid',
		'section with a question outside the quiz'
	);
	ok_error(
		call_api(
			'PUT',
			$sections_path,
			array(
				'sections' => array(
					array(
						'title'        => 'A',
						'question_ids' => array( $first['id'] ),
					),
					array(
						'title'        => 'B',
						'question_ids' => array( $first['id'] ),
					),
				),
				'revision' => $revision,
			)
		),
		422,
		'ohmylms_quiz_sections_invalid',
		'question in two sections'
	);
	ok_error(
		call_api(
			'PUT',
			$sections_path,
			array(
				'sections' => array(
					array(
						'title'        => '  ',
						'question_ids' => array(),
					),
				),
				'revision' => $revision,
			)
		),
		422,
		'ohmylms_quiz_sections_invalid',
		'section without a title'
	);
	ok_error(
		call_api(
			'PUT',
			$sections_path,
			array(
				'sections' => array( array( 'question_ids' => array() ) ),
				'revision' => $revision,
			)
		),
		400,
		'rest_invalid_param',
		'section title missing'
	);
	$sectioned = call_api(
		'PUT',
		$sections_path,
		array(
			'sections' => array(
				array(
					'title'        => 'Part A',
					'question_ids' => array( $third['id'] ),
					'new_page'     => false,
				),
				array(
					'title'        => 'Part B',
					'question_ids' => array( $first['id'], $second['id'] ),
					'new_page'     => true,
				),
			),
			'revision' => $revision,
		)
	);
	ok( 200 === $sectioned->get_status(), 'sections saved: ' . wp_json_encode( $sectioned->get_data() ) );
	$data = $sectioned->get_data();
	ok( 2 === count( $data['sections'] ) && 'Part B' === $data['sections'][1]['title'] && true === $data['sections'][1]['new_page'] && array( $first['id'], $second['id'] ) === $data['sections'][1]['question_ids'], 'sections listed' );
	ok( 'Part B' === $data['questions'][0]['section'] && 'Part A' === $data['questions'][2]['section'], 'slots name their section' );
	ok( $revision !== $data['revision'], 'sections change the revision' );
	$revision = $data['revision'];
	$settings = AssessmentSettings::get( $quiz );
	ok( 2 === count( $settings['sections'] ) && ! empty( $settings['sections'][1]['new_page'] ), 'sections stored in the assessment settings' );
	$published = RevisionPublisher::publish( $quiz );
	ok( ! is_wp_error( $published ) && (int) $published['slots'][0]['question_id'] === (int) $third['id'] && 2 === (int) $published['slots'][1]['page'], 'publishing orders the paper by section and starts Part B on a new page' );

	// Removing a question drops its override and its section membership.
	$removed = call_api( 'DELETE', "/quizzes/$quiz/questions/{$first['id']}", array( 'revision' => $revision ) );
	ok( 200 === $removed->get_status(), 'question removed' );
	ok( array( $second['id'] ) === $removed->get_data()['sections'][1]['question_ids'], 'removed question left its section' );
	wp_cache_delete( $quiz, 'post_meta' );
	$stored = get_post_meta( $quiz, '_quiz_settings', true );
	ok( ! isset( $stored['slot_marks'][ $first['id'] ] ), 'removed question lost its override' );
	$revision = $removed->get_data()['revision'];

	// Pools.
	$pools_path = "/quizzes/$quiz/questions/pools";
	$pool       = array(
		'title'    => 'Warm-up',
		'skill_id' => (int) $skill['term_id'],
		'count'    => 1,
		'marks'    => 2,
	);
	ok_error(
		call_api(
			'PUT',
			$pools_path,
			array(
				'pools'    => array( array_merge( $pool, array( 'skill_id' => 999999999 ) ) ),
				'revision' => $revision,
			)
		),
		422,
		'ohmylms_quiz_pool_invalid',
		'pool of an unknown skill'
	);
	ok_error(
		call_api(
			'PUT',
			$pools_path,
			array(
				'pools'    => array( array_merge( $pool, array( 'count' => 51 ) ) ),
				'revision' => $revision,
			)
		),
		400,
		'rest_invalid_param',
		'pool too large'
	);
	ok_error(
		call_api(
			'PUT',
			$pools_path,
			array(
				'pools'    => array( array_merge( $pool, array( 'difficulty' => 'hard' ) ) ),
				'revision' => $revision,
			)
		),
		400,
		'rest_invalid_param',
		'pool difficulty'
	);
	ok_error(
		call_api(
			'PUT',
			$pools_path,
			array(
				'pools'    => array( array_merge( $pool, array( 'bank_id' => $foreign_bank ) ) ),
				'revision' => $revision,
			)
		),
		403,
		'ohmylms_quiz_pool_bank_forbidden',
		'pool from a bank the author may not use'
	);
	ok_error(
		call_api(
			'PUT',
			$pools_path,
			array(
				'pools'    => array( $pool ),
				'revision' => $revision,
			)
		),
		409,
		'quiz_pool_shortage',
		'pool without approved questions'
	);
	ok( array() === Pools::rules( $quiz ), 'refused pools wrote nothing' );
	$drawable = make_choice( 'drawable', $skill_map );
	ok( 200 === call_api( 'POST', "/questions/{$drawable['id']}/approve" )->get_status(), 'pool question approved' );
	ok_error(
		call_api(
			'PUT',
			$pools_path,
			array(
				'pools'    => array( $pool ),
				'revision' => str_repeat( 'b', 16 ),
			)
		),
		409,
		'ohmylms_quiz_questions_conflict',
		'stale revision on pools'
	);
	$pooled = call_api(
		'PUT',
		$pools_path,
		array(
			'pools'    => array( $pool ),
			'revision' => $revision,
		)
	);
	ok( 200 === $pooled->get_status(), 'pool saved: ' . wp_json_encode( $pooled->get_data() ) );
	$data = $pooled->get_data();
	ok( 1 === count( $data['pools'] ) && 'Warm-up' === $data['pools'][0]['title'] && "$tag skill" === $data['pools'][0]['skill']['name'] && true === $data['pools'][0]['include_secure'], 'pool listed' );
	ok( 1 === $data['totals']['pool_questions'] && 2.0 === (float) $data['totals']['pool_marks'] && 4.0 === (float) $data['totals']['total_marks'], 'pool totals: ' . wp_json_encode( $data['totals'] ) );
	ok( $revision !== $data['revision'], 'pools change the revision' );
	$revision = $data['revision'];
	$cleared  = call_api(
		'PUT',
		$pools_path,
		array(
			'pools'    => array(),
			'revision' => $revision,
		)
	);
	ok( 200 === $cleared->get_status() && array() === $cleared->get_data()['pools'] && '' === get_post_meta( $quiz, Pools::META, true ), 'pools cleared' );

	echo "OK quiz-questions-authoring-integration: $checks checks\n";
	$exit_code = 0;
} catch ( Throwable $error ) {
	fwrite( STDERR, $error->getMessage() . "\n" . $error->getTraceAsString() . "\n" );
	echo "Passed $checks checks before the failure.\n";
	$exit_code = 1;
} finally {
	wp_set_current_user( 0 );
	foreach ( $post_ids as $post_id ) {
		if ( Schema::ready() ) {
			$wpdb->query( $wpdb->prepare( 'DELETE s FROM %i s JOIN %i r ON r.id = s.revision_id WHERE r.quiz_id = %d', Schema::table( 'quiz_revision_slots' ), Schema::table( 'quiz_revisions' ), $post_id ) );
			$wpdb->delete( Schema::table( 'quiz_revisions' ), array( 'quiz_id' => $post_id ), array( '%d' ) );
		}
		$wpdb->delete( $wpdb->prefix . OHMYLMS_QUIZ_QUESTION_RELATIONSHIP, array( 'quiz_id' => $post_id ), array( '%d' ) );
		wp_delete_post( $post_id, true );
	}
	$question_ids = array_unique( array_merge( array_map( 'intval', $question_ids ), array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title LIKE %s", OHMYLMS_QUESTION_CPT, $wpdb->esc_like( $tag ) . '%' ) ) ) ) );
	foreach ( $question_ids as $leftover ) {
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE question_id = %d', $wpdb->prefix . 'ohmylms_question_answers', $leftover ) ) as $answer_id ) {
			$wpdb->delete( $wpdb->prefix . 'ohmylms_question_answermeta', array( 'answer_id' => (int) $answer_id ), array( '%d' ) );
		}
		$wpdb->delete( $wpdb->prefix . 'ohmylms_question_answers', array( 'question_id' => $leftover ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . OHMYLMS_QUIZ_QUESTION_RELATIONSHIP, array( 'question_id' => $leftover ), array( '%d' ) );
		wp_delete_post( $leftover, true );
		if ( Schema::ready() ) {
			$wpdb->query( $wpdb->prepare( 'DELETE s FROM %i s JOIN %i v ON v.id = s.version_id WHERE v.question_id = %d', Schema::table( 'qb_version_skills' ), Schema::table( 'qb_question_versions' ), $leftover ) );
			$wpdb->delete( Schema::table( 'qb_question_versions' ), array( 'question_id' => $leftover ), array( '%d' ) );
			$wpdb->delete( Schema::table( 'qb_questions' ), array( 'question_id' => $leftover ), array( '%d' ) );
			$wpdb->delete( Schema::table( 'question_skills' ), array( 'question_id' => $leftover ), array( '%d' ) );
		}
	}
	foreach ( $bank_ids as $bank_id ) {
		$wpdb->delete( Schema::table( 'qb_grants' ), array( 'bank_id' => $bank_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'qb_banks' ), array( 'id' => $bank_id ), array( '%d' ) );
	}
	foreach ( $term_ids as $term_id ) {
		wp_delete_term( $term_id, Taxonomy::NAME );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
}

exit( isset( $exit_code ) ? $exit_code : 0 );
