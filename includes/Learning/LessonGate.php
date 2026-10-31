<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Server-side gate of "Mark as complete".
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Learning;

use OhMyLMS\Assignment\Delivery;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Whether a learner may complete a lesson or live session through `ohmylms_lesson_completed` (see
 * docs/LEARNER-FRONTEND.md, L6). The lesson page is already gated by `CommonHook::restrict_access()`; this
 * repeats the same rules for the request itself, so a direct call cannot complete content the learner
 * cannot open: enrolment, the drip release, lesson prerequisites, the sequential lock and any other
 * `ohmylms_is_lesson_locked` rule. Quizzes and assignments are completed by their own submission and
 * grading, never by this request. People who can edit the content skip the release, prerequisite and
 * lock rules (they preview it), but still need an enrolment to record progress.
 */
final class LessonGate {

	/** Post types completed by "Mark as complete". */
	const TYPES = array( 'ohmylms-lesson', 'ohmylms-session' );

	/**
	 * Course a learner takes the content in; sessions fall back to their parent course.
	 *
	 * @param int $id      Content ID.
	 * @param int $user_id Learner.
	 * @return int
	 */
	public static function course( $id, $user_id ) {
		$course = (int) ohmylms_get_course_by_content_id( $id, $user_id );
		$post   = get_post( $id );
		if ( ! $course && $post && 'ohmylms-session' === $post->post_type && $post->post_parent ) {
			$course = (int) $post->post_parent;
		}
		return $course;
	}

	/**
	 * Check a completion request.
	 *
	 * @param int $id      Lesson or session ID.
	 * @param int $user_id Learner.
	 * @return int|WP_Error The course ID, or `ohmylms_lesson_<reason>` with the learner-facing message.
	 */
	public static function check_completion( $id, $user_id ) {
		$id      = absint( $id );
		$user_id = (int) $user_id;
		$post    = $id ? get_post( $id ) : null;
		if ( $user_id < 1 ) {
			return new WP_Error( 'ohmylms_lesson_not_logged_in', __( 'Log in to complete this lesson.', 'ohmylms' ), array( 'status' => 401 ) );
		}
		if ( ! $post || 'publish' !== $post->post_status || ! in_array( $post->post_type, array_merge( self::TYPES, array_keys( Delivery::PREREQUISITE_TYPES ) ), true ) ) {
			return new WP_Error( 'ohmylms_lesson_not_found', __( 'Lesson not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		if ( ! in_array( $post->post_type, self::TYPES, true ) ) {
			return new WP_Error( 'ohmylms_lesson_not_completable', __( 'Quizzes and assignments are completed when you submit them.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$course = self::course( $id, $user_id );
		if ( ! $course ) {
			return new WP_Error( 'ohmylms_lesson_not_in_course', __( 'This lesson is not part of a course yet.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		if ( ! ( new \OhMyLMS\Data\Student( $user_id ) )->maybe_enrolled( $course ) ) {
			return new WP_Error( 'ohmylms_lesson_not_enrolled', __( 'Enrol in the course to complete this lesson.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		if ( user_can( $user_id, 'edit_post', $id ) ) {
			return $course;
		}
		$drip = (string) apply_filters( 'ohmylms_drip_protection_message', '', $id, $post->post_type, $user_id );
		if ( '' !== $drip ) {
			return new WP_Error( 'ohmylms_lesson_not_open', $drip, array( 'status' => 403 ) );
		}
		$missing = self::missing_prerequisites( $id, $user_id );
		if ( $missing ) {
			/* translators: %s: comma-separated titles. */
			return new WP_Error( 'ohmylms_lesson_prerequisites', sprintf( __( 'Complete %s before you complete this lesson.', 'ohmylms' ), implode( ', ', $missing ) ), array( 'status' => 403 ) );
		}
		if ( apply_filters( 'ohmylms_is_lesson_sequentially_locked', false, $id, $course, $user_id ) ) {
			return new WP_Error( 'ohmylms_lesson_locked', __( 'You must complete the previous lesson before accessing this content.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		if ( apply_filters( 'ohmylms_is_lesson_locked', false, $id, $course, $user_id ) ) {
			return new WP_Error( 'ohmylms_lesson_locked', __( 'This lesson is locked.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		return $course;
	}

	/**
	 * Titles of a lesson's prerequisites the learner has not completed (read fresh).
	 *
	 * @param int $id      Lesson ID.
	 * @param int $user_id Learner.
	 * @return string[]
	 */
	public static function missing_prerequisites( $id, $user_id ) {
		$lesson   = 'ohmylms-lesson' === get_post_type( $id ) ? ohmylms_get_lesson( $id ) : null;
		$required = $lesson && method_exists( $lesson, 'get_prerequisites' ) ? $lesson->get_prerequisites() : array();
		if ( ! is_array( $required ) || empty( $required['enable'] ) || empty( $required['data'] ) || ! is_array( $required['data'] ) ) {
			return array();
		}
		$missing = array();
		foreach ( $required['data'] as $row ) {
			$content = absint( is_array( $row ) ? ( $row['value'] ?? 0 ) : 0 );
			if ( $content && ! Delivery::completed( $user_id, $content ) ) {
				$missing[] = get_post( $content ) ? html_entity_decode( get_the_title( $content ), ENT_QUOTES, 'UTF-8' ) : (string) ( $row['label'] ?? '' );
			}
		}
		return $missing;
	}
}
