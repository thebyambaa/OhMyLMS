<?php
/**
 * Pure window and delta maths for the front dashboard summary.
 *
 * @package OhMyLMS
 */

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DAY_IN_SECONDS', 86400 );
	require dirname( __DIR__, 2 ) . '/includes/Dashboard/FrontSummary.php';

	$failed = false;
	$checks = 0;
	function dash_check( $condition, $message ) {
		global $checks, $failed;
		++$checks;
		if ( ! $condition ) {
			$failed = true;
			echo $message . "\n";
		}
	}

	$window = \OhMyLMS\Dashboard\FrontSummary::window( '7', 1_700_000_000 );
	dash_check( 7 === $window['days'], 'A 7-day range was not 7 days.' );
	dash_check( null === \OhMyLMS\Dashboard\FrontSummary::delta( 5, 0 ), 'A zero previous value invented a percent.' );
	dash_check( 50.0 === \OhMyLMS\Dashboard\FrontSummary::delta( 15, 10 ), 'Delta was not 50 percent.' );
	$term = \OhMyLMS\Dashboard\FrontSummary::window( 'term', 1_700_000_000 );
	dash_check( 90 === $term['days'], 'This term was not treated as 90 days.' );
	echo $failed ? "Dashboard checks failed.\n" : "OK: {$checks} dashboard checks passed.\n";
	exit( $failed ? 1 : 0 );
}
