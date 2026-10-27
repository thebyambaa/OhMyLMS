<?php // phpcs:ignore WordPress.Files.FileName -- Retains the established public include path and Composer class mapping.
/**
 * Stripe Payment Intents Gateway.
 *
 * @package OhMyLMS
 */


use OhMyLMS\Commerce\Abstracts\PaymentGateway;
use OhMyLMS\Commerce\Gateways\Stripe\StripeApi;
use OhMyLMS\Commerce\Gateways\Stripe\Helper;
use OhMyLMS\Commerce\Gateways\Stripe\StripeCustomer;
use function OhMyLMS\Commerce\ecommerce;

if ( ! defined( 'ABSPATH' ) ) {
	wp_die(); // Exit if accessed directly.
}

/**
 * Stripe Payment Intents Gateway.
 *
 * This gateway supports dynamic payment method selection based on the current currency.
 * Payment methods are loaded from payment_method.json and filtered by currency support.
 * Users can enable/disable specific payment methods through the admin settings.
 *
 * @since TBD
 */
class OhMyLMS_Gateway_Stripe_Intents extends PaymentGateway {

	const META_INTENT_ID_TEMP      = '_stripe_intent_id_temp';
	const META_PAYMENT_INTENT_ID   = '_stripe_payment_intent_id';
	const META_CHARGE_CAPTURED     = '_stripe_charge_captured';
	const META_PAYMENT_METHOD_TYPE = '_stripe_payment_method_type';
	const META_TRANSACTION_ID      = '_transaction_id'; // Standard WP/Woo key, used for PI ID here.
	const META_CHARGE_ID           = '_stripe_charge_id';
	/**
	 * Publishable Key.
	 *
	 * @var string
	 */
	public $publishable_key;

	/**
	 * Secret Key.
	 *
	 * @var string
	 */
	private $secret_key;

	/**
	 * Supports subscription api.
	 *
	 * @var mixed
	 */
	public $supports_subscription_api = false;

	/**
	 * Constructor for the gateway.
	 *
	 * Initializes the gateway settings, API keys, and hooks.
	 */
	public function __construct() {
		$this->id                   = 'stripe';
		$gateway_settings_key       = 'ohmylms_' . $this->id . '_settings';
		$this->settings             = get_option( $gateway_settings_key, array() );
		$this->title                = $this->get_setting( 'title', __( 'Stripe', 'ohmylms' ) );
		$this->description          = $this->get_setting( 'instruction', __( 'Pay via Stripe; accepts various payment methods.', 'ohmylms' ) );
		$this->has_fields           = true;
		$this->order_button_text    = __( 'Place payment', 'ohmylms' );
		$this->enabled              = $this->get_setting( 'enabled', 'no' );
		$this->testmode             = $this->get_setting( 'test_mode', 'no' );
		$this->publishable_key      = 'yes' === $this->testmode ? $this->get_setting( 'sandbox_publishable_key', '' ) : $this->get_setting( 'publishable_key', '' );
		$this->secret_key           = 'yes' === $this->testmode ? $this->get_setting( 'sandbox_secret_key', '' ) : $this->get_setting( 'secret_key', '' );
		$this->subscription_support = true;

		// Set the secret key for the API handler class.
		StripeApi::set_secret_key( $this->secret_key );

		// Actions & Filters.
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'wp', array( $this, 'handle_stripe_return' ) );
	}


	/**
	 * Get payment gateway settings.
	 * This method returns an array of settings for the payment gateway.
	 *
	 * @return array
	 */
	public function get_settings() {
		$fields = array(
			array(
				'title'             => __( 'Title', 'ohmylms' ),
				'short_description' => __( 'Enter the title that will appear for stripe payment during checkout.', 'ohmylms' ),
				'input_type'        => 'text',
				'default_value'     => __( 'Stripe payment', 'ohmylms' ),
				'option_name'       => 'title',
				'value'             => $this->title,
			),
			array(
				'title'             => __( 'Instruction', 'ohmylms' ),
				'short_description' => __( 'Provide detailed instructions on how students should complete stripe payment.', 'ohmylms' ),
				'input_type'        => 'textarea',
				'default_value'     => __( 'Pay with stripe payment.', 'ohmylms' ),
				'option_name'       => 'instruction',
				'value'             => $this->description,
			),
			array(
				'title'             => __( 'Test Mode', 'ohmylms' ),
				'short_description' => __( 'Automatically complete orders for testing purposes without actual payment.', 'ohmylms' ),
				'input_type'        => 'switch',
				'default_value'     => $this->get_setting( 'test_mode', 'no' ),
				'option_name'       => 'test_mode',
				'value'             => $this->testmode,
				'conditional_logic' => array(
					'type'     => 'control',
					'controls' => array( 'sandbox_publishable_key', 'sandbox_secret_key', 'publishable_key', 'secret_key' ),
				),
			),
			array(
				'title'             => __( 'Sandbox Publisher Key', 'ohmylms' ),
				'short_description' => __( 'Enter your Sandbox Publisher Key.', 'ohmylms' ) . ' <a href="https://docs.stripe.com/sandboxes" target="_blank">' . __( 'How to find your Sandbox Publisher Key', 'ohmylms' ) . '</a>',
				'input_type'        => 'text',
				'default_value'     => '',
				'value'             => $this->get_setting( 'sandbox_publishable_key', '' ),
				'option_name'       => 'sandbox_publishable_key',
				'conditional_logic' => array(
					'type'       => 'dependent',
					'depends_on' => 'test_mode',
					'show_when'  => 'yes',
				),
			),
			array(
				'title'             => __( 'Sandbox Secret Key', 'ohmylms' ),
				'short_description' => __( 'Enter your Sandbox Secret Key.', 'ohmylms' ) . ' <a href="https://docs.stripe.com/sandboxes" target="_blank">' . __( 'How to find your Sandbox Secret Key', 'ohmylms' ) . '</a>',
				'input_type'        => 'text',
				'default_value'     => '',
				'value'             => $this->get_setting( 'sandbox_secret_key', '' ),
				'option_name'       => 'sandbox_secret_key',
				'conditional_logic' => array(
					'type'       => 'dependent',
					'depends_on' => 'test_mode',
					'show_when'  => 'yes',
				),
			),
			array(
				'title'             => __( 'Live Publisher Key', 'ohmylms' ),
				'short_description' => __( 'Enter your Live Publisher Key.', 'ohmylms' ) . ' <a href="https://docs.stripe.com/sandboxes" target="_blank">' . __( 'How to find your Live Publisher Key', 'ohmylms' ) . '</a>',
				'input_type'        => 'text',
				'default_value'     => '',
				'option_name'       => 'publishable_key',
				'value'             => $this->get_setting( 'publishable_key', '' ),
				'conditional_logic' => array(
					'type'       => 'dependent',
					'depends_on' => 'test_mode',
					'show_when'  => 'no',
				),
			),
			array(
				'title'             => __( 'Live Secret Key', 'ohmylms' ),
				'short_description' => __( 'Enter your Live Secret Key.', 'ohmylms' ) . ' <a href="https://docs.stripe.com/sandboxes" target="_blank">' . __( 'How to find your Live Secret Key', 'ohmylms' ) . '</a>',
				'input_type'        => 'text',
				'default_value'     => '',
				'option_name'       => 'secret_key',
				'value'             => $this->get_setting( 'secret_key', '' ),
				'conditional_logic' => array(
					'type'       => 'dependent',
					'depends_on' => 'test_mode',
					'show_when'  => 'no',
				),
			),
		);

		$payment_methods = $this->get_payment_methods();
		$currency        = $this->get_current_currency();
		$fields[]        = array(
			'title'             => __( 'Payment Methods', 'ohmylms' ),
			'short_description' => sprintf( /* translators: %s: text value. */
			__( 'Select which payment methods to enable for your store. Current currency: %s. Only methods supported by your currency are shown.', 'ohmylms' ), strtoupper( $currency ) ),
			'input_type'        => 'section_header',
			'option_name'       => 'payment_methods_section',
		);

		foreach ( $payment_methods as $method ) {
			if ( in_array( $currency, $method['supportedCurrencies'] ) ) {
				$fields[] = array(
					'title'               => $this->get_payment_method_display_name( $method ),
					'short_description'   => $method['description'],
					'input_type'          => 'checkbox',
					'default_value'       => $method['defaultSelected'] ? 'yes' : 'no',
					'option_name'         => 'payment_method_' . $method['key'],
					'value'               => $this->get_setting( 'payment_method_' . $method['key'], $method['defaultSelected'] ? 'yes' : 'no' ),
					'payment_method_key'  => $method['key'],
					'payment_method_data' => $method, // Pass the full method data for React.
				);
			}
		}

		$gateway_settings = array(
			'id'                   => 'stripe',
			'title'                => __( 'Stripe', 'ohmylms' ),
			'description'          => __( 'Stripe', 'ohmylms' ),
			'icon'                 => '<svg width="43" height="42" fill="none" viewBox="0 0 43 42" xmlns="http://www.w3.org/2000/svg" style="display: block;"><rect width="43" height="42" fill="#635BFF" rx="21"/><g clipPath="url(#clip0_2161_731)"><path fill="#fff" d="M7.688 8.25h27.625v27.625H7.688z"/><path fill="#635BFF" d="M8.75 4A4.25 4.25 0 004.5 8.25v25.5A4.25 4.25 0 008.75 38h25.5a4.25 4.25 0 004.25-4.25V8.25A4.25 4.25 0 0034.25 4H8.75zm13.23 11.443c-1.24 0-1.99.349-1.99 1.26 0 .995 1.289 1.433 2.89 1.977 2.609.881 6.043 2.046 6.058 6.36 0 4.18-3.35 6.585-8.224 6.585-2.2-.01-4.374-.462-6.394-1.33v-5.56c1.967 1.076 4.451 1.87 6.396 1.87 1.311 0 2.248-.35 2.248-1.425 0-1.1-1.398-1.604-3.087-2.212-2.572-.927-5.814-2.096-5.814-5.984 0-4.133 3.161-6.609 7.917-6.609a15.52 15.52 0 015.81 1.073v5.489c-1.78-.956-4.029-1.494-5.81-1.494z"/></g><defs><clipPath id="clip0_2161_731"><rect width="34" height="34" x="4.5" y="4" fill="#fff" rx="17"/></clipPath></defs></svg>',
			'has_config'           => true,
			'subscription_support' => true,
			'settings_fields'      => $fields,
			'enabled'              => $this->enabled,
		);

		return $gateway_settings;
	}

	/**
	 * Get payment methods from JSON file
	 *
	 * @return array
	 */
	private function get_payment_methods() {
		$json_file = __DIR__ . '/payment_method.json';

		if ( ! file_exists( $json_file ) ) {
			return array();
		}

		$json_content = file_get_contents( $json_file );
		if ( ! $json_content ) {
			return array();
		}

		$payment_methods = json_decode( $json_content, true );
		if ( ! is_array( $payment_methods ) ) {
			return array();
		}

		return $payment_methods;
	}

	/**
	 * Get current currency
	 *
	 * @return string
	 */
	private function get_current_currency() {
		return strtolower( function_exists( 'get_ohmylms_currency' ) ? get_ohmylms_currency() : 'usd' );
	}

	/**
	 * Get payment method display name with currency support
	 *
	 * @param array $method Method.
	 * @return string
	 */
	private function get_payment_method_display_name( $method ) {
		$currencies = implode( ', ', array_map( 'strtoupper', $method['supportedCurrencies'] ) );
		return $method['name'] . ' (' . $currencies . ')';
	}


	/**
	 * Output for the payment fields on the checkout page.
	 *
	 * Includes the HTML template for the Stripe Payment Element.
	 * This method is typically called by the e-commerce system during checkout.
	 *
	 * @return void
	 */
	public function payment_fields() {
		$user               = wp_get_current_user();
				$user_email = '';
		$description        = $this->get_description();
		if ( isset( $user->ID ) && $user->ID ) {
				$user_email = get_user_meta( $user->ID, 'billing_email', true );
				$user_email = $user_email ? $user_email : $user->user_email;
		}

		ob_start();
		if ( 'yes' === $this->testmode ) {
			$description .= ' ' . sprintf( /* translators: %s: text value. */
			__( 'TEST MODE ENABLED. In test mode, you can use the card number 4242424242424242 with any CVC and a valid expiration date or check the <a href="%s" target="_blank">Testing Stripe documentation</a> for more card numbers.', 'ohmylms' ), 'https://stripe.com/docs/testing' );
		}
		if ( $description ) {
			echo wpautop( wp_kses_post( $description ) );
		}

		?>

		<div id="stripe-payment-element-wrapper">
			<p>Loading payment form...</p>
			<div id="payment-element" data-email="<?php echo esc_attr( $user_email ); ?>" data-currency="<?php echo esc_attr( strtolower( function_exists( 'get_ohmylms_currency' ) ? get_ohmylms_currency() : 'usd' ) ); ?>"/>
			<div id="card-element" style="display:none;"></div>
			<div id="payment-message" class="hidden"></div>
			<div id="stripe-error-message" role="alert" style="color: red;"></div>
		</div>

		<?php
		ob_end_flush();

		do_action( 'ohmylms_stripe_payment_fields_stripe', $this->id );
	}

	/**
	 * Enqueue scripts and styles for the gateway on the frontend.
	 *
	 * This method registers and enqueues the Stripe.js V3 library and the custom
	 * `stripe-intents.js` script which handles the Payment Element and AJAX communication.
	 * It localizes parameters for the frontend script, including API keys, AJAX URLs, nonces,
	 * and flags like `is_subscription`.
	 *
	 * @global $wp Global WordPress object, potentially used for order context (commented out).
	 * @return void
	 */
	public function payment_scripts() {
		if ( ! is_ohmylms_checkout() && ! is_ohmylms_order_received_page() ) {
			return;
		}

		if ( 'no' === $this->enabled ) {
			return;
		}

		wp_register_script( 'stripe-v3', 'https://js.stripe.com/v3/', array(), null, true ); // Version is handled by Stripe.

		// Determine JS file URL and version (assuming constants are defined in the main plugin).
		$js_file_url = defined( 'OHMYLMS_URL' ) ? OHMYLMS_URL . '/packages/e-commerce/assets/js/stripe-intents.js' : plugins_url( '../../../../assets/js/stripe-intents.js', __FILE__ );
		$js_version  = defined( 'OHMYLMS_VERSION' ) ? OHMYLMS_VERSION : time(); // Use time for cache busting during dev.

		wp_register_script( 'ohmylms-stripe-intents', $js_file_url, array( 'jquery', 'stripe-v3' ), $js_version, true );

		// Prepare parameters to pass to the frontend script.
		$current_order_id = 0;

		$is_subscription_purchase = false;
		if ( $current_order_id && function_exists( 'ecommerce_order_contains_subscription' ) ) {
			$is_subscription_purchase = ecommerce_order_contains_subscription( $current_order_id );
		} elseif ( ! $current_order_id && function_exists( 'ecommerce_cart_contains_subscription' ) ) {
			$is_subscription_purchase = ecommerce_cart_contains_subscription();
		}

		$stripe_params = array(
			'publishableKey'        => $this->publishable_key,
			'ajax_url'              => admin_url( 'admin-ajax.php' ),
			'create_intent_nonce'   => wp_create_nonce( 'ohmylms_stripe_intents_create_intent_nonce' ),
			'handle_success_nonce'  => wp_create_nonce( 'ohmylms_stripe_intents_handle_success_nonce' ),
			'create_intent_action'  => 'ohmylms_stripe_intents_create_payment_intent',
			'handle_success_action' => 'ohmylms_stripe_intents_handle_payment_success',
			'order_id'              => $current_order_id, // Pass current order ID if available.
			'currency'              => strtolower( function_exists( 'get_ohmylms_currency' ) ? get_ohmylms_currency() : 'usd' ),
			'error_prefix'          => __( 'Payment error: ', 'ohmylms' ),
			'return_url'            => $this->get_return_url(),
			'is_subscription'       => $is_subscription_purchase,
			'amount_in_cents'       => Helper::get_stripe_amount( ecommerce()->cart->get_total( 'edit' ) ),
		);

		wp_localize_script( 'ohmylms-stripe-intents', 'ohmylms_stripe_intents_params', $stripe_params );
		wp_enqueue_script( 'ohmylms-stripe-intents' );
	}

	/**
	 * Process the payment and return the result.
	 *
	 * For Stripe Payment Intents, the actual payment processing (confirmation, handling 3DS)
	 * is largely managed by the client-side JavaScript (Stripe Elements) and AJAX handlers
	 * (`ajax_create_payment_intent`, `ajax_handle_payment_success`).
	 * This function is typically called when the main checkout form is submitted.
	 * It might perform final validations or simply return success, relying on AJAX handlers
	 * to update the order status based on Stripe's response.
	 *
	 * @param int  $order_id The ID of the order being processed.
	 * @param bool $is_subscription Is subscription.
	 * @return array An array containing the result of the payment processing ('success' or 'failure')
	 *               and a 'redirect' URL if applicable.
	 */
	public function process_payment( $order_id, $is_subscription = false ) {
		if ( ! $this->validate_required_classes() ) {
			return array(
				'result'  => 'failure',
				'message' => __( 'Critical payment functions are missing. Please contact support.', 'ohmylms' ),
			);
		}

		$order      = ohmylms_ecommerce_get_order( $order_id );
		$validation = $this->validate_order_data( $order );
		if ( true !== $validation ) {
			return $validation;
		}

		if ( isset( $_GET['redirect_status'] ) && 'failed' === $_GET['redirect_status'] ) {
			$order->add_order_note( __( 'Payment was not completed. Customer returned from payment page with failed status.', 'ohmylms' ) );
			return array(
				'result'  => 'failure',
				'message' => __( 'Payment was not completed. Please try again.', 'ohmylms' ),
			);
		}

		$amount_in_cents = Helper::get_stripe_amount( $order->get_total(), $order->get_currency() );
		$currency        = strtolower( $order->get_currency() );

		if ( empty( $amount_in_cents ) || empty( $currency ) ) {
			return array(
				'result'  => 'failure',
				'message' => __( 'Order amount or currency is invalid.', 'ohmylms' ),
			);
		}

		$payment_method_id = isset( $_POST['stripe_source'] ) ? sanitize_text_field( wp_unslash( $_POST['stripe_source'] ) ) : null;
		update_post_meta( $order_id, '_stripe_payment_method_id', $payment_method_id );

		if ( empty( $payment_method_id ) ) {
			return array(
				'result'  => 'failure',
				'message' => __( 'Payment method (stripe_source) is missing. Please select a payment method.', 'ohmylms' ),
			);
		}

		$customer = $this->get_customer_id_for_order( $order );
		update_post_meta( $order_id, '_stripe_customer_id', $customer );
		update_post_meta( $order_id, '_has_subscription', $is_subscription ? 'yes' : 'no' );
		$return_url    = $this->get_return_url_for_order( $order_id );
		$intent_params = $this->prepare_intent_params( $order_id, $amount_in_cents, $currency, $payment_method_id, $customer, $return_url, $is_subscription );
		try {
			// Attach payment method BEFORE PaymentIntent.
			try {
				$payment_method_object = StripeApi::get_payment_method( $payment_method_id );
				if ( null === $payment_method_object->customer || $payment_method_object->customer !== $customer ) {
					$result = StripeApi::attach_payment_method( $payment_method_id, $customer );
				}
				// Update the default payment method for the customer.
				StripeApi::update_customer_default_payment_method( $customer, $payment_method_id );
			} catch ( \Exception $e ) {
				$order->add_order_note( sprintf( /* translators: %s: text value. */
				__( 'Stripe PaymentMethod attachment failed: %s', 'ohmylms' ), $e->getMessage() ) );
			}

			// Now create the PaymentIntent.
			$intent = StripeApi::request( $intent_params, 'payment_intents' );

			if ( ! empty( $intent->error ) ) {
				return array(
					'result'  => 'failure',
					'message' => $intent->error->message,
				);
			}

			if ( ! empty( $intent->charges ) && ! empty( $intent->charges->data[0] ) ) {
				$charge = $intent->charges->data[0];
				if ( isset( $charge->balance_transaction ) ) {
					$balanceTransaction = StripeApi::request( null, 'balance_transactions/' . sanitize_text_field( $charge->balance_transaction ), 'GET' );
					$stripe_fee         = Helper::format_balance_fee( $balanceTransaction, 'fee' );
					$stripe_net         = Helper::format_balance_fee( $balanceTransaction, 'net' );
					update_post_meta( $order_id, '_stripe_fee', $stripe_fee );
					update_post_meta( $order_id, '_stripe_net', $stripe_net );
				}
			}

			if ( $intent && ( 'succeeded' === $intent->status || 'processing' === $intent->status ) ) {
				return $this->handle_successful_intent( $intent, $order_id, $order );
			} elseif ( $intent && 'requires_action' === $intent->status ) {
				return $this->handle_intent_requires_action( $intent, $order_id, $order );
			} elseif ( $intent && 'requires_payment_method' === $intent->status ) {
				$error_message = isset( $intent->last_payment_error->message )
					? $intent->last_payment_error->message
					: __( 'Payment failed. Please try another payment method.', 'ohmylms' );
				$order->add_order_note( sprintf( /* translators: %s: text value. */
				__( 'Stripe payment failed: %s', 'ohmylms' ), $error_message ) );
				return array(
					'result'  => 'failure',
					'message' => $error_message,
				);
			} else {
				$order->add_order_note( sprintf( /* translators: %s: text value. */
				__( 'Stripe PaymentIntent has an unexpected status: %s', 'ohmylms' ), $intent->status ?? 'Unknown' ) );
				return array(
					'result'  => 'failure',
					'message' => __( 'An unexpected error occurred with the payment gateway.', 'ohmylms' ),
				);
			}
		} catch ( \Exception $e ) {
			$order->add_order_note( sprintf( /* translators: %s: text value. */
			__( 'Stripe API Error: %s', 'ohmylms' ), $e->getMessage() ) );
			return array(
				'result'  => 'failure',
				'message' => __( 'There was an error processing your payment. Please try again or contact support.', 'ohmylms' ),
			);
		}
	}


	/**
	 * Handle the return from Stripe payment page and thank you page
	 * This is called on init to handle both return URL and thank you page
	 */
	public function handle_stripe_return() {

		// Check if we're on the thank you page.
		if ( isset( $_GET['ohmylms-order-received'] ) || ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], 'ohmylms-order-received' ) !== false ) ) {
			$order_id = isset( $_GET['ohmylms-order-received'] ) ? absint( wp_unslash( $_GET['ohmylms-order-received'] ) ) : 0;

			// If order ID is not in GET, try to get it from the URL.
			if ( ! $order_id && isset( $_SERVER['REQUEST_URI'] ) ) {
				preg_match( '/ohmylms-order-received\/(\d+)/', $_SERVER['REQUEST_URI'], $matches );
				if ( ! empty( $matches[1] ) ) {
					$order_id = absint( $matches[1] );
				}
			}

			if ( $order_id ) {
				$order = ohmylms_ecommerce_get_order( $order_id );
				if ( $order ) {
					// Check for failed redirect status.
					if ( isset( $_GET['redirect_status'] ) && 'failed' === $_GET['redirect_status'] ) {
						// Update order status.
						if ( method_exists( $order, 'update_status' ) ) {
							$order->update_status( 'failed', __( 'Payment was not completed.', 'ohmylms' ) );
						}
						// Add order note.
						$order->add_order_note( __( 'Payment was not completed. Customer returned from payment page with failed status.', 'ohmylms' ) );
						// Store the failed status in session or transient for the frontend.
						set_transient( 'stripe_payment_failed_' . $order_id, true, 5 * MINUTE_IN_SECONDS );
						return;
					}

					// Handle successful payment.
					if ( isset( $_GET['payment_intent'] ) && isset( $_GET['payment_intent_client_secret'] ) ) {
						try {
							$intent = StripeApi::request(
								null,
								'payment_intents/' . sanitize_text_field( wp_unslash( $_GET['payment_intent'] ) )
							);

							if ( $intent && 'succeeded' === $intent->status ) {
								if ( ! empty( $intent->charges ) && ! empty( $intent->charges->data[0] ) ) {
									$charge    = $intent->charges->data[0];
									$charge_id = $charge->id;

									if ( isset( $charge->balance_transaction ) ) {
										$balanceTransaction = StripeApi::request( null, 'balance_transactions/' . sanitize_text_field( $charge->balance_transaction ), 'GET' );
										$stripe_fee         = Helper::format_balance_fee( $balanceTransaction, 'fee' );
										$stripe_net         = Helper::format_balance_fee( $balanceTransaction, 'net' );
										update_post_meta( $order_id, '_stripe_fee', $stripe_fee );
										update_post_meta( $order_id, '_stripe_net', $stripe_net );
									}

									if ( isset( $charge->payment_method_details->type ) ) {
										$payment_method_type_from_intent = $charge->payment_method_details->type;
										update_post_meta( $order_id, self::META_PAYMENT_METHOD_TYPE, $payment_method_type_from_intent );

										$method_title = $this->get_payment_method_title( $payment_method_type_from_intent );
										update_post_meta( $order_id, '_payment_method_title', $method_title );
										update_post_meta( $order_id, '_payment_method', 'stripe' );
									}

									// Save transaction ID using charge ID.
									if ( $charge_id ) {
										$order->payment_complete( $charge_id ? $charge_id : $payment_intent->id );
										$order->add_order_note(
											sprintf(
												/* translators: %1$s: text value; %2$s: text value. */
												__( 'Payment completed via Stripe. Charge ID: %1$s. Payment Method: %2$s', 'ohmylms' ),
												isset( $charge_id ) ? $charge_id : $payment_intent->id,
												$method_title
											)
										);
									}
								}
							}
						} catch ( \Exception $e ) {
							$order->add_order_note( sprintf( /* translators: %s: text value. */
							__( 'Error verifying payment status: %s', 'ohmylms' ), $e->getMessage() ) );
						}
					}
				}
			}
		}
	}

	/**
	 * Get payment method title from predefined array
	 *
	 * @param string $method_type The payment method type from Stripe.
	 * @return string The human readable title
	 */
	private function get_payment_method_title( $method_type ) {
		$payment_methods = array(
			'card'       => __( 'Credit card / Debit card', 'ohmylms' ),
			'alipay'     => __( 'Alipay', 'ohmylms' ),
			'bancontact' => __( 'Bancontact', 'ohmylms' ),
			'boleto'     => __( 'Boleto', 'ohmylms' ),
			'eps'        => __( 'EPS', 'ohmylms' ),
			'ideal'      => __( 'iDEAL', 'ohmylms' ),
			'multibanco' => __( 'Multibanco', 'ohmylms' ),
			'oxxo'       => __( 'OXXO', 'ohmylms' ),
			'p24'        => __( 'Przelewy24', 'ohmylms' ),
			'sepa_debit' => __( 'Direct debit payment', 'ohmylms' ),
			'cashapp'    => __( 'Cash App Pay', 'ohmylms' ),
		);

		return isset( $payment_methods[ $method_type ] ) ? $payment_methods[ $method_type ] : ucfirst( $method_type );
	}


	/**
	 * Get the transaction URL for this gateway.
	 * This method is used to generate the URL for viewing transaction details.
	 *
	 * @param object $order The order object for which the transaction URL is being generated.
	 * @return string The URL for viewing the transaction details.
	 */
	public function get_transaction_url( $order ) {
		$this->transaction_url = Helper::get_transaction_url( $this->testmode );
		return parent::get_transaction_url( $order );
	}

	/**
	 * Extract and save payment information from a Stripe intent
	 *
	 * @param object $intent The Stripe PaymentIntent object.
	 * @param int    $order_id The order ID.
	 * @return array Array containing charge_id and payment_method_type
	 */
	private function extract_and_save_payment_info( $intent, $order_id ) {
		$charge_id           = null;
		$payment_method_type = null;

		if ( ! empty( $intent->charges ) && ! empty( $intent->charges->data[0] ) ) {
			$charge    = $intent->charges->data[0];
			$charge_id = $charge->id;

			if ( isset( $charge->balance_transaction ) ) {
				$balanceTransaction = StripeApi::request( null, 'balance_transactions/' . sanitize_text_field( $charge->balance_transaction ), 'GET' );
				$stripe_fee         = Helper::format_balance_fee( $balanceTransaction, 'fee' );
				$stripe_net         = Helper::format_balance_fee( $balanceTransaction, 'net' );
				update_post_meta( $order_id, '_stripe_fee', $stripe_fee );
				update_post_meta( $order_id, '_stripe_net', $stripe_net );
			}

			if ( isset( $charge->payment_method_details->type ) ) {
				$payment_method_type = $charge->payment_method_details->type;
				update_post_meta( $order_id, self::META_PAYMENT_METHOD_TYPE, $payment_method_type );

				$method_title = $this->get_payment_method_title( $payment_method_type );
				update_post_meta( $order_id, '_payment_method_title', $method_title );
				update_post_meta( $order_id, '_payment_method', 'stripe' );
			}
		}

		return array(
			'charge_id'           => $charge_id,
			'payment_method_type' => $payment_method_type,
		);
	}

	/**
	 * Handle successful payment intent
	 *
	 * @param object $intent The Stripe PaymentIntent object.
	 * @param mixed  $order_id Order id.
	 * @param object $order The order object.
	 * @param bool   $is_subscription Whether this is a subscription payment.
	 * @return array Result array
	 */
	private function handle_successful_intent( $intent, $order_id, $order, $is_subscription = false ) {
		update_post_meta( $order_id, self::META_PAYMENT_INTENT_ID, $intent->id );
		update_post_meta( $order_id, self::META_CHARGE_CAPTURED, 'yes' );

		$payment_info = $this->extract_and_save_payment_info( $intent, $order_id );

		$note = sprintf(
			/* translators: %1$s: text value; %2$s: text value; %3$s: text value; %4$s: text value. */
			__( 'Stripe %1$s payment successful (server-side). Intent ID: %2$s. Charge ID: %3$s. Payment Method: %4$s.', 'ohmylms' ),
			$is_subscription ? 'subscription' : '',
			$intent->id,
			isset( $payment_info['charge_id'] ) ? $payment_info['charge_id'] : 'N/A',
			$payment_info['payment_method_type']
		);

		$order->add_order_note( $note );
		if ( method_exists( $order, 'payment_complete' ) ) {
			$order->payment_complete( $payment_info['charge_id'] );
		} elseif ( did_action( 'ohmylms_payment_completed' ) || has_action( 'ohmylms_payment_completed' ) ) {
			do_action( 'ohmylms_payment_completed', $order_id );
		}

		return array(
			'result'         => 'success',
			'transaction_id' => $payment_info['charge_id'],
			'redirect'       => $this->get_return_url( $order ),
		);
	}

	/**
	 * Handle intent requiring action
	 *
	 * @param object $intent The Stripe PaymentIntent object.
	 * @param mixed  $order_id Order id.
	 * @param object $order The order object.
	 * @return array Result array
	 */
	private function handle_intent_requires_action( $intent, $order_id, $order ) {
		update_post_meta( $order_id, self::META_PAYMENT_INTENT_ID, $intent->id );
		$client_secret = isset( $intent->client_secret ) ? $intent->client_secret : null;

		if ( ! $client_secret ) {
			$order->add_order_note( __( 'Stripe requires action, but client_secret is missing.', 'ohmylms' ) );
			return array(
				'result'  => 'failure',
				'message' => __( 'Payment requires further action, but client secret was not provided by Stripe.', 'ohmylms' ),
			);
		}

		return array(
			'result'        => 'requires_action',
			'client_secret' => $client_secret,
			'order_id'      => $order_id,
			'redirect_url'  => $intent->next_action->redirect_to_url->url ?? null,
			'return_url'    => $this->get_return_url( $order ),
		);
	}


	/**
	 * Process a refund for an order.
	 * This method handles the refund process by creating a refund request
	 *
	 * @param int        $order_id The ID of the order to refund.
	 * @param float|null $amount The amount to refund (optional, full refund if null).
	 * @param string     $reason The reason for the refund (optional).
	 * @return array The response from the Stripe API with result status.
	 *
	 * @since 1.0.0
	 * @throws \Exception When the operation cannot be completed.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		try {
			if ( ! is_numeric( $order_id ) || $order_id <= 0 ) {
				throw new \Exception( __( 'Invalid order ID provided.', 'ohmylms' ) );
			}

			$order = ohmylms_ecommerce_get_order( $order_id );
			if ( ! $order ) {
				throw new \Exception( __( 'Order not found.', 'ohmylms' ) );
			}

			if ( ! method_exists( $order, 'get_currency' ) || ! method_exists( $order, 'get_transaction_id' ) ) {
				throw new \Exception( __( 'Order object is missing required methods.', 'ohmylms' ) );
			}

			$order_currency = $order->get_currency();
			if ( empty( $order_currency ) ) {
				throw new \Exception( __( 'Order currency is missing.', 'ohmylms' ) );
			}

			$transaction_id = $order->get_transaction_id();
			if ( empty( $transaction_id ) ) {
				throw new \Exception( __( 'No transaction ID found for this order.', 'ohmylms' ) );
			}

			$request = array( 'charge' => $transaction_id );

			if ( ! is_null( $amount ) ) {
				if ( ! is_numeric( $amount ) || $amount < 0 ) {
					throw new \Exception( __( 'Invalid refund amount provided.', 'ohmylms' ) );
				}
				$request['amount'] = Helper::get_stripe_amount( $amount, $order_currency );
			}

			if ( ! empty( $reason ) ) {
				$request['metadata'] = array(
					'reason' => sanitize_text_field( $reason ),
				);
			}

			$response = StripeApi::request( $request, 'refunds' );

			if ( empty( $response ) || ! isset( $response->id ) ) {
				throw new \Exception( __( 'Failed to process refund through Stripe.', 'ohmylms' ) );
			}

			if ( isset( $response->balance_transaction ) ) {
				$balanceTransaction = StripeApi::request( null, 'balance_transactions/' . sanitize_text_field( $response->balance_transaction ), 'GET' );
				$stripe_net         = Helper::format_balance_fee( $balanceTransaction, 'net' );
				// Get existing values.
				$existing_net = (float) get_post_meta( $order_id, '_stripe_net', true );
				$stripe_net   = abs( $stripe_net );
				// Subtract refunded fee/net from existing.
				update_post_meta( $order_id, '_stripe_net', $existing_net - $stripe_net );
			}

			// Add order note about the refund.
			$order->add_order_note(
				sprintf(
					/* translators: %s: text value. */
					__( 'Refund processed via Stripe.Reason: %s', 'ohmylms' ),
					$reason ? $reason : __( 'No reason provided', 'ohmylms' )
				)
			);

			return array(
				'result'    => 'success',
				'refund_id' => $response->id,
				'message'   => __( 'Refund processed successfully.', 'ohmylms' ),
			);

		} catch ( \Exception $e ) {
			if ( isset( $order ) && method_exists( $order, 'add_order_note' ) ) {
				$order->add_order_note( sprintf( /* translators: %s: text value. */
				__( 'Refund failed: %s', 'ohmylms' ), $e->getMessage() ) );
			}
			return array(
				'result'  => 'failure',
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Validate order data
	 *
	 * @param object $order The order object.
	 * @return array|bool Array with error message if invalid, true if valid
	 */
	private function validate_order_data( $order ) {
		if ( ! $order || ! method_exists( $order, 'get_total' ) || ! method_exists( $order, 'get_currency' ) ||
			! method_exists( $order, 'get_billing_email' ) || ! method_exists( $order, 'add_order_note' ) ) {
			return array(
				'result'  => 'failure',
				'message' => __( 'Order data is invalid or incomplete.', 'ohmylms' ),
			);
		}
		return true;
	}

	/**
	 * Get Stripe customer ID for order
	 *
	 * @param object $order The order object.
	 * @return string|null The Stripe customer ID
	 */
	private function get_customer_id_for_order( $order ) {
		$customer_id = get_post_meta( $order->get_id(), '_stripe_customer_id', true );

		if ( ! empty( $customer_id ) ) {
			return $customer_id;
		}

		$user = $order->get_user();
		if ( false === $user ) {
			$user = wp_get_current_user();
		}
		if ( ! $customer_id ) {
			$customer_id = get_user_meta( $user->ID, '_ohmylms_stripe_customer_id', true );
			if ( $customer_id ) {
				return $customer_id;
			}
		}
		$customer = new StripeCustomer( $user->ID, $customer_id );
		return $customer->update_or_create_customer();
	}

	/**
	 * Prepare intent parameters
	 *
	 * @param int    $order_id Order identifier.
	 * @param int    $amount_in_cents Amount in cents.
	 * @param string $currency Currency.
	 * @param string $payment_method_id Payment method identifier.
	 * @param mixed  $customer Customer.
	 * @param string $return_url Return url.
	 * @param bool   $is_subscription Is subscription.
	 * @return array
	 */
	private function prepare_intent_params( $order_id, $amount_in_cents, $currency, $payment_method_id, $customer, $return_url, $is_subscription = false ) {
		$params = array(
			'amount'               => $amount_in_cents,
			'currency'             => $currency,
			'customer'             => $customer,
			'payment_method'       => $payment_method_id,
			'confirm'              => 'true',
			'confirmation_method'  => $is_subscription ? 'manual' : 'automatic',
			'return_url'           => $return_url,
			'metadata'             => array(
				'order_id'   => $order_id,
				'wp_user_id' => get_current_user_id() > 0 ? get_current_user_id() : 'guest',
			),
			'payment_method_types' => $this->get_valid_stripe_payment_method_types( $currency, $customer ),
		);

		if ( $customer ) {
			$params['customer'] = $customer;
		}

		if ( $is_subscription ) {
			$params['setup_future_usage'] = 'off_session';
		}

		return $params;
	}


	/**
	 * Get valid Stripe payment method types for the merchant account.
	 *
	 * @param string      $currency Currency.
	 * @param string|null $stripe_customer_id Optional. Stripe customer ID to personalize methods.
	 * @return array
	 *
	 * @since 1.0.0
	 */
	function get_valid_stripe_payment_method_types( $currency = 'usd', $stripe_customer_id = null ) {
		$currency  = strtolower( $currency );
		$cache_key = 'ohmylms_stripe_payment_methods_' . $currency;

		$cached = get_transient( $cache_key );
		if ( ! empty( $cached ) && is_array( $cached ) ) {
			return $cached;
		}
		$selected_methods = array();
		$payment_methods  = $this->get_payment_methods();

		foreach ( $payment_methods as $method ) {
			if ( in_array( $currency, $method['supportedCurrencies'] ) ) {
				$option_name = 'payment_method_' . $method['key'];
				$is_enabled  = $this->get_setting( $option_name, $method['defaultSelected'] ? 'yes' : 'no' );

				if ( 'yes' === $is_enabled ) {
					$selected_methods[] = $method['key'];
				}
			}
		}
		if ( empty( $selected_methods ) ) {
			$selected_methods = array( 'card', 'link' );
		}

		set_transient( $cache_key, $selected_methods, HOUR_IN_SECONDS );

		return $selected_methods;
	}


	/**
	 * Get return URL for order
	 *
	 * @param int $order_id Order identifier.
	 * @return string
	 */
	private function get_return_url_for_order( $order_id ) {
		return add_query_arg(
			array(
				'action'   => 'ohmylms_stripe_return',
				'order_id' => $order_id,
			),
			home_url( '/' )
		);
	}

	/**
	 * Validate required classes exist
	 *
	 * @return bool
	 */
	private function validate_required_classes() {
		return function_exists( 'ohmylms_ecommerce_get_order' ) &&
				class_exists( 'OhMyLMS\Commerce\Gateways\Stripe\Helper' ) &&
				class_exists( 'OhMyLMS\Commerce\Gateways\Stripe\StripeApi' );
	}


	/**
	 * Get payment gateway meta for Gateway stripe intents.
	 *
	 * @param mixed $order Order.
	 */
	public function get_payment_gateway_meta( $order ) {
		$order_id = $order->get_id();
		return array(
			array(
				'type'  => 'fee',
				'label' => __( 'Stripe Fee', 'ohmylms' ),
				'value' => get_post_meta( $order_id, '_stripe_fee', true ),
			),
			array(
				'type'  => 'net',
				'label' => __( 'Stripe net', 'ohmylms' ),
				'value' => get_post_meta( $order_id, '_stripe_net', true ),
			),
		);
	}


	/*
	Process a recurring payment for a Stripe subscription.
	*
	* @param int $original_order_id The ID of the original order.
	* @param float $amount The amount to charge.
	* @param int $subscription_id The ID of the ohmylms-subscription post.
	* @param int $student_id The ID of the student.
	* @return array Result of the payment attempt. Details defined in parent::process_recurring_payment().
	* @since 1.0.0
	*/
	/**
	 * Process recurring payment for Gateway stripe intents.
	 *
	 * @param mixed $original_order_id Original order identifier.
	 * @param mixed $renewal_order_id Renewal order identifier.
	 * @param mixed $amount Amount.
	 * @param mixed $subscription_id Subscription identifier.
	 * @param mixed $student_id Student identifier.
	 */
	public function process_recurring_payment( $original_order_id, $renewal_order_id, $amount, $subscription_id, $student_id ) {
		try {
			$original_order_id = absint( $original_order_id );
			$renewal_order_id  = absint( $renewal_order_id );
			$subscription_id   = absint( $subscription_id );
			$student_id        = absint( $student_id );

			$original_order = ohmylms_ecommerce_get_order( $original_order_id );
			$renewal_order  = ohmylms_ecommerce_get_order( $renewal_order_id );
			if ( ! $original_order ) {
				return array(
					'result'  => 'failure',
					'message' => __( 'Original order not found for Stripe recurring payment.', 'ohmylms' ),
				);
			}
			$stripe_customer_id       = get_post_meta( $original_order_id, '_stripe_customer_id', true );
			$stripe_payment_method_id = get_post_meta( $original_order_id, '_stripe_payment_method_id', true );

			if ( empty( $stripe_customer_id ) ) {
				return array(
					'result'  => 'failure',
					'message' => __( 'Stripe Customer ID not found on subscription.', 'ohmylms' ),
				);
			}
			if ( empty( $stripe_payment_method_id ) ) {
				return array(
					'result'  => 'failure',
					'message' => __( 'Stripe Payment Method ID not found on subscription.', 'ohmylms' ),
				);
			}

			update_post_meta( $renewal_order_id, '_stripe_customer_id', $stripe_customer_id );
			update_post_meta( $renewal_order_id, '_stripe_payment_method_id', $stripe_payment_method_id );

			$intent_args = array(
				'amount'              => Helper::get_stripe_amount( $amount, $original_order->get_currency() ),
				'currency'            => strtolower( $original_order->get_currency() ),
				'customer'            => $stripe_customer_id,
				'payment_method'      => $stripe_payment_method_id,
				'confirm'             => 'true',
				'off_session'         => 'one_off',
				'confirmation_method' => 'automatic',
				'description'         => sprintf( /* translators: %1$s: text value; %2$s: text value. */
				__( 'Subscription Renewal - Order #%1$s, Subscription #%2$s', 'ohmylms' ), $original_order_id, $subscription_id ),
				'metadata'            => array(
					'order_id'        => $renewal_order_id,
					'subscription_id' => $subscription_id,
					'parent_order_id' => $renewal_order_id,
					'student_id'      => $student_id,
					'site_url'        => get_site_url(),
				),
			);

			StripeApi::set_secret_key( $this->secret_key );
			$payment_intent = StripeApi::request( $intent_args, 'payment_intents' );

			if ( ! empty( $payment_intent->error ) ) {
				$error_message = $payment_intent->error->message;
				if ( isset( $payment_intent->error->code ) && 'authentication_required' === $payment_intent->error->code ) {
					$error_message = __( 'Payment requires authentication. Please update your payment method.', 'ohmylms' );
				}
				return array(
					'result'  => 'failure',
					'message' => $error_message,
				);
			}
			if ( 'succeeded' === $payment_intent->status || 'processing' === $payment_intent->status ) {
				return $this->handle_successful_intent( $payment_intent, $renewal_order_id, $renewal_order, true );
			} elseif ( 'requires_action' === $payment_intent->status || 'requires_source_action' === $payment_intent->status ) {
				return $this->handle_intent_requires_action( $payment_intent, $renewal_order_id, $renewal_order );
			} else {
				return array(
					'result'   => 'failure',
					'message'  => sprintf( /* translators: %s: text value. */
					__( 'Stripe payment intent status: %s', 'ohmylms' ), $payment_intent->status ),
					'order_id' => $renewal_order_id,
				);
			}
		} catch ( \Exception $e ) {
			return array(
				'result'  => 'failure',
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Process an upsell/downsell payment using an existing order's payment method.
	 *
	 * This method is used to handle upsell or downsell offers after the original order has been placed.
	 *
	 * @param object $original_order The original order object.
	 * @param object $course The course object for which the upsell/downsell is being processed.
	 * @param float  $amount The amount to charge for the upsell/downsell.
	 * @param array  $step_data Additional step data, including step ID.
	 * .
	 * @return array|WP_Error An array with success status or a WP_Error on failure.
	 */
	public function process_offer_payment( $original_order, $course, $amount, $step_data ) {
		// If amount is 0 (free with discount), no payment needed.
		if ( $amount <= 0 ) {
			return true;
		}

		try {
			// Get Stripe customer ID from original order.
			$stripe_customer_id = get_post_meta( $original_order->get_id(), '_stripe_customer_id', true );
			if ( empty( $stripe_customer_id ) ) {
				return new \WP_Error( 'no_customer', __( 'No Stripe customer found for tokenization.', 'ohmylms' ) );
			}

			// Get stored payment method from original order.
			$payment_method_id = get_post_meta( $original_order->get_id(), '_stripe_payment_method_id', true );
			if ( empty( $payment_method_id ) ) {
				return new \WP_Error( 'no_payment_method', __( 'No stored payment method found for upsell.', 'ohmylms' ) );
			}

			// Get available payment gateways.
			$available_gateways = ecommerce()->gateways()->get_available_payment_gateways();
			if ( ! isset( $available_gateways['stripe'] ) ) {
				return new \WP_Error( 'gateway_unavailable', __( 'Stripe gateway not available.', 'ohmylms' ) );
			}

			// Ensure payment method is attached to customer.
			$payment_method = $this->ensure_payment_method_attached( $payment_method_id, $stripe_customer_id );
			if ( is_wp_error( $payment_method ) ) {
				return $payment_method;
			}

			// Use existing gateway's StripeApi class to create payment intent.
			$currency        = strtolower( $original_order->get_currency() );
			$amount_in_cents = Helper::get_stripe_amount( $amount, $currency );

			$intent_params = array(
				'amount'              => $amount_in_cents,
				'currency'            => $currency,
				'customer'            => $stripe_customer_id,
				'payment_method'      => $payment_method_id,
				'confirm'             => 'true',
				'off_session'         => 'one_off',
				'confirmation_method' => 'automatic',
				'description'         => sprintf( /* translators: %s: text value. */
				__( 'Upsell/Downsell - Order #%s', 'ohmylms' ), $original_order->get_id() ),
				'metadata'            => array(
					'order_id'   => $original_order->get_id(),
					'type'       => 'upsell_downsell',
					'wp_user_id' => get_current_user_id(),
					'course_id'  => $course->get_id(),
					'step_id'    => $step_data['step_id'] ?? '',
				),
			);

			// Create and confirm payment intent using existing gateway API.
			$upsell_step_id  = $step_data['step_id'] ?? uniqid( 'upsell_', true );
			$idempotency_key = 'ohmylms-' . $original_order->get_id() . '-' . $upsell_step_id;

			$payment_intent = StripeApi::request(
				$intent_params,
				'payment_intents',
				'POST',
				false,
				$idempotency_key
			);

			if ( ! empty( $payment_intent->error ) ) {
				return new \WP_Error( 'payment_failed', $payment_intent->error->message );
			}

			if ( $payment_intent && ( 'succeeded' === $payment_intent->status || 'processing' === $payment_intent->status ) ) {
				// Store payment details in order meta.
				update_post_meta( $original_order->get_id(), '_upsell_order_id', $payment_intent->id );

				if ( ! empty( $payment_intent->charges ) && ! empty( $payment_intent->charges->data[0] ) ) {
					$charge = $payment_intent->charges->data[0];
					update_post_meta( $original_order->get_id(), '_upsell_charge_id', $charge->id );

					// Store fee and net amounts like the main gateway does.
					if ( isset( $charge->balance_transaction ) ) {
						$balance_transaction = StripeApi::request(
							null,
							'balance_transactions/' . sanitize_text_field( $charge->balance_transaction ),
							'GET'
						);
						$stripe_fee          = Helper::format_balance_fee( $balance_transaction, 'fee' );
						$stripe_net          = Helper::format_balance_fee( $balance_transaction, 'net' );
						// Get existing values.
						$existing_fee = (float) get_post_meta( $original_order->get_id(), '_stripe_fee', true );
						$existing_net = (float) get_post_meta( $original_order->get_id(), '_stripe_net', true );

						// Add and update.
						update_post_meta( $original_order->get_id(), '_stripe_fee', $existing_fee + $stripe_fee );
						update_post_meta( $original_order->get_id(), '_stripe_net', $existing_net + $stripe_net );
					}
				}

				$original_order->save();
				return array(
					'success' => true,
					'message' => __( 'Payment processed successfully.', 'ohmylms' ),
				);
			} else {
				$status = $payment_intent->status ?? 'unknown';
				return new \WP_Error( 'payment_incomplete', sprintf( /* translators: %s: text value. */
				__( 'Payment intent status: %s', 'ohmylms' ), $status ) );
			}
		} catch ( \Exception $e ) {
			return new \WP_Error( 'payment_exception', sprintf( /* translators: %s: text value. */
			__( 'Stripe payment failed: %s', 'ohmylms' ), $e->getMessage() ) );
		}
	}

	/**
	 * Ensure the payment method is attached to the customer.
	 * If it is already attached, return the same ID.
	 * If not, attempt to attach it and return the ID or an error.
	 *
	 * @param string $payment_method_id The Stripe payment method ID.
	 * @param string $customer_id The Stripe customer ID.
	 * .
	 * @return string|\WP_Error The payment method ID or a WP_Error on failure.
	 */
	private function ensure_payment_method_attached( $payment_method_id, $customer_id ) {
		$payment_method = StripeApi::request(
			null,
			'payment_methods/' . $payment_method_id,
			'GET'
		);

		if ( isset( $payment_method->error ) ) {
			return new \WP_Error( 'fetch_failed', $payment_method->error->message );
		}

		// If already attached to the correct customer, return the same ID.
		if ( isset( $payment_method->customer ) && $payment_method->customer === $customer_id ) {
			return $payment_method_id;
		}

		// Otherwise, try attaching it.
		$attach = StripeApi::request(
			array( 'customer' => $customer_id ),
			'payment_methods/' . $payment_method_id . '/attach',
			'POST'
		);

		if ( isset( $attach->error ) ) {
			return new \WP_Error( 'attachment_failed', $attach->error->message );
		}

		return $payment_method_id;
	}
}
