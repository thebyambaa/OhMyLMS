<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Pure gradebook rules: cell states, weighted totals, weights validation and letters.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Gradebook;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Rules of docs/api/gradebook.md, "Math". No database access.
 *
 * Cell states: `score` (graded or overridden), `pending` (submitted, waiting for grading), `missing`
 * (assigned, due date passed, nothing submitted), `none` (assigned, nothing submitted, not due yet or no due
 * date), `not_assigned` (the learner is outside the item's audience) and `excused` (manual).
 */
final class GradeMath {

	const SCORE        = 'score';
	const PENDING      = 'pending';
	const MISSING      = 'missing';
	const NONE         = 'none';
	const NOT_ASSIGNED = 'not_assigned';
	const EXCUSED      = 'excused';

	/**
	 * Most categories per course.
	 */
	const MAX_CATEGORIES = 12;

	/**
	 * Percentage of a score, rounded to two decimals; null without a positive maximum.
	 *
	 * @param float|null $score Score.
	 * @param float|null $max   Maximum.
	 * @return float|null
	 */
	public static function percent( $score, $max ) {
		if ( null === $score || null === $max || (float) $max <= 0 ) {
			return null;
		}
		return round( 100 * (float) $score / (float) $max, 2 );
	}

	/**
	 * State of a cell from what the learner did, then the manual override on top.
	 *
	 * @param array $input {
	 *     @type bool        $assigned Whether the learner is in the item's audience.
	 *     @type array|null  $graded   Latest graded attempt: `score`, `max`.
	 *     @type bool        $pending  A submission waits for grading (and none is graded).
	 *     @type string|null $due      Due date (UTC `Y-m-d H:i:s`) or null.
	 *     @type string      $now      Current UTC time `Y-m-d H:i:s`.
	 *     @type float       $max      The item's maximum.
	 *     @type array|null  $override `kind` (`score`|`excused`), `score`, `max`.
	 * }
	 * @return array{state:string,score:float|null,max:float|null,percent:float|null,overridden:bool,computed:array}
	 */
	public static function cell( array $input ) {
		$max      = (float) ( $input['max'] ?? 0 );
		$computed = array(
			'state' => self::NONE,
			'score' => null,
			'max'   => $max > 0 ? $max : null,
		);
		if ( empty( $input['assigned'] ) ) {
			$computed['state'] = self::NOT_ASSIGNED;
		} elseif ( ! empty( $input['graded'] ) ) {
			$computed = array(
				'state' => self::SCORE,
				'score' => max( 0.0, (float) $input['graded']['score'] ),
				'max'   => (float) $input['graded']['max'] > 0 ? (float) $input['graded']['max'] : ( $max > 0 ? $max : null ),
			);
		} elseif ( ! empty( $input['pending'] ) ) {
			$computed['state'] = self::PENDING;
		} elseif ( ! empty( $input['due'] ) && (string) $input['due'] < (string) $input['now'] ) {
			$computed['state'] = self::MISSING;
		}
		$computed['percent'] = self::percent( $computed['score'], $computed['max'] );

		$override = $input['override'] ?? null;
		$cell     = $computed + array(
			'overridden' => false,
			'computed'   => $computed,
		);
		if ( $override && self::NOT_ASSIGNED !== $computed['state'] ) {
			if ( self::EXCUSED === $override['kind'] ) {
				$cell['state']      = self::EXCUSED;
				$cell['score']      = null;
				$cell['percent']    = null;
				$cell['overridden'] = true;
			} elseif ( self::SCORE === $override['kind'] ) {
				$cell['state']      = self::SCORE;
				$cell['score']      = (float) $override['score'];
				$cell['max']        = (float) $override['max'] > 0 ? (float) $override['max'] : $computed['max'];
				$cell['percent']    = self::percent( $cell['score'], $cell['max'] );
				$cell['overridden'] = true;
			}
		}
		return $cell;
	}

	/**
	 * What a cell adds to a total: score and maximum, or null when it does not count.
	 *
	 * Scores count; a missing cell counts as zero out of the item's maximum when `$missing_as_zero`;
	 * pending, not submitted, not assigned and excused cells never count.
	 *
	 * @param array $cell            Cell (`state`, `score`, `max`).
	 * @param bool  $missing_as_zero Missing work counts as zero.
	 * @return array{0:float,1:float}|null
	 */
	public static function counted( array $cell, $missing_as_zero ) {
		$max = isset( $cell['max'] ) ? (float) $cell['max'] : 0.0;
		if ( $max <= 0 ) {
			return null;
		}
		if ( self::SCORE === $cell['state'] && null !== $cell['score'] ) {
			return array( (float) $cell['score'], $max );
		}
		if ( self::MISSING === $cell['state'] && $missing_as_zero ) {
			return array( 0.0, $max );
		}
		return null;
	}

	/**
	 * Course total of one learner.
	 *
	 * Without weights (points mode) it is the sum of counted scores over the sum of their maximums. With
	 * weights, each category's percentage is its counted points over its counted maximum; the total is the
	 * weighted mean of the categories that have counted work and a weight above zero (weights of empty
	 * categories are left out, so the rest scale up). Items whose category has no weight do not count.
	 *
	 * @param array      $cells           Cells, each with `category`, `state`, `score`, `max`.
	 * @param array|null $weights         Category => weight (sum 100), or null/empty for points mode.
	 * @param bool       $missing_as_zero Missing work counts as zero.
	 * @return array{percent:float|null,mode:string,counted:int,categories:array}
	 */
	public static function total( array $cells, $weights, $missing_as_zero ) {
		$weights    = is_array( $weights ) ? $weights : array();
		$mode       = $weights ? 'weighted' : 'points';
		$categories = array();
		$counted    = 0;
		$score      = 0.0;
		$max        = 0.0;
		foreach ( $cells as $cell ) {
			$part = self::counted( $cell, $missing_as_zero );
			if ( null === $part ) {
				continue;
			}
			$category = (string) ( $cell['category'] ?? '' );
			if ( 'weighted' === $mode && empty( $weights[ $category ] ) ) {
				continue;
			}
			++$counted;
			$score += $part[0];
			$max   += $part[1];
			if ( ! isset( $categories[ $category ] ) ) {
				$categories[ $category ] = array(
					'score' => 0.0,
					'max'   => 0.0,
				);
			}
			$categories[ $category ]['score'] += $part[0];
			$categories[ $category ]['max']   += $part[1];
		}
		$summary = array();
		foreach ( $categories as $category => $figures ) {
			$summary[ $category ] = array(
				'percent' => self::percent( $figures['score'], $figures['max'] ),
				'weight'  => 'weighted' === $mode ? (float) $weights[ $category ] : null,
			);
		}
		if ( 'points' === $mode ) {
			return array(
				'percent'    => self::percent( $score, $max ),
				'mode'       => $mode,
				'counted'    => $counted,
				'categories' => $summary,
			);
		}
		$sum   = 0.0;
		$total = 0.0;
		foreach ( $summary as $figures ) {
			if ( null === $figures['percent'] ) {
				continue;
			}
			$sum   += $figures['weight'];
			$total += $figures['weight'] * $figures['percent'];
		}
		return array(
			'percent'    => $sum > 0 ? round( $total / $sum, 2 ) : null,
			'mode'       => $mode,
			'counted'    => $counted,
			'categories' => $summary,
		);
	}

	/**
	 * Validate category weights: up to 12 categories, keys of lowercase letters, digits, `-` and `_`
	 * (at most 40), each weight 0–100 with at most two decimals, summing to 100. An empty object is valid
	 * (no weighting).
	 *
	 * @param mixed $weights Candidate.
	 * @return array<string,float>|WP_Error Normalised weights.
	 */
	public static function validate_weights( $weights ) {
		if ( ! is_array( $weights ) || ( $weights && array_values( $weights ) === $weights ) ) {
			return self::invalid( __( 'Send the weights as an object of category => percentage.', 'ohmylms' ) );
		}
		if ( count( $weights ) > self::MAX_CATEGORIES ) {
			/* translators: %d: most categories. */
			return self::invalid( sprintf( __( 'Use at most %d categories.', 'ohmylms' ), self::MAX_CATEGORIES ) );
		}
		$clean = array();
		$sum   = 0.0;
		foreach ( $weights as $key => $value ) {
			$key = (string) $key;
			if ( ! preg_match( '/^[a-z0-9][a-z0-9_-]{0,39}$/', $key ) ) {
				return self::invalid( __( 'Category names use lowercase letters, digits, hyphens and underscores (at most 40).', 'ohmylms' ) );
			}
			if ( ! is_int( $value ) && ! is_float( $value ) && ! ( is_string( $value ) && is_numeric( $value ) ) ) {
				return self::invalid( __( 'Each weight must be a number.', 'ohmylms' ) );
			}
			$number = (float) $value;
			if ( ! is_finite( $number ) || $number < 0 || $number > 100 || abs( round( $number, 2 ) - $number ) > 1e-9 ) {
				return self::invalid( __( 'Each weight is a percentage from 0 to 100 with at most two decimals.', 'ohmylms' ) );
			}
			$clean[ $key ] = round( $number, 2 );
			$sum          += $clean[ $key ];
		}
		if ( $clean && abs( $sum - 100 ) > 0.001 ) {
			/* translators: %s: the sum of the weights. */
			return self::invalid( sprintf( __( 'The weights add up to %s%%; they must add up to 100%%.', 'ohmylms' ), (string) round( $sum, 2 ) ) );
		}
		return $clean;
	}

	/**
	 * Letter of a percentage on a scale (highest boundary first); null for null.
	 *
	 * @param float|null $percent Percentage.
	 * @param array      $scale   List of `letter`, `min` (descending `min`, the last 0).
	 * @return string|null
	 */
	public static function letter( $percent, array $scale ) {
		if ( null === $percent ) {
			return null;
		}
		foreach ( $scale as $step ) {
			if ( (float) $percent >= (float) $step['min'] ) {
				return (string) $step['letter'];
			}
		}
		return null;
	}

	/**
	 * Mean of the non-null values, two decimals; null for none.
	 *
	 * @param array $values Values.
	 * @return float|null
	 */
	public static function average( array $values ) {
		$values = array_values(
			array_filter(
				$values,
				static function ( $value ) {
					return null !== $value;
				}
			)
		);
		return $values ? round( array_sum( $values ) / count( $values ), 2 ) : null;
	}

	/**
	 * 422 error for invalid weights.
	 *
	 * @param string $message Message.
	 * @return WP_Error
	 */
	private static function invalid( $message ) {
		return new WP_Error(
			'ohmylms_gradebook_invalid_weights',
			$message,
			array(
				'status' => 422,
				'params' => array( 'weights' => $message ),
			)
		);
	}
}
