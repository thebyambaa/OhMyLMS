<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * AccountHubUsersController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Schools\Service;
use OhMyLMS\Utility\Transaction;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_User;
use WP_User_Query;

defined( 'ABSPATH' ) || exit;

/**
 * The Account Hub "All accounts" directory under `ohmylms/v1/account-hub/users`: a paged list of the
 * site's accounts, creating student, teacher and parent accounts, and editing an account's name and
 * email. See docs/api/account-hub.md.
 *
 * Permissions follow WordPress: listing needs `list_users`, creating `create_users`, editing
 * `edit_user` for that account. On top of core, no request can reach beyond the caller's own
 * privileges: an account type is offered only when its role is editable and holds no capability the
 * caller lacks, and an account is editable only when it is not the caller's own and holds no
 * capability the caller lacks (so a user manager can never take over an administrator's email).
 *
 * @since 1.0.0
 */
class AccountHubUsersController extends AccountHubController {

	/**
	 * Account types this directory creates, in display order.
	 */
	const TYPES = array( 'student', 'teacher', 'parent' );

	/**
	 * Registration period filters.
	 */
	const PERIODS = array( 'all', 'last_30_days', 'current_month', 'previous_month', 'current_year', 'last_12_months' );

	/**
	 * User meta that marks a blocked account (`yes`).
	 */
	const BLOCKED_META = '_ohmylms_banned_student';

	/**
	 * User meta with the last sign-in (site-local MySQL time).
	 */
	const LAST_LOGIN_META = '_ohmylms_last_login';

	/**
	 * Register the routes.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		$base = '/' . $this->rest_base . '/users';
		$name = array(
			'type'      => 'string',
			'maxLength' => 190,
		);

		register_rest_route(
			$this->namespace,
			$base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_users' ),
					'permission_callback' => array( $this, 'list_permissions_check' ),
					'args'                => $this->collection_args(
						array(
							'role'       => array(
								'description'       => __( 'Only accounts with this role (slug).', 'ohmylms' ),
								'type'              => 'string',
								'default'           => '',
								'maxLength'         => 100,
								'validate_callback' => array( $this, 'validate_role_filter' ),
							),
							'registered' => array(
								'description' => __( 'Only accounts registered in this period (site time zone).', 'ohmylms' ),
								'type'        => 'string',
								'default'     => 'all',
								'enum'        => self::PERIODS,
							),
							'status'     => array(
								'description' => __( 'Only active or only blocked accounts.', 'ohmylms' ),
								'type'        => 'string',
								'default'     => 'all',
								'enum'        => array( 'all', 'active', 'blocked' ),
							),
						)
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_user' ),
					'permission_callback' => array( $this, 'create_permissions_check' ),
					'args'                => array(
						'type'       => array(
							'description' => __( 'Account type.', 'ohmylms' ),
							'type'        => 'string',
							'enum'        => self::TYPES,
							'required'    => true,
						),
						'first_name' => array_merge(
							$name,
							array(
								'minLength' => 1,
								'required'  => true,
							)
						),
						'last_name'  => array_merge( $name, array( 'default' => '' ) ),
						'email'      => array(
							'type'      => 'string',
							'format'    => 'email',
							'maxLength' => 100,
							'required'  => true,
						),
						'username'   => array(
							'description' => __( 'Sign-in name; derived from the email when empty.', 'ohmylms' ),
							'type'        => 'string',
							'default'     => '',
							'maxLength'   => 60,
						),
						'send_email' => array(
							'description' => __( 'Email the person a link to set their password.', 'ohmylms' ),
							'type'        => 'boolean',
							'default'     => true,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/options',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_options' ),
					'permission_callback' => array( $this, 'list_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/(?P<id>[\d]+)',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier.', 'ohmylms' ),
						'type'        => 'integer',
						'minimum'     => 1,
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_user' ),
					'permission_callback' => array( $this, 'edit_users_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_user' ),
					'permission_callback' => array( $this, 'edit_users_permissions_check' ),
					'args'                => array(
						'first_name' => array_merge( $name, array( 'minLength' => 1 ) ),
						'last_name'  => $name,
						'email'      => array(
							'type'      => 'string',
							'format'    => 'email',
							'maxLength' => 100,
						),
						'send_reset' => array(
							'description' => __( 'Email the person a password reset link after saving.', 'ohmylms' ),
							'type'        => 'boolean',
							'default'     => false,
						),
						'version'    => array(
							'description' => __( 'The version the editor loaded; a different stored version answers 409.', 'ohmylms' ),
							'type'        => 'string',
							'pattern'     => '^[a-f0-9]{16}$',
						),
					),
				),
			)
		);
	}

	/*
	 * Permissions.
	 */

	/**
	 * Require a core capability: 401 anonymous, 403 otherwise.
	 *
	 * @param string $capability Capability.
	 * @param string $code       Code suffix.
	 * @param string $message    Message.
	 * @return true|WP_Error
	 */
	private function require_capability( $capability, $code, $message ) {
		if ( is_user_logged_in() && current_user_can( $capability ) ) {
			return true;
		}

		return new WP_Error( 'ohmylms_account_hub_' . $code, $message, array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Listing accounts needs `list_users`.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function list_permissions_check( $request ) {
		return $this->require_capability( 'list_users', 'cannot_list_users', __( 'Sorry, you are not allowed to list accounts.', 'ohmylms' ) );
	}

	/**
	 * Creating accounts needs `create_users`.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function create_permissions_check( $request ) {
		return $this->require_capability( 'create_users', 'cannot_create_users', __( 'Sorry, you are not allowed to create accounts.', 'ohmylms' ) );
	}

	/**
	 * Reading or editing one account needs `edit_users`; the account itself is checked in the callback.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function edit_users_permissions_check( $request ) {
		return $this->require_capability( 'edit_users', 'cannot_edit_users', __( 'Sorry, you are not allowed to edit accounts.', 'ohmylms' ) );
	}

	/**
	 * Whether every granted capability (role names aside) is also held by the current user.
	 *
	 * @param array $caps Capability => grant.
	 * @return bool
	 */
	private function within_own_capabilities( array $caps ) {
		if ( is_multisite() && is_super_admin() ) {
			return true;
		}
		$mine  = (array) wp_get_current_user()->allcaps;
		$roles = wp_roles();
		foreach ( $caps as $cap => $grant ) {
			if ( $grant && ! $roles->is_role( $cap ) && empty( $mine[ $cap ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether the current user may edit an account here.
	 *
	 * @param WP_User $user Account.
	 * @return bool
	 */
	protected function can_manage( WP_User $user ) {
		if ( get_current_user_id() === (int) $user->ID || ! current_user_can( 'edit_user', $user->ID ) ) {
			return false;
		}
		if ( is_multisite() && is_super_admin( $user->ID ) && ! is_super_admin() ) {
			return false;
		}

		return $this->within_own_capabilities( (array) $user->allcaps );
	}

	/**
	 * Account types the current user may create, mapped to their WordPress role.
	 *
	 * @return array<string,string> Type => role slug.
	 */
	protected function assignable_types() {
		if ( ! current_user_can( 'create_users' ) ) {
			return array();
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$editable = get_editable_roles();
		$roles    = array(
			'student' => function_exists( 'ohmylms_get_assignable_student_role' ) ? ohmylms_get_assignable_student_role() : 'subscriber',
			'teacher' => 'ohmylms_teacher',
			'parent'  => 'ohmylms_parent',
		);
		$types    = array();
		foreach ( $roles as $type => $slug ) {
			$role = get_role( $slug );
			if ( $role && 'administrator' !== $slug && isset( $editable[ $slug ] ) && $this->within_own_capabilities( (array) $role->capabilities ) ) {
				$types[ $type ] = $slug;
			}
		}

		return $types;
	}

	/**
	 * Validate the role filter against the site's roles.
	 *
	 * @param mixed $value Value.
	 * @return true|WP_Error
	 */
	public function validate_role_filter( $value ) {
		if ( ! is_string( $value ) ) {
			return new WP_Error( 'rest_invalid_param', __( 'role must be a role slug.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		if ( '' === $value || wp_roles()->is_role( $value ) ) {
			return true;
		}

		return new WP_Error( 'rest_invalid_param', __( 'Unknown role.', 'ohmylms' ), array( 'status' => 400 ) );
	}

	/*
	 * Shapes.
	 */

	/**
	 * Translated role labels of an account.
	 *
	 * @param WP_User $user Account.
	 * @return array[] `{ slug, label }`.
	 */
	private function roles_of( WP_User $user ) {
		$names = wp_roles()->get_names();
		$roles = array();
		foreach ( (array) $user->roles as $slug ) {
			$roles[] = array(
				'slug'  => (string) $slug,
				'label' => isset( $names[ $slug ] ) ? translate_user_role( $names[ $slug ] ) : (string) $slug,
			);
		}

		return $roles;
	}

	/**
	 * Last sign-in as UTC ISO 8601, or null.
	 *
	 * @param int $user_id User id.
	 * @return string|null
	 */
	private function last_login( $user_id ) {
		$value = get_user_meta( $user_id, self::LAST_LOGIN_META, true );
		if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) ) {
			return null;
		}

		return $this->iso( get_gmt_from_date( $value ) );
	}

	/**
	 * One listed account. No avatar, edit URL or other profile data.
	 *
	 * @param WP_User $user Account.
	 * @return array
	 */
	public function prepare_account( WP_User $user ) {
		return array(
			'id'            => (int) $user->ID,
			'display_name'  => (string) $user->display_name,
			'username'      => (string) $user->user_login,
			'email'         => (string) $user->user_email,
			'roles'         => $this->roles_of( $user ),
			'registered_at' => $this->iso( $user->user_registered ),
			'last_login'    => $this->last_login( (int) $user->ID ),
			'blocked'       => 'yes' === get_user_meta( $user->ID, self::BLOCKED_META, true ),
			'can_edit'      => $this->can_manage( $user ),
		);
	}

	/**
	 * Stale-save version of an account's editable fields.
	 *
	 * @param string $email   Email.
	 * @param string $display Display name.
	 * @param string $first   First name.
	 * @param string $last    Last name.
	 * @return string
	 */
	private function account_version( $email, $display, $first, $last ) {
		return substr( hash( 'sha256', (string) wp_json_encode( array( (string) $email, (string) $display, (string) $first, (string) $last ) ) ), 0, 16 );
	}

	/**
	 * The editor's view of an account.
	 *
	 * @param WP_User $user Account.
	 * @return array
	 */
	private function prepare_editable( WP_User $user ) {
		$first = (string) get_user_meta( $user->ID, 'first_name', true );
		$last  = (string) get_user_meta( $user->ID, 'last_name', true );

		return array(
			'id'           => (int) $user->ID,
			'username'     => (string) $user->user_login,
			'display_name' => (string) $user->display_name,
			'first_name'   => $first,
			'last_name'    => $last,
			'email'        => (string) $user->user_email,
			'roles'        => $this->roles_of( $user ),
			'version'      => $this->account_version( $user->user_email, $user->display_name, $first, $last ),
		);
	}

	/**
	 * Private, uncached response.
	 *
	 * @param mixed $data   Body.
	 * @param int   $status Status.
	 * @return WP_REST_Response
	 */
	private function private_response( $data, $status = 200 ) {
		$response = rest_ensure_response( $data );
		$response->set_status( $status );
		$response->header( 'Cache-Control', 'private, no-store' );

		return $response;
	}

	/*
	 * Callbacks.
	 */

	/**
	 * Registration bounds of a period, as UTC MySQL times.
	 *
	 * @param string $period Period id.
	 * @return array{0:string,1:string}|null Inclusive start and exclusive end, or null for all.
	 */
	private function period_bounds( $period ) {
		if ( ! in_array( $period, self::PERIODS, true ) || 'all' === $period ) {
			return null;
		}
		$now   = new \DateTimeImmutable( 'now', wp_timezone() );
		$month = $now->modify( 'first day of this month' )->setTime( 0, 0 );
		$end   = $now->modify( '+1 second' );
		switch ( $period ) {
			case 'last_30_days':
				$start = $now->modify( '-30 days' );
				break;
			case 'current_month':
				$start = $month;
				break;
			case 'previous_month':
				$start = $month->modify( '-1 month' );
				$end   = $month;
				break;
			case 'current_year':
				$start = $now->setDate( (int) $now->format( 'Y' ), 1, 1 )->setTime( 0, 0 );
				break;
			default:
				$start = $now->modify( '-12 months' );
		}
		$utc = new \DateTimeZone( 'UTC' );

		return array( $start->setTimezone( $utc )->format( 'Y-m-d H:i:s' ), $end->setTimezone( $utc )->format( 'Y-m-d H:i:s' ) );
	}

	/**
	 * List the site's accounts by name.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_users( $request ) {
		$page     = max( 1, (int) $request['page'] );
		$per_page = min( 100, max( 1, (int) $request['per_page'] ) );
		$search   = $this->clean( $request['search'], 100 );
		$args     = array(
			'number'      => $per_page,
			'paged'       => $page,
			'orderby'     => 'display_name',
			'order'       => 'ASC',
			'count_total' => true,
		);
		if ( '' !== $search ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}
		if ( '' !== (string) $request['role'] ) {
			$args['role'] = (string) $request['role'];
		}
		$bounds = $this->period_bounds( (string) $request['registered'] );
		if ( $bounds ) {
			$args['date_query'] = array(
				array(
					'column'    => 'user_registered',
					'after'     => $bounds[0],
					'before'    => $bounds[1],
					'inclusive' => true,
				),
			);
		}
		if ( 'blocked' === $request['status'] ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded, paged admin filter on one key.
				array(
					'key'   => self::BLOCKED_META,
					'value' => 'yes',
				),
			);
		} elseif ( 'active' === $request['status'] ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded, paged admin filter on one key.
				'relation' => 'OR',
				array(
					'key'     => self::BLOCKED_META,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => self::BLOCKED_META,
					'value'   => 'yes',
					'compare' => '!=',
				),
			);
		}
		$query  = new WP_User_Query( $args );
		$total  = (int) $query->get_total();
		$paging = $this->paging( $request, $total );
		if ( is_wp_error( $paging ) ) {
			return $paging;
		}
		$response = $this->collection( array_map( array( $this, 'prepare_account' ), (array) $query->get_results() ), $total, $paging[2] );
		$response->header( 'Cache-Control', 'private, no-store' );

		return $response;
	}

	/**
	 * Roles for the filter and the account types the current user may create.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_options( $request ) {
		$labels = array(
			'student' => __( 'Student', 'ohmylms' ),
			'teacher' => __( 'Teacher', 'ohmylms' ),
			'parent'  => __( 'Parent', 'ohmylms' ),
		);
		$roles  = array();
		foreach ( wp_roles()->get_names() as $slug => $name ) {
			$roles[] = array(
				'slug'  => (string) $slug,
				'label' => translate_user_role( $name ),
			);
		}
		$types = array();
		foreach ( array_keys( $this->assignable_types() ) as $type ) {
			$types[] = array(
				'value' => $type,
				'label' => $labels[ $type ],
			);
		}

		return rest_ensure_response(
			array(
				'roles'      => $roles,
				'types'      => $types,
				'can_create' => (bool) $types,
			)
		);
	}

	/**
	 * Load an account the current user may edit.
	 *
	 * @param int $id User id.
	 * @return WP_User|WP_Error
	 */
	private function account_or_error( $id ) {
		$user = get_userdata( absint( $id ) );
		if ( ! $user || ( is_multisite() && ! is_user_member_of_blog( $user->ID ) ) ) {
			return $this->error( 'user_not_found', __( 'Account not found.', 'ohmylms' ), 404 );
		}
		if ( get_current_user_id() === (int) $user->ID ) {
			return $this->error( 'own_account', __( 'Change your own name and email on your profile page.', 'ohmylms' ), 403 );
		}

		return $this->can_manage( $user ) ? $user : $this->error( 'cannot_manage_user', __( 'Sorry, you cannot edit an account with more privileges than your own.', 'ohmylms' ), 403 );
	}

	/**
	 * Read one account for editing.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_user( $request ) {
		$user = $this->account_or_error( $request['id'] );
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		return $this->private_response( $this->prepare_editable( $user ) );
	}

	/**
	 * Create a student, teacher or parent account. The person sets their own password.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_user( $request ) {
		$types = $this->assignable_types();
		$type  = (string) $request['type'];
		if ( ! isset( $types[ $type ] ) ) {
			return $this->error( 'type_not_allowed', __( 'Sorry, you cannot create this type of account.', 'ohmylms' ), 403 );
		}
		$first = $this->clean( $request['first_name'] );
		$last  = $this->clean( $request['last_name'] );
		$email = sanitize_email( (string) $request['email'] );
		if ( '' === $first ) {
			return $this->error( 'invalid_name', __( 'Enter a first name.', 'ohmylms' ), 422, array( 'field' => 'first_name' ) );
		}
		if ( ! is_email( $email ) ) {
			return $this->error( 'invalid_email', __( 'Enter a valid email address.', 'ohmylms' ), 422, array( 'field' => 'email' ) );
		}
		if ( email_exists( $email ) ) {
			return $this->error( 'email_exists', __( 'An account with this email already exists.', 'ohmylms' ), 409, array( 'field' => 'email' ) );
		}
		$requested = trim( (string) $request['username'] );
		if ( '' !== $requested ) {
			$login = sanitize_user( $requested, true );
			if ( $login !== $requested || ! validate_username( $login ) ) {
				return $this->error( 'invalid_username', __( 'Use only letters, numbers, spaces and . - _ @ in the username.', 'ohmylms' ), 422, array( 'field' => 'username' ) );
			}
			if ( username_exists( $login ) ) {
				return $this->error( 'username_exists', __( 'This username is already taken.', 'ohmylms' ), 409, array( 'field' => 'username' ) );
			}
		} else {
			$base  = sanitize_user( (string) strstr( $email, '@', true ), true );
			$base  = '' !== $base ? mb_substr( $base, 0, 50 ) : 'user';
			$login = $base;
			for ( $i = 2; username_exists( $login ) && $i < 1000; $i++ ) {
				$login = $base . $i;
			}
		}
		$id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 24 ),
				'first_name'   => $first,
				'last_name'    => $last,
				'display_name' => trim( $first . ' ' . $last ),
				'role'         => $types[ $type ],
			)
		);
		if ( is_wp_error( $id ) ) {
			return $this->error( 'rejected', $id->get_error_message(), 422 );
		}
		if ( $request['send_email'] ) {
			wp_new_user_notification( $id, null, 'user' );
		}
		try {
			Service::audit( 0, 'account_created_by_admin', $id );
		} catch ( \Throwable $failure ) {
			unset( $failure );
		}
		$response = $this->private_response( $this->prepare_account( get_userdata( $id ) ), 201 );
		$response->header( 'Location', rest_url( $this->namespace . '/' . $this->rest_base . '/users/' . $id ) );

		return $response;
	}

	/**
	 * Update an account's first name, last name and email. A stale `version` answers 409.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_user( $request ) {
		$user = $this->account_or_error( $request['id'] );
		if ( is_wp_error( $user ) ) {
			return $user;
		}
		$first = null === $request['first_name'] ? (string) get_user_meta( $user->ID, 'first_name', true ) : $this->clean( $request['first_name'] );
		$last  = null === $request['last_name'] ? (string) get_user_meta( $user->ID, 'last_name', true ) : $this->clean( $request['last_name'] );
		$email = null === $request['email'] ? (string) $user->user_email : sanitize_email( (string) $request['email'] );
		if ( '' === $first ) {
			return $this->error( 'invalid_name', __( 'Enter a first name.', 'ohmylms' ), 422, array( 'field' => 'first_name' ) );
		}
		if ( ! is_email( $email ) ) {
			return $this->error( 'invalid_email', __( 'Enter a valid email address.', 'ohmylms' ), 422, array( 'field' => 'email' ) );
		}
		$other = email_exists( $email );
		if ( $other && (int) $other !== (int) $user->ID ) {
			return $this->error( 'email_exists', __( 'Another account already uses this email.', 'ohmylms' ), 409, array( 'field' => 'email' ) );
		}
		$version = (string) $request['version'];
		try {
			$result = Transaction::run(
				function () use ( $user, $first, $last, $email, $version ) {
					global $wpdb;
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Lock the account row so the version check and the write are atomic.
					$row = $wpdb->get_row( $wpdb->prepare( 'SELECT user_email,display_name FROM %i WHERE ID=%d FOR UPDATE', $wpdb->users, $user->ID ), ARRAY_A );
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Current names under the same lock, bypassing the object cache.
					$meta    = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key,meta_value FROM %i WHERE user_id=%d AND meta_key IN ('first_name','last_name') ORDER BY umeta_id DESC", $wpdb->usermeta, $user->ID ), ARRAY_A );
					$names   = wp_list_pluck( (array) $meta, 'meta_value', 'meta_key' );
					$current = $this->account_version( $row['user_email'] ?? '', $row['display_name'] ?? '', $names['first_name'] ?? '', $names['last_name'] ?? '' );
					if ( '' !== $version && $version !== $current ) {
						return $this->error( 'conflict', __( 'This account was changed by someone else. Reload it before saving.', 'ohmylms' ), 409, array( 'version' => $current ) );
					}
					$updated = wp_update_user(
						array(
							'ID'           => $user->ID,
							'user_email'   => $email,
							'first_name'   => $first,
							'last_name'    => $last,
							'display_name' => trim( $first . ' ' . $last ),
						)
					);
					if ( is_wp_error( $updated ) ) {
						return $this->error( 'rejected', $updated->get_error_message(), 422 );
					}

					return true;
				}
			);
		} catch ( \Throwable $failure ) {
			clean_user_cache( $user->ID );
			return $this->error( 'failed', __( 'The change could not be saved. Nothing was changed.', 'ohmylms' ), 500 );
		}
		clean_user_cache( $user->ID );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$fresh = get_userdata( $user->ID );
		if ( $request['send_reset'] ) {
			retrieve_password( $fresh->user_login );
		}
		try {
			Service::audit( 0, 'account_updated_by_admin', (int) $user->ID );
		} catch ( \Throwable $failure ) {
			unset( $failure );
		}

		return $this->private_response( $this->prepare_editable( $fresh ) );
	}
}
