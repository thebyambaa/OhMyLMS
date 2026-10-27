<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Factory;

use OhMyLMS\Data\Question;

/**
 * Plugin question factory implementation.
 */
class QuestionFactory {

	/**
	 * Get question object
	 *
	 * @param bool $question_id Question identifier.
	 * @return bool|question
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function get_question( $question_id = false ) {
		$question_id = $this->get_question_id( $question_id );

		if ( ! $question_id ) {
			return false;
		}

		return new Question( $question_id );
	}


	/**
	 * Get question id
	 *
	 * @param mixed $question Question.
	 * @return bool|int
	 * @since 1.0.0
	 */
	private function get_question_id( $question ) {
		global $post;

		if ( false === $question && isset( $post, $post->ID ) && OHMYLMS_QUESTION_CPT === get_post_type( $post->ID ) ) {
			return absint( $post->ID );
		} elseif ( is_numeric( $question ) ) {
			return $question;
		} elseif ( $question instanceof Question ) {
			return $question->get_id();
		} elseif ( ! empty( $question->ID ) ) {
			return $question->ID;
		} else {
			return false;
		}
	}
}
