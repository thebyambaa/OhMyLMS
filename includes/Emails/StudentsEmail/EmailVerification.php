<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Email Verification Email
 *
 * @package OhMyLMS\Emails\StudentsEmail
 * @since   1.0.0
 */

namespace OhMyLMS\Emails\StudentsEmail;

use OhMyLMS\Emails\Emails;
use OhMyLMS\Services\EmailVerificationService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Listens for ohmylms_send_email_verification and sends the HTML verification email.
 */
class EmailVerification {

	/**
	 * Initialize Email verification.
	 */
	public function __construct() {
		add_action( 'ohmylms_send_email_verification', array( $this, 'send' ), 10, 2 );
	}

	/**
	 * Send the verification email.
	 *
	 * @param int    $user_id User identifier.
	 * @param string $token Token.
	 * @return bool
	 */
	public function send( int $user_id, string $token ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$site_name      = get_bloginfo( 'name' );
		$verify_url     = EmailVerificationService::get_verification_url( $token );
		$email_settings = Emails::get_email_settings();

		$subject = sprintf(
			/* translators: %s: site name */
			__( 'Confirm your email address for %s', 'ohmylms' ),
			$site_name
		);

		$message = $this->get_template(
			array(
				'user_name'      => $user->display_name ?: $user->user_login,
				'verify_url'     => $verify_url,
				'site_name'      => $site_name,
				'email_settings' => $email_settings,
			)
		);

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$sender_name  = $email_settings['ohmylms_email_sender_name'] ?? '';
		$sender_email = $email_settings['ohmylms_email_sender_email_address'] ?? '';
		if ( $sender_email && $sender_name ) {
			$headers[] = 'From: ' . $sender_name . ' <' . $sender_email . '>';
		}

		return wp_mail( $user->user_email, $subject, $message, $headers );
	}

	/**
	 * Build the HTML email body.
	 *
	 * @param array $data Input data.
	 */
	private function get_template( array $data ): string {
		$defaults = array(
			'user_name'      => '',
			'verify_url'     => '',
			'site_name'      => get_bloginfo( 'name' ),
			'email_settings' => array(),
		);
		$data     = wp_parse_args( $data, $defaults );

		$settings   = $data['email_settings'];
		$base_color = ! empty( $settings['ohmylms_email_base_color'] ) ? $settings['ohmylms_email_base_color'] : '#6E42D3';
		$bg_color   = ! empty( $settings['ohmylms_email_background_color'] ) ? $settings['ohmylms_email_background_color'] : '#F4F5F7';
		$body_bg    = ! empty( $settings['ohmylms_email_body_background_color'] ) ? $settings['ohmylms_email_body_background_color'] : '#FFFFFF';
		$text_color = ! empty( $settings['ohmylms_email_body_text_color'] ) ? $settings['ohmylms_email_body_text_color'] : '#1F2328';
		$logo_url   = '';

		if ( ! empty( $settings['ohmylms_email_branding_image'] ) ) {
			$logo_id  = attachment_url_to_postid( $settings['ohmylms_email_branding_image'] );
			$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : $settings['ohmylms_email_branding_image'];
		}

		ob_start();
		?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc_html( sprintf( /* translators: %s: text value. */
__( 'Confirm your email for %s', 'ohmylms' ), $data['site_name'] ) ); ?></title>
</head>
<body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;background-color:<?php echo esc_attr( $bg_color ); ?>;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:<?php echo esc_attr( $bg_color ); ?>;padding:40px 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background-color:<?php echo esc_attr( $body_bg ); ?>;border-radius:16px;box-shadow:0 4px 6px rgba(0,0,0,0.1);overflow:hidden;max-width:600px;width:100%;">
<tr><td style="padding:40px 40px 0;">

		<?php if ( $logo_url ) : ?>
<div style="text-align:center;margin-bottom:30px;">
<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $data['site_name'] ); ?>" style="max-width:150px;height:auto;">
</div>
		<?php else : ?>
<div style="text-align:center;margin-bottom:24px;">
<h2 style="margin:0;font-size:18px;font-weight:600;color:<?php echo esc_attr( $base_color ); ?>;"><?php echo esc_html( $data['site_name'] ); ?></h2>
</div>
		<?php endif; ?>

<div style="text-align:center;margin-bottom:24px;">
<div style="display:inline-block;background-color:#f0f0ff;border-radius:50%;padding:16px;margin-bottom:16px;">
<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="<?php echo esc_attr( $base_color ); ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
</svg>
</div>
<h1 style="margin:0;font-size:26px;font-weight:700;color:<?php echo esc_attr( $text_color ); ?>;"><?php esc_html_e( 'Confirm your email address', 'ohmylms' ); ?></h1>
</div>

<p style="margin:0 0 16px;font-size:16px;color:<?php echo esc_attr( $text_color ); ?>;line-height:1.6;">
		<?php echo esc_html( sprintf( /* translators: %s: text value. */
		__( 'Hi %s,', 'ohmylms' ), $data['user_name'] ) ); ?>
</p>
<p style="margin:0 0 24px;font-size:16px;color:<?php echo esc_attr( $text_color ); ?>;line-height:1.6;">
		<?php echo esc_html( sprintf( /* translators: %s: text value. */
		__( 'Thanks for joining %s! Please verify your email address to activate your account and access your courses.', 'ohmylms' ), $data['site_name'] ) ); ?>
</p>

<div style="text-align:center;margin:32px 0;">
<a href="<?php echo esc_url( $data['verify_url'] ); ?>"
	style="display:inline-block;background-color:<?php echo esc_attr( $base_color ); ?>;color:#ffffff;padding:14px 36px;text-decoration:none;border-radius:8px;font-size:16px;font-weight:600;letter-spacing:0.01em;">
		<?php esc_html_e( 'Verify Email Address', 'ohmylms' ); ?>
</a>
</div>

<p style="margin:0 0 8px;font-size:14px;color:#7A8B9A;line-height:1.6;">
		<?php esc_html_e( 'This link expires in 24 hours.', 'ohmylms' ); ?>
</p>
<p style="margin:0 0 24px;font-size:14px;color:#7A8B9A;line-height:1.6;">
		<?php esc_html_e( 'If you did not create an account, you can safely ignore this email.', 'ohmylms' ); ?>
</p>

<div style="border-top:1px solid #e5e7eb;padding:20px 0;">
<p style="margin:0;font-size:12px;color:#7A8B9A;text-align:center;">
&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( $data['site_name'] ); ?>
</p>
</div>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
		<?php
		return ob_get_clean();
	}
}
