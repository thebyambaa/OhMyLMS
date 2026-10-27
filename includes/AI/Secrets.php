<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Keeps a third-party API key unreadable in the database.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps a third-party API key unreadable in the database.
 *
 * The key is sealed with libsodium (bundled with WordPress) under a key derived from the
 * site's own salts, so a copy of the database alone does not reveal it. Anyone who can read
 * the code and wp-config.php can, which is why a constant or environment variable
 * (OHMYLMS_AI_API_KEY) is the better home for a production key and is preferred when set.
 * Changing the site's AUTH salts invalidates a stored key; the admin page then asks for it again.
 */
final class Secrets {
	const PREFIX = 'v1:';

	/**
	 * Key for Secrets.
	 */
	private static function key() {
		return sodium_crypto_generichash( 'ohmylms-ai|' . wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' ), '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}

	/** Sealed, printable text for $secret.
	 *
	 * @param mixed $secret Secret.
	 */
	public static function seal( $secret ) {
		$secret = (string) $secret;
		if ( '' === $secret ) {
			return ''; }
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		return self::PREFIX . base64_encode( $nonce . sodium_crypto_secretbox( $secret, $nonce, self::key() ) );
	}

	/** The original secret, or '' when the text is not ours or cannot be opened any more.
	 *
	 * @param mixed $sealed Sealed.
	 */
	public static function open( $sealed ) {
		$sealed = (string) $sealed;
		if ( strpos( $sealed, self::PREFIX ) !== 0 ) {
			return ''; }
		$raw = base64_decode( substr( $sealed, strlen( self::PREFIX ) ), true );
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return ''; }
		$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), self::key() );
		return false === $plain ? '' : $plain;
	}

	/**
	 * Hint for Secrets.
	 * "…a1b2": enough to recognise a key, never enough to use it.
	 *
	 * @param mixed $secret Secret.
	 */
	public static function hint( $secret ) {
		$secret = (string) $secret;
		return strlen( $secret ) < 12 ? '' : '…' . substr( $secret, -4 );
	}
}
