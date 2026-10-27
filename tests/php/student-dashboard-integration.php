<?php
if (PHP_SAPI !== 'cli' || empty($argv[1])) { exit(1); }
require rtrim($argv[1], '/\\') . '/wp-load.php';
if (!defined('OHMYLMS_TEST_SITE') || DB_NAME !== 'ohmylms_streak_test') { throw new RuntimeException('Requires isolated test site'); }
use OhMyLMS\Practice\Dashboard;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Engagement\{Streak, StreakSettings, StreakSchema};
$checks = 0;
function dashboard_check($ok, $message) { global $checks; if (!$ok) { throw new RuntimeException($message); } $checks++; }
$original = get_option('ohmylms_streak_settings', null);
$users = []; $sessions = []; $course_id = 0; $skill_id = 0;
try {
    foreach (['learner', 'other'] as $kind) { $users[$kind] = wp_create_user('dashboard-' . $kind . '-' . wp_generate_password(8, false), wp_generate_password(30)); }
    $user = $users['learner']; wp_set_current_user($user); update_user_meta($user, 'first_name', 'Alex');
    update_user_meta($user, '_ohmylms_learning_timezone', 'Asia/Ulaanbaatar');
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Ulaanbaatar'));
    $week = $now->modify('-' . ((int) $now->format('N') - 1) . ' days')->setTime(0, 0)->setTimezone(new DateTimeZone('UTC'));
    $term = wp_insert_term('Understanding fractions', \OhMyLMS\Skills\Taxonomy::NAME); $skill_id = $term['term_id'];
    $wpdb->insert(Schema::table('student_skill_state'), ['student_id' => $user, 'term_id' => $skill_id, 'level' => 'attempted', 'review_due' => 1, 'evidence_count' => 3, 'independent_correct' => 2, 'families' => 2, 'score' => 0.6, 'last_evidence_at' => $week->format('Y-m-d H:i:s'), 'updated_at' => $week->format('Y-m-d H:i:s')]);
    foreach ([$user, $users['other']] as $owner) {
        $wpdb->insert(Schema::table('practice_sessions'), ['uuid' => wp_generate_uuid4(), 'student_id' => $owner, 'mode' => 'skill', 'term_id' => $skill_id, 'policy' => '{}', 'status' => 'active', 'started_at' => $week->format('Y-m-d H:i:s')]);
        $session = (int) $wpdb->insert_id; $sessions[] = $session;
        foreach (['0', '"wrong"', 'null', '" "'] as $index => $response) {
            $wpdb->insert(Schema::table('practice_items'), ['session_id' => $session, 'position' => $index + 1, 'question_id' => 0, 'question_uuid' => wp_generate_uuid4(), 'version_id' => 0, 'option_order' => '[]', 'display' => '{}', 'response' => $response, 'answered_at' => $week->format('Y-m-d H:i:s')]);
        }
        $wpdb->insert(Schema::table('practice_items'), ['session_id' => $session, 'position' => 5, 'question_id' => 0, 'question_uuid' => wp_generate_uuid4(), 'version_id' => 0, 'option_order' => '[]', 'display' => '{}', 'response' => '"old"', 'answered_at' => $week->modify('-1 second')->format('Y-m-d H:i:s')]);
    }
    $data = Dashboard::data($user);
    dashboard_check($data['weekly'] === ['answers' => 2, 'skills' => 1, 'days' => 1], 'Weekly stats respect learner ownership, local week boundary and meaningful responses');
    dashboard_check($data['theme'] === 'meadow', 'Default theme');
    dashboard_check($data['recent'][0]['id'] === $skill_id && $data['recommendations'][0]['type'] === 'review', 'Existing skill summary and review recommendations reused');
    $request = new WP_REST_Request('PUT', '/ohmylms/v1/student/dashboard-theme'); $request->set_param('theme', 'ocean');
    dashboard_check(rest_do_request($request)->get_status() === 200 && Dashboard::data($user)['theme'] === 'ocean', 'Learner can persist own theme');
    dashboard_check(!get_user_meta($users['other'], '_ohmylms_dashboard_theme', true), 'Theme does not affect other learners');
    $request->set_param('theme', 'unknown'); dashboard_check(rest_do_request($request)->get_status() === 400, 'Unapproved themes rejected');
    wp_set_current_user(0); $request->set_param('theme', 'sunset'); dashboard_check(rest_do_request($request)->get_status() >= 400, 'Guest cannot change themes');
    wp_set_current_user($user);
    $disabled = static function () { return false; }; add_filter('ohmylms_practice_enabled', $disabled);
    dashboard_check(Dashboard::data($user)['recent'] === [], 'Practice switch respected'); remove_filter('ohmylms_practice_enabled', $disabled);
    $course_id = wp_insert_post(['post_type' => OHMYLMS_COURSE_CPT, 'post_title' => 'Exploring fractions', 'post_status' => 'publish']);
    $wpdb->insert($wpdb->prefix . 'ohmylms_user_enrollment', ['user_id' => $user, 'course_id' => $course_id, 'status' => 'enrolled', 'progress' => 'running', 'start_date' => current_time('mysql')]);
    update_option('ohmylms_streak_settings', array_replace(StreakSettings::defaults(), ['enable' => true])); StreakSchema::install(); Streak::record($user, 'practice', 'dashboard-fixture');
    $student = new \OhMyLMS\Data\Student($user);
    ob_start(); include OHMYLMS_DIR . '/templates/profile/dashboard-content.php'; $html = ob_get_clean();
    dashboard_check(strpos($html, 'Exploring fractions') !== false && strpos($html, 'oml-panel-recommendations') !== false, 'Dashboard renders courses and recommendation panel');
    dashboard_check(strpos($html, 'Next streak milestone') !== false && strpos($html, 'Active practice days') !== false, 'Dashboard reuses streak and real weekly summaries');
    wp_mkdir_p(OHMYLMS_DIR . '/build/test-fixtures');
    $fixture = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/dashboard.css"><link rel="stylesheet" href="/streak.css"></head><body style="font-family:system-ui;margin:0;padding:16px"><main style="max-width:1200px;margin:auto">' . $html . '</main><script src="/dashboard.js"></script></body></html>';
    file_put_contents(OHMYLMS_DIR . '/build/test-fixtures/student-dashboard.html', $fixture);
    echo "$checks student dashboard checks passed; browser fixture rendered.\n";
} finally {
    foreach ($sessions as $id) { $wpdb->delete(Schema::table('practice_items'), ['session_id' => $id]); $wpdb->delete(Schema::table('practice_sessions'), ['id' => $id]); }
    foreach ($users as $id) { foreach (['state', 'days', 'activities', 'milestones'] as $name) { $wpdb->delete(StreakSchema::table($name), ['user_id' => $id]); } $wpdb->delete($wpdb->prefix . 'ohmylms_user_enrollment', ['user_id' => $id]); $wpdb->delete($wpdb->prefix . 'ohmylms_user_achievement', ['user_id' => $id]); require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user($id); }
    if ($course_id) { wp_delete_post($course_id, true); }
    if ($skill_id) { $wpdb->delete(Schema::table('student_skill_state'), ['term_id' => $skill_id]); wp_delete_term($skill_id, \OhMyLMS\Skills\Taxonomy::NAME); }
    if ($original === null) { delete_option('ohmylms_streak_settings'); } else { update_option('ohmylms_streak_settings', $original); }
}
