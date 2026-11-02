<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * AccountHubGuardiansController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Schools\Access;
use OhMyLMS\Schools\Guardians;
use OhMyLMS\Schools\Service;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Guardians of a school's learners under `ohmylms/v1/account-hub/schools/{id}` (docs/api/account-hub.md,
 * "Guardians"): one list of active guardian links and open guardian invitations, filtered by class,
 * with invite, link and unlink. Scope and permissions are the Account Hub's. A link is never made from a
 * name or an email alone: inviting and linking both create a single-use invitation that the parent
 * accepts while signed in with that address.
 *
 * @since 1.0.0
 */
class AccountHubGuardiansController extends AccountHubController {

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
		$edit = array( $this, 'edit_permissions_check' );

		register_rest_route(
			$this->namespace,
			$base . '/guardians',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_guardians' ),
					'permission_callback' => array( $this, 'read_permissions_check' ),
					'args'                => $this->collection_args(
						array(
							'status'   => array(
								'description' => __( 'active: linked guardians; pending: open invitations; all: both.', 'ohmylms' ),
								'type'        => 'string',
								'default'     => 'all',
								'enum'        => Guardians::STATUSES,
							),
							'class_id' => array_merge( $id, array( 'description' => __( 'Only guardians of students in this class of the school.', 'ohmylms' ) ) ),
						)
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'invite_guardian' ),
					'permission_callback' => $edit,
					'args'                => array(
						'student_id' => array_merge( $id, array( 'required' => true ) ),
						'parent_id'  => array_merge( $id, array( 'description' => __( 'Existing parent account to link (an invitation to its own email).', 'ohmylms' ) ) ),
						'email'      => array(
							'description' => __( 'Email to invite, when the parent has no account here yet.', 'ohmylms' ),
							'type'        => 'string',
							'format'      => 'email',
							'maxLength'   => 190,
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
			$base . '/guardians/(?P<link>[\d]+)',
			array(
				'args' => array(
					'id'   => $id,
					'link' => $id,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'unlink_guardian' ),
					'permission_callback' => $edit,
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/guardian-invitations/(?P<invitation>[\d]+)',
			array(
				'args' => array(
					'id'         => $id,
					'invitation' => $id,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'revoke_guardian_invitation' ),
					'permission_callback' => $edit,
				),
			)
		);
	}

	/**
	 * Mark a response as private.
	 *
	 * @param WP_REST_Response $response Response.
	 * @return WP_REST_Response
	 */
	private function no_store( WP_REST_Response $response ) {
		$response->header( 'Cache-Control', 'private, no-store' );

		return $response;
	}

	/**
	 * List a school's guardians: active links and open invitations, by student.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_guardians( $request ) {
		$school = $this->school_or_error( $request['id'] );
		if ( is_wp_error( $school ) ) {
			return $school;
		}
		$class_id = (int) $request['class_id'];
		if ( $class_id ) {
			$class = Service::row( 'classes', $class_id );
			if ( ! $class || (int) $class['school_id'] !== (int) $school['id'] ) {
				return $this->error( 'class_not_found', __( 'Class not found.', 'ohmylms' ), 404 );
			}
		}
		$status = (string) $request['status'];
		$like   = $this->like( $request );
		$total  = Guardians::count( (int) $school['id'], $status, $class_id, $like );
		$paging = $this->paging( $request, $total );
		if ( is_wp_error( $paging ) ) {
			return $paging;
		}
		list( $page, $per_page, $pages ) = $paging;
		$rows                            = Guardians::page( (int) $school['id'], $status, $class_id, $like, $per_page, ( $page - 1 ) * $per_page );

		return $this->no_store( $this->collection( array_map( array( $this, 'prepare_guardian' ), $rows ), $total, $pages ) );
	}

	/**
	 * Shape a guardian row: display names only, a masked address for open invitations.
	 *
	 * @param array $row Raw row from `Guardians::page()`.
	 * @return array
	 */
	public function prepare_guardian( array $row ) {
		$link = 'link' === $row['kind'];

		return array(
			'kind'       => $link ? 'link' : 'invitation',
			'id'         => (int) $row['id'],
			'status'     => $link ? 'active' : 'pending',
			'student'    => array(
				'id'           => (int) $row['student_id'],
				'display_name' => (string) $row['student_name'],
			),
			'parent'     => (int) $row['parent_id'] ? array(
				'id'           => (int) $row['parent_id'],
				'display_name' => (string) $row['parent_name'],
			) : null,
			'linked_at'  => $link ? $this->iso( $row['at'] ) : null,
			'expires_at' => $link ? null : $this->iso( $row['at'] ),
			'email_hint' => $link ? null : AccountHubRosterController::mask_email( (string) $row['email'] ),
		);
	}

	/**
	 * Invite a guardian for a student: an existing parent account (`parent_id`) or an email address.
	 * The link becomes active when the parent accepts while signed in with that address.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function invite_guardian( $request ) {
		$school = $this->writable_school_or_error( $request['id'] );
		if ( is_wp_error( $school ) ) {
			return $school;
		}
		$school_id = (int) $school['id'];
		$student   = (int) $request['student_id'];
		if ( ! Access::student( $school_id, $student ) ) {
			return $this->error( 'not_on_roster', __( 'Choose a student on this school\'s roster.', 'ohmylms' ), 422 );
		}
		$parent_id = (int) $request['parent_id'];
		$email     = sanitize_email( (string) $request['email'] );
		if ( ( $parent_id && '' !== $email ) || ( ! $parent_id && '' === $email ) ) {
			return $this->error( 'parent_required', __( 'Choose a parent account or enter one email address.', 'ohmylms' ), 400 );
		}
		if ( $parent_id ) {
			$parent = get_userdata( $parent_id );
			if ( ! $parent ) {
				return $this->error( 'parent_not_found', __( 'Parent not found.', 'ohmylms' ), 404 );
			}
			if ( ! is_email( $parent->user_email ) ) {
				return $this->error( 'parent_without_email', __( 'This parent account has no email address to send the invitation to.', 'ohmylms' ), 422 );
			}
			$email = (string) $parent->user_email;
		} elseif ( ! is_email( $email ) ) {
			return $this->error( 'invalid_email', __( 'Enter a valid email address.', 'ohmylms' ), 422 );
		}
		$account = $parent_id ? get_userdata( $parent_id ) : get_user_by( 'email', $email );
		if ( $account && (int) $account->ID === $student ) {
			return $this->error( 'not_on_roster', __( 'A student cannot be their own guardian.', 'ohmylms' ), 422 );
		}
		if ( $account && Guardians::linked( $school_id, (int) $account->ID, $student ) ) {
			return $this->error( 'already_linked', __( 'This parent is already linked to the student.', 'ohmylms' ), 409 );
		}
		$pending = Guardians::pending( $school_id, $student, $email );
		if ( $pending ) {
			return $this->error( 'invitation_pending', __( 'An invitation for this parent and student is already waiting. Revoke it to create a new one.', 'ohmylms' ), 409, array( 'invitation' => $pending ) );
		}
		try {
			$invite = Service::invite(
				$school_id,
				array(
					'role'            => 'guardian',
					'email'           => $email,
					'student_user_id' => $student,
					'send_email'      => (bool) $request['send_email'],
				)
			);
		} catch ( \Throwable $failure ) {
			return $this->service_error( $failure );
		}
		$row      = Service::row( 'school_invitations', (int) $invite['id'] );
		$response = $this->created(
			array(
				'kind'       => 'invitation',
				'id'         => (int) $invite['id'],
				'school_id'  => $school_id,
				'student_id' => $student,
				'url'        => (string) $invite['url'],
				'expires_at' => $this->iso( $row['expires_at'] ?? '' ),
				'email_sent' => (bool) $invite['email_sent'],
			),
			'/schools/' . $school_id . '/guardians'
		);

		return $this->no_store( $response );
	}

	/**
	 * Revoke an active guardian link of the school (it becomes inactive; nothing is deleted).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function unlink_guardian( $request ) {
		$school = $this->school_or_error( $request['id'] );
		if ( is_wp_error( $school ) ) {
			return $school;
		}
		$link = Guardians::link( (int) $school['id'], (int) $request['link'] );
		if ( ! $link ) {
			return $this->error( 'link_not_found', __( 'Link not found.', 'ohmylms' ), 404 );
		}
		if ( 'active' !== $school['status'] ) {
			return $this->error( 'school_inactive', __( 'This school is archived. Restore it first.', 'ohmylms' ), 409 );
		}
		try {
			Service::revoke_guardian( (int) $school['id'], (int) $link['id'] );
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
	 * Revoke an open guardian invitation of the school.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function revoke_guardian_invitation( $request ) {
		$school = $this->school_or_error( $request['id'] );
		if ( is_wp_error( $school ) ) {
			return $school;
		}
		$invite = Guardians::invitation( (int) $school['id'], (int) $request['invitation'] );
		if ( ! $invite ) {
			return $this->error( 'invitation_not_found', __( 'Invitation not found.', 'ohmylms' ), 404 );
		}
		if ( 'active' !== $school['status'] ) {
			return $this->error( 'school_inactive', __( 'This school is archived. Restore it first.', 'ohmylms' ), 409 );
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
}
