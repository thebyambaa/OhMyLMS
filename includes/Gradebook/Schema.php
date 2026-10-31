<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Tables of the gradebook: item settings, category weights, cell overrides and their history.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Gradebook;

defined( 'ABSPATH' ) || exit;

/**
 * Additive, independently versioned schema (option `ohmylms_gradebook_schema`). Installed on first use
 * by {@see Schema::ensure()}; {@see Schema::uninstall()} drops it again (the legacy `gradebook_overrides`
 * table is never touched by either).
 *
 * - `gradebook_items`: per course and graded item, the category, an optional maximum, the audience
 *   (`all`, `classes` with `class_ids`, `individuals`) and an optional due date (UTC).
 * - `gradebook_weights`: category weights of a course (`class_id` 0) or of one class in it (override).
 * - `gradebook_cells`: the current manual state of a cell (`score` or `excused`; `none` after a revert),
 *   with a version that every change raises (stale writes answer 409).
 * - `gradebook_cell_history`: every change with who, when, the old and new values and the reason.
 */
final class Schema {

	const VERSION = '1';
	const OPTION  = 'ohmylms_gradebook_schema';

	/**
	 * Tables known to exist, per prefix.
	 *
	 * @var array<string,bool>
	 */
	private static $ready = array();

	/**
	 * Full table name.
	 *
	 * @param string $name Short name (`gradebook_cells`).
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_' . $name;
	}

	/**
	 * Column definitions per table.
	 *
	 * @return array<string,string>
	 */
	private static function definitions() {
		return array(
			'gradebook_items'        => "course_id bigint unsigned NOT NULL, content_id bigint unsigned NOT NULL, category varchar(40) NOT NULL DEFAULT '', max_marks decimal(12,4) NULL, audience varchar(20) NOT NULL DEFAULT 'all', class_ids varchar(500) NOT NULL DEFAULT '', due_at datetime NULL, updated_by bigint unsigned NOT NULL DEFAULT 0, updated_at datetime NOT NULL, UNIQUE KEY course_item (course_id,content_id)",
			'gradebook_weights'      => 'course_id bigint unsigned NOT NULL, class_id bigint unsigned NOT NULL DEFAULT 0, weights text NOT NULL, version int unsigned NOT NULL DEFAULT 1, updated_by bigint unsigned NOT NULL DEFAULT 0, updated_at datetime NOT NULL, UNIQUE KEY course_class (course_id,class_id), KEY class_id (class_id)',
			'gradebook_cells'        => "course_id bigint unsigned NOT NULL, content_id bigint unsigned NOT NULL, user_id bigint unsigned NOT NULL, kind varchar(20) NOT NULL DEFAULT 'none', score decimal(12,4) NULL, max_score decimal(12,4) NULL, reason text NOT NULL, version int unsigned NOT NULL DEFAULT 1, updated_by bigint unsigned NOT NULL DEFAULT 0, updated_at datetime NOT NULL, UNIQUE KEY grade_cell (course_id,content_id,user_id), KEY user_id (user_id)",
			'gradebook_cell_history' => "cell_id bigint unsigned NOT NULL, course_id bigint unsigned NOT NULL, content_id bigint unsigned NOT NULL, user_id bigint unsigned NOT NULL, action varchar(20) NOT NULL, old_kind varchar(20) NOT NULL DEFAULT 'none', old_score decimal(12,4) NULL, new_kind varchar(20) NOT NULL DEFAULT 'none', new_score decimal(12,4) NULL, max_score decimal(12,4) NULL, computed_score decimal(12,4) NULL, reason text NOT NULL, actor_id bigint unsigned NOT NULL DEFAULT 0, created_at datetime NOT NULL, KEY cell (course_id,content_id,user_id,id), KEY user_id (user_id)",
		);
	}

	/**
	 * Whether every table exists for the current prefix (memoised per prefix).
	 *
	 * @return bool
	 */
	public static function ready() {
		global $wpdb;
		$prefix = $wpdb->prefix;
		if ( isset( self::$ready[ $prefix ] ) ) {
			return self::$ready[ $prefix ];
		}
		if ( self::VERSION !== get_option( self::OPTION ) ) {
			return false;
		}
		foreach ( array_keys( self::definitions() ) as $name ) {
			$table = self::table( $name );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Schema probe, memoised.
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
				self::$ready[ $prefix ] = false;
				return false;
			}
		}
		self::$ready[ $prefix ] = true;
		return true;
	}

	/**
	 * Install when needed; true when the tables exist afterwards.
	 *
	 * @return bool
	 */
	public static function ensure() {
		if ( self::ready() ) {
			return true;
		}
		self::install();
		global $wpdb;
		unset( self::$ready[ $wpdb->prefix ] );
		return self::ready();
	}

	/**
	 * Create or update the tables, then copy legacy per-cell overrides (`gradebook_overrides`) that have no
	 * cell yet, with one history row each (`action` = `import`). Idempotent.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( self::definitions() as $name => $definition ) {
			$table   = self::table( $name );
			$columns = str_replace( ', ', ",\n", $definition );
			dbDelta( "CREATE TABLE $table (\n id bigint unsigned NOT NULL AUTO_INCREMENT,\n $columns,\n PRIMARY KEY  (id)\n) ENGINE=InnoDB " . $wpdb->get_charset_collate() . ';' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Schema probe.
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
				return;
			}
		}
		self::import_legacy();
		update_option( self::OPTION, self::VERSION, false );
	}

	/**
	 * Copy legacy overrides into cells (only cells that do not exist yet).
	 *
	 * @return void
	 */
	private static function import_legacy() {
		global $wpdb;
		$legacy = \OhMyLMS\Schools\Schema::table( 'gradebook_overrides' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Schema probe.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $legacy ) ) ) !== $legacy ) {
			return;
		}
		$cells   = self::table( 'gradebook_cells' );
		$history = self::table( 'gradebook_cell_history' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- One-time set-based copy.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO %i (course_id,content_id,user_id,kind,score,max_score,reason,version,updated_by,updated_at)
				SELECT o.course_id,o.content_id,o.user_id,'score',o.score,o.max_score,o.note,1,o.updated_by,o.updated_at FROM %i o
				LEFT JOIN %i c ON c.course_id=o.course_id AND c.content_id=o.content_id AND c.user_id=o.user_id WHERE c.id IS NULL",
				$cells,
				$legacy,
				$cells
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO %i (cell_id,course_id,content_id,user_id,action,old_kind,new_kind,new_score,max_score,reason,actor_id,created_at)
				SELECT c.id,c.course_id,c.content_id,c.user_id,'import','none','score',c.score,c.max_score,c.reason,c.updated_by,c.updated_at FROM %i c
				LEFT JOIN %i h ON h.cell_id=c.id WHERE h.id IS NULL AND c.kind='score'",
				$history,
				$cells,
				$history
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Drop the tables and the schema option (schema down). Legacy overrides stay where they were.
	 *
	 * @return void
	 */
	public static function uninstall() {
		global $wpdb;
		foreach ( array_keys( self::definitions() ) as $name ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema down.
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::table( $name ) ) );
		}
		delete_option( self::OPTION );
		unset( self::$ready[ $wpdb->prefix ] );
	}

	/**
	 * Forget the memoised probe (tests).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$ready = array();
	}
}
