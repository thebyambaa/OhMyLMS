<?php
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__, 4) . '/wp-load.php';
function prefix_check($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}
// Membership plans are served by ohmylms/v1/memberships (Rest\V1\MembershipController, commit 7b471320); the
// retired ohmylms/v1/membership routes are gone. Do not let the shutdown course sync touch other plans.
remove_action('shutdown', [\OhMyLMS\Membership\CourseSelection::class, 'flush']);
function prefix_request($method, $path, array $body = []) {
    $request = new WP_REST_Request($method, '/ohmylms/v1/memberships' . $path);
    if ($body) {
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(wp_json_encode($body));
    }
    return rest_do_request($request);
}
$admins = get_users(['role' => 'administrator', 'number' => 1]);
prefix_check(!empty($admins), 'An administrator is needed for the integration test.');
wp_set_current_user($admins[0]->ID);
$id = 0;
try {
    $response = prefix_request('POST', '', ['name' => 'OhMyLMS prefix regression test', 'status' => 'draft']);
    prefix_check($response->get_status() === 201, 'Membership create failed: ' . wp_json_encode($response->get_data()));
    $id = $response->get_data()['id'];
    prefix_check(get_post_type($id) === 'ohmylms-membership', 'Incorrect membership post type.');
    prefix_check(prefix_request('GET', '/' . $id)->get_status() === 200, 'Membership read failed.');
    $response = prefix_request('PUT', '/' . $id, ['name' => 'OhMyLMS prefix regression updated', 'regular_price' => '100']);
    prefix_check($response->get_status() === 200, 'Membership update failed: ' . wp_json_encode($response->get_data()));
    prefix_check(get_the_title($id) === 'OhMyLMS prefix regression updated', 'Membership name not persisted.');
    prefix_check((float) ohmylms_get_membership($id)->get_regular_price() === 100.0, 'Membership price not persisted.');
    prefix_check(prefix_request('GET', '')->get_status() === 200, 'Membership collection failed.');
    prefix_check(rest_do_request(new WP_REST_Request('GET', '/ohmylms/v1/membership'))->get_status() === 404, 'The retired ohmylms/v1/membership route still answers.');
    wp_set_current_user(0);
    prefix_check(prefix_request('PUT', '/' . $id, ['name' => 'Anonymous'])->get_status() >= 400, 'Anonymous edit was accepted.');
    prefix_check(get_the_title($id) === 'OhMyLMS prefix regression updated', 'Anonymous edit changed the plan.');
    echo "10 live membership API checks passed.\n";
} finally {
    if ($id) { wp_delete_post($id, true); }
}
