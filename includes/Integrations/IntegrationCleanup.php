<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Integration Cleanup Handler
 *
 * Handles cleanup of integrations when content (course/lesson/quiz/assignment) is deleted
 *
 * @package OhMyLMS_Pro
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations;

use OhMyLMS\Integrations\WPFusion\Includes\WPFusionMigration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integration Cleanup Class
 */
class IntegrationCleanup {

	/**
	 * Initialize the cleanup hooks
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		// Hook into WordPress post deletion.
		add_action( 'before_delete_post', array( __CLASS__, 'delete_content_integrations' ), 10, 2 );

		// Also hook into trash action as a safeguard.
		add_action( 'wp_trash_post', array( __CLASS__, 'maybe_delete_integrations_on_trash' ), 10, 1 );
	}

	/**
	 * Delete all integrations when content is permanently deleted
	 *
	 * @param mixed   $post_id Post id.
	 * @param WP_Post $post Post object being deleted.
	 * @since 1.0.0
	 */
	public static function delete_content_integrations( $post_id, $post ) {
		// Get post type.
		$post_type = get_post_type( $post_id );

		// Map post types to content types.
		$content_type_map = array(
			'ohmylms-course'     => 'course',
			'ohmylms-lesson'     => 'lesson',
			'ohmylms-quiz'       => 'quiz',
			'ohmylms-assignment' => 'assignment',
		);

		// Check if this is a OhMyLMS content type.
		if ( ! isset( $content_type_map[ $post_type ] ) ) {
			return;
		}

		$content_type = $content_type_map[ $post_type ];

		// Delete integrations for this content.
		self::delete_integrations_by_content( $content_type, $post_id );
	}

	/**
	 * Maybe delete integrations when post is trashed
	 * Only if permanent deletion is configured
	 *
	 * @param int $post_id Post ID being trashed.
	 * @since 1.0.0
	 */
	public static function maybe_delete_integrations_on_trash( $post_id ) {
		// Check if EMPTY_TRASH_DAYS is 0 (immediate permanent deletion).
		if ( defined( 'EMPTY_TRASH_DAYS' ) && EMPTY_TRASH_DAYS === 0 ) {
			$post = get_post( $post_id );
			if ( $post ) {
				self::delete_content_integrations( $post_id, $post );
			}
		}
	}

	/**
	 * Delete all integrations for a specific content
	 *
	 * @param string $content_type Content type (course, lesson, quiz, assignment).
	 * @param int    $content_id Content ID.
	 * @return int Number of integrations deleted
	 * @since 1.0.0
	 */
	public static function delete_integrations_by_content( $content_type, $content_id ) {
		global $wpdb;

		$table_name = WPFusionMigration::get_table_name();

		// Get all integrations for this content.
		$integrations = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id FROM %i
                 WHERE content_type = %s 
                 AND content_id = %d',
				$table_name,
				$content_type,
				$content_id
			),
			ARRAY_A
		);

		if ( empty( $integrations ) ) {
			return 0;
		}

		// Delete all integrations.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i
                 WHERE content_type = %s 
                 AND content_id = %d',
				$table_name,
				$content_type,
				$content_id
			)
		);

		// Log the deletion.
		if ( $deleted > 0 ) {
			do_action(
				'ohmylms_integrations_deleted',
				$content_type,
				$content_id,
				$deleted,
				$integrations
			);

			error_log(
				sprintf(
					'OhMyLMS: Deleted %d integration(s) for %s ID %d',
					$deleted,
					$content_type,
					$content_id
				)
			);
		}

		return $deleted;
	}

	/**
	 * Get count of integrations for specific content
	 * Useful for showing confirmation before deletion
	 *
	 * @param string $content_type Content type (course, lesson, quiz, assignment).
	 * @param int    $content_id Content ID.
	 * @return int Number of integrations
	 * @since 1.0.0
	 */
	public static function get_integration_count( $content_type, $content_id ) {
		global $wpdb;

		$table_name = WPFusionMigration::get_table_name();

		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i
                 WHERE content_type = %s 
                 AND content_id = %d',
				$table_name,
				$content_type,
				$content_id
			)
		);

		return intval( $count );
	}

	/**
	 * Restore integrations (if needed in future for undo functionality)
	 * This would require storing deleted integrations temporarily
	 *
	 * @param string $content_type Content type.
	 * @param int    $content_id Content ID.
	 * @since 1.0.0
	 */
	public static function restore_integrations( $content_type, $content_id ) {
		// Placeholder for future undo functionality
		// Could store deleted integrations in a separate table with timestamp.
		do_action( 'ohmylms_integrations_restore_requested', $content_type, $content_id );
	}
}
