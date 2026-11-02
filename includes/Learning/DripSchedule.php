<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * When drip-scheduled course content opens for a learner.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Learning;

defined( 'ABSPATH' ) || exit;

/**
 * One reading of the stored `_drip_settings` (`{enable, type, days, date, time}`) for lessons, quizzes and
 * assignments. Dates are site time. Content whose release cannot be worked out (no enrolment, no cohort,
 * no date) is open, as it always was.
 */
final class DripSchedule {

	const TYPES = array( 'enrollment-from-x-days', 'specific-date', 'cohort-start', 'cohort-from-x-days' );

	/**
	 * Site time zone.
	 *
	 * @return \DateTimeZone
	 */
	public static function timezone() {
		return new \DateTimeZone( function_exists( 'ohmylms_timezone_string' ) ? ohmylms_timezone_string() : 'UTC' );
	}

	/**
	 * Release date (`YYYY-MM-DD`) and time (`HH:MM:SS`) of stored settings, including the legacy editor's
	 * `YYYY-MM-DDTHH:mm:ss` values in either field.
	 *
	 * @param array $drip Stored settings.
	 * @return array{0:string,1:string} Date (or empty) and time.
	 */
	public static function date_parts( array $drip ) {
		$date = preg_match( '/^(\d{4}-\d{2}-\d{2})/', (string) ( $drip['date'] ?? '' ), $match ) ? $match[1] : '';
		$time = (string) ( $drip['time'] ?? '' );
		if ( preg_match( '/T(\d{2}:\d{2})(?::(\d{2}))?/', $time, $found ) || preg_match( '/^(\d{2}:\d{2})(?::(\d{2}))?/', $time, $found ) ) {
			$time = $found[1] . ':' . ( isset( $found[2] ) && '' !== $found[2] ? $found[2] : '00' );
		} else {
			$time = '00:00:00';
		}
		return array( $date, $time );
	}

	/**
	 * When the content opens for a learner.
	 *
	 * @param mixed $drip      Stored drip settings.
	 * @param int   $course_id Course the learner takes the content in.
	 * @param int   $user_id   Learner.
	 * @return \DateTimeImmutable|null Null when the content is not scheduled or the date is unknown.
	 */
	public static function opens_at( $drip, $course_id, $user_id ) {
		if ( ! is_array( $drip ) || empty( $drip['enable'] ) ) {
			return null;
		}
		$zone = self::timezone();
		$type = (string) ( $drip['type'] ?? '' );
		$days = max( 0, (int) ( $drip['days'] ?? 0 ) );

		if ( 'cohort-start' === $type || 'cohort-from-x-days' === $type ) {
			$course  = $course_id ? ohmylms_get_course( $course_id ) : null;
			$cohorts = $course && method_exists( $course, 'get_cohort' ) ? $course->get_cohort() : array();
			$cohort  = is_array( $cohorts ) && $cohorts ? reset( $cohorts ) : null;
			if ( ! is_array( $cohort ) || empty( $cohort['start_date'] ) ) {
				return null;
			}
			$start = \DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i:s', (string) $cohort['start_date'], $zone );
			if ( ! $start ) {
				$start = \DateTimeImmutable::createFromFormat( '!Y-m-d', substr( (string) $cohort['start_date'], 0, 10 ), $zone );
			}
			if ( ! $start ) {
				return null;
			}
			return 'cohort-from-x-days' === $type ? $start->modify( "+{$days} days" ) : $start;
		}

		if ( 'specific-date' === $type ) {
			list( $date, $time ) = self::date_parts( $drip );
			if ( '' === $date ) {
				return null;
			}
			$at = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', "$date $time", $zone );
			return $at ? $at : null;
		}

		if ( 'enrollment-from-x-days' === $type ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh per-learner read; the gate must reflect the current enrolment.
			$enrolled = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT start_date FROM %i WHERE user_id = %d AND course_id = %d AND status = %s ORDER BY id ASC LIMIT 1',
					$wpdb->prefix . 'ohmylms_user_enrollment',
					(int) $user_id,
					(int) $course_id,
					'enrolled'
				)
			);
			if ( ! $enrolled ) {
				return null;
			}
			$start = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', (string) $enrolled, $zone );
			if ( ! $start ) {
				$start = \DateTimeImmutable::createFromFormat( '!Y-m-d', substr( (string) $enrolled, 0, 10 ), $zone );
			}
			return $start ? $start->modify( "+{$days} days" ) : null;
		}

		return null;
	}

	/**
	 * Whether the content is still closed for the learner.
	 *
	 * @param mixed                   $drip      Stored drip settings.
	 * @param int                     $course_id Course ID.
	 * @param int                     $user_id   Learner.
	 * @param \DateTimeInterface|null $now       Current time (tests).
	 * @return bool
	 */
	public static function is_locked( $drip, $course_id, $user_id, $now = null ) {
		$at = self::opens_at( $drip, $course_id, $user_id );
		return $at && ( $now ? $now : new \DateTimeImmutable( 'now', self::timezone() ) ) < $at;
	}

	/**
	 * Release time as the site displays dates and times.
	 *
	 * @param \DateTimeInterface $at Release time.
	 * @return string
	 */
	public static function format( \DateTimeInterface $at ) {
		/* translators: 1: date, 2: time. */
		return sprintf( __( '%1$s at %2$s', 'ohmylms' ), wp_date( get_option( 'date_format', 'F j, Y' ), $at->getTimestamp() ), wp_date( get_option( 'time_format', 'g:i a' ), $at->getTimestamp() ) );
	}
}
