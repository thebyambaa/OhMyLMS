<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Handles hooks related to lessons in the OhMyLMS plugin.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Hooks;

use OhMyLMS\Abstracts\HookHandler;

/**
 * Handles hooks related to lessons in the OhMyLMS plugin.
 *
 * @since 1.0.0
 */
class QuestionHookHandler extends HookHandler {

	/**
	 * Quiz placement and answer options are written by QuestionBank\DraftWriter inside the
	 * authorized save transaction, and removal by QuestionBank\Usage. The legacy listeners
	 * below remain callable for third-party code but are no longer attached: they wrote
	 * options by answer ID alone and unlinked a question from every quiz on delete.
	 */
	public function register_hooks() {
	}


	/**
	 * Link lesson with chapter.
	 * Update
	 *
	 * @param mixed $question Question.
	 * @param mixed $request REST request.
	 */
	public function link_question_with_quiz( $question, $request ) {

		if ( ! is_a( $question, 'WP_Post' ) ) {
			return;
		}
		$question_id = $question->ID;

		if ( empty( $request['quiz_id'] ) ) {
			return;
		}

		$quiz_id      = intval( $request['quiz_id'] );
		$order_number = ! empty( $request['order_number'] ) ? intval( $request['order_number'] ) : 0;
		$this->update_content_relationship( $quiz_id, $question_id, $order_number );
	}



	/**
	 * Update the relationship between a chapter and a lesson.
	 *
	 * @param mixed $quiz_id Quiz identifier.
	 * @param mixed $question_id Question identifier.
	 * @param int   $order_number The order number of the lesson in the chapter.
	 * .
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function update_content_relationship( $quiz_id, $question_id, $order_number = 0 ) {
		if ( ! $quiz_id || ! $question_id ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . OHMYLMS_QUIZ_QUESTION_RELATIONSHIP;

		// Check if the relationship already exists.
		$exists = $wpdb->get_var(
			$wpdb->prepare( 'SELECT ID FROM %i WHERE quiz_id = %d AND question_id = %d LIMIT 1', $table_name, $quiz_id, $question_id )
		);

		// If exists, update the relationship, otherwise insert a new one.
		if ( $exists ) {
			$wpdb->update(
				$table_name,
				array(
					'order_number' => $order_number,
				),
				array(
					'quiz_id'     => $quiz_id,
					'question_id' => $question_id,
				),
				array( '%d' ),
				array( '%d', '%d' )
			);
		} else {
			$wpdb->insert(
				$table_name,
				array(
					'quiz_id'      => $quiz_id,
					'question_id'  => $question_id,
					'order_number' => $order_number,
				),
				array( '%d', '%d', '%d' )
			);
		}

		/**
		 * Action triggered after a course and chapter relationship is created.
		 *
		 * @since 1.0.0
		 *
		 * @param int $chapter_id The ID of the chapter.
		 * @param int $lesson_id The ID of the lesson.
		 */
		do_action( 'ohmylms_quiz_question_relationship_created', $quiz_id, $question_id );
	}


	/**
	 * Unlink a lesson from a chapter.
	 *
	 * @param mixed $request REST request.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function unlink_quiz_from_question( $request ) {
		$question_id = isset( $request['id'] ) ? (int) $request['id'] : 0;

		if ( ! $question_id ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . OHMYLMS_QUIZ_QUESTION_RELATIONSHIP;

		$wpdb->delete(
			$table_name,
			array(
				'question_id' => $question_id,
			),
			array(
				'%d',
				'%d',
			)
		);
	}

	/**
	 * Save or update question answer for Question hook handler.
	 *
	 * @param mixed $question Question.
	 * @param mixed $request REST request.
	 */
	public function save_or_update_question_answer( $question, $request ) {
		$answers = ! empty( $request['questions'] ) ? $request['questions'] : array();
		if ( empty( $answers ) || ! is_a( $question, 'WP_Post' ) ) {
			return;
		}
		// Delegate to the ownership-checked writer; foreign option IDs are rejected.
		\OhMyLMS\QuestionBank\DraftWriter::save(
			array(
				'id'        => $question->ID,
				'questions' => $answers,
			)
		);
	}

	/**
	 * Update question answer meta data.
	 *
	 * @param int   $answer_id The ID of the answer.
	 * @param array $answer_data The answer data.
	 * .
	 * @return void
	 *
	 * @since 1.0.0
	 */
	private function update_question_answer_meta( $answer_id, $answer_data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ohmylms_question_answermeta';

		// Define allowed meta keys.
		$allowed_keys = array(
			'thumbnail_id'  => '_thumbnail_id',
			'image_url'     => '_image_url',
			'matching_data' => '_matching_data',
		);

		foreach ( $allowed_keys as $key => $meta_key ) {
			if ( isset( $answer_data[ $key ] ) ) {
				$meta_value = maybe_serialize( $answer_data[ $key ] );

				// Check if the meta already exists.
				$existing = $wpdb->get_var(
					$wpdb->prepare( 'SELECT meta_value FROM %i WHERE answer_id = %d AND meta_key = %s', $table, $answer_id, $meta_key )
				);

				if ( null !== $existing ) {
					// Update existing meta.
					$wpdb->update(
						$table,
						array( 'meta_value' => $meta_value ),
						array(
							'answer_id' => $answer_id,
							'meta_key'  => $meta_key,
						),
						array( '%s' ),
						array( '%d', '%s' )
					);
				} else {
					// Insert new meta.
					$wpdb->insert(
						$table,
						array(
							'answer_id'  => $answer_id,
							'meta_key'   => $meta_key,
							'meta_value' => $meta_value,
						),
						array( '%d', '%s', '%s' )
					);
				}
			}
		}
	}


	/**
	 * Update or add meta data for a question answer.
	 *
	 * @param int    $answer_id The ID of the answer.
	 * @param string $meta_key The meta key.
	 * @param mixed  $meta_value The meta value.
	 * @return void
	 */
	protected function update_answer_meta( $answer_id, $meta_key, $meta_value ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_question_answermeta';

		$value = maybe_serialize( $meta_value );

		// Check if the meta key already exists for the given answer_id.
		$existing_meta = $wpdb->get_var( $wpdb->prepare( 'SELECT meta_key FROM %i WHERE answer_id = %d AND meta_key = %s', $table_name, $answer_id, $meta_key ) );

		if ( empty( $meta_value ) ) {
			if ( null !== $existing_meta ) {
				// Delete meta if value is empty and it exists.
				$wpdb->delete(
					$table_name,
					array(
						'answer_id' => $answer_id,
						'meta_key'  => $meta_key,
					),
					array( '%d', '%s' )
				);
			}
			return;
		}

		if ( null !== $existing_meta ) {
			// Update existing meta.
			$wpdb->update(
				$table_name,
				array( 'meta_value' => $value ),
				array(
					'answer_id' => $answer_id,
					'meta_key'  => $meta_key,
				),
				array( '%s' ),
				array( '%d', '%s' )
			);
		} else {
			// Insert new meta.
			$wpdb->insert(
				$table_name,
				array(
					'answer_id'  => $answer_id,
					'meta_key'   => $meta_key,
					'meta_value' => $value,
				),
				array( '%d', '%s', '%s' )
			);
		}
	}
}
