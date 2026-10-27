<?php
/**
 * Emails email footer template.
 *
 * @package OhMyLMS
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$base_color             = $email_settings['ohmylms_email_base_color'] ?? 'var(--ohmylms-primary-color)';
$email_text_color       = $email_settings['ohmylms_email_body_text_color'] ?? '#1F2328';
$cta_btn_position       = $email_settings['ohmylms_email_button_possition'] ?? 'center';
$link                   = isset( $settings['button_link'] ) ? $settings['button_link'] : '#';
$additional_footer_text = $email_settings['ohmylms_email_footer_text'] ?? '';
?>

<div style="margin: 0 0 15px 0; padding:0; color: <?php echo esc_attr( $email_text_color ); ?>; font-family: Helvetica Neue;,Helvetica,Roboto,Arial,sans-serif; font-size: 15px; line-height: 1.6;">
	<?php echo wp_kses_post( $settings['footer_text'] ); ?>
</div>

<?php if ( $settings['button_text'] ) : ?>
<div style="text-align: <?php echo esc_attr( $cta_btn_position ); ?>; margin: 25px 0 0;">
	<a href="<?php echo esc_url( $link ); ?>" style="background: <?php echo esc_attr( $base_color ); ?>; color: #ffffff; padding: 14px 25px; font-size: 15px; font-weight: 500; line-height:1; text-transform: capitalize; text-decoration: none; border-radius: 12px; display: inline-block;">
		<?php echo esc_html( $settings['button_text'] ); ?>
	</a>
</div>
<?php endif; ?>

<?php if ( ! empty( $additional_footer_text ) ) : ?>
	<div style="margin: 15px 0 0 0; padding:0; color: <?php echo esc_attr( $email_text_color ); ?>; font-family: Helvetica Neue, Helvetica, Roboto, Arial, sans-serif; font-size: 13px; line-height: 1.5; text-align: center;">
		<?php echo wp_kses_post( $additional_footer_text ); ?>
	</div>
<?php endif; ?>
