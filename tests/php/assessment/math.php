<?php
/** Phase 6: numerical and structured questions, random pools, part-level evidence. */
use OhMyLMS\Assessment\AttemptItems;
use OhMyLMS\Assessment\GradeEvents;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Quiz\Review;
use OhMyLMS\Quiz\Submission;
use OhMyLMS\Skills\Evidence;
use OhMyLMS\Skills\Taxonomy;

global $wpdb;
$t = static function ($name) { return Schema::table($name); };
as_user($admin);
$skill = static function ($name) use (&$fixture) {
	$created = call(
		'POST',
		'skills',
		array(
			'name'            => $name . ' ' . wp_generate_password( 5, false, false ),
			'public_practice' => true,
		)
	)->get_data();
    $fixture['terms'][] = [$created['id'], Taxonomy::NAME];
    return (int) $created['id'];
};
$fractions = $skill('Fractions'); $equations = $skill('Equations'); $reasoning = $skill('Reasoning'); $areas = $skill('Area');

// ---- Authoring validation ----
$bad = call('POST', 'question', ['name' => 'Bad number', 'settings' => ['type' => 'numerical', 'answer' => 'four']]);
ok(status_of($bad) === 400, 'Non-numeric numerical answer accepted: ' . status_of($bad));
$bad = call('POST', 'question', ['name' => 'Bad parts', 'settings' => ['type' => 'structured', 'parts' => [['id' => 'a', 'kind' => 'numerical']]]]);
ok(status_of($bad) === 400, 'Structured part without answer accepted');

[$course_id, $quiz_id] = course_with_quiz($admin, ['allow_attempts' => 10, 'layout' => 'all_questions_in_one_page']);
$numerical = remember_post(call('POST', 'question', ['quiz_id' => $quiz_id, 'name' => 'Simplify 6/8', 'settings' => ['type' => 'numerical', 'answer' => 0.75, 'tolerance' => 0.001, 'unit' => '', 'score' => ['enabled' => true, 'value' => 2]],
    'skills' => ['p1' => ['primary' => $fractions]]])->get_data()['id']);
$structured = call('POST', 'question', ['quiz_id' => $quiz_id, 'name' => 'Rectangle', 'description' => 'A rectangle has sides 3 cm and 5 cm.',
    'settings' => ['type' => 'structured', 'score' => ['enabled' => true, 'value' => 6], 'parts' => [
        ['id' => 'a', 'label' => '(a)', 'prompt' => 'Area?', 'kind' => 'numerical', 'marks' => 2, 'answer' => 15, 'unit' => 'cm²', 'rubric' => 'secret rubric'],
        ['id' => 'b', 'label' => '(b)', 'prompt' => 'Solve 2x=4', 'kind' => 'text', 'marks' => 1, 'accepted' => ['x=2']],
        ['id' => 'c', 'label' => '(c)', 'prompt' => 'Explain', 'kind' => 'written', 'marks' => 3],
    ]], 'skills' => ['a' => ['primary' => $areas], 'b' => ['primary' => $equations], 'c' => ['primary' => $reasoning]]]);
ok(status_of($structured) === 201, 'Structured create failed: ' . wp_json_encode($structured->get_data()));
$structured = remember_post($structured->get_data()['id']);

// ---- Pools: shortage is reported, then satisfied ----
$pool_questions = [];
foreach ([['f1', 0.5], ['f1', 0.25], ['f2', 0.2]] as $i => [$family, $answer]) {
    $id = remember_post(call('POST', 'question', ['name' => 'Pool ' . $i, 'settings' => ['type' => 'numerical', 'answer' => $answer, 'score' => ['enabled' => true, 'value' => 1]],
        'skills' => ['p1' => ['primary' => $fractions]], 'bank' => ['family_id' => $family . '-' . $quiz_id]])->get_data()['id']);
    call('POST', 'question-bank/' . $id . '/approve');
    $pool_questions[] = $id;
}
$shortage = call('PUT', 'quiz/' . $quiz_id . '/pools', ['pools' => [['title' => 'Random fractions', 'term_id' => $fractions, 'count' => 3, 'marks' => 1]]]);
ok(status_of($shortage) === 409 && $shortage->get_data()['code'] === 'quiz_pool_shortage', 'Pool shortage not reported: ' . wp_json_encode($shortage->get_data()));
$extra = remember_post(call('POST', 'question', ['name' => 'Pool extra', 'settings' => ['type' => 'numerical', 'answer' => 0.1, 'score' => ['enabled' => true, 'value' => 1]],
    'skills' => ['p1' => ['primary' => $fractions]], 'bank' => ['family_id' => 'f3-' . $quiz_id]])->get_data()['id']);
call('POST', 'question-bank/' . $extra . '/approve');
// The fixed numerical question is mapped to the same skill but must never be drawn twice.
call('POST', 'question-bank/' . $numerical . '/approve');
$saved = call('PUT', 'quiz/' . $quiz_id . '/pools', ['pools' => [['title' => 'Random fractions', 'term_id' => $fractions, 'count' => 3, 'marks' => 1]]]);
ok(status_of($saved) === 200, 'Feasible pool rejected: ' . wp_json_encode($saved->get_data()));

$student = make_user('subscriber');
enroll($student, $course_id);
as_user($student);
$attempt = Submission::start($quiz_id, $student); $fixture['attempts'][] = $attempt;
ok(is_int($attempt), 'Start with pools failed: ' . (is_wp_error($attempt) ? $attempt->get_error_message() : ''));
$items = AttemptItems::items($attempt);
$drawn = array_values(array_filter($items, static function ($item) use ($numerical, $structured) { return !in_array((int) $item['question_id'], [$numerical, $structured], true); }));
ok(count($items) === 5 && count($drawn) === 3, 'Pool slots not drawn');
$families = array_map(static function ($item) use ($wpdb, $t) { return $wpdb->get_var($wpdb->prepare("SELECT family_id FROM {$t('qb_questions')} WHERE question_id=%d", $item['question_id'])); }, $drawn);
ok(count(array_unique($families)) === 3 && !in_array($numerical, array_column($drawn, 'question_id'), true), 'Pool repeated a family or the fixed question');
ok(wp_json_encode(AttemptItems::delivery($attempt)) === wp_json_encode(AttemptItems::delivery($attempt)) && $drawn[0]['display']['section'] === 'Random fractions', 'Pool delivery unstable or unlabeled');

// ---- Learner-safe structured delivery and grading ----
$views = array_column(AttemptItems::delivery($attempt), null, 'id');
$leak = wp_json_encode($views[$structured]);
ok(strpos($leak, 'secret rubric') === false && strpos($leak, '"answer":15') === false && strpos($leak, 'x=2') === false && count($views[$structured]['settings']['parts']) === 3, 'Structured delivery leaked keys');
$GLOBALS['post'] = get_post($quiz_id); setup_postdata($GLOBALS['post']);
ob_start(); ohmylms_get_template('single-lesson/quiz-form.php'); $page = ob_get_clean(); wp_reset_postdata();
ok(strpos($page, '[quiz_question][' . $structured . '][a]') !== false && strpos($page, 'inputmode="decimal"') !== false && strpos($page, 'secret rubric') === false, 'Structured/numerical templates not rendered safely');
$answers = [$numerical => ['3/4'], $structured => ['a' => '15', 'b' => 'X = 2', 'c' => 'Area is length times width']];
foreach ($drawn as $item) { $answers[(int) $item['question_id']] = ['wrong']; }
$result = Submission::submit($quiz_id, $attempt, $student, $answers);
ok(!is_wp_error($result) && $result['status'] === 'in-review', 'Math submission failed: ' . wp_json_encode($result));
// 2 (numerical) + 6 * (2+1)/6 auto parts = 5; drawn wrong = 0.
ok(abs((float) $result['total'] - 5.0) < 0.0001, 'Math total wrong: ' . $result['total']);
$structured_item = array_values(array_filter($items, static function ($item) use ($structured) { return (int) $item['question_id'] === $structured; }))[0];
$events = $wpdb->get_results($wpdb->prepare("SELECT part_id, awarded, max_marks FROM {$t('grade_events')} WHERE source_type='quiz' AND source_id=%d AND item_id=%d AND superseded_by=0 ORDER BY part_id", $attempt, $structured_item['id']), ARRAY_A);
ok(count($events) === 2 && $events[0]['part_id'] === 'a' && (float) $events[0]['awarded'] === 2.0 && (float) $events[1]['awarded'] === 1.0, 'Per-part events wrong: ' . wp_json_encode($events));

// Teacher marks the whole question 5/6: auto parts keep 3, the written part gets 2.
as_user($admin);
$report = Review::save($quiz_id, $attempt, [$structured => 5]);
ok(!is_wp_error($report) && $report['status'] === 'completed', 'Structured review failed');
$written = GradeEvents::history('quiz', $attempt, (int) $structured_item['id']);
$c = array_values(array_filter($written, static function ($event) { return $event['part_id'] === 'c' && !(int) $event['superseded_by']; }));
ok($c && (float) $c[0]['awarded'] === 2.0 && $c[0]['grader'] === 'manual', 'Written part not marked from the remainder');
$row_total = static function () use ($wpdb, $attempt, $structured) { return (float) $wpdb->get_var($wpdb->prepare("SELECT achive_mark FROM {$wpdb->prefix}ohmylms_quiz_attempts_answers WHERE quiz_attempt_id=%d AND question_id=%d", $attempt, $structured)); };
ok($row_total() === 5.0 && (float) $report['total_achieved_marks'] === 7.0, 'Structured total did not include automatic parts: ' . $row_total());
ok(is_wp_error(Review::save($quiz_id, $attempt, [$structured => ['a' => 9]])), 'Part mark above the part maximum accepted');
$precise = Review::save($quiz_id, $attempt, [$structured => ['c' => 3]]);
ok(!is_wp_error($precise) && $row_total() === 6.0, 'Per-part review total wrong: ' . $row_total());
Evidence::process();
$by_skill = [];
foreach ($wpdb->get_results($wpdb->prepare("SELECT term_id, awarded, available FROM {$t('skill_evidence')} WHERE student_id=%d AND source_type='quiz' AND role='primary' AND superseded=0 AND question_id=%d", $student, $structured), ARRAY_A) as $row) { $by_skill[(int) $row['term_id']] = [(float) $row['awarded'], (float) $row['available']]; }
ok(($by_skill[$areas] ?? null) === [2.0, 2.0] && ($by_skill[$equations] ?? null) === [1.0, 1.0] && ($by_skill[$reasoning] ?? null) === [3.0, 3.0], 'Part-level evidence not attributed to part skills: ' . wp_json_encode($by_skill));

// ---- Practice uses numerical, never teacher-marked structured parts ----
as_user($student);
$session = call('POST', 'practice/sessions', ['term_id' => $fractions, 'item_limit' => 3])->get_data();
ok(!empty($session['current']) && $session['current']['settings']['type'] === 'numerical', 'Numerical practice not offered');
$answer = call('POST', 'practice/sessions/' . $session['uuid'] . '/answer', ['item_id' => $session['current']['item_id'], 'response' => ['not a number']])->get_data();
ok($answer['correct'] === false && !empty($answer['feedback']['expected']), 'Invalid numeric answer not graded wrong with feedback');
ok(status_of(call('POST', 'practice/sessions', ['term_id' => $reasoning])) === 409, 'Teacher-marked structured question offered for practice');

// ---- Non-recording author preview ----
as_user($admin);
$attempts_before = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ohmylms_quiz_attempts");
$events_before = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t('grade_events')}");
$preview = call('POST', 'quiz/' . $quiz_id . '/preview');
ok(status_of($preview) === 200 && count($preview->get_data()['questions']) === 5 && strpos(wp_json_encode($preview->get_data()), 'secret rubric') === false, 'Preview failed or leaked keys');
$numeric_view = array_values(array_filter($preview->get_data()['questions'], static function ($view) use ($numerical) { return (int) $view['id'] === $numerical; }))[0];
$graded = call('POST', 'quiz/' . $quiz_id . '/preview/grade', ['token' => $numeric_view['token'], 'response' => ['0.75']]);
ok(status_of($graded) === 200 && $graded->get_data()['correct'] && $graded->get_data()['recorded'] === false, 'Preview grading failed');
ok((int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ohmylms_quiz_attempts") === $attempts_before && (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t('grade_events')}") === $events_before, 'Preview recorded learner data');
as_user($student);
ok(status_of(call('POST', 'quiz/' . $quiz_id . '/preview')) === 403, 'Learner opened the author preview');

// ---- Per-part marking through the grading endpoint ----
as_user($admin);
$report = call('GET', 'quiz/' . $quiz_id . '/report/' . $attempt)->get_data();
$entry = array_values(array_filter($report['report']['questions'], static function ($question) use ($structured) { return (int) $question['id'] === $structured; }))[0];
ok($entry['parts']['a']['max'] === 2.0 && $entry['parts']['c']['max'] === 3.0 && $entry['parts']['a']['awarded'] === 2.0, 'Report lacks per-part marks: ' . wp_json_encode($entry['parts']));
$entry['part_marks'] = ['c' => 1.5];
$posted = call('POST', 'quiz/' . $quiz_id . '/report/' . $attempt, ['report' => ['questions' => [$entry]]]);
ok(status_of($posted) === 200 && $row_total() === 4.5, 'Per-part marks via the grading endpoint failed: ' . $row_total());

// ---- Mongolian content survives authoring, versions and grading ----
as_user($admin);
$mn_skill = $skill('Бутархай тоо');
$mn = call('POST', 'question', ['name' => 'Хялбарчил: \(\frac{6}{8}\)', 'description' => '<p>Тэгшитгэл бод.</p>', 'settings' => ['type' => 'structured', 'score' => ['enabled' => true, 'value' => 2],
    'parts' => [['id' => 'a', 'label' => '(а)', 'prompt' => 'Хариу', 'kind' => 'text', 'marks' => 2, 'accepted' => ['Х = 2']]]], 'skills' => ['a' => ['primary' => $mn_skill]], 'bank' => ['family_id' => 'бутархай-1']]);
ok(status_of($mn) === 201, 'Mongolian question rejected: ' . wp_json_encode($mn->get_data()));
$mn_id = remember_post($mn->get_data()['id']);
$mn_version = \OhMyLMS\QuestionBank\VersionPublisher::snapshot(\OhMyLMS\QuestionBank\VersionPublisher::identity($mn_id)['current_version_id']);
ok($mn_version->get_name() === 'Хялбарчил: \(\frac{6}{8}\)' && strpos($mn_version->get_description(), 'Тэгшитгэл') !== false, 'Mongolian/LaTeX text altered in version');
ok(\OhMyLMS\Assessment\Structured::grade(['a' => 'х=2'], $mn_version)['correct'], 'Cyrillic text answer not matched');
ok(\OhMyLMS\QuestionBank\VersionPublisher::identity($mn_id)['family_id'] !== '', 'Cyrillic family ID dropped');
ok(get_term($mn_skill, \OhMyLMS\Skills\Taxonomy::NAME)->name !== '' && strpos(get_term($mn_skill, \OhMyLMS\Skills\Taxonomy::NAME)->name, 'Бутархай') === 0, 'Mongolian skill name altered');
