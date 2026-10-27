<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Handles hooks related to courses in the OhMyLMS plugin.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Hooks;

use OhMyLMS\Abstracts\HookHandler;
use OhMyLMS\Data\Chapter;

/**
 * Handles hooks related to courses in the OhMyLMS plugin.
 *
 * @since 1.0.0
 */
class CourseHookHandler extends HookHandler {

	/**
	 * Register hooks for Course hook handler.
	 */
	public function register_hooks() {
		add_action( 'ohmylms_after_creating_first_course', array( $this, 'save_first_course_creation_timestamp' ), 10, 2 );
		add_action( 'ohmylms_after_creating_first_content', array( $this, 'save_first_content_creation_timestamp' ), 10, 2 );
		add_action( 'ohmylms_rest_insert_course', array( $this, 'create_default_chapter' ), 10, 2 );
		add_action( 'ohmylms_rest_delete_course', array( $this, 'unlink_chapter_from_course' ), 10 );
		add_action( 'ohmylms_rest_delete_course', array( $this, 'unlink_course_from_membership' ), 10 );
	}


	/**
	 * Save first course creation timestamp for Course hook handler.
	 *
	 * @param mixed $id Id.
	 * @param mixed $course Course.
	 */
	public function save_first_course_creation_timestamp( $id, $course ) {
		$timestamp = current_time( 'timestamp' );
		update_option( 'ohmylms_first_course_created', $timestamp );
	}

	/**
	 * Save first content creation timestamp for Course hook handler.
	 *
	 * @param mixed $id Id.
	 * @param mixed $course Course.
	 */
	public function save_first_content_creation_timestamp( $id, $course ) {
		$timestamp = current_time( 'timestamp' );
		update_option( 'ohmylms_first_content_created', $timestamp );
	}


	/**
	 * Create a default chapter for a course.
	 *
	 * @param \WP_Post         $course The course post object.
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function create_default_chapter( $course, $request ) {

		if ( ! is_a( $course, 'WP_Post' ) ) {
			return;
		}
		$course_id = $course->ID;

		$chapter = new Chapter();
		$chapter->set_name( __( 'Untitled', 'ohmylms' ) );
		$chapter->set_status( 'publish' );
		$chapter->save();

		$this->update_chapter_relationship( $course_id, $chapter->get_id() );
	}


	/**
	 * Update the relationship between a course and a chapter.
	 *
	 * @param int $course_id The ID of the course.
	 * @param int $chapter_id The ID of the chapter.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 * @param int $order_number Order number.
	 */
	public function update_chapter_relationship( $course_id, $chapter_id, $order_number = 0 ) {
		if ( ! $course_id || ! $chapter_id ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . OHMYLMS_CHAPTER_RELATIONSHIP;

		// Check if the relationship already exists.
		$exists = $wpdb->get_var(
			$wpdb->prepare( 'SELECT ID FROM %i WHERE course_id = %d AND chapter_id = %d LIMIT 1', $table_name, $course_id, $chapter_id )
		);

		// If exists, update the relationship, otherwise insert a new one.
		if ( $exists ) {
			$wpdb->update(
				$table_name,
				array(
					'order_number' => $order_number,
				),
				array(
					'course_id'  => $course_id,
					'chapter_id' => $chapter_id,
				),
				array( '%d' ),
				array( '%d', '%d' )
			);
		} else {
			$wpdb->insert(
				$table_name,
				array(
					'course_id'    => $course_id,
					'chapter_id'   => $chapter_id,
					'order_number' => $order_number,
				),
				array( '%d', '%d', '%d' )
			);
		}

		/**
		 * Action triggered after a course and chapter relationship is created.
		 *
		 * @since 1.0.0
		 *
		 * @param int $course_id The ID of the course.
		 * @param int $chapter_id The ID of the chapter.
		 */
		do_action( 'ohmylms_course_chapter_relationship_created', $course_id, $chapter_id );
	}


	/**
	 * Unlink a chapter from a course.
	 * Delete chapter and course relationship
	 *
	 * @param int $course_id The ID of the course.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function unlink_chapter_from_course( $course_id ) {
		if ( ! $course_id ) {
			return;
		}

		global $wpdb;

		$table_name = $wpdb->prefix . OHMYLMS_CHAPTER_RELATIONSHIP;
		// Fetch all chapter IDs before deleting.
		$chapter_ids = $wpdb->get_col(
			$wpdb->prepare( 'SELECT chapter_id FROM %i WHERE course_id = %d', $table_name, $course_id )
		);

		if ( ! empty( $chapter_ids ) ) {
			$wpdb->delete(
				$table_name,
				array(
					'course_id' => $course_id,
				),
				array(
					'%d',
				)
			);

			foreach ( $chapter_ids as $chapter_id ) {
				/**
				 * Executes the 'ohmylms_after_remove_chapter_from_course' action hook.
				 * This hook is triggered when a chapter is being deleted via Hook.
				 *
				 * @param int $chapter_id The ID of the chapter being deleted.
				 * @since 1.0.0
				 */
				do_action( 'ohmylms_after_remove_chapter_from_course', $chapter_id );
			}
		}
	}

	/**
	 * Unlinks a course from a membership.
	 *
	 * @param int $course_id The ID of the course to unlink.
	 * @return void
	 */
	public function unlink_course_from_membership( $course_id ) {
		$course_id = intval( $course_id );

		if ( $course_id <= 0 ) {
			return;
		}

		// Get all membership post IDs.
		$membership_posts = get_posts(
			array(
				'post_type'      => sanitize_text_field( 'ohmylms-membership' ),
				'posts_per_page' => -1, // Get all posts.
				'fields'         => 'ids', // Only get IDs.
			)
		);

		// Check if there are any memberships.
		if ( empty( $membership_posts ) ) {
			return;
		}

		// Process each membership post ID.
		foreach ( $membership_posts as $membership_id ) {
			if ( ! ohmylms_is_pro() ) {
				continue;
			}

			$membership = ohmylms_get_membership( $membership_id );

			if ( ! $membership ) {
				continue; // Skip if no membership found.
			}

			$products = $membership->get_products( 'edit' );

			if ( ! is_array( $products ) ) {
				continue; // Ensure $products is an array before processing.
			}

			foreach ( $products as $index => $product ) {
				// Ensure 'id' key exists before accessing.
				if ( isset( $product['id'] ) && (int) $product['id'] === $course_id ) {
					unset( $products[ $index ] ); // Remove the matching course.
				}
			}

			// Reindex array to maintain proper structure.
			$products = array_values( $products );

			// Update the membership with the modified product list.
			update_post_meta( $membership_id, '_products', $products );
		}
	}
}
