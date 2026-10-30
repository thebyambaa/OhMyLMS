<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Read-only aggregates of quiz attempts for reports.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Quiz;

use OhMyLMS\Learning\CourseStructure;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only aggregates of `ohmylms_quiz_attempts` and `ohmylms_quiz_attempts_answers` behind the quiz
 * reports, the Content Hub overview and the student report (see docs/api/quiz-reports.md and
 * docs/api/students.md).
 *
 * Callers decide who may see what: a `$quiz_ids` scope of null means every quiz, an array limits the
 * rows to those quizzes ({@see GradingPolicy::gradable_quiz_ids()}). Every list is paged in SQL.
 */
final class AttemptReports {

	/**
	 * Quiz statuses shown in the "all" view of the quiz reports.
	 */
	const LISTED_STATUSES = array( 'publish', 'draft', 'pending', 'future', 'private' );

	/**
	 * Sort keys of the quiz reports and their SQL; `%s` is the direction.
	 */
	const QUIZ_ORDERBY = array(
		'title'          => 'p.post_title %s',
		'created_at'     => 'p.post_date_gmt %s',
		'attempts'       => 'COALESCE(s.attempts, 0) %s',
		'to_grade'       => 'COALESCE(s.to_grade, 0) %s',
		'last_submitted' => 's.last_submitted IS NULL ASC, s.last_submitted %s',
	);

	/**
	 * Attempts table.
	 *
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_quiz_attempts';
	}

	/**
	 * Answers table.
	 *
	 * @return string
	 */
	private static function answers_table() {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_quiz_attempts_answers';
	}

	/**
	 * Per-quiz attempt figures as a derived table.
	 *
	 * @return string SQL with one `%i` placeholder for the attempts table.
	 */
	private static function stats_sql() {
		return "SELECT quiz_id, COUNT(*) AS attempts, COUNT(DISTINCT student_id) AS learners, SUM(status = 'in-progress') AS in_progress, SUM(status = 'in-review') AS to_grade, SUM(status = 'completed') AS completed, AVG(CASE WHEN status = 'completed' THEN total END) AS average_score, MAX(CASE WHEN status <> 'in-progress' AND end_date > '1000-01-01' THEN end_date END) AS last_submitted FROM %i GROUP BY quiz_id";
	}

	/**
	 * FROM and WHERE of the filtered quiz set, with its arguments.
	 *
	 * @param array      $filters  `search`, `course`, `status` (all|publish|draft) and `activity` (all|attempted|to_grade).
	 * @param int[]|null $quiz_ids Scope: null for every quiz.
	 * @return array{0:string,1:array}|null Null when the scope is empty.
	 */
	private static function filtered( array $filters, $quiz_ids ) {
		global $wpdb;

		$status   = (string) ( $filters['status'] ?? 'all' );
		$statuses = 'all' === $status ? self::LISTED_STATUSES : array( $status );
		$where    = array( 'p.post_type = %s', 'p.post_status IN (' . implode( ',', array_fill( 0, count( $statuses ), '%s' ) ) . ')' );
		$args     = array_merge( array( self::table(), $wpdb->posts, OHMYLMS_QUIZ_CPT ), $statuses );

		if ( null !== $quiz_ids ) {
			if ( ! $quiz_ids ) {
				return null;
			}
			$where[] = 'p.ID IN (' . implode( ',', array_fill( 0, count( $quiz_ids ), '%d' ) ) . ')';
			$args    = array_merge( $args, $quiz_ids );
		}
		$search = (string) ( $filters['search'] ?? '' );
		if ( '' !== $search ) {
			$where[] = 'p.post_title LIKE %s';
			$args[]  = '%' . $wpdb->esc_like( $search ) . '%';
		}
		if ( ! empty( $filters['course'] ) ) {
			list( $sql, $course_args ) = CourseStructure::content_in_course_sql( (int) $filters['course'] );

			$where[] = "p.ID IN ({$sql})";
			$args    = array_merge( $args, $course_args );
		}
		$activity = (string) ( $filters['activity'] ?? 'all' );
		if ( 'attempted' === $activity ) {
			$where[] = 's.attempts > 0';
		} elseif ( 'to_grade' === $activity ) {
			$where[] = 's.to_grade > 0';
		}

		return array( '(' . self::stats_sql() . ') s RIGHT JOIN %i p ON s.quiz_id = p.ID WHERE ' . implode( ' AND ', $where ), $args );
	}

	/**
	 * Number of quizzes in the filtered set.
	 *
	 * @param array      $filters  See filtered().
	 * @param int[]|null $quiz_ids Scope: null for every quiz.
	 * @return int
	 */
	public static function count_quizzes( array $filters, $quiz_ids ) {
		global $wpdb;

		$filtered = self::filtered( $filters, $quiz_ids );
		if ( null === $filtered ) {
			return 0;
		}
		list( $from, $args ) = $filtered;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- Built from constants with generated placeholders.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$from}", $args ) );
	}

	/**
	 * One page of quizzes with their attempt figures.
	 *
	 * @param array      $filters  See filtered().
	 * @param int[]|null $quiz_ids Scope: null for every quiz.
	 * @param string     $orderby  Key of QUIZ_ORDERBY.
	 * @param string     $order    `asc` or `desc`.
	 * @param int        $page     Page, from 1.
	 * @param int        $per_page Page size.
	 * @return array<int,array<string,mixed>> Rows with ID, post_title, post_status and the figures of stats_sql().
	 */
	public static function quiz_page( array $filters, $quiz_ids, $orderby, $order, $page, $per_page ) {
		global $wpdb;

		$filtered = self::filtered( $filters, $quiz_ids );
		if ( null === $filtered ) {
			return array();
		}
		list( $from, $args ) = $filtered;
		$direction           = 'asc' === $order ? 'ASC' : 'DESC';
		$sort                = sprintf( self::QUIZ_ORDERBY[ $orderby ] ?? self::QUIZ_ORDERBY['last_submitted'], $direction );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- Built from constants with generated placeholders.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT p.ID, p.post_title, p.post_status, s.attempts, s.learners, s.in_progress, s.to_grade, s.completed, s.average_score, s.last_submitted FROM {$from} ORDER BY {$sort}, p.ID {$direction} LIMIT %d OFFSET %d", array_merge( $args, array( (int) $per_page, ( (int) $page - 1 ) * (int) $per_page ) ) ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Totals over the whole filtered set.
	 *
	 * @param array      $filters  See filtered().
	 * @param int[]|null $quiz_ids Scope: null for every quiz.
	 * @return array{quizzes:int,attempts:int,learners:int,to_grade:int,completed:int}
	 */
	public static function quiz_summary( array $filters, $quiz_ids ) {
		global $wpdb;

		$summary  = array(
			'quizzes'   => 0,
			'attempts'  => 0,
			'learners'  => 0,
			'to_grade'  => 0,
			'completed' => 0,
		);
		$filtered = self::filtered( $filters, $quiz_ids );
		if ( null === $filtered ) {
			return $summary;
		}
		list( $from, $args ) = $filtered;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery
		$row      = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS quizzes, COALESCE(SUM(s.attempts), 0) AS attempts, COALESCE(SUM(s.to_grade), 0) AS to_grade, COALESCE(SUM(s.completed), 0) AS completed FROM {$from}", $args ) );
		$learners = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT a.student_id) FROM %i a WHERE a.quiz_id IN (SELECT p.ID FROM {$from})", array_merge( array( self::table() ), $args ) ) );
		// phpcs:enable
		if ( $row ) {
			$summary = array(
				'quizzes'   => (int) $row->quizzes,
				'attempts'  => (int) $row->attempts,
				'learners'  => $learners,
				'to_grade'  => (int) $row->to_grade,
				'completed' => (int) $row->completed,
			);
		}

		return $summary;
	}

	/**
	 * Completed attempts at or above each quiz's pass mark, compared in marks as the learner flow does.
	 *
	 * @param array<int,float> $marks Quiz ID => pass mark ({@see QuizSettings::pass_marks()}).
	 * @return array<int,int> Quiz ID => passed attempts; quizzes without a completed attempt are absent.
	 */
	public static function passed_counts( array $marks ) {
		global $wpdb;

		if ( ! $marks ) {
			return array();
		}
		$cases = array();
		$args  = array();
		foreach ( $marks as $id => $mark ) {
			$cases[] = 'WHEN %d THEN %f';
			$args[]  = $id;
			$args[]  = $mark;
		}
		$holders = implode( ',', array_fill( 0, count( $marks ), '%d' ) );
		$case    = 'CASE quiz_id ' . implode( ' ', $cases ) . ' END';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- Placeholders are generated from the mark count.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT quiz_id, SUM(total >= {$case}) AS passed FROM %i WHERE status = 'completed' AND quiz_id IN ({$holders}) GROUP BY quiz_id", array_merge( $args, array( self::table() ), array_keys( $marks ) ) ) );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row->quiz_id ] = (int) $row->passed;
		}

		return $out;
	}

	/**
	 * Attempts waiting for manual marks.
	 *
	 * @param int[]|null $quiz_ids Scope: null for every quiz.
	 * @return int
	 */
	public static function count_to_grade( $quiz_ids ) {
		global $wpdb;

		if ( null === $quiz_ids ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE status = 'in-review'", self::table() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		if ( ! $quiz_ids ) {
			return 0;
		}
		$holders = implode( ',', array_fill( 0, count( $quiz_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE status = 'in-review' AND quiz_id IN ({$holders})", array_merge( array( self::table() ), $quiz_ids ) ) );
	}

	/**
	 * Submitted-attempt figures of one student.
	 *
	 * @param int $student_id Student (user) ID.
	 * @return array{attempts:int,in_review:int,average:float|null} Average percent of attempts with marks.
	 */
	public static function student_summary( $student_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Aggregates over plugin tables; the report must be fresh.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS attempts, SUM(a.status = 'in-review') AS in_review, AVG(CASE WHEN m.max_marks > 0 THEN a.total / m.max_marks * 100 END) AS average FROM %i a LEFT JOIN ( SELECT quiz_attempt_id, SUM(question_marks) AS max_marks FROM %i WHERE student_id = %d GROUP BY quiz_attempt_id ) m ON m.quiz_attempt_id = a.id WHERE a.student_id = %d AND a.status <> 'in-progress'",
				self::table(),
				self::answers_table(),
				(int) $student_id,
				(int) $student_id
			),
			ARRAY_A
		);

		return array(
			'attempts'  => (int) ( $row['attempts'] ?? 0 ),
			'in_review' => (int) ( $row['in_review'] ?? 0 ),
			'average'   => isset( $row['average'] ) ? (float) $row['average'] : null,
		);
	}

	/**
	 * Number of attempts of one student.
	 *
	 * @param int $student_id Student (user) ID.
	 * @return int
	 */
	public static function count_student_attempts( $student_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE student_id = %d', self::table(), (int) $student_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh report read.
	}

	/**
	 * One page of a student's attempts, newest first.
	 *
	 * @param int $student_id Student (user) ID.
	 * @param int $page       Page, from 1.
	 * @param int $per_page   Page size.
	 * @return array<int,array<string,mixed>> Rows with id, quiz_id, course_id, status, total, start_date, end_date.
	 */
	public static function student_attempts( $student_id, $page, $per_page ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh report read.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, quiz_id, course_id, status, total, start_date, end_date FROM %i WHERE student_id = %d ORDER BY start_date DESC, id DESC LIMIT %d OFFSET %d', self::table(), (int) $student_id, (int) $per_page, ( (int) $page - 1 ) * (int) $per_page ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Highest possible marks of several attempts (the sum of their question marks).
	 *
	 * @param int[] $attempt_ids Attempt IDs.
	 * @return array<int,float> Attempt ID => marks; attempts without answers are absent.
	 */
	public static function max_marks( array $attempt_ids ) {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'intval', $attempt_ids ) ) );
		if ( ! $ids ) {
			return array();
		}
		$holders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$maxes   = array();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- Placeholders are generated from the id count.
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT quiz_attempt_id, SUM(question_marks) AS max_marks FROM %i WHERE quiz_attempt_id IN ({$holders}) GROUP BY quiz_attempt_id", array_merge( array( self::answers_table() ), $ids ) ), ARRAY_A ) as $max ) {
			$maxes[ (int) $max['quiz_attempt_id'] ] = (float) $max['max_marks'];
		}
		return $maxes;
	}
}
