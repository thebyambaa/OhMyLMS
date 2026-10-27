<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Course Bundle administration: create, describe, edit members, publish and delete.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Curriculum\Access;
use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Links;
use OhMyLMS\Curriculum\Schema;
use OhMyLMS\Bundles\Bundles;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/** Course Bundle administration: create, describe, edit members, publish and delete. */
class BundleController extends RestController {
	/**
	 * Register routes for Bundle controller.
	 */
	public function register_routes() {
		$admin = array( Access::class, 'admin' );
		register_rest_route(
			$this->namespace,
			'/bundles',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create' ),
					'permission_callback' => $admin,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/bundles/targets',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'targets' ),
					'permission_callback' => $admin,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/bundles/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => 'PUT,PATCH',
					'callback'            => array( $this, 'update' ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => $admin,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/bundles/(?P<id>\d+)/items',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'set_members' ),
					'permission_callback' => $admin,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/bundles/(?P<id>\d+)/publish',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'publish' ),
					'permission_callback' => $admin,
				),
			)
		);
	}

	/**
	 * Detail for Bundle controller.
	 *
	 * @param mixed $bundle_id Bundle identifier.
	 */
	private function detail( $bundle_id ) {
		$bundle = Bundles::get( $bundle_id );
		return $bundle ? array(
			'bundle'  => Bundles::describe( $bundle ),
			'members' => Bundles::describe_members( $bundle_id ),
		) : null;
	}

	/**
	 * Index for Bundle controller.
	 */
	public function index() {
		return rest_ensure_response(
			array(
				'bundles' => array_map( array( Bundles::class, 'describe' ), Bundles::all() ),
				'limits'  => array(
					'max_members' => Bundles::MAX_MEMBERS,
					'max_bundles' => Bundles::MAX_BUNDLES,
				),
			)
		);
	}

	/**
	 * Create for Bundle controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function create( WP_REST_Request $request ) {
		$bundle = Bundles::create( $request->get_params() );
		if ( is_wp_error( $bundle ) ) {
			return $bundle; }
		$response = rest_ensure_response( $this->detail( (int) $bundle['id'] ) );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Show for Bundle controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function show( WP_REST_Request $request ) {
		$detail = $this->detail( (int) $request['id'] );
		return $detail ? rest_ensure_response( $detail ) : Bundles::missing();
	}

	/**
	 * Update for Bundle controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function update( WP_REST_Request $request ) {
		$params = $request->get_params();
		$bundle = Bundles::update( (int) $request['id'], array_intersect_key( $params, array_flip( array( 'title', 'description' ) ) ), $params['expected_updated_at'] ?? null );
		return is_wp_error( $bundle ) ? $bundle : rest_ensure_response( $this->detail( (int) $bundle['id'] ) );
	}

	/**
	 * Set members for Bundle controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function set_members( WP_REST_Request $request ) {
		$items = $request->get_param( 'items' );
		if ( ! is_array( $items ) ) {
			return Access::error( 'ohmylms_bundle_invalid', __( 'Send the ordered list of members.', 'ohmylms' ) ); }
		$bundle = Bundles::set_members( (int) $request['id'], $items, $request->get_param( 'expected_updated_at' ) );
		return is_wp_error( $bundle ) ? $bundle : rest_ensure_response( $this->detail( (int) $bundle['id'] ) );
	}

	/**
	 * Publish for Bundle controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function publish( WP_REST_Request $request ) {
		$published = $request->has_param( 'published' ) ? rest_sanitize_boolean( $request->get_param( 'published' ) ) : true;
		$bundle    = Bundles::set_status( (int) $request['id'], $published );
		return is_wp_error( $bundle ) ? $bundle : rest_ensure_response( $this->detail( (int) $bundle['id'] ) );
	}

	/**
	 * Delete for Bundle controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function delete( WP_REST_Request $request ) {
		$result = Bundles::delete( (int) $request['id'], rest_sanitize_boolean( $request->get_param( 'force' ) ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/** Courses or curriculum items an administrator can add to a bundle.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function targets( WP_REST_Request $request ) {
		global $wpdb;
		$type   = (string) $request->get_param( 'type' );
		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		if ( 'course' === $type ) {
			return rest_ensure_response( Links::targets( 'course', $search ) ); }
		if ( 'curriculum' !== $type ) {
			return Access::error( 'ohmylms_bundle_invalid', __( 'Choose courses or curriculum items.', 'ohmylms' ) ); }
		$like = '%' . $wpdb->esc_like( $search ) . '%';
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE name LIKE %s OR code LIKE %s ORDER BY name LIMIT 20', Items::table(), $like, $like ), ARRAY_A ) ?: array();
		return rest_ensure_response(
			array_map(
				static function ( $row ) {
					return array(
						'id'        => (int) $row['id'],
						'title'     => $row['name'],
						'item_type' => $row['item_type'],
						'code'      => $row['code'],
						'version'   => $row['version'],
						'path'      => Items::path_names( (int) $row['id'], false ),
					);
				},
				$rows
			)
		);
	}
}
