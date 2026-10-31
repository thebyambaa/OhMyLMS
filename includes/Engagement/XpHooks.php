<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Wires XP and the mastery score into practice, and exposes the learner's own totals.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Engagement;

use OhMyLMS\Skills\Score;

defined( 'ABSPATH' ) || exit;

/** Wires XP and the mastery score into practice, and exposes the learner's own totals. */
final class XpHooks {
	/**
	 * Init for Xp hooks.
	 */
	public static function init() {
		add_action(
			'init',
			static function () {
				// Storage is installed the first time either feature is on.
				if ( XpSettings::enabled() || Score::get()['enable'] ) {
					XpSchema::install(); }
			},
			7
		);
		// After the streak hook (priority 10), so the day is already counted when XP is read.
		add_action( 'ohmylms_practice_completed', array( self::class, 'practice' ), 11 );
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * Practice for Xp hooks.
	 *
	 * @param mixed $uuid Uuid.
	 */
	public static function practice( $uuid ) {
		Xp::award_practice( (string) $uuid );
	}

	/**
	 * Register the learner XP routes ({@see Rest\XpController}).
	 */
	public static function routes() {
		( new Rest\XpController() )->register_routes();
	}
}
