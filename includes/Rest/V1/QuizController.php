<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Controller for handling quiz REST API endpoints.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Data\Question;
use OhMyLMS\Data\Quiz;
use OhMyLMS\Data\Student;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use OhMyLMS\DataException;
use OhMyLMS\QuestionBank\AccessPolicy;
use OhMyLMS\QuestionBank\DraftWriter;
use OhMyLMS\QuestionBank\Usage;
use OhMyLMS\Quiz\Review;
use OhMyLMS\Utility\Transaction;
/**
 * Controller for handling quiz REST API endpoints.
 *
 * This class extends the RESTController abstract class and defines REST API routes
 * for course-related CRUD operations and many more.
 *
 * @since 1.0.0
 */
class QuizController extends RestController {

	/**
	 * The base route for quiz base endpoints.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $base = 'quiz';

	/**
	 * Check quiz permission for Quiz controller.
	 */
	public function check_quiz_permission() {
		return AccessPolicy::check( AccessPolicy::can_author() );
	}

	/**
	 * Object-level permission for single-quiz routes: GET/PUT edit, DELETE delete.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return true|WP_Error
	 */
	public function check_item_permission( $request ) {
		$id = (int) $request['id'];
		if ( get_post_type( $id ) !== OHMYLMS_QUIZ_CPT ) {
			return new WP_Error( 'ohmylms_rest_invalid_quiz_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		return AccessPolicy::check( 'DELETE' === $request->get_method() ? AccessPolicy::can_delete_quiz( $id ) : AccessPolicy::can_edit_quiz( $id ) );
	}

	/**
	 * Reports and grading are limited to people who may edit that quiz.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return true|WP_Error
	 */
	public function check_grading_permission( $request ) {
		$id = (int) $request['id'];
		if ( get_post_type( $id ) !== OHMYLMS_QUIZ_CPT ) {
			return new WP_Error( 'ohmylms_rest_invalid_quiz_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		if ( ! empty( $request['attempt_id'] ) && ! $this->attempt_belongs_to_quiz( (int) $request['attempt_id'], $id ) ) {
			return new WP_Error( 'ohmylms_rest_invalid_attempt_id', __( 'Attempt not found for this quiz.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		return AccessPolicy::check( AccessPolicy::can_grade_quiz( $id ), __( 'You cannot view or grade this quiz.', 'ohmylms' ) );
	}

	/**
	 * Removing a question from a quiz edits the quiz; it never deletes the question.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return true|WP_Error
	 */
	public function check_item_permission_for_edit( $request ) {
		$id = (int) $request['id'];
		if ( get_post_type( $id ) !== OHMYLMS_QUIZ_CPT ) {
			return new WP_Error( 'ohmylms_rest_invalid_quiz_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		return AccessPolicy::check( AccessPolicy::can_edit_quiz( $id ) );
	}

	/**
	 * Remove one question from this quiz. The question stays in the bank.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function remove_question( $request ) {
		$result = Usage::remove_from_quiz( (int) $request['id'], (int) $request['question_id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result + array( 'status' => 'success' ) );
	}

	/**
	 * Attempt belongs to quiz for Quiz controller.
	 *
	 * @param mixed $attempt_id Attempt identifier.
	 * @param mixed $quiz_id Quiz identifier.
	 */
	private function attempt_belongs_to_quiz( $attempt_id, $quiz_id ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}ohmylms_quiz_attempts WHERE id=%d AND quiz_id=%d", $attempt_id, $quiz_id ) );
	}

	/**
	 * Registers REST API routes for quiz operations.
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
					'permission_callback' => array( $this, 'check_quiz_permission' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_quiz_permission' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/import/',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'import_file' ),
					'permission_callback' => array( $this, 'check_quiz_permission' ),
					'args'                => array(
						'name'       => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						// The file text. `csv` is the older name for the same thing.
						'content'    => array( 'type' => 'string' ),
						'csv'        => array( 'type' => 'string' ),
						'format'     => array(
							'type'    => 'string',
							'enum'    => array( 'auto', 'ohmylms', 'csv' ),
							'default' => 'auto',
						),
						// Check the file and report what it holds, without creating anything.
						'dry_run'    => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'copy_media' => array(
							'type'    => 'boolean',
							'default' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)/export',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'export_file' ),
					'permission_callback' => array( $this, 'check_item_permission_for_edit' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)/skills',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'set_skills' ),
					'permission_callback' => array( $this, 'check_item_permission_for_edit' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/trash-bulk/',
			array(
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'trash_bulk' ),
					'permission_callback' => array( $this, 'check_quiz_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/contents/',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items_with_content' ),
					'permission_callback' => array( $this, 'check_quiz_permission' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item_with_content' ),
					'permission_callback' => array( $this, 'check_quiz_permission' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the quiz.', 'ohmylms' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'check_item_permission' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_item_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'check_item_permission' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)/content',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the Quiz.', 'ohmylms' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_questions' ),
					'permission_callback' => array( $this, 'check_item_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_questions' ),
					'permission_callback' => array( $this, 'check_item_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)/report',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the Quiz.', 'ohmylms' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_report' ),
					'permission_callback' => array( $this, 'check_grading_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)/report/(?P<attempt_id>[\d]+)',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the Quiz.', 'ohmylms' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_attempt_report' ),
					'permission_callback' => array( $this, 'check_grading_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'review_attempt_report' ),
					'permission_callback' => array( $this, 'check_grading_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)/report/(?P<attempt_id>[\d]+)/(?P<quiz_attempt_answer_id>[\d]+)',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the Quiz.', 'ohmylms' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_attempt_report_manually' ),
					'permission_callback' => array( $this, 'check_grading_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)/questions/(?P<question_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'remove_question' ),
					'permission_callback' => array( $this, 'check_item_permission_for_edit' ),
				),
			)
		);
	}

	/**
	 * Replace the skills the quiz as a whole is connected to.
	 *
	 * @param WP_REST_Request $request Request with `skill_ids`.
	 * @return WP_Error|WP_REST_Response
	 */
	public function set_skills( $request ) {
		$saved = \OhMyLMS\Quiz\QuizSkills::set( (int) $request['id'], (array) $request['skill_ids'] );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		return rest_ensure_response( array( 'skill_ids' => $saved ) );
	}

	/**
	 * Create a draft quiz and its questions from a quiz file, or check the file (`dry_run`).
	 *
	 * The file is an OhMyLMS quiz file (JSON) or the older CSV layout. Nothing is created if
	 * any question is invalid; the error then lists every problem with its question number.
	 *
	 * @param WP_REST_Request $request Request with `content` (or `csv`), and optionally `name`, `format`, `dry_run`, `copy_media`.
	 * @return WP_Error|WP_REST_Response
	 */
	public function import_file( $request ) {
		$content = (string) ( $request['content'] ?? '' );
		if ( '' === $content ) {
			$content = (string) ( $request['csv'] ?? '' );
		}
		if ( '' === trim( $content ) ) {
			return new WP_Error( 'ohmylms_quiz_import_empty', __( 'The file is empty.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$result = \OhMyLMS\Quiz\Exchange\Importer::run(
			$content,
			array(
				'name'       => (string) $request['name'],
				'format'     => empty( $request['format'] ) ? 'auto' : (string) $request['format'],
				'dry_run'    => rest_sanitize_boolean( $request['dry_run'] ),
				'copy_media' => null === $request['copy_media'] ? true : rest_sanitize_boolean( $request['copy_media'] ),
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$response = rest_ensure_response( $result + array( 'status' => $result['dry_run'] ? 'ok' : 'success' ) );
		$response->set_status( $result['dry_run'] ? 200 : 201 );
		return $response;
	}

	/**
	 * Older name of {@see self::import_file()}, kept for callers of the CSV-only route.
	 *
	 * @param WP_REST_Request $request Request with `name` and `csv`.
	 * @return WP_Error|WP_REST_Response
	 */
	public function import_csv( $request ) {
		return $this->import_file( $request );
	}

	/**
	 * Download a quiz and all of its questions as an OhMyLMS quiz file.
	 *
	 * @param WP_REST_Request $request Request with the quiz `id`.
	 * @return WP_Error|WP_REST_Response `filename`, `mime`, `content`, `summary`, `warnings`.
	 */
	public function export_file( $request ) {
		$result = \OhMyLMS\Quiz\Exchange\Exporter::export( (int) $request['id'] );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * Get collection of quizs.
	 *
	 * This method handles the retrieval of quizs based on the provided request parameters.
	 * It supports various filters and pagination options to customize the query.
	 *
	 * @param \WP_REST_Request $request The REST request object containing query parameters.
	 * @return WP_Error|\WP_HTTP_Response|\WP_REST_Response The response object containing the quizs data or an error.
	 *
	 * @since 1.0.0
	 */
	public function get_items( $request ) {
		$args = array(
			'offset'              => isset( $request['offset'] ) ? intval( $request['offset'] ) : 0,
			'order'               => isset( $request['order'] ) ? sanitize_text_field( $request['order'] ) : 'DESC',
			'orderby'             => isset( $request['orderby'] ) ? sanitize_text_field( $request['orderby'] ) : 'date',
			'paged'               => isset( $request['page'] ) ? intval( $request['page'] ) : 1,
			'post__in'            => isset( $request['include'] ) ? array_map( 'intval', (array) $request['include'] ) : array(),
			'post__not_in'        => isset( $request['exclude'] ) ? array_map( 'intval', (array) $request['exclude'] ) : array(),
			'posts_per_page'      => isset( $request['per_page'] ) ? intval( $request['per_page'] ) : 10,
			'name'                => isset( $request['slug'] ) ? sanitize_text_field( $request['slug'] ) : '',
			'post_parent__in'     => isset( $request['parent'] ) ? array_map( 'intval', (array) $request['parent'] ) : array(),
			'post_parent__not_in' => isset( $request['parent_exclude'] ) ? array_map( 'intval', (array) $request['parent_exclude'] ) : array(),
			's'                   => isset( $request['search'] ) ? sanitize_text_field( $request['search'] ) : '',
			'post_type'           => OHMYLMS_QUIZ_CPT,
			'post_status'         => isset( $request['post_status'] ) ? sanitize_text_field( $request['post_status'] ) : array( 'draft', 'publish', 'future' ),
		);

		$args['date_query'] = array();

		if ( 'any' === $args['post_status'] || ! in_array( $args['post_status'], array( 'draft', 'publish', 'future' ) ) ) {
			$args['post_status'] = array( 'draft', 'publish', 'future' );
		}

		if ( isset( $request['before'] ) ) {
			$args['date_query'][0]['before'] = sanitize_text_field( $request['before'] );
		}

		if ( isset( $request['after'] ) ) {
			$args['date_query'][0]['after'] = sanitize_text_field( $request['after'] );
		}

		if ( isset( $request['filter'] ) && is_array( $request['filter'] ) ) {
			$args = array_merge( $args, $request['filter'] );
			unset( $args['filter'] );
		}

		$args       = apply_filters( 'ohmylms_rest_ohmylms_quiz_query', $args, $request );
		$query_args = $this->prepare_items_query( $args, $request );

		$posts_query  = new WP_Query();
		$query_result = $posts_query->query( $query_args );
		$posts        = array();

		foreach ( $query_result as $post ) {
			if ( ! AccessPolicy::can_edit_quiz( $post->ID ) ) {
				continue;
			}
			$data    = $this->prepare_item_for_response( $post, $request );
			$posts[] = $this->prepare_response_for_collection( $data );
		}
		$page                 = (int) $query_args['paged'];
		$total_posts          = $posts_query->found_posts;
		$total_filtered_posts = $total_posts;

		if ( 1 > $total_posts && 1 < $page ) {
			unset( $query_args['paged'] );
			$count_query = new WP_Query();
			$count_query->query( $query_args );
			$total_posts = $count_query->found_posts;
		}

		$max_pages = ceil( $total_posts / (int) $query_args['posts_per_page'] );

		if ( isset( $request['orderby'] ) && in_array( $request['orderby'], array( 'number_of_submissions' ) ) ) {
			usort(
				$posts,
				function ( $a, $b ) use ( $request ) {
					if ( strtoupper( $request['order'] ) === 'DESC' ) {
						return $a['number_of_submissions'] <=> $b['number_of_submissions'];
					} else {
						return $b['number_of_submissions'] <=> $a['number_of_submissions'];
					}
				}
			);
		}

		if ( isset( $request['orderby'] ) && 'course_name' === $request['orderby'] ) {
			usort(
				$posts,
				function ( $a, $b ) use ( $request ) {
					if ( isset( $b['courses']['course_name'] ) ) {
						$order = strtoupper( $request['order'] ) === 'DESC' ? -1 : 1;
						return strcasecmp( $a['courses']['course_name'], $b['courses']['course_name'] ) * $order;
					}
				}
			);
		}

		if ( ! empty( $request['course_id'] ) ) {
			$course_id = (int) $request['course_id'];

			$posts = array_filter(
				$posts,
				function ( $post ) use ( $course_id ) {
					return isset( $post['courses']['id'] ) && $post['courses']['id'] == $course_id;
				}
			);

			// Re-index array (optional).
			$posts                = array_values( $posts );
			$total_filtered_posts = count( $posts );
		}

		$response = rest_ensure_response( $posts );
		$response->header( 'X-WP-Total', (int) $total_posts );
		$response->header( 'X-WP-TotalPages', (int) $max_pages );
		$response->header( 'X-WP-NoOfFilteredQuizzes', (int) $total_filtered_posts );

		$request_params = $request->get_query_params();
		if ( ! empty( $request_params['filter'] ) ) {
			unset( $request_params['filter']['posts_per_page'] );
			unset( $request_params['filter']['paged'] );
		}
		$base = add_query_arg( $request_params, rest_url( sprintf( '/%s/%s', $this->namespace, $this->rest_base ) ) );

		if ( 1 < $page ) {
			$prev_page = $page - 1;
			if ( $prev_page > $max_pages ) {
				$prev_page = $max_pages;
			}
			$prev_link = add_query_arg( 'page', $prev_page, $base );
			$response->link_header( 'prev', $prev_link );
		}
		if ( $max_pages > $page ) {
			$next_page = $page + 1;
			$next_link = add_query_arg( 'page', $next_page, $base );
			$response->link_header( 'next', $next_link );
		}
		return $response;
	}



	/**
	 * Get collection of quizs with content.
	 *
	 * This method handles the retrieval of quizs based on the provided request parameters.
	 * It supports various filters and pagination options to customize the query.
	 *
	 * @param \WP_REST_Request $request The REST request object containing query parameters.
	 * @return WP_Error|\WP_HTTP_Response|\WP_REST_Response The response object containing the quizs data or an error.
	 *
	 * @since 1.0.0
	 */
	public function get_items_with_content( $request ) {
		$args = array(
			'offset'              => isset( $request['offset'] ) ? intval( $request['offset'] ) : 0,
			'order'               => isset( $request['order'] ) ? sanitize_text_field( $request['order'] ) : 'DESC',
			'orderby'             => isset( $request['orderby'] ) ? sanitize_text_field( $request['orderby'] ) : 'date',
			'paged'               => isset( $request['page'] ) ? intval( $request['page'] ) : 1,
			'post__in'            => isset( $request['include'] ) ? array_map( 'intval', (array) $request['include'] ) : array(),
			'post__not_in'        => isset( $request['exclude'] ) ? array_map( 'intval', (array) $request['exclude'] ) : array(),
			'posts_per_page'      => isset( $request['per_page'] ) ? intval( $request['per_page'] ) : 10,
			'name'                => isset( $request['slug'] ) ? sanitize_text_field( $request['slug'] ) : '',
			'post_parent__in'     => isset( $request['parent'] ) ? array_map( 'intval', (array) $request['parent'] ) : array(),
			'post_parent__not_in' => isset( $request['parent_exclude'] ) ? array_map( 'intval', (array) $request['parent_exclude'] ) : array(),
			's'                   => isset( $request['search'] ) ? sanitize_text_field( $request['search'] ) : '',
			'post_type'           => OHMYLMS_QUIZ_CPT,
			'post_status'         => isset( $request['post_status'] ) ? sanitize_text_field( $request['post_status'] ) : 'any',
		);

		$args['date_query'] = array();

		if ( isset( $request['before'] ) ) {
			$args['date_query'][0]['before'] = sanitize_text_field( $request['before'] );
		}

		if ( isset( $request['after'] ) ) {
			$args['date_query'][0]['after'] = sanitize_text_field( $request['after'] );
		}

		if ( isset( $request['filter'] ) && is_array( $request['filter'] ) ) {
			$args = array_merge( $args, $request['filter'] );
			unset( $args['filter'] );
		}

		$args       = apply_filters( 'ohmylms_rest_ohmylms_quiz_query', $args, $request );
		$query_args = $this->prepare_items_query( $args, $request );

		$posts_query  = new WP_Query();
		$query_result = $posts_query->query( $query_args );
		$posts        = array();

		foreach ( $query_result as $post ) {
			if ( ! AccessPolicy::can_edit_quiz( $post->ID ) ) {
				continue;
			}
			$data    = $this->prepare_item_for_response( $post, $request );
			$posts[] = $this->prepare_response_for_collection( $data );
		}
		$page        = (int) $query_args['paged'];
		$total_posts = $posts_query->found_posts;

		if ( 1 > $total_posts && 1 < $page ) {
			unset( $query_args['paged'] );
			$count_query = new WP_Query();
			$count_query->query( $query_args );
			$total_posts = $count_query->found_posts;
		}

		$max_pages = ceil( $total_posts / (int) $query_args['posts_per_page'] );

		$response = rest_ensure_response( $posts );
		$response->header( 'X-WP-Total', (int) $total_posts );
		$response->header( 'X-WP-TotalPages', (int) $max_pages );

		$request_params = $request->get_query_params();
		if ( ! empty( $request_params['filter'] ) ) {
			unset( $request_params['filter']['posts_per_page'] );
			unset( $request_params['filter']['paged'] );
		}
		$base = add_query_arg( $request_params, rest_url( sprintf( '/%s/%s', $this->namespace, $this->rest_base ) ) );

		if ( 1 < $page ) {
			$prev_page = $page - 1;
			if ( $prev_page > $max_pages ) {
				$prev_page = $max_pages;
			}
			$prev_link = add_query_arg( 'page', $prev_page, $base );
			$response->link_header( 'prev', $prev_link );
		}
		if ( $max_pages > $page ) {
			$next_page = $page + 1;
			$next_link = add_query_arg( 'page', $next_page, $base );
			$response->link_header( 'next', $next_link );
		}
		return $response;
	}

	/**
	 * Add quiz
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function create_item( $request ) {

		if ( ! empty( $request['id'] ) ) {
			// Translators: %s is replaced with error name.
			return new WP_Error( 'ohmylms_rest_quiz_exists', sprintf( __( 'Cannot create existing %s.', 'ohmylms' ), 'Quiz' ), array( 'status' => 400 ) );
		}
		try {
			$quiz_id = $this->save_quiz( $request );

			// The data store always inserts new quizzes as published; honor a requested draft.
			if ( 'draft' === ( $request['status'] ?? '' ) ) {
				wp_update_post(
					array(
						'ID'          => $quiz_id,
						'post_status' => 'draft',
					)
				);
			}
			$post = get_post( $quiz_id );
			/**
			 * Fires after a Quiz is inserted via the REST API.
			 *
			 * @param \WP_Post         $post    The post object for the quiz.
			 * @param \WP_REST_Request $request The request object.
			 * @param bool             $creating Whether the quiz is being created (true) or updated (false).
			 *
			 * @since 1.0.0
			 */
			do_action( 'ohmylms_rest_insert_quiz', $post, $request, true );

			$request->set_param( 'context', 'edit' );
			$response = $this->prepare_item_for_response( $post, $request );
			$response = rest_ensure_response( $response );
			if ( ! is_wp_error( $response ) ) {
				$response->set_status( 201 );
			}
			return $response;
		} catch ( DataException $e ) {
			return new WP_Error( 400, $e->getMessage(), array( 'status' => $e->getCode() ) );
		}
	}


	/**
	 * Add quiz
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function create_item_with_content( $request ) {

		if ( ! empty( $request['id'] ) ) {
			// Translators: %s is replaced with error name.
			return new WP_Error( 'ohmylms_rest_quiz_exists', sprintf( __( 'Cannot create existing %s.', 'ohmylms' ), 'Quiz' ), array( 'status' => 400 ) );
		}
		try {
			$quiz_id = $this->save_quiz( $request );

			$post = get_post( $quiz_id );
			/**
			 * Fires after a Quiz is inserted via the REST API.
			 *
			 * @param \WP_Post         $post    The post object for the quiz.
			 * @param \WP_REST_Request $request The request object.
			 * @param bool             $creating Whether the quiz is being created (true) or updated (false).
			 *
			 * @since 1.0.0
			 */
			do_action( 'ohmylms_rest_insert_quiz', $post, $request, true );

			$request->set_param( 'context', 'edit' );
			$response = $this->prepare_item_for_response( $post, $request );
			$response = rest_ensure_response( $response );
			if ( ! is_wp_error( $response ) ) {
				$response->set_status( 201 );
			}
			return $response;
		} catch ( DataException $e ) {
			return new WP_Error( 400, $e->getMessage(), array( 'status' => $e->getCode() ) );
		}
	}


	/**
	 * Delete bulk quizs.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response The response object indicating success or failure.
	 *
	 * @since 1.0.0
	 */
	public function trash_bulk( $request ) {
		$quiz_ids = $request->get_param( 'quiz_ids' );
		if ( is_array( $quiz_ids ) ) {
			foreach ( $quiz_ids as $quiz_id ) {
				if ( get_post_type( $quiz_id ) !== 'ohmylms-quiz' ) {
					return new \WP_REST_Response( array( 'message' => 'Invalid quiz ID.' ), 400 );
				}
				if ( ! AccessPolicy::can_delete_quiz( $quiz_id ) ) {
					return AccessPolicy::denied( __( 'You are not allowed to delete one of these quizzes.', 'ohmylms' ) );
				}
			}
			foreach ( $quiz_ids as $quiz_id ) {
				wp_trash_post( $quiz_id );
				do_action( 'ohmylms_rest_delete_quiz', $quiz_id );
			}
			return new \WP_REST_Response( array( 'message' => 'Deleted Successfully' ), 200 );
		}
		return new \WP_REST_Response( array( 'message' => 'Failed to trash the quiz.' ), 500 );
	}


	/**
	 * Update Quiz settings
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function update_item( $request ) {

		$post_id = (int) $request['id'];

		if ( empty( $post_id ) || get_post_type( $post_id ) !== OHMYLMS_QUIZ_CPT ) {
			return new WP_Error( 'ohmylms_rest_quiz_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		if ( ! empty( $request['base_modified'] ) ) {
			$current = get_post_field( 'post_modified_gmt', $post_id );
			if ( $current && strtotime( $current . ' UTC' ) > strtotime( (string) $request['base_modified'] . ' UTC' ) ) {
				return new WP_Error(
					'ohmylms_quiz_conflict',
					__( 'This quiz was changed by someone else. Reload it before saving.', 'ohmylms' ),
					array(
						'status'   => 409,
						'modified' => $current,
					)
				);
			}
		}
		$content = $request['content'] ?? null;
		if ( null !== $content && ! is_array( $content ) ) {
			return new WP_Error( 'ohmylms_quiz_content_invalid', __( 'Quiz content must be a list of questions.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		// Validate and authorize every question before anything is written.
		if ( $content ) {
			$plans = DraftWriter::prepare_many( $content, $post_id );
			if ( is_wp_error( $plans ) ) {
				return $plans;
			}
		}

		$saved_ids = array();
		try {
			$post = Transaction::run(
				function () use ( $request, $content, $post_id, &$saved_ids ) {
					$quiz_id = $this->save_quiz( $request );
					$post    = get_post( $quiz_id );
					$this->update_additional_fields_for_object( $post, $request );
					$this->update_post_meta_fields( $post, $request );
					if ( $content ) {
						$saved = DraftWriter::save_many( $content, $post_id );
						if ( is_wp_error( $saved ) ) {
							throw new DataException( $saved->get_error_code(), $saved->get_error_message(), (int) ( $saved->get_error_data()['status'] ?? 400 ), (array) $saved->get_error_data() );
						}
						$saved_ids = array_column( $saved, 'id' );
					}
					return $post;
				}
			);
		} catch ( DataException $e ) {
			return new WP_Error( $e->getErrorCode(), $e->getMessage(), $e->getErrorData() );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'ohmylms_quiz_storage', __( 'The quiz could not be saved. Nothing was changed.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		$request->set_param( 'context', 'edit' );
		$data                  = $this->prepare_item_for_response( $post, $request );
		$data_array            = rest_get_server()->response_to_data( $data, false );
		$data_array['content'] = ohmylms_get_quiz( $post->ID )->get_questions();
		// Server IDs for each submitted question, in submitted order, so editors can map temporary IDs.
		$data_array['saved_ids'] = $saved_ids;
		return rest_ensure_response( $data_array );
	}

	/**
	 * Save nested quiz questions through the authorized batch writer.
	 *
	 * @return array|WP_Error Saved IDs, or an error listing every failing question.
	 * @param mixed $post Post.
	 * @param mixed $request REST request.
	 */
	public function save_question( $post, $request ) {
		$questions = $request['content'] ?? array();
		if ( ! $questions ) {
			return array();
		}
		return DraftWriter::save_many( (array) $questions, $post->ID );
	}

	/**
	 * Get quiz content
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_item( $request ) {
		$id   = (int) $request['id'];
		$post = get_post( $id );
		if ( empty( $id ) || empty( $post->ID ) || OHMYLMS_QUIZ_CPT !== $post->post_type ) {
			return new WP_Error( 'ohmylms_rest_invalid_quiz_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		$data                  = $this->prepare_item_for_response( $post, $request );
		$question              = ohmylms_get_quiz( $post->ID )->get_questions();
		$data_array            = rest_get_server()->response_to_data( $data, false );
		$data_array['content'] = $question;
		$response              = rest_ensure_response( $data_array );

		$response->link_header( 'alternate', get_permalink( $id ), array( 'type' => 'text/html' ) );

		return $response;

		return rest_ensure_response( $response );
	}


	/**
	 * Delete a single quiz.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 *
	 * @since 1.0.0
	 */
	public function delete_item( $request ) {
		$quiz_id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( ! $quiz_id ) {
			return new WP_Error( 'ohmylms_rest_quiz_empty_id', __( 'ID is required.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$quiz = ohmylms_get_quiz( $quiz_id );

		if ( ! ( $quiz instanceof Quiz ) ) {
			return new WP_Error( 'ohmylms_rest_quiz_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$quiz->delete();

		/**
		 * Executes the 'ohmylms_rest_delete_quiz' action hook.
		 * This hook is triggered when a quiz is being deleted via the REST API.
		 *
		 * @param array $request The request array.
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_rest_delete_quiz', $quiz_id );

		$response = array(
			'id'      => $quiz_id,
			'status'  => 'success',
			'message' => __( 'Quiz has been deleted successfully.', 'ohmylms' ),
		);
		return rest_ensure_response( $response );
	}


	/**
	 * Save quiz
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return bool
	 * @since 1.0.0
	 */
	public function save_quiz( $request ) {
		$quiz = $this->prepare_item_for_database( $request );
		return $quiz->save();
	}


	/**
	 * Get report for Quiz controller.
	 *
	 * @param mixed $request REST request.
	 */
	public function get_report( $request ) {
		$id   = (int) $request['id'];
		$post = get_post( $id );
		if ( empty( $id ) || empty( $post->ID ) || OHMYLMS_QUIZ_CPT !== $post->post_type ) {
			return new WP_Error( 'ohmylms_rest_invalid_quiz_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$quiz = ohmylms_get_quiz( $post->ID );

		$report   = $quiz->get_report();
		$data     = array(
			'report'               => $report,
			'question_total_marks' => $quiz->get_total_marks(),
			'passing_mark'         => $quiz->get_passing_grade(),
		);
		$response = rest_ensure_response( $data );

		$response->link_header( 'alternate', get_permalink( $id ), array( 'type' => 'text/html' ) );
		return $response;
	}


	/**
	 * Get attempt report for a quiz.
	 * This method retrieves the report for a specific quiz attempt.
	 *
	 * @param \WP_REST_Request $request The REST request object containing the quiz ID and attempt ID.
	 * @return \WP_Error|\WP_REST_Response The response object containing the attempt report data or an error.
	 * @since 1.0.0
	 */
	public function get_attempt_report( $request ) {
		$id         = (int) $request['id'];
		$attempt_id = (int) $request['attempt_id'];
		$post       = get_post( $id );
		if ( empty( $id ) || empty( $post->ID ) || OHMYLMS_QUIZ_CPT !== $post->post_type ) {
			return new WP_Error( 'ohmylms_rest_invalid_quiz_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$quiz      = ohmylms_get_quiz( $post->ID );
		$report    = $quiz->get_attempt_report( $attempt_id );
		$attempt   = ohmylms_get_attempt( $attempt_id );
		$student   = $attempt->get_student();
		$course_id = $attempt->get_course_id();
		$course    = ohmylms_get_course( $course_id );
		$data      = array(
			'report'               => $report,
			'question_total_marks' => $quiz->get_total_marks(),
			'total_question'       => $quiz->get_total_question(),
			'passing_mark'         => $quiz->get_passing_grade(),
			'start_date'           => $attempt->get_start_date(),
			'end_date'             => ( $attempt->get_end_date() instanceof \DateTimeInterface ) ? $attempt->get_end_date()->format( 'Y-m-d H:i:s' ) : $attempt->get_end_date(),
			'score'                => $attempt->get_total_score(),
			'student'              => array(
				'id'    => $student->get_id(),
				'name'  => $student->get_name(),
				'email' => $student->get_email(),
			),
			'course'               => array(
				'id'   => $course->get_id(),
				'name' => $course->get_name(),
			),
		);
		$response  = rest_ensure_response( $data );
		$response->link_header( 'alternate', get_permalink( $id ), array( 'type' => 'text/html' ) );
		return $response;
	}

	/**
	 * Review attempt report for a quiz.
	 * This method allows the review of a specific quiz attempt by updating the questions and their answers.
	 *
	 * @param \WP_REST_Request $request The REST request object containing the quiz ID and attempt ID.
	 * @return \WP_Error|\WP_REST_Response The response object containing the updated attempt report data or an error.
	 * @since 1.0.0
	 */
	public function review_attempt_report( $request ) {
		$id         = (int) $request['id'];
		$attempt_id = (int) $request['attempt_id'];
		$data       = $request->get_params();

		$quiz = ohmylms_get_quiz( $id );
		if ( empty( $quiz ) ) {
			return new WP_Error( 'ohmylms_rest_invalid_quiz_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		// Only marks that actually change, or that are awaiting manual review, are submitted.
		global $wpdb;
		$stored = $wpdb->get_results( $wpdb->prepare( "SELECT question_id, achive_mark, is_manually_reviewed FROM {$wpdb->prefix}ohmylms_quiz_attempts_answers WHERE quiz_attempt_id=%d", $attempt_id ), OBJECT_K );
		$marks  = array();
		foreach ( (array) ( $data['report']['questions'] ?? array() ) as $question ) {
			if ( ! is_array( $question ) || ! isset( $question['id'], $question['achive_mark'] ) || ! isset( $stored[ $question['id'] ] ) ) {
				continue;
			}
			$row     = $stored[ $question['id'] ];
			$pending = 'in-review' === ( $question['status'] ?? '' ) && empty( $row->is_manually_reviewed );
			if ( $pending || (float) $row->achive_mark !== (float) $question['achive_mark'] ) {
				$marks[ (int) $question['id'] ] = $question['achive_mark'];
			}
			// Structured questions may be marked part by part.
			if ( isset( $question['part_marks'] ) && is_array( $question['part_marks'] ) && $question['part_marks'] ) {
				$marks[ (int) $question['id'] ] = array_map( 'floatval', $question['part_marks'] );
			}
		}

		$report = Review::save( $id, $attempt_id, $marks );
		if ( is_wp_error( $report ) ) {
			return $report;
		}

		return rest_ensure_response(
			array(
				'status'  => 'success',
				'message' => __( 'Quiz has been reviewed successfully.', 'ohmylms' ),
				'data'    => $report,
			)
		);
	}


	/**
	 * Update attempt report manually for a quiz.
	 * This method allows manual updates to the attempt report for a specific quiz attempt.
	 *
	 * @param \WP_REST_Request $request The REST request object containing the quiz ID, attempt ID, and other parameters.
	 * @return \WP_Error|\WP_REST_Response The response object containing the updated attempt report data or an error.
	 * @since 1.0.0
	 */
	public function update_attempt_report_manually( $request ) {
		$id          = (int) $request['id'];
		$attempt_id  = (int) $request['attempt_id'];
		$question_id = (int) $request['quiz_attempt_answer_id'];
		$quiz        = ohmylms_get_quiz( $id );
		if ( empty( $quiz ) ) {
			return new WP_Error( 'ohmylms_rest_invalid_quiz_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		// Historical route name: the last segment is the question ID within the attempt.
		$report = Review::save( $id, $attempt_id, array( $question_id => $request['marks'] ) );
		if ( is_wp_error( $report ) ) {
			return $report;
		}
		$response = rest_ensure_response(
			array(
				'report'               => $report,
				'question_total_marks' => $quiz->get_total_marks(),
				'total_question'       => $quiz->get_total_question(),
				'passing_mark'         => $quiz->get_passing_grade(),
			)
		);
		$response->link_header( 'alternate', get_permalink( $id ), array( 'type' => 'text/html' ) );
		return $response;
	}


	/**
	 * Prepare a quiz for database.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return bool|Quiz
	 *
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	protected function prepare_item_for_database( $request ) {
		$id = isset( $request['id'] ) ? absint( $request['id'] ) : 0;

		if ( isset( $request['id'] ) ) {
			$quiz = ohmylms_get_quiz( $id );
		} else {
			$quiz = new Quiz();
		}

		if ( isset( $request['name'] ) ) {
			$quiz->set_name( wp_filter_post_kses( $request['name'] ) );
		}

		if ( isset( $request['description'] ) ) {
			$quiz->set_description( wp_filter_post_kses( $request['description'] ) );
		}

		if ( isset( $request['slug'] ) ) {
			$quiz->set_slug( wp_filter_post_kses( $request['slug'] ) );
		}

		if ( isset( $request['status'] ) ) {
			$quiz->set_status( get_post_status_object( $request['status'] ) ? $request['status'] : 'draft' );
		}
		$this->set_quiz_meta( $quiz, $request );
		return $quiz;
	}

	/**
	 * Prepare a single quiz for response.
	 *
	 * @param \WP_Post         $post The post object.
	 * @param \WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 *
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function prepare_item_for_response( $post, $request ) {
		$quiz = ohmylms_get_quiz( $post->ID );
		$data = $this->get_quiz_data( $quiz );

		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $quiz, $request ) );

		/**
		 * Filters the response for the quiz in the REST API.
		 *
		 * This filter allows developers to modify the quiz response data before it is returned by the REST API.
		 *
		 * @param array $response The response data for the quiz.
		 * @param \WP_Post $post The WP_Post object representing the quiz.
		 * @param \WP_REST_Request $request The request object containing information about the API request.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'ohmylms_rest_prepare_quiz', $response, $post, $request );
	}




	/**
	 * Get quiz data.
	 *
	 * @param quiz $quiz Quiz.
	 * @return array
	 *
	 * @since 1.0.0
	 */
	protected function get_quiz_data( $quiz ) {
		$course_id = ohmylms_get_course_by_content_id( $quiz->get_id() );
		$courses   = array();
		if ( $course_id ) {
			$course = ohmylms_get_course( $course_id );
			if ( $course ) {
				$courses['id']          = $course_id;
				$courses['course_name'] = $course->get_name();
			}
		}
		$report                = $quiz->get_report();
		$number_of_submissions = 0;
		if ( is_array( $report ) ) {
			$number_of_submissions = count( $report );
		}
		$data = array(
			'id'                    => $quiz->get_id(),
			'name'                  => $quiz->get_name(),
			'type'                  => 'quiz',
			'skill_ids'             => \OhMyLMS\Quiz\QuizSkills::get( $quiz->get_id() ),
			'description'           => $quiz->get_description(),
			'preview_url'           => \OhMyLMS\Assessment\PreviewPlayer::url( $quiz->get_id() ),
			'slug'                  => $quiz->get_slug(),
			'status'                => $quiz->get_status(),
			'settings'              => $quiz->get_settings(),
			'drip_settings'         => $quiz->get_drip_settings(),
			'courses'               => $courses,
			'number_of_submissions' => $number_of_submissions,
			'date_created'          => $quiz->get_date_created(),
			'modified'              => get_post_field( 'post_modified_gmt', $quiz->get_id() ),
		);
		return $data;
	}




	/**
	 * Prepare links for the request.
	 *
	 * @param mixed $product Product.
	 * @param mixed $request REST request.
	 * @return array[]
	 *
	 * @since 1.0.0
	 */
	protected function prepare_links( $product, $request ) {
		$links = array(
			'self'       => array(
				'href' => rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->base, $product->get_id() ) ),
			),
			'collection' => array(
				'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->base ) ),
			),
		);

		return $links;
	}



	/**
	 * Update post meta fields for a quiz.
	 *
	 * This method updates the meta fields for a given quiz post based on the provided request data.
	 *
	 * @param \WP_Post         $post The post object representing the quiz.
	 * @param \WP_REST_Request $request The REST request object containing the meta data.
	 * @return bool True on success, false on failure.
	 *
	 * @throws DataException When the operation cannot be completed.
	 * @since 1.0.0
	 */
	protected function update_post_meta_fields( $post, $request ) {
		$quiz = ohmylms_get_quiz( $post );
		$quiz = $this->set_quiz_meta( $quiz, $request );
		$quiz->save();
		/**
		 * Fires after the meta data for a quiz is updated.
		 *
		 * @param WP_Post $quiz The updated quiz object.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_rest_quiz_meta_updated', $quiz );

		return true;
	}


	/**
	 * Set product meta data for a quiz.
	 *
	 * @param Quiz            $quiz The quiz object.
	 * @param WP_REST_Request $request The REST request object containing the meta data.
	 * @return Quiz The updated quiz object.
	 *
	 * @since 1.0.0
	 */
	protected function set_quiz_meta( $quiz, $request ) {
		if ( isset( $request['questions'] ) ) {
			$quiz->set_questions( $request['questions'] );
		}
		if ( isset( $request['settings'] ) ) {
			$quiz->set_settings( $request['settings'] );
		}
		if ( isset( $request['drip_settings'] ) ) {
			$quiz->set_drip_settings( $request['drip_settings'] );
		}

		return $quiz;
	}



	/**
	 * Prepares the query arguments for fetching items.
	 *
	 * This function filters and constructs the query arguments based on the allowed query variables.
	 * It ensures that only valid query variables are included in the final query arguments.
	 *
	 * @param array                $prepared_args The prepared arguments for the query.
	 * @param WP_REST_Request|null $request The REST request object.
	 *
	 * @return array The filtered and prepared query arguments.
	 * @since 1.0.0
	 */
	protected function prepare_items_query( $prepared_args = array(), $request = null ) {

		$valid_vars = array_flip( $this->get_allowed_query_vars() );
		$query_args = array();
		foreach ( $valid_vars as $var => $index ) {
			if ( isset( $prepared_args[ $var ] ) ) {
				/**
				 * Filter the query_vars used in `get_items` for the constructed query.
				 *
				 * The dynamic portion of the hook name, $var, refers to the query_var key.
				 *
				 * @param mixed $prepared_args[ $var ] The query_var value.
				 */
				$query_args[ $var ] = apply_filters( "ohmylms_rest_query_var-{$var}", $prepared_args[ $var ] );
			}
		}

		$query_args['ignore_sticky_posts'] = true;

		if ( 'include' === $query_args['orderby'] ) {
			$query_args['orderby'] = 'post__in';
		} elseif ( 'id' === $query_args['orderby'] ) {
			$query_args['orderby'] = 'ID'; // ID must be capitalized.
		} elseif ( 'slug' === $query_args['orderby'] ) {
			$query_args['orderby'] = 'name';
		}

		return $query_args;
	}



	/**
	 * Get the allowed query variables for the REST API.
	 *
	 * This method retrieves the list of query variables that are allowed to be used
	 * in REST API requests for quizs. It merges the public and private query variables
	 * and applies filters to allow customization.
	 *
	 * @return array The array of allowed query variables.
	 *
	 * @since 1.0.0
	 */
	protected function get_allowed_query_vars() {
		global $wp;

		/**
		 * Filter the publicly allowed query vars.
		 *
		 * Allows adjusting of the default query vars that are made public.
		 *
		 * @param array  Array of allowed WP_Query query vars.
		 */
		$valid_vars = apply_filters( 'query_vars', $wp->public_query_vars );

		$post_type_obj = get_post_type_object( OHMYLMS_QUIZ_CPT );
		if ( current_user_can( $post_type_obj->cap->edit_posts ) ) {
			$valid_vars = array_merge( $valid_vars, $wp->private_query_vars );
		}
		$rest_valid = array(
			'date_query',
			'ignore_sticky_posts',
			'offset',
			'post__in',
			'post__not_in',
			'post_parent',
			'post_parent__in',
			'post_parent__not_in',
			'posts_per_page',
			'meta_query',
			'tax_query',
			'meta_key',
			'meta_value',
			'meta_compare',
			'meta_value_num',
		);
		$valid_vars = array_merge( $valid_vars, $rest_valid );

		/**
		 * Filter the valid query variables for the REST API.
		 *
		 * This filter allows developers to modify the list of valid query variables
		 * that can be used in REST API requests for quizs.
		 *
		 * @param array $valid_vars The array of valid query variables.
		 */
		$valid_vars = apply_filters( 'ohmylms_rest_query_vars', $valid_vars );

		return $valid_vars;
	}


	/**
	 * Retrieves the lessons of a chapter.
	 *
	 * This method fetches the lessons associated with a specific chapter ID.
	 *
	 * @param \WP_REST_Request $request The REST request object containing the chapter ID.
	 * @return WP_Error|\WP_HTTP_Response|\WP_REST_Response The response object containing the lessons data or an error.
	 *
	 * @since 1.0.0
	 */
	public function get_questions( $request ) {
		$quiz_id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		// Check the chapter id exist or not.
		if ( ! $quiz_id ) {
			return new WP_Error( 'ohmylms_rest_quiz_empty_id', __( 'ID is required.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		// Get existing chapter by chapter id.
		$quiz = ohmylms_get_quiz( $quiz_id );

		// Check the chapter exist or not.
		if ( ! ( $quiz instanceof Quiz ) ) {
			return new WP_Error( 'ohmylms_rest_quiz_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$questions = $quiz->get_questions();

		$response = array(
			'status'  => 'success',
			'message' => __( 'Quiz fetched successfully', 'ohmylms' ),
			'data'    => $questions,
		);

		return rest_ensure_response( $response );
	}



	/**
	 * Updates the contents of a chapter.
	 *
	 * This method updates the lessons associated with a specific chapter ID.
	 *
	 * @param WP_REST_Request $request The REST request object containing the chapter ID and lessons data.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response The response object indicating success or failure.
	 *
	 * @since 1.0.0
	 */
	public function update_questions( $request ) {
		$quiz_id = (int) $request['id'];

		// Check the chapter id exist or not.
		if ( ! $quiz_id ) {
			return new WP_Error( 'ohmylms_rest_chapter_empty_id', __( 'ID is required.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		// Get existing chapter by chapter id.
		$quiz = ohmylms_get_quiz( $quiz_id );

		// Check the chapter exist or not.
		if ( ! ( $quiz instanceof Quiz ) ) {
			return new WP_Error( 'ohmylms_rest_chapter_empty_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$all_data       = (array) $request->get_json_params();
		$questions_data = isset( $all_data['questions'] ) && is_array( $all_data['questions'] ) ? $all_data['questions'] : array();
		$plans          = DraftWriter::prepare_many( $questions_data, $quiz_id );
		if ( is_wp_error( $plans ) ) {
			return $plans;
		}
		try {
			Transaction::run(
				function () use ( $all_data, $questions_data, $quiz_id ) {
					if ( isset( $all_data['quiz'] ) && is_array( $all_data['quiz'] ) ) {
						$this->save_quiz( array( 'id' => $quiz_id ) + $all_data['quiz'] );
					}
					$saved = DraftWriter::save_many( $questions_data, $quiz_id );
					if ( is_wp_error( $saved ) ) {
						throw new DataException( $saved->get_error_code(), $saved->get_error_message(), (int) ( $saved->get_error_data()['status'] ?? 400 ), (array) $saved->get_error_data() );
					}
				}
			);
		} catch ( DataException $e ) {
			return new WP_Error( $e->getErrorCode(), $e->getMessage(), $e->getErrorData() );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'ohmylms_quiz_storage', __( 'The quiz could not be saved. Nothing was changed.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		$response = array(
			'status'  => 'success',
			'message' => __( 'Contents updated successfully', 'ohmylms' ),
		);
		return rest_ensure_response( $response );
	}
}
