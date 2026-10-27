<?php
/**
 * xAPI emitter against a REAL LRS (manual smoke test; never part of a normal run).
 *
 *   OHMYLMS_TEST_CREDENTIALS=/path/test-credentials.json \
 *   OHMYLMS_LRS_CREDENTIALS=/path/lrs.json   # {"endpoint":"https://…/xapi","key":"…","secret":"…"}
 *   php tests/php/assessment-integration.php xapi-live
 *
 * Use a throwaway LRS. The isolated test site blocks all HTTP at PHP_INT_MAX, so this registers a
 * pass-through at the same priority that performs real requests to the LRS host only. Everything
 * else stays blocked. Only fake test users are involved; statements stay in the LRS afterwards.
 */
use OhMyLMS\Assessment\AttemptItems;
use OhMyLMS\Quiz\Review;
use OhMyLMS\Quiz\Submission;
use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\XApi\Identity;
use OhMyLMS\XApi\LrsClient;
use OhMyLMS\XApi\Outbox;
use OhMyLMS\XApi\Processor;
use OhMyLMS\XApi\Schema as XSchema;
use OhMyLMS\XApi\Settings;
use OhMyLMS\XApi\Uuid;

global $wpdb;
$outbox = XSchema::table('outbox');
$lrs_file = getenv('OHMYLMS_LRS_CREDENTIALS');
if (!$lrs_file || !is_file($lrs_file)) { throw new RuntimeException('Set OHMYLMS_LRS_CREDENTIALS to a JSON file with endpoint, key and secret.'); }
$lrs = json_decode(file_get_contents($lrs_file), true);
foreach (['endpoint', 'key', 'secret'] as $field) { ok(!empty($lrs[$field]), "Credentials file lacks $field"); }
$lrs_host = wp_parse_url($lrs['endpoint'], PHP_URL_HOST);
$ca_bundle = ABSPATH . WPINC . '/certificates/ca-bundle.crt';

// ---- Pass-through to the LRS host only, with a log that never records credentials ----
$http = [];
add_filter('pre_http_request', static function ($pre, $args, $url) use (&$http, $lrs_host, $ca_bundle) {
    if (wp_parse_url($url, PHP_URL_HOST) !== $lrs_host) { return $pre; }
    $headers = [];
    foreach ($args['headers'] as $name => $value) { $headers[] = $name . ': ' . $value; }
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $args['method'], CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => (int) $args['timeout'], CURLOPT_FOLLOWLOCATION => false, CURLOPT_CAINFO => $ca_bundle]);
    if (isset($args['body'])) { curl_setopt($ch, CURLOPT_POSTFIELDS, $args['body']); }
    $started = microtime(true);
    $raw = curl_exec($ch);
    $elapsed = (int) round((microtime(true) - $started) * 1000);
    if ($raw === false) { $error = curl_error($ch); curl_close($ch); $http[] = [$args['method'], wp_parse_url($url, PHP_URL_PATH), 0, $elapsed]; return new WP_Error('http_request_failed', $error); }
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $head = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $http[] = [$args['method'], wp_parse_url($url, PHP_URL_PATH), $code, $elapsed];
    return ['headers' => [], 'body' => substr($raw, $head), 'response' => ['code' => $code, 'message' => ''], 'cookies' => [], 'filename' => null];
}, PHP_INT_MAX, 3);

// Direct, authenticated read of the LRS (bypasses OhMyLMS) to see what it actually stored.
$lrs_get = static function ($path, array $query = []) use ($lrs, $ca_bundle) {
    $url = rtrim($lrs['endpoint'], '/') . '/' . ltrim($path, '/') . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CAINFO => $ca_bundle,
        CURLOPT_HTTPHEADER => ['X-Experience-API-Version: 1.0.3', 'Authorization: Basic ' . base64_encode($lrs['key'] . ':' . $lrs['secret'])]]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, json_decode((string) $body, true), (string) $body];
};
/** Differences where $sent is not contained in $stored (an LRS may add fields, and may re-format numbers). */
$missing = static function ($sent, $stored, $path = '') use (&$missing) {
    $diffs = [];
    if (is_array($sent)) {
        if (!is_array($stored)) { return [$path . ' is not an object/array in the LRS']; }
        foreach ($sent as $key => $value) {
            if (!array_key_exists($key, $stored)) { $diffs[] = $path . '/' . $key . ' missing in the LRS'; continue; }
            $diffs = array_merge($diffs, $missing($value, $stored[$key], $path . '/' . $key));
        }
        return $diffs;
    }
    if ($path === '/timestamp' && strtotime((string) $sent) !== false) { return strtotime((string) $sent) === strtotime((string) $stored) ? [] : [$path . ' differs: sent ' . $sent . ', stored ' . $stored]; }
    if (is_numeric($sent) && is_numeric($stored)) { return abs((float) $sent - (float) $stored) < 1e-9 ? [] : [$path . ' differs: sent ' . $sent . ', stored ' . $stored]; }
    return $sent === $stored ? [] : [$path . ' differs: sent ' . wp_json_encode($sent) . ', stored ' . wp_json_encode($stored)];
};

// ---- Fixture and cleanup ----
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
$configure = static function ($secret = null) use ($lrs) {
    update_option(Settings::OPTION, ['enabled' => 1, 'endpoint' => $lrs['endpoint'], 'key' => $lrs['key'], 'secret' => $secret ?? $lrs['secret'], 'actor' => 'pseudonym',
        'groups' => array_fill_keys(array_keys(Settings::groups()), 1), 'responses' => 1, 'retention_days' => 14], false);
};
$configure();
$report = [];
$note = static function ($line) use (&$report) { $report[] = $line; };

// ---- 1. Connection test through our own client ----
$test = LrsClient::test();
ok($test['ok'] && strpos($test['message'], 'key is accepted') !== false, 'Connection test failed or did not verify the key: ' . $test['message']);
$note('Connection test: ' . $test['message']);

// ---- 2. A realistic flow with fake users ----
as_user($admin);
$student = make_user('subscriber');
$course_id = remember_post(call('POST', 'courses', ['name' => 'Smoke-test course', 'status' => 'publish'])->get_data()['id']);
$lesson_id = remember_post(wp_insert_post(['post_type' => OHMYLMS_LESSON_CPT, 'post_title' => 'Smoke-test lesson', 'post_status' => 'publish', 'post_content' => 'x']));
[$quiz_course, $quiz_id] = course_with_quiz($admin, ['allow_attempts' => 10]);
$make_question = static function ($name) use ($quiz_id) {
    $created = call('POST', 'question', ['name' => $name, 'quiz_id' => $quiz_id, 'settings' => ['type' => 'single-choice', 'score' => ['enabled' => true, 'value' => 2]],
        'questions' => [['answer' => 'Right', 'is_correct' => 1], ['answer' => 'Wrong']], 'bank' => ['family_id' => 'live-' . wp_generate_password(6, false, false)]]);
    ok(status_of($created) === 201, 'Question create failed');
    $id = remember_post($created->get_data()['id']);
    ok(status_of(call('POST', 'question-bank/' . $id . '/approve')) === 200, 'Question approve failed');
    return $id;
};
$q1 = $make_question('Smoke Q1');
$q2 = $make_question('Smoke Q2');
enroll($student, $course_id);
enroll($student, $quiz_course);

$order_id = 9980000 + random_int(1, 9999);
$wpdb->insert($wpdb->prefix . 'ohmylms_user_enrollment', ['user_id' => $student, 'course_id' => $course_id, 'order_id' => $order_id, 'status' => 'enrolled', 'progress' => 'running', 'start_date' => current_time('mysql')]);
$fixture['enrollments'][] = (int) $wpdb->insert_id;
do_action('ohmylms_after_enrolled_student', $order_id);
do_action('ohmylms_lesson_completed', $lesson_id, $course_id, $student);
do_action('ohmylms_course_completed', $student, $course_id, 0);
as_user($student);
$attempt = Submission::start($quiz_id, $student);
$fixture['attempts'][] = (int) $attempt;
$delivery = array_column(AttemptItems::delivery($attempt), null, 'id');
$tokens = static function ($view) { return array_column($view['questions'], 'id', 'answer'); };
Submission::submit($quiz_id, $attempt, $student, [$q1 => [$tokens($delivery[$q1])['Right']], $q2 => [$tokens($delivery[$q2])['Wrong']]]);
as_user($admin);
Review::save($quiz_id, $attempt, [$q1 => 2, $q2 => 2], 'Both accepted');
as_user($admin);
$skill = (int) call('POST', 'skills', ['name' => 'Smoke skill ' . wp_generate_password(5, false, false)])->get_data()['id'];
$fixture['terms'][] = [$skill, Taxonomy::NAME];
do_action('ohmylms_skill_state_updated', $student, $skill, ['level' => 'mastered']);

$queued = $wpdb->get_results($wpdb->prepare("SELECT id, kind, statement_uuid FROM $outbox WHERE student_id=%d ORDER BY id", $student), ARRAY_A);
$kinds = array_count_values(array_column($queued, 'kind'));
$expected_kinds = ['enrolled' => 1, 'lesson_completed' => 1, 'course_completed' => 1, 'attempt_completed' => 1, 'attempt_graded' => 2, 'answered' => 4, 'skill_mastered' => 1];
ksort($kinds); ksort($expected_kinds);
ok($kinds === $expected_kinds, 'Queued events differ from the flow: ' . wp_json_encode($kinds));
$note('Queued ' . count($queued) . ' statements: ' . wp_json_encode($kinds));

$http = [];
$delivered = Processor::process();
ok($delivered === count($queued), "Delivered $delivered of " . count($queued) . '; health: ' . wp_json_encode(Processor::health()));
$status = array_count_values($wpdb->get_col($wpdb->prepare("SELECT status FROM $outbox WHERE student_id=%d", $student)));
ok($status === ['sent' => count($queued)], 'Outbox not fully sent: ' . wp_json_encode($status));
$post = array_values(array_filter($http, static function ($h) { return $h[0] === 'POST'; }));
ok(count($post) === 1 && $post[0][2] === 200, 'Expected one accepted batch POST, got ' . wp_json_encode($post));
$note(sprintf('Delivery: %d statements in %d request (HTTP %d, %d ms)', $delivered, count($post), $post[0][2], $post[0][3]));

// ---- 3. Read back what the LRS stored and compare with what we sent ----
$rows = $wpdb->get_results($wpdb->prepare("SELECT kind, statement_uuid, statement FROM $outbox WHERE student_id=%d ORDER BY id", $student), ARRAY_A);
$all_diffs = [];
$stored_by_kind = [];
foreach ($rows as $row) {
    [$code, $stored] = $lrs_get('statements', ['statementId' => $row['statement_uuid']]);
    ok($code === 200 && is_array($stored) && ($stored['id'] ?? '') === $row['statement_uuid'], "LRS does not have {$row['kind']} statement (HTTP $code)");
    $sent = json_decode($row['statement'], true);
    $diffs = $missing($sent, $stored);
    foreach ($diffs as $diff) { $all_diffs[] = $row['kind'] . $diff; }
    ok(!empty($stored['stored']) && !empty($stored['authority']), "LRS did not add stored/authority to {$row['kind']}");
    $stored_by_kind[$row['kind']][] = $stored;
}
$note('Read back ' . count($rows) . ' of ' . count($rows) . ' statements by ID; differences from what was sent: ' . ($all_diffs ? count($all_diffs) : 'none'));
foreach (array_slice($all_diffs, 0, 12) as $diff) { $note('  DIFF ' . $diff); }
ok(!$all_diffs, 'The LRS stored statements differently from what was sent: ' . implode('; ', array_slice($all_diffs, 0, 5)));

$actor = Identity::actor($student);
[$code, $by_agent] = $lrs_get('statements', ['agent' => wp_json_encode($actor), 'limit' => 100]);
ok($code === 200 && count($by_agent['statements'] ?? []) === count($rows), 'Query by learner returned ' . count($by_agent['statements'] ?? []) . " statements (HTTP $code), expected " . count($rows));
$note('Query by learner (pseudonymous account) returned all ' . count($rows) . ' statements');
$any = $stored_by_kind['answered'][0];
ok(preg_match('/^[0-9a-f]{64}$/', $any['actor']['account']['name']) === 1 && strpos(wp_json_encode($by_agent), '@') === false, 'A learner identity leaked into the LRS');
$responses = array_map(static function ($s) { return $s['result']['response'] ?? ''; }, $stored_by_kind['answered']);
sort($responses);
ok($responses === ['Right', 'Right', 'Wrong', 'Wrong'], 'Answers did not arrive as the text the learner saw: ' . wp_json_encode($responses));
$note('Answers stored as readable text: ' . wp_json_encode($responses));

// ---- 4. Replaying a statement the LRS already has ----
$replay = json_decode($rows[0]['statement'], true);
$http = [];
$again = LrsClient::post([$replay]);
ok($again['class'] === 'ok', 'A replay was not treated as delivered: ' . wp_json_encode($again));
$note('Replay of an already-stored statement: HTTP ' . $again['status'] . ' (treated as delivered)');
[$code, $by_agent_after] = $lrs_get('statements', ['agent' => wp_json_encode($actor), 'limit' => 100]);
ok(count($by_agent_after['statements'] ?? []) === count($rows), 'The replay created a duplicate in the LRS');
$note('No duplicate created by the replay');

// ---- 5. One invalid statement in a batch: does the LRS reject all of it? ----
$fresh = static function ($label) use ($actor) {
    return ['id' => Uuid::v4(), 'actor' => $actor, 'verb' => \OhMyLMS\XApi\Vocabulary::verb('completed'), 'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        'object' => ['objectType' => 'Activity', 'id' => home_url('/') . 'xapi/activities/lesson/smoke-' . strtolower($label), 'definition' => ['type' => 'http://adlnet.gov/expapi/activities/lesson', 'name' => ['en-US' => 'Smoke ' . $label]]]];
};
// How does this LRS treat a batch that contains one invalid statement? The spec says: reject all of it.
$probe_a = $fresh('probe-A'); $probe_bad = $fresh('probe-B'); unset($probe_bad['verb']); $probe_c = $fresh('probe-C');
$batch = LrsClient::post([$probe_a, $probe_bad, $probe_c]);
[$probe_a_code] = $lrs_get('statements', ['statementId' => $probe_a['id']]);
if ($batch['class'] === 'reject') {
    ok($probe_a_code === 404, 'The LRS stored a valid statement from a rejected batch (HTTP ' . $probe_a_code . ')');
    $note('Invalid statement in a batch: HTTP ' . $batch['status'] . ' for the whole batch, nothing stored (all-or-nothing, as the spec says)');
} else {
    ok($batch['class'] === 'ok' && is_array($batch['stored']) && count($batch['stored']) === 2 && !in_array($probe_bad['id'], $batch['stored'], true) && $probe_a_code === 200,
        'Unexpected answer to a batch with an invalid statement: ' . wp_json_encode($batch));
    $note('Invalid statement in a batch: this LRS answers HTTP ' . $batch['status'] . ', stores the 2 valid statements, silently DROPS the invalid one and lists only the 2 stored IDs (the spec says to reject the whole batch), so a 200 alone is not proof of delivery');
}

// Through the real processor: whatever the LRS does, a statement it did not store must never be marked sent,
// and the valid ones must arrive.
$good_a = $fresh('A'); $bad = $fresh('B'); unset($bad['verb']); $good_c = $fresh('C');
foreach ([[$good_a, 'course_completed'], [$bad, 'course_completed'], [$good_c, 'course_completed']] as $i => [$statement, $kind]) {
    $wpdb->insert($outbox, ['statement_uuid' => $statement['id'], 'student_id' => $student, 'kind' => $kind, 'ref' => '{}', 'occurred_at' => gmdate('Y-m-d H:i:s'), 'statement' => wp_json_encode($statement),
        'status' => 'pending', 'tries' => 0, 'next_attempt_at' => gmdate('Y-m-d H:i:s', time() - 5), 'created_at' => gmdate('Y-m-d H:i:s')]);
}
$http = [];
$isolated = Processor::process();
ok($isolated === 2, "Isolation delivered $isolated of 2 good statements");
[$code_a] = $lrs_get('statements', ['statementId' => $good_a['id']]);
[$code_c] = $lrs_get('statements', ['statementId' => $good_c['id']]);
[$code_b] = $lrs_get('statements', ['statementId' => $bad['id']]);
ok($code_a === 200 && $code_c === 200 && $code_b === 404, "After isolation the LRS has A=$code_a C=$code_c B=$code_b");
$bad_row = $wpdb->get_row($wpdb->prepare("SELECT status, last_error FROM $outbox WHERE statement_uuid=%s", $bad['id']), ARRAY_A);
ok($bad_row['status'] === 'failed' && $bad_row['last_error'] !== '', 'The bad statement was not marked failed: ' . wp_json_encode($bad_row));
$note(sprintf('Processor isolation: 2 good delivered, bad one failed (%d requests); reason kept: "%s"', count($http), substr($bad_row['last_error'], 0, 90)));

// ---- 6. Wrong credentials and a wrong URL: the queue pauses without losing anything ----
$configure($lrs['secret'] . 'x');
$bad_test = LrsClient::test();
ok(!$bad_test['ok'] && strpos($bad_test['message'], 'key and secret') !== false, 'A wrong secret must fail the connection test: ' . wp_json_encode($bad_test));
$note('Wrong secret, connection test: ' . $bad_test['message'] . ' (the LRS answers /about without checking credentials, so the test also posts an empty list)');
ok(!$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $outbox WHERE student_id=%d AND status='pending'", $student)), 'Rows were still pending before the credentials test');
$held_statement = $fresh('D');
$wpdb->insert($outbox, ['statement_uuid' => $held_statement['id'], 'student_id' => $student, 'kind' => 'course_completed', 'ref' => '{}', 'occurred_at' => gmdate('Y-m-d H:i:s'), 'statement' => wp_json_encode($held_statement),
    'status' => 'pending', 'tries' => 0, 'next_attempt_at' => gmdate('Y-m-d H:i:s', time() - 5), 'created_at' => gmdate('Y-m-d H:i:s')]);
$held_id = (int) $wpdb->insert_id;
$http = [];
$sent_bad = Processor::process();
$held = $wpdb->get_row($wpdb->prepare("SELECT status, tries, next_attempt_at, last_error FROM $outbox WHERE id=%d", $held_id), ARRAY_A);
$note('Wrong secret, real POST: HTTP ' . ($http[0][2] ?? '?') . ', row ' . $held['status'] . ', attempts used ' . $held['tries'] . ', message "' . substr($held['last_error'], 0, 80) . '"');
ok($sent_bad === 0 && $held['status'] === 'pending', 'A refused credential should leave the row waiting');
ok((int) $held['tries'] === 0 && strtotime($held['next_attempt_at'] . ' UTC') > time() + 600, 'A refused credential should pause without using attempts: ' . wp_json_encode($held));
$configure();
$wpdb->update($outbox, ['next_attempt_at' => gmdate('Y-m-d H:i:s', time() - 5)], ['id' => $held_id]);
ok(Processor::process() === 1, 'The held statement was not delivered after the credentials were fixed');
$note('Credentials fixed: the held statement was delivered');

$settings_now = get_option(Settings::OPTION);
$settings_now['endpoint'] = rtrim($lrs['endpoint'], '/') . '/definitely-not-here';
update_option(Settings::OPTION, $settings_now, false);
$bad_url = LrsClient::test();
$note('Wrong endpoint path, connection test: ' . ($bad_url['ok'] ? 'UNEXPECTEDLY OK' : $bad_url['message']));
$http = [];
$wrong = LrsClient::post([$fresh('E')]);
$note('Wrong endpoint path, POST: HTTP ' . $wrong['status'] . ' classified "' . $wrong['class'] . '"');
$configure();

echo "\n===== LIVE LRS SMOKE TEST REPORT (" . $lrs_host . ") =====\n";
foreach ($report as $line) { echo '- ' . $line . "\n"; }
echo "=====\n";
