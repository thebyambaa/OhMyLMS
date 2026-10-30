<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Shared, fail-closed achievement conditions. Points remain the spendable balance.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Engagement;

/** Shared, fail-closed achievement criteria. Points remain the spendable balance. */
final class Rules {
	/**
	 * Group names used by the readers, mapped to gamification sections.
	 */
	const SECTIONS = array(
		'point'       => 'points',
		'badge'       => 'badges',
		'level'       => 'levels',
		'reward'      => 'rewards',
		'leaderboard' => 'leaderboard',
	);

	/**
	 * Whether a group (point, badge, level, reward or leaderboard) is usable.
	 *
	 * Gamification must be on in Add-ons and the group switched on in its own settings.
	 *
	 * @param string $group Group name.
	 * @return bool
	 */
	public static function enabled( $group ) {
		return isset( self::SECTIONS[ $group ] ) && GamificationStore::enabled( self::SECTIONS[ $group ] );
	}

	/**
	 * Whether a list of criteria is non-empty and every criterion is allowed.
	 *
	 * Metrics and operators come from fixed allow-lists and the value must be an integer in range,
	 * so stored criteria can never run arbitrary comparisons.
	 *
	 * @param mixed $rules Criteria: a list of `metric`, `operator` and `value`.
	 * @return bool
	 */
	public static function valid( $rules ) {
		if ( ! is_array( $rules ) || ! $rules || count( $rules ) > GamificationRegistry::MAX_CRITERIA ) {
			return false;
		}
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule )
				|| ! isset( $rule['metric'], $rule['operator'], $rule['value'] )
				|| ! is_string( $rule['metric'] ) || ! isset( GamificationRegistry::metrics()[ $rule['metric'] ] )
				|| ! in_array( $rule['operator'], GamificationRegistry::operators(), true )
				|| ! ( is_int( $rule['value'] ) || ( is_string( $rule['value'] ) && ctype_digit( $rule['value'] ) ) )
				|| $rule['value'] < 0 || $rule['value'] > GamificationRegistry::MAX_CRITERION_VALUE ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Value for Rules.
	 *
	 * @param mixed $field Field.
	 * @param mixed $user User.
	 */
	public static function value( $field, $user ) {
		$user = (int) $user;
		if ( 'points' === $field ) {
			return Point::get_total_points( $user ); }
		if ( 'completed_courses' === $field ) {
			return ( new \OhMyLMS\Data\Student( $user ) )->get_completed_course_count(); }
		if ( 'completed_lesson' === $field ) {
			global $wpdb;
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT p.content_id) FROM {$wpdb->prefix}ohmylms_user_progress p JOIN {$wpdb->prefix}ohmylms_user_enrollment e ON e.id=p.enrollment_id JOIN {$wpdb->posts} lesson ON lesson.ID=p.content_id WHERE e.user_id=%d AND p.status='completed' AND lesson.post_type=%s", $user, OHMYLMS_LESSON_CPT ) );
		}
		return null;
	}

	/**
	 * Compare for Rules.
	 *
	 * @param mixed $value Value to process.
	 * @param mixed $sign Sign.
	 * @param mixed $required Required.
	 */
	public static function compare( $value, $sign, $required ) {
		if ( ! is_numeric( $value ) || ! is_numeric( $required ) || ! is_finite( (float) $value ) || ! is_finite( (float) $required ) ) {
			return false;
		}
		// Preserve integer precision; normalize only the mixed integer/float equality case.
		$value    += 0;
		$required += 0;
		$equal     = is_int( $value ) && is_int( $required ) ? $value === $required : (float) $value === (float) $required;
		switch ( $sign ) {
			case '>=':
				return $value >= $required;
			case '<=':
				return $value <= $required;
			case '>':
				return $value > $required;
			case '<':
				return $value < $required;
			case '!=':
				return ! $equal;
			case '==':
				return $equal;
		}
		return false;
	}

	/**
	 * Whether a learner meets every criterion.
	 *
	 * @param mixed $rules Criteria.
	 * @param mixed $user  User id.
	 * @param array $cache Metric values already read for this learner (filled in).
	 * @return bool
	 */
	public static function met( $rules, $user, array &$cache = array() ) {
		if ( ! $user || ! self::valid( $rules ) ) {
			return false;
		}
		foreach ( $rules as $rule ) {
			if ( ! array_key_exists( $rule['metric'], $cache ) ) {
				$cache[ $rule['metric'] ] = self::value( $rule['metric'], $user );
			}
			if ( ! self::compare( $cache[ $rule['metric'] ], $rule['operator'], $rule['value'] ) ) {
				return false;
			}
		}
		return true;
	}
}
