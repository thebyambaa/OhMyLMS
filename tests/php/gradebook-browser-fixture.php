<?php
/**
 * Live gradebook fixtures for a browser check on the Local site: `create <tag>` builds the tagged fixtures of
 * tests/php/support/gradebook-fixture.php (with the 30 × 20 course) and prints ids and logins; `cleanup <tag>`
 * removes them. The tag must look like `gbklive<digits>`.
 *
 * Usage: php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *        -d extension=curl -d mysqli.default_port=10006 tests/php/gradebook-browser-fixture.php create gbklive1
 *
 * @package OhMyLMS
 */

if ( 'cli' !== PHP_SAPI || $argc < 3 || ! in_array( $argv[1], array( 'create', 'cleanup' ), true ) || ! preg_match( '/^gbklive\d+$/', $argv[2] ) ) {
	exit( "Usage: php gradebook-browser-fixture.php create|cleanup gbklive<digits>\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';
require __DIR__ . '/support/gradebook-fixture.php';

$tag = $argv[2];
if ( 'cleanup' === $argv[1] ) {
	gbfx_delete( $tag );
	\OhMyLMS\Dashboard\Cache::flush();
	echo "removed $tag\n";
	exit( 0 );
}
$fx = gbfx_create( $tag, true );
echo wp_json_encode(
	array(
		'tag'      => $tag,
		'course'   => $fx['course'],
		'perf'     => $fx['perf']['course'],
		'class_a1' => $fx['class_a1'],
		'class_a2' => $fx['class_a2'],
		'school_a' => $fx['school_a'],
		'learners' => $fx['l'],
		'items'    => array(
			'q1' => $fx['q1'],
			'h1' => $fx['h1'],
			'e1' => $fx['e1'],
			'h2' => $fx['h2'],
			'q2' => $fx['q2'],
		),
		'logins'   => array(
			'admin'   => array( $tag . '_admin', $tag . '-Pass-admin-1!' ),
			'teacher' => array( $tag . '_teacher', $tag . '-Pass-teacher-1!' ),
			'sa'      => array( $tag . '_sa', $tag . '-Pass-sa-1!' ),
		),
	),
	JSON_PRETTY_PRINT
) . "\n";
