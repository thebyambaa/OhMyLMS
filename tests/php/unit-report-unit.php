<?php
/**
 * Unit checks for unit (chapter) filtering, grouping and comparison of skill reports.
 * Pure functions only; no WordPress needed.
 *
 * Run: php tests/php/unit-report-unit.php
 */
define( 'ABSPATH', __DIR__ );
require dirname( __DIR__, 2 ) . '/includes/Performance/Levels.php';
require dirname( __DIR__, 2 ) . '/includes/Skills/UnitReport.php';
function check( $value, $message ) {
	if ( ! $value ) {
		throw new RuntimeException( $message );
	}
}
function __( $text, $domain = '' ) {
	return $text;
}
use OhMyLMS\Skills\UnitReport as U;

// Which unit a skill sits in: outcomes first, then practice items, then items attached to skills.
$program = array(
	'outcomes' => array(
		array(
			'term_id'    => 11,
			'chapter_id' => 100,
		),
		array(
			'term_id'    => 12,
			'chapter_id' => 100,
		),
		array( 'term_id' => 13 ),
		array(
			'term_id'    => 14,
			'chapter_id' => 200,
		),
	),
	'items'    => array(
		array(
			'type'       => 'practice',
			'content_id' => 13,
			'chapter_id' => 200,
		),
		array(
			'type'       => 'practice',
			'content_id' => 11,
			'chapter_id' => 200,
		),
		array(
			'type'       => 'lesson',
			'content_id' => 900,
			'chapter_id' => 300,
			'skill_ids'  => array( 15, 14 ),
		),
		array(
			'type'       => 'quiz',
			'content_id' => 901,
			'chapter_id' => 0,
			'skill_ids'  => array( 16 ),
		),
	),
);
$map = U::unit_map( $program );
check( 100 === $map[11] && 100 === $map[12], 'Outcomes name their unit' );
check( 200 === $map[13], 'A practice item places an outcome that names no unit' );
check( 200 === $map[14], 'An outcome unit is not overridden by a later item' );
check( 100 === $map[11], 'A practice item never moves a skill that an outcome already places' );
check( 300 === $map[15], 'An item attached to a skill places it' );
check( ! isset( $map[16] ), 'No unit means unmapped' );
check( array() === U::unit_map( null ) && array() === U::unit_map( array() ), 'No program, no units' );

// Filtering to one unit.
check( array( 11, 12 ) === U::filter( array( 11, 12, 13, 99 ), $map, 100 ), 'Filter keeps the unit skills in order' );
check( array( 13, 14 ) === U::filter( array( 11, 13, 14 ), $map, 200 ), 'Filter to another unit' );
check( array( 11, 99 ) === U::filter( array( 11, 99 ), $map, 0 ), 'No unit keeps every skill' );
check( array() === U::filter( array( 16, 99 ), $map, 100 ), 'Unmapped skills are not in any unit' );

// Grouping under unit headers.
$units  = array(
	array(
		'id'   => 100,
		'name' => 'Fractions',
	),
	array(
		'id'   => 200,
		'name' => 'Decimals',
	),
	array(
		'id'   => 300,
		'name' => 'Empty',
	),
);
$groups = U::group( array( 14, 11, 16, 12, 99 ), $map, $units, 'Other skills' );
check( array( 100, 200, 0 ) === array_column( $groups, 'id' ), 'Groups follow unit order with Other last; empty units are dropped' );
check( array( 11, 12 ) === $groups[0]['skills'] && array( 14 ) === $groups[1]['skills'] && array( 16, 99 ) === $groups[2]['skills'], 'Skills keep their order inside a group' );
check( 'Other skills' === $groups[2]['name'], 'Other group gets the given header' );
$stray = U::group( array( 11 ), array( 11 => 777 ), $units );
check( array( 0 ) === array_column( $stray, 'id' ), 'A skill whose unit is not in the course falls into Other' );

// Unit selector counts.
$counts = U::with_counts( $units, $map );
check( array( 2, 2, 1 ) === array_column( $counts, 'skills' ), 'Counts per unit from the map' );
check( array( 1, 0, 0 ) === array_column( U::with_counts( $units, $map, array( 11 ) ), 'skills' ), 'Counts can be limited to some skills' );

// Class average hides small groups and counts everyone, including Not started.
check( null === U::average( array( 'mastered', 'mastered', 'mastered', 'mastered' ) ), 'A group of four is never averaged' );
$avg = U::average( array( 'mastered', 'proficient', 'familiar', 'not-started', 'attempted' ) );
check( 5 === $avg['total'] && 1 === $avg['counts']['familiar'], 'Average counts each level' );
check( 'familiar' === $avg['key'], 'Mean rank rounds to the middle level' );
check( 40 === $avg['proficient_share'], 'Share at Proficient or above' );
$odd = U::average( array( 'weird', 'weird', 'weird', 'weird', 'mastered' ) );
check( 5 === $odd['total'] && 4 === $odd['counts']['not-started'], 'Unknown levels count as Not started' );

// Side-by-side comparison: missing means Not started; class averages follow the learner.
$levels  = array(
	7 => array(
		11 => 'mastered',
		12 => 'attempted',
	),
	8 => array( 11 => 'familiar' ),
);
$cohorts = array(
	7 => array( 11 => array( 'proficient', 'proficient', 'familiar', 'mastered', 'attempted', 'proficient', 'proficient', 'familiar' ) ),
);
$rows    = U::compare( array( 11, 12 ), $levels, $cohorts );
check( 'mastered' === $rows[0]['cells'][7] && 'familiar' === $rows[0]['cells'][8], 'Each child gets a cell per skill' );
check( 'not-started' === $rows[1]['cells'][8], 'An unpractised skill is Not started' );
check( isset( $rows[0]['class'][7] ) && ! isset( $rows[0]['class'][8] ), 'Only a learner with a class gets an average' );
check( 'proficient' === $rows[0]['class'][7]['key'], 'Class average is the rounded mean level' );
check( null === $rows[1]['class'][7], 'A skill nobody in the class has started shows no average (too few answers)' );
check( array( 'key', 'band' ) === array_keys( $rows[0]['class'][7] ), 'A guardian only gets a level and a band, never counts or a share' );

// Guardian class comparison: classmates only, at least MIN_PEERS, coarse bands.
check( U::MIN_PEERS >= 8 && U::MIN_PEERS > U::MIN_COHORT, 'Guardians need more classmates than staff' );
check( null === U::peer_average( array_fill( 0, U::MIN_PEERS - 1, 'mastered' ) ), 'One classmate short of the minimum shows nothing' );
check( 'most' === U::peer_average( array_fill( 0, 8, 'mastered' ) )['band'], 'Everyone Proficient or above is Most' );
check( 'few' === U::peer_average( array_fill( 0, 8, 'familiar' ) )['band'], 'Nobody Proficient or above is Few' );
$half = U::peer_average( array( 'proficient', 'proficient', 'proficient', 'proficient', 'familiar', 'familiar', 'attempted', 'not-started' ) );
check( 'half' === $half['band'], 'Half the class is About half' );
check( ! isset( $half['counts'] ) && ! isset( $half['total'] ) && ! isset( $half['proficient_share'] ), 'No counts or percentage are returned' );
check( 'half' === U::peer_average( array( 'mastered', 'mastered', 'mastered', 'mastered', 'mastered', 'familiar', 'familiar', 'familiar' ) )['band'], '5 of 8 is About half' );
check( 'most' === U::peer_average( array( 'mastered', 'mastered', 'mastered', 'mastered', 'mastered', 'mastered', 'familiar', 'familiar' ) )['band'], '6 of 8 is Most' );
check( 'few' === U::peer_average( array( 'mastered', 'mastered', 'familiar', 'familiar', 'familiar', 'familiar', 'familiar', 'familiar' ) )['band'], '2 of 8 is Few' );

// Skills shown are the ones any child has started, by name.
$names = array(
	11 => 'Zebra',
	12 => 'Apple',
	13 => 'Mango',
);
check( array( 12, 11 ) === U::started_skills( $levels, $names ), 'Started skills are sorted by name' );
check( array() === U::started_skills( array( 7 => array( 11 => 'not-started' ) ), $names ), 'Not started everywhere is not shown' );
check( array( 11 ) === U::started_skills( array( 7 => array( 11 => 'familiar', 99 => 'mastered' ) ), $names ), 'Unknown skills are ignored' );

// Text signals so colour is never the only one.
check( 'M' === U::short( 'mastered' ) && 'P' === U::short( 'proficient' ) && 'F' === U::short( 'familiar' ) && 'A' === U::short( 'attempted' ), 'Letters for the four levels' );
check( '–' === U::short( 'not-started' ) && '–' === U::short( 'bogus' ), 'Dash for Not started' );

echo "unit report ok\n";
