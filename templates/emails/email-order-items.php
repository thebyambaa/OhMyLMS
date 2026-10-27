<?php
/**
 * Emails email order items template.
 *
 * @package OhMyLMS
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$base_color          = $email_settings['ohmylms_email_base_color'] ?? 'var(--ohmylms-primary-color)';
$email_bg_color      = $email_settings['ohmylms_email_background_color'] ?? '#F4F5F7';
$email_body_bg_color = $email_settings['ohmylms_email_body_background_color'] ?? '#ffffff';
$email_text_color    = $email_settings['ohmylms_email_body_text_color'] ?? '<?php echo $email_text_color; ?>';

?>

<table class="ohmylms-order-items-table" style="width: 100%; border: 0; border-collapse: separate; border-radius: 0; background: transparent; margin: 0 0 20px">
	<tr>
		<td style="border: 1px solid #EBECED; border-radius: 10px; overflow: hidden; background: transparent;">
			<table style="width: 100%; border: 0; border-collapse: collapse; border-radius: 0; background: transparent;">
				<thead>
					<tr>
						<th style="background: #F9FAFB; color: #7A8B9A; font-size: 14px; line-height: 1; padding: 13px 20px; border: none; border-bottom: 1px solid #EBECED; text-align: left; border-radius: 10px 0 0 0; font-weight: 500;">
							<?php echo esc_html__( 'Course Name', 'ohmylms' ); ?>
						</th>

						<th style="background: #F9FAFB; color: #7A8B9A; font-size: 14px; line-height: 1; padding: 13px 20px; border: none; border-bottom: 1px solid #EBECED; text-align: left; font-weight: 500;">
							<?php echo esc_html__( 'Quantity', 'ohmylms' ); ?>
						</th>

						<th style="background: #F9FAFB; color: #7A8B9A; font-size: 14px; line-height: 1; padding: 13px 20px; border: none; border-bottom: 1px solid #EBECED; text-align: left; border-radius: 0 10px 0 0; font-weight: 500;">
							<?php echo esc_html__( 'Price', 'ohmylms' ); ?>
						</th>
					</tr>
				</thead>

				
				<tbody>
					<?php foreach ( $order->get_items() as $item ) : ?>
						<tr>
							<td style="background: transparent; padding: 14px 20px; border:none; border-bottom: 1px solid #EBECED; color: <?php echo esc_attr( $email_text_color ); ?>; font-size: 14px; font-weight: 400; line-height: 1.3;">
								<?php echo esc_html( $item->get_name() ); ?>
							</td>

							<td style="background: transparent; padding: 14px 20px; border:none; border-bottom: 1px solid #EBECED; color: <?php echo esc_attr( $email_text_color ); ?>; font-size: 14px; font-weight: 400; line-height: 1.3;">
								<?php echo esc_html( $item->get_quantity() ); ?>
							</td>

							<td style="background: transparent; padding: 14px 20px; border:none; border-bottom: 1px solid #EBECED; color: <?php echo esc_attr( $email_text_color ); ?>; font-size: 14px; font-weight: 400; line-height: 1.3; width: 110px;">
								<?php echo esc_html( wp_strip_all_tags( ohmylms_price( $item->get_total(), array( 'currency' => $order->get_currency() ) ) ) ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>

				<tfoot>
					
					<tr>
						<th colspan="2" style="background: transparent; padding: 14px 20px 7px; color: <?php echo esc_attr( $email_text_color ); ?>; border: none; text-align: right; font-size: 14px; font-weight: 500; line-height: 1;">
							<?php echo esc_html__( 'Subtotal:', 'ohmylms' ); ?>
						</th>

						<td style="background: transparent; padding: 14px 20px 7px; color: <?php echo esc_attr( $email_text_color ); ?>; border: none; font-size: 14px; font-weight: 400; line-height: 1; width: 110px;">
							<?php echo esc_html( wp_strip_all_tags( ohmylms_price( $order->get_cart_subtotal(), array( 'currency' => $order->get_currency() ) ) ) ); ?>
						</td>
					</tr>
					<?php if ( $order->get_cart_discount() > 0 ) : ?>

					<tr>
						<th colspan="2" style="background: transparent; padding: 14px 20px 7px; color: <?php echo esc_attr( $email_text_color ); ?>; border: none; text-align: right; font-size: 14px; font-weight: 500; line-height: 1;">
							<?php echo esc_html__( 'Discount:', 'ohmylms' ); ?>
						</th>

						<td style="background: transparent; padding: 14px 20px 7px; color: <?php echo esc_attr( $email_text_color ); ?>; border: none; font-size: 14px; font-weight: 400; line-height: 1; width: 110px;">
							-<?php echo esc_html( wp_strip_all_tags( ohmylms_price( $order->get_cart_discount(), array( 'currency' => $order->get_currency() ) ) ) ); ?>
						</td>
					</tr>

					<?php endif; ?>

					<?php if ( $order->get_tax_amount() ) : ?>

					<tr>
						<th colspan="2" style="background: transparent; padding: 14px 20px 7px; color: <?php echo esc_attr( $email_text_color ); ?>; border: none; text-align: right; font-size: 14px; font-weight: 500; line-height: 1;">
							<?php echo esc_html__( 'Tax', 'ohmylms' ); ?> <span class="tax-rate" style="color: <?php echo esc_attr( $email_text_color ); ?>;">(<?php echo esc_html( wp_strip_all_tags( $order->get_tax_rate() ) ); ?>%):</span>
						</th>

						<td style="background: transparent; padding: 14px 20px 7px; color: <?php echo esc_attr( $email_text_color ); ?>; border: none; font-size: 14px; font-weight: 400; line-height: 1; width: 110px;">
							<?php echo esc_html( wp_strip_all_tags( ohmylms_price( $order->get_tax_amount(), array( 'currency' => $order->get_currency() ) ) ) ); ?>
						</td>
					</tr>

					<?php endif; ?>
					
					<tr>
						<th colspan="2" style="background: transparent; padding: 7px 20px 14px; color: <?php echo esc_attr( $email_text_color ); ?>; border: none; text-align: right; font-size: 14px; font-weight: 500; line-height: 1;">
							<?php echo esc_html__( 'Payment method:', 'ohmylms' ); ?>
						</th>

						<td style="background: transparent; padding: 7px 20px 14px; color: <?php echo esc_attr( $email_text_color ); ?>; border: none; font-size: 14px; font-weight: 400; line-height: 1; width: 110px;">
							<?php echo esc_html( $order->get_payment_method_title() ); ?>
						</td>
					</tr>
					
					<tr>
						<th colspan="2" style="background: transparent; padding: 14px 20px; color: <?php echo esc_attr( $email_text_color ); ?>; border: none; border-top: 1px solid #EBECED; text-align: right; font-size: 14px; font-weight: 500; line-height: 1;">
							<?php echo esc_html__( 'Total:', 'ohmylms' ); ?>
						</th>

						<td style="background: transparent; padding: 14px 20px; color: <?php echo esc_attr( $email_text_color ); ?>; border: none; border-top: 1px solid #EBECED; font-size: 14px; font-weight: 400; line-height: 1; width: 110px;">
							<?php echo esc_html( wp_strip_all_tags( ohmylms_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ) ); ?>
						</td>
					</tr>

				</tfoot>
			</table>
		</td>
	</tr>
</table>
