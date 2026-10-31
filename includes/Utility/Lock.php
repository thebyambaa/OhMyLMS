<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Named locks: MySQL advisory locks (`GET_LOCK`) and option-row locks.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Utility;

defined( 'ABSPATH' ) || exit;

/**
 * The one place that takes and releases named locks (docs/BACKEND-BOUNDARIES.md, plan step 9).
 *
 * Advisory locks belong to the database connection: MySQL frees them when the connection closes, and the
 * connection that holds a name can take it again (MySQL 5.7+, MariaDB 10.0.2+); each take needs its own
 * release, so nested `with()` calls on one name are safe. Option-row locks (`ohmylms_lock_<name>` rows,
 * `add_option()` inserts on the unique option_name) outlive the connection and expire after a TTL instead.
 */
final class Lock {

	/**
	 * Prefixes that already mark a plugin lock name; other names get {@see Lock::PREFIX}.
	 * `oml` is the short prefix of the exam import and free-sale locks.
	 */
	const PREFIXES = array( 'ohmylms', 'oml' );

	/**
	 * Prefix added to names that do not start with one of {@see Lock::PREFIXES}.
	 */
	const PREFIX = 'ohmylms-';

	/**
	 * Longest name MySQL accepts for `GET_LOCK()`.
	 */
	const MAX_LENGTH = 64;

	/**
	 * Option name prefix of option-row locks.
	 */
	const OPTION_PREFIX = 'ohmylms_lock_';

	/**
	 * The advisory lock name used for $name: prefixed when it has no plugin prefix, and, when longer than 64
	 * characters, its first 31 characters (letters, digits, `_.:-`) plus `-` and the MD5 of the whole name.
	 *
	 * @param string $name Lock name.
	 * @return string
	 */
	public static function name( string $name ): string {
		$prefixed = false;
		foreach ( self::PREFIXES as $prefix ) {
			$prefixed = $prefixed || 0 === strpos( $name, $prefix );
		}
		if ( ! $prefixed ) {
			$name = self::PREFIX . $name;
		}
		if ( strlen( $name ) > self::MAX_LENGTH ) {
			$name = substr( (string) preg_replace( '/[^A-Za-z0-9_.:-]/', '', $name ), 0, 31 ) . '-' . md5( $name );
		}
		return $name;
	}

	/**
	 * Take an advisory lock, waiting up to $timeout seconds.
	 *
	 * @param string $name    Lock name (see name()).
	 * @param int    $timeout Seconds to wait; 0 answers at once.
	 * @return bool True when this connection holds the lock.
	 */
	public static function acquire( string $name, int $timeout ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Advisory lock, not table data; never cached.
		return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', self::name( $name ), $timeout ) );
	}

	/**
	 * Release one take of an advisory lock held by this connection.
	 *
	 * @param string $name Lock name (see name()).
	 * @return void
	 */
	public static function release( string $name ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Advisory lock, not table data; never cached.
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', self::name( $name ) ) );
	}

	/**
	 * Run $work under an advisory lock. The lock is released when $work returns or throws; exceptions propagate.
	 *
	 * @param string   $name    Lock name (see name()).
	 * @param int      $timeout Seconds to wait for the lock; 0 answers at once.
	 * @param callable $work    Runs under the lock; its result is returned.
	 * @param mixed    $busy    Returned, without running $work, when the lock is not taken in time. A Closure is
	 *                          called instead and its result returned (it may build an error or throw). Null
	 *                          (the default) returns the `ohmylms_lock_busy` error, status 409.
	 * @return mixed What $work returns, or the busy value.
	 */
	public static function with( string $name, int $timeout, callable $work, $busy = null ) {
		if ( ! self::acquire( $name, $timeout ) ) {
			return self::busy( $busy );
		}
		try {
			return $work();
		} finally {
			self::release( $name );
		}
	}

	/**
	 * Option name of an option-row lock.
	 *
	 * @param string $name Lock name (letters, digits, dashes and underscores; see sanitize_key()).
	 * @return string
	 */
	public static function option_key( string $name ): string {
		return self::OPTION_PREFIX . sanitize_key( $name );
	}

	/**
	 * Take an option-row lock. A lock older than $ttl seconds counts as abandoned and is taken over.
	 *
	 * @param string $name Lock name (see option_key()).
	 * @param int    $ttl  Seconds after which a held lock is stale.
	 * @return bool True when the lock was taken.
	 */
	public static function acquire_option( string $name, int $ttl ): bool {
		$key = self::option_key( $name );
		if ( add_option( $key, time(), '', 'no' ) ) {
			return true;
		}
		$taken = (int) get_option( $key );
		if ( $taken && ( time() - $taken ) > $ttl ) {
			update_option( $key, time(), 'no' );
			return true;
		}
		return false;
	}

	/**
	 * Release an option-row lock.
	 *
	 * @param string $name Lock name (see option_key()).
	 * @return void
	 */
	public static function release_option( string $name ) {
		delete_option( self::option_key( $name ) );
	}

	/**
	 * Run $work under an option-row lock; like with(), the lock is released when $work returns or throws.
	 *
	 * @param string   $name Lock name (see option_key()).
	 * @param int      $ttl  Seconds after which a held lock is stale.
	 * @param callable $work Runs under the lock; its result is returned.
	 * @param mixed    $busy Busy value, as for with().
	 * @return mixed What $work returns, or the busy value.
	 */
	public static function with_option( string $name, int $ttl, callable $work, $busy = null ) {
		if ( ! self::acquire_option( $name, $ttl ) ) {
			return self::busy( $busy );
		}
		try {
			return $work();
		} finally {
			self::release_option( $name );
		}
	}

	/**
	 * The value a busy lock answers.
	 *
	 * @param mixed $busy Busy value, Closure or null.
	 * @return mixed
	 */
	private static function busy( $busy ) {
		if ( $busy instanceof \Closure ) {
			return $busy();
		}
		if ( null === $busy ) {
			return new \WP_Error( 'ohmylms_lock_busy', __( 'Another request is saving this. Try again in a moment.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		return $busy;
	}
}
