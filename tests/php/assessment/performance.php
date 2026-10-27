<?php
/** Unified skill tracking: policies, settings, activities that count, level changes, rebuilds and AI notes. */
use OhMyLMS\AI\Secrets;
use OhMyLMS\AI\Settings as AiSettings;
use OhMyLMS\Assessment\GradeEvents;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Performance\Engine;
use OhMyLMS\Performance\Insights;
use OhMyLMS\Performance\Levels;
use OhMyLMS\Performance\Settings;
use OhMyLMS\Performance\Snapshot;
use OhMyLMS\Practice\Sessions;
use OhMyLMS\Skills\Evidence;
use OhMyLMS\Skills\Taxonomy;

global $wpdb;
$run = wp_generate_password(6, false, false);
$previous = [
    'performance' => get_option(Settings::OPTION, null),
    'ai' => get_option(AiSettings::OPTION, null),
];
register_shutdown_function(static function () use ($previous) {
    foreach (['performance' => Settings::OPTION, 'ai' => AiSettings::OPTION] as $key => $option) {
        if ($previous[$key] === null) { delete_option($option); } else { update_option($option, $previous[$key]); }
    }
});
delete_option(Settings::OPTION);

// ---- Settings ----
ok(Settings::get()['policy'] === 'evidence' && Settings::get()['show_score'] === true && Settings::get()['ai_insights'] === false, 'The defaults are wrong');
update_option(Settings::OPTION, ['policy' => 'nonsense']);
ok(Settings::get()['policy'] === 'evidence', 'An invalid stored policy was accepted');
ok(is_wp_error(Settings::validate(['policy' => 'nonsense'])) && is_wp_error(Settings::validate('steps')) && !is_wp_error(Settings::validate(['policy' => 'steps'])), 'Setting validation wrong');
ok(array_keys(Settings::policies()) === ['evidence', 'steps', 'score'], 'The built-in policies are not registered: ' . implode(',', array_keys(Settings::policies())));
ok(!Settings::retries(), 'Retry is on with the evidence policy');
as_user($admin);
$saved = call('POST', 'engagement/settings/mastery', ['policy' => 'bogus']);
ok(status_of($saved) === 400, 'The route accepted a bad policy');
$payload = call('GET', 'engagement/settings/mastery')->get_data();
ok(count($payload['policies']) === 3 && in_array('exam', array_column($payload['surface_options'], 'id'), true) && in_array('practice', array_column($payload['surface_options'], 'id'), true) && array_key_exists('ai_ready', $payload), 'The settings payload is missing parts: ' . wp_json_encode(array_keys($payload)));
$learner = make_user('subscriber');
as_user($learner);
ok(in_array(status_of(call('POST', 'engagement/settings/mastery', ['policy' => 'steps'])), [401, 403], true), 'A learner changed the policy');
as_user($admin);

// ---- A skill with questions ----
$skill = (int) call('POST', 'skills', ['name' => 'Performance skill ' . $run, 'public_practice' => true])->get_data()['id'];
$fixture['terms'][] = [$skill, Taxonomy::NAME];
for ($n = 1; $n <= 8; $n++) {
    $response = call('POST', 'question', ['name' => "Perf q$n $run", 'description' => "Pick $n $run",
        'settings' => ['type' => 'single-choice', 'hint' => '<p>One</p>', 'explanation' => '<p>Because.</p>', 'score' => ['enabled' => true, 'value' => 1]],
        'questions' => [['answer' => 'Right', 'is_correct' => 1, 'order_number' => 1], ['answer' => 'Wrong', 'is_correct' => 0, 'order_number' => 2]],
        'skills' => ['p1' => ['primary' => $skill]], 'bank' => ['family_id' => "pf$n-$run"]]);
    ok(status_of($response) === 201, "Creating question $n failed: " . wp_json_encode($response->get_data()));
    $id = remember_post($response->get_data()['id']);
    ok(status_of(call('POST', 'question-bank/' . $id . '/approve')) === 200, "Approving question $n failed");
}
as_user($learner);
$act = static function ($uuid, $path, $body) { return call('POST', 'practice/sessions/' . $uuid . '/' . $path, $body); };
$tokens = static function ($state, $right) { return [array_column($state['current']['questions'], 'id', 'answer')[$right ? 'Right' : 'Wrong']]; };
$state_row = static function () use ($wpdb, $learner, $skill) {
    return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Schema::table('student_skill_state') . ' WHERE student_id=%d AND term_id=%d', $learner, $skill), ARRAY_A);
};
$play = static function ($right_count, $total = 3) use ($act, $tokens, $skill) {
    $state = call('POST', 'practice/sessions', ['term_id' => $skill, 'item_limit' => $total])->get_data();
    for ($n = 0; $n < $total; $n++) {
        $step = $act($state['uuid'], 'answer', ['item_id' => $state['current']['item_id'], 'response' => $tokens($state, $n < $right_count)]);
        $state = $step->get_data()['session'] ?? $step->get_data();
        if (!empty($step->get_data()['retry'])) { return [$step->get_data(), true]; }
    }
    return [$state, false];
};

// ---- The evidence policy: the state is one row with a level, a mastery score and the counts ----
[$state] = $play(3);
ok($state['status'] === 'complete', 'The session did not finish: ' . wp_json_encode(array_diff_key($state, ['current' => 1])));
ok(!empty($state['levels']) && $state['levels'][0]['before'] === 'not-started' && $state['levels'][0]['change'] === 'up', 'The summary does not say what the practice did to the level: ' . wp_json_encode($state['levels'] ?? null));
$row = $state_row();
ok($row && $row['policy'] === 'evidence' && $row['level'] === 'proficient' && (int) $row['evidence_count'] === 3 && (int) $row['scored'] === 3 && (float) $row['mastery'] > 0, 'The unified state row is wrong: ' . wp_json_encode($row));
ok((float) $row['accuracy'] === 1.0, 'The accuracy was not stored');
$snapshot = Snapshot::student($learner, [$skill, $skill + 1000000]);
ok($snapshot[$skill]['key'] === 'proficient' && $snapshot[$skill]['policy'] === 'evidence' && $snapshot[$skill + 1000000]['key'] === 'not-started', 'The snapshot is wrong: ' . wp_json_encode($snapshot));
ok(Snapshot::counts($learner)['proficient'] === 1 && Snapshot::started_terms($learner) === [$skill], 'The counts and started skills are wrong');
$skills = Snapshot::skills($learner);
ok(count($skills) === 1 && $skills[0]['level_label'] === 'Proficient' && $skills[0]['evidence_count'] === 3, 'The skill list is wrong: ' . wp_json_encode($skills));
ok(array_keys(Snapshot::students([$learner], [])) === [$learner] && Snapshot::students([0], [$skill]) === [0 => [$skill => ['key' => 'not-started', 'label' => 'Not started', 'score' => null, 'policy' => 'none']]], 'Empty and anonymous snapshots are wrong');
$logged = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Schema::table('skill_level_events') . ' WHERE student_id=%d AND term_id=%d', $learner, $skill));
ok($logged === 1, 'The level change was not logged once: ' . $logged);
Evidence::process();
Engine::recompute($learner, $skill);
ok((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Schema::table('skill_level_events') . ' WHERE student_id=%d AND term_id=%d', $learner, $skill)) === 1, 'Recomputing logged the same activity twice');
ok(count(Engine::changes($learner, 'practice', (int) Sessions::get($state['uuid'])['id'])) === 1, 'The change list for the activity is missing');

// ---- Activities that count ----
$before = (int) $row['evidence_count'];
update_option(Settings::OPTION, ['policy' => 'evidence', 'surfaces' => ['practice' => false]]);
ok(!Settings::counts('practice', 1) && Settings::counts('inline', 1), 'The practice surface was not switched off alone');
$play(3);
Evidence::process();
ok((int) $state_row()['evidence_count'] === $before, 'Practice that does not count still became skill evidence');
update_option(Settings::OPTION, ['policy' => 'evidence', 'surfaces' => []]);
ok(Settings::counts('practice', 1) && Settings::counts('quiz', 0) && Settings::counts('exam', 0, 'exam'), 'Surfaces do not count by default');
$saved_exam = Settings::validate(['surfaces' => ['exam' => false, 'nonsense' => false]]);
ok($saved_exam['surfaces'] === ['exam' => false], 'Unknown surfaces were kept: ' . wp_json_encode($saved_exam['surfaces']));

// ---- Changing policy rebuilds every state ----
as_user($admin);
$saved = call('POST', 'engagement/settings/mastery', ['policy' => 'steps']);
ok(status_of($saved) === 200 && Settings::get()['policy'] === 'steps' && Settings::retries(), 'Choosing the step policy failed: ' . wp_json_encode($saved->get_data()));
ok((bool) wp_next_scheduled('ohmylms_performance_rebuild', ['states', 0]), 'A policy change did not queue a rebuild');
wp_clear_scheduled_hook('ohmylms_performance_rebuild', ['states', 0]);
$cursor = 0;
do { $cursor = Engine::rebuild_states($cursor, 50); } while ($cursor);
$row = $state_row();
ok($row['policy'] === 'steps' && in_array($row['level'], ['familiar', 'proficient'], true), 'The rebuild did not apply the new policy: ' . wp_json_encode($row));
as_user($learner);

// ---- The step policy: practice, retry, quizzes ----
[$state] = $play(3);
$row = $state_row();
ok($row['level'] === 'proficient', 'A perfect exercise did not reach proficient under the step policy: ' . wp_json_encode($row));
ok($state['levels'][0]['after'] === 'proficient', 'The summary does not show the new level: ' . wp_json_encode($state['levels']));
$state = call('POST', 'practice/sessions', ['term_id' => $skill, 'item_limit' => 3])->get_data();
ok($state['retry'] === true, 'Retry is not offered under the step policy');
$wrong = $act($state['uuid'], 'answer', ['item_id' => $state['current']['item_id'], 'response' => $tokens($state, false)]);
ok($wrong->get_data()['retry'] === true && strpos(wp_json_encode($wrong->get_data()), 'Because') === false, 'A wrong first answer did not offer another try');
$state = Sessions::state(Sessions::get($state['uuid']));
$fixed = $act($state['uuid'], 'answer', ['item_id' => $state['current']['item_id'], 'response' => $tokens($state, true)]);
$state = $fixed->get_data()['session'];
foreach ([1, 2] as $n) {
    $step = $act($state['uuid'], 'answer', ['item_id' => $state['current']['item_id'], 'response' => $tokens($state, true)]);
    $state = $step->get_data()['session'];
}
ok($state['status'] === 'complete' && $state['levels'][0]['change'] === 'same' && $state_row()['level'] === 'proficient', 'A weaker exercise lowered the level: ' . wp_json_encode($state['levels'] ?? null));
// Quizzes (graded answers from a quiz) move the level one step.
$version = (int) $wpdb->get_var($wpdb->prepare('SELECT version_id FROM ' . Schema::table('practice_items') . ' WHERE session_id=%d LIMIT 1', (int) Sessions::get($state['uuid'])['id']));
$question = (int) $wpdb->get_var($wpdb->prepare('SELECT question_id FROM ' . Schema::table('practice_items') . ' WHERE version_id=%d LIMIT 1', $version));
$grade_quiz = static function ($attempt, $flags) use ($learner, $version, $question) {
    foreach ($flags as $i => $flag) {
        GradeEvents::record(['source_type' => 'quiz', 'source_id' => $attempt, 'item_id' => 9000 + $attempt * 10 + $i, 'student_id' => $learner, 'question_id' => $question, 'version_id' => $version,
            'awarded' => $flag ? 1 : 0, 'max_marks' => 1, 'fraction' => $flag ? 1 : 0, 'correct' => $flag, 'grader' => 'auto', 'reason' => 'submitted']);
    }
    Evidence::process();
};
$base = 900000 + (int) $learner;
$grade_quiz($base + 1, [true, true]);
ok($state_row()['level'] === 'mastered', 'All right on a quiz did not move proficient to mastered: ' . $state_row()['level']);
$grade_quiz($base + 2, [true, false]);
ok($state_row()['level'] === 'proficient', 'A miss on a quiz did not move mastered to proficient: ' . $state_row()['level']);
Evidence::process();
ok((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Schema::table('skill_level_events') . " WHERE student_id=%d AND term_id=%d AND source_type='quiz'", $learner, $skill)) === 2, 'The quiz activities were not logged once each');
ok(Engine::changes($learner, 'quiz', $base + 2)[0]['change'] === 'down' && Engine::changes($learner, 'quiz', $base + 1)[0]['change'] === 'up', 'The quiz change list is wrong: ' . wp_json_encode([Engine::changes($learner, 'quiz', $base + 1), Engine::changes($learner, 'quiz', $base + 2)]));
// A regrade replaces the old result exactly once.
$old = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . Schema::table('grade_events') . " WHERE source_type='quiz' AND source_id=%d AND superseded_by=0 AND correct=0 LIMIT 1", $base + 2));
ok($old > 0, 'The missed quiz answer was not found');
GradeEvents::record(['source_type' => 'quiz', 'source_id' => $base + 2, 'item_id' => 9000 + ($base + 2) * 10 + 1, 'student_id' => $learner, 'question_id' => $question, 'version_id' => $version,
    'awarded' => 1, 'max_marks' => 1, 'fraction' => 1, 'correct' => 1, 'grader' => 'manual', 'reason' => 'regrade']);
Evidence::process();
ok($state_row()['level'] === 'mastered', 'A regrade did not restore the level: ' . $state_row()['level']);

// ---- The score policy ----
as_user($admin);
call('POST', 'engagement/settings/mastery', ['policy' => 'score']);
wp_clear_scheduled_hook('ohmylms_performance_rebuild', ['states', 0]);
Engine::rebuild_states(0, 1000);
$row = $state_row();
ok($row['policy'] === 'score' && in_array($row['level'], Levels::SCALE, true) && (float) $row['mastery'] > 0, 'The score policy row is wrong: ' . wp_json_encode($row));
ok(!Settings::retries(), 'Retry stayed on after leaving the step policy');

// ---- AI notes: off by default, de-identified, cached, capped ----
call('POST', 'engagement/settings/mastery', ['policy' => 'evidence']);
wp_clear_scheduled_hook('ohmylms_performance_rebuild', ['states', 0]);
update_option(Settings::OPTION, ['policy' => 'evidence', 'ai_insights' => false]);
$off = Insights::note($learner, 'student', $learner);
ok(is_wp_error($off) && $off->get_error_code() === 'ohmylms_insight_off', 'A note was written with AI notes switched off');
update_option(Settings::OPTION, ['policy' => 'evidence', 'ai_insights' => true]);
update_option(AiSettings::OPTION, ['enabled' => true, 'provider' => 'anthropic', 'model' => 'test-model', 'key' => Secrets::seal('test-key-not-real'), 'daily_limit' => 2, 'max_tokens' => 400, 'timeout' => 5]);
ok(Insights::available(), 'Notes are not available with a provider set up');
$sent = [];
$calls = 0;
// The isolated site blocks every outgoing request; this test answers the one provider call itself and blocks the rest.
remove_all_filters('pre_http_request');
add_filter('pre_http_request', static function ($pre, $args, $url) use (&$sent, &$calls) {
    if (strpos($url, 'api.anthropic.com') === false) { return new WP_Error('blocked', 'External HTTP is blocked'); }
    ++$calls;
    $sent[] = $args['body'];
    return ['response' => ['code' => 200, 'message' => 'OK'], 'headers' => [], 'body' => wp_json_encode(['content' => [['type' => 'text', 'text' => "**You** are doing well with this skill.\nTry five more questions today."]], 'usage' => ['input_tokens' => 10, 'output_tokens' => 12]])];
}, 1000, 3);
$facts = Insights::facts($learner);
ok(count($facts['skills']) === 1 && $facts['skills'][0]['level'] && strpos(wp_json_encode($facts), (string) get_userdata($learner)->user_email) === false && strpos(wp_json_encode($facts), get_userdata($learner)->user_login) === false, 'The facts name the learner or are empty: ' . wp_json_encode($facts));
$note = Insights::note($learner, 'student', $learner);
ok(!is_wp_error($note) && $note['cached'] === false && strpos($note['text'], 'doing well') !== false && strpos($note['text'], '**') === false, 'The note was not written or not cleaned: ' . wp_json_encode($note));
ok($calls === 1 && strpos($sent[0], get_userdata($learner)->user_email) === false && strpos($sent[0], get_userdata($learner)->user_login) === false && strpos($sent[0], 'test-key-not-real') === false && strpos($sent[0], '<skills>') !== false, 'The request leaks identity or lacks the fenced facts');
$again = Insights::note($learner, 'student', $learner);
ok(!is_wp_error($again) && $again['cached'] === true && $calls === 1 && $again['text'] === $note['text'], 'An unchanged learner was written about twice');
$teacher_note = Insights::note($learner, 'teacher', $admin);
ok(!is_wp_error($teacher_note) && $calls === 2, 'The teacher note was not written');
// The daily cap is per reader: the learner already used one request, a second would be allowed, a third is not.
$wpdb->delete(Schema::table('skill_evidence'), ['student_id' => $learner, 'source_type' => 'nonexistent']);
update_user_meta($learner, Insights::META . 'student', []);
$second = Insights::note($learner, 'student', $learner);
$third_blocked = (function () use ($learner) { update_user_meta($learner, Insights::META . 'student', []); return Insights::note($learner, 'student', $learner); })();
ok(!is_wp_error($second) && is_wp_error($third_blocked) && $third_blocked->get_error_code() === 'ohmylms_insight_limit', 'The daily cap did not hold: ' . wp_json_encode($third_blocked));
ok(is_wp_error(Insights::note($learner, 'parent', $learner)), 'An unknown audience was accepted');
$nobody = make_user('subscriber');
$empty = Insights::note($nobody, 'student', $nobody);
ok(is_wp_error($empty) && $empty->get_error_code() === 'ohmylms_insight_empty', 'A learner with no activity got a note');
remove_all_filters('pre_http_request');

// ---- The report route: who may ask for which note ----
delete_transient('ohmylms_perf_ai_' . $learner . '_' . gmdate('Ymd'));
delete_transient('ohmylms_perf_ai_' . $admin . '_' . gmdate('Ymd'));
add_filter('pre_http_request', static function ($pre, $args, $url) {
    return strpos($url, 'api.anthropic.com') === false ? new WP_Error('blocked', 'External HTTP is blocked') : ['response' => ['code' => 200, 'message' => 'OK'], 'headers' => [], 'body' => wp_json_encode(['content' => [['type' => 'text', 'text' => 'A note.']], 'usage' => []])];
}, 1000, 3);
update_user_meta($learner, Insights::META . 'student', []);
as_user($learner);
$mine = call('POST', 'reports/skills/students/' . $learner . '/insight', ['audience' => 'student']);
ok(status_of($mine) === 200 && $mine->get_data()['text'] === 'A note.', 'A learner could not ask for their own note: ' . wp_json_encode($mine->get_data()));
ok(status_of(call('POST', 'reports/skills/students/' . $learner . '/insight', ['audience' => 'teacher'])) === 403, 'A learner asked for the teacher note about themselves');
$other = make_user('subscriber');
as_user($other);
ok(in_array(status_of(call('POST', 'reports/skills/students/' . $learner . '/insight', ['audience' => 'student'])), [401, 403], true), 'Another learner asked for a note about someone else');
ok(in_array(status_of(call('POST', 'reports/skills/students/' . $learner . '/insight', ['audience' => 'teacher'])), [401, 403], true), 'Another learner asked for a teacher note');
as_user($admin);
ok(status_of(call('POST', 'reports/skills/students/' . $learner . '/insight', ['audience' => 'teacher'])) === 200, 'An administrator could not ask for the teacher note');
ok(status_of(call('POST', 'reports/skills/students/' . $learner . '/insight', ['audience' => 'student'])) === 403, 'An administrator wrote a note as the learner');
ok(status_of(call('POST', 'reports/skills/students/' . $learner . '/insight', ['audience' => 'nonsense'])) === 400, 'An unknown audience was accepted by the route');
ok(call('GET', 'reports/skills/students/' . $learner)->get_data()['insights'] === true, 'The report does not say notes are available');
remove_all_filters('pre_http_request');

// ---- Rebuilding from the evidence ----
$processed = Evidence::rebuild($learner);
ok($processed > 0 && $state_row() && (int) $state_row()['evidence_count'] >= 3, 'Rebuilding the learner left no state: ' . wp_json_encode($state_row()));
$wpdb->delete(Schema::table('skill_level_events'), ['student_id' => $learner]);
$wpdb->delete(Schema::table('student_skill_state'), ['student_id' => $learner]);
$wpdb->delete(Schema::table('skill_evidence'), ['student_id' => $learner]);
ok(Snapshot::counts($learner)['total'] === 0, 'Cleaning the learner left state behind');
