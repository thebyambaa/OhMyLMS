<?php
/**
 * Emails email order details template.
 *
 * @package OhMyLMS
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$base_color       = $email_settings['ohmylms_email_base_color'] ?? 'var(--ohmylms-primary-color)';
$email_text_color = $email_settings['ohmylms_email_body_text_color'] ?? '#1F2328';
$cta_btn_position = $email_settings['ohmylms_email_button_possition'] ?? 'center';

?>
<h2 style="color: <?php echo esc_attr( $base_color ); ?>; font-size: 16px; line-height: 1.3; margin-bottom: 20px; font-weight: bold;">
	<?php echo 'Order #' . esc_html( $order->get_id() ); ?> (<?php echo esc_html( $order->get_date_created()->format( 'F j, Y' ) ); ?>)
</h2>
