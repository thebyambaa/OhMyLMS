<?php
/** Unit tests for every unit of a course, the Level up button on the unit and the course challenge card. */
use OhMyLMS\Assessment\AssessmentSettings;
use OhMyLMS\Assessment\SkillAssessment;
use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\Student\CourseOutline;

global $wpdb;
$run = wp_generate_password(6, false, false);
as_user($admin);
$clear_chapter_cache = static function () {
    foreach (['chapters_cache', 'raw_chapters_cache'] as $name) {
        $cache = new ReflectionProperty(\OhMyLMS\DataStores\CourseStore::class, $name);
        $cache->setAccessible(true); $cache->setValue(null, []);
    }
};

// ---- Skills: A and B have questions, C has questions, N has none ----
$skills = [];
foreach (['A' => 2, 'B' => 2, 'C' => 2, 'N' => 0] as $letter => $count) {
    $skills[$letter] = (int) call('POST', 'skills', ['name' => "Level skill $letter $run", 'public_practice' => true])->get_data()['id'];
    $fixture['terms'][] = [$skills[$letter], Taxonomy::NAME];
    for ($n = 1; $n <= $count; $n++) {
        $response = call('POST', 'question', ['name' => "$letter$n $run", 'description' => "Question $letter$n $run",
            'settings' => ['type' => 'single-choice', 'score' => ['enabled' => true, 'value' => 1]],
            'questions' => [['answer' => 'Right', 'is_correct' => 1, 'order_number' => 1], ['answer' => 'Wrong', 'is_correct' => 0, 'order_number' => 2]],
            'skills' => ['p1' => ['primary' => $skills[$letter]]], 'bank' => ['family_id' => "lv$letter$n-$run"]]);
        ok(status_of($response) === 201, "Creating $letter$n failed");
        $id = remember_post($response->get_data()['id']);
        ok(status_of(call('POST', 'question-bank/' . $id . '/approve')) === 200, "Approving $letter$n failed");
    }
}

// ---- A course with four units: A+B, C+N, no skills, and an empty-bank skill only ----
$course_id = remember_post(call('POST', 'courses', ['name' => "Level up course $run", 'status' => 'publish'])->get_data()['id']);
$units = [];
foreach ([['Unit one', ['A', 'B']], ['Unit two', ['C', 'N']], ['Unit three', []], ['Unit four', ['N']]] as $order => [$title, $letters]) {
    $chapter = remember_post(wp_insert_post(['post_type' => 'ohmylms-chapter', 'post_title' => "$title $run", 'post_status' => 'publish']));
    $wpdb->insert($wpdb->prefix . 'ohmylms_chapter_relationship', ['course_id' => $course_id, 'chapter_id' => $chapter, 'order_number' => $order]);
    $lesson = remember_post(wp_insert_post(['post_type' => OHMYLMS_LESSON_CPT, 'post_title' => "$title lesson $run", 'post_status' => 'publish']));
    if ($letters) { wp_set_object_terms($lesson, array_map(static function ($l) use ($skills) { return $skills[$l]; }, $letters), Taxonomy::NAME); }
    $wpdb->insert($wpdb->prefix . 'ohmylms_content_relationship', ['chapter_id' => $chapter, 'content_id' => $lesson, 'content_type' => 'lesson', 'order_number' => 0]);
    $units[$title] = $chapter;
}
$clear_chapter_cache();

// ---- Input and permission checks ----
ok(status_of(call('POST', 'skill-assessments/course', ['course_id' => $course_id, 'per_skill' => 0])) === 400, 'per_skill 0 was accepted');
ok(status_of(call('POST', 'skill-assessments/course', ['course_id' => 999999999])) === 404, 'An unknown course was accepted');
$learner = make_user('subscriber');
as_user($learner);
ok(in_array(status_of(call('POST', 'skill-assessments/course', ['course_id' => $course_id])), [401, 403], true), 'A learner built assessments for a course');
ok(is_wp_error(SkillAssessment::create_for_course($course_id, [])), 'A learner could call the builder directly');
as_user($admin);

// ---- Create them: unit tests plus a course challenge, published ----
$made = call('POST', 'skill-assessments/course', ['course_id' => $course_id, 'per_skill' => 1, 'challenge' => true, 'publish' => true]);
ok(status_of($made) === 200, 'The course build failed: ' . wp_json_encode($made->get_data()));
$report = $made->get_data();
foreach ($report['created'] as $item) { remember_post($item['id']); }
ok(count($report['created']) === 3, 'Expected two unit tests and a challenge: ' . wp_json_encode($report));
ok(array_column($report['created'], 'style') === ['unit-test', 'unit-test', 'course-challenge'], 'The created assessments are not in unit order with the challenge last');
ok($report['created'][0]['skills'] === 2 && $report['created'][1]['skills'] === 1, 'Each unit test should cover only its own skills with questions: ' . wp_json_encode($report['created']));
ok($report['created'][2]['skills'] === 3, 'The course challenge should cover every skill that has questions');
ok(in_array("Level skill N $run", $report['left_out'], true), 'The skill without questions was not listed as left out');
$skipped = implode(' | ', array_map(static function ($item) { return $item['unit'] . ': ' . $item['reason']; }, $report['skipped']));
ok(count($report['skipped']) === 3 && strpos($skipped, "Unit three $run") !== false && strpos($skipped, "Unit four $run") !== false, 'The empty units (and the default untitled unit) were not skipped with reasons: ' . $skipped);
ok($report['managed'] === false, 'A course without a learning program was marked managed');
foreach ($report['created'] as $item) {
    ok(get_post_status($item['id']) === 'publish' && AssessmentSettings::get($item['id'])['style'] === $item['style'], 'A created assessment is not published with its style');
}
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ohmylms_content_relationship WHERE chapter_id=%d AND content_id=%d AND content_type='quiz'", $units['Unit one'], $report['created'][0]['id'])) === 1, 'The first unit test is not in the first unit');

// ---- Running it again changes nothing ----
$clear_chapter_cache();
$again = call('POST', 'skill-assessments/course', ['course_id' => $course_id, 'per_skill' => 1, 'challenge' => true])->get_data();
ok($again['created'] === [], 'A second run created duplicates: ' . wp_json_encode($again['created']));
ok(count(array_filter($again['skipped'], static function ($item) { return strpos($item['reason'], 'already') !== false; })) === 3, 'A second run did not report what already exists: ' . wp_json_encode($again['skipped']));

// ---- What the learner sees: Level up on each unit that has a test, and the course challenge ----
enroll($learner, $course_id);
as_user($learner);
$outline = CourseOutline::get($course_id, $learner);
ok(!is_wp_error($outline) && $outline['enrolled'], 'The outline did not load');
$by_title = [];
foreach ($outline['units'] as $unit) { $by_title[$unit['title']] = $unit; }
ok(!empty($by_title["Unit one $run"]['test']) && $by_title["Unit one $run"]['test']['style'] === 'unit-test' && $by_title["Unit one $run"]['test']['kind_label'] === 'Unit test', 'Unit one has no unit test in the outline');
ok(!empty($by_title["Unit two $run"]['test']) && empty($by_title["Unit three $run"]['test'] ?? null), 'Unit tests are on the wrong units');
ok(!empty($outline['challenge']) && $outline['challenge']['style'] === 'course-challenge', 'The course challenge is missing from the outline');
ok($by_title["Unit one $run"]['test']['url'] === ohmylms_get_pretty_content_permalink($by_title["Unit one $run"]['test']['content_id']), 'The Level up link is not the unit test');
set_query_var('hub_course', $course_id);
ob_start(); ohmylms_get_template('student-hub/course.php'); $page = ob_get_clean();
ok(substr_count($page, 'Level up</a>') === 2, 'Expected a Level up button on the two units that have a unit test: ' . substr_count($page, 'Level up</a>'));
ok(strpos($page, 'Start course challenge') !== false && strpos($page, 'Test your knowledge of the skills in this course.') !== false, 'The course challenge card is missing');
ok(strpos($page, 'Unit test') !== false && strpos($page, 'Course challenge') !== false, 'The assessment rows do not carry their own names');
// A visitor who is not enrolled is sent to the course page, not into the test.
as_user(make_user('subscriber'));
$visitor = CourseOutline::get($course_id, get_current_user_id());
ok(!$visitor['enrolled'] && strpos($visitor['units'][0]['test']['url'], '#oml-course-enroll') !== false && strpos($visitor['units'][0]['test']['url'], 'legacy') === false, 'A visitor was linked straight into the unit test');
// A draft unit test stays out of the learner's view.
as_user($admin);
wp_update_post(['ID' => $report['created'][1]['id'], 'post_status' => 'draft']);
as_user($learner);
$hidden = CourseOutline::get($course_id, $learner);
$titles = array_column($hidden['units'], 'test', 'title');
ok(empty($titles["Unit two $run"]), 'A draft unit test is shown to learners');
as_user($admin);

// ---- The authoring screen's whole-course form ----
require_once ABSPATH . 'wp-admin/includes/template.php';
$_GET = ['course' => (string) $course_id];
ob_start();
\OhMyLMS\Assessment\SkillAssessmentPage::render();
ok(strpos(ob_get_clean(), 'Create for every unit') !== false, 'The whole-course form is not offered once a course is chosen');
$_GET = [];
$_POST = ['ohmylms_course_assessments' => '1', '_wpnonce' => wp_create_nonce('ohmylms_course_assessments'), 'course' => (string) $course_id, 'per_skill' => '1', 'challenge' => '1'];
$_REQUEST = $_POST;
ob_start();
\OhMyLMS\Assessment\SkillAssessmentPage::render();
$form_html = ob_get_clean();
ok(strpos($form_html, '0 assessments were created.') !== false && strpos($form_html, 'It already has a unit test.') !== false, 'The whole-course form did not report what already exists: ' . substr(wp_strip_all_tags($form_html), 0, 500));
$_POST = [];
$_REQUEST = [];
