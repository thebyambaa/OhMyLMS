<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Plugin rewrite rules implementation.
 */
class RewriteRules {

	/**
	 * Initialize rewrite rules
	 */
	public function __construct() {
		add_action( 'generate_rewrite_rules', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'post_type_link', array( $this, 'change_content_single_url' ), 1, 2 );
	}

	/**
	 * Add custom rewrite rules for courses, lessons, quizzes, and memberships
	 *
	 * This method registers custom rewrite rules for the OhMyLMS plugin.
	 *
	 * @param \WP_Rewrite $wp_rewrite The WP_Rewrite instance.
	 * @since 1.0.0
	 */
	public function add_rewrite_rules( \WP_Rewrite $wp_rewrite ) {
		$permalink_settings = ohmylms_get_permalink_structure();
		$course_base        = $permalink_settings['course_base'];
		$lesson_base        = $permalink_settings['lesson_base'];
		$quiz_base          = $permalink_settings['quiz_base'];
		$membership_base    = $permalink_settings['membership_base'];
		$category_base      = $permalink_settings['category_base'];
		$tag_base           = $permalink_settings['tag_base'];
		$assignment_base    = ! empty( $permalink_settings['assignment_base'] ) ? $permalink_settings['assignment_base'] : 'assignments';

		$rules = array();
		$rules[ "{$course_base}/([^/]+)/{$lesson_base}/([^/]+)/?$" ]     = 'index.php?post_type=ohmylms-lesson&name=$matches[2]';
		$rules[ "{$course_base}/([^/]+)/{$quiz_base}/([^/]+)/?$" ]       = 'index.php?post_type=ohmylms-quiz&name=$matches[2]';
		$rules[ "{$course_base}/([^/]+)/{$assignment_base}/([^/]+)/?$" ] = 'index.php?post_type=ohmylms-assignment&name=$matches[2]';
		$rules[ "{$course_base}/([^/]+)/sessions/([^/]+)/?$" ]           = 'index.php?post_type=ohmylms-session&name=$matches[2]';
		$rules[ "{$membership_base}/([^/]+)/?$" ]                        = 'index.php?post_type=ohmylms-membership&name=$matches[1]';
		$rules[ "{$category_base}/([^/]+)/?$" ]                          = 'index.php?course-category=$matches[1]';
		$rules[ "{$tag_base}/([^/]+)/?$" ]                               = 'index.php?course-tag=$matches[1]';

		$wp_rewrite->rules = $rules + $wp_rewrite->rules;
	}



	/**
	 * Change the permalink structure for lessons, quizzes, and assignments
	 *
	 * @param mixed    $post_link Post link.
	 * @param \WP_Post $post The post object.
	 * @return string Modified post link.
	 * @since 1.0.0
	 */
	public function change_content_single_url( $post_link, $post ) {
		$permalink_settings = ohmylms_get_permalink_structure();
		if ( ! isset( $permalink_settings['assignment_base'] ) ) {
			$permalink_settings['assignment_base'] = 'assignments';
		}
		$structure = '';
		if ( 'ohmylms-lesson' === $post->post_type ) {
			$course_id = ohmylms_get_course_by_content_id( $post->ID );
			$course    = get_post( $course_id );
			$structure = ( ! empty( $permalink_settings['course_base'] ) && ! empty( $permalink_settings['lesson_base'] ) )
				? home_url( '/' . $permalink_settings['course_base'] . '/{course}/' . $permalink_settings['lesson_base'] . '/{lesson}' )
				: '/{post_type}/{lesson}/';
			$structure = str_replace(
				array( '{course}', '{lesson}' ),
				array( $course ? $course->post_name : '', $post->post_name ),
				$structure
			);
		} elseif ( 'ohmylms-quiz' === $post->post_type ) {
			$course_id = ohmylms_get_course_by_content_id( $post->ID );
			$course    = get_post( $course_id );
			$structure = ( ! empty( $permalink_settings['course_base'] ) && ! empty( $permalink_settings['quiz_base'] ) )
				? home_url( '/' . $permalink_settings['course_base'] . '/{course}/' . $permalink_settings['quiz_base'] . '/{quiz}' )
				: '/{post_type}/{quiz}/';
			$structure = str_replace(
				array( '{course}', '{quiz}' ),
				array( $course ? $course->post_name : '', $post->post_name ),
				$structure
			);
		} elseif ( 'ohmylms-assignment' === $post->post_type ) {
			$course_id = ohmylms_get_course_by_content_id( $post->ID );
			$course    = get_post( $course_id );
			$structure = ( ! empty( $permalink_settings['course_base'] ) && ! empty( $permalink_settings['assignment_base'] ) )
				? home_url( '/' . $permalink_settings['course_base'] . '/{course}/' . $permalink_settings['assignment_base'] . '/{assignment}' )
				: '/{post_type}/{assignment}/';
			$structure = str_replace(
				array( '{course}', '{assignment}' ),
				array( $course ? $course->post_name : '', $post->post_name ),
				$structure
			);
		}
		if ( ! empty( $structure ) ) {
			// Remove any double slashes except after 'https://'.
			$post_link = str_replace( home_url( '/' ), '', $structure );
			$post_link = str_replace( '{post_type}', $post->post_type, $post_link );
			$post_link = home_url( '/' . ltrim( $post_link, '/' ) );
			$post_link = preg_replace( '#(?<!:)//+#', '/', $post_link );
		}
		return $post_link;
	}
}
