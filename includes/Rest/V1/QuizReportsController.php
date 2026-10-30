<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Quiz reports overview: attempt figures for every quiz the user may grade.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
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
	const LISTED_STATUSES = array( 'publish', 'draft', 'pending', 'future', 'private' );

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
	 * Per-quiz attempt figures as a derived table.
	 *
	 * @return string SQL with one `%i` placeholder for the attempts table.
	 */
	private function stats_sql() {
		return "SELECT quiz_id, COUNT(*) AS attempts, COUNT(DISTINCT student_id) AS learners, SUM(status = 'in-progress') AS in_progress, SUM(status = 'in-review') AS to_grade, SUM(status = 'completed') AS completed, AVG(CASE WHEN status = 'completed' THEN total END) AS average_score, MAX(CASE WHEN status <> 'in-progress' AND end_date > '1000-01-01' THEN end_date END) AS last_submitted FROM %i GROUP BY quiz_id";
	}

	/**
	 * FROM and WHERE of the filtered quiz set, with its arguments.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{0:string,1:array}|null Null when the user may grade no quiz.
	 */
	private function filtered( $request ) {
		global $wpdb;

		$statuses = 'all' === $request['status'] ? self::LISTED_STATUSES : array( (string) $request['status'] );
		$where    = array( 'p.post_type = %s', 'p.post_status IN (' . implode( ',', array_fill( 0, count( $statuses ), '%s' ) ) . ')' );
		$args     = array_merge( array( $wpdb->prefix . 'ohmylms_quiz_attempts', $wpdb->posts, OHMYLMS_QUIZ_CPT ), $statuses );

		if ( ! current_user_can( 'manage_options' ) ) {
			$ids = GradingPolicy::quiz_ids_for_author( get_current_user_id() );
			if ( ! $ids ) {
				return null;
			}
			$where[] = 'p.ID IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')';
			$args    = array_merge( $args, $ids );
		}
		if ( '' !== (string) $request['search'] ) {
			$where[] = 'p.post_title LIKE %s';
			$args[]  = '%' . $wpdb->esc_like( (string) $request['search'] ) . '%';
		}
		if ( ! empty( $request['course'] ) ) {
			$where[] = 'p.ID IN (SELECT content.content_id FROM %i content INNER JOIN %i chapter ON chapter.chapter_id = content.chapter_id WHERE chapter.course_id = %d)';
			$args    = array_merge( $args, array( $wpdb->prefix . 'ohmylms_content_relationship', $wpdb->prefix . 'ohmylms_chapter_relationship', (int) $request['course'] ) );
		}
		if ( 'attempted' === $request['activity'] ) {
			$where[] = 's.attempts > 0';
		} elseif ( 'to_grade' === $request['activity'] ) {
			$where[] = 's.to_grade > 0';
		}

		return array( '(' . $this->stats_sql() . ') s RIGHT JOIN %i p ON s.quiz_id = p.ID WHERE ' . implode( ' AND ', $where ), $args );
	}

	/**
	 * List quizzes with their attempt figures.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		global $wpdb;

		$page     = max( 1, (int) $request['page'] );
		$per_page = min( 100, max( 1, (int) $request['per_page'] ) );
		$filtered = $this->filtered( $request );
		$rows     = array();
		$total    = 0;
		if ( null !== $filtered ) {
			list( $from, $args ) = $filtered;
			$order               = 'asc' === $request['order'] ? 'ASC' : 'DESC';
			$columns             = array(
				'title'          => "p.post_title {$order}",
				'created_at'     => "p.post_date_gmt {$order}",
				'attempts'       => "COALESCE(s.attempts, 0) {$order}",
				'to_grade'       => "COALESCE(s.to_grade, 0) {$order}",
				'last_submitted' => "s.last_submitted IS NULL ASC, s.last_submitted {$order}",
			);
			$sort                = $columns[ $request['orderby'] ];
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$from}", $args ) );
			$pages = (int) ceil( $total / $per_page );
			if ( $page > $pages && $total > 0 ) {
				return new WP_Error( 'ohmylms_quiz_report_invalid_page', __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), array( 'status' => 400 ) );
			}
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT p.ID, p.post_title, p.post_status, s.attempts, s.learners, s.in_progress, s.to_grade, s.completed, s.average_score, s.last_submitted FROM {$from} ORDER BY {$sort}, p.ID {$order} LIMIT %d OFFSET %d", array_merge( $args, array( $per_page, ( $page - 1 ) * $per_page ) ) ), ARRAY_A );
			// phpcs:enable
			$rows = is_array( $rows ) ? $rows : array();
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
		global $wpdb;

		$summary  = array(
			'quizzes'   => 0,
			'attempts'  => 0,
			'learners'  => 0,
			'to_grade'  => 0,
			'completed' => 0,
		);
		$filtered = $this->filtered( $request );
		if ( null !== $filtered ) {
			list( $from, $args ) = $filtered;
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery
			$row      = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS quizzes, COALESCE(SUM(s.attempts), 0) AS attempts, COALESCE(SUM(s.to_grade), 0) AS to_grade, COALESCE(SUM(s.completed), 0) AS completed FROM {$from}", $args ) );
			$learners = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT a.student_id) FROM %i a WHERE a.quiz_id IN (SELECT p.ID FROM {$from})", array_merge( array( $wpdb->prefix . 'ohmylms_quiz_attempts' ), $args ) ) );
			// phpcs:enable
			if ( $row ) {
				$summary = array(
					'quizzes'   => (int) $row->quizzes,
					'attempts'  => (int) $row->attempts,
					'learners'  => $learners,
					'to_grade'  => (int) $row->to_grade,
					'completed' => (int) $row->completed,
				);
			}
		}
		$response = rest_ensure_response( $summary );
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
		global $wpdb;

		if ( ! $ids ) {
			return array();
		}
		$holders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT content.content_id, MIN(chapter.course_id) AS course_id FROM %i content INNER JOIN %i chapter ON chapter.chapter_id = content.chapter_id WHERE content.content_id IN ({$holders}) GROUP BY content.content_id", array_merge( array( $wpdb->prefix . 'ohmylms_content_relationship', $wpdb->prefix . 'ohmylms_chapter_relationship' ), $ids ) ) );
		$map  = array();
		foreach ( (array) $rows as $row ) {
			$map[ (int) $row->content_id ] = (int) $row->course_id;
		}
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
		global $wpdb;

		if ( ! $ids ) {
			return array();
		}
		update_meta_cache( 'post', $ids );
		$marks = array();
		foreach ( $ids as $id ) {
			$raw     = get_post_meta( $id, QuizSettings::META, true );
			$passing = is_array( $raw ) && isset( $raw['passing_grade'] ) && is_array( $raw['passing_grade'] ) ? $raw['passing_grade'] : array();
			if ( ! empty( $passing['enabled'] ) && isset( $passing['value'] ) && is_numeric( $passing['value'] ) && (float) $passing['value'] > 0 ) {
				$marks[ $id ] = round( (float) $passing['value'], 2 );
			}
		}
		$out = array();
		foreach ( $ids as $id ) {
			$out[ $id ] = array( 0, $marks[ $id ] ?? 0.0 );
		}
		if ( ! $marks ) {
			return $out;
		}
		$cases = array();
		$args  = array();
		foreach ( $marks as $id => $mark ) {
			$cases[] = 'WHEN %d THEN %f';
			$args[]  = $id;
			$args[]  = $mark;
		}
		$holders = implode( ',', array_fill( 0, count( $marks ), '%d' ) );
		$case    = 'CASE quiz_id ' . implode( ' ', $cases ) . ' END';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT quiz_id, SUM(total >= {$case}) AS passed FROM %i WHERE status = 'completed' AND quiz_id IN ({$holders}) GROUP BY quiz_id", array_merge( $args, array( $wpdb->prefix . 'ohmylms_quiz_attempts' ), array_keys( $marks ) ) ) );
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row->quiz_id ][0] = (int) $row->passed;
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
