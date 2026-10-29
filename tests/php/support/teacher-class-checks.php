<?php
/**
 * Scoped, removable fixtures for native teacher class management.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Schools\ClassManagement;
use OhMyLMS\Schools\Schema;
use OhMyLMS\Schools\Service;
use OhMyLMS\Student\Hub;
use OhMyLMS\Student\TeacherActions;
use OhMyLMS\Student\TeacherWorkspace;

defined( 'ABSPATH' ) || exit;

/**
 * Exercise persistence and authorization, removing every fixture in finally.
 *
 * @return int Number of successful assertions.
 * @throws RuntimeException When a fixture assertion fails.
 */
function ohmylms_teacher_class_checks() {
	global $wpdb;
	$original = get_current_user_id();
	$users    = array();
	$classes  = array();
	$schools  = array();
	$posts    = array();
	$count    = 0;
	$check    = static function ( $condition, $message ) use ( &$count ) {
		if ( ! $condition ) {
			throw new RuntimeException( esc_html( $message ) );
		}
		++$count;
	};
	$denied   = static function ( $callback, $message ) use ( $check ) {
		try {
			$callback();
		} catch ( RuntimeException $error ) {
			$check( in_array( $error->getCode(), array( 400, 403 ), true ), $message );
			return;
		}
		$check( false, $message );
	};
	$actor    = static function ( $id ) {
		wp_set_current_user( 0 );
		wp_set_current_user( $id );
	};
	$token    = static function ( $invite ) {
		parse_str( wp_parse_url( $invite['url'], PHP_URL_QUERY ), $query );
		return $query['ohmylms_invite'];
	};
	try {
		foreach ( array( 'administrator', 'ohmylms_teacher', 'ohmylms_teacher', 'subscriber', 'subscriber' ) as $index => $role ) {
			$id = wp_insert_user(
				array(
					'user_login'   => 'teacher-check-' . wp_generate_password( 12, false ),
					'user_pass'    => wp_generate_password( 40 ),
					'user_email'   => 'teacher-check-' . wp_generate_password( 12, false ) . '@example.test',
					'display_name' => 'Roster fixture ' . $index,
					'role'         => $role,
				)
			);
			$check( ! is_wp_error( $id ), 'Fixture identity creation failed.' );
			$users[] = $id;
		}
		list( $manager, $teacher, $other_teacher, $student, $other_student ) = $users;
		$actor( 0 );
		$denied(
			static function () {
				TeacherActions::apply( 'create', array( 'name' => 'Denied class' ) );
			},
			'Guest created a class.'
		);
		$actor( $teacher );
		$die_handler = static function () {
			return static function ( $message, $title, $args ) {
				throw new RuntimeException( 'Teacher request rejected.', (int) $args['response'] );
			};
		};
		add_filter( 'wp_die_handler', $die_handler );
		try {
			$_SERVER['REQUEST_METHOD'] = 'POST';
			$_POST                     = array(
				'operation' => 'create',
				'_wpnonce'  => 'invalid',
			);
			$denied(
				static function () {
					TeacherActions::handle();
				},
				'Invalid teacher form nonce accepted.'
			);
			$_SERVER['REQUEST_METHOD'] = 'GET';
			$_POST                     = array(
				'operation' => 'create',
				'_wpnonce'  => wp_create_nonce( TeacherActions::ACTION . '_create' ),
			);
			$denied(
				static function () {
					TeacherActions::handle();
				},
				'Teacher mutation accepted a GET request.'
			);
		} finally {
			remove_filter( 'wp_die_handler', $die_handler );
			$_POST                     = array();
			$_SERVER['REQUEST_METHOD'] = 'GET';
		}
		$created   = TeacherActions::apply(
			'create',
			array(
				'name'    => 'Teacher roster fixture',
				'subject' => 'Math',
				'grade'   => '6',
			)
		);
		$class_id  = $created['class_id'];
		$classes[] = $class_id;
		$check( 0 === (int) Service::row( 'classes', $class_id )['school_id'], 'Independent class acquired a school.' );
		$check( in_array( $class_id, array_map( 'intval', array_column( TeacherWorkspace::classes(), 'id' ) ), true ), 'Creator cannot see class.' );
		$check( array() === \OhMyLMS\Student\SchoolGradebook::courses( 0, $class_id, true ), 'Teacher cannot open their independent class gradebook.' );
		$denied(
			static function () use ( $class_id ) {
				\OhMyLMS\Student\SchoolGradebook::courses( 999, $class_id, true );
			},
			'Teacher gradebook accepted a mismatched school.'
		);
		TeacherActions::apply(
			'edit',
			array(
				'class_id'  => $class_id,
				'name'      => '<b>Renamed roster</b>',
				'subject'   => 'Science',
				'grade'     => '7',
				'school_id' => 999,
			)
		);
		$row = Service::row( 'classes', $class_id );
		$check( 'Renamed roster' === $row['name'] && 0 === (int) $row['school_id'], 'Edit did not sanitize or moved scope.' );
		$denied(
			static function () use ( $class_id ) {
				ClassManagement::edit( $class_id, array( 'name' => '' ) );
			},
			'Empty class name accepted.'
		);
		$actor( $other_teacher );
		$denied(
			static function () use ( $class_id ) {
				\OhMyLMS\Student\SchoolGradebook::courses( 0, $class_id, true );
			},
			'Unrelated teacher read the class gradebook.'
		);
		$denied(
			static function () use ( $class_id ) {
				ClassManagement::edit( $class_id, array( 'name' => 'Stolen' ) );
			},
			'Unrelated teacher edited class.'
		);
		$denied(
			static function () use ( $class_id ) {
				ClassManagement::students( $class_id );
			},
			'Unrelated teacher read roster.'
		);
		$actor( $teacher );
		$invite = ClassManagement::invite( $class_id, get_userdata( $student )->user_email );
		$actor( $other_student );
		$denied(
			static function () use ( $invite, $token ) {
				Service::accept( $token( $invite ) );
			},
			'Wrong recipient accepted invitation.'
		);
		$actor( $student );
		$roles    = wp_get_current_user()->roles;
		$verified = get_user_meta( $student, \OhMyLMS\Services\EmailVerificationService::META_VERIFIED, true );
		$check( ! empty( Service::accept( $token( $invite ) )['success'] ), 'Intended learner cannot accept invitation.' );
		$check( wp_get_current_user()->roles === $roles, 'Class invitation changed global roles.' );
		$check( get_user_meta( $student, \OhMyLMS\Services\EmailVerificationService::META_VERIFIED, true ) === $verified, 'Manual class link changed email verification state.' );
		$check( in_array( $class_id, array_map( 'intval', array_column( Hub::learner_classes(), 'id' ) ), true ), 'Independent class missing for learner.' );
		$check( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE school_id=0 AND user_id=%d', Schema::table( 'school_memberships' ), $student ) ), 'Standalone invitation created a wildcard school membership.' );
		$check( array( 'Roster fixture 1' ) === Hub::supporters( $class_id )['teachers'], 'Independent teacher connection missing.' );
		$denied(
			static function () use ( $invite, $token ) {
				Service::accept( $token( $invite ) );
			},
			'Invitation replay succeeded.'
		);
		$denied(
			static function () use ( $class_id ) {
				TeacherActions::apply(
					'edit',
					array(
						'class_id' => $class_id,
						'name'     => 'Student edit',
					)
				);
			},
			'Student edited class.'
		);
		$actor( $teacher );
		$check( 1 === count( ClassManagement::students( $class_id ) ), 'Roster includes staff or omits accepted learner.' );
		$check( array() === ClassManagement::students( $class_id, 'No such fixture' ), 'Roster search ignored.' );
		$revoked = ClassManagement::invite( $class_id, get_userdata( $other_student )->user_email );
		ClassManagement::revoke( $class_id, $revoked['id'] );
		$actor( $other_student );
		$denied(
			static function () use ( $revoked, $token ) {
				Service::accept( $token( $revoked ) );
			},
			'Revoked invitation accepted.'
		);
		$actor( $teacher );
		$expired = ClassManagement::invite( $class_id, get_userdata( $other_student )->user_email );
		Service::update( 'school_invitations', array( 'expires_at' => '2000-01-01 00:00:00' ), array( 'id' => $expired['id'] ) );
		$actor( $other_student );
		$denied(
			static function () use ( $expired, $token ) {
				Service::accept( $token( $expired ) );
			},
			'Expired invitation accepted.'
		);
		$actor( $teacher );
		$pending = ClassManagement::invite( $class_id, get_userdata( $other_student )->user_email );
		( new WP_User( $teacher ) )->set_role( 'subscriber' );
		$actor( $other_student );
		$denied(
			static function () use ( $pending, $token ) {
				Service::accept( $token( $pending ) );
			},
			'Issuer without teaching authority granted roster access.'
		);
		( new WP_User( $teacher ) )->set_role( 'ohmylms_teacher' );
		Service::update( 'school_invitations', array( 'role' => 'teacher' ), array( 'id' => $pending['id'] ) );
		$denied(
			static function () use ( $pending, $token ) {
				Service::accept( $token( $pending ) );
			},
			'Standalone invitation granted a teacher role.'
		);
		Service::update( 'school_invitations', array( 'role' => 'student' ), array( 'id' => $pending['id'] ) );
		Service::membership(
			'class_memberships',
			array(
				'class_id' => $class_id,
				'user_id'  => $teacher,
				'role'     => 'teacher',
			),
			false
		);
		$actor( $other_student );
		$denied(
			static function () use ( $pending, $token ) {
				Service::accept( $token( $pending ) );
			},
			'Revoked issuer still granted roster access.'
		);
		Service::membership(
			'class_memberships',
			array(
				'class_id' => $class_id,
				'user_id'  => $teacher,
				'role'     => 'teacher',
			)
		);
		$actor( $teacher );
		$denied(
			static function () use ( $class_id ) {
				TeacherActions::apply( 'archive', array( 'class_id' => $class_id ) );
			},
			'Archive did not require confirmation.'
		);
		TeacherActions::apply(
			'archive',
			array(
				'class_id' => $class_id,
				'confirm'  => 'yes',
			)
		);
		$check( ! in_array( $class_id, array_map( 'intval', array_column( TeacherWorkspace::classes(), 'id' ) ), true ), 'Archived class remained active.' );
		$check( in_array( $class_id, array_map( 'intval', array_column( TeacherWorkspace::classes( 1, 'archived' ), 'id' ) ), true ), 'Archived class cannot be found for restoration.' );
		$denied(
			static function () use ( $class_id ) {
				ClassManagement::edit( $class_id, array( 'name' => 'Archived edit' ) );
			},
			'Archived class editable.'
		);
		$actor( $other_student );
		$denied(
			static function () use ( $pending, $token ) {
				Service::accept( $token( $pending ) );
			},
			'Archived class accepted an invitation.'
		);
		$actor( $teacher );
		ClassManagement::restore( $class_id );
		$check( 1 === count( ClassManagement::students( $class_id ) ), 'Restoration lost student roster.' );
		$posts[] = wp_insert_post(
			array(
				'post_type'   => OHMYLMS_COURSE_CPT,
				'post_status' => 'publish',
				'post_title'  => 'Roster preservation fixture',
			)
		);
		$wpdb->insert(
			$wpdb->prefix . 'ohmylms_user_enrollment',
			array(
				'course_id' => $posts[0],
				'user_id'   => $student,
				'status'    => 'enrolled',
				'progress'  => 'in-progress',
			)
		);
		TeacherActions::apply(
			'remove',
			array(
				'class_id'   => $class_id,
				'student_id' => $student,
				'confirm'    => 'yes',
			)
		);
		$check( get_userdata( $student ) && \OhMyLMS\Learning\CourseProgram::enrollment( $student, $posts[0] ), 'Roster removal deleted identity or course enrollment.' );
		$check( array() === ClassManagement::students( $class_id ), 'Removed learner still on roster.' );
		$denied(
			static function () use ( $class_id, $teacher ) {
				ClassManagement::remove_student( $class_id, $teacher );
			},
			'Student removal removed a teacher.'
		);
		$actor( $manager );
		$school       = Service::create_school(
			array(
				'name'     => 'Roster school fixture',
				'slug'     => 'roster-check-' . wp_generate_password( 10, false ),
				'timezone' => 'UTC',
			)
		)['id'];
		$schools[]    = $school;
		$year         = Service::create_year(
			$school,
			array(
				'label'     => 'Fixture year',
				'starts_at' => '2026-01-01',
				'ends_at'   => '2026-12-31',
			)
		)['id'];
		$school_class = Service::create_class(
			$school,
			array(
				'name'             => 'School roster fixture',
				'academic_year_id' => $year,
			)
		)['id'];
		$classes[]    = $school_class;
		Service::membership(
			'school_memberships',
			array(
				'school_id' => $school,
				'user_id'   => $teacher,
				'role'      => 'teacher',
			)
		);
		Service::membership(
			'school_memberships',
			array(
				'school_id' => $school,
				'user_id'   => $student,
				'role'      => 'student',
			)
		);
		Service::class_member(
			$school_class,
			array(
				'user_id' => $teacher,
				'role'    => 'teacher',
			)
		);
		$actor( $teacher );
		$check( in_array( $student, array_map( 'intval', array_column( ClassManagement::students( $school_class, '', 1, true ), 'user_id' ) ), true ), 'Eligible school student missing.' );
		$check( ! in_array( $other_student, array_map( 'intval', array_column( ClassManagement::students( $school_class, '', 1, true ), 'user_id' ) ), true ), 'Unrelated site user leaked into candidates.' );
		TeacherActions::apply(
			'add',
			array(
				'class_id'   => $school_class,
				'student_id' => $student,
			)
		);
		$check( 1 === count( ClassManagement::students( $school_class ) ), 'School student not added.' );
		$check( array() === ClassManagement::students( $school_class, '', 1, true ), 'Existing roster student remained a candidate.' );
		$denied(
			static function () use ( $school_class, $other_student ) {
				TeacherActions::apply(
					'add',
					array(
						'class_id'   => $school_class,
						'student_id' => $other_student,
					)
				);
			},
			'Non-school student added directly.'
		);
		$denied(
			static function () use ( $school_class ) {
				TeacherActions::apply(
					'archive',
					array(
						'class_id' => $school_class,
						'confirm'  => 'yes',
					)
				);
			},
			'Teacher bypassed school archive authority.'
		);
		$denied(
			static function () use ( $year ) {
				TeacherActions::apply(
					'create',
					array(
						'name'             => 'Unauthorized school class',
						'academic_year_id' => $year,
					)
				);
			},
			'Teacher bypassed school class creation authority.'
		);
		$actor( $manager );
		Service::membership(
			'school_memberships',
			array(
				'school_id' => $school,
				'user_id'   => $teacher,
				'role'      => 'teacher',
			),
			false
		);
		$actor( $teacher );
		$denied(
			static function () use ( $school_class ) {
				ClassManagement::students( $school_class );
			},
			'Revoked school teacher retained roster access.'
		);
		return $count;
	} finally {
		$actor( $original );
		foreach ( $classes as $class_id ) {
			foreach ( array( 'class_memberships', 'school_invitations' ) as $table ) {
				$wpdb->delete( Schema::table( $table ), array( 'class_id' => $class_id ) );
			}
			$wpdb->delete( Schema::table( 'classes' ), array( 'id' => $class_id ) );
		}
		foreach ( $schools as $school ) {
			foreach ( array( 'school_memberships', 'academic_years', 'school_audit_log' ) as $table ) {
				$wpdb->delete( Schema::table( $table ), array( 'school_id' => $school ) );
			}
			$wpdb->delete( Schema::table( 'schools' ), array( 'id' => $school ) );
		}
		foreach ( $users as $id ) {
			$wpdb->delete( Schema::table( 'school_audit_log' ), array( 'actor_id' => $id ) );
			$wpdb->delete( $wpdb->prefix . 'ohmylms_user_enrollment', array( 'user_id' => $id ) );
			delete_transient( TeacherActions::FLASH . $id );
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $id );
		}
		foreach ( $posts as $id ) {
			wp_delete_post( $id, true );
		}
	}
}
