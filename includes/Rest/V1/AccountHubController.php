<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * AccountHubController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Schools\Access;
use OhMyLMS\Schools\ClassManagement;
use OhMyLMS\Schools\Schema;
use OhMyLMS\Schools\Service;
use OhMyLMS\Utility\Transaction;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * AccountHubController class.
 *
 * Admin REST API for schools, classes, class rosters, teachers and parents under
 * `ohmylms/v1/account-hub`. See docs/api/account-hub.md for the contract. Every write goes through the
 * existing services in `includes/Schools/` (Service, ClassManagement), which re-check relationship
 * scope; this controller adds capability checks, validation, paging, minimal fields and stale-save
 * detection.
 *
 * Platform administrators (`manage_options`) see and manage everything. School administrators (an
 * active `school_admin` membership in an active school) see and manage only their schools. Everyone
 * else gets 401 (anonymous) or 403.
 *
 * @since 1.0.0
 */
class AccountHubController extends RestController {

	/**
	 * Capability of a platform administrator.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Most rows in an unpaged sub-list (children, pending invitations, academic years).
	 */
	const MAX_SUBLIST = 100;

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
	protected $rest_base = 'account-hub';

	/**
	 * Schools each school administrator manages, keyed by user id (one instance serves many requests).
	 *
	 * @var array<int,int[]>
	 */
	private $managed = array();

	/**
	 * Register the routes.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		$base = '/' . $this->rest_base;
		$id   = array(
			'description' => __( 'Unique identifier.', 'ohmylms' ),
			'type'        => 'integer',
			'minimum'     => 1,
		);
		$read = array( $this, 'read_permissions_check' );
		$edit = array( $this, 'edit_permissions_check' );

		register_rest_route(
			$this->namespace,
			$base . '/schools',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_schools' ),
					'permission_callback' => $read,
					'args'                => $this->collection_args(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_school' ),
					'permission_callback' => array( $this, 'create_school_permissions_check' ),
					'args'                => $this->school_args( true ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/schools/(?P<id>[\d]+)',
			array(
				'args'   => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_school' ),
					'permission_callback' => $read,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_school' ),
					'permission_callback' => $edit,
					'args'                => $this->school_args( false ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/schools/(?P<id>[\d]+)/years',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_year' ),
					'permission_callback' => $edit,
					'args'                => array(
						'label'     => array(
							'type'      => 'string',
							'required'  => true,
							'minLength' => 1,
							'maxLength' => 100,
						),
						'starts_at' => array(
							'type'     => 'string',
							'required' => true,
							'pattern'  => '^\d{4}-\d{2}-\d{2}$',
						),
						'ends_at'   => array(
							'type'     => 'string',
							'required' => true,
							'pattern'  => '^\d{4}-\d{2}-\d{2}$',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/schools/(?P<id>[\d]+)/classes',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_school_classes' ),
					'permission_callback' => $read,
					'args'                => $this->collection_args( $this->class_status_arg() ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_class' ),
					'permission_callback' => $edit,
					'args'                => $this->class_args( true ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/schools/(?P<id>[\d]+)/students',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_school_students' ),
					'permission_callback' => $read,
					'args'                => $this->collection_args(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/classes',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_classes' ),
					'permission_callback' => $read,
					'args'                => $this->collection_args(
						array_merge(
							$this->class_status_arg(),
							array(
								'school_id' => array(
									'description' => __( 'Only classes of this school; 0 for independent classes.', 'ohmylms' ),
									'type'        => 'integer',
									'minimum'     => 0,
								),
							)
						)
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/classes/(?P<id>[\d]+)',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_class' ),
					'permission_callback' => $read,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_class' ),
					'permission_callback' => $edit,
					'args'                => $this->class_args( false ),
				),
			)
		);

		foreach ( array( 'archive', 'restore' ) as $action ) {
			register_rest_route(
				$this->namespace,
				$base . '/classes/(?P<id>[\d]+)/' . $action,
				array(
					'args' => array( 'id' => $id ),
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $this, $action . '_class' ),
						'permission_callback' => $edit,
					),
				)
			);
		}

		$role = array(
			'description' => __( 'Class role.', 'ohmylms' ),
			'type'        => 'string',
			'enum'        => array( 'student', 'teacher' ),
		);
		register_rest_route(
			$this->namespace,
			$base . '/classes/(?P<id>[\d]+)/members',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_members' ),
					'permission_callback' => $read,
					'args'                => $this->collection_args( array( 'role' => $role ) ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_member' ),
					'permission_callback' => $edit,
					'args'                => array(
						'user_id' => array_merge( $id, array( 'required' => true ) ),
						'role'    => array_merge( $role, array( 'required' => true ) ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/classes/(?P<id>[\d]+)/members/(?P<user_id>[\d]+)',
			array(
				'args' => array(
					'id'      => $id,
					'user_id' => $id,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'remove_member' ),
					'permission_callback' => $edit,
					'args'                => array( 'role' => array_merge( $role, array( 'required' => true ) ) ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/classes/(?P<id>[\d]+)/candidates',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_candidates' ),
					'permission_callback' => $read,
					'args'                => $this->collection_args( array( 'role' => array_merge( $role, array( 'required' => true ) ) ) ),
				),
			)
		);

		$school_filter = array(
			'school_id' => array(
				'description' => __( 'Only people of this school.', 'ohmylms' ),
				'type'        => 'integer',
				'minimum'     => 1,
			),
		);
		foreach ( array( 'teachers', 'parents' ) as $people ) {
			register_rest_route(
				$this->namespace,
				$base . '/' . $people,
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_' . $people ),
						'permission_callback' => $read,
						'args'                => $this->collection_args( $school_filter ),
					),
				)
			);
		}

		register_rest_route(
			$this->namespace,
			$base . '/parents/(?P<id>[\d]+)/children',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_children' ),
					'permission_callback' => $read,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'link_child' ),
					'permission_callback' => $edit,
					'args'                => array(
						'school_id'  => array_merge( $id, array( 'required' => true ) ),
						'student_id' => array_merge( $id, array( 'required' => true ) ),
						'send_email' => array(
							'description' => __( 'Also email the invitation link to the parent.', 'ohmylms' ),
							'type'        => 'boolean',
							'default'     => false,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/parents/(?P<id>[\d]+)/children/(?P<link>[\d]+)',
			array(
				'args' => array(
					'id'   => $id,
					'link' => $id,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'unlink_child' ),
					'permission_callback' => $edit,
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/parents/(?P<id>[\d]+)/invitations/(?P<invitation>[\d]+)',
			array(
				'args' => array(
					'id'         => $id,
					'invitation' => $id,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'revoke_invitation' ),
					'permission_callback' => $edit,
				),
			)
		);
	}

	/*
	 * Permissions.
	 */

	/**
	 * Allow platform administrators and active school administrators.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @return true|WP_Error
	 */
	private function require_manager( $code, $message ) {
		if ( is_user_logged_in() && ( current_user_can( self::CAPABILITY ) || $this->managed_schools() ) ) {
			return true;
		}

		return new WP_Error( $code, $message, array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Check if the current user can read Account Hub records.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function read_permissions_check( $request ) {
		return $this->require_manager( 'ohmylms_account_hub_cannot_view', __( 'Sorry, you are not allowed to view schools and classes.', 'ohmylms' ) );
	}

	/**
	 * Check if the current user can change Account Hub records.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function edit_permissions_check( $request ) {
		return $this->require_manager( 'ohmylms_account_hub_cannot_edit', __( 'Sorry, you are not allowed to change schools and classes.', 'ohmylms' ) );
	}

	/**
	 * Only platform administrators create schools here.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function create_school_permissions_check( $request ) {
		if ( current_user_can( self::CAPABILITY ) ) {
			return true;
		}

		return new WP_Error( 'ohmylms_account_hub_cannot_create', __( 'Sorry, you are not allowed to create schools.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Active schools the current user administers, or null for a platform administrator.
	 *
	 * @return int[]|null
	 */
	protected function managed_schools() {
		if ( current_user_can( self::CAPABILITY ) ) {
			return null;
		}
		$user = get_current_user_id();
		if ( ! isset( $this->managed[ $user ] ) ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Current relationships decide scope; never persisted across requests.
			$ids                    = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT m.school_id FROM %i m JOIN %i s ON s.id=m.school_id AND s.status='active' WHERE m.user_id=%d AND m.role='school_admin' AND m.status='active'", Schema::table( 'school_memberships' ), Schema::table( 'schools' ), $user ) );
			$this->managed[ $user ] = array_map( 'intval', (array) $ids );
		}

		return $this->managed[ $user ];
	}

	/**
	 * SQL restricting a school id column to the current scope.
	 *
	 * @param string $column Trusted column expression.
	 * @return string
	 */
	private function scope_sql( $column ) {
		$managed = $this->managed_schools();
		if ( null === $managed ) {
			return '';
		}

		return $managed ? " AND {$column} IN (" . implode( ',', array_map( 'intval', $managed ) ) . ')' : ' AND 1=0';
	}

	/**
	 * Whether the current user may manage a school.
	 *
	 * @param int $school_id School id.
	 * @return bool
	 */
	protected function in_scope( $school_id ) {
		$managed = $this->managed_schools();

		return null === $managed || in_array( (int) $school_id, $managed, true );
	}

	/**
	 * Whether email addresses may be shown (the legacy directories showed them to `list_users`).
	 *
	 * @return bool
	 */
	protected function can_see_email() {
		return current_user_can( 'list_users' );
	}

	/*
	 * Shared helpers.
	 */

	/**
	 * Build an error.
	 *
	 * @param string $code    Suffix after `ohmylms_account_hub_`.
	 * @param string $message Message.
	 * @param int    $status  HTTP status.
	 * @param array  $data    Extra error data.
	 * @return WP_Error
	 */
	protected function error( $code, $message, $status, array $data = array() ) {
		return new WP_Error( 'ohmylms_account_hub_' . $code, $message, array_merge( array( 'status' => $status ), $data ) );
	}

	/**
	 * Out-of-scope error.
	 *
	 * @return WP_Error
	 */
	protected function forbidden() {
		return $this->error( 'forbidden', __( 'Sorry, you can only manage your own schools.', 'ohmylms' ), 403 );
	}

	/**
	 * Translate an exception from the school services.
	 *
	 * @param \Throwable $failure Thrown error.
	 * @return WP_Error
	 */
	protected function service_error( \Throwable $failure ) {
		$status = $failure instanceof \RuntimeException ? (int) $failure->getCode() : 500;
		if ( 403 === $status ) {
			return $this->forbidden();
		}
		if ( 400 === $status ) {
			return $this->error( 'rejected', $failure->getMessage(), 422 );
		}
		if ( 409 === $status ) {
			return $this->error( 'duplicate', $failure->getMessage(), 409 );
		}

		return $this->error( 'failed', __( 'The change could not be saved. Nothing was changed.', 'ohmylms' ), 500 );
	}

	/**
	 * Collection arguments.
	 *
	 * @param array $extra Extra arguments.
	 * @return array
	 */
	protected function collection_args( array $extra = array() ) {
		return array_merge(
			array(
				'page'     => array(
					'description' => __( 'Current page of the collection.', 'ohmylms' ),
					'type'        => 'integer',
					'default'     => 1,
					'minimum'     => 1,
				),
				'per_page' => array(
					'description' => __( 'Maximum number of items per page.', 'ohmylms' ),
					'type'        => 'integer',
					'default'     => 20,
					'minimum'     => 1,
					'maximum'     => 100,
				),
				'search'   => array(
					'description' => __( 'Limit results to names that contain the string.', 'ohmylms' ),
					'type'        => 'string',
					'default'     => '',
					'maxLength'   => 100,
				),
			),
			$extra
		);
	}

	/**
	 * Class status filter.
	 *
	 * @return array
	 */
	private function class_status_arg() {
		return array(
			'status' => array(
				'description' => __( 'Limit result set to classes with a status.', 'ohmylms' ),
				'type'        => 'string',
				'default'     => 'all',
				'enum'        => array( 'all', 'active', 'archived' ),
			),
		);
	}

	/**
	 * School write arguments.
	 *
	 * @param bool $create Whether this is the create route.
	 * @return array
	 */
	private function school_args( $create ) {
		$args = array(
			'name'     => array(
				'description' => __( 'School name.', 'ohmylms' ),
				'type'        => 'string',
				'minLength'   => 1,
				'maxLength'   => 190,
			),
			'timezone' => array(
				'description' => __( 'IANA time zone, such as Asia/Ulaanbaatar.', 'ohmylms' ),
				'type'        => 'string',
				'minLength'   => 1,
				'maxLength'   => 80,
			),
		);
		if ( $create ) {
			$args['name']['required']    = true;
			$args['timezone']['default'] = 'UTC';
		} else {
			$args['version'] = $this->version_arg();
		}

		return $args;
	}

	/**
	 * Class write arguments.
	 *
	 * @param bool $create Whether this is the create route.
	 * @return array
	 */
	private function class_args( $create ) {
		$args = array(
			'name'    => array(
				'description' => __( 'Class name.', 'ohmylms' ),
				'type'        => 'string',
				'minLength'   => 1,
				'maxLength'   => 190,
			),
			'subject' => array(
				'description' => __( 'Subject.', 'ohmylms' ),
				'type'        => 'string',
				'maxLength'   => 100,
			),
			'grade'   => array(
				'description' => __( 'Grade.', 'ohmylms' ),
				'type'        => 'string',
				'maxLength'   => 50,
			),
		);
		if ( $create ) {
			$args['name']['required']   = true;
			$args['academic_year_id']   = array(
				'description' => __( 'Active academic year of the school.', 'ohmylms' ),
				'type'        => 'integer',
				'minimum'     => 1,
				'required'    => true,
			);
			$args['subject']['default'] = '';
			$args['grade']['default']   = '';
		} else {
			$args['version'] = $this->version_arg();
		}

		return $args;
	}

	/**
	 * Stale-save argument.
	 *
	 * @return array
	 */
	private function version_arg() {
		return array(
			'description' => __( 'The version the editor loaded; a different stored version answers 409.', 'ohmylms' ),
			'type'        => 'string',
			'pattern'     => '^[a-f0-9]{16}$',
		);
	}

	/**
	 * Clean a plain-text field.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $max   Maximum length.
	 * @return string
	 */
	protected function clean( $value, $max = 190 ) {
		return Service::text( trim( (string) $value ), $max );
	}

	/**
	 * Offset and page validation shared by every list.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param int             $total   Total rows.
	 * @return array{0:int,1:int,2:int}|WP_Error Page, per page, pages.
	 */
	protected function paging( $request, $total ) {
		$page     = max( 1, (int) $request['page'] );
		$per_page = min( 100, max( 1, (int) $request['per_page'] ) );
		$pages    = (int) ceil( $total / $per_page );
		if ( $page > $pages && $total > 0 ) {
			return $this->error( 'invalid_page', __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), 400 );
		}

		return array( $page, $per_page, $pages );
	}

	/**
	 * Wrap a page of rows with totals.
	 *
	 * @param array $rows  Rows.
	 * @param int   $total Total rows.
	 * @param int   $pages Total pages.
	 * @return WP_REST_Response
	 */
	protected function collection( array $rows, $total, $pages ) {
		$response = rest_ensure_response( $rows );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $pages );

		return $response;
	}

	/**
	 * LIKE pattern for a search string.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string
	 */
	protected function like( $request ) {
		global $wpdb;

		return '%' . $wpdb->esc_like( $this->clean( $request['search'], 100 ) ) . '%';
	}

	/**
	 * UTC database time as ISO 8601, or null.
	 *
	 * @param mixed $value Database datetime.
	 * @return string|null
	 */
	protected function iso( $value ) {
		$value = (string) $value;

		return ( '' === $value || 0 === strpos( $value, '0000-00-00' ) ) ? null : str_replace( ' ', 'T', $value ) . 'Z';
	}

	/**
	 * Created response with Location.
	 *
	 * @param array  $data Body.
	 * @param string $path Path below the base.
	 * @return WP_REST_Response
	 */
	protected function created( array $data, $path ) {
		$response = rest_ensure_response( $data );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( $this->namespace . '/' . $this->rest_base . $path ) );

		return $response;
	}

	/*
	 * Schools.
	 */

	/**
	 * Load a school the current user may manage.
	 *
	 * @param int $id School id.
	 * @return array|WP_Error
	 */
	protected function school_or_error( $id ) {
		$school = Service::row( 'schools', absint( $id ) );
		if ( ! $school ) {
			return $this->error( 'school_not_found', __( 'School not found.', 'ohmylms' ), 404 );
		}

		return $this->in_scope( $school['id'] ) ? $school : $this->forbidden();
	}

	/**
	 * Stale-save version of a school.
	 *
	 * @param array $row School row.
	 * @return string
	 */
	private function school_version( array $row ) {
		return substr( hash( 'sha256', wp_json_encode( array( (string) $row['name'], (string) $row['timezone'], (string) $row['status'] ) ) ), 0, 16 );
	}

	/**
	 * Page of schools.
	 *
	 * @param int    $id     Only this school, or 0.
	 * @param string $like   LIKE pattern.
	 * @param int    $limit  Rows.
	 * @param int    $offset Offset.
	 * @return array
	 */
	private function school_rows( $id, $like, $limit, $offset ) {
		global $wpdb;
		$s     = Schema::table( 'schools' );
		$c     = Schema::table( 'classes' );
		$m     = Schema::table( 'school_memberships' );
		$extra = $this->scope_sql( 's.id' ) . ( $id ? $wpdb->prepare( ' AND s.id=%d', $id ) : '' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table names and the integer scope are trusted; values are prepared.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.id,s.name,s.slug,s.timezone,s.status,s.created_at,
				(SELECT COUNT(*) FROM $c c WHERE c.school_id=s.id AND c.status='active') AS class_count,
				(SELECT COUNT(*) FROM $m m WHERE m.school_id=s.id AND m.role='student' AND m.status='active') AS student_count,
				(SELECT COUNT(*) FROM $m m WHERE m.school_id=s.id AND m.role='teacher' AND m.status='active') AS teacher_count
				FROM $s s WHERE s.name LIKE %s{$extra} ORDER BY s.name, s.id LIMIT %d OFFSET %d",
				$like,
				$limit,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable

		return array_map( array( $this, 'prepare_school' ), (array) $rows );
	}

	/**
	 * Shape a school row.
	 *
	 * @param array $row Database row.
	 * @return array
	 */
	public function prepare_school( array $row ) {
		return array(
			'id'            => (int) $row['id'],
			'name'          => (string) $row['name'],
			'slug'          => (string) $row['slug'],
			'timezone'      => (string) $row['timezone'],
			'status'        => (string) $row['status'],
			'created_at'    => $this->iso( $row['created_at'] ),
			'class_count'   => (int) $row['class_count'],
			'student_count' => (int) $row['student_count'],
			'teacher_count' => (int) $row['teacher_count'],
			'version'       => $this->school_version( $row ),
		);
	}

	/**
	 * School with its academic years.
	 *
	 * @param int $id School id.
	 * @return array
	 */
	private function school_detail( $id ) {
		global $wpdb;
		$rows = $this->school_rows( $id, '%', 1, 0 );
		$data = $rows[0];
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bounded list of one school's years.
		$years         = $wpdb->get_results( $wpdb->prepare( 'SELECT id,label,starts_at,ends_at,status FROM %i WHERE school_id=%d ORDER BY starts_at DESC, id DESC LIMIT %d', Schema::table( 'academic_years' ), $id, self::MAX_SUBLIST ), ARRAY_A );
		$data['years'] = array_map(
			static function ( $year ) {
				return array(
					'id'        => (int) $year['id'],
					'label'     => (string) $year['label'],
					'starts_at' => (string) $year['starts_at'],
					'ends_at'   => (string) $year['ends_at'],
					'status'    => (string) $year['status'],
				);
			},
			(array) $years
		);

		return $data;
	}

	/**
	 * List schools.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_schools( $request ) {
		global $wpdb;
		$like  = $this->like( $request );
		$scope = $this->scope_sql( 's.id' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Integer scope is trusted.
		$total  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i s WHERE s.name LIKE %s{$scope}", Schema::table( 'schools' ), $like ) );
		$paging = $this->paging( $request, $total );
		if ( is_wp_error( $paging ) ) {
			return $paging;
		}
		list( $page, $per_page, $pages ) = $paging;

		return $this->collection( $this->school_rows( 0, $like, $per_page, ( $page - 1 ) * $per_page ), $total, $pages );
	}

	/**
	 * Read one school with its academic years.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_school( $request ) {
		$school = $this->school_or_error( $request['id'] );
		if ( is_wp_error( $school ) ) {
			return $school;
		}

		return rest_ensure_response( $this->school_detail( (int) $school['id'] ) );
	}

	/**
	 * Validate a school name and time zone.
	 *
	 * @param string $name     Name.
	 * @param string $timezone Time zone.
	 * @return true|WP_Error
	 */
	private function validate_school( $name, $timezone ) {
		if ( '' === $name ) {
			return $this->error( 'invalid_name', __( 'Enter a school name.', 'ohmylms' ), 422 );
		}
		if ( ! in_array( $timezone, timezone_identifiers_list(), true ) ) {
			return $this->error( 'invalid_timezone', __( 'Choose a valid time zone, such as Asia/Ulaanbaatar.', 'ohmylms' ), 422 );
		}

		return true;
	}

	/**
	 * Create a school (platform administrators only; no one becomes its administrator).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_school( $request ) {
		$name     = $this->clean( $request['name'] );
		$timezone = $this->clean( $request['timezone'], 80 );
		$valid    = $this->validate_school( $name, $timezone );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		try {
			$created = Service::create_school(
				array(
					'name'     => $name,
					'timezone' => $timezone,
				)
			);
		} catch ( \Throwable $failure ) {
			return $this->service_error( $failure );
		}

		return $this->created( $this->school_detail( (int) $created['id'] ), '/schools/' . (int) $created['id'] );
	}

	/**
	 * Update a school's name and time zone. A stale `version` answers 409.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_school( $request ) {
		$school = $this->school_or_error( $request['id'] );
		if ( is_wp_error( $school ) ) {
			return $school;
		}
		$name     = null === $request['name'] ? (string) $school['name'] : $this->clean( $request['name'] );
		$timezone = null === $request['timezone'] ? (string) $school['timezone'] : $this->clean( $request['timezone'], 80 );
		$valid    = $this->validate_school( $name, $timezone );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$version = (string) $request['version'];
		try {
			$result = Transaction::run(
				function () use ( $school, $name, $timezone, $version ) {
					global $wpdb;
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Lock the row so the version check and the write are atomic.
					$current = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d FOR UPDATE', Schema::table( 'schools' ), $school['id'] ), ARRAY_A );
					if ( '' !== $version && $version !== $this->school_version( (array) $current ) ) {
						return $this->conflict( __( 'This school was changed by someone else. Reload it before saving.', 'ohmylms' ), $this->school_version( (array) $current ) );
					}
					Service::update_school(
						(int) $school['id'],
						array(
							'name'     => $name,
							'timezone' => $timezone,
						)
					);

					return true;
				}
			);
		} catch ( \Throwable $failure ) {
			return $this->service_error( $failure );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $this->school_detail( (int) $school['id'] ) );
	}

	/**
	 * Stale-save error.
	 *
	 * @param string $message Message.
	 * @param string $version Current version.
	 * @return WP_Error
	 */
	private function conflict( $message, $version ) {
		return $this->error( 'conflict', $message, 409, array( 'version' => $version ) );
	}

	/**
	 * Add an academic year to a school.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_year( $request ) {
		$school = $this->school_or_error( $request['id'] );
		if ( is_wp_error( $school ) ) {
			return $school;
		}
		$label = $this->clean( $request['label'], 100 );
		$start = (string) $request['starts_at'];
		$end   = (string) $request['ends_at'];
		foreach ( array( $start, $end ) as $date ) {
			$parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
			if ( ! $parsed || $parsed->format( 'Y-m-d' ) !== $date ) {
				return $this->error( 'invalid_dates', __( 'Use real calendar dates.', 'ohmylms' ), 422 );
			}
		}
		if ( '' === $label || $end <= $start ) {
			return $this->error( 'invalid_dates', __( 'Enter a label and an end date after the start date.', 'ohmylms' ), 422 );
		}
		try {
			Service::create_year(
				(int) $school['id'],
				array(
					'label'     => $label,
					'starts_at' => $start,
					'ends_at'   => $end,
				)
			);
		} catch ( \Throwable $failure ) {
			return $this->service_error( $failure );
		}

		return $this->created( $this->school_detail( (int) $school['id'] ), '/schools/' . (int) $school['id'] );
	}

	/**
	 * Students on a school's roster (for class membership and parent linking).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_school_students( $request ) {
		global $wpdb;
		$school = $this->school_or_error( $request['id'] );
		if ( is_wp_error( $school ) ) {
			return $school;
		}
		$from = $wpdb->prepare( "FROM %i u JOIN %i m ON m.user_id=u.ID AND m.school_id=%d AND m.role='student' AND m.status='active' WHERE u.display_name LIKE %s", $wpdb->users, Schema::table( 'school_memberships' ), $school['id'], $this->like( $request ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- $from is prepared above.
		$total  = (int) $wpdb->get_var( "SELECT COUNT(*) $from" );
		$paging = $this->paging( $request, $total );
		if ( is_wp_error( $paging ) ) {
			return $paging;
		}
		list( $page, $per_page, $pages ) = $paging;
		$rows                            = $wpdb->get_results( $wpdb->prepare( "SELECT u.ID AS id,u.display_name $from ORDER BY u.display_name, u.ID LIMIT %d OFFSET %d", $per_page, ( $page - 1 ) * $per_page ), ARRAY_A );
		// phpcs:enable

		return $this->collection( array_map( array( $this, 'prepare_person' ), (array) $rows ), $total, $pages );
	}

	/**
	 * Minimal person reference.
	 *
	 * @param array $row Row with id and display_name.
	 * @return array
	 */
	public function prepare_person( array $row ) {
		return array(
			'id'           => (int) $row['id'],
			'display_name' => (string) $row['display_name'],
		);
	}

	/*
	 * Classes.
	 */

	/**
	 * Load a class the current user may manage.
	 *
	 * @param int $id Class id.
	 * @return array|WP_Error
	 */
	private function class_or_error( $id ) {
		$class = Service::row( 'classes', absint( $id ) );
		if ( ! $class ) {
			return $this->error( 'class_not_found', __( 'Class not found.', 'ohmylms' ), 404 );
		}
		// Independent (school-less) classes belong to their teacher; only platform administrators see them here.
		$allowed = (int) $class['school_id'] ? $this->in_scope( $class['school_id'] ) : null === $this->managed_schools();

		return $allowed ? $class : $this->forbidden();
	}

	/**
	 * Stale-save version of a class.
	 *
	 * @param array $row Class row.
	 * @return string
	 */
	private function class_version( array $row ) {
		return substr( hash( 'sha256', wp_json_encode( array( (string) $row['name'], (string) $row['subject'], (string) $row['grade'], (string) $row['status'], (string) $row['academic_year_id'] ) ) ), 0, 16 );
	}

	/**
	 * Shared FROM/WHERE for class lists.
	 *
	 * @param WP_REST_Request $request   Request.
	 * @param int|null        $school_id Forced school filter.
	 * @param int             $class_id  Only this class, or 0.
	 * @return string Prepared SQL fragment.
	 */
	private function class_from( $request, $school_id = null, $class_id = 0 ) {
		global $wpdb;
		$sql = $wpdb->prepare( 'FROM %i c LEFT JOIN %i s ON s.id=c.school_id LEFT JOIN %i y ON y.id=c.academic_year_id WHERE 1=1', Schema::table( 'classes' ), Schema::table( 'schools' ), Schema::table( 'academic_years' ) );
		if ( $class_id ) {
			return $sql . $wpdb->prepare( ' AND c.id=%d', $class_id );
		}
		$sql .= $wpdb->prepare( ' AND c.name LIKE %s', $this->like( $request ) );
		if ( null === $school_id && null !== $request['school_id'] ) {
			$school_id = (int) $request['school_id'];
		}
		if ( null !== $school_id ) {
			$sql .= $wpdb->prepare( ' AND c.school_id=%d', $school_id );
		}
		if ( in_array( $request['status'], array( 'active', 'archived' ), true ) ) {
			$sql .= $wpdb->prepare( ' AND c.status=%s', $request['status'] );
		}

		return $sql . $this->scope_sql( 'c.school_id' );
	}

	/**
	 * Class rows for a prepared FROM fragment.
	 *
	 * @param string $from   Prepared FROM/WHERE.
	 * @param int    $limit  Rows.
	 * @param int    $offset Offset.
	 * @return array
	 */
	private function class_rows( $from, $limit, $offset ) {
		global $wpdb;
		$m = Schema::table( 'class_memberships' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- $from is prepared by class_from().
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.id,c.school_id,c.academic_year_id,c.name,c.subject,c.grade,c.status,s.name AS school_name,y.label AS year_label,
				(SELECT COUNT(*) FROM $m m WHERE m.class_id=c.id AND m.role='student' AND m.status='active') AS student_count,
				(SELECT COUNT(*) FROM $m m WHERE m.class_id=c.id AND m.role='teacher' AND m.status='active') AS teacher_count
				$from ORDER BY c.name, c.id LIMIT %d OFFSET %d",
				$limit,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable

		return array_map( array( $this, 'prepare_class' ), (array) $rows );
	}

	/**
	 * Shape a class row.
	 *
	 * @param array $row Database row.
	 * @return array
	 */
	public function prepare_class( array $row ) {
		$school = (int) $row['school_id'];

		return array(
			'id'                  => (int) $row['id'],
			'name'                => (string) $row['name'],
			'subject'             => (string) $row['subject'],
			'grade'               => (string) $row['grade'],
			'status'              => (string) $row['status'],
			'school_id'           => $school,
			'school_name'         => $school ? (string) $row['school_name'] : null,
			'academic_year_id'    => (int) $row['academic_year_id'],
			'academic_year_label' => $school && null !== $row['year_label'] ? (string) $row['year_label'] : null,
			'student_count'       => (int) $row['student_count'],
			'teacher_count'       => (int) $row['teacher_count'],
			'version'             => $this->class_version( $row ),
		);
	}

	/**
	 * Read one class by id (scope already checked).
	 *
	 * @param int $id Class id.
	 * @return array
	 */
	private function class_detail( $id ) {
		$rows = $this->class_rows( $this->class_from( new WP_REST_Request(), null, $id ), 1, 0 );

		return $rows[0];
	}

	/**
	 * List classes for a prepared filter.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $from    Prepared FROM/WHERE.
	 * @return WP_REST_Response|WP_Error
	 */
	private function class_collection( $request, $from ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- $from is prepared by class_from().
		$total  = (int) $wpdb->get_var( "SELECT COUNT(*) $from" );
		$paging = $this->paging( $request, $total );
		if ( is_wp_error( $paging ) ) {
			return $paging;
		}
		list( $page, $per_page, $pages ) = $paging;

		return $this->collection( $this->class_rows( $from, $per_page, ( $page - 1 ) * $per_page ), $total, $pages );
	}

	/**
	 * List classes across the schools in scope (independent classes for platform administrators).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_classes( $request ) {
		if ( null !== $request['school_id'] && (int) $request['school_id'] ) {
			$school = $this->school_or_error( $request['school_id'] );
			if ( is_wp_error( $school ) ) {
				return $school;
			}
		}

		return $this->class_collection( $request, $this->class_from( $request ) );
	}

	/**
	 * List one school's classes.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_school_classes( $request ) {
		$school = $this->school_or_error( $request['id'] );
		if ( is_wp_error( $school ) ) {
			return $school;
		}

		return $this->class_collection( $request, $this->class_from( $request, (int) $school['id'] ) );
	}

	/**
	 * Create a class in a school.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_class( $request ) {
		$school = $this->school_or_error( $request['id'] );
		if ( is_wp_error( $school ) ) {
			return $school;
		}
		$name = $this->clean( $request['name'] );
		if ( '' === $name ) {
			return $this->error( 'invalid_name', __( 'Enter a class name.', 'ohmylms' ), 422 );
		}
		$year = Service::row( 'academic_years', (int) $request['academic_year_id'] );
		if ( ! $year || (int) $year['school_id'] !== (int) $school['id'] || 'active' !== $year['status'] ) {
			return $this->error( 'invalid_year', __( 'Select an active academic year of this school.', 'ohmylms' ), 422 );
		}
		try {
			// The service runs its own transaction and re-checks the year under lock.
			$created = Service::create_class(
				(int) $school['id'],
				array(
					'name'             => $name,
					'subject'          => $this->clean( $request['subject'], 100 ),
					'grade'            => $this->clean( $request['grade'], 50 ),
					'academic_year_id' => (int) $year['id'],
				)
			);
		} catch ( \Throwable $failure ) {
			return $this->service_error( $failure );
		}

		return $this->created( $this->class_detail( (int) $created['id'] ), '/classes/' . (int) $created['id'] );
	}

	/**
	 * Read one class.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_class( $request ) {
		$class = $this->class_or_error( $request['id'] );
		if ( is_wp_error( $class ) ) {
			return $class;
		}

		return rest_ensure_response( $this->class_detail( (int) $class['id'] ) );
	}

	/**
	 * Archived-class error.
	 *
	 * @return WP_Error
	 */
	private function archived_error() {
		return $this->error( 'class_archived', __( 'This class is archived. Restore it first.', 'ohmylms' ), 409 );
	}

	/**
	 * Update a class's name, subject and grade. A stale `version` answers 409.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_class( $request ) {
		$class = $this->class_or_error( $request['id'] );
		if ( is_wp_error( $class ) ) {
			return $class;
		}
		if ( 'active' !== $class['status'] ) {
			return $this->archived_error();
		}
		$data = array(
			'name'    => null === $request['name'] ? (string) $class['name'] : $this->clean( $request['name'] ),
			'subject' => null === $request['subject'] ? (string) $class['subject'] : $this->clean( $request['subject'], 100 ),
			'grade'   => null === $request['grade'] ? (string) $class['grade'] : $this->clean( $request['grade'], 50 ),
		);
		if ( '' === $data['name'] ) {
			return $this->error( 'invalid_name', __( 'Enter a class name.', 'ohmylms' ), 422 );
		}
		$version = (string) $request['version'];
		try {
			$result = Transaction::run(
				function () use ( $class, $data, $version ) {
					global $wpdb;
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Lock the row so the version check and the write are atomic.
					$current = (array) $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d FOR UPDATE', Schema::table( 'classes' ), $class['id'] ), ARRAY_A );
					if ( '' !== $version && $version !== $this->class_version( $current ) ) {
						return $this->conflict( __( 'This class was changed by someone else. Reload it before saving.', 'ohmylms' ), $this->class_version( $current ) );
					}
					if ( 'active' !== $current['status'] ) {
						return $this->archived_error();
					}
					ClassManagement::edit( (int) $class['id'], $data );

					return true;
				}
			);
		} catch ( \Throwable $failure ) {
			return $this->service_error( $failure );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $this->class_detail( (int) $class['id'] ) );
	}

	/**
	 * Archive a class (classes are never deleted; rosters and history stay).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function archive_class( $request ) {
		$class = $this->class_or_error( $request['id'] );
		if ( is_wp_error( $class ) ) {
			return $class;
		}
		if ( 'active' !== $class['status'] ) {
			return $this->archived_error();
		}
		try {
			Service::archive_class( (int) $class['id'] );
		} catch ( \Throwable $failure ) {
			return $this->service_error( $failure );
		}

		return rest_ensure_response( $this->class_detail( (int) $class['id'] ) );
	}

	/**
	 * Restore an archived class (its academic year must still be active).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function restore_class( $request ) {
		$class = $this->class_or_error( $request['id'] );
		if ( is_wp_error( $class ) ) {
			return $class;
		}
		if ( 'archived' !== $class['status'] ) {
			return $this->error( 'class_active', __( 'This class is not archived.', 'ohmylms' ), 409 );
		}
		try {
			ClassManagement::restore( (int) $class['id'] );
		} catch ( \Throwable $failure ) {
			return $this->service_error( $failure );
		}

		return rest_ensure_response( $this->class_detail( (int) $class['id'] ) );
	}

	/*
	 * Class membership.
	 */

	/**
	 * List a class's active members.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_members( $request ) {
		global $wpdb;
		$class = $this->class_or_error( $request['id'] );
		if ( is_wp_error( $class ) ) {
			return $class;
		}
		$from = $wpdb->prepare( "FROM %i cm JOIN %i u ON u.ID=cm.user_id WHERE cm.class_id=%d AND cm.status='active' AND u.display_name LIKE %s", Schema::table( 'class_memberships' ), $wpdb->users, $class['id'], $this->like( $request ) );
		if ( in_array( $request['role'], array( 'student', 'teacher' ), true ) ) {
			$from .= $wpdb->prepare( ' AND cm.role=%s', $request['role'] );
		}
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- $from is prepared above.
		$total  = (int) $wpdb->get_var( "SELECT COUNT(*) $from" );
		$paging = $this->paging( $request, $total );
		if ( is_wp_error( $paging ) ) {
			return $paging;
		}
		list( $page, $per_page, $pages ) = $paging;
		$rows                            = $wpdb->get_results( $wpdb->prepare( "SELECT cm.id,cm.user_id,cm.role,cm.joined_at,u.display_name $from ORDER BY cm.role='teacher' DESC, u.display_name, cm.id LIMIT %d OFFSET %d", $per_page, ( $page - 1 ) * $per_page ), ARRAY_A );
		// phpcs:enable

		return $this->collection( array_map( array( $this, 'prepare_member' ), (array) $rows ), $total, $pages );
	}

	/**
	 * Shape a class membership row.
	 *
	 * @param array $row Database row.
	 * @return array
	 */
	public function prepare_member( array $row ) {
		return array(
			'id'           => (int) $row['id'],
			'user_id'      => (int) $row['user_id'],
			'display_name' => (string) $row['display_name'],
			'role'         => (string) $row['role'],
			'joined_at'    => $this->iso( $row['joined_at'] ),
		);
	}

	/**
	 * Active membership row id, or 0.
	 *
	 * @param int    $class_id Class id.
	 * @param int    $user_id  User id.
	 * @param string $role     Role.
	 * @return int
	 */
	private function active_member( $class_id, $user_id, $role ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Current roster state.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM %i WHERE class_id=%d AND user_id=%d AND role=%s AND status='active'", Schema::table( 'class_memberships' ), $class_id, $user_id, $role ) );
	}

	/**
	 * School roster people who can join a class in a role.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_candidates( $request ) {
		global $wpdb;
		$class = $this->class_or_error( $request['id'] );
		if ( is_wp_error( $class ) ) {
			return $class;
		}
		if ( ! (int) $class['school_id'] ) {
			return $this->collection( array(), 0, 0 );
		}
		$from = $wpdb->prepare(
			"FROM %i u JOIN %i sm ON sm.user_id=u.ID AND sm.school_id=%d AND sm.role=%s AND sm.status='active' WHERE u.display_name LIKE %s AND NOT EXISTS (SELECT 1 FROM %i cm WHERE cm.class_id=%d AND cm.user_id=u.ID AND cm.role=%s AND cm.status='active')",
			$wpdb->users,
			Schema::table( 'school_memberships' ),
			$class['school_id'],
			$request['role'],
			$this->like( $request ),
			Schema::table( 'class_memberships' ),
			$class['id'],
			$request['role']
		);
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- $from is prepared above.
		$total  = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT u.ID) $from" );
		$paging = $this->paging( $request, $total );
		if ( is_wp_error( $paging ) ) {
			return $paging;
		}
		list( $page, $per_page, $pages ) = $paging;
		$rows                            = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT u.ID AS id,u.display_name $from ORDER BY u.display_name, u.ID LIMIT %d OFFSET %d", $per_page, ( $page - 1 ) * $per_page ), ARRAY_A );
		// phpcs:enable

		return $this->collection( array_map( array( $this, 'prepare_person' ), (array) $rows ), $total, $pages );
	}

	/**
	 * Add a school roster student or teacher to a class.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function add_member( $request ) {
		$class = $this->class_or_error( $request['id'] );
		if ( is_wp_error( $class ) ) {
			return $class;
		}
		if ( 'active' !== $class['status'] ) {
			return $this->archived_error();
		}
		if ( ! (int) $class['school_id'] ) {
			return $this->error( 'independent_class', __( 'Students join an independent class through its teacher\'s invitation.', 'ohmylms' ), 422 );
		}
		$user = (int) $request['user_id'];
		$role = (string) $request['role'];
		// Staff can use only the school roster, never arbitrary site users.
		if ( ! Access::school( (int) $class['school_id'], array( $role ), $user ) ) {
			return $this->error( 'not_on_roster', __( 'Only people on this school\'s roster in that role can join the class.', 'ohmylms' ), 422 );
		}
		if ( $this->active_member( (int) $class['id'], $user, $role ) ) {
			return $this->error( 'already_member', __( 'This person is already in the class.', 'ohmylms' ), 409 );
		}
		try {
			Service::class_member(
				(int) $class['id'],
				array(
					'user_id' => $user,
					'role'    => $role,
				)
			);
		} catch ( \Throwable $failure ) {
			return $this->service_error( $failure );
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Read back the saved membership.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT cm.id,cm.user_id,cm.role,cm.joined_at,u.display_name FROM %i cm JOIN %i u ON u.ID=cm.user_id WHERE cm.class_id=%d AND cm.user_id=%d AND cm.role=%s AND cm.status='active'", Schema::table( 'class_memberships' ), $wpdb->users, $class['id'], $user, $role ), ARRAY_A );

		return $this->created( $this->prepare_member( (array) $row ), '/classes/' . (int) $class['id'] . '/members' );
	}

	/**
	 * Remove a student or teacher from a class (the membership becomes inactive).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function remove_member( $request ) {
		$class = $this->class_or_error( $request['id'] );
		if ( is_wp_error( $class ) ) {
			return $class;
		}
		$user = (int) $request['user_id'];
		$role = (string) $request['role'];
		if ( ! $this->active_member( (int) $class['id'], $user, $role ) ) {
			return $this->error( 'member_not_found', __( 'This person is not in the class.', 'ohmylms' ), 404 );
		}
		if ( 'active' !== $class['status'] ) {
			return $this->archived_error();
		}
		try {
			Service::class_member(
				(int) $class['id'],
				array(
					'user_id' => $user,
					'role'    => $role,
					'status'  => 'inactive',
				)
			);
		} catch ( \Throwable $failure ) {
			return $this->service_error( $failure );
		}

		return rest_ensure_response(
			array(
				'removed' => true,
				'user_id' => $user,
				'role'    => $role,
			)
		);
	}

	/*
	 * Teachers and parents.
	 */

	/**
	 * WHERE clause selecting the people of a kind visible to the current user.
	 *
	 * @param string   $kind      `teacher` or `parent`.
	 * @param int|null $school_id Optional school filter.
	 * @return string Prepared SQL fragment on `u`.
	 */
	private function people_where( $kind, $school_id = null ) {
		global $wpdb;
		$school = $school_id ? $wpdb->prepare( ' AND x.school_id=%d', $school_id ) : '';
		if ( 'teacher' === $kind ) {
			$relation = $wpdb->prepare( "EXISTS (SELECT 1 FROM %i x JOIN %i xs ON xs.id=x.school_id AND xs.status='active' WHERE x.user_id=u.ID AND x.role='teacher' AND x.status='active'", Schema::table( 'school_memberships' ), Schema::table( 'schools' ) );
		} else {
			$relation = $wpdb->prepare( "EXISTS (SELECT 1 FROM %i x WHERE x.guardian_user_id=u.ID AND x.status='active'", Schema::table( 'guardian_links' ) );
		}
		$relation .= $school . $this->scope_sql( 'x.school_id' ) . ')';
		if ( null !== $this->managed_schools() || $school_id ) {
			return $relation;
		}
		$role = $wpdb->prepare( 'EXISTS (SELECT 1 FROM %i um WHERE um.user_id=u.ID AND um.meta_key=%s AND um.meta_value LIKE %s)', $wpdb->usermeta, $wpdb->get_blog_prefix() . 'capabilities', '%' . $wpdb->esc_like( '"ohmylms_' . $kind . '"' ) . '%' );

		return "($role OR $relation)";
	}

	/**
	 * Paged list of teachers or parents.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $kind    `teacher` or `parent`.
	 * @return WP_REST_Response|WP_Error
	 */
	private function people( $request, $kind ) {
		global $wpdb;
		$school_id = null === $request['school_id'] ? null : (int) $request['school_id'];
		if ( $school_id ) {
			$school = $this->school_or_error( $school_id );
			if ( is_wp_error( $school ) ) {
				return $school;
			}
		}
		$like   = $this->like( $request );
		$search = $this->can_see_email() ? $wpdb->prepare( '(u.display_name LIKE %s OR u.user_email LIKE %s)', $like, $like ) : $wpdb->prepare( 'u.display_name LIKE %s', $like );
		$from   = $wpdb->prepare( 'FROM %i u WHERE ', $wpdb->users ) . $this->people_where( $kind, $school_id ) . ' AND ' . $search;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- $from is assembled from prepared fragments.
		$total  = (int) $wpdb->get_var( "SELECT COUNT(*) $from" );
		$paging = $this->paging( $request, $total );
		if ( is_wp_error( $paging ) ) {
			return $paging;
		}
		list( $page, $per_page, $pages ) = $paging;
		$rows                            = (array) $wpdb->get_results( $wpdb->prepare( "SELECT u.ID,u.display_name,u.user_email $from ORDER BY u.display_name, u.ID LIMIT %d OFFSET %d", $per_page, ( $page - 1 ) * $per_page ), ARRAY_A );
		$ids                             = array_map( 'intval', wp_list_pluck( $rows, 'ID' ) );
		$in                              = $ids ? implode( ',', $ids ) : '0';
		$schools                         = array();
		$counts                          = array();
		if ( 'teacher' === $kind ) {
			$scope       = $this->scope_sql( 's.id' );
			$memberships = $wpdb->get_results( $wpdb->prepare( "SELECT m.user_id,s.id,s.name FROM %i m JOIN %i s ON s.id=m.school_id AND s.status='active' WHERE m.role='teacher' AND m.status='active' AND m.user_id IN ($in){$scope} ORDER BY s.name, s.id", Schema::table( 'school_memberships' ), Schema::table( 'schools' ) ), ARRAY_A );
			foreach ( (array) $memberships as $membership ) {
				$schools[ (int) $membership['user_id'] ][] = array(
					'id'   => (int) $membership['id'],
					'name' => (string) $membership['name'],
				);
			}
			$scope   = $this->scope_sql( 'c.school_id' );
			$counted = $wpdb->get_results( $wpdb->prepare( "SELECT cm.user_id,COUNT(*) AS total FROM %i cm JOIN %i c ON c.id=cm.class_id AND c.status='active' WHERE cm.role='teacher' AND cm.status='active' AND cm.user_id IN ($in){$scope} GROUP BY cm.user_id", Schema::table( 'class_memberships' ), Schema::table( 'classes' ) ), ARRAY_A );
		} else {
			$scope   = $this->scope_sql( 'g.school_id' );
			$counted = $wpdb->get_results( $wpdb->prepare( "SELECT g.guardian_user_id AS user_id,COUNT(*) AS total FROM %i g WHERE g.status='active' AND g.guardian_user_id IN ($in){$scope} GROUP BY g.guardian_user_id", Schema::table( 'guardian_links' ) ), ARRAY_A );
		}
		// phpcs:enable
		foreach ( (array) $counted as $count ) {
			$counts[ (int) $count['user_id'] ] = (int) $count['total'];
		}
		$email = $this->can_see_email();
		$data  = array();
		foreach ( $rows as $row ) {
			$id   = (int) $row['ID'];
			$item = array(
				'id'           => $id,
				'display_name' => (string) $row['display_name'],
				'email'        => $email ? (string) $row['user_email'] : null,
			);
			if ( 'teacher' === $kind ) {
				$item['schools']     = $schools[ $id ] ?? array();
				$item['class_count'] = $counts[ $id ] ?? 0;
			} else {
				$item['child_count'] = $counts[ $id ] ?? 0;
			}
			$data[] = $item;
		}

		return $this->collection( $data, $total, $pages );
	}

	/**
	 * List teachers.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_teachers( $request ) {
		return $this->people( $request, 'teacher' );
	}

	/**
	 * List parents.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_parents( $request ) {
		return $this->people( $request, 'parent' );
	}

	/**
	 * Load a parent visible to the current user.
	 *
	 * @param int $id User id.
	 * @return \WP_User|WP_Error
	 */
	private function parent_or_error( $id ) {
		global $wpdb;
		$id = absint( $id );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Assembled from prepared fragments.
		$found = $id && $wpdb->get_var( $wpdb->prepare( 'SELECT u.ID FROM %i u WHERE u.ID=%d AND ', $wpdb->users, $id ) . $this->people_where( 'parent' ) );
		$user  = $found ? get_userdata( $id ) : false;

		return $user ? $user : $this->error( 'parent_not_found', __( 'Parent not found.', 'ohmylms' ), 404 );
	}

	/**
	 * Children linked to a parent and pending guardian invitations, within scope.
	 *
	 * @param \WP_User $guardian Parent.
	 * @return array
	 */
	private function children_of( $guardian ) {
		global $wpdb;
		$link_scope   = $this->scope_sql( 'g.school_id' );
		$invite_scope = $this->scope_sql( 'i.school_id' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Integer scope is trusted; values are prepared.
		$links   = $wpdb->get_results( $wpdb->prepare( "SELECT g.id,g.school_id,s.name AS school_name,g.student_user_id,u.display_name,g.approved_at FROM %i g JOIN %i s ON s.id=g.school_id JOIN %i u ON u.ID=g.student_user_id WHERE g.guardian_user_id=%d AND g.status='active'{$link_scope} ORDER BY u.display_name, g.id LIMIT %d", Schema::table( 'guardian_links' ), Schema::table( 'schools' ), $wpdb->users, $guardian->ID, self::MAX_SUBLIST ), ARRAY_A );
		$pending = $wpdb->get_results( $wpdb->prepare( "SELECT i.id,i.school_id,s.name AS school_name,i.student_user_id,u.display_name,i.expires_at FROM %i i JOIN %i s ON s.id=i.school_id LEFT JOIN %i u ON u.ID=i.student_user_id WHERE i.role='guardian' AND i.email=%s AND i.consumed_at IS NULL AND i.revoked_at IS NULL AND i.expires_at>%s{$invite_scope} ORDER BY i.id DESC LIMIT %d", Schema::table( 'school_invitations' ), Schema::table( 'schools' ), $wpdb->users, $guardian->user_email, Service::now(), self::MAX_SUBLIST ), ARRAY_A );
		// phpcs:enable

		return array(
			'children' => array_map(
				function ( $row ) {
					return array(
						'id'           => (int) $row['id'],
						'school_id'    => (int) $row['school_id'],
						'school_name'  => (string) $row['school_name'],
						'student_id'   => (int) $row['student_user_id'],
						'student_name' => (string) $row['display_name'],
						'linked_at'    => $this->iso( $row['approved_at'] ),
					);
				},
				(array) $links
			),
			'pending'  => array_map(
				function ( $row ) {
					return array(
						'id'           => (int) $row['id'],
						'school_id'    => (int) $row['school_id'],
						'school_name'  => (string) $row['school_name'],
						'student_id'   => (int) $row['student_user_id'],
						'student_name' => (string) $row['display_name'],
						'expires_at'   => $this->iso( $row['expires_at'] ),
					);
				},
				(array) $pending
			),
		);
	}

	/**
	 * Read a parent's linked children and pending invitations.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_children( $request ) {
		$parent = $this->parent_or_error( $request['id'] );
		if ( is_wp_error( $parent ) ) {
			return $parent;
		}

		return rest_ensure_response( $this->children_of( $parent ) );
	}

	/**
	 * Start a parent-child link: a single-use guardian invitation addressed to the parent's own
	 * account email. The link becomes active only when the parent signs in and accepts it, so a
	 * guardian link is never made from a name search alone (docs/SCHOOL-FAMILY-SETUP.md).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function link_child( $request ) {
		global $wpdb;
		$parent = $this->parent_or_error( $request['id'] );
		if ( is_wp_error( $parent ) ) {
			return $parent;
		}
		$school = $this->school_or_error( $request['school_id'] );
		if ( is_wp_error( $school ) ) {
			return $school;
		}
		$student = (int) $request['student_id'];
		if ( $student === (int) $parent->ID || ! Access::student( (int) $school['id'], $student ) ) {
			return $this->error( 'not_on_roster', __( 'Choose a student on this school\'s roster.', 'ohmylms' ), 422 );
		}
		if ( ! is_email( $parent->user_email ) ) {
			return $this->error( 'parent_without_email', __( 'This parent account has no email address to send the invitation to.', 'ohmylms' ), 422 );
		}
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Current link and invitation state.
		$linked = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM %i WHERE school_id=%d AND guardian_user_id=%d AND student_user_id=%d AND status='active'", Schema::table( 'guardian_links' ), $school['id'], $parent->ID, $student ) );
		if ( $linked ) {
			return $this->error( 'already_linked', __( 'This parent is already linked to the student.', 'ohmylms' ), 409 );
		}
		$pending = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM %i WHERE school_id=%d AND student_user_id=%d AND role='guardian' AND email=%s AND consumed_at IS NULL AND revoked_at IS NULL AND expires_at>%s", Schema::table( 'school_invitations' ), $school['id'], $student, $parent->user_email, Service::now() ) );
		// phpcs:enable
		if ( $pending ) {
			return $this->error( 'invitation_pending', __( 'An invitation for this parent and student is already waiting. Revoke it to create a new link.', 'ohmylms' ), 409, array( 'invitation' => (int) $pending ) );
		}
		try {
			$invite = Service::invite(
				(int) $school['id'],
				array(
					'role'            => 'guardian',
					'email'           => $parent->user_email,
					'student_user_id' => $student,
					'send_email'      => (bool) $request['send_email'],
				)
			);
		} catch ( \Throwable $failure ) {
			return $this->service_error( $failure );
		}
		$row = Service::row( 'school_invitations', (int) $invite['id'] );

		return $this->created(
			array(
				'id'         => (int) $invite['id'],
				'school_id'  => (int) $school['id'],
				'student_id' => $student,
				'url'        => (string) $invite['url'],
				'expires_at' => $this->iso( $row['expires_at'] ?? '' ),
				'email_sent' => (bool) $invite['email_sent'],
			),
			'/parents/' . (int) $parent->ID . '/children'
		);
	}

	/**
	 * Revoke an active parent-child link (it becomes inactive; nothing is deleted).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function unlink_child( $request ) {
		$parent = $this->parent_or_error( $request['id'] );
		if ( is_wp_error( $parent ) ) {
			return $parent;
		}
		$link = Service::row( 'guardian_links', (int) $request['link'] );
		if ( ! $link || (int) $link['guardian_user_id'] !== (int) $parent->ID || 'active' !== $link['status'] || ! $this->in_scope( $link['school_id'] ) ) {
			return $this->error( 'link_not_found', __( 'Link not found.', 'ohmylms' ), 404 );
		}
		try {
			Service::revoke_guardian( (int) $link['school_id'], (int) $link['id'] );
		} catch ( \Throwable $failure ) {
			return $this->service_error( $failure );
		}

		return rest_ensure_response(
			array(
				'revoked' => true,
				'id'      => (int) $link['id'],
			)
		);
	}

	/**
	 * Revoke a pending guardian invitation addressed to this parent.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function revoke_invitation( $request ) {
		$parent = $this->parent_or_error( $request['id'] );
		if ( is_wp_error( $parent ) ) {
			return $parent;
		}
		$invite = Service::row( 'school_invitations', (int) $request['invitation'] );
		$valid  = $invite && 'guardian' === $invite['role'] && 0 === strcasecmp( (string) $invite['email'], (string) $parent->user_email )
			&& ! $invite['consumed_at'] && ! $invite['revoked_at'] && $invite['expires_at'] > Service::now() && $this->in_scope( $invite['school_id'] );
		if ( ! $valid ) {
			return $this->error( 'invitation_not_found', __( 'Invitation not found.', 'ohmylms' ), 404 );
		}
		try {
			Service::revoke_invite( (int) $invite['school_id'], (int) $invite['id'] );
		} catch ( \Throwable $failure ) {
			return $this->service_error( $failure );
		}

		return rest_ensure_response(
			array(
				'revoked' => true,
				'id'      => (int) $invite['id'],
			)
		);
	}

	/**
	 * School schema (JSON Schema draft 4).
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}
		$integer = static function ( $description ) {
			return array(
				'description' => $description,
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			);
		};

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'account-hub-school',
			'type'       => 'object',
			'properties' => array(
				'id'            => $integer( __( 'Unique identifier for the school.', 'ohmylms' ) ),
				'name'          => array(
					'description' => __( 'School name.', 'ohmylms' ),
					'type'        => 'string',
					'maxLength'   => 190,
					'context'     => array( 'view', 'edit' ),
				),
				'slug'          => array(
					'description' => __( 'Stable slug, set when the school is created.', 'ohmylms' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'timezone'      => array(
					'description' => __( 'IANA time zone used for due dates.', 'ohmylms' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'status'        => array(
					'description' => __( 'School status.', 'ohmylms' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'created_at'    => array(
					'description' => __( 'Creation time (UTC).', 'ohmylms' ),
					'type'        => array( 'string', 'null' ),
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'class_count'   => $integer( __( 'Active classes.', 'ohmylms' ) ),
				'student_count' => $integer( __( 'Students on the roster.', 'ohmylms' ) ),
				'teacher_count' => $integer( __( 'Teachers on the roster.', 'ohmylms' ) ),
				'version'       => array(
					'description' => __( 'Opaque version; send it back on update to detect concurrent changes.', 'ohmylms' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'years'         => array(
					'description' => __( 'Academic years (single school only).', 'ohmylms' ),
					'type'        => 'array',
					'context'     => array( 'edit' ),
					'readonly'    => true,
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}
}
