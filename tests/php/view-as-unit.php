<?php
/** Standalone session/security tests: no database or live account mutations. */
namespace OhMyLMS\Schools {
    function setcookie($name, $value, $options) { $GLOBALS['cookie_writes'][$name] = [$value, $options]; return true; }
    final class Schema { public static function table($name) { return $name; } }
    final class Service {
        public static function need($allowed, $message) { if (!$allowed) { throw new \RuntimeException($message, 400); } }
        public static function text($text) { return $text; }
        public static function row($table, $id) { return $id === 10 ? ['id' => 10, 'school_id' => 1] : false; }
        public static function audit($school, $action, $id) { $GLOBALS['audit'][] = [\get_current_user_id(), $action, $id]; }
    }
    final class Views { public static function portal_url() { return 'https://example.test/?ohmylms_portal=1'; } }
}
namespace {
    define('ABSPATH', __DIR__); define('COOKIEHASH', 'test'); define('HOUR_IN_SECONDS', 3600); define('OHMYLMS_COURSE_CPT', 'course'); define('OHMYLMS_SLUG', 'ohmylms');
    $current = 1; $session_token = 'admin-session'; $cookie_writes = []; $transients = []; $audit = []; $clears = 0; $auth = null; $routes = [];
    $users = [1 => (object) ['ID' => 1, 'display_name' => 'Admin', 'user_email' => 'admin@example.test', 'roles' => ['administrator']], 2 => (object) ['ID' => 2, 'display_name' => 'Student', 'user_email' => 'student@example.test', 'roles' => ['subscriber']], 3 => (object) ['ID' => 3, 'display_name' => 'Teacher', 'user_email' => 'teacher@example.test', 'roles' => ['ohmylms_teacher']], 4 => (object) ['ID' => 4, 'display_name' => 'Parent', 'user_email' => 'parent@example.test', 'roles' => ['ohmylms_parent']], 5 => (object) ['ID' => 5, 'display_name' => 'School admin', 'user_email' => 'school@example.test', 'roles' => ['ohmylms_school_admin']], 6 => (object) ['ID' => 6, 'display_name' => 'Newer student', 'user_email' => 'student2@example.test', 'roles' => ['subscriber']]];
    $usermeta = [6 => ['_ohmylms_last_login' => '2026-01-02 10:00:00'], 2 => ['_ohmylms_last_login' => '2026-03-01 09:00:00']];
    function get_user_meta($id, $key, $single = false) { return $GLOBALS['usermeta'][$id][$key] ?? ''; }
    function update_user_meta($id, $key, $value) { $GLOBALS['usermeta'][$id][$key] = $value; return true; }
    function get_current_user_id() { return $GLOBALS['current']; }
    function wp_get_session_token() { return $GLOBALS['session_token']; }
    function wp_set_current_user($id) { $GLOBALS['current'] = $id; }
    function user_can($user, $cap) { return (is_object($user) ? $user->ID : $user) === 1; }
    function current_user_can($cap, ...$args) { return user_can(get_current_user_id(), $cap); }
    function get_userdata($id) { return $GLOBALS['users'][$id] ?? false; }
    function is_user_logged_in() { return get_current_user_id() > 0; }
    function wp_verify_nonce($nonce, $action) { return $nonce === 'valid'; }
    function register_rest_route($namespace, $route, $args) { $GLOBALS['routes'][$route] = $args; }
    function sanitize_key($s) { return strtolower((string) $s); }
    function absint($n) { return abs((int) $n); }
    function is_ssl() { return true; }
    function get_users($args) { return array_keys(array_filter($GLOBALS['users'], fn($u) => (bool) array_intersect($u->roles, $args['role__in']))); }
    function get_post($id) { return $id === 20 ? (object) ['post_type' => 'course', 'post_author' => 3] : false; }
    function get_permalink($id) { return "https://example.test/course/$id"; }
    function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
    function add_query_arg($args, $value, $url = null) { if ($url !== null) { $args = [$args => $value]; } else { $url = $value; } return $url . '&' . http_build_query($args); }
    function wp_generate_password(...$args) { return str_repeat('a', 64); }
    function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key] = $value; }
    function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
    function delete_transient($key) { unset($GLOBALS['transients'][$key]); }
    function wp_clear_auth_cookie() { $GLOBALS['clears']++; }
    function wp_set_auth_cookie($id, $remember, $secure, $token) { $GLOBALS['auth'] = [$id, $token]; }
    final class WP_Error { public function __construct(public $code, public $message, public $data) {} }
    final class WP_User_Query {
        public function __construct(private $args) {}
        public function get_results() { return array_values(array_filter($GLOBALS['users'], fn($u) => in_array($u->ID, $this->args['include'], true))); }
    }
    final class WP_Session_Tokens {
        public static $tokens = [1 => ['admin-session' => true]];
        public function __construct(private $id) {}
        public static function get_instance($id) { return new self($id); }
        public function verify($token) { return isset(self::$tokens[$this->id][$token]); }
        public function create($expires) { self::$tokens[$this->id]['target-session'] = true; return 'target-session'; }
        public function destroy($token) { unset(self::$tokens[$this->id][$token]); }
    }
    $wpdb = new class {
        public function prepare($query, ...$args) { return [$query, $args]; }
        public function get_col($query) {
            if (is_array($query)) {
                [$sql, $args] = $query;
                if (str_contains($sql, 'class_memberships')) { return $args[1] === 'teacher' ? [3] : [2]; }
                if (str_contains($sql, 'user_enrollment')) { return [2]; }
                if (str_contains($sql, 'school_memberships')) { return []; }
            }
            return [4];
        }
    };
    require __DIR__ . '/../../includes/Schools/Access.php';
    require __DIR__ . '/../../includes/Schools/ViewAs.php';
    require __DIR__ . '/../../includes/Admin/AppPage.php';
    require __DIR__ . '/../../includes/Admin/LegacyRoutes.php';
    use OhMyLMS\Schools\ViewAs;
    function check($value, $message) { if (!$value) { throw new \RuntimeException($message); } }
    function denied($call, $message) { try { $call(); } catch (\RuntimeException $e) { check(in_array($e->getCode(), [400, 403]), $message); return; } throw new \RuntimeException($message); }
    $request = new class { public $nonce = 'valid'; public function get_header($key) { return $this->nonce; } };
    ViewAs::register();
    check($routes['/school/view-as/start']['permission_callback']($request) === true, 'Admin start rejected');
    $request->nonce = 'invalid'; check($routes['/school/view-as/start']['permission_callback']($request) instanceof WP_Error, 'Missing nonce accepted'); $request->nonce = 'valid';
    check(array_column(ViewAs::candidates('student', 10), 'id') === [2], 'Class students scope wrong');
    check(array_column(ViewAs::candidates('instructor', 10), 'id') === [3], 'Class instructors scope wrong');
    check(array_column(ViewAs::candidates('instructor', 0, 20), 'id') === [3], 'Course instructor scope wrong');
    check(array_column(ViewAs::candidates('student', 0, 20), 'id') === [2], 'Course enrollment scope wrong');
    denied(fn() => ViewAs::start(['role' => 'student', 'user_id' => 3, 'class_id' => 10, 'course_id' => 0]), 'Wrong class role accepted');
    denied(fn() => ViewAs::start(['role' => 'student', 'user_id' => 1, 'class_id' => 0, 'course_id' => 0]), 'Admin offered as student');
    $result = ViewAs::start(['role' => 'student', 'user_id' => 2, 'class_id' => 0, 'course_id' => 20]);
    check($result['url'] === get_permalink(20) && $auth === [2, 'target-session'] && $clears === 1, 'Switch did not set target session and clear both auth cookie schemes');
    [$token, $options] = $cookie_writes['ohmylms_view_as_test'];
    check($options['httponly'] && $options['secure'] && $options['samesite'] === 'Lax', 'Return cookie protections missing');
    $_COOKIE['ohmylms_view_as_test'] = $token;
    $current = 2; $session_token = 'different-session';
    check(!ViewAs::session(), 'Return session leaked to a normal login for the same student');
    denied(fn() => ViewAs::return([]), 'Cross-session return accepted');
    $session_token = 'target-session';
    check((bool) ViewAs::session(), 'Target session cannot return');
    check($routes['/school/view-as/start']['permission_callback']($request) instanceof WP_Error, 'Student can initiate a switch');
    denied(fn() => ViewAs::start(['role' => 'student', 'user_id' => 2]), 'Direct non-admin switch accepted');
    unset(WP_Session_Tokens::$tokens[1]['admin-session']);
    denied(fn() => ViewAs::return([]), 'Revoked admin session restored');
    WP_Session_Tokens::$tokens[1]['admin-session'] = true;
    $returned = ViewAs::return([]);
    check($returned === ['url' => 'https://example.test/wp-admin/admin.php?page=ohmylms-app#/account-hub'], 'Return does not open Account Hub in the admin app');
    check($current === 1 && $auth === [1, 'admin-session'] && $clears === 2, 'Original admin session not restored');
    check(!WP_Session_Tokens::get_instance(2)->verify('target-session') && !$transients, 'Target session or return token survived return');
    check($audit === [[1, 'view_as_started', 2], [1, 'view_as_returned', 2]], 'Switch audit attribution incorrect');
    // One-click previews: school admins are a previewable role and the default person is chosen safely.
    check(array_column(ViewAs::candidates('school'), 'id') === [5], 'School administrators are not offered');
    check(ViewAs::default(['role' => 'student'])['person']['id'] === 2 && ViewAs::default(['role' => 'student'])['source'] === 'recent', 'The most recently signed-in student is not the default');
    check(ViewAs::default(['role' => 'parent'])['person']['id'] === 4 && ViewAs::default(['role' => 'school'])['person']['id'] === 5, 'Parent or school default missing');
    $usermeta[1][ViewAs::LAST_META . 'student'] = 6;
    check(ViewAs::default(['role' => 'student']) === ['role' => 'student', 'person' => ['id' => 6, 'name' => 'Newer student', 'email' => 'student2@example.test'], 'source' => 'remembered'], 'The remembered student is not preferred');
    $usermeta[1][ViewAs::LAST_META . 'student'] = 3;
    check(ViewAs::default(['role' => 'student'])['source'] === 'recent', 'A remembered person who no longer holds the role was kept');
    denied(fn() => ViewAs::default(['role' => 'instructor']), 'An unsupported default role was accepted');
    check(isset($routes['/school/view-as/default']) && $routes['/school/view-as/default']['methods'] === 'GET', 'The default route is missing');
    $request->nonce = 'valid';
    check($routes['/school/view-as/default']['permission_callback']($request) === true, 'Admin cannot read the default person');
    $session_token = 'admin-session';
    ViewAs::start(['role' => 'teacher', 'user_id' => 3, 'class_id' => 0, 'course_id' => 0]);
    check($usermeta[1][ViewAs::LAST_META . 'teacher'] === 3, 'The previewed person was not remembered');
    check(ViewAs::pick_default([], [], []) === ['person' => null, 'source' => ''], 'An empty role must have no default');
    echo "View-as scope, nonce, session binding, revocation, cookie and return checks passed.\n";
}
