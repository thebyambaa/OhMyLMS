<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Which courses place a lesson, quiz or assignment in their published learning program.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Learning;

defined( 'ABSPATH' ) || exit;

/**
 * Which courses place a lesson, quiz or assignment in their published learning program.
 *
 * Lessons were always reusable inside learning programs. Quizzes and assignments used to be tied to
 * the one course whose chapters held them. With the Content Hub they can be placed in several
 * grades and exams, so a piece of content can resolve to more than one course:
 *
 * - its home course, from the original chapter relationship (unchanged, and still the default);
 * - every course whose published program places it.
 *
 * `resolve()` picks the course the current learner is enrolled in (home first), so existing content
 * that has a single course behaves exactly as before. Attempt history is shared across all of them.
 */
final class Placements {
	const OPTION = 'ohmylms_learning_placements';
	const TYPES  = array( 'lesson', 'quiz', 'assignment' );

	/**
	 * Index.
	 *
	 * @var mixed
	 */
	private static $index;
	/**
	 * Enrolled.
	 *
	 * @var mixed
	 */
	private static $enrolled = array();

	/**
	 * Pure: content ID => course IDs, from rows of `[course_id, program JSON or array]`.
	 *
	 * @param array<int, array{0:int,1:string|array}> $rows Rows.
	 * @return array<int, int[]>
	 */
	public static function index_from( array $rows ) {
		$index = array();
		foreach ( $rows as $row ) {
			$course  = (int) $row[0];
			$program = is_array( $row[1] ) ? $row[1] : json_decode( (string) $row[1], true );
			foreach ( (array) ( $program['items'] ?? array() ) as $item ) {
				if ( ! is_array( $item ) || ! in_array( $item['type'] ?? '', self::TYPES, true ) || empty( $item['content_id'] ) ) {
					continue; }
				$index[ (int) $item['content_id'] ][ $course ] = $course;
			}
		}
		foreach ( $index as $content => $courses ) {
			sort( $courses );
			$index[ $content ] = array_values( $courses ); }
		return $index;
	}

	/**
	 * Pure: the course to use among candidates. The first candidate the learner is enrolled in wins,
	 * so the home course stays the default; with no enrollment (or no learner) the first candidate.
	 *
	 * @param int[]    $candidates Home course first.
	 * @param callable $enrolled fn(int $course): bool.
	 */
	public static function choose( array $candidates, callable $enrolled ) {
		$candidates = array_values( array_unique( array_filter( array_map( 'intval', $candidates ) ) ) );
		if ( ! $candidates ) {
			return 0; }
		foreach ( $candidates as $course ) {
			if ( $enrolled( $course ) ) {
				return $course; }
		}
		return $candidates[0];
	}

	/**
	 * Index for Placements.
	 */
	public static function index() {
		if ( self::$index !== null ) {
			return self::$index; }
		$stored      = get_option( self::OPTION, null );
		self::$index = is_array( $stored ) ? $stored : self::rebuild();
		return self::$index;
	}

	/**
	 * Rebuild for Placements.
	 */
	public static function rebuild() {
		global $wpdb;
		if ( ! Schema::ready() ) {
			return self::$index = array(); }
		$rows  = $wpdb->get_results(
			$wpdb->prepare( 'SELECT m.post_id AS course_id, p.program FROM %i m JOIN %i p ON p.id = m.meta_value JOIN %i c ON c.ID = m.post_id WHERE m.meta_key = %s AND c.post_type = %s AND c.post_status IN (\'publish\',\'private\')', $wpdb->postmeta, Schema::table( 'programs' ), $wpdb->posts, CourseProgram::CURRENT, OHMYLMS_COURSE_CPT ),
			ARRAY_N
		);
		$index = self::index_from( is_array( $rows ) ? $rows : array() );
		update_option( self::OPTION, $index, false );
		return self::$index = $index;
	}

	/** Drop the derived index; it is rebuilt on next use. Call when a program is published or a course changes state. */
	public static function flush() {
		self::$index    = null;
		self::$enrolled = array();
		delete_option( self::OPTION );
	}

	/**
	 * Courses for for Placements.
	 *
	 * @return int[] Courses whose published program places this content.
	 * @param mixed $content_id Content identifier.
	 */
	public static function courses_for( $content_id ) {
		return self::index()[ (int) $content_id ] ?? array();
	}

	/**
	 * Enrolled for Placements.
	 *
	 * @param mixed $student_id Student identifier.
	 * @param mixed $course_id Course identifier.
	 */
	private static function enrolled( $student_id, $course_id ) {
		global $wpdb;
		$key = $student_id . ':' . $course_id;
		if ( ! isset( self::$enrolled[ $key ] ) ) {
			self::$enrolled[ $key ] = (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE user_id=%d AND course_id=%d AND status=\'enrolled\' LIMIT 1', $wpdb->prefix . 'ohmylms_user_enrollment', $student_id, $course_id ) );
		}
		return self::$enrolled[ $key ];
	}

	/**
	 * The course a piece of content is opened in. `$home` is its original chapter-based course (or
	 * null); it is returned untouched unless a published program places the content too.
	 *
	 * @param mixed $content_id Content identifier.
	 * @param mixed $home Home.
	 * @param int   $student_id Student identifier.
	 */
	public static function resolve( $content_id, $home, $student_id = 0 ) {
		$placed = self::courses_for( $content_id );
		if ( ! $placed ) {
			return $home; }
		$candidates = array_values( array_unique( array_filter( array_merge( array( (int) $home ), $placed ) ) ) );
		if ( count( $candidates ) === 1 ) {
			return $candidates[0]; }
		$student_id = (int) ( $student_id ? $student_id : get_current_user_id() );
		return self::choose(
			$candidates,
			static function ( $course ) use ( $student_id ) {
				return 0 < $student_id && self::enrolled( $student_id, $course );
			}
		);
	}
}
