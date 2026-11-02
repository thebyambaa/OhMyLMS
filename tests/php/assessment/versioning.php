<?php
/** Phase 2: UUID/versions, revisions, frozen attempt delivery, snapshot grading and reports. */
use OhMyLMS\Assessment\AttemptItems;
use OhMyLMS\Assessment\GradeEvents;
use OhMyLMS\Assessment\Migration;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Quiz\Review;
use OhMyLMS\Quiz\Submission;
use OhMyLMS\QuestionBank\VersionPublisher;

global $wpdb;
ok(Schema::ready(), 'Assessment schema not installed');

// ---- A04: identities, versions and the resumable migration ----
as_user($admin);
$legacy_question = remember_post(wp_insert_post(['post_type' => OHMYLMS_QUESTION_CPT, 'post_title' => 'Pre-versioning question', 'post_status' => 'publish']));
update_post_meta($legacy_question, '_question_settings', ['type' => 'true-false', 'score' => ['enabled' => true, 'value' => 1]]);
ok(!VersionPublisher::identity($legacy_question, false), 'Legacy fixture already had an identity');
delete_option(Migration::OPTION);
$state = Migration::run_all();
ok($state['status'] === 'done' && (int) $state['version'] === Migration::VERSION, 'Migration did not complete: ' . wp_json_encode($state));
$identity = VersionPublisher::identity($legacy_question, false);
ok($identity && preg_match('/^[0-9a-f-]{36}$/', $identity['uuid']), 'Migration did not assign a UUID');
$version = VersionPublisher::version($identity['current_version_id']);
ok($version && (int) $version['is_migration_snapshot'] === 1 && $version['title'] === 'Pre-versioning question', 'Migration snapshot missing or unlabeled');
$again = Migration::run_all();
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . Schema::table('qb_question_versions') . " WHERE question_id=%d", $legacy_question)) === 1, 'Re-running the migration created duplicate versions');
$inventory = Migration::inventory();
ok($inventory['questions_with_identity'] >= 1 && isset($inventory['orphan_options']), 'Inventory incomplete');

// Saving identical content reuses the version; a change creates the next one.
[$course_id, $quiz_id] = course_with_quiz($admin, ['allow_attempts' => 5, 'passing_grade' => ['enabled' => true, 'value' => 1], 'randomize_questions' => true]);
$choice = choice_question($quiz_id, 'Original wording', 2);
$first = VersionPublisher::identity($choice);
ok(status_of(edit_question($choice, ['name' => 'Original wording'])) === 200, 'Unchanged save failed');
ok((int) VersionPublisher::identity($choice)['current_version_id'] === (int) $first['current_version_id'], 'Unchanged save created a version');
$multi = new_question(['quiz_id' => $quiz_id, 'name' => 'Pick primes', 'settings' => ['type' => 'multiple-choice', 'score' => ['enabled' => true, 'value' => 3], 'randomize' => true],
    'questions' => [['answer' => '2', 'is_correct' => 1], ['answer' => '3', 'is_correct' => 1], ['answer' => '4', 'is_correct' => 0], ['answer' => '9', 'is_correct' => 0]]]);
$essay = new_question(['quiz_id' => $quiz_id, 'name' => 'Explain', 'settings' => ['type' => 'short-text', 'score' => ['enabled' => true, 'value' => 4]], 'questions' => [['answer' => 'model answer', 'is_correct' => 1]]]);
$match = new_question(['quiz_id' => $quiz_id, 'name' => 'Match', 'settings' => ['type' => 'matching', 'score' => ['enabled' => true, 'value' => 1]],
    'questions' => [['answer' => 'Cat', 'matching_data' => ['label' => 'Meow']], ['answer' => 'Dog', 'matching_data' => ['label' => 'Woof']], ['answer' => 'Cow', 'matching_data' => ['label' => 'Moo']]]]);

// ---- A05: frozen delivery ----
$student = make_user('subscriber');
enroll($student, $course_id);
as_user($student);
$attempt = Submission::start($quiz_id, $student);
ok(is_int($attempt), 'Versioned start failed: ' . (is_wp_error($attempt) ? $attempt->get_error_message() : ''));
$fixture['attempts'][] = $attempt;
$context = AttemptItems::context($attempt);
ok($context && $context['engine'] === 'versioned' && (int) $context['revision_id'] > 0, 'Attempt context not created');
$items = AttemptItems::items($attempt);
ok(count($items) === 4, 'Attempt did not freeze every question');
$delivery = AttemptItems::delivery($attempt);
ok(wp_json_encode($delivery) === wp_json_encode(AttemptItems::delivery($attempt)), 'Delivery changes between page loads');
$leak = wp_json_encode($delivery);
ok(strpos($leak, 'is_correct') === false && strpos($leak, 'model answer') === false, 'Delivery exposes answer keys');
$by_question = [];
foreach ($delivery as $view) { $by_question[$view['id']] = $view; }
foreach ($by_question[$choice]['questions'] as $option) { ok(!is_numeric($option['id']) && strlen($option['id']) === 16, 'Option IDs are not opaque tokens'); }
ok(count($by_question[$match]['definitions']) === 3, 'Matching definitions not frozen');
ok(!array_intersect(array_column($by_question[$match]['definitions'], 'id'), array_column($by_question[$match]['questions'], 'id')), 'Matching definitions share tokens with their answers');
ob_start(); \OhMyLMS\Extensions\QuestionTypes::render($by_question[$match], ['id' => $attempt]); $html = ob_get_clean();
ok(strpos($html, 'data-option-id="' . $by_question[$match]['questions'][0]['id'] . '"') !== false, 'Matching template did not render tokens');

// Teacher edits everything after the learner started.
as_user($admin);
$choice_options = option_rows($choice);
$edited = edit_question($choice, ['name' => 'Edited wording', 'settings' => ['type' => 'single-choice', 'score' => ['enabled' => true, 'value' => 10]],
    'questions' => [['id' => (int) $choice_options[0]['id'], 'answer' => 'Right', 'is_correct' => 0], ['id' => (int) $choice_options[1]['id'], 'answer' => 'Wrong', 'is_correct' => 1]]]);
ok(status_of($edited) === 200, 'Teacher edit failed: ' . wp_json_encode($edited->get_data()));
$wpdb->delete($wpdb->prefix . 'ohmylms_quiz_questions_relationship', ['quiz_id' => $quiz_id, 'question_id' => $multi]);
ok((int) VersionPublisher::identity($choice)['current_version_id'] !== (int) $first['current_version_id'], 'Edit did not create a new version');

as_user($student);
$after_edit = AttemptItems::delivery($attempt);
ok(wp_json_encode($after_edit) === wp_json_encode($delivery), 'Editing the question changed an in-progress delivery');
$tokens = [];
foreach ($by_question[$choice]['questions'] as $option) { $tokens[$option['answer']] = $option['id']; }
$multi_tokens = [];
foreach ($by_question[$multi]['questions'] as $option) { $multi_tokens[$option['answer']] = $option['id']; }
$match_options = []; foreach ($by_question[$match]['questions'] as $option) { $match_options[$option['answer']] = $option['id']; }
$match_defs = []; foreach ($by_question[$match]['definitions'] as $option) { $match_defs[$option['matching_data']['label']] = $option['id']; }
$numeric = (string) $choice_options[1]['id']; // correct in the current version
$result = Submission::submit($quiz_id, $attempt, $student, [
    $choice => [$tokens['Right']],
    $multi => [$multi_tokens['3'], $multi_tokens['2']],
    $essay => ['Because it is so'],
    $match => [$match_defs['Meow'] => $match_options['Cat'], $match_defs['Woof'] => $match_options['Dog'], $match_defs['Moo'] => $match_options['Cow']],
]);
ok(!is_wp_error($result), 'Versioned submit failed: ' . (is_wp_error($result) ? $result->get_error_message() : ''));
ok($result['status'] === 'in-review' && (float) $result['total'] === 6.0, 'Snapshot grading wrong (expected original key and marks 2+3+1): ' . wp_json_encode($result));
$graded = []; foreach (AttemptItems::items($attempt) as $item) { $graded[$item['question_id']] = $item; }
ok($graded[$choice]['status'] === 'graded' && (float) $graded[$choice]['awarded'] === 2.0 && (int) $graded[$choice]['correct'] === 1, 'Choice item not graded against its frozen version');
ok($graded[$essay]['status'] === 'needs-review', 'Manual item not left for review');
ok(GradeEvents::current('quiz', $attempt, (int) $graded[$choice]['id'])['grader'] === 'auto', 'No grade event for auto-graded item');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . Schema::table('evidence_outbox') . " o JOIN " . Schema::table('grade_events') . " e ON e.id=o.grade_event_id WHERE e.source_id=%d AND e.source_type='quiz'", $attempt)) === 3, 'Evidence outbox rows missing');

// Report shows what was seen, not current content.
as_user($admin);
$report = ohmylms_get_quiz($quiz_id)->get_attempt_report($attempt);
$report_by = array_column($report['questions'], null, 'id');
ok($report['engine'] === 'versioned' && $report_by[$choice]['name'] === 'Original wording' && (float) $report_by[$choice]['settings']['score']['value'] === 2.0, 'Report uses current content');
ok(isset($report_by[$multi]) && !$report_by[$choice]['version']['is_current'], 'Report lost a removed question or version flag');
ok(status_of(call('GET', 'quizzes/' . $quiz_id . '/attempts/' . $attempt)) === 200, 'Attempt endpoint failed for versioned attempt');

// Manual review: grade the essay, then move marks without changing the total.
$reviewed = Review::save($quiz_id, $attempt, [$essay => 3], 'Good reasoning');
ok(!is_wp_error($reviewed) && $reviewed['status'] === 'completed' && (float) $reviewed['total_achieved_marks'] === 9.0, 'Manual review failed: ' . wp_json_encode(is_wp_error($reviewed) ? $reviewed->get_error_message() : $reviewed));
$essay_item = (int) $graded[$essay]['id'];
$choice_item = (int) $graded[$choice]['id'];
$reviewed = Review::save($quiz_id, $attempt, [$essay => 4, $choice => 1], 'Moved a mark');
ok(!is_wp_error($reviewed) && (float) $reviewed['total_achieved_marks'] === 9.0, 'Total changed when marks moved between items');
ok(count(GradeEvents::history('quiz', $attempt, $essay_item)) === 2 && count(GradeEvents::history('quiz', $attempt, $choice_item)) === 2, 'Regrade with unchanged total did not record per-item events');
ok((float) GradeEvents::current('quiz', $attempt, $choice_item)['awarded'] === 1.0 && GradeEvents::current('quiz', $attempt, $choice_item)['reason'] === 'Moved a mark', 'Superseding event wrong');

// A later attempt gets a new revision containing the edited version.
as_user($student);
$second = Submission::start($quiz_id, $student);
$fixture['attempts'][] = $second;
ok(is_int($second) && (int) AttemptItems::context($second)['revision_id'] !== (int) $context['revision_id'], 'New attempt reused the stale revision');
ok(count(AttemptItems::items($second)) === 3, 'New revision does not reflect removed question');
Submission::submit($quiz_id, $second, $student, [], 'exit');

// Numeric option IDs (or another attempt's tokens) never match.
$third = Submission::start($quiz_id, $student);
$fixture['attempts'][] = $third;
$result = Submission::submit($quiz_id, $third, $student, [$choice => [$numeric], $essay => ['x'], $match => []]);
$third_items = []; foreach (AttemptItems::items($third) as $item) { $third_items[$item['question_id']] = $item; }
ok(!is_wp_error($result) && (int) $third_items[$choice]['correct'] === 0, 'A raw option ID was accepted as a token');

// Legacy attempts finish on the legacy engine.
$wpdb->insert($wpdb->prefix . 'ohmylms_quiz_attempts', ['quiz_id' => $quiz_id, 'student_id' => $student, 'course_id' => $course_id, 'total' => 0, 'status' => 'in-progress', 'start_date' => current_time('mysql')]);
$legacy_attempt = (int) $wpdb->insert_id; $fixture['attempts'][] = $legacy_attempt;
$result = Submission::submit($quiz_id, $legacy_attempt, $student, [$choice => [(string) $choice_options[1]['id']], $essay => ['legacy']]);
ok(!is_wp_error($result) && !AttemptItems::is_versioned($legacy_attempt) && (float) $result['total'] === 10.0, 'Legacy attempt did not use legacy grading: ' . wp_json_encode($result));
as_user($admin);
ok(ohmylms_get_quiz($quiz_id)->get_attempt_report($legacy_attempt)['engine'] === 'legacy', 'Legacy report not labeled');

// A question type without the version-aware contract blocks publication.
ohmylms_register_question_type('legacy-only-' . strtolower(wp_generate_password(6, false, false)), ['label' => 'Legacy only', 'render' => '__return_null', 'validate' => '__return_true', 'grade' => static function () { return ['correct' => true, 'fraction' => 1, 'manual' => false]; }]);
$types = \OhMyLMS\Extensions\Registry::all('question'); end($types); $legacy_type = key($types);
$blocked = remember_post(wp_insert_post(['post_type' => OHMYLMS_QUESTION_CPT, 'post_title' => 'Blocked', 'post_status' => 'publish']));
update_post_meta($blocked, '_question_settings', ['type' => $legacy_type]);
\OhMyLMS\QuestionBank\DraftWriter::link($quiz_id, $blocked, 99);
$response = call('POST', 'quiz/' . $quiz_id . '/revisions');
ok(status_of($response) === 409 && $response->get_data()['code'] === 'quiz_type_unversioned', 'Unversioned type was published');
$wpdb->delete($wpdb->prefix . 'ohmylms_quiz_questions_relationship', ['question_id' => $blocked]);

// LaTeX survives authoring.
$latex = new_question(['name' => 'Solve \\(\\frac{1}{2}x=3\\)', 'description' => '<p>\\(\\sqrt{x}\\)</p>', 'settings' => ['type' => 'true-false'], 'questions' => [['answer' => 'True', 'is_correct' => 1], ['answer' => 'False']]]);
ok(get_post_field('post_title', $latex) === 'Solve \\(\\frac{1}{2}x=3\\)' && strpos(get_post_field('post_content', $latex), '\\sqrt') !== false, 'LaTeX backslashes were stripped');

// The real student template renders the frozen delivery (no answer keys, tokens only).
as_user($student);
$render_attempt = Submission::start($quiz_id, $student);
$fixture['attempts'][] = $render_attempt;
$GLOBALS['post'] = get_post($quiz_id); setup_postdata($GLOBALS['post']);
ob_start(); ohmylms_get_template('single-lesson/quiz-form.php'); $page = ob_get_clean();
wp_reset_postdata();
ok(strpos($page, 'data-attempt-engine="versioned"') !== false, 'Quiz form did not use the versioned delivery');
ok(strpos($page, 'Edited wording') !== false && strpos($page, 'is_correct') === false, 'Quiz form rendered wrong content or an answer key');
foreach (option_rows($choice) as $row) { ok(strpos($page, 'value="' . $row['id'] . '"') === false, 'Quiz form exposed a raw option ID'); }
Submission::submit($quiz_id, $render_attempt, $student, [], 'exit');
