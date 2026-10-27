<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Shared, fail-closed achievement conditions. Points remain the spendable balance.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Engagement;

/** Shared, fail-closed achievement conditions. Points remain the spendable balance. */
final class Rules {
	const FIELDS = array( 'points', 'completed_lesson', 'completed_courses' );
	const SIGNS  = array( '>=', '<=', '>', '<', '!=', '==', '=' );

	/**
	 * Enabled for Rules.
	 *
	 * @param mixed $group Group.
	 */
	public static function enabled( $group ) {
		$integrations = get_option( 'ohmylms_integrations', array() );
		$settings     = get_option( 'ohmylms_' . $group . '_settings', array() );
		// Legacy "enable" fields were placeholders with no UI; preserve their old behavior.
		// The explicit switch introduced here defaults on for existing configurations.
		return ! empty( $integrations['gamification']['is_enable'] ) && ( ! isset( $settings['feature_enabled'] ) || in_array( $settings['feature_enabled'], array( true, 1, '1' ), true ) );
	}

	/**
	 * Valid for Rules.
	 *
	 * @param mixed $rules Rules.
	 */
	public static function valid( $rules ) {
		if ( ! is_array( $rules ) || ! $rules ) {
			return false; }
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) || ! in_array( $rule['dataValue'] ?? '', self::FIELDS, true )
				|| ! in_array( $rule['compareSign'] ?? '', self::SIGNS, true )
				|| ! isset( $rule['compareData'] ) || ! is_numeric( $rule['compareData'] )
				|| ! is_finite( (float) $rule['compareData'] ) || $rule['compareData'] < 0 ) {
				return false; }
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
			case '=':
			case '==':
				return $equal;
		}
		return false;
	}

	/**
	 * Met for Rules.
	 *
	 * @param mixed $rules Rules.
	 * @param mixed $user User.
	 */
	public static function met( $rules, $user ) {
		if ( ! $user || ! self::valid( $rules ) ) {
			return false; }
		foreach ( $rules as $rule ) {
			if ( ! self::compare( self::value( $rule['dataValue'], $user ), $rule['compareSign'], $rule['compareData'] ) ) {
				return false; }
		}
		return true;
	}
}
