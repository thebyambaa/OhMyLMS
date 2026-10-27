<?php
/**
 * Time functions.
 *
 * @package OhMyLMS
 */


/**
 * Convert date string to timestamp
 *
 * @param mixed $date_string Date string.
 * @return int
 * @throws Exception When the operation cannot be completed.
 * @since 1.0.0
 */
function ohmylms_date_to_time( $date_string ) {
	if ( 0 == $date_string ) {
		return 0;
	}

	$date_time = new DateTime( $date_string, new DateTimeZone( 'UTC' ) );

	return intval( $date_time->getTimestamp() );
}

/**
 * Add time to timestamp
 *
 * @param mixed $number_of_periods Number of periods.
 * @param mixed $period Period.
 * @param mixed $from_timestamp From timestamp.
 * @return false|int|mixed
 * @since 1.0.0
 */
function ohmylms_add_time( $number_of_periods, $period, $from_timestamp ) {
	if ( $number_of_periods > 0 ) {
		$next_timestamp = ohmylms_strtotime( "+ {$number_of_periods} {$period}", $from_timestamp );
	} else {
		$next_timestamp = $from_timestamp;
	}

	return $next_timestamp;
}


/**
 * Convert date string to timestamp
 *
 * @param mixed $time_string Time string.
 * @param mixed $from_timestamp From timestamp.
 * @return false|int
 * @since 1.0.0
 */
function ohmylms_strtotime( $time_string, $from_timestamp = null ) {
	$original_timezone = date_default_timezone_get();
	date_default_timezone_set( 'UTC' );

	if ( null === $from_timestamp ) {
		$next_timestamp = strtotime( $time_string );
	} else {
		$next_timestamp = strtotime( $time_string, $from_timestamp );
	}
	date_default_timezone_set( $original_timezone );
	return $next_timestamp;
}
