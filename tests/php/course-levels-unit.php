<?php
/**
 * Pure course level range checks, independent of WordPress and a database.
 *
 * @package OhMyLMS
 */

define( 'ABSPATH', __DIR__ );
require dirname( __DIR__, 2 ) . '/includes/Skills/CourseLevels.php';

use OhMyLMS\Skills\CourseLevels;

/**
 * Assert one level summary result.
 *
 * @param bool $condition Expected condition.
 * @throws RuntimeException When an assertion fails.
 */
function ohmylms_course_level_check( $condition ) {
	if ( ! $condition ) {
		throw new RuntimeException( 'Course level summary assertion failed.' );
	}
}

$math_skill     = array(
	'subject' => 'Mathematics',
	'profile' => 'mathematics',
	'levels'  => array( 'Grade 9', 'Grade 10', 'Grade 11' ),
	'from'    => 1,
	'to'      => 2,
);
$language_skill = array(
	'subject' => 'English',
	'profile' => 'english',
	'levels'  => array( 'A1', 'A2', 'B1', 'B2' ),
	'from'    => 3,
	'to'      => 4,
);
$original       = array( $math_skill, $language_skill );
$summary        = CourseLevels::summarize( $original, array( 'Grade 10', 'Grade 10' ) );
ohmylms_course_level_check( array( 'Grade 10' ) === $summary['curriculum'] );
ohmylms_course_level_check( array( 'Grade 9 – Grade 10', 'B1 – B2' ) === array_column( $summary['skills'], 'label' ) );
ohmylms_course_level_check( array( $math_skill, $language_skill ) === $original );
$summary = CourseLevels::summarize(
	array(
		$math_skill,
		array_merge(
			$math_skill,
			array(
				'from' => 3,
				'to'   => 3,
			)
		),
	)
);
ohmylms_course_level_check( 1 === count( $summary['skills'] ) && 'Grade 9 – Grade 11' === $summary['skills'][0]['label'] );
$summary = CourseLevels::summarize(
	array(
		array_merge(
			$math_skill,
			array(
				'from' => 2,
				'to'   => 0,
			)
		),
	)
);
ohmylms_course_level_check( 'Grade 10' === $summary['skills'][0]['label'] );
$summary = CourseLevels::summarize(
	array(
		array_merge(
			$math_skill,
			array(
				'from' => 0,
				'to'   => 0,
			)
		),
		array_merge(
			$math_skill,
			array(
				'from' => 3,
				'to'   => 2,
			)
		),
		array_merge( $math_skill, array( 'to' => 99 ) ),
	)
);
ohmylms_course_level_check( array() === $summary['skills'] && 3 === $summary['unplaced_skills'] );
$summary = CourseLevels::summarize( array() );
ohmylms_course_level_check( array() === $summary['skills'] && array() === $summary['curriculum'] && 0 === $summary['unplaced_skills'] );
echo "Course levels: subject scales, range aggregation, unknown levels and curriculum labels passed.\n";
