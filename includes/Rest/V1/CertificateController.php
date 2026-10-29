<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Controller for certificate template REST API endpoints.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Certificates\Template;
use OhMyLMS\Certificates\TemplateRenderer;
use OhMyLMS\Data\Certificate;
use OhMyLMS\DataStores\DataStores;
use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Certificate template REST controller (`ohmylms/v1/certificates`).
 *
 * Every route requires {@see self::CAPABILITY}. The contract is documented in docs/api/certificates.md
 * and assets/src/app/features/certificates/contract.json.
 *
 * @since 1.0.0
 */
class CertificateController extends RestController {

	/**
	 * Capability required by every certificate route.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Maximum courses assignable to one template.
	 */
	const MAX_COURSES = 200;

	/**
	 * Template fields that are writable through create, update and preview.
	 */
	const TEMPLATE_FIELDS = array( 'orientation', 'size', 'font_family', 'background_color', 'background_id', 'signature', 'blocks' );

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * The base route for certificate endpoints.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $rest_base = 'certificates';

	/**
	 * Course ids by certificate for the current collection page.
	 *
	 * @var array<int,int[]>|null
	 */
	private $course_ids = null;

	/**
	 * Register the routes for the certificate endpoints.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		$base = '/' . $this->rest_base;
		$item = $base . '/(?P<id>[\d]+)';
		$id   = array(
			'description' => __( 'Unique identifier for the certificate template.', 'ohmylms' ),
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
			$base . '/options',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_options' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/courses',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_course_choices' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'search'   => array(
							'description' => __( 'Limit results to courses whose title matches the string.', 'ohmylms' ),
							'type'        => 'string',
							'default'     => '',
						),
						'page'     => array(
							'description' => __( 'Current page of the collection.', 'ohmylms' ),
							'type'        => 'integer',
							'default'     => 1,
							'minimum'     => 1,
						),
						'per_page' => array(
							'description' => __( 'Maximum number of courses returned.', 'ohmylms' ),
							'type'        => 'integer',
							'default'     => 20,
							'minimum'     => 1,
							'maximum'     => 50,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/preview',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'preview' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_preview_args(),
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
				),
				'schema' => array( $this, 'get_public_item_schema' ),
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
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Check the capability shared by every route.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message used when access is denied.
	 * @return true|WP_Error
	 */
	private function require_capability( $code, $message ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return new WP_Error( $code, $message, array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Check if the current user can list certificate templates (also options and preview).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return $this->require_capability( 'ohmylms_certificate_cannot_view', __( 'Sorry, you are not allowed to view certificate templates.', 'ohmylms' ) );
	}

	/**
	 * Check if the current user can read one certificate template.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return $this->require_capability( 'ohmylms_certificate_cannot_view', __( 'Sorry, you are not allowed to view certificate templates.', 'ohmylms' ) );
	}

	/**
	 * Check if the current user can create (or duplicate) a certificate template.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		return $this->require_capability( 'ohmylms_certificate_cannot_create', __( 'Sorry, you are not allowed to create certificate templates.', 'ohmylms' ) );
	}

	/**
	 * Check if the current user can update a certificate template.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		return $this->require_capability( 'ohmylms_certificate_cannot_edit', __( 'Sorry, you are not allowed to edit certificate templates.', 'ohmylms' ) );
	}

	/**
	 * Check if the current user can delete a certificate template.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		return $this->require_capability( 'ohmylms_certificate_cannot_delete', __( 'Sorry, you are not allowed to delete certificate templates.', 'ohmylms' ) );
	}

	/**
	 * Load a certificate template post or answer 404.
	 *
	 * @param int $id Certificate id.
	 * @return WP_Post|WP_Error
	 */
	private function get_certificate_post( $id ) {
		$post = get_post( absint( $id ) );
		if ( ! $post || OHMYLMS_CERTIFICATE_CPT !== $post->post_type || 'trash' === $post->post_status ) {
			return new WP_Error( 'ohmylms_certificate_not_found', __( 'Certificate template not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		return $post;
	}

	/**
	 * Get certificate templates.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$page     = max( 1, (int) $request['page'] );
		$per_page = min( 100, max( 1, (int) $request['per_page'] ) );
		$orderby  = array(
			'id'         => 'ID',
			'name'       => 'title',
			'status'     => 'post_status',
			'created_at' => 'date',
			'updated_at' => 'modified',
		);

		$args = array(
			'post_type'           => OHMYLMS_CERTIFICATE_CPT,
			'post_status'         => 'all' === $request['status'] ? array( 'draft', 'publish' ) : $request['status'],
			'posts_per_page'      => $per_page,
			'paged'               => $page,
			's'                   => (string) $request['search'],
			'orderby'             => $orderby[ $request['orderby'] ],
			'order'               => strtoupper( $request['order'] ),
			'ignore_sticky_posts' => true,
		);

		if ( ! empty( $request['course'] ) ) {
			$args['post__in'] = $this->certificate_ids_for_course( (int) $request['course'] );
		}

		/**
		 * Filters the WP_Query arguments used to list certificate templates through the REST API.
		 *
		 * @param array            $args    WP_Query arguments.
		 * @param WP_REST_Request  $request Request.
		 *
		 * @since 1.0.0
		 */
		$args  = apply_filters( 'ohmylms_rest_ohmylms_certificate_query', $args, $request );
		$query = new WP_Query( $args );

		$total = (int) $query->found_posts;
		if ( ! $query->posts && $page > 1 ) {
			// WP_Query reports no total for a page beyond the last, so count the matches again.
			$count = new WP_Query(
				array_merge(
					$args,
					array(
						'posts_per_page' => 1,
						'paged'          => 1,
						'fields'         => 'ids',
					)
				)
			);
			$total = (int) $count->found_posts;
		}
		$total_pages = (int) ceil( $total / $per_page );
		if ( $page > $total_pages && $total > 0 ) {
			return new WP_Error( 'ohmylms_certificate_invalid_page', __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$store            = DataStores::load( 'certificate' );
		$this->course_ids = $store->get_course_ids_for( wp_list_pluck( $query->posts, 'ID' ) );

		$data = array();
		foreach ( $query->posts as $post ) {
			$data[] = $this->prepare_response_for_collection( $this->prepare_item_for_response( $post, $request ) );
		}
		$this->course_ids = null;

		$response = rest_ensure_response( $data );
		$this->add_pagination_headers( $response, $request, $total, $total_pages, $page );

		return $response;
	}

	/**
	 * Certificate ids assigned to a course, as a non-empty list usable in post__in.
	 *
	 * @param int $course_id Course id.
	 * @return int[]
	 */
	private function certificate_ids_for_course( $course_id ) {
		global $wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare( 'SELECT certificate_id FROM %i WHERE course_id = %d', $wpdb->prefix . 'ohmylms_certificate_relationship', $course_id )
		);
		$ids = array_map( 'intval', (array) $ids );

		return $ids ? $ids : array( 0 );
	}

	/**
	 * Add X-WP-Total, X-WP-TotalPages and Link headers.
	 *
	 * @param WP_REST_Response $response    Response.
	 * @param WP_REST_Request  $request     Request.
	 * @param int              $total       Total items.
	 * @param int              $total_pages Total pages.
	 * @param int              $page        Current page.
	 * @return void
	 */
	private function add_pagination_headers( WP_REST_Response $response, WP_REST_Request $request, $total, $total_pages, $page ) {
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $total_pages );

		$base = add_query_arg( urlencode_deep( $request->get_query_params() ), rest_url( $this->namespace . '/' . $this->rest_base ) );
		if ( $page > 1 ) {
			$response->link_header( 'prev', add_query_arg( 'page', $page - 1, $base ) );
		}
		if ( $page < $total_pages ) {
			$response->link_header( 'next', add_query_arg( 'page', $page + 1, $base ) );
		}
	}

	/**
	 * Get one certificate template.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$post = $this->get_certificate_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return $this->prepare_item_for_response( $post, $request );
	}

	/**
	 * Create a certificate template.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_item( $request ) {
		if ( ! empty( $request['id'] ) ) {
			return new WP_Error( 'ohmylms_certificate_exists', __( 'Cannot create an existing certificate template.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$name = $this->clean_name( $request['name'] );
		if ( is_wp_error( $name ) ) {
			return $name;
		}

		$template = Template::normalize( $this->template_input( $request ), Template::defaults() );
		if ( is_wp_error( $template ) ) {
			return $template;
		}

		$course_ids = $this->clean_course_ids( null === $request['course_ids'] ? array() : $request['course_ids'] );
		if ( is_wp_error( $course_ids ) ) {
			return $course_ids;
		}

		$certificate = new Certificate();
		$certificate->set_name( $name );
		$certificate->set_status( null === $request['status'] ? 'draft' : $request['status'] );

		$saved = $this->persist( $certificate, $template, $course_ids );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$post = get_post( $certificate->get_id() );

		/**
		 * Fires after a certificate is inserted via the REST API.
		 *
		 * @param WP_Post         $post    The certificate post.
		 * @param WP_REST_Request $request The request object.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_rest_insert_certificate', $post, $request );

		return $this->created_response( $post, $request );
	}

	/**
	 * Update a certificate template. PUT, PATCH and POST all behave as a partial update.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_item( $request ) {
		$post = $this->get_certificate_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$certificate = ohmylms_get_certificate( $post );
		if ( ! $certificate ) {
			return new WP_Error( 'ohmylms_certificate_not_found', __( 'Certificate template not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		// Validate everything before anything is written.
		if ( null !== $request['name'] ) {
			$name = $this->clean_name( $request['name'] );
			if ( is_wp_error( $name ) ) {
				return $name;
			}
			$certificate->set_name( $name );
		}
		if ( null !== $request['status'] ) {
			$certificate->set_status( $request['status'] );
		}

		$template = Template::normalize( $this->template_input( $request ), $certificate->get_contents() );
		if ( is_wp_error( $template ) ) {
			return $template;
		}

		$course_ids = null;
		if ( null !== $request['course_ids'] ) {
			$course_ids = $this->clean_course_ids( $request['course_ids'] );
			if ( is_wp_error( $course_ids ) ) {
				return $course_ids;
			}
		}

		$saved = $this->persist( $certificate, $template, $course_ids );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return $this->prepare_item_for_response( get_post( $post->ID ), $request );
	}

	/**
	 * Delete a certificate template permanently, together with its course assignments.
	 *
	 * Media-library images are never deleted: other templates may use them.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function delete_item( $request ) {
		$post = $this->get_certificate_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$certificate = ohmylms_get_certificate( $post );
		$previous    = $this->prepare_item_for_response( $post, $request )->get_data();
		$certificate->delete();

		if ( get_post( $post->ID ) ) {
			return new WP_Error( 'ohmylms_certificate_delete_failed', __( 'The certificate template could not be deleted.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		/**
		 * Fires after a certificate is deleted via the REST API.
		 *
		 * @param int $certificate_id Certificate id.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_rest_delete_certificate', $post->ID );

		return rest_ensure_response(
			array(
				'deleted'  => true,
				'previous' => $previous,
			)
		);
	}

	/**
	 * Duplicate a certificate template as a draft without course assignments.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function duplicate_item( $request ) {
		$post = $this->get_certificate_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$source = ohmylms_get_certificate( $post );
		$name   = mb_substr(
			/* translators: %s: certificate template name. */
			sprintf( __( 'Copy of %s', 'ohmylms' ), $source->get_name() ),
			0,
			200
		);

		$copy = new Certificate();
		$copy->set_name( $name );
		$copy->set_status( 'draft' );

		$saved = $this->persist( $copy, $source->get_contents(), array() );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$new_post = get_post( $copy->get_id() );

		/** This action is documented in includes/Rest/V1/CertificateController.php */
		do_action( 'ohmylms_rest_insert_certificate', $new_post, $request );

		return $this->created_response( $new_post, $request );
	}

	/**
	 * Courses that can be assigned to a template: a small, searchable, paged lookup.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_course_choices( $request ) {
		$query = new WP_Query(
			array(
				'post_type'           => OHMYLMS_COURSE_CPT,
				'post_status'         => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page'      => min( 50, max( 1, (int) $request['per_page'] ) ),
				'paged'               => max( 1, (int) $request['page'] ),
				's'                   => (string) $request['search'],
				'orderby'             => 'title',
				'order'               => 'ASC',
				'ignore_sticky_posts' => true,
			)
		);

		$data = array();
		foreach ( $query->posts as $course ) {
			if ( current_user_can( 'edit_post', $course->ID ) ) {
				$data[] = array(
					'id'     => (int) $course->ID,
					'title'  => get_the_title( $course ),
					'status' => $course->post_status,
				);
			}
		}

		$response = rest_ensure_response( $data );
		$response->header( 'X-WP-Total', (string) (int) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) (int) $query->max_num_pages );

		return $response;
	}

	/**
	 * Render a server-side preview of an unsaved template.
	 *
	 * Nothing is stored. The HTML is a complete document built only from validated values and escaped
	 * output; display it in a sandboxed frame.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function preview( $request ) {
		$template = Template::normalize( $this->template_input( $request ), Template::defaults() );
		if ( is_wp_error( $template ) ) {
			return $template;
		}

		$dimensions = Template::dimensions( $template );
		$values     = TemplateRenderer::sample_values();

		return rest_ensure_response(
			array(
				'html'      => TemplateRenderer::document( $template, $values ),
				'width_mm'  => $dimensions['width'],
				'height_mm' => $dimensions['height'],
				'values'    => $values,
			)
		);
	}

	/**
	 * Editor options: choices, placeholders and limits.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_options( $request ) {
		$to_options = static function ( array $map ) {
			$options = array();
			foreach ( $map as $value => $label ) {
				$options[] = array(
					'value' => (string) $value,
					'label' => (string) $label,
				);
			}

			return $options;
		};

		$sizes = array();
		foreach ( Template::sizes() as $value => $definition ) {
			$sizes[] = array(
				'value'     => (string) $value,
				'label'     => $definition['label'],
				'width_mm'  => $definition['width'],
				'height_mm' => $definition['height'],
			);
		}

		$placeholders = array();
		foreach ( Template::placeholders() as $token => $definition ) {
			$placeholders[] = array(
				'value'  => $token,
				'label'  => $definition['label'],
				'sample' => $definition['sample'],
			);
		}

		return rest_ensure_response(
			array(
				'orientations'  => $to_options( Template::orientations() ),
				'sizes'         => $sizes,
				'font_families' => $to_options( Template::font_families() ),
				'alignments'    => $to_options( Template::alignments() ),
				'statuses'      => $to_options(
					array(
						'draft'   => __( 'Draft', 'ohmylms' ),
						'publish' => __( 'Published', 'ohmylms' ),
					)
				),
				'placeholders'  => $placeholders,
				'limits'        => array(
					'max_blocks'    => Template::MAX_BLOCKS,
					'max_text'      => Template::MAX_TEXT,
					'min_font_size' => Template::MIN_FONT_SIZE,
					'max_font_size' => Template::MAX_FONT_SIZE,
					'max_courses'   => self::MAX_COURSES,
				),
			)
		);
	}

	/**
	 * Save a certificate and everything that belongs to it. All input is already validated.
	 *
	 * @param Certificate $certificate Certificate (new or loaded).
	 * @param array       $template    Canonical template.
	 * @param int[]|null  $course_ids  Courses to assign, or null to leave them unchanged.
	 * @return true|WP_Error
	 */
	private function persist( Certificate $certificate, array $template, $course_ids ) {
		$id = $certificate->save();
		if ( ! $id || ! get_post( $id ) ) {
			return new WP_Error( 'ohmylms_certificate_save_failed', __( 'The certificate template could not be saved.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		$certificate->set_id( $id );

		$certificate->set_contents( $template );
		// Derived on the server from the validated template; clients cannot supply HTML.
		$certificate->set_html_contents( TemplateRenderer::fragment( $template, TemplateRenderer::identity_values() ) );

		if ( null !== $course_ids ) {
			$assigned = $certificate->set_courses( $course_ids );
			if ( is_wp_error( $assigned ) ) {
				return $assigned;
			}
		}

		return true;
	}

	/**
	 * Build the 201 response for a new template.
	 *
	 * @param WP_Post         $post    Certificate post.
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	private function created_response( WP_Post $post, WP_REST_Request $request ) {
		$response = $this->prepare_item_for_response( $post, $request );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $post->ID ) ) );

		return $response;
	}

	/**
	 * Sanitize and validate a name.
	 *
	 * @param mixed $name Raw name.
	 * @return string|WP_Error
	 */
	private function clean_name( $name ) {
		$name = trim( sanitize_text_field( (string) $name ) );
		if ( '' === $name || mb_strlen( $name ) > 200 ) {
			return new WP_Error(
				'ohmylms_certificate_invalid_name',
				__( 'Enter a name of 1 to 200 characters.', 'ohmylms' ),
				array(
					'status' => 422,
					'field'  => 'name',
				)
			);
		}

		return $name;
	}

	/**
	 * Validate course ids: each must be an existing course the current user can edit.
	 *
	 * @param mixed $course_ids Raw ids.
	 * @return int[]|WP_Error
	 */
	private function clean_course_ids( $course_ids ) {
		$course_ids = array_values( array_unique( array_map( 'absint', (array) $course_ids ) ) );
		if ( count( $course_ids ) > self::MAX_COURSES ) {
			return new WP_Error(
				'ohmylms_certificate_invalid_courses',
				__( 'Assign no more than 200 courses to one template.', 'ohmylms' ),
				array(
					'status' => 422,
					'field'  => 'course_ids',
				)
			);
		}

		$invalid = array();
		foreach ( $course_ids as $course_id ) {
			$post = $course_id ? get_post( $course_id ) : null;
			if ( ! $post || OHMYLMS_COURSE_CPT !== $post->post_type || 'trash' === $post->post_status || ! current_user_can( 'edit_post', $course_id ) ) {
				$invalid[] = $course_id;
			}
		}
		if ( $invalid ) {
			return new WP_Error(
				'ohmylms_certificate_invalid_courses',
				__( 'One or more selected courses do not exist.', 'ohmylms' ),
				array(
					'status'     => 422,
					'field'      => 'course_ids',
					'course_ids' => $invalid,
				)
			);
		}

		return $course_ids;
	}

	/**
	 * Template fields present in a request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	private function template_input( WP_REST_Request $request ) {
		$input = array();
		foreach ( self::TEMPLATE_FIELDS as $field ) {
			if ( null !== $request[ $field ] ) {
				$input[ $field ] = $request[ $field ];
			}
		}

		return $input;
	}

	/**
	 * Prepare a certificate for the REST response.
	 *
	 * @param WP_Post         $item    Certificate post.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		$post        = $item;
		$certificate = ohmylms_get_certificate( $post );
		$template    = $certificate->get_contents();
		$fields      = $this->get_fields_for_response( $request );
		$course_ids  = isset( $this->course_ids[ $post->ID ] ) ? $this->course_ids[ $post->ID ] : null;
		$courses     = $certificate->get_courses( $course_ids );
		$background  = (int) $template['background_id'];
		$signature   = (int) $template['signature']['image_id'];

		$values = array(
			'id'               => (int) $post->ID,
			'name'             => $post->post_title,
			'status'           => 'publish' === $post->post_status ? 'publish' : 'draft',
			'orientation'      => $template['orientation'],
			'size'             => $template['size'],
			'font_family'      => $template['font_family'],
			'background_color' => $template['background_color'],
			'background_id'    => $background,
			'background_url'   => $background ? (string) wp_get_attachment_url( $background ) : '',
			'signature'        => $template['signature'],
			'signature_url'    => $signature ? (string) wp_get_attachment_url( $signature ) : '',
			'blocks'           => $template['blocks'],
			'course_ids'       => wp_list_pluck( $courses, 'id' ),
			'courses'          => $courses,
			'course_count'     => count( $courses ),
			'created_at'       => $this->format_date( $post->post_date_gmt, $post->post_date ),
			'updated_at'       => $this->format_date( $post->post_modified_gmt, $post->post_modified ),
		);

		$data = array();
		foreach ( $values as $field => $value ) {
			if ( rest_is_field_included( $field, $fields ) ) {
				$data[ $field ] = $value;
			}
		}

		$response = rest_ensure_response( $data );
		$response->add_links(
			array(
				'self'       => array( 'href' => rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $post->ID ) ) ),
				'collection' => array( 'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ) ),
			)
		);

		/**
		 * Filters the response for a certificate in the REST API.
		 *
		 * @param WP_REST_Response $response The response.
		 * @param WP_Post          $post     The certificate post.
		 * @param WP_REST_Request  $request  The request.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'ohmylms_rest_prepare_certificate', $response, $post, $request );
	}

	/**
	 * Format a date as RFC 3339 in UTC.
	 *
	 * @param string $gmt   GMT date from the database.
	 * @param string $local Site-time date, used when the GMT date is unset.
	 * @return string
	 */
	private function format_date( $gmt, $local ) {
		if ( ! $gmt || '0000-00-00 00:00:00' === $gmt ) {
			$gmt = get_gmt_from_date( $local );
		}

		return (string) mysql_to_rfc3339( $gmt ) . 'Z';
	}

	/**
	 * Get the certificate schema (JSON Schema draft 4).
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$context = array( 'view', 'edit' );
		$number  = static function ( $description, $min, $max ) {
			return array(
				'description' => $description,
				'type'        => 'number',
				'minimum'     => $min,
				'maximum'     => $max,
			);
		};

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'certificate',
			'type'       => 'object',
			'properties' => array(
				'id'               => array(
					'description' => __( 'Unique identifier for the certificate template.', 'ohmylms' ),
					'type'        => 'integer',
					'context'     => $context,
					'readonly'    => true,
				),
				'name'             => array(
					'description' => __( 'Template name.', 'ohmylms' ),
					'type'        => 'string',
					'minLength'   => 1,
					'maxLength'   => 200,
					'context'     => $context,
					'required'    => true,
				),
				'status'           => array(
					'description' => __( 'Draft templates are not offered to learners; published templates are.', 'ohmylms' ),
					'type'        => 'string',
					'enum'        => array( 'draft', 'publish' ),
					'context'     => $context,
				),
				'orientation'      => array(
					'description' => __( 'Page orientation.', 'ohmylms' ),
					'type'        => 'string',
					'enum'        => array_keys( Template::orientations() ),
					'context'     => $context,
				),
				'size'             => array(
					'description' => __( 'Page size.', 'ohmylms' ),
					'type'        => 'string',
					'enum'        => array_keys( Template::sizes() ),
					'context'     => $context,
				),
				'font_family'      => array(
					'description' => __( 'Generic font family for every text block.', 'ohmylms' ),
					'type'        => 'string',
					'enum'        => array_keys( Template::font_families() ),
					'context'     => $context,
				),
				'background_color' => array(
					'description' => __( 'Page background color as #rrggbb.', 'ohmylms' ),
					'type'        => 'string',
					'pattern'     => '^#[0-9a-fA-F]{6}$',
					'context'     => $context,
				),
				'background_id'    => array(
					'description' => __( 'Media library attachment id of the background image; 0 for none.', 'ohmylms' ),
					'type'        => 'integer',
					'minimum'     => 0,
					'context'     => $context,
				),
				'background_url'   => array(
					'description' => __( 'URL of the background image (read only).', 'ohmylms' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => $context,
					'readonly'    => true,
				),
				'signature'        => array(
					'description'          => __( 'Signature image and its position as percentages of the page.', 'ohmylms' ),
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'image_id' => array(
							'description' => __( 'Media library attachment id of the signature image; 0 for none.', 'ohmylms' ),
							'type'        => 'integer',
							'minimum'     => 0,
						),
						'x'        => $number( __( 'Left edge, percent of page width.', 'ohmylms' ), 0, 100 ),
						'y'        => $number( __( 'Top edge, percent of page height.', 'ohmylms' ), 0, 100 ),
						'width'    => $number( __( 'Width, percent of page width.', 'ohmylms' ), 1, 100 ),
					),
					'context'              => $context,
				),
				'signature_url'    => array(
					'description' => __( 'URL of the signature image (read only).', 'ohmylms' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => $context,
					'readonly'    => true,
				),
				'blocks'           => array(
					'description' => __( 'Text blocks. Text may contain placeholders such as {student_name}.', 'ohmylms' ),
					'type'        => 'array',
					'maxItems'    => Template::MAX_BLOCKS,
					'items'       => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => array( 'id', 'text', 'x', 'y', 'width', 'font_size' ),
						'properties'           => array(
							'id'        => array(
								'description' => __( 'Stable block identifier, unique within the template.', 'ohmylms' ),
								'type'        => 'string',
								'pattern'     => '^[A-Za-z0-9_-]{1,32}$',
							),
							'text'      => array(
								'description' => __( 'Plain text; placeholders are replaced when the certificate is issued.', 'ohmylms' ),
								'type'        => 'string',
								'minLength'   => 1,
								'maxLength'   => Template::MAX_TEXT,
							),
							'x'         => $number( __( 'Left edge, percent of page width.', 'ohmylms' ), 0, 100 ),
							'y'         => $number( __( 'Top edge, percent of page height.', 'ohmylms' ), 0, 100 ),
							'width'     => $number( __( 'Width, percent of page width (x + width must not exceed 100).', 'ohmylms' ), 1, 100 ),
							'font_size' => $number( __( 'Font size in points.', 'ohmylms' ), Template::MIN_FONT_SIZE, Template::MAX_FONT_SIZE ),
							'align'     => array(
								'description' => __( 'Text alignment.', 'ohmylms' ),
								'type'        => 'string',
								'enum'        => array_keys( Template::alignments() ),
							),
							'color'     => array(
								'description' => __( 'Text color as #rrggbb.', 'ohmylms' ),
								'type'        => 'string',
								'pattern'     => '^#[0-9a-fA-F]{6}$',
							),
							'bold'      => array(
								'description' => __( 'Bold text.', 'ohmylms' ),
								'type'        => 'boolean',
							),
						),
					),
					'context'     => $context,
				),
				'course_ids'       => array(
					'description' => __( 'Courses that use this template. A course uses one template, so assigning it here moves it from any other.', 'ohmylms' ),
					'type'        => 'array',
					'maxItems'    => self::MAX_COURSES,
					'items'       => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'context'     => $context,
				),
				'courses'          => array(
					'description' => __( 'Assigned courses with titles (read only).', 'ohmylms' ),
					'type'        => 'array',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'id'    => array( 'type' => 'integer' ),
							'title' => array( 'type' => 'string' ),
						),
					),
					'context'     => $context,
					'readonly'    => true,
				),
				'course_count'     => array(
					'description' => __( 'Number of assigned courses (read only).', 'ohmylms' ),
					'type'        => 'integer',
					'context'     => $context,
					'readonly'    => true,
				),
				'created_at'       => array(
					'description' => __( 'Creation time, RFC 3339 in UTC.', 'ohmylms' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => $context,
					'readonly'    => true,
				),
				'updated_at'       => array(
					'description' => __( 'Last modification time, RFC 3339 in UTC.', 'ohmylms' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => $context,
					'readonly'    => true,
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Arguments for partial updates: the creation arguments without defaults or required flags.
	 *
	 * @return array
	 */
	private function get_update_args() {
		$args = $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE );
		foreach ( $args as $name => $arg ) {
			unset( $args[ $name ]['default'], $args[ $name ]['required'] );
		}

		return $args;
	}

	/**
	 * Arguments for the preview route: template fields only, none required.
	 *
	 * @return array
	 */
	private function get_preview_args() {
		return array_intersect_key( $this->get_update_args(), array_flip( self::TEMPLATE_FIELDS ) );
	}

	/**
	 * Get collection parameters.
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		$params['per_page']['default']   = 10;
		$params['per_page']['maximum']   = 100;
		$params['search']['description'] = __( 'Limit results to templates whose name or text matches the string.', 'ohmylms' );

		$params['status'] = array(
			'description' => __( 'Limit result set to templates with a specific status.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'all',
			'enum'        => array( 'all', 'draft', 'publish' ),
		);

		$params['course'] = array(
			'description' => __( 'Limit result set to the template assigned to this course.', 'ohmylms' ),
			'type'        => 'integer',
			'minimum'     => 1,
		);

		$params['orderby'] = array(
			'description' => __( 'Sort collection by attribute.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'created_at',
			'enum'        => array( 'id', 'name', 'status', 'created_at', 'updated_at' ),
		);

		$params['order'] = array(
			'description' => __( 'Order sort attribute ascending or descending.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'desc',
			'enum'        => array( 'asc', 'desc' ),
		);

		return $params;
	}
}
