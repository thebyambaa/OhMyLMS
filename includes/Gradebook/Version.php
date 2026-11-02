<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Version token of gradebook data, part of every gradebook cache key.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Gradebook;

defined( 'ABSPATH' ) || exit;

/**
 * Replaced on every override, weight, item-setting and scale write, and (once per request) after attempts,
 * grading and enrolment changes, so cached grids never show a stale override.
 *
 * The token is unique per change rather than a counter: two requests raising a counter at the same time
 * can store the same number (and MySQL then reports the second write as unchanged), which would make two
 * different states share a cache key.
 */
final class Version {

	const OPTION = 'ohmylms_gradebook_version';

	/**
	 * Whether this request already replaced the token from a hook.
	 *
	 * @var bool
	 */
	private static $touched = false;

	/**
	 * Current token.
	 *
	 * @return string
	 */
	public static function current() {
		return (string) get_option( self::OPTION, '1' );
	}

	/**
	 * Replace the token now (after a gradebook write).
	 *
	 * @return string The new token.
	 */
	public static function bump() {
		$next = str_replace( '.', '', uniqid( '', true ) ) . wp_rand( 1000, 9999 );
		update_option( self::OPTION, $next, false );
		return $next;
	}

	/**
	 * Replace the token at most once per request (hooked to attempt, grading and enrolment actions).
	 *
	 * @return void
	 */
	public static function touch() {
		if ( self::$touched ) {
			return;
		}
		self::$touched = true;
		self::bump();
	}
}
