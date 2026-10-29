<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Emails\StudentsEmail;

use OhMyLMS\Emails\Emails;

/**
 * Plugin cancel enrollment implementation.
 */
class CancelEnrollment {

	/**
	 * Initialize Cancel enrollment.
	 */
	public function __construct() {
		add_action( 'ohmylms_update_order_status_to_cancelled', array( $this, 'trigger' ), 10 );
	}

	/**
	 * Basic settings for Cancel enrollment.
	 */
	public function basic_settings(): array {
		$basic = array(
			'id'             => 'student_cancel_enrollment',
			'template_html'  => 'emails/creators/new-order.php',
			'title'          => __( 'Notify Student of Enrollment Cancellation', 'ohmylms' ),
			'tooltip'        => __( 'Notify the students that their course enrollment is cancelled.', 'ohmylms' ),
			'recipient_type' => 'student',
			'description'    => __( 'This email is sent to the student when an enrollment is cancelled.', 'ohmylms' ),
		);
		return $basic;
	}

	/**
	 * Default settings for Cancel enrollment.
	 */
	public function default_settings(): array {
		$default_settings = array(
			'enable'             => false,
			'heading'            => __( 'Enrollment Canceled: Need Assistance?', 'ohmylms' ),
			'subject'            => __( 'Ops! Enrollment Canceled', 'ohmylms' ),
			'additional_content' => '<p>Hi [student_name],</p><p>We noticed your enrollment for <strong>[course_name]</strong> was canceled.</p>',
			'footer_text'        => '<p>Your refund will be processed as soon as possible, and you will receive an e-mail notification once it has been completed. Please allow <strong>[expected_processing_time]</strong> for the refund to reflect in your account.</p><p>Thank you for choosing <strong>[store_name]</strong>, and we look forward to serving you better in the future. If you\'d like to explore more of our courses or re-enroll, contact us at<a href="mailto:[admin_email]" style="color: var(--ohmylms-primary-color); font-weight: 500;">[admin_email]</a>.</p><p >Best,<strong>[site_name]</strong></p>',
			'recipient_email'    => array(),
		);
		return $default_settings;
	}

	/**
	 * Trigger for Cancel enrollment.
	 *
	 * @param mixed $order Order.
	 */
	public function trigger( $order ) {
		if ( ! $order ) {
			return;
		}

		$settings = Emails::get_template_settings( 'student_cancel_enrollment', $this->default_settings() );

		if ( ! Emails::is_enabled( $settings ) ) {
			return;
		}
		$student_id = $order->get_student_id();
		$user       = get_user_by( 'id', $student_id );
		$name       = '';
		$to         = '';

		if ( $user ) {
			$to   = $user->user_email;
			$name = $user->display_name;
		}

		if ( ! $to ) {
			return;
		}

		$subject = isset( $settings['subject'] ) ? $settings['subject'] : '';

		global $wpdb;
		$table_name  = $wpdb->prefix . 'ohmylms_user_enrollment';
		$enroll_data = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d AND order_id = %d', $table_name, $student_id, $order->get_id() ), ARRAY_A );
		$course      = false;
		if ( empty( $enroll_data['course_id'] ) ) {
			return;
		}

		$course  = ohmylms_get_course( $enroll_data['course_id'] );
		$subject = Emails::replace_merge_tags( $subject, $order, $course );

		$email_settings = Emails::get_email_settings();
		if ( $order && is_array( $email_settings ) && is_array( $enroll_data ) && is_array( $settings ) && $course ) {
			// Use ohmylms_get_template to get the email body.
			ob_start();
			ohmylms_get_template(
				'emails/cancel-enrollment', // Template file name (without .php).
				array(
					'order'          => $order,
					'student_name'   => $name,
					'enroll_data'    => $enroll_data,
					'course'         => $course,
					'settings'       => $settings,
					'email_settings' => $email_settings,
				)
			);
			$html_body = ob_get_clean();

			ob_start();
			ohmylms_get_template( 'emails/email-styles' );
			$styles = ob_get_clean();

			$html_body = Emails::replace_merge_tags( $html_body, $order, $course );

			$sender_name  = $email_settings['ohmylms_email_sender_name'];
			$sender_email = $email_settings['ohmylms_email_sender_email_address'];
			$headers      = array(
				'MIME-Version: 1.0',
				'Content-Type: text/html; charset=UTF-8',
			);

			if ( $sender_email && $sender_name ) {
				$from      = 'From: ' . $sender_name;
				$from      = 'From: ' . $sender_name . ' <' . $sender_email . '>';
				$headers[] = $from;
			}

			wp_mail( $to, $subject, $html_body, $headers );
		}
	}
}
