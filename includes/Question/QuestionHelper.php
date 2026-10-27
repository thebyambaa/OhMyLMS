<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Responsible to handle all quiz related calculations.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Question;

/**
 * Responsible to handle all quiz related calculations
 *
 * @since 1.0.0
 */
class QuestionHelper {

	/**
	 * Add new question
	 *
	 * @param string $title Title.
	 * @param string $content Content.
	 * @return array
	 * @since 1.0.0
	 */
	public static function add_question( string $title, string $content ): array {
		/**
		 * Fires before question add
		 *
		 * @param string $title question title
		 * @param string $content question title
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_before_add_question', $title, $content );

		$quiz_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_type'   => 'ohmylms-question',
				'post_status' => 'publish',
			)
		);

		/**
		 * log the error when debug is enabled.
		 * $post_id->get_error_message()
		 */
		if ( is_wp_error( $quiz_id ) ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Failed to add question', 'ohmylms' ),
			);
		}

		/**
		 * Fires after question add
		 *
		 * @param string $title question title
		 * @param string $content question title
		 * @param string $quiz_id newly inserted question id
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_after_add_question', $title, $content, $quiz_id );

		return array(
			'status'  => 'success',
			'message' => __( 'Question has been added.', 'ohmylms' ),
		);
	}

	/**
	 * Saved question
	 *
	 * @param int   $post_id Post identifier.
	 * @param array $question_data Question data.
	 * @return array
	 * @since 1.0.0
	 */
	public static function save_question( int $post_id, array $question_data ): array {
		/**
		 * Fires before question save
		 *
		 * @param number $post_id new inserted question id
		 * @param array $question_data key value pair based items to update meta fields
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_before_question_update', $post_id, $question_data );

		if ( empty( $question_data['title'] ) ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Title is required to save question.', 'ohmylms' ),
			);
		}

		$post = get_post( $post_id );

		if ( $post && 'ohmylms-question' === $post->post_type ) {
			wp_update_post(
				array(
					'ID'         => $post_id,
					'post_title' => sanitize_text_field( $question_data['title'] ),
				)
			);
		}

		/**
		 * Fires after question update
		 *
		 * @param number $post_id new inserted question id
		 * @param array $question_data key value pair based items to update meta fields
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_after_question_update', $post_id, $question_data );

		return array(
			'status'  => 'success',
			'message' => __( 'Question has been saved.', 'ohmylms' ),
		);
	}

	/**
	 * Deleted question
	 *
	 * @param int $post_id Post identifier.
	 * @return array
	 * @since 1.0.0
	 */
	public static function delete_question( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post || 'ohmylms-question' !== $post->post_type ) {

			return array(
				'status'  => 'error',
				'message' => __( 'Question not found.', 'ohmylms' ),
			);
		}

		/**
		 * Fires before question delete
		 *
		 * @param string $post_id question id to delete
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_before_question_delete', $post_id );

		$result = wp_delete_post( $post_id, true );

		if ( ! $result ) {

			return array(
				'status'  => 'error',
				'message' => __( 'Failed to delete question.', 'ohmylms' ),
			);
		}

		/**
		 * Fires after question delete
		 *
		 * @param string $post_id Deleted Question id
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_after_question_delete', $post_id );

		return array(
			'status'  => 'success',
			'message' => __( 'Question has been deleted.', 'ohmylms' ),
		);
	}
}
