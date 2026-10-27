<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Nestable database transaction. MySQL has no nested transactions, so only the
 * outermost call starts/commits; any failure rolls back the whole unit.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Utility;

defined( 'ABSPATH' ) || exit;

/**
 * Nestable database transaction. MySQL has no nested transactions, so only the
 * outermost call starts/commits; any failure rolls back the whole unit.
 */
final class Transaction {
	/**
	 * Depth.
	 *
	 * @var mixed
	 */
	private static $depth = 0;

	/** Run $work inside one transaction and return its result. Exceptions roll back and are rethrown.
	 *
	 * @param callable $work Work.
	 */
	public static function run( callable $work ) {
		global $wpdb;
		if ( self::$depth === 0 ) {
			$wpdb->query( 'START TRANSACTION' ); }
		++self::$depth;
		try {
			$result = $work();
		} catch ( \Throwable $error ) {
			--self::$depth;
			if ( self::$depth === 0 ) {
				$wpdb->query( 'ROLLBACK' ); }
			throw $error;
		}
		--self::$depth;
		if ( self::$depth === 0 ) {
			$wpdb->query( 'COMMIT' ); }
		return $result;
	}

	/**
	 * Active for Transaction.
	 */
	public static function active() {
		return self::$depth > 0; }
}
