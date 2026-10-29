<?php
/**
 * Prints the design-language gallery: every shared component and variant, once with the learner tokens
 * (:root, learner branding defaults) and once with the admin Design system tokens (Khan preset).
 *
 * No WordPress needed: the few functions the Ui helpers call are stubbed. Used by
 * tests/browser/ui-gallery.spec.cjs; also handy to open by hand:
 *   php tests/browser/fixtures/ui-gallery.php > gallery.html
 *
 * @package OhMyLMS
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'OHMYLMS_DIR', dirname( __DIR__, 3 ) );
define( 'OHMYLMS_FILE', OHMYLMS_DIR . '/ohmylms.php' );
define( 'OHMYLMS_VERSION', 'gallery' );
function get_option( $key, $default = false ) { return $default; }
function __( $text ) { return $text; }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_url( $v ) { return $v; }
function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $k ) ); }
function wp_style_is() { return true; }
function wp_enqueue_style() {}
require OHMYLMS_DIR . '/vendor/autoload.php';

use OhMyLMS\Design\DesignSystem;
use OhMyLMS\Design\Tokens;
use OhMyLMS\Design\Ui;

/**
 * One gallery section's markup.
 *
 * @return string
 */
function oml_gallery_sections() {
	$buttons = '';
	foreach ( array( 'secondary', 'primary', 'tertiary', 'danger' ) as $variant ) {
		foreach ( array( 'sm', 'md', 'lg' ) as $size ) {
			$buttons .= Ui::button( $variant . ' ' . $size, array( 'variant' => $variant, 'size' => $size ) ) . ' ';
		}
	}
	$buttons .= Ui::button( 'disabled', array( 'variant' => 'primary', 'disabled' => true ) ) . ' ';
	$buttons .= Ui::button( 'link', array( 'variant' => 'primary', 'href' => '#' ) );

	$badges = Ui::badge( 'neutral' );
	foreach ( array( 'primary', 'success', 'warning', 'danger', 'info' ) as $tone ) {
		$badges .= ' ' . Ui::badge( $tone, array( 'tone' => $tone ) );
	}

	$progress = '';
	foreach ( array( 0, 35, 100 ) as $value ) {
		$progress .= '<p>' . Ui::progress( $value, $value . ' percent' ) . '</p>';
	}
	$progress .= '<p>' . Ui::progress( 70, 'Course progress', array( 'tone' => 'success' ) ) . '</p>';

	$cards = '<div class="gallery-row">'
		. Ui::card( '<h3 class="oml-card-title">Card</h3><p>Surface, border, radius and shadow.</p>' )
		. Ui::card( '<h3 class="oml-card-title">Flat</h3><p>No shadow.</p>', array( 'flat' => true ) )
		. Ui::card( '<h3 class="oml-card-title">Interactive</h3><p>Lifts on hover.</p>', array( 'interactive' => true ) )
		. '</div>';

	$type = '';
	foreach ( array( 'xs', 'sm', 'md', 'lg', 'xl', '2xl' ) as $size ) {
		$type .= '<div style="font-size:var(--oml-font-size-' . $size . ')">Font size ' . $size . '</div>';
	}

	$swatches = '';
	foreach ( array( 'primary', 'secondary', 'success', 'warning', 'danger', 'info', 'heading', 'text', 'muted', 'border', 'surface', 'subtle', 'background' ) as $name ) {
		$swatches .= '<span class="gallery-swatch"><i style="background:var(--oml-' . $name . ')"></i>' . $name . '</span>';
	}

	$sections = array(
		'buttons'  => $buttons,
		'badges'   => $badges,
		'progress' => $progress,
		'cards'    => $cards,
		'type'     => $type,
		'colors'   => $swatches,
	);
	$html     = '';
	foreach ( $sections as $name => $markup ) {
		$html .= '<section data-gallery="' . $name . '"><h2>' . ucfirst( $name ) . '</h2>' . $markup . '</section>';
	}
	return $html;
}

$learner_vars = ':root{' . Tokens::frontend_oml_vars() . '}';
$admin_vars   = str_replace( 'body.' . DesignSystem::BODY_CLASS, '.gallery-admin', DesignSystem::css( DesignSystem::preset_values( 'khan' ) ) );

echo '<!doctype html><html><head><meta charset="utf-8"><title>OhMyLMS design language</title><style>'
	. file_get_contents( OHMYLMS_DIR . '/assets/css/oml-ui.css' ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	. $learner_vars . $admin_vars
	. '*,*::before,*::after{animation:none!important;transition:none!important}'
	. 'body{font-family:Arial,Helvetica,sans-serif;font-size:14px;margin:0}'
	. '.gallery-context{background:var(--oml-background);color:var(--oml-text);padding:24px;width:720px}'
	. '.gallery-context h1{color:var(--oml-heading);font-size:20px;margin:0 0 16px}'
	. 'section{margin-bottom:24px;padding-bottom:8px}section h2{color:var(--oml-heading);font-size:13px;margin:0 0 8px;text-transform:uppercase}'
	. '.gallery-row{display:flex;gap:16px}.gallery-row>*{flex:1}'
	. '.gallery-swatch{display:inline-flex;align-items:center;gap:6px;margin:0 12px 8px 0}.gallery-swatch i{border:1px solid var(--oml-border);display:inline-block;height:20px;width:20px}'
	. '</style></head><body>';
foreach ( array( 'learner' => 'Learner (:root)', 'admin' => 'Admin (Khan preset)' ) as $context => $title ) {
	echo '<main class="gallery-context' . ( 'admin' === $context ? ' gallery-admin' : '' ) . '" data-context="' . $context . '"><h1>' . $title . '</h1>' . oml_gallery_sections() . '</main>';
}
echo '</body></html>';
