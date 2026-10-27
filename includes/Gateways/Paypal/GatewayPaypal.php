<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class GatewayPaypal
 * Handles PayPal payment gateway interactions with vaulting for one-time and recurring payments.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Commerce\Abstracts\PaymentGateway;
use OhMyLMS\Gateways\Paypal\PaypalAPI;
use OhMyLMS\Integrations\Funnel\Includes\FunnelManager;

/**
 * Class GatewayPaypal
 * Handles PayPal payment gateway interactions with vaulting for one-time and recurring payments.
 */
class GatewayPaypal extends PaymentGateway {

	/**
	 * Client id.
	 *
	 * @var mixed
	 */
	private $client_id;
	/**
	 * Client secret.
	 *
	 * @var mixed
	 */
	private $client_secret;
	/**
	 * Access token.
	 *
	 * @var mixed
	 */
	private $access_token;
	/**
	 * Api url.
	 *
	 * @var mixed
	 */
	private $api_url;
	/**
	 * Currency.
	 *
	 * @var mixed
	 */
	private $currency;
	/**
	 * Min amount.
	 *
	 * @var mixed
	 */
	private $min_amount;
	/**
	 * Webhook url.
	 *
	 * @var mixed
	 */
	private $webhook_url;
	/**
	 * Webhook id.
	 *
	 * @var mixed
	 */
	private $webhook_id;
	/**
	 * Supports subscription api.
	 *
	 * @var mixed
	 */
	public $supports_subscription_api = true;
	/**
	 * Paypal api.
	 *
	 * @var mixed
	 */
	private $paypal_api;

	/**
	 * Paypal constructor.
	 * Initializes the PayPal API client with client credentials and sandbox flag.
	 */
	public function __construct() {
		$this->id                   = 'paypal';
		$gateway_settings_key       = 'ohmylms_' . $this->id . '_settings';
		$this->settings             = get_option( $gateway_settings_key, array() );
		$this->title                = $this->get_setting( 'title', __( 'Paypal', 'ohmylms' ) );
		$this->description          = $this->get_setting( 'instruction', __( 'Pay with PayPal payment.', 'ohmylms' ) );
		$this->subscription_support = true;
		$this->testmode             = 'yes' === $this->get_setting( 'test_mode', 'yes' );
		$this->client_id            = $this->testmode
			? $this->get_setting( 'sandbox_client_id', '' )
			: $this->get_setting( 'client_id', '' );
		$this->client_secret        = $this->testmode
			? $this->get_setting( 'sandbox_client_secret', '' )
			: $this->get_setting( 'client_secret', '' );
		$this->webhook_url          = $this->get_setting( 'webhook_url', home_url( '/wp-json/ohmylms/v1/paypal-webhook' ) );
		$this->webhook_id           = $this->get_setting( 'webhook_id', '' );
		$this->has_fields           = true;
		$this->enabled              = $this->get_setting( 'enabled', 'no' );
		$this->order_button_text    = __( 'Place payment', 'ohmylms' );
		$this->currency             = $this->get_current_currency();
		$this->min_amount           = '0.1';
		$this->api_url              = $this->testmode
			? 'https://api-m.sandbox.paypal.com'
			: 'https://api-m.paypal.com';
		add_action( 'template_redirect', array( $this, 'handle_paypal_return' ) );
		add_action( 'rest_api_init', array( $this, 'register_webhook_endpoint' ) );
		$this->paypal_api = new PaypalAPI(
			$this->client_id,
			$this->client_secret,
			$this->testmode,
			$this->currency,
			$this->min_amount
		);
		if ( ! $this->webhook_id && 'yes' === $this->enabled && $this->client_id && $this->client_secret ) {
			$this->create_webhook();
		}
	}


	/**
	 * Get current currency
	 *
	 * @return string
	 */
	private function get_current_currency() {
		return strtoupper( function_exists( 'get_ohmylms_currency' ) ? get_ohmylms_currency() : 'usd' );
	}

	/**
	 * Get payment gateway settings.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_settings() {
		$fields = array(
			array(
				'title'             => __( 'Title', 'ohmylms' ),
				'short_description' => __( 'Enter the title that will appear for PayPal payment during checkout.', 'ohmylms' ),
				'input_type'        => 'text',
				'value'             => $this->title,
				'default_value'     => __( 'PayPal payment', 'ohmylms' ),
				'option_name'       => 'title',
			),
			array(
				'title'             => __( 'Instruction', 'ohmylms' ),
				'short_description' => __( 'Provide detailed instructions on how students should complete PayPal payment', 'ohmylms' ),
				'input_type'        => 'textarea',
				'value'             => $this->get_setting( 'instruction', '' ),
				'default_value'     => __( 'Pay with PayPal payment.', 'ohmylms' ),
				'option_name'       => 'instruction',
			),
			array(
				'title'             => __( 'Test Mode', 'ohmylms' ),
				'short_description' => __( 'Automatically complete orders for testing purposes without actual payment.', 'ohmylms' ),
				'input_type'        => 'switch',
				'default_value'     => 'no',
				'value'             => $this->get_setting( 'test_mode', '' ),
				'option_name'       => 'test_mode',
				'conditional_logic' => array(
					'type'     => 'control',
					'controls' => array( 'sandbox_client_id', 'sandbox_client_secret', 'client_id', 'client_secret' ),
				),
			),
			array(
				'title'             => __( 'Merchant Email', 'ohmylms' ),
				'short_description' => __( 'Enter your merchant email.', 'ohmylms' ),
				'input_type'        => 'text',
				'default_value'     => '',
				'value'             => $this->get_setting( 'merchant_email', '' ),
				'option_name'       => 'merchant_email',
			),
			array(
				'title'             => __( 'Sandbox Client ID', 'ohmylms' ),
				'short_description' => __( 'Enter your Client ID for sandbox environment.', 'ohmylms' ) . ' ' . __( 'How to find your', 'ohmylms' ) . ' <a href="https://developer.paypal.com/tools/sandbox/" target="_blank">' . __( 'Sandbox Publisher Key', 'ohmylms' ) . '</a>',
				'input_type'        => 'text',
				'default_value'     => '',
				'value'             => $this->get_setting( 'sandbox_client_id', '' ),
				'option_name'       => 'sandbox_client_id',
				'conditional_logic' => array(
					'type'       => 'dependent',
					'depends_on' => 'test_mode',
					'show_when'  => 'enabled',
				),
			),
			array(
				'title'             => __( 'Sandbox Client Secret', 'ohmylms' ),
				'short_description' => __( 'Enter your Client Secret for sandbox environment.', 'ohmylms' ) . ' ' . __( 'How to find your', 'ohmylms' ) . ' <a href="https://developer.paypal.com/tools/sandbox/" target="_blank">' . __( 'Sandbox Secret Key', 'ohmylms' ) . '</a>',
				'input_type'        => 'text',
				'default_value'     => '',
				'value'             => $this->get_setting( 'sandbox_client_secret', '' ),
				'option_name'       => 'sandbox_client_secret',
				'conditional_logic' => array(
					'type'       => 'dependent',
					'depends_on' => 'test_mode',
					'show_when'  => 'enabled',
				),
			),
			array(
				'title'             => __( 'Live Client ID', 'ohmylms' ),
				'short_description' => __( 'Enter your Client ID for live environment.', 'ohmylms' ) . ' ' . __( 'How to find your', 'ohmylms' ) . ' <a href="https://developer.paypal.com/tools/sandbox/" target="_blank">' . __( 'Live Publisher Key', 'ohmylms' ) . '</a>',
				'input_type'        => 'text',
				'default_value'     => '',
				'value'             => $this->get_setting( 'client_id', '' ),
				'option_name'       => 'client_id',
				'conditional_logic' => array(
					'type'       => 'dependent',
					'depends_on' => 'test_mode',
					'show_when'  => 'disabled',
				),
			),
			array(
				'title'             => __( 'Live Client Secret', 'ohmylms' ),
				'short_description' => __( 'Enter your Client Secret for live environment.', 'ohmylms' ) . ' ' . __( 'How to find your', 'ohmylms' ) . ' <a href="https://developer.paypal.com/tools/sandbox/" target="_blank">' . __( 'Live Secret Key', 'ohmylms' ) . '</a>',
				'input_type'        => 'text',
				'default_value'     => '',
				'value'             => $this->get_setting( 'client_secret', '' ),
				'option_name'       => 'client_secret',
				'conditional_logic' => array(
					'type'       => 'dependent',
					'depends_on' => 'test_mode',
					'show_when'  => 'disabled',
				),
			),
		// array(
		// 'title' => __('Webhook URL', 'ohmylms'),
		// 'short_description' => __('Enter the webhook URL for PayPal notifications.', 'ohmylms') . ' ' . __('How to set up', 'ohmylms') . ' <a href="https://developer.paypal.com/docs/api-basics/notifications/webhooks/" target="_blank">' . __('PayPal Webhooks', 'ohmylms') . '</a>',
		// 'input_type' => 'text',
		// 'default_value' => home_url('/wp-json/ohmylms/v1/paypal-webhook'),
		// 'value' => $this->get_setting('webhook_url', ''),
		// 'option_name' => 'webhook_url'
		// ),
		// array(
		// 'title' => __('Webhook ID', 'ohmylms'),
		// 'short_description' => __('Enter the PayPal webhook ID after creating it in the PayPal Developer Dashboard.', 'ohmylms'),
		// 'input_type' => 'text',
		// 'default_value' => '',
		// 'value' => $this->get_setting('webhook_id', ''),
		// 'option_name' => 'webhook_id'
		// ).
		);

		$gateway_settings = array(
			'id'                   => 'paypal',
			'title'                => __( 'Paypal', 'ohmylms' ),
			'description'          => __( 'Paypal payment gateway', 'ohmylms' ),
			'icon'                 => '<svg width="43" height="42" fill="none" viewBox="0 0 43 42" xmlns="http://www.w3.org/2000/svg" style="display: block;"><rect width="43" height="42" fill="#D9F2FF" rx="21"/><g clipPath="url(#clip0_2161_753)"><path fill="#27346A" d="M28.816 10.868c-1.143-1.262-3.21-1.804-5.853-1.804h-7.672c-.261 0-.514.09-.713.255-.199.165-.33.393-.371.643l-3.195 19.627a.645.645 0 00.65.737H16.4l1.19-7.309-.037.229c.084-.517.54-.898 1.08-.898h2.251c4.422 0 7.884-1.74 8.895-6.772.03-.15.056-.294.078-.436-.127-.065-.127-.065 0 0 .301-1.86-.002-3.126-1.04-4.272z"/><path fill="#374CA1" d="M19.4 14.47a.99.99 0 01.415-.09h6.015c.712 0 1.376.045 1.983.14a8.615 8.615 0 011.212.28c.298.096.576.209.831.34.301-1.86-.002-3.126-1.04-4.272-1.144-1.262-3.21-1.804-5.853-1.804h-7.672c-.54 0-1 .381-1.085.898l-3.194 19.626a.644.644 0 00.65.737H16.4l2.468-15.16a.916.916 0 01.178-.411.957.957 0 01.356-.284z"/><path fill="#2CBAFF" d="M29.776 15.576c-1.012 5.032-4.474 6.772-8.895 6.772H18.63c-.54 0-.997.382-1.08.898l-1.48 9.088a.564.564 0 00.569.645h3.992c.229 0 .45-.079.624-.223a.924.924 0 00.325-.562l.038-.197.753-4.62.048-.255a.924.924 0 01.325-.562.98.98 0 01.624-.223h.597c3.868 0 6.896-1.523 7.781-5.925.37-1.84.178-3.375-.799-4.454a3.801 3.801 0 00-1.093-.817c-.023.142-.048.286-.078.435z"/><path fill="#303C87" d="M28.796 14.733a7.573 7.573 0 00-.477-.119 9.325 9.325 0 00-.507-.093 12.874 12.874 0 00-1.984-.14h-6.014a.975.975 0 00-.77.374.912.912 0 00-.178.412l-1.278 7.851-.037.23c.084-.518.54-.899 1.08-.899h2.251c4.422 0 7.884-1.74 8.895-6.772.03-.149.055-.293.078-.435a5.563 5.563 0 00-1.059-.41"/></g><defs><clipPath id="clip0_2161_753"><path fill="#fff" d="M0 0h21v24H0z" transform="translate(11 9)"/></clipPath></defs></svg>',
			'has_config'           => true,
			'subscription_support' => true,
			'settings_fields'      => $fields,
			'enabled'              => $this->enabled,
		);

		return $gateway_settings;
	}

	/**
	 * Output payment fields on the checkout page.
	 * Displays the instruction/description for PayPal payment.
	 *
	 * @return void
	 */
	public function payment_fields() {
		$description = $this->get_description();
		if ( $description ) {
			echo wpautop( wp_kses_post( $description ) );
		}
	}

	/**
	 * Creates a webhook for PayPal notifications.
	 *
	 * @return string|WP_Error The webhook ID or WP_Error if creation fails
	 * @since 1.0.0
	 */
	private function create_webhook() {
		try {
			$response = $this->paypal_api->create_webhook(
				$this->webhook_url,
				array(
					'PAYMENT.CAPTURE.COMPLETED',
					'PAYMENT.CAPTURE.REFUNDED',
					'BILLING.SUBSCRIPTION.ACTIVATED',
					'BILLING.SUBSCRIPTION.UPDATED',
				)
			);
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$webhook_id = $response['id'] ?? null;
			update_option( 'ohmylms_paypal_settings', array_merge( $this->settings, array( 'webhook_id' => $webhook_id ) ) );
			$this->webhook_id = $webhook_id;
			return $webhook_id;
		} catch ( \Exception $e ) {
			return new \WP_Error( 'paypal_webhook_error', $e->getMessage() );
		}
	}

	/**
	 * Registers a REST API endpoint for webhook handling.
	 *
	 * @since 1.0.0
	 */
	public function register_webhook_endpoint() {
		register_rest_route(
			'ohmylms/v1',
			'/paypal-webhook',
			array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Verify that a webhook request genuinely came from PayPal.
	 *
	 * Uses PayPal's verify-webhook-signature API with the transmission headers
	 * that accompany every real webhook delivery. Requests without those
	 * headers, or for which PayPal does not return SUCCESS, are rejected.
	 *
	 * @param \WP_REST_Request $request The incoming request.
	 * @return true|\WP_Error
	 */
	private function verify_webhook_request( $request ) {
		if ( empty( $this->webhook_id ) ) {
			return new \WP_Error( 'paypal_webhook_unconfigured', __( 'PayPal webhook is not configured.', 'ohmylms' ) );
		}

		$auth_algo         = $request->get_header( 'paypal-auth-algo' );
		$cert_url          = $request->get_header( 'paypal-cert-url' );
		$transmission_id   = $request->get_header( 'paypal-transmission-id' );
		$transmission_sig  = $request->get_header( 'paypal-transmission-sig' );
		$transmission_time = $request->get_header( 'paypal-transmission-time' );

		if ( ! $auth_algo || ! $cert_url || ! $transmission_id || ! $transmission_sig || ! $transmission_time ) {
			return new \WP_Error( 'paypal_webhook_unsigned', __( 'Missing PayPal signature headers.', 'ohmylms' ) );
		}

		// Only certificates served by PayPal are acceptable.
		$host = wp_parse_url( $cert_url, PHP_URL_HOST );
		if ( ! $host || ! preg_match( '/(^|\.)paypal\.com$/i', $host ) ) {
			return new \WP_Error( 'paypal_webhook_bad_cert', __( 'Unexpected PayPal certificate URL.', 'ohmylms' ) );
		}

		$payload = json_decode( $request->get_body(), true );
		if ( ! is_array( $payload ) ) {
			return new \WP_Error( 'paypal_webhook_bad_payload', __( 'Malformed webhook payload.', 'ohmylms' ) );
		}

		$result = $this->paypal_api->verify_webhook_signature(
			$this->webhook_id,
			$payload,
			$auth_algo,
			$cert_url,
			$transmission_id,
			$transmission_sig,
			$transmission_time
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! isset( $result['verification_status'] ) || 'SUCCESS' !== $result['verification_status'] ) {
			return new \WP_Error( 'paypal_webhook_invalid_signature', __( 'PayPal signature verification failed.', 'ohmylms' ) );
		}

		return true;
	}

	/**
	 * Confirm with PayPal that an order was really captured for the expected amount.
	 *
	 * @param string $paypal_order_id The PayPal order ID named in the request.
	 * @param int    $order_id The local order ID being completed.
	 * @return true|\WP_Error
	 */
	private function confirm_paypal_capture( $paypal_order_id, $order_id ) {
		if ( empty( $paypal_order_id ) ) {
			return new \WP_Error( 'paypal_missing_order', __( 'No PayPal order ID supplied.', 'ohmylms' ) );
		}

		$remote = $this->paypal_api->get_order( $paypal_order_id );
		if ( is_wp_error( $remote ) ) {
			return $remote;
		}

		if ( ! isset( $remote['status'] ) || 'COMPLETED' !== $remote['status'] ) {
			return new \WP_Error( 'paypal_not_completed', __( 'PayPal order is not completed.', 'ohmylms' ) );
		}

		$order = ohmylms_ecommerce_get_order( $order_id );
		if ( ! $order ) {
			return new \WP_Error( 'paypal_unknown_order', __( 'Order not found.', 'ohmylms' ) );
		}

		$remote_amount   = $remote['purchase_units'][0]['amount']['value'] ?? null;
		$remote_currency = $remote['purchase_units'][0]['amount']['currency_code'] ?? null;

		if ( null === $remote_amount || null === $remote_currency ) {
			return new \WP_Error( 'paypal_missing_amount', __( 'PayPal order has no amount.', 'ohmylms' ) );
		}

		$expected_amount = number_format( (float) $order->get_total( 'edit' ), 2, '.', '' );
		$actual_amount   = number_format( (float) $remote_amount, 2, '.', '' );

		if ( $expected_amount !== $actual_amount || strtoupper( $remote_currency ) !== strtoupper( $order->get_currency() ) ) {
			return new \WP_Error( 'paypal_amount_mismatch', __( 'PayPal payment does not match the order total.', 'ohmylms' ) );
		}

		return true;
	}

	/**
	 * Handles the PayPal webhook requests.
	 *
	 * @param mixed $request REST request.
	 * @return array|WP_REST_Response
	 */
	public function handle_webhook( $request ) {
		try {
			if ( 'POST' !== $request->get_method() ) {
				return new WP_REST_Response(
					array(
						'status'  => 'error',
						'message' => 'Invalid request method',
					),
					405
				);
			}

			// This endpoint is unauthenticated, so nothing in the request body
			// may be trusted until PayPal has vouched for it.
			$signature_check = $this->verify_webhook_request( $request );
			if ( is_wp_error( $signature_check ) ) {
				return new WP_REST_Response(
					array(
						'status'  => 'error',
						'message' => $signature_check->get_error_message(),
					),
					401
				);
			}

			$post_data    = json_decode( $request->get_body(), true );
			$order_id     = isset( $post_data['order_id'] ) ? intval( $post_data['order_id'] ) : 0;
			$payment_data = $post_data['data'] ?? array();

			if ( ! $order_id || empty( $payment_data ) ) {
				return new WP_REST_Response(
					array(
						'status'  => 'error',
						'message' => __( 'Invalid order ID or payment data', 'ohmylms' ),
					),
					400
				);
			}

			$paypal_order_id = $payment_data['id'];

			// Confirm the claimed payment against PayPal itself rather than
			// trusting the amounts supplied in the request body.
			$confirmed = $this->confirm_paypal_capture( $paypal_order_id, $order_id );
			if ( is_wp_error( $confirmed ) ) {
				return new WP_REST_Response(
					array(
						'status'  => 'error',
						'message' => $confirmed->get_error_message(),
					),
					400
				);
			}
			$capture_data   = $payment_data['purchase_units'][0]['payments']['captures'][0];
			$transaction_id = $capture_data['id'] ?? null;
			$paypal_fee     = $capture_data['seller_receivable_breakdown']['paypal_fee']['value'] ?? null;
			$net_amount     = $capture_data['seller_receivable_breakdown']['net_amount']['value'] ?? null;
			$vault_id       = $payment_data['payment_source']['paypal']['attributes']['vault']['id'];

			update_post_meta( $order_id, '_paypal_vault_id', $vault_id );
			update_post_meta( $order_id, '_paypal_payment_payload', $payment_data );
			update_post_meta( $order_id, '_paypal_order_id', $paypal_order_id );
			update_post_meta( $order_id, '_paypal_fee', $paypal_fee );
			update_post_meta( $order_id, '_net_amount', $net_amount );

			$renewal_order = ohmylms_ecommerce_get_order( $order_id );
			$renewal_order->payment_complete( $transaction_id );

			return array(
				'status'         => 'success',
				'transaction_id' => $transaction_id,
			);
		} catch ( \Exception $e ) {
			return new WP_REST_Response(
				array(
					'status'  => 'error',
					'message' => $e->getMessage(),
				),
				500
			);
		}
	}

	/**
	 * Processes the payment by creating an order and optionally vaulting the payment method.
	 *
	 * @param int  $order_id Order identifier.
	 * @param bool $is_subscription Is subscription.
	 * @return array|WP_Error
	 * @since 1.0.0
	 */
	public function process_payment( $order_id, $is_subscription = false ) {
		try {
			$response_data = $this->create_order( $order_id );
			if ( is_wp_error( $response_data ) ) {
				return $response_data;
			}
			return array(
				'result'   => 'success',
				'redirect' => $this->get_url( $response_data['links'], 'payer-action' ),
			);
		} catch ( \Exception $e ) {
			return new \WP_Error( 'paypal_error', $e->getMessage() );
		}
	}

	/**
	 * Creates a PayPal order for the given order ID.
	 *
	 * @param mixed $order_id Order identifier.
	 * @param int   $renewal_order_id Renewal order identifier.
	 * @param bool  $is_subscription Is subscription.
	 * @return array|WP_Error
	 * @since 1.0.0
	 */
	private function create_order( $order_id, $renewal_order_id = 0, $is_subscription = false ) {
		try {
			$data     = $this->prepare_data( $order_id, $renewal_order_id, $is_subscription );
			$response = $this->paypal_api->create_order( $data );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$paypal_order_id = $response['id'] ?? null;
			if ( ! $paypal_order_id ) {
				return new \WP_Error( 'paypal_error', __( 'PayPal order ID not found in response.', 'ohmylms' ) );
			}
			update_post_meta( $order_id, '_paypal_order_id', $paypal_order_id );
			return $response;
		} catch ( \Exception $e ) {
			return new \WP_Error( 'paypal_error', $e->getMessage() );
		}
	}

	/**
	 * Prepares the data for creating a PayPal order.
	 *
	 * @param mixed $order_id Order identifier.
	 * @param int   $renewal_order_id Renewal order identifier.
	 * @param bool  $is_subscription Is subscription.
	 * @return array
	 * @since 1.0.0
	 */
	public function prepare_data( $order_id, $renewal_order_id = 0, $is_subscription = false ) {
		$order          = ohmylms_ecommerce_get_order( $order_id );
		$items          = $order->get_items();
		$amount         = $this->get_amount_data( $order );
		$cart_discount  = $order->get_cart_discount();
		$filtered_items = array();

		// Calculate the total of all items before discount and tax.
		$total_items_amount_before_discount = 0;
		$items_data                         = array();

		foreach ( $items as $item ) {
			if ( $item->get_id() ) {
				$item_total = $item->get_total(); // This should be the base amount before tax.

				$items_data[]                        = array(
					'name'            => $item->get_name(),
					'original_amount' => $item_total,
				);
				$total_items_amount_before_discount += $item_total;
			}
		}

		// Apply discount proportionally to each item first.
		$total_after_discount = $total_items_amount_before_discount - $cart_discount;

		if ( $cart_discount > 0 && $total_items_amount_before_discount > 0 ) {
			$discount_ratio = $cart_discount / $total_items_amount_before_discount;
			foreach ( $items_data as $item_data ) {
				$item_discount         = $item_data['original_amount'] * $discount_ratio;
				$amount_after_discount = $item_data['original_amount'] - $item_discount;

				// Now apply tax to the discounted amount.
				$tax_amount   = \OhMyLMS_Tax_Calculator::get_instance()->calculate_tax( $order->get_tax_rate(), array( 'total' => $amount_after_discount ) );
				$final_amount = is_array( $tax_amount ) && isset( $tax_amount['total_with_tax'] ) ? $tax_amount['total_with_tax'] : $amount_after_discount;

				$filtered_items[] = array(
					'name'        => $item_data['name'],
					'quantity'    => '1',
					'unit_amount' => array(
						'currency_code' => $this->currency,
						'value'         => number_format( $final_amount, 2, '.', '' ),
					),
				);
			}
		} else {
			// No discount, apply tax to original amounts.
			foreach ( $items_data as $item_data ) {
				$tax_amount   = \OhMyLMS_Tax_Calculator::get_instance()->calculate_tax( $order->get_tax_rate(), array( 'total' => $item_data['original_amount'] ) );
				$final_amount = is_array( $tax_amount ) && isset( $tax_amount['total_with_tax'] ) ? $tax_amount['total_with_tax'] : $item_data['original_amount'];

				$filtered_items[] = array(
					'name'        => $item_data['name'],
					'quantity'    => '1',
					'unit_amount' => array(
						'currency_code' => $this->currency,
						'value'         => number_format( $final_amount, 2, '.', '' ),
					),
				);
			}
		}

		$data = array(
			'intent'         => 'CAPTURE',
			'purchase_units' => array(
				array(
					'custom_id' => $order->get_id(),
					'items'     => $filtered_items,
					'amount'    => $amount,
					'shipping'  => $this->get_shipping_info( $order ),
				),
			),
		);

		if ( $is_subscription ) {
			$this->get_payment_source_for_recurring( $data, $order );
		} else {
			$this->get_payment_source( $data, $order );
		}
		return $data;
	}


	/**
	 * Retrieves the payment source data for recurring payments.
	 *
	 * @param mixed $data Input data.
	 * @param mixed $order Order.
	 * @return void
	 * @since 1.0.0
	 */
	public function get_payment_source( &$data, $order ) {
		// Check if there's a funnel configured for this order.
		$funnel_url = $this->check_and_build_funnel_url( $order );

		// If funnel is configured, store session data immediately.
		if ( $funnel_url ) {
			$this->store_funnel_session_data_if_needed( $order );
		}

		$return_url = $funnel_url ? $funnel_url : add_query_arg(
			array(
				'order_id' => $order->get_id(),
				'key'      => $order->get_order_key(),
			),
			ohmylms_get_checkout_url() . '/ohmylms-order-received/' . $order->get_id()
		);

		$data['payment_source'] = array(
			'paypal' => array(
				'attributes'         => array(
					'vault' => array(
						'store_in_vault' => 'ON_SUCCESS',
						'usage_type'     => 'MERCHANT',
					),
				),
				'experience_context' => array(
					'user_action'               => 'PAY_NOW',
					'payment_method_preference' => 'UNRESTRICTED',
					'return_url'                => $return_url,
					'cancel_url'                => home_url( '/return?order_id=' . $order->get_id() ),
				),
			),
		);
	}


	/**
	 * Retrieves the payment source data for recurring payments using a vaulted payment method.
	 *
	 * @param mixed $data Input data.
	 * @param mixed $order Order.
	 * @return void
	 */
	public function get_payment_source_for_recurring( &$data, $order ) {
		$order_id = $order->get_id();
		$vault_id = get_post_meta( $order_id, '_paypal_vault_id', true );
		if ( ! $vault_id ) {
			return;
		}
		$vault = $this->paypal_api->get_vault( $vault_id );
		if ( is_wp_error( $vault ) ) {
			return;
		}

		$payment_source         = $vault['payment_source']['paypal'];
		$data['payment_source'] = array(
			'paypal' => array(
				'attributes'         => array(
					'customer' => array(
						'id' => $vault['customer']['id'],
					),
				),
				'vault_id'           => $vault['id'],
				'email_address'      => $payment_source['email_address'],
				'name'               => array(
					'given_name' => $payment_source['name']['given_name'],
					'surname'    => $payment_source['name']['surname'],
				),
				'experience_context' => array(
					'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
				),
				'address'            => array(
					'address_line_1' => $payment_source['shipping']['address']['address_line_1'] ?? 'N/A',
					'address_line_2' => $payment_source['shipping']['address']['address_line_2'] ?? '',
					'admin_area_1'   => $payment_source['shipping']['address']['admin_area_1'] ?? '',
					'admin_area_2'   => $payment_source['shipping']['address']['admin_area_2'] ?? 'N/A',
					'postal_code'    => $payment_source['shipping']['address']['postal_code'] ?? '',
					'country_code'   => $payment_source['shipping']['address']['country_code'] ?? 'US',
				),
			),
		);
	}


	/**
	 * Retrieves the URL for a specific link relation from the PayPal response.
	 *
	 * @param mixed $links Links.
	 * @param mixed $rel Rel.
	 * @return mixed|string|null
	 * @since 1.0.0
	 */
	public function get_url( $links, $rel ) {
		$filtered = array_filter( $links, fn( $link ) => isset( $link['rel'] ) && $link['rel'] === $rel );
		return ( $first = reset( $filtered ) ) ? $first['href'] : null;
	}

	/**
	 * Retrieves the amount data for the order.
	 *
	 * @param \OhMyLMS\Commerce\Data\Order $order Order.
	 * @return array
	 * @since 1.0.0
	 */
	public function get_amount_data( $order ) {
		$order_total = $order->get_total(); // This should already include tax and discount.

		$breakdown = array(
			'item_total' => array(
				'currency_code' => $this->currency,
				'value'         => number_format( $order_total, 2, '.', '' ),
			),
		);

		return array(
			'currency_code' => $this->currency,
			'value'         => number_format( $order_total, 2, '.', '' ),
			'breakdown'     => $breakdown,
		);
	}

	/**
	 * Processes a recurring payment using a vaulted payment method.
	 *
	 * @param int    $original_order_id Original order identifier.
	 * @param int    $renewal_order_id Renewal order identifier.
	 * @param float  $amount Amount.
	 * @param string $subscription_id Subscription identifier.
	 * @param int    $student_id Student identifier.
	 * @return array|WP_Error
	 * @since 1.0.0
	 */
	public function process_recurring_payment( $original_order_id, $renewal_order_id, $amount, $subscription_id, $student_id ) {
		try {
			$response_data = $this->create_order( $original_order_id, $renewal_order_id, true );
			if ( is_wp_error( $response_data ) ) {
				return $response_data;
			}
			if ( 'COMPLETED' === $response_data['status'] ) {
				$payload = get_post_meta( $original_order_id, '_paypal_payment_payload', true );
				$response_data['payment_source']['paypal']['attributes'] = array(
					'vault' => array(
						'id' => $payload['payment_source']['paypal']['attributes']['vault']['id'],
					),
				);
				$response = wp_remote_request(
					$this->webhook_url,
					array(
						'method'    => 'POST',
						'body'      => wp_json_encode(
							array(
								'parent_order_id' => $original_order_id,
								'order_id'        => $renewal_order_id,
								'data'            => $response_data,
							)
						),
						'headers'   => array(
							'Content-Type' => 'application/json',
						),
						'sslverify' => false,
					)
				);
				return $response;
			}
		} catch ( \Exception $e ) {
			return new \WP_Error( 'paypal_error', $e->getMessage() );
		}
	}

	/**
	 * Process refund.
	 *
	 * @param int        $order_id Order identifier.
	 * @param float|null $amount Amount.
	 * @param string     $reason Reason.
	 * @return bool|WP_Error
	 * @since 1.0.0
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		try {
			$order = ohmylms_ecommerce_get_order( $order_id );
			if ( ! $this->can_refund_order( $order ) ) {
				return new \WP_Error( 'error', __( 'Refund failed.', 'ohmylms' ) );
			}

			$capture_id = get_post_meta( $order_id, '_transaction_id', true );
			$response   = $this->issue_refund( $capture_id, $amount, $reason, $order );

			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$net_amount = get_post_meta( $order_id, '_net_amount', true );
			update_post_meta( $order_id, '_net_amount', floatval( $net_amount ) - abs( $amount ) );
			return 'COMPLETED' === $response['status'];
		} catch ( \Exception $e ) {
			return new \WP_Error( 'paypal_error', $e->getMessage() );
		}
	}

	/**
	 * Checks if the order can be refunded.
	 *
	 * @param \OhMyLMS\Commerce\Data\Order $order Order.
	 * @return bool
	 * @since 1.0.0
	 */
	public function can_refund_order( $order ) {
		return $order && get_post_meta( $order->get_id(), '_transaction_id', true );
	}

	/**
	 * Issues a refund for a captured payment.
	 *
	 * @param string                       $capture_id Capture identifier.
	 * @param float|null                   $amount Amount.
	 * @param string                       $reason Reason.
	 * @param \OhMyLMS\Commerce\Data\Order $order Order.
	 * @return array|WP_Error
	 * @since 1.0.0
	 * @throws \Exception When the operation cannot be completed.
	 */
	private function issue_refund( $capture_id, $amount, $reason, $order ) {
		try {
			$currency = $order->get_currency();
			$response = $this->paypal_api->refund_capture( $capture_id, $amount, $currency, $reason );

			if ( is_wp_error( $response ) ) {
				throw new \Exception( 'Refund request failed: ' . $response->get_error_message() );
			}
			$order->add_order_note(
				sprintf(
					/* translators: %s: text value. */
					__( 'Refund processed via Paypal.Reason: %s', 'ohmylms' ),
					$reason ? $reason : __( 'No reason provided', 'ohmylms' )
				)
			);
			return $response;
		} catch ( \Exception $e ) {
			return new \WP_Error( 'paypal_error', $e->getMessage() );
		}
	}

	/**
	 * Get payment gateway meta data for display on order details.
	 *
	 * @param \OhMyLMS\Commerce\Data\Order $order Order.
	 * @return array
	 */
	public function get_payment_gateway_meta( $order ) {
		$order_id = $order->get_id();
		$meta     = array();

		$paypal_fee = get_post_meta( $order_id, '_paypal_fee', true );
		if ( $paypal_fee ) {
			$meta[] = array(
				'type'  => 'fee',
				'label' => __( 'PayPal Fee', 'ohmylms' ),
				'value' => $paypal_fee,
			);
		}

		$net_amount = get_post_meta( $order_id, '_net_amount', true );
		if ( $net_amount ) {
			$meta[] = array(
				'type'  => 'net',
				'label' => __( 'PayPal Net', 'ohmylms' ),
				'value' => $net_amount,
			);
		}
		return $meta;
	}

	/**
	 * Get the transaction URL for this gateway.
	 *
	 * @param \OhMyLMS\Commerce\Data\Order $order Order.
	 * @return string
	 */
	public function get_transaction_url( $order ) {
		$paypal_order_id = get_post_meta( $order->get_id(), '_transaction_id', true );
		if ( $this->testmode && $paypal_order_id ) {
			return 'https://www.sandbox.paypal.com/activity/payment/' . $paypal_order_id;
		} elseif ( $paypal_order_id ) {
			return 'https://www.paypal.com/activity/payment/' . $paypal_order_id;
		}
		return parent::get_transaction_url( $order );
	}

	/**
	 * Get shipping information for the order.
	 *
	 * @param \OhMyLMS\Commerce\Data\Order $order Order.
	 * @return array
	 * @since 1.0.0
	 */
	private function get_shipping_info( $order ) {
		$address_line_1 = $order->get_address();
		$address_line_2 = '';
		$city           = $order->get_city();
		$state          = $order->get_state();
		$country        = $order->get_country();
		$post_code      = $order->get_postcode();
		// Ensure required fields are not empty.
		if ( empty( $address_line_1 ) ) {
			$address_line_1 = 'N/A';
		}
		if ( empty( $city ) ) {
			$city = 'N/A';
		}

		if ( empty( $post_code ) ) {
			$post_code = 'N/A';
		}

		return array(
			'type'    => 'SHIPPING',
			'name'    => array(
				'full_name' => trim( $order->get_first_name() . ' ' . $order->get_last_name() ),
			),
			'address' => array(
				'address_line_1' => $address_line_1,
				'address_line_2' => $address_line_2,
				'admin_area_2'   => $city,      // City is admin_area_2.
				'admin_area_1'   => $state,     // State/Province is admin_area_1.
				'postal_code'    => $post_code,
				'country_code'   => $country ? $country : 'US',
			),
		);
	}

	/**
	 * Handle PayPal return and vaulting.
	 *
	 * @since 1.0.0
	 */
	public function handle_paypal_return() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( ! isset( $_GET['token'] ) && ! isset( $_POST['token'] ) ) {
			return;
		}

		global $wpdb;
		$order_id      = sanitize_text_field( wp_unslash( $_GET['order_id'] ));
		$token         = sanitize_text_field( wp_unslash( $_GET['token'] ));
		$response_data = $this->paypal_api->get_order_details( $token );

		if ( is_wp_error( $response_data ) ) {
			return;
		}
		$vault_id        = isset( $response_data['payment_source']['paypal']['attributes']['vault']['id'] ) ? $response_data['payment_source']['paypal']['attributes']['vault']['id'] : null;
		$paypal_order_id = ! empty( $response_data['id'] ) ? $response_data['id'] : null;
		$capture_data    = ! empty( $response_data['purchase_units'][0]['payments']['captures'][0] ) ? $response_data['purchase_units'][0]['payments']['captures'][0] : null;
		$transaction_id  = ! empty( $capture_data['id'] ) ? $capture_data['id'] : null;
		$paypal_fee      = ! empty( $capture_data['seller_receivable_breakdown']['paypal_fee']['value'] ) ? $capture_data['seller_receivable_breakdown']['paypal_fee']['value'] : null;
		$net_amount      = ! empty( $capture_data['seller_receivable_breakdown']['net_amount']['value'] ) ? $capture_data['seller_receivable_breakdown']['net_amount']['value'] : null;

		update_post_meta( $order_id, '_paypal_vault_id', $vault_id );
		update_post_meta( $order_id, '_paypal_payment_payload', $response_data );
		update_post_meta( $order_id, '_paypal_order_id', $paypal_order_id );
		update_post_meta( $order_id, '_paypal_fee', $paypal_fee );
		update_post_meta( $order_id, '_net_amount', $net_amount );

		$membership_table = $wpdb->prefix . 'ohmylms_user_membership';
		$order            = ohmylms_ecommerce_get_order( $order_id );
		if ( $order ) {
			$order->payment_complete( $transaction_id );
			$wpdb->update(
				$membership_table,
				array(
					'status' => 'enrolled',
				),
				array(
					'order_id' => $order_id,
				)
			);
		}
	}

	/**
	 * Check for funnel configuration and build funnel URL if needed.
	 *
	 * @param \OhMyLMS\Commerce\Data\Order $order The order object.
	 * @return string|null Funnel URL or null if no funnel configured.
	 * @since 1.0.0
	 */
	private function check_and_build_funnel_url( $order ) {
		// Validate order object.
		if ( ! $order ) {
			return null;
		}

		$order_items = $order->get_items();
		if ( empty( $order_items ) ) {
			return null;
		}

		// Look for courses with funnel steps.
		foreach ( $order_items as $item ) {
			if ( method_exists( $item, 'get_course_id' ) ) {
				$course_id = $item->get_course_id();
				if ( $course_id ) {
					// Check if this course has funnel steps configured.
					$funnel_steps = get_post_meta( $course_id, '_funnel_steps', true );
					if ( ! empty( $funnel_steps ) && is_array( $funnel_steps ) ) {
						// Get the first funnel step.
						$first_step = reset( $funnel_steps );
						if ( $first_step && isset( $first_step['step_id'] ) ) {
							// Build and return funnel URL using FunnelManager.
							$funnel_manager = new FunnelManager();
							return $funnel_manager->build_funnel_step_url( $order->get_id(), $first_step );
						}
					}
					return null;
				}
			}
		}
	}

	/**
	 * Store funnel session data if funnel is configured for the order.
	 *
	 * @param \OhMyLMS\Commerce\Data\Order $order The order object.
	 * @return void
	 * @since 1.0.0
	 */
	private function store_funnel_session_data_if_needed( $order ) {
		if ( ! $order ) {
			return;
		}
		$order_items = $order->get_items();
		if ( empty( $order_items ) ) {
			return;
		}

		// Look for courses with funnel steps.
		foreach ( $order_items as $item ) {
			if ( method_exists( $item, 'get_course_id' ) ) {
				$course_id = $item->get_course_id();
				if ( $course_id ) {
					// Check if this course has funnel steps configured.
					$funnel_steps = get_post_meta( $course_id, '_funnel_steps', true );
					if ( ! empty( $funnel_steps ) && is_array( $funnel_steps ) ) {
						// Get the first funnel step.
						$first_step = reset( $funnel_steps );
						if ( $first_step && isset( $first_step['step_id'] ) ) {
							// Store funnel session data.
							$this->store_funnel_session_data( $order->get_id(), $course_id, $first_step );
							return; // Found funnel, no need to check other items.
						}
					}
				}
			}
		}
	}

	/**
	 * Store funnel session data for tracking.
	 *
	 * @param int   $order_id The order ID.
	 * @param int   $course_id The course ID.
	 * @param array $step_data The funnel step data.
	 * @return void
	 * @since 1.0.0
	 */
	private function store_funnel_session_data( $order_id, $course_id, $step_data ) {
		$funnel_session_data = array(
			'order_id'     => $order_id,
			'course_id'    => $course_id,
			'current_step' => $step_data['step_id'],
			'step_data'    => $step_data,
			'created_at'   => time(),
		);
		// Store in session.
		if ( function_exists( '\OhMyLMS\Commerce\ecommerce' ) ) {
				\OhMyLMS\Commerce\ecommerce()->session->set( 'funnel_data', $funnel_session_data );
				\OhMyLMS\Commerce\ecommerce()->session->save_data();
		}
		// Also store as order meta as a backup in case session is lost.
		update_post_meta( $order_id, '_funnel_session_data', $funnel_session_data );
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

		// Get the vault_id from original order meta.
		$vault_id = get_post_meta( $original_order->get_id(), '_paypal_vault_id', true );
		if ( empty( $vault_id ) ) {
			return new \WP_Error( 'missing_vault_id', __( 'No saved PayPal payment method found for this order.', 'ohmylms' ) );
		}

		$data = array(
			'intent'         => 'CAPTURE',
			'purchase_units' => array(
				array(
					'amount'      => array(
						'currency_code' => $this->currency,
						'value'         => number_format( $amount, 2, '.', '' ),
					),
					'description' => sprintf( 'Upsell/Downsell: %s', $course->get_name() ),
					'custom_id'   => $original_order->get_id() . '-upsell-' . $step_data['step_id'],
				),
			),
			'payment_source' => array(
				'paypal' => array(
					'vault_id' => $vault_id,
				),
			),
		);

		// Send request to PayPal API.
		$response = $this->paypal_api->create_order( $data );

		if ( is_wp_error( $response ) || empty( $response['id'] ) ) {
			return new \WP_Error( 'paypal_error', __( 'Unable to create upsell PayPal order.', 'ohmylms' ), $response );
		}

		$capture_data = $response['purchase_units'][0]['payments']['captures'][0];
		$paypal_fee   = $capture_data['seller_receivable_breakdown']['paypal_fee']['value'] ?? null;
		$net_amount   = $capture_data['seller_receivable_breakdown']['net_amount']['value'] ?? null;

		update_post_meta( $original_order->get_id(), '_upsell_order_id', $response['id'] );
		update_post_meta( $original_order->get_id(), '_upsell_transaction_id', $capture_data['id'] );

		// Get existing values.
		$existing_fee = (float) get_post_meta( $original_order->get_id(), '_paypal_fee', true );
		$existing_net = (float) get_post_meta( $original_order->get_id(), '_net_amount', true );

		// Add and update.
		update_post_meta( $original_order->get_id(), '_paypal_fee', $existing_fee + $paypal_fee );
		update_post_meta( $original_order->get_id(), '_net_amount', $existing_net + $net_amount );

		// Mark upsell as paid in your system.
		do_action( 'ohmylms_upsell_payment_completed', $original_order->get_id(), $step_data );

		$original_order->save();
		return array(
			'success' => true,
			'message' => __( 'Payment processed successfully.', 'ohmylms' ),
		);
	}
}
