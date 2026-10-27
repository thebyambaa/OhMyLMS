<?php
/** Pure unit checks for syllabus CSV row cleaning and import planning (no WordPress database). */
define('ABSPATH', __DIR__ . '/');
function __($text) { return $text; }
require dirname(__DIR__, 2) . '/vendor/autoload.php';
use OhMyLMS\Curriculum\SyllabusCourse;
use OhMyLMS\Curriculum\SyllabusPlan;
use OhMyLMS\Curriculum\SyllabusRows;
$checks = 0;
function check($condition, $message) { global $checks; if (!$condition) { throw new RuntimeException($message); } $checks++; }

$row = static function (array $fields, $line = 0) { static $n = 0; return ['line' => $line ?: ++$n] + $fields; };

// ---- Row cleaning --------------------------------------------------------------------------------
$clean = SyllabusRows::normalize([
    $row(['content' => "  1   Number ", 'group_code' => 'C1.1', 'group' => 'Types of number', 'skill' => "Identify \n and use  natural numbers", 'skill_code' => 'C1.1.1', 'description' => "e.g. 6 000 000 000\r\n\r\n\r\n\r\nsix billion"], 10),
    $row(['content' => '', 'group' => '', 'skill' => '', 'skill_code' => ''], 11),
    $row(['content_code' => '11.1', 'group_code' => 'М11.1А', 'skill_code' => 'М11.1А1', 'skill' => 'Квадрат тэгшитгэлийн графикийг зөв зурах.'], 12),
    $row(['content' => 'Paper 1 > 1 Number > Fractions', 'content_code' => 'F', 'group' => 'Ordering'], 13),
    $row(['group' => 'Always positive', 'skill' => 'Use the conditions a > 0 and D < 0, so x<y is kept', 'skill_code' => "\xEF\xBB\xBFM1.1"], 14),
]);
check($clean['errors'] === [] && $clean['skipped'] === 1 && count($clean['rows']) === 4, 'four usable rows, one blank row skipped');
$first = $clean['rows'][0];
check($first['content_path'] === ['1 Number'] && $first['skill'] === 'Identify and use natural numbers', 'whitespace and line breaks collapse in names');
check($first['description'] === "e.g. 6 000 000 000\n\nsix billion", 'notes keep line breaks but collapse blank-line runs');
check($first['line'] === 10, 'the line number of the file is kept');
$mn = $clean['rows'][1];
check($mn['content_path'] === ['11.1'] && $mn['content_code'] === '' && $mn['group'] === 'М11.1А' && $mn['group_code'] === 'М11.1А', 'a code with no name becomes the name of a content item or group');
check($clean['rows'][2]['content_path'] === ['Paper 1', '1 Number', 'Fractions'] && $clean['rows'][2]['content_code'] === 'F', 'a ">" path makes nested content and the code belongs to the last level');
check(strpos($clean['rows'][3]['skill'], 'a > 0 and D < 0, so x<y is kept') !== false && $clean['rows'][3]['skill_code'] === 'M1.1', 'maths symbols are kept and a byte order mark is removed');

$bad = SyllabusRows::normalize([
    $row(['skill_code' => 'X1'], 3),
    $row(['skill' => str_repeat('a', 191)], 4),
    $row(['skill' => 'ok', 'skill_code' => str_repeat('c', 41)], 5),
    $row(['group' => 'g', 'description' => 'notes alone'], 6),
    $row(['content' => 'a>b>c>d>e>f'], 7),
]);
check(count($bad['errors']) === 4 && array_column($bad['errors'], 'line') === [3, 4, 5, 7], 'each problem is reported against its own line');
check(count($bad['warnings']) === 1 && $bad['warnings'][0]['line'] === 6 && count($bad['rows']) === 1 && $bad['rows'][0]['group'] === 'g' && $bad['rows'][0]['description'] === '', 'a group row with notes but no skill is kept and its notes are dropped with a warning');
check(count(SyllabusRows::normalize($row([]) ? array_fill(0, SyllabusRows::MAX_ROWS + 1, ['skill' => 'x']) : [])['errors']) === 1, 'too many rows is refused as a whole');
$notes = SyllabusRows::normalize([$row(['skill' => '', 'description' => 'orphan note'], 9)]);
check($notes['rows'] === [] && count($notes['warnings']) === 1 && $notes['skipped'] === 1, 'notes with nothing to attach to are skipped with a warning');
check(SyllabusRows::line("a\x80b") === 'a?b', 'invalid UTF-8 is replaced instead of emptying the field');

// ---- Planning ------------------------------------------------------------------------------------
/** Apply planned operations to the state exactly as the importer will, so a second plan can be compared. */
function apply_ops(array $state, array $ops) {
    static $next = 1000;
    $map = [];
    $resolve = static function ($ref) use (&$map) { return is_string($ref) ? $map[$ref] : $ref; };
    foreach ($ops as $op) {
        switch ($op['op']) {
            case 'content': $id = ++$next; $map[$op['ref']] = $id; $state['contents'][] = ['id' => $id, 'parent_id' => $resolve($op['parent']), 'name' => $op['name'], 'code' => $op['code']]; break;
            case 'content_update': foreach ($state['contents'] as &$c) { if ($c['id'] === $op['id']) { $c = array_merge($c, $op['fields']); } } unset($c); break;
            case 'group': $id = ++$next; $map[$op['ref']] = $id; $state['groups'][] = ['id' => $id, 'item_id' => $resolve($op['item']), 'name' => $op['name'], 'code' => $op['code'], 'skills' => []]; break;
            case 'group_update': foreach ($state['groups'] as &$g) { if ($g['id'] === $op['id']) { $g = array_merge($g, $op['fields']); } } unset($g); break;
            case 'skill': $id = ++$next; $map[$op['ref']] = $id; foreach ($state['groups'] as &$g) { if ($g['id'] === $resolve($op['group'])) { $g['skills'][] = ['term_id' => $id, 'name' => $op['name'], 'code' => $op['code'], 'description' => $op['description']]; } } unset($g); break;
            case 'skill_update': foreach ($state['groups'] as &$g) { foreach ($g['skills'] as &$s) { if ($s['term_id'] === $op['term']) { $s = array_merge($s, $op['fields']); } } unset($s); } unset($g); break;
            case 'skill_move':
                $moving = null;
                foreach ($state['groups'] as &$g) { foreach ($g['skills'] as $i => $s) { if ($s['term_id'] === $op['term']) { $moving = $s; unset($g['skills'][$i]); $g['skills'] = array_values($g['skills']); } } } unset($g);
                foreach ($state['groups'] as &$g) { if ($g['id'] === $resolve($op['group'])) { $g['skills'][] = $moving; } } unset($g);
                break;
        }
    }
    return $state;
}
$empty = ['root' => 7, 'contents' => [['id' => 7, 'parent_id' => 0, 'name' => 'Mathematics', 'code' => '0580']], 'groups' => []];
$file = SyllabusRows::normalize([
    $row(['content' => '1 Number', 'group_code' => 'C1.1', 'group' => 'Types of number', 'skill_code' => 'C1.1.1', 'skill' => 'Identify natural numbers', 'description' => 'e.g. six billion']),
    $row(['content' => '1 Number', 'group_code' => 'C1.1', 'group' => 'Types of number', 'skill_code' => 'C1.1.2', 'skill' => 'Identify prime numbers']),
    $row(['content' => '1 Number', 'group_code' => 'C1.2', 'group' => 'Sets', 'skill_code' => 'C1.2.1', 'skill' => 'Use set language']),
    $row(['content_code' => '11.1', 'content' => 'Квадрат тэгшитгэл', 'group_code' => 'М11.1А', 'skill_code' => 'М11.1А1', 'skill' => 'Графикийг зөв зурах.']),
    $row(['content_code' => '11.1', 'content' => 'Квадрат тэгшитгэл', 'group_code' => 'М11.1А', 'skill_code' => 'М11.1А2', 'skill' => 'Шийдийн тоог тодорхойлох.']),
    $row(['content' => '2 Algebra', 'group' => 'Heading only']),
    $row(['content' => '3 Geometry']),
])['rows'];
$plan = SyllabusPlan::build($empty, $file);
$report = $plan['report'];
check($report['valid'] && $report['errors'] === [], 'a clean file plans without errors');
check($report['contents']['create'] === 4 && $report['groups']['create'] === 4 && $report['skills']['create'] === 5 && $report['contents']['unchanged'] + $report['groups']['unchanged'] + $report['skills']['unchanged'] === 0, 'creates 4 contents, 4 groups and 5 skills and recognises nothing as existing (got ' . json_encode([$report['contents'], $report['groups'], $report['skills']]) . ')');
check(array_column(array_filter($plan['ops'], static function ($op) { return $op['op'] === 'content'; }), 'name') === ['1 Number', 'Квадрат тэгшитгэл', '2 Algebra', '3 Geometry'], 'contents are created in file order, once each');
$groups = array_values(array_filter($plan['ops'], static function ($op) { return $op['op'] === 'group'; }));
check($groups[0]['item'] === $plan['ops'][0]['ref'] && $groups[0]['code'] === 'C1.1' && $groups[2]['name'] === 'М11.1А', 'a group sits under its content item and a code-only group is named by its code');
$skill_ops = array_values(array_filter($plan['ops'], static function ($op) { return $op['op'] === 'skill'; }));
check($skill_ops[0]['description'] === 'e.g. six billion' && $skill_ops[1]['group'] === $skill_ops[0]['group'] && $skill_ops[2]['group'] !== $skill_ops[0]['group'], 'skills of one group share it');
check($plan['ops'][0]['parent'] === 7, 'a first-level content item hangs off the syllabus item');

// Applying the plan and importing the same file again changes nothing.
$state = apply_ops($empty, $plan['ops']);
$again = SyllabusPlan::build($state, $file);
check($again['ops'] === [] && $again['report']['valid'], 'importing the same file twice plans no operations');
check($again['report']['skills']['unchanged'] === 5 && $again['report']['skills']['create'] === 0 && $again['report']['groups']['unchanged'] === 4 && $again['report']['contents']['unchanged'] === 4, 'every existing thing is recognised as unchanged');

// Edits: a renamed skill (same code), added notes, a new code for a skill matched by name, a new skill, a moved skill.
$edited = SyllabusRows::normalize([
    $row(['content' => '1 Number', 'group_code' => 'C1.1', 'group' => 'Types of number', 'skill_code' => 'C1.1.1', 'skill' => 'Identify and use natural numbers']),
    $row(['content' => '1 Number', 'group_code' => 'C1.1', 'group' => 'Types of number', 'skill' => '  IDENTIFY   prime numbers ', 'skill_code' => 'C1.1.2', 'description' => 'new notes']),
    $row(['content' => '1 Number', 'group_code' => 'C1.2', 'group' => 'Sets', 'skill_code' => 'C1.2.1', 'skill' => 'Use set language']),
    $row(['content' => '1 Number', 'group_code' => 'C1.2', 'group' => 'Sets', 'skill_code' => 'C1.2.2', 'skill' => 'Use Venn diagrams']),
    $row(['content' => '1 Number', 'group_code' => 'C1.2', 'group' => 'Sets', 'skill_code' => 'C1.1.1', 'skill' => 'Identify and use natural numbers']),
]);
$by = SyllabusPlan::build($state, array_slice($edited['rows'], 0, 4));
$ops = array_column($by['ops'], null, null);
check($by['report']['skills']['update'] === 2 && $by['report']['skills']['create'] === 1 && $by['report']['skills']['unchanged'] === 1, 'rename, new notes and a new skill: ' . json_encode($by['report']['skills']));
$kinds = array_count_values(array_column($by['ops'], 'op'));
check(($kinds['skill_update'] ?? 0) === 2 && ($kinds['skill'] ?? 0) === 1 && !isset($kinds['content']) && !isset($kinds['group']), 'only skill operations are planned for an edit');
$renamed = array_values(array_filter($by['ops'], static function ($op) { return $op['op'] === 'skill_update' && isset($op['fields']['name']); }));
check(count($renamed) === 2 && $renamed[1]['fields']['name'] === 'IDENTIFY prime numbers', 'a name that differs only in case or spacing is still taken from the file');
$two = SyllabusPlan::build($state, [$edited['rows'][3], $edited['rows'][4]]);
check($two['report']['skills']['move'] === 1 && array_values(array_filter($two['ops'], static function ($op) { return $op['op'] === 'skill_move'; }))[0]['term'] > 0, 'a skill code that now sits in another group moves the skill instead of creating a duplicate');
check($two['report']['skills']['create'] === 1 && $two['report']['groups']['create'] === 0, 'moving a skill reuses the existing group');

// Matching by name when there is no code, and by code when both sides have one.
$named = SyllabusPlan::build($state, SyllabusRows::normalize([$row(['content' => '1 NUMBER', 'group' => 'types  of NUMBER', 'skill' => 'identify natural numbers'])])['rows']);
check($named['report']['skills']['create'] === 0 && $named['report']['groups']['create'] === 0 && $named['report']['contents']['create'] === 0, 'names match ignoring case and spacing when no code is given');
check(count(array_filter($named['ops'], static function ($op) { return $op['op'] === 'skill_update'; })) === 1, 'a matched skill whose text differs only in case is updated to the file text');
$codes = SyllabusPlan::build($state, SyllabusRows::normalize([$row(['content' => '1 Number', 'group_code' => 'C9.9', 'group' => 'Types of number'])])['rows']);
check($codes['report']['groups']['create'] === 1, 'two groups with the same name but different codes are different groups');

// Repeated rows and conflicts.
$dup = SyllabusPlan::build($empty, SyllabusRows::normalize([
    $row(['content' => 'A', 'group_code' => 'G1', 'skill_code' => 'S1', 'skill' => 'One'], 2),
    $row(['content' => 'A', 'group_code' => 'G1', 'skill_code' => 'S1', 'skill' => 'One'], 3),
    $row(['content' => 'A', 'group_code' => 'G2', 'skill_code' => 'S1', 'skill' => 'One'], 4),
])['rows']);
check(count($dup['report']['warnings']) === 1 && $dup['report']['warnings'][0]['line'] === 3, 'an identical repeated row is ignored with a warning');
check(!$dup['report']['valid'] && $dup['report']['errors'][0]['line'] === 4, 'the same skill code under two groups is an error on its line');

// Skills with no group land in a default group, once, with one warning per content.
$loose = SyllabusPlan::build($empty, SyllabusRows::normalize([
    $row(['content' => 'A', 'skill' => 'One']), $row(['content' => 'A', 'skill' => 'Two']), $row(['content' => 'B', 'skill' => 'Three']),
])['rows'], ['default_group' => 'General']);
check($loose['report']['groups']['create'] === 2 && $loose['report']['skills']['create'] === 3 && count($loose['report']['warnings']) === 2, 'ungrouped skills share one default group per content');

// Limits.
$limit = SyllabusPlan::build($empty, SyllabusRows::normalize([$row(['group' => 'A', 'skill' => 'x']), $row(['group' => 'B', 'skill' => 'y']), $row(['group' => 'C', 'skill' => 'z'])])['rows'], ['max_groups' => 2, 'max_skills' => 2]);
check(!$limit['report']['valid'] && count($limit['report']['errors']) === 2, 'group and skill limits are enforced');
// Content nesting.
$nested = SyllabusPlan::build($empty, SyllabusRows::normalize([$row(['content' => 'Paper 1 > Number', 'group' => 'G', 'skill' => 's']), $row(['content' => 'Paper 1 > Algebra', 'group' => 'G', 'skill' => 's2'])])['rows']);
$contents = array_values(array_filter($nested['ops'], static function ($op) { return $op['op'] === 'content'; }));
check(count($contents) === 3 && $contents[1]['parent'] === $contents[0]['ref'] && $contents[2]['parent'] === $contents[0]['ref'], 'nested content shares its parent');

// ---- A syllabus is also a course: how its skill groups and skills are mirrored --------------------------
check(SyllabusCourse::chapter_title(['name' => 'Types of number', 'code' => 'C1.1']) === 'C1.1 · Types of number', 'a chapter is titled with its group code and name');
check(SyllabusCourse::chapter_title(['name' => 'М11.1А', 'code' => 'М11.1А']) === 'М11.1А' && SyllabusCourse::chapter_title(['name' => 'Sets', 'code' => '']) === 'Sets', 'a code that is the name, or no code, leaves just the name');
check(mb_strlen(SyllabusCourse::chapter_title(['name' => str_repeat('Ө', 190), 'code' => str_repeat('К', 40)])) === 200, 'a chapter title is cut at the course chapter limit');

$groups = [
    ['uuid' => 'g1', 'name' => 'Types of number', 'code' => 'C1.1', 'skills' => [['term_id' => 1], ['term_id' => 5]]],
    ['uuid' => 'g2', 'name' => 'Sets', 'code' => '', 'skills' => [['term_id' => 4]]],
    ['uuid' => 'g3', 'name' => 'Powers', 'code' => '', 'skills' => []],
];
$chapters = [
    ['id' => 10, 'name' => 'C1.1 · Types of number', 'group' => 'g1'],
    ['id' => 11, 'name' => 'Old name', 'group' => 'g2'],
    ['id' => 12, 'name' => 'Added by hand in the catalog', 'group' => ''],
    ['id' => 13, 'name' => 'Group since deleted', 'group' => 'gone'],
];
$plan = SyllabusCourse::plan_chapters($chapters, $groups);
check($plan['keep'] === ['g1' => 10, 'g2' => 11] && $plan['rename'] === [11 => 'Sets'] && $plan['create'] === ['g3' => 'Powers'], 'chapters are kept, renamed or created to match the groups');
check($plan['orphans'] === [13], 'a chapter whose group is gone is an orphan, and a chapter made by hand is never touched');
check(SyllabusCourse::plan_chapters([], [])['create'] === [] && SyllabusCourse::plan_chapters($chapters, $groups) === $plan, 'planning is repeatable');

$outcomes = [
    ['term_id' => 1, 'target' => 'mastered', 'required' => true, 'chapter_id' => 10],
    ['term_id' => 2, 'target' => 'proficient', 'required' => false, 'chapter_id' => 13],
    ['term_id' => 3, 'target' => 'proficient', 'required' => true, 'chapter_id' => 0],
    ['term_id' => 4, 'target' => 'mastered', 'required' => false, 'chapter_id' => 0],
];
$next = SyllabusCourse::plan_outcomes($outcomes, $groups, ['g1' => 10, 'g2' => 11, 'g3' => 14], [10, 11, 13]);
check(array_column($next, 'term_id') === [3, 1, 5, 4], 'outcomes the syllabus does not manage stay first, then the skills follow in group order');
check($next[1] === ['term_id' => 1, 'target' => 'mastered', 'required' => true, 'chapter_id' => 10], 'a skill that is already an outcome keeps its target and required flag');
check($next[2] === ['term_id' => 5, 'target' => 'proficient', 'required' => false, 'chapter_id' => 10], 'a new skill starts at Proficient and not required, in its group\'s chapter');
check($next[3] === ['term_id' => 4, 'target' => 'mastered', 'required' => false, 'chapter_id' => 11], 'a skill added to the course by hand is taken over by the group it now sits in');
check(!in_array(2, array_column($next, 'term_id'), true), 'a skill removed from the syllabus leaves the course');
check(SyllabusCourse::same_outcomes($next, SyllabusCourse::plan_outcomes($next, $groups, ['g1' => 10, 'g2' => 11, 'g3' => 14], [10, 11])), 'mirroring twice changes nothing');
check(!SyllabusCourse::same_outcomes($outcomes, $next), 'a difference is noticed');
check(SyllabusCourse::chapter_order([12, 11, 10, 14], [10, 11, 14]) === [10, 11, 14, 12] && SyllabusCourse::chapter_order([12], []) === [12], 'mirrored chapters follow the groups, then the others in their old order');

// Which skills the course requires: only requirement and target change, in place.
$required = SyllabusCourse::plan_requirements($next, [
    ['term_id' => 5, 'required' => true],
    ['term_id' => 1, 'target' => 'proficient'],
    ['term_id' => 4, 'target' => 'bogus', 'required' => true],
    ['term_id' => 999, 'required' => true],
    'not a change',
]);
check(array_column($required, 'term_id') === [3, 1, 5, 4] && array_column($required, 'chapter_id') === array_column($next, 'chapter_id'), 'requirement changes keep the skills, their order and their chapters');
check($required[2]['required'] === true && $required[2]['target'] === 'proficient', 'a skill can be made required without touching its target');
check($required[1]['target'] === 'proficient' && $required[1]['required'] === true, 'a target can change without touching the required flag');
check($required[3]['target'] === 'mastered' && $required[3]['required'] === true, 'an unknown target is ignored');
check(SyllabusCourse::plan_requirements($next, []) === $next && SyllabusCourse::plan_requirements($next, [['term_id' => 1, 'required' => false]])[1]['required'] === false, 'no change changes nothing, and a skill can be made optional again');

$categorized = SyllabusRows::normalize([['group' => 'Core', 'skill' => 'Natural numbers', 'skill_code' => 'C1.1', 'category' => 'Core']]);
$category_plan = SyllabusPlan::build(['root' => 1], $categorized['rows']);
$category_skills = array_values(array_filter($category_plan['ops'], static function ($op) { return $op['op'] === 'skill'; }));
check($category_skills[0]['category'] === 'Core', 'skill category survives cleaning and import planning');
$category_existing = ['root' => 1, 'groups' => [['id' => 2, 'item_id' => 1, 'name' => 'Core', 'code' => '', 'skills' => [['term_id' => 3, 'name' => 'Natural numbers', 'code' => 'C1.1', 'description' => '', 'category' => 'Core']]]]];
check(SyllabusPlan::build($category_existing, $categorized['rows'])['ops'] === [], 'reimporting the same skill category changes nothing');
$categorized['rows'][0]['category'] = 'Advanced';
$category_change = SyllabusPlan::build($category_existing, $categorized['rows']);
check($category_change['ops'][0]['fields']['category'] === 'Advanced', 'category can change without moving or duplicating a skill');
check(count(SyllabusRows::normalize([['skill' => 'Skill', 'category' => str_repeat('x', 61)]])['errors']) === 1, 'overlong categories fail import validation');
echo "syllabus unit checks passed: $checks\n";
