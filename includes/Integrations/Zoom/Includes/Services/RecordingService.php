<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * RecordingService class.
 *
 * @package ohmylms-pro
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\Zoom\Includes\Services;

use OhMyLMS\Integrations\Zoom\Includes\Api\Endpoints\RecordingApi;

/**
 * Class RecordingService
 *
 * @package OhMyLMS\Integrations\Zoom\Services
 * @since 1.0.0
 */
class RecordingService {
	/**
	 * The RecordingApi instance.
	 *
	 * @var RecordingApi
	 */
	private $recording_api;

	/**
	 * RecordingService constructor.
	 *
	 * @param RecordingApi $recording_api The RecordingApi instance.
	 */
	public function __construct( RecordingApi $recording_api ) {
		$this->recording_api = $recording_api;
	}

	/**
	 * List all recordings for a user.
	 *
	 * @param string $user_id The user ID.
	 * @param array  $params  The query parameters.
	 *
	 * @return array The API response.
	 */
	public function list_recordings( $user_id, $params = array() ) {
		return $this->recording_api->list( $user_id, $params );
	}

	/**
	 * Get all recordings for a meeting.
	 *
	 * @param int   $meeting_id The meeting ID.
	 * @param array $params     The query parameters.
	 *
	 * @return array The API response.
	 */
	public function get_recordings_for_meeting( $meeting_id, $params = array() ) {
		return $this->recording_api->get_for_meeting( $meeting_id, $params );
	}

	/**
	 * Delete a recording.
	 *
	 * @param string $meeting_id The meeting ID.
	 *
	 * @return array The API response.
	 */
	public function delete_recording( $meeting_id ) {
		return $this->recording_api->delete( $meeting_id );
	}
}
