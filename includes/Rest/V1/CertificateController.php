<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Controller for handling certificate REST API endpoints.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Data\Certificate;
use OhMyLMS\DataException;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Controller for handling certificate REST API endpoints.
 *
 * This class extends the RESTController abstract class and defines REST API routes
 * for certificate-related CRUD operations and many more.
 *
 * @since 1.0.0
 */
class CertificateController extends RestController {

	/**
	 * The base route for certificate base endpoints.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $base = 'certificates';

	/**
	 * Check certificate permission for Certificate controller.
	 */
	public function check_certificate_permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Registers REST API routes for certificate operations.
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
					'permission_callback' => array( $this, 'check_certificate_permission' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_certificate_permission' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
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
					'permission_callback' => array( $this, 'check_certificate_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the certificate.', 'ohmylms' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'check_certificate_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'check_certificate_permission' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_certificate_permission' ),
				),
			)
		);
	}

	/**
	 * Check if a given request has access to read an item.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 *
	 * @since 1.0.0
	 */
	public function get_item_permissions_check( $request ) {
		$post = get_post( (int) $request['id'] );

		if ( $post && ! current_user_can( 'read_post', $post->ID ) ) {
			return new WP_Error( 'ohmylms_rest_cannot_view', __( 'Sorry, you cannot view this resource.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}


	/**
	 * Get collection of certificates with caching.
	 *
	 * @param \WP_REST_Request $request The REST request object containing query parameters.
	 * @return WP_Error|\WP_HTTP_Response|\WP_REST_Response The response object containing the certificates data or an error.
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
			'post_type'           => OHMYLMS_CERTIFICATE_CPT,
			'post_status'         => isset( $request['post_status'] ) ? sanitize_text_field( $request['post_status'] ) : array( 'draft', 'publish', 'future' ),
			'meta_query'          => array(),
		);

		// Correct post_status.
		if ( 'any' === $args['post_status'] || ! in_array( $args['post_status'], array( 'draft', 'publish', 'future' ), true ) ) {
			$args['post_status'] = array( 'draft', 'publish', 'future' );
		}

		// Date query.
		if ( isset( $request['before'] ) || isset( $request['after'] ) ) {
			$date_query = array();
			if ( isset( $request['before'] ) ) {
				$date_query['before'] = sanitize_text_field( $request['before'] );
			}
			if ( isset( $request['after'] ) ) {
				$date_query['after'] = sanitize_text_field( $request['after'] );
			}
			$args['date_query'][] = $date_query;
		}

		// Meta query.
		if ( isset( $request['meta_key'], $request['meta_value'] ) ) {
			$args['meta_query'][] = array(
				'key'     => sanitize_text_field( $request['meta_key'] ),
				'value'   => sanitize_text_field( $request['meta_value'] ),
				'compare' => 'LIKE',
			);
		}

		// Filter by course_id if passed
		// if ( ! empty( $request['course_id'] ) ) {
		// $course_id = intval( $request['course_id'] );.

		// $args['meta_query'][] = array(
		// 'key'     => 'course_ids', // Adjust the meta key if needed
		// 'value'   => '"' . $course_id . '"',
		// 'compare' => 'LIKE',
		// );
		// }

		// Allow further filters.
		$args       = apply_filters( 'ohmylms_rest_ohmylms_certificate_query', $args, $request );
		$query_args = $this->prepare_items_query( $args, $request );

		$posts_query = new WP_Query( $query_args );
		$posts       = array();

		foreach ( $posts_query->posts as $post ) {
			$data    = $this->prepare_item_for_response( $post, $request );
			$posts[] = $this->prepare_response_for_collection( $data );
		}

		$filter_posts = $posts;
		if ( ! empty( $request['course_id'] ) ) {
			$filter_posts = array();

			foreach ( $posts as $key => $post ) {
				if ( isset( $post['courses'] ) ) {
					$courses = $post['courses'];
					foreach ( $courses as $course ) {
						if ( (int) $course['id'] === (int) $request['course_id'] ) {
							$filter_posts[] = $post;
						}
					}
				}
			}
		}

		$total_posts    = $posts_query->found_posts;
		$posts_per_page = (int) ( 0 < $query_args['posts_per_page'] ? $query_args['posts_per_page'] : $total_posts );
		$max_pages      = ceil( $total_posts / $posts_per_page );

		$response = rest_ensure_response( $filter_posts );
		$response->header( 'X-WP-Total', $total_posts );
		$response->header( 'X-WP-TotalCertificates', (int) $total_posts );
		$response->header( 'X-WP-TotalPages', $max_pages );

		return $response;
	}



	/**
	 * Create a single certificate
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 *
	 * @since 1.0.0
	 */
	public function create_item( $request ) {
		if ( ! empty( $request['id'] ) ) {
			// Translators: %s is replaced with object name.
			return new WP_Error( 'ohmylms_rest_certificate_exists', sprintf( __( 'Cannot create existing %s.', 'ohmylms' ), 'certificate' ), array( 'status' => 400 ) );
		}

		try {
			$certificate_id = $this->save_certificate( $request );
			$post           = get_post( $certificate_id );
			$this->update_post_meta_fields( $post, $request );

			/**
			 * Fires after a certificate is inserted via the REST API.
			 *
			 * @param \WP_Post         $post    The post object for the certificate.
			 * @param \WP_REST_Request $request The request object.
			 *
			 * @since 1.0.0
			 */
			do_action( 'ohmylms_rest_insert_certificate', $post, $request );

			$request->set_param( 'context', 'edit' );
			$response = $this->prepare_item_for_response_with_contents( $post, $request );
			$response = rest_ensure_response( $response );
			$response->set_status( 201 );
			return $response;
		} catch ( DataException $e ) {
			return new WP_Error( 400, $e->getMessage(), array( 'status' => $e->getCode() ) );
		}
	}


	/**
	 * Retrieves a single certificate by ID.
	 *
	 * @param \WP_REST_Request $request The REST request object containing the certificate ID.
	 * @return WP_Error|\WP_HTTP_Response|\WP_REST_Response The response object containing the certificate data or an error.
	 *
	 * @since 1.0.0
	 */
	public function get_item( $request ) {
		$id   = (int) $request['id'];
		$post = get_post( $id );
		if ( empty( $id ) || empty( $post->ID ) || OHMYLMS_CERTIFICATE_CPT !== $post->post_type ) {
			return new WP_Error( 'ohmylms_rest_invalid_certificate_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		$data     = $this->prepare_item_for_response_with_contents( $post, $request );
		$response = rest_ensure_response( $data );
		$response->link_header( 'alternate', get_permalink( $id ), array( 'type' => 'text/html' ) );
		return $response;
	}


	/**
	 * Updates a single certificate.
	 *
	 * @param \WP_REST_Request $request The REST request object containing the certificate ID and data.
	 * @return WP_Error|\WP_REST_Response|\WP_HTTP_Response The response object containing the updated certificate data or an error.
	 *
	 * @since 1.0.0
	 */
	public function update_item( $request ) {
		$post_id = (int) $request['id'];
		if ( empty( $post_id ) || get_post_type( $post_id ) !== OHMYLMS_CERTIFICATE_CPT ) {
			return new WP_Error( 'ohmylms_rest_certificate_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		try {
			$certificate_id = $this->save_certificate( $request );
			$post           = get_post( $certificate_id );
			$this->update_post_meta_fields( $post, $request );

			$request->set_param( 'context', 'edit' );
			$response = $this->prepare_item_for_response_with_contents( $post, $request );
			return rest_ensure_response( $response );

		} catch ( DataException $e ) {
			return new WP_Error( $e->getErrorCode(), $e->getMessage(), $e->getErrorData() );
		}
	}



	/**
	 * Delete certificate
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function delete_item( $request ) {
		// Get certificate id.
		$certificate_id = isset( $request['id'] ) ? (int) $request['id'] : 0;

		// Check the certificate id exist or not.
		if ( ! $certificate_id ) {
			return new WP_Error( 'ohmylms_rest_certificate_empty_id', __( 'ID is required.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		// Get existing certificate by certificate id.
		$certificate = ohmylms_get_certificate( $certificate_id );

		// Check the certificate exist or not.
		if ( ! ( $certificate instanceof Certificate ) ) {
			return new WP_Error( 'ohmylms_rest_certificate_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		// Delete the certificate.
		$certificate->delete();

		/**
		 * Executes the 'ohmylms_rest_delete_certificate' action hook.
		 * This hook is triggered when a certificate is being deleted via the REST API.
		 *
		 * @param string $certificate_id Certificate ID.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_rest_delete_certificate', $certificate_id );

		$response = array(
			'status'  => 'success',
			'message' => __( 'Certificate deleted successfully', 'ohmylms' ),
		);
		return rest_ensure_response( $response );
	}

	/**
	 * Update post meta fields for a certificate.
	 *
	 * This method updates the meta fields for a given certificate post based on the provided request data.
	 *
	 * @param \WP_Post         $post The post object representing the certificate.
	 * @param \WP_REST_Request $request The REST request object containing the meta data.
	 * @return bool True on success, false on failure.
	 *
	 * @throws DataException When the operation cannot be completed.
	 * @since 1.0.0
	 */
	protected function update_post_meta_fields( $post, $request ) {
		$certificate = ohmylms_get_certificate( $post );
		// Save certificate meta fields.
		$certificate = $this->set_certificate_meta( $certificate, $request );

		// Save the certificate data.
		$certificate->save();

		return true;
	}


	/**
	 * Set product meta data for a certificate.
	 *
	 * @param Certificate     $certificate The certificate object.
	 * @param WP_REST_Request $request The REST request object containing the meta data.
	 * @return Certificate The updated certificate object.
	 *
	 * @since 1.0.0
	 */
	protected function set_certificate_meta( $certificate, $request ) {
		if ( isset( $request['contents'] ) ) {
			$certificate->set_contents( $request['contents'] );
		}

		if ( isset( $request['html_contents'] ) ) {
			$certificate->set_html_contents( $request['html_contents'] );
		}

		if ( isset( $request['courses'] ) ) {
			$certificate->set_courses( $request['courses'] );
		}

		// Handle template thumbnail from setup wizard.
		if ( isset( $request['template_thumbnail'] ) && ! empty( $request['template_thumbnail'] ) ) {
			$thumbnail_id = $this->upload_template_thumbnail( $request['template_thumbnail'], $certificate->get_id() );
			if ( $thumbnail_id ) {
				$certificate->set_thumbnail_image( $thumbnail_id );
			}
		}

		return $certificate;
	}

	/**
	 * Upload template thumbnail from URL to media library
	 *
	 * @param string $image_url The URL of the template thumbnail.
	 * @param int    $post_id The certificate post ID.
	 * @return int|false The attachment ID or false on failure
	 */
	private function upload_template_thumbnail( $image_url, $post_id ) {
		// Load required WordPress admin files.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Check if image URL is valid.
		if ( empty( $image_url ) ) {
			return false;
		}

		// Convert relative URL to absolute if needed.
		if ( strpos( $image_url, 'http' ) !== 0 ) {
			$image_url = site_url( $image_url );
		}

		// Get the file name from URL.
		$filename = basename( $image_url );

		// Download the image.
		$tmp = download_url( $image_url );
		if ( is_wp_error( $tmp ) ) {
			return false;
		}

		// Set up the array of supported file types.
		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $tmp,
		);

		// Do the validation and storage.
		$attachment_id = media_handle_sideload( $file_array, $post_id );

		// If error storing permanently, unlink.
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $file_array['tmp_name'] );
			return false;
		}

		return $attachment_id;
	}


	/**
	 * Saves a certificate to the database.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return int
	 *
	 * @since 1.0.0
	 */
	public function save_certificate( $request ) {
		$certificate = $this->prepare_item_for_database( $request );
		return $certificate->save();
	}


	/**
	 * Prepare a single certificate for create or update.
	 *
	 * @param mixed $request REST request.
	 * @return bool|Certificate|object|WP_Error
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	protected function prepare_item_for_database( $request ) {
		$id = isset( $request['id'] ) ? absint( $request['id'] ) : 0;

		if ( 0 < $id ) {
			$certificate = ohmylms_get_certificate( $id );
		} else {
			$certificate = new Certificate();
		}

		if ( isset( $request['name'] ) ) {
			$certificate->set_name( wp_filter_post_kses( $request['name'] ) );
		}

		if ( isset( $request['status'] ) ) {
			$certificate->set_status( get_post_status_object( $request['status'] ) ? $request['status'] : 'draft' );
		}

		if ( isset( $request['thumbnail_id'] ) ) {
			$certificate->set_thumbnail_image( $request['thumbnail_id'] );
		}

		return $certificate;
	}

	/**
	 * Prepares the query arguments for fetching items.
	 *
	 * This function filters and constructs the query arguments based on the allowed query variables.
	 * It ensures that only valid query variables are included in the final query arguments.
	 *
	 * @param array                $prepared_args The prepared arguments for the query.
	 * @param WP_REST_Request|null $request The REST request object.
	 * .
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
				$query_args[ $var ] = apply_filters( "creatolr_lms_rest_query_var-{$var}", $prepared_args[ $var ] );
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
	 * Delete bulk certificates.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response The response object indicating success or failure.
	 *
	 * @since 1.0.0
	 */
	public function trash_bulk( $request ) {
		$certificate_ids = $request->get_param( 'certificate_ids' );
		if ( is_array( $certificate_ids ) ) {
			foreach ( $certificate_ids as $certificate_id ) {
				if ( get_post_type( $certificate_id ) !== 'ohmylms-certificate' ) {
					return new \WP_REST_Response( array( 'message' => 'Invalid certificate ID.' ), 400 );
				}
				wp_trash_post( $certificate_id );
				do_action( 'ohmylms_rest_delete_certificate', $certificate_id );
			}
			return new \WP_REST_Response( array( 'message' => 'Deleted Successfully' ), 200 );
		}
		return new \WP_REST_Response( array( 'message' => 'Failed to trash the certificate.' ), 500 );
	}


	/**
	 * Get the allowed query variables for the REST API.
	 *
	 * This method retrieves the list of query variables that are allowed to be used
	 * in REST API requests for certificates. It merges the public and private query variables
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

		$post_type_obj = get_post_type_object( OHMYLMS_CERTIFICATE_CPT );
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
		 * that can be used in REST API requests for certificates.
		 *
		 * @param array $valid_vars The array of valid query variables.
		 */
		$valid_vars = apply_filters( 'ohmylms_rest_query_vars', $valid_vars );

		return $valid_vars;
	}


	/**
	 * Prepare a single certificate for response.
	 *
	 * @param \WP_Post         $post The post object.
	 * @param \WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 *
	 * @since 1.0.0
	 */
	public function prepare_item_for_response( $post, $request ) {

		$certificate = ohmylms_get_certificate( $post );
		$data        = $this->get_certificate_data( $certificate );
		$response    = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $certificate, $request ) );

		/**
		 * Filters the response for the certificate in the REST API.
		 *
		 * This filter allows developers to modify the certificate response data before it is returned by the REST API.
		 *
		 * @param array $response The response data for the certificate.
		 * @param \WP_Post $post The WP_Post object representing the certificate.
		 * @param \WP_REST_Request $request The request object containing information about the API request.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'ohmylms_rest_prepare_certificate', $response, $post, $request );
	}


	/**
	 * Prepare a single certificate for response.
	 *
	 * @param \WP_Post         $post The post object.
	 * @param \WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 *
	 * @since 1.0.0
	 */
	public function prepare_item_for_response_with_contents( $post, $request ) {

		$certificate      = ohmylms_get_certificate( $post );
		$data             = $this->get_certificate_data( $certificate );
		$data['contents'] = $certificate->get_contents();
		$response         = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $certificate, $request ) );

		/**
		 * Filters the response for the certificate in the REST API.
		 *
		 * This filter allows developers to modify the certificate response data before it is returned by the REST API.
		 *
		 * @param array $response The response data for the certificate.
		 * @param \WP_Post $post The WP_Post object representing the certificate.
		 * @param \WP_REST_Request $request The request object containing information about the API request.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'ohmylms_rest_prepare_certificate', $response, $post, $request );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @param mixed $certificate Certificate.
	 * @param mixed $request REST request.
	 * @return array[]
	 *
	 * @since 1.0.0
	 */
	protected function prepare_links( $certificate, $request ) {
		$links = array(
			'self'       => array(
				'href' => rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->base, $certificate->get_id() ) ),
			),
			'collection' => array(
				'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->base ) ),
			),
		);

		return $links;
	}


	/**
	 * Get certificate data.
	 *
	 * @param Certificate $certificate Certificate.
	 * @return array
	 *
	 * @since 1.0.0
	 */
	protected function get_certificate_data( $certificate ) {
		$data = array(
			'id'           => $certificate->get_id(),
			'name'         => $certificate->get_name(),
			'slug'         => $certificate->get_slug(),
			'status'       => $certificate->get_status(),
			'thumbnail_id' => $certificate->get_thumbnail_id(),
			'courses'      => $certificate->get_courses(),
			'course_count' => count( $certificate->get_courses() ),
			'image_src'    => wp_get_attachment_image_src( $certificate->get_thumbnail_id(), 'large' ) ? wp_get_attachment_image_src( $certificate->get_thumbnail_id(), 'large' )[0] : '',
			'date_created' => $certificate->get_date_created(),
		);
		return $data;
	}
}
