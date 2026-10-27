<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
namespace OhMyLMS\Schools;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin controller implementation.
 */
final class Controller {
	/**
	 * Register for Controller.
	 */
	public static function register() {
		$routes = array(
			'/classes'                            => 'GET,POST',
			'/me'                                 => 'GET',
			'/users'                              => 'GET,POST',
			'/users/(?P<id>\d+)'                  => 'GET,POST',
			'/register'                           => 'POST',
			'/accept'                             => 'POST',
			'/schools'                            => 'GET,POST',
			'/children'                           => 'GET',
			'/work'                               => 'GET',
			'/courses'                            => 'GET',
			'/schools/(?P<school>\d+)/(?P<section>years|classes|roster|students|invitations|guardians|report|import|rollover)' => 'GET,POST',
			'/schools/(?P<school>\d+)/(?P<section>members|invitations|guardians)/(?P<id>\d+)' => 'DELETE',
			'/classes/(?P<class>\d+)/(?P<section>members|assignments|submissions|archive)' => 'GET,POST',
			'/assignments/(?P<learning>\d+)/submissions/(?P<attempt>\d+)' => 'POST',
			'/courses/(?P<course>\d+)/activities' => 'GET',
		);
		foreach ( $routes as $route => $methods ) {
			register_rest_route(
				'ohmylms/v1',
				'/school' . $route,
				array(
					'methods'             => $methods,
					'permission_callback' => function ( $request ) {
						if ( in_array( $request->get_route(), array( '/ohmylms/v1/school/register', '/ohmylms/v1/school/accept' ), true ) ) {
							return wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ? true : new \WP_Error( 'ohmylms_nonce', 'Refresh the page and try again.', array( 'status' => 403 ) );
						}
						return is_user_logged_in() ? true : new \WP_Error( 'ohmylms_login', 'Please sign in.', array( 'status' => 401 ) );
					},
					'callback'            => array( self::class, 'dispatch' ),
				)
			);
		}
	}
	/**
	 * Dispatch for Controller.
	 *
	 * @param mixed $request REST request.
	 */
	public static function dispatch( $request ) {
		try {
			$result   = self::handle( $request );
			$response = rest_ensure_response( $result );
			$response->header( 'Cache-Control', 'private, no-store' );
			return $response;
		} catch ( \Throwable $error ) {
			$status = in_array( $error->getCode(), array( 400, 403, 404, 409, 429 ), true ) ? $error->getCode() : 500;
			if ( 500 === $status ) {
				error_log( '[OhMyLMS schools] ' . $error->getMessage() ); }
			return new \WP_Error( 'ohmylms_school_error', 500 === $status ? 'Unable to complete the request. Please try again.' : $error->getMessage(), array( 'status' => $status ) );
		}
	}
	/**
	 * Rate limit for Controller.
	 *
	 * @param mixed $purpose Purpose.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	private static function rate_limit( $purpose ) {
		$key      = 'ohmylms_' . $purpose . '_' . hash_hmac( 'sha256', $_SERVER['REMOTE_ADDR'] ?? 'local', wp_salt( 'nonce' ) );
		$attempts = (int) get_transient( $key );
		if ( 15 <= $attempts ) {
			throw new \RuntimeException( 'Too many attempts. Please try again in 15 minutes.', 429 ); }
		set_transient( $key, $attempts + 1, 15 * MINUTE_IN_SECONDS );
	}
	/**
	 * Signup for Controller.
	 *
	 * @param mixed $data Input data.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	public static function signup( $data ) {
		self::rate_limit( 'school_signup' );
		Service::need( ! is_user_logged_in(), 'You are already signed in.' );
		Service::need( empty( $data['website'] ), 'Unable to create this account.' );
		$role = $data['role'] ?? 'student';
		Service::need( in_array( $role, array( 'student', 'teacher', 'parent' ), true ), 'Choose student, teacher or parent.' );
		$email    = sanitize_email( $data['email'] ?? '' );
		$password = (string) ( $data['password'] ?? '' );
		Service::need( is_email( $email ) && ! email_exists( $email ), 'Use a valid unused email, or sign in to your existing account.' );
		Service::need( strlen( $password ) >= 12 && strlen( $password ) <= 128, 'Use a password between 12 and 128 characters.' );
		$student_role = function_exists( 'ohmylms_get_assignable_student_role' ) ? ohmylms_get_assignable_student_role() : 'subscriber';
		$roles        = array(
			'student' => $student_role,
			'teacher' => 'ohmylms_teacher',
			'parent'  => 'ohmylms_parent',
		);
		$id           = wp_insert_user(
			array(
				'user_login'   => 'learner-' . strtolower( wp_generate_password( 16, false ) ),
				'user_email'   => $email,
				'user_pass'    => $password,
				'first_name'   => Service::text( $data['first_name'] ?? '' ),
				'last_name'    => Service::text( $data['last_name'] ?? '' ),
				'display_name' => trim( Service::text( $data['first_name'] ?? '' ) . ' ' . Service::text( $data['last_name'] ?? '' ) ) ?: __( 'Learner', 'ohmylms' ),
				'role'         => $roles[ $role ],
			)
		);
		if ( is_wp_error( $id ) ) {
			throw new \RuntimeException( $id->get_error_message(), 400 ); }
		if ( \OhMyLMS\Services\EmailVerificationService::is_required() || 'parent' === $role ) {
			\OhMyLMS\Services\EmailVerificationService::generate_and_send( $id ); }
		Service::audit( 0, 'account_created', $id );
		return array(
			'success'   => true,
			'message'   => 'Account created. Check your inbox for verification if requested, then sign in. No course enrollment or order was created.',
			'login_url' => wp_login_url( Views::portal_url() ),
		);
	}
	/**
	 * Create account for Controller.
	 *
	 * @param mixed $data Input data.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	private static function create_account( $data ) {
		Access::require_access( current_user_can( 'create_users' ) );
		$role = sanitize_key( $data['role'] ?? '' );
		Service::need( in_array( $role, array( 'student', 'teacher', 'parent' ), true ), 'Choose student, teacher or parent.' );
		$email = sanitize_email( $data['email'] ?? '' );
		Service::need( is_email( $email ) && ! email_exists( $email ), 'Use a valid email that is not already registered.' );
		$first = Service::text( $data['first_name'] ?? '' );
		$last  = Service::text( $data['last_name'] ?? '' );
		Service::need( '' !== $first, 'A first name is required.' );
		$login = sanitize_user( $data['username'] ?? '', true ) ?: sanitize_user( strstr( $email, '@', true ), true );
		$base  = $login ? $login : 'user';
		$login = $base;
		for ( $i = 2; username_exists( $login ); $i++ ) {
			$login = $base . $i; }
		$roles = array(
			'student' => function_exists( 'ohmylms_get_assignable_student_role' ) ? ohmylms_get_assignable_student_role() : 'subscriber',
			'teacher' => 'ohmylms_teacher',
			'parent'  => 'ohmylms_parent',
		);
		$id    = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 24 ),
				'first_name'   => $first,
				'last_name'    => $last,
				'display_name' => trim( $first . ' ' . $last ),
				'role'         => $roles[ $role ],
			)
		);
		if ( is_wp_error( $id ) ) {
			throw new \RuntimeException( $id->get_error_message(), 400 ); }
		// The new user sets their own password through the standard WordPress link.
		if ( ! empty( $data['send_email'] ) ) {
			wp_new_user_notification( $id, null, 'user' ); }
		Service::audit( 0, 'account_created_by_admin', $id );
		return array(
			'id'       => $id,
			'username' => $login,
			'message'  => __( 'Account created.', 'ohmylms' ),
		);
	}
	/**
	 * Account for Controller.
	 *
	 * @param mixed $id Id.
	 * @param mixed $data Input data.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	private static function account( $id, $data = null ) {
		$user = get_userdata( $id );
		Access::require_access( $user && current_user_can( 'edit_user', $id ) );
		if ( null !== $data ) {
			$email = sanitize_email( $data['email'] ?? '' );
			$other = email_exists( $email );
			Service::need( is_email( $email ) && ( ! $other || (int) $other === $id ), 'Use a valid email that is not used by another account.' );
			$first = Service::text( $data['first_name'] ?? '' );
			Service::need( '' !== $first, 'A first name is required.' );
			$last    = Service::text( $data['last_name'] ?? '' );
			$updated = wp_update_user(
				array(
					'ID'           => $id,
					'user_email'   => $email,
					'first_name'   => $first,
					'last_name'    => $last,
					'display_name' => trim( $first . ' ' . $last ),
				)
			);
			if ( is_wp_error( $updated ) ) {
				throw new \RuntimeException( $updated->get_error_message(), 400 ); }
			if ( ! empty( $data['send_reset'] ) && current_user_can( 'edit_user', $id ) ) {
				retrieve_password( $user->user_login ); }
			Service::audit( 0, 'account_updated_by_admin', $id );
			return array(
				'id'      => $id,
				'message' => __( 'Account updated.', 'ohmylms' ),
			);
		}
		return array(
			'id'         => $id,
			'first_name' => $user->first_name,
			'last_name'  => $user->last_name,
			'email'      => $user->user_email,
			'username'   => $user->user_login,
		);
	}
	/**
	 * Handle for Controller.
	 *
	 * @param mixed $r R.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	private static function handle( $r ) {
		global $wpdb;
		$route   = $r->get_route();
		$write   = $r->get_method() === 'POST';
		$data    = $r->get_json_params() ?: $r->get_body_params();
		$school  = absint( $r['school'] );
		$class   = absint( $r['class'] );
		$id      = absint( $r['id'] );
		$section = $r['section'];
		$page    = max( 1, absint( $r['page'] ) );
		if ( '/ohmylms/v1/school/register' === $route ) {
			return self::signup( $data ); }
		if ( preg_match( '#^/ohmylms/v1/school/users/\d+$#', $route ) ) {
			return self::account( $id, $write ? $data : null ); }
		if ( '/ohmylms/v1/school/users' === $route && $write ) {
			return self::create_account( $data ); }
		if ( '/ohmylms/v1/school/users' === $route ) {
			Access::require_access( current_user_can( 'list_users' ) );
			$search = Service::text( $r['search'] ?? '' );
			$filter = sanitize_key( $r['role'] ?? '' );
			$extra  = array();
			Service::need( in_array( $filter, array( '', 'teacher', 'parent' ), true ), 'Invalid account filter.' );
			if ( $filter ) {
				$ids = get_users(
					array(
						'role'   => 'ohmylms_' . $filter,
						'fields' => 'ID',
						'number' => -1,
					)
				);
				if ( 'teacher' === $filter ) {
					$m   = Schema::table( 'school_memberships' );
					$ids = array_merge( $ids, $wpdb->get_col( $wpdb->prepare( 'SELECT user_id FROM %i WHERE role=\'teacher\' AND status=\'active\'', $m ) ) );
				} else {
					$g   = Schema::table( 'guardian_links' );
					$ids = array_merge( $ids, $wpdb->get_col( $wpdb->prepare( 'SELECT guardian_user_id FROM %i WHERE status=\'active\'', $g ) ) );
				}
				$extra['include'] = array_unique( array_map( 'intval', $ids ) ) ?: array( 0 );
			}
			$query = new \WP_User_Query(
				array_merge(
					$extra,
					array(
						'number'         => 50,
						'paged'          => $page,
						'orderby'        => 'display_name',
						'order'          => 'ASC',
						'search'         => '' !== $search ? '*' . $search . '*' : '',
						'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
					)
				)
			);
			return array_map(
				function ( $user ) {
					return array(
						'id'           => $user->ID,
						'display_name' => $user->display_name,
						'user_login'   => $user->user_login,
						'email'        => $user->user_email,
						'created_at'   => $user->user_registered,
						'avatar'       => get_avatar_url( $user->ID ),
						'last_login'   => get_user_meta( $user->ID, '_ohmylms_last_login', true ),
						'is_banned'    => (bool) get_user_meta( $user->ID, '_ohmylms_banned_student', true ),
						'roles'        => implode(
							', ',
							array_map(
								function ( $role ) {
									return translate_user_role( wp_roles()->roles[ $role ]['name'] ?? $role );
								},
								$user->roles
							)
						),
						'edit_url'     => current_user_can( 'edit_user', $user->ID ) ? get_edit_user_link( $user->ID ) : '',
					);
				},
				$query->get_results()
			);
		}
		if ( '/ohmylms/v1/school/accept' === $route ) {
			self::rate_limit( 'school_invite' );
			return Service::accept( $data['token'] ?? '', (string) ( $data['password'] ?? '' ) ); }
		if ( '/ohmylms/v1/school/me' === $route ) {
			$m = Schema::table( 'school_memberships' );
			return array(
				'user_id'          => get_current_user_id(),
				'name'             => wp_get_current_user()->display_name,
				'platform_admin'   => Access::platform(),
				'can_create_class' => Access::teacher(),
				'can_list_users'   => current_user_can( 'list_users' ),
				'memberships'      => $wpdb->get_results( $wpdb->prepare( 'SELECT school_id,role FROM %i WHERE user_id=%d AND status=\'active\'', $m, get_current_user_id() ), ARRAY_A ),
			);
		}
		if ( '/ohmylms/v1/school/classes' === $route ) {
			return $write ? Service::create_class( 0, $data ) : Service::classes( 0, $page ); }
		if ( '/ohmylms/v1/school/schools' === $route ) {
			return $write ? Service::create_school( $data ) : Service::schools( $page ); }
		if ( '/ohmylms/v1/school/children' === $route ) {
			return Service::children(); }
		if ( '/ohmylms/v1/school/work' === $route ) {
			return Service::assignments( 0, absint( $r['student'] ), absint( $r['school_id'] ), $page, absint( $r['class_id'] ) ); }
		if ( '/ohmylms/v1/school/courses' === $route ) {
			Access::require_access( $r['class_id'] && Access::classroom( absint( $r['class_id'] ), true ) );
			$posts = get_posts(
				array(
					'post_type'      => OHMYLMS_COURSE_CPT,
					'post_status'    => 'publish',
					'posts_per_page' => 50,
					'paged'          => $page,
					's'              => Service::text( $r['search'] ?? '' ),
				)
			);
			return array_map(
				function ( $post ) {
					return array(
						'id'    => $post->ID,
						'title' => $post->post_title,
					);
				},
				$posts
			);
		}
		if ( $r['course'] ) {
			Access::require_access( $r['class_id'] && Access::classroom( absint( $r['class_id'] ), true ) );
			$course = get_post( absint( $r['course'] ) );
			Service::need( $course && OHMYLMS_COURSE_CPT === $course->post_type && 'publish' === $course->post_status, 'Course not available.' );
			$rel      = Schema::table( 'content_relationship' );
			$chapters = Schema::table( 'chapter_relationship' );
			return $wpdb->get_results( $wpdb->prepare( 'SELECT DISTINCT p.ID AS id,p.post_title AS title FROM %i r JOIN %i c ON c.chapter_id=r.chapter_id JOIN %i p ON p.ID=r.content_id WHERE c.course_id=%d ORDER BY p.post_title LIMIT 200', $rel, $chapters, $wpdb->posts, $course->ID ), ARRAY_A );
		}
		if ( $write && $r['learning'] && $r['attempt'] ) {
			return Service::grade( absint( $r['learning'] ), absint( $r['attempt'] ), $data ); }
		if ( $r->get_method() === 'DELETE' ) {
			if ( 'members' === $section ) {
				return Service::remove_member( $school, $id ); }
			if ( 'invitations' === $section ) {
				return Service::revoke_invite( $school, $id ); }
			if ( 'guardians' === $section ) {
				return Service::revoke_guardian( $school, $id ); }
		}
		if ( $class ) {
			if ( 'members' === $section ) {
				if ( $write ) {
					return Service::class_member( $class, $data ); }
				$record = Access::classroom( $class );
				Access::require_access( $record );
				return Service::roster( (int) $record['school_id'], $class, $page, Service::text( $r['search'] ?? '' ) );
			}
			if ( 'assignments' === $section ) {
				return $write ? Service::create_assignment( $class, $data ) : Service::assignments( $class, 0, 0, $page ); }
			if ( 'submissions' === $section && ! $write ) {
				return Service::submissions( $class ); }
			if ( 'archive' === $section && $write ) {
				return Service::archive_class( $class ); }
		}
		if ( $school ) {
			switch ( $section ) {
				case 'years':
					return $write ? Service::create_year( $school, $data ) : Service::years( $school );
				case 'classes':
					return $write ? Service::create_class( $school, $data ) : Service::classes( $school, $page );
				case 'roster':
					if ( ! $write ) {
						return Service::roster( $school, 0, $page, Service::text( $r['search'] ?? '' ) );
					} break;
				case 'students':
					if ( $write ) {
						return Service::create_student( $school, $data );
					} break;
				case 'invitations':
					return $write ? Service::invite( $school, $data ) : Service::invitations( $school );
				case 'guardians':
					if ( ! $write ) {
						return Service::guardians( $school );
					} break;
				case 'report':
					if ( ! $write ) {
						return Service::report( $school );
					} break;
				case 'import':
					if ( $write ) {
						return Service::import_roster( $school, $data );
					} break;
				case 'rollover':
					if ( $write ) {
						return Service::rollover( $school, $data );
					} break;
			}
		}
		throw new \RuntimeException( 'Route not found.', 404 );
	}
}
