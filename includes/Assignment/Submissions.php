<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Read-only views of a student's assignment submissions.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Assignment;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only views of `ohmylms_assignment_attempts` and the assignments' point totals for reports
 * (see docs/api/students.md). Writes stay with the assignment data store and the grading routes.
 */
final class Submissions {

	/**
	 * Submissions table.
	 *
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_assignment_attempts';
	}

	/**
	 * Submission figures of one student.
	 *
	 * @param int $user_id Student ID.
	 * @return array{submitted:int,graded:int,passed:int,failed:int}
	 */
	public static function student_summary( $user_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Aggregate over plugin tables; the report must be fresh.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS submitted, SUM(status <> 'submitted') AS graded, SUM(status = 'passed') AS passed, SUM(status = 'failed') AS failed FROM %i WHERE user_id = %d",
				self::table(),
				(int) $user_id
			),
			ARRAY_A
		);
		return array(
			'submitted' => (int) ( $row['submitted'] ?? 0 ),
			'graded'    => (int) ( $row['graded'] ?? 0 ),
			'passed'    => (int) ( $row['passed'] ?? 0 ),
			'failed'    => (int) ( $row['failed'] ?? 0 ),
		);
	}

	/**
	 * Number of submissions of one student.
	 *
	 * @param int $user_id Student ID.
	 * @return int
	 */
	public static function count_for_student( $user_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d', self::table(), (int) $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh report read.
	}

	/**
	 * One page of a student's submissions, newest first.
	 *
	 * @param int $user_id  Student ID.
	 * @param int $page     Page, from 1.
	 * @param int $per_page Page size.
	 * @return array[] Rows with id, assignment_id, course_id, status, score, total_attempt, start_date and end_date.
	 */
	public static function page_for_student( $user_id, $page, $per_page ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh report read.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, assignment_id, course_id, status, score, total_attempt, start_date, end_date FROM %i WHERE user_id = %d ORDER BY start_date DESC, id DESC LIMIT %d OFFSET %d', self::table(), (int) $user_id, (int) $per_page, ( (int) $page - 1 ) * (int) $per_page ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Point totals of several assignments, read with one meta cache fill.
	 *
	 * @param int[] $assignment_ids Assignment post IDs.
	 * @return array<int,int> Assignment ID => total points (0 when unset).
	 */
	public static function max_scores( array $assignment_ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $assignment_ids ) ) ) );
		if ( ! $ids ) {
			return array();
		}
		update_meta_cache( 'post', $ids );
		$scores = array();
		foreach ( $ids as $id ) {
			$scores[ $id ] = (int) get_post_meta( $id, '_total_points', true );
		}
		return $scores;
	}
}
