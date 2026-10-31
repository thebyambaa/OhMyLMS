<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * "Pulse": headline figures of the period against the previous one, with a daily series each.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard\Widgets;

use OhMyLMS\Analytics\Repository as Money;
use OhMyLMS\Dashboard\Enrolments;
use OhMyLMS\Dashboard\Period;
use OhMyLMS\Dashboard\Repository;
use OhMyLMS\Dashboard\Scope;
use OhMyLMS\Dashboard\Widget;

defined( 'ABSPATH' ) || exit;

/**
 * Metrics (docs/api/dashboard.md, "Pulse"), each `{ id, format, value, previous, series }` with one series
 * value per day of the current period (`dates`):
 *
 * - `revenue` (`all` scope only): net earnings (Analytics definitions); series = net per day;
 * - `active_learners`: distinct learners active in the last 7 days of the period, against the last 7 days
 *   of the previous period; series = distinct active learners per day;
 * - `completions`: course completions of the scope's learners in the scope's courses;
 * - `renewals_at_risk` (`all` scope only): failed renewal payments, with `on_hold` subscriptions now.
 */
final class Pulse extends Widget {

	/**
	 * Widget id.
	 *
	 * @return string
	 */
	public function id() {
		return 'pulse';
	}

	/**
	 * Metrics of a scope and period.
	 *
	 * @param Scope  $scope  Scope.
	 * @param Period $period Period.
	 * @return array
	 */
	public function compute( Scope $scope, Period $period ) {
		$range    = $period->range();
		$previous = $period->previous();
		$dates    = $period->day_keys();
		$metrics  = array();
		$currency = null;

		if ( $scope->includes_commerce() ) {
			$money     = new Money();
			$current   = $money->summary( $range->start_sql(), $range->end_sql() );
			$before    = $money->summary( $previous->start_sql(), $previous->end_sql() );
			$currency  = $money->currency();
			$metrics[] = array(
				'id'       => 'revenue',
				'format'   => 'money',
				'value'    => $current['net'],
				'previous' => $before['net'],
				'series'   => array_map(
					static function ( $point ) {
						return $point['net'];
					},
					$money->series( $range )
				),
			);
		}

		$active    = Repository::active_learners(
			$scope,
			$range->start_sql(),
			$range->end_sql(),
			array( Period::last_week( $range ), Period::last_week( $previous ) )
		);
		$metrics[] = array(
			'id'       => 'active_learners',
			'format'   => 'number',
			'value'    => $active['weeks'][0],
			'previous' => $active['weeks'][1],
			'series'   => self::fill( $dates, $active['days'] ),
		);

		$completed = Enrolments::completions_per_day( $scope, $previous->start_sql(), $range->end_sql() );
		$metrics[] = array(
			'id'       => 'completions',
			'format'   => 'number',
			'value'    => self::sum_between( $completed, $range->start, $range->end ),
			'previous' => self::sum_between( $completed, $previous->start, $previous->end ),
			'series'   => self::fill( $dates, $completed ),
		);

		if ( $scope->includes_commerce() ) {
			$failed    = Repository::failed_renewals_per_day( $previous->start_sql(), $range->end_sql() );
			$metrics[] = array(
				'id'       => 'renewals_at_risk',
				'format'   => 'number',
				'value'    => self::sum_between( $failed, $range->start, $range->end ),
				'previous' => self::sum_between( $failed, $previous->start, $previous->end ),
				'series'   => self::fill( $dates, $failed ),
				'on_hold'  => Repository::subscriptions_on_hold(),
			);
		}

		return array(
			'dates'    => $dates,
			'currency' => $currency,
			'metrics'  => $metrics,
		);
	}

	/**
	 * One value per date, 0 where none.
	 *
	 * @param string[]          $dates  `Y-m-d` keys.
	 * @param array<string,int> $values Values by date.
	 * @return int[]
	 */
	private static function fill( array $dates, array $values ) {
		return array_map(
			static function ( $date ) use ( $values ) {
				return (int) ( $values[ $date ] ?? 0 );
			},
			$dates
		);
	}

	/**
	 * Sum of the values dated between two days (inclusive).
	 *
	 * @param array<string,int> $values Values by `Y-m-d`.
	 * @param string            $start  First day.
	 * @param string            $end    Last day.
	 * @return int
	 */
	private static function sum_between( array $values, $start, $end ) {
		$sum = 0;
		foreach ( $values as $date => $value ) {
			if ( $date >= $start && $date <= $end ) {
				$sum += (int) $value;
			}
		}
		return $sum;
	}
}
