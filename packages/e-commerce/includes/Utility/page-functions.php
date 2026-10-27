<?php
/**
 * Page functions.
 *
 * @package OhMyLMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ohmylms ecommerce page endpoint title for Page-functions.
 *
 * @param mixed $title Title.
 */
function ohmylms_ecommerce_page_endpoint_title( $title ) {
	global $wp_query;

	if ( ! is_null( $wp_query ) && ! is_admin() && is_main_query() && in_the_loop() && is_page() ) {
		$endpoint       = \OhMyLMS\Commerce\ecommerce()->query->get_current_endpoint();
		$action         = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
		$endpoint_title = \OhMyLMS\Commerce\ecommerce()->query->get_endpoint_title( $endpoint, $action );
		$title          = $endpoint_title ? $endpoint_title : $title;

		remove_filter( 'the_title', 'ohmylms_ecommerce_page_endpoint_title' );
	}

	return $title;
}

add_filter( 'the_title', 'ohmylms_ecommerce_page_endpoint_title' );



/**
 * Ohmylms ecommerce get endpoint url for Page-functions.
 *
 * @param mixed  $endpoint Endpoint.
 * @param string $value Value to process.
 * @param string $permalink Permalink.
 */
function ohmylms_ecommerce_get_endpoint_url( $endpoint, $value = '', $permalink = '' ) {

	if ( ! $permalink ) {
		$permalink = get_permalink();
	}

	// Map endpoint to options.
	$query_vars = \OhMyLMS\Commerce\ecommerce()->query->get_query_vars();
	$endpoint   = ! empty( $query_vars[ $endpoint ] ) ? $query_vars[ $endpoint ] : $endpoint;

	if ( get_option( 'permalink_structure' ) ) {
		if ( strstr( $permalink, '?' ) ) {
			$query_string = '?' . wp_parse_url( $permalink, PHP_URL_QUERY );
			$permalink    = current( explode( '?', $permalink ) );
		} else {
			$query_string = '';
		}
		$url = trailingslashit( $permalink );

		if ( $value ) {
			$url .= trailingslashit( $endpoint ) . user_trailingslashit( $value );
		} else {
			$url .= user_trailingslashit( $endpoint );
		}

		$url .= $query_string;
	} else {
		$url = add_query_arg( $endpoint, $value, $permalink );
	}

	return $url;
}
