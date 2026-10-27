<?php
// Skills and the Question Bank are core features: no Add-ons switch exists for either, whatever was saved.
// Each saved-setting combination runs in a fresh process so require_once mirrors WordPress.
define('ABSPATH', __DIR__);
define('OHMYLMS_DIR', dirname(__DIR__, 2));
define('OHMYLMS_FILE', OHMYLMS_DIR . '/ohmylms.php');
define('OHMYLMS_SLUG', 'ohmylms');
define('OHMYLMS_ENABLED_MODULES', ['skills', 'question_bank', '../outside']);
require OHMYLMS_DIR . '/vendor/autoload.php';
$mode = $argv[1] ?? 'off';
// Legacy sites may still have saved switches; they must have no effect.
$GLOBALS['addon_options'] = [
    'skills' => ['is_enable' => in_array($mode, ['skills', 'both'], true) ? 1 : 0],
    'question_bank' => ['is_enable' => in_array($mode, ['bank', 'both'], true) ? '1' : 0],
];
$GLOBALS['hooks'] = [];
$GLOBALS['loaded_addons'] = [];
function get_option($key, $default = []) { return $GLOBALS['addon_options']; }
function add_action($hook, $callback, $priority = 10) { $GLOBALS['hooks'][$hook][] = $callback; }
function add_filter($hook, $callback) { add_action($hook, $callback); }
function apply_filters($hook, $value) { return $value; }
function __($text, $domain) { return $text; }
function plugins_url($path, $file) { return $path; }
function do_action($hook) { $GLOBALS['loaded_addons'][] = $hook; }
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
class WP_Error { public function __construct(public $code, public $message, public $data) {} }
class WP_REST_Controller {}
function is_wp_error($value) { return $value instanceof WP_Error; }
use OhMyLMS\Extensions\Addons;
use OhMyLMS\Extensions\Modules;
Addons::init();
$manifest = Addons::manifest(['existing' => ['is_enable' => 1], 'question_bank' => ['is_enable' => 1], 'skills' => ['is_enable' => 1]]);
check(isset($manifest['existing']), 'Existing add-ons retained');
check(count($manifest) === 1 && !isset($manifest['question_bank']) && !isset($manifest['skills']), 'Skills and Question Bank are core and not add-ons');
check(Addons::enabled('skills') === true, 'Skills are always on');
check(Addons::enabled('unknown-addon') === false, 'Other add-ons still follow their switch');
Modules::load();
check(in_array('ohmylms_skills_module_loaded', $GLOBALS['loaded_addons'], true), 'Skills entry point always loads');
check(!in_array('ohmylms_question_bank_module_loaded', $GLOBALS['loaded_addons'], true), 'Question Bank has no module entry point');
check(count($GLOBALS['loaded_addons']) === 1, 'Only the core entry point loads');

$controller = (new ReflectionClass(\OhMyLMS\Rest\V1\SkillController::class))->newInstanceWithoutConstructor();
check(!method_exists($controller, 'addon_permission'), 'Skill routes have no add-on permission gate');
Modules::load();
check(count($GLOBALS['loaded_addons']) === 1, 'Entry points load only once');
echo "Core Skills checks passed: $mode\n";
