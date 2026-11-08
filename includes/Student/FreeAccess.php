<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Free access: students open a course without enrolling first.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use OhMyLMS\Data\Course;
use OhMyLMS\Data\Student;

defined( 'ABSPATH' ) || exit;

/**
 * A course is open to every signed-in student unless its author switched "Free access for all students" off
 * in the course's Basics. The first time a student opens such a course they are enrolled (source `self`),
 * so progress, the gradebook and the student's course list work as for any other enrolment.
 *
 * Nothing is granted when the student's enrolment was ended on purpose (a row exists in another status),
 * the student is blocked, or the course has its own entry rules: a password or a limited number of seats.
 */
final class FreeAccess {

	/**
	 * Enrol a signed-in student in a free-access course and say whether they now have access.
	 *
	 * @param int    $user_id Student (user) ID.
	 * @param Course $course  The course being opened.
	 * @return bool True when the student was enrolled just now.
	 */
	public static function grant( $user_id, $course ) {
		$user_id   = (int) $user_id;
		$course_id = (int) $course->get_id();
		if ( ! $user_id || ! $course_id || ! self::open( $course ) ) {
			return false;
		}
		// An enrolment in any status means someone decided about this student already.
		if ( null !== Enrollments::legacy_status( $user_id, $course_id ) ) {
			return false;
		}
		$student = new Student( $user_id );
		if ( $student->maybe_banned() || user_can( $user_id, 'edit_post', $course_id ) ) {
			return false;
		}
		$inserted = Enrollments::create(
			array(
				'user_id'    => $user_id,
				'course_id'  => $course_id,
				'status'     => 'enrolled',
				'progress'   => 'running',
				'start_date' => current_time( 'mysql' ),
			),
			array( 'source' => 'self' ),
			array( '%d', '%d', '%s', '%s', '%s' )
		);
		if ( ! $inserted ) {
			return false;
		}
		update_user_meta( $user_id, '_is_ohmylms_student', 'yes' );
		update_user_meta( $user_id, '_is_ohmylms_user', 'yes' );
		/** This action is documented in includes/Student/Enrollments.php */
		do_action( 'ohmylms_manual_student_enrollment', $user_id, $course_id );
		return true;
	}

	/**
	 * Whether a course is open to everyone: published, switched on, and without its own entry rules.
	 *
	 * @param Course $course The course.
	 * @return bool
	 */
	public static function open( $course ) {
		$course_id = (int) $course->get_id();
		return 'publish' === get_post_status( $course_id )
			&& $course->get_free_access()
			&& 'password_protected' !== $course->get_access_type()
			&& ! $course->get_has_capacity()
			&& ! get_post_meta( $course_id, '_ohmylms_exam_wrapper', true );
	}
}
