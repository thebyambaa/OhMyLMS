<?php
/**
 * Tagged fixtures of the mastery heatmap and review-set tests: schools, classes, learners, skills with
 * approved questions, and skill states (`student_skill_state`, the derived table the widgets read). Built
 * on the dashboard fixture helpers; masteryfx_delete() removes everything of a tag.
 *
 * Layout (L1-L16 are learners, percentages are stored mastery scores; "-" is a row without evidence):
 *
 * - School A (`sa` administers it): class A1 (taught by `teacher` and `author`) L1-L6, class A2 L7-L10.
 * - School B: class B1 L11-L12. School C has no classes: L13-L15 enrolled in course CC.
 * - School D (`bounds`): 21 classes; L16 is in the first and has evidence on 41 extra skills.
 * - Skills: K1 (Mathematics, 5 questions), K2 (Science, 5), K3 (no subject, 1), K4 (no questions).
 *
 *        K1    K2    K3    K4
 *   L1   100   30    49.6  10
 *   L2   96    20    10    10
 *   L3   85    45    20    10
 *   L4   60    49.4
 *   L5   40    10
 *   L7-L10 95  (L7: a K2 row with no evidence)
 *   L11, L12   10 (K2)
 *   L13-L15 70 (K1)
 *
 * `teacher` has the teacher role only; `author` is a teacher who may also author posts (WordPress author).
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders

require_once __DIR__ . '/dashboard-fixture.php';

use OhMyLMS\Assessment\Schema as AssessmentSchema;
use OhMyLMS\Curriculum\Comparison;
use OhMyLMS\Schools\Schema;
use OhMyLMS\Skills\Taxonomy;

/**
 * Store a learner's state on a skill.
 *
 * @param int        $learner  Learner.
 * @param int        $skill    Skill.
 * @param float|null $mastery  Percentage; null for a row without evidence.
 * @return void
 */
function masteryfx_state( $learner, $skill, $mastery ) {
	dashfx_insert(
		AssessmentSchema::table( 'student_skill_state' ),
		array(
			'student_id'     => $learner,
			'term_id'        => $skill,
			'level'          => null === $mastery ? 'not-started' : 'attempted',
			'policy'         => 'evidence',
			'evidence_count' => null === $mastery ? 0 : 3,
			'mastery'        => null === $mastery ? 0 : $mastery,
			'peak'           => null === $mastery ? 0 : $mastery,
			'updated_at'     => gmdate( 'Y-m-d H:i:s' ),
		)
	);
}

/**
 * Create a skill.
 *
 * @param string $tag     Tag.
 * @param string $name    Name.
 * @param string $subject Shared subject ('' for none).
 * @return int
 * @throws RuntimeException When the term cannot be created.
 */
function masteryfx_skill( $tag, $name, $subject = '' ) {
	$term = wp_insert_term( $tag . ' ' . $name, Taxonomy::NAME, array( 'slug' => $tag . '-' . sanitize_title( $name ) ) );
	if ( is_wp_error( $term ) ) {
		throw new RuntimeException( esc_html( 'Could not create skill ' . $name . ': ' . $term->get_error_message() ) );
	}
	if ( '' !== $subject ) {
		update_term_meta( (int) $term['term_id'], Comparison::SUBJECT_META, $subject );
	}
	return (int) $term['term_id'];
}

/**
 * Create approved single-choice questions for a skill, through the REST routes (as the fixture admin).
 *
 * @param string $tag   Tag.
 * @param int    $skill Skill.
 * @param int    $count Questions.
 * @return int[]
 * @throws RuntimeException When a question cannot be created or approved.
 */
function masteryfx_questions( $tag, $skill, $count ) {
	$ids = array();
	for ( $n = 1; $n <= $count; $n++ ) {
		$request = new WP_REST_Request( 'POST', '/ohmylms/v1/questions' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'name'        => $tag . ' Q' . $skill . '-' . $n,
					'description' => 'Question ' . $n,
					'settings'    => array(
						'type'  => 'single-choice',
						'score' => array(
							'enabled' => true,
							'value'   => 1,
						),
					),
					'options'     => array(
						array(
							'answer'       => 'Right',
							'is_correct'   => 1,
							'order_number' => 1,
						),
						array(
							'answer'       => 'Wrong',
							'is_correct'   => 0,
							'order_number' => 2,
						),
					),
					'skills'      => array( 'p1' => array( 'primary' => $skill ) ),
					'bank'        => array( 'family_id' => $tag . '-' . $skill . '-' . $n ),
				)
			)
		);
		$created = rest_do_request( $request );
		if ( 201 !== $created->get_status() ) {
			throw new RuntimeException( 'Could not create a question: ' . wp_json_encode( $created->get_data() ) );
		}
		$id       = (int) $created->get_data()['id'];
		$approved = rest_do_request( new WP_REST_Request( 'POST', '/ohmylms/v1/questions/' . $id . '/approve' ) );
		if ( 200 !== $approved->get_status() ) {
			throw new RuntimeException( 'Could not approve a question: ' . wp_json_encode( $approved->get_data() ) );
		}
		$ids[] = $id;
	}
	return $ids;
}

/**
 * Create the fixtures.
 *
 * @param string $tag    Tag (lowercase letters and digits).
 * @param bool   $bounds Also create school D (21 classes, 41 extra skills).
 * @return array Ids by name.
 */
function masteryfx_create( $tag, $bounds = true ) {
	global $wpdb;
	$fx = array( 'tag' => $tag );
	foreach ( array(
		'admin'   => 'administrator',
		'sa'      => 'ohmylms_school_admin',
		'teacher' => 'ohmylms_teacher',
		'author'  => 'ohmylms_teacher',
	) as $name => $role ) {
		$fx[ $name ] = dashfx_user( $tag, $name, $role );
	}
	( new WP_User( $fx['author'] ) )->add_role( 'author' );
	$learners = array();
	for ( $i = 1; $i <= 16; $i++ ) {
		$learners[ $i ] = dashfx_user( $tag, 'l' . $i, 'subscriber' );
	}
	$fx['learners'] = $learners;
	wp_set_current_user( $fx['admin'] );

	$school = static function ( $key, $name ) use ( $tag, &$fx ) {
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
		return $fx[ $key ];
	};
	$member = static function ( $school_id, $user, $role ) {
		dashfx_insert(
			Schema::table( 'school_memberships' ),
			array(
				'school_id' => $school_id,
				'user_id'   => $user,
				'role'      => $role,
				'status'    => 'active',
				'joined_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	};
	$class  = static function ( $school_id, $name, array $students, array $teachers = array() ) use ( $tag ) {
		$id = dashfx_insert(
			Schema::table( 'classes' ),
			array(
				'school_id'        => $school_id,
				'academic_year_id' => 0,
				'name'             => $tag . ' ' . $name,
				'status'           => 'active',
			)
		);
		foreach ( array_fill_keys( $students, 'student' ) + array_fill_keys( $teachers, 'teacher' ) as $user => $role ) {
			dashfx_insert(
				Schema::table( 'class_memberships' ),
				array(
					'class_id'  => $id,
					'user_id'   => $user,
					'role'      => $role,
					'status'    => 'active',
					'joined_at' => gmdate( 'Y-m-d H:i:s' ),
				)
			);
		}
		return $id;
	};
	$pick   = static function ( array $numbers ) use ( $learners ) {
		return array_map(
			static function ( $n ) use ( $learners ) {
				return $learners[ $n ];
			},
			$numbers
		);
	};

	$a = $school( 'school_a', 'Alpha School' );
	$member( $a, $fx['sa'], 'school_admin' );
	$member( $a, $fx['teacher'], 'teacher' );
	$member( $a, $fx['author'], 'teacher' );
	foreach ( $pick( range( 1, 10 ) ) as $user ) {
		$member( $a, $user, 'student' );
	}
	$fx['class_a1'] = $class( $a, 'Class A1', $pick( range( 1, 6 ) ), array( $fx['teacher'], $fx['author'] ) );
	$fx['class_a2'] = $class( $a, 'Class A2', $pick( range( 7, 10 ) ) );

	$b = $school( 'school_b', 'Beta School' );
	foreach ( $pick( array( 11, 12 ) ) as $user ) {
		$member( $b, $user, 'student' );
	}
	$fx['class_b1'] = $class( $b, 'Class B1', $pick( array( 11, 12 ) ) );

	$c              = $school( 'school_c', 'Gamma School' );
	$fx['course_c'] = dashfx_post( $tag, OHMYLMS_COURSE_CPT, 'Gamma Course' );
	foreach ( $pick( array( 13, 14, 15 ) ) as $user ) {
		$member( $c, $user, 'student' );
		dashfx_insert(
			$wpdb->prefix . 'ohmylms_user_enrollment',
			array(
				'user_id'    => $user,
				'course_id'  => $fx['course_c'],
				'status'     => 'enrolled',
				'progress'   => 'running',
				'start_date' => current_time( 'mysql' ),
				'end_date'   => '0000-00-00 00:00:00',
			)
		);
	}

	$fx['k1'] = masteryfx_skill( $tag, 'Fractions', 'Mathematics' );
	$fx['k2'] = masteryfx_skill( $tag, 'Cells', 'Science' );
	$fx['k3'] = masteryfx_skill( $tag, 'Grammar' );
	$fx['k4'] = masteryfx_skill( $tag, 'Orbits' );
	masteryfx_questions( $tag, $fx['k1'], 5 );
	masteryfx_questions( $tag, $fx['k2'], 5 );
	masteryfx_questions( $tag, $fx['k3'], 1 );

	$states = array(
		'k1' => array(
			1  => 100,
			2  => 96,
			3  => 85,
			4  => 60,
			5  => 40,
			7  => 95,
			8  => 95,
			9  => 95,
			10 => 95,
			13 => 70,
			14 => 70,
			15 => 70,
		),
		'k2' => array(
			1  => 30,
			2  => 20,
			3  => 45,
			4  => 49.4,
			5  => 10,
			7  => null,
			11 => 10,
			12 => 10,
		),
		'k3' => array(
			1 => 49.6,
			2 => 10,
			3 => 20,
		),
		'k4' => array(
			1 => 10,
			2 => 10,
			3 => 10,
		),
	);
	foreach ( $states as $skill => $rows ) {
		foreach ( $rows as $n => $mastery ) {
			masteryfx_state( $learners[ $n ], $fx[ $skill ], $mastery );
		}
	}

	if ( $bounds ) {
		$d = $school( 'school_d', 'Delta School' );
		$member( $d, $learners[16], 'student' );
		$fx['bounds_classes'] = array();
		for ( $i = 1; $i <= 21; $i++ ) {
			$fx['bounds_classes'][] = $class( $d, sprintf( 'Delta %02d', $i ), 1 === $i ? array( $learners[16] ) : array() );
		}
		for ( $i = 1; $i <= 41; $i++ ) {
			masteryfx_state( $learners[16], masteryfx_skill( $tag, sprintf( 'Bound %02d', $i ) ), 50 );
		}
	}
	\OhMyLMS\Dashboard\ScopeResolver::reset();
	\OhMyLMS\Dashboard\Cache::flush();
	return $fx;
}

/**
 * Remove every fixture of a tag: skill states, questions with their bank rows, review-set quizzes, skills,
 * then the dashboard fixtures (users, posts, schools, classes).
 *
 * @param string $tag Tag.
 * @return void
 */
function masteryfx_delete( $tag ) {
	global $wpdb;
	$like = $wpdb->esc_like( $tag ) . '%';
	$t    = static function ( $name ) {
		return AssessmentSchema::table( $name );
	};

	$users = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT ID FROM %i WHERE user_login LIKE %s', $wpdb->users, $like ) ) );
	foreach ( $users as $user ) {
		$wpdb->delete( $t( 'student_skill_state' ), array( 'student_id' => $user ) );
	}
	$terms = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT t.term_id FROM %i t INNER JOIN %i x ON x.term_id = t.term_id WHERE x.taxonomy = %s AND t.name LIKE %s', $wpdb->terms, $wpdb->term_taxonomy, Taxonomy::NAME, $like ) ) );
	$posts = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT ID FROM %i WHERE post_title LIKE %s', $wpdb->posts, $like ) ) );
	foreach ( $posts as $post ) {
		foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE question_id = %d', $t( 'qb_question_versions' ), $post ) ) as $version ) {
			$wpdb->delete( $t( 'qb_version_skills' ), array( 'version_id' => (int) $version ) );
		}
		$wpdb->delete( $t( 'qb_question_versions' ), array( 'question_id' => $post ) );
		$wpdb->delete( $t( 'qb_questions' ), array( 'question_id' => $post ) );
		foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE quiz_id = %d', $t( 'quiz_revisions' ), $post ) ) as $revision ) {
			$wpdb->delete( $t( 'quiz_revision_slots' ), array( 'revision_id' => (int) $revision ) );
		}
		$wpdb->delete( $t( 'quiz_revisions' ), array( 'quiz_id' => $post ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_question_answers', array( 'question_id' => $post ) );
	}
	if ( $terms ) {
		$review_sets = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT post_id FROM %i WHERE meta_key = %s AND meta_value REGEXP %s', $wpdb->postmeta, \OhMyLMS\Dashboard\Widgets\ReviewSetsService::KEY_META, '\\|skill:(' . implode( '|', $terms ) . ')$' ) ) );
		foreach ( $review_sets as $quiz ) {
			wp_delete_post( $quiz, true );
		}
	}
	foreach ( $terms as $term ) {
		wp_delete_term( $term, Taxonomy::NAME );
	}
	dashfx_delete( $tag );
}
