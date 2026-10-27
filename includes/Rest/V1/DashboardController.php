<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * DashboardController class.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Admin\Settings\AdminSettings;
use WP_Query;

/**
 * DashboardController class.
 *
 * Handles REST API endpoints for settings.
 *
 * @since 1.0.0
 */
class DashboardController extends RestController {

	/**
	 * The base route for dashboard endpoints.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $base = 'dashboard';

	/**
	 * Register the routes for the dashboard endpoints.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/courses',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_courses' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}



	/**
	 * Get a single item.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error The response or error object.
	 *
	 * @since 1.0.0
	 */
	public function get_item( $request ) {
		// Get filters.
		$filter     = $request->get_param( 'filter' ); // 'monthly', 'weekly', 'yearly', 'custom'
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );
		$earnings   = $this->get_data( $filter, $start_date, $end_date );
		return rest_ensure_response( $earnings );
	}


	/**
	 * Retrieves and processes order data for a given filter and date range.
	 *
	 * This function calculates the total earnings, net earnings, and refunds for orders
	 * based on the filter ('daily', 'monthly', 'yearly', or 'custom') and the provided
	 * date range. It also generates a graph data structure that breaks down the earnings,
	 * net earnings, and refunds for each time period (day, month, or year).
	 * Additionally, it fetches data about courses, including the total number of students,
	 * courses, and reviews within the specified date range.
	 *
	 * @param string $filter The filter to apply ('daily', 'monthly', 'yearly', or 'custom').
	 * @param string $start_date The start date for the date range (optional).
	 * @param string $end_date The end date for the date range (optional).
	 * .
	 * @return array An associative array containing:
	 * - total_earning: Total earnings from completed orders.
	 * - total_students: Total number of students enrolled in courses.
	 * - total_course: Total number of courses.
	 * - total_reviews: Total number of reviews for courses.
	 * - earning_graph: An array with:
	 *   - total_revenue: Total revenue from completed orders.
	 *   - total_refund: Total refunded amount.
	 *   - net_amount: Total net earnings (completed orders minus refunds).
	 *   - graph_data: An associative array with time periods (daily, monthly, yearly) as keys and
	 *     earnings, net earnings, and refunds as values.
	 * - courses: Data about courses including sales count and enrollments.
	 */
	public function get_data( $filter, $start_date, $end_date ) {
		global $wpdb;
		// Build base query.
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

		$query = $this->get_filter_query( $query, $filter, $start_date, $end_date );
		// Execute query.
		$results = $wpdb->get_results( $query, ARRAY_A );

		// Calculate totals and prepare graph data.
		$total_earning     = 0;
		$course_sold       = 0;
		$total_net_earning = 0;
		$total_refund      = 0;
		$graph_data        = array();

		// Determine grouping based on custom range.
		$group_by = 'daily'; // Default grouping.
		if ( 'custom' === $filter && $start_date && $end_date ) {
			$start    = new \DateTime( $start_date );
			$end      = new \DateTime( $end_date );
			$interval = $start->diff( $end );

			if ( $interval->m > 1 || $interval->y >= 1 ) {
				$group_by = 'monthly'; // Group by month if range is >1 month and ≤1 year.
			}
			if ( $interval->y >= 1 && $interval->m + $interval->y * 12 > 12 ) {
				$group_by = 'yearly'; // Group by year if range is >1 year.
			}
		} elseif ( 'monthly' === $filter ) {
			$group_by = 'daily';
		} elseif ( 'yearly' === $filter ) {
			$group_by = 'monthly';
		}

		// Initialize graph_data with all required keys.
		if ( 'daily' === $group_by ) {
			$start    = $start_date ? new \DateTime( $start_date ) : new \DateTime( 'first day of this month' );
			$end      = $end_date ? new \DateTime( $end_date ) : new \DateTime( 'last day of this month' );
			$interval = new \DateInterval( 'P1D' ); // Daily intervals.
		} elseif ( 'monthly' === $group_by ) {
			$start    = $start_date ? new \DateTime( $start_date ) : new \DateTime( 'first day of January this year' );
			$end      = $end_date ? new \DateTime( $end_date ) : new \DateTime( 'last day of December this year' );
			$interval = new \DateInterval( 'P1M' ); // Monthly intervals.
		} elseif ( 'yearly' === $group_by ) {
			$start    = $start_date ? new \DateTime( $start_date ) : new \DateTime( 'first day of January' );
			$end      = $end_date ? new \DateTime( $end_date ) : new \DateTime( 'last day of December' );
			$interval = new \DateInterval( 'P1Y' ); // Yearly intervals.
		}

		$date_period = new \DatePeriod( $start, $interval, $end->modify( '+1 day' ) );
		foreach ( $date_period as $date ) {
			$date_key                = $date->format( 'daily' === $group_by ? 'Y-m-d' : ( 'monthly' === $group_by ? 'Y-m' : 'Y' ) );
			$graph_data[ $date_key ] = array(
				'earning' => 0,
				'net'     => 0,
				'refund'  => 0,
			);
		}

		// Process query results.
		foreach ( $results as $result ) {

			$line_total     = floatval( $result['order_total'] );
			$total_earning += $line_total;

			$date_key = '';
			if ( 'daily' === $group_by ) {
				$date_key = date( 'Y-m-d', strtotime( $result['post_date'] ) );
			} elseif ( 'monthly' === $group_by ) {
				$date_key = date( 'Y-m', strtotime( $result['post_date'] ) );
			} elseif ( 'yearly' === $group_by ) {
				$date_key = date( 'Y', strtotime( $result['post_date'] ) );
			}

			if ( 'ohmylms-completed' === $result['order_status'] ) {
				$total_net_earning              += $line_total;
				$graph_data[ $date_key ]['net'] += $line_total;

				$order = ohmylms_ecommerce_get_order( $result['order_id'] );

				foreach ( $order->get_refunds() as $single_refund ) {
					$refund_id                          = $single_refund->ID;
					$graph_data[ $date_key ]['refund'] += ohmylms_format_decimal( get_post_meta( $refund_id, '_refund_amount', true ), ohmylms_get_price_decimals() );
				}
			}

			if ( 'ohmylms-refunded' === $result['order_status'] ) {
				$total_refund                      += $line_total;
				$graph_data[ $date_key ]['refund'] += $line_total;
			}
			$graph_data[ $date_key ]['earning'] += $line_total;
		}

		ksort( $graph_data );
		$course_data = $this->get_all_courses_with_sales_count_and_enrollment( $filter, $start_date, $end_date );

		// Get total Course count.
		$args = array(
			'post_type'      => 'ohmylms-course',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ), // Exclude 'trash'.
			'posts_per_page' => -1, // Get all posts.
			'fields'         => 'ids', // We only need the IDs.
		);

		$query          = new WP_Query( $args );
		$total_courses  = $query->found_posts;
		$recent_courses = $this->get_recent_courses_with_30_day_stats();
		return apply_filters(
			'ohmylms_dashboard_data',
			array(
				'total_earning'          => $total_earning,
				'currency'               => html_entity_decode( get_ohmylms_currency_symbol( get_ohmylms_currency() ) ),
				'currency_pos'           => get_ohmylms_currency_position(),
				'total_students'         => $course_data['total_students'],
				'total_course'           => $course_data['total_course'],
				'total_reviews'          => $course_data['total_reviews'],
				'earning_graph'          => array(
					'total_revenue' => $total_earning,
					'total_refund'  => $total_refund,
					'net_amount'    => $total_net_earning,
					'graph_data'    => $graph_data,
				),
				'courses'                => $course_data['course_data'],
				'course_sold'            => $course_data['course_sold'],
				'total_created_courses'  => $total_courses,

				'earning'                => $this->get_earning_overview(),
				'recent_courses'         => $recent_courses['recent_courses'],
				'total_sales'            => $recent_courses['total_sales'],
				'sales_growth_rate'      => $recent_courses['sales_growth_rate'],
				'total_enrollments'      => $recent_courses['total_enrollments'],
				'enrollment_growth_rate' => $recent_courses['enrollment_growth_rate'],
				'top_course'             => $this->get_top_performed_course_last_30_days(),
			)
		);
	}


	/**
	 * Get earning overview for Dashboard controller.
	 */
	public function get_earning_overview() {
		global $wpdb;

		$today = new \DateTime();
		// Set date range: Last 30 days.
		$end_date   = new \DateTime();
		$start_date = ( clone $end_date )->modify( '-30 days' );

		$start_date_sql = $start_date->format( 'Y-m-d H:i:s' );
		// $end_date_sql   = $end_date->format( 'Y-m-d H:i:s' );
		$end_date_sql = $end_date->setTime( 23, 59, 59 )->format( 'Y-m-d H:i:s' );

		// Previous period (30 days before the current 30-day window).
		$prev_start_date = ( clone $start_date )->modify( '-30 days' )->format( 'Y-m-d H:i:s' );
		$prev_end_date   = ( clone $start_date )->modify( '-1 day' )->format( 'Y-m-d H:i:s' );

		// Common query parts
		// $base_query = "SELECT m.meta_value as line_total, pm.meta_value as order_total, p.post_status, p.ID as order_id
		// FROM {$wpdb->posts} p
		// INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
		// INNER JOIN {$wpdb->prefix}ohmylms_order_items oi ON p.ID = oi.order_id
		// INNER JOIN {$wpdb->prefix}ohmylms_order_itemmeta m ON oi.order_item_id = m.order_item_id
		// WHERE p.post_type = 'ohmylms-order'
		// AND m.meta_key = '_line_total'
		// AND pm.meta_key = '_order_total'
		// AND p.post_status IN ('ohmylms-completed', 'ohmylms-refunded')
		// AND p.post_date BETWEEN %s AND %s";.

		$base_query = "SELECT 
				pm.meta_value as order_total, 
				p.post_status, 
				p.ID as order_id
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
			WHERE p.post_type = 'ohmylms-order'
			AND pm.meta_key = '_order_total'
			AND p.post_status IN ('ohmylms-completed', 'ohmylms-refunded')
			AND p.post_date BETWEEN %s AND %s";

		// Get data for current and previous periods.
		$current_data  = $wpdb->get_results( $wpdb->prepare( $base_query, $start_date_sql, $end_date_sql ), ARRAY_A );
		$previous_data = $wpdb->get_results( $wpdb->prepare( $base_query, $prev_start_date, $prev_end_date ), ARRAY_A );

		// Helper to summarize.
		$calculate_sums = function ( $data ) {
			$earning = $refund = $net = 0;
			foreach ( $data as $row ) {
				$amount = floatval( $row['order_total'] );

				if ( 'ohmylms-completed' === $row['post_status'] ) {
					$order    = ohmylms_ecommerce_get_order( $row['order_id'] );
					$earning += $amount;
					$net     += $amount;

					foreach ( $order->get_refunds() as $single_refund ) {
						$refund_id = $single_refund->ID;
						$refund   += ohmylms_format_decimal( get_post_meta( $refund_id, '_refund_amount', true ), ohmylms_get_price_decimals() );
						$net      -= ohmylms_format_decimal( get_post_meta( $refund_id, '_refund_amount', true ), ohmylms_get_price_decimals() );
					}
				} elseif ( 'ohmylms-refunded' === $row['post_status'] ) {
					$earning += $amount;
					$refund  += $amount;
				}
			}
			return array(
				'earning' => $earning,
				'refund'  => $refund,
				'net'     => $net,
			);
		};

		$current_totals = $calculate_sums( $current_data );
		$prev_totals    = $calculate_sums( $previous_data );

		// Calculate growth %.
		$calc_growth = function ( $current, $previous ) {
			if ( $previous <= 0 ) {
				return $current > 0 ? 100 : 0;
			}
			return round( ( ( $current - $previous ) / $previous ) * 100, 2 );
		};

		return array(
			'total_earning' => $current_totals['earning'],
			'refund'        => $current_totals['refund'],
			'net_income'    => $current_totals['net'],
			'growth'        => array(
				'earning' => $calc_growth( $current_totals['earning'], $prev_totals['earning'] ),
				'refund'  => $calc_growth( $current_totals['refund'], $prev_totals['refund'] ),
				'net'     => $calc_growth( $current_totals['net'], $prev_totals['net'] ),
			),
		);
	}


	/**
	 * Retrieves a list of courses with detailed information such as course name, number of chapters,
	 * lessons, quizzes, and earnings based on the applied filters.
	 *
	 * This function performs a custom query to gather courses data from the WordPress database, applying
	 * filters for specific date ranges (monthly, weekly, yearly, or custom) and calculates various metrics
	 * like the number of chapters, lessons, quizzes, and earnings based on completed or refunded orders.
	 *
	 * The results are returned as an array of courses, each containing the following details:
	 * - id: The course ID
	 * - name: The course name (title)
	 * - chapters: The number of chapters associated with the course
	 * - lessons: The total number of lessons (text, video, audio) in the course
	 * - quizzes: The total number of quizzes in the course
	 * - earnings: The total earnings for the course based on completed and refunded orders
	 * - url: The URL to view the course on the site
	 *
	 * @param WP_REST_Request $request The request object containing filter parameters.
	 * .
	 * @return array An array of courses with their associated details.
	 */
	public function get_courses( $request ) {

		// Get filters.
		$filter     = $request->get_param( 'filter' ); // 'monthly', 'weekly', 'yearly', 'custom'
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		$search_term = $request->get_param( 'search' );
		$page        = $request->get_param( 'page' ) ? (int) $request->get_param( 'page' ) : 1;
		$per_page    = $request->get_param( 'per_page' ) ? (int) $request->get_param( 'per_page' ) : 10;
		$offset      = isset( $request['offset'] ) ? intval( $request['offset'] ) : ( $page - 1 ) * $per_page;

		global $wpdb;

		// Base query to count total courses.
		$count_query = "
			SELECT COUNT(DISTINCT p.ID)
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->prefix}ohmylms_chapter_relationship cr ON p.ID = cr.course_id
			WHERE p.post_type = 'ohmylms-course'
			AND p.post_status = 'publish'
		";

		// Add search term filter for count query.
		if ( $search_term ) {
			$search_term_for_count = '%' . $wpdb->esc_like( $search_term ) . '%';
			$count_query          .= $wpdb->prepare( ' AND p.post_title LIKE %s', $search_term_for_count );
		}

		// Apply filters to count query.
		$count_query = $this->get_filter_query( $count_query, $filter, $start_date, $end_date );

		// Get total courses.
		$total_courses = (int) $wpdb->get_var( $count_query );

		// Calculate max pages.
		$max_pages = ceil( $total_courses / $per_page );

		// Adjust if page exceeds max pages.
		if ( $total_courses < 1 && $page > 1 ) {
			$page   = 1;
			$offset = 0;
		}

		// Base query to get courses and their details.
		$query = "
			SELECT
				p.ID AS course_id,
				p.post_title AS course_name,
				COUNT(DISTINCT cr.chapter_id) AS chapters,
				SUM(CASE WHEN cr2.content_type IN ('text', 'video', 'audio', 'session') THEN 1 ELSE 0 END) AS lessons,
				SUM(CASE WHEN cr2.content_type = 'quiz' THEN 1 ELSE 0 END) AS quizzes,
				COALESCE((
					SELECT SUM(oi_meta_line_total.meta_value)
					FROM {$wpdb->prefix}ohmylms_order_items oi
					INNER JOIN {$wpdb->prefix}ohmylms_order_itemmeta oi_meta_course ON oi.order_item_id = oi_meta_course.order_item_id
					INNER JOIN {$wpdb->prefix}ohmylms_order_itemmeta oi_meta_line_total ON oi.order_item_id = oi_meta_line_total.order_item_id
					INNER JOIN {$wpdb->posts} orders ON oi.order_id = orders.ID
					WHERE oi_meta_course.meta_key = '_course_id'
					AND oi_meta_course.meta_value = p.ID
					AND oi_meta_line_total.meta_key = '_line_total'
					AND orders.post_status IN ('ohmylms-completed', 'ohmylms-refunded')
				), 0) AS earnings
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->prefix}ohmylms_chapter_relationship cr ON p.ID = cr.course_id
			LEFT JOIN {$wpdb->prefix}ohmylms_content_relationship cr2 ON cr.chapter_id = cr2.chapter_id
			WHERE p.post_type = 'ohmylms-course'
			AND p.post_status = 'publish'
		";

		// Apply search term filter.
		if ( $search_term ) {
			$search_term_for_course = '%' . $wpdb->esc_like( $search_term ) . '%';
			$query                 .= $wpdb->prepare( ' AND p.post_title LIKE %s', $search_term_for_course );
		}

		// Apply date and filter-based conditions.
		$query = $this->get_filter_query( $query, $filter, $start_date, $end_date );

		// Group by course ID to aggregate data.
		$query .= ' GROUP BY p.ID ORDER BY p.post_date DESC';

		// Apply pagination.
		$query .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $per_page, $offset );

		// Execute query.
		$results = $wpdb->get_results( $query, ARRAY_A );

		// Prepare final output.
		$courses = array();
		foreach ( $results as $row ) {
			$course    = ohmylms_get_course( $row['course_id'] );
			$courses[] = array(
				'id'           => $row['course_id'],
				'currency'     => html_entity_decode( get_ohmylms_currency_symbol( get_ohmylms_currency() ) ),
				'currency_pos' => get_ohmylms_currency_position(),
				'name'         => $row['course_name'],
				'chapters'     => (int) $row['chapters'],
				'lessons'      => (int) $row['lessons'],
				'quizzes'      => (int) $row['quizzes'],
				'earnings'     => (float) $row['earnings'],
				'url'          => get_permalink( $row['course_id'] ),
				'video_id'     => $course->get_video_id(),
				'video_src'    => wp_get_attachment_url( $course->get_video_id() ),
				'image_src'    => wp_get_attachment_image_src( $course->get_thumbnail_id(), 'large' ) ? wp_get_attachment_image_src( $course->get_thumbnail_id(), 'large' )[0] : '',
			);
		}

		$response = rest_ensure_response( $courses );
		$response->header( 'X-WP-Total', $total_courses );
		$response->header( 'X-WP-TotalPages', $max_pages );
		return $response;
	}

	/**
	 * Retrieves all courses with sales count, enrolled students count, and total reviews,
	 * based on the specified filter and date range. The function also fetches the total number
	 * of distinct students enrolled in courses.
	 *
	 * @param string $filter The filter type for the query. Can be 'monthly', 'weekly', 'yearly', or 'custom'.
	 * @param string $start_date The start date for the custom filter (format: 'YYYY-MM-DD').
	 * @param string $end_date The end date for the custom filter (format: 'YYYY-MM-DD').
	 * @param string $status The status of the courses to fetch (default is 'publish').
	 * .
	 * @return array An associative array containing:
	 *   - 'total_reviews' (int): The total number of reviews across all courses.
	 *   - 'total_students' (int): The total number of distinct students enrolled across all courses.
	 *   - 'total_course' (int): The total number of courses.
	 *   - 'course_data' (array): An array of course data, where each course is represented as an associative array containing:
	 *     - 'id' (int): The course ID.
	 *     - 'url' (string): The course URL.
	 *     - 'title' (string): The course title.
	 *     - 'status' (string): The course status.
	 *     - 'date' (string): The course creation date.
	 *     - 'price' (float): The course price (sale or regular price).
	 *     - 'ratings' (float): The average course rating.
	 *     - 'total_sales_count' (int): The total number of sales for the course.
	 *     - 'total_enrolled_students' (int): The total number of enrolled students in the course.
	 */
	public function get_all_courses_with_sales_count_and_enrollment( $filter, $start_date, $end_date, $status = 'publish' ) {
		global $wpdb;

		$query = "SELECT p.ID, p.post_title, p.post_status, p.post_date
             FROM {$wpdb->posts} p
             WHERE post_type = %s AND post_status = %s";

		$query = $this->get_filter_query( $query, $filter, $start_date, $end_date );

		// Build query to fetch courses.
		$courses_query = $wpdb->prepare(
			$query,
			'ohmylms-course',
			$status
		);

		// Execute courses query.
		$courses = $wpdb->get_results( $courses_query, ARRAY_A );

		$count_courses = count( $courses );
		// $query .= " LIMIT 5";

		// Build query to fetch courses.
		$courses_query = $wpdb->prepare(
			$query,
			'ohmylms-course',
			$status
		);
		// Execute courses query.
		$courses       = $wpdb->get_results( $courses_query, ARRAY_A );
		$total_reviews = 0;
		// Prepare results with total sales count and enrolled students count.
		$course_data = array();
		$course_sold = 0;
		foreach ( $courses as $course ) {
			$course_obj = ohmylms_get_course( $course['ID'] );
			$price      = $course_obj->get_sale_price() ? $course_obj->get_sale_price() : $course_obj->get_regular_price();
			if ( 'free' === $course_obj->get_price_type() ) {
				$price = 'free';
			}
			$ratings       = $course_obj->get_average_rating();
			$total_reviews = (int) $course_obj->get_review_count() + $total_reviews;
			// Fetch the number of sales for the course.
			$sales_query = $wpdb->prepare(
				'SELECT COUNT(*) as sales_count
				 FROM %i oi
				 INNER JOIN %i m ON oi.order_item_id = m.order_item_id
				 INNER JOIN %i p ON oi.order_id = p.ID
				 WHERE m.meta_key = %s AND m.meta_value = %d AND p.post_status = \'ohmylms-completed\'',
				$wpdb->prefix . 'ohmylms_order_items',
				$wpdb->prefix . 'ohmylms_order_itemmeta',
				$wpdb->prefix . 'posts',
				'_course_id',
				$course['ID']
			);

			$sales_count = $wpdb->get_var( $sales_query );

			// Fetch the number of enrolled students for the course with 'enrolled' status.
			$enrollment_query = $wpdb->prepare(
				'SELECT COUNT(*) as enrolled_count
                 FROM %i ue
                 WHERE ue.course_id = %d AND ue.status = %s',
				$wpdb->prefix . 'ohmylms_user_enrollment',
				$course['ID'],
				'enrolled'  // Only count users with 'enrolled' status.
			);

			$enrolled_students_count = $wpdb->get_var( $enrollment_query );
			$course_obj              = ohmylms_get_course( $course['ID'] );
			// Add course data with sales count and enrolled students count.
			$course_data[] = array(
				'id'                      => $course['ID'],
				'url'                     => get_permalink( $course['ID'] ),
				'title'                   => $course['post_title'],
				'status'                  => $course['post_status'],
				'date'                    => $course['post_date'],
				'price'                   => $price,
				'image_src'               => wp_get_attachment_image_src( $course_obj->get_thumbnail_id(), 'large' ) ? wp_get_attachment_image_src( $course_obj->get_thumbnail_id(), 'large' )[0] : '',
				'ratings'                 => $ratings,
				'total_sales_count'       => $sales_count ? intval( $sales_count ) : 0, // Default to 0 if no sales.
				'total_enrolled_students' => $enrolled_students_count ? intval( $enrolled_students_count ) : 0, // Default to 0 if no enrollments.
				'video_id'                => $course_obj->get_video_id(),
				'video_src'               => wp_get_attachment_url( $course_obj->get_video_id() ),
			);
			$course_sold  += $sales_count ? intval( $sales_count ) : 0;
		}

		// Fetch the total distinct students count across all courses with 'enrolled' status.
		$total_distinct_students_query = "
            SELECT COUNT(DISTINCT user_id) as total_distinct_students
            FROM {$wpdb->prefix}ohmylms_user_enrollment s
            WHERE status = %s";

		// Apply filters.
		if ( 'monthly' === $filter ) {
			$total_distinct_students_query .= ' AND MONTH(s.start_date) = MONTH(CURRENT_DATE()) AND YEAR(s.start_date) = YEAR(CURRENT_DATE())';
		} elseif ( 'weekly' === $filter ) {
			$total_distinct_students_query .= ' AND WEEK(s.start_date) = WEEK(CURRENT_DATE()) AND YEAR(s.start_date) = YEAR(CURRENT_DATE())';
		} elseif ( 'yearly' === $filter ) {
			$total_distinct_students_query .= ' AND YEAR(s.start_date) = YEAR(CURRENT_DATE())';
		} elseif ( 'custom' === $filter && $start_date && $end_date ) {
			$total_distinct_students_query .= $wpdb->prepare( ' AND s.start_date BETWEEN %s AND %s', $start_date, $end_date );
		}

		$total_distinct_students = $wpdb->get_var( $wpdb->prepare( $total_distinct_students_query, 'enrolled' ) );

		$data = array(
			'total_reviews'  => $total_reviews,
			'total_students' => $total_distinct_students,
			'total_course'   => $count_courses,
			'course_sold'    => $course_sold,
			'course_data'    => $course_data,
		);

		return $data;
	}

	/**
	 * Get recent courses with 30 day stats for Dashboard controller.
	 */
	public function get_recent_courses_with_30_day_stats() {
		global $wpdb;

		// Date range for the last 30 days and previous 30 days.
		$today         = current_time( 'Y-m-d' );
		$start_30_days = date( 'Y-m-d', strtotime( '-30 days', strtotime( $today ) ) );
		$prev_30_start = date( 'Y-m-d', strtotime( '-60 days', strtotime( $today ) ) );
		$prev_30_end   = date( 'Y-m-d', strtotime( '-30 days', strtotime( $today ) ) );

		// Query to get the latest 5 courses.
		$query = $wpdb->prepare(
			'SELECT p.ID, p.post_title, p.post_status, p.post_date
			 FROM %i p
			 WHERE post_type = %s AND post_status = %s
			 ORDER BY p.post_date DESC
			 LIMIT 5',
			$wpdb->posts,
			'ohmylms-course',
			'publish'
		);

		$courses = $wpdb->get_results( $query, ARRAY_A );

		$course_data       = array();
		$total_sales       = 0;
		$total_enrollments = 0;
		$student_ids       = array();
		foreach ( $courses as $course ) {
			$course_obj = ohmylms_get_course( $course['ID'] );
			$price      = $course_obj->get_sale_price() ? $course_obj->get_sale_price() : $course_obj->get_regular_price();

			if ( 'free' === $course_obj->get_price_type() ) {
				$price = 'free';
			}

			// Sales in the last 30 days including the current day.
			$sales_query = $wpdb->prepare(
				'SELECT COUNT(*)
				 FROM %i oi
				 INNER JOIN %i m ON oi.order_item_id = m.order_item_id
				 INNER JOIN %i p ON oi.order_id = p.id
				 WHERE m.meta_key = %s AND m.meta_value = %d
				 AND p.post_date BETWEEN %s AND %s
				 AND p.post_status IN (\'ohmylms-completed\')',
				$wpdb->prefix . 'ohmylms_order_items',
				$wpdb->prefix . 'ohmylms_order_itemmeta',
				$wpdb->prefix . 'posts',
				'_course_id',
				$course['ID'],
				$start_30_days . ' 00:00:00',
				// Start of the 30-day period.
				$today . ' 23:59:59' // End of the current day (midnight).
			);
			$sales_count = (int) $wpdb->get_var( $sales_query );

			// Enrollments in the last 30 days including the current day.
			$enrollment_query = $wpdb->prepare(
				'SELECT COUNT(DISTINCT user_id)
				 FROM %i
				 WHERE course_id = %d AND status = %s
				 AND start_date BETWEEN %s AND %s',
				$wpdb->prefix . 'ohmylms_user_enrollment',
				$course['ID'],
				'enrolled',
				$start_30_days . ' 00:00:00',
				// Start of the 30-day period.
				$today . ' 23:59:59' // End of the current day (midnight).
			);
			$enrolled_count     = (int) $wpdb->get_var( $enrollment_query );
			$total_sales       += $sales_count;
			$total_enrollments += $enrolled_count;
			// Fetch sales in the previous 30 days for sales growth calculation.
			$prev_sales_query = $wpdb->prepare(
				'SELECT COUNT(*)
				 FROM %i oi
				 INNER JOIN %i m ON oi.order_item_id = m.order_item_id
				 INNER JOIN %i p ON oi.order_id = p.id
				 WHERE m.meta_key = %s AND m.meta_value = %d
				 AND p.post_date BETWEEN %s AND %s',
				$wpdb->prefix . 'ohmylms_order_items',
				$wpdb->prefix . 'ohmylms_order_itemmeta',
				$wpdb->prefix . 'posts',
				'_course_id',
				$course['ID'],
				$prev_30_start . ' 00:00:00',
				// Start of the previous 30-day period.
				$prev_30_end . ' 23:59:59' // End of the previous period (midnight).
			);
			$previous_sales = (int) $wpdb->get_var( $prev_sales_query );

			// Calculate sales growth rate for each course.
			$sales_growth_rate = $previous_sales > 0
				? round( ( ( $sales_count - $previous_sales ) / $previous_sales ) * 100, 2 )
				: ( $sales_count > 0 ? 100 : 0 );

			// Add the course data to the result array.
			$course_data[] = array(
				'id'                      => $course['ID'],
				'url'                     => get_permalink( $course['ID'] ),
				'title'                   => $course['post_title'],
				'status'                  => $course['post_status'],
				'date'                    => $course['post_date'],
				'price'                   => $price,
				'image_src'               => wp_get_attachment_image_src( $course_obj->get_thumbnail_id(), 'large' )[0] ?? '',
				'total_sales_count'       => $sales_count, // Includes current day.
				'total_enrolled_students' => $enrolled_count, // Includes current day.
				'sales_growth_rate'       => $sales_growth_rate, // Individual course growth rate.
				'categories'              => $this->get_taxonomy_terms( $course['ID'] ),
				'video_id'                => $course_obj->get_video_id(),
				'video_src'               => wp_get_attachment_url( $course_obj->get_video_id() ),
			);
		}

		// Fetch total sales in previous 30 days (for growth).
		$prev_sales_query = $wpdb->prepare(
			'SELECT COUNT(*)
			FROM %i oi
			INNER JOIN %i m ON oi.order_item_id = m.order_item_id
			INNER JOIN %i p ON oi.order_id = p.id
			WHERE m.meta_key = %s
			AND p.post_date BETWEEN %s AND %s',
			$wpdb->prefix . 'ohmylms_order_items',
			$wpdb->prefix . 'ohmylms_order_itemmeta',
			$wpdb->prefix . 'posts',
			'_course_id',
			$prev_30_start . ' 00:00:00',
			// Start of the previous 30-day period.
			$prev_30_end . ' 23:59:59' // End of the previous period (midnight).
		);
		$previous_sales = (int) $wpdb->get_var( $prev_sales_query );

		// Calculate growth rate.
		$sales_growth_rate = $previous_sales > 0
			? round( ( ( $total_sales - $previous_sales ) / $previous_sales ) * 100, 2 )
			: ( $total_sales > 0 ? 100 : 0 );

		// Enrollments in the last 30 days including the current day.
		$prev_enrollment_query = $wpdb->prepare(
			'SELECT COUNT(DISTINCT user_id) as unique_students
			 FROM %i
			 WHERE status = %s
			 AND start_date BETWEEN %s AND %s',
			$wpdb->prefix . 'ohmylms_user_enrollment',
			'enrolled',
			$prev_30_start . ' 00:00:00',
			// Start of the previous 30-day period.
			$prev_30_end . ' 23:59:59' // End of the previous period (midnight).
		);
		$previous_enrollment = (int) $wpdb->get_var( $prev_enrollment_query );

		// Calculate sales growth rate for each course.
		$enrollment_growth_rate = $previous_enrollment > 0
			? round( ( ( (int) $this->get_unique_students_count( $start_30_days, $today ) - $previous_enrollment ) / $previous_enrollment ) * 100, 2 )
			: ( (int) $this->get_unique_students_count( $start_30_days, $today ) > 0 ? 100 : 0 );

		return array(
			'recent_courses'         => $course_data,
			'total_sales'            => $total_sales,
			'sales_growth_rate'      => $sales_growth_rate,
			'total_enrollments'      => $this->get_unique_students_count( $start_30_days, $today ),
			'enrollment_growth_rate' => $enrollment_growth_rate,
		);
	}


	/**
	 * Get unique student count across all courses.
	 *
	 * @param string $start_date Start date in Y-m-d format.
	 * @param string $end_date End date in Y-m-d format.
	 * @return int Number of unique students.
	 */
	private function get_unique_students_count( $start_date, $end_date ) {
		global $wpdb;

		$query = $wpdb->prepare(
			'SELECT COUNT(DISTINCT user_id) as unique_students
            FROM %i
            WHERE status = %s
            AND start_date BETWEEN %s AND %s',
			$wpdb->prefix . 'ohmylms_user_enrollment',
			'enrolled',
			$start_date . ' 00:00:00',
			$end_date . ' 23:59:59'
		);

		return (int) $wpdb->get_var( $query );
	}


	/**
	 * Get taxonomy terms for a course.
	 *
	 * @param mixed  $course_id Course identifier.
	 * @param string $taxonomy The taxonomy to retrieve terms for. Default is 'category'.
	 * @return array The array of terms with their IDs and names.
	 *
	 * @since 1.0.0
	 */
	protected function get_taxonomy_terms( $course_id, $taxonomy = 'category' ) {
		$course = ohmylms_get_course( $course_id );
		$terms  = array();

		if ( ! $course ) {
			return $terms;
		}

		// Course categories and tags were replaced by the curriculum and Course Bundles; the response
		// keys are unchanged.
		return \OhMyLMS\Curriculum\Placement::card_terms( $course->get_id(), 'tag' === $taxonomy );
	}



	/**
	 * Get top performed course last 30 days for Dashboard controller.
	 */
	public function get_top_performed_course_last_30_days() {
		global $wpdb;

		$thirty_days_ago = date( 'Y-m-d', strtotime( '-30 days' ) );

		// Get the course with the highest number of enrollments in the last 30 days.
		$top_course_query = $wpdb->prepare(
			'
			SELECT course_id, COUNT(*) as enrolled_count
			FROM %i
			WHERE status = %s AND start_date >= %s
			GROUP BY course_id
			ORDER BY enrolled_count DESC
			LIMIT 1
		',
			$wpdb->prefix . 'ohmylms_user_enrollment',
			'enrolled',
			$thirty_days_ago
		);

		$top_course = $wpdb->get_row( $top_course_query );

		if ( ! $top_course ) {
			return null; // No enrollments in the last 30 days.
		}

		$course_id      = $top_course->course_id;
		$enrolled_count = (int) $top_course->enrolled_count;
		$post           = get_post( $course_id );

		if ( ! $post ) {
			return null;
		}

		$course_obj = ohmylms_get_course( $course_id );

		// Get completed students count for this course.
		$completed_query = $wpdb->prepare(
			'
			SELECT COUNT(*)
			FROM %i
			WHERE course_id = %d AND progress = %s AND start_date >= %s
		',
			$wpdb->prefix . 'ohmylms_user_enrollment',
			$course_id,
			'completed',
			$thirty_days_ago
		);

		$completed_count = (int) $wpdb->get_var( $completed_query );
		$ratings         = $course_obj->get_average_rating();
		// Build result.
		return array(
			'id'              => $course_id,
			'title'           => $post->post_title,
			'url'             => get_permalink( $course_id ),
			'image_src'       => wp_get_attachment_image_src( $course_obj->get_thumbnail_id(), 'large' )[0] ?? '',
			'total_students'  => $enrolled_count,
			'total_completed' => $completed_count,
			'ratings'         => $ratings,
			'video_id'        => $course_obj->get_video_id(),
			'video_src'       => wp_get_attachment_url( $course_obj->get_video_id() ),
		);
	}


	/**
	 * Adds filter conditions to the provided SQL query based on the specified filter type and date range.
	 * The function modifies the query to include conditions for monthly, weekly, yearly, or custom date filters.
	 *
	 * @param string $query The base SQL query to which filters will be applied.
	 * @param string $filter The filter type to apply. Can be one of 'monthly', 'weekly', 'yearly', or 'custom'.
	 * @param string $start_date The start date for the custom filter (format: 'YYYY-MM-DD'). Required if $filter is 'custom'.
	 * @param string $end_date The end date for the custom filter (format: 'YYYY-MM-DD'). Required if $filter is 'custom'.
	 * .
	 * @return string The modified SQL query with the applied filters.
	 */
	public function get_filter_query( $query, $filter, $start_date, $end_date ) {
		global $wpdb;

		// Apply filters.
		if ( 'monthly' === $filter ) {
			$query .= ' AND MONTH(p.post_date) = MONTH(CURRENT_DATE()) AND YEAR(p.post_date) = YEAR(CURRENT_DATE())';
		} elseif ( 'weekly' === $filter ) {
			$query .= ' AND WEEK(p.post_date) = WEEK(CURRENT_DATE()) AND YEAR(p.post_date) = YEAR(CURRENT_DATE())';
		} elseif ( 'yearly' === $filter ) {
			$query .= ' AND YEAR(p.post_date) = YEAR(CURRENT_DATE())';
		} elseif ( 'custom' === $filter && $start_date && $end_date ) {
			$query .= $wpdb->prepare( ' AND p.post_date BETWEEN %s AND %s', $start_date, $end_date );
		}

		return $query;
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
	 * Check permissions for getting items.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return bool True if the current user has permission, false otherwise.
	 *
	 * @since 1.0.0
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'edit_posts' );
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
		return current_user_can( 'edit_posts' );
	}
}
