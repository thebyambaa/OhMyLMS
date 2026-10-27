<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * DripContent class
 */
namespace OhMyLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


/**
 * Plugin drip content implementation.
 */
class DripContent {

	/**
	 * Initialize drip content functionality
	 */
	public function __construct() {
		add_filter( 'ohmylms_is_lesson_locked', array( $this, 'is_lesson_locked' ), 10, 4 );
		add_filter( 'ohmylms_drip_protection_message', array( $this, 'drip_protection_message' ), 10, 4 );
	}

	/**
	 * Check if a lesson is locked based on drip settings
	 *
	 * @param bool $is_locked Current locked status of the lesson.
	 * @param int  $lesson_id ID of the lesson.
	 * @param int  $course_id ID of the course.
	 * @param int  $current_student_id ID of the current student.
	 * @return bool True if the lesson is locked, false otherwise
	 * @since 1.0.0
	 */
	public function is_lesson_locked( $is_locked, $lesson_id, $course_id, $current_student_id ) {
		$post_type = get_post_type( $lesson_id );
		if ( 'ohmylms-lesson' === $post_type ) {
			$lesson = ohmylms_get_lesson( $lesson_id );
		} elseif ( 'ohmylms-quiz' === $post_type ) {
			$lesson = ohmylms_get_quiz( $lesson_id );
		} elseif ( 'ohmylms-assignment' === $post_type ) {
			$lesson = ohmylms_get_assignment( $lesson_id );
		} elseif ( 'ohmylms-session' === $post_type ) {
			$lesson = ohmylms_get_session( $lesson_id );
		} else {
			return $is_locked; // Unsupported post type, return original status.
		}

		if ( ! $lesson ) {
			return $is_locked;
		}

		// Check if get_drip_settings method exists before calling.
		if ( ! method_exists( $lesson, 'get_drip_settings' ) ) {
			return $is_locked;
		}
		$drip_settings = $lesson->get_drip_settings( 'edit' );
		if ( ! $drip_settings || ! is_array( $drip_settings ) || empty( $drip_settings['enable'] ) ) {
			return $is_locked;
		}

		$timezone = new \DateTimeZone( function_exists( 'ohmylms_timezone_string' ) ? ohmylms_timezone_string() : 'UTC' );
		$now      = new \DateTime( 'now', $timezone );
		$type     = $drip_settings['type'] ?? '';

		if ( 'cohort-start' === $type || 'cohort-from-x-days' === $type ) {
			$course = ohmylms_get_course( $course_id );
			if ( ! $course ) {
				return $is_locked;
			}
			$cohorts = $course->get_cohort();
			$cohort  = is_array( $cohorts ) && count( $cohorts ) > 0 ? $cohorts[0] : null;
			if ( ! $cohort || empty( $cohort['start_date'] ) ) {
				return $is_locked;
			}
			// Handle both 'Y-m-d\\TH:i:s' and 'Y-m-d' formats.
			$start_date = \DateTime::createFromFormat( 'Y-m-d\TH:i:s', $cohort['start_date'], $timezone );

			if ( ! $start_date ) {
				return $is_locked;
			}
			if ( 'cohort-from-x-days' === $type ) {
				$days = intval( $drip_settings['days'] ?? 0 );
				$start_date->modify( "+{$days} days" );
			}

			return $now < $start_date;
		}

		if ( 'specific-date' === $type ) {
			$date = $drip_settings['date'] ?? '';
			$time = $drip_settings['time'] ?? '00:00:00';
			if ( ! $date ) {
				return $is_locked;
			}
			$unlock_date = \DateTime::createFromFormat( 'Y-m-d H:i:s', "$date $time", $timezone );
			if ( ! $unlock_date ) {
				$unlock_date = \DateTime::createFromFormat( 'Y-m-d', $date, $timezone );
			}
			if ( ! $unlock_date ) {
				return $is_locked;
			}
			return $now < $unlock_date;
		}

		if ( 'enrollment-from-x-days' === $type ) {
			global $wpdb;
			$enrollment_date = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT start_date FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE user_id = %d AND course_id = %d AND status = %s",
					$current_student_id,
					$course_id,
					'enrolled'
				)
			);
			if ( ! $enrollment_date ) {
				return $is_locked;
			}
			$enrollment_dt = \DateTime::createFromFormat( 'Y-m-d H:i:s', $enrollment_date, $timezone );
			if ( ! $enrollment_dt ) {
				$enrollment_dt = \DateTime::createFromFormat( 'Y-m-d', $enrollment_date, $timezone );
			}
			if ( ! $enrollment_dt ) {
				return $is_locked;
			}
			$days = intval( $drip_settings['days'] ?? 0 );
			$enrollment_dt->modify( "+{$days} days" );
			return $now < $enrollment_dt;
		}

		return $is_locked;
	}


	/**
	 * Get the unlock date for a lesson based on drip settings
	 *
	 * @param string $unlock_date Current unlock date of the lesson.
	 * @param int    $lesson_id ID of the lesson.
	 * @param int    $course_id ID of the course.
	 * @param int    $current_student_id ID of the current student.
	 * @return string Formatted unlock date or the original unlock date if no drip settings apply
	 * @since 1.0.0
	 */
	public function get_lesson_unlock_date( $unlock_date, $lesson_id, $course_id, $current_student_id ) {
		$lesson = ohmylms_get_lesson( $lesson_id );
		if ( ! $lesson ) {
			return $unlock_date;
		}
		$drip_settings = $lesson->get_drip_settings( 'edit' );
		if ( ! $drip_settings || ! is_array( $drip_settings ) || empty( $drip_settings['enable'] ) ) {
			return $unlock_date;
		}

		$timezone = new \DateTimeZone( function_exists( 'ohmylms_timezone_string' ) ? ohmylms_timezone_string() : 'UTC' );
		$type     = $drip_settings['type'] ?? '';

		if ( 'cohort-start' === $type || 'cohort-from-x-days' === $type ) {
			$course = ohmylms_get_course( $course_id );
			if ( ! $course ) {
				return $unlock_date;
			}
			$cohorts = $course->get_cohort();
			$cohort  = is_array( $cohorts ) && count( $cohorts ) > 0 ? $cohorts[0] : null;
			if ( ! $cohort || empty( $cohort['start_date'] ) ) {
				return $unlock_date;
			}
			$start_date = \DateTime::createFromFormat( 'Y-m-d\TH:i:s', $cohort['start_date'], $timezone );
			if ( ! $start_date ) {
				return $unlock_date;
			}
			if ( 'cohort-from-x-days' === $type ) {
				$days = intval( $drip_settings['days'] ?? 0 );
				$start_date->modify( "+{$days} days" );
			}
			return $start_date->format( 'Y-m-d' );
		}

		if ( 'specific-date' === $type ) {
			$date = $drip_settings['date'] ?? '';
			$time = $drip_settings['time'] ?? '00:00:00';
			if ( ! $date ) {
				return $unlock_date;
			}
			$unlock_date_obj = \DateTime::createFromFormat( 'Y-m-d H:i:s', "$date $time", $timezone );
			if ( ! $unlock_date_obj ) {
				$unlock_date_obj = \DateTime::createFromFormat( 'Y-m-d', $date, $timezone );
			}
			if ( ! $unlock_date_obj ) {
				return $unlock_date;
			}
			return $unlock_date_obj->format( 'Y-m-d' );
		}

		return $unlock_date;
	}

	/**
	 * Generate a drip protection message for content
	 *
	 * @param string $output Current output message.
	 * @param int    $content_id ID of the content (lesson, quiz, assignment).
	 * @param string $post_type Type of the content (lesson, quiz, assignment).
	 * @param int    $student_id ID of the student.
	 * @return string Modified output message with drip protection information
	 * @since 1.0.0
	 */
	public function drip_protection_message( $output, $content_id, $post_type, $student_id ) {
		$is_locked = $this->is_lesson_locked( true, $content_id, ohmylms_get_course_by_content_id( $content_id ), $student_id );
		if ( ! $is_locked ) {
			return $output; // No drip protection, return original output.
		}
		$content      = null;
		$content_type = '';
		if ( 'ohmylms-lesson' === $post_type ) {
			$content      = ohmylms_get_lesson( $content_id );
			$content_type = 'lesson';
		} elseif ( 'ohmylms-quiz' === $post_type ) {
			$content      = ohmylms_get_quiz( $content_id );
			$content_type = 'quiz';
		} elseif ( 'ohmylms-assignment' === $post_type ) {
			$content      = ohmylms_get_assignment( $content_id );
			$content_type = 'assignment';
		}
		if ( ! $content ) {
			return $output;
		}
		if ( ! method_exists( $content, 'get_drip_settings' ) ) {
			return $output;
		}
		$drip_settings = $content->get_drip_settings( 'edit' );
		if ( ! $drip_settings || ! is_array( $drip_settings ) || empty( $drip_settings['enable'] ) ) {
			return $output;
		}
		$course_id       = ohmylms_get_course_by_content_id( $content_id );
		$timezone        = new \DateTimeZone( function_exists( 'ohmylms_timezone_string' ) ? ohmylms_timezone_string() : 'UTC' );
		$type            = $drip_settings['type'] ?? '';
		$unlock_datetime = null;
		if ( 'cohort-start' === $type || 'cohort-from-x-days' === $type ) {
			$course = ohmylms_get_course( $course_id );
			if ( $course ) {
				$cohorts = $course->get_cohort();
				$cohort  = is_array( $cohorts ) && count( $cohorts ) > 0 ? $cohorts[0] : null;
				if ( $cohort && ! empty( $cohort['start_date'] ) ) {
					$start_date = \DateTime::createFromFormat( 'Y-m-d\TH:i:s', $cohort['start_date'], $timezone );
					if ( $start_date ) {
						if ( 'cohort-from-x-days' === $type ) {
							$days = intval( $drip_settings['days'] ?? 0 );
							$start_date->modify( "+{$days} days" );
						}
						$unlock_datetime = $start_date;
					}
				}
			}
		} elseif ( 'specific-date' === $type ) {
			$date = $drip_settings['date'] ?? '';
			$time = $drip_settings['time'] ?? '00:00:00';
			if ( $date ) {
				$unlock_date = \DateTime::createFromFormat( 'Y-m-d H:i:s', "$date $time", $timezone );
				if ( ! $unlock_date ) {
					$unlock_date = \DateTime::createFromFormat( 'Y-m-d', $date, $timezone );
				}
				if ( $unlock_date ) {
					$unlock_datetime = $unlock_date;
				}
			}
		} elseif ( 'enrollment-from-x-days' === $type ) {
			global $wpdb;
			$enrollment_date = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT start_date FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE user_id = %d AND course_id = %d AND status = %s",
					$student_id,
					$course_id,
					'enrolled'
				)
			);
			if ( $enrollment_date ) {
				$enrollment_dt = \DateTime::createFromFormat( 'Y-m-d H:i:s', $enrollment_date, $timezone );
				if ( ! $enrollment_dt ) {
					$enrollment_dt = \DateTime::createFromFormat( 'Y-m-d', $enrollment_date, $timezone );
				}
				if ( $enrollment_dt ) {
					$days = intval( $drip_settings['days'] ?? 0 );
					$enrollment_dt->modify( "+{$days} days" );
					$unlock_datetime = $enrollment_dt;
				}
			}
		}

		if ( $unlock_datetime ) {
			// Use WordPress date and time format.
			$date_format = get_option( 'date_format', 'F j, Y' );
			$time_format = get_option( 'time_format', 'g:i a' );
			$formatted   = $unlock_datetime->format( "$date_format \\a\\t $time_format" );
			return sprintf(
				\__( 'This %1$s will be available on %2$s.', 'ohmylms' ),
				$content_type,
				$formatted
			);
		}
		return $output;
	}
}
