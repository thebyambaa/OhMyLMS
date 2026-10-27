<?php
/** Pure unit checks for curriculum hierarchy rules, evidence pooling and skill classification (no WordPress database). */
define('ABSPATH', __DIR__ . '/');
define('DAY_IN_SECONDS', 86400);
function __($text) { return $text; }
function get_option() { return false; }
require dirname(__DIR__, 2) . '/vendor/autoload.php';
use OhMyLMS\Curriculum\Tree;
use OhMyLMS\Skills\Mastery;
use OhMyLMS\Bundles\Combined;
use OhMyLMS\Bundles\Progress;
$checks = 0;
function check($condition, $message) { global $checks; if (!$condition) { throw new RuntimeException($message); } $checks++; }

// Hierarchy: 1 > 2 > 3 > 4, 1 > 5, and a separate root 6 > 7.
$parents = [1 => 0, 2 => 1, 3 => 2, 4 => 3, 5 => 1, 6 => 0, 7 => 6];
check(Tree::ancestors($parents, 4) === [3, 2, 1], 'ancestors nearest first');
check(Tree::ancestors($parents, 1) === [], 'a root has no ancestors');
check(Tree::depth($parents, 4) === 4 && Tree::depth($parents, 1) === 1 && Tree::depth($parents, 7) === 2, 'depth counts a root as 1');
check(Tree::descendants($parents, 1) === [2, 5, 3, 4], 'descendants breadth first');
check(Tree::descendants($parents, 4) === [] && Tree::descendants($parents, 6) === [7], 'leaf and small branch descendants');
check(Tree::height($parents, 1) === 4 && Tree::height($parents, 5) === 1 && Tree::height($parents, 6) === 2, 'subtree height');
check(Tree::would_cycle($parents, 1, 4) && Tree::would_cycle($parents, 2, 3) && Tree::would_cycle($parents, 3, 3), 'moving under itself or a descendant is a cycle');
check(!Tree::would_cycle($parents, 4, 0) && !Tree::would_cycle($parents, 5, 6) && !Tree::would_cycle($parents, 2, 5), 'legal moves are not cycles');
check(!Tree::would_cycle($parents, 1, 0), 'moving to the top level is never a cycle');
// Corrupted data never loops forever and never reports an item as its own ancestor.
$loop = [1 => 2, 2 => 3, 3 => 1, 4 => 99];
check(Tree::ancestors($loop, 1) === [2, 3] && !in_array(1, Tree::ancestors($loop, 1), true), 'ancestor walk stops at a loop');
check(count(Tree::descendants($loop, 1)) === 2 && Tree::height($loop, 1) >= 1, 'descendant walk stops at a loop');
check(Tree::ancestors($loop, 4) === [] && Tree::depth($loop, 4) === 1, 'a missing parent ends the walk');
// Sibling placement.
check(Tree::insert_at([10, 20, 30], 99) === [10, 20, 30, 99], 'null position appends');
check(Tree::insert_at([10, 20, 30], 99, 1) === [10, 99, 20, 30], 'insert at an index');
check(Tree::insert_at([10, 20, 30], 99, 100) === [10, 20, 30, 99] && Tree::insert_at([10, 20, 30], 99, -5) === [99, 10, 20, 30], 'positions clamp');
check(Tree::insert_at([10, 20, 30], 20, 0) === [20, 10, 30] && Tree::insert_at([10, 20, 30], 10, 2) === [20, 30, 10], 'moving within a list');
check(Tree::positions([5, 6, 7]) === [5 => 0, 6 => 1, 7 => 2], 'dense positions');

// Evidence pooling: a graded part counts once; repeat answers are never independent first tries.
$row = static function ($event, $term, $correct, $question, $family, $minutes, $part = 'p1', $first = 1, $id = 0) {
    return ['id' => $id ?: $event * 10 + $term, 'grade_event_id' => $event, 'term_id' => $term, 'part_id' => $part, 'question_id' => $question, 'awarded' => $correct ? 1 : 0, 'available' => 1, 'independent' => 1, 'first_try' => $first, 'family_id' => $family, 'evidence_at' => gmdate('Y-m-d H:i:s', 1700000000 + $minutes * 60)];
};
$rules = Mastery::rules();
$now = 1700000000 + 86400;
$a = [$row(1, 10, true, 101, 'f1', 0), $row(2, 10, true, 102, 'f2', 1)];
$b_duplicate = [$row(1, 20, true, 101, 'f1', 0)];
$merged = Combined::merge(array_merge($a, $b_duplicate));
check(count($merged) === 2, 'the same event and part collapses to one answer');
check(Combined::evaluate(array_merge($a, $b_duplicate), $rules, $now)['level'] === 'familiar', 'a duplicate must not lift two answers to proficient');
check(Combined::evaluate(array_merge($a, $b_duplicate, [$row(3, 20, true, 103, 'f3', 2)]), $rules, $now)['level'] === 'proficient', 'three distinct correct answers from several families are proficient');
check(Combined::evaluate([], $rules, $now)['level'] === 'not-started', 'no evidence is not started');
check(count(Combined::merge([$row(1, 10, true, 101, 'f1', 0, 'p1'), $row(1, 10, true, 101, 'f1', 0, 'p2')])) === 2, 'different parts of one event are different evidence');
// A question answered again in another event is kept but is no longer a first try.
$again = Combined::merge([$row(2, 20, true, 101, 'f1', 10), $row(1, 10, true, 101, 'f1', 0)]);
check(count($again) === 2 && $again[0]['grade_event_id'] === 1 && $again[0]['first_try'] === 1 && $again[1]['first_try'] === 0, 'a repeated question is not an independent first try');
check(Combined::evaluate([$row(1, 10, true, 101, 'f1', 0), $row(2, 20, true, 101, 'f1', 5), $row(3, 20, true, 101, 'f1', 6)], $rules, $now)['independent_correct'] === 1, 'repeats of one question add no independent evidence');
// Ordering is by time, then ID, whatever order the rows arrive in.
$ordered = Combined::merge([$row(3, 10, true, 103, 'f3', 5), $row(1, 10, true, 101, 'f1', 0), $row(2, 10, true, 102, 'f2', 5, 'p1', 1, 7)]);
check(array_column($ordered, 'grade_event_id') === [1, 2, 3], 'rows ordered by time, then by ID');
$input = [$row(1, 10, true, 101, 'f1', 0), $row(1, 20, true, 101, 'f1', 0)];
$copy = $input; Combined::merge($input);
check($input === $copy, 'merging does not change its input');

// Skill classification: strengths, gaps, in progress, and "Not assessed" is never a gap.
check(Progress::classify('mastered', false) === 'strength' && Progress::classify('proficient', true) === 'strength', 'proficient and mastered are strengths');
check(Progress::classify('familiar', true) === 'gap' && Progress::classify('attempted', false) === 'developing', 'developing with recent errors is a gap');
check(Progress::classify('not-started', false) === 'not-assessed' && Progress::classify('not-started', true) === 'not-assessed', 'unassessed skills are never gaps');
check(Progress::class_label('not-assessed') === 'Not assessed' && Progress::class_label('gap') === 'Needs practice', 'labels');
check(Progress::mode_label('skill-based') === 'Skill-based' && Progress::mode_label('blended') === 'Blended' && Progress::mode_label('traditional') === 'Traditional', 'learning modes keep their names');
$entries = [['classification' => 'strength'], ['classification' => 'gap'], ['classification' => 'gap'], ['classification' => 'developing'], ['classification' => 'not-assessed'], ['classification' => 'not-assessed']];
check(Progress::summarize($entries) === ['total' => 6, 'strengths' => 1, 'gaps' => 2, 'developing' => 1, 'not_assessed' => 2], 'summary counts each class once');
check(Progress::summarize([]) === ['total' => 0, 'strengths' => 0, 'gaps' => 0, 'developing' => 0, 'not_assessed' => 0], 'empty summary');
echo "$checks curriculum unit checks passed.\n";
