<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Questions have one authored statement; the post title is a derived plain-text list title.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Assessment;

/** Questions have one authored statement; the post title is a derived plain-text list title. */
final class QuestionTitle {
	const LENGTH = 80;

	/**
	 * Readable one-line text of a statement: tags removed, entities decoded, LaTeX simplified.
	 *
	 * @param string $html Statement HTML.
	 * @return string Plain text.
	 */
	public static function text( $html ) {
		$value = preg_replace( '#<br\s*/?>|</(?:p|div|li|h\d)>#i', ' ', (string) $html );
		$value = html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES, 'UTF-8' );
		$value = preg_replace( '/\$\$?|\\\\[()\[\]]/', '', $value );
		// Hide {{variables}} so their braces are not read as LaTeX groups.
		$vars  = array();
		$value = preg_replace_callback(
			'/\{\{[^{}]*\}\}/',
			static function ( $m ) use ( &$vars ) {
				$vars[] = $m[0];
				return "\x01" . ( count( $vars ) - 1 ) . "\x02";
			},
			$value
		);
		for ( $pass = 0; $pass < 3; $pass++ ) {
			$value = preg_replace_callback(
				'/\\\\[dt]?frac\s*\{([^{}]*)\}\s*\{([^{}]*)\}/',
				static function ( $m ) {
					return trim( $m[1] ) . '/' . trim( $m[2] );
				},
				$value
			);
			$value = preg_replace_callback(
				'/\\\\sqrt\s*\{([^{}]*)\}/',
				static function ( $m ) {
					return '√' . trim( $m[1] );
				},
				$value
			);
		}
		$value   = preg_replace_callback(
			'/\x01(\d+)\x02/',
			static function ( $m ) use ( $vars ) {
				return $vars[ (int) $m[1] ] ?? '';
			},
			$value
		);
		$symbols = array(
			'cdot'   => '·',
			'times'  => '×',
			'div'    => '÷',
			'pm'     => '±',
			'le'     => '≤',
			'leq'    => '≤',
			'ge'     => '≥',
			'geq'    => '≥',
			'ne'     => '≠',
			'neq'    => '≠',
			'pi'     => 'π',
			'infty'  => '∞',
			'degree' => '°',
			'circ'   => '°',
		);
		$value   = preg_replace_callback(
			'/\\\\([a-zA-Z]+)/',
			static function ( $m ) use ( $symbols ) {
				return $symbols[ $m[1] ] ?? '';
			},
			$value
		);
		$value   = preg_replace( '/\^\{([^{}]*)\}/', '^$1', $value );
		return trim( preg_replace( '/ ([?!.,;:)])/u', '$1', preg_replace( '/\s+/u', ' ', $value ) ) );
	}

	/**
	 * List title of a statement, cut at a word boundary.
	 *
	 * @param string $html Statement HTML.
	 * @return string Title.
	 */
	public static function from_statement( $html ) {
		$text = self::text( $html );
		if ( mb_strlen( $text ) <= self::LENGTH ) {
			return $text;
		}
		$cut   = mb_substr( $text, 0, self::LENGTH - 1 );
		$space = mb_strrpos( $cut, ' ' );
		return rtrim( false !== $space && $space > self::LENGTH / 2 ? mb_substr( $cut, 0, $space ) : $cut ) . '…';
	}

	/**
	 * Whether a stored title is only a placeholder.
	 *
	 * @param string $name Title.
	 * @return bool
	 */
	public static function placeholder( $name ) {
		return (bool) preg_match( '/^(?:|Untitled|Question \d+)$/', trim( (string) $name ) );
	}
}
