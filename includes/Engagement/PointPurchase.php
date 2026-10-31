<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Course purchase with bonus points.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Engagement;

use OhMyLMS\Student\Enrollments;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Buys courses with bonus points and refunds such purchases.
 *
 * A purchase is one database transaction under the learner's points lock: balance check, a zero-total
 * order paid with `points` (meta `_purchased_by = point`, `_purchased_point`), one debit row in
 * `ohmylms_user_achievement` (reason `purchase_course`, `content_id` = order) and one pending
 * enrolment per course through {@see Enrollments::create()} with source `purchase/order`. After the
 * commit the order is completed, which activates the enrolments through the existing
 * `ohmylms_payment_completed` listener. A refund restores the points once, cancels the order's
 * enrolments and marks the order refunded, also in one transaction. See docs/api/gamification.md#point-purchases.
 *
 * @since 1.0.0
 */
final class PointPurchase {

	const PAYMENT_METHOD = 'points';
	const MAX_COURSES    = 20;

	/**
	 * Whether purchases with points are switched on (add-on, rewards section, purchase flag).
	 *
	 * @return bool
	 */
	public static function enabled() {
		return Reward::maybe_met_rules( 'purchase_course' );
	}

	/**
	 * Point price of a course, or 0 when it cannot be bought with points.
	 *
	 * @param int $course_id Course ID.
	 * @return int
	 */
	public static function price( $course_id ) {
		$course_id = (int) $course_id;
		if ( OHMYLMS_COURSE_CPT !== get_post_type( $course_id ) || 'publish' !== get_post_status( $course_id ) ) {
			return 0;
		}
		if ( 'yes' === get_post_meta( $course_id, '_reward_disabled', true ) ) {
			return 0;
		}
		$points = get_post_meta( $course_id, '_purchase_point', true );
		return Point::valid_amount( $points ) ? (int) $points : 0;
	}

	/**
	 * Error helper.
	 *
	 * @param string $code    Code.
	 * @param string $message Message.
	 * @param int    $status  HTTP status.
	 * @param array  $data    Extra data.
	 * @return WP_Error
	 */
	private static function error( $code, $message, $status, array $data = array() ) {
		return new WP_Error( $code, $message, array_merge( array( 'status' => $status ), $data ) );
	}

	/**
	 * Whether the learner already holds an active or pending enrolment in the course.
	 *
	 * @param int $user_id   Learner.
	 * @param int $course_id Course.
	 * @return bool
	 */
	private static function holds( $user_id, $course_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh write-side check.
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE user_id = %d AND course_id = %d AND status IN ('enrolled','pending')", $wpdb->prefix . 'ohmylms_user_enrollment', (int) $user_id, (int) $course_id ) );
	}

	/**
	 * Check a purchase without changing anything.
	 *
	 * @param int   $user_id    Learner.
	 * @param int[] $course_ids Courses.
	 * @return array|WP_Error `{course_ids, points, balance, prices}`.
	 */
	public static function quote( $user_id, array $course_ids ) {
		$user_id = (int) $user_id;
		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			return self::error( 'ohmylms_points_not_logged_in', __( 'Log in to buy courses with points.', 'ohmylms' ), 401 );
		}
		if ( ! self::enabled() ) {
			return self::error( 'ohmylms_points_purchase_disabled', __( 'Courses cannot be bought with points on this site.', 'ohmylms' ), 409 );
		}
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $course_ids ) ) ) );
		if ( ! $ids || count( $ids ) !== count( $course_ids ) || count( $ids ) > self::MAX_COURSES ) {
			return self::error(
				'ohmylms_points_invalid_courses',
				/* translators: %d: maximum number of courses. */
				sprintf( __( 'Choose 1 to %d different courses.', 'ohmylms' ), self::MAX_COURSES ),
				400
			);
		}
		$student = new \OhMyLMS\Data\Student( $user_id );
		if ( $student->maybe_banned() ) {
			return self::error( 'ohmylms_points_student_blocked', __( 'Your account has been restricted and is not eligible to make purchases.', 'ohmylms' ), 403 );
		}
		$prices = array();
		foreach ( $ids as $course_id ) {
			$price = self::price( $course_id );
			if ( ! $price ) {
				return self::error( 'ohmylms_points_course_not_for_points', __( 'This course cannot be bought with points.', 'ohmylms' ), 409, array( 'course_id' => $course_id ) );
			}
			if ( self::holds( $user_id, $course_id ) ) {
				return self::error( 'ohmylms_points_already_enrolled', __( 'You are already enrolled in this course.', 'ohmylms' ), 409, array( 'course_id' => $course_id ) );
			}
			$blocked = apply_filters( 'ohmylms_point_purchase_course_error', null, $course_id, $user_id );
			if ( is_wp_error( $blocked ) ) {
				return $blocked;
			}
			$prices[ $course_id ] = $price;
		}

		return array(
			'course_ids' => $ids,
			'prices'     => $prices,
			'points'     => (int) array_sum( $prices ),
			'balance'    => Point::get_total_points( $user_id ),
		);
	}

	/**
	 * Buy courses with points.
	 *
	 * @param int   $user_id    Learner.
	 * @param int[] $course_ids Courses.
	 * @return array|WP_Error `{order_id, points, balance, course_ids}`.
	 */
	public static function purchase( $user_id, array $course_ids ) {
		global $wpdb;
		$quote = self::quote( $user_id, $course_ids );
		if ( is_wp_error( $quote ) ) {
			return $quote;
		}
		if ( $quote['balance'] < $quote['points'] ) {
			return self::insufficient( $quote );
		}

		$order_id = 0;
		$result   = Achievements::locked(
			$user_id,
			static function () use ( $wpdb, $user_id, $quote, &$order_id ) {
				$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				try {
					if ( Point::get_total_points( $user_id ) < $quote['points'] ) {
						throw new \RuntimeException( 'insufficient' );
					}
					foreach ( $quote['course_ids'] as $course_id ) {
						if ( self::holds( $user_id, $course_id ) ) {
							throw new \RuntimeException( 'enrolled' );
						}
					}
					$order_id = self::create_order( $user_id, $quote );
					if ( ! Point::deduct_points( $user_id, 'point', $quote['points'], 'purchase_course', null, null, $order_id ) ) {
						throw new \RuntimeException( 'insufficient' );
					}
					$origin = array(
						'source'      => 'purchase',
						'source_type' => 'order',
						'source_ref'  => $order_id,
						'created_by'  => (int) $user_id,
					);
					foreach ( $quote['course_ids'] as $course_id ) {
						$inserted = Enrollments::create(
							array(
								'user_id'    => (int) $user_id,
								'course_id'  => (int) $course_id,
								'order_id'   => $order_id,
								'status'     => 'pending',
								'progress'   => 'running',
								'start_date' => current_time( 'mysql' ),
							),
							$origin,
							array( '%d', '%d', '%d', '%s', '%s', '%s' )
						);
						if ( ! $inserted ) {
							throw new \RuntimeException( 'enrolment' );
						}
					}
					$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					return 'ok';
				} catch ( \Throwable $e ) {
					$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					if ( $order_id ) {
						clean_post_cache( $order_id );
					}
					return $e instanceof \RuntimeException ? $e->getMessage() : 'failed';
				}
			}
		);

		if ( 'ok' !== $result ) {
			if ( 'insufficient' === $result ) {
				return self::insufficient( array_merge( $quote, array( 'balance' => Point::get_total_points( $user_id ) ) ) );
			}
			if ( 'enrolled' === $result ) {
				return self::error( 'ohmylms_points_already_enrolled', __( 'You are already enrolled in this course.', 'ohmylms' ), 409 );
			}
			if ( false === $result ) {
				return self::error( 'ohmylms_points_busy', __( 'Another points purchase is in progress. Try again in a moment.', 'ohmylms' ), 409 );
			}
			return self::error( 'ohmylms_points_purchase_failed', __( 'The purchase could not be saved. No points were spent.', 'ohmylms' ), 500 );
		}

		$order = ohmylms_ecommerce_get_order( $order_id );
		if ( ! $order || ! $order->payment_complete( 'points-' . $order_id ) ) {
			self::undo( $user_id, $order_id );
			return self::error( 'ohmylms_points_purchase_failed', __( 'The purchase could not be completed. Your points were restored.', 'ohmylms' ), 500 );
		}
		$order->add_order_note(
			sprintf(
				/* translators: %d: points. */
				__( 'Paid with %d bonus points.', 'ohmylms' ),
				$quote['points']
			)
		);

		/**
		 * Fires after courses were bought with points and the order completed.
		 *
		 * @param int   $order_id   Order ID.
		 * @param int   $user_id    Learner.
		 * @param int[] $course_ids Courses.
		 * @param int   $points     Points spent.
		 */
		do_action( 'ohmylms_point_purchase_completed', $order_id, (int) $user_id, $quote['course_ids'], $quote['points'] );

		return array(
			'order_id'   => $order_id,
			'course_ids' => $quote['course_ids'],
			'points'     => $quote['points'],
			'balance'    => Point::get_total_points( $user_id ),
		);
	}

	/**
	 * Balance error.
	 *
	 * @param array $quote Quote.
	 * @return WP_Error
	 */
	private static function insufficient( array $quote ) {
		return self::error(
			'ohmylms_points_insufficient',
			__( 'You do not have enough points to purchase this course.', 'ohmylms' ),
			409,
			array(
				'points'  => $quote['points'],
				'balance' => $quote['balance'],
			)
		);
	}

	/**
	 * Create the pending points order (inside the purchase transaction).
	 *
	 * @param int   $user_id Learner.
	 * @param array $quote   Quote.
	 * @return int Order ID.
	 * @throws \RuntimeException When the order cannot be saved.
	 */
	private static function create_order( $user_id, array $quote ) {
		$order = new \OhMyLMS\Commerce\Data\Order();
		$order->set_student_id( (int) $user_id );
		$order->set_currency( get_ohmylms_currency() );
		$order->set_payment_method( self::PAYMENT_METHOD );
		$order->set_payment_method_title( __( 'Bonus points', 'ohmylms' ) );
		$order->set_total( 0 );
		$order->set_order_version( defined( 'OHMYLMS_VERSION' ) ? OHMYLMS_VERSION : '' );
		foreach ( $quote['course_ids'] as $course_id ) {
			$item = new \OhMyLMS\Commerce\Data\OrderItemCourse();
			$item->set_props(
				array(
					'name'      => get_the_title( $course_id ),
					'course_id' => (int) $course_id,
					'quantity'  => 1,
					'subtotal'  => 0,
					'total'     => 0,
				)
			);
			$order->add_item( $item );
		}
		$order_id = (int) $order->save();
		if ( ! $order_id ) {
			throw new \RuntimeException( 'order' );
		}
		update_post_meta( $order_id, '_purchased_by', 'point' );
		update_post_meta( $order_id, '_purchased_point', (int) $quote['points'] );
		update_post_meta( $order_id, '_purchased_point_prices', array_map( 'intval', $quote['prices'] ) );
		return $order_id;
	}

	/**
	 * Undo a committed purchase whose order could not be completed.
	 *
	 * @param int $user_id  Learner.
	 * @param int $order_id Order.
	 * @return void
	 */
	private static function undo( $user_id, $order_id ) {
		Point::refund_purchase( $user_id, $order_id );
		Enrollments::update( array( 'status' => 'cancelled' ), array( 'order_id' => (int) $order_id ), null, array( '%s' ), array( '%d' ) );
		$order = ohmylms_ecommerce_get_order( $order_id );
		if ( $order ) {
			$order->set_status( 'failed', __( 'Points purchase could not be completed; points restored.', 'ohmylms' ) );
			$order->save();
		}
	}

	/**
	 * Points spent and refunded for an order, or null when it was not paid with points.
	 *
	 * @param int $order_id Order.
	 * @return array|null `{points, refunded, refunded_at, refundable}`.
	 */
	public static function summary( $order_id ) {
		$order_id = (int) $order_id;
		if ( 'point' !== get_post_meta( $order_id, '_purchased_by', true ) ) {
			return null;
		}
		$refunded_at = (string) get_post_meta( $order_id, '_points_refunded_at', true );
		$status      = str_replace( 'ohmylms-', '', (string) get_post_status( $order_id ) );
		return array(
			'points'      => (int) get_post_meta( $order_id, '_purchased_point', true ),
			'refunded'    => '' !== $refunded_at,
			'refunded_at' => $refunded_at ? str_replace( ' ', 'T', $refunded_at ) : null,
			'refundable'  => '' === $refunded_at && in_array( $status, array( 'completed', 'processing' ), true ),
		);
	}

	/**
	 * Refund a points order: restore the points once, cancel its enrolments, mark it refunded.
	 *
	 * @param int    $order_id Order.
	 * @param string $reason   Plain-text reason.
	 * @param int    $actor    User who refunds (0 = system).
	 * @return array|WP_Error Summary after the refund.
	 */
	public static function refund( $order_id, $reason, $actor = 0 ) {
		global $wpdb;
		$order_id = (int) $order_id;
		$order    = ohmylms_ecommerce_get_order( $order_id );
		if ( ! $order || 'ohmylms-order' !== get_post_type( $order_id ) || 'trash' === get_post_status( $order_id ) ) {
			return self::error( 'ohmylms_order_not_found', __( 'Order not found.', 'ohmylms' ), 404 );
		}
		$summary = self::summary( $order_id );
		if ( null === $summary ) {
			return self::error( 'ohmylms_points_not_point_order', __( 'This order was not paid with points.', 'ohmylms' ), 409 );
		}
		$reason = sanitize_textarea_field( (string) $reason );
		if ( '' === trim( $reason ) || mb_strlen( $reason ) > 500 ) {
			return self::error( 'ohmylms_points_refund_reason', __( 'Give a reason of 1 to 500 characters.', 'ohmylms' ), 422, array( 'field' => 'reason' ) );
		}

		$lock = 'ohmylms_lock_point-refund-' . $order_id;
		if ( ! add_option( $lock, time(), '', 'no' ) ) {
			$taken = (int) get_option( $lock );
			if ( ! $taken || time() - $taken <= 60 ) {
				return self::error( 'ohmylms_points_refund_in_progress', __( 'A refund for this order is already being processed.', 'ohmylms' ), 409 );
			}
			update_option( $lock, time(), 'no' );
		}
		try {
			clean_post_cache( $order_id );
			$summary = self::summary( $order_id );
			if ( $summary['refunded'] ) {
				return self::error( 'ohmylms_points_already_refunded', __( 'The points of this order were already refunded.', 'ohmylms' ), 409 );
			}
			if ( ! $summary['refundable'] ) {
				return self::error( 'ohmylms_refund_order_status', __( 'Only completed points orders can be refunded.', 'ohmylms' ), 409 );
			}
			$user_id = (int) $order->get_student_id();

			$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			try {
				if ( ! Point::refund_purchase( $user_id, $order_id ) ) {
					throw new \RuntimeException( 'points' );
				}
				Enrollments::update( array( 'status' => 'cancelled' ), array( 'order_id' => $order_id ), null, array( '%s' ), array( '%d' ) );
				update_post_meta( $order_id, '_points_refunded_at', current_time( 'mysql' ) );
				update_post_meta( $order_id, '_points_refund_reason', $reason );
				update_post_meta( $order_id, '_points_refunded_by', (int) $actor );
				$order->set_status( 'refunded' );
				if ( ! $order->save() || 'refunded' !== str_replace( 'ohmylms-', '', (string) get_post_status( $order_id ) ) ) {
					throw new \RuntimeException( 'order' );
				}
				$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			} catch ( \Throwable $e ) {
				$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				clean_post_cache( $order_id );
				return self::error( 'ohmylms_refund_failed', __( 'The points could not be refunded. Nothing was changed.', 'ohmylms' ), 500 );
			}
			clean_post_cache( $order_id );
			$order = ohmylms_ecommerce_get_order( $order_id );
			$order->add_order_note(
				sprintf(
					/* translators: 1: points, 2: reason. */
					__( 'Refunded %1$d bonus points. Reason: %2$s', 'ohmylms' ),
					$summary['points'],
					$reason
				),
				0,
				true
			);

			/**
			 * Fires after a points order was refunded.
			 *
			 * @param int $order_id Order ID.
			 * @param int $user_id  Learner.
			 * @param int $points   Points restored.
			 */
			do_action( 'ohmylms_point_purchase_refunded', $order_id, $user_id, $summary['points'] );

			return array_merge(
				self::summary( $order_id ),
				array( 'balance' => Point::get_total_points( $user_id ) )
			);
		} finally {
			delete_option( $lock );
		}
	}
}
