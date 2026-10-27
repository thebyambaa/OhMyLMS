<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * The 0-100 skill mastery score: forgiving early, strict near the top.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Skills;

defined( 'ABSPATH' ) || exit;

/**
 * The 0-100 skill mastery score: forgiving early, strict near the top.
 *
 * It is separate from XP (effort) and from grading (right or wrong). Each independent
 * first-try answer to the skill moves the score:
 *
 *  - a correct answer adds the gain of the score's current band, scaled by question difficulty
 *    (0.75 + 0.25 * d / 2 for difficulty d = 1..4, so a standard question counts in full);
 *  - a correct answer after a hint earns half;
 *  - a partly correct answer (a multi-part question) counts as correct from 70% of its marks
 *    and the gain is scaled by the share earned;
 *  - a wrong answer subtracts the band's loss, with or without a hint;
 *  - the score never falls below the start of the band it was in, so one bad day cannot erase
 *    proficiency (the bands start at 0, 70, 80 and 90);
 *  - repeats of the same question are not first tries and do not move it (every new issue of a
 *    randomised template is a first try).
 *
 * A mastered skill that is not practised decays slowly, never below the start of its top band.
 * The model is a pure function of the evidence rows, so a regrade or a changed rule is applied
 * by recomputing from scratch; nothing is stored that cannot be rebuilt.
 */
final class ScoreModel {
	/** Question difficulty names to the 1-4 scale of the formula. */
	const DIFFICULTY = array(
		'easy'      => 1,
		'standard'  => 2,
		'challenge' => 3,
		'hard'      => 3,
		'exam'      => 4,
	);

	const MEDALS = array(
		80  => 'bronze',
		90  => 'silver',
		100 => 'gold',
	);

	/**
	 * Defaults for Score model.
	 */
	public static function defaults() {
		return array(
			// Starting values within the ranges of the math.mn spec; tune them with real data.
			'bands'            => array(
				array(
					'from' => 0,
					'gain' => 9.0,
					'loss' => 1.5,
				),
				array(
					'from' => 70,
					'gain' => 5.0,
					'loss' => 3.5,
				),
				array(
					'from' => 80,
					'gain' => 2.5,
					'loss' => 5.0,
				),
				array(
					'from' => 90,
					'gain' => 1.5,
					'loss' => 6.5,
				),
			),
			'hint_gain'        => 0.5,
			'partial_correct'  => 0.7,
			'unlock'           => 80,
			'decay_grace_days' => 21,
			'decay_per_week'   => 1.0,
			'decay_floor'      => 90,
		);
	}

	/** Defaults with any provided values laid over them.
	 *
	 * @param array $over Over.
	 */
	public static function config( array $over = array() ) {
		$config          = array_merge( self::defaults(), array_diff_key( array_intersect_key( $over, self::defaults() ), array( 'bands' => 1 ) ) );
		$config['bands'] = self::defaults()['bands'];
		if ( isset( $over['bands'] ) && is_array( $over['bands'] ) && count( $over['bands'] ) === 4 && ! array_filter( $over['bands'], 'is_scalar' ) ) {
			$starts = array( 0, 70, 80, 90 );
			foreach ( $over['bands'] as $i => $band ) {
				$config['bands'][ $i ] = array(
					'from' => $starts[ $i ],
					'gain' => (float) ( $band['gain'] ?? $config['bands'][ $i ]['gain'] ),
					'loss' => (float) ( $band['loss'] ?? $config['bands'][ $i ]['loss'] ),
				);
			}
		}
		return $config;
	}

	/** Index of the band a score is in.
	 *
	 * @param mixed $score Score.
	 * @param array $bands Bands.
	 */
	public static function band_index( $score, array $bands ) {
		$index = 0;
		foreach ( $bands as $i => $band ) {
			if ( $score >= $band['from'] - 1e-9 ) {
				$index = $i; }
		}
		return $index;
	}

	/** Name of the band: learning, building, proficient or challenge.
	 *
	 * @param mixed $score Score.
	 * @param array $bands Bands.
	 */
	public static function band_name( $score, array $bands ) {
		return array( 'learning', 'building', 'proficient', 'challenge' )[ self::band_index( $score, $bands ) ];
	}

	/** Highest medal for a score: bronze at 80, silver at 90, gold at 100; '' below 80.
	 *
	 * @param mixed $score Score.
	 */
	public static function medal( $score ) {
		$medal = '';
		foreach ( self::MEDALS as $at => $name ) {
			if ( $score >= $at - 1e-9 ) {
				$medal = $name; }
		}
		return $medal;
	}

	/**
	 * Run the evidence rows, oldest first.
	 *
	 * @param array[] $rows each: awarded, available, independent, first_try, difficulty; optional grade_event_id.
	 * @param array   $config Config.
	 * @param float   $start the score before the first row.
	 * @return array{score:float,floor:int,scored:int,correct:int,peak:float,medal:string,struggling:bool,trace:array}
	 */
	public static function run( array $rows, array $config = null, $start = 0.0 ) {
		$config  = $config ? $config : self::config();
		$bands   = $config['bands'];
		$score   = max( 0.0, min( 100.0, (float) $start ) );
		$peak    = $score;
		$scored  = 0;
		$correct = 0;
		$recent  = array();
		$trace   = array();
		foreach ( $rows as $row ) {
			$available = (float) ( $row['available'] ?? 0 );
			$id        = $row['grade_event_id'] ?? null;
			if ( empty( $row['first_try'] ) || $available <= 0 ) {
				$trace[] = array(
					'grade_event_id' => $id,
					'counted'        => false,
					'before'         => $score,
					'after'          => $score,
					'delta'          => 0.0,
				);
				continue;
			}
			$ratio  = max( 0.0, min( 1.0, (float) $row['awarded'] / $available ) );
			$right  = $ratio >= (float) $config['partial_correct'] - 1e-9;
			$band   = $bands[ self::band_index( $score, $bands ) ];
			$d      = self::DIFFICULTY[ $row['difficulty'] ?? 'standard' ] ?? 2;
			$before = $score;
			if ( $right ) {
				$gain = $band['gain'] * ( 0.75 + 0.25 * $d / 2 );
				if ( $ratio < 1.0 - 1e-9 ) {
					$gain *= $ratio; }
				if ( empty( $row['independent'] ) ) {
					$gain *= (float) $config['hint_gain']; }
				$score = min( 100.0, $score + $gain );
			} else {
				$score = max( (float) $band['from'], $score - $band['loss'] );
			}
			$score    = round( $score, 2 );
			$peak     = max( $peak, $score );
			$scored  += 1;
			$correct += $right ? 1 : 0;
			$recent[] = $right;
			$recent   = array_slice( $recent, -2 );
			$trace[]  = array(
				'grade_event_id' => $id,
				'counted'        => true,
				'right'          => $right,
				'before'         => $before,
				'after'          => $score,
				'delta'          => round( $score - $before, 2 ),
			);
		}
		return array(
			'score'      => $score,
			'floor'      => (int) $bands[ self::band_index( $score, $bands ) ]['from'],
			'scored'     => $scored,
			'correct'    => $correct,
			'peak'       => $peak,
			'medal'      => self::medal( $peak ),
			// Two misses in a row while still learning: suggest the prerequisite skill.
			'struggling' => count( $recent ) === 2 && ! $recent[0] && ! $recent[1] && $score < 70,
			'trace'      => $trace,
		);
	}

	/**
	 * The score as shown today: a skill at the top that has not been practised for weeks loses a
	 * point a week, never going below the decay floor. The stored score is untouched.
	 *
	 * @return array{score:float,decay:float,review_due:bool,idle_days:int}
	 * @param mixed $score Score.
	 * @param mixed $last_scored_ts Last scored ts.
	 * @param mixed $now Now.
	 * @param array $config Config.
	 */
	public static function effective( $score, $last_scored_ts, $now, array $config = null ) {
		$config = $config ? $config : self::config();
		$idle   = $last_scored_ts ? max( 0, (int) floor( ( $now - $last_scored_ts ) / 86400 ) ) : 0;
		$decay  = 0.0;
		if ( $score > $config['decay_floor'] && $idle > $config['decay_grace_days'] ) {
			$weeks = (int) floor( ( $idle - $config['decay_grace_days'] ) / 7 ) + 1;
			$decay = min( $score - $config['decay_floor'], $weeks * (float) $config['decay_per_week'] );
		}
		return array(
			'score'      => round( $score - $decay, 2 ),
			'decay'      => round( $decay, 2 ),
			'review_due' => $decay > 0,
			'idle_days'  => $idle,
		);
	}
}
