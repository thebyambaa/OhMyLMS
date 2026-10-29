<?php
/** Native block browser fixture using the real block, templates and WordPress runtime. */
if ( PHP_SAPI !== 'cli-server' ) { exit; }
define( 'OHMYLMS_DIR', dirname( __DIR__, 2 ) );
define( 'OHMYLMS_FILE', OHMYLMS_DIR . '/ohmylms.php' );
define( 'OHMYLMS_VERSION', 'test' );
define( 'OHMYLMS_QUIZ_CPT', 'ohmylms-quiz' );
define( 'ABSPATH', dirname( OHMYLMS_DIR, 3 ) . '/' );
$route = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$files = array(
	'/runtime.js' => ABSPATH . 'wp-includes/js/dist/script-modules/interactivity/index.js',
	'/router.js' => ABSPATH . 'wp-includes/js/dist/script-modules/interactivity-router/index.js',
	'/style.css' => OHMYLMS_DIR . '/assets/css/style.css',
);
if ( isset( $files[ $route ] ) ) {
	header( 'Content-Type: ' . ( substr( $route, -4 ) === '.css' ? 'text/css' : 'application/javascript' ) );
	readfile( $files[ $route ] ); exit;
}
if ( strpos( $route, '/assets/' ) === 0 ) {
	$file = realpath( OHMYLMS_DIR . $route );
	if ( $file && strpos( $file, realpath( OHMYLMS_DIR . '/assets' ) . DIRECTORY_SEPARATOR ) === 0 && in_array( pathinfo( $file, PATHINFO_EXTENSION ), array( 'js', 'mjs', 'css' ), true ) ) {
		header( 'Content-Type: ' . ( substr( $file, -4 ) === '.css' ? 'text/css' : 'application/javascript' ) );
		readfile( $file ); exit;
	}
}
if ( '/fixture' !== $route && ! preg_match( '#^/api/quizzes/(10|20)/attempt$#', $route, $matches ) ) { http_response_code( 404 ); exit; }
session_start();
require __DIR__ . '/quiz-inline-runtime.php';
spl_autoload_register( static function ( $class ) {
	if ( strpos( $class, 'WP_HTML_' ) === 0 ) {
		$file = ABSPATH . 'wp-includes/html-api/class-' . str_replace( '_', '-', strtolower( $class ) ) . '.php';
	} elseif ( strpos( $class, 'OhMyLMS\\' ) === 0 ) {
		$file = OHMYLMS_DIR . '/includes/' . str_replace( '\\', '/', substr( $class, 8 ) ) . '.php';
	}
	if ( isset( $file ) && is_file( $file ) ) { require $file; }
} );
require ABSPATH . 'wp-includes/class-wp-token-map.php';
require ABSPATH . 'wp-includes/html-api/html5-named-character-references.php';
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return esc_attr( $value ); }
function esc_url( $value ) { return esc_attr( $value ); }
function esc_html__( $value, $domain ) { return esc_html( $value ); }
function esc_html_e( $value, $domain ) { echo esc_html( $value ); }
function esc_attr_e( $value, $domain ) { echo esc_attr( $value ); }
function __( $value, $domain ) { return $value; }
function _n( $single, $plural, $count, $domain ) { return $count === 1 ? $single : $plural; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_kses_uri_attributes() { return array(); }
function wp_has_noncharacters( $value ) { return false; }
function wp_interactivity_data_wp_context( $value ) { return 'data-wp-context="' . esc_attr( json_encode( $value ) ) . '"'; }
function wp_enqueue_script_module( $id ) {}
function wp_enqueue_style( ...$args ) {}
function did_action( $action ) { return false; }
function add_filter( $name, $callback, ...$args ) { $GLOBALS['filters'][ $name ][] = $callback; }
function apply_filters( $name, $value, ...$args ) { foreach ( $GLOBALS['filters'][ $name ] ?? array() as $callback ) { $value = $callback( $value, ...$args ); } return $value; }
function add_action( ...$args ) {}
function do_action( ...$args ) {}
function get_post_meta( ...$args ) { return ''; }
function get_option( $key, $default = '' ) { return $default; }
function sanitize_key( $value ) { return $value; }
function sanitize_text_field( $value ) { return $value; }
function get_current_user_id() { return 1; }
function get_the_ID() { return $GLOBALS['post']->ID ?? 100; }
function get_post( $id ) { return (object) array( 'ID' => $id ); }
function setup_postdata( $post ) {}
function get_post_type( $id ) { return OHMYLMS_QUIZ_CPT; }
function get_post_status( $id ) { return 'publish'; }
function absint( $value ) { return abs( (int) $value ); }
function home_url( $path ) { return $path; }
function add_query_arg( $name, $value, $url ) { return $url . '?' . urlencode( $name ) . '=' . urlencode( $value ); }
function is_user_logged_in() { return true; }
function is_wp_error( $value ) { return false; }
function rest_ensure_response( $value ) { return $value; }
function rest_url( $path ) { return preg_replace( '#^ohmylms/v1/quiz-player/#', '/api/', $path ); }
function wp_create_nonce( $action ) { return 'fixture'; }
function wp_nonce_field( $action, $name ) { echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="fixture">'; }
function get_block_wrapper_attributes( $attrs ) { return 'class="wp-block-ohmylms-quiz-player ' . esc_attr( $attrs['class'] ) . '"'; }
function plugins_url( $path, $file ) { return '/' . $path; }
function admin_url( $path ) { return '/expiry'; }
function get_permalink( $id = 0 ) { return '/fixture'; }
function get_the_permalink() { return '/fixture'; }
function locate_template( $path ) { return ''; }
function current_time( $type ) { return $type === 'timestamp' ? time() : date( 'Y-m-d H:i:s' ); }
function ohmylms_get_course_by_content_id( $id ) { return 2; }
function ohmylms_get_next_content_permalink( $id ) { return ''; }
function ohmylms_render_slot( ...$args ) {}
function ohmylms_get_template( $name, $args = array() ) { extract( $args ); include OHMYLMS_DIR . '/templates/' . $name; }
function ohmylms_get_quiz( $id ) {
	return new class( $id ) {
		private $id;
		function __construct( $id ) { $this->id = $id; }
		function get_name() { return 'Quiz ' . $this->id; }
		function get_quiz_attempt( $user ) { return ! empty( $_SESSION['quizzes'][ $this->id ]['active'] ) ? array( 'id' => 11 ) : null; }
		function get_all_quiz_attempts( ...$args ) { return ! empty( $_SESSION['quizzes'][ $this->id ]['completed'] ) ? array( array( 'quiz_attempt_id' => 11, 'total_achieved_marks' => $_SESSION['quizzes'][ $this->id ]['score'] ) ) : array(); }
		function count_total_attempt( ...$args ) { return count( $this->get_all_quiz_attempts() ); }
		function get_take_attempts() { return 2; }
		function get_timer() { return 0; }
		function get_settings() { return array( 'layout' => 'one_question_per_page' ); }
		function get_questions() { return array( array( 'id' => 101, 'name' => 'What is 2 + 2?', 'frozen' => true, 'settings' => array( 'type' => 'single-choice', 'required' => true, 'score' => array( 'value' => 1 ) ), 'questions' => array( array( 'id' => 1010, 'question_id' => 101, 'answer' => '4' ), array( 'id' => 1011, 'question_id' => 101, 'answer' => '5' ) ) ) ); }
	};
}
\OhMyLMS\Extensions\QuestionTypes::register_defaults();
\OhMyLMS\Blocks\QuizPlayerBlock::init();
if ( '/fixture' !== $route ) {
	file_put_contents( 'php://stderr', 'Native fixture request: ' . $route . ' ' . ( $_POST['operation'] ?? '' ) . "\n" );
	header( 'Content-Type: application/json' );
	if ( ( $_SERVER['HTTP_X_WP_NONCE'] ?? '' ) !== 'fixture' ) { http_response_code( 403 ); echo '{"message":"Invalid nonce"}'; exit; }
	echo json_encode( \OhMyLMS\Blocks\QuizPlayerBlock::mutate( array_merge( $_POST, array( 'id' => (int) $matches[1] ) ) ) ); exit;
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>Native quiz block test</title>
<link rel="stylesheet" href="/assets/css/oml-ui.css"><link rel="stylesheet" href="/style.css"><link rel="stylesheet" href="/assets/css/quiz-player-templates.css"><link rel="stylesheet" href="/assets/css/quiz-player-inline.css"><link rel="stylesheet" href="/assets/interactivity/frontend.css">
<script type="importmap">{"imports":{"@wordpress/interactivity":"/runtime.js","@wordpress/interactivity-router":"/router.js","ohmylms/interactivity":"/assets/interactivity/sdk.js","ohmylms/questions":"/assets/interactivity/questions.js"}}</script>
<script id="wp-script-module-data-@wordpress/interactivity" type="application/json">{"config":{}}</script></head>
<body><main style="max-width:900px;margin:32px auto"><h1>Lesson with native quizzes</h1><p>Browser test fixture — no learner records are created.</p>
<?php echo \OhMyLMS\Blocks\QuizPlayerBlock::render( array( 'quizId' => 10 ) ); echo \OhMyLMS\Blocks\QuizPlayerBlock::render( array( 'quizId' => 20 ) ); ?>
</main><script type="module" src="/assets/interactivity/quiz.js"></script><script type="module" src="/assets/interactivity/questions.js"></script></body></html>
