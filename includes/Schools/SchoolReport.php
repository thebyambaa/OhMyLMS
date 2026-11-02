<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Per-school summary report and its CSV export.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Schools;

use OhMyLMS\Dashboard\Enrolments;
use OhMyLMS\Dashboard\MasteryRepository;
use OhMyLMS\Dashboard\Period;
use OhMyLMS\Dashboard\Repository;
use OhMyLMS\Dashboard\Scope;

defined( 'ABSPATH' ) || exit;

/**
 * Figures come from the dashboard read models over `Dashboard\Scope` (a school scope for the summary, a
 * class scope per class), so the report counts exactly what the Dashboard counts for the same school:
 * learners are active student members, activity is an enrolment, progress row, quiz attempt or assignment
 * submission in the period, completion is enrolled learners per course who completed it. Callers check
 * that the user may see the school. Only aggregates and class names leave this class.
 */
final class SchoolReport {

	/** Most classes listed. */
	const MAX_CLASSES = 50;

	/** Most courses counted per scope. */
	const MAX_COURSES = 200;

	/** Most skills in the mastery overview. */
	const MAX_SKILLS = 8;

	/** Mastery percentage below which a learner counts as struggling on a skill. */
	const BELOW = 50;

	/**
	 * Build the report.
	 *
	 * @param array  $school School row.
	 * @param Period $period Period for active learners.
	 * @return array
	 */
	public static function build( array $school, Period $period ) {
		global $wpdb;
		$scope   = Scope::school( (int) $school['id'], (string) $school['name'] );
		$range   = $period->range();
		$classes = Schema::table( 'classes' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bounded list of one school's active classes.
		$active = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id,name,grade FROM %i WHERE school_id=%d AND status='active' ORDER BY name, id LIMIT %d", $classes, $school['id'], self::MAX_CLASSES ), ARRAY_A );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Counts by status.
		$counts = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS n FROM %i WHERE school_id=%d GROUP BY status', $classes, $school['id'] ), ARRAY_A );
		$by     = array();
		foreach ( $counts as $row ) {
			$by[ (string) $row['status'] ] = (int) $row['n'];
		}
		$work = self::assignments( (int) $school['id'] );
		$rows = array();
		foreach ( $active as $class ) {
			$class_scope = Scope::classroom( (int) $class['id'], (string) $class['name'], (int) $school['id'], (string) $school['name'] );
			$figures     = self::figures( $class_scope, $range->start_sql(), $range->end_sql() );
			$rows[]      = array_merge(
				array(
					'id'    => (int) $class['id'],
					'name'  => (string) $class['name'],
					'grade' => (string) $class['grade'],
				),
				$figures,
				array(
					'teachers'           => self::teachers( (int) $class['id'] ),
					'assigned'           => $work[ (int) $class['id'] ]['assigned'] ?? 0,
					'assigned_completed' => $work[ (int) $class['id'] ]['completed'] ?? 0,
				)
			);
		}
		$summary = self::figures( $scope, $range->start_sql(), $range->end_sql() );

		return array(
			'school'       => array(
				'id'     => (int) $school['id'],
				'name'   => (string) $school['name'],
				'status' => (string) $school['status'],
			),
			'period'       => array(
				'days'  => $period->days(),
				'start' => substr( (string) $range->start, 0, 10 ),
				'end'   => substr( (string) $range->end, 0, 10 ),
			),
			'summary'      => array_merge(
				$summary,
				array(
					'classes'            => $by['active'] ?? 0,
					'archived_classes'   => $by['archived'] ?? 0,
					'assigned'           => array_sum( array_column( $work, 'assigned' ) ),
					'assigned_completed' => array_sum( array_column( $work, 'completed' ) ),
				)
			),
			'classes'      => $rows,
			'more_classes' => max( 0, ( $by['active'] ?? 0 ) - count( $rows ) ),
			'mastery'      => self::mastery( (array) $scope->learner_ids() ),
			'generated_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
		);
	}

	/**
	 * Learners, active learners and course completion of a scope.
	 *
	 * @param Scope  $scope Scope.
	 * @param string $start Period start (site time).
	 * @param string $end   Period end (site time).
	 * @return array{learners:int,active_learners:int,enrolled:int,completed:int,completion:int|null}
	 */
	private static function figures( Scope $scope, $start, $end ) {
		$learners = count( (array) $scope->learner_ids() );
		$active   = 0;
		$enrolled = 0;
		$done     = 0;
		if ( $learners ) {
			$activity = Repository::active_learners( $scope, $start, $end, array( array( $start, $end ) ) );
			$active   = (int) ( $activity['weeks'][0] ?? 0 );
			foreach ( Enrolments::courses_by_enrolment( $scope, self::MAX_COURSES )['courses'] as $course ) {
				$enrolled += $course['enrolled'];
				$done     += $course['completed'];
			}
		}

		return array(
			'learners'        => $learners,
			'active_learners' => $active,
			'enrolled'        => $enrolled,
			'completed'       => $done,
			'completion'      => $enrolled ? (int) round( 100 * $done / $enrolled ) : null,
		);
	}

	/**
	 * Active teachers of a class.
	 *
	 * @param int $class_id Class.
	 * @return int
	 */
	private static function teachers( $class_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One count.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE class_id=%d AND role='teacher' AND status='active'", Schema::table( 'class_memberships' ), $class_id ) );
	}

	/**
	 * Distributed work per class: recipients and completed recipients of active assignments.
	 *
	 * @param int $school_id School.
	 * @return array<int,array{assigned:int,completed:int}>
	 */
	private static function assignments( $school_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One grouped query.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT a.class_id, COUNT(r.id) AS assigned, SUM(CASE WHEN r.completed_at IS NOT NULL THEN 1 ELSE 0 END) AS completed FROM %i a JOIN %i r ON r.assignment_id=a.id WHERE a.school_id=%d AND a.status='active' GROUP BY a.class_id", Schema::table( 'learning_assignments' ), Schema::table( 'assignment_recipients' ), $school_id ), ARRAY_A );
		$work = array();
		foreach ( $rows as $row ) {
			$work[ (int) $row['class_id'] ] = array(
				'assigned'  => (int) $row['assigned'],
				'completed' => (int) $row['completed'],
			);
		}

		return $work;
	}

	/**
	 * Mastery overview: the skills most learners have evidence on, with the average mastery.
	 *
	 * @param int[] $learners Learners.
	 * @return array{skills:array[],average:int|null,assessed:int}
	 */
	private static function mastery( array $learners ) {
		$skills  = MasteryRepository::top_skills( $learners, self::MAX_SKILLS );
		$figures = MasteryRepository::skill_figures( $learners, array_map( 'intval', array_column( $skills, 'id' ) ), self::BELOW );
		$list    = array();
		$sum     = 0.0;
		$count   = 0;
		foreach ( $skills as $skill ) {
			$figure = $figures[ (int) $skill['id'] ] ?? null;
			if ( ! $figure || ! $figure['assessed'] ) {
				continue;
			}
			$sum   += $figure['sum'];
			$count += $figure['assessed'];
			$list[] = array(
				'id'       => (int) $skill['id'],
				'name'     => (string) $skill['name'],
				'code'     => (string) $skill['code'],
				'assessed' => (int) $figure['assessed'],
				'average'  => (int) round( $figure['sum'] / $figure['assessed'] ),
				'below'    => (int) $figure['below'],
			);
		}

		return array(
			'skills'   => $list,
			'average'  => $count ? (int) round( $sum / $count ) : null,
			'assessed' => $count,
		);
	}

	/**
	 * Spreadsheet-safe CSV cell: text that a spreadsheet would run as a formula is prefixed with `'`.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function cell( $value ) {
		$text = null === $value ? '' : (string) $value;
		if ( preg_match( '/^[\s]*[=+\-@\t\r]/', $text ) ) {
			$text = "'" . $text;
		}

		return '"' . str_replace( '"', '""', $text ) . '"';
	}

	/**
	 * The report as CSV (UTF-8, CRLF): one row per class and a total, then the mastery overview.
	 *
	 * @param array $report Report from {@see self::build()}.
	 * @return string
	 */
	public static function csv( array $report ) {
		$days  = (int) $report['period']['days'];
		$lines = array(
			array(
				__( 'Class', 'ohmylms' ),
				__( 'Grade', 'ohmylms' ),
				__( 'Learners', 'ohmylms' ),
				/* translators: %d: number of days. */
				sprintf( __( 'Active learners (last %d days)', 'ohmylms' ), $days ),
				__( 'Course enrolments', 'ohmylms' ),
				__( 'Completed', 'ohmylms' ),
				__( 'Completion %', 'ohmylms' ),
				__( 'Teachers', 'ohmylms' ),
				__( 'Work assigned', 'ohmylms' ),
				__( 'Work completed', 'ohmylms' ),
			),
		);
		foreach ( $report['classes'] as $row ) {
			$lines[] = array( $row['name'], $row['grade'], $row['learners'], $row['active_learners'], $row['enrolled'], $row['completed'], $row['completion'], $row['teachers'], $row['assigned'], $row['assigned_completed'] );
		}
		$total   = $report['summary'];
		$lines[] = array( __( 'Whole school', 'ohmylms' ), '', $total['learners'], $total['active_learners'], $total['enrolled'], $total['completed'], $total['completion'], '', $total['assigned'], $total['assigned_completed'] );
		if ( $report['mastery']['skills'] ) {
			$lines[] = array();
			$lines[] = array( __( 'Skill', 'ohmylms' ), __( 'Code', 'ohmylms' ), __( 'Learners assessed', 'ohmylms' ), __( 'Average mastery %', 'ohmylms' ), __( 'Below 50%', 'ohmylms' ) );
			foreach ( $report['mastery']['skills'] as $skill ) {
				$lines[] = array( $skill['name'], $skill['code'], $skill['assessed'], $skill['average'], $skill['below'] );
			}
		}

		return implode(
			"\r\n",
			array_map(
				static function ( $line ) {
					return implode( ',', array_map( array( self::class, 'cell' ), $line ) );
				},
				$lines
			)
		) . "\r\n";
	}

	/**
	 * File name of the export.
	 *
	 * @param array $report Report.
	 * @return string
	 */
	public static function filename( array $report ) {
		$slug = sanitize_title( $report['school']['name'] );

		return 'school-report-' . ( '' !== $slug ? $slug : (int) $report['school']['id'] ) . '-' . $report['period']['end'] . '.csv';
	}
}
