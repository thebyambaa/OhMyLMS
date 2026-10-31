<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * DashboardAtRiskController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Dashboard\Access;
use OhMyLMS\Dashboard\Period;
use OhMyLMS\Dashboard\Scope;
use OhMyLMS\Dashboard\Widgets\AtRiskNudges;
use OhMyLMS\Dashboard\Widgets\AtRiskSettings;
use RuntimeException;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Routes of the At-risk learners widget (docs/api/dashboard.md, "At-risk learners"):
 *
 * - `POST /ohmylms/v1/dashboard/widgets/at-risk/nudges` (`scope`, `learner`, optional `period`): nudge a
 *   flagged learner of the scope; dashboard access, the scope resolved for the current user;
 * - `GET /ohmylms/v1/dashboard/widgets/at-risk/nudges` (optional `learner`): the nudge log, site administrators;
 * - `GET|PATCH /ohmylms/v1/settings/at-risk`: the rules, with the settings permissions (`manage_options`).
 *
 * Cookie-authenticated calls need the `wp_rest` nonce (core's `rest_cookie_check_errors()`).
 */
class DashboardAtRiskController extends WP_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/dashboard/widgets/at-risk/nudges',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'nudge' ),
					'permission_callback' => array( $this, 'dashboard_permissions_check' ),
					'args'                => array(
						'scope'   => array_merge( Scope::arg(), array( 'required' => true ) ),
						'period'  => Period::arg(),
						'learner' => array(
							'description' => __( 'User id of the learner to nudge.', 'ohmylms' ),
							'type'        => 'integer',
							'minimum'     => 1,
							'required'    => true,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'log' ),
					'permission_callback' => array( $this, 'log_permissions_check' ),
					'args'                => array(
						'learner' => array(
							'description' => __( 'Only nudges of this learner.', 'ohmylms' ),
							'type'        => 'integer',
							'minimum'     => 1,
						),
					),
				),
			)
		);

		$settings = new SettingsController();
		register_rest_route(
			$this->namespace,
			'/settings/' . AtRiskSettings::GROUP,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => function () {
						return $this->settings_response( array() );
					},
					'permission_callback' => array( $settings, 'get_items_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => function ( WP_REST_Request $request ) {
						$values = AtRiskSettings::validate( $request->get_json_params() );
						if ( is_wp_error( $values ) ) {
							return $values;
						}
						$changed = AtRiskSettings::write( $values );
						return is_wp_error( $changed ) ? $changed : $this->settings_response( $changed );
					},
					'permission_callback' => array( $settings, 'update_item_permissions_check' ),
				),
				'schema' => array( AtRiskSettings::class, 'schema' ),
			)
		);
	}

	/**
	 * Dashboard access.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function dashboard_permissions_check( $request ) {
		$user_id = get_current_user_id();
		return Access::can_view( $user_id ) ? true : Access::denied( $user_id );
	}

	/**
	 * Site administrators only.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function log_permissions_check( $request ) {
		$user_id = get_current_user_id();
		if ( Access::is_site_admin( $user_id ) ) {
			return true;
		}
		return new WP_Error( 'ohmylms_at_risk_log_forbidden', __( 'Sorry, you are not allowed to read the nudge log.', 'ohmylms' ), array( 'status' => $user_id > 0 ? 403 : 401 ) );
	}

	/**
	 * Nudge a learner.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function nudge( $request ) {
		$user_id = get_current_user_id();
		$scope   = Scope::from_request( $request, $user_id );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}
		try {
			$result = AtRiskNudges::send( $user_id, $scope, Period::from_request( $request ), (int) $request['learner'] );
		} catch ( RuntimeException $exception ) {
			return new WP_Error( 'ohmylms_dashboard_query_failed', __( 'The dashboard could not be loaded.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		if ( is_wp_error( $result ) ) {
			$data = (array) $result->get_error_data();
			if ( 429 === ( $data['status'] ?? 0 ) ) {
				$response = rest_convert_error_to_response( $result );
				$response->header( 'Retry-After', (string) (int) ( $data['retry_after'] ?? 60 ) );
				return $response;
			}
			return $result;
		}
		$response = rest_ensure_response( $result );
		$response->set_status( 201 );
		$response->header( 'Cache-Control', 'private, no-store' );
		return $response;
	}

	/**
	 * The nudge log.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function log( $request ) {
		$response = rest_ensure_response( AtRiskNudges::log( (int) $request['learner'] ) );
		$response->header( 'Cache-Control', 'private, no-store' );
		return $response;
	}

	/**
	 * Settings response, shaped like the registry groups.
	 *
	 * @param string[] $changed Changed fields.
	 * @return \WP_REST_Response
	 */
	private function settings_response( array $changed ) {
		return rest_ensure_response(
			array(
				'group'   => AtRiskSettings::GROUP,
				'values'  => AtRiskSettings::read(),
				'changed' => $changed,
			)
		);
	}
}
