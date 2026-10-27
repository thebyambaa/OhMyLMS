<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Levels from transparent evidence rules.
 *
 * @package OhMyLMS\Performance
 */

namespace OhMyLMS\Performance\Policies;

use OhMyLMS\Performance\Policy;

defined( 'ABSPATH' ) || exit;

/**
 * Only primary-skill, independent, first-try evidence counts toward attainment; assisted answers
 * and retries are kept but stay separate.
 *
 *  - Attempted:  some evidence exists.
 *  - Familiar:   at least `familiar_min` independent answers at `familiar_accuracy` or better.
 *  - Proficient: enough recent independent answers, from enough families, at a high accuracy.
 *  - Mastered:   the proficient criteria at a higher bar, plus a correct answer given at least
 *                `review_gap_hours` after the learner first reached proficiency.
 */
final class EvidencePolicy implements Policy {

	/** Default thresholds; the `ohmylms_mastery_rules` option overrides them. */
	const DEFAULTS = array(
		'window'              => 8,
		'familiar_min'        => 2,
		'familiar_accuracy'   => 0.6,
		'proficient_min'      => 3,
		'proficient_families' => 2,
		'proficient_accuracy' => 0.8,
		'mastered_min'        => 5,
		'mastered_families'   => 3,
		'mastered_accuracy'   => 0.85,
		'review_gap_hours'    => 24,
		'review_after_days'   => 14,
	);

	/** {@inheritdoc} */
	public function id() {
		return 'evidence';
	}

	/** {@inheritdoc} */
	public function label() {
		return __( 'Evidence rules', 'ohmylms' );
	}

	/** {@inheritdoc} */
	public function description() {
		return __( 'A level is earned by enough recent independent answers, from different question families, at a high accuracy. Mastery also needs a correct answer after a gap.', 'ohmylms' );
	}

	/**
	 * The level the evidence earns.
	 *
	 * @param array[] $rows    Evidence rows, oldest first.
	 * @param array   $context Context built by the engine, see Policy::level().
	 * @return string One of Levels::SCALE.
	 */
	public function level( array $rows, array $context ) {
		return self::evaluate( $rows, $context['rules'] ?? self::DEFAULTS, (int) ( $context['now'] ?? time() ) )['level'];
	}

	/**
	 * Level and review timing for ordered evidence rows (oldest first). Pure.
	 *
	 * @param array[] $rows  Evidence rows.
	 * @param array   $rules Thresholds, see DEFAULTS.
	 * @param int     $now   Timestamp.
	 * @return array level, review_due, review_due_at, evidence_count, independent_correct, families, accuracy, last_evidence_at.
	 */
	public static function evaluate( array $rows, array $rules, $now ) {
		$rules       = array_merge( self::DEFAULTS, $rules );
		$independent = array_values(
			array_filter(
				$rows,
				static function ( $row ) {
					return ! empty( $row['independent'] ) && ! empty( $row['first_try'] ) && (float) $row['available'] > 0;
				}
			)
		);
		$correct     = static function ( $row ) {
			return (float) $row['awarded'] >= (float) $row['available'] - 0.0001;
		};
		$family      = static function ( $row ) {
			return '' !== (string) ( $row['family_id'] ?? '' ) ? 'f:' . $row['family_id'] : 'q:' . $row['question_id'];
		};
		$accuracy_of = static function ( array $window ) {
			return $window ? array_sum(
				array_map(
					static function ( $row ) {
						return min( 1, (float) $row['awarded'] / (float) $row['available'] );
					},
					$window
				)
			) / count( $window ) : 0.0;
		};
		$level       = $rows ? 'attempted' : 'not-started';
		$recent      = array_slice( $independent, -max( 1, (int) $rules['window'] ) );
		$accuracy    = $accuracy_of( $recent );
		$hits_recent = array_values( array_filter( $recent, $correct ) );
		$families    = count( array_unique( array_map( $family, $hits_recent ) ) );
		if ( count( $recent ) >= (int) $rules['familiar_min'] && $accuracy >= (float) $rules['familiar_accuracy'] ) {
			$level = 'familiar';
		}
		// First moment the proficient bar was met, walking the history forward.
		$proficient_at = null;
		for ( $i = 0, $n = count( $independent ); $i < $n; $i++ ) {
			$window = array_slice( $independent, max( 0, $i + 1 - (int) $rules['window'] ), min( $i + 1, (int) $rules['window'] ) );
			$hits   = array_values( array_filter( $window, $correct ) );
			if ( count( $hits ) >= $rules['proficient_min'] && count( array_unique( array_map( $family, $hits ) ) ) >= $rules['proficient_families'] && $accuracy_of( $window ) >= $rules['proficient_accuracy'] ) {
				$proficient_at = strtotime( $independent[ $i ]['evidence_at'] . ' UTC' );
				break;
			}
		}
		$proficient_now = count( $hits_recent ) >= $rules['proficient_min'] && $families >= $rules['proficient_families'] && $accuracy >= $rules['proficient_accuracy'];
		if ( $proficient_now ) {
			$level = 'proficient';
		}
		if ( $proficient_now && null !== $proficient_at && count( $hits_recent ) >= $rules['mastered_min'] && $families >= $rules['mastered_families'] && $accuracy >= $rules['mastered_accuracy'] ) {
			$gap = (int) $rules['review_gap_hours'] * 3600;
			foreach ( $hits_recent as $row ) {
				if ( strtotime( $row['evidence_at'] . ' UTC' ) >= $proficient_at + $gap ) {
					$level = 'mastered';
					break;
				}
			}
		}
		$last          = $rows ? strtotime( end( $rows )['evidence_at'] . ' UTC' ) : null;
		$review_due_at = in_array( $level, array( 'proficient', 'mastered' ), true ) && $last ? $last + (int) $rules['review_after_days'] * DAY_IN_SECONDS : null;
		return array(
			'level'               => $level,
			'review_due'          => null !== $review_due_at && $now >= $review_due_at ? 1 : 0,
			'review_due_at'       => $review_due_at ? gmdate( 'Y-m-d H:i:s', $review_due_at ) : null,
			'evidence_count'      => count( $rows ),
			'independent_correct' => count( array_filter( $independent, $correct ) ),
			'families'            => $families,
			'accuracy'            => round( $accuracy, 4 ),
			'last_evidence_at'    => $last ? gmdate( 'Y-m-d H:i:s', $last ) : null,
		);
	}
}
