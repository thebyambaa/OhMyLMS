<?php // phpcs:ignore WordPress.Files.FileName -- Required Composer PSR-4 class/file mapping.
/**
 * Old lesson-group page addresses (`?hub_lesson=<uuid>`) land on the group's unit.
 *
 * Lesson groups were flattened into their unit in b0e21ca5, so the group page is gone. Bookmarks and
 * links in mail point at it; they move permanently to the unit's section of the course overview.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use OhMyLMS\Learning\CourseProgram;
use OhMyLMS\Learning\UnitSnapshots;

defined( 'ABSPATH' ) || exit;

/** Read-only redirect; nothing generates these addresses any more. */
final class LegacyLessonLinks {
	const QUERY_VAR = 'hub_lesson';

	/** Hook the query variable and the redirect. */
	public static function init() {
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		// Before Hub::redirect_course_page (6), so a course URL moves once, straight to the unit.
		add_action( 'template_redirect', array( __CLASS__, 'redirect' ), 4 );
	}

	/**
	 * Keep reading the old parameter.
	 *
	 * @param array $vars Registered query variables.
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/** Send an old group page request to its unit with a 301. */
	public static function redirect() {
		$lesson = get_query_var( self::QUERY_VAR, '' );
		if ( ! is_string( $lesson ) || '' === $lesson ) {
			return;
		}
		$course_id = is_singular( OHMYLMS_COURSE_CPT ) ? get_queried_object_id() : absint( get_query_var( 'hub_course', 0 ) );
		if ( ! $course_id || OHMYLMS_COURSE_CPT !== get_post_type( $course_id ) ) {
			return;
		}
		wp_safe_redirect( self::target( $course_id, $lesson ), 301 );
		exit;
	}

	/**
	 * The course overview, at the old group's unit when the learner's outline still lists it.
	 *
	 * @param int    $course_id Course ID.
	 * @param string $lesson_id Old lesson group UUID.
	 * @return string
	 */
	public static function target( $course_id, $lesson_id ) {
		$base    = is_user_logged_in() ? PlayerContext::overview_url( $course_id ) : get_permalink( $course_id );
		$unit_id = wp_is_uuid( $lesson_id ) ? self::unit_of( $course_id, $lesson_id, get_current_user_id() ) : 0;
		$outline = $unit_id ? CourseOutline::get( $course_id, get_current_user_id() ) : null;
		return $base . ( is_array( $outline ) ? LessonJourney::unit_anchor( $outline, $unit_id ) : '' );
	}

	/**
	 * The unit that held an old group, from publications stored before the flattening.
	 *
	 * @param int    $course_id Course ID.
	 * @param string $lesson_id Old lesson group UUID.
	 * @param int    $student_id Current learner, or 0 for a visitor.
	 * @return int Unit (chapter) ID, or 0 when no publication knows the group.
	 */
	public static function unit_of( $course_id, $lesson_id, $student_id ) {
		foreach ( array( UnitSnapshots::published_program( $course_id, $student_id ), CourseProgram::current( $course_id ) ) as $program ) {
			foreach ( (array) ( $program['groups'] ?? array() ) as $group ) {
				if ( is_array( $group ) && ( $group['id'] ?? '' ) === $lesson_id ) {
					return (int) ( $group['chapter_id'] ?? 0 );
				}
			}
		}
		return 0;
	}
}
