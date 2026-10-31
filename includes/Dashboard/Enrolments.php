<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Every enrolment and progress read of the dashboard, in one place.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard;

defined( 'ABSPATH' ) || exit;

/**
 * Aggregate reads of `ohmylms_user_enrollment` and `ohmylms_user_progress` for scopes and widgets.
 *
 * `Student\Enrollments` owns these rows but only offers per-student reads, so the dashboard's bulk reads
 * live here, and only here, until that service grows equivalents (docs/api/dashboard.md, "Known gaps").
 * An enrolment counts when its status is `enrolled`; it is completed when its progress is `completed`
 * (`end_date` is the completion time).
 */
final class Enrolments {

	/**
	 * Enrolments table.
	 *
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_user_enrollment';
	}

	/**
	 * Progress table.
	 *
	 * @return string
	 */
	private static function progress_table() {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_user_progress';
	}

	/**
	 * Run a prepared query returning rows.
	 *
	 * @param string $sql  SQL with placeholders.
	 * @param array  $args Values.
	 * @return array[]
	 * @throws \RuntimeException On a database error.
	 */
	private static function rows( $sql, array $args ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Identifiers use %i, values use placeholders, id lists come from Scope::in_condition().
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
		if ( '' !== $wpdb->last_error ) {
			throw new \RuntimeException( 'Dashboard query failed.' );
		}
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Courses some of the learners are enrolled in.
	 *
	 * @param int[] $learner_ids Learners.
	 * @return int[]
	 */
	public static function course_ids_for_learners( array $learner_ids ) {
		if ( ! $learner_ids ) {
			return array();
		}
		$args = array( self::table() );
		$in   = Scope::in_condition( 'user_id', $learner_ids, $args );
		$ids  = array_map( 'intval', wp_list_pluck( self::rows( "SELECT DISTINCT course_id FROM %i WHERE status = 'enrolled' AND $in", $args ), 'course_id' ) );
		sort( $ids );
		return $ids;
	}

	/**
	 * Course completions per day between two datetimes.
	 *
	 * @param Scope  $scope Scope.
	 * @param string $start Start datetime (site time).
	 * @param string $end   End datetime (site time).
	 * @return array<string,int> `Y-m-d` => completions.
	 */
	public static function completions_per_day( Scope $scope, $start, $end ) {
		$args    = array( self::table(), $start, $end );
		$learner = $scope->learner_condition( 'user_id', $args );
		$course  = $scope->course_condition( 'course_id', $args );
		$rows    = self::rows(
			"SELECT SUBSTRING( end_date, 1, 10 ) AS day, COUNT(*) AS n FROM %i
			WHERE status = 'enrolled' AND progress = 'completed' AND end_date BETWEEN %s AND %s AND $learner AND $course
			GROUP BY day",
			$args
		);
		$days = array();
		foreach ( $rows as $row ) {
			$days[ (string) $row['day'] ] = (int) $row['n'];
		}
		return $days;
	}

	/**
	 * SQL selecting `(user_id, at)` activity rows from enrolment starts and progress rows, for a UNION.
	 *
	 * @param Scope  $scope Scope.
	 * @param string $start Start datetime.
	 * @param string $end   End datetime.
	 * @param array  $args  Placeholder values, appended to.
	 * @return string
	 */
	public static function activity_sql( Scope $scope, $start, $end, array &$args ) {
		$args[]   = self::table();
		$args[]   = $start;
		$args[]   = $end;
		$learner  = $scope->learner_condition( 'e.user_id', $args );
		$course   = $scope->course_condition( 'e.course_id', $args );
		$sql      = "SELECT e.user_id, e.start_date AS at FROM %i e WHERE e.start_date BETWEEN %s AND %s AND $learner AND $course";
		$args[]   = self::progress_table();
		$args[]   = self::table();
		$args[]   = $start;
		$args[]   = $end;
		$learner2 = $scope->learner_condition( 'e.user_id', $args );
		$course2  = $scope->course_condition( 'e.course_id', $args );
		return $sql . " UNION ALL SELECT e.user_id, p.start_date AS at FROM %i p INNER JOIN %i e ON e.id = p.enrollment_id WHERE p.start_date BETWEEN %s AND %s AND $learner2 AND $course2";
	}

	/**
	 * Courses of the scope whose completion rate is below a threshold, worst first.
	 *
	 * @param Scope $scope        Scope.
	 * @param int   $min_learners Fewest enrolled learners a course needs to be judged.
	 * @param float $max_rate     Completion rate (0-1) below which a course stalls.
	 * @param int   $limit        Most courses returned.
	 * @return array[] Rows with course_id, enrolled and completed.
	 */
	public static function low_completion_courses( Scope $scope, $min_learners, $max_rate, $limit ) {
		$args    = array( self::table() );
		$learner = $scope->learner_condition( 'user_id', $args );
		$course  = $scope->course_condition( 'course_id', $args );
		$args[]  = (int) $min_learners;
		$args[]  = (float) $max_rate;
		$args[]  = (int) $limit;
		return array_map(
			static function ( $row ) {
				return array(
					'course_id' => (int) $row['course_id'],
					'enrolled'  => (int) $row['enrolled'],
					'completed' => (int) $row['completed'],
				);
			},
			self::rows(
				"SELECT course_id, COUNT( DISTINCT user_id ) AS enrolled, COUNT( DISTINCT CASE WHEN progress = 'completed' THEN user_id END ) AS completed
				FROM %i WHERE status = 'enrolled' AND $learner AND $course
				GROUP BY course_id HAVING enrolled >= %d AND completed / enrolled < %f
				ORDER BY completed / enrolled ASC, enrolled DESC, course_id ASC LIMIT %d",
				$args
			)
		);
	}

	/**
	 * Learners of the scope who completed each content item of a course.
	 *
	 * @param Scope $scope     Scope.
	 * @param int   $course_id Course.
	 * @return array<int,int> Content id => learners who completed it.
	 */
	public static function completed_items( Scope $scope, $course_id ) {
		$args    = array( self::progress_table(), self::table(), (int) $course_id );
		$learner = $scope->learner_condition( 'e.user_id', $args );
		$rows    = self::rows(
			"SELECT p.content_id, COUNT( DISTINCT e.user_id ) AS n FROM %i p INNER JOIN %i e ON e.id = p.enrollment_id
			WHERE e.course_id = %d AND e.status = 'enrolled' AND p.status = 'completed' AND $learner
			GROUP BY p.content_id",
			$args
		);
		$items = array();
		foreach ( $rows as $row ) {
			$items[ (int) $row['content_id'] ] = (int) $row['n'];
		}
		return $items;
	}
}
