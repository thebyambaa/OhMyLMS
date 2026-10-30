<?php
/**
 * Request path, content type and byte-range helpers for serving package files.
 *
 * @package OhMyLMS\Cmi5
 */

namespace OhMyLMS\Cmi5;

defined( 'ABSPATH' ) || exit;

/**
 * Pure helpers used when a learner's browser asks for a file from a package.
 */
final class ContentPath {

	/**
	 * Turn the path part of a request into a package-relative file path.
	 *
	 * @param string $raw Path after the token, still URL-encoded.
	 * @return string|false The relative path, or false when it is unsafe or empty.
	 */
	public static function clean( $raw ) {
		$decoded = rawurldecode( (string) $raw );
		if ( '' === $decoded || preg_match( '/[\x00-\x1f\x7f\\\\:]/', $decoded ) ) {
			return false;
		}
		$clean = array();
		foreach ( explode( '/', $decoded ) as $segment ) {
			if ( '' === $segment || '.' === $segment ) {
				continue;
			}
			if ( '..' === $segment ) {
				return false;
			}
			$clean[] = $segment;
		}

		return $clean ? implode( '/', $clean ) : false;
	}

	/**
	 * Content type for a file, from its extension.
	 *
	 * @param string $path File path.
	 * @return string
	 */
	public static function mime( $path ) {
		$types     = array(
			'html'  => 'text/html; charset=utf-8',
			'htm'   => 'text/html; charset=utf-8',
			'xhtml' => 'application/xhtml+xml; charset=utf-8',
			'js'    => 'application/javascript; charset=utf-8',
			'mjs'   => 'application/javascript; charset=utf-8',
			'css'   => 'text/css; charset=utf-8',
			'json'  => 'application/json; charset=utf-8',
			'map'   => 'application/json; charset=utf-8',
			'xml'   => 'application/xml; charset=utf-8',
			'txt'   => 'text/plain; charset=utf-8',
			'csv'   => 'text/csv; charset=utf-8',
			'vtt'   => 'text/vtt; charset=utf-8',
			'srt'   => 'text/plain; charset=utf-8',
			'png'   => 'image/png',
			'jpg'   => 'image/jpeg',
			'jpeg'  => 'image/jpeg',
			'gif'   => 'image/gif',
			'webp'  => 'image/webp',
			'avif'  => 'image/avif',
			'svg'   => 'image/svg+xml',
			'ico'   => 'image/x-icon',
			'bmp'   => 'image/bmp',
			'mp3'   => 'audio/mpeg',
			'wav'   => 'audio/wav',
			'ogg'   => 'audio/ogg',
			'oga'   => 'audio/ogg',
			'm4a'   => 'audio/mp4',
			'aac'   => 'audio/aac',
			'mp4'   => 'video/mp4',
			'm4v'   => 'video/mp4',
			'webm'  => 'video/webm',
			'ogv'   => 'video/ogg',
			'woff'  => 'font/woff',
			'woff2' => 'font/woff2',
			'ttf'   => 'font/ttf',
			'otf'   => 'font/otf',
			'eot'   => 'application/vnd.ms-fontobject',
			'wasm'  => 'application/wasm',
			'pdf'   => 'application/pdf',
		);
		$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

		return isset( $types[ $extension ] ) ? $types[ $extension ] : 'application/octet-stream';
	}

	/**
	 * Interpret a Range header for a file of a known size.
	 *
	 * Only a single byte range is served (what browsers send to seek in audio and video); anything
	 * else is ignored and the whole file is sent, which the HTTP specification allows.
	 *
	 * @param string $header Value of the Range header, or an empty string.
	 * @param int    $size   File size in bytes.
	 * @return array|null|false [start, end] (inclusive), null to send the whole file, or false when
	 *                          the range cannot be satisfied (answer 416).
	 */
	public static function parse_range( $header, $size ) {
		if ( '' === (string) $header || $size < 1 ) {
			return null;
		}
		if ( ! preg_match( '/^bytes=(\d*)-(\d*)$/', trim( $header ), $match ) || ( '' === $match[1] && '' === $match[2] ) ) {
			return null;
		}
		if ( '' === $match[1] ) {
			// "-500": the last 500 bytes.
			$length = (int) $match[2];
			if ( 0 === $length ) {
				return false;
			}

			return array( max( 0, $size - $length ), $size - 1 );
		}
		$start = (int) $match[1];
		$end   = '' === $match[2] ? $size - 1 : min( (int) $match[2], $size - 1 );
		if ( $start >= $size || $end < $start ) {
			return false;
		}

		return array( $start, $end );
	}
}
