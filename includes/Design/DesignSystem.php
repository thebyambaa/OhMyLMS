<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Backend design system: one set of colors, button roundness and spacing for every plugin admin screen.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Design;

defined( 'ABSPATH' ) || exit;

/**
 * Backend design system.
 *
 * The saved option holds a preset name, nine editable palette colors (plus four fixed neutrals), the
 * button radius and a density. Everything is sanitized on the way in and the generated CSS only ever
 * contains validated hex colors, bounded integers and whitelisted keywords. The variables are scoped to
 * `body.oml-ds`, a class added only on this plugin's admin screens, so other wp-admin pages keep their
 * own look. The shared stylesheet assets/css/admin-design-system.css reads these variables.
 */
final class DesignSystem {
	const OPTION         = 'ohmylms_design_system';
	const BODY_CLASS     = 'oml-ds';
	const DEFAULT_PRESET = 'khan';
	const PILL           = 999;
	const MAX_RADIUS     = 24;
	const DENSITIES      = array( 'compact', 'comfortable', 'spacious' );
	const SPACE_STEPS    = array( 1, 2, 3, 4, 6, 8, 12, 16 );

	/** Named roundness choices shown in the settings tab. */
	const RADIUS_PRESETS = array(
		'square'  => 0,
		'slight'  => 4,
		'rounded' => 8,
		'pill'    => self::PILL,
	);

	/** Color keys, in the order the settings tab shows them; the last four are not editable in the tab. */
	const COLOR_KEYS = array( 'primary', 'secondary', 'success', 'danger', 'warning', 'link', 'heading', 'text', 'muted', 'info', 'border', 'surface', 'background' );

	/**
	 * Preset values.
	 *
	 * Khan Academy: Wonder Blocks color tokens (blue #1865f2, red #d92916, green #00a60e, gold #ffb100,
	 * darkBlue #0b2149, offBlack #21242c, offWhite #f7f8fa; fadedOffBlack72 and fadedOffBlack16 mixed
	 * with white give muted text and borders; offBlack50 gives the neutral outline). See docs/DESIGN-SYSTEM.md.
	 */
	const PRESETS = array(
		'khan'    => array(
			'label'         => 'Khan Academy',
			'colors'        => array(
				'primary'    => '#1865f2',
				'secondary'  => '#909296',
				'success'    => '#00a60e',
				'danger'     => '#d92916',
				'warning'    => '#ffb100',
				'link'       => '#1865f2',
				'heading'    => '#0b2149',
				'text'       => '#21242c',
				'muted'      => '#5f6167',
				'info'       => '#1865f2',
				'border'     => '#dcdcdd',
				'surface'    => '#ffffff',
				'background' => '#f7f8fa',
			),
			'button_radius' => 4,
			'density'       => 'comfortable',
		),
		'classic' => array(
			'label'         => 'Classic',
			'colors'        => array(
				'primary'    => '#6e42d3',
				'secondary'  => '#6e42d3',
				'success'    => '#35bd4c',
				'danger'     => '#cc1818',
				'warning'    => '#b45309',
				'link'       => '#6e42d3',
				'heading'    => '#000d25',
				'text'       => '#000d25',
				'muted'      => '#7a8b9a',
				'info'       => '#2271b1',
				'border'     => '#dcdcde',
				'surface'    => '#ffffff',
				'background' => '#f0f0f1',
			),
			'button_radius' => 4,
			'density'       => 'comfortable',
		),
	);

	/** Button heights in pixels (small, medium, large) for each density. */
	const HEIGHTS = array(
		'compact'     => array( 24, 32, 40 ),
		'comfortable' => array( 28, 36, 44 ),
		'spacious'    => array( 32, 40, 48 ),
	);

	/** Multiplier applied to the 4px spacing grid for each density. */
	const SCALE = array(
		'compact'     => 0.75,
		'comfortable' => 1,
		'spacious'    => 1.25,
	);

	/** Register the option sanitizer, body class and stylesheet. */
	public static function init() {
		add_filter( 'sanitize_option_' . self::OPTION, array( __CLASS__, 'sanitize' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 30 );
	}

	/**
	 * Values of one preset.
	 *
	 * @param string $name Preset slug; unknown names give the default preset.
	 * @return array{preset:string,colors:array<string,string>,button_radius:int,density:string}
	 */
	public static function preset_values( $name ) {
		$name = is_string( $name ) && isset( self::PRESETS[ $name ] ) ? $name : self::DEFAULT_PRESET;
		$set  = self::PRESETS[ $name ];
		return array(
			'preset'        => $name,
			'colors'        => $set['colors'],
			'button_radius' => $set['button_radius'],
			'density'       => $set['density'],
		);
	}

	/**
	 * Hex color in lower-case six-digit form, or ''.
	 *
	 * @param mixed $value Candidate value.
	 * @return string
	 */
	public static function hex( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = strtolower( trim( $value ) );
		if ( preg_match( '/^#([0-9a-f]{3})$/', $value, $short ) ) {
			$value = '#' . $short[1][0] . $short[1][0] . $short[1][1] . $short[1][1] . $short[1][2] . $short[1][2];
		}
		return preg_match( '/^#[0-9a-f]{6}$/', $value ) ? $value : '';
	}

	/**
	 * Button radius in pixels: a whole number 0 to 24, or 999 for pill.
	 *
	 * @param mixed $value Candidate value.
	 * @return int|null Null when the value is not a number.
	 */
	public static function radius( $value ) {
		if ( ! is_numeric( $value ) ) {
			return null;
		}
		$value = (int) round( (float) $value );
		return $value >= self::PILL ? self::PILL : max( 0, min( self::MAX_RADIUS, $value ) );
	}

	/**
	 * Check a submitted value without changing it; used by the REST controller to show errors.
	 *
	 * @param mixed $input Submitted settings.
	 * @return array<string,string> Field name to message; empty when valid.
	 */
	public static function validate( $input ) {
		$errors = array();
		if ( ! is_array( $input ) ) {
			return array( 'settings' => __( 'The design system settings must be an object.', 'ohmylms' ) );
		}
		if ( isset( $input['preset'] ) && ( ! is_string( $input['preset'] ) || ! in_array( $input['preset'], array_merge( array_keys( self::PRESETS ), array( 'custom' ) ), true ) ) ) {
			$errors['preset'] = __( 'Choose Khan Academy, Classic or Custom.', 'ohmylms' );
		}
		if ( isset( $input['colors'] ) && ! is_array( $input['colors'] ) ) {
			$errors['colors'] = __( 'Colors must be an object.', 'ohmylms' );
		} elseif ( isset( $input['colors'] ) ) {
			foreach ( $input['colors'] as $key => $value ) {
				if ( ! in_array( $key, self::COLOR_KEYS, true ) ) {
					$errors[ 'colors.' . sanitize_key( $key ) ] = __( 'Unknown color.', 'ohmylms' );
				} elseif ( '' === self::hex( $value ) ) {
					$errors[ 'colors.' . $key ] = __( 'Use a color like #1865f2.', 'ohmylms' );
				}
			}
		}
		if ( isset( $input['button_radius'] ) && null === self::radius( $input['button_radius'] ) ) {
			$errors['button_radius'] = __( 'Button roundness must be a number from 0 to 24, or pill.', 'ohmylms' );
		}
		if ( isset( $input['density'] ) && ( ! is_string( $input['density'] ) || ! in_array( $input['density'], self::DENSITIES, true ) ) ) {
			$errors['density'] = __( 'Choose Compact, Comfortable or Spacious.', 'ohmylms' );
		}
		return $errors;
	}

	/**
	 * Clean a saved or submitted value. Unknown or invalid fields fall back to the chosen preset's values
	 * and the preset name is recomputed from the result.
	 *
	 * @param mixed $input Raw settings.
	 * @return array{preset:string,colors:array<string,string>,button_radius:int,density:string}
	 */
	public static function sanitize( $input ) {
		$input  = is_array( $input ) ? $input : array();
		$base   = self::preset_values( isset( $input['preset'] ) && is_string( $input['preset'] ) ? $input['preset'] : self::DEFAULT_PRESET );
		$colors = $base['colors'];
		if ( isset( $input['colors'] ) && is_array( $input['colors'] ) ) {
			foreach ( self::COLOR_KEYS as $key ) {
				$color = isset( $input['colors'][ $key ] ) ? self::hex( $input['colors'][ $key ] ) : '';
				if ( '' !== $color ) {
					$colors[ $key ] = $color;
				}
			}
		}
		$radius          = isset( $input['button_radius'] ) ? self::radius( $input['button_radius'] ) : null;
		$density         = isset( $input['density'] ) && is_string( $input['density'] ) && in_array( $input['density'], self::DENSITIES, true ) ? $input['density'] : $base['density'];
		$clean           = array(
			'preset'        => 'custom',
			'colors'        => $colors,
			'button_radius' => null === $radius ? $base['button_radius'] : $radius,
			'density'       => $density,
		);
		$clean['preset'] = self::matching_preset( $clean );
		return $clean;
	}

	/**
	 * Name of the preset whose values equal the given settings, else 'custom'.
	 *
	 * @param array $settings Cleaned settings.
	 * @return string
	 */
	public static function matching_preset( $settings ) {
		foreach ( array_keys( self::PRESETS ) as $name ) {
			$preset = self::preset_values( $name );
			if ( $preset['colors'] === $settings['colors'] && $preset['button_radius'] === $settings['button_radius'] && $preset['density'] === $settings['density'] ) {
				return $name;
			}
		}
		return 'custom';
	}

	/**
	 * The active settings. Sites that never saved the option get the Khan Academy preset.
	 *
	 * @return array{preset:string,colors:array<string,string>,button_radius:int,density:string}
	 */
	public static function get() {
		return self::sanitize( get_option( self::OPTION, array() ) );
	}

	/**
	 * Save settings after sanitizing them.
	 *
	 * @param array $input Raw settings.
	 * @return array The stored settings.
	 */
	public static function save( $input ) {
		$clean = self::sanitize( $input );
		update_option( self::OPTION, $clean, false );
		return $clean;
	}

	/**
	 * Relative luminance of a hex color (WCAG 2.x).
	 *
	 * @param string $hex Six-digit hex.
	 * @return float
	 */
	public static function luminance( $hex ) {
		$hex = self::hex( $hex );
		if ( '' === $hex ) {
			return 0.0;
		}
		$out = 0.0;
		foreach ( array( 1, 3, 5 ) as $i => $offset ) {
			$weight  = array( 0.2126, 0.7152, 0.0722 )[ $i ];
			$channel = hexdec( substr( $hex, $offset, 2 ) ) / 255;
			$out    += $weight * ( $channel <= 0.03928 ? $channel / 12.92 : pow( ( $channel + 0.055 ) / 1.055, 2.4 ) );
		}
		return $out;
	}

	/**
	 * WCAG contrast ratio between two colors.
	 *
	 * @param string $a First hex color.
	 * @param string $b Second hex color.
	 * @return float 1 to 21.
	 */
	public static function contrast( $a, $b ) {
		$la = self::luminance( $a );
		$lb = self::luminance( $b );
		return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
	}

	/**
	 * White or black, whichever reads better on the given background.
	 *
	 * @param string $background Hex color.
	 * @return string '#ffffff' or '#000000'.
	 */
	public static function text_on( $background ) {
		return self::contrast( $background, '#ffffff' ) >= self::contrast( $background, '#000000' ) ? '#ffffff' : '#000000';
	}

	/**
	 * Whether the automatically chosen text color reaches WCAG AA (4.5:1) on the background.
	 *
	 * @param string $background Hex color.
	 * @return bool
	 */
	public static function passes_aa( $background ) {
		return self::contrast( $background, self::text_on( $background ) ) >= 4.5;
	}

	/**
	 * Whether white text reaches WCAG AA (4.5:1) on the background; false means the system uses black text.
	 *
	 * @param string $background Hex color.
	 * @return bool
	 */
	public static function white_passes_aa( $background ) {
		return self::contrast( $background, '#ffffff' ) >= 4.5;
	}

	/**
	 * Pixel values for the spacing grid at a density. Multiples of 4 stay whole numbers.
	 *
	 * @param string $density Density slug.
	 * @return int[] Steps 1 to 8 (index 0 is --oml-space-1).
	 */
	public static function spacing( $density ) {
		$scale = self::SCALE[ $density ] ?? 1;
		$out   = array();
		foreach ( array( 4, 8, 12, 16, 24, 32, 48, 64 ) as $base ) {
			$out[] = (int) round( $base * $scale );
		}
		return $out;
	}

	/**
	 * Control and card radii derived from the button radius.
	 *
	 * @param int $button_radius Button radius.
	 * @return array{button:int,control:int,card:int}
	 */
	public static function radii( $button_radius ) {
		$control = self::PILL === $button_radius ? 8 : min( $button_radius, 8 );
		return array(
			'button'  => $button_radius,
			'control' => $control,
			'card'    => 0 === $control ? 0 : $control + 4,
		);
	}

	/** Fixed dark neutrals: page, card, text, muted text and borders for the dark theme. */
	const DARK = array(
		'background' => '#252531',
		'surface'    => '#151521',
		'heading'    => '#f3f3f7',
		'text'       => '#f3f3f7',
		'muted'      => '#b7b8c5',
		'border'     => '#4a4c53',
	);

	/** Minimum contrast of a brand or status color against the dark card (WCAG AA for text). */
	const DARK_MIN_CONTRAST = 4.5;

	/**
	 * Lighten a color until it reads on the dark card.
	 *
	 * @param string $hex Hex color.
	 * @return string Hex color with at least DARK_MIN_CONTRAST against DARK['surface'].
	 */
	public static function for_dark( $hex ) {
		for ( $step = 0; $step <= 10; $step++ ) {
			$candidate = Tokens::mix( $hex, '#ffffff', $step / 10 );
			if ( self::contrast( $candidate, self::DARK['surface'] ) >= self::DARK_MIN_CONTRAST ) {
				return $candidate;
			}
		}
		return '#ffffff';
	}

	/**
	 * The dark theme's --oml-* overrides: neutrals are fixed, brand and status colors are lightened so
	 * they stay readable, soft tints are mixed into the dark card. Same names as variables(), so
	 * components need no dark-specific rules.
	 *
	 * @param array $c Validated colors (COLOR_KEYS).
	 * @return array<string,string> Variable name (without the leading dashes) => value.
	 */
	public static function dark_variables( $c ) {
		$dark    = self::DARK;
		$primary = self::for_dark( $c['primary'] );
		$second  = self::for_dark( $c['secondary'] );
		$link    = self::for_dark( $c['link'] );
		$vars    = array(
			'oml-background'      => $dark['background'],
			'oml-surface'         => $dark['surface'],
			'oml-subtle'          => Tokens::mix( $dark['surface'], '#ffffff', 0.06 ),
			'oml-heading'         => $dark['heading'],
			'oml-text'            => $dark['text'],
			'oml-muted'           => $dark['muted'],
			'oml-border'          => $dark['border'],
			'oml-primary'         => $primary,
			'oml-primary-hover'   => Tokens::mix( $primary, '#ffffff', 0.15 ),
			'oml-primary-active'  => Tokens::mix( $primary, '#ffffff', 0.3 ),
			'oml-primary-soft'    => Tokens::mix( $dark['surface'], $primary, 0.2 ),
			'oml-primary-rgb'     => Tokens::rgb( $primary ),
			'oml-on-primary'      => self::text_on( $primary ),
			'oml-secondary'       => $second,
			'oml-secondary-hover' => Tokens::mix( $second, '#ffffff', 0.15 ),
			'oml-link'            => $link,
			'oml-link-hover'      => Tokens::mix( $link, '#ffffff', 0.2 ),
			'oml-focus'           => $primary,
			'oml-shade'           => self::DARK['surface'],
			'oml-color-scheme'    => 'dark',
			'oml-shadow-sm'       => '0 1px 2px rgba(0, 0, 0, 0.4)',
			'oml-shadow-md'       => '0 4px 12px rgba(0, 0, 0, 0.5)',
		);
		foreach ( array( 'success', 'warning', 'danger', 'info' ) as $status ) {
			$color                              = self::for_dark( $c[ $status ] );
			$vars[ 'oml-' . $status ]           = $color;
			$vars[ 'oml-' . $status . '-soft' ] = Tokens::mix( $dark['surface'], $color, 0.2 );
		}
		$vars['oml-danger-hover']  = Tokens::mix( $vars['oml-danger'], '#ffffff', 0.15 );
		$vars['oml-danger-active'] = Tokens::mix( $vars['oml-danger'], '#ffffff', 0.3 );
		$vars['oml-on-danger']     = self::text_on( $vars['oml-danger'] );
		return $vars;
	}

	/**
	 * The --oml-* semantic tokens: the one vocabulary every component reads, in admin and on learner pages.
	 *
	 * Admin feeds it from the Design system settings; learner pages feed it from the learner branding
	 * options (Tokens::frontend_css), so the names and derived shades are identical but each side keeps
	 * its own values.
	 *
	 * @param array  $c             Validated colors (COLOR_KEYS).
	 * @param int    $button_radius Button radius in pixels.
	 * @param string $density       One of DENSITIES.
	 * @return array<string,string> Variable name (without the leading dashes) => value.
	 */
	public static function variables( $c, $button_radius, $density ) {
		$primary = $c['primary'];
		$on      = self::text_on( $primary );
		$space   = self::spacing( $density );
		$radii   = self::radii( $button_radius );
		$heights = self::HEIGHTS[ $density ];
		$focus   = self::contrast( $primary, $c['surface'] ) >= 3 ? $primary : $c['heading'];
		$vars    = array(
			'oml-primary'          => $primary,
			'oml-primary-hover'    => Tokens::mix( $primary, '#000000', 0.15 ),
			'oml-primary-active'   => Tokens::mix( $primary, '#000000', 0.3 ),
			'oml-primary-soft'     => Tokens::mix( $primary, '#ffffff', 0.9 ),
			'oml-primary-rgb'      => Tokens::rgb( $primary ),
			'oml-on-primary'       => $on,
			'oml-secondary'        => $c['secondary'],
			'oml-secondary-hover'  => Tokens::mix( $c['secondary'], '#000000', 0.15 ),
			'oml-success'          => $c['success'],
			'oml-success-soft'     => Tokens::mix( $c['success'], '#ffffff', 0.9 ),
			'oml-warning'          => $c['warning'],
			'oml-warning-soft'     => Tokens::mix( $c['warning'], '#ffffff', 0.88 ),
			'oml-danger'           => $c['danger'],
			'oml-danger-hover'     => Tokens::mix( $c['danger'], '#000000', 0.15 ),
			'oml-danger-active'    => Tokens::mix( $c['danger'], '#000000', 0.3 ),
			'oml-danger-soft'      => Tokens::mix( $c['danger'], '#ffffff', 0.92 ),
			'oml-on-danger'        => self::text_on( $c['danger'] ),
			'oml-info'             => $c['info'],
			'oml-info-soft'        => Tokens::mix( $c['info'], '#ffffff', 0.92 ),
			'oml-link'             => $c['link'],
			'oml-link-hover'       => Tokens::mix( $c['link'], '#000000', 0.2 ),
			'oml-heading'          => $c['heading'],
			'oml-text'             => $c['text'],
			'oml-muted'            => $c['muted'],
			'oml-border'           => $c['border'],
			'oml-surface'          => $c['surface'],
			'oml-subtle'           => Tokens::mix( $c['background'], '#ffffff', 0.5 ),
			'oml-background'       => $c['background'],
			'oml-focus'            => $focus,
			'oml-shade'            => $c['heading'],
			'oml-color-scheme'     => 'light',
			'oml-radius-button'    => $radii['button'] . 'px',
			'oml-radius-control'   => $radii['control'] . 'px',
			'oml-radius-card'      => $radii['card'] . 'px',
			'oml-button-height-sm' => $heights[0] . 'px',
			'oml-button-height-md' => $heights[1] . 'px',
			'oml-button-height-lg' => $heights[2] . 'px',
			'oml-button-pad-sm'    => $space[1] . 'px',
			'oml-button-pad-md'    => $space[3] . 'px',
			'oml-button-pad-lg'    => $space[4] . 'px',
			'oml-page-pad'         => $space[4] . 'px',
		);
		foreach ( $space as $i => $px ) {
			$vars[ 'oml-space-' . ( $i + 1 ) ] = $px . 'px';
		}
		$shadow = Tokens::rgb( $c['heading'] );
		return $vars + array(
			'oml-font-size-xs'   => '12px',
			'oml-font-size-sm'   => '13px',
			'oml-font-size-md'   => '14px',
			'oml-font-size-lg'   => '16px',
			'oml-font-size-xl'   => '20px',
			'oml-font-size-2xl'  => '24px',
			'oml-line-tight'     => '1.2',
			'oml-line-normal'    => '1.5',
			'oml-weight-regular' => '400',
			'oml-weight-medium'  => '500',
			'oml-weight-strong'  => '600',
			'oml-shadow-sm'      => '0 1px 2px rgba(' . $shadow . ', 0.08)',
			'oml-shadow-md'      => '0 4px 12px rgba(' . $shadow . ', 0.12)',
			'oml-motion-fast'    => '120ms',
			'oml-motion-base'    => '200ms',
			'oml-z-dropdown'     => '100',
			'oml-z-modal'        => '1000',
			'oml-z-toast'        => '1100',
		);
	}

	/**
	 * CSS custom properties for the settings, scoped to the plugin's admin screens.
	 *
	 * @param array|null $settings Cleaned settings; the saved ones when null.
	 * @return string
	 */
	public static function css( $settings = null ) {
		$settings = is_array( $settings ) ? self::sanitize( $settings ) : self::get();
		$c        = $settings['colors'];
		$primary  = $c['primary'];
		$on       = self::text_on( $primary );
		$radii    = self::radii( $settings['button_radius'] );
		$vars     = self::variables( $c, $settings['button_radius'], $settings['density'] );
		// The existing admin stylesheets and WordPress/Arco components read these names.
		$vars += array(
			'wp-admin-theme-color'                      => $primary,
			'wp-admin-theme-color--rgb'                 => Tokens::rgb( $primary ),
			'wp-admin-theme-color-darker-10'            => Tokens::mix( $primary, '#000000', 0.15 ),
			'wp-admin-theme-color-darker-20'            => Tokens::mix( $primary, '#000000', 0.3 ),
			'wp-components-color-accent'                => $primary,
			'wp-components-color-accent-darker-10'      => Tokens::mix( $primary, '#000000', 0.15 ),
			'wp-components-color-accent-darker-20'      => Tokens::mix( $primary, '#000000', 0.3 ),
			'wp-components-color-accent-inverted'       => $on,
			'wp-components-color-foreground'            => $c['heading'],
			'wp-components-color-subdued'               => $c['muted'],
			'wp-components-button-color-secondary-text' => $primary,
			'ohmylms-primary-color'                     => $primary,
			'ohmylms-primary-color-rgb'                 => Tokens::rgb( $primary ),
			'ohmylms-primary-hover-color'               => Tokens::mix( $primary, '#000000', 0.15 ),
			'ohmylms-primary-soft-color'                => Tokens::mix( $primary, '#ffffff', 0.9 ),
			'ohmylms-admin-heading-color'               => $c['heading'],
			'ohmylms-admin-text-color'                  => $c['text'],
			'ohmylms-admin-muted-color'                 => $c['muted'],
			'ohmylms-admin-surface'                     => $c['surface'],
			'ohmylms-admin-subtle'                      => Tokens::mix( $c['background'], '#ffffff', 0.5 ),
			'ohmylms-admin-border'                      => $c['border'],
			'ohmylms-admin-radius'                      => $radii['card'] . 'px',
		);
		$css   = 'body.' . self::BODY_CLASS . '{';
		foreach ( $vars as $name => $value ) {
			$css .= '--' . $name . ':' . $value . ';';
		}
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
		foreach ( $scale as $step => $mixer ) {
			$css .= '--primary-' . $step . ':' . Tokens::rgb( Tokens::mix( $primary, $mixer[0], $mixer[1] ) ) . ';';
		}
		return $css . '}';
	}

	/**
	 * Whether the current admin screen belongs to this plugin.
	 *
	 * @return bool
	 */
	public static function is_plugin_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return false;
		}
		if ( false !== strpos( (string) $screen->id, 'ohmylms' ) ) {
			return true;
		}
		$types = array();
		foreach ( array( 'OHMYLMS_COURSE_CPT', 'OHMYLMS_CHAPTER_CPT', 'OHMYLMS_LESSON_CPT', 'OHMYLMS_QUIZ_CPT', 'OHMYLMS_QUESTION_CPT', 'OHMYLMS_MEMBERSHIP_CPT', 'OHMYLMS_ASSIGNMENT_CPT', 'OHMYLMS_CERTIFICATE_CPT' ) as $constant ) {
			if ( defined( $constant ) ) {
				$types[] = constant( $constant );
			}
		}
		return in_array( (string) $screen->post_type, $types, true );
	}

	/**
	 * Add the scoping class on this plugin's admin screens.
	 *
	 * @param string $classes Space-separated body classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		return self::is_plugin_screen() ? trim( $classes . ' ' . self::BODY_CLASS ) : $classes;
	}

	/** Enqueue the shared stylesheet and its variables on the plugin's admin screens. */
	public static function enqueue() {
		if ( ! self::is_plugin_screen() ) {
			return;
		}
		$path = OHMYLMS_DIR . '/assets/css/admin-design-system.css';
		// Load after the other plugin and WordPress component styles so the shared rules win ties.
		$deps = array();
		foreach ( array( 'wp-components', Ui::STYLE_HANDLE, 'ohmylms-main', 'ohmylms-tailwind', 'ohmylms-admin-ui', 'ohmylms-question-editor', 'ohmylms-skill-studio' ) as $handle ) {
			if ( wp_style_is( $handle, 'registered' ) ) {
				$deps[] = $handle;
			}
		}
		wp_enqueue_style( 'ohmylms-admin-design-system', plugins_url( 'assets/css/admin-design-system.css', OHMYLMS_FILE ), $deps, OHMYLMS_VERSION . '.' . substr( (string) hash_file( 'sha256', $path ), 0, 12 ) );
		wp_add_inline_style( 'ohmylms-admin-design-system', self::css() );
		wp_add_inline_script(
			'wp-element',
			'window.ohmylmsDesignSystem=' . wp_json_encode(
				array(
					'presets' => array_map(
						static function ( $name ) {
							return array_merge( self::preset_values( $name ), array( 'label' => self::PRESETS[ $name ]['label'] ) );
						},
						array_keys( self::PRESETS )
					),
				)
			) . ';',
			'before'
		);
	}
}
