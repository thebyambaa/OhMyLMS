<?php
/** CLI-only fixtures for an explicitly named local site; never exposed as an HTTP endpoint. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$site = realpath(dirname(__DIR__, 4));
if (basename(dirname($site, 2)) !== 'xyz') { throw new RuntimeException('Requires the xyz Local site'); }
$_SERVER['HTTP_HOST'] = 'xyz.local';
$_SERVER['SERVER_NAME'] = 'xyz.local';
$_SERVER['REQUEST_URI'] = '/';
define('DISABLE_WP_CRON', true);
require $site . '/wp-load.php';
$action = $argv[1] ?? 'inspect';
foreach ($GLOBALS['wp_filter']['shutdown']->callbacks ?? [] as $priority => $callbacks) {
    foreach ($callbacks as $callback) {
        if (is_array($callback['function']) && is_object($callback['function'][0]) && $callback['function'][0] instanceof \OhMyLMS\Commerce\SessionHandler) {
            remove_action('shutdown', $callback['function'], $priority);
        }
    }
}
if (parse_url(home_url(), PHP_URL_HOST) !== 'xyz.local') { throw new RuntimeException('Unexpected site URL'); }
// Fixture creation and CLI permission checks must not deliver notifications.
add_filter('pre_wp_mail', '__return_true');
add_filter('pre_http_request', static fn() => new WP_Error('fixture_network', 'External HTTP disabled during fixture setup'), 1);
$state_file = dirname(__DIR__) . '/test-results/quiz-reports-live-fixture.json';
function live_check($value, $message) { if (!$value || is_wp_error($value)) { throw new RuntimeException($message . (is_wp_error($value) ? ': '.$value->get_error_message() : '')); } return $value; }
function live_request($method, $path, $data = []) {
    $request = new WP_REST_Request($method, '/ohmylms/v1/'.$path);
    $request->set_body_params($data);
    return rest_do_request($request);
}
function live_save($state) { global $state_file; file_put_contents($state_file, wp_json_encode($state, JSON_PRETTY_PRINT)); }
$admin = get_user_by('login', 'admin');
live_check($admin && user_can($admin, 'manage_options'), 'Admin unavailable');
wp_set_current_user($admin->ID);
if ($action === 'inspect') {
    echo wp_json_encode(['site'=>home_url(), 'source_assets'=>defined('OHMYLMS_SOURCE_ASSETS') && OHMYLMS_SOURCE_ASSETS, 'plugin'=>OHMYLMS_DIR, 'tables'=>$wpdb->get_col('SHOW TABLES')]);
    exit;
}
if ($action === 'schema') {
    $created = [];
    foreach (['get_schema', 'bundled_get_schema'] as $method) {
        $reflection = new ReflectionMethod(\OhMyLMS\Install::class, $method);
        $schema = $reflection->invoke(null);
        foreach (explode(';', $schema) as $statement) {
            if (!preg_match('/CREATE TABLE\s+(\w+)/i', $statement, $match)) { continue; }
            $table = $match[1];
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)))) { continue; }
            live_check($wpdb->query($statement) !== false, 'Create missing table '.$table);
            $created[] = $table;
        }
    }
    echo wp_json_encode(['created'=>$created, 'legacy_tables'=>'untouched']); exit;
}
if ($action === 'create') {
    live_check(!file_exists($state_file), 'Existing fixture must be cleaned first');
    if (!is_dir(dirname($state_file))) { mkdir(dirname($state_file), 0700, true); }
    $state = ['marker'=>'quiz-react-'.wp_generate_password(8, false), 'posts'=>[], 'questions'=>[], 'attempts'=>[], 'student'=>0, 'enrollment'=>0];
    live_save($state);
    add_action('wp_insert_post', static function($id, $post, $update) use (&$state) {
        if (!$update && in_array($post->post_type, ['ohmylms-courses','ohmylms-chapter','ohmylms-quiz','ohmylms-question'], true)) {
            $state['posts'][] = $id;
            $state['posts'] = array_values(array_unique($state['posts']));
            update_post_meta($id, '_quiz_react_fixture', $state['marker']); live_save($state);
        }
    }, 10, 3);
    $state['student'] = live_check(wp_insert_user(['user_login'=>$state['marker'], 'user_pass'=>wp_generate_password(40), 'user_email'=>$state['marker'].'@example.invalid', 'display_name'=>'Quiz React Test Student', 'role'=>'subscriber']), 'Create student');
    live_save($state);
    foreach (['courses'=>'course', 'quiz'=>'quiz'] as $route=>$key) {
        $response = live_request('POST', $route, ['name'=>'Quiz React Integration '.$key, 'status'=>'draft']);
        live_check($response->get_status() < 300, 'Create '.$key);
        $state[$key] = $response->get_data()['id']; $state['posts'] = array_values(array_unique(array_merge($state['posts'], [$state[$key]])));
        update_post_meta($state[$key], '_quiz_react_fixture', $state['marker']); live_save($state);
    }
    $state['chapter'] = wp_insert_post(['post_type'=>'ohmylms-chapter', 'post_title'=>'Quiz React Integration chapter', 'post_status'=>'draft']);
    $state['posts'] = array_values(array_unique(array_merge($state['posts'], [$state['chapter']]))); update_post_meta($state['chapter'], '_quiz_react_fixture', $state['marker']); live_save($state);
    live_check($wpdb->insert($wpdb->prefix.'ohmylms_chapter_relationship', ['course_id'=>$state['course'], 'chapter_id'=>$state['chapter'], 'order_number'=>0]), 'Chapter relationship');
    live_check($wpdb->insert($wpdb->prefix.'ohmylms_content_relationship', ['chapter_id'=>$state['chapter'], 'content_id'=>$state['quiz'], 'content_type'=>'quiz', 'order_number'=>0]), 'Quiz relationship');
    live_check(live_request('PUT', 'quiz/'.$state['quiz'], ['settings'=>['allow_attempts'=>20, 'passing_grade'=>['enabled'=>true, 'value'=>70]]])->get_status() < 300, 'Quiz settings');
    foreach (['short-text','long-text','statement','fill-in-the-blank','single-choice','multiple-choice','true-false','reorder','matching'] as $index=>$type) {
        $response = live_request('POST', 'question', ['name'=>'Integration '.$type, 'status'=>'draft', 'settings'=>['type'=>$type, 'required'=>true, 'score'=>['enabled'=>true, 'value'=>10]]]);
        live_check($response->get_status() < 300, 'Create '.$type);
        $id = $response->get_data()['id']; $state['posts'] = array_values(array_unique(array_merge($state['posts'], [$id])));
        update_post_meta($id, '_quiz_react_fixture', $state['marker']); live_save($state);
        live_check($wpdb->insert($wpdb->prefix.'ohmylms_quiz_questions_relationship', ['quiz_id'=>$state['quiz'], 'question_id'=>$id, 'order_number'=>$index]), 'Question relationship');
        $options = [];
        foreach (['Answer A','Answer B'] as $number=>$answer) {
            live_check($wpdb->insert($wpdb->prefix.'ohmylms_question_answers', ['question_id'=>$id, 'answer'=>$answer, 'order_number'=>$number+1, 'is_correct'=>$number===0 ? 1 : 0]), 'Answer option');
            $options[] = (int)$wpdb->insert_id;
        }
        $given = $index < 4 ? ['Fixture written response'] : [$options[0]];
        if ($type === 'reorder') { $given = $options; }
        if ($type === 'matching') { $given = [(string)$options[0]=>(string)$options[0], (string)$options[1]=>(string)$options[1]]; }
        $state['questions'][] = ['id'=>$id, 'type'=>$type, 'given'=>$given, 'marks'=>$index < 4 ? 0 : 10]; live_save($state);
    }
    live_check($wpdb->insert($wpdb->prefix.'ohmylms_user_enrollment', ['user_id'=>$state['student'], 'course_id'=>$state['course'], 'status'=>'enrolled', 'progress'=>'running', 'start_date'=>current_time('mysql')]), 'Enrollment');
    $state['enrollment'] = (int)$wpdb->insert_id; live_save($state);
    for ($index=0; $index<12; $index++) {
        $date = sprintf('2026-09-%02d 10:00:00', $index+1);
        live_check($wpdb->insert($wpdb->prefix.'ohmylms_quiz_attempts', ['quiz_id'=>$state['quiz'], 'course_id'=>$state['course'], 'student_id'=>$state['student'], 'total'=>50, 'status'=>'in-review', 'start_date'=>$date, 'end_date'=>$date]), 'Attempt');
        $attempt = (int)$wpdb->insert_id; $state['attempts'][] = $attempt; live_save($state);
        foreach ($state['questions'] as $question) {
            live_check($wpdb->insert($wpdb->prefix.'ohmylms_quiz_attempts_answers', ['quiz_id'=>$state['quiz'], 'student_id'=>$state['student'], 'question_id'=>$question['id'], 'quiz_attempt_id'=>$attempt, 'given_answer'=>maybe_serialize($question['given']), 'question_marks'=>10, 'achive_mark'=>$question['marks'], 'is_correct'=>$question['marks'] > 0 ? 1 : 0]), 'Attempt answer');
        }
    }
    echo wp_json_encode(['quiz'=>$state['quiz'], 'attempts'=>$state['attempts'], 'student'=>$state['student'], 'types'=>array_column($state['questions'], 'type')]);
    exit;
}
$state = json_decode(file_get_contents($state_file), true);
foreach ($state['posts'] as $id) { live_check(get_post_meta($id, '_quiz_react_fixture', true) === $state['marker'], 'Fixture ownership mismatch'); }
if ($action === 'verify') {
    $results = [];
    foreach ([0, $state['student']] as $viewer) {
        wp_set_current_user($viewer);
        foreach ([['GET','quiz/'.$state['quiz'].'/report'], ['GET','quiz/'.$state['quiz'].'/report/'.$state['attempts'][0]], ['POST','quiz/'.$state['quiz'].'/report/'.$state['attempts'][0]]] as [$method,$path]) {
            $status = live_request($method, $path)->get_status();
            live_check(in_array($status, [401,403], true), 'Unauthorized report access');
            $results[] = ['viewer'=>$viewer ? 'subscriber' : 'guest', 'method'=>$method, 'status'=>$status];
        }
    }
    wp_set_current_user($admin->ID);
    $reports = [];
    foreach ([$state['attempts'][0], $state['attempts'][1]] as $attempt) {
        $response = live_request('GET', 'quiz/'.$state['quiz'].'/report/'.$attempt);
        live_check($response->get_status() === 200, 'Admin report');
        $data = $response->get_data();
        if (isset($argv[2])) {
            $expected_score = $attempt === $state['attempts'][0] ? (int)$argv[2] : 50;
            live_check((int)$data['score'] === $expected_score, 'Persisted score mismatch');
            live_check($data['report']['status'] === ($attempt === $state['attempts'][0] ? 'completed' : 'in-review'), 'Persisted status mismatch');
            foreach ($data['report']['questions'] as $index=>$question) {
                $expected_mark = $index >= 4 ? 10 : ($attempt === $state['attempts'][0] ? 8 : 0);
                live_check((int)$question['achive_mark'] === $expected_mark, 'Persisted question marks mismatch');
            }
        }
        $reports[] = ['attempt'=>$attempt, 'score'=>$data['score'], 'status'=>$data['report']['status'] ?? null, 'marks'=>array_column($data['report']['questions'], 'achive_mark'), 'db_status'=>$wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}ohmylms_quiz_attempts WHERE id=%d", $attempt))];
    }
    echo wp_json_encode(['permissions'=>$results, 'reports'=>$reports], JSON_PRETTY_PRINT); exit;
}
if ($action === 'cleanup') {
    foreach ($state['attempts'] as $attempt) {
        $wpdb->delete($wpdb->prefix.'ohmylms_quiz_attempts_answers', ['quiz_attempt_id'=>$attempt]);
        $wpdb->delete($wpdb->prefix.'ohmylms_quiz_attempts', ['id'=>$attempt]);
    }
    if ($state['enrollment']) { $wpdb->delete($wpdb->prefix.'ohmylms_user_progress', ['enrollment_id'=>$state['enrollment']]); $wpdb->delete($wpdb->prefix.'ohmylms_user_enrollment', ['id'=>$state['enrollment']]); }
    if (!empty($state['quiz'])) { $wpdb->delete($wpdb->prefix.'ohmylms_quiz_questions_relationship', ['quiz_id'=>$state['quiz']]); $wpdb->delete($wpdb->prefix.'ohmylms_content_relationship', ['content_id'=>$state['quiz']]); }
    if (!empty($state['course'])) { $wpdb->delete($wpdb->prefix.'ohmylms_chapter_relationship', ['course_id'=>$state['course']]); }
    foreach ($state['posts'] as $id) {
        $answers = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}ohmylms_question_answers WHERE question_id=%d", $id));
        foreach ($answers as $answer) { $wpdb->delete($wpdb->prefix.'ohmylms_question_answermeta', ['answer_id'=>$answer]); }
        $wpdb->delete($wpdb->prefix.'ohmylms_question_answers', ['question_id'=>$id]); wp_delete_post($id, true);
    }
    if ($state['student']) {
        $wpdb->delete($wpdb->prefix.'ohmylms_user_achievement', ['user_id'=>$state['student']]);
        $wpdb->delete($wpdb->prefix.'ohmylms_notifications', ['student_id'=>$state['student']]);
        require_once ABSPATH.'wp-admin/includes/user.php'; wp_delete_user($state['student']);
    }
    foreach ($state['posts'] as $id) { live_check(get_post($id) === null, 'Fixture post remains'); }
    live_check(!get_user_by('id', $state['student']), 'Fixture student remains');
    if (!empty($state['quiz'])) {
        live_check((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ohmylms_quiz_attempts WHERE quiz_id=%d", $state['quiz'])) === 0, 'Fixture attempts remain');
        live_check((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ohmylms_quiz_attempts_answers WHERE quiz_id=%d", $state['quiz'])) === 0, 'Fixture answers remain');
    }
    unlink($state_file); echo "Removed owned quiz report fixtures.\n"; exit;
}
throw new RuntimeException('Unknown action');
