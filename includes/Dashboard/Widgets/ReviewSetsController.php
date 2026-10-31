<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * "Assign review set": creates the draft review set of a recommended group.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard\Widgets;

use OhMyLMS\Dashboard\Scope;
use RuntimeException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * `POST /ohmylms/v1/dashboard/widgets/review-sets/assign` with `scope`, `class_id` and `skill_id`
 * ({@see ReviewSetsService::assign()}): 201 with the new draft, 200 with the one that already exists.
 */
final class ReviewSetsController {

	/**
	 * Register the route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'ohmylms/v1',
			'/dashboard/widgets/review-sets/assign',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'assign' ),
					'permission_callback' => array( MasteryController::class, 'permissions_check' ),
					'args'                => array(
						'scope'    => array_merge( Scope::arg(), array( 'required' => true ) ),
						'class_id' => array(
							'description' => __( 'Class of the group.', 'ohmylms' ),
							'type'        => 'integer',
							'minimum'     => 1,
							'required'    => true,
						),
						'skill_id' => array(
							'description' => __( 'Skill to review.', 'ohmylms' ),
							'type'        => 'integer',
							'minimum'     => 1,
							'required'    => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Create (or return) the draft review set.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function assign( $request ) {
		$user_id = get_current_user_id();
		$scope   = Scope::from_request( $request, $user_id );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}
		try {
			$result = ReviewSetsService::assign( $scope, (int) $request['class_id'], (int) $request['skill_id'], $user_id );
		} catch ( RuntimeException $exception ) {
			return new WP_Error( 'ohmylms_dashboard_query_failed', __( 'The dashboard could not be loaded.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$response = rest_ensure_response( array( 'scope' => $scope->key() ) + $result );
		$response->set_status( $result['created'] ? 201 : 200 );
		$response->header( 'Cache-Control', 'private, no-store' );
		return $response;
	}
}
