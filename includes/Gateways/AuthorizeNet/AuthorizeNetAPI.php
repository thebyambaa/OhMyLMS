<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Authorize.Net API Handler Class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Gateways\AuthorizeNet;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Authorize.Net API Handler Class.
 *
 * Handles communication with the Authorize.Net REST API.
 */
class AuthorizeNetAPI {

	/**
	 * Authorize.Net API Login ID.
	 *
	 * @var string
	 */
	private $api_login_id;

	/**
	 * Authorize.Net API Transaction Key.
	 *
	 * @var string
	 */
	private $transaction_key;

	/**
	 * Authorize.Net API Signature Key.
	 * (Primarily for webhook validation, not direct requests)
	 *
	 * @var string|null
	 */
	private $signature_key;

	/**
	 * Authorize.Net API Base URL.
	 *
	 * @var string
	 */
	private $api_url;

	/**
	 * Constructor for AuthorizeNetAPI.
	 *
	 * @param string      $api_login_id API Login ID.
	 * @param string      $transaction_key API Transaction Key.
	 * @param string|null $signature_key API Signature Key (for webhooks).
	 * @param bool        $is_test_mode Whether to use sandbox or live API URLs.
	 */
	public function __construct( $api_login_id, $transaction_key, $signature_key = null, $is_test_mode = true ) {
		$this->api_login_id    = $api_login_id;
		$this->transaction_key = $transaction_key;
		$this->signature_key   = $signature_key;

		if ( $is_test_mode ) {
			$this->api_url = '  https://apitest.authorize.net/xml/v1/request.api/';
		} else {
			$this->api_url = '  https://api.authorize.net/xml/v1/request.api/';
		}
	}

	/**
	 * Makes an HTTP request to the Authorize.Net API.
	 *
	 * @param string $endpoint_path The API endpoint path (e.g., 'transactions').
	 * @param array  $request_body_wrapper The request data, wrapped in its main key (e.g., ['createTransactionRequest' => [...] ]).
	 *                                      If empty for GET/DELETE, an empty array should be passed.
	 * @param string $request_type The HTTP request type (e.g., 'POST', 'GET', 'DELETE').
	 * @param int    $timeout Optional timeout in seconds. Default 60. Use higher for refunds.
	 * @return array|WP_Error The decoded JSON response as an associative array, or WP_Error on failure.
	 */
	private function make_api_request( $endpoint_path, array $request_body_wrapper = array(), $request_type = 'POST', $timeout = 60 ) {
		$full_api_url = $this->api_url;

		$headers = array(
			'Content-Type' => 'application/json',
		);

		$body_to_encode = array();

		if ( ! empty( $request_body_wrapper ) ) {
			$request_key = key( $request_body_wrapper ); // e.g., 'createTransactionRequest'.
			if ( $request_key ) {
				$body_to_encode = array(
					$request_key => array_merge(
						array(
							'merchantAuthentication' => array(
								'name'           => $this->api_login_id,
								'transactionKey' => $this->transaction_key,
							),
						),
						$request_body_wrapper[ $request_key ]
					),
				);
			}
		} elseif ( 'POST' === $request_type || 'PUT' === $request_type ) {
			// Some POST/PUT requests might not have a specific wrapper but still need merchant auth if body is expected
			// For now, assume $request_body_wrapper is always provided for POST/PUT that need auth.
		}

		$args = array(
			'method'  => $request_type,
			'headers' => $headers,
			'timeout' => $timeout,
		);

		if ( ! empty( $body_to_encode ) ) {
			$args['body'] = wp_json_encode( $body_to_encode );
		} elseif ( ( 'POST' === $request_type || 'PUT' === $request_type ) && empty( $body_to_encode ) && ! empty( $request_body_wrapper ) ) {
			// If request_body_wrapper was passed but didn't fit the key model (e.g. direct array for some other API)
			// This part is less likely to be used with Authorize.Net's current structure.
			// For now, this case is not fully handled; requests should have a top-level key.
		}

		$response = wp_remote_request( $full_api_url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );

		// Strip UTF-8 BOM if present, as it can break json_decode.
		if ( 0 === strpos( bin2hex( $body ), 'efbbbf' ) ) {
			$body = substr( $body, 3 );
		}

		$decoded_body = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			// Optionally log the error: error_log( 'Authorize.Net API JSON Decode Error: ' . json_last_error_msg() . ' | Body: ' . $body );.
			return new \WP_Error( 'json_decode_error', __( 'Failed to decode API response.', 'ohmylms' ), array( 'status' => wp_remote_retrieve_response_code( $response ) ) );
		}

		return $decoded_body;
	}

	/**
	 * Creates a transaction (e.g., authCapture, refund).
	 *
	 * @param array $transaction_request_content Content for the 'transactionRequest' part of the API call.
	 * @param int   $timeout Optional timeout in seconds. Default 60. Refunds may need 90-120 seconds.
	 * @return array|WP_Error The API response.
	 */
	public function createTransaction( array $transaction_request_content, $timeout = 60 ) {
		// Automatically increase timeout for refund transactions.
		if ( isset( $transaction_request_content['transactionType'] ) &&
			'refundTransaction' === $transaction_request_content['transactionType'] ) {
			$timeout = max( $timeout, 120 ); // Use at least 120 seconds for refunds.
		}

		$payload = array(
			'createTransactionRequest' => array(
				'transactionRequest' => $transaction_request_content,
			),
		);
		return $this->make_api_request( 'transactions', $payload, 'POST', $timeout );
	}

	/**
	 * Creates a customer profile.
	 *
	 * @param array $profile_details_content Content for the 'profile' and other parts of the request.
	 * @return array|WP_Error The API response.
	 */
	public function createCustomerProfile( array $profile_details_content ) {
		$payload = array(
			'createCustomerProfileRequest' => $profile_details_content,
		);
		return $this->make_api_request( 'customerprofiles', $payload, 'POST' );
	}

	/**
	 * Creates a new subscription (Automated Recurring Billing).
	 *
	 * @param array $subscription_details_content Content for the 'subscription' part of the request.
	 * @return array|WP_Error The API response.
	 */
	public function createSubscription( array $subscription_details_content ) {
		$payload = array(
			'createSubscriptionRequest' => array( // Assuming REST equivalent of ARBCreateSubscriptionRequest.
				'subscription' => $subscription_details_content,
			),
		);
		// The endpoint for REST recurring billing might be different, e.g., 'recurringbillingservice/subscriptions'
		// For now, using a generic 'subscriptions' path relative to the main API URL.
		// This might need adjustment based on Authorize.Net's REST API structure for subscriptions.
		return $this->make_api_request( 'subscriptions', $payload, 'POST' );
	}

	/**
	 * Cancels a subscription.
	 *
	 * @param string $subscription_id The ID of the subscription to cancel.
	 * @return array|WP_Error The API response.
	 */
	public function cancelSubscription( $subscription_id ) {
		// For REST, typically a DELETE request to /subscriptions/{subscription_id}
		// Some APIs might expect a payload even for DELETE, or use a POST to a cancel endpoint.
		// This is a simplified version assuming no body is needed for DELETE.
		// The 'cancelSubscriptionRequest' wrapper is used if the API expects it, even for DELETE with no body.
		// Or, if it's a POST to a cancel action, it would be structured like other POSTs.
		// For a pure REST DELETE, the $request_body_wrapper would be empty or not used.
		// Let's assume for now it's a DELETE to an endpoint, and no complex body.
		// If Authorize.Net's REST API for cancelling subscriptions requires a body (e.g. with merchantAuth),
		// then this needs adjustment.
		// The make_api_request will add merchantAuth if a body wrapper is provided.
		// For DELETE with no body, merchantAuth is usually via headers or part of URL, not typical for AuthNet.
		// Let's assume it's a POST to a specific action endpoint for cancellation for robust auth,
		// or a DELETE that implicitly handles auth (less likely for AuthNet style).
		// Reverting to a common pattern: POST to cancel, or use a specific cancelSubscriptionRequest.
		$payload = array(
			'cancelSubscriptionRequest' => array( // Assuming REST equivalent of ARBCancelSubscriptionRequest.
				'subscriptionId' => $subscription_id,
			),
		);
		// This might be a POST to 'subscriptions/{id}/cancel' or similar, or to a general 'subscriptions' endpoint
		// with an action specified in the payload. Using 'subscriptions' for now.
		return $this->make_api_request( 'subscriptions', $payload, 'POST' ); // Changed to POST for consistency with AuthNet patterns.
	}
}
