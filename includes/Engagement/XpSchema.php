<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Additive sidecars for XP and the skill mastery score. Nothing existing is rewritten and no
 * history is invented: the score of past practice is computed on first read from the evidence
 * that is already stored.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Engagement;

defined( 'ABSPATH' ) || exit;

/**
 * Additive sidecars for XP and the skill mastery score. Nothing existing is rewritten and no
 * history is invented: the score of past practice is computed on first read from the evidence
 * that is already stored.
 */
final class XpSchema {
	const VERSION = '1';
	const OPTION  = 'ohmylms_xp_schema';

	/**
	 * Table for Xp schema.
	 *
	 * @param mixed $name Name.
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_' . $name;
	}

	/**
	 * Ready for Xp schema.
	 */
	public static function ready() {
		return get_option( self::OPTION ) === self::VERSION;
	}

	/** The column and key definitions, one entry per table. */
	public static function definitions() {
		return array(
			// One row per XP award; event_key makes every award idempotent per learner.
			'xp_events'    => 'student_id bigint unsigned NOT NULL, event_key varchar(100) NOT NULL, kind varchar(20) NOT NULL, xp int NOT NULL, term_id bigint unsigned NOT NULL DEFAULT 0, source_type varchar(20) NOT NULL DEFAULT \'\', source_id bigint unsigned NOT NULL DEFAULT 0, meta text NULL, local_date date NOT NULL, created_at datetime NOT NULL, UNIQUE KEY learner_event (student_id,event_key), KEY by_day (student_id,local_date)',
			// The current mastery score per learner and skill, rebuilt from evidence at any time.
			'skill_scores' => 'student_id bigint unsigned NOT NULL, term_id bigint unsigned NOT NULL, score decimal(5,2) NOT NULL DEFAULT 0, peak decimal(5,2) NOT NULL DEFAULT 0, scored int unsigned NOT NULL DEFAULT 0, correct int unsigned NOT NULL DEFAULT 0, struggling tinyint unsigned NOT NULL DEFAULT 0, milestones varchar(16) NOT NULL DEFAULT \'\', last_scored_at datetime NULL, updated_at datetime NOT NULL, UNIQUE KEY learner_skill (student_id,term_id), KEY by_skill (term_id,score)',
		);
	}

	/**
	 * Install for Xp schema.
	 */
	public static function install() {
		if ( self::ready() ) {
			return true; }
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$collate     = $wpdb->get_charset_collate();
		$definitions = self::definitions();
		foreach ( $definitions as $name => $definition ) {
			$columns = str_replace( ', ', ",\n", $definition );
			dbDelta( 'CREATE TABLE ' . self::table( $name ) . " (\n id bigint unsigned NOT NULL AUTO_INCREMENT,\n $columns,\n PRIMARY KEY  (id)\n) ENGINE=InnoDB $collate;" );
		}
		foreach ( $definitions as $name => $definition ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( self::table( $name ) ) ) ) !== self::table( $name ) ) {
				return false; }
			$columns = $wpdb->get_col( 'SHOW COLUMNS FROM ' . self::table( $name ) );
			foreach ( explode( ', ', $definition ) as $part ) {
				$column = strtok( $part, ' ' );
				if ( ! in_array( $column, array( 'UNIQUE', 'KEY', 'PRIMARY' ), true ) && ! in_array( $column, $columns, true ) ) {
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
