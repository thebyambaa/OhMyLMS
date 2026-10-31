<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Storage of issued certificates.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Certificates;

defined( 'ABSPATH' ) || exit;

/**
 * One table, `{prefix}ohmylms_issued_certificates`, installed on first use. A row is one certificate a
 * learner received for a course; revoking keeps the row. `active_key` (`<user>:<course>`) is set only on
 * the issued row, so the unique index allows one valid certificate per learner and course while any
 * number of revoked rows remain.
 */
final class IssuedSchema {
	const VERSION = '1';
	const OPTION  = 'ohmylms_issued_certificates_schema';

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_issued_certificates';
	}

	/**
	 * Whether the table is installed at the current version.
	 *
	 * @return bool
	 */
	public static function ready() {
		return self::VERSION === get_option( self::OPTION );
	}

	/**
	 * Column and key definitions.
	 *
	 * @return string
	 */
	public static function definition() {
		return 'code varchar(19) NOT NULL, user_id bigint unsigned NOT NULL, course_id bigint unsigned NOT NULL, certificate_id bigint unsigned NOT NULL DEFAULT 0, status varchar(20) NOT NULL DEFAULT \'issued\', active_key varchar(64) NULL, source varchar(20) NOT NULL DEFAULT \'completion\', student_name varchar(255) NOT NULL DEFAULT \'\', course_title text NOT NULL, template longtext NOT NULL, completed_at datetime NULL, issued_at datetime NOT NULL, issued_by bigint unsigned NOT NULL DEFAULT 0, revoked_at datetime NULL, revoked_by bigint unsigned NOT NULL DEFAULT 0, replaces bigint unsigned NOT NULL DEFAULT 0, UNIQUE KEY code (code), UNIQUE KEY active_key (active_key), KEY user_course (user_id,course_id), KEY course (course_id), KEY certificate (certificate_id), KEY issued (issued_at)';
	}

	/**
	 * Install or upgrade the table; true when it is ready.
	 *
	 * @return bool
	 */
	public static function install() {
		if ( self::ready() ) {
			return true;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$columns = str_replace( ', ', ",\n", self::definition() );
		dbDelta( 'CREATE TABLE ' . self::table() . " (\n id bigint unsigned NOT NULL AUTO_INCREMENT,\n $columns,\n PRIMARY KEY  (id)\n) ENGINE=InnoDB " . $wpdb->get_charset_collate() . ';' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Schema check after dbDelta.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( self::table() ) ) ) !== self::table() ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Schema check after dbDelta.
		$present = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', self::table() ) );
		foreach ( explode( ', ', self::definition() ) as $part ) {
			$column = strtok( $part, ' ' );
			if ( ! in_array( $column, array( 'UNIQUE', 'KEY', 'PRIMARY' ), true ) && ! in_array( $column, $present, true ) ) {
				return false;
			}
		}
		update_option( self::OPTION, self::VERSION, false );
		return true;
	}
}
