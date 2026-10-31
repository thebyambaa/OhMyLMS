<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * DashboardScopesController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Dashboard\Access;
use OhMyLMS\Dashboard\ScopeResolver;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * `GET /ohmylms/v1/dashboard/scopes`: the scopes the current user may pick on the dashboard
 * (docs/api/dashboard.md). Site administrators, school administrators and teachers only.
 */
class DashboardScopesController extends WP_REST_Controller {

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
	protected $rest_base = 'dashboard/scopes';

	/**
	 * Register the route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Only users with dashboard access.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		$user_id = get_current_user_id();
		return Access::can_view( $user_id ) ? true : Access::denied( $user_id );
	}

	/**
	 * The user's scopes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) {
		$user_id = get_current_user_id();
		$scopes  = ScopeResolver::available( $user_id );
		$data    = array(
			'scopes'     => array_map(
				static function ( $scope ) {
					return $scope->to_array();
				},
				$scopes
			),
			'default'    => $scopes ? $scopes[0]->key() : null,
			'site_admin' => Access::is_site_admin( $user_id ),
		);
		$response = rest_ensure_response( $data );
		$response->header( 'Cache-Control', 'private, no-store' );
		return $response;
	}

	/**
	 * Response schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}
		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'dashboard-scopes',
			'type'       => 'object',
			'properties' => array(
				'scopes'     => array(
					'description' => __( 'Scopes in picker order: all, then each school followed by its classes, then other classes.', 'ohmylms' ),
					'type'        => 'array',
					'readonly'    => true,
					'items'       => self::scope_schema(),
				),
				'default'    => array(
					'description' => __( 'Scope used when a request names none.', 'ohmylms' ),
					'type'        => array( 'string', 'null' ),
					'readonly'    => true,
				),
				'site_admin' => array(
					'description' => __( 'Whether the user sees site-wide and commerce figures in the "all" scope.', 'ohmylms' ),
					'type'        => 'boolean',
					'readonly'    => true,
				),
			),
		);
		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Schema of one scope.
	 *
	 * @return array
	 */
	public static function scope_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'          => array( 'type' => 'string' ),
				'type'        => array(
					'type' => 'string',
					'enum' => array( 'all', 'school', 'class' ),
				),
				'label'       => array( 'type' => 'string' ),
				'school_id'   => array( 'type' => 'integer' ),
				'school_name' => array( 'type' => 'string' ),
				'commerce'    => array( 'type' => 'boolean' ),
			),
		);
	}
}
