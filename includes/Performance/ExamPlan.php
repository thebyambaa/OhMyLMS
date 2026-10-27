<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Exam mode: a day-by-day plan toward a target syllabus by an exam date. Pure arithmetic.
 *
 * @package OhMyLMS\Performance
 */

namespace OhMyLMS\Performance;

defined( 'ABSPATH' ) || exit;

/**
 * A learner preparing for an exam (a target syllabus such as Digital SAT, with a date) gets a plan:
 *
 *  - Learning phase: the target's skills not yet proficient, ordered by how much each lifts readiness
 *    (objectives mapped to one shared skill add up), skills whose prerequisites are met first, then by
 *    grade. They are spread over the days until the review window, at most three a day (one sitting).
 *  - Review phase: in the last REVIEW_DAYS days nothing new is started; each day reviews the target
 *    skills already worked on, least recently practised first.
 *
 * Nothing here reads WordPress, so every rule is tested on its own (tests/php/performance-unit.php).
 */
final class ExamPlan {

	/** The last days before the exam are for review only. */
	const REVIEW_DAYS = 7;

	/** Skills per day: one sitting of about fifteen minutes. */
	const PER_DAY = 3;

	/** Days of the schedule returned. */
	const SHOWN_DAYS = 7;

	/** How far ahead an exam date may be set, in days. */
	const MAX_DAYS = 1095;

	/**
	 * Whole days from today to the exam date (0 on the day itself, negative after it).
	 *
	 * @param string $date Exam date, Y-m-d.
	 * @param int    $now  Timestamp.
	 * @return int|null Null for an unreadable date.
	 */
	public static function days_left( $date, $now ) {
		if ( ! is_string( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return null;
		}
		$exam = strtotime( $date . ' 00:00:00 UTC' );
		if ( false === $exam || gmdate( 'Y-m-d', $exam ) !== $date ) {
			return null;
		}
		return (int) floor( ( $exam - strtotime( gmdate( 'Y-m-d', (int) $now ) . ' 00:00:00 UTC' ) ) / DAY_IN_SECONDS );
	}

	/**
	 * The target's skills still to learn, most useful first.
	 *
	 * @param int[]            $objectives Target syllabus objectives.
	 * @param array<int,array> $pooled     Pooled skills (GuidanceModel::pooled).
	 * @param array<int,int>   $pool       Objective => shared skill it is equivalent to.
	 * @param array<int,array> $info       Skill => prerequisites, grade_from.
	 * @return array[] Each: skill, level, lift (share of readiness gained when proficient, 0-1), ready (prerequisites met).
	 */
	public static function gaps( array $objectives, array $pooled, array $pool, array $info ) {
		$objectives = array_values( array_unique( array_map( 'intval', $objectives ) ) );
		$total      = count( $objectives );
		$items      = array();
		foreach ( $objectives as $term ) {
			$skill = (int) ( $pool[ $term ] ?? $term );
			$level = (string) ( $pooled[ $skill ]['level'] ?? 'not-started' );
			if ( Levels::is_proficient( $level ) ) {
				continue;
			}
			if ( ! isset( $items[ $skill ] ) ) {
				$met = true;
				foreach ( (array) ( $info[ $skill ]['prerequisites'] ?? array() ) as $prerequisite ) {
					if ( ! isset( $pooled[ $prerequisite ] ) || ! Levels::is_proficient( $pooled[ $prerequisite ]['level'] ) ) {
						$met = false;
						break;
					}
				}
				$items[ $skill ] = array(
					'skill' => $skill,
					'level' => $level,
					'lift'  => 0.0,
					'ready' => $met,
					'grade' => (int) ( $info[ $skill ]['grade_from'] ?? 0 ),
				);
			}
			$items[ $skill ]['lift'] += ( 1.0 - ( GuidanceModel::READY_POINTS[ $level ] ?? 0.0 ) ) / max( 1, $total );
		}
		$items = array_values( $items );
		usort(
			$items,
			static function ( $left, $right ) {
				return array( $right['ready'], $right['lift'], $left['grade'] ? $left['grade'] : 99, $left['skill'] ) <=> array( $left['ready'], $left['lift'], $right['grade'] ? $right['grade'] : 99, $right['skill'] );
			}
		);
		foreach ( $items as &$item ) {
			$item['lift'] = round( $item['lift'], 4 );
			unset( $item['grade'] );
		}
		return $items;
	}

	/**
	 * The plan from today to the exam.
	 *
	 * @param array[]          $gaps      From gaps().
	 * @param int[]            $worked    Target skills already started or known, for review.
	 * @param array<int,array> $pooled    Pooled skills, for when each was last practised.
	 * @param int              $days_left Days until the exam.
	 * @param int              $now       Timestamp.
	 * @return array phase (learn, review or over), days_left, learning_days, per_day, remaining, on_track, today (skill IDs), schedule (date, skills, phase).
	 */
	public static function plan( array $gaps, array $worked, array $pooled, $days_left, $now ) {
		$days_left = (int) $days_left;
		$result    = array(
			'phase'         => 'over',
			'days_left'     => $days_left,
			'learning_days' => 0,
			'per_day'       => 0,
			'remaining'     => count( $gaps ),
			'on_track'      => true,
			'today'         => array(),
			'schedule'      => array(),
		);
		if ( $days_left < 0 ) {
			return $result;
		}
		$learning                = max( 0, $days_left - self::REVIEW_DAYS );
		$result['learning_days'] = $learning;
		$result['phase']         = $learning > 0 ? 'learn' : 'review';
		$result['per_day']       = $learning > 0 && $gaps ? min( self::PER_DAY, (int) ceil( count( $gaps ) / $learning ) ) : 0;
		$result['on_track']      = count( $gaps ) <= $learning * self::PER_DAY;
		// Least recently practised first, for review days.
		$reviews = array_values( array_unique( array_map( 'intval', $worked ) ) );
		usort(
			$reviews,
			static function ( $left, $right ) use ( $pooled ) {
				return array( (int) ( $pooled[ $left ]['last'] ?? 0 ), $left ) <=> array( (int) ( $pooled[ $right ]['last'] ?? 0 ), $right );
			}
		);
		$queue  = array_column( $gaps, 'skill' );
		$review = 0;
		$today  = strtotime( gmdate( 'Y-m-d', (int) $now ) . ' 00:00:00 UTC' );
		for ( $day = 0; $day <= $days_left && $day < self::SHOWN_DAYS; $day++ ) {
			$in_review = $days_left - $day < self::REVIEW_DAYS || ! $queue;
			if ( $in_review ) {
				// Rotate through the worked skills, a few a day, so each comes round again.
				$skills = array();
				for ( $i = 0, $n = min( self::PER_DAY, count( $reviews ) ); $i < $n; $i++ ) {
					$skills[] = $reviews[ $review % count( $reviews ) ];
					++$review;
				}
			} else {
				$skills = array_splice( $queue, 0, max( 1, $result['per_day'] ) );
			}
			$result['schedule'][] = array(
				'date'   => gmdate( 'Y-m-d', $today + $day * DAY_IN_SECONDS ),
				'phase'  => $in_review ? 'review' : 'learn',
				'skills' => array_values( array_unique( $skills ) ),
			);
		}
		$result['today'] = $result['schedule'] ? $result['schedule'][0]['skills'] : array();
		return $result;
	}
}
