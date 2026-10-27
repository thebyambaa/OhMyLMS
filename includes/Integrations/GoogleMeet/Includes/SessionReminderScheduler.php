<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * SessionReminderScheduler class.
 *
 * @package ohmylms-pro
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\GoogleMeet\Includes;

/**
 * Class SessionReminderScheduler
 *
 * @package OhMyLMS\Integrations\GoogleMeet
 * @since 1.0.0
 */
class SessionReminderScheduler {
	/**
	 * Initialize the scheduler.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function init() {
		// Schedule reminders for upcoming sessions.
		\add_action( 'ohmylms_googlemeet_send_reminder', array( __CLASS__, 'send_session_reminder' ) );

		// Check for upcoming sessions every hour.
		if ( ! \wp_next_scheduled( 'ohmylms_googlemeet_check_sessions' ) ) {
			\wp_schedule_event( time(), 'hourly', 'ohmylms_googlemeet_check_sessions' );
		}

		\add_action( 'ohmylms_googlemeet_check_sessions', array( __CLASS__, 'check_upcoming_sessions' ) );
	}

	/**
	 * Check for upcoming sessions and schedule reminders.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function check_upcoming_sessions() {
		// Get all GoogleMeet sessions starting in the next 24 hours.
		$args = array(
			'post_type'      => 'lesson',
			'posts_per_page' => -1,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => '_lesson_platform',
					'value'   => 'googlemeet',
					'compare' => '=',
				),
				array(
					'key'     => '_session_start_time',
					'value'   => array( time(), time() + DAY_IN_SECONDS ),
					'compare' => 'BETWEEN',
					'type'    => 'NUMERIC',
				),
			),
		);

		$sessions = \get_posts( $args );

		foreach ( $sessions as $session ) {
			$start_time    = \get_post_meta( $session->ID, '_session_start_time', true );
			$reminder_sent = \get_post_meta( $session->ID, '_googlemeet_reminder_sent', true );

			// Send reminder 1 hour before session.
			$reminder_time = $start_time - HOUR_IN_SECONDS;

			if ( ! $reminder_sent && time() >= $reminder_time ) {
				self::send_session_reminder( $session->ID );
				\update_post_meta( $session->ID, '_googlemeet_reminder_sent', true );
			}
		}
	}

	/**
	 * Send session reminder to enrolled students.
	 *
	 * @since 1.0.0
	 *
	 * @param int $session_id The session ID.
	 *
	 * @return void
	 */
	public static function send_session_reminder( $session_id ) {
		$course_id = \get_post_meta( $session_id, '_lesson_course_id', true );

		if ( ! $course_id ) {
			return;
		}

		// Get enrolled students.
		$enrolled_students = self::get_enrolled_students( $course_id );

		if ( empty( $enrolled_students ) ) {
			return;
		}

		$session_title = \get_the_title( $session_id );
		$meet_link     = \get_post_meta( $session_id, '_googlemeet_link', true );
		$start_time    = \get_post_meta( $session_id, '_session_start_time', true );

		foreach ( $enrolled_students as $student_id ) {
			$user = \get_userdata( $student_id );

			if ( ! $user ) {
				continue;
			}

			// Send email reminder.
			$subject = sprintf( /* translators: %s: text value. */
			__( 'Reminder: %s starts in 1 hour', 'ohmylms' ), $session_title );
			$message = sprintf(
				/* translators: %1$s: text value; %2$s: text value; %3$s: text value; %4$s: text value. */
				__(
					'Hi %1$s,

This is a reminder that your Google Meet session "%2$s" will start in 1 hour.

Start Time: %3$s
Join Link: %4$s

See you there!',
					'ohmylms'
				),
				$user->display_name,
				$session_title,
				\wp_date( 'F j, Y g:i A', $start_time ),
				$meet_link
			);

			\wp_mail( $user->user_email, $subject, $message );
		}
	}

	/**
	 * Get enrolled students for a course.
	 *
	 * @since 1.0.0
	 *
	 * @param int $course_id The course ID.
	 *
	 * @return array The student IDs.
	 */
	private static function get_enrolled_students( $course_id ) {
		$args = array(
			'meta_key'   => '_enrolled_course_' . $course_id,
			'meta_value' => '1',
			'fields'     => 'ID',
		);

		return \get_users( $args );
	}
}
