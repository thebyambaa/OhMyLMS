<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class PaypalAPI
 * Handles direct PayPal API interactions for token generation, vaulting, products, plans, subscriptions, orders, captures, refunds, and webhooks.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Gateways\Paypal;

// Exit if accessed directly in a non-WordPress environment.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Error;

/**
 * Class PaypalAPI
 * Handles direct PayPal API interactions for token generation, vaulting, products, plans, subscriptions, orders, captures, refunds, and webhooks.
 */
class PaypalAPI {
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
	 * Token expires at.
	 *
	 * @var mixed
	 */
	private $token_expires_at;
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
	 * Constructor
	 *
	 * @param string $client_id PayPal client ID.
	 * @param string $client_secret PayPal client secret.
	 * @param bool   $is_sandbox Whether to use sandbox environment.
	 * @param string $currency Currency code (default: USD).
	 * @param string $min_amount Minimum transaction amount (default: 0.1).
	 */
	public function __construct( $client_id, $client_secret, $is_sandbox = true, $currency = 'USD', $min_amount = '0.1' ) {
		$this->client_id     = $client_id;
		$this->client_secret = $client_secret;
		$this->currency      = $currency;
		$this->min_amount    = $min_amount;
		$this->api_url       = $is_sandbox
			? 'https://api-m.sandbox.paypal.com'
			: 'https://api-m.paypal.com';
	}

	/**
	 * Generates an access token using client credentials.
	 *
	 * @return string|WP_Error Access token or WP_Error on failure
	 */
	public function generate_access_token() {
		if ( ! $this->access_token || $this->is_token_expired() ) {
			$url  = "{$this->api_url}/v1/oauth2/token";
			$auth = base64_encode( "{$this->client_id}:{$this->client_secret}" );
			$body = array( 'grant_type' => 'client_credentials' );
			$args = array(
				'method'  => 'POST',
				'body'    => http_build_query( $body ),
				'headers' => array(
					'Authorization' => 'Basic ' . $auth,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
			);

			$response = wp_remote_request( $url, $args );
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$response_body = wp_remote_retrieve_body( $response );
			$data          = json_decode( $response_body, true );
			if ( isset( $data['access_token'] ) ) {
				$this->access_token     = $data['access_token'];
				$this->token_expires_at = time() + ( $data['expires_in'] ?? 3600 ) - 300; // 5-minute buffer
				return $this->access_token;
			}

			return new WP_Error( 'paypal_error', isset( $data['error_description'] ) ? $data['error_description'] : 'Access token not received' );
		}
		return $this->access_token;
	}

	/**
	 * Checks if the access token is expired.
	 *
	 * @return bool True if token is expired or not set, false otherwise
	 */
	private function is_token_expired() {
		return ! $this->token_expires_at || time() >= $this->token_expires_at;
	}

	/**
	 * Makes an API request to PayPal.
	 *
	 * @param string $method HTTP method (GET, POST, etc.).
	 * @param string $endpoint API endpoint (relative to api_url).
	 * @param array  $params Request body parameters.
	 * @param array  $headers Additional headers.
	 * @return array|WP_Error Response data or WP_Error on failure
	 */
	public function request( $method, $endpoint, $params = array(), $headers = array() ) {
		$token = $this->generate_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$default_headers = array(
			'Authorization'     => 'Bearer ' . $this->access_token,
			'Content-Type'      => 'application/json',
			'PayPal-Request-Id' => uniqid( 'paypal-', true ), // Unique request ID for idempotency.
		);
		$headers         = array_merge( $default_headers, $headers );
		$args            = array(
			'method'  => $method,
			'headers' => $headers,
		);

		if ( ! empty( $params ) ) {
			$args['body'] = wp_json_encode( $params );
		}

		$response = wp_remote_request( $this->api_url . $endpoint, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );
		$status_code   = wp_remote_retrieve_response_code( $response );
		if ( $status_code >= 400 || isset( $data['error'] ) || isset( $data['name'] ) ) {
			$message = $data['error_description']
				?? $data['message']
				?? ( isset( $data['details'][0]['description'] ) ? $data['details'][0]['description'] : null )
				?? 'API request failed';
			return new WP_Error( 'paypal_error', $message );
		}

		return $data;
	}

	/**
	 * Creates a webhook for PayPal notifications.
	 *
	 * @param string $webhook_url Webhook URL.
	 * @param array  $event_types Array of event types to subscribe to.
	 * @return array|WP_Error Webhook data or WP_Error on failure
	 */
	public function create_webhook( $webhook_url, $event_types ) {
		$body = array(
			'url'         => $webhook_url,
			'event_types' => array_map(
				function ( $event ) {
					return array( 'name' => $event );
				},
				$event_types
			),
		);

		return $this->request( 'POST', '/v1/notifications/webhooks', $body );
	}

	/**
	 * Verifies the signature of a webhook event.
	 *
	 * @param string $webhook_id PayPal webhook ID.
	 * @param array  $payload Webhook payload.
	 * @param string $auth_algo Authorization algorithm.
	 * @param string $cert_url Certificate URL.
	 * @param string $transmission_id Transmission ID.
	 * @param string $transmission_sig Transmission signature.
	 * @param string $transmission_time Transmission time.
	 * @return array|WP_Error Verification result or WP_Error on failure
	 */
	public function verify_webhook_signature( $webhook_id, $payload, $auth_algo, $cert_url, $transmission_id, $transmission_sig, $transmission_time ) {
		$body = array(
			'auth_algo'         => $auth_algo,
			'cert_url'          => $cert_url,
			'transmission_id'   => $transmission_id,
			'transmission_sig'  => $transmission_sig,
			'transmission_time' => $transmission_time,
			'webhook_id'        => $webhook_id,
			'webhook_event'     => $payload,
		);

		return $this->request( 'POST', '/v1/notifications/verify-webhook-signature', $body );
	}

	/**
	 * Creates a PayPal order.
	 *
	 * @param array $order_data Order data.
	 * @return array|WP_Error Order data or WP_Error on failure
	 */
	public function create_order( $order_data ) {
		return $this->request( 'POST', '/v2/checkout/orders', $order_data );
	}

	/**
	 * Retrieves a PayPal product by its ID.
	 *
	 * @param mixed $vault_id Vault identifier.
	 * @return array|WP_Error
	 * @since 1.0.0
	 */
	public function get_vault( $vault_id ) {
		$this->generate_access_token();
		return $this->request( 'GET', '/v3/vault/payment-tokens/' . $vault_id );
	}

	/**
	 * Reads a PayPal order without modifying it.
	 *
	 * Unlike get_order_details(), which captures the order, this is a safe
	 * read used to confirm what PayPal actually recorded before a local order
	 * is marked as paid.
	 *
	 * @param string $paypal_order_id PayPal order ID.
	 * @return array|WP_Error
	 */
	public function get_order( $paypal_order_id ) {
		return $this->request( 'GET', '/v2/checkout/orders/' . rawurlencode( $paypal_order_id ) );
	}

	/**
	 * Retrieves the details of a PayPal order.
	 *
	 * @param mixed $token Token.
	 * @return array|WP_Error
	 * @since 1.0.0
	 */
	public function get_order_details( $token ) {
		return $this->request( 'POST', "/v2/checkout/orders/{$token}/capture" );
	}

	/**
	 * Refunds a captured payment.
	 *
	 * @param string      $capture_id Capture ID.
	 * @param float|null  $amount Refund amount.
	 * @param string|null $currency Currency code.
	 * @param string      $reason Refund reason.
	 * @return array|WP_Error Refund data or WP_Error on failure
	 */
	public function refund_capture( $capture_id, $amount = null, $currency = null, $reason = '' ) {
		$this->generate_access_token();
		$body = array();
		if ( $amount && $currency ) {
			$body['amount'] = array(
				'value'         => number_format( $amount, 2, '.', '' ),
				'currency_code' => $currency,
			);
		}
		if ( $reason ) {
			$body['note_to_payer'] = $reason;
		}

		return $this->request( 'POST', "/v2/payments/captures/{$capture_id}/refund", $body );
	}
}
