<?php
/**
 * Question bank delete (single and bulk) and copy-into-quiz:
 * `POST /questions/delete`, `DELETE /questions/{id}` and `POST /quizzes/{id}/questions/copy`.
 *
 * Covers the usage rules (unused questions are deleted, questions with learner history or in a published
 * quiz revision are archived, questions in several quizzes are refused), the cleanup of the quiz a deleted
 * question leaves (pin, marks, section) under the quiz revision lock, permissions, and copies placed at
 * the end of a quiz or in place of their source.
 *
 * Runs against the Local site (`800.local`) and removes everything it creates.
 *
 * Usage (set OHMYLMS_QUESTIONS_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/question-delete-copy-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI test script writing to STDOUT/STDERR.

use OhMyLMS\Assessment\RevisionPublisher;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\QuestionBank\Banks;
use OhMyLMS\QuestionBank\Usage;

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_QUESTIONS_TEST' ) ) {
	exit( "Set OHMYLMS_QUESTIONS_TEST=1 to run the question delete and copy tests against the local site.\n" );
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
$bank_ids     = array();
$history      = array();
$tag          = 'qdel' . strtolower( wp_generate_password( 6, false, false ) );

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
 * @param string $path   Path.
 * @param array  $data   JSON body (query for GET and DELETE).
 * @return WP_REST_Response
 */
function call_api( $method, $path, $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1' . $path );
	if ( 'GET' === $method || 'DELETE' === $method ) {
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
	ok( $status === $response->get_status() && $code === $got, "$label: expected $status $code, got " . $response->get_status() . ' ' . wp_json_encode( $data ) );
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
 * Create a single-choice question of the current user.
 *
 * @param string $name  Name suffix.
 * @param array  $extra Extra body fields.
 * @return int Question ID.
 */
function make_question( $name, array $extra = array() ) {
	global $question_ids, $tag;
	$response = call_api(
		'POST',
		'/questions',
		array_merge(
			array(
				'name'     => "$tag $name",
				'settings' => array(
					'type'  => 'single-choice',
					'score' => array(
						'enabled' => true,
						'value'   => 2,
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
			),
			$extra
		)
	);
	ok( 201 === $response->get_status(), "question $name: " . wp_json_encode( $response->get_data() ) );
	$question_ids[] = (int) $response->get_data()['id'];
	return (int) $response->get_data()['id'];
}

/**
 * Create a quiz.
 *
 * @param string $name   Name suffix.
 * @param int    $author Author.
 * @return int
 */
function make_quiz( $name, $author ) {
	global $post_ids, $tag;
	$quiz       = (int) wp_insert_post(
		array(
			'post_type'   => OHMYLMS_QUIZ_CPT,
			'post_title'  => "$tag $name",
			'post_status' => 'draft',
			'post_author' => $author,
		)
	);
	$post_ids[] = $quiz;
	return $quiz;
}

/**
 * Current revision of a quiz's question list.
 *
 * @param int $quiz Quiz.
 * @return string
 */
function revision_of( $quiz ) {
	return (string) call_api( 'GET', "/quizzes/$quiz/questions" )->get_data()['revision'];
}

/**
 * Place questions in a quiz.
 *
 * @param int   $quiz Quiz.
 * @param int[] $ids  Questions.
 * @return array Quiz questions.
 */
function place( $quiz, array $ids ) {
	$response = call_api(
		'POST',
		"/quizzes/$quiz/questions",
		array(
			'question_ids' => $ids,
			'revision'     => revision_of( $quiz ),
		)
	);
	ok( 200 === $response->get_status(), 'placed: ' . wp_json_encode( $response->get_data() ) );
	return $response->get_data();
}

/**
 * Question IDs of a quiz in order.
 *
 * @param array $placement Quiz questions.
 * @return int[]
 */
function ids_of( array $placement ) {
	return array_map( 'intval', array_column( $placement['questions'], 'question_id' ) );
}

add_filter(
	'ohmylms_question_response_count',
	static function ( $count, $question_id ) use ( &$history ) {
		return in_array( (int) $question_id, $history, true ) ? $count + 1 : $count;
	},
	10,
	2
);
$fired = array();
add_action(
	'ohmylms_rest_delete_question',
	static function ( $request, $result ) use ( &$fired ) {
		$fired[] = $result;
	},
	10,
	2
);

try {
	ok( Schema::ready(), 'question bank schema is installed' );
	$routes = rest_get_server()->get_routes( 'ohmylms/v1' );
	ok( isset( $routes['/ohmylms/v1/questions/delete'] ), 'bulk delete route' );
	ok( isset( $routes['/ohmylms/v1/quizzes/(?P<id>[\d]+)/questions/copy'] ), 'copy into quiz route' );

	$author  = make_test_user( 'author', 'author' );
	$other   = make_test_user( 'other', 'author' );
	$student = make_test_user( 'student', 'subscriber' );

	wp_set_current_user( $author );
	$unused   = make_question( 'unused' );
	$placed   = make_question( 'placed' );
	$shared   = make_question( 'shared' );
	$answered = make_question( 'answered' );
	$frozen   = make_question( 'frozen' );
	$quiz     = make_quiz( 'quiz', $author );
	$second   = make_quiz( 'second quiz', $author );
	$third    = make_quiz( 'third quiz', $author );
	place( $quiz, array( $placed, $shared, $answered ) );
	place( $second, array( $shared ) );
	place( $third, array( $frozen ) );
	$history[] = $answered;

	// The placed question gets a pin, a marks override and a section.
	$pinned = call_api(
		'PUT',
		"/quizzes/$quiz/questions/$placed/pin",
		array(
			'current'  => true,
			'revision' => revision_of( $quiz ),
		)
	);
	ok( 200 === $pinned->get_status(), 'pinned: ' . wp_json_encode( $pinned->get_data() ) );
	ok(
		200 === call_api(
			'PUT',
			"/quizzes/$quiz/questions/$placed/marks",
			array(
				'marks'    => 5,
				'revision' => revision_of( $quiz ),
			)
		)->get_status(),
		'marks override'
	);
	$sectioned = call_api(
		'PUT',
		"/quizzes/$quiz/questions/sections",
		array(
			'sections' => array(
				array(
					'title'        => 'Part A',
					'question_ids' => array( $placed, $answered ),
				),
			),
			'revision' => revision_of( $quiz ),
		)
	);
	ok( 200 === $sectioned->get_status(), 'section: ' . wp_json_encode( $sectioned->get_data() ) );

	// The list tells which rows may be deleted.
	$rows = call_api( 'GET', '/questions', array( 'search' => $tag ) )->get_data();
	ok( count( $rows ) >= 5 && true === $rows[0]['permissions']['can_delete'], 'summaries carry permissions.can_delete' );

	wp_set_current_user( $other );
	$foreign = make_question( 'foreign' );

	// Permissions.
	wp_set_current_user( 0 );
	ok_error( call_api( 'POST', '/questions/delete', array( 'ids' => array( $unused ) ) ), 401, 'ohmylms_question_cannot_delete', 'anonymous bulk delete' );
	wp_set_current_user( $student );
	ok_error( call_api( 'POST', '/questions/delete', array( 'ids' => array( $unused ) ) ), 403, 'ohmylms_question_cannot_delete', 'subscriber bulk delete' );

	// Validation and preflight: nothing changes when one question is blocked.
	wp_set_current_user( $author );
	ok_error( call_api( 'POST', '/questions/delete', array( 'ids' => array() ) ), 400, 'rest_invalid_param', 'empty ids' );
	ok_error( call_api( 'POST', '/questions/delete', array( 'ids' => range( 1, 101 ) ) ), 400, 'rest_invalid_param', 'more than 100 ids' );
	ok_error( call_api( 'POST', '/questions/delete', array( 'ids' => array( $unused, $unused ) ) ), 400, 'rest_invalid_param', 'duplicate ids' );
	ok_error(
		call_api(
			'POST',
			'/questions/delete',
			array(
				'ids'   => array( $unused ),
				'force' => true,
			)
		),
		400,
		'ohmylms_question_unknown_field',
		'unknown field'
	);
	$blocked = call_api( 'POST', '/questions/delete', array( 'ids' => array( $unused, $shared, $foreign, 999999999 ) ) );
	ok_error( $blocked, 409, 'ohmylms_question_delete_blocked', 'blocked questions refuse the batch' );
	$reasons = array_column( $blocked->get_data()['data']['blocked'], 'code', 'id' );
	ok( 'ohmylms_question_shared' === $reasons[ $shared ] && 'ohmylms_question_cannot_delete' === $reasons[ $foreign ] && 'ohmylms_question_not_found' === $reasons[999999999] && ! isset( $reasons[ $unused ] ), 'each blocked question says why' );
	$shared_row = array_values(
		array_filter(
			$blocked->get_data()['data']['blocked'],
			static function ( $row ) use ( $shared ) {
				return $row['id'] === $shared;
			}
		)
	)[0];
	ok( array( $quiz, $second ) === $shared_row['quizzes'] || array( $second, $quiz ) === $shared_row['quizzes'], 'shared question lists its quizzes' );
	ok( get_post( $unused ) && array() === $fired, 'nothing was deleted' );

	// skip_blocked deletes the others.
	$partial = call_api(
		'POST',
		'/questions/delete',
		array(
			'ids'          => array( $unused, $shared, $foreign ),
			'skip_blocked' => true,
		)
	);
	ok( 200 === $partial->get_status(), 'skip_blocked: ' . wp_json_encode( $partial->get_data() ) );
	ok(
		array(
			array(
				'id'     => $unused,
				'action' => 'deleted',
			),
		) === $partial->get_data()['results'] && 2 === count( $partial->get_data()['skipped'] ),
		'unused question deleted, the blocked ones reported'
	);
	ok( ! get_post( $unused ) && get_post( $shared ) && get_post( $foreign ), 'only the unused question is gone' );
	ok( ! $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE question_id = %d', Schema::table( 'qb_question_versions' ), $unused ) ), 'its versions are gone' );
	ok( 1 === count( $fired ) && 'deleted' === $fired[0]['action'], 'ohmylms_rest_delete_question fired once' );

	// A placed question is deleted and leaves no trace in its quiz; one with history is archived.
	$before  = revision_of( $quiz );
	$deleted = call_api( 'POST', '/questions/delete', array( 'ids' => array( $placed, $answered ) ) );
	ok( 200 === $deleted->get_status(), 'placed and answered: ' . wp_json_encode( $deleted->get_data() ) );
	$actions = array_column( $deleted->get_data()['results'], 'action', 'id' );
	ok( 'deleted' === $actions[ $placed ] && 'archived' === $actions[ $answered ], 'unanswered question deleted, answered question archived' );
	ok( ! get_post( $placed ) && Usage::ARCHIVED === get_post_status( $answered ), 'post states' );
	$after = call_api( 'GET', "/quizzes/$quiz/questions" )->get_data();
	ok( array( $shared ) === ids_of( $after ), 'both left the quiz; the shared question stays' );
	ok( $before !== $after['revision'], 'the quiz revision changed, so open editors reload' );
	wp_cache_delete( $quiz, 'post_meta' );
	$pins = get_post_meta( $quiz, '_ohmylms_version_pins', true );
	ok( ! is_array( $pins ) || ! isset( $pins[ $placed ] ), 'pin removed' );
	$settings = get_post_meta( $quiz, '_quiz_settings', true );
	ok( ! isset( $settings['slot_marks'][ $placed ] ), 'marks override removed' );
	ok( array() === $after['sections'][0]['question_ids'], 'section membership removed' );
	$stale = call_api(
		'PUT',
		"/quizzes/$quiz/questions/order",
		array(
			'question_ids' => array( $shared ),
			'revision'     => $before,
		)
	);
	ok_error( $stale, 409, 'ohmylms_quiz_questions_conflict', 'an editor holding the old revision is refused' );

	// A question in a published quiz revision is archived, never deleted.
	ok( ! is_wp_error( RevisionPublisher::publish( $third ) ), 'third quiz published' );
	ok( Usage::in_revision( $frozen ), 'the frozen question is in a revision' );
	$single = call_api( 'DELETE', "/questions/$frozen" );
	ok( 200 === $single->get_status() && true === $single->get_data()['archived'] && false === $single->get_data()['deleted'], 'single delete archives a question a revision delivers: ' . wp_json_encode( $single->get_data() ) );
	ok( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE question_id = %d', Schema::table( 'qb_question_versions' ), $frozen ) ) > 0, 'its versions are kept for the revision' );

	// Copy into a quiz.
	$source = make_question( 'source' );
	$target = make_quiz( 'target', $author );
	place( $target, array( $source ) );
	wp_set_current_user( 0 );
	ok_error(
		call_api(
			'POST',
			"/quizzes/$target/questions/copy",
			array(
				'question_ids' => array( $source ),
				'revision'     => str_repeat( 'a', 16 ),
			)
		),
		401,
		'ohmylms_quiz_cannot_edit',
		'anonymous copy'
	);
	wp_set_current_user( $other );
	ok_error(
		call_api(
			'POST',
			"/quizzes/$target/questions/copy",
			array(
				'question_ids' => array( $foreign ),
				'revision'     => str_repeat( 'a', 16 ),
			)
		),
		403,
		'ohmylms_quiz_cannot_edit',
		'copy into a quiz the user cannot edit'
	);
	wp_set_current_user( $author );
	$revision = revision_of( $target );
	ok_error(
		call_api(
			'POST',
			"/quizzes/$target/questions/copy",
			array(
				'question_ids' => array( $source ),
				'revision'     => $revision,
				'pin'          => true,
			)
		),
		400,
		'ohmylms_quiz_questions_unknown_field',
		'copy refuses unknown fields'
	);
	ok_error(
		call_api(
			'POST',
			"/quizzes/$target/questions/copy",
			array(
				'question_ids' => array( $source ),
				'revision'     => str_repeat( 'b', 16 ),
			)
		),
		409,
		'ohmylms_quiz_questions_conflict',
		'copy with a stale revision'
	);
	ok_error(
		call_api(
			'POST',
			"/quizzes/$target/questions/copy",
			array(
				'question_ids' => array( $answered ),
				'revision'     => $revision,
			)
		),
		409,
		'ohmylms_question_archived',
		'archived questions are not copied in'
	);
	ok_error(
		call_api(
			'POST',
			"/quizzes/$target/questions/copy",
			array(
				'question_ids' => array( $foreign ),
				'revision'     => $revision,
			)
		),
		403,
		'ohmylms_quiz_question_forbidden',
		'a question the user may not use'
	);
	ok_error(
		call_api(
			'POST',
			"/quizzes/$target/questions/copy",
			array(
				'question_ids' => array( 999999999 ),
				'revision'     => $revision,
			)
		),
		404,
		'ohmylms_question_not_found',
		'unknown question'
	);
	$count  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_title LIKE %s", OHMYLMS_QUESTION_CPT, $wpdb->esc_like( $tag ) . '%' ) );
	$copied = call_api(
		'POST',
		"/quizzes/$target/questions/copy",
		array(
			'question_ids' => array( $source, $shared ),
			'revision'     => $revision,
		)
	);
	ok( 201 === $copied->get_status(), 'copied: ' . wp_json_encode( $copied->get_data() ) );
	$map = array_column( $copied->get_data()['copied'], 'id', 'source_id' );
	ok( 2 === count( $map ) && $map[ $source ] !== $source && $map[ $shared ] !== $shared, 'each source got a new question' );
	ok( array( $source, $map[ $source ], $map[ $shared ] ) === ids_of( $copied->get_data() ), 'copies are placed at the end, the source stays' );
	ok( "$tag source (copy)" === get_the_title( $map[ $source ] ) && (int) get_post_field( 'post_author', $map[ $source ] ) === $author, 'copy is named and owned by the user' );
	ok( $count + 2 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_title LIKE %s", OHMYLMS_QUESTION_CPT, $wpdb->esc_like( $tag ) . '%' ) ), 'exactly two questions were created' );
	$question_ids = array_merge( $question_ids, array_values( $map ) );

	// Replace: the copy takes the source's place, marks and section; the pin is dropped.
	$replaceable = make_question( 'replaceable' );
	$after_it    = make_question( 'after' );
	$swap        = make_quiz( 'swap', $author );
	place( $swap, array( $replaceable, $after_it ) );
	ok(
		200 === call_api(
			'PUT',
			"/quizzes/$swap/questions/$replaceable/pin",
			array(
				'current'  => true,
				'revision' => revision_of( $swap ),
			)
		)->get_status(),
		'pin before replace'
	);
	ok(
		200 === call_api(
			'PUT',
			"/quizzes/$swap/questions/$replaceable/marks",
			array(
				'marks'    => 7,
				'revision' => revision_of( $swap ),
			)
		)->get_status(),
		'marks before replace'
	);
	ok(
		200 === call_api(
			'PUT',
			"/quizzes/$swap/questions/sections",
			array(
				'sections' => array(
					array(
						'title'        => 'Only',
						'question_ids' => array( $replaceable, $after_it ),
					),
				),
				'revision' => revision_of( $swap ),
			)
		)->get_status(),
		'section before replace'
	);
	$revision = revision_of( $swap );
	ok_error(
		call_api(
			'POST',
			"/quizzes/$swap/questions/copy",
			array(
				'question_ids' => array( $replaceable, $after_it ),
				'replace'      => true,
				'revision'     => $revision,
			)
		),
		422,
		'ohmylms_quiz_question_replace_invalid',
		'replace takes one question'
	);
	ok_error(
		call_api(
			'POST',
			"/quizzes/$swap/questions/copy",
			array(
				'question_ids' => array( $source ),
				'replace'      => true,
				'revision'     => $revision,
			)
		),
		422,
		'ohmylms_quiz_question_replace_invalid',
		'replace needs a question of the quiz'
	);
	$replaced = call_api(
		'POST',
		"/quizzes/$swap/questions/copy",
		array(
			'question_ids' => array( $replaceable ),
			'replace'      => true,
			'revision'     => $revision,
		)
	);
	ok( 201 === $replaced->get_status(), 'replaced: ' . wp_json_encode( $replaced->get_data() ) );
	$copy_id        = (int) $replaced->get_data()['copied'][0]['id'];
	$question_ids[] = $copy_id;
	$list           = $replaced->get_data();
	ok( array( $copy_id, $after_it ) === ids_of( $list ), 'the copy is in the place of its source' );
	ok( 7.0 === (float) $list['questions'][0]['marks_override'] && 0 === $list['questions'][0]['pinned_version_id'], 'marks override moved, pin dropped' );
	ok( array( $copy_id, $after_it ) === $list['sections'][0]['question_ids'], 'section membership moved' );
	ok( get_post( $replaceable ) && Usage::quizzes( $replaceable ) === array(), 'the source stays in the bank, out of the quiz' );

	// Shared bank: a use grant can copy an unapproved question although it cannot place it by reference.
	wp_set_current_user( $author );
	$bank = Banks::create( "$tag bank", 0, 'shared' );
	ok( is_array( $bank ), 'bank created' );
	$bank_ids[] = (int) $bank['id'];
	ok( ! is_wp_error( Banks::grant( (int) $bank['id'], $other, 'use' ) ), 'use grant' );
	$banked = make_question( 'banked', array( 'bank' => array( 'bank_id' => (int) $bank['id'] ) ) );
	wp_set_current_user( $other );
	$own = make_quiz( 'other quiz', $other );
	ok_error(
		call_api(
			'POST',
			"/quizzes/$own/questions",
			array(
				'question_ids' => array( $banked ),
				'revision'     => revision_of( $own ),
			)
		),
		409,
		'quiz_question_unapproved',
		'adding the shared question itself needs an approved version'
	);
	$shared_copy = call_api(
		'POST',
		"/quizzes/$own/questions/copy",
		array(
			'question_ids' => array( $banked ),
			'revision'     => revision_of( $own ),
		)
	);
	ok( 201 === $shared_copy->get_status(), 'a use grant may copy it: ' . wp_json_encode( $shared_copy->get_data() ) );
	$own_copy       = (int) $shared_copy->get_data()['copied'][0]['id'];
	$question_ids[] = $own_copy;
	ok( (int) get_post_field( 'post_author', $own_copy ) === $other && true === $shared_copy->get_data()['questions'][0]['permissions']['can_edit'], 'the copy belongs to the grantee and is editable' );

	echo "OK question-delete-copy-integration: $checks checks\n";
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
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
}

exit( isset( $exit_code ) ? $exit_code : 0 );
