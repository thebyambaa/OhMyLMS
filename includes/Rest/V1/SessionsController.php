<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * SessionsController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Integrations\IntegrationSettings;
use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Live sessions under `ohmylms/v1/sessions`: paged list, create, read, update, delete, read-only
 * attendees and provider status. Meetings are created, updated and deleted by the existing provider
 * hooks (`ohmylms_{provider}_session_created|updated|deleted`, Zoom and Google Meet services); OAuth
 * stays on the provider endpoints. Provider credentials, tokens and host start links are never
 * returned. See docs/api/sessions.md.
 *
 * @since 1.0.0
 */
class SessionsController extends RestController {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * The base route.
	 *
	 * @var string
	 */
	protected $rest_base = 'sessions';

	/**
	 * Session post type.
	 */
	const POST_TYPE = 'ohmylms-session';

	/**
	 * Supported providers.
	 */
	const PROVIDERS = array( 'zoom', 'googlemeet' );

	/**
	 * Zoom meeting options (stored as '1' or '').
	 */
	const OPTIONS = array( 'host_video', 'participant_video', 'join_before_host', 'mute_upon_entry', 'auto_record' );

	/**
	 * Largest page.
	 */
	const MAX_PER_PAGE = 50;

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$id = array(
			'id' => array(
				'description' => __( 'Session ID.', 'ohmylms' ),
				'type'        => 'integer',
				'minimum'     => 1,
			),
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
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
					'args'                => $this->write_args( true ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/providers',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_providers' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/courses',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_courses' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'search' => array(
							'type'      => 'string',
							'maxLength' => 100,
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args'   => $id,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->write_args( false ),
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
			'/' . $this->rest_base . '/(?P<id>[\d]+)/attendees',
			array(
				'args' => $id,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_attendees' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'page'     => $this->get_collection_params()['page'],
						'per_page' => $this->get_collection_params()['per_page'],
					),
				),
			)
		);
	}

	/**
	 * Argument schema of create and update.
	 *
	 * @param bool $create Whether fields are required.
	 * @return array
	 */
	private function write_args( $create ) {
		$args = array(
			'title'       => array(
				'type'      => 'string',
				'minLength' => 1,
				'maxLength' => 200,
			),
			'description' => array(
				'type'      => 'string',
				'maxLength' => 5000,
			),
			'status'      => array(
				'type' => 'string',
				'enum' => array( 'draft', 'publish' ),
			),
			'provider'    => array(
				'type' => 'string',
				'enum' => self::PROVIDERS,
			),
			'start'       => array(
				'description' => __( 'Local start time in the session time zone, YYYY-MM-DDTHH:MM.', 'ohmylms' ),
				'type'        => 'string',
				'pattern'     => '^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$',
			),
			'timezone'    => array(
				'type'      => 'string',
				'maxLength' => 64,
			),
			'duration'    => array(
				'description' => __( 'Minutes.', 'ohmylms' ),
				'type'        => 'integer',
				'minimum'     => 5,
				'maximum'     => 1440,
			),
			'course_id'   => array(
				'type'    => array( 'integer', 'null' ),
				'minimum' => 1,
			),
			'password'    => array(
				'description' => __( 'Zoom passcode: up to 10 letters, digits or @ - _ *.', 'ohmylms' ),
				'type'        => 'string',
				'pattern'     => '^[A-Za-z0-9@_*-]{0,10}$',
			),
			'options'     => array(
				'type'                 => 'object',
				'properties'           => array_fill_keys( self::OPTIONS, array( 'type' => 'boolean' ) ),
				'additionalProperties' => false,
			),
		);
		if ( $create ) {
			foreach ( array( 'title', 'provider', 'start', 'timezone', 'duration' ) as $name ) {
				$args[ $name ]['required'] = true;
			}
		}
		return $args;
	}

	/**
	 * Collection parameters.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'page'     => array(
				'type'    => 'integer',
				'default' => 1,
				'minimum' => 1,
			),
			'per_page' => array(
				'type'    => 'integer',
				'default' => 20,
				'minimum' => 1,
				'maximum' => self::MAX_PER_PAGE,
			),
			'search'   => array(
				'type'      => 'string',
				'maxLength' => 100,
			),
			'provider' => array(
				'type' => 'string',
				'enum' => array_merge( array( 'all' ), self::PROVIDERS ),
			),
			'status'   => array(
				'type'    => 'string',
				'default' => 'any',
				'enum'    => array( 'any', 'draft', 'publish' ),
			),
		);
	}

	/**
	 * Forbidden error.
	 *
	 * @param string $code    Error code.
	 * @param string $message Message.
	 * @return WP_Error
	 */
	private function forbidden( $code, $message ) {
		return new WP_Error( $code, $message, array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Load a session post.
	 *
	 * @param int $id Post ID.
	 * @return WP_Post|WP_Error
	 */
	private function find( $id ) {
		$post = get_post( absint( $id ) );
		if ( ! $post || self::POST_TYPE !== $post->post_type || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
			return new WP_Error( 'ohmylms_session_not_found', __( 'Session not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		return $post;
	}

	/**
	 * List permission: authors and above.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'edit_posts' ) ? true : $this->forbidden( 'ohmylms_session_cannot_view', __( 'Sorry, you are not allowed to view sessions.', 'ohmylms' ) );
	}

	/**
	 * Create permission.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return $this->forbidden( 'ohmylms_session_cannot_create', __( 'Sorry, you are not allowed to create sessions.', 'ohmylms' ) );
		}
		if ( 'publish' === $request['status'] && ! current_user_can( 'publish_posts' ) ) {
			return $this->forbidden( 'ohmylms_session_cannot_publish', __( 'Sorry, you are not allowed to publish sessions.', 'ohmylms' ) );
		}
		return true;
	}

	/**
	 * Read permission: the user can edit this session (host data and attendees).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return $this->object_permission( $request, 'edit_post', 'ohmylms_session_cannot_view', __( 'Sorry, you are not allowed to view this session.', 'ohmylms' ) );
	}

	/**
	 * Update permission.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		$allowed = $this->object_permission( $request, 'edit_post', 'ohmylms_session_cannot_edit', __( 'Sorry, you are not allowed to edit this session.', 'ohmylms' ) );
		if ( true === $allowed && 'publish' === $request['status'] && ! current_user_can( 'publish_posts' ) ) {
			return $this->forbidden( 'ohmylms_session_cannot_publish', __( 'Sorry, you are not allowed to publish sessions.', 'ohmylms' ) );
		}
		return $allowed;
	}

	/**
	 * Delete permission.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		return $this->object_permission( $request, 'delete_post', 'ohmylms_session_cannot_delete', __( 'Sorry, you are not allowed to delete this session.', 'ohmylms' ) );
	}

	/**
	 * Check a capability on the session of the request. Unknown sessions answer 404 only to users who
	 * may edit posts at all.
	 *
	 * @param WP_REST_Request $request    Request.
	 * @param string          $capability Meta capability.
	 * @param string          $code       Error code.
	 * @param string          $message    Error message.
	 * @return true|WP_Error
	 */
	private function object_permission( $request, $capability, $code, $message ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return $this->forbidden( $code, $message );
		}
		$post = $this->find( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		return current_user_can( $capability, $post->ID ) ? true : $this->forbidden( $code, $message );
	}

	/**
	 * Course of a session: its chapter placement, else its parent course.
	 *
	 * @param WP_Post $post Session.
	 * @return int Course ID or 0.
	 */
	private function course_of( WP_Post $post ) {
		$course = function_exists( 'ohmylms_get_course_by_content_id' ) ? (int) ohmylms_get_course_by_content_id( $post->ID ) : 0;
		if ( ! $course && $post->post_parent && 'ohmylms-course' === get_post_type( $post->post_parent ) ) {
			$course = (int) $post->post_parent;
		}
		return $course;
	}

	/**
	 * Stored schedule of a session.
	 *
	 * @param WP_Post $post Session.
	 * @return array{start:?string,timezone:string,duration:int}
	 */
	private function schedule( WP_Post $post ) {
		$timezone = (string) get_post_meta( $post->ID, '_timezone', true );
		if ( ! in_array( $timezone, timezone_identifiers_list(), true ) ) {
			$timezone = wp_timezone_string();
			$timezone = in_array( $timezone, timezone_identifiers_list(), true ) ? $timezone : 'UTC';
		}
		$raw   = (string) get_post_meta( $post->ID, '_start_date', true );
		$start = null;
		if ( '' !== $raw ) {
			try {
				$start = ( new DateTimeImmutable( $raw, new DateTimeZone( $timezone ) ) )->format( 'Y-m-d\TH:i' );
			} catch ( Exception $exception ) {
				$start = null;
			}
		}
		return array(
			'start'    => $start,
			'timezone' => $timezone,
			'duration' => max( 0, (int) get_post_meta( $post->ID, '_duration', true ) ),
		);
	}

	/**
	 * Upcoming, live or ended, from the schedule.
	 *
	 * @param array $schedule Output of schedule().
	 * @return string
	 */
	private function state( array $schedule ) {
		if ( null === $schedule['start'] ) {
			return 'unscheduled';
		}
		$zone  = new DateTimeZone( $schedule['timezone'] );
		$start = new DateTimeImmutable( $schedule['start'], $zone );
		$end   = $start->add( new DateInterval( 'PT' . max( 1, $schedule['duration'] ) . 'M' ) );
		$now   = new DateTimeImmutable( 'now', $zone );
		if ( $now < $start ) {
			return 'upcoming';
		}
		return $now > $end ? 'ended' : 'live';
	}

	/**
	 * Attendee-facing join link (Zoom join_url or Meet link). Host start links are not returned.
	 *
	 * @param WP_Post $post     Session.
	 * @param string  $provider Provider.
	 * @return string|null
	 */
	private function join_url( WP_Post $post, $provider ) {
		$url = (string) get_post_meta( $post->ID, '_join_url', true );
		if ( '' === $url && 'zoom' === $provider ) {
			$data = json_decode( (string) get_post_meta( $post->ID, '_zoom_meeting_data', true ), true );
			$url  = is_array( $data ) && isset( $data['join_url'] ) ? (string) $data['join_url'] : '';
		}
		$url = esc_url_raw( $url, array( 'https' ) );
		return '' === $url ? null : $url;
	}

	/**
	 * Shape a session for the response.
	 *
	 * @param WP_Post $post   Session.
	 * @param bool    $detail Include description, passcode and options.
	 * @return array
	 */
	private function describe( WP_Post $post, $detail ) {
		$provider = (string) get_post_meta( $post->ID, '_platform', true );
		$provider = in_array( $provider, self::PROVIDERS, true ) ? $provider : null;
		$schedule = $this->schedule( $post );
		$course   = $this->course_of( $post );
		$created  = $provider && 'yes' === get_post_meta( $post->ID, '_is_' . $provider . '_session_created', true );
		$data     = array(
			'id'       => (int) $post->ID,
			'title'    => html_entity_decode( (string) $post->post_title, ENT_QUOTES, 'UTF-8' ),
			'status'   => 'publish' === $post->post_status || 'future' === $post->post_status ? 'publish' : 'draft',
			'provider' => $provider,
			'start'    => $schedule['start'],
			'timezone' => $schedule['timezone'],
			'duration' => $schedule['duration'],
			'state'    => $this->state( $schedule ),
			'course'   => $course ? array(
				'id'    => $course,
				'title' => html_entity_decode( (string) get_the_title( $course ), ENT_QUOTES, 'UTF-8' ),
			) : null,
			'meeting'  => array(
				'created'  => (bool) $created,
				'join_url' => $created ? $this->join_url( $post, (string) $provider ) : null,
			),
		);
		if ( $detail ) {
			$options = array();
			foreach ( self::OPTIONS as $option ) {
				$options[ $option ] = in_array( (string) get_post_meta( $post->ID, '_' . $option, true ), array( '1', 'true', 'yes' ), true );
			}
			$data['description'] = (string) $post->post_content;
			$data['password']    = (string) get_post_meta( $post->ID, '_password', true );
			$data['options']     = $options;
			$data['can_delete']  = current_user_can( 'delete_post', $post->ID );
		}
		return $data;
	}

	/**
	 * Paged sessions the user may edit.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$page     = max( 1, (int) $request['page'] );
		$per_page = min( self::MAX_PER_PAGE, max( 1, (int) $request['per_page'] ) );
		$args     = array(
			'post_type'              => self::POST_TYPE,
			'post_status'            => 'any' === $request['status'] ? array( 'publish', 'future', 'draft', 'pending', 'private' ) : ( 'publish' === $request['status'] ? array( 'publish', 'future' ) : array( 'draft', 'pending' ) ),
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'orderby'                => array(
				'date' => 'DESC',
				'ID'   => 'DESC',
			),
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
		);
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			$args['author'] = get_current_user_id();
		}
		$search = trim( (string) $request['search'] );
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		if ( in_array( $request['provider'], self::PROVIDERS, true ) ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Indexed meta key filter on a small post type.
				array(
					'key'   => '_platform',
					'value' => (string) $request['provider'],
				),
			);
		}

		$query = new WP_Query( $args );
		$total = (int) $query->found_posts;
		$pages = (int) ceil( $total / $per_page );
		if ( $total > 0 && $page > $pages ) {
			return new WP_Error( 'ohmylms_session_invalid_page', __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = $this->describe( $post, false );
		}
		$response = rest_ensure_response( $items );
		( new StudentController() )->add_pagination_headers( $response, $request, $total, $pages, $page, $this->rest_base );
		return $response;
	}

	/**
	 * One session.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$post = $this->find( $request['id'] );
		return is_wp_error( $post ) ? $post : $this->respond( $post );
	}

	/**
	 * Response of one session, never cached by intermediaries.
	 *
	 * @param WP_Post $post   Session.
	 * @param int     $status HTTP status.
	 * @return WP_REST_Response
	 */
	private function respond( WP_Post $post, $status = 200 ) {
		$response = rest_ensure_response( $this->describe( get_post( $post->ID ), true ) );
		$response->set_status( $status );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * Validate the merged values of a write.
	 *
	 * @param array $values Merged values.
	 * @return true|WP_Error
	 */
	private function validate( array $values ) {
		if ( '' === trim( wp_strip_all_tags( $values['title'] ) ) ) {
			return new WP_Error(
				'ohmylms_session_invalid_title',
				__( 'Enter a title.', 'ohmylms' ),
				array(
					'status' => 422,
					'field'  => 'title',
				)
			);
		}
		if ( ! in_array( $values['timezone'], timezone_identifiers_list(), true ) ) {
			return new WP_Error(
				'ohmylms_session_invalid_timezone',
				__( 'Choose a valid time zone.', 'ohmylms' ),
				array(
					'status' => 422,
					'field'  => 'timezone',
				)
			);
		}
		$parts = null === $values['start'] ? false : DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $values['start'], new DateTimeZone( $values['timezone'] ) );
		if ( ! $parts || $parts->format( 'Y-m-d\TH:i' ) !== $values['start'] ) {
			return new WP_Error(
				'ohmylms_session_invalid_start',
				__( 'Enter a valid start date and time.', 'ohmylms' ),
				array(
					'status' => 422,
					'field'  => 'start',
				)
			);
		}
		if ( $values['course_id'] ) {
			$course = get_post( $values['course_id'] );
			if ( ! $course || 'ohmylms-course' !== $course->post_type || 'trash' === $course->post_status ) {
				return new WP_Error(
					'ohmylms_session_invalid_course',
					__( 'Choose an existing course.', 'ohmylms' ),
					array(
						'status' => 422,
						'field'  => 'course_id',
					)
				);
			}
			if ( ! current_user_can( 'edit_post', $course->ID ) ) {
				return $this->forbidden( 'ohmylms_session_cannot_edit_course', __( 'Sorry, you are not allowed to add sessions to this course.', 'ohmylms' ) );
			}
		}
		if ( 'zoom' !== $values['provider'] && '' !== $values['password'] ) {
			return new WP_Error(
				'ohmylms_session_invalid_password',
				__( 'Only Zoom meetings take a passcode.', 'ohmylms' ),
				array(
					'status' => 422,
					'field'  => 'password',
				)
			);
		}
		return true;
	}

	/**
	 * Write post fields and schedule meta.
	 *
	 * @param int   $id     Post ID (0 to create).
	 * @param array $values Validated values.
	 * @return int|WP_Error
	 */
	private function write( $id, array $values ) {
		$postarr = array(
			'post_type'    => self::POST_TYPE,
			'post_title'   => sanitize_text_field( $values['title'] ),
			'post_content' => wp_kses_post( $values['description'] ),
			'post_status'  => $values['status'],
			'post_parent'  => (int) $values['course_id'],
		);
		if ( $id ) {
			$postarr['ID'] = $id;
			$result        = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$result = wp_insert_post( wp_slash( $postarr ), true );
		}
		if ( is_wp_error( $result ) || ! $result ) {
			return new WP_Error( 'ohmylms_session_save_failed', __( 'The session could not be saved.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		$start = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $values['start'], new DateTimeZone( $values['timezone'] ) );
		update_post_meta( $result, '_content_type', 'session' );
		update_post_meta( $result, '_platform', $values['provider'] );
		update_post_meta( $result, '_start_date', $start->format( 'Y-m-d H:i:s' ) );
		update_post_meta( $result, '_timezone', $values['timezone'] );
		update_post_meta( $result, '_duration', (int) $values['duration'] );
		update_post_meta( $result, '_password', $values['password'] );
		foreach ( self::OPTIONS as $option ) {
			update_post_meta( $result, '_' . $option, $values['options'][ $option ] ? '1' : '' );
		}
		return (int) $result;
	}

	/**
	 * Ask the provider integration to create or update the meeting.
	 *
	 * @param int    $id     Session ID.
	 * @param array  $values Validated values.
	 * @param string $event  `created` or `updated`.
	 * @return bool Whether the provider reported a meeting.
	 */
	private function sync_provider( $id, array $values, $event ) {
		$provider = $values['provider'];
		$flag     = '_is_' . $provider . '_session_created';
		if ( ! has_action( 'ohmylms_' . $provider . '_session_' . $event ) ) {
			return false;
		}
		$start = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $values['start'], new DateTimeZone( $values['timezone'] ) );
		$end   = $start->add( new DateInterval( 'PT' . (int) $values['duration'] . 'M' ) );
		$inner = new WP_REST_Request( 'POST', '/' . $this->namespace . '/session' );
		$inner->set_param( 'context', 'edit' );
		foreach ( array(
			'id'                => $id,
			'topic'             => sanitize_text_field( $values['title'] ),
			'agenda'            => wp_strip_all_tags( $values['description'] ),
			'platform'          => $provider,
			'date'              => $start->format( 'Y-m-d\TH:i:s' ),
			'endDate'           => $end->format( 'Y-m-d\TH:i:s' ),
			'timezone'          => $values['timezone'],
			'duration'          => (int) $values['duration'],
			'duration_unit'     => 'min',
			'password'          => $values['password'],
			'type'              => 2,
			'host_video'        => $values['options']['host_video'],
			'participant_video' => $values['options']['participant_video'],
			'join_before_host'  => $values['options']['join_before_host'],
			'mute_upon_entry'   => $values['options']['mute_upon_entry'],
			'auto_record'       => $values['options']['auto_record'],
		) as $name => $value ) {
			$inner->set_param( $name, $value );
		}

		update_post_meta( $id, $flag, 'no' );
		/** This action is documented in includes/Rest/V1/SessionController.php. */
		do_action( 'ohmylms_' . $provider . '_session_' . $event, get_post( $id ), $inner );
		return 'yes' === get_post_meta( $id, $flag, true );
	}

	/**
	 * Values of a write: stored values overridden by the request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param WP_Post|null    $post    Existing session.
	 * @return array
	 */
	private function merged( $request, $post ) {
		$stored  = $post ? $this->describe( $post, true ) : null;
		$options = array();
		foreach ( self::OPTIONS as $option ) {
			$default            = 'auto_record' === $option || 'join_before_host' === $option ? false : true;
			$options[ $option ] = $stored ? $stored['options'][ $option ] : $default;
			if ( isset( $request['options'][ $option ] ) ) {
				$options[ $option ] = (bool) $request['options'][ $option ];
			}
		}
		$pick   = static function ( $name, $fallback ) use ( $request ) {
			return null !== $request[ $name ] ? $request[ $name ] : $fallback;
		};
		$course = $stored && $stored['course'] ? $stored['course']['id'] : 0;
		if ( $request->has_param( 'course_id' ) ) {
			$course = (int) $request['course_id'];
		}
		return array(
			'title'       => (string) $pick( 'title', $stored ? $stored['title'] : '' ),
			'description' => (string) $pick( 'description', $stored ? $stored['description'] : '' ),
			'status'      => (string) $pick( 'status', $stored ? $stored['status'] : 'draft' ),
			'provider'    => (string) $pick( 'provider', $stored ? (string) $stored['provider'] : '' ),
			'start'       => $pick( 'start', $stored ? $stored['start'] : null ),
			'timezone'    => (string) $pick( 'timezone', $stored ? $stored['timezone'] : '' ),
			'duration'    => (int) $pick( 'duration', $stored ? $stored['duration'] : 60 ),
			'course_id'   => $course,
			'password'    => (string) $pick( 'password', $stored ? $stored['password'] : '' ),
			'options'     => $options,
		);
	}

	/**
	 * The provider failure error.
	 *
	 * @param string $provider Provider.
	 * @return WP_Error
	 */
	private function provider_failed( $provider ) {
		return new WP_Error(
			'ohmylms_session_provider_failed',
			'zoom' === $provider
				? __( 'Zoom did not create the meeting. Check the Zoom credentials on the Integrations screen and try again.', 'ohmylms' )
				: __( 'Google Meet did not create the meeting. Connect your Google account on the Integrations screen and try again.', 'ohmylms' ),
			array( 'status' => 502 )
		);
	}

	/**
	 * Create a session and its meeting. Nothing is kept when the provider fails.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$values = $this->merged( $request, null );
		$valid  = $this->validate( $values );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$id = $this->write( 0, $values );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		if ( ! $this->sync_provider( $id, $values, 'created' ) ) {
			wp_delete_post( $id, true );
			return $this->provider_failed( $values['provider'] );
		}

		/**
		 * Fires after a session and its meeting were created through the sessions API.
		 *
		 * @param int $id Session ID.
		 */
		do_action( 'ohmylms_session_saved', $id );
		$response = $this->respond( get_post( $id ), 201 );
		$response->header( 'Location', rest_url( $this->namespace . '/' . $this->rest_base . '/' . $id ) );
		return $response;
	}

	/**
	 * Update a session and its meeting. The previous values are restored when the provider fails.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$post = $this->find( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$before = $this->merged( new WP_REST_Request(), $post );
		$values = $this->merged( $request, $post );
		if ( $before['provider'] && $values['provider'] !== $before['provider'] ) {
			return new WP_Error( 'ohmylms_session_provider_locked', __( 'The provider of a session cannot change. Create a new session instead.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		$valid = $this->validate( $values );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$meeting = json_decode( (string) get_post_meta( $post->ID, '_zoom_meeting_data', true ), true );
		$created = 'yes' === get_post_meta( $post->ID, '_is_' . $values['provider'] . '_session_created', true );
		$event   = $created || ( 'zoom' === $values['provider'] && is_array( $meeting ) ) ? 'updated' : 'created';
		$id      = $this->write( $post->ID, $values );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		if ( ! $this->sync_provider( $id, $values, $event ) ) {
			$this->write( $post->ID, $before );
			update_post_meta( $post->ID, '_is_' . $values['provider'] . '_session_created', $created ? 'yes' : 'no' );
			return $this->provider_failed( $values['provider'] );
		}

		/** This action is documented in includes/Rest/V1/SessionsController.php. */
		do_action( 'ohmylms_session_saved', $id );
		return $this->respond( get_post( $id ) );
	}

	/**
	 * Delete a session permanently and ask the provider to delete its meeting.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$post = $this->find( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$previous = $this->describe( $post, false );
		if ( $previous['provider'] ) {
			$inner = new WP_REST_Request( 'DELETE', '/' . $this->namespace . '/session/' . $post->ID );
			$inner->set_param( 'platform', $previous['provider'] );
			/** This action is documented in includes/Rest/V1/SessionController.php. */
			do_action( 'ohmylms_' . $previous['provider'] . '_session_deleted', $post->ID, $inner );
		}
		if ( ! wp_delete_post( $post->ID, true ) ) {
			return new WP_Error( 'ohmylms_session_delete_failed', __( 'The session could not be deleted.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		/** This action is documented in includes/Rest/V1/SessionController.php. */
		do_action( 'ohmylms_rest_delete_session', $post->ID );
		return rest_ensure_response(
			array(
				'deleted'  => true,
				'previous' => $previous,
			)
		);
	}

	/**
	 * Students of the session's course, with whether they completed the session. Read only.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_attendees( $request ) {
		global $wpdb;

		$post = $this->find( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$page     = max( 1, (int) $request['page'] );
		$per_page = min( self::MAX_PER_PAGE, max( 1, (int) $request['per_page'] ) );
		$course   = $this->course_of( $post );
		$route    = $this->rest_base . '/' . $post->ID . '/attendees';
		$items    = array();
		$total    = 0;
		if ( $course ) {
			$table = $wpdb->prefix . 'ohmylms_user_enrollment';
			// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Enrolment and progress tables have no API.
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i e INNER JOIN %i u ON u.ID = e.user_id WHERE e.course_id = %d AND e.status IN ('enrolled','banned')", $table, $wpdb->users, $course ) );
			if ( $total > 0 && $page > (int) ceil( $total / $per_page ) ) {
				return new WP_Error( 'ohmylms_session_invalid_page', __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), array( 'status' => 400 ) );
			}
			$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT e.user_id, e.status, u.display_name, EXISTS ( SELECT 1 FROM %i p WHERE p.enrollment_id = e.id AND p.content_id = %d AND p.status = 'completed' ) AS attended FROM %i e INNER JOIN %i u ON u.ID = e.user_id WHERE e.course_id = %d AND e.status IN ('enrolled','banned') ORDER BY u.display_name ASC, e.user_id ASC LIMIT %d OFFSET %d", $wpdb->prefix . 'ohmylms_user_progress', $post->ID, $table, $wpdb->users, $course, $per_page, ( $page - 1 ) * $per_page ), ARRAY_A );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery
			foreach ( $rows as $row ) {
				$items[] = array(
					'user_id'   => (int) $row['user_id'],
					'name'      => (string) $row['display_name'],
					'status'    => 'banned' === $row['status'] ? 'banned' : 'enrolled',
					'completed' => (bool) $row['attended'],
				);
			}
		}
		$response = rest_ensure_response( $items );
		( new StudentController() )->add_pagination_headers( $response, $request, $total, (int) ceil( $total / $per_page ), $page, $route );
		return $response;
	}

	/**
	 * Up to 20 courses the user may add sessions to, by title.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_courses( $request ) {
		$args = array(
			'post_type'              => 'ohmylms-course',
			'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'posts_per_page'         => 20,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			$args['author'] = get_current_user_id();
		}
		$search = trim( (string) $request['search'] );
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		$items = array();
		foreach ( ( new WP_Query( $args ) )->posts as $course ) {
			if ( current_user_can( 'edit_post', $course->ID ) ) {
				$items[] = array(
					'id'    => (int) $course->ID,
					'title' => html_entity_decode( (string) $course->post_title, ENT_QUOTES, 'UTF-8' ),
				);
			}
		}
		return rest_ensure_response( $items );
	}

	/**
	 * Provider availability for the editor. Booleans only: no credential, token or hint.
	 *
	 * @return WP_REST_Response
	 */
	public function get_providers() {
		$zoom   = class_exists( IntegrationSettings::class ) && IntegrationSettings::supports( 'zoom' ) ? (bool) IntegrationSettings::describe( 'zoom' )['configured'] : false;
		$tokens = get_user_meta( get_current_user_id(), 'ohmylms_googlemeet_tokens', true );
		return rest_ensure_response(
			array(
				array(
					'id'         => 'zoom',
					'label'      => __( 'Zoom', 'ohmylms' ),
					'available'  => (bool) has_action( 'ohmylms_zoom_session_created' ),
					'configured' => $zoom,
				),
				array(
					'id'         => 'googlemeet',
					'label'      => __( 'Google Meet', 'ohmylms' ),
					'available'  => (bool) has_action( 'ohmylms_googlemeet_session_created' ),
					'configured' => is_array( $tokens ) && ! empty( $tokens['refresh_token'] ),
				),
			)
		);
	}

	/**
	 * Session schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}
		$options      = array_fill_keys( self::OPTIONS, array( 'type' => 'boolean' ) );
		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'session',
			'type'       => 'object',
			'properties' => array(
				'id'          => array(
					'type'     => 'integer',
					'readonly' => true,
				),
				'title'       => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
				'status'      => array(
					'type' => 'string',
					'enum' => array( 'draft', 'publish' ),
				),
				'provider'    => array(
					'type' => array( 'string', 'null' ),
					'enum' => array_merge( self::PROVIDERS, array( null ) ),
				),
				'start'       => array( 'type' => array( 'string', 'null' ) ),
				'timezone'    => array( 'type' => 'string' ),
				'duration'    => array( 'type' => 'integer' ),
				'state'       => array(
					'type'     => 'string',
					'enum'     => array( 'unscheduled', 'upcoming', 'live', 'ended' ),
					'readonly' => true,
				),
				'course'      => array(
					'type'       => array( 'object', 'null' ),
					'properties' => array(
						'id'    => array( 'type' => 'integer' ),
						'title' => array( 'type' => 'string' ),
					),
				),
				'meeting'     => array(
					'type'       => 'object',
					'readonly'   => true,
					'properties' => array(
						'created'  => array( 'type' => 'boolean' ),
						'join_url' => array( 'type' => array( 'string', 'null' ) ),
					),
				),
				'password'    => array( 'type' => 'string' ),
				'options'     => array(
					'type'       => 'object',
					'properties' => $options,
				),
				'can_delete'  => array(
					'type'     => 'boolean',
					'readonly' => true,
				),
			),
		);
		return $this->add_additional_fields_schema( $this->schema );
	}
}
