<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Reward system Helper class.
 *
 * This class handles the reward system settings and functionality.
 *
 * @since 1.0.0
 * @package OhMyLMSPro
 */
namespace OhMyLMS\Engagement;

use OhMyLMS\Engagement\Achievements;

/**
 * Plugin reward implementation.
 */
class Reward {
	/** Point checkout cannot waive payment for cash items, memberships or disabled courses.
	 *
	 * @param mixed $cart Cart.
	 * @param int   $membership_id Membership identifier.
	 */
	public static function valid_point_cart( $cart, $membership_id = 0 ) {
		if ( $membership_id || ! is_array( $cart ) || ! $cart || ! self::maybe_met_rules( 'purchase_course' ) ) {
			return false; }
		foreach ( $cart as $item ) {
			$id = (int) ( $item['course_id'] ?? $item['id'] ?? 0 );
			if ( ( $item['purchase_by'] ?? '' ) !== 'point' || get_post_type( $id ) !== OHMYLMS_COURSE_CPT ) {
				return false; }
			$course = ohmylms_get_course( $id );
			if ( ! $course || $course->get_reward_disabled() === 'yes' || $course->get_purchase_point() <= 0 ) {
				return false; }
		}
		return true;
	}

	/**
	 * Get the point settings.
	 *
	 * @return array
	 */
	public static function get_rules() {
		$settings = get_option( 'ohmylms_reward_settings', array() );
		return apply_filters( 'ohmylms_reward_settings', $settings );
	}

	/**
	 * Maybe enable the point system.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function maybe_enable() {
		return Rules::enabled( 'reward' );
	}

	/**
	 * Maybe met rules for a specific event.
	 *
	 * @return bool
	 * @since 1.0.0
	 * @param mixed $event Event.
	 * @param int   $threshold Threshold.
	 */
	public static function maybe_met_rules( $event, $threshold = 0 ) {
		if ( ! self::maybe_enable() ) {
			return false; }
		$settings = self::get_rules();
		if ( ! empty( $settings['rules'] ) && is_array( $settings['rules'] ) ) {
			foreach ( $settings['rules'] as $rule ) {
				if ( isset( $rule['slug'], $rule['value'] ) && $rule['slug'] === $event && $rule['value'] ) {
					if ( ! isset( $rule['threshold'] ) || ( isset( $rule['threshold'] ) && Rules::compare( $rule['threshold'], '==', $threshold ) ) ) {
						return true;
					}
				}
			}
		}
		return false;
	}
}
