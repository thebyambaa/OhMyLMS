<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * DashboardProController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Analytics\DateRange;
use OhMyLMS\Analytics\Repository;
use OhMyLMS\Analytics\RestSupport;
use RuntimeException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

/**
 * REST API of the single-course report under `ohmylms/v1/analytics/courses/<id>`.
 *
 * `GET /analytics/courses/<id>` returns the course figures for a range and
 * `GET /analytics/courses/<id>/students` pages its students with progress (personal data, so the
 * shared capability applies). See docs/api/analytics.md. Registered through the `ohmylms_rest_v1_controllers` filter.
 *
 * @since 1.0.0
 */
class DashboardProController extends RestController {

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
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$id_arg = array(
			'id' => array(
				'description' => __( 'Course ID.', 'ohmylms' ),
				'type'        => 'integer',
				'minimum'     => 1,
			),
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args'   => $id_arg,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => DateRange::args(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/students',
			array(
				'args' => $id_arg,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);
	}

	/**
	 * Check the shared capability for one course.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return RestSupport::authorize();
	}

	/**
	 * Check the shared capability for its students.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return RestSupport::authorize();
	}

	/**
	 * Build the not-found error.
	 *
	 * @return WP_Error
	 */
	private function not_found() {
		return new WP_Error( 'ohmylms_analytics_course_not_found', __( 'Course not found.', 'ohmylms' ), array( 'status' => 404 ) );
	}

	/**
	 * Load the course post.
	 *
	 * @param int $id Course ID.
	 * @return \WP_Post|null
	 */
	private function find_course( $id ) {
		$post = get_post( absint( $id ) );
		if ( ! $post || Repository::COURSE_TYPE !== $post->post_type || ! in_array( $post->post_status, Repository::COURSE_STATUSES, true ) ) {
			return null;
		}
		return $post;
	}

	/**
	 * Course figures for a range.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$course = $this->find_course( $request['id'] );
		if ( ! $course ) {
			return $this->not_found();
		}
		$range = DateRange::from_request( $request );
		if ( is_wp_error( $range ) ) {
			return $range;
		}

		$id       = (int) $course->ID;
		$previous = $range->previous();
		try {
			$repository = new Repository();
			$money      = array(
				'current'  => $repository->course_money( $id, $range->start_sql(), $range->end_sql() ),
				'previous' => $repository->course_money( $id, $previous->start_sql(), $previous->end_sql() ),
			);
			$content    = $repository->content_counts( $id );
			$students   = $repository->course_enrollments( $id, $range->start_sql(), $range->end_sql() );
			$series     = $repository->course_series( $id, $range );
			$currency   = $repository->currency();
		} catch ( RuntimeException $exception ) {
			return RestSupport::query_failed();
		}

		$rating = array(
			'average' => 0.0,
			'count'   => 0,
		);
		$object = function_exists( 'ohmylms_get_course' ) ? ohmylms_get_course( $id ) : null;
		if ( $object ) {
			$rating = array(
				'average' => (float) $object->get_average_rating(),
				'count'   => (int) $object->get_review_count(),
			);
		}

		$thumbnail = get_the_post_thumbnail_url( $id, 'medium' );
		$data      = array(
			'course'   => array(
				'id'            => $id,
				'title'         => html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
				'status'        => $course->post_status,
				'permalink'     => 'publish' === $course->post_status ? (string) get_permalink( $id ) : '',
				'thumbnail_url' => $thumbnail ? (string) $thumbnail : null,
				'rating'        => $rating,
			),
			'content'  => $content,
			'range'    => $range->to_array(),
			'currency' => $currency,
			'summary'  => $money,
			'students' => $students,
			'series'   => $series,
		);

		/**
		 * Fires after the report of one course was built.
		 *
		 * @param array $data      Report data (see docs/api/analytics.md).
		 * @param int   $course_id Course ID.
		 */
		do_action( 'ohmylms_rest_analytics_course_data', $data, $id );

		return rest_ensure_response( $this->prepare_item_for_response( $data, $request ) );
	}

	/**
	 * Paged students of the course with progress.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$course = $this->find_course( $request['id'] );
		if ( ! $course ) {
			return $this->not_found();
		}

		$page     = max( 1, (int) $request['page'] );
		$per_page = min( 100, max( 1, (int) $request['per_page'] ) );

		try {
			$result = ( new Repository() )->course_students(
				(int) $course->ID,
				array(
					'progress' => (string) $request['progress'],
					'search'   => trim( (string) $request['search'] ),
				),
				(string) $request['orderby'],
				(string) $request['order'],
				$per_page,
				( $page - 1 ) * $per_page
			);
		} catch ( RuntimeException $exception ) {
			return RestSupport::query_failed();
		}

		if ( $result['total'] > 0 && $page > (int) ceil( $result['total'] / $per_page ) ) {
			return RestSupport::invalid_page();
		}

		$response = rest_ensure_response( $result['items'] );
		RestSupport::add_pagination_headers( $response, $request, $result['total'], $per_page, $page, $this->rest_base . '/' . (int) $course->ID . '/students' );
		return $response;
	}

	/**
	 * Pass the report through the additional-fields machinery.
	 *
	 * @param array           $item    Report data.
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public function prepare_item_for_response( $item, $request ) {
		return $this->add_additional_fields_to_object( $item, $request );
	}

	/**
	 * Collection parameters of the students route.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array_merge(
			RestSupport::paging_args(),
			array(
				'search'   => RestSupport::search_arg( __( 'Limit results to students whose name, username or email contains the string.', 'ohmylms' ) ),
				'progress' => array(
					'description' => __( 'Limit results by completion.', 'ohmylms' ),
					'type'        => 'string',
					'default'     => 'all',
					'enum'        => array( 'all', 'running', 'completed' ),
				),
				'orderby'  => array(
					'description' => __( 'Sort by attribute.', 'ohmylms' ),
					'type'        => 'string',
					'default'     => 'name',
					'enum'        => array_keys( Repository::STUDENT_ORDERBY ),
				),
				'order'    => RestSupport::order_arg( 'asc' ),
			)
		);
	}

	/**
	 * Get the course report schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$period  = array(
			'type'       => 'object',
			'properties' => array(
				'sales'   => array( 'type' => 'integer' ),
				'gross'   => array( 'type' => 'number' ),
				'refunds' => array( 'type' => 'number' ),
				'net'     => array( 'type' => 'number' ),
			),
		);
		$context = array( 'view' );

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'analytics-course',
			'type'       => 'object',
			'properties' => array(
				'course'   => array(
					'type'       => 'object',
					'context'    => $context,
					'readonly'   => true,
					'properties' => array(
						'id'            => array( 'type' => 'integer' ),
						'title'         => array( 'type' => 'string' ),
						'status'        => array( 'type' => 'string' ),
						'permalink'     => array( 'type' => 'string' ),
						'thumbnail_url' => array( 'type' => array( 'string', 'null' ) ),
						'rating'        => array(
							'type'       => 'object',
							'properties' => array(
								'average' => array( 'type' => 'number' ),
								'count'   => array( 'type' => 'integer' ),
							),
						),
					),
				),
				'content'  => array(
					'type'       => 'object',
					'context'    => $context,
					'readonly'   => true,
					'properties' => array(
						'chapters'    => array( 'type' => 'integer' ),
						'lessons'     => array( 'type' => 'integer' ),
						'quizzes'     => array( 'type' => 'integer' ),
						'assignments' => array( 'type' => 'integer' ),
					),
				),
				'range'    => RestSupport::range_schema(),
				'currency' => RestSupport::currency_schema(),
				'summary'  => array(
					'type'       => 'object',
					'context'    => $context,
					'readonly'   => true,
					'properties' => array(
						'current'  => $period,
						'previous' => $period,
					),
				),
				'students' => array(
					'type'       => 'object',
					'context'    => $context,
					'readonly'   => true,
					'properties' => array(
						'enrolled'    => array( 'type' => 'integer' ),
						'in_range'    => array( 'type' => 'integer' ),
						'completed'   => array( 'type' => 'integer' ),
						'in_progress' => array( 'type' => 'integer' ),
					),
				),
				'series'   => RestSupport::series_schema(),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}
}
