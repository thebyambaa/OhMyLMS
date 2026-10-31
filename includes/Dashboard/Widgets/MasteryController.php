<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Learners of a mastery heatmap cell.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard\Widgets;

use OhMyLMS\Dashboard\Access;
use OhMyLMS\Dashboard\Cache;
use OhMyLMS\Dashboard\MasteryRepository;
use OhMyLMS\Dashboard\Period;
use OhMyLMS\Dashboard\Scope;
use RuntimeException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * `GET /ohmylms/v1/dashboard/widgets/mastery-heatmap/learners?scope=&row=&skill=`: the learners of one row
 * of the scope who are below Familiar on a skill, lowest first (at most 50), with display names only.
 * The row must be one the scope shows (`class:<id>` or `course:<id>`), so a teacher only reaches their own
 * classes. Cached like the widgets; report links are added per user.
 */
final class MasteryController {

	/** Most learners listed. */
	const MAX_LEARNERS = 50;

	/** Row key pattern. */
	const ROW_PATTERN = '^(class|course):[1-9][0-9]{0,18}$';

	/**
	 * Register the route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'ohmylms/v1',
			'/dashboard/widgets/mastery-heatmap/learners',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'learners' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
					'args'                => array(
						'scope' => Scope::arg(),
						'row'   => array(
							'description' => __( 'Row of the heatmap: "class:<id>" or "course:<id>".', 'ohmylms' ),
							'type'        => 'string',
							'pattern'     => self::ROW_PATTERN,
							'required'    => true,
						),
						'skill' => array(
							'description' => __( 'Skill id.', 'ohmylms' ),
							'type'        => 'integer',
							'minimum'     => 1,
							'required'    => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Dashboard access.
	 *
	 * @return true|WP_Error
	 */
	public static function permissions_check() {
		$user_id = get_current_user_id();
		return Access::can_view( $user_id ) ? true : Access::denied( $user_id );
	}

	/**
	 * Learners below Familiar in a row on a skill.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function learners( $request ) {
		$user_id = get_current_user_id();
		$scope   = Scope::from_request( $request, $user_id );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}
		$row_key  = (string) $request['row'];
		$skill_id = (int) $request['skill'];
		try {
			$entry = Cache::remember(
				'mastery-heatmap',
				$scope,
				new Period( 30 ),
				static function () use ( $scope, $row_key, $skill_id ) {
					return self::compute( $scope, $row_key, $skill_id );
				},
				array( 'learners', $row_key, $skill_id )
			);
		} catch ( RuntimeException $exception ) {
			return new WP_Error( 'ohmylms_dashboard_query_failed', __( 'The dashboard could not be loaded.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		if ( isset( $entry['error'] ) ) {
			return 'row' === $entry['error']
				? new WP_Error( 'ohmylms_dashboard_row_not_found', __( 'That class or course is not part of this dashboard view.', 'ohmylms' ), array( 'status' => 404 ) )
				: new WP_Error( 'ohmylms_dashboard_skill_not_found', __( 'That skill does not exist.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$reports = user_can( $user_id, Access::SITE_CAPABILITY );
		foreach ( $entry['learners'] as &$learner ) {
			$learner['route'] = $reports ? '/students/' . (int) $learner['id'] . '/report' : null;
		}
		unset( $learner );
		$response = rest_ensure_response( array( 'scope' => $scope->key() ) + $entry );
		$response->header( 'Cache-Control', 'private, no-store' );
		$response->header( 'X-OhMyLMS-Cache', Cache::last_was_hit() ? 'hit' : 'miss' );
		return $response;
	}

	/**
	 * The shared (cacheable) list: no links, display names only.
	 *
	 * @param Scope  $scope    Scope.
	 * @param string $row_key  Row key.
	 * @param int    $skill_id Skill.
	 * @return array
	 */
	public static function compute( Scope $scope, $row_key, $skill_id ) {
		$row = MasteryRepository::row( $scope, $row_key );
		if ( ! $row ) {
			return array( 'error' => 'row' );
		}
		$skill = MasteryRepository::terms( array( $skill_id ) )[ $skill_id ] ?? null;
		if ( ! $skill ) {
			return array( 'error' => 'skill' );
		}
		$thresholds = MasteryLevels::thresholds();
		$found      = MasteryRepository::struggling_learners( $row['learners'], $skill_id, $thresholds['familiar'], self::MAX_LEARNERS );
		$names      = self::names( array_column( $found['learners'], 'id' ) );
		$learners   = array();
		foreach ( $found['learners'] as $learner ) {
			$learners[] = array(
				'id'      => $learner['id'],
				'name'    => $names[ $learner['id'] ] ?? '',
				'percent' => $learner['percent'],
				'level'   => MasteryLevels::level( $learner['percent'], true, $thresholds ),
			);
		}
		return array(
			'row'      => array(
				'key'      => $row['key'],
				'type'     => $row['type'],
				'id'       => $row['id'],
				'label'    => $row['label'],
				'learners' => $row['count'],
			),
			'skill'    => array(
				'id'   => $skill['id'],
				'name' => $skill['name'],
				'code' => $skill['code'],
			),
			'below'    => $found['total'],
			'assessed' => $found['assessed'],
			'learners' => $learners,
			'limit'    => self::MAX_LEARNERS,
		);
	}

	/**
	 * Display names of users.
	 *
	 * @param int[] $ids Users.
	 * @return array<int,string>
	 */
	public static function names( array $ids ) {
		if ( ! $ids ) {
			return array();
		}
		$names = array();
		foreach ( get_users(
			array(
				'include' => array_map( 'intval', $ids ),
				'fields'  => array( 'ID', 'display_name' ),
			)
		) as $user ) {
			$names[ (int) $user->ID ] = (string) $user->display_name;
		}
		return $names;
	}
}
