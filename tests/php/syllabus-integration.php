<?php
/**
 * Syllabuses: any curriculum item can be one, with skill groups and skills inside it, and a CSV import.
 * Disposable WordPress database only: configured through OHMYLMS_TEST_CREDENTIALS.
 * Names are made up to illustrate the shapes of real syllabuses; they are not syllabus definitions.
 */
if (PHP_SAPI !== 'cli') { exit; }
define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
$config = json_decode(file_get_contents(getenv('OHMYLMS_TEST_CREDENTIALS')), true);
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8099';
require $config['site'] . '/wp-load.php';
if (!defined('OHMYLMS_TEST_SITE') || DB_NAME !== 'ohmylms_source_test') { throw new RuntimeException('Requires disposable test site'); }
require_once ABSPATH . 'wp-admin/includes/user.php';

use OhMyLMS\Assessment\Schema as AssessmentSchema;
use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Links;
use OhMyLMS\Curriculum\Schema;
use OhMyLMS\Curriculum\Syllabus;
use OhMyLMS\Curriculum\SyllabusCourse;
use OhMyLMS\Learning\Catalog;
use OhMyLMS\Learning\CourseProgram;
use OhMyLMS\Learning\Schema as LearningSchema;
use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\Bundles\Progress;

$checks = 0; $users = []; $item_ids = []; $stray_terms = []; $course_ids = []; $posts = [];
$admin = get_user_by('login', $config['username'])->ID;
$tag = wp_generate_password(5, false, false);
function ok($condition, $message) { global $checks; if (!$condition) { throw new RuntimeException($message); } $checks++; }
function api($method, $path, $body = [], $query = []) {
    $request = new WP_REST_Request($method, '/ohmylms/v1/' . $path);
    if ($query) { $request->set_query_params($query); }
    if ($body) { $request->set_header('Content-Type', 'application/json'); $request->set_body(wp_json_encode($body)); }
    return rest_do_request($request);
}
function code($response) { return $response->get_data()['code'] ?? ''; }
function item($name, $parent = 0, $type = 'custom', array $extra = []) {
    global $item_ids;
    $response = api('POST', 'curriculum/items', array_merge(['name' => $name, 'item_type' => $type, 'parent_id' => $parent], $extra));
    ok($response->get_status() === 201, 'Curriculum create failed: ' . wp_json_encode($response->get_data()));
    $id = (int) $response->get_data()['item']['id']; $item_ids[] = $id; return $id;
}
function count_rows($table) { global $wpdb; return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table"); }
function outline($id) { $response = api('GET', "curriculum/items/$id/syllabus"); ok($response->get_status() === 200, 'Outline failed: ' . wp_json_encode($response->get_data())); return $response->get_data(); }
function content_named($outline, $name) { foreach ($outline['contents'] as $content) { if ($content['name'] === $name) { return $content; } } return null; }
function group_in($outline, $code) { foreach ($outline['contents'] as $content) { foreach ($content['groups'] as $group) { if ($group['code'] === $code || $group['name'] === $code) { return $group; } } } return null; }
function skill_in($outline, $code) { foreach ($outline['contents'] as $content) { foreach ($content['groups'] as $group) { foreach ($group['skills'] as $skill) { if ($skill['code'] === $code) { return $skill + ['group_id' => $group['id']]; } } } } return null; }
function import_rows($id, array $rows, $dry = false) { return api('POST', "curriculum/items/$id/syllabus/import", ['rows' => $rows, 'dry_run' => $dry]); }
function snapshot_counts() {
    global $wpdb;
    return [count_rows(Items::table()), count_rows(Syllabus::groups_table()), count_rows(Syllabus::placements_table()), (int) wp_count_terms(['taxonomy' => Taxonomy::NAME, 'hide_empty' => false])];
}

try {
    wp_set_current_user($admin);
    global $wpdb;
    Schema::install(); ok(Schema::ready(), 'Curriculum tables unavailable');
    AssessmentSchema::install(); LearningSchema::install();
    foreach (array_keys(Schema::definitions()) as $name) { ok($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', Schema::table($name))) === Schema::table($name), "Table $name missing"); }
    foreach (['is_syllabus', 'skill_root_id', 'course_id'] as $column) { ok((bool) $wpdb->get_var($wpdb->prepare('SHOW COLUMNS FROM ' . Items::table() . ' LIKE %s', $column)), "Column $column missing"); }
    $start = snapshot_counts();

    // ---- Any item can become a syllabus ----
    $cambridge = item('Cambridge ' . $tag, 0, 'framework');
    $igcse = item('IGCSE', $cambridge, 'level');
    $plain = item('Mathematics 0580 ' . $tag, $igcse, 'subject');
    $row = static function ($id) { foreach (api('GET', 'curriculum/tree')->get_data()['items'] as $entry) { if ($entry['id'] === $id) { return $entry; } } return null; };
    ok($row($plain)['is_syllabus'] === false && !isset($row($plain)['syllabus']), 'a new item is an ordinary item with no syllabus counts');
    ok(api('GET', "curriculum/items/$plain/syllabus")->get_status() === 400 && code(api('GET', "curriculum/items/$plain/syllabus")) === 'ohmylms_syllabus_invalid', 'an ordinary item has no syllabus outline');
    $flagged = api('PUT', "curriculum/items/$plain", ['is_syllabus' => true]);
    ok($flagged->get_status() === 200 && $flagged->get_data()['item']['is_syllabus'] === true && $flagged->get_data()['item']['item_type'] === 'subject', 'any item becomes a syllabus and keeps its own type');
    ok($row($plain)['syllabus'] === ['groups' => 0, 'skills' => 0], 'a syllabus reports its group and skill counts');
    $typed = item('Typed syllabus ' . $tag, $igcse, 'syllabus');
    ok($row($typed)['is_syllabus'] === true, 'typing an item "syllabus" makes it one');
    $retyped = api('PUT', "curriculum/items/$typed", ['item_type' => 'unit', 'is_syllabus' => false]);
    ok($retyped->get_data()['item']['is_syllabus'] === false, 'a syllabus with no groups can be turned back into an ordinary item');

    // ---- Skill groups ----
    $syllabus = $plain;
    $other_group_owner = item('Another syllabus ' . $tag, $igcse, 'syllabus');
    $topic = item('1 Number', $syllabus, 'topic');
    $bad = api('POST', "curriculum/items/$syllabus/syllabus/groups", ['name' => '']);
    ok($bad->get_status() === 400, 'a skill group needs a name or a code');
    $elsewhere = item('Elsewhere ' . $tag, $cambridge, 'topic');
    ok(api('POST', "curriculum/items/$syllabus/syllabus/groups", ['name' => 'Lost', 'item_id' => $elsewhere])->get_status() === 400, 'a group cannot sit outside the syllabus');
    $made = api('POST', "curriculum/items/$syllabus/syllabus/groups", ['name' => 'Types of number', 'code' => 'C1.1', 'item_id' => $topic]);
    ok($made->get_status() === 201 && ($g1 = (int) $made->get_data()['group_id']) > 0, 'a group is added under a content item');
    $g2 = (int) api('POST', "curriculum/items/$syllabus/syllabus/groups", ['code' => 'C1.2', 'item_id' => $topic])->get_data()['group_id'];
    $g3 = (int) api('POST', "curriculum/items/$syllabus/syllabus/groups", ['name' => 'Syllabus-level group'])->get_data()['group_id'];
    $o = outline($syllabus);
    ok(group_in($o, 'C1.2')['name'] === 'C1.2', 'a group given only a code is named by its code');
    ok(array_column(content_named($o, '1 Number')['groups'], 'id') === [$g1, $g2] && array_column($o['contents'][0]['groups'], 'id') === [$g3], 'groups sit under the item they were added to');
    ok(api('POST', "curriculum/items/$syllabus/syllabus/groups/$g2/move", ['item_id' => $topic, 'position' => 0])->get_status() === 200 && array_column(content_named(outline($syllabus), '1 Number')['groups'], 'id') === [$g2, $g1], 'a group moves to another position');
    api('POST', "curriculum/items/$syllabus/syllabus/groups/$g2/move", ['item_id' => $topic, 'position' => 1]);
    ok(api('PUT', "curriculum/items/$syllabus/syllabus/groups/$g2", ['code' => 'C1.2x'])->get_status() === 200 && group_in(outline($syllabus), 'C1.2x')['name'] === 'C1.2', 'a code-only edit keeps the name');
    api('PUT', "curriculum/items/$syllabus/syllabus/groups/$g2", ['code' => 'C1.2', 'name' => 'Sets']);
    ok(api('PUT', "curriculum/items/$syllabus/syllabus/groups/$g2", ['name' => ''])->get_status() === 400, 'a group cannot lose its name');
    ok(api('PUT', "curriculum/items/$other_group_owner/syllabus/groups/$g2", ['name' => 'Stolen'])->get_status() === 404 && group_in(outline($syllabus), 'C1.2')['name'] === 'Sets', 'a group can only be edited through its own syllabus');
    $no_flag = api('PUT', "curriculum/items/$syllabus", ['is_syllabus' => false]);
    ok($no_flag->get_status() === 409 && code($no_flag) === 'ohmylms_syllabus_has_groups', 'a syllabus with skill groups cannot be switched off');

    // ---- Skills ----
    $made = api('POST', "curriculum/items/$syllabus/syllabus/groups/$g1/skills", ['name' => 'Identify natural numbers', 'code' => 'C1.1.1', 'description' => 'e.g. six billion']);
    ok($made->get_status() === 201 && ($t1 = (int) $made->get_data()['skill']['term_id']) > 0, 'a skill is created in a group');
    $o = outline($syllabus);
    ok($o['root_skill'] > 0 && get_term($o['root_skill'], Taxonomy::NAME)->name !== '' && (int) get_term($t1, Taxonomy::NAME)->parent === $o['root_skill'], 'skills are created under one root library skill for the syllabus');
    ok(skill_in($o, 'C1.1.1')['name'] === 'Identify natural numbers' && skill_in($o, 'C1.1.1')['description'] === 'e.g. six billion', 'the skill is in the outline with its code and notes');
    ok(get_term_meta($t1, '_ohmylms_skill_code', true) === 'C1.1.1' && Taxonomy::uuid($t1) !== '', 'the library skill carries the code and a UUID');
    $t2 = (int) api('POST', "curriculum/items/$syllabus/syllabus/groups/$g1/skills", ['name' => 'Identify prime numbers', 'code' => 'C1.1.2'])->get_data()['skill']['term_id'];
    $dup = api('POST', "curriculum/items/$syllabus/syllabus/groups/$g2/skills", ['name' => 'Another', 'code' => 'c1.1.1']);
    ok($dup->get_status() === 409 && code($dup) === 'ohmylms_syllabus_code_taken', 'a skill code is unique within the syllabus (ignoring case)');
    $same_name = api('POST', "curriculum/items/$syllabus/syllabus/groups/$g2/skills", ['name' => 'Identify natural numbers', 'code' => 'C1.2.1']);
    ok($same_name->get_status() === 201 && $same_name->get_data()['skill']['name'] === 'Identify natural numbers' && (int) $same_name->get_data()['skill']['term_id'] !== $t1, 'two skills may share their text in a syllabus: the name is kept exactly and the codes tell them apart');
    $t3 = (int) $same_name->get_data()['skill']['term_id'];
    ok(skill_in(outline($syllabus), 'C1.2.1')['name'] === skill_in(outline($syllabus), 'C1.1.1')['name'] && skill_in(outline($syllabus), 'C1.2.1')['term_id'] === $t3, 'both skills read back with their own code');
    ok(api('POST', "curriculum/items/$syllabus/syllabus/groups/$g2/skills", ['name' => ''])->get_status() === 400, 'a skill needs a name');
    ok(api('POST', "curriculum/items/$syllabus/syllabus/groups/$g2/skills", ['name' => 'x', 'code' => str_repeat('c', 41)])->get_status() === 400, 'a skill code has a length limit');
    $maths_skill = (int) api('POST', "curriculum/items/$syllabus/syllabus/groups/$g2/skills", ['name' => 'Use a > 0 and D < 0, x<y & "quotes"', 'code' => 'C1.2.2'])->get_data()['skill']['term_id'];
    ok(skill_in(outline($syllabus), 'C1.2.2')['name'] === 'Use a > 0 and D < 0, x<y & "quotes"', 'maths symbols survive saving and reading back');
    // The skill library and the curriculum skill lists read the same text, not "&gt;" and "&lt;".
    ok(api('GET', "skills/$maths_skill")->get_data()['name'] === 'Use a > 0 and D < 0, x<y & "quotes"', 'the skill API returns the text itself, not HTML entities');
    ok(in_array('Use a > 0 and D < 0, x<y & "quotes"', array_column(api('GET', 'skills')->get_data()['skills'], 'name'), true), 'the skill library lists it the same way');
    ok(Links::targets('skill', 'quotes')[0]['title'] === 'Use a > 0 and D < 0, x<y & "quotes"', 'the curriculum skill picker shows the text too');
    // The same objective text in another syllabus is a different skill: skills are never merged by name.
    $other = item('Other syllabus ' . $tag, $igcse, 'syllabus');
    $og = (int) api('POST', "curriculum/items/$other/syllabus/groups", ['name' => 'G'])->get_data()['group_id'];
    $ot = api('POST', "curriculum/items/$other/syllabus/groups/$og/skills", ['name' => 'Identify natural numbers', 'code' => 'C1.1.1']);
    ok($ot->get_status() === 201 && $ot->get_data()['skill']['name'] === 'Identify natural numbers' && (int) $ot->get_data()['skill']['term_id'] !== $t1, 'the same text in another syllabus is a separate skill');
    ok(outline($other)['root_skill'] !== outline($syllabus)['root_skill'], 'each syllabus has its own root skill');
    // Edit, reorder, move, remove.
    ok(api('PUT', "curriculum/items/$syllabus/syllabus/skills/$t2", ['name' => 'Identify primes', 'description' => 'a note'])->get_status() === 200 && skill_in(outline($syllabus), 'C1.1.2')['name'] === 'Identify primes', 'a skill is renamed and annotated');
    ok(api('PUT', "curriculum/items/$syllabus/syllabus/skills/$t2", ['code' => 'C1.1.1'])->get_status() === 409, 'a skill cannot take another skill\'s code');
    $foreign = (int) $ot->get_data()['skill']['term_id'];
    ok(api('PUT', "curriculum/items/$syllabus/syllabus/skills/$foreign", ['name' => 'Hijacked'])->get_status() === 404 && get_term($foreign, Taxonomy::NAME)->name === 'Identify natural numbers', 'a skill of another syllabus cannot be edited through this one');
    ok(api('POST', "curriculum/items/$syllabus/syllabus/groups/$g1/skills/$t2/move", ['position' => 0])->get_status() === 200 && array_column(group_in(outline($syllabus), 'C1.1')['skills'], 'term_id') === [$t2, $t1], 'a skill moves up in its group');
    ok(api('POST', "curriculum/items/$syllabus/syllabus/groups/$g1/skills/$t2/move", ['group_id' => $g2])->get_status() === 200 && array_column(group_in(outline($syllabus), 'C1.2')['skills'], 'term_id') === [$t3, $maths_skill, $t2] && array_column(group_in(outline($syllabus), 'C1.1')['skills'], 'term_id') === [$t1], 'a skill moves into another group');
    ok(api('DELETE', "curriculum/items/$syllabus/syllabus/groups/$g2/skills/$t2")->get_status() === 200 && term_exists($t2, Taxonomy::NAME) && !in_array($t2, array_column(group_in(outline($syllabus), 'C1.2')['skills'], 'term_id'), true), 'removing a skill from a group keeps it in the library');
    $existing = (int) wp_insert_term('Library skill ' . $tag, Taxonomy::NAME)['term_id'];
    ok(api('POST', "curriculum/items/$syllabus/syllabus/groups/$g2/skills", ['term_id' => $existing])->get_status() === 201 && api('POST', "curriculum/items/$syllabus/syllabus/groups/$g1/skills", ['term_id' => $existing])->get_status() === 409, 'an existing library skill can be placed once per syllabus');
    $totals = outline($syllabus)['totals'];
    ok($totals === ['contents' => 1, 'groups' => 3, 'skills' => 4], 'totals count contents, groups and skills: ' . wp_json_encode($totals));
    ok($row($syllabus)['syllabus'] === ['groups' => 3, 'skills' => 4], 'the tree rows carry the same counts');

    // ---- Importing ----
    $csv = [
        ['line' => 2, 'content' => '2 Algebra', 'group_code' => 'C2.1', 'group' => 'Basic algebra', 'skill_code' => 'C2.1.1', 'skill' => 'Simplify expressions', 'description' => 'collect like terms'],
        ['line' => 3, 'content' => '2 Algebra', 'group_code' => 'C2.1', 'group' => 'Basic algebra', 'skill_code' => 'C2.1.2', 'skill' => 'Expand brackets'],
        ['line' => 4, 'content_code' => '11.1', 'content' => 'Квадрат тэгшитгэл ба тэнцэтгэл биш', 'group_code' => 'М11.1А', 'skill_code' => 'М11.1А1', 'skill' => 'Квадрат тэгшитгэлийн графикийг зөв зурах.'],
        ['line' => 5, 'content_code' => '11.1', 'content' => 'Квадрат тэгшитгэл ба тэнцэтгэл биш', 'group_code' => 'М11.1А', 'skill_code' => 'М11.1А2', 'skill' => 'Үргэлж эерэг байх нөхцөлийг (a > 0, D < 0) хэрэглэх.'],
        ['line' => 6, 'content' => 'Paper 1 > 3 Geometry', 'group' => 'Angles', 'skill' => 'Name angles'],
        ['line' => 7, 'content' => '4 Heading only'],
    ];
    $before = snapshot_counts();
    $dry = import_rows($syllabus, $csv, true);
    ok($dry->get_status() === 200 && $dry->get_data()['report']['valid'] === true && $dry->get_data()['report']['applied'] === false, 'a dry run reports without applying');
    $r = $dry->get_data()['report'];
    ok($r['contents']['create'] === 5 && $r['groups']['create'] === 3 && $r['skills']['create'] === 5, 'the dry run counts what would be created: ' . wp_json_encode([$r['contents'], $r['groups'], $r['skills']]));
    ok(snapshot_counts() === $before, 'a dry run changes nothing');
    $applied = import_rows($syllabus, $csv, false);
    ok($applied->get_status() === 200 && $applied->get_data()['report']['applied'] === true && isset($applied->get_data()['contents']), 'the import is applied and the new outline returned');
    $o = outline($syllabus);
    ok(content_named($o, '2 Algebra') && content_named($o, 'Paper 1') && content_named($o, '3 Geometry') && content_named($o, '4 Heading only') && content_named($o, '11.1') === null && content_named($o, 'Квадрат тэгшитгэл ба тэнцэтгэл биш')['code'] === '11.1', 'contents are created, nested by ">" paths, with their codes');
    ok(content_named($o, '3 Geometry')['parent_id'] === content_named($o, 'Paper 1')['id'] && content_named($o, 'Paper 1')['parent_id'] === $syllabus, 'a content path nests under the syllabus');
    ok(group_in($o, 'М11.1А')['name'] === 'М11.1А' && skill_in($o, 'М11.1А2')['name'] === 'Үргэлж эерэг байх нөхцөлийг (a > 0, D < 0) хэрэглэх.', 'Mongolian names, codes and maths text are stored and read back exactly');
    ok(array_column(group_in($o, 'C2.1')['skills'], 'code') === ['C2.1.1', 'C2.1.2'] && skill_in($o, 'C2.1.1')['description'] === 'collect like terms', 'imported skills keep file order and notes');
    $after_import = snapshot_counts();
    $again = import_rows($syllabus, $csv, false);
    $r = $again->get_data()['report'];
    ok($again->get_status() === 200 && $r['valid'] && $r['contents']['create'] + $r['groups']['create'] + $r['skills']['create'] + $r['skills']['update'] + $r['skills']['move'] === 0, 'importing the same file again changes nothing in the plan');
    ok(snapshot_counts() === $after_import, 'a repeated import adds no rows');
    // Edits: new name for a code, a moved skill, a new skill; nothing is deleted.
    $moving_term = skill_in($o, 'C2.1.2')['term_id'];
    $edit = [
        ['line' => 2, 'content' => '2 Algebra', 'group_code' => 'C2.1', 'skill_code' => 'C2.1.1', 'skill' => 'Simplify algebraic expressions'],
        ['line' => 3, 'content' => '2 Algebra', 'group_code' => 'C2.2', 'group' => 'Equations', 'skill_code' => 'C2.1.2', 'skill' => 'Expand brackets'],
        ['line' => 4, 'content' => '2 Algebra', 'group_code' => 'C2.2', 'skill_code' => 'C2.2.1', 'skill' => 'Solve linear equations'],
    ];
    $applied = import_rows($syllabus, $edit, false);
    $r = $applied->get_data()['report'];
    ok($applied->get_status() === 200 && $r['skills']['update'] === 1 && $r['skills']['move'] === 1 && $r['skills']['create'] === 1 && $r['groups']['create'] === 1, 'an edit file updates, moves and adds: ' . wp_json_encode($r['skills']));
    $o = outline($syllabus);
    ok(skill_in($o, 'C2.1.1')['name'] === 'Simplify algebraic expressions' && skill_in($o, 'C2.1.2')['group_id'] === group_in($o, 'C2.2')['id'] && skill_in($o, 'C2.1.2')['term_id'] === $moving_term, 'the renamed skill, and the moved skill (the same library skill, not a copy), are where the file put them');
    ok(skill_in($o, 'М11.1А1') !== null && skill_in($o, 'C1.1.1') !== null, 'skills the file does not mention stay');
    // Long names in Cyrillic that begin alike: WordPress alone gives them the same truncated slug and fails the insert.
    $long = str_repeat('Урвуу функцийн ', 3);
    $cyrillic = import_rows($syllabus, [
        ['line' => 2, 'content' => 'Long names', 'group_code' => 'L1', 'skill_code' => 'L1.1', 'skill' => $long . 'тодорхойлогдох мужийг тодорхойлох.'],
        ['line' => 3, 'content' => 'Long names', 'group_code' => 'L1', 'skill_code' => 'L1.2', 'skill' => $long . 'тодорхойлогдох мужийг мэдэх.'],
        ['line' => 4, 'content' => 'Long names', 'group_code' => 'L1', 'skill_code' => 'L1.3', 'skill' => str_repeat('Нэг ', 40) . 'урт нэр, хэтэрхий урт.'],
    ], false);
    ok($cyrillic->get_status() === 200 && $cyrillic->get_data()['report']['skills']['create'] === 3 && $cyrillic->get_data()['report']['applied'] === true, 'long Cyrillic names that begin alike are all created: ' . wp_json_encode($cyrillic->get_data()['report']['errors'] ?? $cyrillic->get_data()));
    $o = outline($syllabus);
    ok(skill_in($o, 'L1.1')['name'] === $long . 'тодорхойлогдох мужийг тодорхойлох.' && skill_in($o, 'L1.2') !== null && skill_in($o, 'L1.3') !== null, 'and read back exactly');
    ok(strlen(get_term(skill_in($o, 'L1.1')['term_id'], Taxonomy::NAME)->slug) < 40, 'imported skills get a short slug of their own');
    // Problems stop everything.
    $before = snapshot_counts();
    $broken = import_rows($syllabus, [['line' => 2, 'content' => 'Fine', 'group' => 'G', 'skill' => 'ok'], ['line' => 3, 'skill_code' => 'NO-NAME'], ['line' => 4, 'group_code' => 'G2', 'skill_code' => 'C1.1.1', 'skill' => 'Clash']], false);
    ok($broken->get_status() === 200 && $broken->get_data()['report']['valid'] === false && $broken->get_data()['report']['applied'] === false && count($broken->get_data()['report']['errors']) >= 1, 'a bad row is reported and nothing is applied');
    ok(snapshot_counts() === $before, 'a refused import changes nothing');
    ok(import_rows($cambridge, $csv)->get_status() === 400, 'only a syllabus accepts an import');
    ok(api('POST', "curriculum/items/$syllabus/syllabus/import", [])->get_status() === 400, 'an import needs rows');
    // A failure part-way rolls the whole import back, including the library skills already created.
    // A syllabus at depth 8: "Fits" lands at 9, then "A > B > C" needs depth 11 and runs into the limit of 10.
    $deep = $cambridge;
    for ($i = 0; $i < 7; $i++) { $deep = item("Deep $i $tag", $deep, 'topic'); }
    $deep_syllabus = (int) $deep; ok(api('PUT', "curriculum/items/$deep_syllabus", ['is_syllabus' => true])->get_status() === 200, 'the deep syllabus is flagged');
    $before = snapshot_counts();
    $failed = import_rows($deep_syllabus, [
        ['line' => 2, 'content' => 'Fits', 'group' => 'Early group', 'skill' => 'Created before the failure', 'skill_code' => 'Z1'],
        ['line' => 3, 'content' => 'A > B > C', 'group' => 'Too deep', 'skill' => 'Never created', 'skill_code' => 'Z2'],
    ], false);
    ok($failed->get_status() >= 400 && snapshot_counts() === $before, 'an import that fails part-way leaves nothing behind (' . $failed->get_status() . ')');
    ok(get_terms(['taxonomy' => Taxonomy::NAME, 'name' => 'Created before the failure', 'hide_empty' => false, 'fields' => 'ids']) === [], 'no library skill is left from the failed import');

    // ---- Bundles and membership integration ----
    $scope = Items::with_descendants($syllabus);
    ok(in_array($t1, Links::object_ids($scope, 'skill'), true) && in_array(skill_in(outline($syllabus), 'C2.1.1')['term_id'], Links::object_ids($scope, 'skill'), true) && !in_array($t1, Links::object_ids([$other], 'skill'), true), 'skills in groups count as the syllabus\'s skills (and only its) for course bundles');
    $paths = array_map(static function ($m) { return $m['name']; }, Links::memberships('skill', $t1));
    ok($paths === ['1 Number'], 'a skill reports the content item its group sits under: ' . wp_json_encode($paths));
    $student = wp_create_user('syl-' . wp_generate_password(8, false, false), wp_generate_password(24), 'syl-' . wp_generate_password(8, false, false) . '@example.invalid'); $users[] = $student;
    $summary = Progress::curriculum($student, $syllabus);
    ok($summary !== null && in_array($t1, $summary['skill_ids'], true), 'the learner dashboard lists the syllabus\'s skills');

    // ---- Deleting ----
    $dependents = api('GET', "curriculum/items/$topic")->get_data()['dependents'];
    ok($dependents['own_groups'] === 2, 'delete confirmation counts the skill groups under an item');
    $needs = api('DELETE', "curriculum/items/$topic", ['children' => '']);
    ok($needs->get_status() === 409 && code($needs) === 'ohmylms_curriculum_needs_confirmation', 'deleting an item with skill groups needs confirmation');
    $groups_before = count_rows(Syllabus::groups_table());
    $skill_ids_in_topic = array_merge(array_column(group_in(outline($syllabus), 'C1.1')['skills'], 'term_id'), array_column(group_in(outline($syllabus), 'C1.2')['skills'], 'term_id'));
    $deleted = api('DELETE', "curriculum/items/$topic", ['children' => '', 'confirm' => true]);
    ok($deleted->get_status() === 200 && $deleted->get_data()['groups_removed'] === 2 && count_rows(Syllabus::groups_table()) === $groups_before - 2, 'deleting an item removes its skill groups');
    foreach ($skill_ids_in_topic as $term_id) { ok(term_exists((int) $term_id, Taxonomy::NAME) !== null, 'the skills of a deleted group stay in the library'); }
    $loose = api('DELETE', "curriculum/items/$syllabus/syllabus/groups/$g3");
    ok($loose->get_status() === 200, 'an empty group is deleted without confirmation');
    $with_skills = group_in(outline($syllabus), 'C2.1');
    $needs = api('DELETE', "curriculum/items/$syllabus/syllabus/groups/{$with_skills['id']}");
    ok($needs->get_status() === 409 && code($needs) === 'ohmylms_syllabus_needs_confirmation', 'deleting a group that holds skills needs confirmation');
    $kept = array_column($with_skills['skills'], 'term_id');
    ok(api('DELETE', "curriculum/items/$syllabus/syllabus/groups/{$with_skills['id']}", ['confirm' => true])->get_data()['skills_detached'] === count($kept) && term_exists((int) $kept[0], Taxonomy::NAME), 'a confirmed group delete keeps its skills');
    // Deleting a library skill drops its placement; deleting the root skill lets it be made again.
    $victim = skill_in(outline($syllabus), 'М11.1А1');
    wp_delete_term($victim['term_id'], Taxonomy::NAME);
    ok(skill_in(outline($syllabus), 'М11.1А1') === null && (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Syllabus::placements_table() . ' WHERE term_id=%d', $victim['term_id'])) === 0, 'deleting a library skill removes its placement');
    // Deleting a root skill leaves its children as top-level skills; remember the one so cleanup can find it.
    $stray_terms[] = $foreign;
    $root = outline($other)['root_skill']; wp_delete_term($root, Taxonomy::NAME);
    ok((int) Items::get($other)['skill_root_id'] === 0, 'deleting the root skill forgets it');
    ok(api('POST', "curriculum/items/$other/syllabus/groups/$og/skills", ['name' => 'Again', 'code' => 'A1'])->get_status() === 201 && outline($other)['root_skill'] > 0, 'a new root skill is created when needed');

    // ---- A syllabus is also a course: skill groups are its chapters, skills are its outcomes ----
    $learn = item('Course syllabus ' . $tag, $igcse, 'subject');
    ok((int) $row($learn)['course_id'] === 0, 'an item that is not a syllabus has no course');
    api('PUT', "curriculum/items/$learn", ['is_syllabus' => true]);
    $course = (int) $row($learn)['course_id']; $course_ids[] = $course;
    ok($course > 0 && get_post_type($course) === OHMYLMS_COURSE_CPT && get_post_status($course) === 'draft', 'making an item a syllabus gives it a draft course');
    ok(get_the_title($course) === 'Course syllabus ' . $tag && SyllabusCourse::owner($course) === $learn && SyllabusCourse::course_id($learn) === $course, 'the course is named after the syllabus and knows it');
    ok(in_array($course, array_column(Links::for_item($learn)['course'], 'id'), true), 'the course is placed under its syllabus in the curriculum');
    ok(Catalog::chapters($course) === [] && CourseProgram::editor($course)['draft']['mode'] === 'skill-based', 'a syllabus course starts with no chapters, in skill-based mode');
    ok(outline($learn)['course']['id'] === $course, 'the outline reports the course');
    $again = api('POST', "curriculum/items/$learn/syllabus/course");
    ok($again->get_status() === 200 && $again->get_data()['course']['id'] === $course && count(get_posts(['post_type' => OHMYLMS_COURSE_CPT, 'post_status' => 'any', 'numberposts' => -1, 'meta_key' => SyllabusCourse::ITEM_META, 'meta_value' => $learn, 'fields' => 'ids'])) === 1, 'asking for the course again keeps the one it has');
    $typed_course = item('Typed course syllabus ' . $tag, $igcse, 'syllabus');
    ok((int) $row($typed_course)['course_id'] > 0, 'an item typed "syllabus" gets its course as it is created');

    $unit = item('1 Number', $learn, 'topic');
    $group = static function ($name, $code, $item) use ($learn) { return (int) api('POST', "curriculum/items/$learn/syllabus/groups", ['name' => $name, 'code' => $code, 'item_id' => $item])->get_data()['group_id']; };
    $skill = static function ($group_id, $name, $code) use ($learn) { $made = api('POST', "curriculum/items/$learn/syllabus/groups/$group_id/skills", ['name' => $name, 'code' => $code]); ok($made->get_status() === 201, 'skill add failed: ' . wp_json_encode($made->get_data())); return (int) $made->get_data()['skill']['term_id']; };
    $ga = $group('Types of number', 'C1.1', $unit); $gb = $group('Sets', 'C1.2', $unit);
    $prime = $skill($ga, 'Identify prime numbers', 'C1.1.1'); $square = $skill($ga, 'Identify square numbers', 'C1.1.2'); $notation = $skill($gb, 'Use set notation', 'C1.2.1');
    $catalog = static function () use ($course) { return Catalog::payload($course); };
    $chapter_named = static function ($title) use ($catalog) { foreach ($catalog()['chapters'] as $chapter) { if ($chapter['name'] === $title) { return (int) $chapter['id']; } } return 0; };
    $codes_in = static function ($chapter_id) use ($catalog) { return array_column(array_filter($catalog()['skills'], static function ($entry) use ($chapter_id) { return $entry['chapter_id'] === $chapter_id; }), 'code'); };
    ok(array_column($catalog()['chapters'], 'name') === ['C1.1 · Types of number', 'C1.2 · Sets'], 'every skill group is a chapter of the course, in group order');
    $chapter_a = $chapter_named('C1.1 · Types of number'); $chapter_b = $chapter_named('C1.2 · Sets');
    ok($codes_in($chapter_a) === ['C1.1.1', 'C1.1.2'] && $codes_in($chapter_b) === ['C1.2.1'], 'every skill is an outcome in its group\'s chapter, in order');
    $first = $catalog()['skills'][0];
    ok($first['target'] === 'proficient' && $first['required'] === false, 'a mirrored skill starts at Proficient and not required');
    ok(get_post_meta($chapter_a, SyllabusCourse::GROUP_META, true) === group_in(outline($learn), 'C1.1')['uuid'], 'a mirrored chapter carries its group\'s uuid');
    ok(group_in(outline($learn), 'C1.1')['chapter_id'] === $chapter_a && group_in(outline($learn), 'C1.2')['chapter_id'] === $chapter_b, 'the outline tells each group which chapter it became');

    // What the catalog adds by hand survives every change to the syllabus.
    $lesson = wp_insert_post(['post_type' => OHMYLMS_LESSON_CPT, 'post_title' => 'Primes lesson ' . $tag, 'post_status' => 'publish']); $posts[] = $lesson;
    $hand_skill = wp_insert_term('Library skill ' . $tag . ' by hand', Taxonomy::NAME); $hand_term = (int) $hand_skill['term_id'];
    $hand_chapter = Catalog::add_chapter($course, 'Revision')['id'];
    $outcomes = array_map(static function ($entry) { return ['term_id' => $entry['term_id'], 'target' => $entry['target'], 'required' => $entry['required'], 'chapter_id' => $entry['chapter_id']]; }, $catalog()['skills']);
    foreach ($outcomes as &$outcome) { if ($outcome['term_id'] === $prime) { $outcome['target'] = 'mastered'; $outcome['required'] = true; } }
    unset($outcome);
    $outcomes[] = ['term_id' => $hand_term, 'target' => 'proficient', 'required' => false, 'chapter_id' => $hand_chapter];
    $saved = Catalog::save_draft($course, ['outcomes' => $outcomes, 'attachments' => [['type' => 'lesson', 'content_id' => $lesson, 'chapter_id' => $chapter_a, 'skill_ids' => [$prime], 'required' => false]]]);
    ok($saved === true, 'the catalog can edit the mirrored course: ' . (is_wp_error($saved) ? $saved->get_error_message() : ''));
    api('PUT', "curriculum/items/$learn/syllabus/groups/$ga", ['name' => 'Number types']);
    ok($chapter_named('C1.1 · Number types') === $chapter_a, 'renaming a group renames its chapter and keeps it');
    $after = $catalog();
    $prime_row = current(array_filter($after['skills'], static function ($entry) use ($prime) { return $entry['term_id'] === $prime; }));
    ok($prime_row['target'] === 'mastered' && $prime_row['required'] === true, 'a target or required flag set in the catalog survives a syllabus change');
    ok(in_array($hand_term, array_column($after['skills'], 'term_id'), true) && $chapter_named('Revision') === $hand_chapter, 'a chapter and a skill added by hand are left alone');
    ok(count($after['attachments']) === 1 && $after['attachments'][0]['content_id'] === $lesson && $after['attachments'][0]['skill_ids'] === [$prime], 'a lesson attached to a skill stays attached');

    // Which skills the course requires is decided from the editor, a few at a time, and nothing else about them changes.
    $row_of = static function ($term) use ($catalog) { return current(array_filter($catalog()['skills'], static function ($entry) use ($term) { return $entry['term_id'] === $term; })); };
    $require = api('PUT', "curriculum/items/$learn/syllabus/course/skills", ['skills' => [['term_id' => $square, 'required' => true, 'target' => 'mastered'], ['term_id' => $hand_term, 'required' => true], ['term_id' => 999999, 'required' => true]]]);
    ok($require->get_status() === 200 && $row_of($square)['required'] === true && $row_of($square)['target'] === 'mastered' && $row_of($square)['chapter_id'] === $chapter_a, 'a skill of the syllabus can be required, with a target, from the editor');
    ok($row_of($hand_term)['required'] === false && $row_of($notation)['required'] === false, 'only the skills asked for change, and a skill that is not in the syllabus is left alone');
    ok(api('PUT', "curriculum/items/$learn/syllabus/course/skills", ['skills' => 'all'])->get_status() === 400, 'the skills to change must be a list');
    $not_ready = Catalog::publish($course);
    ok(is_wp_error($not_ready) && strpos(implode(' ', (array) ($not_ready->get_error_data()['errors'] ?? [])), 'approved questions') !== false, 'a required skill without approved questions keeps the course from being published');

    // Order follows the groups; a skill moved between groups changes chapter.
    api('POST', "curriculum/items/$learn/syllabus/groups/$gb/move", ['item_id' => $unit, 'position' => 0]);
    ok(array_column($catalog()['chapters'], 'name') === ['C1.2 · Sets', 'C1.1 · Number types', 'Revision'], 'moving a group moves its chapter, and a chapter added by hand stays last');
    api('POST', "curriculum/items/$learn/syllabus/groups/$ga/skills/$square/move", ['group_id' => $gb, 'position' => 0]);
    ok($codes_in($chapter_b) === ['C1.1.2', 'C1.2.1'] && $codes_in($chapter_a) === ['C1.1.1'], 'a skill moved to another group moves to that group\'s chapter');
    api('DELETE', "curriculum/items/$learn/syllabus/groups/$ga/skills/$prime");
    ok(!in_array($prime, array_column($catalog()['skills'], 'term_id'), true), 'a skill taken out of the syllabus leaves the course');
    $dropped = api('DELETE', "curriculum/items/$learn/syllabus/groups/$gb", ['confirm' => true]);
    ok($dropped->get_status() === 200 && $chapter_named('C1.2 · Sets') === 0 && !array_intersect([$square, $notation], array_column($catalog()['skills'], 'term_id')), 'deleting a group removes its chapter and its skills from the course');
    ok($chapter_named('Revision') === $hand_chapter && in_array($hand_term, array_column($catalog()['skills'], 'term_id'), true), 'deleting a group leaves what was added by hand');
    ok(array_column($catalog()['chapters'], 'name') === ['C1.1 · Number types', 'Revision'], 'the course keeps one chapter per group plus the one made by hand');

    // Renaming the syllabus renames the course; an import brings its chapters and skills along.
    api('PUT', "curriculum/items/$learn", ['name' => 'Renamed syllabus ' . $tag]);
    ok(get_the_title($course) === 'Renamed syllabus ' . $tag, 'renaming a syllabus renames its course');
    $imported = import_rows($learn, [['line' => 2, 'content' => '2 Algebra', 'group_code' => 'C2.1', 'group' => 'Equations', 'skill_code' => 'C2.1.1', 'skill' => 'Solve linear equations'], ['line' => 3, 'content' => '2 Algebra', 'group_code' => 'C2.1', 'group' => 'Equations', 'skill_code' => 'C2.1.2', 'skill' => 'Rearrange formulae']]);
    ok($imported->get_status() === 200 && $imported->get_data()['report']['applied'] === true, 'a CSV import applies');
    $equations = $chapter_named('C2.1 · Equations');
    ok($equations > 0 && $codes_in($equations) === ['C2.1.1', 'C2.1.2'] && $imported->get_data()['course']['id'] === $course, 'an import adds its groups as chapters and its skills as outcomes');
    $summary = outline($learn)['course'];
    ok($summary['chapters'] === 3 && $summary['skills'] === 3 && $summary['published'] === false && $summary['edit'] === "#/course-edit/$course/settings" && !isset($summary['catalog']), 'the outline summarises the course and points at its editor, not at a catalog page');

    // A syllabus course may hold every skill of its syllabus, an ordinary course only 200.
    $many = []; for ($n = 1; $n <= 205; $n++) { $many[] = ['line' => 10 + $n, 'content' => '3 Many', 'group_code' => 'M1', 'group' => 'Many skills', 'skill_code' => 'M1.' . $n, 'skill' => "Many skill $n $tag"]; }
    $big = import_rows($learn, $many);
    ok($big->get_status() === 200 && empty($big->get_data()['course_error']) && count($catalog()['skills']) === 208, 'a syllabus course holds more than 200 skills: ' . ($big->get_data()['course_error'] ?? ''));
    $plain_course = wp_insert_post(['post_type' => OHMYLMS_COURSE_CPT, 'post_title' => 'Ordinary course ' . $tag, 'post_status' => 'draft']); $course_ids[] = $plain_course;
    ok(CourseProgram::too_many_outcomes($plain_course, 201) === true && CourseProgram::too_many_outcomes($course, 201) === false && CourseProgram::too_many_outcomes($course, Syllabus::MAX_SKILLS + 1) === true && CourseProgram::too_many_outcomes($plain_course, 200) === false, 'only a syllabus course may list more than 200 skills');

    // A deleted course is made again, and a deleted syllabus leaves its course in place.
    wp_delete_post($course, true);
    ok(outline($learn)['course'] === null && empty(array_column(Links::for_item($learn)['course'], 'id')), 'a deleted course leaves nothing behind in the outline or the curriculum');
    $made_again = api('POST', "curriculum/items/$learn/syllabus/course");
    $new_course = (int) $made_again->get_data()['course']['id']; $course_ids[] = $new_course;
    ok($made_again->get_status() === 200 && $new_course > 0 && $new_course !== $course && get_post_status($new_course) === 'draft', 'asking again makes a new course');
    ok($made_again->get_data()['course']['chapters'] === 3 && $made_again->get_data()['course']['skills'] === 207, 'the new course is built from the syllabus again');
    $gone = item('Syllabus to delete ' . $tag, $igcse, 'syllabus'); $gone_course = (int) $row($gone)['course_id']; $course_ids[] = $gone_course;
    ok($gone_course > 0 && api('DELETE', "curriculum/items/$gone", ['confirm' => true])->get_status() === 200, 'a syllabus is deleted');
    ok(get_post_status($gone_course) === 'draft' && SyllabusCourse::owner($gone_course) === 0 && empty($wpdb->get_col($wpdb->prepare('SELECT item_id FROM ' . Links::table() . " WHERE object_type='course' AND object_id=%d", $gone_course))), 'deleting a syllabus keeps its course and drops the link');

    // ---- Permissions ----
    $teacher =wp_create_user('syl-t-' . wp_generate_password(8, false, false), wp_generate_password(24), 'syl-t-' . wp_generate_password(8, false, false) . '@example.invalid'); $users[] = $teacher;
    (new WP_User($teacher))->set_role('editor');
    wp_set_current_user($teacher);
    ok(api('GET', "curriculum/items/$syllabus/syllabus")->get_status() === 403 && import_rows($syllabus, $csv, true)->get_status() === 403 && api('POST', "curriculum/items/$syllabus/syllabus/course")->get_status() === 403 && api('PUT', "curriculum/items/$syllabus/syllabus/course/skills", ['skills' => []])->get_status() === 403, 'only administrators can read, import, give a syllabus its course or choose its required skills');
    wp_set_current_user(0);
    ok(api('GET', "curriculum/items/$syllabus/syllabus")->get_status() === 401, 'guests are refused');
    wp_set_current_user($admin);
} finally {
    wp_set_current_user($admin);
    // The courses of the syllabuses (and their chapters) and the lessons made for them go before the items do.
    foreach ($item_ids as $id) { $found = Items::get($id); if ($found && (int) ($found['course_id'] ?? 0)) { $course_ids[] = (int) $found['course_id']; } }
    foreach (array_unique($course_ids) as $course_id) {
        if (get_post_type($course_id) !== OHMYLMS_COURSE_CPT) { continue; }
        foreach (Catalog::chapters($course_id) as $chapter) { wp_delete_post($chapter['id'], true); }
        wp_delete_post($course_id, true);
    }
    foreach ($posts as $post_id) { wp_delete_post($post_id, true); }
    foreach (array_reverse($item_ids) as $id) { if (Items::get($id)) { Items::delete($id, 'delete', true); } }
    // Remove the library skills these syllabuses created: each root skill's children first, then the root.
    $roots = $wpdb->get_col($wpdb->prepare("SELECT m.term_id FROM {$wpdb->termmeta} m WHERE m.meta_key=%s AND m.meta_value IN (" . implode(',', array_fill(0, max(1, count($item_ids)), '%d')) . ')', array_merge([Syllabus::ROOT_META], $item_ids ?: [0])));
    foreach ($roots as $term_id) {
        if (!term_exists((int) $term_id, Taxonomy::NAME)) { continue; }
        foreach (get_term_children((int) $term_id, Taxonomy::NAME) as $child) { wp_delete_term((int) $child, Taxonomy::NAME); }
        wp_delete_term((int) $term_id, Taxonomy::NAME);
    }
    foreach (get_terms(['taxonomy' => Taxonomy::NAME, 'hide_empty' => false, 'name__like' => 'Library skill ' . $tag, 'fields' => 'ids']) as $term_id) { wp_delete_term((int) $term_id, Taxonomy::NAME); }
    foreach (get_terms(['taxonomy' => Taxonomy::NAME, 'hide_empty' => false, 'name__like' => $tag, 'fields' => 'ids']) as $term_id) { wp_delete_term((int) $term_id, Taxonomy::NAME); }
    foreach ($stray_terms as $term_id) { if (term_exists((int) $term_id, Taxonomy::NAME)) { wp_delete_term((int) $term_id, Taxonomy::NAME); } }
    foreach ($users as $user_id) { wp_delete_user($user_id); }
    if (isset($start)) {
        $end = snapshot_counts();
        if ($end !== $start) { fwrite(STDERR, 'Cleanup left rows behind: ' . wp_json_encode([$start, $end]) . "\n"); }
    }
}
echo "$checks syllabus integration checks passed.\n";
