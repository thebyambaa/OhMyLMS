<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class StudentFactory.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Factory;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use OhMyLMS\Data\Student;

/**
 * Class StudentFactory
 *
 * This class is responsible for creating and managing student-related user objects.
 * It provides methods to retrieve student objects based on user ID or WP_User object.
 *
 * @package OhMyLMS\Factory
 * @since 1.0.0
 */
class StudentFactory {

	/**
	 * Get student object based on user ID or WP_User object.
	 *
	 * @param mixed $student_id The user ID, WP_User object, or false for current user.
	 * @return \OhMyLMS\Data\Student|false Returns a Student object if successful, otherwise false.
	 * @since 1.0.0
	 */
	public function get_student( $student_id = false ) {
		$user_id = $this->get_student_id( $student_id );
		if ( ! $user_id ) {
			return false;
		}
		return new Student( $user_id );
	}

	/**
	 * Get student user ID from various input types.
	 *
	 * @param mixed $student Input can be user ID, WP_User, or false for current user.
	 * @return int|false
	 * @since 1.0.0
	 */
	private function get_student_id( $student ) {
		if ( false === $student ) {
			return get_current_user_id() ?: false;
		}
		if ( is_numeric( $student ) ) {
			return $this->is_student_exist( $student ) ? absint( $student ) : false;
		}
		if ( $student instanceof \WP_User ) {
			return $this->is_student_exist( $student->ID ) ? $student->ID : false;
		}
		if ( is_object( $student ) && ! empty( $student->ID ) ) {
			return $this->is_student_exist( $student->ID ) ? $student->ID : false;
		}
		return false;
	}

	/**
	 * Checks whether a user with the given ID exists and is a student.
	 *
	 * @param int $user_id The user ID to check.
	 * @return bool Returns true if the user exists and is a student, otherwise false.
	 * @since 1.0.0
	 */
	public function is_student_exist( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		// 'ohmylms_student' is the dedicated role (1.2.12+); 'subscriber' is kept
		// for backward compatibility with users created before the migration
		// ran, or by an add-on still on an older version. 'administrator' has
		// been accepted since 1.0.0 (admins previewing courses) — unchanged.
		$valid_roles = array( 'subscriber', 'administrator' );
		if ( function_exists( 'ohmylms_get_student_role' ) ) {
			$valid_roles[] = ohmylms_get_student_role();
		}

		return (bool) array_intersect( $valid_roles, (array) $user->roles );
	}
}
