<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * MeetingService class.
 *
 * @package ohmylms-pro
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\Zoom\Includes\Services;

use OhMyLMS\Integrations\Zoom\Includes\Api\Endpoints\MeetingApi;

/**
 * Class MeetingService
 *
 * @package OhMyLMS\Integrations\Zoom\Services
 * @since 1.0.0
 */
class MeetingService {
	/**
	 * The MeetingApi instance.
	 *
	 * @var MeetingApi
	 */
	private $meeting_api;

	/**
	 * MeetingService constructor.
	 *
	 * @param MeetingApi $meeting_api The MeetingApi instance.
	 */
	public function __construct( MeetingApi $meeting_api ) {
		$this->meeting_api = $meeting_api;
	}

	/**
	 * Create a new meeting.
	 *
	 * @param string $user_id The user ID.
	 * @param array  $data    The meeting data.
	 *
	 * @return array The API response.
	 */
	public function create_meeting( $user_id, $data ) {
		// Add any business logic here before creating the meeting.
		return $this->meeting_api->create( $user_id, $data );
	}

	/**
	 * Get the details of a meeting.
	 *
	 * @param int $meeting_id The meeting ID.
	 *
	 * @return array The API response.
	 */
	public function get_meeting( $meeting_id ) {
		return $this->meeting_api->get( $meeting_id );
	}

	/**
	 * Update a meeting.
	 *
	 * @param int   $meeting_id The meeting ID.
	 * @param array $data       The meeting data.
	 *
	 * @return array The API response.
	 */
	public function update_meeting( $meeting_id, $data ) {
		return $this->meeting_api->update( $meeting_id, $data );
	}

	/**
	 * Delete a meeting.
	 *
	 * @param int $meeting_id The meeting ID.
	 *
	 * @return array The API response.
	 */
	public function delete_meeting( $meeting_id ) {
		return $this->meeting_api->delete( $meeting_id );
	}

	/**
	 * List all meetings for a user.
	 *
	 * @param string $user_id The user ID.
	 * @param array  $params  The query parameters.
	 *
	 * @return array The API response.
	 */
	public function list_meetings( $user_id, $params = array() ) {
		return $this->meeting_api->list( $user_id, $params );
	}
}
