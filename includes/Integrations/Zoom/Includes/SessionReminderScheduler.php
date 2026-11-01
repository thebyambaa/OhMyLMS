<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Zoom Session Reminder Handler
 *
 * This class handles the reminder for zoom sessions
 *
 * @package OhMyLMS\Integrations\Zoom\Includes
 * @since 1.0.0
 * @author WPFunnels Team
 */

namespace OhMyLMS\Integrations\Zoom\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use OhMyLMS\I18n\RecipientLocale;
use WP_Query;

/**
 * Plugin session reminder scheduler implementation.
 */
class SessionReminderScheduler {
	const ACTION_HOOK = 'ohmylms_send_zoom_session_reminders';

	/**
	 * Init for Session reminder scheduler.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'schedule_reminder' ) );
		add_action( self::ACTION_HOOK, array( __CLASS__, 'handle_reminder' ) );
	}

	/**
	 * Schedule the reminder for zoom sessions
	 *
	 * @return void
	 * @since 1.0.0
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
	 * Handle the reminder for zoom sessions
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function handle_reminder() {
		global $wpdb;
		$now    = current_time( 'timestamp' );
		$target = $now + DAY_IN_SECONDS;
		$args   = array(
			'post_type'      => 'ohmylms-session',
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'     => '_platform',
					'value'   => 'zoom',
					'compare' => '=',
				),
				array(
					'key'     => '_start_date',
					'compare' => 'EXISTS',
				),
			),
			'posts_per_page' => -1,
		);

		$query = new WP_Query( $args );
		if ( ! $query->have_posts() ) {
			return;
		}
		foreach ( $query->posts as $post ) {
			$raw_start       = get_post_meta( $post->ID, '_start_date', true );
			$start_timestamp = strtotime( str_replace( 'T', ' ', $raw_start ) );
			$meeting_data    = get_post_meta( $post->ID, '_zoom_meeting_data', true );
			$meeting_data    = json_decode( $meeting_data, true );
			$course_id       = ohmylms_get_course_by_content_id( $post->ID );
			$zoom_link       = isset( $meeting_data['join_url'] ) ? $meeting_data['join_url'] : '';
			if ( ! $course_id ) {
				continue;
			}
			if ( $start_timestamp > $now && $start_timestamp <= $target ) {
				$students = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT user_id FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE course_id = %d AND status = 'enrolled'",
						$course_id
					)
				);

				foreach ( $students as $student ) {
					$user_id = intval( $student->user_id );
					if ( ! self::has_sent_email( $user_id, $post->ID ) ) {
						self::send_email(
							$user_id,
							$course_id,
							array(
								'start_time'   => $start_timestamp,
								'zoom_link'    => $zoom_link,
								'session_id'   => $post->ID,
								'course_title' => get_the_title( $course_id ),
								'course_link'  => get_permalink( $course_id ),
							)
						);
						self::mark_email_sent( $user_id, $post->ID );
					}
				}
			}
		}

		wp_reset_postdata();
	}

	/**
	 * Check if the user has already received the email
	 *
	 * @param int $user_id User identifier.
	 * @param int $session_id Session identifier.
	 * @return bool
	 * @since 1.0.0
	 */
	protected static function has_sent_email( $user_id, $session_id ) {
		return get_user_meta( $user_id, '_ohmylms_zoom_reminder_' . $session_id, true );
	}

	/**
	 * Mark the email as sent
	 *
	 * @param int $user_id User identifier.
	 * @param int $session_id Session identifier.
	 * @return void
	 * @since 1.0.0
	 */
	protected static function mark_email_sent( $user_id, $session_id ) {
		update_user_meta( $user_id, '_ohmylms_zoom_reminder_' . $session_id, true );
	}

	/**
	 * Send the email to the user
	 *
	 * @param int   $user_id User identifier.
	 * @param int   $course_id Course identifier.
	 * @param array $session_data Session data.
	 * @return void
	 * @since 1.0.0
	 */
	protected static function send_email( $user_id, $course_id, $session_data ) {
		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			return;
		}

		RecipientLocale::run(
			$user,
			static function () use ( $user, $session_data ) {
				self::compose_and_send( $user, $session_data );
			}
		);
	}

	/**
	 * Compose and send the email, in the user's language.
	 *
	 * @param \WP_User $user         User.
	 * @param array    $session_data Session data.
	 * @return void
	 */
	protected static function compose_and_send( $user, $session_data ) {
		$start_time   = date_i18n( 'M j, g:i A', is_numeric( $session_data['start_time'] ) ? (int) $session_data['start_time'] : strtotime( $session_data['start_time'] ) );
		$zoom_link    = $session_data['zoom_link'] ? $session_data['zoom_link'] : '#';
		$course_title = $session_data['course_title'];
		$course_link  = $session_data['course_link'];

		$subject = sprintf(
			/* translators: %s: course title. */
			__( 'Reminder: Zoom Session for "%s" Starts in 24 Hours!', 'ohmylms' ),
			$course_title
		);

		$message = sprintf(
			/* translators: %s: recipient's display name. */
			__( 'Hi %s,', 'ohmylms' ),
			$user->display_name
		) . "\n\n" . sprintf(
			/* translators: %s: course title. */
			__( 'Just a heads-up that your Zoom session for "%s" starts in 24 hours!', 'ohmylms' ),
			$course_title
		) . "\n\n🗓 " . sprintf(
			/* translators: %s: date and time the session starts. */
			__( 'Start Time: %s', 'ohmylms' ),
			$start_time
		) . "\n🔗 " . sprintf(
			/* translators: %s: Zoom meeting URL. */
			__( 'Zoom Link: %s', 'ohmylms' ),
			$zoom_link
		) . "\n📘 " . sprintf(
			/* translators: %s: course URL. */
			__( 'Course Page: %s', 'ohmylms' ),
			$course_link
		) . "\n\n" . __( 'Make sure to join on time. Looking forward to your participation!', 'ohmylms' ) . "\n\n" . sprintf(
			/* translators: %s: site name. */
			__( '— %s Team', 'ohmylms' ),
			get_bloginfo( 'name' )
		);

		wp_mail( $user->user_email, $subject, $message );
	}
}
