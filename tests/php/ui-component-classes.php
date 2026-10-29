<?php
/**
 * Prints Ui::*_class() output for JSON argument sets; used by tests/js/ui-components.test.mjs.
 *
 * @package OhMyLMS
 */

define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

$methods = array(
	'card'     => 'card_class',
	'badge'    => 'badge_class',
	'progress' => 'progress_class',
);
$out     = array();
foreach ( json_decode( $argv[1], true ) as $name => $list ) {
	foreach ( $list as $args ) {
		if ( isset( $args['className'] ) ) {
			$args['class'] = $args['className'];
		}
		$out[ $name ][] = call_user_func( array( '\OhMyLMS\Design\Ui', $methods[ $name ] ), $args );
	}
}
echo json_encode( $out );
