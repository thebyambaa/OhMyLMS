<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Read model of the at-risk signals: last activity, mastery drops and overdue work per learner.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard\Widgets;

use DateTimeImmutable;
use OhMyLMS\Assessment\Schema as AssessmentSchema;
use OhMyLMS\Dashboard\Enrolments;
use OhMyLMS\Dashboard\Period;
use OhMyLMS\Dashboard\Scope;
use OhMyLMS\Schools\Schema;
use OhMyLMS\Skills\Score;
use OhMyLMS\Skills\ScoreModel;

defined( 'ABSPATH' ) || exit;

/**
 * Aggregate reads limited to a scope's learners and courses; nothing loads an object per row.
 *
 * - Activity: enrolment starts, course completions and progress rows (through {@see Enrolments}), quiz
 *   attempts, assignment submissions (site time) and skill evidence (UTC). Only learners with an enrolment
 *   that is not completed are judged on inactivity.
 * - Mastery: the 0-100 skill score of {@see ScoreModel}, replayed from the learner's evidence as it stood at
 *   the start of the window and now (with the model's idle decay); the largest drop over the learner's skills
 *   practised in the window.
 * - Overdue: Account Hub class assignments (`learning_assignments`) due in the period and before now, not
 *   completed by the recipient; limited to the class or school of the scope.
 *
 * Methods throw RuntimeException on a database error.
 */
final class AtRiskSignals {

	/**
	 * Most learner × skill pairs replayed for the mastery rule per request.
	 */
	const MAX_SKILL_PAIRS = 5000;

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
			throw new \RuntimeException( 'At-risk query failed.' );
		}
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Unix time of a site-time datetime, or null.
	 *
	 * @param string|null $datetime MySQL datetime in the site time zone.
	 * @return int|null
	 */
	private static function site_ts( $datetime ) {
		if ( ! $datetime || 0 === strpos( (string) $datetime, '0000' ) ) {
			return null;
		}
		$date = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', substr( (string) $datetime, 0, 19 ), wp_timezone() );
		return $date ? $date->getTimestamp() : null;
	}

	/**
	 * Unix time of a UTC datetime, or null.
	 *
	 * @param string|null $datetime MySQL datetime in UTC.
	 * @return int|null
	 */
	private static function utc_ts( $datetime ) {
		if ( ! $datetime || 0 === strpos( (string) $datetime, '0000' ) ) {
			return null;
		}
		$ts = strtotime( substr( (string) $datetime, 0, 19 ) . ' UTC' );
		return false === $ts ? null : $ts;
	}

	/**
	 * Every signal of the scope's learners.
	 *
	 * @param Scope  $scope  Scope.
	 * @param Period $period Period (bounds the due dates of overdue work).
	 * @param array  $rules  {@see AtRiskSettings::rules()}.
	 * @param int    $now    Unix time of the evaluation.
	 * @return array<int,array{inactive:int|null,mastery:float|null,overdue:int}> Learner => signals, for every learner with any signal.
	 */
	public static function collect( Scope $scope, Period $period, array $rules, $now ) {
		$signals = array();
		$blank   = array(
			'inactive' => null,
			'mastery'  => null,
			'overdue'  => 0,
		);

		if ( ! empty( $rules['inactive']['enabled'] ) ) {
			foreach ( self::inactive_days( $scope, $now ) as $id => $days ) {
				$signals[ $id ]             = $signals[ $id ] ?? $blank;
				$signals[ $id ]['inactive'] = $days;
			}
		}
		if ( ! empty( $rules['mastery']['enabled'] ) ) {
			foreach ( self::mastery_drops( $scope, (int) $rules['mastery']['days'], $now ) as $id => $drop ) {
				$signals[ $id ]            = $signals[ $id ] ?? $blank;
				$signals[ $id ]['mastery'] = $drop;
			}
		}
		if ( ! empty( $rules['overdue']['enabled'] ) ) {
			$from = (string) get_gmt_from_date( $period->range()->start_sql() );
			foreach ( self::overdue( $scope, $from, gmdate( 'Y-m-d H:i:s', $now ) ) as $id => $count ) {
				$signals[ $id ]            = $signals[ $id ] ?? $blank;
				$signals[ $id ]['overdue'] = $count;
			}
		}
		return $signals;
	}

	/**
	 * Whole days since the last activity of every learner with an open enrolment.
	 *
	 * @param Scope $scope Scope.
	 * @param int   $now   Unix time.
	 * @return array<int,int>
	 */
	public static function inactive_days( Scope $scope, $now ) {
		$latest = array();
		foreach ( Enrolments::open_learners( $scope ) as $id => $at ) {
			$latest[ $id ] = self::site_ts( $at );
		}
		if ( ! $latest ) {
			return array();
		}
		$seen = static function ( $id, $ts ) use ( &$latest ) {
			if ( null !== $ts && array_key_exists( $id, $latest ) && ( null === $latest[ $id ] || $ts > $latest[ $id ] ) ) {
				$latest[ $id ] = $ts;
			}
		};

		global $wpdb;
		$args    = array( $wpdb->prefix . 'ohmylms_quiz_attempts' );
		$learner = $scope->learner_condition( 'q.student_id', $args );
		$course  = $scope->course_condition( 'q.course_id', $args );
		foreach ( self::rows( "SELECT q.student_id AS user_id, MAX( q.start_date ) AS at FROM %i q WHERE $learner AND $course GROUP BY q.student_id", $args ) as $row ) {
			$seen( (int) $row['user_id'], self::site_ts( $row['at'] ) );
		}
		$args    = array( $wpdb->prefix . 'ohmylms_assignment_attempts' );
		$learner = $scope->learner_condition( 'a.user_id', $args );
		$course  = $scope->course_condition( 'a.course_id', $args );
		foreach ( self::rows( "SELECT a.user_id, MAX( a.start_date ) AS at FROM %i a WHERE $learner AND $course GROUP BY a.user_id", $args ) as $row ) {
			$seen( (int) $row['user_id'], self::site_ts( $row['at'] ) );
		}
		if ( AssessmentSchema::ready() ) {
			$args    = array( AssessmentSchema::table( 'skill_evidence' ) );
			$learner = $scope->learner_condition( 's.student_id', $args );
			foreach ( self::rows( "SELECT s.student_id AS user_id, MAX( s.evidence_at ) AS at FROM %i s WHERE $learner GROUP BY s.student_id", $args ) as $row ) {
				$seen( (int) $row['user_id'], self::utc_ts( $row['at'] ) );
			}
		}

		$days = array();
		foreach ( $latest as $id => $ts ) {
			if ( null !== $ts ) {
				$days[ $id ] = max( 0, (int) floor( ( $now - $ts ) / DAY_IN_SECONDS ) );
			}
		}
		return $days;
	}

	/**
	 * The largest mastery drop (points) of each learner who practised a skill in the window.
	 *
	 * @param Scope $scope Scope.
	 * @param int   $days  Window in days.
	 * @param int   $now   Unix time.
	 * @return array<int,float> Learner => points dropped (only drops above zero).
	 */
	public static function mastery_drops( Scope $scope, $days, $now ) {
		if ( ! AssessmentSchema::ready() ) {
			return array();
		}
		$table   = AssessmentSchema::table( 'skill_evidence' );
		$cutoff  = $now - max( 1, (int) $days ) * DAY_IN_SECONDS;
		$args    = array( $table, gmdate( 'Y-m-d H:i:s', $cutoff ) );
		$learner = $scope->learner_condition( 'student_id', $args );
		$args[]  = self::MAX_SKILL_PAIRS;
		$pairs   = self::rows(
			"SELECT DISTINCT student_id, term_id FROM %i WHERE role = 'primary' AND superseded = 0 AND evidence_at >= %s AND $learner
			ORDER BY student_id, term_id LIMIT %d",
			$args
		);
		if ( ! $pairs ) {
			return array();
		}
		$wanted   = array();
		$students = array();
		$terms    = array();
		foreach ( $pairs as $pair ) {
			$wanted[ (int) $pair['student_id'] . ':' . (int) $pair['term_id'] ] = true;
			$students[ (int) $pair['student_id'] ]                              = true;
			$terms[ (int) $pair['term_id'] ]                                    = true;
		}
		$args   = array( $table );
		$in_s   = Scope::in_condition( 'student_id', array_keys( $students ), $args );
		$in_t   = Scope::in_condition( 'term_id', array_keys( $terms ), $args );
		$series = array();
		foreach ( self::rows(
			"SELECT student_id, term_id, awarded, available, independent, first_try, difficulty, evidence_at FROM %i
			WHERE role = 'primary' AND superseded = 0 AND $in_s AND $in_t ORDER BY evidence_at ASC, id ASC",
			$args
		) as $row ) {
			$key = (int) $row['student_id'] . ':' . (int) $row['term_id'];
			if ( isset( $wanted[ $key ] ) ) {
				$series[ $key ][] = $row;
			}
		}

		$config = Score::config();
		$drops  = array();
		foreach ( $series as $key => $rows ) {
			$before = array();
			foreach ( $rows as $row ) {
				if ( (int) self::utc_ts( $row['evidence_at'] ) < $cutoff ) {
					$before[] = $row;
				}
			}
			$drop = self::score_at( $before, $cutoff, $config ) - self::score_at( $rows, $now, $config );
			$id   = (int) strtok( $key, ':' );
			if ( $drop > 0 && $drop > ( $drops[ $id ] ?? 0 ) ) {
				$drops[ $id ] = round( $drop, 2 );
			}
		}
		return $drops;
	}

	/**
	 * Mastery score shown at a time: the evidence replayed, then the model's idle decay.
	 *
	 * @param array[] $rows   Evidence rows up to that time, oldest first.
	 * @param int     $at     Unix time.
	 * @param array   $config Score model configuration.
	 * @return float
	 */
	private static function score_at( array $rows, $at, array $config ) {
		if ( ! $rows ) {
			return 0.0;
		}
		$run  = ScoreModel::run( $rows, $config );
		$last = end( $rows );
		return (float) ScoreModel::effective( $run['score'], self::utc_ts( $last['evidence_at'] ), $at, $config )['score'];
	}

	/**
	 * Class work due in a window and not completed, per learner.
	 *
	 * @param Scope  $scope Scope.
	 * @param string $from  Earliest due date (UTC).
	 * @param string $to    Latest due date, normally now (UTC).
	 * @return array<int,int> Learner => overdue items.
	 */
	public static function overdue( Scope $scope, $from, $to ) {
		$args  = array( Schema::table( 'assignment_recipients' ), Schema::table( 'learning_assignments' ), Schema::table( 'classes' ), $from, $to );
		$where = '';
		if ( Scope::CLASSROOM === $scope->type() ) {
			$where  = ' AND a.class_id = %d';
			$args[] = $scope->id();
		} elseif ( Scope::SCHOOL === $scope->type() ) {
			$where  = ' AND a.school_id = %d';
			$args[] = $scope->id();
		}
		$learner = $scope->learner_condition( 'r.student_user_id', $args );
		$counts  = array();
		foreach ( self::rows(
			"SELECT r.student_user_id AS user_id, COUNT(*) AS n FROM %i r
			INNER JOIN %i a ON a.id = r.assignment_id
			INNER JOIN %i c ON c.id = a.class_id AND c.status = 'active'
			WHERE a.status = 'active' AND r.completed_at IS NULL AND a.due_at IS NOT NULL AND a.due_at >= %s AND a.due_at < %s$where AND $learner
			GROUP BY r.student_user_id",
			$args
		) as $row ) {
			$counts[ (int) $row['user_id'] ] = (int) $row['n'];
		}
		return $counts;
	}

	/**
	 * Display names of users, with one query.
	 *
	 * @param int[] $ids User ids.
	 * @return array<int,string>
	 */
	public static function names( array $ids ) {
		global $wpdb;
		if ( ! $ids ) {
			return array();
		}
		$args  = array( $wpdb->users );
		$in    = Scope::in_condition( 'ID', array_map( 'intval', $ids ), $args );
		$names = array();
		foreach ( self::rows( "SELECT ID, display_name FROM %i WHERE $in", $args ) as $row ) {
			$names[ (int) $row['ID'] ] = html_entity_decode( wp_strip_all_tags( (string) $row['display_name'] ), ENT_QUOTES, 'UTF-8' );
		}
		return $names;
	}
}
