<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Webhooks Migration Class
 *
 * Handles the creation and upgrade of the webhooks tables.
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
	 * Option that stores the installed schema version of the webhooks tables.
	 */
	const DB_VERSION_OPTION = 'ohmylms_webhooks_db_version';

	/**
	 * Current schema version. Bump when get_schema() changes.
	 *
	 * 2: adds the `secret` column and the deliveries table.
	 */
	const DB_VERSION = '2';

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

		self::ensure_schema();
	}

	/**
	 * Create or upgrade the webhooks tables when the stored schema version is behind.
	 *
	 * Safe to call on every request: it costs one autoloaded option read once the schema is current.
	 * The REST controller calls it so the API works even before the integration is switched on.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function ensure_schema() {
		if ( self::is_webhooks_table_created() ) {
			return;
		}

		self::create_webhooks_table();
	}

	/**
	 * Get the CREATE TABLE statements for the webhooks and deliveries tables.
	 *
	 * Formatted for dbDelta(): two spaces after PRIMARY KEY, one column per line.
	 *
	 * @param string $charset_collate Charset and collation clause.
	 * @return string
	 * @since 1.1.0
	 */
	public static function get_schema( $charset_collate = '' ) {
		global $wpdb;

		if ( '' === $charset_collate ) {
			$charset_collate = $wpdb->get_charset_collate();
		}

		return "CREATE TABLE {$wpdb->prefix}ohmylms_webhooks (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL,
			trigger_event VARCHAR(100) NOT NULL,
			webhook_url TEXT NOT NULL,
			http_method VARCHAR(10) NOT NULL DEFAULT 'POST',
			data_type VARCHAR(20) NOT NULL DEFAULT 'json',
			data_mapping LONGTEXT,
			secret VARCHAR(128) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY trigger_event (trigger_event),
			KEY status (status)
		) $charset_collate;
		CREATE TABLE {$wpdb->prefix}ohmylms_webhook_deliveries (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			webhook_id BIGINT(20) UNSIGNED NOT NULL,
			delivery_uuid VARCHAR(36) NOT NULL DEFAULT '',
			event VARCHAR(100) NOT NULL DEFAULT '',
			url TEXT NOT NULL,
			http_method VARCHAR(10) NOT NULL DEFAULT 'POST',
			status VARCHAR(20) NOT NULL DEFAULT 'success',
			response_code SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
			duration_ms INT(10) UNSIGNED NOT NULL DEFAULT 0,
			error_code VARCHAR(100) NOT NULL DEFAULT '',
			error_message TEXT,
			request_body TEXT,
			response_body TEXT,
			is_test TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY webhook_id (webhook_id),
			KEY created_at (created_at)
		) $charset_collate;";
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
	 * Check whether the tables exist at the current schema version.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	private static function is_webhooks_table_created() {
		return self::DB_VERSION === (string) get_option( self::DB_VERSION_OPTION, '' );
	}

	/**
	 * Create or upgrade the webhooks tables.
	 *
	 * @since 1.0.0
	 */
	private static function create_webhooks_table() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( self::get_schema() );

		// Mark as created.
		update_option( self::WEBHOOKS_TABLE_CREATED_OPTION, true );
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );

		/**
		 * Fires after webhooks table is created
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_webhooks_table_created' );
	}

	/**
	 * Drop webhooks tables (for uninstall/cleanup)
	 *
	 * @since 1.0.0
	 */
	public static function drop_webhooks_table() {
		global $wpdb;

		$tables = array( $wpdb->prefix . 'ohmylms_webhook_deliveries', $wpdb->prefix . 'ohmylms_webhooks' );
		foreach ( $tables as $table_name ) {
			$wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Trusted table name; uninstall cleanup.
		}

		// Remove the options.
		delete_option( self::WEBHOOKS_TABLE_CREATED_OPTION );
		delete_option( self::DB_VERSION_OPTION );

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
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) ) === $table_name;
	}
}
