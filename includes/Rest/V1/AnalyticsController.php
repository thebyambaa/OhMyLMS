<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;

/**
 * Plugin analytics controller implementation.
 */
class AnalyticsController extends RestController {

	/**
	 * Base.
	 *
	 * @var mixed
	 */
	protected $base = 'analytics';

	/**
	 * Check permission
	 *
	 * @param mixed $request REST request.
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'edit_posts' );
	}


	/**
	 * Register analytics api routes
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/course/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_single_course_analytics' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/earnings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_earning_analytics' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Get single course analytics.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * .
	 * @return \WP_REST_Response|\WP_Error The response object or error object.
	 *
	 * @since 1.0.0
	 */
	public function get_single_course_analytics( $request ) {
		$id   = isset( $request['id'] ) ? absint( $request['id'] ) : 0;
		$post = get_post( $id );

		if ( empty( $id ) || empty( $post ) || OHMYLMS_COURSE_CPT !== $post->post_type ) {
			return new WP_Error( 'ohmylms_rest_invalid_course_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		$data_type = sanitize_key( $request->get_param( 'data_type' ) );
		if ( empty( $data_type ) ) {
			$data_type = 'all';
		}

		$filter  = sanitize_text_field( $request->get_param( 'filter' ) );
		$filter  = $filter ? $filter : 'all';
		$sort_by = sanitize_text_field( $request->get_param( 'sort_by' ) );

		$start_date = sanitize_text_field( $request->get_param( 'start_date' ) );
		$end_date   = sanitize_text_field( $request->get_param( 'end_date' ) );

		$search          = sanitize_text_field( $request->get_param( 'search' ) );
		$completion_type = sanitize_text_field( $request->get_param( 'completion_type' ) );

		$data = $this->get_course_data( $id, $data_type, $filter, $sort_by, $start_date, $end_date, $search, $completion_type );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		do_action( 'ohmylms_rest_analytics_course_data', $data, $id, $data_type, $filter, $sort_by, $start_date, $end_date, $search, $completion_type );
		return $this->prepare_item_for_response( $data, $request );
	}


	/**
	 * Get all course data
	 *
	 * @param int    $course_id Course identifier.
	 * @param string $data_type Data type.
	 * @param string $filter Filter.
	 * @param mixed  $sort_by Sort by.
	 * @param date   $start_date Start date.
	 * @param date   $end_date End date.
	 * .
	 * @param mixed  $search Search.
	 * @param mixed  $completion_type Completion type.
	 * @return \WP_REST_Response|\WP_Error The response object or error object.
	 *
	 * @since 1.0.0
	 */
	private function get_course_data( $course_id, $data_type, $filter, $sort_by = null, $start_date = null, $end_date = null, $search = null, $completion_type = null ) {
		$course = ohmylms_get_course( $course_id );

		if ( ! $course ) {
			return new WP_Error( 'ohmylms_rest_invalid_course_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		$content_data = array(
			'chapters'             => count( $course->get_chapters() ),
			'lessons'              => intval( $course->get_lessons_count() ),
			'quizzes'              => intval( $course->get_quiz_count() ),
			'assignments'          => intval( $course->get_assignment_count() ),
			'total_enrollment'     => $course->get_total_enrolled_users(),
			'completed_students'   => $course->get_total_completed_users(),
			'in_progress_students' => $course->get_total_in_progress_users(),
			'ratings'              => $course->get_rating_count(),
		);

		$response       = array(
			'course_id'    => $course_id,
			'title'        => $course->get_name(),
			'content_data' => $content_data,
		);
		$only_completed = null;

		if ( 'completed' === $completion_type ) {
			$only_completed = true;
		} elseif ( 'not_completed' === $completion_type ) {
			$only_completed = false;
		}

		if ( 'all' === $data_type ) {
			$earning              = $this->get_course_earning_data( $course_id, $filter, $start_date, $end_date );
			$students             = $this->get_all_students( $course_id, $filter, $sort_by, $start_date, $end_date, $search, $only_completed );
			$response['earning']  = $earning;
			$response['students'] = $students;
		}

		if ( 'earning' === $data_type ) {
			$earning             = $this->get_course_earning_data( $course_id, $filter, $start_date, $end_date );
			$response            = array();
			$response['earning'] = $earning;
		}

		if ( 'student' === $data_type ) {
			$students             = $this->get_all_students( $course_id, $filter, $sort_by, $start_date, $end_date, $search, $only_completed );
			$response             = array();
			$response['students'] = $students;
		}

		return $response;
	}


	/**
	 * Get all earning data of a course
	 *
	 * @param int    $course_id Course identifier.
	 * @param string $filter Filter.
	 * @param date   $start_date Start date.
	 * @param date   $end_date End date.
	 * .
	 * @return \WP_REST_Response|\WP_Error The response object or error object.
	 *
	 * @since 1.0.0
	 */
	private function get_course_earning_data( $course_id, $filter, $start_date = null, $end_date = null ) {
		global $wpdb;

		// Convert filters into actual date ranges.
		switch ( $filter ) {
			case 'last_30_days':
				$start_date = ( new \DateTime( '-30 days' ) )->format( 'Y-m-d' );
				$end_date   = ( new \DateTime() )->format( 'Y-m-d' );
				break;
			case 'current_month':
				$start_date = ( new \DateTime( 'first day of this month' ) )->format( 'Y-m-d' );
				$end_date   = ( new \DateTime( 'last day of this month' ) )->format( 'Y-m-d' );
				break;
			case 'previous_month':
				$start_date = ( new \DateTime( 'first day of last month' ) )->format( 'Y-m-d' );
				$end_date   = ( new \DateTime( 'last day of last month' ) )->format( 'Y-m-d' );
				break;
			case 'current_year':
				$start_date = ( new \DateTime( 'first day of January this year' ) )->format( 'Y-m-d' );
				$end_date   = ( new \DateTime( 'last day of December this year' ) )->format( 'Y-m-d' );
				break;
			case 'last_12_months':
				$start_date = ( new \DateTime( '-12 months' ) )->format( 'Y-m-d' );
				$end_date   = ( new \DateTime() )->format( 'Y-m-d' );
				break;
			case 'custom':
				// Ensure both dates are provided.
				if ( ! $start_date || ! $end_date ) {
					return new \WP_Error( 'invalid_date', 'Start and End date are required for custom filter' );
				}
				break;
			default:
				$start_date = null;
				$end_date   = null;
				break;
		}

		// Base query filtered by course_id in order item meta.
		$query = "
            SELECT
                p.ID as order_id,
                p.post_status as order_status,
                p.post_date,
                line_total.meta_value as line_total,
                pm.meta_value as order_total
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
            INNER JOIN {$wpdb->prefix}ohmylms_order_items oi ON p.ID = oi.order_id
            INNER JOIN {$wpdb->prefix}ohmylms_order_itemmeta line_total ON oi.order_item_id = line_total.order_item_id AND line_total.meta_key = '_line_total'
            INNER JOIN {$wpdb->prefix}ohmylms_order_itemmeta course_meta ON oi.order_item_id = course_meta.order_item_id AND course_meta.meta_key = '_course_id'
            WHERE
                p.post_type = 'ohmylms-order'
                AND p.post_status IN ('ohmylms-completed', 'ohmylms-refunded')
				AND pm.meta_key = '_order_total'
                AND course_meta.meta_value = %d

        ";
		if ( $start_date && $end_date ) {
			$query .= ' AND DATE(p.post_date) BETWEEN %s AND %s';
			$query  = $wpdb->prepare( $query, $course_id, $start_date, $end_date );
		} else {
			$query = $wpdb->prepare( $query, $course_id );
		}

		$results = $wpdb->get_results( $query, ARRAY_A );

		// Setup vars.
		$total_earning     = 0;
		$total_net_earning = 0;
		$total_refund      = 0;

		// Determine grouping.
		$group_by = 'daily';
		$start    = new \DateTime( $start_date );
		$end      = new \DateTime( $end_date );
		$interval = $start->diff( $end );

		if ( 1 < $interval->m || 1 <= $interval->y ) {
			$group_by = 'monthly';
		}
		if ( 1 <= $interval->y && $interval->m + $interval->y * 12 > 12 ) {
			$group_by = 'yearly';
		}
		if ( 'current_year' === $filter || 'last_12_months' === $filter ) {
			$group_by = 'monthly';
		}

		// Fill in data.
		foreach ( $results as $result ) {
			$line_total     = floatval( $result['order_total'] );
			$total_earning += $line_total;
			if ( 'ohmylms-completed' === $result['order_status'] ) {
				$total_net_earning += $line_total;

				$order = ohmylms_ecommerce_get_order( $result['order_id'] );
				foreach ( $order->get_refunds() as $single_refund ) {
					$refund_id          = $single_refund->ID;
					$total_refund      += ohmylms_format_decimal( get_post_meta( $refund_id, '_refund_amount', true ), ohmylms_get_price_decimals() );
					$total_net_earning -= ohmylms_format_decimal( get_post_meta( $refund_id, '_refund_amount', true ), ohmylms_get_price_decimals() );
				}
			}
			if ( 'ohmylms-refunded' === $result['order_status'] ) {
				$total_refund += $line_total;
			}
		}

		$graph_data = $this->get_course_earning_graph_data( $course_id, $filter );
		return array(
			'total_earning' => $total_earning,
			'total_refund'  => $total_refund,
			'net_amount'    => $total_net_earning,
			'currency'      => html_entity_decode( get_ohmylms_currency_symbol( get_ohmylms_currency() ) ),
			'currency_pos'  => get_ohmylms_currency_position(),
			'graph_data'    => $graph_data,
		);
	}


	/**
	 * Get course earning graph data for Analytics controller.
	 *
	 * @param mixed $course_id Course identifier.
	 * @param mixed $filter Filter.
	 * @param mixed $start_date Start date.
	 * @param mixed $end_date End date.
	 */
	private function get_course_earning_graph_data( $course_id, $filter, $start_date = null, $end_date = null ) {
		global $wpdb;

		// Convert filters into actual date ranges.
		switch ( $filter ) {
			case 'last_30_days':
				$start_date = ( new \DateTime( '-30 days' ) )->format( 'Y-m-d' );
				$end_date   = ( new \DateTime() )->format( 'Y-m-d' );
				break;
			case 'current_month':
				$start_date = ( new \DateTime( 'first day of this month' ) )->format( 'Y-m-d' );
				$end_date   = ( new \DateTime( 'last day of this month' ) )->format( 'Y-m-d' );
				break;
			case 'previous_month':
				$start_date = ( new \DateTime( 'first day of last month' ) )->format( 'Y-m-d' );
				$end_date   = ( new \DateTime( 'last day of last month' ) )->format( 'Y-m-d' );
				break;
			case 'current_year':
				$start_date = ( new \DateTime( 'first day of January this year' ) )->format( 'Y-m-d' );
				$end_date   = ( new \DateTime( 'last day of December this year' ) )->format( 'Y-m-d' );
				break;
			case 'last_12_months':
				$start_date = ( new \DateTime( '-12 months' ) )->format( 'Y-m-d' );
				$end_date   = ( new \DateTime() )->format( 'Y-m-d' );
				break;
			case 'custom':
				// Ensure both dates are provided.
				if ( ! $start_date || ! $end_date ) {
					return new \WP_Error( 'invalid_date', 'Start and End date are required for custom filter' );
				}
				break;
			default:
				$start_date = ( new \DateTime( 'first day of January this year' ) )->format( 'Y-m-d' );
				$end_date   = ( new \DateTime( 'last day of December this year' ) )->format( 'Y-m-d' );
				break;
		}

		// Base query filtered by course_id in order item meta.
		$query = "
            SELECT
                p.ID as order_id,
                p.post_status as order_status,
                p.post_date,
                line_total.meta_value as line_total
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->prefix}ohmylms_order_items oi ON p.ID = oi.order_id
            INNER JOIN {$wpdb->prefix}ohmylms_order_itemmeta line_total ON oi.order_item_id = line_total.order_item_id AND line_total.meta_key = '_line_total'
            INNER JOIN {$wpdb->prefix}ohmylms_order_itemmeta course_meta ON oi.order_item_id = course_meta.order_item_id AND course_meta.meta_key = '_course_id'
            WHERE
                p.post_type = 'ohmylms-order'
                AND p.post_status IN ('ohmylms-completed', 'ohmylms-refunded')
                AND course_meta.meta_value = %d

        ";
		if ( $start_date && $end_date ) {
			$query .= ' AND DATE(p.post_date) BETWEEN %s AND %s';
			$query  = $wpdb->prepare( $query, $course_id, $start_date, $end_date );
		} else {
			$query = $wpdb->prepare( $query, $course_id );
		}

		$results = $wpdb->get_results( $query, ARRAY_A );

		$graph_data = array();

		// Determine grouping.
		$group_by = 'daily';
		$start    = new \DateTime( $start_date );
		$end      = new \DateTime( $end_date );
		$interval = $start->diff( $end );

		if ( 1 < $interval->m || 1 <= $interval->y ) {
			$group_by = 'monthly';
		}
		if ( 1 <= $interval->y && $interval->m + $interval->y * 12 > 12 ) {
			$group_by = 'yearly';
		}
		if ( 'current_year' === $filter || 'last_12_months' === $filter ) {
			$group_by = 'monthly';
		}

		// Create the period.
		$period_interval = 'daily' === $group_by ? 'P1D' : ( 'monthly' === $group_by ? 'P1M' : 'P1Y' );
		$date_period     = new \DatePeriod( $start, new \DateInterval( $period_interval ), $end->modify( '+1 day' ) );

		foreach ( $date_period as $date ) {
			$key                = $date->format( 'daily' === $group_by ? 'Y-m-d' : ( 'monthly' === $group_by ? 'Y-m' : 'Y' ) );
			$graph_data[ $key ] = array(
				'earning' => 0,
				'net'     => 0,
				'refund'  => 0,
			);
		}

		// Fill in data.
		foreach ( $results as $result ) {
			$line_total = floatval( $result['line_total'] );

			$date_key = date( 'daily' === $group_by ? 'Y-m-d' : ( 'monthly' === $group_by ? 'Y-m' : 'Y' ), strtotime( $result['post_date'] ) );

			if ( 'ohmylms-completed' === $result['order_status'] ) {

				$graph_data[ $date_key ]['net'] += $line_total;

				$order = ohmylms_ecommerce_get_order( $result['order_id'] );
				foreach ( $order->get_refunds() as $single_refund ) {
					$refund_id                          = $single_refund->ID;
					$graph_data[ $date_key ]['refund'] += ohmylms_format_decimal( get_post_meta( $refund_id, '_refund_amount', true ), ohmylms_get_price_decimals() );
					$graph_data[ $date_key ]['net']    -= ohmylms_format_decimal( get_post_meta( $refund_id, '_refund_amount', true ), ohmylms_get_price_decimals() );
				}
			}
			if ( 'ohmylms-refunded' === $result['order_status'] ) {

				$graph_data[ $date_key ]['refund'] += $line_total;
			}

			$graph_data[ $date_key ]['earning'] += $line_total;
		}

		ksort( $graph_data );

		return $graph_data;
	}


	/**
	 * Get all student data of a course
	 *
	 * @param int    $course_id Course identifier.
	 * @param string $filter Filter.
	 * @param mixed  $sort_by Sort by.
	 * @param date   $start_date Start date.
	 * @param date   $end_date End date.
	 * .
	 * @param mixed  $search Search.
	 * @param mixed  $only_completed Only completed.
	 * @return \WP_REST_Response|\WP_Error The response object or error object.
	 *
	 * @since 1.0.0
	 */
	public function get_all_students( $course_id, $filter, $sort_by, $start_date = null, $end_date = null, $search = null, $only_completed = null ) {
		if ( ! ohmylms_is_pro() ) {
			return array();
		}
		$course = ohmylms_get_course( $course_id );

		if ( ! $course ) {
			return new WP_Error( 'ohmylms_rest_invalid_course_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		$students = $course->get_students( $filter, $sort_by, $start_date, $end_date, $search, $only_completed );

		if ( empty( $students ) ) {
			return array();
		}

		return $students;
	}


	/**
	 * Get earning analytics
	 *
	 * @param \WP_REST_Request $request The request object.
	 * .
	 * @return \WP_REST_Response|\WP_Error The response object or error object.
	 *
	 * @since 1.0.0
	 */
	public function get_earning_analytics( $request ) {
		if ( ! ohmylms_is_pro() ) {
			return new WP_Error( 'ohmylms_rest_invalid_course_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		global $wpdb;

		$filter         = sanitize_text_field( $request->get_param( 'filter' ) );
		$start_date     = sanitize_text_field( $request->get_param( 'start_date' ) );
		$end_date       = sanitize_text_field( $request->get_param( 'end_date' ) );
		$payment_method = sanitize_text_field( $request->get_param( 'payment_method' ) );
		$type           = sanitize_key( $request->get_param( 'type' ) );
		$sort_by        = sanitize_key( $request->get_param( 'sort_by' ) );
		$order          = sanitize_key( $request->get_param( 'order' ) );
		$search         = sanitize_text_field( $request->get_param( 'search' ) );

		// Default fallback if empty.
		$type    = ! empty( $type ) ? $type : 'all';
		$sort_by = ! empty( $sort_by ) ? $sort_by : 'date';
		$order   = ! empty( $order ) ? strtoupper( $order ) : 'DESC';

		// Validate order value (only ASC or DESC allowed).
		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}

		// Validate date formats (optional stricter check).
		if ( ! empty( $start_date ) && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) ) {
			return new WP_Error( 'ohmylms_rest_invalid_start_date', __( 'Invalid start date format.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		if ( ! empty( $end_date ) && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_date ) ) {
			return new WP_Error( 'ohmylms_rest_invalid_end_date', __( 'Invalid end date format.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		// Get range and grouping.
		$range      = $this->get_date_range_from_filter( $filter, $start_date, $end_date );
		$start_date = $range['start_date'];
		$end_date   = $range['end_date'];
		$group_by   = $range['group_by'];

		// Query Preparation.
		if ( 'all' !== $filter ) {
			$query = "SELECT 
				p.ID as order_id, 
				pm.meta_value as order_total, 
				p.post_status as order_status, 
				p.post_date
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
			WHERE p.post_type = 'ohmylms-order'
				AND p.post_status IN ('ohmylms-completed', 'ohmylms-refunded')
				AND pm.meta_key = '_order_total'
				AND p.post_date BETWEEN %s AND %s";

			$prepared_query = $wpdb->prepare( $query, $start_date, $end_date );
		} else {
			$query = "SELECT 
				p.ID as order_id, 
				pm.meta_value as order_total, 
				p.post_status as order_status, 
				p.post_date
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
			WHERE p.post_type = 'ohmylms-order'
				AND p.post_status IN ('ohmylms-completed', 'ohmylms-refunded')
				AND pm.meta_key = '_order_total'";

			$prepared_query = $query; // No dynamic data.
		}

		$results = $wpdb->get_results( $prepared_query, ARRAY_A );

		// Initialize.
		$total_earning     = 0;
		$total_net_earning = 0;
		$total_refund      = 0;
		$graph_data        = array();

		// Setup graph buckets.
		$interval = new \DateInterval(
			'daily' === $group_by ? 'P1D' : ( 'monthly' === $group_by ? 'P1M' : 'P1Y' )
		);
		$start    = new \DateTime( $start_date );
		$end      = ( new \DateTime( $end_date ) )->modify( '+1 day' );
		$period   = new \DatePeriod( $start, $interval, $end );

		foreach ( $period as $date ) {
			$key                = $date->format(
				'daily' === $group_by ? 'Y-m-d' : ( 'monthly' === $group_by ? 'Y-m' : 'Y' )
			);
			$graph_data[ $key ] = array(
				'earning' => 0,
				'net'     => 0,
				'refund'  => 0,
			);
		}

		// Parse Results.
		foreach ( $results as $result ) {
			$amount   = floatval( $result['order_total'] );
			$date_key = date(
				'daily' === $group_by ? 'Y-m-d' : ( 'monthly' === $group_by ? 'Y-m' : 'Y' ),
				strtotime( $result['post_date'] )
			);

			if ( 'ohmylms-completed' === $result['order_status'] ) {
				$total_net_earning              += $amount;
				$graph_data[ $date_key ]['net'] += $amount;
				$single_order                    = ohmylms_ecommerce_get_order( $result['order_id'] );

				foreach ( $single_order->get_refunds() as $single_refund ) {
					$refund_id                          = $single_refund->ID;
					$total_refund                      += ohmylms_format_decimal( get_post_meta( $refund_id, '_refund_amount', true ), ohmylms_get_price_decimals() );
					$graph_data[ $date_key ]['net']    -= ohmylms_format_decimal( get_post_meta( $refund_id, '_refund_amount', true ), ohmylms_get_price_decimals() );
					$total_net_earning                 -= ohmylms_format_decimal( get_post_meta( $refund_id, '_refund_amount', true ), ohmylms_get_price_decimals() );
					$graph_data[ $date_key ]['refund'] += ohmylms_format_decimal( get_post_meta( $refund_id, '_refund_amount', true ), ohmylms_get_price_decimals() );
				}
			}

			if ( 'ohmylms-refunded' === $result['order_status'] ) {
				$total_refund                      += $amount;
				$graph_data[ $date_key ]['refund'] += $amount;
			}

			$total_earning                      += $amount;
			$graph_data[ $date_key ]['earning'] += $amount;
		}

		ksort( $graph_data );

		// Previous period calculations.
		$previous_range      = $this->get_previous_date_range( $start_date, $end_date );
		$previous_start_date = $previous_range['start_date'];
		$previous_end_date   = $previous_range['end_date'];

		$previous_query   = $wpdb->prepare( $query, $previous_start_date, $previous_end_date );
		$previous_results = $wpdb->get_results( $previous_query, ARRAY_A );

		$previous_total_earning     = 0;
		$previous_total_net_earning = 0;
		$previous_total_refund      = 0;

		foreach ( $previous_results as $result ) {
			$amount = floatval( $result['order_total'] );

			if ( 'ohmylms-completed' === $result['order_status'] ) {
				$previous_total_net_earning += $amount;
			}

			if ( 'ohmylms-refunded' === $result['order_status'] ) {
				$previous_total_refund += $amount;
			}

			$previous_total_earning += $amount;
		}

		$calculate_growth = function ( $current, $previous ) {
			if ( 0 >= $previous ) {
				return 0 < $current ? 100 : 0;
			}
			return round( ( ( $current - $previous ) / $previous ) * 100, 2 );
		};

		$growth = array(
			'total_revenue' => $calculate_growth( $total_earning, $previous_total_earning ),
			'total_refund'  => $calculate_growth( $total_refund, $previous_total_refund ),
			'net_amount'    => $calculate_growth( $total_net_earning, $previous_total_net_earning ),
		);

		$params = array(
			'limit'          => 10,
			'date_filter'    => $filter,
			'start_date'     => $start_date,
			'end_date'       => $end_date,
			'payment_method' => $payment_method,
			'search'         => $search,
			'type'           => $type,
			'sort_by'        => $sort_by,
			'order'          => $order,
			'status'         => 'all',
		);

		$transactions = $this->get_order_transactions_data( $params );

		return array(
			'currency'               => html_entity_decode( get_ohmylms_currency_symbol( get_ohmylms_currency() ) ),
			'currency_pos'           => get_ohmylms_currency_position(),
			'earning_graph'          => array(
				'total_revenue' => $total_earning,
				'total_refund'  => $total_refund,
				'net_amount'    => $total_net_earning,
				'growth'        => $growth,
				'graph_data'    => $graph_data,
			),
			'transactions'           => $transactions,
			'count_unchecked_orders' => $this->count_unchecked_orders(),
			'order_by_country'       => $this->get_orders_grouped_by_country_via_sql(),
		);
	}



	/**
	 * Get date range from filter
	 *
	 * @param string $filter Filter.
	 * @param string $start Start.
	 * @param string $end End.
	 * .
	 * @return array
	 *
	 * @since 1.0.0
	 */
	public function get_date_range_from_filter( $filter, $start = '', $end = '' ) {
		$today      = new \DateTime();
		$start_date = $end_date = $group_by = '';

		switch ( $filter ) {
			case 'last_30_days':
				$start_date = ( clone $today )->modify( '-30 days' )->format( 'Y-m-d H:i:s' );
				$end_date   = $today->setTime( 23, 59, 59 )->format( 'Y-m-d H:i:s' );
				$group_by   = 'daily';
				break;

			case 'current_month':
				$start_date = ( new \DateTime( 'first day of this month' ) )->format( 'Y-m-d H:i:s' );
				$end_date   = ( new \DateTime( 'last day of this month' ) )->setTime( 23, 59, 59 )->format( 'Y-m-d H:i:s' );
				$group_by   = 'daily';
				break;

			case 'previous_month':
				$start_date = ( new \DateTime( 'first day of last month' ) )->format( 'Y-m-d H:i:s' );
				$end_date   = ( new \DateTime( 'last day of last month' ) )->setTime( 23, 59, 59 )->format( 'Y-m-d H:i:s' );
				$group_by   = 'daily';
				break;

			case 'current_year':
				$start_date = ( new \DateTime( 'first day of January this year' ) )->format( 'Y-m-d H:i:s' );
				$end_date   = ( new \DateTime( 'last day of December this year' ) )->setTime( 23, 59, 59 )->format( 'Y-m-d H:i:s' );
				$group_by   = 'monthly';
				break;

			case 'last_12_months':
				$start_date = ( clone $today )->modify( '-12 months' )->format( 'Y-m-d H:i:s' );
				$end_date   = $today->setTime( 23, 59, 59 )->format( 'Y-m-d H:i:s' );
				$group_by   = 'monthly';
				break;

			case 'custom':
				$start_date = $start ? $start : ( clone $today )->modify( '-30 days' )->format( 'Y-m-d H:i:s' );
				$end_date   = $end ? $end : $today->setTime( 23, 59, 59 )->format( 'Y-m-d H:i:s' );

				$start_obj = new \DateTime( $start_date );
				$end_obj   = new \DateTime( $end_date );
				$diff      = $start_obj->diff( $end_obj );

				if ( 1 <= $diff->y && ( $diff->m + $diff->y * 12 ) > 12 ) {
					$group_by = 'yearly';
				} elseif ( 1 < $diff->m || 1 <= $diff->y ) {
					$group_by = 'monthly';
				} else {
					$group_by = 'daily';
				}
				break;

			default:
				// Fallback to last 30 days.
				$start_date = ( clone $today )->modify( '-30 days' )->format( 'Y-m-d H:i:s' );
				$end_date   = $today->format( 'Y-m-d H:i:s' );
				$group_by   = 'daily';
				break;
		}

		return array(
			'start_date' => $start_date,
			'end_date'   => $end_date,
			'group_by'   => $group_by,
		);
	}


	/**
	 * Get previous date range
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * .
	 * @return array
	 *
	 * @since 1.0.0
	 */
	private function get_previous_date_range( $start_date, $end_date ) {
		$start = new \DateTime( $start_date );
		$end   = new \DateTime( $end_date );

		$diff_days = $start->diff( $end )->days + 1;

		$prev_end   = ( clone $start )->modify( '-1 day' );
		$prev_start = ( clone $prev_end )->modify( "-$diff_days days" )->modify( '+1 day' );

		return array(
			'start_date' => $prev_start->format( 'Y-m-d' ),
			'end_date'   => $prev_end->format( 'Y-m-d' ),
		);
	}


	/**
	 * Get order transactions data
	 *
	 * @param array $params Request parameters.
	 * .
	 * @return array
	 *
	 * @since 1.0.0
	 */
	private function get_order_transactions_data( $params = array() ) {
		$allowed_orderby = array( 'date', 'ID', 'title', 'meta_value', 'meta_value_num', 'modified' );
		$orderby         = ( isset( $params['sort_by'] ) && in_array( $params['sort_by'], $allowed_orderby, true ) )
			? $params['sort_by']
			: 'date';
		$order           = ( isset( $params['order'] ) && in_array( strtoupper( $params['order'] ), array( 'ASC', 'DESC' ), true ) )
			? strtoupper( $params['order'] )
			: 'DESC';

		$args = array(
			'post_type'      => 'ohmylms-order',
			'post_status'    => 'any',
			'posts_per_page' => isset( $params['limit'] ) ? intval( $params['limit'] ) : -1,
			'orderby'        => $orderby,
			'order'          => $order,
			'meta_query'     => array(),
			'date_query'     => array(),
		);

		$today = current_time( 'Y-m-d' );
		if ( ! empty( $params['date_filter'] ) ) {
			switch ( $params['date_filter'] ) {
				case 'last_30_days':
					$args['date_query'][] = array(
						'after'     => date( 'Y-m-d', strtotime( '-30 days' ) ),
						'before'    => $today,
						'inclusive' => true,
					);
					break;
				case 'current_month':
					$args['date_query'][] = array(
						'after'     => date( 'Y-m-01' ),
						'before'    => $today,
						'inclusive' => true,
					);
					break;
				case 'previous_month':
					$args['date_query'][] = array(
						'after'     => date( 'Y-m-01', strtotime( 'first day of last month' ) ),
						'before'    => date( 'Y-m-t', strtotime( 'last month' ) ),
						'inclusive' => true,
					);
					break;
				case 'current_year':
					$args['date_query'][] = array(
						'after'     => date( 'Y-01-01' ),
						'before'    => $today,
						'inclusive' => true,
					);
					break;
				case 'last_12_months':
					$args['date_query'][] = array(
						'after'     => date( 'Y-m-d', strtotime( '-12 months' ) ),
						'before'    => $today,
						'inclusive' => true,
					);
					break;
			}
		}

		if ( ! empty( $params['start_date'] ) && ! empty( $params['end_date'] ) ) {
			$args['date_query'][] = array(
				'after'     => sanitize_text_field( $params['start_date'] ),
				'before'    => sanitize_text_field( $params['end_date'] ),
				'inclusive' => true,
			);
		}

		if ( ! empty( $params['payment_method'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_payment_method',
				'value'   => sanitize_text_field( $params['payment_method'] ),
				'compare' => '=',
			);
		}

		$query   = new \WP_Query( $args );
		$results = array();

		foreach ( $query->posts as $post ) {
			$order_id = $post->ID;
			$order    = ohmylms_ecommerce_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}
			$order_total = $order->get_total();
			$items       = array();
			$type        = 'course';
			foreach ( $order->get_refunds() as $single_refund ) {
				$refund_id    = $single_refund->ID;
				$order_total -= ohmylms_format_decimal( get_post_meta( $refund_id, '_refund_amount', true ), ohmylms_get_price_decimals() );
			}

			if ( $order->get_status() !== 'completed' ) {
				continue;
			}

			foreach ( $order->get_items() as $item_id => $item ) {

				$post_type = get_post_type( $item->get_course_id() );
				if ( OHMYLMS_COURSE_CPT === $post_type ) {
					$course = $item->get_course();
					$type   = 'course';
				} elseif ( OHMYLMS_MEMBERSHIP_CPT === $post_type ) {
					$course = $item->get_membership();
					$type   = 'membership';
				} else {
					$course = $item->get_course();
				}

				if ( is_object( $course ) ) {
					$course_id = $item->get_course_id();
				}
				if ( $course ) {
					$items[] = array(
						'course_id'   => $course_id,
						'course_name' => $course->get_name(),
					);
				}
			}

			if ( 'all' !== $params['type'] && $params['type'] !== $type ) {
				continue;
			}

			$results[] = array(
				'date'        => get_the_date( 'Y-m-d H:i:s', $post ),
				'order_id'    => $order_id,
				'order_total' => floatval( $order_total ),
				'order_items' => $items,
				'type'        => $type,
			);
		}

		return $results;
	}


	/**
	 * Count unchecked orders
	 *
	 * @return int
	 *
	 * @since 1.0.0
	 */
	private function count_unchecked_orders() {
		$args = array(
			'post_type'      => 'ohmylms-order',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => '_is_open',
					'compare' => 'NOT EXISTS',
				),
			),
		);

		$query            = new \WP_Query( $args );
		$unchecked_orders = $query->found_posts;
		return $unchecked_orders;
	}

	/**
	 * Get orders grouped by country via SQL
	 *
	 * @return array
	 *
	 * @since 1.0.0
	 */
	private function get_orders_grouped_by_country_via_sql() {
		global $wpdb;

		$posts_table    = $wpdb->posts;
		$postmeta_table = $wpdb->postmeta;

		$sql = "
            SELECT
                pm_country.meta_value AS country,
                SUM(CAST(pm_total.meta_value AS DECIMAL(10,2))) AS total_order_amount
            FROM {$posts_table} p
            INNER JOIN {$postmeta_table} pm_country
                ON p.ID = pm_country.post_id AND pm_country.meta_key = %s
            INNER JOIN {$postmeta_table} pm_total
                ON p.ID = pm_total.post_id AND pm_total.meta_key = %s
            WHERE p.post_type = %s
            AND p.post_status != %s
			AND p.post_status IN ('ohmylms-completed')
            GROUP BY pm_country.meta_value
            ORDER BY total_order_amount DESC
        ";

		$prepared_sql = $wpdb->prepare(
			$sql,
			'_country',
			'_order_total',
			'ohmylms-order',
			'trash'
		);

		$results = $wpdb->get_results( $prepared_sql );

		$grouped_totals = array();

		foreach ( $results as $row ) {
			$grouped_totals[ $row->country ] = (float) $row->total_order_amount;
		}

		return $grouped_totals;
	}


	/**
	 * Prepare a single item for response.
	 *
	 * @param array            $item The item array.
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response The response object.
	 *
	 * @since 1.0.0
	 */
	public function prepare_item_for_response( $item, $request ) {
		$data     = $this->add_additional_fields_to_object( $item, $request );
		$response = rest_ensure_response( $data );
		return $response;
	}


	/**
	 * Check permissions for updating items.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return bool True if the current user has permission, false otherwise.
	 *
	 * @since 1.0.0
	 */
	public function update_items_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}
}
