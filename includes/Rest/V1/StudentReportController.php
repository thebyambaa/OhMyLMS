<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * StudentReportController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Assignment\Submissions;
use OhMyLMS\Performance\Levels;
use OhMyLMS\Performance\Snapshot;
use OhMyLMS\Quiz\AttemptReports;
use OhMyLMS\Student\Enrollments;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Read-only report of one student under `ohmylms/v1/students/{id}`: a summary, quiz attempts,
 * assignment submissions and skill progress. See docs/api/students.md. Every route needs
 * StudentController::CAPABILITY and answers 404 for users who are not students.
 *
 * @since 1.0.0
 */
class StudentReportController extends RestController {

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
	protected $rest_base = 'students';

	/**
	 * Largest page of a report collection.
	 */
	const MAX_PER_PAGE = 50;

	/**
	 * Skill filters accepted by `/skills`.
	 */
	const SKILL_FILTERS = array( 'all', 'review_due', 'struggling' );

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$id   = array(
			'id' => array(
				'description' => __( 'Unique identifier for the student (the user ID).', 'ohmylms' ),
				'type'        => 'integer',
				'minimum'     => 1,
			),
		);
		$base = '/' . $this->rest_base . '/(?P<id>[\d]+)';

		register_rest_route(
			$this->namespace,
			$base . '/report',
			array(
				'args' => $id,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_summary' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/quiz-attempts',
			array(
				'args' => $id,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_quiz_attempts' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/assignment-submissions',
			array(
				'args' => $id,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_assignment_submissions' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$base . '/skills',
			array(
				'args' => $id,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_skills' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array_merge(
						$this->get_collection_params(),
						array(
							'filter' => array(
								'description' => __( 'Limit results to skills due for review or where the student struggles.', 'ohmylms' ),
								'type'        => 'string',
								'default'     => 'all',
								'enum'        => self::SKILL_FILTERS,
							),
						)
					),
				),
			)
		);
	}

	/**
	 * Check the shared student capability.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return StudentController::authorize( 'ohmylms_student_report_cannot_view', __( 'Sorry, you are not allowed to view student reports.', 'ohmylms' ) );
	}

	/**
	 * Paging arguments.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'page'     => array(
				'description' => __( 'Current page of the collection.', 'ohmylms' ),
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
			),
			'per_page' => array(
				'description' => __( 'Maximum number of items to be returned in result set.', 'ohmylms' ),
				'type'        => 'integer',
				'default'     => 10,
				'minimum'     => 1,
				'maximum'     => self::MAX_PER_PAGE,
			),
		);
	}

	/**
	 * Resolve the student of the request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return int|WP_Error User ID or 404.
	 */
	private function student_id( $request ) {
		$student = ( new StudentController() )->find_student( $request['id'] );
		return is_wp_error( $student ) ? $student : (int) $student['id'];
	}

	/**
	 * Page and size of the request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return int[] Page and per-page.
	 */
	private function paging( $request ) {
		return array(
			max( 1, (int) $request['page'] ),
			min( self::MAX_PER_PAGE, max( 1, (int) $request['per_page'] ) ),
		);
	}

	/**
	 * Build a paged response, or the past-the-end error.
	 *
	 * @param WP_REST_Request $request  Request.
	 * @param array[]         $items    Page items.
	 * @param int             $total    Total items.
	 * @param int             $page     Current page.
	 * @param int             $per_page Page size.
	 * @param string          $route    Route below the namespace.
	 * @return WP_REST_Response
	 */
	private function paged( $request, array $items, $total, $page, $per_page, $route ) {
		$response = rest_ensure_response( $items );
		( new StudentController() )->add_pagination_headers( $response, $request, $total, (int) ceil( $total / $per_page ), $page, $route );
		return $response;
	}

	/**
	 * The past-the-end error.
	 *
	 * @return WP_Error
	 */
	private function invalid_page() {
		return new WP_Error( 'ohmylms_student_report_invalid_page', __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), array( 'status' => 400 ) );
	}

	/**
	 * Summary of the report.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_summary( $request ) {
		$user_id = $this->student_id( $request );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$courses   = Enrollments::course_counts( $user_id );
		$quizzes   = AttemptReports::student_summary( $user_id );
		$enrolled  = $courses['enrolled'];
		$completed = $courses['completed'];
		$average   = $quizzes['average'];

		return rest_ensure_response(
			array(
				'student_id'  => $user_id,
				'courses'     => array(
					'enrolled'    => $enrolled,
					'completed'   => $completed,
					'in_progress' => max( 0, $enrolled - $completed ),
				),
				'quizzes'     => array(
					'attempts'        => $quizzes['attempts'],
					'in_review'       => $quizzes['in_review'],
					'average_percent' => null === $average ? null : round( (float) $average, 1 ),
				),
				'assignments' => Submissions::student_summary( $user_id ),
				'skills'      => Snapshot::review_counts( $user_id ),
			)
		);
	}

	/**
	 * Titles of posts by ID, in one query.
	 *
	 * @param int[] $ids Post IDs.
	 * @return array<int,string>
	 */
	private function titles( array $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		if ( ! $ids ) {
			return array();
		}
		_prime_post_caches( $ids, false, false );
		$titles = array();
		foreach ( $ids as $id ) {
			$post          = get_post( $id );
			$titles[ $id ] = $post ? html_entity_decode( (string) $post->post_title, ENT_QUOTES, 'UTF-8' ) : '';
		}
		return $titles;
	}

	/**
	 * Paged quiz attempts of the student, newest first.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_quiz_attempts( $request ) {
		$user_id = $this->student_id( $request );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		list( $page, $per_page ) = $this->paging( $request );

		$total = AttemptReports::count_student_attempts( $user_id );
		if ( $total > 0 && $page > (int) ceil( $total / $per_page ) ) {
			return $this->invalid_page();
		}
		$rows  = AttemptReports::student_attempts( $user_id, $page, $per_page );
		$maxes = AttemptReports::max_marks( wp_list_pluck( $rows, 'id' ) );

		$titles = $this->titles( array_merge( wp_list_pluck( $rows, 'quiz_id' ), wp_list_pluck( $rows, 'course_id' ) ) );
		$items  = array();
		foreach ( $rows as $row ) {
			$score   = round( (float) $row['total'], 4 );
			$max     = round( $maxes[ (int) $row['id'] ] ?? 0.0, 4 );
			$items[] = array(
				'id'           => (int) $row['id'],
				'quiz_id'      => (int) $row['quiz_id'],
				'quiz_title'   => $titles[ (int) $row['quiz_id'] ] ?? '',
				'course_id'    => (int) $row['course_id'],
				'course_title' => $titles[ (int) $row['course_id'] ] ?? '',
				'status'       => (string) $row['status'],
				'score'        => $score,
				'max_marks'    => $max,
				'percentage'   => $max > 0 ? round( $score / $max * 100, 1 ) : null,
				'started_at'   => StudentController::format_local( $row['start_date'] ),
				'submitted_at' => 'in-progress' === $row['status'] ? null : StudentController::format_local( $row['end_date'] ),
			);
		}
		return $this->paged( $request, $items, $total, $page, $per_page, $this->rest_base . '/' . $user_id . '/quiz-attempts' );
	}

	/**
	 * Paged assignment submissions of the student, newest first. Text, files and feedback stay on
	 * the grading screen.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_assignment_submissions( $request ) {
		$user_id = $this->student_id( $request );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		list( $page, $per_page ) = $this->paging( $request );

		$total = Submissions::count_for_student( $user_id );
		if ( $total > 0 && $page > (int) ceil( $total / $per_page ) ) {
			return $this->invalid_page();
		}
		$rows = Submissions::page_for_student( $user_id, $page, $per_page );

		$assignment_ids = array_map( 'intval', wp_list_pluck( $rows, 'assignment_id' ) );
		$titles         = $this->titles( array_merge( $assignment_ids, wp_list_pluck( $rows, 'course_id' ) ) );
		$max_scores     = Submissions::max_scores( $assignment_ids );

		$items = array();
		foreach ( $rows as $row ) {
			$graded  = 'submitted' !== (string) $row['status'];
			$items[] = array(
				'id'               => (int) $row['id'],
				'assignment_id'    => (int) $row['assignment_id'],
				'assignment_title' => $titles[ (int) $row['assignment_id'] ] ?? '',
				'course_id'        => (int) $row['course_id'],
				'course_title'     => $titles[ (int) $row['course_id'] ] ?? '',
				'status'           => $graded ? 'graded' : 'submitted',
				'result'           => in_array( (string) $row['status'], array( 'passed', 'failed' ), true ) ? (string) $row['status'] : null,
				'score'            => $graded ? (int) $row['score'] : null,
				'max_score'        => $max_scores[ (int) $row['assignment_id'] ] ?? 0,
				'attempts'         => (int) $row['total_attempt'],
				'submitted_at'     => StudentController::format_local( $row['start_date'] ),
				'graded_at'        => $graded ? StudentController::format_local( $row['end_date'] ) : null,
			);
		}
		return $this->paged( $request, $items, $total, $page, $per_page, $this->rest_base . '/' . $user_id . '/assignment-submissions' );
	}

	/**
	 * Paged skill states of the student, by skill name.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_skills( $request ) {
		$user_id = $this->student_id( $request );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		list( $page, $per_page ) = $this->paging( $request );
		$route                   = $this->rest_base . '/' . $user_id . '/skills';
		$filter                  = (string) $request['filter'];

		$total = Snapshot::count_states( $user_id, $filter );
		if ( $total > 0 && $page > (int) ceil( $total / $per_page ) ) {
			return $this->invalid_page();
		}
		$rows = $total > 0 ? Snapshot::state_page( $user_id, $filter, $page, $per_page ) : array();

		$items = array();
		foreach ( $rows as $row ) {
			$items[] = array(
				'skill_id'         => (int) $row['term_id'],
				'name'             => html_entity_decode( (string) $row['name'], ENT_QUOTES, 'UTF-8' ),
				'level'            => (string) $row['level'],
				'level_label'      => (string) Levels::label( (string) $row['level'] ),
				'review_due'       => (bool) $row['review_due'],
				'struggling'       => (bool) $row['struggling'],
				'evidence_count'   => (int) $row['evidence_count'],
				'recent_accuracy'  => round( (float) $row['accuracy'], 4 ),
				'last_evidence_at' => StudentController::format_local( $row['last_evidence_at'] ),
			);
		}
		return $this->paged( $request, $items, $total, $page, $per_page, $route );
	}
}
