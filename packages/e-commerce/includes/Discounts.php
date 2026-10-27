<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Commerce;

/**
 * Plugin discounts implementation.
 */
class Discounts {

	/**
	 * Items.
	 *
	 * @var mixed
	 */
	public $items = array();

	/**
	 * Discounts.
	 *
	 * @var mixed
	 */
	public $discounts = array();

	/**
	 * Object.
	 *
	 * @var mixed
	 */
	public $object;

	/**
	 * Constructor for the Discount class.
	 *
	 * @param mixed $object Object.
	 */
	public function __construct( $object = null ) {
		$this->set_items_from_cart( $object );
	}

	/**
	 * Set items from the cart.
	 *
	 * @param mixed $cart Cart.
	 */
	public function set_items_from_cart( $cart ) {
		$this->items     = array();
		$this->discounts = array();

		$this->object = $cart;

		foreach ( $cart->get_cart() as $key => $cart_item ) {
			if ( empty( $cart_item['data'] ) || ! is_object( $cart_item['data'] ) ) {
				continue;
			}

			// Additional check to ensure the course object has the get_price method.
			if ( ! method_exists( $cart_item['data'], 'get_price' ) ) {
				continue;
			}

			$item                = new \stdClass();
			$item->key           = $key;
			$item->object        = $cart_item;
			$item->course        = $cart_item['data'];
			$item->quantity      = $cart_item['quantity'];
			$item->price         = apply_filters( 'ohmylms_cart_item_price', $item->course->get_price(), $item, $cart_item, $cart );
			$this->items[ $key ] = $item;
		}
	}

	/**
	 * Apply coupon for Discounts.
	 *
	 * @param mixed $coupon Coupon.
	 */
	public function apply_coupon( $coupon ) {

		$coupon_code = $coupon->get_code();
		if ( ! isset( $this->discounts[ $coupon_code ] ) || ! is_array( $this->discounts[ $coupon_code ] ) ) {
			$this->discounts[ $coupon_code ] = array_fill_keys( array_keys( $this->items ), 0 );
		}
		$items_to_apply = $this->get_items_to_apply_coupon( $coupon );

		if ( ! $this->validate_coupon( $coupon, $items_to_apply ) ) {
			return false;
		}

		switch ( $coupon->get_discount_type() ) {
			case 'percent':
				$this->apply_coupon_percent( $coupon, $items_to_apply );
				break;
			case 'fixed_product':
				$this->apply_coupon_fixed_product( $coupon, $items_to_apply );
				break;
			case 'flat-rate':
				$this->apply_coupon_fixed_cart( $coupon, $items_to_apply );
				break;
			default:
				$this->apply_coupon_custom( $coupon, $items_to_apply );
				break;
		}

		return true;
	}

	/**
	 * Get items to apply coupon for Discounts.
	 *
	 * @param mixed $coupon Coupon.
	 */
	protected function get_items_to_apply_coupon( $coupon ) {
		$items_to_apply = array();

		foreach ( $this->items as $item ) {
			$item_to_apply = clone $item;
			if ( 0 === $this->get_discounted_price_in_cents( $item_to_apply ) || 0 >= $item_to_apply->quantity ) {
				continue;
			}
			$items_to_apply[] = $item_to_apply;
		}

		return $items_to_apply;
	}

	/**
	 * Get discounted price in cents for Discounts.
	 *
	 * @param mixed $item Item.
	 */
	protected function get_discounted_price_in_cents( $item ) {
		return absint( round( $item->price - $this->get_discount( $item->key, true ) ) );
	}

	/**
	 * Get discount for Discounts.
	 *
	 * @param mixed $key Lookup key.
	 * @param bool  $in_cents In cents.
	 */
	public function get_discount( $key, $in_cents = false ) {
		$item_discount_totals = $this->get_discounts_by_item( $in_cents );
		return isset( $item_discount_totals[ $key ] ) ? $item_discount_totals[ $key ] : 0;
	}

	/**
	 * Get discounts by item for Discounts.
	 *
	 * @param bool $in_cents In cents.
	 */
	public function get_discounts_by_item( $in_cents = false ) {
		$discounts            = $this->discounts;
		$item_discount_totals = (array) array_shift( $discounts );
		foreach ( $discounts as $code => $item_discounts ) {
			foreach ( $item_discounts as $item_key => $item_discount ) {
				$item_discount_totals[ $code ] += $item_discount;
			}
		}
		return $item_discount_totals;
	}

	/**
	 * Apply coupon percent for Discounts.
	 *
	 * @param mixed $coupon Coupon.
	 * @param mixed $items_to_apply Items to apply.
	 */
	private function apply_coupon_percent( $coupon, $items_to_apply ) {
		$total_discount = 0;
		$cart_total     = 0;
		$applied_count  = 0;
		$coupon_amount  = $coupon->get_amount();

		foreach ( $items_to_apply as $item ) {
			$price_to_discount = round( $item->price );

			$apply_quantity    = $item->quantity;
			$price_to_discount = ( $price_to_discount ) * $apply_quantity;
			$discount          = ( $price_to_discount * ( $coupon_amount / 100 ) );
			$cart_total        = $cart_total + $price_to_discount;
			$total_discount    = $total_discount + $discount;
			$applied_count     = $applied_count + $apply_quantity;
			$this->discounts[ $coupon->get_code() ][ $item->key ] += $discount;
		}
		return $total_discount;
	}

	/**
	 * Apply coupon fixed product for Discounts.
	 *
	 * @param mixed $coupon Coupon.
	 * @param array $items_to_apply Items to apply.
	 */
	private function apply_coupon_fixed_product( $coupon, array $items_to_apply ) {}

	/**
	 * Apply coupon fixed cart for Discounts.
	 *
	 * @param mixed $coupon Coupon.
	 * @param array $items_to_apply Items to apply.
	 */
	private function apply_coupon_fixed_cart( $coupon, array $items_to_apply ) {
		$total_discount = 0;
		$cart_total     = 0;
		$applied_count  = 0;
		$coupon_amount  = $coupon->get_amount();
		foreach ( $items_to_apply as $item ) {
			$discounted_price  = $this->get_discounted_price_in_cents( $item );
			$price_to_discount = ( $item->price );

			$apply_quantity    = $item->quantity;
			$price_to_discount = ( $price_to_discount / $item->quantity ) * $apply_quantity;

			$discount       = $coupon_amount;
			$discount       = min( $discounted_price, $discount );
			$cart_total     = $cart_total + $price_to_discount;
			$total_discount = $total_discount + $discount;
			$applied_count  = $applied_count + $apply_quantity;
			$this->discounts[ $coupon->get_code() ][ $item->key ] += $discount;
		}

		$cart_total_discount = $coupon_amount;
		return $cart_total_discount;
	}

	/**
	 * Apply coupon custom for Discounts.
	 *
	 * @param mixed $coupon Coupon.
	 * @param array $items_to_apply Items to apply.
	 */
	private function apply_coupon_custom( $coupon, array $items_to_apply ) {}

	/**
	 * Validate coupon
	 *
	 * @param object $coupon Coupon.
	 * @param array  $items_to_apply Items to apply.
	 *
	 * @return array
	 *
	 * @since 1.0.0
	 */
	private function validate_coupon( $coupon, $items_to_apply ) {

		if ( ! $coupon instanceof \OhMyLMS\Commerce\Data\Coupon ) {
			return false;
		}

		if ( ! $coupon->get_discount_type( 'percent' ) && ! $coupon->get_discount_type( 'fixed_product' ) && ! $coupon->get_discount_type( 'fixed_cart' ) && ! $coupon->get_discount_type( 'custom' ) ) {
			return false;
		}

		$date_expires = $coupon->get_date_expires();
		if ( $date_expires ) {
			// Get current time in the coupon's timezone.
			$current_time = new \DateTime( 'now', $date_expires->getTimezone() );
			if ( $current_time > $date_expires ) {
				// Coupon is expired.
				return false;
			}
		}

		$date_start = $coupon->get_date_start();
		if ( $date_start ) {
			// Get current time in the coupon's timezone.
			$current_time = new \DateTime( 'now', $date_start->getTimezone() );
			if ( $current_time < $date_start ) {
				return false;
			}
		}

		$uses_count = $coupon->get_usage_count();
		$uses_limit = $coupon->get_usage_limit();

		// Check uses limit.
		if ( $uses_limit && $uses_limit <= $uses_count ) {
			return false;
		}

		$uses_limit_per_user = $coupon->get_usage_limit_per_user();
		if ( $uses_limit_per_user && is_user_logged_in() ) {
			$current_user_id       = get_current_user_id();
			$current_uses_per_user = get_post_meta( $coupon->get_id(), 'usage_count_' . $current_user_id, true );
			if ( $uses_limit_per_user <= $current_uses_per_user ) {
				return false;
			}
		}

		// Check course ids are in checkout or not.
		$course_id_type = $coupon->get_course_id_type();
		if ( 'selected_course' === $course_id_type ) {
			$course_ids = $coupon->get_course_ids();
			if ( is_array( $course_ids ) && ! empty( $course_ids ) ) {
				$should_apply = true;
				foreach ( $items_to_apply as $item ) {
					if ( ! in_array( $item->object['course_id'], $course_ids, true ) ) {
						$should_apply = false;
						break;
					}
				}
				return $should_apply;
			}
		}

		return true;
	}
}
