<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Commerce\Data;

/**
 * Plugin order item coupon implementation.
 */
class OrderItemCoupon extends OrderItem {

	/**
	 * Order Data array. This is the core order data exposed in APIs since 3.0.0.
	 *
	 * @since 3.0.0
	 * @var array
	 */
	protected array $extra_data = array(
		'code'     => '',
		'discount' => 0,
	);

	/**
	 * Get name for Order item coupon.
	 */
	public function get_name() {
		return $this->get_prop( 'name' );
	}

	/**
	 * Set discount for Order item coupon.
	 *
	 * @param mixed $discount Discount.
	 */
	public function set_discount( $discount ) {
		$this->set_prop( 'discount', $discount );
	}

	/**
	 * Set code for Order item coupon.
	 *
	 * @param mixed $code Code.
	 */
	public function set_code( $code ) {
		$this->set_prop( 'code', $code );
	}

	/**
	 * Get discount for Order item coupon.
	 *
	 * @param string $context Context.
	 */
	public function get_discount( $context = 'view' ) {
		return $this->get_prop( 'discount', $context );
	}

	/**
	 * Get code for Order item coupon.
	 */
	public function get_code() {
		return $this->get_prop( 'code' );
	}


	/**
	 * Get type for Order item coupon.
	 */
	public function get_type() {
		return 'coupon';
	}

	/**
	 * Set name for Order item coupon.
	 *
	 * @param mixed $name Name.
	 */
	public function set_name( $name ) {
		$this->set_prop( 'name', $name );
	}
}
