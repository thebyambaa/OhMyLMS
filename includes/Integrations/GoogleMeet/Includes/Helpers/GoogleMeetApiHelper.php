<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * GoogleMeetApiHelper class.
 *
 * @package ohmylms-pro
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\GoogleMeet\Includes\Helpers;

/**
 * Class GoogleMeetApiHelper
 *
 * @package OhMyLMS\Integrations\GoogleMeet\Helpers
 * @since 1.0.0
 */
class GoogleMeetApiHelper {
	/**
	 * Format meeting data for display.
	 *
	 * @since 1.0.0
	 *
	 * @param array $meeting_data The raw meeting data from Google Calendar API.
	 *
	 * @return array The formatted meeting data.
	 */
	public static function format_meeting_data( $meeting_data ) {
		$formatted = array(
			'id'         => $meeting_data['id'] ?? '',
			'topic'      => $meeting_data['summary'] ?? '',
			'agenda'     => $meeting_data['description'] ?? '',
			'start_time' => $meeting_data['start']['dateTime'] ?? '',
			'end_time'   => $meeting_data['end']['dateTime'] ?? '',
			'timezone'   => $meeting_data['start']['timeZone'] ?? 'UTC',
			'meet_link'  => '',
			'html_link'  => $meeting_data['htmlLink'] ?? '',
			'status'     => $meeting_data['status'] ?? '',
		);

		// Extract Google Meet link.
		if ( isset( $meeting_data['conferenceData']['entryPoints'] ) ) {
			foreach ( $meeting_data['conferenceData']['entryPoints'] as $entry ) {
				if ( 'video' === $entry['entryPointType'] ) {
					$formatted['meet_link'] = $entry['uri'];
					break;
				}
			}
		}

		// Calculate duration in minutes.
		if ( $formatted['start_time'] && $formatted['end_time'] ) {
			$start                 = new \DateTime( $formatted['start_time'] );
			$end                   = new \DateTime( $formatted['end_time'] );
			$interval              = $start->diff( $end );
			$formatted['duration'] = $interval->h * 60 + $interval->i;
		}

		return $formatted;
	}

	/**
	 * Validate meeting data.
	 *
	 * @since 1.0.0
	 *
	 * @param array $meeting_data The meeting data to validate.
	 *
	 * @return array|true Array of errors or true if valid.
	 */
	public static function validate_meeting_data( $meeting_data ) {
		$errors = array();

		if ( empty( $meeting_data['topic'] ) ) {
			$errors['topic'] = __( 'Meeting topic is required', 'ohmylms' );
		}

		if ( empty( $meeting_data['agenda'] ) ) {
			$errors['agenda'] = __( 'Meeting agenda is required', 'ohmylms' );
		}

		if ( empty( $meeting_data['date'] ) ) {
			$errors['date'] = __( 'Meeting date is required', 'ohmylms' );
		}

		if ( empty( $meeting_data['duration'] ) || $meeting_data['duration'] <= 0 ) {
			$errors['duration'] = __( 'Valid meeting duration is required', 'ohmylms' );
		}

		if ( empty( $meeting_data['timezone'] ) ) {
			$errors['timezone'] = __( 'Timezone is required', 'ohmylms' );
		}

		return empty( $errors ) ? true : $errors;
	}

	/**
	 * Get available timezones.
	 *
	 * @since 1.0.0
	 *
	 * @return array The available timezones.
	 */
	public static function get_timezones() {
		return \timezone_identifiers_list();
	}

	/**
	 * Convert meeting time to local timezone.
	 *
	 * @since 1.0.0
	 *
	 * @param string $datetime       The datetime string.
	 * @param string $from_timezone  The source timezone.
	 * @param string $to_timezone    The target timezone.
	 *
	 * @return string The converted datetime.
	 */
	public static function convert_timezone( $datetime, $from_timezone, $to_timezone ) {
		try {
			$dt = new \DateTime( $datetime, new \DateTimeZone( $from_timezone ) );
			$dt->setTimezone( new \DateTimeZone( $to_timezone ) );
			return $dt->format( 'Y-m-d H:i:s' );
		} catch ( \Exception $e ) {
			return $datetime;
		}
	}

	/**
	 * Check if meeting is upcoming.
	 *
	 * @since 1.0.0
	 *
	 * @param string $start_time The meeting start time.
	 *
	 * @return bool True if upcoming, false otherwise.
	 */
	public static function is_upcoming( $start_time ) {
		$now          = new \DateTime();
		$meeting_time = new \DateTime( $start_time );
		return $meeting_time > $now;
	}

	/**
	 * Check if meeting is in progress.
	 *
	 * @since 1.0.0
	 *
	 * @param string $start_time The meeting start time.
	 * @param string $end_time   The meeting end time.
	 *
	 * @return bool True if in progress, false otherwise.
	 */
	public static function is_in_progress( $start_time, $end_time ) {
		$now   = new \DateTime();
		$start = new \DateTime( $start_time );
		$end   = new \DateTime( $end_time );
		return $now >= $start && $now <= $end;
	}

	/**
	 * Get meeting status.
	 *
	 * @since 1.0.0
	 *
	 * @param string $start_time The meeting start time.
	 * @param string $end_time   The meeting end time.
	 *
	 * @return string The meeting status (upcoming, live, ended).
	 */
	public static function get_meeting_status( $start_time, $end_time ) {
		if ( self::is_in_progress( $start_time, $end_time ) ) {
			return 'live';
		} elseif ( self::is_upcoming( $start_time ) ) {
			return 'upcoming';
		} else {
			return 'ended';
		}
	}
}
