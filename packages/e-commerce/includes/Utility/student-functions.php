<?php
/**
 * Utility functions for student operations in OhMyLMS.
 *
 * @package OhMyLMS\Ecommerce\Utility
 */

defined( 'ABSPATH' ) || exit;

/**
 * Disable the admin bar for users who cannot edit posts.
 *
 * @param bool $show_admin_bar Whether to show the admin bar.
 * @return bool Whether to show the admin bar.
 */
function ohmylms_disable_admin_bar( $show_admin_bar ) {
	if ( ! ( current_user_can( 'edit_posts' ) ) ) {
		$show_admin_bar = false;
	}
	return $show_admin_bar;
}
add_filter( 'show_admin_bar', 'ohmylms_disable_admin_bar', 10, 1 ); // phpcs:ignore WordPress.VIP.AdminBarRemoval.RemovalDetected


/**
 * Create a new customer account.
 *
 * @param string $email The email address of the new customer.
 * @param string $username Optional. The username for the new customer. Default is an empty string.
 * @param string $password Optional. The password for the new customer. Default is an empty string.
 * @param array  $args Optional. Additional arguments for the new customer.
 * @return int|WP_Error The new customer ID on success, or a WP_Error object on failure.
 * @see https://github.com/woocommerce/woocommerce/blob/88c2f97ba4cdff4394145cee786c357b488f48dc/plugins/woocommerce/includes/wc-user-functions.php#L55
 *
 * @since 1.0.0
 */
function ohmylms_create_new_student( $email, $username = '', $password = '', $args = array() ) {
	if ( empty( $email ) || ! is_email( $email ) ) {
		return new WP_Error( 'registration-error-invalid-email', __( 'Please provide a valid email address.', 'ohmylms' ) );
	}

	if ( email_exists( $email ) ) {
		return new WP_Error( 'registration-error-email-exists', __( 'An account is already registered with your email address. Please log in to proceed', 'ohmylms' ) );
	}

	// Handle username creation.
	if ( empty( $username ) ) {
		$username = ohmylms_create_new_student_username( $email, $args );
	}
	$username = sanitize_user( $username );
	if ( empty( $username ) || ! validate_username( $username ) ) {
		return new WP_Error( 'registration-error-invalid-username', __( 'Please enter a valid account username.', 'ohmylms' ) );
	}
	if ( username_exists( $username ) ) {
		return new WP_Error( 'registration-error-username-exists', __( 'An account is already registered with that username. Please choose another.', 'ohmylms' ) );
	}

	// Handle password creation.
	if ( empty( $password ) ) {
		$password = wp_generate_password();
	}
	if ( empty( $password ) ) {
		return new WP_Error( 'registration-error-missing-password', __( 'Please enter an account password.', 'ohmylms' ) );
	}

	$errors = new WP_Error();
	if ( $errors->get_error_code() ) {
		return $errors;
	}

	$new_customer_data = array_merge(
		$args,
		array(
			'user_login' => $username,
			'user_pass'  => $password,
			'user_email' => $email,
			'role'       => function_exists( 'ohmylms_get_assignable_student_role' ) ? ohmylms_get_assignable_student_role() : 'subscriber',
		)
	);

	$student_id = wp_insert_user( $new_customer_data );

	if ( is_wp_error( $student_id ) ) {
		return $student_id;
	}

	do_action( 'ohmylms_created_customer', $student_id, $new_customer_data );

	return $student_id;
}

/**
 * Create a new student username based on the provided email and user arguments.
 *
 * @param string $email The email address of the new student.
 * @param array  $new_user_args Optional. Additional arguments for the new user.
 * @param string $suffix Optional. Suffix to append to the username to make it unique.
 * @return string The generated username.
 * @link https://github.com/woocommerce/woocommerce/blob/88c2f97ba4cdff4394145cee786c357b488f48dc/plugins/woocommerce/includes/wc-user-functions.php#L212
 *
 * @since 1.0.0
 * @since 1.0.0
 */
function ohmylms_create_new_student_username( $email, $new_user_args = array(), $suffix = '' ) {
	$username_parts = array();

	if ( isset( $new_user_args['first_name'] ) ) {
		$username_parts[] = sanitize_user( $new_user_args['first_name'], true );
	}

	if ( isset( $new_user_args['last_name'] ) ) {
		$username_parts[] = sanitize_user( $new_user_args['last_name'], true );
	}

	$username_parts = array_filter( $username_parts );

	// If there are no parts, e.g. name had unicode chars, or was not provided, fallback to email.
	if ( empty( $username_parts ) ) {
		$email_parts    = explode( '@', $email );
		$email_username = $email_parts[0];

		if ( in_array(
			$email_username,
			array(
				'sales',
				'hello',
				'mail',
				'contact',
				'info',
			),
			true
		) ) {
			// Get the domain part.
			$email_username = $email_parts[1];
		}

		$username_parts[] = sanitize_user( $email_username, true );
	}

	$username = strtolower( implode( '.', $username_parts ) );

	if ( $suffix ) {
		$username .= $suffix;
	}

	/**
	 * WordPress 4.4 - filters the list of blocked usernames.
	 *
	 * @since 3.7.0
	 * @param array $usernames Array of blocked usernames.
	 */
	$illegal_logins = (array) apply_filters( 'illegal_user_logins', array() );

	// Stop illegal logins and generate a new random username.
	if ( in_array( strtolower( $username ), array_map( 'strtolower', $illegal_logins ), true ) ) {
		$new_args               = array();
		$new_args['first_name'] = 'woo_user_' . zeroise( wp_rand( 0, 9999 ), 4 );

		return ohmylms_create_new_student_username( $email, $new_args, $suffix );
	}

	if ( username_exists( $username ) ) {
		// Generate something unique to append to the username in case of a conflict with another user.
		$suffix = '-' . zeroise( wp_rand( 0, 9999 ), 4 );
		return ohmylms_create_new_student_username( $email, $new_user_args, $suffix );
	}
	return $username;
}
