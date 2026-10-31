<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * QuestionsController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Assessment\InlineBlanks;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Extensions\Registry;
use OhMyLMS\Extensions\Settings as ExtensionSettings;
use OhMyLMS\QuestionBank\AccessPolicy;
use OhMyLMS\QuestionBank\Approval;
use OhMyLMS\QuestionBank\BankAttributes;
use OhMyLMS\QuestionBank\Banks;
use OhMyLMS\QuestionBank\DraftWriter;
use OhMyLMS\QuestionBank\Duplicator;
use OhMyLMS\QuestionBank\Search;
use OhMyLMS\QuestionBank\SkillMap;
use OhMyLMS\QuestionBank\Usage;
use OhMyLMS\QuestionBank\VersionPublisher;
use OhMyLMS\Skills\Taxonomy;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * QuestionsController class.
 *
 * Typed REST API for the admin app's question bank under `ohmylms/v1/questions`. See
 * docs/api/questions.md for the contract. Writes go through {@see DraftWriter}, which validates,
 * authorises and versions every save in one transaction. The legacy `/question` and
 * `/question-bank` routes are separate and keep their own shapes.
 *
 * Authentication is WordPress core's: cookie requests need the `X-WP-Nonce` header, which core
 * verifies before any permission callback runs.
 *
 * @since 1.0.0
 */
class QuestionsController extends RestController {

	/**
	 * Largest accepted request body, in bytes.
	 */
	const MAX_BODY_BYTES = 262144;

	/**
	 * Largest number of answer options per question.
	 */
	const MAX_OPTIONS = 100;

	/**
	 * Types whose answers are option rows with structural rules.
	 */
	const OPTION_TYPES = array( 'single-choice', 'multiple-choice', 'true-false', 'reorder', 'matching' );

	/**
	 * On/off settings shared by every type.
	 */
	const FLAG_SETTINGS = array( 'required', 'randomize', 'partial_credit', 'case_sensitive' );

	/**
	 * Text settings shared by every type.
	 */
	const TEXT_SETTINGS = array( 'hint', 'explanation' );

	/**
	 * Longest hint or explanation, in bytes.
	 */
	const MAX_TEXT_SETTING = 65535;

	/**
	 * Most points a question can be worth.
	 */
	const MAX_POINTS = 10000;

	/**
	 * Body fields accepted by create and update.
	 */
	const WRITABLE = array( 'name', 'description', 'settings', 'options', 'skills', 'bank', 'thumbnail_id', 'video_id', 'extension_settings' );

	/**
	 * Post meta holding extension settings.
	 */
	const EXTENSION_META = '_ohmylms_extension_settings';

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * The base route for question endpoints.
	 *
	 * @var string
	 */
	protected $rest_base = 'questions';

	/**
	 * Register the routes for the question endpoints.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		add_filter( 'rest_pre_dispatch', array( $this, 'limit_body_size' ), 10, 3 );

		$base = '/' . $this->rest_base;
		$item = $base . '/(?P<id>[\d]+)';
		$id   = array(
			'description' => __( 'Unique identifier for the question.', 'ohmylms' ),
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
					'args'                => $this->get_write_args( true ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/types',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_types' ),
					'permission_callback' => array( $this, 'get_types_permissions_check' ),
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
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_write_args( false ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		foreach ( array( 'archive', 'restore' ) as $action ) {
			register_rest_route(
				$this->namespace,
				$item . '/' . $action,
				array(
					'args' => array( 'id' => $id ),
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $this, $action . '_item' ),
						'permission_callback' => array( $this, 'update_item_permissions_check' ),
					),
				)
			);
		}

		register_rest_route(
			$this->namespace,
			$item . '/approve',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'approve_item' ),
					'permission_callback' => array( $this, 'approve_item_permissions_check' ),
					'args'                => array(
						'version_id' => array(
							'description' => __( 'Version to approve; the current version when omitted.', 'ohmylms' ),
							'type'        => 'integer',
							'minimum'     => 1,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$item . '/duplicate',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'duplicate_item' ),
					'permission_callback' => array( $this, 'duplicate_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$item . '/versions',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_versions' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => $this->get_paging_params(),
				),
			)
		);
	}

	/**
	 * Refuse oversized bodies on these routes before they are validated or dispatched.
	 *
	 * @param mixed           $result  Response replacing the dispatch, or null.
	 * @param WP_REST_Server  $server  Server.
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function limit_body_size( $result, $server, $request ) {
		if ( null !== $result || ! preg_match( '#^/' . preg_quote( $this->namespace . '/' . $this->rest_base, '#' ) . '(/|$)#', (string) $request->get_route() ) ) {
			return $result;
		}
		if ( strlen( (string) $request->get_body() ) > self::MAX_BODY_BYTES ) {
			return new WP_Error(
				'ohmylms_question_too_large',
				__( 'The request is too large.', 'ohmylms' ),
				array(
					'status'    => 413,
					'max_bytes' => self::MAX_BODY_BYTES,
				)
			);
		}

		return $result;
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
	 * The question bank tables must exist.
	 *
	 * @return true|WP_Error
	 */
	private function bank_ready() {
		if ( ! Schema::ready() ) {
			return new WP_Error( 'ohmylms_question_bank_unavailable', __( 'The question bank is not installed yet.', 'ohmylms' ), array( 'status' => 503 ) );
		}

		return true;
	}

	/**
	 * Build the not-found error.
	 *
	 * @return WP_Error
	 */
	private function not_found() {
		return new WP_Error( 'ohmylms_question_not_found', __( 'Question not found.', 'ohmylms' ), array( 'status' => 404 ) );
	}

	/**
	 * Load a question post or a 404 error.
	 *
	 * @param mixed $id Question ID.
	 * @return WP_Post|WP_Error
	 */
	private function get_question_post( $id ) {
		$post = get_post( absint( $id ) );
		if ( ! $post || OHMYLMS_QUESTION_CPT !== $post->post_type || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
			return $this->not_found();
		}

		return $post;
	}

	/**
	 * Permission for the list: authors, limited to banks they can see.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		$ready = $this->bank_ready();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		if ( ! AccessPolicy::can_author() ) {
			return $this->forbidden( 'ohmylms_question_cannot_view', __( 'Sorry, you are not allowed to view questions.', 'ohmylms' ) );
		}
		$bank = (int) $request['bank'];
		if ( $bank > 0 && ! current_user_can( 'edit_others_posts' ) && ! in_array( $bank, array_map( 'intval', array_column( Banks::for_user(), 'id' ) ), true ) ) {
			return $this->forbidden( 'ohmylms_question_bank_forbidden', __( 'Sorry, you are not allowed to view that question bank.', 'ohmylms' ) );
		}

		return true;
	}

	/**
	 * Permission to create a question.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		$ready = $this->bank_ready();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}

		return AccessPolicy::can_author() ? true : $this->forbidden( 'ohmylms_question_cannot_create', __( 'Sorry, you are not allowed to create questions.', 'ohmylms' ) );
	}

	/**
	 * Permission for the list of question types.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_types_permissions_check( $request ) {
		return AccessPolicy::can_author() ? true : $this->forbidden( 'ohmylms_question_cannot_view', __( 'Sorry, you are not allowed to view questions.', 'ohmylms' ) );
	}

	/**
	 * Permission to read one question (editor view and versions): its editors only.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return $this->check_editor( $request, 'ohmylms_question_cannot_view', __( 'Sorry, you are not allowed to view this question.', 'ohmylms' ) );
	}

	/**
	 * Permission to update, archive or restore one question.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		return $this->check_editor( $request, 'ohmylms_question_cannot_edit', __( 'Sorry, you are not allowed to edit this question.', 'ohmylms' ) );
	}

	/**
	 * Permission to approve a version of one question.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function approve_item_permissions_check( $request ) {
		return $this->check_question(
			$request,
			array( AccessPolicy::class, 'can_approve_question' ),
			'ohmylms_question_cannot_approve',
			__( 'Sorry, you are not allowed to approve versions of this question.', 'ohmylms' )
		);
	}

	/**
	 * Permission to duplicate one question: authors who may use it.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function duplicate_item_permissions_check( $request ) {
		return $this->check_question(
			$request,
			static function ( $question_id ) {
				return AccessPolicy::can_author() && AccessPolicy::can_use_question( $question_id );
			},
			'ohmylms_question_cannot_duplicate',
			__( 'Sorry, you are not allowed to duplicate this question.', 'ohmylms' )
		);
	}

	/**
	 * 404 for missing questions, then the object-level edit check.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $code    Error code when denied.
	 * @param string          $message Message when denied.
	 * @return true|WP_Error
	 */
	private function check_editor( $request, $code, $message ) {
		return $this->check_question( $request, array( AccessPolicy::class, 'can_edit_question' ), $code, $message );
	}

	/**
	 * 404 for missing questions, then an object-level check.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param callable        $allowed Receives the question ID; true when allowed.
	 * @param string          $code    Error code when denied.
	 * @param string          $message Message when denied.
	 * @return true|WP_Error
	 */
	private function check_question( $request, $allowed, $code, $message ) {
		$ready = $this->bank_ready();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$post = $this->get_question_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return call_user_func( $allowed, $post->ID ) ? true : $this->forbidden( $code, $message );
	}

	// Reads.

	/**
	 * List visible questions (summaries without settings or answer options).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$page     = max( 1, (int) $request['page'] );
		$per_page = min( 100, max( 1, (int) $request['per_page'] ) );
		$filters  = array(
			'status'     => 'active' === $request['status'] ? '' : (string) $request['status'],
			'type'       => (string) $request['type'],
			'difficulty' => (string) $request['difficulty'],
			'search'     => (string) $request['search'],
			'bank'       => null === $request['bank'] ? null : (int) $request['bank'],
			'skill'      => (int) $request['skill'],
		);

		/**
		 * Filters the search filters used by GET /questions. Visibility rules are applied after this
		 * filter and cannot be widened by it.
		 *
		 * @param array           $filters Filters understood by OhMyLMS\QuestionBank\Search::query().
		 * @param WP_REST_Request $request The request.
		 */
		$filters = apply_filters( 'ohmylms_rest_questions_query', $filters, $request );

		$result = Search::query( (array) $filters, $page, $per_page, (string) $request['orderby'], (string) $request['order'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$total = $result['total'];
		$pages = (int) ceil( $total / $per_page );
		if ( $page > $pages && $total > 0 ) {
			return new WP_Error( 'ohmylms_question_invalid_page', __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$response = rest_ensure_response( $this->summaries( $result['ids'], $request ) );
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
	 * Get the editor view of one question. Never writes (no identity row is created on read).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$post = $this->get_question_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return $this->prepare_item_for_response( $post, $request );
	}

	/**
	 * Version history of one question, newest first (paged; no content or hashes).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_versions( $request ) {
		$post = $this->get_question_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$page     = max( 1, (int) $request['page'] );
		$per_page = min( 100, max( 1, (int) $request['per_page'] ) );
		$identity = VersionPublisher::identity( $post->ID, false );
		$identity = $identity ? $identity : array();
		$history  = (array) VersionPublisher::history( $post->ID );
		$total    = count( $history );
		$pages    = (int) ceil( $total / $per_page );
		if ( $page > $pages && $total > 0 ) {
			return new WP_Error( 'ohmylms_question_invalid_page', __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$rows = array_slice( $history, ( $page - 1 ) * $per_page, $per_page );
		cache_users( array_unique( array_map( 'intval', array_column( $rows, 'created_by' ) ) ) );
		$current  = (int) ( $identity['current_version_id'] ?? 0 );
		$approved = (int) ( $identity['approved_version_id'] ?? 0 );
		$data     = array();
		foreach ( $rows as $row ) {
			$version_id = (int) $row['id'];
			$data[]     = array(
				'id'          => $version_id,
				'version_no'  => (int) $row['version_no'],
				'created_at'  => $this->iso( $row['created_at'] ),
				'created_by'  => $this->user_ref( (int) $row['created_by'] ),
				'is_current'  => $version_id === $current,
				'is_approved' => $version_id === $approved,
			);
		}

		$response = rest_ensure_response( $data );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $pages );
		$response->header( 'Cache-Control', 'private, no-store' );

		return $response;
	}

	/**
	 * Registered question types (built-in and add-on), sorted by label.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_types( $request ) {
		$types = array();
		foreach ( Registry::all( 'question' ) as $id => $definition ) {
			$answers = $definition['answers'] ?? null;
			$types[] = array(
				'id'       => (string) $id,
				'label'    => wp_strip_all_tags( (string) $definition['label'] ),
				'format'   => isset( $definition['editor']['format'] ) ? (string) $definition['editor']['format'] : null,
				'answers'  => in_array( $answers, array( 'options', 'text' ), true ) ? $answers : null,
				'manual'   => ! empty( $definition['manual'] ),
				'snapshot' => ! empty( $definition['snapshot'] ),
				'builtin'  => Registry::is_core( 'question', (string) $id ),
			);
		}
		usort(
			$types,
			static function ( $a, $b ) {
				return strnatcasecmp( $a['label'], $b['label'] );
			}
		);

		$response = rest_ensure_response( $types );
		$response->header( 'Cache-Control', 'private, no-store' );

		return $response;
	}

	// Writes.

	/**
	 * Create a question.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$known = $this->reject_unknown_fields( $request, self::WRITABLE );
		if ( is_wp_error( $known ) ) {
			return $known;
		}
		$prepared = $this->prepare_payload( $request, null );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}
		$saved = $this->save( $prepared['payload'], $prepared['extension'] );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$post = get_post( (int) $saved['id'] );

		/**
		 * Fires after a question is created or updated through `ohmylms/v1/questions`.
		 *
		 * @param WP_Post         $post     The question post.
		 * @param WP_REST_Request $request  The request.
		 * @param bool            $creating True when the question was created.
		 */
		do_action( 'ohmylms_rest_save_question_item', $post, $request, true );

		$response = $this->prepare_item_for_response( $post, $request );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $post->ID ) ) );

		return $response;
	}

	/**
	 * Partially update a question. PUT, PATCH and POST behave the same; omitted fields keep their
	 * values and `options`, when sent, replace the option set.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$post = $this->get_question_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$known = $this->reject_unknown_fields( $request, array_merge( self::WRITABLE, array( 'base_modified' ) ) );
		if ( is_wp_error( $known ) ) {
			return $known;
		}

		$base    = rest_parse_date( (string) $request['base_modified'], true );
		$current = strtotime( $post->post_modified_gmt . ' UTC' );
		if ( ! $base ) {
			return new WP_Error( 'rest_invalid_param', __( 'base_modified must be an RFC 3339 date.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		if ( $current && $current > $base ) {
			return $this->conflict( $post );
		}

		$prepared = $this->prepare_payload( $request, $post );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}
		if ( $prepared['payload'] || null !== $prepared['extension'] ) {
			$payload = array_merge(
				array(
					'id'            => $post->ID,
					'base_modified' => gmdate( 'Y-m-d H:i:s', $base ),
				),
				$prepared['payload']
			);
			$saved   = $this->save( $payload, $prepared['extension'] );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}

		clean_post_cache( $post->ID );
		$post = get_post( $post->ID );

		/** This action is documented in includes/Rest/V1/QuestionsController.php (ohmylms_rest_save_question_item). */
		do_action( 'ohmylms_rest_save_question_item', $post, $request, false );

		return $this->prepare_item_for_response( $post, $request );
	}

	/**
	 * Archive a question: hidden from the default list and from new use, history kept.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function archive_item( $request ) {
		return $this->change_lifecycle( $request, array( Usage::class, 'archive' ) );
	}

	/**
	 * Restore an archived question.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function restore_item( $request ) {
		return $this->change_lifecycle( $request, array( Usage::class, 'restore' ) );
	}

	/**
	 * Approve a version of a question for shared use and practice (default: the current version,
	 * captured first when the content changed since the last version).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function approve_item( $request ) {
		$known = $this->reject_unknown_fields( $request, array( 'version_id' ) );
		if ( is_wp_error( $known ) ) {
			return $known;
		}
		$post = $this->get_question_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$approved = Approval::approve( $post->ID, $request['version_id'] );
		if ( is_wp_error( $approved ) ) {
			return $approved;
		}
		clean_post_cache( $post->ID );

		return $this->prepare_item_for_response( get_post( $post->ID ), $request );
	}

	/**
	 * Duplicate a question as a new question owned by the current user (new UUID, same family).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function duplicate_item( $request ) {
		$known = $this->reject_unknown_fields( $request, array() );
		if ( is_wp_error( $known ) ) {
			return $known;
		}
		$post = $this->get_question_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$saved = Duplicator::duplicate( $post->ID );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		$copy = get_post( (int) $saved['id'] );

		/** This action is documented in includes/Rest/V1/QuestionsController.php (ohmylms_rest_save_question_item). */
		do_action( 'ohmylms_rest_save_question_item', $copy, $request, true );

		$response = $this->prepare_item_for_response( $copy, $request );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $copy->ID ) ) );

		return $response;
	}

	/**
	 * Run an archive/restore operation and return the editor view.
	 *
	 * @param WP_REST_Request $request   Request.
	 * @param callable        $operation Usage operation taking the question ID.
	 * @return WP_REST_Response|WP_Error
	 */
	private function change_lifecycle( $request, $operation ) {
		$known = $this->reject_unknown_fields( $request, array() );
		if ( is_wp_error( $known ) ) {
			return $known;
		}
		$post = $this->get_question_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$result = call_user_func( $operation, $post->ID );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		clean_post_cache( $post->ID );

		return $this->prepare_item_for_response( get_post( $post->ID ), $request );
	}

	/**
	 * Save through DraftWriter. Extension settings are written from inside its transaction, after
	 * the question post is stored and before the version is captured, so they are versioned with
	 * the rest of the content and rolled back with it.
	 *
	 * @param array      $payload   DraftWriter payload.
	 * @param array|null $extension Complete extension settings to store, or null to keep them.
	 * @return array|WP_Error DraftWriter result.
	 */
	private function save( array $payload, $extension ) {
		$write = null;
		if ( null !== $extension ) {
			$write = static function ( $question ) use ( $extension ) {
				update_post_meta( $question->get_id(), self::EXTENSION_META, wp_slash( $extension ) );
			};
			add_action( 'ohmylms_after_question_object_save', $write );
		}
		try {
			return DraftWriter::save( $payload );
		} finally {
			if ( $write ) {
				remove_action( 'ohmylms_after_question_object_save', $write );
			}
		}
	}

	/**
	 * Build the stale-save error.
	 *
	 * @param WP_Post $post Current question post.
	 * @return WP_Error
	 */
	private function conflict( WP_Post $post ) {
		return new WP_Error(
			'ohmylms_question_conflict',
			__( 'This question was changed by someone else. Reload it before saving.', 'ohmylms' ),
			array(
				'status'   => 409,
				'modified' => $this->iso( $post->post_modified_gmt ),
			)
		);
	}

	/**
	 * Refuse body fields the route does not accept.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string[]        $allowed Accepted field names.
	 * @return true|WP_Error
	 */
	private function reject_unknown_fields( WP_REST_Request $request, array $allowed ) {
		$json    = $request->get_json_params();
		$body    = array_merge( (array) $request->get_body_params(), is_array( $json ) ? $json : array() );
		$unknown = array_values( array_diff( array_map( 'strval', array_keys( $body ) ), $allowed ) );
		if ( $unknown ) {
			return new WP_Error(
				'ohmylms_question_unknown_field',
				__( 'Unknown field(s) in the request.', 'ohmylms' ),
				array(
					'status' => 400,
					'fields' => $unknown,
				)
			);
		}

		return true;
	}

	/**
	 * Validate the request on top of the schema and build the DraftWriter payload from the fields
	 * that were sent. Type rules are checked against the merged result (stored values for the
	 * fields that were not sent).
	 *
	 * @param WP_REST_Request $request Request.
	 * @param WP_Post|null    $post    Question being updated, or null when creating.
	 * @return array|WP_Error {payload: array, extension: array|null}
	 */
	private function prepare_payload( WP_REST_Request $request, $post ) {
		$payload = array();

		if ( $request->has_param( 'name' ) ) {
			$name = trim( (string) $request['name'] );
			if ( '' === $name ) {
				return $this->invalid( 'ohmylms_question_invalid_name', __( 'Enter a question name.', 'ohmylms' ), 'name' );
			}
			$payload['name'] = $name;
		}
		if ( $request->has_param( 'description' ) ) {
			$payload['description'] = (string) $request['description'];
		}

		$settings = null;
		if ( $request->has_param( 'settings' ) ) {
			$settings = (array) $request['settings'];
			$type     = $settings['type'] ?? '';
			if ( ! is_string( $type ) || '' === $type || ! Registry::get( 'question', $type ) ) {
				return new WP_Error(
					'ohmylms_question_invalid_type',
					__( 'Choose a registered question type.', 'ohmylms' ),
					array(
						'status' => 400,
						'field'  => 'settings.type',
					)
				);
			}
			$valid = $this->validate_shared_settings( $settings );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
			$settings            = $this->clean_settings( $settings );
			$payload['settings'] = $settings;
		}

		$options = null;
		if ( $request->has_param( 'options' ) ) {
			$options              = $this->clean_options( (array) $request['options'] );
			$payload['questions'] = $options;
		}

		$type = null !== $settings ? $settings['type'] : ( $post ? $this->stored_type( $post->ID ) : '' );
		// Fill-in-the-blank answers may live in the name, so renaming one re-checks its answers.
		if ( null !== $settings || null !== $options || ( 'fill-in-the-blank' === $type && isset( $payload['name'] ) ) ) {
			$name  = $payload['name'] ?? ( $post ? (string) $post->post_title : '' );
			$valid = $this->validate_options( $type, null !== $options ? $options : ( $post ? $this->stored_options( $post->ID ) : array() ), $name );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
		}

		foreach ( array( 'thumbnail_id', 'video_id' ) as $field ) {
			if ( $request->has_param( $field ) ) {
				$valid = $this->check_media( (int) $request[ $field ], $field );
				if ( is_wp_error( $valid ) ) {
					return $valid;
				}
				$payload[ $field ] = (int) $request[ $field ];
			}
		}
		foreach ( (array) $options as $index => $option ) {
			$valid = $this->check_media( $option['thumbnail_id'], "options[$index].image_id" );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
		}

		if ( $request->has_param( 'skills' ) ) {
			$payload['skills'] = (array) $request['skills'];
		}
		if ( $request->has_param( 'bank' ) ) {
			$payload['bank'] = (array) $request['bank'];
		}

		$extension = null;
		if ( $request->has_param( 'extension_settings' ) ) {
			$saved     = $post ? get_post_meta( $post->ID, self::EXTENSION_META, true ) : array();
			$extension = ExtensionSettings::prepare( 'question', (array) $request['extension_settings'], is_array( $saved ) ? $saved : array() );
			if ( is_wp_error( $extension ) ) {
				return new WP_Error(
					'ohmylms_question_invalid_extension_settings',
					$extension->get_error_message(),
					array(
						'status' => 400,
						'field'  => 'extension_settings',
						'reason' => $extension->get_error_code(),
					)
				);
			}
		}

		return array(
			'payload'   => $payload,
			'extension' => $extension,
		);
	}

	/**
	 * Filter every string leaf of the settings as post HTML unless the user may post unfiltered
	 * HTML. Numbers, booleans and keys are kept.
	 *
	 * @param mixed $value Settings value.
	 * @return mixed
	 */
	private function clean_settings( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = $this->clean_settings( $item );
			}
			return $value;
		}
		if ( is_string( $value ) && ! current_user_can( 'unfiltered_html' ) ) {
			return wp_kses_post( $value );
		}

		return $value;
	}

	/**
	 * Map API options to DraftWriter option rows. Every field is sent, so the option set is exactly
	 * what the client sent.
	 *
	 * @param array $options Options from the request (schema-validated).
	 * @return array[]
	 */
	private function clean_options( array $options ) {
		$unfiltered = current_user_can( 'unfiltered_html' );
		$clean      = array();
		foreach ( array_values( $options ) as $index => $option ) {
			$option   = (array) $option;
			$matching = array();
			foreach ( (array) ( $option['matching_data'] ?? array() ) as $key => $value ) {
				if ( 'label' === $key ) {
					$matching['label'] = $unfiltered ? (string) $value : wp_kses_post( (string) $value );
				} elseif ( 'image_url' === $key ) {
					$matching['image_url'] = esc_url_raw( (string) $value );
				}
			}
			$row = array(
				'answer'        => (string) ( $option['answer'] ?? '' ),
				'is_correct'    => ! empty( $option['is_correct'] ),
				'order_number'  => isset( $option['order_number'] ) ? (int) $option['order_number'] : $index + 1,
				'thumbnail_id'  => (int) ( $option['image_id'] ?? 0 ),
				'image_url'     => isset( $option['image_url'] ) ? esc_url_raw( (string) $option['image_url'] ) : '',
				'matching_data' => $matching,
			);
			if ( ! empty( $option['id'] ) ) {
				$row['id'] = (int) $option['id'];
			}
			$clean[] = $row;
		}

		return $clean;
	}

	/**
	 * Stored type of a question.
	 *
	 * @param int $question_id Question ID.
	 * @return string
	 */
	private function stored_type( $question_id ) {
		$settings = get_post_meta( (int) $question_id, '_question_settings', true );

		return is_array( $settings ) ? (string) ( $settings['type'] ?? '' ) : '';
	}

	/**
	 * Stored options of a question in the shape {@see clean_options()} produces.
	 *
	 * @param int $question_id Question ID.
	 * @return array[]
	 */
	private function stored_options( $question_id ) {
		$question = ohmylms_get_question( (int) $question_id );
		if ( ! $question ) {
			return array();
		}

		return array_map(
			static function ( $option ) {
				return array(
					'answer'        => (string) $option['answer'],
					'is_correct'    => ! empty( $option['is_correct'] ),
					'thumbnail_id'  => (int) ( $option['thumbnail_id'] ?? 0 ),
					'matching_data' => is_array( $option['matching_data'] ?? null ) ? $option['matching_data'] : array(),
				);
			},
			$question->get_questions()
		);
	}

	/**
	 * Rules for the settings every type shares, which no type schema checks: flags, hint and
	 * explanation, points, and the presentation choices of fill-in-the-blank and matching.
	 *
	 * @param array $settings Settings from the request.
	 * @return true|WP_Error
	 */
	private function validate_shared_settings( array $settings ) {
		foreach ( self::FLAG_SETTINGS as $key ) {
			if ( array_key_exists( $key, $settings ) && ! in_array( $settings[ $key ], array( true, false, 0, 1, '0', '1', '' ), true ) ) {
				return $this->invalid( 'ohmylms_question_invalid_settings', __( 'This setting must be on or off.', 'ohmylms' ), "settings.$key" );
			}
		}
		foreach ( self::TEXT_SETTINGS as $key ) {
			if ( array_key_exists( $key, $settings ) && ( ! is_string( $settings[ $key ] ) || strlen( $settings[ $key ] ) > self::MAX_TEXT_SETTING ) ) {
				return $this->invalid(
					'ohmylms_question_invalid_settings',
					sprintf(
						/* translators: %d: maximum length in bytes. */
						__( 'Use text of at most %d characters.', 'ohmylms' ),
						self::MAX_TEXT_SETTING
					),
					"settings.$key"
				);
			}
		}
		if ( array_key_exists( 'score', $settings ) ) {
			$score = $settings['score'];
			$value = is_array( $score ) ? ( $score['value'] ?? 0 ) : null;
			if ( ! is_array( $score ) || ( isset( $score['enabled'] ) && ! in_array( $score['enabled'], array( true, false, 0, 1, '0', '1', '' ), true ) ) || ! is_numeric( $value ) || ! is_finite( (float) $value ) || (float) $value < 0 || (float) $value > self::MAX_POINTS ) {
				return $this->invalid(
					'ohmylms_question_invalid_settings',
					sprintf(
						/* translators: %d: maximum points. */
						__( 'Enter points from 0 to %d.', 'ohmylms' ),
						self::MAX_POINTS
					),
					'settings.score'
				);
			}
		}
		if ( isset( $settings['blank_mode'] ) && ! in_array( $settings['blank_mode'], array( '', 'drag', 'type' ), true ) ) {
			return $this->invalid( 'ohmylms_question_invalid_settings', __( 'Choose how learners fill the blanks.', 'ohmylms' ), 'settings.blank_mode' );
		}
		if ( isset( $settings['match_layout'] ) && ! in_array( $settings['match_layout'], array( 'wire', 'grid' ), true ) ) {
			return $this->invalid( 'ohmylms_question_invalid_settings', __( 'Choose a matching layout.', 'ohmylms' ), 'settings.match_layout' );
		}

		return true;
	}

	/**
	 * Answer rules of the option-format built-in types, checked against the merged question.
	 *
	 * @param string  $type    Question type.
	 * @param array[] $options Options in the {@see clean_options()} shape.
	 * @param string  $name    Question name (fill-in-the-blank answers may be `{braces}` in it).
	 * @return true|WP_Error
	 */
	private function validate_options( $type, array $options, $name = '' ) {
		$texts = array_map(
			static function ( $option ) {
				return trim( wp_strip_all_tags( (string) $option['answer'] ) );
			},
			$options
		);
		if ( 'statement' === $type ) {
			if ( ! $options ) {
				return $this->invalid_options( __( 'Add at least one expected answer.', 'ohmylms' ), 'options' );
			}
			foreach ( array_values( $options ) as $index => $option ) {
				if ( '' === $texts[ $index ] || empty( $option['is_correct'] ) ) {
					return $this->invalid_options( __( 'Every expected answer needs text and must be marked correct.', 'ohmylms' ), "options[$index].answer" );
				}
			}
			return true;
		}
		if ( 'fill-in-the-blank' === $type ) {
			if ( InlineBlanks::parse( $name )['answers'] ) {
				return true;
			}
			foreach ( array_values( $options ) as $index => $option ) {
				if ( ! empty( $option['is_correct'] ) && '' !== $texts[ $index ] ) {
					return true;
				}
			}
			return $this->invalid_options( __( 'Write each answer in {curly brackets} in the question, or add an accepted answer.', 'ohmylms' ), 'options' );
		}
		if ( ! in_array( $type, self::OPTION_TYPES, true ) ) {
			return true;
		}
		$filled  = array_filter(
			$options,
			static function ( $option ) {
				return '' !== trim( wp_strip_all_tags( (string) $option['answer'] ) ) || ! empty( $option['thumbnail_id'] );
			}
		);
		$correct = count( array_filter( array_column( $options, 'is_correct' ) ) );

		if ( count( $filled ) < 2 ) {
			return $this->invalid_options( __( 'Add at least two answer options.', 'ohmylms' ), 'options' );
		}
		if ( 'true-false' === $type && 2 !== count( $options ) ) {
			return $this->invalid_options( __( 'A true/false question has exactly two options.', 'ohmylms' ), 'options' );
		}
		if ( in_array( $type, array( 'single-choice', 'true-false' ), true ) && 1 !== $correct ) {
			return $this->invalid_options( __( 'Mark exactly one option as correct.', 'ohmylms' ), 'options' );
		}
		if ( 'multiple-choice' === $type && $correct < 1 ) {
			return $this->invalid_options( __( 'Mark at least one option as correct.', 'ohmylms' ), 'options' );
		}
		if ( 'matching' === $type ) {
			foreach ( array_values( $options ) as $index => $option ) {
				$label = $option['matching_data']['label'] ?? '';
				if ( ! is_scalar( $label ) || '' === trim( (string) $label ) ) {
					return $this->invalid_options( __( 'Every matching option needs a matching label.', 'ohmylms' ), "options[$index].matching_data.label" );
				}
			}
		}

		return true;
	}

	/**
	 * A media ID must be an attachment (0 clears it).
	 *
	 * @param int    $id    Attachment ID.
	 * @param string $field Field path for the error.
	 * @return true|WP_Error
	 */
	private function check_media( $id, $field ) {
		if ( $id > 0 && 'attachment' !== get_post_type( $id ) ) {
			return $this->invalid( 'ohmylms_question_invalid_media', __( 'The selected media file does not exist.', 'ohmylms' ), $field );
		}

		return true;
	}

	/**
	 * Build a 422 option rule error.
	 *
	 * @param string $message Message.
	 * @param string $field   Field path.
	 * @return WP_Error
	 */
	private function invalid_options( $message, $field ) {
		return $this->invalid( 'ohmylms_question_invalid_options', $message, $field );
	}

	/**
	 * Build a 422 validation error.
	 *
	 * @param string $code    Error code.
	 * @param string $message Message.
	 * @param string $field   Field path.
	 * @return WP_Error
	 */
	private function invalid( $code, $message, $field ) {
		return new WP_Error(
			$code,
			$message,
			array(
				'status' => 422,
				'field'  => $field,
			)
		);
	}

	// Responses.

	/**
	 * List summaries for a page of question IDs, loading related data in batches.
	 *
	 * @param int[]           $ids     Question IDs in display order.
	 * @param WP_REST_Request $request Request.
	 * @return array[]
	 */
	private function summaries( array $ids, $request ) {
		if ( ! $ids ) {
			return array();
		}
		_prime_post_caches( $ids, false, true );
		$identities = Search::identities( $ids );
		$usage      = Search::usage_counts( $ids );
		$skills     = array_fill_keys( $ids, array() );
		$terms      = wp_get_object_terms(
			$ids,
			Taxonomy::NAME,
			array(
				'fields'                 => 'all_with_object_id',
				'update_term_meta_cache' => false,
			)
		);
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$skills[ (int) $term->object_id ][] = (int) $term->term_id;
			}
		}
		$posts = array_filter( array_map( 'get_post', $ids ) );
		cache_users( array_unique( array_map( 'intval', wp_list_pluck( $posts, 'post_author' ) ) ) );

		$rows = array();
		foreach ( $posts as $post ) {
			$identity = $identities[ $post->ID ] ?? array();
			$settings = get_post_meta( $post->ID, '_question_settings', true );
			$settings = is_array( $settings ) ? $settings : array();
			$type     = (string) ( $settings['type'] ?? '' );
			$data     = array_merge(
				array(
					'id'         => (int) $post->ID,
					'uuid'       => (string) ( $identity['uuid'] ?? '' ),
					'name'       => (string) $post->post_title,
					'type'       => $type,
					'type_label' => $this->type_label( $type ),
					'marks'      => ! empty( $settings['score']['enabled'] ) ? (float) ( $settings['score']['value'] ?? 0 ) : 0,
				),
				$this->lifecycle( $post, $identity ),
				$this->bank_fields( $identity ),
				array(
					'skills'      => $skills[ $post->ID ],
					'usage'       => $usage[ $post->ID ],
					'author'      => $this->user_ref( (int) $post->post_author ),
					'modified'    => $this->iso( $post->post_modified_gmt ),
					'permissions' => array(
						'can_edit'    => AccessPolicy::can_edit_question( $post->ID ),
						'can_approve' => AccessPolicy::can_approve_question( $post->ID ),
					),
				)
			);

			/**
			 * Filters one row of GET /questions. Rows must not carry settings or answer options.
			 *
			 * @param array           $data    Summary.
			 * @param WP_Post         $post    Question post.
			 * @param WP_REST_Request $request Request.
			 */
			$rows[] = apply_filters( 'ohmylms_rest_prepare_question_summary', $data, $post, $request );
		}

		return $rows;
	}

	/**
	 * Prepare the editor view of a question.
	 *
	 * @param WP_Post         $item    Question post.
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		$post     = $item;
		$id       = (int) $post->ID;
		$question = ohmylms_get_question( $id );
		$identity = VersionPublisher::identity( $id, false );
		$identity = $identity ? $identity : array();
		$settings = $question ? $question->get_settings() : array();
		$quizzes  = Usage::quizzes( $id );
		if ( $quizzes ) {
			_prime_post_caches( $quizzes, false, false );
		}
		$extension = get_post_meta( $id, self::EXTENSION_META, true );
		$extension = array_intersect_key( is_array( $extension ) ? $extension : array(), $this->question_extension_settings() );
		$skills    = SkillMap::current( $id );

		$data = array_merge(
			array(
				'id'                 => $id,
				'uuid'               => (string) ( $identity['uuid'] ?? '' ),
				'name'               => (string) $post->post_title,
				'description'        => (string) $post->post_content,
				'type'               => (string) ( $settings['type'] ?? '' ),
				'settings'           => $settings ? $settings : new \stdClass(),
				'options'            => $question ? array_map( array( $this, 'option_view' ), $question->get_questions() ) : array(),
				'skills'             => $skills ? $skills : new \stdClass(),
				'bank'               => $this->bank_fields( $identity ),
				'thumbnail_id'       => $question ? (int) $question->get_thumbnail_id() : 0,
				'thumbnail_url'      => $question ? $this->attachment_url( (int) $question->get_thumbnail_id() ) : '',
				'video_id'           => $question ? (int) $question->get_video_id() : 0,
				'video_url'          => $question ? $this->attachment_url( (int) $question->get_video_id() ) : '',
				'extension_settings' => $extension ? $extension : new \stdClass(),
			),
			$this->lifecycle( $post, $identity ),
			array(
				'usage'       => array(
					'quizzes'   => count( $quizzes ),
					'responses' => Usage::responses( $id ),
				),
				'quizzes'     => array_map(
					static function ( $quiz_id ) {
						return array(
							'id'       => (int) $quiz_id,
							'name'     => (string) get_post_field( 'post_title', $quiz_id ),
							'can_edit' => AccessPolicy::can_edit_quiz( $quiz_id ),
						);
					},
					$quizzes
				),
				'permissions' => array(
					'can_edit'    => AccessPolicy::can_edit_question( $id ),
					'can_approve' => AccessPolicy::can_approve_question( $id ),
					'can_delete'  => AccessPolicy::can_delete_question( $id ),
				),
				'modified'    => $this->iso( $post->post_modified_gmt ),
			)
		);
		$data = $this->add_additional_fields_to_object( $data, $request );

		$response = rest_ensure_response( $data );
		$response->header( 'Cache-Control', 'private, no-store' );
		$response->add_links(
			array(
				'self'       => array( 'href' => rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $id ) ) ),
				'collection' => array( 'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ) ),
				'versions'   => array( 'href' => rest_url( sprintf( '%s/%s/%d/versions', $this->namespace, $this->rest_base, $id ) ) ),
			)
		);

		/**
		 * Filters the editor view of a question.
		 *
		 * @param WP_REST_Response $response Response.
		 * @param WP_Post          $post     Question post.
		 * @param WP_REST_Request  $request  Request.
		 */
		return apply_filters( 'ohmylms_rest_prepare_question_item', $response, $post, $request );
	}

	/**
	 * One answer option of the editor view.
	 *
	 * @param array $option Stored option row.
	 * @return array
	 */
	private function option_view( array $option ) {
		$matching = is_array( $option['matching_data'] ?? null ) ? array_intersect_key( $option['matching_data'], array_flip( array( 'label', 'image_url' ) ) ) : array();

		return array(
			'id'            => (int) $option['id'],
			'answer'        => (string) $option['answer'],
			'is_correct'    => ! empty( $option['is_correct'] ),
			'order_number'  => (int) $option['order_number'],
			'image_id'      => (int) ( $option['thumbnail_id'] ?? 0 ),
			'image_url'     => (string) ( $option['image_url'] ?? '' ),
			'matching_data' => $matching ? $matching : null,
		);
	}

	/**
	 * URL of an attachment, or an empty string when there is none.
	 *
	 * @param int $id Attachment ID.
	 * @return string
	 */
	private function attachment_url( $id ) {
		$url = $id > 0 ? wp_get_attachment_url( $id ) : false;

		return $url ? (string) $url : '';
	}

	/**
	 * Lifecycle fields shared by the summary and the editor view.
	 *
	 * @param WP_Post $post     Question post.
	 * @param array   $identity Identity row (may be empty).
	 * @return array
	 */
	private function lifecycle( WP_Post $post, array $identity ) {
		$current  = (int) ( $identity['current_version_id'] ?? 0 );
		$approved = (int) ( $identity['approved_version_id'] ?? 0 );
		if ( Usage::ARCHIVED === $post->post_status ) {
			$status = 'archived';
		} elseif ( $approved ) {
			$status = 'approved';
		} else {
			$status = 'draft';
		}

		return array(
			'status'              => $status,
			'post_status'         => $post->post_status,
			'version'             => (int) ( $identity['latest_version_no'] ?? 0 ),
			'current_version_id'  => $current,
			'approved_version_id' => $approved,
			'approved_is_current' => $approved > 0 && $approved === $current,
		);
	}

	/**
	 * Bank attributes with defaults for questions that have no identity row yet.
	 *
	 * @param array $identity Identity row (may be empty).
	 * @return array
	 */
	private function bank_fields( array $identity ) {
		return array(
			'bank_id'    => (int) ( $identity['bank_id'] ?? 0 ),
			'difficulty' => (string) ( $identity['difficulty'] ?? 'standard' ),
			'source'     => (string) ( $identity['source'] ?? '' ),
			'family_id'  => (string) ( $identity['family_id'] ?? '' ),
			'secure'     => ! empty( $identity['secure'] ),
		);
	}

	/**
	 * Extension settings definitions that apply to questions.
	 *
	 * @return array
	 */
	private function question_extension_settings() {
		return array_filter(
			ExtensionSettings::manifest(),
			static function ( $definition ) {
				return in_array( 'question', (array) ( $definition['contexts'] ?? array() ), true );
			}
		);
	}

	/**
	 * Label of a question type (its ID when the type is not registered).
	 *
	 * @param string $type Type ID.
	 * @return string
	 */
	private function type_label( $type ) {
		$definition = Registry::get( 'question', $type );

		return $definition ? wp_strip_all_tags( (string) $definition['label'] ) : $type;
	}

	/**
	 * User reference.
	 *
	 * @param int $user_id User ID.
	 * @return array{id:int,name:string}
	 */
	private function user_ref( $user_id ) {
		$user = $user_id ? get_userdata( $user_id ) : false;

		return array(
			'id'   => (int) $user_id,
			'name' => $user ? (string) $user->display_name : '',
		);
	}

	/**
	 * RFC 3339 UTC time from a GMT MySQL datetime.
	 *
	 * @param string $gmt GMT datetime.
	 * @return string Empty when unset.
	 */
	private function iso( $gmt ) {
		$time = ( $gmt && '0000-00-00 00:00:00' !== $gmt ) ? strtotime( $gmt . ' UTC' ) : 0;

		return $time ? gmdate( 'Y-m-d\TH:i:s\Z', $time ) : '';
	}

	// Schema and arguments.

	/**
	 * Get the editor view schema (JSON Schema draft 4).
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$readonly = static function ( $type, $description ) {
			return array(
				'description' => $description,
				'type'        => $type,
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			);
		};

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'question',
			'type'       => 'object',
			'properties' => array(
				'id'                  => $readonly( 'integer', __( 'Unique identifier for the question.', 'ohmylms' ) ),
				'uuid'                => $readonly( 'string', __( 'Stable question UUID (empty until the first save through the bank).', 'ohmylms' ) ),
				'name'                => $readonly( 'string', __( 'Question text (post title).', 'ohmylms' ) ),
				'description'         => $readonly( 'string', __( 'Question body (post-safe HTML).', 'ohmylms' ) ),
				'type'                => $readonly( 'string', __( 'Question type ID (settings.type).', 'ohmylms' ) ),
				'settings'            => $readonly( 'object', __( 'Type settings, including answer keys of settings-based types.', 'ohmylms' ) ),
				'options'             => array_merge(
					$readonly( 'array', __( 'Answer options with correctness.', 'ohmylms' ) ),
					array( 'items' => array( 'type' => 'object' ) )
				),
				'skills'              => $readonly( 'object', __( 'Skill map by part: {part: {primary, supporting[]}}.', 'ohmylms' ) ),
				'bank'                => $readonly( 'object', __( 'Bank attributes: bank_id, difficulty, source, family_id, secure.', 'ohmylms' ) ),
				'thumbnail_id'        => $readonly( 'integer', __( 'Question image attachment ID (0 for none).', 'ohmylms' ) ),
				'thumbnail_url'       => $readonly( 'string', __( 'URL of the question image (empty for none).', 'ohmylms' ) ),
				'video_id'            => $readonly( 'integer', __( 'Question video attachment ID (0 for none).', 'ohmylms' ) ),
				'video_url'           => $readonly( 'string', __( 'URL of the question video (empty for none).', 'ohmylms' ) ),
				'extension_settings'  => $readonly( 'object', __( 'Registered extension settings for questions.', 'ohmylms' ) ),
				'status'              => array_merge(
					$readonly( 'string', __( 'Bank status.', 'ohmylms' ) ),
					array( 'enum' => array( 'draft', 'approved', 'archived' ) )
				),
				'post_status'         => $readonly( 'string', __( 'WordPress post status.', 'ohmylms' ) ),
				'version'             => $readonly( 'integer', __( 'Latest version number (0 before the first version).', 'ohmylms' ) ),
				'current_version_id'  => $readonly( 'integer', __( 'Version ID of the current content.', 'ohmylms' ) ),
				'approved_version_id' => $readonly( 'integer', __( 'Approved version ID (0 for none).', 'ohmylms' ) ),
				'approved_is_current' => $readonly( 'boolean', __( 'Whether the approved version is the current content.', 'ohmylms' ) ),
				'usage'               => $readonly( 'object', __( 'Number of quizzes and learner records that use the question.', 'ohmylms' ) ),
				'quizzes'             => array_merge(
					$readonly( 'array', __( 'Quizzes that contain the question.', 'ohmylms' ) ),
					array( 'items' => array( 'type' => 'object' ) )
				),
				'permissions'         => $readonly( 'object', __( 'What the current user may do: can_edit, can_approve, can_delete.', 'ohmylms' ) ),
				'modified'            => array_merge(
					$readonly( 'string', __( 'Last modification time (RFC 3339, UTC). Send it back as base_modified.', 'ohmylms' ) ),
					array( 'format' => 'date-time' )
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Body arguments of create (and, without required fields, update).
	 *
	 * @param bool $creating True for create.
	 * @return array
	 */
	private function get_write_args( $creating ) {
		$uri  = array(
			'type'      => 'string',
			'format'    => 'uri',
			'maxLength' => 2048,
		);
		$args = array(
			'name'               => array(
				'description' => __( 'Question text, 1 to 500 characters.', 'ohmylms' ),
				'type'        => 'string',
				'minLength'   => 1,
				'maxLength'   => 500,
				'required'    => $creating,
			),
			'description'        => array(
				'description' => __( 'Question body (post-safe HTML).', 'ohmylms' ),
				'type'        => 'string',
				'maxLength'   => 65535,
			),
			'settings'           => array(
				'description' => __( 'Type settings; settings.type must be a registered question type.', 'ohmylms' ),
				'type'        => 'object',
				'required'    => $creating,
				'properties'  => array(
					'type' => array(
						'type'      => 'string',
						'maxLength' => 60,
					),
				),
			),
			'options'            => array(
				'description' => __( 'Answer options. When sent, they replace the option set.', 'ohmylms' ),
				'type'        => 'array',
				'maxItems'    => self::MAX_OPTIONS,
				'items'       => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'id'            => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'answer'        => array(
							'type'      => 'string',
							'maxLength' => 10000,
							'required'  => true,
						),
						'is_correct'    => array( 'type' => 'boolean' ),
						'order_number'  => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'image_id'      => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'image_url'     => $uri,
						'matching_data' => array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'properties'           => array(
								'label'     => array(
									'type'      => 'string',
									'maxLength' => 1000,
								),
								'image_url' => $uri,
							),
						),
					),
				),
			),
			'skills'             => array(
				'description'          => __( 'Skill map by part: {part: {primary, supporting[]}}. Replaces the map.', 'ohmylms' ),
				'type'                 => 'object',
				'maxProperties'        => 20,
				'additionalProperties' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'primary'    => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'supporting' => array(
							'type'     => 'array',
							'maxItems' => 50,
							'items'    => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
						),
					),
				),
			),
			'bank'               => array(
				'description'          => __( 'Bank attributes; only the keys sent change.', 'ohmylms' ),
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'bank_id'    => array(
						'type'    => 'integer',
						'minimum' => 0,
					),
					'difficulty' => array(
						'type' => 'string',
						'enum' => BankAttributes::DIFFICULTIES,
					),
					'source'     => array(
						'type'      => 'string',
						'maxLength' => 100,
					),
					'family_id'  => array(
						'type'      => 'string',
						'maxLength' => 64,
					),
					'secure'     => array( 'type' => 'boolean' ),
				),
			),
			'thumbnail_id'       => array(
				'description' => __( 'Question image attachment ID (0 clears it).', 'ohmylms' ),
				'type'        => 'integer',
				'minimum'     => 0,
			),
			'video_id'           => array(
				'description' => __( 'Question video attachment ID (0 clears it).', 'ohmylms' ),
				'type'        => 'integer',
				'minimum'     => 0,
			),
			'extension_settings' => array(
				'description' => __( 'Registered extension settings for questions, validated by their own schemas.', 'ohmylms' ),
				'type'        => 'object',
			),
		);
		if ( ! $creating ) {
			$args['base_modified'] = array(
				'description' => __( 'The modified value of the last read (RFC 3339). A newer stored value answers 409.', 'ohmylms' ),
				'type'        => 'string',
				'format'      => 'date-time',
				'required'    => true,
			);
		}

		return $args;
	}

	/**
	 * Get collection parameters.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params = $this->get_paging_params();

		$params['search']                = parent::get_collection_params()['search'];
		$params['search']['maxLength']   = 100;
		$params['search']['description'] = __( 'Limit results to questions whose name or body contains the text.', 'ohmylms' );

		$params['type']       = array(
			'description' => __( 'Limit results to a registered question type.', 'ohmylms' ),
			'type'        => 'string',
			'enum'        => array_map( 'strval', array_keys( Registry::all( 'question' ) ) ),
		);
		$params['difficulty'] = array(
			'description' => __( 'Limit results to a difficulty band.', 'ohmylms' ),
			'type'        => 'string',
			'enum'        => BankAttributes::DIFFICULTIES,
		);
		$params['status']     = array(
			'description' => __( 'active: not archived; draft: no approved version; approved; archived.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'active',
			'enum'        => array( 'active', 'draft', 'approved', 'archived' ),
		);
		$params['bank']       = array(
			'description' => __( 'Limit results to a bank (0 = personal questions).', 'ohmylms' ),
			'type'        => 'integer',
			'minimum'     => 0,
		);
		$params['skill']      = array(
			'description' => __( 'Limit results to questions mapped to a skill term.', 'ohmylms' ),
			'type'        => 'integer',
			'minimum'     => 1,
		);
		$params['orderby']    = array(
			'description' => __( 'Sort collection by attribute.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'modified',
			'enum'        => array_keys( Search::ORDERBY ),
		);
		$params['order']      = array(
			'description' => __( 'Order sort attribute ascending or descending.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'desc',
			'enum'        => array( 'asc', 'desc' ),
		);

		return $params;
	}

	/**
	 * Page and page-size parameters (1-100, default 20).
	 *
	 * @return array
	 */
	private function get_paging_params() {
		$params = array_intersect_key( parent::get_collection_params(), array_flip( array( 'page', 'per_page' ) ) );

		$params['per_page']['default'] = 20;
		$params['per_page']['maximum'] = 100;

		return $params;
	}
}
