<?php
/**
 * Curriculum -> course -> unit/lesson save/reopen and permission checks on the named Local test site.
 * Creates only tagged fixtures and removes those fixtures in finally. Never resets a database.
 * Set OHMYLMS_AUTHORING_TEST=1 and provide mysqli/mbstring and the Local MySQL port to PHP.
 *
 * @package OhMyLMS
 */

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_AUTHORING_TEST' ) ) {
	exit( "Set OHMYLMS_AUTHORING_TEST=1 to run the authoring REST integration tests.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

use OhMyLMS\Curriculum\Items;
use OhMyLMS\Learning\Catalog;
use OhMyLMS\Learning\CourseProgram;

$checks      = 0;
$post_ids    = array();
$user_ids    = array();
$item_ids    = array();
$fixture_tag = 'authoring' . strtolower( wp_generate_password( 8, false, false ) );

/**
 * Assert a condition.
 *
 * @param bool   $condition Expected truth.
 * @param string $message Failure explanation.
 * @throws RuntimeException On failure.
 */
function authoring_check( $condition, $message ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI assertion, not an HTML response.
	}
	++$checks;
}

/**
 * Dispatch authenticated WordPress REST without remote requests.
 *
 * @param string $method HTTP method.
 * @param string $path Path below the namespace.
 * @param array  $data JSON or query.
 * @return WP_REST_Response
 */
function authoring_request( $method, $path, $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/' . $path );
	if ( 'GET' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

try {
	foreach ( array( 'administrator', 'author', 'subscriber' ) as $fixture_role ) {
		$created_user_id = wp_insert_user(
			array(
				'user_login' => $fixture_tag . $fixture_role,
				'user_pass'  => wp_generate_password( 32 ),
				'role'       => $fixture_role,
			)
		);
		authoring_check( ! is_wp_error( $created_user_id ), 'Create tagged test user.' );
		$user_ids[ $fixture_role ] = $created_user_id;
	}
	wp_set_current_user( 0 );
	authoring_check( 401 === authoring_request( 'GET', 'authoring/courses' )->get_status(), 'Guests cannot list authoring courses.' );
	wp_set_current_user( $user_ids['subscriber'] );
	authoring_check( 403 === authoring_request( 'POST', 'authoring/courses', array( 'title' => $fixture_tag ) )->get_status(), 'Subscribers cannot create courses.' );
	wp_set_current_user( $user_ids['administrator'] );
	authoring_check( 400 === authoring_request( 'POST', 'authoring/courses', array( 'title' => ' ' ) )->get_status(), 'Blank title rejected.' );
	authoring_check(
		400 === authoring_request(
			'POST',
			'authoring/courses',
			array(
				'title'       => $fixture_tag,
				'post_status' => 'publish',
			)
		)->get_status(),
		'Caller cannot publish through draft creation.'
	);
	authoring_check( 400 === authoring_request( 'GET', 'authoring/courses', array( 'page' => -1 ) )->get_status(), 'Invalid page rejected.' );
	$fixture_title = $fixture_tag . ' Course \\alpha';
	$created       = authoring_request( 'POST', 'authoring/courses', array( 'title' => $fixture_title ) );
	authoring_check( 201 === $created->get_status(), 'Create draft course.' );
	authoring_check( $fixture_title === $created->get_data()['title'], 'Course title preserves mathematical backslashes.' );
	$course     = $created->get_data()['id'];
	$post_ids[] = $course;
	authoring_check( 'draft' === get_post_status( $course ), 'Course remains a draft.' );
	$list = authoring_request( 'GET', 'authoring/courses', array( 'search' => $fixture_tag ) )->get_data();
	authoring_check( 1 === count( $list['items'] ) && $course === $list['items'][0]['id'], 'Bounded course search finds fixture.' );
	authoring_check( array( 'id', 'title', 'status' ) === array_keys( $list['items'][0] ), 'Course list contains no learner or pricing data.' );
	$curriculum = authoring_request(
		'POST',
		'curriculum/items',
		array(
			'name'        => $fixture_tag . ' Subject',
			'item_type'   => 'subject',
			'is_syllabus' => false,
		)
	);
	authoring_check( 201 === $curriculum->get_status(), 'Create curriculum subject.' );
	$subject    = $curriculum->get_data()['item'];
	$item_ids[] = $subject['id'];
	$child      = authoring_request(
		'POST',
		'curriculum/items',
		array(
			'name'        => $fixture_tag . ' Topic',
			'item_type'   => 'topic',
			'parent_id'   => $subject['id'],
			'is_syllabus' => false,
		)
	)->get_data()['item'];
	$item_ids[] = $child['id'];
	$cycle      = authoring_request( 'POST', 'curriculum/items/' . $subject['id'] . '/move', array( 'parent_id' => $child['id'] ) );
	authoring_check( $cycle->get_status() >= 400, 'Parent cannot move below its descendant.' );
	$renamed = authoring_request(
		'PUT',
		'curriculum/items/' . $child['id'],
		array(
			'name'                => $fixture_tag . ' Edited topic',
			'expected_updated_at' => $child['updated_at'],
		)
	);
	authoring_check( 200 === $renamed->get_status(), 'Save curriculum rename.' );
	authoring_check( authoring_request( 'GET', 'curriculum/items/' . $child['id'] )->get_data()['item']['name'] === $fixture_tag . ' Edited topic', 'Curriculum rename survives reopen.' );
	$placement = authoring_request( 'PUT', 'courses/' . $course . '/organization', array( 'curriculum_ids' => array( $child['id'] ) ) );
	authoring_check( 200 === $placement->get_status(), 'Place course in curriculum.' );
	authoring_check( authoring_request( 'GET', 'courses/' . $course . '/organization' )->get_data()['curriculum'][0]['id'] === $child['id'], 'Placement survives reopen.' );
	$lesson     = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_LESSON_CPT,
			'post_status' => 'draft',
			'post_title'  => $fixture_tag . ' Lesson',
			'post_author' => $user_ids['administrator'],
		)
	);
	$post_ids[] = $lesson;
	$read       = authoring_request( 'GET', 'courses/' . $course . '/structure' );
	authoring_check( 200 === $read->get_status(), 'Read empty course structure.' );
	$snapshot         = $read->get_data();
	$unit             = wp_generate_uuid4();
	$activity         = wp_generate_uuid4();
	$program          = $snapshot['program'];
	$program['items'] = array(
		array(
			'id'         => $activity,
			'type'       => 'lesson',
			'content_id' => $lesson,
			'chapter_id' => $unit,
			'required'   => true,
		),
	);
	$payload          = array(
		'revision' => $snapshot['structure_revision'],
		'units'    => array(
			array(
				'id'          => $unit,
				'title'       => 'First unit',
				'number'      => '1',
				'description' => 'Saved description',
			),
		),
		'program'  => $program,
	);
	$saved            = authoring_request( 'PUT', 'courses/' . $course . '/structure', $payload );
	authoring_check( 200 === $saved->get_status(), 'Save unit, lesson group and reusable lesson: ' . wp_json_encode( $saved->get_data() ) );
	$reopen     = authoring_request( 'GET', 'courses/' . $course . '/structure' )->get_data();
	$post_ids[] = $reopen['units'][0]['id'];
	authoring_check( 'First unit' === $reopen['units'][0]['title'], 'Unit survives reopen.' );
	authoring_check( $activity === $reopen['program']['items'][0]['id'], 'Placement identity survives reopen.' );
	authoring_check( empty( $reopen['program']['items'][0]['group_id'] ), 'Block lessons belong directly to units.' );
	authoring_check( 'draft' === get_post_status( $lesson ) && ! get_post_meta( $course, CourseProgram::CURRENT, true ), 'Draft save publishes neither lessons nor program.' );
	authoring_check( 409 === authoring_request( 'PUT', 'courses/' . $course . '/structure', $payload )->get_status(), 'Stale read token rejected.' );
	$payload['revision']          = $reopen['structure_revision'];
	$payload['units']             = $reopen['units'];
	$payload['program']           = $reopen['program'];
	$payload['units'][0]['title'] = 'Renamed unit';
	$second                       = authoring_request( 'PUT', 'courses/' . $course . '/structure', $payload );
	authoring_check( 200 === $second->get_status(), 'Fresh read token permits another save.' );
	$before          = Catalog::chapters( $course );
	$bad             = $payload;
	$bad['revision'] = $second->get_data()['structure_revision'];
	$bad['units'][]  = $bad['units'][0];
	authoring_check( 400 === authoring_request( 'PUT', 'courses/' . $course . '/structure', $bad )->get_status(), 'Duplicate unit rejected.' );
	authoring_check( Catalog::chapters( $course ) === $before, 'Failed save leaves units unchanged.' );
	wp_set_current_user( $user_ids['author'] );
	authoring_check( 403 === authoring_request( 'GET', 'courses/' . $course . '/structure' )->get_status(), 'Other author cannot read private outline.' );
	authoring_check( 403 === authoring_request( 'PUT', 'courses/' . $course . '/structure', $payload )->get_status(), 'Other author cannot overwrite outline.' );
	authoring_check( 403 === authoring_request( 'GET', 'curriculum/tree' )->get_status(), 'Other author cannot administer curriculum.' );
	$list = authoring_request( 'GET', 'authoring/courses', array( 'search' => $fixture_tag ) )->get_data();
	authoring_check( empty( $list['items'] ), 'Author list excludes another author’s drafts.' );
	echo "Authoring REST integration: $checks checks passed.\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test count, not an HTML response.
} finally {
	wp_set_current_user( $user_ids['administrator'] ?? 0 );
	foreach ( array_reverse( $post_ids ) as $fixture_post_id ) {
		wp_delete_post( $fixture_post_id, true );
	}
	foreach ( array_reverse( $item_ids ) as $item_id ) {
		Items::delete( $item_id, 'delete', true );
	}
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
}
