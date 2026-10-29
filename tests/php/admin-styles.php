<?php
/** Standalone checks for the WordPress CSS registration contract. */
define( 'ABSPATH', __DIR__ );
define( 'OHMYLMS_DIR', dirname( __DIR__, 2 ) );
define( 'OHMYLMS_FILE', OHMYLMS_DIR . '/ohmylms.php' );
define( 'OHMYLMS_VERSION', 'test' );
$rtl = false;
function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function is_rtl() {
	global $rtl;
	return $rtl;
}
function plugins_url( $path, $file ) {
	return 'https://example.test/plugins/ohmylms/' . $path;
}
function esc_attr( $value ) {
	return htmlspecialchars( $value, ENT_QUOTES );
}
require OHMYLMS_DIR . '/includes/Abstracts/Assets.php';
require OHMYLMS_DIR . '/includes/Assets/AdminAssets.php';
$assets = new OhMyLMS\Assets\AdminAssets();
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
		$html = '<link rel="stylesheet" href="https://example.test/style.css" media="all" />';
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
echo "Admin CSS registration, inline slots, deferral and RTL passed.\n";
