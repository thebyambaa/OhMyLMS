<?php
/**
 * Startup option query integration test.
 *
 * Runs against a WordPress install (the Local site by default). Read-only: it loads WordPress with
 * query logging and checks that the plugin's startup does not read its options one query at a time.
 *
 * Usage (set OHMYLMS_STARTUP_OPTIONS_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/startup-options-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.Security.EscapeOutput

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_STARTUP_OPTIONS_TEST' ) ) {
	exit( "Set OHMYLMS_STARTUP_OPTIONS_TEST=1 to run the startup option tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
define( 'SAVEQUERIES', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

global $wpdb;

$single = array();
$primed = 0;
foreach ( $wpdb->queries as $query ) {
	$sql = preg_replace( '/\s+/', ' ', $query[0] );
	if ( preg_match( "/option_name = '(ohmylms_[^']+)' LIMIT 1/", $sql, $match ) ) {
		$single[] = $match[1];
	}
	if ( false !== strpos( $sql, 'option_name IN' ) && false !== strpos( $sql, "'ohmylms_school_schema'" ) ) {
		++$primed;
	}
}

$failures = array();
if ( 1 !== $primed ) {
	$failures[] = "expected one priming query for the startup options, found $primed";
}
if ( $single ) {
	$failures[] = 'startup read these options one query each; add them to StartupOptions::NAMES or autoload them: ' . implode( ', ', array_unique( $single ) );
}
foreach ( \OhMyLMS\Utility\StartupOptions::NAMES as $name ) {
	// The plugin's own options, or the stored rows of its own transients.
	if ( 1 !== preg_match( '/^(?:_transient_(?:timeout_)?_?)?ohmylms_/', $name ) ) {
		$failures[] = "only the plugin's own options are primed: $name";
	}
}

if ( $failures ) {
	echo 'FAILED: ', implode( "\nFAILED: ", $failures ), "\n";
	exit( 1 );
}
echo 'OK: startup reads its ', count( \OhMyLMS\Utility\StartupOptions::NAMES ), " listed options with one query\n";
exit( 0 );
