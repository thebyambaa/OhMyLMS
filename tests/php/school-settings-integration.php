<?php
// Per-school design overrides: authorization, contrast rule, inheritance. Run: see docs in memory/test notes.
if (PHP_SAPI !== 'cli') { exit; }
$config = json_decode(file_get_contents(getenv('OHMYLMS_TEST_CREDENTIALS')), true);
require $config['site'] . '/wp-load.php';
if (!defined('OHMYLMS_TEST_SITE') || DB_NAME !== 'ohmylms_source_test') { throw new RuntimeException('Requires disposable test site'); }

use OhMyLMS\Schools\Schema;
use OhMyLMS\Schools\Service;
use OhMyLMS\Schools\Settings;
use OhMyLMS\Student\Hub;
use OhMyLMS\Student\SchoolActions;

function ss_check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } echo "ok - $message\n"; }
function ss_throws($callback, $code, $message) {
    try { $callback(); } catch (RuntimeException $e) { ss_check($e->getCode() === $code, $message); return; }
    throw new RuntimeException("Expected failure: $message");
}

$suffix = strtolower(wp_generate_password(8, false)); $users = []; $schools = [];
try {
    Schema::install();
    foreach (['admin_a', 'admin_b'] as $role) {
        $id = wp_insert_user(['user_login' => "ss-$role-$suffix", 'user_email' => "$role-$suffix@example.invalid", 'user_pass' => wp_generate_password(30), 'role' => 'subscriber']);
        ss_check(!is_wp_error($id), "fixture $role"); $users[$role] = $id;
    }
    wp_set_current_user(get_user_by('login', $config['username'])->ID);
    foreach (['a', 'b'] as $key) {
        $schools[$key] = Service::create_school(['name' => "Settings $key $suffix"])['id'];
        Service::membership('school_memberships', ['school_id' => $schools[$key], 'user_id' => $users["admin_$key"], 'role' => 'school_admin']);
    }
    $site = Hub::settings()['accent'];

    ss_check(abs(Settings::contrast_with_white('#ffffff') - 1.0) < 0.01, 'white on white is 1:1');
    ss_check(abs(Settings::contrast_with_white('#000') - 21.0) < 0.1, 'white on black is 21:1');
    ss_check(Settings::get('accent', $schools['a']) === $site, 'school inherits site accent');

    wp_set_current_user($users['admin_a']);
    ss_throws(function () use ($schools) { Settings::save($schools['b'], ['accent' => '#112233']); }, 403, 'admin cannot change another school');
    ss_throws(function () use ($schools) { Settings::save($schools['a'], ['accent' => '#ffee00']); }, 400, 'too-light colour rejected');
    ss_throws(function () use ($schools) { Settings::save($schools['a'], ['accent' => 'red']); }, 400, 'non-hex colour rejected');

    Settings::save($schools['a'], ['accent' => '#112233', 'logo_id' => 999999]);
    ss_check(Settings::get('accent', $schools['a']) === '#112233', 'school accent saved');
    ss_check(!isset(Settings::overrides($schools['a'])['logo']), 'non-image logo id ignored');
    ss_check(Settings::get('accent', $schools['b']) === $site, 'other school unaffected');

    $flash = SchoolActions::apply('update_design', $schools['a'], ['accent' => '#334455']);
    ss_check(Settings::get('accent', $schools['a']) === '#334455' && !empty($flash['message']), 'workspace action saves design');

    Settings::save($schools['a'], ['accent' => '#334455', 'reset_accent' => '1']);
    ss_check(Settings::get('accent', $schools['a']) === $site && get_option(Settings::OPTION . $schools['a']) === false, 'reset returns to site colour and clears record');

    wp_set_current_user($users['admin_b']);
    ss_check(Settings::school_for_user() === $schools['b'], 'member resolves to own school');
    echo "PASS\n";
} finally {
    global $wpdb;
    foreach ($schools as $id) {
        delete_option(Settings::OPTION . $id);
        foreach (['school_memberships', 'school_audit_log'] as $table) { $wpdb->delete(Schema::table($table), ['school_id' => $id]); }
        $wpdb->delete(Schema::table('schools'), ['id' => $id]);
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($users as $id) { wp_delete_user($id); }
}
