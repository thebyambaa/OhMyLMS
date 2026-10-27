<?php
/**
 * Question bank / assessment engine integration suite (isolated test site only).
 *
 *   OHMYLMS_TEST_CREDENTIALS=/path/test-credentials.json php tests/php/assessment-integration.php
 */
if (PHP_SAPI !== 'cli') { exit; }
define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
$config = json_decode(file_get_contents(getenv('OHMYLMS_TEST_CREDENTIALS')), true);
require $config['site'] . '/wp-load.php';
if (!defined('OHMYLMS_TEST_SITE') || DB_NAME !== 'ohmylms_source_test') { throw new RuntimeException('Requires disposable test site'); }
require_once ABSPATH . 'wp-admin/includes/user.php';

$checks = 0;
$fixture = ['posts' => [], 'users' => [], 'attempts' => [], 'enrollments' => [], 'terms' => []];
function ok($condition, $message) { global $checks; if (!$condition) { throw new RuntimeException($message); } $checks++; }
function call($method, $path, $data = [], $query = []) {
    $request = new WP_REST_Request($method, '/ohmylms/v1/' . ltrim($path, '/'));
    if ($query) { $request->set_query_params($query); }
    if ($data) { $request->set_header('Content-Type', 'application/json'); $request->set_body(wp_json_encode($data)); }
    return rest_do_request($request);
}
function status_of($response) { return $response->get_status(); }
function as_user($id) { wp_set_current_user($id); }
function make_user($role) {
    global $fixture;
    $id = wp_create_user('ohmylms-assess-' . $role . '-' . wp_generate_password(8, false, false), wp_generate_password(24), 'assess-' . wp_generate_password(8, false, false) . '@example.invalid');
    if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
    (new WP_User($id))->set_role($role);
    $fixture['users'][] = $id;
    return $id;
}
function remember_post($id) { global $fixture; $fixture['posts'][] = (int) $id; return (int) $id; }
function option_rows($question_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare("SELECT id, answer, is_correct FROM {$wpdb->prefix}ohmylms_question_answers WHERE question_id=%d ORDER BY order_number", $question_id), ARRAY_A);
}
function linked($quiz_id, $question_id) {
    global $wpdb;
    return (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}ohmylms_quiz_questions_relationship WHERE quiz_id=%d AND question_id=%d", $quiz_id, $question_id));
}
/** A course/chapter/quiz with an enrolled student, for attempt tests. */
function course_with_quiz($admin, $quiz_settings = []) {
    global $wpdb, $fixture;
    as_user($admin);
    $course = call('POST', 'courses', ['name' => 'Assessment course', 'status' => 'publish']);
    $course_id = remember_post($course->get_data()['id']);
    $quiz = call('POST', 'quiz', ['name' => 'Assessment quiz', 'status' => 'publish']);
    $quiz_id = remember_post($quiz->get_data()['id']);
    $chapter = remember_post(wp_insert_post(['post_type' => 'ohmylms-chapter', 'post_title' => 'Assessment chapter', 'post_status' => 'publish']));
    $wpdb->insert($wpdb->prefix . 'ohmylms_chapter_relationship', ['course_id' => $course_id, 'chapter_id' => $chapter, 'order_number' => 0]);
    $wpdb->insert($wpdb->prefix . 'ohmylms_content_relationship', ['chapter_id' => $chapter, 'content_id' => $quiz_id, 'content_type' => 'quiz', 'order_number' => 0]);
    if ($quiz_settings) { call('PUT', 'quiz/' . $quiz_id, ['settings' => $quiz_settings]); }
    return [$course_id, $quiz_id];
}
function enroll($student, $course_id) {
    global $wpdb, $fixture;
    $wpdb->insert($wpdb->prefix . 'ohmylms_user_enrollment', ['user_id' => $student, 'course_id' => $course_id, 'status' => 'enrolled', 'progress' => 'running', 'start_date' => current_time('mysql')]);
    $fixture['enrollments'][] = (int) $wpdb->insert_id;
    $cache = new ReflectionProperty(\OhMyLMS\DataStores\StudentStore::class, 'enrollment_cache');
    $cache->setAccessible(true); $cache->setValue(null, []);
}
function choice_question($quiz_id, $name = 'Choice', $score = 2) {
    $response = call('POST', 'question', ['quiz_id' => $quiz_id, 'name' => $name, 'settings' => ['type' => 'single-choice', 'score' => ['enabled' => true, 'value' => $score]],
        'questions' => [['answer' => 'Right', 'is_correct' => 1, 'order_number' => 1], ['answer' => 'Wrong', 'is_correct' => 0, 'order_number' => 2]]]);
    ok(status_of($response) === 201, 'Question create failed: ' . wp_json_encode($response->get_data()));
    return remember_post($response->get_data()['id']);
}

$admin = get_user_by('login', $config['username'])->ID;
$phases = array_slice($argv, 1) ?: ['authoring'];
$previous_integrations = get_option('ohmylms_integrations', []);

try {
    if (in_array('authoring', $phases, true)) {
        // ---- A02: object-level authorization and option ownership ----
        $author_a = make_user('author');
        $author_b = make_user('author');
        $subscriber = make_user('subscriber');

        as_user($author_a);
        $quiz_a = remember_post(call('POST', 'quiz', ['name' => 'Quiz A', 'status' => 'draft'])->get_data()['id']);
        $question_a = choice_question($quiz_a, 'Question A');
        $options_a = option_rows($question_a);
        ok(count($options_a) === 2, 'Options were not created with the question');
        ok(linked($quiz_a, $question_a), 'Question was not placed in its quiz');

        as_user($author_b);
        $quiz_b = remember_post(call('POST', 'quiz', ['name' => 'Quiz B', 'status' => 'draft'])->get_data()['id']);
        $question_b = choice_question($quiz_b, 'Question B');
        ok(status_of(call('GET', 'question/' . $question_a)) === 403, 'Another author could read a question');
        ok(status_of(call('PUT', 'question/' . $question_a, ['name' => 'Hijacked'])) === 403, 'Another author could update a question');
        ok(status_of(call('DELETE', 'question/' . $question_a)) === 403, 'Another author could delete a question');
        ok(status_of(call('GET', 'quiz/' . $quiz_a)) === 403, 'Another author could open a quiz');
        ok(status_of(call('GET', 'quiz/' . $quiz_a . '/report')) === 403, 'Another author could read quiz reports');
        ok(get_the_title($question_a) === 'Question A', 'Denied update still changed the question');

        // Nested writes through the author's own quiz cannot reach someone else's question.
        $response = call('PUT', 'quiz/' . $quiz_b, ['name' => 'Quiz B renamed', 'content' => [['id' => $question_a, 'name' => 'Nested hijack']]]);
        ok(status_of($response) === 403, 'Nested quiz save edited a foreign question: ' . status_of($response));
        ok(get_the_title($question_a) === 'Question A', 'Nested hijack changed the foreign question');
        ok(get_the_title($quiz_b) === 'Quiz B', 'A rejected nested save still renamed the quiz');
        ok(!linked($quiz_b, $question_a), 'Rejected nested save linked a foreign question');

        // Foreign option IDs are rejected and the owning question is untouched.
        $response = call('PUT', 'question/' . $question_b, ['questions' => [['id' => $options_a[0]['id'], 'answer' => 'Stolen', 'is_correct' => 1]]]);
        ok(status_of($response) === 403 && $response->get_data()['code'] === 'ohmylms_option_foreign', 'Foreign option ID accepted: ' . wp_json_encode($response->get_data()));
        ok(option_rows($question_a) == $options_a, 'Foreign option update changed the original question');
        as_user($author_a);
        $own_quiz_q2 = choice_question($quiz_a, 'Second A');
        $response = call('PUT', 'question/' . $own_quiz_q2, ['questions' => [['id' => $options_a[1]['id'], 'answer' => 'Moved', 'is_correct' => 1]]]);
        ok(status_of($response) === 403, 'Option ID from another question of the same author accepted');
        ok(option_rows($question_a) == $options_a, 'Cross-question option update moved an option');

        as_user($subscriber);
        ok(status_of(call('GET', 'question/' . $question_a)) === 403, 'Subscriber read a question');
        ok(status_of(call('POST', 'question', ['name' => 'x'])) === 403, 'Subscriber created a question');
        as_user(0);
        ok(status_of(call('GET', 'quiz/' . $quiz_a)) === 401, 'Guest opened a quiz');

        // ---- A03: aggregate validation, atomic nested saves, stale saves ----
        as_user($author_a);
        $response = call('PUT', 'quiz/' . $quiz_a, ['name' => 'Quiz A renamed', 'content' => [
            ['id' => $question_a, 'name' => 'Question A edited'],
            ['name' => 'Broken', 'settings' => ['type' => 'not-a-registered-type']],
            ['name' => 'Fine new question', 'settings' => ['type' => 'true-false']],
        ]]);
        $data = $response->get_data();
        ok(status_of($response) === 400 && $data['code'] === 'ohmylms_question_batch_invalid', 'Invalid child was not reported: ' . wp_json_encode($data));
        ok(count($data['data']['errors']) === 1 && $data['data']['errors'][0]['index'] === 1, 'Child error lacks its index');
        ok(get_the_title($question_a) === 'Question A' && get_the_title($quiz_a) === 'Quiz A', 'Failed batch partially saved');
        ok(count(\OhMyLMS\QuestionBank\Usage::quizzes($question_a)) === 1 && count(ohmylms_get_quiz($quiz_a)->get_questions()) === 2, 'Failed batch created a question');

        $response = call('PUT', 'quiz/' . $quiz_a, ['content' => [['id' => $question_a, 'name' => 'Twice'], ['id' => $question_a, 'name' => 'Twice again']]]);
        ok(status_of($response) === 400, 'Duplicate question in one save accepted');

        $response = call('PUT', 'quiz/' . $quiz_a, ['name' => 'Quiz A saved', 'content' => [
            ['id' => $question_a, 'name' => 'Question A edited', 'order_number' => 2],
            ['name' => 'New via quiz', 'settings' => ['type' => 'true-false'], 'order_number' => 1, 'questions' => [['answer' => 'True', 'is_correct' => 1], ['answer' => 'False']]],
        ]]);
        $data = $response->get_data();
        ok(status_of($response) === 200, 'Valid nested save failed: ' . wp_json_encode($data));
        ok(count($data['saved_ids']) === 2 && $data['saved_ids'][0] === $question_a, 'Saved IDs not returned in submitted order');
        remember_post($data['saved_ids'][1]);
        ok(get_the_title($question_a) === 'Question A edited' && get_the_title($quiz_a) === 'Quiz A saved', 'Valid nested save lost data');
        ok(count(option_rows($data['saved_ids'][1])) === 2, 'Options of new nested question missing');

        $stale = '2000-01-01 00:00:00';
        $response = call('PUT', 'question/' . $question_a, ['name' => 'Stale', 'base_modified' => $stale]);
        ok(status_of($response) === 409, 'Stale question save accepted');
        $response = call('PUT', 'quiz/' . $quiz_a, ['name' => 'Stale quiz', 'base_modified' => $stale]);
        ok(status_of($response) === 409, 'Stale quiz save accepted');
        $fresh = get_post_field('post_modified_gmt', $question_a);
        ok(status_of(call('PUT', 'question/' . $question_a, ['name' => 'Fresh', 'base_modified' => $fresh])) === 200, 'Current base_modified was rejected');

        // ---- P1: remove from quiz / archive / delete-unused ----
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'ohmylms_quiz_questions_relationship', ['quiz_id' => $quiz_b, 'question_id' => $own_quiz_q2, 'order_number' => 9]);
        as_user($admin);
        $response = call('DELETE', 'question/' . $own_quiz_q2);
        ok(status_of($response) === 409, 'Deleting a question used by two quizzes was allowed');
        $response = call('DELETE', 'quiz/' . $quiz_b . '/questions/' . $own_quiz_q2);
        ok(status_of($response) === 200 && $response->get_data()['action'] === 'removed', 'Remove from quiz failed');
        ok(!linked($quiz_b, $own_quiz_q2) && linked($quiz_a, $own_quiz_q2) && get_post_type($own_quiz_q2) === 'ohmylms-question', 'Remove from quiz affected other quizzes or the question');
        $wpdb->insert($wpdb->prefix . 'ohmylms_quiz_attempts_answers', ['student_id' => $subscriber, 'quiz_id' => $quiz_a, 'question_id' => $own_quiz_q2, 'quiz_attempt_id' => 0, 'given_answer' => 'a:0:{}']);
        $history_row = (int) $wpdb->insert_id;
        $response = call('DELETE', 'question/' . $own_quiz_q2);
        ok(status_of($response) === 200 && $response->get_data()['action'] === 'archived', 'Question with learner history was not archived: ' . wp_json_encode($response->get_data()));
        ok(get_post_status($own_quiz_q2) === 'ohmylms_archived' && count(option_rows($own_quiz_q2)) === 2, 'Archived question lost its content');
        $wpdb->delete($wpdb->prefix . 'ohmylms_quiz_attempts_answers', ['id' => $history_row]);
        $unused = choice_question($quiz_a, 'Unused');
        ok(call('DELETE', 'quiz/' . $quiz_a . '/questions/' . $unused)->get_data()['action'] === 'removed', 'Unlink unused failed');
        $response = call('DELETE', 'question/' . $unused);
        ok($response->get_data()['action'] === 'deleted' && !get_post($unused) && !option_rows($unused), 'Unused question was not fully deleted');

        // Reports are scoped to their own quiz.
        $wpdb->insert($wpdb->prefix . 'ohmylms_quiz_attempts', ['quiz_id' => $quiz_b, 'student_id' => $subscriber, 'course_id' => 0, 'total' => 0, 'status' => 'completed', 'start_date' => current_time('mysql')]);
        $foreign_attempt = (int) $wpdb->insert_id; $fixture['attempts'][] = $foreign_attempt;
        ok(status_of(call('GET', 'quiz/' . $quiz_a . '/report/' . $foreign_attempt)) === 404, 'Attempt from another quiz was reported');
        echo "authoring: passed\n";
    }

    foreach (array_diff($phases, ['authoring']) as $phase) {
        $file = __DIR__ . '/assessment/' . $phase . '.php';
        if (!is_file($file)) { throw new RuntimeException('Unknown phase ' . $phase); }
        require $file;
        echo "$phase: passed\n";
    }
    echo "$checks assessment integration checks passed.\n";
} finally {
    as_user($admin);
    global $wpdb;
    if (\OhMyLMS\Assessment\Schema::ready()) {
        $t = static function ($name) { return \OhMyLMS\Assessment\Schema::table($name); };
        $attempt_ids = $fixture['attempts'];
        foreach ($fixture['users'] as $user) {
            $attempt_ids = array_merge($attempt_ids, array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}ohmylms_quiz_attempts WHERE student_id=%d", $user))));
            $sessions = array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT id FROM {$t('practice_sessions')} WHERE student_id=%d", $user)));
            foreach ($sessions as $session) { $wpdb->delete($t('practice_items'), ['session_id' => $session]); }
            $wpdb->delete($t('practice_sessions'), ['student_id' => $user]);
            $wpdb->delete($t('student_skill_state'), ['student_id' => $user]);
            $wpdb->delete($t('skill_evidence'), ['student_id' => $user]);
            foreach ($wpdb->get_col($wpdb->prepare("SELECT id FROM {$t('grade_events')} WHERE student_id=%d", $user)) as $event) { $wpdb->delete($t('evidence_outbox'), ['grade_event_id' => (int) $event]); }
            $wpdb->delete($t('grade_events'), ['student_id' => $user]);
        }
        foreach (array_unique($attempt_ids) as $attempt) {
            foreach (['attempt_context', 'attempt_items', 'response_drafts', 'response_events'] as $table) { $wpdb->delete($t($table), ['attempt_id' => $attempt]); }
        }
        foreach ($fixture['posts'] as $post) {
            foreach ($wpdb->get_col($wpdb->prepare("SELECT id FROM {$t('qb_question_versions')} WHERE question_id=%d", $post)) as $version) { $wpdb->delete($t('qb_version_skills'), ['version_id' => (int) $version]); }
            $wpdb->delete($t('qb_question_versions'), ['question_id' => $post]);
            $wpdb->delete($t('qb_questions'), ['question_id' => $post]);
            foreach ($wpdb->get_col($wpdb->prepare("SELECT id FROM {$t('quiz_revisions')} WHERE quiz_id=%d", $post)) as $revision) { $wpdb->delete($t('quiz_revision_slots'), ['revision_id' => (int) $revision]); }
            $wpdb->delete($t('quiz_revisions'), ['quiz_id' => $post]);
        }
        foreach ($fixture['banks'] ?? [] as $bank) { $wpdb->delete($t('qb_grants'), ['bank_id' => $bank]); $wpdb->delete($t('qb_banks'), ['id' => $bank]); }
    }
    foreach ($fixture['users'] as $user) {
        foreach (['ohmylms_quiz_attempts_answers', 'ohmylms_quiz_attempts'] as $table) { $wpdb->delete($wpdb->prefix . $table, ['student_id' => $user]); }
        $wpdb->delete($wpdb->prefix . 'ohmylms_user_enrollment', ['user_id' => $user]);
        do_action('ohmylms_test_cleanup_user', $user);
        wp_delete_user($user);
    }
    foreach ($fixture['attempts'] as $attempt) { $wpdb->delete($wpdb->prefix . 'ohmylms_quiz_attempts', ['id' => $attempt]); }
    foreach ($fixture['enrollments'] as $enrollment) {
        $wpdb->delete($wpdb->prefix . 'ohmylms_user_progress', ['enrollment_id' => $enrollment]);
        $wpdb->delete($wpdb->prefix . 'ohmylms_user_enrollment', ['id' => $enrollment]);
    }
    foreach ($fixture['terms'] as [$term, $taxonomy]) { wp_delete_term($term, $taxonomy); }
    foreach ($fixture['posts'] as $post) {
        $wpdb->delete($wpdb->prefix . 'ohmylms_quiz_questions_relationship', ['quiz_id' => $post]);
        $wpdb->delete($wpdb->prefix . 'ohmylms_quiz_questions_relationship', ['question_id' => $post]);
        foreach ($wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}ohmylms_question_answers WHERE question_id=%d", $post)) as $answer) {
            $wpdb->delete($wpdb->prefix . 'ohmylms_question_answermeta', ['answer_id' => $answer]);
        }
        $wpdb->delete($wpdb->prefix . 'ohmylms_question_answers', ['question_id' => $post]);
        $wpdb->delete($wpdb->prefix . 'ohmylms_content_relationship', ['content_id' => $post]);
        $wpdb->delete($wpdb->prefix . 'ohmylms_chapter_relationship', ['chapter_id' => $post]);
        do_action('ohmylms_test_cleanup_post', $post);
        wp_delete_post($post, true);
    }
    update_option('ohmylms_integrations', $previous_integrations);
}
