<?php
/**
 * Pure unit checks for the skill maps: grid layout, coherence checks, prerequisite chains and
 * syllabus comparison. No WordPress needed.
 *
 * Run: php tests/php/skill-map-unit.php
 *
 * @package OhMyLMS
 */

define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use OhMyLMS\Curriculum\SkillMapModel;

$checks = 0;

/**
 * Fail loudly or count a passed check.
 *
 * @param bool   $condition Check.
 * @param string $message   What failed.
 */
function check( $condition, $message ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI test output.
	}
	++$checks;
}

$skill  = static function ( $id, $domain, $from, $to, $prerequisites = array() ) {
	return array(
		'id'            => $id,
		'domain'        => $domain,
		'grade_from'    => $from,
		'grade_to'      => $to,
		'prerequisites' => $prerequisites,
	);
};
$skills = array(
	1 => $skill( 1, 'algebra', 7, 7 ),
	2 => $skill( 2, 'algebra', 8, 8, array( 1 ) ),
	3 => $skill( 3, 'algebra', 9, 9, array( 1, 2 ) ),
	4 => $skill( 4, 'algebra', 9, 10 ),
	5 => $skill( 5, 'geometry', 6, 7 ),
	6 => $skill( 6, 'geometry', 7, 7, array( 7 ) ),
	7 => $skill( 7, 'geometry', 8, 8 ),
	8 => $skill( 8, 'number', 11, 11, array( 9 ) ),
	9 => $skill( 9, 'number', 5, 6 ),
	10 => $skill( 10, '', 0, 0 ),
	11 => $skill( 11, 'statistics', 0, 0 ),
	12 => $skill( 12, 'astrology', 3, 3 ),
);

// Grid.
$grid = SkillMapModel::grid( $skills, array( 'number', 'algebra', 'geometry' ) );
check( array( 'number', 'algebra', 'geometry', 'astrology' ) === $grid['domains'], 'domains follow the given order, unknown ones after: ' . implode( ',', $grid['domains'] ) );
check( range( 3, 11 ) === $grid['grades'], 'grades run from the first to the last grade used, bands included' );
check( array( 3, 4 ) === $grid['cells']['algebra'][9] && array( 5 ) === $grid['cells']['geometry'][6], 'a skill sits in the cell of its first grade' );
check( array( 10, 11 ) === $grid['unplaced'], 'skills without a domain or a grade are unplaced' );
check( array() === SkillMapModel::grid( array() )['grades'], 'an empty library has no grades' );

// Coherence issues.
$issues = SkillMapModel::issues( $skills );
$keyed  = array();
foreach ( $issues as $issue ) {
	$keyed[] = $issue['type'] . ':' . $issue['skill'] . ':' . $issue['other'];
}
check( in_array( 'later_prerequisite:6:7', $keyed, true ), 'a prerequisite taught after the skill is flagged' );
check( in_array( 'long_gap:8:9', $keyed, true ), 'a prerequisite four or more grades back is flagged' );
check( in_array( 'no_prerequisites:4:0', $keyed, true ) && in_array( 'no_prerequisites:7:0', $keyed, true ), 'skills above the first grade of their domain with no prerequisites are flagged' );
check( ! in_array( 'no_prerequisites:1:0', $keyed, true ) && ! in_array( 'no_prerequisites:5:0', $keyed, true ) && ! in_array( 'no_prerequisites:12:0', $keyed, true ), 'the first grade of a domain needs nothing' );
check( ! preg_grep( '/:(2|3):/', $keyed ), 'well-ordered links are fine' );
check( ! preg_grep( '/:(10|11):/', $keyed ), 'unplaced skills are not checked' );
check( 'later_prerequisite' === $issues[0]['type'] && 'no_prerequisites' === end( $issues )['type'] && ! isset( $issues[0]['grade'] ), 'issues are ordered by severity and carry no sort key' );
$short = SkillMapModel::issues( array( 1 => $skill( 1, 'algebra', 5, 7 ), 2 => $skill( 2, 'algebra', 10, 10, array( 1 ) ) ) );
check( array() === $short, 'a gap counts from the end of the prerequisite band' );

// Chains.
$chain = SkillMapModel::chain( $skills, 3 );
check( array( 1, 2 ) === $chain['before'] && array() === $chain['after'], 'a skill builds on its prerequisites, however far back' );
$chain = SkillMapModel::chain( $skills, 1 );
check( array() === $chain['before'] && array( 2, 3 ) === $chain['after'], 'and every skill that builds on it' );
$loop = SkillMapModel::chain( array( 1 => $skill( 1, 'algebra', 1, 1, array( 2 ) ), 2 => $skill( 2, 'algebra', 1, 1, array( 1 ) ) ), 1 );
check( array( 2 ) === $loop['before'] && array( 2 ) === $loop['after'], 'a loop in stored data ends the walk' );

// Comparison: A has objectives 101-104, B has 201-204.
$mappings   = array(
	101 => array( 1 => 'equivalent' ),
	102 => array( 2 => 'narrower' ),
	103 => array( 5 => 'related' ),
	201 => array( 1 => 'equivalent' ),
	202 => array(
		3 => 'equivalent',
		2 => 'broader',
	),
	203 => array( 9 => 'combined' ),
);
$comparison = SkillMapModel::compare( array( 101, 102, 103, 104 ), array( 201, 202, 203, 204 ), $mappings, $skills );
check( array( 1, 2 ) === $comparison['both'] && array() === $comparison['only_a'] && array( 3, 9 ) === $comparison['only_b'], 'skills are split into both, only A and only B: ' . wp_json_encode_stub( $comparison ) );
check( array( 103, 104 ) === $comparison['unmapped_a'] && array( 204 ) === $comparison['unmapped_b'], 'related mappings do not count as teaching a skill' );
check( 50 === $comparison['overlap'], 'overlap is the share of skills taught by both' );
check( array( 1, 2, 3, 9 ) === array_column( $comparison['rows'], 'skill' ), 'rows are ordered by domain, then grade' );
check( array( 102 => 'narrower' ) === $comparison['rows'][1]['a'] && array( 202 => 'broader' ) === $comparison['rows'][1]['b'], 'rows keep each side\'s objectives and relations' );
$domains = array_column( $comparison['domains'], null, 'domain' );
check( 2 === $domains['algebra']['both'] && 1 === $domains['algebra']['only_b'] && 1 === $domains['number']['only_b'], 'counts per domain' );
check( 0 === SkillMapModel::compare( array(), array(), array(), array() )['overlap'], 'nothing to compare is 0% overlap' );

/**
 * Plain JSON for failure messages.
 *
 * @param mixed $value Value.
 * @return string
 */
function wp_json_encode_stub( $value ) {
	return (string) json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- No WordPress here.
}

echo (int) $checks . " skill map unit checks passed.\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
