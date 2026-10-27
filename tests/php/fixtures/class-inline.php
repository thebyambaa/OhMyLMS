<?php
/**
 * Inline assessment renderer fixture for block contract tests.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Practice;

/** Observe delegation without creating practice sessions in a unit test. */
final class Inline {
	/**
	 * Last question reference.
	 *
	 * @var string
	 */
	public static $uuid;

	/**
	 * Stand in for the existing learner access and grading boundary.
	 *
	 * @param string $uuid Question reference.
	 * @return string Learner markup.
	 */
	public static function render( $uuid ) {
		self::$uuid = $uuid;
		return 'existing-inline-renderer';
	}
}
