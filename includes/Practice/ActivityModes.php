<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class mapping.
/**
 * Compatibility manifest for reusable practice activities.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Practice;

defined( 'ABSPATH' ) || exit;

/** The content family determines the editor and private response contract. */
final class ActivityModes {
	const MODES = array(
		'spin-wheel'       => 'deck',
		'open-box'         => 'deck',
		'speaking-cards'   => 'deck',
		'flip-tiles'       => 'deck',
		'find-match'       => 'match',
		'pair-or-no-pair'  => 'match',
		'balloon-pop'      => 'match',
		'gameshow'         => 'choice',
		'timed-true-false' => 'choice',
		'win-or-lose'      => 'choice',
		'maze-chase'       => 'choice',
		'flying-fruit'     => 'choice',
		'whack-a-mole'     => 'choice',
		'airplane'         => 'choice',
		'speed-sorting'    => 'sort',
		'word-magnets'     => 'magnets',
	);

	/**
	 * Resolve a registered family.
	 *
	 * @param string $mode Requested mode.
	 * @return string Family, or empty for another player.
	 */
	public static function family( $mode ) {
		return self::MODES[ $mode ] ?? '';
	}

	/**
	 * Check compatible approved content without converting its response format.
	 *
	 * @param string $mode Activity mode.
	 * @param object $snapshot Immutable question.
	 * @return bool Compatible content.
	 */
	public static function compatible( $mode, $snapshot ) {
		$type     = $snapshot->get_type();
		$settings = $snapshot->get_settings();
		$family   = self::family( $mode );
		if ( 'deck' === $family && 'matching' !== $type ) {
			return 'flip-tiles' !== $mode && 'statement' === $type && '' !== RecallActivity::label( $snapshot->get_name() );
		}
		if ( in_array( $family, array( 'deck', 'match' ), true ) ) {
			$options = $snapshot->get_questions();
			$fronts  = array_map( array( RecallActivity::class, 'label' ), array_column( $options, 'answer' ) );
			$backs   = array_map(
				static function ( $option ) {
					return RecallActivity::label( $option['matching_data']['label'] ?? '' );
				},
				$options
			);
			return 'matching' === $type && count( $options ) > 0 && 30 >= count( $options ) && ! in_array( '', $fronts, true ) && ! in_array( '', $backs, true ) && ( 'match' !== $family || ( 2 <= count( array_unique( $backs ) ) && count( array_unique( $fronts ) ) === count( $fronts ) ) );
		}
		if ( 'choice' === $family ) {
			foreach ( $snapshot->get_questions() as $option ) {
				if ( '' === RecallActivity::label( $option['answer'] ) ) {
					return false;
				}
			}
			return in_array( $type, 'timed-true-false' === $mode ? array( 'true-false' ) : array( 'single-choice', 'true-false' ), true ) && 2 <= count( $snapshot->get_questions() ) && 6 >= count( $snapshot->get_questions() ) && 1 === count( $snapshot->get_correct_options() );
		}
		if ( 'sort' === $family ) {
			return 'categorize' === $type && 2 <= count( $settings['buckets'] ?? array() ) && 8 >= count( $settings['buckets'] ?? array() ) && 0 < count( $settings['items'] ?? array() );
		}
		return 'magnets' === $family && 'build-expression' === $type && 'words' === ( $settings['tile_mode'] ?? '' ) && 0 < count( $settings['tiles'] ?? array() ) && 100 >= count( $settings['tiles'] ?? array() );
	}
}
