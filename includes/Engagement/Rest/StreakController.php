<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Learner streak REST controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Engagement\Rest;

use OhMyLMS\Engagement\Streak;
use WP_REST_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * `ohmylms/v1/engagement/streak`: the signed-in learner's own streak and timezone.
 */
class StreakController extends WP_REST_Controller {

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
	protected $rest_base = 'engagement/streak';

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
	 * Any signed-in user reads their own streak.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public function get_item_permissions_check( $request ) {
		return is_user_logged_in();
	}

	/**
	 * Any signed-in user sets their own timezone.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public function update_item_permissions_check( $request ) {
		return is_user_logged_in();
	}

	/**
	 * The current user's streak snapshot.
	 *
	 * @param \WP_REST_Request $request Request with optional `history_days`.
	 * @return \WP_REST_Response
	 */
	public function get_item( $request ) {
		$days = $request->get_param( 'history_days' );
		return rest_ensure_response( Streak::snapshot( get_current_user_id(), $days ? $days : 7 ) );
	}

	/**
	 * Save the current user's streak timezone.
	 *
	 * @param \WP_REST_Request $request Request with `timezone`.
	 * @return \WP_REST_Response
	 */
	public function update_item( $request ) {
		return rest_ensure_response( Streak::set_timezone( get_current_user_id(), $request->get_param( 'timezone' ) ) );
	}
}
