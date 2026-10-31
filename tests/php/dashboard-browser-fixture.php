<?php
/**
 * Dashboard fixtures for a live browser check (tests/php/support/dashboard-fixture.php).
 *
 *   OHMYLMS_DASHBOARD_TEST=1 php … tests/php/dashboard-browser-fixture.php create <tag>
 *   OHMYLMS_DASHBOARD_TEST=1 php … tests/php/dashboard-browser-fixture.php cleanup <tag>
 *
 * `create` prints the fixture ids and the sign-in names as JSON; each temporary user's password is
 * `<tag>-Pass-<name>-1!`. The tag must be `dashlive` followed by digits.
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.GlobalVariablesOverride

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_DASHBOARD_TEST' ) ) {
	exit( "Set OHMYLMS_DASHBOARD_TEST=1 to create or remove dashboard browser fixtures.\n" );
}
define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';
require __DIR__ . '/support/dashboard-fixture.php';

$action = $argv[1] ?? '';
$tag    = $argv[2] ?? '';
if ( ! preg_match( '/^dashlive[0-9]+$/', $tag ) ) {
	exit( "Usage: create|cleanup dashlive<digits>\n" );
}
add_filter( 'pre_wp_mail', '__return_true' );
if ( 'create' === $action ) {
	dashfx_delete( $tag );
	$fx = dashfx_create( $tag );
	\OhMyLMS\Dashboard\Cache::flush();
	echo wp_json_encode( $fx, JSON_PRETTY_PRINT ), "\n";
} elseif ( 'cleanup' === $action ) {
	dashfx_delete( $tag );
	echo "Dashboard browser fixtures {$tag} removed.\n";
} else {
	exit( "Usage: create|cleanup dashlive<digits>\n" );
}
