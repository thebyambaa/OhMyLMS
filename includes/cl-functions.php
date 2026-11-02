<?php
/**
 * Cl functions.
 *
 * @package OhMyLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get states for a country.
 *
 * @since 1.0.0
 *
 * @param string $country_code The country code.
 * @return array An array of states.
 */
function ohmylms_get_states( $country_code ) {
	return \OhMyLMS\Utility\CountryStates::states( $country_code );
}

/**
 * Get navigation link URL from General Settings
 *
 * @param string $link_type 'profile' or 'courses'.
 * @return string URL
 * @since 1.2.5
 */
function ohmylms_get_nav_link_url( $link_type ) {
	$option_key = 'ohmylms_nav_my_' . $link_type . '_url';
	$page_id    = get_option( $option_key );

	if ( $page_id ) {
		$url = get_permalink( $page_id );
		if ( $url ) {
			return $url;
		}
	}

	// Fallback to default pages.
	if ( 'profile' === $link_type ) {
		return ohmylms_get_account_endpoint_url( 'profile' );
	} elseif ( 'courses' === $link_type ) {
		return ohmylms_get_account_endpoint_url( 'my-courses' );
	}

	return home_url();
}
