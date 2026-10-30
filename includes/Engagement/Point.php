<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Point system Helper class.
 *
 * This class handles the point system settings and functionality.
 *
 * @since 1.0.0
 * @package OhMyLMSPro
 */
namespace OhMyLMS\Engagement;

use OhMyLMS\Engagement\Achievements;

/**
 * Plugin point implementation.
 */
class Point {
	/** Restore a failed point order once, even if point earning has since been disabled.
	 *
	 * @param mixed $user_id User identifier.
	 * @param mixed $order_id Order identifier.
	 */
	public static function refund_purchase( $user_id, $order_id ) {
		return Achievements::locked(
			$user_id,
			static function () use ( $user_id, $order_id ) {
				global $wpdb;
				$spent = $wpdb->get_var( $wpdb->prepare( 'SELECT points FROM %i WHERE user_id=%d AND type=\'point\' AND reason=\'purchase_course\' AND content_id=%d AND status=\'active\' AND points < 0', $wpdb->prefix . 'ohmylms_user_achievement', $user_id, $order_id ) );
				if ( ! $spent ) {
					return false; }
				$result = Achievements::insert_achievement(
					array(
						'user_id'      => $user_id,
						'type'         => 'point',
						'points'       => - (int) $spent,
						'reason'       => 'purchase_course_refund',
						'content_id'   => $order_id,
						'date_created' => current_time( 'mysql' ),
					)
				);
				if ( $result ) {
					do_action( 'ohmylms_after_point_refunded', $user_id, - (int) $spent, $order_id ); }
				return $result;
			}
		);
	}

	/**
	 * Get the point settings: `enabled` and one rule per event (see GamificationRegistry::events()).
	 *
	 * @return array
	 */
	public static function get_rules() {
		return GamificationStore::get( 'points' );
	}

	/**
	 * Find the rule of an event.
	 *
	 * @param string $event Event id.
	 * @return array|null
	 */
	private static function rule( $event ) {
		$settings = self::get_rules();
		foreach ( (array) ( isset( $settings['rules'] ) ? $settings['rules'] : array() ) as $rule ) {
			if ( is_array( $rule ) && isset( $rule['event'] ) && (string) $rule['event'] === (string) $event ) {
				return $rule;
			}
		}
		return null;
	}

	/**
	 * Whether a points amount is a whole number in the allowed range.
	 *
	 * @param mixed $points Points.
	 * @return bool
	 */
	public static function valid_amount( $points ) {
		return is_numeric( $points ) && (int) $points == $points && $points > 0 && $points <= GamificationRegistry::MAX_POINTS; // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- Accepts "5" and 5.0, rejects 5.5.
	}

	/**
	 * Maybe enable the point system.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function maybe_enable() {
		return Rules::enabled( 'point' );
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
			return false;
		}
		$rule = self::rule( $event );
		if ( ! $rule || empty( $rule['enabled'] ) ) {
			return false;
		}
		return null === $rule['threshold'] || ( is_numeric( $threshold ) && $rule['threshold'] <= $threshold );
	}

	/**
	 * Add points to a user.
	 *
	 * @param int    $user_id User identifier.
	 * @param mixed  $type Type.
	 * @param int    $points Points.
	 * @param string $reason Reason.
	 * @param mixed  $course_id Course identifier.
	 * @param mixed  $membership_id Membership identifier.
	 * @param mixed  $content_id Content identifier.
	 * @return bool
	 * @since 1.0.0
	 */
	public static function add_points( $user_id, $type, $points, $reason = '', $course_id = null, $membership_id = null, $content_id = null ) {
		if ( ! self::maybe_enable() || ! self::valid_amount( $points ) || ! $user_id || ! get_userdata( $user_id ) ) {
			return false; // Points are off, the amount is not a whole number in range, or the user is unknown.
		}
		$points = (int) $points;

		$data     = array(
			'user_id'       => $user_id,
			'type'          => $type,
			'points'        => $points,
			'reason'        => $reason,
			'course_id'     => $course_id,
			'membership_id' => $membership_id,
			'content_id'    => $content_id,
			'date_created'  => current_time( 'mysql' ),
		);
		$response = Achievements::insert_achievement( $data );
		if ( $response ) {
			set_transient( 'points_added_for_user_' . $user_id, true, 60 );
			do_action( 'ohmylms_after_point_added', $user_id, $points, $type );

			// Trigger gamification event for tracking.
			do_action(
				'ohmylms_gamification_trigger_used',
				$type,
				array(
					'points'  => $points,
					'user_id' => $user_id,
				)
			);
		}
		return $response;
	}

	/**
	 * Maybe enable email for a slug for Point.
	 *
	 * @param mixed $slug Slug.
	 */
	public static function maybe_enable_email_for_a_slug( $slug ) {
		$rule = self::rule( $slug );
		return $rule && ! empty( $rule['email']['enabled'] );
	}

	/**
	 * Send email
	 *
	 * @param mixed $body Body.
	 * @param mixed $to To.
	 * @param mixed $subject Subject.
	 */
	public static function send_email( $body, $to, $subject ) {
		$headers = array( 'MIME-Version: 1.0', 'Content-Type: text/html; charset=UTF-8' );
		wp_mail( sanitize_email( $to ), sanitize_text_field( $subject ), wp_kses_post( $body ), $headers );
	}


	/**
	 * Add points to a user.
	 *
	 * @param int    $user_id User identifier.
	 * @param mixed  $type Type.
	 * @param int    $points Points.
	 * @param string $reason Reason.
	 * @param mixed  $course_id Course identifier.
	 * @param mixed  $membership_id Membership identifier.
	 * @param mixed  $content_id Content identifier.
	 * @return bool
	 * @since 1.0.0
	 */
	public static function deduct_points( $user_id, $type, $points, $reason = '', $course_id = null, $membership_id = null, $content_id = null ) {
		if ( ! Reward::maybe_enable() || ! self::valid_amount( $points ) || ! $user_id ) {
			return false; // Rewards are off or the amount is not a whole number in range.
		}
		$points = (int) $points;

		$data     = array(
			'user_id'       => $user_id,
			'type'          => $type,
			'points'        => $points ? -$points : 0, // Deduct points.
			'reason'        => $reason,
			'course_id'     => $course_id,
			'membership_id' => $membership_id,
			'content_id'    => $content_id,
			'date_created'  => current_time( 'mysql' ),
		);
		$response = Achievements::locked(
			$user_id,
			static function () use ( $data, $user_id, $points ) {
				if ( self::get_total_points( $user_id ) < $points ) {
					return false; }
				return Achievements::insert_achievement( $data );
			}
		);
		if ( $response ) {
			do_action( 'ohmylms_after_point_deduct', $user_id, $points, $type );
		}
		return $response;
	}

	/**
	 * Get points for a rules slug.
	 * * @param string $slug Slug.
	 *
	 * @return int
	 * @since 1.0.0
	 * @param mixed $slug Slug.
	 */
	public static function get_points_for_slug( $slug ) {
		$rule = self::rule( $slug );
		return $rule ? (int) $rule['points'] : 0;
	}

	/**
	 * Get the email settings (`enabled`, `subject`, `body`) of a rule.
	 *
	 * @param mixed $slug Event id.
	 * @return array
	 */
	public static function get_email_settings_for_slug( $slug ) {
		$rule = self::rule( $slug );
		return $rule && isset( $rule['email'] ) ? $rule['email'] : array();
	}

	/**
	 * Get the total points for a user.
	 *
	 * @param int $user_id User identifier.
	 * @return int
	 * @since 1.0.0
	 */
	public static function get_total_points( $user_id ) {
		global $wpdb;
		$table_name   = $wpdb->prefix . 'ohmylms_user_achievement';
		$total_points = $wpdb->get_var(
			$wpdb->prepare( 'SELECT SUM(points) FROM %i WHERE user_id = %d AND status = \'active\'', $table_name, $user_id )
		);
		return intval( $total_points );
	}
}
