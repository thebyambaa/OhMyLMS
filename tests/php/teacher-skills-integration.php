<?php
/**
 * The teacher's class Skills tab: a students-by-skills grid from recorded class assessments only.
 * Every fixture is rolled back.
 *
 * @package OhMyLMS
 */

$teacher_skills_site = getenv( 'OHMYLMS_TEST_SITE_PATH' );
if ( 'cli' !== PHP_SAPI || ! $teacher_skills_site || ! is_file( $teacher_skills_site . '/wp-load.php' ) ) {
	throw new RuntimeException( 'Set OHMYLMS_TEST_SITE_PATH to a local WordPress installation.' );
}
require $teacher_skills_site . '/wp-load.php';

use OhMyLMS\Assessment\Schema as AssessmentSchema;
use OhMyLMS\Family\Schema as FamilySchema;
use OhMyLMS\Schools\Schema;
use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\Utility\Transaction;

$GLOBALS['teacher_skills_checks'] = 0;

/**
 * Assert a contract.
 *
 * @param bool   $condition Expected result.
 * @param string $message   Failure description.
 * @throws RuntimeException When the contract fails.
 */
function teacher_skills_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
	++$GLOBALS['teacher_skills_checks'];
}

/**
 * Insert a row and return its ID.
 *
 * @param string $table Table.
 * @param array  $row   Row.
 * @return int ID.
 */
function teacher_skills_insert( $table, array $row ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture rows inside the rolled-back transaction.
	teacher_skills_check( false !== $wpdb->insert( $table, $row ), 'Fixture insert failed in ' . $table . ': ' . $wpdb->last_error );
	return (int) $wpdb->insert_id;
}

/**
 * Render the Skills tab for a class.
 *
 * @param int $class_id Class.
 * @return string HTML.
 */
function teacher_skills_render( $class_id ) {
	ob_start();
	ohmylms_get_template(
		'student-hub/teacher-skills.php',
		array(
			'teacher_class' => $class_id,
			'teacher_page'  => 1,
		)
	);
	return (string) ob_get_clean();
}

try {
	Transaction::run(
		static function () {
			$now = current_time( 'mysql', true );
			teacher_skills_check( AssessmentSchema::ready() && FamilySchema::enabled(), 'Class reporting storage is unavailable.' );
			$user = static function ( $prefix, $role ) {
				$id = wp_insert_user(
					array(
						'user_login'   => $prefix . '-' . wp_generate_password( 8, false ),
						'user_pass'    => wp_generate_password( 24 ),
						'display_name' => ucfirst( $prefix ) . ' fixture',
						'role'         => $role,
					)
				);
				teacher_skills_check( ! is_wp_error( $id ), 'User fixture failed.' );
				return (int) $id;
			};
			$teacher  = $user( 'teacher', 'subscriber' );
			$learner  = $user( 'learner', 'subscriber' );
			$stranger = $user( 'stranger', 'subscriber' );
			$school   = teacher_skills_insert(
				Schema::table( 'schools' ),
				array(
					'name'       => 'Teacher skills fixture',
					'slug'       => 'teacher-skills-' . wp_generate_password( 6, false, false ),
					'status'     => 'active',
					'created_by' => $teacher,
					'created_at' => $now,
				)
			);
			foreach ( array(
				array( $teacher, 'teacher' ),
				array( $learner, 'student' ),
			) as $member ) {
				teacher_skills_insert(
					Schema::table( 'school_memberships' ),
					array(
						'school_id'  => $school,
						'user_id'    => $member[0],
						'role'       => $member[1],
						'status'    => 'active',
						'joined_at' => $now,
					)
				);
			}
			$class = teacher_skills_insert(
				Schema::table( 'classes' ),
				array(
					'school_id'        => $school,
					'academic_year_id' => 0,
					'name'             => 'Fixture class',
					'status'           => 'active',
				)
			);
			foreach ( array(
				array( $teacher, 'teacher' ),
				array( $learner, 'student' ),
			) as $member ) {
				teacher_skills_insert(
					Schema::table( 'class_memberships' ),
					array(
						'class_id'   => $class,
						'user_id'    => $member[0],
						'role'       => $member[1],
						'status'    => 'active',
						'joined_at' => $now,
					)
				);
			}
			$fractions = wp_insert_term( 'Fractions fixture ' . wp_generate_password( 4, false ), Taxonomy::NAME );
			$decimals  = wp_insert_term( 'Decimals fixture ' . wp_generate_password( 4, false ), Taxonomy::NAME );
			teacher_skills_check( ! is_wp_error( $fractions ) && ! is_wp_error( $decimals ), 'Skill fixtures failed.' );

			// One assigned quiz attempt that moved one skill, and one home attempt that must not show.
			$assignment = teacher_skills_insert(
				Schema::table( 'learning_assignments' ),
				array(
					'school_id'  => $school,
					'class_id'   => $class,
					'creator_id' => $teacher,
					'title'      => 'Fixture quiz',
					'course_id'  => 0,
					'status'     => 'active',
					'created_at' => $now,
				)
			);
			teacher_skills_insert(
				Schema::table( 'assignment_recipients' ),
				array(
					'assignment_id'   => $assignment,
					'student_user_id' => $learner,
					'assigned_at'     => $now,
				)
			);
			foreach ( array(
				array( 900000001, 'school', $assignment, (int) $fractions['term_id'] ),
				array( 900000002, 'home_learning', 0, (int) $decimals['term_id'] ),
			) as $attempt ) {
				teacher_skills_insert(
					FamilySchema::table( 'activity' ),
					array(
						'learner_user_id' => $learner,
						'source_kind'     => 'quiz',
						'source_id'       => $attempt[0],
						'origin'          => $attempt[1],
						'assignment_id'   => $attempt[2],
						'school_id'       => 'school' === $attempt[1] ? $school : 0,
						'class_id'        => 'school' === $attempt[1] ? $class : 0,
						'created_at'      => $now,
						'completed_at'    => $now,
					)
				);
				teacher_skills_insert(
					AssessmentSchema::table( 'skill_evidence' ),
					array(
						'grade_event_id' => $attempt[0],
						'student_id'     => $learner,
						'term_id'        => $attempt[3],
						'part_id'        => 'p1',
						'role'           => 'primary',
						'awarded'        => 1,
						'available'      => 1,
						'independent'    => 1,
						'first_try'      => 1,
						'family_id'      => 'family-' . $attempt[0],
						'source_type'    => 'quiz',
						'source_id'      => $attempt[0],
						'question_id'    => $attempt[0],
						'version_id'     => $attempt[0],
						'evidence_at'    => $now,
					)
				);
			}

			wp_set_current_user( $teacher );
			$html = teacher_skills_render( $class );
			teacher_skills_check( false !== strpos( $html, '<table class="ohmylms-mx">' ), 'The Skills tab did not render a grid.' );
			teacher_skills_check( false !== strpos( $html, 'Learner fixture' ), 'The grid has no row for the class student.' );
			teacher_skills_check( false !== strpos( $html, esc_html( get_term( $fractions['term_id'] )->name ) ), 'The grid has no column for the class-assessed skill.' );
			teacher_skills_check( false === strpos( $html, get_term( $decimals['term_id'] )->name ), 'Home learning reached the teacher’s class grid.' );
			teacher_skills_check( 1 === preg_match( '/ohmylms-mx-cell ohmylms-mx-(?!not-started)[a-z-]+/', $html ), 'The student’s level is not shown in the cell.' );
			// The class level is the student's own level rule applied to the class attempt's evidence.
			$own = \OhMyLMS\Performance\Engine::scoped( $learner, array( 900000001 ) )[ (int) $fractions['term_id'] ]['level'] ?? '';
			teacher_skills_check( '' !== $own && 'not-started' !== $own && false !== strpos( $html, 'ohmylms-mx-cell ohmylms-mx-' . $own ), 'The class level does not follow the student’s own level rule.' );

			// Someone who does not teach the class sees no report.
			wp_set_current_user( $stranger );
			$denied = teacher_skills_render( $class );
			teacher_skills_check( false === strpos( $denied, 'Learner fixture' ) && false === strpos( $denied, '<table' ), 'A non-teacher saw the class skill grid.' );

			throw new RuntimeException( 'OHMYLMS_TEACHER_SKILLS_TEST_SUCCESS' );
		}
	);
} catch ( Throwable $failure ) {
	if ( 'OHMYLMS_TEACHER_SKILLS_TEST_SUCCESS' === $failure->getMessage() ) {
		echo 'Teacher skills: ' . (int) $GLOBALS['teacher_skills_checks'] . " checks passed; fixtures rolled back.\n";
	} else {
		echo esc_html( $failure->getMessage() ) . "\n";
		exit( 1 );
	}
}
