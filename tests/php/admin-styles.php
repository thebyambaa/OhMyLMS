<?php
/**
 * Standalone checks for the WordPress CSS registration contract.
 *
 * @package OhMyLMS
 */

define( 'ABSPATH', __DIR__ );
define( 'OHMYLMS_DIR', dirname( __DIR__, 2 ) );
define( 'OHMYLMS_FILE', OHMYLMS_DIR . '/ohmylms.php' );
define( 'OHMYLMS_VERSION', 'test' );
$rtl = false;
/**
 * Ignore hook registration in this fixture.
 *
 * @param mixed ...$args Hook arguments.
 */
function add_action( ...$args ) {}
/**
 * Ignore filter registration in this fixture.
 *
 * @param mixed ...$args Filter arguments.
 */
function add_filter( ...$args ) {}
/**
 * Return the layout direction being tested.
 *
 * @return bool
 */
function is_rtl() {
	global $rtl;
	return $rtl;
}
/**
 * Resolve a fixture asset URL.
 *
 * @param string $path Asset path.
 * @return string
 */
function plugins_url( $path ) {
	return 'https://example.test/plugins/ohmylms/' . $path;
}
/**
 * Encode fixture attributes.
 *
 * @param string $value Attribute value.
 * @return string
 */
function esc_attr( $value ) {
	return htmlspecialchars( $value, ENT_QUOTES );
}
require OHMYLMS_DIR . '/includes/Abstracts/Assets.php';
require OHMYLMS_DIR . '/includes/Assets/AdminAssets.php';
require OHMYLMS_DIR . '/includes/Design/Tokens.php';
require OHMYLMS_DIR . '/includes/Design/DesignSystem.php';

// Match WordPress's registration semantics: enqueue with an empty URL cannot create a new alias.
$registered_styles = array();
/**
 * Record a style registration, including URL-free dependency aliases.
 *
 * @param string      $handle Style handle.
 * @param string|bool $src Source URL or false for an alias.
 * @param array       $deps Dependencies.
 * @param string|bool $version Asset version.
 */
function wp_register_style( $handle, $src, $deps = array(), $version = false ) {
	global $registered_styles;
	$registered_styles[ $handle ] = array(
		'src'     => $src,
		'deps'    => $deps,
		'version' => $version,
	);
}
/**
 * Emulate WordPress's enqueue registration and reject an unregistered UI alias.
 *
 * @throws RuntimeException When the UI alias has not been registered.
 *
 * @param string      $handle Style handle.
 * @param string|bool $src Source URL.
 * @param array       $deps Dependencies.
 * @param string|bool $version Asset version.
 */
function wp_enqueue_style( $handle, $src = '', $deps = array(), $version = false ) {
	global $registered_styles;
	if ( $src ) {
		wp_register_style( $handle, $src, $deps, $version );
	}
	if ( 'ohmylms-admin-ui' === $handle && ! isset( $registered_styles[ $handle ] ) ) {
		throw new RuntimeException( 'The module-only admin UI alias was not registered.' );
	}
}
/**
 * Return the plugin admin screen.
 *
 * @return object
 */
function get_current_screen() {
	return (object) array( 'id' => 'toplevel_page_ohmylms' );
}
/**
 * Return option defaults for the fixture.
 *
 * @param string $name Option key.
 * @param mixed  $default_value Fallback.
 * @return mixed
 */
function get_option( $name, $default_value = false ) {
	return $default_value;
}
/**
 * Ignore inline style output.
 *
 * @param mixed ...$args Style arguments.
 */
function wp_add_inline_style( ...$args ) {}
/**
 * Ignore inline script output.
 *
 * @param mixed ...$args Script arguments.
 */
function wp_add_inline_script( ...$args ) {}
/**
 * Encode fixture data without WordPress.
 *
 * @param mixed $value Data to encode.
 * @return string|false
 */
function wp_json_encode( $value ) {
	return json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Standalone replacement for the WordPress JSON wrapper.
}
/**
 * Return untranslated fixture text.
 *
 * @param string $text Fixture string.
 * @return string
 */
function __( $text ) {
	return $text;
}
$assets   = new OhMyLMS\Assets\AdminAssets();
$manifest = require OHMYLMS_DIR . '/assets/dist/css/admin/modules.php';
foreach ( array( false, true ) as $rtl ) {
	$styles = $assets->get_styles();
	foreach ( $manifest as $index => $module ) {
		$handle = 'ohmylms-module-' . $index;
		if ( $module['rtl'] && ! $rtl ) {
			if ( isset( $styles[ $handle ] ) ) {
				throw new RuntimeException( 'LTR loaded RTL styles.' );
			}
			continue;
		}
		if ( ! isset( $styles[ $handle ] ) ) {
			throw new RuntimeException( 'Missing style module.' );
		}
		$html   = '<link rel="stylesheet" href="https://example.test/style.css" media="all" />'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Literal test input for the deferral filter; never rendered.
		$result = $assets->defer_feature_style( $html, $handle );
		if ( 'shared' === $module['group'] ) {
			if ( $html !== $result ) {
				throw new RuntimeException( 'Shared stylesheet was deferred.' );
			}
		} elseif ( false !== strpos( $result, ' href=' ) || false === strpos( $result, 'data-oml-href=' ) ) {
			throw new RuntimeException( 'Feature stylesheet was not deferred.' );
		}
	}
	if ( false !== $styles['ohmylms-main']['src'] || empty( $styles['ohmylms-main']['deps'] ) ) {
		throw new RuntimeException( 'Legacy main handle must depend on ordered modules.' );
	}
	$ui_handles = OhMyLMS\Assets\AdminAssets::get_module_handles( 'ui' );
	if ( empty( $ui_handles ) || array_intersect( $ui_handles, $styles['ohmylms-main']['deps'] ) ) {
		throw new RuntimeException( 'Admin overrides must follow the main CSS as a separate layer.' );
	}
}
OhMyLMS\Design\Tokens::enqueue_admin();
if ( ! in_array( 'ohmylms-main', $registered_styles['ohmylms-admin-ui']['deps'], true ) || ! array_intersect( $ui_handles, $registered_styles['ohmylms-admin-ui']['deps'] ) ) {
	throw new RuntimeException( 'The admin UI alias lost its ordered style dependencies.' );
}
echo "Admin CSS registration, inline slots, deferral and RTL passed.\n";
