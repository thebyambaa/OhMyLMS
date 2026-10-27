<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Commerce;

use AmpProject\Validator\Spec\Tag\P;
use OhMyLMS\Commerce\Data\Coupon;
use OhMyLMS\Commerce\Includes\Tax\TaxService;

defined( 'ABSPATH' ) || exit;

// Include required tax calculation classes.
require_once __DIR__ . '/Tax/EuVatApi.php';
require_once __DIR__ . '/Tax/EuVatApiResponse.php';
require_once __DIR__ . '/Tax/TaxCalculator.php';

/**
 * Plugin cart implementation.
 */
class Cart {

	/**
	 * Contains cart contents
	 *
	 * @var array
	 */
	public $cart_contents = array();

	/**
	 * Session object
	 *
	 * @var CartSession
	 */
	public $session;

	/**
	 * Applied coupons in the cart.
	 *
	 * @var array
	 */
	public $applied_coupons = array();

	/**
	 * Total amount in the cart.
	 *
	 * @var float
	 */
	public $total = 0;

	/**
	 * Coupon discount totals for the cart.
	 *
	 * @var array
	 */
	public $coupon_discount_totals = array();


	/**
	 * Totals for the cart.
	 *
	 * @var array
	 */
	public $totals = array(
		'subtotal'            => 0,
		'discounts_total'     => 0,
		'cart_contents_total' => 0,
		'total'               => 0,
		'fees'                => 0,
		'tax_amount'          => 0,
		'tax_rate'            => 0,
	);


	/**
	 * Default totals.
	 *
	 * @var mixed
	 */
	public $default_totals = array(
		'subtotal'            => 0,
		'discounts_total'     => 0,
		'cart_contents_total' => 0,
		'total'               => 0,
		'fees'                => 0,
		'tax_amount'          => 0,
		'tax_rate'            => 0,
	);


	/**
	 * Protected property to store items.
	 *
	 * @var array
	 */
	protected $items = array();


	/**
	 * Construct.
	 *
	 * @throws \Exception When the operation cannot be completed.*/
	public function __construct() {
		$this->session = new CartSession( $this );
		$this->session->init();
		add_action( 'ohmylms_add_to_cart', array( $this, 'calculate_totals' ), 10, 0 );
		add_action( 'ohmylms_applied_coupon', array( $this, 'calculate_totals' ), 10, 0 );
		add_action( 'ohmylms_removed_coupon', array( $this, 'calculate_totals' ), 10, 0 );
	}


	/**
	 * Set the contents of the cart
	 *
	 * @param array $value - cart array.
	 * @return void
	 */
	public function set_cart_contents( $value ) {
		$this->cart_contents = (array) $value;
	}

	/**
	 * Set the coupon discount totals for the cart.
	 *
	 * @param array $value The array of coupon discount totals.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function set_coupon_discount_totals( $value ) {
		$this->coupon_discount_totals = (array) $value;
	}

	/**
	 * Get the applied coupons in the cart.
	 *
	 * @return array List of applied coupons.
	 */
	public function get_applied_coupons() {
		return $this->applied_coupons;
	}

	/**
	 * Set the applied coupons in the cart.
	 *
	 * @param array $value The array of applied coupons.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function set_applied_coupons( $value = array() ) {
		$this->applied_coupons = (array) $value;
	}

	/**
	 * Get contents of the cart
	 *
	 * @return array
	 */
	public function get_cart_contents() {
		return $this->cart_contents;
	}


	/**
	 * Get cart data
	 *
	 * @return array
	 */
	public function get_cart() {
		if ( ! did_action( 'ohmylms_load_cart_from_session' ) ) {
			$this->session->get_cart_from_session();
		}
		return array_filter( $this->get_cart_contents() );
	}



	/**
	 * Generate a unique id for cart
	 *
	 * @param int   $course_id Id of course.
	 * @param array $cart_item_data other cart item data.
	 * @return string cart item key
	 *
	 * @since 1.0.0
	 */
	public function generate_cart_id( $course_id, $cart_item_data ) {
		$id_parts = array( $course_id );

		if ( is_array( $cart_item_data ) && ! empty( $cart_item_data ) ) {
			$cart_item_data_key = '';
			foreach ( $cart_item_data as $key => $value ) {
				if ( is_array( $value ) || is_object( $value ) ) {
					$value = http_build_query( $value );
				}
				$cart_item_data_key .= trim( $key ) . trim( $value );

			}
			$id_parts[] = $cart_item_data_key;
		}

		return md5( implode( '_', $id_parts ) );
	}

	/**
	 * Get the cart subtotal.
	 *
	 * This function calculates and returns the subtotal of the cart.
	 *
	 * @return float The cart subtotal.
	 */
	public function get_cart_subtotal() {
		$cart_subtotal = ohmylms_price( $this->get_totals_by_key( 'subtotal' ) );
		return $cart_subtotal;
	}

	/**
	 * Get the discount amount for a specific coupon code.
	 *
	 * @param string $code The coupon code.
	 * @return float The discount amount for the specified coupon code.
	 *
	 * @since 1.0.0
	 */
	public function get_coupon_discount_amount( $code ) {

		foreach ( $this->coupon_discount_totals as $key => $value ) {
			if ( $value['code'] === $code ) {
				return round( $value['discount'], ohmylms_get_price_decimals() );
			}
		}
		return 0;
	}

	/**
	 * Retrieve the coupons applied to the cart.
	 *
	 * This function fetches the list of applied coupons from the cart,
	 * creates a new Coupon object for each coupon code, and returns them
	 * in an associative array where the keys are the coupon codes.
	 *
	 * @return array List of Coupon objects.
	 */
	public function get_coupons() {
		$coupons = array();
		foreach ( $this->get_applied_coupons() as $code ) {
			$coupon           = new Coupon( $code );
			$coupons[ $code ] = $coupon;
		}

		return $coupons;
	}



	/**
	 * Check if course is in the cart and return the cart item key
	 *
	 * @param mixed $cart_id id of course to find in the cart.
	 * @return string cart item key
	 *
	 * @since 1.0.0
	 */
	public function find_product_in_cart( $cart_id = false ) {
		if ( false !== $cart_id ) {
			if ( is_array( $this->cart_contents ) && isset( $this->cart_contents[ $cart_id ] ) ) {
				return $cart_id;
			}
		}
		return '';
	}


	/**
	 * Add to cart functionality of course
	 *
	 * @param mixed $course_id Course identifier.
	 * @param int   $quantity Quantity.
	 * @param array $cart_item_data Cart item data.
	 * @return bool|string
	 *
	 * @since 1.0.0
	 * @throws \Exception When the operation cannot be completed.
	 */
	public function add_to_cart( $course_id, $quantity = 1, $cart_item_data = array() ) {
		try {
			$this->empty_cart( true );
			$course_id = absint( $course_id );
			$post_type = get_post_type( $course_id );
			$course    = null;
			if ( OHMYLMS_COURSE_CPT === $post_type ) {
				$course = ohmylms_get_course( $course_id );
				if ( ! $course ) {
					throw new \Exception( __( 'Invalid course.', 'ohmylms' ) );
				}
			} elseif ( OHMYLMS_MEMBERSHIP_CPT === $post_type ) {
				$course = ohmylms_get_membership( $course_id );
				if ( ! $course ) {
					throw new \Exception( __( 'Invalid membership.', 'ohmylms' ) );
				}
			}

			if ( ! $course ) {
				return false;
			}

			$cart_id       = $this->generate_cart_id( $course_id, $cart_item_data );
			$cart_item_key = $this->find_product_in_cart( $cart_id );

			if ( ! $cart_item_key ) {
				$cart_item_key = $cart_id;
			}

			// $course = ohmylms_get_course( $course_id );

			$this->cart_contents[ $cart_item_key ] = apply_filters(
				'ohmylms_add_cart_item',
				array_merge(
					$cart_item_data,
					array(
						'key'       => $cart_item_key,
						'course_id' => $course_id,
						'type'      => $post_type,
						'quantity'  => $quantity,
						'data'      => $course,
					)
				),
				$cart_item_key
			);

			$this->cart_contents[ $cart_item_key ]['quantity'] = $quantity;
			do_action( 'ohmylms_add_to_cart', $cart_item_key, $quantity, $course_id, $cart_item_data );

			return $cart_item_key;

		} catch ( \Exception $e ) {
			if ( $e->getMessage() ) {
				ohmylms_add_notice( $e->getMessage(), 'error' );
			}
			return false;
		}
	}

	/**
	 * Calculate the totals for the cart.
	 *
	 * This function checks if the cart is empty and sets the session if it is.
	 * It triggers actions before and after calculating the totals.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function calculate_totals() {
		if ( $this->is_empty() ) {
			$this->session->set_session();
			return;
		}

		do_action( 'ohmylms_before_calculate_totals', $this );

		$this->calculate_item_totals();

		do_action( 'ohmylms_after_calculate_totals', $this );
	}


	/**
	 * Calculate item totals for the cart.
	 *
	 * This function iterates over the items in the cart, calculates the total for each item,
	 * and updates the cart contents and totals accordingly.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected function calculate_item_totals() {
		$this->get_items_from_cart();
		$this->calculate_discounts();
		$total     = 0;
		$sub_total = 0;
		foreach ( $this->items as $item_key => $item ) {
			// Set item total as full price (no discount per item).
			$item->total                                    = $item->price;
			$item->subtotal                                 = $item->subtotal;
			$item->total                                    = apply_filters( 'ohmylms_cart_item_line_total', $item->total, $item, $item_key, $this );
			$this->cart_contents[ $item_key ]['line_total'] = $item->total;
			$total     += $item->total;
			$sub_total += $item->subtotal;
		}

		// Subtract total discount from cart total.
		$discount_total = isset( $this->totals['discounts_total'] ) ? $this->totals['discounts_total'] : 0;
		$cart_total     = $total - $discount_total;
		if ( 0 > $cart_total ) {
			$cart_total = 0;
		}

		$this->totals['total']    = $cart_total;
		$this->totals['subtotal'] = $sub_total;
		$this->set_total( $cart_total );
	}

	/**
	 * Get the discounted price in cents for a specific item.
	 *
	 * @param string $item_key The key of the item.
	 * @return int The discounted price in cents.
	 *
	 * @since 1.0.0
	 */
	public function get_discounted_price_in_cents( $item_key ) {
		$item             = $this->items[ $item_key ];
		$discounted_price = isset( $this->coupon_discount_totals[ $item_key ]['discount'] ) ? $item->price - $this->coupon_discount_totals[ $item_key ]['discount'] : $item->price;
		return apply_filters( 'ohmylms_cart_discounted_price', $discounted_price, $item, $item_key, $this );
	}

	/**
	 * Calculate discounts for the cart.
	 *
	 * This function retrieves the coupons applied to the cart,
	 * creates a new Discounts object, and applies each coupon
	 * to calculate the total discounts for the cart items.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	protected function calculate_discounts() {
		$coupons   = $this->get_coupons_from_cart();
		$discounts = new Discounts( $this );
		if ( ! empty( $coupons ) ) {
			foreach ( $coupons as $coupon ) {
				$discounts->apply_coupon( $coupon );
			}
		} else {
			$discounts->discounts         = array();
			$this->coupon_discount_totals = array();
		}

		foreach ( $discounts->discounts as $code => $item_discounts ) {
			foreach ( $item_discounts as $item_key => $item_discount ) {
				$this->coupon_discount_totals[ $item_key ] = array(
					'code'     => $code,
					'discount' => $item_discount,
				);
			}
		}

		$this->set_discount_total( array_sum( array_column( $this->coupon_discount_totals, 'discount' ) ) );
	}

	/**
	 * Get the coupon discount totals for the cart.
	 *
	 * This function returns an array of coupon discount totals applied to the cart.
	 *
	 * @return array The coupon discount totals.
	 * @since 1.0.0
	 */
	public function get_coupon_discount_totals() {
		return (array) $this->coupon_discount_totals;
	}

	/**
	 * Retrieve the coupons applied to the cart.
	 *
	 * This function fetches the list of applied coupons from the cart,
	 * creates a new Coupon object for each coupon code, and sorts them
	 * based on their discount type.
	 *
	 * @return array List of Coupon objects.
	 */
	protected function get_coupons_from_cart() {
		$coupons = array();
		foreach ( $this->get_applied_coupons() as $code ) {
			$coupon           = new Coupon( $code );
			$coupons[ $code ] = $coupon;
		}
		return $coupons;
	}

	/**
	 * Check if cart is empty or not
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_empty() {
		return 0 === count( $this->get_cart() );
	}

	/**
	 * Set the discount total for the cart.
	 *
	 * @param float $value The discount total amount to set.
	 * @return void
	 */
	public function set_discount_total( $value ) {
		$this->totals['discounts_total'] = $value;
	}


	/**
	 * Return all calculated totals.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_totals() {
		return empty( $this->totals ) ? array(
			'subtotal'            => 0,
			'discounts_total'     => 0,
			'cart_contents_total' => 0,
			'total'               => 0,
			'fees'                => 0,
			'tax_amount'          => 0,
			'tax_rate'            => 0,
		) : $this->totals;
	}

	/**
	 * Get the total amount for a specific key in the cart totals.
	 *
	 * @param string $key The key to get the total for.
	 * @return float The total amount for the specified key.
	 */
	public function get_totals_by_key( $key ) {
		// Defensive access to avoid PHP notices when a totals key is missing.
		// Return 0 as a safe default when the requested key doesn't exist.
		return isset( $this->totals[ $key ] ) ? $this->totals[ $key ] : 0;
	}

	/**
	 * Set the total amount for a specific key in the cart totals.
	 *
	 * @param string $key The key to set the total for.
	 * @param float  $value The total amount to set.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function set_totals_by_key( $key, $value ) {
		$this->totals[ $key ] = $value;
	}


	/**
	 * Returns the hash based on cart contents
	 *
	 * @return mixed|null
	 * @since 1.0.0
	 */
	public function get_cart_hash() {
		$cart_session = $this->session->get_cart_for_session();
		return $cart_session ? md5( wp_json_encode( $cart_session ) ) : '';
	}


	/**
	 * Empty cart data
	 *
	 * @param bool $clear_persistent_cart Clear persistent cart.
	 */
	public function empty_cart( $clear_persistent_cart = true ) {
		do_action( 'ohmylms_before_cart_emptied', $clear_persistent_cart );

		$this->cart_contents = array();
		$this->totals        = array(
			'subtotal'            => 0,
			'discounts_total'     => 0,
			'cart_contents_total' => 0,
			'total'               => 0,
			'fees'                => 0,
		);

		if ( $clear_persistent_cart ) {
			$this->session->persistent_cart_destroy();
		}

		do_action( 'ohmylms_cart_emptied', $clear_persistent_cart );
	}


	/**
	 * Gets cart total after calculation
	 *
	 * @param string $context Context.
	 * @return mixed|void
	 * @since 1.0.0
	 */
	public function get_total( $context = 'view' ) {
		$total = $this->get_total_by_var( 'total' );
		return 'view' == $context ? ohmylms_price( $total ) : $total;
	}

	/**
	 * Get the total fees for the cart.
	 *
	 * @param string $context The context in which the total is retrieved. Default is 'view'.
	 * @return float The total fees amount.
	 *
	 * @since 1.0.0
	 */
	public function get_fees_total( $context = 'view' ) {
		return $this->get_total_by_var( 'fees' );
	}

	/**
	 * Get the total amount for a specific variable in the cart totals.
	 *
	 * @param string $var The variable name to get the total for.
	 * @return float The total amount for the specified variable.
	 */
	public function get_total_by_var( $var ) {
		// Defensive access to avoid PHP notices when a totals key is missing.
		// Return 0 as a safe default when the requested variable doesn't exist.
		return isset( $this->totals[ $var ] ) ? $this->totals[ $var ] : 0;
	}

	/**
	 * Looks at the totals to see if payment is actually required.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function needs_payment() {
		return apply_filters( 'ohmylms_cart_needs_payment', 0 < self::get_total( $this->get_cart_contents() ), $this );
	}

	/**
	 * Set the total amount in the cart.
	 *
	 * @param float $value The total amount to set.
	 * @return void
	 */
	public function set_total( $value ) {
		$this->total = ohmylms_format_decimal( $value, ohmylms_get_price_decimals() );
	}

	/**
	 * Set the total amount for a specific key in the cart totals.
	 *
	 * @param float $value The total amount to set.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function set_totals( $value ) {
		$this->totals = wp_parse_args( $value, $this->default_totals );
	}

	/**
	 * Retrieve items from the cart and populate the items property.
	 *
	 * This function iterates over the cart contents and creates an object for each item,
	 * storing relevant details such as quantity, price, and course data.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function get_items_from_cart() {
		$this->items = array();
		foreach ( $this->get_cart() as $cart_item_key => $cart_item ) {
			// Skip items with invalid data.
			if ( empty( $cart_item['data'] ) || ! is_object( $cart_item['data'] ) ) {
				continue;
			}

			$item                          = (object) array(
				'object'   => null,
				'quantity' => 0,
				'course'   => false,
				'subtotal' => 0,
				'total'    => 0,
				'price'    => 0,
			);
			$item->key                     = $cart_item_key;
			$item->object                  = $cart_item;
			$item->quantity                = $cart_item['quantity'];
			$item->price                   = $cart_item['data']->is_on_sale() && $cart_item['data']->validate_on_sale()
			? (float) $cart_item['data']->get_price() * (float) $cart_item['quantity']
			: (float) $cart_item['data']->get_regular_price() * (float) $cart_item['quantity'];
			$item->subtotal                = apply_filters(
				'ohmylms_cart_item_subtotal',
				$cart_item['data']->is_on_sale() && $cart_item['data']->validate_on_sale() ? (float) $cart_item['data']->get_price() * (float) $cart_item['quantity'] : (float) $cart_item['data']->get_regular_price() * (float) $cart_item['quantity'],
				$cart_item,
				$cart_item_key,
				$this
			);
			$item->course                  = $cart_item['data'];
			$this->items[ $cart_item_key ] = $item;
		}
	}

	/**
	 * Check if a discount is applied to the cart.
	 *
	 * @param string $coupon_code Optional. The coupon code to check. Default is an empty string.
	 * @return bool True if the discount is applied, false otherwise.
	 *
	 * @since 1.0.0
	 */
	public function has_discount( $coupon_code = '' ) {
		return $coupon_code ? in_array( ohmylms_ecommerce_format_coupon_code( $coupon_code ), $this->applied_coupons, true ) : count( $this->applied_coupons ) > 0;
	}

	/**
	 * Apply a coupon to the cart.
	 *
	 * @param string $coupon_code The coupon code to apply.
	 * @return bool True if the coupon was applied successfully, false otherwise.
	 *
	 * @since 1.0.0
	 */
	public function apply_coupon( $coupon_code ) {
		$coupon_code = ohmylms_ecommerce_format_coupon_code( $coupon_code );

		// Get the coupon.
		$the_coupon = new Coupon( $coupon_code );

		$coupon_validity = $this->validate_coupon( $the_coupon, $this->cart_contents );

		if ( isset( $coupon_validity['validity'] ) && ! $coupon_validity['validity'] ) {
			ohmylmse_add_notice( $coupon_validity['message'], 'error' );
			return false;
		}

		// Prevent adding coupons by post ID.
		if ( $the_coupon->get_code() !== $coupon_code ) {
			$the_coupon->set_code( $coupon_code );
			ohmylmse_add_notice( __( 'Invalid coupon code.', 'ohmylms' ), 'error' );
			return false;
		}

		// Check if applied.
		if ( $this->has_discount( $coupon_code ) ) {
			ohmylmse_add_notice( __( 'Coupon already applied.', 'ohmylms' ), 'error' );
			return false;
		}

		// If its individual use then remove other coupons.
		if ( $the_coupon->get_individual_use() ) {
			$coupons_to_keep = array();

			foreach ( $this->applied_coupons as $applied_coupon ) {
				$keep_key = array_search( $applied_coupon, $coupons_to_keep, true );
				if ( false === $keep_key ) {
					$this->remove_coupon( $applied_coupon );
				} else {
					unset( $coupons_to_keep[ $keep_key ] );
				}
			}

			if ( ! empty( $coupons_to_keep ) ) {
				$this->applied_coupons += $coupons_to_keep;
			}
		}

		// Check to see if an individual use coupon is set.
		if ( $this->applied_coupons ) {
			foreach ( $this->applied_coupons as $code ) {
				$coupon = new Coupon( $code );
				if ( $coupon->get_individual_use() ) {
					ohmylmse_add_notice( __( 'Coupon already applied.', 'ohmylms' ), 'error' );
					return false;
				}
			}
		}

		$this->applied_coupons[] = $coupon_code;

		do_action( 'ohmylms_applied_coupon', $coupon_code );
		// Recalculate totals including tax if tax information is available.
		$this->recalculate_totals_with_tax();
		return true;
	}


	/**
	 * Validate coupon
	 *
	 * @param object $coupon Coupon.
	 * @param array  $items_to_apply Items to apply.
	 * .
	 * @return array
	 *
	 * @since 1.0.0
	 */
	private function validate_coupon( $coupon, $items_to_apply = array() ) {

		$response = array(
			'validity' => true,
			'message'  => '',
		);

		if ( count( $this->get_applied_coupons() ) ) {
			$response['validity'] = false;
			$response['message']  = __( 'Use one coupon at a time', 'ohmylms' );
		}

		if ( ! $coupon instanceof \OhMyLMS\Commerce\Data\Coupon ) {
			$response['validity'] = false;
			$response['message']  = __( 'Invalid coupon', 'ohmylms' );
		}

		if ( ! $coupon->get_discount_type( 'percent' ) && ! $coupon->get_discount_type( 'fixed_product' ) && ! $coupon->get_discount_type( 'fixed_cart' ) && ! $coupon->get_discount_type( 'custom' ) ) {
			$response['validity'] = false;
			$response['message']  = __( 'Invalid coupon type', 'ohmylms' );
		}

		$date_expires = $coupon->get_date_expires();
		if ( $date_expires ) {
			// Get current time in the coupon's timezone.
			$current_time = new \DateTime( 'now', $date_expires->getTimezone() );
			if ( $current_time > $date_expires ) {
				// Coupon is expired.
				$response['validity'] = false;
				$response['message']  = __( 'Coupon code is expired', 'ohmylms' );
			}
		}

		// Check if coupon is valid yet.
		$date_start = $coupon->get_date_start();
		if ( $date_start ) {
			// Get current time in the coupon's timezone.
			$current_time = new \DateTime( 'now', $date_start->getTimezone() );
			if ( $current_time < $date_start ) {
				$response['validity'] = false;
				$response['message']  = __( 'Coupon code is not valid yet', 'ohmylms' );
			}
		}

		$uses_count          = $coupon->get_usage_count();
		$uses_limit          = $coupon->get_usage_limit();
		$uses_limit_per_user = $coupon->get_usage_limit_per_user();

		// Check uses limit.
		if ( $uses_limit && $uses_limit <= $uses_count ) {
			$response['validity'] = false;
			$response['message']  = __( 'Coupon limit is over', 'ohmylms' );
		}

		if ( $uses_limit_per_user && is_user_logged_in() ) {
			$current_user_id       = get_current_user_id();
			$current_uses_per_user = get_post_meta( $coupon->get_id(), 'usage_count_' . $current_user_id, true );
			if ( $uses_limit_per_user <= $current_uses_per_user ) {
				$response['validity'] = false;
				$response['message']  = __( 'Coupon limit is over for you', 'ohmylms' );
			}
		}

		// Check course ids are in checkout or not.
		$course_id_type = $coupon->get_course_id_type();
		if ( 'selected_course' === $course_id_type ) {
			$course_ids = $coupon->get_course_ids();
			if ( is_array( $course_ids ) && ! empty( $course_ids ) && ! empty( $items_to_apply ) ) {
				$should_apply = true;
				foreach ( $items_to_apply as $item ) {
					if ( ! in_array( $item['course_id'], $course_ids, true ) ) {
						$should_apply = false;
						break;
					}
				}

				if ( ! $should_apply ) {
					$response['validity'] = false;
					$response['message']  = __( 'Coupon is not valid for this item', 'ohmylms' );
				}
			}
		}
		return $response;
	}

	/**
	 * Remove a coupon from the cart.
	 *
	 * @param string $coupon_code The coupon code to remove.
	 * @return bool True if the coupon was removed, false otherwise.
	 *
	 * @since 1.0.0
	 */
	public function remove_coupon( $coupon_code ) {
		$coupon_code = ohmylms_ecommerce_format_coupon_code( $coupon_code );
		$position    = array_search( $coupon_code, array_map( 'ohmylms_ecommerce_format_coupon_code', $this->get_applied_coupons() ), true );
		if ( false !== $position ) {
			unset( $this->applied_coupons[ $position ] );
		}

		do_action( 'ohmylms_removed_coupon', $coupon_code );
		// Recalculate totals including tax if tax information is available.
		$this->recalculate_totals_with_tax();
		return true;
	}

	/**
	 * Get the tax rate for a specific country and state.
	 *
	 * This function retrieves the tax rate for a given country and state,
	 * and applies reverse charge if applicable based on VAT number validation.
	 *
	 * @param string $country The country code.
	 * @param string $state The state code (optional).
	 * @param string $vat_number The VAT number (optional).
	 * @return bool True if the tax rate was successfully retrieved and applied.
	 *
	 * @since 1.0.0
	 */
	public function get_country_tax_rate( $country, $state = '', $vat_number = '' ) {
		$tax_rate = TaxService::get_instance()->get_country_tax_rate( $country, $state );
		if ( 0 < $tax_rate ) {
			if ( TaxService::get_instance()->is_eu_vat_enabled() && TaxService::get_instance()->is_eu_countries( $country ) ) {
				if ( ! empty( $vat_number ) ) {
					$session_data = array(
						'cart_contents' => ecommerce()->cart->get_cart_contents(),
						'vat_number'    => $vat_number,
						'country_code'  => $country,
					);

					if ( ! TaxService::get_instance()->is_vat_validation_disabled() ) {

						$response = \OhMyLMS_EU_VAT_API::check_vat( $vat_number, $country );
						if ( ! $response->is_valid() ) {
							// Invalid VAT number - continue with regular tax calculation.
							// Don't apply reverse charge for invalid VAT numbers.
							$session_data['is_valid']      = false;
							$session_data['error_message'] = $response->get_error_message();
							ecommerce()->session->set( 'ohmylms_checkout_eu_vat_number', $session_data );
							// Continue with regular tax calculation instead of reverse charge.
						} else {
							// Valid VAT number - apply reverse charge.
							$session_data['company_name']    = $response->name;
							$session_data['company_address'] = $response->address;
							$session_data['is_valid']        = $response->is_valid();
							$session_data['reverse_charged'] = true;
							ecommerce()->session->set( 'ohmylms_checkout_eu_vat_number', $session_data );
							$tax_rate = 0;
							// Apply reverse charge (no tax for valid VAT numbers).
						}
					} else {
						// VAT validation is disabled - assume valid and apply reverse charge.
						$session_data['reverse_charged'] = true;
						ecommerce()->session->set( 'ohmylms_checkout_eu_vat_number', $session_data );
						$tax_rate = 0;
						// Apply reverse charge when validation is disabled.
					}
				}
			}
			$this->calculate_totals();
			// Calculate tax based on the tax rate.
			$tax_data = \OhMyLMS_Tax_Calculator::get_instance()->calculate_tax( $tax_rate, $this->totals );

			$this->totals['total']      = $tax_data['total'];
			$this->totals['tax_amount'] = $tax_data['tax_amount'];
			$this->totals['tax_rate']   = $tax_rate;
			ecommerce()->session->set( 'cart_totals', $this->totals );
		} else {
			$this->calculate_totals();
			$total     = 0;
			$sub_total = 0;
			foreach ( $this->items as $item_key => $item ) {
				$item->total                                    = $this->get_discounted_price_in_cents( $item_key );
				$item->subtotal                                 = $item->subtotal;
				$item->total                                    = apply_filters( 'ohmylms_cart_item_line_total', $item->total, $item, $item_key, $this );
				$this->cart_contents[ $item_key ]['line_total'] = $item->total;
				$total     += $item->total;
				$sub_total += $item->subtotal;
			}

			$this->totals['total']      = $total;
			$this->totals['subtotal']   = $sub_total;
			$this->totals['tax_amount'] = 0;
			$this->totals['tax_rate']   = 0;
			$this->set_total( $total );
			ecommerce()->session->set( 'cart_totals', $this->totals );
		}

		return true;
	}

	/**
	 * Recalculate cart totals with tax if tax information is available
	 *
	 * This method checks if tax calculation data is available in the session
	 * and recalculates the cart totals including tax amounts
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function recalculate_totals_with_tax() {
		// Check if we have tax information in the session.
		$stored_totals = ecommerce()->session->get( 'cart_totals' );
		if ( ! empty( $stored_totals ) && isset( $stored_totals['tax_rate'] ) && 0 < $stored_totals['tax_rate'] ) {
			$tax_rate = $stored_totals['tax_rate'];
			$this->calculate_totals();

			// Now calculate tax based on the discounted total.
			$tax_data = \OhMyLMS_Tax_Calculator::get_instance()->calculate_tax( $tax_rate, $this->totals );

			if ( TaxService::get_instance()->prices_include_tax() ) {
				$this->totals['total'] = $this->totals['total'];
			} else {
				$this->totals['total'] = $this->totals['total'] + $tax_data['tax_amount'];
			}

			// The frontend will add tax_amount to display the final total.
			$this->totals['tax_amount'] = $tax_data['tax_amount'];
			$this->totals['tax_rate']   = $tax_rate;
			// Update session with new totals.
			ecommerce()->session->set( 'cart_totals', $this->totals );
		} else {
			$this->calculate_totals();
		}
	}
}
