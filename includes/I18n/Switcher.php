<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Learner language switcher (English / Монгол).
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\I18n;

use OhMyLMS\Extensions\Interactivity;

defined( 'ABSPATH' ) || exit;

/**
 * Server-rendered switcher: a GET form of `ohmylms_locale` buttons, so it works without JavaScript
 * ({@see InterfaceLanguage::redirect_query()}). The `ohmylms/language` module posts the choice to
 * `POST ohmylms/v1/learner/locale` instead and reloads the page. Shown in the learner header and
 * profile settings tab, the Student Hub (menu, Settings › Account, sign-in) and wherever
 * `[ohmylms_language_switcher]` is placed.
 */
final class Switcher {

	/** Option of Settings › General › "Language switcher" (`yes` / `no`, default on). */
	const OPTION = 'ohmylms_language_switcher';

	/** Shortcode tag. */
	const SHORTCODE = 'ohmylms_language_switcher';

	/** Stylesheet handle. */
	const STYLE = 'ohmylms-language-switcher';

	/** Interactivity module. */
	const MODULE = 'ohmylms/language';

	/** Register hooks. */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/** Register the shortcode. */
	public static function register() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Whether the switcher is shown (Settings › General).
	 *
	 * @return bool
	 */
	public static function enabled() {
		return 'no' !== get_option( self::OPTION, 'yes' );
	}

	/**
	 * `[ohmylms_language_switcher label="…" variant="inline|compact"]`.
	 *
	 * @param array|string $attributes Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $attributes ) {
		$attributes = shortcode_atts(
			array(
				'label'   => '',
				'variant' => 'inline',
			),
			is_array( $attributes ) ? $attributes : array(),
			self::SHORTCODE
		);
		return self::render(
			array(
				'label'   => sanitize_text_field( $attributes['label'] ),
				'variant' => sanitize_key( $attributes['variant'] ),
			)
		);
	}

	/**
	 * The switcher's HTML; empty when it is turned off or only one language is offered.
	 *
	 * @param array $args {
	 *     Optional.
	 *
	 *     @type string $variant `inline` (visible label) or `compact` (label for screen readers only).
	 *     @type string $label   Group label. Default "Language".
	 * }
	 * @return string
	 */
	public static function render( array $args = array() ) {
		$locales = InterfaceLanguage::locales();
		if ( ! self::enabled() || count( $locales ) < 2 ) {
			return '';
		}
		$variant     = isset( $args['variant'] ) && 'compact' === $args['variant'] ? 'compact' : 'inline';
		$label       = isset( $args['label'] ) && is_string( $args['label'] ) && '' !== $args['label'] ? $args['label'] : __( 'Language', 'ohmylms' );
		$current     = InterfaceLanguage::active_locale();
		$id          = wp_unique_id( 'ohmylms-language-' );
		$url         = InterfaceLanguage::request_url();
		$action      = strtok( $url, '?#' );
		$label_class = 'ohmylms-language-switcher-label' . ( 'compact' === $variant ? ' ohmylms-visually-hidden' : '' );
		$context     = array(
			'current' => $current,
			'busy'    => false,
		);

		$html  = '<form class="ohmylms-language-switcher is-' . esc_attr( $variant ) . '" method="get" action="' . esc_url( $action ) . '"';
		$html .= ' data-wp-interactive="' . esc_attr( self::MODULE ) . '" data-wp-context="' . esc_attr( (string) wp_json_encode( $context ) ) . '"';
		$html .= ' data-wp-on--submit="actions.choose" data-wp-on-window--pageshow="actions.reset" data-wp-bind--aria-busy="context.busy">';
		$html .= '<span class="' . esc_attr( $label_class ) . '" id="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</span>';
		$html .= '<span class="ohmylms-language-switcher-options" role="group" aria-labelledby="' . esc_attr( $id ) . '">';
		foreach ( $locales as $locale => $name ) {
			$tag   = str_replace( '_', '-', $locale );
			$html .= '<button type="submit" class="ohmylms-language-switcher-option" name="' . esc_attr( InterfaceLanguage::QUERY_VAR ) . '" value="' . esc_attr( $locale ) . '"';
			$html .= ' lang="' . esc_attr( $tag ) . '" aria-pressed="' . ( $locale === $current ? 'true' : 'false' ) . '" data-wp-bind--disabled="context.busy">';
			$html .= esc_html( $name ) . '</button>';
		}
		$html .= '</span>';
		$html .= self::hidden_fields( $url );
		if ( is_user_logged_in() ) {
			$html .= '<input type="hidden" name="' . esc_attr( InterfaceLanguage::NONCE_FIELD ) . '" value="' . esc_attr( wp_create_nonce( InterfaceLanguage::NONCE_ACTION ) ) . '">';
		}
		$html .= '</form>';

		self::enqueue();
		return $html;
	}

	/**
	 * Hidden inputs that carry the current query string through the GET form (a GET form replaces
	 * the query of its action), without the switcher's own parameters.
	 *
	 * @param string $url Current URL.
	 * @return string
	 */
	private static function hidden_fields( $url ) {
		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
		if ( '' === $query ) {
			return '';
		}
		$params = array();
		wp_parse_str( $query, $params );
		unset( $params[ InterfaceLanguage::QUERY_VAR ], $params[ InterfaceLanguage::NONCE_FIELD ] );
		$html = '';
		foreach ( self::flatten( $params ) as $pair ) {
			$html .= '<input type="hidden" name="' . esc_attr( $pair[0] ) . '" value="' . esc_attr( $pair[1] ) . '">';
		}
		return $html;
	}

	/**
	 * Name/value pairs of nested query parameters (`a[b][]`), two levels deep.
	 *
	 * @param array  $params Parameters.
	 * @param string $prefix Name prefix.
	 * @param int    $depth  Nesting level.
	 * @return array<int, array{0: string, 1: string}>
	 */
	private static function flatten( array $params, $prefix = '', $depth = 0 ) {
		$pairs = array();
		foreach ( $params as $key => $value ) {
			$name = '' === $prefix ? (string) $key : $prefix . '[' . ( is_int( $key ) ? '' : $key ) . ']';
			if ( is_array( $value ) ) {
				if ( $depth < 2 ) {
					$pairs = array_merge( $pairs, self::flatten( $value, $name, $depth + 1 ) );
				}
			} elseif ( is_scalar( $value ) ) {
				$pairs[] = array( $name, (string) $value );
			}
		}
		return $pairs;
	}

	/** Load the module and the stylesheet. */
	public static function enqueue() {
		Interactivity::enqueue( self::MODULE );
		wp_enqueue_style(
			self::STYLE,
			plugins_url( 'assets/css/language-switcher.css', OHMYLMS_FILE ),
			array(),
			(string) filemtime( OHMYLMS_DIR . '/assets/css/language-switcher.css' )
		);
	}
}
