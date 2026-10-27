<?php
/** Pure unit checks for randomised question templates (no WordPress). */
define( 'ABSPATH', __DIR__ . '/' );
function __( $text ) {
	return $text; }
function wp_json_encode( $value ) {
	return json_encode( $value ); }
function wp_rand( $min = 0, $max = 0 ) {
	return random_int( $min ?: 0, $max ?: 0x7fffffff ); }
require dirname( __DIR__ ) . '/vendor/autoload.php';
use OhMyLMS\Assessment\Cas\Expression as E;
use OhMyLMS\Assessment\Interactive as I;
use OhMyLMS\Assessment\NumericAnswer as N;
use OhMyLMS\Assessment\QuestionSnapshot;
use OhMyLMS\Assessment\Template as T;

$checks = 0;
function check( $condition, $message ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message ); }
	++$checks;
}

// Expression engine: the functions templates use.
$val = static function ( $text, $vars = array() ) {
	$tree = E::parse( $text );
	return $tree === null ? 'parse' : E::evaluate( $tree, $vars );
};
check( $val( 'gcd(12,18)' ) === 6.0 && $val( 'gcd(7,5)' ) === 1.0 && $val( 'lcm(4,6)' ) === 12.0, 'gcd and lcm' );
check( $val( 'mod(17,5)' ) === 2.0 && $val( 'mod(-1,5)' ) === 4.0 && $val( 'mod(5,0)' ) === null, 'mod follows the divisor and refuses zero' );
check( $val( 'min(3,1,2)' ) === 1.0 && $val( 'max(3,1,2)' ) === 3.0, 'min and max take several arguments' );
check( $val( 'round(2.567,2)' ) === 2.57 && $val( 'round(2.5)' ) === 3.0 && $val( 'floor(-1.5)' ) === -2.0 && $val( 'ceil(1.2)' ) === 2.0, 'rounding functions' );
check( $val( 'gcd(1.5,3)' ) === null && $val( 'sin(1,2)' ) === null && $val( 'gcd(1)' ) === null, 'bad arguments are undefined, not errors' );
check( $val( 'gcd(a,b)', array( 'a' => 8.0, 'b' => 12.0 ) ) === 4.0, 'functions take variables' );
check( E::parse( 'f(1,2,3,4)' ) === null && E::parse( 'gcd(1,)' ) === null && E::parse( '(1,2)' ) === null && E::parse( '1,2' ) === null, 'commas only separate function arguments' );

// Generation: deterministic, in range, constrained.
$template = array(
	'variables'   => array(
		array( 'name' => 'a', 'type' => 'int', 'min' => 2, 'max' => 12 ),
		array( 'name' => 'b', 'type' => 'int', 'min' => 2, 'max' => 12, 'exclude' => array( 7 ) ),
		array( 'name' => 'x', 'type' => 'decimal', 'min' => 1, 'max' => 2, 'places' => 2 ),
		array( 'name' => 'w', 'type' => 'choice', 'values' => array( 'apples', 'pears', 'plums' ) ),
		array( 'name' => 'c', 'type' => 'expr', 'expr' => 'a*b', 'places' => 0 ),
	),
	'constraints' => array( 'a>b', 'gcd(a,b)=1' ),
);
$first = T::generate( $template, 42 );
check( $first['ok'] && $first['params'] === T::generate( $template, 42 )['params'], 'the same seed gives the same numbers' );
$seen = array();
for ( $seed = 1; $seed <= 300; $seed++ ) {
	$g = T::generate( $template, $seed );
	$p = $g['params'];
	check( $g['ok'], "seed $seed generates" );
	check( $p['a'] > $p['b'] && $p['b'] !== 7 && $p['a'] >= 2 && $p['a'] <= 12, "seed $seed respects ranges and exclusions" );
	check( E::evaluate( E::parse( 'gcd(a,b)' ), array( 'a' => (float) $p['a'], 'b' => (float) $p['b'] ) ) === 1.0, "seed $seed is coprime" );
	check( $p['x'] >= 1 && $p['x'] <= 2 && abs( $p['x'] * 100 - round( $p['x'] * 100 ) ) < 1e-9, "seed $seed decimal places" );
	check( in_array( $p['w'], array( 'apples', 'pears', 'plums' ), true ) && $p['c'] == $p['a'] * $p['b'], "seed $seed choice and derived value" );
	$seen[ T::signature( $p ) ] = true;
}
check( count( $seen ) > 40, 'seeds give plenty of different questions' );
$impossible = array( 'variables' => array( array( 'name' => 'a', 'type' => 'int', 'min' => 1, 'max' => 3 ) ), 'constraints' => array( 'a>5' ) );
check( ! T::generate( $impossible, 1 )['ok'], 'impossible conditions fail instead of looping' );
check( ! T::generate( array( 'variables' => array( array( 'name' => 'a', 'type' => 'int', 'min' => 5, 'max' => 1 ) ) ), 1 )['ok'], 'empty range fails' );
$divisible = array( 'variables' => array( array( 'name' => 'a', 'type' => 'int', 'min' => 2, 'max' => 9 ), array( 'name' => 'b', 'type' => 'int', 'min' => 2, 'max' => 9 ) ), 'constraints' => array( 'mod(a,b)=0', 'a!=b' ) );
for ( $seed = 1; $seed <= 60; $seed++ ) {
	$p = T::generate( $divisible, $seed )['params'];
	check( $p['a'] % $p['b'] === 0 && $p['a'] !== $p['b'], 'divisibility condition' );
}
$stepped = array( 'variables' => array( array( 'name' => 'a', 'type' => 'int', 'min' => 10, 'max' => 50, 'step' => 10 ) ) );
for ( $seed = 1; $seed <= 40; $seed++ ) {
	check( T::generate( $stepped, $seed )['params']['a'] % 10 === 0, 'step' );
}

// Formatting and placeholders.
check( T::format( 3 ) === '3' && T::format( -2.5 ) === '-2.5' && T::format( 0.1 + 0.2 ) === '0.3' && T::format( 2.0, 2 ) === '2.00' && T::format( -0.0, 1 ) === '0.0' && T::format( 3, null, true ) === '+3' && T::format( -3, null, true ) === '-3', 'number formatting' );
$p = array( 'a' => 3, 'b' => -4, 'x' => 1.5, 'w' => 'pears' );
$sub = static function ( $text ) use ( $p ) {
	return T::substitute( $text, $p );
};
check( $sub( 'Add {{a}} and {{b}}' ) === 'Add 3 and -4', 'plain placeholders' );
check( $sub( '{{a}}x{{b:+}}' ) === '3x-4' && $sub( '{{a}}x{{a:+}}' ) === '3x+3', 'sign modifier' );
check( $sub( '{{x*3:2}} m' ) === '4.50 m' && $sub( 'about {{a/7:3}}' ) === 'about 0.429' && $sub( '{{a/7:3}}' ) === 0.429, 'places modifier' );
check( $sub( 'You have {{a}} {{w}}' ) === 'You have 3 pears', 'text variable' );
check( is_float( $sub( '{{a*2+1}}' ) ) && $sub( '{{a*2+1}}' ) == 7, 'a lone placeholder becomes a number' );
check( is_numeric( $sub( '{{ a*b }}' ) ) && $sub( '{{ a*b }}' ) == -12, 'spaces are fine' );
check( $sub( '{{w}}' ) === 'pears', 'a lone text variable stays text' );
check( $sub( 'cost {{a*b}}' ) === 'cost -12', 'embedded numbers are text' );
check( $sub( '\\frac{{{a}}}{2}' ) === '\\frac{3}{2}', 'braces around a placeholder survive' );
check( $sub( '$\\frac{1}{2}$ is half' ) === '$\\frac{1}{2}$ is half', 'single braces are left alone' );
$errors = array();
check( T::substitute( 'x {{zz+1}} y', $p, $errors ) === 'x {{zz+1}} y' && $errors === array( 'zz+1' ), 'an unknown value is left visible and reported' );
check( $sub( '{{a/0}}' ) === '{{a/0}}', 'undefined arithmetic is left visible' );
check( $sub( '{{gcd(12,18)}} and {{max(a,b)}}' ) === '6 and 3', 'functions in placeholders' );

// Applying a template to a whole question.
$settings = array(
	'type'        => 'numerical',
	'answer'      => 0,
	'unit'        => '{{w}}',
	'hint'        => 'Multiply {{a}} by {{b}}.',
	'explanation' => '{{a}} × {{b}} = {{a*b}}',
	'score'       => array( 'enabled' => true, 'value' => 1 ),
	'parts'       => array( array( 'id' => 'p', 'answer' => 0 ) ),
	'template'    => array(
		'variables' => array( array( 'name' => 'a', 'type' => 'int', 'min' => 2, 'max' => 9 ), array( 'name' => 'b', 'type' => 'int', 'min' => 2, 'max' => 9 ), array( 'name' => 'w', 'type' => 'choice', 'values' => array( 'cm' ) ) ),
		'set'       => array( array( 'path' => 'answer', 'expr' => 'a*b' ), array( 'path' => 'parts.0.answer', 'expr' => 'a+b' ) ),
	),
);
$options = array( array( 'id' => 1, 'answer' => '{{a*b}}', 'is_correct' => 1, 'matching_data' => array( 'label' => 'x{{a}}' ) ) );
$r       = T::apply( 'What is {{a}} × {{b}}?', '<p>Use {{a}} rows of {{b}}.</p>', $settings, $options, 7 );
$g       = T::generate( $settings['template'], 7 )['params'];
check( $r['ok'] && ! $r['errors'] && $r['title'] === 'What is ' . $g['a'] . ' × ' . $g['b'] . '?', 'title is instantiated' );
check( $r['body'] === '<p>Use ' . $g['a'] . ' rows of ' . $g['b'] . '.</p>', 'body is instantiated' );
check( ! isset( $r['settings']['template'] ), 'the template never reaches the concrete question' );
check( $r['settings']['answer'] == $g['a'] * $g['b'] && is_numeric( $r['settings']['answer'] ), 'a computed setting is a number' );
check( $r['settings']['parts'][0]['answer'] == $g['a'] + $g['b'], 'a nested path is set' );
check( $r['settings']['unit'] === 'cm' && $r['settings']['hint'] === 'Multiply ' . $g['a'] . ' by ' . $g['b'] . '.', 'text settings are instantiated' );
check( $r['settings']['explanation'] === $g['a'] . ' × ' . $g['b'] . ' = ' . ( $g['a'] * $g['b'] ), 'a worked solution shows the numbers' );
check( $r['options'][0]['answer'] === (string) ( $g['a'] * $g['b'] ) && $r['options'][0]['matching_data']['label'] === 'x' . $g['a'], 'option text is instantiated' );
check( $r['settings']['type'] === 'numerical' && $r['settings']['score']['value'] === 1, 'other settings are untouched' );
$other = T::apply( 'What is {{a}} × {{b}}?', '', $settings, $options, 8 );
check( $other['title'] !== $r['title'] || $other['params'] !== $r['params'], 'another seed asks something else' );
check( T::apply( 'Q', '', $settings, $options, 7 ) === T::apply( 'Q', '', $settings, $options, 7 ), 'applying twice gives an identical result' );
$broken = T::apply( 'Find {{q}}', '', $settings, $options, 1 );
check( $broken['errors'] === array( 'q' ), 'unknown names are reported' );

// A frozen version becomes a concrete question; grading uses the concrete numbers.
$version  = array(
	'id' => 5, 'question_id' => 9, 'question_uuid' => 'u', 'version_no' => 1, 'type' => 'numerical',
	'title' => 'What is {{a}} × {{b}}?', 'body' => '', 'settings' => $settings, 'options' => array(),
	'media' => array(), 'extension' => array(), 'parts' => array(),
);
$snapshot = new QuestionSnapshot( $version );
check( $snapshot->is_template() && $snapshot->get_instance() === null, 'a version knows it is a template' );
$one      = $snapshot->instantiate( 7 );
$two      = $snapshot->instantiate( 7 );
$three    = $snapshot->instantiate( 11 );
check( $one !== $snapshot && $one->get_name() === $two->get_name() && $one->get_name() !== '' && strpos( $one->get_name(), '{{' ) === false, 'instantiating does not alter the frozen version' );
check( strpos( $snapshot->get_name(), '{{' ) !== false && isset( $snapshot->get_settings()['template'] ), 'the version itself stays a template' );
check( $one->get_instance()['seed'] === 7 && $one->get_instance()['params'] === $g, 'the instance records its numbers' );
$answer7  = (string) ( $g['a'] * $g['b'] );
$grade7   = N::grade( array( $answer7 ), $one->get_settings() );
check( $grade7['correct'], 'the right answer for these numbers is correct' );
$g3       = T::generate( $settings['template'], 11 )['params'];
check( $g3['a'] * $g3['b'] === $g['a'] * $g['b'] || ! N::grade( array( $answer7 ), $three->get_settings() )['correct'], 'the answer of another instance is not accepted for this one' );
$plain    = new QuestionSnapshot( array_merge( $version, array( 'settings' => array( 'type' => 'numerical', 'answer' => 4 ) ) ) );
check( ! $plain->is_template() && $plain->instantiate( 5 ) === $plain, 'a plain question is returned unchanged' );

// Templates with expression and interactive types.
$expression = array(
	'type' => 'expression', 'answer' => '{{a}}(x+{{b}})', 'form' => 'any',
	'template' => array( 'variables' => array( array( 'name' => 'a', 'type' => 'int', 'min' => 2, 'max' => 5 ), array( 'name' => 'b', 'type' => 'int', 'min' => 1, 'max' => 9 ) ) ),
);
$ex = T::apply( 'Expand {{a}}(x+{{b}})', '', $expression, array(), 3 );
$ep = T::generate( $expression['template'], 3 )['params'];
check( I::grade( 'expression', array( ( $ep['a'] ) . 'x+' . ( $ep['a'] * $ep['b'] ) ), $ex['settings'] )['correct'], 'an expression template accepts the expanded form' );
check( ! I::grade( 'expression', array( $ep['a'] . 'x+' . ( $ep['a'] * $ep['b'] + 1 ) ), $ex['settings'] )['correct'], 'and rejects a wrong one' );
$blank = array(
	'type' => 'multi-blank', 'layout' => 'inline', 'text' => '{{a}} + {{b}} = {x}',
	'blanks' => array( 'x' => array( 'kind' => 'numerical', 'answer' => 0 ) ),
	'template' => array( 'variables' => array( array( 'name' => 'a', 'type' => 'int', 'min' => 1, 'max' => 9 ), array( 'name' => 'b', 'type' => 'int', 'min' => 1, 'max' => 9 ) ), 'set' => array( array( 'path' => 'blanks.x.answer', 'expr' => 'a+b' ) ) ),
);
$mb = T::apply( '', '', $blank, array(), 4 );
$bp = T::generate( $blank['template'], 4 )['params'];
check( I::grade( 'multi-blank', array( 'x' => (string) ( $bp['a'] + $bp['b'] ) ), $mb['settings'] )['correct'] && I::blank_ids( $mb['settings'] ) === array( 'x' ), 'a multi-blank template computes its blank and keeps its markers' );

// Authoring validation.
$check_ok = static function ( $example ) {
	return true; };
check( T::validate( $settings, 'What is {{a}} × {{b}}?', '', $options, $check_ok ) === true, 'a good template validates' );
$bad = static function ( $mutate ) use ( $settings, $options ) {
	$copy = $settings;
	$mutate( $copy );
	return T::validate( $copy, 'What is {{a}}?', '', $options, null );
};
check( $bad( static function ( &$s ) { $s['template']['variables'][0]['name'] = 'ab'; } ) !== true, 'long variable names are refused' );
check( $bad( static function ( &$s ) { $s['template']['variables'][0]['name'] = 'e'; } ) !== true, 'e is Euler, not a variable' );
check( $bad( static function ( &$s ) { $s['template']['variables'][1]['name'] = 'a'; } ) !== true, 'duplicate names are refused' );
check( $bad( static function ( &$s ) { $s['template']['variables'][0]['type'] = 'weird'; } ) !== true, 'unknown types are refused' );
check( $bad( static function ( &$s ) { $s['template']['variables'][0]['max'] = 1; } ) !== true, 'a reversed range is refused' );
check( $bad( static function ( &$s ) { $s['template']['constraints'] = array( 'a>100' ); } ) !== true, 'impossible conditions are refused' );
check( strpos( (string) T::validate( array_merge( $settings, array( 'template' => array_merge( $settings['template'], array( 'constraints' => array( 'a>100' ) ) ) ) ), 'Q', '', array(), null ), 'strict' ) !== false, 'and explained' );
check( $bad( static function ( &$s ) { $s['template']['set'][0]['expr'] = 'a*('; } ) !== true, 'a formula that cannot be read is refused' );
check( $bad( static function ( &$s ) { $s['template']['set'][0]['path'] = 'bad path!'; } ) !== true, 'a bad setting name is refused' );
check( T::validate( $settings, 'What is {{a}} × {{zz}}?', '', $options, null ) !== true, 'a placeholder that cannot be worked out is refused' );
check( T::validate( array_merge( $settings, array( 'template' => array( 'variables' => array( array( 'name' => 'a', 'type' => 'int', 'min' => 3, 'max' => 3 ) ) ) ) ), '{{a}}', '', array(), null ) !== true, 'a template that never changes is refused' );
check( T::validate( $settings, 'Q {{a}}', '', $options, static function ( $example ) { return 'bad example'; } ) !== true, 'an invalid example question is refused' );
check( strpos( (string) T::validate( $settings, 'Q {{a}}', '', $options, static function ( $example ) { return 'bad example'; } ), 'bad example' ) !== false, 'with the reason' );
$too_many = $settings;
$too_many['template']['variables'] = array_map( static function ( $i ) { return array( 'name' => chr( 97 + $i + ( $i >= 4 ? 1 : 0 ) ), 'type' => 'int', 'min' => 1, 'max' => 9 ); }, range( 0, 13 ) );
check( T::validate( $too_many, 'Q', '', array(), null ) !== true, 'at most twelve variables' );

// Examples for the author, and fresh numbers in practice.
$examples = T::samples( 'numerical', 'What is {{a}} × {{b}}?', '', $settings, $options, 4 );
check( count( $examples ) === 4 && $examples[0]['expected'] !== '' && ! $examples[0]['errors'] && strpos( $examples[0]['title'], '{{' ) === false, 'samples show concrete questions and answers' );
check( T::expected_text( 'numerical', array( 'answer' => 12 ), array() ) === '12' && T::expected_text( 'single-choice', array(), array( array( 'answer' => 'A', 'is_correct' => 0 ), array( 'answer' => 'B', 'is_correct' => 1 ) ) ) === 'B', 'expected answer text' );
$used = array();
$repeat = 0;
for ( $i = 0; $i < 12; $i++ ) {
	$seed = T::fresh_seed( $settings, array_keys( $used ) );
	$sig  = T::signature( T::generate( $settings['template'], $seed )['params'] );
	$repeat += isset( $used[ $sig ] ) ? 1 : 0;
	$used[ $sig ] = true;
}
check( $repeat === 0, 'practice avoids repeating numbers it already gave' );
check( T::fresh_seed( array(), array() ) > 0 && T::seed_from( 'x' ) === T::seed_from( 'x' ) && T::seed_from( 'x' ) !== T::seed_from( 'y' ), 'seeds' );
$math_token = '\left\lbrace\left\lbrace a\right\rbrace\right\rbrace';
$math_marker = '[[ohmylms-math:latex:inline]]' . $math_token . '[[/ohmylms-math]]';
$math_errors = array();
check( T::substitute( $math_marker, array( 'a' => -6.5 ), $math_errors ) === '[[ohmylms-math:latex:inline]]-6.5[[/ohmylms-math]]', 'saved MathLive literal double braces resolve inside marked equations' );
check( T::substitute( $math_token, array( 'a' => -6.5 ), $math_errors ) === $math_token, 'unmarked literal LaTeX is unchanged' );
check( T::substitute( '[[ohmylms-math:latex:display]]\frac{\{\{a*b\}\}}{\sqrt{x^2+1}}[[/ohmylms-math]]', array( 'a' => 3, 'b' => 4 ), $math_errors ) === '[[ohmylms-math:latex:display]]\frac{12}{\sqrt{x^2+1}}[[/ohmylms-math]]', 'nested equations retain fractions and roots around tokens' );
$unknown_math_errors = array();
T::substitute( $math_marker, array(), $unknown_math_errors );
check( ! empty( $unknown_math_errors ), 'unknown equation variables are reported instead of silently displayed' );
echo "$checks template unit checks passed.\n";
