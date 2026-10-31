<?php
/**
 * Disposable-site checks for the sample school through the Account Hub routes and View as.
 *
 * Requires OHMYLMS_TEST_CREDENTIALS and the isolated ohmylms_source_test database; refuses anything else.
 * The 800.local suite tests/php/account-hub-schools-lifecycle-rest-integration.php covers the same routes.
 */
if (PHP_SAPI !== 'cli') { exit; }
$config = json_decode(file_get_contents(getenv('OHMYLMS_TEST_CREDENTIALS')), true);
require $config['site'] . '/wp-load.php';
if (!defined('OHMYLMS_TEST_SITE') || DB_NAME !== 'ohmylms_source_test') { throw new RuntimeException('Requires disposable test site'); }

use OhMyLMS\Schools\SamplePreview;
use OhMyLMS\Schools\Schema;

function sample_check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function sample_request($method, $path, $params = [], $expected = 200) {
    $request = new WP_REST_Request($method, '/ohmylms/v1/' . $path);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    if ($method === 'GET' || $method === 'DELETE') { $request->set_query_params($params); } else { $request->set_body_params($params); }
    $response = rest_do_request($request);
    sample_check($response->get_status() === $expected, "$method $path: expected $expected, got " . $response->get_status() . ' ' . wp_json_encode($response->get_data()));
    return $response->get_data();
}

$admin = get_user_by('login', $config['username']);
$outsider = wp_insert_user(['user_login' => 'sample-outsider-' . strtolower(wp_generate_password(8, false)), 'user_email' => 'outsider@example.invalid', 'user_pass' => wp_generate_password(30), 'role' => 'subscriber']);
try {
    Schema::install();
    wp_set_current_user($outsider);
    sample_request('POST', 'account-hub/sample-school', [], 403);
    sample_request('DELETE', 'account-hub/sample-school', [], 403);
    wp_set_current_user($admin->ID);

    $first = sample_request('POST', 'account-hub/sample-school', [], 201);
    sample_check($first['complete'] && count($first['people']) === 9 && count($first['classes']) === 2, 'Create did not build every sample class and person');
    $second = sample_request('POST', 'account-hub/sample-school');
    sample_check($second === $first, 'A second create changed the sample data');
    sample_check(count(get_users(['meta_key' => SamplePreview::META, 'fields' => 'ID'])) === 9, 'A second create duplicated accounts');
    foreach ($first['people'] as $person) {
        $user = get_userdata($person['id']);
        sample_check(!wp_check_password('', $user->user_pass, $user->ID) && str_ends_with($user->user_email, '@example.invalid'), "Sample {$person['key']} is signable");
    }
    foreach (['student', 'teacher', 'parent', 'school'] as $role) {
        $default = sample_request('GET', 'school/view-as/default', ['role' => $role]);
        sample_check(!empty($default['person']), "No default person for $role");
    }
    $teacher = array_values(array_filter($first['people'], fn($p) => $p['key'] === 'teacher'))[0]['id'];
    $removed = sample_request('DELETE', 'account-hub/sample-school');
    sample_check($removed['removed'] && !get_userdata($teacher) && get_userdata($outsider), 'Removal did not stop at the marked records');
    sample_check(!sample_request('GET', 'account-hub/sample-school')['exists'], 'Sample data survived removal');
    echo "Sample preview integration checks passed.\n";
} finally {
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($outsider);
    if (SamplePreview::status()['exists']) { SamplePreview::remove(true); }
}
