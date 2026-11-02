<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Cohort Reminder Scheduler
 * This class handles the reminder for cohort courses
 *
 * @package OhMyLMS\Integrations\Cohorts\Includes
 * @since 1.0.0
 * @author WPFunnels Team
 */

namespace OhMyLMS\Integrations\Cohorts\Includes;

use OhMyLMS\I18n\RecipientLocale;

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
		// $now and $target are site-local timestamps, so gmdate() formats them as site-local times.
		$cohorts = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE course_id IN (' . implode( ',', array_fill( 0, count( $cohort_course_ids ), '%d' ) ) . ') AND start_date > %s AND start_date <= %s',
				...array_merge(
					array( $wpdb->prefix . 'ohmylms_cohorts' ),
					$cohort_course_ids,
					array(
						gmdate( 'Y-m-d H:i:s', $now ),
						gmdate( 'Y-m-d H:i:s', $target ),
					)
				)
			)
		);

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

		RecipientLocale::run(
			$user,
			static function () use ( $user, $course_id, $cohort ) {
				self::compose_and_send( $user, $course_id, $cohort );
			}
		);
	}

	/**
	 * Compose and send the email, in the user's language.
	 *
	 * @param \WP_User $user      User.
	 * @param mixed    $course_id Course identifier.
	 * @param mixed    $cohort    Cohort.
	 */
	protected static function compose_and_send( $user, $course_id, $cohort ) {
		$course_title = get_the_title( $course_id );
		$course_link  = get_permalink( $course_id );
		$start_date   = date_i18n( 'M j, g:i A', strtotime( $cohort->start_date ) );

		$subject = sprintf(
			/* translators: %s: course title. */
			__( 'Reminder: Your Course "%s" Starts in 24 Hours!', 'ohmylms' ),
			$course_title
		);

		$message = sprintf(
			/* translators: %s: recipient's display name. */
			__( 'Hi %s,', 'ohmylms' ),
			$user->display_name
		) . "\n\n" . sprintf(
			/* translators: %s: course title. */
			__( 'Just a quick reminder that your course "%s" starts in 24 hours!', 'ohmylms' ),
			$course_title
		) . "\n\n🗓 " . sprintf(
			/* translators: %s: date and time the cohort starts. */
			__( 'Start Date: %s', 'ohmylms' ),
			$start_date
		) . "\n📍 " . sprintf(
			/* translators: %s: course URL. */
			__( 'Course Link: %s', 'ohmylms' ),
			$course_link
		) . "\n\n" . __( 'We\'re excited to have you on board. Make sure to log in on time and make the most of your learning journey.', 'ohmylms' ) . "\n\n" . __( 'See you in class!', 'ohmylms' ) . "\n\n" . sprintf(
			/* translators: %s: site name. */
			__( '— %s Team', 'ohmylms' ),
			get_bloginfo( 'name' )
		);

		wp_mail( $user->user_email, $subject, $message );
	}
}
