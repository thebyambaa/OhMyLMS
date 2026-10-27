<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Tables for the xAPI emitter.
 *
 * @package OhMyLMS\XApi
 */

namespace OhMyLMS\XApi;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Installation checks that the tables exist; the answer must come from the database, not a cache.

/**
 * Additive, independently versioned schema for the statement outbox and registrations.
 */
final class Schema {

	const VERSION = '1';
	const OPTION  = 'ohmylms_xapi_schema';

	/**
	 * Prefixed table name.
	 *
	 * @param string $name Table name without prefix.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;

		return $wpdb->prefix . 'ohmylms_xapi_' . $name;
	}

	/**
	 * Whether the tables are installed at the current version.
	 *
	 * @return bool
	 */
	public static function ready() {
		return get_option( self::OPTION ) === self::VERSION;
	}

	/**
	 * Create or upgrade the tables.
	 *
	 * @return bool
	 */
	public static function install() {
		if ( self::ready() ) {
			return true;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		// Outbox. A row is a reference to an OhMyLMS event; the statement itself is built when it is sent.
		dbDelta(
			'CREATE TABLE ' . self::table( 'outbox' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			statement_uuid char(36) NOT NULL,
			student_id bigint(20) unsigned NOT NULL DEFAULT 0,
			kind varchar(40) NOT NULL,
			ref longtext NOT NULL,
			occurred_at datetime NOT NULL,
			statement longtext NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			tries int(10) unsigned NOT NULL DEFAULT 0,
			next_attempt_at datetime NOT NULL,
			last_error text NULL,
			created_at datetime NOT NULL,
			sent_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY statement_uuid (statement_uuid),
			KEY queue (status,next_attempt_at,id),
			KEY student (student_id),
			KEY pruning (status,sent_at)
		) ENGINE=InnoDB $charset;"
		);

		// One registration (a learner's enrolment in a course) per student and course.
		dbDelta(
			'CREATE TABLE ' . self::table( 'registrations' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			student_id bigint(20) unsigned NOT NULL,
			course_id bigint(20) unsigned NOT NULL,
			registration char(36) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY student_course (student_id,course_id),
			UNIQUE KEY registration (registration)
		) ENGINE=InnoDB $charset;"
		);

		foreach ( array( 'outbox', 'registrations' ) as $name ) {
			$table = self::table( $name );
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
				return false;
			}
		}
		update_option( self::OPTION, self::VERSION, false );

		return true;
	}
}
