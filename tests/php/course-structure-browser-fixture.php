<?php
/**
 * Temporary session and content for local structure browser verification.
 *
 * @package OhMyLMS
 */

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_STRUCTURE_INTEGRATION' ) ) {
	exit( 1 );
}
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Learning\Catalog;

$structure_path = dirname( __DIR__, 2 ) . '/test-results/structure-browser-session.json';
if ( 'cleanup' === ( $argv[1] ?? '' ) ) {
	$structure_fixture = json_decode( file_get_contents( $structure_path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Ephemeral local CLI fixture.
	wp_set_current_user( $structure_fixture['actor'] );
	WP_Session_Tokens::get_instance( $structure_fixture['actor'] )->destroy( $structure_fixture['token'] );
	global $wpdb;
	foreach ( Catalog::chapters( $structure_fixture['course'] ) as $structure_unit ) {
		$wpdb->delete( $wpdb->prefix . 'ohmylms_content_relationship', array( 'chapter_id' => $structure_unit['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Only this fixture's rows.
		wp_delete_post( $structure_unit['id'], true );
	}
	$wpdb->delete( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'course_id' => $structure_fixture['course'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Only this fixture's rows.
	$wpdb->delete( \OhMyLMS\Learning\Schema::table( 'programs' ), array( 'course_id' => $structure_fixture['course'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Only the fixture's empty-outcome publications.
	foreach ( $structure_fixture['contents'] ?? array() as $structure_content ) {
		wp_delete_post( $structure_content, true );
	}
	foreach ( $structure_fixture['skills'] ?? array() as $structure_skill ) {
		wp_delete_term( $structure_skill, \OhMyLMS\Skills\Taxonomy::NAME );
	}
	wp_delete_post( $structure_fixture['course'], true );
	wp_delete_file( $structure_path );
	exit;
}
$structure_actor = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
	)
)[0]->ID;
wp_set_current_user( $structure_actor );
$structure_request = new WP_REST_Request( 'POST', '/ohmylms/v1/courses' );
$structure_request->set_body_params(
	array(
		'name'        => 'Structure browser fixture',
		'status'      => 'draft',
		'course_type' => 'self-paced',
	)
);
$structure_response = rest_do_request( $structure_request );
if ( $structure_response->is_error() ) {
	throw new RuntimeException( 'Course fixture creation failed.' );
}
$structure_course         = $structure_response->get_data()['id'];
$structure_lesson         = wp_insert_post(
	array(
		'post_type'    => OHMYLMS_LESSON_CPT,
		'post_title'   => 'Preview fixture activity',
		'post_content' => 'A published test activity.',
		'post_status'  => 'publish',
	)
);
$structure_library_lesson = wp_insert_post(
	array(
		'post_type'    => OHMYLMS_LESSON_CPT,
		'post_title'   => 'Outline library activity',
		'post_content' => 'Reusable outline test activity.',
		'post_status'  => 'publish',
	)
);
$structure_extra_lesson   = wp_insert_post(
	array(
		'post_type'    => OHMYLMS_LESSON_CPT,
		'post_title'   => 'Outline lesson library',
		'post_content' => 'A reusable block lesson.',
		'post_status'  => 'publish',
	)
);
$structure_skill          = wp_insert_term( 'Outline connection skill ' . wp_generate_uuid4(), \OhMyLMS\Skills\Taxonomy::NAME );
$structure_quiz           = wp_insert_post(
	array(
		'post_type'   => OHMYLMS_QUIZ_CPT,
		'post_title'  => 'Outline connection quiz',
		'post_status' => 'publish',
	)
);
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Native activity used only by this browser fixture.
$wpdb->insert(
	$wpdb->prefix . 'ohmylms_content_relationship',
	array(
		'chapter_id'   => Catalog::chapters( $structure_course )[0]['id'],
		'content_id'   => $structure_lesson,
		'content_type' => 'text',
		'order_number' => 1,
	)
);
$structure_expiry  = time() + HOUR_IN_SECONDS;
$structure_token   = WP_Session_Tokens::get_instance( $structure_actor )->create( $structure_expiry );
$structure_cookies = array();
foreach ( array(
	LOGGED_IN_COOKIE => 'logged_in',
	AUTH_COOKIE      => 'auth',
) as $structure_cookie => $structure_scheme ) {
	$structure_cookies[] = array(
		'name'     => $structure_cookie,
		'value'    => wp_generate_auth_cookie( $structure_actor, $structure_expiry, $structure_scheme, $structure_token ),
		'domain'   => wp_parse_url( home_url(), PHP_URL_HOST ),
		'path'     => '/',
		'expires'  => $structure_expiry,
		'httpOnly' => true,
		'secure'   => false,
		'sameSite' => 'Lax',
	);
}
$structure_fixture = array(
	'actor'    => $structure_actor,
	'token'    => $structure_token,
	'course'   => $structure_course,
	'cookies'  => $structure_cookies,
	'base'     => home_url(),
	'units'    => Catalog::chapters( $structure_course ),
	'contents' => array( $structure_lesson, $structure_library_lesson, $structure_extra_lesson, $structure_quiz ),
	'skills'   => array( (int) $structure_skill['term_id'] ),
);
file_put_contents( $structure_path, wp_json_encode( $structure_fixture ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Ephemeral fixture removed during cleanup.
echo "Structure browser fixture ready.\n";
