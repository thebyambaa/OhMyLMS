<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.
namespace OhMyLMS\Commerce\Data;

use OhMyLMS\Commerce\Abstracts\Data;
use OhMyLMS\Commerce\DataStores;

/**
 * Plugin subscription implementation.
 */
class Subscription extends Data {

	/**
	 * Data store name.
	 *
	 * @var mixed
	 */
	protected string $data_store_name = 'subscription';

	/**
	 * Object type.
	 *
	 * @var mixed
	 */
	protected string $object_type = 'subscription';

	/**
	 * Data.
	 *
	 * @var mixed
	 */
	protected array $data = array(
		'student_id'                   => null,
		'membership_id'                => null,
		'original_order_id'            => null,
		'payment_gateway_id'           => null,
		'gateway_customer_id'          => null,
		'gateway_payment_method_token' => null,
		'schedule_start_date'          => null,
		'schedule_next_payment_date'   => null,
		'schedule_end_date'            => null,
		'status'                       => null,
		'student_name'                 => null,
		'student_email'                => null,
		'student_profile'              => null,
		'trial_end'                    => '',
		'last_payment_date'            => '',
		'recurring_amount'             => '',
		'billing_period'               => '',
		'billing_interval'             => 1,
		'order_total'                  => 0,
		'order_version'                => OHMYLMS_VERSION,
	);

	/**
	 * Initialize Subscription.
	 *
	 * @param int $data Input data.
	 */
	public function __construct( $data = 0 ) {
		parent::__construct( $data );
		if ( is_int( $data ) ) {
			$this->set_id( $data );
		} elseif ( is_string( $data ) ) {
			$this->set_id( $data );
		}

		// load the data store.
		$this->data_store = DataStores::load( $this->data_store_name );

		if ( $this->get_id() > 0 ) {
			$this->data_store->read( $this );
		}
	}

	// Getters.
	/**
	 * Get student id for Subscription.
	 *
	 * @param string $context Context.
	 */
	public function get_student_id( $context = 'view' ) {
		return $this->get_prop( 'student_id', $context );
	}
	/**
	 * Get membership id for Subscription.
	 *
	 * @param string $context Context.
	 */
	public function get_membership_id( $context = 'view' ) {
		return $this->get_prop( 'membership_id', $context );
	}
	/**
	 * Get original order id for Subscription.
	 *
	 * @param string $context Context.
	 */
	public function get_original_order_id( $context = 'view' ) {
		return $this->get_prop( 'original_order_id', $context );
	}
	/**
	 * Get payment gateway id for Subscription.
	 *
	 * @param string $context Context.
	 */
	public function get_payment_gateway_id( $context = 'view' ) {
		return $this->get_prop( 'payment_gateway_id', $context );
	}
	/**
	 * Get gateway customer id for Subscription.
	 *
	 * @param string $context Context.
	 */
	public function get_gateway_customer_id( $context = 'view' ) {
		return $this->get_prop( 'gateway_customer_id', $context );
	}
	/**
	 * Get gateway payment method token for Subscription.
	 *
	 * @param string $context Context.
	 */
	public function get_gateway_payment_method_token( $context = 'view' ) {
		return $this->get_prop( 'gateway_payment_method_token', $context );
	}
	/**
	 * Get start date for Subscription.
	 *
	 * @param string $context Context.
	 */
	public function get_start_date( $context = 'view' ) {
		return $this->get_prop( 'start_date', $context );
	}
	/**
	 * Get next payment date for Subscription.
	 *
	 * @param string $context Context.
	 */
	public function get_next_payment_date( $context = 'view' ) {
		return $this->get_prop( 'next_payment_date', $context );
	}
	/**
	 * Get status for Subscription.
	 *
	 * @param string $context Context.
	 */
	public function get_status( $context = 'view' ) {
		$status = $this->get_prop( 'status', $context );
		return ! empty( $status ) ? $status : 'pending';
	}
	/**
	 * Get student name for Subscription.
	 *
	 * @param string $context Context.
	 */
	public function get_student_name( $context = 'view' ) {
		return $this->get_prop( 'student_name', $context );
	}
	/**
	 * Get student email for Subscription.
	 *
	 * @param string $context Context.
	 */
	public function get_student_email( $context = 'view' ) {
		return $this->get_prop( 'student_email', $context );
	}
	/**
	 * Get student profile for Subscription.
	 *
	 * @param string $context Context.
	 */
	public function get_student_profile( $context = 'view' ) {
		return $this->get_prop( 'student_profile', $context );
	}
	/**
	 * Get billing period for Subscription.
	 *
	 * @param string $context Context.
	 */
	public function get_billing_period( $context = 'view' ) {
		return $this->get_prop( 'billing_period', $context );
	}
	/**
	 * Get products for Subscription.
	 *
	 * @param string $context Context.
	 */
	public function get_products( $context = 'view' ) {
		return $this->get_prop( 'products', $context );
	}
	/**
	 * Get trial end date for Subscription.
	 *
	 * @param string $context Context.
	 */
	public function get_trial_end_date( $context = 'view' ) {
		return $this->get_prop( 'trial_end_date', $context );
	}
	/**
	 * Get last payment date for Subscription.
	 *
	 * @param string $context Context.
	 */
	public function get_last_payment_date( $context = 'view' ) {
		return $this->get_prop( 'last_payment_date', $context );
	}
	/**
	 * Get recurring amount for Subscription.
	 *
	 * @param string $context Context.
	 */
	public function get_recurring_amount( $context = 'view' ) {
		return $this->get_prop( 'recurring_amount', $context );
	}
	/**
	 * Get order total for Subscription.
	 *
	 * @param string $context Context.
	 */
	public function get_order_total( $context = 'view' ) {
		return $this->get_prop( 'order_total', $context );
	}
	/**
	 * Get order version for Subscription.
	 *
	 * @param string $context Context.
	 */
	public function get_order_version( $context = 'view' ) {
		return $this->get_prop( 'order_version', $context );
	}
	/**
	 * Get schedule start date for Subscription.
	 *
	 * @param string $context Context.
	 */
	public function get_schedule_start_date( $context = 'view' ) {
		return $this->get_prop( 'schedule_start_date', $context );
	}
	/**
	 * Get schedule next payment date for Subscription.
	 *
	 * @param string $context Context.
	 */
	public function get_schedule_next_payment_date( $context = 'view' ) {
		return $this->get_prop( 'schedule_next_payment_date', $context );
	}
	/**
	 * Get schedule end date for Subscription.
	 *
	 * @param string $context Context.
	 */
	public function get_schedule_end_date( $context = 'view' ) {
		return $this->get_prop( 'schedule_end_date', $context );
	}

	/**
	 * Get related orders for Subscription.
	 */
	public function get_related_orders() {
		return $this->data_store->get_related_orders( $this->get_id() );
	}

	// Setters.
	/**
	 * Set student id for Subscription.
	 *
	 * @param mixed $student_id Student identifier.
	 */
	public function set_student_id( $student_id ) {
		$this->set_prop( 'student_id', $student_id );
	}
	/**
	 * Set membership id for Subscription.
	 *
	 * @param mixed $membership_id Membership identifier.
	 */
	public function set_membership_id( $membership_id ) {
		$this->set_prop( 'membership_id', $membership_id );
	}
	/**
	 * Set original order id for Subscription.
	 *
	 * @param mixed $original_order_id Original order identifier.
	 */
	public function set_original_order_id( $original_order_id ) {
		$this->set_prop( 'original_order_id', $original_order_id );
	}
	/**
	 * Set payment gateway id for Subscription.
	 *
	 * @param mixed $payment_gateway_id Payment gateway identifier.
	 */
	public function set_payment_gateway_id( $payment_gateway_id ) {
		$this->set_prop( 'payment_gateway_id', $payment_gateway_id );
	}
	/**
	 * Set gateway customer id for Subscription.
	 *
	 * @param mixed $gateway_customer_id Gateway customer identifier.
	 */
	public function set_gateway_customer_id( $gateway_customer_id ) {
		$this->set_prop( 'gateway_customer_id', $gateway_customer_id );
	}
	/**
	 * Set gateway payment method token for Subscription.
	 *
	 * @param mixed $gateway_payment_method_token Gateway payment method token.
	 */
	public function set_gateway_payment_method_token( $gateway_payment_method_token ) {
		$this->set_prop( 'gateway_payment_method_token', $gateway_payment_method_token );
	}
	/**
	 * Set start date for Subscription.
	 *
	 * @param mixed $start_date Start date.
	 */
	public function set_start_date( $start_date ) {
		$this->set_prop( 'start_date', $start_date );
	}
	/**
	 * Set next payment date for Subscription.
	 *
	 * @param mixed $next_payment_date Next payment date.
	 */
	public function set_next_payment_date( $next_payment_date ) {
		$this->set_prop( 'next_payment_date', $next_payment_date );
	}
	/**
	 * Set status for Subscription.
	 *
	 * @param mixed $new_status New status.
	 */
	public function set_status( $new_status ) {
		$new_status = 0 === strpos( $new_status, 'ohmylms-' ) ? substr( $new_status, strlen( 'ohmylms-' ) ) : $new_status;
		$this->set_prop( 'status', $new_status );
	}
	/**
	 * Set student name for Subscription.
	 *
	 * @param mixed $status Status.
	 */
	public function set_student_name( $status ) {
		$this->set_prop( 'student_name', $status );
	}
	/**
	 * Set student email for Subscription.
	 *
	 * @param mixed $status Status.
	 */
	public function set_student_email( $status ) {
		$this->set_prop( 'student_email', $status );
	}
	/**
	 * Set student profile for Subscription.
	 *
	 * @param mixed $status Status.
	 */
	public function set_student_profile( $status ) {
		$this->set_prop( 'student_profile', $status );
	}

	/**
	 * Set billing period for Subscription.
	 *
	 * @param mixed $billing_period Billing period.
	 */
	public function set_billing_period( $billing_period ) {
		$this->set_prop( 'billing_period', $billing_period );
	}

	/**
	 * Set products for Subscription.
	 *
	 * @param mixed $products Products.
	 */
	public function set_products( $products ) {
		$this->set_prop( 'products', $products );
	}

	/**
	 * Set trial end date for Subscription.
	 *
	 * @param mixed $trial_end Trial end.
	 */
	public function set_trial_end_date( $trial_end ) {
		$this->set_prop( 'trial_end_date', $trial_end );
	}

	/**
	 * Set last payment date for Subscription.
	 *
	 * @param mixed $last_payment_date Last payment date.
	 */
	public function set_last_payment_date( $last_payment_date ) {
		$this->set_prop( 'last_payment_date', $last_payment_date );
	}

	/**
	 * Set recurring amount for Subscription.
	 *
	 * @param mixed $recurring_amount Recurring amount.
	 */
	public function set_recurring_amount( $recurring_amount ) {
		$this->set_prop( 'recurring_amount', $recurring_amount );
	}

	/**
	 * Set order total for Subscription.
	 *
	 * @param mixed $order_total Order total.
	 */
	public function set_order_total( $order_total ) {
		$this->set_prop( 'order_total', $order_total );
	}

	/**
	 * Set order version for Subscription.
	 *
	 * @param mixed $order_version Order version.
	 */
	public function set_order_version( $order_version ) {
		$this->set_prop( 'order_version', $order_version );
	}
	/**
	 * Set related orders for Subscription.
	 *
	 * @param mixed $related_orders Related orders.
	 */
	public function set_related_orders( $related_orders ) {
		$this->set_prop( 'related_orders', $related_orders );
	}
	/**
	 * Set schedule start date for Subscription.
	 *
	 * @param mixed $date Date.
	 */
	public function set_schedule_start_date( $date ) {
		$this->set_prop( 'schedule_start_date', $date );
	}
	/**
	 * Set schedule next payment date for Subscription.
	 *
	 * @param mixed $date Date.
	 */
	public function set_schedule_next_payment_date( $date ) {
		$this->set_prop( 'schedule_next_payment_date', $date );
	}
	/**
	 * Set schedule end date for Subscription.
	 *
	 * @param mixed $date Date.
	 */
	public function set_schedule_end_date( $date ) {
		$this->set_prop( 'schedule_end_date', $date );
	}
}
