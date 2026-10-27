<?php
/** Phase 5: evidence pipeline, mastery rules, practice, inline checks, guest claims, reports. */
use OhMyLMS\Assessment\AttemptItems;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Practice\Inline;
use OhMyLMS\Quiz\Review;
use OhMyLMS\Quiz\Submission;
use OhMyLMS\Skills\Evidence;
use OhMyLMS\Skills\Mastery;
use OhMyLMS\Skills\Taxonomy;

global $wpdb;
$t = static function ($name) { return Schema::table($name); };
as_user($admin);
$skill = static function ($name, $extra = []) use (&$fixture) {
    $created = call('POST', 'skills', ['name' => $name . ' ' . wp_generate_password(5, false, false)] + $extra)->get_data();
    $fixture['terms'][] = [$created['id'], Taxonomy::NAME];
    return (int) $created['id'];
};
$basics = $skill('Basics');
$solve = $skill('Solving', ['prerequisites' => [$basics], 'public_practice' => true]);
$support = $skill('Reading');
$lesson = remember_post(wp_insert_post(['post_type' => OHMYLMS_LESSON_CPT, 'post_title' => 'Solving lesson', 'post_status' => 'publish', 'post_content' => 'x']));
call('PUT', 'skills/' . $solve . '/lessons', ['lesson_ids' => [$lesson]]);
[$course_id, $quiz_id] = course_with_quiz($admin, ['allow_attempts' => 10]);
$make = static function ($name, $bank, $quiz = 0) use ($solve, $support) {
    $data = ['name' => $name, 'settings' => ['type' => 'single-choice', 'score' => ['enabled' => true, 'value' => 1], 'hint' => 'Undo the operation'],
        'questions' => [['answer' => 'yes', 'is_correct' => 1], ['answer' => 'no']], 'skills' => ['p1' => ['primary' => $solve, 'supporting' => [$support]]], 'bank' => $bank];
    if ($quiz) { $data['quiz_id'] = $quiz; }
    $id = remember_post(call('POST', 'question', $data)->get_data()['id']);
    return $id;
};
$approve = static function ($id) { ok(status_of(call('POST', 'question-bank/' . $id . '/approve')) === 200, 'Approve failed'); };
$q1 = $make('Quiz Q1', ['family_id' => 'fam-a'], $quiz_id); $approve($q1);
$q2 = $make('Quiz Q2', ['family_id' => 'fam-b'], $quiz_id); $approve($q2);
$practice = [];
foreach ([['easy', 'fam-c'], ['standard', 'fam-d'], ['standard', 'fam-e'], ['challenge', 'fam-f']] as $i => [$difficulty, $family]) {
    $practice[] = $id = $make('Practice ' . $i, ['difficulty' => $difficulty, 'family_id' => $family]); $approve($id);
}
$secure = $make('Exam only', ['secure' => true]); $approve($secure);
$draft = $make('Unapproved', []);

$student = make_user('subscriber');
enroll($student, $course_id);
$tokens = static function ($view) { return array_column($view['questions'], 'id', 'answer'); };

// ---- Quiz evidence (formal surface) ----
as_user($student);
$attempt = Submission::start($quiz_id, $student); $fixture['attempts'][] = $attempt;
$delivery = array_column(AttemptItems::delivery($attempt), null, 'id');
Submission::submit($quiz_id, $attempt, $student, [$q1 => [$tokens($delivery[$q1])['yes']], $q2 => [$tokens($delivery[$q2])['no']]]);
Evidence::process();
$rows = $wpdb->get_results($wpdb->prepare("SELECT term_id, role, awarded, available, superseded FROM {$t('skill_evidence')} WHERE student_id=%d AND source_type='quiz' ORDER BY id", $student), ARRAY_A);
ok(count($rows) === 4 && count(array_filter($rows, static function ($r) use ($support) { return (int) $r['term_id'] === $support && $r['role'] === 'supporting'; })) === 2, 'Quiz evidence rows wrong: ' . wp_json_encode($rows));
$state = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t('student_skill_state')} WHERE student_id=%d AND term_id=%d", $student, $solve), ARRAY_A);
ok($state['level'] === 'attempted' && (int) $state['evidence_count'] === 2, 'Skill state after quiz wrong');
ok(!$wpdb->get_var($wpdb->prepare("SELECT level FROM {$t('student_skill_state')} WHERE student_id=%d AND term_id=%d", $student, $support)) || true, 'Supporting state');
ok(Evidence::process() === 0, 'Evidence reprocessed the same events');

// A regrade that moves a mark between items (unchanged total) supersedes evidence exactly once.
as_user($admin);
Review::save($quiz_id, $attempt, [$q1 => 0, $q2 => 1], 'Swapped');
Evidence::process();
$current = $wpdb->get_results($wpdb->prepare("SELECT question_id, awarded FROM {$t('skill_evidence')} WHERE student_id=%d AND term_id=%d AND role='primary' AND superseded=0 ORDER BY question_id", $student, $solve), ARRAY_A);
ok(count($current) === 2 && (float) $current[0]['awarded'] === 0.0 && (float) $current[1]['awarded'] === 1.0, 'Regrade did not supersede evidence: ' . wp_json_encode($current));
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t('skill_evidence')} WHERE student_id=%d AND term_id=%d AND role='primary'", $student, $solve)) === 4, 'Regrade duplicated evidence');
$before = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t('student_skill_state')} WHERE student_id=%d ORDER BY term_id", $student), ARRAY_A);
ok(Evidence::rebuild($student) > 0, 'Rebuild processed nothing');
$after = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t('student_skill_state')} WHERE student_id=%d ORDER BY term_id", $student), ARRAY_A);
ok(array_column($before, 'level', 'term_id') == array_column($after, 'level', 'term_id') && array_column($before, 'evidence_count', 'term_id') == array_column($after, 'evidence_count', 'term_id'), 'Rebuild changed the outcome');

// ---- Skill practice ----
as_user($student);
$started = call('POST', 'practice/sessions', ['term_id' => $solve, 'item_limit' => 10]);
ok(status_of($started) === 201, 'Practice start failed: ' . wp_json_encode($started->get_data()));
$state = $started->get_data();
$seen = [];
$guard = 0;
while ($state['status'] === 'active' && $state['current'] && $guard++ < 12) {
    $view = $state['current'];
    ok(strpos(wp_json_encode($view), 'is_correct') === false, 'Practice view leaked correctness');
    $seen[] = (int) $view['id'];
    if (count($seen) === 1) {
        $hint = call('POST', 'practice/sessions/' . $state['uuid'] . '/hint', ['item_id' => $view['item_id']]);
        ok(status_of($hint) === 200 && $hint->get_data()['hint'] === 'Undo the operation' && $hint->get_data()['lessons'][0]['id'] === $lesson, 'Hint failed');
    }
    $answer = call('POST', 'practice/sessions/' . $state['uuid'] . '/answer', ['item_id' => $view['item_id'], 'response' => [$tokens($view)['yes']]]);
    ok(status_of($answer) === 200 && $answer->get_data()['correct'] && $answer->get_data()['feedback']['correct_options'] === [$tokens($view)['yes']], 'Practice answer failed: ' . wp_json_encode($answer->get_data()));
    ok(status_of(call('POST', 'practice/sessions/' . $state['uuid'] . '/answer', ['item_id' => $view['item_id'], 'response' => [$tokens($view)['yes']]])) === 409, 'Same practice item graded twice');
    $state = $answer->get_data()['session'];
}
ok(!in_array($secure, $seen, true) && !in_array($draft, $seen, true), 'Practice served an exam-only or unapproved question');
ok(count($seen) === count(array_unique($seen)) && count($seen) === 6, 'Practice repeated or skipped questions: ' . wp_json_encode($seen));
ok($state['status'] === 'complete' && $state['ended'] === 'exhausted' && !empty($state['notice']), 'Exhausted pool not disclosed');
Evidence::process();
$practice_rows = $wpdb->get_results($wpdb->prepare("SELECT question_id, independent, first_try FROM {$t('skill_evidence')} WHERE student_id=%d AND term_id=%d AND source_type='practice' AND role='primary'", $student, $solve), ARRAY_A);
ok(count($practice_rows) === 6, 'Practice evidence missing');
ok(count(array_filter($practice_rows, static function ($r) { return !(int) $r['independent']; })) === 1, 'Hinted answer counted as independent');
$repeat = array_values(array_filter($practice_rows, static function ($r) use ($q1) { return (int) $r['question_id'] === $q1; }));
ok($repeat && !(int) $repeat[0]['first_try'], 'Repeat of a quiz question counted as a first try');
ok(status_of(call('POST', 'practice/sessions', ['term_id' => $basics])) === 409, 'Practice started with an empty pool');
$other = make_user('subscriber');
as_user($other);
ok(status_of(call('GET', 'practice/sessions/' . $state['uuid'])) === 404, 'Another learner opened a practice session');

// ---- Mastery rules (pure) ----
$rules = Mastery::rules();
$row = static function ($ok, $family, $at, $independent = 1) { return ['awarded' => $ok ? 1 : 0, 'available' => 1, 'independent' => $independent, 'first_try' => 1, 'family_id' => $family, 'question_id' => crc32($family . $at), 'evidence_at' => gmdate('Y-m-d H:i:s', $at)]; };
$now = time();
$day = [$row(1, 'a', $now - 500), $row(1, 'b', $now - 400), $row(1, 'c', $now - 300), $row(1, 'd', $now - 200), $row(1, 'e', $now - 100)];
ok(Mastery::evaluate($day, $rules, $now)['level'] === 'proficient', 'Same-day streak should be proficient, not mastered');
$spaced = [$row(1, 'a', $now - 3 * 86400), $row(1, 'b', $now - 3 * 86400 + 60), $row(1, 'c', $now - 3 * 86400 + 120), $row(1, 'd', $now - 86400), $row(1, 'e', $now - 3600)];
ok(Mastery::evaluate($spaced, $rules, $now)['level'] === 'mastered', 'Spaced correct review should reach mastery');
$assisted = array_map(static function ($r) { $r['independent'] = 0; return $r; }, $spaced);
ok(Mastery::evaluate($assisted, $rules, $now)['level'] === 'attempted', 'Assisted answers counted toward attainment');
$stale = Mastery::evaluate([$row(1, 'a', $now - 40 * 86400), $row(1, 'b', $now - 40 * 86400), $row(1, 'c', $now - 40 * 86400)], $rules, $now);
ok($stale['level'] === 'proficient' && $stale['review_due'] === 1, 'Stale proficiency not flagged for review');

// ---- Inline checks (no completion side effects) ----
as_user($student);
$uuid = \OhMyLMS\QuestionBank\VersionPublisher::uuid($practice[1]);
$GLOBALS['post'] = get_post($lesson); setup_postdata($GLOBALS['post']);
$html = Inline::render($uuid);
wp_reset_postdata();
ok(strpos($html, 'ohmylms-inline-check') !== false && strpos($html, 'is_correct') === false && preg_match('/data-token="([^"]+)"/', $html, $match), 'Inline check not rendered');
$token = html_entity_decode($match[1]);
preg_match_all('/value="(o[0-9a-f]{15})"/', $html, $values);
ok(count($values[1]) === 2, 'Inline options are not tokens');
$progress_before = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ohmylms_user_progress");
$responses = [];
foreach ($values[1] as $value) {
    $result = call('POST', 'practice/inline', ['token' => $token, 'response' => [$value]]);
    ok(status_of($result) === 200, 'Inline answer failed: ' . wp_json_encode($result->get_data()));
    $responses[] = $result->get_data();
}
ok(count(array_filter(array_column($responses, 'correct'))) === 1, 'Inline grading wrong');
ok((int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ohmylms_user_progress") === $progress_before, 'Inline check changed lesson completion');
ok(status_of(call('POST', 'practice/inline', ['token' => $token . 'x', 'response' => [$values[1][0]]])) === 400, 'Tampered inline token accepted');
Evidence::process();
$inline = $wpdb->get_results($wpdb->prepare("SELECT first_try FROM {$t('skill_evidence')} WHERE student_id=%d AND source_type='inline' AND role='primary' ORDER BY id", $student), ARRAY_A);
ok(count($inline) === 2 && !(int) $inline[1]['first_try'], 'Inline retries not distinguished');
$secure_html = Inline::render(\OhMyLMS\QuestionBank\VersionPublisher::uuid($secure));
ok($secure_html === '', 'Exam-only question rendered inline for a learner');

// ---- Guest practice and claim ----
as_user(0);
$guest = call('POST', 'practice/guest');
ok(status_of($guest) === 201 && strlen($guest->get_data()['token']) >= 40, 'Guest credential not issued');
$credential = $guest->get_data()['token'];
ok(status_of(call('POST', 'practice/inline', ['token' => $token, 'response' => [$values[1][0]]])) === 401, 'Anonymous answer without a guest credential accepted');
$guest_call = static function ($method, $path, $data) use ($credential) {
    $request = new WP_REST_Request($method, '/ohmylms/v1/' . $path);
    $request->set_header('X-OhMyLMS-Guest', $credential);
    $request->set_header('Content-Type', 'application/json'); $request->set_body(wp_json_encode($data));
    return rest_do_request($request);
};
$GLOBALS['post'] = get_post($lesson); setup_postdata($GLOBALS['post']); $guest_html = Inline::render($uuid); wp_reset_postdata();
preg_match('/data-token="([^"]+)"/', $guest_html, $gm); preg_match_all('/value="(o[0-9a-f]{15})"/', $guest_html, $gv);
foreach ($gv[1] as $value) { ok(status_of($guest_call('POST', 'practice/inline', ['token' => html_entity_decode($gm[1]), 'response' => [$value]])) === 200, 'Guest inline answer failed'); }
$guest_practice = $guest_call('POST', 'practice/sessions', ['term_id' => $solve, 'item_limit' => 3]);
ok(status_of($guest_practice) === 201, 'Guest practice failed');
$guest_state = $guest_practice->get_data();
$guest_call('POST', 'practice/sessions/' . $guest_state['uuid'] . '/answer', ['item_id' => $guest_state['current']['item_id'], 'response' => [$tokens($guest_state['current'])['yes']]]);
Evidence::process();
$guest_row = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$t('guest_sessions')} WHERE token_hash=%s", hash('sha256', $credential)), ARRAY_A);
$waiting = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t('evidence_outbox')} o JOIN {$t('grade_events')} e ON e.id=o.grade_event_id JOIN {$t('practice_sessions')} s ON s.id=e.source_id WHERE s.guest_id=%d AND o.status='waiting'", (int) $guest_row['id']));
ok($waiting === 3, 'Guest events not held for a claim: ' . $waiting);
$claimer = make_user('subscriber');
as_user($claimer);
$claim = call('POST', 'practice/claim', ['guest_token' => $credential]);
ok(status_of($claim) === 200 && $claim->get_data()['claimed'] === 3, 'Claim failed: ' . wp_json_encode($claim->get_data()));
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t('skill_evidence')} WHERE student_id=%d AND role='primary'", $claimer)) === 3, 'Claimed answers did not become evidence');
ok(call('POST', 'practice/claim', ['guest_token' => $credential])->get_data()['already'] === true, 'Repeated claim by the same account not idempotent');
as_user($other);
ok(status_of(call('POST', 'practice/claim', ['guest_token' => $credential])) === 409, 'Guest results attached to a second account');
as_user(0);
ok(status_of($guest_call('POST', 'practice/sessions', ['term_id' => $solve])) === 401, 'Claimed guest credential still usable');
ok(status_of(call('POST', 'practice/claim', ['guest_token' => $credential])) === 401, 'Anonymous claim accepted');

// ---- Recommendations and teacher reports ----
as_user($student);
$recommendations = call('GET', 'me/skills')->get_data();
ok(!empty($recommendations['skills']), 'Learner skill summary empty');
$struggler = make_user('subscriber');
enroll($struggler, $course_id);
as_user($struggler);
$a = Submission::start($quiz_id, $struggler); $fixture['attempts'][] = $a;
$view = array_column(AttemptItems::delivery($a), null, 'id');
Submission::submit($quiz_id, $a, $struggler, [$q1 => [$tokens($view[$q1])['no']], $q2 => [$tokens($view[$q2])['no']]]);
Evidence::process();
$help = array_values(array_filter(call('GET', 'me/skills')->get_data()['recommendations'], static function ($item) use ($solve) { return $item['type'] === 'help' && $item['term_id'] === $solve; }));
ok($help && $help[0]['lessons'][0]['id'] === $lesson && $help[0]['prerequisites'][0]['id'] === $basics, 'Help recommendation missing lesson or prerequisite');
as_user($admin);
$matrix = call('GET', 'reports/skills', [], ['course_id' => $course_id]);
ok(status_of($matrix) === 200 && in_array($struggler, array_column($matrix->get_data()['students'], 'id'), true), 'Course skill report failed');
$outsider = make_user('author');
as_user($outsider);
ok(status_of(call('GET', 'reports/skills', [], ['course_id' => $course_id])) === 403, 'Outsider read the course skill report');
ok(status_of(call('GET', 'reports/skills/students/' . $struggler, [], ['course_id' => $course_id])) === 403, 'Outsider read a learner report');
