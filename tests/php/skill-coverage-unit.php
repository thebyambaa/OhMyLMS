<?php
/**
 * Pure unit checks for the course skill coverage rules.
 *
 * Run: php tests/php/skill-coverage-unit.php
 *
 * @package OhMyLMS
 */

define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use OhMyLMS\Learning\SkillCoverage as C;

$checks = 0;

/**
 * Assert a unit result.
 *
 * @param bool   $condition Expected result.
 * @param string $message   Failure reason.
 * @throws RuntimeException On failure.
 */
function check( $condition, $message ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI diagnostic.
	}
	++$checks;
}

$rules = array(
	'familiar_min'        => 2,
	'familiar_families'   => 1,
	'proficient_min'      => 3,
	'proficient_families' => 2,
	'mastered_min'        => 5,
	'mastered_families'   => 3,
);

check( C::COVERED === C::status( true, true ), 'taught and assessable is covered' );
check( C::NEEDS_QUESTIONS === C::status( true, false ), 'taught but too few questions needs questions' );
check( C::NEEDS_TEACHING === C::status( false, true ), 'assessable but not taught needs teaching' );
check( C::NEEDS_BOTH === C::status( false, false ), 'neither needs both' );

$lesson = static function ( $id, $unit, array $skills, $title = '' ) {
	return array(
		'type'       => 'lesson',
		'content_id' => $id,
		'chapter_id' => $unit,
		'title'      => $title ? $title : "Lesson $id",
		'skills'     => $skills,
	);
};
$report = C::assemble(
	array(
		'course_id'  => 7,
		'revision'   => 'rev-1',
		'units'      => array(
			10 => 'Fractions',
			11 => 'Decimals',
		),
		'placed'     => array(
			$lesson( 1, 10, array( 100 ), 'Halves' ),
			$lesson( 1, 10, array( 100 ), 'Halves' ),
			$lesson( 2, 11, array( 100, 101 ) ),
			array(
				'type'       => 'quiz',
				'content_id' => 3,
				'chapter_id' => 10,
				'title'      => 'Quiz',
				'skills'     => array( 100, 102 ),
			),
			array(
				'type'       => 'practice',
				'content_id' => 103,
				'chapter_id' => 11,
				'title'      => '',
				'skills'     => array( 103 ),
			),
		),
		'outcomes'   => array(
			array(
				'term_id'    => 100,
				'target'     => 'mastered',
				'required'   => true,
				'chapter_id' => 10,
			),
			array(
				'term_id'    => 101,
				'target'     => 'familiar',
				'required'   => false,
				'chapter_id' => 11,
			),
			array(
				'term_id'    => 104,
				'target'     => 'proficient',
				'required'   => true,
				'chapter_id' => 0,
			),
		),
		'objectives' => array( 101, 105 ),
		'terms'      => array(
			100 => array(
				'name' => 'Add halves',
				'code' => 'F.1',
			),
			101 => array(
				'name' => 'Add tenths',
				'code' => '',
			),
			102 => array(
				'name' => 'Compare',
				'code' => '',
			),
			103 => array(
				'name' => 'Round',
				'code' => '',
			),
			104 => array(
				'name' => 'Order',
				'code' => '',
			),
			105 => array(
				'name' => 'Estimate',
				'code' => '',
			),
		),
		'pools'      => array(
			100 => array(
				'questions' => 5,
				'families'  => 3,
			),
			101 => array(
				'questions' => 2,
				'families'  => 1,
			),
			102 => array(
				'questions' => 4,
				'families'  => 2,
			),
			104 => array(
				'questions' => 1,
				'families'  => 1,
			),
		),
		'rules'      => $rules,
	)
);

$by = array();
foreach ( $report['skills'] as $skill ) {
	$by[ $skill['term_id'] ] = $skill;
}
check( 6 === count( $by ), 'every skill the course touches is listed once: outcomes, tagged content, practice and objectives' );
check( 7 === $report['course_id'], 'the course id is carried through' );
check( 'rev-1' === $report['revision'], 'the draft revision is carried through, for the writes the report offers' );
check(
	array(
		array(
			'id'    => 10,
			'title' => 'Fractions',
		),
		array(
			'id'    => 11,
			'title' => 'Decimals',
		),
	) === $report['units'],
	'the units are listed in order, so a fix can choose where to put a lesson'
);

$halves = $by[100];
check( $halves['outcome'] && $halves['required'] && 'mastered' === $halves['target'], 'a required outcome keeps its target' );
check( 2 === count( $halves['teaching'] ), 'a lesson placed twice is listed once, so two lessons teach it' );
check( array( 10, 11 ) === array_column( $halves['teaching'], 'unit_id' ) && array( 'Fractions', 'Decimals' ) === array_column( $halves['teaching'], 'unit' ), 'teaching lists the unit of each lesson' );
check( 1 === count( $halves['assessing'] ) && 'quiz' === $halves['assessing'][0]['type'], 'a tagged quiz is listed as assessing' );
check( 5 === $halves['questions_need'] && 3 === $halves['families_need'] && 'target' === $halves['need_basis'], 'questions needed follow the outcome target' );
check( C::COVERED === $halves['status'], 'taught with a big enough pool is covered' );

$tenths = $by[101];
check( ! $tenths['required'] && $tenths['objective'] && $tenths['outcome'], 'an optional outcome that is also an objective' );
check( 2 === $tenths['questions_need'] && C::COVERED === $tenths['status'], 'a familiar target needs only the familiar thresholds' );

$compare = $by[102];
check( ! $compare['outcome'] && 0 === count( $compare['teaching'] ) && 1 === count( $compare['assessing'] ), 'a skill only a quiz is tagged with is not taught' );
check( 'default' === $compare['need_basis'] && 3 === $compare['questions_need'], 'a skill with no outcome is judged by the proficient thresholds' );
check( C::NEEDS_TEACHING === $compare['status'], 'a quiz tag does not count as teaching' );

$round = $by[103];
check( $round['practice'] && 0 === count( $round['teaching'] ) && 0 === count( $round['assessing'] ), 'practice is flagged, and is neither teaching nor assessing' );
check( C::NEEDS_BOTH === $round['status'] && 0 === $round['questions'], 'a practice-only skill with no questions needs both' );

$ordered = $by[104];
check( $ordered['required'] && C::NEEDS_BOTH === $ordered['status'], 'a required outcome with no lesson and a thin pool needs both' );
check( 1 === $ordered['questions'] && 3 === $ordered['questions_need'], 'the shortfall is reported in numbers' );

$estimate = $by[105];
check( $estimate['objective'] && ! $estimate['outcome'] && C::NEEDS_BOTH === $estimate['status'], 'a syllabus objective with no content is a gap, not hidden' );

check( array( 104, 105, 103, 102, 100, 101 ) === array_column( $report['skills'], 'term_id' ), 'gaps come first (needs-both, then needs-teaching), required before optional, then name; covered skills last' );
check( C::COVERED === $report['skills'][ count( $report['skills'] ) - 1 ]['status'], 'covered skills come last' );
check( 104 === $report['skills'][0]['term_id'], 'among equal gaps a required skill comes first' );

$summary = $report['summary'];
check( 6 === $summary['skills'] && 2 === $summary['required'], 'summary counts skills and required skills' );
check( 2 === $summary['covered'] && 1 === $summary['needs_teaching'] && 0 === $summary['needs_questions'] && 3 === $summary['needs_both'], 'summary counts each status' );
check( 2 === $summary['objectives'] && 1 === $summary['objectives_taught'], 'summary counts objectives and how many are taught' );
check( 1 === $summary['required_not_ready'], 'summary counts required skills whose question pool is short' );

$empty = C::assemble( array( 'course_id' => 9 ) );
check( array() === $empty['skills'] && 0 === $empty['summary']['skills'], 'a course with no skills has an empty report' );
check( '' === $empty['revision'] && array() === $empty['units'], 'a report with no revision or units has empty ones' );
$unknown = C::assemble(
	array(
		'outcomes' => array(
			array(
				'term_id'  => 55,
				'target'   => 'bogus',
				'required' => true,
			),
		),
		'rules'    => $rules,
	)
);
check( 'default' === $unknown['skills'][0]['need_basis'] && 3 === $unknown['skills'][0]['questions_need'] && '' === $unknown['skills'][0]['name'], 'an unknown target falls back to proficient, and a missing skill has an empty name' );

echo "PASS: $checks skill coverage unit checks.\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
