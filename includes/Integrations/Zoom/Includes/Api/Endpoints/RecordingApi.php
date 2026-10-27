<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * RecordingApi class.
 *
 * @package ohmylms-pro
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\Zoom\Includes\Api\Endpoints;

use OhMyLMS\Integrations\Zoom\Includes\Api\ZoomApiClient;

/**
 * Class RecordingApi
 *
 * @package OhMyLMS\Integrations\Zoom\Api\Endpoints
 * @since 1.0.0
 */
class RecordingApi {
	/**
	 * The ZoomApiClient instance.
	 *
	 * @var ZoomApiClient
	 */
	private $client;

	/**
	 * RecordingApi constructor.
	 *
	 * @param ZoomApiClient $client The ZoomApiClient instance.
	 */
	public function __construct( ZoomApiClient $client ) {
		$this->client = $client;
	}

	/**
	 * Get all recordings for a user.
	 *
	 * @param string $user_id The user ID.
	 * @param array  $params  The query parameters.
	 *
	 * @return array The API response.
	 */
	public function list( $user_id, $params = array() ) {
		return $this->client->get( "users/{$user_id}/recordings", $params );
	}

	/**
	 * Get all recordings for a meeting.
	 *
	 * @param int   $meeting_id The meeting ID.
	 * @param array $params     The query parameters.
	 *
	 * @return array The API response.
	 */
	public function get_for_meeting( $meeting_id, $params = array() ) {
		return $this->client->get( "meetings/{$meeting_id}/recordings", $params );
	}

	/**
	 * Delete a recording.
	 *
	 * @param int $meeting_id The meeting ID.
	 *
	 * @return array The API response.
	 */
	public function delete( $meeting_id ) {
		return $this->client->delete( "meetings/{$meeting_id}/recordings" );
	}
}
