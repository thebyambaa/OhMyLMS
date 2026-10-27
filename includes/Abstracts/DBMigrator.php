<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Database migration class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Abstracts;

/**
 * Database migration class.
 *
 * Abstract class to handle database migration classes.
 */
abstract class DBMigrator {

	/**
	 * Migrate the database table.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 * */
	abstract public static function migrate();
}
