<?php
/** Pure checks for lesson-group arrangement, advisory course-link rules and the course_links schema (no WordPress database). */
define('ABSPATH', __DIR__ . '/');
function __($text) { return $text; }
require dirname(__DIR__, 2) . '/vendor/autoload.php';
use OhMyLMS\Curriculum\CourseLinks;
use OhMyLMS\Curriculum\Schema;
use OhMyLMS\Learning\ProgramGroups;
$checks = 0;
function check($condition, $message) { global $checks; if (!$condition) { throw new RuntimeException($message); } $checks++; }

// ---- Outline grouping ----
$groups = [['id' => 'g1', 'chapter_id' => 10, 'title' => 'Intro'], ['id' => 'g2', 'chapter_id' => 10, 'title' => 'Empty'], ['id' => 'g3', 'chapter_id' => 20, 'title' => 'Other unit']];
$rows = [['name' => 'a', 'group_id' => 'g1'], ['name' => 'b', 'group_id' => ''], ['name' => 'c', 'group_id' => 'g1'], ['name' => 'd']];
$arranged = ProgramGroups::arrange($groups, $rows, 10);
check(count($arranged['groups']) === 1 && $arranged['groups'][0]['title'] === 'Intro', 'only populated groups of this unit are returned');
check(array_column($arranged['groups'][0]['items'], 'name') === ['a', 'c'], 'group items keep their order');
check(array_column($arranged['ungrouped'], 'name') === ['b', 'd'], 'ungrouped rows keep their order');
$plain = ProgramGroups::arrange([], $rows, 10);
check($plain['groups'] === [] && count($plain['ungrouped']) === 4, 'no groups: everything is ungrouped, as today');
$orphan = ProgramGroups::arrange([], [['name' => 'x', 'group_id' => 'gone']], 10);
check(count($orphan['ungrouped']) === 1, 'a row pointing at a missing group is still shown');
check(ProgramGroups::check_member($groups, 'g1', 10) === null && ProgramGroups::check_member($groups, '', 10) === null, 'members and ungrouped pass');
check(ProgramGroups::check_member($groups, 'g1', 20) === 'group_chapter' && ProgramGroups::check_member($groups, 'zz', 10) === 'group', 'wrong unit and unknown group fail');

// ---- Course link rules ----
check(CourseLinks::creates_cycle([], 1, 1), 'a self link is a cycle');
check(CourseLinks::creates_cycle([2 => [3], 3 => [1]], 1, 2), 'a direct-or-indirect loop is a cycle');
check(!CourseLinks::creates_cycle([2 => [3]], 1, 2), 'a chain is not a cycle');
check(!CourseLinks::creates_cycle([2 => [3], 4 => [3]], 1, 3), 'a shared target is not a cycle');
$ok = CourseLinks::validate(1, [['course_id' => 2, 'type' => 'subcourse'], ['course_id' => 3, 'type' => 'get_ready']], []);
check(is_array($ok) && array_column($ok, 'position') === [0, 1], 'valid links get positions');
check(CourseLinks::validate(1, [['course_id' => 1, 'type' => 'subcourse']], []) === 'self', 'no self link');
check(CourseLinks::validate(1, [['course_id' => 2, 'type' => 'friend']], []) === 'type', 'type is whitelisted');
check(CourseLinks::validate(1, [['course_id' => 2, 'type' => 'subcourse'], ['course_id' => 2, 'type' => 'subcourse']], []) === 'duplicate', 'same link twice is rejected');
check(is_array(CourseLinks::validate(1, [['course_id' => 2, 'type' => 'subcourse'], ['course_id' => 2, 'type' => 'get_ready']], [])), 'the same course may be linked in two different ways');
check(CourseLinks::validate(1, [['course_id' => 2, 'type' => 'prerequisite']], ['prerequisite' => [2 => [1]]]) === 'cycle', 'a prerequisite loop through another course is rejected');
check(CourseLinks::validate(1, [['course_id' => 2, 'type' => 'subcourse']], ['subcourse' => [2 => [1]]]) === 'cycle', 'a sub-course loop is rejected');
check(is_array(CourseLinks::validate(1, [['course_id' => 2, 'type' => 'subcourse']], ['prerequisite' => [2 => [1]]])), 'a course may contain one that requires it: sub-courses and prerequisites are separate graphs');
check(is_array(CourseLinks::validate(1, [['course_id' => 2, 'type' => 'prerequisite']], ['subcourse' => [2 => [1]]])), 'and a prerequisite may be the parent of a sub-course');
check(CourseLinks::validate(1, [['course_id' => 2, 'type' => 'subcourse'], ['course_id' => 3, 'type' => 'subcourse']], ['subcourse' => [3 => [1]]]) === 'cycle', 'a loop through a link of this same submission and the stored graph is rejected');
check(is_array(CourseLinks::validate(1, [['course_id' => 2, 'type' => 'get_ready']], ['subcourse' => [2 => [1]], 'prerequisite' => [2 => [1]]])), 'get-ready suggestions may point both ways');
check(is_array(CourseLinks::validate(1, [['course_id' => 2, 'type' => 'subcourse']], ['subcourse' => [1 => [9]]])), "this course's own old edges are ignored while replacing");
$many = [];
for ($i = 2; $i < 54; $i++) { $many[] = ['course_id' => $i, 'type' => 'get_ready']; }
check(CourseLinks::validate(1, $many, []) === 'too_many', 'more than 50 links are rejected');
check(CourseLinks::validate(1, 'x', []) === 'invalid' && CourseLinks::validate(1, [['type' => 'subcourse']], []) === 'invalid', 'malformed input is rejected');

// ---- Lesson group carry-over (Content Hub saves rows without group_id) ----
$stored = [['id' => 'i1', 'chapter_id' => 10, 'group_id' => 'g1'], ['id' => 'i2', 'chapter_id' => 10], ['id' => 'i3', 'chapter_id' => 10, 'group_id' => 'g1']];
$sent = ProgramGroups::carry([['id' => 'i1', 'chapter_id' => 10], ['id' => 'i2', 'chapter_id' => 10], ['id' => 'i3', 'chapter_id' => 20], ['id' => 'new', 'chapter_id' => 10]], $stored, 'id');
check(($sent[0]['group_id'] ?? null) === 'g1', 'a row sent without group_id keeps its group');
check(!isset($sent[1]['group_id']) && !isset($sent[3]['group_id']), 'ungrouped and new rows stay ungrouped');
check(!isset($sent[2]['group_id']), 'a row that moved to another unit loses its group');
$sent = ProgramGroups::carry([['id' => 'i1', 'chapter_id' => 10, 'group_id' => '']], $stored, 'id');
check($sent[0]['group_id'] === '', 'an explicit empty group_id clears the group');
$outcomes = ProgramGroups::carry([['term_id' => 5, 'chapter_id' => 10], ['term_id' => 6]], [['term_id' => 5, 'chapter_id' => 10, 'group_id' => 'g1'], ['term_id' => 6, 'group_id' => 'g1']], 'term_id');
check(($outcomes[0]['group_id'] ?? null) === 'g1' && ($outcomes[1]['group_id'] ?? null) === 'g1', 'outcomes are matched by term_id');

// ---- Copying a program to a duplicated or imported course ----
use OhMyLMS\Learning\CourseProgram;
$program = [
    'id' => 9, 'version' => 2, 'course_id' => 1, 'mode' => 'blended',
    'groups' => [['id' => 'g1', 'chapter_id' => 10, 'title' => 'Intro'], ['id' => 'g2', 'chapter_id' => 30, 'title' => 'Gone']],
    'outcomes' => [['term_id' => 5, 'target' => 'proficient', 'required' => true, 'chapter_id' => 10, 'group_id' => 'g1'], ['term_id' => 6, 'target' => 'proficient', 'required' => false]],
    'items' => [
        ['id' => 'i1', 'type' => 'lesson', 'content_id' => 100, 'chapter_id' => 10, 'group_id' => 'g1', 'skill_ids' => [5]],
        ['id' => 'i2', 'type' => 'practice', 'content_id' => 5, 'chapter_id' => 10],
        ['id' => 'i3', 'type' => 'quiz', 'content_id' => 300, 'chapter_id' => 30, 'group_id' => 'g2'],
        ['id' => 'i4', 'type' => 'lesson', 'content_id' => 400, 'chapter_id' => 20],
    ],
];
$copy = CourseProgram::remap($program, [10 => 110, 20 => 120], [100 => 1100, 400 => 1400]);
check(!isset($copy['id']) && !isset($copy['version']) && !isset($copy['course_id']) && $copy['mode'] === 'blended', 'row identifiers are dropped, settings kept');
check(count($copy['groups']) === 1 && $copy['groups'][0] === ['id' => 'g1', 'chapter_id' => 110, 'title' => 'Intro'], 'groups follow their unit and groups of uncopied units are dropped');
check($copy['outcomes'][0]['chapter_id'] === 110 && $copy['outcomes'][0]['group_id'] === 'g1' && !isset($copy['outcomes'][1]['chapter_id']), 'outcomes follow their unit and keep their group');
check($copy['items'][0]['chapter_id'] === 110 && $copy['items'][0]['content_id'] === 1100 && $copy['items'][0]['group_id'] === 'g1' && $copy['items'][0]['skill_ids'] === [5], 'lessons get the new unit and content, keeping group and skills');
check($copy['items'][1]['content_id'] === 5, 'a practice item points at a skill, which is not remapped');
check($copy['items'][2]['chapter_id'] === 0 && !isset($copy['items'][2]['group_id']) && $copy['items'][2]['content_id'] === 300, 'a row whose unit was not copied becomes unplaced and ungrouped');
check($copy['items'][3]['chapter_id'] === 120 && $copy['items'][3]['content_id'] === 1400 && !isset($copy['items'][3]['group_id']), 'an ungrouped row stays ungrouped');
$plain = CourseProgram::remap(['mode' => 'traditional', 'outcomes' => [], 'items' => []], [], []);
check(!isset($plain['groups']), 'a program without groups gets none');

// ---- Schema ----
// course_links arrived in version 7; later features may bump the version further.
check(version_compare(Schema::VERSION, '7', '>='), 'schema version includes course_links');
$definitions = Schema::definitions();
check(isset($definitions['course_links']), 'course_links is defined');
foreach (['course_id bigint unsigned', 'related_course_id bigint unsigned', 'link_type varchar(20)', 'position int unsigned', 'created_by', 'created_at datetime', 'UNIQUE KEY course_link (course_id,related_course_id,link_type)', 'KEY related (related_course_id,link_type)'] as $part) {
    check(strpos($definitions['course_links'], $part) !== false, "course_links has $part");
}
echo "Course links checks passed: $checks\n";
