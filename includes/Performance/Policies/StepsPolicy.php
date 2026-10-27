<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Levels that move one step at a time, exercise by exercise and test by test.
 *
 * @package OhMyLMS\Performance
 */

namespace OhMyLMS\Performance\Policies;

use OhMyLMS\Performance\Levels;
use OhMyLMS\Performance\Policy;

defined( 'ABSPATH' ) || exit;

/**
 * Replays the evidence one activity at a time, following Khan Academy's published rules:
 *
 *  - An exercise (a practice session) raises a skill to Attempted, Familiar (70-99% right) or
 *    Proficient (all right) and never lowers it, except that an imperfect exercise takes Mastered
 *    back to Proficient.
 *  - An assessment (a quiz, exam or question player) moves a skill up one step when every
 *    question on it was right, and down one step when any was missed.
 *
 * Right means right first time without a hint in an exercise; an assessment counts any correct
 * answer. Because the level is replayed from the ledger, a regrade changes it exactly once.
 */
final class StepsPolicy implements Policy {

	/** Activity types that count as exercises; every other source is an assessment. */
	const EXERCISES = array( 'practice' );

	/** {@inheritdoc} */
	public function id() {
		return 'steps';
	}

	/** {@inheritdoc} */
	public function label() {
		return __( 'Step levels', 'ohmylms' );
	}

	/** {@inheritdoc} */
	public function description() {
		return __( 'Practice raises a skill to Attempted, Familiar or Proficient. A quiz or exam moves it up one step when every question on the skill is right and down one step when any is missed.', 'ohmylms' );
	}

	/**
	 * The level the evidence earns.
	 *
	 * @param array[] $rows    Evidence rows, oldest first.
	 * @param array   $context Context built by the engine, see Policy::level().
	 * @return string One of Levels::SCALE.
	 */
	public function level( array $rows, array $context ) {
		$level = 'not-started';
		foreach ( self::activities( $rows ) as $activity ) {
			$level = self::apply( $level, $activity );
		}
		return $level;
	}

	/**
	 * The rows grouped into the activities they came from, in the order each began.
	 *
	 * @param array[] $rows Evidence rows, oldest first.
	 * @return array[] Each: type, id, exercise, right, wrong, total.
	 */
	public static function activities( array $rows ) {
		$groups = array();
		foreach ( $rows as $row ) {
			$type = (string) ( $row['source_type'] ?? '' );
			$key  = $type . ':' . (int) ( $row['source_id'] ?? 0 );
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'type'     => $type,
					'id'       => (int) ( $row['source_id'] ?? 0 ),
					'exercise' => in_array( $type, self::EXERCISES, true ),
					'right'    => 0,
					'wrong'    => 0,
					'total'    => 0,
				);
			}
			if ( (float) $row['available'] <= 0 ) {
				continue;
			}
			$correct = (float) $row['awarded'] >= (float) $row['available'] - 0.0001;
			if ( $groups[ $key ]['exercise'] ) {
				$correct = $correct && ! empty( $row['independent'] ) && ! empty( $row['first_try'] );
			}
			++$groups[ $key ]['total'];
			++$groups[ $key ][ $correct ? 'right' : 'wrong' ];
		}
		return array_values( array_filter( $groups, static fn( $group ) => $group['total'] > 0 ) );
	}

	/**
	 * The level after one activity.
	 *
	 * @param string $level    Level before.
	 * @param array  $activity One of activities().
	 * @return string
	 */
	public static function apply( $level, array $activity ) {
		return $activity['exercise']
			? Levels::exercise( $level, $activity['right'], $activity['total'] )
			: Levels::assessment( $level, 0 === $activity['wrong'] );
	}
}
