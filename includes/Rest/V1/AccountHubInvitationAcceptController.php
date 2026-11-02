<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * AccountHubInvitationAcceptController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Schools\Service;
use OhMyLMS\Schools\Signup;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * `POST ohmylms/v1/account-hub/invitations/accept`: the signed-in recipient accepts a school, class or
 * guardian invitation with its code (the learner hub's "Join with an invitation" form). Replaces
 * `POST ohmylms/v1/school/accept`. Any signed-in account may call it; the REST nonce is required and
 * attempts are rate limited. `Service::accept()` checks that the account's email is the invited one, the
 * school is active and the issuer still has permission. Password-setup codes are refused here: they are
 * used on the hub's own password page, so no password ever passes through this route.
 *
 * @since 1.0.0
 */
class AccountHubInvitationAcceptController extends RestController {

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
	protected $rest_base = 'account-hub/invitations/accept';

	/**
	 * Register the route.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'accept' ),
					'permission_callback' => array( $this, 'accept_permissions_check' ),
					'args'                => array(
						'token' => array(
							'description' => __( 'Invitation code (64 hexadecimal characters).', 'ohmylms' ),
							'type'        => 'string',
							'required'    => true,
							'pattern'     => '^[a-f0-9]{64}$',
						),
					),
				),
			)
		);
	}

	/**
	 * Signed-in accounts with a valid REST nonce.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function accept_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'ohmylms_invitation_login', __( 'Sign in with the invited account to accept this invitation.', 'ohmylms' ), array( 'status' => 401 ) );
		}
		if ( ! wp_verify_nonce( (string) $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
			return new WP_Error( 'ohmylms_invitation_nonce', __( 'Refresh the page and try again.', 'ohmylms' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Accept an invitation.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function accept( $request ) {
		$token = (string) $request['token'];
		try {
			Signup::rate_limit( 'school_invite' );
			$role = Service::invite_role( $token );
			if ( 'activation' === $role ) {
				return new WP_Error( 'ohmylms_invitation_password_setup', __( 'This is a password setup link. Open it in your browser to choose a password.', 'ohmylms' ), array( 'status' => 422 ) );
			}
			$result = Service::accept( $token );
		} catch ( \Throwable $failure ) {
			$code = $failure instanceof \RuntimeException ? (int) $failure->getCode() : 500;
			if ( 429 === $code ) {
				return new WP_Error( 'ohmylms_invitation_rate_limited', $failure->getMessage(), array( 'status' => 429 ) );
			}
			if ( in_array( $code, array( 400, 403 ), true ) ) {
				return new WP_Error( 'ohmylms_invitation_rejected', $failure->getMessage(), array( 'status' => 422 ) );
			}

			return new WP_Error( 'ohmylms_invitation_failed', __( 'Unable to accept the invitation. Please try again.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		$response = rest_ensure_response(
			array(
				'accepted' => true,
				'role'     => $role,
				'message'  => (string) $result['message'],
			)
		);
		$response->header( 'Cache-Control', 'private, no-store' );

		return $response;
	}
}
