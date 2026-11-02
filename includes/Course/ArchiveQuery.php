<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Feature-owned hook operations, with registration kept in the compatibility adapter.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Course;

defined( 'ABSPATH' ) || exit;

/** Owns the Course operations delegated by CommonHook. */
final class ArchiveQuery {

	/**
	 * Modifies the number of posts per page for the course archive.
	 *
	 * @param WP_Query $query The WP_Query object.
	 */
	public function modify_posts_per_page_for_course_archive( $query ) {
		// Ensure we're modifying the main query and not in the admin area.
		if ( ! is_admin() && $query->is_main_query() ) {
			// Check if the post type is 'ohmylms-course'.
			if ( $query->is_post_type_archive( OHMYLMS_COURSE_CPT ) ) {
				// Modify the number of posts per page.
				$query->set( 'posts_per_page', get_option( 'ohmylms_courses_per_page', 10 ) ); // Set to your desired number.
			}
		}
	}
}
