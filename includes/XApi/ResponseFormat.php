<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Learner responses as xAPI result.response strings.
 *
 * @package OhMyLMS\XApi
 */

namespace OhMyLMS\XApi;

defined( 'ABSPATH' ) || exit;

/**
 * OhMyLMS stores choice responses as option IDs, which mean nothing outside the site. This
 * resolves them to the option text the learner saw, and writes lists and pairs in the xAPI
 * response formats ("a[,]b" for lists, "key[.]value" for pairs).
 */
final class ResponseFormat {

	const MAX_LENGTH = 1000;

	/**
	 * Format a stored response.
	 *
	 * @param mixed $response Decoded response: scalar, list or map. Null means unanswered.
	 * @param array $labels   Option ID => option text for the answered version.
	 * @return string|null Null when there is nothing to report.
	 */
	public static function format( $response, array $labels = array() ) {
		if ( null === $response || array() === $response || '' === $response ) {
			return null;
		}
		if ( is_array( $response ) ) {
			$parts = array();
			$list  = array_keys( $response ) === range( 0, count( $response ) - 1 );
			foreach ( $response as $key => $value ) {
				$text = self::scalar( $value, $labels );
				if ( null === $text ) {
					continue;
				}
				$parts[] = $list ? $text : self::scalar( $key, $labels ) . '[.]' . $text;
			}
			$text = implode( '[,]', $parts );
		} else {
			$text = self::scalar( $response, $labels );
		}
		if ( null === $text || '' === $text ) {
			return null;
		}

		return self::truncate( $text );
	}

	/**
	 * One scalar, resolved through the option labels when it is a known option ID.
	 *
	 * @param mixed $value  Value.
	 * @param array $labels Option labels.
	 * @return string|null
	 */
	private static function scalar( $value, array $labels ) {
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		if ( is_array( $value ) ) {
			return self::truncate( (string) wp_json_encode( $value ) );
		}
		if ( ! is_scalar( $value ) ) {
			return null;
		}
		$key = (string) $value;
		if ( isset( $labels[ $key ] ) ) {
			return trim( (string) $labels[ $key ] );
		}

		return $key;
	}

	/**
	 * Cut to MAX_LENGTH characters without splitting a multibyte character.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function truncate( $text ) {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $text, 0, self::MAX_LENGTH, 'UTF-8' );
		}

		return substr( $text, 0, self::MAX_LENGTH );
	}
}
