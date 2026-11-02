<?php
/**
 * Question Bank import (review then apply) and export (selected or filtered) REST integration test.
 *
 * Runs against the Local site (`800.local`). It creates tagged questions, one skill and temporary
 * users and removes them in `finally`.
 *
 * Usage (set OHMYLMS_QUESTIONS_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/question-exchange-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_QUESTIONS_TEST' ) ) {
	exit( "Set OHMYLMS_QUESTIONS_TEST=1 to run the question import/export REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Assessment\Schema;
use OhMyLMS\QuestionBank\BulkImport;
use OhMyLMS\Skills\Taxonomy;

global $wpdb;

$checks   = 0;
$user_ids = array();
$skill_id = 0;
$tag      = 'qx' . strtolower( wp_generate_password( 6, false, false ) );

/**
 * Assert a condition.
 *
 * @param mixed  $value   Truthy when the check passes.
 * @param string $message Failure message.
 * @throws RuntimeException On failure.
 */
function ok( $value, $message ) {
	global $checks;
	if ( ! $value ) {
		throw new RuntimeException( 'FAILED: ' . $message );
	}
	++$checks;
}

/**
 * POST JSON to a route below /ohmylms/v1.
 *
 * @param string $path Route.
 * @param array  $data Body.
 * @return WP_REST_Response
 */
function qx_post( $path, array $data ) {
	$request = new WP_REST_Request( 'POST', '/ohmylms/v1' . $path );
	$request->set_header( 'Content-Type', 'application/json' );
	$request->set_body( wp_json_encode( $data ) );
	return rest_do_request( $request );
}

/**
 * Assert a status and code.
 *
 * @param WP_REST_Response $response Response.
 * @param int              $status   Expected status.
 * @param string           $code     Expected code.
 * @param string           $label    Description.
 */
function ok_error( $response, $status, $code, $label ) {
	$data = $response->get_data();
	ok( $response->get_status() === $status && ( $data['code'] ?? '' ) === $code, "$label: expected $status $code, got " . $response->get_status() . ' ' . wp_json_encode( $data ) );
}

/**
 * Create a temporary user.
 *
 * @param string $name Suffix.
 * @param string $role Role.
 * @return int
 */
function qx_user( $name, $role ) {
	global $tag, $user_ids;
	$id = wp_insert_user(
		array(
			'user_login' => $tag . $name,
			'user_pass'  => wp_generate_password( 24 ),
			'user_email' => $tag . $name . '@example.invalid',
			'role'       => $role,
		)
	);
	ok( ! is_wp_error( $id ), "user $name" );
	$user_ids[] = $id;
	return $id;
}

/**
 * Questions this run created.
 *
 * @return int[]
 */
function qx_created() {
	global $wpdb, $tag;
	return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT post_id FROM %i WHERE meta_key=%s AND meta_value LIKE %s', $wpdb->postmeta, BulkImport::KEY_META, '%' . $wpdb->esc_like( $tag ) . '%' ) ) );
}

try {
	ok( Schema::ready(), 'question bank tables installed' );
	$admins = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	);
	ok( ! empty( $admins ), 'an administrator exists' );
	$admin      = (int) $admins[0];
	$author     = qx_user( 'author', 'author' );
	$subscriber = qx_user( 'sub', 'subscriber' );
	wp_set_current_user( $admin );
	$term = wp_insert_term( $tag . ' skill', Taxonomy::NAME );
	ok( ! is_wp_error( $term ), 'skill fixture' );
	$skill_id = (int) $term['term_id'];

	$routes = rest_get_server()->get_routes( 'ohmylms/v1' );
	foreach ( array( '/ohmylms/v1/question-bank/import', '/ohmylms/v1/questions/export' ) as $route ) {
		ok( isset( $routes[ $route ] ) && is_callable( $routes[ $route ][0]['permission_callback'] ), "route $route" );
	}

	// Permissions.
	wp_set_current_user( 0 );
	ok( 401 === qx_post( '/question-bank/import', array( 'text' => 'x' ) )->get_status(), 'anonymous import refused' );
	ok(
		401 === qx_post(
			'/questions/export',
			array(
				'format' => 'csv',
				'ids'    => array( 1 ),
			)
		)->get_status(),
		'anonymous export refused'
	);
	wp_set_current_user( $subscriber );
	ok_error( qx_post( '/question-bank/import', array( 'text' => 'x' ) ), 403, 'ohmylms_question_cannot_import', 'subscriber import' );
	ok_error(
		qx_post(
			'/questions/export',
			array(
				'format' => 'csv',
				'ids'    => array( 1 ),
			)
		),
		403,
		'ohmylms_question_cannot_export',
		'subscriber export'
	);
	wp_set_current_user( $admin );

	// Schema limits.
	ok_error( qx_post( '/question-bank/import', array( 'text' => str_repeat( 'x', 2000001 ) ) ), 400, 'rest_invalid_param', 'text size limit' );
	ok_error( qx_post( '/question-bank/import', array( 'rows' => array_fill( 0, 201, array( 'question' => 'x' ) ) ) ), 400, 'rest_invalid_param', 'row limit' );
	ok_error(
		qx_post(
			'/question-bank/import',
			array(
				'text'   => 'x',
				'format' => 'xml',
			)
		),
		400,
		'rest_invalid_param',
		'format enum'
	);
	ok_error(
		qx_post(
			'/question-bank/import',
			array(
				'text'   => 'x',
				'review' => 'nope',
			)
		),
		400,
		'rest_invalid_param',
		'review format'
	);
	ok_error(
		qx_post(
			'/question-bank/import',
			array(
				'text'    => 'x',
				'bank_id' => -1,
			)
		),
		400,
		'rest_invalid_param',
		'bank id minimum'
	);
	ok_error( qx_post( '/questions/export', array( 'ids' => array( 1 ) ) ), 400, 'rest_missing_callback_param', 'export format required' );
	ok_error( qx_post( '/questions/export', array( 'format' => 'csv' ) ), 400, 'ohmylms_question_export_scope', 'export needs ids or filters' );
	ok_error(
		qx_post(
			'/questions/export',
			array(
				'format'  => 'csv',
				'ids'     => array( 1 ),
				'filters' => array( 'search' => 'x' ),
			)
		),
		400,
		'ohmylms_question_export_scope',
		'not both'
	);
	ok_error(
		qx_post(
			'/questions/export',
			array(
				'format'  => 'csv',
				'filters' => array( 'orderby' => 'x' ),
			)
		),
		400,
		'rest_invalid_param',
		'unknown filter refused'
	);
	ok_error(
		qx_post(
			'/questions/export',
			array(
				'format' => 'csv',
				'ids'    => range( 1, 201 ),
			)
		),
		400,
		'rest_invalid_param',
		'export id limit'
	);

	// Review, then apply.
	$csv    = "exam,paper,year,section,number,type,question,answers,correct,skills\n";
	$csv   .= "$tag,P1,2026,A,1,single-choice,\"$tag What is 2+2?\",3|4|5,B,#$skill_id\n";
	$csv   .= "$tag,P1,2026,A,2,true-false,\"$tag The sky is blue.\",,true,#$skill_id\n";
	$review = qx_post(
		'/question-bank/import',
		array(
			'text'    => $csv,
			'format'  => 'csv',
			'dry_run' => true,
		)
	);
	$report = $review->get_data();
	ok( 200 === $review->get_status() && 2 === $report['counts']['create'] && preg_match( '/^[a-f0-9]{64}$/', $report['review'] ), 'dry run plans two questions: ' . wp_json_encode( $report ) );
	ok( 'private, no-store' === ( $review->get_headers()['Cache-Control'] ?? '' ), 'import responses are not cacheable' );
	ok( ! qx_created(), 'dry run writes nothing' );
	ok_error(
		qx_post(
			'/question-bank/import',
			array(
				'text'    => $csv . "$tag,P1,2026,A,3,true-false,\"$tag Water is wet.\",,true,#$skill_id\n",
				'format'  => 'csv',
				'dry_run' => false,
				'review'  => $report['review'],
			)
		),
		409,
		'ohmylms_import_review_stale',
		'changed file needs a new check'
	);
	ok( ! qx_created(), 'stale apply writes nothing' );
	$apply = array(
		'text'    => $csv,
		'format'  => 'csv',
		'dry_run' => false,
		'review'  => $report['review'],
	);
	wp_set_current_user( $author );
	ok_error( qx_post( '/question-bank/import', $apply ), 409, 'ohmylms_import_review_stale', 'review bound to the reviewer' );
	wp_set_current_user( $admin );
	$applied = qx_post( '/question-bank/import', $apply )->get_data();
	ok( ! empty( $applied['committed'] ) && 2 === count( qx_created() ), 'reviewed import committed' );
	ok_error( qx_post( '/question-bank/import', $apply ), 409, 'ohmylms_import_review_stale', 'the same review cannot be applied twice' );
	$again = qx_post(
		'/question-bank/import',
		array(
			'text'   => $csv,
			'format' => 'csv',
		)
	)->get_data();
	ok( 2 === $again['counts']['unchanged'] && 0 === $again['counts']['create'], 'a second check finds the questions unchanged' );
	$ids = array_map( 'intval', array_column( $applied['rows'], 'id' ) );

	// Export selected and filtered questions.
	$selected = qx_post(
		'/questions/export',
		array(
			'format' => 'csv',
			'ids'    => $ids,
			'label'  => $tag,
		)
	);
	$file     = $selected->get_data();
	ok( 200 === $selected->get_status() && 2 === $file['count'] && 'text/csv' === $file['mime'] && false !== strpos( $file['content'], 'What is 2+2?' ) && 0 === $file['skipped'], 'selected questions exported: ' . wp_json_encode( array_diff_key( $file, array( 'content' => 1 ) ) ) );
	ok( 'private, no-store' === ( $selected->get_headers()['Cache-Control'] ?? '' ), 'export is not cacheable' );
	$filtered = qx_post(
		'/questions/export',
		array(
			'format'  => 'json',
			'filters' => array( 'search' => $tag ),
		)
	)->get_data();
	ok( 2 === $filtered['count'] && is_array( json_decode( $filtered['content'], true ) ), 'filtered questions exported as JSON' );
	$round = qx_post(
		'/question-bank/import',
		array(
			'text'   => $file['content'],
			'format' => 'csv',
		)
	)->get_data();
	// The exporter writes the label as the exam (no paper or year), so the check plans copies, never errors.
	ok( 0 === $round['counts']['error'] && 2 === $round['counts']['create'], 'an exported file passes the import check: ' . wp_json_encode( $round['counts'] ) );
	ok_error(
		qx_post(
			'/questions/export',
			array(
				'format'  => 'csv',
				'filters' => array( 'search' => $tag . 'nothing' ),
			)
		),
		422,
		'ohmylms_question_export_empty',
		'no match'
	);
	ok_error(
		qx_post(
			'/questions/export',
			array(
				'format'  => 'csv',
				'filters' => array( 'type' => 'no-such-type' ),
			)
		),
		400,
		'ohmylms_question_invalid_type',
		'unknown type'
	);
	ok_error(
		qx_post(
			'/questions/export',
			array(
				'format' => 'csv',
				'ids'    => array( 999999999 ),
			)
		),
		404,
		'ohmylms_question_not_found',
		'unknown question'
	);

	// Export the questions of a quiz, in quiz order, named after the quiz.
	$qx_quiz = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_QUIZ_CPT,
			'post_title'  => $tag . ' quiz',
			'post_status' => 'draft',
		)
	);
	foreach ( array_reverse( $ids ) as $question_id ) {
		\OhMyLMS\QuestionBank\DraftWriter::link( $qx_quiz, $question_id );
	}
	$quiz_file = qx_post(
		'/questions/export',
		array(
			'format'  => 'json',
			'quiz_id' => $qx_quiz,
		)
	);
	$rows      = json_decode( (string) ( $quiz_file->get_data()['content'] ?? '' ), true );
	ok( 200 === $quiz_file->get_status() && 2 === $quiz_file->get_data()['count'] && 0 === $quiz_file->get_data()['skipped'], 'a quiz export: ' . wp_json_encode( array_diff_key( (array) $quiz_file->get_data(), array( 'content' => 1 ) ) ) );
	ok( is_array( $rows ) && false !== strpos( wp_json_encode( $rows ), $tag . ' quiz' ), 'the quiz title labels the rows' );
	ok_error(
		qx_post(
			'/questions/export',
			array(
				'format'  => 'json',
				'quiz_id' => $qx_quiz,
				'ids'     => $ids,
			)
		),
		400,
		'ohmylms_question_export_scope',
		'a quiz and selected IDs together'
	);
	ok_error(
		qx_post(
			'/questions/export',
			array(
				'format'  => 'json',
				'quiz_id' => 999999999,
			)
		),
		404,
		'ohmylms_quiz_not_found',
		'unknown quiz'
	);

	// Another author cannot export answer keys of questions they cannot edit.
	wp_set_current_user( $author );
	ok_error(
		qx_post(
			'/questions/export',
			array(
				'format'  => 'json',
				'quiz_id' => $qx_quiz,
			)
		),
		403,
		'ohmylms_question_cannot_export',
		'other author exports a quiz they cannot edit'
	);
	ok_error(
		qx_post(
			'/questions/export',
			array(
				'format' => 'csv',
				'ids'    => $ids,
			)
		),
		403,
		'ohmylms_question_cannot_export',
		'other author selected export'
	);
	ok_error(
		qx_post(
			'/questions/export',
			array(
				'format'  => 'csv',
				'filters' => array( 'search' => $tag ),
			)
		),
		422,
		'ohmylms_question_export_empty',
		'other author sees no matching question'
	);

	fwrite( STDOUT, "OK question-exchange-rest-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	if ( ! empty( $qx_quiz ) ) {
		$wpdb->delete( $wpdb->prefix . 'ohmylms_quiz_questions_relationship', array( 'quiz_id' => $qx_quiz ), array( '%d' ) );
		wp_delete_post( $qx_quiz, true );
	}
	foreach ( qx_created() as $leftover ) {
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE question_id = %d', $wpdb->prefix . 'ohmylms_question_answers', $leftover ) ) as $answer_id ) {
			$wpdb->delete( $wpdb->prefix . 'ohmylms_question_answermeta', array( 'answer_id' => (int) $answer_id ), array( '%d' ) );
		}
		$wpdb->delete( $wpdb->prefix . 'ohmylms_question_answers', array( 'question_id' => $leftover ), array( '%d' ) );
		wp_delete_post( $leftover, true );
		if ( Schema::ready() ) {
			$wpdb->query( $wpdb->prepare( 'DELETE s FROM %i s JOIN %i v ON v.id = s.version_id WHERE v.question_id = %d', Schema::table( 'qb_version_skills' ), Schema::table( 'qb_question_versions' ), $leftover ) );
			$wpdb->delete( Schema::table( 'qb_question_versions' ), array( 'question_id' => $leftover ), array( '%d' ) );
			$wpdb->delete( Schema::table( 'qb_questions' ), array( 'question_id' => $leftover ), array( '%d' ) );
			$wpdb->delete( Schema::table( 'question_skills' ), array( 'question_id' => $leftover ), array( '%d' ) );
		}
	}
	if ( $skill_id ) {
		wp_delete_term( $skill_id, Taxonomy::NAME );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
}

exit( isset( $exit_code ) ? $exit_code : 0 );
