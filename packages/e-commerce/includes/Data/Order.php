<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Commerce\Data;

use OhMyLMS\Commerce\Abstracts\Data;
use OhMyLMS\Commerce\DataStores;
use OhMyLMS\Commerce\EcommerceDateTime;
use OhMyLMS\Commerce\Includes\Tax\TaxService;

use function OhMyLMS\Commerce\ecommerce;

/**
 * Plugin order implementation.
 */
class Order extends Data {

	/**
	 * Items associated with the order.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected $items = array();

	/**
	 * Name of the store
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected string $data_store_name = 'order';


	/**
	 * Status transition.
	 *
	 * @var mixed
	 */
	public $status_transition = array();


	/**
	 * Object type
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $object_type = 'order';

	/**
	 * Data associated with the order.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected array $data = array(
		'total'                => 0,
		'status'               => '',
		'order_key'            => '',
		'date_created'         => null,
		'date_completed'       => null,
		'date_paid'            => null,
		'date_modified'        => null,
		'payment_method'       => '',
		'payment_method_title' => '',
		'currency'             => '',
		'cart_hash'            => '',
		'student_id'           => 0,
		'cart_discount'        => 0,
		'transaction_id'       => '',
		'email'                => '',
		'first_name'           => '',
		'last_name'            => '',
		'address'              => '',
		'country'              => '',
		'city'                 => '',
		'postcode'             => '',
		'state'                => '',
		'phone'                => '',
		'vat_number'           => '',
		'parent_id'            => 0,
		'related_orders'       => array(),
		'order_version'        => OHMYLMS_VERSION,
		'subscription_id'      => 0,
		'line_items'           => array(),
		'tax_amount'           => 0,
		'tax_rate'             => 0,
	);

	/**
	 * Order constructor.
	 *
	 * Initializes the order object. If an order ID or an order object is provided, it sets the ID.
	 * Loads the data store and reads the order data if the ID is valid.
	 *
	 * @param int|self|object|string $order The order ID, order object, or an empty string.
	 * @since 1.0.0
	 */
	public function __construct( $order = '' ) {
		parent::__construct( $order );
		if ( is_numeric( $order ) && $order > 0 ) {
			$this->set_id( $order );
		} elseif ( $order instanceof self ) {
			$this->set_id( absint( $order->get_id() ) );
		} elseif ( ! empty( $order->ID ) ) {
			$this->set_id( absint( $order->ID ) );
		}

		// load the data store.
		$this->data_store = DataStores::load( $this->data_store_name );

		if ( $this->get_id() > 0 ) {
			$this->data_store->read( $this );
		}
	}

	/**
	 * Save the order and its items.
	 *
	 * This function saves the order data and then saves the associated items.
	 *
	 * @since 1.0.0
	 */
	public function save() {
		parent::save();
		$this->save_items();
		$this->status_transition();
		return $this->get_id();
	}

	/**
	 * Save the items associated with the order.
	 *
	 * This function iterates through the items to delete and removes them.
	 * It then iterates through the items to add or update, sets the order ID for each item,
	 * saves the item, and updates the items array if the item ID has changed.
	 *
	 * @since 1.0.0
	 */
	public function save_items() {
		foreach ( $this->items as $item_group => $items ) {
			if ( is_array( $items ) ) {
				$items = array_filter( $items );
				foreach ( $items as $item_key => $item ) {
					$item->set_order_id( $this->get_id() );
					$item_id = $item->save();
					if ( $item_id !== $item_key ) {
						$this->items[ $item_group ][ $item_id ] = $item;
						unset( $this->items[ $item_group ][ $item_key ] );
					}
				}
			}
		}
	}

	/**
	 * Handle the status transition of the order.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected function status_transition() {
		$status_transition       = $this->status_transition;
		$this->status_transition = false;
		if ( ! $this->get_status() || ! $status_transition ) {
			return;
		}
		try {
			do_action( 'ohmylms_order_status_' . $status_transition['to'], $this->get_id(), $this, $status_transition );
			if ( ! empty( $status_transition['from'] ) ) {
				$transition_note = sprintf( /* translators: %1$s: text value; %2$s: text value. */
				__( 'Order status changed from %1$s to %2$s.', 'ohmylms' ), $status_transition['from'], $status_transition['to'] );
				$this->add_order_note( $transition_note );
				do_action( 'ohmylms_order_status_' . $status_transition['from'] . '_to_' . $status_transition['to'], $this->get_id(), $this );
				do_action( 'ohmylms_order_status_changed', $this->get_id(), $status_transition['from'], $status_transition['to'], $this );
			} else {
				$transition_note = sprintf( /* translators: %s: text value. */
				__( 'Order status set to %s.', 'ohmylms' ), $status_transition['to'] );
				$this->add_order_note( $transition_note );
			}
		} catch ( \Exception $e ) {
			$this->add_order_note( __( 'Error during status transition.', 'ohmylms' ) . ' ' . $e->getMessage() );
		}
	}

	/**
	 * Check if the order needs payment.
	 *
	 * This function determines if the order requires payment by checking if the total amount is greater than 0.
	 *
	 * @return bool True if payment is needed, false otherwise.
	 * @since 1.0.0
	 */
	public function needs_payment() {
		// if the total is greater than 0, then payment is needed.
		if ( $this->get_total() > 0 ) {
			return true;
		}
		return false;
	}

	/**
	 * Mark the order as complete and trigger the necessary actions.
	 *
	 * This function sets the order status to 'processing' or 'completed' based on whether the order needs processing.
	 * It also sets the date paid if it is not already set, saves the order, and triggers the 'woocommerce_payment_complete' action.
	 *
	 * @return bool True if the payment is successfully marked as complete, false otherwise.
	 * @since 1.0.0
	 * @param string $transaction_id Transaction identifier.
	 */
	public function payment_complete( $transaction_id = '' ) {
		if ( ! $this->get_id() ) { // Order must exist.
			return false;
		}

		try {
			if ( ecommerce()->session ) {
				ecommerce()->session->set( 'order_awaiting_payment', false );
			}

			if ( ! $this->get_date_paid( 'edit' ) ) {
				$this->set_date_paid( current_time( 'mysql' ) );

			}

			$this->set_status( 'completed' );
			$this->set_transaction_id( $transaction_id );
			$this->add_order_note( 'Payment completed' );

			$this->save();
			do_action( 'ohmylms_payment_completed', $this->get_id() );
		} catch ( \Exception $e ) {
			// TODO: Add the error at Log file
			// TODO: Add order note with the error message.
			return false;
		}
		return true;
	}

	/*
	|--------------------------------------------------------------------------
	| Getters
	|--------------------------------------------------------------------------
	*/

	/**
	 * Get the total amount of the order.
	 *
	 * This function retrieves the total amount for the order.
	 *
	 * @return float The total amount of the order.
	 * @since 1.0.0
	 * @param string $context Context.
	 */
	public function get_total( $context = 'view' ) {
		return $this->get_prop( 'total', $context );
	}

	/**
	 * Get the order key.
	 *
	 * This function retrieves the order key, which is used to identify the order.
	 *
	 * @param string $context The context for retrieving the order key. Default is 'view'.
	 * @return string The order key.
	 * @since 1.0.0
	 */
	public function get_order_key( $context = 'view' ) {
		return $this->get_prop( 'order_key', $context );
	}

	/**
	 * Get the total discount for the order.
	 *
	 * @return float The total discount amount.
	 * @since 1.0.0
	 * @param string $context Context.
	 */
	public function get_cart_discount( $context = 'view' ) {
		return $this->get_prop( 'cart_discount', $context );
	}

	/**
	 * Get the tax amount for the order.
	 *
	 * This function retrieves the total tax amount for the order.
	 *
	 * @param string $context The context for retrieving the tax amount. Default is 'view'.
	 * @return float The total tax amount.
	 * @since 1.0.0
	 */
	public function get_tax_amount( $context = 'view' ) {
		return $this->get_prop( 'tax_amount', $context );
	}

	/**
	 * Get the tax rate for the order.
	 *
	 * This function retrieves the tax rate applied to the order.
	 *
	 * @param string $context The context for retrieving the tax rate. Default is 'view'.
	 * @return float The tax rate.
	 * @since 1.0.0
	 */
	public function get_tax_rate( $context = 'view' ) {
		return $this->get_prop( 'tax_rate', $context );
	}

	/**
	 * Get the order number.
	 *
	 * This function retrieves the order number, which is the same as the order ID.
	 *
	 * @return int The order number.
	 * @since 1.0.0
	 */
	public function get_order_number() {
		return $this->get_id();
	}

	/**
	 * Get related orders for Order.
	 */
	public function get_related_orders() {
		return $this->data_store->get_related_orders( $this );
	}


	/**
	 * Generates a URL for the thanks page (order received).
	 *
	 * @return string The URL for the order received page.
	 * @since 1.0.0
	 */
	public function get_checkout_redirect_url() {
		$checkout_page_id   = get_option( 'ohmylms_checkout_page_id' );
		$checkout_page_url  = get_permalink( $checkout_page_id );
		$order_received_url = ohmylms_ecommerce_get_endpoint_url( 'ohmylms-order-received', $this->get_id(), $checkout_page_url );
		$order_received_url = add_query_arg( 'key', $this->get_order_key(), $order_received_url );
		return $order_received_url;
	}

	/**
	 * Get the date the order was paid.
	 *
	 * This function retrieves the date the order was paid.
	 *
	 * @param string $context The context for retrieving the date paid. Default is 'view'.
	 * @return string|null The date the order was paid, or null if not paid.
	 * @since 1.0.0
	 */
	public function get_date_paid( $context = 'view' ) {
		return $this->get_prop( 'date_paid', $context );
	}

	/**
	 * Get the currency for the order.
	 *
	 * This function retrieves the currency for the order.
	 *
	 * @return string The currency code.
	 * @since 1.0.0
	 */
	public function get_currency() {
		// The order keeps the currency it was priced in; the store setting is only a fallback.
		$currency = $this->get_prop( 'currency' );
		return $currency ? $currency : get_ohmylms_currency();
	}

	/**
	 * Get the date the or
	 * r was created.
	 *
	 * This function retrieves the date the order was created.
	 *
	 * @return EcommerceDateTime The date the order was created, or null if not set.
	 * @since 1.0.0
	 */
	public function get_date_created() {
		return $this->get_prop( 'date_created' );
	}

	/**
	 * Get the billing email for the order.
	 *
	 * @param string $context The context for retrieving the billing email. Default is 'view'.
	 * @return string|null The billing email, or null if not set.
	 * @since 1.0.0
	 */
	public function get_billing_email( $context = 'view' ) {
		return $this->get_prop( '_billing_email', $context );
	}

	/**
	 * Get the total amount of the order.
	 *
	 * @param string $context The context for retrieving the order total. Default is 'view'.
	 * @return float The total amount of the order.
	 * @since 1.0.0
	 */
	public function get_order_total( $context = 'view' ) {
		return $this->get_prop( 'total', $context );
	}

	/**
	 * Get the total refunded amount for the order.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_formatted_order_total() {
		$order_total     = $this->get_total();
		$total_refunded  = $this->get_total_refunded();
		$formatted_total = ohmylms_price( $order_total, array( 'currency' => $this->get_currency() ) );

		if ( $total_refunded ) {
			$formatted_total = '<del aria-hidden="true">' . wp_strip_all_tags( $formatted_total ) . '</del> <ins>' . ohmylms_price( $order_total - $total_refunded, array( 'currency' => $this->get_currency() ) ) . '</ins>';
		}

		return $formatted_total;
	}

	/**
	 * Get the payment method for the order.
	 *
	 * This function retrieves the payment method used for the order.
	 *
	 * @return string The payment method.
	 * @since 1.0.0
	 */
	public function get_payment_method() {
		return $this->get_prop( 'payment_method' );
	}

	/**
	 * Get the payment method title for the order.
	 *
	 * This function retrieves the payment method title used for the order.
	 *
	 * @return string The payment method title.
	 * @since 1.0.0
	 */
	public function get_payment_method_title() {
		return $this->get_prop( 'payment_method_title' );
	}

	/**
	 * Get the cart hash for the order.
	 *
	 * This function retrieves the cart hash associated with the order.
	 *
	 * @return string The cart hash.
	 * @since 1.0.0
	 */
	public function get_cart_hash() {
		return $this->get_prop( 'cart_hash' );
	}

	/**
	 * Get order version for Order.
	 *
	 * @param string $context Context.
	 */
	public function get_order_version( $context = 'view' ) {
		return $this->get_prop( 'order_version', $context );
	}

	/**
	 * Get the student ID associated with the order.
	 *
	 * @return int The student ID.
	 * @since 1.0.0
	 */
	public function get_student_id() {
		return $this->get_prop( 'student_id' );
	}

	/**
	 * Get the full name of the student associated with the order.
	 *
	 * This function concatenates the first name and last name of the student.
	 *
	 * @return string The full name of the student.
	 * @since 1.0.0
	 */
	public function get_student_name() {
		return $this->get_first_name() . ' ' . $this->get_last_name();
	}

	/**
	 * Get the student's profile image.
	 *
	 * This function retrieves the profile image of the student associated with the order.
	 * If the profile image is not set, it returns the initials of the student's name.
	 *
	 * @return string The URL of the profile image or the initials of the student's name.
	 * @since 1.0.0
	 */
	public function get_student_image() {
		$student_id    = $this->get_student_id();
		$profile_image = get_user_meta( $student_id, 'profile_image', true );
		if ( ! empty( $profile_image ) ) {
			return $profile_image;
		}
		return null;
	}


	/**
	 * Get the URL to the student's profile page.
	 *
	 * This function constructs the URL to the student's profile page in the admin area.
	 *
	 * @since 1.0.0
	 */
	public function get_student_profile_url() {
		$student_id = $this->get_student_id();
		if ( $student_id ) {
			return admin_url( 'admin.php?page=ohmylms#/students/' . $student_id . '/report' );
		}
		return '';
	}


	/**
	 * Get the transaction ID for the order.
	 *
	 * This function retrieves the transaction ID associated with the order.
	 *
	 * @return string The transaction ID.
	 * @since 1.0.0
	 */
	public function get_transaction_id() {
		return $this->get_prop( 'transaction_id' );
	}

	/**
	 * Get post type
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	public function get_post_type() {
		return 'ohmylms-order';
	}

	/**
	 * Get the parent ID for the order.
	 *
	 * @param string $context The context for retrieving the parent ID. Default is 'view'.
	 * @return int The parent ID.
	 *
	 * @since 1.0.0
	 */
	public function get_parent_id( $context = 'view' ) {
		return $this->get_prop( 'parent_id', $context );
	}

	/**
	 * Get the subscription ID associated with the order.
	 *
	 * @param string $context The context for retrieving the subscription ID. Default is 'view'.
	 * @return int The subscription ID, or 0 if not set.
	 * @since 1.0.0
	 */
	public function get_subscription_id( $context = 'view' ) {
		return $this->get_prop( 'subscription_id', $context );
	}

	/**
	 * Get the user associated with the order.
	 *
	 * This function retrieves the user object associated with the order.
	 *
	 * @return \WP_User|false The user object if found, false otherwise.
	 * @since 1.0.0
	 */
	public function get_user() {
		return $this->get_user_id() ? get_user_by( 'id', $this->get_user_id() ) : false;
	}

	/**
	 * Get the user ID associated with the order.
	 *
	 * @param string $context The context for retrieving the user ID. Default is 'view'.
	 * @return int The user ID.
	 * @since 1.0.0
	 */
	public function get_user_id( $context = 'view' ) {
		return $this->get_student_id( $context );
	}

	/**
	 * Get the status of the order.
	 *
	 * This function retrieves the status of the order. If the status is not set, it defaults to 'pending'.
	 *
	 * @param string $context The context for retrieving the status. Default is 'view'.
	 * @return string The status of the order.
	 *
	 * @since 1.0.0
	 */
	public function get_status( $context = 'view' ) {
		$status = $this->get_prop( 'status', $context );
		return ! empty( $status ) ? $status : 'pending';
	}

	/**
	 * Get the email for the order.
	 *
	 * This function retrieves the email for the order.
	 *
	 * @param string $context The context for retrieving the email. Default is 'view'.
	 * @return string|null The email address, or null if not set.
	 *
	 * @since 1.0.0
	 */
	public function get_email( $context = 'view' ) {
		return $this->get_prop( 'email', $context );
	}

	/**
	 * Get the first name for the order.
	 *
	 * @param string $context The context for retrieving the first name. Default is 'view'.
	 * @return string|null The first name, or null if not set.
	 *
	 * @since 1.0.0
	 */
	public function get_first_name( $context = 'view' ) {
		return $this->get_prop( 'first_name', $context );
	}

	/**
	 * Get the last name for the order.
	 *
	 * @param string $context The context for retrieving the last name. Default is 'view'.
	 * @return string|null The last name, or null if not set.
	 *
	 * @since 1.0.0
	 */
	public function get_last_name( $context = 'view' ) {
		return $this->get_prop( 'last_name', $context );
	}

	/**
	 * Get the address for the order.
	 *
	 * @param string $context The context for retrieving the address. Default is 'view'.
	 * @return string|null The address, or null if not set.
	 *
	 * @since 1.0.0
	 */
	public function get_address( $context = 'view' ) {
		return $this->get_prop( 'address', $context );
	}


	/**
	 * Get the country for the order.
	 *
	 * @param string $context The context for retrieving the country. Default is 'view'.
	 * @return string|null The country, or null if not set.
	 *
	 * @since 1.0.0
	 */
	public function get_country( $context = 'view' ) {
		return $this->get_prop( 'country', $context );
	}

	/**
	 * Get the city for the order.
	 *
	 * @param string $context The context for retrieving the city. Default is 'view'.
	 * @return string|null The city, or null if not set.
	 *
	 * @since 1.0.0
	 */
	public function get_city( $context = 'view' ) {
		return $this->get_prop( 'city', $context );
	}

	/**
	 * Get the postcode for the order.
	 *
	 * @param string $context The context for retrieving the postcode. Default is 'view'.
	 * @return string|null The postcode, or null if not set.
	 *
	 * @since 1.0.0
	 */
	public function get_postcode( $context = 'view' ) {
		return $this->get_prop( 'postcode', $context );
	}


	/**
	 * Get the state for the order.
	 *
	 * @param string $context The context for retrieving the state. Default is 'view'.
	 * @return string|null The state, or null if not set.
	 *
	 * @since 1.0.0
	 */
	public function get_state( $context = 'view' ) {
		return $this->get_prop( 'state', $context );
	}

	/**
	 * Get the billing phone number for the order.
	 *
	 * @param string $context The context for retrieving the phone. Default is 'view'.
	 * @return string|null The phone number, or null if not set.
	 *
	 * @since 1.0.0
	 */
	public function get_phone( $context = 'view' ) {
		return $this->get_prop( 'phone', $context );
	}

	/**
	 * Alias of get_phone() for gateways that expect the billing_ prefix.
	 *
	 * @param string $context The context for retrieving the phone. Default is 'view'.
	 * @return string|null
	 *
	 * @since 1.0.0
	 */
	public function get_billing_phone( $context = 'view' ) {
		return $this->get_phone( $context );
	}

	/**
	 * Get the VAT number for the order.
	 *
	 * @param string $context The context for retrieving the VAT number. Default is 'view'.
	 * @return string|null The VAT number, or null if not set.
	 *
	 * @since 1.0.0
	 */
	public function get_vat_number( $context = 'view' ) {
		return $this->get_prop( 'vat_number', $context );
	}

	/**
	 * Get the date the order was completed.
	 *
	 * This function retrieves the date the order was completed.
	 *
	 * @param string $context The context for retrieving the date completed. Default is 'view'.
	 * @return EcommerceDateTime|null The date the order was completed, or null if not set.
	 *
	 * @since 1.0.0
	 */
	public function get_date_completed( $context = 'view' ) {
		return $this->get_prop( 'date_completed', $context );
	}


	/**
	 * Get the remaining refund amount for the order.
	 *
	 * This function calculates the remaining refund amount by subtracting the total refunded amount from the total order amount.
	 *
	 * @return float The remaining refund amount.
	 * @since 1.0.0
	 */
	public function get_remaining_refund_amount() {
		return ohmylms_format_decimal( $this->get_total() - $this->get_total_refunded(), ohmylms_get_price_decimals() );
	}

	/**
	 * Get the remaining refund items for the order.
	 *
	 * This function calculates the remaining refund items by subtracting the total refunded items from the total order items.
	 *
	 * @return int The remaining refund items.
	 * @since 1.0.0
	 */
	public function get_remaining_refund_items() {
		return absint( $this->get_item_count() - $this->get_item_count_refunded() );
	}

	/**
	 * Get the count of refunded items for the specified item type.
	 *
	 * @param string $item_type The type of items to count. Default is an empty string, which counts 'line_item' type.
	 * @return int The count of refunded items for the specified type.
	 *
	 * @since 1.0.0
	 */
	public function get_item_count_refunded( $item_type = '' ) {
		if ( empty( $item_type ) ) {
			$item_type = array( 'line_item' );
		}
		if ( ! is_array( $item_type ) ) {
			$item_type = array( $item_type );
		}
		$count = 0;

		foreach ( $this->get_refunds() as $refund ) {
			$refund = new OrderRefund( $refund->ID );
			foreach ( $refund->get_items( $item_type ) as $refunded_item ) {
				$count += abs( $refunded_item->get_quantity() );
			}
		}

		return $count;
	}

	/**
	 * Get the date the order was last modified.
	 *
	 * This function retrieves the date the order was last modified.
	 *
	 * @param string $context The context for retrieving the date modified. Default is 'view'.
	 * @return EcommerceDateTime|null The date the order was last modified, or null if not set.
	 *
	 * @since 1.0.0
	 */
	public function get_date_modified( $context = 'view' ) {
		return $this->get_prop( 'date_modified', $context );
	}


	/**
	 * Set the parent ID for the order.
	 *
	 * This function sets the parent ID for the order after validating the provided value.
	 * If the value is invalid, it triggers an error.
	 *
	 * @param int $value The parent ID to set.
	 * @since 1.0.0
	 */
	public function set_parent_id( $value ) {
		$this->set_prop( 'parent_id', absint( $value ) );
	}

	/**
	 * Set the date the order was last modified.
	 *
	 * This function sets the date the order was last modified.
	 *
	 * @param string|null $date The date to set. Default is null.
	 * @since 1.0.0
	 */
	public function set_date_modified( $date = null ) {
		$this->set_date_prop( 'date_modified', $date );
	}

	/**
	 * Get the refunds associated with the order.
	 *
	 * This function retrieves the refunds associated with the order by querying the database
	 * for posts of type 'ohmylms_order_refund' that have the current order as their parent.
	 *
	 * @return array The refunds associated with the order.
	 * @since 1.0.0
	 */
	public function get_refunds() {
		$args  = array(
			'limit'       => -1,
			'post_parent' => $this->get_id(),
			'post_type'   => 'ohmylms_order_refund',
			'post_status' => 'any',
		);
		$query = new \WP_Query( $args );
		return $query->get_posts();
	}


	/**
	 * Get the item count for the order.
	 *
	 * This function retrieves the count of items for the specified item type in the order.
	 *
	 * @param string $item_type The type of items to count. Default is an empty string, which counts 'line_item' type.
	 * @return int The count of items for the specified type.
	 *
	 * @since 1.0.0
	 */
	public function get_item_count( $item_type = '' ) {
		$items = $this->get_items( empty( $item_type ) ? 'line_item' : $item_type );
		$count = 0;

		foreach ( $items as $item ) {
			$count += $item->get_quantity();
		}

		return $count;
	}


	/**
	 * Get the total amount refunded for the order.
	 *
	 * This function retrieves the total amount that has been refunded for the order.
	 *
	 * @return float The total amount refunded.
	 * @since 1.0.0
	 */
	public function get_total_refunded() {
		return $this->data_store->get_total_refunded( $this );
	}

	/**
	 * Calculate the totals for the order.
	 *
	 * @since 1.0.0
	 */
	public function calculate_totals() {
		$cart_total = (float) $this->get_cart_total_for_order();
		$this->set_total( round( $cart_total, ohmylms_get_price_decimals() ) );
		$this->save();
		return $this->get_total();
	}

	/**
	 * Get the cart subtotal for the order.
	 *
	 * This function calculates the cart subtotal by iterating through the items in the order
	 * and summing up their subtotals.
	 *
	 * @return float The cart subtotal for the order.
	 * @since 1.0.0
	 */
	public function get_cart_total_for_order() {
		$cart_subtotal = 0;

		foreach ( $this->get_items() as $item ) {
			$cart_subtotal += $item->get_total();
		}

		return $cart_subtotal;
	}

	/*
	|--------------------------------------------------------------------------
	| Setters
	|--------------------------------------------------------------------------
	*/

	/**
	 * Set the currency for the order.
	 *
	 * This function sets the currency for the order. If no value is provided, it defaults to the system currency.
	 *
	 * @param string $value The currency code to set for the order.
	 * @since 1.0.0
	 */
	public function set_currency( $value ) {
		$this->set_prop( 'currency', $value ? $value : get_ohmylms_currency() );
	}

	/**
	 * Set the total amount of the order.
	 *
	 * This function sets the total amount for the order after formatting the value.
	 *
	 * @param float $value The total amount to set for the order.
	 * @since 1.0.0
	 */
	public function set_total( $value ) {
		$this->set_prop( 'total', ohmylms_format_decimal( $value, ohmylms_get_price_decimals() ) );
	}

	/**
	 * Set the order key.
	 *
	 * This function sets the order key, which is used to identify the order.
	 *
	 * @param string $value The order key to set.
	 * @since 1.0.0
	 */
	public function set_order_key( $value ) {
		$this->set_prop( 'order_key', $value );
	}

	/**
	 * Set the date the order was paid.
	 *
	 * This function sets the date the order was paid.
	 *
	 * @param string|null $date The date to set as the payment date. Default is null.
	 * .
	 * @since 1.0.0
	 */
	public function set_date_paid( $date = null ) {
		$this->set_date_prop( 'date_paid', $date );
	}

	/**
	 * Set the date the order was completed.
	 *
	 * @param string|null $date The date to set as the completion date. Default is null.
	 * .
	 * @since 1.0.0
	 */
	public function set_date_completed( $date = null ) {
		$this->set_date_prop( 'date_completed', $date );
	}

	/**
	 * Set the status of the order to 'completed'.
	 *
	 * This function sets the status of the order to 'completed'.
	 *
	 * @since 1.0.0
	 * @param mixed  $new_status New status.
	 * @param string $note Note.
	 * @param bool   $manual_update Manual update.
	 */
	public function set_status( $new_status, $note = '', $manual_update = false ) {
		$old_status = $this->get_status();
		$new_status = 0 === strpos( $new_status, 'ohmylms-' ) ? substr( $new_status, strlen( 'ohmylms-' ) ) : $new_status;
		$this->set_prop( 'status', $new_status );
		$this->status_transition = array(
			'from'   => ! empty( $this->status_transition['from'] ) ? $this->status_transition['from'] : $old_status,
			'to'     => $new_status,
			'note'   => $note,
			'manual' => (bool) $manual_update,
		);
		$this->maybe_set_date_paid();
		$this->maybe_set_date_completed();

		if ( $old_status ) {
			$transition_note         = sprintf( /* translators: %1$s: text value; %2$s: text value. */
			__( 'Order status changed from %1$s to %2$s.', 'ohmylms' ), $old_status, $new_status );
			$this->status_transition = array(
				'from' => ! empty( $this->status_transition['from'] ) ? $this->status_transition['from'] : $old_status,
				'to'   => $new_status,
				'note' => $transition_note,
			);
		}
	}

	/**
	 * Set the date the order was created.
	 *
	 * @param string|null $value The date to set as the creation date. Default is null.
	 * @since 1.0.0
	 */
	public function set_date_created( $value ) {
		$this->set_date_prop( 'date_created', $value );
	}

	/**
	 * Set order version for Order.
	 *
	 * @param mixed $value Value to process.
	 */
	public function set_order_version( $value ) {
		$this->set_prop( 'order_version', $value );
	}

	/**
	 * Set the payment method for the order.
	 *
	 * This function sets the payment method for the order. If an object is provided, it sets the payment method ID.
	 * If no value is provided, it defaults to an empty string.
	 *
	 * @param string|object $payment_method The payment method to set for the order.
	 * @since 1.0.0
	 */
	public function set_payment_method( $payment_method = '' ) {
		if ( is_object( $payment_method ) ) {
			$this->set_payment_method( $payment_method->id );
			$this->set_payment_method_title( $payment_method->get_title() );
		} elseif ( '' === $payment_method ) {
			$this->set_prop( 'payment_method', '' );
			$this->set_prop( 'payment_method_title', '' );
		} else {
			$this->set_prop( 'payment_method', $payment_method );
		}
	}


	/**
	 * Set the payment method title for the order.
	 *
	 * This function sets the payment method title for the order.
	 *
	 * @param string $value The payment method title to set.
	 * @since 1.0.0
	 */
	public function set_payment_method_title( $value ) {
		$this->set_prop( 'payment_method_title', $value );
	}

	/**
	 * Set the cart hash for the order.
	 *
	 * This function sets the cart hash for the order.
	 *
	 * @param string $value The cart hash to set.
	 * @since 1.0.0
	 */
	public function set_cart_hash( $value ) {
		$this->set_prop( 'cart_hash', $value );
	}

	/**
	 * Set the transaction ID for the order.
	 *
	 * @param string $value The transaction ID to set.
	 * @since 1.0.0
	 */
	public function set_transaction_id( $value ) {
		$this->set_prop( 'transaction_id', $value );
	}

	/**
	 * Set the student ID associated with the order.
	 *
	 * @param int $value The student ID to set.
	 * @since 1.0.0
	 */
	public function set_student_id( $value ) {
		$this->set_prop( 'student_id', $value );
	}

	/**
	 * Set the email for the order.
	 *
	 * This function sets the email for the order if the provided value is a valid email address.
	 *
	 * @param string $value The email address to set.
	 * .
	 * @since 1.0.0
	 */
	public function set_email( $value ) {
		if ( $value && is_email( $value ) ) {
			$this->set_prop( 'email', $value );
		}
	}

	/*
	Get the key for the items array based on the item type.
	 *
	 * This function determines the key to use for storing items in the order based on the type of the item.
	 *
	 * @param \OhMyLMS\Commerce\Data\OrderItem $item The item to get the key for.
	 * @return string The key for the items array.
	 * @since 1.0.0
	 */
	/**
	 * Get items key for Order.
	 *
	 * @param mixed $item Item.
	 */
	protected function get_items_key( $item ) {
		if ( is_a( $item, 'OhMyLMS\Commerce\Data\OrderItemCourse' ) ) {
			return 'line_items';
		} elseif ( is_a( $item, 'OhMyLMS\Commerce\Data\OrderItemCoupon' ) ) {
			return 'coupon_lines';
		}
		return '';
	}

	/**
	 * Add an item to the order.
	 *
	 * This function adds an item to the order by generating a temporary ID if the item does not have one.
	 * It ensures that existing items are loaded before appending the new item.
	 *
	 * @param \OhMyLMS\Commerce\Data\OrderItem $item The item to add to the order.
	 * @return bool True if the item was successfully added, false otherwise.
	 * @since 1.0.0
	 */
	public function add_item( $item ) {
		$items_key = $this->get_items_key( $item );
		if ( ! $items_key ) {
			return false;
		}
		if ( ! isset( $this->items[ $items_key ] ) ) {
			$this->items[ $items_key ] = $this->get_items( $item->get_type() );
		}
		// Set parent.
		$item->set_order_id( $this->get_id() );

		// Append new row with generated temporary ID.
		$item_id = $item->get_id();
		if ( $item_id ) {
			$this->items[ $items_key ][ $item_id ] = $item;
		} else {
			$this->items[ $items_key ][ 'new:' . $items_key . count( $this->items[ $items_key ] ) ] = $item;
		}
	}

	/**
	 * Set the first name for the order.
	 *
	 * @param string $value The first name to set.
	 * .
	 * @since 1.0.0
	 */
	public function set_first_name( $value ) {
		$this->set_prop( 'first_name', $value );
	}

	/**
	 * Set the last name for the order.
	 *
	 * @param string $value The last name to set.
	 * .
	 * @since 1.0.0
	 */
	public function set_last_name( $value ) {
		$this->set_prop( 'last_name', $value );
	}

	/**
	 * Set order items
	 *
	 * @since 1.0.0
	 * @param mixed $items Items.
	 */
	public function set_items( $items ) {
		$this->items = $items;
	}

	/**
	 * Set the address for the order.
	 *
	 * @param string $value The address to set.
	 * .
	 * @since 1.0.0
	 */
	public function set_address( $value ) {
		$this->set_prop( 'address', $value );
	}


	/**
	 * Set the country for the order.
	 *
	 * @param string $value The country to set.
	 * .
	 * @since 1.0.0
	 */
	public function set_country( $value ) {
		$this->set_prop( 'country', $value );
	}

	/**
	 * Set the city for the order.
	 *
	 * @param string $value The city to set.
	 * .
	 * @since 1.0.0
	 */
	public function set_city( $value ) {
		$this->set_prop( 'city', $value );
	}

	/**
	 * Set the postcode for the order.
	 *
	 * @param string $value The postcode to set.
	 * .
	 * @since 1.0.0
	 */
	public function set_postcode( $value ) {
		$this->set_prop( 'postcode', $value );
	}


	/**
	 * Set the state for the order.
	 *
	 * @param string $value The state to set.
	 * .
	 * @since 1.0.0
	 */
	public function set_state( $value ) {
		$this->set_prop( 'state', $value );
	}

	/**
	 * Set the billing phone number for the order.
	 *
	 * @param string $value The phone number to set.
	 * .
	 * @since 1.0.0
	 */
	public function set_phone( $value ) {
		$this->set_prop( 'phone', sanitize_text_field( $value ) );
	}

	/**
	 * Alias of set_phone() for gateways that expect the billing_ prefix.
	 *
	 * @param string $value The phone number to set.
	 * .
	 * @since 1.0.0
	 */
	public function set_billing_phone( $value ) {
		$this->set_phone( $value );
	}

	/**
	 * Set the VAT number for the order.
	 *
	 * @param string $value The VAT number to set.
	 * .
	 * @since 1.0.0
	 */
	public function set_vat_number( $value ) {
		$this->set_prop( 'vat_number', sanitize_text_field( $value ) );
	}

	/**
	 * Set the total discount for the order.
	 *
	 * This function sets the total discount amount for the order.
	 *
	 * @param float $value The total discount amount to set.
	 * .
	 * @since 1.0.0
	 */
	public function set_cart_discount( $value ) {
		$this->set_prop( 'cart_discount', ohmylms_format_decimal( $value, false, true ) );
	}

	/**
	 * Set the tax amount for the order.
	 *
	 * This function sets the tax amount for the order.
	 *
	 * @param float $value The tax amount to set.
	 * .
	 * @since 1.0.0
	 */
	public function set_tax_amount( $value ) {
		$this->set_prop( 'tax_amount', ohmylms_format_decimal( $value, false, true ) );
	}

	/**
	 * Set the tax rate for the order.
	 *
	 * This function sets the tax rate for the order.
	 *
	 * @param float $value The tax rate to set.
	 * .
	 * @since 1.0.0
	 */
	public function set_tax_rate( $value ) {
		$this->set_prop( 'tax_rate', $value );
	}

	/**
	 * Maybe set the date the order was paid.
	 *
	 * This function sets the date paid to the current time if the order status is 'completed' and the date paid is not already set.
	 *
	 * @since 1.0.0
	 */
	protected function maybe_set_date_paid() {
		if ( 'completed' === $this->get_status() && ! $this->get_date_paid( 'edit' ) ) {
			$this->set_date_paid( time() );
		}
	}

	/**
	 * Maybe set the date the order was completed.
	 *
	 * This function sets the date completed to the current time if the order status is 'completed'.
	 *
	 * @since 1.0.0
	 */
	protected function maybe_set_date_completed() {
		if ( $this->has_status( 'completed' ) ) {
			$this->set_date_completed( time() );
		}
	}

	/**
	 * Check if the order has a specific status.
	 *
	 * @param array|string $status Status to check.
	 * @return bool True if the order has the specified status, false otherwise.
	 *
	 * @since 1.0.0
	 */
	public function has_status( $status ) {
		return $this->get_status() === $status;
	}

	/*
	Get items of specified types from the order.
	 *
	 * @param array $types The types of items to retrieve. Default is ['line_item'].
	 * @return array The items of the specified types.
	 *
	 * @since 1.0.0
	 */
	/**
	 * Get items for Order.
	 *
	 * @param string $types Types.
	 */
	public function get_items( $types = 'line_item' ) {
		$items  = array();
		$types  = (array) $types;
		$groups = array(
			'line_item' => 'line_items',
			'coupon'    => 'coupon_lines',
		);

		foreach ( $types as $type ) {
			$group = $groups[ $type ];
			if ( $group ) {
				if ( ! isset( $this->items[ $group ] ) ) {
					$this->items[ $group ] = array_filter( $this->data_store->read_items( $this, $type ) );
				}
				$items = $items + $this->items[ $group ];
			}
		}
		return $items;
	}

	/**
	 * Get the formatted line subtotal for the order item.
	 *
	 * This function retrieves the formatted line subtotal for the specified order item.
	 *
	 * @param \OhMyLMS\Commerce\Data\OrderItem $item The order item to get the subtotal for.
	 * @param string                           $tax_display The tax display mode. Default is an empty string.
	 * @return string The formatted line subtotal.
	 *
	 * @since 1.0.0
	 */
	public function get_formatted_line_subtotal( $item, $tax_display = '' ) {
		return ohmylms_price( $item->get_subtotal(), array( 'currency' => $this->get_currency() ) );
	}

	/**
	 * Get the line subtotal for the order item.
	 *
	 * This function calculates the line subtotal for the specified order item.
	 *
	 * @param \OhMyLMS\Commerce\Data\OrderItem $item The order item to calculate the subtotal for.
	 * @param bool                             $inc_tax Whether to include tax in the subtotal calculation. Default is false.
	 * @param bool                             $round Whether to round the subtotal. Default is true.
	 * @return float The line subtotal for the order item.
	 *
	 * @since 1.0.0
	 */
	public function get_line_subtotal( $item, $inc_tax = false, $round = true ) {
		$subtotal = 0;
		if ( is_callable( array( $item, 'get_subtotal' ) ) ) {
			$subtotal = (float) $item->get_subtotal();
			$subtotal = $round ? round( $subtotal, ohmylms_get_price_decimals() ) : $subtotal;
		}
		return $subtotal;
	}


	/**
	 * Get cart subtotal for Order.
	 */
	public function get_cart_subtotal() {
		$items    = $this->get_items();
		$subtotal = 0;
		foreach ( $items as $item ) {
			$subtotal += $item->get_subtotal();
		}
		return $subtotal;
	}

	/**
	 * Get the cart subtotal for the order.
	 *
	 * This function calculates the cart subtotal by iterating through the items in the order
	 * and summing up their subtotals.
	 *
	 * @param array $total_rows The array of total rows to which the subtotal row will be added.
	 * @return float The cart subtotal for the order.
	 *
	 * @since 1.0.0
	 */
	protected function add_order_items_subtotal_row( &$total_rows ) {
		$subtotal = $this->get_cart_subtotal();
		if ( $subtotal ) {
			$total_rows['cart_subtotal'] = array(
				'label' => __( 'Subtotal', 'ohmylms' ),
				'value' => ohmylms_price( $subtotal, array( 'currency' => $this->get_currency() ) ),
			);
		}
		return $subtotal;
	}

	/**
	 * Add the total row to the order item totals.
	 *
	 * This function adds the total row to the array of total rows for the order.
	 *
	 * @param array $total_rows The array of total rows to which the total row will be added.
	 * @since 1.0.0
	 */
	protected function add_order_items_totals_total_row( &$total_rows ) {
		$tax_amount                = $this->get_tax_amount();
		$total_rows['order_total'] = array(
			'label' => \OhMyLMS\Commerce\Includes\Tax\TaxService::get_instance()->prices_include_tax() ? esc_html( sprintf( 'Total ( Including tax : %s )', number_format( $tax_amount, 2 ) ) ) : __( 'Total:', 'ohmylms' ),
			'value' => ohmylms_price( $this->get_formatted_order_total(), array( 'currency' => $this->get_currency() ) ),
		);
	}

	/**
	 * Add the payment method row to the order item totals.
	 *
	 * This function adds the payment method row to the array of total rows for the order.
	 *
	 * @param array $total_rows The array of total rows to which the payment method row will be added.
	 * .
	 * @since 1.0.0
	 */
	protected function add_order_items_payment_method_row( &$total_rows ) {
		$payment_method = $this->get_payment_method_title();
		if ( $payment_method ) {
			$total_rows['payment_method'] = array(
				'label' => __( 'Payment Method', 'ohmylms' ),
				'value' => $payment_method,
			);
		}
	}

	/**
	 * Add total row for discounts.
	 *
	 * @param array $total_rows Reference to total rows array.
	 * @since 1.0.0
	 */
	protected function add_order_item_totals_discount_row( &$total_rows ) {
		if ( $this->get_cart_discount() > 0 ) {
			$total_rows['discount'] = array(
				'label' => __( 'Discount', 'ohmylms' ),
				'value' => '-' . ohmylms_price( $this->get_cart_discount(), array( 'currency' => $this->get_currency() ) ),
			);
		}
	}

	/**
	 * Add tax row to the order item totals.
	 *
	 * This function adds a tax row to the array of total rows for the order.
	 *
	 * @param array $total_rows The array of total rows to which the tax row will be added.
	 * .
	 * @since 1.0.0
	 */
	protected function add_order_items_tax_row( &$total_rows ) {
		$tax_amount = $this->get_tax_amount();
		if ( $tax_amount ) {
			$tax_rate  = $this->get_tax_rate();
			$tax_label = TaxService::get_instance()->get_tax_label();

			// Add tax rate span if tax rate exists.
			if ( $tax_rate > 0 ) {
				$tax_label .= ' <span class="tax-rate">' . ( $tax_rate ) . '%</span>';
			}

			$total_rows['tax'] = array(
				'label' => $tax_label,
				'value' => ohmylms_price( $tax_amount, array( 'currency' => $this->get_currency() ) ),
			);
		}
	}

	/**
	 * Get the order item totals.
	 *
	 * This function retrieves the totals for the order items, including subtotals, taxes, and discounts.
	 *
	 * @return array The order item totals.
	 * @since 1.0.0
	 */
	public function get_order_item_totals() {
		$total_rows = array();
		$this->add_order_items_subtotal_row( $total_rows );
		$this->add_order_item_totals_discount_row( $total_rows );
		$this->add_order_items_tax_row( $total_rows );
		$this->add_order_items_totals_total_row( $total_rows );
		$this->add_order_item_order_id_row( $total_rows );
		$this->add_order_item_purchase_date_row( $total_rows );
		$this->add_order_items_payment_method_row( $total_rows );
		return $total_rows;
	}

	/**
	 * Add order item order id row for Order.
	 *
	 * @param mixed $total_rows Total rows.
	 */
	public function add_order_item_order_id_row( &$total_rows ) {
		$total_rows['order_id'] = array(
			'label' => __( 'Order ID', 'ohmylms' ),
			'value' => $this->get_id(),
		);
	}


	/**
	 * Add order item purchase date row for Order.
	 *
	 * @param mixed $total_rows Total rows.
	 */
	public function add_order_item_purchase_date_row( &$total_rows ) {
		$total_rows['purchase_date'] = array(
			'label' => __( 'Purchase date', 'ohmylms' ),
			'value' => ( new \DateTime( $this->get_date_paid( 'edit' ) ) )->format( 'd/m/Y' ),
		);
	}

	/**
	 * Add a note to the order.
	 *
	 * This function adds a note to the order. The note can be marked as a customer note
	 * and can indicate if it was added by a user.
	 *
	 * @param string $note The content of the note to add.
	 * @param int    $is_customer_note Whether the note is a customer note. Default is 0.
	 * @param bool   $added_by_user Whether the note was added by a user. Default is false.
	 * .
	 * @since 1.0.0
	 */
	public function add_order_note( $note, $is_customer_note = 0, $added_by_user = false ) {
		if ( ! $this->get_id() ) {
			return 0;
		}
		$user_id = 0;
		if ( is_user_logged_in() && current_user_can( 'edit_post', $this->get_id() ) && $added_by_user ) {
			$user                 = get_user_by( 'id', get_current_user_id() );
			$comment_author       = $user->display_name;
			$comment_author_email = $user->user_email;
			$user_id              = get_current_user_id();
		} else {
			$comment_author        = __( 'OhMyLMS', 'ohmylms' );
			$comment_author_email  = strtolower( __( 'OhMyLMS', 'ohmylms' ) ) . '@';
			$comment_author_email .= isset( $_SERVER['HTTP_HOST'] ) ? str_replace( 'www.', '', sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : 'noreply.com'; // WPCS: input var ok.
			$comment_author_email  = sanitize_email( $comment_author_email );
		}
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $this->get_id(),
				'comment_author'       => $comment_author,
				'comment_author_email' => $comment_author_email,
				'comment_author_url'   => '',
				'comment_content'      => $note,
				'comment_agent'        => 'OhMyLMS',
				'comment_type'         => 'order_note',
				'comment_parent'       => 0,
				'comment_approved'     => 1,
				'user_id'              => $user_id,
			),
		);
		if ( $is_customer_note ) {
			add_comment_meta( $comment_id, 'is_customer_note', 1 );
		}
		return $comment_id;
	}

	/**
	 * Get the customer order notes.
	 *
	 * This function retrieves the customer order notes associated with the order.
	 *
	 * @return array The customer order notes.
	 * @since 1.0.0
	 */
	public function get_customer_order_notes() {
		$notes = array();
		$args  = array(
			'post_id' => $this->get_id(),
			'approve' => 'approve',
			'type'    => '',
		);

		remove_filter( 'comments_clauses', array( 'OhMyLMS\Commerce\Comments', 'exclude_order_comments' ) );

		$comments = get_comments( $args );

		foreach ( $comments as $comment ) {
			if ( ! get_comment_meta( $comment->comment_ID, 'is_customer_note', true ) ) {
				continue;
			}
			$comment->comment_content = make_clickable( $comment->comment_content );
			$notes[]                  = $comment;
		}

		add_filter( 'comments_clauses', array( 'OhMyLMS\Commerce\Comments', 'exclude_order_comments' ) );

		return $notes;
	}

	/**
	 * Update the status of the order.
	 *
	 * @param string $new_status The new status to set for the order.
	 * @param string $note Optional. A note to add to the order. Default is an empty string.
	 * @param bool   $manual Optional. Whether the status change is manual. Default is false.
	 * @return bool True if the status was successfully updated, false otherwise.
	 *
	 * @since 1.0.0
	 */
	public function update_status( $new_status, $note = '', $manual = false ) {
		if ( ! $this->get_id() ) {
			return false;
		}
		try {
			$this->set_status( $new_status );
			$this->save();
		} catch ( \Exception $e ) {
			// Todo: Add the error at Log file.
			return false;
		}
		return true;
	}


	/**
	 * Set related orders for Order.
	 *
	 * @param mixed $related_orders Related orders.
	 */
	public function set_related_orders( $related_orders ) {
		$this->set_prop( 'related_orders', $related_orders );
	}

	/**
	 * Set the subscription ID for the order.
	 *
	 * @param int $value The subscription ID to set.
	 * @since 1.0.0
	 */
	public function set_subscription_id( $value ) {
		$this->set_prop( 'subscription_id', \absint( $value ) );
	}

	/**
	 * Check if the order is a renewal order.
	 *
	 * @return bool True if the order is a renewal order, false otherwise.
	 * @since 1.0.0
	 */
	public function is_renewal_order() {
		return $this->data_store->is_renewal_order( $this );
	}

	/**
	 * Check if the order is a parent order.
	 *
	 * @return bool True if the order is a parent order, false otherwise.
	 * @since 1.0.0
	 */
	public function is_parent_order() {
		return $this->data_store->is_parent_order( $this );
	}
}
