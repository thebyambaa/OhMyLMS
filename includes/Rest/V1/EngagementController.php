<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * EngagementController class.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Engagement\Achievements;
use OhMyLMS\Engagement\GamificationRegistry;
use OhMyLMS\Engagement\GamificationStore;
use OhMyLMS\Engagement\Point;
use OhMyLMS\Engagement\Rest\BadgesController;
use OhMyLMS\Engagement\Rest\GamificationController;
use OhMyLMS\Engagement\Rest\LevelsController;
use OhMyLMS\Engagement\StreakSettings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

/**
 * REST API for gamification under `ohmylms/v1/gamification`.
 *
 * This controller owns the section settings (points, levels, badges, leaderboard and rewards),
 * the options route and manual point adjustments; levels and badges are collections handled by
 * {@see LevelsController} and {@see BadgesController}. Definitions, schema and defaults come from
 * {@see GamificationRegistry}; storage and validation from {@see GamificationStore}.
 *
 * The streak, XP, score and mastery settings keep their routes under `engagement/settings/<group>`
 * until they are rebuilt. Every route requires CAPABILITY. See docs/api/gamification.md.
 *
 * @since 1.0.0
 */
class EngagementController extends GamificationController {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'gamification';

	/**
	 * Settings groups that still live under `engagement/settings/<group>`.
	 *
	 * @var string[]
	 */
	const LEGACY_GROUPS = array( 'streak', 'xp', 'score', 'mastery' );

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$base = '/' . $this->rest_base;

		register_rest_route(
			$this->namespace,
			$base . '/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);
		foreach ( GamificationRegistry::sections() as $section ) {
			$schema = GamificationRegistry::schema( $section );
			$args   = array();
			foreach ( $schema['properties'] as $name => $property ) {
				if ( empty( $property['readonly'] ) ) {
					$args[ $name ] = array_merge(
						$property,
						array(
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => 'rest_sanitize_request_arg',
						)
					);
				}
			}
			register_rest_route(
				$this->namespace,
				$base . '/settings/' . $section,
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => function () use ( $section ) {
							return rest_ensure_response( GamificationStore::for_response( $section ) );
						},
						'permission_callback' => array( $this, 'get_item_permissions_check' ),
					),
					array(
						'methods'             => WP_REST_Server::EDITABLE,
						'callback'            => function ( $request ) use ( $section ) {
							return $this->update_section( $section, $request );
						},
						'permission_callback' => array( $this, 'update_item_permissions_check' ),
						'args'                => $args,
					),
					'schema' => static function () use ( $section ) {
						return GamificationRegistry::schema( $section );
					},
				)
			);
		}
		register_rest_route(
			$this->namespace,
			$base . '/options',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_options' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/points/adjustments',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'adjust_points' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => array(
						'user_id'   => array(
							'description' => __( 'Learner user id.', 'ohmylms' ),
							'type'        => 'integer',
							'minimum'     => 1,
							'required'    => true,
						),
						'points'    => array(
							'description' => __( 'Whole number to add (positive) or remove (negative); not zero.', 'ohmylms' ),
							'type'        => 'integer',
							'minimum'     => -GamificationRegistry::MAX_POINTS,
							'maximum'     => GamificationRegistry::MAX_POINTS,
							'required'    => true,
						),
						'reference' => array(
							'description' => __( 'Idempotency key. Repeating a request with the same reference changes nothing.', 'ohmylms' ),
							'type'        => 'string',
							'pattern'     => '^[A-Za-z0-9_.-]{1,60}$',
							'required'    => true,
						),
						'note'      => array(
							'description'       => __( 'Why the points were changed.', 'ohmylms' ),
							'type'              => 'string',
							'maxLength'         => 200,
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		( new LevelsController() )->register_routes();
		( new BadgesController() )->register_routes();

		register_rest_route(
			$this->namespace,
			'/engagement/settings/(?P<group_id>' . implode( '|', self::LEGACY_GROUPS ) . ')',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_legacy_settings' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_legacy_settings' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
			)
		);
	}

	/**
	 * All sections in one response.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) {
		unset( $request );
		$all = array( 'integration_enabled' => GamificationStore::integration_enabled() );
		foreach ( GamificationRegistry::sections() as $section ) {
			$all[ $section ] = GamificationStore::for_response( $section );
		}
		return rest_ensure_response( $all );
	}

	/**
	 * Update a section. Only the fields sent change.
	 *
	 * @param string          $section Section id.
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	private function update_section( $section, $request ) {
		$body    = $request->get_json_params();
		$body    = is_array( $body ) ? $body : $request->get_params();
		$known   = GamificationRegistry::schema( $section )['properties'];
		$unknown = array_diff_key( $body, $known );
		if ( $unknown ) {
			return new WP_Error(
				'rest_invalid_param',
				/* translators: %s: field names. */
				sprintf( __( 'Unknown settings: %s.', 'ohmylms' ), implode( ', ', array_map( 'sanitize_key', array_keys( $unknown ) ) ) ),
				array( 'status' => 400 )
			);
		}
		$data = $body;
		$next = GamificationStore::prepare( $section, $data );
		if ( is_wp_error( $next ) ) {
			return $next;
		}
		if ( ! GamificationStore::save( $section, $next ) ) {
			return new WP_Error( 'ohmylms_gamification_save_failed', __( 'Could not save the settings.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		if ( 'points' === $section ) {
			/**
			 * Fires after the point rules were saved.
			 *
			 * @param array $settings The saved points section.
			 */
			do_action( 'ohmylms_point_settings_updated', GamificationStore::get( 'points' ) );
		}
		return rest_ensure_response( GamificationStore::for_response( $section ) );
	}

	/**
	 * Allow-lists and limits the editor needs.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_options( $request ) {
		unset( $request );
		$list      = static function ( array $labels ) {
			$items = array();
			foreach ( $labels as $value => $label ) {
				$items[] = array(
					'value' => $value,
					'label' => $label,
				);
			}
			return $items;
		};
		$operators = array();
		foreach ( GamificationRegistry::operators() as $operator ) {
			$operators[] = array(
				'value' => $operator,
				'label' => $operator,
			);
		}
		return rest_ensure_response(
			array(
				'integration_enabled' => GamificationStore::integration_enabled(),
				'metrics'             => $list( GamificationRegistry::metrics() ),
				'operators'           => $operators,
				'leaderboard_modes'   => $list( GamificationRegistry::leaderboard_modes() ),
				'image_mime_types'    => GamificationStore::image_mime_types(),
				'limits'              => array(
					'max_points'          => GamificationRegistry::MAX_POINTS,
					'max_criterion_value' => GamificationRegistry::MAX_CRITERION_VALUE,
					'max_levels'          => GamificationRegistry::MAX_LEVELS,
					'max_badges'          => GamificationRegistry::MAX_BADGES,
					'max_criteria'        => GamificationRegistry::MAX_CRITERIA,
				),
			)
		);
	}

	/**
	 * Add or remove points by hand, once per reference.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function adjust_points( $request ) {
		$user_id = (int) $request['user_id'];
		$points  = (int) $request['points'];
		if ( 0 === $points ) {
			return GamificationStore::error( 'ohmylms_points_zero', __( 'The number of points cannot be zero.', 'ohmylms' ) );
		}
		if ( ! get_userdata( $user_id ) ) {
			return new WP_Error( 'ohmylms_gamification_user_not_found', __( 'User not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$result = Achievements::adjust_points( $user_id, $points, $request['reference'], $request['note'], get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$response = rest_ensure_response(
			array(
				'user_id'   => $user_id,
				'points'    => $points,
				'reference' => $request['reference'],
				'created'   => $result['created'],
				'balance'   => $result['balance'],
			)
		);
		$response->set_status( $result['created'] ? 201 : 200 );
		return $response;
	}

	/**
	 * Read a streak, XP, score or mastery group.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_legacy_settings( $request ) {
		switch ( $request['group_id'] ) {
			case 'streak':
				return rest_ensure_response( StreakSettings::get() );
			case 'xp':
				return rest_ensure_response( \OhMyLMS\Engagement\XpSettings::get() );
			case 'score':
				return rest_ensure_response( \OhMyLMS\Skills\Score::get() );
		}
		return rest_ensure_response( \OhMyLMS\Performance\Settings::payload() );
	}

	/**
	 * Validate and save a streak, XP, score or mastery group.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function save_legacy_settings( $request ) {
		$group    = $request['group_id'];
		$settings = $request->get_json_params();
		if ( 'mastery' === $group ) {
			$saved = \OhMyLMS\Performance\Settings::validate( $settings );
			$saved = is_wp_error( $saved ) ? $saved : \OhMyLMS\Performance\Settings::save( $saved );
			return is_wp_error( $saved ) ? $saved : rest_ensure_response( array( 'success' => true ) );
		}
		if ( 'xp' === $group ) {
			$settings = \OhMyLMS\Engagement\XpSettings::validate( $settings );
		} elseif ( 'score' === $group ) {
			$settings = \OhMyLMS\Skills\Score::validate( $settings );
		} else {
			$settings = StreakSettings::validate( $settings );
			if ( ! is_wp_error( $settings ) ) {
				$badges = array_column( \OhMyLMS\Engagement\Badge::get_badges( true ), 'slug' );
				foreach ( $settings['milestones'] as $reward ) {
					if ( $reward['badge'] && ! in_array( $reward['badge'], $badges, true ) ) {
						return new WP_Error( 'streak_badge', __( 'Choose an existing milestone badge.', 'ohmylms' ), array( 'status' => 400 ) );
					}
				}
			}
		}
		if ( is_wp_error( $settings ) ) {
			return $settings;
		}
		if ( 'streak' === $group && $settings['enable'] && ! \OhMyLMS\Engagement\StreakSchema::install() ) {
			return new WP_Error( 'streak_schema', __( 'Could not install streak storage.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		if ( in_array( $group, array( 'xp', 'score' ), true ) && $settings['enable'] && ! \OhMyLMS\Engagement\XpSchema::install() ) {
			return new WP_Error( 'xp_schema', __( 'Could not install XP and mastery score storage.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		update_option( 'ohmylms_' . $group . '_settings', $settings );
		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Settings saved successfully.', 'ohmylms' ),
			)
		);
	}
}
