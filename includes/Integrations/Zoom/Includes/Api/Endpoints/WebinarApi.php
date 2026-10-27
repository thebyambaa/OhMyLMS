<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * WebinarApi class.
 *
 * @package ohmylms-pro
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\Zoom\Includes\Api\Endpoints;

use OhMyLMS\Integrations\Zoom\Includes\Api\ZoomApiClient;

/**
 * Class WebinarApi
 *
 * @package OhMyLMS\Integrations\Zoom\Api\Endpoints
 * @since 1.0.0
 */
class WebinarApi {
	/**
	 * The ZoomApiClient instance.
	 *
	 * @var ZoomApiClient
	 */
	private $client;

	/**
	 * WebinarApi constructor.
	 *
	 * @param ZoomApiClient $client The ZoomApiClient instance.
	 */
	public function __construct( ZoomApiClient $client ) {
		$this->client = $client;
	}

	/**
	 * Create a new webinar.
	 *
	 * @param string $user_id The user ID.
	 * @param array  $data    The webinar data.
	 *
	 * @return array The API response.
	 */
	public function create( $user_id, $data ) {
		return $this->client->post( "users/{$user_id}/webinars", $data );
	}

	/**
	 * Get the details of a webinar.
	 *
	 * @param int $webinar_id The webinar ID.
	 *
	 * @return array The API response.
	 */
	public function get( $webinar_id ) {
		return $this->client->get( "webinars/{$webinar_id}" );
	}

	/**
	 * Update a webinar.
	 *
	 * @param int   $webinar_id The webinar ID.
	 * @param array $data       The webinar data.
	 *
	 * @return array The API response.
	 */
	public function update( $webinar_id, $data ) {
		return $this->client->put( "webinars/{$webinar_id}", $data );
	}

	/**
	 * Delete a webinar.
	 *
	 * @param int $webinar_id The webinar ID.
	 *
	 * @return array The API response.
	 */
	public function delete( $webinar_id ) {
		return $this->client->delete( "webinars/{$webinar_id}" );
	}

	/**
	 * List all webinars for a user.
	 *
	 * @param string $user_id The user ID.
	 * @param array  $params  The query parameters.
	 *
	 * @return array The API response.
	 */
	public function list( $user_id, $params = array() ) {
		return $this->client->get( "users/{$user_id}/webinars", $params );
	}
}
