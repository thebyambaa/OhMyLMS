<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * REST routes of the gradebook (`ohmylms/v1/gradebook/*`, `ohmylms/v1/settings/grade-scale`).
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Gradebook\Rest;

use OhMyLMS\Dashboard\Cache;
use OhMyLMS\Dashboard\Directory;
use OhMyLMS\Dashboard\Scope;
use OhMyLMS\Dashboard\ScopeResolver;
use OhMyLMS\Gradebook\Access;
use OhMyLMS\Gradebook\CourseGrid;
use OhMyLMS\Gradebook\Items;
use OhMyLMS\Gradebook\Overrides;
use OhMyLMS\Gradebook\Repository;
use OhMyLMS\Gradebook\SavedViews;
use OhMyLMS\Gradebook\ScaleSettings;
use OhMyLMS\Gradebook\Views;
use OhMyLMS\Gradebook\Weights;
use OhMyLMS\Rest\V1\SettingsController;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Contract: docs/api/gradebook.md. Every read and write resolves `scope` with the dashboard rules and
 * answers `Cache-Control: private, no-store`; reads say `X-OhMyLMS-Cache: hit|miss`.
 */
final class GradebookController {

	/**
	 * Namespace.
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
		$scope  = array( 'scope' => Scope::arg() );
		$id     = static function ( $description ) {
			return array(
				'description' => $description,
				'type'        => 'integer',
				'minimum'     => 1,
			);
		};
		$string = static function ( $description, $max = 200 ) {
			return array(
				'description' => $description,
				'type'        => 'string',
				'maxLength'   => $max,
			);
		};
		$read   = static function ( $callback, array $args = array() ) use ( $scope ) {
			return array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => $callback,
				'permission_callback' => array( Access::class, 'check' ),
				'args'                => $scope + $args,
			);
		};

		register_rest_route( $this->namespace, '/gradebook/context', array( $read( array( $this, 'context' ) ) ) );
		register_rest_route(
			$this->namespace,
			'/gradebook/courses/(?P<course>\d+)',
			array(
				$read(
					array( $this, 'course' ),
					array(
						'audience' => array(
							'description' => __( 'all, individuals or class:<id>.', 'ohmylms' ),
							'type'        => 'string',
							'pattern'     => '^(all|individuals|class:[1-9][0-9]{0,18})$',
							'default'     => 'all',
						),
						'search'   => $string( __( 'Learner name contains.', 'ohmylms' ) ),
						'status'   => array(
							'type' => 'string',
							'enum' => array_merge( array( '' ), CourseGrid::STATUS_FILTERS ),
						),
						'type'     => array(
							'description' => __( 'Item category.', 'ohmylms' ),
							'type'        => 'string',
							'pattern'     => '^([a-z0-9][a-z0-9_-]{0,39})?$',
						),
						'module'   => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'orderby'  => array(
							'type'    => 'string',
							'enum'    => array( 'name', 'total', 'source' ),
							'default' => 'name',
						),
						'order'    => array(
							'type'    => 'string',
							'enum'    => array( 'asc', 'desc' ),
							'default' => 'asc',
						),
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100000,
							'default' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => CourseGrid::MAX_PER_PAGE,
							'default' => 50,
						),
						'export'   => array(
							'type'    => 'boolean',
							'default' => false,
						),
					)
				),
			)
		);
		register_rest_route( $this->namespace, '/gradebook/classes/(?P<class>\d+)', array( $read( array( $this, 'roster' ) ) ) );
		register_rest_route( $this->namespace, '/gradebook/learners', array( $read( array( $this, 'learners' ), array( 'search' => $string( __( 'Learner name contains.', 'ohmylms' ), 100 ) ) ) ) );
		register_rest_route( $this->namespace, '/gradebook/learners/(?P<learner>\d+)', array( $read( array( $this, 'transcript' ) ) ) );
		register_rest_route(
			$this->namespace,
			'/gradebook/mastery',
			array(
				$read(
					array( $this, 'mastery' ),
					array(
						'course'   => array(
							'type'    => 'integer',
							'minimum' => 0,
							'default' => 0,
						),
						'audience' => array(
							'type'    => 'string',
							'pattern' => '^(all|individuals|class:[1-9][0-9]{0,18})$',
							'default' => 'all',
						),
						'search'   => $string( __( 'Learner name contains.', 'ohmylms' ) ),
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100000,
							'default' => 1,
						),
					)
				),
			)
		);
		register_rest_route( $this->namespace, '/gradebook/rollup', array( $read( array( $this, 'rollup' ) ) ) );
		register_rest_route(
			$this->namespace,
			'/gradebook/courses/(?P<course>\d+)/cells/(?P<item>\d+)/(?P<learner>\d+)',
			array(
				$read( array( $this, 'cell' ) ),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'change_cell' ),
					'permission_callback' => array( Access::class, 'check' ),
					'args'                => $scope + array(
						'action'           => array(
							'type'     => 'string',
							'enum'     => Overrides::ACTIONS,
							'required' => true,
						),
						'score'            => array( 'type' => array( 'number', 'null' ) ),
						'reason'           => $string( __( 'Why the grade changes.', 'ohmylms' ), 2000 ),
						'expected_version' => array(
							'description' => __( 'Version of the cell the change is based on (0 when it had no manual change).', 'ohmylms' ),
							'type'        => 'integer',
							'minimum'     => 0,
							'required'    => true,
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/gradebook/courses/(?P<course>\d+)/weights',
			array(
				$read( array( $this, 'weights' ) ),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_weights' ),
					'permission_callback' => array( Access::class, 'check' ),
					'args'                => $scope + array(
						'class_id'         => array(
							'type'    => 'integer',
							'minimum' => 0,
							'default' => 0,
						),
						'weights'          => array(
							'type'     => 'object',
							'required' => true,
						),
						'expected_version' => array(
							'type'     => 'integer',
							'minimum'  => 0,
							'required' => true,
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/gradebook/courses/(?P<course>\d+)/items/(?P<item>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_item' ),
					'permission_callback' => array( Access::class, 'check' ),
					'args'                => $scope + array(
						'category'  => array(
							'type'    => 'string',
							'pattern' => '^([a-z0-9][a-z0-9_-]{0,39})?$',
						),
						'max_marks' => array( 'type' => array( 'number', 'null' ) ),
						'audience'  => array(
							'type'    => 'string',
							'enum'    => Items::AUDIENCES,
							'default' => 'all',
						),
						'class_ids' => array(
							'type'     => 'array',
							'items'    => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
							'maxItems' => 50,
						),
						'due_at'    => array(
							'type'      => array( 'string', 'null' ),
							'maxLength' => 40,
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/gradebook/views',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'views' ),
					'permission_callback' => array( Access::class, 'check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_view' ),
					'permission_callback' => array( Access::class, 'check' ),
					'args'                => array(
						'name'    => array(
							'type'     => 'string',
							'required' => true,
						),
						'section' => array(
							'type'     => 'string',
							'enum'     => SavedViews::SECTIONS,
							'required' => true,
						),
						'query'   => array(
							'type'    => 'string',
							'default' => '',
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/gradebook/views/(?P<id>[a-f0-9]{12})',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_view' ),
					'permission_callback' => array( Access::class, 'check' ),
				),
			)
		);

		$settings = new SettingsController();
		register_rest_route(
			$this->namespace,
			'/settings/' . ScaleSettings::GROUP,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => function () {
						return $this->scale_response( array() );
					},
					'permission_callback' => array( $settings, 'get_items_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => function ( WP_REST_Request $request ) {
						$values = ScaleSettings::validate( $request->get_json_params() );
						if ( is_wp_error( $values ) ) {
							return $values;
						}
						$changed = ScaleSettings::write( $values );
						return is_wp_error( $changed ) ? $changed : $this->scale_response( $changed );
					},
					'permission_callback' => array( $settings, 'update_item_permissions_check' ),
				),
				'schema' => array( ScaleSettings::class, 'schema' ),
			)
		);
	}

	/**
	 * Resolve the request's scope.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return Scope|WP_Error
	 */
	private function scope( $request ) {
		ScopeResolver::reset();
		return Scope::from_request( $request, get_current_user_id() );
	}

	/**
	 * Wrap data in a no-store response, or turn an exception into an error.
	 *
	 * @param callable $build Returns data or a WP_Error.
	 * @param bool     $cache Whether to send the cache header.
	 * @return WP_REST_Response|WP_Error
	 */
	private function respond( callable $build, $cache = true ) {
		try {
			$data = $build();
		} catch ( \DomainException $exception ) {
			return new WP_Error( 'ohmylms_gradebook_too_large', __( 'This gradebook is too large to show at once. Pick a school or class.', 'ohmylms' ), array( 'status' => 422 ) );
		} catch ( \RuntimeException $exception ) {
			error_log( '[OhMyLMS gradebook] ' . $exception->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Server-side diagnostics only.
			return new WP_Error( 'ohmylms_gradebook_query_failed', __( 'The gradebook could not be loaded.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$response = rest_ensure_response( $data );
		$response->header( 'Cache-Control', 'private, no-store' );
		if ( $cache ) {
			$response->header( 'X-OhMyLMS-Cache', Cache::last_was_hit() ? 'hit' : 'miss' );
		}
		return $response;
	}

	/**
	 * Scopes, courses and classes the picker offers for the resolved scope.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function context( $request ) {
		$scope = $this->scope( $request );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}
		return $this->respond(
			function () use ( $scope ) {
				global $wpdb;
				$user_id = get_current_user_id();
				$ids     = $scope->course_ids();
				$args    = array( $wpdb->posts, OHMYLMS_COURSE_CPT );
				$in      = Scope::in_condition( 'ID', $ids, $args );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Fragment uses placeholders; bounded.
				$courses = $wpdb->get_results( $wpdb->prepare( "SELECT ID AS id, post_title AS title FROM %i WHERE post_type = %s AND post_status NOT IN ( 'trash', 'auto-draft' ) AND $in ORDER BY post_title, ID LIMIT 500", $args ), ARRAY_A );
				$classes = array();
				if ( $scope->is_all() ) {
					$classes = Directory::classes( null, true );
				} elseif ( Scope::SCHOOL === $scope->type() ) {
					$classes = Directory::classes( array( $scope->id() ), false );
				} else {
					$classes = array_values( Repository::classes( array( $scope->id() ) ) );
				}
				return array(
					'scope'      => $scope->to_array(),
					'scopes'     => array_map(
						static function ( $item ) {
							return $item->to_array();
						},
						ScopeResolver::available( $user_id )
					),
					'courses'    => array_map(
						static function ( $row ) {
							return array(
								'id'    => (int) $row['id'],
								'title' => (string) $row['title'],
							);
						},
						is_array( $courses ) ? $courses : array()
					),
					'classes'    => array_map(
						static function ( $row ) {
							return array(
								'id'          => (int) $row['id'],
								'name'        => (string) $row['name'],
								'school_name' => (string) $row['school_name'],
							);
						},
						$classes
					),
					'scale'      => ScaleSettings::scale(),
					'site_admin' => \OhMyLMS\Dashboard\Access::is_site_admin( $user_id ),
				);
			},
			false
		);
	}

	/**
	 * Course × Students.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function course( $request ) {
		$scope = $this->scope( $request );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}
		$course_id = (int) $request['course'];
		if ( ! Access::has_course( $scope, $course_id ) ) {
			return Access::course_not_found();
		}
		return $this->respond(
			function () use ( $scope, $course_id, $request ) {
				$data    = CourseGrid::present( CourseGrid::for_scope( $course_id, $scope ), $request->get_params() );
				$user_id = get_current_user_id();
				$manage  = Access::can_manage_course( $user_id, $course_id );
				$classes = array();
				foreach ( $data['classes'] as $class ) {
					if ( Access::can_weight_class( $user_id, $class['id'] ) ) {
						$classes[] = $class['id'];
					}
				}
				$data['scope'] = $scope->to_array();
				$data['can']   = array(
					'grade'         => true,
					'manage_course' => $manage,
					'weight_classes' => $classes,
				);
				return $data;
			}
		);
	}

	/**
	 * Roster × Assignments.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function roster( $request ) {
		$scope = $this->scope( $request );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}
		if ( ! Access::has_class( $scope, (int) $request['class'] ) ) {
			return Access::class_not_found();
		}
		return $this->respond(
			static function () use ( $request ) {
				return Views::roster( (int) $request['class'] );
			}
		);
	}

	/**
	 * Learners of the scope whose name contains the search (at most 20), for the transcript picker.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function learners( $request ) {
		$scope = $this->scope( $request );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}
		return $this->respond(
			static function () use ( $scope, $request ) {
				global $wpdb;
				$search = trim( (string) $request['search'] );
				$args   = array( $wpdb->users );
				$in     = Scope::in_condition( 'ID', $scope->learner_ids(), $args );
				$where  = '';
				if ( '' !== $search ) {
					$where  = ' AND display_name LIKE %s';
					$args[] = '%' . $wpdb->esc_like( $search ) . '%';
				}
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Fragments use placeholders; at most 20 rows.
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID AS id, display_name AS name FROM %i WHERE $in$where ORDER BY display_name, ID LIMIT 20", $args ), ARRAY_A );
				return array(
					'learners' => array_map(
						static function ( $row ) {
							return array(
								'id'   => (int) $row['id'],
								'name' => (string) $row['name'],
							);
						},
						is_array( $rows ) ? $rows : array()
					),
				);
			},
			false
		);
	}

	/**
	 * Student transcript.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function transcript( $request ) {
		$scope = $this->scope( $request );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}
		$learner = (int) $request['learner'];
		if ( ! Access::has_learner( $scope, $learner ) ) {
			return Access::learner_not_found();
		}
		return $this->respond(
			static function () use ( $scope, $learner ) {
				return Views::transcript( $scope, $learner );
			}
		);
	}

	/**
	 * Skill mastery.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function mastery( $request ) {
		$scope = $this->scope( $request );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}
		$course_id = (int) $request['course'];
		if ( $course_id && ! Access::has_course( $scope, $course_id ) ) {
			return Access::course_not_found();
		}
		return $this->respond(
			static function () use ( $scope, $request ) {
				return Views::mastery( $scope, $request->get_params() );
			}
		);
	}

	/**
	 * Org roll-up.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rollup( $request ) {
		$scope = $this->scope( $request );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}
		return $this->respond(
			static function () use ( $scope ) {
				$data          = Views::rollup( $scope );
				$data['nodes'] = Views::public_nodes( $data['nodes'] );
				$data['scope'] = $scope->to_array();
				return $data;
			}
		);
	}

	/**
	 * Check a cell request: the course and the learner are in the scope.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return Scope|WP_Error
	 */
	private function cell_scope( $request ) {
		$scope = $this->scope( $request );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}
		if ( ! Access::has_course( $scope, (int) $request['course'] ) ) {
			return Access::course_not_found();
		}
		if ( ! Access::has_learner( $scope, (int) $request['learner'] ) ) {
			return Access::learner_not_found();
		}
		return $scope;
	}

	/**
	 * One cell with its history.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function cell( $request ) {
		$scope = $this->cell_scope( $request );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}
		return $this->respond(
			static function () use ( $request ) {
				return Overrides::detail( (int) $request['course'], (int) $request['item'], (int) $request['learner'] );
			},
			false
		);
	}

	/**
	 * Override, excuse or revert a cell.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function change_cell( $request ) {
		$scope = $this->cell_scope( $request );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}
		return $this->respond(
			static function () use ( $request ) {
				return Overrides::apply(
					(int) $request['course'],
					(int) $request['item'],
					(int) $request['learner'],
					array(
						'action'           => $request['action'],
						'score'            => $request['score'],
						'reason'           => $request['reason'],
						'expected_version' => $request['expected_version'],
					),
					get_current_user_id()
				);
			},
			false
		);
	}

	/**
	 * Weights of a course: the course's and the class overrides the scope shows.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function weights( $request ) {
		$scope = $this->scope( $request );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}
		$course_id = (int) $request['course'];
		if ( ! Access::has_course( $scope, $course_id ) ) {
			return Access::course_not_found();
		}
		return $this->respond(
			static function () use ( $scope, $course_id ) {
				$grid = CourseGrid::for_scope( $course_id, $scope );
				return array(
					'course'  => $grid['course'],
					'weights' => $grid['weights'],
					'classes' => $grid['classes'],
				);
			}
		);
	}

	/**
	 * Save course weights or a class override.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_weights( $request ) {
		$scope = $this->scope( $request );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}
		$course_id = (int) $request['course'];
		$class_id  = (int) $request['class_id'];
		if ( ! Access::has_course( $scope, $course_id ) ) {
			return Access::course_not_found();
		}
		$user_id = get_current_user_id();
		$allowed = $class_id ? Access::has_class( $scope, $class_id ) && Access::can_weight_class( $user_id, $class_id ) : Access::can_manage_course( $user_id, $course_id );
		if ( ! $allowed ) {
			return Access::forbidden();
		}
		$body = $request->get_json_params();
		return $this->respond(
			static function () use ( $course_id, $class_id, $body, $request, $user_id ) {
				return Weights::save( $course_id, $class_id, is_array( $body ) && array_key_exists( 'weights', $body ) ? $body['weights'] : $request['weights'], (int) $request['expected_version'], $user_id );
			},
			false
		);
	}

	/**
	 * Save an item's gradebook settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_item( $request ) {
		$scope = $this->scope( $request );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}
		$course_id = (int) $request['course'];
		if ( ! Access::has_course( $scope, $course_id ) ) {
			return Access::course_not_found();
		}
		if ( ! Access::can_manage_course( get_current_user_id(), $course_id ) ) {
			return Access::forbidden();
		}
		return $this->respond(
			static function () use ( $course_id, $request ) {
				return Items::save(
					$course_id,
					(int) $request['item'],
					array(
						'category'  => $request['category'],
						'max_marks' => $request['max_marks'],
						'audience'  => $request['audience'],
						'class_ids' => $request['class_ids'],
						'due_at'    => $request['due_at'],
					),
					get_current_user_id()
				);
			},
			false
		);
	}

	/**
	 * The user's saved views.
	 *
	 * @return WP_REST_Response
	 */
	public function views() {
		$response = rest_ensure_response( array( 'views' => SavedViews::all( get_current_user_id() ) ) );
		$response->header( 'Cache-Control', 'private, no-store' );
		return $response;
	}

	/**
	 * Save a view.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_view( $request ) {
		$view = SavedViews::save( get_current_user_id(), (string) $request['name'], (string) $request['section'], (string) $request['query'] );
		if ( is_wp_error( $view ) ) {
			return $view;
		}
		$response = rest_ensure_response( $view );
		$response->set_status( 201 );
		$response->header( 'Cache-Control', 'private, no-store' );
		return $response;
	}

	/**
	 * Delete a view.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_view( $request ) {
		if ( ! SavedViews::delete( get_current_user_id(), (string) $request['id'] ) ) {
			return new WP_Error( 'ohmylms_gradebook_view_not_found', __( 'This saved view does not exist.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response(
			array(
				'deleted' => true,
				'id'      => (string) $request['id'],
			)
		);
	}

	/**
	 * Settings-group response.
	 *
	 * @param string[] $changed Changed fields.
	 * @return WP_REST_Response
	 */
	private function scale_response( array $changed ) {
		return rest_ensure_response(
			array(
				'group'   => ScaleSettings::GROUP,
				'values'  => ScaleSettings::read(),
				'changed' => $changed,
			)
		);
	}
}
