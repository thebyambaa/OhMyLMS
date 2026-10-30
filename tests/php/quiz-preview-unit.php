<?php
/**
 * Standalone test for quiz preview mode in OhMyLMS\Quiz\Submission: admins and a quiz's author
 * can retry freely and leave no record (no attempt, completion, evidence or outbound events),
 * while enrolled learners keep being recorded exactly as before. No database or WordPress runtime.
 */
namespace OhMyLMS\Data {
    final class Student {
        public function __construct(private $id) {}
        public function maybe_enrolled($course_id) { return in_array([$this->id, $course_id], $GLOBALS['enrollments'], true); }
        public function is_course_completed($course_id) { return false; }
        public function complete_lesson($quiz_id, $course_id) { $GLOBALS['completed'][] = [$this->id, $quiz_id]; }
        public function get_over_all_completion_rate($course_id) { return $GLOBALS['course_rate']; }
    }
}
namespace OhMyLMS\Learning {
    final class CourseProgram { public static function managed($student_id, $course_id) { return false; } }
}
namespace OhMyLMS\Assessment {
    final class Engine { public static function versioned() { return false; } }
    final class Schema {
        public static function ready() { return true; }
        public static function table($name) { return 'wp_ohmylms_' . $name; }
    }
}
namespace {
    define('ABSPATH', __DIR__); define('HOUR_IN_SECONDS', 3600);
    const ADMIN = 1, ADMIN_ENROLLED = 2, STUDENT = 3, STUDENT_ENROLLED = 4, EDITOR = 5, EDITOR_ENROLLED = 6, AUTHOR_ENROLLED = 7;
    const QUIZ = 20, COURSE = 50, ATTEMPT = 9;
    $current = ADMIN;
    $enrollments = [[ADMIN_ENROLLED, COURSE], [STUDENT_ENROLLED, COURSE], [EDITOR_ENROLLED, COURSE], [AUTHOR_ENROLLED, COURSE]];
    $admins = [ADMIN, ADMIN_ENROLLED]; $editors = [ADMIN, ADMIN_ENROLLED, EDITOR, EDITOR_ENROLLED, AUTHOR_ENROLLED]; $quiz_author = AUTHOR_ENROLLED;
    $filters = []; $actions = []; $completed = []; $transients = []; $take = 1; $used = 3; $stale_attempts = []; $removed = 0; $course_rate = 0;

    final class WP_Error { public function __construct(public $code = '', public $message = '', public $data = []) {} public function get_error_code() { return $this->code; } }
    function is_wp_error($value) { return $value instanceof WP_Error; }
    function get_post_type($id) { return $id === QUIZ ? 'ohmylms-quiz' : 'post'; }
    function get_post_field($field, $id) { return $id === QUIZ ? $GLOBALS['quiz_author'] : 0; }
    function ohmylms_get_course_by_content_id($id) { return $id === QUIZ ? COURSE : 0; }
    function user_can($user, $cap, $id = 0) { return in_array($user, $cap === 'manage_options' ? $GLOBALS['admins'] : $GLOBALS['editors'], true); }
    function current_user_can($cap, $id = 0) { return user_can($GLOBALS['current'], $cap, $id); }
    function apply_filters($hook, $value, ...$args) { return isset($GLOBALS['filters'][$hook]) ? $GLOBALS['filters'][$hook]($value, ...$args) : $value; }
    function do_action($hook, ...$args) { $GLOBALS['actions'][] = $hook; }
    function current_time($type) { return '2026-01-01 00:00:00'; }
    function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key] = $value; }
    function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
    function delete_transient($key) { unset($GLOBALS['transients'][$key]); }
    function ohmylms_get_quiz($id) {
        return new class {
            public function get_quiz_attempt($student) { return null; }
            public function count_total_attempt($student, $course) { return $GLOBALS['used']; }
            public function get_take_attempts() { return $GLOBALS['take']; }
            public function get_questions() { return []; }
        };
    }
    $wpdb = new class {
        public $prefix = 'wp_'; public $insert_id = 0; public $queries = []; public $selects = []; public $deleted = []; public $inserted = [];
        public function prepare($query, ...$args) { return $query; }
        public function query($sql) { $this->queries[] = $sql; return str_contains($sql, 'DELETE p FROM') ? $GLOBALS['removed'] : true; }
        public function get_var($sql) { return '1'; }
        public function get_col($sql) { $this->selects[] = $sql; return $GLOBALS['stale_attempts']; }
        public function insert($table, $row) { $this->inserted[] = $table; $this->insert_id = 42; return 1; }
        public function delete($table, $where) { $this->deleted[] = [$table, $where]; return 1; }
    };

    require __DIR__ . '/../../includes/Utility/Transaction.php';
    require __DIR__ . '/../../includes/Quiz/Submission.php';
    use OhMyLMS\Quiz\Submission;

    function check($value, $message) { if (!$value) { throw new \RuntimeException($message); } }
    function reset_state() {
        foreach (['actions', 'completed', 'transients', 'stale_attempts', 'filters'] as $name) { $GLOBALS[$name] = []; }
        $GLOBALS['removed'] = 0; $GLOBALS['course_rate'] = 0;
        $w = $GLOBALS['wpdb']; $w->deleted = []; $w->inserted = []; $w->queries = []; $w->selects = [];
    }
    function commit($student, $reason = 'submit') {
        $graded = ['rows' => [['question_marks' => 5, 'achive_mark' => 5, 'is_correct' => 1], ['question_marks' => 5, 'achive_mark' => 0, 'is_correct' => 0]],
            'total' => 5, 'manual' => false, 'status' => 'completed', 'reason' => $reason, 'passing' => 5, 'revision_id' => 3];
        $attempt = ['id' => ATTEMPT, 'quiz_id' => QUIZ, 'student_id' => $student];
        return (new \ReflectionMethod(Submission::class, 'after_commit'))->invoke(null, $attempt, COURSE, $graded);
    }
    function start($student) { $GLOBALS['current'] = $student; return Submission::start(QUIZ, $student); }
    function erased_attempts() { return array_values(array_unique(array_map(fn($d) => current($d[1]), $GLOBALS['wpdb']->deleted))); }

    // Who counts as previewing: admins and the author always, other editors until they enrol, nobody else.
    check(Submission::is_preview(QUIZ, ADMIN) === true, 'Unenrolled admin is not previewing');
    check(Submission::is_preview(QUIZ, ADMIN_ENROLLED) === true, 'An enrolled admin must still preview, or testing is recorded');
    check(Submission::is_preview(QUIZ, AUTHOR_ENROLLED) === true, 'The quiz author must preview even when enrolled');
    check(Submission::is_preview(QUIZ, EDITOR) === true, 'Unenrolled editor is not previewing');
    check(Submission::is_preview(QUIZ, EDITOR_ENROLLED) === false, 'An enrolled editor who is neither admin nor author is a real learner');
    check(Submission::is_preview(QUIZ, STUDENT) === false, 'Unenrolled non-editor treated as previewing');
    check(Submission::is_preview(QUIZ, STUDENT_ENROLLED) === false, 'Enrolled student treated as previewing');
    check(Submission::is_preview(QUIZ, 0) === false, 'Guest treated as previewing');
    check(Submission::is_preview(99, ADMIN) === false, 'Non-quiz content treated as previewing');
    $filters['ohmylms_quiz_is_preview'] = fn($preview, $quiz, $user) => $user === ADMIN_ENROLLED ? false : $preview;
    check(Submission::is_preview(QUIZ, ADMIN_ENROLLED) === false && Submission::is_preview(QUIZ, ADMIN) === true, 'The ohmylms_quiz_is_preview filter is not honoured');
    $filters = [];

    // A previewed submission records nothing and tells nobody, but shows its score once.
    foreach ([ADMIN, ADMIN_ENROLLED, AUTHOR_ENROLLED] as $previewer) {
        reset_state(); commit($previewer);
        check($actions === [], "Preview by $previewer fired events: " . implode(',', $actions));
        check($completed === [], "Preview by $previewer completed the lesson");
        $erased = array_map(fn($d) => $d[0], $wpdb->deleted);
        foreach (['wp_ohmylms_quiz_attempts_answers', 'wp_ohmylms_attempt_context', 'wp_ohmylms_attempt_items', 'wp_ohmylms_response_drafts', 'wp_ohmylms_response_events', 'wp_ohmylms_quiz_attempts'] as $table) {
            check(in_array($table, $erased, true), "Preview by $previewer left rows in $table");
        }
        check(end($wpdb->deleted) === ['wp_ohmylms_quiz_attempts', ['id' => ATTEMPT]], 'The attempt row itself was not erased last');
        check($wpdb->queries === ['START TRANSACTION', 'COMMIT'], 'Cleanup was not atomic');
        check(Submission::take_preview_result(QUIZ, STUDENT_ENROLLED) === null, 'Preview result leaked to another user');
        $result = Submission::take_preview_result(QUIZ, $previewer);
        check($result && $result['total'] === 5.0 && $result['max'] === 10.0 && $result['passing'] === 5.0 && $result['questions'] === 2 && $result['correct'] === 1 && $result['status'] === 'completed', 'Preview result summary is wrong');
        check(Submission::take_preview_result(QUIZ, $previewer) === null, 'Preview result was shown twice instead of once');
    }

    // Leaving a previewed quiz erases the attempt and any earlier result.
    reset_state(); commit(ADMIN); commit(ADMIN, 'exit');
    check(Submission::take_preview_result(QUIZ, ADMIN) === null, 'Exiting a preview kept a result');
    check($actions === [], 'Exiting a preview fired events');

    // A real learner is recorded exactly as before: completion, events, and no erasing.
    foreach ([STUDENT_ENROLLED, EDITOR_ENROLLED] as $learner) {
        reset_state(); commit($learner);
        check($completed === [[$learner, QUIZ]], 'Passing learner was not marked complete');
        foreach (['ohmylms_answer_graded', 'ohmylms_quiz_result', 'ohmylms_quiz_submission', 'ohmylms_attempt_submitted', 'ohmylms_attempt_graded', 'ohmylms_lesson_completed'] as $hook) {
            check(in_array($hook, $actions, true), "Learner submission no longer fires $hook");
        }
        check($wpdb->deleted === [] && $transients === [], 'Learner attempt was erased');
    }

    // Starting: previews ignore the attempt limit and announce nothing; learners keep both.
    $take = 1; $used = 3;
    foreach ([ADMIN, ADMIN_ENROLLED] as $previewer) {
        reset_state(); $id = start($previewer);
        check($id === 42, "Preview by $previewer was blocked by the attempt limit");
        check(!in_array('ohmylms_attempt_started', $actions, true), 'Preview announced an attempt start');
    }
    reset_state(); $blocked = start(STUDENT_ENROLLED);
    check($blocked instanceof WP_Error && $blocked->get_error_code() === 'quiz_attempt_limit', 'Learner attempt limit no longer enforced');
    check($wpdb->inserted === [], 'A blocked learner attempt was still written');
    $take = 5; reset_state(); $id = start(STUDENT_ENROLLED);
    check($id === 42 && $actions === ['ohmylms_attempt_started'], 'Learner start no longer announced');

    // Opening your own quiz clears what older versions recorded, but never an attempt in progress.
    reset_state(); $stale_attempts = ['11', '12']; $removed = 1;
    Submission::clear_preview_record(QUIZ, AUTHOR_ENROLLED);
    check(strpos($wpdb->selects[0], "status<>'in-progress'") !== false, 'Cleanup could erase an attempt in progress');
    check(erased_attempts() === [11, 12], 'Exactly the old attempts should be erased, got ' . json_encode(erased_attempts()));
    check(count(array_filter($wpdb->queries, fn($q) => str_contains($q, 'DELETE p FROM') && str_contains($q, "p.status='completed'"))) === 1, 'The old completion was not removed');
    check(count(array_filter($wpdb->queries, fn($q) => str_contains($q, "SET progress='running'"))) === 1, 'The course was left completed after its quiz was cleared');
    reset_state(); $stale_attempts = ['11']; $removed = 0;
    Submission::clear_preview_record(QUIZ, AUTHOR_ENROLLED);
    check(count(array_filter($wpdb->queries, fn($q) => str_contains($q, "SET progress='running'"))) === 0, 'Course reopened although no completion was removed');
    foreach ([ADMIN, EDITOR, STUDENT_ENROLLED, 0] as $other) {
        reset_state(); $stale_attempts = ['11']; $removed = 1;
        Submission::clear_preview_record(QUIZ, $other);
        check($wpdb->deleted === [] && $wpdb->queries === [] && $wpdb->selects === [], "Records of user $other were touched although they did not author the quiz");
    }

    echo "Quiz preview: admins and authors retry freely and leave no record; enrolled learners are still recorded.\n";
}
