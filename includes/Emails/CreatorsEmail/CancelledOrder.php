<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Emails\CreatorsEmail;

use OhMyLMS\Emails\Emails;
/**
 * Plugin cancelled order implementation.
 */
class CancelledOrder {

	/**
	 * Initialize Cancelled order.
	 */
	public function __construct() {
		add_action( 'ohmylms_update_order_status_to_cancelled', array( $this, 'trigger' ), 10 );
	}

	/**
	 * Basic settings for Cancelled order.
	 */
	public function basic_settings(): array {
		$basic = array(
			'id'             => 'creator_cancelled_order',
			'template_html'  => 'emails/creators/new-order.php',
			'title'          => __( 'Send Email When Order Is Cancelled', 'ohmylms' ),
			'tooltip'        => __( 'This email is sent to the creator when a order is cancelled.', 'ohmylms' ),
			'recipient_type' => 'creator',
			'description'    => __( 'This email is sent to the creator when a order is cancelled.', 'ohmylms' ),
		);
		return $basic;
	}

	/**
	 * Default settings for Cancelled order.
	 */
	public function default_settings(): array {
		$default_settings = array(
			'enable'             => false,
			'heading'            => __( 'Order Canceled!', 'ohmylms' ),
			'subject'            => __( 'Ops! an order has been canceled', 'ohmylms' ),
			'additional_content' => '<p>Hi,</p><p>[student_name] has canceled their order for <strong>[course_name]</strong></p>',
			'footer_text'        => '<p>Your payout will be processed as soon as possible, and you will receive an e-mail notification once it has been completed. Please allow <strong>[expected_processing_time]</strong> for the payout to reflect in your account.</p><p>Thank you for partnering with <strong>[Your Platform Name]</strong>, and we look forward to continuing to support your success. If you have any questions or need assistance, contact us at <a href="mailto:[email@example.com]" style="color: var(--ohmylms-primary-color); font-weight: 500;">[email@example.com]</a>.</p><p>Best, <strong>[site_name]</strong></p>',
			'recipient_email'    => array(),
		);
		return $default_settings;
	}

	/**
	 * Trigger for Cancelled order.
	 *
	 * @param mixed $order Order.
	 */
	public function trigger( $order ) {
		$settings = get_option( 'create_lms_email_creator_cancelled_order', $this->default_settings() );

		if ( ! $order ) {
			return;
		}

		$student_id = $order->get_student_id();
		$user       = get_user_by( 'id', $student_id );
		$name       = '';
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
		$enroll_data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE user_id = %d AND order_id = %d",
				$student_id,
				$order->get_id()
			),
			ARRAY_A
		);
		$course      = false;
		if ( empty( $enroll_data['course_id'] ) ) {
			return;
		}

		$course = ohmylms_get_course( $enroll_data['course_id'] );

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
