<?php
/**
 * Tagged dashboard fixtures: two schools, four classes, staff, learners, courses with content,
 * enrolments, progress, submissions, quiz attempts and orders. Everything is named with the tag and
 * removed by dashfx_delete(), which also cleans up after an interrupted run.
 *
 * Layout (S1-S8 are learners):
 *
 * - School A: administrator `sa`, teacher `teacher`; students S1-S6. Class A1 (taught by `teacher`): S1-S5.
 *   Class A2: S6. Course C1 (linked to A1) with lessons L1-L3, an assignment and a quiz.
 * - School B: administrator `sb`; students S7, S8. Class B1: S7, S8. Course C2.
 * - Independent class I1 taught by `teacher`: S1.
 * - `lone` has the teacher role and no class; `outsider` is a subscriber.
 *
 * C1: S1-S6 enrolled, S1 completed today; L1 completed by S1-S5, L2 by S1-S2, L3 by S1 (most stop at L2).
 * Submissions waiting: S1 (C1, 5 days), S7 (C2, 5 days); S2 (C1, today) is too recent.
 * Quiz: S1-S5 answered the question, S2-S5 wrongly (80 % missed). Orders: one failed, one on hold.
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders

use OhMyLMS\Schools\Schema;

/**
 * Create a tagged user.
 *
 * @param string $tag  Tag.
 * @param string $name Short name.
 * @param string $role Role.
 * @return int
 * @throws RuntimeException When the user cannot be created.
 */
function dashfx_user( $tag, $name, $role ) {
	$id = wp_insert_user(
		array(
			'user_login'   => $tag . '_' . $name,
			'user_email'   => $tag . '_' . $name . '@example.test',
			'user_pass'    => $tag . '-Pass-' . $name . '-1!',
			'display_name' => ucfirst( $name ) . ' ' . $tag,
			'role'         => $role,
		)
	);
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( 'Could not create user ' . $name . ': ' . $id->get_error_message() );
	}
	return (int) $id;
}

/**
 * Create a tagged post.
 *
 * @param string $tag    Tag.
 * @param string $type   Post type.
 * @param string $title  Title (prefixed with the tag).
 * @param string $status Status.
 * @param string $date   Local post date ('' for now).
 * @return int
 * @throws RuntimeException When the post cannot be created.
 */
function dashfx_post( $tag, $type, $title, $status = 'publish', $date = '' ) {
	$args = array(
		'post_type'   => $type,
		'post_status' => $status,
		'post_title'  => $tag . ' ' . $title,
	);
	if ( '' !== $date ) {
		$args['post_date']     = $date;
		$args['post_date_gmt'] = get_gmt_from_date( $date );
	}
	$id = wp_insert_post( $args, true );
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( 'Could not create post ' . $title . ': ' . $id->get_error_message() );
	}
	return (int) $id;
}

/**
 * Insert a row and return its id.
 *
 * @param string $table Table.
 * @param array  $row   Row.
 * @return int
 * @throws RuntimeException When the insert fails.
 */
function dashfx_insert( $table, array $row ) {
	global $wpdb;
	if ( false === $wpdb->insert( $table, $row ) ) {
		throw new RuntimeException( 'Insert into ' . $table . ' failed: ' . $wpdb->last_error );
	}
	return (int) $wpdb->insert_id;
}

/**
 * Create the fixtures.
 *
 * @param string $tag Tag (lowercase letters and digits).
 * @return array Ids by name.
 */
function dashfx_create( $tag ) {
	global $wpdb;
	$p     = $wpdb->prefix . 'ohmylms_';
	$now   = current_time( 'mysql' );
	$ago   = static function ( $days, $hours = 0 ) {
		return wp_date( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS - $hours * HOUR_IN_SECONDS );
	};
	$fx    = array( 'tag' => $tag );
	$users = array(
		'admin'    => 'administrator',
		'sa'       => 'ohmylms_school_admin',
		'sb'       => 'ohmylms_school_admin',
		'teacher'  => 'ohmylms_teacher',
		'lone'     => 'ohmylms_teacher',
		'outsider' => 'subscriber',
	);
	foreach ( $users as $name => $role ) {
		$fx[ $name ] = dashfx_user( $tag, $name, $role );
	}
	$fx['students'] = array();
	for ( $i = 1; $i <= 8; $i++ ) {
		$fx['students'][ $i ] = dashfx_user( $tag, 's' . $i, 'subscriber' );
	}
	$s = $fx['students'];

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
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}
	$member = static function ( $school, $user, $role ) {
		dashfx_insert(
			Schema::table( 'school_memberships' ),
			array(
				'school_id' => $school,
				'user_id'   => $user,
				'role'      => $role,
				'status'    => 'active',
				'joined_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	};
	$member( $fx['school_a'], $fx['sa'], 'school_admin' );
	$member( $fx['school_a'], $fx['teacher'], 'teacher' );
	$member( $fx['school_b'], $fx['sb'], 'school_admin' );
	foreach ( array( 1, 2, 3, 4, 5, 6 ) as $i ) {
		$member( $fx['school_a'], $s[ $i ], 'student' );
	}
	foreach ( array( 7, 8 ) as $i ) {
		$member( $fx['school_b'], $s[ $i ], 'student' );
	}

	$classes = array(
		'class_a1' => array( $fx['school_a'], 'Class A1', array( 1, 2, 3, 4, 5 ), $fx['teacher'] ),
		'class_a2' => array( $fx['school_a'], 'Class A2', array( 6 ), 0 ),
		'class_b1' => array( $fx['school_b'], 'Class B1', array( 7, 8 ), 0 ),
		'class_i1' => array( 0, 'Independent I1', array( 1 ), $fx['teacher'] ),
	);
	foreach ( $classes as $key => $class ) {
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
					'user_id'   => $s[ $i ],
					'role'      => 'student',
					'status'    => 'active',
					'joined_at' => gmdate( 'Y-m-d H:i:s' ),
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
					'joined_at' => gmdate( 'Y-m-d H:i:s' ),
				)
			);
		}
	}

	$fx['course_1']   = dashfx_post( $tag, OHMYLMS_COURSE_CPT, 'Course One' );
	$fx['course_2']   = dashfx_post( $tag, OHMYLMS_COURSE_CPT, 'Course Two' );
	$fx['chapter']    = dashfx_post( $tag, OHMYLMS_CHAPTER_CPT, 'Unit' );
	$fx['lessons']    = array(
		1 => dashfx_post( $tag, OHMYLMS_LESSON_CPT, 'Lesson One' ),
		2 => dashfx_post( $tag, OHMYLMS_LESSON_CPT, 'Lesson Two' ),
		3 => dashfx_post( $tag, OHMYLMS_LESSON_CPT, 'Lesson Three' ),
	);
	$fx['assignment'] = dashfx_post( $tag, OHMYLMS_ASSIGNMENT_CPT, 'Essay' );
	$fx['quiz']       = dashfx_post( $tag, OHMYLMS_QUIZ_CPT, 'Checkpoint' );
	$fx['question']   = dashfx_post( $tag, OHMYLMS_QUESTION_CPT, 'Tricky question' );
	dashfx_insert( Schema::table( 'course_classes' ), array( 'course_id' => $fx['course_1'], 'class_id' => $fx['class_a1'], 'created_by' => $fx['admin'], 'created_at' => gmdate( 'Y-m-d H:i:s' ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	dashfx_insert( $p . 'chapter_relationship', array( 'course_id' => $fx['course_1'], 'chapter_id' => $fx['chapter'], 'order_number' => 1 ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	foreach ( $fx['lessons'] as $order => $lesson ) {
		dashfx_insert( $p . 'content_relationship', array( 'chapter_id' => $fx['chapter'], 'content_id' => $lesson, 'content_type' => 'lesson', 'order_number' => $order ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	}

	$fx['enrolments'] = array();
	foreach ( array( 1, 2, 3, 4, 5, 6 ) as $i ) {
		$fx['enrolments'][ $i ] = dashfx_insert(
			$p . 'user_enrollment',
			array(
				'user_id'    => $s[ $i ],
				'course_id'  => $fx['course_1'],
				'status'     => 'enrolled',
				'progress'   => 1 === $i ? 'completed' : 'running',
				'start_date' => $ago( 20 ),
				'end_date'   => 1 === $i ? $now : '0000-00-00 00:00:00',
			)
		);
	}
	foreach ( array( 7, 8 ) as $i ) {
		$fx['enrolments'][ $i ] = dashfx_insert(
			$p . 'user_enrollment',
			array(
				'user_id'    => $s[ $i ],
				'course_id'  => $fx['course_2'],
				'status'     => 'enrolled',
				'progress'   => 7 === $i ? 'completed' : 'running',
				'start_date' => $ago( 20 ),
				'end_date'   => 7 === $i ? $ago( 2 ) : '0000-00-00 00:00:00',
			)
		);
	}
	$done = array(
		1 => array( 1, 2, 3, 4, 5 ),
		2 => array( 1, 2 ),
		3 => array( 1 ),
	);
	foreach ( $done as $lesson => $learners ) {
		foreach ( $learners as $i ) {
			dashfx_insert(
				$p . 'user_progress',
				array(
					'enrollment_id' => $fx['enrolments'][ $i ],
					'content_id'    => $fx['lessons'][ $lesson ],
					'content_type'  => 'lesson',
					'status'        => 'completed',
					'start_date'    => $ago( 3, $lesson ),
				)
			);
		}
	}

	foreach ( array(
		array( 1, $fx['course_1'], $ago( 5 ) ),
		array( 2, $fx['course_1'], $now ),
		array( 7, $fx['course_2'], $ago( 5 ) ),
	) as $submission ) {
		dashfx_insert(
			$p . 'assignment_attempts',
			array(
				'user_id'       => $s[ $submission[0] ],
				'course_id'     => $submission[1],
				'assignment_id' => $fx['assignment'],
				'content'       => 'fixture',
				'files'         => '',
				'status'        => 'submitted',
				'start_date'    => $submission[2],
			)
		);
	}

	foreach ( array( 1, 2, 3, 4, 5 ) as $i ) {
		$attempt = dashfx_insert(
			$p . 'quiz_attempts',
			array(
				'course_id'  => $fx['course_1'],
				'quiz_id'    => $fx['quiz'],
				'student_id' => $s[ $i ],
				'total'      => 1 === $i ? 1 : 0,
				'status'     => 'completed',
				'start_date' => $ago( 1, 1 ),
				'end_date'   => $ago( 1 ),
			)
		);
		dashfx_insert(
			$p . 'quiz_attempts_answers',
			array(
				'student_id'      => $s[ $i ],
				'quiz_id'         => $fx['quiz'],
				'question_id'     => $fx['question'],
				'quiz_attempt_id' => $attempt,
				'given_answer'    => 'x',
				'question_marks'  => 1,
				'achive_mark'     => 1 === $i ? 1 : 0,
				'is_correct'      => 1 === $i ? 1 : 0,
			)
		);
	}

	$fx['order_failed'] = dashfx_post( $tag, 'ohmylms-order', 'failed order', 'ohmylms-failed', $ago( 1 ) );
	$fx['order_held']   = dashfx_post( $tag, 'ohmylms-order', 'held order', 'ohmylms-on-hold', $ago( 4 ) );
	update_post_meta( $fx['order_failed'], '_order_total', '40' );
	update_post_meta( $fx['order_held'], '_order_total', '25' );
	return $fx;
}

/**
 * Remove every fixture of a tag.
 *
 * @param string $tag Tag.
 * @return void
 */
function dashfx_delete( $tag ) {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/user.php';
	$p    = $wpdb->prefix . 'ohmylms_';
	$like = $wpdb->esc_like( $tag ) . '%';

	$users = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT ID FROM %i WHERE user_login LIKE %s', $wpdb->users, $like ) ) );
	$posts = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT ID FROM %i WHERE post_title LIKE %s', $wpdb->posts, $like ) ) );
	if ( $users ) {
		$in          = implode( ',', $users );
		$enrolments  = array_map( 'intval', (array) $wpdb->get_col( "SELECT id FROM {$p}user_enrollment WHERE user_id IN ($in)" ) );
		$attempt_ids = array_map( 'intval', (array) $wpdb->get_col( "SELECT id FROM {$p}quiz_attempts WHERE student_id IN ($in)" ) );
		if ( $enrolments ) {
			$wpdb->query( "DELETE FROM {$p}user_progress WHERE enrollment_id IN (" . implode( ',', $enrolments ) . ')' );
		}
		if ( $attempt_ids ) {
			$wpdb->query( "DELETE FROM {$p}quiz_attempts_answers WHERE quiz_attempt_id IN (" . implode( ',', $attempt_ids ) . ')' );
		}
		$wpdb->query( "DELETE FROM {$p}quiz_attempts WHERE student_id IN ($in)" );
		$wpdb->query( "DELETE FROM {$p}user_enrollment WHERE user_id IN ($in)" );
		$wpdb->query( "DELETE FROM {$p}assignment_attempts WHERE user_id IN ($in)" );
	}
	if ( $posts ) {
		$in = implode( ',', $posts );
		$wpdb->query( "DELETE FROM {$p}chapter_relationship WHERE course_id IN ($in) OR chapter_id IN ($in)" );
		$wpdb->query( "DELETE FROM {$p}content_relationship WHERE chapter_id IN ($in) OR content_id IN ($in)" );
		$wpdb->query( "DELETE FROM {$p}course_classes WHERE course_id IN ($in)" );
		$wpdb->query( "DELETE FROM {$p}course_class_students WHERE course_id IN ($in)" );
		foreach ( $posts as $post ) {
			wp_delete_post( $post, true );
		}
	}
	$schools = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE name LIKE %s', Schema::table( 'schools' ), $like ) ) );
	$classes = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE name LIKE %s', Schema::table( 'classes' ), $like ) ) );
	if ( $classes ) {
		$in = implode( ',', $classes );
		$wpdb->query( 'DELETE FROM ' . Schema::table( 'class_memberships' ) . " WHERE class_id IN ($in)" );
		$wpdb->query( 'DELETE FROM ' . Schema::table( 'course_classes' ) . " WHERE class_id IN ($in)" );
		$wpdb->query( 'DELETE FROM ' . Schema::table( 'classes' ) . " WHERE id IN ($in)" );
	}
	if ( $schools ) {
		$in = implode( ',', $schools );
		$wpdb->query( 'DELETE FROM ' . Schema::table( 'school_memberships' ) . " WHERE school_id IN ($in)" );
		$wpdb->query( 'DELETE FROM ' . Schema::table( 'school_audit_log' ) . " WHERE school_id IN ($in)" );
		$wpdb->query( 'DELETE FROM ' . Schema::table( 'schools' ) . " WHERE id IN ($in)" );
	}
	foreach ( $users as $user ) {
		wp_delete_user( $user );
	}
	\OhMyLMS\Dashboard\Cache::flush();
}
