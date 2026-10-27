<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * MeetingService class.
 *
 * @package ohmylms-pro
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\GoogleMeet\Includes\Services;

use OhMyLMS\Integrations\GoogleMeet\Includes\Api\GoogleMeetApiClient;
use OhMyLMS\Integrations\GoogleMeet\Includes\Services\TokenService;

/**
 * Class MeetingService
 *
 * @package OhMyLMS\Integrations\GoogleMeet\Services
 * @since 1.0.0
 */
class MeetingService {
	/**
	 * The GoogleMeetApiClient instance.
	 *
	 * @var GoogleMeetApiClient
	 */
	private $api_client;

	/**
	 * MeetingService constructor.
	 */
	public function __construct() {
		$token_service    = new TokenService();
		$this->api_client = new GoogleMeetApiClient( $token_service );
	}

	/**
	 * Create a Google Meet meeting.
	 *
	 * @since 1.0.0
	 *
	 * @param array $meeting_data The meeting data.
	 *
	 * @return array The API response.
	 */
	public function create_meeting( $meeting_data ) {
		$calendar_id = 'primary'; // Use primary calendar.

		// Prepare event data for Google Calendar API.
		$event_data = array(
			'summary'        => $meeting_data['topic'],
			'description'    => $meeting_data['agenda'],
			'start'          => array(
				'dateTime' => $this->format_datetime( $meeting_data['date'], $meeting_data['timezone'] ),
				'timeZone' => $meeting_data['timezone'],
			),
			'end'            => array(
				'dateTime' => $this->calculate_end_time( $meeting_data['date'], $meeting_data['duration'], $meeting_data['timezone'] ),
				'timeZone' => $meeting_data['timezone'],
			),
			'conferenceData' => array(
				'createRequest' => array(
					'requestId'             => uniqid( 'ohmylms_' ),
					'conferenceSolutionKey' => array(
						'type' => 'hangoutsMeet',
					),
				),
			),
		);

		// Add optional settings.
		if ( ! empty( $meeting_data['attendees'] ) ) {
			$event_data['attendees'] = $this->format_attendees( $meeting_data['attendees'] );
		}

		// Create event with conference data.
		$response = $this->api_client->post(
			"calendars/{$calendar_id}/events?conferenceDataVersion=1",
			$event_data
		);

		if ( $response['success'] && isset( $response['data']['id'] ) ) {
			$event_data = $response['data'];

			// Extract Google Meet link.
			$meet_link = '';
			if ( isset( $event_data['conferenceData']['entryPoints'] ) ) {
				foreach ( $event_data['conferenceData']['entryPoints'] as $entry ) {
					if ( 'video' === $entry['entryPointType'] ) {
						$meet_link = $entry['uri'];
						break;
					}
				}
			}

			return array(
				'success'    => true,
				'meeting_id' => $event_data['id'],
				'meet_link'  => $meet_link,
				'html_link'  => $event_data['htmlLink'],
				'event_data' => $event_data,
			);
		}

		return $response;
	}

	/**
	 * Update a Google Meet meeting.
	 *
	 * @since 1.0.0
	 *
	 * @param string $meeting_id   The meeting ID.
	 * @param array  $meeting_data The updated meeting data.
	 *
	 * @return array The API response.
	 */
	public function update_meeting( $meeting_id, $meeting_data ) {
		$calendar_id = 'primary';

		$event_data = array(
			'summary'     => $meeting_data['topic'],
			'description' => $meeting_data['agenda'],
			'start'       => array(
				'dateTime' => $this->format_datetime( $meeting_data['date'], $meeting_data['timezone'] ),
				'timeZone' => $meeting_data['timezone'],
			),
			'end'         => array(
				'dateTime' => $this->calculate_end_time( $meeting_data['date'], $meeting_data['duration'], $meeting_data['timezone'] ),
				'timeZone' => $meeting_data['timezone'],
			),
		);

		if ( ! empty( $meeting_data['attendees'] ) ) {
			$event_data['attendees'] = $this->format_attendees( $meeting_data['attendees'] );
		}

		return $this->api_client->patch(
			"calendars/{$calendar_id}/events/{$meeting_id}?conferenceDataVersion=1",
			$event_data
		);
	}

	/**
	 * Delete a Google Meet meeting.
	 *
	 * @since 1.0.0
	 *
	 * @param string $meeting_id The meeting ID.
	 *
	 * @return array The API response.
	 */
	public function delete_meeting( $meeting_id ) {
		$calendar_id = 'primary';
		return $this->api_client->delete( "calendars/{$calendar_id}/events/{$meeting_id}" );
	}

	/**
	 * Get meeting details.
	 *
	 * @since 1.0.0
	 *
	 * @param string $meeting_id The meeting ID.
	 *
	 * @return array The API response.
	 */
	public function get_meeting( $meeting_id ) {
		$calendar_id = 'primary';
		return $this->api_client->get( "calendars/{$calendar_id}/events/{$meeting_id}" );
	}

	/**
	 * Format datetime for Google Calendar API.
	 *
	 * @since 1.0.0
	 *
	 * @param string $datetime The datetime string.
	 * @param string $timezone The timezone.
	 *
	 * @return string The formatted datetime.
	 */
	private function format_datetime( $datetime, $timezone ) {
		$dt = new \DateTime( $datetime, new \DateTimeZone( $timezone ) );
		return $dt->format( \DateTime::RFC3339 );
	}

	/**
	 * Calculate end time based on start time and duration.
	 *
	 * @since 1.0.0
	 *
	 * @param string $datetime The start datetime.
	 * @param int    $duration The duration in minutes.
	 * @param string $timezone The timezone.
	 *
	 * @return string The end datetime.
	 */
	private function calculate_end_time( $datetime, $duration, $timezone ) {
		$dt = new \DateTime( $datetime, new \DateTimeZone( $timezone ) );
		$dt->modify( "+{$duration} minutes" );
		return $dt->format( \DateTime::RFC3339 );
	}

	/**
	 * Format attendees for Google Calendar API.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attendees The attendees array.
	 *
	 * @return array The formatted attendees.
	 */
	private function format_attendees( $attendees ) {
		$formatted = array();
		foreach ( $attendees as $email ) {
			$formatted[] = array( 'email' => $email );
		}
		return $formatted;
	}
}
