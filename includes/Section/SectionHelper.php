<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Responsible to handle all section related calculations.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Section;

use OhMyLMS\Course\CourseHelper;

/**
 * Responsible to handle all section related calculations
 *
 * @since 1.0.0
 */
class SectionHelper {

	/**
	 * Add section under a course
	 *
	 * @param mixed $title Title.
	 * @param mixed $description Description.
	 * @return array
	 * @since 1.0.0
	 */
	public static function add_section( $title, $description ): array {

		/**
		 * Fires before section add
		 *
		 * @param string $title course title
		 * @param string $description course description
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_before_add_section', $title, $description );

		$post_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_excerpt' => $description,
				'post_type'    => 'ohmylms-section',
				'post_status'  => 'publish',
			)
		);

		/**
		 * log the error when debug is enabled.
		 * $post_id->get_error_message()
		 */
		if ( is_wp_error( $post_id ) ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Failed to add section', 'ohmylms' ),
			);
		}

		/**
		 * Fires after section add
		 *
		 * @param string $title course title
		 * @param string $description course description
		 * @param string $post_id newly inserted section id
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_after_add_section', $title, $description, $post_id );

		$section_data = array(
			'id'          => $post_id,
			'title'       => $title,
			'description' => $description,
		);

		return array(
			'status'  => 'success',
			'message' => __( 'Section has been added.', 'ohmylms' ),
			'data'    => $section_data,
		);
	}

	/**
	 * Insert section lesson relation
	 *
	 * @param mixed $section_id Section identifier.
	 * @param mixed $lesson_id Lesson identifier.
	 * @return array
	 * @since 1.0.0
	 */
	public static function insert_section_lesson_relation( $section_id, $lesson_id ): array {

		global $wpdb;

		$table = $wpdb->prefix . 'ohmylms_sections_with_lessons';

		$result = $wpdb->insert(
			$table,
			array(
				'section_id'     => $section_id,
				'lesson_id'      => $lesson_id,
				'created_at'     => current_time( 'mysql' ),
				'created_at_gmt' => current_time( 'mysql', 1 ),
				'updated_at'     => current_time( 'mysql' ),
				'updated_at_gmt' => current_time( 'mysql', 1 ),
				'created_by'     => get_current_user_id(),
				'updated_by'     => get_current_user_id(),
			),
			array(
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%d',
			)
		);

		if ( false === $result ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Failed to insert section lesson relation.', 'ohmylms' ),
			);
		}

		return array(
			'status'  => 'success',
			'message' => __( 'Section lesson relation inserted.', 'ohmylms' ),
		);
	}
}
