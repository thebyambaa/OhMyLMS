<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * `Accept-Language` parsing and matching against the interface locales.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\I18n;

defined( 'ABSPATH' ) || exit;

/**
 * Picks the interface locale a browser asks for. `parseAcceptLanguage()` and `matchLocale()` in
 * assets/interactivity/language/model.mjs implement the same rules; both are checked against
 * tests/fixtures/accept-language.json.
 */
final class AcceptLanguage {

	/** Longer headers are cut; real browsers send well under 200 characters. */
	const MAX_LENGTH = 1024;

	/** Ranges read from one header. */
	const MAX_RANGES = 32;

	/**
	 * Language ranges of a header, best first: lower-case tags (`_` read as `-`) with their quality.
	 * Ranges with `q=0`, a malformed quality or a malformed tag are dropped; equal qualities keep
	 * their order.
	 *
	 * @param mixed $header Header value.
	 * @return array<int, array{tag: string, q: float}>
	 */
	public static function parse( $header ) {
		if ( ! is_string( $header ) || '' === trim( $header ) ) {
			return array();
		}
		$ranges = array();
		$parts  = array_slice( explode( ',', substr( $header, 0, self::MAX_LENGTH ) ), 0, self::MAX_RANGES );
		foreach ( $parts as $position => $part ) {
			$params = explode( ';', $part );
			$tag    = str_replace( '_', '-', strtolower( trim( (string) array_shift( $params ) ) ) );
			if ( '*' !== $tag && ! preg_match( '/^[a-z]{1,8}(-[a-z0-9]{1,8})*$/', $tag ) ) {
				continue;
			}
			$quality = 1.0;
			foreach ( $params as $param ) {
				$param = strtolower( trim( $param ) );
				if ( 0 !== strpos( $param, 'q' ) ) {
					continue;
				}
				$quality = preg_match( '/^q\s*=\s*(0(\.\d{0,3})?|1(\.0{0,3})?)$/', $param, $match ) ? (float) $match[1] : 0.0;
			}
			if ( $quality <= 0 ) {
				continue;
			}
			$ranges[] = array(
				'tag'      => $tag,
				'q'        => $quality,
				'position' => $position,
			);
		}
		usort(
			$ranges,
			static function ( $a, $b ) {
				$order = $b['q'] <=> $a['q'];
				return 0 !== $order ? $order : $a['position'] <=> $b['position'];
			}
		);
		return array_map(
			static function ( $range ) {
				return array(
					'tag' => $range['tag'],
					'q'   => $range['q'],
				);
			},
			$ranges
		);
	}

	/**
	 * The locale the best range asks for. A range matches a locale by its whole tag (`en-us` is
	 * `en_US`) or by its language (`mn-MN` and `mn-Cyrl` are `mn`, `en-GB` is `en_US`); `*` takes the
	 * fallback. Ranges no locale serves are skipped.
	 *
	 * @param array    $ranges   Output of {@see self::parse()}.
	 * @param string[] $locales  WordPress locales, in preference order.
	 * @param string   $fallback Returned when nothing matches.
	 * @return string
	 */
	public static function match( array $ranges, array $locales, $fallback ) {
		foreach ( $ranges as $range ) {
			if ( '*' === $range['tag'] ) {
				return $fallback;
			}
			foreach ( $locales as $locale ) {
				if ( self::tag( $locale ) === $range['tag'] ) {
					return $locale;
				}
			}
			$language = explode( '-', $range['tag'] )[0];
			foreach ( $locales as $locale ) {
				if ( explode( '-', self::tag( $locale ) )[0] === $language ) {
					return $locale;
				}
			}
		}
		return $fallback;
	}

	/**
	 * Parse a header and match it.
	 *
	 * @param mixed    $header   Header value.
	 * @param string[] $locales  WordPress locales.
	 * @param string   $fallback Returned when nothing matches.
	 * @return string
	 */
	public static function negotiate( $header, array $locales, $fallback ) {
		return self::match( self::parse( $header ), $locales, $fallback );
	}

	/**
	 * Lower-case BCP 47 tag of a WordPress locale (`en_US` is `en-us`).
	 *
	 * @param string $locale WordPress locale.
	 * @return string
	 */
	public static function tag( $locale ) {
		return strtolower( str_replace( '_', '-', (string) $locale ) );
	}
}
