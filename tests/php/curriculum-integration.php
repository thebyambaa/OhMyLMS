<?php
/**
 * Curriculum, Course Bundles, skill mapping and dashboard progress.
 * Disposable WordPress database only: configured through OHMYLMS_TEST_CREDENTIALS.
 * Names used below are made up to illustrate shapes; they are not real syllabus definitions.
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
use OhMyLMS\Curriculum\SkillMappings;
use OhMyLMS\Curriculum\Tree;
use OhMyLMS\Learning\CompletionPolicy;
use OhMyLMS\Learning\Schema as LearningSchema;
use OhMyLMS\QuestionBank\Banks;
use OhMyLMS\Skills\Mastery;
use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\Bundles\Follows;
use OhMyLMS\Bundles\Frontend;
use OhMyLMS\Bundles\Progress;
use OhMyLMS\Bundles\Bundles;

$checks = 0; $posts = []; $users = []; $terms = []; $banks = []; $bundle_ids = []; $item_ids = [];
$previous_integrations = get_option('ohmylms_integrations', []);
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
function created($path, $body) {
    global $posts;
    $response = api('POST', $path, $body);
    ok($response->get_status() === 201, 'Create failed: ' . wp_json_encode($response->get_data()));
    $id = (int) $response->get_data()['id']; $posts[] = $id; return $id;
}
function user($role = 'subscriber') {
    global $users;
    $id = wp_create_user('bundle-' . wp_generate_password(9, false, false), wp_generate_password(24), 'bundle-' . wp_generate_password(9, false, false) . '@example.invalid');
    (new WP_User($id))->set_role($role); $users[] = $id; return $id;
}
function skill($name) {
    global $terms, $tag;
    $id = (int) wp_insert_term($name . ' ' . $tag . wp_generate_password(3, false, false), Taxonomy::NAME)['term_id']; $terms[] = $id; return $id;
}
function enroll($student, $course, $progress = 'running') {
    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'ohmylms_user_enrollment', ['user_id' => $student, 'course_id' => $course, 'status' => 'enrolled', 'progress' => $progress, 'start_date' => current_time('mysql')]);
    return (int) $wpdb->insert_id;
}
function tree() { return api('GET', 'curriculum/tree')->get_data()['items']; }
function item($name, $parent = 0, $type = 'custom', array $extra = []) {
    global $item_ids;
    $response = api('POST', 'curriculum/items', array_merge(['name' => $name, 'item_type' => $type, 'parent_id' => $parent], $extra));
    ok($response->get_status() === 201, 'Curriculum create failed: ' . wp_json_encode($response->get_data()));
    $id = (int) $response->get_data()['item']['id']; $item_ids[] = $id; return $id;
}
function children_of($parent) { return array_values(array_map(static function ($row) { return $row['name']; }, array_filter(tree(), static function ($row) use ($parent) { return $row['parent_id'] === $parent; }))); }
function snapshot() { return array_map(static function ($row) { return [$row['id'], $row['parent_id'], $row['position'], $row['name']]; }, tree()); }
function dense($parent) {
    $positions = array_values(array_map(static function ($row) { return $row['position']; }, array_filter(tree(), static function ($row) use ($parent) { return $row['parent_id'] === $parent; })));
    return $positions === ($positions ? range(0, count($positions) - 1) : []);
}
function count_rows($table) { global $wpdb; return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table"); }
function evidence($student, $term, $event, $correct, $question, $family, $when, $part = 'p1') {
    global $wpdb;
    $ok = $wpdb->insert(AssessmentSchema::table('skill_evidence'), ['grade_event_id' => $event, 'student_id' => $student, 'term_id' => $term, 'part_id' => $part, 'role' => 'primary', 'awarded' => $correct ? 1 : 0, 'available' => 1, 'independent' => 1, 'first_try' => 1, 'difficulty' => 'standard', 'family_id' => $family, 'source_type' => 'practice', 'source_id' => 0, 'question_id' => $question, 'version_id' => 0, 'mapping_version' => 1, 'evidence_at' => gmdate('Y-m-d H:i:s', $when), 'superseded' => 0]);
    ok($ok !== false, 'Evidence insert failed: ' . $wpdb->last_error);
}
function states($student) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare('SELECT term_id, level, review_due, evidence_count, independent_correct, families, score FROM ' . AssessmentSchema::table('student_skill_state') . ' WHERE student_id=%d ORDER BY term_id', $student), ARRAY_A);
}
function view($bundle_id, $student) { return Progress::for_bundle(Bundles::get($bundle_id), $student); }
/** The course store memoizes content counts per request; a single-process test must reset them after changing content. */
function reset_course_counts() {
    $class = new ReflectionClass(\OhMyLMS\DataStores\CourseStore::class);
    foreach (['lessons_count_cache', 'quiz_count_cache', 'assignment_count_cache', 'all_content_count_cache'] as $name) { $property = $class->getProperty($name); $property->setAccessible(true); $property->setValue([]); }
}
function member($view, $type, $id) { foreach ($view['members'] as $member) { if ($member['type'] === $type && $member['id'] === $id) { return $member; } } return null; }

try {
    wp_set_current_user($admin);
    Schema::install(); ok(Schema::ready(), 'Curriculum tables unavailable');
    AssessmentSchema::install(); LearningSchema::install();
    global $wpdb;
    foreach (array_keys(Schema::definitions()) as $name) { ok($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', Schema::table($name))) === Schema::table($name), "Table $name missing"); }
    $start = ['items' => count_rows(Items::table()), 'bundles' => count_rows(Bundles::table()), 'links' => count_rows(Links::table()), 'follows' => count_rows(Follows::table()), 'mappings' => count_rows(SkillMappings::table()), 'members' => count_rows(Bundles::members_table())];

    // ---- Hierarchy: different depths, shared names, admin-defined types, stable IDs ----
    $cambridge = item('Cambridge ' . $tag, 0, 'framework');
    $igcse = item('IGCSE', $cambridge, 'level');
    $maths = item('Mathematics', $igcse, 'subject');
    $syllabus = item('Mathematics syllabus', $maths, 'syllabus', ['code' => 'TEST-0001', 'version' => '2025-2027', 'description' => 'Illustrative only']);
    $alevel = item('AS and A Level', $cambridge, 'level');
    $sat = item('Digital SAT ' . $tag, 0, 'framework');
    $reading = item('Reading and Writing', $sat, 'section');
    $words = item('Words in context', $reading, 'topic');
    $sat_math = item('Math', $sat, 'section');
    $national = item('Mongolian National Curriculum ' . $tag, 0, 'framework');
    $grade9 = item('Grade 9', $national, 'grade');
    $national_maths = item('Mathematics', $grade9, 'subject');
    $ib = item('IB Diploma Programme ' . $tag, 0, 'ib-programme');
    $rows = [];
    foreach (tree() as $row) { $rows[$row['id']] = $row; }
    ok(Tree::depth(Items::parents(), $syllabus) === 4 && Tree::depth(Items::parents(), $words) === 3 && Tree::depth(Items::parents(), $national_maths) === 3, 'Hierarchies of different depths not supported');
    ok($rows[$maths]['name'] === $rows[$national_maths]['name'] && $rows[$maths]['uuid'] !== $rows[$national_maths]['uuid'] && $maths !== $national_maths, 'Items with the same name must stay separate records');
    ok($rows[$ib]['item_type'] === 'ib-programme', 'Administrator-defined item types not stored');
    ok($rows[$syllabus]['code'] === 'TEST-0001' && $rows[$syllabus]['version'] === '2025-2027' && $rows[$syllabus]['description'] === 'Illustrative only', 'Code, version and description not stored');
    ok(preg_match('/^[0-9a-f-]{36}$/', $rows[$cambridge]['uuid']) === 1 && count(array_unique(array_column($rows, 'uuid'))) === count($rows), 'UUIDs missing or duplicated');
    ok(children_of($cambridge) === ['IGCSE', 'AS and A Level'] && dense($cambridge) && dense(0), 'Creation did not append siblings in order');
    ok(!array_key_exists('learning_mode', $rows[$syllabus]) && !array_key_exists('mode', $rows[$syllabus]), 'Curriculum items must not carry a learning mode');

    // ---- Validation ----
    foreach ([['name' => ''], ['name' => str_repeat('n', 191)], ['name' => 'x', 'item_type' => '9lives'], ['name' => 'x', 'parent_id' => 999999], ['name' => 'x', 'description' => str_repeat('d', 2001)], ['name' => 'x', 'code' => str_repeat('c', 61)], ['name' => 'x', 'version' => str_repeat('v', 61)]] as $bad) {
        $response = api('POST', 'curriculum/items', $bad);
        ok($response->get_status() === 400, 'Invalid input accepted: ' . wp_json_encode($bad) . ' -> ' . $response->get_status());
    }
    ok(api('GET', 'curriculum/items/999999')->get_status() === 404 && api('PUT', 'curriculum/items/999999', ['name' => 'x'])->get_status() === 404, 'Missing item should be 404');
    $depth_ids = []; $parent = 0;
    for ($level = 1; $level <= Items::MAX_DEPTH; $level++) { $parent = item('Depth ' . $level, $parent); $depth_ids[] = $parent; }
    $too_deep = api('POST', 'curriculum/items', ['name' => 'Too deep', 'parent_id' => $parent]);
    ok($too_deep->get_status() === 400 && code($too_deep) === 'ohmylms_curriculum_depth', 'Depth limit not enforced on create');

    // ---- Reorder, move between parents, cycles, depth ----
    $before_ids = [$maths => $rows[$maths]['uuid'], $syllabus => $rows[$syllabus]['uuid']];
    $moved = api('POST', "curriculum/items/$alevel/move", ['parent_id' => $cambridge, 'position' => 0]);
    ok($moved->get_status() === 200 && children_of($cambridge) === ['AS and A Level', 'IGCSE'] && dense($cambridge), 'Reordering siblings failed');
    $moved = api('POST', "curriculum/items/$maths/move", ['parent_id' => $alevel]);
    ok($moved->get_status() === 200 && children_of($alevel) === ['Mathematics'] && children_of($igcse) === [] && dense($igcse) && dense($alevel), 'Move between parents failed');
    $after = []; foreach (tree() as $row) { $after[$row['id']] = $row; }
    ok($after[$syllabus]['parent_id'] === $maths && $after[$maths]['uuid'] === $before_ids[$maths] && $after[$syllabus]['uuid'] === $before_ids[$syllabus], 'A moved branch lost its children or stable ID');
    $frozen = snapshot();
    foreach ([[$cambridge, $syllabus], [$cambridge, $cambridge], [$maths, $syllabus], [$alevel, $syllabus]] as [$id, $under]) {
        $response = api('POST', "curriculum/items/$id/move", ['parent_id' => $under]);
        ok($response->get_status() === 400 && code($response) === 'ohmylms_curriculum_cycle', "Circular move accepted ($id under $under)");
    }
    ok(snapshot() === $frozen, 'A rejected move changed the tree');
    $deep_move = api('POST', "curriculum/items/$cambridge/move", ['parent_id' => end($depth_ids)]);
    ok($deep_move->get_status() === 400 && code($deep_move) === 'ohmylms_curriculum_depth' && snapshot() === $frozen, 'Moving a branch past the depth limit was accepted');
    ok(api('POST', "curriculum/items/$maths/move", ['parent_id' => 999999])->get_status() === 400 && snapshot() === $frozen, 'Moving under a missing parent was accepted');
    ok(api('POST', 'curriculum/items/999999/move', ['parent_id' => 0])->get_status() === 404, 'Moving a missing item should be 404');
    api('POST', "curriculum/items/$igcse/move", ['parent_id' => $cambridge, 'position' => 99]);
    ok(children_of($cambridge) === ['AS and A Level', 'IGCSE'], 'Large positions should clamp to the end');
    api('POST', "curriculum/items/$igcse/move", ['parent_id' => $cambridge, 'position' => -4]);
    ok(children_of($cambridge) === ['IGCSE', 'AS and A Level'] && dense($cambridge), 'Negative positions should clamp to the start');
    api('POST', "curriculum/items/$syllabus/move", ['parent_id' => 0]);
    ok(in_array('Mathematics syllabus', children_of(0), true) && children_of($maths) === [], 'Move to the top level failed');
    api('POST', "curriculum/items/$syllabus/move", ['parent_id' => $maths]);
    ok(children_of($maths) === ['Mathematics syllabus'], 'Moving back failed');
    foreach (array_reverse($depth_ids) as $id) { api('DELETE', "curriculum/items/$id", ['confirm' => true]); }

    // ---- Editing and stale saves ----
    $current = api('GET', "curriculum/items/$syllabus")->get_data()['item'];
    $stale = api('PUT', "curriculum/items/$syllabus", ['name' => 'Stale write', 'expected_updated_at' => '2000-01-01 00:00:00']);
    ok($stale->get_status() === 409 && code($stale) === 'ohmylms_curriculum_stale' && api('GET', "curriculum/items/$syllabus")->get_data()['item']['name'] === 'Mathematics syllabus', 'A stale edit overwrote newer data');
    $saved = api('PUT', "curriculum/items/$syllabus", ['name' => 'Mathematics syllabus (revised)', 'version' => '2028', 'expected_updated_at' => $current['updated_at']]);
    ok($saved->get_status() === 200 && $saved->get_data()['item']['version'] === '2028' && $saved->get_data()['item']['code'] === 'TEST-0001', 'Partial update lost untouched fields');
    ok(api('PUT', "curriculum/items/$syllabus", ['parent_id' => $cambridge, 'name' => 'Mathematics syllabus'])->get_data()['item']['parent_id'] === $maths, 'Plain edits must not change the parent');

    // ---- Serialized writers: another session holding the lock blocks structural changes ----
    $second = new mysqli(DB_HOST === '127.0.0.1:10005' ? '127.0.0.1' : DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, 10005);
    $lock_name = Items::LOCK . '-' . md5($wpdb->prefix);
    $second->query("SELECT GET_LOCK('" . $second->real_escape_string($lock_name) . "', 0)");
    $started = microtime(true);
    $blocked = Items::move($grade9, 0, null);
    ok(is_wp_error($blocked) && $blocked->get_error_code() === 'ohmylms_curriculum_busy' && microtime(true) - $started >= 4, 'A concurrent structural change was not serialized');
    $second->query("SELECT RELEASE_LOCK('" . $second->real_escape_string($lock_name) . "')"); $second->close();
    ok(!is_wp_error(Items::move($grade9, $national, null)), 'Lock was not released');

    // ---- Content fixtures ----
    $igcse_english = created('courses', ['name' => 'IGCSE English prep ' . $tag, 'status' => 'publish']);
    $sat_prep = created('courses', ['name' => 'Digital SAT prep ' . $tag, 'status' => 'publish']);
    $other_english = created('courses', ['name' => 'Other English course ' . $tag, 'status' => 'publish']);
    $draft_course = created('courses', ['name' => 'Draft course ' . $tag, 'status' => 'draft']);
    $exam = created('quiz', ['name' => 'Mock exam ' . $tag, 'status' => 'publish']);
    $skill_a = skill('Linear equations (syllabus A)'); $skill_b = skill('Linear equations (syllabus B)');
    $bank = Banks::create('Curriculum bank ' . $tag); $banks[] = (int) $bank['id'];

    // ---- Links: shared across modules, many-to-many, references only ----
    foreach ([['course', $igcse_english], ['skill', $skill_a], ['bank', (int) $bank['id']], ['quiz', $exam]] as [$type, $id]) {
        $response = api('POST', "curriculum/items/$syllabus/links", ['object_type' => $type, 'object_id' => $id]);
        // A syllabus is also a course, so this item is already linked to its own course.
        ok($response->get_status() === 200 && count($response->get_data()['links'][$type]) === ($type === 'course' ? 2 : 1), "Link of type $type failed: " . wp_json_encode($response->get_data()));
    }
    ok(api('POST', "curriculum/items/$syllabus/links", ['object_type' => 'course', 'object_id' => $igcse_english])->get_status() === 200 && count(Links::for_item($syllabus)['course']) === 2, 'Duplicate links should be idempotent');
    api('POST', "curriculum/items/$words/links", ['object_type' => 'course', 'object_id' => $igcse_english]);
    ok(count(Links::memberships('course', $igcse_english)) === 2, 'One course should be linkable under several curriculum items');
    ok(api('POST', "curriculum/items/$syllabus/links", ['object_type' => 'lesson', 'object_id' => 1])->get_status() === 400, 'Unknown link type accepted');
    ok(api('POST', "curriculum/items/$syllabus/links", ['object_type' => 'course', 'object_id' => $exam])->get_status() === 404, 'A quiz was accepted as a course link');
    ok(api('POST', "curriculum/items/$syllabus/links", ['object_type' => 'skill', 'object_id' => 999999])->get_status() === 404, 'A missing skill was accepted');
    ok(api('POST', 'curriculum/items/999999/links', ['object_type' => 'course', 'object_id' => $igcse_english])->get_status() === 404, 'A link on a missing item was accepted');
    $shown = api('GET', "curriculum/items/$syllabus")->get_data();
    $english_link = current(array_filter($shown['links']['course'], static function ($entry) use ($igcse_english) { return $entry['id'] === $igcse_english; }));
    ok($english_link['title'] === get_the_title($igcse_english) && $shown['links']['bank'][0]['title'] === 'Curriculum bank ' . $tag && $shown['links']['skill'][0]['available'] === true, 'Linked content titles missing');
    ok(get_post_status($igcse_english) === 'publish' && !metadata_exists('post', $igcse_english, '_ohmylms_learning_program'), 'Linking changed the course');
    $targets = api('GET', 'curriculum/link-targets', [], ['type' => 'course', 'search' => 'English'])->get_data();
    ok(count(array_filter($targets, static function ($row) use ($other_english) { return $row['id'] === $other_english; })) === 1, 'Link search missed a course');
    ok(api('DELETE', "curriculum/items/$syllabus/links", ['object_type' => 'bank', 'object_id' => (int) $bank['id']])->get_status() === 200 && api('DELETE', "curriculum/items/$syllabus/links", ['object_type' => 'bank', 'object_id' => (int) $bank['id']])->get_status() === 200 && Links::for_item($syllabus)['bank'] === [], 'Removing a link should be repeatable');

    // ---- Deletion safety ----
    $leaf = item('Temporary leaf', $cambridge);
    $deleted = api('DELETE', "curriculum/items/$leaf");
    ok($deleted->get_status() === 200 && $deleted->get_data()['deleted'] === [$leaf] && Items::get($leaf) === null, 'An unlinked leaf should delete directly');
    ok(api('DELETE', 'curriculum/items/999999')->get_status() === 404, 'Deleting a missing item should be 404');
    $frozen = snapshot();
    $refused = api('DELETE', "curriculum/items/$maths");
    ok($refused->get_status() === 409 && code($refused) === 'ohmylms_curriculum_has_children' && snapshot() === $frozen, 'An item with children was deleted without a choice');
    $refused = api('DELETE', "curriculum/items/$maths", ['children' => 'promote']);
    ok($refused->get_status() === 409 && code($refused) === 'ohmylms_curriculum_needs_confirmation' && snapshot() === $frozen, 'Deletion went ahead without confirmation');
    $linked_leaf = item('Linked leaf', $cambridge);
    api('POST', "curriculum/items/$linked_leaf/links", ['object_type' => 'course', 'object_id' => $other_english]);
    $refused = api('DELETE', "curriculum/items/$linked_leaf");
    ok($refused->get_status() === 409 && code($refused) === 'ohmylms_curriculum_needs_confirmation' && $refused->get_data()['data']['dependents']['own_links'] === 1, 'Deleting a linked item needs confirmation and must report what is affected');
    $deleted = api('DELETE', "curriculum/items/$linked_leaf", ['confirm' => true]);
    ok($deleted->get_status() === 200 && $deleted->get_data()['links_removed'] === 1 && get_post_status($other_english) === 'publish' && Links::memberships('course', $other_english) === [], 'Deleting an item must remove only the link, never the linked course');
    // Promote keeps children in order at the parent's position.
    $holder = item('Holder', $cambridge); $child_one = item('Child one', $holder); $child_two = item('Child two', $holder);
    $before_order = children_of($cambridge);
    $position = array_search('Holder', $before_order, true);
    $promoted = api('DELETE', "curriculum/items/$holder", ['children' => 'promote', 'confirm' => true]);
    $expected = $before_order; array_splice($expected, $position, 1, ['Child one', 'Child two']);
    ok($promoted->get_status() === 200 && $promoted->get_data()['promoted'] === 2 && children_of($cambridge) === $expected && dense($cambridge) && Items::get($holder) === null, 'Promoting children kept the wrong order: ' . wp_json_encode(children_of($cambridge)));
    // A deleted branch removes its links and bundle memberships, nothing else.
    $branch = item('Branch', $cambridge); $branch_child = item('Branch child', $branch);
    api('POST', "curriculum/items/$branch_child/links", ['object_type' => 'course', 'object_id' => $other_english]);
    $branch_bundle = api('POST', 'bundles', ['title' => 'Branch bundle ' . $tag])->get_data()['bundle']['id']; $bundle_ids[] = (int) $branch_bundle;
    api('PUT', "bundles/$branch_bundle/items", ['items' => [['type' => 'curriculum', 'id' => $branch_child], ['type' => 'course', 'id' => $other_english]]]);
    $removed = api('DELETE', "curriculum/items/$branch", ['children' => 'delete', 'confirm' => true]);
    ok($removed->get_status() === 200 && count($removed->get_data()['deleted']) === 2 && $removed->get_data()['links_removed'] === 1 && $removed->get_data()['bundles_updated'] === 1, 'Branch delete summary wrong: ' . wp_json_encode($removed->get_data()));
    $left = Bundles::members((int) $branch_bundle);
    ok(Items::get($branch) === null && Items::get($branch_child) === null && count($left) === 1 && $left[0]['type'] === 'course' && $left[0]['id'] === $other_english, 'A deleted branch left references behind');
    api('DELETE', "bundles/$branch_bundle", ['force' => true]);

    // ---- Authorization: guests, learners and even authors cannot administer ----
    $student = user(); $student_b = user(); $editor = user('editor');
    $probe_bundle = (int) api('POST', 'bundles', ['title' => 'Probe bundle ' . $tag])->get_data()['bundle']['id']; $bundle_ids[] = $probe_bundle;
    $routes = [
        ['GET', 'curriculum/tree'], ['POST', 'curriculum/items', ['name' => 'Intruder']], ['GET', "curriculum/items/$maths"], ['PUT', "curriculum/items/$maths", ['name' => 'Hacked']],
        ['DELETE', "curriculum/items/$maths", ['children' => 'delete', 'confirm' => true]], ['POST', "curriculum/items/$maths/move", ['parent_id' => 0]],
        ['POST', "curriculum/items/$maths/links", ['object_type' => 'course', 'object_id' => $other_english]], ['DELETE', "curriculum/items/$syllabus/links", ['object_type' => 'course', 'object_id' => $igcse_english]],
        ['GET', 'curriculum/link-targets', [], ['type' => 'course']], ['GET', 'skill-mappings'], ['PUT', 'skill-mappings', ['specific_id' => $skill_a, 'shared_id' => $skill_b]], ['DELETE', 'skill-mappings', ['specific_id' => $skill_a, 'shared_id' => $skill_b]],
        ['GET', 'bundles'], ['POST', 'bundles', ['title' => 'Intruder bundle']], ['GET', "bundles/$probe_bundle"], ['PUT', "bundles/$probe_bundle", ['title' => 'Hacked']],
        ['PUT', "bundles/$probe_bundle/items", ['items' => []]], ['POST', "bundles/$probe_bundle/publish"], ['DELETE', "bundles/$probe_bundle", ['force' => true]], ['GET', 'bundles/targets', [], ['type' => 'course']],
    ];
    $totals = [count_rows(Items::table()), count_rows(Bundles::table()), count_rows(Links::table()), count_rows(SkillMappings::table())];
    foreach ([[0, 401], [$student, 403], [$editor, 403]] as [$who, $expected_status]) {
        wp_set_current_user($who);
        foreach ($routes as $route) {
            $response = api($route[0], $route[1], $route[2] ?? [], $route[3] ?? []);
            ok($response->get_status() === $expected_status, "{$route[0]} {$route[1]} gave {$response->get_status()} instead of $expected_status for user $who");
        }
    }
    wp_set_current_user($admin);
    ok($totals === [count_rows(Items::table()), count_rows(Bundles::table()), count_rows(Links::table()), count_rows(SkillMappings::table())] && Items::get($maths)['name'] === 'Mathematics', 'A denied request still changed data');
    wp_set_current_user(0);
    ok(api('GET', 'me/bundles')->get_status() === 401 && api('POST', "bundles/$probe_bundle/follow")->get_status() === 401 && api('DELETE', "bundles/$probe_bundle/follow")->get_status() === 401 && api('GET', "me/bundles/$probe_bundle")->get_status() === 401, 'Guests reached learner routes');
    wp_set_current_user($admin);

    // ---- Bundles: named groupings across curricula, published explicitly ----
    $bundle = api('POST', 'bundles', ['title' => 'English Exam Preparation ' . $tag, 'description' => 'Selected English and test-prep courses']);
    ok($bundle->get_status() === 201 && $bundle->get_data()['bundle']['status'] === 'draft', 'Bundles should start as drafts');
    $english = (int) $bundle->get_data()['bundle']['id']; $bundle_ids[] = $english;
    foreach ([['title' => ''], ['title' => str_repeat('t', 191)], ['title' => 'ok', 'description' => str_repeat('d', 2001)]] as $bad) { ok(api('POST', 'bundles', $bad)->get_status() === 400, 'Invalid bundle accepted'); }
    $empty_publish = api('POST', "bundles/$english/publish");
    ok($empty_publish->get_status() === 409 && code($empty_publish) === 'ohmylms_bundle_empty' && Bundles::get($english)['status'] === 'draft', 'An empty bundle was published');
    $selected = [['type' => 'course', 'id' => $igcse_english], ['type' => 'course', 'id' => $sat_prep], ['type' => 'curriculum', 'id' => $syllabus]];
    $set = api('PUT', "bundles/$english/items", ['items' => $selected]);
    ok($set->get_status() === 200 && array_map(static function ($m) { return $m['type'] . ':' . $m['id']; }, $set->get_data()['members']) === ['course:' . $igcse_english, 'course:' . $sat_prep, 'curriculum:' . $syllabus], 'Members not stored in the chosen order');
    ok(!in_array($other_english, array_column(Bundles::members($english), 'id'), true) || count(Bundles::members($english)) === 3, 'A bundle must contain only what was chosen');
    ok(count(array_filter(Bundles::members($english), static function ($m) use ($other_english) { return $m['type'] === 'course' && $m['id'] === $other_english; })) === 0, 'Another English course was added automatically');
    foreach ([[['type' => 'course', 'id' => $igcse_english], ['type' => 'course', 'id' => $igcse_english]], [['type' => 'course', 'id' => 999999]], [['type' => 'curriculum', 'id' => 999999]], [['type' => 'course', 'id' => $exam]], [['type' => 'lesson', 'id' => 1]]] as $bad) {
        ok(api('PUT', "bundles/$english/items", ['items' => $bad])->get_status() === 400 && count(Bundles::members($english)) === 3, 'Invalid members accepted: ' . wp_json_encode($bad));
    }
    $reordered = api('PUT', "bundles/$english/items", ['items' => array_reverse($selected)]);
    ok($reordered->get_data()['members'][0]['id'] === $syllabus, 'Reordering members failed');
    api('PUT', "bundles/$english/items", ['items' => $selected]);
    $current = Bundles::get($english);
    ok(api('PUT', "bundles/$english/items", ['items' => [], 'expected_updated_at' => '2000-01-01 00:00:00'])->get_status() === 409 && count(Bundles::members($english)) === 3, 'A stale member edit overwrote newer data');
    ok(api('PUT', "bundles/$english", ['title' => 'x', 'expected_updated_at' => '2000-01-01 00:00:00'])->get_status() === 409, 'A stale bundle edit was accepted');
    $edited = api('PUT', "bundles/$english", ['title' => 'English Exam Preparation ' . $tag, 'description' => 'Edited description', 'expected_updated_at' => $current['updated_at']]);
    ok($edited->get_status() === 200 && $edited->get_data()['bundle']['description'] === 'Edited description', 'Bundle edit failed');
    $second_bundle = (int) api('POST', 'bundles', ['title' => 'Second bundle ' . $tag])->get_data()['bundle']['id']; $bundle_ids[] = $second_bundle;
    ok(api('PUT', "bundles/$second_bundle/items", ['items' => [['type' => 'course', 'id' => $igcse_english], ['type' => 'curriculum', 'id' => $syllabus]]])->get_status() === 200 && Bundles::members($english) !== [] && count(Bundles::members($english)) === 3, 'A course or syllabus could not appear in more than one bundle');
    ok(get_post_status($igcse_english) === 'publish' && count(Links::for_item($syllabus)['course']) === 2, 'Bundle membership duplicated or changed content');
    ok(api('POST', "bundles/$english/publish")->get_data()['bundle']['status'] === 'published' && Bundles::get($english)['published_at'] !== null, 'Publishing failed');
    ok(api('PUT', "bundles/$english/items", ['items' => []])->get_status() === 409, 'A published bundle was emptied');
    $listed = array_column(api('GET', 'bundles')->get_data()['bundles'], null, 'id');
    ok($listed[$english]['member_count'] === 3 && $listed[$english]['status'] === 'published' && $listed[$english]['follower_count'] === 0 && $listed[$probe_bundle]['member_count'] === 0, 'Bundle list counts wrong: ' . wp_json_encode($listed[$english]));

    // ---- Learners: discover, follow, unfollow; separate from enrollment and access ----
    wp_set_current_user($student);
    $mine = api('GET', 'me/bundles')->get_data();
    $suggested_ids = array_column($mine['suggested'], 'id');
    ok(in_array($english, $suggested_ids, true) && !in_array($second_bundle, $suggested_ids, true) && $mine['followed'] === [], 'Learners should discover published bundles only');
    ok(api('POST', "bundles/$second_bundle/follow")->get_status() === 404 && !Follows::is_following($student, $second_bundle), 'A draft bundle could be followed');
    ok(api('POST', 'bundles/999999/follow')->get_status() === 404, 'A missing bundle could be followed');
    $enrollments_before = count_rows($wpdb->prefix . 'ohmylms_user_enrollment'); $progress_before = count_rows($wpdb->prefix . 'ohmylms_user_progress');
    $followed = api('POST', "bundles/$english/follow");
    ok($followed->get_status() === 200 && $followed->get_data()['followed'] === true && strpos($followed->get_data()['html'], 'English Exam Preparation') !== false, 'Following failed');
    api('POST', "bundles/$english/follow");
    ok((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Follows::table() . ' WHERE user_id=%d AND bundle_id=%d', $student, $english)) === 1, 'Following twice created two rows');
    ok(count_rows($wpdb->prefix . 'ohmylms_user_enrollment') === $enrollments_before && count_rows($wpdb->prefix . 'ohmylms_user_progress') === $progress_before, 'Following a bundle changed enrollments or progress');
    ok(\OhMyLMS\Learning\CourseProgram::enrollment($student, $igcse_english) === null && \OhMyLMS\Learning\CourseProgram::enrollment($student, $sat_prep) === null, 'Following a bundle enrolled the learner');
    ok(api('GET', "courses/$igcse_english/learning/progress")->get_status() === 403, 'Following a bundle granted course access');
    ok(array_column(api('GET', 'me/bundles')->get_data()['followed'], 'id') === [$english], 'Followed bundle missing from the dashboard list');
    $detail = api('GET', "me/bundles/$english");
    ok($detail->get_status() === 200 && member($detail->get_data(), 'course', $igcse_english)['enrolled'] === false, 'Learner bundle detail failed');
    ok(api('GET', "me/bundles/$second_bundle")->get_status() === 404, 'A draft bundle was readable by a learner');
    // A different learner is unaffected, and cannot change someone else's dashboard.
    wp_set_current_user($student_b);
    ok(api('GET', 'me/bundles')->get_data()['followed'] === [] && in_array($english, array_column(api('GET', 'me/bundles')->get_data()['suggested'], 'id'), true), 'Another learner saw someone else\'s follows');
    api('DELETE', "bundles/$english/follow");
    ok(Follows::is_following($student, $english), 'A learner removed another learner\'s bundle');
    wp_set_current_user($student);
    $unfollowed = api('DELETE', "bundles/$english/follow");
    ok($unfollowed->get_status() === 200 && $unfollowed->get_data()['followed'] === false && !Follows::is_following($student, $english) && api('DELETE', "bundles/$english/follow")->get_status() === 200, 'Removing from the dashboard failed or was not repeatable');
    ok(count_rows($wpdb->prefix . 'ohmylms_user_enrollment') === $enrollments_before, 'Unfollowing changed enrollments');
    api('POST', "bundles/$english/follow");
    // Unpublishing hides a bundle from followers; republishing restores it.
    wp_set_current_user($admin);
    api('POST', "bundles/$english/publish", ['published' => false]);
    ok(Follows::followed($student) === [] && Follows::is_following($student, $english), 'Unpublishing should hide a bundle but keep the follow');
    api('POST', "bundles/$english/publish", ['published' => true]);
    ok(array_map('intval', array_column(Follows::followed($student), 'id')) === [$english], 'Republishing should restore the follow');
    $needs_force = api('DELETE', "bundles/$english");
    ok($needs_force->get_status() === 409 && code($needs_force) === 'ohmylms_bundle_has_followers' && $needs_force->get_data()['data']['followers'] === 1 && Bundles::get($english) !== null, 'Deleting a followed bundle needs confirmation');
    Follows::unfollow($student, $english);

    // ---- Course progress: counts from the course's own rules, read-only ----
    $chapter = (int) ohmylms_get_course($igcse_english)->get_chapters()[0]['id']; $posts[] = $chapter;
    $lessons = [];
    for ($i = 0; $i < 3; $i++) {
        $lessons[] = created('lessons', ['name' => 'English lesson ' . $i . ' ' . $tag, 'description' => '<p>Text</p>', 'type' => 'text', 'status' => 'publish']);
        $wpdb->insert($wpdb->prefix . 'ohmylms_content_relationship', ['chapter_id' => $chapter, 'content_id' => end($lessons), 'content_type' => 'text', 'order_number' => $i]);
    }
    reset_course_counts();
    $eid = enroll($student, $igcse_english);
    $wpdb->insert($wpdb->prefix . 'ohmylms_user_progress', ['enrollment_id' => $eid, 'content_id' => $lessons[0], 'content_type' => 'text', 'status' => 'completed', 'start_date' => current_time('mysql')]);
    $wpdb->insert($wpdb->prefix . 'ohmylms_user_progress', ['enrollment_id' => $eid, 'content_id' => $lessons[1], 'content_type' => 'text', 'status' => 'completed', 'start_date' => current_time('mysql')]);
    $before_state = [count_rows($wpdb->prefix . 'ohmylms_user_progress'), $wpdb->get_var($wpdb->prepare("SELECT progress FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE id=%d", $eid))];
    $english_view = view($english, $student);
    $course_row = member($english_view, 'course', $igcse_english);
    ok($course_row['enrolled'] === true && $course_row['mode'] === 'traditional' && $course_row['progress'] === ['kind' => 'activities', 'met' => 2, 'total' => 3] && $course_row['completed'] === false, 'Traditional course counts wrong: ' . wp_json_encode($course_row));
    ok(!array_key_exists('percent', $course_row) && !array_key_exists('percentage', $course_row), 'No percentage should be invented');
    $not_enrolled = member($english_view, 'course', $sat_prep);
    ok($not_enrolled['enrolled'] === false && $not_enrolled['progress'] === null && $not_enrolled['completed'] === false, 'A course the learner is not enrolled in must show no progress');
    api('PUT', "bundles/$second_bundle/items", ['items' => [['type' => 'course', 'id' => $draft_course], ['type' => 'course', 'id' => $igcse_english]]]);
    wp_set_current_user($admin); api('POST', "bundles/$second_bundle/publish");
    $hidden = view($second_bundle, $student);
    ok(member($hidden, 'course', $draft_course) === null && member($hidden, 'course', $igcse_english) !== null, 'An unpublished course was shown to a learner');
    ok(array_filter(Bundles::describe_members($second_bundle), static function ($m) use ($draft_course) { return $m['id'] === $draft_course && $m['status'] === 'draft'; }) !== [], 'Administrators should still see unpublished members');
    // Managed course: dimensions from its learning program; viewing never awards completion.
    $managed_lesson = created('lessons', ['name' => 'Managed lesson ' . $tag, 'description' => '<p>x</p>', 'type' => 'text', 'status' => 'publish']);
    $managed = created('courses', ['name' => 'Managed course ' . $tag, 'status' => 'publish']);
    $published = api('POST', "courses/$managed/learning/publish", ['mode' => 'traditional', 'recognize_prior' => true, 'evidence_days' => 0, 'bank_ids' => [], 'outcomes' => [], 'items' => [['id' => wp_generate_uuid4(), 'type' => 'lesson', 'content_id' => $managed_lesson, 'chapter_id' => 0, 'required' => true, 'pass_percent' => 80]], 'apply_existing' => false]);
    ok($published->get_status() === 200, 'Learning program publish failed: ' . wp_json_encode($published->get_data()));
    $managed_eid = enroll($student, $managed);
    api('PUT', "bundles/$second_bundle/items", ['items' => [['type' => 'course', 'id' => $managed], ['type' => 'course', 'id' => $igcse_english]]]);
    $before_awards = count_rows(\OhMyLMS\Learning\Schema::table('awards'));
    $managed_view = member(view($second_bundle, $student), 'course', $managed);
    ok($managed_view['progress']['kind'] === 'program' && $managed_view['progress']['dimensions']['activities'] === ['met' => 0, 'total' => 1] && $managed_view['mode'] === 'traditional', 'Program dimensions wrong: ' . wp_json_encode($managed_view));
    $wpdb->insert($wpdb->prefix . 'ohmylms_user_progress', ['enrollment_id' => $managed_eid, 'content_id' => $managed_lesson, 'content_type' => 'text', 'status' => 'completed', 'start_date' => current_time('mysql')]);
    ok(CompletionPolicy::status($student, $managed)['eligible'] === true, 'Fixture should be eligible for completion');
    $managed_view = member(view($second_bundle, $student), 'course', $managed);
    ok($managed_view['progress']['dimensions']['activities'] === ['met' => 1, 'total' => 1], 'Program progress did not update');
    ok($wpdb->get_var($wpdb->prepare("SELECT progress FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE id=%d", $managed_eid)) === 'running' && count_rows(\OhMyLMS\Learning\Schema::table('awards')) === $before_awards && $managed_view['completed'] === false, 'Viewing a bundle changed course completion');
    $after_state = [count_rows($wpdb->prefix . 'ohmylms_user_progress') - 1, $wpdb->get_var($wpdb->prepare("SELECT progress FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE id=%d", $eid))];
    ok($after_state === $before_state, 'Building the dashboard changed progress records');

    // ---- Skills: strengths, gaps, not assessed, practice availability ----
    $strong = skill('Strong skill'); $weak = skill('Weak skill'); $partial = skill('Partial skill'); $untouched = skill('Untouched skill'); $nopool = skill('No practice skill');
    $base = time() - 6 * DAY_IN_SECONDS;
    foreach ([[1, true, 'f1'], [2, true, 'f2'], [3, true, 'f1'], [4, true, 'f2']] as $n => [$q, $right, $family]) { evidence($student, $strong, 9100000 + $n, $right, 7100 + $q, $family, $base + $n * 3600); }
    foreach ([[1, false], [2, false], [3, true]] as $n => [$q, $right]) { evidence($student, $weak, 9100100 + $n, $right, 7200 + $q, 'w' . $q, $base + $n * 3600); }
    evidence($student, $partial, 9100200, true, 7301, 'p1', $base);
    foreach ([$strong, $weak, $partial] as $term) { Mastery::recompute($student, $term); }
    $state_rows = array_column(states($student), 'level', 'term_id');
    ok($state_rows[$strong] === 'proficient' || $state_rows[$strong] === 'mastered', 'Fixture: strong skill should be proficient: ' . wp_json_encode($state_rows));
    ok($state_rows[$weak] === 'attempted' && $state_rows[$partial] === 'attempted' && !isset($state_rows[$untouched]), 'Fixture: other skills should be developing or unassessed');
    // Practice exists for the weak skill and the untouched skill (public catalog practice).
    foreach ([$weak, $untouched] as $term) {
        for ($i = 0; $i < 3; $i++) {
            $q = created('question', ['name' => 'Practice ' . $term . '-' . $i, 'settings' => ['type' => 'single-choice', 'hint' => 'h', 'score' => ['enabled' => true, 'value' => 1]], 'questions' => [['answer' => 'Right', 'is_correct' => 1], ['answer' => 'Wrong']], 'skills' => ['p1' => ['primary' => $term, 'supporting' => []]], 'bank' => ['family_id' => 'cf-' . $term . '-' . $i]]);
            ok(api('POST', 'questions/' . $q . '/approve')->get_status() === 200, 'Approve failed');
        }
        ok(api('PUT', 'skills/' . $term, ['public_practice' => true])->get_status() === 200, 'Public practice flag failed');
    }
    $skills_bundle = (int) api('POST', 'bundles', ['title' => 'Skills bundle ' . $tag])->get_data()['bundle']['id']; $bundle_ids[] = $skills_bundle;
    api('POST', "curriculum/items/$words/links", ['object_type' => 'skill', 'object_id' => $strong]);
    api('POST', "curriculum/items/$words/links", ['object_type' => 'skill', 'object_id' => $weak]);
    api('POST', "curriculum/items/$sat_math/links", ['object_type' => 'skill', 'object_id' => $untouched]);
    api('POST', "curriculum/items/$sat_math/links", ['object_type' => 'skill', 'object_id' => $partial]);
    api('POST', "curriculum/items/$sat_math/links", ['object_type' => 'skill', 'object_id' => $nopool]);
    api('POST', "curriculum/items/$sat/links", ['object_type' => 'skill', 'object_id' => $strong]);
    wp_set_current_user($admin);
    api('PUT', "bundles/$skills_bundle/items", ['items' => [['type' => 'curriculum', 'id' => $sat]]]);
    $skill_view = view($skills_bundle, $student);
    $by_term = []; foreach ($skill_view['skills'] as $entry) { $by_term[$entry['term_id']] = $entry; }
    ok(count($skill_view['skills']) === 5 && isset($by_term[$strong], $by_term[$weak], $by_term[$partial], $by_term[$untouched], $by_term[$nopool]), 'Skills of descendant items were not gathered once each: ' . count($skill_view['skills']));
    ok($by_term[$strong]['classification'] === 'strength' && $by_term[$strong]['practice_url'] === null, 'Strengths should not be queued for practice');
    ok($by_term[$weak]['classification'] === 'gap' && $by_term[$weak]['level_label'] === 'Attempted', 'A skill with repeated recent errors should be a gap');
    ok($by_term[$partial]['classification'] === 'developing', 'A skill with one answer is in progress, not a gap');
    ok($by_term[$untouched]['level'] === 'not-started' && $by_term[$untouched]['level_label'] === 'Not started' && $by_term[$untouched]['classification'] === 'not-assessed' && $by_term[$untouched]['evidence_count'] === 0, 'Unassessed skills must read "Not assessed"');
    ok($by_term[$nopool]['level'] === 'not-started' && $by_term[$nopool]['classification'] === 'not-assessed', 'A skill with no evidence should be not assessed');
    ok($skill_view['skill_summary'] === ['total' => 5, 'strengths' => 1, 'gaps' => 1, 'developing' => 1, 'not_assessed' => 2], 'Skill summary wrong: ' . wp_json_encode($skill_view['skill_summary']));
    ok(strpos((string) $by_term[$weak]['practice_url'], 'ohmylms_practice=' . $weak) !== false && strpos((string) $by_term[$untouched]['practice_url'], 'ohmylms_practice=' . $untouched) !== false, 'Practice links missing where questions exist');
    ok($by_term[$nopool]['practice_url'] === null && $by_term[$partial]['practice_url'] === null, 'A practice link was offered with no questions to practise');
    $next = array_column($skill_view['next_practice'], 'term_id');
    ok($next === [$weak, $untouched], 'Next practice should list the gap first, then unassessed skills with questions: ' . wp_json_encode($next));
    ok(count(states($student)) === 3 && !isset(array_column(states($student), null, 'term_id')[$untouched]) && !isset(array_column(states($student), null, 'term_id')[$nopool]), 'Viewing must not create skill states for unassessed skills');

    // ---- Skill mappings: explicit, directional, never by name ----
    $same_a = skill('Fractions'); $same_b = skill('Fractions');
    $shared = skill('Solving linear equations (shared)'); $other_shared = skill('Other shared skill'); $related = skill('Related skill');
    // Skills are core, so mapping needs no add-on switch; it stays an administrator-only action.
    $mapped = api('PUT', 'skill-mappings', ['specific_id' => $skill_a, 'shared_id' => $shared, 'relation' => 'equivalent', 'note' => 'Syllabus A is non-calculator.']);
    ok($mapped->get_status() === 200 && $mapped->get_data()['mapping']['note'] === 'Syllabus A is non-calculator.', 'Mapping failed: ' . wp_json_encode($mapped->get_data()));
    ok(api('PUT', 'skill-mappings', ['specific_id' => $skill_b, 'shared_id' => $shared, 'relation' => 'equivalent', 'note' => 'Syllabus B allows a calculator.'])->get_status() === 200, 'Second mapping failed');
    ok(api('PUT', 'skill-mappings', ['specific_id' => $related, 'shared_id' => $shared, 'relation' => 'related'])->get_status() === 200, 'Related mapping failed');
    ok(count(SkillMappings::all()) === $start['mappings'] + 3 && SkillMappings::for_skill($shared)['mapped_from'][0]['specific']['id'] === $skill_a, 'Mappings not recorded');
    $update = api('PUT', 'skill-mappings', ['specific_id' => $skill_a, 'shared_id' => $shared, 'relation' => 'equivalent', 'note' => 'Updated note.']);
    ok($update->get_status() === 200 && count(SkillMappings::all()) === $start['mappings'] + 3 && $update->get_data()['mapping']['note'] === 'Updated note.', 'Updating a mapping created a duplicate');
    foreach ([['specific_id' => $skill_a, 'shared_id' => $skill_a], ['specific_id' => $skill_a, 'shared_id' => 999999], ['specific_id' => 999999, 'shared_id' => $shared], ['specific_id' => $skill_a, 'shared_id' => $other_shared, 'relation' => 'sameish'], ['specific_id' => $skill_a, 'shared_id' => $other_shared, 'note' => str_repeat('n', 1001)]] as $bad) {
        ok(in_array(api('PUT', 'skill-mappings', $bad)->get_status(), [400, 404], true), 'Invalid mapping accepted: ' . wp_json_encode($bad));
    }
    $chain = api('PUT', 'skill-mappings', ['specific_id' => $shared, 'shared_id' => $other_shared]);
    ok($chain->get_status() === 409 && code($chain) === 'ohmylms_mapping_chain', 'A shared skill was mapped onward, allowing chained pooling');
    $chain = api('PUT', 'skill-mappings', ['specific_id' => $other_shared, 'shared_id' => $skill_a]);
    ok($chain->get_status() === 409 && code($chain) === 'ohmylms_mapping_chain', 'A mapped skill was used as a shared target');
    ok(count(SkillMappings::all([$same_a, $same_b])) === 0, 'Skills with matching names must not be mapped automatically');

    // ---- Combined view: pooled once, related skills excluded, shared evidence never completes another course ----
    $combined_bundle = (int) api('POST', 'bundles', ['title' => 'Combined bundle ' . $tag])->get_data()['bundle']['id']; $bundle_ids[] = $combined_bundle;
    $item_a = item('Syllabus A item', $cambridge, 'syllabus', ['code' => 'A-1', 'version' => '2024']);
    $item_b = item('Syllabus B item', $sat, 'syllabus', ['code' => 'B-1', 'version' => '2026']);
    api('POST', "curriculum/items/$item_a/links", ['object_type' => 'skill', 'object_id' => $skill_a]);
    api('POST', "curriculum/items/$item_b/links", ['object_type' => 'skill', 'object_id' => $skill_b]);
    api('PUT', "bundles/$combined_bundle/items", ['items' => [['type' => 'curriculum', 'id' => $item_a], ['type' => 'curriculum', 'id' => $item_b]]]);
    $t = time() - 5 * DAY_IN_SECONDS;
    // Phase 1. Syllabus A has two correct answers. The same graded part (event 9200001) is also
    // recorded under B. Counted once the pool holds 2 answers (developing); double counted it
    // would hold 3 and wrongly read as proficient. A related skill has one answer of its own.
    evidence($student, $skill_a, 9200001, true, 8001, 'a1', $t);
    evidence($student, $skill_a, 9200002, true, 8002, 'a2', $t + 60);
    evidence($student, $skill_b, 9200001, true, 8001, 'a1', $t);
    evidence($student, $related, 9200005, true, 8005, 'r1', $t + 240);
    foreach ([$skill_a, $skill_b, $related] as $term) { Mastery::recompute($student, $term); }
    $states_before = states($student);
    $group = view($combined_bundle, $student)['combined'];
    ok(count($group) === 1 && $group[0]['shared']['term_id'] === $shared, 'The shared skill did not appear in the combined view');
    $group = $group[0];
    ok($group['pooled_sources'] === 2, 'Only equivalent mappings should pool: ' . $group['pooled_sources']);
    ok($group['evidence_count'] === 2 && $group['level'] === 'familiar', 'The same graded answer was counted twice, or a related skill was pooled: ' . wp_json_encode([$group['evidence_count'], $group['level']]));
    // Phase 2. A genuine extra answer under B lifts the pool to three distinct answers.
    evidence($student, $skill_b, 9200003, true, 8003, 'b1', $t + 120); Mastery::recompute($student, $skill_b);
    $group = view($combined_bundle, $student)['combined'][0];
    ok($group['evidence_count'] === 3 && in_array($group['level'], ['proficient', 'mastered'], true), 'Pooled level should reflect three distinct correct answers: ' . wp_json_encode([$group['evidence_count'], $group['level']]));
    $own = array_column(states($student), null, 'term_id');
    ok($own[$skill_a]['level'] === 'familiar' && $own[$skill_b]['level'] === 'familiar' && $own[$related]['level'] === 'attempted', 'Pooling must not change any skill\'s own level: ' . wp_json_encode($own));
    $source_levels = array_column($group['sources'], 'level', 'term_id');
    ok($source_levels[$skill_a] === 'familiar' && $source_levels[$skill_b] === 'familiar' && $source_levels[$related] === 'attempted', 'Each source skill must keep its own level in the combined view');
    $by_source = array_column($group['sources'], null, 'term_id');
    ok($by_source[$skill_a]['note'] === 'Updated note.' && $by_source[$skill_b]['note'] === 'Syllabus B allows a calculator.' && $by_source[$skill_a]['relation'] === 'equivalent' && $by_source[$related]['relation'] === 'related', 'Differences and relations were not preserved');
    $context_a = array_column($by_source[$skill_a]['context'], 'version', 'code');
    $context_b = array_column($by_source[$skill_b]['context'], 'version', 'code');
    ok(($context_a['A-1'] ?? '') === '2024' && ($context_a['TEST-0001'] ?? '') === '2028' && ($context_b['B-1'] ?? '') === '2026', 'Syllabus code and version context missing: ' . wp_json_encode([$context_a, $context_b]));
    ok($by_source[$skill_a]['context'][0]['path'] !== [] || $by_source[$skill_a]['context'][1]['path'] !== [], 'Curriculum path context missing');
    ok($by_source[$related]['evidence_count'] === 1 && $group['evidence_count'] === 3, 'A related mapping must not add evidence');
    $states_after_view = states($student);
    view($combined_bundle, $student); view($combined_bundle, $student);
    ok(states($student) === $states_after_view, 'Building a combined view changed stored skill states');
    // Without any evidence, the shared skill reads Not assessed.
    $fresh = user(); enroll($fresh, $igcse_english);
    $fresh_group = view($combined_bundle, $fresh)['combined'][0];
    ok($fresh_group['level'] === 'not-started' && $fresh_group['level_label'] === 'Not started' && $fresh_group['evidence_count'] === 0, 'An unassessed shared skill must read Not assessed');
    // Matching names alone never pool evidence.
    evidence($student, $same_a, 9200010, true, 8010, 'n1', $t); Mastery::recompute($student, $same_a);
    ok(!isset(array_column(states($student), null, 'term_id')[$same_b]), 'Evidence on one skill leaked to a skill with the same name');
    $states_before_z = states($student);
    // Shared evidence must not complete a course that counts only its own evidence (recognize_prior off): course Z requires skill B, not the shared one. Programs that recognise prior learning get credit back (tests/php/learning-integration.php).
    $z_lesson = created('lessons', ['name' => 'Z lesson ' . $tag, 'description' => '<p>x</p>', 'type' => 'text', 'status' => 'publish']);
    for ($i = 0; $i < 3; $i++) {
        $q = created('question', ['name' => 'Z question ' . $i, 'settings' => ['type' => 'single-choice', 'hint' => 'h', 'score' => ['enabled' => true, 'value' => 1]], 'questions' => [['answer' => 'Right', 'is_correct' => 1], ['answer' => 'Wrong']], 'skills' => ['p1' => ['primary' => $skill_b, 'supporting' => []]], 'bank' => ['family_id' => 'z-family-' . $i]]);
        api('POST', 'questions/' . $q . '/approve');
    }
    $course_z = created('courses', ['name' => 'Course Z ' . $tag, 'status' => 'publish']);
    $z_published = api('POST', "courses/$course_z/learning/publish", ['mode' => 'skill-based', 'recognize_prior' => false, 'evidence_days' => 0, 'bank_ids' => [], 'outcomes' => [['term_id' => $skill_b, 'target' => 'proficient', 'required' => true]], 'items' => [['id' => wp_generate_uuid4(), 'type' => 'lesson', 'content_id' => $z_lesson, 'chapter_id' => 0, 'required' => false, 'pass_percent' => 80]], 'apply_existing' => false]);
    ok($z_published->get_status() === 200, 'Course Z publish failed: ' . wp_json_encode($z_published->get_data()));
    $z_eid = enroll($student, $course_z);
    $z_status = CompletionPolicy::status($student, $course_z);
    ok($z_status['dimensions']['outcomes'] === ['met' => 0, 'total' => 1] && !$z_status['eligible'], 'Course Z should be incomplete: its own skill is only developing');
    api('PUT', "bundles/$combined_bundle/items", ['items' => [['type' => 'curriculum', 'id' => $item_a], ['type' => 'curriculum', 'id' => $item_b], ['type' => 'course', 'id' => $course_z]]]);
    $z_view = member(view($combined_bundle, $student), 'course', $course_z);
    ok($z_view['completed'] === false && $z_view['progress']['dimensions']['outcomes'] === ['met' => 0, 'total' => 1], 'Shared skill evidence marked another course as progressing');
    ok(CompletionPolicy::award($student, $course_z)['completed'] === false && $wpdb->get_var($wpdb->prepare("SELECT progress FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE id=%d", $z_eid)) === 'running', 'Shared skill evidence completed another course');
    ok(states($student) === $states_before_z, 'Evaluating another course changed stored skill states');

    // ---- Rendering: escaped output and clear separation ----
    $wpdb->update($wpdb->terms, ['name' => '<img src=x onerror=alert(1)>'], ['term_id' => $skill_a]);
    $wpdb->update(Bundles::table(), ['title' => 'A & B "quoted" <i>raw</i> ' . $tag], ['id' => $combined_bundle]);
    clean_term_cache($skill_a, Taxonomy::NAME);
    Follows::follow($student, $english); Follows::follow($student, $skills_bundle);
    wp_set_current_user($admin); api('POST', "bundles/$combined_bundle/publish", ['published' => true]); api('POST', "bundles/$skills_bundle/publish", ['published' => true]);
    Follows::follow($student, $combined_bundle); Follows::follow($student, $skills_bundle);
    wp_set_current_user($student);
    $html = Frontend::section($student);
    ok(strpos($html, '<img src=x') === false && strpos($html, '&lt;img src=x') !== false, 'A skill name was not escaped');
    ok(strpos($html, 'A &amp; B &quot;quoted&quot; &lt;i&gt;raw&lt;/i&gt;') !== false && strpos($html, '<i>raw</i>') === false, 'A bundle title was not escaped');
    $missing = array_values(array_filter(['Not assessed', 'Not enrolled', 'Remove from dashboard', 'aria-label="Remove ', 'role="status"', '<progress', 'Needs practice', 'Strength', 'Add to my dashboard', 'Combined skills', 'Counts toward shared level', 'Related only', 'Practice'], static function ($marker) use ($html) { return strpos($html, $marker) === false; }));
    ok(!$missing, 'Dashboard markup missing labelled controls or states: ' . wp_json_encode($missing));
    ok(strpos($html, 'does not enrol you') !== false && strpos($html, 'never completes a course') !== false, 'Following must be explained as separate from enrollment');
    ok(strpos($html, 'Course completed') === false, 'No course should show as completed here');
    wp_set_current_user(0);
    ok(Frontend::section(0) === '' && Frontend::shortcode() === '', 'Guests should see no dashboard section');
    wp_set_current_user($admin);

    // ---- Cleanup hooks: deleted content leaves no dangling references ----
    $doomed_course = created('courses', ['name' => 'Doomed course ' . $tag, 'status' => 'publish']);
    $doomed_skill = skill('Doomed skill');
    api('POST', "curriculum/items/$words/links", ['object_type' => 'course', 'object_id' => $doomed_course]);
    api('POST', "curriculum/items/$words/links", ['object_type' => 'skill', 'object_id' => $doomed_skill]);
    api('PUT', "bundles/$second_bundle/items", ['items' => [['type' => 'course', 'id' => $doomed_course], ['type' => 'course', 'id' => $igcse_english]]]);
    SkillMappings::save($doomed_skill, $other_shared, 'equivalent', '');
    $doomed_exam = created('quiz', ['name' => 'Doomed exam ' . $tag, 'status' => 'publish']);
    api('POST', "curriculum/items/$words/links", ['object_type' => 'quiz', 'object_id' => $doomed_exam]);
    ok(count(Links::memberships('quiz', $doomed_exam)) === 1, 'Fixture: exam link missing');
    wp_delete_post($doomed_course, true);
    wp_delete_post($doomed_exam, true);
    wp_delete_term($doomed_skill, Taxonomy::NAME);
    ok(Links::memberships('course', $doomed_course) === [] && Links::memberships('quiz', $doomed_exam) === [] && Links::memberships('skill', $doomed_skill) === [] && SkillMappings::for_skill($doomed_skill)['maps_to'] === [], 'Deleted content left curriculum links or mappings');
    ok(array_column(Bundles::members($second_bundle), 'id') === [$igcse_english], 'A deleted course stayed in a bundle');
    $leaver = user(); Follows::follow($leaver, $english);
    wp_delete_user($leaver);
    ok((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Follows::table() . ' WHERE user_id=%d', $leaver)) === 0, 'A deleted learner left follows behind');

    // ---- Migration: repeatable, additive, existing records untouched ----
    $before = ['items' => count_rows(Items::table()), 'bundles' => count_rows(Bundles::table()), 'follows' => count_rows(Follows::table()), 'links' => count_rows(Links::table()),
        'enrollments' => count_rows($wpdb->prefix . 'ohmylms_user_enrollment'), 'progress' => count_rows($wpdb->prefix . 'ohmylms_user_progress'), 'programs' => count_rows(\OhMyLMS\Learning\Schema::table('programs')),
        'states' => count_rows(AssessmentSchema::table('student_skill_state')), 'evidence' => count_rows(AssessmentSchema::table('skill_evidence')), 'courses' => (int) wp_count_posts(OHMYLMS_COURSE_CPT)->publish];
    delete_option(Schema::OPTION); ok(!Schema::ready(), 'Option reset failed');
    ok(Schema::install() === true && Schema::ready(), 'Reinstall failed');
    Schema::install();
    $after = ['items' => count_rows(Items::table()), 'bundles' => count_rows(Bundles::table()), 'follows' => count_rows(Follows::table()), 'links' => count_rows(Links::table()),
        'enrollments' => count_rows($wpdb->prefix . 'ohmylms_user_enrollment'), 'progress' => count_rows($wpdb->prefix . 'ohmylms_user_progress'), 'programs' => count_rows(\OhMyLMS\Learning\Schema::table('programs')),
        'states' => count_rows(AssessmentSchema::table('student_skill_state')), 'evidence' => count_rows(AssessmentSchema::table('skill_evidence')), 'courses' => (int) wp_count_posts(OHMYLMS_COURSE_CPT)->publish];
    ok($before === $after, 'Repeating the migration changed existing records: ' . wp_json_encode([$before, $after]));
    ok(CompletionPolicy::status($student, $managed)['dimensions']['activities'] === ['met' => 1, 'total' => 1] && \OhMyLMS\Learning\CourseProgram::current($managed)['mode'] === 'traditional' && \OhMyLMS\Learning\CourseProgram::current($course_z)['mode'] === 'skill-based', 'Existing courses, modes or progress changed');
} finally {
    wp_set_current_user($admin);
    foreach ($bundle_ids as $id) { if (Bundles::get($id)) { Bundles::delete($id, true); } }
    // A syllabus is also a course: remove the courses (and chapters) that syllabus items were given.
    foreach ($item_ids as $id) {
        $made = Items::get($id)['course_id'] ?? 0;
        if ($made && get_post_type((int) $made) === OHMYLMS_COURSE_CPT) {
            foreach (\OhMyLMS\Learning\Catalog::chapters((int) $made) as $chapter) { wp_delete_post($chapter['id'], true); }
            wp_delete_post((int) $made, true);
        }
    }
    foreach (array_reverse($item_ids) as $id) { if (Items::get($id)) { Items::delete($id, 'delete', true); } }
    foreach (SkillMappings::all() as $mapping) { if (in_array((int) $mapping['specific_term_id'], $terms, true) || in_array((int) $mapping['shared_term_id'], $terms, true)) { SkillMappings::remove((int) $mapping['specific_term_id'], (int) $mapping['shared_term_id']); } }
    foreach ($users as $user_id) {
        $wpdb->delete(AssessmentSchema::table('skill_evidence'), ['student_id' => $user_id]); $wpdb->delete(AssessmentSchema::table('student_skill_state'), ['student_id' => $user_id]);
        foreach ($wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE user_id=%d", $user_id)) as $enrollment_id) {
            $wpdb->delete($wpdb->prefix . 'ohmylms_user_progress', ['enrollment_id' => $enrollment_id]);
            $wpdb->delete(\OhMyLMS\Learning\Schema::table('enrollments'), ['enrollment_id' => $enrollment_id]); $wpdb->delete(\OhMyLMS\Learning\Schema::table('awards'), ['enrollment_id' => $enrollment_id]);
        }
        $wpdb->delete($wpdb->prefix . 'ohmylms_user_enrollment', ['user_id' => $user_id]); $wpdb->delete(Follows::table(), ['user_id' => $user_id]);
        wp_delete_user($user_id);
    }
    foreach ($posts as $post_id) { wp_delete_post($post_id, true); }
    foreach ($terms as $term_id) { wp_delete_term($term_id, Taxonomy::NAME); }
    foreach ($banks as $bank_id) { $wpdb->delete(AssessmentSchema::table('qb_banks'), ['id' => $bank_id]); }
    update_option('ohmylms_integrations', $previous_integrations);
    if (isset($start)) {
        $end = ['items' => count_rows(Items::table()), 'bundles' => count_rows(Bundles::table()), 'links' => count_rows(Links::table()), 'follows' => count_rows(Follows::table()), 'mappings' => count_rows(SkillMappings::table()), 'members' => count_rows(Bundles::members_table())];
        if ($end !== $start) { fwrite(STDERR, 'Cleanup left rows behind: ' . wp_json_encode([$start, $end]) . "\n"); }
    }
}
echo "$checks curriculum integration checks passed.\n";
