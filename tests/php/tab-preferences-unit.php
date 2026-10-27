<?php
require dirname(__DIR__, 2) . '/includes/Extensions/TabPreferences.php';
use OhMyLMS\Extensions\TabPreferences;
class WP_Error { public function __construct(public $code, public $message, public $data) {} }
function is_wp_error($value) { return $value instanceof WP_Error; }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function rest_ensure_response($value) { return $value; }
function get_current_user_id() { return $GLOBALS['user']; }
function current_user_can($capability) { return in_array($capability, $GLOBALS['capabilities'], true); }
function get_user_meta($user, $key, $single) { return $GLOBALS['meta'][$user][$key] ?? ''; }
function wp_slash($value) { return is_array($value) ? array_map('wp_slash', $value) : (is_string($value) ? addslashes($value) : $value); }
function unslash($value) { return is_array($value) ? array_map('unslash', $value) : (is_string($value) ? stripslashes($value) : $value); }
function update_user_meta($user, $key, $value) { if (!empty($GLOBALS['fail'])) return false; $GLOBALS['meta'][$user][$key] = unslash($value); return true; }
function register_rest_route($namespace, $route, $args) { $GLOBALS['route'] = $args; }
function check($value, $message) { if (!$value) throw new RuntimeException($message); }
class Request implements ArrayAccess {
    public function __construct(private $scope, private $body) {}
    public function get_json_params() { return $this->body; }
    public function offsetExists($offset): bool { return $offset === 'scope'; }
    public function offsetGet($offset): mixed { return $this->scope; }
    public function offsetSet($offset, $value): void {}
    public function offsetUnset($offset): void {}
}
$GLOBALS['user'] = 1;
$GLOBALS['capabilities'] = [];
TabPreferences::register_routes();
check(!$GLOBALS['route']['permission_callback'](), 'Non-managers cannot write preferences');
$GLOBALS['capabilities'] = ['manage_options'];
check($GLOBALS['route']['permission_callback'](), 'Administrators can save their layout');
$layout = ['order' => ['courses', 'catalog'], 'groups' => [['id' => 'g1', 'name' => "Teacher's <b>group</b>", 'background' => '#abcdef', 'text' => '#112233', 'collapsed' => true]], 'assignments' => ['catalog' => 'g1', 'courses' => 'unknown']];
$prepared = TabPreferences::prepare($layout);
check($prepared['groups'][0]['name'] === "Teacher's group", 'Names are sanitized');
check(!isset($prepared['assignments']['courses']), 'Unknown group assignments are removed');
check(TabPreferences::save(new Request('content-hub', $layout)) === $prepared, 'Save returns normalized layout');
check(TabPreferences::read()['content-hub'] === $prepared, 'Saved names preserve quotes');
check(!is_wp_error(TabPreferences::save(new Request('memberships', $layout))), 'Other sections save independently');
check(isset(TabPreferences::read()['content-hub'], TabPreferences::read()['memberships']), 'A save retains other section layouts');
$GLOBALS['user'] = 2;
check(TabPreferences::read() === [], 'Layouts belong only to the current user');
$GLOBALS['user'] = 1;
check(is_wp_error(TabPreferences::save(new Request('unknown', $layout))), 'Unknown sections are rejected');
$bad = $layout;
$bad['groups'][0]['background'] = ['bad'];
check(is_wp_error(TabPreferences::prepare($bad)), 'Malformed color types are rejected without throwing');
$bad['groups'][0]['background'] = 'red';
check(is_wp_error(TabPreferences::prepare($bad)), 'Only hex colors are accepted');
check(is_wp_error(TabPreferences::prepare(['order' => []])), 'Incomplete data is rejected');
$GLOBALS['fail'] = true;
$layout['order'] = ['catalog', 'courses'];
check(is_wp_error(TabPreferences::save(new Request('content-hub', $layout))), 'Database failures are reported');
echo "Tab preferences checks passed.\n";
