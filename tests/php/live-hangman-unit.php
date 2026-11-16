<?php
/**
 * Live Hangman projection and legacy grading contracts.
 *
 * @package OhMyLMS
 */

define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use OhMyLMS\Assessment\ExtendedQuestions;

$hidden = ExtendedQuestions::hangman_progress( 'Ball 2!', '', 3 );
if ( array( null, null, null, null, ' ', '2', '!' ) !== $hidden['letters'] || $hidden['done'] ) {
	throw new RuntimeException( 'The hidden word leaked or punctuation disappeared.' );
}
$partial = ExtendedQuestions::hangman_progress( 'Ball', 'llXx', 3 );
if ( array( null, null, 'l', 'l' ) !== $partial['letters'] || 1 !== $partial['mistakes'] ) {
	throw new RuntimeException( 'Repeated letters or duplicate guesses were counted incorrectly.' );
}
$lost_game = ExtendedQuestions::hangman_progress( 'Ball', 'xyzbal', 3 );
if ( ! $lost_game['done'] || $lost_game['solved'] || array( 'x', 'y', 'z' ) !== $lost_game['guessed'] ) {
	throw new RuntimeException( 'Guesses after the mistake limit changed the result.' );
}
$won = ExtendedQuestions::hangman_progress( 'Өвөл', 'Өвл', 6 );
if ( ! $won['solved'] || 0 !== $won['mistakes'] ) {
	throw new RuntimeException( 'Cyrillic case or repeated letters failed.' );
}
$legacy = ExtendedQuestions::grade(
	'hangman',
	array( 'guesses' => 'bal' ),
	array(
		'accepted'   => array( 'Ball' ),
		'max_misses' => 3,
	)
);
$live   = ExtendedQuestions::grade(
	'hangman',
	array( 'guesses' => 'bal' ),
	array(
		'accepted'   => array( 'Ball' ),
		'max_misses' => 3,
		'live_play'  => true,
	)
);
if ( ! $legacy['correct'] || 0.0 !== $live['fraction'] || $live['manual'] ) {
	throw new RuntimeException( 'Live games changed legacy grading or awarded marks.' );
}
echo "Live Hangman: hidden letters, duplicates, stop limits, Cyrillic and grading passed.\n";
