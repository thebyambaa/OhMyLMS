<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Learner XP REST controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Engagement\Rest;

use OhMyLMS\Engagement\Xp;
use WP_Error;
use WP_REST_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * `ohmylms/v1/engagement/xp`: the signed-in learner's own XP totals and daily goal.
 */
class XpController extends WP_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'engagement/xp';

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'callback'            => array( $this, 'get_item' ),
				),
				array(
					'methods'             => 'PUT',
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'callback'            => array( $this, 'update_item' ),
				),
			)
		);
	}

	/**
	 * Any signed-in user reads their own XP.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public function get_item_permissions_check( $request ) {
		return is_user_logged_in();
	}

	/**
	 * Any signed-in user sets their own goal.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public function update_item_permissions_check( $request ) {
		return is_user_logged_in();
	}

	/**
	 * The current user's XP summary, or `enabled: false` when XP is off.
	 *
	 * @param \WP_REST_Request $request Request with optional `history_days`.
	 * @return \WP_REST_Response
	 */
	public function get_item( $request ) {
		if ( ! Xp::enabled() ) {
			return rest_ensure_response( array( 'enabled' => false ) );
		}
		$days = $request->get_param( 'history_days' );
		return rest_ensure_response( Xp::summary( get_current_user_id(), $days ? $days : 7 ) );
	}

	/**
	 * Save the current user's daily goal.
	 *
	 * @param \WP_REST_Request $request Request with `goal`.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		if ( ! Xp::enabled() ) {
			return new WP_Error( 'xp_off', __( 'XP is not switched on.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$goal = Xp::set_goal( get_current_user_id(), $request->get_param( 'goal' ) );
		return is_wp_error( $goal ) ? $goal : rest_ensure_response( Xp::summary( get_current_user_id() ) );
	}
}
