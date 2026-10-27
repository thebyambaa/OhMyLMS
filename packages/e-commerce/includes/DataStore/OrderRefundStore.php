<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Commerce\DataStore;

/**
 * Plugin order refund store implementation.
 */
class OrderRefundStore extends OrderStore {

	/**
	 * Update the post meta for the order.
	 *
	 * @param \OhMyLMS\Commerce\Data\OrderRefund $order The order object.
	 */
	protected function update_post_meta( &$order ) {
		parent::update_post_meta( $order );

		$order->update_meta_data( '_refund_amount', $order->get_amount() );
		$order->update_meta_data( '_refunded_by', $order->get_refunded_by() );
		$order->update_meta_data( '_refunded_payment', $order->get_refunded_payment() );
		$order->update_meta_data( '_refund_reason', $order->get_reason() );
		$order->update_meta_data( '_cancel_enrollment', $order->get_cancel_enrollment() );
	}

	/**
	 * Get the post title for the order.
	 *
	 * This method generates a formatted title for the order post.
	 *
	 * @return string The formatted post title.
	 *
	 * @since 1.0.0
	 */
	protected function get_post_title() {
		return sprintf( /* translators: %s: text value. */
		__( 'Refund &ndash; %s', 'ohmylms' ), ( new \DateTime( 'now' ) )->format( _x( 'M d, Y @ h:i A', 'Order date', 'ohmylms' ) ) );
	}

	/**
	 * Cancel the student enrollment for a given refund and order.
	 *
	 * @param \OhMyLMS\Commerce\Data\OrderRefund $refund The refund object.
	 * @param \OhMyLMS\Commerce\Data\Order       $order The order object.
	 *
	 * @since 1.0.0
	 */
	public function cancel_student_enrollment( $refund, $order ) {
		global $wpdb;
		$order_id        = $order->get_id();
		$parent_order_id = wp_get_post_parent_id( $order_id );
		if ( $parent_order_id ) {
			$order_id = $parent_order_id;
		}

		$table_name = $wpdb->prefix . 'ohmylms_user_enrollment';
		$wpdb->update(
			$table_name,
			array(
				'status' => 'cancelled',
			),
			array(
				'order_id' => $order_id,
			)
		);
		if ( ohmylms_is_pro() ) {
			$table_name = $wpdb->prefix . 'ohmylms_user_membership';
			$wpdb->update(
				$table_name,
				array(
					'status' => 'cancelled',
				),
				array(
					'order_id' => $order_id,
				)
			);
		}
	}
}
