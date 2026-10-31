<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * "Insights": three to five plain sentences computed by fixed rules from the dashboard's own figures.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard\Widgets;

use OhMyLMS\Analytics\Repository as Money;
use OhMyLMS\Dashboard\Access;
use OhMyLMS\Dashboard\ClassSkillGaps;
use OhMyLMS\Dashboard\CourseHealthReport;
use OhMyLMS\Dashboard\Enrolments;
use OhMyLMS\Dashboard\InsightRules;
use OhMyLMS\Dashboard\Period;
use OhMyLMS\Dashboard\Repository;
use OhMyLMS\Dashboard\Scope;
use OhMyLMS\Dashboard\Widget;

defined( 'ABSPATH' ) || exit;

/**
 * `data: { insights[] }`, most important first, each `{ key, rule, severity, text, action }`
 * (docs/api/dashboard.md, "Insights"). Rules live in {@see InsightRules}; this class only reads the
 * figures for the scope and period. No AI or external calls. Revenue is read only for the `all` scope
 * (site administrators) and is removed again in {@see Insights::present()} for anyone else.
 */
final class Insights extends Widget {

	/**
	 * Widget id.
	 *
	 * @return string
	 */
	public function id() {
		return 'insights';
	}

	/**
	 * Insights of a scope and period.
	 *
	 * @param Scope  $scope  Scope.
	 * @param Period $period Period.
	 * @return array
	 */
	public function compute( Scope $scope, Period $period ) {
		$rules    = InsightRules::thresholds();
		$range    = $period->range();
		$previous = $period->previous();
		$found    = array();

		if ( $scope->includes_commerce() ) {
			$money   = new Money();
			$now     = $money->summary( $range->start_sql(), $range->end_sql() );
			$before  = $money->summary( $previous->start_sql(), $previous->end_sql() );
			$top     = $money->courses( $range, array( 'active_only' => false ), 'net', 'desc', 1, 0 )['items'][0] ?? null;
			$found[] = InsightRules::revenue( $now['net'], $before['net'], $period->days(), $money->currency(), $top, $rules['revenue'] );
		}

		$health  = CourseHealthReport::build( $scope );
		$found[] = InsightRules::drop_off( $health['courses'], $rules['drop_off'] );

		$found[] = InsightRules::class_gap( ClassSkillGaps::largest( $scope, $rules['class_gap'] ), $rules['class_gap'] );

		$missed  = Repository::missed_questions( $scope, $range->start_sql(), $range->end_sql(), max( 1, $rules['missed_question']['min_learners'] ), $rules['missed_question']['min_rate'], 1 );
		$found[] = InsightRules::missed_question( $missed[0] ?? null, $rules['missed_question'] );

		$lessons = array();
		foreach ( $health['courses'] as $course ) {
			if ( $course['lessons'] > 0 ) {
				$lessons[ $course['id'] ] = $course['lessons'];
			}
		}
		$found[] = InsightRules::quiz_lessons( Enrolments::lessons_by_quiz_taking( $scope, array_keys( $lessons ), Repository::FINISHED_ATTEMPT ), $lessons, $rules['quiz_lessons'] );

		$completed = Enrolments::completions_per_day( $scope, $previous->start_sql(), $range->end_sql() );
		$found[]   = InsightRules::completions( self::sum( $completed, $range->start, $range->end ), self::sum( $completed, $previous->start, $previous->end ), $period->days(), $rules['completions'] );

		return array( 'insights' => InsightRules::select( $found, $rules['limit'] ) );
	}

	/**
	 * Per user: drop links the user cannot open, and revenue for anyone but site administrators.
	 *
	 * @param array  $data    Cached data.
	 * @param Scope  $scope   Scope.
	 * @param Period $period  Period.
	 * @param int    $user_id User.
	 * @return array
	 */
	public function present( array $data, Scope $scope, Period $period, $user_id ) {
		$admin    = Access::is_site_admin( (int) $user_id );
		$insights = array();
		foreach ( (array) ( $data['insights'] ?? array() ) as $insight ) {
			if ( 'revenue' === $insight['rule'] && ( ! $admin || ! $scope->includes_commerce() ) ) {
				continue;
			}
			$action            = $insight['action'];
			$insight['action'] = is_array( $action ) && user_can( (int) $user_id, (string) $action['capability'] ) ? array(
				'label' => (string) $action['label'],
				'route' => (string) $action['route'],
			) : null;
			$insights[]        = $insight;
		}
		return array( 'insights' => $insights );
	}

	/**
	 * Sum of the values dated between two days (inclusive).
	 *
	 * @param array<string,int> $values Values by `Y-m-d`.
	 * @param string            $start  First day.
	 * @param string            $end    Last day.
	 * @return int
	 */
	private static function sum( array $values, $start, $end ) {
		$sum = 0;
		foreach ( $values as $date => $value ) {
			if ( $date >= $start && $date <= $end ) {
				$sum += (int) $value;
			}
		}
		return $sum;
	}
}
