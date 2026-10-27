<?php
/** Phase 4: decimal scoring, autosave/resume, UTC deadlines and finalization, exam settings. */
use OhMyLMS\Assessment\AssessmentSettings;
use OhMyLMS\Assessment\AttemptItems;
use OhMyLMS\Assessment\Deadlines;
use OhMyLMS\Assessment\Migration;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Assessment\Scoring;
use OhMyLMS\Quiz\Submission;

global $wpdb;
as_user($admin);
if (!\OhMyLMS\Extensions\Registry::get('question', 'test-half')) {
    ohmylms_register_question_type('test-half', ['label' => 'Half credit', 'snapshot' => true, 'render' => '__return_null',
        'validate' => static function ($answer) { return is_array($answer); },
        'grade' => static function ($answer) { $value = (string) reset($answer); return ['correct' => $value === 'full', 'fraction' => $value === 'full' ? 1 : ($value === 'half' ? 0.5 : 0), 'manual' => false]; }]);
}
[$course_id, $quiz_id] = course_with_quiz($admin, ['allow_attempts' => 20, 'time_limit' => ['value' => 10, 'type' => 'minutes'], 'passing_grade' => ['enabled' => true, 'value' => 1]]);
$choice = choice_question($quiz_id, 'Pick right', 2);
$half = remember_post(call('POST', 'question', ['quiz_id' => $quiz_id, 'name' => 'Partial', 'settings' => ['type' => 'test-half', 'score' => ['enabled' => true, 'value' => 3]]])->get_data()['id']);
$student = make_user('subscriber');
$other = make_user('subscriber');
enroll($student, $course_id);
enroll($other, $course_id);

// A legacy-int attempt started before the decimal migration keeps its policy.
as_user($student);
$wasDecimal = get_option(Scoring::MIGRATED_OPTION);
delete_option(Scoring::MIGRATED_OPTION);
$legacy_policy = Submission::start($quiz_id, $student); $fixture['attempts'][] = $legacy_policy;
ok(AttemptItems::context($legacy_policy)['scoring'] === Scoring::LEGACY, 'Pre-migration attempt not legacy-int');

// ---- Decimal scoring migration ----
ok(Migration::migrate_decimal_scores() === true && get_option(Scoring::MIGRATED_OPTION) === '1', 'Decimal migration failed');
ok(strtolower($wpdb->get_var("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$wpdb->prefix}ohmylms_quiz_attempts' AND COLUMN_NAME='total'")) === 'decimal', 'Total column not decimal');
$tokens = static function ($attempt, $question) {
    foreach (AttemptItems::delivery($attempt) as $view) { if ((int) $view['id'] === (int) $question) { return array_column($view['questions'], 'id', 'answer'); } }
    return [];
};
$result = Submission::submit($quiz_id, $legacy_policy, $student, [$choice => [$tokens($legacy_policy, $choice)['Right']], $half => ['half']]);
ok(!is_wp_error($result) && $result['total'] === 4, 'Legacy-int attempt did not round half credit as before (2 + round(1.5)=4): ' . wp_json_encode($result));
$decimal = Submission::start($quiz_id, $student); $fixture['attempts'][] = $decimal;
ok(AttemptItems::context($decimal)['scoring'] === Scoring::DECIMAL, 'New attempt not on decimal scoring');
$result = Submission::submit($quiz_id, $decimal, $student, [$choice => [$tokens($decimal, $choice)['Right']], $half => ['half']]);
ok(!is_wp_error($result) && (float) $result['total'] === 3.5 && (float) $wpdb->get_var($wpdb->prepare("SELECT total FROM {$wpdb->prefix}ohmylms_quiz_attempts WHERE id=%d", $decimal)) === 3.5, 'Decimal total not stored: ' . wp_json_encode($result));
as_user($admin);
$report = call('GET', 'quiz/' . $quiz_id . '/report')->get_data()['report'];
$row = array_values(array_filter($report, static function ($r) use ($decimal) { return (int) $r['quiz_attempt_id'] === $decimal; }))[0];
ok($row['total_marks'] === 3.5, 'Report did not expose a numeric decimal total');
// Manual review honors decimal policy on new attempts.
$reviewed = \OhMyLMS\Quiz\Review::save($quiz_id, $decimal, [$half => 2.25]);
ok((float) $reviewed['total_achieved_marks'] === 4.25, 'Decimal manual mark rounded');
if ($wasDecimal !== '1') { /* keep migrated: the migration is one-way by design */ }

// ---- Autosave and resume ----
as_user($student);
$attempt = Submission::start($quiz_id, $student); $fixture['attempts'][] = $attempt;
$choice_tokens = $tokens($attempt, $choice);
$save = call('POST', 'attempts/' . $attempt . '/responses', ['question_id' => $choice, 'response' => [$choice_tokens['Wrong']], 'sequence' => 100]);
ok(status_of($save) === 200 && $save->get_data()['accepted'], 'Autosave failed: ' . wp_json_encode($save->get_data()));
$save = call('POST', 'attempts/' . $attempt . '/responses', ['question_id' => $choice, 'response' => [$choice_tokens['Right']], 'sequence' => 200]);
$stale = call('POST', 'attempts/' . $attempt . '/responses', ['question_id' => $choice, 'response' => [$choice_tokens['Wrong']], 'sequence' => 150]);
ok($stale->get_data()['accepted'] === false, 'An older autosave overwrote a newer one');
call('POST', 'attempts/' . $attempt . '/responses', ['question_id' => $half, 'response' => ['full'], 'sequence' => 300]);
$resume = call('GET', 'attempts/' . $attempt)->get_data();
ok($resume['responses']->{$choice}['response'] === [$choice_tokens['Right']] && $resume['remaining_seconds'] > 500, 'Resume did not return the latest tokenized answer');
ok(status_of(call('POST', 'attempts/' . $attempt . '/responses', ['question_id' => 999999, 'response' => ['x']])) === 400, 'Autosave accepted a foreign question');
as_user($other);
ok(status_of(call('GET', 'attempts/' . $attempt)) === 404 && status_of(call('POST', 'attempts/' . $attempt . '/responses', ['question_id' => $choice, 'response' => []])) === 404, 'Another learner reached the attempt');
as_user(0);
ok(status_of(call('GET', 'attempts/' . $attempt)) === 401, 'Guest reached the attempt');

// ---- Closed browser: deadline passes, cron finalizes with saved answers only ----
$wpdb->update(Schema::table('attempt_context'), ['deadline_at' => gmdate('Y-m-d H:i:s', time() - 120)], ['attempt_id' => $attempt]);
as_user($student);
ok(status_of(call('POST', 'attempts/' . $attempt . '/responses', ['question_id' => $choice, 'response' => [$choice_tokens['Wrong']], 'sequence' => 999])) === 409, 'Autosave accepted after the deadline');
as_user(0);
ok(Deadlines::finalize_due() >= 1, 'Finalizer did not run');
$row = $wpdb->get_row($wpdb->prepare("SELECT status, total FROM {$wpdb->prefix}ohmylms_quiz_attempts WHERE id=%d", $attempt), ARRAY_A);
ok($row['status'] === 'completed' && (float) $row['total'] === 5.0, 'Expired attempt not finalized from saved answers: ' . wp_json_encode($row));
ok(AttemptItems::context($attempt)['finalize_reason'] === 'timeout', 'Finalize reason not recorded');
ok(Deadlines::finalize_due() === 0, 'Finalizer processed an attempt twice');

// ---- Late submission: after deadline + grace, supplied answers are ignored ----
as_user($student);
$late = Submission::start($quiz_id, $student); $fixture['attempts'][] = $late;
$late_tokens = $tokens($late, $choice);
call('POST', 'attempts/' . $late . '/responses', ['question_id' => $choice, 'response' => [$late_tokens['Wrong']], 'sequence' => 1]);
$wpdb->update(Schema::table('attempt_context'), ['deadline_at' => gmdate('Y-m-d H:i:s', time() - 600)], ['attempt_id' => $late]);
$result = Submission::submit($quiz_id, $late, $student, [$choice => [$late_tokens['Right']], $half => ['full']]);
ok(!is_wp_error($result) && $result['reason'] === 'timeout' && (float) $result['total'] === 0.0, 'Late submission graded its supplied answers: ' . wp_json_encode($result));
// Within the grace window the submitted answers still count.
$grace = Submission::start($quiz_id, $student); $fixture['attempts'][] = $grace;
$grace_tokens = $tokens($grace, $choice);
$wpdb->update(Schema::table('attempt_context'), ['deadline_at' => gmdate('Y-m-d H:i:s', time() - 5), 'grace_seconds' => 15], ['attempt_id' => $grace]);
$result = Submission::submit($quiz_id, $grace, $student, [$choice => [$grace_tokens['Right']]]);
ok(!is_wp_error($result) && $result['reason'] === 'timeout' && (float) $result['total'] === 2.0, 'Answer sent within grace was rejected');

// Starting again after an unattended expiry finalizes the old attempt first.
$abandoned = Submission::start($quiz_id, $student); $fixture['attempts'][] = $abandoned;
$wpdb->update(Schema::table('attempt_context'), ['deadline_at' => gmdate('Y-m-d H:i:s', time() - 600)], ['attempt_id' => $abandoned]);
$next = Submission::start($quiz_id, $student); $fixture['attempts'][] = $next;
ok(is_int($next) && $next !== $abandoned && $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}ohmylms_quiz_attempts WHERE id=%d", $abandoned)) === 'completed', 'Expired attempt was resumed instead of finalized');
Submission::submit($quiz_id, $next, $student, [], 'exit');

// ---- Exam settings: accommodations, availability, sections, feedback release ----
as_user($admin);
$settings = call('PUT', 'quiz/' . $quiz_id . '/assessment-settings', ['preset' => 'exam', 'accommodations' => [$student => 300],
    'sections' => [['title' => 'Section B', 'questions' => [$choice], 'marks' => [$choice => 4]], ['title' => 'Section A', 'questions' => [$half]]]]);
ok(status_of($settings) === 200 && $settings->get_data()['settings']['kind'] === 'exam' && $settings->get_data()['settings']['feedback_release'] === 'after_close', 'Exam preset not applied: ' . wp_json_encode($settings->get_data()));
ok(status_of(call('PUT', 'quiz/' . $quiz_id . '/assessment-settings', ['sections' => [['title' => 'X', 'questions' => [999999]]]])) === 400, 'Foreign section question accepted');
ok(status_of(call('PUT', 'quiz/' . $quiz_id . '/assessment-settings', ['available_from' => '2030-01-02 10:00', 'available_until' => '2030-01-01 10:00'])) === 400, 'Closing before opening accepted');
as_user($student);
$exam = Submission::start($quiz_id, $student); $fixture['attempts'][] = $exam;
$context = AttemptItems::context($exam);
ok((int) $context['extra_seconds'] === 300 && strtotime($context['deadline_at'] . ' UTC') - strtotime($context['started_at'] . ' UTC') === 900, 'Extra time not recorded/applied');
$delivery = AttemptItems::delivery($exam);
ok($delivery[0]['section'] === 'Section B' && (float) $delivery[0]['marks'] === 4.0 && $delivery[1]['section'] === 'Section A', 'Sections/slot marks not frozen in order');
$exam_tokens = $tokens($exam, $choice);
Submission::submit($quiz_id, $exam, $student, [$choice => [$exam_tokens['Right']], $half => ['full']]);
ok(!AssessmentSettings::feedback_visible($exam), 'Exam results visible before release');
as_user($admin);
call('PUT', 'quiz/' . $quiz_id . '/assessment-settings', ['released' => true]);
ok(AssessmentSettings::feedback_visible($exam), 'Released results still hidden');
// A late start ends at the closing time, not after the full time limit plus extra time.
$closes_at = gmdate('Y-m-d H:i:s', time() + 120);
call('PUT', 'quiz/' . $quiz_id . '/assessment-settings', ['available_until' => $closes_at]);
as_user($student);
$capped = Submission::start($quiz_id, $student); $fixture['attempts'][] = $capped;
ok(AttemptItems::context($capped)['deadline_at'] === $closes_at && Deadlines::remaining(AttemptItems::context($capped)) <= 120, 'Attempt deadline not capped at the closing time');
Submission::submit($quiz_id, $capped, $student, [], 'exit');
as_user($admin);
call('PUT', 'quiz/' . $quiz_id . '/assessment-settings', ['available_from' => gmdate('Y-m-d H:i:s', time() + 3600), 'available_until' => '']);
as_user($student);
$closed = Submission::start($quiz_id, $student);
ok(is_wp_error($closed) && $closed->get_error_code() === 'quiz_not_open', 'Exam started before opening time');
as_user($admin);
call('PUT', 'quiz/' . $quiz_id . '/assessment-settings', ['available_from' => '', 'available_until' => gmdate('Y-m-d H:i:s', time() - 60)]);
as_user($student);
$closed = Submission::start($quiz_id, $student);
ok(is_wp_error($closed) && $closed->get_error_code() === 'quiz_closed', 'Exam started after closing time');
ok(AssessmentSettings::feedback_visible($exam), 'after_close feedback hidden after close');
