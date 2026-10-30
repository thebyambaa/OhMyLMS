<?php
/**
 * Random tokens for cmi5 sessions.
 *
 * @package OhMyLMS\Cmi5
 */

namespace OhMyLMS\Cmi5;

defined( 'ABSPATH' ) || exit;

/**
 * Generates and hashes the secrets a launch session depends on. Only hashes are ever stored, so a
 * copy of the database cannot be used to read a learner's files or write to their session.
 */
final class Tokens {

	/**
	 * A secret carried in the path of every content URL, so files load without cookies.
	 *
	 * @return string 64 hexadecimal characters.
	 */
	public static function content() {
		return bin2hex( random_bytes( 32 ) );
	}

	/**
	 * The secret in the one-time fetch URL.
	 *
	 * @return string 64 hexadecimal characters.
	 */
	public static function fetch() {
		return bin2hex( random_bytes( 32 ) );
	}

	/**
	 * The auth token handed to the AU. It is sent back as an HTTP Basic credential, so it is a
	 * valid base64 string.
	 *
	 * @return string
	 */
	public static function auth() {
		return base64_encode( random_bytes( 32 ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- cmi5 hands the AU a Basic credential, which is base64 by definition.
	}

	/**
	 * What is stored in place of a token.
	 *
	 * @param string $token Token.
	 * @return string
	 */
	public static function hash( $token ) {
		return hash( 'sha256', (string) $token );
	}

	/**
	 * Whether a value has the shape of a content or fetch token.
	 *
	 * @param mixed $token Value.
	 * @return bool
	 */
	public static function is_hex_token( $token ) {
		return is_string( $token ) && 1 === preg_match( '/^[0-9a-f]{64}$/', $token );
	}
}
