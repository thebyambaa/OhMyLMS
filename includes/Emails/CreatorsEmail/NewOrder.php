<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class NewOrder.
 *
 * @package OhMyLMS
 */



namespace OhMyLMS\Emails\CreatorsEmail;

use OhMyLMS\Emails\Emails;



/**
 * Class NewOrder
 *
 * This class handles the sending of the new order confirmation email to the creator when a new order is placed.
 *
 * @package OhMyLMS\Emails\CreatorsEmail
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
			'id'             => 'creator_new_order',
			'template_html'  => 'emails/creators/new-order.php',
			'title'          => __( 'Send Email When Order Is Confirmed', 'ohmylms' ),
			'tooltip'        => __( 'This email is sent to the creator when a new order is received.', 'ohmylms' ),
			'recipient_type' => 'creator',
			'description'    => __( 'This email is sent to the creator when a new order is received.', 'ohmylms' ),
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
			'heading'            => __( 'New Order has been placed', 'ohmylms' ),
			'subject'            => __( 'Congratulations! {student_name} placed New Order', 'ohmylms' ),
			'additional_content' => '<p style="margin: 0 0 15px 0; padding:0; color: #1F2328; font-family: Helvetica Neue, Helvetica, Roboto, Arial, sans-serif; font-size: 15px; line-height: 1.6;">Dear Creator,</p><p style="margin: 0 0 15px 0; padding:0; color: #1F2328; font-family: Helvetica Neue, Helvetica, Roboto, Arial, sans-serif; font-size: 15px; line-height: 1.6;">Congratulations! A student has purchased one of your courses. Below are the details for your reference:</p>',
			'button_text'        => '',
			'footer_text'        => 'You can view this courses by clicking the button below.',
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
		$email_sent = get_post_meta( $order->get_id(), '_creator_new_order_email_sent', true );
		if ( $email_sent ) {
			return;
		}

		$settings       = get_option( 'create_lms_email_creator_new_order', $this->default_settings() );
		$to             = ! empty( $settings['recipient_email'] ) ? $settings['recipient_email'] : get_option( 'admin_email' );
		$subject        = isset( $settings['subject'] ) ? $settings['subject'] : '';
		$subject        = Emails::replace_merge_tags( $subject, $order );
		$email_settings = Emails::get_email_settings();
		if ( $order && is_array( $email_settings ) && is_array( $data ) && is_array( $settings ) ) {

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
			$styles    = ob_get_clean();
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
				update_post_meta( $order->get_id(), '_creator_new_order_email_sent', true );
			}
		}
	}
}
