<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * AccountHubRosterController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Schools\Directory;
use OhMyLMS\Schools\Lifecycle;
use OhMyLMS\Schools\RosterImport;
use OhMyLMS\Schools\Service;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * School roster intake under `ohmylms/v1/account-hub/schools/{id}`: invitations, school-managed
 * student accounts (no email) and a reviewed CSV import of students and teachers. See
 * docs/api/account-hub.md. Scope and permissions are the Account Hub's: platform administrators,
 * or school administrators for their own active schools. Writes go through `Schools\Service` and
 * `Schools\Lifecycle`, which re-check the relationship.
 *
 * @since 1.0.0
 */
class AccountHubRosterController extends AccountHubController {

	/**
	 * Largest CSV accepted, in bytes.
	 */
	const MAX_CSV_BYTES = 100000;

	/**
	 * Most data rows in one CSV.
	 */
	const MAX_CSV_ROWS = 200;

	/**
	 * Roles a roster invitation can grant (guardians use the parents routes, activation the setup link).
	 */
	const INVITE_ROLES = array( 'school_admin', 'teacher', 'student' );

	/**
	 * Columns the CSV import understands.
	 */
	const CSV_COLUMNS = RosterImport::COLUMNS;

	/**
	 * Register the routes.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		$base = '/' . $this->rest_base . '/schools/(?P<id>[\d]+)';
		$id   = array(
			'description' => __( 'Unique identifier.', 'ohmylms' ),
			'type'        => 'integer',
			'minimum'     => 1,
		);
		$read = array( $this, 'read_permissions_check' );
		$edit = array( $this, 'edit_permissions_check' );

		register_rest_route(
			$this->namespace,
			$base . '/invitations',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_invitations' ),
					'permission_callback' => $read,
					'args'                => $this->collection_args(
						array(
							'status' => array(
								'description' => __( 'pending: open invitations only; all: every invitation.', 'ohmylms' ),
								'type'        => 'string',
								'default'     => 'pending',
								'enum'        => array( 'pending', 'all' ),
							),
						)
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_invitation' ),
					'permission_callback' => $edit,
					'args'                => array(
						'role'       => array(
							'description' => __( 'Role the invitation grants.', 'ohmylms' ),
							'type'        => 'string',
							'enum'        => self::INVITE_ROLES,
							'required'    => true,
						),
						'email'      => array(
							'description' => __( 'Recipient email address.', 'ohmylms' ),
							'type'        => 'string',
							'format'      => 'email',
							'maxLength'   => 190,
							'required'    => true,
						),
						'class_id'   => array(
							'description' => __( 'Optional active class of this school to join on acceptance (teachers and students).', 'ohmylms' ),
							'type'        => 'integer',
							'minimum'     => 1,
						),
						'send_email' => array(
							'description' => __( 'Also email the invitation link.', 'ohmylms' ),
							'type'        => 'boolean',
							'default'     => false,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/invitations/(?P<invitation>[\d]+)',
			array(
				'args' => array(
					'id'         => $id,
					'invitation' => $id,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_invitation' ),
					'permission_callback' => $edit,
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/managed-students',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_managed_students' ),
					'permission_callback' => $read,
					'args'                => $this->collection_args(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_managed_student' ),
					'permission_callback' => $edit,
					'args'                => array(
						'name'                => array(
							'description' => __( 'Student display name.', 'ohmylms' ),
							'type'        => 'string',
							'minLength'   => 1,
							'maxLength'   => 190,
							'required'    => true,
						),
						'external_student_id' => array(
							'description' => __( 'School student ID, unique in the school.', 'ohmylms' ),
							'type'        => 'string',
							'minLength'   => 1,
							'maxLength'   => 100,
							'required'    => true,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/managed-students/(?P<user_id>[\d]+)/setup-link',
			array(
				'args' => array(
					'id'      => $id,
					'user_id' => $id,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_setup_link' ),
					'permission_callback' => $edit,
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/roster-import',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'import_roster' ),
					'permission_callback' => $edit,
					'args'                => array(
						'csv'        => array(
							'description' => __( 'CSV text with a header row: role, name, email, external_student_id.', 'ohmylms' ),
							'type'        => 'string',
							'minLength'   => 1,
							'maxLength'   => self::MAX_CSV_BYTES,
							'required'    => true,
						),
						'dry_run'    => array(
							'description' => __( 'Review without writing (default). Apply with false and the review token.', 'ohmylms' ),
							'type'        => 'boolean',
							'default'     => true,
						),
						'review'     => array(
							'description' => __( 'Review token from the dry run; required to apply.', 'ohmylms' ),
							'type'        => 'string',
							'pattern'     => '^[a-f0-9]{64}$',
						),
						'send_email' => array(
							'description' => __( 'Email the invitations created by the import.', 'ohmylms' ),
							'type'        => 'boolean',
							'default'     => false,
						),
					),
				),
			)
		);
	}

	/*
	 * Shared helpers.
	 */

	/**
	 * Mark a response as private: it may carry single-use links or school identifiers.
	 *
	 * @param WP_REST_Response $response Response.
	 * @return WP_REST_Response
	 */
	private function no_store( WP_REST_Response $response ) {
		$response->header( 'Cache-Control', 'private, no-store' );

		return $response;
	}

	/**
	 * Masked email (`a***@example.com`) for people without `list_users`.
	 *
	 * @param string $email Email.
	 * @return string
	 */
	public static function mask_email( $email ) {
		$email = (string) $email;
		$at    = strrpos( $email, '@' );
		if ( false === $at || 0 === $at ) {
			return '' === $email ? '' : '***';
		}

		return mb_substr( $email, 0, 1 ) . '***' . substr( $email, $at );
	}

	/**
	 * Load a school the caller manages that is still active (inactive schools are read only).
	 *
	 * @param int $id School id.
	 * @return array|WP_Error
	 */
	private function active_school_or_error( $id ) {
		$school = $this->school_or_error( $id );
		if ( is_wp_error( $school ) ) {
			return $school;
		}
		if ( 'active' !== $school['status'] ) {
			return $this->error( 'school_inactive', __( 'This school is not active.', 'ohmylms' ), 409 );
		}

		return $school;
	}

	/*
	 * Invitations.
	 */

	/**
	 * List a school's invitations, newest first. Tokens are never returned.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_invitations( $request ) {
		$school = $this->school_or_error( $request['id'] );
		if ( is_wp_error( $school ) ) {
			return $school;
		}
		$like    = $this->like( $request );
		$pending = 'pending' === $request['status'];
		$total   = Directory::count_invitations( (int) $school['id'], $like, $pending );
		$paging  = $this->paging( $request, $total );
		if ( is_wp_error( $paging ) ) {
			return $paging;
		}
		list( $page, $per_page, $pages ) = $paging;
		$rows                            = Directory::invitations( (int) $school['id'], $like, $pending, $per_page, ( $page - 1 ) * $per_page );

		return $this->no_store( $this->collection( array_map( array( $this, 'prepare_invitation' ), $rows ), $total, $pages ) );
	}

	/**
	 * Shape an invitation row.
	 *
	 * @param array $row Database row.
	 * @return array
	 */
	public function prepare_invitation( array $row ) {
		if ( $row['consumed_at'] ) {
			$status = 'accepted';
		} elseif ( $row['revoked_at'] ) {
			$status = 'revoked';
		} elseif ( (string) $row['expires_at'] <= Service::now() ) {
			$status = 'expired';
		} else {
			$status = 'pending';
		}

		return array(
			'id'           => (int) $row['id'],
			'role'         => (string) $row['role'],
			'email'        => $this->can_see_email() && '' !== (string) $row['email'] ? (string) $row['email'] : null,
			'email_hint'   => self::mask_email( $row['email'] ),
			'class_id'     => (int) $row['class_id'] ? (int) $row['class_id'] : null,
			'class_name'   => (int) $row['class_id'] && null !== $row['class_name'] ? (string) $row['class_name'] : null,
			'student_id'   => (int) $row['student_user_id'] ? (int) $row['student_user_id'] : null,
			'student_name' => (int) $row['student_user_id'] && null !== $row['student_name'] ? (string) $row['student_name'] : null,
			'status'       => $status,
			'expires_at'   => $this->iso( $row['expires_at'] ),
			'created_at'   => $this->iso( $row['created_at'] ),
		);
	}

	/**
	 * Create a single-use roster invitation. The link is returned once.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_invitation( $request ) {
		$school = $this->active_school_or_error( $request['id'] );
		if ( is_wp_error( $school ) ) {
			return $school;
		}
		$role  = (string) $request['role'];
		$email = sanitize_email( (string) $request['email'] );
		$class = (int) $request['class_id'];
		if ( ! is_email( $email ) ) {
			return $this->error( 'invalid_email', __( 'Enter a valid email address.', 'ohmylms' ), 422 );
		}
		if ( $class ) {
			$row = Service::row( 'classes', $class );
			if ( ! $row || (int) $row['school_id'] !== (int) $school['id'] || 'active' !== $row['status'] || 'school_admin' === $role ) {
				return $this->error( 'invalid_class', __( 'Choose an active class of this school, for teacher and student invitations only.', 'ohmylms' ), 422 );
			}
		}
		if ( RosterImport::on_roster( (int) $school['id'], $email, $role ) ) {
			return $this->error( 'already_member', __( 'This person is already on the roster in that role.', 'ohmylms' ), 409 );
		}
		$pending = Directory::pending_invitation( (int) $school['id'], $email, $role );
		if ( $pending ) {
			return $this->error( 'invitation_pending', __( 'An invitation for this email and role is already waiting. Revoke it to create a new one.', 'ohmylms' ), 409, array( 'invitation' => $pending ) );
		}
		try {
			$invite = Service::invite(
				(int) $school['id'],
				array(
					'role'       => $role,
					'email'      => $email,
					'class_id'   => $class,
					'send_email' => (bool) $request['send_email'],
				)
			);
		} catch ( \Throwable $failure ) {
			return $this->service_error( $failure );
		}
		$row = Service::row( 'school_invitations', (int) $invite['id'] );

		return $this->no_store(
			$this->created(
				array(
					'id'         => (int) $invite['id'],
					'role'       => $role,
					'url'        => (string) $invite['url'],
					'expires_at' => $this->iso( $row['expires_at'] ?? '' ),
					'email_sent' => (bool) $invite['email_sent'],
				),
				'/schools/' . (int) $school['id'] . '/invitations'
			)
		);
	}

	/**
	 * Revoke an open invitation of the school (it stays in the list as revoked).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_invitation( $request ) {
		$school = $this->school_or_error( $request['id'] );
		if ( is_wp_error( $school ) ) {
			return $school;
		}
		$invite = Service::row( 'school_invitations', (int) $request['invitation'] );
		if ( ! $invite || (int) $invite['school_id'] !== (int) $school['id'] || $invite['consumed_at'] || $invite['revoked_at'] || $invite['expires_at'] <= Service::now() ) {
			return $this->error( 'invitation_not_found', __( 'Invitation not found.', 'ohmylms' ), 404 );
		}
		try {
			Service::revoke_invite( (int) $school['id'], (int) $invite['id'] );
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

	/*
	 * Managed students.
	 */

	/**
	 * List the school-managed student accounts (no email), by name.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_managed_students( $request ) {
		$school = $this->school_or_error( $request['id'] );
		if ( is_wp_error( $school ) ) {
			return $school;
		}
		$like   = $this->like( $request );
		$total  = Directory::count_managed_students( (int) $school['id'], $like );
		$paging = $this->paging( $request, $total );
		if ( is_wp_error( $paging ) ) {
			return $paging;
		}
		list( $page, $per_page, $pages ) = $paging;
		$rows                            = Directory::managed_students( (int) $school['id'], $like, $per_page, ( $page - 1 ) * $per_page );
		$setup                           = Directory::setup_link_expiries( (int) $school['id'], array_map( 'intval', wp_list_pluck( $rows, 'id' ) ) );
		$data                            = array();
		foreach ( $rows as $row ) {
			$data[] = $this->prepare_managed_student( $row, $setup[ (int) $row['id'] ] ?? '' );
		}

		return $this->no_store( $this->collection( $data, $total, $pages ) );
	}

	/**
	 * Shape a managed student.
	 *
	 * @param array  $row   Row with id, display_name, user_login, external_student_id, joined_at.
	 * @param string $setup Expiry of an open password-setup link, or ''.
	 * @return array
	 */
	public function prepare_managed_student( array $row, $setup = '' ) {
		return array(
			'id'                  => (int) $row['id'],
			'display_name'        => (string) $row['display_name'],
			'username'            => (string) $row['user_login'],
			'external_student_id' => (string) $row['external_student_id'],
			'joined_at'           => $this->iso( $row['joined_at'] ?? '' ),
			'setup_expires_at'    => '' === $setup ? null : $this->iso( $setup ),
		);
	}

	/**
	 * Create a school-managed student account (no email) on the roster.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_managed_student( $request ) {
		$school = $this->active_school_or_error( $request['id'] );
		if ( is_wp_error( $school ) ) {
			return $school;
		}
		$name     = $this->clean( $request['name'] );
		$external = $this->clean( $request['external_student_id'], 100 );
		if ( '' === $name || '' === $external ) {
			return $this->error( 'invalid_student', __( 'Enter the student\'s name and school student ID.', 'ohmylms' ), 422 );
		}
		if ( Directory::student_by_external_id( (int) $school['id'], $external ) ) {
			return $this->error( 'duplicate_student_id', __( 'This school student ID is already on the roster.', 'ohmylms' ), 409 );
		}
		try {
			$created = Service::create_student(
				(int) $school['id'],
				array(
					'name'                => $name,
					'external_student_id' => $external,
				)
			);
		} catch ( \Throwable $failure ) {
			return $this->service_error( $failure );
		}
		$user = get_userdata( (int) $created['id'] );

		return $this->no_store(
			$this->created(
				$this->prepare_managed_student(
					array(
						'id'                  => (int) $created['id'],
						'display_name'        => $user ? $user->display_name : $name,
						'user_login'          => (string) $created['username'],
						'external_student_id' => $external,
						'joined_at'           => Service::now(),
					)
				),
				'/schools/' . (int) $school['id'] . '/managed-students'
			)
		);
	}

	/**
	 * Replace a managed student's password-setup link (earlier unused links are revoked).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_setup_link( $request ) {
		$school = $this->active_school_or_error( $request['id'] );
		if ( is_wp_error( $school ) ) {
			return $school;
		}
		$student = (int) $request['user_id'];
		if ( ! Lifecycle::managed_student( (int) $school['id'], $student ) ) {
			return $this->error( 'student_not_found', __( 'Managed student not found.', 'ohmylms' ), 404 );
		}
		try {
			$invite = Lifecycle::recovery( (int) $school['id'], $student );
		} catch ( \Throwable $failure ) {
			return $this->service_error( $failure );
		}
		$row = Service::row( 'school_invitations', (int) $invite['id'] );

		return $this->no_store(
			$this->created(
				array(
					'id'         => (int) $invite['id'],
					'student_id' => $student,
					'url'        => (string) $invite['url'],
					'expires_at' => $this->iso( $row['expires_at'] ?? '' ),
				),
				'/schools/' . (int) $school['id'] . '/managed-students'
			)
		);
	}

	/*
	 * CSV import.
	 */

	/**
	 * Parse roster CSV into rows. Structural problems answer one error for the whole file.
	 *
	 * @param string $csv CSV text.
	 * @return array|WP_Error List of `{line, cells}` and the recognised header.
	 */
	public static function parse_csv( $csv ) {
		$csv = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $csv );
		if ( ! mb_check_encoding( $csv, 'UTF-8' ) ) {
			return new WP_Error( 'ohmylms_account_hub_csv_invalid', __( 'Save the CSV as UTF-8 and try again.', 'ohmylms' ), array( 'status' => 422 ) );
		}
		$stream = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- In-memory stream.
		fwrite( $stream, $csv ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- In-memory stream.
		rewind( $stream );
		$header = fgetcsv( $stream, 0, ',', '"', '' );
		$header = is_array( $header ) ? array_map( static fn( $cell ) => strtolower( trim( (string) $cell ) ), $header ) : array();
		$header = array_map( static fn( $cell ) => 'student_id' === $cell ? 'external_student_id' : $cell, $header );
		if ( ! in_array( 'name', $header, true ) ) {
			fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- In-memory stream.
			return new WP_Error( 'ohmylms_account_hub_csv_invalid', __( 'The first row must be a header with a name column (and role, email, external_student_id as needed).', 'ohmylms' ), array( 'status' => 422 ) );
		}
		if ( count( array_unique( $header ) ) !== count( $header ) ) {
			fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- In-memory stream.
			return new WP_Error( 'ohmylms_account_hub_csv_invalid', __( 'Each column may appear only once in the header.', 'ohmylms' ), array( 'status' => 422 ) );
		}
		$rows = array();
		$line = 1;
		while ( false !== ( $values = fgetcsv( $stream, 0, ',', '"', '' ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition, Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- Stream reader.
			++$line;
			if ( array( null ) === $values || '' === trim( implode( '', $values ) ) ) {
				continue;
			}
			if ( count( $rows ) >= self::MAX_CSV_ROWS ) {
				fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- In-memory stream.
				/* translators: %d: maximum rows. */
				return new WP_Error( 'ohmylms_account_hub_csv_too_long', sprintf( __( 'Import up to %d people at a time.', 'ohmylms' ), self::MAX_CSV_ROWS ), array( 'status' => 422 ) );
			}
			$cells = array();
			foreach ( $header as $index => $column ) {
				$cells[ $column ] = isset( $values[ $index ] ) ? trim( (string) $values[ $index ] ) : '';
			}
			$rows[] = array(
				'line'  => $line,
				'cells' => $cells,
				'extra' => count( $values ) > count( $header ),
			);
		}
		fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- In-memory stream.
		if ( ! $rows ) {
			return new WP_Error( 'ohmylms_account_hub_csv_empty', __( 'The CSV has no people below the header.', 'ohmylms' ), array( 'status' => 422 ) );
		}

		return array(
			'header' => $header,
			'rows'   => $rows,
		);
	}

	/**
	 * Review (dry run) or apply a roster CSV through `Schools\RosterImport`. Applying needs the review token
	 * of an unchanged plan; applying the same file again creates nothing (existing IDs, members and open
	 * invitations are skipped).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function import_roster( $request ) {
		$school = $this->active_school_or_error( $request['id'] );
		if ( is_wp_error( $school ) ) {
			return $school;
		}
		$school_id = (int) $school['id'];
		$parsed    = self::parse_csv( (string) $request['csv'] );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}
		$dry_run = false !== $request['dry_run'];
		if ( $dry_run ) {
			$plan = RosterImport::plan( $school_id, $parsed );

			return $this->no_store( rest_ensure_response( $this->import_report( $plan, true, RosterImport::review_token( $school_id, $plan ) ) ) );
		}
		if ( ! is_string( $request['review'] ) || '' === $request['review'] ) {
			return $this->error( 'review_required', __( 'Review this file before importing it.', 'ohmylms' ), 400 );
		}
		$plan = RosterImport::apply( $school_id, $parsed, $request['review'], (bool) $request['send_email'] );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}

		return $this->no_store( rest_ensure_response( $this->import_report( $plan, false, null ) ) );
	}

	/**
	 * Import response body.
	 *
	 * @param array       $plan    Plan or applied plan.
	 * @param bool        $dry_run Whether nothing was written.
	 * @param string|null $review  Review token.
	 * @return array
	 */
	private function import_report( array $plan, $dry_run, $review ) {
		return array(
			'dry_run' => $dry_run,
			'review'  => $review,
			'counts'  => $plan['counts'],
			'ignored' => $plan['ignored'],
			'rows'    => array_map(
				static function ( $row ) {
					return array(
						'line'                => (int) $row['line'],
						'role'                => (string) $row['role'],
						'name'                => (string) $row['name'],
						'email'               => (string) $row['email'],
						'external_student_id' => (string) $row['external_student_id'],
						'action'              => (string) $row['action'],
						'error'               => $row['error'],
						'user_id'             => isset( $row['user_id'] ) ? (int) $row['user_id'] : null,
						'username'            => $row['username'] ?? null,
						'url'                 => $row['url'] ?? null,
					);
				},
				$plan['rows']
			),
		);
	}
}
