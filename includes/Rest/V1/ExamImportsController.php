<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * ExamImportsController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\ExamImport\FigureContent;
use OhMyLMS\ExamImport\Figures;
use OhMyLMS\ExamImport\Jobs;
use OhMyLMS\ExamImport\Proposals;
use OhMyLMS\ExamImport\Review;
use OhMyLMS\ExamImport\Workflow;
use OhMyLMS\QuestionBank\BulkImport;
use WP_Error;
use WP_HTTP_Response;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Typed import-review API of the new admin under `ohmylms/v1/exam-imports/jobs`. See docs/api/exams.md.
 *
 * Upload, validation, extraction, commit, exam and quiz creation go through the existing job pipeline
 * ({@see Jobs}, {@see Workflow}, {@see \OhMyLMS\Exams\Library}); the legacy `/exam-imports` routes keep
 * their shapes. Jobs, rows (which carry answers and rubrics) and figure crops are visible to the job
 * owner only, including to administrators.
 */
class ExamImportsController extends RestController {

	/**
	 * Largest request body other than uploads, in bytes.
	 */
	const MAX_BODY = 1048576;

	/**
	 * Largest CSV or JSON upload, in bytes.
	 */
	const MAX_ROWS_FILE = 1048576;

	/**
	 * The base route.
	 *
	 * @var string
	 */
	protected $rest_base = 'exam-imports/jobs';

	/**
	 * Verified crop waiting to be streamed by {@see self::serve_figure()}.
	 *
	 * @var string|null
	 */
	private static $figure = null;

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$base     = '/' . $this->rest_base;
		$item     = $base . '/(?P<id>[\d]+)';
		$row      = $item . '/rows/(?P<index>[\d]+)';
		$args     = array(
			'id' => array(
				'description' => __( 'Unique identifier for the import job.', 'ohmylms' ),
				'type'        => 'integer',
				'minimum'     => 1,
			),
		);
		$row_args = $args + array(
			'index' => array(
				'description' => __( 'Zero-based position of the row in the paper.', 'ohmylms' ),
				'type'        => 'integer',
				'minimum'     => 0,
				'maximum'     => 199,
			),
		);
		$revision = array(
			'description' => __( 'The `revision` of the last read; a changed review answers 409.', 'ohmylms' ),
			'type'        => 'integer',
			'minimum'     => 0,
			'required'    => true,
		);
		$text     = static function ( $max ) {
			return array(
				'type'      => 'string',
				'maxLength' => $max,
			);
		};

		register_rest_route(
			$this->namespace,
			$base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'collection_permissions_check' ),
					'args'                => array(
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 50,
							'default' => 20,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'collection_permissions_check' ),
					'args'                => array(
						'target'         => array(
							'type'    => 'string',
							'enum'    => array( 'exam', 'quiz', 'questions' ),
							'default' => 'exam',
						),
						'default_skills' => $text( 400 ),
						'name'           => $text( 190 ),
						'format'         => array(
							'type'    => 'string',
							'enum'    => array( 'auto', 'csv', 'json' ),
							'default' => 'auto',
						),
						'text'           => $text( self::MAX_ROWS_FILE ),
						'dry_run'        => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'skip_invalid'   => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$item,
			array(
				'args' => $args,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'item_permissions_check' ),
				),
			)
		);

		foreach ( array( 'extract', 'retry' ) as $operation ) {
			register_rest_route(
				$this->namespace,
				$item . '/' . $operation,
				array(
					'args' => $args,
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $this, $operation . '_item' ),
						'permission_callback' => array( $this, 'item_permissions_check' ),
					),
				)
			);
		}

		register_rest_route(
			$this->namespace,
			$row,
			array(
				'args' => $row_args,
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_row' ),
					'permission_callback' => array( $this, 'item_permissions_check' ),
					'args'                => array(
						'revision'        => $revision,
						'question'        => $text( 20000 ),
						'page'            => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 500,
						),
						'requires_source' => array( 'type' => 'boolean' ),
						'notes'           => $text( 4000 ),
						'skills'          => $text( 1000 ),
						'parts'           => array(
							'type'     => 'array',
							'minItems' => 1,
							'maxItems' => 50,
							'items'    => array( 'type' => 'object' ),
						),
						'figures'         => array(
							'type'     => 'array',
							'maxItems' => 20,
							'items'    => array( 'type' => 'object' ),
						),
					),
				),
			)
		);

		foreach ( array( 'accept', 'reject' ) as $decision ) {
			register_rest_route(
				$this->namespace,
				$row . '/' . $decision,
				array(
					'args' => $row_args,
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $this, $decision . '_row' ),
						'permission_callback' => array( $this, 'item_permissions_check' ),
						'args'                => array( 'revision' => $revision ),
					),
				)
			);
		}

		$proposal      = $row . '/figures/(?P<figure>[a-zA-Z0-9_-]{1,40})/proposal';
		$proposal_args = $row_args + array(
			'figure' => array(
				'description' => __( 'ID of the figure placement in the row.', 'ohmylms' ),
				'type'        => 'string',
				'pattern'     => '^[a-zA-Z0-9_-]{1,40}$',
			),
		);
		register_rest_route(
			$this->namespace,
			$proposal,
			array(
				'args' => $proposal_args,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'propose_figure' ),
					'permission_callback' => array( $this, 'item_permissions_check' ),
					'args'                => array(
						'revision' => $revision,
						'source'   => array(
							'type'     => 'string',
							'enum'     => array( 'generate', 'authored' ),
							'required' => true,
						),
						'spec'     => array( 'type' => 'object' ),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$proposal . '/accept',
			array(
				'args' => $proposal_args,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'accept_proposal' ),
					'permission_callback' => array( $this, 'item_permissions_check' ),
					'args'                => array(
						'revision' => $revision,
						'display'  => array(
							'type'    => 'string',
							'enum'    => FigureContent::PROPOSAL_DISPLAYS,
							'default' => 'replace',
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$proposal . '/reject',
			array(
				'args' => $proposal_args,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reject_proposal' ),
					'permission_callback' => array( $this, 'item_permissions_check' ),
					'args'                => array( 'revision' => $revision ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$item . '/apply',
			array(
				'args' => $args,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'apply_item' ),
					'permission_callback' => array( $this, 'item_permissions_check' ),
					'args'                => array(
						'revision'         => $revision,
						'title'            => $text( 190 ),
						'duration_minutes' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 1440,
						),
						'board'            => $text( 190 ),
						'qualification'    => $text( 190 ),
						'subject_code'     => $text( 190 ),
						'paper'            => $text( 190 ),
						'variant'          => $text( 190 ),
						'session'          => $text( 190 ),
						'calculator'       => $text( 190 ),
						'instructions'     => $text( 4000 ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$item . '/figures',
			array(
				'args' => $args,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_figures' ),
					'permission_callback' => array( $this, 'item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_figure' ),
					'permission_callback' => array( $this, 'item_permissions_check' ),
					'args'                => array(
						'page'   => array(
							'type'     => 'integer',
							'minimum'  => 1,
							'maximum'  => 500,
							'required' => true,
						),
						'bounds' => array(
							'type'     => 'array',
							'minItems' => 4,
							'maxItems' => 4,
							'items'    => array(
								'type'    => 'number',
								'minimum' => 0,
								'maximum' => 1,
							),
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$item . '/figures/(?P<asset>[0-9a-fA-F-]{36})',
			array(
				'args' => $args,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_figure_image' ),
					'permission_callback' => array( $this, 'item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/exam-imports/skills',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_skills' ),
					'permission_callback' => array( $this, 'collection_permissions_check' ),
					'args'                => array(
						'search' => array(
							'type'      => 'string',
							'minLength' => 1,
							'maxLength' => 200,
							'required'  => true,
						),
						'page'   => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
					),
				),
			)
		);

		if ( ! has_filter( 'rest_pre_serve_request', array( self::class, 'serve_figure' ) ) ) {
			add_filter( 'rest_pre_serve_request', array( self::class, 'serve_figure' ), 10, 3 );
		}
	}

	// Permissions.

	/**
	 * 401 for guests, 403 for everyone else.
	 *
	 * @param string $code    Error code.
	 * @param string $message Message.
	 * @return WP_Error
	 */
	private function forbidden( $code, $message ) {
		return new WP_Error( $code, $message, array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Collection routes: the legacy import capabilities, `edit_posts` and `upload_files`.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function collection_permissions_check( $request ) {
		return current_user_can( 'edit_posts' ) && current_user_can( 'upload_files' ) ? true : $this->forbidden( 'ohmylms_exam_import_cannot_view', __( 'Sorry, you are not allowed to import exams.', 'ohmylms' ) );
	}

	/**
	 * Item routes: 404 for unknown IDs first, then the job owner only.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function item_permissions_check( $request ) {
		if ( ! Review::job( (int) $request['id'] ) ) {
			return new WP_Error( 'ohmylms_exam_import_not_found', __( 'Import not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		if ( ! Jobs::permission( $this->legacy( $request ) ) ) {
			return $this->forbidden( 'ohmylms_exam_import_forbidden', __( 'Sorry, you are not allowed to open this import.', 'ohmylms' ) );
		}
		if ( strlen( (string) $request->get_body() ) > self::MAX_BODY ) {
			return new WP_Error( 'ohmylms_exam_import_too_large', __( 'The request is too large.', 'ohmylms' ), array( 'status' => 413 ) );
		}
		return true;
	}

	// Callbacks.

	/**
	 * Internal request for a legacy pipeline callback, carrying only the job ID and the given params.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param array           $params  Extra parameters.
	 * @return WP_REST_Request
	 */
	private function legacy( $request, array $params = array() ) {
		$legacy = new WP_REST_Request( $request->get_method(), '/ohmylms/v1/exam-imports/' . (int) $request['id'] );
		$legacy->set_param( 'id', (int) $request['id'] );
		foreach ( $params as $key => $value ) {
			$legacy->set_param( $key, $value );
		}
		return $legacy;
	}

	/**
	 * No-store JSON response.
	 *
	 * @param mixed $data   Body.
	 * @param int   $status Status.
	 * @return WP_REST_Response
	 */
	private function respond( $data, $status = 200 ) {
		$response = new WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'private, no-store' );
		return $response;
	}

	/**
	 * The job's detail view after an operation.
	 *
	 * @param int $id     Job ID.
	 * @param int $status Status.
	 * @return WP_REST_Response|WP_Error
	 */
	private function detail( $id, $status = 200 ) {
		$job = Review::job( $id );
		if ( ! $job ) {
			return new WP_Error( 'ohmylms_exam_import_not_found', __( 'Import not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		return $this->respond( Review::view( $id, $job, true ), $status );
	}

	/**
	 * Body fields the route does not accept.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string[]        $allowed Accepted fields.
	 * @return WP_Error|null
	 */
	private function unknown_fields( $request, array $allowed ) {
		$fields = array_diff( array_keys( array_merge( (array) $request->get_body_params(), (array) $request->get_json_params() ) ), $allowed );
		if ( ! $fields ) {
			return null;
		}
		return new WP_Error(
			'ohmylms_exam_import_unknown_field',
			__( 'The request contains fields this route does not accept.', 'ohmylms' ),
			array(
				'status' => 400,
				'fields' => array_values( $fields ),
			)
		);
	}

	/**
	 * The owner's jobs, newest first.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$per_page = (int) $request['per_page'];
		$page     = (int) $request['page'];
		$query    = new WP_Query(
			array(
				'post_type'      => Jobs::TYPE,
				'post_status'    => 'private',
				'author'         => get_current_user_id(),
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);
		$total    = (int) $query->found_posts;
		$pages    = (int) ceil( $total / $per_page );
		if ( $page > 1 && $page > $pages ) {
			return new WP_Error( 'ohmylms_exam_import_invalid_page', __( 'The page number is larger than the number of pages.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$items = array();
		foreach ( $query->posts as $post ) {
			$job = Review::job( $post->ID );
			if ( $job ) {
				$items[] = Review::view( $post->ID, $job );
			}
		}
		$response = $this->respond( $items );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $pages );
		return $response;
	}

	/**
	 * Start an import: a PDF (with an optional mark scheme) goes to the private job pipeline; CSV or
	 * JSON rows, as a file or as `text`, become a committed job, or a dry-run report with `dry_run`.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$unknown = $this->unknown_fields( $request, array( 'target', 'default_skills', 'name', 'format', 'text', 'dry_run', 'skip_invalid' ) );
		if ( $unknown ) {
			return $unknown;
		}
		$files = $request->get_file_params();
		$file  = isset( $files['file'] ) && is_array( $files['file'] ) && UPLOAD_ERR_NO_FILE !== ( $files['file']['error'] ?? UPLOAD_ERR_NO_FILE ) ? $files['file'] : null;
		$text  = $request['text'];
		$name  = (string) $request['name'];
		if ( $file ) {
			$extension = strtolower( pathinfo( (string) ( $file['name'] ?? '' ), PATHINFO_EXTENSION ) );
			if ( 'pdf' === $extension ) {
				$created = Jobs::create( $request );
				if ( is_wp_error( $created ) ) {
					return $created;
				}
				return $this->created( (int) $created->get_data()['id'] );
			}
			if ( ! in_array( $extension, array( 'csv', 'json', 'txt' ), true ) ) {
				return new WP_Error( 'ohmylms_exam_import_file_type', __( 'Choose a PDF paper, or a CSV or JSON file of question rows.', 'ohmylms' ), array( 'status' => 400 ) );
			}
			if ( UPLOAD_ERR_OK !== ( $file['error'] ?? null ) || ! is_string( $file['tmp_name'] ?? null ) || ! is_uploaded_file( $file['tmp_name'] ) || filesize( $file['tmp_name'] ) > self::MAX_ROWS_FILE ) {
				return new WP_Error( 'ohmylms_exam_import_upload', __( 'Choose a file of at most 1 MB.', 'ohmylms' ), array( 'status' => 400 ) );
			}
			$text = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Verified local upload, never a URL.
			$name = '' !== trim( $name ) ? $name : sanitize_file_name( (string) $file['name'] );
		}
		if ( ! is_string( $text ) || '' === trim( $text ) ) {
			return new WP_Error( 'ohmylms_exam_import_empty', __( 'Choose a file, or paste question rows.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$format = (string) $request['format'];
		if ( $request['dry_run'] ) {
			$rows = BulkImport::parse( $text, $format );
			$dry  = is_wp_error( $rows ) ? $rows : BulkImport::run( $rows, array( 'default_skills' => Jobs::skill_tokens( $request['default_skills'] ?? '' ) ) );
			if ( is_wp_error( $dry ) ) {
				return $dry;
			}
			return $this->respond(
				array(
					'dry_run' => true,
					'counts'  => $dry['counts'],
					'rows'    => array_map(
						static function ( $row ) {
							return array_intersect_key( $row, array_flip( array( 'row', 'status', 'errors', 'warnings' ) ) );
						},
						array_values( $dry['rows'] )
					),
				)
			);
		}
		$rows = new WP_REST_Request( 'POST', '/ohmylms/v1/exam-imports/rows' );
		$rows->set_param( 'text', $text );
		$rows->set_param( 'format', $format );
		$rows->set_param( 'target', $request['target'] );
		$rows->set_param( 'default_skills', (string) $request['default_skills'] );
		$rows->set_param( 'skip_invalid', (bool) $request['skip_invalid'] );
		$rows->set_param( 'name', $name );
		$created = Workflow::rows( $rows );
		if ( is_wp_error( $created ) ) {
			return $created;
		}
		return $this->created( (int) $created->get_data()['id'] );
	}

	/**
	 * 201 with the new job and its Location.
	 *
	 * @param int $id Job ID.
	 * @return WP_REST_Response|WP_Error
	 */
	private function created( $id ) {
		$response = $this->detail( $id, 201 );
		if ( $response instanceof WP_REST_Response ) {
			$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $id ) ) );
		}
		return $response;
	}

	/**
	 * One job with its inventory and review rows; also what the review screen polls.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		return $this->detail( (int) $request['id'] );
	}

	/**
	 * Delete a job and its private sources (refused while an exam uses it).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$result = Jobs::delete( $this->legacy( $request ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( empty( $result['deleted'] ) ) {
			return new WP_Error( 'ohmylms_exam_import_delete_failed', __( 'The import could not be deleted.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		return $this->respond(
			array(
				'deleted' => true,
				'id'      => (int) $request['id'],
			)
		);
	}

	/**
	 * Start or resume extraction through the configured AI provider.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function extract_item( $request ) {
		$result = Workflow::extract( $this->legacy( $request ) );
		return is_wp_error( $result ) ? $result : $this->detail( (int) $request['id'] );
	}

	/**
	 * Requeue failed or interrupted validation.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function retry_item( $request ) {
		$result = Jobs::retry( $this->legacy( $request ) );
		return is_wp_error( $result ) ? $result : $this->detail( (int) $request['id'] );
	}

	/**
	 * Edit one row.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_row( $request ) {
		$unknown = $this->unknown_fields( $request, array_merge( array( 'revision' ), Review::ROW_FIELDS ) );
		if ( $unknown ) {
			return $unknown;
		}
		$changes = array();
		foreach ( Review::ROW_FIELDS as $field ) {
			if ( null !== $request[ $field ] ) {
				$changes[ $field ] = $request[ $field ];
			}
		}
		$result = Review::edit_row( (int) $request['id'], (int) $request['index'], $changes, (int) $request['revision'] );
		return is_wp_error( $result ) ? $result : $this->respond( $result );
	}

	/**
	 * Accept one row.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function accept_row( $request ) {
		return $this->decide( $request, 'accept' );
	}

	/**
	 * Reject one row.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reject_row( $request ) {
		return $this->decide( $request, 'reject' );
	}

	/**
	 * Record a row decision.
	 *
	 * @param WP_REST_Request $request  Request.
	 * @param string          $decision accept|reject.
	 * @return WP_REST_Response|WP_Error
	 */
	private function decide( $request, $decision ) {
		$unknown = $this->unknown_fields( $request, array( 'revision' ) );
		if ( $unknown ) {
			return $unknown;
		}
		$result = Review::decide( (int) $request['id'], (int) $request['index'], $decision, (int) $request['revision'] );
		return is_wp_error( $result ) ? $result : $this->respond( $result );
	}

	/**
	 * Propose a JSXGraph reconstruction of a figure: generated by the exam converter, or authored.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function propose_figure( $request ) {
		$unknown = $this->unknown_fields( $request, array( 'revision', 'source', 'spec' ) );
		if ( $unknown ) {
			return $unknown;
		}
		if ( 'authored' === $request['source'] && null === $request['spec'] ) {
			return new WP_Error( 'rest_missing_callback_param', __( 'Send the figure configuration in `spec`.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$result = Proposals::propose( (int) $request['id'], (int) $request['index'], (string) $request['figure'], (int) $request['revision'], (string) $request['source'], 'authored' === $request['source'] ? $request['spec'] : null );
		return is_wp_error( $result ) ? $result : $this->respond( $result );
	}

	/**
	 * Accept a figure's proposal.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function accept_proposal( $request ) {
		$unknown = $this->unknown_fields( $request, array( 'revision', 'display' ) );
		if ( $unknown ) {
			return $unknown;
		}
		$result = Proposals::accept( (int) $request['id'], (int) $request['index'], (string) $request['figure'], (int) $request['revision'], (string) $request['display'] );
		return is_wp_error( $result ) ? $result : $this->respond( $result );
	}

	/**
	 * Reject a figure's proposal.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reject_proposal( $request ) {
		$unknown = $this->unknown_fields( $request, array( 'revision' ) );
		if ( $unknown ) {
			return $unknown;
		}
		$result = Proposals::reject( (int) $request['id'], (int) $request['index'], (string) $request['figure'], (int) $request['revision'] );
		return is_wp_error( $result ) ? $result : $this->respond( $result );
	}

	/**
	 * Commit accepted rows and create the exam or quiz; repeating it changes nothing.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function apply_item( $request ) {
		$fields  = array( 'title', 'duration_minutes', 'board', 'qualification', 'subject_code', 'paper', 'variant', 'session', 'calculator', 'instructions' );
		$unknown = $this->unknown_fields( $request, array_merge( array( 'revision' ), $fields ) );
		if ( $unknown ) {
			return $unknown;
		}
		$details = array();
		foreach ( $fields as $field ) {
			if ( null !== $request[ $field ] ) {
				$details[ $field ] = $request[ $field ];
			}
		}
		$result = Review::apply( (int) $request['id'], (int) $request['revision'], $details );
		return is_wp_error( $result ) ? $result : $this->respond( $result );
	}

	/**
	 * Crop records of this job, with image URLs of the authorised figure route.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_figures( $request ) {
		$listed = Figures::listing( $this->legacy( $request ) );
		if ( is_wp_error( $listed ) ) {
			return $listed;
		}
		return $this->respond(
			array_map(
				function ( $figure ) use ( $request ) {
					return $this->figure_view( (int) $request['id'], $figure );
				},
				$listed
			)
		);
	}

	/**
	 * Safe crop view.
	 *
	 * @param int   $id     Job ID.
	 * @param array $figure Legacy view.
	 * @return array
	 */
	private function figure_view( $id, array $figure ) {
		$ready = 'ready' === ( $figure['state'] ?? '' );
		return array(
			'id'       => (string) $figure['id'],
			'state'    => (string) $figure['state'],
			'attempts' => (int) ( $figure['attempts'] ?? 0 ),
			'error'    => (string) ( $figure['error'] ?? '' ),
			'page'     => (int) ( $figure['page'] ?? 0 ),
			'bounds'   => array_map( 'floatval', (array) ( $figure['bounds'] ?? array() ) ),
			'width'    => (int) ( $figure['width'] ?? 0 ),
			'height'   => (int) ( $figure['height'] ?? 0 ),
			'url'      => $ready ? add_query_arg( '_wpnonce', wp_create_nonce( 'wp_rest' ), rest_url( sprintf( '%s/%s/%d/figures/%s', $this->namespace, $this->rest_base, $id, $figure['id'] ) ) ) : '',
		);
	}

	/**
	 * Queue a crop of the question paper.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_figure( $request ) {
		$unknown = $this->unknown_fields( $request, array( 'page', 'bounds' ) );
		if ( $unknown ) {
			return $unknown;
		}
		$created = Figures::create(
			$this->legacy(
				$request,
				array(
					'page'   => (int) $request['page'],
					'bounds' => array_map( 'floatval', (array) $request['bounds'] ),
				)
			)
		);
		return is_wp_error( $created ) ? $created : $this->respond( $this->figure_view( (int) $request['id'], $created ), 201 );
	}

	/**
	 * Stream one crop of this job to its owner. The body is replaced in {@see self::serve_figure()};
	 * internal dispatches get the size.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_figure_image( $request ) {
		$uuid = strtolower( (string) $request['asset'] );
		if ( ! Review::owns_asset( (int) $request['id'], $uuid ) ) {
			return new WP_Error( 'ohmylms_exam_import_figure_not_found', __( 'This import has no such figure.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$path = Figures::verified_path( $uuid );
		if ( is_wp_error( $path ) ) {
			return new WP_Error( 'ohmylms_exam_import_figure_unavailable', __( 'The figure is not ready or failed its integrity check.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		self::$figure = $path;
		$response     = $this->respond( array( 'bytes' => (int) filesize( $path ) ) );
		$response->header( 'Content-Type', 'image/png' );
		$response->header( 'Content-Disposition', 'inline; filename="exam-figure.png"' );
		$response->header( 'X-Content-Type-Options', 'nosniff' );
		$response->header( 'Cross-Origin-Resource-Policy', 'same-origin' );
		return $response;
	}

	/**
	 * Send a verified crop instead of the JSON body of the figure route.
	 *
	 * @param bool                  $served  Whether the request has already been served.
	 * @param WP_HTTP_Response|null $result  Result to send.
	 * @param WP_REST_Request|null  $request Served request.
	 * @return bool
	 */
	public static function serve_figure( $served, $result, $request = null ) {
		$file         = self::$figure;
		self::$figure = null;
		if (
			$served || null === $file || ! ( $result instanceof WP_HTTP_Response ) || 200 !== $result->get_status()
			|| ! ( $request instanceof WP_REST_Request ) || ! preg_match( '#^/ohmylms/v1/exam-imports/jobs/\d+/figures/[0-9a-fA-F-]{36}$#', $request->get_route() )
		) {
			return $served;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Stream an authorised, checksum-verified private crop.
		readfile( $file );
		return true;
	}

	/**
	 * Search the existing skill catalogue for tagging rows.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_skills( $request ) {
		$found = \OhMyLMS\QuestionBank\SkillCatalogue::search( sanitize_text_field( $request['search'] ), (int) $request['page'], 10 );
		$items = array();
		foreach ( (array) $found['items'] as $skill ) {
			$items[] = array(
				'id'   => (int) ( $skill['id'] ?? 0 ),
				'name' => (string) ( $skill['name'] ?? '' ),
				'code' => (string) ( $skill['code'] ?? '' ),
				'path' => implode( ' › ', array_map( 'strval', array_column( (array) ( $skill['path'] ?? array() ), 'name' ) ) ),
			);
		}
		$response = $this->respond( $items );
		$response->header( 'X-WP-Total', (string) (int) $found['total'] );
		$response->header( 'X-WP-TotalPages', (string) (int) ceil( $found['total'] / 10 ) );
		return $response;
	}
}
