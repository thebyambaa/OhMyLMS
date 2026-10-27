<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Commerce\DataStore;

use OhMyLMS\Commerce\Abstracts\Data;

/**
 * Plugin order item coupon store implementation.
 */
class OrderItemCouponStore extends OrderItemStore {

	/**
	 * Read an order item coupon.
	 *
	 * @param Data $item The order item coupon object.
	 * @since 1.0.0
	 */
	public function read( &$item ) {
		parent::read( $item );
		$id = $item->get_id();
		$item->set_props(
			array(
				'discount' => $this->get_metadata( $id, 'discount_amount', true ),
				'code'     => $item->get_name(),
			)
		);
	}


	/**
	 * Saves an item's data to the database / item meta.
	 *
	 * @param Data $item The order item coupon object.
	 *
	 * @since 1.0.0
	 */
	public function save_item_data( &$item ) {
		$id          = $item->get_id();
		$save_values = array(
			'discount_amount' => $item->get_discount( 'edit' ),
			'_course_id'      => $id,
		);
		foreach ( $save_values as $key => $value ) {
			$this->update_metadata( $id, $key, $value );
		}
	}
}
