<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Controller for handling lesson REST API endpoints.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Data\Lesson;
use OhMyLMS\DataException;
use OhMyLMS\Lesson\LessonHelper;
use OhMyLMS\Lesson\LessonValidator;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use WP_HTTP_Response;
use WP_Query;

/**
 * Controller for handling lesson REST API endpoints.
 *
 * This class extends the RESTController abstract class and defines REST API routes
 * for lesson-related CRUD operations and many more.
 *
 * @since 1.0.0
 */
class LessonController extends RestController {

	/**
	 * The base route for lesson base endpoints.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $base = 'lessons';

	/**
	 * Check if the current user has permission to edit posts.
	 *
	 * This function checks if the current user has the 'edit_posts' capability.
	 *
	 * @return bool True if the user has the 'edit_posts' capability, false otherwise.
	 * @since 1.0.0
	 */
	public function check_lesson_permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Registers REST API routes for lesson operations.
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
					'permission_callback' => array( $this, 'check_lesson_permission' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_lesson_permission' ),
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
					'permission_callback' => array( $this, 'check_lesson_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the lesson.', 'ohmylms' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'check_lesson_permission' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_lesson_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'check_lesson_permission' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/content/save/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_lesson_content' ),
					'permission_callback' => array( $this, 'check_lesson_permission' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);
	}

	/**
	 * Get collection of lessons.
	 *
	 * This method handles the retrieval of lessons based on the provided request parameters.
	 * It supports various filters and pagination options to customize the query.
	 *
	 * @param \WP_REST_Request $request The REST request object containing query parameters.
	 * @return WP_Error|\WP_HTTP_Response|\WP_REST_Response The response object containing the lessons data or an error.
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
			'post_type'           => OHMYLMS_LESSON_CPT,
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

		$args       = apply_filters( 'ohmylms_rest_ohmylms_lesson_query', $args, $request );
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
	 * Add lesson
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function create_item( $request ) {
		if ( ! empty( $request['id'] ) ) {
			// Translators: %s is replaced with object name.
			return new WP_Error( 'ohmylms_rest_lesson_exists', sprintf( __( 'Cannot create existing %s.', 'ohmylms' ), 'Lesson' ), array( 'status' => 400 ) );
		}
		try {
			$lesson_id = $this->save_lesson( $request );

			$post = get_post( $lesson_id );
			/**
			 * Fires after a Lesson is inserted via the REST API.
			 *
			 * @param \WP_Post         $post    The post object for the lesson.
			 * @param \WP_REST_Request $request The request object.
			 * @param bool             $creating Whether the lesson is being created (true) or updated (false).
			 *
			 * @since 1.0.0
			 */
			do_action( 'ohmylms_rest_insert_lesson', $post, $request, true );

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
	 * Update lesson settings
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function update_item( $request ) {

		$post_id = (int) $request['id'];

		if ( empty( $post_id ) || get_post_type( $post_id ) !== OHMYLMS_LESSON_CPT ) {
			return new WP_Error( 'ohmylms_rest_lesson_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		try {
			$lesson_id = $this->save_lesson( $request );
			$post      = get_post( $lesson_id );
			$this->update_additional_fields_for_object( $post, $request );
			$this->update_post_meta_fields( $post, $request );
			$request->set_param( 'context', 'edit' );
			if ( isset( $request['status'] ) ) {
				$lesson = ohmylms_get_lesson( $lesson_id );
				$lesson->set_status( get_post_status_object( $request['status'] ) ? $request['status'] : 'draft' );
				$lesson->save();
			}

			$response = $this->prepare_item_for_response( $post, $request );
			return rest_ensure_response( $response );

		} catch ( DataException $e ) {
			return new WP_Error( $e->getErrorCode(), $e->getMessage(), $e->getErrorData() );
		}
	}


	/**
	 * Delete bulk lessons.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response The response object indicating success or failure.
	 *
	 * @since 1.0.0
	 */
	public function trash_bulk( $request ) {
		$lesson_ids = $request->get_param( 'lesson_ids' );
		if ( is_array( $lesson_ids ) ) {
			foreach ( $lesson_ids as $lesson_id ) {
				if ( get_post_type( $lesson_id ) !== 'ohmylms-lesson' ) {
					return new \WP_REST_Response( array( 'message' => 'Invalid lesson ID.' ), 400 );
				}
				wp_trash_post( $lesson_id );
				do_action( 'ohmylms_rest_delete_lesson', $lesson_id );
			}
			return new \WP_REST_Response( array( 'message' => 'Deleted Successfully' ), 200 );
		}
		return new \WP_REST_Response( array( 'message' => 'Failed to trash the lesson.' ), 500 );
	}


	/**
	 * Update post meta fields for a lesson.
	 *
	 * This method updates the meta fields for a given lesson post based on the provided request data.
	 *
	 * @param \WP_Post         $post The post object representing the lesson.
	 * @param \WP_REST_Request $request The REST request object containing the meta data.
	 * @return bool True on success, false on failure.
	 *
	 * @throws DataException When the operation cannot be completed.
	 * @since 1.0.0
	 */
	protected function update_post_meta_fields( $post, $request ) {
		$lesson = ohmylms_get_lesson( $post );
		if ( isset( $request['image_id'] ) && ! empty( $request['image_id'] ) ) {
			$lesson = $this->set_lesson_cover_image( $lesson, $request['image_id'] );
		} else {
			$lesson->set_cover_image_id( '' );
		}
		if ( isset( $request['thumbnail_id'] ) && ! empty( $request['thumbnail_id'] ) ) {
			$lesson = $this->set_lesson_thumbnail_id( $lesson, $request['thumbnail_id'] );
		} else {
			$lesson->set_thumbnail_id( '' );
		}
		if ( isset( $request['video_id'] ) && ! empty( $request['video_id'] ) ) {
			$lesson = $this->set_lesson_video_id( $lesson, $request['video_id'] );
		} else {
			$lesson->set_video_id( '' );
		}
		if ( isset( $request['audio_id'] ) && ! empty( $request['audio_id'] ) ) {
			$lesson = $this->set_lesson_audio_id( $lesson, $request['audio_id'] );
		} else {
			$lesson->set_audio_id( '' );
		}

		// Save lesson meta fields.
		$lesson = $this->set_lesson_meta( $lesson, $request );
		$lesson->save();
		/**
		 * Fires after the meta data for a lesson is updated.
		 *
		 * @param WP_Post $lesson The updated lesson object.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_rest_lesson_meta_updated', $lesson );

		return $lesson;
	}


	/**
	 * Set the cover image for a lesson.
	 *
	 * @param lesson $lesson The Lesson object.
	 * @param int    $attachment_id The attachment ID of the image.
	 * @return lesson The updated lesson object.
	 * @throws DataException If the attachment ID is not a valid image.
	 *
	 * @since 1.0.0
	 */
	protected function set_lesson_cover_image( $lesson, $attachment_id ) {
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			// Translators: %s is replaced with error name.
			throw new DataException( 'ohmylms_lesson_invalid_cover_image_id', sprintf( __( '#%s is an invalid image ID.', 'ohmylms' ), $attachment_id ), 400 );
		}

		$lesson->set_cover_image_id( $attachment_id );

		return $lesson;
	}
	/**
	 * Set the cover image for a lesson.
	 *
	 * @param lesson $lesson The Lesson object.
	 * @param int    $attachment_id The attachment ID of the image.
	 * @return lesson The updated lesson object.
	 * @throws DataException If the attachment ID is not a valid image.
	 *
	 * @since 1.0.0
	 */
	protected function set_lesson_thumbnail_id( $lesson, $attachment_id ) {

		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			// Translators: %s is replaced with error name.
			throw new DataException( 'ohmylms_lesson_invalid_thumbnail_id', sprintf( __( '#%s is an invalid image ID.', 'ohmylms' ), $attachment_id ), 400 );
		}

		$lesson->set_thumbnail_id( $attachment_id );

		return $lesson;
	}
	/**
	 * Set the cover image for a lesson.
	 *
	 * @param lesson $lesson The Lesson object.
	 * @param int    $attachment_id The attachment ID of the image.
	 * @return lesson The updated lesson object.
	 * @throws DataException If the attachment ID is not a valid image.
	 *
	 * @since 1.0.0
	 */
	protected function set_lesson_video_id( $lesson, $attachment_id ) {
		if ( ! wp_attachment_is( 'video', $attachment_id ) ) {
			// Translators: %s is replaced with error name.
			throw new DataException( 'ohmylms_lesson_invalid_video_id', sprintf( __( '#%s is an invalid image ID.', 'ohmylms' ), $attachment_id ), 400 );
		}

		$lesson->set_video_id( $attachment_id );

		return $lesson;
	}
	/**
	 * Set the cover image for a lesson.
	 *
	 * @param lesson $lesson The Lesson object.
	 * @param int    $attachment_id The attachment ID of the image.
	 * @return lesson The updated lesson object.
	 * @throws DataException If the attachment ID is not a valid image.
	 *
	 * @since 1.0.0
	 */
	protected function set_lesson_audio_id( $lesson, $attachment_id ) {
		if ( ! wp_attachment_is( 'audio', $attachment_id ) ) {
			// Translators: %s is replaced with error name.
			throw new DataException( 'ohmylms_lesson_invalid_audio_id', sprintf( __( '#%s is an invalid image ID.', 'ohmylms' ), $attachment_id ), 400 );
		}

		$lesson->set_audio_id( $attachment_id );

		return $lesson;
	}

	/**
	 * Set product meta data for a lesson.
	 *
	 * @param Lesson          $lesson The lesson object.
	 * @param WP_REST_Request $request The REST request object containing the meta data.
	 * @return Lesson The updated lesson object.
	 *
	 * @since 1.0.0
	 */
	protected function set_lesson_meta( $lesson, $request ) {
		if ( isset( $request['type'] ) ) {
			$lesson->set_type( $request['type'] );
		}
		if ( isset( $request['content'] ) ) {
			$lesson->set_status( $request['content'] );
		}
		if ( isset( $request['drip_settings'] ) && method_exists( $lesson, 'set_drip_settings' ) ) {
			$lesson->set_drip_settings( $request['drip_settings'] );
		}
		if ( isset( $request['enable_comments'] ) ) {
			$lesson->set_enable_comments( $request['enable_comments'] );
		}
		if ( isset( $request['download_resource'] ) && method_exists( $lesson, 'set_download_resource' ) ) {
			$lesson->set_download_resource( $request['download_resource'] );
		}
		if ( isset( $request['prerequisites'] ) && method_exists( $lesson, 'set_prerequisites' ) ) {
			$lesson->set_prerequisites( $request['prerequisites'] );
		}

		if ( isset( $request['preview_enable'] ) && method_exists( $lesson, 'set_preview_enable' ) ) {
			$lesson->set_preview_enable( $request['preview_enable'] );
		}

		if ( isset( $request['video_settings'] ) && method_exists( $lesson, 'set_video_settings' ) ) {
			$lesson->set_video_settings( $request['video_settings'] );
		}

		if ( $request->has_param( 'external_url' ) ) {
			$lesson->set_external_url( $request->get_param( 'external_url' ) );
		}

		return $lesson;
	}


	/**
	 * Save lesson settings
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function save_lesson_content( $request ) {
		$params  = $request->get_json_params();
		$post_id = (int) $request['id'];

		$response = LessonHelper::save_lesson( $post_id, $params );

		return rest_ensure_response( $response );
	}

	/**
	 * Get lesson content
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_item( $request ) {
		$id   = (int) $request['id'];
		$post = get_post( $id );
		if ( empty( $id ) || empty( $post->ID ) || OHMYLMS_LESSON_CPT !== $post->post_type ) {
			return new WP_Error( 'ohmylms_rest_invalid_lesson_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		$data     = $this->prepare_item_for_response( $post, $request );
		$response = rest_ensure_response( $data );

		$response->link_header( 'alternate', get_permalink( $id ), array( 'type' => 'text/html' ) );

		return $response;

		return rest_ensure_response( $response );
	}

	/**
	 * Delete a single lesson.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 *
	 * @since 1.0.0
	 */
	public function delete_item( $request ) {
		$lesson_id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( ! $lesson_id ) {
			return new WP_Error( 'ohmylms_rest_lesson_empty_id', __( 'ID is required.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$lesson = ohmylms_get_lesson( $lesson_id );

		if ( ! ( $lesson instanceof Lesson ) ) {
			return new WP_Error( 'ohmylms_rest_lesson_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$lesson->delete();

		/**
		 * Executes the 'ohmylms_rest_delete_lesson' action hook.
		 * This hook is triggered when a lesson is being deleted via the REST API.
		 *
		 * @param array $request The request array.
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_rest_delete_lesson', $lesson_id );

		$response = array(
			'id'      => $lesson_id,
			'status'  => 'success',
			'message' => __( 'Lesson has been deleted successfully.', 'ohmylms' ),
		);
		return rest_ensure_response( $response );
	}

	/**
	 * Save lesson
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return bool
	 * @since 1.0.0
	 */
	public function save_lesson( $request ) {
		$lesson = $this->prepare_item_for_database( $request );
		return $lesson->save();
	}

	/**
	 * Prepare a lesson for database.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return Lesson
	 *
	 * @since 1.0.0
	 */
	protected function prepare_item_for_database( $request ) {
		$id = isset( $request['id'] ) ? absint( $request['id'] ) : 0;

		if ( isset( $request['id'] ) ) {
			$lesson = ohmylms_get_lesson( $id );
		} else {
			$lesson = ohmylms_is_pro() ? new \OhMyLMS\Data\Lesson() : new Lesson();
		}

		if ( isset( $request['name'] ) ) {
			$lesson->set_name( wp_filter_post_kses( $request['name'] ) );
		}
		if ( isset( $request['type'] ) ) {
			$lesson->set_type( $request['type'] );
		}
		if ( isset( $request['description'] ) ) {
			$lesson->set_description( $request['description'] );
		}
		if ( isset( $request['slug'] ) ) {
			$lesson->set_slug( wp_filter_post_kses( $request['slug'] ) );
		}
		if ( isset( $request['status'] ) ) {
			$lesson->set_status( get_post_status_object( $request['status'] ) ? $request['status'] : 'draft' );
		}
		return $lesson;
	}


	/**
	 * Get lesson data.
	 *
	 * @param Lesson $lesson Lesson.
	 * @return array
	 *
	 * @since 1.0.0
	 * @param mixed  $request REST request.
	 */
	protected function get_lesson_data( $lesson, $request = null ) {
		if ( $request instanceof WP_REST_Request ) {
			if ( isset( $request['order_number'] ) ) {
				$order_number = intval( $request['order_number'] );
			} else {
				$order_number = $lesson->get_order_number();
			}
		} else {
			$order_number = $lesson->get_order_number();
		}

		$data = array(
			'id'                => $lesson->get_id(),
			'name'              => html_entity_decode( $lesson->get_name() ),
			'slug'              => $lesson->get_slug(),
			'status'            => $lesson->get_status(),
			'type'              => $lesson->get_type(),
			'description'       => $lesson->get_description(),
			'content'           => $lesson->get_content(),
			'preview_url'       => $lesson->get_permalink(),
			'drip_settings'     => method_exists( $lesson, 'get_drip_settings' ) ? $lesson->get_drip_settings() : '',
			'enable_comments'   => $lesson->get_enable_comments(),
			'download_resource' => method_exists( $lesson, 'get_download_resource' ) ? $lesson->get_download_resource() : '',
			'prerequisites'     => method_exists( $lesson, 'get_prerequisites' ) ? $lesson->get_prerequisites() : '',
			'thumbnail_id'      => $lesson->get_thumbnail_id(),
			'thumbnail_src'     => wp_get_attachment_image_src( $lesson->get_thumbnail_id(), 'small' ) ? wp_get_attachment_image_src( $lesson->get_thumbnail_id(), 'small' )[0] : '',
			'image_id'          => $lesson->get_cover_image_id(),
			'image_src'         => wp_get_attachment_image_src( $lesson->get_cover_image_id(), 'large' ) ? wp_get_attachment_image_src( $lesson->get_cover_image_id(), 'large' )[0] : '',
			'date_created'      => $lesson->get_date_created(),
			'date_modified'     => $lesson->get_date_modified(),
			'video_id'          => $lesson->get_video_id(),
			'video_src'         => wp_get_attachment_url( $lesson->get_video_id() ),
			'audio_id'          => $lesson->get_audio_id(),
			'audio_src'         => wp_get_attachment_url( $lesson->get_audio_id() ),
			'external_url'      => $lesson->get_external_url(),
			'video_settings'    => method_exists( $lesson, 'get_video_settings' ) ? $lesson->get_video_settings() : '',
			'order_number'      => $order_number,
			'preview_enable'    => method_exists( $lesson, 'get_preview_enable' ) ? $lesson->get_preview_enable() : false,
		);
		return $data;
	}


	/**
	 * Prepare a single lesson for response.
	 *
	 * @param \WP_Post         $post The post object.
	 * @param \WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 *
	 * @since 1.0.0
	 */
	public function prepare_item_for_response( $post, $request ) {
		$lesson   = ohmylms_get_lesson( $post->ID );
		$data     = $this->get_lesson_data( $lesson, $request );
		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $lesson, $request ) );

		/**
		 * Filters the response for the lesson in the REST API.
		 *
		 * This filter allows developers to modify the lesson response data before it is returned by the REST API.
		 *
		 * @param array $response The response data for the lesson.
		 * @param \WP_Post $post The WP_Post object representing the lesson.
		 * @param \WP_REST_Request $request The request object containing information about the API request.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'ohmylms_rest_prepare_lesson', $response, $post, $request );
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
	 * in REST API requests for lessons. It merges the public and private query variables
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

		$post_type_obj = get_post_type_object( OHMYLMS_LESSON_CPT );
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
		 * that can be used in REST API requests for lessons.
		 *
		 * @param array $valid_vars The array of valid query variables.
		 */
		$valid_vars = apply_filters( 'ohmylms_rest_query_vars', $valid_vars );

		return $valid_vars;
	}
}
