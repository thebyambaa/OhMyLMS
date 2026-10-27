<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * ZoomApiInterface class.
 *
 * @package ohmylms-pro
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\Zoom\Includes\Api;

/**
 * Interface ZoomApiInterface
 *
 * @package OhMyLMS\Integrations\Zoom\Interfaces
 * @since 1.0.0
 */
interface ZoomApiInterface {
	/**
	 * Make a GET request to the Zoom API.
	 *
	 * @param string $endpoint The API endpoint.
	 * @param array  $params   The query parameters.
	 *
	 * @return array The API response.
	 */
	public function get( $endpoint, $params = array() );

	/**
	 * Make a POST request to the Zoom API.
	 *
	 * @param string $endpoint The API endpoint.
	 * @param array  $data     The request body.
	 *
	 * @return array The API response.
	 */
	public function post( $endpoint, $data = array() );

	/**
	 * Make a PUT request to the Zoom API.
	 *
	 * @param string $endpoint The API endpoint.
	 * @param array  $data     The request body.
	 *
	 * @return array The API response.
	 */
	public function put( $endpoint, $data = array() );

	/**
	 * Make a DELETE request to the Zoom API.
	 *
	 * @param string $endpoint The API endpoint.
	 *
	 * @return array The API response.
	 */
	public function delete( $endpoint );
}
