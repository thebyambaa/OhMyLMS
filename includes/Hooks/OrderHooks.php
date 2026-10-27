<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Hooks;

use OhMyLMS\Abstracts\HookHandler;

/**
 * Plugin order hooks implementation.
 */
class OrderHooks extends HookHandler {

	/**
	 * Register hooks for Order hooks.
	 */
	public function register_hooks() {
		add_action( 'ohmylms_order_refunded', array( $this, 'cancel_student_enrollment' ), 10, 2 );
		add_action( 'ohmylms_rest_delete_order', array( $this, 'rest_delete_order' ), 10 );
		add_action( 'ohmylms_update_order_status_to_completed', array( $this, 'after_payment_completed' ), 10 );
		add_action( 'ohmylms_payment_completed', array( $this, 'after_payment_completed' ), 10 );
	}


	/**
	 * Cancel student enrollment when an order is refunded.
	 *
	 * @param \OhMyLMS\Commerce\Data\OrderRefund $refund The refund object.
	 * @param \OhMyLMS\Commerce\Data\Order       $order The order object.
	 * .
	 * @since 1.0.0
	 */
	public function cancel_student_enrollment( $refund, $order ) {
		if ( $order->get_meta( '_ohmylms_refund_processed' ) ) {
			return;
		}

		$order_total    = ohmylms_format_decimal( $order->get_total(), ohmylms_get_price_decimals() );
		$total_refunded = ohmylms_format_decimal( abs( $refund->get_total() ), ohmylms_get_price_decimals() );
		$is_full_refund = ( $total_refunded === $order_total );

		if ( ! $is_full_refund ) {
			$accumulated = 0;
			foreach ( $order->get_refunds() as $single_refund ) {
				$accumulated += (float) ohmylms_format_decimal(
					get_post_meta( $single_refund->ID, '_refund_amount', true ),
					ohmylms_get_price_decimals()
				);
			}
			$is_full_refund = ( ohmylms_format_decimal( $accumulated, ohmylms_get_price_decimals() ) === $order_total );
		}

		if ( ! $is_full_refund ) {
			return;
		}

		$order->update_meta_data( '_ohmylms_refund_processed', 1 );
		$order->save();

		try {
			$refund->cancel_student_enrollment( $order );

			$subscription_id = get_post_meta( $order->get_id(), '_subscription_id', true );
			$subscription_id = $subscription_id ? $subscription_id : get_post_meta( $order->get_id(), '_subscription_renewal_id', true );

			if ( $subscription_id ) {
				$subscription_id = absint( $subscription_id );
				$subscription    = ohmylms_ecommerce_get_subscription( $subscription_id );
				if ( $subscription ) {
					\OhMyLMS\Commerce\SubscriptionManager::mark_subscription_cancelled( $subscription_id );
				}
			}
		} catch ( \Exception $e ) {
			$order->delete_meta_data( '_ohmylms_refund_processed' );
			$order->save();
			error_log( 'OhMyLMS: refund cancellation failed for order #' . $order->get_id() . ': ' . $e->getMessage() );
		}
	}


	/**
	 * Delete order.
	 *
	 * @param int $order_id The order ID.
	 * .
	 * @since 1.0.0
	 */
	public function rest_delete_order( $order_id ) {
		global $wpdb;
		// Get all order items for this order.
		$order_items = $wpdb->get_col(
			$wpdb->prepare( 'SELECT order_item_id FROM %i WHERE order_id = %d', $wpdb->prefix . 'ohmylms_order_items', $order_id )
		);

		if ( ! empty( $order_items ) ) {
			// Delete order item meta.
			$wpdb->query(
				"DELETE FROM {$wpdb->prefix}ohmylms_order_itemmeta WHERE order_item_id IN (" . implode( ',', array_map( 'absint', $order_items ) ) . ')'
			);

			// Delete order items.
			$wpdb->delete(
				$wpdb->prefix . 'ohmylms_order_items',
				array( 'order_id' => $order_id ),
				array( '%d' )
			);
		}

		$subscription_id = get_post_meta( $order_id, '_subscription_id', true );
		$subscription_id = $subscription_id ? $subscription_id : get_post_meta( $order_id, '_subscription_renewal_id', true );
		if ( $subscription_id ) {
			$subscription_id = absint( $subscription_id );
			$subscription    = ohmylms_ecommerce_get_subscription( $subscription_id );

			// Validate subscription existence and post type.
			if ( ! $subscription ) {
				return;
			}
			// Mark the subscription as cancelled.
			\OhMyLMS\Commerce\SubscriptionManager::mark_subscription_cancelled( $subscription_id );
		}
	}


	/**
	 * After payment completed.
	 *
	 * @param mixed $order Order.
	 * @since 1.0.0
	 */ public function after_payment_completed( $order ) {
		if ( ! is_object( $order ) ) {
			$order_id = absint( $order );
			$order    = ohmylms_ecommerce_get_order( $order_id );
		}

		if ( ! is_object( $order ) ) {
			return;
		}

		// Check any pending enrollment for this order.
		// To check pending enrollment, we will check in ohmylms_user_enrollment table with status 'pending' for this order id.
		global $wpdb;
		$table_name  = $wpdb->prefix . 'ohmylms_user_enrollment';
		$enroll_data = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE order_id = %d AND status = %s', $table_name, $order->get_id(), 'pending' ), ARRAY_A );
		if ( ! empty( $enroll_data ) ) {
			foreach ( $enroll_data as $enroll ) {
				$rows = $wpdb->update(
					$table_name,
					array( 'status' => 'enrolled' ),
					array(
						'id'     => $enroll['id'],
						'status' => 'pending',
					),
					array( '%s' ),
					array( '%d', '%s' )
				);
				if ( $rows > 0 ) {
					do_action( 'ohmylms_after_enrolled_student', $order->get_id(), $enroll['user_id'] );
				}
			}
		}

		// for ohmylms_user_membership as well, check pending membership for this order.
		if ( ! ohmylms_is_pro() ) {
			return;
		}
		$table_name      = $wpdb->prefix . 'ohmylms_user_membership';
		$membership_data = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE order_id = %d AND status = %s', $table_name, $order->get_id(), 'pending' ), ARRAY_A );
		if ( ! empty( $membership_data ) ) {
			// update the status to 'enrolled' for each membership using query.
			foreach ( $membership_data as $membership ) {
				$student_id               = $membership['user_id'];
				$membership_data_instance = new \OhMyLMS\DataStores\StudentStore();
				$membership_data_instance->update_membership_enrollment_status( $student_id, $order->get_id(), 'enrolled' );
			}
		}
}
}
