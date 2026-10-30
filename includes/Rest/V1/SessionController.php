<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Controller for handling ohmylms-session REST API endpoints.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use WP_HTTP_Response;
use WP_Query;

/**
 * Controller for handling ohmylms-session REST API endpoints.
 */
class SessionController extends RestController {

	/**
	 * The base route for session endpoints.
	 *
	 * @var string
	 */
	protected $base = 'session';

	/**
	 * Check if the current user has permission to edit posts.
	 *
	 * @return bool
	 */
	public function check_session_permission() {
		return \current_user_can( 'edit_posts' );
	}

	/**
	 * Permission check for reading a single session.
	 *
	 * @since 1.2.20
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * .
	 * @return true|\WP_Error
	 */
	public function check_session_read_permission( $request ) {
		if ( ! \current_user_can( 'edit_posts' ) ) {
			return new \WP_Error( 'ohmylms_rest_forbidden', __( 'Sorry, you are not allowed to manage this resource.', 'ohmylms' ), array( 'status' => \rest_authorization_required_code() ) );
		}

		return $this->check_object_permission( $request, 'read', 'ohmylms-session' );
	}

	/**
	 * Permission check for editing a single session.
	 *
	 * @since 1.2.20
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * .
	 * @return true|\WP_Error
	 */
	public function check_session_edit_permission( $request ) {
		return $this->check_object_permission( $request, 'edit', 'ohmylms-session' );
	}

	/**
	 * Permission check for deleting a single session.
	 *
	 * @since 1.2.20
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * .
	 * @return true|\WP_Error
	 */
	public function check_session_delete_permission( $request ) {
		return $this->check_object_permission( $request, 'delete', 'ohmylms-session' );
	}

	/**
	 * Registers REST API routes for session operations.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->base . '/',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'check_session_permission' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_session_permission' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
			)
		);

		\register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the session.', 'ohmylms' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'check_session_edit_permission' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_session_delete_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'check_session_read_permission' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);
	}

	/**
	 * Get collection of sessions.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function get_items( $request ) {
		$args = array(
			'offset'         => isset( $request['offset'] ) ? intval( $request['offset'] ) : 0,
			'order'          => isset( $request['order'] ) ? sanitize_text_field( $request['order'] ) : 'DESC',
			'orderby'        => isset( $request['orderby'] ) ? sanitize_text_field( $request['orderby'] ) : 'date',
			'paged'          => isset( $request['page'] ) ? intval( $request['page'] ) : 1,
			'post__in'       => isset( $request['include'] ) ? array_map( 'intval', (array) $request['include'] ) : array(),
			'post__not_in'   => isset( $request['exclude'] ) ? array_map( 'intval', (array) $request['exclude'] ) : array(),
			'posts_per_page' => isset( $request['per_page'] ) ? intval( $request['per_page'] ) : 10,
			's'              => isset( $request['search'] ) ? sanitize_text_field( $request['search'] ) : '',
			'post_type'      => 'ohmylms-session',
			'post_status'    => isset( $request['post_status'] ) ? sanitize_text_field( $request['post_status'] ) : array( 'draft', 'publish', 'future' ),
		);

		$args['date_query'] = array();
		if ( isset( $request['before'] ) ) {
			$args['date_query'][0]['before'] = sanitize_text_field( $request['before'] );
		}
		if ( isset( $request['after'] ) ) {
			$args['date_query'][0]['after'] = sanitize_text_field( $request['after'] );
		}

		$args       = apply_filters( 'ohmylms_rest_ohmylms_session_query', $args, $request );
		$query_args = $this->prepare_items_query( $args, $request );

		$posts_query  = new WP_Query();
		$query_result = $posts_query->query( $query_args );

		$posts = array();
		foreach ( $query_result as $post ) {
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}

			// Check if session has a valid course_id.
			$course_id = ohmylms_get_course_by_content_id( $post->ID );
			if ( empty( $course_id ) ) {
				continue; // Skip sessions without a valid course_id.
			}

			$data    = $this->prepare_item_for_response( $post, $request );
			$posts[] = $this->prepare_response_for_collection( $data );
		}

		$page        = (int) $query_args['paged'];
		$total_posts = count( $posts ); // Use actual filtered count.
		$max_pages   = ceil( $total_posts / (int) $query_args['posts_per_page'] );

		$response = rest_ensure_response( $posts );
		$response->header( 'X-WP-Total', (int) $total_posts );
		$response->header( 'X-WP-TotalPages', (int) $max_pages );

		return $response;
	}

	/**
	 * Get a single session.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function get_item( $request ) {
		$id   = (int) $request['id'];
		$post = get_post( $id );
		if ( empty( $id ) || empty( $post->ID ) || 'ohmylms-session' !== $post->post_type ) {
			return new WP_Error( 'ohmylms_rest_invalid_session_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$data     = $this->prepare_item_for_response( $post, $request );
		$response = rest_ensure_response( $data );
		$response->link_header( 'alternate', get_permalink( $id ), array( 'type' => 'text/html' ) );
		return $response;
	}

	/**
	 * Create a session.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function create_item( $request ) {
		if ( ! empty( $request['id'] ) ) {
			return new WP_Error( 'ohmylms_rest_session_exists', sprintf( /* translators: %s: text value. */
			__( 'Cannot create existing %s.', 'ohmylms' ), 'Session' ), array( 'status' => 400 ) );
		}
		$session_id = $this->save_session( $request );
		update_post_meta( $session_id, '_content_type', $request['content_type'] );
		update_post_meta( $session_id, '_platform', $request['platform'] );
		update_post_meta( $session_id, '_start_date', $request['date'] );
		update_post_meta( $session_id, '_timezone', $request['timezone'] );
		update_post_meta( $session_id, '_duration', $request['duration'] );
		update_post_meta( $session_id, '_session_type', $request['type'] );
		$this->update_custom_meta_fields( $session_id, $request );

		$post = get_post( $session_id );
		do_action( 'ohmylms_rest_insert_session', $post, $request, true );
		$request->set_param( 'context', 'edit' );
		do_action( "ohmylms_{$request['platform']}_session_created", $post, $request );
		$maybe_create_session = get_post_meta( $session_id, "_is_{$request['platform']}_session_created", true );

		if ( 'yes' !== $maybe_create_session ) {
			// Return with error that session is not created because API key is not configured or API key has not the permission to create a session.
			$response = new WP_Error( 'ohmylms_rest_session_not_created', __( "{$request['platform']} session could not be created. Please check your {$request['platform']} API key and permissions.", 'ohmylms' ), array( 'status' => 500 ) );
			return $response;
		}

		if ( 'googlemeet' === $request['platform'] ) {
			// For Google Meet, update the session with meeting data.
			$title    = $request->get_param( 'topic' );
			$start    = $request->get_param( 'date' );
			$end      = $request->get_param( 'endDate' );
			$desc     = $request->get_param( 'agenda' );
			$timezone = $request->get_param( 'timezone' ) ?: 'UTC';

			update_post_meta(
				$session_id,
				'_googlemeet_meeting_data',
				array(
					'title'       => $title,
					'start'       => $start,
					'endDate'     => $end,
					'description' => $desc,
					'timezone'    => $timezone,
				)
			);
		}

		// Build response after the platform hook has persisted its meeting meta
		// (duration/timezone/password/toggles/join_url/start_url), so the create
		// response matches what a subsequent GET would return.
		$response = $this->prepare_item_for_response( $post, $request );
		$response = rest_ensure_response( $response );
		if ( ! is_wp_error( $response ) ) {
			$response->set_status( 201 );
		}
		return $response;
	}

	/**
	 * Update a session.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function update_item( $request ) {
		$post_id = (int) $request['id'];
		if ( empty( $post_id ) || get_post_type( $post_id ) !== 'ohmylms-session' ) {
			return new WP_Error( 'ohmylms_rest_session_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$session_id = $this->update_session( $request );

		update_post_meta( $session_id, '_session_type', $request['type'] );
		update_post_meta( $session_id, '_timezone', $request['timezone'] );
		update_post_meta( $session_id, '_platform', $request['platform'] );
		update_post_meta( $session_id, '_duration', $request['duration'] );
		update_post_meta( $session_id, '_content_type', $request['content_type'] );
		update_post_meta( $session_id, '_start_date', $request['date'] );

		$post = get_post( $session_id );
		$this->update_post_meta_fields( $post, $request );
		$this->update_custom_meta_fields( $session_id, $request );
		$request->set_param( 'context', 'edit' );
		do_action( "ohmylms_{$request['platform']}_session_updated", $post, $request );
		$maybe_create_zoom_session = get_post_meta( $session_id, "_is_{$request['platform']}_session_created", true );
		if ( 'yes' !== $maybe_create_zoom_session ) {
			// Return with error that Zoom session is not created because API key is not configured or API key has not the permission to create a session.
			$response = new WP_Error( "ohmylms_rest_{$request['platform']}_session_not_created", __( "{$request['platform']} session could not be created. Please check your {$request['platform']} API key and permissions.", 'ohmylms' ), array( 'status' => 500 ) );
			return $response;
		}
		$response = $this->prepare_item_for_response( $post, $request );

		if ( 'googlemeet' === $request['platform'] ) {
			// For Google Meet, update the session with meeting data.
			$title    = $request->get_param( 'topic' );
			$start    = $request->get_param( 'date' );
			$end      = $request->get_param( 'endDate' );
			$desc     = $request->get_param( 'agenda' );
			$timezone = $request->get_param( 'timezone' ) ?: 'UTC';

			update_post_meta(
				$session_id,
				'_googlemeet_meeting_data',
				array(
					'title'       => $title,
					'start'       => $start,
					'endDate'     => $end,
					'description' => $desc,
					'timezone'    => $timezone,
				)
			);
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Delete a session.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 */
	public function delete_item( $request ) {
		$session_id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( ! $session_id ) {
			return new WP_Error( 'ohmylms_rest_session_empty_id', __( 'ID is required.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		wp_delete_post( $session_id, true );
		do_action( 'ohmylms_rest_delete_session', $session_id );
		do_action( "ohmylms_{$request['platform']}_session_deleted", $session_id, $request );
		$response = array(
			'id'      => $session_id,
			'status'  => 'success',
			'message' => __( 'Session has been deleted successfully.', 'ohmylms' ),
		);
		return rest_ensure_response( $response );
	}


	/**
	 * Update session for Session controller.
	 *
	 * @param mixed $request REST request.
	 */
	public function update_session( $request ) {
		$session = $this->prepare_item_for_database( $request );
		unset( $session['post_parent'] );
		unset( $session['post_status'] );
		return wp_update_post( $session );
	}

	/**
	 * Save session (create or update).
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return int|WP_Error
	 */
	public function save_session( $request ) {
		$session = $this->prepare_item_for_database( $request );
		return wp_insert_post( $session );
	}

	/**
	 * Prepare a session for database.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array
	 */
	protected function prepare_item_for_database( $request ) {
		$id      = isset( $request['id'] ) ? absint( $request['id'] ) : 0;
		$postarr = array(
			'ID'           => $id,
			'post_type'    => 'ohmylms-session',
			'post_title'   => isset( $request['topic'] ) ? wp_filter_post_kses( $request['topic'] ) : '',
			'post_content' => isset( $request['agenda'] ) ? wp_filter_post_kses( $request['agenda'] ) : '',
			'post_status'  => isset( $request['status'] ) ? $request['status'] : 'publish',
			'post_parent'  => isset( $request['course_id'] ) ? $request['course_id'] : 0,
		);
		return $postarr;
	}

	/**
	 * Update post meta fields for a session.
	 *
	 * @param \WP_Post        $post Post.
	 * @param WP_REST_Request $request REST request.
	 * @return void
	 */
	protected function update_post_meta_fields( $post, $request ) {
		if ( isset( $request['type'] ) ) {
			update_post_meta( $post->ID, '_session_type', sanitize_text_field( $request['type'] ) );
		}
		if ( isset( $request['session_type'] ) ) {
			update_post_meta( $post->ID, '_session_type', sanitize_text_field( $request['type'] ) );
		}

		if ( isset( $request['join_before_host'] ) ) {
			update_post_meta( $post->ID, '_join_before_host', sanitize_text_field( $request['join_before_host'] ) );
		}

		if ( isset( $request['mute_upon_entry'] ) ) {
			update_post_meta( $post->ID, '_mute_upon_entry', sanitize_text_field( $request['mute_upon_entry'] ) );
		}

		if ( isset( $request['participant_video'] ) ) {
			update_post_meta( $post->ID, '_participant_video', sanitize_text_field( $request['participant_video'] ) );
		}

		if ( isset( $request['host_video'] ) ) {
			update_post_meta( $post->ID, '_host_video', sanitize_text_field( $request['host_video'] ) );
		}
	}

	/**
	 * Persist the Live Class custom fields (recording, cover media, attachments).
	 *
	 * @param int             $post_id Post identifier.
	 * @param WP_REST_Request $request REST request.
	 * @return void
	 */
	protected function update_custom_meta_fields( $post_id, $request ) {
		$params = $request->get_params();

		if ( array_key_exists( 'auto_record', $params ) ) {
			update_post_meta( $post_id, '_auto_record', rest_sanitize_boolean( $request['auto_record'] ) ? '1' : '' );
		}

		if ( array_key_exists( 'zoom_plan', $params ) ) {
			update_post_meta( $post_id, '_zoom_plan', sanitize_text_field( $request['zoom_plan'] ) );
		}

		if ( array_key_exists( 'cover_image_id', $params ) ) {
			update_post_meta( $post_id, '_cover_image_id', $request['cover_image_id'] ? absint( $request['cover_image_id'] ) : 0 );
		}

		if ( array_key_exists( 'cover_video_id', $params ) ) {
			update_post_meta( $post_id, '_cover_video_id', $request['cover_video_id'] ? absint( $request['cover_video_id'] ) : 0 );
		}

		if ( array_key_exists( 'attachments', $params ) && is_array( $request['attachments'] ) ) {
			$clean = array();
			foreach ( $request['attachments'] as $file ) {
				if ( empty( $file['id'] ) ) {
					continue;
				}
				$clean[] = array(
					'id'   => absint( $file['id'] ),
					'url'  => esc_url_raw( isset( $file['url'] ) ? $file['url'] : '' ),
					'name' => sanitize_text_field( isset( $file['name'] ) ? $file['name'] : '' ),
					'size' => sanitize_text_field( isset( $file['size'] ) ? $file['size'] : '' ),
				);
			}
			update_post_meta( $post_id, '_attachments', wp_json_encode( $clean ) );
		}

		if ( array_key_exists( 'recording_source', $params ) ) {
			update_post_meta( $post_id, '_recording_source', sanitize_text_field( $request['recording_source'] ) );
		}

		if ( array_key_exists( 'recording_url', $params ) ) {
			update_post_meta( $post_id, '_recording_url', esc_url_raw( $request['recording_url'] ) );
		}

		if ( array_key_exists( 'recording_state', $params ) ) {
			update_post_meta( $post_id, '_recording_state', sanitize_text_field( $request['recording_state'] ) );
		}
	}

	/**
	 * Prepare a single session for response.
	 *
	 * @param \WP_Post        $post Post.
	 * @param WP_REST_Request $request REST request.
	 * @return array
	 */
	public function prepare_item_for_response( $post, $request ) {
		$platform     = get_post_meta( $post->ID, '_platform', true );
		$meeting_data = null;
		$end_date     = null;

		if ( 'zoom' === $platform ) {
			$meeting_data = get_post_meta( $post->ID, '_zoom_meeting_data', true );
			$meeting_data = json_decode( $meeting_data, true );
		} elseif ( 'googlemeet' === $platform ) {
			$meeting_data = get_post_meta( $post->ID, '_googlemeet_event_data', true );
		}

		$course_id    = ohmylms_get_course_by_content_id( $post->ID );
		$course_title = get_the_title( $course_id );
		$chapter_id   = ohmylms_get_chapter_id_by_content_id( $post->ID );
		$start_date   = get_post_meta( $post->ID, '_start_date', true );
		$duration     = get_post_meta( $post->ID, '_duration', true );
		$timezone     = get_post_meta( $post->ID, '_timezone', true );

		// Handle Google Meet specific data.
		if ( 'googlemeet' === $platform && is_array( $meeting_data ) ) {
			if ( isset( $meeting_data['start']['dateTime'] ) ) {
				$start_date = $meeting_data['start']['dateTime'];
			}
			if ( isset( $meeting_data['start']['timeZone'] ) ) {
				$timezone = $meeting_data['start']['timeZone'];
			}
			if ( isset( $meeting_data['end']['dateTime'] ) ) {
				$end_date = $meeting_data['end']['dateTime'];
				// Calculate duration from start and end dates.
				$start_time = strtotime( $start_date );
				$end_time   = strtotime( $end_date );
				if ( $start_time && $end_time ) {
					$duration = round( ( $end_time - $start_time ) / 60 ); // Convert to minutes and round.
				}
			}
		}

		// Live Class custom fields (recording, cover media, attachments).
		$cover_image_id = (int) get_post_meta( $post->ID, '_cover_image_id', true );
		$cover_video_id = (int) get_post_meta( $post->ID, '_cover_video_id', true );
		$attachments    = json_decode( get_post_meta( $post->ID, '_attachments', true ), true );
		if ( ! is_array( $attachments ) ) {
			$attachments = array();
		}

		// Calculate meeting status based on start date, duration, and current time.
		$meeting_status = $this->calculate_meeting_status( $start_date, $timezone, $duration );

		// For response, we need both formatted and raw dates
		// Convert to the session's timezone for proper display.
		$formatted_start_date = '';
		$formatted_end_date   = '';

		if ( $start_date && $timezone ) {
			try {
				$start_dt             = new \DateTime( $start_date, new \DateTimeZone( $timezone ) );
				$formatted_start_date = $start_dt->format( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
			} catch ( \Exception $e ) {
				$formatted_start_date = $start_date ? date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $start_date ) ) : '';
			}
		} else {
			$formatted_start_date = $start_date ? date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $start_date ) ) : '';
		}

		if ( $end_date && $timezone ) {
			try {
				$end_dt             = new \DateTime( $end_date, new \DateTimeZone( $timezone ) );
				$formatted_end_date = $end_dt->format( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
			} catch ( \Exception $e ) {
				$formatted_end_date = $end_date ? date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $end_date ) ) : '';
			}
		} else {
			$formatted_end_date = $end_date ? date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $end_date ) ) : '';
		}

		// A Zoom start link signs the caller in as the meeting host.
		$can_host = current_user_can( 'edit_post', $post->ID );
		$data     = array(
			'id'                => $post->ID,
			'topic'             => $post->post_title,
			'agenda'            => $post->post_content,
			'date'              => $formatted_start_date,
			'rawDate'           => $start_date, // Raw date for editing.
			'endDate'           => $formatted_end_date,
			'rawEndDate'        => $end_date, // Raw end date for editing.
			'duration'          => $duration,
			'duration_unit'     => 'min',
			'password'          => $can_host ? get_post_meta( $post->ID, '_password', true ) : '',
			'timezone'          => $timezone,
			'order'             => get_post_meta( $post->ID, '_order', true ),
			'type'              => get_post_meta( $post->ID, '_type', true ),
			'host_video'        => get_post_meta( $post->ID, '_host_video', true ),
			'participant_video' => get_post_meta( $post->ID, '_participant_video', true ),
			'join_before_host'  => get_post_meta( $post->ID, '_join_before_host', true ),
			'mute_upon_entry'   => get_post_meta( $post->ID, '_mute_upon_entry', true ),
			'content_type'      => 'session',
			'platform'          => $platform,
			'join_url'          => $this->get_join_url( $post->ID, $platform, $meeting_data ),
			'start_url'         => $can_host ? $this->get_start_url( $post->ID, $platform, $meeting_data ) : '',
			'status'            => $meeting_status,
			'course_id'         => $course_id,
			'chapter_id'        => $chapter_id,
			'course_title'      => $course_title,
			'preview_url'       => get_permalink( $post->ID ),
			'auto_record'       => (bool) get_post_meta( $post->ID, '_auto_record', true ),
			'zoom_plan'         => get_post_meta( $post->ID, '_zoom_plan', true ) ?: 'free',
			'cover_image_id'    => $cover_image_id ?: null,
			'cover_image_src'   => $cover_image_id ? wp_get_attachment_url( $cover_image_id ) : '',
			'cover_video_id'    => $cover_video_id ?: null,
			'cover_video_src'   => $cover_video_id ? wp_get_attachment_url( $cover_video_id ) : '',
			'attachments'       => $attachments,
			'recording_source'  => get_post_meta( $post->ID, '_recording_source', true ),
			'recording_url'     => get_post_meta( $post->ID, '_recording_url', true ),
			'recording_state'   => get_post_meta( $post->ID, '_recording_state', true ) ?: 'empty',
		);
		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $post, $request ) );
		return $response;
	}

	/**
	 * Prepare links for the request.
	 *
	 * @param mixed $post Post.
	 * @param mixed $request REST request.
	 * @return array[]
	 */
	protected function prepare_links( $post, $request ) {
		$links = array(
			'self'       => array(
				'href' => rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->base, $post->ID ) ),
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
	 * @param array                $prepared_args Prepared args.
	 * @param WP_REST_Request|null $request REST request.
	 * @return array
	 */
	protected function prepare_items_query( $prepared_args = array(), $request = null ) {
		global $wp;
		$valid_vars    = apply_filters( 'query_vars', $wp->public_query_vars );
		$post_type_obj = get_post_type_object( 'ohmylms-session' );
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
		$query_args = array();
		foreach ( array_flip( $valid_vars ) as $var => $index ) {
			if ( isset( $prepared_args[ $var ] ) ) {
				$query_args[ $var ] = $prepared_args[ $var ];
			}
		}
		$query_args['ignore_sticky_posts'] = true;
		if ( isset( $query_args['orderby'] ) && 'include' === $query_args['orderby'] ) {
			$query_args['orderby'] = 'post__in';
		} elseif ( isset( $query_args['orderby'] ) && 'id' === $query_args['orderby'] ) {
			$query_args['orderby'] = 'ID';
		} elseif ( isset( $query_args['orderby'] ) && 'slug' === $query_args['orderby'] ) {
			$query_args['orderby'] = 'name';
		}
		return $query_args;
	}

	/**
	 * Calculate meeting status based on start date, duration, and current time.
	 *
	 * @param string $start_date The start date of the meeting (format: Y-m-d H:i:s).
	 * @param mixed  $timezone Timezone.
	 * @param int    $duration The duration of the meeting in minutes.
	 * @return string The meeting status: 'expired', 'upcoming', or 'running'
	 */
	private function calculate_meeting_status( $start_date, $timezone, $duration ) {
		if ( empty( $start_date ) || empty( $duration ) ) {
			return 'upcoming';
		}

		try {
			// Meeting start time in given timezone.
			$start = new \DateTime( $start_date, new \DateTimeZone( $timezone ) );

			// Current time in same timezone.
			$now = new \DateTime( 'now', new \DateTimeZone( $timezone ) );

			// Calculate end time.
			$end = clone $start;
			$end->modify( "+{$duration} minutes" );

			if ( $now < $start ) {
				return 'upcoming';
			} elseif ( $now > $end ) {
				return 'expired';
			} else {
				return 'running';
			}
		} catch ( \Exception $e ) {
			return 'upcoming';
		}
	}

	/**
	 * Get the join URL for a session.
	 *
	 * @param int        $post_id The post ID.
	 * @param string     $platform The platform (zoom, googlemeet).
	 * @param array|null $meeting_data The meeting data.
	 * @return string The join URL.
	 */
	private function get_join_url( $post_id, $platform, $meeting_data ) {
		// Check post meta first (used by Google Meet and Zoom).
		$join_url = get_post_meta( $post_id, '_join_url', true );

		if ( ! empty( $join_url ) ) {
			return $join_url;
		}

		// Fallback to meeting data (Zoom).
		if ( is_array( $meeting_data ) && isset( $meeting_data['join_url'] ) ) {
			return $meeting_data['join_url'];
		}

		return '';
	}

	/**
	 * Get the start URL for a session.
	 *
	 * @param int        $post_id The post ID.
	 * @param string     $platform The platform (zoom, googlemeet).
	 * @param array|null $meeting_data The meeting data.
	 * @return string The start URL.
	 */
	private function get_start_url( $post_id, $platform, $meeting_data ) {
		// Check post meta first (used by Google Meet and Zoom).
		$start_url = get_post_meta( $post_id, '_start_url', true );

		if ( ! empty( $start_url ) ) {
			return $start_url;
		}

		// Fallback to meeting data (Zoom).
		if ( is_array( $meeting_data ) && isset( $meeting_data['start_url'] ) ) {
			return $meeting_data['start_url'];
		}

		return '';
	}
}
