<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Cache of dashboard widget data per scope and period.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard;

defined( 'ABSPATH' ) || exit;

/**
 * Transients keyed by widget, scope, period and a version number (docs/api/dashboard.md, "Cache").
 *
 * Entries live `ohmylms_dashboard_cache_ttl` seconds (default 600; 0 disables caching). Cached values
 * must not depend on who asks: per-user parts (snoozes, links the user may open) are applied after
 * reading. {@see Cache::flush()} drops every entry at once by raising the version; Bootstrap calls it on
 * cheap, obvious writes (grading, order status changes, manual enrolment and unenrolment).
 */
final class Cache {

	const VERSION_OPTION = 'ohmylms_dashboard_cache_version';

	/**
	 * Default lifetime in seconds.
	 */
	const DEFAULT_TTL = 600;

	/**
	 * Whether the last {@see Cache::remember()} call was served from the cache.
	 *
	 * @var bool
	 */
	private static $last_hit = false;

	/**
	 * Seconds an entry lives.
	 *
	 * @param string $widget Widget id.
	 * @return int
	 */
	public static function ttl( $widget = '' ) {
		/**
		 * Filters the dashboard cache lifetime in seconds. Return 0 to disable caching.
		 *
		 * @param int    $ttl    Seconds.
		 * @param string $widget Widget id ('' when not widget specific).
		 */
		return max( 0, (int) apply_filters( 'ohmylms_dashboard_cache_ttl', self::DEFAULT_TTL, (string) $widget ) );
	}

	/**
	 * Transient name of an entry.
	 *
	 * @param string $widget Widget id.
	 * @param Scope  $scope  Scope.
	 * @param Period $period Period.
	 * @param array  $extra  Other inputs that change the result.
	 * @return string
	 */
	public static function key( $widget, Scope $scope, Period $period, array $extra = array() ) {
		return 'ohmylms_dash_' . md5( implode( '|', array( (string) $widget, (string) self::version(), $scope->cache_key(), $period->cache_key(), (string) wp_json_encode( $extra ) ) ) );
	}

	/**
	 * Return a cached value, or compute and store it.
	 *
	 * @param string   $widget  Widget id.
	 * @param Scope    $scope   Scope.
	 * @param Period   $period  Period.
	 * @param callable $compute Returns a JSON-serialisable array that does not depend on the current user.
	 * @param array    $extra   Other inputs that change the result.
	 * @return array
	 */
	public static function remember( $widget, Scope $scope, Period $period, callable $compute, array $extra = array() ) {
		$ttl            = self::ttl( $widget );
		self::$last_hit = false;
		if ( 0 === $ttl ) {
			return $compute();
		}
		$key    = self::key( $widget, $scope, $period, $extra );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			self::$last_hit = true;
			return $cached;
		}
		$value = $compute();
		set_transient( $key, $value, $ttl );
		return $value;
	}

	/**
	 * Whether the last {@see Cache::remember()} call was a hit.
	 *
	 * @return bool
	 */
	public static function last_was_hit() {
		return self::$last_hit;
	}

	/**
	 * Current version number.
	 *
	 * @return int
	 */
	private static function version() {
		return (int) get_option( self::VERSION_OPTION, 1 );
	}

	/**
	 * Invalidate every entry. Old transients expire on their own.
	 *
	 * @return void
	 */
	public static function flush() {
		update_option( self::VERSION_OPTION, self::version() + 1, false );
	}

	/**
	 * Invalidate every entry after a write, at most once per request (one write may fire several hooks).
	 *
	 * @return void
	 */
	public static function invalidate() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		self::flush();
	}
}
