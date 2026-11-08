<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Publishing a course publishes its learning program.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Learning;

defined( 'ABSPATH' ) || exit;

/**
 * Learners read the published learning program, not the course post, so a course set to Published in its
 * Basics with no program published shows them an empty course. When a course goes live without one, its
 * saved outline is published with it, if it is ready. A course whose outline is not ready (for example
 * one with no activities yet) still goes live, and Preview and publish says what is missing.
 */
final class AutoPublish {

	/**
	 * Publish the saved outline of a course that has none published.
	 *
	 * @param int $course_id Course ID.
	 * @return bool True when a program was published now.
	 */
	public static function program( $course_id ) {
		$course_id = (int) $course_id;
		if ( ! Schema::ready() || get_post_meta( $course_id, CourseProgram::CURRENT, true ) ) {
			return false;
		}
		try {
			return ! is_wp_error( Catalog::publish( $course_id, false ) );
		} catch ( \Throwable $error ) {
			return false;
		}
	}
}
