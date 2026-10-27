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
	 * Get the point settings.
	 *
	 * @return array
	 */
	public static function get_rules() {
		$settings = get_option( 'ohmylms_point_settings', array() );
		return apply_filters( 'ohmylms_point_settings', $settings );
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
			return false; }
		$settings = self::get_rules();

		if ( ! empty( $settings['rules'] ) && is_array( $settings['rules'] ) ) {
			foreach ( $settings['rules'] as $rule ) {
				if ( isset( $rule['slug'], $rule['value'] ) && $rule['slug'] === $event && $rule['value'] ) {
					if ( ! isset( $rule['threshold'] ) || ( isset( $rule['threshold'] ) && $rule['threshold'] <= $threshold ) ) {
						return true;
					}
				}
			}
		}
		return false;
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
		if ( ! self::maybe_enable() || ! is_numeric( $points ) || $points <= 0 || ! $user_id ) {
			return false; // Point system is not enabled.
		}

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
		$settings = self::get_rules();
		if ( ! empty( $settings['rules'] ) && is_array( $settings['rules'] ) ) {
			foreach ( $settings['rules'] as $rule ) {
				if ( isset( $rule['email'], $rule['email']['enable'] ) && (string) $rule['slug'] === (string) $slug && $rule['email']['enable'] ) {
					return true;
				}
			}
		}
		return false;
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
		wp_mail( $to, $subject, $body, $headers );
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
		if ( ! Reward::maybe_enable() || ! is_numeric( $points ) || $points <= 0 || ! $user_id ) {
			return false; // Point system is not enabled.
		}

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
		$settings = self::get_rules();
		if ( ! empty( $settings['rules'] ) && is_array( $settings['rules'] ) ) {
			foreach ( $settings['rules'] as $rule ) {
				if ( isset( $rule['point'], $rule['point'] ) && $rule['slug'] === $slug ) {
					return intval( $rule['point'] ?? 0 );
				}
			}
		}
		return 0; // Default points if not found.
	}


	/**
	 * Get points for a rules slug.
	 * * @param string $slug Slug.
	 *
	 * @return int
	 * @since 1.0.0
	 * @param mixed $slug Slug.
	 */
	public static function get_email_settings_for_slug( $slug ) {
		$settings = self::get_rules();
		if ( ! empty( $settings['rules'] ) && is_array( $settings['rules'] ) ) {
			foreach ( $settings['rules'] as $rule ) {
				if ( isset( $rule['email'] ) && $rule['slug'] === $slug ) {
					return $rule['email'];
				}
			}
		}
		return array();
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
