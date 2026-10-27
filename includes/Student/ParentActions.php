<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping.
/**
 * Parent account creation from the hub, reusing the school signup rules.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use OhMyLMS\Schools\Controller;

defined( 'ABSPATH' ) || exit;

/** Creates only a parent account; no child link, enrollment or order is created. */
final class ParentActions {

	const ACTION = 'ohmylms_parent_signup';

	/** Register the signed-out form handler. */
	public static function init() {
		add_action( 'admin_post_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/** Verify the nonce, create the account and return to the sign-up page with a result code. */
	public static function handle() {
		check_admin_referer( self::ACTION );
		$base = Hub::base_url();
		$role = isset( $_POST['role'] ) && is_string( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : 'parent';
		$role = in_array( $role, array( 'student', 'teacher', 'parent' ), true ) ? $role : 'parent';
		$back = $base ? $base : home_url( '/' );
		if ( 'student' !== $role ) {
			$back = add_query_arg( 'hub_role', $role, $back );
		}
		if ( 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			wp_safe_redirect( $back );
			exit;
		}
		try {
			Controller::signup(
				array(
					'role'       => $role,
					'email'      => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
					'password'   => isset( $_POST['password'] ) && is_string( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords are never altered.
					'first_name' => isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '',
					'last_name'  => isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '',
					'website'    => isset( $_POST['website'] ) ? sanitize_text_field( wp_unslash( $_POST['website'] ) ) : '',
				)
			);
			$args = array( 'signup' => 'created' );
		} catch ( \Throwable $error ) {
			$args    = array( 'signup' => 'failed' );
			$message = in_array( $error->getCode(), array( 400, 429 ), true ) ? $error->getMessage() : __( 'Unable to create this account. Please try again.', 'ohmylms' );
			set_transient( 'ohmylms_parent_signup_' . self::client_key(), $message, MINUTE_IN_SECONDS );
		}
		wp_safe_redirect( add_query_arg( $args, $back ) );
		exit;
	}

	/**
	 * Per-client key for the one-time error message.
	 *
	 * @return string
	 */
	public static function client_key() {
		return md5( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ) . sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ) );
	}
}
