<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Feature-owned hook operations, with registration kept in the compatibility adapter.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Student;

defined( 'ABSPATH' ) || exit;

/** Owns the Student operations delegated by CommonHook. */
final class Activity {

	/**
	 * Set users last login
	 *
	 * @param mixed  $user_login User login.
	 * @param object $user User object.
	 * .
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function set_users_last_login( $user_login, $user ) {
		update_user_meta( $user->ID, '_ohmylms_last_login', current_time( 'mysql' ) );
	}
}
