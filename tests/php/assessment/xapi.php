<?php
/**
 * xAPI emitter: queuing, statement content, delivery, retry and failure handling.
 *
 *   OHMYLMS_TEST_CREDENTIALS=/path/test-credentials.json php tests/php/assessment-integration.php xapi
 *
 * The LRS is faked through pre_http_request, so nothing leaves the machine.
 */
use OhMyLMS\Assessment\AttemptItems;
use OhMyLMS\Assessment\GradeEvents;
use OhMyLMS\Quiz\Review;
use OhMyLMS\Quiz\Submission;
use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\XApi\AdminPage;
use OhMyLMS\XApi\Materializer;
use OhMyLMS\XApi\Outbox;
use OhMyLMS\XApi\Processor;
use OhMyLMS\XApi\Schema as XSchema;
use OhMyLMS\XApi\Settings;
use OhMyLMS\XApi\Uuid;

global $wpdb;
$outbox = XSchema::table('outbox');

// ---- Fixture: settings, a fake LRS, cleanup ----
$previous = ['settings' => get_option(Settings::OPTION, false), 'health' => get_option(Processor::HEALTH, false)];
register_shutdown_function(static function () use ($previous) {
    foreach ([Settings::OPTION => $previous['settings'], Processor::HEALTH => $previous['health']] as $name => $value) {
        $value === false ? delete_option($name) : update_option($name, $value, false);
    }
});
add_action('ohmylms_test_cleanup_user', static function ($user) {
    global $wpdb;
    $wpdb->delete(XSchema::table('outbox'), ['student_id' => $user]);
    $wpdb->delete(XSchema::table('registrations'), ['student_id' => $user]);
});

ok(XSchema::install() && XSchema::ready(), 'xAPI schema did not install');
$configure = static function (array $extra = []) {
    update_option(Settings::OPTION, array_merge([
        'enabled' => 1, 'endpoint' => 'https://lrs.example.test/xapi/', 'key' => 'lrs-key', 'secret' => 's3cr3t-value',
        'actor' => 'pseudonym', 'groups' => array_fill_keys(array_keys(Settings::groups()), 1), 'responses' => 1, 'retention_days' => 14,
    ], $extra), false);
};
$configure();
ok(Settings::enabled(), 'Emitter should be enabled');

// The fake LRS: records every request and answers according to $lrs['mode'].
// The test site's isolation plugin blocks all HTTP at PHP_INT_MAX, so this registers at the same
// priority (after it) and answers only for its own made-up host. Every other URL stays blocked.
$lrs = ['mode' => 'ok', 'requests' => [], 'bad_id' => null];
add_filter('pre_http_request', static function ($pre, $args, $url) use (&$lrs) {
    if (strpos($url, 'https://lrs.example.test/') !== 0) { return $pre; }
    $statements = json_decode($args['body'] ?? '[]', true) ?: [];
    $lrs['requests'][] = ['url' => $url, 'method' => $args['method'], 'headers' => $args['headers'], 'redirection' => $args['redirection'], 'statements' => $statements];
    // Asking whether the LRS has a statement: GET /statements?statementId=...
    if ($args['method'] === 'GET' && strpos($url, 'statementId=') !== false) {
        parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);
        $id = $query['statementId'] ?? '';
        $code = ['unclear' => 500, 'drops' => $id === $lrs['bad_id'] ? 404 : 200][$lrs['mode']] ?? 200;
        return ['headers' => [], 'body' => $code === 200 ? wp_json_encode(['id' => $id]) : 'No.', 'response' => ['code' => $code, 'message' => ''], 'cookies' => [], 'filename' => null];
    }
    $code = ['ok' => 200, 'conflict' => 409, 'down' => 503, 'denied' => 401, 'missing' => 404, 'redirect' => 302, 'empty204' => 204, 'auth_required' => 401][$lrs['mode']] ?? 200;
    $is_about = substr($url, -6) === '/about';
    if ($lrs['mode'] === 'auth_required' && $is_about) { $code = 200; }   // /about answers without checking the key, as real LRSs do
    if ($lrs['mode'] === 'no_empty' && !$statements && !$is_about) { $code = 400; }   // an LRS that refuses an empty POST
    if ($lrs['mode'] === 'picky') {
        // A conformant LRS: one invalid statement rejects the whole request.
        $code = 200;
        foreach ($statements as $statement) { if ($statement['id'] === $lrs['bad_id']) { $code = 400; } }
    }
    $ids = array_column($statements, 'id');
    if ($lrs['mode'] === 'drops') {
        // Seen on a real LRS: in a batch it stores the valid statements, drops the invalid one, answers 200
        // with only the stored IDs; the invalid statement on its own is rejected with 400.
        if (count($statements) === 1 && $ids[0] === $lrs['bad_id']) { $code = 400; }
        $ids = array_values(array_diff($ids, [$lrs['bad_id']]));
    }
    if (in_array($lrs['mode'], ['echo_none', 'unclear'], true)) { $ids = []; }   // 200, but the list says nothing was stored
    $body = $code === 200 ? wp_json_encode($ids) : ($code === 400 ? 'Schema violation in statement 0' : ($code === 204 ? '' : 'No.'));
    if (substr($url, -6) === '/about') { $body = '{"version":["1.0.3"]}'; }
    return ['headers' => [], 'body' => $body, 'response' => ['code' => $code, 'message' => ''], 'cookies' => [], 'filename' => null];
}, PHP_INT_MAX, 3);
$reset_lrs = static function ($mode = 'ok') use (&$lrs) { $lrs['mode'] = $mode; $lrs['requests'] = []; $lrs['bad_id'] = null; };
$rows_for = static function ($student, $kind = null) use ($wpdb, $outbox) {
    $sql = $wpdb->prepare("SELECT * FROM $outbox WHERE student_id=%d" . ($kind ? ' AND kind=%s' : '') . ' ORDER BY id', ...array_filter([$student, $kind]));
    return $wpdb->get_results($sql, ARRAY_A);
};
$sent_statements = static function () use (&$lrs) {
    $all = [];
    foreach ($lrs['requests'] as $request) { foreach ($request['statements'] as $statement) { $all[] = $statement; } }
    return $all;
};
$make_due = static function ($student) use ($wpdb, $outbox) {
    $wpdb->query($wpdb->prepare("UPDATE $outbox SET next_attempt_at=%s WHERE student_id=%d AND status='pending'", gmdate('Y-m-d H:i:s', time() - 5), $student));
};

as_user($admin);
$student = make_user('subscriber');
$other = make_user('subscriber');
$course_id = remember_post(call('POST', 'courses', ['name' => 'xAPI course', 'status' => 'publish'])->get_data()['id']);
$lesson_id = remember_post(wp_insert_post(['post_type' => OHMYLMS_LESSON_CPT, 'post_title' => 'xAPI lesson', 'post_status' => 'publish', 'post_content' => 'x']));
enroll($student, $course_id);

// ---- Queuing is idempotent and honours the switches ----
do_action('ohmylms_course_completed', $student, $course_id, 0);
do_action('ohmylms_course_completed', $student, $course_id, 0);
$queued = $rows_for($student, 'course_completed');
ok(count($queued) === 1, 'A repeated event was queued twice: ' . count($queued));
ok($queued[0]['statement_uuid'] === Uuid::v5(Settings::site_id(), 'course-completed:' . $student . ':' . $course_id), 'Statement ID is not derived from the event');
ok($queued[0]['status'] === 'pending' && $queued[0]['statement'] === null, 'A queued row should hold a reference, not a built statement');

$configure(['enabled' => 0]);
do_action('ohmylms_course_completed', $other, $course_id, 0);
ok(!$rows_for($other), 'Disabled emitter still queued an event');
$reset_lrs();
ok(Processor::process() === 0 && !$lrs['requests'], 'Disabled emitter still called the LRS');
$configure(['groups' => ['courses' => 0] + array_fill_keys(array_keys(Settings::groups()), 1)]);
do_action('ohmylms_course_completed', $other, $course_id, 0);
ok(!$rows_for($other), 'A switched-off event group was still queued');
$configure();

do_action('ohmylms_lesson_completed', $lesson_id, $course_id, $student);
ok(count($rows_for($student, 'lesson_completed')) === 1, 'Lesson completion not queued');
$quiz_for_skip = remember_post(call('POST', 'quiz', ['name' => 'xAPI skipped quiz', 'status' => 'publish'])->get_data()['id']);
do_action('ohmylms_lesson_completed', $quiz_for_skip, $course_id, $student);
ok(count($rows_for($student, 'lesson_completed')) === 1, 'A quiz was reported as a lesson (its attempt carries the score)');

// Some callers pass only the order; the enrolment rows say who and which course.
$order_id = 9990000 + random_int(1, 9999);
$wpdb->insert($wpdb->prefix . 'ohmylms_user_enrollment', ['user_id' => $student, 'course_id' => $course_id, 'order_id' => $order_id, 'status' => 'enrolled', 'progress' => 'running', 'start_date' => current_time('mysql')]);
$fixture['enrollments'][] = (int) $wpdb->insert_id;
do_action('ohmylms_after_enrolled_student', $order_id);
do_action('ohmylms_after_enrolled_student', $order_id, $student);
do_action('ohmylms_after_enrolled_student', $order_id, $other);
ok(count($rows_for($student, 'enrolled')) === 1 && !$rows_for($other), 'Enrolment queued wrongly: ' . count($rows_for($student, 'enrolled')));

// Listeners never throw, whatever they are given.
foreach ([[null], ['x'], [[]], [['attempt_id' => 0]]] as $args) {
    \OhMyLMS\XApi\Listeners::attempt_submitted(...$args);
    \OhMyLMS\XApi\Listeners::attempt_graded(...$args);
}
\OhMyLMS\XApi\Listeners::grade_event(1, 'not-an-array');
\OhMyLMS\XApi\Listeners::skill_state(1, 1, null);
ok(true, 'Listeners tolerate unexpected input');

// ---- Statement content and delivery ----
$reset_lrs();
$sent = Processor::process();
ok($sent === 3, "Expected 3 statements delivered, got $sent");
ok(count($lrs['requests']) === 1, 'Statements should travel in one batch: ' . count($lrs['requests']));
$request = $lrs['requests'][0];
ok($request['url'] === 'https://lrs.example.test/xapi/statements' && $request['method'] === 'POST', 'Wrong request: ' . $request['method'] . ' ' . $request['url']);
ok($request['headers']['Authorization'] === 'Basic ' . base64_encode('lrs-key:s3cr3t-value') && $request['headers']['X-Experience-API-Version'] === '1.0.3', 'Missing auth or version header');
ok($request['redirection'] === 0, 'Redirects must not be followed with credentials attached');
$by_verb = [];
foreach ($request['statements'] as $statement) { $by_verb[basename($statement['verb']['id'])][] = $statement; }
ok(isset($by_verb['completed'], $by_verb['registered']) && count($by_verb['completed']) === 2, 'Unexpected verbs: ' . implode(',', array_keys($by_verb)));

$course_done = null; $lesson_done = null;
foreach ($by_verb['completed'] as $statement) {
    if (strpos($statement['object']['id'], '/course/') !== false) { $course_done = $statement; } else { $lesson_done = $statement; }
}
ok($course_done['object']['id'] === home_url('/') . 'xapi/activities/course/' . $course_id && $course_done['object']['definition']['name'][\OhMyLMS\XApi\Activities::language()] === 'xAPI course', 'Course activity wrong: ' . wp_json_encode($course_done['object']));
ok($course_done['object']['definition']['type'] === 'http://adlnet.gov/expapi/activities/course' && $course_done['result'] === ['completion' => true], 'Course type or result wrong');
ok(preg_match('/^[0-9a-f]{64}$/', $course_done['actor']['account']['name']) === 1 && strpos(wp_json_encode($course_done), 'example.invalid') === false && strpos(wp_json_encode($course_done), 'example.com') === false, 'The actor leaks an identity: ' . wp_json_encode($course_done['actor']));
ok($course_done['actor']['account']['homePage'] === untrailingslashit(home_url()), 'Account home page wrong');
ok(Uuid::is_valid($course_done['context']['registration']) && $course_done['context']['platform'] === 'OhMyLMS', 'Context wrong: ' . wp_json_encode($course_done['context']));
ok($course_done['id'] === $queued[0]['statement_uuid'], 'The sent statement does not carry the queued ID');
ok(preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $course_done['timestamp']) === 1 && abs(strtotime($course_done['timestamp']) - time()) < 120, 'Timestamp wrong: ' . $course_done['timestamp']);
ok($lesson_done['object']['definition']['type'] === 'http://adlnet.gov/expapi/activities/lesson' && $lesson_done['context']['contextActivities']['parent'][0]['id'] === $course_done['object']['id'], 'Lesson should sit under its course');
ok($lesson_done['context']['registration'] === $course_done['context']['registration'] && $by_verb['registered'][0]['context']['registration'] === $course_done['context']['registration'], 'One enrolment must use one registration across statements');
$row_after = $rows_for($student, 'course_completed')[0];
ok($row_after['status'] === 'sent' && $row_after['sent_at'] !== null && $row_after['statement'] !== null && $row_after['last_error'] === null, 'Delivered row not settled');
$reset_lrs();
ok(Processor::process() === 0 && !$lrs['requests'], 'A delivered row was sent again');

// A replay is the same statement, so it cannot duplicate in the LRS.
$wpdb->query($wpdb->prepare("DELETE FROM $outbox WHERE id=%d", $row_after['id']));
do_action('ohmylms_course_completed', $student, $course_id, 0);
$replayed = $rows_for($student, 'course_completed')[0];
ok($replayed['statement_uuid'] === $row_after['statement_uuid'], 'A replayed event got a new statement ID');
$wpdb->delete($outbox, ['id' => $replayed['id']]);

// ---- Quiz attempt: completion, outcome and each answer ----
as_user($admin);
[$quiz_course, $quiz_id] = course_with_quiz($admin, ['allow_attempts' => 10]);
$make_question = static function ($name) use ($quiz_id, $wpdb) {
    $data = ['name' => $name, 'quiz_id' => $quiz_id, 'settings' => ['type' => 'single-choice', 'score' => ['enabled' => true, 'value' => 2]],
        'questions' => [['answer' => 'Right', 'is_correct' => 1], ['answer' => 'Wrong']], 'bank' => ['family_id' => 'xapi-' . wp_generate_password(6, false, false)]];
    $created = call('POST', 'question', $data);
    ok(status_of($created) === 201, 'Question create failed: ' . wp_json_encode($created->get_data()));
    $id = remember_post($created->get_data()['id']);
    ok(status_of(call('POST', 'questions/' . $id . '/approve')) === 200, 'Question approve failed');
    return $id;
};
$q1 = $make_question('xAPI Q1');
$q2 = $make_question('xAPI Q2');
enroll($student, $quiz_course);
as_user($student);
$attempt = Submission::start($quiz_id, $student);
ok(is_int($attempt) || ctype_digit((string) $attempt), 'Attempt did not start: ' . wp_json_encode($attempt));
$fixture['attempts'][] = (int) $attempt;
$delivery = array_column(AttemptItems::delivery($attempt), null, 'id');
$tokens = static function ($view) { return array_column($view['questions'], 'id', 'answer'); };
Submission::submit($quiz_id, $attempt, $student, [$q1 => [$tokens($delivery[$q1])['Right']], $q2 => [$tokens($delivery[$q2])['Wrong']]]);

$queued_kinds = array_count_values(array_column($rows_for($student), 'kind'));
ok(($queued_kinds['attempt_completed'] ?? 0) === 1 && ($queued_kinds['attempt_graded'] ?? 0) === 1 && ($queued_kinds['answered'] ?? 0) === 2, 'Attempt events queued wrongly: ' . wp_json_encode($queued_kinds));

$reset_lrs();
Processor::process();
$statements = $sent_statements();
$verbs = array_map(static function ($s) { return basename($s['verb']['id']); }, $statements);
$attempt_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ohmylms_quiz_attempts WHERE id=%d", $attempt), ARRAY_A);
$basis = \OhMyLMS\Assessment\AttemptReport::basis($attempt, ohmylms_get_quiz($quiz_id));
$passed = (float) $attempt_row['total'] >= $basis['passing'];
ok(count(array_keys($verbs, 'answered')) === 2 && count(array_keys($verbs, 'completed')) === 1 && count(array_keys($verbs, $passed ? 'passed' : 'failed')) === 1, 'Attempt verbs wrong: ' . implode(',', $verbs));

$completed = $outcome = null; $answers = [];
foreach ($statements as $statement) {
    $verb = basename($statement['verb']['id']);
    if ($verb === 'completed') { $completed = $statement; }
    if ($verb === 'passed' || $verb === 'failed') { $outcome = $statement; }
    if ($verb === 'answered') { $answers[] = $statement; }
}
ok($completed['object']['definition']['type'] === 'http://adlnet.gov/expapi/activities/assessment', 'A quiz should be an assessment activity');
ok($completed['result']['completion'] === true && !isset($completed['result']['success']), 'The completion statement should not also claim success: ' . wp_json_encode($completed['result']));
ok((float) $completed['result']['score']['raw'] === (float) $attempt_row['total'] && (float) $completed['result']['score']['max'] === $basis['max'], 'Attempt score wrong: ' . wp_json_encode($completed['result']));
ok($outcome['result']['success'] === $passed && $outcome['object']['id'] === $completed['object']['id'], 'Outcome statement wrong: ' . wp_json_encode($outcome['result']));
ok($completed['context']['contextActivities']['parent'][0]['id'] === home_url('/') . 'xapi/activities/course/' . $quiz_course, 'The quiz should sit under its course');

usort($answers, static function ($a, $b) { return strcmp($a['result']['response'], $b['result']['response']); });
$uuid1 = $wpdb->get_var($wpdb->prepare('SELECT uuid FROM ' . \OhMyLMS\Assessment\Schema::table('qb_questions') . ' WHERE question_id=%d', $q1));
ok($answers[0]['result']['response'] === 'Right' && $answers[0]['result']['success'] === true && $answers[1]['result']['response'] === 'Wrong' && $answers[1]['result']['success'] === false, 'Answers should read as the text the learner saw: ' . wp_json_encode(array_column(array_column($answers, 'result'), 'response')));
ok((float) $answers[0]['result']['score']['raw'] === 2.0 && (float) $answers[1]['result']['score']['raw'] === 0.0 && (float) $answers[0]['result']['score']['max'] === 2.0, 'Answer scores wrong');
ok($answers[0]['object']['id'] === home_url('/') . 'xapi/activities/question/' . $uuid1 && $answers[0]['object']['definition']['interactionType'] === 'choice' && $answers[0]['object']['definition']['type'] === 'http://adlnet.gov/expapi/activities/cmi.interaction', 'Question activity wrong: ' . wp_json_encode($answers[0]['object']));
ok($answers[0]['context']['contextActivities']['parent'][0]['id'] === $completed['object']['id'] && $answers[0]['context']['contextActivities']['grouping'][0]['id'] === home_url('/') . 'xapi/activities/course/' . $quiz_course, 'Answer should sit under its quiz and course');
ok($answers[0]['context']['registration'] === $completed['context']['registration'], 'Answer and attempt must share a registration');
ok($answers[0]['context']['extensions']['https://ohmylms.com/xapi/extensions/grader'] === 'auto' && !isset($answers[0]['context']['extensions']['https://ohmylms.com/xapi/extensions/regrade']), 'Grader extension wrong');
ok(strpos(wp_json_encode($answers), '"is_correct"') === false && strpos(wp_json_encode($answers), 'correctResponsesPattern') === false, 'Answers must not expose which option is correct');

// "Include what the learner answered" off: the same event is rebuilt without the response.
$configure(['responses' => 0]);
$answered_row = $rows_for($student, 'answered')[0];
$rebuilt = Materializer::statement($answered_row);
ok(!isset($rebuilt['result']['response']) && isset($rebuilt['result']['score']), 'Responses switch ignored: ' . wp_json_encode($rebuilt['result']));
$configure();

// A teacher regrade: new answer statements say what they supersede, and a new total gives a new outcome.
as_user($admin);
$reset_lrs();
Review::save($quiz_id, $attempt, [$q1 => 2, $q2 => 2], 'Both accepted');
Processor::process();
$statements = $sent_statements();
$regraded = array_values(array_filter($statements, static function ($s) { return basename($s['verb']['id']) === 'answered'; }));
// Review::save records a manual grade for every question the teacher saves, so both answers are reported again.
ok(count($regraded) === 2, 'A regrade should report each saved answer once: ' . count($regraded));
foreach ($regraded as $statement) {
    $extensions = $statement['context']['extensions'];
    ok($extensions['https://ohmylms.com/xapi/extensions/grader'] === 'manual' && $extensions['https://ohmylms.com/xapi/extensions/regrade']['supersedes'] > 0 && $statement['result']['success'] === true && (float) $statement['result']['score']['raw'] === 2.0, 'Regraded answer wrong: ' . wp_json_encode($statement['result']) . wp_json_encode($extensions));
}
$new_outcome = array_values(array_filter($statements, static function ($s) { return in_array(basename($s['verb']['id']), ['passed', 'failed'], true); }));
ok(count($new_outcome) === 1 && (float) $new_outcome[0]['result']['score']['raw'] === 4.0, 'A changed total should report a new outcome: ' . wp_json_encode($new_outcome));

// ---- Skill mastery: only reaching "mastered", once ----
as_user($admin);
$skill = (int) call('POST', 'skills', ['name' => 'xAPI skill ' . wp_generate_password(5, false, false)])->get_data()['id'];
$fixture['terms'][] = [$skill, Taxonomy::NAME];
do_action('ohmylms_skill_state_updated', $student, $skill, ['level' => 'proficient']);
ok(!$rows_for($student, 'skill_mastered'), 'A level below mastered was reported');
do_action('ohmylms_skill_state_updated', $student, $skill, ['level' => 'mastered']);
do_action('ohmylms_skill_state_updated', $student, $skill, ['level' => 'mastered']);
ok(count($rows_for($student, 'skill_mastered')) === 1, 'Mastery should be reported once');
$reset_lrs();
Processor::process();
$mastered = $sent_statements()[0];
ok(basename($mastered['verb']['id']) === 'mastered' && $mastered['object']['definition']['type'] === 'http://adlnet.gov/expapi/activities/objective' && !isset($mastered['context']['registration']), 'Mastery statement wrong: ' . wp_json_encode($mastered));

// ---- Delivery failures ----
$retry_student = make_user('subscriber');
$fresh_course = static function () { return remember_post(wp_insert_post(['post_type' => OHMYLMS_COURSE_CPT, 'post_title' => 'xAPI retry course', 'post_status' => 'publish'])); };

// Server error: the row waits, with backoff, and is delivered when the LRS recovers.
$c1 = $fresh_course();
do_action('ohmylms_course_completed', $retry_student, $c1, 0);
$reset_lrs('down');
ok(Processor::process() === 0 && count($lrs['requests']) === 1, 'LRS outage handling');
$row = $rows_for($retry_student)[0];
ok($row['status'] === 'pending' && (int) $row['tries'] === 1 && strtotime($row['next_attempt_at'] . ' UTC') > time() + 30 && strpos($row['last_error'], '503') !== false, 'Outage should retry with backoff: ' . wp_json_encode($row));
$reset_lrs('down');
ok(Processor::process() === 0 && !$lrs['requests'], 'A row was retried before its backoff elapsed');
$make_due($retry_student);
$reset_lrs();
ok(Processor::process() === 1 && $rows_for($retry_student)[0]['status'] === 'sent', 'Recovery did not deliver the waiting row');

// After MAX_TRIES the row fails and an administrator can requeue it.
$c2 = $fresh_course();
do_action('ohmylms_course_completed', $retry_student, $c2, 0);
$reset_lrs('down');
for ($i = 0; $i < Outbox::MAX_TRIES; $i++) { $make_due($retry_student); Processor::process(); }
$failed = $wpdb->get_row($wpdb->prepare("SELECT * FROM $outbox WHERE student_id=%d AND status='failed'", $retry_student), ARRAY_A);
ok($failed && (int) $failed['tries'] === Outbox::MAX_TRIES, 'A row should fail after ' . Outbox::MAX_TRIES . ' attempts');
ok(Outbox::counts()['failed'] >= 1 && in_array($failed['id'], array_column(Outbox::recent_failed(50), 'id'), true), 'Failed rows not reported');
$reset_lrs();
ok(Outbox::requeue_failed() >= 1, 'Requeue found nothing');
Processor::process();
ok($rows_for($retry_student, 'course_completed')[1]['status'] === 'sent', 'Requeued row was not delivered');

// Bad credentials or a wrong URL pause the queue without using up attempts.
foreach (['denied' => '401', 'missing' => '404', 'redirect' => '302'] as $mode => $code) {
    $c = $fresh_course();
    do_action('ohmylms_course_completed', $retry_student, $c, 0);
    $before = $wpdb->get_row($wpdb->prepare("SELECT id, tries FROM $outbox WHERE student_id=%d AND statement_uuid=%s", $retry_student, Uuid::v5(Settings::site_id(), 'course-completed:' . $retry_student . ':' . $c)), ARRAY_A);
    $reset_lrs($mode);
    ok(Processor::process() === 0 && count($lrs['requests']) === 1, "$mode: one request then pause");
    $held = $wpdb->get_row($wpdb->prepare("SELECT * FROM $outbox WHERE id=%d", $before['id']), ARRAY_A);
    ok($held['status'] === 'pending' && (int) $held['tries'] === 0 && strtotime($held['next_attempt_at'] . ' UTC') > time() + 600 && $held['last_error'] !== '', "$mode should hold without spending attempts: " . wp_json_encode($held));
    ok(!empty(Processor::health()['last_error']), "$mode: the problem is not shown to the administrator");
    $make_due($retry_student);
    $reset_lrs();
    Processor::process();
    ok($wpdb->get_var($wpdb->prepare("SELECT status FROM $outbox WHERE id=%d", $before['id'])) === 'sent', "$mode: held row not delivered after the fix");
}
ok(Processor::health()['last_error'] === '' && !empty(Processor::health()['last_success']), 'Health should clear after a success');

// One rejected statement must not block its batch: find it by sending singly.
$good1 = $fresh_course(); $bad = $fresh_course(); $good2 = $fresh_course();
foreach ([$good1, $bad, $good2] as $c) { do_action('ohmylms_course_completed', $retry_student, $c, 0); }
$reset_lrs('picky');
$lrs['bad_id'] = Uuid::v5(Settings::site_id(), 'course-completed:' . $retry_student . ':' . $bad);
$delivered = Processor::process();
$states = $wpdb->get_col($wpdb->prepare("SELECT status FROM $outbox WHERE student_id=%d AND kind='course_completed' AND statement_uuid IN (%s,%s,%s) ORDER BY id", $retry_student, Uuid::v5(Settings::site_id(), 'course-completed:' . $retry_student . ':' . $good1), $lrs['bad_id'], Uuid::v5(Settings::site_id(), 'course-completed:' . $retry_student . ':' . $good2)));
ok($delivered === 2 && $states === ['sent', 'failed', 'sent'], "Bad statement isolation: delivered $delivered, states " . implode(',', $states));
ok(count($lrs['requests']) === 4 && count($lrs['requests'][0]['statements']) === 3, 'Expected one batch then three single requests: ' . count($lrs['requests']));

// A 200 is not proof of delivery. A real LRS stores the valid statements of a batch, DROPS the invalid
// one, and answers 200 listing only what it stored. That statement must not be marked sent.
$status_of_event = static function ($course) use ($wpdb, $outbox, $retry_student) {
    return $wpdb->get_row($wpdb->prepare("SELECT status, tries, last_error FROM $outbox WHERE statement_uuid=%s", Uuid::v5(Settings::site_id(), 'course-completed:' . $retry_student . ':' . $course)), ARRAY_A);
};
$d1 = $fresh_course(); $d_bad = $fresh_course(); $d2 = $fresh_course();
foreach ([$d1, $d_bad, $d2] as $c) { do_action('ohmylms_course_completed', $retry_student, $c, 0); }
$reset_lrs('drops');
$lrs['bad_id'] = Uuid::v5(Settings::site_id(), 'course-completed:' . $retry_student . ':' . $d_bad);
$delivered = Processor::process();
$dropped = $status_of_event($d_bad);
ok($delivered === 2 && $status_of_event($d1)['status'] === 'sent' && $status_of_event($d2)['status'] === 'sent', "A lenient LRS: the two stored statements should be sent (delivered $delivered)");
ok($dropped['status'] === 'failed', 'A statement the LRS silently dropped was marked ' . $dropped['status'] . ', not failed: ' . wp_json_encode($dropped));
ok(strpos($dropped['last_error'], 'Schema violation') !== false, 'The LRS\'s real reason should be kept: ' . $dropped['last_error']);
ok(count($lrs['requests']) === 3 && $lrs['requests'][1]['method'] === 'GET' && count($lrs['requests'][2]['statements']) === 1, 'Expected the batch, a "do you have it" check, then the lone statement: ' . count($lrs['requests']));

// 200 with an empty list, but the LRS does have the statement (what a replay can look like): delivered.
$e1 = $fresh_course(); $e2 = $fresh_course();
foreach ([$e1, $e2] as $c) { do_action('ohmylms_course_completed', $retry_student, $c, 0); }
$reset_lrs('echo_none');
ok(Processor::process() === 2 && $status_of_event($e1)['status'] === 'sent' && $status_of_event($e2)['status'] === 'sent', 'Statements the LRS already has were not treated as delivered');
ok(count($lrs['requests']) === 3, 'Expected one POST and one check per statement: ' . count($lrs['requests']));

// 200 with an empty list and the check itself fails: unknown, so retry later, never "sent" and never "failed".
$u1 = $fresh_course();
do_action('ohmylms_course_completed', $retry_student, $u1, 0);
$reset_lrs('unclear');
ok(Processor::process() === 0, 'An unconfirmed statement was counted as delivered');
$unclear = $status_of_event($u1);
ok($unclear['status'] === 'pending' && (int) $unclear['tries'] === 1 && strpos($unclear['last_error'], 'confirm') !== false, 'An unconfirmed statement should wait and retry: ' . wp_json_encode($unclear));
$make_due($retry_student);
$reset_lrs();
ok(Processor::process() === 1 && $status_of_event($u1)['status'] === 'sent', 'The unconfirmed statement was not delivered once the LRS recovered');

// An LRS that lists nothing at all (204): nothing to check against, so trust the success as before.
$n1 = $fresh_course(); $n2 = $fresh_course();
foreach ([$n1, $n2] as $c) { do_action('ohmylms_course_completed', $retry_student, $c, 0); }
$reset_lrs('empty204');
ok(Processor::process() === 2 && count($lrs['requests']) === 1, 'A 204 should deliver the batch without extra requests: ' . count($lrs['requests']));

// A conflict means the LRS already has it.
$c = $fresh_course();
do_action('ohmylms_course_completed', $retry_student, $c, 0);
$reset_lrs('conflict');
ok(Processor::process() === 1, 'A 409 (already stored) should count as delivered');

// Content that was deleted before sending is skipped, not retried forever.
$gone_lesson = remember_post(wp_insert_post(['post_type' => OHMYLMS_LESSON_CPT, 'post_title' => 'Short-lived', 'post_status' => 'publish']));
do_action('ohmylms_lesson_completed', $gone_lesson, $course_id, $retry_student);
wp_delete_post($gone_lesson, true);
$reset_lrs();
Processor::process();
$skipped = $wpdb->get_row($wpdb->prepare("SELECT * FROM $outbox WHERE student_id=%d AND status='skipped'", $retry_student), ARRAY_A);
ok($skipped && strpos($skipped['last_error'], 'lesson') !== false && !$lrs['requests'], 'A deleted lesson should be skipped with a reason');

// A learner deleted before sending is skipped too (and nobody else's statements are affected).
$ghost = make_user('subscriber');
do_action('ohmylms_course_completed', $ghost, $course_id, 0);
wp_delete_user($ghost);
$fixture['users'] = array_values(array_diff($fixture['users'], [$ghost]));
Processor::process();
ok($wpdb->get_var($wpdb->prepare("SELECT status FROM $outbox WHERE student_id=%d", $ghost)) === 'skipped', 'A deleted learner should be skipped');
$wpdb->delete($outbox, ['student_id' => $ghost]);

// ---- Housekeeping ----
$wpdb->query($wpdb->prepare("UPDATE $outbox SET sent_at=%s WHERE student_id=%d AND status='sent'", gmdate('Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS), $retry_student));
$before_count = Outbox::counts()['sent'];
ok(Outbox::prune(14) >= 1 && Outbox::counts()['sent'] < $before_count, 'Prune did not remove old delivered rows');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $outbox WHERE student_id=%d AND status='sent'", $student)) >= 1, 'Prune removed recent rows');

// ---- Connection test and settings screen ----
$reset_lrs();
$test = \OhMyLMS\XApi\LrsClient::test();
ok($test['ok'] && strpos($test['message'], '1.0.3') !== false && substr($lrs['requests'][0]['url'], -6) === '/about', 'Connection test wrong: ' . wp_json_encode($test));
ok(strpos($test['message'], 'key is accepted') !== false && count($lrs['requests']) === 2 && $lrs['requests'][1]['method'] === 'POST' && $lrs['requests'][1]['statements'] === [], 'The test should also check the key with an empty POST: ' . wp_json_encode($test));
$reset_lrs('denied');
$test = \OhMyLMS\XApi\LrsClient::test();
ok(!$test['ok'] && $test['message'] !== '', 'A refused connection test should explain itself');
// The "about" resource answers without checking the key (as real LRSs do), so a wrong key must still be caught.
$reset_lrs('auth_required');
$test = \OhMyLMS\XApi\LrsClient::test();
ok(!$test['ok'] && strpos($test['message'], 'key and secret') !== false && strpos($test['message'], 'Connected') === false, 'A wrong key must not report "Connected": ' . wp_json_encode($test));
// An LRS that refuses an empty request: the address works, the key is unproven rather than wrong.
$reset_lrs('no_empty');
$test = \OhMyLMS\XApi\LrsClient::test();
ok($test['ok'] && strpos($test['message'], 'could not be checked') !== false, 'An LRS that refuses an empty POST should say the key is unchecked: ' . wp_json_encode($test));
$reset_lrs();

require_once ABSPATH . 'wp-admin/includes/template.php';
as_user($admin);
ob_start();
AdminPage::render();
$page = ob_get_clean();
ok(strpos($page, 'ohmylms_xapi_save') !== false && strpos($page, 'name="_wpnonce"') !== false && strpos($page, 'lrs.example.test') !== false, 'Settings screen incomplete');
ok(strpos($page, 's3cr3t-value') === false, 'The saved secret was written into the settings page');
ok(strpos($page, 'A secret is saved') !== false, 'The screen should say a secret exists without showing it');

// Saving: a blank secret keeps the stored one; the endpoint is validated; groups and retention are bounded.
$saved = Settings::save(['enabled' => '1', 'endpoint' => 'ftp://nope', 'key' => 'k2', 'secret' => '', 'actor' => 'bogus', 'retention_days' => '9999', 'groups' => ['lessons' => '1']]);
ok($saved['secret'] === 's3cr3t-value' && $saved['endpoint'] === '' && $saved['actor'] === 'pseudonym' && $saved['retention_days'] === 365, 'Settings not validated: ' . wp_json_encode(array_diff_key($saved, ['secret' => 1])));
ok($saved['groups']['lessons'] === 1 && $saved['groups']['answers'] === 0, 'Unticked groups should be off');
ok(!Settings::enabled(), 'An invalid endpoint must leave the emitter off');
$configure();

echo "xapi: " . $wpdb->get_var("SELECT COUNT(*) FROM $outbox") . " outbox rows exercised\n";
