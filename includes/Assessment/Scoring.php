<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Explicit scoring policy per attempt.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Assessment;

defined( 'ABSPATH' ) || exit;

/**
 * Explicit scoring policy per attempt.
 *
 * - legacy-int: the original behavior; each item awards whole points (rounded).
 * - decimal:    items award marks x fraction to four decimal places.
 *
 * Attempts keep the policy they started with, so totals never change retroactively.
 * New attempts use decimal scoring once the attempt total column has been migrated.
 */
final class Scoring {
	const DECIMAL         = 'decimal';
	const LEGACY          = 'legacy-int';
	const MIGRATED_OPTION = 'ohmylms_attempt_total_decimal';

	/**
	 * Policy for new attempts for Scoring.
	 */
	public static function policy_for_new_attempts() {
		return get_option( self::MIGRATED_OPTION ) === '1' ? self::DECIMAL : self::LEGACY;
	}

	/**
	 * Award for Scoring.
	 *
	 * @param mixed $marks Marks.
	 * @param mixed $fraction Fraction.
	 * @param mixed $policy Policy.
	 */
	public static function award( $marks, $fraction, $policy ) {
		$fraction = max( 0.0, min( 1.0, (float) $fraction ) );
		$value    = max( 0.0, (float) $marks ) * $fraction;
		return self::DECIMAL === $policy ? round( $value, 4 ) : (float) (int) round( $value );
	}

	/** Manual marks under the attempt's policy, clamped to the item's maximum.
	 *
	 * @param mixed $mark Mark.
	 * @param mixed $max Max.
	 * @param mixed $policy Policy.
	 */
	public static function manual( $mark, $max, $policy ) {
		$mark = max( 0.0, min( (float) $max, (float) $mark ) );
		return self::DECIMAL === $policy ? round( $mark, 4 ) : (float) (int) round( $mark );
	}

	/**
	 * Total for Scoring.
	 *
	 * @param array $awards Awards.
	 */
	public static function total( array $awards ) {
		return round( array_sum( array_map( 'floatval', $awards ) ), 4 );
	}

	/** Format a stored total for display without trailing zeros.
	 *
	 * @param mixed $value Value to process.
	 */
	public static function display( $value ) {
		$value = round( (float) $value, 4 );
		return rtrim( rtrim( number_format( $value, 4, '.', '' ), '0' ), '.' );
	}
}
