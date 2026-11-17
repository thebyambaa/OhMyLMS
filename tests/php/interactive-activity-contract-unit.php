<?php
/**
 * The PHP side of the interactive activity contract must give the same configuration as the
 * JavaScript side (tests/js/interactive-activities.test.mjs) for every case in the shared fixture.
 *
 * Run: php tests/php/interactive-activity-contract-unit.php
 *
 * @package OhMyLMS
 */

define( 'ABSPATH', __DIR__ );

/**
 * Stand-in for the WordPress function the graph sanitizer uses.
 *
 * @param string $color Candidate colour.
 * @return string
 */
function sanitize_hex_color( $color ) {
	return preg_match( '/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', (string) $color ) ? $color : '';
}

/**
 * Stand-in for the WordPress function the graph sanitizer uses.
 *
 * @param string $text Text.
 * @return string
 */
function wp_strip_all_tags( $text ) {
	return strip_tags( (string) $text );
}

require dirname( __DIR__, 2 ) . '/includes/Blocks/InteractiveActivityBlock.php';

use OhMyLMS\Blocks\InteractiveActivityBlock as Block;

$cases    = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/interactive-activity-contract.json' ), true );
$failures = array();
foreach ( $cases as $case ) {
	$actual = Block::sanitize_activity( $case['activity'], $case['input'] );
	// Loose comparison: 1 and 1.0 are the same number to JSON and to the script.
	if ( $actual != $case['expected'] ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual
		$failures[] = $case['activity'] . ' ' . wp_json_encode_stub( $case['input'] ) . ' gave ' . wp_json_encode_stub( $actual ) . ', expected ' . wp_json_encode_stub( $case['expected'] );
	}
}

/**
 * JSON for a failure message.
 *
 * @param mixed $value Value.
 * @return string
 */
function wp_json_encode_stub( $value ) {
	return (string) json_encode( $value );
}

// Every activity the script knows has a PHP contract that returns its own version.
foreach ( Block::ACTIVITIES as $activity ) {
	$config = Block::sanitize_activity( $activity, null );
	if ( 'function-graph' !== $activity && 1 !== $config['version'] ) {
		$failures[] = "$activity has no versioned default";
	}
}

if ( $failures ) {
	fwrite( STDERR, "FAILED:\n  " . implode( "\n  ", $failures ) . "\n" );
	exit( 1 );
}
echo 'OK: ' . count( $cases ) . " shared contract cases give the same configuration in PHP\n";
