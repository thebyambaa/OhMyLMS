<?php
/**
 * Building the URL an AU is launched with.
 *
 * @package OhMyLMS\Cmi5
 */

namespace OhMyLMS\Cmi5;

defined( 'ABSPATH' ) || exit;

/**
 * Appends the cmi5 launch parameters to an AU's URL and builds the URLs the LMS itself serves.
 */
final class LaunchUrl {

	/**
	 * The parameters cmi5 defines (section 8.1).
	 */
	const PARAMS = array( 'endpoint', 'fetch', 'actor', 'registration', 'activityId' );

	/**
	 * Add the cmi5 parameters to an AU URL.
	 *
	 * Query parameters the AU already has are kept (section 13.1.4 says they are merged). A parameter
	 * the AU defines with a cmi5 name is dropped, so the LMS value always wins.
	 *
	 * @param string $au_url AU URL, relative to nothing: already made absolute.
	 * @param array  $cmi5   Values for endpoint, fetch, actor, registration and activityId.
	 * @return string
	 */
	public static function build( $au_url, array $cmi5 ) {
		$fragment = '';
		$pos      = strpos( $au_url, '#' );
		if ( false !== $pos ) {
			$fragment = substr( $au_url, $pos );
			$au_url   = substr( $au_url, 0, $pos );
		}
		$query = '';
		$pos   = strpos( $au_url, '?' );
		if ( false !== $pos ) {
			$query  = substr( $au_url, $pos + 1 );
			$au_url = substr( $au_url, 0, $pos );
		}
		$pairs = array();
		foreach ( '' === $query ? array() : explode( '&', $query ) as $pair ) {
			if ( '' === $pair ) {
				continue;
			}
			$name = rawurldecode( explode( '=', $pair, 2 )[0] );
			if ( ! in_array( $name, self::PARAMS, true ) ) {
				$pairs[] = $pair;
			}
		}
		foreach ( self::PARAMS as $name ) {
			if ( isset( $cmi5[ $name ] ) ) {
				$pairs[] = rawurlencode( $name ) . '=' . rawurlencode( (string) $cmi5[ $name ] );
			}
		}

		return $au_url . '?' . implode( '&', $pairs ) . $fragment;
	}

	/**
	 * URL of a file inside a package for one launch session.
	 *
	 * @param string $home     Site home URL with a trailing slash.
	 * @param string $token    Content token.
	 * @param string $relative Path inside the package, with an optional query string and fragment.
	 * @return string
	 */
	public static function content_url( $home, $token, $relative ) {
		$suffix = '';
		$cut    = strcspn( $relative, '?#' );
		if ( $cut < strlen( $relative ) ) {
			$suffix   = substr( $relative, $cut );
			$relative = substr( $relative, 0, $cut );
		}
		$segments = array_map( 'rawurlencode', explode( '/', rawurldecode( $relative ) ) );

		return $home . Routes::PREFIX . '/c/' . $token . '/' . implode( '/', $segments ) . $suffix;
	}

	/**
	 * The one-time fetch URL for a session.
	 *
	 * @param string $home  Site home URL with a trailing slash.
	 * @param string $token Fetch token.
	 * @return string
	 */
	public static function fetch_url( $home, $token ) {
		return $home . Routes::PREFIX . '/fetch/' . $token;
	}

	/**
	 * The LRS endpoint AUs send xAPI requests to (with a trailing slash, as in the spec's examples).
	 *
	 * @param string $home Site home URL with a trailing slash.
	 * @return string
	 */
	public static function endpoint( $home ) {
		return $home . Routes::PREFIX . '/lrs/';
	}
}
