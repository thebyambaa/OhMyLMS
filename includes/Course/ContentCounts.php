<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Units and content counted per course.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Course;

defined( 'ABSPATH' ) || exit;

/**
 * How many units, lessons, quizzes and assignments each course holds, for several courses with two queries.
 */
final class ContentCounts {

	/**
	 * Counts for several courses.
	 *
	 * Lessons include videos and articles; they are stored with the content types `lesson`, `video` and
	 * `text`. A course with no units reads as all zeros.
	 *
	 * @param int[] $course_ids Course IDs.
	 * @return array<int,array{units:int,lessons:int,quizzes:int,assignments:int}> Course ID => counts.
	 */
	public static function by_course( array $course_ids ) {
		global $wpdb;
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $course_ids ) ) ) );
		$out = array();
		foreach ( $ids as $id ) {
			$out[ $id ] = array(
				'units'       => 0,
				'lessons'     => 0,
				'quizzes'     => 0,
				'assignments' => 0,
			);
		}
		if ( ! $ids ) {
			return $out;
		}
		$holders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$chapter = $wpdb->prefix . 'ohmylms_chapter_relationship';
		$content = $wpdb->prefix . 'ohmylms_content_relationship';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- Placeholders are generated from the ID count; read-only counts for one page of courses.
		$units = $wpdb->get_results( $wpdb->prepare( "SELECT course_id, COUNT(*) AS total FROM %i WHERE course_id IN ({$holders}) GROUP BY course_id", array_merge( array( $chapter ), $ids ) ) );
		$items = $wpdb->get_results( $wpdb->prepare( "SELECT c.course_id, r.content_type, COUNT(*) AS total FROM %i r JOIN %i c ON c.chapter_id = r.chapter_id WHERE c.course_id IN ({$holders}) GROUP BY c.course_id, r.content_type", array_merge( array( $content, $chapter ), $ids ) ) );
		// phpcs:enable
		foreach ( (array) $units as $row ) {
			$out[ (int) $row->course_id ]['units'] = (int) $row->total;
		}
		$group = array(
			'lesson'     => 'lessons',
			'video'      => 'lessons',
			'text'       => 'lessons',
			'quiz'       => 'quizzes',
			'assignment' => 'assignments',
		);
		foreach ( (array) $items as $row ) {
			$key = $group[ (string) $row->content_type ] ?? '';
			if ( '' !== $key ) {
				$out[ (int) $row->course_id ][ $key ] += (int) $row->total;
			}
		}
		// The course builder saves activities in the course program, so it wins over the older relationship tables.
		foreach ( $ids as $id ) {
			$placed = self::from_program( $id, $group );
			if ( null !== $placed ) {
				$out[ $id ] = array( 'units' => $out[ $id ]['units'] ) + $placed;
			}
		}
		return $out;
	}

	/**
	 * Lessons, quizzes and assignments placed in a course's published program, or its draft before the first publish.
	 *
	 * @param int      $course_id Course ID.
	 * @param string[] $group     Content type => count key.
	 * @return array{lessons:int,quizzes:int,assignments:int}|null Null when the course has no program items.
	 */
	private static function from_program( $course_id, array $group ) {
		$program = \OhMyLMS\Learning\CourseProgram::current( $course_id );
		if ( ! is_array( $program ) || empty( $program['items'] ) ) {
			$program = get_post_meta( $course_id, \OhMyLMS\Learning\CourseProgram::DRAFT, true );
		}
		if ( ! is_array( $program ) || empty( $program['items'] ) || ! is_array( $program['items'] ) ) {
			return null;
		}
		$counts = array(
			'lessons'     => 0,
			'quizzes'     => 0,
			'assignments' => 0,
		);
		foreach ( $program['items'] as $item ) {
			$key = $group[ (string) ( $item['type'] ?? '' ) ] ?? '';
			if ( '' !== $key ) {
				++$counts[ $key ];
			}
		}
		return $counts;
	}
}
