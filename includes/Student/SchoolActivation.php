<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping.
/**
 * Hub page for school account-activation invitations (a managed student sets a password).
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use OhMyLMS\Schools\Service;

defined( 'ABSPATH' ) || exit;

/** The existing school service validates, authorizes and consumes the token. */
final class SchoolActivation {

	const ACTION = 'ohmylms_school_activate';

	/** Register the handler and private headers for invitation pages. */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'template_redirect', array( __CLASS__, 'private_headers' ), 2 );
	}

	/** Keep invitation tokens out of caches and referrers. */
	public static function private_headers() {
		if ( isset( $_GET['ohmylms_invite'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presence check only.
			nocache_headers();
			header( 'Referrer-Policy: no-referrer' );
			header( 'X-Robots-Tag: noindex' );
		}
	}

	/**
	 * Set the password and consume the invitation; shows the sign-in name once.
	 *
	 * @throws \RuntimeException Caught locally and shown as a safe message.
	 */
	public static function handle() {
		check_admin_referer( self::ACTION );
		$key      = 'ohmylms_school_activate_' . hash_hmac( 'sha256', sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'local' ) ), wp_salt( 'nonce' ) );
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
			if ( 'activation' !== Service::invite_role( $token ) ) {
				throw new \RuntimeException( 'This invitation cannot be used here.', 403 );
			}
			$result = Service::accept( $token, $password );
			/* translators: %s: sign-in name. */
			$message = sprintf( __( 'Your password is set. Your sign-in name is: %s', 'ohmylms' ), $result['username'] );
			wp_die( esc_html( $message ) . ' <a href="' . esc_url( wp_login_url( Hub::base_url() ? Hub::base_url() : home_url( '/' ) ) ) . '">' . esc_html__( 'Sign in', 'ohmylms' ) . '</a>', esc_html__( 'Account ready', 'ohmylms' ), array( 'response' => 200 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Parts escaped above.
		} catch ( \Throwable $error ) {
			$safe = in_array( $error->getCode(), array( 400, 403 ), true ) ? $error->getMessage() : __( 'Unable to complete this request.', 'ohmylms' );
			wp_die( esc_html( $safe ), esc_html__( 'Could not set password', 'ohmylms' ), array( 'response' => 400 ) );
		}
	}
}
