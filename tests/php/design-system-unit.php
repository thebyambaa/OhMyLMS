<?php
/**
 * Standalone checks for the backend design system: defaults, sanitizing, presets, contrast and CSS output.
 *
 * No WordPress or database needed.
 *
 * @package OhMyLMS
 */

define( 'ABSPATH', __DIR__ . '/' );
$options = array();
function __( $text ) { return $text; }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value ) { $GLOBALS['options'][ $key ] = $value; return true; }
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use OhMyLMS\Design\DesignSystem as DS;

$checks = 0;
function check( $condition, $message ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$checks;
}

// Defaults: a site that never saved the option gets the Khan Academy preset.
$d = DS::get();
check( 'khan' === $d['preset'] && '#1865f2' === $d['colors']['primary'] && 4 === $d['button_radius'] && 'comfortable' === $d['density'], 'fresh sites get the khan preset' );
check( '#d92916' === $d['colors']['danger'] && '#21242c' === $d['colors']['text'] && '#0b2149' === $d['colors']['heading'], 'khan palette comes from Wonder Blocks' );
check( 'khan' === DS::sanitize( 'garbage' )['preset'], 'non-array input falls back to khan' );

// Presets map back to their name; edits give custom.
$classic = DS::preset_values( 'classic' );
check( 'classic' === DS::sanitize( $classic )['preset'], 'classic values are recognised' );
check( '#6e42d3' === DS::sanitize( array( 'preset' => 'classic' ) )['colors']['primary'], 'preset name alone applies its values' );
$edited = DS::sanitize( array( 'preset' => 'khan', 'colors' => array( 'primary' => '#AABBCC' ) ) );
check( 'custom' === $edited['preset'] && '#aabbcc' === $edited['colors']['primary'], 'an edited value becomes custom and is lower-cased' );
check( '#112233' === DS::hex( '#123' ) || '#112233' === DS::hex( '#123' ), 'short hex expands' );
check( '' === DS::hex( 'red' ) && '' === DS::hex( '#12345' ) && '' === DS::hex( array() ) && '' === DS::hex( 'url(x)' ), 'non-hex values are rejected' );
$bad = DS::sanitize( array( 'colors' => array( 'primary' => 'javascript:alert(1)', 'bogus' => '#ffffff' ), 'density' => 'huge', 'button_radius' => 'x' ) );
check( '#1865f2' === $bad['colors']['primary'] && ! isset( $bad['colors']['bogus'] ) && 'comfortable' === $bad['density'] && 4 === $bad['button_radius'], 'invalid fields fall back to the preset' );

// Radius clamping and the pill value.
check( 0 === DS::radius( -5 ) && 24 === DS::radius( 40 ) && 999 === DS::radius( 999 ) && 999 === DS::radius( 5000 ) && 8 === DS::radius( '8' ) && null === DS::radius( 'abc' ), 'radius is clamped 0-24 with 999 as pill' );
check( array( 'square' => 0, 'slight' => 4, 'rounded' => 8, 'pill' => 999 ) === DS::RADIUS_PRESETS, 'named roundness presets' );
$r = DS::radii( 999 );
check( 999 === $r['button'] && 8 === $r['control'] && 12 === $r['card'], 'pill buttons keep controls and cards sane' );
$r = DS::radii( 0 );
check( 0 === $r['control'] && 0 === $r['card'], 'square stays square' );

// Validation reports fields.
check( array() === DS::validate( DS::preset_values( 'khan' ) ), 'preset values validate' );
$errors = DS::validate( array( 'preset' => 'neon', 'colors' => array( 'primary' => 'blue', 'nope' => '#fff' ), 'button_radius' => 'wide', 'density' => 'x' ) );
foreach ( array( 'preset', 'colors.primary', 'colors.nope', 'button_radius', 'density' ) as $field ) {
	check( isset( $errors[ $field ] ), "validation flags $field" );
}
check( isset( DS::validate( 'x' )['settings'] ), 'non-object input is flagged' );

// Contrast: white or black text is chosen automatically.
check( '#ffffff' === DS::text_on( '#1865f2' ) && DS::passes_aa( '#1865f2' ), 'Khan blue takes white text and passes AA' );
check( '#000000' === DS::text_on( '#ffb100' ) && DS::passes_aa( '#ffb100' ), 'gold takes black text' );
check( '#ffffff' === DS::text_on( '#6e42d3' ), 'classic purple takes white text' );
check( DS::white_passes_aa( '#1865f2' ) && ! DS::white_passes_aa( '#ffb100' ) && ! DS::white_passes_aa( '#808080' ), 'white-text AA warning applies only to light colors' );
check( abs( DS::contrast( '#000000', '#ffffff' ) - 21.0 ) < 0.01 && abs( DS::contrast( '#ffffff', '#ffffff' ) - 1.0 ) < 0.01, 'contrast ratio bounds' );

// Density and spacing grid.
check( array( 4, 8, 12, 16, 24, 32, 48, 64 ) === DS::spacing( 'comfortable' ), 'comfortable is the 4px grid' );
check( array( 3, 6, 9, 12, 18, 24, 36, 48 ) === DS::spacing( 'compact' ), 'compact scales down' );
check( 5 === DS::spacing( 'spacious' )[0] && 80 === DS::spacing( 'spacious' )[7], 'spacious scales up' );

// CSS output: only the expected custom properties, scoped, with safe values.
$css = DS::css( DS::preset_values( 'khan' ) );
check( 0 === strpos( $css, 'body.oml-ds{' ) && '}' === substr( $css, -1 ), 'variables are scoped to the plugin body class' );
foreach ( array( '--oml-primary:#1865f2;', '--oml-on-primary:#ffffff;', '--oml-radius-button:4px;', '--oml-space-1:4px;', '--oml-space-8:64px;', '--oml-radius-control:4px;', '--oml-radius-card:8px;', '--wp-admin-theme-color:#1865f2;' ) as $needle ) {
	check( false !== strpos( $css, $needle ), "css contains $needle" );
}
$body = substr( $css, strlen( 'body.oml-ds{' ), -1 );
foreach ( array_filter( explode( ';', $body ) ) as $declaration ) {
	check( 1 === preg_match( '/^--[a-z0-9-]+:(#[0-9a-f]{6}|[0-9]+px|[0-9]+, [0-9]+, [0-9]+|[0-9]+(\.[0-9]+)?|[0-9]+ms|0 [0-9]+px [0-9]+px rgba\([0-9]+, [0-9]+, [0-9]+, 0\.[0-9]+\))$/', $declaration ), "declaration is a plain variable: $declaration" );
}
$hostile = DS::css( array( 'colors' => array( 'primary' => '#fff;}body{display:none', 'link' => 'red;x:y' ), 'button_radius' => '4px;}x{', 'density' => '</style>' ) );
check( false === strpos( $hostile, 'display' ) && false === strpos( $hostile, '</style>' ) && false === strpos( $hostile, 'red' ) && substr_count( $hostile, '{' ) === 1, 'hostile values cannot inject CSS' );
$pill = DS::css( array_merge( DS::preset_values( 'khan' ), array( 'button_radius' => 999 ) ) );
check( false !== strpos( $pill, '--oml-radius-button:999px;' ), 'pill maps to 999px' );
check( false !== strpos( DS::css( array_merge( DS::preset_values( 'khan' ), array( 'density' => 'compact' ) ) ), '--oml-button-height-md:32px;' ), 'compact shrinks buttons' );

// Saving stores the cleaned value and reset restores Khan.
$saved = DS::save( array( 'colors' => array( 'primary' => '#ff0066' ), 'button_radius' => 999 ) );
check( 'custom' === $saved['preset'] && $saved === $options['ohmylms_design_system'], 'save stores the sanitized option' );
check( 'custom' === DS::get()['preset'] && 999 === DS::get()['button_radius'], 'saved values are read back' );
DS::save( DS::preset_values( 'khan' ) );
check( 'khan' === DS::get()['preset'], 'reset to khan' );

// Learner pages get the same --oml-* names, valued from the learner branding, not the admin Design system.
$options['ohmylms_primary_color_scheme'] = '#aa2200';
$learner                                 = \OhMyLMS\Design\Tokens::frontend_css();
check( false !== strpos( $learner, '--oml-primary:#aa2200;' ) && false !== strpos( $learner, '--ohmylms-primary-color:#aa2200;' ), 'learner --oml-primary follows the learner brand color' );
check( false === strpos( $learner, '--oml-primary:#1865f2;' ), 'learner tokens ignore the admin Design system' );
$admin_names = array_keys( DS::variables( DS::preset_values( 'classic' )['colors'], 4, 'comfortable' ) );
foreach ( $admin_names as $name ) {
	check( false !== strpos( $learner, '--' . $name . ':' ), "learner css defines --$name" );
}
unset( $options['ohmylms_primary_color_scheme'] );

// Dark theme: same token names as light, brand and status colors lightened to read on the dark card.
$light_names = array_keys( DS::variables( DS::preset_values( 'classic' )['colors'], 4, 'comfortable' ) );
foreach ( array( '#6e42d3', '#000d25', '#1865f2', '#ffb100', '#222222' ) as $brand ) {
	$palette         = DS::preset_values( 'classic' )['colors'];
	$palette['primary']   = $brand;
	$palette['secondary'] = $brand;
	$palette['link']      = $brand;
	$dark            = DS::dark_variables( $palette );
	check( DS::contrast( $dark['oml-primary'], DS::DARK['surface'] ) >= DS::DARK_MIN_CONTRAST, "dark primary for $brand reads on the dark card" );
	check( DS::contrast( $dark['oml-on-primary'], $dark['oml-primary'] ) >= 4.5, "dark on-primary for $brand reads on the primary" );
}
foreach ( array_keys( DS::dark_variables( DS::preset_values( 'classic' )['colors'] ) ) as $name ) {
	check( in_array( $name, $light_names, true ), "dark token --$name also exists in the light set" );
}
$dark = DS::dark_variables( DS::preset_values( 'classic' )['colors'] );
check( DS::DARK['surface'] === $dark['oml-surface'] && DS::DARK['border'] === $dark['oml-border'], 'dark neutrals are the fixed dark values' );
check( $dark['oml-primary'] === DS::for_dark( '#6e42d3' ), 'dark primary derives from the brand color' );
$options['ohmylms_primary_color_scheme'] = '#aa2200';
$learner_css                             = \OhMyLMS\Design\Tokens::frontend_css();
check( false !== strpos( $learner_css, '[data-theme="dark"],body:has(.oml-player[data-theme="dark"]){' ) && false !== strpos( $learner_css, '@media (prefers-color-scheme:dark){[data-theme="system"],body:has(.oml-player[data-theme="system"]){' ), 'learner css has dark and system rules' );
check( false !== strpos( $learner_css, '--oml-surface:' . DS::DARK['surface'] . ';' ), 'dark rule sets the dark surface' );
unset( $options['ohmylms_primary_color_scheme'] );

echo "Design system checks passed: $checks\n";
