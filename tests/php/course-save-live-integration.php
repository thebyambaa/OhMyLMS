<?php
/**
 * One course save activates saved resources and updates active learners atomically.
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

/**
 * Assert a fixture condition.
 *
 * @param bool   $condition Expected condition.
 * @param string $message Diagnostic message.
 * @throws RuntimeException When an assertion fails.
 */
function live_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Local diagnostic.
	}
}
/**
 * Send a complete, revision-guarded course save.
 *
 * @param int         $course Course ID.
 * @param array       $program Complete program.
 * @param string|null $revision Optional stale revision.
 * @return WP_REST_Response REST response.
 */
function live_save( $course, $program, $revision = null ) {
	$request = new WP_REST_Request( 'PUT', '/ohmylms/v1/courses/' . $course . '/structure' );
	$request->set_header( 'Content-Type', 'application/json' );
	$request->set_body(
		wp_json_encode(
			array(
				'revision' => $revision ?? Catalog::revision( $course ),
				'units'    => array(),
				'program'  => $program,
				'activate' => true,
			)
		)
	);
	return rest_do_request( $request );
}

$live_admin       = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
	)
)[0]->ID;
$live_posts       = array();
$live_enrollments = array();
global $wpdb;
try {
	wp_set_current_user( $live_admin );
	foreach ( array(
		'course' => OHMYLMS_COURSE_CPT,
		'lesson' => OHMYLMS_LESSON_CPT,
		'quiz'   => OHMYLMS_QUIZ_CPT,
	) as $kind => $live_post_type ) {
		$live_posts[ $kind ] = wp_insert_post(
			array(
				'post_type'    => $live_post_type,
				'post_title'   => 'One-save ' . $kind . ' fixture',
				'post_status'  => 'draft',
				'post_content' => '<!-- wp:paragraph --><p>Saved content.</p><!-- /wp:paragraph -->',
			)
		);
	}
	$live_course              = $live_posts['course'];
	$live_program             = CourseProgram::defaults( $live_course );
	$live_program['mode']     = 'traditional';
	$live_program['groups']   = array();
	$live_program['outcomes'] = array();
	$live_program['items']    = array();
	foreach ( array( 'lesson', 'quiz' ) as $kind ) {
		$live_program['items'][] = array(
			'id'         => wp_generate_uuid4(),
			'type'       => $kind,
			'content_id' => $live_posts[ $kind ],
			'chapter_id' => 0,
			'required'   => true,
		);
	}
	$live_result = live_save( $live_course, $live_program );
	live_check( 200 === $live_result->get_status(), 'A single save activates the complete outline.' );
	$live_old = CourseProgram::current( $live_course );
	live_check( ! empty( $live_old['id'] ), 'Learners have a current program after one save.' );
	foreach ( $live_posts as $live_post_id ) {
		live_check( 'publish' === get_post_status( $live_post_id ), 'The course and saved resources need no separate publish step.' );
	}
	foreach ( array( 'in-progress', 'completed' ) as $progress ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Temporary fixture enrollment.
		$wpdb->insert(
			$wpdb->prefix . 'ohmylms_user_enrollment',
			array(
				'user_id'    => $live_admin,
				'course_id'  => $live_course,
				'status'     => 'enrolled',
				'progress'   => $progress,
				'start_date' => current_time( 'mysql' ),
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Temporary fixture enrollment.
		$live_enrollments[ $progress ] = array(
			'id'        => (int) $wpdb->insert_id,
			'course_id' => $live_course,
		);
		CourseProgram::for_enrollment( $live_enrollments[ $progress ] );
	}
	$live_stale                        = Catalog::revision( $live_course );
	$live_program['items'][0]['title'] = 'Updated saved lesson';
	$live_result                       = live_save( $live_course, $live_program );
	live_check( 200 === $live_result->get_status(), 'Saving an edit updates the live program.' );
	$live_current = CourseProgram::current( $live_course );
	live_check( $live_current['id'] !== $live_old['id'], 'The updated program retains an audit version.' );
	live_check( CourseProgram::for_enrollment( $live_enrollments['in-progress'] )['id'] === $live_current['id'], 'Existing active learners immediately receive the saved outline.' );
	live_check( CourseProgram::for_enrollment( $live_enrollments['completed'] )['id'] === $live_old['id'], 'Completed learner history is preserved.' );
	live_check( 409 === live_save( $live_course, $live_program, $live_stale )->get_status(), 'Stale edits cannot overwrite a newer course save.' );
	$live_program['items'][0]['required'] = false;
	$live_program['items'][1]['required'] = false;
	wp_update_post(
		array(
			'ID'          => $live_posts['lesson'],
			'post_status' => 'draft',
		)
	);
	$live_revision = Catalog::revision( $live_course );
	live_check( 409 === live_save( $live_course, $live_program )->get_status(), 'Invalid completion settings still fail validation.' );
	live_check( 'draft' === get_post_status( $live_posts['lesson'] ), 'Failed saves roll back resource activation.' );
	live_check( Catalog::revision( $live_course ) === $live_revision && CourseProgram::current( $live_course )['id'] === $live_current['id'], 'Failed saves leave the saved outline and learner version unchanged.' );
	wp_set_current_user( 0 );
	live_check( live_save( $live_course, $live_program )->get_status() >= 400, 'Anonymous course activation is denied.' );
	echo "PASS: one-save activation, resources, existing learners, completed history, conflicts, rollback and authorization.\n";
} finally {
	wp_set_current_user( $live_admin );
	foreach ( $live_enrollments as $enrollment ) {
		$wpdb->delete( Schema::table( 'enrollments' ), array( 'enrollment_id' => $enrollment['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture cleanup.
		$wpdb->delete( $wpdb->prefix . 'ohmylms_user_enrollment', array( 'id' => $enrollment['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture cleanup.
	}
	$live_program_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE course_id=%d', Schema::table( 'programs' ), $live_course ?? 0 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fixture cleanup.
	foreach ( $live_program_ids as $live_post_id ) {
		$wpdb->delete( Schema::table( 'outcomes' ), array( 'program_id' => $live_post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture cleanup.
	}
	$wpdb->delete( Schema::table( 'programs' ), array( 'course_id' => $live_course ?? 0 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture cleanup.
	foreach ( array_reverse( $live_posts ) as $live_post_id ) {
		wp_delete_post( $live_post_id, true );
	}
}
