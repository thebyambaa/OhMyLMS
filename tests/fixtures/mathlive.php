<?php
/** Local integration fixture: real expression template, local MathLive and server grader. */
if ( PHP_SAPI !== 'cli-server' ) { exit; }
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
define( 'OHMYLMS_FILE', ABSPATH . 'ohmylms.php' );
define( 'OHMYLMS_DIR', ABSPATH );
define( 'OHMYLMS_VERSION', 'test' );
define( 'OHMYLMS_MATHLIVE_ENABLED', true );
$route = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
if ( preg_match( '~^/(assets/(?:dist/admin/|dist/mathlive/fonts/|css/|src/features/math/|interactivity/)[a-zA-Z0-9_./-]+)$~', $route, $match ) && false === strpos( $route, '..' ) ) {
	$file = ABSPATH . $match[1];
	if ( ! is_file( $file ) ) { http_response_code( 404 ); exit; }
	$ext = pathinfo( $file, PATHINFO_EXTENSION );
	header( 'Content-Type: ' . ( 'css' === $ext ? 'text/css' : ( 'woff2' === $ext ? 'font/woff2' : 'application/javascript' ) ) );
	readfile( $file ); exit;
}
require ABSPATH . 'vendor/autoload.php';
function __( $text ) { return $text; }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $text ) { return esc_attr( $text ); }
function esc_html_e( $text ) { echo esc_html( $text ); }
function esc_attr_e( $text ) { echo esc_attr( $text ); }
function apply_filters( $name, $value ) { return $value; }
function wp_script_is() { return true; }
function wp_style_is() { return true; }
function wp_enqueue_script() {}
function wp_enqueue_style() {}
function plugins_url( $path ) { return '/' . $path; }
$keys = array(
	1 => array( 'answer' => '\\frac{\\sqrt{x^{2}+1}}{\\frac{1}{2}}', 'form' => 'any' ),
	2 => array( 'answer' => '2(x+3)', 'form' => 'expanded' ),
	3 => array( 'answer' => '(x+1)(x-1)', 'form' => 'factored' ),
	4 => array( 'answer' => '1/2', 'form' => 'simplified' ),
	5 => array( 'answer' => '2x+5=11', 'form' => 'any' ),
);
if ( '/grade' === $route && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	header( 'Content-Type: application/json' );
	$result = array();
	foreach ( $keys as $id => $settings ) {
		$result[ $id ] = \OhMyLMS\Assessment\Interactive::grade( 'expression', (array) ( $_POST['attempt'][1]['quiz_question'][ $id ] ?? array() ), $settings );
	}
	echo json_encode( $result ); exit;
}
if ( '/fixture' !== $route ) { http_response_code( 404 ); exit; }
?><!doctype html><html lang="en"><head><meta charset="utf-8"><title>MathLive integration fixture</title><link rel="stylesheet" href="/assets/css/mathlive.css"></head><body>
<h1>MathLive integration fixture</h1><p id="prose">Costs $5. [[ohmylms-math:latex:inline]]\frac{1}{2}[[/ohmylms-math]]</p>
<form method="post" action="/grade">
<?php foreach ( $keys as $id => $settings ) {
	$attempt = array( 'id' => 1 );
	$question = array( 'id' => $id, 'settings' => $settings );
	echo '<section class="ohmylms-quiz-box" data-question-id="' . esc_attr( $id ) . '">';
	require ABSPATH . 'templates/single-lesson/quiz-loop/expression.php';
	echo '</section>';
} ?>
<button type="submit">Submit</button></form><div id="dynamic"></div>
<script>window.ohmylmsMath={enabled:true,runtime:'/assets/dist/admin/math-runtime.js',fonts:'/assets/dist/mathlive/fonts',style:'/assets/css/mathlive.css'};</script>
<?php if ( isset( $_GET['loader'] ) ) { ?><script src="/assets/interactivity/math-loader.js"></script><?php } ?>
<?php if ( ! isset( $_GET['fallback'] ) ) { ?><script src="/assets/dist/admin/math-runtime.js"></script><?php } ?>
</body></html>
