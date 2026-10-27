<?php
/**
 * Frozen choice grading checks, without a WordPress database.
 *
 * @package OhMyLMS
 */

define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

$choice_checks = 0;
foreach ( array( false, true ) as $partial_credit ) {
	$snapshot = new \OhMyLMS\Assessment\QuestionSnapshot(
		array(
			'type'     => 'multiple-choice',
			'settings' => array(
				'type'           => 'multiple-choice',
				'partial_credit' => $partial_credit,
			),
			'options'  => array(
				array(
					'id'         => 1,
					'is_correct' => true,
				),
				array(
					'id'         => 2,
					'is_correct' => true,
				),
				array(
					'id'         => 3,
					'is_correct' => true,
				),
				array(
					'id'         => 4,
					'is_correct' => false,
				),
			),
		)
	);
	$cases    = array(
		array( array( 1, 2, 3 ), 1.0, true ),
		array( array( 1 ), $partial_credit ? 1 / 3 : 0.0, false ),
		array( array( 1, 2, 4 ), $partial_credit ? 1 / 3 : 0.0, false ),
		array( array( 1, 4 ), 0.0, false ),
		array( array( 4 ), 0.0, false ),
		array( array(), 0.0, false ),
		array( array( 1, 2, 3, 4 ), $partial_credit ? 2 / 3 : 0.0, false ),
		array( array( 1, 2, 999 ), $partial_credit ? 1 / 3 : 0.0, false ),
	);
	foreach ( $cases as list( $answer, $fraction, $correct ) ) {
		$grade = \OhMyLMS\Extensions\QuestionTypes::grade_builtin( 'multiple-choice', $answer, $snapshot );
		if ( abs( $grade['fraction'] - $fraction ) > 0.000001 || $correct !== $grade['correct'] ) {
			throw new RuntimeException( 'Choice grade does not match its frozen settings.' );
		}
		++$choice_checks;
	}
	$duplicates = \OhMyLMS\Extensions\QuestionTypes::grade_builtin( 'multiple-choice', array( 1, 1 ), $snapshot );
	if ( abs( $duplicates['fraction'] - ( $partial_credit ? 1 / 3 : 0.0 ) ) > 0.000001 ) {
		throw new RuntimeException( 'Repeated answer IDs inflated credit.' );
	}
	++$choice_checks;
}
echo (int) $choice_checks . " choice grading checks passed.\n";
