<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Learner dashboard REST controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Practice\Rest;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Practice\Dashboard;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/** `PUT ohmylms/v1/student/dashboard-theme`: the signed-in learner's dashboard theme. */
class DashboardController extends RestController {

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/student/dashboard-theme',
			array(
				'methods'             => 'PUT',
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'callback'            => array( $this, 'update_item' ),
			)
		);
	}

	/**
	 * Any signed-in user sets their own theme.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function update_item_permissions_check( $request ) {
		return is_user_logged_in();
	}

	/**
	 * Save the theme.
	 *
	 * @param WP_REST_Request $request Request with `theme`.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		$theme = $request->get_param( 'theme' );
		$saved = Dashboard::save_theme( get_current_user_id(), $theme );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		return rest_ensure_response( array( 'theme' => $theme ) );
	}
}
