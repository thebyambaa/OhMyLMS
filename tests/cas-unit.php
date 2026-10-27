<?php
/** Pure unit checks for the expression engine (no WordPress). */
define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__ ) . '/vendor/autoload.php';
use OhMyLMS\Assessment\Cas\Expression as E;

$checks = 0;
function check( $condition, $message ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message ); }
	++$checks;
}
function same( $student, $key, $form = 'any' ) {
	return E::check( $student, $key, $form )['correct'];
}
function reason( $student, $key, $form = 'any' ) {
	return E::check( $student, $key, $form )['reason'];
}

// Parsing and evaluation.
$v = static function ( $text, $vars = array() ) {
	$tree = E::parse( $text );
	return $tree === null ? null : E::evaluate( $tree, $vars );
};
check( $v( '2+3*4' ) === 14.0, 'precedence' );
check( $v( '(2+3)*4' ) === 20.0, 'parentheses' );
check( $v( '2^3^2' ) === 512.0, 'power is right associative' );
check( $v( '-2^2' ) === -4.0, 'unary minus binds below power' );
check( $v( '2^-1' ) === 0.5, 'negative exponent' );
check( $v( '3/4+1/8' ) === 0.875, 'fractions' );
check( $v( '2x', array( 'x' => 5.0 ) ) === 10.0, 'implicit multiplication' );
check( $v( '2(x+3)', array( 'x' => 1.0 ) ) === 8.0, 'number times bracket' );
check( $v( '(x+1)(x-1)', array( 'x' => 3.0 ) ) === 8.0, 'bracket times bracket' );
check( abs( $v( 'sin(pi/2)' ) - 1.0 ) < 1e-12 && abs( $v( 'sqrt(16)' ) - 4.0 ) < 1e-12, 'functions and pi' );
check( abs( $v( 'sin x', array( 'x' => 0.0 ) ) ) < 1e-12, 'function without brackets' );
check( $v( '1/0' ) === null && $v( 'sqrt(-1)' ) === null && $v( 'ln(0)' ) === null, 'undefined values are null, not errors' );
check( $v( '(-8)^(1/3)' ) === null, 'fractional power of a negative is undefined' );
check( $v( '9^9^9' ) === null, 'overflow is undefined' );
// Unicode and LaTeX as a math keyboard writes them.
check( $v( '6 ÷ 3 × 2' ) === 4.0 && $v( "3 \u{2212} 1" ) === 2.0 && $v( '3·4' ) === 12.0, 'Unicode operators' );
check( $v( '√16' ) === 4.0 && $v( '√(x+1)', array( 'x' => 8.0 ) ) === 3.0, 'root sign' );
check( $v( 'x²', array( 'x' => 3.0 ) ) === 9.0, 'superscript two' );
check( $v( '\frac{3}{4}+\frac{1}{8}' ) === 0.875, 'LaTeX fractions' );
check( $v( '\frac{x+1}{2}', array( 'x' => 5.0 ) ) === 3.0, 'LaTeX fraction with an expression' );
check( $v( '\frac{\frac{1}{2}}{2}' ) === 0.25, 'nested LaTeX fractions' );
check( abs( $v( '\sqrt{9}+\sqrt[3]{8}' ) - 5.0 ) < 1e-9, 'LaTeX roots' );
check( $v( '2\cdot x^{2}', array( 'x' => 3.0 ) ) === 18.0, 'LaTeX cdot and braced exponent' );
check( $v( '\left(x+1\right)\left(x-1\right)', array( 'x' => 3.0 ) ) === 8.0, 'LaTeX left/right brackets' );
// Rejected input: never a fatal, never evaluated as PHP.
check( $v( 'x^{12}', array( 'x' => 2.0 ) ) === 4096.0, 'MathLive braced power without commands' );
check( $v( '\\frac{\\sqrt{x^{2}+1}}{\\frac{1}{2}}', array( 'x' => 0.0 ) ) === 2.0, 'MathLive nested root, power and fraction' );
check( $v( '\\left\\lvert -3\\right\\rvert' ) === 3.0, 'MathLive absolute value delimiters' );
foreach ( array( '\\unknown{x}', '\\leftarrow', '\\sum_{n=1}^{2}n', '\\int x', '\\placeholder[omlvar0]{a}', str_repeat( '\\quad ', 1000 ) . '1' ) as $unsupported ) {
	check( E::parse( $unsupported ) === null, 'Unsupported MathLive notation rejected' );
}
foreach ( array( '', '   ', '2+', '(2+3', '2+3)', '*3', '2**3', 'system("ls")', '$x', 'x;y', '1 2 +', '<script>', 'x = = 2', str_repeat( '1+', 200 ) . '1', str_repeat( '(', 60 ) . '1' . str_repeat( ')', 60 ), str_repeat( 'x', 300 ) ) as $bad ) {
	check( E::parse( $bad ) === null, 'rejects ' . substr( $bad, 0, 30 ) );
}

// Equivalence: the cases the spec names.
check( same( '2x+6', '2(x+3)' ), '2x+6 = 2(x+3)' );
check( same( '2(x+3)', '2x+6' ), 'and the other way round' );
check( same( 'x^2-1', '(x+1)(x-1)' ), 'difference of squares' );
check( same( '(x+1)^2', 'x^2+2x+1' ), 'perfect square' );
check( same( '1/2*x', 'x/2' ) && same( '0.5x', 'x/2' ), 'fraction and decimal coefficients' );
check( same( 'x*x', 'x^2' ) && same( 'x(x)', 'x²' ), 'repeated factor' );
check( same( '3x+4y', '4y+3x' ), 'order does not matter' );
check( same( '\frac{x}{2}+\frac{x}{2}', 'x' ), 'LaTeX input is graded like ASCII' );
check( same( 'sin(x)^2+cos(x)^2', '1' ), 'trig identity' );
check( same( '(x^2-1)/(x-1)', 'x+1' ), 'cancelled factor' );
check( same( '7/8', '0.875' ) && same( '14/16', '7/8' ), 'numbers' );
check( ! same( '2x+5', '2(x+3)' ) && reason( '2x+5', '2(x+3)' ) === 'different', 'different expressions' );
check( ! same( 'x^2', '2x' ), 'x^2 is not 2x' );
check( ! same( 'x+0.001', 'x' ), 'a small offset is still different' );
check( ! same( 'x', 'y' ), 'different variables' );
check( ! same( '2x', '2' ) && ! same( 'x', 'x+y' ), 'extra or missing variables' );
check( reason( '2x+', '2x' ) === 'parse' && reason( '2x', '2x+' ) === 'parse', 'unparseable is reported, not different' );
check( ! same( 'sqrt(x)', '-sqrt(x)' ), 'sign matters under a root' );
check( ! same( 'x', '-x' ) && ! same( '1/x', 'x' ), 'sanity' );
// Equations: same solutions up to a constant factor.
check( same( '2x+5=11', '2x+5=11' ) && same( '4x+10=22', '2x+5=11' ), 'scaled equation' );
check( same( '11=2x+5', '2x+5=11' ), 'swapped sides' );
check( same( 'x=3', '2x+5=11' ), 'x=3 has the same left-minus-right up to a factor (2x+5-11 = 2(x-3))' );
check( ! same( 'x=4', '2x+5=11' ), 'a different root is a different equation' );
check( ! same( '2x+5=12', '2x+5=11' ), 'wrong constant' );
check( ! same( '2x+5', '2x+5=11' ) && ! same( '2x+5=11', '2x+5' ), 'equation versus expression' );

// Forms.
check( same( 'x^2+2x+1', '(x+1)^2', 'expanded' ), 'expanded accepted' );
check( reason( '(x+1)^2', '(x+1)^2', 'expanded' ) === 'form', 'unexpanded rejected' );
check( reason( 'x^2+x+x+1', '(x+1)^2', 'expanded' ) === 'form', 'uncombined like terms rejected' );
check( reason( '2(x+3)', '2x+6', 'expanded' ) === 'form', 'bracketed product is not expanded' );
check( same( '2x+6', '2(x+3)', 'expanded' ), '2x+6 expanded' );
check( same( 'x^2-5x+6', '(x-2)(x-3)', 'expanded' ), 'quadratic expanded' );
check( reason( 'x-(1+2)', 'x-3', 'expanded' ) === 'form', 'bracketed subtraction is not expanded' );
check( same( '(x-2)(x-3)', 'x^2-5x+6', 'factored' ), 'factored accepted' );
check( same( '2(x+3)', '2x+6', 'factored' ), 'common factor taken out' );
check( same( '(x+1)^2', 'x^2+2x+1', 'factored' ), 'square is factored' );
check( reason( 'x^2-5x+6', '(x-2)(x-3)', 'factored' ) === 'form', 'sum is not factored' );
check( reason( 'x(x-5)+6', 'x^2-5x+6', 'factored' ) === 'form', 'partly factored is not factored' );
check( same( '3/4', '0.75', 'simplified' ) && same( '3/4', '6/8', 'simplified' ), 'lowest terms' );
check( reason( '6/8', '3/4', 'simplified' ) === 'form', 'unsimplified fraction rejected' );
check( reason( '2+3', '5', 'simplified' ) === 'form' && same( '5', '2+3', 'simplified' ), 'unfolded arithmetic rejected' );
check( reason( 'x+x', '2x', 'simplified' ) === 'form' && same( '2x', 'x+x', 'simplified' ), 'like terms rejected' );
check( reason( '1x', 'x', 'simplified' ) === 'form' && reason( 'x+0', 'x', 'simplified' ) === 'form', 'identity operations rejected' );

// An external CAS can decide equivalence through the filter.
function apply_filters( $hook, $value, ...$args ) {
	global $cas_override;
	return $hook === 'ohmylms_cas_equivalent' && isset( $cas_override ) ? $cas_override : $value;
}
$cas_override = true;
check( same( 'x', 'y' ), 'filter can accept' );
$cas_override = false;
check( ! same( 'x', 'x' ), 'filter can reject' );
unset( $cas_override );
check( same( 'x', 'x' ), 'null filter keeps the numeric test' );

// Cost: a worst-case answer is cheap.
$start = microtime( true );
for ( $i = 0; $i < 200; $i++ ) {
	same( '(x+1)^2*(x-2)^3/(x+5)+sin(x)^2+cos(x)^2', '(x+1)^2*(x-2)^3/(x+5)+1' );
}
check( microtime( true ) - $start < 2.0, '200 gradings in under two seconds' );
echo "$checks CAS unit checks passed.\n";
