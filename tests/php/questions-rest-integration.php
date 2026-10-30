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
	foreach ( array( '/questions', '/questions/types', '/questions/(?P<id>[\d]+)', '/questions/(?P<id>[\d]+)/archive', '/questions/(?P<id>[\d]+)/restore', '/questions/(?P<id>[\d]+)/versions' ) as $route ) {
		ok( isset( $routes[ '/ohmylms/v1' . $route ] ), 'route registered: ' . $route );
		foreach ( $routes[ '/ohmylms/v1' . $route ] as $endpoint ) {
			ok( is_callable( $endpoint['permission_callback'] ) && '__return_true' !== $endpoint['permission_callback'], "real permission_callback on $route" );
		}
	}
	ok( isset( $routes['/ohmylms/v1/question'], $routes['/ohmylms/v1/question-bank'] ), 'legacy routes still registered' );

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

	// The legacy /question-bank list (now backed by the same Search service) is unchanged.
	foreach ( array( $admin, $author_a ) as $user ) {
		wp_set_current_user( $user );
		$legacy = call_api( 'GET', '/question-bank', array( 'search' => $tag ) );
		$typed  = call_api( 'GET', '/questions', array( 'search' => $tag ) );
		$data   = $legacy->get_data();
		ok( 200 === $legacy->get_status() && array_map( 'intval', wp_list_pluck( $data['items'], 'id' ) ) === ids_of( $typed ), "legacy list returns the same ids for user $user" );
		ok( (int) $typed->get_headers()['X-WP-Total'] === $data['total'] && 1 === $data['page'] && 20 === $data['per_page'], "legacy list total and paging for user $user" );
		ok( isset( $data['items'][0]['native_editor_url'], $data['items'][0]['can_edit'], $data['items'][0]['usage']['archived'] ) && is_string( $data['items'][0]['modified'] ) && is_int( $data['items'][0]['author'] ), 'legacy row shape is unchanged' );
	}

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
		call_api( 'GET', '/question-bank', array( 'search' => $tag ) ),
		call_api( 'GET', '/question-bank/' . $alpha['id'] ),
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
	ok( 403 === $seen[0]->get_status() && 403 === $seen[1]->get_status() && 403 === $seen[7]->get_status(), 'item reads are refused' );
	ok( ! in_array( (int) $alpha['id'], ids_of( $seen[2] ), true ), "A's question is not listed for B" );

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
