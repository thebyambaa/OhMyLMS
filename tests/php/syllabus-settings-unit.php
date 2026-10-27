<?php
define('ABSPATH', __DIR__ . '/');
function __($text) { return $text; }
function sanitize_text_field($text) { return trim(strip_tags($text)); }
function is_wp_error($value) { return $value instanceof WP_Error; }
class WP_Error { public function __construct(...$args) {} }
require dirname(__DIR__, 2) . '/vendor/autoload.php';
use OhMyLMS\Curriculum\SyllabusSettings;
use OhMyLMS\Learning\CourseProgram;
use OhMyLMS\Curriculum\Icons;
function verify($value, $message) { if (!$value) { throw new RuntimeException($message); } }
$settings = SyllabusSettings::clean(['grade' => ' Grade 9 ', 'subject' => 'Математик', 'language' => 'Монгол', 'categories' => ['Core', 'Extended', 'Core', ' Олимпиад ', ''], 'price' => 100]);
verify($settings === ['grade' => 'Grade 9', 'subject' => 'Математик', 'language' => 'Монгол', 'categories' => ['Core', 'Extended', 'Олимпиад']], 'grade profiles retain custom categories and reject unrelated course fields');
verify(SyllabusSettings::clean(['categories' => []]) === ['categories' => []], 'category suggestions can be cleared without modifying skills');
verify(SyllabusSettings::clean(['grade' => str_repeat('x', 101)]) instanceof WP_Error, 'profile text has a length limit');
verify(SyllabusSettings::clean(['categories' => 'Core']) instanceof WP_Error, 'categories must be a list');
verify(SyllabusSettings::clean(['categories' => [str_repeat('x', 61)]]) instanceof WP_Error, 'category length is validated');
$program = ['mode' => 'skill-based', 'items' => [], 'outcomes' => [['term_id' => 1, 'required' => false]]];
verify(CourseProgram::completion_readiness($program, true) === [], 'grade skill collections need no course completion requirement');
verify(count(CourseProgram::completion_readiness($program, false)) === 2, 'ordinary courses retain completion checks');
$program['outcomes'] = [];
verify(count(CourseProgram::completion_readiness($program, true)) === 1, 'an empty syllabus cannot be published');
verify(Icons::clean('calculator') === 'calculator', 'supported topic and chapter icons are accepted');
verify(Icons::clean('') === '', 'an icon can be reset to the default');
verify(Icons::clean('invalid-icon') instanceof WP_Error && Icons::clean(['calculator']) instanceof WP_Error, 'unsupported and malformed icons are rejected');
verify(SyllabusSettings::category_allowed('Core', ['Core', 'Extended']), 'skills can choose an existing syllabus category');
verify(SyllabusSettings::category_allowed('', ['Core']), 'a skill category can be cleared');
verify(!SyllabusSettings::category_allowed('New category', ['Core']), 'skills cannot create categories');
verify(!SyllabusSettings::category_allowed('core', ['Core']), 'category selections match stored labels exactly');
verify(SyllabusSettings::clean(['category_styles' => ['Core' => ['icon' => 'calculator', 'color' => '#AABBCC']]]) === ['category_styles' => ['Core' => ['icon' => 'calculator', 'color' => '#aabbcc']]], 'category icons and colors are persisted safely');
verify(SyllabusSettings::clean(['category_styles' => ['Core' => ['icon' => 'invalid-icon', 'color' => '#aabbcc']]]) instanceof WP_Error, 'invalid category icons are refused');
verify(SyllabusSettings::clean(['category_styles' => ['Core' => ['color' => 'red;display:none']]]) instanceof WP_Error, 'only hex icon colors are accepted');
verify(SyllabusSettings::clean(['category_styles' => 'Core']) instanceof WP_Error, 'category style collections must be arrays');
echo "syllabus settings unit checks passed: 19\n";
