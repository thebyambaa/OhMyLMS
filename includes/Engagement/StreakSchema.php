<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Additive sidecars; no imported or invented historical streaks.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Engagement;

/** Additive sidecars; no imported or invented historical streaks. */
final class StreakSchema {
	const VERSION = '1';
	const OPTION  = 'ohmylms_streak_schema';
	/**
	 * Table for Streak schema.
	 *
	 * @param mixed $name Name.
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_streak_' . $name; }
	/**
	 * Ready for Streak schema.
	 */
	public static function ready() {
		return get_option( self::OPTION ) === self::VERSION; }
	/**
	 * Install for Streak schema.
	 */
	public static function install() {
		if ( self::ready() ) {
			return true; }
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$collate     = $wpdb->get_charset_collate();
		$definitions = array(
			'state'      => 'user_id bigint unsigned NOT NULL, timezone varchar(64) NOT NULL, current_streak int unsigned NOT NULL DEFAULT 0, longest_streak int unsigned NOT NULL DEFAULT 0, freezes int unsigned NOT NULL DEFAULT 0, refill int unsigned NOT NULL DEFAULT 0, cursor_date date NULL, last_activity datetime NULL, PRIMARY KEY  (user_id)',
			'days'       => 'user_id bigint unsigned NOT NULL, local_date date NOT NULL, status varchar(12) NOT NULL, timezone varchar(64) NOT NULL, recorded_at datetime NOT NULL, PRIMARY KEY  (user_id,local_date)',
			'activities' => 'user_id bigint unsigned NOT NULL, source_type varchar(12) NOT NULL, source_id varchar(64) NOT NULL, local_date date NOT NULL, occurred_at datetime NOT NULL, PRIMARY KEY  (user_id,source_type,source_id), KEY history (user_id,local_date)',
			'milestones' => "user_id bigint unsigned NOT NULL, days int unsigned NOT NULL, badge_id varchar(45) NOT NULL, points int unsigned NOT NULL DEFAULT 0, status varchar(12) NOT NULL DEFAULT 'pending', PRIMARY KEY  (user_id,days)",
		);
		foreach ( $definitions as $name => $definition ) {
			$columns = str_replace( ', ', ",\n", $definition );
			dbDelta( 'CREATE TABLE ' . self::table( $name ) . " (\n$columns\n) ENGINE=InnoDB $collate;" );
		}
		foreach ( $definitions as $name => $definition ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( self::table( $name ) ) ) ) !== self::table( $name ) ) {
				return false; }
			$columns = $wpdb->get_col( 'SHOW COLUMNS FROM ' . self::table( $name ) );
			foreach ( explode( ', ', $definition ) as $part ) {
				$column = strtok( $part, ' ' );
				if ( ! in_array( $column, array( 'PRIMARY', 'KEY' ), true ) && ! in_array( $column, $columns, true ) ) {
					return false; }
			}
			$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', self::table( $name ) ) );
			if ( 'InnoDB' !== $engine ) {
				return false; }
		}
		update_option( self::OPTION, self::VERSION, false );
		return true;
	}
}
