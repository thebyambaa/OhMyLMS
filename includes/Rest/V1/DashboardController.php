<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * DashboardController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Analytics\Cache;
use OhMyLMS\Analytics\DateRange;
use OhMyLMS\Analytics\Repository;
use OhMyLMS\Analytics\RestSupport;
use RuntimeException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

/**
 * REST API of the dashboard overview under `ohmylms/v1/dashboard`.
 *
 * One request returns what the overview screen shows for a date range: the totals with the previous
 * period, the earnings series, the newest courses and the most popular courses. See
 * docs/api/analytics.md for the contract. Every route needs AnalyticsController::CAPABILITY.
 *
 * @since 1.0.0
 */
class DashboardController extends RestController {

	/**
	 * Number of courses in each course list.
	 */
	const LIST_SIZE = 5;

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * The base route for dashboard endpoints.
	 *
	 * @var string
	 */
	protected $rest_base = 'dashboard';

	/**
	 * Register the routes for the dashboard endpoints.
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
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => DateRange::args(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Check if the current user can read the dashboard.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return RestSupport::authorize();
	}

	/**
	 * Get the dashboard overview.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$range = DateRange::from_request( $request );
		if ( is_wp_error( $range ) ) {
			return $range;
		}

		try {
			$data = Cache::remember(
				'dashboard',
				$range->to_array(),
				function () use ( $range ) {
					return $this->build( $range );
				}
			);
		} catch ( RuntimeException $exception ) {
			return RestSupport::query_failed();
		}

		/**
		 * Filters the dashboard overview payload.
		 *
		 * @param array     $data  Overview data (see docs/api/analytics.md).
		 * @param DateRange $range Resolved date range.
		 */
		$data = apply_filters( 'ohmylms_dashboard_data', $data, $range );

		return rest_ensure_response( $this->prepare_item_for_response( $data, $request ) );
	}

	/**
	 * Compute the overview.
	 *
	 * @param DateRange $range Range.
	 * @return array
	 * @throws RuntimeException On a database error.
	 */
	private function build( DateRange $range ) {
		$repository = new Repository();
		$previous   = $range->previous();

		$recent  = $repository->courses( $range, array(), 'date', 'desc', self::LIST_SIZE, 0 );
		$popular = $repository->courses( $range, array( 'active_only' => true ), 'enrollments', 'desc', self::LIST_SIZE, 0 );

		return array(
			'generated_at'    => gmdate( 'Y-m-d\TH:i:s' ),
			'range'           => $range->to_array(),
			'currency'        => $repository->currency(),
			'summary'         => array(
				'current'  => $repository->summary( $range->start_sql(), $range->end_sql() ),
				'previous' => $repository->summary( $previous->start_sql(), $previous->end_sql() ),
			),
			'totals'          => $repository->catalog_totals(),
			'series'          => $repository->series( $range ),
			'recent_courses'  => RestSupport::decorate_courses( $recent['items'] ),
			'popular_courses' => RestSupport::decorate_courses( $popular['items'] ),
		);
	}

	/**
	 * Pass the overview through the additional-fields machinery.
	 *
	 * @param array           $item    Overview data.
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public function prepare_item_for_response( $item, $request ) {
		return $this->add_additional_fields_to_object( $item, $request );
	}

	/**
	 * Get the dashboard schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$summary = RestSupport::summary_schema();
		$courses = array(
			'type'     => 'array',
			'context'  => array( 'view' ),
			'readonly' => true,
			'items'    => RestSupport::course_row_schema(),
		);

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'dashboard',
			'type'       => 'object',
			'properties' => array(
				'generated_at'    => array(
					'description' => __( 'When the figures were computed (GMT).', 'ohmylms' ),
					'type'        => 'string',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'range'           => RestSupport::range_schema(),
				'currency'        => RestSupport::currency_schema(),
				'summary'         => array(
					'description' => __( 'Totals of the range and of the previous period.', 'ohmylms' ),
					'type'        => 'object',
					'context'     => array( 'view' ),
					'readonly'    => true,
					'properties'  => array(
						'current'  => $summary,
						'previous' => $summary,
					),
				),
				'totals'          => array(
					'description' => __( 'Site-wide counts that do not depend on the range.', 'ohmylms' ),
					'type'        => 'object',
					'context'     => array( 'view' ),
					'readonly'    => true,
					'properties'  => array(
						'courses'           => array( 'type' => 'integer' ),
						'published_courses' => array( 'type' => 'integer' ),
						'students'          => array( 'type' => 'integer' ),
					),
				),
				'series'          => RestSupport::series_schema(),
				'recent_courses'  => array_merge( $courses, array( 'description' => __( 'The newest courses with their figures for the range.', 'ohmylms' ) ) ),
				'popular_courses' => array_merge( $courses, array( 'description' => __( 'Courses with the most new students in the range.', 'ohmylms' ) ) ),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}
}
