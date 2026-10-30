<?php
/** Skill course links, guardian/teacher skill views, frozen media, page breaks, gradebook maxima. */
use OhMyLMS\Assessment\AttemptItems;
use OhMyLMS\Assessment\RevisionPublisher;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\QuestionBank\MediaFreezer;
use OhMyLMS\QuestionBank\VersionPublisher;
use OhMyLMS\Quiz\Submission;
use OhMyLMS\Schools\Schema as SchoolSchema;
use OhMyLMS\Skills\Taxonomy;

global $wpdb;
$t = static function ($name) { return Schema::table($name); };
as_user($admin);

// ---- Skills linked to courses ----
$created = call('POST', 'skills', ['name' => 'Course skill ' . wp_generate_password(5, false, false)])->get_data();
$skill = (int) $created['id'];
$fixture['terms'][] = [$skill, Taxonomy::NAME];
[$course_id, $quiz_id] = course_with_quiz($admin);
$course_title = get_the_title($course_id);
$linked = call('PUT', 'skills/' . $skill . '/courses', ['course_ids' => [$course_id]]);
ok(status_of($linked) === 200 && $linked->get_data()['courses'] === [$course_id], 'Course link not saved: ' . wp_json_encode($linked->get_data()));
ok(in_array($skill, wp_get_object_terms($course_id, Taxonomy::NAME, ['fields' => 'ids']), true), 'Course term not assigned');
$targets = call('GET', 'skills/link-targets', [], ['type' => 'course', 'include' => (string) $course_id])->get_data();
ok($targets && $targets[0]['id'] === $course_id && $targets[0]['title'] === $course_title, 'Link target titles missing');
ok(status_of(call('PUT', 'skills/' . $skill . '/courses', ['course_ids' => [$quiz_id]])) === 403, 'A quiz was linked as a course');
$author = make_user('author');
as_user($author);
ok(status_of(call('PUT', 'skills/' . $skill . '/courses', ['course_ids' => [$course_id]])) === 403, 'Author linked a course they cannot edit');
ok(!array_filter(call('GET', 'skills/link-targets', [], ['type' => 'course', 'include' => (string) $course_id])->get_data()), 'Author saw a course they cannot edit');
as_user($admin);
ok(call('PUT', 'skills/' . $skill . '/courses', ['course_ids' => []])->get_data()['courses'] === [], 'Course unlink failed');
call('PUT', 'skills/' . $skill . '/courses', ['course_ids' => [$course_id]]);
// The course report lists linked skills even before evidence exists.
$matrix = call('GET', 'reports/skills', [], ['course_id' => $course_id])->get_data();
ok(in_array($skill, array_column($matrix['skills'], 'id'), true), 'Course skill missing from the course report');

// ---- Guardian and teacher skill views (school scope) ----
$student = make_user('subscriber');
$guardian = make_user('subscriber');
$teacher = make_user('subscriber');
$stranger = make_user('subscriber');
$now = current_time('mysql', true);
$wpdb->insert(SchoolSchema::table('schools'), ['name' => 'Skill school', 'slug' => 'skill-school-' . wp_generate_password(6, false, false), 'timezone' => 'UTC', 'status' => 'active', 'created_by' => $admin, 'created_at' => $now]);
$school = (int) $wpdb->insert_id;
foreach ([[$student, 'student'], [$teacher, 'teacher'], [$guardian, 'guardian']] as [$user, $role]) {
    $wpdb->insert(SchoolSchema::table('school_memberships'), ['school_id' => $school, 'user_id' => $user, 'role' => $role, 'status' => 'active', 'joined_at' => $now]);
}
$wpdb->insert(SchoolSchema::table('guardian_links'), ['school_id' => $school, 'guardian_user_id' => $guardian, 'student_user_id' => $student, 'status' => 'active', 'approved_by' => $admin, 'approved_at' => $now]);
$wpdb->insert(SchoolSchema::table('classes'), ['school_id' => $school, 'academic_year_id' => 0, 'name' => 'Skill class', 'status' => 'active']);
$class = (int) $wpdb->insert_id;
foreach ([[$student, 'student'], [$teacher, 'teacher']] as [$user, $role]) {
    $wpdb->insert(SchoolSchema::table('class_memberships'), ['class_id' => $class, 'user_id' => $user, 'role' => $role, 'status' => 'active', 'joined_at' => $now]);
}
$wpdb->insert($t('student_skill_state'), ['student_id' => $student, 'term_id' => $skill, 'level' => 'proficient', 'evidence_count' => 4, 'independent_correct' => 3, 'families' => 2, 'score' => 0.75, 'last_evidence_at' => $now, 'updated_at' => $now]);
$path = 'reports/skills/students/' . $student;
try {
    as_user($guardian);
    $view = call('GET', $path, [], ['school_id' => $school]);
    ok(status_of($view) === 200 && $view->get_data()['skills'][0]['level'] === 'proficient', 'Guardian could not see their child: ' . wp_json_encode($view->get_data()));
    ok($view->get_data()['evidence'] === [], 'Guardian received the answer log');
    ok(status_of(call('GET', $path)) === 403, 'Guardian view without school scope');
    ok(status_of(call('GET', 'reports/skills', [], ['class_id' => $class])) === 403, 'Guardian opened the class matrix');
    as_user($teacher);
    ok(status_of(call('GET', $path, [], ['school_id' => $school])) === 200, 'School teacher could not see a school learner');
    $matrix = call('GET', 'reports/skills', [], ['class_id' => $class]);
    ok(status_of($matrix) === 200 && (array) $matrix->get_data()['students'][0]['skills'] && in_array($skill, array_column($matrix->get_data()['skills'], 'id'), true), 'Class matrix missing the learner skill');
    as_user($stranger);
    ok(status_of(call('GET', $path, [], ['school_id' => $school])) === 403, 'Stranger saw a learner via school_id');
    $wpdb->update(SchoolSchema::table('guardian_links'), ['status' => 'inactive'], ['school_id' => $school, 'guardian_user_id' => $guardian]);
    as_user($guardian);
    ok(status_of(call('GET', $path, [], ['school_id' => $school])) === 403, 'Revoked guardian still saw the learner');
    as_user($student);
    ok(call('GET', $path)->get_data()['skills'][0]['id'] === $skill, 'Learner could not see own skills');
} finally {
    as_user($admin);
    foreach (['guardian_links', 'school_memberships', 'classes'] as $table) { $wpdb->delete(SchoolSchema::table($table), ['school_id' => $school]); }
    $wpdb->delete(SchoolSchema::table('schools'), ['id' => $school]);
    $wpdb->delete(SchoolSchema::table('class_memberships'), ['class_id' => $class]);
}

// ---- Frozen media ----
$uploads = wp_upload_dir();
$file = trailingslashit($uploads['path']) . 'ohmylms-freeze-' . wp_generate_password(6, false, false) . '.png';
$png = static function ($red) { $image = imagecreatetruecolor(4, 4); imagefill($image, 0, 0, imagecolorallocate($image, $red, 10, 10)); ob_start(); imagepng($image); return ob_get_clean(); };
file_put_contents($file, $png(200));
$attachment = wp_insert_attachment(['post_mime_type' => 'image/png', 'post_title' => 'Freeze', 'post_status' => 'inherit'], $file);
remember_post($attachment);
$question = choice_question($quiz_id, 'Pictured question', 2);
set_post_thumbnail($question, $attachment);
$body_url = trailingslashit($uploads['url']) . basename($file);
call('PUT', 'question/' . $question, ['description' => '<p>See <img src="' . $body_url . '" alt=""></p>']);
$first = VersionPublisher::capture($question);
$snapshot = VersionPublisher::snapshot((int) $first['id']);
$frozen = $snapshot->frozen();
ok(isset($frozen['image'], $frozen['body:' . md5($body_url)]), 'Media not frozen: ' . wp_json_encode($frozen));
$frozen_path = MediaFreezer::path_for_url($frozen['image']['url']);
ok($frozen_path === null && strpos($frozen['image']['url'], '/' . MediaFreezer::DIR . '/') !== false, 'Frozen copy is not in frozen storage');
$frozen_file = trailingslashit($uploads['basedir']) . substr($frozen['image']['url'], strlen(trailingslashit($uploads['baseurl'])));
ok(is_file($frozen_file) && hash_file('sha256', $frozen_file) === hash_file('sha256', $file), 'Frozen copy differs from the source');
ok($snapshot->get_image_url() === $frozen['image']['url'] && strpos($snapshot->get_description(), $frozen['image']['url']) !== false && strpos($snapshot->get_description(), $body_url) === false, 'Snapshot still serves live media');
ok((int) VersionPublisher::capture($question)['id'] === (int) $first['id'], 'Unchanged media created a version');
// Replace the file in place: old version keeps the old copy, the next capture is a new version.
file_put_contents($file, $png(20));
touch($file, time() + 5);
clearstatcache();
$second = VersionPublisher::capture($question);
ok((int) $second['id'] !== (int) $first['id'] && $second['content_hash'] === $first['content_hash'], 'Replaced file did not create a new version');
$new_frozen = VersionPublisher::snapshot((int) $second['id'])->frozen();
ok($new_frozen['image']['sha256'] === hash_file('sha256', $file) && $new_frozen['image']['url'] !== $frozen['image']['url'], 'New version did not freeze the replacement');
ok(VersionPublisher::snapshot((int) $first['id'])->get_image_url() === $frozen['image']['url'] && hash_file('sha256', $frozen_file) === $frozen['image']['sha256'], 'Old version changed after replacement');
// Deleting the media library item does not break the old version.
wp_delete_attachment($attachment, true);
ok(is_file($frozen_file) && VersionPublisher::snapshot((int) $first['id'])->get_image_url() === $frozen['image']['url'], 'Frozen copy lost with the attachment');
ok(MediaFreezer::path_for_url('https://elsewhere.example/a.png') === null && MediaFreezer::path_for_url($uploads['baseurl'] . '/../wp-config.php') === null, 'Outside files accepted');
@unlink($file);
foreach ([$frozen_file, trailingslashit($uploads['basedir']) . substr($new_frozen['image']['url'], strlen(trailingslashit($uploads['baseurl'])))] as $copy) { @unlink($copy); }

// ---- Page breaks ----
[$exam_course, $exam] = course_with_quiz($admin, ['layout' => 'all_questions_in_one_page', 'allow_attempts' => 5]);
$q1 = choice_question($exam, 'Page one A', 1);
$q2 = choice_question($exam, 'Page one B', 1);
$q3 = choice_question($exam, 'Page two', 1);
$saved = call('PUT', 'quiz/' . $exam . '/assessment-settings', ['sections' => [
    ['title' => 'Part A', 'questions' => [$q1, $q2], 'marks' => []],
    ['title' => 'Part B', 'questions' => [$q3], 'marks' => [], 'new_page' => true],
]]);
ok(status_of($saved) === 200 && $saved->get_data()['settings']['sections'][1]['new_page'] === true && $saved->get_data()['settings']['sections'][0]['new_page'] === false, 'Page break not saved: ' . wp_json_encode($saved->get_data()));
$revision = RevisionPublisher::publish($exam);
ok(array_column(RevisionPublisher::slots((int) $revision['id']), 'page', 'question_id') == [$q1 => 1, $q2 => 1, $q3 => 2], 'Slots not paged');
$learner = make_user('subscriber');
enroll($learner, $exam_course);
as_user($learner);
$attempt = Submission::start($exam, $learner); $fixture['attempts'][] = $attempt;
ok(array_column(AttemptItems::delivery($attempt), 'page', 'id') == [$q1 => 1, $q2 => 1, $q3 => 2], 'Delivery lost page numbers');
// The paper renders as two pages even though the quiz shows all questions on one page.
$GLOBALS['post'] = get_post($exam); setup_postdata($GLOBALS['post']);
ob_start();
$_GET['quiz'] = 'start';
ohmylms_get_template('single-lesson/quiz-form.php');
unset($_GET['quiz']);
$html = ob_get_clean();
wp_reset_postdata();
ok(substr_count($html, "class='ohmylms-question-group question-group-") === 2 && strpos($html, 'total-group="2"') !== false && strpos($html, 'ohmylms-grouped-questions') !== false, 'Paper not split into two pages');
as_user($admin);
$exam_preview = call('POST', 'quiz/' . $exam . '/preview')->get_data();
ok(array_column($exam_preview['questions'], 'page') === [1, 1, 2], 'Preview lost page numbers');

// ---- Gradebook maximum without attempts follows the published revision ----
ok(abs(\OhMyLMS\Schools\Gradebook::items($exam_course)[0]['max'] - 3.0) < 1e-9, 'Gradebook max is not the revision total');
call('PUT', 'question/' . $q3, ['settings' => ['type' => 'single-choice', 'score' => ['enabled' => true, 'value' => 5]]]);
ok((float) ohmylms_get_quiz($exam)->get_total_marks() === 7.0, 'Live total did not change');
ok(abs(\OhMyLMS\Schools\Gradebook::items($exam_course)[0]['max'] - 3.0) < 1e-9, 'Unpublished edit changed the gradebook max');
RevisionPublisher::publish($exam);
ok(abs(\OhMyLMS\Schools\Gradebook::items($exam_course)[0]['max'] - 7.0) < 1e-9, 'Published revision total not used');

// ---- New question written directly in the bank (no quiz) ----
$writer = make_user('author');
as_user($writer);
$created = call('POST', 'question', ['name' => 'Bank-only fraction', 'description' => '\(\frac{1}{2}\) of 8?',
    'settings' => ['type' => 'single-choice', 'required' => false, 'score' => ['enabled' => true, 'value' => 2]],
    'questions' => [['answer' => '4', 'is_correct' => 1, 'order_number' => 1], ['answer' => '2', 'is_correct' => 0, 'order_number' => 2]]]);
ok(status_of($created) === 201, 'Bank-only question not created: ' . wp_json_encode($created->get_data()));
$bank_only = remember_post($created->get_data()['id']);
ok(!$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}ohmylms_quiz_questions_relationship WHERE question_id=%d", $bank_only)), 'Bank-only question was placed in a quiz');
ok(strpos(get_post_field('post_content', $bank_only), '\frac{1}{2}') !== false, 'LaTeX lost on bank-only question');
$listed = call('GET', 'question-bank', [], ['search' => 'Bank-only fraction'])->get_data();
ok(in_array($bank_only, array_map('intval', array_column($listed['items'], 'id')), true), 'Bank-only question missing from the bank');
ok(status_of(call('POST', 'question-bank/' . $bank_only . '/approve')) === 200, 'Bank-only question could not be approved');
$structured = call('POST', 'question', ['name' => 'Bank-only parts', 'settings' => ['type' => 'structured', 'score' => ['enabled' => true, 'value' => 3],
    'parts' => [['id' => 'a', 'kind' => 'numerical', 'marks' => 1, 'answer' => 4], ['id' => 'b', 'kind' => 'written', 'marks' => 2]]], 'questions' => []]);
ok(status_of($structured) === 201, 'Bank-only structured question not created: ' . wp_json_encode($structured->get_data()));
remember_post($structured->get_data()['id']);
ok(status_of(call('POST', 'question', ['name' => 'Bad numerical', 'settings' => ['type' => 'numerical', 'answer' => 'x', 'score' => ['enabled' => true, 'value' => 1]], 'questions' => []])) === 400, 'Invalid bank-only question accepted');
as_user(make_user('subscriber'));
ok(status_of(call('POST', 'question', ['name' => 'Learner question', 'settings' => ['type' => 'long-text'], 'questions' => []])) === 403, 'A learner created a bank question');
as_user($admin);

// ---- Design tokens: settings become CSS variables; bad values fall back ----
use OhMyLMS\Design\Tokens;
$saved_design = [];
foreach (array_keys(Tokens::DEFAULTS) as $key) { $saved_design[$key] = get_option($key, null); }
try {
    ok(Tokens::mix('#6e42d3', '#000000', 0) === '#6e42d3' && Tokens::mix('#000000', '#ffffff', 0.5) === '#808080' && Tokens::rgb('#2563eb') === '37, 99, 235', 'Color math');
    update_option('ohmylms_admin_primary_color', '#2563EB');
    update_option('ohmylms_admin_font_family', 'Noto Sans');
    $admin_css = \OhMyLMS\Design\DesignSystem::css();
    ok(strpos($admin_css, '--wp-admin-theme-color:#2563eb;') !== false && strpos($admin_css, '--primary-6:37, 99, 235;') !== false && strpos($admin_css, '"Noto Sans"') !== false && strpos($admin_css, ':not(.dashicons)') !== false, 'Admin tokens not built from settings');
    ok(Tokens::font_url('Noto Sans') === 'https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;500;600;700&display=swap' && Tokens::font_url('system') === '', 'Font URL');
    update_option('ohmylms_admin_primary_color', 'red;}body{display:none');
    update_option('ohmylms_admin_font_family', 'Comic"; x');
    $admin_css = \OhMyLMS\Design\DesignSystem::css();
    ok(strpos($admin_css, '--wp-admin-theme-color:#6e42d3;') !== false && strpos($admin_css, 'display:none') === false && strpos($admin_css, 'Comic') === false, 'Invalid design values were not rejected');
    update_option('ohmylms_primary_color_scheme', '#F80');
    update_option('ohmylms_font_family', 'inherit');
    $front = Tokens::frontend_css();
    ok(strpos($front, '--ohmylms-primary-color:#ff8800;') !== false && strpos($front, '--ohmylms-font-family') === false && strpos($front, 'font-family:var') === false, 'Theme font should leave the variable undefined');
    update_option('ohmylms_font_family', 'Montserrat');
    ok(strpos(Tokens::frontend_css(), '--ohmylms-font-family:"Montserrat"') !== false, 'Learner font not applied');
    // The Design settings group saves the new keys.
    $request = new WP_REST_Request('POST', '/ohmylms/v1/settings/design');
    $request->set_header('Content-Type', 'application/json');
    $request->set_body(wp_json_encode(['ohmylms_admin_muted_color' => '#123456', 'ohmylms_font_family' => 'Inter']));
    $response = rest_do_request($request);
    ok($response->get_status() === 200 && get_option('ohmylms_admin_muted_color') === '#123456' && get_option('ohmylms_font_family') === 'Inter', 'Design settings did not save the new keys: ' . wp_json_encode($response->get_data()));
} finally {
    foreach ($saved_design as $key => $value) { $value === null ? delete_option($key) : update_option($key, $value); }
}
