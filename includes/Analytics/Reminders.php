<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Course reminder e-mails.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Analytics;

use OhMyLMS\Student\Directory;
use WP_Error;

/**
 * Sends fixed-template reminder e-mails to students enrolled in a course.
 *
 * Recipients are derived from enrolments, never from the caller; subjects and bodies are fixed plain-text
 * templates. Each student receives at most one reminder per course per interval, and each sender has an
 * hourly quota. See docs/api/students.md ("Course roster").
 *
 * @since 1.0.0
 */
class Reminders {

	/**
	 * Template keys.
	 */
	const TEMPLATES = array( 'start', 'continue', 'complete' );

	/**
	 * Most recipients per request.
	 */
	const MAX_BATCH = 20;

	/**
	 * Default seconds between two reminders of the same student and course.
	 */
	const DEFAULT_INTERVAL = DAY_IN_SECONDS;

	/**
	 * Default reminders one sender may send per hour.
	 */
	const DEFAULT_HOURLY_LIMIT = 50;

	/**
	 * Seconds between two reminders of the same student and course.
	 *
	 * @return int
	 */
	public static function interval() {
		/**
		 * Filters the minimum seconds between two reminders of one student for one course.
		 *
		 * @param int $seconds Seconds, at least one hour.
		 */
		return max( HOUR_IN_SECONDS, (int) apply_filters( 'ohmylms_reminder_interval', self::DEFAULT_INTERVAL ) );
	}

	/**
	 * Reminders one sender may send per hour.
	 *
	 * @return int
	 */
	public static function hourly_limit() {
		/**
		 * Filters how many reminders one administrator may send per hour.
		 *
		 * @param int $limit Reminders, 1-500.
		 */
		return min( 500, max( 1, (int) apply_filters( 'ohmylms_reminder_hourly_limit', self::DEFAULT_HOURLY_LIMIT ) ) );
	}

	/**
	 * Transient key of a sender's quota.
	 *
	 * @param int $sender_id User ID.
	 * @return string
	 */
	private static function quota_key( $sender_id ) {
		return 'ohmylms_reminder_quota_' . absint( $sender_id );
	}

	/**
	 * Reminders the sender may still send in the current hour.
	 *
	 * @param int $sender_id User ID.
	 * @return int
	 */
	public static function remaining( $sender_id ) {
		$quota = get_transient( self::quota_key( $sender_id ) );
		$used  = is_array( $quota ) && isset( $quota['count'], $quota['start'] ) && ( time() - (int) $quota['start'] ) < HOUR_IN_SECONDS ? (int) $quota['count'] : 0;
		return max( 0, self::hourly_limit() - $used );
	}

	/**
	 * Count one sent reminder against the sender's quota.
	 *
	 * @param int $sender_id User ID.
	 * @return void
	 */
	private static function consume( $sender_id ) {
		$quota = get_transient( self::quota_key( $sender_id ) );
		if ( ! is_array( $quota ) || ! isset( $quota['count'], $quota['start'] ) || ( time() - (int) $quota['start'] ) >= HOUR_IN_SECONDS ) {
			$quota = array(
				'start' => time(),
				'count' => 0,
			);
		}
		++$quota['count'];
		set_transient( self::quota_key( $sender_id ), $quota, HOUR_IN_SECONDS );
	}

	/**
	 * Subject and plain-text body of a template.
	 *
	 * @param string $template Template key.
	 * @param string $name     Student display name.
	 * @param string $course   Course title.
	 * @param string $url      Course address.
	 * @return string[] Subject and body.
	 */
	public static function render( $template, $name, $course, $url ) {
		$site = wp_strip_all_tags( html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' ) );
		switch ( $template ) {
			case 'start':
				/* translators: %s: course title. */
				$subject = sprintf( __( 'Start your course: %s', 'ohmylms' ), $course );
				/* translators: 1: student name, 2: course title, 3: course address, 4: site name. */
				$body = sprintf( __( "Hi %1\$s,\n\nYou are enrolled in \"%2\$s\" but have not started yet. You can begin here:\n%3\$s\n\n%4\$s", 'ohmylms' ), $name, $course, $url, $site );
				break;
			case 'complete':
				/* translators: %s: course title. */
				$subject = sprintf( __( 'Finish your course: %s', 'ohmylms' ), $course );
				/* translators: 1: student name, 2: course title, 3: course address, 4: site name. */
				$body = sprintf( __( "Hi %1\$s,\n\nYou have made progress in \"%2\$s\". Complete the remaining lessons to finish the course:\n%3\$s\n\n%4\$s", 'ohmylms' ), $name, $course, $url, $site );
				break;
			default:
				/* translators: %s: course title. */
				$subject = sprintf( __( 'Continue your course: %s', 'ohmylms' ), $course );
				/* translators: 1: student name, 2: course title, 3: course address, 4: site name. */
				$body = sprintf( __( "Hi %1\$s,\n\nThis is a reminder to continue \"%2\$s\". Pick up where you left off:\n%3\$s\n\n%4\$s", 'ohmylms' ), $name, $course, $url, $site );
		}
		return array( sanitize_text_field( $subject ), $body );
	}

	/**
	 * Send a template to students of a course.
	 *
	 * @param int    $course_id Course ID (already validated by the caller).
	 * @param int[]  $user_ids  Requested recipients (at most MAX_BATCH).
	 * @param string $template  Template key.
	 * @param int    $sender_id Sending user.
	 * @return array{sent:int[],skipped:array[],remaining:int}|WP_Error
	 */
	public static function send( $course_id, array $user_ids, $template, $sender_id ) {
		global $wpdb;

		if ( ! in_array( $template, self::TEMPLATES, true ) ) {
			return new WP_Error( 'ohmylms_reminder_invalid_template', __( 'Unknown reminder template.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$course_id = absint( $course_id );
		$user_ids  = array_slice( array_values( array_unique( array_filter( array_map( 'absint', $user_ids ) ) ) ), 0, self::MAX_BATCH );
		if ( ! $user_ids ) {
			return new WP_Error( 'ohmylms_reminder_no_recipients', __( 'Choose at least one student.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		if ( self::remaining( $sender_id ) < 1 ) {
			return new WP_Error( 'ohmylms_reminder_rate_limited', __( 'You have sent the maximum number of reminders for this hour. Try again later.', 'ohmylms' ), array( 'status' => 429 ) );
		}

		$holders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholders are generated from the id count.
		$enrolments = (array) $wpdb->get_results( $wpdb->prepare( "SELECT user_id, status, progress FROM %i WHERE course_id = %d AND user_id IN ({$holders}) ORDER BY id ASC", array_merge( array( $wpdb->prefix . 'ohmylms_user_enrollment', $course_id ), $user_ids ) ), ARRAY_A );
		$last_sent  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT student_id, MAX(created_at) AS sent_at FROM %i WHERE course_id = %d AND status = 'sent' AND student_id IN ({$holders}) GROUP BY student_id", array_merge( array( $wpdb->prefix . 'ohmylms_notifications', $course_id ), $user_ids ) ), ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		$by_user = array();
		foreach ( $enrolments as $row ) {
			if ( ! isset( $by_user[ (int) $row['user_id'] ] ) ) {
				$by_user[ (int) $row['user_id'] ] = $row;
			}
		}
		$recent = array();
		$cutoff = current_time( 'timestamp' ) - self::interval(); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Rows store site-local times.
		foreach ( $last_sent as $row ) {
			if ( strtotime( (string) $row['sent_at'] ) > $cutoff ) {
				$recent[ (int) $row['student_id'] ] = true;
			}
		}

		$course = html_entity_decode( wp_strip_all_tags( get_the_title( $course_id ) ), ENT_QUOTES, 'UTF-8' );
		$url    = (string) get_permalink( $course_id );
		$sent   = array();
		$skip   = array();
		update_meta_cache( 'user', $user_ids );

		foreach ( $user_ids as $user_id ) {
			$enrolment = $by_user[ $user_id ] ?? null;
			$user      = get_userdata( $user_id );
			$reason    = '';
			if ( ! $user || ! $enrolment ) {
				$reason = 'not_enrolled';
			} elseif ( 'enrolled' !== $enrolment['status'] || Directory::is_blocked( $user_id ) ) {
				$reason = 'blocked';
			} elseif ( 'completed' === $enrolment['progress'] ) {
				$reason = 'completed';
			} elseif ( isset( $recent[ $user_id ] ) ) {
				$reason = 'recently_reminded';
			} elseif ( self::remaining( $sender_id ) < 1 ) {
				$reason = 'rate_limited';
			} elseif ( ! is_email( $user->user_email ) ) {
				$reason = 'no_email';
			}
			if ( '' !== $reason ) {
				$skip[] = array(
					'user_id' => $user_id,
					'reason'  => $reason,
				);
				continue;
			}

			$name                   = wp_strip_all_tags( $user->display_name );
			list( $subject, $body ) = self::render( $template, $name, $course, $url );
			self::consume( $sender_id );
			$ok = wp_mail( $user->user_email, $subject, $body );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reminder log table has no API.
			$wpdb->insert(
				$wpdb->prefix . 'ohmylms_notifications',
				array(
					'student_id' => $user_id,
					'course_id'  => $course_id,
					'email'      => $user->user_email,
					'subject'    => $subject,
					'message'    => $template,
					'status'     => $ok ? 'sent' : 'failed',
					'created_at' => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
			);

			if ( ! $ok ) {
				$skip[] = array(
					'user_id' => $user_id,
					'reason'  => 'failed',
				);
				continue;
			}
			$sent[] = $user_id;

			/**
			 * Fires after a course reminder was sent to a student.
			 *
			 * @param int    $user_id   Student ID.
			 * @param int    $course_id Course ID.
			 * @param string $template  Template key.
			 */
			do_action( 'ohmylms_course_reminder_sent', $user_id, $course_id, $template );
		}

		return array(
			'sent'      => $sent,
			'skipped'   => $skip,
			'remaining' => self::remaining( $sender_id ),
		);
	}
}
