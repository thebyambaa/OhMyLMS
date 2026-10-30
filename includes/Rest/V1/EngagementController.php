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
use OhMyLMS\Utility\Transaction;
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
 * The streak, XP, score and mastery settings keep their routes under `engagement/settings/<group>`,
 * now with a schema per group and partial updates (see group_schema()). Every route requires
 * CAPABILITY. See docs/api/gamification.md.
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
			$args['revision'] = array(
				'description' => __( 'The revision the editor loaded; a different stored revision answers 409.', 'ohmylms' ),
				'type'        => 'string',
				'pattern'     => '^[a-f0-9]{16}$',
			);
			register_rest_route(
				$this->namespace,
				$base . '/settings/' . $section,
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => function () use ( $section ) {
							return rest_ensure_response( $this->section_response( $section ) );
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

		foreach ( self::LEGACY_GROUPS as $group ) {
			$args = array();
			foreach ( $this->group_schema( $group )['properties'] as $name => $property ) {
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
				'/engagement/settings/(?P<group_id>' . $group . ')',
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
						'args'                => $args,
					),
					'schema' => function () use ( $group ) {
						return $this->group_schema( $group );
					},
				)
			);
		}
	}

	/**
	 * JSON schema (draft 4) of a streak, XP, score or mastery group. The ranges are the ones the
	 * group's own validate() enforces; read-only properties are what GET adds for the editor.
	 *
	 * @param string $group Group id from LEGACY_GROUPS.
	 * @return array
	 */
	public function group_schema( $group ) {
		$flag    = static function ( $description ) {
			return array(
				'description' => $description,
				'type'        => 'boolean',
			);
		};
		$integer = static function ( $description, $minimum, $maximum ) {
			return array(
				'description' => $description,
				'type'        => 'integer',
				'minimum'     => $minimum,
				'maximum'     => $maximum,
			);
		};
		$number  = static function ( $description, $minimum, $maximum ) {
			return array(
				'description' => $description,
				'type'        => 'number',
				'minimum'     => $minimum,
				'maximum'     => $maximum,
			);
		};

		if ( 'streak' === $group ) {
			$properties = array(
				'enable'           => $flag( __( 'Record daily learning streaks.', 'ohmylms' ) ),
				'lesson'           => $flag( __( 'A completed lesson counts as a learning day.', 'ohmylms' ) ),
				'quiz'             => $flag( __( 'A finished quiz counts as a learning day.', 'ohmylms' ) ),
				'practice'         => $flag( __( 'A practice session counts as a learning day.', 'ohmylms' ) ),
				'practice_minimum' => $integer( __( 'Answers a practice session needs to count.', 'ohmylms' ), 1, 30 ),
				'freezes'          => $flag( __( 'Freezes protect a missed day.', 'ohmylms' ) ),
				'initial_freezes'  => $integer( __( 'Freezes a new learner starts with (at most maximum_freezes).', 'ohmylms' ), 0, 10 ),
				'maximum_freezes'  => $integer( __( 'Most freezes a learner can hold.', 'ohmylms' ), 0, 10 ),
				'refill_days'      => $integer( __( 'Learning days that earn one freeze back.', 'ohmylms' ), 1, 365 ),
				'milestones'       => array(
					'description' => __( 'Streak lengths that award a badge and points. Days are unique.', 'ohmylms' ),
					'type'        => 'array',
					'maxItems'    => 20,
					'items'       => array(
						'type'                 => 'object',
						'required'             => array( 'days', 'badge', 'points' ),
						'additionalProperties' => false,
						'properties'           => array(
							'days'   => $integer( __( 'Streak length in days.', 'ohmylms' ), 1, 10000 ),
							'badge'  => array(
								'description' => __( 'Slug of an existing badge, or empty for none.', 'ohmylms' ),
								'type'        => 'string',
								'maxLength'   => 45,
								'pattern'     => '^[a-z0-9_-]*$',
							),
							'points' => $integer( __( 'Bonus points.', 'ohmylms' ), 0, 100000 ),
						),
					),
				),
			);
		} elseif ( 'xp' === $group ) {
			$properties = array(
				'enable'       => $flag( __( 'Award XP.', 'ohmylms' ) ),
				'lesson'       => $integer( __( 'XP for a completed practice lesson.', 'ohmylms' ), 0, 1000 ),
				'perfect'      => $integer( __( 'Bonus XP for a lesson without mistakes.', 'ohmylms' ), 0, 1000 ),
				'challenge'    => $integer( __( 'XP per first-try answer in the challenge zone.', 'ohmylms' ), 0, 100 ),
				'min_answered' => $integer( __( 'Answers a lesson needs to earn XP.', 'ohmylms' ), 1, 30 ),
				'goals'        => array(
					'description' => __( 'Daily goal choices in XP (duplicates are removed, the list is sorted).', 'ohmylms' ),
					'type'        => 'array',
					'minItems'    => 1,
					'maxItems'    => 6,
					'items'       => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 10000,
					),
				),
				'default_goal' => $integer( __( 'Default daily goal; must be one of the goals.', 'ohmylms' ), 1, 10000 ),
				'goal_bonus'   => $integer( __( 'Bonus points for reaching the daily goal.', 'ohmylms' ), 0, 1000 ),
				'daily_cap'    => $integer( __( 'Most XP a learner can earn per day.', 'ohmylms' ), 10, 100000 ),
			);
		} elseif ( 'score' === $group ) {
			$properties = array(
				'enable'           => $flag( __( 'Compute the 0-100 mastery score.', 'ohmylms' ) ),
				'bands'            => array(
					'description' => __( 'Gain per correct answer and loss per mistake in the bands 0-69, 70-79, 80-89 and 90-100.', 'ohmylms' ),
					'type'        => 'array',
					'minItems'    => 4,
					'maxItems'    => 4,
					'items'       => array(
						'type'                 => 'object',
						'required'             => array( 'gain', 'loss' ),
						'additionalProperties' => false,
						'properties'           => array(
							'gain' => $number( __( 'Gain per correct answer.', 'ohmylms' ), 0, 50 ),
							'loss' => $number( __( 'Loss per mistake.', 'ohmylms' ), 0, 50 ),
						),
					),
				),
				'hint_gain'        => $number( __( 'Share of the gain kept after a hint.', 'ohmylms' ), 0, 1 ),
				'unlock'           => $integer( __( 'Score that unlocks the next skill.', 'ohmylms' ), 50, 100 ),
				'decay_grace_days' => $integer( __( 'Idle days before the score fades.', 'ohmylms' ), 0, 365 ),
				'decay_per_week'   => $number( __( 'Points lost per idle week (never below 90).', 'ohmylms' ), 0, 20 ),
			);
		} else {
			$properties = array(
				'policy'          => array(
					'description' => __( 'How skill levels are decided.', 'ohmylms' ),
					'type'        => 'string',
					'enum'        => array_map( 'strval', array_keys( \OhMyLMS\Performance\Settings::policies() ) ),
				),
				'surfaces'        => array(
					'description'          => __( 'Activity => whether its answers count toward skills. Unknown activities are dropped.', 'ohmylms' ),
					'type'                 => 'object',
					'additionalProperties' => array( 'type' => 'boolean' ),
				),
				'show_score'      => $flag( __( 'Show the mastery score beside the level.', 'ohmylms' ) ),
				'ai_insights'     => $flag( __( 'Let learners and teachers ask for AI study notes.', 'ohmylms' ) ),
				'policies'        => array(
					'description' => __( 'Policies to choose from.', 'ohmylms' ),
					'type'        => 'array',
					'readonly'    => true,
				),
				'surface_options' => array(
					'description' => __( 'Activities and whether they count by default.', 'ohmylms' ),
					'type'        => 'array',
					'readonly'    => true,
				),
				'ai_ready'        => array(
					'description' => __( 'Whether an AI provider is connected.', 'ohmylms' ),
					'type'        => 'boolean',
					'readonly'    => true,
				),
			);
		}

		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'engagement-' . $group . '-settings',
			'type'       => 'object',
			'properties' => $properties,
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
	 * Stale-save revision of a stored section option (16 hex characters).
	 *
	 * @param mixed $stored Unserialized option value, or null when the option does not exist.
	 * @return string
	 */
	private function section_revision( $stored ) {
		return substr( hash( 'sha256', (string) wp_json_encode( $stored ) ), 0, 16 );
	}

	/**
	 * A section as GET and a save answer it: the section plus its `revision`.
	 *
	 * @param string $section Section id.
	 * @return array
	 */
	private function section_response( $section ) {
		$data             = GamificationStore::for_response( $section );
		$data['revision'] = $this->section_revision( get_option( GamificationRegistry::option( $section ), null ) );
		return $data;
	}

	/**
	 * Update a section. Only the fields sent change. With `revision`, the option row is locked and a
	 * different stored revision answers 409 without writing.
	 *
	 * @param string          $section Section id.
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	private function update_section( $section, $request ) {
		$body    = $request->get_json_params();
		$body    = is_array( $body ) ? $body : $request->get_params();
		$known   = GamificationRegistry::schema( $section )['properties'] + array( 'revision' => true );
		$unknown = array_diff_key( $body, $known );
		if ( $unknown ) {
			return new WP_Error(
				'rest_invalid_param',
				/* translators: %s: field names. */
				sprintf( __( 'Unknown settings: %s.', 'ohmylms' ), implode( ', ', array_map( 'sanitize_key', array_keys( $unknown ) ) ) ),
				array( 'status' => 400 )
			);
		}
		$revision = isset( $body['revision'] ) ? (string) $body['revision'] : '';
		unset( $body['revision'] );
		try {
			$result = Transaction::run(
				function () use ( $section, $body, $revision ) {
					global $wpdb;
					$option = GamificationRegistry::option( $section );
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Lock the option row so the revision check and the write are atomic.
					$row = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name=%s FOR UPDATE', $wpdb->options, $option ) );
					if ( '' !== $revision ) {
						$current = $this->section_revision( null === $row ? null : maybe_unserialize( $row ) );
						if ( $current !== $revision ) {
							return new WP_Error(
								'ohmylms_gamification_conflict',
								__( 'These settings were changed by someone else. Reload them before saving.', 'ohmylms' ),
								array(
									'status'   => 409,
									'revision' => $current,
								)
							);
						}
					}
					$next = GamificationStore::prepare( $section, $body );
					if ( is_wp_error( $next ) ) {
						return $next;
					}
					if ( ! GamificationStore::save( $section, $next ) ) {
						throw new \RuntimeException( 'save failed' );
					}
					return true;
				}
			);
		} catch ( \Throwable $failure ) {
			unset( $failure );
			wp_cache_delete( 'alloptions', 'options' );
			wp_cache_delete( GamificationRegistry::option( $section ), 'options' );
			return new WP_Error( 'ohmylms_gamification_save_failed', __( 'Could not save the settings.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( 'points' === $section ) {
			/**
			 * Fires after the point rules were saved.
			 *
			 * @param array $settings The saved points section.
			 */
			do_action( 'ohmylms_point_settings_updated', GamificationStore::get( 'points' ) );
		}
		return rest_ensure_response( $this->section_response( $section ) );
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
		$group      = (string) $request['group_id'];
		$properties = $this->group_schema( $group )['properties'];
		$body       = $request->get_json_params();
		$body       = is_array( $body ) ? $body : (array) $request->get_body_params();
		$unknown    = array_diff_key( $body, $properties );
		if ( $unknown ) {
			return new WP_Error(
				'rest_invalid_param',
				/* translators: %s: field names. */
				sprintf( __( 'Unknown settings: %s.', 'ohmylms' ), implode( ', ', array_map( 'sanitize_key', array_keys( $unknown ) ) ) ),
				array( 'status' => 400 )
			);
		}
		$input = array();
		foreach ( $properties as $name => $property ) {
			if ( empty( $property['readonly'] ) && array_key_exists( $name, $body ) ) {
				$input[ $name ] = $request[ $name ];
			}
		}

		if ( 'mastery' === $group ) {
			$saved = \OhMyLMS\Performance\Settings::validate( $input );
			$saved = is_wp_error( $saved ) ? $saved : \OhMyLMS\Performance\Settings::save( $input );
			return is_wp_error( $saved ) ? $saved : $this->saved_response( $group );
		}

		if ( 'xp' === $group ) {
			$settings = array_replace( \OhMyLMS\Engagement\XpSettings::get(), $input );
			$goals    = array_map( 'intval', (array) $settings['goals'] );
			if ( ! in_array( (int) $settings['default_goal'], $goals, true ) ) {
				return $this->field_error( 'ohmylms_xp_default_goal', __( 'The default goal must be one of the goal choices.', 'ohmylms' ), 'default_goal' );
			}
			$settings = \OhMyLMS\Engagement\XpSettings::validate( $settings );
		} elseif ( 'score' === $group ) {
			$settings = \OhMyLMS\Skills\Score::validate( array_replace( \OhMyLMS\Skills\Score::get(), $input ) );
		} else {
			$settings = array_replace( StreakSettings::get(), $input );
			if ( (int) $settings['initial_freezes'] > (int) $settings['maximum_freezes'] ) {
				return $this->field_error( 'ohmylms_streak_freezes', __( 'A learner cannot start with more freezes than the maximum.', 'ohmylms' ), 'initial_freezes' );
			}
			$days = array_map( 'intval', array_column( (array) $settings['milestones'], 'days' ) );
			if ( count( $days ) !== count( array_unique( $days ) ) ) {
				return $this->field_error( 'ohmylms_streak_milestone_days', __( 'Each milestone needs a different number of days.', 'ohmylms' ), 'milestones' );
			}
			$badges = array_column( \OhMyLMS\Engagement\Badge::get_badges( true ), 'slug' );
			foreach ( (array) $settings['milestones'] as $reward ) {
				if ( '' !== (string) $reward['badge'] && ! in_array( $reward['badge'], $badges, true ) ) {
					return $this->field_error( 'ohmylms_streak_badge', __( 'Choose an existing milestone badge.', 'ohmylms' ), 'milestones' );
				}
			}
			$settings = StreakSettings::validate( $settings );
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
		return $this->saved_response( $group );
	}

	/**
	 * A 422 error about one field.
	 *
	 * @param string $code    Error code.
	 * @param string $message Message.
	 * @param string $field   Field name.
	 * @return WP_Error
	 */
	private function field_error( $code, $message, $field ) {
		return new WP_Error(
			$code,
			$message,
			array(
				'status' => 422,
				'field'  => $field,
			)
		);
	}

	/**
	 * Answer to a successful save: the legacy `success` and `message` plus the stored group in
	 * the same shape as GET.
	 *
	 * @param string $group Group id.
	 * @return \WP_REST_Response
	 */
	private function saved_response( $group ) {
		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'group_id', $group );
		return rest_ensure_response(
			array(
				'success'  => true,
				'message'  => __( 'Settings saved successfully.', 'ohmylms' ),
				'settings' => $this->get_legacy_settings( $request )->get_data(),
			)
		);
	}
}
