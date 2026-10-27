<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * WP Fusion Migration Class
 *
 * Handles the creation of WP Fusion triggers table when the integration is enabled
 *
 * @package OhMyLMS\Integrations\WPFusion
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\WPFusion\Includes;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin wpfusion migration implementation.
 */
class WPFusionMigration {

	/**
	 * Option key to track if integrations table is created
	 */
	const WPFUSION_TABLE_CREATED_OPTION = 'ohmylms_integrations_table_created';

	/**
	 * Initialize the migration
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_ohmylms_integration_table' ) );
	}

	/**
	 * Check if integration table should be created
	 * Now always returns true since this is a universal integrations table for all CRMs
	 *
	 * @since 1.0.0
	 */
	public static function maybe_ohmylms_integration_table() {

		// Check if table is already created.
		if ( self::is_integration_table_created() ) {
			return;
		}

		// Create the table - always create for any integration usage.
		self::ohmylms_integration_table();
	}

	/**
	 * Check if WP Fusion integration is enabled
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	private static function is_wpfusion_enabled() {
		$integrations = get_option( 'ohmylms_integrations', array() );

		if ( empty( $integrations ) || ! is_array( $integrations ) ) {
			return false;
		}

		if ( ! isset( $integrations['wpfusion']['is_enable'] ) ) {
			return false;
		}

		return 1 === (int) $integrations['wpfusion']['is_enable'];
	}

	/**
	 * Check if integrations table is already created
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	private static function is_integration_table_created() {
		global $wpdb;

		// First check the option.
		$option_exists = get_option( self::WPFUSION_TABLE_CREATED_OPTION, false );

		if ( $option_exists ) {
			return true;
		}

		// Double check if table actually exists in database.
		$table_name   = $wpdb->prefix . 'ohmylms_integrations';
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;

		// If table exists but option not set, set the option.
		if ( $table_exists ) {
			update_option( self::WPFUSION_TABLE_CREATED_OPTION, true );
			return true;
		}

		return false;
	}

	/**
	 * Create central integrations table for all CRMs
	 *
	 * @since 1.0.0
	 */
	private static function ohmylms_integration_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'ohmylms_integrations';

		$sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            crm_type VARCHAR(50) NOT NULL COMMENT 'wpfusion, activecampaign, etc.',
            trigger_event VARCHAR(100) NOT NULL,
            content_type VARCHAR(50) NOT NULL COMMENT 'lesson, course, quiz, etc.',
            content_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'ID of the lesson/course/quiz',
            action_type VARCHAR(50) NOT NULL,
            action_data LONGTEXT,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY crm_type (crm_type),
            KEY trigger_event (trigger_event),
            KEY content_type_id (content_type, content_id),
            KEY status (status)
        ) $charset_collate;";

		dbDelta( $sql );

		// Mark as created.
		update_option( self::WPFUSION_TABLE_CREATED_OPTION, true );

		/**
		 * Fires after integrations table is created
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_integrations_table_created' );
	}

	/**
	 * Drop integrations table (for uninstall/cleanup)
	 *
	 * @since 1.0.0
	 */
	public static function drop_wpfusion_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ohmylms_integrations';
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );

		// Remove the option.
		delete_option( self::WPFUSION_TABLE_CREATED_OPTION );

		/**
		 * Fires after integrations table is dropped
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_integrations_table_dropped' );
	}

	/**
	 * Get integrations table name
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_integrations';
	}

	/**
	 * Check if integrations table exists
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function table_exists() {
		global $wpdb;
		$table_name = self::get_table_name();
		return $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
	}
}
