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
class QuizHookHandler extends HookHandler {

	/**
	 * Register hooks for Quiz hook handler.
	 */
	public function register_hooks() {
		add_action( 'ohmylms_rest_insert_quiz', array( $this, 'link_quiz_with_chapter' ), 10, 2 );
		add_action( 'ohmylms_rest_delete_quiz', array( $this, 'unlink_chapter_from_quiz' ), 10 );
	}


	/**
	 * Link lesson with chapter.
	 * Update
	 *
	 * @param mixed $quiz Quiz.
	 * @param mixed $request REST request.
	 */
	public function link_quiz_with_chapter( $quiz, $request ) {
		if ( ! is_a( $quiz, 'WP_Post' ) ) {
			return;
		}
		$quiz_id = $quiz->ID;

		if ( empty( $request['chapter_id'] ) ) {
			return;
		}

		$chapter_id   = intval( $request['chapter_id'] );
		$order_number = ! empty( $request['quiz_order'] ) ? intval( $request['quiz_order'] ) : 0;
		$this->update_content_relationship( $chapter_id, $quiz_id, $order_number );
	}



	/**
	 * Update the relationship between a chapter and a lesson.
	 *
	 * @param int $chapter_id The ID of the chapter.
	 * @param int $quiz_id The ID of the lesson.
	 * @param int $order_number The order number of the lesson in the chapter.
	 * .
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function update_content_relationship( $chapter_id, $quiz_id, $order_number = 0 ) {

		if ( ! $chapter_id || ! $quiz_id ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . OHMYLMS_CONTENT_RELATIONSHIP;

		// Check if the relationship already exists.
		$exists = $wpdb->get_var(
			$wpdb->prepare( 'SELECT ID FROM %i WHERE chapter_id = %d AND content_id = %d LIMIT 1', $table_name, $chapter_id, $quiz_id )
		);

		// If exists, update the relationship, otherwise insert a new one.
		if ( $exists ) {
			$wpdb->update(
				$table_name,
				array(
					'order_number' => $order_number,
				),
				array(
					'content_id' => $quiz_id,
					'chapter_id' => $chapter_id,
				),
				array( '%d' ),
				array( '%d', '%d' )
			);
		} else {
			$wpdb->insert(
				$table_name,
				array(
					'content_id'   => $quiz_id,
					'chapter_id'   => $chapter_id,
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
		 * @param int $quiz_id The ID of the lesson.
		 */
		do_action( 'ohmylms_chapter_lesson_relationship_created', $chapter_id, $quiz_id );
	}


	/**
	 * Unlink a lesson from a chapter.
	 *
	 * @param mixed $quiz_id Quiz identifier.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function unlink_chapter_from_quiz( $quiz_id ) {

		if ( ! $quiz_id ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . OHMYLMS_CONTENT_RELATIONSHIP;

		$wpdb->delete(
			$table_name,
			array(
				'content_id' => $quiz_id,
			),
			array(
				'%d',
				'%d',
			)
		);

		$table_name = $wpdb->prefix . 'ohmylms_user_progress';
		$wpdb->delete(
			$table_name,
			array(
				'content_id' => $quiz_id,
			),
			array(
				'%d',
				'%d',
			)
		);

		// Step 3: Get all question IDs linked to the quiz.
		$relationship_table = $wpdb->prefix . 'ohmylms_quiz_questions_relationship';
		$question_ids       = $wpdb->get_col(
			$wpdb->prepare( 'SELECT question_id FROM %i WHERE quiz_id = %d', $relationship_table, $quiz_id )
		);

		if ( ! empty( $question_ids ) ) {
			// Step 4: Get all answer IDs for the above question IDs.
			$answers_table = $wpdb->prefix . 'ohmylms_question_answers';
			$placeholders  = implode( ',', array_fill( 0, count( $question_ids ), '%d' ) );
			$answer_ids    = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$answers_table} WHERE question_id IN ($placeholders)",
					...$question_ids
				)
			);

			// Step 5: Delete answer meta.
			if ( ! empty( $answer_ids ) ) {
				$ans_meta_table    = $wpdb->prefix . 'ohmylms_question_answermeta';
				$meta_placeholders = implode( ',', array_fill( 0, count( $answer_ids ), '%d' ) );
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$ans_meta_table} WHERE answer_id IN ($meta_placeholders)",
						...$answer_ids
					)
				);
			}

			// Step 6: Delete answers (options).
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$answers_table} WHERE question_id IN ($placeholders)",
					...$question_ids
				)
			);
		}

		// Step 7: Delete quiz-question relationship.
		$wpdb->delete(
			$relationship_table,
			array( 'quiz_id' => $quiz_id ),
			array( '%d' )
		);
	}
}
