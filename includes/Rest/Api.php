<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * API Manager class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest;

/**
 * API Manager class.
 *
 * All API classes would be registered here.
 *
 * @since 1.0.0
 */
class Api {

	/**
	 * Class dir and class name mapping.
	 *
	 * @var array
	 *
	 * @since 1.0.0
	 */
	protected $class_map;

	/**
	 * Option holding the map of first route segments to controller groups.
	 */
	const MAP_OPTION = 'ohmylms_rest_route_map';

	/**
	 * Controllers already registered on the current REST server, keyed by class name.
	 *
	 * @var array<string,bool>
	 */
	private $registered = array();

	/**
	 * Controller groups already registered on demand, keyed by group id.
	 *
	 * @var array<int,bool>
	 */
	private $ensured = array();

	/**
	 * Whether every controller is registered.
	 *
	 * @var bool
	 */
	private $all_registered = false;

	/**
	 * Stored route map while controllers are registered on demand, otherwise null.
	 *
	 * @var array|null
	 */
	private $route_map = null;

	/**
	 * A map being built during `rest_api_init`, with the route counts it was built from.
	 *
	 * @var array|null
	 */
	private $pending = null;

	/**
	 * Whether this class is reading the registered routes itself.
	 *
	 * @var bool
	 */
	private $checking = false;

	/**
	 * Whether the complete endpoint list is being read back from the server.
	 *
	 * @var bool
	 */
	private $capturing = false;

	/**
	 * Complete endpoint list read back from the server.
	 *
	 * @var array|null
	 */
	private $captured = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! class_exists( 'WP_REST_Server' ) ) {
			return;
		}

		$controllers_v1  = array(
			\OhMyLMS\Rest\V1\PluginController::class,
			\OhMyLMS\Rest\V1\CourseController::class,
			\OhMyLMS\Rest\V1\CourseCatalogController::class,
			\OhMyLMS\Rest\V1\CourseSettingsController::class,
			\OhMyLMS\Rest\V1\LessonAuthoringController::class,
			\OhMyLMS\Rest\V1\LessonPreviewController::class,
			\OhMyLMS\Rest\V1\SyllabusEditorController::class,
			\OhMyLMS\Rest\V1\CourseStructureController::class,
			\OhMyLMS\Rest\V1\LearningController::class,
			\OhMyLMS\Rest\V1\ContentHubController::class,
			\OhMyLMS\Rest\V1\AuthoringController::class,
			\OhMyLMS\Rest\V1\SetupWizardController::class,
			\OhMyLMS\Rest\V1\StudentController::class,
			\OhMyLMS\Rest\V1\UserController::class,
			\OhMyLMS\Rest\V1\ChapterController::class,
			\OhMyLMS\Rest\V1\LessonController::class,
			\OhMyLMS\Rest\V1\QuizController::class,
			\OhMyLMS\Rest\V1\QuizQuestionsController::class,
			\OhMyLMS\Rest\V1\AssessmentController::class,
			\OhMyLMS\Rest\V1\QuestionBankController::class,
			\OhMyLMS\Rest\V1\QuestionsController::class,
			\OhMyLMS\Rest\V1\QuestionImportController::class,
			\OhMyLMS\Rest\V1\QuestionBankToolsController::class,
			\OhMyLMS\Rest\V1\CourseStructureToolsController::class,
			\OhMyLMS\Rest\V1\SkillAssessmentToolsController::class,
			\OhMyLMS\Rest\V1\SkillController::class,
			\OhMyLMS\Rest\V1\SkillAuthoringController::class,
			\OhMyLMS\Rest\V1\AttemptController::class,
			\OhMyLMS\Rest\V1\PracticeController::class,
			\OhMyLMS\Rest\V1\PlacementController::class,
			\OhMyLMS\Rest\V1\SkillReportController::class,
			\OhMyLMS\Rest\V1\CurriculumController::class,
			\OhMyLMS\Rest\V1\SubjectController::class,
			\OhMyLMS\Rest\V1\SiteSetupController::class,
			\OhMyLMS\Rest\V1\SyllabusController::class,
			\OhMyLMS\Rest\V1\CourseOrganizationController::class,
			\OhMyLMS\Rest\V1\SkillMappingController::class,
			\OhMyLMS\Rest\V1\ComparisonController::class,
			\OhMyLMS\Rest\V1\SkillMapOverlayController::class,
			\OhMyLMS\Rest\V1\SkillWorkspaceController::class,
			\OhMyLMS\Rest\V1\PreviewController::class,
			\OhMyLMS\Rest\V1\TemplateController::class,
			\OhMyLMS\Rest\V1\SettingsController::class,
			\OhMyLMS\Rest\V1\PaymentGatewaySettingsController::class,
			\OhMyLMS\Rest\V1\DesignSettingsController::class,
			\OhMyLMS\Rest\V1\DesignSystemController::class,
			\OhMyLMS\Rest\V1\HubIconsController::class,
			\OhMyLMS\Rest\V1\CategoryController::class,
			\OhMyLMS\Rest\V1\EmailController::class,
			\OhMyLMS\Rest\V1\DashboardController::class,
			\OhMyLMS\Rest\V1\FrontDashboardController::class,
			\OhMyLMS\Rest\V1\StudioController::class,
			\OhMyLMS\Rest\V1\AnalyticsController::class,
			\OhMyLMS\Rest\V1\CourseRosterController::class,
			\OhMyLMS\Rest\V1\StudentReportController::class,
			\OhMyLMS\Rest\V1\SessionsController::class,
			\OhMyLMS\Rest\V1\CertificateController::class,
			\OhMyLMS\Rest\V1\IssuedCertificatesController::class,
			\OhMyLMS\Rest\V1\MigrationController::class,
			\OhMyLMS\Rest\V1\NotificationController::class,
			\OhMyLMS\Rest\V1\IntegrationsController::class,
			\OhMyLMS\Rest\V1\AppController::class,
			\OhMyLMS\Rest\V1\WebhookController::class,
			\OhMyLMS\Rest\V1\SubscriptionsController::class,
			\OhMyLMS\Rest\V1\AssignmentsController::class,
			\OhMyLMS\Rest\V1\AssignmentDeliveryController::class,
			\OhMyLMS\Rest\V1\AccountHubController::class,
			\OhMyLMS\Rest\V1\AccountHubRosterController::class,
			\OhMyLMS\Rest\V1\AccountHubSchoolLifecycleController::class,
			\OhMyLMS\Rest\V1\AccountHubGuardiansController::class,
			\OhMyLMS\Rest\V1\AccountHubClassWorkController::class,
			\OhMyLMS\Rest\V1\AccountHubReportsController::class,
			\OhMyLMS\Rest\V1\AccountHubSampleSchoolController::class,
			\OhMyLMS\Rest\V1\AccountHubInvitationAcceptController::class,
			\OhMyLMS\Rest\V1\ExamsController::class,
			\OhMyLMS\Rest\V1\ExamImportsController::class,
			\OhMyLMS\Rest\V1\ExamImportSourceController::class,
			\OhMyLMS\Rest\V1\AccountHubUsersController::class,
			\OhMyLMS\Rest\V1\ContentHubOverviewController::class,
			\OhMyLMS\Rest\V1\ContentBankController::class,
			\OhMyLMS\Rest\V1\SkillCoverageController::class,
			\OhMyLMS\Rest\V1\QuizReportsController::class,
			\OhMyLMS\Rest\V1\CheckoutSettingsController::class,
			\OhMyLMS\Rest\V1\PointPurchasesController::class,
			\OhMyLMS\Rest\V1\CartController::class,
			\OhMyLMS\Rest\V1\CohortsController::class,
			\OhMyLMS\Rest\V1\AutomationsController::class,
			\OhMyLMS\Assessment\Rest\ResponseMediaController::class,
			\OhMyLMS\ExamImport\Rest\JobsController::class,
			\OhMyLMS\ExamImport\Rest\WorkflowController::class,
			\OhMyLMS\ExamImport\Rest\FiguresController::class,
			\OhMyLMS\Exams\Rest\LibraryController::class,
			\OhMyLMS\Exams\Rest\ComposerController::class,
			\OhMyLMS\QuestionBank\Rest\OrganisationController::class,
			\OhMyLMS\Practice\Rest\DashboardController::class,
			\OhMyLMS\Assessment\Rest\QuestionPreviewController::class,
			\OhMyLMS\Blocks\Rest\QuizPlayerController::class,
			\OhMyLMS\Blocks\Rest\QuestionPlayerController::class,
			\OhMyLMS\MCP\Rest\ServerController::class,
			\OhMyLMS\AI\Rest\SettingsController::class,
		);
		$controllers_v1  = apply_filters( 'ohmylms_rest_v1_controllers', $controllers_v1 );
		$controllers_v2  = array();
		$this->class_map = array_merge( $controllers_v1, $controllers_v2 );

		// Init REST API routes.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ), 10 );
	}

	/**
	 * Register REST API routes.
	 *
	 * Controllers are registered on demand: a request for `/ohmylms/v1/courses/12` only loads the
	 * controllers that own routes under `/ohmylms/v1/courses`, found through a stored map of first
	 * path segments. Controllers that share a segment always load together and in their listed order,
	 * so route precedence is the same as with full registration. The index, batch requests, unknown
	 * segments, WP-CLI and any code that lists routes through the server register everything.
	 *
	 * A controller whose `register_routes()` adds hooks that act on routes outside its own segments
	 * must name those segments in a static `lazy_route_prefixes()` method, so it is loaded for them.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		$this->registered     = array();
		$this->ensured        = array();
		$this->all_registered = false;
		$this->route_map      = null;

		/**
		 * Filters whether REST controllers are registered on demand.
		 *
		 * @param bool $lazy True to register only the controllers a request needs.
		 */
		$lazy = (bool) apply_filters( 'ohmylms_rest_lazy_routes', ! ( defined( 'WP_CLI' ) && WP_CLI ) );
		if ( ! $lazy ) {
			$this->register_all();
			return;
		}

		$map = get_option( self::MAP_OPTION );
		if ( ! is_array( $map ) || ( $map['key'] ?? '' ) !== $this->map_key() ) {
			$this->pending = $this->build_map();
			add_action( 'rest_api_init', array( $this, 'store_map' ), PHP_INT_MAX );
			return;
		}
		if ( empty( $map['lazy'] ) ) {
			$this->register_all();
			return;
		}

		$this->route_map = $map;
		$this->register_classes( $map['eager'] );
		add_filter( 'rest_pre_dispatch', array( $this, 'register_for_request' ), -1000, 3 );
		add_filter( 'rest_endpoints', array( $this, 'complete_endpoints' ), -1000 );
	}

	/**
	 * Keep the endpoint list sufficient for whoever reads it.
	 *
	 * `WP_REST_Server::get_routes()` applies this filter. When the server is matching a request,
	 * including the link targets it matches without dispatching them, only that request's controllers
	 * are needed. Every other caller that lists routes (the index, tools, tests) must see all of them.
	 *
	 * @param array $endpoints Registered endpoints.
	 * @return array Endpoints, complete unless a request is being matched.
	 */
	public function complete_endpoints( $endpoints ) {
		if ( $this->capturing ) {
			$this->captured = $endpoints;
			return $endpoints;
		}
		if ( $this->all_registered || null === $this->route_map || $this->checking ) {
			return $endpoints;
		}
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- The filter has no other way to tell the server's own route matching, and the request it matches, from a caller that lists routes.
		$trace   = debug_backtrace( 0, 6 );
		$listing = true;
		foreach ( $trace as $index => $frame ) {
			if ( 'get_routes' !== $frame['function'] || 'WP_REST_Server' !== ( $frame['class'] ?? '' ) ) {
				continue;
			}
			$caller  = $trace[ $index + 1 ] ?? array();
			$request = $caller['args'][0] ?? null;
			if ( 'rest_send_allow_header' === ( $caller['function'] ?? '' ) ) {
				// Reads the entry of the route that was just matched.
				return $endpoints;
			}
			if ( 'match_request_to_handler' === ( $caller['function'] ?? '' ) && $request instanceof \WP_REST_Request ) {
				if ( ! $this->ensure_route( (string) $request->get_route() ) ) {
					return $endpoints;
				}
				$listing = false;
			}
			break;
		}
		if ( $listing ) {
			$this->register_all();
		}

		$this->capturing = true;
		rest_get_server()->get_routes();
		$this->capturing = false;
		$endpoints       = is_array( $this->captured ) ? $this->captured : $endpoints;
		$this->captured  = null;
		return $endpoints;
	}

	/**
	 * Store the map once every other registrar has run.
	 *
	 * Routes that other code registers after this class, under a segment this class also uses, used
	 * to follow the controllers' routes. Those controller groups stay registered during
	 * `rest_api_init`, so that order is kept.
	 */
	public function store_map() {
		if ( null === $this->pending ) {
			return;
		}
		$map     = $this->pending['map'];
		$server  = rest_get_server();
		$changed = array_diff_assoc( array_map( 'count', $server->get_routes() ), $this->pending['counts'] );
		$late    = array();
		foreach ( array_keys( $changed ) as $pattern ) {
			foreach ( $map['namespaces'] as $candidate ) {
				if ( 0 !== strpos( $pattern, '/' . $candidate . '/' ) ) {
					continue;
				}
				$prefix = $this->literal_prefix( $pattern, $map['namespaces'] );
				if ( null === $prefix ) {
					$map['lazy'] = false;
				} elseif ( isset( $map['prefixes'][ $prefix ] ) ) {
					$late[ $map['prefixes'][ $prefix ] ] = true;
				}
			}
		}
		$eager = array_flip( $map['eager'] );
		foreach ( array_keys( $late ) as $group ) {
			$eager += array_flip( $map['groups'][ $group ] );
		}
		$map['eager']  = array_values( array_filter( $this->class_map, static fn( $controller_class ) => isset( $eager[ $controller_class ] ) ) );
		$this->pending = null;
		update_option( self::MAP_OPTION, $map, true );
	}

	/**
	 * Register the controllers a request needs, before the server looks for its route.
	 *
	 * Runs ahead of the other `rest_pre_dispatch` callbacks, so the ones a controller adds for its
	 * own routes still see this request.
	 *
	 * @param mixed            $result  Response to replace the requested one, usually null.
	 * @param \WP_REST_Server  $server  Server instance.
	 * @param \WP_REST_Request $request Request being dispatched.
	 * @return mixed The unchanged result.
	 */
	public function register_for_request( $result, $server, $request ) {
		if ( null !== $this->route_map ) {
			$this->ensure_route( (string) $request->get_route() );
		}
		return $result;
	}

	/**
	 * Register the controller group that owns a route.
	 *
	 * @param string $route Requested route.
	 * @return bool Whether controllers were registered.
	 */
	private function ensure_route( $route ) {
		if ( $this->all_registered ) {
			return false;
		}
		$path = strtolower( untrailingslashit( $route ) );
		if ( '' === $path || '/batch/v1' === $path ) {
			$this->register_all();
			return true;
		}

		$namespace = '';
		foreach ( $this->route_map['namespaces'] as $candidate ) {
			if ( 0 === strpos( $path . '/', '/' . $candidate . '/' ) && strlen( $candidate ) > strlen( $namespace ) ) {
				$namespace = $candidate;
			}
		}
		if ( '' === $namespace ) {
			return false;
		}

		$segment = strtok( substr( $path, strlen( $namespace ) + 2 ), '/' );
		$prefix  = '/' . $namespace . '/' . $segment;
		$server  = rest_get_server();
		if ( false !== $segment && isset( $this->route_map['prefixes'][ $prefix ] ) ) {
			$group = $this->route_map['prefixes'][ $prefix ];
			if ( isset( $this->ensured[ $group ] ) ) {
				return false;
			}
			$this->ensured[ $group ] = true;
			$count                   = count( $this->registered );
			$this->register_classes( $this->route_map['groups'][ $group ] );
			if ( $this->has_route( $server, $namespace, $route ) ) {
				return count( $this->registered ) > $count;
			}
		}

		// The namespace index, a route that does not exist, or a map older than the code.
		$this->register_all();
		if ( false !== $segment && $this->has_route( $server, $namespace, $route ) ) {
			delete_option( self::MAP_OPTION );
		}
		return true;
	}

	/**
	 * Whether a registered route pattern matches the path, as the server will test it.
	 *
	 * @param \WP_REST_Server $server    Server instance.
	 * @param string          $rest_namespace Route namespace.
	 * @param string          $route     Requested route.
	 * @return bool
	 */
	private function has_route( $server, $rest_namespace, $route ) {
		$this->checking = true;
		$patterns       = array_keys( $server->get_routes( $rest_namespace ) );
		$this->checking = false;
		foreach ( $patterns as $pattern ) {
			if ( '/' . $rest_namespace !== $pattern && preg_match( '@^' . $pattern . '$@i', $route ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Register every controller that is not registered yet.
	 */
	private function register_all() {
		$this->register_classes( $this->class_map );
		$this->all_registered = true;

		/**
		 * Fires once every controller is registered, for code that also registers its routes on demand.
		 */
		do_action( 'ohmylms_rest_register_all' );
	}

	/**
	 * Register controllers once each.
	 *
	 * @param string[] $classes Controller class names, in registration order.
	 */
	private function register_classes( array $classes ) {
		$known = array_flip( $this->class_map );
		foreach ( $classes as $controller_class ) {
			if ( isset( $this->registered[ $controller_class ] ) || ! isset( $known[ $controller_class ] ) ) {
				continue;
			}
			$this->registered[ $controller_class ] = true;
			$controller_instance                   = new $controller_class();
			$controller_instance->register_routes();
		}
	}

	/**
	 * Identify the controller list the stored map was built for.
	 *
	 * @return string
	 */
	private function map_key() {
		return md5( OHMYLMS_VERSION . '|' . implode( ',', $this->class_map ) );
	}

	/**
	 * Register every controller, recording which first path segments each one registers under.
	 *
	 * Controllers that share a segment are grouped, so a group can always be registered as a whole.
	 * A route outside a namespace, or one whose first segment is a pattern, could match any path;
	 * the map is then marked as not lazy.
	 *
	 * @return array{counts:array<string,int>,map:array{key:string,lazy:bool,namespaces:string[],prefixes:array<string,int>,groups:array<int,string[]>,eager:string[]}}
	 */
	private function build_map() {
		$server   = rest_get_server();
		$count    = static function () use ( $server ) {
			return array_map( 'count', $server->get_routes() );
		};
		$lazy     = true;
		$owners   = array();
		$prefixes = array();
		$before   = $count();
		foreach ( $this->class_map as $controller_class ) {
			$this->register_classes( array( $controller_class ) );
			$after      = $count();
			$namespaces = $server->get_namespaces();
			$found      = array();
			foreach ( $after as $pattern => $handlers ) {
				if ( isset( $before[ $pattern ] ) && $before[ $pattern ] === $handlers ) {
					continue;
				}
				$prefix = $this->literal_prefix( $pattern, $namespaces );
				if ( null === $prefix ) {
					$lazy = false;
				} elseif ( '' !== $prefix ) {
					$found[ $prefix ] = true;
				}
			}
			if ( is_callable( array( $controller_class, 'lazy_route_prefixes' ) ) ) {
				foreach ( (array) call_user_func( array( $controller_class, 'lazy_route_prefixes' ) ) as $prefix ) {
					$found[ strtolower( untrailingslashit( (string) $prefix ) ) ] = true;
				}
			}
			$owners[ $controller_class ] = array_keys( $found );
			$before                      = $after;
		}
		$this->all_registered = true;
		/** This action is documented in includes/Rest/Api.php */
		do_action( 'ohmylms_rest_register_all' );

		// Union controllers that share a prefix into groups.
		$group_of = array();
		foreach ( $owners as $controller_class => $found ) {
			$group_of[ $controller_class ] = $controller_class;
		}
		$root  = static function ( $controller_class ) use ( &$group_of ) {
			while ( $group_of[ $controller_class ] !== $controller_class ) {
				$controller_class = $group_of[ $controller_class ];
			}
			return $controller_class;
		};
		$first = array();
		foreach ( $owners as $controller_class => $found ) {
			foreach ( $found as $prefix ) {
				if ( isset( $first[ $prefix ] ) ) {
					$group_of[ $root( $controller_class ) ] = $root( $first[ $prefix ] );
				} else {
					$first[ $prefix ] = $controller_class;
				}
			}
		}

		$ids        = array();
		$groups     = array();
		$eager      = array();
		$namespaces = array();
		foreach ( $owners as $controller_class => $found ) {
			if ( ! $found ) {
				$eager[] = $controller_class;
				continue;
			}
			$leader = $root( $controller_class );
			if ( ! isset( $ids[ $leader ] ) ) {
				$ids[ $leader ] = count( $ids );
			}
			$groups[ $ids[ $leader ] ][] = $controller_class;
			foreach ( $found as $prefix ) {
				$prefixes[ $prefix ]            = $ids[ $leader ];
				$route_namespace                = substr( $prefix, 1, strrpos( $prefix, '/' ) - 1 );
				$namespaces[ $route_namespace ] = true;
			}
		}

		return array(
			'counts' => $before,
			'map'    => array(
				'key'        => $this->map_key(),
				'lazy'       => $lazy,
				'namespaces' => array_keys( $namespaces ),
				'prefixes'   => $prefixes,
				'groups'     => $groups,
				'eager'      => $eager,
			),
		);
	}

	/**
	 * Reduce a route pattern to `/namespace/first-segment`.
	 *
	 * @param string   $pattern    Registered route pattern.
	 * @param string[] $namespaces Registered namespaces.
	 * @return string|null The lowercase prefix, an empty string for a namespace index, or null when
	 *                     the pattern is outside every namespace or its first segment is not literal.
	 */
	private function literal_prefix( $pattern, array $namespaces ) {
		$namespace = '';
		foreach ( $namespaces as $candidate ) {
			if ( 0 === strpos( $pattern . '/', '/' . $candidate . '/' ) && strlen( $candidate ) > strlen( $namespace ) ) {
				$namespace = $candidate;
			}
		}
		if ( '' === $namespace ) {
			return null;
		}
		$rest = substr( $pattern, strlen( $namespace ) + 1 );
		if ( '' === $rest ) {
			return '';
		}
		if ( ! preg_match( '#^/([A-Za-z0-9_-]+)(/.*)?$#', $rest, $match ) ) {
			return null;
		}
		return strtolower( '/' . $namespace . '/' . $match[1] );
	}
}
