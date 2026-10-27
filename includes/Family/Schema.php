<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping.
/**
 * Additive, independently versioned schema for home-learning family links.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Family;

defined( 'ABSPATH' ) || exit;

/** Never reuses school tables; school_id=0 is not a family-permission wildcard. */
final class Schema {

	const VERSION = '2';
	const OPTION  = 'ohmylms_family_schema';
	const SCOPE   = 'home_learning';

	/**
	 * Prefixed table name.
	 *
	 * @param string $name Table key.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_family_' . $name;
	}

	/**
	 * Whether the feature is on and its tables are installed.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return ( ! defined( 'OHMYLMS_FAMILY_ENABLED' ) || OHMYLMS_FAMILY_ENABLED ) && get_option( self::OPTION ) === self::VERSION;
	}

	/** Create or upgrade tables; repeatable and additive. */
	public static function install() {
		if ( get_option( self::OPTION ) === self::VERSION ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$definitions = array(
			'activity'    => "learner_user_id bigint unsigned NOT NULL, source_kind varchar(20) NOT NULL, source_id bigint unsigned NOT NULL, course_id bigint unsigned NOT NULL DEFAULT 0, content_id bigint unsigned NOT NULL DEFAULT 0, origin varchar(30) NOT NULL DEFAULT 'unknown', assignment_id bigint unsigned NOT NULL DEFAULT 0, school_id bigint unsigned NOT NULL DEFAULT 0, class_id bigint unsigned NOT NULL DEFAULT 0, created_at datetime NOT NULL, completed_at datetime NULL, UNIQUE KEY source (source_kind,source_id), KEY learner_origin (learner_user_id,origin,created_at)",
			'links'       => "guardian_user_id bigint unsigned NOT NULL, learner_user_id bigint unsigned NOT NULL, scope varchar(30) NOT NULL DEFAULT 'home_learning', status varchar(20) NOT NULL DEFAULT 'pending', approved_by bigint unsigned NOT NULL DEFAULT 0, approval_method varchar(30) NOT NULL DEFAULT '', revision int unsigned NOT NULL DEFAULT 1, created_at datetime NOT NULL, updated_at datetime NOT NULL, revoked_at datetime NULL, UNIQUE KEY pair (guardian_user_id,learner_user_id,scope), KEY guardian_status (guardian_user_id,status), KEY learner_status (learner_user_id,status)",
			'invitations' => "token_hash char(64) NOT NULL, kind varchar(20) NOT NULL, issuer_id bigint unsigned NOT NULL, learner_user_id bigint unsigned NOT NULL DEFAULT 0, email varchar(190) NOT NULL DEFAULT '', scope varchar(30) NOT NULL DEFAULT 'home_learning', expires_at datetime NOT NULL, consumed_at datetime NULL, revoked_at datetime NULL, created_at datetime NOT NULL, UNIQUE KEY token_hash (token_hash), KEY issuer (issuer_id,expires_at), KEY learner (learner_user_id,kind)",
			'audit'       => 'actor_id bigint unsigned NOT NULL, action varchar(60) NOT NULL, link_id bigint unsigned NOT NULL DEFAULT 0, created_at datetime NOT NULL, KEY link_time (link_id,created_at)',
		);
		foreach ( $definitions as $name => $definition ) {
			$table   = self::table( $name );
			$columns = str_replace( ', ', ",\n", $definition );
			dbDelta( "CREATE TABLE $table (\n id bigint unsigned NOT NULL AUTO_INCREMENT,\n $columns,\n PRIMARY KEY  (id)\n) ENGINE=InnoDB " . $wpdb->get_charset_collate() . ';' );
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Install-time table check.
				return;
			}
		}
		update_option( self::OPTION, self::VERSION, false );
	}
}
