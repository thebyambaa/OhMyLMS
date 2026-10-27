<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * XP settings (option "ohmylms_xp_settings"). XP rewards effort and is never spent; it is
 * separate from points (spendable), from the skill mastery score and from grading.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Engagement;

defined( 'ABSPATH' ) || exit;

/**
 * XP settings (option "ohmylms_xp_settings"). XP rewards effort and is never spent; it is
 * separate from points (spendable), from the skill mastery score and from grading.
 */
final class XpSettings {
	const OPTION = 'ohmylms_xp_settings';

	/**
	 * Defaults for Xp settings.
	 */
	public static function defaults() {
		return array(
			'enable'       => false,
			// XP for a finished practice lesson, as in the math.mn spec: 10, +5 for no mistakes,
			// +2 for each correct answer while the skill is at 90 or more (the challenge zone).
			'lesson'       => 10,
			'perfect'      => 5,
			'challenge'    => 2,
			'min_answered' => 3,
			// Daily goal choices, the default, and a bonus when it is first met each day.
			'goals'        => array( 10, 20, 30 ),
			'default_goal' => 20,
			'goal_bonus'   => 0,
			// A ceiling on XP per day: repeating lessons for points cannot run away.
			'daily_cap'    => 300,
		);
	}

	/**
	 * Get for Xp settings.
	 */
	public static function get() {
		$settings = self::validate( array_replace( self::defaults(), (array) get_option( self::OPTION, array() ) ) );
		return is_wp_error( $settings ) ? self::defaults() : $settings;
	}

	/**
	 * Enabled for Xp settings.
	 */
	public static function enabled() {
		return ! empty( self::get()['enable'] );
	}

	/**
	 * Validate for Xp settings.
	 *
	 * @return array|\WP_Error
	 * @param mixed $settings Configuration settings.
	 */
	public static function validate( $settings ) {
		$fail = static function () {
			return new \WP_Error( 'xp_settings', __( 'Invalid XP settings.', 'ohmylms' ), array( 'status' => 400 ) );
		};
		if ( ! is_array( $settings ) ) {
			return $fail(); }
		$settings = array_replace( self::defaults(), $settings );
		if ( ! in_array( $settings['enable'], array( true, false, 0, 1, '0', '1' ), true ) ) {
			return $fail(); }
		$settings['enable'] = in_array( $settings['enable'], array( true, 1, '1' ), true );
		foreach ( array(
			'lesson'       => array( 0, 1000 ),
			'perfect'      => array( 0, 1000 ),
			'challenge'    => array( 0, 100 ),
			'min_answered' => array( 1, 30 ),
			'default_goal' => array( 1, 10000 ),
			'goal_bonus'   => array( 0, 1000 ),
			'daily_cap'    => array( 10, 100000 ),
		) as $key => $range ) {
			if ( filter_var( $settings[ $key ], FILTER_VALIDATE_INT ) === false || $settings[ $key ] < $range[0] || $settings[ $key ] > $range[1] ) {
				return $fail(); }
			$settings[ $key ] = (int) $settings[ $key ];
		}
		if ( ! is_array( $settings['goals'] ) || count( $settings['goals'] ) < 1 || count( $settings['goals'] ) > 6 ) {
			return $fail(); }
		$goals = array();
		foreach ( $settings['goals'] as $goal ) {
			if ( filter_var( $goal, FILTER_VALIDATE_INT ) === false || $goal < 1 || $goal > 10000 ) {
				return $fail(); }
			$goals[ (int) $goal ] = (int) $goal;
		}
		sort( $goals );
		$settings['goals'] = array_values( $goals );
		if ( ! in_array( $settings['default_goal'], $settings['goals'], true ) ) {
			return $fail(); }
		return array_intersect_key( $settings, self::defaults() );
	}
}
