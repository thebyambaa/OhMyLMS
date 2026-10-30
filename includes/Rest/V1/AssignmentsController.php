<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * AssignmentsController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Data\Assignment;
use OhMyLMS\Learning\Placements;
use OhMyLMS\Utility\Transaction;
use WP_Error;
use WP_HTTP_Response;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * REST API for assignments under `ohmylms/v1/assignments`. See docs/api/assignments.md for the contract.
 *
 * Authentication is WordPress core's (cookie + `X-WP-Nonce`, or application passwords). Capabilities
 * mirror the legacy admin: the assignment post type's `edit_posts` to list and create, and `edit_post` /
 * `delete_post` on the assignment for everything else. Reviewing and grading submissions needs
 * `edit_post` on the assignment, as the legacy report and grading routes did. Learner submissions are
 * rows of `ohmylms_assignment_attempts`, written by the learner front end
 * ({@see \OhMyLMS\Data\Assignment::submit_file_submission()}); grading goes through the legacy pipeline
 * ({@see AssignmentController::update_attempt_report()}) so learners get the same hooks and emails.
 *
 * @since 1.0.0
 */
class AssignmentsController extends RestController {

	/**
	 * Writable publication states.
	 */
	const STATUSES = array( 'draft', 'publish' );

	/**
	 * Time limit units the learner deadline understands (`P<n>D|W|M`).
	 */
	const TIME_UNITS = array( 'day', 'week', 'month' );

	/**
	 * Largest total score. Scores are stored in an INT column.
	 */
	const MAX_SCORE = 1000;

	/**
	 * Largest number of uploads per learner.
	 */
	const MAX_FILES = 20;

	/**
	 * Largest per-file size limit in megabytes.
	 */
	const MAX_FILE_SIZE_MB = 1024;

	/**
	 * Largest time limit, in units.
	 */
	const MAX_TIME_LIMIT = 365;

	/**
	 * Longest grader feedback.
	 */
	const MAX_FEEDBACK = 10000;

	/**
	 * Settings stored as post meta: field => meta key.
	 */
	const SETTING_KEYS = array(
		'max_score'               => '_total_points',
		'pass_score'              => '_maximum_pass_points',
		'time_limit_enabled'      => '_enable_time_limit',
		'time_limit'              => '_time_limit',
		'time_limit_unit'         => '_time_limit_type',
		'allow_file_uploads'      => '_allow_upload_files',
		'max_files'               => '_number_of_files',
		'file_size_limit_enabled' => '_enable_file_size_limit',
		'max_file_size_mb'        => '_max_file_size_limit',
	);

	/**
	 * Settings of a new assignment (the legacy data-class defaults).
	 */
	const DEFAULTS = array(
		'max_score'               => 50,
		'pass_score'              => 25,
		'time_limit_enabled'      => false,
		'time_limit'              => 10,
		'time_limit_unit'         => 'week',
		'allow_file_uploads'      => true,
		'max_files'               => 5,
		'file_size_limit_enabled' => true,
		'max_file_size_mb'        => 5,
	);

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
	protected $rest_base = 'assignments';

	/**
	 * Figures for the current collection page, keyed by assignment ID.
	 *
	 * @var array{submissions:array<int,array{total:int,pending:int}>,courses:array<int,int>}|null
	 */
	private $stats = null;

	/**
	 * Verified upload waiting to be streamed by {@see self::serve_download()}.
	 *
	 * @var string|null
	 */
	private static $download = null;

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$base       = '/' . $this->rest_base;
		$item       = $base . '/(?P<id>[\d]+)';
		$submission = $item . '/submissions/(?P<submission_id>[\d]+)';
		$id         = array(
			'description' => __( 'Unique identifier for the assignment.', 'ohmylms' ),
			'type'        => 'integer',
			'minimum'     => 1,
		);
		$sub_id     = array(
			'description' => __( 'Unique identifier for the submission.', 'ohmylms' ),
			'type'        => 'integer',
			'minimum'     => 1,
		);

		register_rest_route(
			$this->namespace,
			$base,
			array(
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
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/courses',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_courses' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_course_collection_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$item,
			array(
				'args'   => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param( array( 'default' => 'view' ) ),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_update_args(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
					'args'                => array(
						'force' => array(
							'description' => __( 'Delete permanently instead of moving to the trash.', 'ohmylms' ),
							'type'        => 'boolean',
							'default'     => false,
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			$item . '/submissions',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_submissions' ),
					'permission_callback' => array( $this, 'grading_permissions_check' ),
					'args'                => $this->get_submission_collection_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$submission,
			array(
				'args' => array(
					'id'            => $id,
					'submission_id' => $sub_id,
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_submission' ),
					'permission_callback' => array( $this, 'grading_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'grade_submission' ),
					'permission_callback' => array( $this, 'grading_permissions_check' ),
					'args'                => array(
						'score'    => array(
							'description' => __( 'Whole-number score from 0 to the assignment maximum.', 'ohmylms' ),
							'type'        => 'integer',
							'minimum'     => 0,
							'maximum'     => self::MAX_SCORE,
							'required'    => true,
						),
						'feedback' => array(
							'description' => __( 'Feedback for the learner (post-safe HTML).', 'ohmylms' ),
							'type'        => 'string',
							'maxLength'   => self::MAX_FEEDBACK,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$submission . '/file',
			array(
				'args' => array(
					'id'            => $id,
					'submission_id' => $sub_id,
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'download_submission_file' ),
					'permission_callback' => array( $this, 'grading_permissions_check' ),
				),
			)
		);

		if ( ! has_filter( 'rest_pre_serve_request', array( self::class, 'serve_download' ) ) ) {
			add_filter( 'rest_pre_serve_request', array( self::class, 'serve_download' ), 10, 3 );
		}
	}

	// Permissions.

	/**
	 * Build a permission error: 401 for guests, 403 otherwise.
	 *
	 * @param string $code    Error code.
	 * @param string $message Message.
	 * @return WP_Error
	 */
	private function forbidden( $code, $message ) {
		return new WP_Error( $code, $message, array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Build the not-found error.
	 *
	 * @return WP_Error
	 */
	private function not_found() {
		return new WP_Error( 'ohmylms_assignment_not_found', __( 'Assignment not found.', 'ohmylms' ), array( 'status' => 404 ) );
	}

	/**
	 * Load an assignment post or a 404 error.
	 *
	 * @param mixed $id Assignment ID.
	 * @return WP_Post|WP_Error
	 */
	private function get_assignment_post( $id ) {
		$post = get_post( absint( $id ) );
		if ( ! $post || OHMYLMS_ASSIGNMENT_CPT !== $post->post_type || 'auto-draft' === $post->post_status ) {
			return $this->not_found();
		}

		return $post;
	}

	/**
	 * Whether the current user has a primitive capability of the assignment post type.
	 *
	 * @param string $cap Key of the post type's `cap` object, such as `edit_posts`.
	 * @return bool
	 */
	private function type_can( $cap ) {
		$type = get_post_type_object( OHMYLMS_ASSIGNMENT_CPT );

		return $type && current_user_can( $type->cap->$cap );
	}

	/**
	 * Permission for the list and the course picker.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return $this->type_can( 'edit_posts' ) ? true : $this->forbidden( 'ohmylms_assignment_cannot_view', __( 'Sorry, you are not allowed to view assignments.', 'ohmylms' ) );
	}

	/**
	 * Permission to create an assignment (publishing needs `publish_posts`).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! $this->type_can( 'edit_posts' ) ) {
			return $this->forbidden( 'ohmylms_assignment_cannot_create', __( 'Sorry, you are not allowed to create assignments.', 'ohmylms' ) );
		}
		if ( 'publish' === $request['status'] && ! $this->type_can( 'publish_posts' ) ) {
			return $this->forbidden( 'ohmylms_assignment_cannot_publish', __( 'Sorry, you are not allowed to publish assignments.', 'ohmylms' ) );
		}

		return true;
	}

	/**
	 * Permission to read one assignment.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		$post = $this->get_assignment_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return current_user_can( 'edit_post', $post->ID ) ? true : $this->forbidden( 'ohmylms_assignment_cannot_view', __( 'Sorry, you are not allowed to view this assignment.', 'ohmylms' ) );
	}

	/**
	 * Permission to update an assignment.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		$post = $this->get_assignment_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $this->forbidden( 'ohmylms_assignment_cannot_edit', __( 'Sorry, you are not allowed to edit this assignment.', 'ohmylms' ) );
		}
		if ( 'publish' === $request['status'] && 'publish' !== $post->post_status && ! $this->type_can( 'publish_posts' ) ) {
			return $this->forbidden( 'ohmylms_assignment_cannot_publish', __( 'Sorry, you are not allowed to publish assignments.', 'ohmylms' ) );
		}

		return true;
	}

	/**
	 * Permission to delete an assignment.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		$post = $this->get_assignment_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return current_user_can( 'delete_post', $post->ID ) ? true : $this->forbidden( 'ohmylms_assignment_cannot_delete', __( 'Sorry, you are not allowed to delete this assignment.', 'ohmylms' ) );
	}

	/**
	 * Permission to review and grade submissions: `edit_post` on the assignment, as in the legacy report.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function grading_permissions_check( $request ) {
		$post = $this->get_assignment_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return current_user_can( 'edit_post', $post->ID ) ? true : $this->forbidden( 'ohmylms_assignment_cannot_grade', __( 'Sorry, you are not allowed to review submissions of this assignment.', 'ohmylms' ) );
	}

	// Collection.

	/**
	 * List assignments.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$page     = max( 1, (int) $request['page'] );
		$per_page = min( 100, max( 1, (int) $request['per_page'] ) );
		$orderby  = array(
			'id'         => 'ID',
			'title'      => 'title',
			'status'     => 'post_status',
			'created_at' => 'date',
			'updated_at' => 'modified',
		);
		$status   = (string) $request['status'];
		$args     = array(
			'post_type'              => OHMYLMS_ASSIGNMENT_CPT,
			'post_status'            => 'all' === $status ? array( 'publish', 'draft', 'pending', 'private', 'future' ) : array( $status ),
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'orderby'                => $orderby[ $request['orderby'] ],
			'order'                  => strtoupper( (string) $request['order'] ),
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
		);
		if ( '' !== (string) $request['search'] ) {
			$args['s'] = (string) $request['search'];
		}
		if ( ! $this->type_can( 'edit_others_posts' ) ) {
			$args['author'] = get_current_user_id();
		}
		if ( ! empty( $request['course'] ) ) {
			$ids              = $this->assignment_ids_in_course( (int) $request['course'] );
			$args['post__in'] = $ids ? $ids : array( 0 );
		}

		/**
		 * Filters the WP_Query arguments used by GET /assignments.
		 *
		 * @param array           $args    Query arguments.
		 * @param WP_REST_Request $request The request.
		 */
		$args = apply_filters( 'ohmylms_rest_ohmylms_assignment_query', $args, $request );

		$query = new WP_Query( $args );
		$total = $this->found_posts( $query, $args );
		$pages = (int) ceil( $total / $per_page );
		if ( $page > $pages && $total > 0 ) {
			return $this->invalid_page();
		}

		$this->stats = $this->page_stats( wp_list_pluck( $query->posts, 'ID' ) );
		$data        = array();
		foreach ( $query->posts as $post ) {
			$data[] = $this->prepare_response_for_collection( $this->prepare_item_for_response( $post, $request ) );
		}
		$this->stats = null;

		$response = rest_ensure_response( $data );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $pages );

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
	 * Search the courses the list can be filtered by (the user's own unless they edit others' posts).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_courses( $request ) {
		$page     = max( 1, (int) $request['page'] );
		$per_page = min( 50, max( 1, (int) $request['per_page'] ) );
		$type     = get_post_type_object( OHMYLMS_COURSE_CPT );
		$args     = array(
			'post_type'              => OHMYLMS_COURSE_CPT,
			'post_status'            => array( 'publish', 'draft', 'private', 'future', 'pending' ),
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);
		if ( '' !== (string) $request['search'] ) {
			$args['s'] = (string) $request['search'];
		}
		if ( $type && ! current_user_can( $type->cap->edit_others_posts ) ) {
			$args['author'] = get_current_user_id();
		}
		$query = new WP_Query( $args );
		$total = $this->found_posts( $query, $args );
		$pages = (int) ceil( $total / $per_page );
		if ( $page > $pages && $total > 0 ) {
			return $this->invalid_page();
		}

		$response = rest_ensure_response( array_map( array( $this, 'course_ref' ), $query->posts ) );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $pages );

		return $response;
	}

	/**
	 * The error for a page past the end.
	 *
	 * @return WP_Error
	 */
	private function invalid_page() {
		return new WP_Error( 'ohmylms_assignment_invalid_page', __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), array( 'status' => 400 ) );
	}

	/**
	 * Total matches of a query; a page past the end reports no rows, so it is counted again from page one.
	 *
	 * @param WP_Query $query Executed query.
	 * @param array    $args  Its arguments.
	 * @return int
	 */
	private function found_posts( WP_Query $query, array $args ) {
		if ( $query->posts || (int) $args['paged'] <= 1 ) {
			return (int) $query->found_posts;
		}
		$count = new WP_Query(
			array_merge(
				$args,
				array(
					'paged'          => 1,
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			)
		);

		return (int) $count->found_posts;
	}

	/**
	 * Content IDs placed in a course, through its chapters or a published learning program.
	 *
	 * @param int $course_id Course ID.
	 * @return int[]
	 */
	private function assignment_ids_in_course( $course_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT DISTINCT content.content_id FROM %i content INNER JOIN %i chapter ON chapter.chapter_id = content.chapter_id WHERE chapter.course_id = %d LIMIT 5000',
				$wpdb->prefix . 'ohmylms_content_relationship',
				$wpdb->prefix . 'ohmylms_chapter_relationship',
				$course_id
			)
		);
		foreach ( Placements::index() as $content_id => $courses ) {
			if ( in_array( (int) $course_id, array_map( 'intval', (array) $courses ), true ) ) {
				$ids[] = $content_id;
			}
		}

		return array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
	}

	/**
	 * Submission and course figures for a page of assignments, with one query each.
	 *
	 * @param int[] $ids Assignment IDs.
	 * @return array{submissions:array<int,array{total:int,pending:int}>,courses:array<int,int>}
	 */
	private function page_stats( array $ids ) {
		global $wpdb;

		$stats = array(
			'submissions' => array(),
			'courses'     => array(),
		);
		$ids   = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( ! $ids ) {
			return $stats;
		}
		$holders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT assignment_id, COUNT(*) AS total, SUM(status = 'submitted') AS pending FROM %i WHERE assignment_id IN ({$holders}) GROUP BY assignment_id", array_merge( array( $this->attempts_table() ), $ids ) ) );
		foreach ( (array) $rows as $row ) {
			$stats['submissions'][ (int) $row->assignment_id ] = array(
				'total'   => (int) $row->total,
				'pending' => (int) $row->pending,
			);
		}
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT content.content_id, MIN(chapter.course_id) AS course_id FROM %i content INNER JOIN %i chapter ON chapter.chapter_id = content.chapter_id WHERE content.content_id IN ({$holders}) GROUP BY content.content_id", array_merge( array( $wpdb->prefix . 'ohmylms_content_relationship', $wpdb->prefix . 'ohmylms_chapter_relationship' ), $ids ) ) );
		// phpcs:enable
		foreach ( (array) $rows as $row ) {
			$stats['courses'][ (int) $row->content_id ] = (int) $row->course_id;
		}
		foreach ( $ids as $id ) {
			if ( empty( $stats['courses'][ $id ] ) ) {
				$placed                   = Placements::courses_for( $id );
				$stats['courses'][ $id ] = $placed ? (int) $placed[0] : 0;
			}
		}
		$courses = array_values( array_filter( array_unique( $stats['courses'] ) ) );
		if ( $courses ) {
			_prime_post_caches( $courses, false, false );
		}

		return $stats;
	}

	// Item.

	/**
	 * Read one assignment.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$post = $this->get_assignment_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return $this->prepare_item_for_response( $post, $request );
	}

	/**
	 * Create an assignment. Settings not sent get the legacy defaults.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		if ( ! empty( $request['id'] ) ) {
			return new WP_Error( 'ohmylms_assignment_exists', __( 'Cannot create an existing assignment.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$input = $this->validated_input( $request, self::DEFAULTS );
		if ( is_wp_error( $input ) ) {
			return $input;
		}
		$fields   = $input['fields'];
		$settings = array_merge( self::DEFAULTS, $input['settings'] );
		try {
			$id = Transaction::run(
				static function () use ( $fields, $settings ) {
					$id = wp_insert_post(
						wp_slash(
							array_merge(
								array(
									'post_type'    => OHMYLMS_ASSIGNMENT_CPT,
									'post_status'  => 'draft',
									'post_author'  => get_current_user_id(),
									'post_content' => '',
								),
								$fields
							)
						),
						true
					);
					if ( is_wp_error( $id ) || ! $id ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal message, caught in this method and never output.
						throw new \RuntimeException( is_wp_error( $id ) ? $id->get_error_message() : 'insert failed' );
					}
					update_post_meta( $id, '_type', 'assignment' );
					self::write_settings( $id, $settings );

					return (int) $id;
				}
			);
		} catch ( \Throwable $error ) {
			return new WP_Error( 'ohmylms_assignment_create_failed', __( 'The assignment could not be created.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		$post = get_post( $id );
		$request->set_param( 'chapter_id', null );

		/** This action is documented in includes/DataStores/AssignmentStore.php. */
		do_action( 'ohmylms_after_creating_new_assignment', $id, new Assignment( $id ) );
		/** This action is documented in includes/Rest/V1/AssignmentController.php. */
		do_action( 'ohmylms_rest_insert_assignment', $post, $request, true );

		$request->set_param( 'context', 'edit' );
		$response = $this->prepare_item_for_response( $post, $request );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $id ) ) );

		return $response;
	}

	/**
	 * Update an assignment. Omitted fields are unchanged. `base_modified` (the `updated_at` the client
	 * loaded) makes a save of a stale copy fail with 409. A trashed assignment is restored by sending a status.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$post = $this->get_assignment_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! empty( $request['base_modified'] ) ) {
			$base    = strtotime( (string) $request['base_modified'] );
			$current = strtotime( $post->post_modified_gmt . ' UTC' );
			if ( ! $base || ( $current && $current > $base ) ) {
				return new WP_Error(
					'ohmylms_assignment_conflict',
					__( 'This assignment was changed by someone else. Reload it before saving.', 'ohmylms' ),
					array(
						'status'   => 409,
						'modified' => $this->iso( $post->post_modified_gmt ),
					)
				);
			}
		}
		$input = $this->validated_input( $request, $this->read_settings( $post->ID ) );
		if ( is_wp_error( $input ) ) {
			return $input;
		}
		$fields   = $input['fields'];
		$settings = $input['settings'];
		$restore  = 'trash' === $post->post_status && isset( $fields['post_status'] );
		try {
			Transaction::run(
				static function () use ( $post, $fields, $settings, $restore ) {
					if ( $restore ) {
						wp_untrash_post( $post->ID );
					}
					// Always touch the post so `updated_at` moves and the stale-save check covers settings too.
					$updated = wp_update_post( wp_slash( array_merge( array( 'ID' => $post->ID ), $fields ) ), true );
					if ( is_wp_error( $updated ) || ! $updated ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal message, caught in this method and never output.
						throw new \RuntimeException( is_wp_error( $updated ) ? $updated->get_error_message() : 'update failed' );
					}
					self::write_settings( $post->ID, $settings );
				}
			);
		} catch ( \Throwable $error ) {
			return new WP_Error( 'ohmylms_assignment_update_failed', __( 'The assignment could not be saved. Nothing was changed.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		clean_post_cache( $post->ID );
		$updated = get_post( $post->ID );
		$request->set_param( 'chapter_id', null );

		/** This action is documented in includes/Rest/V1/AssignmentController.php. */
		do_action( 'ohmylms_rest_insert_assignment', $updated, $request, false );
		/** This action is documented in includes/DataStores/AssignmentStore.php. */
		do_action( 'ohmylms_after_updating_assignment', $updated->ID, new Assignment( $updated->ID ) );

		$request->set_param( 'context', 'edit' );

		return $this->prepare_item_for_response( $updated, $request );
	}

	/**
	 * Move an assignment to the trash (as the legacy list did) or delete it for good with `force`.
	 * Refused while learners have submissions; set it to draft instead.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$post = $this->get_assignment_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$submissions = $this->submission_total( $post->ID );
		if ( $submissions > 0 ) {
			return new WP_Error(
				'ohmylms_assignment_has_submissions',
				__( 'This assignment has learner submissions and cannot be deleted. Set it to draft instead.', 'ohmylms' ),
				array(
					'status'      => 409,
					'submissions' => $submissions,
				)
			);
		}
		$request->set_param( 'context', 'edit' );
		$previous = $this->prepare_item_for_response( $post, $request )->get_data();
		$force    = rest_sanitize_boolean( $request['force'] ) || 'trash' === $post->post_status;
		if ( $force ) {
			( new Assignment( $post->ID ) )->delete();
			if ( get_post( $post->ID ) ) {
				return new WP_Error( 'ohmylms_assignment_delete_failed', __( 'The assignment could not be deleted.', 'ohmylms' ), array( 'status' => 500 ) );
			}
		} elseif ( ! wp_trash_post( $post->ID ) ) {
			return new WP_Error( 'ohmylms_assignment_delete_failed', __( 'The assignment could not be moved to the trash.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		/** This action is documented in includes/Rest/V1/AssignmentController.php (unlinks chapters and progress). */
		do_action( 'ohmylms_rest_delete_assignment', $post->ID );

		return rest_ensure_response(
			array(
				'deleted'  => $force,
				'trashed'  => ! $force,
				'previous' => $previous,
			)
		);
	}

	/**
	 * Validate a create or update request against the merged stored values. Nothing is written here.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param array           $stored  Current settings (defaults when creating).
	 * @return array{fields:array<string,string>,settings:array<string,mixed>}|WP_Error
	 */
	private function validated_input( WP_REST_Request $request, array $stored ) {
		$fields = array();
		if ( $request->has_param( 'title' ) ) {
			$title = trim( sanitize_text_field( (string) $request['title'] ) );
			if ( '' === $title ) {
				return new WP_Error(
					'ohmylms_assignment_invalid_title',
					__( 'Enter a title for the assignment.', 'ohmylms' ),
					array(
						'status' => 422,
						'field'  => 'title',
					)
				);
			}
			$fields['post_title'] = $title;
		}
		if ( $request->has_param( 'instructions' ) ) {
			$fields['post_content'] = wp_kses_post( (string) $request['instructions'] );
		}
		if ( $request->has_param( 'status' ) ) {
			$fields['post_status'] = (string) $request['status'];
		}

		$settings = array();
		foreach ( array_keys( self::SETTING_KEYS ) as $field ) {
			if ( $request->has_param( $field ) && null !== $request[ $field ] ) {
				$settings[ $field ] = $request[ $field ];
			}
		}
		$merged = array_merge( $stored, $settings );
		if ( (int) $merged['pass_score'] > (int) $merged['max_score'] ) {
			return new WP_Error(
				'ohmylms_assignment_invalid_pass_score',
				__( 'The passing score cannot be higher than the maximum score.', 'ohmylms' ),
				array(
					'status' => 422,
					'field'  => 'pass_score',
				)
			);
		}

		return array(
			'fields'   => $fields,
			'settings' => $settings,
		);
	}

	/**
	 * Store settings in the legacy meta keys and formats (numbers as strings, flags as '1'/'0').
	 *
	 * @param int   $id       Assignment ID.
	 * @param array $settings Validated settings.
	 * @return void
	 */
	private static function write_settings( $id, array $settings ) {
		foreach ( $settings as $field => $value ) {
			if ( is_bool( $value ) ) {
				$value = $value ? '1' : '0';
			} elseif ( 'time_limit_unit' !== $field ) {
				$value = (string) (int) $value;
			}
			update_post_meta( $id, self::SETTING_KEYS[ $field ], $value );
		}
	}

	/**
	 * Settings as the learner front end reads them ({@see \OhMyLMS\Data\Assignment} getters).
	 *
	 * @param int $id Assignment ID.
	 * @return array<string,mixed>
	 */
	private function read_settings( $id ) {
		$meta = array();
		foreach ( self::SETTING_KEYS as $field => $key ) {
			$meta[ $field ] = get_post_meta( $id, $key, true );
		}
		$unit = strtolower( rtrim( (string) $meta['time_limit_unit'], 's' ) );

		return array(
			'max_score'               => max( 0, (int) $meta['max_score'] ),
			'pass_score'              => max( 0, (int) $meta['pass_score'] ),
			'time_limit_enabled'      => (bool) $meta['time_limit_enabled'],
			'time_limit'              => max( 0, (int) $meta['time_limit'] ),
			'time_limit_unit'         => in_array( $unit, self::TIME_UNITS, true ) ? $unit : 'week',
			// A missing value means "allowed" for learners (the getter defaults to true).
			'allow_file_uploads'      => metadata_exists( 'post', $id, self::SETTING_KEYS['allow_file_uploads'] ) ? (bool) $meta['allow_file_uploads'] : true,
			'max_files'               => max( 0, (int) $meta['max_files'] ),
			'file_size_limit_enabled' => (bool) $meta['file_size_limit_enabled'],
			'max_file_size_mb'        => max( 0, (int) $meta['max_file_size_mb'] ),
		);
	}

	/**
	 * Courses (and chapters) an assignment is placed in: chapter placements first, then learning programs.
	 *
	 * @param int $id Assignment ID.
	 * @return array[]
	 */
	private function placements( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT content.chapter_id, chapter.course_id FROM %i content INNER JOIN %i chapter ON chapter.chapter_id = content.chapter_id WHERE content.content_id = %d ORDER BY content.ID ASC LIMIT 50',
				$wpdb->prefix . 'ohmylms_content_relationship',
				$wpdb->prefix . 'ohmylms_chapter_relationship',
				$id
			)
		);
		$result = array();
		$seen   = array();
		foreach ( (array) $rows as $row ) {
			$course = get_post( (int) $row->course_id );
			if ( ! $course || OHMYLMS_COURSE_CPT !== $course->post_type ) {
				continue;
			}
			$chapter  = get_post( (int) $row->chapter_id );
			$result[] = array(
				'course_id'     => (int) $course->ID,
				'course_title'  => html_entity_decode( get_the_title( $course ), ENT_QUOTES, 'UTF-8' ),
				'chapter_id'    => $chapter ? (int) $chapter->ID : null,
				'chapter_title' => $chapter ? html_entity_decode( get_the_title( $chapter ), ENT_QUOTES, 'UTF-8' ) : null,
				'source'        => 'chapter',
			);
			$seen[ (int) $course->ID ] = true;
		}
		foreach ( Placements::courses_for( $id ) as $course_id ) {
			$course = get_post( (int) $course_id );
			if ( isset( $seen[ (int) $course_id ] ) || ! $course || OHMYLMS_COURSE_CPT !== $course->post_type ) {
				continue;
			}
			$result[] = array(
				'course_id'     => (int) $course->ID,
				'course_title'  => html_entity_decode( get_the_title( $course ), ENT_QUOTES, 'UTF-8' ),
				'chapter_id'    => null,
				'chapter_title' => null,
				'source'        => 'program',
			);
		}

		return $result;
	}

	/**
	 * Describe a course post.
	 *
	 * @param WP_Post $post Course post.
	 * @return array{id:int,title:string,status:string}
	 */
	private function course_ref( $post ) {
		return array(
			'id'     => (int) $post->ID,
			'title'  => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'status' => (string) $post->post_status,
		);
	}

	/**
	 * Course reference for an ID, or null when it is not a course.
	 *
	 * @param int $course_id Course ID.
	 * @return array{id:int,title:string}|null
	 */
	private function course_or_null( $course_id ) {
		$course = $course_id ? get_post( (int) $course_id ) : null;
		if ( ! $course || OHMYLMS_COURSE_CPT !== $course->post_type ) {
			return null;
		}

		return array(
			'id'    => (int) $course->ID,
			'title' => html_entity_decode( get_the_title( $course ), ENT_QUOTES, 'UTF-8' ),
		);
	}

	/**
	 * Prepare an assignment for the response.
	 *
	 * @param WP_Post         $item    Assignment post.
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		$post  = $item;
		$id    = (int) $post->ID;
		$stats = $this->stats ?? $this->page_stats( array( $id ) );
		$subs  = $stats['submissions'][ $id ] ?? array(
			'total'   => 0,
			'pending' => 0,
		);
		if ( 'trash' === $post->post_status ) {
			$status = 'trash';
		} else {
			$status = in_array( $post->post_status, array( 'publish', 'future', 'private' ), true ) ? 'publish' : 'draft';
		}
		$author  = get_userdata( (int) $post->post_author );
		$created = '0000-00-00 00:00:00' === $post->post_date_gmt ? get_gmt_from_date( $post->post_date ) : $post->post_date_gmt;

		$data = array_merge(
			array(
				'id'            => $id,
				'title'         => html_entity_decode( (string) $post->post_title, ENT_QUOTES, 'UTF-8' ),
				'slug'          => (string) $post->post_name,
				'status'        => $status,
				'permalink'     => (string) get_permalink( $post ),
				'author_id'     => (int) $post->post_author,
				'author_name'   => $author ? (string) $author->display_name : '',
				'course'        => $this->course_or_null( $stats['courses'][ $id ] ?? 0 ),
				'instructions'  => (string) $post->post_content,
				'placements'    => array(),
			),
			$this->read_settings( $id ),
			array(
				'submission_count' => (int) $subs['total'],
				'pending_count'    => (int) $subs['pending'],
				'created_at'       => $this->iso( $created ),
				'updated_at'       => $this->iso( $post->post_modified_gmt ),
				'can_edit'         => current_user_can( 'edit_post', $id ),
				'can_delete'       => current_user_can( 'delete_post', $id ),
			)
		);

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		if ( 'edit' === $context ) {
			$data['placements'] = $this->placements( $id );
		}
		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links(
			array(
				'self'        => array( 'href' => rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $id ) ) ),
				'collection'  => array( 'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ) ),
				'submissions' => array( 'href' => rest_url( sprintf( '%s/%s/%d/submissions', $this->namespace, $this->rest_base, $id ) ) ),
			)
		);

		/**
		 * Filters the assignment response of the REST API.
		 *
		 * @param WP_REST_Response $response Response.
		 * @param WP_Post          $post     Assignment post.
		 * @param WP_REST_Request  $request  Request.
		 */
		return apply_filters( 'ohmylms_rest_prepare_assignment', $response, $post, $request );
	}

	// Submissions.

	/**
	 * Learner submission table.
	 *
	 * @return string
	 */
	private function attempts_table() {
		global $wpdb;

		return $wpdb->prefix . 'ohmylms_assignment_attempts';
	}

	/**
	 * Number of submissions of an assignment.
	 *
	 * @param int $id Assignment ID.
	 * @return int
	 */
	private function submission_total( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE assignment_id = %d', $this->attempts_table(), $id ) );
	}

	/**
	 * Load one submission of an assignment, or null when it belongs to another assignment or does not exist.
	 *
	 * @param int $assignment_id Assignment ID.
	 * @param int $submission_id Submission ID.
	 * @return object|null
	 */
	private function submission_row( $assignment_id, $submission_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT a.*, u.display_name FROM %i a LEFT JOIN %i u ON u.ID = a.user_id WHERE a.id = %d AND a.assignment_id = %d',
				$this->attempts_table(),
				$wpdb->users,
				$submission_id,
				$assignment_id
			)
		);

		return $row ? $row : null;
	}

	/**
	 * The submission-not-found error.
	 *
	 * @return WP_Error
	 */
	private function submission_not_found() {
		return new WP_Error( 'ohmylms_assignment_submission_not_found', __( 'Submission not found.', 'ohmylms' ), array( 'status' => 404 ) );
	}

	/**
	 * List the submissions of an assignment, newest first. Student data is limited to ID and display name.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_submissions( $request ) {
		global $wpdb;

		$post = $this->get_assignment_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$page     = max( 1, (int) $request['page'] );
		$per_page = min( 100, max( 1, (int) $request['per_page'] ) );
		$where    = 'WHERE a.assignment_id = %d';
		$params   = array( $post->ID );
		if ( 'submitted' === $request['status'] ) {
			$where .= " AND a.status = 'submitted'";
		} elseif ( 'graded' === $request['status'] ) {
			$where .= " AND a.status <> 'submitted'";
		}
		if ( ! empty( $request['course'] ) ) {
			$where   .= ' AND a.course_id = %d';
			$params[] = (int) $request['course'];
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i a {$where}", array_merge( array( $this->attempts_table() ), $params ) ) );
		$pages = (int) ceil( $total / $per_page );
		if ( $page > $pages && $total > 0 ) {
			return $this->invalid_page();
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.id, a.user_id, a.course_id, a.assignment_id, a.status, a.score, a.files, a.start_date, a.end_date, u.display_name
				FROM %i a LEFT JOIN %i u ON u.ID = a.user_id {$where}
				ORDER BY a.id DESC LIMIT %d OFFSET %d",
				array_merge( array( $this->attempts_table(), $wpdb->users ), $params, array( $per_page, ( $page - 1 ) * $per_page ) )
			)
		);
		// phpcs:enable

		$settings = $this->read_settings( $post->ID );
		$data     = array();
		foreach ( (array) $rows as $row ) {
			$data[] = $this->prepare_submission( $row, $settings, false );
		}

		$response = rest_ensure_response( $data );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $pages );

		return $response;
	}

	/**
	 * Read one submission with its text, feedback and downloadable attachments.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_submission( $request ) {
		$post = $this->get_assignment_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$row = $this->submission_row( $post->ID, (int) $request['submission_id'] );
		if ( ! $row ) {
			return $this->submission_not_found();
		}

		return rest_ensure_response( $this->prepare_submission( $row, $this->read_settings( $post->ID ), true ) );
	}

	/**
	 * Grade a submission through the legacy grading pipeline, so the learner gets the same status
	 * (passed/failed), completion, hooks and "assignment graded" email as from the legacy screen.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function grade_submission( $request ) {
		$post = $this->get_assignment_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$row = $this->submission_row( $post->ID, (int) $request['submission_id'] );
		if ( ! $row ) {
			return $this->submission_not_found();
		}
		$settings = $this->read_settings( $post->ID );
		$score    = (int) $request['score'];
		if ( $score > $settings['max_score'] ) {
			return new WP_Error(
				'ohmylms_assignment_invalid_score',
				/* translators: %d: maximum score. */
				sprintf( __( 'The score must be a whole number from 0 to %d.', 'ohmylms' ), $settings['max_score'] ),
				array(
					'status'    => 422,
					'field'     => 'score',
					'max_score' => $settings['max_score'],
				)
			);
		}
		// The same lookup the legacy pipeline makes; it cannot complete without a course.
		$course_id = (int) ohmylms_get_course_by_content_id( $post->ID );
		if ( ! $course_id || ! ohmylms_get_course( $course_id ) ) {
			return new WP_Error( 'ohmylms_assignment_not_in_course', __( 'This assignment is not placed in a course, so it cannot be graded. Add it to a course first.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		// Completing the learner's progress loads the content through its chapter link type.
		if ( ! is_object( ohmylms_get_content_object( ohmylms_get_content_type_id_by_content_id( $post->ID ), $post->ID ) ) ) {
			return new WP_Error( 'ohmylms_assignment_placement_invalid', __( 'This assignment\'s course placement is incomplete. Remove it from the course and add it again in the course editor before grading.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		$feedback = $request->has_param( 'feedback' ) ? wp_kses_post( (string) $request['feedback'] ) : (string) $row->note;

		$legacy = new WP_REST_Request( 'POST' );
		$legacy->set_url_params(
			array(
				'id'      => (int) $post->ID,
				'user_id' => (int) $row->user_id,
			)
		);
		$legacy->set_header( 'Content-Type', 'application/json' );
		$legacy->set_body(
			wp_json_encode(
				array(
					array(
						'id'     => (int) $row->id,
						'score'  => $score,
						'status' => 'reviewed',
						'note'   => $feedback,
					),
				)
			)
		);
		$result = ( new AssignmentController() )->update_attempt_report( $legacy );
		$graded = $this->submission_row( $post->ID, (int) $row->id );
		if ( is_wp_error( $result ) || ! $graded || 'submitted' === $graded->status || (int) $graded->score !== $score ) {
			return new WP_Error( 'ohmylms_assignment_grade_failed', __( 'The grade could not be saved.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( $this->prepare_submission( $graded, $settings, true ) );
	}

	/**
	 * Stream the uploaded file of a submission. The response body is replaced by the file in
	 * {@see self::serve_download()}; requests dispatched internally get the file name and size.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function download_submission_file( $request ) {
		$post = $this->get_assignment_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$row = $this->submission_row( $post->ID, (int) $request['submission_id'] );
		if ( ! $row ) {
			return $this->submission_not_found();
		}
		$file = $this->upload_path( $row );
		if ( null === $file ) {
			return new WP_Error( 'ohmylms_assignment_file_not_found', __( 'This submission has no file that can be downloaded.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$name           = sanitize_file_name( wp_basename( $file ) );
		$size           = (int) filesize( $file );
		self::$download = $file;

		$response = rest_ensure_response(
			array(
				'name' => $name,
				'size' => $size,
			)
		);
		$response->header( 'Content-Type', 'application/octet-stream' );
		$response->header( 'Content-Disposition', 'attachment; filename="' . $name . '"' );
		$response->header( 'Content-Length', (string) $size );
		$response->header( 'X-Content-Type-Options', 'nosniff' );
		$response->header( 'Cache-Control', 'private, no-store' );

		return $response;
	}

	/**
	 * Send a verified upload instead of the JSON body of the file route.
	 *
	 * @param bool                  $served  Whether the request has already been served.
	 * @param WP_HTTP_Response|null $result  Result to send.
	 * @param WP_REST_Request|null  $request Served request.
	 * @return bool
	 */
	public static function serve_download( $served, $result, $request = null ) {
		$file           = self::$download;
		self::$download = null;
		if (
			$served || null === $file || ! ( $result instanceof WP_HTTP_Response ) || 200 !== $result->get_status()
			|| ! ( $request instanceof WP_REST_Request ) || ! preg_match( '#^/ohmylms/v1/assignments/\d+/submissions/\d+/file$#', $request->get_route() )
		) {
			return $served;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Stream a verified upload without loading it into memory.
		readfile( $file );

		return true;
	}

	/**
	 * Absolute path of a submission's upload when it is a regular file inside the uploads directory.
	 *
	 * @param object $row Submission row.
	 * @return string|null
	 */
	private function upload_path( $row ) {
		$files = $this->files_of( $row );
		if ( empty( $files['file'] ) || ! is_string( $files['file'] ) ) {
			return null;
		}
		$file    = realpath( $files['file'] );
		$uploads = wp_upload_dir( null, false );
		$root    = realpath( $uploads['basedir'] );
		if ( ! $file || ! $root || 0 !== strpos( wp_normalize_path( $file ), trailingslashit( wp_normalize_path( $root ) ) ) || ! is_file( $file ) ) {
			return null;
		}

		return $file;
	}

	/**
	 * Stored upload data (the `wp_handle_upload()` result) without instantiating objects.
	 *
	 * @param object $row Submission row.
	 * @return array
	 */
	private function files_of( $row ) {
		$value = isset( $row->files ) ? (string) $row->files : '';
		if ( ! is_serialized( $value ) ) {
			return array();
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Plugin-written data; objects are refused.
		$files = @unserialize( trim( $value ), array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		return is_array( $files ) ? $files : array();
	}

	/**
	 * Prepare a submission for the response.
	 *
	 * @param object $row      Submission row with `display_name`.
	 * @param array  $settings Assignment settings.
	 * @param bool   $detail   Include text, feedback and attachments.
	 * @return array
	 */
	private function prepare_submission( $row, array $settings, $detail ) {
		$graded = 'submitted' !== (string) $row->status;
		$file   = $this->upload_path( $row );
		$data   = array(
			'id'               => (int) $row->id,
			'assignment_id'    => (int) $row->assignment_id,
			'student'          => array(
				'id'           => (int) $row->user_id,
				'display_name' => null !== $row->display_name ? (string) $row->display_name : __( 'Deleted user', 'ohmylms' ),
			),
			'course'           => $this->course_or_null( (int) $row->course_id ),
			'status'           => $graded ? 'graded' : 'submitted',
			'result'           => in_array( (string) $row->status, array( 'passed', 'failed' ), true ) ? (string) $row->status : null,
			'score'            => $graded ? (int) $row->score : null,
			'max_score'        => $settings['max_score'],
			'pass_score'       => $settings['pass_score'],
			'submitted_at'     => $this->iso( get_gmt_from_date( (string) $row->start_date ) ),
			'graded_at'        => $graded ? $this->iso( (string) $row->end_date ) : null,
			'attachment_count' => null === $file ? 0 : 1,
		);
		if ( $detail ) {
			$data['content']     = (string) $row->content;
			$data['feedback']    = (string) $row->note;
			$data['attachments'] = array();
			if ( null !== $file ) {
				$type                  = wp_check_filetype( $file );
				$data['attachments'][] = array(
					'name' => sanitize_file_name( wp_basename( $file ) ),
					'type' => $type['type'] ? (string) $type['type'] : 'application/octet-stream',
					'size' => (int) filesize( $file ),
					'url'  => add_query_arg(
						'_wpnonce',
						wp_create_nonce( 'wp_rest' ),
						rest_url( sprintf( '%s/%s/%d/submissions/%d/file', $this->namespace, $this->rest_base, (int) $row->assignment_id, (int) $row->id ) )
					),
				);
			}
		}

		return $data;
	}

	/**
	 * Convert a GMT MySQL date to ISO 8601 (UTC).
	 *
	 * @param string $gmt Date in `Y-m-d H:i:s`.
	 * @return string
	 */
	private function iso( $gmt ) {
		$time = $gmt && '0000-00-00 00:00:00' !== $gmt ? strtotime( $gmt . ' UTC' ) : 0;

		return $time ? gmdate( 'Y-m-d\TH:i:s\Z', $time ) : '';
	}

	// Schema and arguments.

	/**
	 * Assignment schema (JSON Schema draft 4).
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}
		$both     = array( 'view', 'edit' );
		$readonly = static function ( $description, $type, $context = array( 'view', 'edit' ) ) {
			return array(
				'description' => $description,
				'type'        => $type,
				'context'     => $context,
				'readonly'    => true,
			);
		};
		$integer  = static function ( $description, $minimum, $maximum, $default ) use ( $both ) {
			return array(
				'description' => $description,
				'type'        => 'integer',
				'minimum'     => $minimum,
				'maximum'     => $maximum,
				'default'     => $default,
				'context'     => $both,
			);
		};
		$flag     = static function ( $description, $default ) use ( $both ) {
			return array(
				'description' => $description,
				'type'        => 'boolean',
				'default'     => $default,
				'context'     => $both,
			);
		};

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'ohmylms-assignment',
			'type'       => 'object',
			'properties' => array(
				'id'                      => $readonly( __( 'Unique identifier for the assignment.', 'ohmylms' ), 'integer' ),
				'title'                   => array(
					'description' => __( 'Assignment title (plain text).', 'ohmylms' ),
					'type'        => 'string',
					'minLength'   => 1,
					'maxLength'   => 200,
					'required'    => true,
					'context'     => $both,
				),
				'slug'                    => $readonly( __( 'Assignment slug.', 'ohmylms' ), 'string' ),
				'status'                  => array(
					'description' => __( 'Draft assignments are hidden from learners. `trash` is read-only: send a status to restore.', 'ohmylms' ),
					'type'        => 'string',
					'enum'        => self::STATUSES,
					'default'     => 'draft',
					'context'     => $both,
				),
				'permalink'               => $readonly( __( 'Learner URL.', 'ohmylms' ), 'string' ),
				'author_id'               => $readonly( __( 'Author user ID.', 'ohmylms' ), 'integer' ),
				'author_name'             => $readonly( __( 'Author display name.', 'ohmylms' ), 'string' ),
				'course'                  => $readonly( __( 'First course the assignment is placed in, or null.', 'ohmylms' ), array( 'object', 'null' ) ),
				'instructions'            => array(
					'description' => __( 'Instructions shown to learners (post-safe HTML).', 'ohmylms' ),
					'type'        => 'string',
					'maxLength'   => 100000,
					'default'     => '',
					'context'     => array( 'edit' ),
				),
				'placements'              => $readonly( __( 'Courses and chapters the assignment is placed in (read only).', 'ohmylms' ), 'array', array( 'edit' ) ),
				'max_score'               => $integer( __( 'Total points.', 'ohmylms' ), 1, self::MAX_SCORE, self::DEFAULTS['max_score'] ),
				'pass_score'              => $integer( __( 'Points needed to pass (at most max_score).', 'ohmylms' ), 0, self::MAX_SCORE, self::DEFAULTS['pass_score'] ),
				'time_limit_enabled'      => $flag( __( 'Give learners a deadline after they open the assignment.', 'ohmylms' ), self::DEFAULTS['time_limit_enabled'] ),
				'time_limit'              => $integer( __( 'Deadline length, in time_limit_unit.', 'ohmylms' ), 1, self::MAX_TIME_LIMIT, self::DEFAULTS['time_limit'] ),
				'time_limit_unit'         => array(
					'description' => __( 'Deadline unit.', 'ohmylms' ),
					'type'        => 'string',
					'enum'        => self::TIME_UNITS,
					'default'     => self::DEFAULTS['time_limit_unit'],
					'context'     => $both,
				),
				'allow_file_uploads'      => $flag( __( 'Learners submit by uploading files.', 'ohmylms' ), self::DEFAULTS['allow_file_uploads'] ),
				'max_files'               => $integer( __( 'Uploads allowed per learner.', 'ohmylms' ), 1, self::MAX_FILES, self::DEFAULTS['max_files'] ),
				'file_size_limit_enabled' => $flag( __( 'Limit the size of each upload.', 'ohmylms' ), self::DEFAULTS['file_size_limit_enabled'] ),
				'max_file_size_mb'        => $integer( __( 'Largest upload in megabytes.', 'ohmylms' ), 1, self::MAX_FILE_SIZE_MB, self::DEFAULTS['max_file_size_mb'] ),
				'submission_count'        => $readonly( __( 'Learner submissions.', 'ohmylms' ), 'integer' ),
				'pending_count'           => $readonly( __( 'Submissions waiting for a grade.', 'ohmylms' ), 'integer' ),
				'created_at'              => $readonly( __( 'Creation time (UTC).', 'ohmylms' ), 'string' ),
				'updated_at'              => $readonly( __( 'Last modification time (UTC); send it back as base_modified.', 'ohmylms' ), 'string' ),
				'can_edit'                => $readonly( __( 'Whether the current user may edit and grade it.', 'ohmylms' ), 'boolean' ),
				'can_delete'              => $readonly( __( 'Whether the current user may delete it.', 'ohmylms' ), 'boolean' ),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Arguments for the update route: every writable field, optional, without defaults (schema
	 * defaults would otherwise reset fields in a partial update), plus `base_modified`.
	 *
	 * @return array
	 */
	private function get_update_args() {
		$args = $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE );
		foreach ( $args as $name => $arg ) {
			unset( $args[ $name ]['default'], $args[ $name ]['required'] );
		}
		$args['base_modified'] = array(
			'description' => __( 'The updated_at value the edit started from; a newer stored copy answers 409.', 'ohmylms' ),
			'type'        => 'string',
			'maxLength'   => 40,
		);

		return $args;
	}

	/**
	 * Collection parameters of the list.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params = array_intersect_key( parent::get_collection_params(), array_flip( array( 'context', 'page', 'per_page', 'search' ) ) );

		$params['per_page']['default']   = 20;
		$params['per_page']['maximum']   = 100;
		$params['search']['description'] = __( 'Limit results to assignments whose title or instructions match the string.', 'ohmylms' );

		$params['status']  = array(
			'description' => __( 'Limit result set to assignments with a status. `all` excludes the trash.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'all',
			'enum'        => array( 'all', 'publish', 'draft', 'trash' ),
		);
		$params['course']  = array(
			'description' => __( 'Limit result set to assignments placed in a course.', 'ohmylms' ),
			'type'        => 'integer',
			'minimum'     => 1,
		);
		$params['orderby'] = array(
			'description' => __( 'Sort collection by attribute.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'created_at',
			'enum'        => array( 'id', 'title', 'status', 'created_at', 'updated_at' ),
		);
		$params['order']   = array(
			'description' => __( 'Order sort attribute ascending or descending.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'desc',
			'enum'        => array( 'asc', 'desc' ),
		);

		return $params;
	}

	/**
	 * Collection parameters of the course picker.
	 *
	 * @return array
	 */
	private function get_course_collection_params() {
		$params = array_intersect_key( parent::get_collection_params(), array_flip( array( 'page', 'per_page', 'search' ) ) );

		$params['per_page']['default'] = 20;
		$params['per_page']['maximum'] = 50;

		return $params;
	}

	/**
	 * Collection parameters of the submission list.
	 *
	 * @return array
	 */
	private function get_submission_collection_params() {
		$params = array_intersect_key( parent::get_collection_params(), array_flip( array( 'page', 'per_page' ) ) );

		$params['per_page']['default'] = 20;
		$params['per_page']['maximum'] = 100;
		$params['status']              = array(
			'description' => __( 'Limit result set to submissions waiting for a grade or already graded.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'all',
			'enum'        => array( 'all', 'submitted', 'graded' ),
		);
		$params['course']              = array(
			'description' => __( 'Limit result set to submissions made in a course.', 'ohmylms' ),
			'type'        => 'integer',
			'minimum'     => 1,
		);

		return $params;
	}
}
