<?php
/**
 * Fixture for tests/browser/curriculum.spec.cjs. Disposable WordPress database only.
 * Usage: php curriculum-browser-fixture.php setup [tag] | cleanup <tag>
 */
if (PHP_SAPI !== 'cli') { exit; }
define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
$config = json_decode(file_get_contents(getenv('OHMYLMS_TEST_CREDENTIALS')), true);
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8099';
require $config['site'] . '/wp-load.php';
if (!defined('OHMYLMS_TEST_SITE') || DB_NAME !== 'ohmylms_source_test') { throw new RuntimeException('Requires disposable test site'); }
require_once ABSPATH . 'wp-admin/includes/user.php';
use OhMyLMS\Assessment\Schema as A;
use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Schema as C;
use OhMyLMS\Skills\Mastery;
use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\Bundles\Bundles;

global $wpdb;
$admin = get_user_by('login', $config['username']); wp_set_current_user($admin->ID);
$action = $argv[1] ?? ''; $tag = $argv[2] ?? strtolower(wp_generate_password(10, false, false));
if (!preg_match('/^[a-z0-9]{10}$/D', $tag)) { throw new RuntimeException('Invalid fixture tag'); }
$file = sys_get_temp_dir() . '/ohmylms-curriculum-browser-' . $tag . '.json';
$call = static function ($method, $path, $data = []) {
    $r = new WP_REST_Request($method, '/ohmylms/v1/' . $path);
    if ($data) { $r->set_header('Content-Type', 'application/json'); $r->set_body(wp_json_encode($data)); }
    $response = rest_do_request($r);
    if ($response->is_error()) { throw new RuntimeException(wp_json_encode($response->get_data())); }
    return $response->get_data();
};
if ($action === 'setup') {
    C::install();
    $state = ['tag' => $tag, 'posts' => [], 'terms' => [], 'items' => [], 'bundles' => [], 'previous_integrations' => get_option('ohmylms_integrations')];
    $create = static function ($path, $data) use ($call, &$state) { $id = (int) $call('POST', $path, $data)['id']; $state['posts'][] = $id; return $id; };
    $item = static function ($name, $parent, $type, $extra = []) use ($call, &$state) { $id = (int) $call('POST', 'curriculum/items', array_merge(['name' => $name, 'parent_id' => $parent, 'item_type' => $type], $extra))['item']['id']; $state['items'][] = $id; return $id; };
    // Courses: one the learner is enrolled in with partial progress, one they are not.
    $state['enrolled_course'] = $create('courses', ['name' => 'English warm-up ' . $tag, 'status' => 'publish']);
    $state['other_course'] = $create('courses', ['name' => 'Test prep ' . $tag, 'status' => 'publish']);
    $chapter = (int) ohmylms_get_course($state['enrolled_course'])->get_chapters()[0]['id']; $state['posts'][] = $chapter;
    $state['lessons'] = [];
    foreach (range(1, 3) as $i) {
        $lesson = $create('lessons', ['name' => 'Warm-up lesson ' . $i . ' ' . $tag, 'type' => 'text', 'description' => '<p>x</p>', 'status' => 'publish']);
        $wpdb->insert($wpdb->prefix . 'ohmylms_content_relationship', ['chapter_id' => $chapter, 'content_id' => $lesson, 'content_type' => 'text', 'order_number' => $i]);
        $state['lessons'][] = $lesson;
    }
    // Skills: one with evidence (developing), one never assessed.
    $state['skill_seen'] = (int) $call('POST', 'skills', ['name' => 'Reading inference ' . $tag, 'code' => 'RD-1'])['id']; $state['terms'][] = $state['skill_seen'];
    $state['skill_new'] = (int) $call('POST', 'skills', ['name' => 'Vocabulary in context ' . $tag, 'code' => 'VC-1'])['id']; $state['terms'][] = $state['skill_new'];
    // Curriculum seed: shapes of different depth; names are illustrative, not syllabus definitions.
    $state['root_a'] = $item('Exam board ' . $tag, 0, 'framework');
    $state['level'] = $item('Secondary level', $state['root_a'], 'level');
    $state['subject'] = $item('English language', $state['level'], 'subject');
    $state['syllabus'] = $item('English language syllabus', $state['subject'], 'syllabus', ['code' => 'E-100', 'version' => '2025']);
    $state['root_b'] = $item('Entrance test ' . $tag, 0, 'framework');
    $state['section'] = $item('Reading and writing', $state['root_b'], 'section');
    $call('POST', 'curriculum/items/' . $state['syllabus'] . '/links', ['object_type' => 'course', 'object_id' => $state['enrolled_course']]);
    $call('POST', 'curriculum/items/' . $state['section'] . '/links', ['object_type' => 'skill', 'object_id' => $state['skill_seen']]);
    $call('POST', 'curriculum/items/' . $state['section'] . '/links', ['object_type' => 'skill', 'object_id' => $state['skill_new']]);
    // A published bundle mixing a course and a curriculum item from another curriculum.
    $bundle = $call('POST', 'bundles', ['title' => 'English exam preparation ' . $tag, 'description' => 'Selected English and test-prep material.'])['bundle'];
    $state['bundle'] = (int) $bundle['id']; $state['bundles'][] = $state['bundle'];
    $call('PUT', 'bundles/' . $state['bundle'] . '/items', ['items' => [['type' => 'course', 'id' => $state['enrolled_course']], ['type' => 'course', 'id' => $state['other_course']], ['type' => 'curriculum', 'id' => $state['section']]]]);
    $call('POST', 'bundles/' . $state['bundle'] . '/publish', ['published' => true]);
    // Learner: enrolled in one course with one lesson done and one skill with two correct answers.
    $state['login'] = 'bundles-browser-' . $tag; $state['password'] = wp_generate_password(24, false, false);
    $state['student'] = wp_create_user($state['login'], $state['password'], $state['login'] . '@example.invalid');
    (new WP_User($state['student']))->set_role('subscriber');
    $wpdb->insert($wpdb->prefix . 'ohmylms_user_enrollment', ['user_id' => $state['student'], 'course_id' => $state['enrolled_course'], 'status' => 'enrolled', 'progress' => 'running', 'start_date' => current_time('mysql')]);
    $state['enrollment'] = (int) $wpdb->insert_id;
    $wpdb->insert($wpdb->prefix . 'ohmylms_user_progress', ['enrollment_id' => $state['enrollment'], 'content_id' => $state['lessons'][0], 'content_type' => 'text', 'status' => 'completed', 'start_date' => current_time('mysql')]);
    foreach ([[1, true], [2, true]] as $n => [$q, $right]) {
        $wpdb->insert(A::table('skill_evidence'), ['grade_event_id' => 9300000 + $n, 'student_id' => $state['student'], 'term_id' => $state['skill_seen'], 'part_id' => 'p1', 'role' => 'primary', 'awarded' => 1, 'available' => 1, 'independent' => 1, 'first_try' => 1, 'difficulty' => 'standard', 'family_id' => 'bf' . $q, 'source_type' => 'practice', 'source_id' => 0, 'question_id' => 9300 + $q, 'version_id' => 0, 'mapping_version' => 1, 'evidence_at' => gmdate('Y-m-d H:i:s', time() - 3 * DAY_IN_SECONDS + $n * 60), 'superseded' => 0]);
    }
    Mastery::recompute($state['student'], $state['skill_seen']);
    // A page for the learner dashboard section.
    $state['page'] = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'My bundles ' . $tag, 'post_name' => 'my-bundles-' . $tag, 'post_content' => '[ohmylms_bundles]']);
    $state['page_url'] = get_permalink($state['page']);
    $state['dashboard_url'] = get_permalink((int) get_option('ohmylms_student_dashboard_page_id'));
    file_put_contents($file, wp_json_encode($state)); echo wp_json_encode($state), "\n";
} elseif ($action === 'cleanup') {
    $state = json_decode(file_get_contents($file), true);
    foreach (array_reverse($state['bundles']) as $id) { if (Bundles::get($id)) { Bundles::delete($id, true); } }
    // Administrators may have created more items and bundles during the run: remove anything carrying the tag.
    foreach ($wpdb->get_col($wpdb->prepare('SELECT id FROM ' . Bundles::table() . ' WHERE title LIKE %s', '%' . $wpdb->esc_like($tag) . '%')) as $id) { Bundles::delete((int) $id, true); }
    foreach (array_reverse($wpdb->get_col($wpdb->prepare('SELECT id FROM ' . Items::table() . ' WHERE name LIKE %s ORDER BY id', '%' . $wpdb->esc_like($tag) . '%'))) as $id) { if (Items::get((int) $id)) { Items::delete((int) $id, 'delete', true); } }
    foreach (array_reverse($state['items']) as $id) { if (Items::get($id)) { Items::delete($id, 'delete', true); } }
    // Remaining browser-created items (names without the tag) that were created for this run.
    foreach (array_reverse($wpdb->get_col('SELECT id FROM ' . Items::table() . " WHERE name LIKE 'Browser %' ORDER BY id")) as $id) { if (Items::get((int) $id)) { Items::delete((int) $id, 'delete', true); } }
    // A syllabus is also a course: the courses (and chapters) of syllabuses that are gone go too.
    foreach (get_posts(['post_type' => OHMYLMS_COURSE_CPT, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => \OhMyLMS\Curriculum\SyllabusCourse::ITEM_META]) as $course) {
        if (Items::get((int) get_post_meta($course, \OhMyLMS\Curriculum\SyllabusCourse::ITEM_META, true))) { continue; }
        foreach (\OhMyLMS\Learning\Catalog::chapters($course) as $chapter) { wp_delete_post($chapter['id'], true); }
        wp_delete_post($course, true);
    }
    foreach (['skill_evidence', 'student_skill_state'] as $table) { $wpdb->delete(A::table($table), ['student_id' => $state['student']]); }
    $wpdb->delete(\OhMyLMS\Bundles\Follows::table(), ['user_id' => $state['student']]);
    $wpdb->delete($wpdb->prefix . 'ohmylms_user_progress', ['enrollment_id' => $state['enrollment']]);
    $wpdb->delete($wpdb->prefix . 'ohmylms_user_enrollment', ['id' => $state['enrollment']]);
    foreach ($state['posts'] as $post) { $wpdb->delete($wpdb->prefix . 'ohmylms_content_relationship', ['content_id' => $post]); wp_delete_post($post, true); }
    wp_delete_post($state['page'], true);
    foreach ($state['terms'] as $term) { wp_delete_term($term, Taxonomy::NAME); }
    wp_delete_user($state['student']);
    update_option('ohmylms_integrations', $state['previous_integrations']); unlink($file); echo "Cleaned curriculum fixture\n";
}
