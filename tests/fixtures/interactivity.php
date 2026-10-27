<?php
/** Local browser fixture: real templates + WordPress runtime, mocked data, no database or wp-load. */
if (PHP_SAPI !== 'cli-server') exit;
define('OHMYLMS_DIR', dirname(__DIR__, 2));
define('OHMYLMS_FILE', OHMYLMS_DIR . '/ohmylms.php');
define('OHMYLMS_VERSION', 'test');
define('ABSPATH', dirname(OHMYLMS_DIR, 3) . '/');
define('OHMYLMS_SS_DIR', dirname(OHMYLMS_DIR) . '/ohmylms-smartscore/');
$route = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$files = [
    '/runtime.js' => ABSPATH . 'wp-includes/js/dist/script-modules/interactivity/index.js',
    '/sdk.js' => OHMYLMS_DIR . '/assets/interactivity/sdk.js',
    '/quiz.js' => OHMYLMS_DIR . '/assets/interactivity/quiz.js',
    '/ui.js' => OHMYLMS_DIR . '/assets/interactivity/ui.js',
    '/gamification.js' => OHMYLMS_DIR . '/assets/interactivity/gamification.js',
    '/smartscore.js' => OHMYLMS_SS_DIR . 'assets/interactivity.js',
    '/questions.js' => OHMYLMS_DIR . '/assets/interactivity/questions.js',
    '/tabs.js' => OHMYLMS_DIR . '/assets/interactivity/tabs.js',
    '/curriculum.js' => OHMYLMS_DIR . '/assets/interactivity/curriculum.js',
];
if ($route === '/frontend.css') { header('Content-Type: text/css'); readfile(OHMYLMS_DIR . '/assets/interactivity/frontend.css'); exit; }
if (isset($files[$route])) { header('Content-Type: application/javascript'); readfile($files[$route]); exit; }
if ($route !== '/fixture') { http_response_code(404); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') { echo 'Fixture submission received'; exit; }

spl_autoload_register(static function ($class) {
    if (strpos($class, 'WP_HTML_') === 0) {
        $path = ABSPATH . 'wp-includes/html-api/class-' . str_replace('_', '-', strtolower($class)) . '.php';
        if (is_file($path)) require $path;
    }
});
require ABSPATH . 'wp-includes/class-wp-token-map.php';
require ABSPATH . 'wp-includes/html-api/html5-named-character-references.php';
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_html($value) { return esc_attr($value); }
function esc_url($value) { return esc_attr($value); }
function esc_attr_e($value, $domain = '') { echo esc_attr($value); }
function esc_html_e($value, $domain = '') { echo esc_html($value); }
function __($value, $domain = '') { return $value; }
function wp_json_encode($value) { return json_encode($value); }
function wp_kses_post($value) { return $value; }
function sanitize_text_field($value) { return strip_tags((string) $value); }
function wp_kses_uri_attributes() { return []; }
function wp_has_noncharacters($value) { return false; }
function wp_interactivity_data_wp_context($value) { return 'data-wp-context="' . esc_attr(json_encode($value)) . '"'; }
function wp_enqueue_script_module($id) {}
function wp_register_script_module(...$args) {}
function wp_enqueue_style(...$args) {}
function did_action($action) { return false; }
function apply_filters($name, $value, ...$args) {
    if ($name === 'ohmylms_course_tabs') return [
        'description' => ['title' => 'Description', 'callback' => static function () { echo 'Description panel'; }],
        'content' => ['title' => 'Course content', 'callback' => static function () { echo 'Content panel'; }],
        'addon' => ['title' => 'Add-on tab', 'callback' => static function () { echo 'Extension panel'; }],
    ];
    return $value;
}
function do_action(...$args) {}
function add_action(...$args) {}
function add_filter(...$args) {}
function _doing_it_wrong(...$args) { throw new RuntimeException(json_encode($args)); }
function wp_unique_id($prefix) { static $id = 0; return $prefix . ++$id; }
function admin_url($value) { return '/expiry'; }
function wp_create_nonce($action) { return $action; }
function wp_nonce_field($action, $name) { printf('<input name="%s" value="fixture-nonce" type="hidden">', esc_attr($name)); }
function get_the_ID() { return 10; }
function get_current_user_id() { return 1; }
function get_post_meta(...$args) { return ''; }
function get_option($name, $default = '') { return $default; }
function _nx($single, $plural, $number, ...$args) { return $number === 1 ? $single : $plural; }
function ohmylms_format_duration($duration) { return ''; }
function get_the_permalink() { return '/fixture'; }
function get_the_title($id = 0) { return 'Fixture quiz'; }
function current_time($type) { return $type === 'timestamp' ? time() : date('Y-m-d H:i:s'); }
function get_permalink($id) { return '/fixture'; }
function plugins_url($path, $file) { return '/' . $path; }
function wp_get_attachment_url($id) { return false; }
function ohmylms_get_course_by_content_id($id) { return 2; }
function ohmylms_get_next_content_permalink($id) { return '/next-lesson'; }
function ohmylms_render_slot($name, $context = []) { echo '<div data-test-slot="' . esc_attr($name) . '"></div>'; }
function ohmylms_enqueue_interactivity_module($id) {}
function ohmylms_get_template($name, $args) { extract($args); include OHMYLMS_DIR . '/templates/' . $name; }
function ohmylms_get_question($id) { return new class { function get_image_url() { return ''; } function get_video_id() { return 0; } }; }

require OHMYLMS_DIR . '/includes/Extensions/Registry.php';
require OHMYLMS_DIR . '/includes/Extensions/QuestionTypes.php';
require OHMYLMS_DIR . '/includes/Extensions/Layouts.php';
require OHMYLMS_DIR . '/includes/Extensions/Interactivity.php';
\OhMyLMS\Extensions\QuestionTypes::register_defaults();
\OhMyLMS\Extensions\Registry::register('question', 'custom-type', [
    'label' => 'Custom', 'validate' => '__return_true', 'grade' => '__return_true',
    'render' => static function ($question, $attempt) { $args = compact('question', 'attempt'); include dirname(OHMYLMS_DIR) . '/ohmylms-custom-question/templates/single-lesson/quiz-loop/custom-type.php'; },
]);
function __return_true() { return true; }
function ohmylms_get_quiz($id) {
    return new class {
        function get_name() { return 'Fixture quiz'; }
        function get_timer() { return (float) ($_GET['timer'] ?? 0); }
        function get_quiz_attempt($student) { return ['id' => 11, 'start_date' => date('Y-m-d H:i:s')]; }
        function get_settings() { return ['layout' => $_GET['layout'] ?? 'one_question_per_page', 'question_in_one_page' => 2, 'short_text_limit' => 5]; }
        function get_questions() {
            $types = explode(',', $_GET['types'] ?? 'single-choice,short-text,custom-type');
            $questions = [];
            foreach ($types as $index => $type) {
                $id = 101 + $index;
                $questions[] = ['id' => $id, 'name' => 'Question ' . $id, 'settings' => ['type' => $type, 'required' => true],
                    'questions' => [['id' => $id * 10, 'question_id' => $id, 'answer' => 'A', 'matching_data' => ['label' => 'First']], ['id' => $id * 10 + 1, 'question_id' => $id, 'answer' => 'B', 'matching_data' => ['label' => 'Second']]]];
            }
            return $questions;
        }
    };
}
?>
<!doctype html><html><head><meta charset="utf-8">
<link rel="stylesheet" href="/frontend.css">
<style>[hidden]{display:none!important}.ohmylms-one-question-per-page .ohmylms-quiz-box:not(.active),.ohmylms-question-group:not(.active){display:none}.required-question,.ohmylms-quiz-alert,.ohmylms-quiz-timeup-text{display:none}</style>
<script type="importmap">{"imports":{"@wordpress/interactivity":"/runtime.js","ohmylms/interactivity":"/sdk.js","ohmylms/questions":"/questions.js"}}</script>
<script id="wp-script-module-data-@wordpress/interactivity" type="application/json"><?php echo json_encode(['config' => ['ohmylms/smartscore' => ['root' => '/api', 'nonce' => 'fixture', 'i18n' => [
    'error' => 'Request failed', 'correctIs' => 'Correct answer', 'explanation' => 'Explanation', 'keepGoing' => 'Keep practising', 'gotIt' => 'Got it', 'mastered' => 'Mastered', 'completed' => 'Complete', 'reached' => 'Reached %d', 'incorrect' => 'Incorrect', 'praise' => ['Correct'],
]]]]); ?></script>
</head><body>
<?php
if (($_GET['view'] ?? '') === 'practice') {
    $quiz_id = 10;
    $state = ['score' => 0, 'zone' => 'learning', 'zoneLabel' => '', 'code' => 'A'];
    $data = ['quiz' => 10, 'title' => 'Practice fixture', 'state' => $state, 'next' => '/next-lesson'];
    include OHMYLMS_SS_DIR . 'templates/practice.php';
    if (!empty($_GET['multiple'])) { $quiz_id = 20; $data['quiz'] = 20; include OHMYLMS_SS_DIR . 'templates/practice.php'; }
} else { include OHMYLMS_DIR . '/templates/single-lesson/quiz-form.php'; }
echo \OhMyLMS\Extensions\Interactivity::disclosures('<div class="ohmylms-accordion-item"><div class="ohmylms-accordion-head">Disclosure</div><div class="ohmylms-accordion-body">Details</div></div>', 'fixture.php');
include OHMYLMS_DIR . '/templates/global/ohmylms-celebration.php';
?>
<script type="module" src="/quiz.js"></script><script type="module" src="/ui.js"></script><script type="module" src="/gamification.js"></script><script type="module" src="/smartscore.js"></script><script type="module" src="/tabs.js"></script><script type="module" src="/curriculum.js"></script>
</body></html>
