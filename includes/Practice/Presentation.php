<?php // phpcs:ignore WordPress.Files.FileName -- Existing PSR-4 class/file mapping.
/**
 * Read-only practice entry information.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Practice;

defined( 'ABSPATH' ) || exit;

/** Entry counts use the same authorized pool as session creation. */
final class Presentation {
	/**
	 * Planned question count; templates can generate repeated instances.
	 *
	 * @param array $pool Authorized pool, never sent to the browser.
	 * @param int   $requested Requested item limit.
	 * @return int
	 */
	public static function count( array $pool, $requested ) {
		$limit = max( 3, min( 30, (int) $requested ) );
		foreach ( $pool as $question ) {
			if ( ! empty( $question['is_template'] ) ) {
				return $limit;
			}
		}
		return min( $limit, count( array_unique( array_column( $pool, 'question_id' ) ) ) );
	}

	/**
	 * Count practice questions available to the current visitor in this course.
	 *
	 * @param int $skill Skill term ID.
	 * @param int $course Course ID or zero for catalog practice.
	 * @param int $requested Requested limit.
	 * @return int
	 */
	public static function available( $skill, $course, $requested ) {
		$pool = \OhMyLMS\Learning\PracticeAccessPolicy::pool( Selector::pool( $skill ), array( 'student_id' => get_current_user_id() ), $skill, $course );
		return self::count( $pool, $requested );
	}
}
