<?php
/**
 * Temporary local fixtures for the shared structure boundary.
 *
 * @package OhMyLMS
 */

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_STRUCTURE_INTEGRATION' ) ) {
	exit( "Set OHMYLMS_STRUCTURE_INTEGRATION=1 for local fixture checks.\n" );
}

require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Learning\Catalog;
use OhMyLMS\Learning\CourseProgram;
use OhMyLMS\Learning\CourseStructure;
use OhMyLMS\Learning\StructurePlan;

$structure_checks = 0;
$structure_posts  = array();
$structure_users  = array();
$structure_admin  = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
	)
)[0]->ID;

/**
 * Assert one local integration condition.
 *
 * @param bool   $condition Expected result.
 * @param string $message Failure description.
 * @throws RuntimeException When the condition is false.
 */
function structure_check( $condition, $message ) {
	global $structure_checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI-only diagnostics.
	}
	++$structure_checks;
}

/**
 * Call a route with a structure snapshot token.
 *
 * @param string      $method HTTP method.
 * @param string      $path Route suffix.
 * @param array       $body JSON body.
 * @param string|null $revision Expected token.
 * @return WP_REST_Response
 */
function structure_api( $method, $path, array $body = array(), $revision = null ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/' . $path );
	$request->set_header( 'Content-Type', 'application/json' );
	$request->set_body( wp_json_encode( $body ) );
	if ( null !== $revision ) {
		$request->set_header( 'X-OhMyLMS-Structure-Revision', $revision );
	}
	return rest_do_request( $request );
}

try {
	wp_set_current_user( $structure_admin );
	$structure_course  = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_COURSE_CPT,
			'post_title'  => 'Structure fixture ' . wp_generate_uuid4(),
			'post_status' => 'publish',
		)
	);
	$structure_posts[] = $structure_course;
	$structure_other   = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_COURSE_CPT,
			'post_title'  => 'Other structure fixture',
			'post_status' => 'draft',
		)
	);
	$structure_posts[] = $structure_other;
	$structure_first   = Catalog::add_chapter( $structure_course, 'First unit' )['id'];
	$structure_second  = Catalog::add_chapter( $structure_course, 'Second unit' )['id'];
	$structure_foreign = Catalog::add_chapter( $structure_other, 'Foreign unit' )['id'];
	array_push( $structure_posts, $structure_first, $structure_second, $structure_foreign );
	$structure_lesson  = wp_insert_post(
		array(
			'post_type'    => OHMYLMS_LESSON_CPT,
			'post_title'   => 'Reusable title',
			'post_content' => 'Reusable body',
			'post_status'  => 'publish',
		)
	);
	$structure_posts[] = $structure_lesson;
	$read              = structure_api( 'GET', 'courses/' . $structure_course . '/structure' );
	structure_check( 200 === $read->get_status(), 'Normalized structure read succeeds.' );
	$revision = $read->get_data()['revision'];
	structure_check( 32 === strlen( $revision ), 'Opaque revision keeps the established 32-character contract.' );
	structure_check( $read->get_headers()['X-OhMyLMS-Structure-Revision'] === $revision, 'Read header and body agree.' );
	structure_check( 409 === structure_api( 'PUT', 'chapters/' . $structure_first, array( 'name' => 'Missing token' ) )->get_status(), 'Legacy writes require a read token.' );
	$renamed = structure_api(
		'PUT',
		'chapters/' . $structure_first,
		array(
			'name'        => 'Renamed unit',
			'description' => '<p>Unit detail</p>',
		),
		$revision
	);
	structure_check( 200 === $renamed->get_status(), 'Legacy chapter save works: ' . wp_json_encode( $renamed->get_data() ) );
	structure_check( 'Renamed unit' === get_post_field( 'post_title', $structure_first ), 'Native authoring title is saved.' );
	structure_check( Catalog::revision( $structure_course ) !== $revision, 'Unit metadata invalidates the program revision.' );
	structure_check( 409 === structure_api( 'PUT', 'content-hub/catalog/' . $structure_course . '/chapters/' . $structure_second, array( 'name' => 'Stale title' ), $revision )->get_status(), 'Content Hub rejects a token invalidated by the legacy writer.' );
	$revision = Catalog::revision( $structure_course );
	$invalid  = structure_api(
		'POST',
		'courses/' . $structure_course . '/chapters',
		array(
			array(
				'id'   => $structure_foreign,
				'name' => 'Injected',
			),
		),
		$revision
	);
	structure_check( 400 === $invalid->get_status(), 'Foreign unit injection rejected.' );
	structure_check( 'Foreign unit' === get_the_title( $structure_foreign ), 'Foreign unit remains unchanged.' );
	$invalid = structure_api(
		'POST',
		'courses/' . $structure_course . '/chapters',
		array(
			array(
				'id'   => $structure_first,
				'name' => 'Partial',
			),
		),
		$revision
	);
	structure_check( 400 === $invalid->get_status(), 'Incomplete unit replacement rejected.' );
	$invalid = structure_api( 'PUT', 'content-hub/catalog/' . $structure_course . '/chapters/order', array( 'ids' => array( $structure_first, $structure_first ) ), $revision );
	structure_check( 400 === $invalid->get_status(), 'Duplicate reorder IDs rejected.' );
	$rollback = structure_api(
		'POST',
		'courses/' . $structure_course . '/chapters',
		array(
			array(
				'id'   => $structure_first,
				'name' => 'Must roll back',
			),
			array(
				'id'   => $structure_second,
				'name' => '',
			),
		),
		$revision
	);
	structure_check( 400 === $rollback->get_status() && 'Renamed unit' === get_post_field( 'post_title', $structure_first ), 'A later validation error rolls back an earlier row write.' );
	structure_check( Catalog::revision( $structure_course ) === $revision, 'Rollback retains the original revision.' );
	global $wpdb;
	$structure_updates    = 0;
	$structure_fail_sql   = static function ( $query ) use ( &$structure_updates, $wpdb ) {
		if ( 0 === strpos( $query, 'UPDATE `' . $wpdb->prefix . 'ohmylms_chapter_relationship`' ) && 2 === ++$structure_updates ) {
			return 'INTENTIONAL STRUCTURE FIXTURE SQL FAILURE';
		}
		return $query;
	};
	$structure_suppressed = $wpdb->suppress_errors( true );
	add_filter( 'query', $structure_fail_sql );
	try {
		$structure_failed_order = CourseStructure::change(
			$structure_course,
			$revision,
			static function () use ( $structure_course, $structure_first, $structure_second ) {
				return Catalog::reorder_chapters( $structure_course, array( $structure_second, $structure_first ) );
			}
		);
	} finally {
		remove_filter( 'query', $structure_fail_sql );
		$wpdb->suppress_errors( $structure_suppressed );
	}
	structure_check( is_wp_error( $structure_failed_order ) && 500 === $structure_failed_order->get_error_data()['status'], 'A database ordering failure is reported.' );
	structure_check( Catalog::revision( $structure_course ) === $revision && Catalog::chapters( $structure_course )[0]['id'] === $structure_first, 'A later SQL failure rolls back earlier ordering writes.' );
	$ordered = structure_api(
		'POST',
		'courses/' . $structure_course . '/chapters',
		array(
			array(
				'id'           => $structure_first,
				'name'         => 'Renamed unit',
				'order_number' => 2,
			),
			array(
				'id'           => $structure_second,
				'name'         => 'Second unit',
				'order_number' => 1,
			),
		),
		$revision
	);
	structure_check( 200 === $ordered->get_status(), 'Complete legacy reorder succeeds.' );
	structure_check( Catalog::chapters( $structure_course )[0]['id'] === $structure_second, 'Explicit positions determine canonical unit order.' );
	$contents = structure_api(
		'POST',
		'chapters/' . $structure_first . '/content',
		array(
			array(
				'id'           => $structure_lesson,
				'name'         => 'Do not rename shared content',
				'type'         => 'text',
				'order_number' => 1,
			),
		),
		Catalog::revision( $structure_course )
	);
	structure_check( 200 === $contents->get_status(), 'Legacy content placement succeeds: ' . wp_json_encode( $contents->get_data() ) );
	structure_check( 'Reusable title' === get_the_title( $structure_lesson ), 'Placement saves do not rename shared resources.' );
	structure_check( 'Reusable body' === get_post_field( 'post_content', $structure_lesson ), 'Placement saves retain content bodies.' );
	$defaults = CourseProgram::defaults( $structure_course );
	structure_check( CourseProgram::defaults( $structure_course )['items'][0]['id'] === $defaults['items'][0]['id'], 'Native fallback placement identities are stable across reads.' );
	$placement_id                    = wp_generate_uuid4();
	$group_id                        = wp_generate_uuid4();
	$program                         = $defaults;
	$program['items'][0]['id']       = $placement_id;
	$program['items'][0]['group_id'] = $group_id;
	$program['groups']               = array(
		array(
			'id'         => $group_id,
			'chapter_id' => $structure_first,
			'title'      => 'Reading group',
		),
	);
	$saved                           = structure_api( 'PUT', 'courses/' . $structure_course . '/learning', $program, Catalog::revision( $structure_course ) );
	structure_check( 200 === $saved->get_status(), 'Learning writer uses the same read token.' );
	$view = structure_api( 'GET', 'courses/' . $structure_course . '/structure' )->get_data();
	// Since b0e21ca5 lesson groups are flattened into their unit, so the placement is listed ungrouped.
	structure_check( $view['units'][0]['id'] === $structure_second && array() === $view['units'][1]['groups'] && $view['units'][1]['ungrouped'][0]['id'] === $placement_id && ! isset( $view['program']['groups'] ), 'Shared view preserves empty units, canonical order and placement IDs, with lesson groups flattened: ' . wp_json_encode( $view['units'][1] ?? null ) );
	$learner = \OhMyLMS\Student\CourseOutline::get( $structure_course, 0 );
	structure_check( array() === $learner['units'], 'An unpublished native addition stays out of the learner outline.' );
	$published = structure_api( 'POST', 'courses/' . $structure_course . '/learning/publish', $program, Catalog::revision( $structure_course ) );
	structure_check( 200 === $published->get_status(), 'Program publication retains existing validation.' );
	$structure_original_program = CourseProgram::current( $structure_course );
	$structure_bound            = array();
	foreach ( array( 'in-progress', 'completed' ) as $structure_progress_state ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Temporary enrollments belong only to the fixture course.
		$wpdb->insert(
			$wpdb->prefix . 'ohmylms_user_enrollment',
			array(
				'user_id'    => 'completed' === $structure_progress_state ? 0 : $structure_admin,
				'course_id'  => $structure_course,
				'status'     => 'enrolled',
				'progress'   => $structure_progress_state,
				'start_date' => current_time( 'mysql', true ),
			)
		);
		$structure_bound[ $structure_progress_state ] = array(
			'id'        => $wpdb->insert_id,
			'course_id' => $structure_course,
		);
		structure_check( CourseProgram::for_enrollment( $structure_bound[ $structure_progress_state ] )['id'] === $structure_original_program['id'], 'Enrollment starts on the current program.' );
	}
	$structure_pre_edit_revision = Catalog::revision( $structure_course );
	$structure_unit_edit         = structure_api(
		'PUT',
		'chapters/' . $structure_first,
		array(
			'name'        => 'Draft-only title',
			'description' => '<p>Draft-only description</p>',
			'number'      => '2A',
		),
		$structure_pre_edit_revision
	);
	structure_check( 200 === $structure_unit_edit->get_status(), 'Metadata edit is saved as a draft.' );
	$structure_order_edit = structure_api( 'PUT', 'content-hub/catalog/' . $structure_course . '/chapters/order', array( 'ids' => array( $structure_first, $structure_second ) ), Catalog::revision( $structure_course ) );
	structure_check( 200 === $structure_order_edit->get_status(), 'Unit reordering is saved as a draft.' );
	$structure_draft_preview = structure_api( 'GET', 'courses/' . $structure_course . '/structure/preview' )->get_data();
	structure_check( 'Draft-only title' === $structure_draft_preview['units'][0]['title'] && '2A' === $structure_draft_preview['units'][0]['number'] && '<p>Draft-only description</p>' === $structure_draft_preview['units'][0]['description'], 'Preview includes saved draft metadata and order.' );
	structure_check( CourseProgram::current( $structure_course ) === $structure_original_program, 'Saving and previewing leave the published program unchanged.' );
	structure_check( 'Renamed unit' === get_the_title( $structure_first ) && '' === \OhMyLMS\Curriculum\UnitNumber::get( $structure_first ), 'Legacy public titles and unit numbers retain the assigned publication.' );
	structure_check( 'Renamed unit' === ohmylms_get_chapter( $structure_first )->get_name() && '<p>Unit detail</p>' === ohmylms_get_chapter( $structure_first )->get_description(), 'Legacy chapter objects retain published metadata.' );
	structure_check( ohmylms_get_course( $structure_course )->get_chapters()[0]['id'] === $structure_second, 'Legacy course templates retain published unit order.' );
	$structure_learner_view = \OhMyLMS\Student\CourseOutline::get( $structure_course, $structure_admin );
	structure_check( 'Renamed unit' === $structure_learner_view['units'][0]['title'], 'The learner hub does not leak a draft title.' );
	structure_check( 409 === structure_api( 'POST', 'courses/' . $structure_course . '/structure/publish', array(), $structure_pre_edit_revision )->get_status(), 'A stale publication is rejected.' );
	$structure_publication = structure_api( 'POST', 'courses/' . $structure_course . '/structure/publish', array(), $structure_draft_preview['revision'] );
	structure_check( 200 === $structure_publication->get_status(), 'Explicit outline publication succeeds.' );
	$structure_new_program = CourseProgram::current( $structure_course );
	structure_check( 'Draft-only title' === $structure_new_program['units'][0]['name'] && '2A' === $structure_new_program['units'][0]['number'], 'Publication snapshots current metadata and order.' );
	structure_check( CourseProgram::get( $structure_original_program['id'] ) === $structure_original_program, 'An earlier program version remains immutable.' );
	structure_check( CourseProgram::for_enrollment( $structure_bound['in-progress'] )['id'] === $structure_original_program['id'], 'Ordinary publishing keeps existing learners on their assigned version.' );
	structure_check( 'Draft-only title' === \OhMyLMS\Student\CourseOutline::get( $structure_course, 0 )['units'][0]['title'], 'Visitors see the new published outline.' );
	$structure_apply       = structure_api( 'POST', 'courses/' . $structure_course . '/structure/publish', array( 'apply_existing' => true ), Catalog::revision( $structure_course ) );
	$structure_upgraded_id = CourseProgram::for_enrollment( $structure_bound['in-progress'] )['id'];
	structure_check( 200 === $structure_apply->get_status() && CourseProgram::current( $structure_course )['id'] === $structure_upgraded_id, 'Explicit apply-existing upgrades an unfinished enrollment.' );
	structure_check( CourseProgram::for_enrollment( $structure_bound['completed'] )['id'] === $structure_original_program['id'], 'Completed learners retain their previous version.' );
	$structure_published_id            = CourseProgram::current( $structure_course )['id'];
	$structure_empty_program           = $program;
	$structure_empty_program['items']  = array();
	$structure_empty_program['groups'] = array();
	structure_check( 200 === structure_api( 'PUT', 'courses/' . $structure_course . '/learning', $structure_empty_program, Catalog::revision( $structure_course ) )->get_status(), 'An incomplete learning program can be saved privately.' );
	structure_check( 409 === structure_api( 'POST', 'courses/' . $structure_course . '/structure/publish', array(), Catalog::revision( $structure_course ) )->get_status() && CourseProgram::current( $structure_course )['id'] === $structure_published_id, 'Readiness failure leaves the published pointer unchanged.' );
	structure_check( 200 === structure_api( 'PUT', 'courses/' . $structure_course . '/learning', $program, Catalog::revision( $structure_course ) )->get_status(), 'The ready program can be restored as a draft.' );
	$blocked = structure_api( 'DELETE', 'chapters/' . $structure_first, array(), Catalog::revision( $structure_course ) );
	structure_check( 409 === $blocked->get_status(), 'Legacy deletion cannot cascade-delete a populated or published unit.' );
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Temporary progress belongs only to this fixture's lesson.
	$wpdb->insert(
		$wpdb->prefix . 'ohmylms_user_progress',
		array(
			'enrollment_id' => 0,
			'content_id'    => $structure_lesson,
			'content_type'  => 'lesson',
			'status'        => 'completed',
			'start_date'    => current_time( 'mysql', true ),
		)
	);
	$structure_progress = $wpdb->insert_id;
	structure_check( 0 < $structure_progress, 'Temporary learner history was created.' );
	$detached = structure_api( 'DELETE', 'chapters/' . $structure_first . '/content/' . $structure_lesson, array(), Catalog::revision( $structure_course ) );
	structure_check( 200 === $detached->get_status() && 'publish' === get_post_status( $structure_lesson ), 'Removing a placement preserves its reusable content.' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Verify the fixture's learner history survived placement removal.
	structure_check( 'completed' === $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM %i WHERE id=%d', $wpdb->prefix . 'ohmylms_user_progress', $structure_progress ) ), 'Removing a placement preserves learner history.' );
	structure_check( CourseProgram::current( $structure_course )['items'][0]['id'] === $placement_id, 'Removing a native placement does not rewrite the published program.' );
	$deny = static function ( $caps, $cap, $actor, $args ) use ( $structure_course ) {
		return 'edit_post' === $cap && (int) ( $args[0] ?? 0 ) === $structure_course ? array( 'do_not_allow' ) : $caps;
	};
	add_filter( 'map_meta_cap', $deny, 10, 4 );
	structure_check( 403 === structure_api( 'GET', 'chapters/' . $structure_first . '/content' )->get_status(), 'Course-specific denial applies to legacy reads.' );
	structure_check( 403 === structure_api( 'PUT', 'chapters/' . $structure_first, array( 'name' => 'Forbidden' ), Catalog::revision( $structure_course ) )->get_status(), 'Course-specific denial applies to legacy writes.' );
	remove_filter( 'map_meta_cap', $deny, 10 );
	wp_set_current_user( 0 );
	structure_check( 401 === structure_api( 'GET', 'courses/' . $structure_course . '/structure' )->get_status(), 'Anonymous private structure access denied.' );
	structure_check( 401 === structure_api( 'GET', 'courses/' . $structure_course . '/structure/preview' )->get_status(), 'Anonymous draft preview denied.' );
	structure_check( 401 === structure_api( 'POST', 'courses/' . $structure_course . '/structure/publish' )->get_status(), 'Anonymous outline publication denied.' );
	wp_set_current_user( $structure_admin );
	$structure_group_uuid = wp_generate_uuid4();
	$structure_now        = current_time( 'mysql', true );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Isolated syllabus fixture; no existing curriculum ordering is changed.
	$wpdb->insert(
		\OhMyLMS\Curriculum\Items::table(),
		array(
			'uuid'        => wp_generate_uuid4(),
			'name'        => 'Mirror fixture',
			'is_syllabus' => 1,
			'course_id'   => $structure_other,
			'created_at'  => $structure_now,
			'updated_at'  => $structure_now,
		)
	);
	$structure_syllabus = $wpdb->insert_id;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Isolated group fixture with no skill placements.
	$wpdb->insert(
		\OhMyLMS\Curriculum\Syllabus::groups_table(),
		array(
			'uuid'       => $structure_group_uuid,
			'item_id'    => $structure_syllabus,
			'name'       => 'Mirrored topic',
			'created_at' => $structure_now,
			'updated_at' => $structure_now,
		)
	);
	$structure_syllabus_group = $wpdb->insert_id;
	update_post_meta( $structure_other, \OhMyLMS\Curriculum\SyllabusCourse::ITEM_META, $structure_syllabus );
	update_post_meta( $structure_foreign, \OhMyLMS\Curriculum\SyllabusCourse::GROUP_META, $structure_group_uuid );
	$structure_before_mirror = Catalog::revision( $structure_other );
	structure_check( true === \OhMyLMS\Curriculum\SyllabusCourse::sync( $structure_syllabus ), 'Syllabus synchronization succeeds under the shared transaction.' );
	$structure_mirror_view = structure_api( 'GET', 'courses/' . $structure_other . '/structure' )->get_data();
	structure_check( $structure_foreign === $structure_mirror_view['units'][0]['id'] && 'Mirrored topic' === $structure_mirror_view['units'][0]['title'], 'Syllabus synchronization preserves the mirrored unit ID and updates the shared view.' );
	structure_check( 409 === structure_api( 'PUT', 'chapters/' . $structure_foreign, array( 'name' => 'Stale mirror edit' ), $structure_before_mirror )->get_status(), 'A mirror update invalidates an earlier dashboard token.' );
	structure_check(
		array_keys(
			StructurePlan::order_units(
				array(
					9 => array(),
					2 => array(),
					0 => array(),
				),
				array( array( 'id' => 2 ), array( 'id' => 9 ) )
			)
		) === array(
			2,
			9,
			0,
		),
		'Legacy unplaced units are retained after canonical units.'
	);
	echo 'PASS: ' . (int) $structure_checks . " shared structure integration checks.\n";
} finally {
	wp_set_current_user( $structure_admin );
	global $wpdb;
	foreach ( $structure_bound ?? array() as $structure_binding ) {
		$wpdb->delete( \OhMyLMS\Learning\Schema::table( 'enrollments' ), array( 'enrollment_id' => $structure_binding['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Only this fixture's program bindings.
		$wpdb->delete( $wpdb->prefix . 'ohmylms_user_enrollment', array( 'id' => $structure_binding['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Only this fixture's native enrollments.
	}

	if ( ! empty( $structure_syllabus_group ) ) {
		$wpdb->delete( \OhMyLMS\Curriculum\Syllabus::groups_table(), array( 'id' => $structure_syllabus_group ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Only this fixture's group.
	}
	if ( ! empty( $structure_syllabus ) ) {
		$wpdb->delete( \OhMyLMS\Curriculum\Items::table(), array( 'id' => $structure_syllabus ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Only this fixture's syllabus.
	}
	if ( ! empty( $structure_progress ) ) {
		$wpdb->delete( $wpdb->prefix . 'ohmylms_user_progress', array( 'id' => $structure_progress ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Remove only this fixture's temporary progress.
	}
	foreach ( array( $structure_course ?? 0, $structure_other ?? 0 ) as $structure_cleanup_course ) {
		foreach ( Catalog::chapters( $structure_cleanup_course ) as $structure_cleanup_unit ) {
			$wpdb->delete( $wpdb->prefix . 'ohmylms_content_relationship', array( 'chapter_id' => $structure_cleanup_unit['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Remove only this test fixture's native rows.
		}
		$wpdb->delete( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'course_id' => $structure_cleanup_course ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Remove only this test fixture's native rows.
		$wpdb->delete( \OhMyLMS\Learning\Schema::table( 'outcomes' ), array( 'program_id' => (int) get_post_meta( $structure_cleanup_course, CourseProgram::CURRENT, true ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Only this fixture's published program outcomes.
		$wpdb->delete( \OhMyLMS\Learning\Schema::table( 'programs' ), array( 'course_id' => $structure_cleanup_course ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Only programs created for this fixture.
	}
	foreach ( array_reverse( $structure_posts ) as $structure_cleanup_post ) {
		wp_delete_post( $structure_cleanup_post, true );
	}
}
