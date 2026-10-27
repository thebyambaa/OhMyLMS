<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * The one scale a learner's skill level is told on, and the rules that move along it.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Performance;

defined( 'ABSPATH' ) || exit;

/**
 * Every part of the plugin names a skill level with these five words, whichever policy decided it
 * (see Policy). The step rules below are pure, so they are tested without WordPress.
 */
final class Levels {

	/** Lowest to highest. */
	const SCALE = array( 'not-started', 'attempted', 'familiar', 'proficient', 'mastered' );

	/**
	 * Learner-facing name for a level.
	 *
	 * @param string $level Level.
	 * @return string
	 */
	public static function label( $level ) {
		return array(
			'not-started' => __( 'Not started', 'ohmylms' ),
			'attempted'   => __( 'Attempted', 'ohmylms' ),
			'familiar'    => __( 'Familiar', 'ohmylms' ),
			'proficient'  => __( 'Proficient', 'ohmylms' ),
			'mastered'    => __( 'Mastered', 'ohmylms' ),
		)[ $level ] ?? $level;
	}

	/**
	 * Position on the scale, so levels can be compared.
	 *
	 * @param string $level Level.
	 * @return int
	 */
	public static function rank( $level ) {
		$rank = array_search( $level, self::SCALE, true );
		return false === $rank ? 0 : $rank;
	}

	/**
	 * Whether a level is Proficient or better.
	 *
	 * @param string $level Level.
	 * @return bool
	 */
	public static function is_proficient( $level ) {
		return self::rank( $level ) >= self::rank( 'proficient' );
	}

	/**
	 * Whether a skill has been started but is not Proficient yet.
	 *
	 * @param string $level Level.
	 * @return bool
	 */
	public static function is_developing( $level ) {
		return in_array( $level, array( 'attempted', 'familiar' ), true );
	}

	/**
	 * Level after an exercise: below 70% right is Attempted, 70-99% Familiar, 100% Proficient. An
	 * exercise raises a skill to that level but never lowers it, except that an imperfect exercise
	 * takes Mastered back to Proficient.
	 *
	 * @param string $current Level before.
	 * @param int    $right   Questions right first time without help.
	 * @param int    $total   Questions in the exercise.
	 * @return string
	 */
	public static function exercise( $current, $right, $total ) {
		if ( $total < 1 ) {
			return $current;
		}
		$share  = $right / $total;
		$result = $share >= 1 ? 'proficient' : ( $share >= 0.7 ? 'familiar' : 'attempted' );
		if ( self::rank( $result ) > self::rank( $current ) ) {
			return $result;
		}
		return 'mastered' === $current && $share < 1 ? 'proficient' : $current;
	}

	/**
	 * Level after an assessment's questions on one skill: all right moves up one step (never below
	 * Familiar), any miss moves down one (never below Attempted).
	 *
	 * @param string $current   Level before.
	 * @param bool   $all_right Every question on the skill was right.
	 * @return string
	 */
	public static function assessment( $current, $all_right ) {
		$rank = self::rank( $current );
		if ( $all_right ) {
			return self::SCALE[ min( 4, max( 2, $rank + 1 ) ) ];
		}
		return self::SCALE[ max( 1, $rank - 1 ) ];
	}

	/**
	 * Level for a 0-100 mastery score: below the second band Attempted, the second band Familiar,
	 * the third and fourth Proficient, a full score Mastered, and nothing scored Not started.
	 *
	 * @param float $score  Mastery score.
	 * @param int   $scored Answers behind the score.
	 * @param array $bands  Score bands, each with a "from" start; four expected.
	 * @return string
	 */
	public static function from_score( $score, $scored, array $bands ) {
		$score = (float) $score;
		if ( (int) $scored < 1 && $score <= 0 ) {
			return 'not-started';
		}
		if ( $score >= 100 - 1e-9 ) {
			return 'mastered';
		}
		$familiar   = isset( $bands[1]['from'] ) ? (float) $bands[1]['from'] : 70.0;
		$proficient = isset( $bands[2]['from'] ) ? (float) $bands[2]['from'] : 80.0;
		if ( $score >= $proficient - 1e-9 ) {
			return 'proficient';
		}
		return $score >= $familiar - 1e-9 ? 'familiar' : 'attempted';
	}
}
