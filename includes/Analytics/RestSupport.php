<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Shared REST helpers of the dashboard and analytics controllers.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Analytics;

use OhMyLMS\Rest\V1\AnalyticsController;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Permission check, error builders, pagination headers and schema fragments.
 *
 * @since 1.0.0
 */
final class RestSupport {

	/**
	 * Namespace of the routes.
	 */
	const NAMESPACE_ = 'ohmylms/v1';

	/**
	 * Check the one capability shared by every dashboard and analytics route.
	 *
	 * @return true|WP_Error
	 */
	public static function authorize() {
		if ( ! current_user_can( AnalyticsController::CAPABILITY ) ) {
			return new WP_Error( 'ohmylms_analytics_cannot_view', __( 'Sorry, you are not allowed to view analytics.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * Error for a failed database query.
	 *
	 * @return WP_Error
	 */
	public static function query_failed() {
		return new WP_Error( 'ohmylms_analytics_query_failed', __( 'The report could not be loaded.', 'ohmylms' ), array( 'status' => 500 ) );
	}

	/**
	 * Error for a page beyond the last one.
	 *
	 * @return WP_Error
	 */
	public static function invalid_page() {
		return new WP_Error( 'ohmylms_analytics_invalid_page', __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), array( 'status' => 400 ) );
	}

	/**
	 * Add X-WP-Total, X-WP-TotalPages and Link headers.
	 *
	 * @param WP_REST_Response $response Response.
	 * @param WP_REST_Request  $request  Request.
	 * @param int              $total    Total items.
	 * @param int              $per_page Page size.
	 * @param int              $page     Current page.
	 * @param string           $route    Route below the namespace.
	 * @return void
	 */
	public static function add_pagination_headers( WP_REST_Response $response, WP_REST_Request $request, $total, $per_page, $page, $route ) {
		$total_pages = (int) ceil( $total / max( 1, $per_page ) );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $total_pages );

		$base = add_query_arg( urlencode_deep( $request->get_query_params() ), rest_url( self::NAMESPACE_ . '/' . $route ) );
		if ( $page > 1 ) {
			$response->link_header( 'prev', add_query_arg( 'page', $page - 1, $base ) );
		}
		if ( $page < $total_pages ) {
			$response->link_header( 'next', add_query_arg( 'page', $page + 1, $base ) );
		}
	}

	/**
	 * Page and per_page arguments.
	 *
	 * @param int $default_per_page Default page size.
	 * @return array<string,array>
	 */
	public static function paging_args( $default_per_page = 20 ) {
		return array(
			'page'     => array(
				'description' => __( 'Current page of the collection.', 'ohmylms' ),
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
			),
			'per_page' => array(
				'description' => __( 'Maximum number of items per page.', 'ohmylms' ),
				'type'        => 'integer',
				'default'     => $default_per_page,
				'minimum'     => 1,
				'maximum'     => 100,
			),
		);
	}

	/**
	 * Order argument.
	 *
	 * @param string $default_order Default direction.
	 * @return array
	 */
	public static function order_arg( $default_order = 'desc' ) {
		return array(
			'description' => __( 'Sort direction.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => $default_order,
			'enum'        => array( 'asc', 'desc' ),
		);
	}

	/**
	 * Search argument.
	 *
	 * @param string $description Description.
	 * @return array
	 */
	public static function search_arg( $description ) {
		return array(
			'description' => $description,
			'type'        => 'string',
			'maxLength'   => 100,
		);
	}

	/**
	 * Schema of a money amount.
	 *
	 * @param string $description Description.
	 * @return array
	 */
	public static function money_schema( $description ) {
		return array(
			'description' => $description,
			'type'        => 'number',
			'context'     => array( 'view' ),
			'readonly'    => true,
		);
	}

	/**
	 * Schema of the `currency` member.
	 *
	 * @return array
	 */
	public static function currency_schema() {
		return array(
			'description' => __( 'Site currency.', 'ohmylms' ),
			'type'        => 'object',
			'context'     => array( 'view' ),
			'readonly'    => true,
			'properties'  => array(
				'code'     => array( 'type' => 'string' ),
				'symbol'   => array( 'type' => 'string' ),
				'decimals' => array( 'type' => 'integer' ),
				'position' => array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * Schema of the `range` member.
	 *
	 * @return array
	 */
	public static function range_schema() {
		return array(
			'description' => __( 'The resolved date range and its previous period.', 'ohmylms' ),
			'type'        => 'object',
			'context'     => array( 'view' ),
			'readonly'    => true,
			'properties'  => array(
				'preset'         => array(
					'type' => 'string',
					'enum' => DateRange::PRESETS,
				),
				'start'          => array( 'type' => 'string' ),
				'end'            => array( 'type' => 'string' ),
				'days'           => array( 'type' => 'integer' ),
				'granularity'    => array(
					'type' => 'string',
					'enum' => array( 'day', 'month' ),
				),
				'previous_start' => array( 'type' => 'string' ),
				'previous_end'   => array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * Schema of one period summary.
	 *
	 * @return array
	 */
	public static function summary_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'orders'      => array( 'type' => 'integer' ),
				'gross'       => array( 'type' => 'number' ),
				'refunds'     => array( 'type' => 'number' ),
				'net'         => array( 'type' => 'number' ),
				'sales'       => array( 'type' => 'integer' ),
				'enrollments' => array( 'type' => 'integer' ),
			),
		);
	}

	/**
	 * Schema of the chart series.
	 *
	 * @return array
	 */
	public static function series_schema() {
		return array(
			'description' => __( 'One point per day or month of the range, empty buckets included.', 'ohmylms' ),
			'type'        => 'array',
			'context'     => array( 'view' ),
			'readonly'    => true,
			'items'       => array(
				'type'       => 'object',
				'properties' => array(
					'date'    => array( 'type' => 'string' ),
					'orders'  => array( 'type' => 'integer' ),
					'gross'   => array( 'type' => 'number' ),
					'refunds' => array( 'type' => 'number' ),
					'net'     => array( 'type' => 'number' ),
				),
			),
		);
	}

	/**
	 * Schema of a course row of the course report.
	 *
	 * @return array
	 */
	public static function course_row_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'            => array( 'type' => 'integer' ),
				'title'         => array( 'type' => 'string' ),
				'status'        => array( 'type' => 'string' ),
				'date'          => array( 'type' => 'string' ),
				'sales'         => array( 'type' => 'integer' ),
				'gross'         => array( 'type' => 'number' ),
				'refunds'       => array( 'type' => 'number' ),
				'net'           => array( 'type' => 'number' ),
				'enrollments'   => array( 'type' => 'integer' ),
				'completed'     => array( 'type' => 'integer' ),
				'permalink'     => array( 'type' => 'string' ),
				'thumbnail_url' => array( 'type' => array( 'string', 'null' ) ),
			),
		);
	}

	/**
	 * Add permalink and thumbnail to repository course rows (post caches are primed once).
	 *
	 * @param array[] $rows Rows from Repository::courses().
	 * @return array[]
	 */
	public static function decorate_courses( array $rows ) {
		$ids = array_map( 'intval', wp_list_pluck( $rows, 'id' ) );
		if ( $ids ) {
			_prime_post_caches( $ids, false, true );
		}
		foreach ( $rows as &$row ) {
			$thumb                = get_the_post_thumbnail_url( $row['id'], 'medium' );
			$row['permalink']     = 'publish' === $row['status'] ? (string) get_permalink( $row['id'] ) : '';
			$row['thumbnail_url'] = $thumb ? (string) $thumb : null;
		}
		unset( $row );
		return $rows;
	}
}
