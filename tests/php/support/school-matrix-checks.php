<?php
/**
 * Isolated school matrix, totals and export-value checks.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Schools\Schema;
use OhMyLMS\Schools\Service;
use OhMyLMS\Student\SchoolGradebook;

defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/school-report-checks.php';

/**
 * Verify quiz/assignment cells, scope and multi-page roster handling.
 *
 * @return int Check count.
 * @throws RuntimeException When fixture creation or an assertion fails.
 */
function ohmylms_school_matrix_checks() {
	global $wpdb;
	$original = get_current_user_id();
	$fixture  = array();
	$count    = 0;
	$check    = static function ( $value, $message ) use ( &$count ) {
		if ( ! $value ) {
			throw new RuntimeException( esc_html( $message ) );
		} ++$count;
	};
	try {
		ohmylms_school_report_fixture( $fixture );
		$school                                = $fixture['schools'][0];
		$class_id                              = $fixture['classes'][0];
		$student                               = $fixture['users'][2];
		list( $course, $chapter, $assignment ) = $fixture['posts'];
		$quiz                                  = wp_insert_post(
			array(
				'post_type'   => OHMYLMS_QUIZ_CPT,
				'post_status' => 'publish',
				'post_title'  => 'Quiz matrix fixture',
			)
		);
		$fixture['posts'][]                    = $quiz;
		$question                              = wp_insert_post(
			array(
				'post_type'   => OHMYLMS_QUESTION_CPT,
				'post_status' => 'publish',
				'post_title'  => 'Question matrix fixture',
			)
		);
		$fixture['posts'][]                    = $question;
		update_post_meta(
			$question,
			'_question_settings',
			array(
				'type'  => 'single-choice',
				'score' => array(
					'enabled' => true,
					'value'   => 10,
				),
			)
		);
		Service::insert(
			'quiz_questions_relationship',
			array(
				'quiz_id'      => $quiz,
				'question_id'  => $question,
				'order_number' => 1,
			)
		);
		Service::insert(
			'content_relationship',
			array(
				'chapter_id'   => $chapter,
				'content_id'   => $quiz,
				'content_type' => 'quiz',
			)
		);
		$matrix = SchoolGradebook::read( $school, $class_id, $course );
		$check( 1 === count( $matrix['items'] ), 'Undistributed assessment became a gradebook column.' );
		$check( 'pending' === $matrix['students'][0]['cells'][ $assignment ]['status'] && null === $matrix['students'][0]['total']['percent'], 'Pending submission became a grade.' );
		$work         = Service::create_assignment(
			$class_id,
			array(
				'course_id'  => $course,
				'content_id' => $quiz,
				'title'      => 'Quiz work',
			)
		);
		$attempt_time = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', time() + 2 ) );
		$quiz_attempt = Service::insert(
			'quiz_attempts',
			array(
				'course_id'  => $course,
				'quiz_id'    => $quiz,
				'student_id' => $student,
				'total'      => 4,
				'status'     => 'completed',
				'start_date' => $attempt_time,
			)
		);
		Service::insert(
			'quiz_attempts_answers',
			array(
				'student_id'      => $student,
				'quiz_id'         => $quiz,
				'question_id'     => $question,
				'quiz_attempt_id' => $quiz_attempt,
				'given_answer'    => 'test',
				'question_marks'  => 10,
				'achive_mark'     => 4,
				'minus_mark'      => 0,
			)
		);
		Service::insert(
			'quiz_attempts',
			array(
				'course_id'  => $course,
				'quiz_id'    => $quiz,
				'student_id' => $student,
				'total'      => 0,
				'status'     => 'in-progress',
				'start_date' => $attempt_time,
			)
		);
		Service::update(
			'assignment_attempts',
			array(
				'score'  => 80,
				'status' => 'passed',
			),
			array( 'id' => $fixture['attempt'] )
		);
		$matrix = SchoolGradebook::read( $school, $class_id, $course );
		$cells  = $matrix['students'][0]['cells'];
		$check( 4.0 === $cells[ $quiz ]['score'] && 10.0 === $cells[ $quiz ]['max'], 'Pending retake hid a grade or earned points became maximum points.' );
		$check( 76.36 === $matrix['students'][0]['total']['percent'], 'Weighted gradebook total is incorrect.' );
		$export_columns = array_values(
			array_filter(
				$matrix['items'],
				static function ( $item ) use ( $assignment ) {
					return $item['id'] === $assignment;
				}
			)
		);
		$export_row     = SchoolGradebook::csv_row( $matrix['students'][0], $export_columns );
		$check( 6 === count( $export_row ) && 80.0 === (float) $export_row[3] && 100.0 === (float) $export_row[4] && 80.0 === (float) $export_row[5], 'Columns added during export changed the header or totals.' );
		$missing_student = $matrix['students'][0];
		unset( $missing_student['cells'][ $assignment ] );
		$removed_row = SchoolGradebook::csv_row( $missing_student, $export_columns );
		$check( SchoolGradebook::status( 'not-assigned' ) === $removed_row[2] && '' === $removed_row[5], 'Removing a column during export shifted its cells or retained stale grades.' );
		Service::update(
			'assignment_attempts',
			array(
				'score'  => 0,
				'status' => 'failed',
			),
			array( 'id' => $fixture['attempt'] )
		);
		$check( 0.0 === SchoolGradebook::read( $school, $class_id, $course )['students'][0]['cells'][ $assignment ]['score'], 'A real zero was excluded.' );
		$wpdb->delete(
			Schema::table( 'assignment_recipients' ),
			array(
				'assignment_id'   => $work['id'],
				'student_user_id' => $student,
			)
		);
		$check( 'not-assigned' === SchoolGradebook::read( $school, $class_id, $course )['students'][0]['cells'][ $quiz ]['status'], 'Unassigned personal quiz history leaked.' );
		Service::update( 'assignment_attempts', array( 'start_date' => get_date_from_gmt( '2000-01-01 00:00:00' ) ), array( 'id' => $fixture['attempt'] ) );
		$check( 'missing' === SchoolGradebook::read( $school, $class_id, $course )['students'][0]['cells'][ $assignment ]['status'], 'Pre-assignment work leaked.' );
		Service::create_assignment(
			$class_id,
			array(
				'course_id'        => $course,
				'title'            => 'Entire course',
				'prior_completion' => 1,
			)
		);
		$check( 'graded' === SchoolGradebook::read( $school, $class_id, $course )['students'][0]['cells'][ $quiz ]['status'], 'Whole-course distribution did not include course assessments.' );
		for ( $index = 0; $index < 50; ++$index ) {
			$id = wp_insert_user(
				array(
					'user_login'   => 'matrix-page-' . wp_generate_password( 14, false ),
					'user_pass'    => wp_generate_password( 30 ),
					'display_name' => 'Pagination learner ' . $index,
					'role'         => 'subscriber',
				)
			);
			if ( is_wp_error( $id ) ) {
				throw new RuntimeException( 'Cannot create pagination fixture.' ); }
			$fixture['users'][] = $id;
			Service::membership(
				'school_memberships',
				array(
					'school_id' => $school,
					'user_id'   => $id,
					'role'      => 'student',
				)
			);
			Service::class_member(
				$class_id,
				array(
					'user_id' => $id,
					'role'    => 'student',
				)
			);
		}
		$first = SchoolGradebook::read( $school, $class_id, $course );
		$last  = SchoolGradebook::read( $school, $class_id, $course, 2 );
		$check( 50 === count( $first['students'] ) && $first['more'] && 1 === count( $last['students'] ) && ! $last['more'], 'Roster pagination truncates or duplicates students.' );
		$check( ! array_intersect( array_column( $first['students'], 'id' ), array_column( $last['students'], 'id' ) ), 'Roster pages overlap.' );
		Service::membership(
			'school_memberships',
			array(
				'school_id' => $school,
				'user_id'   => $student,
				'role'      => 'student',
			),
			false
		);
		$check( ! in_array( $student, array_map( 'intval', array_column( SchoolGradebook::read( $school, $class_id, $course )['students'], 'id' ) ), true ), 'Removed student remains in gradebook.' );
		wp_set_current_user( $fixture['users'][1] );
		try {
			SchoolGradebook::read( $school, $class_id, $course );
			$check( false, 'Cross-school gradebook access succeeded.' );
		} catch ( RuntimeException $error ) {
			$check( 403 === $error->getCode(), 'Expected cross-school denial.' ); }
		foreach ( array( '=HYPERLINK("bad")', '  +1', '@SUM(A1)', "\t-1" ) as $formula ) {
			$check( "'" === substr( SchoolGradebook::csv_value( $formula ), 0, 1 ), 'CSV formula injection was not neutralized.' ); }
		$check( 'Learner' === SchoolGradebook::csv_value( 'Learner' ), 'Ordinary CSV text changed.' );
		return $count;
	} finally {
		if ( ! empty( $quiz ) ) {
			foreach ( array( 'quiz_attempts', 'quiz_attempts_answers', 'quiz_questions_relationship' ) as $table ) {
				$wpdb->delete( Schema::table( $table ), array( 'quiz_id' => $quiz ) ); }
		}
		wp_set_current_user( $original );
		ohmylms_school_report_cleanup( $fixture );
	}
}
