<?php
if (PHP_SAPI !== 'cli') { exit; }
$config = json_decode(file_get_contents(getenv('OHMYLMS_TEST_CREDENTIALS')), true);
require $config['site'] . '/wp-load.php';
if (!defined('OHMYLMS_TEST_SITE') || DB_NAME !== 'ohmylms_source_test') { throw new RuntimeException('Requires disposable test site'); }
require_once ABSPATH . 'wp-admin/includes/user.php';
use OhMyLMS\Learning\Schema as L;
use OhMyLMS\Assessment\Schema as A;

$admin = get_user_by('login', $config['username']); wp_set_current_user($admin->ID);
$action = $argv[1] ?? ''; $tag = $argv[2] ?? strtolower(wp_generate_password(10, false, false));
if (!preg_match('/^[a-z0-9]{10}$/D', $tag)) { throw new RuntimeException('Invalid fixture tag'); }
$file = sys_get_temp_dir() . '/ohmylms-learning-browser-' . $tag . '.json';
$call = static function ($method, $path, $data = []) {
    $r = new WP_REST_Request($method, '/ohmylms/v1/' . $path);
    if ($data) { $r->set_header('Content-Type', 'application/json'); $r->set_body(wp_json_encode($data)); }
    $response = rest_do_request($r);
    if ($response->is_error()) { throw new RuntimeException(wp_json_encode($response->get_data())); }
    return $response->get_data();
};
if ($action === 'setup') {
    $state = ['tag' => $tag, 'posts' => [], 'previous_integrations' => get_option('ohmylms_integrations')];
    $create = static function ($path, $data) use ($call, &$state) { $id = (int) $call('POST', $path, $data)['id']; $state['posts'][] = $id; return $id; };
    $state['original_course'] = $create('courses', ['name' => 'Original fractions course ' . $tag, 'status' => 'publish']);
    $state['course'] = $create('courses', ['name' => 'Blended fractions ' . $tag, 'status' => 'publish']);
    foreach ([$state['original_course'], $state['course']] as $course) { $state['posts'][] = (int) ohmylms_get_course($course)->get_chapters()[0]['id']; }
    $state['lesson'] = $create('lessons', ['name' => 'Equivalent fractions shared lesson ' . $tag, 'type' => 'text', 'description' => '<p>Two fractions are equivalent when they describe the same amount.</p><p>For example, one half is equal to two quarters.</p>', 'status' => 'publish']);
    $wpdb->insert($wpdb->prefix . 'ohmylms_content_relationship', ['chapter_id' => ohmylms_get_course($state['original_course'])->get_chapters()[0]['id'], 'content_id' => $state['lesson'], 'content_type' => 'text', 'order_number' => 0]);
    $state['skill'] = (int) $call('POST', 'skills', ['name' => 'Recognize equivalent fractions ' . $tag, 'code' => 'FRA-EQ'])['id'];
    foreach (range(1, 4) as $i) {
        $q = $create('question', ['name' => 'Equivalent fractions ' . $i, 'settings' => ['type' => 'single-choice', 'score' => ['enabled' => true, 'value' => 1]], 'questions' => [['answer' => 'Same value', 'is_correct' => 1], ['answer' => 'Different value']], 'skills' => ['p1' => ['primary' => $state['skill'], 'supporting' => []]], 'bank' => ['family_id' => 'browser-learning-' . $i]]);
        $call('POST', 'question-bank/' . $q . '/approve');
    }
    $state['login'] = 'learning-browser-' . $tag; $state['password'] = wp_generate_password(24, false, false);
    $state['student'] = wp_create_user($state['login'], $state['password'], $state['login'] . '@example.invalid');
    (new WP_User($state['student']))->set_role('subscriber');
    $wpdb->insert($wpdb->prefix . 'ohmylms_user_enrollment', ['user_id' => $state['student'], 'course_id' => $state['course'], 'status' => 'enrolled', 'progress' => 'running', 'start_date' => current_time('mysql')]);
    $state['enrollment'] = (int) $wpdb->insert_id;
    file_put_contents($file, wp_json_encode($state)); echo wp_json_encode($state), "\n";
} elseif ($action === 'cleanup') {
    $state = json_decode(file_get_contents($file), true);
    $sessions = $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . A::table('practice_sessions') . ' WHERE student_id=%d', $state['student']));
    foreach ($sessions as $session) { $wpdb->delete(A::table('practice_items'), ['session_id' => $session]); }
    $wpdb->delete(A::table('practice_sessions'), ['student_id' => $state['student']]);
    $events = $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . A::table('grade_events') . ' WHERE student_id=%d', $state['student']));
    foreach ($events as $event) { $wpdb->delete(A::table('evidence_outbox'), ['grade_event_id' => $event]); }
    foreach (['grade_events', 'skill_evidence', 'student_skill_state'] as $table) { $wpdb->delete(A::table($table), ['student_id' => $state['student']]); }
    foreach (['awards', 'enrollments'] as $table) { $wpdb->delete(L::table($table), ['enrollment_id' => $state['enrollment']]); }
    $wpdb->delete($wpdb->prefix . 'ohmylms_user_progress', ['enrollment_id' => $state['enrollment']]);
    $wpdb->delete($wpdb->prefix . 'ohmylms_user_enrollment', ['id' => $state['enrollment']]);
    foreach ($state['posts'] as $post) {
        $wpdb->delete($wpdb->prefix . 'ohmylms_content_relationship', ['content_id' => $post]);
        foreach ($wpdb->get_col($wpdb->prepare('SELECT id FROM ' . L::table('programs') . ' WHERE course_id=%d', $post)) as $program) { $wpdb->delete(L::table('outcomes'), ['program_id' => $program]); }
        $wpdb->delete(L::table('programs'), ['course_id' => $post]); wp_delete_post($post, true);
    }
    wp_delete_term($state['skill'], \OhMyLMS\Skills\Taxonomy::NAME); wp_delete_user($state['student']);
    update_option('ohmylms_integrations', $state['previous_integrations']); unlink($file); echo "Cleaned learning fixture\n";
}
