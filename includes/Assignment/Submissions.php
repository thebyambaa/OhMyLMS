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
 * (see docs/api/students.md) and the assignment submissions API (docs/api/assignments.md). Writes stay
 * with the assignment data store and the grading routes.
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
	 * Submission totals and submissions awaiting grading, per assignment, with one query.
	 *
	 * @param int[] $assignment_ids Assignment post IDs.
	 * @return array<int,array{total:int,pending:int}> Assignments without submissions are absent.
	 */
	public static function counts_by_assignment( array $assignment_ids ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'absint', $assignment_ids ) ) );
		if ( ! $ids ) {
			return array();
		}
		$holders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- Placeholders are generated from the id count.
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT assignment_id, COUNT(*) AS total, SUM(status = 'submitted') AS pending FROM %i WHERE assignment_id IN ({$holders}) GROUP BY assignment_id", array_merge( array( self::table() ), $ids ) ) );
		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row->assignment_id ] = array(
				'total'   => (int) $row->total,
				'pending' => (int) $row->pending,
			);
		}
		return $counts;
	}

	/**
	 * Number of submissions of one assignment.
	 *
	 * @param int $assignment_id Assignment post ID.
	 * @return int
	 */
	public static function count_for_assignment( $assignment_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE assignment_id = %d', self::table(), (int) $assignment_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh count.
	}

	/**
	 * One submission of an assignment with the student's display name.
	 *
	 * @param int $assignment_id Assignment post ID.
	 * @param int $submission_id Submission ID.
	 * @return object|null Null when it does not exist or belongs to another assignment.
	 */
	public static function find( $assignment_id, $submission_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh row; grading writes it.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT a.*, u.display_name FROM %i a LEFT JOIN %i u ON u.ID = a.user_id WHERE a.id = %d AND a.assignment_id = %d', self::table(), $wpdb->users, (int) $submission_id, (int) $assignment_id ) );
		return $row ? $row : null;
	}

	/**
	 * WHERE clause and arguments of an assignment's submission list.
	 *
	 * @param int    $assignment_id Assignment post ID.
	 * @param string $status        `submitted`, `graded` or anything else for all.
	 * @param int    $course_id     Only this course, or 0.
	 * @return array{0:string,1:array}
	 */
	private static function assignment_where( $assignment_id, $status, $course_id ) {
		$where  = 'WHERE a.assignment_id = %d';
		$params = array( (int) $assignment_id );
		if ( 'submitted' === $status ) {
			$where .= " AND a.status = 'submitted'";
		} elseif ( 'graded' === $status ) {
			$where .= " AND a.status <> 'submitted'";
		}
		if ( $course_id ) {
			$where   .= ' AND a.course_id = %d';
			$params[] = (int) $course_id;
		}
		return array( $where, $params );
	}

	/**
	 * Number of an assignment's submissions matching a filter.
	 *
	 * @param int    $assignment_id Assignment post ID.
	 * @param string $status        `submitted`, `graded` or anything else for all.
	 * @param int    $course_id     Only this course, or 0.
	 * @return int
	 */
	public static function count_for_list( $assignment_id, $status, $course_id ) {
		global $wpdb;
		list( $where, $params ) = self::assignment_where( $assignment_id, $status, $course_id );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- The clause is built from fixed fragments.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i a {$where}", array_merge( array( self::table() ), $params ) ) );
	}

	/**
	 * One page of an assignment's submissions, newest first, with the student's display name.
	 *
	 * @param int    $assignment_id Assignment post ID.
	 * @param string $status        `submitted`, `graded` or anything else for all.
	 * @param int    $course_id     Only this course, or 0.
	 * @param int    $page          Page, from 1.
	 * @param int    $per_page      Page size.
	 * @return object[]
	 */
	public static function page_for_assignment( $assignment_id, $status, $course_id, $page, $per_page ) {
		global $wpdb;
		list( $where, $params ) = self::assignment_where( $assignment_id, $status, $course_id );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- The clause is built from fixed fragments.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.id, a.user_id, a.course_id, a.assignment_id, a.status, a.score, a.files, a.start_date, a.end_date, u.display_name
				FROM %i a LEFT JOIN %i u ON u.ID = a.user_id {$where}
				ORDER BY a.id DESC LIMIT %d OFFSET %d",
				array_merge( array( self::table(), $wpdb->users ), $params, array( (int) $per_page, ( (int) $page - 1 ) * (int) $per_page ) )
			)
		);
		// phpcs:enable
		return (array) $rows;
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
