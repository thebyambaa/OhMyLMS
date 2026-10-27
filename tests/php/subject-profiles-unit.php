<?php
/**
 * Pure unit checks for subject profiles: keys, validation and level labels.
 *
 * Run: php tests/php/subject-profiles-unit.php
 *
 * @package OhMyLMS
 */

define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use OhMyLMS\Skills\SubjectProfileModel as M;

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

// Keys: lower case, words joined by hyphens, any script.
check( 'mathematics' === M::key( ' Mathematics ' ), 'Mathematics key' );
check( 'english-language' === M::key( 'English  language!' ), 'Punctuation and spaces become one hyphen' );
check( 'математик' === M::key( 'Математик' ), 'Cyrillic subject names keep their letters' );
check( '' === M::key( '!!!' ), 'A name without letters has no key' );
check( 40 === mb_strlen( M::key( str_repeat( 'a', 60 ) ) ), 'Keys are at most 40 characters' );

// A complete profile is normalised.
$profile = M::normalize(
	array(
		'label'    => ' English language ',
		'language' => 'en',
		'domains'  => array(
			array(
				'id'    => 'reading',
				'label' => 'Reading',
			),
			array(
				'id'    => 'writing',
				'label' => ' Writing ',
			),
		),
		'levels'   => array( 'A1', 'A2', array( 'label' => 'B1' ), 'B2', 'C1', 'C2' ),
		'ai'       => array(
			'role'    => 'language curriculum analyst',
			'unknown' => 'dropped',
		),
	)
);
check( is_array( $profile ), 'A valid profile normalises: ' . wp_json_encode_safe( $profile ) );
check( 'English language' === $profile['label'] && 'Writing' === $profile['domains'][1]['label'], 'Text is trimmed' );
check( array( 'A1', 'A2', 'B1', 'B2', 'C1', 'C2' ) === $profile['levels'], 'Levels keep their order as labels' );
check( array( 'role', 'context', 'level_hints' ) === array_keys( $profile['ai'] ) && '' === $profile['ai']['context'], 'AI context has exactly the known fields' );
check(
	array(
		'reading' => 'Reading',
		'writing' => 'Writing',
	) === M::domain_map( $profile ),
	'Domain map by id'
);

// Levels are ordinals with labels; values above the scale read as its last level.
check(
	array(
		'value' => 3,
		'label' => 'B1',
	) === M::levels( $profile )[2],
	'Level ordinals start at 1'
);
check( 'B2' === M::level_label( $profile, 4 ) && 'C2' === M::level_label( $profile, 99 ) && '' === M::level_label( $profile, 0 ), 'Level labels' );

// A profile with no domains is valid (the generic profile).
check(
	is_array(
		M::normalize(
			array(
				'label'  => 'Generic',
				'levels' => array( 'Level 1' ),
			)
		)
	),
	'Domains are optional'
);

// Rejections name the field.
$base = array(
	'label'  => 'Science',
	'levels' => array( 'Grade 1' ),
);
foreach ( array(
	'label'    => array( 'label' => '' ),
	'language' => array( 'language' => 'English!' ),
	'domains'  => array( 'domains' => 'reading' ),
	'domain'   => array(
		'domains' => array(
			array(
				'id'    => 'Physics',
				'label' => 'Physics',
			),
		),
	),
	'levels'   => array( 'levels' => array() ),
	'level'    => array( 'levels' => array( '' ) ),
	'ai'       => array( 'ai' => array( 'role' => str_repeat( 'x', 2001 ) ) ),
) as $code => $change ) {
	check( M::normalize( array_replace( $base, $change ) ) === $code, 'Rejects ' . $code );
}
$twice = array(
	'id'    => 'physics',
	'label' => 'Physics',
);
check( 'domain' === M::normalize( array_replace( $base, array( 'domains' => array( $twice, $twice ) ) ) ), 'Domain ids are unique' );
check( 'levels' === M::normalize( array_replace( $base, array( 'levels' => array_fill( 0, 31, 'Level' ) ) ) ), 'At most 30 levels' );

/**
 * JSON for failure messages without WordPress.
 *
 * @param mixed $value Value.
 * @return string
 */
function wp_json_encode_safe( $value ) {
	return (string) json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Pure test without WordPress loaded.
}

echo 'PASS: ' . (int) $checks . " subject profile unit checks.\n";
