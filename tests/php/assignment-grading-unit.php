<?php
/** Standalone behavioral checks for the shared grading operation and its compatibility adapter. */
namespace OhMyLMS\Data {
    final class Student {
        public function __construct( $id ) {}
        public function get_over_all_completion_rate( $course ) { return $GLOBALS['completed'] ? 100 : 20; }
        public function complete_lesson( $id, $course ) { $GLOBALS['completed'] = true; }
        public function is_course_completed( $course ) { return $GLOBALS['completed']; }
    }
}
namespace OhMyLMS\Learning {
    final class CourseProgram { public static function managed( $student, $course ) { return false; } }
}
namespace OhMyLMS\Student {
    final class Enrollments { public static function legacy_row( $student, $course ) { return array( 'order_id' => 9 ); } }
}
namespace {
    define( 'ABSPATH', __DIR__ );
    define( 'OHMYLMS_ASSIGNMENT_CPT', 'ohmylms-assignment' );
    class WP_Error {
        public function __construct( public $code, public $message, public $data ) {}
    }
    class WP_REST_Response {
        public $links = array();
        public function __construct( public $data ) {}
        public function link_header( ...$link ) { $this->links[] = $link; }
    }
    class WP_REST_Request extends \ArrayObject {
        public function get_json_params() { return $GLOBALS['rows']; }
    }
    function __( $text, $domain ) { return $text; }
    function is_wp_error( $value ) { return $value instanceof WP_Error; }
    function rest_ensure_response( $data ) { return new WP_REST_Response( $data ); }
    function get_permalink( $id ) { return '/assignment/' . $id; }
    function get_post( $id ) { return $id === 10 ? (object) array( 'ID' => 10, 'post_type' => OHMYLMS_ASSIGNMENT_CPT ) : null; }
    function get_post_meta( $id, $key, $single ) { return '_maximum_pass_points' === $key ? 5 : 10; }
    function ohmylms_get_course_by_content_id( $id ) { return 20; }
    function ohmylms_get_course( $id ) {
        return new class {
            public function get_id() { return 20; }
            public function get_name() { return 'Course'; }
        };
    }
    function ohmylms_get_assignment( $id ) {
        return new class {
            public function update_assignment_attempts( $student, $rows ) { $GLOBALS['saved'] = array( $student, $rows ); }
            public function get_assignment_attempts( $student ) { return $GLOBALS['saved'][1]; }
        };
    }
    function do_action( $name, ...$args ) { $GLOBALS['events'][] = array( $name, $args ); }
    function grading_check( $condition, $message ) {
        if ( ! $condition ) { throw new \RuntimeException( $message ); }
        ++$GLOBALS['checks'];
    }
    require __DIR__ . '/../../includes/Assignment/Grading.php';
    require __DIR__ . '/../../includes/Rest/V1/AssignmentController.php';
    $checks = 0;
    foreach ( array( 0, 5, 10 ) as $score ) {
        $completed = false;
        $events = array();
        $rows = array( array( 'id' => 42, 'score' => $score, 'status' => 'reviewed', 'note' => 'Feedback' ) );
        $result = \OhMyLMS\Assignment\Grading::review( 10, 7, $rows );
        $status = $score >= 5 ? 'passed' : 'failed';
        grading_check( $saved[0] === 7 && $saved[1][0]['id'] === 42, 'The verified student and attempt reach storage.' );
        grading_check( $saved[1][0]['status'] === $status && $saved[1][0]['note'] === 'Feedback', 'Pass/fail and feedback are preserved.' );
        grading_check( $completed === ( $score >= 5 ), 'Only passing grades complete learner progress.' );
        $reviews = array_values( array_filter( $events, fn( $event ) => 'ohmylms_after_assignment_review' === $event[0] ) );
        grading_check( count( $reviews ) === 1 && $reviews[0][1] === array( 10, 20, 7, $status, 42 ), 'Exactly one review hook identifies the learner and attempt.' );
        grading_check( $result['additional_data'] === array( 'course_id' => 20, 'course_name' => 'Course', 'total_marks' => 10, 'pass_marks' => 5 ), 'Domain report data stays compatible.' );
        $events = array();
        $response = ( new \OhMyLMS\Rest\V1\AssignmentController() )->update_attempt_report( new WP_REST_Request( array( 'id' => 10, 'user_id' => 7 ) ) );
        grading_check( $response instanceof WP_REST_Response && $response->data === $result, 'Compatibility adapter retains the report shape.' );
        grading_check( $response->links === array( array( 'alternate', '/assignment/10', array( 'type' => 'text/html' ) ) ), 'Compatibility adapter retains the alternate link.' );
        grading_check( count( array_filter( $events, fn( $event ) => 'ohmylms_course_completed' === $event[0] ) ) === 0, 'Regrading does not repeat the course completion transition.' );
    }
    $events = array();
    $invalid = \OhMyLMS\Assignment\Grading::review( 0, 7, $rows );
    grading_check( $invalid instanceof WP_Error && $invalid->code === 'ohmylms_rest_invalid_assignemnt_id' && $invalid->data['status'] === 404, 'The established invalid-ID error is preserved.' );
    grading_check( array() === $events, 'Invalid IDs produce no review events.' );
    echo "Assignment grading: $checks checks passed.\n";
}
