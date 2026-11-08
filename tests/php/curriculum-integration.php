<?php
/**
 * Curriculum structure, links, deletion, skill mapping and the cleanup of deleted content.
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

$checks = 0; $posts = []; $users = []; $terms = []; $banks = []; $item_ids = [];
$previous_integrations = get_option('ohmylms_integrations', []);
$admin = get_user_by('login', $config['username'])->ID;
$tag = wp_generate_password(5, false, false);
function ok($condition, $message) { global $checks; if (!$condition) { throw new RuntimeException($message); } $checks++; }
function api($method, $path, $body = [], $query = [], $revision = null) {
    $request = new WP_REST_Request($method, '/ohmylms/v1/' . $path);
    if ($query) { $request->set_query_params($query); }
    if ($body) { $request->set_header('Content-Type', 'application/json'); $request->set_body(wp_json_encode($body)); }
    if (null !== $revision) { $request->set_header('X-OhMyLMS-Structure-Revision', $revision); }
    return rest_do_request($request);
}
/** The course structure read token that learning program writes must send. */
function structure_revision($course) { return (string) api('GET', "courses/$course/learning")->get_data()['structure_revision']; }
function code($response) { return $response->get_data()['code'] ?? ''; }
function created($path, $body) {
    global $posts;
    $response = api('POST', $path, $body);
    ok($response->get_status() === 201, 'Create failed: ' . wp_json_encode($response->get_data()));
    $id = (int) $response->get_data()['id']; $posts[] = $id; return $id;
}
function user($role = 'subscriber') {
    global $users;
    $id = wp_create_user('curr-' . wp_generate_password(9, false, false), wp_generate_password(24), 'curr-' . wp_generate_password(9, false, false) . '@example.invalid');
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
    return $wpdb->get_results($wpdb->prepare('SELECT term_id, level, review_due, evidence_count, independent_correct, families, mastery FROM ' . AssessmentSchema::table('student_skill_state') . ' WHERE student_id=%d ORDER BY term_id', $student), ARRAY_A);
}
/** The course store memoizes content counts per request; a single-process test must reset them after changing content. */
function reset_course_counts() {
    $class = new ReflectionClass(\OhMyLMS\DataStores\CourseStore::class);
    foreach (['lessons_count_cache', 'quiz_count_cache', 'assignment_count_cache', 'all_content_count_cache'] as $name) { $property = $class->getProperty($name); $property->setAccessible(true); $property->setValue([]); }
}

try {
    wp_set_current_user($admin);
    Schema::install(); ok(Schema::ready(), 'Curriculum tables unavailable');
    AssessmentSchema::install(); LearningSchema::install();
    global $wpdb;
    foreach (array_keys(Schema::definitions()) as $name) { ok($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', Schema::table($name))) === Schema::table($name), "Table $name missing"); }
    $start = ['items' => count_rows(Items::table()), 'links' => count_rows(Links::table()), 'mappings' => count_rows(SkillMappings::table())];

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
    $igcse_english = created('courses', ['title' => 'IGCSE English prep ' . $tag, 'status' => 'publish']);
    $sat_prep = created('courses', ['title' => 'Digital SAT prep ' . $tag, 'status' => 'publish']);
    $other_english = created('courses', ['title' => 'Other English course ' . $tag, 'status' => 'publish']);
    $draft_course = created('courses', ['title' => 'Draft course ' . $tag, 'status' => 'draft']);
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
    // A deleted branch removes its links, nothing else.
    $branch = item('Branch', $cambridge); $branch_child = item('Branch child', $branch);
    api('POST', "curriculum/items/$branch_child/links", ['object_type' => 'course', 'object_id' => $other_english]);
    $removed = api('DELETE', "curriculum/items/$branch", ['children' => 'delete', 'confirm' => true]);
    ok($removed->get_status() === 200 && count($removed->get_data()['deleted']) === 2 && $removed->get_data()['links_removed'] === 1, 'Branch delete summary wrong: ' . wp_json_encode($removed->get_data()));
    ok(Items::get($branch) === null && Items::get($branch_child) === null && Links::memberships('course', $other_english) === [], 'A deleted branch left references behind');

    // ---- Authorization: guests, learners and even authors cannot administer ----
    $student = user(); $student_b = user(); $editor = user('editor');
    $routes = [
        ['GET', 'curriculum/tree'], ['POST', 'curriculum/items', ['name' => 'Intruder']], ['GET', "curriculum/items/$maths"], ['PUT', "curriculum/items/$maths", ['name' => 'Hacked']],
        ['DELETE', "curriculum/items/$maths", ['children' => 'delete', 'confirm' => true]], ['POST', "curriculum/items/$maths/move", ['parent_id' => 0]],
        ['POST', "curriculum/items/$maths/links", ['object_type' => 'course', 'object_id' => $other_english]], ['DELETE', "curriculum/items/$syllabus/links", ['object_type' => 'course', 'object_id' => $igcse_english]],
        ['GET', 'curriculum/link-targets', [], ['type' => 'course']], ['GET', 'skill-mappings'], ['PUT', 'skill-mappings', ['specific_id' => $skill_a, 'shared_id' => $skill_b]], ['DELETE', 'skill-mappings', ['specific_id' => $skill_a, 'shared_id' => $skill_b]],
    ];
    $totals = [count_rows(Items::table()), count_rows(Links::table()), count_rows(SkillMappings::table())];
    foreach ([[0, 401], [$student, 403], [$editor, 403]] as [$who, $expected_status]) {
        wp_set_current_user($who);
        foreach ($routes as $route) {
            $response = api($route[0], $route[1], $route[2] ?? [], $route[3] ?? []);
            ok($response->get_status() === $expected_status, "{$route[0]} {$route[1]} gave {$response->get_status()} instead of $expected_status for user $who");
        }
    }
    wp_set_current_user($admin);
    ok($totals === [count_rows(Items::table()), count_rows(Links::table()), count_rows(SkillMappings::table())] && Items::get($maths)['name'] === 'Mathematics', 'A denied request still changed data');
    wp_set_current_user(0);
    wp_set_current_user($admin);

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

    // ---- Cleanup hooks: deleted content leaves no dangling references ----
    $doomed_course = created('courses', ['title' => 'Doomed course ' . $tag, 'status' => 'publish']);
    $doomed_skill = skill('Doomed skill');
    api('POST', "curriculum/items/$words/links", ['object_type' => 'course', 'object_id' => $doomed_course]);
    api('POST', "curriculum/items/$words/links", ['object_type' => 'skill', 'object_id' => $doomed_skill]);
    SkillMappings::save($doomed_skill, $other_shared, 'equivalent', '');
    $doomed_exam = created('quiz', ['name' => 'Doomed exam ' . $tag, 'status' => 'publish']);
    api('POST', "curriculum/items/$words/links", ['object_type' => 'quiz', 'object_id' => $doomed_exam]);
    ok(count(Links::memberships('quiz', $doomed_exam)) === 1, 'Fixture: exam link missing');
    wp_delete_post($doomed_course, true);
    wp_delete_post($doomed_exam, true);
    wp_delete_term($doomed_skill, Taxonomy::NAME);
    ok(Links::memberships('course', $doomed_course) === [] && Links::memberships('quiz', $doomed_exam) === [] && Links::memberships('skill', $doomed_skill) === [] && SkillMappings::for_skill($doomed_skill)['maps_to'] === [], 'Deleted content left curriculum links or mappings');

    // ---- Migration: repeatable, additive, existing records untouched ----
    $before = ['items' => count_rows(Items::table()), 'links' => count_rows(Links::table()),
        'enrollments' => count_rows($wpdb->prefix . 'ohmylms_user_enrollment'), 'progress' => count_rows($wpdb->prefix . 'ohmylms_user_progress'), 'programs' => count_rows(\OhMyLMS\Learning\Schema::table('programs')),
        'states' => count_rows(AssessmentSchema::table('student_skill_state')), 'evidence' => count_rows(AssessmentSchema::table('skill_evidence')), 'courses' => (int) wp_count_posts(OHMYLMS_COURSE_CPT)->publish];
    delete_option(Schema::OPTION); ok(!Schema::ready(), 'Option reset failed');
    ok(Schema::install() === true && Schema::ready(), 'Reinstall failed');
    Schema::install();
    $after = ['items' => count_rows(Items::table()), 'links' => count_rows(Links::table()),
        'enrollments' => count_rows($wpdb->prefix . 'ohmylms_user_enrollment'), 'progress' => count_rows($wpdb->prefix . 'ohmylms_user_progress'), 'programs' => count_rows(\OhMyLMS\Learning\Schema::table('programs')),
        'states' => count_rows(AssessmentSchema::table('student_skill_state')), 'evidence' => count_rows(AssessmentSchema::table('skill_evidence')), 'courses' => (int) wp_count_posts(OHMYLMS_COURSE_CPT)->publish];
    ok($before === $after, 'Repeating the migration changed existing records: ' . wp_json_encode([$before, $after]));
} finally {
    wp_set_current_user($admin);
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
        $wpdb->delete($wpdb->prefix . 'ohmylms_user_enrollment', ['user_id' => $user_id]);
        wp_delete_user($user_id);
    }
    foreach ($posts as $post_id) { wp_delete_post($post_id, true); }
    foreach ($terms as $term_id) { wp_delete_term($term_id, Taxonomy::NAME); }
    foreach ($banks as $bank_id) { $wpdb->delete(AssessmentSchema::table('qb_banks'), ['id' => $bank_id]); }
    update_option('ohmylms_integrations', $previous_integrations);
    if (isset($start)) {
        $end = ['items' => count_rows(Items::table()), 'links' => count_rows(Links::table()), 'mappings' => count_rows(SkillMappings::table())];
        if ($end !== $start) { fwrite(STDERR, 'Cleanup left rows behind: ' . wp_json_encode([$start, $end]) . "\n"); }
    }
}
echo "$checks curriculum integration checks passed.\n";
