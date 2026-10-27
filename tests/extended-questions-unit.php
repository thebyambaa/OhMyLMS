<?php
/**
 * Isolated checks for the extended question grading and privacy contracts.
 *
 * @package OhMyLMS\Tests
 */

define( 'ABSPATH', __DIR__ );
require __DIR__ . '/php/fixtures/class-wp-error.php';
/**
 * Preserve the translation API signature in isolated tests.
 *
 * @param string $text Source text.
 * @param string $domain Translation domain.
 * @return string
 */
function __( $text, $domain = '' ) {
	unset( $domain );
	return $text;
}
/**
 * Preserve the escaping API in the isolated diagnostic harness.
 *
 * @param string $text Diagnostic text.
 * @return string
 */
function esc_html( $text ) {
	return htmlspecialchars( $text, ENT_QUOTES );
}
require __DIR__ . '/../includes/Assessment/ExtendedQuestions.php';
require __DIR__ . '/../includes/Assessment/Structured.php';
use OhMyLMS\Assessment\ExtendedQuestions as Q;
$checks = 0;
/**
 * Assert one assessment invariant.
 *
 * @param bool   $condition Expected condition.
 * @param string $label Diagnostic label.
 * @throws RuntimeException When the invariant fails.
 */
function check( $condition, $label ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $label ) );
	} ++$checks; }
$cases = array(
	'hot-text'          => array(
		array(
			'tokens'  => array(
				array(
					'id'   => 'a',
					'text' => '2',
				),
				array(
					'id'   => 'b',
					'text' => '3',
				),
			),
			'correct' => array( 'a' ),
		),
		array( 'a' => '1' ),
		array( 'b' => '1' ),
	),
	'match-table-grid'  => array(
		array(
			'rows'    => array(
				array(
					'id'    => 'r',
					'label' => '2+2',
				),
			),
			'columns' => array(
				array(
					'id'    => 'c',
					'label' => '4',
				),
			),
			'key'     => array( 'r' => 'c' ),
		),
		array( 'r' => 'c' ),
		array( 'r' => 'wrong' ),
	),
	'labeling'          => array(
		array(
			'image_url' => 'https://example.test/image.png',
			'targets'   => array(
				array(
					'id'     => 'a',
					'x'      => 25,
					'y'      => 50,
					'answer' => 'Circle',
				),
			),
		),
		array( 'a' => 'Circle' ),
		array( 'a' => 'Square' ),
	),
	'hotspot'           => array(
		array(
			'image_url' => 'https://example.test/image.png',
			'zones'     => array(
				array(
					'x'      => 50,
					'y'      => 50,
					'radius' => 10,
				),
			),
		),
		array(
			'x' => '50',
			'y' => '50',
		),
		array(
			'x' => '1',
			'y' => '1',
		),
	),
	'graphing'          => array(
		array(
			'min'    => -5,
			'max'    => 5,
			'mode'   => 'points',
			'points' => array(
				array(
					'x' => 2,
					'y' => 3,
				),
			),
		),
		array( 'points' => '[{"x":2,"y":3}]' ),
		array( 'points' => '[{"x":0,"y":0}]' ),
	),
	'interactive-video' => array(
		array(
			'video_url'   => 'https://example.test/video.mp4',
			'checkpoints' => array(
				array(
					'id'     => 'a',
					'at'     => 5,
					'prompt' => '2+2?',
					'answer' => '4',
				),
			),
		),
		array( 'a' => '4' ),
		array( 'a' => '5' ),
	),
);
foreach ( $cases as $question_type => $case ) {
	list( $settings, $correct, $wrong ) = $case;
	check( true === Q::validate_settings( $question_type, $settings ), "$question_type settings" );
	check( Q::validate_answer( $question_type, $correct ), "$question_type response" );
	check( 1.0 === Q::grade( $question_type, $correct, $settings )['fraction'], "$question_type correct" );
	check( 0.0 === Q::grade( $question_type, $wrong, $settings )['fraction'], "$question_type incorrect" );
	$public = Q::public_view( $question_type, $settings );
	foreach ( array( 'correct', 'key', 'points', 'zones', 'answer', 'rubric' ) as $private ) {
		check( ! isset( $public[ $private ] ), "$question_type public key $private" ); }
	if ( 'labeling' === $question_type ) {
		check( ! isset( $public['targets'][0]['answer'] ), 'Label assignment private' ); }
	if ( 'interactive-video' === $question_type ) {
		check( ! isset( $public['checkpoints'][0]['answer'] ), 'Checkpoint answer private' ); }
}
foreach ( Q::MANUAL_TYPES as $question_type ) {
	check( Q::grade( $question_type, array( 'text' => 'Response' ), array() )['manual'], "$question_type pending review" ); }
foreach ( Q::UNSCORED_TYPES as $question_type ) {
	check(
		true === Q::validate_settings(
			$question_type,
			array(
				'score'   => array( 'value' => 0 ),
				'choices' => array(
					array(
						'id'   => 'a',
						'text' => 'Yes',
					),
					array(
						'id'   => 'b',
						'text' => 'No',
					),
				),
			)
		),
		"$question_type zero score"
	);
	check( Q::validate_settings( $question_type, array( 'score' => array( 'value' => 1 ) ) ) instanceof WP_Error, "$question_type cannot silently become graded" ); }
check( ! Q::validate_answer( 'audio-response', array( 'media' => 'javascript:alert(1)' ) ), 'Unsafe media URL rejected' );
check( ! Q::validate_answer( 'video-response', array( 'media' => 'data:text/html;base64,PHNjcmlwdD4=' ) ), 'Executable upload rejected' );
check( Q::validate_answer( 'audio-response', array( 'media' => 'data:audio/wav;base64,UklGRg==' ) ), 'Bounded audio accepted' );
check( ! Q::validate_answer( 'graphing', array( 'points' => '[{"x":999999,"y":0}]' ) ), 'Unbounded graph rejected' );
check( ! Q::validate_answer( 'draw', array( 'points' => '{bad}' ) ), 'Malformed drawing rejected' );
$line = array(
	'min'    => -5,
	'max'    => 5,
	'mode'   => 'line',
	'points' => array(
		array(
			'x' => 0,
			'y' => 1,
		),
		array(
			'x' => 1,
			'y' => 2,
		),
	),
);
check( 1.0 === Q::grade( 'graphing', array( 'points' => '[{"x":2,"y":3},{"x":3,"y":4}]' ), $line )['fraction'], 'Equivalent line accepted' );
check( 0.0 === Q::grade( 'graphing', array( 'points' => '[{"x":2,"y":3},{"x":2,"y":3,"extra":true}]' ), $line )['fraction'], 'Duplicate coordinates cannot masquerade as a line' );
check( Q::validate_settings( 'graphing', array_replace( $line, array( 'max' => 0.5 ) ) ) instanceof WP_Error, 'Key must fit authored axes' );
check(
	Q::validate_settings(
		'hot-text',
		array(
			'tokens'  => array(
				array(
					'id'   => 'a',
					'text' => 'A',
				),
			),
			'correct' => array( array( 'a' ) ),
		)
	) instanceof WP_Error,
	'Malformed token keys rejected without warnings'
);
check( Q::validate_settings( 'audio-response', array( 'max_seconds' => 301 ) ) instanceof WP_Error, 'Recording duration bounded' );
check(
	! Q::validate_answer(
		'hotspot',
		array(
			'x' => -1,
			'y' => 50,
		)
	),
	'Hotspot coordinates bounded'
);
check(
	! Q::validate_answer(
		'draw',
		array(
			'one' => str_repeat( 'a', 3000001 ),
			'two' => str_repeat( 'a', 3000001 ),
		)
	),
	'Combined submission size bounded'
);
echo esc_html( "$checks extended question checks passed.\n" );
