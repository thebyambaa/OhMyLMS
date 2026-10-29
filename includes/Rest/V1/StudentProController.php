<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * StudentProController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * StudentProController class.
 *
 * Enrolments of one student under `ohmylms/v1/students/{id}/enrollments`: list with progress, enrol
 * and unenrol. See docs/api/students.md. Every route needs StudentController::CAPABILITY.
 *
 * @since 1.0.0
 */
class StudentProController extends RestController {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * The base route for student endpoints.
	 *
	 * @var string
	 */
	protected $rest_base = 'students';

	/**
	 * Register the routes for the enrolment endpoints.
	 */
	public function register_routes() {
		$collection = '/' . $this->rest_base . '/(?P<id>[\d]+)/enrollments';
		$student    = array(
			'description' => __( 'Unique identifier for the student (the user ID).', 'ohmylms' ),
			'type'        => 'integer',
			'minimum'     => 1,
		);
		$course     = array(
			'description' => __( 'Unique identifier for the course.', 'ohmylms' ),
			'type'        => 'integer',
			'minimum'     => 1,
		);

		register_rest_route(
			$this->namespace,
			$collection,
			array(
				'args'   => array( 'id' => $student ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => array(
						'course_id' => $course + array( 'required' => true ),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			$collection . '/(?P<course_id>[\d]+)',
			array(
				'args'   => array(
					'id'        => $student,
					'course_id' => $course,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Check if the current user can list a student's enrolments.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return StudentController::authorize( 'ohmylms_enrollment_cannot_view', __( 'Sorry, you are not allowed to view enrolments.', 'ohmylms' ) );
	}

	/**
	 * Check if the current user can enrol a student.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		return StudentController::authorize( 'ohmylms_enrollment_cannot_create', __( 'Sorry, you are not allowed to enrol students.', 'ohmylms' ) );
	}

	/**
	 * Check if the current user can unenrol a student.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		return StudentController::authorize( 'ohmylms_enrollment_cannot_delete', __( 'Sorry, you are not allowed to unenrol students.', 'ohmylms' ) );
	}

	/**
	 * Enrolments table name.
	 *
	 * @return string
	 */
	private function table() {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_user_enrollment';
	}

	/**
	 * Build the not-found error for an enrolment.
	 *
	 * @return WP_Error
	 */
	private function enrollment_not_found() {
		return new WP_Error( 'ohmylms_enrollment_not_found', __( 'Enrolment not found.', 'ohmylms' ), array( 'status' => 404 ) );
	}

	/**
	 * Get a student's enrolments with progress.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		global $wpdb;

		$students = new StudentController();
		$student  = $students->find_student( $request['id'] );
		if ( is_wp_error( $student ) ) {
			return $student;
		}

		$user_id  = (int) $student['id'];
		$page     = max( 1, (int) $request['page'] );
		$per_page = min( 100, max( 1, (int) $request['per_page'] ) );

		$from = " FROM %i e INNER JOIN %i p ON p.ID = e.course_id AND p.post_type = %s WHERE e.user_id = %d AND e.status IN ('enrolled','banned')";
		$args = array( $this->table(), $wpdb->posts, StudentController::COURSE_POST_TYPE, $user_id );
		if ( in_array( $request['progress'], array( 'running', 'completed' ), true ) ) {
			$from  .= ' AND e.progress = %s';
			$args[] = $request['progress'];
		}
		if ( '' !== (string) $request['search'] ) {
			$from  .= ' AND p.post_title LIKE %s';
			$args[] = '%' . $wpdb->esc_like( (string) $request['search'] ) . '%';
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Prepared with %i identifiers.
		$total       = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*)' . $from, $args ) );
		$total_pages = (int) ceil( $total / $per_page );
		if ( $total > 0 && $page > $total_pages ) {
			return new WP_Error( 'ohmylms_enrollment_invalid_page', __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$args[] = $per_page;
		$args[] = ( $page - 1 ) * $per_page;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Prepared with %i identifiers.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT e.id, e.course_id, e.status, e.progress, e.start_date, e.end_date, p.post_title' . $from . ' ORDER BY e.start_date DESC, e.id DESC LIMIT %d OFFSET %d', $args ), ARRAY_A );

		$data = array();
		foreach ( $this->describe_rows( is_array( $rows ) ? $rows : array() ) as $item ) {
			$data[] = $this->prepare_response_for_collection( $this->prepare_item_for_response( $item, $request ) );
		}

		$response = rest_ensure_response( $data );
		$students->add_pagination_headers( $response, $request, $total, $total_pages, $page, $this->rest_base . '/' . $user_id . '/enrollments' );
		return $response;
	}

	/**
	 * Add completed-item counts and course totals to enrolment rows (one progress query per page).
	 *
	 * @param array[] $rows Enrolment rows joined with the course title.
	 * @return array[]
	 */
	private function describe_rows( array $rows ) {
		global $wpdb;

		if ( ! $rows ) {
			return array();
		}

		$ids          = array_map( 'intval', wp_list_pluck( $rows, 'id' ) );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Placeholders are generated from the id count.
		$done = $wpdb->get_results( $wpdb->prepare( "SELECT enrollment_id, content_type, COUNT(DISTINCT content_id) AS total FROM %i WHERE status = 'completed' AND enrollment_id IN ($placeholders) GROUP BY enrollment_id, content_type", array_merge( array( $wpdb->prefix . 'ohmylms_user_progress' ), $ids ) ), ARRAY_A );

		$completed = array();
		foreach ( is_array( $done ) ? $done : array() as $row ) {
			$kind = in_array( $row['content_type'], array( 'quiz', 'assignment' ), true ) ? $row['content_type'] : 'lesson';

			$completed[ (int) $row['enrollment_id'] ][ $kind ] = ( $completed[ (int) $row['enrollment_id'] ][ $kind ] ?? 0 ) + (int) $row['total'];
		}

		$items = array();
		foreach ( $rows as $row ) {
			$course_id = (int) $row['course_id'];
			$course    = function_exists( 'ohmylms_get_course' ) ? ohmylms_get_course( $course_id ) : null;
			$totals    = array(
				'lesson'     => $course ? (int) $course->get_lessons_count() : 0,
				'quiz'       => $course ? (int) $course->get_quiz_count() : 0,
				'assignment' => $course ? (int) $course->get_assignment_count() : 0,
			);
			$mine      = $completed[ (int) $row['id'] ] ?? array();

			$parts = array();
			$sum   = 0;
			$all   = 0;
			foreach ( $totals as $kind => $total ) {
				$done_count     = min( $total, (int) ( $mine[ $kind ] ?? 0 ) );
				$parts[ $kind ] = array(
					'completed' => $done_count,
					'total'     => $total,
				);
				$sum           += $done_count;
				$all           += $total;
			}

			$finished = 'completed' === $row['progress'];
			$percent  = $finished ? 100 : ( $all > 0 ? (int) floor( 100 * $sum / $all ) : 0 );

			$items[] = array(
				'course_id'        => $course_id,
				'title'            => html_entity_decode( (string) $row['post_title'], ENT_QUOTES, 'UTF-8' ),
				'status'           => 'banned' === $row['status'] ? 'banned' : 'enrolled',
				'progress'         => $finished ? 'completed' : 'running',
				'enrolled_at'      => StudentController::format_local( $row['start_date'] ),
				'completed_at'     => $finished ? StudentController::format_local( $row['end_date'] ) : null,
				'lessons'          => $parts['lesson'],
				'quizzes'          => $parts['quiz'],
				'assignments'      => $parts['assignment'],
				'percent_complete' => $percent,
			);
		}
		return $items;
	}

	/**
	 * Load one enrolment (with progress) of a student.
	 *
	 * @param int $user_id   Student ID.
	 * @param int $course_id Course ID.
	 * @return array|null
	 */
	private function find_enrollment( $user_id, $course_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- No API for enrolment rows.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT e.id, e.course_id, e.status, e.progress, e.start_date, e.end_date, p.post_title FROM %i e LEFT JOIN %i p ON p.ID = e.course_id WHERE e.user_id = %d AND e.course_id = %d ORDER BY e.id ASC LIMIT 1', $this->table(), $wpdb->posts, $user_id, $course_id ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		$items = $this->describe_rows( array( $row ) );
		return $items[0];
	}

	/**
	 * Enrol a student in a course.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		global $wpdb;

		$user_id   = absint( $request['id'] );
		$course_id = absint( $request['course_id'] );

		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			return StudentController::not_found_error();
		}

		$course = $course_id ? get_post( $course_id ) : null;
		if ( ! $course || StudentController::COURSE_POST_TYPE !== $course->post_type || in_array( $course->post_status, array( 'trash', 'auto-draft' ), true ) ) {
			return new WP_Error( 'ohmylms_course_not_found', __( 'Course not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		if ( 'yes' === get_user_meta( $user_id, StudentController::BLOCKED_META, true ) ) {
			return new WP_Error( 'ohmylms_student_blocked', __( 'This student is blocked. Unblock the student before enrolling them.', 'ohmylms' ), array( 'status' => 409 ) );
		}

		$table = $this->table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- No API for enrolment rows.
		if ( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d AND course_id = %d', $table, $user_id, $course_id ) ) > 0 ) {
			return new WP_Error( 'ohmylms_student_already_enrolled', __( 'The student is already enrolled in this course.', 'ohmylms' ), array( 'status' => 409 ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- No API for enrolment rows.
		$inserted = $wpdb->insert(
			$table,
			array(
				'user_id'    => $user_id,
				'course_id'  => $course_id,
				'status'     => 'enrolled',
				'progress'   => 'running',
				'start_date' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);
		if ( ! $inserted ) {
			return new WP_Error( 'ohmylms_enrollment_create_failed', __( 'The enrolment could not be saved.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		// Two simultaneous requests can both pass the check above: the later row gives way.
		$row_id = (int) $wpdb->insert_id;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- No API for enrolment rows.
		if ( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d AND course_id = %d', $table, $user_id, $course_id ) ) > 1 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- No API for enrolment rows.
			$wpdb->delete( $table, array( 'id' => $row_id ), array( '%d' ) );
			return new WP_Error( 'ohmylms_student_already_enrolled', __( 'The student is already enrolled in this course.', 'ohmylms' ), array( 'status' => 409 ) );
		}

		update_user_meta( $user_id, '_is_ohmylms_student', 'yes' );
		update_user_meta( $user_id, '_is_ohmylms_user', 'yes' );

		/**
		 * Fires after an administrator enrolled a student by hand.
		 *
		 * @param int $user_id   Student (user) ID.
		 * @param int $course_id Course ID.
		 */
		do_action( 'ohmylms_manual_student_enrollment', $user_id, $course_id );

		$response = $this->prepare_item_for_response( $this->find_enrollment( $user_id, $course_id ), $request );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( $this->namespace . '/' . $this->rest_base . '/' . $user_id . '/enrollments' ) );
		return $response;
	}

	/**
	 * Unenrol a student: removes the enrolment and its progress records.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		global $wpdb;

		$user_id   = absint( $request['id'] );
		$course_id = absint( $request['course_id'] );

		$previous = $this->find_enrollment( $user_id, $course_id );
		if ( ! $previous ) {
			return $this->enrollment_not_found();
		}

		$table = $this->table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- No API for enrolment rows.
		$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE user_id = %d AND course_id = %d', $table, $user_id, $course_id ) ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- No API for enrolment rows.
		if ( false === $wpdb->delete(
			$table,
			array(
				'user_id'   => $user_id,
				'course_id' => $course_id,
			),
			array( '%d', '%d' )
		) ) { // phpcs:ignore WordPress.Arrays.MultipleStatementAlignment
			return new WP_Error( 'ohmylms_enrollment_delete_failed', __( 'The enrolment could not be removed.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		// Progress records of a removed enrolment would be orphans; an unfinished delete leaves only those.
		foreach ( $ids as $enrollment_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- No API for progress rows.
			$wpdb->delete( $wpdb->prefix . 'ohmylms_user_progress', array( 'enrollment_id' => $enrollment_id ), array( '%d' ) );
		}

		/**
		 * Fires after a student's enrolment was removed.
		 *
		 * @param int $user_id   Student (user) ID.
		 * @param int $course_id Course ID.
		 */
		do_action( 'ohmylms_student_unenrolled', $user_id, $course_id );

		$response = new WP_REST_Response(
			array(
				'deleted'  => true,
				'previous' => $this->prepare_response_for_collection( $this->prepare_item_for_response( $previous, $request ) ),
			)
		);
		return $response;
	}

	/**
	 * Matches the REST schema: turn a described enrolment into a response.
	 *
	 * @param array           $item    Output of describe_rows().
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $item, $request );
		$data    = $this->filter_response_by_context( $data, $context );
		return rest_ensure_response( $data );
	}

	/**
	 * Get the enrolment schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$counter = static function ( $description ) {
			return array(
				'description' => $description,
				'type'        => 'object',
				'context'     => array( 'view' ),
				'readonly'    => true,
				'properties'  => array(
					'completed' => array( 'type' => 'integer' ),
					'total'     => array( 'type' => 'integer' ),
				),
			);
		};

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'student-enrollment',
			'type'       => 'object',
			'properties' => array(
				'course_id'        => array(
					'description' => __( 'Course ID.', 'ohmylms' ),
					'type'        => 'integer',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'title'            => array(
					'description' => __( 'Course title.', 'ohmylms' ),
					'type'        => 'string',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'status'           => array(
					'description' => __( 'Access: enrolled, or banned while the student is blocked.', 'ohmylms' ),
					'type'        => 'string',
					'enum'        => array( 'enrolled', 'banned' ),
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'progress'         => array(
					'description' => __( 'Learning state.', 'ohmylms' ),
					'type'        => 'string',
					'enum'        => array( 'running', 'completed' ),
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'enrolled_at'      => array(
					'description' => __( 'Enrolment date in site time.', 'ohmylms' ),
					'type'        => array( 'string', 'null' ),
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'completed_at'     => array(
					'description' => __( 'Completion date in site time, or null.', 'ohmylms' ),
					'type'        => array( 'string', 'null' ),
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'lessons'          => $counter( __( 'Completed and total lessons.', 'ohmylms' ) ),
				'quizzes'          => $counter( __( 'Completed and total quizzes.', 'ohmylms' ) ),
				'assignments'      => $counter( __( 'Completed and total assignments.', 'ohmylms' ) ),
				'percent_complete' => array(
					'description' => __( 'Completed share of all items, 0 to 100.', 'ohmylms' ),
					'type'        => 'integer',
					'minimum'     => 0,
					'maximum'     => 100,
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Get the collection parameters.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params = array_intersect_key( parent::get_collection_params(), array_flip( array( 'context', 'page', 'per_page', 'search' ) ) );

		$params['per_page']['default']   = 20;
		$params['per_page']['maximum']   = 100;
		$params['search']['description'] = __( 'Limit results to courses whose title contains the string.', 'ohmylms' );
		$params['search']['maxLength']   = 100;

		$params['progress'] = array(
			'description' => __( 'Limit results to enrolments in one learning state.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'all',
			'enum'        => array( 'all', 'running', 'completed' ),
		);

		return $params;
	}
}
