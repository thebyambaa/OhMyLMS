<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.
namespace OhMyLMS\Commerce\Gateways\QPay;

use OhMyLMS\Commerce\Abstracts\PaymentGateway;
use function OhMyLMS\Commerce\ecommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin gateway qpay implementation.
 */
class GatewayQPay extends PaymentGateway {
	/**
	 * Invoice code.
	 *
	 * @var mixed
	 */
	private $invoice_code;
	/**
	 * Hooks registered.
	 *
	 * @var mixed
	 */
	private static $hooks_registered = false;

	/**
	 * Initialize Gateway qpay.
	 */
	public function __construct() {
		$this->id                = 'qpay';
		$this->settings          = (array) get_option( 'ohmylms_qpay_settings', array() );
		$this->title             = $this->get_setting( 'title', __( 'QPay', 'ohmylms' ) );
		$this->description       = $this->get_setting( 'instruction', __( 'Pay via QPay QR code using your bank app.', 'ohmylms' ) );
		$this->has_fields        = false;
		$this->order_button_text = __( 'Pay with QPay', 'ohmylms' );
		$this->enabled           = $this->normalize_yesno( $this->get_setting( 'enabled', 'no' ) );
		$this->testmode          = $this->normalize_yesno( $this->get_setting( 'testmode', 'no' ) );
		$this->invoice_code      = $this->get_setting( 'invoice_code', '' );
		if ( ! self::$hooks_registered ) {
			self::$hooks_registered = true;
			add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ), 20 );
			foreach ( array( 'wp_ajax_', 'wp_ajax_nopriv_' ) as $prefix ) {
				add_action( $prefix . 'ohmylms_qpay_check_payment', array( $this, 'ajax_check_payment' ) );
				add_action( $prefix . 'ohmylms_qpay_resume', array( $this, 'ajax_resume' ) );
			}
			add_action( 'rest_api_init', array( $this, 'register_callback_route' ) );
			add_action( 'update_option_ohmylms_qpay_settings', array( QPayAPI::class, 'settings_changed' ), 10, 2 );
		}
	}

	/**
	 * Normalize yesno for Gateway qpay.
	 *
	 * @param mixed $value Value to process.
	 */
	private function normalize_yesno( $value ) {
		return in_array( strtolower( (string) $value ), array( 'yes', 'true', '1', 'on' ), true ) ? 'yes' : 'no';
	}

	/**
	 * Get settings for Gateway qpay.
	 */
	public function get_settings() {
			$fields = array(
				array(
					'title'             => __( 'Enable', 'ohmylms' ),
					'short_description' => __( 'Enable QPay at checkout.', 'ohmylms' ),
					'input_type'        => 'switch',
					'default_value'     => 'no',
					'option_name'       => 'enabled',
					'value'             => $this->enabled,
				),
				array(
					'title'             => __( 'Title', 'ohmylms' ),
					'short_description' => __( 'The title displayed during checkout.', 'ohmylms' ),
					'input_type'        => 'text',
					'default_value'     => __( 'QPay', 'ohmylms' ),
					'option_name'       => 'title',
					'value'             => $this->title,
				),
				array(
					'title'             => __( 'Instruction', 'ohmylms' ),
					'short_description' => __( 'Instructions shown to students during checkout.', 'ohmylms' ),
					'input_type'        => 'textarea',
					'default_value'     => __( 'Pay via QPay QR code using your bank app.', 'ohmylms' ),
					'option_name'       => 'instruction',
					'value'             => $this->description,
				),
				array(
					'title'             => __( 'Invoice Code', 'ohmylms' ),
					'short_description' => __( 'QPay-assigned invoice code for your merchant account.', 'ohmylms' ),
					'input_type'        => 'text',
					'default_value'     => '',
					'option_name'       => 'invoice_code',
					'value'             => $this->invoice_code,
				),
				array(
					'title'             => __( 'Test Mode', 'ohmylms' ),
					'short_description' => __( 'Use QPay sandbox environment for testing. Turn this off to use your live merchant credentials.', 'ohmylms' ),
					'input_type'        => 'switch',
					'default_value'     => 'no',
					'option_name'       => 'testmode',
					'value'             => $this->testmode,
					'conditional_logic' => array(
						'type'     => 'control',
						'controls' => array( 'test_client_id', 'test_client_secret', 'live_client_id', 'live_client_secret' ),
					),
				),
				array(
					'title'             => __( 'Test Client ID', 'ohmylms' ),
					'short_description' => __( 'QPay sandbox Client ID.', 'ohmylms' ),
					'input_type'        => 'text',
					'default_value'     => '',
					'option_name'       => 'test_client_id',
					'value'             => $this->get_setting( 'test_client_id', '' ),
					'conditional_logic' => array(
						'type'       => 'dependent',
						'depends_on' => 'testmode',
						'show_when'  => 'yes',
					),
				),
				array(
					'title'             => __( 'Test Client Secret', 'ohmylms' ),
					'short_description' => __( 'QPay sandbox Client Secret.', 'ohmylms' ),
					'input_type'        => 'text',
					'default_value'     => '',
					'option_name'       => 'test_client_secret',
					'value'             => $this->get_setting( 'test_client_secret', '' ),
					'conditional_logic' => array(
						'type'       => 'dependent',
						'depends_on' => 'testmode',
						'show_when'  => 'yes',
					),
				),
				array(
					'title'             => __( 'Live Client ID', 'ohmylms' ),
					'short_description' => __( 'QPay production Client ID.', 'ohmylms' ),
					'input_type'        => 'text',
					'default_value'     => '',
					'option_name'       => 'live_client_id',
					'value'             => $this->get_setting( 'live_client_id', '' ),
					'conditional_logic' => array(
						'type'       => 'dependent',
						'depends_on' => 'testmode',
						'show_when'  => 'no',
					),
				),
				array(
					'title'             => __( 'Live Client Secret', 'ohmylms' ),
					'short_description' => __( 'QPay production Client Secret.', 'ohmylms' ),
					'input_type'        => 'text',
					'default_value'     => '',
					'option_name'       => 'live_client_secret',
					'value'             => $this->get_setting( 'live_client_secret', '' ),
					'conditional_logic' => array(
						'type'       => 'dependent',
						'depends_on' => 'testmode',
						'show_when'  => 'no',
					),
				),
			);

			return array(
				'id'                   => $this->id,
				'title'                => __( 'QPay', 'ohmylms' ),
				'description'          => __( 'QPay QR Code Payment Gateway', 'ohmylms' ),
				'icon'                 => '<svg width="23" height="18" viewBox="0 0 23 18" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="1" y="1" width="21" height="16" rx="2" stroke="var(--ohmylms-primary-color)" stroke-width="2"/><rect x="4" y="4" width="4" height="4" fill="var(--ohmylms-primary-color)"/><rect x="10" y="4" width="4" height="4" fill="var(--ohmylms-primary-color)"/><rect x="4" y="10" width="4" height="4" fill="var(--ohmylms-primary-color)"/><rect x="10" y="10" width="2" height="2" fill="var(--ohmylms-primary-color)"/><rect x="14" y="10" width="2" height="2" fill="var(--ohmylms-primary-color)"/><rect x="14" y="14" width="2" height="2" fill="var(--ohmylms-primary-color)"/></svg>',
				'has_config'           => true,
				'subscription_support' => false,
				'settings_fields'      => $fields,
				'enabled'              => $this->enabled,
			);
	}



	/**
	 * Credentials for Gateway qpay.
	 *
	 * @param mixed $mode Mode.
	 */
	private function credentials( $mode ) {
		// Read fresh options: admin saves and tests can change settings within a request.
		$settings = (array) get_option( 'ohmylms_qpay_settings', array() );
		return array( $settings[ $mode . '_client_id' ] ?? '', $settings[ $mode . '_client_secret' ] ?? '' );
	}

	/**
	 * Api for order for Gateway qpay.
	 *
	 * @param int $order_id Order identifier.
	 */
	public function api_for_order( $order_id = 0 ) {
		$mode = $order_id ? get_post_meta( $order_id, '_qpay_mode', true ) : '';
		// Legacy invoices did not record their environment; preserve their configured mode.
		if ( ! in_array( $mode, array( 'test', 'live' ), true ) ) {
			$mode = 'yes' === $this->testmode ? 'test' : 'live'; }
		list( $id, $secret ) = $this->credentials( $mode );
		if ( '' === $id || '' === $secret ) {
			return new \WP_Error( 'qpay_config', __( 'QPay is not configured for this payment environment.', 'ohmylms' ) );
		}
		$api         = new QPayAPI( $id, $secret, 'test' === $mode );
		$fingerprint = $order_id ? get_post_meta( $order_id, '_qpay_merchant', true ) : '';
		if ( $fingerprint && ! hash_equals( $fingerprint, $api->fingerprint() ) ) {
			return new \WP_Error( 'qpay_merchant_changed', __( 'QPay credentials for this invoice have changed. Please contact the store.', 'ohmylms' ) );
		}
		return $api;
	}

	/**
	 * Is available for Gateway qpay.
	 */
	public function is_available() {
		if ( 'yes' !== $this->enabled || ! $this->invoice_code || 'MNT' !== get_ohmylms_currency() || is_wp_error( $this->api_for_order() ) ) {
			return false; }
		if ( ecommerce()->cart ) {
			foreach ( ecommerce()->cart->get_cart() as $item ) {
				$id = isset( $item['data'] ) && is_object( $item['data'] ) ? $item['data']->get_id() : 0;
				if ( 'ohmylms-membership' === get_post_type( $id ) ) {
					$membership = ohmylms_get_membership( $id );
					if ( ! $membership || 'one_time' !== $membership->get_subscription_period() ) {
						return false; }
				}
			}
		}
		return parent::is_available();
	}

	/**
	 * Eligible order for Gateway qpay.
	 *
	 * @param mixed $order Order.
	 * @param mixed $subscription Subscription.
	 */
	private function eligible_order( $order, $subscription ) {
		if ( $subscription || 'yes' !== $this->enabled || ! $this->invoice_code || ! $order || 'qpay' !== $order->get_payment_method()
			|| 'MNT' !== $order->get_currency() || 'pending' !== $order->get_status() ) {
			return false; }
		$ids = array( (int) get_post_meta( $order->get_id(), '_membership_id', true ) );
		foreach ( $order->get_items() as $item ) {
			$ids[] = $item->get_course_id(); }
		foreach ( array_filter( $ids ) as $id ) {
			if ( 'ohmylms-membership' === get_post_type( $id ) ) {
				$membership = ohmylms_get_membership( $id );
				if ( ! $membership || 'one_time' !== $membership->get_subscription_period() ) {
					return false; }
			}
		}
		$amount = PaymentService::minor_units( $order->get_total() );
		return null !== $amount && $amount > 0;
	}

	/**
	 * Process payment for Gateway qpay.
	 *
	 * @param mixed $order_id Order identifier.
	 * @param bool  $is_subscription Is subscription.
	 */
	public function process_payment( $order_id, $is_subscription = false ) {
		return PaymentService::locked(
			$order_id,
			function () use ( $order_id, $is_subscription ) {
				$order = ohmylms_ecommerce_get_order( $order_id );
				if ( ! $this->eligible_order( $order, $is_subscription ) ) {
					return new \WP_Error( 'qpay_eligibility', __( 'QPay is available only for configured, one-time MNT purchases.', 'ohmylms' ) );
				}
				$api = $this->api_for_order( $order_id );
				if ( is_wp_error( $api ) ) {
					return $api; }
				$invoice_id = get_post_meta( $order_id, '_qpay_invoice_id', true );
				$invoice    = get_post_meta( $order_id, '_qpay_invoice_data', true );
				if ( $invoice_id ) {
					$amount = get_post_meta( $order_id, '_qpay_invoice_amount', true );
					if ( '' !== $amount && PaymentService::minor_units( $amount ) !== PaymentService::minor_units( $order->get_total() ) ) {
						return new \WP_Error( 'qpay_amount_changed', __( 'The order amount has changed. Please contact the store before paying this invoice.', 'ohmylms' ) );
					}
					// Reopen the original invoice. Never issue another while its status is unknown.
					$remote = $api->get_invoice( $invoice_id );
					if ( is_wp_error( $remote ) ) {
						return $remote; }
					$status = strtoupper( $remote['invoice_status'] ?? '' );
					if ( in_array( $status, array( 'EXPIRED', 'CANCELLED', 'CANCELED' ), true ) ) {
						return new \WP_Error( 'qpay_invoice_expired', __( 'This QPay invoice has expired or was cancelled. Contact the store to close this order before starting a new purchase.', 'ohmylms' ) );
					}
					if ( ! is_array( $invoice ) ) {
						$invoice = $remote; }
					$invoice['invoice_id'] = $invoice_id;
				} else {
					if ( get_post_meta( $order_id, '_qpay_create_started', true ) ) {
						return new \WP_Error( 'qpay_invoice_uncertain', __( 'The invoice request could not be confirmed. Please contact the store before trying another payment.', 'ohmylms' ) );
					}
					update_post_meta( $order_id, '_qpay_native', 1 );
					update_post_meta( $order_id, '_qpay_mode', 'yes' === $this->testmode ? 'test' : 'live' );
					update_post_meta( $order_id, '_qpay_merchant', $api->fingerprint() );
					update_post_meta( $order_id, '_qpay_invoice_amount', $order->get_total() );
					update_post_meta( $order_id, '_qpay_create_started', time() );
					$callback_token = wp_generate_password( 48, false, false );
					update_post_meta( $order_id, '_qpay_callback_token', $callback_token );
					$invoice = $api->create_invoice(
						array(
							'invoice_code'          => $this->invoice_code,
							'sender_invoice_no'     => (string) $order_id,
							'invoice_receiver_code' => 'terminal',
							'invoice_description'   => sprintf( /* translators: %s: text value. */
							__( 'Order #%s', 'ohmylms' ), $order_id ),
							'amount'                => (float) $order->get_total(),
							'callback_url'          => add_query_arg(
								array(
									'order_id'   => $order_id,
									'qpay_token' => $callback_token,
								),
								rest_url( 'ohmylms/v1/qpay/callback' )
							),
						)
					);
					if ( is_wp_error( $invoice ) ) {
						$data = $invoice->get_error_data();
						// An explicit client rejection created no invoice. Network/5xx failures are ambiguous.
						if ( isset( $data['http_status'] ) && $data['http_status'] >= 400 && $data['http_status'] < 500 ) {
							delete_post_meta( $order_id, '_qpay_create_started' );
						}
						return $invoice;
					}
					if ( empty( $invoice['invoice_id'] ) || ! is_string( $invoice['invoice_id'] ) ) {
						return new \WP_Error( 'qpay_invoice_response', __( 'QPay did not return an invoice ID. Please contact the store before paying again.', 'ohmylms' ) );
					}
					update_post_meta( $order_id, '_qpay_invoice_id', $invoice['invoice_id'] );
					update_post_meta( $order_id, '_qpay_invoice_data', $invoice );
					$order->add_order_note( __( 'QPay invoice created; awaiting verified payment.', 'ohmylms' ) );
				}
				$token = get_post_meta( $order_id, '_qpay_browser_token', true );
				if ( ! $token ) {
					$token = wp_generate_password( 48, false, false );
					update_post_meta( $order_id, '_qpay_browser_token', $token );
				}
				if ( empty( $invoice['qr_image'] ) && empty( $invoice['urls'] ) ) {
					return new \WP_Error( 'qpay_invoice_display', __( 'This invoice cannot be displayed. Please contact the store with your order number.', 'ohmylms' ) );
				}
				return array(
					'result'          => 'success',
					'payment_status'  => 'pending',
					'payment_method'  => 'qpay',
					'order_id'        => $order_id,
					'payment_token'   => $token,
					'qpay_invoice_id' => $invoice['invoice_id'],
					'qr_image'        => $invoice['qr_image'] ?? '',
					'urls'            => $invoice['urls'] ?? array(),
				);
			}
		);
	}

	/**
	 * Can read order for Gateway qpay.
	 *
	 * @param mixed  $order Order.
	 * @param string $token Token.
	 */
	public function can_read_order( $order, $token = '' ) {
		if ( ! $order || 'qpay' !== $order->get_payment_method() ) {
			return false; }
		if ( get_current_user_id() && (int) $order->get_user_id() === get_current_user_id() ) {
			return true; }
		$stored = get_post_meta( $order->get_id(), '_qpay_browser_token', true );
		return is_string( $token ) && $stored && hash_equals( $stored, $token );
	}

	/**
	 * Ajax order for Gateway qpay.
	 */
	private function ajax_order() {
		check_ajax_referer( 'ohmylms_qpay_check_payment_nonce', 'nonce' );
		$id    = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$order = ohmylms_ecommerce_get_order( $id );
		$token = isset( $_POST['payment_token'] ) && is_string( $_POST['payment_token'] ) ? wp_unslash( $_POST['payment_token'] ) : '';
		if ( ! $this->can_read_order( $order, $token ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot access this payment.', 'ohmylms' ) ), 403 );
		}
		return $id;
	}

	/**
	 * Ajax check payment for Gateway qpay.
	 */
	public function ajax_check_payment() {
		// Verify directly as a fallback when callbacks cannot reach the site.
		$id     = $this->ajax_order();
		$result = PaymentService::settle( $id, $this, 'poll' );
		$this->clear_paid_cart( $id, $result );
		$this->send_ajax_result( $result );
	}

	/**
	 * Ajax resume for Gateway qpay.
	 */
	public function ajax_resume() {
		$id = $this->ajax_order();
		// Explicit customer recovery also works when the provider callback was delayed or unreachable.
		$state = PaymentService::settle( $id, $this, true );
		$this->clear_paid_cart( $id, $state );
		if ( is_wp_error( $state ) || 'paid' === $state['status'] ) {
			$this->send_ajax_result( $state );
			return; }
		$this->send_ajax_result( $this->process_payment( $id ) );
	}

	/**
	 * Send ajax result for Gateway qpay.
	 *
	 * @param mixed $result Result.
	 */
	private function send_ajax_result( $result ) {
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				),
				'qpay_busy' === $result->get_error_code() ? 409 : 400
			);
		}
		wp_send_json_success( $result );
	}

	/**
	 * Clear paid cart for Gateway qpay.
	 *
	 * @param mixed $id Id.
	 * @param mixed $result Result.
	 */
	private function clear_paid_cart( $id, $result ) {
		if ( ! is_wp_error( $result ) && 'paid' === ( $result['status'] ?? '' ) && ecommerce()->cart ) {
			$order = ohmylms_ecommerce_get_order( $id );
			if ( $order && $order->get_cart_hash() === ecommerce()->cart->get_cart_hash() ) {
				ohmylms_empty_cart();
			}
		}
	}

	/**
	 * Register callback route for Gateway qpay.
	 */
	public function register_callback_route() {
		register_rest_route(
			'ohmylms/v1',
			'/qpay/callback',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_callback' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle callback for Gateway qpay.
	 *
	 * @param mixed $request REST request.
	 */
	public function handle_callback( $request ) {
		$id       = absint( $request->get_param( 'order_id' ) );
		$token    = get_post_meta( $id, '_qpay_callback_token', true );
		$supplied = $request->get_param( 'qpay_token' );
		// Historical callback URLs have no token. They still require server-to-server verification.
		if ( $token && ( ! is_string( $supplied ) || ! hash_equals( $token, $supplied ) ) ) {
			return new \WP_REST_Response( array( 'message' => 'Invalid callback.' ), 403 );
		}
		$result = PaymentService::settle( $id, $this, true );
		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( array( 'message' => 'Payment could not be verified.' ), 'qpay_order' === $result->get_error_code() ? 404 : 503 );
		}
		return new \WP_REST_Response( array( 'status' => $result['status'] ), 200 );
	}

	/**
	 * Payment scripts for Gateway qpay.
	 */
	public function payment_scripts() {
		if ( ! function_exists( 'is_ohmylms_checkout' ) || ! is_ohmylms_checkout() ) {
			return; }
		$base = 'packages/e-commerce/assets/';
		wp_enqueue_style( 'ohmylms-qpay-checkout', plugins_url( $base . 'css/qpay-checkout.css', OHMYLMS_FILE ), array(), filemtime( OHMYLMS_DIR . '/' . $base . 'css/qpay-checkout.css' ) );
		wp_enqueue_script( 'ohmylms-qpay-checkout', plugins_url( $base . 'js/qpay-checkout.js', OHMYLMS_FILE ), array( 'jquery', 'ohmylms-checkout' ), filemtime( OHMYLMS_DIR . '/' . $base . 'js/qpay-checkout.js' ), true );
		wp_localize_script(
			'ohmylms-qpay-checkout',
			'ohmylms_qpay_params',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'ohmylms_qpay_check_payment_nonce' ),
				'i18n'     => array(
					'title'   => __( 'Pay with QPay', 'ohmylms' ),
					'waiting' => __( 'Waiting for payment confirmation…', 'ohmylms' ),
					'paused'  => __( 'Automatic checking is paused. Your order is still pending. Resume checking if you have paid.', 'ohmylms' ),
					'close'   => __( 'Close', 'ohmylms' ),
					'resume'  => __( 'Resume QPay payment', 'ohmylms' ),
					'check'   => __( 'Resume checking', 'ohmylms' ),
					'banks'   => __( 'Or open your bank app:', 'ohmylms' ),
					'error'   => __( 'Could not load payment status. Please resume checking shortly.', 'ohmylms' ),
					'paid'    => __( 'Payment confirmed. Opening your order…', 'ohmylms' ),
				),
			)
		);
	}
}
