<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Commerce\Gateways\Stripe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin stripe api implementation.
 */
class StripeApi {
	/**
	 * Stripe API Endpoint
	 */
	const ENDPOINT = 'https://api.stripe.com/v1/';

	const STRIPE_API_VERSION = '2020-08-27';


	/**
	 * Secret API Key.
	 *
	 * @var string
	 */
	private static $secret_key = '';

	/**
	 * Set secret API Key.
	 *
	 * @param mixed $secret_key Secret key.
	 */
	public static function set_secret_key( $secret_key ) {
		self::$secret_key = $secret_key;
	}

	/**
	 * Get the secret API key.
	 *
	 * @return string The secret API key.
	 */
	public static function get_secret_key() {
		if ( ! self::$secret_key ) {
			$options         = get_option( 'ohmylms_stripe_settings' );
			$secret_key      = $options['secret_key'] ?? '';
			$test_secret_key = $options['sandbox_secret_key'] ?? '';

			if ( isset( $options['testmode'] ) ) {
				self::set_secret_key( 'yes' === $options['testmode'] ? $test_secret_key : $secret_key );
			}
		}
		return self::$secret_key;
	}


	/**
	 * Get the user agent information.
	 *
	 * @return array The user agent information.
	 */
	public static function get_user_agent() {
		return array(
			'application'      => array(
				'name'    => 'OhMyLMS',
				'version' => '1.0.0',
				'url'     => 'https://ohmylms.com',
			),
			'bindings_version' => '1.0.0',
			'lang'             => 'php',
			'lang_version'     => phpversion(),
			'publisher'        => 'ohmylms',
			'uname'            => php_uname(),
		);
	}

	/**
	 * Get the headers for the Stripe API request.
	 *
	 * @return array The headers for the Stripe API request.
	 */
	public static function get_headers() {
		$user_agent = self::get_user_agent();
		$app_info   = $user_agent['application'];

		$headers = array(
			'Authorization'  => 'Basic ' . base64_encode( self::get_secret_key() . ':' ),
			'Stripe-Version' => self::STRIPE_API_VERSION,
		);

		// These headers should not be overridden for this gateway.
		$headers['User-Agent']                 = $app_info['name'] . '/' . $app_info['version'] . ' (' . $app_info['url'] . ')';
		$headers['X-Stripe-Client-User-Agent'] = wp_json_encode( $user_agent );

		return $headers;
	}

	/**
	/**
	 * Makes a request to the Stripe API.
	 *
	 * @param array       $request_body The request data (body).
	 * @param string      $api The API endpoint to use. Default is 'charges'.
	 * @param string      $method The HTTP method to use. Default is 'POST'.
	 * @param bool        $with_headers Whether to include headers in the response. Default is false.
	 * @param string|null $idempotency_key Optional idempotency key. If not provided, one might be generated.
	 * .
	 * @return array|object The response from the Stripe API.
	 *
	 * @throws \Exception If there is a problem connecting to the Stripe API endpoint or if Stripe returns an error.
	 *
	 * @since 1.0.0
	 */
	public static function request( $request_body, $api = 'charges', $method = 'POST', $with_headers = false, $idempotency_key = null ) {
		$headers = self::get_headers();

		// Idempotency for POST requests to relevant endpoints.
		if ( 'POST' === $method ) {
			if ( null === $idempotency_key && isset( $request_body['metadata']['order_id'] ) ) {
				// Generate a basic idempotency key if not provided and order_id is available
				// This can be made more robust or specific if needed.
				$idempotency_key = 'ohmylms-' . $request_body['metadata']['order_id'] . '-' . $api . '-request-' . uniqid();
			}
			if ( $idempotency_key ) {
				$headers['Idempotency-Key'] = $idempotency_key;
			}
		}

		$response = wp_safe_remote_request( // Changed to wp_safe_remote_request for flexibility with GET/POST etc.
			self::ENDPOINT . $api,
			array(
				'method'      => $method,
				'headers'     => $headers,
				'body'        => 'POST' === $method || 'PUT' === $method ? $request_body : null, // Only include body for relevant methods.
				'data_format' => 'body', // Ensures body is sent as form data for POST.
				'timeout'     => 70,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'WP_Error: ' . $response->get_error_message() );
		}

		$response_body    = wp_remote_retrieve_body( $response );
		$response_code    = wp_remote_retrieve_response_code( $response );
		$response_headers = wp_remote_retrieve_headers( $response );

		if ( empty( $response_body ) ) {
			throw new \Exception( __( 'Empty response body from Stripe API.', 'ohmylms' ) );
		}

		$decoded_body = json_decode( $response_body );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new \Exception( __( 'Failed to decode JSON response from Stripe API.', 'ohmylms' ) . ' Body: ' . substr( $response_body, 0, 200 ) );
		}

		// Check for Stripe errors in the response body itself
		// Stripe often returns 200 OK for API errors but includes an 'error' object in the JSON.
		if ( isset( $decoded_body->error ) ) {
			// It's good practice to throw an exception here so calling code can catch it.
			// The message can be made more specific based on $decoded_body->error properties.
			$error_message = isset( $decoded_body->error->message ) ? $decoded_body->error->message : 'Unknown Stripe API error.';
			// You might want to include more details like error type or code if available.
			// For example: $error_message .= " (Type: {$decoded_body->error->type}, Code: {$decoded_body->error->code})";
			// throw new \Exception( 'Stripe API Error: ' . $error_message );
			// For now, we will return the decoded body with the error, OhMyLMS_Gateway_Stripe_Intents will check for ->error.
		}

		if ( $with_headers ) {
			return array(
				'headers' => $response_headers,
				'body'    => $decoded_body,
			);
		}

		return $decoded_body;
	}

	/**
	 * Retrieve a resource from the Stripe API.
	 *
	 * @param string $payment_method_id Payment method identifier.
	 * @return array|object The response from the Stripe API.
	 *
	 * @throws \Exception If there is a problem connecting to the Stripe API endpoint.
	 *
	 * @since 1.0.0
	 */
	public static function get_payment_method( string $payment_method_id ) {
		// Sources have a separate API.
		if ( 0 === strpos( $payment_method_id, 'src_' ) ) {
			return self::retrieve( 'sources/' . $payment_method_id );
		}

		// If it's not a source it's a PaymentMethod.
		return self::retrieve( 'payment_methods/' . $payment_method_id );
	}


	/**
	 * Retrieve a resource from the Stripe API.
	 *
	 * @param string $api The API endpoint to retrieve.
	 * .
	 * @return array|object The response from the Stripe API.
	 *
	 * @throws \Exception If there is a problem connecting to the Stripe API endpoint.
	 *
	 * @since 1.0.0
	 */
	public static function retrieve( $api ) {
		$response = wp_safe_remote_get(
			self::ENDPOINT . $api,
			array(
				'method'  => 'GET',
				'headers' => self::get_headers(),
				'timeout' => 70,
			)
		);

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			return new \WP_Error( 'stripe_error', __( 'There was a problem connecting to the Stripe API endpoint.', 'ohmylms' ) );
		}

		return json_decode( $response['body'] );
	}


	/**
	 * Request with level3 data for Stripe api.
	 *
	 * @param mixed $request REST request.
	 * @param mixed $api Api.
	 * @param mixed $level3_data Level3 data.
	 * @param mixed $order Order.
	 */
	public static function request_with_level3_data( $request, $api, $level3_data, $order ) {
		$result = self::request(
			$request,
			$api
		);

		return $result;
	}

	/**
	 * Attach payment method for Stripe api.
	 *
	 * @param mixed $payment_method_id Payment method identifier.
	 * @param mixed $customer_id Customer identifier.
	 */
	public static function attach_payment_method( $payment_method_id, $customer_id ) {
		$request = array(
			'customer' => $customer_id,
		);

		return self::request( $request, 'payment_methods/' . $payment_method_id . '/attach', 'POST' );
	}

	/**
	 * Update customer default payment method for Stripe api.
	 *
	 * @param mixed $customer_id Customer identifier.
	 * @param mixed $payment_method_id Payment method identifier.
	 */
	public static function update_customer_default_payment_method( $customer_id, $payment_method_id ) {
		$request = array(
			'invoice_settings' => array(
				'default_payment_method' => $payment_method_id,
			),
		);

		return self::request( $request, 'customers/' . $customer_id, 'POST' );
	}
}
