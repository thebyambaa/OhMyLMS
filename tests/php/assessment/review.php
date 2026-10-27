<?php
/** Answer review on the result screen: opt-in switch, what each question type shows, who may see it. */
use OhMyLMS\Assessment\AssessmentSettings;
use OhMyLMS\Assessment\AttemptItems;
use OhMyLMS\Assessment\RevisionPublisher;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Quiz\AnswerReview;
use OhMyLMS\Quiz\Submission;

global $wpdb;
ok(Schema::ready(), 'Assessment schema not installed');

// Answers are shown unless the quiz turns the switch off, and the older "hide answers" switch still wins.
ok(AnswerReview::enabled([]), 'Answers are hidden for a quiz that never set the switch');
ok(AnswerReview::enabled(['show_correct_answers' => null]), 'A missing value did not use the default');
ok(AnswerReview::enabled(['hide_answers' => false]), 'A quiz with other settings lost the default');
ok(AnswerReview::enabled(['show_correct_answers' => true]), 'The switch has no effect');
ok(AnswerReview::enabled(['show_correct_answers' => '1', 'hide_answers' => false]), 'A stored "1" does not count as on');
ok(!AnswerReview::enabled(['show_correct_answers' => false]), 'Turning the switch off did not hide the answers');
ok(!AnswerReview::enabled(['show_correct_answers' => '0']), 'A stored "0" counted as on');
ok(!AnswerReview::enabled(['show_correct_answers' => '']), 'An empty stored value counted as on');
ok(!AnswerReview::enabled(['show_correct_answers' => 'false']), 'A stored "false" counted as on');
ok(!AnswerReview::enabled(['hide_answers' => true]), 'Hide answers did not hide a quiz that never set the switch');
ok(!AnswerReview::enabled(['show_correct_answers' => true, 'hide_answers' => true]), 'Hide answers did not win over the switch');

// Outcome, and which attempt is reviewed: the one asked for if it may be reviewed, otherwise the newest.
ok(AnswerReview::result(23, 23, 50, 'completed') === 'pass', 'Full marks did not pass');
ok(AnswerReview::result(60, 100, 50, 'completed') === 'pass', 'Reaching the passing mark did not pass');
ok(AnswerReview::result(1, 23, 50, 'completed') === 'fail', 'Below the passing mark did not fail');
ok(AnswerReview::result(15, 23, 50, 'in-review') === 'review', 'A teacher-marked attempt was not awaiting review');
// Score and verdict counts also work for an attempt that is not stored (the instructor preview).
$entries_for_summary = [['verdict' => 'correct'], ['verdict' => 'correct'], ['verdict' => 'partial'], ['verdict' => 'incorrect'], ['verdict' => 'pending'], ['verdict' => 'unanswered']];
$preview_summary = AnswerReview::summarize($entries_for_summary, 13.0, 23, 50, 'in-review');
ok($preview_summary['result'] === 'review' && $preview_summary['percent'] === 56.5, 'A preview summary has the wrong outcome or percent');
ok($preview_summary['counts'] === ['correct' => 2, 'partial' => 1, 'incorrect' => 1, 'pending' => 1, 'unanswered' => 1] && $preview_summary['total'] === 6, 'A preview summary has the wrong verdict counts');
ok(AnswerReview::summarize([], 0, 0, 0, 'completed')['percent'] === null, 'A preview with no marks available has a percent');
$reviewable = [['quiz_attempt_id' => 9], ['quiz_attempt_id' => 4]];
ok(AnswerReview::select($reviewable, 4)['quiz_attempt_id'] === 4, 'The requested attempt was not chosen');
ok(AnswerReview::select($reviewable, 7)['quiz_attempt_id'] === 9, 'An attempt that cannot be reviewed was chosen');
ok(AnswerReview::select($reviewable, 0)['quiz_attempt_id'] === 9, 'The newest attempt was not the default');
ok(AnswerReview::select([], 4) === null, 'An attempt was chosen from nothing');

// Links start from the page being viewed (so any placement works), name the attempt, and drop practice unless asked.
$_SERVER['REQUEST_URI'] = '/some-page/?retry=1&review=3&quiz=start&keep=1';
$link = AnswerReview::url(9);
ok(strpos($link, 'review=9') !== false && strpos($link, 'keep=1') !== false, 'A review link lost the attempt or the page: ' . $link);
ok(strpos($link, 'retry') === false && strpos($link, 'quiz=start') === false, 'A review link kept practice or quiz mode: ' . $link);
ok(strpos(AnswerReview::url(9, ['retry' => 1]), 'retry=1') !== false, 'A retry link lost practice');
ok(AnswerReview::for_quiz(0) === '', 'A missing quiz produced a review');
ok(AnswerReview::shortcode([]) === '', 'A review shortcode without a quiz produced output');

[$course_id, $quiz_id] = course_with_quiz($admin, ['allow_attempts' => 9, 'show_correct_answers' => true]);
ok(AnswerReview::enabled(ohmylms_get_quiz($quiz_id)->get_settings()), 'The switch did not survive saving the quiz');

$choice = choice_question($quiz_id, 'Pick the right one', 2);
$create = static function ($name, $type, $score, $options = [], $extra = []) use ($quiz_id) {
    $response = call('POST', 'question', ['quiz_id' => $quiz_id, 'name' => $name, 'settings' => array_merge(['type' => $type, 'score' => ['enabled' => true, 'value' => $score]], $extra), 'questions' => $options]);
    ok(status_of($response) === 201, "Creating $name failed: " . wp_json_encode($response->get_data()));
    return remember_post($response->get_data()['id']);
};
$multi = $create('Pick primes', 'multiple-choice', 3, [['answer' => '2', 'is_correct' => 1], ['answer' => '3', 'is_correct' => 1], ['answer' => '4', 'is_correct' => 0], ['answer' => '9', 'is_correct' => 0]]);
$blank = $create('The capital of France is {Paris}.', 'fill-in-the-blank', 1, [['answer' => 'Paris', 'is_correct' => 1]]);
$number = $create('How long is the line?', 'numerical', 1, [], ['answer' => 3.5, 'unit' => 'cm']);
$essay = $create('Explain why', 'short-text', 4, [['answer' => 'model answer', 'is_correct' => 1]], ['explanation' => '<p>Because of <strong>symmetry</strong>.</p>']);
$match = $create('Match the sound', 'matching', 3, [['answer' => 'Cat', 'matching_data' => ['label' => 'Meow']], ['answer' => 'Dog', 'matching_data' => ['label' => 'Woof']]]);
$skipped = $create('True or false: 2 is even', 'true-false', 1, [['answer' => 'True', 'is_correct' => 1], ['answer' => 'False', 'is_correct' => 0]]);

$student = make_user('subscriber');
$other = make_user('subscriber');
enroll($student, $course_id);
enroll($other, $course_id);
as_user($student);
$attempt = Submission::start($quiz_id, $student);
ok(is_int($attempt), 'Start failed: ' . (is_wp_error($attempt) ? $attempt->get_error_message() : ''));
$fixture['attempts'][] = $attempt;

// Nothing is reviewable while the attempt is still being taken.
$row = ['quiz_attempt_id' => $attempt, 'status' => 'in-progress'];
ok(!AnswerReview::can_review(ohmylms_get_quiz($quiz_id)->get_settings(), $row), 'An unsubmitted attempt can be reviewed');
ok(AnswerReview::for_attempt($attempt) === [], 'An unsubmitted attempt exposes its answers');

$tokens = [];
$definitions = [];
foreach (AttemptItems::delivery($attempt) as $view) {
    foreach ($view['questions'] as $option) { $tokens[$view['id']][$option['answer']] = $option['id']; }
    foreach ($view['definitions'] as $option) { $definitions[$view['id']][$option['matching_data']['label']] = $option['id']; }
}
$result = Submission::submit($quiz_id, $attempt, $student, [
    $choice => [$tokens[$choice]['Right']],
    $multi => [$tokens[$multi]['3'], $tokens[$multi]['9']],
    $blank => ['Lyon'],
    $number => ['3.5'],
    $essay => ['Because it is so'],
    $match => [$definitions[$match]['Meow'] => $tokens[$match]['Cat'], $definitions[$match]['Woof'] => $tokens[$match]['Dog']],
]);
ok(!is_wp_error($result), 'Submit failed: ' . (is_wp_error($result) ? $result->get_error_message() : ''));

$entries_for = static function () use ($attempt) {
    $by = [];
    foreach (AnswerReview::for_attempt($attempt) as $entry) { $by[trim(wp_strip_all_tags($entry['name']))] = $entry; }
    return $by;
};
as_user($student);
$settings = ohmylms_get_quiz($quiz_id)->get_settings();
$row = ['quiz_attempt_id' => $attempt, 'status' => 'in-review'];
ok(AnswerReview::can_review($settings, $row), 'A submitted attempt cannot be reviewed');
ok(!AnswerReview::can_review(['show_correct_answers' => false], $row), 'Review is shown with the switch off');
ok(AnswerReview::can_review([], $row), 'Review is hidden for a quiz that never set the switch');
ok(!AnswerReview::can_review($settings + ['hide_answers' => true], $row), 'Review is shown with hide answers on');

$by = $entries_for();
ok(count($by) === 7, 'Review must list every question the learner was given: ' . wp_json_encode(array_keys($by)));
ok($by['Pick the right one']['verdict'] === 'correct' && $by['Pick the right one']['given'] === ['Right'] && $by['Pick the right one']['expected'] === ['Right'], 'Single choice: ' . wp_json_encode($by['Pick the right one']));
ok((float) $by['Pick the right one']['awarded'] === 2.0 && (float) $by['Pick the right one']['max'] === 2.0, 'Marks of a correct answer');
ok($by['Pick primes']['verdict'] === 'incorrect' && $by['Pick primes']['given'] === ['3', '9'] && $by['Pick primes']['expected'] === ['2', '3'], 'Multiple choice: ' . wp_json_encode($by['Pick primes']));
$inline = $by['The capital of France is _____.'] ?? null;
ok($inline && $inline['verdict'] === 'incorrect' && $inline['given'] === ['Lyon'] && $inline['expected'] === ['Paris'], 'Inline blank: ' . wp_json_encode($inline));
ok(strpos(wp_json_encode(array_keys($by)), 'Paris') === false, 'The prompt of an inline blank gives its answer away');
ok($by['How long is the line?']['verdict'] === 'correct' && $by['How long is the line?']['expected'] === ['3.5 cm'], 'Numerical: ' . wp_json_encode($by['How long is the line?']));
ok($by['Explain why']['verdict'] === 'pending' && $by['Explain why']['given'] === ['Because it is so'], 'A teacher-marked answer must stay pending: ' . wp_json_encode($by['Explain why']));
ok(strpos($by['Explain why']['explanation'], '<strong>symmetry</strong>') !== false, 'Explanation missing');
ok($by['Match the sound']['expected'] === ['Cat → Meow', 'Dog → Woof'] && $by['Match the sound']['given'] === ['Cat → Meow', 'Dog → Woof'], 'Matching: ' . wp_json_encode($by['Match the sound']));
// The true/false question was left out of the submission: it is still listed, as not answered.
ok(($by['True or false: 2 is even']['verdict'] ?? '') === 'unanswered' && $by['True or false: 2 is even']['given'] === [] && $by['True or false: 2 is even']['expected'] === ['True'], 'An unanswered question is mislabeled: ' . wp_json_encode($by['True or false: 2 is even'] ?? null));

// What the learner sees is rendered escaped, with the verdict spelled out.
ob_start();
ohmylms_get_template('single-lesson/quiz-review.php', ['entries' => [['name' => '<script>alert(1)</script><b>Q</b>', 'type' => 'short-text', 'verdict' => 'partial', 'awarded' => 1.0, 'max' => 2.0, 'given' => ['<img src=x onerror=alert(1)>'], 'expected' => ['A & B'], 'explanation' => '<script>x</script><em>Why</em>']]]);
$html = ob_get_clean();
ok(strpos($html, 'Partly correct') !== false && strpos($html, '1 / 2 marks') !== false, 'Verdict or marks missing from the page');
ok(strpos($html, '<script') === false && strpos($html, '<img') === false && strpos($html, 'A &amp; B') !== false && strpos($html, '<em>Why</em>') !== false, 'Review output is not escaped: ' . $html);

// Only the learner who took the attempt can see it.
as_user($other);
ok(AnswerReview::for_attempt($attempt) === [], 'Another learner can read the answers');
as_user(0);
ok(AnswerReview::for_attempt($attempt) === [], 'A guest can read the answers');
as_user($student);

// The review shows the questions as they were when the attempt was taken.
as_user($admin);
$options = option_rows($choice);
call('PUT', 'question/' . $choice, ['name' => 'Edited wording', 'questions' => [['id' => $options[0]['id'], 'answer' => 'Changed', 'is_correct' => 0], ['id' => $options[1]['id'], 'answer' => 'Other', 'is_correct' => 1]]]);
as_user($student);
$by = $entries_for();
ok(isset($by['Pick the right one']) && $by['Pick the right one']['expected'] === ['Right'], 'Editing the question changed a past review: ' . wp_json_encode(array_keys($by)));

// Attempts from before versioning are reviewed against the question as it is now.
$wpdb->delete(Schema::table('attempt_context'), ['attempt_id' => $attempt]);
$legacy = AnswerReview::for_attempt($attempt);
ok(count($legacy) === 7, 'A pre-versioning attempt lost questions: ' . count($legacy));
$legacy_by = [];
foreach ($legacy as $entry) { $legacy_by[trim(wp_strip_all_tags($entry['name']))] = $entry; }
ok($legacy_by['Edited wording']['expected'] === ['Other'] && $legacy_by['Edited wording']['given'] === ['Changed'], 'Legacy single choice: ' . wp_json_encode($legacy_by['Edited wording'] ?? array_keys($legacy_by)));

// An exam whose results are not released yet reveals nothing, even with the switch on.
as_user($admin);
$saved = AssessmentSettings::save($quiz_id, ['feedback_release' => 'manual', 'released' => false]);
ok(!is_wp_error($saved), 'Could not save assessment settings');
RevisionPublisher::publish($quiz_id);
as_user($student);
$held = Submission::start($quiz_id, $student);
ok(is_int($held), 'Second start failed: ' . (is_wp_error($held) ? $held->get_error_message() : ''));
$fixture['attempts'][] = $held;
$submitted = Submission::submit($quiz_id, $held, $student, []);
ok(!is_wp_error($submitted), 'Second submit failed');
$held_row = ['quiz_attempt_id' => $held, 'status' => 'completed'];
ok(!AnswerReview::can_review($settings, $held_row), 'Answers are shown before the results are released');
as_user($admin);
AssessmentSettings::save($quiz_id, ['released' => true]);
as_user($student);
ok(AnswerReview::can_review($settings, $held_row), 'Answers stay hidden after the results are released');

// The latest submitted attempt is the one reviewed; one still in progress is skipped.
$rows = [
    ['quiz_attempt_id' => 3, 'status' => 'completed'],
    ['quiz_attempt_id' => 9, 'status' => 'in-progress'],
    ['quiz_attempt_id' => 7, 'status' => 'in-review'],
];
ok(AnswerReview::latest_submitted($rows)['quiz_attempt_id'] === 7, 'Latest submitted attempt not chosen');
ok(AnswerReview::latest_submitted([['quiz_attempt_id' => 1, 'status' => 'in-progress']]) === null, 'An unsubmitted attempt was chosen');
