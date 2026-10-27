<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Object-level authorization for question and quiz authoring.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\QuestionBank;

defined( 'ABSPATH' ) || exit;

/**
 * Object-level authorization for question and quiz authoring.
 *
 * Services call these checks themselves, so nested writes (a quiz save that
 * creates or updates questions) cannot bypass the REST permission callbacks.
 */
final class AccessPolicy {
	/** May the current user author new quiz/question content at all? */
	public static function can_author() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Can edit question for Access policy.
	 *
	 * @param mixed $question_id Question identifier.
	 */
	public static function can_edit_question( $question_id ) {
		$question_id = (int) $question_id;
		if ( ! $question_id ) {
			return self::can_author(); }
		if ( get_post_type( $question_id ) !== OHMYLMS_QUESTION_CPT ) {
			return false; }
		return current_user_can( 'edit_post', $question_id ) || self::bank_allows( $question_id, 'edit' );
	}

	/** May the current user approve a version of this question for shared use and practice?
	 *
	 * @param mixed $question_id Question identifier.
	 */
	public static function can_approve_question( $question_id ) {
		$question_id = (int) $question_id;
		if ( ! $question_id || get_post_type( $question_id ) !== OHMYLMS_QUESTION_CPT ) {
			return false; }
		$bank = \OhMyLMS\Assessment\Schema::ready() ? Banks::of_question( $question_id ) : 0;
		return $bank ? Banks::can( $bank, 'approve' ) : current_user_can( 'edit_post', $question_id );
	}

	/**
	 * Bank allows for Access policy.
	 *
	 * @param mixed $question_id Question identifier.
	 * @param mixed $permission Permission.
	 */
	private static function bank_allows( $question_id, $permission ) {
		if ( ! \OhMyLMS\Assessment\Schema::ready() ) {
			return false; }
		$bank = Banks::of_question( $question_id );
		return $bank > 0 && Banks::can( $bank, $permission );
	}

	/**
	 * Can delete question for Access policy.
	 *
	 * @param mixed $question_id Question identifier.
	 */
	public static function can_delete_question( $question_id ) {
		$question_id = (int) $question_id;
		return $question_id && get_post_type( $question_id ) === OHMYLMS_QUESTION_CPT && current_user_can( 'delete_post', $question_id );
	}

	/**
	 * May the current user place this question in their assessments without editing it?
	 * Editors of the question may always use it; shared-bank grants extend this.
	 *
	 * @param mixed $question_id Question identifier.
	 */
	public static function can_use_question( $question_id ) {
		$question_id = (int) $question_id;
		if ( ! $question_id || get_post_type( $question_id ) !== OHMYLMS_QUESTION_CPT ) {
			return false; }
		if ( current_user_can( 'edit_post', $question_id ) || self::bank_allows( $question_id, 'use' ) ) {
			return true; }
		return (bool) apply_filters( 'ohmylms_can_use_question', false, $question_id, get_current_user_id() );
	}

	/**
	 * Can edit quiz for Access policy.
	 *
	 * @param mixed $quiz_id Quiz identifier.
	 */
	public static function can_edit_quiz( $quiz_id ) {
		$quiz_id = (int) $quiz_id;
		if ( ! $quiz_id ) {
			return self::can_author(); }
		return get_post_type( $quiz_id ) === OHMYLMS_QUIZ_CPT && current_user_can( 'edit_post', $quiz_id );
	}

	/**
	 * Can delete quiz for Access policy.
	 *
	 * @param mixed $quiz_id Quiz identifier.
	 */
	public static function can_delete_quiz( $quiz_id ) {
		$quiz_id = (int) $quiz_id;
		return $quiz_id && get_post_type( $quiz_id ) === OHMYLMS_QUIZ_CPT && current_user_can( 'delete_post', $quiz_id );
	}

	/** Grading and attempt reports belong to whoever may edit the quiz.
	 *
	 * @param mixed $quiz_id Quiz identifier.
	 */
	public static function can_grade_quiz( $quiz_id ) {
		return (int) $quiz_id > 0 && self::can_edit_quiz( $quiz_id );
	}

	/** WP_Error for a failed check, using the REST convention of 401 for guests and 403 otherwise.
	 *
	 * @param string $message Message.
	 */
	public static function denied( $message = '' ) {
		return new \WP_Error(
			'ohmylms_forbidden',
			$message ? $message : __( 'You are not allowed to change this content.', 'ohmylms' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/** Returns true or a WP_Error; for REST permission callbacks and service guards.
	 *
	 * @param mixed  $allowed Allowed.
	 * @param string $message Message.
	 */
	public static function check( $allowed, $message = '' ) {
		return $allowed ? true : self::denied( $message );
	}
}
