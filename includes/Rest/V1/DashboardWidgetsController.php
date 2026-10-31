<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * DashboardWidgetsController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Dashboard\Access;
use OhMyLMS\Dashboard\Cache;
use OhMyLMS\Dashboard\Period;
use OhMyLMS\Dashboard\Scope;
use OhMyLMS\Dashboard\Snoozes;
use OhMyLMS\Dashboard\Widgets;
use RuntimeException;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Widget data and "needs attention" snoozes (docs/api/dashboard.md):
 *
 * - `GET /ohmylms/v1/dashboard/widgets/<id>?scope=&period=` for every registered `Dashboard\Widget`;
 * - `POST|DELETE /ohmylms/v1/dashboard/widgets/needs-attention/snoozes` with `scope` and `key`.
 *
 * Every route needs dashboard access; the scope is resolved for the current user on each request.
 */
class DashboardWidgetsController extends WP_REST_Controller {

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
	protected $rest_base = 'dashboard/widgets';

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<widget>' . Widgets::ID_PATTERN . ')',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'widget' => array(
							'description' => __( 'Widget id.', 'ohmylms' ),
							'type'        => 'string',
						),
						'scope'  => Scope::arg(),
						'period' => Period::arg(),
					),
				),
			)
		);
		$snooze_args = array(
			'scope' => array_merge( Scope::arg(), array( 'required' => true ) ),
			'key'   => array(
				'description' => __( 'Key of the item, as listed by the widget.', 'ohmylms' ),
				'type'        => 'string',
				'pattern'     => Snoozes::KEY_PATTERN,
				'required'    => true,
			),
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/needs-attention/snoozes',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'snooze' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => $snooze_args,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'wake' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => $snooze_args,
				),
			)
		);
	}

	/**
	 * Dashboard access.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permissions_check( $request ) {
		$user_id = get_current_user_id();
		return Access::can_view( $user_id ) ? true : Access::denied( $user_id );
	}

	/**
	 * Widget data for a scope and period.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$widget = Widgets::get( (string) $request['widget'] );
		if ( ! $widget ) {
			return new WP_Error( 'ohmylms_dashboard_widget_not_found', __( 'Unknown dashboard widget.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$user_id = get_current_user_id();
		$scope   = Scope::from_request( $request, $user_id );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}
		if ( ! $widget->available( $scope, $user_id ) ) {
			return new WP_Error( 'ohmylms_dashboard_widget_unavailable', __( 'This widget is not available for this school or class.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		$period = Period::from_request( $request );

		try {
			$entry = Cache::remember(
				$widget->id(),
				$scope,
				$period,
				static function () use ( $widget, $scope, $period ) {
					return array(
						'generated_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
						'data'         => $widget->compute( $scope, $period ),
					);
				}
			);
			$hit  = Cache::last_was_hit();
			$data = $widget->present( (array) $entry['data'], $scope, $period, $user_id );
		} catch ( RuntimeException $exception ) {
			return new WP_Error( 'ohmylms_dashboard_query_failed', __( 'The dashboard could not be loaded.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		$response = rest_ensure_response(
			array(
				'widget'       => $widget->id(),
				'scope'        => $scope->to_array(),
				'period'       => $period->to_array(),
				'generated_at' => (string) $entry['generated_at'],
				'data'         => $data,
			)
		);
		$response->header( 'Cache-Control', 'private, no-store' );
		$response->header( 'X-OhMyLMS-Cache', $hit ? 'hit' : 'miss' );
		return $response;
	}

	/**
	 * Snooze an item for the current user.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function snooze( $request ) {
		$user_id = get_current_user_id();
		$scope   = Scope::from_request( $request, $user_id );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}
		$key   = (string) $request['key'];
		$until = Snoozes::snooze( $user_id, $scope, $key );
		return rest_ensure_response(
			array(
				'scope' => $scope->key(),
				'key'   => $key,
				'until' => gmdate( 'Y-m-d\TH:i:s\Z', $until ),
			)
		);
	}

	/**
	 * Remove a snooze of the current user.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function wake( $request ) {
		$user_id = get_current_user_id();
		$scope   = Scope::from_request( $request, $user_id );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}
		$key = (string) $request['key'];
		return rest_ensure_response(
			array(
				'scope'   => $scope->key(),
				'key'     => $key,
				'removed' => Snoozes::wake( $user_id, $scope, $key ),
			)
		);
	}
}
