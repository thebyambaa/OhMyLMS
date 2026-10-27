<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * WP Fusion Helpers
 *
 * Utility functions for WP Fusion integration
 *
 * @package OhMyLMS\Integrations\WPFusion\Includes
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\WPFusion\Includes;

use OhMyLMS\Integrations\WPFusion\Includes\Api\WPFusionApiClient;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin helpers implementation.
 */
class Helpers {

	/**
	 * Check if WP Fusion is connected
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function is_connected() {
		$credentials = get_option( 'ohmylms_wpfusion_credentials', array() );
		return ! empty( $credentials ) && isset( $credentials['api_key'] );
	}

	/**
	 * Get WP Fusion API client
	 *
	 * @return WPFusionApiClient|null
	 * @since 1.0.0
	 */
	public static function get_api_client() {
		if ( ! self::is_connected() ) {
			return null;
		}

		$credentials = get_option( 'ohmylms_wpfusion_credentials', array() );

		return new WPFusionApiClient(
			$credentials['api_url'],
			$credentials['api_key']
		);
	}

	/**
	 * Get all available tags from WP Fusion
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_available_tags() {
		$api_client = self::get_api_client();

		if ( ! $api_client ) {
			return array();
		}

		$result = $api_client->get_available_tags();

		if ( ! $result['success'] ) {
			return array();
		}

		return $result['data'];
	}

	/**
	 * Get trigger by ID
	 *
	 * @param int $trigger_id Trigger ID.
	 * @return array|null
	 * @since 1.0.0
	 */
	public static function get_trigger( $trigger_id ) {
		global $wpdb;

		$table_name = WPFusionMigration::get_table_name();

		$trigger = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table_name, $trigger_id ),
			ARRAY_A
		);

		if ( ! $trigger ) {
			return null;
		}

		// Decode JSON field.
		if ( isset( $trigger['action_data'] ) ) {
			$trigger['action_data'] = json_decode( $trigger['action_data'], true );
		}

		return $trigger;
	}

	/**
	 * Get all triggers
	 *
	 * @param string $status Filter by status (active, inactive, all).
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_all_triggers( $status = 'all' ) {
		global $wpdb;

		$table_name = WPFusionMigration::get_table_name();

		if ( 'all' === $status ) {
			$triggers = $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM %i ORDER BY created_at DESC', $table_name ),
				ARRAY_A
			);
		} else {
			$triggers = $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM %i WHERE status = %s ORDER BY created_at DESC', $table_name, $status ),
				ARRAY_A
			);
		}

		// Decode JSON fields.
		foreach ( $triggers as &$trigger ) {
			if ( isset( $trigger['action_data'] ) ) {
				$trigger['action_data'] = json_decode( $trigger['action_data'], true );
			}
		}

		return $triggers;
	}

	/**
	 * Get triggers by event
	 *
	 * @param string $event_name Event name.
	 * @param string $status Filter by status (active, inactive, all).
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_triggers_by_event( $event_name, $status = 'active' ) {
		global $wpdb;

		$table_name = WPFusionMigration::get_table_name();

		if ( 'all' === $status ) {
			$triggers = $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM %i WHERE trigger_event = %s ORDER BY created_at DESC', $table_name, $event_name ),
				ARRAY_A
			);
		} else {
			$triggers = $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM %i WHERE trigger_event = %s AND status = %s ORDER BY created_at DESC', $table_name, $event_name, $status ),
				ARRAY_A
			);
		}

		// Decode JSON fields.
		foreach ( $triggers as &$trigger ) {
			if ( isset( $trigger['action_data'] ) ) {
				$trigger['action_data'] = json_decode( $trigger['action_data'], true );
			}
		}

		return $triggers;
	}

	/**
	 * Get available OhMyLMS events
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_available_events() {
		return array(
			'ohmylms_course_completed'           => __( 'Course Completed', 'ohmylms' ),
			'ohmylms_lesson_completed'           => __( 'Lesson Completed', 'ohmylms' ),
			'ohmylms_manual_student_enrollment'  => __( 'Student Enrolled', 'ohmylms' ),
			'ohmylms_quiz_submission'            => __( 'Quiz Submitted', 'ohmylms' ),
			'ohmylms_after_assignment_submitted' => __( 'Assignment Submitted', 'ohmylms' ),
			'ohmylms_quiz_result'                => __( 'Quiz Result', 'ohmylms' ),
		);
	}

	/**
	 * Get available WP Fusion actions
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_available_actions() {
		return array(
			'add_tag'       => __( 'Add Tag', 'ohmylms' ),
			'remove_tag'    => __( 'Remove Tag', 'ohmylms' ),
			'update_fields' => __( 'Update Contact Fields', 'ohmylms' ),
		);
	}

	/**
	 * Validate trigger data
	 *
	 * @param array $data Trigger data.
	 * @return array Validation result with 'valid' and 'errors'
	 * @since 1.0.0
	 */
	public static function validate_trigger_data( $data ) {
		$errors = array();

		if ( empty( $data['name'] ) ) {
			$errors[] = __( 'Trigger name is required', 'ohmylms' );
		}

		if ( empty( $data['trigger_event'] ) ) {
			$errors[] = __( 'Trigger event is required', 'ohmylms' );
		}

		if ( empty( $data['action_type'] ) ) {
			$errors[] = __( 'Action type is required', 'ohmylms' );
		}

		if ( empty( $data['action_data'] ) ) {
			$errors[] = __( 'Action data is required', 'ohmylms' );
		}

		// Validate action-specific data.
		if ( ! empty( $data['action_type'] ) && ! empty( $data['action_data'] ) ) {
			switch ( $data['action_type'] ) {
				case 'add_tag':
				case 'remove_tag':
					if ( empty( $data['action_data']['tags'] ) || ! is_array( $data['action_data']['tags'] ) ) {
						$errors[] = __( 'At least one tag is required', 'ohmylms' );
					}
					break;

				case 'update_fields':
					if ( empty( $data['action_data']['fields'] ) || ! is_array( $data['action_data']['fields'] ) ) {
						$errors[] = __( 'At least one field is required', 'ohmylms' );
					}
					break;
			}
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}

	/**
	 * Format trigger for display
	 *
	 * @param array $trigger Trigger data.
	 * @return array Formatted trigger
	 * @since 1.0.0
	 */
	public static function format_trigger_for_display( $trigger ) {
		$available_events  = self::get_available_events();
		$available_actions = self::get_available_actions();

		$formatted = $trigger;

		// Add readable event name.
		$formatted['event_label'] = isset( $available_events[ $trigger['trigger_event'] ] )
			? $available_events[ $trigger['trigger_event'] ]
			: $trigger['trigger_event'];

		// Add readable action name.
		$formatted['action_label'] = isset( $available_actions[ $trigger['action_type'] ] )
			? $available_actions[ $trigger['action_type'] ]
			: $trigger['action_type'];

		return $formatted;
	}

	/**
	 * Delete trigger
	 *
	 * @param int $trigger_id Trigger ID.
	 * @return bool
	 * @since 1.0.0
	 */
	public static function delete_trigger( $trigger_id ) {
		global $wpdb;

		$table_name = WPFusionMigration::get_table_name();

		$deleted = $wpdb->delete(
			$table_name,
			array( 'id' => $trigger_id ),
			array( '%d' )
		);

		return false !== $deleted;
	}

	/**
	 * Update trigger status
	 *
	 * @param int    $trigger_id Trigger ID.
	 * @param string $status New status.
	 * @return bool
	 * @since 1.0.0
	 */
	public static function update_trigger_status( $trigger_id, $status ) {
		global $wpdb;

		$table_name = WPFusionMigration::get_table_name();

		$updated = $wpdb->update(
			$table_name,
			array( 'status' => $status ),
			array( 'id' => $trigger_id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}
}
