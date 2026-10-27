<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * GoogleMeetApiInterface interface.
 *
 * @package ohmylms-pro
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\GoogleMeet\Includes\Api;

/**
 * Interface GoogleMeetApiInterface
 *
 * @package OhMyLMS\Integrations\GoogleMeet\Api
 * @since 1.0.0
 */
interface GoogleMeetApiInterface {
	/**
	 * Make a GET request.
	 *
	 * @param string $endpoint The API endpoint.
	 * @param array  $params   The query parameters.
	 *
	 * @return array The API response.
	 */
	public function get( $endpoint, $params = array() );

	/**
	 * Make a POST request.
	 *
	 * @param string $endpoint The API endpoint.
	 * @param array  $data     The request body.
	 *
	 * @return array The API response.
	 */
	public function post( $endpoint, $data = array() );

	/**
	 * Make a PUT request.
	 *
	 * @param string $endpoint The API endpoint.
	 * @param array  $data     The request body.
	 *
	 * @return array The API response.
	 */
	public function put( $endpoint, $data = array() );

	/**
	 * Make a PATCH request.
	 *
	 * @param string $endpoint The API endpoint.
	 * @param array  $data     The request body.
	 *
	 * @return array The API response.
	 */
	public function patch( $endpoint, $data = array() );

	/**
	 * Make a DELETE request.
	 *
	 * @param string $endpoint The API endpoint.
	 *
	 * @return array The API response.
	 */
	public function delete( $endpoint );
}
