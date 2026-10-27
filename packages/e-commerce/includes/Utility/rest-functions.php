<?php
/**
 * Rest functions.
 *
 * @package OhMyLMS
 */


use OhMyLMS\Commerce\EcommerceDateTime;

/**
 * Prepare a date response for the REST API.
 *
 * This function formats a date for the REST API response.
 *
 * @param int|string|\DateTimeInterface $date The date to be formatted.
 * @param bool                          $utc Whether to convert the date to UTC. Default true.
 * @return string|null The formatted date string, or null if the date is invalid.
 *
 * @since 1.0.0
 */
function ohmylms_ecommerce_rest_prepare_date_response( $date, $utc = true ) {
	if ( is_numeric( $date ) ) {
		$date = new EcommerceDateTime( "@$date", new DateTimeZone( 'UTC' ) );
		$date->setTimezone( new \DateTimeZone( ohmylms_timezone_string() ) );
	} elseif ( is_string( $date ) ) {
		$date = new EcommerceDateTime( $date, new \DateTimeZone( 'UTC' ) );
		$date->setTimezone( new \DateTimeZone( ohmylms_timezone_string() ) );
	}

	if ( is_null( $date ) || false === $date ) {
		return null;
	}

	return gmdate( 'Y-m-d\TH:i:s', $utc ? $date->getTimestamp() : $date->getOffsetTimestamp() );
}
