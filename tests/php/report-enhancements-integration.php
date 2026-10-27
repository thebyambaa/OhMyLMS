<?php
/**
 * Report aggregation and classroom privacy checks on the disposable database.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Family\Reports;
use OhMyLMS\Family\Schema as FamilySchema;
use OhMyLMS\Schools\Schema;
use OhMyLMS\Assessment\Schema as AssessmentSchema;
use OhMyLMS\Performance\Settings;
use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\Student\TeacherReports;

if ( 'cli' !== PHP_SAPI ) {
	exit;
}
$report_config_path = getenv( 'OHMYLMS_TEST_CREDENTIALS' );
if ( ! $report_config_path || ! is_file( $report_config_path ) ) {
	throw new RuntimeException( 'Disposable credentials required.' );
}
$report_config = json_decode( file_get_contents( $report_config_path ), true );
require $report_config['site'] . '/wp-load.php';
if ( ! defined( 'OHMYLMS_TEST_SITE' ) || ! OHMYLMS_TEST_SITE || 'ohmylms_source_test' !== DB_NAME ) {
	throw new RuntimeException( 'Disposable database required.' );
}
require __DIR__ . '/support/school-report-checks.php';

$fixture  = array();
$term_id  = 0;
$original = get_current_user_id();
$setting  = get_option( Settings::OPTION, null );
$count    = 0;
$check    = static function ( $value, $message ) use ( &$count ) {
	if ( ! $value ) {
		throw new RuntimeException( esc_html( $message ) );
	}
	++$count;
};
try {
	ohmylms_school_report_fixture( $fixture );
	FamilySchema::install();
	update_option( Settings::OPTION, array( 'policy' => 'steps' ) );
	$learner     = $fixture['users'][2];
	$teacher     = $fixture['users'][0];
	$class       = $fixture['classes'][0];
	$school      = $fixture['schools'][0];
	$course      = $fixture['posts'][0];
	$now         = gmdate( 'Y-m-d H:i:s' );
	$report_term = wp_insert_term( 'Report skill ' . wp_generate_password( 8, false ), Taxonomy::NAME );
	$check( ! is_wp_error( $report_term ), 'Skill fixture failed.' );
	$term_id = (int) $report_term['term_id'];
	$source  = wp_rand( 100000000, 200000000 );
	global $wpdb;
	$record = static function ( $origin, $wrong = 0, $kind = 'quiz', $completed = null ) use ( &$source, $wpdb, $learner, $school, $class, $course, $fixture, $term_id, $now, $check ) {
		++$source;
		$check(
			false !== $wpdb->insert(
				FamilySchema::table( 'activity' ),
				array(
					'learner_user_id' => $learner,
					'source_kind'     => $kind,
					'source_id'       => $source,
					'course_id'       => $course,
					'content_id'      => $fixture['posts'][2],
					'origin'          => $origin,
					'assignment_id'   => 'school' === $origin ? $fixture['learning'] : 0,
					'school_id'       => 'school' === $origin ? $school : 0,
					'class_id'        => 'school' === $origin ? $class : 0,
					'created_at'      => $completed ? $completed : $now,
					'completed_at'    => $completed ? $completed : $now,
				)
			),
			'Activity fixture failed.'
		);
		if ( 'quiz' === $kind ) {
			$check(
				false !== $wpdb->insert(
					AssessmentSchema::table( 'skill_level_events' ),
					array(
						'student_id'   => $learner,
						'term_id'      => $term_id,
						'source_type'  => 'quiz',
						'source_id'    => $source,
						'level_before' => 'mastered',
						'level_after'  => 'mastered',
						'detail'       => wp_json_encode(
							array(
								'right' => $wrong ? 0 : 1,
								'wrong' => $wrong,
							)
						),
						'created_at'   => $now,
					)
				),
				'Level fixture failed.'
			);
		}
	};
	$record( 'school' );
	$record( 'school' );
	$record( 'school', 1 );
	$record( 'unknown' );
	$record( 'home_learning' );
	$record( 'home_learning', 0, 'assignment' );
	$record( 'home_learning', 0, 'assignment', gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) );
	wp_set_current_user( $teacher );
	$data  = TeacherReports::read( $class );
	$skill = $data['students'][0]['skills'][ $term_id ];
	$check( 'familiar' === $skill['level'] && 3 === $skill['assessments'], 'Class replay leaked home/global levels or lost ordering.' );
	$check( 30 === TeacherReports::read( $class, 999 )['days'], 'Invalid period accepted.' );
	$check( array() === TeacherReports::read( $class, 30, 10000 )['students'], 'Roster pagination was ignored.' );
	// The class report replays recorded assessment outcomes, whichever policy decides the levels.
	update_option( Settings::OPTION, array( 'policy' => 'evidence' ) );
	$check( TeacherReports::read( $class )['enabled'], 'The class report depends on the level policy.' );
	update_option( Settings::OPTION, array( 'policy' => 'steps' ) );
	$wpdb->update(
		Schema::table( 'class_memberships' ),
		array( 'status' => 'inactive' ),
		array(
			'class_id' => $class,
			'user_id'  => $learner,
		)
	);
	$check( array() === TeacherReports::read( $class )['students'], 'Removed learner remained in report.' );
	$wpdb->update(
		Schema::table( 'class_memberships' ),
		array( 'status' => 'active' ),
		array(
			'class_id' => $class,
			'user_id'  => $learner,
		)
	);
	wp_set_current_user( $fixture['users'][1] );
	$blocked = false;
	try {
		TeacherReports::read( $class );
	} catch ( RuntimeException $error ) {
		$blocked = 403 === $error->getCode();
	}
	$check( $blocked, 'Another school could read the class report.' );
	$guardian = wp_insert_user(
		array(
			'user_login' => 'report-parent-' . wp_generate_password( 12, false ),
			'user_pass'  => wp_generate_password( 40 ),
			'role'       => 'ohmylms_parent',
		)
	);
	$check( ! is_wp_error( $guardian ), 'Parent fixture failed.' );
	$fixture['users'][] = $guardian;
	update_user_meta( $guardian, \OhMyLMS\Services\EmailVerificationService::META_VERIFIED, 'yes' );
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
			'updated_at'       => gmdate( 'Y-m-d H:i:s', time() - 2 * DAY_IN_SECONDS ),
		)
	);
	$report_link_id = (int) $wpdb->insert_id;
	wp_set_current_user( $guardian );
	$home = Reports::read( $learner );
	$check( 3 === (int) $home['summary']['activities'], 'Home total included school or unknown activity.' );
	$check( 2 === count( $home['daily'] ) && 2 === count( $home['types'] ), 'Daily/type aggregation failed.' );
	$check( 3 === (int) $home['courses'][0]['activities'] && (int) $home['courses'][0]['course_id'] === $course, 'Course aggregation lost scope.' );
	$wpdb->update( FamilySchema::table( 'links' ), array( 'updated_at' => $now ), array( 'id' => $report_link_id ) );
	$check( 2 === (int) Reports::read( $learner )['courses'][0]['activities'], 'Course summary exposed a prior link period.' );
	$wpdb->update( FamilySchema::table( 'links' ), array( 'status' => 'revoked' ), array( 'id' => $report_link_id ) );
	$blocked = false;
	try {
		Reports::read( $learner );
	} catch ( RuntimeException $error ) {
		$blocked = 403 === $error->getCode();
	}
	$check( $blocked, 'Revoked parent could read report aggregates.' );
	wp_set_current_user( $teacher );
	for ( $roster_index = 1; $roster_index <= 201; ++$roster_index ) {
		$roster_user = wp_insert_user(
			array(
				'user_login'   => 'large-report-' . wp_generate_password( 12, false ),
				'user_pass'    => wp_generate_password( 40 ),
				'display_name' => sprintf( 'Report large %03d', $roster_index ),
				'role'         => 'subscriber',
			)
		);
		if ( is_wp_error( $roster_user ) ) {
			throw new RuntimeException( esc_html( $roster_user->get_error_message() ) );
		}
		$fixture['users'][] = $roster_user;
		\OhMyLMS\Schools\Service::membership(
			'school_memberships',
			array(
				'school_id' => $school,
				'user_id'   => $roster_user,
				'role'      => 'student',
			)
		);
		\OhMyLMS\Schools\Service::class_member(
			$class,
			array(
				'user_id' => $roster_user,
				'role'    => 'student',
			)
		);
	}
	$roster_first = TeacherReports::read( $class );
	$roster_last  = TeacherReports::read( $class, 30, 11 );
	$check( 20 === count( $roster_first['students'] ) && $roster_first['more'], 'Large roster first page was not bounded.' );
	$check( 2 === count( $roster_last['students'] ) && ! $roster_last['more'], 'Large roster last page or more flag is wrong.' );
	$check( ! array_intersect( array_column( $roster_first['students'], 'user_id' ), array_column( $roster_last['students'], 'user_id' ) ), 'Large roster pages repeated students.' );
	echo esc_html( "$count report enhancement checks passed." ) . "\n";
} finally {
	wp_set_current_user( $original );
	foreach ( $fixture['users'] ?? array() as $user_id ) {
		$wpdb->delete( FamilySchema::table( 'activity' ), array( 'learner_user_id' => $user_id ) );
		$wpdb->delete( FamilySchema::table( 'links' ), array( 'guardian_user_id' => $user_id ) );
		$wpdb->delete( AssessmentSchema::table( 'skill_level_events' ), array( 'student_id' => $user_id ) );
	}
	if ( $term_id ) {
		wp_delete_term( $term_id, Taxonomy::NAME );
	}
	ohmylms_school_report_cleanup( $fixture );
	if ( null === $setting ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $setting );
	}
}
