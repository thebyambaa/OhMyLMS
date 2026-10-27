<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Controller for handling assignment REST API endpoints.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Data\Assignment;
use OhMyLMS\DataException;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use WP_HTTP_Response;
use WP_Query;

/**
 * Controller for handling assignment REST API endpoints.
 *
 * This class extends the RESTController abstract class and defines REST API routes
 * for assignment-related CRUD operations and many more.
 *
 * @since 1.0.0
 */
class AssignmentController extends RestController {

	/**
	 * The base route for assignment base endpoints.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $base = 'assignment';

	/**
	 * Check if the current user has permission to edit posts.
	 *
	 * This function checks if the current user has the 'edit_posts' capability.
	 *
	 * @return bool True if the user has the 'edit_posts' capability, false otherwise.
	 * @since 1.0.0
	 */
	public function check_assignment_permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Permission check for reading a single assignment.
	 *
	 * @since 1.2.20
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return true|\WP_Error
	 */
	public function check_assignment_read_permission( $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error( 'ohmylms_rest_forbidden', __( 'Sorry, you are not allowed to manage this resource.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return $this->check_object_permission( $request, 'read', 'ohmylms-assignment' );
	}

	/**
	 * Permission check for editing a single assignment.
	 *
	 * @since 1.2.20
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return true|\WP_Error
	 */
	public function check_assignment_edit_permission( $request ) {
		return $this->check_object_permission( $request, 'edit', 'ohmylms-assignment' );
	}

	/**
	 * Permission check for deleting a single assignment.
	 *
	 * @since 1.2.20
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return true|\WP_Error
	 */
	public function check_assignment_delete_permission( $request ) {
		return $this->check_object_permission( $request, 'delete', 'ohmylms-assignment' );
	}


	/**
	 * Registers REST API routes for assignment operations.
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
					'permission_callback' => array( $this, 'check_assignment_permission' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_assignment_permission' ),
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
					'permission_callback' => array( $this, 'check_assignment_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the assignment.', 'ohmylms' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'check_assignment_edit_permission' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_assignment_delete_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'check_assignment_read_permission' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)/report',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the assignment.', 'ohmylms' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_assignment_report' ),
					'permission_callback' => array( $this, 'check_assignment_edit_permission' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)/report/(?P<user_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_attempt_report' ),
					'permission_callback' => array( $this, 'check_assignment_edit_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_attempt_report' ),
					'permission_callback' => array( $this, 'check_assignment_edit_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/content/save/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_assignment_content' ),
					'permission_callback' => array( $this, 'check_assignment_edit_permission' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);
	}

	/**
	 * Get collection of assignments.
	 *
	 * This method handles the retrieval of assignments based on the provided request parameters.
	 * It supports various filters and pagination options to customize the query.
	 *
	 * @param \WP_REST_Request $request The REST request object containing query parameters.
	 * @return WP_Error|\WP_HTTP_Response|\WP_REST_Response The response object containing the assignments data or an error.
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
			'post_type'           => OHMYLMS_ASSIGNMENT_CPT,
			'post_status'         => isset( $request['post_status'] ) ? sanitize_text_field( $request['post_status'] ) : array( 'draft', 'publish', 'future' ),
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

		if ( 'any' === $args['post_status'] || ! in_array( $args['post_status'], array( 'draft', 'publish', 'future' ) ) ) {
			$args['post_status'] = array( 'draft', 'publish', 'future' );
		}

		$args       = apply_filters( 'ohmylms_rest_ohmylms_assignment_query', $args, $request );
		$query_args = $this->prepare_items_query( $args, $request );

		$posts_query  = new WP_Query();
		$query_result = $posts_query->query( $query_args );

		$posts = array();
		foreach ( $query_result as $post ) {
			if ( ! current_user_can( 'read_post', $post->ID ) ) {
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
		$response->header( 'X-WP-NoOfFilteredAssignments', (int) $total_filtered_posts );

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
	 * Add assignment
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function create_item( $request ) {
		if ( ! empty( $request['id'] ) ) {
			// Translators: %s is replaced with error name.
			return new WP_Error( 'ohmylms_rest_assignment_exists', sprintf( __( 'Cannot create existing %s.', 'ohmylms' ), 'Assignment' ), array( 'status' => 400 ) );
		}
		try {
			$assignment_id = $this->save_assignment( $request );

			$post = get_post( $assignment_id );
			/**
			 * Fires after a Assignment is inserted via the REST API.
			 *
			 * @param \WP_Post         $post    The post object for the assignment.
			 * @param \WP_REST_Request $request The request object.
			 * @param bool             $creating Whether the assignment is being created (true) or updated (false).
			 *
			 * @since 1.0.0
			 */
			do_action( 'ohmylms_rest_insert_assignment', $post, $request, true );

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
	 * Update assignment settings
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function update_item( $request ) {

		$post_id = (int) $request['id'];

		if ( empty( $post_id ) || get_post_type( $post_id ) !== OHMYLMS_ASSIGNMENT_CPT ) {
			return new WP_Error( 'ohmylms_rest_assignment_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		try {
			$assignment_id = $this->save_assignment( $request );
			$post          = get_post( $assignment_id );
			$this->update_additional_fields_for_object( $post, $request );
			$this->update_post_meta_fields( $post, $request );
			$request->set_param( 'context', 'edit' );
			$response = $this->prepare_item_for_response( $post, $request );
			return rest_ensure_response( $response );

		} catch ( DataException $e ) {
			return new WP_Error( $e->getErrorCode(), $e->getMessage(), $e->getErrorData() );
		}
	}


	/**
	 * Delete bulk assignments.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response The response object indicating success or failure.
	 *
	 * @since 1.0.0
	 */
	public function trash_bulk( $request ) {
		$assignment_ids = $request->get_param( 'assignment_ids' );
		if ( is_array( $assignment_ids ) ) {
			$assignment_ids = $this->filter_allowed_post_ids( $assignment_ids, 'delete', 'ohmylms-assignment' );

			if ( is_wp_error( $assignment_ids ) ) {
				return $assignment_ids;
			}

			foreach ( $assignment_ids as $assignment_id ) {
				wp_trash_post( $assignment_id );
				do_action( 'ohmylms_rest_delete_assignment', $assignment_id );
			}
			return new \WP_REST_Response( array( 'message' => 'Deleted Successfully' ), 200 );
		}
		return new \WP_REST_Response( array( 'message' => 'Failed to trash the assignment.' ), 500 );
	}


	/**
	 * Update post meta fields for a assignment.
	 *
	 * This method updates the meta fields for a given assignment post based on the provided request data.
	 *
	 * @param \WP_Post         $post The post object representing the assignment.
	 * @param \WP_REST_Request $request The REST request object containing the meta data.
	 * @return bool True on success, false on failure.
	 *
	 * @throws DataException When the operation cannot be completed.
	 * @since 1.0.0
	 */
	protected function update_post_meta_fields( $post, $request ) {
		$assignment = ohmylms_get_assignment( $post );

		// Save assignment meta fields.
		$assignment = $this->set_assignment_meta( $assignment, $request );
		// Save the assignment data.
		$assignment->save();

		/**
		 * Fires after the meta data for a assignment is updated.
		 *
		 * @param WP_Post $assignment The updated assignment object.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_rest_assignment_meta_updated', $assignment );

		return true;
	}


	/**
	 * Set the cover image for a assignment.
	 *
	 * @param assignment $assignment The Assignment object.
	 * @param int        $attachment_id The attachment ID of the image.
	 * @return assignment The updated assignment object.
	 * @throws DataException If the attachment ID is not a valid image.
	 *
	 * @since 1.0.0
	 */
	protected function set_assignment_cover_image( $assignment, $attachment_id ) {
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			// Translators: %s is replaced with error name.
			throw new DataException( 'ohmylms_assignment_invalid_cover_image_id', sprintf( __( '#%s is an invalid image ID.', 'ohmylms' ), $attachment_id ), 400 );
		}

		$assignment->set_cover_image_id( $attachment_id );

		return $assignment;
	}
	/**
	 * Set the cover image for a assignment.
	 *
	 * @param assignment $assignment The Assignment object.
	 * @param int        $attachment_id The attachment ID of the image.
	 * @return assignment The updated assignment object.
	 * @throws DataException If the attachment ID is not a valid image.
	 *
	 * @since 1.0.0
	 */
	protected function set_assignment_thumbnail_id( $assignment, $attachment_id ) {

		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			// Translators: %s is replaced with error name.
			throw new DataException( 'ohmylms_assignment_invalid_thumbnail_id', sprintf( __( '#%s is an invalid image ID.', 'ohmylms' ), $attachment_id ), 400 );
		}

		$assignment->set_thumbnail_id( $attachment_id );

		return $assignment;
	}
	/**
	 * Set the cover image for a assignment.
	 *
	 * @param assignment $assignment The Assignment object.
	 * @param int        $attachment_id The attachment ID of the image.
	 * @return assignment The updated assignment object.
	 * @throws DataException If the attachment ID is not a valid image.
	 *
	 * @since 1.0.0
	 */
	protected function set_assignment_video_id( $assignment, $attachment_id ) {
		if ( ! wp_attachment_is( 'video', $attachment_id ) ) {
			// Translators: %s is replaced with error name.
			throw new DataException( 'ohmylms_assignment_invalid_video_id', sprintf( __( '#%s is an invalid image ID.', 'ohmylms' ), $attachment_id ), 400 );
		}

		$assignment->set_video_id( $attachment_id );

		return $assignment;
	}
	/**
	 * Set the cover image for a assignment.
	 *
	 * @param assignment $assignment The Assignment object.
	 * @param int        $attachment_id The attachment ID of the image.
	 * @return assignment The updated assignment object.
	 * @throws DataException If the attachment ID is not a valid image.
	 *
	 * @since 1.0.0
	 */
	protected function set_assignment_audio_id( $assignment, $attachment_id ) {
		if ( ! wp_attachment_is( 'audio', $attachment_id ) ) {
			// Translators: %s is replaced with error name.
			throw new DataException( 'ohmylms_assignment_invalid_audio_id', sprintf( __( '#%s is an invalid image ID.', 'ohmylms' ), $attachment_id ), 400 );
		}

		$assignment->set_audio_id( $attachment_id );

		return $assignment;
	}

	/**
	 * Set product meta data for a assignment.
	 *
	 * @param Assignment      $assignment The assignment object.
	 * @param WP_REST_Request $request The REST request object containing the meta data.
	 * @return Assignment The updated assignment object.
	 *
	 * @since 1.0.0
	 */
	protected function set_assignment_meta( $assignment, $request ) {
		if ( isset( $request['type'] ) ) {
			$assignment->set_type( $request['type'] );
		}
		if ( isset( $request['content'] ) ) {
			$assignment->set_status( $request['content'] );
		}
		if ( isset( $request['enable_comments'] ) ) {
			$assignment->set_enable_comments( $request['enable_comments'] );
		}
		if ( isset( $request['download_resource'] ) ) {
			$assignment->set_download_resource( $request['download_resource'] );
		}
		if ( isset( $request['prerequisites'] ) ) {
			$assignment->set_prerequisites( $request['prerequisites'] );
		}
		if ( isset( $request['enable_time_limit'] ) ) {
			$assignment->set_enable_time_limit( $request['enable_time_limit'] );
		}
		if ( isset( $request['time_limit'] ) ) {
			$assignment->set_time_limit( $request['time_limit'] );
		}
		if ( isset( $request['time_limit_type'] ) ) {
			$assignment->set_time_limit_type( $request['time_limit_type'] );
		}
		if ( isset( $request['total_points'] ) ) {
			$assignment->set_total_points( $request['total_points'] );
		}

		if ( isset( $request['maximum_pass_points'] ) ) {
			$assignment->set_maximum_pass_points( $request['maximum_pass_points'] );
		}

		if ( isset( $request['allow_upload_files'] ) ) {
			$assignment->set_allow_upload_files( $request['allow_upload_files'] );
		}

		if ( isset( $request['number_of_files'] ) ) {
			$assignment->set_number_of_files( $request['number_of_files'] );
		}
		if ( isset( $request['enable_file_size_limit'] ) ) {
			$assignment->set_enable_file_size_limit( $request['enable_file_size_limit'] );
		}
		if ( isset( $request['max_file_size_limit'] ) ) {
			$assignment->set_max_file_size_limit( $request['max_file_size_limit'] );
		}
		if ( isset( $request['drip_settings'] ) ) {
			$assignment->set_drip_settings( $request['drip_settings'] );
		}

		return $assignment;
	}


	/**
	 * Save assignment settings
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function save_assignment_content( $request ) {
		$params  = $request->get_json_params();
		$post_id = (int) $request['id'];

		$response = LessonHelper::save_assignment( $post_id, $params );

		return rest_ensure_response( $response );
	}

	/**
	 * Get assignment content
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_item( $request ) {
		$id   = (int) $request['id'];
		$post = get_post( $id );
		if ( empty( $id ) || empty( $post->ID ) || OHMYLMS_ASSIGNMENT_CPT !== $post->post_type ) {
			return new WP_Error( 'ohmylms_rest_invalid_assignment_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		$data     = $this->prepare_item_for_response( $post, $request );
		$response = rest_ensure_response( $data );

		$response->link_header( 'alternate', get_permalink( $id ), array( 'type' => 'text/html' ) );

		return $response;

		return rest_ensure_response( $response );
	}
	/**
	 * Get assignment content
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_assignment_report( $request ) {
		$id   = (int) $request['id'];
		$post = get_post( $id );
		if ( empty( $id ) || empty( $post->ID ) || OHMYLMS_ASSIGNMENT_CPT !== $post->post_type ) {
			return new WP_Error( 'ohmylms_rest_invalid_assignment_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		$assignemnt = ohmylms_get_assignment( $post->ID );

		$report   = $assignemnt->get_report();
		$response = rest_ensure_response( $report );

		$response->link_header( 'alternate', get_permalink( $id ), array( 'type' => 'text/html' ) );

		return $response;

		return rest_ensure_response( $response );
	}


	/**
	 * Get attempt report for Assignment controller.
	 *
	 * @param mixed $request REST request.
	 */
	public function get_attempt_report( $request ) {
		$id         = (int) $request['id'];
		$attempt_id = (int) $request['user_id'];
		$post       = get_post( $id );
		if ( empty( $id ) || empty( $post->ID ) || OHMYLMS_ASSIGNMENT_CPT !== $post->post_type ) {
			return new WP_Error( 'ohmylms_rest_invalid_assignemnt_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$assignment           = ohmylms_get_assignment( $post->ID );
		$get_assignment_title = $assignment->get_assignment_title();
		$report               = $assignment->get_assignment_attempts( $attempt_id );
		$data                 = array(
			'report' => $report,
		);
		$course_id            = ohmylms_get_course_by_content_id( $post->ID );
		$pass_marks           = get_post_meta( $post->ID, '_maximum_pass_points', true );
		$total_marks          = get_post_meta( $post->ID, '_total_points', true );

		$course = ohmylms_get_course( $course_id );
		if ( $course ) {
			$data['additional_data'] = array(
				'course_id'   => $course->get_id(),
				'course_name' => $course->get_name(),
			);
		}

		$data['additional_data']['total_marks']     = $total_marks;
		$data['additional_data']['pass_marks']      = $pass_marks;
		$data['additional_data']['assignment_name'] = $get_assignment_title;

		$response = rest_ensure_response( $data );

		$response->link_header( 'alternate', get_permalink( $id ), array( 'type' => 'text/html' ) );
		return $response;
	}

	/**
	 * Update attempt report for Assignment controller.
	 *
	 * @param mixed $request REST request.
	 */
	public function update_attempt_report( $request ) {
		$id         = (int) $request['id'];
		$attempt_id = (int) $request['user_id'];

		$post = get_post( $id );
		if ( empty( $id ) || empty( $post->ID ) || OHMYLMS_ASSIGNMENT_CPT !== $post->post_type ) {
			return new WP_Error( 'ohmylms_rest_invalid_assignemnt_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		$get_data = $request->get_json_params();

		$quiz                     = ohmylms_get_assignment( $post->ID );
		$pass_marks               = get_post_meta( $post->ID, '_maximum_pass_points', true );
		$course_id                = ohmylms_get_course_by_content_id( $post->ID );
		$previous_completion_rate = 0;
		if ( isset( $get_data[0]['score'], $get_data[0]['status'] ) ) {
			if ( $get_data[0]['score'] >= $pass_marks ) {
				$get_data[0]['status'] = 'passed';

				$student = new \OhMyLMS\Data\Student( $attempt_id );
				if ( $student ) {
					$previous_completion_rate = $student->get_over_all_completion_rate( $course_id );
					$student->complete_lesson( $post->ID, $course_id );
				}
			} else {
				$get_data[0]['status'] = 'failed';
			}
		}
		$quiz->update_assignment_attempts( $attempt_id, $get_data );

		$report = $quiz->get_assignment_attempts( $attempt_id );

		$data = array(
			'report' => $report,
		);

		$total_marks = get_post_meta( $post->ID, '_total_points', true );

		$course                  = ohmylms_get_course( $course_id );
		$data['additional_data'] = array(
			'course_id'   => $course->get_id(),
			'course_name' => $course->get_name(),
		);

		$data['additional_data']['total_marks'] = $total_marks;
		$data['additional_data']['pass_marks']  = $pass_marks;
		$response                               = rest_ensure_response( $data );

		$response->link_header( 'alternate', get_permalink( $id ), array( 'type' => 'text/html' ) );

		do_action( 'ohmylms_after_assignment_review', $post->ID, $course_id, $attempt_id, $get_data[0]['status'] );

		if ( class_exists( '\OhMyLMS\Engagement\Leaderboard' ) ) {
			$avg_marks = \OhMyLMS\Engagement\Leaderboard::get_student_avg_assignment_marks( $attempt_id, $post->ID );
			do_action( 'ohmylms_pro_after_assignment_review', $post->ID, $course_id, $attempt_id, $avg_marks );
		}
		$student                 = new \OhMyLMS\Data\Student( $attempt_id );
		$maybe_course_completion = $student && $student->is_course_completed( $course_id ) ? 'yes' : 'no';

		if ( 'yes' === $maybe_course_completion ) {
			do_action( 'ohmylms_student_completed_course_after_reviewing_assignment', $attempt_id, $course_id );
		}

		$completion_rate = $student->get_over_all_completion_rate( $course_id );
		if ( (int) ( $completion_rate ) === 100 && (int) ( $previous_completion_rate ) !== 100 && ! \OhMyLMS\Learning\CourseProgram::managed( $attempt_id, $course_id ) ) {
			global $wpdb;
			$table_name  = $wpdb->prefix . 'ohmylms_user_enrollment';
			$enroll_data = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d AND course_id = %d', $table_name, $attempt_id, $course_id ), ARRAY_A );
			if ( isset( $enroll_data['order_id'] ) ) {
				do_action( 'ohmylms_course_completed', $attempt_id, $course_id, $enroll_data['order_id'] );
			}
		}

		return $response;
	}

	/**
	 * Delete a single assignment.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 *
	 * @since 1.0.0
	 */
	public function delete_item( $request ) {
		$assignment_id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( ! $assignment_id ) {
			return new WP_Error( 'ohmylms_rest_assignment_empty_id', __( 'ID is required.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$assignment = ohmylms_get_assignment( $assignment_id );

		if ( ! ( $assignment instanceof Assignment ) ) {
			return new WP_Error( 'ohmylms_rest_assignment_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$assignment->delete();

		/**
		 * Executes the 'ohmylms_rest_delete_assignment' action hook.
		 * This hook is triggered when a assignment is being deleted via the REST API.
		 *
		 * @param array $request The request array.
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_rest_delete_assignment', $assignment_id );

		$response = array(
			'id'      => $assignment_id,
			'status'  => 'success',
			'message' => __( 'Assignment has been deleted successfully.', 'ohmylms' ),
		);
		return rest_ensure_response( $response );
	}

	/**
	 * Save assignment
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return bool
	 * @since 1.0.0
	 */
	public function save_assignment( $request ) {
		$assignment = $this->prepare_item_for_database( $request );
		return $assignment->save();
	}

	/**
	 * Prepare a assignment for database.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return Assignment
	 *
	 * @since 1.0.0
	 */
	protected function prepare_item_for_database( $request ) {
		$id = isset( $request['id'] ) ? absint( $request['id'] ) : 0;

		if ( isset( $request['id'] ) ) {
			$assignment = ohmylms_get_assignment( $id );
		} else {
			$assignment = new Assignment();
		}

		if ( isset( $request['name'] ) ) {
			$assignment->set_name( wp_filter_post_kses( $request['name'] ) );
		}
		if ( isset( $request['type'] ) ) {
			$assignment->set_type( $request['type'] );
		}

		if ( isset( $request['description'] ) ) {
			$assignment->set_description( $request['description'] );
		}

		if ( isset( $request['slug'] ) ) {
			$assignment->set_slug( wp_filter_post_kses( $request['slug'] ) );
		}

		if ( isset( $request['status'] ) ) {
			$assignment->set_status( get_post_status_object( $request['status'] ) ? $request['status'] : 'draft' );
		}
		return $assignment;
	}


	/**
	 * Get assignment data.
	 *
	 * @param Assignment $assignment Assignment.
	 * @return array
	 *
	 * @since 1.0.0
	 */
	protected function get_assignment_data( $assignment ) {
		$course_id = ohmylms_get_course_by_content_id( $assignment->get_id() );
		$courses   = array();
		if ( $course_id ) {
			$course = ohmylms_get_course( $course_id );
			if ( $course ) {
				$courses['id']          = $course_id;
				$courses['course_name'] = $course->get_name();
			}
		}
		$report                = $assignment->get_report();
		$number_of_submissions = 0;
		if ( is_array( $report ) ) {
			$number_of_submissions = count( $report );
		}

		$data = array(
			'id'                     => $assignment->get_id(),
			'name'                   => $assignment->get_name(),
			'slug'                   => $assignment->get_slug(),
			'status'                 => $assignment->get_status(),
			'type'                   => $assignment->get_type(),
			'description'            => $assignment->get_description(),
			'content'                => $assignment->get_content(),
			'preview_url'            => $assignment->get_permalink(),
			'enable_comments'        => $assignment->get_enable_comments(),
			'download_resource'      => $assignment->get_download_resource(),
			'prerequisites'          => $assignment->get_prerequisites(),
			'date_created'           => $assignment->get_date_created(),
			'date_modified'          => $assignment->get_date_modified(),
			'enable_time_limit'      => $assignment->get_enable_time_limit(),
			'time_limit'             => $assignment->get_time_limit(),
			'time_limit_type'        => $assignment->get_time_limit_type(),
			'total_points'           => $assignment->get_total_points(),
			'maximum_pass_points'    => $assignment->get_maximum_pass_points(),
			'allow_upload_files'     => $assignment->get_allow_upload_files(),
			'number_of_files'        => $assignment->get_number_of_files(),
			'enable_file_size_limit' => $assignment->get_enable_file_size_limit(),
			'max_file_size_limit'    => $assignment->get_max_file_size_limit(),
			'drip_settings'          => $assignment->get_drip_settings(),
			'courses'                => $courses,
			'number_of_submissions'  => $number_of_submissions,
		);

		return $data;
	}


	/**
	 * Prepare a single assignment for response.
	 *
	 * @param \WP_Post         $post The post object.
	 * @param \WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 *
	 * @since 1.0.0
	 */
	public function prepare_item_for_response( $post, $request ) {
		$assignment = ohmylms_get_assignment( $post->ID );
		$data       = $this->get_assignment_data( $assignment );
		$response   = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $assignment, $request ) );

		/**
		 * Filters the response for the assignment in the REST API.
		 *
		 * This filter allows developers to modify the assignment response data before it is returned by the REST API.
		 *
		 * @param array $response The response data for the assignment.
		 * @param \WP_Post $post The WP_Post object representing the assignment.
		 * @param \WP_REST_Request $request The request object containing information about the API request.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'ohmylms_rest_prepare_assignment', $response, $post, $request );
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
				$query_args[ $var ] = apply_filters( "woocommerce_rest_query_var-{$var}", $prepared_args[ $var ] );
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
	 * in REST API requests for assignments. It merges the public and private query variables
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

		$post_type_obj = get_post_type_object( OHMYLMS_ASSIGNMENT_CPT );
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
		 * that can be used in REST API requests for assignments.
		 *
		 * @param array $valid_vars The array of valid query variables.
		 */
		$valid_vars = apply_filters( 'ohmylms_rest_query_vars', $valid_vars );

		return $valid_vars;
	}
}
