<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
namespace OhMyLMS\Schools;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin service implementation.
 */
final class Service {
	/**
	 * Now for Service.
	 */
	public static function now() {
		return gmdate( 'Y-m-d H:i:s' ); }
	/**
	 * Text for Service.
	 *
	 * @param mixed $value Value to process.
	 * @param int   $max Max.
	 */
	public static function text( $value, $max = 190 ) {
		return mb_substr( sanitize_text_field( (string) $value ), 0, $max ); }
	/**
	 * Need for Service.
	 *
	 * @param mixed $value Value to process.
	 * @param mixed $message Message.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	public static function need( $value, $message ) {
		if ( ! $value ) {
			throw new \RuntimeException( $message, 400 ); }
	}
	/**
	 * Insert for Service.
	 *
	 * @param mixed $table Table.
	 * @param array $data Input data.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	public static function insert( $table, array $data ) {
		global $wpdb;
		if ( false === $wpdb->insert( Schema::table( $table ), $data ) ) {
			throw new \RuntimeException( 'Unable to save this record. It may already exist.', 409 ); }
		return (int) $wpdb->insert_id;
	}
	/**
	 * Update for Service.
	 *
	 * @param mixed $table Table.
	 * @param mixed $data Input data.
	 * @param mixed $where Where.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	public static function update( $table, $data, $where ) {
		global $wpdb;
		if ( false === $wpdb->update( Schema::table( $table ), $data, $where ) ) {
			throw new \RuntimeException( 'Unable to update this record.', 500 ); }
	}
	/**
	 * Audit for Service.
	 *
	 * @param mixed $school School.
	 * @param mixed $action Action.
	 * @param int   $object Object.
	 */
	public static function audit( $school, $action, $object = 0 ) {
		self::insert(
			'school_audit_log',
			array(
				'school_id'  => $school,
				'actor_id'   => get_current_user_id(),
				'action'     => $action,
				'object_id'  => $object,
				'created_at' => self::now(),
			)
		);
	}
	/**
	 * Row for Service.
	 *
	 * @param mixed $table Table.
	 * @param mixed $id Id.
	 */
	public static function row( $table, $id ) {
		global $wpdb;
		$name = Schema::table( $table );
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $name, $id ), ARRAY_A );
	}
	/**
	 * Membership for Service.
	 *
	 * @param mixed $table Table.
	 * @param array $key Lookup key.
	 * @param bool  $active Active.
	 */
	public static function membership( $table, array $key, $active = true ) {
		global $wpdb;
		$name    = Schema::table( $table );
		$clauses = array();
		$values  = array();
		foreach ( $key as $column => $value ) {
			$clauses[] = "$column=%s";
			$values[]  = $value; }
		$id   = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $name WHERE " . implode( ' AND ', $clauses ), $values ) );
		$data = array( 'status' => $active ? 'active' : 'inactive' );
		if ( $id ) {
			self::update( $table, $data, array( 'id' => $id ) );
			return (int) $id; }
		self::need( $active, 'Membership does not exist.' );
		return self::insert( $table, array_merge( $key, $data, array( 'joined_at' => self::now() ) ) );
	}
	/**
	 * Transaction for Service.
	 *
	 * @param mixed $callback Callback to invoke.
	 */
	public static function transaction( $callback ) {
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		try {
			$result = $callback();
			$wpdb->query( 'COMMIT' );
			return $result; } catch ( \Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			throw $error; }
	}
	/**
	 * Schools for Service.
	 *
	 * @param int $page Page.
	 */
	public static function schools( $page = 1 ) {
		global $wpdb;
		$s     = Schema::table( 'schools' );
		$m     = Schema::table( 'school_memberships' );
		$where = Access::platform() ? '1=1' : $wpdb->prepare( 's.status=\'active\' AND EXISTS (SELECT 1 FROM %i m WHERE m.school_id=s.id AND m.user_id=%d AND m.status=\'active\')', $m, get_current_user_id() );
		return $wpdb->get_results( $wpdb->prepare( "SELECT s.* FROM $s s WHERE $where ORDER BY s.name LIMIT 50 OFFSET %d", ( max( 1, $page ) - 1 ) * 50 ), ARRAY_A );
	}
	/**
	 * Create school for Service.
	 *
	 * @param mixed $data Input data.
	 */
	public static function create_school( $data ) {
		// Platform administrators may create any school; a teacher may create one school and becomes its administrator.
		Access::require_access( Access::platform() || Access::teacher() );
		global $wpdb;
		if ( ! Access::platform() ) {
			self::need( ! (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE created_by=%d AND status='active'", Schema::table( 'schools' ), get_current_user_id() ) ), 'You have already created a school.' );
		}
		$name = self::text( $data['name'] ?? '' );
		$zone = self::text( $data['timezone'] ?? 'UTC', 80 );
		self::need( $name && in_array( $zone, timezone_identifiers_list(), true ), 'Provide a school name and a valid timezone.' );
		$base = sanitize_title( $data['slug'] ?? $name );
		$base = $base ? $base : 'school';
		$slug = $base;
		for ( $i = 2; $i < 100 && $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE slug=%s', Schema::table( 'schools' ), $slug ) ); $i++ ) {
			$slug = $base . '-' . $i;
		}
		$id = self::insert(
			'schools',
			array(
				'name'       => $name,
				'slug'       => $slug,
				'timezone'   => $zone,
				'created_by' => get_current_user_id(),
				'created_at' => self::now(),
			)
		);
		if ( ! Access::platform() ) {
			$user = wp_get_current_user();
			foreach ( array( 'school_admin', 'teacher' ) as $role ) {
				self::membership(
					'school_memberships',
					array(
						'school_id' => $id,
						'user_id'   => $user->ID,
						'role'      => $role,
					)
				);
			}
			$user->add_role( 'ohmylms_school_admin' );
		}
		self::audit( $id, 'school_created', $id );
		return array( 'id' => $id );
	}
	/**
	 * Update school for Service.
	 *
	 * @param mixed $school School.
	 * @param mixed $data Input data.
	 */
	public static function update_school( $school, $data ) {
		Access::require_access( Access::school( $school ) );
		$name = self::text( $data['name'] ?? '' );
		$zone = self::text( $data['timezone'] ?? 'UTC', 80 );
		self::need( $name && in_array( $zone, timezone_identifiers_list(), true ), 'Provide a school name and a valid timezone.' );
		self::update(
			'schools',
			array(
				'name'     => $name,
				'timezone' => $zone,
			),
			array( 'id' => $school )
		);
		self::audit( $school, 'school_updated', $school );
		return array( 'success' => true );
	}
	/**
	 * Years for Service.
	 *
	 * @param mixed $school School.
	 */
	public static function years( $school ) {
		global $wpdb;
		Access::require_access( Access::school( $school, array( 'school_admin', 'teacher' ) ) );
		$table = Schema::table( 'academic_years' );
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE school_id=%d ORDER BY starts_at DESC LIMIT 100', $table, $school ), ARRAY_A );
	}
	/**
	 * Create year for Service.
	 *
	 * @param mixed $school School.
	 * @param mixed $data Input data.
	 */
	public static function create_year( $school, $data ) {
		Access::require_access( Access::school( $school ) );
		$start = $data['starts_at'] ?? '';
		$end   = $data['ends_at'] ?? '';
		$label = self::text( $data['label'] ?? '', 100 );
		foreach ( array( $start, $end ) as $date ) {
			$parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
			self::need( $parsed && $parsed->format( 'Y-m-d' ) === $date, 'Use valid academic-year dates.' );
		}
		self::need( $label && $end > $start, 'The end date must follow the start date.' );
		$id = self::insert(
			'academic_years',
			array(
				'school_id' => $school,
				'label'     => $label,
				'starts_at' => $start,
				'ends_at'   => $end,
			)
		);
		self::audit( $school, 'year_created', $id );
		return array( 'id' => $id );
	}
	/**
	 * Classes for Service.
	 *
	 * @param mixed $school School.
	 * @param int   $page Page.
	 */
	public static function classes( $school, $page = 1 ) {
		global $wpdb;
		Access::require_access( $school ? Access::school( $school, array( 'school_admin', 'teacher', 'student' ) ) : Access::teacher() );
		$c     = Schema::table( 'classes' );
		$m     = Schema::table( 'class_memberships' );
		$audit = Schema::table( 'school_audit_log' );
		$scope = ( $school ? Access::school( $school ) : Access::platform() ) ? '' : $wpdb->prepare( ' AND EXISTS (SELECT 1 FROM %i m WHERE m.class_id=c.id AND m.user_id=%d AND m.status=\'active\')', $m, get_current_user_id() );
		return $wpdb->get_results( $wpdb->prepare( "SELECT c.*,(SELECT MIN(a.created_at) FROM $audit a WHERE a.action='class_created' AND a.object_id=c.id) AS created_at FROM $c c WHERE c.school_id=%d $scope ORDER BY c.id DESC LIMIT 50 OFFSET %d", $school, ( max( 1, $page ) - 1 ) * 50 ), ARRAY_A );
	}
	/**
	 * Create class for Service.
	 *
	 * @param mixed $school School.
	 * @param mixed $data Input data.
	 */
	public static function create_class( $school, $data ) {
		Access::require_access( $school ? Access::school( $school ) : Access::teacher() );
		$year = $school ? self::row( 'academic_years', absint( $data['academic_year_id'] ?? 0 ) ) : array( 'id' => 0 );
		if ( $school ) {
			self::need( $year && (int) $year['school_id'] === $school && 'active' === $year['status'], 'Select an active academic year in this school.' ); }
		$name = self::text( $data['name'] ?? '' );
		self::need( $name, 'A class name is required.' );
		return self::transaction(
			function () use ( $school, $year, $name, $data ) {
				if ( $school ) {
					global $wpdb;
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Revalidate the year under the same lock used by rollover.
					$year_status = $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM %i WHERE id=%d FOR UPDATE', Schema::table( 'academic_years' ), $year['id'] ) );
					Access::require_access( Access::school( $school ) );
					self::need( 'active' === $year_status, 'Select an active academic year in this school.' );
				}
				$id = self::insert(
					'classes',
					array(
						'school_id'        => $school,
						'academic_year_id' => $year['id'],
						'name'             => $name,
						'subject'          => self::text( $data['subject'] ?? '', 100 ),
						'grade'            => self::text( $data['grade'] ?? '', 50 ),
					)
				);
				if ( ! $school ) {
					self::membership(
						'class_memberships',
						array(
							'class_id' => $id,
							'user_id'  => get_current_user_id(),
							'role'     => 'teacher',
						)
					); }
				self::audit( $school, 'class_created', $id );
				return array( 'id' => $id );
			}
		);
	}
	/**
	 * Roster for Service.
	 *
	 * @param mixed  $school School.
	 * @param int    $class Class.
	 * @param int    $page Page.
	 * @param string $search Search.
	 */
	public static function roster( $school, $class = 0, $page = 1, $search = '' ) {
		global $wpdb;
		if ( $class ) {
			$record = Access::classroom( $class );
			Access::require_access( $record && (int) $record['school_id'] === $school );
		} else {
			Access::require_access( Access::school( $school ) ); }
		if ( ! $school && $class ) {
			$c    = Schema::table( 'class_memberships' );
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			return $wpdb->get_results( $wpdb->prepare( 'SELECT m.id,m.user_id,m.role,m.status,u.display_name,u.user_login FROM %i m JOIN %i u ON u.ID=m.user_id WHERE m.class_id=%d AND m.status=\'active\' AND u.display_name LIKE %s ORDER BY u.display_name,m.id LIMIT 50 OFFSET %d', $c, $wpdb->users, $class, $like, ( max( 1, $page ) - 1 ) * 50 ), ARRAY_A );
		}
		$m     = Schema::table( 'school_memberships' );
		$c     = Schema::table( 'class_memberships' );
		$p     = Schema::table( 'school_student_profiles' );
		$scope = $class ? $wpdb->prepare( ' AND EXISTS (SELECT 1 FROM %i cm WHERE cm.class_id=%d AND cm.user_id=m.user_id AND cm.role=m.role AND cm.status=\'active\')', $c, $class ) : '';
		$like  = '%' . $wpdb->esc_like( $search ) . '%';
		return $wpdb->get_results( $wpdb->prepare( "SELECT m.id,m.user_id,m.role,m.status,u.display_name,u.user_login,p.external_student_id FROM $m m JOIN {$wpdb->users} u ON u.ID=m.user_id LEFT JOIN $p p ON p.school_id=m.school_id AND p.user_id=m.user_id WHERE m.school_id=%d AND m.status='active' $scope AND (u.display_name LIKE %s OR p.external_student_id LIKE %s) ORDER BY u.display_name,m.id LIMIT 50 OFFSET %d", $school, $like, $like, ( max( 1, $page ) - 1 ) * 50 ), ARRAY_A );
	}
	/**
	 * Class member for Service.
	 *
	 * @param mixed $class Class.
	 * @param mixed $data Input data.
	 */
	public static function class_member( $class, $data ) {
		$record = Access::classroom( $class, true );
		Access::require_access( $record );
		$user = absint( $data['user_id'] ?? 0 );
		$role = $data['role'] ?? 'student';
		self::need( in_array( $role, array( 'student', 'teacher' ), true ), 'Invalid class role.' );
		if ( 'teacher' === $role ) {
			Access::require_access( Access::school( $record['school_id'] ) ); }
		// Staff can use only the master school roster, never arbitrary site users.
		Access::require_access( Access::school( $record['school_id'], array( $role ), $user ) );
		$active = ( $data['status'] ?? 'active' ) === 'active';
		self::membership(
			'class_memberships',
			array(
				'class_id' => $class,
				'user_id'  => $user,
				'role'     => $role,
			),
			$active
		);
		self::audit( $record['school_id'], $active ? 'class_member_added' : 'class_member_removed', $user );
		return array( 'success' => true );
	}
	/**
	 * Remove member for Service.
	 *
	 * @param mixed $school School.
	 * @param mixed $id Id.
	 */
	public static function remove_member( $school, $id ) {
		global $wpdb;
		Access::require_access( Access::school( $school ) );
		$record = self::row( 'school_memberships', $id );
		self::need( $record && (int) $record['school_id'] === $school, 'Membership not found.' );
		self::need( (int) $record['user_id'] !== get_current_user_id() || Access::platform(), 'Ask another administrator to remove your membership.' );
		self::transaction(
			function () use ( $school, $id, $record, $wpdb ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Share the departure lock and recheck authority after waiting for it.
				$wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE id=%d FOR UPDATE', Schema::table( 'schools' ), $school ) );
				Access::require_access( Access::school( $school ) );
				$record = self::row( 'school_memberships', $id );
				self::need( $record && (int) $record['school_id'] === $school, 'Membership not found.' );
				if ( 'school_admin' === $record['role'] && 'active' === $record['status'] ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Do not remove the last existing active administrator.
					$other_admin = $wpdb->get_var( $wpdb->prepare( "SELECT m.id FROM %i m JOIN %i u ON u.ID=m.user_id WHERE m.school_id=%d AND m.role='school_admin' AND m.status='active' AND m.user_id<>%d LIMIT 1", Schema::table( 'school_memberships' ), $wpdb->users, $school, $record['user_id'] ) );
					self::need( $other_admin, 'Invite another school administrator before removing this administrator.' );
				}
				self::update( 'school_memberships', array( 'status' => 'inactive' ), array( 'id' => $id ) );
				$c = Schema::table( 'classes' );
				$m = Schema::table( 'class_memberships' );
				$wpdb->query( $wpdb->prepare( 'UPDATE %i m JOIN %i c ON c.id=m.class_id SET m.status=\'inactive\' WHERE c.school_id=%d AND m.user_id=%d AND m.role=%s', $m, $c, $school, $record['user_id'], $record['role'] ) );
				if ( 'student' === $record['role'] ) {
					self::update(
						'guardian_links',
						array( 'status' => 'inactive' ),
						array(
							'school_id'       => $school,
							'student_user_id' => $record['user_id'],
						)
					); }
				self::audit( $school, 'school_member_removed', $record['user_id'] );
			}
		);
		return array( 'success' => true );
	}
	/**
	 * Create a managed student account and its school membership.
	 *
	 * @param mixed $school School.
	 * @param mixed $data Input data.
	 * @throws \RuntimeException When the account cannot be created.
	 * @throws \Throwable When a failed membership transaction is rolled back.
	 */
	public static function create_student( $school, $data ) {
		Access::require_access( Access::school( $school ) );
		$name     = self::text( $data['name'] ?? '' );
		$external = self::text( $data['external_student_id'] ?? '', 100 );
		self::need( $name && $external, 'Student name and school student ID are required.' );
		global $wpdb;
		$p = Schema::table( 'school_student_profiles' );
		self::need( ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE school_id=%d AND external_student_id=%s', $p, $school, $external ) ), 'This school student ID already exists. Use the existing roster entry.' );
		$role = function_exists( 'ohmylms_get_assignable_student_role' ) ? ohmylms_get_assignable_student_role() : 'subscriber';
		$id   = wp_insert_user(
			array(
				'user_login'   => 'student-' . $school . '-' . strtolower( wp_generate_password( 12, false ) ),
				'user_pass'    => wp_generate_password( 40 ),
				'display_name' => $name,
				'role'         => $role,
			)
		);
		if ( is_wp_error( $id ) ) {
			throw new \RuntimeException( $id->get_error_message(), 400 ); }
		try {
			self::transaction(
				function () use ( $school, $id, $external ) {
					self::insert(
						'school_student_profiles',
						array(
							'school_id'           => $school,
							'user_id'             => $id,
							'external_student_id' => $external,
						)
					);
					self::membership(
						'school_memberships',
						array(
							'school_id' => $school,
							'user_id'   => $id,
							'role'      => 'student',
						)
					);
					update_user_meta( $id, '_ohmylms_managed_school', $school );
					self::audit( $school, 'student_created', $id );
				}
			);
		} catch ( \Throwable $error ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $id );
			throw $error;
		}
		return array(
			'id'       => $id,
			'username' => get_userdata( $id )->user_login,
		);
	}
	/**
	 * Invite for Service.
	 *
	 * @param mixed $school School.
	 * @param mixed $data Input data.
	 */
	public static function invite( $school, $data ) {
		Access::require_access( Access::school( $school ) );
		$role    = $data['role'] ?? '';
		$email   = sanitize_email( $data['email'] ?? '' );
		$student = absint( $data['student_user_id'] ?? 0 );
		$class   = absint( $data['class_id'] ?? 0 );
		self::need( in_array( $role, array( 'school_admin', 'teacher', 'student', 'guardian', 'activation' ), true ), 'Invalid invitation type.' );
		self::need( 'activation' === $role || is_email( $email ), 'A valid recipient email is required.' );
		if ( 'activation' === $role || 'guardian' === $role ) {
			Access::require_access( Access::student( $school, $student ) ); }
		if ( 'activation' === $role ) {
			Access::require_access( Lifecycle::managed_student( $school, $student ) ); }
		if ( $class ) {
			$record = Access::classroom( $class, true );
			self::need( $record && (int) $record['school_id'] === $school && in_array( $role, array( 'teacher', 'student' ), true ), 'Select a valid class for this invitation.' );
		}
		$token = bin2hex( random_bytes( 32 ) );
		$id    = self::insert(
			'school_invitations',
			array(
				'token_hash'      => hash( 'sha256', $token ),
				'school_id'       => $school,
				'class_id'        => $class,
				'student_user_id' => $student,
				'role'            => $role,
				'email'           => $email,
				'expires_at'      => gmdate( 'Y-m-d H:i:s', time() + 2 * DAY_IN_SECONDS ),
				'invited_by'      => get_current_user_id(),
				'created_at'      => self::now(),
			)
		);
		$url   = \OhMyLMS\Student\Hub::invitation_url( $token, $role );
		// Return the link to the authorized staff member. Mail is sent only on an explicit UI action.
		if ( ! empty( $data['send_email'] ) && 'activation' !== $role ) {
			$sent = wp_mail( $email, __( 'Your OhMyLMS invitation', 'ohmylms' ), sprintf( __( 'Open this invitation within 48 hours: %s', 'ohmylms' ), $url ) );
		}
		self::audit( $school, 'invitation_created', $id );
		return array(
			'id'         => $id,
			'url'        => $url,
			'email_sent' => isset( $sent ) ? (bool) $sent : false,
		);
	}
	/**
	 * Invitations for Service.
	 *
	 * @param mixed $school School.
	 */
	public static function invitations( $school ) {
		global $wpdb;
		Access::require_access( Access::school( $school ) );
		$table = Schema::table( 'school_invitations' );
		return $wpdb->get_results( $wpdb->prepare( 'SELECT id,role,email,student_user_id,expires_at,consumed_at,revoked_at FROM %i WHERE school_id=%d ORDER BY id DESC LIMIT 50', $table, $school ), ARRAY_A );
	}
	/**
	 * Revoke invite for Service.
	 *
	 * @param mixed $school School.
	 * @param mixed $id Id.
	 */
	public static function revoke_invite( $school, $id ) {
		Access::require_access( Access::school( $school ) );
		$invite = self::row( 'school_invitations', $id );
		self::need( $invite && (int) $invite['school_id'] === $school, 'Invitation not found.' );
		self::update( 'school_invitations', array( 'revoked_at' => self::now() ), array( 'id' => $id ) );
		self::audit( $school, 'invitation_revoked', $id );
		return array( 'success' => true );
	}
	/**
	 * Role of a currently valid invitation, without consuming it; empty when invalid.
	 *
	 * @param string $token Raw token.
	 * @return string
	 */
	public static function invite_role( $token ) {
		global $wpdb;
		if ( ! is_string( $token ) || ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			return '';
		}
		$role = $wpdb->get_var( $wpdb->prepare( 'SELECT role FROM %i WHERE token_hash=%s AND consumed_at IS NULL AND revoked_at IS NULL AND expires_at>%s', Schema::table( 'school_invitations' ), hash( 'sha256', $token ), self::now() ) );
		return $role ? (string) $role : '';
	}
	/**
	 * Accept for Service.
	 *
	 * @param mixed  $token Token.
	 * @param string $password Password.
	 */
	public static function accept( $token, $password = '' ) {
		global $wpdb;
		self::need( is_string( $token ) && preg_match( '/^[a-f0-9]{64}$/', $token ), 'Invalid invitation.' );
		$table = Schema::table( 'school_invitations' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Candidate lookup only, outside the transaction so it cannot establish a stale authorization snapshot before acquiring the user lock.
		$candidate = $wpdb->get_row( $wpdb->prepare( 'SELECT role,student_user_id FROM %i WHERE token_hash=%s', $table, hash( 'sha256', $token ) ), ARRAY_A );
		return self::transaction(
			function () use ( $wpdb, $table, $token, $password, $candidate ) {
				if ( $candidate && 'activation' === $candidate['role'] ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Match recovery's user-then-invitation lock order to avoid reset/accept deadlocks.
					$wpdb->get_var( $wpdb->prepare( 'SELECT ID FROM %i WHERE ID=%d FOR UPDATE', $wpdb->users, $candidate['student_user_id'] ) );
				}
				$invite = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE token_hash=%s FOR UPDATE', $table, hash( 'sha256', $token ) ), ARRAY_A );
				self::need( $invite && ! $invite['consumed_at'] && ! $invite['revoked_at'] && $invite['expires_at'] > self::now(), 'This invitation has expired or has already been used.' );
				$school = (int) $invite['school_id'];
				if ( 0 === $school ) {
					return ClassManagement::accept( $invite );
				}
				$school_row = self::row( 'schools', $school );
				self::need( $school_row && 'active' === $school_row['status'], 'This school is inactive.' );
				$issuer = get_userdata( $invite['invited_by'] );
				self::need( $issuer && ( user_can( $issuer, 'manage_options' ) || Access::school( $school, array( 'school_admin' ), $issuer->ID ) ), 'The invitation issuer no longer has permission.' );
				if ( 'activation' === $invite['role'] ) {
					$user_id = (int) $invite['student_user_id'];
					Access::require_access( Lifecycle::managed_student( $school, $user_id ) );
					self::need( strlen( $password ) >= 12 && strlen( $password ) <= 128, 'Choose a password between 12 and 128 characters.' );
					wp_set_password( $password, $user_id );
					$updated_user = get_userdata( $user_id );
					self::need( $updated_user && wp_check_password( $password, $updated_user->user_pass, $user_id ), 'Unable to save the new password.' );
					// Invalidate other outstanding recovery links for this child.
					$wpdb->query( $wpdb->prepare( 'UPDATE %i SET revoked_at=%s WHERE student_user_id=%d AND role=\'activation\' AND id<>%d AND consumed_at IS NULL', $table, self::now(), $user_id, $invite['id'] ) );
				} else {
					$user    = wp_get_current_user();
					$user_id = $user->ID;
					Access::require_access( $user_id );
					self::need( strtolower( $user->user_email ) === strtolower( $invite['email'] ), 'This invitation was sent to a different email address. Sign in with the account it was sent to.' );
					// Possession of a single-use token sent to this address proves mailbox access.
					update_user_meta( $user_id, \OhMyLMS\Services\EmailVerificationService::META_VERIFIED, 'yes' );
					if ( 'guardian' === $invite['role'] ) {
						Access::require_access( Access::student( $school, $invite['student_user_id'] ) );
						$links  = Schema::table( 'guardian_links' );
						$link   = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE school_id=%d AND guardian_user_id=%d AND student_user_id=%d', $links, $school, $user_id, $invite['student_user_id'] ) );
						$record = array(
							'status'      => 'active',
							'approved_by' => $invite['invited_by'],
							'approved_at' => self::now(),
						);
						if ( $link ) {
							self::update( 'guardian_links', $record, array( 'id' => $link ) ); } else {
							self::insert(
								'guardian_links',
								array_merge(
									$record,
									array(
										'school_id'        => $school,
										'guardian_user_id' => $user_id,
										'student_user_id'  => $invite['student_user_id'],
									)
								)
							); }
							$user->add_role( 'ohmylms_parent' );
					} else {
						self::membership(
							'school_memberships',
							array(
								'school_id' => $school,
								'user_id'   => $user_id,
								'role'      => $invite['role'],
							)
						);
						$role_map = array(
							'school_admin' => 'ohmylms_school_admin',
							'teacher'      => 'ohmylms_teacher',
							'student'      => function_exists( 'ohmylms_get_assignable_student_role' ) ? ohmylms_get_assignable_student_role() : 'subscriber',
						);
						$user->add_role( $role_map[ $invite['role'] ] );
						if ( $invite['class_id'] ) {
							$class = self::row( 'classes', $invite['class_id'] );
							self::need( $class && 'active' === $class['status'] && (int) $class['school_id'] === $school, 'This class is no longer active.' );
							self::membership(
								'class_memberships',
								array(
									'class_id' => $class['id'],
									'user_id'  => $user_id,
									'role'     => $invite['role'],
								)
							);
						}
					}
				}
				self::update( 'school_invitations', array( 'consumed_at' => self::now() ), array( 'id' => $invite['id'] ) );
				self::audit( $school, 'invitation_accepted', $invite['id'] );
				return array(
					'success'  => true,
					'username' => 'activation' === $invite['role'] ? get_userdata( $user_id )->user_login : '',
					'message'  => 'Invitation accepted. You can now sign in or open your dashboard.',
				);
			}
		);
	}
	/**
	 * Guardians for Service.
	 *
	 * @param mixed $school School.
	 */
	public static function guardians( $school ) {
		global $wpdb;
		Access::require_access( Access::school( $school ) );
		$g = Schema::table( 'guardian_links' );
		return $wpdb->get_results( $wpdb->prepare( 'SELECT g.id,g.student_user_id,g.status,u.display_name AS parent_name,s.display_name AS student_name FROM %i g JOIN %i u ON u.ID=g.guardian_user_id JOIN %i s ON s.ID=g.student_user_id WHERE g.school_id=%d ORDER BY g.id DESC LIMIT 100', $g, $wpdb->users, $wpdb->users, $school ), ARRAY_A );
	}
	/**
	 * Revoke guardian for Service.
	 *
	 * @param mixed $school School.
	 * @param mixed $id Id.
	 */
	public static function revoke_guardian( $school, $id ) {
		Access::require_access( Access::school( $school ) );
		$link = self::row( 'guardian_links', $id );
		self::need( $link && (int) $link['school_id'] === $school, 'Link not found.' );
		self::update( 'guardian_links', array( 'status' => 'inactive' ), array( 'id' => $id ) );
		self::audit( $school, 'guardian_revoked', $id );
		return array( 'success' => true );
	}
	/**
	 * Children for Service.
	 */
	public static function children() {
		global $wpdb;
		$g = Schema::table( 'guardian_links' );
		$s = Schema::table( 'schools' );
		$m = Schema::table( 'school_memberships' );
		return $wpdb->get_results( $wpdb->prepare( 'SELECT DISTINCT g.student_user_id,g.school_id,u.display_name,s.name AS school_name FROM %i g JOIN %i s ON s.id=g.school_id AND s.status=\'active\' JOIN %i u ON u.ID=g.student_user_id JOIN %i m ON m.school_id=g.school_id AND m.user_id=g.student_user_id AND m.role=\'student\' AND m.status=\'active\' WHERE g.guardian_user_id=%d AND g.status=\'active\' ORDER BY u.display_name LIMIT 100', $g, $s, $wpdb->users, $m, get_current_user_id() ), ARRAY_A );
	}
	/**
	 * Create assignment for Service.
	 *
	 * @param mixed $class Class.
	 * @param mixed $data Input data.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	public static function create_assignment( $class, $data ) {
		global $wpdb;
		$record = Access::classroom( $class, true );
		Access::require_access( $record );
		$course_id = absint( $data['course_id'] ?? 0 );
		$content   = absint( $data['content_id'] ?? 0 );
		$course    = get_post( $course_id );
		self::need( $course && OHMYLMS_COURSE_CPT === $course->post_type && 'publish' === $course->post_status, 'Select a published course.' );
		// Content distribution does not grant access or change purchases/enrollments.
		if ( $content ) {
			$rel      = Schema::table( 'content_relationship' );
			$chapters = Schema::table( 'chapter_relationship' );
			self::need( $wpdb->get_var( $wpdb->prepare( 'SELECT r.id FROM %i r JOIN %i c ON c.chapter_id=r.chapter_id WHERE c.course_id=%d AND r.content_id=%d', $rel, $chapters, $course_id, $content ) ), 'This activity does not belong to the selected course.' );
		}
		$m     = Schema::table( 'class_memberships' );
		$sm    = Schema::table( 'school_memberships' );
		$users = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT cm.user_id FROM %i cm JOIN %i sm ON sm.user_id=cm.user_id AND sm.school_id=%d AND sm.role=\'student\' AND sm.status=\'active\' WHERE cm.class_id=%d AND cm.role=\'student\' AND cm.status=\'active\'', $m, $sm, $record['school_id'], $class ) ) );
		if ( ! empty( $data['student_ids'] ) ) {
			$selected = array_unique( array_map( 'absint', (array) $data['student_ids'] ) );
			self::need( ! array_diff( $selected, $users ), 'A selected student is outside this class.' );
			$users = $selected; }
		self::need( $users, 'Add students to the class first.' );
		foreach ( $users as $user ) {
			$student = new \OhMyLMS\Data\Student( $user );
			self::need( $student->maybe_enrolled( $course_id ), 'Every recipient must already have access to this course. Assignment does not enroll students.' );
			self::need( ! empty( $data['prior_completion'] ) || ! ( $content ? $student->maybe_completed( $content ) : $student->is_course_completed( $course_id ) ), 'A recipient already completed this work. Enable counting prior completion or choose different work.' );
		}
		$due = null;
		if ( ! empty( $data['due_at'] ) ) {
			try {
				$school = self::row( 'schools', $record['school_id'] );
				$due    = ( new \DateTimeImmutable( $data['due_at'], new \DateTimeZone( $school['timezone'] ) ) )->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ); } catch ( \Exception $error ) {
				throw new \RuntimeException( 'Invalid due date.', 400 ); }
		}
		$title = self::text( $data['title'] ?? '' ) ?: get_the_title( $content ?: $course_id );
		return self::transaction(
			function () use ( $record, $class, $course_id, $content, $due, $title, $users, $data ) {
				$id = self::insert(
					'learning_assignments',
					array(
						'school_id'        => $record['school_id'],
						'class_id'         => $class,
						'creator_id'       => get_current_user_id(),
						'title'            => $title,
						'course_id'        => $course_id,
						'content_id'       => $content,
						'due_at'           => $due,
						'prior_completion' => ! empty( $data['prior_completion'] ) ? 1 : 0,
						'created_at'       => self::now(),
					)
				);
				foreach ( $users as $user ) {
					$student = new \OhMyLMS\Data\Student( $user );
					$done    = ! empty( $data['prior_completion'] ) && ( $content ? $student->maybe_completed( $content ) : $student->is_course_completed( $course_id ) );
					self::insert(
						'assignment_recipients',
						array(
							'assignment_id'   => $id,
							'student_user_id' => $user,
							'assigned_at'     => self::now(),
							'completed_at'    => $done ? self::now() : null,
						)
					);
				}
				self::audit( $record['school_id'], 'learning_assigned', $id );
				return array(
					'id'         => $id,
					'recipients' => count( $users ),
				);
			}
		);
	}
	/**
	 * Assignments for Service.
	 *
	 * @param int $class Class.
	 * @param int $student Student.
	 * @param int $school School.
	 * @param int $page Page.
	 * @param int $filter_class Filter class.
	 */
	public static function assignments( $class = 0, $student = 0, $school = 0, $page = 1, $filter_class = 0 ) {
		global $wpdb;
		$a  = Schema::table( 'learning_assignments' );
		$r  = Schema::table( 'assignment_recipients' );
		$c  = Schema::table( 'classes' );
		$s  = Schema::table( 'schools' );
		$sm = Schema::table( 'school_memberships' );
		$cm = Schema::table( 'class_memberships' );
		if ( $class ) {
			Access::require_access( Access::classroom( $class ) );
			$scope = $wpdb->prepare( 'a.class_id=%d', $class );
		} else {
			$student = $student ? $student : get_current_user_id();
			Access::require_access( $student === get_current_user_id() || ( $school && Access::guardian( $school, $student ) ) );
			$scope = $wpdb->prepare( 'r.student_user_id=%d', $student );
			if ( $school ) {
				$scope .= $wpdb->prepare( ' AND a.school_id=%d', $school ); }
		}
		if ( $filter_class ) {
			$scope .= $wpdb->prepare( ' AND a.class_id=%d', $filter_class ); }
		// Report only distributed school work and current memberships; never global student history.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT a.id,a.title,a.course_id,a.content_id,a.due_at,a.created_at,a.class_id,c.name AS class_name,a.school_id,r.student_user_id,u.display_name AS student_name,r.completed_at FROM $a a JOIN $r r ON r.assignment_id=a.id JOIN $c c ON c.id=a.class_id JOIN $s s ON s.id=a.school_id AND s.status='active' JOIN {$wpdb->users} u ON u.ID=r.student_user_id JOIN $sm sm ON sm.school_id=a.school_id AND sm.user_id=r.student_user_id AND sm.role='student' AND sm.status='active' JOIN $cm cm ON cm.class_id=a.class_id AND cm.user_id=r.student_user_id AND cm.role='student' AND cm.status='active' WHERE a.status='active' AND $scope ORDER BY a.id DESC,r.student_user_id LIMIT 50 OFFSET %d", ( max( 1, $page ) - 1 ) * 50 ), ARRAY_A );
		foreach ( $rows as &$row ) {
			$row['url'] = get_permalink( $row['content_id'] ? $row['content_id'] : $row['course_id'] ); }
		return $rows;
	}
	/**
	 * Course completed for Service.
	 *
	 * @param mixed $student Student.
	 * @param mixed $course Course.
	 */
	public static function course_completed( $student, $course ) {
		self::mark_completed( $student, $course, 0 ); }
	/**
	 * Content completed for Service.
	 *
	 * @param mixed $content Content.
	 * @param mixed $course Course.
	 * @param mixed $student Student.
	 */
	public static function content_completed( $content, $course, $student ) {
		self::mark_completed( $student, $course, $content ); }
	/**
	 * Mark completed for Service.
	 *
	 * @param mixed $student Student.
	 * @param mixed $course Course.
	 * @param mixed $content Content.
	 */
	private static function mark_completed( $student, $course, $content ) {
		global $wpdb;
		$a = Schema::table( 'learning_assignments' );
		$r = Schema::table( 'assignment_recipients' );
		// Re-check actual LMS state instead of trusting event arguments from integrations.
		$person = new \OhMyLMS\Data\Student( $student );
		if ( ! ( $content ? $person->maybe_completed( $content ) : $person->is_course_completed( $course ) ) ) {
			return; }
		$wpdb->query( $wpdb->prepare( 'UPDATE %i r JOIN %i a ON a.id=r.assignment_id SET r.completed_at=%s WHERE r.student_user_id=%d AND a.course_id=%d AND a.content_id=%d AND a.status=\'active\' AND r.completed_at IS NULL', $r, $a, self::now(), $student, $course, $content ) );
	}
	/**
	 * Report for Service.
	 *
	 * @param mixed $school School.
	 */
	public static function report( $school ) {
		global $wpdb;
		Access::require_access( Access::school( $school ) );
		$a = Schema::table( 'learning_assignments' );
		$r = Schema::table( 'assignment_recipients' );
		$c = Schema::table( 'classes' );
		return $wpdb->get_results( $wpdb->prepare( 'SELECT c.id,c.name,c.status,COUNT(r.id) AS assigned,SUM(CASE WHEN r.completed_at IS NOT NULL THEN 1 ELSE 0 END) AS completed FROM %i c LEFT JOIN %i a ON a.class_id=c.id AND a.status=\'active\' LEFT JOIN %i r ON r.assignment_id=a.id WHERE c.school_id=%d GROUP BY c.id,c.name,c.status ORDER BY c.name LIMIT 200', $c, $a, $r, $school ), ARRAY_A );
	}
	/** Limit grading to an explicitly distributed activity, current roster, and its submission.
	 *
	 * @param mixed $learning_id Learning identifier.
	 * @param mixed $attempt_id Attempt identifier.
	 */
	public static function submission_scope( $learning_id, $attempt_id ) {
		$learning = self::row( 'learning_assignments', $learning_id );
		Access::require_access( $learning && 'active' === $learning['status'] && Access::classroom( $learning['class_id'], true ) );
		$attempt = self::row( 'assignment_attempts', $attempt_id );
		Access::require_access( $attempt && (int) $attempt['assignment_id'] === (int) $learning['content_id'] && (int) $attempt['course_id'] === (int) $learning['course_id'] );
		global $wpdb;
		$r        = Schema::table( 'assignment_recipients' );
		$m        = Schema::table( 'class_memberships' );
		$assigned = $wpdb->get_var( $wpdb->prepare( 'SELECT r.assigned_at FROM %i r JOIN %i m ON m.user_id=r.student_user_id AND m.class_id=%d AND m.role=\'student\' AND m.status=\'active\' WHERE r.assignment_id=%d AND r.student_user_id=%d', $r, $m, $learning['class_id'], $learning_id, $attempt['user_id'] ) );
		Access::require_access( $assigned && Access::student( $learning['school_id'], $attempt['user_id'] ) );
		Access::require_access( $learning['prior_completion'] || get_gmt_from_date( $attempt['start_date'] ) >= $assigned );
		return array( $learning, $attempt );
	}
	/**
	 * Page through reviewable class submissions, rechecking every attempt's scope.
	 *
	 * @param int  $class Class ID.
	 * @param int  $page Page number.
	 * @param bool $has_more Whether another candidate page exists.
	 * @return array
	 */
	public static function submissions( $class, $page = 1, &$has_more = null ) {
		global $wpdb;
		$class_row = Access::classroom( $class, true );
		Access::require_access( $class_row );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Current relationship-scoped candidates; each result is authorized again below.
		$rows     = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.id AS learning_id,t.id FROM %i a JOIN %i r ON r.assignment_id=a.id
				JOIN %i t ON t.assignment_id=a.content_id AND t.course_id=a.course_id AND t.user_id=r.student_user_id
				JOIN %i u ON u.ID=t.user_id
				JOIN %i cm ON cm.class_id=a.class_id AND cm.user_id=t.user_id AND cm.role='student' AND cm.status='active'
				JOIN %i sm ON sm.school_id=a.school_id AND sm.user_id=t.user_id AND sm.role='student' AND sm.status='active'
				WHERE a.class_id=%d AND a.school_id=%d AND a.status='active' ORDER BY t.id DESC,a.id DESC LIMIT 101 OFFSET %d",
				Schema::table( 'learning_assignments' ),
				Schema::table( 'assignment_recipients' ),
				Schema::table( 'assignment_attempts' ),
				$wpdb->users,
				Schema::table( 'class_memberships' ),
				Schema::table( 'school_memberships' ),
				$class,
				$class_row['school_id'],
				( min( 10000, max( 1, absint( $page ) ) ) - 1 ) * 100
			),
			ARRAY_A
		);
		$has_more = count( $rows ) > 100;
		$rows     = array_slice( $rows, 0, 100 );
		$result   = array();
		foreach ( $rows as $row ) {
			try {
				[$learning, $attempt] = self::submission_scope( $row['learning_id'], $row['id'] ); } catch ( \RuntimeException $error ) {
				continue; }
				$files    = maybe_unserialize( $attempt['files'] );
				$result[] = array(
					'id'           => $attempt['id'],
					'learning_id'  => $learning['id'],
					'title'        => $learning['title'],
					'student_name' => get_userdata( $attempt['user_id'] )->display_name,
					'content'      => wp_strip_all_tags( $attempt['content'] ),
					'score'        => $attempt['score'],
					'status'       => $attempt['status'],
					'note'         => wp_strip_all_tags( $attempt['note'] ?? '' ),
					'total_points' => (int) get_post_meta( $attempt['assignment_id'], '_total_points', true ),
					'file_url'     => is_array( $files ) && ! empty( $files['file'] ) ? add_query_arg(
						array(
							'ohmylms_school_file' => $attempt['id'],
							'learning_id'         => $learning['id'],
							'_wpnonce'            => wp_create_nonce( 'ohmylms_school_file' ),
						),
						home_url( '/' )
					) : '',
				);
		}
		return $result;
	}
	/**
	 * Grade for Service.
	 *
	 * @param mixed $learning_id Learning identifier.
	 * @param mixed $attempt_id Attempt identifier.
	 * @param mixed $data Input data.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	public static function grade( $learning_id, $attempt_id, $data ) {
		[$learning, $attempt] = self::submission_scope( $learning_id, $attempt_id );
		$total                = (int) get_post_meta( $attempt['assignment_id'], '_total_points', true );
		$score                = $data['score'] ?? null;
		self::need( is_numeric( $score ) && (float) $score === (float) (int) $score && 0 <= $score && $score <= $total, 'Score must be a whole number between zero and the total points.' );
		// Reuse the existing grading/completion pipeline with only the verified attempt ID.
		$request = new \WP_REST_Request( 'POST' );
		$request->set_url_params(
			array(
				'id'      => (int) $attempt['assignment_id'],
				'user_id' => (int) $attempt['user_id'],
			)
		);
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					array(
						'id'     => (int) $attempt_id,
						'score'  => (int) $score,
						'status' => 'reviewed',
						'note'   => self::text( $data['note'] ?? '', 2000 ),
					),
				)
			)
		);
		$result = ( new \OhMyLMS\Rest\V1\AssignmentController() )->update_attempt_report( $request );
		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException( 'Unable to grade this submission.', 400 ); }
		self::content_completed( $attempt['assignment_id'], $attempt['course_id'], $attempt['user_id'] );
		self::audit( $learning['school_id'], 'submission_graded', $attempt_id );
		return array( 'success' => true );
	}
	/**
	 * Archive class for Service.
	 *
	 * @param mixed $id Id.
	 */
	public static function archive_class( $id ) {
		$class = self::row( 'classes', $id );
		Access::require_access( $class && ( (int) $class['school_id'] ? Access::school( $class['school_id'] ) : Access::classroom( $id, true ) ) );
		self::update( 'classes', array( 'status' => 'archived' ), array( 'id' => $id ) );
		self::audit( $class['school_id'], 'class_archived', $id );
		return array( 'success' => true );
	}
	/**
	 * Rollover for Service.
	 *
	 * @param mixed $school School.
	 * @param mixed $data Input data.
	 */
	public static function rollover( $school, $data ) {
		global $wpdb;
		Access::require_access( Access::school( $school ) );
		$from = self::row( 'academic_years', absint( $data['from_year'] ?? 0 ) );
		$to   = self::row( 'academic_years', absint( $data['to_year'] ?? 0 ) );
		self::need( $from && $to && $from['id'] !== $to['id'] && (int) $from['school_id'] === $school && (int) $to['school_id'] === $school && 'active' === $from['status'] && 'active' === $to['status'], 'Select two different active years in this school.' );
		return self::transaction(
			function () use ( $school, $from, $to, $wpdb, $data ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Lock both years in ID order to serialize concurrent rollovers and class creation.
				$wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE id IN (%d,%d) ORDER BY id FOR UPDATE', Schema::table( 'academic_years' ), $from['id'], $to['id'] ) );
				Access::require_access( Access::school( $school ) );
				$target = self::row( 'academic_years', $to['id'] );
				self::need( $target && 'active' === $target['status'], 'The destination year is no longer active.' );
				$years = Schema::table( 'academic_years' );
				// Serialize rollover; repeated requests must never duplicate classes.
				$status = $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM %i WHERE id=%d FOR UPDATE', $years, $from['id'] ) );
				self::need( 'active' === $status, 'This year has already been archived.' );
				$c       = Schema::table( 'classes' );
				$classes = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE academic_year_id=%d AND school_id=%d AND status=\'active\' ORDER BY id FOR UPDATE', $c, $from['id'], $school ), ARRAY_A );
				if ( isset( $data['preview_hash'] ) ) {
					self::need( hash_equals( $data['preview_hash'], Lifecycle::signature( self::row( 'academic_years', $from['id'] ), self::row( 'academic_years', $to['id'] ), $classes ) ), 'The academic years or source classes changed. Preview the rollover again.' );
				}
				foreach ( $classes as $class ) {
					// New year starts with empty rosters; grade promotion is an explicit staff decision.
					self::insert(
						'classes',
						array(
							'school_id'        => $school,
							'academic_year_id' => $to['id'],
							'name'             => $class['name'],
							'subject'          => $class['subject'],
							'grade'            => $class['grade'],
						)
					);
					self::update( 'classes', array( 'status' => 'archived' ), array( 'id' => $class['id'] ) );
				}
				self::update( 'academic_years', array( 'status' => 'archived' ), array( 'id' => $from['id'] ) );
				self::audit( $school, 'year_rolled_over', $from['id'] );
				return array( 'classes_created' => count( $classes ) );
			}
		);
	}
	/**
	 * Import roster for Service.
	 *
	 * @param mixed $school School.
	 * @param mixed $data Input data.
	 */
	public static function import_roster( $school, $data ) {
		global $wpdb;
		Access::require_access( Access::school( $school ) );
		$csv = (string) ( $data['csv'] ?? '' );
		self::need( strlen( $csv ) <= 100000, 'CSV must be smaller than 100 KB.' );
		$preview_key = 'ohmylms_roster_preview_' . hash( 'sha256', get_current_user_id() . ':' . $school . ':' . $csv );
		if ( ! empty( $data['commit'] ) ) {
			self::need( get_transient( $preview_key ), 'Preview this exact CSV before importing it.' ); }
		$stream = fopen( 'php://temp', 'r+' );
		fwrite( $stream, $csv );
		rewind( $stream );
		$headers = fgetcsv( $stream );
		self::need( $headers && in_array( 'name', $headers, true ) && in_array( 'external_student_id', $headers, true ), 'CSV headers must include name and external_student_id.' );
		$results = array();
		$seen    = array();
		$line    = 1;
		while ( ( $values = fgetcsv( $stream ) ) !== false ) {
			++$line;
			self::need( 201 >= $line, 'Import up to 200 students at a time.' );
			if ( $values === array( null ) ) {
				continue; }
			$result = array( 'line' => $line );
			try {
				self::need( count( $headers ) === count( $values ), 'Column count does not match the header.' );
				$row      = array_combine( $headers, $values );
				$name     = self::text( $row['name'] );
				$external = self::text( $row['external_student_id'], 100 );
				self::need( $name && $external && ! isset( $seen[ $external ] ), 'Missing name/student ID or repeated student ID in this file.' );
				$seen[ $external ] = true;
				$table             = Schema::table( 'school_student_profiles' );
				$existing          = $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM %i WHERE school_id=%d AND external_student_id=%s', $table, $school, $external ) );
				$result           += array(
					'name'                => $name,
					'external_student_id' => $external,
					'status'              => $existing ? 'existing' : 'new',
				);
				if ( ! empty( $data['commit'] ) && ! $existing ) {
					$created           = self::create_student( $school, $row );
					$result['status']  = 'created';
					$result['user_id'] = $created['id']; }
			} catch ( \Throwable $error ) {
				$result['status'] = 'error';
				$result['error']  = $error->getMessage(); }
			$results[] = $result;
		}
		fclose( $stream );
		if ( empty( $data['commit'] ) ) {
			set_transient( $preview_key, 1, 15 * MINUTE_IN_SECONDS ); } else {
			delete_transient( $preview_key );
			self::audit( $school, 'roster_imported' ); }
			return $results;
	}
}
