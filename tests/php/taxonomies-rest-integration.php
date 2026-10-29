<?php
/**
 * Taxonomies (categories and tags) REST API integration test.
 *
 * Runs against a WordPress install (the Local site by default) with real database tables. Categories
 * are curriculum items and tags are course bundles, so the test creates tagged items, bundles, a
 * course and temporary users, and removes everything in `finally`.
 *
 * Usage (set OHMYLMS_TAXONOMIES_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/taxonomies-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, Generic.Formatting.MultipleStatementAlignment

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_TAXONOMIES_TEST' ) ) {
	exit( "Set OHMYLMS_TAXONOMIES_TEST=1 to run the taxonomies REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Bundles\Bundles;
use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Links;
use OhMyLMS\Curriculum\Schema;

global $wpdb;

$checks     = 0;
$user_ids   = array();
$item_ids   = array();
$bundle_ids = array();
$course_id  = 0;
$tag        = 'txtest' . strtolower( wp_generate_password( 6, false, false ) );

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
 * Unwrap a created row or fail.
 *
 * @param array|WP_Error $row  Row or error.
 * @param string         $what What was created.
 * @return array
 * @throws RuntimeException When creation failed.
 */
function created( $row, $what ) {
	if ( is_wp_error( $row ) ) {
		throw new RuntimeException( 'FAILED: could not create ' . $what . ': ' . $row->get_error_message() );
	}
	return $row;
}

try {
	ok( Schema::ready(), 'curriculum schema is installed' );

	$admins = get_users(
		array(
			'role'    => 'administrator',
			'number'  => 1,
			'orderby' => 'ID',
			'fields'  => 'ID',
		)
	);
	ok( ! empty( $admins ), 'an administrator exists' );
	$admin = (int) $admins[0];
	wp_set_current_user( $admin );

	$users = array();
	foreach ( array(
		'author'     => 'author',
		'subscriber' => 'subscriber',
	) as $suffix => $role ) {
		$id = wp_insert_user(
			array(
				'user_login' => $tag . '_' . $suffix,
				'user_pass'  => wp_generate_password( 24 ),
				'user_email' => $tag . '.' . $suffix . '@example.invalid',
				'role'       => $role,
			)
		);
		ok( ! is_wp_error( $id ), 'temporary ' . $suffix . ' created' );
		$user_ids[]       = $id;
		$users[ $suffix ] = $id;
	}

	// Fixtures: a small tree (root, child, second root), two bundles, one course linked to both kinds.
	$root = created( Items::create( array( 'name' => $tag . ' Root' ) ), 'root item' );
	$item_ids[] = (int) $root['id'];
	$child      = created(
		Items::create(
			array(
				'name'        => $tag . ' Child',
				'parent_id'   => $root['id'],
				'description' => 'Child description',
			)
		),
		'child item'
	);
	$item_ids[] = (int) $child['id'];
	$other      = created( Items::create( array( 'name' => $tag . ' Aardvark' ) ), 'second root' );
	$item_ids[] = (int) $other['id'];

	$bundle_one   = created( Bundles::create( array( 'title' => $tag . ' Bundle One' ) ), 'bundle one' );
	$bundle_ids[] = (int) $bundle_one['id'];
	$bundle_two   = created( Bundles::create( array( 'title' => $tag . ' Bundle Two' ) ), 'bundle two' );
	$bundle_ids[] = (int) $bundle_two['id'];

	$course_id = wp_insert_post(
		array(
			'post_type'   => 'ohmylms-course',
			'post_status' => 'publish',
			'post_title'  => $tag . ' Linked course',
		),
		true
	);
	ok( ! is_wp_error( $course_id ), 'course created' );
	ok( true === Links::add( (int) $child['id'], 'course', $course_id ), 'course linked to the child item' );
	ok( true === Bundles::add_course( (int) $bundle_one['id'], $course_id ), 'course added to bundle one' );

	// Routes.
	$routes = rest_get_server()->get_routes( 'ohmylms/v1' );
	foreach ( array( 'categories', 'tags' ) as $base ) {
		foreach ( array( "/$base", "/$base/bulk", "/$base/(?P<id>[\\d]+)" ) as $route ) {
			ok( isset( $routes[ '/ohmylms/v1' . $route ] ), 'route registered: ' . $route );
			foreach ( $routes[ '/ohmylms/v1' . $route ] as $endpoint ) {
				ok( is_callable( $endpoint['permission_callback'] ) && '__return_true' !== $endpoint['permission_callback'], 'real permission_callback on ' . $route );
			}
		}
	}

	// Anonymous, subscriber (no edit_posts), author (edit_posts but not manage_options).
	$read_cases  = array(
		array( 'GET', '/categories' ),
		array( 'GET', '/tags' ),
		array( 'GET', '/categories/' . $child['id'] ),
		array( 'GET', '/tags/' . $bundle_one['id'] ),
	);
	$write_cases = array(
		array( 'POST', '/categories' ),
		array( 'PUT', '/categories/' . $child['id'] ),
		array( 'DELETE', '/categories/' . $child['id'] ),
		array( 'DELETE', '/categories/bulk' ),
		array( 'POST', '/tags' ),
		array( 'PATCH', '/tags/' . $bundle_one['id'] ),
		array( 'DELETE', '/tags/' . $bundle_one['id'] ),
		array( 'DELETE', '/tags/bulk' ),
	);
	wp_set_current_user( 0 );
	foreach ( array_merge( $read_cases, $write_cases ) as $case ) {
		$status = call_api( $case[0], $case[1] )->get_status();
		ok( 401 === $status, 'anonymous ' . $case[0] . ' ' . $case[1] . ' is 401, got ' . $status );
	}
	wp_set_current_user( $users['subscriber'] );
	foreach ( array_merge( $read_cases, $write_cases ) as $case ) {
		$status = call_api( $case[0], $case[1] )->get_status();
		ok( 403 === $status, 'subscriber ' . $case[0] . ' ' . $case[1] . ' is 403, got ' . $status );
	}
	wp_set_current_user( $users['author'] );
	foreach ( $read_cases as $case ) {
		$status = call_api( $case[0], $case[1] )->get_status();
		ok( 200 === $status, 'author may read ' . $case[1] . ', got ' . $status );
	}
	foreach ( $write_cases as $case ) {
		$status = call_api( $case[0], $case[1] )->get_status();
		ok( 403 === $status, 'author ' . $case[0] . ' ' . $case[1] . ' is 403, got ' . $status );
	}
	$denied = call_api( 'GET', '/categories/' . $child['id'], array( 'context' => 'edit' ) );
	ok( 403 === $denied->get_status() && 'rest_forbidden_context' === $denied->get_data()['code'], 'author cannot request the edit context (course titles)' );
	$view = call_api( 'GET', '/categories/' . $child['id'] );
	ok( ! isset( $view->get_data()['courses'] ), 'the view context never lists course titles' );

	// Administrator: categories (curriculum items).
	wp_set_current_user( $admin );

	$response = call_api( 'GET', '/categories', array( 'search' => $tag ) );
	ok( 200 === $response->get_status(), 'categories 200' );
	ok( '3' === $response->get_headers()['X-WP-Total'] && '1' === $response->get_headers()['X-WP-TotalPages'], 'categories headers' );
	$names = wp_list_pluck( $response->get_data(), 'name' );
	ok( array( $tag . ' Aardvark', $tag . ' Child', $tag . ' Root' ) === $names, 'sorted by name ascending: ' . implode( '|', $names ) );
	$by_id = array_column( $response->get_data(), null, 'id' );
	ok( 1 === $by_id[ (int) $child['id'] ]['count'] && 0 === $by_id[ (int) $root['id'] ]['count'], 'course counts come from the links' );
	ok( (int) $root['id'] === $by_id[ (int) $child['id'] ]['parent'] && 0 === $by_id[ (int) $root['id'] ]['parent'], 'parent is exposed' );
	ok( 'c' . $child['id'] === $by_id[ (int) $child['id'] ]['slug'] && 'Child description' === $by_id[ (int) $child['id'] ]['description'], 'slug and description' );
	$keys = array_values( array_diff( array_keys( $response->get_data()[0] ), array( '_links' ) ) );
	sort( $keys );
	ok( array( 'count', 'description', 'id', 'name', 'parent', 'slug' ) === $keys, 'collection item fields: ' . implode( ',', $keys ) );
	ok( ! isset( $by_id[ (int) $child['id'] ]['term_id'] ) && ! isset( $by_id[ (int) $child['id'] ]['courses'] ), 'legacy term_id and embedded course list are gone' );

	$response = call_api(
		'GET',
		'/categories',
		array(
			'search'  => $tag,
			'orderby' => 'id',
			'order'   => 'desc',
		)
	);
	ok( (int) $other['id'] === $response->get_data()[0]['id'], 'order by id desc' );
	$response = call_api(
		'GET',
		'/categories',
		array(
			'search'   => $tag,
			'per_page' => 2,
			'page'     => 2,
		)
	);
	ok( 1 === count( $response->get_data() ) && '2' === $response->get_headers()['X-WP-TotalPages'] && '3' === $response->get_headers()['X-WP-Total'], 'second page' );
	ok( false !== strpos( $response->get_headers()['Link'], 'rel="prev"' ), 'Link prev header' );
	$response = call_api(
		'GET',
		'/categories',
		array(
			'search' => $tag,
			'parent' => $root['id'],
		)
	);
	ok( 1 === count( $response->get_data() ) && (int) $child['id'] === $response->get_data()[0]['id'], 'parent filter' );
	$response = call_api(
		'GET',
		'/categories',
		array(
			'search' => $tag,
			'parent' => 0,
		)
	);
	ok( 2 === count( $response->get_data() ), 'parent=0 lists top-level items only' );
	$response = call_api( 'GET', '/categories', array( 'search' => $tag . "' OR 1=1 --" ) );
	ok( 200 === $response->get_status() && array() === $response->get_data(), 'search is literal' );
	$response = call_api(
		'GET',
		'/categories',
		array(
			'search' => $tag,
			'page'   => 9,
		)
	);
	ok( 400 === $response->get_status() && 'ohmylms_category_invalid_page' === $response->get_data()['code'], 'page past the end is 400' );
	foreach ( array(
		array( 'per_page' => 101 ),
		array( 'per_page' => 0 ),
		array( 'page' => 0 ),
		array( 'orderby' => 'count' ),
		array( 'order' => 'up' ),
		array( 'parent' => -1 ),
		array( 'search' => str_repeat( 'x', 101 ) ),
	) as $bad ) {
		$response = call_api( 'GET', '/categories', $bad );
		ok( 400 === $response->get_status() && 'rest_invalid_param' === $response->get_data()['code'], 'invalid arg rejected: ' . wp_json_encode( $bad ) );
	}

	// Single category and the edit context.
	$response = call_api( 'GET', '/categories/' . $child['id'], array( 'context' => 'edit' ) );
	ok( 200 === $response->get_status() && array( $course_id ) === wp_list_pluck( $response->get_data()['courses'], 'id' ), 'edit context lists linked courses' );
	ok( $tag . ' Linked course' === $response->get_data()['courses'][0]['title'], 'course title' );
	$response = call_api( 'GET', '/categories/' . $child['id'] );
	ok( 200 === $response->get_status() && $tag . ' Child' === $response->get_data()['name'], 'read one category' );
	$missing = call_api( 'GET', '/categories/999999999' );
	ok( 404 === $missing->get_status() && 'ohmylms_category_not_found' === $missing->get_data()['code'], 'unknown category is 404' );

	// Tags (bundles).
	$response = call_api( 'GET', '/tags', array( 'search' => $tag ) );
	ok( 200 === $response->get_status() && '2' === $response->get_headers()['X-WP-Total'], 'tags list' );
	$by_id = array_column( $response->get_data(), null, 'id' );
	ok( 1 === $by_id[ (int) $bundle_one['id'] ]['count'] && 0 === $by_id[ (int) $bundle_two['id'] ]['count'], 'bundle course counts' );
	ok( 'b' . $bundle_one['id'] === $by_id[ (int) $bundle_one['id'] ]['slug'] && 0 === $by_id[ (int) $bundle_one['id'] ]['parent'], 'bundle slug and parent' );
	ok(
		200 === call_api(
			'GET',
			'/tags',
			array(
				'parent' => 1,
				'search' => $tag,
			)
		)->get_status(),
		'tags ignore the category-only parent argument'
	);
	$response = call_api( 'GET', '/tags/' . $bundle_one['id'], array( 'context' => 'edit' ) );
	ok( 200 === $response->get_status() && array( $course_id ) === wp_list_pluck( $response->get_data()['courses'], 'id' ), 'tag edit context' );
	$missing = call_api( 'GET', '/tags/999999999' );
	ok( 404 === $missing->get_status() && 'ohmylms_tag_not_found' === $missing->get_data()['code'], 'unknown tag is 404' );
	$response = call_api(
		'GET',
		'/tags',
		array(
			'search' => $tag,
			'page'   => 5,
		)
	);
	ok( 400 === $response->get_status() && 'ohmylms_tag_invalid_page' === $response->get_data()['code'], 'tag page past the end' );

	// Writes are refused for administrators too, and change nothing.
	$before_items   = count( Items::all() );
	$before_bundles = count( Bundles::all() );
	foreach ( array(
		array( 'POST', '/categories', 'ohmylms_categories_replaced' ),
		array( 'PUT', '/categories/' . $child['id'], 'ohmylms_categories_replaced' ),
		array( 'DELETE', '/categories/' . $child['id'], 'ohmylms_categories_replaced' ),
		array( 'DELETE', '/categories/bulk', 'ohmylms_categories_replaced' ),
		array( 'POST', '/tags', 'ohmylms_tags_replaced' ),
		array( 'PATCH', '/tags/' . $bundle_one['id'], 'ohmylms_tags_replaced' ),
		array( 'DELETE', '/tags/' . $bundle_one['id'], 'ohmylms_tags_replaced' ),
		array( 'DELETE', '/tags/bulk', 'ohmylms_tags_replaced' ),
	) as $case ) {
		$response = call_api( $case[0], $case[1], array( 'name' => 'x' ) );
		ok( 410 === $response->get_status() && $case[2] === $response->get_data()['code'], 'administrator write ' . $case[0] . ' ' . $case[1] . ' is 410, got ' . $response->get_status() );
	}
	ok( count( Items::all() ) === $before_items && count( Bundles::all() ) === $before_bundles, 'refused writes changed nothing' );
	ok( Items::get( (int) $child['id'] ) && Bundles::get( (int) $bundle_one['id'] ), 'fixtures survive the refused deletes' );

	// Schema.
	foreach ( array(
		'categories' => 'category',
		'tags'       => 'tag',
	) as $base => $title ) {
		$schema = rest_do_request( new WP_REST_Request( 'OPTIONS', '/ohmylms/v1/' . $base ) )->get_data()['schema'];
		ok( $title === $schema['title'] && 'http://json-schema.org/draft-04/schema#' === $schema['$schema'], "$base schema header" );
		foreach ( array( 'id', 'name', 'slug', 'description', 'parent', 'count', 'courses' ) as $property ) {
			ok( isset( $schema['properties'][ $property ] ), "$base schema property $property" );
		}
	}

	fwrite( STDOUT, "OK taxonomies-rest-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	// Remove only what this run created.
	if ( $course_id && ! is_wp_error( $course_id ) ) {
		$wpdb->delete( Links::table(), array( 'object_id' => $course_id ), array( '%d' ) );
		$wpdb->delete( Bundles::members_table(), array( 'item_id' => $course_id ), array( '%d' ) );
		wp_delete_post( $course_id, true );
	}
	foreach ( array_reverse( $item_ids ) as $item_id ) {
		if ( Items::get( $item_id ) ) {
			Items::delete( $item_id, 'delete', true );
		}
	}
	foreach ( $bundle_ids as $bundle_id ) {
		if ( Bundles::get( $bundle_id ) ) {
			Bundles::delete( $bundle_id, true );
		}
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
}

exit( isset( $exit_code ) ? $exit_code : 0 );
