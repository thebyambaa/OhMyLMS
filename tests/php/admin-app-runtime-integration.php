<?php
/**
 * Admin app runtime selection integration test.
 *
 * Runs against a WordPress install (the Local site by default). Read-only.
 *
 * Usage (set OHMYLMS_ADMIN_RUNTIME_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/admin-app-runtime-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.Security.EscapeOutput, WordPress.Security.NonceVerification.Recommended

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_ADMIN_RUNTIME_TEST' ) ) {
	exit( "Set OHMYLMS_ADMIN_RUNTIME_TEST=1 to run the admin runtime tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Admin\AdminNavigation;
use OhMyLMS\Assets\AdminAppAssets;

$admins = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
wp_set_current_user( (int) $admins[0] );

$failures = array();
$checks   = 0;

/**
 * Runtime chosen for an admin address.
 *
 * @param string $page Page slug.
 * @param string $tab  Tab, or ''.
 * @return string
 */
function runtime_for( $page, $tab = '' ) {
	$_GET = array_filter(
		array(
			'page' => $page,
			'tab'  => $tab,
		)
	);
	return AdminAppAssets::runtime();
}

$expected = array(
	'dashboard'  => '',
	'accounts'   => '',
	'gradebook'  => '',
	'addons'     => '',
	'content'    => 'authoring',
	'bank'       => 'authoring',
	'curriculum' => 'authoring',
	'engagement' => 'media',
	'commerce'   => 'media',
	'settings'   => 'media',
);
foreach ( array_keys( AdminNavigation::groups() ) as $group ) {
	++$checks;
	if ( ! array_key_exists( $group, $expected ) ) {
		$failures[] = "the admin page group '$group' has no runtime decision; add it to this test and, if it needs one, to AdminAppAssets::RUNTIME_BY_GROUP";
		continue;
	}
	$actual = runtime_for( AdminNavigation::page_slug( $group ) );
	if ( $actual !== $expected[ $group ] ) {
		$failures[] = "group '$group' loads '" . $actual . "', expected '" . $expected[ $group ] . "'";
	}
}

++$checks;
if ( 'authoring' !== runtime_for( AdminNavigation::page_slug( 'accounts' ), 'ext-example' ) ) {
	$failures[] = 'an extension tab gets the authoring runtime on any page';
}

++$checks;
add_filter( 'ohmylms_admin_app_runtime', '__return_empty_string' );
if ( '' !== runtime_for( AdminNavigation::page_slug( 'bank' ) ) ) {
	$failures[] = 'the filter can turn the runtime off';
}
remove_filter( 'ohmylms_admin_app_runtime', '__return_empty_string' );

++$checks;
$bogus = static function () {
	return 'everything';
};
add_filter( 'ohmylms_admin_app_runtime', $bogus );
if ( '' !== runtime_for( AdminNavigation::page_slug( 'bank' ) ) ) {
	$failures[] = 'an unknown runtime name loads nothing';
}
remove_filter( 'ohmylms_admin_app_runtime', $bogus );

if ( $failures ) {
	echo 'FAILED: ', implode( "\nFAILED: ", $failures ), "\n";
	exit( 1 );
}
echo "OK: $checks checks\n";
exit( 0 );
