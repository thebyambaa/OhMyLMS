<?php
/**
 * Recommendation ordering without a database.
 *
 * @package OhMyLMS
 */

define( 'ABSPATH', __DIR__ );
require dirname( __DIR__, 2 ) . '/includes/Performance/Levels.php';
require dirname( __DIR__, 2 ) . '/includes/Student/PracticeGuidance.php';

use OhMyLMS\Student\PracticeGuidance;

$guidance_cases = array(
	array(
		array(
			array(
				'term_id'       => 1,
				'need'          => 'review',
				'level'         => 'proficient',
				'session_limit' => 3,
			),
			array(
				'term_id'       => 2,
				'need'          => 'gap',
				'level'         => 'familiar',
				'session_limit' => 3,
			),
			array(
				'term_id'       => 3,
				'need'          => 'gap',
				'level'         => 'not-started',
				'session_limit' => 3,
			),
		),
		3,
	),
	array(
		array(
			array(
				'term_id'       => 1,
				'need'          => 'gap',
				'level'         => 'not-started',
				'session_limit' => 0,
			),
			array(
				'term_id'       => 2,
				'need'          => 'review',
				'level'         => 'proficient',
				'session_limit' => 3,
			),
		),
		2,
	),
	array(
		array(
			array(
				'term_id'       => 1,
				'need'          => 'met',
				'level'         => 'mastered',
				'session_limit' => 3,
			),
		),
		null,
	),
	array(
		array(
			array(
				'term_id'       => 1,
				'need'          => 'gap',
				'level'         => 'familiar',
				'session_limit' => 3,
			),
			array(
				'term_id'       => 2,
				'need'          => 'gap',
				'level'         => 'familiar',
				'session_limit' => 3,
			),
		),
		1,
	),
);
foreach ( $guidance_cases as $guidance_case ) {
	$guidance_result = PracticeGuidance::recommend( $guidance_case[0] );
	if ( ( $guidance_result['term_id'] ?? null ) !== $guidance_case[1] ) {
		throw new RuntimeException( 'Recommendation ordering failed.' );
	}
}
echo "PASS: 4 practice guidance ordering checks.\n";
