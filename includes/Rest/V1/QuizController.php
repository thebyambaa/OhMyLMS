<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Controller for the quiz REST API.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Assessment\AttemptReport;
use OhMyLMS\Assessment\AssessmentSettings;
use OhMyLMS\Assessment\AttemptItems;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Data\Quiz;
use OhMyLMS\DataException;
use OhMyLMS\Learning\CourseStructure;
use OhMyLMS\QuestionBank\AccessPolicy;
use OhMyLMS\QuestionBank\DraftWriter;
use OhMyLMS\QuestionBank\Usage;
use OhMyLMS\Quiz\AttemptReports;
use OhMyLMS\Quiz\GradingPolicy;
use OhMyLMS\Quiz\QuizSettings;
use OhMyLMS\Quiz\Review;
use OhMyLMS\Utility\Transaction;
use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Quiz REST API.
 *
 * Two route families share this class (see docs/api/quizzes.md):
 *
 * - `ohmylms/v1/quizzes`: the typed API of the TypeScript admin. List, create, read, update the quiz
 *   and its settings, trash/delete, and review and grade attempts. Every route is authorised on the
 *   object: `edit_post` / `delete_post` for the quiz, and {@see GradingPolicy} for attempts.
 * - `ohmylms/v1/quiz`: the question-authoring routes (content, question removal, import/export, skills,
 *   raw create/update) that the question editor and internal callers still use. They keep their
 *   response shape.
 *
 * Authentication is WordPress core's: cookie requests need the `X-WP-Nonce` header, which core verifies
 * before any permission callback runs.
 *
 * @since 1.0.0
 */
class QuizController extends RestController {

	/**
	 * Route base of the question-authoring routes.
	 *
	 * @var string
	 */
	protected $base = 'quiz';

	/**
	 * Route base of the typed quiz API.
	 *
	 * @var string
	 */
	protected $rest_base = 'quizzes';

	/**
	 * Statuses shown in the "all" view.
	 */
	const LISTED_STATUSES = array( 'publish', 'draft', 'pending', 'future', 'private' );

	/**
	 * Attempt statuses that can be filtered.
	 */
	const ATTEMPT_STATUSES = array( 'in-progress', 'in-review', 'completed' );

	/**
	 * Largest number of grades in one request.
	 */
	const MAX_GRADES = 200;

	/**
	 * Largest title length.
	 */
	const MAX_TITLE = 200;

	/**
	 * Register the routes.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		$base = '/' . $this->rest_base;
		$item = $base . '/(?P<id>[\d]+)';
		$id   = array(
			'description' => __( 'Unique identifier for the quiz.', 'ohmylms' ),
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
					'args'                => array(
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 10,
							'minimum' => 1,
							'maximum' => 50,
						),
						'search'   => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
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
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
					'args'                => array(
						'force' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
		register_rest_route(
			$this->namespace,
			$item . '/attempts',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_attempts' ),
					'permission_callback' => array( $this, 'grading_permissions_check' ),
					'args'                => $this->get_attempts_collection_params(),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$item . '/attempts/(?P<attempt_id>[\d]+)',
			array(
				'args' => array(
					'id'         => $id,
					'attempt_id' => array(
						'description' => __( 'Unique identifier for the attempt.', 'ohmylms' ),
						'type'        => 'integer',
						'minimum'     => 1,
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_attempt' ),
					'permission_callback' => array( $this, 'grading_permissions_check' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$item . '/attempts/(?P<attempt_id>[\d]+)/grades',
			array(
				'args' => array(
					'id'         => $id,
					'attempt_id' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'grade_attempt' ),
					'permission_callback' => array( $this, 'grading_permissions_check' ),
					'args'                => $this->get_grade_args(),
				),
			)
		);

		$this->register_authoring_routes();
	}

	/**
	 * Register the question-authoring routes under `quiz`.
	 */
	private function register_authoring_routes() {
		$legacy = '/' . $this->base;
		$item   = $legacy . '/(?P<id>[\d]+)';
		$id     = array(
			'id' => array(
				'description' => __( 'Unique identifier for the quiz.', 'ohmylms' ),
				'type'        => 'integer',
				'minimum'     => 1,
			),
		);
		$write  = array(
			'name'          => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'description'   => array( 'type' => 'string' ),
			'status'        => array(
				'type' => 'string',
				'enum' => array( 'draft', 'publish' ),
			),
			'settings'      => array( 'type' => 'object' ),
			'drip_settings' => array( 'type' => array( 'object', 'array', 'string', 'null' ) ),
			'content'       => array( 'type' => 'array' ),
			'base_modified' => array( 'type' => 'string' ),
			'chapter_id'    => array(
				'type'    => 'integer',
				'minimum' => 1,
			),
			'quiz_order'    => array(
				'type'    => 'integer',
				'minimum' => 0,
			),
		);

		register_rest_route(
			$this->namespace,
			$legacy,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'legacy_create_item' ),
					'permission_callback' => array( $this, 'check_quiz_permission' ),
					'args'                => $write,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$legacy . '/import',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'import_file' ),
					'permission_callback' => array( $this, 'check_quiz_permission' ),
					'args'                => array(
						'name'       => array(
							'type'              => 'string',
							'maxLength'         => self::MAX_TITLE,
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'content'    => array(
							'type'      => 'string',
							'maxLength' => \OhMyLMS\Quiz\Exchange\Document::MAX_BYTES,
						),
						'csv'        => array(
							'type'      => 'string',
							'maxLength' => \OhMyLMS\Quiz\Exchange\Document::MAX_BYTES,
						),
						'review'     => array(
							'description' => __( 'Review token from the dry run; when sent, the import must be the checked file and name.', 'ohmylms' ),
							'type'        => 'string',
							'pattern'     => '^[a-f0-9]{64}$',
						),
						'format'     => array(
							'type'    => 'string',
							'enum'    => array( 'auto', 'ohmylms', 'csv' ),
							'default' => 'auto',
						),
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
			$item,
			array(
				'args' => $id,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'legacy_get_item' ),
					'permission_callback' => array( $this, 'check_item_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'legacy_update_item' ),
					'permission_callback' => array( $this, 'check_item_permission' ),
					'args'                => $write,
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$item . '/content',
			array(
				'args' => $id,
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
			$item . '/export',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'export_file' ),
					'permission_callback' => array( $this, 'check_item_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$item . '/skills',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'set_skills' ),
					'permission_callback' => array( $this, 'check_item_permission' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$item . '/questions/(?P<question_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'remove_question' ),
					'permission_callback' => array( $this, 'check_item_permission' ),
				),
			)
		);
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
		return new WP_Error( 'ohmylms_quiz_not_found', __( 'Quiz not found.', 'ohmylms' ), array( 'status' => 404 ) );
	}

	/**
	 * Load a quiz post or a 404 error.
	 *
	 * @param mixed $id Quiz ID.
	 * @return \WP_Post|WP_Error
	 */
	private function get_quiz_post( $id ) {
		$post = get_post( absint( $id ) );
		if ( ! $post || OHMYLMS_QUIZ_CPT !== $post->post_type || 'auto-draft' === $post->post_status ) {
			return $this->not_found();
		}

		return $post;
	}

	/**
	 * Whether the current user may use the quiz screens at all.
	 *
	 * @return bool
	 */
	private function can_author() {
		$type = get_post_type_object( OHMYLMS_QUIZ_CPT );

		return $type && current_user_can( $type->cap->edit_posts );
	}

	/**
	 * Permission for the list and the course picker.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return $this->can_author() ? true : $this->forbidden( 'ohmylms_quiz_cannot_view', __( 'Sorry, you are not allowed to view quizzes.', 'ohmylms' ) );
	}

	/**
	 * Permission to read one quiz: its editors and the people who grade it.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		$post = $this->get_quiz_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( current_user_can( 'edit_post', $post->ID ) || GradingPolicy::can_grade( $post->ID ) ) {
			return true;
		}

		return $this->forbidden( 'ohmylms_quiz_cannot_view', __( 'Sorry, you are not allowed to view this quiz.', 'ohmylms' ) );
	}

	/**
	 * Permission to create a quiz.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! $this->can_author() ) {
			return $this->forbidden( 'ohmylms_quiz_cannot_create', __( 'Sorry, you are not allowed to create quizzes.', 'ohmylms' ) );
		}

		return true;
	}

	/**
	 * Permission to update a quiz.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		$post = $this->get_quiz_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return current_user_can( 'edit_post', $post->ID ) ? true : $this->forbidden( 'ohmylms_quiz_cannot_edit', __( 'Sorry, you are not allowed to edit this quiz.', 'ohmylms' ) );
	}

	/**
	 * Permission to delete a quiz.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		$post = $this->get_quiz_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return current_user_can( 'delete_post', $post->ID ) ? true : $this->forbidden( 'ohmylms_quiz_cannot_delete', __( 'Sorry, you are not allowed to delete this quiz.', 'ohmylms' ) );
	}

	/**
	 * Permission to review and grade the attempts of a quiz.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function grading_permissions_check( $request ) {
		$post = $this->get_quiz_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return GradingPolicy::can_grade( $post->ID ) ? true : $this->forbidden( 'ohmylms_quiz_cannot_grade', __( 'Only an administrator, the quiz author or the course author can review and grade attempts.', 'ohmylms' ) );
	}

	/**
	 * Permission for the question-authoring routes: object-level edit or delete rights.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function check_item_permission( $request ) {
		$id = (int) $request['id'];
		if ( get_post_type( $id ) !== OHMYLMS_QUIZ_CPT ) {
			return new WP_Error( 'ohmylms_rest_invalid_quiz_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		return AccessPolicy::check( AccessPolicy::can_edit_quiz( $id ) );
	}

	/**
	 * Permission to create through the authoring routes.
	 *
	 * @return true|WP_Error
	 */
	public function check_quiz_permission() {
		return AccessPolicy::check( AccessPolicy::can_author() );
	}

	// Collection.

	/**
	 * Arguments of the quiz list.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_collection_params() {
		return array(
			'context'  => $this->get_context_param( array( 'default' => 'view' ) ),
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
			'search'   => array(
				'description'       => __( 'Limit results to quizzes matching a string.', 'ohmylms' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'status'   => array(
				'description' => __( 'Limit results to one status.', 'ohmylms' ),
				'type'        => 'string',
				'default'     => 'all',
				'enum'        => array( 'all', 'publish', 'draft', 'trash' ),
			),
			'course'   => array(
				'description' => __( 'Limit results to quizzes placed in a course.', 'ohmylms' ),
				'type'        => 'integer',
				'minimum'     => 1,
			),
			'include'  => array(
				'description' => __( 'Limit results to these quiz IDs.', 'ohmylms' ),
				'type'        => 'array',
				'items'       => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				'maxItems'    => 100,
			),
			'orderby'  => array(
				'description' => __( 'Sort the collection by a field.', 'ohmylms' ),
				'type'        => 'string',
				'default'     => 'created_at',
				'enum'        => array( 'id', 'title', 'created_at', 'updated_at' ),
			),
			'order'    => array(
				'description' => __( 'Sort direction.', 'ohmylms' ),
				'type'        => 'string',
				'default'     => 'desc',
				'enum'        => array( 'asc', 'desc' ),
			),
		);
	}

	/**
	 * Arguments of the attempt list.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_attempts_collection_params() {
		return array(
			'page'       => array(
				'type'    => 'integer',
				'default' => 1,
				'minimum' => 1,
			),
			'per_page'   => array(
				'type'    => 'integer',
				'default' => 20,
				'minimum' => 1,
				'maximum' => 100,
			),
			'search'     => array(
				'description'       => __( 'Match the student name or email.', 'ohmylms' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'status'     => array(
				'type'    => 'string',
				'default' => 'all',
				'enum'    => array_merge( array( 'all' ), self::ATTEMPT_STATUSES ),
			),
			'student_id' => array(
				'type'    => 'integer',
				'minimum' => 1,
			),
			'orderby'    => array(
				'type'    => 'string',
				'default' => 'started_at',
				'enum'    => array( 'started_at', 'score', 'student' ),
			),
			'order'      => array(
				'type'    => 'string',
				'default' => 'desc',
				'enum'    => array( 'asc', 'desc' ),
			),
		);
	}

	/**
	 * Arguments of the grading route.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_grade_args() {
		return array(
			'grades' => array(
				'description' => __( 'Marks to award. A client-supplied total is never accepted.', 'ohmylms' ),
				'type'        => 'array',
				'required'    => true,
				'minItems'    => 1,
				'maxItems'    => self::MAX_GRADES,
				'items'       => array(
					'type'                 => 'object',
					'required'             => array( 'question_id', 'marks' ),
					'additionalProperties' => false,
					'properties'           => array(
						'question_id' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'marks'       => array(
							'description'          => __( 'Marks for the whole question, or an object of marks by part ID for structured questions.', 'ohmylms' ),
							'type'                 => array( 'number', 'object' ),
							'minimum'              => 0,
							'maximum'              => 1000000,
							'additionalProperties' => array(
								'type'    => 'number',
								'minimum' => 0,
								'maximum' => 1000000,
							),
						),
					),
				),
			),
			'reason' => array(
				'description'       => __( 'Optional note kept with the grade history.', 'ohmylms' ),
				'type'              => 'string',
				'maxLength'         => 500,
				'sanitize_callback' => 'sanitize_textarea_field',
			),
		);
	}

	/**
	 * List quizzes the current user may edit or grade.
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
			'created_at' => 'date',
			'updated_at' => 'modified',
		);
		$status   = (string) $request['status'];
		$statuses = 'all' === $status ? self::LISTED_STATUSES : array( $status );

		$args = array(
			'post_type'              => OHMYLMS_QUIZ_CPT,
			'post_status'            => $statuses,
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

		// Restrict to the ids the user may see; null means no restriction.
		$allowed = $this->visible_quiz_ids();
		if ( ! empty( $request['course'] ) ) {
			$in_course = $this->quiz_ids_in_course( (int) $request['course'] );
			$allowed   = null === $allowed ? $in_course : array_values( array_intersect( $allowed, $in_course ) );
		}
		if ( ! empty( $request['include'] ) ) {
			$include = array_map( 'absint', (array) $request['include'] );
			$allowed = null === $allowed ? $include : array_values( array_intersect( $allowed, $include ) );
		}
		if ( null !== $allowed ) {
			$args['post__in'] = $allowed ? $allowed : array( 0 );
		}

		/**
		 * Filters the WP_Query arguments used by GET /quizzes.
		 *
		 * @param array           $args    Query arguments.
		 * @param WP_REST_Request $request The request.
		 */
		$args = apply_filters( 'ohmylms_rest_ohmylms_quiz_query', $args, $request );

		$query = new WP_Query( $args );
		$total = $this->found_posts( $query, $args );
		$pages = (int) ceil( $total / $per_page );
		if ( $page > $pages && $total > 0 ) {
			return new WP_Error( 'ohmylms_quiz_invalid_page', __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$ids   = wp_list_pluck( $query->posts, 'ID' );
		$stats = $this->page_stats( $ids );
		$data  = array();
		foreach ( $query->posts as $post ) {
			$data[] = $this->prepare_response_for_collection( $this->prepare_item_for_response( $post, $request, $stats ) );
		}

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
	 * Total matches of a query; a page past the end reports no rows, so it is counted again from page one.
	 *
	 * @param WP_Query $query Executed query.
	 * @param array    $args  Its arguments.
	 * @return int
	 */
	private function found_posts( WP_Query $query, array $args ) {
		if ( $query->posts || (int) $query->get( 'paged' ) <= 1 ) {
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
	 * Quiz IDs the current user may list, or null for every quiz.
	 *
	 * @return int[]|null
	 */
	private function visible_quiz_ids() {
		$type = get_post_type_object( OHMYLMS_QUIZ_CPT );
		if ( $type && current_user_can( $type->cap->edit_others_posts ) ) {
			return null;
		}

		return GradingPolicy::quiz_ids_for_author( get_current_user_id() );
	}

	/**
	 * Quiz IDs placed in a course through its chapters.
	 *
	 * @param int $course_id Course ID.
	 * @return int[]
	 */
	private function quiz_ids_in_course( $course_id ) {
		global $wpdb;

		$rows = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT DISTINCT content.content_id FROM %i content INNER JOIN %i chapter ON chapter.chapter_id = content.chapter_id WHERE chapter.course_id = %d LIMIT 5000',
				$wpdb->prefix . 'ohmylms_content_relationship',
				$wpdb->prefix . 'ohmylms_chapter_relationship',
				$course_id
			)
		);

		return array_map( 'intval', $rows );
	}

	/**
	 * Attempt, question and course figures for a page of quizzes, with one query each.
	 *
	 * @param int[] $ids Quiz IDs.
	 * @return array{attempts:array<int,array{total:int,pending:int}>,questions:array<int,int>,courses:array<int,int>}
	 */
	private function page_stats( array $ids ) {
		global $wpdb;

		$stats = array(
			'attempts'  => array(),
			'questions' => array(),
			'courses'   => array(),
		);
		$ids   = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( ! $ids ) {
			return $stats;
		}
		$holders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT quiz_id, COUNT(*) AS total, SUM(status = 'in-review') AS pending FROM %i WHERE quiz_id IN ({$holders}) GROUP BY quiz_id", array_merge( array( $wpdb->prefix . 'ohmylms_quiz_attempts' ), $ids ) ) );
		foreach ( (array) $rows as $row ) {
			$stats['attempts'][ (int) $row->quiz_id ] = array(
				'total'   => (int) $row->total,
				'pending' => (int) $row->pending,
			);
		}
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT quiz_id, COUNT(*) AS total FROM %i WHERE quiz_id IN ({$holders}) GROUP BY quiz_id", array_merge( array( $wpdb->prefix . 'ohmylms_quiz_questions_relationship' ), $ids ) ) );
		foreach ( (array) $rows as $row ) {
			$stats['questions'][ (int) $row->quiz_id ] = (int) $row->total;
		}
		// phpcs:enable
		$stats['courses'] = CourseStructure::first_courses( $ids );
		if ( $stats['courses'] ) {
			_prime_post_caches( array_values( array_unique( $stats['courses'] ) ), false, false );
		}

		return $stats;
	}

	/**
	 * Search the courses a quiz can be filtered by.
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
			return new WP_Error( 'ohmylms_quiz_invalid_page', __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$data = array();
		foreach ( $query->posts as $course ) {
			$data[] = array(
				'id'     => (int) $course->ID,
				'title'  => html_entity_decode( get_the_title( $course ), ENT_QUOTES, 'UTF-8' ),
				'status' => (string) $course->post_status,
			);
		}
		$response = rest_ensure_response( $data );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $pages );

		return $response;
	}

	// Item.

	/**
	 * Read one quiz.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$post = $this->get_quiz_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return $this->prepare_item_for_response( $post, $request );
	}

	/**
	 * Validate the writable fields of a request and return the values to store.
	 *
	 * @param WP_REST_Request     $request Request.
	 * @param array<string,mixed> $current Current settings (API shape).
	 * @return array<string,mixed>|WP_Error Keys: `fields` (post fields), `settings` (merged, or null if unchanged).
	 */
	private function validated_input( $request, array $current ) {
		$fields = array();
		if ( isset( $request['title'] ) ) {
			$title = trim( sanitize_text_field( (string) $request['title'] ) );
			if ( '' === $title || mb_strlen( $title ) > self::MAX_TITLE ) {
				return new WP_Error( 'ohmylms_quiz_invalid_title', __( 'Enter a title of 1 to 200 characters.', 'ohmylms' ), array( 'status' => 422 ) );
			}
			$fields['post_title'] = $title;
		}
		if ( isset( $request['description'] ) ) {
			$fields['post_content'] = wp_kses_post( (string) $request['description'] );
		}
		if ( isset( $request['status'] ) ) {
			$status = (string) $request['status'];
			if ( ! in_array( $status, array( 'draft', 'publish' ), true ) ) {
				return new WP_Error( 'ohmylms_quiz_invalid_status', __( 'The status must be draft or publish.', 'ohmylms' ), array( 'status' => 422 ) );
			}
			$type = get_post_type_object( OHMYLMS_QUIZ_CPT );
			if ( 'publish' === $status && $type && ! current_user_can( $type->cap->publish_posts ) ) {
				return $this->forbidden( 'ohmylms_quiz_cannot_publish', __( 'Sorry, you are not allowed to publish quizzes.', 'ohmylms' ) );
			}
			$fields['post_status'] = $status;
		}
		$settings = null;
		if ( isset( $request['settings'] ) ) {
			if ( ! is_array( $request['settings'] ) ) {
				return new WP_Error( 'ohmylms_quiz_invalid_settings', __( 'Settings must be an object.', 'ohmylms' ), array( 'status' => 422 ) );
			}
			$settings = QuizSettings::validate( $request['settings'], $current );
			if ( is_wp_error( $settings ) ) {
				return $settings;
			}
		}

		return array(
			'fields'   => $fields,
			'settings' => $settings,
		);
	}

	/**
	 * Create a quiz. It starts as a draft unless `status` says otherwise.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		if ( ! empty( $request['id'] ) ) {
			return new WP_Error( 'ohmylms_quiz_exists', __( 'Cannot create an existing quiz.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$input = $this->validated_input( $request, QuizSettings::defaults() );
		if ( is_wp_error( $input ) ) {
			return $input;
		}
		if ( empty( $input['fields']['post_title'] ) ) {
			return new WP_Error( 'ohmylms_quiz_invalid_title', __( 'Enter a title of 1 to 200 characters.', 'ohmylms' ), array( 'status' => 422 ) );
		}
		$settings = $input['settings'] ? $input['settings'] : QuizSettings::defaults();
		$post     = array_merge(
			array(
				'post_type'    => OHMYLMS_QUIZ_CPT,
				'post_status'  => 'draft',
				'post_content' => '',
				'post_author'  => get_current_user_id(),
			),
			$input['fields']
		);
		try {
			$quiz_id = Transaction::run(
				static function () use ( $post, $settings ) {
					$id = wp_insert_post( wp_slash( $post ), true );
					if ( is_wp_error( $id ) ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal message, caught in this method and never output.
						throw new \RuntimeException( $id->get_error_message() );
					}
					$saved = QuizSettings::save( $id, $settings );
					if ( is_wp_error( $saved ) ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal message, caught in this method and never output.
						throw new \RuntimeException( $saved->get_error_message() );
					}

					return (int) $id;
				}
			);
		} catch ( \Throwable $error ) {
			return new WP_Error( 'ohmylms_quiz_create_failed', __( 'The quiz could not be created.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		$created = get_post( $quiz_id );
		// This route never links a quiz to a chapter; a stray chapter_id must not reach the hook.
		unset( $request['chapter_id'] );
		/** This action is documented in includes/Rest/V1/QuizController.php (ohmylms_rest_insert_quiz). */
		do_action( 'ohmylms_rest_insert_quiz', $created, $request, true );

		$request->set_param( 'context', 'edit' );
		$response = $this->prepare_item_for_response( $created, $request );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $quiz_id ) ) );

		return $response;
	}

	/**
	 * Update a quiz and its settings. Omitted fields are unchanged; a trashed quiz is restored by sending a status.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$post = $this->get_quiz_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! empty( $request['base_modified'] ) ) {
			$base    = strtotime( (string) $request['base_modified'] );
			$current = strtotime( $post->post_modified_gmt . ' UTC' );
			if ( $base && $current && $current > $base ) {
				return new WP_Error(
					'ohmylms_quiz_conflict',
					__( 'This quiz was changed by someone else. Reload it before saving.', 'ohmylms' ),
					array(
						'status'   => 409,
						'modified' => $this->iso( $post->post_modified_gmt ),
					)
				);
			}
		}
		$input = $this->validated_input( $request, QuizSettings::read( $post->ID ) );
		if ( is_wp_error( $input ) ) {
			return $input;
		}
		$restore  = 'trash' === $post->post_status && isset( $input['fields']['post_status'] );
		$settings = $input['settings'];
		$fields   = $input['fields'];
		try {
			Transaction::run(
				static function () use ( $post, $fields, $settings, $restore ) {
					if ( $restore ) {
						wp_untrash_post( $post->ID );
					}
					if ( $fields ) {
						$updated = wp_update_post( wp_slash( array_merge( array( 'ID' => $post->ID ), $fields ) ), true );
						if ( is_wp_error( $updated ) ) {
							// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal message, caught in this method and never output.
							throw new \RuntimeException( $updated->get_error_message() );
						}
					}
					if ( $settings ) {
						$saved = QuizSettings::save( $post->ID, $settings );
						if ( is_wp_error( $saved ) ) {
							// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal message, caught in this method and never output.
							throw new \RuntimeException( $saved->get_error_message() );
						}
					}
				}
			);
		} catch ( \Throwable $error ) {
			return new WP_Error( 'ohmylms_quiz_update_failed', __( 'The quiz could not be saved. Nothing was changed.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		clean_post_cache( $post->ID );
		$updated = get_post( $post->ID );
		unset( $request['chapter_id'] );
		/** This action is documented in includes/Rest/V1/QuizController.php (ohmylms_rest_insert_quiz). */
		do_action( 'ohmylms_rest_insert_quiz', $updated, $request, false );
		do_action( 'ohmylms_after_updating_quiz', $updated->ID, ohmylms_get_quiz( $updated->ID ) );

		$request->set_param( 'context', 'edit' );

		return $this->prepare_item_for_response( $updated, $request );
	}

	/**
	 * Move a quiz to the trash, or delete it for good with `force` (refused while attempts exist).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$post = $this->get_quiz_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$request->set_param( 'context', 'edit' );
		$previous = $this->prepare_item_for_response( $post, $request )->get_data();
		$force    = rest_sanitize_boolean( $request['force'] ) || 'trash' === $post->post_status;
		if ( $force ) {
			$attempts = $this->attempt_total( $post->ID );
			if ( $attempts > 0 ) {
				return new WP_Error(
					'ohmylms_quiz_has_attempts',
					__( 'This quiz has student attempts and cannot be deleted permanently.', 'ohmylms' ),
					array(
						'status'   => 409,
						'attempts' => $attempts,
					)
				);
			}
			$quiz = ohmylms_get_quiz( $post->ID );
			if ( ! ( $quiz instanceof Quiz ) ) {
				return $this->not_found();
			}
			$quiz->delete();
			if ( get_post( $post->ID ) ) {
				return new WP_Error( 'ohmylms_quiz_delete_failed', __( 'The quiz could not be deleted.', 'ohmylms' ), array( 'status' => 500 ) );
			}
		} elseif ( ! wp_trash_post( $post->ID ) ) {
			return new WP_Error( 'ohmylms_quiz_delete_failed', __( 'The quiz could not be moved to the trash.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		/** This action is documented in includes/Rest/V1/QuizController.php (ohmylms_rest_delete_quiz). */
		do_action( 'ohmylms_rest_delete_quiz', $post->ID );

		return rest_ensure_response(
			array(
				'deleted'  => $force,
				'trashed'  => ! $force,
				'previous' => $previous,
			)
		);
	}

	/**
	 * Number of attempts of a quiz.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return int
	 */
	private function attempt_total( $quiz_id ) {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE quiz_id = %d', $wpdb->prefix . 'ohmylms_quiz_attempts', $quiz_id ) );
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

	/**
	 * Prepare a quiz for the response.
	 *
	 * @param \WP_Post                 $item    Quiz post.
	 * @param WP_REST_Request          $request Request.
	 * @param array<string,array>|null $stats   Figures prepared for the whole page; loaded for one quiz when omitted.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request, $stats = null ) {
		$post  = $item;
		$id    = (int) $post->ID;
		$stats = is_array( $stats ) ? $stats : $this->page_stats( array( $id ) );

		$course_id = isset( $stats['courses'][ $id ] ) ? (int) $stats['courses'][ $id ] : 0;
		$course    = $course_id ? get_post( $course_id ) : null;
		$attempts  = isset( $stats['attempts'][ $id ] ) ? $stats['attempts'][ $id ] : array(
			'total'   => 0,
			'pending' => 0,
		);
		$author    = get_userdata( (int) $post->post_author );
		$status    = in_array( $post->post_status, array( 'publish', 'draft', 'trash' ), true ) ? $post->post_status : ( 'future' === $post->post_status ? 'publish' : 'draft' );

		$data = array(
			'id'                   => $id,
			'title'                => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'slug'                 => (string) $post->post_name,
			'status'               => $status,
			'permalink'            => (string) get_permalink( $post ),
			'author_id'            => (int) $post->post_author,
			'author_name'          => $author ? (string) $author->display_name : '',
			'course'               => $course ? array(
				'id'    => (int) $course->ID,
				'title' => html_entity_decode( get_the_title( $course ), ENT_QUOTES, 'UTF-8' ),
			) : null,
			'question_count'       => isset( $stats['questions'][ $id ] ) ? (int) $stats['questions'][ $id ] : 0,
			'attempt_count'        => (int) $attempts['total'],
			'pending_review_count' => (int) $attempts['pending'],
			'created_at'           => $this->iso( $post->post_date_gmt ),
			'updated_at'           => $this->iso( $post->post_modified_gmt ),
			'can_edit'             => current_user_can( 'edit_post', $id ),
			'can_delete'           => current_user_can( 'delete_post', $id ),
			'can_grade'            => GradingPolicy::can_grade( $id ),
		);

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		if ( 'edit' === $context ) {
			$data['description'] = (string) $post->post_content;
			$data['settings']    = QuizSettings::read( $id );
			$data['total_marks'] = (float) ohmylms_get_quiz( $id )->get_total_marks();
		}
		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links(
			array(
				'self'       => array( 'href' => rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $id ) ) ),
				'collection' => array( 'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ) ),
				'attempts'   => array( 'href' => rest_url( sprintf( '%s/%s/%d/attempts', $this->namespace, $this->rest_base, $id ) ) ),
			)
		);

		/**
		 * Filters the quiz response of the REST API.
		 *
		 * @param WP_REST_Response $response Response.
		 * @param \WP_Post         $post     Quiz post.
		 * @param WP_REST_Request  $request  Request.
		 */
		return apply_filters( 'ohmylms_rest_prepare_quiz', $response, $post, $request );
	}

	/**
	 * Quiz schema.
	 *
	 * @return array<string,mixed>
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}
		$both  = array( 'view', 'edit' );
		$date  = static function ( $description ) {
			return array(
				'description' => $description,
				'type'        => 'string',
				'format'      => 'date-time',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			);
		};
		$flag  = static function ( $description ) {
			return array(
				'description' => $description,
				'type'        => 'boolean',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			);
		};
		$count = static function ( $description ) {
			return array(
				'description' => $description,
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			);
		};

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'ohmylms-quiz',
			'type'       => 'object',
			'properties' => array(
				'id'                   => array(
					'description' => __( 'Unique identifier for the quiz.', 'ohmylms' ),
					'type'        => 'integer',
					'context'     => $both,
					'readonly'    => true,
				),
				'title'                => array(
					'description' => __( 'Quiz title.', 'ohmylms' ),
					'type'        => 'string',
					'minLength'   => 1,
					'required'    => true,
					'maxLength'   => self::MAX_TITLE,
					'context'     => $both,
				),
				'slug'                 => array(
					'type'     => 'string',
					'context'  => $both,
					'readonly' => true,
				),
				'status'               => array(
					'description' => __( 'Publication status.', 'ohmylms' ),
					'type'        => 'string',
					'enum'        => array( 'draft', 'publish', 'trash' ),
					'context'     => $both,
				),
				'permalink'            => array(
					'type'     => 'string',
					'context'  => $both,
					'readonly' => true,
				),
				'author_id'            => $count( __( 'Author user ID.', 'ohmylms' ) ),
				'author_name'          => array(
					'type'     => 'string',
					'context'  => $both,
					'readonly' => true,
				),
				'course'               => array(
					'description' => __( 'Course the quiz is placed in, if any.', 'ohmylms' ),
					'type'        => array( 'object', 'null' ),
					'context'     => $both,
					'readonly'    => true,
				),
				'question_count'       => $count( __( 'Number of questions.', 'ohmylms' ) ),
				'attempt_count'        => $count( __( 'Number of attempts.', 'ohmylms' ) ),
				'pending_review_count' => $count( __( 'Attempts waiting for manual grading.', 'ohmylms' ) ),
				'created_at'           => $date( __( 'Creation time (UTC).', 'ohmylms' ) ),
				'updated_at'           => $date( __( 'Last modification time (UTC).', 'ohmylms' ) ),
				'can_edit'             => $flag( __( 'Whether the current user may edit the quiz.', 'ohmylms' ) ),
				'can_delete'           => $flag( __( 'Whether the current user may delete the quiz.', 'ohmylms' ) ),
				'can_grade'            => $flag( __( 'Whether the current user may review and grade attempts.', 'ohmylms' ) ),
				'native_editor_url'    => array(
					'type'     => 'string',
					'context'  => $both,
					'readonly' => true,
				),
				'description'          => array(
					'description' => __( 'Quiz introduction (limited HTML).', 'ohmylms' ),
					'type'        => 'string',
					'context'     => array( 'edit' ),
				),
				'total_marks'          => array(
					'type'     => 'number',
					'context'  => array( 'edit' ),
					'readonly' => true,
				),
				'settings'             => array(
					'description'          => __( 'Quiz settings. Omitted keys are unchanged.', 'ohmylms' ),
					'type'                 => 'object',
					'context'              => array( 'edit' ),
					'additionalProperties' => false,
					'properties'           => array(
						'time_limit_minutes'   => array(
							'description' => __( 'Time limit in minutes; 0 means no limit.', 'ohmylms' ),
							'type'        => 'integer',
							'minimum'     => 0,
							'maximum'     => QuizSettings::MAX_TIME_LIMIT,
						),
						'passing_score'        => array(
							'description' => __( 'Marks needed to pass, 0 to 100; 0 means no pass mark.', 'ohmylms' ),
							'type'        => 'number',
							'minimum'     => 0,
							'maximum'     => 100,
						),
						'attempts_allowed'     => array(
							'description' => __( 'Attempts per student; 0 uses the default of one.', 'ohmylms' ),
							'type'        => 'integer',
							'minimum'     => 0,
							'maximum'     => QuizSettings::MAX_ATTEMPTS,
						),
						'randomize_questions'  => array( 'type' => 'boolean' ),
						'show_correct_answers' => array( 'type' => 'boolean' ),
						'layout'               => array(
							'type' => 'string',
							'enum' => QuizSettings::LAYOUTS,
						),
						'questions_per_page'   => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => QuizSettings::MAX_PER_PAGE,
						),
						'feedback_mode'        => array(
							'type' => 'string',
							'enum' => AssessmentSettings::FEEDBACK,
						),
						'available_from'       => array(
							'type'   => array( 'string', 'null' ),
							'format' => 'date-time',
						),
						'available_until'      => array(
							'type'   => array( 'string', 'null' ),
							'format' => 'date-time',
						),
					),
				),
				'base_modified'        => array(
					'description' => __( 'The updated_at value the editor loaded; a newer saved version answers 409.', 'ohmylms' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'edit' ),
					'arg_options' => array( 'sanitize_callback' => 'sanitize_text_field' ),
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}

	// Attempts.

	/**
	 * List attempts of a quiz.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_attempts( $request ) {
		global $wpdb;

		$quiz_id  = (int) $request['id'];
		$page     = max( 1, (int) $request['page'] );
		$per_page = min( 100, max( 1, (int) $request['per_page'] ) );
		$order    = 'asc' === $request['order'] ? 'ASC' : 'DESC';
		$columns  = array(
			'started_at' => 'a.start_date',
			'score'      => 'a.total',
			'student'    => 'u.display_name',
		);
		$sort     = $columns[ $request['orderby'] ] . ' ' . $order . ', a.id ' . $order;

		$where = array( 'a.quiz_id = %d' );
		$args  = array( $quiz_id );
		if ( 'all' !== $request['status'] ) {
			$where[] = 'a.status = %s';
			$args[]  = (string) $request['status'];
		}
		if ( ! empty( $request['student_id'] ) ) {
			$where[] = 'a.student_id = %d';
			$args[]  = (int) $request['student_id'];
		}
		if ( '' !== (string) $request['search'] ) {
			$like    = '%' . $wpdb->esc_like( (string) $request['search'] ) . '%';
			$where[] = '( u.display_name LIKE %s OR u.user_email LIKE %s )';
			$args[]  = $like;
			$args[]  = $like;
		}
		$tables = array( $wpdb->prefix . 'ohmylms_quiz_attempts', $wpdb->users );
		$clause = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i a LEFT JOIN %i u ON u.ID = a.student_id WHERE {$clause}", array_merge( $tables, $args ) ) );
		$pages = (int) ceil( $total / $per_page );
		if ( $page > $pages && $total > 0 ) {
			return new WP_Error( 'ohmylms_attempt_invalid_page', __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT a.*, u.display_name, u.user_email FROM %i a LEFT JOIN %i u ON u.ID = a.student_id WHERE {$clause} ORDER BY {$sort} LIMIT %d OFFSET %d", array_merge( $tables, $args, array( $per_page, ( $page - 1 ) * $per_page ) ) ), ARRAY_A );
		// phpcs:enable

		$rows  = is_array( $rows ) ? $rows : array();
		$maxes = $this->attempt_max_marks( wp_list_pluck( $rows, 'id' ) );
		$data  = array();
		foreach ( $rows as $row ) {
			$data[] = $this->prepare_attempt( $row, isset( $maxes[ (int) $row['id'] ] ) ? $maxes[ (int) $row['id'] ] : 0.0 );
		}
		$response = rest_ensure_response( $data );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $pages );
		$base = add_query_arg( urlencode_deep( $request->get_query_params() ), rest_url( sprintf( '%s/%s/%d/attempts', $this->namespace, $this->rest_base, $quiz_id ) ) );
		if ( $page > 1 ) {
			$response->link_header( 'prev', add_query_arg( 'page', $page - 1, $base ) );
		}
		if ( $page < $pages ) {
			$response->link_header( 'next', add_query_arg( 'page', $page + 1, $base ) );
		}

		return $response;
	}

	/**
	 * Maximum marks of each attempt (the sum of its frozen question marks), in one query.
	 *
	 * @param array $ids Attempt IDs.
	 * @return array<int,float>
	 */
	private function attempt_max_marks( array $ids ) {
		return AttemptReports::max_marks( array_map( 'absint', $ids ) );
	}

	/**
	 * Shape one attempt row for the list.
	 *
	 * @param array<string,mixed> $row       Joined attempt row.
	 * @param float               $max_marks Maximum marks.
	 * @return array<string,mixed>
	 */
	private function prepare_attempt( array $row, $max_marks ) {
		$score    = round( (float) $row['total'], 4 );
		$started  = $this->local_to_iso( (string) $row['start_date'] );
		$finished = $this->local_to_iso( (string) $row['end_date'] );

		return array(
			'id'            => (int) $row['id'],
			'quiz_id'       => (int) $row['quiz_id'],
			'course_id'     => (int) $row['course_id'],
			'student'       => array(
				'id'    => (int) $row['student_id'],
				'name'  => (string) $row['display_name'],
				'email' => (string) $row['user_email'],
			),
			'status'        => (string) $row['status'],
			'score'         => $score,
			'max_marks'     => round( $max_marks, 4 ),
			'percentage'    => $max_marks > 0 ? round( $score / $max_marks * 100, 1 ) : null,
			'started_at'    => $started,
			'submitted_at'  => 'in-progress' === $row['status'] ? null : $finished,
			'needs_grading' => 'in-review' === $row['status'],
		);
	}

	/**
	 * Convert a site-local MySQL date to ISO 8601 UTC, or null when unset.
	 *
	 * @param string $local Date in `Y-m-d H:i:s` (site time zone).
	 * @return string|null
	 */
	private function local_to_iso( $local ) {
		if ( '' === $local || str_starts_with( $local, '0000' ) ) {
			return null;
		}
		$gmt = get_gmt_from_date( $local );

		return $this->iso( $gmt ) ? $this->iso( $gmt ) : null;
	}

	/**
	 * Load an attempt that belongs to the quiz.
	 *
	 * @param int $quiz_id    Quiz ID.
	 * @param int $attempt_id Attempt ID.
	 * @return array<string,mixed>|WP_Error Joined row.
	 */
	private function find_attempt( $quiz_id, $attempt_id ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT a.*, u.display_name, u.user_email FROM %i a LEFT JOIN %i u ON u.ID = a.student_id WHERE a.id = %d AND a.quiz_id = %d', $wpdb->prefix . 'ohmylms_quiz_attempts', $wpdb->users, $attempt_id, $quiz_id ), ARRAY_A );

		return $row ? $row : new WP_Error( 'ohmylms_attempt_not_found', __( 'Attempt not found for this quiz.', 'ohmylms' ), array( 'status' => 404 ) );
	}

	/**
	 * Read one attempt with its questions, answers, marks and grade history.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_attempt( $request ) {
		$row = $this->find_attempt( (int) $request['id'], (int) $request['attempt_id'] );
		if ( is_wp_error( $row ) ) {
			return $row;
		}

		return rest_ensure_response( $this->attempt_detail( $row ) );
	}

	/**
	 * Build the detail of an attempt.
	 *
	 * @param array<string,mixed> $row Joined attempt row.
	 * @return array<string,mixed>
	 */
	private function attempt_detail( array $row ) {
		$quiz_id    = (int) $row['quiz_id'];
		$attempt_id = (int) $row['id'];
		$quiz       = ohmylms_get_quiz( $quiz_id );
		$report     = $quiz->get_attempt_report( $attempt_id );
		$basis      = AttemptReport::basis( $attempt_id, $quiz );
		$versioned  = ! empty( $basis['versioned'] );

		$questions = array();
		foreach ( (array) ( $report['questions'] ?? array() ) as $index => $question ) {
			$settings = isset( $question['settings'] ) && is_array( $question['settings'] ) ? $question['settings'] : array();
			$max      = isset( $settings['score']['value'] ) ? (float) $settings['score']['value'] : 0.0;
			$parts    = array();
			if ( ! empty( $question['parts'] ) && is_array( $question['parts'] ) ) {
				foreach ( $question['parts'] as $part_id => $part ) {
					$parts[] = array(
						'id'      => (string) $part_id,
						'kind'    => (string) ( $part['kind'] ?? '' ),
						'max'     => (float) ( $part['max'] ?? 0 ),
						'awarded' => isset( $part['awarded'] ) ? (float) $part['awarded'] : null,
						'grader'  => isset( $part['grader'] ) ? (string) $part['grader'] : null,
					);
				}
			}
			$history = array();
			foreach ( array_slice( (array) ( $question['grade_history'] ?? array() ), -10 ) as $event ) {
				$reviewer  = ! empty( $event['reviewer_id'] ) ? get_userdata( (int) $event['reviewer_id'] ) : false;
				$history[] = array(
					'awarded'       => (float) $event['awarded'],
					'grader'        => (string) $event['grader'],
					'reviewer_id'   => (int) $event['reviewer_id'],
					'reviewer_name' => $reviewer ? (string) $reviewer->display_name : '',
					'reason'        => (string) $event['reason'],
					'created_at'    => $this->iso( (string) $event['created_at'] ),
				);
			}
			$questions[] = array(
				'question_id' => (int) $question['id'],
				'position'    => $index + 1,
				'title'       => $this->plain( (string) ( $question['name'] ?? '' ), 1000 ),
				'type'        => (string) ( $settings['type'] ?? '' ),
				'max_marks'   => round( $max, 4 ),
				'awarded'     => round( (float) ( $question['achive_mark'] ?? 0 ), 4 ),
				'status'      => 'in-review' === ( $question['status'] ?? '' ) ? 'in-review' : 'graded',
				'correct'     => array_key_exists( 'correct', $question ) && null !== $question['correct'] ? (bool) $question['correct'] : null,
				'answer'      => $this->answer_lines( $question['given_answer'] ?? null, (array) ( $question['questions'] ?? array() ) ),
				'parts'       => $parts ? $parts : null,
				'history'     => $history,
			);
		}

		return array_merge(
			$this->prepare_attempt( $row, (float) $basis['max'] ),
			array(
				'passing_mark' => (float) $basis['passing'],
				'passed'       => 'completed' === $row['status'] ? (float) $row['total'] >= (float) $basis['passing'] : null,
				'versioned'    => $versioned,
				'questions'    => $questions,
			)
		);
	}

	/**
	 * Plain, bounded text.
	 *
	 * @param string $html  HTML or text.
	 * @param int    $limit Maximum characters.
	 * @return string
	 */
	private function plain( $html, $limit ) {
		$text = trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) );

		return mb_substr( $text, 0, $limit );
	}

	/**
	 * Readable lines for a stored answer: option ids are replaced by their text.
	 *
	 * @param mixed $given   Stored answer.
	 * @param array $options Options of the question.
	 * @return string[]
	 */
	private function answer_lines( $given, array $options ) {
		if ( null === $given || '' === $given || array() === $given ) {
			return array();
		}
		$names = array();
		foreach ( $options as $option ) {
			if ( isset( $option['id'] ) ) {
				$names[ (string) $option['id'] ] = $this->plain( (string) ( $option['answer'] ?? '' ), 500 );
			}
		}
		$lines = array();
		foreach ( array_slice( (array) $given, 0, 100, true ) as $value ) {
			if ( is_array( $value ) ) {
				$line = wp_json_encode( $value );
			} elseif ( isset( $names[ (string) $value ] ) && '' !== $names[ (string) $value ] ) {
				$line = $names[ (string) $value ];
			} else {
				$line = (string) $value;
			}
			$lines[] = mb_substr( $this->plain( (string) $line, 2000 ), 0, 2000 );
		}

		return $lines;
	}

	/**
	 * Award marks. Marks are checked against each question's frozen maximum; the total is recomputed on the
	 * server; a repeated request with the same marks changes nothing and writes no new history.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function grade_attempt( $request ) {
		global $wpdb;

		$params = (array) $request->get_json_params();
		foreach ( array_keys( $params ) as $key ) {
			if ( ! in_array( $key, array( 'grades', 'reason' ), true ) ) {
				return new WP_Error( 'ohmylms_grade_unexpected_field', __( 'Only grades and a reason can be sent. Totals are calculated by the server.', 'ohmylms' ), array( 'status' => 400 ) );
			}
		}
		$quiz_id    = (int) $request['id'];
		$attempt_id = (int) $request['attempt_id'];
		$row        = $this->find_attempt( $quiz_id, $attempt_id );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		if ( 'in-progress' === $row['status'] ) {
			return new WP_Error( 'ohmylms_attempt_not_submitted', __( 'The student has not submitted this attempt yet.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		if ( ! Schema::ready() || ! AttemptItems::is_versioned( $attempt_id ) ) {
			return new WP_Error( 'ohmylms_attempt_not_versioned', __( 'Attempts taken before versioned grading cannot be graded here.', 'ohmylms' ), array( 'status' => 409 ) );
		}

		$max = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT question_id, question_marks FROM %i WHERE quiz_attempt_id = %d', $wpdb->prefix . 'ohmylms_quiz_attempts_answers', $attempt_id ), ARRAY_A ) as $answer ) {
			$max[ (int) $answer['question_id'] ] = (float) $answer['question_marks'];
		}
		$marks = array();
		foreach ( (array) $request['grades'] as $grade ) {
			$question_id = (int) $grade['question_id'];
			if ( isset( $marks[ $question_id ] ) ) {
				return new WP_Error(
					'ohmylms_grade_duplicate_question',
					__( 'A question can only be graded once per request.', 'ohmylms' ),
					array(
						'status'      => 422,
						'question_id' => $question_id,
					)
				);
			}
			if ( ! isset( $max[ $question_id ] ) ) {
				return new WP_Error(
					'ohmylms_grade_unknown_question',
					__( 'A graded question is not part of this attempt.', 'ohmylms' ),
					array(
						'status'      => 422,
						'question_id' => $question_id,
					)
				);
			}
			$value = $grade['marks'];
			if ( is_array( $value ) ) {
				$value = array_map( 'floatval', $value );
				$sum   = array_sum( $value );
			} else {
				$value = (float) $value;
				$sum   = $value;
			}
			if ( ! is_finite( (float) $sum ) || $sum < 0 || $sum > $max[ $question_id ] + 0.00005 ) {
				return new WP_Error(
					'ohmylms_grade_exceeds_max',
					__( 'Marks must be between 0 and the maximum for the question.', 'ohmylms' ),
					array(
						'status'      => 422,
						'question_id' => $question_id,
						'max'         => $max[ $question_id ],
					)
				);
			}
			$marks[ $question_id ] = $value;
		}

		$events_before = $this->grade_event_count( $attempt_id );
		$report        = Review::save( $quiz_id, $attempt_id, $marks, (string) $request['reason'] );
		if ( is_wp_error( $report ) ) {
			$status = (int) ( $report->get_error_data()['status'] ?? 500 );
			return new WP_Error( $report->get_error_code(), $report->get_error_message(), array( 'status' => $status ) );
		}
		$fresh = $this->find_attempt( $quiz_id, $attempt_id );
		if ( is_wp_error( $fresh ) ) {
			return $fresh;
		}
		$detail            = $this->attempt_detail( $fresh );
		$detail['changed'] = max( 0, $this->grade_event_count( $attempt_id ) - $events_before );

		return rest_ensure_response( $detail );
	}

	/**
	 * Number of grade events written for an attempt.
	 *
	 * @param int $attempt_id Attempt ID.
	 * @return int
	 */
	private function grade_event_count( $attempt_id ) {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE source_type = %s AND source_id = %d', Schema::table( 'grade_events' ), 'quiz', $attempt_id ) );
	}

	// Question-authoring routes (`ohmylms/v1/quiz`).

	/**
	 * Check the chapter a new quiz is linked to: it must be a chapter the user can edit.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	private function check_chapter( $request ) {
		if ( empty( $request['chapter_id'] ) ) {
			return true;
		}
		$chapter_id = absint( $request['chapter_id'] );
		if ( get_post_type( $chapter_id ) !== OHMYLMS_CHAPTER_CPT ) {
			return new WP_Error( 'ohmylms_quiz_invalid_chapter', __( 'The chapter does not exist.', 'ohmylms' ), array( 'status' => 422 ) );
		}

		return current_user_can( 'edit_post', $chapter_id ) ? true : $this->forbidden( 'ohmylms_quiz_chapter_forbidden', __( 'You cannot add a quiz to this chapter.', 'ohmylms' ) );
	}

	/**
	 * Remove one question from this quiz. The question stays in the bank.
	 *
	 * @param WP_REST_Request $request Request.
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
		$name    = (string) $request['name'];
		$format  = empty( $request['format'] ) ? 'auto' : (string) $request['format'];
		$dry_run = rest_sanitize_boolean( $request['dry_run'] );
		$review  = hash_hmac( 'sha256', wp_json_encode( array( get_current_user_id(), $content, $name, $format ) ), wp_salt( 'nonce' ) );
		if ( ! $dry_run && is_string( $request['review'] ) && ! hash_equals( $review, $request['review'] ) ) {
			return new WP_Error( 'ohmylms_quiz_import_review_stale', __( 'This is not the file that was checked. Check it again before importing.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		$result = \OhMyLMS\Quiz\Exchange\Importer::run(
			$content,
			array(
				'name'       => $name,
				'format'     => $format,
				'dry_run'    => $dry_run,
				'copy_media' => null === $request['copy_media'] ? true : rest_sanitize_boolean( $request['copy_media'] ),
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( $result['dry_run'] ) {
			$result['review'] = $review;
		}
		$response = rest_ensure_response( $result + array( 'status' => $result['dry_run'] ? 'ok' : 'success' ) );
		$response->set_status( $result['dry_run'] ? 200 : 201 );
		$response->header( 'Cache-Control', 'private, no-store' );

		return $response;
	}

	/**
	 * Download a quiz and all of its questions as an OhMyLMS quiz file.
	 *
	 * @param WP_REST_Request $request Request with the quiz `id`.
	 * @return WP_Error|WP_REST_Response
	 */
	public function export_file( $request ) {
		$result = \OhMyLMS\Quiz\Exchange\Exporter::export( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		// The file holds every answer key.
		$response = rest_ensure_response( $result );
		$response->header( 'Cache-Control', 'private, no-store' );

		return $response;
	}

	/**
	 * Raw create used by the question editor and internal callers (exam import, activity drafts).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function legacy_create_item( $request ) {
		$chapter = $this->check_chapter( $request );
		if ( is_wp_error( $chapter ) ) {
			return $chapter;
		}
		try {
			$quiz_id = $this->save_quiz( $request );
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
			 * Fires after a quiz is inserted or updated through the REST API.
			 *
			 * @param \WP_Post        $post     The quiz post.
			 * @param WP_REST_Request $request  The request.
			 * @param bool            $creating Whether the quiz is being created (true) or updated (false).
			 */
			do_action( 'ohmylms_rest_insert_quiz', $post, $request, true );

			$response = rest_ensure_response( $this->legacy_response( $post, $request ) );
			$response->set_status( 201 );

			return $response;
		} catch ( DataException $e ) {
			return new WP_Error( 'ohmylms_quiz_invalid', $e->getMessage(), array( 'status' => 400 ) );
		}
	}

	/**
	 * Raw read with content, used by the question editor.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function legacy_get_item( $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || OHMYLMS_QUIZ_CPT !== $post->post_type ) {
			return new WP_Error( 'ohmylms_rest_invalid_quiz_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$data            = $this->legacy_response( $post, $request );
		$data['content'] = ohmylms_get_quiz( $post->ID )->get_questions();

		return rest_ensure_response( $data );
	}

	/**
	 * Raw update with nested questions, used by the question editor and internal callers.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function legacy_update_item( $request ) {
		$post_id = (int) $request['id'];
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
					if ( $content ) {
						$saved = DraftWriter::save_many( $content, $post_id );
						if ( is_wp_error( $saved ) ) {
							// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal message, caught in this method and never output.
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

		$data              = $this->legacy_response( $post, $request );
		$data['content']   = ohmylms_get_quiz( $post->ID )->get_questions();
		$data['saved_ids'] = $saved_ids;
		do_action( 'ohmylms_rest_quiz_meta_updated', ohmylms_get_quiz( $post->ID ) );

		return rest_ensure_response( $data );
	}

	/**
	 * Save a quiz through the data store.
	 *
	 * @param WP_REST_Request|array $request Request or array of fields.
	 * @return int
	 */
	public function save_quiz( $request ) {
		$quiz = $this->prepare_item_for_database( $request );

		return $quiz->save();
	}

	/**
	 * Legacy response shape.
	 *
	 * @param \WP_Post        $post    Quiz post.
	 * @param WP_REST_Request $request Request.
	 * @return array<string,mixed>
	 */
	private function legacy_response( $post, $request ) {
		$quiz     = ohmylms_get_quiz( $post->ID );
		$stats    = $this->page_stats( array( $post->ID ) );
		$course   = isset( $stats['courses'][ $post->ID ] ) ? (int) $stats['courses'][ $post->ID ] : 0;
		$data     = array(
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
			'courses'               => $course ? array(
				'id'          => $course,
				'course_name' => get_the_title( $course ),
			) : array(),
			'number_of_submissions' => isset( $stats['attempts'][ $post->ID ] ) ? $stats['attempts'][ $post->ID ]['total'] : 0,
			'date_created'          => $quiz->get_date_created(),
			'modified'              => get_post_field( 'post_modified_gmt', $quiz->get_id() ),
		);
		$response = apply_filters( 'ohmylms_rest_prepare_quiz', rest_ensure_response( $data ), $post, $request );

		return $response instanceof WP_REST_Response ? $response->get_data() : $data;
	}

	/**
	 * Prepare a quiz object from request fields for the data store.
	 *
	 * @param WP_REST_Request|array $request Request or array of fields.
	 * @return Quiz
	 */
	protected function prepare_item_for_database( $request ) {
		$id   = isset( $request['id'] ) ? absint( $request['id'] ) : 0;
		$quiz = $id ? ohmylms_get_quiz( $id ) : new Quiz();

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
		if ( isset( $request['questions'] ) ) {
			$quiz->set_questions( $request['questions'] );
		}
		if ( isset( $request['settings'] ) && is_array( $request['settings'] ) ) {
			$quiz->set_settings( $request['settings'] );
		}
		if ( isset( $request['drip_settings'] ) ) {
			$quiz->set_drip_settings( $request['drip_settings'] );
		}

		return $quiz;
	}

	/**
	 * Get the questions of a quiz.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_questions( $request ) {
		$quiz = ohmylms_get_quiz( (int) $request['id'] );
		if ( ! ( $quiz instanceof Quiz ) ) {
			return $this->not_found();
		}

		return rest_ensure_response(
			array(
				'status'  => 'success',
				'message' => __( 'Quiz fetched successfully', 'ohmylms' ),
				'data'    => $quiz->get_questions(),
			)
		);
	}

	/**
	 * Replace the questions of a quiz, optionally with quiz fields.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_questions( $request ) {
		$quiz_id = (int) $request['id'];
		$quiz    = ohmylms_get_quiz( $quiz_id );
		if ( ! ( $quiz instanceof Quiz ) ) {
			return $this->not_found();
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
						// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal message, caught in this method and never output.
						throw new DataException( $saved->get_error_code(), $saved->get_error_message(), (int) ( $saved->get_error_data()['status'] ?? 400 ), (array) $saved->get_error_data() );
					}
				}
			);
		} catch ( DataException $e ) {
			return new WP_Error( $e->getErrorCode(), $e->getMessage(), $e->getErrorData() );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'ohmylms_quiz_storage', __( 'The quiz could not be saved. Nothing was changed.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'status'  => 'success',
				'message' => __( 'Contents updated successfully', 'ohmylms' ),
			)
		);
	}
}
