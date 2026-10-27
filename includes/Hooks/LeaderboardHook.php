<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Hook for Course
 *
 * @package    OhMyLMSPro
 * @subpackage OhMyLMSPro/includes
 */
namespace OhMyLMS\Hooks;

use OhMyLMS\Engagement\Leaderboard;

/**
 * Plugin leaderboard hook implementation.
 */
class LeaderboardHook {

	/**
	 * Register hooks for Leaderboard hook.
	 */
	public function register_hooks() {
		add_filter( 'ohmylms_leaderboard_students', array( $this, 'filter_leaderboard_students' ), 10, 2 );
	}

	/**
	 * Filter the leaderboard students.
	 *
	 * @param array $students Students.
	 * @param int   $course_id Course identifier.
	 * @return array
	 */
	public function filter_leaderboard_students( $students, $course_id ) {
		if ( ! Leaderboard::maybe_enable() ) {
			return $students;
		}
		$students_number = Leaderboard::get_students_number();

		if ( empty( $students ) || ! is_array( $students ) ) {
			return $students;
		}

		// get the leaderboard rule.
		$rule = Leaderboard::get_leaderboard_rule();
		if ( 'completion_rate' === $rule ) {
			$updated_students = Leaderboard::get_students_by_completion_rate( $students );
		} elseif ( 'highest_quiz' === $rule ) {
			$updated_students = Leaderboard::get_students_by_highest_quiz( $students, $course_id );
		} elseif ( 'fastest_time' === $rule ) {
			$updated_students = Leaderboard::get_students_by_fastest_time( $students );
		} else {
			$updated_students = Leaderboard::get_students_by_completion_rate( $students );
		}

		if ( $students_number > 0 ) {
			if ( count( $updated_students ) >= $students_number ) {
				return $updated_students;
			} else {
				return array();
			}
		}
		return $students;
	}
}
