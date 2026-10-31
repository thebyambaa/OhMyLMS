<?php
// Standalone regression checks: php tests/membership-permissions.php
// Every membership route is gated by MembershipController::CAPABILITY; missing plans answer 404 in the handlers.
class WP_REST_Controller {}
class WP_Error {
    public $code;
    public $data;
    public function __construct($code, $message, $data) { $this->code = $code; $this->data = $data; }
}
function __($text, $domain) { return $text; }
function current_user_can($capability) { return in_array($capability, $GLOBALS['caps'], true); }
function rest_authorization_required_code() { return 403; }
require dirname(__DIR__) . '/includes/Abstracts/RestController.php';
require dirname(__DIR__) . '/includes/Rest/V1/MembershipController.php';
function verify($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}
$controller = new OhMyLMS\Rest\V1\MembershipController();
$checks = [
    'get_items' => 'ohmylms_membership_cannot_view',
    'get_item' => 'ohmylms_membership_cannot_view',
    'create_item' => 'ohmylms_membership_cannot_create',
    'update_item' => 'ohmylms_membership_cannot_edit',
    'delete_item' => 'ohmylms_membership_cannot_delete',
];
$count = 0;
foreach ([['edit_posts'], ['edit_posts', 'read_post', 'edit_post', 'delete_post']] as $caps) {
    $GLOBALS['caps'] = $caps;
    foreach ($checks as $action => $code) {
        $result = $controller->{$action . '_permissions_check'}(['id' => 7]);
        verify($result instanceof WP_Error && $result->code === $code && $result->data['status'] === 403, "Post-level capabilities alone allowed $action");
        $count++;
    }
}
$GLOBALS['caps'] = [OhMyLMS\Rest\V1\MembershipController::CAPABILITY];
foreach (array_keys($checks) as $action) {
    verify($controller->{$action . '_permissions_check'}(['id' => 7]) === true, "Authorized $action failed");
    $count++;
}
echo "$count membership permission checks passed.\n";
