<?php
/**
 * Content Bank folders and tags REST integration test.
 *
 * Runs against a WordPress install (the Local site by default). It creates a folder tree, a tag, lessons and
 * a quiz, organizes them through `ohmylms/v1/content-bank/*`, checks that the plugin's own list endpoints can
 * be narrowed with `bank_folder` and `bank_tag`, and removes everything in `finally`.
 *
 * Usage (set OHMYLMS_CONTENT_BANK_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/content-bank-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, Generic.Formatting.MultipleStatementAlignment

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_CONTENT_BANK_TEST' ) ) {
	exit( "Set OHMYLMS_CONTENT_BANK_TEST=1 to run the Content Bank REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

use OhMyLMS\ContentBank\Organization;

$checks   = 0;
$post_ids = array();
$term_ids = array();
$mark     = 'cbtest' . strtolower( wp_generate_password( 6, false, false ) );

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
 * Item IDs of a lesson list response.
 *
 * @param array $data Response data of `authoring/lessons`.
 * @return int[]
 */
function lesson_ids( array $data ) {
	return array_map( 'intval', wp_list_pluck( $data['items'], 'id' ) );
}

try {
	$admins = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	);
	ok( $admins, 'an administrator exists' );
	wp_set_current_user( (int) $admins[0] );

	ok( taxonomy_exists( Organization::FOLDER ) && taxonomy_exists( Organization::TAG ), 'folder and tag taxonomies are registered' );
	foreach ( Organization::types() as $post_type ) {
		ok( is_object_in_taxonomy( $post_type, Organization::FOLDER ), "$post_type can be put in a folder" );
		ok( is_object_in_taxonomy( $post_type, Organization::TAG ), "$post_type can be tagged" );
	}

	$parent = call_api(
		'POST',
		'/content-bank/terms',
		array(
			'kind' => 'folder',
			'name' => "$mark parent",
		)
	);
	ok( 201 === $parent->get_status(), 'a folder is created' );
	$parent_id  = (int) $parent->get_data()['id'];
	$term_ids[] = array( $parent_id, Organization::FOLDER );
	$child      = call_api(
		'POST',
		'/content-bank/terms',
		array(
			'kind'   => 'folder',
			'name'   => "$mark child",
			'parent' => $parent_id,
		)
	);
	ok( 201 === $child->get_status() && $parent_id === $child->get_data()['parent'], 'a folder is created inside another' );
	$child_id   = (int) $child->get_data()['id'];
	$term_ids[] = array( $child_id, Organization::FOLDER );
	$tag        = call_api(
		'POST',
		'/content-bank/terms',
		array(
			'kind' => 'tag',
			'name' => "$mark tag",
		)
	);
	ok( 201 === $tag->get_status(), 'a tag is created' );
	$tag_id     = (int) $tag->get_data()['id'];
	$term_ids[] = array( $tag_id, Organization::TAG );
	$again      = call_api(
		'POST',
		'/content-bank/terms',
		array(
			'kind' => 'tag',
			'name' => "$mark tag",
		)
	);
	ok( 400 === $again->get_status(), 'a duplicate tag name is refused' );
	ok(
		400 === call_api(
			'POST',
			'/content-bank/terms',
			array(
				'kind'   => 'folder',
				'name'   => "$mark orphan",
				'parent' => 999999,
			)
		)->get_status(),
		'a folder inside a missing folder is refused'
	);

	$listed = call_api( 'GET', '/content-bank/terms' )->get_data();
	ok( in_array( $child_id, wp_list_pluck( $listed['folders'], 'id' ), true ), 'folders are listed' );
	ok( in_array( $tag_id, wp_list_pluck( $listed['tags'], 'id' ), true ), 'tags are listed' );

	$in_child  = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_LESSON_CPT,
			'post_status' => 'draft',
			'post_title'  => "$mark in child",
		)
	);
	$in_parent = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_LESSON_CPT,
			'post_status' => 'draft',
			'post_title'  => "$mark in parent",
		)
	);
	$loose     = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_LESSON_CPT,
			'post_status' => 'draft',
			'post_title'  => "$mark loose",
		)
	);
	$quiz      = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_QUIZ_CPT,
			'post_status' => 'draft',
			'post_title'  => "$mark quiz",
		)
	);
	$post_ids  = array( $in_child, $in_parent, $loose, $quiz );
	ok( ! in_array( 0, $post_ids, true ), 'content is created' );

	$organized = call_api(
		'POST',
		'/content-bank/organize',
		array(
			'ids'        => array( $in_child, $quiz ),
			'folder_ids' => array( $child_id ),
			'tag_ids'    => array( $tag_id ),
		)
	);
	ok( 200 === $organized->get_status() && array( $in_child, $quiz ) === $organized->get_data()['updated'], 'items are organized' );
	call_api(
		'POST',
		'/content-bank/organize',
		array(
			'ids'        => array( $in_parent ),
			'folder_ids' => array( $parent_id ),
		)
	);

	$assigned = (array) call_api( 'GET', '/content-bank/assignments', array( 'ids' => array( $in_child, $loose ) ) )->get_data();
	ok( array( $child_id ) === $assigned[ $in_child ]['folders'] && array( $tag_id ) === $assigned[ $in_child ]['tags'], 'an item reports its folders and tags' );
	ok( array() === $assigned[ $loose ]['folders'], 'an item in no folder reports none' );

	$search = array(
		'page'   => 1,
		'search' => $mark,
	);
	ok( 3 === count( lesson_ids( call_api( 'GET', '/authoring/lessons', $search )->get_data() ) ), 'without a filter every lesson is listed' );
	$by_child = lesson_ids( call_api( 'GET', '/authoring/lessons', $search + array( 'bank_folder' => $child_id ) )->get_data() );
	ok( array( $in_child ) === $by_child, 'a folder filter lists its lessons' );
	$by_parent = lesson_ids( call_api( 'GET', '/authoring/lessons', $search + array( 'bank_folder' => $parent_id ) )->get_data() );
	sort( $by_parent );
	$expected = array( $in_child, $in_parent );
	sort( $expected );
	ok( $expected === $by_parent, 'a folder filter includes its sub-folders' );
	$by_tag = lesson_ids( call_api( 'GET', '/authoring/lessons', $search + array( 'bank_tag' => $tag_id ) )->get_data() );
	ok( array( $in_child ) === $by_tag, 'a tag filter lists the tagged lessons' );
	ok(
		array() === lesson_ids(
			call_api(
				'GET',
				'/authoring/lessons',
				$search + array(
					'bank_folder' => $parent_id,
					'bank_tag'    => 999999,
				)
			)->get_data()
		),
		'folder and tag filters combine'
	);

	$course_id  = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_COURSE_CPT,
			'post_status' => 'draft',
			'post_title'  => "$mark course",
		)
	);
	$post_ids[] = $course_id;
	$picker     = call_api(
		'GET',
		"/courses/$course_id/structure/resources",
		array(
			'type'     => 'lesson',
			'search'   => $mark,
			'bank_tag' => $tag_id,
		)
	)->get_data();
	ok( array( $in_child ) === array_map( 'intval', wp_list_pluck( $picker['items'], 'id' ) ), 'the course attach picker can be narrowed by tag' );
	$picker_all = call_api(
		'GET',
		"/courses/$course_id/structure/resources",
		array(
			'type'   => 'lesson',
			'search' => $mark,
		)
	)->get_data();
	ok( 3 === count( $picker_all['items'] ), 'the course attach picker lists every match without a filter' );

	$quizzes = call_api(
		'GET',
		'/quizzes',
		array(
			'search'   => $mark,
			'bank_tag' => $tag_id,
		)
	)->get_data();
	ok( array( $quiz ) === array_map( 'intval', wp_list_pluck( $quizzes, 'id' ) ), 'quizzes can be filtered by tag' );
	$unfiltered = call_api( 'GET', '/lessons', array( 'search' => $mark ) );
	ok( 200 === $unfiltered->get_status(), 'a request after a filtered one is not narrowed' );

	call_api(
		'POST',
		'/content-bank/organize',
		array(
			'ids'     => array( $in_child ),
			'tag_ids' => array( $tag_id ),
			'mode'    => 'remove',
		)
	);
	ok( array() === lesson_ids( call_api( 'GET', '/authoring/lessons', $search + array( 'bank_tag' => $tag_id ) )->get_data() ), 'a tag is removed from an item' );
	call_api(
		'POST',
		'/content-bank/organize',
		array(
			'ids'        => array( $in_child ),
			'folder_ids' => array(),
			'mode'       => 'replace',
		)
	);
	ok( array() === lesson_ids( call_api( 'GET', '/authoring/lessons', $search + array( 'bank_folder' => $child_id ) )->get_data() ), 'replacing with no folders clears them' );

	$skipped = call_api(
		'POST',
		'/content-bank/organize',
		array(
			'ids'     => array( 999999, $loose ),
			'tag_ids' => array( $tag_id ),
		)
	)->get_data();
	ok( array( $loose ) === $skipped['updated'] && 'invalid' === $skipped['skipped'][0]['reason'], 'an item that does not exist is skipped, not fatal' );
	ok(
		400 === call_api(
			'POST',
			'/content-bank/organize',
			array(
				'ids'        => array( $loose ),
				'folder_ids' => array( 999999 ),
			)
		)->get_status(),
		'a folder that does not exist is refused'
	);
	ok( 400 === call_api( 'POST', '/content-bank/organize', array( 'ids' => array( $loose ) ) )->get_status(), 'a request that changes nothing is refused' );

	$author = wp_insert_user(
		array(
			'user_login' => $mark . 'sub',
			'user_pass'  => wp_generate_password(),
			'user_email' => $mark . 'sub@example.test',
			'role'       => 'subscriber',
		)
	);
	wp_set_current_user( (int) $author );
	ok( 403 === call_api( 'GET', '/content-bank/terms' )->get_status(), 'a subscriber cannot read folders' );
	wp_set_current_user( (int) $admins[0] );
	wp_delete_user( (int) $author );

	echo "Content Bank REST: $checks checks passed.\n";
} catch ( Throwable $error ) {
	echo $error->getMessage() . PHP_EOL;
	$failed = true;
} finally {
	foreach ( $post_ids as $post_id ) {
		wp_delete_post( (int) $post_id, true );
	}
	foreach ( array_reverse( $term_ids ) as $term ) {
		wp_delete_term( $term[0], $term[1] );
	}
}
if ( ! empty( $failed ) ) {
	exit( 1 );
}
