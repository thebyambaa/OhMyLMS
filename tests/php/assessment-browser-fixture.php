<?php
/**
 * Fixture for tests/browser/assessment.spec.cjs (disposable test site only).
 *
 *   php tests/php/assessment-browser-fixture.php setup             # prints JSON
 *   php tests/php/assessment-browser-fixture.php attempt <tag>     # latest attempt of the learner, JSON
 *   php tests/php/assessment-browser-fixture.php cleanup <tag>
 */
if (PHP_SAPI !== 'cli') { exit; }
define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
$config = json_decode(file_get_contents(getenv('OHMYLMS_TEST_CREDENTIALS')), true);
require $config['site'] . '/wp-load.php';
if (!defined('OHMYLMS_TEST_SITE') || DB_NAME !== 'ohmylms_source_test') { throw new RuntimeException('Requires disposable test site'); }
require_once ABSPATH . 'wp-admin/includes/user.php';
use OhMyLMS\Assessment\Schema as A;
use OhMyLMS\Schools\Schema as S;

global $wpdb;
$action = $argv[1] ?? '';
$tag = $argv[2] ?? '';
$state_file = static function ($tag) {
    if (!preg_match('/^[a-z0-9]{10}$/', $tag)) { throw new RuntimeException('Invalid fixture tag'); }
    return sys_get_temp_dir() . '/ohmylms-assessment-browser-' . $tag . '.json';
};
$admin = get_user_by('login', $config['username']);
wp_set_current_user($admin->ID);
$call = static function ($method, $path, $data = []) {
    $request = new WP_REST_Request($method, '/ohmylms/v1/' . $path);
    if ($data) { $request->set_header('Content-Type', 'application/json'); $request->set_body(wp_json_encode($data)); }
    $response = rest_do_request($request);
    if ($response->is_error()) { throw new RuntimeException($method . ' ' . $path . ': ' . wp_json_encode($response->get_data())); }
    return $response->get_data();
};

if ($action === 'setup') {
    $tag = strtolower(wp_generate_password(10, false, false));
    $state = ['tag' => $tag, 'posts' => [], 'users' => [], 'terms' => [], 'previous_integrations' => get_option('ohmylms_integrations', [])];
    try {
        // Learner, guardian and teacher accounts with throwaway passwords.
        foreach (['learner', 'guardian', 'teacher'] as $role) {
            $login = "ab-$role-$tag";
            $password = wp_generate_password(20, false, false);
            $id = wp_create_user($login, $password, "$login@example.invalid");
            if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
            wp_update_user(['ID' => $id, 'display_name' => ['learner' => 'Сүхээ Learner', 'guardian' => 'Browser Guardian', 'teacher' => 'Browser Teacher'][$role]]);
            $state['users'][$role] = ['id' => $id, 'login' => $login, 'password' => $password];
        }
        // Course, chapter and a quiz with three choice questions.
        $course = $call('POST', 'courses', ['name' => "Browser exam course $tag", 'status' => 'publish'])['id'];
        $quiz = $call('POST', 'quiz', ['name' => "Browser exam $tag", 'status' => 'publish'])['id'];
        $chapter = wp_insert_post(['post_type' => 'ohmylms-chapter', 'post_title' => 'Browser chapter', 'post_status' => 'publish']);
        array_push($state['posts'], $course, $quiz, $chapter);
        $wpdb->insert($wpdb->prefix . 'ohmylms_chapter_relationship', ['course_id' => $course, 'chapter_id' => $chapter, 'order_number' => 0]);
        $wpdb->insert($wpdb->prefix . 'ohmylms_content_relationship', ['chapter_id' => $chapter, 'content_id' => $quiz, 'content_type' => 'quiz', 'order_number' => 0]);
        $call('PUT', "quiz/$quiz", ['settings' => ['layout' => 'all_questions_in_one_page', 'allow_attempts' => 5, 'time_limit' => ['value' => 30, 'type' => 'minutes']]]);
        $questions = [];
        foreach (['Pick 2 + 2' => ['4', '5'], 'Pick 3 × 3' => ['9', '6'], 'Pick 10 − 7' => ['3', '7']] as $name => [$right, $wrong]) {
            $questions[] = $call('POST', 'question', ['quiz_id' => $quiz, 'name' => $name, 'settings' => ['type' => 'single-choice', 'score' => ['enabled' => true, 'value' => 1]],
                'questions' => [['answer' => $right, 'is_correct' => 1, 'order_number' => 1], ['answer' => $wrong, 'is_correct' => 0, 'order_number' => 2]]])['id'];
        }
        $state['posts'] = array_merge($state['posts'], $questions);
        // Sections without a page break; the browser test adds it.
        $call('PUT', "quiz/$quiz/assessment-settings", ['sections' => [
            ['title' => 'Part A', 'questions' => [$questions[0], $questions[1]], 'marks' => []],
            ['title' => 'Part B', 'questions' => [$questions[2]], 'marks' => []],
        ]]);
        $wpdb->insert($wpdb->prefix . 'ohmylms_user_enrollment', ['user_id' => $state['users']['learner']['id'], 'course_id' => $course, 'status' => 'enrolled', 'progress' => 'running', 'start_date' => current_time('mysql')]);
        // A skill with two approved practice questions; the first is also an inline check.
        $skill = $call('POST', 'skills', ['name' => "Бутархай $tag", 'public_practice' => true])['id'];
        $state['terms'][] = $skill;
        $practice = [];
        foreach (['Which is ½ of 8?' => ['4', '2'], 'Which is ¼ of 8?' => ['2', '4']] as $name => [$right, $wrong]) {
            $id = $call('POST', 'question', ['quiz_id' => $quiz, 'name' => $name, 'settings' => ['type' => 'single-choice', 'score' => ['enabled' => true, 'value' => 1]],
                'questions' => [['answer' => $right, 'is_correct' => 1, 'order_number' => 1], ['answer' => $wrong, 'is_correct' => 0, 'order_number' => 2]]])['id'];
            $call('DELETE', "quiz/$quiz/questions/$id");
            $call('PUT', "question/$id/skills", ['skill_map' => ['p1' => ['primary' => $skill, 'supporting' => []]]]);
            $call('POST', "question-bank/$id/approve");
            $practice[] = $id;
        }
        $state['posts'] = array_merge($state['posts'], $practice);
        $page = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => "Browser lesson check $tag",
            'post_content' => '<p>Read, then check yourself.</p>[ohmylms_question uuid="' . \OhMyLMS\QuestionBank\VersionPublisher::uuid($practice[0]) . '"]']);
        $state['posts'][] = $page;
        $call('POST', "quiz/$quiz/revisions");
        // A school with the learner's guardian and a class taught by the teacher.
        $now = current_time('mysql', true);
        $wpdb->insert(S::table('schools'), ['name' => "Browser assessment school $tag", 'slug' => "browser-assessment-$tag", 'timezone' => 'UTC', 'status' => 'active', 'created_by' => $admin->ID, 'created_at' => $now]);
        $state['school'] = $school = (int) $wpdb->insert_id;
        foreach (['learner' => 'student', 'guardian' => 'guardian', 'teacher' => 'teacher'] as $key => $role) {
            $wpdb->insert(S::table('school_memberships'), ['school_id' => $school, 'user_id' => $state['users'][$key]['id'], 'role' => $role, 'status' => 'active', 'joined_at' => $now]);
        }
        $wpdb->insert(S::table('guardian_links'), ['school_id' => $school, 'guardian_user_id' => $state['users']['guardian']['id'], 'student_user_id' => $state['users']['learner']['id'], 'status' => 'active', 'approved_by' => $admin->ID, 'approved_at' => $now]);
        $wpdb->insert(S::table('classes'), ['school_id' => $school, 'academic_year_id' => 0, 'name' => "Browser class $tag", 'grade' => '8', 'status' => 'active']);
        $state['class'] = $class = (int) $wpdb->insert_id;
        foreach (['learner' => 'student', 'teacher' => 'teacher'] as $key => $role) {
            $wpdb->insert(S::table('class_memberships'), ['class_id' => $class, 'user_id' => $state['users'][$key]['id'], 'role' => $role, 'status' => 'active', 'joined_at' => $now]);
        }
        $state += ['course' => $course, 'quiz' => $quiz, 'quiz_url' => get_permalink($quiz), 'questions' => $questions, 'skill' => $skill, 'practice' => $practice, 'page_url' => get_permalink($page)];
        file_put_contents($state_file($tag), wp_json_encode($state));
        echo wp_json_encode($state), "\n";
    } catch (Throwable $error) {
        file_put_contents($state_file($tag), wp_json_encode($state));
        fwrite(STDERR, $error->getMessage() . "\nCleanup tag: $tag\n");
        exit(1);
    }
    exit;
}

$state = json_decode((string) @file_get_contents($state_file($tag)), true);
if (!$state) { throw new RuntimeException('Unknown fixture ' . $tag); }

if ($action === 'attempt') {
    $row = $wpdb->get_row($wpdb->prepare("SELECT id, status, total FROM {$wpdb->prefix}ohmylms_quiz_attempts WHERE student_id=%d AND quiz_id=%d ORDER BY id DESC LIMIT 1", $state['users']['learner']['id'], $state['quiz']), ARRAY_A);
    $evidence = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . A::table('skill_evidence') . " WHERE student_id=%d", $state['users']['learner']['id']));
    echo wp_json_encode(['attempt' => $row, 'evidence' => $evidence]), "\n";
    exit;
}

if ($action === 'cleanup') {
    $t = static function ($name) { return A::table($name); };
    $users = array_column($state['users'], 'id');
    $page = end($state['posts']);
    foreach ($users as $user) {
        foreach ($wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}ohmylms_quiz_attempts WHERE student_id=%d", $user)) as $attempt) {
            foreach (['attempt_context', 'attempt_items', 'response_drafts', 'response_events'] as $table) { $wpdb->delete($t($table), ['attempt_id' => (int) $attempt]); }
        }
        foreach ($wpdb->get_col($wpdb->prepare("SELECT id FROM {$t('grade_events')} WHERE student_id=%d", $user)) as $event) { $wpdb->delete($t('evidence_outbox'), ['grade_event_id' => (int) $event]); }
        foreach (['grade_events', 'skill_evidence', 'student_skill_state'] as $table) { $wpdb->delete($t($table), ['student_id' => $user]); }
        foreach (['ohmylms_quiz_attempts_answers', 'ohmylms_quiz_attempts'] as $table) { $wpdb->delete($wpdb->prefix . $table, ['student_id' => $user]); }
        $wpdb->delete($wpdb->prefix . 'ohmylms_user_enrollment', ['user_id' => $user]);
    }
    // Practice and inline sessions for these learners and for guests on the fixture page.
    $sessions = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$t('practice_sessions')} WHERE term_id=%d OR lesson_id=%d", $state['skill'] ?? 0, $page));
    foreach ($users as $user) { $sessions = array_merge($sessions, $wpdb->get_col($wpdb->prepare("SELECT id FROM {$t('practice_sessions')} WHERE student_id=%d", $user))); }
    foreach (array_unique(array_map('intval', $sessions)) as $session) {
        $guest = (int) $wpdb->get_var($wpdb->prepare("SELECT guest_id FROM {$t('practice_sessions')} WHERE id=%d", $session));
        $wpdb->delete($t('practice_items'), ['session_id' => $session]);
        $wpdb->delete($t('practice_sessions'), ['id' => $session]);
        if ($guest && !$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t('practice_sessions')} WHERE guest_id=%d", $guest))) { $wpdb->delete($t('guest_sessions'), ['id' => $guest]); }
    }
    foreach ($users as $user) { wp_delete_user($user); }
    if (!empty($state['school'])) {
        foreach (['guardian_links', 'school_memberships', 'classes', 'school_audit_log'] as $table) { $wpdb->delete(S::table($table), ['school_id' => $state['school']]); }
        $wpdb->delete(S::table('schools'), ['id' => $state['school']]);
        $wpdb->delete(S::table('class_memberships'), ['class_id' => $state['class'] ?? 0]);
    }
    foreach ($state['posts'] as $post) {
        foreach ($wpdb->get_col($wpdb->prepare("SELECT id FROM {$t('qb_question_versions')} WHERE question_id=%d", $post)) as $version) { $wpdb->delete($t('qb_version_skills'), ['version_id' => (int) $version]); }
        $wpdb->delete($t('qb_question_versions'), ['question_id' => $post]);
        $wpdb->delete($t('qb_questions'), ['question_id' => $post]);
        foreach ($wpdb->get_col($wpdb->prepare("SELECT id FROM {$t('quiz_revisions')} WHERE quiz_id=%d", $post)) as $revision) { $wpdb->delete($t('quiz_revision_slots'), ['revision_id' => (int) $revision]); }
        $wpdb->delete($t('quiz_revisions'), ['quiz_id' => $post]);
        $wpdb->delete($wpdb->prefix . 'ohmylms_quiz_questions_relationship', ['quiz_id' => $post]);
        $wpdb->delete($wpdb->prefix . 'ohmylms_quiz_questions_relationship', ['question_id' => $post]);
        $wpdb->delete($wpdb->prefix . 'ohmylms_question_answers', ['question_id' => $post]);
        $wpdb->delete($wpdb->prefix . 'ohmylms_chapter_relationship', ['course_id' => $post]);
        $wpdb->delete($wpdb->prefix . 'ohmylms_content_relationship', ['content_id' => $post]);
        wp_delete_post($post, true);
    }
    foreach ($state['terms'] as $term) { wp_delete_term($term, \OhMyLMS\Skills\Taxonomy::NAME); }
    if (isset($state['previous_integrations'])) { update_option('ohmylms_integrations', $state['previous_integrations']); }
    @unlink($state_file($tag));
    echo "Assessment browser fixture cleaned.\n";
    exit;
}
throw new RuntimeException('Usage: setup | attempt <tag> | cleanup <tag>');
