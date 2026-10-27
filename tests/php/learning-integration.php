<?php
if (PHP_SAPI !== 'cli') { exit; }
define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
$config = json_decode(file_get_contents(getenv('OHMYLMS_TEST_CREDENTIALS')), true);
require $config['site'] . '/wp-load.php';
if (!defined('OHMYLMS_TEST_SITE') || DB_NAME !== 'ohmylms_source_test') { throw new RuntimeException('Requires disposable test site'); }
require_once ABSPATH . 'wp-admin/includes/user.php';

use OhMyLMS\Learning\CourseProgram;
use OhMyLMS\Learning\CompletionPolicy;
use OhMyLMS\Learning\Schema;
use OhMyLMS\Learning\Frontend;
use OhMyLMS\Assessment\Schema as AssessmentSchema;
use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\Skills\Evidence;
use OhMyLMS\Skills\Mastery;
use OhMyLMS\Practice\Sessions;

$checks = 0; $posts = []; $users = []; $terms = []; $enrollments = []; $sessions = []; $banks = [];
$previous = get_option('ohmylms_integrations', []);
$admin = get_user_by('login', $config['username'])->ID;
function ok($condition, $message) { global $checks; if (!$condition) { throw new RuntimeException($message); } $checks++; }
function api($method, $path, $body = []) {
    $request = new WP_REST_Request($method, '/ohmylms/v1/' . $path);
    if ($body) { $request->set_header('Content-Type', 'application/json'); $request->set_body(wp_json_encode($body)); }
    return rest_do_request($request);
}
function created($path, $body) {
    global $posts;
    $response = api('POST', $path, $body);
    ok($response->get_status() === 201, 'Create failed: ' . wp_json_encode($response->get_data()));
    $id = (int) $response->get_data()['id']; $posts[] = $id; return $id;
}
function learner() {
    global $users;
    $id = wp_create_user('learning-' . wp_generate_password(9, false, false), wp_generate_password(24), 'learning-' . wp_generate_password(9, false, false) . '@example.invalid');
    (new WP_User($id))->set_role('subscriber'); $users[] = $id; return $id;
}
function enrollment($student, $course) {
    global $wpdb, $enrollments;
    $wpdb->insert($wpdb->prefix . 'ohmylms_user_enrollment', ['user_id' => $student, 'course_id' => $course, 'status' => 'enrolled', 'progress' => 'running', 'start_date' => current_time('mysql')]);
    $enrollments[] = (int) $wpdb->insert_id; return (int) $wpdb->insert_id;
}
function item($type, $content, $required = true) { return ['id' => wp_generate_uuid4(), 'type' => $type, 'content_id' => $content, 'chapter_id' => 0, 'required' => $required, 'pass_percent' => 80]; }
function publish($course, $program, $apply = false) {
    global $admin;
    wp_set_current_user($admin);
    $response = api('POST', 'courses/' . $course . '/learning/publish', $program + ['apply_existing' => $apply]);
    ok($response->get_status() === 200, 'Publish failed: ' . wp_json_encode($response->get_data()));
    return $response->get_data()['published'];
}

try {
    wp_set_current_user($admin);
    Schema::install(); ok(Schema::ready(), 'Learning tables unavailable');
    $skill = (int) wp_insert_term('Learning fractions ' . wp_generate_password(6, false, false), Taxonomy::NAME)['term_id']; $terms[] = $skill;
    $empty = (int) wp_insert_term('Learning empty ' . wp_generate_password(6, false, false), Taxonomy::NAME)['term_id']; $terms[] = $empty;
    $lesson = created('lessons', ['name' => 'Shared fractions lesson', 'description' => '<p>Equivalent fractions have the same value.</p>', 'type' => 'text', 'status' => 'publish']);
    $questions = [];
    for ($i = 0; $i < 5; $i++) {
        $q = created('question', ['name' => 'Learning fraction ' . $i, 'settings' => ['type' => 'single-choice', 'hint' => 'Think about equal parts', 'score' => ['enabled' => true, 'value' => 1]], 'questions' => [['answer' => 'Right', 'is_correct' => 1], ['answer' => 'Wrong']], 'skills' => ['p1' => ['primary' => $skill, 'supporting' => []]], 'bank' => ['family_id' => 'learning-family-' . $i]]);
        ok(api('POST', 'question-bank/' . $q . '/approve')->get_status() === 200, 'Approve failed'); $questions[] = $q;
    }
    $course = created('courses', ['name' => 'Blended fractions', 'status' => 'publish']);
    $quiz = created('quiz', ['name' => 'Fractions checkpoint', 'status' => 'publish']);
    $chapter = (int) ohmylms_get_course($course)->get_chapters()[0]['id']; $posts[] = $chapter;
    $wpdb->insert($wpdb->prefix . 'ohmylms_content_relationship', ['chapter_id' => $chapter, 'content_id' => $quiz, 'content_type' => 'quiz', 'order_number' => 0]);
    api('POST', 'quiz/' . $quiz . '/questions', ['question_ids' => [$questions[0]]]);
    $program = ['mode' => 'blended', 'recognize_prior' => true, 'evidence_days' => 0, 'bank_ids' => [], 'outcomes' => [['term_id' => $skill, 'target' => 'proficient', 'required' => true]], 'items' => [item('lesson', $lesson), item('practice', $skill, false), item('quiz', $quiz)]];
    $old_student = learner(); $old_enrollment = enrollment($old_student, $course);
    $published = publish($course, $program);
    ok(CourseProgram::for_enrollment(CourseProgram::enrollment($old_student, $course)) === null, 'Publishing silently changed an existing enrollment');
    $student = learner(); $eid = enrollment($student, $course);
    wp_set_current_user($student);
    // Presentation reads must preserve attempts, grading and completion state.
    $before_sessions = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . AssessmentSchema::table('practice_sessions') . ' WHERE student_id=%d', $student));
    ok(\OhMyLMS\Practice\Presentation::available($skill, $course, 10) === 5, 'Entry count ignored the authorized finite pool');
    $entry = \OhMyLMS\Practice\Frontend::practice(['skill' => $skill, 'course' => $course]);
    ok(strpos($entry, 'Up to 5 questions') !== false && strpos($entry, 'Right') === false, 'Practice entry count wrong or answer data exposed');
    ob_start();
    $opened = \OhMyLMS\Student\PlayerContext::open_practice($course, $skill);
    \OhMyLMS\Student\PlayerContext::close();
    $shell = ob_get_clean();
    ok($opened && substr_count($shell, 'aria-current="page"') === 1 && strpos($shell, '#hub-unit-1') !== false, 'Practice lost its current skill or unit return');
    ok(!\OhMyLMS\Student\PlayerContext::active(), 'Practice shell remained active after closing');
    ob_start();
    $lesson_opened = \OhMyLMS\Student\PlayerContext::open_learning($course, $program['items'][0]['id']);
    \OhMyLMS\Student\PlayerContext::close();
    $lesson_shell = ob_get_clean();
    ok($lesson_opened && strpos($lesson_shell, '#hub-unit-1') !== false && substr_count($lesson_shell, 'aria-current="page"') === 1, 'Program lesson lost its unit context');
    ok(!\OhMyLMS\Student\PlayerContext::open_learning($course, wp_generate_uuid4()), 'Unknown placement opened the lesson shell');
    $presentation_course = $course;
    $focused_lesson = Frontend::render($course, $program['items'][0]['id'], true);
    $course = $presentation_course;
    ok(strpos($focused_lesson, 'Equivalent fractions have the same value') !== false && strpos($focused_lesson, 'Mark lesson complete') !== false && strpos($focused_lesson, 'Program version') === false, 'Focused lesson changed content/completion controls or exposed program internals');
    ok(!\OhMyLMS\Student\PlayerContext::open_practice($course, $empty), 'Unrelated skill opened the course shell');
    wp_set_current_user($old_student);
    ok(\OhMyLMS\Practice\Presentation::available($skill, $course, 10) === 0, 'Entry count bypassed frozen enrollment policy');
    ok(!\OhMyLMS\Student\PlayerContext::open_practice($course, $skill), 'Ineligible enrollment opened the course shell');
    wp_set_current_user($student);
    set_query_var('hub_course', $course);
    ob_start(); ohmylms_get_template('student-hub/course.php'); $overview = ob_get_clean();
    ok(strpos($overview, '>Learn</h3>') !== false && strpos($overview, '>Practice</h3>') !== false && strpos($overview, '>Assessments</h3>') !== false, 'Unit activity groups missing');
    ok(strpos($overview, 'Skill mastery legend') !== false, 'Real skill mastery legend missing');
    ok($before_sessions === (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . AssessmentSchema::table('practice_sessions') . ' WHERE student_id=%d', $student)), 'Presentation created a practice attempt');
    $state = api('GET', 'courses/' . $course . '/learning/progress')->get_data();
    ok($state['dimensions'] === ['activities' => ['met' => 0, 'total' => 1], 'outcomes' => ['met' => 0, 'total' => 1], 'assessments' => ['met' => 0, 'total' => 1]], 'Progress dimensions wrong: ' . wp_json_encode($state));
    ok(api('GET', 'courses/' . $course . '/learning')->get_status() === 403, 'Learner opened teacher editor');
    ok(api('POST', 'courses/' . $course . '/learning/activities/' . $program['items'][2]['id'] . '/complete')->get_status() === 403, 'Learner self-marked a checkpoint');
    $response = api('POST', 'courses/' . $course . '/learning/activities/' . $program['items'][0]['id'] . '/complete');
    ok($response->get_status() === 200 && $response->get_data()['dimensions']['activities']['met'] === 1 && !$response->get_data()['completed'], 'Lesson bypassed skill/quiz requirements');
    ok((new \OhMyLMS\Data\Student($student))->get_over_all_completion_rate($course) < 100, 'Legacy meter bypassed policy');
    $another = learner(); wp_set_current_user($another);
    ok(api('GET', 'courses/' . $course . '/learning/progress')->get_status() === 403, 'Unenrolled learner saw progress');
    ok(api('POST', 'practice/sessions', ['term_id' => $skill, 'course_id' => $course])->get_status() === 403, 'Forged course ID granted practice access');
    wp_set_current_user(0);
    $session = Sessions::start(['guest_id' => 123], $skill);
    ok(is_wp_error($session), 'Private personal question pool exposed to guests');
    wp_set_current_user($admin);
    api('PUT', 'skills/' . $skill, ['public_practice' => true]);
    $session = Sessions::start(['guest_id' => 123], $skill);
    ok(!is_wp_error($session), 'Explicit public pool inaccessible'); $sessions[] = $session['id'];
    wp_set_current_user($student);
    $session = Sessions::start(['student_id' => $student], $skill, ['course_id' => $course, 'item_limit' => 3]);
    ok(!is_wp_error($session), 'Course practice denied: ' . (is_wp_error($session) ? $session->get_error_message() : '')); $sessions[] = $session['id'];
    for ($i = 0; $i < 3; $i++) {
        $view = Sessions::state($session)['current'];
        $right = array_column($view['questions'], 'id', 'answer')['Right'];
        $answer = Sessions::answer($session, $view['item_id'], [$right]);
        ok(!is_wp_error($answer), 'Practice answer failed'); $session = Sessions::get($session['uuid']);
    }
    Evidence::process(200);
    $state = CompletionPolicy::status($student, $course);
    ok($state['dimensions']['outcomes']['met'] === 1 && !$state['eligible'], 'Skill evidence did not count or bypassed checkpoint');
    $wpdb->insert($wpdb->prefix . 'ohmylms_quiz_attempts', ['quiz_id' => $quiz, 'student_id' => $student, 'course_id' => $course, 'total' => 0, 'status' => 'in-review', 'start_date' => current_time('mysql')]);
    $attempt = (int) $wpdb->insert_id;
    ok(!CompletionPolicy::status($student, $course)['eligible'], 'Manual marking counted before grading');
    $wpdb->update($wpdb->prefix . 'ohmylms_quiz_attempts', ['status' => 'completed', 'total' => 0.75], ['id' => $attempt]);
    ok(!CompletionPolicy::status($student, $course)['eligible'], 'Failed checkpoint passed');
    $wpdb->update($wpdb->prefix . 'ohmylms_quiz_attempts', ['total' => 1], ['id' => $attempt]);
    $events = 0;
    $listener = static function ($who, $which) use (&$events, $student, $course) { if ((int) $who === $student && (int) $which === $course) { $events++; } };
    add_action('ohmylms_course_completed', $listener, 100, 2);
    ok(CompletionPolicy::award($student, $course)['completed'], 'Eligible blended course not awarded');
    CompletionPolicy::award($student, $course);
    ok($events === 1, 'Completion event repeated');
    ok((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Schema::table('awards') . ' WHERE enrollment_id=%d', $eid)) === 1, 'Award duplicated');
    $wpdb->update(AssessmentSchema::table('student_skill_state'), ['level' => 'attempted', 'review_due' => 1], ['student_id' => $student, 'term_id' => $skill]);
    ok(CompletionPolicy::status($student, $course)['completed'], 'Review or skill decline revoked historical completion');
    wp_set_current_user($admin);
    $new_program = $program; $new_program['items'][] = item('lesson', created('lessons', ['name' => 'New requirement', 'type' => 'text', 'status' => 'publish']));
    publish($course, $new_program);
    ok(CourseProgram::for_enrollment(CourseProgram::enrollment($student, $course))['version'] === 1, 'Publication changed an existing learner policy');
    publish($course, $program, true);
    ok(CourseProgram::for_enrollment(CourseProgram::enrollment($old_student, $course))['version'] === 3, 'Explicit adoption did not upgrade unfinished enrollment');
    ok(CourseProgram::for_enrollment(CourseProgram::enrollment($student, $course))['version'] === 1, 'Explicit upgrade changed historical award');
    // Skills are core: a stale client cannot switch them off, and no setting is stored for them.
    api('PUT', 'integrations', ['skills' => ['is_enable' => 0]]);
    ok(\OhMyLMS\Extensions\Addons::enabled('skills') && !isset(get_option('ohmylms_integrations')['skills']), 'Skills must stay on and unstored');
    ok(api('DELETE', 'skills/' . $skill, ['force' => true])->get_status() === 409, 'Published outcome skill deleted');
    $bad = $program; $bad['outcomes'][0]['term_id'] = $empty; $bad['items'] = [item('practice', $empty, false)];
    ok(api('POST', 'courses/' . $course . '/learning/publish', $bad)->get_status() === 409, 'Empty pool policy published');
    $bad = $program; $bad['items'][] = $program['items'][0];
    ok(api('PUT', 'courses/' . $course . '/learning', $bad)->get_status() === 400, 'Duplicate placement accepted');
    $stale = $program; $stale['expected_program_id'] = 0;
    ok(api('POST', 'courses/' . $course . '/learning/publish', $stale)->get_status() === 409, 'Stale author publication overwrote a newer version');
    $second = created('courses', ['name' => 'Traditional refresher', 'status' => 'publish']);
    $posts[] = (int) ohmylms_get_course($second)->get_chapters()[0]['id'];
    $traditional = ['mode' => 'traditional', 'recognize_prior' => true, 'evidence_days' => 0, 'bank_ids' => [], 'outcomes' => [], 'items' => [item('lesson', $lesson)]];
    publish($second, $traditional);
    $second_enrollment = enrollment($student, $second);
    wp_set_current_user($student);
    ok(CompletionPolicy::status($student, $second)['dimensions']['activities']['met'] === 0, 'Shared lesson completion leaked across courses');
    $html = Frontend::render($second, $traditional['items'][0]['id']);
    ok(strpos($html, 'Equivalent fractions have the same value') !== false, 'Shared lesson did not render');
    ok(api('POST', 'courses/' . $second . '/learning/activities/' . $traditional['items'][0]['id'] . '/complete')->get_data()['completed'], 'Traditional required lesson did not complete');
    wp_set_current_user($admin);
    $third = created('courses', ['name' => 'Skills refresher', 'status' => 'publish']); $posts[] = (int) ohmylms_get_course($third)->get_chapters()[0]['id'];
    $skills_only = $program; $skills_only['mode'] = 'skill-based'; $skills_only['items'] = [item('lesson', $lesson, false), item('practice', $skill, false)];
    publish($third, $skills_only);
    enrollment($student, $third);
    Mastery::recompute($student, $skill);
    $prior = CompletionPolicy::award($student, $third);
    ok($prior['completed'] && $prior['dimensions']['activities']['total'] === 0, 'Skill-only course did not recognize prior evidence');
    $fourth = created('courses', ['name' => 'Fresh skill evidence', 'status' => 'publish']); $posts[] = (int) ohmylms_get_course($fourth)->get_chapters()[0]['id'];
    $skills_only['recognize_prior'] = false;
    publish($fourth, $skills_only);
    enrollment($student, $fourth);
    ok(!CompletionPolicy::status($student, $fourth)['eligible'], 'Course-only policy credited evidence from another course');
    wp_update_post(['ID' => $fourth, 'post_status' => 'draft']);
    wp_set_current_user($student);
    ok(api('GET', 'courses/' . $fourth . '/learning/progress')->get_status() === 403, 'Unpublished course progress accessible');
    ok(is_wp_error(Sessions::start(['student_id' => $student], $skill, ['course_id' => $fourth])), 'Unpublished course practice accessible');
    ok(CompletionPolicy::status($student, $fourth)['blocked'], 'Unpublished course not blocked');
    // Credit back: proficiency earned on another objective of the same shared skill counts here.
    wp_set_current_user($admin);
    $tag = wp_generate_password(6, false, false);
    $shared = \OhMyLMS\Curriculum\Comparison::save_shared(0, ['name' => 'Credit shared ' . $tag, 'subject' => 'Credit ' . $tag, 'topic' => 'Credit', 'domain' => 'algebra', 'grade_from' => 7, 'grade_to' => 7]);
    ok(!is_wp_error($shared), 'Shared skill not created'); $terms[] = (int) $shared['id'];
    $mine = (int) wp_insert_term('Credit mine ' . $tag, Taxonomy::NAME)['term_id']; $terms[] = $mine;
    $theirs = (int) wp_insert_term('Credit theirs ' . $tag, Taxonomy::NAME)['term_id']; $terms[] = $theirs;
    ok(!is_wp_error(\OhMyLMS\Curriculum\SkillMappings::save($mine, (int) $shared['id'], 'equivalent')) && !is_wp_error(\OhMyLMS\Curriculum\SkillMappings::save($theirs, (int) $shared['id'], 'equivalent')), 'Objectives not mapped');
    for ($i = 0; $i < 3; $i++) {
        $q = created('question', ['name' => 'Credit question ' . $i, 'settings' => ['type' => 'single-choice', 'score' => ['enabled' => true, 'value' => 1]], 'questions' => [['answer' => 'Right', 'is_correct' => 1], ['answer' => 'Wrong']], 'skills' => ['p1' => ['primary' => $mine, 'supporting' => []]], 'bank' => ['family_id' => 'credit-family-' . $tag . $i]]);
        ok(api('POST', 'question-bank/' . $q . '/approve')->get_status() === 200, 'Approve failed');
    }
    $credit_student = learner();
    $set_state = static function ($term, $level) use ($wpdb, $credit_student) {
        $wpdb->delete(AssessmentSchema::table('student_skill_state'), ['student_id' => $credit_student, 'term_id' => $term]);
        $wpdb->insert(AssessmentSchema::table('student_skill_state'), ['student_id' => $credit_student, 'term_id' => $term, 'level' => $level, 'policy' => 'evidence', 'evidence_count' => 4, 'last_evidence_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => current_time('mysql', true)]);
    };
    $credit_program = ['mode' => 'skill-based', 'recognize_prior' => true, 'evidence_days' => 0, 'bank_ids' => [], 'outcomes' => [['term_id' => $mine, 'target' => 'proficient', 'required' => true]], 'items' => [item('practice', $mine, false)]];
    $credit_course = created('courses', ['name' => 'Credit course ' . $tag, 'status' => 'publish']); $posts[] = (int) ohmylms_get_course($credit_course)->get_chapters()[0]['id'];
    publish($credit_course, $credit_program);
    enrollment($credit_student, $credit_course);
    ok(\OhMyLMS\Performance\Guidance::credit($credit_student, [$mine]) === [], 'Credit with no work elsewhere');
    $set_state($theirs, 'familiar');
    \OhMyLMS\Performance\Guidance::flush();
    $familiar = CompletionPolicy::status($credit_student, $credit_course);
    ok(\OhMyLMS\Performance\Guidance::credit($credit_student, [$mine])[$mine]['level'] === 'familiar' && !$familiar['eligible'] && $familiar['program']['outcomes'][0]['credit'] === ['level' => 'familiar', 'own' => 'not-started', 'counts' => false], 'Familiar work elsewhere is shown, not counted: ' . wp_json_encode($familiar['program']['outcomes'][0]['credit'] ?? null));
    $set_state($theirs, 'proficient');
    \OhMyLMS\Performance\Guidance::flush();
    $proficient = CompletionPolicy::status($credit_student, $credit_course);
    ok($proficient['eligible'] && $proficient['program']['outcomes'][0]['credit']['counts'] && $proficient['program']['outcomes'][0]['state']['level'] === 'proficient', 'Proficient work elsewhere completes the target');
    $html = Frontend::class; ob_start(); $rendered = new ReflectionMethod(Frontend::class, 'outcomes'); $rendered->setAccessible(true); $rendered->invoke(null, $proficient['program'], false); $table = ob_get_clean();
    ok(strpos($table, 'from other syllabuses') !== false, 'The level is not marked as from other syllabuses');
    $outline = \OhMyLMS\Student\CourseOutline::get($credit_course, $credit_student);
    ok($outline['summary']['credited'] === 1 && $outline['summary']['proficient'] === 1 && $outline['summary']['percent'] === 100, 'The course outline did not count the credited target: ' . wp_json_encode($outline['summary']));
    $set_state($theirs, 'familiar'); \OhMyLMS\Performance\Guidance::flush();
    $familiar_outline = \OhMyLMS\Student\CourseOutline::get($credit_course, $credit_student);
    ok($familiar_outline['summary']['credited'] === 0 && $familiar_outline['summary']['proficient'] === 0, 'Familiar work elsewhere was counted in the outline');
    $set_state($theirs, 'proficient'); \OhMyLMS\Performance\Guidance::flush();
    $windowed = $credit_program; $windowed['evidence_days'] = 90;
    $windowed_course = created('courses', ['name' => 'Credit windowed ' . $tag, 'status' => 'publish']); $posts[] = (int) ohmylms_get_course($windowed_course)->get_chapters()[0]['id'];
    publish($windowed_course, $windowed); enrollment($credit_student, $windowed_course);
    ok(!CompletionPolicy::status($credit_student, $windowed_course)['eligible'] && CompletionPolicy::status($credit_student, $windowed_course)['program']['outcomes'][0]['credit'] === null, 'A program with an evidence window ignores work elsewhere');
    $own_only = $credit_program; $own_only['recognize_prior'] = false;
    $own_course = created('courses', ['name' => 'Credit own only ' . $tag, 'status' => 'publish']); $posts[] = (int) ohmylms_get_course($own_course)->get_chapters()[0]['id'];
    publish($own_course, $own_only); enrollment($credit_student, $own_course);
    ok(!CompletionPolicy::status($credit_student, $own_course)['eligible'], 'A course-only program ignores work elsewhere');
    foreach ([$mine, $theirs] as $id) { $wpdb->delete(\OhMyLMS\Curriculum\SkillMappings::table(), ['specific_term_id' => $id]); }

    $pure = CompletionPolicy::evaluate(['items' => [], 'outcomes' => []], [], [], []);
    ok(!$pure['eligible'], 'Empty requirements auto-completed');
    $pure = CompletionPolicy::evaluate($program, [$lesson => true], [$skill => ['level' => 'mastered']], [$quiz => ['passed' => true]], true);
    ok(!$pure['eligible'] && $pure['pending'], 'Pending evidence awarded completion');
    echo "$checks learning integration checks passed.\n";
} finally {
    wp_set_current_user($admin);
    foreach ($enrollments as $id) {
        foreach (['awards', 'enrollments'] as $table) { $wpdb->delete(Schema::table($table), ['enrollment_id' => $id]); }
        $wpdb->delete($wpdb->prefix . 'ohmylms_user_progress', ['enrollment_id' => $id]);
        $wpdb->delete($wpdb->prefix . 'ohmylms_user_enrollment', ['id' => $id]);
    }
    foreach ($sessions as $id) { $wpdb->delete(AssessmentSchema::table('practice_items'), ['session_id' => $id]); $wpdb->delete(AssessmentSchema::table('practice_sessions'), ['id' => $id]); }
    foreach ($users as $id) {
        $events = $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . AssessmentSchema::table('grade_events') . ' WHERE student_id=%d', $id));
        foreach ($events as $event) { $wpdb->delete(AssessmentSchema::table('evidence_outbox'), ['grade_event_id' => $event]); }
        foreach (['grade_events', 'skill_evidence', 'student_skill_state'] as $table) { $wpdb->delete(AssessmentSchema::table($table), ['student_id' => $id]); }
        $wpdb->delete($wpdb->prefix . 'ohmylms_quiz_attempts', ['student_id' => $id]);
        wp_delete_user($id);
    }
    foreach ($posts as $id) {
        $programs = $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . Schema::table('programs') . ' WHERE course_id=%d', $id));
        foreach ($programs as $program) { $wpdb->delete(Schema::table('outcomes'), ['program_id' => $program]); }
        $wpdb->delete(Schema::table('programs'), ['course_id' => $id]); wp_delete_post($id, true);
    }
    foreach ($terms as $id) { wp_delete_term($id, Taxonomy::NAME); }
    update_option('ohmylms_integrations', $previous);
}
