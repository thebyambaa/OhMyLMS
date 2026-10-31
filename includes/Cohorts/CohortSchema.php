<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Cohort tables.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Cohorts;

defined( 'ABSPATH' ) || exit;

/**
 * Installs `ohmylms_cohorts` (adds `membership_id` and `enrollment_start` to the table the
 * installer creates) and `ohmylms_cohort_members`. dbDelta only adds; nothing is dropped.
 *
 * @since 1.0.0
 */
final class CohortSchema {

	const VERSION = '1';
	const OPTION  = 'ohmylms_cohorts_schema';

	/**
	 * Cohort table name.
	 *
	 * @return string
	 */
	public static function cohorts() {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_cohorts';
	}

	/**
	 * Member table name.
	 *
	 * @return string
	 */
	public static function members() {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_cohort_members';
	}

	/**
	 * Install or upgrade the tables when the stored schema version differs.
	 *
	 * @return void
	 */
	public static function ensure() {
		if ( self::VERSION === get_option( self::OPTION ) ) {
			return;
		}
		self::install();
	}

	/**
	 * Create or upgrade both tables.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$cohorts = self::cohorts();
		$members = self::members();
		dbDelta(
			"CREATE TABLE {$cohorts} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  course_id bigint(20) unsigned NOT NULL,
  membership_id bigint(20) unsigned NOT NULL DEFAULT 0,
  title varchar(255) DEFAULT NULL,
  start_date datetime NULL,
  end_date datetime NULL,
  enrollment_start datetime NULL,
  enrollment_end datetime NULL,
  has_capacity tinyint(1) NOT NULL DEFAULT 0,
  capacity int(11) DEFAULT NULL,
  status text DEFAULT NULL,
  meta longtext DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY course_id (course_id),
  KEY membership_id (membership_id)
) {$charset};
CREATE TABLE {$members} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  cohort_id bigint(20) unsigned NOT NULL,
  user_id bigint(20) unsigned NOT NULL,
  source varchar(20) NOT NULL DEFAULT 'manual',
  source_ref bigint(20) unsigned NOT NULL DEFAULT 0,
  joined_at datetime NOT NULL,
  added_by bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY cohort_user (cohort_id,user_id),
  KEY user_id (user_id)
) {$charset};"
		);
		update_option( self::OPTION, self::VERSION, false );
	}
}
