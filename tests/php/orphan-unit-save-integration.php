<?php
/**
 * Regression: stale legacy skill units must not block a new lesson save.
 *
 * @package OhMyLMS
 */

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_STRUCTURE_INTEGRATION' ) ) {
	exit( "Set OHMYLMS_STRUCTURE_INTEGRATION=1 for local fixture checks.\n" );
}
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Learning\Catalog;
use OhMyLMS\Learning\CourseProgram;
use OhMyLMS\Learning\Schema;
use OhMyLMS\Skills\Taxonomy;

/**
 * Assert a local fixture condition.
 *
 * @param bool   $condition Expected condition.
 * @param string $message Failure description.
 * @throws RuntimeException On failure.
 */
function orphan_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message ); } // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Local test diagnostic only.
}

/**
 * Save the fixture through the authorized REST boundary.
 *
 * @param int   $course Course identifier.
 * @param array $units Unit rows.
 * @param array $program Program rows.
 * @return WP_REST_Response Save response.
 */
function orphan_save( $course, array $units, array $program ) {
	$request = new WP_REST_Request( 'PUT', '/ohmylms/v1/courses/' . $course . '/structure' );
	$request->set_header( 'Content-Type', 'application/json' );
	$request->set_body(
		wp_json_encode(
			array(
				'revision' => Catalog::revision( $course ),
				'units'    => $units,
				'program'  => $program,
			)
		)
	);
	return rest_do_request( $request );
}

$orphan_posts = array();
$orphan_terms = array();
$orphan_admin = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
	)
)[0]->ID;
try {
	wp_set_current_user( $orphan_admin );
	$orphan_course   = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_COURSE_CPT,
			'post_title'  => 'Orphan unit regression fixture',
			'post_status' => 'draft',
		)
	);
	$orphan_posts[]  = $orphan_course;
	$orphan_resource = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_LESSON_CPT,
			'post_title'  => 'Retained lesson fixture',
			'post_status' => 'draft',
		)
	);
	$orphan_posts[]  = $orphan_resource;
	$orphan_group    = wp_generate_uuid4();
	$orphan_unit     = wp_generate_uuid4();
	$orphan_program  = CourseProgram::defaults( $orphan_course );
	foreach ( array( 'proficient', 'mastered' ) as $orphan_target ) {
		$orphan_term                  = wp_insert_term( 'Orphan skill fixture ' . wp_generate_uuid4(), Taxonomy::NAME );
		$orphan_terms[]               = (int) $orphan_term['term_id'];
		$orphan_program['outcomes'][] = array(
			'term_id'    => (int) $orphan_term['term_id'],
			'target'     => $orphan_target,
			'required'   => true,
			'chapter_id' => 999999999,
			'group_id'   => $orphan_group,
		);
	}
	$orphan_program['groups'] = array(
		array(
			'id'         => $orphan_group,
			'title'      => 'Legacy lesson group',
			'chapter_id' => 999999999,
			'skill_ids'  => $orphan_terms,
		),
	);
	update_post_meta( $orphan_course, CourseProgram::DRAFT, $orphan_program );
	$orphan_original                         = $orphan_program;
	$orphan_program['items'][]               = array(
		'id'         => wp_generate_uuid4(),
		'type'       => 'lesson',
		'content_id' => $orphan_resource,
		'chapter_id' => $orphan_unit,
		'skill_ids'  => $orphan_terms,
		'required'   => true,
		'title'      => 'My unsaved lesson',
	);
	$orphan_units                            = array(
		array(
			'id'    => $orphan_unit,
			'title' => 'My unsaved unit',
		),
	);
	$orphan_bad                              = $orphan_program;
	$orphan_bad['outcomes'][0]['chapter_id'] = 999999998;
	orphan_check( 400 === orphan_save( $orphan_course, $orphan_units, $orphan_bad )->get_status(), 'A changed unknown outcome reference is rejected.' );
	$orphan_bad                           = $orphan_program;
	$orphan_bad['outcomes'][0]['term_id'] = array( $orphan_terms[0] );
	orphan_check( 400 === orphan_save( $orphan_course, $orphan_units, $orphan_bad )->get_status(), 'Malformed identities are rejected without attempting a legacy repair.' );
	orphan_check( array() === Catalog::chapters( $orphan_course ) && get_post_meta( $orphan_course, CourseProgram::DRAFT, true ) === $orphan_original, 'A failed save rolls back the new unit and retains the stored draft.' );
	$orphan_bad                           = $orphan_program;
	$orphan_bad['items'][0]['chapter_id'] = 999999999;
	orphan_check( 400 === orphan_save( $orphan_course, $orphan_units, $orphan_bad )->get_status(), 'An unknown activity unit is not silently repaired.' );
	$orphan_bad             = $orphan_program;
	$orphan_bad['groups'][] = array(
		'id'         => wp_generate_uuid4(),
		'chapter_id' => 999999999,
		'title'      => 'Unrecognized group',
	);
	orphan_check( 400 === orphan_save( $orphan_course, $orphan_units, $orphan_bad )->get_status(), 'A new unknown group reference is rejected.' );
	$orphan_saved = orphan_save( $orphan_course, $orphan_units, $orphan_program );
	orphan_check( 200 === $orphan_saved->get_status(), 'Stored orphan associations are repaired while saving the new unit and lesson.' );
	$orphan_data     = $orphan_saved->get_data();
	$orphan_expected = array_map(
		static function ( $row ) {
			unset( $row['chapter_id'], $row['group_id'] );
			return $row;
		},
		$orphan_original['outcomes']
	);
	orphan_check( $orphan_data['program']['outcomes'] === $orphan_expected, 'Every skill, target and completion requirement is preserved as a course-level outcome.' );
	orphan_check( empty( $orphan_data['program']['groups'] ), 'Obsolete legacy groups no longer reference removed units.' );
	orphan_check( 'My unsaved lesson' === $orphan_data['program']['items'][0]['title'] && $orphan_terms === $orphan_data['program']['items'][0]['skill_ids'], 'The new lesson name and skill connections are retained.' );
	orphan_check( $orphan_data['units'][0]['id'] === $orphan_data['program']['items'][0]['chapter_id'], 'The new lesson points to the newly saved native unit.' );
	orphan_check( ! CourseProgram::current( $orphan_course ), 'Repair saves a draft without publishing.' );
	orphan_check( 200 === orphan_save( $orphan_course, $orphan_data['units'], $orphan_data['program'] )->get_status() && 1 === count( Catalog::chapters( $orphan_course ) ), 'Saving again keeps the same unit and does not duplicate skills.' );
	echo "PASS: 12 orphan-unit save regression checks.\n";
} finally {
	wp_set_current_user( $orphan_admin );
	global $wpdb;
	foreach ( Catalog::chapters( $orphan_course ?? 0 ) as $orphan_cleanup_unit ) {
		$wpdb->delete( $wpdb->prefix . 'ohmylms_content_relationship', array( 'chapter_id' => $orphan_cleanup_unit['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Only fixture content relationships.
		wp_delete_post( $orphan_cleanup_unit['id'], true );
	}
	$wpdb->delete( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'course_id' => $orphan_course ?? 0 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Only fixture unit relationships.
	$wpdb->delete( Schema::table( 'programs' ), array( 'course_id' => $orphan_course ?? 0 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Only fixture drafts.
	foreach ( array_reverse( $orphan_posts ) as $orphan_cleanup_post ) {
		wp_delete_post( $orphan_cleanup_post, true ); }
	foreach ( $orphan_terms as $orphan_cleanup_term ) {
		wp_delete_term( $orphan_cleanup_term, Taxonomy::NAME ); }
}
