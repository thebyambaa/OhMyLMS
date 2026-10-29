<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Emails\StudentsEmail;

use OhMyLMS\Emails\Emails;

/**
 * Plugin new order implementation.
 */
class NewOrder {

	/**
	 * NewOrder constructor.
	 *
	 * Registers the `trigger` function to be executed after an order is created.
	 */
	public function __construct() {
		add_action( 'ohmylms_checkout_after_create_order', array( $this, 'trigger' ), 10, 2 );
	}


	/**
	 * Basic email settings.
	 *
	 * Returns the basic settings for the "New Order" email, including the template file, title, recipient type, and description.
	 *
	 * @return array The basic email settings.
	 */
	public function basic_settings(): array {
		$basic = array(
			'id'             => 'student_new_order',
			'template_html'  => 'emails/creators/new-order.php',
			'title'          => __( 'Notify Student When Order Is Confirmed', 'ohmylms' ),
			'tooltip'        => __( 'Notify the students when their course order is confirmed.', 'ohmylms' ),
			'recipient_type' => 'student',
			'description'    => __( 'Notify the students when their course order is confirmed', 'ohmylms' ),
		);
		return $basic;
	}

	/**
	 * Default email settings.
	 *
	 * Returns the default settings for the "New Order" email, including subject, heading, additional content, button text, and recipient email.
	 *
	 * @return array The default email settings.
	 */
	public function default_settings(): array {
		$default_settings = array(
			'enable'             => true,
			'heading'            => __( 'Order Confirmed: Your Course Awaits!', 'ohmylms' ),
			'subject'            => __( 'Congratulations {student_name}! Your Order Confirmed', 'ohmylms' ),
			'additional_content' => '<p style="margin: 0 0 15px 0; padding:0; color: #1F2328; font-family: Helvetica Neue, Helvetica, Roboto, Arial, sans-serif; font-size: 15px; line-height: 1.6;">Dear [student_name],</p><p style="margin: 0 0 15px 0; padding:0; color: #1F2328; font-family: Helvetica Neue, Helvetica, Roboto, Arial, sans-serif; font-size: 15px; line-height: 1.6;">Thank you for your purchase! We\'ve successfully received your order and are processing it. Below are the details for your reference.</p>',
			'button_text'        => 'View My Course',
			'footer_text'        => 'You can access your course(s) and view your full order details by clicking the button below.',
			'button_link'        => '',
			'recipient_email'    => array(),
		);
		return $default_settings;
	}


	/**
	 * Trigger the sending of the new order email.
	 *
	 * This function is triggered after an order is created. It retrieves the email settings, prepares the email content,
	 * inlines the CSS, and sends the email to the creator or the default admin email.
	 *
	 * @param mixed $order The order object containing details of the new order.
	 * @param mixed $data Additional data related to the order.
	 */
	public function trigger( $order, $data ) {
		if ( ! $order ) {
			return;
		}

		// Prevent duplicate emails - check if email was already sent.
		$email_sent = get_post_meta( $order->get_id(), '_student_new_order_email_sent', true );
		if ( $email_sent ) {
			return;
		}

		$settings = Emails::get_template_settings( 'student_new_order', $this->default_settings() );

		if ( ! Emails::is_enabled( $settings ) ) {
			return;
		}
		$student_id = $order->get_student_id();
		$user       = get_user_by( 'id', $student_id );
		$to         = '';
		if ( $user ) {
			$to = $user->user_email;
		}

		if ( ! $to ) {
			return;
		}

		$subject = isset( $settings['subject'] ) ? $settings['subject'] : '';
		$subject = Emails::replace_merge_tags( $subject, $order );

		$email_settings = Emails::get_email_settings();

		if ( $order && is_array( $data ) && is_array( $email_settings ) && is_array( $settings ) ) {

			if ( isset( $settings['button_link'] ) ) {
				$settings['button_link'] = ohmylms_get_page_permalink( 'student_dashboard' );
			}

			// Use ohmylms_get_template to get the email body.
			ob_start();
			ohmylms_get_template(
				'emails/new-order', // Template file name (without .php).
				array(
					'order'          => $order,
					'data'           => $data,
					'settings'       => $settings,
					'email_settings' => $email_settings,
				)
			);

			$html_body = ob_get_clean();

			ob_start();
			ohmylms_get_template( 'emails/email-styles' );
			$styles = ob_get_clean();

			$html_body = Emails::replace_merge_tags( $html_body, $order );

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

			$email_sent = wp_mail( $to, $subject, $html_body, $headers );

			// Mark email as sent to prevent duplicates.
			if ( $email_sent ) {
				update_post_meta( $order->get_id(), '_student_new_order_email_sent', true );
			}
		}
	}
}
