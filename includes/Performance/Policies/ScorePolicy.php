<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Levels read off the 0-100 mastery score.
 *
 * @package OhMyLMS\Performance
 */

namespace OhMyLMS\Performance\Policies;

use OhMyLMS\Performance\Levels;
use OhMyLMS\Performance\Policy;

defined( 'ABSPATH' ) || exit;

/** The level is the band the mastery score is in: forgiving early, strict near the top. */
final class ScorePolicy implements Policy {

	/** {@inheritdoc} */
	public function id() {
		return 'score';
	}

	/** {@inheritdoc} */
	public function label() {
		return __( 'Mastery score', 'ohmylms' );
	}

	/** {@inheritdoc} */
	public function description() {
		return __( 'A 0-100 score rises quickly while a learner is new to a skill and slowly near the top. The level is the band the score sits in.', 'ohmylms' );
	}

	/**
	 * The level the evidence earns.
	 *
	 * @param array[] $rows    Evidence rows, oldest first.
	 * @param array   $context Context built by the engine, see Policy::level().
	 * @return string One of Levels::SCALE.
	 */
	public function level( array $rows, array $context ) {
		return Levels::from_score( (float) ( $context['score'] ?? 0 ), (int) ( $context['scored'] ?? 0 ), (array) ( $context['bands'] ?? array() ) );
	}
}
