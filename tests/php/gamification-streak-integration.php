<?php
if (PHP_SAPI !== 'cli' || empty($argv[1])) { exit("Usage: php gamification-streak-integration.php /isolated/wordpress\n"); }
require rtrim($argv[1], '/\\') . '/wp-load.php';
if (!defined('OHMYLMS_TEST_SITE') || !OHMYLMS_TEST_SITE || !in_array(DB_NAME, ['ohmylms_streak_test', 'ohmylms_source_test'], true)) { throw new RuntimeException('Requires an isolated test site.'); }
use OhMyLMS\Engagement\{Streak, StreakSchema, StreakSettings, StreakHooks, StreakCalendar, Rules, Badge, Level, Point, Achievements};
$clock = strtotime('2026-01-01 12:00:00 UTC');
add_filter('ohmylms_streak_clock', static function () use (&$clock) { return $clock; });
if (($argv[2] ?? '') === '--worker') {
    $clock = (int) $argv[4];
    $result = Streak::record((int) $argv[3], 'practice', $argv[5] ?? 'concurrent-session');
    if (is_wp_error($result)) { exit(1); }
    exit(0);
}
$checks = 0;
function gamification_check($condition, $message) { global $checks; if (!$condition) { throw new RuntimeException($message); } $checks++; }
function engagement_request($method, $path, $body = []) {
    $request = new WP_REST_Request($method, '/ohmylms/v1/engagement/' . $path);
    $request->set_header('Content-Type', 'application/json'); $request->set_body(wp_json_encode($body));
    return rest_do_request($request);
}
$options = ['timezone_string', 'ohmylms_integrations', 'ohmylms_point_settings', 'ohmylms_badge_settings', 'ohmylms_level_settings', 'ohmylms_reward_settings', 'ohmylms_badges', 'ohmylms_levels', 'ohmylms_streak_settings'];
$original = []; foreach ($options as $name) { $original[$name] = get_option($name, null); }
$users = []; $posts = []; $enrollments = []; $practice_sessions = []; $attempts = [];
try {
    StreakSchema::install();
    gamification_check(StreakSchema::ready() && StreakSchema::install(), 'Versioned schema installation is idempotent');
    update_option('timezone_string', 'UTC');
    update_option('ohmylms_integrations', ['gamification' => ['is_enable' => true]]);
    foreach (['point', 'badge', 'level', 'reward'] as $group) { update_option('ohmylms_' . $group . '_settings', ['feature_enabled' => true, 'rules' => []]); }
    update_option('ohmylms_streak_settings', array_replace(StreakSettings::defaults(), ['enable' => true]));
    foreach (['learner', 'other', 'teacher', 'concurrent', 'custom'] as $kind) {
        $id = wp_create_user('streak-' . $kind . '-' . wp_generate_password(10, false), wp_generate_password(30));
        gamification_check(!is_wp_error($id), 'Fixture user created'); $users[$kind] = $id;
    }
    $admin = get_users(['role' => 'administrator', 'number' => 1])[0];
    wp_set_current_user($admin->ID);
    gamification_check(engagement_request('POST', 'settings/streak', array_replace(StreakSettings::defaults(), ['enable' => true]))->get_status() === 200, 'Streak settings persist');
    gamification_check(engagement_request('POST', 'settings/streak', ['practice_minimum' => 0])->get_status() === 400, 'Invalid settings rejected');
    gamification_check(engagement_request('POST', 'settings/anything', [])->get_status() === 400, 'Unknown setting group rejected');
    gamification_check(engagement_request('POST', 'badges', ['name' => 'Broken', 'rules' => [['dataValue' => 'unknown']]])->get_status() === 400, 'Invalid badge rules rejected');
    $custom_badge = engagement_request('POST', 'badges', ['name' => 'Custom streak fixture', 'award_source' => 'streak', 'rules' => []]);
    $custom_badge_data = $custom_badge->get_data()['badge'] ?? [];
    gamification_check($custom_badge->get_status() === 200 && ($custom_badge_data['slug'] ?? '') === 'custom-streak-fixture', 'Existing badge endpoint creates a streak badge and returns its identity');
    gamification_check(!in_array('custom-streak-fixture', Badge::maybe_met_rules($users['learner']), true), 'Streak badge cannot be awarded by ordinary achievement rules');
    update_option('ohmylms_streak_settings', array_replace(StreakSettings::defaults(), ['enable' => true, 'milestones' => [['days' => 1, 'badge' => 'custom-streak-fixture', 'points' => 0]]]));
    Streak::record($users['custom'], 'practice', 'custom-badge-fixture');
    gamification_check(Achievements::achievement_exists($users['custom'], 'badge', null, null, null, 'custom-streak-fixture'), 'Custom badge is awarded through the existing streak milestone delivery');
    update_option('ohmylms_streak_settings', array_replace(StreakSettings::defaults(), ['enable' => true]));
    wp_set_current_user($users['learner']);
    gamification_check(engagement_request('POST', 'settings/streak', ['enable' => true])->get_status() === 403, 'Learner cannot change settings');
    wp_set_current_user(0);
    gamification_check(engagement_request('GET', 'streak')->get_status() >= 400, 'Unauthenticated streak reads blocked');

    $u = $users['learner'];
    gamification_check(Streak::record($u, 'purchase', 'order') === false, 'Nonlearning event rejected');
    gamification_check(Streak::record($u, 'practice', 'session-1') === true, 'First qualifying day');
    gamification_check(Streak::record($u, 'practice', 'session-1') === false, 'Duplicate activity rejected');
    gamification_check(Streak::record($u, 'practice', 'session-2') === false, 'Different activity on same date does not increment');
    gamification_check(Streak::snapshot($u)['current_streak'] === 1, 'Overall daily streak');
    gamification_check(is_wp_error(Streak::set_timezone($u, 'Asia/Ulaanbaatar')), 'Active timezone change cannot duplicate dates');
    $clock = strtotime('2026-01-04 12:00:00 UTC');
    $state = Streak::snapshot($u);
    gamification_check($state['current_streak'] === 1 && $state['freezes'] === 0, 'Late read reconciles two protected dates');
    gamification_check(count(array_filter($state['history'], static function ($day) { return $day['status'] === 'protected'; })) === 2, 'Protected dates are distinct');
    gamification_check(Streak::record($u, 'practice', 'session-3') === true && Streak::snapshot($u)['current_streak'] === 2, 'Day after freezes increments');
    $clock = strtotime('2026-01-08 12:00:00 UTC');
    gamification_check(Streak::snapshot($u)['current_streak'] === 0 && Streak::snapshot($u)['longest_streak'] === 2, 'Exhausted freezes break but preserve longest');
    gamification_check(Streak::set_timezone($u, 'America/New_York') === true, 'Timezone can change after a broken streak');
    gamification_check(Streak::record($u, 'practice', 'session-1') === false, 'Old activity remains deduplicated after timezone change');
    gamification_check(Streak::record($u, 'practice', 'restart') === true && Streak::snapshot($u)['current_streak'] === 1, 'Restart count');
    for ($i = 9; $i <= 14; $i++) { $clock = strtotime("2026-01-$i 12:00:00 UTC"); Streak::record($u, 'practice', 'streak-' . $i); }
    gamification_check(Streak::snapshot($u)['current_streak'] === 7 && Streak::snapshot($u)['longest_streak'] === 7, 'Milestone reached');
    gamification_check(Achievements::achievement_exists($u, 'badge', null, null, null, 'streak-7'), 'Milestone badge persisted');
    delete_transient('badge_added_for_user_' . $u);
    Streak::deliver($u); Streak::snapshot($u);
    gamification_check(!get_transient('badge_added_for_user_' . $u), 'Refreshing cannot replay milestone celebration');
    gamification_check((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ohmylms_user_achievement WHERE user_id=%d AND badge_id='streak-7'", $u)) === 1, 'Milestone awarded once across refreshes');
    update_option('ohmylms_streak_settings', array_replace(StreakSettings::get(), ['enable' => false]));
    gamification_check(Streak::record($u, 'lesson', 'disabled') === false, 'Disabled streak does not write');
    update_option('ohmylms_streak_settings', array_replace(StreakSettings::get(), ['enable' => true]));

    // Conditions use real persisted lessons, the target learner and all three dimensions.
    $lesson = wp_insert_post(['post_type' => OHMYLMS_LESSON_CPT, 'post_title' => 'Gamification lesson fixture', 'post_status' => 'publish']); $posts[] = $lesson;
    $wpdb->insert($wpdb->prefix . 'ohmylms_user_enrollment', ['user_id' => $u, 'course_id' => 2147483646, 'status' => 'enrolled', 'progress' => 'running', 'start_date' => current_time('mysql')]);
    $enrollment = (int) $wpdb->insert_id; $enrollments[] = $enrollment;
    $wpdb->insert($wpdb->prefix . 'ohmylms_user_progress', ['enrollment_id' => $enrollment, 'content_id' => $lesson, 'content_type' => 'text', 'status' => 'completed', 'start_date' => current_time('mysql')]);
    $progress = (int) $wpdb->insert_id;
    $rule = ['dataValue' => 'completed_lesson', 'compareSign' => '>=', 'compareData' => 1];
    update_option('ohmylms_badges', [['slug' => 'lesson-fixture', 'name' => 'Lesson fixture', 'rules' => [$rule]]]);
    update_option('ohmylms_levels', [['slug' => 'lesson-level', 'name' => 'Lesson level', 'rules' => [$rule]], ['slug' => 'next-lesson', 'name' => 'Next', 'rules' => [array_replace($rule, ['compareData' => 2])]]]);
    wp_set_current_user($users['teacher']);
    gamification_check(in_array('lesson-fixture', Badge::maybe_met_rules($u), true) && Badge::maybe_met_rules($users['teacher']) === [], 'Lesson badge uses target learner instead of teacher or points');
    gamification_check(in_array('lesson-level', Level::maybe_met_rules($u), true), 'Lesson level has real counts');
    (new \OhMyLMS\Hooks\EngagementHook())->after_point_added($u, 0);
    gamification_check(Level::get_current_level_of_a_user($u)['level_id'] === 'lesson-level' && !Level::get_current_level_of_a_user($users['teacher']), 'Background/teacher level targets correct user');
    gamification_check(Level::get_next_level_of_a_user($u)['progress_percentage'] === 50.0, 'Next-level progress uses lesson count');
    wp_set_current_user($u); $student = new \OhMyLMS\Data\Student($u);
    ob_start(); include OHMYLMS_DIR . '/templates/profile/profile.php'; $profile = ob_get_clean();
    gamification_check(strpos($profile, 'Completed lessons: 1') !== false && strpos($profile, '--fill-width: 50%') !== false, 'Profile renders lesson progress instead of point-only progress');
    wp_set_current_user($users['teacher']);
    gamification_check(!Rules::met([], $u) && !Rules::met([['dataValue' => 'unknown', 'compareSign' => '>=', 'compareData' => 0]], $u), 'Empty or unsupported rules fail closed');
    $clock = strtotime('2026-01-15 12:00:00 UTC');
    update_option('ohmylms_point_settings', ['feature_enabled' => false, 'rules' => []]);
    StreakHooks::lesson($progress, $lesson, 2147483646, $u);
    gamification_check(Streak::snapshot($u)['today_complete'], 'Verified lesson counts with points disabled');
    $wrong = Streak::snapshot($users['other']);
    StreakHooks::lesson($progress, $lesson, 2147483646, $users['other']);
    gamification_check(Streak::snapshot($users['other'])['current_streak'] === $wrong['current_streak'], 'Lesson identity cannot be forged');
    update_option('ohmylms_point_settings', ['feature_enabled' => true, 'rules' => []]);
    update_option('ohmylms_reward_settings', ['feature_enabled' => true, 'rules' => [['slug' => 'purchase_course', 'value' => true]]]);
    $reward_course = wp_insert_post(['post_type' => OHMYLMS_COURSE_CPT, 'post_title' => 'Point reward fixture', 'post_status' => 'publish']); $posts[] = $reward_course;
    update_post_meta($reward_course, '_purchase_point', 5);
    $point_cart = [['course_id' => $reward_course, 'purchase_by' => 'point']];
    gamification_check(\OhMyLMS\Engagement\Reward::valid_point_cart($point_cart), 'Eligible point-only course cart');
    gamification_check(!\OhMyLMS\Engagement\Reward::valid_point_cart(array_merge($point_cart, [['course_id' => $reward_course, 'purchase_by' => 'cash']])), 'Mixed cart cannot make cash items free');
    gamification_check(!\OhMyLMS\Engagement\Reward::valid_point_cart($point_cart, 123), 'Membership cannot bypass payment using course points');
    update_post_meta($reward_course, '_reward_disabled', 'yes');
    gamification_check(!\OhMyLMS\Engagement\Reward::valid_point_cart($point_cart), 'Per-course reward switch is enforced on server');
    gamification_check(Point::add_points($u, 'point', 10, 'fixture') === 1 && !Point::add_points($u, 'point', 10, 'fixture'), 'One-time point deduplication');
    gamification_check(Point::deduct_points($u, 'point', 7, 'purchase_course', null, null, 101) === 1, 'First order spends points');
    gamification_check(!Point::deduct_points($u, 'point', 7, 'purchase_course', null, null, 102), 'Atomic balance check prevents overspending');
    gamification_check(Point::deduct_points($u, 'point', 2, 'purchase_course', null, null, 102) === 1, 'Different orders have distinct spend identities');
    gamification_check(!Point::deduct_points($u, 'point', 1, 'purchase_course', null, null, 102), 'Order cannot spend twice');
    update_option('ohmylms_point_settings', ['feature_enabled' => false]);
    gamification_check(Point::refund_purchase($u, 102) === 1 && Point::get_total_points($u) === 3, 'Failed point order refunds actual debit even with earning disabled');
    gamification_check(!Point::refund_purchase($u, 102) && !Point::refund_purchase($u, 999), 'Refund identity is one-time and requires a real debit');
    update_option('ohmylms_point_settings', ['feature_enabled' => true, 'rules' => []]);
    gamification_check(!Badge::add_badge($u, 'badge', 'lesson-fixture') && !Level::add_level($u, 'level', 'lesson-level'), 'Duplicate awards return real false result');
    gamification_check(!Badge::add_badge($u, 'badge', '') && !Level::add_level($u, 'level', '') && !Badge::add_badge(0, 'badge', 'lesson-fixture'), 'Invalid award targets and identifiers rejected');

    // Persistence hooks, not point history or browser-provided learner IDs, qualify activity.
    $other = $users['other'];
    $wpdb->insert($wpdb->prefix . 'ohmylms_quiz_attempts', ['quiz_id' => 0, 'student_id' => $other, 'course_id' => 0, 'total' => 0, 'status' => 'completed', 'start_date' => current_time('mysql'), 'end_date' => current_time('mysql')]);
    $attempt = (int) $wpdb->insert_id; $attempts[] = $attempt;
    $wpdb->insert($wpdb->prefix . 'ohmylms_quiz_attempts_answers', ['quiz_id' => 0, 'student_id' => $other, 'question_id' => 0, 'quiz_attempt_id' => $attempt, 'given_answer' => serialize([]), 'question_marks' => 1, 'achive_mark' => 0, 'minus_mark' => 0, 'is_correct' => 0]);
    $answer_id = (int) $wpdb->insert_id;
    StreakHooks::quiz(['attempt_id' => $attempt, 'student_id' => $users['teacher'], 'reason' => 'submit']);
    gamification_check(Streak::snapshot($other)['current_streak'] === 0, 'Empty quiz submissions do not count');
    $wpdb->update($wpdb->prefix . 'ohmylms_quiz_attempts_answers', ['given_answer' => serialize('Incorrect but attempted')], ['id' => $answer_id]);
    StreakHooks::quiz(['attempt_id' => $attempt, 'reason' => 'exit']);
    gamification_check(Streak::snapshot($other)['current_streak'] === 0, 'Exiting a quiz is not submission');
    StreakHooks::quiz(['attempt_id' => $attempt, 'student_id' => $users['teacher'], 'reason' => 'submit']);
    gamification_check(Streak::snapshot($other)['current_streak'] === 1 && Streak::snapshot($users['teacher'])['current_streak'] === 0, 'Wrong answers count for persisted attempt owner');

    $clock = strtotime('2026-01-16 12:00:00 UTC');
    $uuid = wp_generate_uuid4();
    $wpdb->insert(\OhMyLMS\Assessment\Schema::table('practice_sessions'), ['uuid' => $uuid, 'student_id' => $other, 'guest_id' => 0, 'mode' => 'skill', 'term_id' => 0, 'course_id' => 0, 'policy' => '{}', 'status' => 'active', 'item_limit' => 3, 'started_at' => Streak::now()]);
    $session = (int) $wpdb->insert_id; $practice_sessions[] = $session;
    for ($position = 1; $position <= 3; $position++) {
        $wpdb->insert(\OhMyLMS\Assessment\Schema::table('practice_items'), ['session_id' => $session, 'position' => $position, 'question_id' => 0, 'question_uuid' => wp_generate_uuid4(), 'version_id' => 0, 'option_order' => '[]', 'display' => '{}', 'response' => $position === 3 ? 'null' : '"wrong"', 'answered_at' => Streak::now(), 'correct' => 0]);
    }
    StreakHooks::practice($uuid);
    gamification_check(!Streak::snapshot($other)['today_complete'], 'Individual answers are not completed sessions');
    $wpdb->update(\OhMyLMS\Assessment\Schema::table('practice_sessions'), ['status' => 'complete', 'completed_at' => Streak::now()], ['id' => $session]);
    StreakHooks::practice($uuid);
    gamification_check(!Streak::snapshot($other)['today_complete'], 'Empty answers do not satisfy practice minimum');
    $wpdb->update(\OhMyLMS\Assessment\Schema::table('practice_items'), ['response' => '"still wrong"'], ['session_id' => $session, 'position' => 3]);
    StreakHooks::practice($uuid);
    gamification_check(Streak::snapshot($other)['today_complete'] && Streak::snapshot($other)['current_streak'] === 2, 'Completed session with three meaningful wrong answers counts');
    StreakHooks::practice($uuid);
    gamification_check(Streak::snapshot($other)['current_streak'] === 2, 'Practice completion hook is idempotent');
    $clock = strtotime('2026-01-17 12:00:00 UTC');
    StreakHooks::practice($uuid);
    gamification_check(!Streak::snapshot($other)['today_complete'], 'Old completed session cannot count again on a following date');

    update_option('ohmylms_streak_settings', array_replace(StreakSettings::get(), ['milestones' => [['days' => 1, 'badge' => '', 'points' => 3]]]));
    update_option('ohmylms_point_settings', ['feature_enabled' => false, 'rules' => []]);
    Streak::record($users['teacher'], 'practice', 'pending-milestone');
    gamification_check(Point::get_total_points($users['teacher']) === 0, 'Disabled points leave milestone reward pending');
    update_option('ohmylms_point_settings', ['feature_enabled' => true, 'rules' => []]);
    Streak::snapshot($users['teacher']); Streak::snapshot($users['teacher']);
    gamification_check(Point::get_total_points($users['teacher']) === 3, 'Pending points retry once when feature is enabled');

    delete_transient('badge_added_for_user_' . $other);
    $awarded = 0;
    $watch = static function () use (&$awarded) { $awarded++; };
    add_action('ohmylms_after_badge_added', $watch);
    $break_write = static function ($query) use ($wpdb) { return strpos($query, 'INSERT INTO `' . $wpdb->prefix . 'ohmylms_user_achievement`') === 0 ? 'INVALID GAMIFICATION WRITE' : $query; };
    $suppressed = $wpdb->suppress_errors(true); add_filter('query', $break_write);
    $failed = Badge::add_badge($other, 'badge', 'failed-award');
    remove_filter('query', $break_write); $wpdb->suppress_errors($suppressed); remove_action('ohmylms_after_badge_added', $watch);
    gamification_check(!$failed && $awarded === 0 && !get_transient('badge_added_for_user_' . $other), 'Failed persistence cannot announce success');

    update_option('ohmylms_badge_settings', ['feature_enabled' => false]);
    update_option('ohmylms_level_settings', ['feature_enabled' => false]);
    gamification_check(Badge::maybe_met_rules($u) === [] && Level::maybe_met_rules($u) === false, 'Explicit badge and level switches disable evaluation');

    $commands = [];
    for ($i = 0; $i < 2; $i++) {
        $process = proc_open([PHP_BINARY, '-c', php_ini_loaded_file(), __FILE__, $argv[1], '--worker', (string) $users['concurrent'], (string) $clock], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        gamification_check(is_resource($process), 'Concurrent worker started'); $commands[] = [$process, $pipes];
    }
    foreach ($commands as [$process, $pipes]) { fclose($pipes[0]); stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); gamification_check(proc_close($process) === 0, 'Concurrent worker succeeded'); }
    gamification_check(Streak::snapshot($users['concurrent'])['current_streak'] === 1, 'Concurrent requests increment once');
    gamification_check((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . StreakSchema::table('activities') . ' WHERE user_id=%d', $users['concurrent'])) === 1, 'Concurrent activity identity persisted once');
    $clock = strtotime('2026-01-18 12:00:00 UTC'); $commands = [];
    for ($i = 0; $i < 2; $i++) {
        $process = proc_open([PHP_BINARY, '-c', php_ini_loaded_file(), __FILE__, $argv[1], '--worker', (string) $users['concurrent'], (string) $clock, 'different-session-' . $i], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $commands[] = [$process, $pipes];
    }
    foreach ($commands as [$process, $pipes]) { fclose($pipes[0]); stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); gamification_check(proc_close($process) === 0, 'Different-source worker succeeded'); }
    gamification_check(Streak::snapshot($users['concurrent'])['current_streak'] === 2, 'Concurrent different activities increment one following date');
    gamification_check((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . StreakSchema::table('activities') . ' WHERE user_id=%d', $users['concurrent'])) === 3, 'Separate completed sessions retain separate ledger identities');
    echo "$checks gamification/streak integration checks passed.\n";
} finally {
    foreach ($practice_sessions as $id) { $wpdb->delete(\OhMyLMS\Assessment\Schema::table('practice_items'), ['session_id' => $id]); $wpdb->delete(\OhMyLMS\Assessment\Schema::table('practice_sessions'), ['id' => $id]); }
    foreach ($attempts as $id) { $wpdb->delete($wpdb->prefix . 'ohmylms_quiz_attempts_answers', ['quiz_attempt_id' => $id]); $wpdb->delete($wpdb->prefix . 'ohmylms_quiz_attempts', ['id' => $id]); }
    foreach ($enrollments as $id) { $wpdb->delete($wpdb->prefix . 'ohmylms_user_progress', ['enrollment_id' => $id]); $wpdb->delete($wpdb->prefix . 'ohmylms_user_enrollment', ['id' => $id]); }
    foreach ($posts as $id) { wp_delete_post($id, true); }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($users as $id) {
        foreach (['state', 'days', 'activities', 'milestones'] as $table) { $wpdb->delete(StreakSchema::table($table), ['user_id' => $id]); }
        $wpdb->delete($wpdb->prefix . 'ohmylms_user_achievement', ['user_id' => $id]); wp_delete_user($id);
    }
    foreach ($original as $name => $value) { if ($value === null) { delete_option($name); } else { update_option($name, $value); } }
}
