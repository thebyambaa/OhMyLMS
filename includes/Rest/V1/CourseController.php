<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Controller for handling course REST API endpoints.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Course\CourseHelper;
use OhMyLMS\Data\Course;
use OhMyLMS\Data\Chapter;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Controller for handling course REST API endpoints.
 *
 * Course CRUD under `ohmylms/v1/courses`. See docs/api/courses.md for the contract. Authentication is
 * WordPress core's: cookie authentication requires the `X-WP-Nonce` header (verified by
 * rest_cookie_check_errors() before any permission callback); application passwords also work.
 *
 * Every capability decision goes through {@see CourseController::can()}, per object with the
 * `edit_post`, `read_post` and `delete_post` meta capabilities, so an author can never read or change
 * another author's draft. The auxiliary routes at the end of register_routes() (enrolment, import,
 * export, builder data) are not part of the CRUD contract; they only use the hardened permission
 * callback {@see CourseController::check_legacy_permission()}.
 *
 * @since 1.0.0
 */
class CourseController extends RestController {

	/**
	 * Largest accepted price.
	 */
	const MAX_PRICE = 99999999.99;

	/**
	 * Longest course title, in characters.
	 */
	const MAX_TITLE = 200;

	/**
	 * Longest free-text field (short description, outcomes, audience, requirements), in characters.
	 */
	const MAX_TEXT = 5000;

	/**
	 * Longest access password, in characters.
	 */
	const MAX_PASSWORD = 100;

	/**
	 * Largest seat capacity.
	 */
	const MAX_CAPACITY = 1000000;

	/**
	 * Course levels (stored in `_level`, filtered by the course loop).
	 */
	const LEVELS = array( 'beginner', 'experience', 'expert', 'all' );

	/**
	 * Statuses a client may set. Trash is reached through DELETE and left through /restore.
	 */
	const WRITE_STATUSES = array( 'draft', 'pending', 'publish', 'future', 'private' );

	/**
	 * Statuses listed by `status=all`.
	 */
	const LIST_STATUSES = array( 'publish', 'draft', 'pending', 'private', 'future' );

	/**
	 * Statuses that need the publish capability.
	 */
	const PUBLISHING_STATUSES = array( 'publish', 'future', 'private' );

	/**
	 * Image types accepted as a thumbnail.
	 */
	const THUMBNAIL_MIMES = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * The base route for course endpoints.
	 *
	 * @var string
	 */
	protected $rest_base = 'courses';

	/**
	 * The base route (kept for the auxiliary routes).
	 *
	 * @var string
	 */
	protected $base = 'courses';

	/**
	 * Enrolled student counts for the courses of the current collection page, keyed by course ID.
	 *
	 * @var array<int,int>
	 */
	private $enrolled_counts = array();

	/**
	 * Single place that answers "may the current user do this?".
	 *
	 * @param string $action  `list`, `create`, `publish`, `list_others`, `read`, `edit` or `delete`.
	 * @param int    $post_id Course ID for the object-level actions.
	 * @return bool
	 */
	private function can( $action, $post_id = 0 ) {
		$caps = get_post_type_object( OHMYLMS_COURSE_CPT )->cap;
		switch ( $action ) {
			case 'list':
			case 'create':
				return current_user_can( $caps->edit_posts );
			case 'publish':
				return current_user_can( $caps->publish_posts );
			case 'list_others':
				return current_user_can( $caps->edit_others_posts );
			case 'read':
				return current_user_can( 'read_post', $post_id );
			case 'edit':
				return current_user_can( 'edit_post', $post_id );
			case 'delete':
				return current_user_can( 'delete_post', $post_id );
		}

		return false;
	}

	/**
	 * Build the permission error: 401 when logged out, 403 otherwise.
	 *
	 * @param string $code    Error code.
	 * @param string $message Message.
	 * @return WP_Error
	 */
	private function denied( $code, $message ) {
		return new WP_Error( $code, $message, array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Registers REST API routes for course operations.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		$base = '/' . $this->rest_base;
		$item = $base . '/(?P<id>[\d]+)';
		$id   = array(
			'description' => __( 'Unique identifier for the course.', 'ohmylms' ),
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
					'args'                => array(
						'force' => array(
							'description' => __( 'Delete permanently instead of moving to the trash.', 'ohmylms' ),
							'type'        => 'boolean',
							'default'     => false,
						),
					),
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
					'permission_callback' => array( $this, 'duplicate_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$item . '/restore',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'restore_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
			)
		);

		$this->register_auxiliary_routes();
	}

	/**
	 * Resolve a course or return the 404 error.
	 *
	 * @param int $id Course ID.
	 * @return WP_Post|WP_Error
	 */
	private function get_course_post( $id ) {
		$post = get_post( absint( $id ) );
		if ( ! $post || OHMYLMS_COURSE_CPT !== $post->post_type || 'auto-draft' === $post->post_status ) {
			return new WP_Error( 'ohmylms_course_not_found', __( 'Course not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		return $post;
	}

	/**
	 * Check if the current user can list courses (also the options route).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! $this->can( 'list' ) ) {
			return $this->denied( 'ohmylms_course_cannot_view', __( 'Sorry, you are not allowed to view courses.', 'ohmylms' ) );
		}

		return true;
	}

	/**
	 * Check if the current user can read one course. The edit context (description, password) needs
	 * the edit capability on that course.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		if ( ! $this->can( 'list' ) ) {
			return $this->denied( 'ohmylms_course_cannot_view', __( 'Sorry, you are not allowed to view courses.', 'ohmylms' ) );
		}
		$post = $this->get_course_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( 'edit' === $request['context'] && ! $this->can( 'edit', $post->ID ) ) {
			return $this->denied( 'ohmylms_course_cannot_edit', __( 'Sorry, you are not allowed to edit this course.', 'ohmylms' ) );
		}
		if ( ! $this->can( 'read', $post->ID ) ) {
			return $this->denied( 'ohmylms_course_cannot_view', __( 'Sorry, you are not allowed to view this course.', 'ohmylms' ) );
		}

		return true;
	}

	/**
	 * Check if the current user can create a course, including publishing it.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! $this->can( 'create' ) ) {
			return $this->denied( 'ohmylms_course_cannot_create', __( 'Sorry, you are not allowed to create courses.', 'ohmylms' ) );
		}

		return $this->check_publish_permission( $request );
	}

	/**
	 * Check if the current user can edit this course (and publish when the request asks for it).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		if ( ! $this->can( 'list' ) ) {
			return $this->denied( 'ohmylms_course_cannot_edit', __( 'Sorry, you are not allowed to edit courses.', 'ohmylms' ) );
		}
		$post = $this->get_course_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! $this->can( 'edit', $post->ID ) ) {
			return $this->denied( 'ohmylms_course_cannot_edit', __( 'Sorry, you are not allowed to edit this course.', 'ohmylms' ) );
		}

		return $this->check_publish_permission( $request );
	}

	/**
	 * Check if the current user can delete (or trash) this course.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		if ( ! $this->can( 'list' ) ) {
			return $this->denied( 'ohmylms_course_cannot_delete', __( 'Sorry, you are not allowed to delete courses.', 'ohmylms' ) );
		}
		$post = $this->get_course_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! $this->can( 'delete', $post->ID ) ) {
			return $this->denied( 'ohmylms_course_cannot_delete', __( 'Sorry, you are not allowed to delete this course.', 'ohmylms' ) );
		}

		return true;
	}

	/**
	 * Check if the current user can copy this course: create rights plus edit rights on the source,
	 * so an author cannot copy another author's draft.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function duplicate_item_permissions_check( $request ) {
		if ( ! $this->can( 'create' ) ) {
			return $this->denied( 'ohmylms_course_cannot_create', __( 'Sorry, you are not allowed to create courses.', 'ohmylms' ) );
		}
		$post = $this->get_course_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! $this->can( 'edit', $post->ID ) ) {
			return $this->denied( 'ohmylms_course_cannot_edit', __( 'Sorry, you are not allowed to copy this course.', 'ohmylms' ) );
		}

		return true;
	}

	/**
	 * Deny publishing, scheduling and making private to users without the publish capability.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	private function check_publish_permission( $request ) {
		if ( $request->has_param( 'status' ) && in_array( $request['status'], self::PUBLISHING_STATUSES, true ) && ! $this->can( 'publish' ) ) {
			return $this->denied( 'ohmylms_course_cannot_publish', __( 'Sorry, you are not allowed to publish courses. Submit it for review instead.', 'ohmylms' ) );
		}

		return true;
	}

	/**
	 * Get courses.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$page     = max( 1, (int) $request['page'] );
		$per_page = min( 100, max( 1, (int) $request['per_page'] ) );
		$orderby  = array(
			'id'         => 'ID',
			'title'      => 'title',
			'status'     => 'post_status',
			'created_at' => 'date',
			'updated_at' => 'modified',
			'price'      => 'meta_value_num',
		);
		$statuses = 'all' === $request['status'] ? self::LIST_STATUSES : array( (string) $request['status'] );

		$args = array(
			'post_type'              => OHMYLMS_COURSE_CPT,
			'post_status'            => $statuses,
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'orderby'                => $orderby[ $request['orderby'] ],
			'order'                  => strtoupper( (string) $request['order'] ),
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
		);
		if ( 'price' === $request['orderby'] ) {
			$args['meta_key'] = '_price'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Needed to sort by price.
		}
		if ( '' !== (string) $request['search'] ) {
			$args['s'] = (string) $request['search'];
		}
		if ( ! empty( $request['include'] ) ) {
			$args['post__in'] = array_map( 'absint', (array) $request['include'] );
		}

		// Users who cannot edit others' courses only ever see their own, in every status.
		if ( ! $this->can( 'list_others' ) ) {
			$args['author'] = get_current_user_id();
		} elseif ( ! empty( $request['author'] ) ) {
			$args['author'] = absint( $request['author'] );
		}

		$meta_query = array();
		if ( 'paid' === $request['price_type'] ) {
			$meta_query[] = array(
				'key'   => '_price_type',
				'value' => 'paid',
			);
		} elseif ( 'free' === $request['price_type'] ) {
			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key'   => '_price_type',
					'value' => 'free',
				),
				array(
					'key'     => '_price_type',
					'compare' => 'NOT EXISTS',
				),
			);
		}
		if ( ! empty( $request['level'] ) ) {
			$meta_query[] = array(
				'key'   => '_level',
				'value' => (string) $request['level'],
			);
		}
		if ( $meta_query ) {
			$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded by the post type and paging.
		}

		$date_query = array();
		if ( ! empty( $request['created_after'] ) ) {
			$date_query['after'] = (string) $request['created_after'] . ' 00:00:00';
		}
		if ( ! empty( $request['created_before'] ) ) {
			$date_query['before'] = (string) $request['created_before'] . ' 23:59:59';
		}
		if ( $date_query ) {
			$date_query['inclusive'] = true;
			$args['date_query']      = array( $date_query );
		}

		/**
		 * Filters the WP_Query arguments used by GET /courses.
		 *
		 * @param array           $args    Query arguments.
		 * @param WP_REST_Request $request The request.
		 */
		$args = apply_filters( 'ohmylms_rest_ohmylms_course_query', $args, $request );

		$query = new WP_Query( $args );
		$total = $this->found_posts( $query, $args );
		$pages = (int) ceil( $total / $per_page );
		if ( $page > $pages && $total > 0 ) {
			return new WP_Error( 'ohmylms_course_invalid_page', __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$this->prime_page( $query->posts );

		$data = array();
		foreach ( $query->posts as $post ) {
			$data[] = $this->prepare_response_for_collection( $this->prepare_item_for_response( $post, $request ) );
		}
		$this->enrolled_counts = array();

		$response = rest_ensure_response( $data );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $pages );

		$base = add_query_arg( urlencode_deep( $request->get_query_params() ), rest_url( $this->namespace . '/' . $this->rest_base ) );
		if ( $page > 1 ) {
			$response->link_header( 'prev', add_query_arg( 'page', $page - 1, $base ) );
		}
		if ( $page < $pages ) {
			$response->link_header( 'next', add_query_arg( 'page', $page + 1, $base ) );
		}

		return $response;
	}

	/**
	 * Total matches, also when the requested page is past the end (WP_Query then reports zero).
	 *
	 * @param WP_Query $query Executed query.
	 * @param array    $args  Its arguments.
	 * @return int
	 */
	private function found_posts( WP_Query $query, array $args ) {
		if ( $query->found_posts > 0 || (int) $args['paged'] < 2 ) {
			return (int) $query->found_posts;
		}

		$count = new WP_Query(
			array_merge(
				$args,
				array(
					'paged'          => 1,
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			)
		);

		return (int) $count->found_posts;
	}

	/**
	 * Warm every cache the page needs with one query each: thumbnails, authors and enrolment counts.
	 *
	 * @param WP_Post[] $posts Courses on the page.
	 * @return void
	 */
	private function prime_page( array $posts ) {
		$ids = wp_list_pluck( $posts, 'ID' );
		if ( ! $ids ) {
			return;
		}
		$thumbs = array();
		foreach ( $ids as $id ) {
			$thumb = (int) get_post_meta( $id, '_thumbnail_id', true );
			if ( $thumb ) {
				$thumbs[] = $thumb;
			}
		}
		if ( $thumbs ) {
			_prime_post_caches( array_values( array_unique( $thumbs ) ), false, true );
		}
		if ( $this->can( 'list_others' ) ) {
			cache_users( array_values( array_unique( array_map( 'intval', wp_list_pluck( $posts, 'post_author' ) ) ) ) );
		}
		$this->enrolled_counts = $this->count_enrolled( $ids );
	}

	/**
	 * Count enrolled students for several courses with one query.
	 *
	 * @param int[] $ids Course IDs.
	 * @return array<int,int>
	 */
	private function count_enrolled( array $ids ) {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( ! $ids ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT course_id, COUNT(*) AS total FROM %i WHERE status = 'enrolled' AND course_id IN ({$placeholders}) GROUP BY course_id", array_merge( array( $wpdb->prefix . 'ohmylms_user_enrollment' ), $ids ) ) );
		$counts = array_fill_keys( $ids, 0 );
		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row->course_id ] = (int) $row->total;
		}

		return $counts;
	}

	/**
	 * Get a specific course.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$post = $this->get_course_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$response = $this->prepare_item_for_response( $post, $request );
		$response->link_header( 'alternate', get_permalink( $post ), array( 'type' => 'text/html' ) );

		return $response;
	}

	/**
	 * Create a course.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		if ( ! empty( $request['id'] ) ) {
			return new WP_Error( 'ohmylms_course_exists', __( 'Cannot create an existing course.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$plan = $this->plan_write( $request, null );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}

		/**
		 * Fires before a course is created via the REST API.
		 *
		 * @param WP_REST_Request $request The request.
		 */
		do_action( 'ohmylms_rest_before_create_course', $request );

		$postarr                = $plan['post'];
		$postarr['post_type']   = OHMYLMS_COURSE_CPT;
		$postarr['post_author'] = get_current_user_id();
		$course_id              = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $course_id ) || ! $course_id ) {
			return new WP_Error( 'ohmylms_course_create_failed', __( 'The course could not be created.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		$this->write_meta( $course_id, $plan );
		clean_post_cache( $course_id );
		$post   = get_post( $course_id );
		$course = ohmylms_get_course( $course_id );

		/**
		 * Fires after a new course is created (data store event).
		 *
		 * @param int    $course_id Course ID.
		 * @param Course $course    Course object.
		 */
		do_action( 'ohmylms_new_course', $course_id, $course );
		if ( ! get_option( 'ohmylms_first_course_created', false ) ) {
			do_action( 'ohmylms_after_creating_first_course', $course_id, $course );
		}

		/**
		 * Fires after a course is inserted via the REST API.
		 *
		 * @param WP_Post         $post      The course post.
		 * @param int             $course_id The course ID.
		 * @param WP_REST_Request $request   The request.
		 */
		do_action( 'ohmylms_rest_insert_course', $post, $course_id, $request );

		/**
		 * Fires after a course is created via the REST API.
		 *
		 * @param WP_Post         $post      The course post.
		 * @param int             $course_id The course ID.
		 * @param WP_REST_Request $request   The request.
		 */
		do_action( 'ohmylms_rest_after_create_course', $post, $course_id, $request );

		$request->set_param( 'context', 'edit' );
		$response = $this->prepare_item_for_response( get_post( $course_id ), $request );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $course_id ) ) );

		return $response;
	}

	/**
	 * Update a course. PUT, PATCH and POST all behave as a partial update.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$post = $this->get_course_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( 'trash' === $post->post_status ) {
			return new WP_Error( 'ohmylms_course_trashed', __( 'This course is in the trash. Restore it before editing.', 'ohmylms' ), array( 'status' => 409 ) );
		}

		$plan = $this->plan_write( $request, $post );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}

		/**
		 * Fires before a course is updated via the REST API.
		 *
		 * @param int             $post_id The course ID.
		 * @param WP_REST_Request $request The request.
		 */
		do_action( 'ohmylms_rest_before_update_course', $post->ID, $request );

		if ( $plan['post'] ) {
			$postarr = array_merge( $plan['post'], array( 'ID' => $post->ID ) );
			$result  = wp_update_post( wp_slash( $postarr ), true );
			if ( is_wp_error( $result ) || ! $result ) {
				return new WP_Error( 'ohmylms_course_update_failed', __( 'The course could not be updated.', 'ohmylms' ), array( 'status' => 500 ) );
			}
		}
		$this->write_meta( $post->ID, $plan );
		clean_post_cache( $post->ID );
		$post   = get_post( $post->ID );
		$course = ohmylms_get_course( $post->ID );

		/**
		 * Fires after a course is updated (data store event).
		 *
		 * @param int    $course_id Course ID.
		 * @param Course $course    Course object.
		 */
		do_action( 'ohmylms_update_course', $post->ID, $course );

		/**
		 * Fires after a course is updated via the REST API.
		 *
		 * @param WP_Post         $post      The course post.
		 * @param int             $course_id The course ID.
		 * @param WP_REST_Request $request   The request.
		 */
		do_action( 'ohmylms_rest_update_course', $post, $post->ID, $request );
		do_action( 'ohmylms_rest_after_update_course', $post, $post->ID, $request );

		$request->set_param( 'context', 'edit' );

		return $this->prepare_item_for_response( $post, $request );
	}

	/**
	 * Trash a course, or delete it permanently with `force=true` (refused while students are enrolled).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$post = $this->get_course_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$id = $post->ID;

		if ( ! $request['force'] ) {
			if ( 'trash' === $post->post_status ) {
				return new WP_Error( 'ohmylms_course_already_trashed', __( 'The course is already in the trash.', 'ohmylms' ), array( 'status' => 409 ) );
			}
			if ( ! wp_trash_post( $id ) ) {
				return new WP_Error( 'ohmylms_course_delete_failed', __( 'The course could not be moved to the trash.', 'ohmylms' ), array( 'status' => 500 ) );
			}
			do_action( 'ohmylms_trash_course', $id );
			$request->set_param( 'context', 'edit' );

			return $this->prepare_item_for_response( get_post( $id ), $request );
		}

		$students = $this->count_enrolled( array( $id ) );
		if ( ! empty( $students[ $id ] ) ) {
			return new WP_Error(
				'ohmylms_course_has_students',
				__( 'This course has enrolled students and cannot be deleted permanently. Move it to the trash or unpublish it instead.', 'ohmylms' ),
				array(
					'status'   => 409,
					'students' => $students[ $id ],
				)
			);
		}

		$request->set_param( 'context', 'edit' );
		$previous = $this->prepare_item_for_response( $post, $request )->get_data();

		/**
		 * Fires before a course is deleted permanently via the REST API.
		 *
		 * @param int             $course_id Course ID.
		 * @param Course|null     $course    Course object.
		 * @param WP_REST_Request $request   The request.
		 */
		do_action( 'ohmylms_rest_before_delete_course', $id, ohmylms_get_course( $id ), $request );

		if ( ! wp_delete_post( $id, true ) ) {
			return new WP_Error( 'ohmylms_course_delete_failed', __( 'The course could not be deleted.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		do_action( 'ohmylms_delete_course', $id );

		/**
		 * Fires after a course is deleted permanently via the REST API.
		 *
		 * @param int             $course_id Course ID.
		 * @param WP_REST_Request $request   The request.
		 */
		do_action( 'ohmylms_rest_delete_course', $id, $request );
		do_action( 'ohmylms_rest_after_delete_course', $id, null, $request );

		return rest_ensure_response(
			array(
				'deleted'  => true,
				'previous' => $previous,
			)
		);
	}

	/**
	 * Restore a trashed course (WordPress puts it back as a draft).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function restore_item( $request ) {
		$post = $this->get_course_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( 'trash' !== $post->post_status ) {
			return new WP_Error( 'ohmylms_course_not_trashed', __( 'The course is not in the trash.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		if ( ! wp_untrash_post( $post->ID ) ) {
			return new WP_Error( 'ohmylms_course_update_failed', __( 'The course could not be restored.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		$request->set_param( 'context', 'edit' );

		return $this->prepare_item_for_response( get_post( $post->ID ), $request );
	}

	/**
	 * Duplicate a course as a draft owned by the current user (content, settings and thumbnail; no
	 * students).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function duplicate_item( $request ) {
		$post = $this->get_course_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( 'trash' === $post->post_status ) {
			return new WP_Error( 'ohmylms_course_trashed', __( 'This course is in the trash. Restore it before copying.', 'ohmylms' ), array( 'status' => 409 ) );
		}

		try {
			$copier = new \OhMyLMS\Duplicate\Course( $post->ID );
			$new_id = \OhMyLMS\Learning\UnitSnapshots::authoring( array( $copier, 'duplicate' ) );
		} catch ( \Exception $e ) {
			$new_id = 0;
		}
		if ( ! $new_id || is_wp_error( $new_id ) ) {
			return new WP_Error( 'ohmylms_course_duplicate_failed', __( 'The course could not be duplicated.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		// The copy keeps its access type, so it must keep the password too, and its address stays unique.
		$copy = get_post( $new_id );
		wp_update_post(
			wp_slash(
				array(
					'ID'            => $new_id,
					'post_password' => 'private' === $post->post_status ? '' : $post->post_password,
					'post_name'     => wp_unique_post_slug( $copy->post_name, $new_id, 'publish', OHMYLMS_COURSE_CPT, 0 ),
				)
			)
		);
		clean_post_cache( $new_id );

		$request->set_param( 'context', 'edit' );
		$response = $this->prepare_item_for_response( get_post( $new_id ), $request );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $new_id ) ) );

		return $response;
	}

	/**
	 * Choices, currency settings, limits and the current user's abilities for the editor.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_options( $request ) {
		$can_publish = $this->can( 'publish' );
		$statuses    = array(
			array(
				'value' => 'draft',
				'label' => __( 'Draft', 'ohmylms' ),
			),
			array(
				'value' => 'pending',
				'label' => __( 'Pending review', 'ohmylms' ),
			),
		);
		if ( $can_publish ) {
			$statuses[] = array(
				'value' => 'publish',
				'label' => __( 'Published', 'ohmylms' ),
			);
			$statuses[] = array(
				'value' => 'future',
				'label' => __( 'Scheduled', 'ohmylms' ),
			);
			$statuses[] = array(
				'value' => 'private',
				'label' => __( 'Private', 'ohmylms' ),
			);
		}

		return rest_ensure_response(
			array(
				'statuses'     => $statuses,
				'levels'       => array(
					array(
						'value' => 'beginner',
						'label' => __( 'Beginner', 'ohmylms' ),
					),
					array(
						'value' => 'experience',
						'label' => __( 'Intermediate', 'ohmylms' ),
					),
					array(
						'value' => 'expert',
						'label' => __( 'Expert', 'ohmylms' ),
					),
					array(
						'value' => 'all',
						'label' => __( 'All levels', 'ohmylms' ),
					),
				),
				'price_types'  => array(
					array(
						'value' => 'free',
						'label' => __( 'Free', 'ohmylms' ),
					),
					array(
						'value' => 'paid',
						'label' => __( 'Paid', 'ohmylms' ),
					),
				),
				'access_types' => array(
					array(
						'value' => 'public',
						'label' => __( 'Open to everyone who enrols', 'ohmylms' ),
					),
					array(
						'value' => 'password_protected',
						'label' => __( 'Protected by a password', 'ohmylms' ),
					),
				),
				'currency'     => array(
					'code'     => (string) get_ohmylms_currency(),
					'symbol'   => html_entity_decode( (string) get_ohmylms_currency_symbol( get_ohmylms_currency() ), ENT_QUOTES, 'UTF-8' ),
					'position' => (string) get_ohmylms_currency_position(),
					'decimals' => (int) ohmylms_get_price_decimals(),
				),
				'limits'       => array(
					'max_title'    => self::MAX_TITLE,
					'max_text'     => self::MAX_TEXT,
					'max_price'    => self::MAX_PRICE,
					'max_capacity' => self::MAX_CAPACITY,
					'max_password' => self::MAX_PASSWORD,
				),
				'abilities'    => array(
					'publish'      => $can_publish,
					'edit_others'  => $this->can( 'list_others' ),
					'upload_files' => current_user_can( 'upload_files' ),
					'builder_url'  => current_user_can( 'manage_options' ) ? admin_url( 'admin.php?page=ohmylms#/courses/' ) : '',
				),
			)
		);
	}

	/**
	 * Validate a create or update request as a whole and turn it into the writes to perform. Nothing is
	 * written here, so a rejected request changes nothing.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param WP_Post|null    $post    Existing course, or null when creating.
	 * @return array|WP_Error `post` (wp_insert_post fields), `meta` (meta key => value or null to delete) and
	 *                        `thumbnail` (attachment ID, 0 to remove, or null to leave).
	 */
	private function plan_write( WP_REST_Request $request, $post ) {
		$is_new      = null === $post;
		$decimals    = (int) ohmylms_get_price_decimals();
		$id          = $is_new ? 0 : $post->ID;
		$stored      = static function ( $key, $fallback = '' ) use ( $id ) {
			$value = $id ? get_post_meta( $id, $key, true ) : '';
			return '' === $value ? $fallback : $value;
		};
		$fail        = static function ( $code, $message, $status = 422, $extra = array() ) {
			return new WP_Error( $code, $message, array_merge( array( 'status' => $status ), $extra ) );
		};
		$post_fields = array();
		$meta        = array();

		// Title, slug, description.
		$title = $is_new ? '' : $post->post_title;
		if ( $request->has_param( 'title' ) || $is_new ) {
			$title = trim( sanitize_text_field( (string) $request['title'] ) );
			if ( '' === $title || mb_strlen( $title ) > self::MAX_TITLE ) {
				return $fail( 'ohmylms_course_invalid_title', __( 'Enter a title for the course.', 'ohmylms' ) );
			}
			$post_fields['post_title'] = $title;
		}
		if ( $request->has_param( 'description' ) ) {
			$post_fields['post_content'] = wp_kses_post( (string) $request['description'] );
		}
		foreach ( array(
			'excerpt'           => 'post_excerpt',
			'learning_outcomes' => '_benefit_description',
			'target_audience'   => '_benefiter_description',
			'requirements'      => '_requirement',
		) as $field => $target ) {
			if ( ! $request->has_param( $field ) ) {
				continue;
			}
			$text = sanitize_textarea_field( (string) $request[ $field ] );
			if ( mb_strlen( $text ) > self::MAX_TEXT ) {
				return $fail( 'ohmylms_course_invalid_text', __( 'The text is too long.', 'ohmylms' ), 422, array( 'field' => $field ) );
			}
			if ( 0 === strpos( $target, 'post_' ) ) {
				$post_fields[ $target ] = $text;
			} else {
				$meta[ $target ] = '' === $text ? null : $text;
			}
		}

		// Status and schedule.
		$status      = $is_new ? 'draft' : $post->post_status;
		$status_set  = $request->has_param( 'status' );
		$schedule_in = $request->has_param( 'scheduled_at' ) ? $request['scheduled_at'] : null;
		if ( $status_set ) {
			$status = (string) $request['status'];
		}
		if ( 'future' === $status && ( $status_set || null !== $schedule_in ) ) {
			$when = null === $schedule_in ? null : $this->parse_local_datetime( (string) $schedule_in );
			if ( ! $when || $when <= current_datetime() ) {
				return $fail( 'ohmylms_course_invalid_schedule', __( 'Choose a date and time in the future to schedule the course.', 'ohmylms' ), 422, array( 'field' => 'scheduled_at' ) );
			}
			$post_fields['post_date']     = $when->format( 'Y-m-d H:i:s' );
			$post_fields['post_date_gmt'] = get_gmt_from_date( $post_fields['post_date'] );
		} elseif ( null !== $schedule_in && 'future' !== $status ) {
			return $fail( 'ohmylms_course_invalid_schedule', __( 'A schedule date only applies to scheduled courses.', 'ohmylms' ), 422, array( 'field' => 'scheduled_at' ) );
		}
		if ( $status_set || $is_new ) {
			if ( ! $is_new && 'future' === $post->post_status && 'publish' === $status ) {
				$post_fields['post_date']     = current_time( 'mysql' );
				$post_fields['post_date_gmt'] = current_time( 'mysql', true );
			}
			$post_fields['post_status'] = $status;
		}

		// Slug: always unique across courses, drafts included.
		if ( $request->has_param( 'slug' ) ) {
			$slug = sanitize_title( (string) $request['slug'] );
			if ( '' === $slug ) {
				return $fail( 'ohmylms_course_invalid_slug', __( 'Enter a slug with letters or numbers.', 'ohmylms' ), 422, array( 'field' => 'slug' ) );
			}
			$post_fields['post_name'] = wp_unique_post_slug( $slug, $id, 'publish', OHMYLMS_COURSE_CPT, 0 );
		} elseif ( $is_new || '' === $post->post_name ) {
			$post_fields['post_name'] = wp_unique_post_slug( sanitize_title( $title ), $id, 'publish', OHMYLMS_COURSE_CPT, 0 );
		}

		// Level, reviews, availability, duration.
		if ( $request->has_param( 'level' ) || $is_new ) {
			$meta['_level'] = $request->has_param( 'level' ) ? (string) $request['level'] : 'beginner';
		}
		if ( $request->has_param( 'enable_reviews' ) || $is_new ) {
			$meta['_enable_reviews'] = ( $request->has_param( 'enable_reviews' ) ? (bool) $request['enable_reviews'] : true ) ? '1' : '0';
		}
		if ( $request->has_param( 'available_date' ) ) {
			$date = $request['available_date'];
			if ( null !== $date && '' !== $date && $this->valid_date( (string) $date ) !== (string) $date ) {
				return $fail( 'ohmylms_course_invalid_date', __( 'Dates must be real calendar dates (YYYY-MM-DD).', 'ohmylms' ), 422, array( 'field' => 'available_date' ) );
			}
			$meta['_available_date'] = ( null === $date || '' === $date ) ? null : (string) $date;
		}
		if ( $request->has_param( 'duration' ) ) {
			$duration          = $request['duration'];
			$hours             = is_array( $duration ) ? (int) ( $duration['hours'] ?? 0 ) : 0;
			$minutes           = is_array( $duration ) ? (int) ( $duration['minutes'] ?? 0 ) : 0;
			$meta['_duration'] = ( $hours || $minutes ) ? array(
				'hour' => $hours,
				'min'  => $minutes,
			) : null;
		}

		// Pricing, merged with the stored values so a partial update is validated as a whole.
		$price_type = $request->has_param( 'price_type' ) ? (string) $request['price_type'] : (string) $stored( '_price_type', 'free' );
		$regular    = $request->has_param( 'regular_price' ) ? $request['regular_price'] : $stored( '_regular_price', null );
		$sale       = $request->has_param( 'sale_price' ) ? $request['sale_price'] : $stored( '_sale_price', null );
		$from       = $request->has_param( 'sale_start_date' ) ? $request['sale_start_date'] : $this->date_from_timestamp( $stored( '_sale_price_dates_from', '' ) );
		$to         = $request->has_param( 'sale_end_date' ) ? $request['sale_end_date'] : $this->date_from_timestamp( $stored( '_sale_price_dates_to', '' ) );
		$regular    = ( null === $regular || '' === (string) $regular ) ? null : (string) $regular;
		$sale       = ( null === $sale || '' === (string) $sale ) ? null : (string) $sale;
		$from       = ( null === $from || '' === (string) $from ) ? null : (string) $from;
		$to         = ( null === $to || '' === (string) $to ) ? null : (string) $to;

		if ( 'paid' === $price_type ) {
			foreach ( array(
				'regular_price' => $regular,
				'sale_price'    => $sale,
			) as $field => $value ) {
				if ( null !== $value && ! $this->valid_price( $value, $decimals ) ) {
					return $fail(
						'ohmylms_course_invalid_price',
						/* translators: %d: number of decimal places. */
						sprintf( __( 'Prices must be zero or greater with at most %d decimal places.', 'ohmylms' ), $decimals ),
						422,
						array( 'field' => $field )
					);
				}
			}
			if ( null === $regular || (float) $regular <= 0 ) {
				return $fail( 'ohmylms_course_paid_needs_price', __( 'A paid course needs a regular price above zero.', 'ohmylms' ), 422, array( 'field' => 'regular_price' ) );
			}
			if ( null !== $sale && (float) $sale > (float) $regular ) {
				return $fail( 'ohmylms_course_sale_price_too_high', __( 'The sale price cannot be higher than the regular price.', 'ohmylms' ), 422, array( 'field' => 'sale_price' ) );
			}
			foreach ( array( $from, $to ) as $date ) {
				if ( null !== $date && $this->valid_date( $date ) !== $date ) {
					return $fail( 'ohmylms_course_invalid_sale_dates', __( 'Sale dates must be real calendar dates (YYYY-MM-DD).', 'ohmylms' ) );
				}
			}
			if ( null !== $from && null !== $to && $from > $to ) {
				return $fail( 'ohmylms_course_invalid_sale_dates', __( 'The sale end date must be on or after its start date.', 'ohmylms' ) );
			}
			if ( null === $sale && ( null !== $from || null !== $to ) ) {
				return $fail( 'ohmylms_course_sale_dates_without_price', __( 'Sale dates need a sale price.', 'ohmylms' ), 422, array( 'field' => 'sale_price' ) );
			}
			if ( null !== $sale && ( null === $from ) !== ( null === $to ) ) {
				return $fail( 'ohmylms_course_sale_dates_incomplete', __( 'A sale needs both a start and an end date, or neither.', 'ohmylms' ), 422, array( 'field' => 'sale_end_date' ) );
			}
		} elseif ( null !== $sale || null !== $from || null !== $to ) {
			if ( $request->has_param( 'sale_price' ) || $request->has_param( 'sale_start_date' ) || $request->has_param( 'sale_end_date' ) ) {
				return $fail( 'ohmylms_course_sale_on_free', __( 'A free course cannot have a sale price.', 'ohmylms' ), 422, array( 'field' => 'sale_price' ) );
			}
			// Switching to free clears the stored sale.
			$sale = null;
			$from = null;
			$to   = null;
		}

		$touches_price = $is_new || $request->has_param( 'price_type' ) || $request->has_param( 'regular_price' ) || $request->has_param( 'sale_price' ) || $request->has_param( 'sale_start_date' ) || $request->has_param( 'sale_end_date' );
		if ( $touches_price ) {
			$free                           = 'paid' !== $price_type;
			$regular_out                    = $free ? 0 : $regular;
			$meta['_price_type']            = $price_type;
			$meta['_regular_price']         = number_format( (float) $regular_out, $decimals, '.', '' );
			$meta['_sale_price']            = null === $sale ? null : number_format( (float) $sale, $decimals, '.', '' );
			$meta['_sale_price_dates_from'] = null === $from ? null : $this->timestamp_of_date( $from );
			$meta['_sale_price_dates_to']   = null === $to ? null : $this->timestamp_of_date( $to );
			$meta['_price']                 = $this->current_price( $free, $meta['_regular_price'], $meta['_sale_price'], $from, $to, $decimals );
		}

		// Access and password.
		$access = $request->has_param( 'access_type' ) ? (string) $request['access_type'] : (string) $stored( '_access_type', 'public' );
		if ( $is_new && ! $request->has_param( 'access_type' ) ) {
			$access = 'public';
		}
		$existing_password = $is_new ? '' : (string) $post->post_password;
		$password          = $existing_password;
		if ( $request->has_param( 'password' ) && '' !== (string) $request['password'] ) {
			$password = (string) $request['password'];
			if ( '' === trim( $password ) || mb_strlen( $password ) > self::MAX_PASSWORD ) {
				return $fail( 'ohmylms_course_invalid_password', __( 'Enter a password of 1 to 100 characters.', 'ohmylms' ), 422, array( 'field' => 'password' ) );
			}
		}
		if ( 'password_protected' === $access ) {
			if ( '' === $password ) {
				return $fail( 'ohmylms_course_password_required', __( 'Enter a password for this course.', 'ohmylms' ), 422, array( 'field' => 'password' ) );
			}
			if ( 'private' === $status ) {
				return $fail( 'ohmylms_course_private_password', __( 'A private course cannot also be protected by a password.', 'ohmylms' ), 422, array( 'field' => 'access_type' ) );
			}
		} else {
			$password = '';
		}
		if ( $is_new || $request->has_param( 'access_type' ) || $request->has_param( 'password' ) || 'private' === $status ) {
			$meta['_access_type']         = $access;
			$post_fields['post_password'] = 'private' === $status ? '' : $password;
		}

		// Capacity.
		$has_capacity = $request->has_param( 'has_capacity' ) ? (bool) $request['has_capacity'] : (bool) $stored( '_has_capacity', false );
		$capacity     = $request->has_param( 'capacity' ) ? $request['capacity'] : $stored( '_capacity', null );
		$capacity     = ( null === $capacity || '' === $capacity ) ? null : (int) $capacity;
		if ( $has_capacity && ( null === $capacity || $capacity < 1 || $capacity > self::MAX_CAPACITY ) ) {
			return $fail( 'ohmylms_course_invalid_capacity', __( 'Enter how many students can enrol.', 'ohmylms' ), 422, array( 'field' => 'capacity' ) );
		}
		if ( $is_new || $request->has_param( 'has_capacity' ) || $request->has_param( 'capacity' ) ) {
			$meta['_has_capacity'] = $has_capacity ? '1' : null;
			$meta['_capacity']     = $has_capacity ? $capacity : null;
		}

		// Thumbnail.
		$thumbnail = null;
		if ( $request->has_param( 'thumbnail_id' ) ) {
			$thumbnail = (int) $request['thumbnail_id'];
			if ( $thumbnail ) {
				$attachment = get_post( $thumbnail );
				if ( ! $attachment || 'attachment' !== $attachment->post_type || ! in_array( get_post_mime_type( $attachment ), self::THUMBNAIL_MIMES, true ) ) {
					return $fail( 'ohmylms_course_invalid_thumbnail', __( 'The thumbnail must be a JPEG, PNG, GIF or WebP image from the media library.', 'ohmylms' ), 422, array( 'field' => 'thumbnail_id' ) );
				}
				if ( ! current_user_can( 'edit_post', $thumbnail ) ) {
					return $this->denied( 'ohmylms_course_thumbnail_forbidden', __( 'Sorry, you are not allowed to use this image.', 'ohmylms' ) );
				}
			}
		}

		return array(
			'post'      => $post_fields,
			'meta'      => $meta,
			'thumbnail' => $thumbnail,
		);
	}

	/**
	 * Write the metadata and thumbnail of a validated plan.
	 *
	 * @param int   $course_id Course ID.
	 * @param array $plan      Result of plan_write().
	 * @return void
	 */
	private function write_meta( $course_id, array $plan ) {
		foreach ( $plan['meta'] as $key => $value ) {
			if ( null === $value ) {
				delete_post_meta( $course_id, $key );
			} else {
				update_post_meta( $course_id, $key, is_string( $value ) ? wp_slash( $value ) : $value );
			}
		}
		if ( null !== $plan['thumbnail'] ) {
			if ( $plan['thumbnail'] ) {
				update_post_meta( $course_id, '_thumbnail_id', $plan['thumbnail'] );
			} else {
				delete_post_meta( $course_id, '_thumbnail_id' );
			}
		}
	}

	/**
	 * Whether a string is a fixed-decimal money amount within the allowed range.
	 *
	 * @param string $value    Candidate.
	 * @param int    $decimals Decimal places allowed.
	 * @return bool
	 */
	private function valid_price( $value, $decimals ) {
		if ( ! preg_match( '/^\d{1,8}(\.\d+)?$/', $value ) ) {
			return false;
		}
		$places = strlen( (string) ( explode( '.', $value )[1] ?? '' ) );

		return $places <= $decimals && (float) $value <= self::MAX_PRICE;
	}

	/**
	 * Return the date unchanged when it is a real `YYYY-MM-DD` calendar date, otherwise an empty string.
	 *
	 * @param string $value Candidate.
	 * @return string
	 */
	private function valid_date( $value ) {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $value, $parts ) || ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ) {
			return '';
		}

		return (string) $value;
	}

	/**
	 * Parse a site-local `YYYY-MM-DDTHH:MM[:SS]` string.
	 *
	 * @param string $value Candidate.
	 * @return \DateTimeImmutable|null
	 */
	private function parse_local_datetime( $value ) {
		if ( ! preg_match( '/^(\d{4}-\d{2}-\d{2})T(\d{2}):(\d{2})(?::(\d{2}))?$/', $value, $parts ) || ! $this->valid_date( $parts[1] ) || (int) $parts[2] > 23 || (int) $parts[3] > 59 ) {
			return null;
		}
		$date = date_create_immutable( $parts[1] . ' ' . $parts[2] . ':' . $parts[3] . ':' . ( $parts[4] ?? '00' ), wp_timezone() );

		return $date ? $date : null;
	}

	/**
	 * Timestamp of the start of a site-local calendar day.
	 *
	 * @param string $date `YYYY-MM-DD`.
	 * @return int
	 */
	private function timestamp_of_date( $date ) {
		$value = date_create_immutable( $date . ' 00:00:00', wp_timezone() );

		return $value ? $value->getTimestamp() : 0;
	}

	/**
	 * Site-local calendar date of a stored timestamp, or an empty string.
	 *
	 * @param mixed $timestamp Stored `_sale_price_dates_*` value.
	 * @return string
	 */
	private function date_from_timestamp( $timestamp ) {
		if ( ! is_numeric( $timestamp ) || (int) $timestamp <= 0 ) {
			return '';
		}

		return wp_date( 'Y-m-d', (int) $timestamp );
	}

	/**
	 * The price that applies today.
	 *
	 * @param bool        $free     Whether the course is free.
	 * @param string      $regular  Regular price.
	 * @param string|null $sale     Sale price.
	 * @param string|null $from     Sale start date.
	 * @param string|null $to       Sale end date.
	 * @param int         $decimals Decimal places.
	 * @return string
	 */
	private function current_price( $free, $regular, $sale, $from, $to, $decimals ) {
		if ( $free ) {
			return number_format( 0, $decimals, '.', '' );
		}
		if ( null !== $sale ) {
			$today = current_time( 'Y-m-d' );
			if ( ( null === $from || $today >= $from ) && ( null === $to || $today <= $to ) ) {
				return $sale;
			}
		}

		return $regular;
	}

	/**
	 * Format a stored price as a fixed-decimal string, or null when empty.
	 *
	 * @param mixed $value Stored price.
	 * @return string|null
	 */
	private function price_string( $value ) {
		if ( null === $value || '' === (string) $value || ! is_numeric( $value ) ) {
			return null;
		}

		return number_format( (float) $value, (int) ohmylms_get_price_decimals(), '.', '' );
	}

	/**
	 * Prepare a course for the response.
	 *
	 * @param WP_Post         $item    Course post.
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		$post     = $item;
		$id       = $post->ID;
		$meta     = static function ( $key, $fallback = '' ) use ( $id ) {
			$value = get_post_meta( $id, $key, true );
			return '' === $value ? $fallback : $value;
		};
		$free     = 'paid' !== $meta( '_price_type', 'free' );
		$regular  = $free ? $this->price_string( 0 ) : $this->price_string( $meta( '_regular_price', 0 ) );
		$sale     = $free ? null : $this->price_string( $meta( '_sale_price', null ) );
		$from     = $this->date_from_timestamp( $meta( '_sale_price_dates_from' ) );
		$to       = $this->date_from_timestamp( $meta( '_sale_price_dates_to' ) );
		$decimals = (int) ohmylms_get_price_decimals();
		$thumb_id = (int) $meta( '_thumbnail_id', 0 );
		$thumb    = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : false;
		$duration = $meta( '_duration', array() );
		$hours    = is_array( $duration ) ? (int) ( $duration['hour'] ?? 0 ) : 0;
		$minutes  = is_array( $duration ) ? (int) ( $duration['min'] ?? 0 ) : 0;
		$access   = 'password_protected' === $meta( '_access_type', 'public' ) ? 'password_protected' : 'public';
		$capacity = (bool) $meta( '_has_capacity', false );
		$level    = (string) $meta( '_level', 'beginner' );
		$can_edit = $this->can( 'edit', $id );
		$status   = in_array( $post->post_status, array_merge( self::LIST_STATUSES, array( 'trash' ) ), true ) ? $post->post_status : 'draft';

		$data = array(
			'id'                => $id,
			'title'             => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'slug'              => (string) $post->post_name,
			'status'            => $status,
			'permalink'         => (string) get_permalink( $post ),
			'author_id'         => (int) $post->post_author,
			'author_name'       => $this->can( 'list_others' ) ? (string) get_the_author_meta( 'display_name', $post->post_author ) : '',
			'description'       => (string) $post->post_content,
			'excerpt'           => (string) $post->post_excerpt,
			'level'             => in_array( $level, self::LEVELS, true ) ? $level : 'beginner',
			'price_type'        => $free ? 'free' : 'paid',
			'regular_price'     => $regular,
			'sale_price'        => $sale,
			'sale_start_date'   => $sale && $from ? $from : null,
			'sale_end_date'     => $sale && $to ? $to : null,
			'price'             => $this->current_price( $free, (string) $regular, $sale, $from ? $from : null, $to ? $to : null, $decimals ),
			'currency'          => (string) get_ohmylms_currency(),
			'access_type'       => $access,
			'has_password'      => '' !== (string) $post->post_password,
			'available_date'    => $meta( '_available_date', null ) ? (string) $meta( '_available_date' ) : null,
			'duration'          => ( $hours || $minutes ) ? array(
				'hours'   => $hours,
				'minutes' => $minutes,
			) : null,
			'has_capacity'      => $capacity,
			'capacity'          => $capacity ? (int) $meta( '_capacity', 0 ) : null,
			'enable_reviews'    => (bool) $meta( '_enable_reviews', false ),
			'learning_outcomes' => (string) $meta( '_benefit_description' ),
			'target_audience'   => (string) $meta( '_benefiter_description' ),
			'requirements'      => (string) $meta( '_requirement' ),
			'thumbnail_id'      => $thumb_id ? $thumb_id : null,
			'thumbnail_url'     => $thumb ? $thumb : null,
			'enrolled_count'    => isset( $this->enrolled_counts[ $id ] ) ? $this->enrolled_counts[ $id ] : ( $this->count_enrolled( array( $id ) )[ $id ] ?? 0 ),
			'scheduled_at'      => 'future' === $post->post_status ? str_replace( ' ', 'T', $post->post_date ) : null,
			'created_at'        => mysql_to_rfc3339( $post->post_date ),
			'updated_at'        => mysql_to_rfc3339( $post->post_modified ),
		);
		// The password is shown only to users who may edit this course, and never in a list.
		if ( $can_edit && 'edit' === $request['context'] ) {
			$data['password'] = (string) $post->post_password;
		}

		/**
		 * Filters the course data before the context is applied (extensions add fields here).
		 *
		 * @param array       $data   Response data.
		 * @param Course|null $course Course object.
		 */
		$data = apply_filters( 'ohmylms_rest_get_course_data', $data, ohmylms_get_course( $post ) );

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links(
			array(
				'self'       => array( 'href' => rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $id ) ) ),
				'collection' => array( 'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ) ),
			)
		);

		/**
		 * Filters the response for the course in the REST API.
		 *
		 * @param WP_REST_Response $response The response.
		 * @param WP_Post          $post     The course post.
		 * @param WP_REST_Request  $request  The request.
		 */
		return apply_filters( 'ohmylms_rest_prepare_course', $response, $post, $request );
	}

	/**
	 * Get the course schema (JSON Schema draft 4).
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$both  = array( 'view', 'edit' );
		$edit  = array( 'edit' );
		$text  = static function ( $description, $context, $extra = array() ) {
			return array_merge(
				array(
					'description' => $description,
					'type'        => 'string',
					'context'     => $context,
				),
				$extra
			);
		};
		$price = static function ( $description, $nullable ) use ( $both ) {
			return array(
				'description' => $description,
				'type'        => $nullable ? array( 'string', 'null' ) : 'string',
				'pattern'     => '^\d{1,8}(\.\d+)?$',
				'context'     => $both,
			);
		};
		$date  = static function ( $description ) use ( $both ) {
			return array(
				'description' => $description,
				'type'        => array( 'string', 'null' ),
				'pattern'     => '^\d{4}-\d{2}-\d{2}$',
				'context'     => $both,
			);
		};

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'ohmylms_course',
			'type'       => 'object',
			'properties' => array(
				'id'                => array(
					'description' => __( 'Unique identifier for the course.', 'ohmylms' ),
					'type'        => 'integer',
					'context'     => $both,
					'readonly'    => true,
				),
				'title'             => $text(
					__( 'Course title.', 'ohmylms' ),
					$both,
					array(
						'minLength' => 1,
						'maxLength' => self::MAX_TITLE,
						'required'  => true,
					)
				),
				'slug'              => $text( __( 'Course address. Made unique among courses.', 'ohmylms' ), $both, array( 'maxLength' => 200 ) ),
				'status'            => $text(
					__( 'Publication status.', 'ohmylms' ),
					$both,
					array( 'enum' => self::WRITE_STATUSES )
				),
				'scheduled_at'      => array(
					'description' => __( 'Site-local publish time (YYYY-MM-DDTHH:MM) of a scheduled course.', 'ohmylms' ),
					'type'        => array( 'string', 'null' ),
					'pattern'     => '^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$',
					'context'     => $both,
				),
				'permalink'         => $text( __( 'Public address of the course.', 'ohmylms' ), $both, array( 'readonly' => true ) ),
				'author_id'         => array(
					'description' => __( 'Author user ID.', 'ohmylms' ),
					'type'        => 'integer',
					'context'     => $both,
					'readonly'    => true,
				),
				'author_name'       => $text( __( 'Author display name (only for users who can edit others\' courses).', 'ohmylms' ), $both, array( 'readonly' => true ) ),
				'description'       => $text( __( 'Course description (HTML limited to post content).', 'ohmylms' ), $edit ),
				'excerpt'           => $text( __( 'Short description.', 'ohmylms' ), $edit, array( 'maxLength' => self::MAX_TEXT ) ),
				'level'             => $text( __( 'Course level.', 'ohmylms' ), $both, array( 'enum' => self::LEVELS ) ),
				'price_type'        => $text( __( 'Free or paid.', 'ohmylms' ), $both, array( 'enum' => array( 'free', 'paid' ) ) ),
				'regular_price'     => $price( __( 'Regular price as a fixed-decimal string.', 'ohmylms' ), false ),
				'sale_price'        => $price( __( 'Sale price, or null for no sale.', 'ohmylms' ), true ),
				'sale_start_date'   => $date( __( 'First day of the sale (site timezone).', 'ohmylms' ) ),
				'sale_end_date'     => $date( __( 'Last day of the sale (site timezone).', 'ohmylms' ) ),
				'price'             => array_merge( $price( __( 'Price that applies today.', 'ohmylms' ), false ), array( 'readonly' => true ) ),
				'currency'          => $text( __( 'Site currency code.', 'ohmylms' ), $both, array( 'readonly' => true ) ),
				'access_type'       => $text( __( 'Who can open the course.', 'ohmylms' ), $both, array( 'enum' => array( 'public', 'password_protected' ) ) ),
				'password'          => $text(
					__( 'Course password. Write-only except in the edit context for users who can edit the course.', 'ohmylms' ),
					$edit,
					array( 'maxLength' => self::MAX_PASSWORD )
				),
				'has_password'      => array(
					'description' => __( 'Whether a password is set.', 'ohmylms' ),
					'type'        => 'boolean',
					'context'     => $both,
					'readonly'    => true,
				),
				'available_date'    => $date( __( 'Date the course opens, or null.', 'ohmylms' ) ),
				'duration'          => array(
					'description'          => __( 'Estimated duration, or null.', 'ohmylms' ),
					'type'                 => array( 'object', 'null' ),
					'context'              => $both,
					'additionalProperties' => false,
					'properties'           => array(
						'hours'   => array(
							'type'    => 'integer',
							'minimum' => 0,
							'maximum' => 9999,
						),
						'minutes' => array(
							'type'    => 'integer',
							'minimum' => 0,
							'maximum' => 59,
						),
					),
				),
				'has_capacity'      => array(
					'description' => __( 'Whether enrolment is limited.', 'ohmylms' ),
					'type'        => 'boolean',
					'context'     => $both,
				),
				'capacity'          => array(
					'description' => __( 'Seats when enrolment is limited.', 'ohmylms' ),
					'type'        => array( 'integer', 'null' ),
					'minimum'     => 1,
					'maximum'     => self::MAX_CAPACITY,
					'context'     => $both,
				),
				'enable_reviews'    => array(
					'description' => __( 'Whether students can review the course.', 'ohmylms' ),
					'type'        => 'boolean',
					'context'     => $both,
				),
				'learning_outcomes' => $text( __( 'What students will learn.', 'ohmylms' ), $edit, array( 'maxLength' => self::MAX_TEXT ) ),
				'target_audience'   => $text( __( 'Who the course is for.', 'ohmylms' ), $edit, array( 'maxLength' => self::MAX_TEXT ) ),
				'requirements'      => $text( __( 'What students need beforehand.', 'ohmylms' ), $edit, array( 'maxLength' => self::MAX_TEXT ) ),
				'thumbnail_id'      => array(
					'description' => __( 'Thumbnail attachment ID, or null.', 'ohmylms' ),
					'type'        => array( 'integer', 'null' ),
					'minimum'     => 0,
					'context'     => $both,
				),
				'thumbnail_url'     => array(
					'description' => __( 'Medium thumbnail address.', 'ohmylms' ),
					'type'        => array( 'string', 'null' ),
					'context'     => $both,
					'readonly'    => true,
				),
				'enrolled_count'    => array(
					'description' => __( 'Enrolled students.', 'ohmylms' ),
					'type'        => 'integer',
					'context'     => $both,
					'readonly'    => true,
				),
				'created_at'        => $text( __( 'Creation time (site-local, RFC 3339).', 'ohmylms' ), $both, array( 'readonly' => true ) ),
				'updated_at'        => $text( __( 'Last change (site-local, RFC 3339).', 'ohmylms' ), $both, array( 'readonly' => true ) ),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Arguments for the update route: every writable field, optional, without defaults.
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
	 * Get collection parameters.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params = array_intersect_key( parent::get_collection_params(), array_flip( array( 'context', 'page', 'per_page', 'search' ) ) );

		$params['context']['default']    = 'view';
		$params['context']['enum']       = array( 'view' );
		$params['per_page']['default']   = 20;
		$params['per_page']['maximum']   = 100;
		$params['search']['description'] = __( 'Limit results to courses whose title, description or excerpt matches the string.', 'ohmylms' );

		$params['status']         = array(
			'description' => __( 'Limit result set to a status (`all` excludes the trash).', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'all',
			'enum'        => array_merge( array( 'all', 'trash' ), self::LIST_STATUSES ),
		);
		$params['price_type']     = array(
			'description' => __( 'Limit result set to free or paid courses.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'all',
			'enum'        => array( 'all', 'free', 'paid' ),
		);
		$params['level']          = array(
			'description' => __( 'Limit result set to a level.', 'ohmylms' ),
			'type'        => 'string',
			'enum'        => self::LEVELS,
		);
		$params['author']         = array(
			'description' => __( 'Limit result set to one author (only for users who can edit others\' courses).', 'ohmylms' ),
			'type'        => 'integer',
			'minimum'     => 1,
		);
		$params['created_after']  = array(
			'description' => __( 'Courses created on or after this date (YYYY-MM-DD).', 'ohmylms' ),
			'type'        => 'string',
			'pattern'     => '^\d{4}-\d{2}-\d{2}$',
		);
		$params['created_before'] = array(
			'description' => __( 'Courses created on or before this date (YYYY-MM-DD).', 'ohmylms' ),
			'type'        => 'string',
			'pattern'     => '^\d{4}-\d{2}-\d{2}$',
		);
		$params['include']        = array(
			'description' => __( 'Limit result set to specific course IDs.', 'ohmylms' ),
			'type'        => 'array',
			'items'       => array( 'type' => 'integer' ),
			'maxItems'    => 100,
			'default'     => array(),
		);
		$params['orderby']        = array(
			'description' => __( 'Sort collection by attribute.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'created_at',
			'enum'        => array( 'id', 'title', 'status', 'created_at', 'updated_at', 'price' ),
		);
		$params['order']          = array(
			'description' => __( 'Order sort attribute ascending or descending.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'desc',
			'enum'        => array( 'asc', 'desc' ),
		);

		return $params;
	}

	/**
	 * Permission check for the auxiliary routes (enrolment, import, export, builder data). Routes with a
	 * course ID need the edit capability on that course; enrolment also needs user rights, and
	 * import/export are administrator-only.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function check_legacy_permission( $request ) {
		if ( ! $this->can( 'list' ) ) {
			return $this->denied( 'ohmylms_course_cannot_edit', __( 'Sorry, you are not allowed to manage courses.', 'ohmylms' ) );
		}
		$route = $request->get_route();
		if ( preg_match( '#/(import|import-scorm|export|export-scorm)$#', $route ) && ! current_user_can( 'manage_options' ) ) {
			return $this->denied( 'ohmylms_course_cannot_exchange', __( 'Only administrators can import or export courses.', 'ohmylms' ) );
		}
		if ( $request['id'] ) {
			$post = $this->get_course_post( $request['id'] );
			if ( is_wp_error( $post ) ) {
				return $post;
			}
			if ( ! $this->can( 'edit', $post->ID ) ) {
				return $this->denied( 'ohmylms_course_cannot_edit', __( 'Sorry, you are not allowed to edit this course.', 'ohmylms' ) );
			}
		}
		if ( preg_match( '#/enroll-by-email$#', $route ) && ! current_user_can( 'create_users' ) ) {
			return $this->denied( 'ohmylms_course_cannot_create_users', __( 'Sorry, you are not allowed to create users.', 'ohmylms' ) );
		}
		if ( preg_match( '#/enroll(/\d+)?$#', $route ) && ! current_user_can( 'list_users' ) ) {
			return $this->denied( 'ohmylms_course_cannot_enroll', __( 'Sorry, you are not allowed to enrol students.', 'ohmylms' ) );
		}

		return true;
	}

	/**
	 * Register the auxiliary routes. They are outside the CRUD contract and keep their old shapes.
	 *
	 * @return void
	 */
	private function register_auxiliary_routes() {
		$cb  = array( $this, 'check_legacy_permission' );
		$any = static function ( $methods, $callback, $extra = array() ) use ( $cb ) {
			return array_merge(
				array(
					'methods'             => $methods,
					'callback'            => $callback,
					'permission_callback' => $cb,
				),
				$extra
			);
		};
		$b   = '/' . $this->base;

		register_rest_route( $this->namespace, $b . '/export', $any( WP_REST_Server::READABLE, array( $this, 'export' ) ) );
		register_rest_route(
			$this->namespace,
			$b . '/export-scorm',
			$any(
				WP_REST_Server::READABLE,
				array( $this, 'export_scorm' ),
				array(
					'args' => array(
						'course_ids'    => array(
							'required'          => true,
							'type'              => 'array',
							'description'       => __( 'Array of course IDs to export', 'ohmylms' ),
							'validate_callback' => static function ( $param ) {
								return is_array( $param ) && ! empty( $param );
							},
						),
						'scorm_version' => array(
							'required'    => false,
							'type'        => 'string',
							'default'     => '1.2',
							'description' => __( 'SCORM version (1.2 or 2004)', 'ohmylms' ),
							'enum'        => array( '1.2', '2004' ),
						),
						'_wpnonce'      => array(
							'required'    => false,
							'type'        => 'string',
							'description' => __( 'WordPress nonce for authentication', 'ohmylms' ),
						),
					),
				)
			)
		);
		register_rest_route( $this->namespace, $b . '/import', $any( WP_REST_Server::EDITABLE, array( $this, 'import' ) ) );
		register_rest_route( $this->namespace, $b . '/import-scorm', $any( WP_REST_Server::EDITABLE, array( $this, 'import_scorm' ) ) );
		register_rest_route( $this->namespace, $b . '/(?P<id>\d+)/enroll', $any( WP_REST_Server::EDITABLE, array( $this, 'enroll_manually' ) ) );
		register_rest_route( $this->namespace, $b . '/(?P<id>\d+)/enroll/(?P<student_id>\d+)', $any( WP_REST_Server::DELETABLE, array( $this, 'delete_enroll_manually' ) ) );
		register_rest_route(
			$this->namespace,
			$b . '/(?P<id>\d+)/enroll-by-email',
			$any(
				WP_REST_Server::CREATABLE,
				array( $this, 'enroll_by_email' ),
				array(
					'args' => array(
						'email'      => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_email',
						),
						'first_name' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'last_name'  => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				)
			)
		);
		register_rest_route(
			$this->namespace,
			$b . '/(?P<id>[\d]+)/chapters',
			array(
				$any( WP_REST_Server::READABLE, array( $this, 'get_chapters' ) ),
				$any( WP_REST_Server::EDITABLE, array( $this, 'update_chapters' ) ),
			)
		);
		register_rest_route( $this->namespace, $b . '/update-map/(?P<id>\d+)', $any( WP_REST_Server::EDITABLE, array( $this, 'update_course_map' ) ) );
		register_rest_route(
			$this->namespace,
			$b . '/settings/(?P<id>\d+)',
			array(
				$any( WP_REST_Server::EDITABLE, array( $this, 'save_course_settings' ) ),
				$any( WP_REST_Server::READABLE, array( $this, 'get_course_settings' ) ),
			)
		);
		register_rest_route( $this->namespace, $b . '/get-map/(?P<id>\d+)', $any( WP_REST_Server::READABLE, array( $this, 'get_course_map' ) ) );
		register_rest_route( $this->namespace, $b . '/get-map/default', $any( WP_REST_Server::READABLE, array( $this, 'get_course_map_default' ) ) );
		register_rest_route( $this->namespace, $b . '/filtered-courses', $any( WP_REST_Server::READABLE, array( $this, 'get_filtered_courses' ) ) );
		register_rest_route(
			$this->namespace,
			$b . '/(?P<id>\d+)/terms',
			$any(
				WP_REST_Server::EDITABLE,
				array( $this, 'assign_terms_to_course' ),
				array(
					'args' => array(
						'taxonomy' => array(
							'description' => __( 'The taxonomy to assign.', 'ohmylms' ),
							'type'        => 'string',
							'required'    => true,
						),
						'terms'    => array(
							'description' => __( 'The terms to assign.', 'ohmylms' ),
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'required'    => true,
						),
					),
				)
			)
		);
	}

	/**
	 * Import a course from a JSON file.
	 *
	 * @param WP_REST_Request $request The REST API request object.
	 * @return WP_REST_Response|WP_Error The result of the import operation or an error.
	 */
	public function import( WP_REST_Request $request ) {
		if ( ! ohmylms_is_pro() ) {
			return new \WP_Error( 'pro_feature', __( 'This is pro feature.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$file = $request->get_file_params();
		if ( ! isset( $file['file'] ) || empty( $file['file']['tmp_name'] ) ) {
			return new \WP_Error( 'file_missing', __( 'No file uploaded.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		// Instantiate the CourseImporter class.
		$importer = new \OhMyLMS\Importers\CourseImporter( $file['file'] );
		$result   = $importer->import_course_from_json();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Return success response with the imported course ID.
		return new \WP_REST_Response(
			array(
				'success'   => true,
				'course_id' => $result,
				'message'   => __( 'Courses imported successfully.', 'ohmylms' ),
			),
			200
		);
	}

	/**
	 * Import a course from a SCORM package.
	 *
	 * @param WP_REST_Request $request The REST API request object.
	 * @return WP_REST_Response|WP_Error The result of the import operation or an error.
	 */
	public function import_scorm( WP_REST_Request $request ) {
		$file = $request->get_file_params();
		if ( ! isset( $file['file'] ) || empty( $file['file']['tmp_name'] ) ) {
			return new \WP_Error( 'file_missing', __( 'No file uploaded.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		try {
			// Instantiate the ScormImporter class.
			$importer = new \OhMyLMS\Importers\ScormImporter( $file['file'] );
			$result   = $importer->import_scorm_package();

			// Return success response with detailed report.
			return new \WP_REST_Response(
				array(
					'success' => $result['success'],
					'data'    => array(
						'course_id'           => isset( $result['course_id'] ) ? $result['course_id'] : null,
						'chapters_created'    => isset( $result['chapters_created'] ) ? $result['chapters_created'] : 0,
						'lessons_created'     => isset( $result['lessons_created'] ) ? $result['lessons_created'] : 0,
						'quizzes_created'     => isset( $result['quizzes_created'] ) ? $result['quizzes_created'] : 0,
						'assignments_created' => isset( $result['assignments_created'] ) ? $result['assignments_created'] : 0,
						'media_imported'      => isset( $result['media_imported'] ) ? $result['media_imported'] : 0,
					),
					'report'  => array(
						'skipped_items' => $result['skipped_items'],
						'warnings'      => $result['warnings'],
						'errors'        => $result['errors'],
					),
					'message' => __( 'SCORM package imported successfully.', 'ohmylms' ),
				),
				200
			);
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'import_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Export a course by ID.
	 *
	 * @param WP_REST_Request $request The REST API request object.
	 * @return WP_REST_Response|WP_Error The exported course data or an error.
	 */
	public function export( WP_REST_Request $request ) {
		$course_ids = $request->get_param( 'course_ids' );
		if ( ! ohmylms_is_pro() ) {
			return new \WP_Error( 'pro_feature', __( 'This is pro feature.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		if ( empty( $course_ids ) ) {
			return new \WP_Error( 'invalid_id', __( 'Invalid course ID.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		try {
			$exporter = new \OhMyLMS\Exporters\CourseExporter( $course_ids );
			$exporter->export_courses_as_json();
		} catch ( \Exception $e ) {
			return new \WP_Error( 'export_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Export courses as SCORM package.
	 *
	 * @param WP_REST_Request $request The REST API request object.
	 * @return WP_REST_Response|WP_Error The SCORM package or an error.
	 */
	public function export_scorm( WP_REST_Request $request ) {
		$course_ids    = $request->get_param( 'course_ids' );
		$scorm_version = $request->get_param( 'scorm_version' );

		if ( ! ohmylms_is_pro() ) {
			return new \WP_Error( 'pro_feature', __( 'SCORM export is a pro feature.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		if ( empty( $course_ids ) ) {
			return new \WP_Error( 'invalid_id', __( 'Invalid course ID. Please select at least one course.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		// Validate course IDs.
		foreach ( $course_ids as $course_id ) {
			$course = get_post( $course_id );
			if ( ! $course || OHMYLMS_COURSE_CPT !== $course->post_type ) {
				return new \WP_Error(
					'invalid_course',
					/* translators: %d: course ID. */
					sprintf( __( 'Invalid course ID: %d', 'ohmylms' ), $course_id ),
					array( 'status' => 400 )
				);
			}
		}

		// Default to SCORM 1.2 if not specified.
		if ( empty( $scorm_version ) || ! in_array( $scorm_version, array( '1.2', '2004' ), true ) ) {
			$scorm_version = '1.2';
		}

		try {
			// Increase memory limit for large courses.
			if ( function_exists( 'ini_set' ) ) {
				wp_raise_memory_limit( 'admin' );
			}

			$exporter = new \OhMyLMS\Exporters\ScormExporter( $course_ids, $scorm_version );
			$exporter->export_courses_as_scorm();

			// The export method handles the download and exits, so this won't be reached
			// But we keep it for consistency.
			exit;

		} catch ( \Exception $e ) {
			return new \WP_Error(
				'scorm_export_error',
				/* translators: %s: error message. */
				sprintf( __( 'SCORM export failed: %s', 'ohmylms' ), $e->getMessage() ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Enroll student manually to a course.
	 *
	 * @param WP_REST_Request $request The REST API request object.
	 * @return WP_REST_Response|WP_Error The exported course data or an error.
	 */
	public function enroll_manually( WP_REST_Request $request ) {
		$course_id  = isset( $request['id'] ) ? (int) $request['id'] : '';
		$student_id = isset( $request['student_id'] ) ? (int) $request['student_id'] : '';

		if ( empty( $course_id ) ) {
			return new \WP_Error( 'invalid_id', __( 'Invalid course ID.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		if ( empty( $student_id ) ) {
			return new \WP_Error( 'invalid_student_id', __( 'Invalid student ID.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		try {
			global $wpdb;
			$enrollment_table = $wpdb->prefix . 'ohmylms_user_enrollment';

			$enrollment_data = array(
				'course_id'  => $course_id,
				'user_id'    => $student_id,
				'status'     => 'enrolled',
				'progress'   => 'running',
				'start_date' => current_time( 'mysql' ),
			);
			$student         = new \OhMyLMS\Data\Student( $student_id );
			// update user meta.
			update_user_meta( $student_id, '_is_ohmylms_student', 'yes' );
			update_user_meta( $student_id, '_is_ohmylms_user', 'yes' );
			if ( $student && ! $student->maybe_enrolled( $course_id ) ) {
				// Check if the record exists.
				$existing_record = $wpdb->get_var(
					$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d AND course_id = %d', $enrollment_table, $student_id, $course_id )
				);

				if ( $existing_record ) {
					$response = array(
						'success' => false,
						'message' => __( 'Already enrolled.', 'ohmylms' ),
					);
				} else {
					$wpdb->insert(
						$enrollment_table,
						$enrollment_data
					);

					// Trigger action hook for manual enrollment to enable community access and other integrations.
					do_action( 'ohmylms_manual_student_enrollment', $student_id, $course_id );

					$response = array(
						'success' => true,
						'message' => __( 'Enrolled successfully', 'ohmylms' ),
					);
				}
				return new \WP_REST_Response( $response, 200 );
			} else {
				$response = array(
					'success' => true,
					'message' => __( 'Already enrolled.', 'ohmylms' ),
				);
				return new \WP_REST_Response( $response, 200 );
			}
		} catch ( \Exception $e ) {
			return new \WP_Error( 'enroll_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Delete a student's enrollment from a course.
	 *
	 * @param WP_REST_Request $request The REST API request object.
	 * @return WP_REST_Response|WP_Error Success message or an error.
	 */
	public function delete_enroll_manually( WP_REST_Request $request ) {
		$course_id  = isset( $request['id'] ) ? (int) $request['id'] : '';
		$student_id = isset( $request['student_id'] ) ? (int) $request['student_id'] : '';

		if ( empty( $course_id ) ) {
			return new \WP_Error( 'invalid_id', __( 'Invalid course ID.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		if ( empty( $student_id ) ) {
			return new \WP_Error( 'invalid_student_id', __( 'Invalid student ID.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		try {
			global $wpdb;
			$enrollment_table = $wpdb->prefix . 'ohmylms_user_enrollment';

			// Check if the enrollment exists.
			$existing_record = $wpdb->get_var(
				$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d AND course_id = %d', $enrollment_table, $student_id, $course_id )
			);

			if ( ! $existing_record ) {
				return new \WP_Error(
					'enrollment_not_found',
					__( 'The enrollment record does not exist.', 'ohmylms' ),
					array( 'status' => 404 )
				);
			}

			// Delete the enrollment.
			$deleted = $wpdb->delete(
				$enrollment_table,
				array(
					'user_id'   => $student_id,
					'course_id' => $course_id,
				),
				array(
					'%d',
					'%d',
				)
			);

			if ( $deleted ) {
				$response = array(
					'success' => true,
					'message' => __( 'Student enrollment deleted successfully.', 'ohmylms' ),
				);
			} else {
				$response = array(
					'success' => false,
					'message' => __( 'Failed to delete student enrollment.', 'ohmylms' ),
				);
			}

			return new \WP_REST_Response( $response, 200 );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'delete_enroll_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Generate a unique alphanumeric-only username from a name or email fallback.
	 *
	 * @param string $first_name First name.
	 * @param string $last_name Last name.
	 * @param string $email Email.
	 * @return string
	 */
	private function generate_username( $first_name, $last_name, $email ) {
		$first_name = trim( $first_name );
		$last_name  = trim( $last_name );

		if ( $first_name || $last_name ) {
			$base = strtolower( $first_name . $last_name );
		} else {
			$base = strtolower( explode( '@', $email )[0] );
		}

		$base = preg_replace( '/[^a-z0-9]/', '', $base );

		if ( empty( $base ) ) {
			$base = 'student';
		}

		$username = $base;
		$suffix   = 1;
		while ( username_exists( $username ) ) {
			$username = $base . $suffix;
			++$suffix;
		}

		return $username;
	}

	/**
	 * Enroll a student by email. Creates a new account if no user with that email exists.
	 * Fires ohmylms_manual_enrollment_by_email after enrollment so emails can be sent.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function enroll_by_email( WP_REST_Request $request ) {
		$course_id  = isset( $request['id'] ) ? (int) $request['id'] : 0;
		$email      = isset( $request['email'] ) ? sanitize_email( $request['email'] ) : '';
		$first_name = isset( $request['first_name'] ) ? sanitize_text_field( $request['first_name'] ) : '';
		$last_name  = isset( $request['last_name'] ) ? sanitize_text_field( $request['last_name'] ) : '';

		if ( empty( $course_id ) ) {
			return new \WP_Error( 'invalid_id', __( 'Invalid course ID.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		if ( empty( $email ) || ! is_email( $email ) ) {
			return new \WP_Error( 'invalid_email', __( 'Invalid email address.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$is_new_user    = false;
		$plain_password = '';
		$username       = '';

		$existing_user = get_user_by( 'email', $email );

		if ( $existing_user ) {
			$student_id = $existing_user->ID;
			$username   = $existing_user->user_login;
		} else {
			$username       = $this->generate_username( $first_name, $last_name, $email );
			$plain_password = wp_generate_password( 12, false );

			$user_args = array();
			if ( $first_name ) {
				$user_args['first_name'] = $first_name;
			}
			if ( $last_name ) {
				$user_args['last_name']    = $last_name;
				$user_args['display_name'] = trim( $first_name . ' ' . $last_name );
			}

			$student_id = ohmylms_create_new_student( $email, $username, $plain_password, $user_args );

			if ( is_wp_error( $student_id ) ) {
				return new \WP_Error( 'user_creation_failed', $student_id->get_error_message(), array( 'status' => 400 ) );
			}

			$is_new_user = true;
		}

		try {
			global $wpdb;
			$enrollment_table = $wpdb->prefix . 'ohmylms_user_enrollment';

			update_user_meta( $student_id, '_is_ohmylms_student', 'yes' );
			update_user_meta( $student_id, '_is_ohmylms_user', 'yes' );

			$existing_status = $wpdb->get_var(
				$wpdb->prepare( 'SELECT status FROM %i WHERE user_id = %d AND course_id = %d', $enrollment_table, $student_id, $course_id )
			);

			if ( 'enrolled' === $existing_status ) {
				return new \WP_REST_Response(
					array(
						'success'     => false,
						'message'     => __( 'Student is already enrolled in this course.', 'ohmylms' ),
						'is_new_user' => $is_new_user,
					),
					200
				);
			}

			if ( $existing_status ) {
				$wpdb->update(
					$enrollment_table,
					array(
						'status'     => 'enrolled',
						'start_date' => current_time( 'mysql' ),
					),
					array(
						'user_id'   => $student_id,
						'course_id' => $course_id,
					),
					array( '%s', '%s' ),
					array( '%d', '%d' )
				);
			} else {
				$wpdb->insert(
					$enrollment_table,
					array(
						'course_id'  => $course_id,
						'user_id'    => $student_id,
						'status'     => 'enrolled',
						'progress'   => 'running',
						'start_date' => current_time( 'mysql' ),
					)
				);
			}

			do_action( 'ohmylms_manual_student_enrollment', $student_id, $course_id );
			do_action( 'ohmylms_manual_enrollment_by_email', $student_id, $course_id, $username, $plain_password, $is_new_user );

			return new \WP_REST_Response(
				array(
					'success'     => true,
					'message'     => $is_new_user
						? __( 'New student account created and enrolled successfully.', 'ohmylms' )
						: __( 'Student enrolled successfully.', 'ohmylms' ),
					'is_new_user' => $is_new_user,
					'student_id'  => $student_id,
					'username'    => $username,
				),
				200
			);
		} catch ( \Exception $e ) {
			return new \WP_Error( 'enroll_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Retrieves the chapters of a course.
	 *
	 * This method fetches the chapters associated with a specific course ID.
	 *
	 * @param \WP_REST_Request $request The REST request object containing the course ID.
	 * @return WP_Error|\WP_HTTP_Response|\WP_REST_Response The response object containing the chapters data or an error.
	 *
	 * @since 1.0.0
	 */
	public function get_chapters( $request ) {

		$course_id = isset( $request['id'] ) ? (int) $request['id'] : 0;

		// Check the course id exist or not.
		if ( ! $course_id ) {
			return new WP_Error( 'ohmylms_rest_course_empty_id', __( 'ID is required.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		// Get existing course by course id.
		$course = ohmylms_get_course( $course_id );

		// Check the course exist or not.
		if ( ! ( $course instanceof Course ) ) {
			return new WP_Error( 'ohmylms_rest_course_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$chapters = $course->get_chapters();

		$response = array(
			'status'  => 'success',
			'message' => __( 'Chapters fetched successfully', 'ohmylms' ),
			'data'    => $chapters,
		);

		return rest_ensure_response( $response );
	}

	/**
	 * Updates the chapters of a course.
	 *
	 * This method handles the updating of chapters for a specific course based on the provided request data.
	 *
	 * @param WP_REST_Request $request The REST request object containing the course ID and chapter data.
	 * @return WP_REST_Response The response object containing the result of the update operation.
	 *
	 * @since 1.0.0
	 */
	public function update_chapters( $request ) {
		$course_id = (int) $request['id'];

		// Check the course id exist or not.
		if ( ! $course_id ) {
			return new WP_Error( 'ohmylms_rest_course_empty_id', __( 'ID is required.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		// Get existing course by course id.
		$course = ohmylms_get_course( $course_id );

		// Check the course exist or not.
		if ( ! ( $course instanceof Course ) ) {
			return new WP_Error( 'ohmylms_rest_course_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$chapters    = array();
		$chapter_ids = array();
		foreach ( $request->get_json_params() as $chapter ) {

			$chapter_id = isset( $chapter['id'] ) ? (int) $chapter['id'] : 0;
			if ( ! $chapter_id ) {
				$chapter_obj = new Chapter();
			} else {
				$chapter_obj = ohmylms_get_chapter( $chapter_id );
			}
			$chapter_obj->set_name( $chapter['name'] );
			$chapter_obj->set_description( $chapter['description'] );
			$chapter_obj->save();
			$chapter_obj->set_parent_id( $course_id );
			$chapters[]    = $chapter;
			$chapter_ids[] = $chapter_obj->get_id();
		}

		$course->set_chapters( $chapters );
		$single_chapter = count( $chapters ) === 1 ? 'chapter' : 'chapters';
		$response       = array(
			'status'  => 'success',
			'message' => __( 'Chapters updated successfully', 'ohmylms' ),
		);
		if ( 'chapter' === $single_chapter ) {
			$response['chapter'] = $chapters[0];
		}
		return rest_ensure_response( $response );
	}

	/**
	 * Update course map
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function update_course_map( $request ) {
		$course_id = (int) $request['id'];
		$map       = $request->get_json_params();

		$response = CourseHelper::update_course_map( $course_id, $map );
		return rest_ensure_response( $response );
	}

	/**
	 * Get course map
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_course_map( $request ) {
		$course_id = (int) $request['id'];
		$response  = CourseHelper::get_course_map( $course_id );
		return rest_ensure_response( $response );
	}

	/**
	 * Get default course map
	 *
	 * @param mixed $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_course_map_default( $request ) {
		$course_id = (int) $request['id'];
		$response  = CourseHelper::get_course_map( $course_id );
		return rest_ensure_response( $response );
	}

	/**
	 * Fetch course settings
	 *
	 * @param mixed $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_course_settings( $request ) {
		$course_id = $request['id'];
		$course    = get_post( $course_id );

		if ( ! $course || 'ohmylms-course' !== $course->post_type ) {
			$response = array(
				'status'  => 'error',
				'message' => __( 'Course not found.', 'ohmylms' ),
			);

			return rest_ensure_response( $response );
		}

		$settings_data = get_post_meta( $course_id, 'ohmylms_course_settings', true );

		$response = array(
			'status'  => 'success',
			'message' => __( 'Course settings fetched', 'ohmylms' ),
			'data'    => $settings_data,
		);

		return rest_ensure_response( $response );
	}

	/**
	 * Save course settings
	 *
	 * @param mixed $request REST request.
	 * @return WP_Error|WP_REST_Response|\WP_HTTP_Response
	 * @since 1.0.0
	 */
	public function save_course_settings( $request ) {
		$course_id     = (int) $request['id'];
		$settings_data = $request->get_json_params();

		$response = CourseHelper::save_course_settings( $course_id, $settings_data );
		return rest_ensure_response( $response );
	}

	/**
	 * Get filtered courses for Course controller.
	 *
	 * @param mixed $request REST request.
	 */
	public function get_filtered_courses( $request ) {
		$args = array(
			'post_type'      => OHMYLMS_COURSE_CPT,
			's'              => isset( $request['search'] ) ? sanitize_text_field( (string) $request['search'] ) : '',
			'post__not_in'   => isset( $request['exclude'] ) ? array_map( 'absint', explode( ',', (string) $request['exclude'] ) ) : array(),
			'posts_per_page' => 50,
		);
		if ( ! $this->can( 'list_others' ) ) {
			$args['author'] = get_current_user_id();
		}

		$query = new WP_Query( $args );

		$courses = array();

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$courses[] = array(
					'id'        => get_the_ID(),
					'title'     => get_the_title(),
					'thumbnail' => get_the_post_thumbnail_url( get_the_ID(), 'thumbnail' ),
				);
			}
		}

		wp_reset_postdata();

		return rest_ensure_response( $courses );
	}

	/**
	 * Assign terms to a course.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_Error|\WP_REST_Response The response object containing the result of the term assignment or an error.
	 *
	 * @since 1.0.0
	 */
	public function assign_terms_to_course( $request ) {
		$course_id = (int) $request['id'];
		$taxonomy  = $request['taxonomy'];
		$terms     = $request['terms'];

		if ( empty( $course_id ) || empty( $taxonomy ) || empty( $terms ) ) {
			return new WP_Error( 'missing_params', 'Missing parameters', array( 'status' => 400 ) );
		}
		if ( in_array( $taxonomy, array( 'course_category', 'course_tag' ), true ) ) {
			return new WP_Error( 'ohmylms_taxonomy_replaced', __( 'Course categories and tags were replaced by curriculum items and course bundles. Use /courses/{id}/organization.', 'ohmylms' ), array( 'status' => 410 ) );
		}
		if ( ! is_object_in_taxonomy( OHMYLMS_COURSE_CPT, (string) $taxonomy ) ) {
			return new WP_Error( 'ohmylms_course_invalid_taxonomy', __( 'That taxonomy does not apply to courses.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$result = wp_set_object_terms( $course_id, array_map( 'absint', (array) $terms ), (string) $taxonomy );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'terms'   => $result,
			)
		);
	}
}
