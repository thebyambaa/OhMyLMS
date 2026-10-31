<?php
/** Mixed-skill assessments: the builder, the skill-based draw, the intro screen and level changes on the result. */
use OhMyLMS\Assessment\AssessmentSettings;
use OhMyLMS\Assessment\AttemptItems;
use OhMyLMS\Assessment\Pools;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Assessment\SkillAssessment;
use OhMyLMS\Assessment\SkillAssessmentPage;
use OhMyLMS\Quiz\AnswerReview;
use OhMyLMS\Quiz\Intro;
use OhMyLMS\Quiz\Submission;
use OhMyLMS\Performance\Engine;
use OhMyLMS\Performance\Settings;
use OhMyLMS\Skills\Evidence;
use OhMyLMS\Skills\Taxonomy;

global $wpdb;
$run = wp_generate_password(6, false, false);
$previous_setting = get_option(Settings::OPTION, null);
register_shutdown_function(static function () use ($previous_setting) {
    if ($previous_setting === null) { delete_option(Settings::OPTION); } else { update_option(Settings::OPTION, $previous_setting); }
});
as_user($admin);

// ---- Three skills with three questions each; the letter in the name is the skill ----
$skills = [];
foreach (['A', 'B', 'C'] as $letter) {
    $skills[$letter] = (int) call('POST', 'skills', ['name' => "Check skill $letter $run", 'public_practice' => true])->get_data()['id'];
    $fixture['terms'][] = [$skills[$letter], Taxonomy::NAME];
    for ($n = 1; $n <= 3; $n++) {
        $response = call('POST', 'question', ['name' => "$letter$n $run", 'description' => "Pick $letter$n $run",
            'settings' => ['type' => 'single-choice', 'score' => ['enabled' => true, 'value' => 1]],
            'questions' => [['answer' => 'Right', 'is_correct' => 1, 'order_number' => 1], ['answer' => 'Wrong', 'is_correct' => 0, 'order_number' => 2]],
            'skills' => ['p1' => ['primary' => $skills[$letter]]], 'bank' => ['family_id' => "$letter$n-$run"]]);
        ok(status_of($response) === 201, "Creating question $letter$n failed: " . wp_json_encode($response->get_data()));
        $id = remember_post($response->get_data()['id']);
        ok(status_of(call('POST', 'questions/' . $id . '/approve')) === 200, "Approving $letter$n failed");
    }
}
$empty = (int) call('POST', 'skills', ['name' => "Check skill empty $run", 'public_practice' => true])->get_data()['id'];
$fixture['terms'][] = [$empty, Taxonomy::NAME];

// ---- A unit with the assessment placed in it ----
$course = call('POST', 'courses', ['name' => 'Skill check course', 'status' => 'publish']);
$course_id = remember_post($course->get_data()['id']);
$chapter = remember_post(wp_insert_post(['post_type' => 'ohmylms-chapter', 'post_title' => 'Skill check unit', 'post_status' => 'publish']));
$wpdb->insert($wpdb->prefix . 'ohmylms_chapter_relationship', ['course_id' => $course_id, 'chapter_id' => $chapter, 'order_number' => 0]);

// ---- Builder: input checks ----
$bad = [
    ['style' => 'exam-ish', 'skills' => [$skills['A']]], ['skills' => []], ['skills' => [$skills['A']], 'per_skill' => 0], ['skills' => [$skills['A']], 'per_skill' => 11],
    ['skills' => [999999999]], ['skills' => range(1, 41)],
];
foreach ($bad as $i => $payload) {
    $response = call('POST', 'skill-assessments', $payload);
    ok(status_of($response) === 400, "Bad payload $i was accepted: " . wp_json_encode($response->get_data()));
}
$quizzes_before = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='" . OHMYLMS_QUIZ_CPT . "'");
$short = call('POST', 'skill-assessments', ['title' => "Short $run", 'skills' => [$skills['A'], $empty], 'per_skill' => 1, 'publish' => true]);
ok(status_of($short) === 409 && $short->get_data()['code'] === 'quiz_pool_shortage', 'A skill with no questions did not stop the build: ' . wp_json_encode($short->get_data()));
ok((int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='" . OHMYLMS_QUIZ_CPT . "'") === $quizzes_before, 'A failed build left a quiz behind');
$too_many = call('POST', 'skill-assessments', ['skills' => [$skills['A'], $skills['B'], $skills['C']], 'per_skill' => 4, 'publish' => true]);
ok(status_of($too_many) === 409, 'Asking for more questions than exist was accepted: ' . status_of($too_many));
as_user($admin);

// ---- Builder: a unit test ----
$created = call('POST', 'skill-assessments', ['title' => "Unit test $run", 'style' => 'unit-test', 'skills' => array_values($skills), 'per_skill' => 1, 'publish' => true, 'chapter_id' => $chapter, 'time_limit' => 0]);
ok(status_of($created) === 201, 'Unit test not created: ' . wp_json_encode($created->get_data()));
$made = $created->get_data();
$quiz_id = remember_post($made['id']);
ok($made['questions'] === 3 && $made['skills'] === 3 && $made['style'] === 'unit-test' && $made['status'] === 'publish', 'Builder summary wrong: ' . wp_json_encode($made));
ok(AssessmentSettings::get($quiz_id)['style'] === 'unit-test', 'The style was not saved');
ok(array_column(Pools::rules($quiz_id), 'term_id') === array_values($skills), 'One pool per skill was not saved');
ok(array_unique(array_column(Pools::rules($quiz_id), 'count')) === [1], 'The pool counts are not the requested count');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ohmylms_content_relationship WHERE chapter_id=%d AND content_id=%d AND content_type='quiz'", $chapter, $quiz_id)) === 1, 'The unit test was not placed in its unit');
$learner = make_user('subscriber');
as_user($learner);
ok(status_of(call('POST', 'skill-assessments', ['skills' => [$skills['A']]])) === 403, 'A learner built an assessment');
as_user($admin);

// ---- The intro screen ----
$quiz = ohmylms_get_quiz($quiz_id);
$summary = Intro::summary($quiz);
ok($summary['questions'] === 3 && $summary['skills'] === 3 && $summary['style'] === 'unit-test' && $summary['mixed'] && !$summary['timed'] && $summary['minutes_min'] === 3 && $summary['minutes_max'] === 6, 'Intro summary wrong: ' . wp_json_encode($summary));
ok(Intro::applies($summary), 'A unit test does not get the intro');
ok(Intro::length($summary) === '3 questions · 3-6 minutes', 'Intro length wrong: ' . Intro::length($summary));
ok(Intro::length(['questions' => 1, 'minutes_min' => 20, 'minutes_max' => 20]) === '1 question · 20 minutes', 'A timed single question reads wrong');
$html = Intro::render($quiz);
ok(strpos($html, 'All set for the unit test?') !== false && strpos($html, '3 questions') !== false && strpos($html, 'Covers 3 skills.') !== false, 'Intro markup wrong');
ok(strpos($html, 'move each skill') === false, 'The level sentence shows without the step policy');
ok(Intro::words('quiz')['title'] === 'Time for a quiz?' && strpos(Intro::words('course-challenge')['title'], 'course challenge') !== false, 'Intro wording wrong');
// An ordinary single-skill quiz keeps its own start screen.
[$plain_course, $plain_quiz] = course_with_quiz($admin, ['allow_attempts' => 3]);
choice_question($plain_quiz, 'Plain', 1);
ok(!Intro::applies(Intro::summary(ohmylms_get_quiz($plain_quiz))) && Intro::render(ohmylms_get_quiz($plain_quiz)) === '', 'An ordinary quiz got the intro');
// A timed assessment shows its limit.
$timed = call('POST', 'skill-assessments', ['title' => "Timed $run", 'skills' => [$skills['A'], $skills['B']], 'per_skill' => 1, 'time_limit' => 15, 'publish' => true]);
ok(status_of($timed) === 201, 'Timed build failed: ' . wp_json_encode($timed->get_data()));
$timed_id = remember_post($timed->get_data()['id']);
$timed_summary = Intro::summary(ohmylms_get_quiz($timed_id));
ok($timed_summary['timed'] && $timed_summary['minutes_min'] === 15 && $timed_summary['minutes_max'] === 15 && $timed_summary['style'] === 'quiz' && Intro::applies($timed_summary), 'A timed two-skill quiz summary wrong: ' . wp_json_encode($timed_summary));

// ---- Taking it: each attempt draws one question per skill, then Khan levels move ----
update_option(Settings::OPTION, ['policy' => 'steps']);
ok(Settings::retries(), 'The step policy did not turn on');
$level_of = static function ($student, $term) use ($wpdb) { return (string) $wpdb->get_var($wpdb->prepare('SELECT level FROM ' . OhMyLMS\Assessment\Schema::table('student_skill_state') . ' WHERE student_id=%d AND term_id=%d', $student, $term)) ?: 'not-started'; };
ok(strpos(Intro::render($quiz), 'move each skill up or down one level') !== false, 'The intro does not mention levels with the step policy');
enroll($learner, $course_id);
as_user($learner);
$attempt = Submission::start($quiz_id, $learner);
ok(is_int($attempt), 'Start failed: ' . (is_wp_error($attempt) ? $attempt->get_error_message() : ''));
$fixture['attempts'][] = $attempt;
$views = AttemptItems::delivery($attempt);
ok(count($views) === 3, 'The attempt did not draw one question per skill: ' . count($views));
$letters = array_map(static function ($view) { return substr(trim(wp_strip_all_tags($view['name'])), 0, 1); }, $views);
sort($letters);
ok($letters === ['A', 'B', 'C'], 'The draw is not one question per skill: ' . implode(',', $letters));
$answers = [];
foreach ($views as $view) {
    $letter = substr(trim(wp_strip_all_tags($view['name'])), 0, 1);
    $tokens = array_column($view['questions'], 'id', 'answer');
    $answers[$view['id']] = [$tokens[$letter === 'C' ? 'Wrong' : 'Right']];
}
$result = Submission::submit($quiz_id, $attempt, $learner, $answers);
ok(!is_wp_error($result), 'Submit failed: ' . (is_wp_error($result) ? $result->get_error_message() : ''));
Evidence::process();
ok($level_of($learner, $skills['A']) === 'familiar' && $level_of($learner, $skills['B']) === 'familiar', 'Right answers did not lift the skills to Familiar');
ok($level_of($learner, $skills['C']) === 'attempted', 'The wrong answer did not leave the skill at Attempted');
$changes = Engine::changes($learner, 'quiz', $attempt);
ok(count($changes) === 3 && array_unique(array_column($changes, 'before')) === ['not-started'], 'The level changes are wrong: ' . wp_json_encode($changes));
// The result screen lists them.
$rows = $quiz->get_all_quiz_attempts($learner, $course_id);
$review = AnswerReview::render($quiz, (array) $rows);
ok(strpos($review, 'Skill levels') !== false && strpos($review, 'Leveled up: Not started → Familiar') !== false && strpos($review, 'Leveled up: Not started → Attempted') !== false, 'The result screen does not list the level changes');
// A second attempt draws again, and the same skill moves one more step.
$second = Submission::start($quiz_id, $learner);
ok(is_int($second), 'Second start failed');
$fixture['attempts'][] = $second;
$answers = [];
foreach (AttemptItems::delivery($second) as $view) {
    $tokens = array_column($view['questions'], 'id', 'answer');
    $answers[$view['id']] = [$tokens['Right']];
}
Submission::submit($quiz_id, $second, $learner, $answers);
Evidence::process();
ok($level_of($learner, $skills['A']) === 'proficient' && $level_of($learner, $skills['C']) === 'familiar', 'A second all-right attempt did not move each skill up one step');
// Whatever policy decides the levels, the result screen lists what the attempt did to them.
update_option(Settings::OPTION, ['policy' => 'evidence']);
$review = AnswerReview::render($quiz, (array) $quiz->get_all_quiz_attempts($learner, $course_id));
ok(strpos($review, 'Skill levels') !== false, 'Level changes are missing under the evidence policy');
$wpdb->delete(OhMyLMS\Assessment\Schema::table('skill_level_events'), ['student_id' => $learner]);
// ---- The unit's skills, for the authoring screen and the AI tools ----
as_user($admin);
$lesson = remember_post(wp_insert_post(['post_type' => OHMYLMS_LESSON_CPT, 'post_title' => "Unit lesson $run", 'post_status' => 'publish']));
wp_set_object_terms($lesson, [$skills['A'], $skills['B']], Taxonomy::NAME);
$wpdb->insert($wpdb->prefix . 'ohmylms_content_relationship', ['chapter_id' => $chapter, 'content_id' => $lesson, 'content_type' => 'lesson', 'order_number' => 0]);
$unit = SkillAssessment::unit_skills($chapter);
ok(array_column($unit, 'id') === [$skills['A'], $skills['B']], 'The unit skills are not the skills of its lessons: ' . wp_json_encode($unit));
ok(array_unique(array_column($unit, 'available')) === [3] && $unit[0]['name'] === "Check skill A $run", 'Availability or names wrong: ' . wp_json_encode($unit));
ok(SkillAssessment::unit_skills(999999999) === [], 'An unknown unit had skills');
ok(Pools::available($empty) === 0 && Pools::available($skills['C']) === 3, 'Pools::available is wrong');

// ---- The AI tools: read the unit, propose a draft ----
$tools = \OhMyLMS\MCP\Server::tools();
ok(isset($tools['list_unit_skills'], $tools['create_skill_assessment']) && $tools['list_unit_skills']['readonly'] && !$tools['create_skill_assessment']['readonly'], 'The tools are not registered as expected');
$listed = call('GET', 'mcp-skill-assessments/unit-skills', ['chapter_id' => $chapter]);
ok(status_of($listed) === 200 && count($listed->get_data()) === 2, 'The unit skills tool failed: ' . wp_json_encode($listed->get_data()));
$proposed = call('POST', 'mcp-skill-assessments/create', ['title' => "AI unit test $run", 'style' => 'unit-test', 'skills' => [$skills['A'], $skills['B']], 'per_skill' => 2, 'chapter_id' => $chapter, 'time_limit' => 0]);
ok(status_of($proposed) === 200, 'The AI tool did not create a draft: ' . wp_json_encode($proposed->get_data()));
$drafted = $proposed->get_data();
remember_post($drafted['id']);
ok($drafted['status'] === 'draft' && get_post_status($drafted['id']) === 'draft' && $drafted['questions'] === 4, 'The AI tool published or miscounted: ' . wp_json_encode($drafted));
$sneaky = call('POST', 'mcp-skill-assessments/create', ['title' => "AI sneaky $run", 'style' => 'quiz', 'skills' => [$skills['A']], 'per_skill' => 1, 'publish' => true]);
ok(status_of($sneaky) === 200 && get_post_status($sneaky->get_data()['id']) === 'draft', 'A tool call could publish');
remember_post($sneaky->get_data()['id']);
ok(status_of(call('POST', 'mcp-skill-assessments/create', ['title' => 'Bad', 'style' => 'quiz', 'skills' => [$skills['A']], 'per_skill' => 11])) === 400, 'An out-of-range tool argument was accepted');
as_user($learner);
ok(in_array(status_of(call('POST', 'mcp-skill-assessments/create', ['title' => 'Nope', 'style' => 'quiz', 'skills' => [$skills['A']], 'per_skill' => 1])), [401, 403], true), 'A learner used the AI tool');
as_user($admin);

// ---- Any question type in the bank can be drawn: a numerical question beside a single-choice one ----
$mixed = (int) call('POST', 'skills', ['name' => "Check skill mixed $run", 'public_practice' => true])->get_data()['id'];
$fixture['terms'][] = [$mixed, Taxonomy::NAME];
foreach ([['single-choice', "M1 $run"], ['numerical', "M2 $run"]] as [$type, $name]) {
    $payload = ['name' => $name, 'description' => "Question $name", 'settings' => ['type' => $type, 'score' => ['enabled' => true, 'value' => 1]],
        'skills' => ['p1' => ['primary' => $mixed]], 'bank' => ['family_id' => "$type-$run"]];
    if ($type === 'single-choice') { $payload['questions'] = [['answer' => 'Right', 'is_correct' => 1, 'order_number' => 1], ['answer' => 'Wrong', 'is_correct' => 0, 'order_number' => 2]]; }
    else { $payload['settings'] += ['answer' => 7, 'tolerance' => 0]; }
    $response = call('POST', 'question', $payload);
    ok(status_of($response) === 201, "Creating the $type question failed: " . wp_json_encode($response->get_data()));
    $id = remember_post($response->get_data()['id']);
    ok(status_of(call('POST', 'questions/' . $id . '/approve')) === 200, "Approving the $type question failed");
}
$types_quiz = call('POST', 'skill-assessments', ['title' => "Types $run", 'skills' => [$mixed], 'per_skill' => 2, 'publish' => true, 'chapter_id' => $chapter]);
ok(status_of($types_quiz) === 201, 'The mixed-type assessment was not created: ' . wp_json_encode($types_quiz->get_data()));
$types_id = remember_post($types_quiz->get_data()['id']);
as_user($learner);
$typed = Submission::start($types_id, $learner);
ok(is_int($typed), 'The mixed-type attempt did not start: ' . (is_wp_error($typed) ? $typed->get_error_message() : ''));
$fixture['attempts'][] = $typed;
$drawn = AttemptItems::delivery($typed);
$kinds = array_map(static function ($view) { return $view['settings']['type'] ?? ''; }, $drawn);
sort($kinds);
ok($kinds === ['numerical', 'single-choice'], 'The draw did not hold both question types: ' . implode(',', $kinds));
$answers = [];
foreach ($drawn as $view) {
    $answers[$view['id']] = ($view['settings']['type'] ?? '') === 'numerical' ? ['7'] : [array_column($view['questions'], 'id', 'answer')['Right']];
}
update_option(Settings::OPTION, ['policy' => 'steps']);
$typed_result = Submission::submit($types_id, $typed, $learner, $answers);
ok(!is_wp_error($typed_result), 'Submitting the mixed-type attempt failed');
Evidence::process();
ok($level_of($learner, $mixed) === 'familiar', 'Both types right did not lift the skill: ' . $level_of($learner, $mixed));
update_option(Settings::OPTION, ['policy' => 'evidence']);
$wpdb->delete(OhMyLMS\Assessment\Schema::table('skill_level_events'), ['student_id' => $learner]);
as_user($admin);

// ---- The authoring screen ----
require_once ABSPATH . 'wp-admin/includes/template.php';
// The data store keeps chapter lists for the request; this test added the unit directly.
foreach (['chapters_cache', 'raw_chapters_cache'] as $cache_name) {
    $cache = new ReflectionProperty(\OhMyLMS\DataStores\CourseStore::class, $cache_name);
    $cache->setAccessible(true); $cache->setValue(null, []);
}
$_GET = ['course' => (string) $course_id, 'chapter' => (string) $chapter];
ob_start();
SkillAssessmentPage::render();
$page_html = ob_get_clean();
ok(strpos($page_html, 'Skills this unit teaches') !== false && strpos($page_html, "Check skill A $run") !== false && strpos($page_html, 'name="skills[]"') !== false, 'The screen does not list the unit skills');
ok(strpos($page_html, 'Different questions available') !== false && strpos($page_html, 'Create assessment') !== false, 'The screen lacks the availability column or the button');
$_GET = ['q' => "skill C $run"];
ob_start();
SkillAssessmentPage::render();
$search_html = ob_get_clean();
ok(strpos($search_html, "Check skill C $run") !== false, 'Searching for a skill found nothing (a skill with no code, objective or UUID must still be found)');
$_GET = [];
$_POST = ['ohmylms_skill_assessment' => '1', '_wpnonce' => wp_create_nonce('ohmylms_skill_assessment'), 'title' => "Screen quiz $run", 'style' => 'quiz', 'per_skill' => '1', 'time_limit' => '0', 'skills' => [(string) $skills['A'], (string) $skills['B']], 'course' => (string) $course_id, 'chapter' => (string) $chapter, 'place' => '1'];
$_REQUEST = $_POST;
ob_start();
SkillAssessmentPage::render();
$made_html = ob_get_clean();
ok(strpos($made_html, 'was created with 2 questions for 2 skills') !== false && strpos($made_html, 'It is a draft') !== false, 'The screen did not create the draft: ' . substr(wp_strip_all_tags($made_html), 0, 400));
$screen_quiz = (int) $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type=%s AND post_title=%s", OHMYLMS_QUIZ_CPT, "Screen quiz $run"));
ok($screen_quiz > 0 && get_post_status($screen_quiz) === 'draft', 'The quiz made by the screen is missing or not a draft');
remember_post($screen_quiz);
$_POST = ['ohmylms_skill_assessment' => '1', '_wpnonce' => wp_create_nonce('ohmylms_skill_assessment'), 'title' => 'Empty', 'style' => 'quiz', 'per_skill' => '1', 'skills' => []];
$_REQUEST = $_POST;
ob_start();
SkillAssessmentPage::render();
ok(strpos(ob_get_clean(), 'Choose between 1 and 40 skills.') !== false, 'The screen did not report the missing skills');
$_POST = [];
$_REQUEST = [];
as_user($learner);
ob_start();
SkillAssessmentPage::render();
ok(ob_get_clean() === '', 'A learner saw the authoring screen');
as_user($admin);
