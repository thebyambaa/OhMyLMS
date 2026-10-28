<?php
/**
 * Single syllabus alignment, separate from roadmap measurement and course connections.
 *
 * @package OhMyLMS
 */

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_STRUCTURE_INTEGRATION' ) ) {
	exit( "Set OHMYLMS_STRUCTURE_INTEGRATION=1 for local fixture checks.\n" );
}
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Curriculum\CourseObjectives;
use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Syllabus;
use OhMyLMS\Learning\CourseProgram;
use OhMyLMS\Skills\Taxonomy;

/**
 * Assert a fixture condition.
 *
 * @param bool   $condition Expected condition.
 * @param string $message Diagnostic message.
 * @throws RuntimeException When an assertion fails.
 */
function single_syllabus_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Local diagnostic.
	}
}

/**
 * Save a choice or objective references through the existing permission boundary.
 *
 * @param int   $course Course ID.
 * @param array $data Save request.
 * @return WP_REST_Response REST response.
 */
function single_syllabus_save( $course, array $data ) {
	$request = new WP_REST_Request( 'PUT', '/ohmylms/v1/courses/' . $course . '/syllabus-objectives' );
	$request->set_header( 'Content-Type', 'application/json' );
	$request->set_body( wp_json_encode( $data + array( 'revision' => CourseObjectives::payload( $course )['revision'] ) ) );
	return rest_do_request( $request );
}

$single_actor   = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
	)
)[0]->ID;
$single_sources = array();
$single_terms   = array();
$single_posts   = array();
try {
	wp_set_current_user( $single_actor );
	foreach ( array( 'syllabus', 'syllabus', 'roadmap' ) as $single_kind ) {
		$single_item      = Items::create(
			array(
				'name'        => 'Single source fixture ' . wp_generate_uuid4(),
				'item_type'   => $single_kind,
				'is_syllabus' => true,
			)
		);
		$single_sources[] = (int) $single_item['id'];
		$single_group     = Syllabus::add_group(
			$single_item['id'],
			array(
				'item_id' => $single_item['id'],
				'name'    => 'Fixture skills',
			)
		);
		$single_term      = wp_insert_term( 'Single source skill ' . wp_generate_uuid4(), Taxonomy::NAME );
		$single_terms[]   = (int) $single_term['term_id'];
		Syllabus::add_skill( $single_item['id'], $single_group, $single_term['term_id'] );
	}
	$single_course              = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_COURSE_CPT,
			'post_title'  => 'Single syllabus fixture',
			'post_status' => 'draft',
		)
	);
	$single_posts[]             = $single_course;
	$single_lesson              = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_LESSON_CPT,
			'post_title'  => 'Retained connection fixture',
			'post_status' => 'draft',
		)
	);
	$single_posts[]             = $single_lesson;
	$single_program             = CourseProgram::defaults( $single_course );
	$single_program['mode']     = 'traditional';
	$single_program['outcomes'] = array(
		array(
			'term_id'  => $single_terms[0],
			'target'   => 'mastered',
			'required' => true,
		),
	);
	$single_program['items']    = array(
		array(
			'id'         => wp_generate_uuid4(),
			'type'       => 'lesson',
			'content_id' => $single_lesson,
			'chapter_id' => 0,
			'required'   => true,
			'skill_ids'  => array( $single_terms[0] ),
		),
	);
	CourseProgram::save( $single_course, $single_program );
	$single_before  = get_post_meta( $single_course, CourseProgram::DRAFT, true );
	$single_roadmap = Items::get( $single_sources[2] );
	single_syllabus_check( ! in_array( $single_sources[2], array_column( CourseObjectives::payload( $single_course )['syllabuses'], 'id' ), true ), 'Roadmaps are excluded from course choices.' );
	single_syllabus_check( 200 === single_syllabus_save( $single_course, array( 'syllabus_id' => $single_sources[0] ) )->get_status(), 'An author can select one syllabus without selecting individual objectives.' );
	$single_payload = CourseObjectives::payload( $single_course );
	single_syllabus_check( array( $single_sources[0] ) === $single_payload['source_ids'] && ! $single_payload['mappings'], 'An explicit source is retained with zero selected objective references.' );
	$single_revision = $single_payload['revision'];
	foreach ( array( $single_sources[2], 999999999, array( $single_sources[0], $single_sources[1] ) ) as $single_bad ) {
		single_syllabus_check( 400 === single_syllabus_save( $single_course, array( 'syllabus_id' => $single_bad ) )->get_status(), 'Roadmaps, missing sources and multiple source values are rejected.' );
	}
	single_syllabus_check(
		200 === single_syllabus_save(
			$single_course,
			array(
				'mappings' => array(
					array(
						'syllabus_id' => $single_sources[0],
						'term_id'     => $single_terms[0],
					),
				),
			)
		)->get_status(),
		'References to the chosen syllabus remain compatible.'
	);
	single_syllabus_check( 200 === single_syllabus_save( $single_course, array( 'syllabus_id' => $single_sources[1] ) )->get_status(), 'A different single syllabus can be chosen.' );
	$single_payload = CourseObjectives::payload( $single_course );
	single_syllabus_check( $single_payload['syllabus_id'] === $single_sources[1] && ! $single_payload['mappings'], 'Old alignment references are replaced when choosing a different source.' );
	single_syllabus_check( get_post_meta( $single_course, CourseProgram::DRAFT, true ) === $single_before, 'Existing course outcomes, completion requirements and lesson skill connections are preserved.' );
	single_syllabus_check(
		409 === single_syllabus_save(
			$single_course,
			array(
				'syllabus_id' => $single_sources[0],
				'revision'    => $single_revision,
			)
		)->get_status(),
		'A stale source choice cannot overwrite the current selection.'
	);
	single_syllabus_check(
		400 === single_syllabus_save(
			$single_course,
			array(
				'mappings' => array(
					array(
						'syllabus_id' => $single_sources[0],
						'term_id'     => $single_terms[0],
					),
					array(
						'syllabus_id' => $single_sources[1],
						'term_id'     => $single_terms[1],
					),
				),
			)
		)->get_status(),
		'Legacy mapping requests cannot introduce multiple syllabus sources.'
	);
	single_syllabus_check( Items::get( $single_sources[2] ) === $single_roadmap, 'Roadmap structure and comparison relationships are untouched.' );
	wp_set_current_user( 0 );
	single_syllabus_check( single_syllabus_save( $single_course, array( 'syllabus_id' => $single_sources[0] ) )->get_status() >= 400, 'Anonymous alignment writes are denied.' );
	echo "PASS: single syllabus choice, roadmap exclusion, zero-objective persistence, connection preservation, conflicts and authorization.\n";
} finally {
	wp_set_current_user( $single_actor );
	global $wpdb;
	foreach ( array_reverse( $single_posts ) as $single_post ) {
		wp_delete_post( $single_post, true );
	}
	foreach ( array_reverse( $single_sources ) as $single_source ) {
		Syllabus::delete_for_items( array( $single_source ) );
		$wpdb->delete( Items::table(), array( 'id' => $single_source ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture source cleanup.
	}
	foreach ( $single_terms as $single_term ) {
		wp_delete_term( $single_term, Taxonomy::NAME );
	}
}
