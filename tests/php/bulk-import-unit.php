<?php
/**
 * Unit checks for the question bulk import: parsing, normalising, keys, validation and payloads.
 * Pure functions only; no WordPress needed.
 *
 * Run: php tests/php/bulk-import-unit.php
 */
define( 'ABSPATH', __DIR__ );
require dirname( __DIR__, 2 ) . '/includes/QuestionBank/BulkImport.php';
require dirname( __DIR__, 2 ) . '/includes/Assessment/Structured.php';
require dirname( __DIR__, 2 ) . '/includes/Assessment/NumericAnswer.php';
function check( $value, $message ) {
	if ( ! $value ) {
		throw new RuntimeException( $message );
	}
}
function __( $text, $domain = '' ) {
	return $text;
}
function wp_json_encode( $value ) {
	return json_encode( $value );
}
// Structured::public_parts() filters part prompts; these checks are about which fields are kept.
function wp_kses_post( $html ) {
	return $html;
}
class WP_Error {
	public $code;
	public function __construct( $code = '', $message = '', $data = '' ) {
		$this->code = $code;
	}
}
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}
use OhMyLMS\QuestionBank\BulkImport as B;

$known = static function ( $type ) {
	return in_array( $type, array( 'single-choice', 'multiple-choice', 'true-false', 'short-text', 'numerical', 'fill-in-the-blank', 'expression', 'structured' ), true );
};
$row   = static function ( array $over = array() ) {
	return B::normalize_row(
		$over + array(
			'exam'     => 'Digital SAT',
			'paper'    => '2023-03 US',
			'year'     => '2023',
			'section'  => 'Math M1',
			'number'   => '5',
			'type'     => 'single-choice',
			'question' => 'What is 2 + 2?',
			'answers'  => 'three|four|five|six',
			'correct'  => 'B',
			'skills'   => 'MATH.1',
		)
	);
};
$errs  = static function ( array $r ) use ( $known ) {
	return B::validate_row( $r, $known )['errors'];
};

// ---- Parsing ----
check( 2 === count( B::parse( '[{"number":1},{"number":2}]' ) ), 'JSON list' );
check( 1 === count( B::parse( '{"rows":[{"number":1}]}' ) ), 'JSON object holding rows' );
check( 1 === count( B::parse( array( array( 'number' => 1 ) ) ) ), 'Already decoded rows' );
check( is_wp_error( B::parse( '[{"number":1},' ) ), 'Broken JSON is an error' );
check( is_wp_error( B::parse( "  \n" ) ), 'Empty input is an error' );
check( is_wp_error( B::parse( '{"a":1}' ) ), 'A bare object is not a list of rows' );
check( is_wp_error( B::parse( '[1,2]' ) ), 'Rows must be objects' );
$csv = "\xEF\xBB\xBFExam, Paper ,Question Number,Type,Question,Answers,Correct,Skills\nSAT,P1,1,single-choice,\"Line one\nline two\",\"a|b\",A,M.1\n\nSAT,P1,2,single-choice,Second,\"c|d\",2,M.2;M.3\n";
$rows = B::parse( $csv );
check( is_array( $rows ) && 2 === count( $rows ), 'CSV rows, blank line skipped, BOM ignored' );
check( "Line one\nline two" === $rows[0]['question'], 'Quoted cell may span lines' );
check( isset( $rows[0]['question_number'] ) && isset( $rows[0]['paper'] ), 'Header names are normalised' );
check( is_wp_error( B::parse_csv( "a,b\n" ) ), 'Header without rows' );

// ---- Normalising ----
$n = B::normalize_row(
	array(
		'Question Number' => 7,
		'EXAM'            => ' IELTS ',
		'answers'         => ' a | b |  | c ',
		'skills'          => 'X.1; X.2,X.3',
		'lang'            => 'MN',
		'ignored'         => 'x',
	)
);
check( '7' === $n['number'] && 'IELTS' === $n['exam'], 'Aliases and trimming' );
check( array( 'a', 'b', 'c' ) === $n['answers'], 'Pipe list drops blanks' );
check( array( 'X.1', 'X.2', 'X.3' ) === $n['skills'], 'Skill separators' );
check( 'mn' === $n['language'] && 'single-choice' === $n['type'] && 'standard' === $n['difficulty'], 'Defaults and lowercasing' );
check( array( 'true' ) === B::normalize_row( array( 'correct' => true ) )['correct'], 'JSON true becomes "true"' );
check( array( 'false' ) === B::normalize_row( array( 'correct' => false ) )['correct'], 'JSON false becomes "false"' );
check( array( 'k' => 'v' ) === B::normalize_row( array( 'settings' => '{"k":"v"}' ) )['settings'], 'Settings JSON in a cell' );

// ---- Keys ----
check( 'digital sat|2023-03 us|2023|math m1|5|en' === B::key( $row() ), 'Key shape' );
check( B::key( $row() ) === B::key( $row( array( 'exam' => '  DIGITAL   sat ' ) ) ), 'Key ignores case and spacing' );
check( B::key( $row() ) !== B::key( $row( array( 'language' => 'mn' ) ) ), 'Language is part of the key' );
check( '' === B::key( $row( array( 'number' => '' ) ) ), 'No number, no key' );
check( '' === B::key( $row( array( 'exam' => '', 'paper' => '' ) ) ), 'No exam or paper, no key' );
check( '' !== B::key( $row( array( 'exam' => '', 'paper' => 'Only paper' ) ) ), 'Paper alone is enough' );
check( '' === B::key( $row( array( 'section' => str_repeat( 'x', 200 ) ) ) ), 'Overlong key refused' );

// ---- Validation ----
check( array() === $errs( $row() ), 'A good single-choice row has no errors' );
check( array() === $errs( $row( array( 'correct' => '2' ) ) ), 'Correct by number' );
check( array() === $errs( $row( array( 'correct' => 'Four' ) ) ), 'Correct by answer text' );
check( $errs( $row( array( 'correct' => 'Z' ) ) ), 'Correct letter outside the answers' );
check( $errs( $row( array( 'correct' => '' ) ) ), 'Correct answer required' );
check( $errs( $row( array( 'correct' => 'A|B' ) ) ), 'Single-choice cannot have two correct answers' );
check( $errs( $row( array( 'answers' => 'only one' ) ) ), 'Too few answers' );
check( array() === $errs( $row( array( 'type' => 'multiple-choice', 'correct' => 'A|C' ) ) ), 'Multiple-choice accepts several' );
check( array() === $errs( $row( array( 'type' => 'true-false', 'answers' => '', 'correct' => 'False' ) ) ), 'True/false with default answers' );
check( $errs( $row( array( 'type' => 'true-false', 'answers' => '', 'correct' => 'maybe' ) ) ), 'True/false needs true or false' );
check( array() === $errs( $row( array( 'type' => 'numerical', 'answers' => '', 'correct' => '3,5', 'tolerance' => '0.1' ) ) ), 'Numerical with decimal comma' );
check( $errs( $row( array( 'type' => 'numerical', 'answers' => '', 'correct' => 'abc' ) ) ), 'Numerical needs a number' );
check( $errs( $row( array( 'type' => 'numerical', 'answers' => '', 'correct' => '3', 'tolerance' => '-1' ) ) ), 'Negative tolerance refused' );
check( $errs( $row( array( 'type' => 'numerical', 'answers' => '', 'correct' => '3', 'tolerance_type' => 'sideways' ) ) ), 'Unknown tolerance type refused' );
check( array() === $errs( $row( array( 'type' => 'fill-in-the-blank', 'question' => 'The capital is {Paris}.', 'answers' => '', 'correct' => '' ) ) ), 'Fill in the blank with braces' );
check( $errs( $row( array( 'type' => 'fill-in-the-blank', 'question' => 'No blanks here', 'answers' => '', 'correct' => '' ) ) ), 'Fill in the blank needs braces' );
check( $errs( $row( array( 'type' => 'nonsense' ) ) ), 'Unknown type' );
check( $errs( $row( array( 'type' => 'expression', 'answers' => '', 'correct' => '' ) ) ), 'Registered custom type needs settings' );
check( array() === $errs( $row( array( 'type' => 'expression', 'answers' => '', 'correct' => '', 'settings' => array( 'expected' => 'x+1' ) ) ) ), 'Custom type with settings' );
check( $errs( $row( array( 'skills' => '' ) ) ), 'Skills required' );
check( $errs( $row( array( 'difficulty' => 'brutal' ) ) ), 'Unknown difficulty' );
check( $errs( $row( array( 'marks' => 'lots' ) ) ), 'Marks must be numeric' );
check( $errs( $row( array( 'question' => '' ) ) ), 'Question text required' );
check( $errs( $row( array( 'number' => '' ) ) ), 'Identity required' );
$warn = B::validate_row( $row( array( 'type' => 'short-text', 'answers' => '', 'correct' => '' ) ), $known )['warnings'];
check( $warn, 'Manual types warn that they cannot feed practice' );
check( B::validate_row( $row( array( 'year' => '23' ) ), $known )['warnings'], 'Odd year warns' );

// ---- AI classification fields ----
check( '0.8' === $row( array( 'ai_confidence' => 0.8 ) )['confidence'], 'ai_confidence alias' );
check( 'Why.' === $row( array( 'rationale' => ' Why. ' ) )['notes'] && 'Why.' === $row( array( 'ai_notes' => 'Why.' ) )['notes'], 'notes aliases' );
check( array() === $errs( $row( array( 'confidence' => '0.5' ) ) ) && array() === $errs( $row( array( 'confidence' => 0 ) ) ) && array() === $errs( $row( array( 'confidence' => 1 ) ) ), 'Confidence 0 to 1 accepted' );
check( $errs( $row( array( 'confidence' => '1.7' ) ) ) && $errs( $row( array( 'confidence' => '-0.1' ) ) ) && $errs( $row( array( 'confidence' => 'high' ) ) ), 'Confidence outside 0 to 1 or not a number refused' );
check( array() === $errs( $row() ), 'Confidence is optional' );
$with_notes = B::payload( $row( array( 'confidence' => '0.5', 'notes' => 'Unsure.' ) ), array( 11 ), 0, 0 );
check( ! isset( $with_notes['confidence'] ) && ! isset( $with_notes['notes'] ), 'Review fields are not written into the question itself' );
check( B::fingerprint( B::payload( $row(), array( 11 ), 0, 0 ) ) === B::fingerprint( B::payload( $row( array( 'confidence' => '0.5', 'notes' => 'Unsure.' ) ), array( 11 ), 0, 0 ) ), 'Changing only confidence or notes does not change the fingerprint' );

// ---- Payload ----
$p = B::payload( $row( array( 'family' => 'Fam-1', 'difficulty' => 'challenge', 'explanation' => 'Because.', 'marks' => '2', 'skills' => 'A;B;C' ) ), array( 11, 22, 33 ), 5, 99 );
check( 'Digital SAT 2023-03 US 2023 · Q5' === $p['name'], 'Default title' );
check( 'What is 2 + 2?' === $p['description'] && 'publish' === $p['status'], 'Question body and status' );
check( 'single-choice' === $p['settings']['type'] && 'Because.' === $p['settings']['explanation'] && 2.0 === $p['settings']['score'], 'Settings carry type, explanation, marks' );
check( 4 === count( $p['questions'] ) && true === $p['questions'][1]['is_correct'] && false === $p['questions'][0]['is_correct'], 'Options mark the right answer' );
check( 11 === $p['skills']['p1']['primary'] && array( 22, 33 ) === $p['skills']['p1']['supporting'], 'First skill primary, the rest supporting' );
check( 'challenge' === $p['bank']['difficulty'] && 'fam-1' !== $p['bank']['family_id'] && 'Fam-1' === $p['bank']['family_id'] && 5 === $p['bank']['bank_id'], 'Bank attributes' );
check( 'Digital SAT · 2023-03 US · 2023 · Math M1 · Q5' === $p['bank']['source'], 'Source string' );
check( 99 === $p['thumbnail_id'], 'Image attachment' );
$no_bank = B::payload( $row(), array( 11 ), 0, 0 );
check( ! isset( $no_bank['bank']['bank_id'] ) && ! isset( $no_bank['thumbnail_id'] ) && ! isset( $no_bank['bank']['family_id'] ), 'Optional parts omitted' );
$num = B::payload( $row( array( 'type' => 'numerical', 'answers' => '', 'correct' => '3.5', 'tolerance' => '0.1', 'unit' => 'cm' ) ), array( 11 ), 0, 0 );
check( 3.5 === $num['settings']['answer'] && 0.1 === $num['settings']['tolerance'] && 'cm' === $num['settings']['unit'] && ! isset( $num['questions'] ), 'Numerical settings, no options' );
$fib = B::payload( $row( array( 'type' => 'fill-in-the-blank', 'question' => 'The capital is {Paris}.', 'answers' => '', 'correct' => '' ) ), array( 11 ), 0, 0 );
check( 'The capital is {Paris}.' === $fib['name'] && ! isset( $fib['description'] ), 'Blanks live in the name' );
$custom = B::payload( $row( array( 'type' => 'expression', 'answers' => '', 'correct' => '', 'settings' => array( 'expected' => 'x+1' ), 'options_raw' => array( array( 'answer' => 'x', 'is_correct' => true ) ) ) ), array( 11 ), 0, 0 );
check( 'x+1' === $custom['settings']['expected'] && 'expression' === $custom['settings']['type'] && 1 === count( $custom['questions'] ), 'Custom type passes settings and options through' );

// ---- Fingerprint (idempotency) ----
check( B::fingerprint( $p ) === B::fingerprint( $p ), 'Same payload, same fingerprint' );
$changed = $p;
$changed['questions'][2]['is_correct'] = true;
check( B::fingerprint( $p ) !== B::fingerprint( $changed ), 'Changed content, new fingerprint' );
check( 100 >= mb_strlen( B::source( $row( array( 'section' => str_repeat( 'é', 150 ) ) ) ) ), 'Source limited to 100 characters' );
check( array( 'exam' => 'Digital SAT', 'paper' => '2023-03 US', 'year' => '2023', 'section' => 'Math M1', 'number' => '5', 'language' => 'en' ) === B::paper_fields( $row() ), 'Paper fields kept for rebuilding papers' );

// ---- Multipart import and part-level skills ----
$parts = array(
	array( 'id' => 'a_i', 'label' => '(a)(i)', 'kind' => 'numerical', 'prompt' => 'Find the length.', 'marks' => 2, 'answer' => 4, 'skills' => 'LENGTH;UNITS' ),
	array( 'id' => 'b', 'kind' => 'written', 'prompt' => 'Explain.', 'marks' => 3, 'rubric' => 'Award method marks after review.' ),
);
$multi = $row( array( 'type' => 'structured', 'parts' => $parts, 'skills' => 'REASONING' ) );
check( array() === $errs( $multi ), 'Structured parts accept existing kinds and inherited skills' );
check( array( 'LENGTH', 'UNITS', 'REASONING' ) === B::skill_tokens( $multi ), 'Part tokens included in batch lookup' );
$errors = array();
$map = B::skill_map( $multi, array( 'LENGTH' => 11, 'UNITS' => 22, 'REASONING' => 33 ), $errors );
check( ! $errors && array( 'primary' => 11, 'supporting' => array( 22 ) ) === $map['a_i'] && 33 === $map['b']['primary'], 'Per-part primary/supporting and fallback mapping' );
$payload = B::payload( $multi, array(), 0, 0, $map );
check( 5.0 === B::payload( $row( array( 'type' => 'structured', 'parts' => $parts, 'marks' => 5 ) ), array(), 0, 0, $map )['settings']['score']['value'], 'Explicit structured total retains the scoring engine contract' );
check( $map === $payload['skills'] && ! isset( $payload['skills']['p1'] ), 'Writer receives actual stable part IDs' );
check( true === $payload['settings']['score']['enabled'] && 5.0 === $payload['settings']['score']['value'] && 'a_i' === $payload['settings']['parts'][0]['id'], 'Total marks derived without changing IDs' );
check( ! isset( $payload['settings']['parts'][0]['skills'] ) && isset( $payload['settings']['parts'][1]['rubric'] ), 'Import tokens removed; private rubric retained' );
$question = new class( $payload['settings'] ) {
	private $settings;
	public function __construct( array $settings ) {
		$this->settings = $settings;
	}
	public function get_settings() {
		return $this->settings;
	}
};
$graded = \OhMyLMS\Assessment\Structured::grade( array( 'a_i' => '4', 'b' => 'I multiplied the sides.' ), $question );
check( true === $graded['manual'] && 0.4 === $graded['fraction'] && null === $graded['parts']['b'], 'Imported auto credit retained while written work awaits marking' );
$weights = \OhMyLMS\Assessment\Structured::weights( $payload['settings'] );
check( array( 'a_i' => 0.4, 'b' => 0.6 ) === $weights, 'Part evidence weights follow imported marks' );
$safe = \OhMyLMS\Assessment\Structured::public_parts( $payload['settings'] );
check( ! isset( $safe[0]['answer'] ) && ! isset( $safe[1]['rubric'] ), 'Imported parts public projection excludes answer and rubric' );
$legacy = $row( array( 'type' => 'structured', 'settings' => array( 'parts' => $parts ), 'skills' => 'REASONING' ) );
check( array() === $errs( $legacy ) && B::fingerprint( $payload ) === B::fingerprint( B::payload( $legacy, array(), 0, 0, $map ) ), 'Existing settings.parts and new parts produce identical content' );
check( $errs( $row( array( 'parts' => $parts ) ) ), 'Non-structured rows cannot silently discard parts' );
check( $errs( $row( array( 'type' => 'structured', 'parts' => $parts, 'marks' => 4 ) ) ), 'Conflicting whole-question marks rejected' );
check( array() === $errs( $row( array( 'type' => 'structured', 'parts' => $parts, 'marks' => 5 ) ) ), 'Matching total accepted' );
check( $errs( $row( array( 'type' => 'structured', 'parts' => $parts, 'skills' => '' ) ) ), 'Every part needs explicit or fallback skills' );
$explicit = $parts;
$explicit[1]['skills'] = array( 'REASONING' );
check( array() === $errs( $row( array( 'type' => 'structured', 'parts' => $explicit, 'skills' => '' ) ) ), 'All part skills can replace row skills' );
$explicit[1]['skills'] = array();
check( $errs( $row( array( 'type' => 'structured', 'parts' => $explicit ) ) ), 'Explicitly empty part skills do not silently inherit' );
$unknown = array();
B::skill_map( $multi, array( 'LENGTH' => array( 11, 12 ), 'REASONING' => 33 ), $unknown );
check( 2 === count( $unknown ), 'Ambiguous and missing part skill tokens both reported' );
$changed = $map;
$changed['b']['primary'] = 44;
check( B::fingerprint( $payload ) !== B::fingerprint( B::payload( $multi, array(), 0, 0, $changed ) ), 'Part skill changes affect repeat-import fingerprint' );
foreach ( array( 'broken JSON', array(), array( 'a' => $parts[0] ), array_fill( 0, 51, $parts[0] ), array( 'not an object' ) ) as $invalid ) {
	check( $errs( $row( array( 'type' => 'structured', 'parts' => $invalid ) ) ), 'Malformed or unbounded parts rejected' );
}
foreach ( array(
	array( 'id' => array() ),
	array( 'id' => 'b' ),
	array( 'marks' => INF ),
	array( 'marks' => '1e309' ),
	array( 'accepted' => array( array() ) ),
	array( 'answers' => array() ),
	array( 'tolerance' => INF ),
	array( 'tolerance_type' => 'unknown' ),
	array( 'kind' => 'uninstalled' ),
) as $overrides ) {
	$invalid = $parts;
	$invalid[0] = array_replace( $parts[0], $overrides );
	check( $errs( $row( array( 'type' => 'structured', 'parts' => $invalid ) ) ), 'Invalid structured answer configuration rejected safely' );
}
check( $errs( $row( array( 'marks' => '1e309' ) ) ), 'Nonfinite top-level marks rejected' );
$encoded = $row( array( 'type' => 'structured', 'parts' => json_encode( $parts ) ) );
check( array() === $errs( $encoded ) && $parts === $encoded['parts'], 'CSV JSON cell supports multipart data' );
check( $errs( $row( array( 'type' => 'structured', 'parts' => $parts, 'settings' => array( 'parts' => array( $parts[0] ) ) ) ) ), 'Conflicting duplicate part definitions rejected' );

foreach ( array( 'broken', 4, array( 'x' => 'broken' ) ) as $invalid ) {
	check( $errs( $row( array( 'type' => 'structured', 'settings' => array( 'parts' => $invalid ) ) ) ), 'Malformed legacy settings.parts fails without fatal errors' );
}
check( B::validate_row( $multi, $known )['warnings'], 'Written multipart import explains teacher marking' );
echo "bulk-import-unit: all checks passed\n";
