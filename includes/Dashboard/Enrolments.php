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
	 * Learners of the scope with at least one enrolment that is not completed, with their latest enrolment
	 * start, course completion or progress row (site time), for the at-risk inactivity rule.
	 *
	 * @param Scope $scope Scope.
	 * @return array<int,string> Learner => latest datetime ('' when none is recorded).
	 */
	public static function open_learners( Scope $scope ) {
		$args    = array( self::table() );
		$learner = $scope->learner_condition( 'e.user_id', $args );
		$course  = $scope->course_condition( 'e.course_id', $args );
		$rows    = self::rows(
			"SELECT e.user_id, GREATEST( MAX( e.start_date ), COALESCE( MAX( CASE WHEN e.progress = 'completed' THEN e.end_date END ), '0000-00-00 00:00:00' ) ) AS at
			FROM %i e WHERE e.status = 'enrolled' AND $learner AND $course
			GROUP BY e.user_id HAVING SUM( CASE WHEN e.progress = 'completed' THEN 0 ELSE 1 END ) > 0",
			$args
		);

		$learners = array();
		foreach ( $rows as $row ) {
			$learners[ (int) $row['user_id'] ] = (string) $row['at'];
		}
		if ( ! $learners ) {
			return array();
		}
		$args    = array( self::progress_table(), self::table() );
		$learner = $scope->learner_condition( 'e.user_id', $args );
		$course  = $scope->course_condition( 'e.course_id', $args );
		foreach ( self::rows( "SELECT e.user_id, MAX( p.start_date ) AS at FROM %i p INNER JOIN %i e ON e.id = p.enrollment_id WHERE e.status = 'enrolled' AND $learner AND $course GROUP BY e.user_id", $args ) as $row ) {
			$id = (int) $row['user_id'];
			if ( isset( $learners[ $id ] ) && (string) $row['at'] > $learners[ $id ] ) {
				$learners[ $id ] = (string) $row['at'];
			}
		}
		return $learners;
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

	/**
	 * Courses of the scope with enrolled learners, most enrolled first (course health).
	 *
	 * @param Scope $scope Scope.
	 * @param int   $limit Most courses returned.
	 * @return array{courses:array[],total:int} Rows with course_id, enrolled and completed; total = courses with learners.
	 */
	public static function courses_by_enrolment( Scope $scope, $limit ) {
		global $wpdb;
		$args    = array( self::table(), $wpdb->posts, OHMYLMS_COURSE_CPT );
		$learner = $scope->learner_condition( 'e.user_id', $args );
		$course  = $scope->course_condition( 'e.course_id', $args );
		$from    = "FROM %i e INNER JOIN %i c ON c.ID = e.course_id AND c.post_type = %s AND c.post_status NOT IN ( 'trash', 'auto-draft' )
			WHERE e.status = 'enrolled' AND $learner AND $course";
		$total   = self::rows( "SELECT COUNT( DISTINCT e.course_id ) AS n $from", $args );
		$args[]  = max( 1, (int) $limit );
		$rows    = self::rows(
			"SELECT e.course_id, COUNT( DISTINCT e.user_id ) AS enrolled, COUNT( DISTINCT CASE WHEN e.progress = 'completed' THEN e.user_id END ) AS completed
			$from GROUP BY e.course_id ORDER BY enrolled DESC, e.course_id ASC LIMIT %d",
			$args
		);
		return array(
			'courses' => array_map(
				static function ( $row ) {
					return array(
						'course_id' => (int) $row['course_id'],
						'enrolled'  => (int) $row['enrolled'],
						'completed' => (int) $row['completed'],
					);
				},
				$rows
			),
			'total'   => (int) ( $total[0]['n'] ?? 0 ),
		);
	}

	/**
	 * Learners of the scope who completed each content item, for several courses in one query.
	 *
	 * @param Scope $scope      Scope.
	 * @param int[] $course_ids Courses.
	 * @return array<int,array<int,int>> Course id => content id => learners who completed it.
	 */
	public static function completed_items_by_course( Scope $scope, array $course_ids ) {
		if ( ! $course_ids ) {
			return array();
		}
		$args    = array( self::progress_table(), self::table() );
		$in      = Scope::in_condition( 'e.course_id', $course_ids, $args );
		$learner = $scope->learner_condition( 'e.user_id', $args );
		$rows    = self::rows(
			"SELECT e.course_id, p.content_id, COUNT( DISTINCT e.user_id ) AS n FROM %i p INNER JOIN %i e ON e.id = p.enrollment_id
			WHERE e.status = 'enrolled' AND p.status = 'completed' AND $in AND $learner
			GROUP BY e.course_id, p.content_id",
			$args
		);

		$items = array();
		foreach ( $rows as $row ) {
			$items[ (int) $row['course_id'] ][ (int) $row['content_id'] ] = (int) $row['n'];
		}
		return $items;
	}

	/**
	 * How many enrolments of the scope completed how many content items, per course (for median progress).
	 *
	 * @param Scope $scope      Scope.
	 * @param int[] $course_ids Courses.
	 * @return array<int,array<int,int>> Course id => items completed (-1 = course completed) => enrolments.
	 */
	public static function progress_histogram( Scope $scope, array $course_ids ) {
		if ( ! $course_ids ) {
			return array();
		}
		$args    = array( self::table(), self::progress_table() );
		$in      = Scope::in_condition( 'e.course_id', $course_ids, $args );
		$learner = $scope->learner_condition( 'e.user_id', $args );
		$rows    = self::rows(
			"SELECT x.course_id, x.done, COUNT(*) AS n FROM (
				SELECT e.id, e.course_id, CASE WHEN e.progress = 'completed' THEN -1 ELSE COUNT( DISTINCT p.content_id ) END AS done
				FROM %i e LEFT JOIN %i p ON p.enrollment_id = e.id AND p.status = 'completed'
				WHERE e.status = 'enrolled' AND $in AND $learner
				GROUP BY e.id, e.course_id, e.progress
			) x GROUP BY x.course_id, x.done",
			$args
		);

		$histogram = array();
		foreach ( $rows as $row ) {
			$histogram[ (int) $row['course_id'] ][ (int) $row['done'] ] = (int) $row['n'];
		}
		return $histogram;
	}

	/**
	 * Enrolments of the scope with and without a finished quiz attempt in their course, with the lessons
	 * they completed, per course (insights).
	 *
	 * @param Scope    $scope      Scope.
	 * @param int[]    $course_ids Courses.
	 * @param string[] $finished   Statuses of finished quiz attempts.
	 * @return array[] Rows with course_id, took (bool), enrolments and lessons (completed lessons).
	 */
	public static function lessons_by_quiz_taking( Scope $scope, array $course_ids, array $finished ) {
		global $wpdb;
		if ( ! $course_ids || ! $finished ) {
			return array();
		}
		$statuses = implode( ',', array_fill( 0, count( $finished ), '%s' ) );
		$args     = array_merge( array( $wpdb->prefix . 'ohmylms_quiz_attempts' ), array_values( $finished ), array( self::table(), self::progress_table() ) );
		$in       = Scope::in_condition( 'e.course_id', $course_ids, $args );
		$learner  = $scope->learner_condition( 'e.user_id', $args );
		$rows     = self::rows(
			"SELECT x.course_id, x.took, COUNT(*) AS enrolments, SUM( x.lessons ) AS lessons FROM (
				SELECT e.id, e.course_id,
				CASE WHEN EXISTS ( SELECT 1 FROM %i q WHERE q.student_id = e.user_id AND q.course_id = e.course_id AND q.status IN ( $statuses ) ) THEN 1 ELSE 0 END AS took,
				COUNT( DISTINCT p.content_id ) AS lessons
				FROM %i e LEFT JOIN %i p ON p.enrollment_id = e.id AND p.status = 'completed' AND p.content_type = 'lesson'
				WHERE e.status = 'enrolled' AND $in AND $learner
				GROUP BY e.id, e.course_id
			) x GROUP BY x.course_id, x.took",
			$args
		);
		return array_map(
			static function ( $row ) {
				return array(
					'course_id'  => (int) $row['course_id'],
					'took'       => 1 === (int) $row['took'],
					'enrolments' => (int) $row['enrolments'],
					'lessons'    => (int) $row['lessons'],
				);
			},
			$rows
		);
	}

	/**
	 * The latest enrolments, course completions and lesson completions of the scope (Happening now).
	 *
	 * @param Scope  $scope Scope.
	 * @param string $start Oldest datetime (site time).
	 * @param string $end   Newest datetime.
	 * @param int    $limit Most events.
	 * @return array[] Rows with type (enrolled, course_completed, lesson_completed), id, user_id, course_id, content_id and at, newest first.
	 */
	public static function recent_events( Scope $scope, $start, $end, $limit ) {
		$limit   = max( 1, (int) $limit );
		$args    = array();
		$parts   = array();
		$sources = array(
			'start_date' => 'enrolled',
			'end_date'   => 'course_completed',
		);
		foreach ( $sources as $column => $type ) {
			$args[]  = $type;
			$args[]  = $column;
			$args[]  = self::table();
			$args[]  = $column;
			$args[]  = $start;
			$args[]  = $end;
			$learner = $scope->learner_condition( 'e.user_id', $args );
			$course  = $scope->course_condition( 'e.course_id', $args );
			$done    = 'course_completed' === $type ? " AND e.progress = 'completed'" : '';
			$args[]  = $column;
			$args[]  = $limit;
			$parts[] = "( SELECT %s AS type, e.id, e.user_id, e.course_id, 0 AS content_id, %i AS at FROM %i e
				WHERE e.status = 'enrolled'$done AND %i BETWEEN %s AND %s AND $learner AND $course ORDER BY %i DESC, e.id DESC LIMIT %d )";
		}
		$args[]  = self::progress_table();
		$args[]  = self::table();
		$args[]  = $start;
		$args[]  = $end;
		$learner = $scope->learner_condition( 'e.user_id', $args );
		$course  = $scope->course_condition( 'e.course_id', $args );
		$args[]  = $limit;
		$parts[] = "( SELECT 'lesson_completed' AS type, p.id, e.user_id, e.course_id, p.content_id, p.start_date AS at FROM %i p INNER JOIN %i e ON e.id = p.enrollment_id
			WHERE e.status = 'enrolled' AND p.status = 'completed' AND p.content_type = 'lesson' AND p.start_date BETWEEN %s AND %s AND $learner AND $course
			ORDER BY p.start_date DESC, p.id DESC LIMIT %d )";
		$args[]  = $limit;
		return array_map(
			static function ( $row ) {
				return array(
					'type'       => (string) $row['type'],
					'id'         => (int) $row['id'],
					'user_id'    => (int) $row['user_id'],
					'course_id'  => (int) $row['course_id'],
					'content_id' => (int) $row['content_id'],
					'at'         => (string) $row['at'],
				);
			},
			self::rows( implode( ' UNION ALL ', $parts ) . ' ORDER BY at DESC, id DESC LIMIT %d', $args )
		);
	}

	/**
	 * The scope's courses with the most enrolled learners, each with (up to a limit of) those learners.
	 *
	 * @param Scope $scope        Scope.
	 * @param int   $limit        Most courses.
	 * @param int   $learners_max Most learner ids per course.
	 * @return array<int,array{count:int,learners:int[]}> Course id => enrolled count and sorted learner ids, most learners first.
	 */
	public static function learners_by_course( Scope $scope, $limit, $learners_max ) {
		$args    = array( self::table() );
		$learner = $scope->learner_condition( 'user_id', $args );
		$course  = $scope->course_condition( 'course_id', $args );
		$args[]  = max( 1, (int) $limit );
		$top     = self::rows(
			"SELECT course_id, COUNT( DISTINCT user_id ) AS n FROM %i WHERE status = 'enrolled' AND $learner AND $course
			GROUP BY course_id ORDER BY n DESC, course_id ASC LIMIT %d",
			$args
		);
		$courses = array();
		foreach ( $top as $row ) {
			$args    = array( self::table(), (int) $row['course_id'] );
			$learner = $scope->learner_condition( 'user_id', $args );
			$args[]  = max( 1, (int) $learners_max );
			$ids     = array_map( 'intval', wp_list_pluck( self::rows( "SELECT DISTINCT user_id FROM %i WHERE status = 'enrolled' AND course_id = %d AND $learner ORDER BY user_id LIMIT %d", $args ), 'user_id' ) );

			$courses[ (int) $row['course_id'] ] = array(
				'count'    => (int) $row['n'],
				'learners' => $ids,
			);
		}
		return $courses;
	}
}
