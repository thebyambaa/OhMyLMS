<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Commerce;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin order implementation.
 */
class Order {

	/**
	 * Order id
	 *
	 * @var int
	 * @since 1.0.0
	 */
	public int $id = 0;

	/**
	 * Allowed statuses
	 *
	 * @var array
	 * @since 1.0.0
	 */
	public const ALLOWED_STATUSES = array( 'complete', 'pending', 'failed' );

	/**
	 * Order items
	 *
	 * @var array
	 * @since 1.0.0
	 */
	public array $items = array();

	/**
	 * Order amount
	 *
	 * @var float
	 * @since 1.0.0
	 */
	public float $amount;

	/**
	 * Order source
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $source = 'cart';

	/**
	 * Order status
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $status;

	/**
	 * Order payment method
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $payment_method;

	/**
	 * Initialize Order.
	 *
	 * @param mixed $id Id.
	 */
	public function __construct( $id = null ) {
		if ( $id ) {
			$this->id             = $id;
			$this->items          = $this->get_order_items();
			$this->source         = $this->get_order_source();
			$this->amount         = $this->get_order_amount();
			$this->status         = $this->get_order_status();
			$this->payment_method = $this->get_order_payment_method();
		}
	}

	/**
	 * Get order items.
	 *
	 * @return array|string
	 * @since 1.0.0
	 */
	public function get_order_items() {
		$items = get_post_meta( $this->id, 'ohmylms_order_items', true );
		if ( is_array( $items ) ) {
			return $items;
		}

		return ecommerce()->cart->get_cart_contents();
	}

	/**
	 * Get order amount
	 *
	 * @return float
	 * @since 1.0.0
	 */
	public function get_order_amount(): float {

		if ( 'membership' === $this->source ) {
			return number_format( $this->items['price'], 2 );
		}

		$discount = $this->get_order_discount();
		return ecommerce()->cart->get_total( $this->items, $discount );
	}

	/**
	 * Get order source
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_order_source(): string {
		$order_items = $this->items;
		if ( isset( $order_items[0]['id'] ) ) {
			return 'membership';
		}

		return 'cart';
	}

	/**
	 * Get order discount
	 *
	 * @return float
	 * @since 1.0.0
	 */
	public function get_order_discount(): float {
		return (float) get_post_meta( $this->id, 'ohmylms_order_discount', true );
	}

	/**
	 * Create new order
	 *
	 * @param mixed $cart_data Cart data.
	 * @param mixed $posted_data Posted data.
	 * @return int
	 * @since 1.0.0
	 */
	public static function create_order( $cart_data, $posted_data ): int {

		$order_id = wp_insert_post(
			array(
				'post_title'  => 'Order #',
				'post_type'   => 'ohmylms-order',
				'post_status' => 'publish',
				'meta_input'  => array(),
			)
		);

		if ( is_wp_error( $order_id ) ) {
			return $order_id;
		}

		$order_title = 'Order #' . $order_id;

		wp_update_post(
			array(
				'ID'         => $order_id,
				'post_title' => $order_title,
			)
		);

		update_post_meta( $order_id, 'ohmylms_order_status', 'draft' );
		update_post_meta( $order_id, 'ohmylms_order_number', $order_title );
		update_post_meta( $order_id, 'ohmylms_order_items', $cart_data );
		update_post_meta( $order_id, 'ohmylms_order_discount', 0 );
		update_post_meta( $order_id, 'ohmylms_order_payment_method', $posted_data['payment_method'] );
		update_post_meta( $order_id, 'ohmylms_order_owner', get_current_user_id() );

		( new Order( $order_id ) )->save_billing_details( $posted_data );

		return $order_id;
	}

	/**
	 *
	 * Save billing details
	 *
	 * @param mixed $posted_data Posted data.
	 * @return void
	 * @since 1.0.0
	 */
	private function save_billing_details( $posted_data ): void {
		foreach ( $posted_data as $index => $billing_field ) {
			$this->update_order_meta( 'ohmylms_billing_' . $index, $billing_field );
		}
	}

	/**
	 * Change order status
	 *
	 * @param mixed $status Status.
	 * @return array|null
	 * @since 1.0.0
	 */
	public function update_order_status( $status ) {
		if ( ! in_array( $status, self::ALLOWED_STATUSES, true ) ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Invalid order status', 'ohmylms' ),
			);
		}

		update_post_meta( $this->id, 'ohmylms_order_status', $status );
		return array(
			'status'  => 'success',
			'message' => __( 'Order status updated', 'ohmylms' ),
		);
	}

	/**
	 * Get order status
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_order_status() {
		return get_post_meta( $this->id, 'ohmylms_order_status', true );
	}

	/**
	 * Get order payment method
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_order_payment_method() {
		return get_post_meta( $this->id, 'ohmylms_order_payment_method', true );
	}

	/**
	 * Update order meta
	 *
	 * @param mixed $key Lookup key.
	 * @param mixed $value Value to process.
	 * @return void
	 * @since 1.0.0
	 */
	public function update_order_meta( $key, $value ) {
		update_post_meta( $this->id, $key, $value );
	}

	/**
	 * Get order meta
	 *
	 * @param mixed $key Lookup key.
	 * @param bool  $single Single.
	 * @return mixed
	 * @since 1.0.0
	 */
	public function get_order_meta( $key, bool $single = true ) {
		return get_post_meta( $this->id, $key, $single );
	}

	/**
	 * Save payment details after order completion
	 *
	 * @param mixed $payment_method Payment method.
	 * @param mixed $payment_type Payment type.
	 * @param mixed $payment_details Payment details.
	 * @return void
	 * @since 1.0.0
	 */
	public function save_order_payment( $payment_method, $payment_type, $payment_details ) {
		$this->update_order_meta( 'ohmylms_order_payment_method', $payment_method );
		$this->update_order_meta( 'ohmylms_order_payment_type', $payment_type );
		$this->update_order_meta( 'ohmylms_order_payment_details', $payment_details );
	}
}
