<?php
if (PHP_SAPI !== 'cli' || empty($argv[1])) { exit(1); }
require rtrim($argv[1], '/\\') . '/wp-load.php';
if (!defined('OHMYLMS_TEST_SITE') || !OHMYLMS_TEST_SITE || DB_NAME !== 'ohmylms_streak_test') { throw new RuntimeException('Requires isolated streak site'); }
use OhMyLMS\Engagement\{Streak, StreakSchema, StreakSettings};
$original = get_option('ohmylms_streak_settings', null);
$user = wp_create_user('streak-card-' . wp_generate_password(12, false), wp_generate_password(30));
if (is_wp_error($user)) { throw new RuntimeException('Could not create card fixture'); }
$clock = strtotime('2026-01-05 12:00:00 UTC');
add_filter('ohmylms_streak_clock', static function () use (&$clock) { return $clock; });
try {
    update_option('ohmylms_streak_settings', array_replace(StreakSettings::defaults(), ['enable' => true, 'initial_freezes' => 1]));
    wp_set_current_user($user);
    Streak::record($user, 'practice', 'card-session');
    $clock = strtotime('2026-01-08 12:00:00 UTC');
    ob_start();
    include OHMYLMS_DIR . '/templates/profile/streak.php';
    $card = ob_get_clean();
    $output = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/streak.css"></head><body style="font-family:system-ui,sans-serif"><main style="max-width:1000px;margin:24px auto">' . $card . '</main><script src="/streak.js"></script></body></html>';
    wp_mkdir_p(OHMYLMS_DIR . '/build/test-fixtures');
    file_put_contents(OHMYLMS_DIR . '/build/test-fixtures/streak-card.html', $output);
    echo "Isolated streak card fixture rendered.\n";
} finally {
    foreach (['state', 'days', 'activities', 'milestones'] as $name) { $wpdb->delete(StreakSchema::table($name), ['user_id' => $user]); }
    $wpdb->delete($wpdb->prefix . 'ohmylms_user_achievement', ['user_id' => $user]);
    require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user($user);
    if ($original === null) { delete_option('ohmylms_streak_settings'); } else { update_option('ohmylms_streak_settings', $original); }
}
