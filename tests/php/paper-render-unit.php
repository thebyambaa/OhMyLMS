<?php
// Server-rendered paper: the PHP paper model equals the editor's (tests/fixtures/paper-models.json) and
// PaperRenderer prints the paper markup that paper.css styles. Run: php tests/php/paper-render-unit.php
define('ABSPATH', __DIR__);
define('OHMYLMS_DIR', dirname(__DIR__, 2));
define('OHMYLMS_FILE', OHMYLMS_DIR . '/ohmylms.php');
require OHMYLMS_DIR . '/vendor/autoload.php';
$GLOBALS['filtered'] = 0;
function __($text, $domain = '') { return $text; }
function _n($single, $plural, $count, $domain = '') { return 1 === (int) $count ? $single : $plural; }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_html__($text, $domain = '') { return esc_html($text); }
function esc_url($url) { return preg_match('#^https?://#', (string) $url) ? htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8') : ''; }
function wp_kses($value, $allowed) { return strip_tags($value, array_map(fn($tag) => "<$tag>", array_keys($allowed))); }
function wp_kses_post($html) { return preg_replace(['#<script\b[^>]*>.*?</script>#is', '#\son\w+="[^"]*"#i'], '', (string) $html); }
function wp_strip_all_tags($html) { return strip_tags((string) $html); }
function apply_filters($hook, $value, ...$args) { if ('ohmylms_paper_question_html' === $hook) { $GLOBALS['filtered']++; } return $value; }
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
use OhMyLMS\Exams\PaperModel;
use OhMyLMS\Exams\PaperRenderer;
use OhMyLMS\Exams\PaperSettings;

/** Whole floats as integers, keyed arrays sorted, cell sets sorted: the shapes JSON gives both sides. */
function normalize($value, $key = '') {
	if (is_float($value) && floor($value) === $value) { return (int) $value; }
	if (!is_array($value)) { return $value; }
	$out = [];
	foreach ($value as $k => $item) { $out[$k] = normalize($item, (string) $k); }
	if (in_array($key, ['cells', 'marked'], true)) { sort($out); }
	if (!array_is_list($out)) { ksort($out); }
	return $out;
}

// ---- The PHP model is the editor's model ----
$fixture  = json_decode(file_get_contents(OHMYLMS_DIR . '/tests/fixtures/paper-models.json'), true);
$settings = PaperSettings::sanitize($fixture['settings']);
check(count($fixture['cases']) >= 90, 'The fixture covers every printed type');
foreach ($fixture['cases'] as $case) {
	$model = PaperModel::question($case['question'], $settings, $case['answers'], $case['prompt'], $case['version']);
	$want  = normalize($case['expected']);
	$got   = normalize(json_decode(json_encode($model), true));
	check($want === $got, $case['name'] . " differs from the editor:\n" . json_encode($want) . "\n" . json_encode($got));
}

// ---- Numbering, totals and versions ----
$defaults = PaperSettings::DEFAULTS;
$item     = fn($id, $type = 'single-choice', $value = 1) => ['kind' => 'question', 'question' => ['id' => $id, 'settings' => ['type' => $type, 'score' => ['enabled' => true, 'value' => $value]]]];
$items    = [
	['kind' => 'paper', 'name' => 'ohmylms/paper-header', 'attrs' => [], 'items' => []],
	$item(1), $item(2), $item(3),
	['kind' => 'paper', 'name' => 'ohmylms/paper-section', 'attrs' => ['title' => 'B'], 'items' => []],
	$item(4, 'single-choice', 2), $item(5, 'statement'), $item(6), $item(7),
	['kind' => 'paper', 'name' => 'ohmylms/paper-columns', 'attrs' => [], 'items' => [$item(8), $item(9), ['span' => true] + $item(10)]],
];
$ids = function (array $list) use (&$ids) {
	$out = [];
	foreach ($list as $entry) {
		if ('question' === $entry['kind']) { $out[] = $entry['question']['id']; } else { $out = array_merge($out, ['[' . $entry['name'] . ']'], $ids($entry['items'])); }
	}
	return $out;
};
check($items === PaperModel::version_items($items, 'A'), 'Version A is the paper as laid out');
$b = $ids(PaperModel::version_items($items, 'B'));
// The same runs and anchors as versionItems() in paperModel.mjs (tests/js/paper-types.test.mjs).
check([7, 6] === array_slice($b, 7, 2) && [9, 8, 10] === array_slice($b, 10) && ['[ohmylms/paper-section]', 4, 5] === array_slice($b, 4, 3), 'Versions shuffle each run in place: ' . json_encode($b));
check($b === $ids(PaperModel::version_items($items, 'B')) && $b !== $ids(PaperModel::version_items($items, 'C')), 'Versions are repeatable and differ');
$annotated = PaperModel::annotate($items, ['numbering' => 'q'] + $defaults);
check('Q1' === $annotated['items'][1]['label'] && '' === $annotated['items'][6]['label'] && 'Q9' === $annotated['items'][9]['items'][2]['label'], 'Statements are not numbered; nested questions are');
check(10.0 === $annotated['total'] && 7.0 === $annotated['items'][4]['total'], 'Total and section marks');
check('B' === PaperModel::version('b', ['versions' => 2] + $defaults) && 'A' === PaperModel::version('C', ['versions' => 2] + $defaults), 'Only versions the paper offers');
check(90 === PaperModel::minutes(['value' => 1.5, 'type' => 'hours']) && 0 === PaperModel::minutes(null), 'Time limits in minutes');

// ---- The document ----
$question = fn($id, $name, $type = 'short-text', $extra = []) => ['kind' => 'question', 'question' => array_replace_recursive(['id' => $id, 'name' => $name, 'public' => true, 'settings' => ['type' => $type, 'score' => ['enabled' => true, 'value' => 2]], 'questions' => []], $extra)];
$document = [
	['kind' => 'paper', 'name' => 'ohmylms/paper-header', 'attrs' => ['school' => '<b>West</b><img src=x onerror=alert(1)>', 'fields' => ['name', 'date', 'evil']], 'items' => []],
	['kind' => 'paper', 'name' => 'ohmylms/paper-instructions', 'attrs' => [], 'items' => [['kind' => 'html', 'html' => '<ul><li>Use a pen</li></ul><script>x()</script>']]],
	['kind' => 'paper', 'name' => 'ohmylms/paper-section', 'attrs' => ['title' => 'Section A'], 'items' => []],
	$question(11, 'Capital of <France>?'),
	['kind' => 'paper', 'name' => 'ohmylms/paper-page-break', 'attrs' => [], 'items' => []],
	['kind' => 'layout', 'name' => 'core/columns', 'attrs' => [], 'items' => [
		['kind' => 'layout', 'name' => 'core/column', 'attrs' => ['width' => '40%'], 'items' => [$question(12, 'Pick one', 'single-choice', ['questions' => [['answer' => 'Yes'], ['answer' => 'No']]])]],
		['kind' => 'layout', 'name' => 'core/column', 'attrs' => ['width' => 'calc(1px)'], 'items' => [$question(13, 'Say why', 'long-text')]],
	]],
	['kind' => 'paper', 'name' => 'ohmylms/paper-answer-space', 'attrs' => ['style' => 'grid', 'lines' => 99], 'items' => []],
	['kind' => 'paper', 'name' => 'ohmylms/paper-formula-sheet', 'attrs' => ['newPage' => false], 'items' => []],
];
$context = ['title' => 'Exam <1>', 'time_limit' => ['value' => 45], 'settings' => $defaults, 'version' => 'A'];
$html    = PaperRenderer::document($document, $context);
check(str_contains($html, '<p class="ohmylms-paper-header-school"><b>West</b></p>') && !str_contains($html, 'onerror'), 'Header text keeps bold only');
check(str_contains($html, '<p class="ohmylms-paper-header-title">Exam &lt;1&gt;</p>') && str_contains($html, 'Time: 45 minutes · Total: 6 marks'), 'Title escaped; time and total marks');
check(str_contains($html, 'is-name') && str_contains($html, 'is-date') && !str_contains($html, 'is-evil'), 'Only known candidate fields');
check(str_contains($html, '<p class="ohmylms-paper-instructions-heading">Instructions</p><div class="ohmylms-print-block"><ul><li>Use a pen</li></ul></div>') && !str_contains($html, '<script'), 'Instructions use the default heading and filtered content');
check(str_contains($html, '<span class="ohmylms-paper-section-total">[6 marks]</span>'), 'Section totals');
check(str_contains($html, '<p>Capital of &lt;France&gt;?</p>') && str_contains($html, '<span class="ohmylms-print-number">1</span>'), 'Statements are escaped and numbered');
check(str_contains($html, '<div class="ohmylms-paper-page-break"><span>Page break</span></div>'), 'Page breaks print');
check(str_contains($html, '<div class="ohmylms-print-column" style="flex:0 0 40%">') && str_contains($html, '<div class="ohmylms-print-column"><div'), 'Column widths only when valid');
check(str_contains($html, '<ol class="ohmylms-print-options"><li><span class="ohmylms-print-letter">A</span><span class="ohmylms-print-option">Yes</span></li>'), 'Choices print lettered');
check(str_contains($html, '--ohmylms-paper-lines:4') && str_contains($html, 'is-grid" style="--ohmylms-paper-lines:30"'), 'Lines per mark and clamped answer space');
check(str_contains($html, '<div class="ohmylms-paper-formula-sheet"><p class="ohmylms-paper-formula-heading">Formula sheet</p>'), 'Formula sheet without a new page');
check(3 === $GLOBALS['filtered'], 'Every printed question passes through ohmylms_paper_question_html');

// The screen paper: continuous, with the player's boxes in the question places.
$placed = [];
$screen = PaperRenderer::document($document, $context + ['continuous' => true, 'slot' => function ($entry, $label) use (&$placed) {
	$placed[] = [$entry['question']['id'], $label];
	return 13 === $entry['question']['id'] ? null : '<div class="box"></div>';
}]);
check(!str_contains($screen, 'ohmylms-paper-page-break') && [[11, '1'], [12, '2'], [13, '3']] === $placed, 'Slots receive questions with their printed numbers');
check(2 === substr_count($screen, '<div class="box"></div>') && !str_contains($screen, 'Say why'), 'A question without a box leaves no place');

// Learner Paper View heads.
$choice = ['id' => 12, 'public' => true, 'name' => 'Pick one', 'settings' => ['type' => 'single-choice'], 'questions' => [['answer' => 'Yes'], ['answer' => 'No']]];
$head   = PaperRenderer::question($choice, 'Q2', $defaults, false, 'A', ['choice_input' => fn($option, $index) => '<input name="a" value="' . $index . '">']);
check(str_contains($head, '<li class="ohmylms-paper-choice"><label class="ohmylms-paper-choice-label"><input name="a" value="0"><span class="ohmylms-print-letter">A</span>'), 'Printed letters label the player fields');
$only = PaperRenderer::question($choice, 'Q2', $defaults, false, 'A', ['head_only' => true, 'prompt' => '<div class="question-box"><input></div>']);
check(!str_contains($only, 'ohmylms-print-options') && str_contains($only, '<div class="ohmylms-print-prompt"><div class="question-box"><input></div></div>'), 'Head only, with the player statement');

// Answer key and answer sheet.
$key = PaperRenderer::question(['id' => 5, 'name' => 'Order', 'settings' => ['type' => 'reorder', 'score' => ['enabled' => true, 'value' => 1]], 'questions' => [['answer' => 'egg', 'order_number' => 1], ['answer' => 'larva', 'order_number' => 2], ['answer' => 'pupa', 'order_number' => 3], ['answer' => 'butterfly', 'order_number' => 4]]], '1', $defaults, true);
check(str_contains($key, 'Number the boxes to show the correct order.') && str_contains($key, '<div class="ohmylms-print-key"><p>B, C, A, D</p></div>'), 'Reorder key letters (as the editor: jumble [2, 0, 1, 3])');
$sheet_items = [$item(1), $item(2, 'long-text'), ['kind' => 'question', 'question' => ['id' => 3, 'settings' => ['type' => 'multiple-choice'], 'questions' => [['answer' => 'a', 'is_correct' => 1], ['answer' => 'b', 'is_correct' => 0]]]]];
$sheet = PaperRenderer::answer_sheet($sheet_items, ['title' => 'T', 'settings' => $defaults, 'answers' => true]);
check(str_contains($sheet, 'Marking sheet') && 1 === substr_count($sheet, 'is-filled') && str_contains($sheet, '<li class="is-multiple">') && str_contains($sheet, 'Answer question 2 on the question paper.'), 'Marking sheet fills correct circles');
check(PaperRenderer::has_math('<p>$x^2$</p>') && PaperRenderer::has_math('[[ohmylms-math:latex:inline]]x[[/ohmylms-math]]') && !PaperRenderer::has_math('<p>Five dollars</p>'), 'Math detection');

// Figures.
$figure = PaperRenderer::question(['id' => 24, 'settings' => ['type' => 'set-clock', 'hour' => 15, 'minute' => 5, 'show_digital' => true]], '1', $defaults, true);
check(str_contains($figure, 'class="ohmylms-print-figure is-clock"') && str_contains($figure, '<g stroke="#b32d2e"') && str_contains($figure, '<p>Answer: 15:05</p>'), 'Clock figure with hands in the key');
$blank = PaperRenderer::question(['id' => 24, 'public' => true, 'settings' => ['type' => 'set-clock', 'show_digital' => true]], '1', $defaults);
check(!str_contains($blank, '#b32d2e') && str_contains($blank, '<span class="ohmylms-print-box"></span>:'), 'Student clock has no hands');
echo "paper-render-unit: ok\n";
