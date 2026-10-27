<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Commerce\Factory;

use OhMyLMS\Commerce\Data\OrderItemCourse;

/**
 * Plugin order item course factory implementation.
 */
class OrderItemCourseFactory {

	/**
	 * Get order item course
	 *
	 * @param bool $order_item_id Order item identifier.
	 * @return OrderItemCourse|bool
	 */
	public function get_order_item_course( $order_item_id = false ) {
		$order_item_id = $this->get_order_item_id( $order_item_id );

		if ( ! $order_item_id ) {
			return false;
		}

		return new OrderItemCourse( $order_item_id );
	}
}
