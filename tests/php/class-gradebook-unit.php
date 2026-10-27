<?php
/**
 * Class gradebook scores, pending retakes and export totals without a database.
 *
 * @package OhMyLMS
 */

define( 'ABSPATH', __DIR__ );
require dirname( __DIR__, 2 ) . '/includes/Student/SchoolGradebook.php';
require dirname( __DIR__, 2 ) . '/includes/Schools/GradebookMath.php';

use OhMyLMS\Student\SchoolGradebook;
use OhMyLMS\Schools\GradebookMath;

/**
 * Assert a class gradebook result.
 *
 * @param bool $condition Expected result.
 * @throws RuntimeException On a failed check.
 */
function ohmylms_class_gradebook_check( $condition ) {
	if ( ! $condition ) {
		throw new RuntimeException( 'Class gradebook assertion failed.' );
	}
}

$item     = array(
	'id'  => 10,
	'max' => 20,
);
$scope    = array( array( 'prior_completion' => true ) );
$attempts = array(
	array(
		'status' => 'pending',
		'score'  => 0,
	),
	array(
		'status' => 'completed',
		'score'  => 8,
		'max'    => 10,
	),
);
$cell     = SchoolGradebook::cell( $item, $scope, $attempts );
ohmylms_class_gradebook_check( 8.0 === $cell['score'] && 10.0 === $cell['max'] );
ohmylms_class_gradebook_check( 'not-assigned' === SchoolGradebook::cell( $item, array(), $attempts )['status'] );
ohmylms_class_gradebook_check( 'missing' === SchoolGradebook::cell( $item, $scope, array() )['status'] );
ohmylms_class_gradebook_check( 'pending' === SchoolGradebook::cell( $item, $scope, array( $attempts[0] ) )['status'] );
$zero = SchoolGradebook::cell(
	$item,
	$scope,
	array(
		array(
			'status' => 'failed',
			'score'  => 0,
			'max'    => 20,
		),
	)
);
ohmylms_class_gradebook_check( 0.0 === $zero['score'] );
$total = GradebookMath::total(
	array(
		$cell,
		$zero,
		array(
			'score' => null,
			'max'   => 100,
		),
	)
);
ohmylms_class_gradebook_check( 26.67 === $total['percent'] && 2 === $total['graded'] );
ohmylms_class_gradebook_check(
	null === GradebookMath::total(
		array(
			array(
				'score' => null,
				'max'   => 20,
			),
		)
	)['percent']
);
ohmylms_class_gradebook_check( "'=SUM(A1:A2)" === SchoolGradebook::csv_value( '=SUM(A1:A2)' ) );
echo "Class gradebook checks passed.\n";
