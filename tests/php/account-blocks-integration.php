<?php
/** Disposable WordPress integration checks using connection-local tables. */
if (PHP_SAPI !== 'cli') { exit; }
$config = json_decode(file_get_contents(getenv('OHMYLMS_TEST_CREDENTIALS')), true);
$GLOBALS['wp_filter']['pre_wp_mail'][10][] = ['function' => function () { return true; }, 'accepted_args' => 2];
require $config['site'] . '/wp-load.php';
if (DB_NAME !== 'ohmylms_source_test' || (!defined('OHMYLMS_TEST_SITE') && !defined('OMLMS_TEST_SITE'))) { throw new RuntimeException('Requires disposable test site'); }
use OhMyLMS\Schools\Schema;
use OhMyLMS\Schools\Views;
function account_check($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
    $GLOBALS['account_checks']++;
}
function account_register($data, $nonce = true) {
    $request = new WP_REST_Request('POST', '/ohmylms/v1/school/register');
    if ($nonce) { $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest')); }
    $request->set_body_params($data);
    return rest_do_request($request);
}
$GLOBALS['account_checks'] = 0;
$original_prefix = $wpdb->prefix;
$prefix = 'acctest_' . bin2hex(random_bytes(6)) . '_';
$tables = ['users', 'usermeta', 'options', 'posts', 'postmeta', 'ohmylms_school_audit_log', 'ohmylms_school_memberships', 'ohmylms_class_memberships', 'ohmylms_guardian_links', 'ohmylms_user_enrollment'];
foreach ($tables as $table) {
    account_check(false !== $wpdb->query("CREATE TEMPORARY TABLE `{$prefix}{$table}` LIKE `{$original_prefix}{$table}`"), 'Cannot isolate ' . $table);
}
$wpdb->query("INSERT INTO `{$prefix}options` SELECT * FROM `{$original_prefix}options`");
$wpdb->set_prefix($prefix);
wp_cache_flush();
wp_set_current_user(0);
// Unrelated integrations must not send emails or enroll fixture identities.
remove_all_actions('user_register');
try {
    delete_transient('ohmylms_school_signup_' . hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] ?? 'local', wp_salt('nonce')));
    foreach (['student', 'teacher', 'parent'] as $role) {
        account_check(WP_Block_Type_Registry::get_instance()->is_registered('ohmylms/' . $role . '-registration'), $role . ' block missing');
        $html = do_blocks('<!-- wp:ohmylms/' . $role . '-registration /-->');
        account_check(strpos($html, 'Continue to your learning hub') !== false && strpos($html, 'data-ohmylms-school-view') === false, $role . ' legacy block did not forward to the hub');
        $data = ['role' => $role, 'first_name' => 'Block', 'email' => $role . '-block@example.invalid', 'password' => 'Account-block-test-password-42'];
        $response = account_register($data);
        account_check(200 === $response->get_status(), $role . ' registration failed: ' . wp_json_encode($response->get_data()));
        $user = get_user_by('email', $data['email']);
        $expected = ['student' => ohmylms_get_assignable_student_role(), 'teacher' => 'ohmylms_teacher', 'parent' => 'ohmylms_parent'][$role];
        account_check($user && in_array($expected, $user->roles, true), $role . ' assigned incorrectly');
        account_check(!user_can($user, 'manage_options'), 'Public account elevated to administrator');
        $authenticated = wp_authenticate($data['email'], $data['password']);
        account_check(!is_wp_error($authenticated) && $authenticated->ID === $user->ID, 'Email sign-in failed');
        account_check(400 === account_register($data)->get_status(), 'Duplicate email accepted');
    }
    foreach (['school_memberships', 'class_memberships', 'guardian_links', 'user_enrollment'] as $table) {
        account_check(0 === (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Schema::table($table)), 'Registration granted ' . $table);
    }
    account_check(400 === account_register(['role' => 'administrator'])->get_status(), 'Arbitrary role accepted');
    account_check(403 === account_register(['role' => 'teacher'], false)->get_status(), 'Missing nonce accepted');
    account_check(400 === account_register(['role' => 'teacher', 'website' => 'spam'])->get_status(), 'Honeypot bypassed');
    account_check(400 === account_register(['role' => 'teacher', 'email' => 'weak@example.invalid', 'password' => 'short'])->get_status(), 'Weak password accepted');
    account_check(WP_Block_Type_Registry::get_instance()->is_registered('ohmylms/sign-in'), 'Sign-in block missing');
    $login = do_blocks('<!-- wp:ohmylms/sign-in /-->');
    account_check(strpos($login, '<a href=') !== false && strpos($login, 'name="pwd"') === false, 'Legacy login block did not forward to sign-in');
    account_check(strpos(Views::render('sign-in'), 'data-ohmylms-school-view') === false, 'Repeated login blocks still mount the retired app');
    wp_set_current_user(get_user_by('email', 'teacher-block@example.invalid')->ID);
    account_check(strpos(Views::render('sign-in'), 'name="pwd"') === false, 'Logged-in visitor sees login form');
    account_check(400 === account_register(['role' => 'parent'])->get_status(), 'Logged-in signup accepted');
    echo $GLOBALS['account_checks'] . " account block integration checks passed.\n";
} finally {
    wp_set_current_user(0);
    $wpdb->set_prefix($original_prefix);
    wp_cache_flush();
}
