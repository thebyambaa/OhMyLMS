<?php
/**
 * Tagged at-risk fixtures, built on the dashboard fixture helpers. Everything is named with the tag and
 * removed by riskfx_delete(), which also cleans up after an interrupted run.
 *
 * Layout (L1-L8 are learners, all enrolled in course C, linked to class A1):
 *
 * - School A: administrator `sa`, teachers `teacher` (class A1) and `teacher2` (class A2); students L1-L6, L8.
 *   Class A1: L1-L5, L8. Class A2: L6. Guardian `parent` of L6 in school A.
 * - School B: student L7, class B1 (no teacher).
 *
 * Signals: L1 active today; L2 last active 9 days ago (quiz); L3 enrolled 20 days ago, nothing since;
 * L4 active, mastery down (5 right answers 20 days ago, 10 wrong in the last days); L5 last progress 8 days
 * ago and three overdue A1 items (two due 2 days ago, one 20 days ago; plus a completed and a future one);
 * L6 active with one overdue A2 item; L7 enrolled 10 days ago; L8 completed the course 30 days ago.
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders

require_once __DIR__ . '/dashboard-fixture.php';

use OhMyLMS\Schools\Schema;

/**
 * Create the fixtures.
 *
 * @param string $tag Tag (lowercase letters and digits).
 * @return array Ids by name.
 */
function riskfx_create( $tag ) {
	global $wpdb;
	$p    = $wpdb->prefix . 'ohmylms_';
	$ago  = static function ( $days, $hours = 0 ) {
		return wp_date( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS - $hours * HOUR_IN_SECONDS );
	};
	$utc  = static function ( $days, $hours = 0 ) {
		return gmdate( 'Y-m-d H:i:s', time() - (int) round( $days * DAY_IN_SECONDS ) - $hours * HOUR_IN_SECONDS );
	};
	$gnow = gmdate( 'Y-m-d H:i:s' );
	$fx   = array( 'tag' => $tag );
	foreach ( array(
		'admin'    => 'administrator',
		'sa'       => 'ohmylms_school_admin',
		'teacher'  => 'ohmylms_teacher',
		'teacher2' => 'ohmylms_teacher',
		'parent'   => 'subscriber',
	) as $name => $role ) {
		$fx[ $name ] = dashfx_user( $tag, $name, $role );
	}
	$fx['l'] = array();
	for ( $i = 1; $i <= 8; $i++ ) {
		$fx['l'][ $i ] = dashfx_user( $tag, 'l' . $i, 'subscriber' );
	}
	$l = $fx['l'];

	foreach ( array(
		'school_a' => 'Risk School A',
		'school_b' => 'Risk School B',
	) as $key => $name ) {
		$fx[ $key ] = dashfx_insert(
			Schema::table( 'schools' ),
			array(
				'name'       => $tag . ' ' . $name,
				'slug'       => $tag . '-' . $key,
				'timezone'   => 'UTC',
				'status'     => 'active',
				'created_by' => $fx['admin'],
				'created_at' => $gnow,
			)
		);
	}
	$member = static function ( $school, $user, $role ) use ( $gnow ) {
		dashfx_insert(
			Schema::table( 'school_memberships' ),
			array(
				'school_id' => $school,
				'user_id'   => $user,
				'role'      => $role,
				'status'    => 'active',
				'joined_at' => $gnow,
			)
		);
	};
	$member( $fx['school_a'], $fx['sa'], 'school_admin' );
	$member( $fx['school_a'], $fx['teacher'], 'teacher' );
	$member( $fx['school_a'], $fx['teacher2'], 'teacher' );
	foreach ( array( 1, 2, 3, 4, 5, 6, 8 ) as $i ) {
		$member( $fx['school_a'], $l[ $i ], 'student' );
	}
	$member( $fx['school_b'], $l[7], 'student' );

	foreach ( array(
		'class_a1' => array( $fx['school_a'], 'Risk A1', array( 1, 2, 3, 4, 5, 8 ), $fx['teacher'] ),
		'class_a2' => array( $fx['school_a'], 'Risk A2', array( 6 ), $fx['teacher2'] ),
		'class_b1' => array( $fx['school_b'], 'Risk B1', array( 7 ), 0 ),
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
		$people     = array();
		foreach ( $class[2] as $i ) {
			$people[ $l[ $i ] ] = 'student';
		}
		if ( $class[3] ) {
			$people[ $class[3] ] = 'teacher';
		}
		foreach ( $people as $user => $role ) {
			dashfx_insert(
				Schema::table( 'class_memberships' ),
				array(
					'class_id'  => $fx[ $key ],
					'user_id'   => $user,
					'role'      => $role,
					'status'    => 'active',
					'joined_at' => $gnow,
				)
			);
		}
	}

	$fx['course'] = dashfx_post( $tag, OHMYLMS_COURSE_CPT, 'Risk Course' );
	$fx['lesson'] = dashfx_post( $tag, OHMYLMS_LESSON_CPT, 'Risk Lesson' );
	$fx['quiz']   = dashfx_post( $tag, OHMYLMS_QUIZ_CPT, 'Risk Quiz' );
	dashfx_insert( Schema::table( 'course_classes' ), array( 'course_id' => $fx['course'], 'class_id' => $fx['class_a1'], 'created_by' => $fx['admin'], 'created_at' => $gnow ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound

	$started          = array(
		1 => 30,
		2 => 30,
		3 => 20,
		4 => 30,
		5 => 30,
		6 => 30,
		7 => 10,
		8 => 60,
	);
	$fx['enrolments'] = array();
	foreach ( $started as $i => $days ) {
		$fx['enrolments'][ $i ] = dashfx_insert(
			$p . 'user_enrollment',
			array(
				'user_id'    => $l[ $i ],
				'course_id'  => $fx['course'],
				'status'     => 'enrolled',
				'progress'   => 8 === $i ? 'completed' : 'running',
				'start_date' => $ago( $days ),
				'end_date'   => 8 === $i ? $ago( 30 ) : '0000-00-00 00:00:00',
			)
		);
	}
	foreach ( array(
		1 => $ago( 0, 1 ),
		5 => $ago( 8 ),
		6 => $ago( 1 ),
		8 => $ago( 30 ),
	) as $i => $at ) {
		dashfx_insert(
			$p . 'user_progress',
			array(
				'enrollment_id' => $fx['enrolments'][ $i ],
				'content_id'    => $fx['lesson'],
				'content_type'  => 'lesson',
				'status'        => 'completed',
				'start_date'    => $at,
			)
		);
	}
	dashfx_insert(
		$p . 'quiz_attempts',
		array(
			'course_id'  => $fx['course'],
			'quiz_id'    => $fx['quiz'],
			'student_id' => $l[2],
			'total'      => 1,
			'status'     => 'completed',
			'start_date' => $ago( 9 ),
			'end_date'   => $ago( 9 ),
		)
	);

	// L4: five right answers 20 days ago, then ten wrong ones in the last five days (one skill).
	$fx['evidence'] = false;
	if ( \OhMyLMS\Assessment\Schema::ready() ) {
		$fx['evidence'] = true;
		$fx['term']     = 990000000 + wp_rand( 1, 9999999 );
		$event          = 990000000 + wp_rand( 1, 9999999 );
		$answers        = array();
		for ( $n = 0; $n < 5; $n++ ) {
			$answers[] = array( 1, $utc( 20, 5 - $n ) );
		}
		for ( $n = 0; $n < 10; $n++ ) {
			$answers[] = array( 0, $utc( 5 - $n * 0.4 ) );
		}
		foreach ( $answers as $n => $answer ) {
			dashfx_insert(
				\OhMyLMS\Assessment\Schema::table( 'skill_evidence' ),
				array(
					'grade_event_id' => $event + $n,
					'student_id'     => $l[4],
					'term_id'        => $fx['term'],
					'awarded'        => $answer[0],
					'available'      => 1,
					'independent'    => 1,
					'first_try'      => 1,
					'difficulty'     => 'standard',
					'source_type'    => 'practice',
					'source_id'      => 0,
					'question_id'    => 0,
					'version_id'     => 0,
					'evidence_at'    => $answer[1],
				)
			);
		}
	}

	// Class work: L5 three overdue in A1 (two due 2 days ago, one 20 days ago), one completed, one not due yet; L6 one overdue in A2.
	$work = array(
		array( 'class_a1', 2, array( 5 ), false ),
		array( 'class_a1', 2, array( 5 ), false ),
		array( 'class_a1', 20, array( 5 ), false ),
		array( 'class_a1', 3, array( 5 ), true ),
		array( 'class_a1', -3, array( 5 ), false ),
		array( 'class_a2', 1, array( 6 ), false ),
	);
	foreach ( $work as $n => $item ) {
		$assignment = dashfx_insert(
			Schema::table( 'learning_assignments' ),
			array(
				'school_id'  => $fx['school_a'],
				'class_id'   => $fx[ $item[0] ],
				'creator_id' => $fx['teacher'],
				'title'      => $tag . ' work ' . $n,
				'course_id'  => $fx['course'],
				'due_at'     => $utc( $item[1] ),
				'status'     => 'active',
				'created_at' => $utc( 25 ),
			)
		);
		foreach ( $item[2] as $i ) {
			dashfx_insert(
				Schema::table( 'assignment_recipients' ),
				array(
					'assignment_id'   => $assignment,
					'student_user_id' => $l[ $i ],
					'assigned_at'     => $utc( 25 ),
					'completed_at'    => $item[3] ? $utc( 4 ) : null,
				)
			);
		}
	}

	dashfx_insert(
		Schema::table( 'guardian_links' ),
		array(
			'school_id'        => $fx['school_a'],
			'guardian_user_id' => $fx['parent'],
			'student_user_id'  => $l[6],
			'status'           => 'active',
			'approved_by'      => $fx['admin'],
			'approved_at'      => $gnow,
		)
	);
	return $fx;
}

/**
 * Remove every fixture of a tag.
 *
 * @param string $tag Tag.
 * @return void
 */
function riskfx_delete( $tag ) {
	global $wpdb;
	$like  = $wpdb->esc_like( $tag ) . '%';
	$users = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT ID FROM %i WHERE user_login LIKE %s', $wpdb->users, $like ) ) );
	$work  = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE title LIKE %s', Schema::table( 'learning_assignments' ), $like ) ) );
	if ( $work ) {
		$wpdb->query( 'DELETE FROM ' . Schema::table( 'assignment_recipients' ) . ' WHERE assignment_id IN (' . implode( ',', $work ) . ')' );
		$wpdb->query( 'DELETE FROM ' . Schema::table( 'learning_assignments' ) . ' WHERE id IN (' . implode( ',', $work ) . ')' );
	}
	if ( $users ) {
		$in = implode( ',', $users );
		$wpdb->query( 'DELETE FROM ' . Schema::table( 'guardian_links' ) . " WHERE student_user_id IN ($in) OR guardian_user_id IN ($in)" );
		if ( \OhMyLMS\Assessment\Schema::ready() ) {
			$wpdb->query( 'DELETE FROM ' . \OhMyLMS\Assessment\Schema::table( 'skill_evidence' ) . " WHERE student_id IN ($in)" );
		}
	}
	dashfx_delete( $tag );
}
