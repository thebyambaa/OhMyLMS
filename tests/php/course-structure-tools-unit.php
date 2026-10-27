<?php
/** Pure checks for the course structure MCP tools and the unit lesson-group rules (no WordPress database). */
define('ABSPATH', __DIR__ . '/');
require dirname(__DIR__, 2) . '/includes/MCP/CourseStructureTools.php';
require dirname(__DIR__, 2) . '/includes/Learning/ProgramGroups.php';
use OhMyLMS\MCP\CourseStructureTools;
use OhMyLMS\Learning\ProgramGroups;
$checks = 0;
function check($condition, $message) { global $checks; if (!$condition) { throw new RuntimeException($message); } $checks++; }

// ---- Tool definitions ----
$tools = CourseStructureTools::definitions();
$expected = ['get_course_program', 'save_course_program', 'create_course_unit', 'rename_course_unit', 'set_unit_number', 'reorder_course_units', 'delete_course_unit', 'set_unit_lesson_groups', 'get_course_links', 'save_course_links'];
check(array_keys($tools) === $expected, 'the structure tools are exactly the documented ten');
$routes = [];
$controller = file_get_contents(dirname(__DIR__, 2) . '/includes/Rest/V1/CourseStructureToolsController.php');
$curriculum = file_get_contents(dirname(__DIR__, 2) . '/includes/Rest/V1/CurriculumController.php');
foreach ($tools as $name => $tool) {
    check(preg_match('/^[a-z]+(_[a-z]+)+$/', $name) === 1, "$name is snake_case");
    foreach (['method', 'route', 'description', 'properties', 'required', 'list', 'readonly', 'destructive', 'idempotent'] as $key) {
        check(array_key_exists($key, $tool), "$name defines $key");
    }
    check(in_array($tool['method'], ['GET', 'PUT', 'POST'], true) && is_string($tool['description']) && strlen($tool['description']) > 40, "$name has a method and a real description");
    check(!array_diff($tool['required'], array_keys($tool['properties'])), "$name requires only declared properties");
    check(in_array('id', $tool['required'], true) && $tool['properties']['id']['type'] === 'integer' && $tool['properties']['id']['minimum'] === 1, "$name needs a course id");
    check($tool['list'] === false, "$name is not a paged list");
    check($tool['readonly'] === ($tool['method'] === 'GET'), "$name annotation matches its method");
    check(!($tool['destructive'] && $tool['idempotent']), "$name is not destructive and idempotent, which WordPress would route as DELETE without a body");
    check(!isset($routes[$tool['method'] . $tool['route']]), "$name has its own method and route");
    $routes[$tool['method'] . $tool['route']] = true;
    check(strpos($tool['route'], '{id}') !== false, "$name routes by course id");
    if (strpos($tool['route'], CourseStructureTools::PREFIX . '/') === 0) {
        check($name === 'set_unit_lesson_groups' ? strpos($controller, 'save_unit_groups') !== false : strpos($controller, "'$name'") !== false, "$name is served by the structure controller");
    } else {
        check(strpos($curriculum, "'/courses/(?P<id>\\d+)/links'") !== false && $tool['route'] === '/courses/{id}/links', "$name uses the course editor's links route");
    }
    check(stripos($name, 'publish') === false, "$name does not publish");
    foreach (['revision', 'dry_run'] as $guard) {
        if (isset($tool['properties'][$guard])) {
            check($tool['properties'][$guard]['type'] === ($guard === 'dry_run' ? 'boolean' : 'string'), "$name $guard has the right type");
        }
    }
    foreach ($tool['properties'] as $key => $schema) {
        check(isset($schema['type']), "$name.$key declares a type");
        if ($schema['type'] === 'array') {
            check(isset($schema['maxItems']) && $schema['maxItems'] <= 500, "$name.$key caps its list");
        }
        if ($schema['type'] === 'string' && empty($schema['pattern']) && empty($schema['enum'])) {
            check(isset($schema['maxLength']), "$name.$key caps its text");
        }
        if ($schema['type'] === 'object') {
            check(false === ($schema['additionalProperties'] ?? true), "$name.$key is closed");
        }
    }
}
foreach (['outcomes', 'attachments', 'groups'] as $list) {
    check($tools['save_course_program']['properties'][$list]['items']['additionalProperties'] === false, "program $list rows are closed");
}
check(in_array('revision', $tools['save_course_program']['required'], true) && in_array('revision', $tools['set_unit_lesson_groups']['required'], true), 'program writes quote the revision they read');
check(stripos($tools['save_course_program']['description'], 'never publishes') !== false && stripos($tools['set_unit_lesson_groups']['description'], 'never publishes') !== false, 'program writes say they never publish');
check($tools['save_course_program']['properties']['dry_run']['default'] === false && isset($tools['set_unit_lesson_groups']['properties']['dry_run']), 'program writes can be checked first');
check(!in_array('practice', $tools['save_course_program']['properties']['attachments']['items']['properties']['type']['enum'], true), 'practice rows stay derived from skills');

// ---- The destructive tool ----
$delete = $tools['delete_course_unit'];
check($delete['destructive'] && !$delete['readonly'] && !$delete['idempotent'], 'unit deletion is marked destructive');
check(in_array('confirm', $delete['required'], true) && $delete['properties']['confirm']['type'] === 'boolean', 'unit deletion needs an explicit confirm flag');
check(stripos($delete['description'], 'destructive') !== false && stripos($delete['description'], 'confirm=true') !== false, 'unit deletion explains the flag');
foreach (['get_course_program', 'get_course_links'] as $read) {
    check($tools[$read]['readonly'] && !$tools[$read]['destructive'], "$read is read-only");
}
foreach (['create_course_unit', 'rename_course_unit', 'set_unit_number', 'reorder_course_units'] as $safe) {
    check(!$tools[$safe]['readonly'] && !$tools[$safe]['destructive'], "$safe is a plain write");
}
check($tools['save_course_links']['destructive'] && $tools['save_course_links']['properties']['links']['maxItems'] === 50, 'link replacement is a bounded replace');
check($tools['set_unit_number']['properties']['number']['maxLength'] === 10, 'unit numbers are capped at ten characters');

// ---- Unit lesson groups ----
$A = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
$B = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
$I1 = '11111111-1111-1111-1111-111111111111';
$I2 = '22222222-2222-2222-2222-222222222222';
$I3 = '33333333-3333-3333-3333-333333333333';
$I4 = '44444444-4444-4444-4444-444444444444';
$program = [
    'groups' => [
        ['id' => $A, 'chapter_id' => 10, 'title' => 'Old A'],
        ['id' => $B, 'chapter_id' => 20, 'title' => 'Other unit'],
    ],
    'items' => [
        ['id' => $I1, 'type' => 'lesson', 'chapter_id' => 10, 'content_id' => 1, 'group_id' => $A],
        ['id' => $I2, 'type' => 'lesson', 'chapter_id' => 10, 'content_id' => 2],
        ['id' => $I3, 'type' => 'lesson', 'chapter_id' => 20, 'content_id' => 3, 'group_id' => $B],
        ['id' => $I4, 'type' => 'lesson', 'chapter_id' => 0, 'content_id' => 4],
    ],
    'outcomes' => [
        ['term_id' => 7, 'target' => 'proficient', 'chapter_id' => 10, 'group_id' => $A],
        ['term_id' => 8, 'target' => 'proficient', 'chapter_id' => 20],
    ],
];
$ids = function () { static $n = 0; return sprintf('cccccccc-cccc-cccc-cccc-%012d', ++$n); };
$by_id = function (array $rows, $key, $id) { foreach ($rows as $row) { if ((string) $row[$key] === (string) $id) { return $row; } } return null; };

$out = ProgramGroups::replace_unit($program, 10, [
    ['id' => $A, 'title' => '  <b>Warm-up</b> ', 'item_ids' => [$I2], 'skill_ids' => [7]],
    ['title' => 'Practice', 'item_ids' => [$I1]],
], $ids);
check(is_array($out), 'a valid change is applied');
check(count($out['groups']) === 3 && $out['groups'][0]['id'] === $B && $out['groups'][1]['id'] === $A && $out['groups'][1]['title'] === 'Warm-up', 'other units keep their groups; this unit gets the new ones in order, titles cleaned');
check($out['groups'][2]['id'] === 'cccccccc-cccc-cccc-cccc-000000000001' && $out['groups'][2]['chapter_id'] === 10, 'a group without an id is created in this unit');
check($by_id($out['items'], 'id', $I2)['group_id'] === $A && $by_id($out['items'], 'id', $I1)['group_id'] === $out['groups'][2]['id'], 'listed items follow their new group');
check($by_id($out['outcomes'], 'term_id', 7)['group_id'] === $A, 'listed skills follow their group');
check($by_id($out['items'], 'id', $I3)['group_id'] === $B && !isset($by_id($out['outcomes'], 'term_id', 8)['group_id']), 'other units are untouched');

$cleared = ProgramGroups::replace_unit($program, 10, [], $ids);
check(is_array($cleared) && $cleared['groups'] === [['id' => $B, 'chapter_id' => 20, 'title' => 'Other unit']], 'an empty list deletes the unit\'s groups only');
check(!isset($by_id($cleared['items'], 'id', $I1)['group_id']) && !isset($by_id($cleared['outcomes'], 'term_id', 7)['group_id']), 'rows of a deleted group become ungrouped, never dropped');
check(count($cleared['items']) === 4 && count($cleared['outcomes']) === 2, 'no row is lost');
$none = ProgramGroups::replace_unit(['items' => $program['items'], 'groups' => [['id' => $A, 'chapter_id' => 10, 'title' => 'x']]], 10, [], $ids);
check(is_array($none) && !array_key_exists('groups', $none), 'a program without groups stores no groups key');

check(ProgramGroups::replace_unit($program, 0, [], $ids) === 'chapter', 'a unit is required');
check(ProgramGroups::replace_unit($program, 10, [['title' => '']], $ids) === 'title', 'groups need a name');
check(ProgramGroups::replace_unit($program, 10, [['title' => '<i></i>']], $ids) === 'title', 'a name of only markup is empty');
check(ProgramGroups::replace_unit($program, 10, ['x'], $ids) === 'invalid', 'a group must be an object');
check(ProgramGroups::replace_unit($program, 10, [['id' => $B, 'title' => 'Steal']], $ids) === 'group_chapter', 'a group of another unit cannot be reused');
check(ProgramGroups::replace_unit($program, 10, [['id' => 'dddddddd-dddd-dddd-dddd-dddddddddddd', 'title' => 'Ghost']], $ids) === 'group', 'an unknown group id is refused');
check(ProgramGroups::replace_unit($program, 10, [['id' => $A, 'title' => 'One'], ['id' => $A, 'title' => 'Two']], $ids) === 'id', 'a group id appears once');
check(ProgramGroups::replace_unit($program, 10, [['title' => 'G', 'item_ids' => [$I3]]], $ids) === 'item', 'an item of another unit cannot join');
check(ProgramGroups::replace_unit($program, 10, [['title' => 'G', 'item_ids' => [$I4]]], $ids) === 'item', 'an unplaced item cannot join');
check(ProgramGroups::replace_unit($program, 10, [['title' => 'G', 'item_ids' => ['nope']]], $ids) === 'item', 'an unknown item is refused');
check(is_array(ProgramGroups::replace_unit($program, 10, [['title' => 'G', 'skill_ids' => [8]]], $ids)), 'a course skill can also join a lesson in another unit');
check(ProgramGroups::replace_unit($program, 10, [['title' => 'G', 'skill_ids' => ['7']]], $ids) === 'item', 'skill ids must be integers');
check(ProgramGroups::replace_unit($program, 10, [['title' => 'G', 'item_ids' => [$I1]], ['title' => 'H', 'item_ids' => [$I1]]], $ids) === 'duplicate', 'an item sits in one group only');
check(is_array(ProgramGroups::replace_unit($program, 10, [['title' => 'G', 'skill_ids' => [7]], ['title' => 'H', 'skill_ids' => [7]]], $ids)), 'a skill can be taught in several lessons');

echo "Course structure tool checks passed: $checks\n";
