<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Self sign-up of student, teacher and parent accounts, and the attempt limit shared with invitations.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Schools;

defined( 'ABSPATH' ) || exit;

/**
 * The hub sign-up forms (`Student\ParentActions`) create accounts here. No enrolment, order, school
 * membership or child link is created. Formerly behind `POST ohmylms/v1/school/register`, which was
 * retired with the other `school/*` routes (docs/api/account-hub.md, "Retired legacy routes").
 */
final class Signup {

	/**
	 * Allow 15 attempts per client address and purpose in 15 minutes.
	 *
	 * @param string $purpose Purpose such as `school_signup`.
	 * @throws \RuntimeException 429 when the limit is reached.
	 */
	public static function rate_limit( $purpose ) {
		$address  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'local';
		$key      = 'ohmylms_' . $purpose . '_' . hash_hmac( 'sha256', $address, wp_salt( 'nonce' ) );
		$attempts = (int) get_transient( $key );
		if ( 15 <= $attempts ) {
			throw new \RuntimeException( 'Too many attempts. Please try again in 15 minutes.', 429 );
		}
		set_transient( $key, $attempts + 1, 15 * MINUTE_IN_SECONDS );
	}

	/**
	 * Create a signed-out visitor's account.
	 *
	 * @param array $data Keys: role (student, teacher, parent), email, password, first_name, last_name, website (honeypot).
	 * @return array
	 * @throws \RuntimeException 400 for invalid input, 429 when rate limited.
	 */
	public static function create( array $data ) {
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
		$first        = Service::text( $data['first_name'] ?? '' );
		$last         = Service::text( $data['last_name'] ?? '' );
		$display      = trim( $first . ' ' . $last );
		$id           = wp_insert_user(
			array(
				'user_login'   => 'learner-' . strtolower( wp_generate_password( 16, false ) ),
				'user_email'   => $email,
				'user_pass'    => $password,
				'first_name'   => $first,
				'last_name'    => $last,
				'display_name' => '' !== $display ? $display : __( 'Learner', 'ohmylms' ),
				'role'         => $roles[ $role ],
			)
		);
		if ( is_wp_error( $id ) ) {
			throw new \RuntimeException( esc_html( $id->get_error_message() ), 400 );
		}
		if ( \OhMyLMS\Services\EmailVerificationService::is_required() || 'parent' === $role ) {
			\OhMyLMS\Services\EmailVerificationService::generate_and_send( $id );
		}
		Service::audit( 0, 'account_created', $id );

		return array(
			'success'   => true,
			'message'   => 'Account created. Check your inbox for verification if requested, then sign in. No course enrollment or order was created.',
			'login_url' => wp_login_url( Views::portal_url() ),
		);
	}
}
