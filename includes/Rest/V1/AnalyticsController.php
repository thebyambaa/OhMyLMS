<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * AnalyticsController class.
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
 * REST API of the earnings and course reports under `ohmylms/v1/analytics`.
 *
 * Routes: `earnings` (summary, series, breakdowns), `transactions` (paged orders) and `courses`
 * (paged course report). The per-course report lives in DashboardProController. See
 * docs/api/analytics.md for the contract.
 *
 * Revenue, order and student data is sensitive, so every dashboard and analytics route needs
 * CAPABILITY. Authentication is WordPress core's (cookie requests need the `X-WP-Nonce` header).
 *
 * @since 1.0.0
 */
class AnalyticsController extends RestController {

	/**
	 * Capability required by every dashboard and analytics route.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * The base route for analytics endpoints.
	 *
	 * @var string
	 */
	protected $rest_base = 'analytics';

	/**
	 * Register the routes for the analytics endpoints.
	 *
	 * @return void
	 */
	public function register_routes() {
		$base = '/' . $this->rest_base;

		register_rest_route(
			$this->namespace,
			$base . '/earnings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => DateRange::args(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/transactions',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_transactions' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/courses',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_course_params(),
				),
			)
		);
	}

	/**
	 * Check the shared capability.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return RestSupport::authorize();
	}

	/**
	 * Earnings summary, series and breakdowns.
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
				'earnings',
				$range->to_array(),
				function () use ( $range ) {
					$repository = new Repository();
					$previous   = $range->previous();
					return array(
						'generated_at'      => gmdate( 'Y-m-d\TH:i:s' ),
						'range'             => $range->to_array(),
						'currency'          => $repository->currency(),
						'summary'           => array(
							'current'  => $repository->summary( $range->start_sql(), $range->end_sql() ),
							'previous' => $repository->summary( $previous->start_sql(), $previous->end_sql() ),
						),
						'series'            => $repository->series( $range ),
						'by_country'        => $repository->by_country( $range ),
						'by_payment_method' => $repository->by_payment_method( $range ),
					);
				}
			);
		} catch ( RuntimeException $exception ) {
			return RestSupport::query_failed();
		}

		return rest_ensure_response( $this->prepare_item_for_response( $data, $request ) );
	}

	/**
	 * Paged orders of the range.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function get_transactions( $request ) {
		$range = DateRange::from_request( $request );
		if ( is_wp_error( $range ) ) {
			return $range;
		}

		$page     = max( 1, (int) $request['page'] );
		$per_page = min( 100, max( 1, (int) $request['per_page'] ) );
		$search   = trim( (string) $request['search'] );
		$filters  = array(
			'payment_method' => (string) $request['payment_method'],
			'order_id'       => ctype_digit( $search ) ? (int) $search : 0,
		);
		if ( '' !== $search && ! $filters['order_id'] ) {
			// Only order numbers can be searched; any other text matches nothing.
			$filters['order_id'] = PHP_INT_MAX;
		}

		try {
			$result = ( new Repository() )->transactions( $range, $filters, (string) $request['orderby'], (string) $request['order'], $per_page, ( $page - 1 ) * $per_page );
		} catch ( RuntimeException $exception ) {
			return RestSupport::query_failed();
		}

		if ( $result['total'] > 0 && $page > (int) ceil( $result['total'] / $per_page ) ) {
			return RestSupport::invalid_page();
		}

		$response = rest_ensure_response( $result['items'] );
		RestSupport::add_pagination_headers( $response, $request, $result['total'], $per_page, $page, $this->rest_base . '/transactions' );
		return $response;
	}

	/**
	 * Paged course report.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$range = DateRange::from_request( $request );
		if ( is_wp_error( $range ) ) {
			return $range;
		}

		$page     = max( 1, (int) $request['page'] );
		$per_page = min( 100, max( 1, (int) $request['per_page'] ) );

		try {
			$result = ( new Repository() )->courses(
				$range,
				array( 'search' => trim( (string) $request['search'] ) ),
				(string) $request['orderby'],
				(string) $request['order'],
				$per_page,
				( $page - 1 ) * $per_page
			);
		} catch ( RuntimeException $exception ) {
			return RestSupport::query_failed();
		}

		if ( $result['total'] > 0 && $page > (int) ceil( $result['total'] / $per_page ) ) {
			return RestSupport::invalid_page();
		}

		$response = rest_ensure_response( RestSupport::decorate_courses( $result['items'] ) );
		RestSupport::add_pagination_headers( $response, $request, $result['total'], $per_page, $page, $this->rest_base . '/courses' );
		return $response;
	}

	/**
	 * Pass the earnings payload through the additional-fields machinery.
	 *
	 * @param array           $item    Earnings data.
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public function prepare_item_for_response( $item, $request ) {
		return $this->add_additional_fields_to_object( $item, $request );
	}

	/**
	 * Collection parameters of the transactions route.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array_merge(
			DateRange::args(),
			RestSupport::paging_args(),
			array(
				'search'         => RestSupport::search_arg( __( 'Order number to look for.', 'ohmylms' ) ),
				'payment_method' => array(
					'description' => __( 'Limit results to one payment method id.', 'ohmylms' ),
					'type'        => 'string',
					'maxLength'   => 64,
				),
				'orderby'        => array(
					'description' => __( 'Sort by attribute.', 'ohmylms' ),
					'type'        => 'string',
					'default'     => 'date',
					'enum'        => array_keys( Repository::TRANSACTION_ORDERBY ),
				),
				'order'          => RestSupport::order_arg(),
			)
		);
	}

	/**
	 * Collection parameters of the course report route.
	 *
	 * @return array
	 */
	private function get_course_params() {
		return array_merge(
			DateRange::args(),
			RestSupport::paging_args(),
			array(
				'search'  => RestSupport::search_arg( __( 'Limit results to courses whose title contains the string.', 'ohmylms' ) ),
				'orderby' => array(
					'description' => __( 'Sort by attribute.', 'ohmylms' ),
					'type'        => 'string',
					'default'     => 'net',
					'enum'        => array_keys( Repository::COURSE_ORDERBY ),
				),
				'order'   => RestSupport::order_arg(),
			)
		);
	}

	/**
	 * Get the earnings schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$summary   = RestSupport::summary_schema();
		$breakdown = static function ( $key ) {
			return array(
				'type'     => 'array',
				'context'  => array( 'view' ),
				'readonly' => true,
				'items'    => array(
					'type'       => 'object',
					'properties' => array(
						$key     => array( 'type' => 'string' ),
						'orders' => array( 'type' => 'integer' ),
						'gross'  => array( 'type' => 'number' ),
					),
				),
			);
		};

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'analytics-earnings',
			'type'       => 'object',
			'properties' => array(
				'generated_at'      => array(
					'description' => __( 'When the figures were computed (GMT).', 'ohmylms' ),
					'type'        => 'string',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'range'             => RestSupport::range_schema(),
				'currency'          => RestSupport::currency_schema(),
				'summary'           => array(
					'description' => __( 'Totals of the range and of the previous period.', 'ohmylms' ),
					'type'        => 'object',
					'context'     => array( 'view' ),
					'readonly'    => true,
					'properties'  => array(
						'current'  => $summary,
						'previous' => $summary,
					),
				),
				'series'            => RestSupport::series_schema(),
				'by_country'        => $breakdown( 'country' ),
				'by_payment_method' => $breakdown( 'id' ),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}
}
