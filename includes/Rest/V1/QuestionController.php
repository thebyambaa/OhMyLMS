<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Controller for handling lesson REST API endpoints.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Data\Question;
use OhMyLMS\DataException;
use OhMyLMS\Question\QuestionHelper;
use OhMyLMS\QuestionBank\AccessPolicy;
use OhMyLMS\QuestionBank\DraftWriter;
use OhMyLMS\QuestionBank\Usage;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Controller for handling lesson REST API endpoints.
 *
 * This class extends the RESTController abstract class and defines REST API routes
 * for course-related CRUD operations and many more.
 *
 * @since 1.0.0
 */
class QuestionController extends RestController {

	/**
	 * The base route for lesson base endpoints.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $base = 'question';

	/**
	 * Check question permission for Question controller.
	 */
	public function check_question_permission() {
		return AccessPolicy::check( AccessPolicy::can_author() );
	}

	/**
	 * Object-level permission for single-question routes.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return true|WP_Error
	 */
	public function check_item_permission( $request ) {
		$id = (int) $request['id'];
		if ( get_post_type( $id ) !== OHMYLMS_QUESTION_CPT ) {
			return new WP_Error( 'ohmylms_rest_invalid_question_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		if ( 'DELETE' === $request->get_method() ) {
			$quiz_id = (int) $request['quiz_id'];
			return AccessPolicy::check( $quiz_id ? AccessPolicy::can_edit_quiz( $quiz_id ) : AccessPolicy::can_delete_question( $id ) );
		}
		return AccessPolicy::check( AccessPolicy::can_edit_question( $id ) );
	}

	/**
	 * Registers REST API routes for Quiestion operations.
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
					'permission_callback' => array( $this, 'check_question_permission' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_question_permission' ),
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
						'description' => __( 'Unique identifier for the question.', 'ohmylms' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'check_item_permission' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_item_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'check_item_permission' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);
	}

	/**
	 * Get collection of questions.
	 *
	 * This method handles the retrieval of questions based on the provided request parameters.
	 * It supports various filters and pagination options to customize the query.
	 *
	 * @param \WP_REST_Request $request The REST request object containing query parameters.
	 * @return WP_Error|\WP_HTTP_Response|\WP_REST_Response The response object containing the questions data or an error.
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
			'post_type'           => OHMYLMS_QUESTION_CPT,
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

		$args       = apply_filters( 'ohmylms_rest_ohmylms_question_query', $args, $request );
		$query_args = $this->prepare_items_query( $args, $request );

		$posts_query  = new WP_Query();
		$query_result = $posts_query->query( $query_args );

		$posts = array();

		foreach ( $query_result as $post ) {
			if ( ! AccessPolicy::can_edit_question( $post->ID ) && ! AccessPolicy::can_use_question( $post->ID ) ) {
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
	 * Add question
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function create_item( $request ) {

		if ( ! empty( $request['id'] ) ) {
			// Translators: %s is replaced with object name.
			return new WP_Error( 'ohmylms_rest_question_exists', sprintf( __( 'Cannot create existing %s.', 'ohmylms' ), 'Question' ), array( 'status' => 400 ) );
		}
		$saved = DraftWriter::save( $request, (int) $request['quiz_id'] );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		$post = get_post( $saved['id'] );
		/**
		 * Fires after a Question is inserted via the REST API.
		 *
		 * @param \WP_Post         $post    The post object for the question.
		 * @param \WP_REST_Request $request The request object.
		 * @param bool             $creating Whether the question is being created (true) or updated (false).
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_rest_insert_question', $post, $request, true );

		$response = $this->prepare_item_for_response( $post, $request );
		$response = rest_ensure_response( $response );
		do_action( 'ohmylms_rest_question_created', $post, $request );
		if ( ! is_wp_error( $response ) ) {
			$response->set_status( 201 );
		}
		return $response;
	}


	/**
	 * Update Question settings
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function update_item( $request ) {

		$post_id = (int) $request['id'];

		if ( empty( $post_id ) || get_post_type( $post_id ) !== OHMYLMS_QUESTION_CPT ) {
			return new WP_Error( 'ohmylms_rest_question_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$saved = DraftWriter::save( $request, (int) $request['quiz_id'] );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		$post = get_post( $saved['id'] );
		if ( $request instanceof WP_REST_Request ) {
			$this->update_additional_fields_for_object( $post, $request );
		}

		do_action( 'ohmylms_rest_question_updated', $post, $request );

		return rest_ensure_response( $this->prepare_item_for_response( $post, $request ) );
	}



	/**
	 * Get question content
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_item( $request ) {
		$id   = (int) $request['id'];
		$post = get_post( $id );
		if ( empty( $id ) || empty( $post->ID ) || OHMYLMS_QUESTION_CPT !== $post->post_type ) {
			return new WP_Error( 'ohmylms_rest_invalid_question_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		$data     = $this->prepare_item_for_response( $post, $request );
		$response = rest_ensure_response( $data );

		$response->link_header( 'alternate', get_permalink( $id ), array( 'type' => 'text/html' ) );

		return $response;

		return rest_ensure_response( $response );
	}


	/**
	 * Delete a single question.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 *
	 * @since 1.0.0
	 */
	public function delete_item( $request ) {
		$question_id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( ! $question_id ) {
			return new WP_Error( 'ohmylms_rest_question_empty_id', __( 'ID is required.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		if ( get_post_type( $question_id ) !== OHMYLMS_QUESTION_CPT ) {
			return new WP_Error( 'ohmylms_rest_question_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		// With quiz context this removes the question from that quiz only; it is never deleted.
		$quiz_id = (int) $request['quiz_id'];
		$result  = $quiz_id ? Usage::remove_from_quiz( $quiz_id, $question_id ) : Usage::delete_or_archive( $question_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		/**
		 * Executes the 'ohmylms_rest_delete_question' action hook.
		 * Triggered when a question is removed from a quiz, archived or deleted via the REST API.
		 *
		 * @param array $request The request array.
		 * @param array $result  The operation performed: removed, archived or deleted.
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_rest_delete_question', $request, $result );

		$messages = array(
			'removed'  => __( 'Question has been removed from the quiz.', 'ohmylms' ),
			'archived' => __( 'Question has learner history, so it was archived instead of deleted.', 'ohmylms' ),
			'deleted'  => __( 'Question has been deleted successfully.', 'ohmylms' ),
		);
		return rest_ensure_response(
			array(
				'id'      => $question_id,
				'status'  => 'success',
				'action'  => $result['action'],
				'message' => $messages[ $result['action'] ],
			)
		);
	}


	/**
	 * Save question
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return bool
	 * @since 1.0.0
	 * @throws DataException When the operation cannot be completed.
	 */
	public function save_question( $request ) {
		$saved = DraftWriter::save( $request, (int) ( $request['quiz_id'] ?? 0 ) );
		if ( is_wp_error( $saved ) ) {
			$data = (array) $saved->get_error_data();
			throw new DataException( $saved->get_error_code(), $saved->get_error_message(), (int) ( $data['status'] ?? 400 ) );
		}
		return $saved['id'];
	}


	/**
	 * Prepare a question for database.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return bool|Question
	 *
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	protected function prepare_item_for_database( $request ) {
		$id = isset( $request['id'] ) ? absint( $request['id'] ) : 0;

		if ( isset( $request['id'] ) ) {
			$question = ohmylms_get_question( $id );
		} else {
			$question = new Question();
		}

		if ( isset( $request['name'] ) ) {
			$question->set_name( wp_filter_post_kses( $request['name'] ) );
		}

		if ( isset( $request['description'] ) ) {
			$question->set_description( wp_filter_post_kses( $request['description'] ) );
		}

		if ( isset( $request['slug'] ) ) {
			$question->set_slug( wp_filter_post_kses( $request['slug'] ) );
		}

		if ( isset( $request['status'] ) ) {
			$question->set_status( get_post_status_object( $request['status'] ) ? $request['status'] : 'draft' );
		}
		$this->set_question_meta( $question, $request );
		return $question;
	}

	/**
	 * Prepare a single question for response.
	 *
	 * @param \WP_Post         $post The post object.
	 * @param \WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 *
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function prepare_item_for_response( $post, $request ) {
		$question = ohmylms_get_question( $post->ID );
		$data     = $this->get_question_data( $question );
		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $question, $request ) );

		/**
		 * Filters the response for the question in the REST API.
		 *
		 * This filter allows developers to modify the question response data before it is returned by the REST API.
		 *
		 * @param array $response The response data for the question.
		 * @param \WP_Post $post The WP_Post object representing the question.
		 * @param \WP_REST_Request $request The request object containing information about the API request.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'ohmylms_rest_prepare_question', $response, $post, $request );
	}




	/**
	 * Get question data.
	 *
	 * @param question $question Question.
	 * @return array
	 *
	 * @since 1.0.0
	 */
	protected function get_question_data( $question ) {
		$data = array(
			'id'           => $question->get_id(),
			'name'         => $question->get_name(),
			'description'  => $question->get_description(),
			'slug'         => $question->get_slug(),
			'status'       => $question->get_status(),
			'settings'     => $question->get_settings(),
			'answer'       => $question->get_questions(),
			'thumbnail_id' => $question->get_thumbnail_id(),
			'image_src'    => wp_get_attachment_image_src( $question->get_thumbnail_id(), 'large' ) ? wp_get_attachment_image_src( $question->get_thumbnail_id(), 'large' )[0] : '',
			'video_id'     => $question->get_video_id(),
			'video_src'    => wp_get_attachment_url( $question->get_video_id() ),
			'modified'     => get_post_field( 'post_modified_gmt', $question->get_id() ),
			'usage'        => Usage::summary( $question->get_id() ),
		);

		return $data;
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
	 * Update post meta fields for a question.
	 *
	 * This method updates the meta fields for a given question post based on the provided request data.
	 *
	 * @param \WP_Post         $post The post object representing the question.
	 * @param \WP_REST_Request $request The REST request object containing the meta data.
	 * @return bool True on success, false on failure.
	 *
	 * @throws DataException When the operation cannot be completed.
	 * @since 1.0.0
	 */
	protected function update_post_meta_fields( $post, $request ) {
		$question = ohmylms_get_question( $post );
		$question = $this->set_question_meta( $question, $request );
		$question->save();
		/**
		 * Fires after the meta data for a question is updated.
		 *
		 * @param WP_Post $question The updated question object.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_rest_question_meta_updated', $question );

		return true;
	}


	/**
	 * Set product meta data for a question.
	 *
	 * @param Question        $question The question object.
	 * @param WP_REST_Request $request The REST request object containing the meta data.
	 * @return Question The updated question object.
	 *
	 * @since 1.0.0
	 */
	protected function set_question_meta( $question, $request ) {
		if ( isset( $request['settings'] ) ) {
			$question->set_settings( $request['settings'] );
		}
		if ( isset( $request['thumbnail_id'] ) ) {
			$question->set_thumbnail_id( $request['thumbnail_id'] );
		}
		if ( isset( $request['video_id'] ) ) {
			$question->set_video_id( $request['video_id'] );
		}
		return $question;
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
	 * in REST API requests for questions. It merges the public and private query variables
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

		$post_type_obj = get_post_type_object( OHMYLMS_QUIZ_CPT );
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
		 * that can be used in REST API requests for questions.
		 *
		 * @param array $valid_vars The array of valid query variables.
		 */
		$valid_vars = apply_filters( 'ohmylms_rest_query_vars', $valid_vars );

		return $valid_vars;
	}
}
