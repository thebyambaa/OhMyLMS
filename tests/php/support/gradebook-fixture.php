<?php
/**
 * Tagged gradebook fixtures: two schools, three classes, staff, learners with mixed enrolment sources,
 * one course with quizzes, an exam and assignments (with audiences and a due date), attempts in every
 * state, a skill with states, and optionally a 30 × 20 performance course. gbfx_delete() removes
 * everything of a tag, also after an interrupted run.
 *
 * Layout (L1-L10 are learners):
 *
 * - School A: administrator `sa`, teacher `teacher`; students L1-L6. Class A1 (taught by `teacher`): L1-L4.
 *   Class A2: L5, L6. School B: administrator `sb`; student L7 in class B1.
 * - L8 (purchase), L9 (self), L10 (manual) belong to no school or class; `outsider` is a subscriber.
 * - Course C1 linked to A1 and A2. L1-L4 enrolled by A1 (roster), L5-L6 by A2 (roster), L7 manual,
 *   L8-L10 as above. Module M1: quiz Q1 (max 10), assignment H1 (max 20, due yesterday).
 *   Module M2: exam E1 (quiz with `_ohmylms_exam_id`, max 40), assignment H2 (audience: class A1, max 20),
 *   quiz Q2 (audience: individuals, max 10).
 * - Attempts: Q1 L1 8/10, L2 5/10 completed, L3 in review, L4 in progress; H1 L1 graded 18, L2 submitted,
 *   L5 graded 10; E1 L1 30/40; Q2 L8 9/10.
 * - Weights: course homework 40, quiz 30, test 30; class A1 override homework 20, quiz 30, test 50.
 * - Skill K1: L1 90, L2 40 (states).
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders

require_once __DIR__ . '/mastery-fixture.php';

use OhMyLMS\Gradebook\Items;
use OhMyLMS\Gradebook\Schema as GradebookSchema;
use OhMyLMS\Gradebook\Weights;
use OhMyLMS\Schools\Schema;
use OhMyLMS\Student\EnrolmentSchema;
use OhMyLMS\Student\Enrollments;

/**
 * Enrol a learner through the enrolment service (legacy row and projection with its source).
 *
 * @param int   $user   Learner.
 * @param int   $course Course.
 * @param array $origin Source.
 * @return int Legacy row id.
 * @throws RuntimeException When the enrolment fails.
 */
function gbfx_enrol( $user, $course, array $origin ) {
	global $wpdb;
	$created = Enrollments::create(
		array(
			'user_id'    => $user,
			'course_id'  => $course,
			'status'     => 'enrolled',
			'progress'   => 'running',
			'start_date' => current_time( 'mysql' ),
		),
		$origin
	);
	if ( false === $created ) {
		throw new RuntimeException( 'Enrolment failed: ' . $wpdb->last_error );
	}
	return (int) $wpdb->insert_id;
}

/**
 * Store a finished quiz attempt with one answer row carrying the marks.
 *
 * @param int    $course Course.
 * @param int    $quiz   Quiz.
 * @param int    $user   Learner.
 * @param string $status Status.
 * @param float  $score  Earned marks.
 * @param float  $max    Available marks.
 * @return int
 */
function gbfx_quiz_attempt( $course, $quiz, $user, $status, $score, $max ) {
	global $wpdb;
	$p       = $wpdb->prefix . 'ohmylms_';
	$attempt = dashfx_insert(
		$p . 'quiz_attempts',
		array(
			'course_id'  => $course,
			'quiz_id'    => $quiz,
			'student_id' => $user,
			'total'      => $score,
			'status'     => $status,
			'start_date' => current_time( 'mysql' ),
			'end_date'   => current_time( 'mysql' ),
		)
	);
	dashfx_insert(
		$p . 'quiz_attempts_answers',
		array(
			'student_id'      => $user,
			'quiz_id'         => $quiz,
			'question_id'     => 0,
			'quiz_attempt_id' => $attempt,
			'given_answer'    => 'x',
			'question_marks'  => $max,
			'achive_mark'     => $score,
			'is_correct'      => 1,
		)
	);
	return $attempt;
}

/**
 * Store an assignment submission.
 *
 * @param int    $course     Course.
 * @param int    $assignment Assignment.
 * @param int    $user       Learner.
 * @param string $status     `submitted` or a graded status.
 * @param int    $score      Score.
 * @return int
 */
function gbfx_submission( $course, $assignment, $user, $status, $score ) {
	global $wpdb;
	return dashfx_insert(
		$wpdb->prefix . 'ohmylms_assignment_attempts',
		array(
			'user_id'       => $user,
			'course_id'     => $course,
			'assignment_id' => $assignment,
			'content'       => 'fixture',
			'files'         => '',
			'score'         => $score,
			'status'        => $status,
			'start_date'    => current_time( 'mysql' ),
		)
	);
}

/**
 * Place items in a course under a module (chapter).
 *
 * @param int    $course Course.
 * @param int    $chapter Chapter.
 * @param int    $order  Chapter order.
 * @param int[]  $items  Content ids in order.
 * @return void
 */
function gbfx_place( $course, $chapter, $order, array $items ) {
	global $wpdb;
	$p = $wpdb->prefix . 'ohmylms_';
	dashfx_insert(
		$p . 'chapter_relationship',
		array(
			'course_id'    => $course,
			'chapter_id'   => $chapter,
			'order_number' => $order,
		)
	);
	foreach ( array_values( $items ) as $index => $item ) {
		$type = get_post_type( $item ) === OHMYLMS_ASSIGNMENT_CPT ? 'assignment' : 'quiz';
		dashfx_insert(
			$p . 'content_relationship',
			array(
				'chapter_id'   => $chapter,
				'content_id'   => $item,
				'content_type' => $type,
				'order_number' => $index + 1,
			)
		);
	}
}

/**
 * Create the fixtures.
 *
 * @param string $tag  Tag (lowercase letters and digits).
 * @param bool   $perf Also create the 30 × 20 performance course.
 * @return array Ids by name.
 */
function gbfx_create( $tag, $perf = false ) {
	GradebookSchema::ensure();
	$fx = array( 'tag' => $tag );
	foreach ( array(
		'admin'    => 'administrator',
		'sa'       => 'ohmylms_school_admin',
		'sb'       => 'ohmylms_school_admin',
		'teacher'  => 'ohmylms_teacher',
		'outsider' => 'subscriber',
	) as $name => $role ) {
		$fx[ $name ] = dashfx_user( $tag, $name, $role );
	}
	$fx['l'] = array();
	for ( $i = 1; $i <= 10; $i++ ) {
		$fx['l'][ $i ] = dashfx_user( $tag, 'l' . $i, 'subscriber' );
	}
	$l   = $fx['l'];
	$now = gmdate( 'Y-m-d H:i:s' );
	foreach ( array(
		'school_a' => 'Alpha School',
		'school_b' => 'Beta School',
	) as $key => $name ) {
		$fx[ $key ] = dashfx_insert(
			Schema::table( 'schools' ),
			array(
				'name'       => $tag . ' ' . $name,
				'slug'       => $tag . '-' . $key,
				'timezone'   => 'UTC',
				'status'     => 'active',
				'created_by' => $fx['admin'],
				'created_at' => $now,
			)
		);
	}
	$member = static function ( $school, $user, $role ) use ( $now ) {
		dashfx_insert(
			Schema::table( 'school_memberships' ),
			array(
				'school_id' => $school,
				'user_id'   => $user,
				'role'      => $role,
				'status'    => 'active',
				'joined_at' => $now,
			)
		);
	};
	$member( $fx['school_a'], $fx['sa'], 'school_admin' );
	$member( $fx['school_a'], $fx['teacher'], 'teacher' );
	$member( $fx['school_b'], $fx['sb'], 'school_admin' );
	foreach ( array( 1, 2, 3, 4, 5, 6 ) as $i ) {
		$member( $fx['school_a'], $l[ $i ], 'student' );
	}
	$member( $fx['school_b'], $l[7], 'student' );
	foreach ( array(
		'class_a1' => array( $fx['school_a'], 'Class A1', array( 1, 2, 3, 4 ), $fx['teacher'] ),
		'class_a2' => array( $fx['school_a'], 'Class A2', array( 5, 6 ), 0 ),
		'class_b1' => array( $fx['school_b'], 'Class B1', array( 7 ), 0 ),
	) as $key => $class ) {
		$fx[ $key ] = dashfx_insert(
			Schema::table( 'classes' ),
			array(
				'school_id'        => $class[0],
				'academic_year_id' => 0,
				'name'             => $tag . ' ' . $class[1],
				'status'           => 'active',
			)
		);
		foreach ( $class[2] as $i ) {
			dashfx_insert(
				Schema::table( 'class_memberships' ),
				array(
					'class_id'  => $fx[ $key ],
					'user_id'   => $l[ $i ],
					'role'      => 'student',
					'status'    => 'active',
					'joined_at' => $now,
				)
			);
		}
		if ( $class[3] ) {
			dashfx_insert(
				Schema::table( 'class_memberships' ),
				array(
					'class_id'  => $fx[ $key ],
					'user_id'   => $class[3],
					'role'      => 'teacher',
					'status'    => 'active',
					'joined_at' => $now,
				)
			);
		}
	}

	$fx['course'] = dashfx_post( $tag, OHMYLMS_COURSE_CPT, 'Algebra One' );
	$fx['m1']     = dashfx_post( $tag, OHMYLMS_CHAPTER_CPT, 'Equations' );
	$fx['m2']     = dashfx_post( $tag, OHMYLMS_CHAPTER_CPT, 'Graphs' );
	$fx['q1']     = dashfx_post( $tag, OHMYLMS_QUIZ_CPT, 'Linear quiz' );
	$fx['h1']     = dashfx_post( $tag, OHMYLMS_ASSIGNMENT_CPT, 'Practice set' );
	$fx['e1']     = dashfx_post( $tag, OHMYLMS_QUIZ_CPT, 'Unit test' );
	$fx['h2']     = dashfx_post( $tag, OHMYLMS_ASSIGNMENT_CPT, 'Extension set' );
	$fx['q2']     = dashfx_post( $tag, OHMYLMS_QUIZ_CPT, 'Retake quiz' );
	update_post_meta( $fx['h1'], '_total_points', 20 );
	update_post_meta( $fx['h2'], '_total_points', 20 );
	update_post_meta( $fx['e1'], '_ohmylms_exam_id', $fx['e1'] );
	gbfx_place( $fx['course'], $fx['m1'], 1, array( $fx['q1'], $fx['h1'] ) );
	gbfx_place( $fx['course'], $fx['m2'], 2, array( $fx['e1'], $fx['h2'], $fx['q2'] ) );
	foreach ( array( 'class_a1', 'class_a2' ) as $key ) {
		dashfx_insert(
			Schema::table( 'course_classes' ),
			array(
				'course_id'  => $fx['course'],
				'class_id'   => $fx[ $key ],
				'created_by' => $fx['admin'],
				'created_at' => $now,
			)
		);
	}
	foreach ( array(
		1 => 'class_a1',
		2 => 'class_a1',
		3 => 'class_a1',
		4 => 'class_a1',
		5 => 'class_a2',
		6 => 'class_a2',
	) as $i => $key ) {
		gbfx_enrol(
			$l[ $i ],
			$fx['course'],
			array(
				'source'      => 'roster',
				'source_type' => 'class',
				'source_ref'  => $fx[ $key ],
				'class_id'    => $fx[ $key ],
			)
		);
		dashfx_insert(
			Schema::table( 'course_class_students' ),
			array(
				'course_id'  => $fx['course'],
				'class_id'   => $fx[ $key ],
				'user_id'    => $l[ $i ],
				'created_at' => $now,
			)
		);
	}
	gbfx_enrol( $l[7], $fx['course'], array( 'source' => 'manual' ) );
	gbfx_enrol(
		$l[8],
		$fx['course'],
		array(
			'source'      => 'purchase',
			'source_type' => 'order',
			'source_ref'  => 9999001,
		)
	);
	gbfx_enrol(
		$l[9],
		$fx['course'],
		array(
			'source'      => 'self',
			'source_type' => 'order',
			'source_ref'  => 9999002,
		)
	);
	gbfx_enrol( $l[10], $fx['course'], array( 'source' => 'manual' ) );

	$admin = wp_get_current_user();
	wp_set_current_user( $fx['admin'] );
	foreach ( array(
		'q1' => array(
			'category'  => 'quiz',
			'max_marks' => 10,
		),
		'h1' => array(
			'category' => 'homework',
			'due_at'   => wp_date( 'Y-m-d\TH:i', time() - DAY_IN_SECONDS ),
		),
		'e1' => array(
			'category'  => 'test',
			'max_marks' => 40,
		),
		'h2' => array(
			'category'  => 'homework',
			'audience'  => 'classes',
			'class_ids' => array( $fx['class_a1'] ),
		),
		'q2' => array(
			'category'  => 'quiz',
			'max_marks' => 10,
			'audience'  => 'individuals',
		),
	) as $key => $settings ) {
		$saved = Items::save( $fx['course'], $fx[ $key ], $settings, $fx['admin'] );
		if ( is_wp_error( $saved ) ) {
			throw new RuntimeException( 'Item settings failed for ' . $key . ': ' . $saved->get_error_message() );
		}
	}
	foreach ( array(
		0                => array( 'homework' => 40, 'quiz' => 30, 'test' => 30 ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		$fx['class_a1']  => array( 'homework' => 20, 'quiz' => 30, 'test' => 50 ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	) as $class_id => $weights ) {
		$saved = Weights::save( $fx['course'], $class_id, $weights, 0, $fx['admin'] );
		if ( is_wp_error( $saved ) ) {
			throw new RuntimeException( 'Weights failed: ' . $saved->get_error_message() );
		}
	}
	wp_set_current_user( $admin ? $admin->ID : 0 );

	gbfx_quiz_attempt( $fx['course'], $fx['q1'], $l[1], 'completed', 8, 10 );
	gbfx_quiz_attempt( $fx['course'], $fx['q1'], $l[2], 'completed', 5, 10 );
	gbfx_quiz_attempt( $fx['course'], $fx['q1'], $l[3], 'in-review', 0, 10 );
	gbfx_quiz_attempt( $fx['course'], $fx['q1'], $l[4], 'in-progress', 0, 10 );
	gbfx_submission( $fx['course'], $fx['h1'], $l[1], 'passed', 18 );
	gbfx_submission( $fx['course'], $fx['h1'], $l[2], 'submitted', 0 );
	gbfx_submission( $fx['course'], $fx['h1'], $l[5], 'failed', 10 );
	gbfx_quiz_attempt( $fx['course'], $fx['e1'], $l[1], 'completed', 30, 40 );
	gbfx_quiz_attempt( $fx['course'], $fx['q2'], $l[8], 'completed', 9, 10 );

	$fx['skill'] = masteryfx_skill( $tag, 'Linear equations', 'Mathematics' );
	masteryfx_state( $l[1], $fx['skill'], 90 );
	masteryfx_state( $l[2], $fx['skill'], 40 );

	if ( $perf ) {
		$fx['perf'] = gbfx_perf( $tag, $fx );
	}
	\OhMyLMS\Gradebook\Version::bump();
	return $fx;
}

/**
 * A 30 learner × 20 item course linked to class A1 (performance check): 4 modules of 5 quizzes; most cells
 * graded, some in review, some without attempt.
 *
 * @param string $tag Tag.
 * @param array  $fx  Fixtures so far.
 * @return array{course:int,learners:int[],items:int[]}
 */
function gbfx_perf( $tag, array $fx ) {
	$course   = dashfx_post( $tag, OHMYLMS_COURSE_CPT, 'Performance course' );
	$items    = array();
	$learners = array();
	for ( $m = 1; $m <= 4; $m++ ) {
		$chapter = dashfx_post( $tag, OHMYLMS_CHAPTER_CPT, 'Perf module ' . $m );
		$module  = array();
		for ( $n = 1; $n <= 5; $n++ ) {
			$quiz     = dashfx_post( $tag, OHMYLMS_QUIZ_CPT, 'Perf quiz ' . $m . '.' . $n );
			$module[] = $quiz;
			$items[]  = $quiz;
		}
		gbfx_place( $course, $chapter, $m, $module );
	}
	foreach ( $items as $index => $quiz ) {
		Items::save(
			$course,
			$quiz,
			array(
				'category'  => 0 === $index % 5 ? 'test' : 'quiz',
				'max_marks' => 10,
			),
			$fx['admin']
		);
	}
	dashfx_insert(
		Schema::table( 'course_classes' ),
		array(
			'course_id'  => $course,
			'class_id'   => $fx['class_a1'],
			'created_by' => $fx['admin'],
			'created_at' => gmdate( 'Y-m-d H:i:s' ),
		)
	);
	for ( $i = 1; $i <= 30; $i++ ) {
		$user       = dashfx_user( $tag, 'p' . $i, 'subscriber' );
		$learners[] = $user;
		gbfx_enrol( $user, $course, 0 === $i % 3 ? array( 'source' => 'purchase', 'source_type' => 'order', 'source_ref' => 9999100 + $i ) : array( 'source' => 'manual' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		foreach ( $items as $index => $quiz ) {
			$seed = ( $i * 7 + $index * 3 ) % 11;
			if ( 0 === $seed ) {
				continue;
			}
			gbfx_quiz_attempt( $course, $quiz, $user, 1 === $seed ? 'in-review' : 'completed', $seed - 1, 10 );
		}
	}
	Weights::save( $course, 0, array( 'quiz' => 70, 'test' => 30 ), 0, $fx['admin'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	return array(
		'course'   => $course,
		'learners' => $learners,
		'items'    => $items,
	);
}

/**
 * Remove every fixture of a tag: gradebook rows, enrolment projections, skills and states, then the
 * dashboard fixtures (users, posts, schools, classes).
 *
 * @param string $tag Tag.
 * @return void
 */
function gbfx_delete( $tag ) {
	global $wpdb;
	$like    = $wpdb->esc_like( $tag ) . '%';
	$users   = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT ID FROM %i WHERE user_login LIKE %s', $wpdb->users, $like ) ) );
	$posts   = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT ID FROM %i WHERE post_title LIKE %s', $wpdb->posts, $like ) ) );
	$classes = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE name LIKE %s', Schema::table( 'classes' ), $like ) ) );
	if ( GradebookSchema::ready() ) {
		foreach ( $posts as $post ) {
			foreach ( array( 'gradebook_items', 'gradebook_weights', 'gradebook_cells', 'gradebook_cell_history' ) as $table ) {
				$wpdb->delete( GradebookSchema::table( $table ), array( 'course_id' => $post ) );
			}
		}
	}
	foreach ( $posts as $post ) {
		$wpdb->delete( Schema::table( 'gradebook_overrides' ), array( 'course_id' => $post ) );
	}
	foreach ( $users as $user ) {
		Enrollments::delete( array( 'user_id' => $user ) );
		if ( EnrolmentSchema::exists() ) {
			$wpdb->delete( EnrolmentSchema::table(), array( 'user_id' => $user ) );
		}
		delete_user_meta( $user, 'ohmylms_gradebook_views' );
	}
	foreach ( $classes as $class ) {
		$wpdb->delete( Schema::table( 'course_class_students' ), array( 'class_id' => $class ) );
	}
	masteryfx_delete( $tag );
	\OhMyLMS\Gradebook\Version::bump();
}
