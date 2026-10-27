<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Responsible to handle all user and enrollment related operations.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\User;

/**
 * Responsible to handle all user and enrollment related operations
 *
 * @since 1.0.0
 */
class UserHelper {

	/**
	 * Is user enrolled im given course
	 *
	 * @param mixed $course_id Course identifier.
	 * @param mixed $user_id User identifier.
	 * @return bool
	 * @since 1.0.0
	 */
	public static function is_user_enrolled( $course_id, $user_id ): bool {
		$enrollment_id = UserRepository::query_is_user_enrolled( $course_id, $user_id );
		return ! empty( $enrollment_id ); // Return true if user is enrolled, false otherwise.
	}
}
