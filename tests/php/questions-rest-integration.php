<?php
/**
 * Questions REST API (`ohmylms/v1/questions`) integration test.
 *
 * Runs against a WordPress install (the Local site by default) with real database tables. It only
 * creates tagged questions, a tagged post and bank, and temporary users, and removes them (with their
 * answer rows and question-bank identity/version rows) in `finally`.
 *
 * Usage (set OHMYLMS_QUESTIONS_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/questions-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, Generic.Formatting.MultipleStatementAlignment, WordPress.DB.DirectDatabaseQuery, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI test script writing to STDOUT/STDERR.

use OhMyLMS\Assessment\Schema;
use OhMyLMS\QuestionBank\Banks;

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_QUESTIONS_TEST' ) ) {
	exit( "Set OHMYLMS_QUESTIONS_TEST=1 to run the questions REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

global $wpdb;

$checks       = 0;
$question_ids = array();
$post_ids     = array();
$bank_ids     = array();
$user_ids     = array();
$tag          = 'qtest' . strtolower( wp_generate_password( 6, false, false ) );

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
 * @param string $path   Path below /ohmylms/v1 (e.g. /questions/5).
 * @param mixed  $data   Body (JSON) or query (GET). A string is sent as the raw body.
 * @return WP_REST_Response
 */
function call_api( $method, $path, $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1' . $path );
	if ( 'GET' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( is_string( $data ) ? $data : wp_json_encode( $data ) );
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
 * Create a question through the API and remember it for cleanup.
 *
 * @param array $data Payload.
 * @return array Editor view.
 */
function make_question( array $data ) {
	global $question_ids;
	$response = call_api( 'POST', '/questions', $data );
	ok( 201 === $response->get_status(), 'question created: ' . wp_json_encode( $response->get_data() ) );
	$question_ids[] = (int) $response->get_data()['id'];
	return $response->get_data();
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
			'user_login'   => $tag . '_' . $suffix,
			'user_pass'    => wp_generate_password( 24 ),
			'user_email'   => $tag . '_' . $suffix . '@example.invalid',
			'display_name' => $tag . ' ' . $suffix,
			'role'         => $role,
		)
	);
	ok( ! is_wp_error( $id ), "temporary $role created" );
	$user_ids[] = $id;
	return $id;
}

/**
 * IDs of a collection response.
 *
 * @param WP_REST_Response $response Response.
 * @return int[]
 */
function ids_of( $response ) {
	return array_map( 'intval', wp_list_pluck( $response->get_data(), 'id' ) );
}

try {
	ok( Schema::ready(), 'question bank schema is installed' );
	$admins = get_users(
		array(
			'role'    => 'administrator',
			'number'  => 1,
			'orderby' => 'ID',
			'fields'  => 'ID',
		)
	);
	ok( ! empty( $admins ), 'an administrator exists' );
	$admin      = (int) $admins[0];
	$subscriber = make_test_user( 'sub', 'subscriber' );
	$author_a   = make_test_user( 'a', 'author' );
	$author_b   = make_test_user( 'b', 'author' );
	ok( ! user_can( $author_a, 'unfiltered_html' ), 'authors lack unfiltered_html' );

	// Routes and permission callbacks.
	$routes = rest_get_server()->get_routes( 'ohmylms/v1' );
	foreach ( array( '/questions', '/questions/types', '/questions/(?P<id>[\d]+)', '/questions/(?P<id>[\d]+)/archive', '/questions/(?P<id>[\d]+)/restore', '/questions/(?P<id>[\d]+)/approve', '/questions/(?P<id>[\d]+)/duplicate', '/questions/(?P<id>[\d]+)/versions', '/questions/(?P<id>[\d]+)/versions/(?P<version_id>[\d]+)' ) as $route ) {
		ok( isset( $routes[ '/ohmylms/v1' . $route ] ), 'route registered: ' . $route );
		foreach ( $routes[ '/ohmylms/v1' . $route ] as $endpoint ) {
			ok( is_callable( $endpoint['permission_callback'] ) && '__return_true' !== $endpoint['permission_callback'], "real permission_callback on $route" );
		}
	}
	$legacy_routes = array_intersect(
		array_keys( $routes ),
		array(
			'/ohmylms/v1/question',
			'/ohmylms/v1/question/(?P<id>[\d]+)',
			'/ohmylms/v1/question-bank',
			'/ohmylms/v1/question-bank/export',
			'/ohmylms/v1/question-bank/(?P<id>[\d]+)',
			'/ohmylms/v1/question-bank/(?P<id>[\d]+)/approve',
			'/ohmylms/v1/question-bank/(?P<id>[\d]+)/archive',
			'/ohmylms/v1/question-bank/(?P<id>[\d]+)/restore',
			'/ohmylms/v1/question-bank/(?P<id>[\d]+)/duplicate',
			'/ohmylms/v1/question-bank/versions/(?P<version_id>[\d]+)',
			'/ohmylms/v1/quiz/(?P<id>[\d]+)/content',
			'/ohmylms/v1/quiz/(?P<id>[\d]+)/pools',
			'/ohmylms/v1/quiz/(?P<id>[\d]+)/questions/(?P<question_id>[\d]+)',
			'/ohmylms/v1/quiz/(?P<quiz_id>[\d]+)/questions',
			'/ohmylms/v1/quiz/(?P<quiz_id>[\d]+)/questions/(?P<question_id>[\d]+)/pin',
		)
	);
	ok( array() === array_values( $legacy_routes ), 'legacy question, question-bank and quiz content/questions/pools routes are removed: ' . wp_json_encode( array_values( $legacy_routes ) ) );
	ok( isset( $routes['/ohmylms/v1/question-bank/import'], $routes['/ohmylms/v1/question-banks'] ), 'the typed import and the bank routes stay' );

	// Create validation (author A). None of these may write.
	wp_set_current_user( $author_a );
	$single = array(
		'name'     => $tag . ' invalid',
		'settings' => array( 'type' => 'single-choice' ),
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
	ok_error( call_api( 'POST', '/questions', array_diff_key( $single, array( 'name' => 1 ) ) ), 400, 'rest_missing_callback_param', 'name required' );
	ok_error( call_api( 'POST', '/questions', array( 'name' => '   ' ) + $single ), 422, 'ohmylms_question_invalid_name', 'blank name' );
	ok_error( call_api( 'POST', '/questions', array( 'name' => str_repeat( 'x', 501 ) ) + $single ), 400, 'rest_invalid_param', 'name above 500 characters' );
	ok_error( call_api( 'POST', '/questions', array_diff_key( $single, array( 'settings' => 1 ) ) ), 400, 'rest_missing_callback_param', 'settings required' );
	ok_error( call_api( 'POST', '/questions', array( 'settings' => array( 'score' => array( 'enabled' => true ) ) ) + $single ), 400, 'ohmylms_question_invalid_type', 'settings.type required' );
	ok_error( call_api( 'POST', '/questions', array( 'settings' => array( 'type' => '' ) ) + $single ), 400, 'ohmylms_question_invalid_type', 'empty settings.type' );
	ok_error( call_api( 'POST', '/questions', array( 'settings' => array( 'type' => 'no-such-type-' . $tag ) ) + $single ), 400, 'ohmylms_question_invalid_type', 'unknown settings.type' );
	$unknown = call_api( 'POST', '/questions', $single + array( 'status' => 'publish' ) );
	ok_error( $unknown, 400, 'ohmylms_question_unknown_field', 'status is never accepted' );
	ok( array( 'status' ) === $unknown->get_data()['data']['fields'], 'unknown fields are listed' );
	$one = $single;
	unset( $one['options'][1] );
	ok_error( call_api( 'POST', '/questions', $one ), 422, 'ohmylms_question_invalid_options', 'single choice with one option' );
	$two_correct                            = $single;
	$two_correct['options'][1]['is_correct'] = true;
	ok_error( call_api( 'POST', '/questions', $two_correct ), 422, 'ohmylms_question_invalid_options', 'single choice with two correct options' );
	$none_correct                            = $single;
	$none_correct['options'][0]['is_correct'] = false;
	ok_error( call_api( 'POST', '/questions', $none_correct ), 422, 'ohmylms_question_invalid_options', 'single choice without a correct option' );
	$true_false                       = $single;
	$true_false['settings']['type']   = 'true-false';
	$true_false['options'][]          = array( 'answer' => 'Maybe' );
	ok_error( call_api( 'POST', '/questions', $true_false ), 422, 'ohmylms_question_invalid_options', 'true/false with three options' );
	$multiple                           = $single;
	$multiple['settings']['type']       = 'multiple-choice';
	$multiple['options'][0]['is_correct'] = false;
	ok_error( call_api( 'POST', '/questions', $multiple ), 422, 'ohmylms_question_invalid_options', 'multiple choice without a correct option' );
	$matching                     = $single;
	$matching['settings']['type'] = 'matching';
	$matching['options'][0]['matching_data'] = array( 'label' => 'Oui' );
	$matching_error               = call_api( 'POST', '/questions', $matching );
	ok_error( $matching_error, 422, 'ohmylms_question_invalid_options', 'matching option without a label' );
	ok( 'options[1].matching_data.label' === $matching_error->get_data()['data']['field'], 'matching error names the field' );
	$extra_key                           = $single;
	$extra_key['options'][0]['secret']   = 'x';
	ok_error( call_api( 'POST', '/questions', $extra_key ), 400, 'rest_invalid_param', 'unknown option property' );
	ok_error( call_api( 'POST', '/questions', array( 'options' => array_fill( 0, 101, array( 'answer' => 'a' ) ) ) + $single ), 400, 'rest_invalid_param', 'more than 100 options' );
	ok_error( call_api( 'POST', '/questions', $single + array( 'bank' => array( 'difficulty' => 'impossible' ) ) ), 400, 'rest_invalid_param', 'difficulty enum' );
	ok_error( call_api( 'POST', '/questions', $single + array( 'thumbnail_id' => 999999999 ) ), 422, 'ohmylms_question_invalid_media', 'thumbnail must be an attachment' );
	ok_error( call_api( 'POST', '/questions', $single + array( 'extension_settings' => array( 'nope-' . $tag => 1 ) ) ), 400, 'ohmylms_question_invalid_extension_settings', 'unregistered extension settings' );
	$huge = wp_json_encode( array( 'description' => str_repeat( 'x', 300000 ) ) + $single );
	ok_error( call_api( 'POST', '/questions', $huge ), 413, 'ohmylms_question_too_large', 'body above 256 KiB' );
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_title LIKE %s", OHMYLMS_QUESTION_CPT, $wpdb->esc_like( $tag ) . '%' ) ), 'rejected creates wrote nothing' );

	// Valid create (author A).
	$alpha_payload = array(
		'name'        => $tag . ' alpha',
		'description' => '<p>Solve <strong>2x = 6</strong></p><script>alert(1)</script>',
		'settings'    => array(
			'type'  => 'single-choice',
			'score' => array(
				'enabled' => true,
				'value'   => 2,
			),
			'hint'  => '<b>Divide</b><script>alert(2)</script>',
		),
		'options'     => array(
			array(
				'answer'     => 'Three' . $tag,
				'is_correct' => true,
			),
			array(
				'answer'     => 'Twelve',
				'is_correct' => false,
			),
		),
		'bank'        => array(
			'difficulty' => 'easy',
			'source'     => 'Textbook 4.2',
		),
	);
	$created       = call_api( 'POST', '/questions', $alpha_payload );
	ok( 201 === $created->get_status(), 'valid create is 201: ' . wp_json_encode( $created->get_data() ) );
	$alpha          = $created->get_data();
	$question_ids[] = (int) $alpha['id'];
	ok( rest_url( 'ohmylms/v1/questions/' . $alpha['id'] ) === $created->get_headers()['Location'], 'Location header' );
	ok( 'private, no-store' === $created->get_headers()['Cache-Control'], 'editor view is not cacheable' );
	ok( 2 === count( $alpha['options'] ) && true === $alpha['options'][0]['is_correct'] && false === $alpha['options'][1]['is_correct'] && 'Three' . $tag === $alpha['options'][0]['answer'], 'options round-trip with correctness' );
	ok( $alpha['options'][0]['id'] > 0 && 0 === $alpha['options'][0]['image_id'] && null === $alpha['options'][0]['matching_data'], 'option shape' );
	ok( 'single-choice' === $alpha['type'] && 2 === $alpha['settings']['score']['value'] && true === $alpha['settings']['score']['enabled'], 'settings keep numbers and booleans' );
	ok( false === strpos( $alpha['settings']['hint'], '<script' ) && false !== strpos( $alpha['settings']['hint'], '<b>Divide</b>' ), 'settings strings are kses-filtered for authors' );
	ok( false === strpos( $alpha['description'], '<script' ) && false !== strpos( $alpha['description'], '<strong>' ), 'description is kses-filtered for authors' );
	ok( 'draft' === $alpha['status'] && 'publish' === $alpha['post_status'] && 1 === $alpha['version'] && $alpha['current_version_id'] > 0 && 0 === $alpha['approved_version_id'], 'new question is a draft with version 1' );
	ok( 36 === strlen( $alpha['uuid'] ) && 'easy' === $alpha['bank']['difficulty'] && 'Textbook 4.2' === $alpha['bank']['source'] && 0 === $alpha['bank']['bank_id'], 'identity and bank attributes' );
	ok( $alpha['permissions']['can_edit'] && $alpha['permissions']['can_approve'] && array() === $alpha['quizzes'] && 0 === $alpha['usage']['quizzes'], 'permissions and usage' );
	ok( preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $alpha['modified'] ), 'modified is RFC 3339 UTC' );

	wp_set_current_user( $author_b );
	$beta = make_question(
		array(
			'name'     => $tag . ' beta',
			'settings' => array( 'type' => 'multiple-choice' ),
			'options'  => array(
				array(
					'answer'     => 'Red',
					'is_correct' => true,
				),
				array(
					'answer'     => 'Blue',
					'is_correct' => true,
				),
				array( 'answer' => 'Green' ),
			),
		)
	);
	$private_bank = Banks::create( $tag . ' private bank' );
	$bank_ids[]   = (int) $private_bank['id'];

	// Anonymous: 401. Subscriber: 403. Neither writes anything.
	$alpha_path = '/questions/' . $alpha['id'];
	$denied     = array(
		array( 'GET', '/questions' ),
		array( 'POST', '/questions' ),
		array( 'GET', '/questions/types' ),
		array( 'GET', $alpha_path ),
		array( 'PATCH', $alpha_path ),
		array( 'PUT', $alpha_path ),
		array( 'POST', $alpha_path ),
		array( 'POST', $alpha_path . '/archive' ),
		array( 'POST', $alpha_path . '/restore' ),
		array( 'POST', $alpha_path . '/approve' ),
		array( 'POST', $alpha_path . '/duplicate' ),
		array( 'GET', $alpha_path . '/versions' ),
	);
	foreach ( array(
		0           => 401,
		$subscriber => 403,
	) as $user => $status ) {
		wp_set_current_user( $user );
		foreach ( $denied as $case ) {
			$body     = 'GET' === $case[0] ? array() : array(
				'name'          => $tag . ' denied',
				'settings'      => array( 'type' => 'single-choice' ),
				'base_modified' => $alpha['modified'],
			);
			$response = call_api( $case[0], $case[1], $body );
			ok( $status === $response->get_status(), "user $user {$case[0]} {$case[1]} is $status, got " . $response->get_status() );
		}
	}
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s", OHMYLMS_QUESTION_CPT, $tag . ' denied' ) ), 'forbidden writes wrote nothing' );
	ok( 'publish' === get_post_status( $alpha['id'] ), 'forbidden archive changed nothing' );
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_title LIKE %s", OHMYLMS_QUESTION_CPT, $wpdb->esc_like( $alpha['name'] . ' (copy)' ) . '%' ) ), 'forbidden duplicates wrote nothing' );
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT approved_version_id FROM %i WHERE question_id = %d', Schema::table( 'qb_questions' ), $alpha['id'] ) ), 'forbidden approve changed nothing' );

	// Object-level access: author A cannot open author B's question; the admin can.
	wp_set_current_user( $author_a );
	$beta_path = '/questions/' . $beta['id'];
	ok_error( call_api( 'GET', $beta_path ), 403, 'ohmylms_question_cannot_view', "author A reads B's question" );
	ok_error(
		call_api(
			'PATCH',
			$beta_path,
			array(
				'name'          => 'x',
				'base_modified' => $beta['modified'],
			)
		),
		403,
		'ohmylms_question_cannot_edit',
		"author A patches B's question"
	);
	ok_error( call_api( 'POST', $beta_path . '/archive' ), 403, 'ohmylms_question_cannot_edit', "author A archives B's question" );
	ok_error( call_api( 'GET', $beta_path . '/versions' ), 403, 'ohmylms_question_cannot_view', "author A reads B's versions" );
	ok( get_post_field( 'post_title', $beta['id'] ) === $beta['name'] && 'publish' === get_post_status( $beta['id'] ), "B's question unchanged" );
	ok_error( call_api( 'GET', '/questions', array( 'bank' => $private_bank['id'] ) ), 403, 'ohmylms_question_bank_forbidden', "author A filters by B's private bank" );
	wp_set_current_user( $admin );
	ok( 200 === call_api( 'GET', $beta_path )->get_status(), "admin reads B's question" );

	// 404s, checked before capabilities.
	$other_post = wp_insert_post(
		array(
			'post_type'   => 'post',
			'post_title'  => $tag . ' post',
			'post_status' => 'draft',
		)
	);
	$post_ids[] = $other_post;
	ok_error( call_api( 'GET', '/questions/' . $other_post ), 404, 'ohmylms_question_not_found', 'id of another post type' );
	ok_error( call_api( 'GET', '/questions/999999999' ), 404, 'ohmylms_question_not_found', 'unknown id' );
	wp_set_current_user( 0 );
	ok_error( call_api( 'GET', '/questions/999999999' ), 404, 'ohmylms_question_not_found', 'unknown id for a guest' );
	wp_set_current_user( $admin );
	$trashed = make_question( array( 'name' => $tag . ' trashed' ) + array_diff_key( $single, array( 'name' => 1 ) ) );
	wp_trash_post( $trashed['id'] );
	ok_error( call_api( 'GET', '/questions/' . $trashed['id'] ), 404, 'ohmylms_question_not_found', 'trashed question' );

	// List.
	wp_set_current_user( $author_a );
	$list = call_api( 'GET', '/questions', array( 'search' => $tag ) );
	ok( 200 === $list->get_status() && array( (int) $alpha['id'] ) === ids_of( $list ), "author A lists only their own question, not B's: " . wp_json_encode( ids_of( $list ) ) );
	ok( '1' === $list->get_headers()['X-WP-Total'] && '1' === $list->get_headers()['X-WP-TotalPages'], 'X-WP-Total counts only visible questions' );
	ok( 'private, no-store' === $list->get_headers()['Cache-Control'], 'list is not cacheable' );
	$row = $list->get_data()[0];
	ok( ! isset( $row['settings'] ) && ! isset( $row['options'] ) && ! isset( $row['description'] ), 'list rows carry no settings, options or body' );
	ok( false === strpos( wp_json_encode( $list->get_data() ), 'Three' . $tag ), 'list rows carry no answer text' );
	$keys = array( 'id', 'uuid', 'name', 'type', 'type_label', 'marks', 'status', 'post_status', 'version', 'current_version_id', 'approved_version_id', 'approved_is_current', 'bank_id', 'difficulty', 'source', 'family_id', 'secure', 'skills', 'usage', 'author', 'modified', 'permissions' );
	ok( array() === array_diff( $keys, array_keys( $row ) ) && array() === array_diff( array_keys( $row ), $keys ), 'summary keys: ' . implode( ',', array_keys( $row ) ) );
	ok( 'Single Choice' === $row['type_label'] && 2.0 === $row['marks'] && 1 === $row['version'] && 'easy' === $row['difficulty'], 'summary values' );
	ok(
		$author_a === $row['author']['id'] && $tag . ' a' === $row['author']['name'] && array(
			'quizzes'   => 0,
			'responses' => 0,
		) === $row['usage'],
		'summary author and usage'
	);
	ok( true === $row['permissions']['can_edit'] && $alpha['modified'] === $row['modified'], 'summary permissions and modified' );

	wp_set_current_user( $admin );
	$all = call_api( 'GET', '/questions', array( 'search' => $tag ) );
	$all_ids = ids_of( $all );
	sort( $all_ids );
	ok( '2' === $all->get_headers()['X-WP-Total'] && array( (int) $alpha['id'], (int) $beta['id'] ) === $all_ids, 'admin lists both: ' . wp_json_encode( $all_ids ) );
	$by_title = call_api(
		'GET',
		'/questions',
		array(
			'search'  => $tag,
			'orderby' => 'title',
			'order'   => 'asc',
		)
	);
	ok( array( (int) $alpha['id'], (int) $beta['id'] ) === ids_of( $by_title ), 'orderby title asc' );
	$paged = call_api(
		'GET',
		'/questions',
		array(
			'search'   => $tag,
			'per_page' => 1,
			'page'     => 2,
			'orderby'  => 'id',
			'order'    => 'asc',
		)
	);
	ok( array( (int) $beta['id'] ) === ids_of( $paged ) && '2' === $paged->get_headers()['X-WP-TotalPages'], 'paging' );
	$typed = call_api(
		'GET',
		'/questions',
		array(
			'search' => $tag,
			'type'   => 'single-choice',
		)
	);
	ok( array( (int) $alpha['id'] ) === ids_of( $typed ), 'type filter' );
	ok(
		array( (int) $alpha['id'] ) === ids_of(
			call_api(
				'GET',
				'/questions',
				array(
					'search'     => $tag,
					'difficulty' => 'easy',
				)
			)
		),
		'difficulty filter'
	);
	ok(
		2 === count(
			ids_of(
				call_api(
					'GET',
					'/questions',
					array(
						'search' => $tag,
						'bank'   => 0,
					)
				)
			)
		),
		'personal bank filter'
	);
	ok_error( call_api( 'GET', '/questions', array( 'per_page' => 101 ) ), 400, 'rest_invalid_param', 'per_page above 100' );
	ok_error( call_api( 'GET', '/questions', array( 'per_page' => 0 ) ), 400, 'rest_invalid_param', 'per_page zero' );
	ok_error(
		call_api(
			'GET',
			'/questions',
			array(
				'search' => $tag,
				'page'   => 5,
			)
		),
		400,
		'ohmylms_question_invalid_page',
		'page past the end'
	);
	ok_error( call_api( 'GET', '/questions', array( 'type' => 'no-such-type-' . $tag ) ), 400, 'rest_invalid_param', 'unknown type filter' );
	ok_error( call_api( 'GET', '/questions', array( 'status' => 'trash' ) ), 400, 'rest_invalid_param', 'status enum' );
	ok_error( call_api( 'GET', '/questions', array( 'orderby' => 'meta_value' ) ), 400, 'rest_invalid_param', 'orderby enum' );
	ok_error( call_api( 'GET', '/questions', array( 'search' => str_repeat( 'x', 101 ) ) ), 400, 'rest_invalid_param', 'search above 100 characters' );
	$empty = call_api( 'GET', '/questions', array( 'search' => $tag . ' nothing' ) );
	ok( 200 === $empty->get_status() && array() === $empty->get_data() && '0' === $empty->get_headers()['X-WP-Total'], 'empty result is 200' );

	// Filters of the native editors' pickers: secure (exam-only) and the questions of a quiz.
	wp_set_current_user( $author_a );
	$filtered = array(
		'search' => $tag,
		'secure' => false,
	);
	ok( array( (int) $alpha['id'] ) === ids_of( call_api( 'GET', '/questions', $filtered ) ), 'secure=false keeps questions that are not exam-only' );
	ok( array() === ids_of( call_api( 'GET', '/questions', array( 'secure' => true ) + $filtered ) ), 'secure=true leaves them out' );
	ok_error( call_api( 'GET', '/questions', array( 'exclude_quiz' => 0 ) ), 400, 'rest_invalid_param', 'exclude_quiz must be an ID' );
	$quiz_a     = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_QUIZ_CPT,
			'post_title'  => $tag . ' quiz',
			'post_status' => 'draft',
			'post_author' => $author_a,
		)
	);
	$post_ids[] = $quiz_a;
	\OhMyLMS\QuestionBank\DraftWriter::link( $quiz_a, (int) $alpha['id'] );
	$excluded = array(
		'search'       => $tag,
		'exclude_quiz' => $quiz_a,
	);
	ok( array() === ids_of( call_api( 'GET', '/questions', $excluded ) ), 'exclude_quiz leaves out questions already in the quiz' );
	wp_set_current_user( $author_b );
	ok_error( call_api( 'GET', '/questions', $excluded ), 403, 'ohmylms_question_quiz_forbidden', "author B excludes the questions of A's quiz" );
	ok_error( call_api( 'GET', '/questions', array( 'exclude_quiz' => $other_post ) ), 403, 'ohmylms_question_quiz_forbidden', 'exclude_quiz of a post that is not a quiz' );
	wp_set_current_user( $author_a );
	ok( ! is_wp_error( \OhMyLMS\QuestionBank\Usage::remove_from_quiz( $quiz_a, (int) $alpha['id'] ) ), 'fixture placement removed' );
	// Organisation views (docs/QUESTION-BULK-IMPORT.md): a scope narrows the list, never widens it.
	ok_error( call_api( 'GET', '/questions', array( 'organisation' => 'Not A View!' ) ), 400, 'rest_invalid_param', 'organisation is a view ID' );
	ok_error(
		call_api(
			'GET',
			'/questions',
			array(
				'organisation' => $tag,
				'scope'        => 1,
			)
		),
		400,
		'bank_scope',
		'an unknown organisation view'
	);
	$resolve_scope = static function ( $references, $view ) use ( $tag, $alpha ) {
		return $tag === $view ? array( 'skills' => (array) $alpha['skills'] ) : $references;
	};
	add_filter( 'ohmylms_question_bank_resolve_scope', $resolve_scope, 10, 2 );
	$scoped = call_api(
		'GET',
		'/questions',
		array(
			'search'       => $tag,
			'organisation' => $tag,
			'scope'        => 1,
		)
	);
	remove_filter( 'ohmylms_question_bank_resolve_scope', $resolve_scope, 10 );
	ok( 200 === $scoped->get_status() && array() === ids_of( $scoped ), 'a view without matching skills lists nothing: ' . wp_json_encode( $scoped->get_data() ) );

	// Update.
	wp_set_current_user( $author_a );
	ok_error( call_api( 'PATCH', $alpha_path, array( 'name' => $tag . ' renamed' ) ), 400, 'rest_missing_callback_param', 'PATCH without base_modified' );
	$stale = call_api(
		'PATCH',
		$alpha_path,
		array(
			'name'          => $tag . ' stale',
			'base_modified' => '2000-01-01T00:00:00Z',
		)
	);
	ok_error( $stale, 409, 'ohmylms_question_conflict', 'stale base_modified' );
	ok( $alpha['modified'] === $stale->get_data()['data']['modified'], 'conflict reports the current modified time' );
	ok( get_post_field( 'post_title', $alpha['id'] ) === $alpha['name'], 'stale PATCH changed nothing' );
	ok_error(
		call_api(
			'PATCH',
			$alpha_path,
			array(
				'base_modified' => $alpha['modified'],
				'status'        => 'draft',
			)
		),
		400,
		'ohmylms_question_unknown_field',
		'PATCH status is refused'
	);
	ok_error(
		call_api(
			'PATCH',
			$alpha_path,
			array(
				'base_modified' => $alpha['modified'],
				'options'       => array(
					array(
						'answer'     => 'Only',
						'is_correct' => true,
					),
				),
			)
		),
		422,
		'ohmylms_question_invalid_options',
		'PATCH options merged validation'
	);
	ok_error(
		call_api(
			'PATCH',
			$alpha_path,
			array(
				'base_modified' => $alpha['modified'],
				'settings'      => array( 'type' => 'true-false' ),
				'options'       => $alpha_payload['options'] + array( 2 => array( 'answer' => 'Six' ) ),
			)
		),
		422,
		'ohmylms_question_invalid_options',
		'PATCH type change validated against options'
	);
	ok( 2 === count( ohmylms_get_question( $alpha['id'] )->get_questions() ), 'rejected PATCH kept the options' );

	$renamed = call_api(
		'PATCH',
		$alpha_path,
		array(
			'name'          => $tag . ' alpha renamed',
			'base_modified' => $alpha['modified'],
		)
	);
	ok( 200 === $renamed->get_status(), 'PATCH name: ' . wp_json_encode( $renamed->get_data() ) );
	$renamed = $renamed->get_data();
	ok( $tag . ' alpha renamed' === $renamed['name'], 'name changed' );
	ok( wp_list_pluck( $alpha['options'], 'id' ) === wp_list_pluck( $renamed['options'], 'id' ) && wp_list_pluck( $alpha['options'], 'is_correct' ) === wp_list_pluck( $renamed['options'], 'is_correct' ), 'PATCH name keeps the options' );
	ok( $alpha['settings'] === $renamed['settings'] && $alpha['description'] === $renamed['description'] && $alpha['bank'] === $renamed['bank'], 'PATCH name keeps settings, body and bank' );
	ok( 2 === $renamed['version'] && $renamed['current_version_id'] !== $alpha['current_version_id'], 'content change creates version 2' );

	$base     = $renamed['modified'];
	$replaced = call_api(
		'PUT',
		$alpha_path,
		array(
			'base_modified' => $base,
			'options'       => array(
				array(
					'id'         => $renamed['options'][0]['id'],
					'answer'     => 'Three' . $tag,
					'is_correct' => true,
				),
				array(
					'answer'       => 'Six',
					'order_number' => 5,
				),
				array( 'answer' => 'Nine' ),
			),
		)
	);
	ok( 200 === $replaced->get_status(), 'PUT options: ' . wp_json_encode( $replaced->get_data() ) );
	$replaced = $replaced->get_data();
	ok( 3 === count( $replaced['options'] ) && $renamed['options'][0]['id'] === $replaced['options'][0]['id'] && ! in_array( $renamed['options'][1]['id'], wp_list_pluck( $replaced['options'], 'id' ), true ), 'options replace the set and keep sent ids' );
	ok( 3 === $replaced['version'] && $tag . ' alpha renamed' === $replaced['name'], 'option change creates version 3 and keeps the name' );
	$same = call_api( 'POST', $alpha_path, array( 'base_modified' => $replaced['modified'] ) );
	ok( 200 === $same->get_status() && 3 === $same->get_data()['version'], 'POST with nothing to change keeps the version' );

	// Versions.
	$versions = call_api( 'GET', $alpha_path . '/versions' );
	ok( 200 === $versions->get_status() && 3 === count( $versions->get_data() ) && '3' === $versions->get_headers()['X-WP-Total'], 'versions are listed' );
	$latest = $versions->get_data()[0];
	ok( array( 'id', 'version_no', 'created_at', 'created_by', 'is_current', 'is_approved' ) === array_keys( $latest ), 'version shape has no content or hashes' );
	ok( 3 === $latest['version_no'] && $latest['is_current'] && ! $latest['is_approved'] && $author_a === $latest['created_by']['id'] && preg_match( '/Z$/', $latest['created_at'] ), 'newest version first' );
	ok( 1 === count( array_filter( wp_list_pluck( $versions->get_data(), 'is_current' ) ) ), 'exactly one current version' );
	ok(
		array( 2 ) === wp_list_pluck(
			call_api(
				'GET',
				$alpha_path . '/versions',
				array(
					'per_page' => 1,
					'page'     => 2,
				)
			)->get_data(),
			'version_no'
		),
		'versions are paged'
	);
	ok_error( call_api( 'GET', $alpha_path . '/versions', array( 'page' => 9 ) ), 400, 'ohmylms_question_invalid_page', 'versions page past the end' );

	// Content of one version: editors only, never another question's version.
	$history = $versions->get_data();
	$first   = end( $history );
	$content = call_api( 'GET', $alpha_path . '/versions/' . $first['id'] );
	$data    = $content->get_data();
	ok( 200 === $content->get_status() && 1 === (int) $data['version_no'] && (int) $alpha['id'] === (int) $data['question_id'] && 'private, no-store' === $content->get_headers()['Cache-Control'], 'version content is readable by its editor: ' . wp_json_encode( $data ) );
	ok( $tag . ' alpha' === $data['title'] && 'single-choice' === $data['settings']['type'] && 2 === count( $data['options'] ) && ! isset( $data['content_hash'] ) && is_array( $data['skills'] ), 'version content: title, settings, options with answers, skills, no hash' );
	ok_error( call_api( 'GET', $alpha_path . '/versions/999999999' ), 404, 'ohmylms_version_missing', 'unknown version' );
	ok_error( call_api( 'GET', $alpha_path . '/versions/' . $beta['current_version_id'] ), 404, 'ohmylms_version_missing', "a version of another question through A's question" );
	wp_set_current_user( $author_b );
	ok_error( call_api( 'GET', $alpha_path . '/versions/' . $first['id'] ), 403, 'ohmylms_question_cannot_view', "author B reads A's version content" );
	wp_set_current_user( 0 );
	ok_error( call_api( 'GET', $alpha_path . '/versions/' . $first['id'] ), 401, 'ohmylms_question_cannot_view', 'a guest reads version content' );
	wp_set_current_user( $author_a );

	// Extension settings are validated by their schema, merged, and versioned with the content.
	$extension_id = $tag . '-ext';
	ohmylms_register_extension_settings(
		$extension_id,
		array(
			'label'    => 'Test extension',
			'contexts' => array( 'question' ),
			'schema'   => array(
				'type'       => 'object',
				'properties' => array(
					'level' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
			),
		)
	);
	$current = call_api( 'GET', $alpha_path )->get_data();
	ok_error(
		call_api(
			'PATCH',
			$alpha_path,
			array(
				'base_modified'      => $current['modified'],
				'extension_settings' => array( $extension_id => array( 'level' => 0 ) ),
			)
		),
		400,
		'ohmylms_question_invalid_extension_settings',
		'extension settings follow their schema'
	);
	$extended = call_api(
		'PATCH',
		$alpha_path,
		array(
			'base_modified'      => $current['modified'],
			'extension_settings' => array( $extension_id => array( 'level' => 3 ) ),
		)
	);
	ok( 200 === $extended->get_status() && array( 'level' => 3 ) === $extended->get_data()['extension_settings'][ $extension_id ], 'extension settings saved: ' . wp_json_encode( $extended->get_data() ) );
	ok( $current['version'] + 1 === $extended->get_data()['version'], 'an extension change is a new version' );
	$frozen = $wpdb->get_var( $wpdb->prepare( 'SELECT extension FROM %i WHERE id = %d', Schema::table( 'qb_question_versions' ), $extended->get_data()['current_version_id'] ) );
	ok( 3 === ( json_decode( (string) $frozen, true )[ $extension_id ]['level'] ?? null ), 'the version froze the extension settings' );
	$with_name = call_api(
		'PATCH',
		$alpha_path,
		array(
			'base_modified' => $extended->get_data()['modified'],
			'name'          => $tag . ' alpha renamed',
		)
	);
	ok( 200 === $with_name->get_status() && array( 'level' => 3 ) === $with_name->get_data()['extension_settings'][ $extension_id ] && $extended->get_data()['version'] === $with_name->get_data()['version'], 'omitted extension settings are kept; an unchanged save reuses the version' );

	// Archive and restore.
	ok_error( call_api( 'POST', $alpha_path . '/archive', array( 'reason' => 'x' ) ), 400, 'ohmylms_question_unknown_field', 'archive takes no body fields' );
	$archived = call_api( 'POST', $alpha_path . '/archive' );
	ok( 200 === $archived->get_status() && 'archived' === $archived->get_data()['status'] && 'ohmylms_archived' === $archived->get_data()['post_status'], 'archive' );
	ok( array() === ids_of( call_api( 'GET', '/questions', array( 'search' => $tag ) ) ), 'archived question leaves the default list' );
	ok(
		array( (int) $alpha['id'] ) === ids_of(
			call_api(
				'GET',
				'/questions',
				array(
					'search' => $tag,
					'status' => 'archived',
				)
			)
		),
		'status=archived lists it'
	);
	ok( 200 === call_api( 'GET', $alpha_path )->get_status(), 'archived question can still be opened' );
	$restored = call_api( 'POST', $alpha_path . '/restore' );
	ok( 200 === $restored->get_status() && 'draft' === $restored->get_data()['status'] && 'publish' === $restored->get_data()['post_status'], 'restore' );
	ok(
		array( (int) $alpha['id'] ) === ids_of(
			call_api(
				'GET',
				'/questions',
				array(
					'search' => $tag,
					'status' => 'draft',
				)
			)
		),
		'restored question is listed as a draft'
	);

	// Types.
	$types = call_api( 'GET', '/questions/types' );
	$by_id = array_column( $types->get_data(), null, 'id' );
	ok( 200 === $types->get_status() && isset( $by_id['single-choice'], $by_id['graded-reader'] ), 'types include single-choice and graded-reader' );
	ok(
		array(
			'id'       => 'single-choice',
			'label'    => 'Single Choice',
			'format'   => 'ohmylms-options',
			'answers'  => 'options',
			'manual'   => false,
			'snapshot' => true,
			'builtin'  => true,
		) === $by_id['single-choice'],
		'built-in type shape'
	);
	ok( 'ohmylms-settings' === $by_id['graded-reader']['format'] && 'text' === $by_id['graded-reader']['answers'] && false === $by_id['graded-reader']['builtin'], 'add-on type is not built-in' );
	ok( true === $by_id['short-text']['manual'] && 'text' === $by_id['short-text']['answers'], 'manual type' );
	$labels = wp_list_pluck( $types->get_data(), 'label' );
	$sorted = $labels;
	usort( $sorted, 'strnatcasecmp' );
	ok( $labels === $sorted, 'types are sorted by label' );

	// Reads never write: a question without an identity row stays without one.
	wp_set_current_user( $admin );
	$legacy_id      = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_QUESTION_CPT,
			'post_title'  => $tag . ' legacy',
			'post_status' => 'publish',
		)
	);
	$question_ids[] = $legacy_id;
	$wpdb->delete( Schema::table( 'qb_questions' ), array( 'question_id' => $legacy_id ), array( '%d' ) );
	$legacy_view = call_api( 'GET', '/questions/' . $legacy_id );
	ok( 200 === $legacy_view->get_status() && '' === $legacy_view->get_data()['uuid'] && 0 === $legacy_view->get_data()['version'] && 'standard' === $legacy_view->get_data()['bank']['difficulty'], 'question without identity reads with defaults' );
	call_api( 'GET', '/questions', array( 'search' => $tag . ' legacy' ) );
	call_api( 'GET', '/questions/' . $legacy_id . '/versions' );
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE question_id = %d', Schema::table( 'qb_questions' ), $legacy_id ) ), 'reads created no identity row' );

	// A user who may not edit a question never receives its answer keys.
	wp_set_current_user( $author_b );
	$seen = array(
		call_api( 'GET', $alpha_path ),
		call_api( 'GET', $alpha_path . '/versions' ),
		call_api( 'GET', '/questions', array( 'search' => $tag ) ),
		call_api(
			'GET',
			'/questions',
			array(
				'search' => $tag,
				'status' => 'archived',
			)
		),
		call_api( 'POST', $alpha_path . '/archive' ),
		call_api( 'PATCH', $alpha_path, array( 'base_modified' => gmdate( 'Y-m-d\TH:i:s\Z' ) ) ),
		call_api( 'GET', $alpha_path . '/versions/' . $alpha['current_version_id'] ),
		call_api( 'DELETE', $alpha_path ),
	);
	$dump = wp_json_encode(
		array_map(
			static function ( $response ) {
				return $response->get_data();
			},
			$seen
		)
	);
	ok( false === strpos( $dump, 'Three' . $tag ) && false === strpos( $dump, 'Divide' ), 'no answer text or settings reach a user without access' );
	ok( 403 === $seen[0]->get_status() && 403 === $seen[1]->get_status() && 403 === $seen[6]->get_status() && 403 === $seen[7]->get_status(), 'item reads and deletes are refused' );
	ok( 'publish' === get_post_status( $alpha['id'] ), 'the refused delete changed nothing' );
	ok( ! in_array( (int) $alpha['id'], ids_of( $seen[2] ), true ), "A's question is not listed for B" );

	// Approve: 404 before capabilities, object-level approve rights, then the legacy approval logic.
	$approve_path = $alpha_path . '/approve';
	wp_set_current_user( 0 );
	ok_error( call_api( 'POST', '/questions/999999999/approve' ), 404, 'ohmylms_question_not_found', 'approve unknown id for a guest' );
	ok_error( call_api( 'POST', '/questions/999999999/duplicate' ), 404, 'ohmylms_question_not_found', 'duplicate unknown id for a guest' );
	wp_set_current_user( $author_b );
	ok_error( call_api( 'POST', $approve_path ), 403, 'ohmylms_question_cannot_approve', "author B approves A's question" );
	ok( 404 === call_api( 'POST', '/question-bank/' . $alpha['id'] . '/approve' )->get_status(), 'the legacy approve route is gone' );
	wp_set_current_user( $author_a );
	$before = call_api( 'GET', $alpha_path )->get_data();
	ok( 0 === $before['approved_version_id'] && 'draft' === $before['status'], 'alpha starts unapproved' );
	ok_error( call_api( 'POST', $approve_path, array( 'reason' => 'x' ) ), 400, 'ohmylms_question_unknown_field', 'approve takes only version_id' );
	ok_error( call_api( 'POST', $approve_path, array( 'version_id' => 0 ) ), 400, 'rest_invalid_param', 'version_id must be positive' );
	ok_error( call_api( 'POST', $approve_path, array( 'version_id' => 999999999 ) ), 404, 'ohmylms_version_missing', 'unknown version' );
	ok_error( call_api( 'POST', $approve_path, array( 'version_id' => $beta['current_version_id'] ) ), 404, 'ohmylms_version_missing', "a version of B's question" );
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT approved_version_id FROM %i WHERE question_id = %d', Schema::table( 'qb_questions' ), $alpha['id'] ) ), 'rejected approvals changed nothing' );
	$approved = call_api( 'POST', $approve_path );
	ok( 200 === $approved->get_status() && 'private, no-store' === $approved->get_headers()['Cache-Control'], 'approve: ' . wp_json_encode( $approved->get_data() ) );
	$approved = $approved->get_data();
	ok( isset( $approved['settings'], $approved['options'], $approved['permissions']['can_approve'] ), 'approve returns the editor view' );
	ok( 'approved' === $approved['status'] && $approved['approved_version_id'] === $approved['current_version_id'] && true === $approved['approved_is_current'], 'approve sets approved_version_id to the current version' );
	ok( $before['version'] === $approved['version'] && $before['current_version_id'] === $approved['current_version_id'], 'approving unchanged content reuses the current version' );
	ok( 'approved' === $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM %i WHERE question_id = %d', Schema::table( 'qb_questions' ), $alpha['id'] ) ), 'identity row status is approved' );
	ok( true === call_api( 'GET', $alpha_path . '/versions' )->get_data()[0]['is_approved'], 'the current version is marked approved' );
	$newer = call_api(
		'PATCH',
		$alpha_path,
		array(
			'base_modified' => $approved['modified'],
			'name'          => $tag . ' alpha approved then edited',
		)
	)->get_data();
	ok( 'approved' === $newer['status'] && $approved['approved_version_id'] === $newer['approved_version_id'] && false === $newer['approved_is_current'], 'an edit after approval keeps the approved version and is not current' );
	$older = call_api( 'POST', $approve_path, array( 'version_id' => $before['current_version_id'] ) )->get_data();
	ok( $before['current_version_id'] === $older['approved_version_id'] && false === $older['approved_is_current'], 'version_id approves that version' );
	wp_set_current_user( $admin );
	$review = \OhMyLMS\QuestionBank\ReviewQueue::approve( (int) $alpha['id'] );
	ok( true === $review && true === call_api( 'GET', $alpha_path )->get_data()['approved_is_current'], 'the review queue approves the current version through the typed route: ' . wp_json_encode( $review ) );

	// A bank grant of edit (not approve): the user may edit and duplicate, but not approve.
	wp_set_current_user( $author_b );
	$gamma = make_question(
		array(
			'name'     => $tag . ' gamma',
			'settings' => array( 'type' => 'true-false' ),
			'options'  => array(
				array(
					'answer'     => 'True',
					'is_correct' => true,
				),
				array( 'answer' => 'False' ),
			),
			'bank'     => array( 'bank_id' => (int) $private_bank['id'] ),
		)
	);
	ok( (int) $private_bank['id'] === $gamma['bank']['bank_id'], 'gamma is in the private bank' );
	ok( is_array( Banks::grant( $private_bank['id'], $author_a, 'edit' ) ), 'author A gets an edit grant' );
	wp_set_current_user( $author_a );
	$gamma_path = '/questions/' . $gamma['id'];
	$gamma_view = call_api( 'GET', $gamma_path );
	ok( 200 === $gamma_view->get_status() && true === $gamma_view->get_data()['permissions']['can_edit'] && false === $gamma_view->get_data()['permissions']['can_approve'], 'edit grant: can edit, cannot approve' );
	ok_error( call_api( 'POST', $gamma_path . '/approve' ), 403, 'ohmylms_question_cannot_approve', 'edit grant cannot approve' );
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT approved_version_id FROM %i WHERE question_id = %d', Schema::table( 'qb_questions' ), $gamma['id'] ) ), 'denied approve changed nothing' );

	// Duplicate.
	$source = call_api( 'GET', $alpha_path )->get_data();
	ok_error( call_api( 'POST', $alpha_path . '/duplicate', array( 'quiz_id' => 1 ) ), 400, 'ohmylms_question_unknown_field', 'duplicate takes no body fields' );
	$duplicated = call_api( 'POST', $alpha_path . '/duplicate' );
	ok( 201 === $duplicated->get_status(), 'duplicate is 201: ' . wp_json_encode( $duplicated->get_data() ) );
	$copy           = $duplicated->get_data();
	$question_ids[] = (int) $copy['id'];
	ok( rest_url( 'ohmylms/v1/questions/' . $copy['id'] ) === $duplicated->get_headers()['Location'] && 'private, no-store' === $duplicated->get_headers()['Cache-Control'], 'duplicate Location and Cache-Control' );
	ok( $copy['id'] !== $source['id'] && 36 === strlen( $copy['uuid'] ) && $copy['uuid'] !== $source['uuid'], 'the copy has a new id and a different uuid' );
	ok( $source['name'] . ' (copy)' === $copy['name'] && $source['description'] === $copy['description'] && $source['type'] === $copy['type'] && $source['settings'] === $copy['settings'], 'the copy has the same content' );
	$strip = static function ( $options ) {
		return array_map(
			static function ( $option ) {
				unset( $option['id'] );
				return $option;
			},
			$options
		);
	};
	ok( $strip( $source['options'] ) === $strip( $copy['options'] ) && ! array_intersect( wp_list_pluck( $source['options'], 'id' ), wp_list_pluck( $copy['options'], 'id' ) ), 'the copy has the same options as new rows' );
	ok( $source['bank']['difficulty'] === $copy['bank']['difficulty'] && $source['bank']['source'] === $copy['bank']['source'] && '' !== $copy['bank']['family_id'], 'the copy keeps the bank attributes' );
	ok( call_api( 'GET', $alpha_path )->get_data()['bank']['family_id'] === $copy['bank']['family_id'], 'source and copy share a family' );
	ok( 'draft' === $copy['status'] && 1 === $copy['version'] && 0 === $copy['approved_version_id'] && true === $copy['permissions']['can_edit'] && (int) get_post_field( 'post_author', $copy['id'] ) === $author_a, 'the copy is an unapproved draft owned by the user' );
	$gamma_copy = call_api( 'POST', $gamma_path . '/duplicate' );
	ok( 201 === $gamma_copy->get_status() && (int) $private_bank['id'] === $gamma_copy->get_data()['bank']['bank_id'], 'a bank editor duplicates into the bank: ' . wp_json_encode( $gamma_copy->get_data() ) );
	$question_ids[] = (int) $gamma_copy->get_data()['id'];
	wp_set_current_user( $author_b );
	ok_error( call_api( 'POST', $alpha_path . '/duplicate' ), 403, 'ohmylms_question_cannot_duplicate', "author B duplicates A's question" );
	wp_set_current_user( $author_a );

	// The blank document of the native editor (an auto-draft): only its author reads and writes it.
	$blank_id       = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_QUESTION_CPT,
			'post_title'  => $tag . ' blank',
			'post_status' => 'auto-draft',
			'post_author' => $author_a,
		)
	);
	$question_ids[] = $blank_id;
	$blank_path     = '/questions/' . $blank_id;
	$blank          = call_api( 'GET', $blank_path );
	ok( 200 === $blank->get_status() && 'auto-draft' === $blank->get_data()['post_status'] && array() === (array) $blank->get_data()['settings'] && preg_match( '/Z$/', $blank->get_data()['modified'] ), 'the author reads the blank document: ' . wp_json_encode( $blank->get_data() ) );
	ok_error( call_api( 'GET', $blank_path . '/versions' ), 404, 'ohmylms_question_not_found', 'the blank document has no other routes' );
	ok_error( call_api( 'POST', $blank_path . '/approve' ), 404, 'ohmylms_question_not_found', 'the blank document cannot be approved' );
	wp_set_current_user( $admin );
	ok_error( call_api( 'GET', $blank_path ), 404, 'ohmylms_question_not_found', "another user's blank document is not found" );
	wp_set_current_user( $author_a );
	$filled = call_api(
		'PATCH',
		$blank_path,
		array(
			'base_modified' => $blank->get_data()['modified'],
			'name'          => $tag . ' blank filled',
			'settings'      => array( 'type' => 'single-choice' ),
			'options'       => $single['options'],
		)
	);
	ok( 200 === $filled->get_status() && 'draft' === get_post_status( $blank_id ) && 'draft' === $filled->get_data()['post_status'] && 1 === $filled->get_data()['version'], 'the first save makes the blank document a draft question: ' . wp_json_encode( $filled->get_data() ) );

	// Delete: only questions nothing references; edit grants never delete.
	$doomed = make_question( array( 'name' => $tag . ' doomed' ) + array_diff_key( $single, array( 'name' => 1 ) ) );
	ok_error( call_api( 'DELETE', '/questions/' . $doomed['id'], array( 'force' => true ) ), 400, 'ohmylms_question_unknown_field', 'delete takes no body fields' );
	ok_error( call_api( 'DELETE', $gamma_path ), 403, 'ohmylms_question_cannot_delete', 'an edit grant cannot delete' );
	$quiz_b     = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_QUIZ_CPT,
			'post_title'  => $tag . ' quiz b',
			'post_status' => 'draft',
			'post_author' => $author_a,
		)
	);
	$post_ids[] = $quiz_b;
	\OhMyLMS\QuestionBank\DraftWriter::link( $quiz_a, (int) $doomed['id'] );
	\OhMyLMS\QuestionBank\DraftWriter::link( $quiz_b, (int) $doomed['id'] );
	ok_error( call_api( 'DELETE', '/questions/' . $doomed['id'] ), 409, 'ohmylms_question_shared', 'a question in two quizzes is not deleted' );
	\OhMyLMS\QuestionBank\Usage::remove_from_quiz( $quiz_b, (int) $doomed['id'] );
	$deleted = call_api( 'DELETE', '/questions/' . $doomed['id'] );
	ok( 200 === $deleted->get_status() && true === $deleted->get_data()['deleted'] && false === $deleted->get_data()['archived'] && (int) $doomed['id'] === $deleted->get_data()['previous']['id'], 'delete answers deleted and the previous editor view: ' . wp_json_encode( $deleted->get_data() ) );
	ok( null === get_post( $doomed['id'] ) && 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE question_id = %d', $wpdb->prefix . 'ohmylms_quiz_questions_relationship', $doomed['id'] ) ), 'the question and its placement are gone' );
	ok_error( call_api( 'DELETE', '/questions/' . $doomed['id'] ), 404, 'ohmylms_question_not_found', 'a deleted question is not found' );

	// Schema.
	$schema = rest_do_request( new WP_REST_Request( 'OPTIONS', '/ohmylms/v1/questions' ) )->get_data()['schema'];
	ok( 'http://json-schema.org/draft-04/schema#' === $schema['$schema'] && 'question' === $schema['title'] && isset( $schema['properties']['options'], $schema['properties']['modified'] ), 'schema header' );

	fwrite( STDOUT, "OK questions-rest-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	// Remove only what this run created.
	$question_ids = array_map( 'intval', $question_ids );
	$question_ids = array_unique( array_merge( $question_ids, array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title LIKE %s", OHMYLMS_QUESTION_CPT, $wpdb->esc_like( $tag ) . '%' ) ) ) ) );
	foreach ( $question_ids as $leftover ) {
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
	foreach ( $post_ids as $post_id ) {
		wp_delete_post( $post_id, true );
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
