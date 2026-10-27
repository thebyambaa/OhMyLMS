<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * GoogleMeetApiClient class.
 *
 * @package ohmylms-pro
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\GoogleMeet\Includes\Api;

use OhMyLMS\Integrations\GoogleMeet\Includes\Api\GoogleMeetApiInterface;
use OhMyLMS\Integrations\GoogleMeet\Includes\Services\TokenService;

/**
 * Class GoogleMeetApiClient
 *
 * @package OhMyLMS\Integrations\GoogleMeet\Api
 * @since 1.0.0
 */
class GoogleMeetApiClient implements GoogleMeetApiInterface {
	/**
	 * The base URI for the Google Calendar API.
	 *
	 * @var string
	 */
	private $base_uri = 'https://www.googleapis.com/calendar/v3/';

	/**
	 * The TokenService instance.
	 *
	 * @var TokenService
	 */
	private $token_service;

	/**
	 * GoogleMeetApiClient constructor.
	 *
	 * @param TokenService $token_service The TokenService instance.
	 */
	public function __construct( TokenService $token_service ) {
		$this->token_service = $token_service;
	}

	/**
	 * Make a GET request to the Google Calendar API.
	 *
	 * @param string $endpoint The API endpoint.
	 * @param array  $params   The query parameters.
	 *
	 * @return array The API response.
	 */
	public function get( $endpoint, $params = array() ) {
		$url = $this->base_uri . $endpoint;
		if ( ! empty( $params ) ) {
			$url .= '?' . http_build_query( $params );
		}
		return $this->request( 'GET', $url );
	}

	/**
	 * Make a POST request to the Google Calendar API.
	 *
	 * @param string $endpoint The API endpoint.
	 * @param array  $data     The request body.
	 *
	 * @return array The API response.
	 */
	public function post( $endpoint, $data = array() ) {
		return $this->request( 'POST', $this->base_uri . $endpoint, $data );
	}

	/**
	 * Make a PUT request to the Google Calendar API.
	 *
	 * @param string $endpoint The API endpoint.
	 * @param array  $data     The request body.
	 *
	 * @return array The API response.
	 */
	public function put( $endpoint, $data = array() ) {
		return $this->request( 'PUT', $this->base_uri . $endpoint, $data );
	}

	/**
	 * Make a PATCH request to the Google Calendar API.
	 *
	 * @param string $endpoint The API endpoint.
	 * @param array  $data     The request body.
	 *
	 * @return array The API response.
	 */
	public function patch( $endpoint, $data = array() ) {
		return $this->request( 'PATCH', $this->base_uri . $endpoint, $data );
	}

	/**
	 * Make a DELETE request to the Google Calendar API.
	 *
	 * @param string $endpoint The API endpoint.
	 *
	 * @return array The API response.
	 */
	public function delete( $endpoint ) {
		return $this->request( 'DELETE', $this->base_uri . $endpoint );
	}

	/**
	 * Make a request to the Google Calendar API.
	 *
	 * @param string $method   The HTTP method.
	 * @param string $url      The request URL.
	 * @param array  $data     The request body.
	 *
	 * @return array The API response.
	 */
	private function request( $method, $url, $data = array() ) {
		$token = $this->token_service->get_valid_access_token();

		if ( ! $token ) {
			return array(
				'success' => false,
				'message' => 'No valid access token available',
				'code'    => null,
			);
		}

		$headers = array(
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
		);

		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => 30,
		);

		if ( ! empty( $data ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = \wp_remote_request( $url, $args );

		if ( \is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
				'code'    => null,
			);
		}

		$status_code = \wp_remote_retrieve_response_code( $response );
		$body        = \wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( '' === $body && in_array( $status_code, array( 200, 204 ) ) ) {
			return array(
				'success' => true,
				'code'    => $status_code,
				'data'    => array(),
			);
		}

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return array(
				'success' => false,
				'message' => 'Invalid JSON response from Google Calendar API.',
				'code'    => $status_code,
				'raw'     => $body,
			);
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			$error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Google Calendar API error.';
			return array(
				'success' => false,
				'message' => $error_message,
				'code'    => $status_code,
				'data'    => $data,
			);
		}

		return array(
			'success' => true,
			'code'    => $status_code,
			'data'    => $data,
		);
	}
}
