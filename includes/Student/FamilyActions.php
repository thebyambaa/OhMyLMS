<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping.
/**
 * Native parent forms for managed children and the child's credential-setup page.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use OhMyLMS\Family\Schema;
use OhMyLMS\Family\Service as Family;

defined( 'ABSPATH' ) || exit;

/** Every mutation rechecks the signed-in guardian; the setup page needs only its token. */
final class FamilyActions {

	const ACTION       = 'ohmylms_family';
	const SETUP_ACTION = 'ohmylms_family_setup';
	const INVITE       = 'ohmylms_family_invite';
	const FLASH        = 'ohmylms_family_flash_';

	/** Register form handlers and the private setup-page headers. */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_' . self::SETUP_ACTION, array( __CLASS__, 'handle_setup' ) );
		add_action( 'admin_post_nopriv_' . self::SETUP_ACTION, array( __CLASS__, 'handle_setup' ) );
		add_action( 'admin_post_' . self::INVITE, array( __CLASS__, 'handle_invite' ) );
		add_action( 'template_redirect', array( __CLASS__, 'private_headers' ), 2 );
	}

	/** Keep setup tokens out of caches and referrers. */
	public static function private_headers() {
		if ( isset( $_GET['ohmylms_child_setup'] ) || isset( $_GET['ohmylms_family_invite'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presence check only.
			nocache_headers();
			header( 'Referrer-Policy: no-referrer' );
			header( 'X-Robots-Tag: noindex' );
		}
	}

	/**
	 * Hidden fields for a signed-in family operation.
	 *
	 * @param string $operation Operation name.
	 */
	public static function fields( $operation ) {
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		echo '<input type="hidden" name="operation" value="' . esc_attr( $operation ) . '">';
		Hub::nonce_field( self::ACTION . '_' . $operation );
	}

	/**
	 * Apply an operation as the current guardian; callable by integration tests.
	 *
	 * @param string $operation Operation name.
	 * @param array  $data Scalar form fields.
	 * @return array Flash data.
	 * @throws \RuntimeException For an unknown operation or an unauthorized request.
	 */
	public static function apply( $operation, array $data ) {
		switch ( $operation ) {
			case 'create_child':
				$created = Family::create_child( $data['name'] ?? '', $data['grade'] ?? '' );
				$setup   = Family::issue_setup( $created['child_id'] );
				return array(
					'message'   => __( 'Child added. Share the setup link privately so they can choose a password. No email was sent.', 'ohmylms' ),
					'setup_url' => self::setup_url( $setup['token'] ),
				);
			case 'issue_setup':
				if ( 'yes' !== ( $data['confirm'] ?? '' ) ) {
					throw new \RuntimeException( 'Confirm replacement of older setup or reset links.', 400 );
				}
				$setup = Family::issue_setup( absint( $data['child_id'] ?? 0 ) );
				return array(
					'message'   => __( 'Private setup/reset link created. It expires in 48 hours. Older links no longer work; the password stays unchanged until your child uses this link. No email was sent.', 'ohmylms' ),
					'setup_url' => self::setup_url( $setup['token'] ),
				);
			case 'invite_existing':
				$invite = Family::invite_existing( $data['email'] ?? '' );
				return array(
					'message'    => __( 'Private invitation created. Share this link only with your child within 48 hours. No email was sent.', 'ohmylms' ),
					'invite_url' => self::invite_url( $invite['token'] ),
				);
			case 'revoke_invitation':
				Family::revoke_invitation( absint( $data['invitation_id'] ?? 0 ) );
				return array( 'message' => __( 'Invitation revoked.', 'ohmylms' ) );
			case 'update_choices':
				Family::set_choices( absint( $data['child_id'] ?? 0 ), $data['grade'] ?? '', array_map( 'absint', (array) ( $data['bundles'] ?? array() ) ) );
				return array( 'message' => __( 'Learning choices saved.', 'ohmylms' ) );
			case 'revoke_link':
				if ( 'yes' !== ( $data['confirm'] ?? '' ) ) {
					throw new \RuntimeException( 'Confirm removal of this link.', 400 );
				}
				Family::revoke_link( absint( $data['link_id'] ?? 0 ) );
				return array( 'message' => __( 'Link removed. The child’s account and learning history were kept.', 'ohmylms' ) );
		}
		throw new \RuntimeException( 'Unknown operation.', 400 );
	}

	/**
	 * Invitation link for a raw token.
	 *
	 * @param string $token Raw token.
	 * @return string
	 */
	public static function invite_url( $token ) {
		return add_query_arg( 'ohmylms_family_invite', $token, Hub::base_url() ? Hub::base_url() : home_url( '/' ) );
	}

	/**
	 * Setup link for a raw token.
	 *
	 * @param string $token Raw token.
	 * @return string
	 */
	public static function setup_url( $token ) {
		return add_query_arg( 'ohmylms_child_setup', $token, Hub::base_url() ? Hub::base_url() : home_url( '/' ) );
	}

	/**
	 * Handle a signed-in guardian form post and return to the family page.
	 *
	 * @throws \RuntimeException Caught locally and shown as a safe message.
	 */
	public static function handle() {
		$operation = isset( $_POST['operation'] ) && is_string( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
		check_admin_referer( self::ACTION . '_' . $operation );
		$flash = array();
		$data  = array();
		try {
			if ( 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
				throw new \RuntimeException( 'Invalid request.', 400 );
			}
			foreach ( array( 'name', 'grade', 'child_id', 'link_id', 'confirm', 'email', 'invitation_id', 'return' ) as $key ) {
				$data[ $key ] = isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
			}
			$data['bundles'] = isset( $_POST['bundles'] ) && is_array( $_POST['bundles'] ) ? array_map( 'absint', wp_unslash( $_POST['bundles'] ) ) : array();
			$flash           = self::apply( $operation, $data );
		} catch ( \Throwable $error ) {
			$safe  = in_array( $error->getCode(), array( 400, 403, 409 ), true );
			$flash = array( 'error' => $safe ? $error->getMessage() : __( 'Unable to complete this request.', 'ohmylms' ) );
		}
		set_transient( self::FLASH . get_current_user_id(), $flash, MINUTE_IN_SECONDS );
		wp_safe_redirect( isset( $data['return'] ) && 'learner' === $data['return'] ? Hub::url( 'classes', 'connections' ) : ParentWorkspace::url() );
		exit;
	}

	/**
	 * One-time result of the last action for this guardian.
	 *
	 * @return array|null
	 */
	public static function flash() {
		$key  = self::FLASH . get_current_user_id();
		$data = get_transient( $key );
		if ( false === $data ) {
			return null;
		}
		delete_transient( $key );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Child sets a password from a setup link; shows the login name once without storing it.
	 *
	 * @throws \RuntimeException Caught locally and shown as a safe message.
	 */
	public static function handle_setup() {
		check_admin_referer( self::SETUP_ACTION );
		$key      = 'ohmylms_family_setup_' . hash_hmac( 'sha256', sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'local' ) ), wp_salt( 'nonce' ) );
		$attempts = (int) get_transient( $key );
		if ( $attempts >= 15 ) {
			wp_die( esc_html__( 'Too many attempts. Please try again later.', 'ohmylms' ), '', array( 'response' => 429 ) );
		}
		set_transient( $key, $attempts + 1, 15 * MINUTE_IN_SECONDS );
		nocache_headers();
		$token    = isset( $_POST['token'] ) && is_string( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$password = isset( $_POST['password'] ) && is_string( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords are never altered.
		try {
			if ( 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) || ! isset( $_POST['password2'] ) || ! hash_equals( $password, (string) wp_unslash( $_POST['password2'] ) ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Compared verbatim.
				throw new \RuntimeException( 'The passwords do not match.', 400 );
			}
			$result = Family::accept_setup( $token, $password );
			/* translators: %s: sign-in name. */
			$message = sprintf( __( 'Your password is set. Your sign-in name is: %s', 'ohmylms' ), $result['login'] );
			wp_die( esc_html( $message ) . ' <a href="' . esc_url( wp_login_url( Hub::base_url() ? Hub::base_url() : home_url( '/' ) ) ) . '">' . esc_html__( 'Sign in', 'ohmylms' ) . '</a>', esc_html__( 'Account ready', 'ohmylms' ), array( 'response' => 200 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Parts escaped above.
		} catch ( \Throwable $error ) {
			$safe = in_array( $error->getCode(), array( 400, 403 ), true ) ? $error->getMessage() : __( 'Unable to complete this request.', 'ohmylms' );
			wp_die( esc_html( $safe ), esc_html__( 'Could not set password', 'ohmylms' ), array( 'response' => 400 ) );
		}
	}

	/**
	 * Learner accepts or declines a guardian invitation; the token is re-validated for this account.
	 *
	 * @throws \RuntimeException Caught locally and shown as a safe message.
	 */
	public static function handle_invite() {
		check_admin_referer( self::INVITE );
		$token = isset( $_POST['token'] ) && is_string( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$what  = isset( $_POST['decision'] ) && is_string( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
		try {
			if ( 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
				throw new \RuntimeException( 'Invalid request.', 400 );
			}
			if ( 'accept' === $what ) {
				Family::accept_link( $token );
				$flash = array( 'message' => __( 'Linked. Your parent can now see that you are connected for home learning.', 'ohmylms' ) );
			} elseif ( 'decline' === $what ) {
				Family::decline( $token );
				$flash = array( 'message' => __( 'Invitation declined.', 'ohmylms' ) );
			} else {
				throw new \RuntimeException( 'Choose accept or decline.', 400 );
			}
		} catch ( \Throwable $error ) {
			$flash = array( 'error' => in_array( $error->getCode(), array( 400, 403, 409 ), true ) ? $error->getMessage() : __( 'Unable to complete this request.', 'ohmylms' ) );
		}
		set_transient( self::FLASH . get_current_user_id(), $flash, MINUTE_IN_SECONDS );
		wp_safe_redirect( Hub::url( 'classes', 'connections' ) );
		exit;
	}

	/**
	 * Whether the family feature is ready.
	 *
	 * @return bool
	 */
	public static function ready() {
		return Schema::enabled();
	}
}
