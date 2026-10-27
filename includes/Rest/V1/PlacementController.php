<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Adaptive placement tests: starting one and listing the subjects that have one.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Placement\Placement;
use OhMyLMS\Placement\PlacementModel;
use OhMyLMS\Practice\Guests;
use OhMyLMS\Practice\Sessions;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * A placement test is answered through the practice session routes (show, answer, skip); these
 * routes only start one and say where one can be taken. Learners' results are read through the
 * student report routes.
 */
class PlacementController extends RestController {

	/** Register routes. */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/placement/sessions',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'start' ),
					'permission_callback' => array( $this, 'learner_permission' ),
					'args'                => array(
						'subject'    => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'grade'      => array(
							'type'    => 'integer',
							'minimum' => 0,
							'maximum' => \OhMyLMS\Skills\SubjectProfileModel::MAX_LEVELS,
							'default' => 0,
						),
						'item_limit' => array(
							'type'    => 'integer',
							'minimum' => PlacementModel::MIN_LIMIT,
							'maximum' => PlacementModel::MAX_LIMIT,
							'default' => PlacementModel::DEFAULT_LIMIT,
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/placement/subjects',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'subjects' ),
					'permission_callback' => array( $this, 'learner_permission' ),
				),
			)
		);
	}

	/**
	 * Placement tests are for signed-in learners and for guests with the guest credential practice
	 * uses, on sites with practice switched on.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function learner_permission( WP_REST_Request $request ) {
		if ( ! \OhMyLMS\Assessment\Schema::ready() || ! \OhMyLMS\Assessment\Engine::practice() ) {
			return new WP_Error( 'ohmylms_practice_disabled', __( 'Practice is not available.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		if ( is_user_logged_in() || Guests::from_request( $request ) ) {
			return true;
		}
		return new WP_Error( 'ohmylms_guest_required', __( 'Start a guest session or log in.', 'ohmylms' ), array( 'status' => 401 ) );
	}

	/**
	 * Start a placement test and return its first question in the practice session shape.
	 *
	 * @param WP_REST_Request $request subject, grade, item_limit.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function start( WP_REST_Request $request ) {
		$guest   = is_user_logged_in() ? null : Guests::from_request( $request );
		$owner   = $guest ? array( 'guest_id' => (int) $guest['id'] ) : array( 'student_id' => get_current_user_id() );
		$session = Placement::start( $owner, (string) $request['subject'], (int) $request['grade'], (int) $request['item_limit'] );
		if ( is_wp_error( $session ) ) {
			return $session;
		}
		$response = rest_ensure_response( Sessions::state( $session ) );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Subjects with shared skills, each with where to take its test.
	 *
	 * @return \WP_REST_Response
	 */
	public function subjects() {
		$subjects = array();
		foreach ( Placement::subjects() as $subject ) {
			$subjects[] = array(
				'subject' => $subject,
				'url'     => Placement::url( $subject ),
			);
		}
		return rest_ensure_response( $subjects );
	}
}
