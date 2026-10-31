<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * The `ohmylms_enrolments` table: one row per enrolment with its source (see docs/api/enrolments.md).
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

defined( 'ABSPATH' ) || exit;

/**
 * Definition, installation and removal of the `ohmylms_enrolments` table. `Install::get_schema()` creates it
 * on activation and the 1.2.21 database update creates it on existing sites.
 */
final class EnrolmentSchema {

	/**
	 * Schema version stored in {@see self::OPTION}.
	 */
	const VERSION = '1';

	/**
	 * Option holding the installed schema version.
	 */
	const OPTION = 'ohmylms_enrolments_schema';

	/**
	 * Enrolment statuses.
	 */
	const STATUSES = array( 'active', 'completed', 'expired', 'cancelled', 'suspended', 'pending' );

	/**
	 * Enrolment sources.
	 */
	const SOURCES = array( 'purchase', 'self', 'manual', 'roster', 'seat', 'import' );

	/**
	 * Kinds of record `source_ref` points to.
	 */
	const REF_TYPES = array( '', 'order', 'membership', 'subscription', 'class', 'seat', 'exam' );

	/**
	 * Existence of each table name checked during this request.
	 *
	 * @var array<string,bool>
	 */
	private static $exists = array();

	/**
	 * The table name for the current prefix.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_enrolments';
	}

	/**
	 * `CREATE TABLE` statement in the format `dbDelta()` expects.
	 *
	 * @return string
	 */
	public static function sql() {
		global $wpdb;
		$table   = self::table();
		$collate = $wpdb->get_charset_collate();
		return "CREATE TABLE {$table} (
			  id bigint(20) unsigned NOT NULL auto_increment,
			  user_id bigint(20) unsigned NOT NULL,
			  course_id bigint(20) unsigned NOT NULL,
			  status varchar(20) NOT NULL DEFAULT 'active',
			  source varchar(20) NOT NULL DEFAULT 'manual',
			  source_type varchar(20) NOT NULL DEFAULT '',
			  source_ref bigint(20) unsigned NOT NULL DEFAULT 0,
			  class_id bigint(20) unsigned DEFAULT NULL,
			  legacy_id bigint(20) unsigned DEFAULT NULL,
			  active_key tinyint(1) unsigned DEFAULT NULL,
			  migrated tinyint(1) unsigned NOT NULL DEFAULT 0,
			  enrolled_at datetime DEFAULT NULL,
			  expires_at datetime DEFAULT NULL,
			  completed_at datetime DEFAULT NULL,
			  updated_at datetime NOT NULL,
			  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			  PRIMARY KEY  (id),
			  UNIQUE KEY legacy_id (legacy_id),
			  UNIQUE KEY active_enrolment (user_id,course_id,source,source_type,source_ref,active_key),
			  KEY course_status (course_id,status),
			  KEY user_status (user_id,status),
			  KEY class_id (class_id),
			  KEY migrated (migrated)
			) ENGINE=InnoDB {$collate};";
	}

	/**
	 * Create or upgrade the table and record the schema version.
	 *
	 * @return bool Whether the table exists afterwards.
	 */
	public static function install() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( self::sql() );
		unset( self::$exists[ self::table() ] );
		if ( ! self::exists() ) {
			return false;
		}
		update_option( self::OPTION, self::VERSION, false );
		return true;
	}

	/**
	 * Whether the table exists for the current prefix (checked once per request and prefix).
	 *
	 * @return bool
	 */
	public static function exists() {
		global $wpdb;
		$table = self::table();
		if ( ! isset( self::$exists[ $table ] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Existence probe; cached for the request.
			self::$exists[ $table ] = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		}
		return self::$exists[ $table ];
	}

	/**
	 * Drop the table and forget the schema version: the down step of the schema change. Legacy storage is
	 * untouched, so every reader falls back to it.
	 *
	 * @return bool
	 */
	public static function uninstall() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Reverses this schema's own table.
		$dropped = false !== $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::table() ) );
		unset( self::$exists[ self::table() ] );
		delete_option( self::OPTION );
		return $dropped;
	}
}
