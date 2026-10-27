<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Commerce\Factory;

use OhMyLMS\Commerce\Data\Order;
use OhMyLMS\Commerce\Data\OrderRefund;

/**
 * Plugin order factory implementation.
 */
class OrderFactory {

	/**
	 * Get order
	 *
	 * @param bool $order_id Order identifier.
	 * @return Order|bool
	 */
	public function get_order( $order_id = false ) {
		$order_id = $this->get_order_id( $order_id );

		if ( ! $order_id ) {
			return false;
		}

		return new Order( $order_id );
	}

	/**
	 * Get refund order for Order factory.
	 *
	 * @param bool $order_id Order identifier.
	 */
	public function get_refund_order( $order_id = false ) {
		$order_id = $this->get_order_id( $order_id );

		if ( ! $order_id ) {
			return false;
		}

		return new OrderRefund( $order_id );
	}


	/**
	 * Get course id
	 *
	 * @param mixed $order Order.
	 * @return bool|int
	 */
	private function get_order_id( $order ) {
		global $post;

		if ( false === $order && isset( $post, $post->ID ) && 'ohmylms-order' === get_post_type( $post->ID ) ) {
			return absint( $post->ID );
		} elseif ( false === $order && isset( $post, $post->ID ) && 'ohmylms_order_refund' === get_post_type( $post->ID ) ) {
			return $order;
		} elseif ( is_numeric( $order ) ) {
			return $order;
		} elseif ( $order instanceof Order ) {
			return $order->get_id();
		} elseif ( ! empty( $order->ID ) ) {
			return $order->ID;
		} else {
			return false;
		}
	}
}
