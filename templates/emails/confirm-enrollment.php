<?php
/**
 * Emails confirm enrollment template.
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
	<title>
	<?php
	echo 'Confirm Enrollment';
	?>
	</title>
</head>

<?php
$email_logo          = $email_settings['ohmylms_email_branding_image'] ?? '';
$base_color          = $email_settings['ohmylms_email_base_color'] ?? 'var(--ohmylms-primary-color)';
$email_bg_color      = $email_settings['ohmylms_email_background_color'] ?? '#F4F5F7';
$email_body_bg_color = $email_settings['ohmylms_email_body_background_color'] ?? '#ffffff';
$email_text_color    = $email_settings['ohmylms_email_body_text_color'] ?? '#1F2328';
$cta_btn_position    = $email_settings['ohmylms_email_button_possition'] ?? 'center';

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
									<!-- Include Header -->
									<?php do_action( 'ohmylms_email_header', 'Confirm enrollment', $email_settings ); ?>
									
									<!-- Main Content -->
									<tr>
										<td style="padding: 25px 35px 30px; background: <?php echo esc_attr( $email_body_bg_color ); ?>; border: 0; border-radius: 0 0 20px 20px;">
											<div class="body-content-inner" style="color: <?php echo esc_attr( $email_text_color ); ?>; font-family: Helvetica Neue;,Helvetica,Roboto,Arial,sans-serif; font-size: 15px; line-height: 1.6; text-align: left;">
												<h1 style="padding: 0; color: <?php echo esc_attr( $base_color ); ?>; font-size: 28px; line-height: 1.3; margin: 0; font-weight: bold; text-align: center; margin-bottom: 20px;">
													<?php echo wp_kses_post( $settings['heading'] ); ?>
												</h1>

												<div style="margin: 0 0 15px 0; padding:0; color: <?php echo esc_attr( $email_text_color ); ?>; font-family: Helvetica Neue;,Helvetica,Roboto,Arial,sans-serif; font-size: 15px; line-height: 1.6;">
													<?php echo wp_kses_post( $settings['additional_content'] ); ?>
												</div>
												
												<table class="ohmylms-order-items-table" style="width: 100%; border: 0; border-collapse: separate; border-radius: 0; background: transparent; margin: 0 0 20px">
													<tr>
														<td style="border: 1px solid #EBECED; border-radius: 10px; overflow: hidden; background: transparent;">
															<table style="width: 100%; border: 0; border-collapse: collapse; border-radius: 0; background: transparent;">
																<tbody>
																	<tr>
																		<td style="background: transparent; padding: 14px 20px; border:none; border-bottom: 1px solid #EBECED; color: <?php echo esc_attr( $email_text_color ); ?>; font-size: 14px; font-weight: 400; line-height: 1.3;">
																			<span style="color: #7A8B9A;">
																				<?php echo esc_html__( 'Course Name:', 'ohmylms' ); ?>
																			</span>

																			<?php echo esc_html( $course->get_name() ); ?>
																		</td>
																	</tr>

																	<tr>
																		<td style="background: transparent; padding: 14px 20px; border:none; border-bottom: 1px solid #EBECED; color: <?php echo esc_attr( $email_text_color ); ?>; font-size: 14px; font-weight: 400; line-height: 1.3;">
																			<span style="color: #7A8B9A;">
																				<?php echo esc_html__( 'Enrollment Date:', 'ohmylms' ); ?>
																			</span>

																			<?php echo esc_html( $enroll_data['start_date'] ); ?>
																		</td>
																	</tr>

																	<tr>
																		<td style="background: transparent; padding: 14px 20px; border:none; color: <?php echo esc_attr( $email_text_color ); ?>; font-size: 14px; font-weight: 400; line-height: 1.3;">
																			<span style="color: #7A8B9A;">
																				<?php echo esc_html__( 'Access Duration:', 'ohmylms' ); ?>
																			</span>
																			
																			[Lifetime]
																		</td>
																	</tr>
																</tbody>
															</table>
														</td>
													</tr>
												</table>

												<!-- Include Footer -->
												<div style="margin: 0 0 15px 0; padding:0; color: <?php echo esc_attr( $email_text_color ); ?>; font-family: Helvetica Neue;,Helvetica,Roboto,Arial,sans-serif; font-size: 15px; line-height: 1.6;">
													<?php echo wp_kses_post( $settings['footer_text'] ); ?>
												</div>

												<div style="text-align: <?php echo esc_attr( $cta_btn_position ); ?>; margin: 25px 0 0;">
													<a href="<?php echo esc_url( get_the_permalink( $course->get_id() ) ); ?>" style="background: <?php echo esc_attr( $base_color ); ?>; color: #ffffff; padding: 14px 25px; font-size: 15px; font-weight: 500; line-height:1; text-transform: capitalize; text-decoration: none; border-radius: 12px; display: inline-block;">
														<?php echo esc_html( $settings['button_text'] ); ?>
													</a>
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
