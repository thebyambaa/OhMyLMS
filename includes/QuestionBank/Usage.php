<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Where a question is used, and the three distinct removal operations:
 * remove from one quiz, archive (keeps history), and delete when nothing uses it.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\QuestionBank;

use OhMyLMS\Utility\Transaction;

defined( 'ABSPATH' ) || exit;

/**
 * Where a question is used, and the three distinct removal operations:
 * remove from one quiz, archive (keeps history), and delete when nothing uses it.
 */
final class Usage {
	const ARCHIVED = 'ohmylms_archived';

	/**
	 * Register status for Usage.
	 */
	public static function register_status() {
		register_post_status(
			self::ARCHIVED,
			array(
				'label'                     => _x( 'Archived', 'question status', 'ohmylms' ),
				'public'                    => false,
				'internal'                  => false,
				'protected'                 => true,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => false,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of archived questions */
				'label_count'               => _n_noop( 'Archived <span class="count">(%s)</span>', 'Archived <span class="count">(%s)</span>', 'ohmylms' ),
			)
		);
	}

	/**
	 * A question post deleted outside these services (e.g. wp_delete_post): drop its bank
	 * identity and versions only when no learner record references them; otherwise keep
	 * the frozen history so old attempts still report what was seen.
	 *
	 * @param mixed $post_id Post identifier.
	 */
	public static function on_deleted_post( $post_id ) {
		global $wpdb;
		if ( get_post_type( $post_id ) !== OHMYLMS_QUESTION_CPT || ! \OhMyLMS\Assessment\Schema::ready() ) {
			return; }
		$wpdb->delete( $wpdb->prefix . OHMYLMS_QUIZ_QUESTION_RELATIONSHIP, array( 'question_id' => (int) $post_id ) );
		if ( self::responses( $post_id ) > 0 ) {
			return; }
		$versions = \OhMyLMS\Assessment\Schema::table( 'qb_question_versions' );
		$wpdb->query( $wpdb->prepare( 'DELETE s FROM %i s JOIN %i v ON v.id=s.version_id WHERE v.question_id=%d', \OhMyLMS\Assessment\Schema::table( 'qb_version_skills' ), $versions, (int) $post_id ) );
		$wpdb->delete( $versions, array( 'question_id' => (int) $post_id ) );
		$wpdb->delete( \OhMyLMS\Assessment\Schema::table( 'qb_questions' ), array( 'question_id' => (int) $post_id ) );
	}

	/**
	 * Quizzes for Usage.
	 *
	 * @return int[] quiz IDs that currently contain the question.
	 * @param mixed $question_id Question identifier.
	 */
	public static function quizzes( $question_id ) {
		global $wpdb;
		$table = $wpdb->prefix . OHMYLMS_QUIZ_QUESTION_RELATIONSHIP;
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT quiz_id FROM %i WHERE question_id=%d', $table, $question_id ) ) );
	}

	/** Learner records (any surface, including issued-but-unanswered items) that reference the question.
	 *
	 * @param mixed $question_id Question identifier.
	 */
	public static function responses( $question_id ) {
		global $wpdb;
		$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE question_id=%d', $wpdb->prefix . 'ohmylms_quiz_attempts_answers', $question_id ) );
		if ( \OhMyLMS\Assessment\Schema::ready() ) {
			foreach ( array( 'attempt_items', 'practice_items' ) as $table ) {
				$count += (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . \OhMyLMS\Assessment\Schema::table( $table ) . ' WHERE question_id=%d', $question_id ) );
			}
		}
		return (int) apply_filters( 'ohmylms_question_response_count', $count, (int) $question_id );
	}

	/** Remove the question from one quiz only. The question itself is kept.
	 *
	 * @param mixed $quiz_id Quiz identifier.
	 * @param mixed $question_id Question identifier.
	 */
	public static function remove_from_quiz( $quiz_id, $question_id ) {
		$quiz_id     = (int) $quiz_id;
		$question_id = (int) $question_id;
		if ( ! AccessPolicy::can_edit_quiz( $quiz_id ) || ! $quiz_id ) {
			return AccessPolicy::denied( __( 'You are not allowed to edit this quiz.', 'ohmylms' ) ); }
		if ( get_post_type( $question_id ) !== OHMYLMS_QUESTION_CPT ) {
			return new \WP_Error( 'ohmylms_rest_question_invalid_id', __( 'Question ID is invalid.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		global $wpdb;
		$removed = $wpdb->delete(
			$wpdb->prefix . OHMYLMS_QUIZ_QUESTION_RELATIONSHIP,
			array(
				'quiz_id'     => $quiz_id,
				'question_id' => $question_id,
			),
			array( '%d', '%d' )
		);
		if ( false === $removed ) {
			return new \WP_Error( 'ohmylms_question_storage', __( 'Could not remove the question.', 'ohmylms' ), array( 'status' => 500 ) ); }
		if ( ! $removed ) {
			return new \WP_Error( 'ohmylms_question_not_in_quiz', __( 'The question is not part of this quiz.', 'ohmylms' ), array( 'status' => 404 ) ); }
		do_action( 'ohmylms_quiz_question_removed', $quiz_id, $question_id );
		return array(
			'id'      => $question_id,
			'quiz_id' => $quiz_id,
			'action'  => 'removed',
		);
	}

	/**
	 * Archive for Usage.
	 *
	 * @param mixed $question_id Question identifier.
	 */
	public static function archive( $question_id ) {
		$question_id = (int) $question_id;
		if ( ! AccessPolicy::can_edit_question( $question_id ) || ! $question_id ) {
			return AccessPolicy::denied(); }
		$result = wp_update_post(
			array(
				'ID'          => $question_id,
				'post_status' => self::ARCHIVED,
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 500 ) );
			return $result; }
		if ( \OhMyLMS\Assessment\Schema::ready() ) {
			\OhMyLMS\QuestionBank\VersionPublisher::identity( $question_id );
			$wpdb_status = $GLOBALS['wpdb'];
			$wpdb_status->update( \OhMyLMS\Assessment\Schema::table( 'qb_questions' ), array( 'status' => 'archived' ), array( 'question_id' => $question_id ) );
		}
		do_action( 'ohmylms_question_archived', $question_id );
		return array(
			'id'     => $question_id,
			'action' => 'archived',
		);
	}

	/**
	 * Restore for Usage.
	 *
	 * @param mixed $question_id Question identifier.
	 */
	public static function restore( $question_id ) {
		$question_id = (int) $question_id;
		if ( ! AccessPolicy::can_edit_question( $question_id ) || ! $question_id ) {
			return AccessPolicy::denied(); }
		$result = wp_update_post(
			array(
				'ID'          => $question_id,
				'post_status' => 'publish',
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 500 ) );
			return $result; }
		if ( \OhMyLMS\Assessment\Schema::ready() ) {
			$identity = \OhMyLMS\QuestionBank\VersionPublisher::identity( $question_id );
			$GLOBALS['wpdb']->update( \OhMyLMS\Assessment\Schema::table( 'qb_questions' ), array( 'status' => ! empty( $identity['approved_version_id'] ) ? 'approved' : 'draft' ), array( 'question_id' => $question_id ) );
		}
		return array(
			'id'     => $question_id,
			'action' => 'restored',
		);
	}

	/**
	 * Legacy "delete question" without quiz context.
	 *
	 * Deletes only when nothing references the question. A question with learner history is
	 * archived and detached from its single quiz; a question shared by several quizzes must be
	 * removed from a specific quiz instead.
	 *
	 * @param mixed $question_id Question identifier.
	 */
	public static function delete_or_archive( $question_id ) {
		$question_id = (int) $question_id;
		if ( ! AccessPolicy::can_delete_question( $question_id ) ) {
			return AccessPolicy::denied( __( 'You are not allowed to delete this question.', 'ohmylms' ) ); }
		$quizzes = self::quizzes( $question_id );
		if ( count( $quizzes ) > 1 ) {
			return new \WP_Error(
				'ohmylms_question_shared',
				__( 'This question is used in several quizzes. Remove it from a specific quiz instead.', 'ohmylms' ),
				array(
					'status'  => 409,
					'quizzes' => $quizzes,
				)
			);
		}
		$responses = self::responses( $question_id );
		try {
			return Transaction::run(
				static function () use ( $question_id, $quizzes, $responses ) {
					global $wpdb;
					foreach ( $quizzes as $quiz_id ) {
						if ( $wpdb->delete(
							$wpdb->prefix . OHMYLMS_QUIZ_QUESTION_RELATIONSHIP,
							array(
								'quiz_id'     => $quiz_id,
								'question_id' => $question_id,
							),
							array( '%d', '%d' )
						) === false ) {
							throw new \RuntimeException( 'Unlink failed' ); }
					}
					if ( $responses > 0 || apply_filters( 'ohmylms_question_has_history', false, $question_id ) ) {
						$archived = self::archive( $question_id );
						if ( is_wp_error( $archived ) ) {
							throw new \RuntimeException( $archived->get_error_message() ); }
						return $archived;
					}
					$question = ohmylms_get_question( $question_id );
					$question->delete();
					$answers = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE question_id=%d', $wpdb->prefix . 'ohmylms_question_answers', $question_id ) );
					foreach ( $answers as $answer_id ) {
						$wpdb->delete( $wpdb->prefix . 'ohmylms_question_answermeta', array( 'answer_id' => (int) $answer_id ), array( '%d' ) ); }
					$wpdb->delete( $wpdb->prefix . 'ohmylms_question_answers', array( 'question_id' => $question_id ), array( '%d' ) );
					if ( \OhMyLMS\Assessment\Schema::ready() ) {
						// Nothing references these versions (no responses or issued items), so they can go too.
						$versions = \OhMyLMS\Assessment\Schema::table( 'qb_question_versions' );
						$wpdb->query( $wpdb->prepare( 'DELETE s FROM %i s JOIN %i v ON v.id=s.version_id WHERE v.question_id=%d', \OhMyLMS\Assessment\Schema::table( 'qb_version_skills' ), $versions, $question_id ) );
						$wpdb->delete( $versions, array( 'question_id' => $question_id ) );
						$wpdb->delete( \OhMyLMS\Assessment\Schema::table( 'qb_questions' ), array( 'question_id' => $question_id ) );
					}
					return array(
						'id'     => $question_id,
						'action' => 'deleted',
					);
				}
			);
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'ohmylms_question_storage', __( 'Could not delete the question. Nothing was changed.', 'ohmylms' ), array( 'status' => 500 ) );
		}
	}
}
