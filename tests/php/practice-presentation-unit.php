<?php
/**
 * Question limits must describe finite pools and repeatable templates honestly.
 *
 * @package OhMyLMS
 */

define( 'ABSPATH', __DIR__ );
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use OhMyLMS\Practice\Presentation;

$presentation_cases = array(
	array( array(), 10, 0 ),
	array( array( array( 'question_id' => 1 ), array( 'question_id' => 1 ) ), 10, 1 ),
	array(
		array_fill(
			0,
			8,
			array(
				'question_id' => 1,
				'is_template' => true,
			)
		),
		10,
		10,
	),
	array(
		array(
			array(
				'question_id' => 1,
				'is_template' => true,
			),
		),
		100,
		30,
	),
	array(
		array(
			array(
				'question_id' => 1,
				'is_template' => true,
			),
		),
		-1,
		3,
	),
	array( array_map( static fn( $id ) => array( 'question_id' => $id ), range( 1, 12 ) ), 5, 5 ),
);
foreach ( $presentation_cases as $presentation_case ) {
	if ( Presentation::count( $presentation_case[0], $presentation_case[1] ) !== $presentation_case[2] ) {
		throw new RuntimeException( 'Practice entry count violated the session limit or pool.' );
	}
}
echo count( $presentation_cases ) . " practice presentation checks passed.\n";
