<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Calendar policy has no WordPress dependencies and never uses 86400-second days.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Engagement;

/** Calendar policy has no WordPress dependencies and never uses 86400-second days. */
final class StreakCalendar {
	/**
	 * Next for Streak calendar.
	 *
	 * @param mixed $date Date.
	 */
	public static function next( $date ) {
		return ( new \DateTimeImmutable( $date, new \DateTimeZone( 'UTC' ) ) )->modify( '+1 day' )->format( 'Y-m-d' ); }
	/**
	 * Date for Streak calendar.
	 *
	 * @param mixed $utc Utc.
	 * @param mixed $timezone Timezone.
	 */
	public static function date( $utc, $timezone ) {
		return ( new \DateTimeImmutable( $utc, new \DateTimeZone( 'UTC' ) ) )->setTimezone( new \DateTimeZone( $timezone ) )->format( 'Y-m-d' ); }

	/** Close only past dates. Today's pending activity cannot break a streak.
	 *
	 * @param array $state State.
	 * @param mixed $today Today.
	 * @param array $settings Configuration settings.
	 */
	public static function reconcile( array $state, $today, array $settings ) {
		$days             = array();
		$state['freezes'] = $settings['freezes'] ? min( $state['freezes'], $settings['maximum_freezes'] ) : 0;
		if ( ! $settings['freezes'] || $state['freezes'] >= $settings['maximum_freezes'] ) {
			$state['refill'] = 0; }
		if ( ! $state['cursor_date'] ) {
			return array( $state, $days ); }
		for ( $date = self::next( $state['cursor_date'] ); $date < $today; $date = self::next( $date ) ) {
			$status = 'missed';
			if ( $state['current_streak'] > 0 && $state['freezes'] > 0 ) {
				--$state['freezes'];
				$status = 'protected'; } else {
				$state['current_streak'] = 0; }
				$state['cursor_date'] = $date;
				$days[ $date ]        = $status;
		}
		return array( $state, $days );
	}

	/**
	 * Practice for Streak calendar.
	 *
	 * @param array $state State.
	 * @param mixed $date Date.
	 * @param array $settings Configuration settings.
	 */
	public static function practice( array $state, $date, array $settings ) {
		if ( $state['cursor_date'] && $date <= $state['cursor_date'] ) {
			return array( $state, false ); }
		++$state['current_streak'];
		$state['longest_streak'] = max( $state['longest_streak'], $state['current_streak'] );
		$state['cursor_date']    = $date;
		if ( $settings['freezes'] && $state['freezes'] < $settings['maximum_freezes'] ) {
			++$state['refill'];
			if ( $state['refill'] >= $settings['refill_days'] ) {
				++$state['freezes'];
				$state['refill'] = 0; }
		} else {
			$state['refill'] = 0; }
		return array( $state, true );
	}

	/**
	 * Meaningful for Streak calendar.
	 *
	 * @param mixed $answer Answer.
	 */
	public static function meaningful( $answer ) {
		if ( is_array( $answer ) ) {
			foreach ( $answer as $part ) {
				if ( self::meaningful( $part ) ) {
					return true; }
			}
			return false;
		}
		return is_scalar( $answer ) && ( is_numeric( $answer ) || is_bool( $answer ) || trim( wp_strip_all_tags( (string) $answer ) ) !== '' );
	}
}
