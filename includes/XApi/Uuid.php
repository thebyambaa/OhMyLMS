<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * UUID helpers for xAPI statement and registration identifiers.
 *
 * @package OhMyLMS\XApi
 */

namespace OhMyLMS\XApi;

defined( 'ABSPATH' ) || exit;

/**
 * Version 4 (random) and version 5 (name-based) UUIDs.
 *
 * Statement IDs are version 5 so that the same OhMyLMS event always produces the same
 * statement ID. Re-queuing or replaying an event can then never create a duplicate in the LRS.
 */
final class Uuid {

	/**
	 * Random UUID.
	 *
	 * @return string
	 */
	public static function v4() {
		$bytes    = random_bytes( 16 );
		$bytes[6] = chr( ( ord( $bytes[6] ) & 0x0f ) | 0x40 );
		$bytes[8] = chr( ( ord( $bytes[8] ) & 0x3f ) | 0x80 );
		$hex      = bin2hex( $bytes );

		return sprintf( '%s-%s-%s-%s-%s', substr( $hex, 0, 8 ), substr( $hex, 8, 4 ), substr( $hex, 12, 4 ), substr( $hex, 16, 4 ), substr( $hex, 20, 12 ) );
	}

	/**
	 * Name-based UUID (RFC 4122, SHA-1).
	 *
	 * @param string $scope A valid UUID that scopes the name.
	 * @param string $name  Name within that scope.
	 * @return string
	 * @throws \InvalidArgumentException When the scope is not a UUID.
	 */
	public static function v5( $scope, $name ) {
		if ( ! self::is_valid( $scope ) ) {
			throw new \InvalidArgumentException( 'Invalid UUID scope' );
		}
		$hash  = sha1( hex2bin( str_replace( '-', '', $scope ) ) . $name );
		$time  = ( hexdec( substr( $hash, 12, 4 ) ) & 0x0fff ) | 0x5000;
		$clock = ( hexdec( substr( $hash, 16, 4 ) ) & 0x3fff ) | 0x8000;

		return sprintf( '%s-%s-%04x-%04x-%s', substr( $hash, 0, 8 ), substr( $hash, 8, 4 ), $time, $clock, substr( $hash, 20, 12 ) );
	}

	/**
	 * Whether a value is a UUID in canonical form.
	 *
	 * @param mixed $value Value to check.
	 * @return bool
	 */
	public static function is_valid( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value );
	}
}
