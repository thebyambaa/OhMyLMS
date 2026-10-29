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
	 * Same names and derived shades as the admin Design system, so a shared component renders the same
	 * way in both places, but the values come from the learner branding options. Neutrals, status colors
	 * and shape use the Classic preset, which matches the learner theme.
	 *
	 * @return string Declarations without a selector.
	 */
	public static function frontend_oml_vars() {
		$preset              = DesignSystem::PRESETS['classic'];
		$colors              = $preset['colors'];
		$primary             = self::color( 'ohmylms_primary_color_scheme' );
		$colors['primary']   = $primary;
		$colors['secondary'] = $primary;
		$colors['link']      = $primary;
		$colors['heading']   = self::color( 'ohmylms_heading_color_scheme' );
		$colors['text']      = self::color( 'ohmylms_body_text_color_scheme' );
		$colors['success']   = self::color( 'ohmylms_body_progress_color_scheme' );
		$css                 = '';
		foreach ( DesignSystem::variables( $colors, $preset['button_radius'], $preset['density'] ) as $name => $value ) {
			$css .= '--' . $name . ':' . $value . ';';
		}
		return $css;
	}

	/** Learner-site variables, plus the font rule when a font is chosen. */
	public static function frontend_css() {
		$primary = self::color( 'ohmylms_primary_color_scheme' );
		$font    = self::font( 'ohmylms_font_family' );
		$css     = ':root{'
			. '--ohmylms-primary-color:' . $primary . ';'
			. '--ohmylms-primary-color-rgb:' . self::rgb( $primary ) . ';'
			. '--ohmylms-primary-hover-color:' . self::mix( $primary, '#000000', 0.15 ) . ';'
			. '--ohmylms-primary-soft-color:' . self::mix( $primary, '#ffffff', 0.9 ) . ';'
			. '--ohmylms-heading-color:' . self::color( 'ohmylms_heading_color_scheme' ) . ';'
			. '--ohmylms-body-text-color:' . self::color( 'ohmylms_body_text_color_scheme' ) . ';'
			. '--ohmylms-progressbar-color:' . self::color( 'ohmylms_body_progress_color_scheme' ) . ';'
			. '--ohmylms-outline-color:var(--ohmylms-primary-color);'
			. self::frontend_oml_vars()
			// Left undefined for "Theme font" so var(--ohmylms-font-family, fallback) keeps each page's own font.
			. ( 'inherit' !== $font ? '--ohmylms-font-family:' . self::stack( $font ) . ';' : '' )
			. '}';
		if ( 'inherit' !== $font ) {
			$css .= '.ohmylms-page,.ohmylms-schools,.ohmylms-practice,.ohmylms-inline-check,.ohmylms-skill-progress,.ohmylms-quiz{font-family:var(--ohmylms-font-family)}';
		}
		return $css . self::quiz_css();
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
		$primary = self::color( 'ohmylms_admin_primary_color' );
		$heading = self::color( 'ohmylms_admin_heading_color' );
		$muted   = self::color( 'ohmylms_admin_muted_color' );
		$font    = self::font( 'ohmylms_admin_font_family' );
		$css     = ':root{'
			. '--wp-admin-theme-color:' . $primary . ';'
			. '--wp-admin-theme-color--rgb:' . self::rgb( $primary ) . ';'
			. '--wp-admin-theme-color-darker-10:' . self::mix( $primary, '#000000', 0.1 ) . ';'
			. '--wp-admin-theme-color-darker-20:' . self::mix( $primary, '#000000', 0.2 ) . ';'
			. '--wp-components-color-accent:' . $primary . ';'
			. '--wp-components-color-accent-darker-10:' . self::mix( $primary, '#000000', 0.1 ) . ';'
			. '--wp-components-color-accent-darker-20:' . self::mix( $primary, '#000000', 0.2 ) . ';'
			. '--wp-components-color-foreground:' . $heading . ';'
			. '--wp-components-color-subdued:' . $muted . ';'
			. '--wp-components-button-color-secondary-text:' . $heading . ';'
			. '--ohmylms-primary-color:' . $primary . ';'
			. '--ohmylms-primary-color-rgb:' . self::rgb( $primary ) . ';'
			. '--ohmylms-primary-hover-color:' . self::mix( $primary, '#000000', 0.25 ) . ';'
			. '--ohmylms-primary-soft-color:' . self::mix( $primary, '#ffffff', 0.9 ) . ';'
			. '--ohmylms-admin-heading-color:' . $heading . ';'
			. '--ohmylms-admin-text-color:' . $heading . ';'
			. '--ohmylms-admin-muted-color:' . $muted . ';'
			. '--ohmylms-admin-surface:#ffffff;'
			. '--ohmylms-admin-subtle:#f4f5f7;'
			. '--ohmylms-admin-border:#ebedf0;'
			. '--ohmylms-admin-radius:7px;'
			. '--ohmylms-admin-gutter:40px;'
			. '--ohmylms-admin-font:' . self::stack( $font ) . ';'
			. '}';
		// Arco components read "r, g, b" triplets on a 1..9 scale (6 = base).
		$scale = array(
			1 => array( '#ffffff', 0.9 ),
			2 => array( '#ffffff', 0.8 ),
			3 => array( '#ffffff', 0.65 ),
			4 => array( '#ffffff', 0.45 ),
			5 => array( '#ffffff', 0.2 ),
			6 => array( '#ffffff', 0 ),
			7 => array( '#000000', 0.15 ),
			8 => array( '#000000', 0.3 ),
			9 => array( '#000000', 0.45 ),
		);
		$css  .= 'body{';
		foreach ( $scale as $step => [$toward, $amount] ) {
			$css .= '--primary-' . $step . ':' . self::rgb( self::mix( $primary, $toward, $amount ) ) . ';'; }
		$css .= '}';
		if ( 'system' !== $font ) {
			$css .= '#wpbody .ohmylms-admin-page :is(h1,h2,h3,h4,h5,h6,p,span,div,a,button,input,select,textarea,label,td,th,li,legend,strong,small)'
				. ':not(.dashicons):not([class*="dashicons-"]):not(.iconfont){font-family:var(--ohmylms-admin-font)}';
		}
		return $css;
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
		wp_enqueue_style( 'ohmylms-admin-ui', $ui_modules ? false : plugins_url( 'assets/css/admin-ui.css', OHMYLMS_FILE ), array_merge( array( 'ohmylms-main' ), $ui_modules ), $ui_version );
		$question_editor_version = OHMYLMS_VERSION . '.' . substr( hash_file( 'sha256', OHMYLMS_DIR . '/assets/css/question-editor.css' ), 0, 12 );
		wp_enqueue_style( 'ohmylms-question-editor', plugins_url( 'assets/css/question-editor.css', OHMYLMS_FILE ), array( 'ohmylms-admin-ui' ), $question_editor_version );
		foreach ( array( 'question-stage', 'quiz-design', 'quiz-overview', 'course-objectives', 'skill-matrix', 'course-editor-ux', 'course-outline-editor', 'subject-profiles', 'library-curriculum' ) as $file ) {
			wp_enqueue_style( 'ohmylms-' . $file, plugins_url( 'assets/css/' . $file . '.css', OHMYLMS_FILE ), array( 'ohmylms-question-editor' ), (string) filemtime( OHMYLMS_DIR . '/assets/css/' . $file . '.css' ) );
		}
		wp_add_inline_style( 'ohmylms-admin-ui', self::admin_css() );
		wp_add_inline_style( 'ohmylms-question-editor', self::quiz_css() );
		$url = self::font_url( self::font( 'ohmylms_admin_font_family' ) );
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
