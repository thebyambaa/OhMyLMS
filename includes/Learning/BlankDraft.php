<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Empty draft courses left behind when an author starts a course and leaves without adding anything.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Learning;

use OhMyLMS\Curriculum\CourseObjectives;

defined( 'ABSPATH' ) || exit;

/** A course is created the moment "Start from scratch" is chosen; this removes it again if it stayed empty. */
final class BlankDraft {

	/**
	 * Whether nothing at all was ever added: still the default title, no description, image, objectives,
	 * skills, attachments or learners, and only unnamed units without content.
	 *
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function is_blank( $course_id ) {
		global $wpdb;
		$course_id = (int) $course_id;
		$post      = get_post( $course_id );
		if ( ! $post || OHMYLMS_COURSE_CPT !== $post->post_type || 'draft' !== $post->post_status ) {
			return false;
		}
		if ( ! in_array( $post->post_title, array( 'Untitled', 'Untitled Course', __( 'Untitled', 'ohmylms' ), __( 'Untitled Course', 'ohmylms' ) ), true ) ) {
			return false;
		}
		if ( '' !== trim( $post->post_content . $post->post_excerpt ) || has_post_thumbnail( $course_id ) ) {
			return false;
		}
		if ( \OhMyLMS\Curriculum\SyllabusCourse::owner( $course_id ) > 0 || CourseObjectives::references( $course_id ) ) {
			return false;
		}
		$program = get_post_meta( $course_id, CourseProgram::DRAFT, true );
		if ( is_array( $program ) && ( ! empty( $program['outcomes'] ) || ! empty( $program['items'] ) ) ) {
			return false;
		}
		foreach ( Catalog::chapters( $course_id ) as $chapter ) {
			if ( ! in_array( $chapter['name'], array( 'Untitled', __( 'Untitled', 'ohmylms' ) ), true ) ) {
				return false;
			}
			$content = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ohmylms_content_relationship WHERE chapter_id = %d", (int) $chapter['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Exact emptiness check; a cached answer could delete real work.
			if ( $content ) {
				return false;
			}
		}
		$learners = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE course_id = %d", $course_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Exact emptiness check; a cached answer could delete real work.
		return 0 === $learners;
	}

	/**
	 * Delete the course if, and only if, it is still blank.
	 *
	 * @param int $course_id Course ID.
	 * @return bool Whether it was deleted.
	 */
	public static function discard( $course_id ) {
		if ( ! self::is_blank( $course_id ) || ! current_user_can( 'delete_post', (int) $course_id ) ) {
			return false;
		}
		$route = new \WP_REST_Request( 'DELETE', '/ohmylms/v1/courses/' . (int) $course_id );
		$route->set_param( 'force', true );
		return rest_do_request( $route )->get_status() < 300;
	}
}
