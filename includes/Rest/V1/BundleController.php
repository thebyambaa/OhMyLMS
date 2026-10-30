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
		$admin    = array( Access::class, 'admin' );
		$expected = array(
			'type'      => 'string',
			'maxLength' => 32,
		);
		$fields   = array(
			'title'       => array(
				'type'      => 'string',
				'minLength' => 1,
				'maxLength' => 190,
			),
			'description' => array(
				'type'      => 'string',
				'maxLength' => 2000,
			),
		);
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
					'args'                => array( 'title' => $fields['title'] + array( 'required' => true ) ) + $fields,
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
					'args'                => array(
						'type'   => array(
							'type'     => 'string',
							'enum'     => array( 'course', 'curriculum' ),
							'required' => true,
						),
						'search' => array(
							'type'      => 'string',
							'maxLength' => 200,
							'default'   => '',
						),
					),
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
					'args'                => $fields + array( 'expected_updated_at' => $expected ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => $admin,
					'args'                => array(
						'force'               => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'expected_updated_at' => $expected,
					),
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
					'args'                => array(
						'items'               => array(
							'type'     => 'array',
							'required' => true,
							'maxItems' => Bundles::MAX_MEMBERS,
							'items'    => array(
								'type'       => 'object',
								'required'   => array( 'type', 'id' ),
								'properties' => array(
									'type' => array(
										'type' => 'string',
										'enum' => Bundles::MEMBER_TYPES,
									),
									'id'   => array(
										'type'    => 'integer',
										'minimum' => 1,
									),
								),
							),
						),
						'expected_updated_at' => $expected,
					),
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
					'args'                => array(
						'published'           => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'expected_updated_at' => $expected,
					),
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
	 * Run a bundle write under the curriculum lock, refusing it when the bundle changed since it was read.
	 *
	 * @param WP_REST_Request $request Request with id and optional expected_updated_at.
	 * @param callable        $work Write returning a bundle row, a result array or a WP_Error.
	 * @return mixed
	 */
	private function guarded( WP_REST_Request $request, callable $work ) {
		$id       = (int) $request['id'];
		$expected = $request->get_param( 'expected_updated_at' );
		return Items::exclusive(
			static function () use ( $id, $expected, $work ) {
				$bundle = Bundles::get( $id );
				if ( ! $bundle ) {
					return Bundles::missing();
				}
				if ( null !== $expected && '' !== $expected && (string) $expected !== $bundle['updated_at'] ) {
					return Access::error( 'ohmylms_bundle_stale', __( 'Someone else changed this bundle. Reload and try again.', 'ohmylms' ), 409 );
				}
				return $work( $id );
			}
		);
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
		$bundle = $this->guarded(
			$request,
			static function ( $id ) use ( $params ) {
				return Bundles::update( $id, array_intersect_key( $params, array_flip( array( 'title', 'description' ) ) ) );
			}
		);
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
		$bundle = $this->guarded(
			$request,
			static function ( $id ) use ( $items ) {
				return Bundles::set_members( $id, $items );
			}
		);
		return is_wp_error( $bundle ) ? $bundle : rest_ensure_response( $this->detail( (int) $bundle['id'] ) );
	}

	/**
	 * Publish for Bundle controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function publish( WP_REST_Request $request ) {
		$published = rest_sanitize_boolean( $request->get_param( 'published' ) );
		$bundle    = $this->guarded(
			$request,
			static function ( $id ) use ( $published ) {
				return Bundles::set_status( $id, $published );
			}
		);
		return is_wp_error( $bundle ) ? $bundle : rest_ensure_response( $this->detail( (int) $bundle['id'] ) );
	}

	/**
	 * Delete for Bundle controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function delete( WP_REST_Request $request ) {
		$force  = rest_sanitize_boolean( $request->get_param( 'force' ) );
		$result = $this->guarded(
			$request,
			static function ( $id ) use ( $force ) {
				return Bundles::delete( $id, $force );
			}
		);
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
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE name LIKE %s OR code LIKE %s ORDER BY name LIMIT 20', Items::table(), $like, $like ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bounded search of the plugin's curriculum table; results change with every edit.
		$rows = is_array( $rows ) ? $rows : array();
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
