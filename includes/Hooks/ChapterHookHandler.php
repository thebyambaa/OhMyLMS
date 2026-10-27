<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Handles hooks related to chapters in the OhMyLMS plugin.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Hooks;

use OhMyLMS\Abstracts\HookHandler;
use OhMyLMS\Data\Lesson;

/**
 * Handles hooks related to chapters in the OhMyLMS plugin.
 *
 * @since 1.0.0
 */
class ChapterHookHandler extends HookHandler {

	/**
	 * Register hooks for Chapter hook handler.
	 */
	public function register_hooks() {
		// add_action('ohmylms_rest_insert_chapter', [$this, 'link_chapter_with_course'], 10, 2);.
		add_action( 'ohmylms_after_remove_chapter_from_course', array( $this, 'unlink_lesson_from_chapter' ), 10 );
		add_action( 'ohmylms_rest_delete_chapter', array( $this, 'unlink_course_from_chapter' ), 10 );
		add_action( 'ohmylms_rest_delete_chapter', array( $this, 'unlink_lesson_from_chapter' ), 10 );
	}


	/**
	 * Create a default lesson for a chapter.
	 *
	 * @param \WP_Post         $chapter The chapter post object.
	 * @param \WP_REST_Request $request The REST request object.
	 * .
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function link_chapter_with_course( $chapter, $request ) {

		if ( ! is_a( $chapter, 'WP_Post' ) ) {
			return;
		}
		$chapter_id = $chapter->ID;

		if ( ! empty( $request['course_id'] ) ) {
			$course_id            = intval( $request['course_id'] );
			$order_number         = ! empty( $request['chapter_order'] ) ? intval( $request['chapter_order'] ) : 0;
			$course_hook_instance = new CourseHookHandler();
			$course_hook_instance->update_chapter_relationship( $course_id, $chapter_id, $order_number );
		}
	}


	/**
	 * Update the relationship between a chapter and a lesson.
	 *
	 * @param int $chapter_id The ID of the chapter.
	 * @param int $lesson_id The ID of the lesson.
	 * .
	 * @return void
	 *
	 * @since 1.0.0
	 */
	private function update_content_relationship( $chapter_id, $lesson_id ) {
		if ( ! $chapter_id || ! $lesson_id ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . OHMYLMS_CONTENT_RELATIONSHIP;

		$wpdb->insert(
			$table_name,
			array(
				'chapter_id'   => $chapter_id,
				'content_id'   => $lesson_id,
				'content_type' => 'text',
				'order_number' => 0,
			),
			array(
				'%d',
				'%d',
				'%s',
				'%d',
			)
		);

		/**
		 * Action triggered after a chapter and lesson relationship is created.
		 *
		 * @since 1.0.0
		 *
		 * @param int $chapter_id The ID of the chapter.
		 * @param int $lesson_id The ID of the lesson.
		 */
		do_action( 'ohmylms_chapter_lesson_relationship_created', $chapter_id, $lesson_id );
	}


	/**
	 * Unlinks a lesson from a chapter.
	 *
	 * @param mixed $value Value to process.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function unlink_lesson_from_chapter( $value ) {
		if ( is_array( $value ) ) {
			$chapter_id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		} else {
			$chapter_id = $value;
		}

		$this->remove_lesson_from_chapter( $chapter_id );
	}

	/**
	 * Remove a lesson from a chapter
	 *
	 * @param int $chapter_id The ID of the chapter.
	 * .
	 * @return void
	 * @since 1.0.0
	 */
	private function remove_lesson_from_chapter( $chapter_id ) {

		if ( ! $chapter_id ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . OHMYLMS_CONTENT_RELATIONSHIP;

		$wpdb->delete(
			$table_name,
			array(
				'chapter_id' => $chapter_id,
			),
			array(
				'%d',
			)
		);
	}


	/**
	 * Unlink course from chapter relationship
	 *
	 * @param array $request The REST request object.
	 * .
	 * @return void
	 * @since 1.0.0
	 */
	public function unlink_course_from_chapter( $request ) {
		$course_id  = ! empty( $request['course_id'] ) ? $request['course_id'] : 0;
		$chapter_id = ! empty( $request['id'] ) ? $request['id'] : 0;

		if ( ! $course_id ) {
			return;
		}

		global $wpdb;

		$table_name = $wpdb->prefix . OHMYLMS_CHAPTER_RELATIONSHIP;
		$wpdb->delete(
			$table_name,
			array(
				'course_id'  => $course_id,
				'chapter_id' => $chapter_id,
			),
			array(
				'%d',
			)
		);
	}
}
