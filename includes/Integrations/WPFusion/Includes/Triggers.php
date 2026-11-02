<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * WP Fusion triggers repository.
 *
 * Reads and writes the trigger rows of the integrations table.
 *
 * @package OhMyLMS\Integrations\WPFusion\Includes
 */

namespace OhMyLMS\Integrations\WPFusion\Includes;

defined( 'ABSPATH' ) || exit;

/**
 * Trigger rows of the integrations table (`WPFusionMigration::get_table_name()`).
 *
 * `action_data` is stored as JSON and returned decoded.
 */
class Triggers {

	/**
	 * Column formats of a written row, in the order of `row()`.
	 */
	const FORMATS = array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' );

	/**
	 * Triggers of one CRM, newest first.
	 *
	 * @param string $crm_type CRM type, such as `wpfusion`.
	 * @return array[]
	 */
	public static function for_crm( $crm_type ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Plugin table without a WordPress API; admin list read fresh.
		$triggers = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE crm_type = %s ORDER BY created_at DESC', WPFusionMigration::get_table_name(), $crm_type ),
			ARRAY_A
		);

		foreach ( $triggers as &$trigger ) {
			if ( isset( $trigger['action_data'] ) ) {
				$trigger['action_data'] = json_decode( $trigger['action_data'], true );
			}
		}

		return $triggers;
	}

	/**
	 * One trigger.
	 *
	 * @param int $id Trigger ID.
	 * @return array|null Null when there is no such row.
	 */
	public static function find( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Plugin table without a WordPress API; read fresh after writes.
		$trigger = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', WPFusionMigration::get_table_name(), $id ),
			ARRAY_A
		);

		if ( ! $trigger ) {
			return null;
		}

		if ( isset( $trigger['action_data'] ) ) {
			$trigger['action_data'] = json_decode( $trigger['action_data'], true );
		}

		return $trigger;
	}

	/**
	 * Insert a trigger.
	 *
	 * @param array $fields Sanitized fields: name, crm_type, trigger_event, content_type, content_id,
	 *                      action_type, action_data (encoded here), status.
	 * @return int|false New trigger ID, or false when the row was not inserted.
	 */
	public static function create( array $fields ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Plugin table without a WordPress API.
		$inserted = $wpdb->insert( WPFusionMigration::get_table_name(), self::row( $fields ), self::FORMATS );

		if ( ! $inserted ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Overwrite a trigger.
	 *
	 * @param int   $id     Trigger ID.
	 * @param array $fields Sanitized fields, as for `create()`.
	 * @return int|false Rows changed (0 when nothing changed or there is no such row), false on error.
	 */
	public static function update( $id, array $fields ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Plugin table without a WordPress API; nothing is cached.
		return $wpdb->update(
			WPFusionMigration::get_table_name(),
			self::row( $fields ),
			array( 'id' => $id ),
			self::FORMATS,
			array( '%d' )
		);
	}

	/**
	 * Delete a trigger.
	 *
	 * @param int $id Trigger ID.
	 * @return int|false Rows deleted, false on error.
	 */
	public static function delete( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Plugin table without a WordPress API; nothing is cached.
		return $wpdb->delete(
			WPFusionMigration::get_table_name(),
			array( 'id' => $id ),
			array( '%d' )
		);
	}

	/**
	 * Column values of a written row.
	 *
	 * @param array $fields Sanitized fields.
	 * @return array
	 */
	private static function row( array $fields ) {
		return array(
			'name'          => $fields['name'],
			'crm_type'      => $fields['crm_type'],
			'trigger_event' => $fields['trigger_event'],
			'content_type'  => $fields['content_type'],
			'content_id'    => $fields['content_id'],
			'action_type'   => $fields['action_type'],
			'action_data'   => wp_json_encode( $fields['action_data'] ),
			'status'        => $fields['status'],
		);
	}
}
