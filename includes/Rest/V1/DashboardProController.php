<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * DashboardController class.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Admin\Settings\AdminSettings;

/**
 * DashboardController class.
 *
 * Handles REST API endpoints for settings.
 *
 * @since 1.0.0
 */
class DashboardProController extends RestController {

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
			'/' . $this->base . '/courses/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_course_details' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}


	/**
	 * Retrieves detailed information about a specific course, including earnings, total students,
	 * and other course-related data based on the applied filters.
	 *
	 * This function uses the provided course ID to fetch detailed metrics about the course. The
	 * filters such as time range (monthly, weekly, yearly, or custom) are applied to calculate
	 * earnings, the total number of students, and other course-specific data.
	 *
	 * The result is an array containing merged data from the following categories:
	 * - Earnings: The total earnings for the course based on completed or refunded orders.
	 * - Total Students: The total number of students enrolled or engaged with the course within
	 *   the filtered date range.
	 * - Course Data: Additional course-related data (e.g., lessons, quizzes, etc.).
	 *
	 * @param WP_REST_Request $request The request object containing filter parameters and course ID.
	 *
	 * @return array An array containing the merged course earnings, total students, and course data.
	 */
	public function get_course_details( $request ) {

		// Get filters.
		$filter     = $request->get_param( 'filter' ); // 'monthly', 'weekly', 'yearly', 'custom'
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		$course_id = intval( $request['id'] );

		$course_earning = $this->get_course_earning( $course_id, $filter, $start_date, $end_date );
		$total_students = $this->get_student_data( $course_id, $filter, $start_date, $end_date );
		$course_data    = $this->get_course_data( $course_id, $filter, $start_date, $end_date );

		$result = array_merge( $course_earning, $total_students );
		$result = array_merge( $result, $course_data );
		return $result;
	}


	/**
	 * Retrieves the earnings details for a specific course, including total earnings, net earnings,
	 * refunds, and a breakdown of earnings grouped by the selected filter (daily, monthly, or yearly).
	 *
	 * This function queries the orders related to a specific course and calculates:
	 * - Total earnings: The sum of all earnings for the course, including completed and refunded orders.
	 * - Net earnings: The total earnings from completed orders.
	 * - Refunds: The total refunded amount.
	 * - Graph data: A breakdown of earnings, net earnings, and refunds by date (daily, monthly, or yearly).
	 *
	 * The function supports filtering by custom date ranges, as well as predefined filters (weekly, monthly, yearly).
	 * It also groups the data based on the selected filter and date range.
	 *
	 * @param int    $course_id   The ID of the course to retrieve earnings for.
	 * @param string $filter      The filter type (e.g., 'monthly', 'weekly', 'yearly', 'custom').
	 * @param string $start_date  The start date for the custom range filter (optional).
	 * @param string $end_date    The end date for the custom range filter (optional).
	 *
	 * @return array An array containing the following keys:
	 *  - 'total_earning': The total earnings for the course.
	 *  - 'total_refund': The total refunded amount.
	 *  - 'net_amount': The net earnings (completed orders).
	 *  - 'graph_data': A breakdown of earnings, net earnings, and refunds by date.
	 */
	public function get_course_earning( $course_id, $filter, $start_date, $end_date ) {
		global $wpdb;

		// Build base query to fetch orders for a specific course.
		$query = "
			SELECT p.ID as order_id, m.meta_value as line_total, p.post_status as order_status, p.post_date
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->prefix}ohmylms_order_items oi ON p.ID = oi.order_id
			INNER JOIN {$wpdb->prefix}ohmylms_order_itemmeta m ON oi.order_item_id = m.order_item_id
			INNER JOIN {$wpdb->prefix}ohmylms_order_itemmeta course_meta ON oi.order_item_id = course_meta.order_item_id
			WHERE p.post_type = 'ohmylms-order' 
			  AND p.post_status IN ('ohmylms-completed', 'ohmylms-refunded') 
			  AND m.meta_key = '_line_total' 
			  AND course_meta.meta_key = '_course_id' 
			  AND course_meta.meta_value = {$course_id}
		";

		// Add filters for date range and custom filtering.
		$query = $this->get_filter_query( $wpdb->prepare( $query, $course_id ), $filter, $start_date, $end_date );

		// Execute query.
		$results = $wpdb->get_results( $query, ARRAY_A );

		// Initialize totals and graph data.
		$total_earning     = 0;
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
			$line_total     = floatval( $result['line_total'] );
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
			}

			if ( 'ohmylms-refunded' === $result['order_status'] ) {
				$total_refund                      += $line_total;
				$graph_data[ $date_key ]['refund'] += $line_total;
			}

			$graph_data[ $date_key ]['earning'] += $line_total;
		}

		ksort( $graph_data );

		return array(
			'total_earning' => $total_earning,
			'total_refund'  => $total_refund,
			'net_amount'    => $total_net_earning,
			'graph_data'    => $graph_data,
			'currency'      => html_entity_decode( get_ohmylms_currency_symbol( get_ohmylms_currency() ) ),
			'currency_pos'  => get_ohmylms_currency_position(),
		);
	}


	/**
	 * Retrieves the student data for a specific course, including the total number of students,
	 * the number of students who have completed the course, and the number of students currently
	 * in progress, based on the selected filter (monthly, weekly, yearly, or custom date range).
	 *
	 * This function queries the enrollments for a specific course and calculates:
	 * - Total students: The total number of students enrolled in the course.
	 * - Course completion: The number of students who have completed the course.
	 * - Total in progress: The number of students currently in progress with the course.
	 *
	 * The function supports filtering by predefined filters (monthly, weekly, yearly) and custom
	 * date ranges.
	 *
	 * @param int    $course_id   The ID of the course to retrieve student data for.
	 * @param string $filter      The filter type (e.g., 'monthly', 'weekly', 'yearly', 'custom').
	 * @param string $start_date  The start date for the custom range filter (optional).
	 * @param string $end_date    The end date for the custom range filter (optional).
	 *
	 * @return array An array containing the following keys:
	 *  - 'total_students': The total number of students enrolled in the course.
	 *  - 'course_completion': The number of students who have completed the course.
	 *  - 'total_in_progress': The number of students who are currently in progress.
	 */
	public function get_student_data( $course_id, $filter, $start_date, $end_date ) {
		global $wpdb;
		// Start with the base query.
		$enrollment_query = $wpdb->prepare(
			'SELECT ue.user_id as student_id
			 FROM %i ue
			 WHERE ue.course_id = %d AND ue.status = %s',
			$wpdb->prefix . 'ohmylms_user_enrollment',
			$course_id,
			'enrolled'
		);

		// Apply date filters based on the filter type.
		if ( 'monthly' === $filter ) {
			$enrollment_query .= ' AND MONTH(ue.start_date) = MONTH(CURRENT_DATE()) AND YEAR(ue.start_date) = YEAR(CURRENT_DATE())';
		} elseif ( 'weekly' === $filter ) {
			$enrollment_query .= ' AND WEEK(ue.start_date) = WEEK(CURRENT_DATE()) AND YEAR(ue.start_date) = YEAR(CURRENT_DATE())';
		} elseif ( 'yearly' === $filter ) {
			$enrollment_query .= ' AND YEAR(ue.start_date) = YEAR(CURRENT_DATE())';
		} elseif ( 'custom' === $filter && $start_date && $end_date ) {
			$enrollment_query .= $wpdb->prepare( ' AND ue.start_date BETWEEN %s AND %s', $start_date, $end_date );
		}
		$total_students    = 0;
		$course_completion = 0;
		$total_in_progress = 0;
		$results           = $wpdb->get_results( $enrollment_query, ARRAY_A );
		if ( is_array( $results ) ) {
			$total_students = count( $results );
			foreach ( $results as $result ) {
				$student = new \OhMyLMS\Data\Student( $result['student_id'] );
				if ( $student->is_course_completed( $course_id ) ) {
					++$course_completion;
				}
				if ( $student->is_course_in_progress( $course_id ) ) {
					++$total_in_progress;
				}
			}
		}
		return array(
			'total_students'    => $total_students,
			'course_completion' => $course_completion,
			'total_in_progress' => $total_in_progress,
		);
	}


	/**
	 * Retrieves course data, including the number of chapters, lessons, quizzes, assignments,
	 * and total reviews for a specific course, based on the selected filter (monthly, weekly,
	 * yearly, or custom date range).
	 *
	 * This function counts:
	 * - Chapters: The total number of chapters associated with the course.
	 * - Lessons: The total number of lessons (text, video, audio) in the course.
	 * - Quizzes: The total number of quizzes in the course.
	 * - Assignments: The total number of assignments in the course.
	 * - Total Reviews: The total number of reviews for the course.
	 *
	 * The function supports filtering by predefined filters (monthly, weekly, yearly) and custom
	 * date ranges.
	 *
	 * @param int    $course_id   The ID of the course to retrieve data for.
	 * @param string $filter      The filter type (e.g., 'monthly', 'weekly', 'yearly', 'custom').
	 * @param string $start_date  The start date for the custom range filter (optional).
	 * @param string $end_date    The end date for the custom range filter (optional).
	 *
	 * @return array An array containing the following keys:
	 *  - 'chapters': The number of chapters in the course.
	 *  - 'lessons': The number of lessons in the course.
	 *  - 'quizzes': The number of quizzes in the course.
	 *  - 'assignments': The number of assignments in the course.
	 *  - 'total_review': The total number of reviews for the course.
	 */
	public function get_course_data( $course_id, $filter, $start_date, $end_date ) {
		global $wpdb;
		// Base query to get courses and their details.
		$query = "
			SELECT 
				COUNT(DISTINCT cr.chapter_id) AS chapters,
				SUM(CASE WHEN cr2.content_type IN ('text', 'video', 'audio') THEN 1 ELSE 0 END) AS lessons,
				SUM(CASE WHEN cr2.content_type = 'quiz' THEN 1 ELSE 0 END) AS quizzes,
				SUM(CASE WHEN cr2.content_type = 'assignment' THEN 1 ELSE 0 END) AS assignments
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->prefix}ohmylms_chapter_relationship cr ON p.ID = cr.course_id
			LEFT JOIN {$wpdb->prefix}ohmylms_content_relationship cr2 ON cr.chapter_id = cr2.chapter_id
			WHERE p.post_type = 'ohmylms-course'
			AND p.post_status = 'publish'
			AND cr.course_id = {$course_id}
		";

		// Apply filters.
		$query  = $this->get_filter_query( $query, $filter, $start_date, $end_date );
		$result = $wpdb->get_row( $query, ARRAY_A );

		$course                  = ohmylms_get_course( $course_id );
		$result['total_review']  = $course->get_review_count();
		$result['date_created']  = $course->get_date_created();
		$result['date_modified'] = $course->get_date_modified();
		return $result;
	}


	/**
	 * Adds filter conditions to the provided SQL query based on the specified filter type and date range.
	 * The function modifies the query to include conditions for monthly, weekly, yearly, or custom date filters.
	 *
	 * @param string $query The base SQL query to which filters will be applied.
	 * @param string $filter The filter type to apply. Can be one of 'monthly', 'weekly', 'yearly', or 'custom'.
	 * @param string $start_date The start date for the custom filter (format: 'YYYY-MM-DD'). Required if $filter is 'custom'.
	 * @param string $end_date The end date for the custom filter (format: 'YYYY-MM-DD'). Required if $filter is 'custom'.
	 *
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
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error( 'ohmylms_rest_forbidden', __( 'Sorry, you are not allowed to view this resource.', 'ohmylms' ), array( 'status' => \rest_authorization_required_code() ) );
		}

		// The dashboard exposes per course revenue and enrolment data.
		return $this->check_object_permission( $request, 'read', OHMYLMS_COURSE_CPT );
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
