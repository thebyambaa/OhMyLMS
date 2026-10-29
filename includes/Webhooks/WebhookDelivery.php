<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class WebhookDelivery.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Webhooks;

use OhMyLMS\Data\Webhook;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Sends a webhook request: SSRF checks, formatting, signing, logging.
 *
 * Both real event deliveries (WebhookSender) and the REST test endpoint go through send(), so
 * the safety rules below apply everywhere:
 *
 * - Only http(s) URLs without embedded credentials, and only hosts that pass wp_http_validate_url()
 *   (private, loopback and reserved addresses are refused unless a site opts in through the core
 *   `http_request_host_is_external` filter).
 * - wp_safe_remote_request() with redirects disabled, so a 3xx can never lead to an internal host.
 * - A short timeout, and a truncated response body.
 * - HMAC-SHA256 signature over "{timestamp}.{body}" when the webhook has a secret.
 *
 * @package OhMyLMS\Webhooks
 * @since 1.1.0
 */
class WebhookDelivery {

	/**
	 * Default request timeout in seconds.
	 */
	const TIMEOUT = 5;

	/**
	 * Maximum characters of the response body that are returned and stored.
	 */
	const RESPONSE_BODY_LIMIT = 2000;

	/**
	 * Maximum characters of the request body that are stored.
	 */
	const REQUEST_BODY_LIMIT = 4000;

	/**
	 * Validate an outbound webhook URL.
	 *
	 * @param mixed $url URL to validate.
	 * @return true|WP_Error
	 */
	public static function validate_url( $url ) {
		if ( ! is_string( $url ) || '' === $url || strlen( $url ) > 2048 ) {
			return new WP_Error( 'ohmylms_webhook_invalid_url', __( 'The webhook URL is missing or too long.', 'ohmylms' ), array( 'status' => 422 ) );
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['scheme'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return new WP_Error( 'ohmylms_webhook_invalid_url', __( 'The webhook URL must start with http:// or https://.', 'ohmylms' ), array( 'status' => 422 ) );
		}

		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return new WP_Error( 'ohmylms_webhook_invalid_url', __( 'The webhook URL must not contain a username or password.', 'ohmylms' ), array( 'status' => 422 ) );
		}

		// Reject anything esc_url_raw() would rewrite (spaces, control characters, stray markup).
		if ( esc_url_raw( $url, array( 'http', 'https' ) ) !== $url ) {
			return new WP_Error( 'ohmylms_webhook_invalid_url', __( 'The webhook URL contains invalid characters.', 'ohmylms' ), array( 'status' => 422 ) );
		}

		if ( false === wp_http_validate_url( $url ) ) {
			return new WP_Error( 'ohmylms_webhook_unsafe_url', __( 'The webhook URL points to a private, local or otherwise disallowed address.', 'ohmylms' ), array( 'status' => 422 ) );
		}

		return true;
	}

	/**
	 * Build a sample payload for an event.
	 *
	 * @param string $event Trigger event key.
	 * @return array
	 */
	public static function sample_payload( $event ) {
		$payload = array(
			'user_id'      => 1,
			'user_email'   => 'student@example.com',
			'user_name'    => 'Sample Student',
			'course_id'    => 100,
			'course_title' => 'Sample Course',
			'event_time'   => current_time( 'mysql' ),
			'site_url'     => get_site_url(),
		);

		switch ( $event ) {
			case 'course_purchase':
				$payload += array(
					'course_price' => 49.0,
					'order_id'     => 1001,
					'order_total'  => 49.0,
				);
				break;
			case 'course_completion':
				$payload += array(
					'completion_date'       => current_time( 'mysql' ),
					'completion_percentage' => 100,
				);
				break;
			case 'lesson_completion':
				$payload += array(
					'lesson_id'    => 200,
					'lesson_title' => 'Sample Lesson',
				);
				break;
			case 'quiz_submission':
			case 'quiz_achievement':
				$payload += array(
					'quiz_id'    => 300,
					'quiz_title' => 'Sample Quiz',
					'score'      => 90,
				);
				break;
			case 'assignment_submission':
			case 'assignment_achievement':
				$payload += array(
					'assignment_id'    => 400,
					'assignment_title' => 'Sample Assignment',
					'marks'            => 18,
				);
				break;
		}

		/**
		 * Filters the sample payload used by the webhook test endpoint.
		 *
		 * @param array  $payload Sample payload.
		 * @param string $event   Trigger event key.
		 */
		return apply_filters( 'ohmylms_webhook_sample_payload', $payload, $event );
	}

	/**
	 * Apply a webhook's `{ key, value }` data mapping to a raw payload.
	 *
	 * @param array $payload Raw payload keyed by source field.
	 * @param mixed $mapping List of rows (or a JSON string). Empty means send the raw payload.
	 * @return array
	 */
	public static function map_payload( array $payload, $mapping ) {
		if ( is_string( $mapping ) ) {
			$mapping = json_decode( $mapping, true );
		}
		if ( ! is_array( $mapping ) || empty( $mapping ) ) {
			return $payload;
		}

		$mapped = array();
		foreach ( $mapping as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['key'], $row['value'] ) || '' === (string) $row['key'] ) {
				continue;
			}
			if ( array_key_exists( $row['value'], $payload ) ) {
				$mapped[ (string) $row['key'] ] = $payload[ $row['value'] ];
			}
		}

		return $mapped;
	}

	/**
	 * Send a webhook.
	 *
	 * @param Webhook $webhook Webhook to deliver (does not need to be saved).
	 * @param array   $payload Payload, already mapped.
	 * @param array   $options {
	 *     Optional.
	 *
	 *     @type string $event   Event key. Default: the webhook trigger.
	 *     @type bool   $is_test Whether this is a test delivery. Default false.
	 *     @type bool   $log     Persist the attempt for saved webhooks. Default true.
	 *     @type int    $timeout Seconds. Default WebhookDelivery::TIMEOUT.
	 * }
	 * @return array {
	 *     @type string     $delivery_id        UUID of this attempt.
	 *     @type bool       $success            True for a 2xx response.
	 *     @type int        $status_code        HTTP status, 0 when no response.
	 *     @type int        $duration_ms        Elapsed milliseconds.
	 *     @type string     $response_body      Truncated response body.
	 *     @type bool       $response_truncated Whether the body was cut.
	 *     @type array|null $error              { code, message } or null.
	 *     @type array      $request            { url, method, data_type, event, body }.
	 * }
	 */
	public static function send( Webhook $webhook, array $payload, array $options = array() ) {
		$options = wp_parse_args(
			$options,
			array(
				'event'   => $webhook->get_trigger_event(),
				'is_test' => false,
				'log'     => true,
				'timeout' => (int) apply_filters( 'ohmylms_webhook_timeout', self::TIMEOUT, $webhook ),
			)
		);

		$method    = strtoupper( (string) $webhook->get_http_method() );
		$method    = in_array( $method, array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ? $method : 'POST';
		$data_type = in_array( $webhook->get_data_type(), array( 'json', 'xml', 'form' ), true ) ? $webhook->get_data_type() : 'json';
		$url       = (string) $webhook->get_webhook_url();
		$body      = 'GET' === $method ? '' : self::format_payload( $payload, $data_type );
		$uuid      = wp_generate_uuid4();
		$timestamp = time();

		$result = array(
			'delivery_id'        => $uuid,
			'success'            => false,
			'status_code'        => 0,
			'duration_ms'        => 0,
			'response_body'      => '',
			'response_truncated' => false,
			'error'              => null,
			'request'            => array(
				'url'       => $url,
				'method'    => $method,
				'data_type' => $data_type,
				'event'     => (string) $options['event'],
				'body'      => $body,
			),
		);

		$valid = self::validate_url( $url );
		if ( is_wp_error( $valid ) ) {
			$result['error'] = array(
				'code'    => $valid->get_error_code(),
				'message' => $valid->get_error_message(),
			);
		} else {
			$headers = array(
				'User-Agent'          => 'OhMyLMS-Webhooks/1.0 (+' . home_url( '/' ) . ')',
				'X-OhMyLMS-Event'     => (string) $options['event'],
				'X-OhMyLMS-Delivery'  => $uuid,
				'X-OhMyLMS-Timestamp' => (string) $timestamp,
			);
			if ( '' !== $body ) {
				$headers['Content-Type'] = self::content_type( $data_type );
			}
			if ( $options['is_test'] ) {
				$headers['X-OhMyLMS-Test'] = '1';
			}
			$secret = $webhook->get_secret( 'edit' );
			if ( '' !== $secret ) {
				$headers['X-OhMyLMS-Signature'] = 'sha256=' . self::sign( $secret, $timestamp, $body );
			}

			$request_url = 'GET' === $method ? add_query_arg( urlencode_deep( $payload ), $url ) : $url;
			$args        = array(
				'method'      => $method,
				'timeout'     => max( 1, (int) $options['timeout'] ),
				'redirection' => 0,
				'headers'     => $headers,
				'body'        => $body,
			);

			/**
			 * Filters the arguments of an outgoing webhook request.
			 *
			 * The request is always sent with wp_safe_remote_request(); do not use this filter to
			 * relax URL safety.
			 *
			 * @param array   $args    Request arguments.
			 * @param Webhook $webhook Webhook.
			 * @param string  $event   Event key.
			 */
			$args = apply_filters( 'ohmylms_webhook_request_args', $args, $webhook, (string) $options['event'] );

			$started               = microtime( true );
			$response              = wp_safe_remote_request( $request_url, $args );
			$result['duration_ms'] = (int) round( ( microtime( true ) - $started ) * 1000 );

			if ( is_wp_error( $response ) ) {
				$result['error'] = array(
					'code'    => 'ohmylms_webhook_request_failed',
					'message' => $response->get_error_message(),
				);
			} else {
				$code                         = (int) wp_remote_retrieve_response_code( $response );
				$result['status_code']        = $code;
				$truncated                    = self::truncate( (string) wp_remote_retrieve_body( $response ), self::RESPONSE_BODY_LIMIT );
				$result['response_body']      = $truncated[0];
				$result['response_truncated'] = $truncated[1];

				if ( $code >= 200 && $code < 300 ) {
					$result['success'] = true;
				} else {
					$result['error'] = array(
						'code'    => 'ohmylms_webhook_http_error',
						/* translators: %d: HTTP status code returned by the receiving endpoint. */
						'message' => sprintf( __( 'The endpoint responded with HTTP %d (redirects are not followed).', 'ohmylms' ), $code ),
					);
				}
			}
		}

		self::record( $webhook, $payload, $result, $options );

		return $result;
	}

	/**
	 * Compute the HMAC-SHA256 signature of a delivery.
	 *
	 * @param string $secret    Signing secret.
	 * @param int    $timestamp Unix timestamp sent in X-OhMyLMS-Timestamp.
	 * @param string $body      Raw request body.
	 * @return string Lowercase hex digest.
	 */
	public static function sign( $secret, $timestamp, $body ) {
		return hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
	}

	/**
	 * Persist the attempt and notify listeners.
	 *
	 * @param Webhook $webhook Webhook.
	 * @param array   $payload Mapped payload.
	 * @param array   $result  Result of send().
	 * @param array   $options Send options.
	 * @return void
	 */
	private static function record( Webhook $webhook, array $payload, array $result, array $options ) {
		$status = $result['success'] ? 'success' : 'failed';

		if ( $options['log'] && $webhook->get_id() ) {
			$request_body = self::truncate( (string) $result['request']['body'], self::REQUEST_BODY_LIMIT );
			DeliveryLog::insert(
				array(
					'webhook_id'    => $webhook->get_id(),
					'delivery_uuid' => $result['delivery_id'],
					'event'         => $result['request']['event'],
					'url'           => $result['request']['url'],
					'http_method'   => $result['request']['method'],
					'status'        => $status,
					'response_code' => $result['status_code'],
					'duration_ms'   => $result['duration_ms'],
					'error_code'    => $result['error'] ? $result['error']['code'] : '',
					'error_message' => $result['error'] ? $result['error']['message'] : '',
					'request_body'  => $request_body[0],
					'response_body' => $result['response_body'],
					'is_test'       => $options['is_test'],
				)
			);
		}

		$log_data = array(
			'webhook_id'    => $webhook->get_id(),
			'webhook_name'  => $webhook->get_name(),
			'trigger_event' => $result['request']['event'],
			'webhook_url'   => $result['request']['url'],
			'payload'       => $payload,
			'timestamp'     => current_time( 'mysql' ),
			'status'        => $status,
			'response_code' => $result['status_code'],
			'response_body' => $result['response_body'],
			'duration_ms'   => $result['duration_ms'],
			'is_test'       => (bool) $options['is_test'],
			'delivery_id'   => $result['delivery_id'],
		);
		if ( $result['error'] ) {
			$log_data['message'] = $result['error']['message'];
		}

		/**
		 * Fires after every webhook delivery attempt (real and test).
		 *
		 * `status` is `success` for a 2xx response and `failed` otherwise (transport error, unsafe
		 * URL or non-2xx response).
		 *
		 * @param array $log_data Delivery details.
		 */
		do_action( 'ohmylms_webhook_sent', $log_data );
	}

	/**
	 * Truncate a string to a number of characters.
	 *
	 * @param string $value Value.
	 * @param int    $limit Maximum characters.
	 * @return array{0:string,1:bool} Truncated string and whether it was cut.
	 */
	public static function truncate( $value, $limit ) {
		$value = (string) wp_check_invalid_utf8( $value, true );
		if ( mb_strlen( $value, 'UTF-8' ) <= $limit ) {
			return array( $value, false );
		}
		return array( mb_substr( $value, 0, $limit, 'UTF-8' ), true );
	}

	/**
	 * Get the Content-Type header for a data type.
	 *
	 * @param string $data_type json, xml or form.
	 * @return string
	 */
	private static function content_type( $data_type ) {
		switch ( $data_type ) {
			case 'xml':
				return 'application/xml';
			case 'form':
				return 'application/x-www-form-urlencoded';
			default:
				return 'application/json';
		}
	}

	/**
	 * Encode a payload.
	 *
	 * @param array  $payload   Payload.
	 * @param string $data_type json, xml or form.
	 * @return string
	 */
	private static function format_payload( array $payload, $data_type ) {
		switch ( $data_type ) {
			case 'xml':
				$xml = new \SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8"?><webhook/>' );
				self::array_to_xml( $payload, $xml );
				return (string) $xml->asXML();
			case 'form':
				return http_build_query( $payload, '', '&' );
			default:
				return (string) wp_json_encode( $payload );
		}
	}

	/**
	 * Append an array to an XML element, sanitising element names.
	 *
	 * @param array             $data Data.
	 * @param \SimpleXMLElement $xml  Parent element.
	 * @return void
	 */
	private static function array_to_xml( array $data, \SimpleXMLElement $xml ) {
		foreach ( $data as $key => $value ) {
			$name = preg_replace( '/[^A-Za-z0-9_.-]/', '_', (string) $key );
			if ( '' === $name || ! preg_match( '/^[A-Za-z_]/', $name ) ) {
				$name = 'item_' . $name;
			}

			if ( is_array( $value ) ) {
				self::array_to_xml( $value, $xml->addChild( $name ) );
			} else {
				$xml->addChild( $name, htmlspecialchars( is_scalar( $value ) ? (string) $value : '', ENT_XML1 | ENT_QUOTES, 'UTF-8' ) );
			}
		}
	}
}
