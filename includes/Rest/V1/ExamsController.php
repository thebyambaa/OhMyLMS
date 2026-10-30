<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * ExamsController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Assessment\DeliveryPolicy;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Exams\Composer;
use OhMyLMS\Exams\Editor;
use OhMyLMS\Exams\Library;
use OhMyLMS\QuestionBank\AccessPolicy;
use OhMyLMS\QuestionBank\Search;
use OhMyLMS\QuestionBank\VersionPublisher;
use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Typed exam API of the new admin under `ohmylms/v1/exams/library`. See docs/api/exams.md.
 *
 * The legacy `/exams`, `/exams/compose` and `/exams/{id}/settings|questions|publish|release` routes of
 * {@see Library} and {@see Composer} keep their shapes for the old Content Hub and the quiz editor panel.
 * Exams are visible to their author only, as in the legacy routes, including to administrators.
 */
class ExamsController extends RestController {

	/**
	 * Largest request body, in bytes.
	 */
	const MAX_BODY = 262144;

	/**
	 * The base route.
	 *
	 * @var string
	 */
	protected $rest_base = 'exams/library';

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$base     = '/' . $this->rest_base;
		$item     = $base . '/(?P<id>[\d]+)';
		$id       = array(
			'description' => __( 'Unique identifier for the exam.', 'ohmylms' ),
			'type'        => 'integer',
			'minimum'     => 1,
		);
		$revision = array(
			'description' => __( 'The `revision` of the last read; a changed exam answers 409.', 'ohmylms' ),
			'type'        => 'string',
			'pattern'     => '^[0-9a-f]{40}$',
			'required'    => true,
		);

		register_rest_route(
			$this->namespace,
			$base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
							'default' => 20,
						),
						'search'   => array(
							'type'      => 'string',
							'maxLength' => 100,
							'default'   => '',
						),
						'status'   => array(
							'type'    => 'string',
							'enum'    => array( 'all', 'draft', 'publish' ),
							'default' => 'all',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => array(
						'title'            => array(
							'type'      => 'string',
							'maxLength' => Editor::MAX_TITLE,
							'required'  => true,
						),
						'duration_minutes' => array(
							'type'     => 'integer',
							'minimum'  => 1,
							'maximum'  => 1440,
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$item,
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'revision'         => $revision,
						'title'            => array(
							'type'      => 'string',
							'maxLength' => Editor::MAX_TITLE,
						),
						'duration_minutes' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 1440,
						),
						'instructions'     => array(
							'type'      => 'string',
							'maxLength' => Editor::MAX_INSTRUCTIONS,
						),
						'header'           => array(
							'type'                 => 'object',
							'properties'           => array_fill_keys(
								Editor::HEADER_FIELDS,
								array(
									'type'      => 'string',
									'maxLength' => Editor::MAX_HEADER,
								)
							),
							'additionalProperties' => false,
						),
						'timing'           => array(
							'type'                 => 'object',
							'properties'           => array(
								'available_from'   => array(
									'type'      => 'string',
									'maxLength' => 40,
								),
								'available_until'  => array(
									'type'      => 'string',
									'maxLength' => 40,
								),
								'grace_seconds'    => array(
									'type'    => 'integer',
									'minimum' => 0,
									'maximum' => 600,
								),
								'feedback_release' => array(
									'type' => 'string',
									'enum' => array( 'immediate', 'after_close', 'manual' ),
								),
							),
							'additionalProperties' => false,
						),
						'paper'            => array(
							'type'                 => 'object',
							'properties'           => array(
								'size'           => array(
									'type' => 'string',
									'enum' => array( 'a4', 'letter' ),
								),
								'margin'         => array(
									'type'    => 'integer',
									'minimum' => 10,
									'maximum' => 30,
								),
								'font_size'      => array(
									'type'    => 'integer',
									'minimum' => 9,
									'maximum' => 14,
								),
								'font'           => array(
									'type' => 'string',
									'enum' => array( 'sans', 'serif' ),
								),
								'numbering'      => array(
									'type' => 'string',
									'enum' => array( 'number', 'q' ),
								),
								'show_marks'     => array( 'type' => 'boolean' ),
								'lines_per_mark' => array(
									'type'    => 'integer',
									'minimum' => 1,
									'maximum' => 4,
								),
								'page_numbers'   => array( 'type' => 'boolean' ),
								'default_view'   => array( 'type' => 'boolean' ),
								'versions'       => array(
									'type'    => 'integer',
									'minimum' => 1,
									'maximum' => 4,
								),
							),
							'additionalProperties' => false,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$item . '/composition',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_composition' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'revision' => $revision,
						'items'    => array(
							'type'     => 'array',
							'maxItems' => Editor::MAX_QUESTIONS + Editor::MAX_SECTIONS,
							'required' => true,
							'items'    => array(
								'type'                 => 'object',
								'properties'           => array(
									'kind'        => array(
										'type'     => 'string',
										'enum'     => array( 'section', 'question' ),
										'required' => true,
									),
									'title'       => array(
										'type'      => 'string',
										'maxLength' => Editor::MAX_TITLE,
									),
									'new_page'    => array( 'type' => 'boolean' ),
									'question_id' => array(
										'type'    => 'integer',
										'minimum' => 1,
									),
								),
								'additionalProperties' => false,
							),
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$item . '/bank',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_bank' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
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
						'search'   => array(
							'type'      => 'string',
							'maxLength' => 100,
							'default'   => '',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$item . '/publish',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'publish_item' ),
					'permission_callback' => array( $this, 'publish_item_permissions_check' ),
					'args'                => array(
						'revision'        => $revision,
						'confirm_overlap' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
			)
		);
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
	 * List exams: authors.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'edit_posts' ) ? true : $this->forbidden( 'ohmylms_exam_cannot_view', __( 'Sorry, you are not allowed to view exams.', 'ohmylms' ) );
	}

	/**
	 * Create exams: authors.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		return current_user_can( 'edit_posts' ) ? true : $this->forbidden( 'ohmylms_exam_cannot_create', __( 'Sorry, you are not allowed to create exams.', 'ohmylms' ) );
	}

	/**
	 * Item routes: 404 for unknown IDs first, then the author's own exam only.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		$id = (int) $request['id'];
		if ( ! Editor::data( $id ) || 'trash' === get_post_status( $id ) ) {
			return new WP_Error( 'ohmylms_exam_not_found', __( 'Exam not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'edit_posts' ) || get_current_user_id() !== (int) get_post_field( 'post_author', $id ) ) {
			return $this->forbidden( 'ohmylms_exam_forbidden', __( 'Sorry, you are not allowed to edit this exam.', 'ohmylms' ) );
		}
		if ( strlen( (string) $request->get_body() ) > self::MAX_BODY ) {
			return new WP_Error( 'ohmylms_exam_too_large', __( 'The request is too large.', 'ohmylms' ), array( 'status' => 413 ) );
		}
		return true;
	}

	/**
	 * Publish: the item rule plus `publish_posts`.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function publish_item_permissions_check( $request ) {
		$allowed = $this->get_item_permissions_check( $request );
		if ( true !== $allowed ) {
			return $allowed;
		}
		return current_user_can( 'publish_posts' ) ? true : $this->forbidden( 'ohmylms_exam_cannot_publish', __( 'Sorry, you are not allowed to publish exams.', 'ohmylms' ) );
	}

	// Callbacks.

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
			'ohmylms_exam_unknown_field',
			__( 'The request contains fields this route does not accept.', 'ohmylms' ),
			array(
				'status' => 400,
				'fields' => array_values( $fields ),
			)
		);
	}

	/**
	 * The author's exams, newest first.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$per_page = (int) $request['per_page'];
		$page     = (int) $request['page'];
		$query    = new WP_Query(
			array(
				'post_type'      => Library::TYPE,
				'post_status'    => 'all' === $request['status'] ? array( 'publish', 'draft' ) : $request['status'],
				'author'         => get_current_user_id(),
				's'              => sanitize_text_field( $request['search'] ),
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'no_found_rows'  => false,
			)
		);
		$total    = (int) $query->found_posts;
		$pages    = (int) ceil( $total / $per_page );
		if ( $page > 1 && $page > $pages ) {
			return new WP_Error( 'ohmylms_exam_invalid_page', __( 'The page number is larger than the number of pages.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$items = array();
		foreach ( $query->posts as $post ) {
			if ( Editor::data( $post->ID ) ) {
				$items[] = Editor::summary( $post->ID );
			}
		}
		$response = $this->respond( $items );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $pages );
		return $response;
	}

	/**
	 * Create a blank draft exam (composed in OhMyLMS) through the existing composer.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$unknown = $this->unknown_fields( $request, array( 'title', 'duration_minutes' ) );
		if ( $unknown ) {
			return $unknown;
		}
		$title = sanitize_text_field( $request['title'] );
		if ( '' === trim( $title ) ) {
			return new WP_Error(
				'ohmylms_exam_invalid_field',
				__( 'Enter a title of 1 to 190 characters.', 'ohmylms' ),
				array(
					'status' => 422,
					'field'  => 'title',
				)
			);
		}
		$create = new WP_REST_Request( 'POST', '/ohmylms/v1/exams/compose' );
		$create->set_param( 'title', $title );
		$create->set_param( 'duration_minutes', (int) $request['duration_minutes'] );
		$created = Composer::create( $create );
		if ( is_wp_error( $created ) ) {
			return $created;
		}
		$response = $this->respond( Editor::read( (int) $created['id'] ), 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, (int) $created['id'] ) ) );
		return $response;
	}

	/**
	 * One exam with settings and composition.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_item( $request ) {
		return $this->respond( Editor::read( (int) $request['id'] ) );
	}

	/**
	 * Save settings (partial).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$fields  = array( 'revision', 'title', 'duration_minutes', 'instructions', 'header', 'timing', 'paper' );
		$unknown = $this->unknown_fields( $request, $fields );
		if ( $unknown ) {
			return $unknown;
		}
		$input = array();
		foreach ( array_slice( $fields, 1 ) as $field ) {
			if ( null !== $request[ $field ] ) {
				$input[ $field ] = $request[ $field ];
			}
		}
		$result = Editor::update( (int) $request['id'], $input, $request['revision'] );
		return is_wp_error( $result ) ? $result : $this->respond( $result );
	}

	/**
	 * Replace the composition.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_composition( $request ) {
		$unknown = $this->unknown_fields( $request, array( 'revision', 'items' ) );
		if ( $unknown ) {
			return $unknown;
		}
		$result = Editor::compose( (int) $request['id'], (array) $request['items'], $request['revision'] );
		return is_wp_error( $result ) ? $result : $this->respond( $result );
	}

	/**
	 * Questions the author may add, from the existing visibility-scoped bank search. Rows carry no
	 * settings or answers.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_bank( $request ) {
		if ( ! Schema::ready() ) {
			return new WP_Error( 'ohmylms_question_bank_unavailable', __( 'The question bank is not installed.', 'ohmylms' ), array( 'status' => 503 ) );
		}
		$data     = Editor::data( (int) $request['id'] );
		$per_page = (int) $request['per_page'];
		$page     = (int) $request['page'];
		$found    = Search::query(
			array(
				'search'       => sanitize_text_field( $request['search'] ),
				'exclude_quiz' => (int) $data['quiz_id'],
			),
			$page,
			$per_page,
			'modified',
			'desc'
		);
		if ( is_wp_error( $found ) ) {
			return $found;
		}
		$pages = (int) ceil( $found['total'] / $per_page );
		if ( $page > 1 && $page > $pages ) {
			return new WP_Error( 'ohmylms_exam_invalid_page', __( 'The page number is larger than the number of pages.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$items = array();
		foreach ( $found['ids'] as $question_id ) {
			$settings = (array) get_post_meta( $question_id, '_question_settings', true );
			$identity = VersionPublisher::identity( $question_id, false );
			$editable = AccessPolicy::can_edit_question( $question_id );
			$approved = ! empty( $identity['approved_version_id'] );
			$items[]  = array(
				'id'        => (int) $question_id,
				'name'      => (string) get_the_title( $question_id ),
				'type'      => (string) ( $settings['type'] ?? '' ),
				'marks'     => ! empty( $settings['score']['enabled'] ) ? max( 0, (float) ( $settings['score']['value'] ?? 0 ) ) : 0,
				'approved'  => $approved,
				'available' => DeliveryPolicy::allows( $question_id, DeliveryPolicy::QUIZ ) && AccessPolicy::can_use_question( $question_id ) && ( $editable || $approved ),
			);
		}
		$response = $this->respond( $items );
		$response->header( 'X-WP-Total', (string) $found['total'] );
		$response->header( 'X-WP-TotalPages', (string) $pages );
		return $response;
	}

	/**
	 * Publish through the existing gate (completeness, overlap warning, approval of pinned versions).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function publish_item( $request ) {
		$unknown = $this->unknown_fields( $request, array( 'revision', 'confirm_overlap' ) );
		if ( $unknown ) {
			return $unknown;
		}
		$id = (int) $request['id'];
		if ( 'publish' === get_post_status( $id ) ) {
			return new WP_Error( 'ohmylms_exam_published', __( 'This exam is already published.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		$current = Editor::revision( $id );
		if ( ! hash_equals( $current, (string) $request['revision'] ) ) {
			return new WP_Error(
				'ohmylms_exam_conflict',
				__( 'This exam was changed somewhere else. Reload it before saving.', 'ohmylms' ),
				array(
					'status'   => 409,
					'revision' => $current,
				)
			);
		}
		$publish = new WP_REST_Request( 'POST', '/ohmylms/v1/exams/' . $id . '/publish' );
		$publish->set_param( 'id', $id );
		$publish->set_param( 'confirm_overlap', (bool) $request['confirm_overlap'] );
		$result = Library::publish( $publish );
		return is_wp_error( $result ) ? $result : $this->respond( Editor::read( $id ) );
	}
}
