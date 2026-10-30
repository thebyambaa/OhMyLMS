<?php
/**
 * Recognising requests meant for the cmi5 endpoints.
 *
 * @package OhMyLMS\Cmi5
 */

namespace OhMyLMS\Cmi5;

defined( 'ABSPATH' ) || exit;

/**
 * Parses a request path into a route. Pure, so the rules (and the many ways to get them wrong) are
 * unit tested without a web server.
 *
 * Routes, all under /ohmylms-cmi5/:
 *   c/{content token}/{path}   a file from a package, for one launch session
 *   fetch/{fetch token}        the one-time URL an AU POSTs to for its auth token
 *   lrs/{resource}             the LRS endpoint (xAPI resources)
 */
final class Routes {

	const PREFIX = 'ohmylms-cmi5';

	/**
	 * Parse a request.
	 *
	 * @param string $request_uri Request URI as received, including any query string.
	 * @param string $home_path   Path of the site home URL, for example "/" or "/shop/".
	 * @return array|null Null when the request is not for cmi5 at all. Otherwise an array with a
	 *                    "route" of c, fetch, lrs or invalid, plus token and path where they apply.
	 */
	public static function parse( $request_uri, $home_path ) {
		$path = (string) wp_parse_url( (string) $request_uri, PHP_URL_PATH );
		$base = rtrim( (string) $home_path, '/' ) . '/' . self::PREFIX . '/';
		if ( 0 !== strpos( $path, $base ) ) {
			return null;
		}
		$rest  = substr( $path, strlen( $base ) );
		$parts = explode( '/', $rest, 3 );
		switch ( $parts[0] ) {
			case 'c':
				if ( ! isset( $parts[1], $parts[2] ) || ! Tokens::is_hex_token( $parts[1] ) || '' === $parts[2] ) {
					return array( 'route' => 'invalid' );
				}

				return array(
					'route' => 'c',
					'token' => $parts[1],
					'path'  => $parts[2],
				);
			case 'fetch':
				if ( ! isset( $parts[1] ) || ! Tokens::is_hex_token( $parts[1] ) || isset( $parts[2] ) ) {
					return array( 'route' => 'invalid' );
				}

				return array(
					'route' => 'fetch',
					'token' => $parts[1],
				);
			case 'lrs':
				return array(
					'route' => 'lrs',
					'path'  => isset( $parts[1] ) ? ltrim( $parts[1] . ( isset( $parts[2] ) ? '/' . $parts[2] : '' ), '/' ) : '',
				);
		}

		return array( 'route' => 'invalid' );
	}
}
