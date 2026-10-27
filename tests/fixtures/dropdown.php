<?php
/** Native dropdown template, practice collector, autosave and server grading fixture. */
if ( 'cli-server' !== PHP_SAPI ) { exit; }
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
define( 'OHMYLMS_FILE', ABSPATH . 'ohmylms.php' );
define( 'OHMYLMS_VERSION', 'test' );
define( 'OHMYLMS_DIR', ABSPATH );
$route = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
if ( preg_match( '~^/assets/(?:js|css)/[a-z-]+\.(?:js|css)$~', $route ) ) {
	header( 'Content-Type: ' . ( str_ends_with( $route, '.js' ) ? 'application/javascript' : 'text/css' ) );
	readfile( ABSPATH . ltrim( $route, '/' ) ); exit;
}
require ABSPATH . 'vendor/autoload.php';
function __( $text ) { return $text; }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $text ) { return esc_attr( $text ); }
function esc_html_e( $text ) { echo esc_html( $text ); }
function apply_filters( $name, $value ) { return $value; }
function wp_script_is() { return true; }
function wp_style_is() { return true; }
function wp_enqueue_script() {}
function wp_enqueue_style() {}
function plugins_url( $path ) { return '/' . $path; }
use OhMyLMS\Assessment\Interactive;
$settings = array( 'type' => 'dropdown-blanks', 'text' => 'Slope {1}, direction {2}.', 'dropdown_grading_version' => 2, 'score' => array( 'enabled' => true, 'value' => 5 ), 'slots' => array(
	array( 'id' => '1', 'choices' => array( 'positive', 'rising', 'negative' ), 'answers' => array( 'positive', 'rising' ), 'multiple' => true, 'grading' => 'equal', 'points' => 3 ),
	array( 'id' => '2', 'choices' => array( 'up', 'down' ), 'answer' => 'up', 'answers' => array( 'up' ), 'grading' => 'equal', 'points' => 2 ),
) );
if ( '/attempt' === $route ) {
	header( 'Content-Type: application/json' );
	echo json_encode( array( 'responses' => array( '1' => array( 'response' => array( '1' => array( 'positive' ), '2' => 'up' ) ) ) ) ); exit;
}
if ( '/attempt/responses' === $route ) {
	header( 'Content-Type: application/json' );
	echo json_encode( array( 'saved' => true ) ); exit;
}
if ( '/grade' === $route ) {
	header( 'Content-Type: application/json' );
	echo json_encode( Interactive::grade( 'dropdown-blanks', $_POST['attempt'][1]['quiz_question'][1] ?? array(), $settings ) ); exit;
}
if ( '/fixture' !== $route ) { http_response_code( 404 ); exit; }
?><!doctype html><html lang="en"><head><meta charset="utf-8"><title>Dropdown selection fixture</title><link rel="stylesheet" href="/assets/css/interactive.css"></head><body>
<div class="ohmylms-quiz" data-attempt-engine="versioned" data-autosave="/attempt/responses"><form method="post" action="/grade"><input type="hidden" class="ohmylms-quiz-form"><section class="ohmylms-quiz-box">
<?php
$attempt = array( 'id' => 1 );
$question = array( 'id' => 1, 'settings' => $settings );
require ABSPATH . 'templates/single-lesson/quiz-loop/dropdown-blanks.php';
?>
</section><button type="submit">Submit</button></form></div><div id="practice"></div>
<script>window.ohmylmsQuizAutosave={i18n:{saved:'Saved',saving:'Saving'}};window.fixtureSettings=<?php echo json_encode( Interactive::public_view( 'dropdown-blanks', $settings, 'test' ) ); ?>;</script>
<script src="/assets/js/interactive-controls.js"></script><script src="/assets/js/quiz-autosave.js"></script>
<script>window.OhMyLMSInteractive.render(document.querySelector('#practice'),'dropdown-blanks',window.fixtureSettings);</script>
</body></html>
