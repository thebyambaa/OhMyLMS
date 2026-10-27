<?php
/** Pure checks for unit display numbers (no WordPress database). */
define('ABSPATH', __DIR__ . '/');
function __($text) { return $text; }
require dirname(__DIR__, 2) . '/vendor/autoload.php';
use OhMyLMS\Curriculum\UnitNumber;
$checks = 0;
function check($condition, $message) { global $checks; if (!$condition) { throw new RuntimeException($message); } $checks++; }

// ---- Unit labels ----
check(UnitNumber::normalize('3') === '3' && UnitNumber::normalize(' 2A ') === '2A' && UnitNumber::normalize(4) === '4', 'short labels are kept, trimmed');
check(UnitNumber::normalize('A.1-b') === 'A.1-b', 'dot and dash are allowed');
check(UnitNumber::normalize('') === '' && UnitNumber::normalize('   ') === '' && UnitNumber::normalize(null) === '', 'empty means automatic');
check(UnitNumber::normalize('abcdefghij') === 'abcdefghij', 'ten characters fit');
check(UnitNumber::normalize('abcdefghijk') === null, 'eleven characters do not');
foreach (['Unit 2', '2_A', '<b>', "1\n2", 'é', '1/2', [1], new stdClass()] as $bad) {
    check(UnitNumber::normalize($bad) === null, 'rejects ' . (is_scalar($bad) ? json_encode($bad) : gettype($bad)));
}
check(UnitNumber::normalize("3\n") === '3', 'a trailing newline is trimmed, not smuggled in');

// ---- What a unit prints ----
check(UnitNumber::label('', 0) === '1' && UnitNumber::label('', 3) === '4', 'no label: position + 1, as before');
check(UnitNumber::label('2A', 0) === '2A' && UnitNumber::label('7', 1) === '7', 'a stored label wins');
check(UnitNumber::label(null, 2) === '3', 'a missing label is automatic');

echo "Unit number checks passed: $checks
";
