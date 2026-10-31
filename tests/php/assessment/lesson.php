<?php
/** Phase 8: fast-feedback lessons (staging, replay of misses) and the AI tutor (provider HTTP mocked). */
use OhMyLMS\AI\Feedback;
use OhMyLMS\AI\Settings;
use OhMyLMS\Assessment\AttemptItems;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Practice\Selector;
use OhMyLMS\Practice\Sessions;
use OhMyLMS\Skills\Evidence;
use OhMyLMS\Skills\Taxonomy;

global $wpdb;
$t = static function ($name) { return Schema::table($name); };
$run = wp_generate_password(6, false, false);
$previous_ai = get_option('ohmylms_ai');
$previous_stats = get_option('ohmylms_ai_stats');
// Start unconfigured, and put the site's own settings back even if this phase stops half way.
delete_option('ohmylms_ai');
delete_option('ohmylms_ai_stats');
register_shutdown_function(static function () use ($previous_ai, $previous_stats) {
    foreach (['ohmylms_ai' => $previous_ai, 'ohmylms_ai_stats' => $previous_stats] as $name => $value) { if ($value === false) { delete_option($name); } else { update_option($name, $value); } }
    delete_option('ohmylms_practice_style');
});
as_user($admin);

$new_skill = static function ($label) use (&$fixture, $run) {
    $created = call('POST', 'skills', ['name' => $label . ' ' . $run, 'public_practice' => true])->get_data();
    $fixture['terms'][] = [$created['id'], Taxonomy::NAME];
    return (int) $created['id'];
};
$add = static function ($settings, $name, $skill, $family, $extra = []) use (&$fixture) {
    $response = call('POST', 'question', array_merge(['name' => $name, 'settings' => array_merge(['score' => ['enabled' => true, 'value' => 1]], $settings),
        'skills' => ['p1' => ['primary' => $skill]], 'bank' => ['family_id' => $family]], $extra));
    ok(status_of($response) === 201, "Create $name failed: " . wp_json_encode($response->get_data()));
    $id = remember_post($response->get_data()['id']);
    ok(status_of(call('POST', 'questions/' . $id . '/approve')) === 200, "Approve $name failed");
    return $id;
};

// ---- A skill with two questions of every stage ----
$skill = $new_skill('Lesson skill');
$add(['type' => 'single-choice'], "Pick right $run", $skill, "l1-$run", ['questions' => [['answer' => 'Right', 'is_correct' => 1, 'order_number' => 1], ['answer' => 'Wrong', 'is_correct' => 0, 'order_number' => 2]], 'description' => "Which is right? $run"]);
$add(['type' => 'dropdown-blanks', 'text' => 'The slope is {1}', 'slots' => [['id' => '1', 'choices' => ['positive', 'negative'], 'answer' => 'positive']]], "Slope $run", $skill, "l2-$run");
$add(['type' => 'number-line', 'min' => 0, 'max' => 10, 'step' => 1, 'target' => 4], "Place 4 $run", $skill, "l3-$run");
$add(['type' => 'build-expression', 'correct' => ['2', '+', '3'], 'distractors' => ['-']], "Build 2+3 $run", $skill, "l4-$run");
$template_id = $add(['type' => 'numerical', 'answer' => 0, 'tolerance' => 0, 'template' => ['variables' => [['name' => 'a', 'type' => 'int', 'min' => 3, 'max' => 12], ['name' => 'b', 'type' => 'int', 'min' => 2, 'max' => 9]], 'constraints' => ['a>b'], 'set' => [['path' => 'answer', 'expr' => 'a*b']]]], "Product $run", $skill, "l5-$run", ['description' => 'What is {{a}} × {{b}}? ' . $run]);
$add(['type' => 'expression', 'answer' => '2(x+3)', 'form' => 'any'], "Expand $run", $skill, "l6-$run", ['description' => "Expand 2(x+3) $run"]);

$student = make_user('subscriber');
as_user($student);

/** Answer the current item of a state, right or wrong, by what its type needs. */
$answer = static function ($state, $right) {
    $view = $state['current'];
    $type = $view['settings']['type'];
    switch ($type) {
        case 'single-choice':
            $tokens = array_column($view['questions'], 'id', 'answer');
            $response = [$tokens[$right ? 'Right' : 'Wrong']];
            break;
        case 'dropdown-blanks': $response = ['1' => $right ? 'positive' : 'negative']; break;
        case 'number-line': $response = ['value' => $right ? '4' : '9']; break;
        case 'build-expression': $response = $right ? ['2', '+', '3'] : ['3', '+']; break;
        case 'expression': $response = [$right ? '2x+6' : '2x']; break;
        case 'numerical':
            preg_match('/(\d+) × (\d+)/', $view['name'] . ' ' . $view['description'], $m);
            $response = [(string) ((int) $m[1] * (int) $m[2] + ($right ? 0 : 1))];
            break;
        default: throw new RuntimeException("Unhandled type $type");
    }
    $result = call('POST', 'practice/sessions/' . $state['uuid'] . '/answer', ['item_id' => $view['item_id'], 'response' => $response]);
    ok(status_of($result) === 200, "Answer to $type failed: " . wp_json_encode($result->get_data()));
    return $result->get_data();
};
$lesson_completed = [];
add_action('ohmylms_practice_lesson_completed', static function ($uuid, $result, $student_id, $term_id) use (&$lesson_completed) { $lesson_completed[] = [$uuid, $result, $student_id, $term_id]; }, 10, 4);

// ---- A lesson: recognise, build, solve; misses come back ----
$started = call('POST', 'practice/sessions', ['term_id' => $skill, 'item_limit' => 6, 'style' => 'lesson']);
ok(status_of($started) === 201, 'Lesson did not start: ' . wp_json_encode($started->get_data()));
$state = $started->get_data();
ok($state['style'] === 'lesson' && $state['planned'] === 6 && $state['replay'] === false && $state['replays'] === 0, 'Lesson state wrong: ' . wp_json_encode(array_diff_key($state, ['current' => 1])));
ok($state['ai'] === ['hint' => false, 'explain' => false], 'AI offered although it is not configured');
$stages = [];
$types = [];
$planned_ids = [];
for ($n = 0; $n < 6; $n++) {
    ok(!empty($state['current']) && $state['replay'] === false, "Planned question $n missing");
    ok(strpos(wp_json_encode($state['current']), '{{') === false && strpos(wp_json_encode($state['current']), 'template') === false, 'Template leaked into the lesson');
    $stages[] = $state['stage'];
    $types[] = $state['current']['settings']['type'];
    $planned_ids[] = $state['current']['item_id'];
    // The last two questions (the "solve" stage) are missed.
    $result = $answer($state, $n < 4);
    ok($result['correct'] === ($n < 4), "Question $n verdict wrong");
    $state = $result['session'];
}
ok($stages === ['recognize', 'recognize', 'guided', 'guided', 'produce', 'produce'], 'Stages out of order: ' . implode(',', $stages) . ' for ' . implode(',', $types));
ok(array_map(['OhMyLMS\Practice\Selector', 'stage'], $types) === $stages, 'Stage labels disagree with the types');
$produced = array_slice($types, 4);
ok(in_array('numerical', $produced, true) && in_array('expression', $produced, true), 'The solve stage should hold the numerical and expression questions: ' . implode(',', $produced));

// Every miss comes back before the lesson ends.
ok($state['status'] === 'active' && !empty($state['current']) && $state['replay'] === true && $state['stage'] === 'replay' && $state['replays'] === 1, 'First replay not issued: ' . wp_json_encode(array_diff_key($state, ['current' => 1])));
$items = Sessions::items(Sessions::get($state['uuid'])['id']);
ok(count($items) === 7 && (int) $items[6]['display']['replay_of'] === (int) $planned_ids[4] && $items[6]['version_id'] === $items[4]['version_id'], 'Replay does not point at the first miss');
$first_miss_type = $types[4];
$replay_view = $state['current'];
ok($replay_view['settings']['type'] === $first_miss_type, 'Replay is a different type of question');
$result = $answer($state, true);
ok($result['correct'] === true, 'Replay answered right was marked wrong');
$state = $result['session'];
ok($state['replay'] === true && $state['replays'] === 2 && !empty($state['current']), 'Second replay not issued');
$result = $answer($state, false);
$state = $result['session'];
ok($state['status'] === 'complete' && $state['ended'] === 'limit' && empty($state['current']), 'Lesson did not end after the replays: ' . wp_json_encode(array_diff_key($state, ['current' => 1])));
$items = Sessions::items(Sessions::get($state['uuid'])['id']);
ok(count($items) === 8 && count(array_filter($items, static function ($item) { return !empty($item['display']['replay_of']); })) === 2, 'Expected six planned items and two replays');
ok($state['planned_right'] === 4 && $state['planned_answered'] === 6 && abs($state['accuracy'] - 0.67) < 0.001, 'Lesson accuracy counts replays: ' . wp_json_encode([$state['planned_right'], $state['planned_answered'], $state['accuracy']]));
ok(count($state['missed']) === 2 && $state['missed'][0]['fixed'] === true && $state['missed'][1]['fixed'] === false && $state['missed'][0]['label'] !== '', 'Missed list wrong: ' . wp_json_encode($state['missed']));
ok(count($lesson_completed) === 1 && $lesson_completed[0][2] === $student && $lesson_completed[0][3] === $skill && $lesson_completed[0][1]['planned_right'] === 4, 'Lesson completion hook did not fire with the result');
ok(status_of(call('POST', 'practice/sessions/' . $state['uuid'] . '/answer', ['item_id' => $planned_ids[0], 'response' => ['x']])) === 409, 'A finished lesson accepted another answer');

// A replayed template question gets new numbers; a replayed plain one is the same question again.
$template_items = array_values(array_filter($items, static function ($item) use ($template_id) { return (int) $item['question_id'] === $template_id; }));
ok(count($template_items) === 2, 'The template question should appear twice (miss and replay)');
$numbers = static function ($item) {
    $s = AttemptItems::snapshot($item);
    preg_match('/(\d+) × (\d+)/', $s->get_description(), $m);
    return $m[1] . 'x' . $m[2];
};
ok($numbers($template_items[0]) !== $numbers($template_items[1]), 'The replay of a template question has the same numbers');
ok(!empty($template_items[1]['display']['replay_of']) && !empty($template_items[1]['display']['instance_seed']) && $template_items[0]['display']['instance_seed'] !== $template_items[1]['display']['instance_seed'], 'Replay did not draw a new seed');

// Evidence: a replay of the same question is not a first try; new numbers are.
Evidence::process();
$evidence = [];
foreach ($wpdb->get_results($wpdb->prepare("SELECT e.first_try, e.question_id, g.item_id FROM {$t('skill_evidence')} e JOIN {$t('grade_events')} g ON g.id=e.grade_event_id WHERE e.student_id=%d AND e.term_id=%d AND e.source_type='practice' AND e.role='primary' AND e.superseded=0", $student, $skill), ARRAY_A) as $row) { $evidence[(int) $row['item_id']] = (int) $row['first_try']; }
$replays = array_values(array_filter($items, static function ($item) { return !empty($item['display']['replay_of']); }));
ok(count($evidence) === 8, 'Every answered question should leave evidence: ' . count($evidence));
foreach ($replays as $replay) {
    $is_template = (int) $replay['question_id'] === $template_id;
    ok($evidence[(int) $replay['id']] === ($is_template ? 1 : 0), 'First-try flag of a ' . ($is_template ? 'template' : 'plain') . ' replay is wrong');
}

// ---- Standard style is unchanged: any order, no replay, exactly the planned count ----
$standard = call('POST', 'practice/sessions', ['term_id' => $skill, 'item_limit' => 3])->get_data();
ok(!isset($standard['style']) && !isset($standard['planned']), 'The standard style gained lesson fields');
$state = $standard;
for ($n = 0; $n < 3; $n++) { $state = $answer($state, false)['session']; }
ok($state['status'] === 'complete' && $state['answered'] === 3 && count(Sessions::items(Sessions::get($state['uuid'])['id'])) === 3, 'Standard style replayed misses');

// ---- A lesson with no mistakes has nothing to replay ----
$perfect = call('POST', 'practice/sessions', ['term_id' => $skill, 'item_limit' => 3, 'style' => 'lesson'])->get_data();
$state = $perfect;
for ($n = 0; $n < 3; $n++) { $state = $answer($state, true)['session']; }
ok($state['status'] === 'complete' && $state['missed'] === [] && $state['planned_right'] === 3 && $state['replays'] === 0, 'A perfect lesson was replayed or miscounted');

// ---- Shortcode and default style ----
update_option('ohmylms_practice_style', 'lesson');
$html = \OhMyLMS\Practice\Frontend::practice(['skill' => $skill]);
ok(strpos($html, 'data-style="lesson"') !== false, 'The site default style is not used');
ok(strpos(\OhMyLMS\Practice\Frontend::practice(['skill' => $skill, 'style' => 'standard']), 'data-style="standard"') !== false && strpos(\OhMyLMS\Practice\Frontend::practice(['skill' => $skill, 'style' => 'whatever']), 'data-style="standard"') !== false, 'The shortcode style attribute is not honoured or not validated');
delete_option('ohmylms_practice_style');

// =========================== The AI tutor ===========================
$KEY = 'sk-ant-TESTKEY-0123456789abcdef';
$ai = static function ($over = []) use ($KEY) {
    return Settings::update(array_merge(['enabled' => '1', 'provider' => 'anthropic', 'model' => 'claude-haiku-5-5', 'key' => $KEY, 'hints' => '1', 'explanations' => '1', 'guests' => '', 'daily_limit' => 50, 'max_tokens' => 300, 'timeout' => 10], $over));
};
$ai();
ok(strpos(wp_json_encode(get_option('ohmylms_ai')), 'TESTKEY') === false && Settings::api_key() === $KEY && Settings::configured(), 'The key is stored in plain text or lost');
ok(strpos(wp_json_encode(Settings::summary()), 'TESTKEY') === false, 'The settings summary leaks the key');

// Mock the provider: record every request, reply as Claude would.
$GLOBALS['ai_calls'] = [];
$GLOBALS['ai_reply'] = 'Think about what the question asks first.';
$GLOBALS['ai_status'] = 200;
$mock = static function ($pre, $args, $url) {
    if (strpos($url, 'api.anthropic.com') === false) { return $pre; }
    $GLOBALS['ai_calls'][] = ['url' => $url, 'headers' => $args['headers'], 'body' => json_decode($args['body'], true), 'raw' => $args['body']];
    if ($GLOBALS['ai_status'] !== 200) {
        return ['response' => ['code' => $GLOBALS['ai_status'], 'message' => 'x'], 'body' => wp_json_encode(['type' => 'error', 'error' => ['message' => 'upstream trouble']])];
    }
    return ['response' => ['code' => 200, 'message' => 'OK'], 'body' => wp_json_encode(['content' => [['type' => 'text', 'text' => $GLOBALS['ai_reply']]], 'usage' => ['input_tokens' => 40, 'output_tokens' => 12]])];
};
// The isolated site blocks every outbound request at PHP_INT_MAX; the mock answers after it, for the provider's host only.
add_filter('pre_http_request', $mock, PHP_INT_MAX, 3);
$calls = static function () { return count($GLOBALS['ai_calls']); };
$ai_call = static function ($uuid, $item_id, $kind) { return call('POST', 'practice/sessions/' . $uuid . '/ai', ['item_id' => $item_id, 'kind' => $kind]); };

// A skill with one plain numerical question, so the expected answer is known and shared by learners.
as_user($admin);
$skill2 = $new_skill('Tutor skill');
$add(['type' => 'numerical', 'answer' => 4217, 'tolerance' => 0, 'explanation' => 'Add the thousands first. ' . $run], "Sum $run", $skill2, "t1-$run", ['description' => "What is 4000 + 217? $run"]);
as_user($student);
$session = call('POST', 'practice/sessions', ['term_id' => $skill2, 'item_limit' => 3, 'style' => 'lesson'])->get_data();
ok($session['ai'] === ['hint' => true, 'explain' => true], 'AI help not offered once configured: ' . wp_json_encode($session['ai']));
$item = $session['current']['item_id'];
$user = get_userdata($student);

// Explaining before answering is not allowed; a hint is.
ok(status_of($ai_call($session['uuid'], $item, 'explain')) === 409 && $calls() === 0, 'An explanation was given before the learner answered');
$hint = $ai_call($session['uuid'], $item, 'hint');
ok(status_of($hint) === 200 && $hint->get_data()['text'] === $GLOBALS['ai_reply'] && $hint->get_data()['cached'] === false && $calls() === 1, 'Hint failed: ' . wp_json_encode($hint->get_data()));
$sent = $GLOBALS['ai_calls'][0];
ok($sent['url'] === 'https://api.anthropic.com/v1/messages' && $sent['headers']['x-api-key'] === $KEY && $sent['body']['model'] === 'claude-haiku-5-5' && $sent['body']['max_tokens'] === 300, 'Provider request malformed');
ok(strpos($sent['raw'], 'TESTKEY') === false, 'The API key is in the request body');
$prompt = $sent['body']['messages'][0]['content'];
ok(strpos($prompt, 'What is 4000 + 217?') !== false && strpos($prompt, '<learner_answer>') !== false, 'The hint prompt lacks the question');
ok(strpos($prompt, '4217') === false && strpos($prompt, 'reference_answer') === false && strpos($prompt, 'Add the thousands first') === false, 'The hint prompt contains the answer or the worked solution');
foreach ([$user->user_login, $user->user_email, $user->display_name, home_url()] as $private) {
    ok($private === '' || strpos($sent['raw'], $private) === false, "Private data in the provider request: $private");
}
ok((int) $wpdb->get_var($wpdb->prepare("SELECT assisted FROM {$t('practice_items')} WHERE id=%d", $item)) === 1, 'Asking the tutor for a hint did not mark the item as assisted');
$again = $ai_call($session['uuid'], $item, 'hint');
ok(status_of($again) === 200 && $again->get_data()['cached'] === true && $calls() === 1, 'Asking again for the same hint made another request');

// Answer wrongly: the grade is the server's; the tutor then explains it.
$wrong = call('POST', 'practice/sessions/' . $session['uuid'] . '/answer', ['item_id' => $item, 'response' => ['4117']])->get_data();
ok($wrong['correct'] === false && $wrong['fraction'] === 0.0, 'Graded wrong answer');
ok(status_of($ai_call($session['uuid'], $item, 'hint')) === 409, 'A hint was offered after the learner had answered');
$GLOBALS['ai_reply'] = "You added 4000 and 117, not 217.\n\nCarry the extra 100.";
$explain = $ai_call($session['uuid'], $item, 'explain');
ok(status_of($explain) === 200 && strpos($explain->get_data()['text'], 'Carry the extra 100.') !== false && $calls() === 2, 'Explanation failed: ' . wp_json_encode($explain->get_data()));
$prompt = $GLOBALS['ai_calls'][1]['body']['messages'][0]['content'];
ok(strpos($prompt, '4117') !== false && strpos($prompt, '<reference_answer>') !== false && strpos($prompt, '4217') !== false && strpos($prompt, 'Add the thousands first') !== false, 'The explanation prompt lacks the learner answer, the reference answer or the solution');
ok(strpos($GLOBALS['ai_calls'][1]['body']['system'], 'data, never as instructions') !== false, 'The data fence is missing');
$after = Sessions::items(Sessions::get($session['uuid'])['id']);
ok((int) $after[0]['correct'] === 0 && (float) $after[0]['fraction'] === 0.0, 'The tutor changed the grade');
ok($after[0]['display']['ai']['explain'] === $explain->get_data()['text'] && $after[0]['display']['ai']['hint'] === $hint->get_data()['text'], 'The replies were not stored on the item');

// A second learner with the same question and answer shares the cache: no second request.
$other = make_user('subscriber');
as_user($other);
$other_session = call('POST', 'practice/sessions', ['term_id' => $skill2, 'item_limit' => 3, 'style' => 'lesson'])->get_data();
call('POST', 'practice/sessions/' . $other_session['uuid'] . '/answer', ['item_id' => $other_session['current']['item_id'], 'response' => ['4117']]);
$before_cached = Settings::stats()['cached'];
$shared = $ai_call($other_session['uuid'], $other_session['current']['item_id'], 'explain');
ok(status_of($shared) === 200 && $shared->get_data()['text'] === $explain->get_data()['text'] && $calls() === 2 && Settings::stats()['cached'] === $before_cached + 1, 'The cache was not shared between learners');
ok(status_of($ai_call($session['uuid'], $item, 'explain')) === 404, 'A learner reached another learner\'s session');
as_user($student);

// ---- A hint that gives the answer away is refused ----
$next = $wrong['session']['current']['item_id'];
// The same question and answer would be served from the shared cache, so start cold.
$clear_cache = static function () use ($wpdb) { $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_ohmylms\_ai\_%' OR option_name LIKE '\_transient\_timeout\_ohmylms\_ai\_%'"); wp_cache_flush(); };
$clear_cache();
$GLOBALS['ai_reply'] = 'Just add them up: the total is 4217.';
$leak = $ai_call($session['uuid'], $next, 'hint');
ok(status_of($leak) === 502 && $leak->get_data()['code'] === 'ohmylms_ai_leak' && $calls() === 3, 'A leaking hint was delivered: ' . wp_json_encode($leak->get_data()));
ok((int) $wpdb->get_var($wpdb->prepare("SELECT assisted FROM {$t('practice_items')} WHERE id=%d", $next)) === 0, 'A refused hint still marked the item as assisted');
ok(strpos(wp_json_encode($leak->get_data()), '4217') === false, 'The refusal repeats the answer');

// ---- A provider failure never blocks practice and never shows the key ----
$clear_cache();
$GLOBALS['ai_status'] = 500;
$failed_before = Settings::stats()['failures'];
$down = $ai_call($session['uuid'], $next, 'hint');
ok(status_of($down) === 502 && $down->get_data()['code'] === 'ohmylms_ai_unavailable' && strpos(wp_json_encode($down->get_data()), 'TESTKEY') === false && Settings::stats()['failures'] === $failed_before + 1, 'Provider failure not handled: ' . wp_json_encode($down->get_data()));
// A broken question cannot be retried endlessly: the leak above and two failures used the three attempts.
ok(status_of($ai_call($session['uuid'], $next, 'hint')) === 502, 'Second failed attempt not reported');
$calls_before = $calls();
$blocked = $ai_call($session['uuid'], $next, 'hint');
ok(status_of($blocked) === 429 && $blocked->get_data()['code'] === 'ohmylms_ai_item_limit' && $calls() === $calls_before, 'The attempts per item are not limited: ' . wp_json_encode($blocked->get_data()));$GLOBALS['ai_status'] = 200;
$right = call('POST', 'practice/sessions/' . $session['uuid'] . '/answer', ['item_id' => $next, 'response' => ['4217']]);
ok(status_of($right) === 200 && $right->get_data()['correct'] === true, 'Practice stopped working after an AI failure');

// ---- Switches and limits ----
as_user($admin);
$ai(['hints' => '']);
as_user($student);
ok(Feedback::availability(Sessions::get($session['uuid'])) === ['hint' => false, 'explain' => true], 'The hint switch is ignored');
as_user($admin); $ai(['explanations' => '', 'hints' => '1']); as_user($student);
$state_now = call('GET', 'practice/sessions/' . $session['uuid'])->get_data();
ok($state_now['ai'] === ['hint' => true, 'explain' => false], 'The state does not follow the settings');
as_user($admin); $ai(['enabled' => '']); as_user($student);
ok(call('GET', 'practice/sessions/' . $session['uuid'])->get_data()['ai'] === ['hint' => false, 'explain' => false] && status_of($ai_call($session['uuid'], $next, 'explain')) === 404, 'AI is available while switched off');
as_user($admin); $ai(['guests' => '']); as_user($student);
ok(Feedback::availability(['student_id' => 0, 'guest_id' => 5]) === ['hint' => false, 'explain' => false], 'Guests got tutor help without being allowed');
as_user($admin); $ai(['guests' => '1']); as_user($student);
ok(Feedback::availability(['student_id' => 0, 'guest_id' => 5]) === ['hint' => true, 'explain' => true], 'Guests cannot get tutor help when allowed');
as_user($admin); $ai(['guests' => '']); as_user($student);

// The daily limit counts provider requests, not cached replies.
$usage = get_user_meta($student, 'ohmylms_ai_usage', true);
ok($usage['count'] === 2, 'Daily usage should count only the two helpful replies, not the refused or failed ones: ' . wp_json_encode($usage));
as_user($admin); $ai(['daily_limit' => 2]); as_user($student);
$fresh = call('POST', 'practice/sessions', ['term_id' => $skill2, 'item_limit' => 3, 'style' => 'lesson'])->get_data();
$limited = $ai_call($fresh['uuid'], $fresh['current']['item_id'], 'hint');
ok(status_of($limited) === 429 && $limited->get_data()['code'] === 'ohmylms_ai_daily', 'The daily limit was not enforced: ' . wp_json_encode($limited->get_data()));
ok(status_of(call('POST', 'practice/sessions/' . $fresh['uuid'] . '/answer', ['item_id' => $fresh['current']['item_id'], 'response' => ['4217']])) === 200, 'Practice stopped at the daily limit');

// ---- Another provider and a bad key ----
as_user($admin);
$ai(['provider' => 'openai', 'model' => 'some-model', 'daily_limit' => 50, 'key' => '']);
remove_filter('pre_http_request', $mock, PHP_INT_MAX);
add_filter('pre_http_request', static function ($pre, $args, $url) {
    if (strpos($url, 'api.openai.com') === false) { return $pre; }
    $GLOBALS['ai_calls'][] = ['url' => $url, 'headers' => $args['headers'], 'body' => json_decode($args['body'], true)];
    return ['response' => ['code' => 401, 'message' => 'x'], 'body' => wp_json_encode(['error' => ['message' => 'Incorrect API key provided']])];
}, PHP_INT_MAX, 3);
as_user($student);
$fresh2 = call('POST', 'practice/sessions', ['term_id' => $skill2, 'item_limit' => 3, 'style' => 'lesson'])->get_data();
$bad = $ai_call($fresh2['uuid'], $fresh2['current']['item_id'], 'hint');
$last = end($GLOBALS['ai_calls']);
ok($last['url'] === 'https://api.openai.com/v1/responses' && $last['headers']['authorization'] === 'Bearer ' . $KEY && status_of($bad) === 502 && $bad->get_data()['code'] === 'ohmylms_ai_auth' && strpos(wp_json_encode($bad->get_data()), 'TESTKEY') === false, 'OpenAI path or key handling wrong: ' . wp_json_encode($bad->get_data()));


// A standalone inline-check shortcode opens in the editor as a question block; anything else is left alone.
$uuid_a = 'aaaaaaaa-1111-4222-8333-bbbbbbbbbbbb';
$uuid_b = 'cccccccc-1111-4222-8333-dddddddddddd';
$lookup = static function ($uuid) use ($uuid_a) { return $uuid === $uuid_a ? 4321 : 0; };
$as_block = '<!-- wp:ohmylms/shared-multiple-choice {"questionId":4321,"uuid":"' . $uuid_a . '"} /-->';
$converted = \OhMyLMS\Practice\Inline::blocks_from_shortcodes("<!-- wp:shortcode -->\n[ohmylms_question uuid=\"$uuid_a\"]\n<!-- /wp:shortcode -->", $lookup);
ok($converted === $as_block, 'A shortcode block did not become a question block: ' . $converted);
$paragraph = "<!-- wp:paragraph -->\n<p>[ohmylms_question uuid=\"$uuid_a\"]</p>\n<!-- /wp:paragraph -->";
ok(\OhMyLMS\Practice\Inline::blocks_from_shortcodes($paragraph, $lookup) === $as_block, 'A paragraph holding only a check did not become a question block');
$curly = "<!-- wp:paragraph -->\n<p>[ohmylms_question uuid=&#8221;$uuid_a&#8221;]</p>\n<!-- /wp:paragraph -->";
ok(\OhMyLMS\Practice\Inline::blocks_from_shortcodes($curly, $lookup) === $as_block, 'A check with curly quotes did not become a question block');
$inside = "<!-- wp:paragraph -->\n<p>Try this: [ohmylms_question uuid=\"$uuid_a\"] then go on.</p>\n<!-- /wp:paragraph -->";
ok(\OhMyLMS\Practice\Inline::blocks_from_shortcodes($inside, $lookup) === $inside, 'A check inside a sentence was changed');
$unknown = "<!-- wp:shortcode -->\n[ohmylms_question uuid=\"$uuid_b\"]\n<!-- /wp:shortcode -->";
ok(\OhMyLMS\Practice\Inline::blocks_from_shortcodes($unknown, $lookup) === $unknown, 'A check for an unknown question was changed');
ok(\OhMyLMS\Practice\Inline::blocks_from_shortcodes('<p>plain</p>', $lookup) === '<p>plain</p>', 'Plain content was changed');
