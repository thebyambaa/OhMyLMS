<?php
/**
 * Native standalone question permissions, shell isolation and quiz configuration.
 *
 * @package OhMyLMS
 */

$native_site = getenv( 'OHMYLMS_TEST_SITE_PATH' );
if ( 'cli' !== PHP_SAPI || ! $native_site || ! is_file( $native_site . '/wp-load.php' ) ) {
	throw new RuntimeException( 'Set OHMYLMS_TEST_SITE_PATH to the disposable migration installation.' );
}
require $native_site . '/wp-load.php';
if ( ! defined( 'OHMYLMS_TEST_SITE' ) || ! OHMYLMS_TEST_SITE || 'ohmylms_gutenberg_migration_20261020' !== DB_NAME ) {
	throw new RuntimeException( 'This test requires the dedicated migration database.' );
}

use OhMyLMS\QuestionBank\Banks;
use OhMyLMS\QuestionBank\AccessPolicy;

/**
 * Assert a real REST boundary.
 *
 * @param bool   $condition Expected result.
 * @param string $message Failure message.
 * @throws RuntimeException When a boundary fails.
 */
function native_question_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
	++$GLOBALS['native_question_checks'];
}

/**
 * Dispatch a test request through the registered permission callbacks.
 *
 * @param string $method HTTP method.
 * @param string $route REST route.
 * @param array  $data Parameters.
 * @return WP_REST_Response Response.
 */
function native_question_request( $method, $route, $data = array() ) {
	$request = new WP_REST_Request( $method, $route );
	if ( 'GET' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_body_params( $data );
	}
	return rest_do_request( $request );
}

$GLOBALS['native_question_checks'] = 0;
$admin                             = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
	)
)[0];
wp_set_current_user( $admin->ID );
$native_question_posts = array();
$users                 = array();

try {
	$native_question_type = get_post_type_object( 'ohmylms-question' );
	native_question_check( $native_question_type->show_ui && $native_question_type->show_in_rest && ! $native_question_type->publicly_queryable && use_block_editor_for_post_type( $native_question_type->name ), 'The standalone editor is not enabled with private delivery.' );
	$question = native_question_request(
		'POST',
		'/ohmylms/v1/question',
		array(
			'name'        => 'Native standalone security fixture',
			'description' => '<p>Original private statement.</p>',
			'settings'    => array(
				'type'  => 'single-choice',
				'score' => array(
					'enabled' => true,
					'value'   => 1,
				),
			),
			'questions'   => array(
				array(
					'answer'     => 'Correct private answer',
					'is_correct' => true,
				),
				array(
					'answer'     => 'Other answer',
					'is_correct' => false,
				),
			),
		)
	);
	native_question_check( 201 === $question->get_status(), 'Question creation failed.' );
	$question_id             = (int) $question->get_data()['id'];
	$native_question_posts[] = $question_id;
	$route                   = '/wp/v2/ohmylms-questions/' . $question_id;
	$before                  = get_post( $question_id )->to_array();
	$read                    = native_question_request( 'GET', $route, array( 'context' => 'edit' ) );
	native_question_check( 200 === $read->get_status(), 'The author cannot read the native question shell.' );
	native_question_check( str_contains( $read->get_data()['content']['raw'], '"questionId":' . $question_id ) && ! str_contains( $read->get_data()['content']['raw'], 'Correct private answer' ), 'Native content did not remain reference-only.' );
	native_question_check( get_post( $question_id )->to_array() === $before, 'Opening the editor changed the stored question.' );
	$saved = native_question_request(
		'POST',
		$route,
		array(
			'title'   => 'Bypass',
			'content' => '<p>Attempt to overwrite the statement.</p>',
		)
	);
	native_question_check( 200 === $saved->get_status(), 'Saving the native shell failed.' );
	native_question_check( get_post_field( 'post_title', $question_id ) === $before['post_title'] && get_post_field( 'post_content', $question_id ) === $before['post_content'], 'Core saving bypassed the question writer.' );
	$detail = native_question_request( 'GET', '/ohmylms/v1/question-bank/' . $question_id );
	native_question_check( str_contains( $detail->get_data()['native_editor_url'], 'post.php?post=' . $question_id ), 'The bank did not expose native navigation.' );

	$blank_id                = wp_insert_post(
		array(
			'post_type'   => 'ohmylms-question',
			'post_status' => 'auto-draft',
			'post_author' => $admin->ID,
		)
	);
	$native_question_posts[] = $blank_id;
	native_question_check( 400 === native_question_request( 'POST', '/wp/v2/ohmylms-questions/' . $blank_id, array( 'status' => 'publish' ) )->get_status(), 'A blank native question was publishable.' );
	$blank = native_question_request( 'GET', '/ohmylms/v1/question-bank/' . $blank_id );
	native_question_check( 200 === $blank->get_status() && empty( $blank->get_data()['settings']['type'] ), 'The new native document cannot initialize its shared controls.' );

	$quiz                    = native_question_request(
		'POST',
		'/ohmylms/v1/quiz',
		array(
			'name'   => 'Native settings fixture',
			'status' => 'draft',
		)
	);
	$quiz_id                 = (int) $quiz->get_data()['id'];
	$native_question_posts[] = $quiz_id;
	$quiz_route              = '/wp/v2/ohmylms-quizzes/' . $quiz_id;
	$config                  = array(
		'settings'      => array(
			'allow_attempts'    => 3,
			'extension_setting' => array( 'keep' => true ),
		),
		'drip_settings' => array(
			'enable' => true,
			'type'   => 'enrollment-from-x-days',
			'days'   => 4,
		),
	);
	$result                  = native_question_request( 'POST', $quiz_route, array( 'ohmylms_quiz' => $config ) );
	native_question_check( 200 === $result->get_status() && 3 === get_post_meta( $quiz_id, '_quiz_settings', true )['allow_attempts'], 'Native quiz settings did not use existing storage.' );
	native_question_check( true === get_post_meta( $quiz_id, '_quiz_settings', true )['extension_setting']['keep'] && 4 === get_post_meta( $quiz_id, '_drip_settings', true )['days'], 'Unknown extension settings or release rules were lost.' );
	$invalid = native_question_request(
		'POST',
		$quiz_route,
		array(
			'title'        => 'Should roll back',
			'ohmylms_quiz' => array(
				'settings'  => array( 'allow_attempts' => 9 ),
				'skill_ids' => array( 2147483647 ),
			),
		)
	);
	native_question_check( 400 === $invalid->get_status() && 3 === get_post_meta( $quiz_id, '_quiz_settings', true )['allow_attempts'] && 'Native settings fixture' === get_the_title( $quiz_id ), 'A failed native settings save did not roll back atomically.' );

	$bank       = Banks::create( 'Native grant fixture', 0, 'shared' );
	$bank_id    = (int) $bank['id'];
	$attributes = native_question_request( 'PUT', '/ohmylms/v1/question/' . $question_id, array( 'bank' => array( 'bank_id' => $bank_id ) ) );
	native_question_check( 200 === $attributes->get_status(), 'The bank membership fixture could not be saved: ' . wp_json_encode( $attributes->get_data() ) );
	foreach ( array( 'edit', 'use', 'unrelated' ) as $grant_level ) {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'native-' . $grant_level . '-' . wp_generate_password( 8, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => 'author',
			)
		);
		$users[] = $user_id;
		if ( 'unrelated' !== $grant_level ) {
			Banks::grant( $bank_id, $user_id, $grant_level );
		}
		wp_set_current_user( $user_id );
		$response = native_question_request( 'GET', $route, array( 'context' => 'edit' ) );
		native_question_check( ( 'edit' === $grant_level ? 200 : 403 ) === $response->get_status(), 'Native reads did not respect the bank grant: ' . $grant_level );
		native_question_check( ! AccessPolicy::can_approve_question( $question_id ), 'An edit/use grant accidentally became an approval grant.' );
		if ( 'edit' === $grant_level ) {
			native_question_check( current_user_can( 'edit_post', $question_id ) && ! current_user_can( 'delete_post', $question_id ), 'Core capability mapping did not preserve the bank edit boundary.' );
			native_question_check( 200 === native_question_request( 'POST', $route, array( 'content' => 'Ignored shell' ) )->get_status(), 'A granted editor cannot save its native shell.' );
		}
		wp_set_current_user( $admin->ID );
	}
	wp_set_current_user( 0 );
	native_question_check( 401 === native_question_request( 'GET', $route )->get_status(), 'Anonymous native question content was exposed.' );
	native_question_check( 401 === native_question_request( 'GET', '/wp/v2/ohmylms-questions' )->get_status(), 'Anonymous collection access was exposed.' );
	native_question_check( 401 === native_question_request( 'POST', $route, array( 'content' => 'unauthorized' ) )->get_status(), 'Anonymous question writes were accepted.' );
	echo (int) $GLOBALS['native_question_checks'] . " native question integration checks passed.\n";
} finally {
	wp_set_current_user( $admin->ID );
	foreach ( array_reverse( $native_question_posts ) as $cleanup_post_id ) {
		wp_delete_post( $cleanup_post_id, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $user_id ) {
		wp_delete_user( $user_id );
	}
	wp_set_current_user( 0 );
}
