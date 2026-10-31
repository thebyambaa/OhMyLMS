<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * DripContent class
 */
namespace OhMyLMS;

use OhMyLMS\Learning\DripSchedule;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


/**
 * Plugin drip content implementation. The release date itself is worked out by {@see DripSchedule}.
 */
class DripContent {

	/**
	 * Initialize drip content functionality
	 */
	public function __construct() {
		add_filter( 'ohmylms_is_lesson_locked', array( $this, 'is_lesson_locked' ), 10, 4 );
		add_filter( 'ohmylms_drip_protection_message', array( $this, 'drip_protection_message' ), 10, 4 );
	}

	/**
	 * Drip settings of a lesson, quiz, assignment or session, or null for other content.
	 *
	 * @param int $content_id Content ID.
	 * @return array|null
	 */
	private function settings_of( $content_id ) {
		$post_type = get_post_type( $content_id );
		if ( 'ohmylms-lesson' === $post_type ) {
			$content = ohmylms_get_lesson( $content_id );
		} elseif ( 'ohmylms-quiz' === $post_type ) {
			$content = ohmylms_get_quiz( $content_id );
		} elseif ( 'ohmylms-assignment' === $post_type ) {
			$content = ohmylms_get_assignment( $content_id );
		} elseif ( 'ohmylms-session' === $post_type ) {
			$content = ohmylms_get_session( $content_id );
		} else {
			return null;
		}
		if ( ! $content || ! method_exists( $content, 'get_drip_settings' ) ) {
			return null;
		}
		$drip = $content->get_drip_settings( 'edit' );
		return is_array( $drip ) ? $drip : null;
	}

	/**
	 * Check if a lesson is locked based on drip settings
	 *
	 * @param bool $is_locked Current locked status of the lesson.
	 * @param int  $lesson_id ID of the lesson.
	 * @param int  $course_id ID of the course.
	 * @param int  $current_student_id ID of the current student.
	 * @return bool True if the lesson is locked, false otherwise
	 * @since 1.0.0
	 */
	public function is_lesson_locked( $is_locked, $lesson_id, $course_id, $current_student_id ) {
		$at = DripSchedule::opens_at( $this->settings_of( $lesson_id ), (int) $course_id, (int) $current_student_id );
		if ( ! $at ) {
			return $is_locked;
		}
		return new \DateTimeImmutable( 'now', DripSchedule::timezone() ) < $at;
	}


	/**
	 * Get the unlock date for a lesson based on drip settings
	 *
	 * @param string $unlock_date Current unlock date of the lesson.
	 * @param int    $lesson_id ID of the lesson.
	 * @param int    $course_id ID of the course.
	 * @param int    $current_student_id ID of the current student.
	 * @return string Formatted unlock date or the original unlock date if no drip settings apply
	 * @since 1.0.0
	 */
	public function get_lesson_unlock_date( $unlock_date, $lesson_id, $course_id, $current_student_id ) {
		$at = DripSchedule::opens_at( $this->settings_of( $lesson_id ), (int) $course_id, (int) $current_student_id );
		return $at ? $at->format( 'Y-m-d' ) : $unlock_date;
	}

	/**
	 * Generate a drip protection message for content
	 *
	 * @param string $output Current output message.
	 * @param int    $content_id ID of the content (lesson, quiz, assignment).
	 * @param string $post_type Type of the content (lesson, quiz, assignment).
	 * @param int    $student_id ID of the student.
	 * @return string Modified output message with drip protection information
	 * @since 1.0.0
	 */
	public function drip_protection_message( $output, $content_id, $post_type, $student_id ) {
		$labels = array(
			'ohmylms-lesson'     => __( 'lesson', 'ohmylms' ),
			'ohmylms-quiz'       => __( 'quiz', 'ohmylms' ),
			'ohmylms-assignment' => __( 'assignment', 'ohmylms' ),
		);
		if ( ! isset( $labels[ $post_type ] ) ) {
			return $output;
		}
		$course_id = (int) ohmylms_get_course_by_content_id( $content_id, (int) $student_id );
		$at        = DripSchedule::opens_at( $this->settings_of( $content_id ), $course_id, (int) $student_id );
		if ( ! $at || new \DateTimeImmutable( 'now', DripSchedule::timezone() ) >= $at ) {
			return $output;
		}
		return sprintf(
			/* translators: 1: content kind such as "assignment", 2: release date and time. */
			__( 'This %1$s will be available on %2$s.', 'ohmylms' ),
			$labels[ $post_type ],
			DripSchedule::format( $at )
		);
	}
}
