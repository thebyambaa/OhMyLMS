<?php
/** Standalone regression checks for optional storage owners and block/unblock orchestration. */
namespace OhMyLMS\Student {
    final class EnrolmentSchema { public static function exists() { return false; } }
    final class EnrolmentMirror { public static function legacy_table() { return 'wp_ohmylms_user_enrollment'; } }
}
namespace {
    define( 'ABSPATH', __DIR__ );
    class WP_Error { public function __construct( public $code, public $message, public $data ) {} }
    function __( $text, $domain ) { return $text; }
    function update_user_meta( $id, $key, $value ) { $GLOBALS['flags'][$id] = $value; }
    function delete_user_meta( $id, $key ) { unset( $GLOBALS['flags'][$id] ); }
    function do_action( $name, ...$args ) { $GLOBALS['events'][] = array( $name, $args ); }
    function status_check( $condition, $message ) {
        if ( ! $condition ) { throw new \RuntimeException( $message ); }
        ++$GLOBALS['checks'];
    }
    $wpdb = new class {
        public $prefix = 'wp_';
        public $rows = array();
        public $failure = '';
        public function esc_like( $table ) { return $table; }
        public function prepare( $sql, ...$args ) { return array( $sql, $args ); }
        public function get_var( $prepared ) {
            status_check( $prepared[0] === 'SHOW TABLES LIKE %s', 'Optional storage is probed with valid SQL.' );
            return array_key_exists( $prepared[1][0], $this->rows ) ? $prepared[1][0] : null;
        }
        public function update( $table, $data, $where, ...$formats ) {
            if ( $table === $this->failure ) { return false; }
            $count = 0;
            foreach ( $this->rows[$table] as &$row ) {
                if ( array_intersect_assoc( $row, $where ) === $where ) {
                    $row = array_merge( $row, $data );
                    ++$count;
                }
            }
            return $count;
        }
    };
    require __DIR__ . '/../../includes/Student/Enrollments.php';
    require __DIR__ . '/../../includes/Membership/Members.php';
    require __DIR__ . '/../../includes/Student/Directory.php';
    $checks = 0;
    $flags = $events = array();
    $initial = array(
        array( 'user_id' => 7, 'status' => 'enrolled' ),
        array( 'user_id' => 7, 'status' => 'cancelled' ),
        array( 'user_id' => 8, 'status' => 'enrolled' ),
    );
    foreach ( array( 'wp_ohmylms_user_enrollment', 'wp_ohmylms_user_membership' ) as $table ) {
        $wpdb->rows[$table] = $initial;
    }
    status_check( true === \OhMyLMS\Student\Directory::set_blocked( 7, true ), 'Blocking succeeds through both owners.' );
    foreach ( $wpdb->rows as $rows ) {
        status_check( $rows[0]['status'] === 'banned', 'Active rows are banned.' );
        status_check( $rows[1]['status'] === 'cancelled' && $rows[2]['status'] === 'enrolled', 'Cancelled rows and other learners remain unchanged.' );
    }
    status_check( $flags[7] === 'yes' && $events === array( array( 'ohmylms_student_blocked', array( 7 ) ) ), 'The flag and public hook follow both writes.' );
    \OhMyLMS\Student\Directory::set_blocked( 7, true );
    \OhMyLMS\Student\Directory::set_blocked( 7, false );
    status_check( ! isset( $flags[7] ), 'Unblocking clears the flag.' );
    foreach ( $wpdb->rows as $rows ) {
        status_check( $rows === $initial, 'Repeated block and unblock preserve the original row scope.' );
    }
    unset( $wpdb->rows['wp_ohmylms_user_membership'] );
    status_check( true === \OhMyLMS\Student\Directory::set_blocked( 7, true ), 'An absent optional membership table is tolerated.' );
    $wpdb->rows['wp_ohmylms_user_membership'] = $initial;
    $wpdb->failure = 'wp_ohmylms_user_membership';
    $events = array();
    $result = \OhMyLMS\Student\Directory::set_blocked( 7, false );
    status_check( $result instanceof WP_Error && $result->data['status'] === 500, 'A failed owner write retains the existing error.' );
    status_check( $flags[7] === 'yes' && $events === array(), 'Failure does not clear the flag or emit a success hook.' );
    status_check( $wpdb->rows['wp_ohmylms_user_enrollment'][0]['status'] === 'enrolled', 'Existing sequential partial-write behavior is explicit; this refactor adds no transaction.' );
    echo "Student status ownership: $checks checks passed.\n";
}
