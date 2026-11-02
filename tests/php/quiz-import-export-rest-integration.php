<?php
/**
 * Quiz import (check, review token, import) and export REST integration test on the Local site.
 *
 * Creates tagged quizzes and questions and temporary users, and removes them in `finally`.
 *
 * Usage (set OHMYLMS_QUIZZES_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/quiz-import-export-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_QUIZZES_TEST' ) ) {
	exit( "Set OHMYLMS_QUIZZES_TEST=1 to run the quiz import/export REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Assessment\Schema;

global $wpdb;

$checks   = 0;
$user_ids = array();
$quiz_ids = array();
$tag      = 'qie' . strtolower( wp_generate_password( 6, false, false ) );

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
 * Dispatch a request below /ohmylms/v1/quiz.
 *
 * @param string $method HTTP method.
 * @param string $path   Path.
 * @param array  $data   Body.
 * @return WP_REST_Response
 */
function qie_api( $method, $path, array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/quiz' . $path );
	if ( 'GET' !== $method ) {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
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

try {
	$admins = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	);
	ok( ! empty( $admins ), 'an administrator exists' );
	$admin  = (int) $admins[0];
	$author = wp_insert_user(
		array(
			'user_login' => $tag . 'author',
			'user_pass'  => wp_generate_password( 24 ),
			'role'       => 'author',
		)
	);
	$sub    = wp_insert_user(
		array(
			'user_login' => $tag . 'sub',
			'user_pass'  => wp_generate_password( 24 ),
			'role'       => 'subscriber',
		)
	);
	ok( ! is_wp_error( $author ) && ! is_wp_error( $sub ), 'user fixtures' );
	$user_ids = array( $author, $sub );

	$file = wp_json_encode(
		array(
			'format'    => 'ohmylms.quiz',
			'version'   => 1,
			'quiz'      => array( 'name' => $tag . ' Quiz' ),
			'questions' => array(
				array(
					'name'        => $tag . ' Q1',
					'description' => '<p>What is 2 + 2?</p>',
					'settings'    => array(
						'type'  => 'single-choice',
						'score' => array(
							'enabled' => true,
							'value'   => 1,
						),
					),
					'options'     => array(
						array(
							'answer'     => '3',
							'is_correct' => false,
						),
						array(
							'answer'     => '4',
							'is_correct' => true,
						),
					),
				),
			),
		)
	);

	// Permissions.
	wp_set_current_user( 0 );
	ok( 401 === qie_api( 'POST', '/import', array( 'content' => $file ) )->get_status(), 'anonymous import refused' );
	wp_set_current_user( $sub );
	ok(
		403 === qie_api(
			'POST',
			'/import',
			array(
				'content' => $file,
				'dry_run' => true,
			)
		)->get_status(),
		'subscriber import refused'
	);
	wp_set_current_user( $admin );

	// Schema limits.
	ok_error(
		qie_api(
			'POST',
			'/import',
			array(
				'content' => $file,
				'name'    => str_repeat( 'x', 201 ),
			)
		),
		400,
		'rest_invalid_param',
		'name length'
	);
	ok_error( qie_api( 'POST', '/import', array( 'content' => str_repeat( ' ', 10485761 ) ) ), 400, 'rest_invalid_param', 'file size' );
	ok_error(
		qie_api(
			'POST',
			'/import',
			array(
				'content' => $file,
				'format'  => 'qti',
			)
		),
		400,
		'rest_invalid_param',
		'format enum'
	);
	ok_error(
		qie_api(
			'POST',
			'/import',
			array(
				'content' => $file,
				'review'  => 'x',
			)
		),
		400,
		'rest_invalid_param',
		'review format'
	);
	ok_error( qie_api( 'POST', '/import', array( 'content' => '   ' ) ), 400, 'ohmylms_quiz_import_empty', 'empty file' );

	// Check, then import exactly the checked file.
	$before = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE post_type=%s AND post_title LIKE %s', $wpdb->posts, OHMYLMS_QUIZ_CPT, $wpdb->esc_like( $tag ) . '%' ) );
	$check  = qie_api(
		'POST',
		'/import',
		array(
			'content' => $file,
			'dry_run' => true,
		)
	);
	$report = $check->get_data();
	ok( 200 === $check->get_status() && 1 === $report['summary']['questions'] && preg_match( '/^[a-f0-9]{64}$/', $report['review'] ), 'dry run summarises the file: ' . wp_json_encode( $report ) );
	ok( 'private, no-store' === ( $check->get_headers()['Cache-Control'] ?? '' ), 'import response not cacheable' );
	ok( 0 === $before, 'nothing exists before the import' );
	ok_error(
		qie_api(
			'POST',
			'/import',
			array(
				'content' => $file,
				'name'    => $tag . ' Renamed',
				'review'  => $report['review'],
			)
		),
		409,
		'ohmylms_quiz_import_review_stale',
		'a different name needs a new check'
	);
	$apply = array(
		'content' => $file,
		'review'  => $report['review'],
	);
	wp_set_current_user( $author );
	ok_error( qie_api( 'POST', '/import', $apply ), 409, 'ohmylms_quiz_import_review_stale', 'review bound to the reviewer' );
	wp_set_current_user( $admin );
	$imported = qie_api( 'POST', '/import', $apply );
	ok( 201 === $imported->get_status() && $imported->get_data()['id'] > 0, 'checked file imported' );
	$quiz       = (int) $imported->get_data()['id'];
	$quiz_ids[] = $quiz;
	ok( 'draft' === get_post_status( $quiz ), 'import creates a draft' );

	// Export.
	$export = qie_api( 'GET', '/' . $quiz . '/export' );
	ok( 200 === $export->get_status() && false !== strpos( $export->get_data()['content'], 'ohmylms.quiz' ) && str_ends_with( $export->get_data()['filename'], '.ohmylms.json' ), 'quiz exported' );
	ok( 'private, no-store' === ( $export->get_headers()['Cache-Control'] ?? '' ), 'export not cacheable' );
	wp_set_current_user( $author );
	ok( 403 === qie_api( 'GET', '/' . $quiz . '/export' )->get_status(), 'another author cannot export the answer keys' );
	wp_set_current_user( $admin );
	ok( 404 === qie_api( 'GET', '/999999999/export' )->get_status(), 'unknown quiz' );

	fwrite( STDOUT, "OK quiz-import-export-rest-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	$quiz_ids  = array_unique( array_merge( $quiz_ids, array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT ID FROM %i WHERE post_type=%s AND post_title LIKE %s', $wpdb->posts, OHMYLMS_QUIZ_CPT, $wpdb->esc_like( $tag ) . '%' ) ) ) ) );
	$questions = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT ID FROM %i WHERE post_type=%s AND post_title LIKE %s', $wpdb->posts, OHMYLMS_QUESTION_CPT, $wpdb->esc_like( $tag ) . '%' ) ) );
	foreach ( $quiz_ids as $quiz_id ) {
		$wpdb->delete( $wpdb->prefix . 'ohmylms_quiz_questions_relationship', array( 'quiz_id' => $quiz_id ), array( '%d' ) );
		wp_delete_post( $quiz_id, true );
	}
	foreach ( $questions as $leftover ) {
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
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		if ( ! is_wp_error( $user_id ) ) {
			wp_delete_user( $user_id );
		}
	}
}

exit( isset( $exit_code ) ? $exit_code : 0 );
