<?php
/**
 * Pure unit checks for the OhMyLMS quiz file format (no WordPress, no database).
 *
 *   php tests/php/quiz-document-unit.php
 */
define( 'ABSPATH', __DIR__ . '/' );
function __( $text ) {
	return $text;
}
function _n( $single, $plural, $number ) {
	return 1 === $number ? $single : $plural;
}
function wp_json_encode( $value, $flags = 0 ) {
	return json_encode( $value, $flags );
}
require dirname( __DIR__, 2 ) . '/includes/Quiz/Exchange/Document.php';

use OhMyLMS\Quiz\Exchange\Document as D;

$checks = 0;
function check( $condition, $message ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$checks;
}
function encoded( $value ) {
	return is_string( $value ) ? $value : json_encode( $value, JSON_PRESERVE_ZERO_FRACTION );
}
function parsed( $value ) {
	$result = D::parse( encoded( $value ) );
	check( ! ( $result instanceof WP_Error ), 'Expected a valid file' );
	return $result;
}
function problems_of( $value ) {
	$result = D::parse( encoded( $value ) );
	check( $result instanceof WP_Error, 'Expected the file to be refused' );
	return $result->get_error_data()['errors'];
}
/** Minimal stand-in for WordPress's error class. */
class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code = '', $message = '', $data = '' ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code() {
		return $this->code;
	}
	public function get_error_message() {
		return $this->message;
	}
	public function get_error_data() {
		return $this->data;
	}
}
function messages( array $problems ) {
	return implode( ' | ', array_map( array( D::class, 'describe' ), $problems ) );
}

$choice = array(
	'name'        => 'Question 1',
	'description' => '<p>What is 2 + 2?</p>',
	'settings'    => array(
		'type'  => 'single-choice',
		'score' => array(
			'enabled' => true,
			'value'   => 2,
		),
	),
	'options'     => array(
		array(
			'answer'     => '3',
			'is_correct' => false,
		),
		array(
			'answer'     => '4',
			'is_correct' => true,
		),
	),
);

// ---- A valid file ----
$file   = array(
	'format'    => 'ohmylms.quiz',
	'version'   => 1,
	'quiz'      => array(
		'name'     => 'Algebra',
		'settings' => array( 'allow_attempts' => 3 ),
	),
	'questions' => array( $choice ),
);
$result = parsed( $file );
$doc    = $result['document'];
check( 'Algebra' === $doc['quiz']['name'] && 3 === $doc['quiz']['settings']['allow_attempts'], 'Quiz fields read' );
check( 'single-choice' === $doc['questions'][0]['settings']['type'] && 2 === count( $doc['questions'][0]['options'] ), 'Question and options read' );
check( $doc['questions'][0]['options'][1]['is_correct'] === true && $doc['questions'][0]['options'][0]['is_correct'] === false, 'Correctness read' );
check( array() === $result['warnings'], 'A clean file has no warnings' );
$summary = D::summary( $doc );
check( 1 === $summary['questions'] && 2.0 === (float) $summary['points'] && array( 'single-choice' => 1 ) === $summary['types'], 'Summary counts questions, points and types' );

// ---- Round trip is exact ----
$again = parsed( D::render( $doc ) )['document'];
check( $again == $doc, 'render then parse returns the same document' );
check( D::render( $again ) === D::render( $doc ), 'rendering is stable' );

// ---- Randomized template survives untouched, including nested lists and floats ----
$template                       = array(
	'variables'   => array(
		array(
			'name' => 'a',
			'type' => 'int',
			'min'  => 2,
			'max'  => 9,
			'step' => 1,
		),
		array(
			'name'   => 'x',
			'type'   => 'decimal',
			'min'    => 1.5,
			'max'    => 5.0,
			'places' => 1,
		),
		array(
			'name'   => 'w',
			'type'   => 'choice',
			'values' => array( 'apples', 'pears' ),
		),
		array(
			'name'   => 'c',
			'type'   => 'expr',
			'expr'   => 'a*2',
			'places' => 0,
		),
	),
	'constraints' => array( 'a>3', 'gcd(a,6)=1' ),
	'set'         => array(
		array(
			'path' => 'answer',
			'expr' => 'a*x',
		),
	),
);
$random                         = $choice;
$random['description']          = '<p>{{a}} {{w}} cost {{x:2}} each. Total?</p>';
$random['settings']             = array(
	'type'     => 'numerical',
	'score'    => array(
		'enabled' => true,
		'value'   => 1,
	),
	'answer'   => 0,
	'template' => $template,
);
$random['options']              = array();
$doc2                           = parsed( array( 'questions' => array( $random ) ) )['document'];
check( $doc2['questions'][0]['settings']['template'] === $template, 'Template variables, constraints and computed settings are kept exactly' );
check( strpos( D::render( $doc2 ), '"max": 5.0' ) !== false, 'A whole-number float stays a float' );
check( strpos( D::render( $doc2 ), '{{x:2}}' ) !== false, 'Placeholders are written unescaped' );
check( 1 === D::summary( $doc2 )['randomized'], 'Summary counts randomized questions' );
check( parsed( D::render( $doc2 ) )['document'] == $doc2, 'A randomized question round-trips' );

// ---- Text in any language, and readable output ----
$mn                 = $choice;
$mn['description']  = '<p>Хоёр дээр хоёр нийлбэл хэд вэ? ½ ≈ 0.5 \\frac{1}{2} https://x.mn/a</p>';
$out                = D::render( parsed( array( 'questions' => array( $mn ) ) )['document'] );
check( strpos( $out, 'Хоёр дээр хоёр' ) !== false, 'Mongolian text is written as text, not \\u escapes' );
check( strpos( $out, 'https://x.mn/a' ) !== false && strpos( $out, '\\/' ) === false, 'Slashes are not escaped' );
check( parsed( $out )['document']['questions'][0]['description'] === $mn['description'], 'Mongolian text and LaTeX braces survive' );
check( parsed( "\xEF\xBB\xBF" . $out ) !== null, 'A byte order mark is ignored' );

// ---- Friendly shorthands ----
$short = array(
	'type'      => 'true-false',
	'name'      => 'Q',
	'questions' => array(
		array(
			'answer'     => 'True',
			'is_correct' => 'true',
		),
		array(
			'answer'     => 'False',
			'is_correct' => '0',
		),
	),
);
$doc3  = parsed( array( $short ) )['document'];
check( 'true-false' === $doc3['questions'][0]['settings']['type'], 'A top-level type is accepted' );
check( true === $doc3['questions'][0]['settings']['score']['enabled'] && 1 === $doc3['questions'][0]['settings']['score']['value'], 'A missing score becomes 1 point' );
check( true === $doc3['questions'][0]['options'][0]['is_correct'] && false === $doc3['questions'][0]['options'][1]['is_correct'], '"true" and "0" read as booleans' );
check( 2 === count( $doc3['questions'][0]['options'] ), '"questions" works as a name for the options' );
check( parsed( array( 'questions' => array( $choice ) ) )['document']['quiz']['name'] === '', 'The quiz block is optional' );

// ---- Matching, images and skills ----
$matching                   = $choice;
$matching['settings']       = array( 'type' => 'matching' );
$matching['options']        = array(
	array(
		'answer'        => 'Cat',
		'matching_data' => array(
			'label'     => 'Meows',
			'image_url' => 'https://example.com/cat.png',
		),
		'image_url'     => 'https://example.com/c.png',
	),
	array(
		'answer'        => 5,
		'matching_data' => array( 'label' => 7 ),
	),
);
$matching['image_url']      = 'https://example.com/q.jpg';
$matching['skills']         = array(
	'p1' => array(
		'primary'    => array(
			'uuid' => 'abc',
			'slug' => 'fractions',
			'name' => 'Fractions',
		),
		'supporting' => array( 'ratios' ),
	),
);
$matching['bank']           = array(
	'difficulty' => 'easy',
	'unknown'    => 'x',
);
$doc4                       = parsed( array( 'questions' => array( $matching ) ) )['document']['questions'][0];
check( 'Meows' === $doc4['options'][0]['matching_data']['label'] && 'https://example.com/cat.png' === $doc4['options'][0]['matching_data']['image_url'], 'Matching definition and image read' );
check( '5' === $doc4['options'][1]['answer'] && '7' === $doc4['options'][1]['matching_data']['label'], 'Numbers become text' );
check( 'fractions' === $doc4['skills']['p1']['primary']['slug'] && 'ratios' === $doc4['skills']['p1']['supporting'][0]['slug'], 'Skills read as references; a bare string is a slug' );
check( array( 'difficulty' => 'easy' ) === $doc4['bank'], 'Only known bank attributes are kept' );
check( 3 === D::summary( array( 'questions' => array( $doc4 ) ) )['media'], 'Summary counts media addresses (question image, option image, matching image)' );

// ---- Refusals report every problem in one pass ----
$refused = problems_of( '{"questions": [' );
check( 0 === $refused[0]['question'] && strpos( $refused[0]['message'], 'not valid JSON' ) !== false, 'Broken JSON is refused with a clear message' );
check( strpos( messages( problems_of( '' ) ), 'empty' ) !== false, 'An empty file is refused' );
check( strpos( messages( problems_of( array( 'format' => 'something.else', 'questions' => array( $choice ) ) ) ), 'not an OhMyLMS quiz file' ) !== false, 'A different format is refused' );
check( strpos( messages( problems_of( array( 'version' => 99, 'questions' => array( $choice ) ) ) ), 'newer version' ) !== false, 'A newer version is refused' );
check( strpos( messages( problems_of( array( 'questions' => array() ) ) ), 'no questions' ) !== false, 'No questions is refused' );

$bad_a                      = $choice;
$bad_a['settings']['type']  = '';
$bad_b                      = $choice;
$bad_b['options']           = 'nope';
$bad_c                      = $choice;
$bad_c['options'][0]['answer'] = array( 'x' );
$bad_d                      = $choice;
$bad_d['image_url']         = 'javascript:alert(1)';
$errors                     = problems_of( array( 'questions' => array( $choice, $bad_a, $bad_b, 'text', $bad_c, $bad_d ) ) );
$numbers                    = array_map(
	static function ( $problem ) {
		return $problem['question'];
	},
	$errors
);
check( array( 2, 3, 4, 5, 6 ) === $numbers, 'One pass reports every bad question, by number: ' . implode( ',', $numbers ) );
check( strpos( messages( $errors ), 'Question 6: "image_url" must be a web address' ) !== false, 'Unsafe addresses are refused' );
$error = D::parse( encoded( array( 'questions' => array( $choice, $bad_a, $bad_b ) ) ) );
check( 'ohmylms_quiz_import_invalid' === $error->get_error_code() && 400 === $error->get_error_data()['status'], 'The error has a code and a 400 status' );
check( strpos( $error->get_error_message(), '2 problems found, nothing was imported' ) !== false, 'The message counts the problems: ' . $error->get_error_message() );

// ---- Warnings, not errors, for harmless oddities ----
$odd         = $choice;
$odd['hint'] = 'Think!';
$odd['id']   = 55;
$odd['uuid'] = 'x';
$result      = parsed( array( 'questions' => array( $odd ), 'quiz' => array( 'name' => 'N', 'colour' => 'red' ) ) );
check( 2 === count( $result['warnings'] ), 'A misplaced hint and an unknown quiz field warn; pasted ids do not: ' . messages( $result['warnings'] ) );
check( strpos( messages( $result['warnings'] ), 'belong inside "settings"' ) !== false, 'The warning says where a hint goes' );

// ---- Detection ----
check( D::looks_like( " \n{ }" ) && D::looks_like( '[1]' ) && D::looks_like( "\xEF\xBB\xBF{" ), 'JSON is recognised' );
check( ! D::looks_like( "type,question\nsingle-choice,Hi" ) && ! D::looks_like( '' ), 'CSV is not mistaken for JSON' );
check( strlen( D::render( $doc ) ) > 0 && D::MAX_QUESTIONS === 1000, 'Limits are defined' );
$huge = problems_of( str_repeat( ' ', D::MAX_BYTES + 1 ) );
check( strpos( $huge[0]['message'], '10 MB' ) !== false, 'An oversized file is refused' );

echo "OK: $checks checks\n";
