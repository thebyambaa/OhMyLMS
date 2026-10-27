<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * SequentialMode class (Pro)
 */
namespace OhMyLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin sequential mode implementation.
 */
class SequentialMode {

	/**
	 * Initialize Sequential mode.
	 */
	public function __construct() {
		add_filter( 'ohmylms_is_lesson_locked', array( $this, 'is_lesson_locked' ), 20, 4 );
	}

	/**
	 * Build a flat ordered list of all content IDs for a course across all chapters.
	 * Chapters ordered by chapter order_number, lessons ordered by lesson order_number.
	 * Unpublished content is excluded — it cannot be completed, so it must not block the sequence.
	 *
	 * @param int $course_id Course identifier.
	 * @return int[]
	 */
	public static function get_ordered_content_ids( $course_id ) {
		$course = ohmylms_get_course( $course_id );
		if ( ! $course ) {
			return array();
		}

		$chapters = $course->get_chapters();
		if ( ! is_array( $chapters ) || empty( $chapters ) ) {
			return array();
		}

		$flat = array();

		foreach ( $chapters as $chapter_data ) {
			if ( ! isset( $chapter_data['id'] ) ) {
				continue;
			}
			$chapter = ohmylms_get_chapter( $chapter_data['id'] );
			if ( ! $chapter ) {
				continue;
			}
			$lessons = $chapter->get_lessons();
			if ( ! is_array( $lessons ) ) {
				continue;
			}
			foreach ( $lessons as $lesson_data ) {
				if ( ! isset( $lesson_data['id'] ) ) {
					continue;
				}
				$post = get_post( $lesson_data['id'] );
				if ( ! $post || 'publish' !== $post->post_status ) {
					continue;
				}
				$flat[] = (int) $lesson_data['id'];
			}
		}

		return $flat;
	}

	/**
	 * Check if a content item is sequentially locked.
	 *
	 * @param int $content_id Content identifier.
	 * @param int $course_id Course identifier.
	 * @param int $student_id Student identifier.
	 * @return bool
	 */
	public static function is_sequentially_locked( $content_id, $course_id, $student_id ) {
		if ( ! $student_id ) {
			return false;
		}

		$course = ohmylms_get_course( $course_id );
		if ( ! $course || 'yes' !== $course->get_sequential_mode() ) {
			return false;
		}

		// Admins and course authors bypass the sequential lock.
		if ( current_user_can( 'manage_options' ) ) {
			return false;
		}
		$post = get_post( $content_id );
		if ( $post && (int) $post->post_author === (int) $student_id ) {
			return false;
		}

		$flat = self::get_ordered_content_ids( $course_id );
		if ( empty( $flat ) ) {
			return false;
		}

		$position = array_search( (int) $content_id, $flat, true );
		if ( false === $position || 0 === $position ) {
			return false;
		}

		$prev_id = $flat[ $position - 1 ];
		$student = ohmylms_get_student( $student_id );
		if ( ! $student ) {
			return false;
		}

		if ( $student->maybe_completed( $prev_id ) ) {
			return false;
		}

		$prev_post = get_post( $prev_id );

		// For assignments, also accept submitted/failed attempts — don't block forever on instructor review.
		if ( $prev_post && OHMYLMS_ASSIGNMENT_CPT === $prev_post->post_type ) {
			global $wpdb;
			$attempt = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}ohmylms_assignment_attempts WHERE user_id = %d AND assignment_id = %d LIMIT 1",
					$student_id,
					$prev_id
				)
			);
			if ( $attempt ) {
				return false;
			}
		}

		// For quizzes, unblock when all attempts are exhausted — student cannot retry.
		if ( $prev_post && OHMYLMS_QUIZ_CPT === $prev_post->post_type ) {
			$quiz = ohmylms_get_quiz( $prev_id );
			if ( $quiz ) {
				$max_attempts  = (int) $quiz->get_take_attempts();
				$used_attempts = (int) $quiz->count_total_attempt( $student_id, $course_id );
				if ( $max_attempts > 0 && $used_attempts >= $max_attempts ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Filter callback for ohmylms_is_lesson_locked (priority 20, after DripContent at 10).
	 *
	 * @param bool $is_locked Is locked.
	 * @param int  $lesson_id Lesson identifier.
	 * @param int  $course_id Course identifier.
	 * @param int  $current_student_id Current student identifier.
	 * @return bool
	 */
	public function is_lesson_locked( $is_locked, $lesson_id, $course_id, $current_student_id ) {
		if ( $is_locked ) {
			return true;
		}
		return self::is_sequentially_locked( $lesson_id, $course_id, $current_student_id );
	}
}
