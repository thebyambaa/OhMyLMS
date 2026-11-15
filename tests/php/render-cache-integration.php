<?php
/**
 * Render-phase read cache integration test.
 *
 * Runs against a WordPress install (the Local site by default). Read-only.
 *
 * Usage (set OHMYLMS_RENDER_CACHE_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/render-cache-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.Security.EscapeOutput, WordPress.DB.DirectDatabaseQuery

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_RENDER_CACHE_TEST' ) ) {
	exit( "Set OHMYLMS_RENDER_CACHE_TEST=1 to run the render cache tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']      = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Learning\CourseProgram;
use OhMyLMS\Learning\UnitSnapshots;
use OhMyLMS\Utility\RenderCache;

global $wpdb;
$checks = 0;

/**
 * Assert a condition.
 *
 * @param mixed  $value   Value that must be truthy.
 * @param string $message Failure message.
 * @throws RuntimeException When the condition is falsy.
 */
function ok( $value, $message ) {
	global $checks;
	if ( ! $value ) {
		throw new RuntimeException( 'FAILED: ' . $message );
	}
	++$checks;
}

$exit_code = 0;
try {
	$reads = 0;
	$read  = static function () use ( &$reads ) {
		return ++$reads;
	};

	// Outside the render phase every call reads.
	RenderCache::stop();
	ok( 1 === RenderCache::remember( 'k', $read ) && 2 === RenderCache::remember( 'k', $read ), 'nothing is remembered before a page renders' );

	// A POST never starts remembering.
	$_SERVER['REQUEST_METHOD'] = 'POST';
	RenderCache::start();
	ok( 3 === RenderCache::remember( 'k', $read ) && 4 === RenderCache::remember( 'k', $read ), 'a POST request always reads' );

	// A GET page view reads once per key, including null results.
	$_SERVER['REQUEST_METHOD'] = 'GET';
	RenderCache::start();
	ok( 5 === RenderCache::remember( 'k', $read ) && 5 === RenderCache::remember( 'k', $read ), 'a rendered page reads a key once' );
	ok( 6 === RenderCache::remember( 'other', $read ), 'another key reads on its own' );
	$nulls = 0;
	$empty = static function () use ( &$nulls ) {
		++$nulls;
		return null;
	};
	RenderCache::remember( 'none', $empty );
	RenderCache::remember( 'none', $empty );
	ok( 1 === $nulls, 'a null result is remembered too' );
	RenderCache::flush();
	ok( 7 === RenderCache::remember( 'k', $read ), 'flushing forgets remembered values' );
	RenderCache::stop();
	ok( 8 === RenderCache::remember( 'k', $read ) && 9 === RenderCache::remember( 'k', $read ), 'stopping returns to fresh reads' );

	// The wrapped lookups return the same data either way and query once while rendering.
	$row = $wpdb->get_row( "SELECT user_id, course_id FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE status='enrolled' ORDER BY id DESC LIMIT 1", ARRAY_A );
	if ( $row ) {
		$student = (int) $row['user_id'];
		$course  = (int) $row['course_id'];
		$fresh   = array(
			CourseProgram::enrollment( $student, $course ),
			CourseProgram::for_enrollment( CourseProgram::enrollment( $student, $course ) ),
			UnitSnapshots::native( $course ),
			ohmylms_get_page_id( 'course' ),
		);
		RenderCache::start();
		$before = $wpdb->num_queries;
		$first  = array(
			CourseProgram::enrollment( $student, $course ),
			CourseProgram::for_enrollment( CourseProgram::enrollment( $student, $course ) ),
			UnitSnapshots::native( $course ),
			ohmylms_get_page_id( 'course' ),
		);
		$middle = $wpdb->num_queries;
		$second = array(
			CourseProgram::enrollment( $student, $course ),
			CourseProgram::for_enrollment( CourseProgram::enrollment( $student, $course ) ),
			UnitSnapshots::native( $course ),
			ohmylms_get_page_id( 'course' ),
		);
		ok( $fresh === $first && $first === $second, 'remembered lookups equal fresh lookups' );
		ok( $middle > $before, 'the first lookups query the database' );
		ok( $wpdb->num_queries === $middle, 'repeating the lookups while rendering runs no query' );
		RenderCache::stop();
		$before = $wpdb->num_queries;
		CourseProgram::enrollment( $student, $course );
		ok( $wpdb->num_queries > $before, 'outside rendering the enrolment is read again' );
	}

	echo "OK: $checks checks\n";
} catch ( Throwable $error ) {
	echo $error->getMessage(), "\n";
	$exit_code = 1;
}
RenderCache::stop();

exit( $exit_code );
