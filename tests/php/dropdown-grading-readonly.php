<?php
/** Read-only dropdown grading checks against the local WordPress installation. */
if ( 'cli' !== PHP_SAPI || empty( $argv[1] ) ) {
	exit( 1 );
}
require rtrim( $argv[1], '/\\' ) . '/wp-load.php';
use OhMyLMS\Assessment\Interactive;
use OhMyLMS\Assessment\Grader;
use OhMyLMS\Assessment\QuestionSnapshot;

$checks = 0;
$check = static function ( $condition, $label ) use ( &$checks ) {
	if ( ! $condition ) {
		throw new RuntimeException( $label );
	}
	++$checks;
};
$settings = array(
	'type' => 'dropdown-blanks', 'text' => '{1} and {2}', 'dropdown_grading_version' => 2,
	'score' => array( 'enabled' => true, 'value' => 5 ),
	'slots' => array(
		array( 'id' => '1', 'choices' => array( 'positive', 'rising', 'negative' ), 'answers' => array( 'positive', 'rising' ), 'multiple' => true, 'grading' => 'equal', 'points' => 3 ),
		array( 'id' => '2', 'choices' => array( 'up', 'down' ), 'answer' => 'up', 'answers' => array( 'up' ), 'points' => 2, 'grading' => 'equal' ),
	),
);
$snapshot = static function ( $private ) {
	return new QuestionSnapshot( array( 'id' => 1, 'question_id' => 1, 'question_uuid' => 'dropdown-test', 'version_no' => 1, 'type' => 'dropdown-blanks', 'title' => 'Question', 'body' => '', 'settings' => $private, 'options' => array(), 'media' => array(), 'parts' => array(), 'extension' => array() ) );
};
$check( true === Interactive::validate_settings( 'dropdown-blanks', $settings ), 'New settings validate.' );
foreach ( array(
	array( array( '1' => array( 'positive' ), '2' => 'up' ), 0.7 ),
	array( array( '1' => array( 'positive', 'rising' ), '2' => 'up' ), 1.0 ),
	array( array( '1' => array( 'positive', 'positive' ), '2' => 'up' ), 0.7 ),
	array( array( '1' => array( 'positive', 'negative' ), '2' => 'up' ), 0.4 ),
	array( array( '1' => array( 'outside' ), '2' => 'up' ), 0.4 ),
	array( array( '1' => array( 'positive' ), '2' => 'down' ), 0.3 ),
	array( array( '1' => array(), '2' => '' ), 0.0 ),
) as $case ) {
	$result = Grader::grade( $snapshot( $settings ), $case[0] );
	$check( ! is_wp_error( $result ) && abs( $result['fraction'] - $case[1] ) < 0.000001, 'Frozen weighted grading: ' . wp_json_encode( $case[0] ) );
}
$equal = $snapshot( $settings );
$settings['slots'][0]['grading'] = 'any';
$result = Grader::grade( $snapshot( $settings ), array( '1' => array( 'rising' ), '2' => 'up' ) );
$check( ! is_wp_error( $result ) && $result['correct'] && 1.0 === $result['fraction'], 'Any correct selection earns full dropdown points.' );
$check( 0.7 === Grader::grade( $equal, array( '1' => array( 'rising' ), '2' => 'up' ) )['fraction'], 'Editing a draft does not change an older snapshot.' );
$settings['slots'][0]['grading'] = 'no-wrong';
$check( true === Interactive::validate_settings( 'dropdown-blanks', $settings ), 'No-wrong settings validate.' );
foreach ( array(
	array( array( 'positive' ), 1.0 ),
	array( array( 'positive', 'rising' ), 1.0 ),
	array( array( 'positive', 'negative' ), 0.4 ),
	array( array( 'negative' ), 0.4 ),
	array( array( 'outside' ), 0.4 ),
	array( array(), 0.4 ),
) as $case ) {
	$result = Grader::grade( $snapshot( $settings ), array( '1' => $case[0], '2' => 'up' ) );
	$check( ! is_wp_error( $result ) && abs( $result['fraction'] - $case[1] ) < 0.000001, 'No-wrong grading requires a nonempty correct subset: ' . wp_json_encode( $case[0] ) );
}
$check( 0.0 === Grader::grade( $snapshot( $settings ), array() )['fraction'], 'An unanswered question earns no points in no-wrong mode.' );
$view = $snapshot( $settings )->student_view();
$check( ! array_intersect( array( 'answers', 'answer', 'grading', 'points' ), array_keys( $view['settings']['slots'][0] ) ), 'Private grading settings never reach learners.' );
$check( true === $view['settings']['slots'][0]['multiple'], 'Learner delivery retains multiple-selection control.' );
$check( is_wp_error( Grader::grade( $equal, array( '1' => array( array( 'positive' ) ) ) ) ), 'Nested learner arrays are rejected.' );
$check( is_wp_error( Grader::grade( $equal, array( '1' => array_fill( 0, 201, 'positive' ) ) ) ), 'Selection lists are bounded.' );
$bad = $settings;
$bad['score']['value'] = 999;
$check( true !== Interactive::validate_settings( 'dropdown-blanks', $bad ), 'Question total must match dropdown points.' );
$bad = $settings;
$bad['slots'][0]['points'] = -1;
$check( true !== Interactive::validate_settings( 'dropdown-blanks', $bad ), 'Negative dropdown points are rejected.' );
$legacy = array( 'text' => '{1} {2}', 'slots' => array( array( 'id' => '1', 'choices' => array( 'a', 'b' ), 'answer' => 'a' ), array( 'id' => '2', 'choices' => array( 'a', 'b' ), 'answer' => 'a' ) ) );
$check( 0.0 === Interactive::grade( 'dropdown-blanks', array( '1' => 'a', '2' => 'b' ), $legacy )['fraction'], 'Legacy all-or-nothing behavior stays unchanged.' );
$legacy['partial_credit'] = true;
$check( 0.5 === Interactive::grade( 'dropdown-blanks', array( '1' => 'a', '2' => 'b' ), $legacy )['fraction'], 'Legacy equal-slot partial credit stays unchanged.' );
echo $checks . " dropdown grading checks passed.\n";
