<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Responsible to handle all lesson related calculations.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Lesson;

use OhMyLMS\Section\SectionHelper;

/**
 * Responsible to handle all lesson related calculations
 *
 * @since 1.0.0
 */
class LessonHelper {

	/**
	 * Add lesson
	 *
	 * @param mixed $title Title.
	 * @param mixed $content Content.
	 * @return array
	 * @since 1.0.0
	 */
	public static function add_lesson( $title, $content ): array {
		/**
		 * Fires before lesson add
		 *
		 * @param string $title lesson title
		 * @param string $content lesson title
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_before_add_lesson', $title, $content );

		$lesson_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_type'   => 'ohmylms-lesson',
				'post_status' => 'publish',
			)
		);

		/**
		 * log the error when debug is enabled.
		 * $post_id->get_error_message()
		 */
		if ( is_wp_error( $lesson_id ) ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Failed to add lesson', 'ohmylms' ),
			);
		}

		/**
		 * Fires after lesson add
		 *
		 * @param string $title lesson title
		 * @param string $content lesson title
		 * @param string $lesson_id newly inserted lesson id
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_after_add_lesson', $title, $content, $lesson_id );

		$lesson_data = array(
			'id'    => $lesson_id,
			'title' => $title,
			'type'  => 'lesson',
		);

		return array(
			'status'  => 'success',
			'message' => __( 'Lesson has been added.', 'ohmylms' ),
			'data'    => $lesson_data,
		);
	}

	/**
	 * Update lesson
	 *
	 * @param mixed $post_id Post identifier.
	 * @param array $map_data Map data.
	 * @return array
	 * @since 1.0.0
	 */
	public static function update_lesson( mixed $post_id, array $map_data ): array {

		/**
		 * Fires before lesson update
		 *
		 * @param number $post_id new inserted lesson id
		 * @param array $map_data key value pair based items to update meta fields
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_before_lesson_update', $post_id, $map_data );

		if ( empty( $map_data['title'] ) ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Title is required to save lesson.', 'ohmylms' ),
			);
		}

		$post = get_post( $post_id );

		if ( $post && 'ohmylms-lesson' === $post->post_type ) {
			wp_update_post(
				array(
					'ID'         => $post_id,
					'post_title' => sanitize_text_field( $map_data['title'] ),
				)
			);
		}

		/**
		 * Fires after lesson update
		 *
		 * @param number $post_id new inserted lesson id
		 * @param array $map_data key value pair based items to update meta fields
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_after_lesson_update', $post_id, $map_data );

		$lesson_data = array(
			'id'    => $post_id,
			'title' => $map_data['title'],
			'type'  => 'lesson',
		);

		return array(
			'status'  => 'success',
			'message' => __( 'Successfully saved lesson.', 'ohmylms' ),
			'data'    => $lesson_data,
		);
	}

	/**
	 * Save lesson
	 *
	 * @param mixed $post_id Post identifier.
	 * @param array $lesson_data Lesson data.
	 * @return array
	 * @since 1.0.0
	 */
	public static function save_lesson( mixed $post_id, array $lesson_data ): array {

		/**
		 * Fires before lesson update
		 *
		 * @param number $post_id new inserted lesson id
		 * @param array $lesson_data key value pair based items to update meta fields
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_before_lesson_update', $post_id, $lesson_data );

		if ( empty( $lesson_data ) ) {
			return array(
				'status'  => 'error',
				'message' => __( 'No data found to update.', 'ohmylms' ),
			);
		}

		$post = get_post( $post_id );

		if ( 'ohmylms-lesson' !== $post->post_type ) {
			return array(
				'status'  => 'error',
				'message' => __( 'No lesson available. ', 'ohmylms' ),
			);
		}

		$post_update_data = array(
			'ID'           => $post_id,
			'post_title'   => sanitize_text_field( $lesson_data['title'] ),
			'post_excerpt' => sanitize_textarea_field( $lesson_data['description'] ),
			'post_content' => wp_kses_post( $lesson_data['editorHTML'] ),
		);

		wp_update_post( $post_update_data );

		update_post_meta( $post_id, 'lesson_editor_content', wp_json_encode( $lesson_data['editorContent'] ) );
		update_post_meta( $post_id, 'lesson_visibility', sanitize_text_field( $lesson_data['visibility'] ) );
		update_post_meta( $post_id, 'lesson_enable_comments', (bool) $lesson_data['enableComments'] );
		update_post_meta( $post_id, 'lesson_prerequisites', (bool) $lesson_data['prerequisites'] );
		update_post_meta( $post_id, 'lesson_drip_settings', (bool) $lesson_data['dripFeed'] );
		update_post_meta( $post_id, 'lesson_download_resource', sanitize_text_field( $lesson_data['downloadResource'] ) );
		update_post_meta( $post_id, 'lesson_thumbnail', sanitize_text_field( $lesson_data['thumbnail'] ) );
		update_post_meta( $post_id, 'lesson_preview_enable', (bool) $lesson_data['preview_enable'] );

		/**
		 * Fires after lesson update
		 *
		 * @param number $post_id new inserted lesson id
		 * @param array $lesson_data key value pair based items to update meta fields
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_after_lesson_update', $post_id, $lesson_data );

		return array(
			'status'  => 'success',
			'message' => __( 'Successfully saved lesson.', 'ohmylms' ),
		);
	}

	/**
	 * Get lesson content
	 *
	 * @param mixed $post_id Post identifier.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_lesson_content( $post_id ): array {

		$post = get_post( $post_id );

		if ( 'ohmylms-lesson' !== $post->post_type ) {
			return array(
				'status'  => 'error',
				'message' => __( 'No lesson available. ', 'ohmylms' ),
			);
		}

		$lesson_data = array(
			'title'            => $post->post_title,
			'description'      => $post->post_excerpt,
			'editorContent'    => json_decode( get_post_meta( $post_id, 'lesson_editor_content', true ), true ),
			'editorHTML'       => $post->post_content,
			'visibility'       => get_post_meta( $post_id, 'lesson_visibility', true ),
			'enableComments'   => (bool) get_post_meta( $post_id, 'lesson_enable_comments', true ),
			'prerequisites'    => (bool) get_post_meta( $post_id, 'lesson_prerequisites', true ),
			'dripFeed'         => (bool) get_post_meta( $post_id, 'lesson_drip_settings', true ),
			'downloadResource' => get_post_meta( $post_id, 'lesson_download_resource', true ),
			'thumbnail'        => get_post_meta( $post_id, 'lesson_thumbnail', true ),
			'preview_enable'   => (bool) get_post_meta( $post_id, 'lesson_preview_enable', true ),
		);

		return array(
			'status'  => 'success',
			'message' => __( 'Successfully fetched lesson content.', 'ohmylms' ),
			'data'    => $lesson_data,
		);
	}

	/**
	 * Delete lesson
	 *
	 * @param int $post_id Post identifier.
	 * @return array
	 * @since 1.0.0
	 */
	public static function delete_lesson( int $post_id ): array {

		$post = get_post( $post_id );
		if ( ! $post || 'ohmylms-lesson' !== $post->post_type ) {

			return array(
				'status'  => 'error',
				'message' => __( 'Lesson not found.', 'ohmylms' ),
			);
		}

		/**
		 * Fires before lesson delete
		 *
		 * @param string $post_id lesson id to delete
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_before_lesson_delete', $post_id );

		$result = wp_delete_post( $post_id, true );

		if ( ! $result ) {

			return array(
				'status'  => 'error',
				'message' => __( 'Failed to delete lesson.', 'ohmylms' ),
			);
		}

		/**
		 * Fires after lesson delete
		 *
		 * @param string $post_id Deleted lesson id
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_after_lesson_delete', $post_id );

		return array(
			'status'  => 'success',
			'message' => __( 'Lesson has been deleted.', 'ohmylms' ),
		);
	}
}
