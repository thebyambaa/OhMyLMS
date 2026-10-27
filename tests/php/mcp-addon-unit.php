<?php
// MCP lifecycle and authorization checks without a WordPress database or network.
define('ABSPATH', __DIR__);
define('OHMYLMS_FILE', dirname(__DIR__, 2) . '/ohmylms.php');
define('OHMYLMS_VERSION', 'test');
$GLOBALS['mcp_integrations'] = [];
$GLOBALS['mcp_hooks'] = [];
$GLOBALS['mcp_abilities'] = [];
function get_option($key, $default = []) { return $key === 'ohmylms_integrations' ? $GLOBALS['mcp_integrations'] : $default; }
function add_action($hook, $callback, $priority = 10, $args = 1) { $GLOBALS['mcp_hooks'][$hook][] = $callback; }
function add_filter($hook, $callback, $priority = 10, $args = 1) { add_action($hook, $callback, $priority, $args); }
function __($value, $domain) { return $value; }
function plugins_url($path, $file) { return $path; }
function current_user_can($capability) { return true; }
function wp_register_ability($name, $definition) { $GLOBALS['mcp_abilities'][$name] = $definition; }
function is_wp_error($value) { return $value instanceof WP_Error; }
class WP_Error {
    public $code;
    public function __construct($code, $message, $data) { $this->code = $code; }
}
require dirname(__DIR__, 2) . '/includes/MCP/Server.php';
require dirname(__DIR__, 2) . '/includes/MCP/Abilities.php';
require dirname(__DIR__, 2) . '/includes/Extensions/Registry.php';
require dirname(__DIR__, 2) . '/includes/Learning/NativeLessonEditor.php';
// Every tool family, so a new one never makes this test fail for want of a require.
foreach (glob(dirname(__DIR__, 2) . '/includes/MCP/*Tools.php') as $tools_file) {
    require_once $tools_file;
}
use OhMyLMS\MCP\Server;
use OhMyLMS\MCP\Abilities;
function mcp_addon_check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
Server::init();
mcp_addon_check(!Server::enabled(), 'MCP is opt-in.');
mcp_addon_check(!isset($GLOBALS['mcp_hooks']['wp_abilities_api_init']), 'Disabled add-on does not register abilities.');
$manifest = Server::manifest(['ai_model' => ['is_enable' => 1], 'other' => ['is_enable' => 1]]);
mcp_addon_check(!isset($manifest['ai_model']) && isset($manifest['other']), 'MCP replaces AI Suite while retaining other add-ons.');
mcp_addon_check($manifest['mcp']['hasSettings'] && $manifest['mcp']['is_enable'] === 0, 'Disabled MCP remains visible with Manage.');
mcp_addon_check(!Server::readiness()['ready'], 'Disabled server reports unavailable.');
mcp_addon_check(Server::authorize(null)->code === 'mcp_disabled', 'Disabled server refuses authentication before inspecting credentials.');
$adapter = new class { public $calls = 0; public function create_server(...$args) { $this->calls++; return true; } };
Server::register_server($adapter);
mcp_addon_check($adapter->calls === 0, 'Disabled add-on does not create a server.');
$GLOBALS['mcp_integrations']['mcp'] = ['is_enable' => '1'];
mcp_addon_check(Server::enabled() && Server::manifest([])['mcp']['is_enable'] === 1, 'Saved switch enables MCP.');
Server::register_server($adapter);
mcp_addon_check($adapter->calls === 1, 'Enabled add-on creates a server.');
Abilities::register();
$tool_count = count(Server::tools());
mcp_addon_check($tool_count >= 50 && count($GLOBALS['mcp_abilities']) === $tool_count, 'Enabled MCP registers one ability for every tool: ' . count($GLOBALS['mcp_abilities']) . ' of ' . $tool_count);
mcp_addon_check($GLOBALS['mcp_abilities']['ohmylms/save-lesson']['meta']['annotations']['destructive'], 'Existing published lesson edits are accurately marked as writes.');
mcp_addon_check($GLOBALS['mcp_abilities']['ohmylms/get-lesson-objectives']['meta']['annotations']['readonly'], 'Lesson mapping reads remain read-only.');
mcp_addon_check($GLOBALS['mcp_abilities']['ohmylms/get-course-objectives']['meta']['annotations']['readonly'], 'Course objective reads are read-only.');
mcp_addon_check($GLOBALS['mcp_abilities']['ohmylms/set-course-objectives']['meta']['annotations']['destructive'], 'Replacing course targets may remove references.');
mcp_addon_check(!$GLOBALS['mcp_abilities']['ohmylms/set-course-objectives']['meta']['annotations']['idempotent'], 'Revision-checked replacement is not labeled idempotent.');
mcp_addon_check(!$GLOBALS['mcp_abilities']['ohmylms/save-combined-skill-mapping']['meta']['annotations']['readonly'], 'Combined coverage tool is explicitly a write.');
mcp_addon_check($GLOBALS['mcp_abilities']['ohmylms/list-courses']['meta']['annotations']['readonly'], 'Existing content tools remain read-only.');
mcp_addon_check(!$GLOBALS['mcp_abilities']['ohmylms/save-skill-mapping']['meta']['annotations']['readonly'], 'Mapping writes are accurately annotated.');
mcp_addon_check($GLOBALS['mcp_abilities']['ohmylms/remove-skill-mapping']['meta']['annotations']['destructive'], 'Mapping removals are accurately annotated.');
mcp_addon_check(!$GLOBALS['mcp_abilities']['ohmylms/create-shared-skill-roadmap']['meta']['annotations']['idempotent'], 'Roadmap creation is not presented as idempotent.');
mcp_addon_check(count(array_unique(array_keys(Server::tools()))) === $tool_count && count(Abilities::names()) === $tool_count, 'Tool names stay unique after merging every tool family.');
mcp_addon_check($GLOBALS['mcp_abilities']['ohmylms/get-course-program']['meta']['annotations']['readonly'], 'Program reads are read-only.');
mcp_addon_check(!$GLOBALS['mcp_abilities']['ohmylms/save-course-program']['meta']['annotations']['readonly'], 'Program writes are writes.');
mcp_addon_check($GLOBALS['mcp_abilities']['ohmylms/delete-course-unit']['meta']['annotations']['destructive'], 'Unit deletion is marked destructive.');
mcp_addon_check($GLOBALS['mcp_abilities']['ohmylms/delete-course-unit']['input_schema']['required'] === ['id', 'chapter_id', 'confirm', 'revision'] && $GLOBALS['mcp_abilities']['ohmylms/delete-course-unit']['input_schema']['additionalProperties'] === false, 'Unit deletion requires an explicit confirm and a closed schema.');
// Writes to the course program carry the revision read first, so a stale outline is refused; reads and
// course links (outside the program routes) do not.
foreach (\OhMyLMS\MCP\CourseStructureTools::definitions() as $mcp_tool => $mcp_definition) {
    $mcp_schema = $GLOBALS['mcp_abilities']['ohmylms/' . str_replace('_', '-', $mcp_tool)]['input_schema'];
    $mcp_write  = 'GET' !== $mcp_definition['method'] && 0 === strpos($mcp_definition['route'], \OhMyLMS\MCP\CourseStructureTools::PREFIX . '/');
    mcp_addon_check($mcp_write === in_array('revision', $mcp_schema['required'], true) && $mcp_write === isset($mcp_schema['properties']['revision']), "$mcp_tool requires the revision only when it writes to the program.");
}
$permission = $GLOBALS['mcp_abilities']['ohmylms/list-courses']['permission_callback'];
mcp_addon_check($permission(), 'Enabled administrator can use an ability.');
$GLOBALS['mcp_integrations']['mcp']['is_enable'] = 0;
mcp_addon_check(!$permission(), 'Disabling the switch immediately blocks existing abilities.');
mcp_addon_check(Server::authorize(null)->code === 'mcp_disabled', 'Disabling the switch immediately blocks existing tokens.');
echo "MCP add-on lifecycle checks passed.\n";
