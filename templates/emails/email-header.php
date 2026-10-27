<?php
/**
 * Emails email header template.
 *
 * @package OhMyLMS
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$email_logo          = $email_settings['ohmylms_email_branding_image'] ?? '';
$email_body_bg_color = $email_settings['ohmylms_email_body_background_color'] ?? '#ffffff';

?>
<!-- Header with Logo -->
<tr class="ohmylms-email-header">
	<td style="border: none; border-bottom: 1px solid #eee; padding: 16px 20px; text-align: center; background: <?php echo esc_attr( $email_body_bg_color ); ?>; border-radius: 20px 20px 0 0;">
		<img src="<?php echo esc_url( $email_logo ); ?>" alt="Email Logo" style="width:108px; height:auto; border: 0; display: block; margin: 0 auto;">
	</td>
</tr>
