<?php
/**
 * Provenance and home-report isolation against the guarded disposable database.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Family\Schema as FamilySchema;
use OhMyLMS\Family\Service as FamilyService;
use OhMyLMS\Family\Provenance;
use OhMyLMS\Family\Reports;
use OhMyLMS\Schools\Schema;
use OhMyLMS\Schools\Service;

if ( 'cli' !== PHP_SAPI ) {
	exit; }
$credentials_path = getenv( 'OHMYLMS_TEST_CREDENTIALS' );
if ( ! $credentials_path || ! is_file( $credentials_path ) ) {
	throw new RuntimeException( 'Disposable credentials required.' ); }
$credentials = json_decode( file_get_contents( $credentials_path ), true );
require $credentials['site'] . '/wp-load.php';
if ( ! defined( 'OHMYLMS_TEST_SITE' ) || ! OHMYLMS_TEST_SITE || 'ohmylms_source_test' !== DB_NAME ) {
	throw new RuntimeException( 'Disposable database required.' ); }
require_once __DIR__ . '/support/school-report-checks.php';
require_once __DIR__ . '/support/family-management-checks.php';
$fixture             = array();
$count               = 0;
$check               = static function ( $value, $message ) use ( &$count ) {
	if ( ! $value ) {
		throw new RuntimeException( esc_html( $message ) );
	} ++$count;
};
$denied              = static function ( $learner ) use ( $check ) {
	try {
		Reports::read( $learner );
	} catch ( RuntimeException $error ) {
		$check( 403 === $error->getCode(), 'Wrong denial code.' );
		return; }
	throw new RuntimeException( 'Unauthorized report was readable.' );
};
$original            = get_current_user_id();
$verification_saved  = get_option( 'ohmylms_require_email_verification', null );
$school_schema_saved = get_option( 'ohmylms_school_schema' );
try {
	FamilySchema::install();
	ohmylms_school_report_fixture( $fixture );
	$learner                               = $fixture['users'][2];
	list( $course, $chapter, $assignment ) = $fixture['posts'];
	$guardian                              = wp_insert_user(
		array(
			'user_login' => 'home-report-' . wp_generate_password( 12, false ),
			'user_pass'  => wp_generate_password( 40 ),
			'role'       => 'ohmylms_parent',
		)
	);
	$check( ! is_wp_error( $guardian ), 'Guardian fixture failed.' );
	$fixture['users'][] = $guardian;
	wp_set_current_user( $guardian );
	$other              = FamilyService::create_child( 'Other home learner' );
	$fixture['users'][] = $other['child_id'];
	$now                = gmdate( 'Y-m-d H:i:s' );
	$wpdb->insert(
		FamilySchema::table( 'links' ),
		array(
			'guardian_user_id' => $guardian,
			'learner_user_id'  => $learner,
			'scope'            => FamilySchema::SCOPE,
			'status'           => 'active',
			'approved_by'      => $guardian,
			'approval_method'  => 'learner_invitation',
			'created_at'       => $now,
			'updated_at'       => $now,
		)
	);
	$home_link = (int) $wpdb->insert_id;
	$check( $home_link > 0, 'Home link fixture failed.' );
	$before = count( FamilyService::children() );
	update_option( FamilySchema::OPTION, '1' );
	FamilySchema::install();
	FamilySchema::install();
	$check( FamilySchema::enabled() && count( FamilyService::children() ) === $before, 'Additive migration changed links or was not idempotent.' );
	wp_set_current_user( $learner );
	$home   = Provenance::token( 'assignment', $course, $assignment );
	$school = Provenance::token( 'assignment', $course, $assignment, $fixture['learning'] );
	$check( 'home_learning' === Provenance::resolve( $home, 'assignment', $course, $assignment )['origin'], 'Explicit home context failed.' );
	$resolved = Provenance::resolve( $school, 'assignment', $course, $assignment );
	$check( 'school' === $resolved['origin'] && $resolved['school_id'] === $fixture['schools'][0], 'Recipient context failed.' );
	foreach ( array( '', $home . 'x', str_replace( 'assignment:', 'quiz:', $home ) ) as $invalid ) {
		$check( 'unknown' === Provenance::resolve( $invalid, 'assignment', $course, $assignment )['origin'], 'Forged origin accepted.' ); }
	$check( 'unknown' === Provenance::resolve( $home, 'assignment', $course + 1, $assignment )['origin'], 'Cross-course token accepted.' );
	$check( 'unknown' === Provenance::resolve( $home, 'assignment', $course, $assignment + 1 )['origin'], 'Cross-content token accepted.' );
	wp_set_current_user( $other['child_id'] );
	$check( 'unknown' === Provenance::resolve( $home, 'assignment', $course, $assignment )['origin'], 'Cross-user token accepted.' );
	$check( 'unknown' === Provenance::resolve( Provenance::token( 'assignment', $course, $assignment, $fixture['learning'] ), 'assignment', $course, $assignment )['origin'], 'Non-recipient selected school context.' );
	wp_set_current_user( $learner );
	$wpdb->update(
		Schema::table( 'class_memberships' ),
		array( 'status' => 'inactive' ),
		array(
			'class_id' => $fixture['classes'][0],
			'user_id'  => $learner,
		)
	);
	$check( 'unknown' === Provenance::resolve( $school, 'assignment', $course, $assignment )['origin'], 'Revoked class scope remained valid.' );
	$wpdb->update(
		Schema::table( 'class_memberships' ),
		array( 'status' => 'active' ),
		array(
			'class_id' => $fixture['classes'][0],
			'user_id'  => $learner,
		)
	);
	$capture = static function ( $token ) use ( $learner, $course, $assignment ) {
		$_GET['oml_origin'] = $token;
		$object             = ohmylms_get_assignment( $assignment );
		$object->submit_file_submission(
			$learner,
			$course,
			array(
				'files'   => array(),
				'content' => 'Report fixture response',
			)
		);
	};
	$capture( $home );
	$home_source = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(source_id) FROM %i WHERE learner_user_id=%d', FamilySchema::table( 'activity' ), $learner ) );
	$check( $home_source > 0, 'Real assignment submission did not record context.' );
	$capture( $school );
	$capture( '' );
	// Replaying a source event under a different context cannot replace its original classification.
	$_GET['oml_origin'] = $school;
	do_action( 'ohmylms_assignment_submitted', $home_source, $assignment, $course, $learner );
	$check( 'home_learning' === $wpdb->get_var( $wpdb->prepare( 'SELECT origin FROM %i WHERE source_kind=%s AND source_id=%d', FamilySchema::table( 'activity' ), 'assignment', $home_source ) ), 'Replay changed origin.' );
	// An event for another source owner cannot poison that source ID.
	do_action( 'ohmylms_assignment_submitted', $home_source, $assignment, $course, $other['child_id'] );
	wp_set_current_user( $guardian );
	$report = Reports::read( $learner );
	$check( 1 === (int) $report['summary']['activities'] && 1 === count( $report['items'] ), 'School/unknown activity leaked into home report.' );
	$check( 0 === (int) Reports::read( $other['child_id'] )['summary']['activities'], 'Cross-child activity leaked.' );
	$denied( $fixture['users'][0] );
	wp_set_current_user( $fixture['users'][0] );
	$denied( $learner );
	wp_set_current_user( 0 );
	$denied( $learner );
	wp_set_current_user( $guardian );
	$wpdb->update( FamilySchema::table( 'links' ), array( 'status' => 'revoked' ), array( 'id' => $home_link ) );
	$denied( $learner );
	$wpdb->update(
		FamilySchema::table( 'links' ),
		array(
			'status'     => 'active',
			'updated_at' => gmdate( 'Y-m-d H:i:s', time() + 60 ),
		),
		array( 'id' => $home_link )
	);
	$check( 0 === (int) Reports::read( $learner )['summary']['activities'], 'Reapproval exposed activity from a prior link period.' );
	$wpdb->update( FamilySchema::table( 'links' ), array( 'updated_at' => $now ), array( 'id' => $home_link ) );
	// Pagination uses the same scope for totals and rows.
	wp_set_current_user( $learner );
	for ( $i = 0; $i < 22; ++$i ) {
		$capture( $home ); }
	wp_set_current_user( $guardian );
	$home_page = Reports::read( $learner, 30, 2 );
	$check( 23 === (int) $home_page['summary']['activities'] && 3 === count( $home_page['items'] ) && 2 === $home_page['pages'], 'Scoped pagination lost rows or totals.' );
	$check( 30 === Reports::read( $learner, 999 )['days'], 'Invalid report period was not bounded.' );
	// Quiz origins freeze at start; finishing later under another context cannot rewrite them.
	wp_set_current_user( $learner );
	$quiz               = wp_insert_post(
		array(
			'post_type'   => 'ohmylms-quiz',
			'post_status' => 'publish',
			'post_title'  => 'Home report quiz',
		)
	);
	$fixture['posts'][] = $quiz;
	$quiz_id            = Service::insert(
		'quiz_attempts',
		array(
			'quiz_id'    => $quiz,
			'student_id' => $learner,
			'course_id'  => $course,
			'status'     => 'in-progress',
			'start_date' => current_time( 'mysql' ),
		)
	);
	$quiz_event         = array(
		'quiz_id'    => $quiz,
		'attempt_id' => $quiz_id,
		'student_id' => $learner,
		'course_id'  => $course,
	);
	unset( $_GET['oml_origin'] );
	$_SERVER['HTTP_REFERER'] = add_query_arg( 'oml_origin', Provenance::token( 'quiz', $course, $quiz ), home_url( '/' ) );
	do_action( 'ohmylms_attempt_started', $quiz_event );
	unset( $_SERVER['HTTP_REFERER'] );
	$check( 'home_learning' === $wpdb->get_var( $wpdb->prepare( 'SELECT origin FROM %i WHERE source_kind=%s AND source_id=%d', FamilySchema::table( 'activity' ), 'quiz', $quiz_id ) ), 'Same-origin REST referrer did not carry the signed context.' );
	$_GET['oml_origin'] = 'invalid';
	do_action( 'ohmylms_attempt_started', $quiz_event );
	do_action( 'ohmylms_attempt_submitted', $quiz_event );
	$old_quiz_id = Service::insert(
		'quiz_attempts',
		array(
			'quiz_id'    => $quiz,
			'student_id' => $learner,
			'course_id'  => $course,
			'status'     => 'completed',
			'start_date' => current_time( 'mysql' ),
		)
	);
	$old_event   = array_merge( $quiz_event, array( 'attempt_id' => $old_quiz_id ) );
	do_action( 'ohmylms_attempt_submitted', $old_event );
	$check( 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE source_kind=%s AND learner_user_id=%d AND origin=%s AND completed_at IS NOT NULL', FamilySchema::table( 'activity' ), 'quiz', $learner, 'home_learning' ) ), 'Resumed quiz lost its context or historical attempt was backfilled.' );
	$lesson             = wp_insert_post(
		array(
			'post_type'   => 'ohmylms-lesson',
			'post_status' => 'publish',
			'post_title'  => 'Home report lesson',
		)
	);
	$fixture['posts'][] = $lesson;
	$enrollment         = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE user_id=%d AND course_id=%d', Schema::table( 'user_enrollment' ), $learner, $course ) );
	$progress           = Service::insert(
		'user_progress',
		array(
			'enrollment_id' => $enrollment,
			'content_id'    => $lesson,
			'content_type'  => 'lesson',
			'status'        => 'completed',
			'start_date'    => current_time( 'mysql' ),
		)
	);
	$_GET['oml_origin'] = Provenance::token( 'lesson', $course, $lesson );
	do_action( 'ohmylms_verified_lesson_completed', $progress, $lesson, $course, $learner );
	$session = array(
		'uuid'         => wp_generate_uuid4(),
		'student_id'   => $learner,
		'guest_id'     => 0,
		'term_id'      => 123,
		'course_id'    => $course,
		'policy'       => '{}',
		'status'       => 'complete',
		'started_at'   => $now,
		'completed_at' => $now,
	);
	$wpdb->insert( \OhMyLMS\Assessment\Schema::table( 'practice_sessions' ), $session );
	$session['id']      = (int) $wpdb->insert_id;
	$_GET['oml_origin'] = Provenance::token( 'practice', $course, 123 );
	do_action( 'ohmylms_practice_started', $session );
	do_action( 'ohmylms_practice_completed', $session['uuid'] );
	wp_set_current_user( $guardian );
	$check( 26 === (int) Reports::read( $learner )['summary']['activities'], 'Lesson, quiz or practice lifecycle did not contribute its scoped activity.' );
	// A stale period is excluded, and reference timestamps are not inferred from global history.
	$wpdb->update(
		FamilySchema::table( 'activity' ),
		array( 'completed_at' => gmdate( 'Y-m-d H:i:s', time() - 100 * DAY_IN_SECONDS ) ),
		array(
			'source_kind' => 'lesson',
			'source_id'   => $progress,
		)
	);
	$check( 25 === (int) Reports::read( $learner, 90 )['summary']['activities'], 'Out-of-period activity was counted.' );
	$count += ohmylms_family_management_checks( $guardian, $other['child_id'], $learner, $course );
	update_option( 'ohmylms_school_schema', 'disabled-for-test' );
	$check( \OhMyLMS\Student\ParentWorkspace::available() && 25 === (int) Reports::read( $learner, 90 )['summary']['activities'], 'Home reports depend on school installation.' );
	update_option( 'ohmylms_school_schema', $school_schema_saved );
	update_user_meta( $guardian, '_ohmylms_email_verified', 'no' );
	update_option( 'ohmylms_require_email_verification', 'yes' );
	$denied( $learner );
	delete_user_meta( $guardian, '_ohmylms_email_verified' );
	if ( ! defined( 'OHMYLMS_FAMILY_ENABLED' ) ) {
		define( 'OHMYLMS_FAMILY_ENABLED', false );
		$check( ! FamilySchema::enabled(), 'Feature rollback flag was ignored.' );
		$denied( $learner );
		$check( 26 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE learner_user_id=%d AND origin=%s', FamilySchema::table( 'activity' ), $learner, 'home_learning' ) ), 'Disabling the feature deleted activity.' );
	}
	echo esc_html( $count . ' family provenance/report checks passed.' ) . "\n";
} finally {
	update_option( 'ohmylms_school_schema', $school_schema_saved );
	if ( null === $verification_saved ) {
		delete_option( 'ohmylms_require_email_verification' );
	} else {
		update_option( 'ohmylms_require_email_verification', $verification_saved );
	}
	unset( $_GET['oml_origin'] );
	foreach ( $fixture['users'] ?? array() as $cleanup_user ) {
		$wpdb->delete( Schema::table( 'quiz_attempts' ), array( 'student_id' => $cleanup_user ) );
		$wpdb->delete( \OhMyLMS\Assessment\Schema::table( 'practice_sessions' ), array( 'student_id' => $cleanup_user ) );
		$wpdb->delete( FamilySchema::table( 'activity' ), array( 'learner_user_id' => $cleanup_user ) );
		$wpdb->delete( FamilySchema::table( 'links' ), array( 'learner_user_id' => $cleanup_user ) );
		$wpdb->delete( FamilySchema::table( 'links' ), array( 'guardian_user_id' => $cleanup_user ) );
		$wpdb->delete( FamilySchema::table( 'audit' ), array( 'actor_id' => $cleanup_user ) );
	}
	ohmylms_school_report_cleanup( $fixture );
	wp_set_current_user( $original );
}
