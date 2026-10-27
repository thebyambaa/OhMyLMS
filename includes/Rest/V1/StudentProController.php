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
class StudentProController extends RestController {

	/**
	 * The base route for student base endpoints.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $base = 'students';

	/**
	 * Check if the current user may view student records.
	 *
	 * Mirrors the gate used by the core StudentController: student data is
	 * site wide personal data, so it is not exposed to every user who merely
	 * holds edit_posts.
	 *
	 * @return bool
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
			'/' . $this->base . '/(?P<id>[\d]+)',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the student.', 'ohmylms' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'check_student_permission' ),
				),
			)
		);
	}

	/**
	 * Get item for Student pro controller.
	 *
	 * @param mixed $request REST request.
	 */
	public function get_item( $request ) {
		global $wpdb;

		// Get the student ID from the request.
		$user_id = $request->get_param( 'id' );

		if ( empty( $user_id ) || ! is_numeric( $user_id ) ) {
			return new WP_Error( 'invalid_user_id', 'Invalid or missing user ID.', array( 'status' => 400 ) );
		}

		$filter          = $request->get_param( 'filter' ) ?? 'all';
		$start_date      = $request->get_param( 'start_date' );
		$end_date        = $request->get_param( 'end_date' );
		$completion_type = $request->get_param( 'completion_type' );
		$search_query    = strtolower( trim( $request->get_param( 'search' ) ?? '' ) );

		$only_completed = null;

		if ( 'completed' === $completion_type ) {
			$only_completed = true;
		} elseif ( 'not_completed' === $completion_type ) {
			$only_completed = false;
		}

		$date_condition = '';

		if ( 'all' !== $filter ) {
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
					if ( ! $start_date || ! $end_date ) {
						return new WP_Error( 'invalid_date', 'Start and End date are required for custom filter.', array( 'status' => 400 ) );
					}
					break;

				default:
					return new WP_Error( 'invalid_filter', 'Invalid filter type.', array( 'status' => 400 ) );
			}
		}

		// Get the student's name and email from wp_users table.
		$user_info = get_user_by( 'ID', $user_id );
		if ( ! $user_info ) {
			return new WP_Error( 'user_not_found', 'User not found.', array( 'status' => 404 ) );
		}
		$student_name  = $user_info->display_name;
		$student_email = $user_info->user_email;
		// Get the student's avatar image URL (if available).
		$student_avatar_url = get_avatar_url( $user_id, array( 'size' => 96 ) ); // You can adjust the size as needed.

		// Get the enrollment date from the ohmylms_user_enrollment table.
		$enrollment_table = $wpdb->prefix . 'ohmylms_user_enrollment';
		$enrollment       = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT start_date
                FROM %i
                WHERE user_id = %d',
				$enrollment_table,
				$user_id
			),
			ARRAY_A
		);

		$enrollment_date = isset( $enrollment['start_date'] ) ? $enrollment['start_date'] : '';

		$student             = new \OhMyLMS\Data\Student( $user_id );
		$enrolled_courses    = $student->get_enrolled_course_count();
		$in_progress_courses = $student->get_progress_course_count();
		$completed_courses   = $student->get_completed_course_count();

		$courses     = $student->get_courses();
		$course_data = array();

		if ( is_array( $courses ) ) {
			foreach ( $courses as $course ) {

				if ( ! $course instanceof Course ) {
					continue;
				}

				$course_id = $course->get_id();

				// Query the ohmylms_user_progress table for completed items for the specific course.
				$enrollment_table = $wpdb->prefix . 'ohmylms_user_enrollment';
				$enrollment       = $wpdb->get_row(
					$wpdb->prepare(
						'SELECT *
                        FROM %i
                        WHERE user_id = %d AND course_id = %d',
						$enrollment_table,
						$user_id,
						$course_id
					),
					ARRAY_A // To return the result as an associative array.
				);

				// If no enrollment found, skip to the next course.
				if ( empty( $enrollment ) ) {
					continue;
				}

				if ( 'all' !== $filter && $enrollment ) {
					$enrolled_on = ( new \DateTime( $enrollment['start_date'] ) )->format( 'Y-m-d' );
					if ( $enrolled_on < $start_date || $enrolled_on > $end_date ) {
						continue; // Skip course outside the date range.
					}
				}

				// Query the progress table for completed items using the enrollment ID.
				$progress_table  = $wpdb->prefix . 'ohmylms_user_progress';
				$completed_items = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT content_type, COUNT(*) as count
                         FROM %i
                         WHERE enrollment_id = %d AND status = \'completed\'
                         GROUP BY content_type',
						$progress_table,
						$enrollment['id']
					),
					ARRAY_A
				);

				// Initialize completed counts for the course.
				$completed_lesson     = 0;
				$completed_assignment = 0;
				$completed_quiz       = 0;

				// Process completed items.
				foreach ( $completed_items as $item ) {
					switch ( $item['content_type'] ) {
						case 'text':
						case 'video':
						case 'audio':
							$completed_lesson += $item['count'];
							break;
						case 'quiz':
							$completed_quiz += $item['count'];
							break;
						case 'assignment':
							$completed_assignment += $item['count'];
							break;
					}
				}

				$course_name = $course->get_name();

				// If there's a search query, skip courses that don't match the name.
				if ( ! empty( $search_query ) && strpos( strtolower( $course_name ), $search_query ) === false ) {
					continue;
				}

				$course_data[] = array(
					'course_id'               => $course->get_id(),
					'image'                   => $course->get_thumbnail_url(),
					'categories'              => $this->get_taxonomy_terms( $course ),
					'course_name'             => $course_name,
					'enrollment_date'         => $enrollment['start_date'],
					'course_lesson_count'     => $course->get_lessons_count(),
					'course_assignment_count' => $course->get_assignment_count(),
					'course_quiz_count'       => $course->get_quiz_count(),
					'completed_lesson'        => $completed_lesson,
					'completed_assignment'    => $completed_assignment,
					'completed_quiz'          => $completed_quiz,
					'total_points'            => $student->get_course_total_points( $course_id ),
					'completed_points'        => $student->get_course_completed_points( $course_id ),
					'is_completed'            => (int) $student->get_course_completed_points( $course_id ) === (int) $student->get_course_total_points( $course_id ) ? true : false,
				);

				if ( null !== $only_completed ) {
					// Filter only completed course_data if requested.
					if ( $only_completed ) {
						$course_data = array_filter(
							$course_data,
							function ( $student ) {
								return true === $student['is_completed'];
							}
						);
						$course_data = array_values( $course_data ); // Reindex array.
					}

					if ( ! $only_completed ) {
						$course_data = array_filter(
							$course_data,
							function ( $student ) {
								return false === $student['is_completed'];
							}
						);
						$course_data = array_values( $course_data ); // Reindex array.
					}
				}
			}
		}

		// Fetch the membership data from ohmylms_user_membership table.
		$membership_table = $wpdb->prefix . 'ohmylms_user_membership';
		$membership_data  = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT membership_id as id
                 FROM %i
                 WHERE user_id = %d',
				$membership_table,
				$user_id
			),
			ARRAY_A
		);
		$total_membership = 0;
		$membership_info  = array();
		if ( ! empty( $membership_data ) ) {
			$total_membership = count( $membership_data );
			foreach ( $membership_data as $single_membership ) {
				$membership = ohmylms_get_membership( $single_membership['id'] );

				if ( ! $membership ) {
					continue;
				}

				$membership_info[] = array(
					'membership_id'     => $single_membership['id'],
					'membership_name'   => $membership->get_name(),
					'date_created'      => $membership->get_date_created(),
					'number_of_courses' => count( $membership->get_products() ),
					'courses'           => $membership->get_products(),
					'regular_price'     => $membership->get_regular_price(),
					'period'            => $membership->get_subscription_period(),
				);
			}
		}

		// Prepare the response.
		$response = array(
			'user_id'             => $user_id,
			'student_name'        => $student_name,
			'student_email'       => $student_email,
			'student_phone'       => $student->get_phone(),
			'student_whatsapp'    => $student->get_whatsapp(),
			'student_timezone'    => $student->get_timezone(),
			'student_img'         => $student_avatar_url,
			'enrollment_date'     => $enrollment_date,
			'enrolled_courses'    => $enrolled_courses,
			'in_progress_courses' => $in_progress_courses,
			'completed_courses'   => $completed_courses,
			'total_membership'    => $total_membership,
			'courses'             => $course_data,
			'memberships'         => $membership_info,
			'currency'            => html_entity_decode( get_ohmylms_currency_symbol( get_ohmylms_currency() ) ),
			'currency_pos'        => get_ohmylms_currency_position(),
		);

		return rest_ensure_response( $response );
	}


	/**
	 * Get taxonomy terms for a course.
	 *
	 * @param Course $course The course object.
	 * @param string $taxonomy The taxonomy to retrieve terms for. Default is 'category'.
	 * @return array The array of terms with their IDs and names.
	 *
	 * @since 1.0.0
	 */
	protected function get_taxonomy_terms( $course, $taxonomy = 'category' ) {
		// Course categories and tags were replaced by the curriculum and Course Bundles; the response
		// keys are unchanged.
		return \OhMyLMS\Curriculum\Placement::card_terms( $course->get_id(), 'tag' === $taxonomy );
	}
}
