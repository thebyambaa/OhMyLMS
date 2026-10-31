<?php
/** Phase 7: randomised question templates: authoring, per-attempt numbers, practice, evidence, previews. */
use OhMyLMS\Assessment\AttemptItems;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Assessment\Template;
use OhMyLMS\Practice\Inline;
use OhMyLMS\QuestionBank\VersionPublisher;
use OhMyLMS\Quiz\Submission;
use OhMyLMS\Skills\Evidence;
use OhMyLMS\Skills\Taxonomy;

global $wpdb;
$t = static function ($name) { return Schema::table($name); };
as_user($admin);
$created = call('POST', 'skills', ['name' => 'Multiplication ' . wp_generate_password(5, false, false), 'public_practice' => true])->get_data();
$fixture['terms'][] = [$created['id'], Taxonomy::NAME];
$skill = (int) $created['id'];

// The numbers of a question, read from its delivered title "What is 7 × 4?".
$numbers = static function ($title) {
    ok((bool) preg_match('/(\d+) × (\d+)/', (string) $title, $m), 'No numbers in the delivered question: ' . $title);
    return [(int) $m[1], (int) $m[2]];
};
$template = static function ($extra = []) {
    return array_merge([
        'variables'   => [['name' => 'a', 'type' => 'int', 'min' => 3, 'max' => 9], ['name' => 'b', 'type' => 'int', 'min' => 2, 'max' => 8]],
        'constraints' => ['a>b'],
        'set'         => [['path' => 'answer', 'expr' => 'a*b']],
    ], $extra);
};
$settings = static function ($template_value) {
    return ['type' => 'numerical', 'answer' => 0, 'tolerance' => 0, 'unit' => '', 'score' => ['enabled' => true, 'value' => 2],
        'hint' => 'Think of {{a}} groups of {{b}}.', 'explanation' => '{{a}} × {{b}} = {{a*b}}', 'template' => $template_value];
};

// ---- Authoring validation: a template must work before it can be saved ----
$impossible = call('POST', 'question', ['name' => 'What is {{a}} × {{b}}?', 'settings' => $settings($template(['constraints' => ['a>100']]))]);
ok(status_of($impossible) === 400 && strpos($impossible->get_data()['message'], 'strict') !== false, 'Impossible template accepted: ' . wp_json_encode($impossible->get_data()));
$unknown = call('POST', 'question', ['name' => 'What is {{a}} × {{zz}}?', 'settings' => $settings($template())]);
ok(status_of($unknown) === 400, 'Unknown placeholder accepted');
$constant = call('POST', 'question', ['name' => 'What is {{a}} × {{b}}?', 'settings' => $settings(['variables' => [['name' => 'a', 'type' => 'int', 'min' => 3, 'max' => 3], ['name' => 'b', 'type' => 'int', 'min' => 2, 'max' => 2]], 'set' => [['path' => 'answer', 'expr' => 'a*b']]])]);
ok(status_of($constant) === 400, 'A template that never changes accepted');
$wrongkey = call('POST', 'question', ['name' => 'What is {{a}} × {{b}}?', 'settings' => $settings($template(['set' => [['path' => 'tolerance', 'expr' => '-1']]]))]);
ok(status_of($wrongkey) === 400, 'An example question that fails the type check was accepted (negative tolerance)');

// ---- The author's preview endpoint shows concrete examples ----
$preview = call('POST', 'question-template/preview', ['name' => 'What is {{a}} × {{b}}?', 'description' => '', 'settings' => $settings($template()), 'questions' => [], 'count' => 4]);
$data = $preview->get_data();
ok(status_of($preview) === 200 && $data['valid'] === true && count($data['samples']) === 4, 'Template preview failed: ' . wp_json_encode($data));
foreach ($data['samples'] as $sample) {
    [$a, $b] = $numbers($sample['title']);
    ok($a > $b && (string) $sample['expected'] === (string) ($a * $b), 'Preview example has the wrong answer: ' . wp_json_encode($sample));
}
$broken = call('POST', 'question-template/preview', ['name' => 'What is {{a}} × {{zz}}?', 'settings' => $settings($template()), 'questions' => []])->get_data();
ok($broken['valid'] === false && $broken['message'] !== '' && count($broken['samples']) > 0, 'Preview did not explain an invalid template');
as_user($subscriber_probe = make_user('subscriber'));
ok(status_of(call('POST', 'question-template/preview', ['settings' => $settings($template())])) === 403, 'A learner used the template preview');
as_user($admin);

// ---- A quiz question that is a template ----
[$course_id, $quiz_id] = course_with_quiz($admin, ['allow_attempts' => 20, 'layout' => 'all_questions_in_one_page']);
$created_q = call('POST', 'question', ['quiz_id' => $quiz_id, 'name' => 'What is {{a}} × {{b}}?', 'description' => '<p>Give the product of {{a}} and {{b}}.</p>',
    'settings' => $settings($template()), 'skills' => ['p1' => ['primary' => $skill]]]);
ok(status_of($created_q) === 201, 'Template question create failed: ' . wp_json_encode($created_q->get_data()));
$qid = remember_post($created_q->get_data()['id']);
$approved = call('POST', 'questions/' . $qid . '/approve');
ok(status_of($approved) === 200, 'Approve failed: ' . wp_json_encode($approved->get_data()));
$version_id = (int) VersionPublisher::identity($qid)['current_version_id'];
$frozen = VersionPublisher::snapshot($version_id);
ok($frozen->is_template() && strpos($frozen->get_name(), '{{') !== false && isset($frozen->get_settings()['template']), 'The frozen version is not the template');
$hash_before = $wpdb->get_var($wpdb->prepare("SELECT content_hash FROM {$t('qb_question_versions')} WHERE id=%d", $version_id));

$student = make_user('subscriber');
enroll($student, $course_id);
as_user($student);

// ---- Each attempt gets its own numbers, fixed for that attempt ----
$seen = [];
$first_attempt = null;
for ($i = 0; $i < 10; $i++) {
    $attempt = Submission::start($quiz_id, $student);
    ok(is_int($attempt), 'Start failed: ' . (is_wp_error($attempt) ? $attempt->get_error_message() : ''));
    $fixture['attempts'][] = $attempt;
    $item = AttemptItems::items($attempt)[0];
    ok(!empty($item['display']['instance_seed']), 'No instance seed stored on the attempt item');
    $view = AttemptItems::delivery($attempt)[0];
    [$a, $b] = $numbers($view['name']);
    $seen[$a . 'x' . $b] = true;
    if ($i === 0) { $first_attempt = [$attempt, $view, $a, $b]; }
    ok(wp_json_encode(AttemptItems::delivery($attempt)[0]) === wp_json_encode($view), 'Refreshing changed the numbers');
    // Learner-safe: nothing about how the numbers were made, no answer.
    $leak = wp_json_encode($view);
    ok(strpos($leak, '{{') === false && strpos($leak, '"template"') === false && strpos($leak, 'variables') === false && strpos($leak, '"answer":' . ($a * $b)) === false && strpos($leak, 'constraints') === false, 'Template or key leaked to the learner: ' . $leak);
    ok(strpos($view['description'], 'product of ' . $a . ' and ' . $b) !== false, 'Body not instantiated');
    // While the first attempt is open, the quiz page itself shows its numbers and no template text.
    if ($i === 0) {
        $GLOBALS['post'] = get_post($quiz_id); setup_postdata($GLOBALS['post']);
        ob_start(); ohmylms_get_template('single-lesson/quiz-form.php'); $page = ob_get_clean(); wp_reset_postdata();
        ok(strpos($page, 'What is ' . $a . ' × ' . $b . '?') !== false || strpos($page, 'What is ' . $a . ' &times; ' . $b) !== false || strpos($page, (string) $a) !== false, 'Quiz page does not show the numbers');
        ok(strpos($page, '{{a}}') === false && strpos($page, 'variables') === false && strpos($page, '"template"') === false, 'Quiz page contains template text');
    }
    $right = $i % 2 === 0;
    $result = Submission::submit($quiz_id, $attempt, $student, [$qid => [$right ? (string) ($a * $b) : (string) ($a * $b + 1)]]);
    ok(!is_wp_error($result), 'Submit failed: ' . (is_wp_error($result) ? $result->get_error_message() : ''));
    ok(abs((float) $result['total'] - ($right ? 2.0 : 0.0)) < 0.0001, "Attempt $i graded against the wrong numbers: total {$result['total']} for {$a}x{$b}");
}
ok(count($seen) >= 4, 'Ten attempts gave too few different questions: ' . implode(',', array_keys($seen)));

// ---- Review and reports show what the learner saw ----
[$attempt0, $view0, $a0, $b0] = $first_attempt;
as_user($admin);

$report = call('GET', 'quiz/' . $quiz_id . '/report/' . $attempt0)->get_data();
$entry = $report['report']['questions'][0];
ok($entry['name'] === 'What is ' . $a0 . ' × ' . $b0 . '?' && $entry['instance']['params']['a'] === $a0 && $entry['instance']['params']['b'] === $b0, 'Report shows other numbers than the learner saw: ' . wp_json_encode([$entry['name'], $entry['instance']]));
ok($entry['settings']['answer'] == $a0 * $b0 && !isset($entry['settings']['template']), 'Report settings are not the concrete question');
ok($entry['correct'] === true && (float) $entry['achive_mark'] === 2.0, 'Report verdict wrong');

// ---- Versions are immutable: editing the template does not disturb finished attempts ----
$edited = call('PUT', 'question/' . $qid, ['settings' => $settings($template(['variables' => [['name' => 'a', 'type' => 'int', 'min' => 11, 'max' => 19], ['name' => 'b', 'type' => 'int', 'min' => 2, 'max' => 8]], 'constraints' => []]))]);
ok(status_of($edited) === 200, 'Template edit failed: ' . wp_json_encode($edited->get_data()));
$after = call('GET', 'quiz/' . $quiz_id . '/report/' . $attempt0)->get_data()['report']['questions'][0];
ok($after['name'] === $entry['name'] && $after['instance'] == $entry['instance'], 'Editing the template changed a finished attempt');
ok($wpdb->get_var($wpdb->prepare("SELECT content_hash FROM {$t('qb_question_versions')} WHERE id=%d", $version_id)) === $hash_before, 'The frozen version changed');
$new_version = (int) VersionPublisher::identity($qid)['current_version_id'];
ok($new_version !== $version_id, 'Editing the template did not create a new version');

// ---- Author preview: concrete numbers, graded against the same numbers, nothing recorded ----
$before = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t('grade_events')}");
$view_response = call('POST', 'quiz/' . $quiz_id . '/preview');
ok(status_of($view_response) === 200, 'Preview failed: ' . wp_json_encode($view_response->get_data()));
$pq = $view_response->get_data()['questions'][0];
ok(strpos(wp_json_encode($pq), '{{') === false && strpos(wp_json_encode($pq), 'variables') === false, 'Preview leaked the template');
preg_match('/(\d+) × (\d+)/', $pq['name'], $pm);
$graded = call('POST', 'quiz/' . $quiz_id . '/preview/grade', ['token' => $pq['token'], 'response' => [(string) ((int) $pm[1] * (int) $pm[2])]]);
ok(status_of($graded) === 200 && $graded->get_data()['correct'] === true && $graded->get_data()['recorded'] === false, 'Preview graded against different numbers: ' . wp_json_encode($graded->get_data()));
$graded_wrong = call('POST', 'quiz/' . $quiz_id . '/preview/grade', ['token' => $pq['token'], 'response' => [(string) ((int) $pm[1] * (int) $pm[2] + 1)]]);
ok($graded_wrong->get_data()['correct'] === false, 'Preview accepted a wrong answer');
ok((int) $wpdb->get_var("SELECT COUNT(*) FROM {$t('grade_events')}") === $before, 'Preview recorded grade events');

// ---- Inline checks: the render token fixes the numbers ----
$token = Inline::sign($new_version, 4242);
ok(Inline::seed($token) === Inline::seed($token) && Inline::seed($token) > 0 && Inline::seed(Inline::sign($new_version, 4242)) !== Inline::seed($token), 'Inline token seed unstable or shared');
ok(Inline::seed('garbage') === 0, 'A forged token produced a seed');

// ---- Practice: a template is endless, each item has its own numbers, each counts as a first try ----
$approved = call('POST', 'questions/' . $qid . '/approve');
ok(status_of($approved) === 200, 'Second approve failed: ' . wp_json_encode($approved->get_data()));
as_user($student);
$session = call('POST', 'practice/sessions', ['term_id' => $skill, 'item_limit' => 5]);
ok(status_of($session) === 200 || status_of($session) === 201, 'Practice session failed: ' . wp_json_encode($session->get_data()));
$state = $session->get_data();
$signatures = [];
for ($n = 1; $n <= 5; $n++) {
    ok(!empty($state['current']), "Practice ended early at item $n (the template should be endless): " . wp_json_encode(['ended' => $state['ended'] ?? null, 'notice' => $state['notice'] ?? null]));
    $current = $state['current'];
    ok(strpos(wp_json_encode($current), '{{') === false && strpos(wp_json_encode($current), 'variables') === false, 'Practice view leaked the template');
    ok(!empty($current['has_hint']), 'Hint not offered');
    [$pa, $pb] = $numbers($current['name']);
    $signatures[$pa . 'x' . $pb] = true;
    if ($n === 2) {
        $hint = call('POST', 'practice/sessions/' . $state['uuid'] . '/hint', ['item_id' => $current['item_id']])->get_data();
        ok(strpos((string) $hint['hint'], 'Think of ' . $pa . ' groups of ' . $pb) !== false, 'The hint did not use this question\'s numbers: ' . wp_json_encode($hint));
    }
    $answer = call('POST', 'practice/sessions/' . $state['uuid'] . '/answer', ['item_id' => $current['item_id'], 'response' => [(string) ($n === 4 ? $pa * $pb + 1 : $pa * $pb)]])->get_data();
    ok($answer['correct'] === ($n !== 4), "Practice item $n graded against other numbers: " . wp_json_encode($answer));
    ok(strpos((string) $answer['feedback']['explanation'], $pa . ' × ' . $pb . ' = ' . ($pa * $pb)) !== false, 'The worked solution did not use the numbers shown');
    ok(!empty($answer['feedback']['expected']) && (string) $answer['feedback']['expected'][0] === (string) ($pa * $pb), 'Feedback shows the wrong correct answer');
    $state = $answer['session'];
}
ok(count($signatures) >= 4, 'Practice repeated the same numbers: ' . implode(',', array_keys($signatures)));
ok(($state['ended'] ?? null) === 'limit', 'Practice did not stop at its item limit: ' . wp_json_encode($state['ended'] ?? null));

// Every issue counts as an independent first try for the skill, not just the first.
Evidence::process();
$rows = $wpdb->get_results($wpdb->prepare("SELECT first_try, independent, awarded, available FROM {$t('skill_evidence')} WHERE student_id=%d AND source_type='practice' AND question_id=%d AND role='primary' AND superseded=0", $student, $qid), ARRAY_A);
ok(count($rows) === 5 && count(array_filter($rows, static function ($row) { return (int) $row['first_try'] === 1; })) === 5, 'Template issues were not each counted as a first try: ' . wp_json_encode($rows));
ok(count(array_filter($rows, static function ($row) { return (int) $row['independent'] === 1; })) === 4, 'The item that used the hint should not be independent: ' . wp_json_encode($rows));

// ---- A plain question is untouched by all of this ----
as_user($admin);
$plain = call('POST', 'question', ['quiz_id' => $quiz_id, 'name' => 'Plain {{a}} stays literal', 'settings' => ['type' => 'numerical', 'answer' => 12, 'score' => ['enabled' => true, 'value' => 1]]]);
ok(status_of($plain) === 201, 'Plain question with braces rejected');
$plain_id = remember_post($plain->get_data()['id']);
$plain_snapshot = VersionPublisher::snapshot((int) VersionPublisher::identity($plain_id)['current_version_id']);
ok(!$plain_snapshot->is_template() && $plain_snapshot->instantiate(5) === $plain_snapshot && $plain_snapshot->get_name() === 'Plain {{a}} stays literal', 'A plain question was altered by instantiation');

// Maths generator presets built from a construction: Pythagorean triples and quadratics with two distinct whole solutions.
$presets = json_decode(file_get_contents(dirname(__DIR__) . '/../fixtures/maths-generator.json'), true);
foreach (['pythagorean', 'quadratic'] as $kind) {
    ok(true === Template::validate($presets[$kind]['settings'], '', $presets[$kind]['prompt'], []), "The $kind preset did not validate");
    $seen = [];
    for ($seed = 1; $seed <= 200; $seed++) {
        $drawn = Template::generate($presets[$kind]['settings']['template'], $seed);
        ok($drawn['ok'], "The $kind preset failed to draw at seed $seed");
        $p = $drawn['params'];
        if ('pythagorean' === $kind) {
            ok($p['a'] > 0 && $p['b'] > 0 && $p['a'] ** 2 + $p['b'] ** 2 == $p['c'] ** 2, 'Not a Pythagorean triple: ' . wp_json_encode($p));
        } else {
            $disc = $p['b'] ** 2 - 4 * $p['c'];
            ok($disc > 0 && (int) sqrt($disc) ** 2 == $disc && $p['p'] != $p['q'] && $p['p'] ** 2 + $p['b'] * $p['p'] + $p['c'] == 0 && $p['b'] != 0, 'Quadratic without two distinct whole solutions: ' . wp_json_encode($p));
        }
        $seen[wp_json_encode($p)] = true;
    }
    ok(count($seen) >= ('pythagorean' === $kind ? 10 : 20), "The $kind preset repeats too often: " . count($seen));
}

// More presets: the answer follows from the construction for every seed.
foreach (['quadratic-repeated', 'discriminant', 'pythagorean-leg', 'simultaneous'] as $kind) {
    $settings = $presets[$kind]['settings'];
    ok(true === Template::validate($settings, '', $presets[$kind]['prompt'], []), "The $kind preset did not validate");
    for ($seed = 1; $seed <= 100; $seed++) {
        $applied = Template::apply('', $presets[$kind]['prompt'], $settings, [], $seed);
        $p = $applied['params'];
        $answer = $applied['settings']['answer'];
        ok($applied['ok'] && !$applied['errors'], "The $kind preset failed to draw at seed $seed");
        switch ($kind) {
            case 'quadratic-repeated': $good = $p['b'] ** 2 - 4 * $p['c'] == 0 && $answer == $p['p']; break;
            case 'discriminant': $good = $answer == $p['b'] ** 2 - 4 * $p['a'] * $p['c']; break;
            case 'pythagorean-leg': $good = $p['a'] ** 2 + $answer ** 2 == $p['c'] ** 2; break;
            default: $good = $p['a'] * $p['d'] - $p['b'] * $p['c'] != 0 && $p['a'] * $answer + $p['b'] * $p['y'] == $p['p'] && $p['c'] * $answer + $p['d'] * $p['y'] == $p['q'];
        }
        ok($good, "The $kind answer does not follow from its numbers: " . wp_json_encode([$p, $answer]));
    }
}

// Topic presets: fractions, percentages, graphs, ratio and speed. The answer is rechecked from the drawn numbers.
$gcd = static function ($x, $y) { while ($y) { [$x, $y] = [$y, $x % $y]; } return $x; };
$topic_checks = [
    'equivalent-fraction' => static function ($p, $answer) { return $p['a'] < $p['b'] && $p['a'] * $p['c'] == $answer * $p['b']; },
    'simplify-fraction'   => static function ($p, $answer) use ($gcd) { return $p['n'] * $answer == $p['d'] * $p['a'] && 1 == $gcd((int) $p['a'], (int) $answer); },
    'unlike-fractions'    => static function ($p, $answer) { return $p['b'] != $p['d'] && abs($answer / $p['p'] - ($p['a'] / $p['b'] + $p['c'] / $p['d'])) < 1e-9; },
    'percent-increase'    => static function ($p, $answer) { return abs($answer - $p['o'] * (1 + $p['r'] / 100)) < 1e-9 && floor($answer) == $answer; },
    'percent-decrease'    => static function ($p, $answer) { return abs($answer - $p['o'] * (1 - $p['r'] / 100)) < 1e-9 && floor($answer) == $answer; },
    'reverse-percentage'  => static function ($p, $answer) { return abs($p['n'] - $answer * (1 - $p['r'] / 100)) < 1e-9 && floor($p['n']) == $p['n']; },
    'simple-interest'     => static function ($p, $answer) { return abs($answer - $p['p'] * $p['r'] * $p['t'] / 100) < 1e-9; },
    'gradient'            => static function ($p, $answer) { return $p['c'] != $p['a'] && abs(($p['f'] - $p['b']) / ($p['c'] - $p['a']) - $answer) < 1e-9 && $answer != 0; },
    'y-intercept'         => static function ($p, $answer) { return abs($p['b'] - ($p['m'] * $p['a'] + $answer)) < 1e-9; },
    'midpoint'            => static function ($p, $answer) { return abs(($p['a'] + $p['c']) / 2 - $answer) < 1e-9 && floor($answer) == $answer; },
    'share-ratio'         => static function ($p, $answer) { return $p['a'] != $p['b'] && abs($p['t'] * $p['a'] / ($p['a'] + $p['b']) - $answer) < 1e-9 && floor($answer) == $answer; },
    'speed'               => static function ($p, $answer) { return abs($p['d'] / $p['t'] - $answer) < 1e-9 && floor($answer) == $answer; },
];
foreach ($topic_checks as $kind => $check) {
    $settings = $presets[$kind]['settings'];
    ok(true === Template::validate($settings, '', $presets[$kind]['prompt'], []), "The $kind preset did not validate");
    $seen = [];
    for ($seed = 1; $seed <= 150; $seed++) {
        $applied = Template::apply('', $presets[$kind]['prompt'], $settings, [], $seed);
        ok($applied['ok'] && !$applied['errors'], "The $kind preset failed to draw at seed $seed");
        ok($check($applied['params'], $applied['settings']['answer']), "The $kind answer does not follow from its numbers: " . wp_json_encode([$applied['params'], $applied['settings']['answer']]));
        $seen[wp_json_encode($applied['params'])] = true;
    }
    ok(count($seen) >= 8, "The $kind preset repeats too often: " . count($seen));
}

// A numerical answer, alternatives and tolerance may be variables or formulas.
$formula_settings = ['type' => 'numerical', 'answer' => '{{a*b}}', 'answers' => ['{{a}}', 7], 'tolerance' => '{{c}}', 'tolerance_type' => 'absolute', 'template' => ['variables' => [['name' => 'a', 'type' => 'int', 'min' => 2, 'max' => 9], ['name' => 'b', 'type' => 'int', 'min' => 2, 'max' => 9], ['name' => 'c', 'type' => 'int', 'min' => 1, 'max' => 3]]]];
$formula_applied = Template::apply('', 'x', $formula_settings, [], 11);
$fp = $formula_applied['params'];
ok(!$formula_applied['errors'] && $formula_applied['settings']['answer'] === (float) ($fp['a'] * $fp['b']) && $formula_applied['settings']['answers'] === [(float) $fp['a'], 7] && $formula_applied['settings']['tolerance'] === (float) $fp['c'], 'Formulas in a numerical answer, alternatives and tolerance were not turned into numbers: ' . wp_json_encode($formula_applied['settings']));
ok(true === Template::validate($formula_settings, '', 'x', [], static function ($example) { return is_numeric($example['answer']) && is_numeric($example['tolerance']) && count(array_filter($example['answers'], 'is_numeric')) === 2 ? true : 'not numeric'; }), 'A numerical template with formula fields did not validate');
