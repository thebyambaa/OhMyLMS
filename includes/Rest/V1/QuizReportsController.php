<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Quiz reports overview: attempt figures for every quiz the user may grade.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Learning\CourseStructure;
use OhMyLMS\Quiz\AttemptReports;
use OhMyLMS\Quiz\GradingPolicy;
use OhMyLMS\Quiz\QuizSettings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * `GET ohmylms/v1/reports/quizzes` and `GET ohmylms/v1/reports/quizzes/summary` (see docs/api/quiz-reports.md).
 *
 * Read-only. Rows are limited to quizzes the user may grade ({@see GradingPolicy}): every quiz for
 * administrators, otherwise quizzes the user wrote and quizzes in courses the user wrote. Responses carry
 * only aggregate attempt figures, never student names, emails or answers.
 */
class QuizReportsController extends RestController {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'reports/quizzes';

	/**
	 * Statuses shown in the "all" view.
	 */
	const LISTED_STATUSES = AttemptReports::LISTED_STATUSES;

	/**
	 * Register the routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/summary',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_summary' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->filter_params(),
				),
			)
		);
	}

	/**
	 * Quiz authors only: 401 for guests, 403 for users who cannot write quizzes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		$type = get_post_type_object( OHMYLMS_QUIZ_CPT );
		if ( $type && current_user_can( $type->cap->edit_posts ) ) {
			return true;
		}

		return new WP_Error( 'ohmylms_quiz_report_cannot_view', __( 'Sorry, you are not allowed to view quiz reports.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Filters shared by the list and the summary.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function filter_params() {
		return array(
			'search'   => array(
				'description'       => __( 'Limit results to quizzes whose title matches.', 'ohmylms' ),
				'type'              => 'string',
				'maxLength'         => 200,
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'course'   => array(
				'description' => __( 'Limit results to quizzes placed in a course.', 'ohmylms' ),
				'type'        => 'integer',
				'minimum'     => 1,
			),
			'status'   => array(
				'description' => __( 'Limit results to one publication status.', 'ohmylms' ),
				'type'        => 'string',
				'default'     => 'all',
				'enum'        => array( 'all', 'publish', 'draft' ),
			),
			'activity' => array(
				'description' => __( 'Limit results to quizzes with attempts, or with attempts waiting for grading.', 'ohmylms' ),
				'type'        => 'string',
				'default'     => 'all',
				'enum'        => array( 'all', 'attempted', 'to_grade' ),
			),
		);
	}

	/**
	 * Arguments of the list.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_collection_params() {
		return array_merge(
			array(
				'page'     => array(
					'description' => __( 'Current page of the collection.', 'ohmylms' ),
					'type'        => 'integer',
					'default'     => 1,
					'minimum'     => 1,
				),
				'per_page' => array(
					'description' => __( 'Maximum number of items per page.', 'ohmylms' ),
					'type'        => 'integer',
					'default'     => 20,
					'minimum'     => 1,
					'maximum'     => 100,
				),
				'orderby'  => array(
					'description' => __( 'Sort the collection by a field.', 'ohmylms' ),
					'type'        => 'string',
					'default'     => 'last_submitted',
					'enum'        => array( 'title', 'created_at', 'attempts', 'to_grade', 'last_submitted' ),
				),
				'order'    => array(
					'description' => __( 'Sort direction.', 'ohmylms' ),
					'type'        => 'string',
					'default'     => 'desc',
					'enum'        => array( 'asc', 'desc' ),
				),
			),
			$this->filter_params()
		);
	}

	/**
	 * Filters of the request in the shape AttemptReports expects.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string,mixed>
	 */
	private function filters( $request ) {
		return array(
			'search'   => (string) $request['search'],
			'course'   => (int) $request['course'],
			'status'   => (string) $request['status'],
			'activity' => (string) $request['activity'],
		);
	}

	/**
	 * List quizzes with their attempt figures.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$page     = max( 1, (int) $request['page'] );
		$per_page = min( 100, max( 1, (int) $request['per_page'] ) );
		$filters  = $this->filters( $request );
		$scope    = GradingPolicy::gradable_quiz_ids();
		$rows     = array();
		$total    = AttemptReports::count_quizzes( $filters, $scope );
		if ( $total > 0 ) {
			if ( $page > (int) ceil( $total / $per_page ) ) {
				return new WP_Error( 'ohmylms_quiz_report_invalid_page', __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), array( 'status' => 400 ) );
			}
			$rows = AttemptReports::quiz_page( $filters, $scope, (string) $request['orderby'], (string) $request['order'], $page, $per_page );
		}

		$ids     = array_map( 'intval', wp_list_pluck( $rows, 'ID' ) );
		$courses = $this->courses( $ids );
		$passed  = $this->passed( $ids );
		$data    = array();
		foreach ( $rows as $row ) {
			$id     = (int) $row['ID'];
			$data[] = $this->prepare_row( $row, $courses[ $id ] ?? null, $passed[ $id ] ?? array( 0, 0.0 ) );
		}

		$pages    = (int) ceil( $total / $per_page );
		$response = rest_ensure_response( $data );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $pages );
		$response->header( 'Cache-Control', 'private, no-store' );
		$base = add_query_arg( urlencode_deep( $request->get_query_params() ), rest_url( $this->namespace . '/' . $this->rest_base ) );
		if ( $page > 1 ) {
			$response->link_header( 'prev', add_query_arg( 'page', $page - 1, $base ) );
		}
		if ( $page < $pages ) {
			$response->link_header( 'next', add_query_arg( 'page', $page + 1, $base ) );
		}

		return $response;
	}

	/**
	 * Totals over the whole filtered set.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_summary( $request ) {
		$response = rest_ensure_response( AttemptReports::quiz_summary( $this->filters( $request ), GradingPolicy::gradable_quiz_ids() ) );
		$response->header( 'Cache-Control', 'private, no-store' );

		return $response;
	}

	/**
	 * Course of each quiz (the first course whose chapter contains it).
	 *
	 * @param int[] $ids Quiz IDs.
	 * @return array<int,array{id:int,title:string}>
	 */
	private function courses( array $ids ) {
		$map = CourseStructure::first_courses( $ids );
		if ( $map ) {
			_prime_post_caches( array_values( array_unique( $map ) ), false, false );
		}
		$out = array();
		foreach ( $map as $quiz_id => $course_id ) {
			$course = get_post( $course_id );
			if ( $course && OHMYLMS_COURSE_CPT === $course->post_type ) {
				$out[ $quiz_id ] = array(
					'id'    => (int) $course->ID,
					'title' => html_entity_decode( get_the_title( $course ), ENT_QUOTES, 'UTF-8' ),
				);
			}
		}

		return $out;
	}

	/**
	 * Pass marks and passed completed attempts, compared in marks as the learner flow does.
	 *
	 * @param int[] $ids Quiz IDs.
	 * @return array<int,array{0:int,1:float}> Passed count and pass mark by quiz; quizzes without a pass mark have 0.0.
	 */
	private function passed( array $ids ) {
		$marks  = QuizSettings::pass_marks( $ids );
		$counts = AttemptReports::passed_counts( $marks );
		$out    = array();
		foreach ( $ids as $id ) {
			$out[ $id ] = array( $counts[ $id ] ?? 0, $marks[ $id ] ?? 0.0 );
		}

		return $out;
	}

	/**
	 * Shape one report row.
	 *
	 * @param array<string,mixed>             $row    Joined row.
	 * @param array{id:int,title:string}|null $course Course reference.
	 * @param array{0:int,1:float}            $passed Passed count and pass mark.
	 * @return array<string,mixed>
	 */
	private function prepare_row( array $row, $course, array $passed ) {
		$id        = (int) $row['ID'];
		$completed = (int) $row['completed'];
		$has_mark  = $passed[1] > 0;
		$average   = null === $row['average_score'] ? null : round( (float) $row['average_score'], 2 );
		$last      = null === $row['last_submitted'] ? '' : (string) $row['last_submitted'];

		return array(
			'id'                => $id,
			'title'             => html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
			'status'            => in_array( $row['post_status'], array( 'publish', 'future' ), true ) ? 'publish' : 'draft',
			'course'            => $course,
			'attempts'          => (int) $row['attempts'],
			'learners'          => (int) $row['learners'],
			'in_progress'       => (int) $row['in_progress'],
			'to_grade'          => (int) $row['to_grade'],
			'completed'         => $completed,
			'average_score'     => $average,
			'passing_score'     => $passed[1],
			'passed'            => $has_mark ? $passed[0] : null,
			'pass_rate'         => $has_mark && $completed > 0 ? round( $passed[0] / $completed * 100, 1 ) : null,
			'last_submitted_at' => '' === $last ? null : $this->local_to_iso( $last ),
			'can_edit'          => current_user_can( 'edit_post', $id ),
			'can_grade'         => GradingPolicy::can_grade( $id ),
		);
	}

	/**
	 * Convert a site-local MySQL date to ISO 8601 UTC, or null when unset.
	 *
	 * @param string $local Date in `Y-m-d H:i:s` (site time zone), as attempts store it.
	 * @return string|null
	 */
	private function local_to_iso( $local ) {
		if ( '' === $local || str_starts_with( $local, '0000' ) ) {
			return null;
		}
		$time = strtotime( get_gmt_from_date( $local ) . ' UTC' );

		return $time ? gmdate( 'Y-m-d\TH:i:s\Z', $time ) : null;
	}

	/**
	 * Row schema.
	 *
	 * @return array<string,mixed>
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->schema;
		}
		$count        = array(
			'type'     => 'integer',
			'readonly' => true,
		);
		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'ohmylms-quiz-report',
			'type'       => 'object',
			'properties' => array(
				'id'                => $count,
				'title'             => array( 'type' => 'string' ),
				'status'            => array(
					'type' => 'string',
					'enum' => array( 'publish', 'draft' ),
				),
				'course'            => array( 'type' => array( 'object', 'null' ) ),
				'attempts'          => $count,
				'learners'          => $count,
				'in_progress'       => $count,
				'to_grade'          => $count,
				'completed'         => $count,
				'average_score'     => array( 'type' => array( 'number', 'null' ) ),
				'passing_score'     => array( 'type' => 'number' ),
				'passed'            => array( 'type' => array( 'integer', 'null' ) ),
				'pass_rate'         => array( 'type' => array( 'number', 'null' ) ),
				'last_submitted_at' => array(
					'type'   => array( 'string', 'null' ),
					'format' => 'date-time',
				),
				'can_edit'          => array( 'type' => 'boolean' ),
				'can_grade'         => array( 'type' => 'boolean' ),
			),
		);

		return $this->schema;
	}
}
