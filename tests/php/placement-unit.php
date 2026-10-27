<?php
/**
 * Pure unit checks for the adaptive placement test: priors from the stated grade, how answers spread
 * through prerequisites and grades, which skill is asked next, and the resulting placement.
 *
 * Run: php tests/php/placement-unit.php
 *
 * @package OhMyLMS
 */

define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use OhMyLMS\Placement\PlacementModel;

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

$skill = static function ( $domain, $from, $to, $prerequisites = array() ) {
	return array(
		'domain'        => $domain,
		'grade_from'    => $from,
		'grade_to'      => $to,
		'prerequisites' => $prerequisites,
	);
};
// A chain in algebra (1 -> 2 -> 3 -> 4), a geometry pair, and a number skill with no links.
$skills = array(
	1 => $skill( 'algebra', 5, 5 ),
	2 => $skill( 'algebra', 6, 6, array( 1 ) ),
	3 => $skill( 'algebra', 7, 7, array( 2 ) ),
	4 => $skill( 'algebra', 8, 8, array( 3 ) ),
	5 => $skill( 'geometry', 6, 6 ),
	6 => $skill( 'geometry', 8, 8, array( 5 ) ),
	7 => $skill( 'number', 7, 7 ),
	8 => $skill( 'algebra', 0, 0 ),
);

// Priors.
check( 0.5 === PlacementModel::prior( $skills[3], 0 ) && 0.5 === PlacementModel::prior( $skills[8], 7 ), 'no stated grade or no skill grade starts at even odds' );
check( 0.85 === PlacementModel::prior( $skills[1], 7 ) && 0.75 === PlacementModel::prior( $skills[2], 7 ), 'earlier grades start likely known' );
check( 0.5 === PlacementModel::prior( $skills[3], 7 ) && 0.25 === PlacementModel::prior( $skills[4], 7 ) && 0.15 === PlacementModel::prior( $skill( 'algebra', 10, 10 ), 7 ), 'the stated grade is uncertain, later grades unlikely' );

// Answers spread.
$p = PlacementModel::estimate(
	$skills,
	0,
	array(
		array(
			'skill'   => 3,
			'outcome' => 'right',
		),
	)
);
check( 0.95 === $p[3] && $p[2] >= 0.9 && $p[1] >= 0.85 && $p[1] < $p[2], 'getting a skill right makes what it builds on likely known, less so further back' );
check( 0.5 === $p[4] && 0.5 === $p[5], 'a right answer says nothing about later or unrelated skills' );
$p = PlacementModel::estimate(
	$skills,
	0,
	array(
		array(
			'skill'   => 2,
			'outcome' => 'wrong',
		),
	)
);
check( 0.1 === $p[2] && $p[3] <= 0.15 && $p[4] <= 0.2 && $p[4] > $p[3], 'missing a skill makes what builds on it likely unknown' );
check( 0.5 === $p[1], 'a miss says nothing about prerequisites' );
$p = PlacementModel::estimate(
	$skills,
	0,
	array(
		array(
			'skill'   => 5,
			'outcome' => 'unknown',
		),
	)
);
check( 0.05 === $p[5] && $p[6] <= 0.15, '"not learned yet" is a stronger miss' );
$p = PlacementModel::estimate(
	$skills,
	0,
	array(
		array(
			'skill'   => 7,
			'outcome' => 'right',
		),
	)
);
check( 0.5 === $p[1] && 0.95 === $p[7], 'no links and no earlier skills in its domain: nothing else moves' );
$sparse = array(
	1 => $skill( 'geometry', 5, 5 ),
	2 => $skill( 'geometry', 8, 8 ),
	3 => $skill( 'geometry', 10, 10 ),
);
$p      = PlacementModel::estimate(
	$sparse,
	0,
	array(
		array(
			'skill'   => 2,
			'outcome' => 'right',
		),
	)
);
check( 0.75 === $p[1] && 0.5 === $p[3], 'without links, earlier grades of the domain still rise' );
$p = PlacementModel::estimate(
	$sparse,
	0,
	array(
		array(
			'skill'   => 2,
			'outcome' => 'wrong',
		),
	)
);
check( 0.3 === $p[3] && 0.5 === $p[1], 'and later grades fall after a miss' );
$p = PlacementModel::estimate(
	$skills,
	0,
	array(
		array(
			'skill'   => 4,
			'outcome' => 'right',
		),
		array(
			'skill'   => 2,
			'outcome' => 'wrong',
		),
	)
);
check( 0.1 === $p[2] && 0.95 === $p[4], 'direct answers stand even when they disagree' );
check( 0.5 === PlacementModel::estimate( $skills, 0, array( array( 'skill' => 99, 'outcome' => 'right' ) ) )[1], 'unknown skills are ignored' );

// Next question.
$p     = PlacementModel::estimate( $skills, 0, array() );
$first = PlacementModel::next( $skills, $p, array( 1, 2, 3, 4, 5, 6, 7 ), array(), 0 );
check( in_array( $first, array( 2, 3 ), true ), 'the first question sits in the middle of the longest chain: ' . $first );
$after = PlacementModel::estimate(
	$skills,
	0,
	array(
		array(
			'skill'   => $first,
			'outcome' => 'right',
		),
	)
);
$next  = PlacementModel::next( $skills, $after, array( 1, 2, 3, 4, 5, 6, 7 ), array( $first ), 0 );
check( in_array( $next, array( 5, 6, 7 ), true ), 'the next question reaches a domain not asked yet: ' . $next );
check( 0 === PlacementModel::next( $skills, $p, array( 1 ), array( 1 ), 0 ) && 0 === PlacementModel::next( $skills, $p, array(), array(), 0 ), 'nothing left to ask' );
$sure = array_fill_keys( array_keys( $skills ), 0.9 );
check( 0 === PlacementModel::next( $skills, $sure, array_keys( $skills ), array(), 0 ), 'skills already near certain are not asked' );
$graded = PlacementModel::estimate( $skills, 7, array() );
$pick   = PlacementModel::next( $skills, $graded, array( 1, 2, 3, 4, 7 ), array(), 7 );
check( 3 === $pick, 'with a stated grade the test starts at that grade: ' . $pick );

// Result.
$p      = array(
	1 => 0.95,
	2 => 0.9,
	3 => 0.5,
	4 => 0.1,
	5 => 0.9,
	6 => 0.2,
	7 => 0.75,
	8 => 0.5,
);
$result = PlacementModel::result( $skills, $p );
check( array( 1, 2, 5, 7 ) === $result['known'] && array( 4, 6 ) === $result['unknown'] && array( 3, 8 ) === $result['unsure'], 'skills are known, not known yet or unsure' );
$domains = array_column( $result['domains'], null, 'domain' );
check( 6 === $domains['algebra']['grade'] && 2 === $domains['algebra']['known'] && 5 === $domains['algebra']['total'], 'the working grade is the last grade with enough known: ' . json_encode( $domains['algebra'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- No WordPress here.
check( 6 === $domains['geometry']['grade'] && 7 === $domains['number']['grade'], 'each domain gets its own grade' );
check( array( 3, 6, 8 ) === $result['start'], 'start with skills whose prerequisites are known, earliest grade first: ' . implode( ',', $result['start'] ) );
check( array( 3 ) === PlacementModel::result( $skills, $p, 1 )['start'], 'the start list is bounded' );

// Limits.
check( 15 === PlacementModel::limit( 0 ) && 5 === PlacementModel::limit( 2 ) && 30 === PlacementModel::limit( 99 ) && 12 === PlacementModel::limit( '12' ), 'question limits' );

echo (int) $checks . " placement unit checks passed.\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
