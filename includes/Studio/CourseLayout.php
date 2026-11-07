<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Storage for the course structure the front-end studios read and write.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Studio;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the chapter, content and quiz-question relationship tables for the studio endpoints.
 *
 * The REST controller decides who may do what and shapes the response; this class only talks to the
 * tables, so the queries live in one place and the controller stays free of `$wpdb`.
 */
final class CourseLayout {

	/**
	 * Units of a course in their saved order.
	 *
	 * @param int $course Course ID.
	 * @return object[] Rows with `chapter_id` and `order_number`.
	 */
	public static function chapters( $course ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT chapter_id, order_number FROM %i WHERE course_id=%d ORDER BY order_number, chapter_id', $wpdb->prefix . 'ohmylms_chapter_relationship', $course ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (array) $rows;
	}

	/**
	 * Content placed in a unit, in its saved order.
	 *
	 * @param int $chapter Chapter ID.
	 * @return object[] Rows with `content_id`, `content_type` and `order_number`.
	 */
	public static function contents( $chapter ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT content_id, content_type, order_number FROM %i WHERE chapter_id=%d ORDER BY order_number, content_id', $wpdb->prefix . 'ohmylms_content_relationship', $chapter ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (array) $rows;
	}

	/**
	 * Whether the chapter belongs to the course.
	 *
	 * @param int $chapter Chapter ID.
	 * @param int $course  Course ID.
	 * @return bool
	 */
	public static function chapter_in_course( $chapter, $course ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE course_id=%d AND chapter_id=%d', $wpdb->prefix . 'ohmylms_chapter_relationship', $course, $chapter ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * The course a chapter belongs to.
	 *
	 * @param int $chapter Chapter ID.
	 * @return int Course ID, or 0 when the chapter is in no course.
	 */
	public static function course_of( $chapter ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT course_id FROM %i WHERE chapter_id=%d LIMIT 1', $wpdb->prefix . 'ohmylms_chapter_relationship', $chapter ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Move a unit to a position within its course.
	 *
	 * @param int $course   Course ID.
	 * @param int $chapter  Chapter ID.
	 * @param int $position Position, from 1.
	 * @return void
	 */
	public static function set_chapter_position( $course, $chapter, $position ) {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'ohmylms_chapter_relationship',
			array( 'order_number' => $position ),
			array(
				'course_id'  => $course,
				'chapter_id' => $chapter,
			),
			array( '%d' ),
			array( '%d', '%d' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Move content to a position within its unit.
	 *
	 * @param int $chapter  Chapter ID.
	 * @param int $content  Content post ID.
	 * @param int $position Position, from 1.
	 * @return void
	 */
	public static function set_content_position( $chapter, $content, $position ) {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'ohmylms_content_relationship',
			array( 'order_number' => $position ),
			array(
				'chapter_id' => $chapter,
				'content_id' => $content,
			),
			array( '%d' ),
			array( '%d', '%d' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Add a unit after the last one of a course.
	 *
	 * @param int $course  Course ID.
	 * @param int $chapter Chapter ID.
	 * @return void
	 */
	public static function append_chapter( $course, $chapter ) {
		global $wpdb;
		$next = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(order_number) FROM %i WHERE course_id=%d', $wpdb->prefix . 'ohmylms_chapter_relationship', $course ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prefix . 'ohmylms_chapter_relationship',
			array(
				'course_id'    => $course,
				'chapter_id'   => $chapter,
				'order_number' => $next + 1,
			),
			array( '%d', '%d', '%d' )
		);
	}

	/**
	 * Add content after the last item of a unit.
	 *
	 * @param int    $chapter Chapter ID.
	 * @param int    $content Content post ID.
	 * @param string $type    Content type stored with the placement.
	 * @return void
	 */
	public static function append_content( $chapter, $content, $type ) {
		global $wpdb;
		$next = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(order_number) FROM %i WHERE chapter_id=%d', $wpdb->prefix . 'ohmylms_content_relationship', $chapter ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prefix . 'ohmylms_content_relationship',
			array(
				'chapter_id'   => $chapter,
				'content_id'   => $content,
				'content_type' => $type,
				'order_number' => $next + 1,
			),
			array( '%d', '%d', '%s', '%d' )
		);
	}

	/**
	 * Questions placed on a quiz, in their saved order.
	 *
	 * @param int $quiz Quiz ID.
	 * @return object[] Rows with `question_id`.
	 */
	public static function quiz_questions( $quiz ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT question_id FROM %i WHERE quiz_id=%d ORDER BY order_number, question_id', $wpdb->prefix . 'ohmylms_quiz_questions_relationship', $quiz ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (array) $rows;
	}

	/**
	 * Answers of a question, in their saved order.
	 *
	 * @param int $question Question post ID.
	 * @return object[] Rows with `id`, `answer` and `is_correct`.
	 */
	public static function answers( $question ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, answer, is_correct FROM %i WHERE question_id=%d ORDER BY order_number, id', $wpdb->prefix . 'ohmylms_question_answers', $question ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (array) $rows;
	}

	/**
	 * Quizzes that list a skill, counted from the quiz-skills table when it exists.
	 *
	 * @param int $term Skill term ID.
	 * @return int
	 */
	public static function quiz_skill_uses( $term ) {
		global $wpdb;
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'ohmylms_quiz_skills' ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			return 0;
		}
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE term_id=%d', $wpdb->prefix . 'ohmylms_quiz_skills', $term ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
