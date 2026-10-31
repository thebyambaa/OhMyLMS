<?php
/**
 * Live-check fixtures for the At-risk learners widget: `create <tag>` prints the ids and logins as JSON,
 * `cleanup <tag>` removes everything of the tag. Tags must be `risklive` followed by digits.
 *
 * Usage (set OHMYLMS_AT_RISK_TEST=1):
 *   php ... tests/php/at-risk-browser-fixture.php create risklive123
 *   php ... tests/php/at-risk-browser-fixture.php cleanup risklive123
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_AT_RISK_TEST' ) ) {
	exit( "Set OHMYLMS_AT_RISK_TEST=1.\n" );
}
$risk_mode = $argv[1] ?? '';
$tag    = $argv[2] ?? '';
if ( ! in_array( $risk_mode, array( 'create', 'cleanup' ), true ) || ! preg_match( '/^risklive[0-9]+$/', $tag ) ) {
	exit( "Usage: create|cleanup risklive<digits>\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';
require __DIR__ . '/support/at-risk-fixture.php';

if ( 'cleanup' === $risk_mode ) {
	riskfx_delete( $tag );
	echo "Removed $tag.\n";
	exit( 0 );
}
$fx = riskfx_create( $tag );
\OhMyLMS\Dashboard\Cache::flush();
echo wp_json_encode(
	array(
		'admin'    => array( $tag . '_admin', $tag . '-Pass-admin-1!' ),
		'teacher'  => array( $tag . '_teacher', $tag . '-Pass-teacher-1!' ),
		'class_a1' => 'class:' . $fx['class_a1'],
		'school_a' => 'school:' . $fx['school_a'],
		'learners' => $fx['l'],
	)
) . "\n";
