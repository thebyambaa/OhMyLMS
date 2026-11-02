<?php
/** Cleanup adapters preserve parent orders, domain call order and user deletion stages. */
namespace OhMyLMS\Abstracts {
    abstract class HookHandler { abstract public function register_hooks(); }
}
namespace OhMyLMS\Student {
    final class Enrollments {
        public static function delete( $where, $format ) { $GLOBALS['calls'][] = array( 'enrollments.delete', $where ); }
        public static function update( $data, $where ) { $GLOBALS['calls'][] = array( 'enrollments.update', $data, $where ); }
    }
}
namespace OhMyLMS\Membership {
    final class Members {
        public static function delete( $where ) { $GLOBALS['calls'][] = array( 'members.delete', $where ); }
        public static function first_order( $id ) { $GLOBALS['calls'][] = array( 'members.first_order', $id ); return 12; }
        public static function cancel_user( $id ) { $GLOBALS['calls'][] = array( 'members.cancel_user', $id ); }
    }
}
namespace {
    define( 'ABSPATH', __DIR__ );
    $pro = true;
    $calls = array();
    $checks = 0;
    function ohmylms_is_pro() { return $GLOBALS['pro']; }
    function wp_get_post_parent_id( $id ) { return 3 === $id ? 2 : 0; }
    function lifecycle_check( $expected, $message ) {
        if ( $expected !== $GLOBALS['calls'] ) { throw new \RuntimeException( $message ); }
        ++$GLOBALS['checks'];
        $GLOBALS['calls'] = array();
    }
    require __DIR__ . '/../../includes/Student/Lifecycle.php';
    require __DIR__ . '/../../includes/Hooks/CommonHook.php';
    $hook = new \OhMyLMS\Hooks\CommonHook();
    $hook->delete_enrollment( '4' );
    lifecycle_check( array( array( 'enrollments.delete', array( 'course_id' => 4 ) ) ), 'Course deletion stays enrollment-scoped.' );
    $hook->delete_enrollment_after_delete_order( '3' );
    lifecycle_check( array( array( 'enrollments.delete', array( 'order_id' => 2 ) ), array( 'members.delete', array( 'order_id' => 2 ) ) ), 'Order cleanup resolves the parent and preserves owner order.' );
    $hook->delete_membership( '8' );
    lifecycle_check( array( array( 'members.first_order', 8 ), array( 'enrollments.delete', array( 'order_id' => 12 ) ), array( 'members.delete', array( 'membership_id' => 8 ) ) ), 'Plan cleanup retains the established first-order behavior.' );
    $hook->after_delete_wp_user( 7 );
    lifecycle_check( array( array( 'enrollments.update', array( 'status' => 'cancelled' ), array( 'user_id' => 7 ) ), array( 'members.cancel_user', 7 ) ), 'Before deletion cancels rows rather than deleting them.' );
    $hook->delete_membership_and_enrollment( 7 );
    lifecycle_check( array( array( 'members.delete', array( 'user_id' => 7 ) ), array( 'enrollments.delete', array( 'user_id' => 7 ) ) ), 'After deletion removes both owners in the original order.' );
    $pro = false;
    $hook->delete_membership_and_enrollment( 7 );
    lifecycle_check( array( array( 'enrollments.delete', array( 'user_id' => 7 ) ) ), 'The compatibility pro guard still avoids membership cleanup.' );
    echo "Student lifecycle: $checks checks passed.\n";
}
