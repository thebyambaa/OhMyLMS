<?php
/**
 * Mastery heatmap and review-set fixtures for a live browser check (tests/php/support/mastery-fixture.php,
 * without the 21-class bounds school).
 *
 *   OHMYLMS_DASHBOARD_TEST=1 php … tests/php/dashboard-mastery-browser-fixture.php create <tag>
 *   OHMYLMS_DASHBOARD_TEST=1 php … tests/php/dashboard-mastery-browser-fixture.php cleanup <tag>
 *
 * `create` prints the fixture ids and the sign-in names as JSON; each temporary user's password is
 * `<tag>-Pass-<name>-1!`. The tag must be `mastlive` followed by digits.
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.GlobalVariablesOverride

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_DASHBOARD_TEST' ) ) {
	exit( "Set OHMYLMS_DASHBOARD_TEST=1 to create or remove mastery browser fixtures.\n" );
}
define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';
require __DIR__ . '/support/mastery-fixture.php';

$action = $argv[1] ?? '';
$tag    = $argv[2] ?? '';
if ( ! preg_match( '/^mastlive[0-9]+$/', $tag ) ) {
	exit( "Usage: create|cleanup mastlive<digits>\n" );
}
add_filter( 'pre_wp_mail', '__return_true' );
if ( 'create' === $action ) {
	masteryfx_delete( $tag );
	$fx             = masteryfx_create( $tag, false );
	$fx['logins']   = array_map(
		static function ( $name ) use ( $tag ) {
			return $tag . '_' . $name;
		},
		array(
			'admin'   => 'admin',
			'sa'      => 'sa',
			'teacher' => 'teacher',
			'author'  => 'author',
		)
	);
	$fx['password'] = $tag . '-Pass-<name>-1!';
	echo wp_json_encode( $fx, JSON_PRETTY_PRINT ), "\n";
} elseif ( 'cleanup' === $action ) {
	masteryfx_delete( $tag );
	echo "Mastery browser fixtures {$tag} removed.\n";
} else {
	exit( "Usage: create|cleanup mastlive<digits>\n" );
}
