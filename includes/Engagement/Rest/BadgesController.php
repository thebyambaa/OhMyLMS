<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Badges REST controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Engagement\Rest;

use OhMyLMS\Engagement\Achievements;
use OhMyLMS\Engagement\Badge;
use OhMyLMS\Engagement\GamificationRegistry;
use OhMyLMS\Engagement\GamificationStore;
use OhMyLMS\Engagement\StreakSettings;
use WP_Error;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * `ohmylms/v1/gamification/badges`: custom badges, plus manual award and revoke.
 *
 * Images are attachment ids that must be JPEG, PNG, GIF or WebP files in the media library;
 * the URL in responses is derived from the attachment and never accepted as input.
 *
 * @since 1.0.0
 */
class BadgesController extends GamificationController {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'gamification/badges';

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$slug  = '(?P<slug>[a-z0-9]+(?:-[a-z0-9]+)*)';
		$param = array(
			'user_id' => array(
				'description' => __( 'Learner user id.', 'ohmylms' ),
				'type'        => 'integer',
				'minimum'     => 1,
			),
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array_merge(
						$this->get_collection_params(),
						array(
							'source' => array(
								'description'       => __( 'Which badges to list: custom ones, the system streak badges, or both.', 'ohmylms' ),
								'type'              => 'string',
								'enum'              => array( 'custom', 'streak', 'all' ),
								'default'           => 'custom',
								'validate_callback' => 'rest_validate_request_arg',
							),
						)
					),
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
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/' . $slug . '/awards',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'award' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => array_merge( $param, array( 'user_id' => array_merge( $param['user_id'], array( 'required' => true ) ) ) ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/' . $slug . '/awards/(?P<user_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'revoke' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Find a badge (custom or system) by slug.
	 *
	 * @param string $slug Slug.
	 * @return array|null
	 */
	private function find( $slug ) {
		foreach ( Badge::get_badges( true ) as $badge ) {
			if ( isset( $badge['slug'] ) && $badge['slug'] === $slug ) {
				return $badge;
			}
		}
		return null;
	}

	/**
	 * List badges.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$source = $request['source'];
		$badges = array();
		foreach ( Badge::get_badges( 'custom' !== $source ) as $badge ) {
			$is_streak = $this->is_system( $badge['slug'] );
			if ( ( 'custom' === $source && $is_streak ) || ( 'streak' === $source && ! $is_streak ) ) {
				continue;
			}
			$badges[] = $badge;
		}
		$counts = $this->earned_counts( 'badge' );
		$items  = array();
		foreach ( $badges as $badge ) {
			$badge['earned_count'] = isset( $counts[ $badge['slug'] ] ) ? $counts[ $badge['slug'] ] : 0;
			$items[]               = $this->prepare_item_for_response( $badge, $request )->get_data();
		}
		return $this->paginate( $items, $request, $this->rest_base );
	}

	/**
	 * Whether a slug belongs to a system streak badge.
	 *
	 * @param string $slug Badge slug.
	 * @return bool
	 */
	private function is_system( $slug ) {
		return in_array( $slug, array_column( StreakSettings::badges(), 'slug' ), true );
	}

	/**
	 * Read one badge.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$badge = $this->find( $request['slug'] );
		return $badge ? $this->with_count( $badge, $request ) : $this->not_found();
	}

	/**
	 * Create a badge.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$saved = GamificationStore::save_badge( $this->body( $request ) );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		$response = $this->with_count( $this->find( $saved['slug'] ), $request );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( $this->namespace . '/' . $this->rest_base . '/' . $saved['slug'] ) );
		return $response;
	}

	/**
	 * Update a badge. System streak badges cannot be changed.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		if ( $this->is_system( $request['slug'] ) ) {
			return new WP_Error( 'ohmylms_badge_system', __( 'System badges cannot be changed.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		$saved = GamificationStore::save_badge( $this->body( $request ), $request['slug'] );
		return is_wp_error( $saved ) ? $saved : $this->with_count( $this->find( $saved['slug'] ), $request );
	}

	/**
	 * Delete a badge. Awards already given stay in the log but are no longer shown.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		if ( $this->is_system( $request['slug'] ) ) {
			return new WP_Error( 'ohmylms_badge_system', __( 'System badges cannot be deleted.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		$previous = $this->find( $request['slug'] );
		$deleted  = GamificationStore::delete_badge( $request['slug'] );
		if ( is_wp_error( $deleted ) ) {
			return $deleted;
		}
		return rest_ensure_response(
			array(
				'deleted'  => true,
				'previous' => $this->prepare_item_for_response( $previous, $request )->get_data(),
			)
		);
	}

	/**
	 * Give a badge to a learner. Repeating the request changes nothing and answers 200.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function award( $request ) {
		$badge   = $this->find( $request['slug'] );
		$user_id = (int) $request['user_id'];
		if ( ! $badge ) {
			return $this->not_found();
		}
		if ( ! get_userdata( $user_id ) ) {
			return new WP_Error( 'ohmylms_gamification_user_not_found', __( 'User not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$awarded = Achievements::award_badge_manually( $user_id, $badge['slug'], get_current_user_id() );
		if ( null === $awarded ) {
			return new WP_Error( 'ohmylms_badge_award_failed', __( 'Could not award the badge.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		$response = rest_ensure_response(
			array(
				'badge'   => $badge['slug'],
				'user_id' => $user_id,
				'awarded' => $awarded,
			)
		);
		$response->set_status( $awarded ? 201 : 200 );
		return $response;
	}

	/**
	 * Take a badge back. Revoking a badge the learner does not hold answers 200 with `revoked` 0.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function revoke( $request ) {
		$badge   = $this->find( $request['slug'] );
		$user_id = (int) $request['user_id'];
		if ( ! $badge ) {
			return $this->not_found();
		}
		return rest_ensure_response(
			array(
				'badge'   => $badge['slug'],
				'user_id' => $user_id,
				'revoked' => Achievements::revoke_badge( $user_id, $badge['slug'], get_current_user_id() ),
			)
		);
	}

	/**
	 * Request body as an array.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array
	 */
	private function body( $request ) {
		$body = $request->get_json_params();
		return is_array( $body ) ? $body : $request->get_params();
	}

	/**
	 * Not-found error.
	 *
	 * @return WP_Error
	 */
	private function not_found() {
		return new WP_Error( 'ohmylms_badge_not_found', __( 'Badge not found.', 'ohmylms' ), array( 'status' => 404 ) );
	}

	/**
	 * Prepare a badge with its learner count.
	 *
	 * @param array            $badge   Badge.
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	private function with_count( array $badge, $request ) {
		$counts                = $this->earned_counts( 'badge' );
		$badge['earned_count'] = isset( $counts[ $badge['slug'] ] ) ? $counts[ $badge['slug'] ] : 0;
		return $this->prepare_item_for_response( $badge, $request );
	}

	/**
	 * Shape a badge for output.
	 *
	 * @param array            $item    Badge.
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		unset( $request );
		$system = $this->is_system( $item['slug'] );
		return rest_ensure_response(
			array(
				'slug'         => $item['slug'],
				'name'         => $item['name'],
				'description'  => $item['description'],
				'image_id'     => $system ? 0 : (int) ( isset( $item['image_id'] ) ? $item['image_id'] : 0 ),
				'image_url'    => ! empty( $item['image'] ) ? esc_url_raw( $item['image'] ) : null,
				'color'        => isset( $item['color'] ) ? $item['color'] : '#6e42d3',
				'award_source' => $system ? 'streak' : $item['award_source'],
				'criteria'     => isset( $item['criteria'] ) ? $item['criteria'] : array(),
				'source'       => $system ? 'streak' : 'custom',
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
		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'gamification-badge',
			'type'       => 'object',
			'properties' => array(
				'slug'         => array(
					'description' => __( 'Stable identifier made from the name when the badge is created.', 'ohmylms' ),
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
				'image_id'     => array(
					'description' => __( 'Media library attachment id of the badge image (0 or null for none).', 'ohmylms' ),
					'type'        => array( 'integer', 'null' ),
					'minimum'     => 0,
				),
				'image_url'    => array(
					'description' => __( 'URL of the image, derived from image_id.', 'ohmylms' ),
					'type'        => array( 'string', 'null' ),
					'format'      => 'uri',
					'readonly'    => true,
				),
				'color'        => array(
					'type'    => 'string',
					'pattern' => '^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$',
				),
				'award_source' => array(
					'description' => __( 'criteria: awarded when all criteria are met. streak: awarded by a learning streak milestone.', 'ohmylms' ),
					'type'        => 'string',
					'enum'        => array( 'criteria', 'streak' ),
				),
				'criteria'     => array(
					'description' => __( 'Conditions that must all be met. Required for criteria badges, empty for streak badges.', 'ohmylms' ),
					'type'        => 'array',
					'maxItems'    => GamificationRegistry::MAX_CRITERIA,
					'items'       => GamificationRegistry::criterion_schema(),
				),
				'source'       => array(
					'type'     => 'string',
					'enum'     => array( 'custom', 'streak' ),
					'readonly' => true,
				),
				'earned_count' => array(
					'description' => __( 'Learners who hold this badge.', 'ohmylms' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
			),
		);
		return $this->add_additional_fields_schema( $this->schema );
	}
}
