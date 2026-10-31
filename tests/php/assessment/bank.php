<?php
/** Phase 3: shared bank reuse, approval, read-only use, pins, duplicate, skills and mappings. */
use OhMyLMS\Assessment\AttemptItems;
use OhMyLMS\Quiz\Submission;
use OhMyLMS\QuestionBank\SkillMap;
use OhMyLMS\QuestionBank\VersionPublisher;
use OhMyLMS\Skills\Taxonomy;

global $wpdb;
$owner = make_user('author');
$colleague = make_user('author');
$outsider = make_user('author');

// ---- Skills catalogue (A07) ----
as_user($owner);
$fractions = call('POST', 'skills', ['name' => 'Fractions ' . wp_generate_password(4, false, false), 'code' => 'FR.1']);
ok(status_of($fractions) === 201, 'Skill create failed: ' . wp_json_encode($fractions->get_data()));
$fractions = $fractions->get_data(); $fixture['terms'][] = [$fractions['id'], Taxonomy::NAME];
$equations = call('POST', 'skills', ['name' => 'Linear equations ' . wp_generate_password(4, false, false), 'prerequisites' => [$fractions['id']]])->get_data();
$fixture['terms'][] = [$equations['id'], Taxonomy::NAME];
ok(preg_match('/^[0-9a-f-]{36}$/', $equations['uuid']) && $equations['prerequisites'] === [$fractions['id']], 'Skill UUID or prerequisite missing');
$cycle = call('PUT', 'skills/' . $fractions['id'], ['prerequisites' => [$equations['id']]]);
ok(status_of($cycle) === 400 && $cycle->get_data()['code'] === 'ohmylms_skill_cycle', 'Prerequisite cycle accepted');
ok(status_of(call('PUT', 'skills/' . $fractions['id'], ['prerequisites' => [$fractions['id']]])) === 400, 'Self prerequisite accepted');
$lesson = remember_post(wp_insert_post(['post_type' => OHMYLMS_LESSON_CPT, 'post_title' => 'Fractions lesson', 'post_status' => 'publish', 'post_author' => $owner]));
$linked = call('PUT', 'skills/' . $fractions['id'] . '/lessons', ['lesson_ids' => [$lesson]]);
ok(status_of($linked) === 200 && $linked->get_data()['lessons'] === [$lesson], 'Lesson link failed');
ok(get_user_meta($owner, 'skills', true) === '' || get_user_meta($owner, 'skills', true) === false, 'Profile skills touched');

// ---- Shared bank and approval (A06) ----
$bank = call('POST', 'question-banks', ['name' => 'Algebra department', 'visibility' => 'shared'])->get_data();
$fixture['banks'][] = (int) $bank['id'];
ok((int) $bank['owner_id'] === $owner, 'Bank owner wrong');
$quiz_owner = remember_post(call('POST', 'quiz', ['name' => 'Owner quiz', 'status' => 'publish'])->get_data()['id']);
$created = call('POST', 'question', ['quiz_id' => $quiz_owner, 'name' => 'Solve 2x=6', 'settings' => ['type' => 'single-choice', 'score' => ['enabled' => true, 'value' => 2]],
    'questions' => [['answer' => '3', 'is_correct' => 1], ['answer' => '12']], 'skills' => ['p1' => ['primary' => $equations['id'], 'supporting' => [$fractions['id']]]],
    'bank' => ['bank_id' => $bank['id'], 'difficulty' => 'easy', 'source' => 'Textbook 4.2', 'family_id' => 'solve-ax-b']]);
ok(status_of($created) === 201, 'Bank question create failed: ' . wp_json_encode($created->get_data()));
$shared = remember_post($created->get_data()['id']);
$identity = VersionPublisher::identity($shared);
ok((int) $identity['bank_id'] === (int) $bank['id'] && $identity['difficulty'] === 'easy' && $identity['family_id'] === 'solve-ax-b', 'Bank attributes not stored');
$frozen_skills = SkillMap::for_version($identity['current_version_id']);
ok(count($frozen_skills) === 2 && $frozen_skills[0]['role'] === 'primary', 'Skill map not frozen into version');

as_user($colleague);
ok(status_of(call('GET', 'question-bank/' . $shared)) === 403, 'Ungranted colleague could open a bank question');
as_user($owner);
ok(status_of(call('POST', 'question-banks/' . $bank['id'] . '/grants', ['user_id' => $colleague, 'permission' => 'use'])) === 200, 'Grant failed');

as_user($colleague);
$quiz_colleague = remember_post(call('POST', 'quiz', ['name' => 'Colleague quiz', 'status' => 'publish'])->get_data()['id']);
$search = call('GET', 'question-bank', [], ['bank' => $bank['id'], 'skill' => $equations['id'], 'difficulty' => 'easy']);
ok(status_of($search) === 200 && in_array($shared, array_column($search->get_data()['items'], 'id'), true), 'Bank search did not find the shared question');
$add = call('POST', 'quiz/' . $quiz_colleague . '/questions', ['question_ids' => [$shared]]);
ok(status_of($add) === 409, 'Unapproved shared question was placed in a quiz');
as_user($owner);
$approve = call('POST', 'questions/' . $shared . '/approve');
ok(status_of($approve) === 200 && $approve->get_data()['approved_is_current'], 'Approve failed');
$approved_version = (int) $approve->get_data()['approved_version_id'];

as_user($colleague);
$add = call('POST', 'quiz/' . $quiz_colleague . '/questions', ['question_ids' => [$shared]]);
ok(status_of($add) === 200 && linked($quiz_colleague, $shared), 'Approved shared question could not be reused');
ok(status_of(call('PUT', 'question/' . $shared, ['name' => 'Colleague edit'])) === 403, 'Use-only colleague edited the shared question');
$content = call('GET', 'quiz/' . $quiz_colleague)->get_data()['content'];
ok(!empty($content[0]['readonly']) && $content[0]['uuid'] === $identity['uuid'], 'Quiz editor not told the question is read-only');
// Saving the whole quiz with the unchanged shared question is fine; changing it is refused.
$unchanged = $content[0];
ok(status_of(call('PUT', 'quiz/' . $quiz_colleague, ['name' => 'Colleague quiz saved', 'content' => [$unchanged]])) === 200, 'Unchanged read-only question blocked the quiz save');
$changed = $unchanged; $changed['name'] = 'Sneaky change';
ok(status_of(call('PUT', 'quiz/' . $quiz_colleague, ['content' => [$changed]])) === 403, 'Changed read-only question was saved');
ok(get_the_title($shared) === 'Solve 2x=6', 'Shared question changed through another quiz');

// The owner edits; the colleague's quiz keeps the approved version until a new approval.
as_user($owner);
call('PUT', 'question/' . $shared, ['name' => 'Solve 2x=8', 'questions' => [['id' => option_rows($shared)[0]['id'], 'answer' => '4', 'is_correct' => 1], ['id' => option_rows($shared)[1]['id'], 'answer' => '16']]]);
$edited_version = (int) VersionPublisher::identity($shared)['current_version_id'];
ok($edited_version !== $approved_version, 'Owner edit did not create a version');
$owner_revision = call('POST', 'quiz/' . $quiz_owner . '/revisions')->get_data()['revision'];
as_user($colleague);
$colleague_revision = call('POST', 'quiz/' . $quiz_colleague . '/revisions')->get_data()['revision'];
ok((int) $owner_revision['slots'][0]['version_id'] === $edited_version, 'Owner quiz did not use the current version');
ok((int) $colleague_revision['slots'][0]['version_id'] === $approved_version, 'Colleague quiz did not stay on the approved version');

// Pinning: the owner pins their quiz to the approved version.
as_user($owner);
ok(status_of(call('PUT', 'quiz/' . $quiz_owner . '/questions/' . $shared . '/pin', ['version_id' => $approved_version])) === 200, 'Pin failed');
ok((int) call('POST', 'quiz/' . $quiz_owner . '/revisions')->get_data()['revision']['slots'][0]['version_id'] === $approved_version, 'Pinned version not used');
call('PUT', 'quiz/' . $quiz_owner . '/questions/' . $shared . '/pin', ['version_id' => 0]);

// Duplicate-to-edit inside the colleague's quiz: the copy replaces the reference; the original is untouched.
as_user($colleague);
$duplicate = call('POST', 'question-bank/' . $shared . '/duplicate', ['quiz_id' => $quiz_colleague]);
ok(status_of($duplicate) === 201, 'Duplicate failed: ' . wp_json_encode($duplicate->get_data()));
$copy = remember_post($duplicate->get_data()['id']);
ok(linked($quiz_colleague, $copy) && !linked($quiz_colleague, $shared) && linked($quiz_owner, $shared), 'Duplicate did not replace only the colleague reference');
ok($duplicate->get_data()['family_id'] === 'solve-ax-b' && $duplicate->get_data()['uuid'] !== $identity['uuid'], 'Duplicate lost family or reused UUID');
ok(status_of(call('PUT', 'question/' . $copy, ['name' => 'Colleague version'])) === 200 && get_the_title($shared) === 'Solve 2x=8', 'Duplicate not independently editable');

// Outsiders see nothing.
as_user($outsider);
$search = call('GET', 'question-bank', [], ['search' => 'Solve']);
ok(!in_array($shared, array_column($search->get_data()['items'], 'id'), true), 'Bank search leaked a private bank question');
ok(status_of(call('POST', 'quiz/' . $quiz_colleague . '/questions', ['question_ids' => [$shared]])) === 403, 'Outsider edited another quiz');

// A learner attempt on the colleague quiz (now the copy) and on the owner quiz each freeze their own version.
as_user($admin);
$wpdb->delete($wpdb->prefix . 'ohmylms_quiz_questions_relationship', ['quiz_id' => $quiz_colleague, 'question_id' => $copy]);
\OhMyLMS\QuestionBank\DraftWriter::link($quiz_colleague, $shared);
$course = remember_post(call('POST', 'courses', ['name' => 'Bank course', 'status' => 'publish'])->get_data()['id']);
$chapter = remember_post(wp_insert_post(['post_type' => 'ohmylms-chapter', 'post_title' => 'Bank chapter', 'post_status' => 'publish']));
$wpdb->insert($wpdb->prefix . 'ohmylms_chapter_relationship', ['course_id' => $course, 'chapter_id' => $chapter, 'order_number' => 0]);
foreach ([$quiz_owner, $quiz_colleague] as $i => $quiz) { $wpdb->insert($wpdb->prefix . 'ohmylms_content_relationship', ['chapter_id' => $chapter, 'content_id' => $quiz, 'content_type' => 'quiz', 'order_number' => $i]); }
$learner = make_user('subscriber');
enroll($learner, $course);
as_user($learner);
$a1 = Submission::start($quiz_owner, $learner); $a2 = Submission::start($quiz_colleague, $learner);
$fixture['attempts'][] = $a1; $fixture['attempts'][] = $a2;
ok((int) AttemptItems::items($a1)[0]['version_id'] === $edited_version && (int) AttemptItems::items($a2)[0]['version_id'] === $approved_version, 'Attempts did not freeze their quiz-specific versions');
Submission::submit($quiz_owner, $a1, $learner, [], 'exit'); Submission::submit($quiz_colleague, $a2, $learner, [], 'exit');

// Archive keeps history; archived questions leave default search.
as_user($owner);
ok(status_of(call('POST', 'question-bank/' . $shared . '/archive')) === 200 && get_post_status($shared) === 'ohmylms_archived', 'Archive failed');
ok(!in_array($shared, array_column(call('GET', 'question-bank', [], ['bank' => $bank['id']])->get_data()['items'], 'id'), true), 'Archived question still listed');
ok(in_array($shared, array_column(call('GET', 'question-bank', [], ['bank' => $bank['id'], 'status' => 'archived'])->get_data()['items'], 'id'), true), 'Archived filter empty');
ok(status_of(call('POST', 'question-bank/' . $shared . '/restore')) === 200 && get_post_status($shared) === 'publish', 'Restore failed');

// Skill deletion is refused once mappings are frozen.
as_user($admin);
ok(status_of(call('DELETE', 'skills/' . $equations['id'])) === 409, 'Mapped skill deleted without force');
