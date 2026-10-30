<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * CourseRosterController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Analytics\Reminders;
use OhMyLMS\Analytics\Repository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Writes of the course roster under `ohmylms/v1/analytics/courses/{id}`: enrol by e-mail, bulk unenrol
 * and fixed-template reminders. The roster itself is read from DashboardProController. Enrolment changes
 * are dispatched to the student enrolment routes, so their validation and hooks apply unchanged.
 * See docs/api/students.md ("Course roster").
 *
 * @since 1.0.0
 */
class CourseRosterController extends RestController {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * The base route.
	 *
	 * @var string
	 */
	protected $rest_base = 'analytics/courses';

	/**
	 * Most students of one bulk request.
	 */
	const MAX_BULK = 50;

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$id   = array(
			'id' => array(
				'description' => __( 'Course ID.', 'ohmylms' ),
				'type'        => 'integer',
				'minimum'     => 1,
			),
		);
		$base = '/' . $this->rest_base . '/(?P<id>[\d]+)';

		register_rest_route(
			$this->namespace,
			$base . '/enrollments',
			array(
				'args' => $id,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'enroll_by_email' ),
					'permission_callback' => array( $this, 'edit_permissions_check' ),
					'args'                => array(
						'email' => array(
							'description' => __( 'E-mail address of an existing user.', 'ohmylms' ),
							'type'        => 'string',
							'format'      => 'email',
							'maxLength'   => 100,
							'required'    => true,
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/enrollments/bulk',
			array(
				'args' => $id,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'bulk' ),
					'permission_callback' => array( $this, 'edit_permissions_check' ),
					'args'                => array(
						'action'   => array(
							'description' => __( 'Bulk action.', 'ohmylms' ),
							'type'        => 'string',
							'enum'        => array( 'unenroll' ),
							'required'    => true,
						),
						'user_ids' => $this->user_ids_arg( self::MAX_BULK ),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/reminders',
			array(
				'args' => $id,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'remind' ),
					'permission_callback' => array( $this, 'remind_permissions_check' ),
					'args'                => array(
						'template' => array(
							'description' => __( 'Fixed reminder template.', 'ohmylms' ),
							'type'        => 'string',
							'enum'        => Reminders::TEMPLATES,
							'required'    => true,
						),
						'user_ids' => $this->user_ids_arg( Reminders::MAX_BATCH ),
					),
				),
			)
		);
	}

	/**
	 * Schema of a list of student IDs.
	 *
	 * @param int $max Most items.
	 * @return array
	 */
	private function user_ids_arg( $max ) {
		return array(
			'description' => __( 'Student (user) IDs.', 'ohmylms' ),
			'type'        => 'array',
			'items'       => array(
				'type'    => 'integer',
				'minimum' => 1,
			),
			'minItems'    => 1,
			'maxItems'    => $max,
			'uniqueItems' => true,
			'required'    => true,
		);
	}

	/**
	 * Check the student capability for enrolment changes.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function edit_permissions_check( $request ) {
		return StudentController::authorize( 'ohmylms_roster_cannot_edit', __( 'Sorry, you are not allowed to change enrolments.', 'ohmylms' ) );
	}

	/**
	 * Check the student capability for reminders.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function remind_permissions_check( $request ) {
		return StudentController::authorize( 'ohmylms_reminder_cannot_send', __( 'Sorry, you are not allowed to send reminders.', 'ohmylms' ) );
	}

	/**
	 * Load the course of the request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return int|WP_Error Course ID or 404.
	 */
	private function course_id( $request ) {
		$post = get_post( absint( $request['id'] ) );
		if ( ! $post || Repository::COURSE_TYPE !== $post->post_type || ! in_array( $post->post_status, Repository::COURSE_STATUSES, true ) ) {
			return new WP_Error( 'ohmylms_analytics_course_not_found', __( 'Course not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		return (int) $post->ID;
	}

	/**
	 * Dispatch a request to a student enrolment route.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route below the namespace.
	 * @param array  $body   Body parameters.
	 * @return WP_REST_Response
	 */
	private function dispatch( $method, $route, array $body = array() ) {
		$inner = new WP_REST_Request( $method, '/' . $this->namespace . '/' . $route );
		if ( $body ) {
			$inner->set_body_params( $body );
		}
		return rest_get_server()->dispatch( $inner );
	}

	/**
	 * Enrol an existing user, found by e-mail address.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function enroll_by_email( $request ) {
		$course_id = $this->course_id( $request );
		if ( is_wp_error( $course_id ) ) {
			return $course_id;
		}
		$user = get_user_by( 'email', sanitize_email( (string) $request['email'] ) );
		if ( ! $user ) {
			return new WP_Error( 'ohmylms_roster_user_not_found', __( 'No user has this e-mail address. Ask the student to register first.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		$result = $this->dispatch( 'POST', 'students/' . (int) $user->ID . '/enrollments', array( 'course_id' => $course_id ) );
		if ( $result->is_error() ) {
			return $result->as_error();
		}
		$response = rest_ensure_response(
			array(
				'user_id'    => (int) $user->ID,
				'name'       => (string) $user->display_name,
				'enrollment' => $result->get_data(),
			)
		);
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Apply a bulk action; each student succeeds or fails on its own.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function bulk( $request ) {
		$course_id = $this->course_id( $request );
		if ( is_wp_error( $course_id ) ) {
			return $course_id;
		}

		$results = array();
		foreach ( array_unique( array_map( 'absint', (array) $request['user_ids'] ) ) as $user_id ) {
			$result    = $this->dispatch( 'DELETE', 'students/' . $user_id . '/enrollments/' . $course_id );
			$error     = $result->is_error() ? $result->as_error() : null;
			$results[] = array(
				'user_id' => $user_id,
				'ok'      => null === $error,
				'code'    => $error ? $error->get_error_code() : null,
			);
		}
		return rest_ensure_response(
			array(
				'action'  => 'unenroll',
				'results' => $results,
			)
		);
	}

	/**
	 * Send a fixed reminder to enrolled students.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function remind( $request ) {
		$course_id = $this->course_id( $request );
		if ( is_wp_error( $course_id ) ) {
			return $course_id;
		}
		$result = Reminders::send( $course_id, (array) $request['user_ids'], (string) $request['template'], get_current_user_id() );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}
}
