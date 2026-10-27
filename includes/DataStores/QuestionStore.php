<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class QuestionStore
 * Handles CRUD operations for Question data.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\DataStores;

use OhMyLMS\Abstracts\DataStore;
use OhMyLMS\Data\Question;

defined( 'ABSPATH' ) || exit;

/**
 * Class QuestionStore
 * Handles CRUD operations for Question data.
 *
 * @package OhMyLMS\DataStores
 * @since 1.0.0
 */
class QuestionStore extends DataStore {

	/**
	 * Must exist meta keys.
	 *
	 * @var mixed
	 */
	protected $must_exist_meta_keys = array(
		'_questions_settings',
		'_thumbnail_id' => 'thumbnail_id',
		'_video_id'     => 'video_id',
		'_image_id'     => 'image_id',
	);
	/**
	 * Create a new question.
	 *
	 * @param Question $question Question.
	 * @since 1.0.0
	 */
	public function create( &$question ): void {
		if ( ! $question->get_date_created( 'edit' ) ) {
			$question->set_date_created( time() );
		}
		/**
		 * Fires before question add
		 *
		 * @param string $question question object
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_before_creating_new_question', $question );

		$question_id = wp_insert_post(
			apply_filters(
				'ohmylms_new_question_data', // Custom filter hook name.
				array(
					// wp_insert_post() unslashes its input; slash so backslashes (e.g. LaTeX) are kept.
					'post_title'    => wp_slash( $question->get_name() ? $question->get_name() : __( 'Untitled', 'ohmylms' ) ),
					'post_content'  => wp_slash( (string) $question->get_description() ),
					'post_author'   => get_current_user_id(),
					'post_type'     => OHMYLMS_QUESTION_CPT,
					'post_status'   => 'publish',
					'post_name'     => $question->get_slug( 'edit' ),
					'post_date'     => gmdate( 'Y-m-d H:i:s', $question->get_date_created( 'edit' )->getOffsetTimestamp() ),
					'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $question->get_date_created( 'edit' )->getTimestamp() ),
				)
			),
			true
		);
		if ( $question_id && ! is_wp_error( $question_id ) ) {
			$question->set_id( $question_id );
			$this->update_post_meta( $question );

			/**
			 * Fires after question add
			 *
			 * @param string $question question object
			 * @since 1.0.0
			 */
			do_action( 'ohmylms_after_creating_new_question', $question );
		}
	}

	/**
	 * Read for Question store.
	 *
	 * @param mixed $question Question.
	 */
	public function read( &$question ) {
		$post_object = get_post( $question->get_id() );
		if ( ! $question->get_id() || ! $post_object || OHMYLMS_QUESTION_CPT !== $post_object->post_type ) {
			return ( __( 'Invalid Question.', 'ohmylms' ) );
		}

		$question->set_props(
			array(
				'name'          => $post_object->post_title,
				'slug'          => $post_object->post_name,
				'status'        => $post_object->post_status,
				'date_created'  => $post_object->post_date_gmt,
				'date_modified' => $post_object->post_modified_gmt,
				'description'   => $post_object->post_content,
			)
		);

		$this->read_question_data( $question );
	}

	/**
	 * Update an existing question.
	 *
	 * @param Question $question Question.
	 * @return void
	 * @since 1.0.0
	 */
	public function update( &$question ) {
		/**
		 * Before question update
		 *
		 * @param Question $question
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_before_updating_question', $question );

		$post_data = array(
			// wp_update_post() unslashes its input; slash so backslashes (e.g. LaTeX) are kept.
			'post_content' => wp_slash( (string) $question->get_description( 'edit' ) ),
			'post_excerpt' => wp_slash( (string) $question->get_short_description( 'edit' ) ),
			'post_title'   => wp_slash( (string) $question->get_name( 'edit' ) ),
			'post_status'  => $question->get_status( 'edit' ) ? $question->get_status( 'edit' ) : 'publish',
			'post_name'    => $question->get_slug( 'edit' ),
			'post_type'    => OHMYLMS_QUESTION_CPT,
		);
		if ( $question->get_date_created( 'edit' ) ) {
			$post_data['post_date']     = gmdate( 'Y-m-d H:i:s', $question->get_date_created( 'edit' )->getOffsetTimestamp() );
			$post_data['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $question->get_date_created( 'edit' )->getTimestamp() );
		}
		$post_data['post_modified']     = current_time( 'mysql' );
		$post_data['post_modified_gmt'] = current_time( 'mysql', 1 );

		wp_update_post( array_merge( array( 'ID' => $question->get_id() ), $post_data ) );

		$this->update_post_meta( $question );
		/**
		 * Action hook to perform additional actions after a lesson is updated.
		 *
		 * @param int    $question_id The ID of the updated lesson.
		 * @param Question $question    The question object.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_after_updating_question', $question->get_id(), $question );
	}

	/**
	 * Delete a question.
	 *
	 * @param Question $question Question.
	 * @param array    $args Operation arguments.
	 * @since 1.0.0
	 */
	public function delete( &$question, $args = array() ) {

		/**
		 * Before question delete
		 *
		 * @param Question $question
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_before_question_delete', $question );

		wp_delete_post( $question->get_id(), true );

		/**
		 * After question delete
		 *
		 * @param Question $question
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_after_question_delete', $question );

		return array(
			'status'  => 'success',
			'message' => __( 'Question has been deleted.', 'ohmylms' ),
		);
	}

	/**
	 * Read question data for Question store.
	 *
	 * @param mixed $question Question.
	 */
	protected function read_question_data( &$question ) {
		$id               = $question->get_id();
		$post_meta_values = get_post_meta( $id );

		$meta_key_to_props = $this->question_meta_key();

		foreach ( $meta_key_to_props as $meta_key => $prop ) {
			$meta_value         = isset( $post_meta_values[ $meta_key ][0] ) ? $post_meta_values[ $meta_key ][0] : null;
			$set_props[ $prop ] = maybe_unserialize( $meta_value );
		}
		$question->set_props( $set_props );
	}

	/**
	 * Update post meta for Question store.
	 *
	 * @param mixed $question Question.
	 * @param bool  $force Force.
	 */
	protected function update_post_meta( &$question, $force = false ) {
		$props_to_update = $this->question_meta_key();
		foreach ( $props_to_update as $meta_key => $prop ) {
			$value = $question->{"get_$prop"}( 'edit' );
			// WordPress unslashes nested metadata too, including LaTeX in part prompts.
			$value = wp_slash( $value );
			$this->update_or_delete_post_meta( $question, $meta_key, $value );
		}

		/**
		 * Fires after the meta data for a lesson is updated.
		 *
		 * @param WP_Post $question The updated lesson object.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_lesson_meta_updated', $question );
	}

	/**
	 *  Get the question meta key
	 *
	 * @return mixed|null
	 */
	protected function question_meta_key() {
		return apply_filters(
			'ohmylms_question_meta_key_to_props',
			array(
				'_question_settings' => 'settings',
				'_thumbnail_id'      => 'thumbnail_id',
				'_image_id'          => 'image_id',
				'_video_id'          => 'video_id',
			)
		);
	}


	/**
	 * Get questions for Question store.
	 *
	 * @param mixed $question Question.
	 */
	public function get_questions( $question ) {
		global $wpdb;

		$answers_table = $wpdb->prefix . 'ohmylms_question_answers';
		$meta_table    = $wpdb->prefix . 'ohmylms_question_answermeta';
		$question_id   = $question->get_id();

		$results = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE question_id = %d ORDER BY order_number ASC', $answers_table, $question_id ),
			ARRAY_A
		);

		foreach ( $results as &$answer ) {
			$meta_values = array(
				'_thumbnail_id'  => '',
				'_matching_data' => '',
				'_image_url'     => '',
			);

			$answer_meta = $wpdb->get_results(
				$wpdb->prepare( 'SELECT meta_key, meta_value FROM %i WHERE answer_id = %d', $meta_table, $answer['id'] ),
				ARRAY_A
			);

			if ( ! empty( $answer_meta ) ) {
				foreach ( $answer_meta as $meta ) {
					if ( array_key_exists( $meta['meta_key'], $meta_values ) ) {
						$meta_values[ $meta['meta_key'] ] = $meta['meta_value'];
					}
				}
			}
			$answer['thumbnail_id']  = $meta_values['_thumbnail_id'];
			$answer['matching_data'] = maybe_unserialize( $meta_values['_matching_data'] );
			$answer['image_url']     = ! empty( $answer['thumbnail_id'] ) ? wp_get_attachment_url( $answer['thumbnail_id'] ) : '';
		}

		return $results;
	}

	/**
	 * Save attempt answer for Question store.
	 *
	 * @param mixed $question Question.
	 * @param mixed $student_id Student identifier.
	 * @param mixed $data Input data.
	 */
	public function save_attempt_answer( $question, $student_id, $data ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_quiz_attempts_answers';
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$data[ $key ] = maybe_serialize( $value );
			}
		}
		$wpdb->insert( $table_name, $data );
	}

	/**
	 * Legacy option writer. Delegates to the ownership-checked QuestionBank writer so an
	 * answer ID from another question can never be rewritten through this question.
	 *
	 * @return true|\WP_Error
	 * @param mixed $question Question.
	 * @param mixed $questions Questions.
	 * @param mixed $question_id Question identifier.
	 */
	public function save_quiz_answer( $question, $questions, $question_id ) {
		$answers = ! empty( $questions['questions'] ) ? $questions['questions'] : array();
		if ( empty( $answers ) ) {
			return true;
		}
		$saved = \OhMyLMS\QuestionBank\DraftWriter::save(
			array(
				'id'        => (int) $question_id,
				'questions' => $answers,
			)
		);
		return is_wp_error( $saved ) ? $saved : true;
	}
}
