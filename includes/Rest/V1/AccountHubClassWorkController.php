<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * AccountHubClassWorkController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Schools\Schema;
use OhMyLMS\Schools\Service;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Work distributed to a class under `ohmylms/v1/account-hub/classes/{id}` (docs/api/account-hub.md,
 * "Class work"): assign a course or one of its activities to the current roster, list what was assigned
 * with completion, and review and grade written submissions. Replaces the class routes of
 * `ohmylms/v1/school/*` that the deleted platform class editor used. Scope and permissions are the
 * Account Hub's; `Schools\Service` re-checks the class relationship, never enrols anyone and grades
 * through the assignment grading pipeline.
 *
 * @since 1.0.0
 */
class AccountHubClassWorkController extends AccountHubController {

	/**
	 * Register the routes.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		$base = '/' . $this->rest_base . '/classes/(?P<id>[\d]+)';
		$id   = array(
			'description' => __( 'Unique identifier.', 'ohmylms' ),
			'type'        => 'integer',
			'minimum'     => 1,
		);
		$read = array( $this, 'read_permissions_check' );
		$edit = array( $this, 'edit_permissions_check' );

		register_rest_route(
			$this->namespace,
			$base . '/assignments',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_assignments' ),
					'permission_callback' => $read,
					'args'                => $this->collection_args(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_assignment' ),
					'permission_callback' => $edit,
					'args'                => array(
						'course_id'        => array_merge( $id, array( 'required' => true ) ),
						'content_id'       => array_merge( $id, array( 'description' => __( 'One activity of the course; the whole course when omitted.', 'ohmylms' ) ) ),
						'title'            => array(
							'type'      => 'string',
							'maxLength' => 190,
							'default'   => '',
						),
						'due_at'           => array(
							'description' => __( 'Due date and time in the school\'s time zone (YYYY-MM-DDTHH:MM).', 'ohmylms' ),
							'type'        => 'string',
							'pattern'     => '^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2})?$',
							'default'     => '',
						),
						'prior_completion' => array(
							'description' => __( 'Count work the students completed before it was assigned.', 'ohmylms' ),
							'type'        => 'boolean',
							'default'     => false,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/courses',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_courses' ),
					'permission_callback' => $read,
					'args'                => $this->collection_args(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/courses/(?P<course>[\d]+)/activities',
			array(
				'args' => array(
					'id'     => $id,
					'course' => $id,
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_activities' ),
					'permission_callback' => $read,
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/submissions',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_submissions' ),
					'permission_callback' => $read,
					'args'                => array(
						'page' => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
							'maximum' => 10000,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/submissions/(?P<attempt>[\d]+)',
			array(
				'args' => array(
					'id'      => $id,
					'attempt' => $id,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'grade_submission' ),
					'permission_callback' => $edit,
					'args'                => array(
						'assignment_id' => array_merge( $id, array( 'required' => true ) ),
						'score'         => array(
							'type'     => 'integer',
							'minimum'  => 0,
							'required' => true,
						),
						'note'          => array(
							'type'      => 'string',
							'maxLength' => 2000,
							'default'   => '',
						),
					),
				),
			)
		);
	}

	/**
	 * Load an active class of an active school in scope.
	 *
	 * @param int $id Class id.
	 * @return array|WP_Error
	 */
	private function active_class_or_error( $id ) {
		$class = $this->class_or_error( $id );
		if ( is_wp_error( $class ) ) {
			return $class;
		}
		if ( 'active' !== $class['status'] ) {
			return $this->error( 'class_archived', __( 'This class is archived. Restore it first.', 'ohmylms' ), 409 );
		}

		return $class;
	}

	/**
	 * Private response.
	 *
	 * @param WP_REST_Response $response Response.
	 * @return WP_REST_Response
	 */
	private function no_store( WP_REST_Response $response ) {
		$response->header( 'Cache-Control', 'private, no-store' );

		return $response;
	}

	/**
	 * Work assigned to the class with recipients and completion, newest first.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_assignments( $request ) {
		global $wpdb;
		$class = $this->class_or_error( $request['id'] );
		if ( is_wp_error( $class ) ) {
			return $class;
		}
		$a = Schema::table( 'learning_assignments' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One count.
		$total  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE class_id=%d AND status='active' AND title LIKE %s", $a, $class['id'], $this->like( $request ) ) );
		$paging = $this->paging( $request, $total );
		if ( is_wp_error( $paging ) ) {
			return $paging;
		}
		list( $page, $per_page, $pages ) = $paging;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One page with grouped recipient counts.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT a.id,a.title,a.course_id,a.content_id,a.due_at,a.created_at,COUNT(r.id) AS recipients,SUM(CASE WHEN r.completed_at IS NOT NULL THEN 1 ELSE 0 END) AS completed FROM %i a LEFT JOIN %i r ON r.assignment_id=a.id WHERE a.class_id=%d AND a.status='active' AND a.title LIKE %s GROUP BY a.id ORDER BY a.id DESC LIMIT %d OFFSET %d", $a, Schema::table( 'assignment_recipients' ), $class['id'], $this->like( $request ), $per_page, ( $page - 1 ) * $per_page ), ARRAY_A );
		$data = array();
		foreach ( $rows as $row ) {
			$data[] = array(
				'id'            => (int) $row['id'],
				'title'         => (string) $row['title'],
				'course_id'     => (int) $row['course_id'],
				'course_title'  => html_entity_decode( get_the_title( (int) $row['course_id'] ), ENT_QUOTES, 'UTF-8' ),
				'content_id'    => (int) $row['content_id'],
				'content_title' => (int) $row['content_id'] ? html_entity_decode( get_the_title( (int) $row['content_id'] ), ENT_QUOTES, 'UTF-8' ) : null,
				'due_at'        => $this->iso( $row['due_at'] ),
				'created_at'    => $this->iso( $row['created_at'] ),
				'recipients'    => (int) $row['recipients'],
				'completed'     => (int) $row['completed'],
			);
		}

		return $this->collection( $data, $total, $pages );
	}

	/**
	 * Assign a published course or one of its activities to the class's current students.
	 * Everyone must already have course access; nobody is enrolled.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_assignment( $request ) {
		$class = $this->active_class_or_error( $request['id'] );
		if ( is_wp_error( $class ) ) {
			return $class;
		}
		if ( ! (int) $class['school_id'] ) {
			return $this->error( 'independent_class', __( 'Work is assigned to school classes only.', 'ohmylms' ), 422 );
		}
		$school = Service::row( 'schools', (int) $class['school_id'] );
		if ( ! $school || 'active' !== $school['status'] ) {
			return $this->error( 'school_inactive', __( 'This school is archived. Restore it first.', 'ohmylms' ), 409 );
		}
		$due = (string) $request['due_at'];
		try {
			$created = Service::create_assignment(
				(int) $class['id'],
				array(
					'course_id'        => (int) $request['course_id'],
					'content_id'       => (int) $request['content_id'],
					'title'            => $this->clean( $request['title'] ),
					'due_at'           => '' === $due ? '' : str_replace( 'T', ' ', $due ),
					'prior_completion' => (bool) $request['prior_completion'],
				)
			);
		} catch ( \Throwable $failure ) {
			return $this->service_error( $failure );
		}

		return $this->created(
			array(
				'id'         => (int) $created['id'],
				'recipients' => (int) $created['recipients'],
			),
			'/classes/' . (int) $class['id'] . '/assignments'
		);
	}

	/**
	 * Published courses to assign, by title.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_courses( $request ) {
		$class = $this->class_or_error( $request['id'] );
		if ( is_wp_error( $class ) ) {
			return $class;
		}
		$query  = new \WP_Query(
			array(
				'post_type'      => OHMYLMS_COURSE_CPT,
				'post_status'    => 'publish',
				'posts_per_page' => min( 100, max( 1, (int) $request['per_page'] ) ),
				'paged'          => max( 1, (int) $request['page'] ),
				's'              => $this->clean( $request['search'], 100 ),
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => false,
			)
		);
		$data   = array();
		foreach ( $query->posts as $post ) {
			$data[] = array(
				'id'    => (int) $post->ID,
				'title' => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			);
		}

		return $this->collection( $data, (int) $query->found_posts, (int) $query->max_num_pages );
	}

	/**
	 * Published activities of a published course (at most 200).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_activities( $request ) {
		global $wpdb;
		$class = $this->class_or_error( $request['id'] );
		if ( is_wp_error( $class ) ) {
			return $class;
		}
		$course = get_post( (int) $request['course'] );
		if ( ! $course || OHMYLMS_COURSE_CPT !== $course->post_type || 'publish' !== $course->post_status ) {
			return $this->error( 'course_not_found', __( 'Course not available.', 'ohmylms' ), 404 );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bounded outline of one course.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT p.ID AS id,p.post_title AS title FROM %i r JOIN %i c ON c.chapter_id=r.chapter_id JOIN %i p ON p.ID=r.content_id WHERE c.course_id=%d AND p.post_status='publish' ORDER BY p.post_title LIMIT 200", Schema::table( 'content_relationship' ), Schema::table( 'chapter_relationship' ), $wpdb->posts, $course->ID ), ARRAY_A );

		return rest_ensure_response(
			array_map(
				static function ( $row ) {
					return array(
						'id'    => (int) $row['id'],
						'title' => html_entity_decode( (string) $row['title'], ENT_QUOTES, 'UTF-8' ),
					);
				},
				$rows
			)
		);
	}

	/**
	 * Written submissions to the class's distributed activities (100 per page, newest first).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_submissions( $request ) {
		$class = $this->active_class_or_error( $request['id'] );
		if ( is_wp_error( $class ) ) {
			return $class;
		}
		$more = false;
		try {
			$rows = Service::submissions( (int) $class['id'], (int) $request['page'], $more );
		} catch ( \Throwable $failure ) {
			return $this->service_error( $failure );
		}
		$data = array();
		foreach ( $rows as $row ) {
			$data[] = array(
				'id'            => (int) $row['id'],
				'assignment_id' => (int) $row['learning_id'],
				'title'         => (string) $row['title'],
				'student_name'  => (string) $row['student_name'],
				'content'       => (string) $row['content'],
				'score'         => null === $row['score'] || '' === $row['score'] ? null : (int) $row['score'],
				'status'        => (string) $row['status'],
				'note'          => (string) $row['note'],
				'total_points'  => (int) $row['total_points'],
				'file_url'      => '' !== $row['file_url'] ? (string) $row['file_url'] : null,
			);
		}
		$response = rest_ensure_response(
			array(
				'items'    => $data,
				'has_more' => (bool) $more,
			)
		);

		return $this->no_store( $response );
	}

	/**
	 * Grade a written submission (whole points up to the activity total) with feedback.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function grade_submission( $request ) {
		$class = $this->active_class_or_error( $request['id'] );
		if ( is_wp_error( $class ) ) {
			return $class;
		}
		$learning = Service::row( 'learning_assignments', (int) $request['assignment_id'] );
		if ( ! $learning || (int) $learning['class_id'] !== (int) $class['id'] ) {
			return $this->error( 'submission_not_found', __( 'Submission not found.', 'ohmylms' ), 404 );
		}
		try {
			Service::grade(
				(int) $learning['id'],
				(int) $request['attempt'],
				array(
					'score' => (int) $request['score'],
					'note'  => (string) $request['note'],
				)
			);
		} catch ( \Throwable $failure ) {
			if ( $failure instanceof \RuntimeException && 403 === (int) $failure->getCode() ) {
				return $this->error( 'submission_not_found', __( 'Submission not found.', 'ohmylms' ), 404 );
			}

			return $this->service_error( $failure );
		}
		$attempt = Service::row( 'assignment_attempts', (int) $request['attempt'] );

		return rest_ensure_response(
			array(
				'graded' => true,
				'id'     => (int) $request['attempt'],
				'score'  => (int) ( $attempt['score'] ?? 0 ),
				'status' => (string) ( $attempt['status'] ?? '' ),
			)
		);
	}
}
