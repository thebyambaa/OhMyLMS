<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Read-only SQL of the built-in dashboard widgets.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard;

defined( 'ABSPATH' ) || exit;

/**
 * Aggregates over assignment submissions, quiz attempts and answers, orders and subscriptions, always
 * limited to a scope's learners and courses. Each method is one or two aggregate queries; nothing loads
 * an object per row. Methods throw RuntimeException on a database error. Enrolment and progress reads
 * are in {@see Enrolments}; money comes from `Analytics\Repository`.
 */
final class Repository {

	const ORDER_TYPE        = 'ohmylms-order';
	const SUBSCRIPTION_TYPE = 'ohmylms-subscription';

	/**
	 * Attempt statuses of finished, graded quiz attempts.
	 */
	const FINISHED_ATTEMPT = array( 'completed', 'passed', 'failed' );

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
	 * One row of a prepared query.
	 *
	 * @param string $sql  SQL with placeholders.
	 * @param array  $args Values.
	 * @return array
	 */
	private static function row( $sql, array $args ) {
		$rows = self::rows( $sql, $args );
		return $rows ? $rows[0] : array();
	}

	/**
	 * Plugin table name.
	 *
	 * @param string $name Name without prefix.
	 * @return string
	 */
	private static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_' . $name;
	}

	/**
	 * Assignment submissions waiting to be graded since before a datetime.
	 *
	 * @param Scope  $scope  Scope.
	 * @param string $before Submitted before this datetime (site time).
	 * @return array{count:int,assignments:int,oldest:string|null,oldest_assignment:int,oldest_title:string}
	 */
	public static function waiting_submissions( Scope $scope, $before ) {
		global $wpdb;
		$args    = array( self::table( 'assignment_attempts' ), $wpdb->posts, $before );
		$learner = $scope->learner_condition( 'a.user_id', $args );
		$course  = $scope->course_condition( 'a.course_id', $args );
		$from    = "FROM %i a INNER JOIN %i p ON p.ID = a.assignment_id AND p.post_status NOT IN ( 'trash', 'auto-draft' )
			WHERE a.status = 'submitted' AND a.start_date < %s AND $learner AND $course";
		$total   = self::row( "SELECT COUNT(*) AS n, COUNT( DISTINCT a.assignment_id ) AS assignments $from", $args );
		$oldest  = self::row( "SELECT a.assignment_id, a.start_date, p.post_title $from ORDER BY a.start_date ASC, a.id ASC LIMIT 1", $args );
		return array(
			'count'             => (int) ( $total['n'] ?? 0 ),
			'assignments'       => (int) ( $total['assignments'] ?? 0 ),
			'oldest'            => isset( $oldest['start_date'] ) ? (string) $oldest['start_date'] : null,
			'oldest_assignment' => (int) ( $oldest['assignment_id'] ?? 0 ),
			'oldest_title'      => html_entity_decode( (string) ( $oldest['post_title'] ?? '' ), ENT_QUOTES, 'UTF-8' ),
		);
	}

	/**
	 * Orders in one status, optionally only those dated in a window.
	 *
	 * @param string      $status Post status such as `ohmylms-failed`.
	 * @param string|null $start  Start datetime, or null for any date.
	 * @param string|null $end    End datetime.
	 * @return array{count:int,total:float,oldest:string|null}
	 */
	public static function orders_in_status( $status, $start = null, $end = null ) {
		global $wpdb;
		$args  = array( $wpdb->posts, $wpdb->postmeta, self::ORDER_TYPE, (string) $status );
		$range = '';
		if ( null !== $start ) {
			$range  = ' AND p.post_date BETWEEN %s AND %s';
			$args[] = $start;
			$args[] = $end;
		}
		$row = self::row(
			"SELECT COUNT(*) AS n, COALESCE( SUM( CAST( t.meta_value AS DECIMAL(20,4) ) ), 0 ) AS total, MIN( p.post_date ) AS oldest
			FROM %i p LEFT JOIN %i t ON t.post_id = p.ID AND t.meta_key = '_order_total'
			WHERE p.post_type = %s AND p.post_status = %s$range",
			$args
		);
		return array(
			'count'  => (int) ( $row['n'] ?? 0 ),
			'total'  => (float) ( $row['total'] ?? 0 ),
			'oldest' => empty( $row['oldest'] ) ? null : (string) $row['oldest'],
		);
	}

	/**
	 * Failed renewal orders (orders that carry `_subscription_renewal_id`) per day.
	 *
	 * @param string $start Start datetime.
	 * @param string $end   End datetime.
	 * @return array<string,int> `Y-m-d` => failed renewals.
	 */
	public static function failed_renewals_per_day( $start, $end ) {
		global $wpdb;
		$rows = self::rows(
			"SELECT SUBSTRING( p.post_date, 1, 10 ) AS day, COUNT( DISTINCT p.ID ) AS n FROM %i p
			INNER JOIN %i r ON r.post_id = p.ID AND r.meta_key = '_subscription_renewal_id'
			WHERE p.post_type = %s AND p.post_status = 'ohmylms-failed' AND p.post_date BETWEEN %s AND %s GROUP BY day",
			array( $wpdb->posts, $wpdb->postmeta, self::ORDER_TYPE, $start, $end )
		);
		$days = array();
		foreach ( $rows as $row ) {
			$days[ (string) $row['day'] ] = (int) $row['n'];
		}
		return $days;
	}

	/**
	 * Subscriptions on hold (a renewal payment failed and was not recovered).
	 *
	 * @return int
	 */
	public static function subscriptions_on_hold() {
		global $wpdb;
		$row = self::row( "SELECT COUNT(*) AS n FROM %i WHERE post_type = %s AND post_status = 'ohmylms-on-hold'", array( $wpdb->posts, self::SUBSCRIPTION_TYPE ) );
		return (int) ( $row['n'] ?? 0 );
	}

	/**
	 * Questions most learners of the scope answered wrongly in finished attempts of a window.
	 *
	 * @param Scope  $scope        Scope.
	 * @param string $start        Start datetime (attempt end).
	 * @param string $end          End datetime.
	 * @param int    $min_learners Fewest distinct learners who answered.
	 * @param float  $min_rate     Smallest share (0-1) of wrong answers.
	 * @param int    $limit        Most questions returned.
	 * @return array[] Rows with question_id, title, quiz_id, quiz_title, learners, answers and missed.
	 */
	public static function missed_questions( Scope $scope, $start, $end, $min_learners, $min_rate, $limit ) {
		global $wpdb;
		$statuses = implode( ',', array_fill( 0, count( self::FINISHED_ATTEMPT ), '%s' ) );
		$args     = array_merge( array( self::table( 'quiz_attempts_answers' ), self::table( 'quiz_attempts' ) ), self::FINISHED_ATTEMPT, array( $start, $end ) );
		$learner  = $scope->learner_condition( 'qa.student_id', $args );
		$course   = $scope->course_condition( 'qa.course_id', $args );
		$args[]   = (int) $min_learners;
		$args[]   = (float) $min_rate;
		$args[]   = (int) $limit;
		$found    = self::rows(
			"SELECT aa.question_id, MIN( aa.quiz_id ) AS quiz_id, COUNT( DISTINCT aa.student_id ) AS learners, COUNT(*) AS answers,
			SUM( CASE WHEN aa.is_correct = 1 THEN 0 ELSE 1 END ) AS missed
			FROM %i aa INNER JOIN %i qa ON qa.id = aa.quiz_attempt_id
			WHERE qa.status IN ( $statuses ) AND qa.end_date BETWEEN %s AND %s AND $learner AND $course
			GROUP BY aa.question_id
			HAVING learners >= %d AND missed / answers >= %f
			ORDER BY missed / answers DESC, learners DESC, aa.question_id ASC LIMIT %d",
			$args
		);
		$ids = array();
		foreach ( $found as $row ) {
			$ids[] = (int) $row['question_id'];
			$ids[] = (int) $row['quiz_id'];
		}
		$titles = self::titles( $ids );
		$list   = array();
		foreach ( $found as $row ) {
			$question = (int) $row['question_id'];
			$quiz     = (int) $row['quiz_id'];
			if ( ! isset( $titles[ $question ] ) ) {
				continue;
			}
			$list[] = array(
				'question_id' => $question,
				'title'       => $titles[ $question ],
				'quiz_id'     => $quiz,
				'quiz_title'  => $titles[ $quiz ] ?? '',
				'learners'    => (int) $row['learners'],
				'answers'     => (int) $row['answers'],
				'missed'      => (int) $row['missed'],
			);
		}
		return $list;
	}

	/**
	 * SQL selecting `(user_id, at)` activity rows from quiz attempts and assignment submissions, for a UNION.
	 *
	 * @param Scope  $scope Scope.
	 * @param string $start Start datetime.
	 * @param string $end   End datetime.
	 * @param array  $args  Placeholder values, appended to.
	 * @return string
	 */
	public static function attempt_activity_sql( Scope $scope, $start, $end, array &$args ) {
		$args[]   = self::table( 'quiz_attempts' );
		$args[]   = $start;
		$args[]   = $end;
		$learner  = $scope->learner_condition( 'q.student_id', $args );
		$course   = $scope->course_condition( 'q.course_id', $args );
		$sql      = "SELECT q.student_id AS user_id, q.start_date AS at FROM %i q WHERE q.start_date BETWEEN %s AND %s AND $learner AND $course";
		$args[]   = self::table( 'assignment_attempts' );
		$args[]   = $start;
		$args[]   = $end;
		$learner2 = $scope->learner_condition( 'a.user_id', $args );
		$course2  = $scope->course_condition( 'a.course_id', $args );
		return $sql . " UNION ALL SELECT a.user_id, a.start_date AS at FROM %i a WHERE a.start_date BETWEEN %s AND %s AND $learner2 AND $course2";
	}

	/**
	 * Distinct active learners per day, and in two weeks, from every activity source.
	 *
	 * Activity is an enrolment, a progress row, a quiz attempt or an assignment submission starting.
	 *
	 * @param Scope    $scope Scope.
	 * @param string   $start First datetime of the daily series.
	 * @param string   $end   Last datetime of the daily series.
	 * @param string[] $weeks Two `[start, end]` pairs: current and previous week.
	 * @return array{days:array<string,int>,weeks:int[]}
	 */
	public static function active_learners( Scope $scope, $start, $end, array $weeks ) {
		$args  = array();
		$union = self::activity_union( $scope, $start, $end, $args );
		$rows  = self::rows( "SELECT SUBSTRING( x.at, 1, 10 ) AS day, COUNT( DISTINCT x.user_id ) AS n FROM ( $union ) x GROUP BY day", $args );
		$days  = array();
		foreach ( $rows as $row ) {
			$days[ (string) $row['day'] ] = (int) $row['n'];
		}
		$counts = array();
		foreach ( $weeks as $week ) {
			$args     = array();
			$union    = self::activity_union( $scope, $week[0], $week[1], $args );
			$row      = self::row( "SELECT COUNT( DISTINCT x.user_id ) AS n FROM ( $union ) x", $args );
			$counts[] = (int) ( $row['n'] ?? 0 );
		}
		return array(
			'days'  => $days,
			'weeks' => $counts,
		);
	}

	/**
	 * UNION of every activity source in a window.
	 *
	 * @param Scope  $scope Scope.
	 * @param string $start Start datetime.
	 * @param string $end   End datetime.
	 * @param array  $args  Placeholder values, appended to.
	 * @return string
	 */
	private static function activity_union( Scope $scope, $start, $end, array &$args ) {
		$first = Enrolments::activity_sql( $scope, $start, $end, $args );
		return $first . ' UNION ALL ' . self::attempt_activity_sql( $scope, $start, $end, $args );
	}

	/**
	 * Titles of posts outside the trash, with one query.
	 *
	 * @param int[] $ids Post ids.
	 * @return array<int,string>
	 */
	private static function titles( array $ids ) {
		global $wpdb;
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		if ( ! $ids ) {
			return array();
		}
		$args   = array( $wpdb->posts );
		$in     = Scope::in_condition( 'ID', $ids, $args );
		$titles = array();
		foreach ( self::rows( "SELECT ID, post_title FROM %i WHERE post_status NOT IN ( 'trash', 'auto-draft' ) AND $in", $args ) as $row ) {
			$titles[ (int) $row['ID'] ] = html_entity_decode( wp_strip_all_tags( (string) $row['post_title'] ), ENT_QUOTES, 'UTF-8' );
		}
		return $titles;
	}
}
