<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Mastery levels of the dashboard heatmap: the five level names told by a percentage.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard\Widgets;

use OhMyLMS\Performance\Levels;

defined( 'ABSPATH' ) || exit;

/**
 * The heatmap and the review-set recommendations read a learner's 0-100 mastery score on a skill
 * (`student_skill_state.mastery`, kept by `Performance\Engine` whatever the site's level policy) and name
 * it with the five words of `Performance\Levels`: Mastered from 95, Proficient from 80, Familiar from 50,
 * Attempted below that once there is evidence, Not started without evidence. Percentages are rounded to
 * whole numbers before they are compared. Pure apart from the threshold filter.
 */
final class MasteryLevels {

	/**
	 * Default lower bounds, in percent.
	 */
	const DEFAULTS = array(
		'mastered'   => 95,
		'proficient' => 80,
		'familiar'   => 50,
	);

	/**
	 * Thresholds with the site's changes; each at least 1 and below the one above it, else the defaults.
	 *
	 * @return array{mastered:int,proficient:int,familiar:int}
	 */
	public static function thresholds() {
		/**
		 * Filters the percentages at which the dashboard calls a skill Mastered, Proficient and Familiar.
		 *
		 * @param array $thresholds `mastered` (95), `proficient` (80) and `familiar` (50), 1-100, descending.
		 */
		$filtered = (array) apply_filters( 'ohmylms_dashboard_mastery_thresholds', self::DEFAULTS );
		$values   = array();
		foreach ( self::DEFAULTS as $level => $default ) {
			$values[ $level ] = isset( $filtered[ $level ] ) && is_numeric( $filtered[ $level ] ) ? (int) $filtered[ $level ] : $default;
		}
		$valid = $values['mastered'] <= 100 && $values['mastered'] > $values['proficient'] && $values['proficient'] > $values['familiar'] && $values['familiar'] >= 1;
		return $valid ? $values : self::DEFAULTS;
	}

	/**
	 * Level of a percentage.
	 *
	 * @param int|float $percent    0-100; rounded first.
	 * @param bool      $assessed   Whether there is any evidence behind it.
	 * @param array     $thresholds From {@see MasteryLevels::thresholds()}.
	 * @return string One of Levels::SCALE.
	 */
	public static function level( $percent, $assessed, array $thresholds ) {
		if ( ! $assessed ) {
			return 'not-started';
		}
		$percent = (int) round( (float) $percent );
		foreach ( array( 'mastered', 'proficient', 'familiar' ) as $level ) {
			if ( $percent >= $thresholds[ $level ] ) {
				return $level;
			}
		}
		return 'attempted';
	}

	/**
	 * Whether a percentage is below Familiar (and so counts as struggling) once there is evidence.
	 *
	 * @param int|float $percent    0-100; rounded first.
	 * @param array     $thresholds From {@see MasteryLevels::thresholds()}.
	 * @return bool
	 */
	public static function below_familiar( $percent, array $thresholds ) {
		return (int) round( (float) $percent ) < $thresholds['familiar'];
	}

	/**
	 * The legend: every level, highest first, with its name and lower bound (null when it has none).
	 *
	 * @param array $thresholds From {@see MasteryLevels::thresholds()}.
	 * @return array[] Each: id, label, min.
	 */
	public static function legend( array $thresholds ) {
		$legend = array();
		foreach ( array_reverse( Levels::SCALE ) as $level ) {
			$legend[] = array(
				'id'    => $level,
				'label' => Levels::label( $level ),
				'min'   => $thresholds[ $level ] ?? null,
			);
		}
		return $legend;
	}
}
