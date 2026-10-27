<?php
/** Phase 9: XP, the daily goal, the 0-100 mastery score and the lesson reward summary. */
use OhMyLMS\Engagement\SessionReward;
use OhMyLMS\Engagement\Streak;
use OhMyLMS\Engagement\StreakSchema;
use OhMyLMS\Engagement\StreakSettings;
use OhMyLMS\Engagement\Xp;
use OhMyLMS\Engagement\XpSchema;
use OhMyLMS\Engagement\XpSettings;
use OhMyLMS\Practice\Sessions;
use OhMyLMS\Skills\Evidence;
use OhMyLMS\Skills\Score;
use OhMyLMS\Skills\ScoreModel;
use OhMyLMS\Skills\Taxonomy;

global $wpdb;
$run = wp_generate_password(6, false, false);
// Put the site's own settings back even if this phase stops half way.
$saved = [];
foreach (['ohmylms_xp_settings', 'ohmylms_score_settings', 'ohmylms_streak_settings'] as $name) { $saved[$name] = get_option($name); }
register_shutdown_function(static function () use ($saved) {
    foreach ($saved as $name => $value) { if ($value === false) { delete_option($name); } else { update_option($name, $value); } }
    remove_all_filters('ohmylms_streak_clock');
});
$set = static function ($name, array $values) { update_option($name, $values); };
$xp_settings = static function (array $over = []) use ($set) { $set('ohmylms_xp_settings', array_merge(XpSettings::defaults(), ['enable' => true], $over)); };
$score_settings = static function (array $over = []) use ($set) { $set('ohmylms_score_settings', array_merge(Score::defaults(), ['enable' => true], $over)); };
$clock = 1791374400; // Wednesday 2026-10-07 12:00 UTC
add_filter('ohmylms_streak_clock', static function () use (&$clock) { return $clock; });

as_user($admin);

// ---- Settings: validated, installed on enabling, read back ----
$bad = call('POST', 'engagement/settings/xp', ['enable' => true, 'lesson' => -5]);
ok(status_of($bad) === 400, 'Negative XP accepted');
$bad = call('POST', 'engagement/settings/xp', ['enable' => true, 'goals' => [10, 20], 'default_goal' => 15]);
ok(status_of($bad) === 400, 'A default goal outside the goal list accepted');
$bad = call('POST', 'engagement/settings/xp', ['enable' => 'yes']);
ok(status_of($bad) === 400, 'A non-boolean switch accepted');
$bad = call('POST', 'engagement/settings/score', ['enable' => true, 'bands' => [['gain' => 1, 'loss' => 1]]]);
ok(status_of($bad) === 400, 'An incomplete band list accepted');
$bad = call('POST', 'engagement/settings/score', ['enable' => true, 'unlock' => 20]);
ok(status_of($bad) === 400, 'An unlock score below 50 accepted');
$good = call('POST', 'engagement/settings/xp', array_merge(XpSettings::defaults(), ['enable' => true, 'goals' => [30, 10, 20, 20]]));
ok(status_of($good) === 200 && XpSchema::ready() && XpSettings::get()['goals'] === [10, 20, 30], 'Valid XP settings not saved or storage not installed: ' . wp_json_encode($good->get_data()));
$good = call('POST', 'engagement/settings/score', array_merge(Score::defaults(), ['enable' => true]));
ok(status_of($good) === 200 && Score::enabled() && call('GET', 'engagement/settings/score')->get_data()['enable'] === true && call('GET', 'engagement/settings/xp')->get_data()['default_goal'] === 20, 'Valid score settings not saved');
as_user($student_probe = make_user('subscriber'));
ok(status_of(call('POST', 'engagement/settings/xp', XpSettings::defaults())) === 403, 'A learner changed the XP settings');
as_user($admin);

// ---- A skill with one endless template question ----
$created = call('POST', 'skills', ['name' => "XP skill $run", 'public_practice' => true])->get_data();
$fixture['terms'][] = [$created['id'], Taxonomy::NAME];
$skill = (int) $created['id'];
$question = call('POST', 'question', ['name' => 'Product', 'description' => 'What is {{a}} × {{b}}? ' . $run, 'settings' => ['type' => 'numerical', 'answer' => 0, 'tolerance' => 0, 'score' => ['enabled' => true, 'value' => 1],
    'template' => ['variables' => [['name' => 'a', 'type' => 'int', 'min' => 3, 'max' => 12], ['name' => 'b', 'type' => 'int', 'min' => 2, 'max' => 9]], 'constraints' => ['a>b'], 'set' => [['path' => 'answer', 'expr' => 'a*b']]]],
    'skills' => ['p1' => ['primary' => $skill]], 'bank' => ['family_id' => "xp-$run"]]);
ok(status_of($question) === 201, 'Template question failed: ' . wp_json_encode($question->get_data()));
$qid = remember_post($question->get_data()['id']);
ok(status_of(call('POST', 'question-bank/' . $qid . '/approve')) === 200, 'Approve failed');

$student = make_user('subscriber');
as_user($student);
$goals_met = [];
$milestones = [];
add_action('ohmylms_xp_goal_met', static function ($who, $date, $goal) use (&$goals_met) { $goals_met[] = [$who, $date, $goal]; }, 10, 3);
add_action('ohmylms_skill_score_milestone', static function ($who, $term, $at, $medal) use (&$milestones) { $milestones[] = [$who, $term, $at, $medal]; }, 10, 4);

/** Run one lesson of four questions; $rights says which answers are right. Returns the final state. */
$lesson = static function (array $rights, $style = 'lesson') use ($skill) {
    $state = call('POST', 'practice/sessions', ['term_id' => $skill, 'item_limit' => count($rights), 'style' => $style])->get_data();
    foreach ($rights as $right) {
        ok(!empty($state['current']), 'The lesson ran out of questions');
        preg_match('/(\d+) × (\d+)/', $state['current']['name'] . ' ' . $state['current']['description'], $m);
        $answer = call('POST', 'practice/sessions/' . $state['uuid'] . '/answer', ['item_id' => $state['current']['item_id'], 'response' => [(string) ((int) $m[1] * (int) $m[2] + ($right ? 0 : 1))]]);
        ok(status_of($answer) === 200, 'Answer failed: ' . wp_json_encode($answer->get_data()));
        $state = $answer->get_data()['session'];
    }
    // A lesson replays its misses once: answer those right, as a learner who has understood would.
    while ($state['status'] === 'active' && !empty($state['current'])) {
        preg_match('/(\d+) × (\d+)/', $state['current']['name'] . ' ' . $state['current']['description'], $m);
        $state = call('POST', 'practice/sessions/' . $state['uuid'] . '/answer', ['item_id' => $state['current']['item_id'], 'response' => [(string) ((int) $m[1] * (int) $m[2])]])->get_data()['session'];
    }
    return $state;
};
$stored_score = static function () use ($wpdb, $student, $skill) { return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . \OhMyLMS\Assessment\Schema::table('student_skill_state') . ' WHERE student_id=%d AND term_id=%d', $student, $skill), ARRAY_A); };

// ---- Lesson 1: four right. XP 10 + 5; score 9 per answer in the learning band ----
$one = $lesson([true, true, true, true]);
ok($one['status'] === 'complete' && $one['reward']['xp']['xp'] === 15 && $one['reward']['xp']['parts'] === ['lesson' => 10, 'perfect' => 5], 'Lesson 1 XP wrong: ' . wp_json_encode($one['reward'] ?? null));
ok($one['reward']['xp']['total'] === 15 && $one['reward']['xp']['today'] === 15 && $one['reward']['xp']['goal'] === 20 && $one['reward']['xp']['goal_met'] === false && $goals_met === [], 'Lesson 1 daily goal wrong');
ok($one['reward']['score']['before'] === 0.0 && $one['reward']['score']['after'] === 36.0 && $one['reward']['score']['delta'] === 36.0 && $one['reward']['score']['unlock'] === 80 && $one['reward']['score']['skill'] === "XP skill $run", 'Lesson 1 score wrong: ' . wp_json_encode($one['reward']['score']));
$row = $stored_score();
ok((float) $row['mastery'] === 36.0 && (int) $row['scored'] === 4 && (int) $row['correct'] === 4 && $row['milestones'] === '', 'Stored score wrong: ' . wp_json_encode($row));
$event = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . XpSchema::table('xp_events') . " WHERE student_id=%d AND event_key=%s", $student, 'practice:' . $one['uuid']), ARRAY_A);
ok($event && (int) $event['xp'] === 15 && $event['local_date'] === '2026-10-07' && (int) $event['term_id'] === $skill && $event['source_type'] === 'practice', 'The XP event is wrong: ' . wp_json_encode($event));

// Paying again is impossible: the same completion event, a replayed hook, a second request.
do_action('ohmylms_practice_completed', $one['uuid']);
Xp::award_practice($one['uuid']);
ok(Xp::total($student) === 15 && (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . XpSchema::table('xp_events') . ' WHERE student_id=%d', $student)) === 1, 'XP was paid twice for one lesson');
$again = call('GET', 'practice/sessions/' . $one['uuid'])->get_data();
ok($again['reward']['xp']['xp'] === 15, 'The summary of a finished lesson lost its XP');

// ---- Lesson 2: the daily goal (20) is met once, with a marker row and one action ----
$two = $lesson([true, true, true, true]);
ok($two['reward']['xp']['today'] === 30 && $two['reward']['xp']['goal_met'] === true && count($goals_met) === 1 && $goals_met[0] === [$student, '2026-10-07', 20], 'The goal was not met once: ' . wp_json_encode([$two['reward']['xp'], $goals_met]));
ok($two['reward']['score']['before'] === 36.0 && $two['reward']['score']['after'] === 72.0, 'Lesson 2 score wrong (36 + 9 + 9 + 9 + 9)');
$three = $lesson([true, true, true, true]);
ok(count($goals_met) === 1 && Xp::earned($student, '2026-10-07') === 45, 'Meeting the goal again the same day paid or announced it twice');
ok($three['reward']['score']['before'] === 72.0 && $three['reward']['score']['after'] === 87.0 && $three['reward']['score']['reached'] === [80], 'Lesson 3: 72 -> 77 -> 82 -> 84.5 -> 87 expected: ' . wp_json_encode($three['reward']['score']));
ok(count($milestones) === 1 && $milestones[0] === [$student, $skill, 80, 'bronze'] && $stored_score()['milestones'] === '80', 'Bronze at 80 was not announced exactly once');

// ---- Lesson 4: the challenge zone pays 2 XP for each right answer made at 90 or more ----
$four = $lesson([true, true, true, true]);
ok($four['reward']['score']['after'] === 95.0 && $four['reward']['xp']['parts'] === ['lesson' => 10, 'perfect' => 5, 'challenge' => 4] && $four['reward']['xp']['xp'] === 19, 'Challenge zone XP wrong: ' . wp_json_encode($four['reward']));
ok(array_column($milestones, 2) === [80, 90] && $milestones[1][3] === 'silver', 'Silver at 90 was not announced: ' . wp_json_encode($milestones));
ok(Xp::earned($student, '2026-10-07') === 64 && count($goals_met) === 1, 'The day total is wrong');

// ---- Lesson 5: four misses. The score stops at the band floor (90); XP is only for the lesson ----
$five = $lesson([false, false, false, false], 'standard');
ok($five['reward']['score']['before'] === 95.0 && $five['reward']['score']['after'] === 90.0, 'The floor of the top band did not hold: ' . wp_json_encode($five['reward']['score']));
ok($five['reward']['xp']['xp'] === 10 && $five['reward']['xp']['parts'] === ['lesson' => 10], 'A lesson with misses should earn the lesson XP only: ' . wp_json_encode($five['reward']['xp']));
ok(count($milestones) === 2 && $stored_score()['milestones'] === '80,90', 'A slip announced or lost a medal');
$summary = Score::summary($student)[$skill];
ok($summary['score'] === 90.0 && $summary['medal'] === 'silver' && $summary['band'] === 'challenge' && $summary['unlocked'] === true && $summary['review_due'] === false, 'Score summary wrong: ' . wp_json_encode($summary));

// ---- A lesson with misses and a replay: only first tries move the score ----
$clock += 86400; // Thursday
$mixed = $lesson([false, true, true, true]);
ok($mixed['status'] === 'complete' && $mixed['replays'] === 1 && $mixed['reward']['xp']['parts'] === ['lesson' => 10, 'challenge' => 6] && $mixed['reward']['xp']['xp'] === 16 && $mixed['reward']['xp']['today'] === 16 && $mixed['reward']['xp']['total'] === 90, 'The next day starts a new daily total: ' . wp_json_encode($mixed['reward']['xp']));
ok($mixed['reward']['score']['before'] === 90.0 && $mixed['reward']['score']['after'] === 96.0, 'The mixed lesson score is wrong (a miss at the floor, three rights, a replay right): ' . wp_json_encode($mixed['reward']['score']));
ok($mixed['planned_right'] === 3 && Xp::earned($student, '2026-10-08') === $mixed['reward']['xp']['xp'], 'Lesson accuracy wrong');
$expected = ScoreModel::run(array_map(static function ($row) { return $row; }, Score::rows($student, $skill)), Score::config());
ok(abs($expected['score'] - (float) $stored_score()['mastery']) < 0.001, 'The stored score is not what the evidence gives');
$after_mixed = $mixed['reward']['score']['after'];

// ---- The week and the learner's own endpoint ----
$summary_xp = call('GET', 'engagement/xp')->get_data();
ok($summary_xp['enabled'] === true && $summary_xp['date'] === '2026-10-08' && $summary_xp['week_from'] === '2026-10-05' && $summary_xp['total'] === 90 && $summary_xp['week'] === 90 && $summary_xp['today'] === 16, 'XP summary wrong: ' . wp_json_encode($summary_xp));
ok(count($summary_xp['days']) === 7 && $summary_xp['days'][6] === ['date' => '2026-10-08', 'xp' => 16] && $summary_xp['days'][5] === ['date' => '2026-10-07', 'xp' => 74] && $summary_xp['days'][0]['xp'] === 0, 'The seven-day history is wrong: ' . wp_json_encode($summary_xp['days']));
$put = call('PUT', 'engagement/xp', ['goal' => 30]);
ok(status_of($put) === 200 && $put->get_data()['goal'] === 30 && Xp::goal($student) === 30, 'The goal could not be changed');
ok(status_of(call('PUT', 'engagement/xp', ['goal' => 25])) === 400 && Xp::goal($student) === 30, 'A goal outside the list was accepted');
as_user(0);
ok(status_of(call('GET', 'engagement/xp')) === 401, 'A guest read XP');
as_user($student);

// ---- The daily ceiling ----
$xp_settings(['daily_cap' => 12]);
$capped = $lesson([true, true, true, true]);
ok($capped['reward']['xp']['xp'] === 0 && $capped['reward']['xp']['capped'] === true && Xp::earned($student, '2026-10-08') === 16, 'The daily cap did not limit XP: ' . wp_json_encode($capped['reward']['xp']));
$xp_settings();

// ---- Evidence summary carries the score; standard style pays the same ----
$summary_skills = Evidence::summary($student);
$mine = array_values(array_filter($summary_skills, static function ($s) use ($skill) { return (int) $s['id'] === $skill; }))[0];
ok(isset($mine['mastery']) && $mine['mastery']['medal'] === 'gold' && $mine['mastery']['score'] === 100.0 && isset($mine['level_label']), 'The skill summary lacks the mastery score: ' . wp_json_encode($mine));
$standard = $lesson([true, true, true], 'standard');
ok($standard['reward']['xp']['parts'] === ['lesson' => 10, 'perfect' => 5, 'challenge' => 6] && $standard['reward']['xp']['xp'] === 21, 'Standard-style practice pays differently: ' . wp_json_encode($standard['reward']['xp']));

// ---- Streak in the reward ----
as_user($admin);
call('POST', 'engagement/settings/streak', array_merge(StreakSettings::defaults(), ['enable' => true, 'milestones' => []]));
ok(StreakSchema::ready(), 'Streak storage missing');
as_user($student);
$clock += 86400;
$with_streak = $lesson([true, true, true, true]);
ok(isset($with_streak['reward']['streak']) && $with_streak['reward']['streak']['current'] >= 1 && $with_streak['reward']['streak']['today_complete'] === true, 'The reward lacks the streak: ' . wp_json_encode($with_streak['reward']));

// ---- Switching things off ----
$xp_settings(['enable' => false]);
$off = $lesson([true, true, true]);
ok(!isset($off['reward']['xp']) && isset($off['reward']['score']) && call('GET', 'engagement/xp')->get_data() === ['enabled' => false], 'XP did not switch off cleanly');
$before_off = Xp::total($student);
Xp::award_practice($off['uuid']);
ok(Xp::total($student) === $before_off && Xp::award($student, 'x', 'practice', 5) === null, 'XP was paid while switched off');
$score_settings(['enable' => false]);
ok(Score::summary($student) === [] && !isset(array_values(array_filter(Evidence::summary($student), static function ($s) use ($skill) { return (int) $s['id'] === $skill; }))[0]['mastery']) && !isset($lesson([true, true, true])['reward']['score']), 'The score did not switch off cleanly');

// ---- The score is kept up to date even while the feature is off, so switching it on shows it at once ----
$stored_before = (float) $stored_score()['mastery'];
$score_settings(['enable' => true]);
$backfilled = Score::summary($student);
ok(isset($backfilled[$skill]) && $backfilled[$skill]['scored'] > 0 && abs($backfilled[$skill]['stored'] - $stored_before) < 0.001, 'The score kept while the feature was off was not shown when it was switched on');
ok(abs($backfilled[$skill]['stored'] - ScoreModel::run(Score::rows($student, $skill), Score::config())['score']) < 0.001, 'The backfilled score differs from the evidence');

// ---- Tuning is honoured: a band that gains more reaches the top sooner ----
$first_step = static function () use ($student, $skill) { $trace = array_values(Score::trace($student, $skill)); return $trace[0]['after']; };
ok($first_step() === 9.0, 'The default learning band should add 9 to the first answer');
$score_settings(['bands' => [['gain' => 20.0, 'loss' => 1.5], ['gain' => 5.0, 'loss' => 3.5], ['gain' => 2.5, 'loss' => 5.0], ['gain' => 1.5, 'loss' => 6.5]]]);
ok($first_step() === 20.0, 'Tuned band values were not applied');
$score_settings();

// ---- A learner who never practised has nothing, and other learners are separate ----
$other = make_user('subscriber');
ok(Score::summary($other) === [] && Xp::total($other) === 0 && Xp::summary($other)['today'] === 0, 'A new learner has XP or a score');
$wpdb->delete(XpSchema::table('xp_events'), ['student_id' => $student]);
$wpdb->delete(StreakSchema::table('state'), ['user_id' => $student]);
foreach (['days', 'activities', 'milestones'] as $name) { $wpdb->delete(StreakSchema::table($name), ['user_id' => $student]); }
