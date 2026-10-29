<?php
/**
 * Prints Ui::button_class() for a JSON list of argument sets; used by tests/js/ui-button.test.mjs.
 *
 * @package OhMyLMS
 */

define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

$out = array();
foreach ( json_decode( $argv[1], true ) as $args ) {
	if ( isset( $args['className'] ) ) {
		$args['class'] = $args['className'];
	}
	$out[] = \OhMyLMS\Design\Ui::button_class( $args );
}
echo json_encode( $out );
