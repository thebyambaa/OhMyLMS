<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * StudentController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_User;

/**
 * StudentController class.
 *
 * REST API for the student directory under `ohmylms/v1/students`. See docs/api/students.md for the
 * contract. Enrolments of one student live in StudentProController.
 *
 * A student is a user with an enrolment, or with the student role (`subscriber`, `ohmylms_student` or
 * the role returned by ohmylms_get_student_role()). Students are personal data, so every route needs
 * CAPABILITY and responses only carry the fields the admin screen shows.
 *
 * Authentication is WordPress core's: cookie authentication requires the `X-WP-Nonce` header (checked
 * by rest_cookie_check_errors() before any permission callback runs); application passwords also work.
 *
 * @since 1.0.0
 */
class StudentController extends RestController {

	/**
	 * Capability required by every student route (including StudentProController).
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Post type of courses.
	 */
	const COURSE_POST_TYPE = 'ohmylms-course';

	/**
	 * User meta flag set while a student is blocked.
	 */
	const BLOCKED_META = '_ohmylms_banned_student';

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * The base route for student endpoints.
	 *
	 * @var string
	 */
	protected $rest_base = 'students';

	/**
	 * Sort keys and the SQL expression behind each.
	 *
	 * @var array<string,string>
	 */
	const ORDERBY_COLUMNS = array(
		'name'             => 'u.display_name',
		'email'            => 'u.user_email',
		'registered'       => 'u.user_registered',
		'courses_enrolled' => 'courses_enrolled',
	);

	/**
	 * Register the routes for the student endpoints.
	 */
	public function register_routes() {
		$base = '/' . $this->rest_base;

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
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/courses',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_courses' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_course_collection_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/(?P<id>[\d]+)',
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'Unique identifier for the student (the user ID).', 'ohmylms' ),
						'type'        => 'integer',
						'minimum'     => 1,
					),
				),
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
					'args'                => array(
						'blocked' => array(
							'description' => __( 'Whether the student is blocked from their courses.', 'ohmylms' ),
							'type'        => 'boolean',
							'required'    => true,
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Check the capability shared by every student route.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message used when access is denied.
	 * @return true|WP_Error
	 */
	public static function authorize( $code, $message ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return new WP_Error( $code, $message, array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * Check if the current user can list students (and course choices).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return self::authorize( 'ohmylms_student_cannot_view', __( 'Sorry, you are not allowed to view students.', 'ohmylms' ) );
	}

	/**
	 * Check if the current user can read one student.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return self::authorize( 'ohmylms_student_cannot_view', __( 'Sorry, you are not allowed to view students.', 'ohmylms' ) );
	}

	/**
	 * Check if the current user can block or unblock a student.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		return self::authorize( 'ohmylms_student_cannot_edit', __( 'Sorry, you are not allowed to edit students.', 'ohmylms' ) );
	}

	/**
	 * Build the not-found error.
	 *
	 * @return WP_Error
	 */
	public static function not_found_error() {
		return new WP_Error( 'ohmylms_student_not_found', __( 'Student not found.', 'ohmylms' ), array( 'status' => 404 ) );
	}

	/**
	 * Role slugs that make a user part of the student directory.
	 *
	 * @return string[]
	 */
	private function student_roles() {
		return array_values( array_unique( array( 'subscriber', 'ohmylms_student', ohmylms_get_student_role() ) ) );
	}

	/**
	 * Build the FROM/WHERE part shared by the page and count queries.
	 *
	 * @param array<string,mixed> $filters Filters: search, course, user_id.
	 * @return array{0:string,1:array} SQL fragment and its prepare() arguments.
	 */
	private function directory_sql( array $filters ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ohmylms_user_enrollment';
		$sql   = ' FROM %i u LEFT JOIN ( SELECT user_id, COUNT(DISTINCT course_id) AS courses_enrolled,'
			. " COUNT(DISTINCT CASE WHEN progress = 'completed' THEN course_id END) AS courses_completed"
			. " FROM %i WHERE status IN ('enrolled','banned') GROUP BY user_id ) agg ON agg.user_id = u.ID";
		$args  = array( $wpdb->users, $table );
		$likes = array();
		$roles = array( $wpdb->usermeta, $wpdb->get_blog_prefix() . 'capabilities' );
		foreach ( $this->student_roles() as $role ) {
			$likes[] = 'm.meta_value LIKE %s';
			$roles[] = '%' . $wpdb->esc_like( '"' . $role . '";b:1;' ) . '%';
		}

		$sql .= ' WHERE ( agg.user_id IS NOT NULL OR EXISTS ( SELECT 1 FROM %i m WHERE m.user_id = u.ID AND m.meta_key = %s AND ( ' . implode( ' OR ', $likes ) . ' ) ) )';
		$args = array_merge( $args, $roles );

		if ( ! empty( $filters['user_id'] ) ) {
			$sql   .= ' AND u.ID = %d';
			$args[] = (int) $filters['user_id'];
		}

		if ( ! empty( $filters['course'] ) ) {
			$sql   .= " AND EXISTS ( SELECT 1 FROM %i ec WHERE ec.user_id = u.ID AND ec.course_id = %d AND ec.status IN ('enrolled','banned') )";
			$args[] = $table;
			$args[] = (int) $filters['course'];
		}

		if ( isset( $filters['search'] ) && '' !== $filters['search'] ) {
			$like   = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$sql   .= ' AND ( u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s )';
			$args[] = $like;
			$args[] = $like;
			$args[] = $like;
		}

		return array( $sql, $args );
	}

	/**
	 * Read directory rows.
	 *
	 * @param array<string,mixed> $filters  Filters accepted by directory_sql().
	 * @param string              $orderby  Key of ORDERBY_COLUMNS.
	 * @param string              $order    ASC or DESC.
	 * @param int                 $limit    Row limit.
	 * @param int                 $offset   Row offset.
	 * @return array[] Rows keyed by column alias.
	 */
	private function query_rows( array $filters, $orderby, $order, $limit, $offset ) {
		global $wpdb;

		list( $from, $args ) = $this->directory_sql( $filters );
		$column              = self::ORDERBY_COLUMNS[ $orderby ];
		$direction           = 'DESC' === $order ? 'DESC' : 'ASC';
		$sql                 = 'SELECT u.ID AS id, u.display_name, u.user_email, u.user_registered,'
			. ' COALESCE(agg.courses_enrolled, 0) AS courses_enrolled, COALESCE(agg.courses_completed, 0) AS courses_completed'
			. $from
			. " ORDER BY {$column} {$direction}, u.ID ASC LIMIT %d OFFSET %d";
		$args[]              = (int) $limit;
		$args[]              = (int) $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Prepared with %i identifiers and an allow-listed ORDER BY.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count directory rows.
	 *
	 * @param array<string,mixed> $filters Filters accepted by directory_sql().
	 * @return int
	 */
	private function count_rows( array $filters ) {
		global $wpdb;

		list( $from, $args ) = $this->directory_sql( $filters );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Prepared with %i identifiers.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*)' . $from, $args ) );
	}

	/**
	 * Load one student of the directory.
	 *
	 * @param int $id User ID.
	 * @return array|WP_Error Row, or a 404 error when the user is not a student.
	 */
	public function find_student( $id ) {
		$id = absint( $id );
		if ( ! $id ) {
			return self::not_found_error();
		}
		$rows = $this->query_rows( array( 'user_id' => $id ), 'name', 'ASC', 1, 0 );
		return $rows ? $rows[0] : self::not_found_error();
	}

	/**
	 * Get students.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$page     = max( 1, (int) $request['page'] );
		$per_page = min( 100, max( 1, (int) $request['per_page'] ) );
		$filters  = array(
			'search' => (string) $request['search'],
			'course' => absint( $request['course'] ),
		);

		$total       = $this->count_rows( $filters );
		$total_pages = (int) ceil( $total / $per_page );
		if ( $total > 0 && $page > $total_pages ) {
			return new WP_Error( 'ohmylms_student_invalid_page', __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$rows = $this->query_rows( $filters, $request['orderby'], strtoupper( (string) $request['order'] ), $per_page, ( $page - 1 ) * $per_page );
		// One query for the meta of the whole page instead of one per student.
		update_meta_cache( 'user', array_map( 'intval', wp_list_pluck( $rows, 'id' ) ) );

		$data = array();
		foreach ( $rows as $row ) {
			$data[] = $this->prepare_response_for_collection( $this->prepare_item_for_response( $row, $request ) );
		}

		$response = rest_ensure_response( $data );
		$this->add_pagination_headers( $response, $request, $total, $total_pages, $page, $this->rest_base );
		return $response;
	}

	/**
	 * Add X-WP-Total, X-WP-TotalPages and Link headers.
	 *
	 * @param WP_REST_Response $response    Response.
	 * @param WP_REST_Request  $request     Request.
	 * @param int              $total       Total items.
	 * @param int              $total_pages Total pages.
	 * @param int              $page        Current page.
	 * @param string           $route       Route below the namespace.
	 * @return void
	 */
	public function add_pagination_headers( WP_REST_Response $response, WP_REST_Request $request, $total, $total_pages, $page, $route ) {
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $total_pages );

		$base = add_query_arg( urlencode_deep( $request->get_query_params() ), rest_url( $this->namespace . '/' . $route ) );
		if ( $page > 1 ) {
			$response->link_header( 'prev', add_query_arg( 'page', $page - 1, $base ) );
		}
		if ( $page < $total_pages ) {
			$response->link_header( 'next', add_query_arg( 'page', $page + 1, $base ) );
		}
	}

	/**
	 * Get one student.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$row = $this->find_student( $request['id'] );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		return $this->prepare_item_for_response( $row, $request );
	}

	/**
	 * Block or unblock a student.
	 *
	 * Blocking flags the user and sets their enrolments (and memberships) to `banned`; unblocking
	 * reverses exactly that. Administrators and the current user cannot be blocked.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		global $wpdb;

		$row = $this->find_student( $request['id'] );
		if ( is_wp_error( $row ) ) {
			return $row;
		}

		$user_id = (int) $row['id'];
		$block   = rest_sanitize_boolean( $request['blocked'] );

		if ( $block && ( get_current_user_id() === $user_id || user_can( $user_id, self::CAPABILITY ) ) ) {
			return new WP_Error( 'ohmylms_student_protected', __( 'You cannot block yourself or an administrator.', 'ohmylms' ), array( 'status' => 422 ) );
		}

		$from = $block ? 'enrolled' : 'banned';
		$to   = $block ? 'banned' : 'enrolled';

		$tables = array( $wpdb->prefix . 'ohmylms_user_enrollment', $wpdb->prefix . 'ohmylms_user_membership' );
		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Existence probe for an optional table.
			if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- No API for enrolment rows.
			$result = $wpdb->update(
				$table,
				array( 'status' => $to ),
				array(
					'user_id' => $user_id,
					'status'  => $from,
				),
				array( '%s' ),
				array( '%d', '%s' )
			); // phpcs:ignore WordPress.Arrays.MultipleStatementAlignment
			if ( false === $result ) {
				return new WP_Error( 'ohmylms_student_update_failed', __( 'The student could not be updated.', 'ohmylms' ), array( 'status' => 500 ) );
			}
		}

		if ( $block ) {
			update_user_meta( $user_id, self::BLOCKED_META, 'yes' );
		} else {
			delete_user_meta( $user_id, self::BLOCKED_META );
		}

		/**
		 * Fires after a student was blocked.
		 *
		 * @param int $user_id Student (user) ID.
		 */
		do_action( $block ? 'ohmylms_student_blocked' : 'ohmylms_student_unblocked', $user_id );

		return $this->get_item( $request );
	}

	/**
	 * Matches the REST schema: turn a directory row into a response.
	 *
	 * @param array           $item    Row from query_rows().
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		$user_id    = (int) $item['id'];
		$last_login = get_user_meta( $user_id, '_ohmylms_last_login', true );

		$data = array(
			'id'                => $user_id,
			'name'              => (string) $item['display_name'],
			'email'             => (string) $item['user_email'],
			'avatar_url'        => (string) get_avatar_url( $user_id, array( 'size' => 96 ) ),
			'registered'        => self::format_gmt( $item['user_registered'] ),
			'last_login'        => is_string( $last_login ) && '' !== $last_login ? self::format_local( $last_login ) : null,
			'blocked'           => 'yes' === get_user_meta( $user_id, self::BLOCKED_META, true ),
			'enrolled_courses'  => (int) $item['courses_enrolled'],
			'completed_courses' => (int) $item['courses_completed'],
		);

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links(
			array(
				'self'        => array( 'href' => rest_url( $this->namespace . '/' . $this->rest_base . '/' . $user_id ) ),
				'collection'  => array( 'href' => rest_url( $this->namespace . '/' . $this->rest_base ) ),
				'enrollments' => array( 'href' => rest_url( $this->namespace . '/' . $this->rest_base . '/' . $user_id . '/enrollments' ) ),
			)
		);
		return $response;
	}

	/**
	 * Format a MySQL date stored in GMT as a site-time ISO string.
	 *
	 * @param string $mysql MySQL datetime in GMT.
	 * @return string|null
	 */
	public static function format_gmt( $mysql ) {
		if ( ! is_string( $mysql ) || '' === $mysql || 0 === strpos( $mysql, '0000-00-00' ) ) {
			return null;
		}
		return get_date_from_gmt( $mysql, 'Y-m-d\TH:i:s' );
	}

	/**
	 * Format a MySQL date stored in site time as an ISO string.
	 *
	 * @param string $mysql MySQL datetime in site time.
	 * @return string|null
	 */
	public static function format_local( $mysql ) {
		if ( ! is_string( $mysql ) || '' === $mysql || 0 === strpos( $mysql, '0000-00-00' ) ) {
			return null;
		}
		return str_replace( ' ', 'T', $mysql );
	}

	/**
	 * List courses for the course filter and the enrol form.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_courses( $request ) {
		$page     = max( 1, (int) $request['page'] );
		$per_page = min( 100, max( 1, (int) $request['per_page'] ) );

		$query = new WP_Query(
			array(
				'post_type'              => self::COURSE_POST_TYPE,
				'post_status'            => array( 'publish', 'private', 'draft', 'pending', 'future' ),
				'posts_per_page'         => $per_page,
				'paged'                  => $page,
				's'                      => (string) $request['search'],
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'ignore_sticky_posts'    => true,
			)
		);

		$total       = (int) $query->found_posts;
		$total_pages = (int) $query->max_num_pages;
		if ( $total > 0 && $page > $total_pages ) {
			return new WP_Error( 'ohmylms_student_invalid_page', __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$data = array();
		foreach ( $query->posts as $course_id ) {
			$data[] = array(
				'id'     => (int) $course_id,
				'title'  => html_entity_decode( get_the_title( $course_id ), ENT_QUOTES, 'UTF-8' ),
				'status' => get_post_status( $course_id ),
			);
		}

		$response = rest_ensure_response( $data );
		$this->add_pagination_headers( $response, $request, $total, $total_pages, $page, $this->rest_base . '/courses' );
		return $response;
	}

	/**
	 * Collection parameters of the course choices route.
	 *
	 * @return array
	 */
	private function get_course_collection_params() {
		$params = array_intersect_key( parent::get_collection_params(), array_flip( array( 'context', 'page', 'per_page', 'search' ) ) );

		$params['per_page']['default']   = 20;
		$params['per_page']['maximum']   = 100;
		$params['search']['description'] = __( 'Limit results to courses whose title matches the string.', 'ohmylms' );
		$params['search']['maxLength']   = 100;

		return $params;
	}

	/**
	 * Get the student schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$context = array( 'view' );

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'student',
			'type'       => 'object',
			'properties' => array(
				'id'                => array(
					'description' => __( 'Unique identifier for the student (the user ID).', 'ohmylms' ),
					'type'        => 'integer',
					'context'     => $context,
					'readonly'    => true,
				),
				'name'              => array(
					'description' => __( 'Display name.', 'ohmylms' ),
					'type'        => 'string',
					'context'     => $context,
					'readonly'    => true,
				),
				'email'             => array(
					'description' => __( 'Email address.', 'ohmylms' ),
					'type'        => 'string',
					'format'      => 'email',
					'context'     => $context,
					'readonly'    => true,
				),
				'avatar_url'        => array(
					'description' => __( 'Avatar URL.', 'ohmylms' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => $context,
					'readonly'    => true,
				),
				'registered'        => array(
					'description' => __( 'Registration date in site time.', 'ohmylms' ),
					'type'        => array( 'string', 'null' ),
					'context'     => $context,
					'readonly'    => true,
				),
				'last_login'        => array(
					'description' => __( 'Last login in site time, or null.', 'ohmylms' ),
					'type'        => array( 'string', 'null' ),
					'context'     => $context,
					'readonly'    => true,
				),
				'blocked'           => array(
					'description' => __( 'Whether the student is blocked.', 'ohmylms' ),
					'type'        => 'boolean',
					'context'     => $context,
				),
				'enrolled_courses'  => array(
					'description' => __( 'Number of courses the student is enrolled in.', 'ohmylms' ),
					'type'        => 'integer',
					'context'     => $context,
					'readonly'    => true,
				),
				'completed_courses' => array(
					'description' => __( 'Number of those courses that are completed.', 'ohmylms' ),
					'type'        => 'integer',
					'context'     => $context,
					'readonly'    => true,
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Get the collection parameters.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		$params['per_page']['default']   = 20;
		$params['per_page']['maximum']   = 100;
		$params['search']['description'] = __( 'Limit results to students whose name, username or email contains the string.', 'ohmylms' );
		$params['search']['maxLength']   = 100;

		$params['course'] = array(
			'description' => __( 'Limit results to students enrolled in one course.', 'ohmylms' ),
			'type'        => 'integer',
			'minimum'     => 1,
		);

		$params['orderby'] = array(
			'description' => __( 'Sort collection by attribute.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'name',
			'enum'        => array_keys( self::ORDERBY_COLUMNS ),
		);

		$params['order'] = array(
			'description' => __( 'Order sort attribute ascending or descending.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'asc',
			'enum'        => array( 'asc', 'desc' ),
		);

		return $params;
	}
}
