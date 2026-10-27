<?php
// Printable paper: settings validation, the printed page box and print links.
define('ABSPATH', __DIR__);
define('OHMYLMS_DIR', dirname(__DIR__, 2));
define('OHMYLMS_FILE', OHMYLMS_DIR . '/ohmylms.php');
require OHMYLMS_DIR . '/vendor/autoload.php';
$GLOBALS['translation'] = 'Page %1$s of %2$s';
function __($text, $domain) { return 'Page %1$s of %2$s' === $text ? $GLOBALS['translation'] : $text; }
function wp_create_nonce($action) { return 'nonce-' . $action; }
function home_url($path) { return 'https://example.test' . $path; }
function add_query_arg(array $args, $url) { return $url . '?' . http_build_query($args); }
function wp_kses($value, $allowed) { return strip_tags($value, array_map(fn($tag) => "<$tag>", array_keys($allowed))); }
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
use OhMyLMS\Exams\PaperSettings;
use OhMyLMS\Exams\PrintPaper;

check(PaperSettings::sanitize(null) === PaperSettings::DEFAULTS, 'Missing settings fall back to the defaults');
$odd = PaperSettings::sanitize(['size' => 'a0', 'margin' => '99', 'font_size' => 3, 'font' => '<b>', 'numbering' => 'q', 'show_marks' => '0', 'lines_per_mark' => 'x', 'default_view' => true, 'evil' => 'x']);
check('a4' === $odd['size'] && 30 === $odd['margin'] && 9 === $odd['font_size'], 'Size and numbers are clamped');
check('sans' === $odd['font'] && 'q' === $odd['numbering'], 'Choices keep only allowed values');
check(false === $odd['show_marks'] && true === $odd['default_view'] && 2 === $odd['lines_per_mark'], 'Flags and invalid numbers are normalized');
check(!isset($odd['evil']) && array_keys($odd) === array_keys(PaperSettings::DEFAULTS), 'Only known fields are kept');

$css = PrintPaper::page_css(PaperSettings::sanitize(['size' => 'letter', 'margin' => 25, 'font_size' => 12]));
check(str_contains($css, '@page{size:letter;margin:25mm;'), 'The page box uses the paper size and margins');
check(str_contains($css, 'content:"Page " counter(page) " of " counter(pages)'), 'Page numbers use the print counters');
check(str_contains($css, '--ohmylms-paper-font-size:12pt') && str_contains($css, '--ohmylms-paper-width:215.9mm'), 'The sheet matches the settings');
check(!str_contains(PrintPaper::page_css(PaperSettings::sanitize(['page_numbers' => false])), '@bottom-center'), 'Page numbers can be left out');

// A translation may reorder the counters and must not break out of the CSS string.
$GLOBALS['translation'] = '%2$s pages; "now" %1$s </style>';
$css = PrintPaper::page_css(PaperSettings::DEFAULTS);
check(str_contains($css, 'content:counter(pages) " pages; \"now\" " counter(page) " \3c /style>"') && !str_contains($css, '</style'),'Translated footers keep their order and are escaped: ' . $css);

$url = PrintPaper::url(42, 'key');
check(str_contains($url, 'ohmylms_print=42') && str_contains($url, 'variant=key') && str_contains($url, '_wpnonce=nonce-ohmylms_print_42'), 'Print links carry the exam, variant and an exam-specific nonce');
check(str_contains(PrintPaper::url(42, 'anything'), 'variant=paper'), 'Unknown variants print the student paper');
// Paper block attributes come from block comments, which saving does not filter.
$attrs = PrintPaper::paper_attributes(['school' => '<b>West</b> <script>x()</script><img src=x onerror=y>', 'showTime' => false, 'lines' => 6, 'fields' => ['name', '<i>class</i>'], 'bad-key"' => 'v', 'object' => new stdClass()]);
check('<b>West</b> x()' === $attrs['school'], 'Text keeps only bold, italic and line breaks: ' . $attrs['school']);
check(false === $attrs['showTime'] && 6 === $attrs['lines'], 'Booleans and numbers are kept');
check(['name', '<i>class</i>'] === $attrs['fields'], 'Lists are filtered item by item');
check('v' === $attrs['badkey'] && null === $attrs['object'], 'Keys are reduced to safe characters and objects are dropped');
// Printed order must match jumble() in paperTypes.mjs (values from tests/js/paper-types.test.mjs).
check(3826002220 === PrintPaper::hash('a') && 0x811c9dc5 === PrintPaper::hash(''), 'FNV-1a matches the reference values');
check(3110411366 === PrintPaper::hash('42|Égalité') && 2491053697 === PrintPaper::hash('5|egg'), 'Hashes use UTF-8 bytes like the editor');
check([2, 0, 1, 3] === PrintPaper::jumble(['egg', 'larva', 'pupa', 'butterfly'], '5', true), 'Reorder steps jumble as in the editor');
check([2, 1, 0] === PrintPaper::jumble(['Paris', 'Rome', 'Madrid'], '9', true), 'Matching answers jumble as in the editor');
check([1, 0, 2, 3] === PrintPaper::jumble(['Égalité', 'ß', '日本', 'a'], '77'), 'Unicode texts jumble as in the editor');
for ($seed = 0; $seed < 200; $seed++) {
	check([1, 0] === PrintPaper::jumble(['a', 'b'], (string) $seed, true), 'A list is never printed in answer order');
}
// Versions and answer sheets.
check(1 === PaperSettings::sanitize([])['versions'] && 4 === PaperSettings::sanitize(['versions' => 9])['versions'], 'Versions default to one and stop at four');
check(str_contains(PrintPaper::url(42, 'sheet', 'B'), 'variant=sheet&version=B'), 'Links carry the sheet variant and version');
check(!str_contains(PrintPaper::url(42, 'paper', 'A'), 'version='), 'Version A needs no parameter');
check(str_contains(PrintPaper::url(42, 'sheet-key'), 'variant=sheet-key') && !str_contains(PrintPaper::url(42, 'paper', 'Z'), 'version='), 'Unknown versions are dropped');
check('12' === PrintPaper::seed(12, 'A') && '12:C' === PrintPaper::seed(12, 'C'), 'Seeds match questionSeed() in paperModel.mjs');
echo "paper-print-unit: ok\n";
