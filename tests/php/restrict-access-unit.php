<?php
/**
 * Standalone test for OhMyLMS\Hooks\CommonHook::restrict_access(): the single-content gate behind
 * every editor "Preview" button. Admins and the content author must reach lessons, quizzes,
 * assignments and sessions without enrolling; everyone else must still be blocked.
 * No database or WordPress runtime.
 */
namespace OhMyLMS\Abstracts {
    abstract class HookHandler { abstract public function register_hooks(); }
}
namespace OhMyLMS\Services {
    final class EmailVerificationService {
        public static function is_required() { return false; }
        public static function is_verified($user_id) { return true; }
    }
}
namespace OhMyLMS\Quiz {
    final class Submission { public static function clear_preview_record($quiz_id, $user_id) { $GLOBALS['cleared'][] = [$quiz_id, $user_id]; } }
}
namespace OhMyLMS\Data {
    final class Student {
        public function __construct(private $id) {}
        public function maybe_enrolled($course_id) { return in_array([$this->id, $course_id], $GLOBALS['enrollments'], true); }
        public function maybe_banned() { return false; }
    }
}
namespace {
    define('ABSPATH', __DIR__);
    const ADMIN = 1, AUTHOR = 2, STUDENT = 3, ENROLLED = 4, GUEST = 0;
    const COURSE = 50, CONTENT = 60;
    $current = GUEST; $post = null; $preview_meta = ''; $enrollments = [[ENROLLED, COURSE]]; $cleared = [];

    function is_single() { return true; }
    function is_user_logged_in() { return $GLOBALS['current'] > 0; }
    function get_current_user_id() { return $GLOBALS['current']; }
    function current_user_can($cap) { return $cap === 'manage_options' && $GLOBALS['current'] === ADMIN; }
    function apply_filters($hook, $value, ...$args) { return $value; }
    function ohmylms_get_course_id_by_content_id($id) { return COURSE; }
    function get_post_meta($id, $key, $single) { return $GLOBALS['preview_meta']; }
    function ohmylms_is_pro() { return true; }
    function ohmylms_get_lesson($id) { return new class { public function get_prerequisites() { return ''; } }; }
    function ohmylms_get_assignment($id) { return new class { public function get_prerequisites() { return ''; } }; }
    function __($text) { return $text; }
    function esc_html__($text) { return $text; }
    function esc_html_e($text) { echo $text; }
    function esc_html($text) { return $text; }
    function esc_url($url) { return $url; }
    function wp_die($message, $title = '', $args = []) { throw new \RuntimeException('wp_die:' . ($args['response'] ?? 200)); }

    require __DIR__ . '/../../includes/Hooks/CommonHook.php';

    function visit($user, $type, $post_author = AUTHOR, $preview = '') {
        $GLOBALS['current'] = $user;
        $GLOBALS['preview_meta'] = $preview;
        $GLOBALS['post'] = (object) ['ID' => CONTENT, 'post_type' => $type, 'post_author' => $post_author, 'post_parent' => COURSE];
        $hook = (new \ReflectionClass(\OhMyLMS\Hooks\CommonHook::class))->newInstanceWithoutConstructor();
        try { $hook->restrict_access(); return 'allowed'; } catch (\RuntimeException $e) { return $e->getMessage(); }
    }
    function check($value, $message) { if (!$value) { throw new \RuntimeException($message); } }

    foreach (['ohmylms-lesson', 'ohmylms-quiz', 'ohmylms-assignment', 'ohmylms-session'] as $type) {
        check(visit(ADMIN, $type) === 'allowed', "Admin who is neither author nor enrolled is blocked from $type");
        check(visit(AUTHOR, $type) === 'allowed', "Author is blocked from their own $type");
        check(visit(ENROLLED, $type) === 'allowed', "Enrolled student is blocked from $type");
        check(visit(STUDENT, $type, AUTHOR, '1') === 'allowed', "Preview-enabled $type is not open to unenrolled students");
        check(visit(STUDENT, $type) === 'wp_die:403', "Unenrolled student can open $type");
        check(visit(GUEST, $type) === 'wp_die:403', "Guest can open $type");
    }
    check(visit(STUDENT, 'post') === 'allowed', 'Unrestricted post types must not be gated');

    // Opening a quiz while signed in lets the quiz clear stale preview records first; nothing else does.
    $cleared = []; visit(ADMIN, 'ohmylms-quiz');
    check($cleared === [[CONTENT, ADMIN]], 'Opening a quiz did not clear stale preview records for the signed-in user');
    $cleared = []; visit(GUEST, 'ohmylms-quiz'); visit(ADMIN, 'ohmylms-lesson'); visit(ADMIN, 'ohmylms-assignment');
    check($cleared === [], 'Stale preview records cleared for a guest or for non-quiz content');
    echo "Content gate: admins and authors bypass enrollment; guests and unenrolled students stay blocked.\n";
}
