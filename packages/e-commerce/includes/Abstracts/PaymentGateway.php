<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.
/**
 * Class PaymentGateway.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Commerce\Abstracts;

use OhMyLMS\Abstracts\SettingsApi;
use function OhMyLMS\Commerce\ecommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Class PaymentGateway
 *
 * @package OhMyLMS\Order\Abstracts
 * @since 1.0.0
 */
abstract class PaymentGateway extends SettingsApi {

	/**
	 * Text of order button
	 *
	 * @var string $order_button_text
	 * @since 1.0.0
	 */
	public string $order_button_text;

	/**
	 * Id of the payment gateway
	 *
	 * @var string $id
	 * @since 1.0.0
	 */
	public string $id;


	/**
	 * Title of the gateway
	 *
	 * @var string $title
	 * @since 1.0.0
	 */
	public string $title;


	/**
	 * Description of the payment gateway
	 *
	 * @var string $description
	 * @since 1.0.0
	 */
	public $description;


	/**
	 * Chosen payment method id.
	 *
	 * @var bool
	 */
	public bool $chosen;


	/**
	 * True if gateway shows fields on checkout
	 *
	 * @var bool $has_fields
	 */
	public bool $has_fields;

	/**
	 * Whether the gateway is enabled.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public $enabled;

	/**
	 * Testmode.
	 *
	 * @var mixed
	 */
	public $testmode;


	/**
	 * Subscription support.
	 *
	 * @var mixed
	 */
	public $subscription_support = false;


	/**
	 * Transaction url.
	 *
	 * @var mixed
	 */
	public $transaction_url = '';


	/**
	 * Settings.
	 *
	 * @var mixed
	 */
	public array $settings;

	/**
	 * Checkout field keys this gateway needs the buyer to fill in, on top of the
	 * always-required base fields. e.g. array( 'phone' ) for a gateway whose API
	 * or fraud rules require a phone number.
	 *
	 * A gateway can set this statically, override get_required_checkout_fields(),
	 * or third parties can use the 'ohmylms_gateway_required_checkout_fields'
	 * filter — so custom gateways can make a field optional or mandatory too.
	 *
	 * @var string[]
	 * @since 1.0.0
	 */
	public array $required_checkout_fields = array();


	/**
	 * Get settings for Payment gateway.
	 */
	abstract function get_settings();


	/**
	 * Check if gateway has fields on checkout
	 *
	 * @return bool
	 */
	public function has_fields() {
		return (bool) $this->has_fields;
	}

	/**
	 * Checkout field keys that must be filled in when this gateway is selected.
	 *
	 * @return string[]
	 * @since 1.0.0
	 */
	public function get_required_checkout_fields() {
		$fields = (array) apply_filters(
			'ohmylms_gateway_required_checkout_fields',
			(array) $this->required_checkout_fields,
			isset( $this->id ) ? $this->id : '',
			$this
		);

		return array_values( array_unique( array_filter( array_map( 'strval', $fields ) ) ) );
	}

	/**
	 * Get payment gateway title
	 *
	 * @return mixed|void
	 * @since 1.0.0
	 */
	public function get_title() {
		return apply_filters( 'ohmylms_payment_gateway_title', $this->title, $this->id );
	}


	/**
	 * Get payment gateway description
	 *
	 * @return mixed|void
	 * @since 1.0.0
	 */
	public function get_description() {
		return apply_filters( 'ohmylms_gateway_description', $this->description, $this->id );
	}


	/**
	 * Init settings for Payment gateway.
	 */
	public function init_settings() {
		parent::init_settings();
	}


	/**
	 * Check if payment gateway is available
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_available() {
		$is_available = false;
		if ( ecommerce()->cart && 0 < $this->get_order_total() && 'yes' === $this->enabled ) {
			$is_available = true;
		}
		return $is_available;
	}


	/**
	 * Returns whether this gateways needs to setup
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function needs_setup() {
		return false;
	}


	/**
	 * Process Payment
	 *
	 * @param int  $order_id Order ID.
	 * @param bool $is_subscription Is subscription.
	 * @return array
	 * @since 1.0.0
	 */
	public function process_payment( $order_id, $is_subscription = false ) {
		return array();
	}


	/**
	 * Process refund
	 *
	 * @param int    $order_id Order ID.
	 * @param mixed  $amount Amount.
	 * @param string $reason Reason.
	 * @return bool
	 * @since 1.0.0
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		return false;
	}


	/**
	 * Set as current gateway.
	 *
	 * Set this as the current gateway.
	 */
	public function set_current() {
		$this->chosen = true;
	}


	/**
	 * Get order total for Payment gateway.
	 */
	protected function get_order_total() {

		$total    = 0;
		$order_id = absint( get_query_var( 'order-pay' ) );

		// Gets order total from "pay for order" page.
		if ( 0 < $order_id ) {
			$order = ohmylms_ecommerce_get_order( $order_id );
			if ( $order ) {
				$total = (float) $order->get_total();
			}

			// Gets order total from cart/checkout.
		} elseif ( 0 < ecommerce()->cart->get_total( 'edit' ) ) {
			$total = (float) ecommerce()->cart->get_total( 'edit' );
		}

		return $total;
	}

	/**
	 * Get the return URL for the order.
	 *
	 * @param \OhMyLMS\Commerce\Data\Order|null $order The order object. Default is null.
	 * @return string The return URL.
	 * @since 1.0.0
	 */
	public function get_return_url( $order = null ) {
		if ( ! $order ) {
			$order_id = absint( get_query_var( 'order-pay' ) );
			if ( 0 < $order_id ) {
				$order = ohmylms_ecommerce_get_order( $order_id );
			}
		}
		if ( ! $order || ! is_a( $order, '\OhMyLMS\Commerce\Data\Order' ) ) {
			return '';
		}
		$return_url = $order->get_checkout_redirect_url();
		return $return_url;
	}


	/**
	 * Get an option from the payment gateway settings.
	 *
	 * This function retrieves the value of a specified option key from the payment gateway settings.
	 * If the option is not set, it will initialize the settings and return the default value if provided.
	 *
	 * @param string $key The key of the option to retrieve.
	 * @param mixed  $empty_value The value to return if the option is not set. Default is null.
	 * @return mixed The value of the option, or the empty value if the option is not set.
	 *
	 * @since 1.0.0
	 */
	public function get_option( $key, $empty_value = null ) {
		if ( empty( $this->settings ) ) {
			$this->init_settings();
		}

		// Get option default if unset.
		if ( ! isset( $this->settings[ $key ] ) ) {
			$form_fields            = $this->get_form_fields();
			$this->settings[ $key ] = isset( $form_fields[ $key ] ) ? $this->get_field_default( $form_fields[ $key ] ) : '';
		}

		if ( ! is_null( $empty_value ) && '' === $this->settings[ $key ] ) {
			$this->settings[ $key ] = $empty_value;
		}

		return $this->settings[ $key ];
	}

	/**
	 * Validate fields for Payment gateway.
	 */
	public function validate_fields() {
		return true;
	}

	/**
	 * Get the transaction URL for the order.
	 *
	 * @param \OhMyLMS\Commerce\Data\Order $order The order object.
	 * @return string The transaction URL.
	 * @since 1.0.0
	 */
	public function get_transaction_url( $order ) {
		$transaction_id = $order->get_transaction_id();
		// phpcs:ignore Generic.Functions.SprintfPlaceholders.Invalid
		$return_url = sprintf( $this->transaction_url, $transaction_id );
		return $return_url;
	}

	/**
	 * Process a recurring payment for a subscription.
	 *
	 * This method is called by the Action Scheduler for automated renewals.
	 * Gateways should implement this to charge the customer using stored payment details.
	 * It should NOT handle subscription status changes or rescheduling, only the payment attempt.
	 *
	 * @param int   $original_order_id The ID of the original order that created the subscription.
	 * @param mixed $renewal_order_id Renewal order identifier.
	 * @param float $amount The amount to charge for this renewal.
	 * @param int   $subscription_id The ID of the ohmylms-subscription post.
	 * @param int   $student_id The ID of the student/user.
	 * @return array Should return an array with 'result' => 'success' or 'failure'.
	 *               On success, can optionally include 'transaction_id'.
	 *               On failure, should include 'message' with the error.
	 *               Example success: ['result' => 'success', 'transaction_id' => 'ch_123...']
	 *               Example failure: ['result' => 'failure', 'message' => 'Card declined.']
	 * @since NEXT_VERSION
	 */
	public function process_recurring_payment( $original_order_id, $renewal_order_id, $amount, $subscription_id, $student_id ) {
		return array(
			'result'  => 'failure',
			'message' => __( 'Recurring payments not implemented for this gateway.', 'ohmylms' ),
		);
	}

	/**
	 * Get payment gateway meta data for the order.
	 * This method can be overridden by payment gateways to provide additional meta data
	 *
	 * @param \OhMyLMS\Commerce\Data\Order $order The order object.
	 * @return array An associative array of meta data to be saved with the order.
	 * @since 1.0.0
	 */
	public function get_payment_gateway_meta( $order ) {
		return array();
	}


	/**
	 * Get a setting value for the payment gateway.
	 *
	 * This method retrieves a setting value using the pattern ohmylms_{gateway_id}_settings.
	 * It first checks the gateway-specific settings, then falls back to the default settings.
	 *
	 * @param string $key The setting key to retrieve.
	 * @param mixed  $default The default value if the setting is not found.
	 * @return mixed The setting value or default if not found.
	 * @since 1.0.0
	 */
	public function get_setting( $key, $default = '' ) {

		if ( isset( $this->settings[ $key ] ) ) {
			return $this->settings[ $key ];
		}
		return $default;
	}


	/**
	 * Is test mode for Payment gateway.
	 */
	public function is_test_mode() {
		return $this->testmode;
	}
}
