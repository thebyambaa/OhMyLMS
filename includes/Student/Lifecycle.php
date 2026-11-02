<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Enrollment and membership cleanup coordinated through their storage owners.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Student;

use OhMyLMS\Membership\Members;

defined( 'ABSPATH' ) || exit;

/** Preserve the established cleanup sequence and public CommonHook callbacks. */
final class Lifecycle {

	/**
	 * Remove enrollments when a course is deleted.
	 *
	 * @param int $course_id Course ID.
	 */
	public static function delete_course( $course_id ) {
		Enrollments::delete( array( 'course_id' => (int) $course_id ), array( '%d' ) );
	}

	/**
	 * Remove enrollment and membership rows associated with an order or its parent.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function delete_order( $order_id ) {
		$order_id = (int) $order_id;
		$parent   = wp_get_post_parent_id( $order_id );
		if ( $parent ) {
			$order_id = $parent;
		}
		Enrollments::delete( array( 'order_id' => $order_id ), array( '%d' ) );
		if ( ohmylms_is_pro() ) {
			Members::delete( array( 'order_id' => $order_id ) );
		}
	}

	/**
	 * Remove a plan's members and the legacy first order's enrollments.
	 *
	 * @param int $membership_id Plan ID.
	 */
	public static function delete_plan( $membership_id ) {
		$membership_id = (int) $membership_id;
		$order_id      = ohmylms_is_pro() ? Members::first_order( $membership_id ) : 0;
		if ( $order_id ) {
			Enrollments::delete( array( 'order_id' => $order_id ), array( '%d' ) );
		}
		if ( ohmylms_is_pro() ) {
			Members::delete( array( 'membership_id' => $membership_id ) );
		}
	}

	/**
	 * Remove a deleted user's membership and enrollment rows.
	 *
	 * @param int $user_id User ID.
	 */
	public static function delete_user( $user_id ) {
		if ( ohmylms_is_pro() ) {
			Members::delete( array( 'user_id' => $user_id ) );
		}
		Enrollments::delete( array( 'user_id' => (int) $user_id ), array( '%d' ) );
	}

	/**
	 * Cancel rows before WordPress deletes a user, preserving both hook stages.
	 *
	 * @param int $user_id User ID.
	 */
	public static function cancel_user( $user_id ) {
		Enrollments::update( array( 'status' => 'cancelled' ), array( 'user_id' => (int) $user_id ) );
		if ( ohmylms_is_pro() ) {
			Members::cancel_user( $user_id );
		}
	}
}
