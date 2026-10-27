<?php
/** Pure unit checks for assessment logic (no WordPress database). */
define('ABSPATH', __DIR__ . '/');
define('DAY_IN_SECONDS', 86400);
function __($text) { return $text; }
function get_option() { return false; }
require dirname(__DIR__) . '/vendor/autoload.php';
use OhMyLMS\Assessment\NumericAnswer as N;
use OhMyLMS\Assessment\Structured as S;
use OhMyLMS\Assessment\Scoring;
use OhMyLMS\Skills\Mastery;
$checks = 0;
function check($condition, $message) { global $checks; if (!$condition) { throw new RuntimeException($message); } $checks++; }

// Parsing: accepted forms and rejected input.
foreach (['3' => 3.0, '-2.50' => -2.5, '.5' => 0.5, '2,5' => 2.5, '1.2e3' => 1200.0, '3/4' => 0.75, '-1/2' => -0.5, '1 1/2' => 1.5, ' 7 ' => 7.0, "\u{2212}4" => -4.0] as $input => $expected) {
    check(abs(N::parse($input) - $expected) < 1e-12, "parse $input");
}
foreach (['', '   ', 'abc', '1,000.5', '1/0', '2x', '1e999', '3/4/5', 'NaN', 'INF', '0x1A', str_repeat('9', 70)] as $input) {
    check(N::parse($input) === null, "reject '$input'");
}
check(N::parse('3/4', false) === null, 'fractions can be disabled');
check(N::parse(['4.5']) === 4.5, 'array input');
// Tolerance.
check(N::matches(0.30000000000000004, 0.3), 'float noise absorbed');
check(!N::matches(0.31, 0.3), 'exact means exact');
check(N::matches(9.8, 10, 0.2) && !N::matches(9.7, 10, 0.2), 'absolute tolerance');
check(N::matches(103, 100, 0.05, 'relative') && !N::matches(106, 100, 0.05, 'relative'), 'relative tolerance');
check(!N::matches(null, 3) && !N::matches(3, 'x'), 'invalid values never match');
$grade = N::grade(['2/3'], ['answer' => 0.6667, 'tolerance' => 0.001]);
check($grade['correct'] && $grade['fraction'] === 1.0, 'fraction answer within tolerance');
check(!N::grade(['banana'], ['answer' => 1])['valid'], 'invalid input flagged, graded wrong');
check(N::grade(['-3'], ['answers' => [3, -3]])['correct'], 'alternative answers');

// Structured grading and weights.
$question = new class {
    public function get_settings() { return ['parts' => [
        ['id' => 'a', 'kind' => 'numerical', 'marks' => 2, 'answer' => 4, 'tolerance' => 0],
        ['id' => 'b', 'kind' => 'text', 'marks' => 1, 'accepted' => ['x = 2', 'x=2']],
        ['id' => 'c', 'kind' => 'written', 'marks' => 3],
    ]]; }
};
$weights = S::weights($question->get_settings());
check(abs($weights['a'] - 2 / 6) < 1e-12 && abs(array_sum($weights) - 1) < 1e-12, 'part weights follow marks');
$result = S::grade(['a' => '4', 'b' => '  X = 2 ', 'c' => 'Because...'], $question);
check($result['manual'] && $result['parts']['a'] === 1.0 && $result['parts']['b'] === 1.0 && $result['parts']['c'] === null && abs($result['fraction'] - 0.5) < 1e-12, 'structured auto parts + pending written');
$result = S::grade(['a' => '5', 'b' => 'x=2'], $question);
check(!$result['manual'] && $result['parts']['c'] === 0.0 && abs($result['fraction'] - 1 / 6) < 1e-12 && !$result['correct'], 'blank written part scores zero without review');
check(S::validate_settings(['parts' => [['id' => 'a', 'kind' => 'numerical', 'answer' => 'x']]]) !== true, 'non-numeric answer rejected');
check(S::validate_settings(['parts' => [['id' => 'a', 'kind' => 'text'], ['id' => 'a', 'kind' => 'written']]]) !== true, 'duplicate part IDs rejected');
check(S::validate_settings($question->get_settings()) === true, 'valid parts accepted');
$public = S::public_parts($question->get_settings());
check(!isset($public[0]['answer']) && !isset($public[1]['accepted']) && $public[2]['kind'] === 'written', 'learner view strips keys');

// Scoring policies.
check(Scoring::award(3, 0.5, Scoring::LEGACY) === 2.0 && Scoring::award(3, 0.5, Scoring::DECIMAL) === 1.5, 'legacy rounds, decimal keeps halves');
check(Scoring::award(2, 1.7, Scoring::DECIMAL) === 2.0 && Scoring::award(2, -1, Scoring::DECIMAL) === 0.0, 'fractions clamped');
check(Scoring::display(4.25) === '4.25' && Scoring::display(5.0) === '5', 'display trims zeros');

// Mastery rules: transparent thresholds.
$rules = ['window' => 8, 'proficient_min' => 3, 'proficient_families' => 2, 'proficient_accuracy' => 0.8, 'mastered_min' => 5, 'mastered_families' => 3, 'mastered_accuracy' => 0.85, 'review_gap_hours' => 24, 'review_after_days' => 14];
$row = function ($ok, $family, $at, $independent = 1, $first = 1) { return ['awarded' => $ok ? 1 : 0, 'available' => 1, 'independent' => $independent, 'first_try' => $first, 'family_id' => $family, 'question_id' => crc32($family), 'evidence_at' => gmdate('Y-m-d H:i:s', $at)]; };
$now = 2000000000;
check(Mastery::evaluate([], $rules, $now)['level'] === 'not-started', 'no evidence');
check(Mastery::evaluate([$row(0, 'a', $now)], $rules, $now)['level'] === 'attempted', 'any evidence is attempted');
$same_family = [$row(1, 'a', $now - 30), $row(1, 'a', $now - 20), $row(1, 'a', $now - 10)];
check(Mastery::evaluate($same_family, $rules, $now)['level'] === 'familiar', 'one family is familiar, not proficient');
$retries = [$row(1, 'a', $now - 30, 1, 0), $row(1, 'b', $now - 20, 1, 0), $row(1, 'c', $now - 10, 1, 0)];
check(Mastery::evaluate($retries, $rules, $now)['level'] === 'attempted', 'repeats do not count');
$mixed = [$row(1, 'a', $now - 50), $row(0, 'b', $now - 40), $row(1, 'c', $now - 30), $row(1, 'd', $now - 20), $row(0, 'e', $now - 10)];
check(Mastery::evaluate($mixed, $rules, $now)['level'] === 'familiar', '60% accuracy is familiar, not proficient');
// Mongolian (Cyrillic) answers: case and spacing ignored, letters preserved.
check(S::normalize_text('  Х = 2 ') === S::normalize_text('х=2'), 'Cyrillic case-insensitive match');
check(S::normalize_text('Өндөр') !== S::normalize_text('Ондор'), 'Cyrillic letters not folded');

// Interactive types: dropdown sentence, sorting, multi-blank / table, tile expression.
use OhMyLMS\Assessment\Interactive as I;
$dropdown = ['text' => 'The slope is {1}, so the line {2}.', 'slots' => [
    ['id' => '1', 'choices' => ['positive', 'negative'], 'answer' => 'positive'],
    ['id' => '2', 'choices' => ['rises', 'falls'], 'answer' => 'rises'],
]];
check(I::validate_settings('dropdown-blanks', $dropdown) === true, 'dropdown settings valid');
check(I::grade('dropdown-blanks', ['1' => 'positive', '2' => 'rises'], $dropdown)['correct'], 'dropdown all correct');
check(!I::grade('dropdown-blanks', ['1' => 'positive', '2' => 'falls'], $dropdown)['correct'], 'dropdown one wrong is wrong');
check(I::grade('dropdown-blanks', ['1' => 'positive', '2' => 'falls'], $dropdown)['fraction'] === 0.0, 'whole-question grading by default');
check(abs(I::grade('dropdown-blanks', ['1' => 'positive', '2' => 'falls'], $dropdown + ['partial_credit' => true])['fraction'] - 0.5) < 1e-12, 'partial credit when enabled');
check(!I::grade('dropdown-blanks', ['1' => 'zigzag', '2' => 'rises'], $dropdown)['correct'], 'a value outside the choices is rejected');
check(!I::grade('dropdown-blanks', [], $dropdown)['correct'], 'empty answer is wrong, not an error');
check(I::validate_settings('dropdown-blanks', ['text' => 'No markers', 'slots' => []]) !== true, 'sentence needs markers');
$bad = $dropdown; $bad['slots'][0]['answer'] = 'sideways';
check(I::validate_settings('dropdown-blanks', $bad) !== true, 'answer must be a choice');
$bad = $dropdown; $bad['slots'][0]['choices'] = ['same', 'Same'];
check(I::validate_settings('dropdown-blanks', $bad) !== true, 'duplicate choices rejected');
$view = I::public_view('dropdown-blanks', $dropdown, 'seed');
check(!isset($view['slots'][0]['answer']) && count($view['slots'][0]['choices']) === 2, 'dropdown view strips the key');
check(strpos(json_encode($view), 'answer') === false, 'no answer anywhere in the dropdown view');

$sort = ['buckets' => [['id' => 'b1', 'label' => 'Polygon'], ['id' => 'b2', 'label' => 'Not polygon']],
    'items' => [['id' => 'i1', 'text' => 'Triangle'], ['id' => 'i2', 'text' => 'Circle'], ['id' => 'i3', 'text' => 'Square']],
    'key' => ['i1' => 'b1', 'i2' => 'b2', 'i3' => 'b1']];
check(I::validate_settings('categorize', $sort) === true, 'categorize settings valid');
check(I::grade('categorize', ['i1' => 'b1', 'i2' => 'b2', 'i3' => 'b1'], $sort)['correct'], 'categorize correct');
$wrong = I::grade('categorize', ['i1' => 'b1', 'i2' => 'b1', 'i3' => 'b1'], $sort);
check(!$wrong['correct'] && $wrong['items']['i2'] === false && $wrong['items']['i1'] === true, 'categorize reports which item is wrong');
check(!I::grade('categorize', ['i1' => 'b1'], $sort)['correct'], 'unplaced items are wrong');
$bad = $sort; unset($bad['key']['i3']);
check(I::validate_settings('categorize', $bad) !== true, 'every item needs a group');
$view = I::public_view('categorize', $sort, 'seed');
check(!isset($view['key']) && count($view['items']) === 3 && count($view['buckets']) === 2, 'categorize view has no key');
check(I::public_view('categorize', $sort, 'seed') === $view, 'order is stable for a seed');

$blanks = ['layout' => 'inline', 'text' => '3/4 + 1/8 = {a} and the unit is {u}', 'blanks' => [
    'a' => ['kind' => 'numerical', 'answer' => 0.875, 'tolerance' => 0.001],
    'u' => ['kind' => 'text', 'accepted' => ['metre', 'm']],
]];
check(I::validate_settings('multi-blank', $blanks) === true, 'multi-blank valid');
check(I::grade('multi-blank', ['a' => '7/8', 'u' => ' M '], $blanks)['correct'], 'equivalent number and spaced, cased text accepted');
$result = I::grade('multi-blank', ['a' => '0.9', 'u' => 'm'], $blanks);
check(!$result['correct'] && $result['items'] === ['a' => false, 'u' => true], 'multi-blank marks each box');
check(!I::grade('multi-blank', ['a' => '', 'u' => 'm'], $blanks)['correct'], 'empty box is wrong');
$table = ['layout' => 'table', 'columns' => ['x', 'y'], 'rows' => [['1', '{a}'], ['2', '{b}']], 'blanks' => [
    'a' => ['kind' => 'numerical', 'answer' => 3], 'b' => ['kind' => 'numerical', 'answer' => 5]]];
check(I::blank_ids($table) === ['a', 'b'] && I::validate_settings('multi-blank', $table) === true, 'table blanks found in cells');
check(I::grade('multi-blank', ['a' => '3', 'b' => '5'], $table)['correct'], 'table completed');
$view = I::public_view('multi-blank', $table, 'seed');
check($view['fields']['a']['kind'] === 'numerical' && strpos(json_encode($view), '"answer"') === false && !isset($view['blanks']), 'table view carries kinds, not answers');
$bad = $blanks; $bad['blanks']['a'] = ['kind' => 'numerical', 'answer' => 'x'];
check(I::validate_settings('multi-blank', $bad) !== true, 'non-numeric expected value rejected');

$tiles = ['correct' => ['3', 'x', '+', '4'], 'distractors' => ['-', '5'], 'alternatives' => [['4', '+', '3', 'x']]];
check(I::validate_settings('build-expression', $tiles) === true, 'tiles valid');
check(I::grade('build-expression', ['3', 'x', '+', '4'], $tiles)['correct'], 'tiles in the right order');
check(I::grade('build-expression', ['4', '+', '3', 'x'], $tiles)['correct'], 'alternative order accepted');
check(!I::grade('build-expression', ['3', 'x', '4', '+'], $tiles)['correct'], 'wrong order rejected');
check(!I::grade('build-expression', ['3', 'x', '+'], $tiles)['correct'] && !I::grade('build-expression', [], $tiles)['correct'], 'incomplete or empty is wrong');
$view = I::public_view('build-expression', $tiles, 'seed');
check(count($view['tiles']) === 6 && !isset($view['correct']) && !isset($view['alternatives']), 'tile view shows the bank only');
$bad = $tiles; $bad['alternatives'] = [['9']];
check(I::validate_settings('build-expression', $bad) !== true, 'alternative may only use bank tiles');
$safe = I::learner_settings('build-expression', ['id' => 5, 'settings' => $tiles]);
check(!isset($safe['correct']) && !isset($safe['alternatives']) && !isset($safe['distractors']) && count($safe['tiles']) === 6, 'raw tile settings are reduced by the template helper');
$safe = I::learner_settings('categorize', ['id' => 5, 'settings' => $sort]);
check(!isset($safe['key']) && isset($safe['items']), 'template helper strips raw categorize key');
check(I::expected('categorize', $sort)[1] === 'Circle → Not polygon', 'readable expected answer');

// CAS-backed types: typed expressions, tile equivalence, expression blanks.
$expr = ['answer' => '2(x+3)', 'form' => 'any', 'alternatives' => []];
check(I::validate_settings('expression', $expr) === true, 'expression settings valid');
check(I::grade('expression', ['2x+6'], $expr)['correct'], 'equivalent expression accepted');
check(I::grade('expression', ['answer' => '\frac{2x}{1}+6'], $expr)['correct'], 'keyed answer and LaTeX accepted');
check(!I::grade('expression', ['2x+5'], $expr)['correct'] && I::grade('expression', ['2x+5'], $expr)['reason'] === 'different', 'different expression rejected');
check(I::grade('expression', ['2x+'], $expr)['reason'] === 'parse' && !I::grade('expression', [], $expr)['correct'], 'unparseable or empty is wrong, not an error');
check(I::grade('expression', ['x=2'], $expr + [])['correct'] === false, 'an equation is not an expression');
$factored = ['answer' => '2x+6', 'form' => 'factored'];
check(I::grade('expression', ['2(x+3)'], $factored)['correct'] && I::grade('expression', ['2x+6'], $factored)['reason'] === 'form', 'form is enforced');
check(I::grade('expression', ['x=-2'], ['answer' => 'x=2', 'alternatives' => ['x=-2']])['correct'], 'other accepted answers');
check(I::validate_settings('expression', ['answer' => '2(x+']) !== true && I::validate_settings('expression', array_merge($expr, ['form' => 'weird'])) !== true, 'invalid key or form rejected');
check(I::validate_settings('expression', array_merge($expr, ['alternatives' => ['2(']])) !== true, 'invalid alternative rejected');
check(I::public_view('expression', $factored, 's') === ['form' => 'factored'] , 'expression view exposes only the form');
$safe = I::learner_settings('expression', ['id' => 3, 'settings' => $factored]);
check(!isset($safe['answer']) && $safe['form'] === 'factored', 'template helper strips the expression key');
check(I::expected('expression', $expr) === ['2(x+3)'], 'expected expression');

$tiles_eq = ['correct' => ['3', 'x', '+', '4'], 'distractors' => ['-'], 'equivalence' => true, 'form' => 'any'];
check(I::validate_settings('build-expression', $tiles_eq) === true, 'tile equivalence settings valid');
check(I::grade('build-expression', ['4', '+', '3', 'x'], $tiles_eq)['correct'], 'equivalent tile order accepted');
check(!I::grade('build-expression', ['4', '+', '3', 'x'], array_merge($tiles_eq, ['equivalence' => false]))['correct'], 'without equivalence only listed orders count');
check(!I::grade('build-expression', ['3', 'x', '-', '4'], $tiles_eq)['correct'], 'a different expression is still wrong');
check(!I::grade('build-expression', ['3', '+', '+'], $tiles_eq)['correct'], 'nonsense tiles are wrong, not an error');
check(!I::grade('build-expression', ['4', '+', '3', 'x'], array_merge($tiles_eq, ['form' => 'factored']))['correct'], 'tile form is enforced');
check(I::validate_settings('build-expression', ['correct' => ['3', '+', '+'], 'equivalence' => true]) !== true, 'equivalence needs a valid expression');
$mb = ['layout' => 'inline', 'text' => 'Expand: {a}', 'blanks' => ['a' => ['kind' => 'expression', 'answer' => 'x^2+2x+1', 'form' => 'expanded']]];
check(I::validate_settings('multi-blank', $mb) === true, 'expression blank valid');
check(I::grade('multi-blank', ['a' => 'x*x+2x+1'], $mb)['correct'] && !I::grade('multi-blank', ['a' => '(x+1)^2'], $mb)['correct'], 'expression blank checks value and form');
check(I::public_view('multi-blank', $mb, 's')['fields']['a']['kind'] === 'expression' && strpos(json_encode(I::public_view('multi-blank', $mb, 's')), 'x^2') === false, 'expression blank view hides the key');
check(I::validate_settings('multi-blank', ['text' => '{a}', 'blanks' => ['a' => ['kind' => 'expression', 'answer' => '(']]]) !== true, 'invalid expression blank rejected');

// Visual types: number line, shade, blocks, clock, money, jug, chart, grid.
$nl = ['min' => 0, 'max' => 10, 'step' => 1, 'target' => 3.5, 'tolerance' => 0.5];
check(I::validate_settings('number-line', $nl) === true, 'number line valid');
check(I::grade('number-line', ['value' => '3.5'], $nl)['correct'] && I::grade('number-line', ['value' => '4'], $nl)['correct'] && !I::grade('number-line', ['value' => '5'], $nl)['correct'], 'number line tolerance');
check(I::grade('number-line', ['value' => '7/2'], $nl)['correct'], 'number line accepts a fraction');
check(!I::grade('number-line', [], $nl)['correct'] && !I::grade('number-line', ['value' => 'abc'], $nl)['correct'], 'number line empty or junk is wrong');
check(I::grade('number-line', ['value' => '4'], ['min' => 0, 'max' => 10, 'step' => 1, 'target' => 4])['correct'] && !I::grade('number-line', ['value' => '4.6'], ['min' => 0, 'max' => 10, 'step' => 1, 'target' => 4])['correct'], 'default tolerance is half a tick');
check(I::validate_settings('number-line', ['min' => 5, 'max' => 5, 'step' => 1, 'target' => 5]) !== true && I::validate_settings('number-line', array_merge($nl, ['target' => 11])) !== true && I::validate_settings('number-line', array_merge($nl, ['step' => 0.001])) !== true, 'number line bad ranges rejected');
$v = I::public_view('number-line', $nl, 's');
check(!isset($v['target']) && !isset($v['tolerance']) && $v['snap'] === 1.0, 'number line view hides the key');

$sh = ['shape' => 'circle', 'parts' => 8, 'answer' => 3];
check(I::validate_settings('shade-model', $sh) === true && I::validate_settings('shade-model', array_merge($sh, ['answer' => 9])) !== true, 'shade validation');
check(I::grade('shade-model', ['c0' => '1', 'c4' => '1', 'c7' => '1'], $sh)['correct'], 'any three cells');
check(!I::grade('shade-model', ['c0' => '1', 'c1' => '1'], $sh)['correct'] && !I::grade('shade-model', ['c0' => '1', 'c1' => '1', 'c2' => '1', 'c3' => '1'], $sh)['correct'], 'wrong count rejected');
check(!I::grade('shade-model', ['c0' => '1', 'c1' => '1', 'c99' => '1'], $sh)['correct'] && I::grade('shade-model', ['c0' => '1', 'c1' => '1', 'c2' => '1', 'c3' => '0'], $sh)['correct'], 'out of range cells ignored, zeros are unshaded');
check(I::grade('shade-model', [], ['shape' => 'bar', 'parts' => 4, 'answer' => 0])['correct'], 'shading nothing can be the answer');
check(!isset(I::public_view('shade-model', $sh, 's')['answer']), 'shade view hides the key');

$cb = ['places' => ['hundreds', 'tens', 'ones'], 'target' => 243, 'max_per_place' => 30];
check(I::validate_settings('count-blocks', $cb) === true && I::validate_settings('count-blocks', array_merge($cb, ['places' => ['hundreds'], 'target' => 243])) !== true, 'blocks validation');
check(I::grade('count-blocks', ['hundreds' => '2', 'tens' => '4', 'ones' => '3'], $cb)['correct'], 'blocks canonical');
check(I::grade('count-blocks', ['hundreds' => '1', 'tens' => '14', 'ones' => '3'], $cb)['correct'], 'blocks regrouped');
check(!I::grade('count-blocks', ['hundreds' => '1', 'tens' => '14', 'ones' => '3'], array_merge($cb, ['canonical' => true]))['correct'], 'canonical forbids regrouping');
check(!I::grade('count-blocks', ['hundreds' => '2', 'tens' => '4', 'ones' => '2'], $cb)['correct'] && !I::grade('count-blocks', ['hundreds' => '-2', 'tens' => '44', 'ones' => '3'], $cb)['correct'] && !I::grade('count-blocks', ['hundreds' => '2', 'tens' => '4', 'ones' => '3.0'], $cb)['correct'], 'blocks wrong, negative or fractional counts rejected');
check(I::grade('count-blocks', ['tens' => '24', 'ones' => '3'], $cb)['correct'], 'missing places count as zero');

$ck = ['hour' => 3, 'minute' => 45, 'tolerance' => 1];
check(I::validate_settings('set-clock', $ck) === true && I::validate_settings('set-clock', ['hour' => 13, 'minute' => 0]) !== true, 'clock validation');
check(I::grade('set-clock', ['h' => '3', 'm' => '45'], $ck)['correct'] && I::grade('set-clock', ['h' => '3', 'm' => '46'], $ck)['correct'] && !I::grade('set-clock', ['h' => '3', 'm' => '47'], $ck)['correct'], 'clock tolerance in minutes');
check(!I::grade('set-clock', ['h' => '4', 'm' => '45'], $ck)['correct'], 'wrong hour');
check(I::grade('set-clock', ['h' => '12', 'm' => '0'], ['hour' => 12, 'minute' => 0, 'tolerance' => 0])['correct'] && I::grade('set-clock', ['h' => '0', 'm' => '0'], ['hour' => 12, 'minute' => 0, 'tolerance' => 0])['correct'], 'twelve o clock');
check(I::grade('set-clock', ['h' => '11', 'm' => '59'], ['hour' => 12, 'minute' => 0, 'tolerance' => 1])['correct'], 'tolerance wraps around the face');
check(!I::grade('set-clock', ['h' => '3'], $ck)['correct'] && !I::grade('set-clock', ['h' => '25', 'm' => '99'], $ck)['correct'], 'incomplete or impossible times are wrong');
$v = I::public_view('set-clock', $ck, 's');
check(!isset($v['hour']) && !isset($v['minute']), 'clock view hides the time');

$ma = ['denominations' => [1000, 5000, 500, 100], 'symbol' => '₮', 'target' => 3500];
check(I::validate_settings('make-amount', $ma) === true && I::validate_settings('make-amount', array_merge($ma, ['target' => 3550])) !== true, 'money validation');
check(I::grade('make-amount', ['d1000' => '3', 'd500' => '1'], $ma)['correct'] && I::grade('make-amount', ['d500' => '7'], $ma)['correct'], 'any combination of bills');
check(!I::grade('make-amount', ['d1000' => '3'], $ma)['correct'] && !I::grade('make-amount', ['d200' => '17', 'd500' => '0'], $ma)['correct'] && !I::grade('make-amount', ['d1000' => '-3', 'd5000' => '1'], $ma)['correct'], 'wrong total, unknown bill and negative counts rejected');
check(I::public_view('make-amount', $ma, 's')['denominations'] === [5000, 1000, 500, 100] && !isset(I::public_view('make-amount', $ma, 's')['target']), 'money view sorts bills and hides the amount');

$fl = ['min' => 0, 'max' => 1000, 'step' => 50, 'unit' => 'ml', 'target' => 750];
check(I::validate_settings('fill-level', $fl) === true, 'jug valid');
check(I::grade('fill-level', ['value' => '750'], $fl)['correct'] && I::grade('fill-level', ['value' => '800'], $fl)['correct'] && !I::grade('fill-level', ['value' => '850'], $fl)['correct'], 'jug within one minor tick');

$bc = ['categories' => [['id' => 'cat', 'label' => 'Cats'], ['id' => 'dog', 'label' => 'Dogs']], 'max' => 10, 'step' => 1, 'values' => ['cat' => 4, 'dog' => 7], 'show_table' => true];
check(I::validate_settings('build-chart', $bc) === true && I::validate_settings('build-chart', array_merge($bc, ['values' => ['cat' => 4]])) !== true, 'chart validation');
check(I::grade('build-chart', ['cat' => '4', 'dog' => '7'], $bc)['correct'], 'chart correct');
$res = I::grade('build-chart', ['cat' => '4', 'dog' => '6'], $bc);
check(!$res['correct'] && $res['items'] === ['cat' => true, 'dog' => false], 'chart marks each bar');
check(!I::grade('build-chart', ['cat' => '4'], $bc)['correct'], 'a missing bar is wrong');
check($v = I::public_view('build-chart', $bc, 's'), 'chart view');
check($v['table'][1] === ['label' => 'Dogs', 'value' => 7.0] && !isset($v['values']), 'chart shows the table only when asked');
check(!isset(I::public_view('build-chart', array_merge($bc, ['show_table' => false]), 's')['table']), 'chart hides the table by default');

$gb = ['rows' => 6, 'cols' => 8, 'constraints' => ['area' => 12, 'perimeter' => 14, 'rectangle' => true]];
check(I::validate_settings('grid-build', $gb) === true && I::validate_settings('grid-build', array_merge($gb, ['constraints' => []])) !== true && I::validate_settings('grid-build', array_merge($gb, ['constraints' => ['area' => 99]])) !== true, 'grid validation');
$rect = function ($r, $c, $h, $w) { $a = []; for ($i = 0; $i < $h; $i++) { for ($j = 0; $j < $w; $j++) { $a['r' . ($r + $i) . 'c' . ($c + $j)] = '1'; } } return $a; };
check(I::grade('grid-build', $rect(1, 1, 3, 4), $gb)['correct'] && I::grade('grid-build', $rect(0, 0, 4, 3), $gb)['correct'], '3x4 and 4x3 both have area 12 and perimeter 14');
check(!I::grade('grid-build', $rect(0, 0, 2, 6), $gb)['correct'], '2x6 has the area but not the perimeter');
check(!I::grade('grid-build', $rect(0, 0, 3, 4) + ['r5c7' => '1'], $gb)['correct'] && !I::grade('grid-build', [], $gb)['correct'], 'extra square or nothing is wrong');
$l_shape = ['r0c0' => '1', 'r1c0' => '1', 'r2c0' => '1', 'r2c1' => '1'];
check(I::grade('grid-build', $l_shape, ['rows' => 4, 'cols' => 4, 'constraints' => ['area' => 4, 'perimeter' => 10]])['correct'] && !I::grade('grid-build', $l_shape, ['rows' => 4, 'cols' => 4, 'constraints' => ['area' => 4, 'rectangle' => true]])['correct'], 'perimeter counts bends, rectangle rejects an L');
check(!I::grade('grid-build', ['r0c0' => '1', 'r3c3' => '1'], ['rows' => 4, 'cols' => 4, 'constraints' => ['area' => 2, 'connected' => true]])['correct'] && I::grade('grid-build', ['r0c0' => '1', 'r0c1' => '1'], ['rows' => 4, 'cols' => 4, 'constraints' => ['area' => 2, 'connected' => true]])['correct'], 'connected shapes');
check(!I::grade('grid-build', ['r9c9' => '1'], ['rows' => 4, 'cols' => 4, 'constraints' => ['area' => 1]])['correct'], 'squares outside the grid are ignored');
check(!isset(I::public_view('grid-build', $gb, 's')['constraints']), 'grid view hides the conditions');

// The template helper strips every visual key, and each type has a readable expected answer.
foreach (['number-line' => $nl, 'shade-model' => $sh, 'count-blocks' => $cb, 'set-clock' => $ck, 'make-amount' => $ma, 'fill-level' => $fl, 'build-chart' => $bc, 'grid-build' => $gb] as $type => $settings) {
    $safe = I::learner_settings($type, ['id' => 4, 'settings' => $settings + ['type' => $type]]);
    foreach (OhMyLMS\Assessment\Visual::private_keys($type) as $key) {
        check(!array_key_exists($key, $safe), "$type template settings drop $key");
    }
    check(I::expected($type, $settings) !== [], "$type has an expected answer");
    check(I::is_interactive($type), "$type is registered as interactive");
}
echo "$checks assessment unit checks passed.\n";
