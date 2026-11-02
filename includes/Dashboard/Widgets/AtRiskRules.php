<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * The at-risk rules: which signals flag a learner, and how strongly.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard\Widgets;

defined( 'ABSPATH' ) || exit;

/**
 * Pure evaluation of one learner's signals against the rules of {@see AtRiskSettings::rules()}:
 *
 * - `inactive`: days since the last activity >= `min` (default 7);
 * - `mastery`: the largest drop of a skill's mastery score over the last `days` days >= `min` points
 *   (default 10 in 14 days);
 * - `overdue`: assigned class work past its due date and not completed >= `min` items (default 1).
 *
 * Level `high` when two or more rules match or one reaches its `severe` threshold, `watch` for one rule.
 */
final class AtRiskRules {

	const HIGH  = 'high';
	const WATCH = 'watch';

	/**
	 * Rule ids in display order.
	 */
	const RULES = array( 'inactive', 'mastery', 'overdue' );

	/**
	 * Evaluate signals.
	 *
	 * @param array $signals `inactive` (days or null when unknown), `mastery` (points dropped or null), `overdue` (items).
	 * @param array $rules   {@see AtRiskSettings::rules()}.
	 * @return array{level:string,reasons:array[]}|null Null when no rule matches.
	 */
	public static function evaluate( array $signals, array $rules ) {
		$reasons = array();
		foreach ( self::RULES as $rule ) {
			$value = $signals[ $rule ] ?? null;
			$def   = $rules[ $rule ] ?? array();
			if ( null === $value || empty( $def['enabled'] ) ) {
				continue;
			}
			$value = (int) floor( (float) $value );
			if ( $value < (int) $def['min'] ) {
				continue;
			}
			$reasons[] = array(
				'rule'   => $rule,
				'value'  => $value,
				'severe' => $value >= (int) $def['severe'],
			);
		}
		if ( ! $reasons ) {
			return null;
		}
		$severe = in_array( true, wp_list_pluck( $reasons, 'severe' ), true );
		return array(
			'level'   => count( $reasons ) >= 2 || $severe ? self::HIGH : self::WATCH,
			'reasons' => $reasons,
		);
	}

	/**
	 * Sort weight of an evaluated learner: level, then number of reasons, then how far past the thresholds.
	 *
	 * @param array $result {@see self::evaluate()}.
	 * @param array $rules  Rules.
	 * @return float Higher is more urgent.
	 */
	public static function weight( array $result, array $rules ) {
		$weight  = self::HIGH === $result['level'] ? 1000 : 0;
		$weight += 100 * count( $result['reasons'] );
		foreach ( $result['reasons'] as $reason ) {
			$weight += min( 99, $reason['value'] / max( 1, (int) $rules[ $reason['rule'] ]['min'] ) );
		}
		return $weight;
	}

	/**
	 * Translated text of a reason, such as "Inactive 9 days" or "Mastery down 15 pts in 2 wks".
	 *
	 * @param array $reason `rule` and `value`.
	 * @param array $rules  Rules (for the mastery window).
	 * @return string
	 */
	public static function text( array $reason, array $rules ) {
		$value = (int) $reason['value'];
		switch ( $reason['rule'] ) {
			case 'inactive':
				/* translators: %s: number of days. */
				return sprintf( _n( 'Inactive %s day', 'Inactive %s days', $value, 'ohmylms' ), number_format_i18n( $value ) );
			case 'mastery':
				$days   = (int) ( $rules['mastery']['days'] ?? 14 );
				$window = 0 === $days % 7
					/* translators: %s: number of weeks. */
					? sprintf( _n( '%s wk', '%s wks', $days / 7, 'ohmylms' ), number_format_i18n( $days / 7 ) )
					/* translators: %s: number of days. */
					: sprintf( _n( '%s day', '%s days', $days, 'ohmylms' ), number_format_i18n( $days ) );
				/* translators: 1: points, 2: time window such as "2 wks". */
				return sprintf( _n( 'Mastery down %1$s pt in %2$s', 'Mastery down %1$s pts in %2$s', $value, 'ohmylms' ), number_format_i18n( $value ), $window );
			case 'overdue':
				/* translators: %s: number of items. */
				return sprintf( _n( '%s overdue item', '%s overdue items', $value, 'ohmylms' ), number_format_i18n( $value ) );
		}
		return '';
	}

	/**
	 * Reasons joined for display: "Inactive 7 days · Mastery down 15 pts in 2 wks".
	 *
	 * @param array[] $reasons Reasons.
	 * @param array   $rules   Rules.
	 * @return string
	 */
	public static function summary( array $reasons, array $rules ) {
		$texts = array();
		foreach ( $reasons as $reason ) {
			$texts[] = self::text( $reason, $rules );
		}
		return implode( ' · ', $texts );
	}
}
