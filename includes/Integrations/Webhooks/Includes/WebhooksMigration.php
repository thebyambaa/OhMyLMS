<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Webhooks Migration Class
 *
 * Handles the creation of webhooks table when the integration is enabled
 *
 * @package OhMyLMS\Integrations\Webhooks
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\Webhooks\Includes;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin webhooks migration implementation.
 */
class WebhooksMigration {

	/**
	 * Option key to track if webhooks table is created
	 */
	const WEBHOOKS_TABLE_CREATED_OPTION = 'ohmylms_webhooks_table_created';

	/**
	 * Initialize the migration
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_create_webhooks_table' ) );
	}

	/**
	 * Check if webhooks integration is enabled and create table if needed
	 *
	 * @since 1.0.0
	 */
	public static function maybe_create_webhooks_table() {

		// Check if webhooks integration is enabled.
		if ( ! self::is_webhooks_enabled() ) {
			return;
		}
		// Check if table is already created.
		if ( self::is_webhooks_table_created() ) {
			return;
		}

		// Create the table.
		self::create_webhooks_table();
	}

	/**
	 * Check if webhooks integration is enabled
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	private static function is_webhooks_enabled() {
		$integrations = get_option( 'ohmylms_integrations', array() );

		if ( empty( $integrations ) || ! is_array( $integrations ) ) {
			return false;
		}

		if ( ! isset( $integrations['webhooks']['is_enable'] ) ) {
			return false;
		}

		return 1 === (int) $integrations['webhooks']['is_enable'];
	}

	/**
	 * Check if webhooks table is already created
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	private static function is_webhooks_table_created() {
		global $wpdb;

		// First check the option.
		$option_exists = get_option( self::WEBHOOKS_TABLE_CREATED_OPTION, false );

		if ( $option_exists ) {
			return true;
		}

		// Double check if table actually exists in database.
		$table_name   = $wpdb->prefix . 'ohmylms_webhooks';
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;

		// If table exists but option not set, set the option.
		if ( $table_exists ) {
			update_option( self::WEBHOOKS_TABLE_CREATED_OPTION, true );
			return true;
		}

		return false;
	}

	/**
	 * Create webhooks table
	 *
	 * @since 1.0.0
	 */
	private static function create_webhooks_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'ohmylms_webhooks';

		$sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            trigger_event VARCHAR(100) NOT NULL,
            webhook_url TEXT NOT NULL,
            http_method VARCHAR(10) NOT NULL DEFAULT 'POST',
            data_type VARCHAR(20) NOT NULL DEFAULT 'json',
            data_mapping LONGTEXT,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY trigger_event (trigger_event),
            KEY status (status)
        ) $charset_collate;";

		dbDelta( $sql );

		// Mark as created.
		update_option( self::WEBHOOKS_TABLE_CREATED_OPTION, true );

		/**
		 * Fires after webhooks table is created
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_webhooks_table_created' );
	}

	/**
	 * Drop webhooks table (for uninstall/cleanup)
	 *
	 * @since 1.0.0
	 */
	public static function drop_webhooks_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ohmylms_webhooks';
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );

		// Remove the option.
		delete_option( self::WEBHOOKS_TABLE_CREATED_OPTION );

		/**
		 * Fires after webhooks table is dropped
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_webhooks_table_dropped' );
	}

	/**
	 * Get webhooks table name
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_webhooks';
	}

	/**
	 * Check if webhooks table exists
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
