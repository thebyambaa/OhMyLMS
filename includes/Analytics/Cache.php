<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Short-lived cache for analytics aggregates.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Analytics;

/**
 * Transient cache with a version number, so every entry can be dropped at once with flush().
 *
 * Aggregates are cached for `ohmylms_analytics_cache_ttl` seconds (default 60; 0 disables the cache).
 * Entries are not invalidated by individual order or enrolment writes, so the short lifetime is
 * what bounds staleness. Per-row tables (transactions, course report, students) are never cached.
 *
 * @since 1.0.0
 */
final class Cache {

	const VERSION_OPTION = 'ohmylms_analytics_cache_version';

	/**
	 * Seconds an entry lives.
	 *
	 * @return int
	 */
	public static function ttl() {
		/**
		 * Filters the analytics cache lifetime in seconds. Return 0 to disable caching.
		 *
		 * @param int $ttl Seconds.
		 */
		return max( 0, (int) apply_filters( 'ohmylms_analytics_cache_ttl', 60 ) );
	}

	/**
	 * Return a cached value, or compute and store it.
	 *
	 * @param string   $name     Entry name.
	 * @param array    $key      Values that change the result (range, filters).
	 * @param callable $compute  Returns a JSON-serialisable array.
	 * @return array
	 */
	public static function remember( $name, array $key, callable $compute ) {
		$ttl = self::ttl();
		if ( 0 === $ttl ) {
			return $compute();
		}
		$id     = 'ohmylms_an_' . md5( $name . '|' . (int) get_option( self::VERSION_OPTION, 1 ) . '|' . wp_json_encode( $key ) );
		$cached = get_transient( $id );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$value = $compute();
		set_transient( $id, $value, $ttl );
		return $value;
	}

	/**
	 * Invalidate every cached entry.
	 *
	 * @return void
	 */
	public static function flush() {
		update_option( self::VERSION_OPTION, (int) get_option( self::VERSION_OPTION, 1 ) + 1, false );
	}
}
