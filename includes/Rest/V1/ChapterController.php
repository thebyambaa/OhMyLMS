<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Controller for handling chapter REST API endpoints.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Data\Assignment;
use OhMyLMS\Data\Chapter;
use OhMyLMS\Data\Course;
use OhMyLMS\Data\Lesson;
use OhMyLMS\Data\Quiz;
use OhMyLMS\DataException;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use WP_HTTP_Response;

/**
 * Controller for handling chapter REST API endpoints.
 *
 * This class extends the RESTController abstract class and defines REST API routes
 * for chapter-related CRUD operations and many more.
 *
 * @since 1.0.0
 */
class ChapterController extends RestController {

	/**
	 * The base route for chapter base endpoints.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $base = 'chapters';

	/**
	 * Check chapter permission for Chapter controller.
	 */
	public function check_chapter_permission() {
		return current_user_can( 'edit_posts' );
	}


	/**
	 * Check content delete permission for Chapter controller.
	 */
	public function check_content_delete_permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Registers REST API routes for chapter operations.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_chapter_permission' ),
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
						'description' => __( 'Unique identifier for the chapter.', 'ohmylms' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'check_chapter_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_chapter_permission' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)/content',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the chapter.', 'ohmylms' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_lessons' ),
					'permission_callback' => array( $this, 'check_chapter_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_contents' ),
					'permission_callback' => array( $this, 'check_chapter_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)/content/(?P<content_id>[\d]+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_content_from_chapter' ),
				'permission_callback' => array( $this, 'check_content_delete_permission' ),
				'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::DELETABLE ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)/search-contents',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the chapter.', 'ohmylms' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_search_content' ),
					'permission_callback' => array( $this, 'check_chapter_permission' ),
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
	 * Create a single chapter
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 *
	 * @since 1.0.0
	 */
	public function create_item( $request ) {
		if ( ! empty( $request['id'] ) ) {
			return new WP_Error( 'ohmylms_rest_chapter_exists', sprintf( __( 'Cannot create existing chapter', 'ohmylms' ) ), array( 'status' => 400 ) );
		}
		try {
			$chapter_id = $this->save_chapter( $request );
			$post       = get_post( $chapter_id );

			/**
			 * Fires after a chapter is inserted via the REST API.
			 *
			 * @param \WP_Post         $post    The post object for the chapter.
			 * @param \WP_REST_Request $request The request object.
			 * @param bool             $creating Whether the chapter is being created (true) or updated (false).
			 *
			 * @since 1.0.0
			 */
			do_action( 'ohmylms_rest_insert_chapter', $post, $request, true );

			$request->set_param( 'context', 'edit' );
			$response = $this->prepare_item_for_response( $post, $request );
			$response = rest_ensure_response( $response );
			$response->set_status( 201 );
			return $response;
		} catch ( DataException $e ) {
			return new WP_Error( 400, $e->getMessage(), array( 'status' => $e->getCode() ) );
		}
	}

	/**
	 * Updates a chapter item.
	 *
	 * @param WP_REST_Request $request The request object containing the chapter data.
	 *
	 * @return WP_Error|WP_REST_Response|WP_HTTP_Response
	 *         Returns a WP_Error if the ID is invalid or an exception occurs.
	 *         Returns a WP_REST_Response or WP_HTTP_Response on successful update.
	 *
	 * @since 1.0.0
	 */
	public function update_item( $request ) {
		$post_id = (int) $request['id'];

		if ( empty( $post_id ) || OHMYLMS_CHAPTER_CPT !== get_post_type( $post_id ) ) {
			return new WP_Error( 'ohmylms_rest_chapter_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		try {
			$chapter_id = $this->save_chapter( $request );
			$post       = get_post( $chapter_id );

			$this->update_additional_fields_for_object( $post, $request );
			$this->update_post_meta_fields( $post, $request );
			$request->set_param( 'context', 'edit' );

			$response = $this->prepare_item_for_response( $post, $request );

			/**
			 * Fires after a chapter is updated via the REST API.
			 *
			 * @param \WP_Post $post The post object for the chapter.
			 *
			 * @since 1.0.0
			 */
			do_action( 'ohmylms_rest_chapter_updated', $post );

			return rest_ensure_response( $response );
		} catch ( DataException $e ) {
			return new WP_Error( $e->getErrorCode(), $e->getMessage(), $e->getErrorData() );
		}
	}

	/**
	 * Delete chapter
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function delete_item( $request ) {
		// Get chapter id.
		$chapter_id = isset( $request['id'] ) ? (int) $request['id'] : 0;

		// Check the chapter id exist or not.
		if ( ! $chapter_id ) {
			return new WP_Error( 'ohmylms_rest_chapter_empty_id', __( 'ID is required.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		// Get exisiting chapter by chapter id.
		$chapter = ohmylms_get_chapter( $chapter_id );

		// Check the chapter exist or not.
		if ( ! ( $chapter instanceof Chapter ) ) {
			return new WP_Error( 'ohmylms_rest_chapter_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		// Delete the chapter.
		$chapter->delete();

		/**
		 * Executes the 'ohmylms_rest_delete_chapter' action hook.
		 * This hook is triggered when a chapter is being deleted via the REST API.
		 *
		 * @param array $request The request array.
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_rest_delete_chapter', $request );

		$response = array(
			'id'      => $chapter_id,
			'status'  => 'success',
			'message' => __( 'Deleted Successfully', 'ohmylms' ),
		);

		return rest_ensure_response( $response );
	}



	/**
	 * Saves a chapter to the database.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return int
	 *
	 * @since 1.0.0
	 */
	public function save_chapter( $request ) {
		$chapter = $this->prepare_item_for_database( $request );
		return $chapter->save();
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
	public function get_lessons( $request ) {
		$chapter_id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		// Check the chapter id exist or not.
		if ( ! $chapter_id ) {
			return new WP_Error( 'ohmylms_rest_chapter_empty_id', __( 'ID is required.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		// Get existing chapter by chapter id.
		$chapter = ohmylms_get_chapter( $chapter_id );

		// Check the chapter exist or not.
		if ( ! ( $chapter instanceof Chapter ) ) {
			return new WP_Error( 'ohmylms_rest_chapter_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$lessons = $chapter->get_lessons();

		$response = array(
			'status'  => 'success',
			'message' => __( 'Lessons fetched successfully', 'ohmylms' ),
			'data'    => $lessons,
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
	public function update_contents( $request ) {
		$chapter_id = (int) $request['id'];

		// Check the chapter id exist or not.
		if ( ! $chapter_id ) {
			return new WP_Error( 'ohmylms_rest_chapter_empty_id', __( 'ID is required.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		// Get existing chapter by chapter id.
		$chapter = ohmylms_get_chapter( $chapter_id );

		// Check the chapter exist or not.
		if ( ! ( $chapter instanceof Chapter ) ) {
			return new WP_Error( 'ohmylms_rest_chapter_empty_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$lessons      = array();
		$order_number = 0;

		foreach ( $request->get_json_params() as $lesson ) {
			if ( ( isset( $lesson['content_type'] ) && 'session' == $lesson['content_type'] ) || ( isset( $lesson['type'] ) && 'session' == $lesson['type'] ) ) {
				++$order_number;
				$lesson['order_number'] = ! empty( $lesson['order_number'] ) ? intval( $lesson['order_number'] ) : $order_number;
				$chapter->get_data_store()->set_session( $chapter, $lesson );
				$lessons[] = $lesson;
			} else {
				if ( isset( $lesson['type'] ) && 'quiz' == $lesson['type'] ) {
					if ( isset( $lesson['id'] ) ) {
						$lesson_obj = ohmylms_get_quiz( $lesson['id'] );
					} else {
						$lesson_obj = new Quiz( $chapter );
					}
				} elseif ( isset( $lesson['type'] ) && 'assignment' == $lesson['type'] ) {
					if ( isset( $lesson['id'] ) ) {
						$lesson_obj = ohmylms_get_assignment( $lesson['id'] );
					} else {
						$lesson_obj = new Assignment( $chapter );
					}
				} else {
					if ( isset( $lesson['id'] ) ) {
						$lesson_obj = ohmylms_get_lesson( $lesson['id'] );
					} else {
						$lesson_obj = new Lesson( $chapter );
					}
					$lesson_obj->set_type( $lesson['type'] );
				}

				$lesson_obj->set_id( $lesson['id'] );
				$lesson_obj->set_name( $lesson['name'] );
				++$order_number;
				$lesson['order_number'] = ! empty( $lesson['order_number'] ) ? intval( $lesson['order_number'] ) : $order_number;

				// Handle Prerequisites.
				if ( isset( $lesson['prerequisites'] ) && 'quiz' != $lesson['type'] ) {
					$lesson_obj->set_prerequisites( $lesson['prerequisites'] );
				}
				$lesson_obj->save();
				$lessons[] = $lesson;
			}
		}

		$chapter->set_contents( $lessons );
		$response = array(
			'status'  => 'success',
			'message' => __( 'Contents updated successfully', 'ohmylms' ),
		);
		return rest_ensure_response( $response );
	}

	/**
	 * Delete a lesson from a chapter.
	 *
	 * @param WP_REST_Request $request The request object containing the chapter and lesson IDs.
	 * @return WP_Error|\WP_HTTP_Response|\WP_REST_Response The response object indicating success or failure.
	 *
	 * @since 1.0.0
	 */
	public function delete_content_from_chapter( $request ) {
		$chapter_id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		$content_id = isset( $request['content_id'] ) ? (int) $request['content_id'] : 0;

		if ( ! $content_id || ! $chapter_id ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . OHMYLMS_CONTENT_RELATIONSHIP;

		$wpdb->delete(
			$table_name,
			array(
				'chapter_id' => $chapter_id,
				'content_id' => $content_id,
			),
			array(
				'%d',
				'%d',
			)
		);

		$table_name = $wpdb->prefix . 'ohmylms_user_progress';
		$wpdb->delete(
			$table_name,
			array(
				'content_id' => $content_id,
			),
			array(
				'%d',
				'%d',
			)
		);

		$response = array(
			'id'      => $chapter_id,
			'status'  => 'success',
			'message' => __( 'Deleted Successfully', 'ohmylms' ),
		);
		return rest_ensure_response( $response );
	}

	/**
	 * Get search content
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|\WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_search_content( $request ) {
		$chapter_id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		$term       = isset( $request['term'] ) ? sanitize_text_field( $request['term'] ) : '';
		// Check the chapter id exist or not.
		if ( ! $chapter_id ) {
			return new WP_Error( 'ohmylms_rest_chapter_empty_id', __( 'ID is required.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		// Get existing chapter by chapter id.
		$chapter = ohmylms_get_chapter( $chapter_id );

		// Check the chapter exist or not.
		if ( ! ( $chapter instanceof Chapter ) ) {
			return new WP_Error( 'ohmylms_rest_chapter_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$course_id = ohmylms_get_course_id_by_chapter_id( $chapter_id );

		$course = ohmylms_get_course( $course_id );
		// Check the chapter exist or not.
		if ( ! ( $course instanceof Course ) ) {
			return new WP_Error( 'ohmylms_rest_course_invalid_id', __( 'Course ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$lessons  = $course->search_lessons_in_course( $term );
		$response = array(
			'status'  => 'success',
			'message' => __( 'Lessons fetched successfully', 'ohmylms' ),
			'data'    => $lessons,
		);

		return rest_ensure_response( $response );
	}

	/**
	 * Prepare a single chapter for create or update.
	 *
	 * @param mixed $request REST request.
	 * @return bool|Chapter|object|WP_Error
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	protected function prepare_item_for_database( $request ) {
		$id = isset( $request['id'] ) ? absint( $request['id'] ) : 0;
		if ( $id > 0 ) {
			$chapter = ohmylms_get_chapter( $id );
		} else {
			$chapter = new Chapter();
		}
		if ( isset( $request['name'] ) ) {
			$chapter->set_name( wp_filter_post_kses( $request['name'] ) );
		}

		if ( isset( $request['course_id'] ) ) {
			$chapter->set_parent_id( $request['course_id'] );
		}

		if ( isset( $request['description'] ) ) {
			$chapter->set_description( $request['description'] );
		}

		if ( isset( $request['status'] ) ) {
			$chapter->set_status( get_post_status_object( $request['status'] ) ? $request['status'] : 'draft' );
		}
		return $chapter;
	}

	/**
	 * Prepare a single chapter for response.
	 *
	 * @param \WP_Post         $post The post object.
	 * @param \WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 *
	 * @since 1.0.0
	 */
	public function prepare_item_for_response( $post, $request ) {
		$chapter  = ohmylms_get_chapter( $post );
		$data     = $this->get_chapter_data( $chapter );
		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $chapter, $request ) );

		/**
		 * Filters the response for the chapter in the REST API.
		 *
		 * This filter allows developers to modify the chapter response data before it is returned by the REST API.
		 *
		 * @param array $response The response data for the chapter.
		 * @param \WP_Post $post The WP_Post object representing the chapter.
		 * @param \WP_REST_Request $request The request object containing information about the API request.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'ohmylms_rest_prepare_chapter', $response, $post, $request );
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
	 * Get chapter data.
	 *
	 * @param Chapter $chapter Chapter.
	 * @return array
	 *
	 * @since 1.0.0
	 */
	protected function get_chapter_data( $chapter ) {
		$data = array(
			'id'          => $chapter->get_id(),
			'name'        => $chapter->get_name(),
			'description' => $chapter->get_description(),
		);

		return $data;
	}

	/**
	 * Update additional fields for the chapter.
	 *
	 * @param \WP_Post        $post The post object for the chapter.
	 * @param WP_REST_Request $request The request object containing the chapter data.
	 *
	 * @since 1.0.0
	 */
	protected function update_post_meta_fields( $post, $request ): bool {
		// Retrieve the chapter object using the chapter-specific function.
		$chapter = ohmylms_get_chapter( $post );

		// Set additional product-related metadata fields for the chapter.
		$chapter = $this->set_chapter_meta( $chapter, $request );

		// Save the updated chapter data.
		$chapter->save();

		return true;
	}

	/**
	 * Set additional fields for the chapter.
	 *
	 * @param Chapter         $chapter The chapter object.
	 * @param WP_REST_Request $request The request object containing the chapter data.
	 *
	 * @return Chapter The updated chapter object.
	 *
	 * @since 1.0.0
	 */
	protected function set_chapter_meta( $chapter, $request ): Chapter {
		if ( isset( $request['availability'] ) ) {
			$chapter->set_availability( $request['availability'] );
		}

		if ( isset( $request['available_date'] ) ) {
			$chapter->set_available_date( $request['available_date'] );
		}

		if ( isset( $request['accessibility'] ) ) {
			$chapter->set_accessibility( $request['accessibility'] );
		}

		return $chapter;
	}
}
