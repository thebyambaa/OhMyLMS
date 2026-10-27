<?php
/**
 * Independent course generation from a reviewed syllabus selection.
 *
 * @package OhMyLMS
 */

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_STRUCTURE_INTEGRATION' ) ) {
	exit( "Set OHMYLMS_STRUCTURE_INTEGRATION=1 for local generation checks.\n" );
}
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Curriculum\CourseFromSyllabus;
use OhMyLMS\Curriculum\CourseObjectives;
use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Syllabus;
use OhMyLMS\Curriculum\SyllabusCourse;
use OhMyLMS\Learning\Catalog;
use OhMyLMS\Learning\CourseProgram;
use OhMyLMS\Skills\Taxonomy;

/**
 * Assert one integration behavior.
 *
 * @param bool   $condition Expected result.
 * @param string $message Failure reason.
 * @throws RuntimeException On failure.
 */
function seed_check( $condition, $message ) {
	global $seed_checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI test diagnostic.
	}
	++$seed_checks;
}

/**
 * Call the generation routes.
 *
 * @param string $method Method.
 * @param string $suffix Route suffix.
 * @param array  $data Body.
 * @return WP_REST_Response
 */
function seed_api( $method, $suffix = '', array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/courses/from-syllabus' . $suffix );
	$request->set_header( 'Content-Type', 'application/json' );
	$request->set_body( wp_json_encode( $data ) );
	return rest_do_request( $request );
}

/**
 * Remove the exact temporary records, including courses made by the browser.
 *
 * @param array $state Fixture identifiers.
 */
function seed_cleanup( array $state ) {
	global $wpdb;
	wp_set_current_user( $state['actor'] );
	if ( isset( $state['token'] ) ) {
		WP_Session_Tokens::get_instance( $state['actor'] )->destroy( $state['token'] );
	}
	$courses = $state['courses'];
	if ( isset( $state['browser_title'] ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Exact unique browser fixture title only.
		$courses = array_merge( $courses, $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s", OHMYLMS_COURSE_CPT, $state['browser_title'] ) ) );
	}
	foreach ( array_unique( $courses ) as $course ) {
		foreach ( Catalog::chapters( $course ) as $unit ) {
			wp_delete_post( $unit['id'], true );
		}
		$wpdb->delete( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'course_id' => $course ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture relationships only.
		wp_delete_post( $course, true );
	}
	foreach ( $state['resources'] ?? array() as $resource_id ) {
		wp_delete_post( $resource_id, true );
	}
	foreach ( array_reverse( $state['items'] ) as $id ) {
		Syllabus::delete_for_items( array( $id ) );
		$wpdb->delete( Items::table(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture source items only.
	}
	foreach ( $state['terms'] as $id ) {
		wp_delete_term( $id, Taxonomy::NAME );
	}
}

$seed_path = dirname( __DIR__, 2 ) . '/test-results/syllabus-course-session.json';
if ( 'cleanup' === ( $argv[1] ?? '' ) ) {
	seed_cleanup( json_decode( file_get_contents( $seed_path ), true ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Ignored ephemeral fixture file.
	wp_delete_file( $seed_path );
	exit;
}
$seed_checks = 0;
$seed_state  = array(
	'actor'   => get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
		)
	)[0]->ID,
	'courses' => array(),
	'items'   => array(),
	'terms'   => array(),
	'base'    => home_url(),
);
$seed_keep   = false;
try {
	wp_set_current_user( $seed_state['actor'] );
	$seed_syllabus         = Items::create(
		array(
			'name'      => 'Course generation syllabus',
			'item_type' => 'syllabus',
		)
	);
	$seed_state['items'][] = (int) $seed_syllabus['id'];
	foreach ( array( 'Number', 'Algebra' ) as $seed_name ) {
		$seed_topic            = Items::create(
			array(
				'name'      => $seed_name,
				'item_type' => 'topic',
				'parent_id' => $seed_syllabus['id'],
			)
		);
		$seed_state['items'][] = (int) $seed_topic['id'];
		$seed_group            = Syllabus::add_group(
			$seed_syllabus['id'],
			array(
				'item_id' => $seed_topic['id'],
				'name'    => $seed_name . ' foundations',
			)
		);
		foreach ( array( 'first', 'second' ) as $seed_part ) {
			$seed_term             = wp_insert_term( $seed_name . ' ' . $seed_part . ' ' . wp_generate_uuid4(), Taxonomy::NAME );
			$seed_state['terms'][] = (int) $seed_term['term_id'];
			seed_check( true === Syllabus::add_skill( $seed_syllabus['id'], $seed_group, $seed_term['term_id'] ), 'Fixture skill placed.' );
		}
	}
	$seed_source = seed_api( 'GET', '/' . $seed_syllabus['id'] );
	seed_check( 200 === $seed_source->get_status(), 'Authors can preview a syllabus without managing its mirrored course.' );
	$seed_source = $seed_source->get_data();
	seed_check( 2 === count( $seed_source['topics'] ) && 2 === count( $seed_source['topics'][0]['lessons'][0]['skills'] ), 'Preview includes topics, lessons and skills in outline order.' );
	seed_check( ! SyllabusCourse::course_id( $seed_syllabus['id'] ), 'Preview does not create a mirrored syllabus course.' );
	$seed_body = array(
		'syllabus_id' => (int) $seed_syllabus['id'],
		'title'       => 'Selected syllabus course',
		'skill_ids'   => array( $seed_state['terms'][1] ),
		'revision'    => $seed_source['revision'],
	);
	foreach ( array( array(), array( -1 ), array( '12' ), array( $seed_state['terms'][0], $seed_state['terms'][0] ), array( PHP_INT_MAX ), array( array() ) ) as $seed_invalid ) {
		seed_check( 400 === seed_api( 'POST', '', array_replace( $seed_body, array( 'skill_ids' => $seed_invalid ) ) )->get_status(), 'Invalid or empty selections are rejected.' );
	}
	seed_check( 409 === seed_api( 'POST', '', array_replace( $seed_body, array( 'revision' => 'stale' ) ) )->get_status(), 'Stale reviewed sources are rejected.' );
	$seed_made = seed_api( 'POST', '', $seed_body );
	seed_check( 201 === $seed_made->get_status(), 'A reviewed selection creates a draft course.' );
	$seed_course             = (int) $seed_made->get_data()['id'];
	$seed_state['courses'][] = $seed_course;
	$seed_draft              = CourseProgram::editor( $seed_course )['draft'];
	$seed_state['resources'] = array_merge( $seed_state['resources'] ?? array(), array_column( $seed_draft['items'], 'content_id' ) );
	seed_check( 'draft' === get_post_status( $seed_course ) && ! get_post_meta( $seed_course, SyllabusCourse::ITEM_META, true ), 'The course is independent and unpublished.' );
	seed_check( 1 === count( Catalog::chapters( $seed_course ) ) && 'Number' === Catalog::chapters( $seed_course )[0]['name'], 'Only the selected topic becomes a unit.' );
	seed_check( 1 === count( $seed_draft['items'] ) && 'Number foundations' === $seed_draft['items'][0]['title'], 'The source skill group becomes a block lesson.' );
	seed_check( $seed_draft['outcomes'][0]['term_id'] === $seed_state['terms'][1] && array( $seed_state['terms'][1] ) === $seed_draft['items'][0]['skill_ids'], 'The selected existing skill is linked to its lesson.' );
	seed_check( false === $seed_draft['outcomes'][0]['required'] && 'draft' === get_post_status( $seed_draft['items'][0]['content_id'] ) && ! CourseProgram::current( $seed_course ), 'Lessons remain unpublished drafts and skills begin optional.' );
	seed_check( 1 === count( CourseObjectives::references( $seed_course ) ), 'Course objective mappings retain the syllabus reference.' );
	$seed_after = CourseFromSyllabus::preview( $seed_syllabus['id'] );
	seed_check( $seed_source['revision'] === $seed_after['revision'], 'Course generation leaves the source unchanged.' );
	$seed_legacy = seed_api( 'POST', '', array( 'syllabus_id' => (int) $seed_syllabus['id'] ) );
	seed_check( 201 === $seed_legacy->get_status(), 'Older clients may still create from the whole syllabus.' );
	$seed_state['courses'][]                = (int) $seed_legacy->get_data()['id'];
	$seed_shared                            = CourseProgram::editor( (int) $seed_legacy->get_data()['id'] )['draft'];
	$seed_state['resources']                = array_merge( $seed_state['resources'], array_column( $seed_shared['items'], 'content_id' ) );
	$seed_shared['items'][1]['skill_ids'][] = $seed_state['terms'][1];
	seed_check(
		4 === count( $seed_shared['outcomes'] ) && 2 === count(
			array_filter(
				$seed_shared['items'],
				static function ( $item ) use ( $seed_state ) {
					return in_array( $seed_state['terms'][1], $item['skill_ids'], true );
				}
			)
		),
		'Both block lessons retain the shared skill while completion counts it once.'
	);
	$seed_saved = CourseProgram::save( (int) $seed_legacy->get_data()['id'], $seed_shared );
	seed_check( ! is_wp_error( $seed_saved ) && CourseProgram::editor( (int) $seed_legacy->get_data()['id'] )['draft']['items'] === $seed_shared['items'], 'Lesson skill connections survive a save and reload.' );
	$seed_body['revision'] = CourseFromSyllabus::preview( $seed_syllabus['id'] )['revision'];
	seed_check( 2 === $seed_legacy->get_data()['lessons'] && 4 === $seed_legacy->get_data()['objectives'], 'Whole-syllabus creation now also seeds lessons.' );
	$seed_fail = static function ( $response, $handler, $request ) {
		return '/ohmylms/v1/courses' === $request->get_route() && 'POST' === $request->get_method() ? new WP_Error( 'fixture_failure', 'Injected failure after inserting the course.', array( 'status' => 500 ) ) : $response;
	};
	add_filter( 'rest_request_after_callbacks', $seed_fail, 99, 3 );
	$seed_failed = seed_api( 'POST', '', array_replace( $seed_body, array( 'title' => 'Syllabus rollback fixture' ) ) );
	remove_filter( 'rest_request_after_callbacks', $seed_fail, 99 );
	seed_check( 500 === $seed_failed->get_status(), 'A failed generation reports an error.' );
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Assert the temporary course was rolled back in SQL.
	seed_check( '0' === $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s", OHMYLMS_COURSE_CPT, 'Syllabus rollback fixture' ) ), 'The inserted course is rolled back on failure.' );
	$seed_partial = static function ( $meta_id, $post_id, $meta_key ) {
		if ( CourseObjectives::META === $meta_key ) {
			throw new RuntimeException( 'Fixture failure after units and objective mappings.' );
		}
	};
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Count relationship rows before the intentionally failed fixture write.
	$seed_relationships = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ohmylms_chapter_relationship" );
	add_action( 'added_post_meta', $seed_partial, 10, 3 );
	$seed_partial_result = seed_api( 'POST', '', array_replace( $seed_body, array( 'title' => 'Syllabus partial rollback fixture' ) ) );
	remove_action( 'added_post_meta', $seed_partial, 10 );
	seed_check( 500 === $seed_partial_result->get_status(), 'Failure after unit and mapping creation reaches the transaction.' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Verify only the attempted fixture write was rolled back.
	$seed_remaining = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ohmylms_chapter_relationship" );
	seed_check( $seed_relationships === $seed_remaining, 'Partial creation leaves no unit relationships.' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Verify the temporary course was rolled back too.
	seed_check( '0' === $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s", OHMYLMS_COURSE_CPT, 'Syllabus partial rollback fixture' ) ), 'Partial creation leaves no course post.' );
	wp_set_current_user( 0 );
	seed_check( 401 === seed_api( 'GET', '/' . $seed_syllabus['id'] )->get_status() && 401 === seed_api( 'POST', '', $seed_body )->get_status(), 'Anonymous preview and creation are denied.' );
	wp_set_current_user( $seed_state['actor'] );
	echo 'PASS: ' . (int) $seed_checks . " syllabus course checks.\n";
	if ( 'fixture' === ( $argv[1] ?? '' ) ) {
		$seed_expiry                 = time() + HOUR_IN_SECONDS;
		$seed_state['token']         = WP_Session_Tokens::get_instance( $seed_state['actor'] )->create( $seed_expiry );
		$seed_state['syllabus']      = (int) $seed_syllabus['id'];
		$seed_state['browser_title'] = 'Generated browser course ' . wp_generate_uuid4();
		foreach ( array(
			LOGGED_IN_COOKIE => 'logged_in',
			AUTH_COOKIE      => 'auth',
		) as $seed_cookie => $seed_scheme ) {
			$seed_state['cookies'][] = array(
				'name'     => $seed_cookie,
				'value'    => wp_generate_auth_cookie( $seed_state['actor'], $seed_expiry, $seed_scheme, $seed_state['token'] ),
				'domain'   => wp_parse_url( home_url(), PHP_URL_HOST ),
				'path'     => '/',
				'expires'  => $seed_expiry,
				'httpOnly' => true,
				'secure'   => false,
				'sameSite' => 'Lax',
			);
		}
		file_put_contents( $seed_path, wp_json_encode( $seed_state ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Ignored session removed during cleanup.
		$seed_keep = true;
	}
} finally {
	if ( ! $seed_keep ) {
		seed_cleanup( $seed_state );
	}
}
