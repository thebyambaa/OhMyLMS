<?php // phpcs:ignore WordPress.Files.FileName -- Required Composer PSR-4 class/file mapping.
/**
 * Fixed-date, zero-price membership promotions without a billing agreement.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Membership;

use OhMyLMS\Student\Hub;

defined( 'ABSPATH' ) || exit;

/** Free grants have their own row identity; expiration must never revoke a paid upgrade. */
final class FreeSale {
	const META        = '_ohmylms_membership_free_sales';
	const EXPIRY_HOOK = 'ohmylms_expire_free_membership';

	/** Register authenticated signup and cron/request-time expiry. */
	public static function init() {
		add_action( 'admin_post_ohmylms_free_membership', array( __CLASS__, 'signup' ) );
		add_action( 'init', array( __CLASS__, 'expire_current' ), 20 );
		add_action( self::EXPIRY_HOOK, array( __CLASS__, 'expire' ) );
	}

	/**
	 * Read a strict calendar date from an existing sale-date field.
	 *
	 * @param mixed $value Date object, REST date object or ISO date string.
	 * @return string Valid Y-m-d or empty.
	 */
	public static function date( $value ) {
		if ( $value instanceof \DateTimeInterface ) {
			return $value->format( 'Y-m-d' );
		}
		$value = is_array( $value ) ? ( $value['date'] ?? '' ) : $value;
		if ( ! is_string( $value ) || ! preg_match( '/^(\d{4}-\d{2}-\d{2})(?:[T ].*)?$/D', $value, $match ) ) {
			return '';
		}
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $match[1], new \DateTimeZone( 'UTC' ) );
		return $date && $match[1] === $date->format( 'Y-m-d' ) ? $match[1] : '';
	}

	/**
	 * The exclusive expiry boundary, at the next local midnight.
	 *
	 * @param string        $end Sale end date.
	 * @param \DateTimeZone $timezone Site timezone.
	 * @return int UTC timestamp, or zero for an invalid date.
	 */
	public static function cutoff( $end, \DateTimeZone $timezone ) {
		$date = self::date( $end );
		return $date ? ( new \DateTimeImmutable( $date . ' 00:00:00', $timezone ) )->modify( '+1 day' )->getTimestamp() : 0;
	}

	/**
	 * An active, published zero-price sale, independently of cached cart totals.
	 *
	 * @param object|null $plan Membership object.
	 * @return int Free access expiry, or zero when no free promotion is active.
	 */
	public static function active( $plan ) {
		if ( ! $plan || 'ohmylms-membership' !== get_post_type( $plan->get_id() ) || 'publish' !== $plan->get_status() || '' === (string) $plan->get_sale_price( 'edit' ) || ! is_numeric( $plan->get_sale_price( 'edit' ) ) || 0.0 !== (float) $plan->get_sale_price( 'edit' ) ) {
			return 0;
		}
		$end   = self::cutoff( self::date( $plan->get_sale_price_dates_to() ), wp_timezone() );
		$start = self::date( $plan->get_sale_price_dates_from() );
		$now   = time();
		return $end > $now && ( ! $start || ( new \DateTimeImmutable( $start, wp_timezone() ) )->getTimestamp() <= $now ) ? $end : 0;
	}

	/**
	 * Render a nonce-protected free signup, separate from payment checkout.
	 *
	 * @param object $plan Membership object.
	 */
	public static function form( $plan ) {
		if ( ! self::active( $plan ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			echo '<a class="oml-hub-button ohmylms-button" href="' . esc_url( wp_login_url( Hub::url( 'membership', 'plans' ) ) ) . '">' . esc_html__( 'Sign in for free access', 'ohmylms' ) . '</a>';
			return;
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'ohmylms_free_membership_' . $plan->get_id() );
		echo '<input type="hidden" name="action" value="ohmylms_free_membership"><input type="hidden" name="membership_id" value="' . esc_attr( $plan->get_id() ) . '">';
		echo '<button type="submit" class="oml-hub-button ohmylms-button">' . esc_html__( 'Start free access', 'ohmylms' ) . '</button></form>';
		echo '<p>' . esc_html__( 'Access ends on the sale end date. No payment or automatic renewal.', 'ohmylms' ) . '</p>';
	}

	/** Handle an authenticated free signup. */
	public static function signup() {
		$plan_id = absint( $_POST['membership_id'] ?? 0 );
		check_admin_referer( 'ohmylms_free_membership_' . $plan_id );
		if ( ! is_user_logged_in() || ( new \OhMyLMS\Data\Student( get_current_user_id() ) )->maybe_banned() ) {
			wp_die( esc_html__( 'This account cannot start free access.', 'ohmylms' ), '', array( 'response' => 403 ) );
		}
		$result = self::claim( $plan_id, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}
		wp_safe_redirect( Hub::url( 'library' ) );
		exit;
	}

	/**
	 * Grant access under a per-user lock, never creating an order or subscription.
	 *
	 * @param int $plan_id Membership ID.
	 * @param int $user_id Current user ID.
	 * @return true|\WP_Error Result.
	 */
	public static function claim( $plan_id, $user_id ) {
		global $wpdb;
		if ( ! $user_id || get_current_user_id() !== $user_id ) {
			return new \WP_Error( 'free_membership_user', __( 'Sign in to start free access.', 'ohmylms' ) );
		}
		$lock = 'oml-free-' . md5( $wpdb->prefix . ':' . $user_id );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 3)', $lock ) ) ) {
			return new \WP_Error( 'free_membership_busy', __( 'Please try again.', 'ohmylms' ) );
		}
		try {
			self::expire_locked( $user_id );
			$plan = ohmylms_get_membership( $plan_id );
			$end  = self::active( $plan );
			if ( ! $end ) {
				return new \WP_Error( 'free_membership_closed', __( 'This free promotion is not available.', 'ohmylms' ) );
			}
			$table    = $wpdb->prefix . 'ohmylms_user_membership';
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM %i WHERE user_id=%d AND membership_id=%d AND status='enrolled' LIMIT 1", $table, $user_id, $plan_id ) );
			if ( ! $existing ) {
				if ( false === $wpdb->insert(
					$table,
					array(
						'user_id'       => $user_id,
						'membership_id' => $plan_id,
						'status'        => 'enrolled',
						'progress'      => 'running',
						'start_date'    => current_time( 'mysql', true ),
						'end_date'      => gmdate( 'Y-m-d H:i:s', $end ),
					)
				) ) {
					return new \WP_Error( 'free_membership_failed', __( 'Free access could not be saved.', 'ohmylms' ) );
				}
				$row_id            = (int) $wpdb->insert_id;
				$grants            = get_user_meta( $user_id, self::META, true );
				$grants            = is_array( $grants ) ? $grants : array();
				$grants[ $row_id ] = array(
					'plan_id' => $plan_id,
					'expires' => $end,
				);
				if ( false === update_user_meta( $user_id, self::META, $grants ) ) {
					$wpdb->delete(
						$table,
						array(
							'id'              => $row_id,
							'user_id'         => $user_id,
							'order_id'        => 0,
							'subscription_id' => 0,
						)
					);
					return new \WP_Error( 'free_membership_failed', __( 'Free access could not be saved.', 'ohmylms' ) );
				}
				wp_schedule_single_event( $end, self::EXPIRY_HOOK, array( $user_id ) );
			}
			CourseSelection::sync( $plan_id );
			return true;
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/** Expire due free grants before this user's lesson/quiz permission checks. */
	public static function expire_current() {
		if ( get_current_user_id() ) {
			self::expire( get_current_user_id() );
		}
	}

	/**
	 * Expire only a tracked free grant; paid replacements and other sources survive.
	 *
	 * @param int $user_id Grant owner.
	 */
	public static function expire( $user_id ) {
		global $wpdb;
		$grants = get_user_meta( $user_id, self::META, true );
		if ( ! is_array( $grants ) || ! $grants ) {
			return;
		}
		$lock = 'oml-free-' . md5( $wpdb->prefix . ':' . $user_id );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 3)', $lock ) ) ) {
			return;
		}
		try {
			self::expire_locked( $user_id );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/**
	 * Apply expiry while holding the user's free-grant lock.
	 *
	 * @param int $user_id Grant owner.
	 */
	private static function expire_locked( $user_id ) {
		global $wpdb;
		$grants    = get_user_meta( $user_id, self::META, true );
		$grants    = is_array( $grants ) ? $grants : array();
		$remaining = $grants;
		foreach ( $grants as $row_id => $grant ) {
			if ( (int) $grant['expires'] > time() ) {
				continue;
			}
			$changed = $wpdb->query( $wpdb->prepare( "UPDATE %i SET status='expired' WHERE id=%d AND user_id=%d AND membership_id=%d AND order_id=0 AND subscription_id=0 AND status='enrolled'", $wpdb->prefix . 'ohmylms_user_membership', $row_id, $user_id, $grant['plan_id'] ) );
			if ( false === $changed ) {
				continue;
			}
			// Retry failed course revocation on the next request, even if the grant is already expired.
			$revoked = $wpdb->query( $wpdb->prepare( "UPDATE %i SET status='cancelled' WHERE user_id=%d AND membership_id=%d AND order_id=0 AND status='enrolled' AND NOT EXISTS (SELECT 1 FROM %i WHERE user_id=%d AND membership_id=%d AND status='enrolled')", $wpdb->prefix . 'ohmylms_user_enrollment', $user_id, $grant['plan_id'], $wpdb->prefix . 'ohmylms_user_membership', $user_id, $grant['plan_id'] ) );
			if ( false === $revoked ) {
				continue;
			}
			unset( $remaining[ $row_id ] );
		}
		if ( $remaining !== $grants ) {
			update_user_meta( $user_id, self::META, $remaining );
		}
	}
}
