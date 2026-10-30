<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Levels REST controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Engagement\Rest;

use OhMyLMS\Engagement\GamificationRegistry;
use OhMyLMS\Engagement\GamificationStore;
use WP_Error;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * `ohmylms/v1/gamification/levels`: a ladder of point thresholds.
 *
 * Levels are always listed from the lowest threshold up and no two levels may share a threshold,
 * so the ladder is strictly increasing by construction.
 *
 * @since 1.0.0
 */
class LevelsController extends GamificationController {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'gamification/levels';

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$slug = '(?P<slug>[a-z0-9]+(?:-[a-z0-9]+)*)';
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/' . $slug,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * List levels, lowest threshold first.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$counts = $this->earned_counts( 'level' );
		$items  = array();
		foreach ( GamificationStore::levels() as $level ) {
			$level['earned_count'] = isset( $counts[ $level['slug'] ] ) ? $counts[ $level['slug'] ] : 0;
			$items[]               = $this->prepare_item_for_response( $level, $request )->get_data();
		}
		return $this->paginate( $items, $request, $this->rest_base );
	}

	/**
	 * Read one level.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		foreach ( GamificationStore::levels() as $level ) {
			if ( $level['slug'] === $request['slug'] ) {
				return $this->with_count( $level, $request );
			}
		}
		return new WP_Error( 'ohmylms_level_not_found', __( 'Level not found.', 'ohmylms' ), array( 'status' => 404 ) );
	}

	/**
	 * Create a level.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$level = GamificationStore::save_level( $request->get_json_params() ? $request->get_json_params() : $request->get_params() );
		if ( is_wp_error( $level ) ) {
			return $level;
		}
		$response = $this->with_count( $level, $request );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( $this->namespace . '/' . $this->rest_base . '/' . $level['slug'] ) );
		return $response;
	}

	/**
	 * Update a level.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$level = GamificationStore::save_level( $request->get_json_params() ? $request->get_json_params() : $request->get_params(), $request['slug'] );
		return is_wp_error( $level ) ? $level : $this->with_count( $level, $request );
	}

	/**
	 * Delete a level. Awards already given stay in the log.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$level = GamificationStore::delete_level( $request['slug'] );
		if ( is_wp_error( $level ) ) {
			return $level;
		}
		return rest_ensure_response(
			array(
				'deleted'  => true,
				'previous' => $this->prepare_item_for_response( $level, $request )->get_data(),
			)
		);
	}

	/**
	 * Prepare a level with its learner count.
	 *
	 * @param array            $level   Level.
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	private function with_count( array $level, $request ) {
		$counts                = $this->earned_counts( 'level' );
		$level['earned_count'] = isset( $counts[ $level['slug'] ] ) ? $counts[ $level['slug'] ] : 0;
		return $this->prepare_item_for_response( $level, $request );
	}

	/**
	 * Shape a level for output.
	 *
	 * @param array            $item    Level.
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		unset( $request );
		return rest_ensure_response(
			array(
				'slug'         => $item['slug'],
				'name'         => $item['name'],
				'description'  => $item['description'],
				'color'        => $item['color'],
				'text_color'   => $item['text_color'],
				'points'       => $item['points'],
				'earned_count' => isset( $item['earned_count'] ) ? $item['earned_count'] : 0,
			)
		);
	}

	/**
	 * Item schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}
		$color        = array(
			'type'    => 'string',
			'pattern' => '^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$',
		);
		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'gamification-level',
			'type'       => 'object',
			'properties' => array(
				'slug'         => array(
					'description' => __( 'Stable identifier made from the name when the level is created.', 'ohmylms' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'name'         => array(
					'type'      => 'string',
					'minLength' => 1,
					'maxLength' => 60,
					'required'  => true,
				),
				'description'  => array(
					'type'      => 'string',
					'maxLength' => 200,
				),
				'color'        => array_merge( $color, array( 'description' => __( 'Background colour.', 'ohmylms' ) ) ),
				'text_color'   => array_merge( $color, array( 'description' => __( 'Text colour.', 'ohmylms' ) ) ),
				'points'       => array(
					'description' => __( 'Points a learner needs to reach this level. Unique across levels.', 'ohmylms' ),
					'type'        => 'integer',
					'minimum'     => 0,
					'maximum'     => GamificationRegistry::MAX_CRITERION_VALUE,
					'required'    => true,
				),
				'earned_count' => array(
					'description' => __( 'Learners who have reached this level.', 'ohmylms' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
			),
		);
		return $this->add_additional_fields_schema( $this->schema );
	}
}
