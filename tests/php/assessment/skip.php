<?php
/** Practice runner parity: per-question results for the progress dots, Skip, and the assisted flag. */
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Practice\Sessions;
use OhMyLMS\Skills\Taxonomy;

global $wpdb;
$run = wp_generate_password(6, false, false);
as_user($admin);

$skip_skill = (int) call('POST', 'skills', ['name' => 'Skip skill ' . $run, 'public_practice' => true])->get_data()['id'];
$fixture['terms'][] = [$skip_skill, Taxonomy::NAME];
for ($n = 1; $n <= 6; $n++) {
    $response = call('POST', 'question', ['name' => "Skip q$n $run", 'description' => "Which is right? $n $run",
        'settings' => ['type' => 'single-choice', 'hint' => '<p>First step</p><hr><p>Second step</p>', 'score' => ['enabled' => true, 'value' => 1]],
        'questions' => [['answer' => 'Right', 'is_correct' => 1, 'order_number' => 1], ['answer' => 'Wrong', 'is_correct' => 0, 'order_number' => 2]],
        'skills' => ['p1' => ['primary' => $skip_skill]], 'bank' => ['family_id' => "sk$n-$run"]]);
    ok(status_of($response) === 201, "Creating question $n failed: " . wp_json_encode($response->get_data()));
    $id = remember_post($response->get_data()['id']);
    ok(status_of(call('POST', 'question-bank/' . $id . '/approve')) === 200, "Approving question $n failed");
}

$learner = make_user('subscriber');
as_user($learner);
$pick = static function ($state, $right) {
    $tokens = array_column($state['current']['questions'], 'id', 'answer');
    return [$tokens[$right ? 'Right' : 'Wrong']];
};
$act = static function ($uuid, $path, $body) {
    return call('POST', 'practice/sessions/' . $uuid . '/' . $path, $body);
};
$events = static function ($session_id) use ($wpdb) {
    return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Schema::table('grade_events') . " WHERE source_type='practice' AND source_id=%d", $session_id));
};

// ---- A new session: nothing answered, skipping allowed ----
$started = call('POST', 'practice/sessions', ['term_id' => $skip_skill, 'item_limit' => 3]);
ok(status_of($started) === 201, 'Practice did not start: ' . wp_json_encode($started->get_data()));
$state = $started->get_data();
$session_id = (int) Sessions::get($state['uuid'])['id'];
ok($state['results'] === [] && $state['skipped'] === 0 && $state['can_skip'] === true, 'New session state wrong: ' . wp_json_encode(array_diff_key($state, ['current' => 1])));
ok($state['current']['has_hint'] === true, 'The question lost its hint flag');

// ---- Skip: replaced, not graded, no evidence, not counted ----
$skipped_id = $state['current']['item_id'];
$skipped = $act($state['uuid'], 'skip', ['item_id' => $skipped_id]);
ok(status_of($skipped) === 200, 'Skip failed: ' . wp_json_encode($skipped->get_data()));
$state = $skipped->get_data()['session'];
ok($state['current']['item_id'] !== $skipped_id, 'The skipped question is still current');
ok($state['answered'] === 0 && $state['results'] === [] && $state['skipped'] === 1 && $state['status'] === 'active', 'A skipped question was counted: ' . wp_json_encode(array_diff_key($state, ['current' => 1])));
ok($events($session_id) === 0, 'Skipping wrote a grade event');
$row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Schema::table('practice_items') . ' WHERE id=%d', $skipped_id), ARRAY_A);
ok($row['response'] === null && (int) $row['correct'] === 0 && (int) $row['tries'] === 0 && $row['answered_at'] !== null, 'The skipped row was written as an answer');
ok(strpos((string) $row['display'], 'skipped') !== false, 'The skipped row is not marked');
ok(status_of($act($state['uuid'], 'skip', ['item_id' => $skipped_id])) === 409, 'A skipped question could be skipped twice');
ok(status_of($act($state['uuid'], 'answer', ['item_id' => $skipped_id, 'response' => $pick($state, true)])) === 409, 'A skipped question could still be answered');
ok($state['current']['questions'] && true, 'The replacement has no options');

// ---- Answer right first time, then right after a hint ----
$first = $act($state['uuid'], 'answer', ['item_id' => $state['current']['item_id'], 'response' => $pick($state, true)]);
ok(status_of($first) === 200 && $first->get_data()['correct'] === true && $first->get_data()['assisted'] === false, 'First-try answer wrong: ' . wp_json_encode($first->get_data()['correct'] ?? null));
$state = $first->get_data()['session'];
ok($state['results'] === ['right'] && $state['answered'] === 1 && $state['skipped'] === 1, 'Results after a right answer wrong: ' . wp_json_encode(array_diff_key($state, ['current' => 1])));
$hint = $act($state['uuid'], 'hint', ['item_id' => $state['current']['item_id']]);
ok(status_of($hint) === 200 && strpos($hint->get_data()['hint'], '<hr') !== false, 'The hint did not keep its step break');
$helped = $act($state['uuid'], 'answer', ['item_id' => $state['current']['item_id'], 'response' => $pick($state, true)]);
ok($helped->get_data()['correct'] === true && $helped->get_data()['assisted'] === true, 'An answer after a hint was not marked assisted');
$state = $helped->get_data()['session'];
ok($state['results'] === ['right', 'help'], 'The helped answer is not "help": ' . wp_json_encode($state['results']));
$wrong = $act($state['uuid'], 'answer', ['item_id' => $state['current']['item_id'], 'response' => $pick($state, false)]);
$state = $wrong->get_data()['session'];
ok($state['results'] === ['right', 'help', 'wrong'] && $state['status'] === 'complete' && $state['answered'] === 3 && $state['correct'] === 2, 'The session did not finish on the third counted problem: ' . wp_json_encode(array_diff_key($state, ['current' => 1])));
ok($state['can_skip'] === false, 'A finished session still offers Skip');
ok($events($session_id) === 3, 'Grade events do not match the three answered questions');
ok(status_of($act($state['uuid'], 'skip', ['item_id' => $skipped_id])) === 409, 'Skipped in a finished session');

// ---- Skipping cannot cycle the whole pool ----
$cycle = call('POST', 'practice/sessions', ['term_id' => $skip_skill, 'item_limit' => 3])->get_data();
$skips = 0;
$refused = false;
for ($i = 0; $i < 6 && $cycle['status'] === 'active'; $i++) {
    $result = $act($cycle['uuid'], 'skip', ['item_id' => $cycle['current']['item_id']]);
    if (status_of($result) !== 200) { $refused = status_of($result) === 409; break; }
    $skips++;
    $cycle = $result->get_data()['session'];
}
ok($skips <= 3 && ($refused || $cycle['status'] === 'complete'), 'Skipping went past its limit: ' . $skips . ' skips');

// ---- Lesson style does not offer or allow Skip ----
$lesson = call('POST', 'practice/sessions', ['term_id' => $skip_skill, 'item_limit' => 3, 'style' => 'lesson'])->get_data();
ok($lesson['can_skip'] === false, 'Lesson style offers Skip');
ok(status_of($act($lesson['uuid'], 'skip', ['item_id' => $lesson['current']['item_id']])) === 409, 'A lesson question was skipped');

// ---- Someone else's session ----
$other = make_user('subscriber');
as_user($other);
$fresh = null;
as_user($learner);
$fresh = call('POST', 'practice/sessions', ['term_id' => $skip_skill, 'item_limit' => 3])->get_data();
as_user($other);
ok(in_array(status_of($act($fresh['uuid'], 'skip', ['item_id' => $fresh['current']['item_id']])), [403, 404], true), 'Another learner skipped a question in this session');
as_user($admin);
