<?php
require __DIR__ . '/../includes/Engagement/StreakCalendar.php';
use OhMyLMS\Engagement\StreakCalendar as Calendar;

$checks = 0;
function streak_check($condition, $message) {
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
}
$settings = ['freezes' => true, 'maximum_freezes' => 2, 'refill_days' => 7];
$state = ['current_streak' => 0, 'longest_streak' => 0, 'freezes' => 2, 'refill' => 0, 'cursor_date' => null];
[$state, $added] = Calendar::practice($state, '2026-01-01', $settings);
streak_check($added && $state['current_streak'] === 1, 'First practice');
[$same, $added] = Calendar::practice($state, '2026-01-01', $settings);
streak_check(!$added && $same === $state, 'One increment per date');
[$state, $history] = Calendar::reconcile($state, '2026-01-04', $settings);
streak_check($history === ['2026-01-02' => 'protected', '2026-01-03' => 'protected'], 'Each freeze covers one date');
streak_check($state['current_streak'] === 1 && $state['freezes'] === 0, 'Protected days preserve count');
[$state] = Calendar::practice($state, '2026-01-04', $settings);
streak_check($state['current_streak'] === 2 && $state['refill'] === 1, 'Next practiced day increments once');
[$state, $history] = Calendar::reconcile($state, '2026-01-07', $settings);
streak_check($state['current_streak'] === 0 && $history['2026-01-05'] === 'missed' && $history['2026-01-06'] === 'missed', 'Exhausted multi-day gaps break');
[$state] = Calendar::practice($state, '2026-01-07', $settings);
streak_check($state['current_streak'] === 1 && $state['longest_streak'] === 2, 'Restart preserves longest');
for ($i = 8; $i <= 12; $i++) { [$state] = Calendar::practice($state, sprintf('2026-01-%02d', $i), $settings); }
streak_check($state['freezes'] === 1 && $state['refill'] === 0, 'Seven actual dates refill one freeze');
$state['freezes'] = 2; $state['refill'] = 6;
[$state] = Calendar::practice($state, '2026-01-13', $settings);
streak_check($state['refill'] === 0 && $state['freezes'] === 2, 'No banked credit at cap');
[$state, $history] = Calendar::reconcile($state, '2026-01-17', $settings);
streak_check(array_values($history) === ['protected', 'protected', 'missed'] && $state['current_streak'] === 0, 'Future earnings cannot protect past misses');
[$state] = Calendar::practice($state, '2026-01-17', $settings);
streak_check($state['current_streak'] === 1, 'Long absence restarts at one');
$off = array_replace($settings, ['freezes' => false]);
[$state, $history] = Calendar::reconcile($state, '2026-01-19', $off);
streak_check($state['freezes'] === 0 && $history['2026-01-18'] === 'missed', 'Disabled freezes do not protect');
streak_check(Calendar::date('2026-03-08 06:59:59', 'America/New_York') === '2026-03-08', 'Before DST forward');
streak_check(Calendar::date('2026-03-09 03:59:59', 'America/New_York') === '2026-03-08', '23-hour DST day');
streak_check(Calendar::date('2026-11-02 04:59:59', 'America/New_York') === '2026-11-01', '25-hour DST day');
streak_check(Calendar::date('2026-01-01 15:59:59', 'Asia/Ulaanbaatar') === '2026-01-01' && Calendar::date('2026-01-01 16:00:00', 'Asia/Ulaanbaatar') === '2026-01-02', 'Learner midnight');
streak_check(Calendar::next('2024-02-28') === '2024-02-29' && Calendar::next('2024-02-29') === '2024-03-01', 'Leap-date arithmetic');
foreach ([null, '', '  ', '<p> </p>', [], ['a' => []]] as $empty) { streak_check(!Calendar::meaningful($empty), 'Empty answer rejected'); }
foreach ([0, '0', false, 'incorrect answer', ['a' => 'wrong']] as $answer) { streak_check(Calendar::meaningful($answer), 'Participation includes incorrect answers'); }
echo "$checks streak calendar checks passed.\n";
