<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class LessonFactory.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Factory;

use OhMyLMS\Data\Lesson;
/**
 * Class LessonFactory
 *
 * Factory class for creating and retrieving Lesson objects.
 *
 * @package OhMyLMS\Factory
 * @since 1.0.0
 */
class LessonFactory {

	/**
	 * Get lesson object
	 *
	 * @param bool $lesson_id Lesson identifier.
	 * @return bool|Lesson
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function get_lesson( $lesson_id = false ) {
		$lesson_id = $this->get_lesson_id( $lesson_id );
		if ( ! $lesson_id ) {
			return false;
		}
		return new Lesson( $lesson_id );
	}


	/**
	 * Get lesson id
	 *
	 * @param mixed $lesson Lesson.
	 * @return bool|int
	 * @since 1.0.0
	 */
	private function get_lesson_id( $lesson ) {
		global $post;

		// Check if input is false and post is set.
		if ( false === $lesson && isset( $post, $post->ID ) && OHMYLMS_LESSON_CPT === get_post_type( $post->ID ) ) {
			return absint( $post->ID );
		}

		// If input is numeric, check if lesson exists.
		elseif ( is_numeric( $lesson ) ) {
			return $this->is_lesson_exist( $lesson ) ? $lesson : false;
		}

		// If input is an instance of Lesson.
		elseif ( $lesson instanceof Lesson ) {
			$id = $lesson->get_id();
			return $this->is_lesson_exist( $id ) ? $id : false;
		}

		// If input contains a valid ID property.
		elseif ( ! empty( $lesson->ID ) ) {
			return $this->is_lesson_exist( $lesson->ID ) ? $lesson->ID : false;
		}

		// Otherwise, return false.
		else {
			return false;
		}
	}


	/**
	 * Checks whether a lesson with the given ID exists.
	 *
	 * This method verifies that the lesson exists in the database and is of
	 * the correct post type (`OHMYLMS_LESSON_CPT`).
	 *
	 * @param int $lesson_id The ID of the lesson to check.
	 * @return bool Returns true if the lesson exists, otherwise false.
	 * @since 1.0.0
	 */
	public function is_lesson_exist( $lesson_id ) {
		if ( ! $lesson_id ) {
			return false;
		}

		$lesson = get_post( $lesson_id );

		// Check if the post exists and the post type matches.
		if ( $lesson && OHMYLMS_LESSON_CPT === get_post_type( $lesson_id ) ) {
			return true;
		} else {
			return false;
		}
	}
}
