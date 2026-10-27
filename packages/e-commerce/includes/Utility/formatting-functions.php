<?php
/**
 * Formatting functions.
 *
 * @package OhMyLMS
 */



/**
 * Convert a string representation of a date/time into a Unix timestamp.
 *
 * @param string   $time_string The date/time string to convert.
 * @param int|null $from_timestamp Optional. The timestamp to use as a base for relative date/time strings. Default is null.
 * @return int|false The Unix timestamp on success, or false on failure.
 *
 * @link https://github.com/woocommerce/woocommerce/blob/5907114d6eabae41edf39c593a36345b92990b38/plugins/woocommerce/includes/wc-formatting-functions.php#L703
 * @see wc_string_to_timestamp()
 *
 * @since 1.0.0
 */
function ohmylms_ecommerce_string_to_timestamp( $time_string, $from_timestamp = null ) {
	$time_string = $time_string ?? '';

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


/**
 * Get the timezone offset in seconds.
 *
 * @return int The timezone offset in seconds.
 * @link https://github.com/woocommerce/woocommerce/blob/5907114d6eabae41edf39c593a36345b92990b38/plugins/woocommerce/includes/wc-formatting-functions.php#L808
 * @see wc_timezone_offset()
 * @since 1.0.0
 */
function ohmylms_ecommerce_timezone_offset() {
	$timezone = get_option( 'timezone_string' );

	if ( $timezone ) {
		$timezone_object = new \DateTimeZone( $timezone );
		return $timezone_object->getOffset( new \DateTime( 'now' ) );
	} else {
		return floatval( get_option( 'gmt_offset', 0 ) ) * HOUR_IN_SECONDS;
	}
}


/**
 * Get the timezone string.
 *
 * @return string The timezone string.
 * @since 1.0.0
 */
function ohmylms_ecommerce_timezone_string() {
	// Added in WordPress 5.3 Ref https://developer.wordpress.org/reference/functions/wp_timezone_string/.
	if ( function_exists( 'wp_timezone_string' ) ) {
		return wp_timezone_string();
	}

	// If site timezone string exists, return it.
	$timezone = get_option( 'timezone_string' );
	if ( $timezone ) {
		return $timezone;
	}

	// Get UTC offset, if it isn't set then return UTC.
	$utc_offset = floatval( get_option( 'gmt_offset', 0 ) );
	if ( ! is_numeric( $utc_offset ) || 0.0 === $utc_offset ) {
		return 'UTC';
	}

	// Adjust UTC offset from hours to seconds.
	$utc_offset = (int) ( $utc_offset * 3600 );

	// Attempt to guess the timezone string from the UTC offset.
	$timezone = timezone_name_from_abbr( '', $utc_offset );
	if ( $timezone ) {
		return $timezone;
	}

	// Last try, guess timezone string manually.
	foreach ( timezone_abbreviations_list() as $abbr ) {
		foreach ( $abbr as $city ) {
			// WordPress restrict the use of date(), since it's affected by timezone settings, but in this case is just what we need to guess the correct timezone.
			if ( (bool) date( 'I' ) === (bool) $city['dst'] && $city['timezone_id'] && intval( $city['offset'] ) === $utc_offset ) { // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				return $city['timezone_id'];
			}
		}
	}

	// Fallback to UTC.
	return 'UTC';
}

/**
 * Format the refund total amount.
 *
 * @param float $amount The amount to be formatted.
 * @return float The formatted refund total amount.
 *
 * @since 1.0.0
 */
function ohmylms_ecommerce_format_refund_total( $amount ) {
	return $amount * -1;
}


/**
 * Format a coupon code.
 *
 * @param string $code The coupon code to format.
 * @return string The formatted coupon code.
 * @since 1.0.0
 */
function ohmylms_ecommerce_format_coupon_code( $code ) {
	$code = html_entity_decode( $code );
	$code = wp_kses( sanitize_post_field( 'post_title', $code ?? '', 0, 'db' ), 'entities' );
	return $code;
}


/**
 * Ohmylms ecommerce string to datetime for Formatting-functions.
 *
 * @param mixed $time_string Time string.
 */
function ohmylms_ecommerce_string_to_datetime( $time_string ) {
	// Strings are defined in local WP timezone. Convert to UTC.
	if ( 1 === preg_match( '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(Z|((-|\+)\d{2}:\d{2}))$/', $time_string, $date_bits ) ) {
		$offset    = ! empty( $date_bits[7] ) ? iso8601_timezone_to_offset( $date_bits[7] ) : ohmylms_timezone_offset();
		$timestamp = gmmktime( $date_bits[4], $date_bits[5], $date_bits[6], $date_bits[2], $date_bits[3], $date_bits[1] ) - $offset;
	} else {
		$timestamp = ohmylms_string_to_timestamp( get_gmt_from_date( gmdate( 'Y-m-d H:i:s', ohmylms_string_to_timestamp( $time_string ) ) ) );
	}
	$datetime = new \OhMyLMS\Commerce\EcommerceDateTime( "@{$timestamp}", new DateTimeZone( 'UTC' ) );

	// Set local timezone or offset.
	$timezone_string = get_option( 'timezone_string' );
	if ( ! empty( $timezone_string ) ) {
		$datetime->setTimezone( new \DateTimeZone( $timezone_string ) );
	} else {
		$datetime->set_utc_offset( ohmylms_timezone_offset() );
	}

	return $datetime;
}
