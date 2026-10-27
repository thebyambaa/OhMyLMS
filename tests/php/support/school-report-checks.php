<?php
/**
 * Removable school-report fixtures and authorization checks.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Schools\Schema;
use OhMyLMS\Schools\Service;
use OhMyLMS\Student\SchoolActions;
use OhMyLMS\Student\SchoolReports;

defined( 'ABSPATH' ) || exit;

/**
 * Create an isolated report fixture. The caller must clean up even on failure.
 *
 * @param array $fixture Accumulated fixture IDs.
 * @throws RuntimeException When a fixture identity cannot be created.
 */
function ohmylms_school_report_fixture( &$fixture ) {
	global $wpdb;
	$fixture = array(
		'users'   => array(),
		'schools' => array(),
		'posts'   => array(),
	);
	foreach ( array( 'ohmylms_teacher', 'ohmylms_teacher', 'subscriber' ) as $role ) {
		$id = wp_insert_user(
			array(
				'user_login'   => 'report-check-' . wp_generate_password( 12, false ),
				'user_pass'    => wp_generate_password( 40 ),
				'display_name' => 'Report demo learner',
				'role'         => $role,
			)
		);
		if ( is_wp_error( $id ) ) {
			throw new RuntimeException( 'Unable to create test identity.' );
		}
		$fixture['users'][] = $id;
	}
	foreach ( array_slice( $fixture['users'], 0, 2 ) as $index => $user_id ) {
		wp_set_current_user( $user_id );
		$school               = Service::create_school(
			array(
				'name'     => 'Reports demo ' . wp_generate_password( 6, false ),
				'timezone' => 'Asia/Ulaanbaatar',
			)
		);
		$fixture['schools'][] = (int) $school['id'];
		$year                 = Service::create_year(
			$school['id'],
			array(
				'label'     => '2026–27',
				'starts_at' => '2026-09-01',
				'ends_at'   => '2027-06-01',
			)
		);
		$class                = Service::create_class(
			$school['id'],
			array(
				'name'             => 'Demo mathematics',
				'academic_year_id' => $year['id'],
			)
		);
		$fixture['classes'][] = (int) $class['id'];
	}
	wp_set_current_user( $fixture['users'][0] );
	$school  = $fixture['schools'][0];
	$student = $fixture['users'][2];
	Service::membership(
		'school_memberships',
		array(
			'school_id' => $school,
			'user_id'   => $student,
			'role'      => 'student',
		)
	);
	Service::class_member(
		$fixture['classes'][0],
		array(
			'user_id' => $student,
			'role'    => 'student',
		)
	);
	foreach ( array( OHMYLMS_COURSE_CPT, 'ohmylms-chapter', OHMYLMS_ASSIGNMENT_CPT ) as $post_type ) {
		$fixture['posts'][] = wp_insert_post(
			array(
				'post_type'   => $post_type,
				'post_status' => 'publish',
				'post_title'  => 'Report demo activity',
			)
		);
	}
	list( $course, $chapter, $content ) = $fixture['posts'];
	update_post_meta( $content, '_type', 'assignment' );
	update_post_meta( $content, '_total_points', 100 );
	update_post_meta( $content, '_maximum_pass_points', 50 );
	Service::insert(
		'chapter_relationship',
		array(
			'course_id'  => $course,
			'chapter_id' => $chapter,
		)
	);
	Service::insert(
		'content_relationship',
		array(
			'chapter_id'   => $chapter,
			'content_id'   => $content,
			'content_type' => 'assignment',
		)
	);
	Service::insert(
		'user_enrollment',
		array(
			'user_id'    => $student,
			'course_id'  => $course,
			'status'     => 'enrolled',
			'progress'   => 'running',
			'start_date' => Service::now(),
		)
	);
	$work                = Service::create_assignment(
		$fixture['classes'][0],
		array(
			'course_id'  => $course,
			'content_id' => $content,
			'title'      => 'Written mathematics',
			'due_at'     => '2020-01-01T12:00',
		)
	);
	$fixture['learning'] = $work['id'];
	$fixture['attempt']  = Service::insert(
		'assignment_attempts',
		array(
			'user_id'       => $student,
			'course_id'     => $course,
			'assignment_id' => $content,
			'content'       => 'My solution: 6 × 7 = 42.',
			'files'         => maybe_serialize( array() ),
			'status'        => 'submitted',
			'start_date'    => get_date_from_gmt( gmdate( 'Y-m-d H:i:s', time() + 2 ) ),
		)
	);
}

/**
 * Remove only recorded fixture IDs and their dependent rows.
 *
 * @param array $fixture Fixture IDs.
 */
function ohmylms_school_report_cleanup( $fixture ) {
	global $wpdb;
	foreach ( $fixture['schools'] ?? array() as $school ) {
		$classes = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE school_id=%d', Schema::table( 'classes' ), $school ) );
		foreach ( $classes as $class ) {
			$wpdb->delete( Schema::table( 'class_memberships' ), array( 'class_id' => $class ) );
		}
		$assignments = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE school_id=%d', Schema::table( 'learning_assignments' ), $school ) );
		foreach ( $assignments as $assignment ) {
			$wpdb->delete( Schema::table( 'assignment_recipients' ), array( 'assignment_id' => $assignment ) );
		}
		foreach ( array( 'school_memberships', 'school_student_profiles', 'academic_years', 'classes', 'guardian_links', 'school_invitations', 'learning_assignments', 'school_audit_log' ) as $table ) {
			$wpdb->delete( Schema::table( $table ), array( 'school_id' => $school ) );
		}
		$wpdb->delete( Schema::table( 'schools' ), array( 'id' => $school ) );
	}
	if ( ! empty( $fixture['posts'][0] ) ) {
		$course      = $fixture['posts'][0];
		$enrollments = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE course_id=%d', Schema::table( 'user_enrollment' ), $course ) );
		foreach ( $enrollments as $enrollment ) {
			$wpdb->delete( Schema::table( 'user_progress' ), array( 'enrollment_id' => $enrollment ) );
		}
		foreach ( array( 'user_enrollment', 'assignment_attempts', 'chapter_relationship' ) as $table ) {
			$wpdb->delete( Schema::table( $table ), array( 'course_id' => $course ) );
		}
	}
	if ( ! empty( $fixture['posts'][1] ) ) {
		$wpdb->delete( Schema::table( 'content_relationship' ), array( 'chapter_id' => $fixture['posts'][1] ) );
	}
	foreach ( $fixture['posts'] ?? array() as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $fixture['users'] ?? array() as $user_id ) {
		$wpdb->delete( Schema::table( 'school_audit_log' ), array( 'actor_id' => $user_id ) );
		delete_transient( SchoolActions::FLASH . $user_id );
		wp_delete_user( $user_id );
	}
}

/**
 * Run isolated persistence and tenant-isolation checks, always cleaning up.
 *
 * @return int Passed assertion count.
 */
function ohmylms_school_report_checks() {
	global $wpdb;
	$original = get_current_user_id();
	$fixture  = array();
	$count    = 0;
	$check    = static function ( $condition, $message ) use ( &$count ) {
		if ( ! $condition ) {
			throw new RuntimeException( esc_html( $message ) );
		}
		++$count;
	};
	$denied   = static function ( $callback ) use ( $check ) {
		try {
			$callback();
		} catch ( RuntimeException $error ) {
			$check( in_array( $error->getCode(), array( 400, 403 ), true ), 'Expected a validation or permission failure.' );
			return;
		}
		$check( false, 'Unauthorized request succeeded.' );
	};
	try {
		ohmylms_school_report_fixture( $fixture );
		$school  = $fixture['schools'][0];
		$class   = $fixture['classes'][0];
		$student = $fixture['users'][2];
		$data    = array(
			'learning_id' => $fixture['learning'],
			'attempt_id'  => $fixture['attempt'],
			'score'       => 20,
			'note'        => 'Please revise.',
		);
		$check( ! current_user_can( 'manage_options' ), 'Fixture must exercise a non-platform school administrator.' );
		$rows = SchoolReports::overview( $school );
		$check( 1 === count( $rows ) && 1 === (int) $rows[0]['assigned'] && 1 === (int) $rows[0]['overdue'], 'Completion summary incorrect.' );
		$check( 1 === count( SchoolReports::work( $school, $class ) ), 'Assigned work missing.' );
		$check( 1 === count( SchoolReports::submissions( $school, $class )['rows'] ), 'Submission missing.' );
		$check( array() === SchoolReports::submissions( $school, $class, 2 )['rows'], 'Submission paging repeats rows.' );
		$check( array() === SchoolReports::overview( $school, 2 ), 'Overview paging repeats rows.' );
		$check( array() === SchoolReports::work( $school, $class, 2 ), 'Assigned-work paging repeats rows.' );
		$class_record = Service::row( 'classes', $class );
		for ( $index = 0; $index < 20; ++$index ) {
			Service::create_class(
				$school,
				array(
					'name'             => 'Pagination ' . $index,
					'academic_year_id' => $class_record['academic_year_id'],
				)
			);
		}
		$overview_first = SchoolReports::overview( $school );
		$overview_last  = SchoolReports::overview( $school, 2 );
		$check( 21 === count( $overview_first ) && 1 === count( $overview_last ), 'Class pagination lost its lookahead or final class.' );
		$check( ! array_intersect( array_column( array_slice( $overview_first, 0, 20 ), 'id' ), array_column( $overview_last, 'id' ) ), 'Class report pages overlap.' );
		$attempt_data = Service::row( 'assignment_attempts', $fixture['attempt'] );
		unset( $attempt_data['id'] );
		$attempt_data['start_date'] = get_date_from_gmt( '2000-01-01 00:00:00' );
		$old_attempt                = Service::insert( 'assignment_attempts', $attempt_data );
		$check( 1 === count( SchoolReports::submissions( $school, $class )['rows'] ), 'Pre-distribution submission leaked.' );
		$denied(
			static function () use ( $school, $data, $old_attempt ) {
				SchoolActions::apply( 'grade_submission', $school, array_merge( $data, array( 'attempt_id' => $old_attempt ) ) );
			}
		);
		$attempt_data['start_date'] = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', time() + 2 ) );
		for ( $index = 0; $index < 101; ++$index ) {
			Service::insert( 'assignment_attempts', $attempt_data );
		}
		$first_page  = SchoolReports::submissions( $school, $class );
		$second_page = SchoolReports::submissions( $school, $class, 2 );
		$check( 100 === count( $first_page['rows'] ) && $first_page['more'], 'Gradebook truncates the first page.' );
		$check( 2 === count( $second_page['rows'] ) && ! $second_page['more'], 'Gradebook loses the final page.' );
		$check( ! array_intersect( array_column( $first_page['rows'], 'id' ), array_column( $second_page['rows'], 'id' ) ), 'Gradebook pages overlap.' );
		$denied(
			static function () use ( $fixture, $school ) {
				SchoolReports::work( $school, $fixture['classes'][1] );
			}
		);
		$denied(
			static function () use ( $fixture ) {
				SchoolReports::overview( $fixture['schools'][1] );
			}
		);
		$denied(
			static function () use ( $school, $data ) {
				SchoolActions::apply( 'grade_submission', $school, array_merge( $data, array( 'score' => 101 ) ) );
			}
		);
		$denied(
			static function () use ( $school, $data ) {
				SchoolActions::apply( 'grade_submission', $school, array_merge( $data, array( 'score' => '2.5' ) ) );
			}
		);
		$denied(
			static function () use ( $fixture, $data ) {
				SchoolActions::apply( 'grade_submission', $fixture['schools'][1], $data );
			}
		);
		SchoolActions::apply( 'grade_submission', $school, $data );
		$attempt = Service::row( 'assignment_attempts', $fixture['attempt'] );
		$check( 20 === (int) $attempt['score'] && 'failed' === $attempt['status'], 'Failing grade did not use the assessment pipeline.' );
		$data['score'] = 80;
		SchoolActions::apply( 'grade_submission', $school, $data );
		$check( 'passed' === Service::row( 'assignment_attempts', $fixture['attempt'] )['status'], 'Passing grade was not saved.' );
		$check( 1 === (int) SchoolReports::overview( $school )[0]['completed'], 'Passing grade did not complete assigned work.' );
		wp_set_current_user( $fixture['users'][1] );
		$denied(
			static function () use ( $school, $class ) {
				SchoolReports::submissions( $school, $class );
			}
		);
		$denied(
			static function () use ( $school, $data ) {
				SchoolActions::apply( 'grade_submission', $school, $data );
			}
		);
		wp_set_current_user( $student );
		$denied(
			static function () use ( $school, $class ) {
				SchoolReports::skills( $school, $class );
			}
		);
		wp_set_current_user( $fixture['users'][0] );
		Service::membership(
			'school_memberships',
			array(
				'school_id' => $school,
				'user_id'   => $student,
				'role'      => 'student',
			),
			false
		);
		$check( 0 === (int) SchoolReports::overview( $school )[0]['assigned'], 'Removed student remains in summary.' );
		$check( array() === SchoolReports::work( $school, $class ), 'Removed student remains in assigned work.' );
		$check( array() === SchoolReports::submissions( $school, $class )['rows'], 'Removed student submission leaked.' );
		$denied(
			static function () use ( $school, $data ) {
				SchoolActions::apply( 'grade_submission', $school, $data );
			}
		);
		Service::membership(
			'school_memberships',
			array(
				'school_id' => $school,
				'user_id'   => $student,
				'role'      => 'student',
			)
		);
		Service::archive_class( $class );
		$check( 1 === count( SchoolReports::work( $school, $class ) ), 'Archiving removed read-only work history.' );
		$check( array() === SchoolReports::submissions( $school, $class )['rows'], 'Archived class remains gradeable.' );
		$denied(
			static function () use ( $school, $data ) {
				SchoolActions::apply( 'grade_submission', $school, $data );
			}
		);
		return $count;
	} finally {
		wp_set_current_user( $original );
		ohmylms_school_report_cleanup( $fixture );
	}
}
