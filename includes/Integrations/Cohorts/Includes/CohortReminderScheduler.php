<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Integrations\Cohorts\Includes;

/**
 * Cohort Reminder Scheduler
 * This class handles the reminder for cohort courses
 *
 * @package OhMyLMS\Integrations\Cohorts\Includes
 * @since 1.0.0
 * @author WPFunnels Team
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Plugin cohort reminder scheduler implementation.
 */
class CohortReminderScheduler {
	const ACTION_HOOK = 'ohmylms_send_cohort_start_reminders';

	/**
	 * Init for Cohort reminder scheduler.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'schedule_reminder' ) );
		add_action( self::ACTION_HOOK, array( __CLASS__, 'handle_reminder' ) );
	}

	/**
	 * Schedule reminder for Cohort reminder scheduler.
	 */
	public static function schedule_reminder() {
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}
		if ( ! as_has_scheduled_action( self::ACTION_HOOK ) ) {
			as_schedule_recurring_action( time(), HOUR_IN_SECONDS, self::ACTION_HOOK );
		}
	}


	/**
	 * Handle reminder
	 *
	 * @return void
	 */
	public static function handle_reminder() {
		global $wpdb;

		$now    = current_time( 'timestamp' );
		$target = $now + DAY_IN_SECONDS;

		$args = array(
			'post_type'      => 'ohmylms-course',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => '_type',
					'value' => 'cohort-based',
				),
			),
			'fields'         => 'ids',
		);

		$cohort_course_ids = get_posts( $args );
		if ( empty( $cohort_course_ids ) ) {
			return;
		}
		$cohort_placeholders = implode( ',', array_fill( 0, count( $cohort_course_ids ), '%d' ) );
		$sql                 = $wpdb->prepare(
			"
            SELECT * FROM {$wpdb->prefix}ohmylms_cohorts
                WHERE course_id IN ($cohort_placeholders)
                AND start_date > %s
                AND start_date <= %s
            ",
			...array_merge(
				$cohort_course_ids,
				array(
					date( 'Y-m-d H:i:s', $now ),
					date( 'Y-m-d H:i:s', $target ),
				)
			)
		);
		$cohorts             = $wpdb->get_results( $sql );

		if ( empty( $cohorts ) ) {
			return;
		}

		foreach ( $cohorts as $cohort ) {
			$course_id = intval( $cohort->course_id );
			$students  = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT user_id FROM %i
                     WHERE course_id = %d',
					$wpdb->prefix . 'ohmylms_user_enrollment',
					$course_id
				)
			);
			foreach ( $students as $student ) {
				$user_id = intval( $student->user_id );
				if ( ! self::has_sent_email( $user_id, $cohort->id ) ) {
					self::send_email( $user_id, $course_id, $cohort );
					self::mark_email_sent( $user_id, $cohort->id );
				}
			}
		}
	}


	/**
	 * Check if email was already sent for this user + cohort
	 *
	 * @param mixed $user_id User identifier.
	 * @param mixed $cohort_id Cohort identifier.
	 */
	protected static function has_sent_email( $user_id, $cohort_id ) {
		return get_user_meta( $user_id, '_ohmylms_cohort_reminder_' . $cohort_id, true );
	}

	/**
	 * Mark that email has been sent
	 *
	 * @param mixed $user_id User identifier.
	 * @param mixed $cohort_id Cohort identifier.
	 */
	protected static function mark_email_sent( $user_id, $cohort_id ) {
		update_user_meta( $user_id, '_ohmylms_cohort_reminder_' . $cohort_id, true );
	}

	/**
	 * Send the actual email
	 *
	 * @param mixed $user_id User identifier.
	 * @param mixed $course_id Course identifier.
	 * @param mixed $cohort Cohort.
	 */
	protected static function send_email( $user_id, $course_id, $cohort ) {
		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			return;
		}

		$course_title = get_the_title( $course_id );
		$course_link  = get_permalink( $course_id );
		$start_date   = date_i18n( 'M j, g:i A', strtotime( $cohort->start_date ) );

		$subject = sprintf(
			'Reminder: Your Course "%s" Starts in 24 Hours!',
			$course_title
		);

		$message = sprintf(
			"Hi %s,\n\n" .
			"Just a quick reminder that your course \"%s\" starts in 24 hours!\n\n" .
			"🗓 Start Date: %s\n" .
			"📍 Course Link: %s\n\n" .
			"We're excited to have you on board. Make sure to log in on time and make the most of your learning journey.\n\n" .
			"See you in class!\n\n" .
			'— %s Team',
			$user->display_name,
			$course_title,
			$start_date,
			$course_link,
			get_bloginfo( 'name' )
		);

		wp_mail( $user->user_email, $subject, $message );
	}
}
