<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Shared UI components for PHP-rendered markup.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Design;

defined( 'ABSPATH' ) || exit;

/**
 * Shared UI components.
 *
 * Each component has one stylesheet section in assets/css/oml-ui.css (tokens only), a React version in
 * assets/src/ui and a PHP helper here that emits identical class names, so server-rendered and React
 * screens look the same. See docs/DESIGN-TOKENS.md.
 */
final class Ui {
	const STYLE_HANDLE    = 'ohmylms-ui';
	const BUTTON_VARIANTS = array( 'primary', 'secondary', 'tertiary', 'danger' );
	const BUTTON_SIZES    = array( 'sm', 'md', 'lg' );
	const BADGE_TONES     = array( 'primary', 'success', 'warning', 'danger', 'info' );

	/** Register the stylesheet on admin and learner pages. */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ), 5 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register' ), 5 );
		// After FrontendAssets registers ohmylms-frontend (priority 10).
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'attach_legacy_buttons' ), 11 );
	}

	/** Draw the existing .ohmylms-button markup with the shared tokens; prints right after the learner stylesheet. */
	public static function attach_legacy_buttons() {
		$css = self::legacy_button_css();
		if ( '' !== $css && wp_style_is( 'ohmylms-frontend', 'registered' ) ) {
			wp_add_inline_style( 'ohmylms-frontend', $css );
		}
	}

	/**
	 * Contents of assets/css/oml-legacy-buttons.css, read once per request.
	 *
	 * @return string
	 */
	public static function legacy_button_css() {
		static $css = null;
		if ( null === $css ) {
			$path = OHMYLMS_DIR . '/assets/css/oml-legacy-buttons.css';
			$css  = is_readable( $path ) ? (string) file_get_contents( $path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local plugin file.
		}
		return $css;
	}

	/** Register the shared stylesheet; learner pages enqueue it through the helpers below. */
	public static function register() {
		$path = OHMYLMS_DIR . '/assets/css/oml-ui.css';
		wp_register_style( self::STYLE_HANDLE, plugins_url( 'assets/css/oml-ui.css', OHMYLMS_FILE ), array(), OHMYLMS_VERSION . '.' . substr( (string) hash_file( 'sha256', $path ), 0, 12 ) );
	}

	/** Enqueue the shared stylesheet, registering it first when a helper runs early. */
	public static function enqueue() {
		if ( ! wp_style_is( self::STYLE_HANDLE, 'registered' ) ) {
			self::register();
		}
		wp_enqueue_style( self::STYLE_HANDLE );
	}

	/**
	 * Class names for a button; the same strings as assets/src/ui/button.mjs.
	 *
	 * @param array $args variant, size, block, class.
	 * @return string
	 */
	public static function button_class( $args = array() ) {
		$variant = $args['variant'] ?? 'secondary';
		$size    = $args['size'] ?? 'md';
		$classes = array( 'oml-btn' );
		if ( 'secondary' !== $variant && in_array( $variant, self::BUTTON_VARIANTS, true ) ) {
			$classes[] = 'oml-btn-' . $variant;
		}
		if ( 'md' !== $size && in_array( $size, self::BUTTON_SIZES, true ) ) {
			$classes[] = 'oml-btn-' . $size;
		}
		if ( ! empty( $args['block'] ) ) {
			$classes[] = 'oml-btn-block';
		}
		if ( ! empty( $args['class'] ) ) {
			$classes[] = $args['class'];
		}
		return implode( ' ', $classes );
	}

	/**
	 * Render a button, or a link when href is given.
	 *
	 * @param string $label Button text (escaped here).
	 * @param array  $args  variant, size, block, class, href, type, disabled, attrs (name => value).
	 * @return string Escaped HTML.
	 */
	public static function button( $label, $args = array() ) {
		self::enqueue();
		$attrs    = array( 'class' => self::button_class( $args ) );
		$disabled = ! empty( $args['disabled'] );
		if ( ! empty( $args['href'] ) ) {
			$tag = 'a';
			if ( $disabled ) {
				$attrs['aria-disabled'] = 'true';
			} else {
				$attrs['href'] = $args['href'];
			}
		} else {
			$tag           = 'button';
			$attrs['type'] = $args['type'] ?? 'button';
			if ( $disabled ) {
				$attrs['disabled'] = 'disabled';
			}
		}
		foreach ( (array) ( $args['attrs'] ?? array() ) as $name => $value ) {
			$attrs[ sanitize_key( $name ) ] = $value;
		}
		$html = '';
		foreach ( $attrs as $name => $value ) {
			$html .= ' ' . $name . '="' . ( 'href' === $name ? esc_url( $value ) : esc_attr( $value ) ) . '"';
		}
		return '<' . $tag . $html . '>' . esc_html( $label ) . '</' . $tag . '>';
	}

	/**
	 * Class names for a card; the same strings as assets/src/ui/components.mjs.
	 *
	 * @param array $args flat, interactive, class.
	 * @return string
	 */
	public static function card_class( $args = array() ) {
		$classes = array( 'oml-card' );
		if ( ! empty( $args['flat'] ) ) {
			$classes[] = 'oml-card-flat';
		}
		if ( ! empty( $args['interactive'] ) ) {
			$classes[] = 'oml-card-interactive';
		}
		if ( ! empty( $args['class'] ) ) {
			$classes[] = $args['class'];
		}
		return implode( ' ', $classes );
	}

	/**
	 * Class names for a badge.
	 *
	 * @param array $args tone, class.
	 * @return string
	 */
	public static function badge_class( $args = array() ) {
		$tone    = $args['tone'] ?? 'neutral';
		$classes = array( 'oml-badge' );
		if ( in_array( $tone, self::BADGE_TONES, true ) ) {
			$classes[] = 'oml-badge-' . $tone;
		}
		if ( ! empty( $args['class'] ) ) {
			$classes[] = $args['class'];
		}
		return implode( ' ', $classes );
	}

	/**
	 * Class names for a progress bar.
	 *
	 * @param array $args tone, class.
	 * @return string
	 */
	public static function progress_class( $args = array() ) {
		$classes = array( 'oml-progress' );
		if ( 'success' === ( $args['tone'] ?? 'primary' ) ) {
			$classes[] = 'oml-progress-success';
		}
		if ( ! empty( $args['class'] ) ) {
			$classes[] = $args['class'];
		}
		return implode( ' ', $classes );
	}

	/**
	 * A progress value as a whole number from 0 to 100.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public static function clamp_percent( $value ) {
		return is_numeric( $value ) ? (int) min( 100, max( 0, round( (float) $value ) ) ) : 0;
	}

	/**
	 * Render a card around markup the caller has already escaped.
	 *
	 * @param string $content_html Escaped inner HTML.
	 * @param array  $args         flat, interactive, class, tag.
	 * @return string
	 */
	public static function card( $content_html, $args = array() ) {
		self::enqueue();
		$tag = $args['tag'] ?? 'div';
		$tag = in_array( $tag, array( 'div', 'section', 'article', 'li', 'aside' ), true ) ? $tag : 'div';
		return '<' . $tag . ' class="' . esc_attr( self::card_class( $args ) ) . '">' . $content_html . '</' . $tag . '>';
	}

	/**
	 * Render a badge.
	 *
	 * @param string $label Text (escaped here).
	 * @param array  $args  tone, class.
	 * @return string
	 */
	public static function badge( $label, $args = array() ) {
		self::enqueue();
		return '<span class="' . esc_attr( self::badge_class( $args ) ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Render a progress bar.
	 *
	 * @param mixed  $value 0 to 100.
	 * @param string $label Accessible name (escaped here).
	 * @param array  $args  tone, class.
	 * @return string
	 */
	public static function progress( $value, $label, $args = array() ) {
		self::enqueue();
		$percent = self::clamp_percent( $value );
		return '<div class="' . esc_attr( self::progress_class( $args ) ) . '" role="progressbar" aria-label="' . esc_attr( $label ) . '" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . $percent . '"><span class="oml-progress-bar" style="width:' . $percent . '%"></span></div>';
	}
}
