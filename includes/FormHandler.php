<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS;

use OhMyLMS\Data\Student;
use function OhMyLMS\Commerce\ecommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin form handler implementation.
 */
class FormHandler {

	/**
	 * Init for Form handler.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'save_account_details' ) );
		add_action( 'template_redirect', array( __CLASS__, 'save_account_notification' ) );
		add_action( 'template_redirect', array( __CLASS__, 'save_submission_form_data' ) );
		add_action( 'template_redirect', array( __CLASS__, 'save_quiz_attempt' ) );
		add_action( 'template_redirect', array( __CLASS__, 'save_quiz_submit' ) );
		add_action( 'template_redirect', array( __CLASS__, 'save_quiz_exit_submit' ) );
	}

	/**
	 * Save account details.
	 *
	 * @return void
	 */
	public static function save_account_details() {
		if ( empty( $_POST['action'] ) || 'save_account_details' !== $_POST['action'] ) {
			return;
		}

		$nonce_value = $_POST['save-account-details-nonce'] ?? ''; // @codingStandardsIgnoreLine.

		if ( ! wp_verify_nonce( $nonce_value, 'save_account_details' ) ) {
			return;
		}

		$user_id = get_current_user_id();

		if ( 0 >= $user_id ) {
			return;
		}

		$first_name   = ! empty( $_POST['first_name'] ) ? ohmylms_clean( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name    = ! empty( $_POST['last_name'] ) ? ohmylms_clean( wp_unslash( $_POST['last_name'] ) ) : '';
		$display_name = ! empty( $_POST['display_name'] ) ? ohmylms_clean( wp_unslash( $_POST['display_name'] ) ) : '';
		$address      = ! empty( $_POST['address'] ) ? ohmylms_clean( wp_unslash( $_POST['address'] ) ) : '';
		$email        = ! empty( $_POST['email'] ) ? ohmylms_clean( wp_unslash( $_POST['email'] ) ) : '';
		$pass_current = ! empty( $_POST['password_current'] ) ? ohmylms_clean( wp_unslash( $_POST['password_current'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$pass1        = ! empty( $_POST['password1'] ) ? ohmylms_clean( wp_unslash( $_POST['password1'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$pass2        = ! empty( $_POST['password2'] ) ? ohmylms_clean( wp_unslash( $_POST['password2'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$bio          = ! empty( $_POST['bio'] ) ? htmlspecialchars( wp_strip_all_tags( wp_unslash( $_POST['bio'] ) ), ENT_QUOTES, 'UTF-8' ) : '';
		$interests    = ! empty( $_POST['interests'] ) ? htmlspecialchars( wp_strip_all_tags( wp_unslash( $_POST['interests'] ) ), ENT_QUOTES, 'UTF-8' ) : '';
		$skills       = ! empty( $_POST['skills'] ) && is_array( $_POST['skills'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['skills'] ) ) : array();
		$link_labels  = ! empty( $_POST['link_labels'] ) && is_array( $_POST['link_labels'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['link_labels'] ) ) : array();
		$link_urls    = ! empty( $_POST['link_urls'] ) && is_array( $_POST['link_urls'] ) ? array_map( 'esc_url_raw', wp_unslash( $_POST['link_urls'] ) ) : array();
		$phone        = ! empty( $_POST['phone'] ) ? ohmylms_clean( wp_unslash( $_POST['phone'] ) ) : '';
		$whatsapp     = ! empty( $_POST['whatsapp'] ) ? ohmylms_clean( wp_unslash( $_POST['whatsapp'] ) ) : '';
		$country      = ! empty( $_POST['country'] ) ? ohmylms_clean( wp_unslash( $_POST['country'] ) ) : '';

		$save_pass = true;

		// current user data.
		$current_user       = get_user_by( 'id', $user_id );
		$current_first_name = $current_user->first_name;
		$current_last_name  = $current_user->last_name;
		$current_email      = $current_user->user_email;

		$user               = new \stdClass();
		$user->ID           = $user_id;
		$user->first_name   = $first_name;
		$user->last_name    = $last_name;
		$user->display_name = $display_name;

		if ( is_email( $display_name ) ) {
			\OhMyLMS\Commerce\ohmylmse_add_notice( __( 'Display name cannot be changed to email address due to privacy concern.', 'ohmylms' ), 'error' );
		}

		$required_fields = array(
			// 'first_name'   => 'First Name',
			// 'last_name'    => 'Last Name',
			'email'        => 'Email',
			'display_name' => 'Display name',
		);

		foreach ( $required_fields as $field_key => $field_name ) {
			if ( empty( $_POST[ $field_key ] ) ) {
				/* translators: %s: Field name. */
				\OhMyLMS\Commerce\ohmylmse_add_notice( sprintf( __( '%s is a required field.', 'ohmylms' ), '<strong>' . esc_html( $field_name ) . '</strong>' ), 'error', array( 'id' => $field_key ) );
			}
		}

		// handle email.
		if ( $email ) {
			$email = sanitize_email( $email );
			if ( ! is_email( $email ) ) {
				\OhMyLMS\Commerce\ohmylmse_add_notice( __( 'Please provide a valid email address.', 'ohmylms' ), 'error' );
			} elseif ( email_exists( $email ) && $email !== $current_user->user_email ) {
				\OhMyLMS\Commerce\ohmylmse_add_notice( __( 'This email address is already registered.', 'ohmylms' ), 'error' );
			}
			$user->user_email = $email;
		}

		// handle password.
		if ( ! empty( $pass_current ) && empty( $pass1 ) && empty( $pass2 ) ) {
			\OhMyLMS\Commerce\ohmylmse_add_notice( __( 'Please fill out all password fields.', 'ohmylms' ), 'error' );
			$save_pass = false;
		} elseif ( ! empty( $pass1 ) && empty( $pass_current ) ) {
			\OhMyLMS\Commerce\ohmylmse_add_notice( __( 'Please enter your current password.', 'ohmylms' ), 'error' );
			$save_pass = false;
		} elseif ( ! empty( $pass1 ) && empty( $pass2 ) ) {
			\OhMyLMS\Commerce\ohmylmse_add_notice( __( 'Please re-enter your password.', 'ohmylms' ), 'error' );
			$save_pass = false;
		} elseif ( ( ! empty( $pass1 ) || ! empty( $pass2 ) ) && $pass1 !== $pass2 ) {
			\OhMyLMS\Commerce\ohmylmse_add_notice( __( 'New passwords do not match.', 'ohmylms' ), 'error' );
			$save_pass = false;
		} elseif ( ! empty( $pass1 ) && ! wp_check_password( $pass_current, $current_user->user_pass, $current_user->ID ) ) {
			\OhMyLMS\Commerce\ohmylmse_add_notice( __( 'Your current password is incorrect.', 'ohmylms' ), 'error' );
			$save_pass = false;
		}

		if ( $pass1 && $save_pass ) {
			$user->user_pass = $pass1;
		}

		// Phone, province, school name and grade share their rules with the hub Settings screen.
		$learner_details = \OhMyLMS\Student\LearnerProfile::validate( array_intersect_key( wp_unslash( $_POST ), array_flip( array( 'phone', 'province', 'school_name', 'grade' ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified above; sanitized by LearnerProfile::validate().
		foreach ( $learner_details['errors'] as $learner_error ) {
			\OhMyLMS\Commerce\ohmylmse_add_notice( esc_html( $learner_error ), 'error' );
		}

		if ( \OhMyLMS\Commerce\ohmylmse_notice_count( 'error' ) === 0 ) {
			wp_update_user( $user );
			$student = new Student( $user->ID );
			$student->set_email( $email );
			$student->set_display_name( $display_name );
			$student->set_first_name( $first_name );
			$student->set_last_name( $last_name );
			$student->set_bio( $bio );
			$student->set_interest( $interests );
			$student->set_skills( $skills );
			$student->set_social_links( $link_labels, $link_urls );
			$student->set_address( $address );
			$student->set_phone( $phone );
			$student->set_whatsapp( $whatsapp );
			$student->set_country( $country );

			$student->save();
			\OhMyLMS\Student\LearnerProfile::save( $user->ID, $learner_details['values'] );

			\OhMyLMS\Commerce\ohmylmse_add_notice( __( 'Account details changed successfully.', 'ohmylms' ) );

			// Check if redirect_to parameter is set (for shortcode usage).
			$redirect_url = ! empty( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';

			if ( $redirect_url ) {
				wp_safe_redirect( $redirect_url );
			} else {
				wp_safe_redirect( ohmylms_get_endpoint_url( 'profile', '', ohmylms_get_page_permalink( 'student_profile' ) ) );
			}
			exit;
		} else {
			// Check if redirect_to parameter is set (for shortcode usage).
			$redirect_url = ! empty( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';

			if ( $redirect_url ) {
				// Add edit parameter back for error state.
				$redirect_url = add_query_arg( 'edit', 'true', $redirect_url );
				wp_safe_redirect( $redirect_url );
			} else {
				wp_safe_redirect( ohmylms_get_endpoint_url( 'profile-edit', '', ohmylms_get_page_permalink( 'student_profile' ) ) );
			}
			exit;
		}
	}

	/**
	 * Save account notification settings.
	 *
	 * @return void
	 */
	public static function save_account_notification() {
		$nonce_value = $_POST['save-account-notification-nonce'] ?? ''; // @codingStandardsIgnoreLine.

		if ( ! wp_verify_nonce( $nonce_value, 'save_account_notification' ) ) {
			return;
		}

		if ( empty( $_POST['action'] ) || 'save_account_notification' !== $_POST['action'] ) {
			return;
		}

		$user_id = get_current_user_id();

		if ( 0 >= $user_id ) {
			return;
		}

		$notification_chapter            = ! empty( $_POST['notification_chapter'] ) ? ohmylms_clean( wp_unslash( $_POST['notification_chapter'] ) ) : 'off';
		$notification_direct_messages    = ! empty( $_POST['notification_direct_messages'] ) ? ohmylms_clean( wp_unslash( $_POST['notification_direct_messages'] ) ) : 'off';
		$notification_new_course_content = ! empty( $_POST['notification_new_course_content'] ) ? ohmylms_clean( wp_unslash( $_POST['notification_new_course_content'] ) ) : 'off';
		$notification_comments_replies   = ! empty( $_POST['notification_comments_replies'] ) ? ohmylms_clean( wp_unslash( $_POST['notification_comments_replies'] ) ) : 'off';
		if ( \OhMyLMS\Commerce\ohmylmse_notice_count( 'error' ) === 0 ) {
			$student = new Student( $user_id );
			$student->set_notification_chapter( $notification_chapter );
			$student->set_notification_direct_messages( $notification_direct_messages );
			$student->set_notification_new_course_content( $notification_new_course_content );
			$student->set_notification_comments_replies( $notification_comments_replies );

			$student->save();
			\OhMyLMS\Commerce\ohmylmse_add_notice( __( 'Notification settings saved successfully.', 'ohmylms' ) );
			wp_safe_redirect( ohmylms_get_endpoint_url( 'notification', '', ohmylms_get_page_permalink( 'student_profile' ) ) );
			exit;
		}
	}

	/**
	 * Save assignment submission form data.
	 *
	 * @return void
	 */
	public static function save_submission_form_data() {
		$nonce_value = $_POST['save-assignment-submission-nonce'] ?? ''; // @codingStandardsIgnoreLine.
		if ( ! wp_verify_nonce( $nonce_value, 'save_assignment_submission_file' ) ) {
			return;
		}

		if ( empty( $_POST['action'] ) || 'save_assignment_submission_file' !== $_POST['action'] ) {
			return;
		}

		$assignment_id = ! empty( $_POST['assignment_id'] ) ? ohmylms_clean( wp_unslash( $_POST['assignment_id'] ) ) : '';
		$course_id     = ! empty( $_POST['course_id'] ) ? ohmylms_clean( wp_unslash( $_POST['course_id'] ) ) : '';
		$content       = ! empty( $_POST['submission-body'] ) ? ohmylms_clean( sanitize_text_field( wp_unslash( $_POST['submission-body'] ) ) ) : '';

		$assignment = new \OhMyLMS\Data\Assignment( $assignment_id );

		if ( ! $assignment->get_id() ) {
			return;
		}

		$student = new Student( get_current_user_id() );

		if ( ! $student->get_id() ) {
			return;
		}

		// The same gate as the AJAX upload: enrolment, release, prerequisites and the timed deadline.
		$allowed = \OhMyLMS\Assignment\Delivery::check_submission( $assignment->get_id(), $student->get_id() );
		if ( is_wp_error( $allowed ) ) {
			\OhMyLMS\Commerce\ohmylmse_add_notice( $allowed->get_error_message(), 'error' );
			wp_safe_redirect( get_permalink( $assignment->get_id() ) );
			exit;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		if ( ! isset( $_FILES['ohmylms-submission-file']['error'] ) || UPLOAD_ERR_NO_FILE === absint( $_FILES['ohmylms-submission-file']['error'] ) ) {
			\OhMyLMS\Commerce\ohmylmse_add_notice( __( 'No file was uploaded.', 'ohmylms' ), 'error' );
			wp_safe_redirect( get_permalink( $assignment->get_id() ) );
			exit;
		}

		$allowed_mimes = apply_filters(
			'ohmylms_assignment_allowed_mimes',
			array(
				'jpg|jpeg' => 'image/jpeg',
				'png'      => 'image/png',
				'webp'     => 'image/webp',
				'pdf'      => 'application/pdf',
				'doc'      => 'application/msword',
				'docx'     => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'xls'      => 'application/vnd.ms-excel',
				'xlsx'     => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				'csv'      => 'text/csv',
				'mp3'      => 'audio/mpeg',
				'mp4'      => 'video/mp4',
				'zip'      => 'application/zip',
			)
		);

		$uploaded_file = wp_handle_upload(
			$_FILES['ohmylms-submission-file'],
			array(
				'test_form' => false,
				'mimes'     => $allowed_mimes,
			)
		);

		if ( ! $uploaded_file || isset( $uploaded_file['error'] ) ) {
			$error_message = isset( $uploaded_file['error'] ) ? $uploaded_file['error'] : __( 'File upload failed.', 'ohmylms' );
			\OhMyLMS\Commerce\ohmylmse_add_notice( $error_message, 'error' );
			wp_safe_redirect( get_permalink( $assignment->get_id() ) );
			exit;
		}

		$file_size = filesize( $uploaded_file['file'] );
		$file_size = ohmylms_format_file_size( $file_size );

		if ( $assignment->get_enable_file_size_limit() && $assignment->get_max_file_size_limit() < $file_size ) {
			\OhMyLMS\Commerce\ohmylmse_add_notice( __( 'File size is too large.', 'ohmylms' ), 'error' );
			wp_safe_redirect( get_permalink( $assignment->get_id() ) );
			exit;
		}

		if ( $assignment->get_number_of_files() <= count( $assignment->get_submission( $student->get_id() ) ) ) {
			\OhMyLMS\Commerce\ohmylmse_add_notice( __( 'You have reached the maximum number of files allowed.', 'ohmylms' ), 'error' );
			wp_safe_redirect( get_permalink( $assignment->get_id() ) );
			exit;
		}

		$submit_data = array(
			'content' => $content,
			'files'   => $uploaded_file,
		);

		$assignment->submit_file_submission( $student->get_id(), $course_id, $submit_data );

		\OhMyLMS\Commerce\ohmylmse_add_notice( __( 'Assignment submitted successfully.', 'ohmylms' ) );
		wp_safe_redirect( get_permalink( $assignment->get_id() ) );
		exit;
	}

	/**
	 * Save quiz attempt.
	 *
	 * @return void
	 */
	public static function save_quiz_attempt() {
		if ( 'quiz-action' !== sanitize_key( wp_unslash( $_POST['action'] ?? '' ) ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['save-quiz-attempt-nonce'] ?? '' ) ), 'save_quiz_attempt' ) ) {
			wp_die( 'Invalid quiz request.', '', array( 'response' => 403 ) );
		}
		$quiz_id    = absint( $_POST['ohmylms_quiz_id'] ?? 0 );
		$student_id = get_current_user_id();
		$result     = \OhMyLMS\Quiz\Submission::start( $quiz_id, $student_id );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}
		wp_safe_redirect( get_permalink( $quiz_id ) );
		exit;
	}

	/**
	 * Save quiz submission.
	 *
	 * @return void
	 */
	public static function save_quiz_submit() {
		if ( 'ohmylms-quiz-submission' !== sanitize_key( wp_unslash( $_POST['action'] ?? '' ) ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['save-quiz-submit-nonce'] ?? '' ) ), 'save_quiz_submit' ) ) {
			wp_die( 'Invalid quiz request.', '', array( 'response' => 403 ) );
		}
		$quiz_id    = absint( $_POST['ohmylms_quiz_id'] ?? 0 );
		$student_id = get_current_user_id();
		$attempt_id = absint( $_POST['quiz_attempt_id'] ?? 0 );
		$answers    = wp_unslash( $_POST['attempt'][ $attempt_id ]['quiz_question'] ?? array() );
		if ( ! is_array( $answers ) ) {
			wp_die( 'Invalid answers.', '', array( 'response' => 400 ) );
		}
		$result = \OhMyLMS\Quiz\Submission::submit( $quiz_id, $attempt_id, $student_id, $answers, 'submit' );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}
		wp_safe_redirect( get_permalink( $quiz_id ) );
		exit;
	}


	/**
	 * Save quiz exit submit for Form handler.
	 */
	public static function save_quiz_exit_submit() {
		if ( 'ohmylms-quiz-exit-submission' !== sanitize_key( wp_unslash( $_POST['action'] ?? '' ) ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['save-quiz-exit-submit-nonce'] ?? '' ) ), 'save_quiz_exit_submit' ) ) {
			wp_die( 'Invalid quiz request.', '', array( 'response' => 403 ) );
		}
		$quiz_id    = absint( $_POST['ohmylms_quiz_id'] ?? 0 );
		$student_id = get_current_user_id();
		$attempt_id = absint( $_POST['quiz_attempt_id'] ?? 0 );
		$answers    = wp_unslash( $_POST['attempt'][ $attempt_id ]['quiz_question'] ?? array() );
		if ( ! is_array( $answers ) ) {
			wp_die( 'Invalid answers.', '', array( 'response' => 400 ) );
		}
		$result = \OhMyLMS\Quiz\Submission::submit( $quiz_id, $attempt_id, $student_id, $answers, 'exit' );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}
		wp_safe_redirect( get_permalink( $quiz_id ) );
		exit;
	}
}
