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
	 * Routes for Xp hooks.
	 */
	public static function routes() {
		register_rest_route(
			'ohmylms/v1',
			'/engagement/xp',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => 'is_user_logged_in',
					'callback'            => static function ( $request ) {
						if ( ! Xp::enabled() ) {
							return rest_ensure_response( array( 'enabled' => false ) ); }
						return rest_ensure_response( Xp::summary( get_current_user_id(), $request->get_param( 'history_days' ) ?: 7 ) );
					},
				),
				array(
					'methods'             => 'PUT',
					'permission_callback' => 'is_user_logged_in',
					'callback'            => static function ( $request ) {
						if ( ! Xp::enabled() ) {
							return new \WP_Error( 'xp_off', __( 'XP is not switched on.', 'ohmylms' ), array( 'status' => 404 ) ); }
						$goal = Xp::set_goal( get_current_user_id(), $request->get_param( 'goal' ) );
						return is_wp_error( $goal ) ? $goal : rest_ensure_response( Xp::summary( get_current_user_id() ) );
					},
				),
			)
		);
	}
}
