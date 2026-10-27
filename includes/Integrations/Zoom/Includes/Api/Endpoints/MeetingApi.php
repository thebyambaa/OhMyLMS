<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * MeetingApi class.
 *
 * @package ohmylms-pro
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\Zoom\Includes\Api\Endpoints;

use OhMyLMS\Integrations\Zoom\Includes\Api\ZoomApiClient;

/**
 * Class MeetingApi
 *
 * @package OhMyLMS\Integrations\Zoom\Api\Endpoints
 * @since 1.0.0
 */
class MeetingApi {
	/**
	 * The ZoomApiClient instance.
	 *
	 * @var ZoomApiClient
	 */
	private $client;

	/**
	 * MeetingApi constructor.
	 *
	 * @param ZoomApiClient $client The ZoomApiClient instance.
	 */
	public function __construct( ZoomApiClient $client ) {
		$this->client = $client;
	}

	/**
	 * Create a new meeting.
	 *
	 * @param string $user_id The user ID.
	 * @param array  $data    The meeting data.
	 *
	 * @return array The API response.
	 */
	public function create( $user_id, $data ) {
		return $this->client->post( "users/{$user_id}/meetings", $data );
	}

	/**
	 * Get the details of a meeting.
	 *
	 * @param int $meeting_id The meeting ID.
	 *
	 * @return array The API response.
	 */
	public function get( $meeting_id ) {
		return $this->client->get( "meetings/{$meeting_id}" );
	}

	/**
	 * Update a meeting.
	 *
	 * @param int   $meeting_id The meeting ID.
	 * @param array $data       The meeting data.
	 *
	 * @return array The API response.
	 */
	public function update( $meeting_id, $data ) {
		return $this->client->patch( "meetings/{$meeting_id}", $data );
	}

	/**
	 * Delete a meeting.
	 *
	 * @param int $meeting_id The meeting ID.
	 *
	 * @return array The API response.
	 */
	public function delete( $meeting_id ) {
		return $this->client->delete( "meetings/{$meeting_id}" );
	}

	/**
	 * List all meetings for a user.
	 *
	 * @param string $user_id The user ID.
	 * @param array  $params  The query parameters.
	 *
	 * @return array The API response.
	 */
	public function list( $user_id, $params = array() ) {
		return $this->client->get( "users/{$user_id}/meetings", $params );
	}
}
