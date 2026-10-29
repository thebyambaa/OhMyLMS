<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Design tokens: one place that turns the Design settings into CSS custom properties.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Design;

defined( 'ABSPATH' ) || exit;

/**
 * Design tokens: one place that turns the Design settings into CSS custom properties.
 *
 * Learner pages get --ohmylms-* variables (colors, derived shades, font). The admin app gets the
 * same brand variables plus the WordPress components and Arco tokens it is built on, so native
 * screens and SDK pages follow the chosen colors and font. Stylesheets read the variables;
 * nothing else should hard-code brand colors or fonts.
 */
final class Tokens {
	/** Shared player and question-editor colors; separate from site branding. */
	const QUIZ_COLORS = array(
		'ohmylms_quiz_stage_color'               => '#461b49',
		'ohmylms_quiz_prompt_color'              => '#29152b',
		'ohmylms_quiz_canvas_color'              => '#e6e6e6',
		'ohmylms_quiz_toolbar_color'             => '#ffffff',
		'ohmylms_quiz_toolbar_text_color'        => '#333333',
		'ohmylms_quiz_accent_color'              => '#8854b7',
		'ohmylms_quiz_text_color'                => '#ffffff',
		'ohmylms_quiz_answer_1_color'            => '#2e72ad',
		'ohmylms_quiz_answer_2_color'            => '#2a9ca6',
		'ohmylms_quiz_answer_3_color'            => '#eca91d',
		'ohmylms_quiz_answer_4_color'            => '#cf5071',
		'ohmylms_quiz_correct_color'             => '#00c985',
		'ohmylms_quiz_player_stage_color'        => '#450565',
		'ohmylms_quiz_player_prompt_color'       => '#260932',
		'ohmylms_quiz_player_toolbar_color'      => '#0b0b0b',
		'ohmylms_quiz_player_toolbar_text_color' => '#ffffff',
		'ohmylms_quiz_player_answer_1_color'     => '#acb900',
		'ohmylms_quiz_player_answer_2_color'     => '#a65cef',
		'ohmylms_quiz_player_answer_3_color'     => '#ff7910',
		'ohmylms_quiz_player_answer_4_color'     => '#15c6b4',
	);
	/** Google Fonts with Cyrillic (Mongolian) coverage. */
	const FONTS        = array( 'Inter', 'Roboto', 'Open Sans', 'Noto Sans', 'Montserrat', 'Nunito', 'PT Sans', 'Fira Sans', 'Rubik' );
	const SYSTEM_STACK = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';

	/** Defaults, also used by the settings screen. */
	const DEFAULTS = array(
		'ohmylms_primary_color_scheme'       => '#6e42d3',
		'ohmylms_heading_color_scheme'       => '#000d25',
		'ohmylms_body_text_color_scheme'     => '#52525b',
		'ohmylms_body_progress_color_scheme' => '#35bd4c',
		'ohmylms_font_family'                => 'inherit',
		'ohmylms_admin_primary_color'        => '#6e42d3',
		'ohmylms_admin_heading_color'        => '#000d25',
		'ohmylms_admin_muted_color'          => '#7a8b9a',
		'ohmylms_admin_font_family'          => 'system',
	);

	/** Register palette sanitizers and design assets. */
	public static function init() {
		foreach ( self::QUIZ_COLORS as $option => $default ) {
			add_filter(
				'sanitize_option_' . $option,
				static function ( $value ) use ( $default ) {
					return is_string( $value ) && sanitize_hex_color( $value ) ? sanitize_hex_color( $value ) : $default;
				}
			);
		}
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_font' ), 5 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ), 20 );
	}

	/**
	 * Return a saved hex color, or the default.
	 *
	 * @param string $option Registered color option.
	 * @return string
	 */
	public static function color( $option ) {
		$aliases = array(
			'ohmylms_primary_color_scheme'       => 'primary',
			'ohmylms_heading_color_scheme'       => 'heading',
			'ohmylms_body_text_color_scheme'     => 'text',
			'ohmylms_body_progress_color_scheme' => 'success',
			'ohmylms_admin_primary_color'        => 'primary',
			'ohmylms_admin_heading_color'        => 'heading',
			'ohmylms_admin_muted_color'          => 'muted',
		);
		if ( isset( $aliases[ $option ] ) ) {
			return DesignSystem::get()['colors'][ $aliases[ $option ] ];
		}
		$value = get_option( $option, '' );
		$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';
		if ( preg_match( '/^#([0-9a-f]{3})$/', $value, $short ) ) {
			$value = '#' . $short[1][0] . $short[1][0] . $short[1][1] . $short[1][1] . $short[1][2] . $short[1][2];
		}
		return preg_match( '/^#[0-9a-f]{6}$/', $value ) ? $value : ( self::DEFAULTS[ $option ] ?? self::QUIZ_COLORS[ $option ] );
	}

	/**
	 * Emit the same bounded design tokens on admin and learner pages.
	 *
	 * @return string
	 */
	public static function quiz_css() {
		$css = ':root{';
		foreach ( self::QUIZ_COLORS as $option => $default ) {
			$name = str_replace( '_', '-', substr( $option, strlen( 'ohmylms_quiz_' ), -strlen( '_color' ) ) );
			$css .= '--ohmylms-quiz-' . $name . ':' . self::color( $option ) . ';';
		}
		return $css . '}';
	}

	/** A saved font choice: one of FONTS, 'system' or (learner site only) 'inherit'.
	 *
	 * @param mixed $option Option.
	 */
	public static function font( $option ) {
		$value   = get_option( $option, '' );
		$allowed = array_merge( self::FONTS, array( 'system' ), 'ohmylms_font_family' === $option ? array( 'inherit' ) : array() );
		return is_string( $value ) && in_array( $value, $allowed, true ) ? $value : self::DEFAULTS[ $option ];
	}

	/**
	 * Rgb for Tokens.
	 * "r, g, b" for rgba() use.
	 *
	 * @param mixed $hex Hex.
	 */
	public static function rgb( $hex ) {
		return implode( ', ', self::channels( $hex ) );
	}

	/**
	 * Channels for Tokens.
	 *
	 * @param mixed $hex Hex.
	 */
	private static function channels( $hex ) {
		return array( hexdec( substr( $hex, 1, 2 ) ), hexdec( substr( $hex, 3, 2 ) ), hexdec( substr( $hex, 5, 2 ) ) );
	}

	/** Mix $hex toward $with by $amount (0..1).
	 *
	 * @param mixed $hex Hex.
	 * @param mixed $with With.
	 * @param mixed $amount Amount.
	 */
	public static function mix( $hex, $with, $amount ) {
		$from = self::channels( $hex );
		$to   = self::channels( $with );
		$out  = '#';
		foreach ( array( 0, 1, 2 ) as $i ) {
			$out .= sprintf( '%02x', (int) round( $from[ $i ] + ( $to[ $i ] - $from[ $i ] ) * $amount ) ); }
		return $out;
	}

	/** CSS font-family value for a choice.
	 *
	 * @param mixed $font Font.
	 */
	public static function stack( $font ) {
		if ( 'inherit' === $font ) {
			return 'inherit'; }
		if ( 'system' === $font ) {
			return self::SYSTEM_STACK; }
		return '"' . $font . '", ' . self::SYSTEM_STACK;
	}

	/** Google Fonts stylesheet URL for a choice, or ''.
	 *
	 * @param mixed $font Font.
	 */
	public static function font_url( $font ) {
		if ( ! in_array( $font, self::FONTS, true ) ) {
			return ''; }
		return 'https://fonts.googleapis.com/css2?family=' . str_replace( ' ', '+', $font ) . ':wght@400;500;600;700&display=swap';
	}

	/**
	 * The --oml-* semantic tokens for learner pages.
	 *
	 * One saved Design system controls admin and learner colors, spacing and shape.
	 *
	 * @return string Declarations without a selector.
	 */
	public static function frontend_oml_vars() {
		$preset = DesignSystem::get();
		return self::declarations( DesignSystem::variables( self::frontend_colors(), $preset['button_radius'], $preset['density'] ) );
	}

	/**
	 * The shared palette, used unchanged by admin and learner pages.
	 *
	 * @return array Colors keyed like DesignSystem::COLOR_KEYS.
	 */
	public static function frontend_colors() {
		return DesignSystem::get()['colors'];
	}

	/**
	 * The dark theme for learner pages: the --oml-* overrides on a data-theme="dark" element, or on
	 * data-theme="system" when the visitor's device prefers dark.
	 *
	 * @return string CSS rules.
	 */
	public static function frontend_dark_css() {
		$vars = self::declarations( DesignSystem::dark_variables( self::frontend_colors() ) ) . self::legacy_aliases();
		return '[data-theme="dark"],body:has(.oml-player[data-theme="dark"]){' . $vars . '}'
			. '@media (prefers-color-scheme:dark){[data-theme="system"],body:has(.oml-player[data-theme="system"]){' . $vars . '}}';
	}

	/**
	 * Custom property declarations.
	 *
	 * @param array $vars Name without the leading dashes => value.
	 * @return string
	 */
	private static function declarations( $vars ) {
		$css = '';
		foreach ( $vars as $name => $value ) {
			$css .= '--' . $name . ':' . $value . ';';
		}
		return $css;
	}

	/**
	 * Preserve public legacy token names as aliases of the shared vocabulary.
	 *
	 * @return string CSS declarations, reapplied within dark theme scopes.
	 */
	private static function legacy_aliases() {
		return '--ohmylms-primary-color:var(--oml-primary);--ohmylms-primary-color-rgb:var(--oml-primary-rgb);'
			. '--ohmylms-primary-hover-color:var(--oml-primary-hover);--ohmylms-primary-soft-color:var(--oml-primary-soft);'
			. '--ohmylms-heading-color:var(--oml-heading);--ohmylms-body-text-color:var(--oml-text);'
			. '--ohmylms-progressbar-color:var(--oml-success);--ohmylms-outline-color:var(--oml-primary);';
	}

	/** Learner-site variables, plus the font rule when a font is chosen. */
	public static function frontend_css() {
		$font = self::font( 'ohmylms_font_family' );
		$css  = ':root{'
			. self::legacy_aliases()
			. '--ohmylms-font-family:var(--oml-font-family);'
			. self::frontend_oml_vars()
			// Left undefined for "Theme font" so var(--ohmylms-font-family, fallback) keeps each page's own font.
			. ( 'inherit' !== $font ? '--ohmylms-font-family:' . self::stack( $font ) . ';' : '' )
			. '}';
		$css .= '.ohmylms-page,.ohmylms-schools,.ohmylms-practice,.ohmylms-inline-check,.ohmylms-skill-progress,.ohmylms-quiz{font-family:var(--oml-font-family)}';
		return $css . self::frontend_dark_css() . self::quiz_css();
	}

	/**
	 * Enqueue frontend font for Tokens.
	 */
	public static function enqueue_frontend_font() {
		$url = self::font_url( self::font( 'ohmylms_font_family' ) );
		if ( $url ) {
			wp_enqueue_style( 'ohmylms-font', $url, array(), null ); }
	}

	/** Admin-app variables (brand, WordPress components, Arco scale) and font. */
	public static function admin_css() {
		return DesignSystem::css()
			. 'body.oml-ds{--ohmylms-admin-font:var(--oml-font-family);--ohmylms-admin-gutter:var(--oml-page-pad);}'
			. 'body.oml-ds .ohmylms-admin-page{font-family:var(--oml-font-family)}';
	}

	/**
	 * Is admin app screen for Tokens.
	 */
	public static function is_admin_app_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && 'toplevel_page_ohmylms' === $screen->id;
	}

	/**
	 * Enqueue admin for Tokens.
	 */
	public static function enqueue_admin() {
		if ( ! self::is_admin_app_screen() ) {
			return; }
		$ui_version = OHMYLMS_VERSION . '.' . substr( hash_file( 'sha256', OHMYLMS_DIR . '/assets/css/admin-ui.css' ), 0, 12 );
		$ui_modules = \OhMyLMS\Assets\AdminAssets::get_module_handles( 'ui' );
		// WordPress only registers a new handle through wp_enqueue_style() when its URL is non-empty.
		// Register the module-only alias explicitly so its dependencies and the design system can load.
		wp_register_style( 'ohmylms-admin-ui', $ui_modules ? false : plugins_url( 'assets/css/admin-ui.css', OHMYLMS_FILE ), array_merge( array( 'ohmylms-main' ), $ui_modules ), $ui_version );
		wp_enqueue_style( 'ohmylms-admin-ui' );
		$question_editor_version = OHMYLMS_VERSION . '.' . substr( hash_file( 'sha256', OHMYLMS_DIR . '/assets/css/question-editor.css' ), 0, 12 );
		wp_enqueue_style( 'ohmylms-question-editor', plugins_url( 'assets/css/question-editor.css', OHMYLMS_FILE ), array( 'ohmylms-admin-ui' ), $question_editor_version );
		foreach ( array( 'question-stage', 'quiz-design', 'quiz-overview', 'course-objectives', 'skill-matrix', 'course-editor-ux', 'course-outline-editor', 'subject-profiles', 'library-curriculum' ) as $file ) {
			wp_enqueue_style( 'ohmylms-' . $file, plugins_url( 'assets/css/' . $file . '.css', OHMYLMS_FILE ), array( 'ohmylms-question-editor' ), (string) filemtime( OHMYLMS_DIR . '/assets/css/' . $file . '.css' ) );
		}
		wp_add_inline_style( 'ohmylms-admin-ui', self::admin_css() );
		wp_add_inline_style( 'ohmylms-question-editor', self::quiz_css() );
		$url = self::font_url( self::font( 'ohmylms_font_family' ) );
		if ( $url ) {
			wp_enqueue_style( 'ohmylms-admin-font', $url, array(), null ); }
		// The Design settings screen previews font names in their own face.
		wp_add_inline_script(
			'wp-element',
			'window.ohmylmsDesign=' . wp_json_encode(
				array(
					'fonts'    => self::FONTS,
					'defaults' => self::DEFAULTS + self::QUIZ_COLORS,
				)
			) . ';',
			'before'
		);
	}
}
