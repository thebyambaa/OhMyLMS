<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Remembers repeated reads while a front-end page is rendered.
 *
 * Templates ask the same question many times in one page view: the learner hub read a course's
 * enrolment and program seven or eight times each. Those reads go straight to custom tables, so
 * WordPress's own caches do not cover them.
 *
 * Values are only remembered once `template_redirect` has finished on a GET or HEAD request. Every
 * action that changes data for such a request (enrolling through a link, redirects, form handlers)
 * runs before that point, so what follows only reads. REST, AJAX, admin, cron and POST requests
 * never remember anything and always read fresh data.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Utility;

defined( 'ABSPATH' ) || exit;

/** Request-scoped read cache for the render phase of a page view. */
final class RenderCache {

	/**
	 * Whether a page is being rendered.
	 *
	 * @var bool
	 */
	private static $rendering = false;

	/**
	 * Remembered values by key.
	 *
	 * @var array<string,mixed>
	 */
	private static $values = array();

	/** Start remembering after every `template_redirect` callback has run. */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'start' ), PHP_INT_MAX );
	}

	/** Begin the render phase of a read-only page view. */
	public static function start() {
		$method          = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
		self::$rendering = in_array( $method, array( 'GET', 'HEAD' ), true );
	}

	/**
	 * Return the remembered value for a key, reading it once.
	 *
	 * @param string   $key  Key naming the read and its arguments.
	 * @param callable $read Reads the value.
	 * @return mixed
	 */
	public static function remember( $key, callable $read ) {
		if ( ! self::$rendering ) {
			return $read();
		}
		if ( ! array_key_exists( $key, self::$values ) ) {
			self::$values[ $key ] = $read();
		}
		return self::$values[ $key ];
	}

	/** Forget everything, for code that changes data while a page renders. */
	public static function flush() {
		self::$values = array();
	}

	/** Stop remembering and forget everything. */
	public static function stop() {
		self::$rendering = false;
		self::$values    = array();
	}
}
