<?php
/**
 * Emails at-risk nudge template.
 *
 * @package OhMyLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $settings['heading'] ); ?></title>
</head>

<?php
$email_bg_color      = $email_settings['ohmylms_email_background_color'] ?? '#F4F5F7';
$base_color          = $email_settings['ohmylms_email_base_color'] ?? '#6E42D3';
$email_body_bg_color = $email_settings['ohmylms_email_body_background_color'] ?? '#FFFFFF';
$email_text_color    = $email_settings['ohmylms_email_body_text_color'] ?? '#1F2328';
$cta_btn_position    = $email_settings['ohmylms_email_button_possition'] ?? 'left';
?>

<body>
	<div class="ohmylms-email-container">
		<table class="ohmylms-table-main" style="width: 100%; border-spacing: 0; background: <?php echo esc_attr( $email_bg_color ); ?>; border:0;">
			<tr style="background: transparent; border: none; border-radius: 0;">
				<td style="background: transparent; border: none; border-radius: 0; padding: 40px;">
					<table class="ohmylms-table2" style="border-spacing: 0; max-width: 600px; width: 100%; margin-left: auto; margin-right: auto; border-collapse: separate;">
						<tr>
							<td style="border: 5px solid <?php echo esc_attr( $base_color ); ?>; background: <?php echo esc_attr( $email_body_bg_color ); ?>; border-radius: 20px;">
								<table class="ohmylms-table3" style="width: 100%; border-collapse: collapse;">
									<?php do_action( 'ohmylms_email_header', $settings['heading'], $email_settings ); ?>
									<tr>
										<td style="padding: 25px 35px 30px; background: <?php echo esc_attr( $email_body_bg_color ); ?>; border: 0; border-radius: 0 0 20px 20px;">
											<div class="body-content-inner" style="color: <?php echo esc_attr( $email_text_color ); ?>; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 15px; line-height: 1.6; text-align: left;">
												<h1 style="padding: 0; color: <?php echo esc_attr( $base_color ); ?>; font-size: 28px; line-height: 1.3; margin: 0 0 20px; font-weight: bold; text-align: center;">
													<?php echo esc_html( $settings['heading'] ); ?>
												</h1>
												<div style="margin: 0 0 15px 0; padding:0; color: <?php echo esc_attr( $email_text_color ); ?>;">
													<?php echo wp_kses_post( $settings['additional_content'] ); ?>
												</div>
												<?php if ( '' !== (string) $settings['button_text'] ) : ?>
												<div style="text-align: <?php echo esc_attr( $cta_btn_position ); ?>; margin: 25px 0;">
													<a href="<?php echo esc_url( $button_url ); ?>" style="background: <?php echo esc_attr( $base_color ); ?>; color: #ffffff; padding: 14px 25px; font-size: 15px; font-weight: 500; text-decoration: none; border-radius: 12px; display: inline-block;">
														<?php echo esc_html( $settings['button_text'] ); ?>
													</a>
												</div>
												<?php endif; ?>
												<div style="margin: 15px 0 0; color: <?php echo esc_attr( $email_text_color ); ?>; font-size: 13px;">
													<?php echo wp_kses_post( $settings['footer_text'] ); ?>
												</div>
											</div>
										</td>
									</tr>
								</table>
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
	</div>
</body>
</html>
