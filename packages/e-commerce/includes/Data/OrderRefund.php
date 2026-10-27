<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Commerce\Data;

/**
 * Plugin order refund implementation.
 */
class OrderRefund extends Order {

	/**
	 * Name of the store
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected string $data_store_name = 'order_refund';

	/**
	 * Object type
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $object_type = 'order_refund';

	/**
	 * Data for the order refund.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	public array $extra_data = array(
		'amount'            => '',
		'reason'            => '',
		'refunded_by'       => 0,
		'refunded_payment'  => 0,
		'parent_id'         => 0,
		'cancel_enrollment' => 0,
	);

	/**
	 * Set the amount.
	 *
	 * @param string $amount Amount.
	 * @since 1.0.0
	 */
	public function set_amount( $amount ) {
		$this->set_prop( 'amount', ohmylms_format_decimal( $amount ) );
	}

	/**
	 * Set the cancel enrollment status.
	 *
	 * @param bool $value Cancel enrollment status.
	 * @since 1.0.0
	 */
	public function set_cancel_enrollment( $value ) {
		$this->set_prop( 'cancel_enrollment', $value );
	}

	/**
	 * Set the reason.
	 *
	 * @param string $reason Reason.
	 * @since 1.0.0
	 */
	public function set_reason( $reason ) {
		$this->set_prop( 'reason', $reason );
	}


	/**
	 * Set the refunded by.
	 *
	 * @param int $refunded_by Refunded by.
	 * @since 1.0.0
	 */
	public function set_refunded_by( $refunded_by ) {
		$this->set_prop( 'refunded_by', $refunded_by );
	}


	/**
	 * Set the refunded payment status.
	 *
	 * @param bool $value Refunded payment status.
	 * @since 1.0.0
	 */
	public function set_refunded_payment( $value ) {
		$this->set_prop( 'refunded_payment', $value );
	}

	/**
	 * Get the amount.
	 *
	 * @return string
	 * @since 1.0.0
	 * @param string $context Context.
	 */
	public function get_amount( $context = 'view' ) {
		return $this->get_prop( 'amount', $context );
	}

	/**
	 * Get the status.
	 *
	 * @param string $context Context for how to retrieve the status.
	 * @return string Status of the order refund.
	 *
	 * @since 1.0.0
	 */
	public function get_status( $context = 'view' ) {
		return 'completed';
	}

	/**
	 * Get the reason.
	 *
	 * @return string
	 * @since 1.0.0
	 * @param string $context Context.
	 */
	public function get_reason( $context = 'view' ) {
		return $this->get_prop( 'reason', $context );
	}



	/**
	 * Get the refunded by.
	 *
	 * @return int
	 * @since 1.0.0
	 * @param string $context Context.
	 */
	public function get_refunded_by( $context = 'view' ) {
		return $this->get_prop( 'refunded_by', $context );
	}

	/**
	 * Get post type
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	public function get_post_type() {
		return 'ohmylms_order_refund';
	}

	/**
	 * Get the refunded payment status.
	 *
	 * @param string $context Context for how to retrieve the refunded payment status.
	 * @return bool Refunded payment status.
	 *
	 * @since 1.0.0
	 */
	public function get_refunded_payment( $context = 'view' ) {
		return $this->get_prop( 'refunded_payment', $context );
	}

	/**
	 * Get the cancel enrollment status.
	 *
	 * @param string $context Context for how to retrieve the cancel enrollment status.
	 * @return bool Cancel enrollment status.
	 *
	 * @since 1.0.0
	 */
	public function get_cancel_enrollment( $context = 'view' ) {
		return $this->get_prop( 'cancel_enrollment', $context );
	}

	/**
	 * Cancel the student enrollment for the given order.
	 *
	 * @param \OhMyLMS\Commerce\Data\Order $order The order object.
	 * @return void
	 * @since 1.0.0
	 */
	public function cancel_student_enrollment( $order ) {
		return $this->data_store->cancel_student_enrollment( $this, $order );
	}
}
