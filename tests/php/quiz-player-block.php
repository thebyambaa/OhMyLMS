<?php
/** Standalone regression checks for quiz embed metadata and navigation. */

define( 'ABSPATH', __DIR__ );
define( 'OHMYLMS_QUIZ_CPT', 'ohmylms-quiz' );
define( 'OHMYLMS_DIR', dirname( __DIR__, 2 ) );
define( 'OHMYLMS_FILE', OHMYLMS_DIR . '/ohmylms.php' );
define( 'OHMYLMS_VERSION', 'test' );
require __DIR__ . '/fixtures/quiz-player-runtime.php';
class WP_Error {
	public $code;
	private $message;
	public function __construct( $code, $message, $data = null ) { $this->code = $code; $this->message = $message; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function is_user_logged_in() { return ! empty( $GLOBALS['logged_in'] ); }
function get_current_user_id() { return is_user_logged_in() ? 3 : 0; }
function __( $text, $domain ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( $text, ENT_QUOTES ); }
function plugins_url( $path, $file ) { return 'https://example.test/plugins/' . $path; }
function wp_enqueue_style( ...$args ) {}
function rest_url( $path ) { return 'https://example.test/wp-json/' . $path; }
function wp_interactivity_data_wp_context( $context ) { return 'data-wp-context="' . esc_attr( json_encode( $context ) ) . '"'; }
function wp_login_url( $return ) { return 'https://example.test/login?return=' . urlencode( $return ); }
function has_block( $name ) { return true; }
function nocache_headers() { $GLOBALS['uncached'] = true; }
function shortcode_atts( $defaults, $attributes ) { return array_merge( $defaults, array_intersect_key( (array) $attributes, $defaults ) ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function do_blocks( $markup ) { $GLOBALS['blocks'][] = $markup; return 'rendered:' . $markup; }
$fixtures = array(
	7 => array( 'type' => 'ohmylms-quiz', 'status' => 'publish', 'title' => 'Math <quiz>', 'editable' => false ),
	8 => array( 'type' => 'ohmylms-quiz', 'status' => 'private', 'title' => 'Private quiz', 'editable' => false ),
	9 => array( 'type' => 'ohmylms-quiz', 'status' => 'draft', 'title' => 'Author draft', 'editable' => true ),
	10 => array( 'type' => 'page', 'status' => 'publish', 'title' => 'Page', 'editable' => true ),
);
function absint( $value ) { return abs( (int) $value ); }
function home_url( $path ) { return 'https://example.test' . $path; }
function add_query_arg( $key, $value, $url = null ) {
	if ( is_array( $key ) ) { return $value . '?' . http_build_query( $key ); }
	return $url . '?' . http_build_query( array( $key => $value ) );
}
function wp_create_nonce( $action ) { return 'author-preview-nonce'; }
function wp_unslash( $value ) { return $value; }
function get_post_type( $id ) { return $GLOBALS['fixtures'][ $id ]['type'] ?? ''; }
function get_post_status( $id ) { return $GLOBALS['fixtures'][ $id ]['status'] ?? ''; }
function get_the_title( $post ) { return $GLOBALS['fixtures'][ is_object( $post ) ? $post->ID : $post ]['title']; }
function esc_html__( $text, $domain ) { return htmlspecialchars( $text, ENT_QUOTES ); }
function esc_url( $url ) { return htmlspecialchars( $url, ENT_QUOTES ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function get_block_wrapper_attributes() { return 'class="wp-block-ohmylms-quiz-player"'; }
function get_permalink( $id = 10 ) { return 'https://example.test/quiz/' . $id; }
function rest_ensure_response( $items ) { return $items; }
function current_user_can( $capability, $id = 0 ) {
	return 'edit_posts' === $capability ? ! empty( $GLOBALS['can_author'] ) : ! empty( $GLOBALS['fixtures'][ $id ]['editable'] );
}
function get_posts( $args ) {
	return array_map( static function ( $id ) { return (object) array( 'ID' => $id, 'post_status' => get_post_status( $id ) ); }, array( 7, 8, 9 ) );
}
function register_rest_route( $namespace, $route, $settings ) { $GLOBALS['route_settings'] = $settings; $GLOBALS['routes'][ $route ] = $settings; }
require OHMYLMS_DIR . '/includes/QuestionBank/AccessPolicy.php';
require OHMYLMS_DIR . '/includes/Assessment/PreviewPlayer.php';
require OHMYLMS_DIR . '/includes/Blocks/QuizPlayerBlock.php';
use OhMyLMS\Blocks\QuizPlayerBlock;
$checks = 0;
function check_embed( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	++$GLOBALS['checks'];
}
$html = QuizPlayerBlock::render( array( 'quizId' => 7, 'height' => 9999 ) );
check_embed( ! str_contains( $html, '<iframe' ), 'Native players must never render an iframe.' );
check_embed( str_contains( $html, 'data-wp-router-region="ohmylms-quiz-player-1"' ), 'Native players must define a router region.' );
check_embed( str_contains( $html, 'Log in to take this quiz.' ), 'Guests must get a login prompt, not question data.' );
check_embed( str_contains( $html, 'inlineEndpoint' ), 'Native player must carry authenticated transport configuration.' );
check_embed( str_contains( QuizPlayerBlock::render( array( 'quizId' => 7 ) ), 'ohmylms-quiz-player-2' ), 'Repeated quizzes need unique regions.' );
$GLOBALS['logged_in'] = true;
check_embed( str_contains( QuizPlayerBlock::render( array( 'quizId' => 7 ) ), 'Enrollment required.' ), 'Access failures must render inside the block.' );
$GLOBALS['logged_in'] = false;
foreach ( array( 0, 8, 9, 10 ) as $id ) {
	check_embed( str_contains( QuizPlayerBlock::render( array( 'quizId' => $id ) ), 'This quiz is unavailable.' ), 'Missing, private, draft or non-quiz posts must not render publicly.' );
}
$_GET['ohmylms_quiz_embed'] = 7;
check_embed( QuizPlayerBlock::redirect( get_permalink( 7 ) ) === QuizPlayerBlock::url( 7 ), 'Quiz submission must stay inside the frame.' );
check_embed( QuizPlayerBlock::redirect( get_permalink( 9 ) ) === get_permalink( 9 ), 'Other quiz redirects must stay unchanged.' );
check_embed( QuizPlayerBlock::redirect( 'https://elsewhere.test' ) === 'https://elsewhere.test', 'Unrelated redirects must stay unchanged.' );
$context = QuizPlayerBlock::player_context( array( 'quizId' => 7 ) );
check_embed( $context['returnUrl'] === QuizPlayerBlock::url( 7 ), 'Timeout submissions must keep embedded navigation.' );
check_embed( ! isset( QuizPlayerBlock::player_context( array( 'quizId' => 9 ) )['returnUrl'] ), 'Other player contexts must stay unchanged.' );
$_GET['ohmylms_quiz_embed'] = array( 7 );
check_embed( QuizPlayerBlock::redirect( get_permalink( 7 ) ) === get_permalink( 7 ), 'Array navigation input must be rejected.' );
$items = QuizPlayerBlock::choices( array() );
check_embed( array_column( $items, 'id' ) === array( 7, 9 ), 'Private quizzes must stay out of unauthorized pickers.' );
check_embed( array_keys( $items[0] ) === array( 'id', 'title', 'status', 'previewUrl' ), 'Picker must not expose questions or answer keys.' );
check_embed( '' === $items[0]['previewUrl'] && str_contains( $items[1]['previewUrl'], '_wpnonce=' ), 'Only quiz authors may preview.' );
QuizPlayerBlock::register_routes();
$GLOBALS['can_author'] = false;
check_embed( ! ( $GLOBALS['route_settings']['permission_callback'] )(), 'Guests must not access quiz picker metadata.' );
$GLOBALS['can_author'] = true;
check_embed( ( $GLOBALS['route_settings']['permission_callback'] )(), 'Content authors must access the quiz picker.' );
$route = $GLOBALS['routes']['/quiz-player/quizzes/(?P<id>\d+)/attempt'];
check_embed( ! ( $route['permission_callback'] )(), 'Guests must not mutate attempts.' );
$GLOBALS['logged_in'] = true;
check_embed( ( $route['permission_callback'] )(), 'Authenticated users may reach the shared access check.' );
check_embed( array( 'saved' => true ) === QuizPlayerBlock::mutate( array( 'id' => 7, 'operation' => 'start' ) ), 'Start must return no score or redirects.' );
check_embed( array( 7, 3, 'start' ) === $GLOBALS['submitted'], 'Start must delegate the authenticated user to the existing engine.' );
foreach ( array( 'submit', 'exit', 'timeout' ) as $reason ) {
	$answers = array( 17 => array( 'answer' ) );
	QuizPlayerBlock::mutate( array( 'id' => 7, 'operation' => $reason, 'quiz_attempt_id' => 11, 'attempt' => array( 11 => array( 'quiz_question' => $answers ) ) ) );
	check_embed( array( 7, 11, 3, $answers, $reason ) === $GLOBALS['submitted'], 'Submissions must preserve answers and engine reason.' );
}
check_embed( is_wp_error( QuizPlayerBlock::mutate( array( 'id' => 9, 'operation' => 'start' ) ) ), 'Draft quizzes cannot start from the frontend.' );
check_embed( is_wp_error( QuizPlayerBlock::mutate( array( 'id' => 7, 'operation' => 'delete' ) ) ), 'Unknown operations must be rejected.' );
check_embed( is_wp_error( QuizPlayerBlock::mutate( array( 'id' => 7, 'operation' => 'submit', 'quiz_attempt_id' => 11, 'attempt' => 'invalid' ) ) ), 'Malformed answers must be rejected.' );
$GLOBALS['engine_error'] = new WP_Error( 'quiz_locked', 'Locked.' );
check_embed( $GLOBALS['engine_error'] === QuizPlayerBlock::mutate( array( 'id' => 7, 'operation' => 'start' ) ), 'Engine access failures must propagate unchanged.' );
QuizPlayerBlock::prevent_cache();
check_embed( DONOTCACHEPAGE && $GLOBALS['uncached'], 'Personalized quiz pages must bypass page caching.' );
// [ohmylms_quiz] renders the registered block itself, so it is the same player everywhere.
check_embed( '' === QuizPlayerBlock::shortcode( array() ), 'A player shortcode without a quiz must render nothing.' );
check_embed( '' === QuizPlayerBlock::shortcode( array( 'quiz' => 'abc' ) ), 'A non-numeric quiz must render nothing.' );
$GLOBALS['blocks'] = array();
$shortcode = QuizPlayerBlock::shortcode( array( 'quiz' => '7' ) );
check_embed( array( '<!-- wp:ohmylms/quiz-player {"quizId":7} /-->' ) === $GLOBALS['blocks'], 'The shortcode must render the quiz player block for that quiz.' );
check_embed( str_starts_with( $shortcode, 'rendered:' ), 'The shortcode must return the block output unchanged.' );
echo $checks . " quiz player block checks passed.\n";
