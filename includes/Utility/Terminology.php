<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Site-wide wording: calls course chapters "units" (or any administrator-chosen word).
 *
 * Only the words learners and administrators read change. Post types, tables, REST routes,
 * URLs, hooks and CSS classes keep their stored names, so nothing needs migrating and the
 * change can be switched off again. English source strings are rewritten when they have no
 * translation of their own; a locale that already translates "Chapter" keeps its translation.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Utility;

defined( 'ABSPATH' ) || exit;

/** Renames "chapter" in every translatable string of the plugin's text domains. */
final class Terminology {

	const OPTION  = 'ohmylms_terminology';
	const DOMAINS = array( 'ohmylms', 'ohmylms-pro' );
	const HANDLE  = 'wp-i18n';

	/** Register the PHP and JavaScript string filters. */
	public static function init() {
		add_filter( 'gettext', array( __CLASS__, 'gettext' ), 20, 3 );
		add_filter( 'gettext_with_context', array( __CLASS__, 'gettext_with_context' ), 20, 4 );
		add_filter( 'ngettext', array( __CLASS__, 'ngettext' ), 20, 5 );
		add_filter( 'ngettext_with_context', array( __CLASS__, 'ngettext_with_context' ), 20, 6 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'script' ), 1 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'script' ), 1 );
	}

	/**
	 * Saved wording with safe defaults.
	 *
	 * @return array{enabled:bool,singular:string,plural:string}
	 */
	public static function settings() {
		return self::sanitize( get_option( self::OPTION, array() ) );
	}

	/**
	 * Accept only short plain words; anything else falls back to Unit/Units.
	 *
	 * @param mixed $input Untrusted settings.
	 * @return array{enabled:bool,singular:string,plural:string}
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$singular = self::word( $input['singular'] ?? '', __( 'Unit', 'ohmylms' ) );
		$plural   = self::word( $input['plural'] ?? '', '' );
		return array(
			'enabled'  => ! array_key_exists( 'enabled', $input ) || ! empty( $input['enabled'] ),
			'singular' => $singular,
			'plural'   => '' !== $plural ? $plural : $singular . 's',
		);
	}

	/**
	 * One short label without markup.
	 *
	 * @param mixed  $value Candidate label.
	 * @param string $fallback Used when the candidate is empty.
	 * @return string
	 */
	private static function word( $value, $fallback ) {
		$value = is_string( $value ) ? trim( sanitize_text_field( $value ) ) : '';
		$value = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 30 ) : substr( $value, 0, 30 );
		return '' !== $value ? $value : $fallback;
	}

	/**
	 * Rewrite the word "chapter" in an English string, keeping capitalization.
	 *
	 * @param string $text String to rewrite.
	 * @return string
	 */
	public static function rename( $text ) {
		if ( ! is_string( $text ) || false === stripos( $text, 'chapter' ) ) {
			return $text;
		}
		$labels = self::settings();
		if ( ! $labels['enabled'] ) {
			return $text;
		}
		return preg_replace_callback(
			'/\bchapters?\b/iu',
			static function ( $found ) use ( $labels ) {
				$word   = $found[0];
				$plural = 's' === strtolower( substr( $word, -1 ) );
				$label  = $plural ? $labels['plural'] : $labels['singular'];
				if ( strtoupper( $word ) === $word ) {
					return self::change_case( $label, true );
				}
				return strtoupper( $word[0] ) === $word[0] ? $label : self::change_case( $label, false );
			},
			$text
		);
	}

	/**
	 * Upper- or lowercase a label, using mbstring when the host has it.
	 *
	 * @param string $text Label.
	 * @param bool   $upper True for uppercase.
	 * @return string
	 */
	private static function change_case( $text, $upper ) {
		if ( function_exists( 'mb_strtoupper' ) ) {
			return $upper ? mb_strtoupper( $text ) : mb_strtolower( $text );
		}
		return $upper ? strtoupper( $text ) : strtolower( $text );
	}

	/**
	 * Whether a text domain belongs to this plugin.
	 *
	 * @param mixed $domain Text domain.
	 * @return bool
	 */
	private static function ours( $domain ) {
		return in_array( $domain, self::DOMAINS, true );
	}

	/**
	 * Rename only strings that have no translation of their own.
	 *
	 * @param string $translation Translated text.
	 * @param string $original English source text.
	 * @param mixed  $domain Text domain.
	 * @return string
	 */
	private static function apply( $translation, $original, $domain ) {
		return self::ours( $domain ) && $translation === $original ? self::rename( $translation ) : $translation;
	}

	/**
	 * Filter for gettext().
	 *
	 * @param string $translation Translated text.
	 * @param string $text Source text.
	 * @param string $domain Text domain.
	 * @return string
	 */
	public static function gettext( $translation, $text, $domain ) {
		return self::apply( $translation, $text, $domain );
	}

	/**
	 * Filter for _x().
	 *
	 * @param string $translation Translated text.
	 * @param string $text Source text.
	 * @param string $context Translation context.
	 * @param string $domain Text domain.
	 * @return string
	 */
	public static function gettext_with_context( $translation, $text, $context, $domain ) {
		// Spreadsheet columns distinguish syllabus Units from Chapters explicitly.
		if ( 'syllabus-spreadsheet' === $context ) {
			return $translation;
		}
		return self::apply( $translation, $text, $domain );
	}

	/**
	 * Filter for _n().
	 *
	 * @param string $translation Translated text.
	 * @param string $single Singular source.
	 * @param string $plural Plural source.
	 * @param int    $number Count.
	 * @param string $domain Text domain.
	 * @return string
	 */
	public static function ngettext( $translation, $single, $plural, $number, $domain ) {
		$original = 1 === (int) $number ? $single : $plural;
		return self::apply( $translation, $original, $domain );
	}

	/**
	 * Filter for _nx().
	 *
	 * @param string $translation Translated text.
	 * @param string $single Singular source.
	 * @param string $plural Plural source.
	 * @param int    $number Count.
	 * @param string $context Translation context.
	 * @param string $domain Text domain.
	 * @return string
	 */
	public static function ngettext_with_context( $translation, $single, $plural, $number, $context, $domain ) {
		unset( $context );
		return self::ngettext( $translation, $single, $plural, $number, $domain );
	}

	/** Mirror the rename in the JavaScript i18n layer used by the editor and learner screens. */
	public static function script() {
		$labels = self::settings();
		if ( ! $labels['enabled'] ) {
			return;
		}
		$config = wp_json_encode(
			array(
				'domains'  => self::DOMAINS,
				'singular' => $labels['singular'],
				'plural'   => $labels['plural'],
			)
		);
		wp_add_inline_script(
			self::HANDLE,
			'( function ( config ) {
	var hooks = window.wp && window.wp.hooks;
	if ( ! hooks || ! config ) {
		return;
	}
	var rename = function ( text ) {
		if ( typeof text !== "string" || ! /chapter/i.test( text ) ) {
			return text;
		}
		return text.replace( /\bchapters?\b/gi, function ( word ) {
			var label = /s$/i.test( word ) ? config.plural : config.singular;
			if ( word === word.toUpperCase() ) {
				return label.toUpperCase();
			}
			return word.charAt( 0 ) === word.charAt( 0 ).toUpperCase() ? label : label.toLowerCase();
		} );
	};
	var wrap = function ( originalIndex, domainIndex, contextIndex ) {
		return function () {
			var args = arguments;
			if ( contextIndex !== undefined && args[ contextIndex ] === "syllabus-spreadsheet" ) {
				return args[ 0 ];
			}
			var domain = args[ domainIndex ];
			if ( config.domains.indexOf( domain ) === -1 ) {
				return args[ 0 ];
			}
			var original = originalIndex === "n" ? ( args[ 3 ] === 1 ? args[ 1 ] : args[ 2 ] ) : args[ originalIndex ];
			return args[ 0 ] === original ? rename( args[ 0 ] ) : args[ 0 ];
		};
	};
	hooks.addFilter( "i18n.gettext", "ohmylms/terminology", wrap( 1, 2 ) );
	hooks.addFilter( "i18n.gettext_with_context", "ohmylms/terminology", wrap( 1, 3, 2 ) );
	hooks.addFilter( "i18n.ngettext", "ohmylms/terminology", wrap( "n", 4 ) );
	hooks.addFilter( "i18n.ngettext_with_context", "ohmylms/terminology", wrap( "n", 5 ) );
} )( ' . $config . ' );',
			'after'
		);
	}
}
