<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Controller for handling student REST API endpoints.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Data\Course;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Controller for handling student REST API endpoints.
 *
 * This class extends the RESTController abstract class and defines REST API routes
 * for student-related CRUD operations and many more.
 *
 * @since 1.0.0
 */
class StudentController extends RestController {

	/**
	 * The base route for student base endpoints.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $base = 'students';

	/**
	 * Check student permission for Student controller.
	 */
	public function check_student_permission() {
		return current_user_can( 'manage_options' ) || current_user_can( 'manage_ohmylms' );
	}

	/**
	 * Registers REST API routes for student operations.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'check_student_permission' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'ban_student' ),
					'permission_callback' => array( $this, 'check_student_permission' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		// Unban student endpoint.
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/unban',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'unban_student' ),
					'permission_callback' => array( $this, 'check_student_permission' ),
					'args'                => array(
						'ids' => array(
							'required'          => true,
							'type'              => 'array',
							'description'       => __( 'User IDs to unban', 'ohmylms' ),
							'sanitize_callback' => function ( $ids ) {
								return array_map( 'absint', (array) $ids );
							},
						),
					),
				),
			)
		);
	}


	/**
	 * Retrieves all students with their enrollment data.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		global $wpdb;

		// New student accounts belong in the directory before their first enrollment.
		$student_roles = array( 'subscriber', 'ohmylms_student', ohmylms_get_student_role() );
		$role_filters  = array();
		foreach ( array_unique( $student_roles ) as $role ) {
			$role_filters[] = $wpdb->prepare( 'student_role.meta_value LIKE %s', '%' . $wpdb->esc_like( '"' . $role . '";b:1;' ) . '%' );
		}
		$student_filter = $wpdb->prepare(
			' AND (e.user_id IS NOT NULL OR EXISTS (SELECT 1 FROM ' . $wpdb->usermeta . ' student_role WHERE student_role.user_id = u.ID AND student_role.meta_key = %s AND (' . implode( ' OR ', $role_filters ) . ')) OR EXISTS (SELECT 1 FROM ' . $wpdb->usermeta . ' managed_school WHERE managed_school.user_id = u.ID AND managed_school.meta_key = %s))',
			$wpdb->get_blog_prefix() . 'capabilities',
			'_ohmylms_managed_school'
		);

		// Pagination parameters.
		$page     = $request->get_param( 'page' ) ? (int) $request->get_param( 'page' ) : 1;
		$per_page = $request->get_param( 'per_page' ) ? (int) $request->get_param( 'per_page' ) : 10;

		$filter     = $request->get_param( 'date_filter' ); // 'monthly', 'weekly', 'yearly', 'custom'
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );
		$offset     = ( $page - 1 ) * $per_page;

		// Search parameter.
		$search    = isset( $request['search'] ) ? sanitize_text_field( $request['search'] ) : '';
		$course_id = $request->get_param( 'course_id' ) ? (int) $request->get_param( 'course_id' ) : null;

		// Order by parameter (default is 'name').
		$order_by = $request->get_param( 'order_by' ) ? sanitize_text_field( $request->get_param( 'order_by' ) ) : 'name';
		$order    = $request->get_param( 'order' ) ? strtoupper( sanitize_text_field( $request->get_param( 'order' ) ) ) : 'ASC';

		// Validate the order_by parameter.
		$valid_order_by = array( 'name', 'email', 'registration_date', 'courses_enrolled', 'membership_enrolled' );
		$valid_order    = array( 'ASC', 'DESC' );
		if ( ! in_array( $order_by, $valid_order_by, true ) ) {
			$order_by = 'name';
		}

		if ( ! in_array( $order, $valid_order, true ) ) {
			$order = 'ASC';
		}

		switch ( $order_by ) {
			case 'email':
				$order_column = 'u.user_email';
				break;
			case 'registration_date':
				$order_column = 'registration_date'; // alias used in SELECT.
				break;
			case 'courses_enrolled':
				$order_column = 'courses_enrolled'; // alias used in SELECT.
				break;
			case 'membership_enrolled':
				$order_column = 'membership_enrolled'; // alias used in SELECT.
				break;
			default:
				$order_column = 'u.display_name'; // 'name' by default
				break;
		}

		$query = '
		SELECT
			u.ID AS user_id,
			u.display_name AS student_name,
			u.user_email AS student_email,
			COUNT(DISTINCT e.course_id) AS courses_enrolled,
			COALESCE(MIN(e.start_date), u.user_registered) AS registration_date';

		// Add membership count only if pro is active.
		if ( ohmylms_is_pro() ) {
			$query .= ',
				COUNT(DISTINCT m.membership_id) AS membership_enrolled';
		} else {
			$query .= ',
				0 AS membership_enrolled';
		}

		$query .= "
            FROM
                {$wpdb->users} u
            LEFT JOIN
                {$wpdb->prefix}ohmylms_user_enrollment e
                ON u.ID = e.user_id AND e.status IN ('enrolled', 'banned')";

		// Add membership join only if pro is active.
		if ( ohmylms_is_pro() ) {
			$query .= "
            LEFT JOIN
                {$wpdb->prefix}ohmylms_user_membership m
                ON u.ID = m.user_id AND m.status IN ('enrolled', 'banned')";
		}

		$query .= '
            WHERE
                1=1
        ';
		$query .= $student_filter;

		// Base query with optional search filter
		// $query = "
		// SELECT
		// u.ID AS user_id,
		// u.display_name AS student_name,
		// u.user_email AS student_email,
		// COUNT(e.course_id) AS courses_enrolled,
		// MIN(e.start_date) AS registration_date,
		// COUNT(DISTINCT m.membership_id) AS membership_enrolled
		// FROM
		// {$wpdb->users} u
		// INNER JOIN
		// {$wpdb->prefix}ohmylms_user_enrollment e
		// ON u.ID = e.user_id AND e.status = 'enrolled'
		// LEFT JOIN
		// {$wpdb->prefix}ohmylms_user_membership m
		// ON u.ID = m.user_id AND m.status = 'enrolled'
		// WHERE
		// 1=1
		// ";.

		$course_name = '';
		// Add course ID filter if provided.
		if ( ! empty( $course_id ) ) {
			$query .= $wpdb->prepare( ' AND e.course_id = %d ', $course_id );

			$course = get_post( $course_id );
			if ( $course ) {
				$course_name = $course->post_title;
			}
		}

		// Add search filter if search parameter is provided.
		if ( ! empty( $search ) ) {
			$query .= $wpdb->prepare(
				' AND (u.display_name LIKE %s OR u.user_email LIKE %s) ',
				'%' . $wpdb->esc_like( $search ) . '%',
				'%' . $wpdb->esc_like( $search ) . '%'
			);
		}

		// Apply date and time filters using the helper function.
		$query = $this->get_filter_query( $query, $filter, $start_date, $end_date );

		// Grouping, ordering, and limiting.
		$query .= "
            GROUP BY u.ID
            ORDER BY {$order_column} {$order}
            LIMIT %d OFFSET %d
        ";

		// Execute the query with pagination.
		$results = $wpdb->get_results( $wpdb->prepare( $query, $per_page, $offset ), ARRAY_A );

		// Count total students with search filter if applicable.
		$count_query = "
			SELECT
				COUNT(DISTINCT u.ID)
			FROM
				{$wpdb->users} u
			LEFT JOIN
				{$wpdb->prefix}ohmylms_user_enrollment e
				ON u.ID = e.user_id AND e.status IN ('enrolled', 'banned')";

		// Add membership join only if pro is active.
		if ( ohmylms_is_pro() ) {
			$count_query .= "
			LEFT JOIN
				{$wpdb->prefix}ohmylms_user_membership m
				ON u.ID = m.user_id AND m.status IN ('enrolled', 'banned')";
		}

		$count_query .= '
			WHERE
				1=1';
		$count_query .= $student_filter;

		// Add course ID filter if provided.
		if ( ! empty( $course_id ) ) {
			$count_query .= $wpdb->prepare( ' AND e.course_id = %d ', $course_id );
		}

		if ( ! empty( $search ) ) {
			$count_query .= $wpdb->prepare(
				' AND (u.display_name LIKE %s OR u.user_email LIKE %s) ',
				'%' . $wpdb->esc_like( $search ) . '%',
				'%' . $wpdb->esc_like( $search ) . '%'
			);
		}

		$count_query = $this->get_filter_query( $count_query, $filter, $start_date, $end_date );

		$total_students = (int) $wpdb->get_var( $count_query );

		// Calculate total pages.
		$max_pages = $total_students ? ceil( $total_students / $per_page ) : 0;

		// Prepare response.
		$response_data = array();
		foreach ( $results as $row ) {
			$student_avatar_url = get_avatar_url( $row['user_id'], array( 'size' => 96 ) ); // You can adjust the size as needed.
			$last_login         = get_user_meta( $row['user_id'], '_ohmylms_last_login', true );
			$is_banned          = get_user_meta( $row['user_id'], '_ohmylms_banned_student', true );
			$response_data[]    = array(
				'user_id'             => $row['user_id'],
				'student_name'        => $row['student_name'],
				'student_email'       => $row['student_email'],
				'student_img'         => $student_avatar_url,
				'student_phone'       => get_user_meta( $row['user_id'], 'billing_phone', true ),
				'student_whatsapp'    => get_user_meta( $row['user_id'], 'whatsapp', true ),
				'student_timezone'    => get_user_meta( $row['user_id'], 'timezone', true ),
				'last_login'          => $last_login,
				'is_banned'           => 'yes' === $is_banned,
				'courses_enrolled'    => (int) $row['courses_enrolled'],
				'membership_enrolled' => (int) $row['membership_enrolled'],
				'registration_date'   => $row['registration_date'],
			);
		}

		// Create response object.
		$response = rest_ensure_response( $response_data );
		if ( ! empty( $course_name ) ) {
			$response->header( 'X-WP-Course-Name', $course_name );
		}
		$response->header( 'X-WP-Total', $total_students );
		$response->header( 'X-WP-TotalPages', $max_pages );

		return $response;
	}


	/**
	 * Get filter query for Student controller.
	 *
	 * @param mixed $query Query.
	 * @param mixed $filter Filter.
	 * @param mixed $start_date Start date.
	 * @param mixed $end_date End date.
	 */
	public function get_filter_query( $query, $filter, $start_date, $end_date ) {
		global $wpdb;
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

		if ( ! $start_date || ! $end_date ) {
			return $query;
		}
		$end_date = $end_date . ' 23:59:59'; // Append time to the end date.
		$query   .= $wpdb->prepare( ' AND COALESCE(e.start_date, u.user_registered) BETWEEN %s AND %s', $start_date, $end_date );

		return $query;
	}






	/**
	 * Ban a student from OhMyLMS
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 *
	 * @since 1.0.0
	 */
	public function ban_student( $request ) {
		$user_ids = $request->get_param( 'ids' );

		if ( empty( $user_ids ) ) {
			return new \WP_Error( 'ohmylms_rest_ohmylms-user_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		global $wpdb;
		$enrollment_table = $wpdb->prefix . 'ohmylms_user_enrollment';
		$membership_table = $wpdb->prefix . 'ohmylms_user_membership';

		foreach ( $user_ids as $user_id ) {
			if ( ! is_numeric( $user_id ) ) {
				return new \WP_Error( 'ohmylms_rest_ohmylms-user_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
			}

			// Update enrollment status to 'banned' instead of deleting.
			$wpdb->update(
				$enrollment_table,
				array( 'status' => 'banned' ),
				array( 'user_id' => $user_id ),
				array( '%s' ),
				array( '%d' )
			);

			// Update membership status to 'banned' instead of deleting.
			if ( ohmylms_is_pro() ) {
				$wpdb->update(
					$membership_table,
					array( 'status' => 'banned' ),
					array( 'user_id' => $user_id ),
					array( '%s' ),
					array( '%d' )
				);
			}

			// Update user meta by adding a new meta key.
			update_user_meta( $user_id, '_ohmylms_banned_student', 'yes' );
		}

		return rest_ensure_response(
			array(
				'message'  => __( 'Student blocked successfully.', 'ohmylms' ),
				'user_ids' => $user_ids,
				'status'   => 'success',
			)
		);
	}

	/**
	 * Unban a student from OhMyLMS
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 *
	 * @since 1.0.0
	 */
	public function unban_student( $request ) {
		$user_ids = $request->get_param( 'ids' );

		if ( empty( $user_ids ) ) {
			return new \WP_Error( 'ohmylms_rest_ohmylms-user_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		global $wpdb;
		$enrollment_table = $wpdb->prefix . 'ohmylms_user_enrollment';
		$membership_table = $wpdb->prefix . 'ohmylms_user_membership';

		foreach ( $user_ids as $user_id ) {
			if ( ! is_numeric( $user_id ) ) {
				return new \WP_Error( 'ohmylms_rest_ohmylms-user_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
			}

			// Restore enrollment status from 'banned' to 'enrolled'.
			$wpdb->update(
				$enrollment_table,
				array( 'status' => 'enrolled' ),
				array(
					'user_id' => $user_id,
					'status'  => 'banned',
				),
				array( '%s' ),
				array( '%d', '%s' )
			);

			// Restore membership status from 'banned' to 'enrolled'.
			if ( ohmylms_is_pro() ) {
				$wpdb->update(
					$membership_table,
					array( 'status' => 'enrolled' ),
					array(
						'user_id' => $user_id,
						'status'  => 'banned',
					),
					array( '%s' ),
					array( '%d', '%s' )
				);
			}

			// Remove the banned meta key.
			delete_user_meta( $user_id, '_ohmylms_banned_student' );
		}

		return rest_ensure_response(
			array(
				'message'  => __( 'Student unblocked successfully.', 'ohmylms' ),
				'user_ids' => $user_ids,
				'status'   => 'success',
			)
		);
	}
}
