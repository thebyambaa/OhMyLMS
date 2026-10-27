<?php
/** Pure checks that course links are stored in the order they are submitted (the hub strips read them by position). */
define('ABSPATH', __DIR__ . '/');
function __($text) { return $text; }
require dirname(__DIR__, 2) . '/vendor/autoload.php';
use OhMyLMS\Curriculum\CourseLinks;
$checks = 0;
function check($condition, $message) { global $checks; if (!$condition) { throw new RuntimeException($message); } $checks++; }

// ---- Course links keep the order they were saved in (the hub strip reads them by position) ----
$order = [['course_id' => 20, 'type' => 'get_ready'], ['course_id' => 10, 'type' => 'get_ready'], ['course_id' => 30, 'type' => 'subcourse']];
$stored = CourseLinks::validate(1, $order, []);
check(is_array($stored) && array_column($stored, 'course_id') === [20, 10, 30] && array_column($stored, 'position') === [0, 1, 2], 'positions follow the submitted order, not the course ID');
$swapped = CourseLinks::validate(1, [$order[1], $order[0], $order[2]], []);
check(is_array($swapped) && array_column($swapped, 'course_id') === [10, 20, 30] && array_column($swapped, 'position') === [0, 1, 2], 'a reordered list is stored in the new order');
echo "Course link order checks passed: $checks
";
