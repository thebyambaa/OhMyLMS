<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Authorize.Net Payment Gateway.
 *
 * @package OhMyLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use OhMyLMS\Commerce\Abstracts\PaymentGateway;
use OhMyLMS\Gateways\AuthorizeNet\AuthorizeNetAPI;

/**
 * Authorize.Net Payment Gateway.
 *
 * Provides an Authorize.Net payment gateway.
 */
class GatewayAuthorizenet extends PaymentGateway {

	/**
	 * Publishable key.
	 *
	 * @var mixed
	 */
	public $publishable_key;

	/**
	 * Api login id.
	 *
	 * @var mixed
	 */
	public $api_login_id;

	/**
	 * Transaction key.
	 *
	 * @var mixed
	 */
	public $transaction_key;

	/**
	 * Signature key.
	 *
	 * @var mixed
	 */
	public $signature_key;

	/**
	 * Client key.
	 *
	 * @var mixed
	 */
	public $client_key;

	/**
	 * Private key.
	 *
	 * @var mixed
	 */
	public $private_key;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id                = 'authorize_net';
		$gateway_settings_key    = 'ohmylms_' . $this->id . '_settings';
		$this->settings          = get_option( $gateway_settings_key, array() );
		$this->title             = $this->get_option( 'title', __( 'Authorize.Net', 'ohmylms' ) );
		$this->description       = $this->get_option( 'description', __( 'Pay with your credit card via Authorize.Net.', 'ohmylms' ) );
		$this->has_fields        = true;
		$this->order_button_text = __( 'Proceed to Secure Payment', 'ohmylms' );
		$this->testmode          = 'yes' === $this->get_option( 'test_mode' );
		$this->enabled           = $this->get_option( 'enabled' );

		if ( $this->testmode ) {
			$this->api_login_id    = $this->get_option( 'sandbox_api_login_id' );
			$this->transaction_key = $this->get_option( 'sandbox_transaction_key' );
			$this->signature_key   = $this->get_option( 'sandbox_signature_key' );
			$this->client_key      = $this->get_option( 'sandbox_client_key' );
		} else {
			$this->api_login_id    = $this->get_option( 'api_login_id' );
			$this->transaction_key = $this->get_option( 'transaction_key' );
			$this->signature_key   = $this->get_option( 'signature_key' );
			$this->client_key      = $this->get_option( 'client_key' );
		}

		$this->publishable_key = $this->client_key;
		$this->private_key     = $this->transaction_key;

		$this->subscription_support = true;

		// Actions.
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
	}


	/**
	 * Get settings for Gateway authorizenet.
	 */
	public function get_settings() {
		$fields = array(
			array(
				'title'             => __( 'Title', 'ohmylms' ),
				'short_description' => __( 'Enter the title that will appear for Authorize.Net payment during checkout.', 'ohmylms' ),
				'input_type'        => 'text',
				'value'             => $this->title,
				'default_value'     => __( 'Authorize.Net', 'ohmylms' ),
				'option_name'       => 'title',
			),
			array(
				'title'             => __( 'Description', 'ohmylms' ),
				'short_description' => __( 'Provide detailed description for Authorize.Net payment method', 'ohmylms' ),
				'input_type'        => 'textarea',
				'value'             => $this->get_setting( 'description', '' ),
				'default_value'     => __( 'Pay with your credit card via Authorize.Net.', 'ohmylms' ),
				'option_name'       => 'description',
			),
			array(
				'title'             => __( 'Test Mode', 'ohmylms' ),
				'short_description' => __( 'Enable test mode for sandbox environment testing.', 'ohmylms' ),
				'input_type'        => 'switch',
				'default_value'     => 'yes',
				'value'             => $this->get_setting( 'test_mode', 'yes' ),
				'option_name'       => 'test_mode',
				'conditional_logic' => array(
					'type'     => 'control',
					'controls' => array(
						'sandbox_api_login_id',
						'sandbox_transaction_key',
						'sandbox_signature_key',
						'sandbox_client_key',
						'api_login_id',
						'transaction_key',
						'signature_key',
						'client_key',
					),
				),
			),
			// Sandbox API Login ID.
			array(
				'title'             => __( 'Sandbox API Login ID', 'ohmylms' ),
				'short_description' => __( 'Enter your Authorize.Net sandbox API Login ID.', 'ohmylms' ),
				'input_type'        => 'text',
				'default_value'     => '',
				'value'             => $this->get_setting( 'sandbox_api_login_id', '' ),
				'option_name'       => 'sandbox_api_login_id',
				'conditional_logic' => array(
					'type'       => 'dependent',
					'depends_on' => 'test_mode',
					'show_when'  => 'yes',
				),
			),
			// Sandbox Transaction Key.
			array(
				'title'             => __( 'Sandbox Transaction Key', 'ohmylms' ),
				'short_description' => __( 'Enter your Authorize.Net sandbox Transaction Key.', 'ohmylms' ),
				'input_type'        => 'text',
				'default_value'     => '',
				'value'             => $this->get_setting( 'sandbox_transaction_key', '' ),
				'option_name'       => 'sandbox_transaction_key',
				'conditional_logic' => array(
					'type'       => 'dependent',
					'depends_on' => 'test_mode',
					'show_when'  => 'yes',
				),
			),
			// Sandbox Signature Key.
			array(
				'title'             => __( 'Sandbox Signature Key', 'ohmylms' ),
				'short_description' => __( 'Enter your Authorize.Net sandbox Signature Key (optional).', 'ohmylms' ),
				'input_type'        => 'text',
				'default_value'     => '',
				'value'             => $this->get_setting( 'sandbox_signature_key', '' ),
				'option_name'       => 'sandbox_signature_key',
				'conditional_logic' => array(
					'type'       => 'dependent',
					'depends_on' => 'test_mode',
					'show_when'  => 'yes',
				),
			),
			// Sandbox Client Key.
			array(
				'title'             => __( 'Sandbox Client Key', 'ohmylms' ),
				'short_description' => __( 'Enter your Authorize.Net sandbox Public Client Key (for Accept.js).', 'ohmylms' ),
				'input_type'        => 'text',
				'default_value'     => '',
				'value'             => $this->get_setting( 'sandbox_client_key', '' ),
				'option_name'       => 'sandbox_client_key',
				'conditional_logic' => array(
					'type'       => 'dependent',
					'depends_on' => 'test_mode',
					'show_when'  => 'yes',
				),
			),
			// Live API Login ID.
			array(
				'title'             => __( 'Live API Login ID', 'ohmylms' ),
				'short_description' => __( 'Enter your Authorize.Net live API Login ID.', 'ohmylms' ),
				'input_type'        => 'text',
				'default_value'     => '',
				'value'             => $this->get_setting( 'api_login_id', '' ),
				'option_name'       => 'api_login_id',
				'conditional_logic' => array(
					'type'       => 'dependent',
					'depends_on' => 'test_mode',
					'show_when'  => 'no',
				),
			),
			// Live Transaction Key.
			array(
				'title'             => __( 'Live Transaction Key', 'ohmylms' ),
				'short_description' => __( 'Enter your Authorize.Net live Transaction Key.', 'ohmylms' ),
				'input_type'        => 'text',
				'default_value'     => '',
				'value'             => $this->get_setting( 'transaction_key', '' ),
				'option_name'       => 'transaction_key',
				'conditional_logic' => array(
					'type'       => 'dependent',
					'depends_on' => 'test_mode',
					'show_when'  => 'no',
				),
			),
			// Live Signature Key.
			array(
				'title'             => __( 'Live Signature Key', 'ohmylms' ),
				'short_description' => __( 'Enter your Authorize.Net live Signature Key (optional).', 'ohmylms' ),
				'input_type'        => 'text',
				'default_value'     => '',
				'value'             => $this->get_setting( 'signature_key', '' ),
				'option_name'       => 'signature_key',
				'conditional_logic' => array(
					'type'       => 'dependent',
					'depends_on' => 'test_mode',
					'show_when'  => 'no',
				),
			),
			// Live Client Key.
			array(
				'title'             => __( 'Live Client Key', 'ohmylms' ),
				'short_description' => __( 'Enter your Authorize.Net live Public Client Key (for Accept.js).', 'ohmylms' ),
				'input_type'        => 'text',
				'default_value'     => '',
				'value'             => $this->get_setting( 'client_key', '' ),
				'option_name'       => 'client_key',
				'conditional_logic' => array(
					'type'       => 'dependent',
					'depends_on' => 'test_mode',
					'show_when'  => 'no',
				),
			),
		);
		$gateway_settings = array(
			'id'                   => 'authorize_net',
			'title'                => __( 'Authorize.Net', 'ohmylms' ),
			'description'          => __( 'Authorize.Net payment gateway', 'ohmylms' ),
			'icon'                 => '<svg xmlns="http://www.w3.org/2000/svg" width="43" height="42" viewBox="0 0 43 42" fill="none"><rect width="43" height="42" rx="21" fill="#1d2a47"/><g clip-path="url(#a)"><path d="M13.5 13C18.78 13 24.06 13 29.5 13C29.5 18.28 29.5 23.56 29.5 29C24.22 29 18.94 29 13.5 29C13.5 23.72 13.5 18.44 13.5 13Z" fill="#1d2a47"/><path d="M13.5 16C13.83 16 14.16 16 14.5 16C15.16 17.98 15.82 19.96 16.5 22C20.13 22 23.76 22 27.5 22C27.83 23.65 28.16 25.3 28.5 27C26.5 29 26.5 29 23.4609 29.1953C20.1406 29.1302 16.8203 29.0651 13.5 29C13.5 24.71 13.5 20.42 13.5 16Z" fill="#1d2a46"/><path d="M16.5 14C22.0682 14.5682 22.0682 14.5682 23.5 16C23.6875 18.4375 23.6875 18.4375 23.5 21C21.5 23 21.5 23 19 23.25C16.5 23 16.5 23 14.5 21C14.25 18.5 14.25 18.5 14.5 16C15.16 15.34 15.82 14.68 16.5 14Z" fill="#196ff1"/><path d="M23.5 22C25.15 22.33 26.8 22.66 28.5 23C28.17 24.65 27.84 26.3 27.5 28C25.85 27.67 24.2 27.34 22.5 27C22.83 25.35 23.16 23.7 23.5 22Z" fill="#e6ac00"/></g><defs><clipPath id="a"><rect width="16" height="16" fill="#fff" transform="translate(13.5 13)"/></clipPath></defs></svg>',
			'has_config'           => true,
			'subscription_support' => true,
			'settings_fields'      => $fields,
			'enabled'              => $this->enabled,
		);

		return $gateway_settings;
	}


	/**
	 * Output the gateway settings screen.
	 *
	 * This function should echo the HTML for the payment form.
	 */
	public function payment_fields() {
		ob_start();

		if ( $this->get_description() ) {
			echo '<p>' . wp_kses_post( $this->get_description() ) . '</p>';
		}
		?>
		<div id="authorize-net-payment-form">
			<fieldset>
				<div class="form-row">
					<label for="anet-card-number"><?php esc_html_e( 'Card Number', 'ohmylms' ); ?></label>
					<input type="text" id="anet-card-number" class="authorize-net-field" autocomplete="cc-number" placeholder="1234 5678 9012 3456" />
				</div>
				<div class="form-row">
					<label for="anet-expiration-date"><?php esc_html_e( 'Expiration Date (MM/YY)', 'ohmylms' ); ?></label>
					<input type="text" id="anet-expiration-date" class="authorize-net-field" autocomplete="cc-exp" placeholder="MM/YY" />
				</div>
				<div class="form-row">
					<label for="anet-cvv"><?php esc_html_e( 'CVV', 'ohmylms' ); ?></label>
					<input type="text" id="anet-cvv" class="authorize-net-field" autocomplete="cc-csc" placeholder="123" />
				</div>
			</fieldset>
			<div id="authorize-net-payment-errors" role="alert" style="color: red;"></div>
			<input type="hidden" name="authorize_net_opaque_data_value" id="authorize_net_opaque_data_value" />
			<input type="hidden" name="authorize_net_opaque_data_descriptor" id="authorize_net_opaque_data_descriptor" />
		</div>
		<?php
		do_action( 'ohmylms_authorize_net_payment_fields', $this->id );

		ob_end_flush();
	}

	/**
	 * Enqueue scripts and styles for the payment gateway.
	 */
	public function payment_scripts() {
		if ( ! is_ohmylms_checkout() && is_ohmylms_order_received_page() ) {
			return;
		}

		if ( 'yes' !== $this->enabled ) {
			return;
		}

		$accept_js_url = $this->testmode ? 'https://jstest.authorize.net/v1/Accept.js' : 'https://js.authorize.net/v1/Accept.js';
		wp_register_script( 'authorize-net-accept-js', $accept_js_url, array(), null, true );
		wp_enqueue_script( 'authorize-net-accept-js' );

		if ( ! defined( 'OHMYLMS_VERSION' ) ) {
			define( 'OHMYLMS_VERSION', time() ); // Use time for cache busting during development.
		}

		$custom_js_url     = plugins_url( '/authorize-net.js', __FILE__ );
		$custom_js_version = OHMYLMS_VERSION;

		wp_register_script( 'ohmylms-authorize-net', $custom_js_url, array( 'jquery', 'authorize-net-accept-js' ), $custom_js_version, true );

		$localize_params = array(
			'apiLoginId'                => $this->api_login_id,
			'clientKey'                 => $this->client_key,
			'testmode'                  => $this->testmode ? 'true' : 'false',
			'acceptJsUrl'               => $accept_js_url,
			'ajax_url'                  => admin_url( 'admin-ajax.php' ),
			'checkout_nonce'            => wp_create_nonce( 'ohmylms_authorize_checkout_nonce' ),
			'error_prefix'              => __( 'Payment error: ', 'ohmylms' ),
			'msg_card_number_empty'     => __( 'Card number is required.', 'ohmylms' ),
			'msg_expiration_date_empty' => __( 'Expiration date is required.', 'ohmylms' ),
			'msg_cvv_empty'             => __( 'CVV is required.', 'ohmylms' ),
			'msg_opaque_data_error'     => __( 'There was an error processing your payment details. Please try again.', 'ohmylms' ),
		);

		wp_localize_script( 'ohmylms-authorize-net', 'ohmylms_authnet_params', $localize_params );
		wp_enqueue_script( 'ohmylms-authorize-net' );
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int   $order_id Order identifier.
	 * @param false $is_subscription Is subscription.
	 * @return array Result of payment processing.
	 */
	public function process_payment( $order_id, $is_subscription = false ) {

		// Retrieve the order.
		$order = ohmylms_ecommerce_get_order( $order_id ); // Adjusted to use assumed global.
		// Or use a generic function if available: $order = ohmylms_ecommerce_get_order( $order_id );.

		if ( ! $order ) {
			return array(
				'result'  => 'failure',
				'message' => __( 'Order not found. Please try again.', 'ohmylms' ),
			);
		}

		// Get opaque data from POST.
		$opaque_data_descriptor = isset( $_POST['authorize_net_opaque_data_descriptor'] ) ? sanitize_text_field( wp_unslash( $_POST['authorize_net_opaque_data_descriptor'] )) : '';
		$opaque_data_value      = isset( $_POST['authorize_net_opaque_data_value'] ) ? sanitize_text_field( wp_unslash( $_POST['authorize_net_opaque_data_value'] )) : '';

		// Validate that opaque data exists.
		if ( empty( $opaque_data_descriptor ) || empty( $opaque_data_value ) ) {
			return array(
				'result'  => 'failure',
				'message' => __( 'Payment information not processed correctly. Please try again.', 'ohmylms' ),
			);
		}

		// Validate opaque data format.
		if ( strpos( $opaque_data_descriptor, 'COMMON.ACCEPT.INAPP.PAYMENT' ) === false ) {
			return array(
				'result'  => 'failure',
				'message' => __( 'Invalid payment token format. Please try again.', 'ohmylms' ),
			);
		}

		$api = new AuthorizeNetAPI(
			$this->api_login_id,
			$this->transaction_key,
			$this->signature_key,
			$this->testmode
		);

		// Create customer profile for future recurring payments.
		$customer_profile_id         = null;
		$customer_payment_profile_id = null;
		$user_id                     = $order->get_user_id();

		// Create profile if user exists and doesn't already have one.
		if ( $user_id && (bool) $is_subscription ) {
			// Check if user already has a customer profile.
			$existing_profile_id         = get_user_meta( $user_id, '_authorize_net_customer_profile_id', true );
			$existing_payment_profile_id = get_user_meta( $user_id, '_authorize_net_payment_profile_id', true );

			// Only create new profile if one doesn't exist.
			if ( empty( $existing_profile_id ) || empty( $existing_payment_profile_id ) ) {
				$bill_to = array(
					'firstName' => $order->get_first_name() ? $order->get_first_name() : 'N/A',
					'lastName'  => $order->get_last_name() ? $order->get_last_name() : 'N/A',
					'address'   => $order->get_address() ? $order->get_address() : 'N/A',
					'city'      => $order->get_city() ? $order->get_city() : 'N/A',
					'state'     => $order->get_state() ? $order->get_state() : 'N/A',
					'zip'       => $order->get_postcode() ? $order->get_postcode() : 'N/A',
					'country'   => $order->get_country() ? $order->get_country() : 'N/A',
				);

				$profile_payload = array(
					'profile'        => array(
						'merchantCustomerId' => 'U_' . $user_id . '_' . time(),
						'email'              => $order->get_email(),
						'paymentProfiles'    => array(
							array(
								'customerType' => 'individual',
								'billTo'       => $bill_to,
								'payment'      => array(
									'opaqueData' => array(
										'dataDescriptor' => $opaque_data_descriptor,
										'dataValue'      => $opaque_data_value,
									),
								),
							),
						),
					),
					'validationMode' => $this->testmode ? 'testMode' : 'liveMode',
				);

				$profile_response = $api->createCustomerProfile( $profile_payload );
				if ( ! is_wp_error( $profile_response ) && isset( $profile_response['messages']['resultCode'] ) && 'Ok' === $profile_response['messages']['resultCode'] ) {
					if ( ! empty( $profile_response['customerProfileId'] ) && ! empty( $profile_response['customerPaymentProfileIdList'][0] ) ) {
						$customer_profile_id         = $profile_response['customerProfileId'];
						$customer_payment_profile_id = $profile_response['customerPaymentProfileIdList'][0];

						// Store profile IDs in user meta for future use.
						update_user_meta( $user_id, '_authorize_net_customer_profile_id', $customer_profile_id );
						update_user_meta( $user_id, '_authorize_net_payment_profile_id', $customer_payment_profile_id );

					}
				} else {
					// Log profile creation failure but continue with regular payment.
					$profile_error = is_wp_error( $profile_response ) ? $profile_response->get_error_message() : ( $profile_response['messages']['message'][0]['text'] ?? 'Unknown error' );
				}
			} else {
				// Use existing profile IDs.
				$customer_profile_id         = $existing_profile_id;
				$customer_payment_profile_id = $existing_payment_profile_id;
			}
		}

		// Prepare transaction payload with correct element order per Authorize.Net XML schema
		// Schema order: transactionType -> amount -> payment/profile -> order -> billTo -> other optional elements.
		$transaction_payload = array(
			'transactionType' => 'authCaptureTransaction',
			'amount'          => number_format( $order->get_total(), 2, '.', '' ),
		);

		// Add payment method before order.
		if ( ! empty( $customer_profile_id ) && ! empty( $customer_payment_profile_id ) ) {
			// Use the stored customer profile for payment.
			$transaction_payload['profile'] = array(
				'customerProfileId' => $customer_profile_id,
				'paymentProfile'    => array(
					'paymentProfileId' => $customer_payment_profile_id,
				),
			);
		} else {
			// Use opaque data directly (no profile created).
			$transaction_payload['payment'] = array(
				'opaqueData' => array(
					'dataDescriptor' => $opaque_data_descriptor,
					'dataValue'      => $opaque_data_value,
				),
			);
		}

		// Add order info after payment/profile.
		$transaction_payload['order'] = array(
			'invoiceNumber' => $order->get_order_number() ? $order->get_order_number() : $order_id,
			'description'   => sprintf( /* translators: %1$s: text value; %2$s: text value. */
				__( 'Order %1$s from %2$s', 'ohmylms' ),
				$order_id,
				get_bloginfo( 'name' )
			),
		);

		// Add billTo only when using opaque data (not with profile).
		if ( empty( $customer_profile_id ) || empty( $customer_payment_profile_id ) ) {
			$transaction_payload['billTo'] = array(
				'firstName' => $order->get_first_name(),
				'lastName'  => $order->get_last_name(),
				'email'     => $order->get_email(),
			);
		}

		$response = $api->createTransaction( $transaction_payload );

		if ( is_wp_error( $response ) ) {

			return array(
				'result'  => 'failure',
				'message' => __( 'Payment gateway communication error. Please try again.', 'ohmylms' ),
			);
		}

		if ( isset( $response['messages']['resultCode'] ) && 'Ok' === $response['messages']['resultCode'] ) {
			if ( isset( $response['transactionResponse']['responseCode'] ) && '1' == $response['transactionResponse']['responseCode'] ) {
				// Payment successful.
				$transaction_id = $response['transactionResponse']['transId'];

				update_post_meta( $order_id, '_transaction_id', $transaction_id );
				update_post_meta( $order_id, '_payment_method', $this->id );
				update_post_meta( $order_id, '_payment_method_title', $this->get_title() );

				// Store customer profile IDs if they were created.
				if ( ! empty( $customer_profile_id ) ) {
					update_post_meta( $order_id, '_authorize_net_customer_profile_id', $customer_profile_id );
				}
				if ( ! empty( $customer_payment_profile_id ) ) {
					update_post_meta( $order_id, '_authorize_net_payment_profile_id', $customer_payment_profile_id );
				}

				// Store card details for future refunds (required by Authorize.Net API).
				if ( isset( $response['transactionResponse']['accountNumber'] ) ) {
					update_post_meta( $order_id, '_authorize_net_card_last4', $response['transactionResponse']['accountNumber'] );
				}
				if ( isset( $response['transactionResponse']['accountType'] ) ) {
					update_post_meta( $order_id, '_authorize_net_card_type', $response['transactionResponse']['accountType'] );
				}

				// Store additional transaction details for refunds.
				if ( isset( $response['transactionResponse']['avsResultCode'] ) ) {
					update_post_meta( $order_id, '_authorize_net_avs_result', $response['transactionResponse']['avsResultCode'] );
				}
				if ( isset( $response['transactionResponse']['cvvResultCode'] ) ) {
					update_post_meta( $order_id, '_authorize_net_cvv_result', $response['transactionResponse']['cvvResultCode'] );
				}

				// Assuming $order->payment_complete() exists and handles status updates.
				// And $order->add_order_note() exists.
				if ( method_exists( $order, 'payment_complete' ) ) {
					$order->payment_complete( $transaction_id );
				}
				if ( method_exists( $order, 'add_order_note' ) ) {
					$order->add_order_note(
						sprintf( /* translators: %s: text value. */
							__( 'Authorize.Net payment successful. Transaction ID: %s', 'ohmylms' ),
							$transaction_id
						)
					);
				}

				// Reduce stock levels.
				if ( function_exists( 'ohmylms_reduce_order_stock' ) ) {
					ohmylms_reduce_order_stock( $order_id );
				}

				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);
			} else {
				// Transaction declined or error.
				$error_message = __( 'Payment failed.', 'ohmylms' );
				if ( isset( $response['transactionResponse']['errors'][0]['errorText'] ) ) {
					$error_message = $response['transactionResponse']['errors'][0]['errorText'];
				} elseif ( isset( $response['transactionResponse']['messages'][0]['description'] ) ) {
					// Sometimes messages array in transactionResponse contains useful info for declines.
					$error_message = $response['transactionResponse']['messages'][0]['description'];
				}
				if ( method_exists( $order, 'add_order_note' ) ) {
					$order->add_order_note(
						sprintf( /* translators: %s: text value. */
							__( 'Authorize.Net payment failed: %s', 'ohmylms' ),
							$error_message
						)
					);
				}
				return array(
					'result'  => 'failure',
					'message' => $error_message,
				);
			}
		} elseif ( isset( $response['messages']['resultCode'] ) && 'Error' === $response['messages']['resultCode'] ) {
			// Main API error.
			$main_error_message = __( 'An error occurred with the payment gateway.', 'ohmylms' );
			$error_codes        = array();

			if ( isset( $response['messages']['message'] ) && is_array( $response['messages']['message'] ) ) {
				foreach ( $response['messages']['message'] as $message ) {
					if ( isset( $message['code'] ) ) {
						$error_codes[] = $message['code'];
					}
				}
				$main_error_message = $response['messages']['message'][0]['text'];
			}

			// Handle specific E00076 errors (invalid opaque data).
			if ( in_array( 'E00076', $error_codes ) ) {
				$main_error_message = __( 'There was an issue processing your payment information. Please refresh the page and try again, or use a different card.', 'ohmylms' );
			}

			return array(
				'result'  => 'failure',
				'message' => $main_error_message,
			);
		}

		// Fallback for unexpected response structure.
		return array(
			'result'  => 'failure',
			'message' => __( 'An unexpected error occurred. Please try again.', 'ohmylms' ),
		);
	}

	/**
	 * Process a subscription payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @param int $membership_id Membership ID (optional).
	 * @return array Result of subscription payment processing.
	 */
	public function process_subscription_payment( $order_id, $membership_id = 0 ) {
		global $ohmylms_ecommerce; // Assuming this global object provides LMS order functionality.

		$order = ohmylms_ecommerce_get_order( $order_id );
		if ( ! $order ) {
			return array(
				'result'  => 'failure',
				'message' => __( 'Order not found.', 'ohmylms' ),
			);
		}

		$nonce = isset( $_POST['security'] ) ? sanitize_text_field( wp_unslash( $_POST['security'] )) : '';
		if ( ! wp_verify_nonce( $nonce, 'ohmylms_authorize_checkout_nonce' ) ) {
			return array(
				'result'  => 'failure',
				'message' => __( 'Security check failed.', 'ohmylms' ),
			);
		}

		$opaque_data_descriptor = isset( $_POST['authorize_net_opaque_data_descriptor'] ) ? sanitize_text_field( wp_unslash( $_POST['authorize_net_opaque_data_descriptor'] )) : '';
		$opaque_data_value      = isset( $_POST['authorize_net_opaque_data_value'] ) ? sanitize_text_field( wp_unslash( $_POST['authorize_net_opaque_data_value'] )) : '';

		if ( empty( $opaque_data_descriptor ) || empty( $opaque_data_value ) ) {
			return array(
				'result'  => 'failure',
				'message' => __( 'Payment token not found.', 'ohmylms' ),
			);
		}

		// require_once __DIR__ . '/AuthorizeNet/AuthorizeNetAPI.php';.
		$api = new AuthorizeNetAPI( $this->api_login_id, $this->transaction_key, $this->signature_key, $this->testmode );

		// Customer Profile Handling.
		$customer_profile_id         = null;
		$customer_payment_profile_id = null;
		$user_id                     = $order->get_user_id(); // Assuming method exists.

		// TODO: Implement retrieval of existing customer_profile_id if available for the user.
		// $existing_profile_id = get_user_meta( $user_id, '_authorize_net_customer_profile_id', true );.

		// For this example, we always create a new profile. In production, check for existing first.
		if ( $user_id ) {
			$bill_to = array(
				// TODO: Populate these from $order->get_billing_first_name(), get_billing_last_name(), etc.
				'firstName' => $order->get_billing_first_name() ? $order->get_billing_first_name() : 'N/A',
				'lastName'  => $order->get_billing_last_name() ? $order->get_billing_last_name() : 'N/A',
				'address'   => $order->get_billing_address_1() ? $order->get_billing_address_1() : 'N/A',
				'city'      => $order->get_billing_city() ? $order->get_billing_city() : 'N/A',
				'state'     => $order->get_billing_state() ? $order->get_billing_state() : 'N/A',
				'zip'       => $order->get_billing_postcode() ? $order->get_billing_postcode() : 'N/A',
				'country'   => $order->get_billing_country() ? $order->get_billing_country() : 'N/A',
			);
			if ( $order->get_billing_phone() ) {
				$bill_to['phoneNumber'] = $order->get_billing_phone();
			}

			$profile_payload_for_api = array(
				'profile'        => array(
					'merchantCustomerId' => 'CRTLMS_USER_' . $user_id . '_' . time(), // Ensure uniqueness.
					'email'              => $order->get_billing_email(),
					'description'        => sprintf( /* translators: %s: text value. */
						__( 'OhMyLMS Customer Profile for User ID: %s', 'ohmylms' ),
						$user_id
					),
					'paymentProfiles'    => array(
						array(
							'customerType' => 'individual',
							'billTo'       => $bill_to,
							'payment'      => array(
								'opaqueData' => array(
									'dataDescriptor' => $opaque_data_descriptor,
									'dataValue'      => $opaque_data_value,
								),
							),
						),
					),
				),
				'validationMode' => $this->testmode ? 'test_mode' : 'liveMode', // Use testMode for sandbox, liveMode for production.
			);

			$profile_response = $api->createCustomerProfile( $profile_payload_for_api );

			if ( is_wp_error( $profile_response ) ) {
				return array(
					'result'  => 'failure',
					'message' => __( 'Failed to create customer payment profile (API error).', 'ohmylms' ),
				);
			}

			if ( isset( $profile_response['messages']['resultCode'] ) && 'Ok' === $profile_response['messages']['resultCode'] ) {
				if ( ! empty( $profile_response['customerProfileId'] ) && ! empty( $profile_response['customerPaymentProfileIdList'][0] ) ) {
					$customer_profile_id         = $profile_response['customerProfileId'];
					$customer_payment_profile_id = $profile_response['customerPaymentProfileIdList'][0];
					// Store these IDs for future use (e.g., in user meta).
					if ( $user_id ) {
						update_user_meta( $user_id, '_authorize_net_customer_profile_id', $customer_profile_id );
						update_user_meta( $user_id, '_authorize_net_payment_profile_id', $customer_payment_profile_id );
					}
				} else {
					$error_msg = $profile_response['messages']['message'][0]['text'] ?? 'Failed to retrieve profile IDs from response.';
					return array(
						'result'  => 'failure',
						'message' => sprintf( /* translators: %s: text value. */
							__( 'Failed to create customer payment profile: %s', 'ohmylms' ),
							$error_msg
						),
					);
				}
			} else {
				// Error creating profile.
				$error_message = $profile_response['messages']['message'][0]['text'] ?? __( 'Unknown error creating customer profile.', 'ohmylms' );
				return array(
					'result'  => 'failure',
					'message' => $error_message,
				);
			}
		} else {
			// User ID not found, cannot create profile based on user.
			// This path should ideally not be taken for subscriptions.
			return array(
				'result'  => 'failure',
				'message' => __( 'User information is required for subscriptions.', 'ohmylms' ),
			);
		}

		if ( ! $customer_profile_id || ! $customer_payment_profile_id ) {
			return array(
				'result'  => 'failure',
				'message' => __( 'Failed to set up customer profile for subscription. Please try again.', 'ohmylms' ),
			);
		}

		// Prepare Subscription Data
		// TODO: These parameters (interval length, unit, totalOccurrences, amount for subscription)
		// must be dynamically fetched based on the selected membership/subscription plan.
		// Using placeholders for now.
		$subscription_payload = array(
			'name'            => sprintf( /* translators: %s: text value. */
				__( 'Subscription for Order %s', 'ohmylms' ),
				$order_id
			), // TODO: Get item name.
			'paymentSchedule' => array(
				'interval'         => array(
					'length' => '1', // e.g., 1.
					'unit'   => 'months', // e.g., 'days' or 'months'.
				),
				'startDate'        => date( 'Y-m-d' ), // Today, or specific start date from plan.
				'totalOccurrences' => '9999',         // For ongoing until cancelled. Or a specific number.
				// 'trialOccurrences' => 0, // Optional: if trial period applies
				// 'trialAmount' => 0.00,   // Optional: if trial period applies.
			),
			'amount'          => number_format( $order->get_total(), 2, '.', '' ), // Amount per occurrence.
			'profile'         => array(
				'customerProfileId'        => $customer_profile_id,
				'customerPaymentProfileId' => $customer_payment_profile_id,
				// 'customerAddressId' => $customer_address_id, // Optional, if shipping address is separate
			),
		);
		// Optional: Add order details to subscription for reference.
		$subscription_payload['order'] = array(
			'invoiceNumber' => $order->get_order_number() ? $order->get_order_number() : $order_id,
			'description'   => sprintf( /* translators: %1$s: text value; %2$s: text value. */
				__( 'Subscription for Order %1$s from %2$s', 'ohmylms' ),
				$order_id,
				get_bloginfo( 'name' )
			),
		);

		$response = $api->createSubscription( $subscription_payload );

		if ( is_wp_error( $response ) ) {
			return array(
				'result'  => 'failure',
				'message' => __( 'Subscription setup communication error.', 'ohmylms' ),
			);
		}

		if ( isset( $response['messages']['resultCode'] ) && 'Ok' === $response['messages']['resultCode'] ) {
			if ( ! empty( $response['subscriptionId'] ) ) {
				$subscription_id = $response['subscriptionId'];

				update_post_meta( $order_id, '_authorize_net_subscription_id', $subscription_id );
				update_post_meta( $order_id, '_authorize_net_customer_profile_id', $customer_profile_id ); // Persist profile ID with order.
				update_post_meta( $order_id, '_authorize_net_payment_profile_id', $customer_payment_profile_id ); // Persist payment profile ID.
				update_post_meta( $order_id, '_payment_method', $this->id . '_subscription' ); // e.g., 'authorize_net_subscription'.
				update_post_meta( $order_id, '_payment_method_title', $this->get_title() . ' ' . __( '(Subscription)', 'ohmylms' ) );

				// If $membership_id is provided, associate Authorize.Net subscription ID with it.
				if ( $membership_id && function_exists( 'update_membership_meta' ) ) { // Assuming a generic function.
					update_membership_meta( $membership_id, '_authorize_net_subscription_id', $subscription_id );
				}

				if ( method_exists( $order, 'payment_complete' ) ) {
					// For subscriptions, the first payment might be $0 if there's a trial.
					// Or it might be the first installment. Authorize.Net handles the schedule.
					// We mark the order as complete, assuming the subscription setup itself is the success.
					// If an initial payment was processed as part of createSubscription, transId might be available.
					// However, createSubscription response doesn't usually include a transId directly.
					// The payment is part of the subscription schedule.
					$order->payment_complete( $subscription_id ); // Pass subscription_id as reference.
				}
				if ( method_exists( $order, 'add_order_note' ) ) {
					$order->add_order_note(
						sprintf( /* translators: %1$s: text value; %2$s: text value. */
							__( 'Authorize.Net subscription started. Subscription ID: %1$s. Customer Profile ID: %2$s.', 'ohmylms' ),
							$subscription_id,
							$customer_profile_id
						)
					);
				}

				// Reduce stock levels if applicable for the initial order of a subscription product.
				if ( function_exists( 'ohmylms_reduce_order_stock' ) ) {
					ohmylms_reduce_order_stock( $order_id );
				}

				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);
			} else {
				// Subscription created successfully according to resultCode, but subscriptionId is missing.
				$error_message = $response['messages']['message'][0]['text'] ?? __( 'Subscription ID not found in response.', 'ohmylms' );
				return array(
					'result'  => 'failure',
					'message' => $error_message,
				);
			}
		} else {
			// Error creating subscription.
			$error_message = $response['messages']['message'][0]['text'] ?? __( 'Unknown error creating subscription.', 'ohmylms' );
			return array(
				'result'  => 'failure',
				'message' => $error_message,
			);
		}
	}

	/**
	 * Process a refund for an order.
	 *
	 * @param int        $order_id Order ID.
	 * @param float|null $amount Refund amount. Null for full refund.
	 * @param string     $reason Refund reason.
	 * @return bool True on success, false on failure.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		global $ohmylms_ecommerce; // Assuming this global object provides LMS order functionality.

		$order = ohmylms_ecommerce_get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		$original_transaction_id = get_post_meta( $order_id, '_transaction_id', true );
		if ( empty( $original_transaction_id ) ) {
			$message = __( 'Original transaction ID not found for this order. Cannot process refund.', 'ohmylms' );
			if ( method_exists( $order, 'add_order_note' ) ) {
				$order->add_order_note( $message );
			}
			return false;
		}

		if ( is_null( $amount ) ) {
			$amount = $order->get_total(); // Assumes get_total() returns the original, fully paid amount.
		}

		if ( ! is_numeric( $amount ) || $amount <= 0 ) {
			$message = __( 'Invalid refund amount.', 'ohmylms' );
			if ( method_exists( $order, 'add_order_note' ) ) {
				$order->add_order_note( $message );
			}
			return false;
		}

		// require_once __DIR__ . '/AuthorizeNet/AuthorizeNetAPI.php';.
		$api = new AuthorizeNetAPI( $this->api_login_id, $this->transaction_key, $this->signature_key, $this->testmode );

		// Skip void and go directly to refund for testing
		// Get stored card details for refund.
		$card_last4 = get_post_meta( $order_id, '_authorize_net_card_last4', true );

		if ( empty( $card_last4 ) ) {
			$message = __( 'Card details not found for this order. Cannot process refund without card information.', 'ohmylms' );
			if ( method_exists( $order, 'add_order_note' ) ) {
				$order->add_order_note( $message );
			}
			return false;
		}

		$refund_payload = array(
			'transactionType' => 'refundTransaction',
			'amount'          => number_format( $amount, 2, '.', '' ),
			'payment'         => array(
				'creditCard' => array(
					'cardNumber'     => $card_last4,
					'expirationDate' => 'XXXX',
				),
			),
		);

		$response = $api->createTransaction( $refund_payload );

		$note_prefix = __( 'Authorize.Net Refund:', 'ohmylms' ) . ' ';

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			if ( method_exists( $order, 'add_order_note' ) ) {
				$order->add_order_note(
					$note_prefix . sprintf( /* translators: %s: text value. */
						__( 'API Error: %s', 'ohmylms' ),
						$error_message
					)
				);
			}
			return false;
		}
		if ( isset( $response['messages']['resultCode'] ) && 'Ok' === $response['messages']['resultCode'] ) {
			if ( isset( $response['transactionResponse']['responseCode'] ) && '1' == $response['transactionResponse']['responseCode'] ) {
				// Refund successful.
				$refund_trans_id = $response['transactionResponse']['transId'];
				$success_message = sprintf(
					/* translators: %1$s: text value; %2$s: text value; %3$s: text value. */
					__( 'Refund successful. Amount: %1$s. Transaction ID: %2$s. Reason: %3$s', 'ohmylms' ),
					number_format( $amount, 2 ),
					$refund_trans_id,
					empty( $reason ) ? __( 'N/A', 'ohmylms' ) : sanitize_text_field( $reason )
				);

				if ( method_exists( $order, 'add_order_note' ) ) {
					$order->add_order_note( $note_prefix . $success_message );
				}
				// Optionally, update order status to 'refunded' or similar.
				// update_post_meta( $order_id, '_authorize_net_refund_transaction_id', $refund_trans_id );.

				return true;

			} else {
				// Transaction declined or error during refund.
				$error_text = __( 'Refund processing failed.', 'ohmylms' );
				if ( isset( $response['transactionResponse']['errors'][0]['errorText'] ) ) {
					$error_text = $response['transactionResponse']['errors'][0]['errorText'];
				} elseif ( isset( $response['transactionResponse']['messages'][0]['description'] ) ) {
					$error_text = $response['transactionResponse']['messages'][0]['description'];
				}
				if ( method_exists( $order, 'add_order_note' ) ) {
					$order->add_order_note(
						$note_prefix . sprintf( /* translators: %s: text value. */
							__( 'Failed: %s', 'ohmylms' ),
							$error_text
						)
					);
				}
				return false;
			}
		} elseif ( isset( $response['messages']['resultCode'] ) && 'Error' === $response['messages']['resultCode'] ) {
			// Main API error.
			$main_error_message = __( 'Refund API call failed.', 'ohmylms' );
			if ( isset( $response['messages']['message'][0]['text'] ) ) {
				$main_error_message = $response['messages']['message'][0]['text'];
			}
			if ( method_exists( $order, 'add_order_note' ) ) {
				$order->add_order_note(
					$note_prefix . sprintf( /* translators: %s: text value. */
						__( 'API Error: %s', 'ohmylms' ),
						$main_error_message
					)
				);
			}
			return false;
		}

		// Fallback for unexpected response structure.
		$unknown_error_message = __( 'An unexpected error occurred during refund processing.', 'ohmylms' );
		if ( method_exists( $order, 'add_order_note' ) ) {
			$order->add_order_note( $note_prefix . $unknown_error_message );
		}
		return false;
	}

	/**
	 * Cancels an Authorize.Net subscription and optionally refunds the last payment.
	 *
	 * @param int        $lms_subscription_id The LMS-specific subscription ID.
	 * @param int        $order_id The ID of the original order that created the subscription.
	 * @param float|null $amount The amount to refund. If null, original order total is used.
	 * @param string     $reason Optional reason for cancellation and refund.
	 * @return bool True if cancellation (and refund, if applicable) was successful, false otherwise.
	 */
	public function refund_and_cancel_subscription( $lms_subscription_id, $order_id, $amount = null, $reason = '' ) {
		global $ohmylms_ecommerce;
		$order = ohmylms_ecommerce_get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		$authnet_subscription_id = get_post_meta( $order_id, '_authorize_net_subscription_id', true );

		if ( empty( $authnet_subscription_id ) ) {
			$message = sprintf( /* translators: %1$d: number; %2$s: text value. */
				__( 'Authorize.Net Subscription ID not found for order %1$d (LMS Sub ID: %2$s). Cannot cancel.', 'ohmylms' ),
				$order_id,
				$lms_subscription_id
			);
			if ( method_exists( $order, 'add_order_note' ) ) {
				$order->add_order_note( $message );
			}
			return false;
		}

		// require_once __DIR__ . '/AuthorizeNet/AuthorizeNetAPI.php';.
		$api = new AuthorizeNetAPI( $this->api_login_id, $this->transaction_key, $this->signature_key, $this->testmode );

		// Step 1: Cancel the subscription.
		$cancel_response    = $api->cancelSubscription( $authnet_subscription_id );
		$cancel_note_prefix = __( 'Authorize.Net Subscription Cancellation:', 'ohmylms' ) . ' ';

		$cancellation_successful = false;
		if ( is_wp_error( $cancel_response ) ) {
			$error_message = $cancel_response->get_error_message();
			if ( method_exists( $order, 'add_order_note' ) ) {
				$order->add_order_note(
					$cancel_note_prefix . sprintf( /* translators: %s: text value. */
						__( 'API Error: %s', 'ohmylms' ),
						$error_message
					)
				);
			}
			// Do not return yet, attempt refund if $amount > 0 or refund is intended.
			// However, if cancellation is the primary goal and it fails, maybe return false.
			// For now, we'll note the failure and proceed to refund if amount is specified.
			// Let's decide to fail early if cancellation fails.
			return false;

		} elseif ( isset( $cancel_response['messages']['resultCode'] ) && 'Ok' === $cancel_response['messages']['resultCode'] ) {
			$success_message = sprintf( /* translators: %s: text value. */
				__( 'Subscription ID %s successfully cancelled.', 'ohmylms' ),
				$authnet_subscription_id
			);
			if ( method_exists( $order, 'add_order_note' ) ) {
				$order->add_order_note( $cancel_note_prefix . $success_message );
			}
			$cancellation_successful = true;
		} else {
			// Cancellation failed at API level.
			$error_text = $cancel_response['messages']['message'][0]['text'] ?? __( 'Unknown error during subscription cancellation.', 'ohmylms' );
			if ( method_exists( $order, 'add_order_note' ) ) {
				$order->add_order_note(
					$cancel_note_prefix . sprintf( /* translators: %s: text value. */
						__( 'Failed: %s', 'ohmylms' ),
						$error_text
					)
				);
			}
			return false; // If cancellation fails, stop here.
		}

		// If we reach here, cancellation was successful. Now, process refund if amount is specified.
		if ( is_null( $amount ) || ( is_numeric( $amount ) && $amount <= 0 ) ) {
			// No refund requested or amount is invalid, but cancellation was successful.
			// Update LMS subscription status to cancelled if applicable by the calling function/hook.
			return true; // Cancellation succeeded, no refund action needed/valid.
		}

		// Step 2: Process the refund
		// TODO: This refunds against the *original* order's transaction.
		// A more robust solution would find the *last* transaction ID for this subscription.
		$original_order_transaction_id = get_post_meta( $order_id, '_transaction_id', true );
		if ( empty( $original_order_transaction_id ) ) {
			$message = __( 'Original transaction ID for refund not found with the initial order. Cannot process refund part.', 'ohmylms' );
			if ( method_exists( $order, 'add_order_note' ) ) {
				$order->add_order_note( $cancel_note_prefix . $message ); // Add to previous cancellation success note.
			}
			return false; // Cancellation succeeded, but required refund part cannot proceed.
		}

		// Get stored card details for refund
		// Get stored card details - required for refunds in most Authorize.Net configurations.
		$card_last4 = get_post_meta( $order_id, '_authorize_net_card_last4', true );

		if ( empty( $card_last4 ) ) {
			$message = __( 'Card details not found for this order. Cannot process subscription refund without card information.', 'ohmylms' );
			if ( method_exists( $order, 'add_order_note' ) ) {
				$order->add_order_note( $cancel_note_prefix . $message );
			}
			return false;
		}

		$amount_to_refund = $amount; // Amount is already validated to be > 0 if not null.

		$refund_payload = array(
			'transactionType' => 'refundTransaction',
			'amount'          => number_format( $amount_to_refund, 2, '.', '' ),
			'refTransId'      => $original_order_transaction_id,
		);

		$refund_response    = $api->createTransaction( $refund_payload );
		$refund_note_prefix = __( 'Authorize.Net Subscription Refund:', 'ohmylms' ) . ' ';

		if ( is_wp_error( $refund_response ) ) {
			$error_message = $refund_response->get_error_message();
			if ( method_exists( $order, 'add_order_note' ) ) {
				$order->add_order_note(
					$refund_note_prefix . sprintf( /* translators: %s: text value. */
						__( 'Subscription cancelled, but refund API Error: %s', 'ohmylms' ),
						$error_message
					)
				);
			}
			return false; // Cancellation was successful, but refund failed.
		}

		if ( isset( $refund_response['messages']['resultCode'] ) && 'Ok' === $refund_response['messages']['resultCode'] ) {
			if ( isset( $refund_response['transactionResponse']['responseCode'] ) && '1' == $refund_response['transactionResponse']['responseCode'] ) {
				// Refund successful.
				$refund_trans_id = $refund_response['transactionResponse']['transId'];
				$success_message = sprintf(
					/* translators: %1$s: text value; %2$s: text value; %3$s: text value. */
					__( 'Subscription cancelled. Refund successful. Amount: %1$s. Refund Transaction ID: %2$s. Reason: %3$s', 'ohmylms' ),
					number_format( $amount_to_refund, 2 ),
					$refund_trans_id,
					empty( $reason ) ? __( 'N/A', 'ohmylms' ) : sanitize_text_field( $reason )
				);
				if ( method_exists( $order, 'add_order_note' ) ) {
					$order->add_order_note( $refund_note_prefix . $success_message );
				}
				return true; // Both cancellation and refund were successful.
			} else {
				// Transaction declined or error during refund.
				$error_text = $refund_response['transactionResponse']['errors'][0]['errorText'] ?? __( 'Refund processing failed.', 'ohmylms' );
				if ( method_exists( $order, 'add_order_note' ) ) {
					$order->add_order_note(
						$refund_note_prefix . sprintf( /* translators: %s: text value. */
							__( 'Subscription cancelled, but refund failed: %s', 'ohmylms' ),
							$error_text
						)
					);
				}
				return false; // Cancellation was successful, but refund failed.
			}
		} elseif ( isset( $refund_response['messages']['resultCode'] ) && 'Error' === $refund_response['messages']['resultCode'] ) {
			// Main API error for refund.
			$main_error_message = $refund_response['messages']['message'][0]['text'] ?? __( 'Refund API call failed.', 'ohmylms' );
			if ( method_exists( $order, 'add_order_note' ) ) {
				$order->add_order_note(
					$refund_note_prefix . sprintf( /* translators: %s: text value. */
						__( 'Subscription cancelled, but refund API Error: %s', 'ohmylms' ),
						$main_error_message
					)
				);
			}
			return false; // Cancellation was successful, but refund failed.
		}

		// Fallback for unexpected refund response structure.
		$unknown_error_message = __( 'An unexpected error occurred during subscription refund processing.', 'ohmylms' );
		if ( method_exists( $order, 'add_order_note' ) ) {
			$order->add_order_note( $refund_note_prefix . $unknown_error_message );
		}
		return false; // Cancellation was successful, but refund failed.
	}

	/**
	 * Process a recurring payment for a subscription renewal.
	 *
	 * This method is called by WP Cron when a subscription renewal needs to be charged.
	 * It uses the stored customer profile from the original order to charge the renewal.
	 * This is NOT handled by Authorize.Net's ARB - it's a manual one-time charge.
	 *
	 * @param int   $original_order_id The ID of the original order that created the subscription.
	 * @param int   $renewal_order_id The ID of the renewal order.
	 * @param float $amount The amount to charge for this renewal.
	 * @param int   $subscription_id The ID of the ohmylms-subscription post.
	 * @param int   $student_id The ID of the student/customer.
	 * @return array Result of the payment attempt.
	 * @since 1.0.0
	 */
	public function process_recurring_payment( $original_order_id, $renewal_order_id, $amount, $subscription_id, $student_id ) {
		// Validate and sanitize inputs.
		$original_order_id = absint( $original_order_id );
		$renewal_order_id  = absint( $renewal_order_id );
		$subscription_id   = absint( $subscription_id );
		$student_id        = absint( $student_id );
		$amount            = floatval( $amount );

		// Get orders.
		$original_order = ohmylms_ecommerce_get_order( $original_order_id );
		$renewal_order  = ohmylms_ecommerce_get_order( $renewal_order_id );

		if ( ! $original_order ) {
			return array(
				'result'  => 'failure',
				'message' => __( 'Original order not found for recurring payment.', 'ohmylms' ),
			);
		}

		if ( ! $renewal_order ) {
			return array(
				'result'  => 'failure',
				'message' => __( 'Renewal order not found for recurring payment.', 'ohmylms' ),
			);
		}

		// Get customer profile IDs from original order or user meta.
		$customer_profile_id         = get_post_meta( $original_order_id, '_authorize_net_customer_profile_id', true );
		$customer_payment_profile_id = get_post_meta( $original_order_id, '_authorize_net_payment_profile_id', true );

		// If not found in order meta, try user meta.
		if ( empty( $customer_profile_id ) || empty( $customer_payment_profile_id ) ) {
			$user_id = $original_order->get_user_id();
			if ( $user_id ) {
				$customer_profile_id         = get_user_meta( $user_id, '_authorize_net_customer_profile_id', true );
				$customer_payment_profile_id = get_user_meta( $user_id, '_authorize_net_payment_profile_id', true );
			}
		}

		if ( empty( $customer_profile_id ) || empty( $customer_payment_profile_id ) ) {
			return array(
				'result'  => 'failure',
				'message' => __( 'Customer profile information not found. Cannot process recurring payment.', 'ohmylms' ),
			);
		}

		// Initialize API.
		$api = new AuthorizeNetAPI( $this->api_login_id, $this->transaction_key, $this->signature_key, $this->testmode );

		// Create transaction using stored customer profile (one-time charge).
		$transaction_payload = array(
			'transactionType' => 'authCaptureTransaction',
			'amount'          => number_format( $amount, 2, '.', '' ),
			'profile'         => array(
				'customerProfileId' => $customer_profile_id,
				'paymentProfile'    => array(
					'paymentProfileId' => $customer_payment_profile_id,
				),
			),
			'order'           => array(
				'invoiceNumber' => $renewal_order->get_order_number() ? $renewal_order->get_order_number() : $renewal_order_id,
				'description'   => sprintf(
					/* translators: %1$s: text value; %2$s: text value. */
					__( 'Renewal for Subscription #%1$s - Order #%2$s', 'ohmylms' ),
					$subscription_id,
					$renewal_order_id
				),
			),
			'customerIP'      => $this->get_ip_address(),
		);

		$response = $api->createTransaction( $transaction_payload );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			if ( method_exists( $renewal_order, 'add_order_note' ) ) {
				$renewal_order->add_order_note(
					sprintf( /* translators: %s: text value. */
						__( 'Authorize.Net recurring payment failed. API Error: %s', 'ohmylms' ),
						esc_html( $error_message )
					)
				);
			}
			return array(
				'result'  => 'failure',
				'message' => $error_message,
			);
		}

		// Check response.
		if ( isset( $response['messages']['resultCode'] ) && 'Ok' === $response['messages']['resultCode'] ) {
			if ( isset( $response['transactionResponse']['responseCode'] ) && '1' == $response['transactionResponse']['responseCode'] ) {
				// Payment successful.
				$transaction_id = $response['transactionResponse']['transId'];

				// Store transaction details in renewal order.
				update_post_meta( $renewal_order_id, '_transaction_id', $transaction_id );
				update_post_meta( $renewal_order_id, '_payment_method', $this->id );
				update_post_meta( $renewal_order_id, '_payment_method_title', $this->get_title() );

				// Store customer profile IDs for future use.
				update_post_meta( $renewal_order_id, '_authorize_net_customer_profile_id', $customer_profile_id );
				update_post_meta( $renewal_order_id, '_authorize_net_payment_profile_id', $customer_payment_profile_id );

				// Store card details if available in response.
				if ( isset( $response['transactionResponse']['accountNumber'] ) ) {
					update_post_meta( $renewal_order_id, '_authorize_net_card_last4', $response['transactionResponse']['accountNumber'] );
				}
				if ( isset( $response['transactionResponse']['accountType'] ) ) {
					update_post_meta( $renewal_order_id, '_authorize_net_card_type', $response['transactionResponse']['accountType'] );
				}

				// Mark payment complete.
				if ( method_exists( $renewal_order, 'payment_complete' ) ) {
					$renewal_order->payment_complete( $transaction_id );
				}

				// Add order note.
				if ( method_exists( $renewal_order, 'add_order_note' ) ) {
					$renewal_order->add_order_note(
						sprintf(
							/* translators: %1$s: text value; %2$s: text value. */
							__( 'Authorize.Net recurring payment successful. Transaction ID: %1$s, Amount: %2$s', 'ohmylms' ),
							$transaction_id,
							number_format( $amount, 2 )
						)
					);
				}

				return array(
					'result'         => 'success',
					'transaction_id' => $transaction_id,
					'redirect'       => $this->get_return_url( $renewal_order ),
				);
			} else {
				// Transaction declined or error.
				$error_message = __( 'Recurring payment declined.', 'ohmylms' );
				if ( isset( $response['transactionResponse']['errors'][0]['errorText'] ) ) {
					$error_message = $response['transactionResponse']['errors'][0]['errorText'];
				} elseif ( isset( $response['transactionResponse']['messages'][0]['description'] ) ) {
					$error_message = $response['transactionResponse']['messages'][0]['description'];
				}

				if ( method_exists( $renewal_order, 'add_order_note' ) ) {
					$renewal_order->add_order_note(
						sprintf( /* translators: %s: text value. */
							__( 'Authorize.Net recurring payment failed: %s', 'ohmylms' ),
							$error_message
						)
					);
				}

				return array(
					'result'  => 'failure',
					'message' => $error_message,
				);
			}
		} elseif ( isset( $response['messages']['resultCode'] ) && 'Error' === $response['messages']['resultCode'] ) {
			// Main API error.
			$main_error_message = __( 'Recurring payment API error.', 'ohmylms' );
			if ( isset( $response['messages']['message'][0]['text'] ) ) {
				$main_error_message = $response['messages']['message'][0]['text'];
			}

			if ( method_exists( $renewal_order, 'add_order_note' ) ) {
				$renewal_order->add_order_note(
					sprintf( /* translators: %s: text value. */
						__( 'Authorize.Net recurring payment API error: %s', 'ohmylms' ),
						$main_error_message
					)
				);
			}

			return array(
				'result'  => 'failure',
				'message' => $main_error_message,
			);
		}

		// Fallback for unexpected response structure.

		if ( method_exists( $renewal_order, 'add_order_note' ) ) {
			$renewal_order->add_order_note( __( 'Authorize.Net recurring payment: Unexpected API response.', 'ohmylms' ) );
		}

		return array(
			'result'  => 'failure',
			'message' => __( 'An unexpected error occurred during recurring payment processing.', 'ohmylms' ),
		);
	}

	/**
	 * Get user IP address helper method.
	 *
	 * @return string
	 */
	private function get_ip_address() {
		if ( isset( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) );
		} elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			return (string) rest_is_ip_address( trim( current( preg_split( '/,/', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ) ) ) );
		} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return '';
	}
}
?>
