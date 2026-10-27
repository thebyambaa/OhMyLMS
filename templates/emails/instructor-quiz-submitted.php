<?php
/**
 * Emails instructor quiz submitted template.
 *
 * @package OhMyLMS
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$email_logo          = $email_settings['ohmylms_email_branding_image'] ?? '';
$base_color          = $email_settings['ohmylms_email_base_color'] ?? '#7B68EE';
$email_bg_color      = $email_settings['ohmylms_email_background_color'] ?? '#F4F5F7';
$email_body_bg_color = $email_settings['ohmylms_email_body_background_color'] ?? '#ffffff';
$email_text_color    = $email_settings['ohmylms_email_body_text_color'] ?? '#1F2328';
$cta_btn_position    = $email_settings['ohmylms_email_button_possition'] ?? 'center';
?>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $is_digest ? __( 'Quiz Submissions Digest', 'ohmylms' ) : __( 'New Quiz Submission', 'ohmylms' ) ); ?></title>
</head>
<body>
	<div class="ohmylms-email-container">
		<table class="ohmylms-table-main" style="width: 100%; border-spacing: 0; background: <?php echo esc_attr( $email_bg_color ); ?>; border:0;">
			<tr style="background: transparent; border: none; border-radius: 0;">
				<td style="background: transparent; border: none; border-radius: 0; padding: 40px;">
					<table class="ohmylms-table2" style="border-spacing: 0; max-width: 600px; width: 100%; margin-left: auto; margin-right: auto; border-collapse: separate;">
						<tr>
							<td style="border: 5px solid <?php echo esc_attr( $base_color ); ?>; background: <?php echo esc_attr( $email_body_bg_color ); ?>; border-radius: 20px;">
								<table class="ohmylms-table3" style="width: 100%; border-collapse: collapse;">
									<?php do_action( 'ohmylms_email_header', __( 'Quiz Submission', 'ohmylms' ), $email_settings ); ?>
									<tr>
										<td style="padding: 25px 35px 30px; background: <?php echo esc_attr( $email_body_bg_color ); ?>; border: 0; border-radius: 0 0 20px 20px;">
											<div class="body-content-inner" style="color: <?php echo esc_attr( $email_text_color ); ?>; font-family: Helvetica Neue, Helvetica, Roboto, Arial, sans-serif; font-size: 15px; line-height: 1.6; text-align: left;">

												<h1 style="padding: 0; color: <?php echo esc_attr( $base_color ); ?>; font-size: 28px; line-height: 1.3; margin: 0; font-weight: bold; text-align: center; margin-bottom: 20px;">
													<?php echo esc_html( $settings['heading'] ); ?>
												</h1>

												<div style="margin: 0 0 20px 0; padding:0;">
													<?php echo wp_kses_post( $settings['additional_content'] ); ?>
												</div>

												<?php if ( $is_digest ) : ?>
													<div style="color: <?php echo esc_attr( $email_text_color ); ?>; font-size: 16px; font-weight: 500; line-height: 1; margin: 0 0 10px;">
														<?php
														/* translators: %s: course name */
														echo esc_html( sprintf( __( 'Submissions for: %s', 'ohmylms' ), $course->get_name() ) );
														?>
													</div>
													<table style="width: 100%; border: 0; border-collapse: separate; margin: 0 0 20px;">
														<tr>
															<td style="border: 1px solid #EBECED; border-radius: 10px; overflow: hidden; background: transparent;">
																<table style="width: 100%; border: 0; border-collapse: collapse;">
																	<thead>
																		<tr>
																			<td style="background: <?php echo esc_attr( $base_color ); ?>; color: #fff; padding: 10px 20px; font-size: 13px; font-weight: 600;"><?php echo esc_html__( 'Student', 'ohmylms' ); ?></td>
																			<td style="background: <?php echo esc_attr( $base_color ); ?>; color: #fff; padding: 10px 20px; font-size: 13px; font-weight: 600;"><?php echo esc_html__( 'Quiz', 'ohmylms' ); ?></td>
																			<td style="background: <?php echo esc_attr( $base_color ); ?>; color: #fff; padding: 10px 20px; font-size: 13px; font-weight: 600;"><?php echo esc_html__( 'Submitted At', 'ohmylms' ); ?></td>
																		</tr>
																	</thead>
																	<tbody>
																		<?php foreach ( $digest_items as $i => $item ) : ?>
																			<tr style="<?php echo $i % 2 === 0 ? '' : 'background: #F9FAFB;'; ?>">
																				<td style="padding: 12px 20px; border-bottom: 1px solid #EBECED; font-size: 14px; color: <?php echo esc_attr( $email_text_color ); ?>;"><?php echo esc_html( $item['student_name'] ); ?></td>
																				<td style="padding: 12px 20px; border-bottom: 1px solid #EBECED; font-size: 14px; color: <?php echo esc_attr( $email_text_color ); ?>;"><?php echo esc_html( $item['quiz_title'] ); ?></td>
																				<td style="padding: 12px 20px; border-bottom: 1px solid #EBECED; font-size: 14px; color: #7A8B9A;"><?php echo esc_html( $item['time'] ); ?></td>
																			</tr>
																		<?php endforeach; ?>
																	</tbody>
																</table>
															</td>
														</tr>
													</table>
												<?php else : ?>
													<div style="color: <?php echo esc_attr( $email_text_color ); ?>; font-size: 16px; font-weight: 500; line-height: 1; margin: 0 0 10px;">
														<?php echo esc_html__( 'Submission Details:', 'ohmylms' ); ?>
													</div>
													<table style="width: 100%; border: 0; border-collapse: separate; margin: 0 0 20px;">
														<tr>
															<td style="border: 1px solid #EBECED; border-radius: 10px; overflow: hidden; background: transparent;">
																<table style="width: 100%; border: 0; border-collapse: collapse;">
																	<tbody>
																		<tr>
																			<td style="background: transparent; padding: 14px 20px; border: none; border-bottom: 1px solid #EBECED; color: <?php echo esc_attr( $email_text_color ); ?>; font-size: 14px; font-weight: 400; line-height: 1.3;">
																				<span style="color: #7A8B9A;"><?php echo esc_html__( 'Student:', 'ohmylms' ); ?></span>
																				<strong style="font-weight: 500;"><?php echo esc_html( $student->display_name ); ?></strong>
																			</td>
																		</tr>
																		<tr>
																			<td style="background: transparent; padding: 14px 20px; border: none; border-bottom: 1px solid #EBECED; color: <?php echo esc_attr( $email_text_color ); ?>; font-size: 14px; font-weight: 400; line-height: 1.3;">
																				<span style="color: #7A8B9A;"><?php echo esc_html__( 'Course:', 'ohmylms' ); ?></span>
																				<strong style="font-weight: 500;"><?php echo esc_html( $course->get_name() ); ?></strong>
																			</td>
																		</tr>
																		<tr>
																			<td style="background: transparent; padding: 14px 20px; border: none; color: <?php echo esc_attr( $email_text_color ); ?>; font-size: 14px; font-weight: 400; line-height: 1.3;">
																				<span style="color: #7A8B9A;"><?php echo esc_html__( 'Quiz:', 'ohmylms' ); ?></span>
																				<strong style="font-weight: 500;"><?php echo esc_html( $quiz_title ); ?></strong>
																			</td>
																		</tr>
																	</tbody>
																</table>
															</td>
														</tr>
													</table>
												<?php endif; ?>

												<?php do_action( 'ohmylms_email_footer', $settings, $email_settings ); ?>

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
