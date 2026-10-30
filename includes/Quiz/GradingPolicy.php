<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Who may review attempts of a quiz and grade them by hand.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Quiz;

defined( 'ABSPATH' ) || exit;

/**
 * Who may review attempts of a quiz and grade them by hand.
 *
 * The rule is deliberately narrower than "may edit posts": a user may see student attempts (names,
 * emails and answers) and change marks only when they are an administrator (`manage_options`), the
 * author of the quiz, or the author of the course that contains the quiz. Authors also need the
 * `edit_post` capability for the quiz or course, so a revoked author role loses access.
 */
final class GradingPolicy {

	/**
	 * Whether a user may review and grade the attempts of a quiz.
	 *
	 * @param int $quiz_id Quiz post ID.
	 * @param int $user_id User ID; the current user when 0.
	 * @return bool
	 */
	public static function can_grade( $quiz_id, $user_id = 0 ) {
		$quiz_id = absint( $quiz_id );
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		$quiz    = $quiz_id ? get_post( $quiz_id ) : null;
		if ( ! $user_id || ! $quiz || OHMYLMS_QUIZ_CPT !== $quiz->post_type ) {
			return false;
		}
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}
		if ( (int) $quiz->post_author === $user_id && user_can( $user_id, 'edit_post', $quiz_id ) ) {
			return true;
		}
		$course_id = self::course_id( $quiz_id );
		$course    = $course_id ? get_post( $course_id ) : null;

		return $course && (int) $course->post_author === $user_id && user_can( $user_id, 'edit_post', $course_id );
	}

	/**
	 * IDs of the quizzes a user may grade without being an administrator or an editor of others' posts:
	 * quizzes they wrote plus quizzes inside courses they wrote.
	 *
	 * @param int $user_id User ID.
	 * @return int[] Quiz IDs, at most 5000.
	 */
	public static function quiz_ids_for_author( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}
		$own    = $wpdb->get_col(
			$wpdb->prepare( 'SELECT ID FROM %i WHERE post_type = %s AND post_author = %d AND post_status <> \'auto-draft\' LIMIT 5000', $wpdb->posts, OHMYLMS_QUIZ_CPT, $user_id )
		);
		$course = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT DISTINCT content.content_id FROM %i content
				INNER JOIN %i chapter ON chapter.chapter_id = content.chapter_id
				INNER JOIN %i course ON course.ID = chapter.course_id AND course.post_author = %d
				INNER JOIN %i quiz ON quiz.ID = content.content_id AND quiz.post_type = %s AND quiz.post_status <> \'auto-draft\'
				LIMIT 5000',
				$wpdb->prefix . 'ohmylms_content_relationship',
				$wpdb->prefix . 'ohmylms_chapter_relationship',
				$wpdb->posts,
				$user_id,
				$wpdb->posts,
				OHMYLMS_QUIZ_CPT
			)
		);

		return array_values( array_unique( array_map( 'intval', array_merge( $own, $course ) ) ) );
	}

	/**
	 * Course that contains a quiz through a chapter, or 0.
	 *
	 * @param int $quiz_id Quiz post ID.
	 * @return int
	 */
	public static function course_id( $quiz_id ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT chapter.course_id FROM %i content INNER JOIN %i chapter ON chapter.chapter_id = content.chapter_id WHERE content.content_id = %d LIMIT 1',
				$wpdb->prefix . 'ohmylms_content_relationship',
				$wpdb->prefix . 'ohmylms_chapter_relationship',
				absint( $quiz_id )
			)
		);
	}
}
