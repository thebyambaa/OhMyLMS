<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * StudentReportController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Performance\Levels;
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
		global $wpdb;

		$user_id = $this->student_id( $request );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Aggregates over plugin tables; no API exists and the report must be fresh.
		$courses     = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT course_id) AS enrolled, COUNT(DISTINCT CASE WHEN progress = 'completed' THEN course_id END) AS completed FROM %i WHERE user_id = %d AND status IN ('enrolled','banned')",
				$wpdb->prefix . 'ohmylms_user_enrollment',
				$user_id
			),
			ARRAY_A
		);
		$quizzes     = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS attempts, SUM(a.status = 'in-review') AS in_review, AVG(CASE WHEN m.max_marks > 0 THEN a.total / m.max_marks * 100 END) AS average FROM %i a LEFT JOIN ( SELECT quiz_attempt_id, SUM(question_marks) AS max_marks FROM %i WHERE student_id = %d GROUP BY quiz_attempt_id ) m ON m.quiz_attempt_id = a.id WHERE a.student_id = %d AND a.status <> 'in-progress'",
				$wpdb->prefix . 'ohmylms_quiz_attempts',
				$wpdb->prefix . 'ohmylms_quiz_attempts_answers',
				$user_id,
				$user_id
			),
			ARRAY_A
		);
		$assignments = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS submitted, SUM(status <> 'submitted') AS graded, SUM(status = 'passed') AS passed, SUM(status = 'failed') AS failed FROM %i WHERE user_id = %d",
				$wpdb->prefix . 'ohmylms_assignment_attempts',
				$user_id
			),
			ARRAY_A
		);
		$skills      = array(
			'total'      => 0,
			'review_due' => 0,
			'struggling' => 0,
		);
		if ( Schema::ready() ) {
			$row    = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT COUNT(*) AS total, SUM(review_due = 1) AS review_due, SUM(struggling = 1) AS struggling FROM %i WHERE student_id = %d',
					Schema::table( 'student_skill_state' ),
					$user_id
				),
				ARRAY_A
			);
			$skills = array(
				'total'      => (int) ( $row['total'] ?? 0 ),
				'review_due' => (int) ( $row['review_due'] ?? 0 ),
				'struggling' => (int) ( $row['struggling'] ?? 0 ),
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		$enrolled  = (int) ( $courses['enrolled'] ?? 0 );
		$completed = (int) ( $courses['completed'] ?? 0 );
		$average   = $quizzes['average'] ?? null;

		return rest_ensure_response(
			array(
				'student_id'  => $user_id,
				'courses'     => array(
					'enrolled'    => $enrolled,
					'completed'   => $completed,
					'in_progress' => max( 0, $enrolled - $completed ),
				),
				'quizzes'     => array(
					'attempts'        => (int) ( $quizzes['attempts'] ?? 0 ),
					'in_review'       => (int) ( $quizzes['in_review'] ?? 0 ),
					'average_percent' => null === $average ? null : round( (float) $average, 1 ),
				),
				'assignments' => array(
					'submitted' => (int) ( $assignments['submitted'] ?? 0 ),
					'graded'    => (int) ( $assignments['graded'] ?? 0 ),
					'passed'    => (int) ( $assignments['passed'] ?? 0 ),
					'failed'    => (int) ( $assignments['failed'] ?? 0 ),
				),
				'skills'      => $skills,
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
		global $wpdb;

		$user_id = $this->student_id( $request );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		list( $page, $per_page ) = $this->paging( $request );
		$table                   = $wpdb->prefix . 'ohmylms_quiz_attempts';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Plugin attempt tables have no API.
		$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE student_id = %d', $table, $user_id ) );
		if ( $total > 0 && $page > (int) ceil( $total / $per_page ) ) {
			return $this->invalid_page();
		}
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, quiz_id, course_id, status, total, start_date, end_date FROM %i WHERE student_id = %d ORDER BY start_date DESC, id DESC LIMIT %d OFFSET %d', $table, $user_id, $per_page, ( $page - 1 ) * $per_page ), ARRAY_A );
		$maxes = array();
		if ( $rows ) {
			$ids     = array_map( 'intval', wp_list_pluck( $rows, 'id' ) );
			$holders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholders are generated from the id count.
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT quiz_attempt_id, SUM(question_marks) AS max_marks FROM %i WHERE quiz_attempt_id IN ({$holders}) GROUP BY quiz_attempt_id", array_merge( array( $wpdb->prefix . 'ohmylms_quiz_attempts_answers' ), $ids ) ), ARRAY_A ) as $max ) {
				$maxes[ (int) $max['quiz_attempt_id'] ] = (float) $max['max_marks'];
			}
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

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
		global $wpdb;

		$user_id = $this->student_id( $request );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		list( $page, $per_page ) = $this->paging( $request );
		$table                   = $wpdb->prefix . 'ohmylms_assignment_attempts';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Plugin attempt tables have no API.
		$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d', $table, $user_id ) );
		if ( $total > 0 && $page > (int) ceil( $total / $per_page ) ) {
			return $this->invalid_page();
		}
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, assignment_id, course_id, status, score, total_attempt, start_date, end_date FROM %i WHERE user_id = %d ORDER BY start_date DESC, id DESC LIMIT %d OFFSET %d', $table, $user_id, $per_page, ( $page - 1 ) * $per_page ), ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		$assignment_ids = array_map( 'intval', wp_list_pluck( $rows, 'assignment_id' ) );
		$titles         = $this->titles( array_merge( $assignment_ids, wp_list_pluck( $rows, 'course_id' ) ) );
		update_meta_cache( 'post', $assignment_ids );

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
				'max_score'        => (int) get_post_meta( (int) $row['assignment_id'], '_total_points', true ),
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
		global $wpdb;

		$user_id = $this->student_id( $request );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		list( $page, $per_page ) = $this->paging( $request );
		$route                   = $this->rest_base . '/' . $user_id . '/skills';
		if ( ! Schema::ready() ) {
			return $this->paged( $request, array(), 0, $page, $per_page, $route );
		}

		$where = 's.student_id = %d';
		if ( 'review_due' === $request['filter'] ) {
			$where .= ' AND s.review_due = 1';
		} elseif ( 'struggling' === $request['filter'] ) {
			$where .= ' AND s.struggling = 1';
		}
		$from = ' FROM %i s INNER JOIN %i t ON t.term_id = s.term_id WHERE ' . $where;
		$args = array( Schema::table( 'student_skill_state' ), $wpdb->terms, $user_id );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Prepared with %i identifiers and an argument array; the WHERE clause is built from constants.
		$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*)' . $from, $args ) );
		if ( $total > 0 && $page > (int) ceil( $total / $per_page ) ) {
			return $this->invalid_page();
		}
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT s.term_id, t.name, s.level, s.review_due, s.struggling, s.evidence_count, s.accuracy, s.last_evidence_at' . $from . ' ORDER BY t.name ASC, s.term_id ASC LIMIT %d OFFSET %d', array_merge( $args, array( $per_page, ( $page - 1 ) * $per_page ) ) ), ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

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
