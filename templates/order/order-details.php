<?php
/**
 * Template for displaying Order details
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/order/order-details.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 *
 * @var \OhMyLMS\Commerce\Data\Order $order Order object.
 */

defined( 'ABSPATH' ) || exit();
?>

<table>
	<tbody>
		<?php
		$items            = $order->get_items();
		$maybe_by_point   = get_post_meta( $order->get_id(), '_purchased_by', true );
		$points           = get_post_meta( $order->get_id(), '_purchased_point', true );
		$is_including_tax = OhMyLMS\Commerce\Includes\Tax\TaxService::get_instance()->prices_include_tax();
		foreach ( $items as $item ) {
			$course    = $item->get_course();
			$is_course = true;
			if ( ! $course ) {
				$id     = $item->get_course_id();
				$course = ohmylms_get_membership( $id );
				if ( ! $course ) {
					continue;
				}
				$is_course = false;
			}
			?>
			<tr>
				<td>
					<div class="course-title-wrapper">
						<?php if ( $is_course && $course->get_thumbnail_url() ) : ?>
							<figure class="ohmylms-course-img">
								<img src="<?php echo esc_url( $course->get_thumbnail_url() ); ?>" alt="course image">
							</figure>
						<?php endif; ?>

						<p class="ohmylms-course-title" title="<?php echo esc_attr( $item->get_name() ); ?>">
							<?php echo esc_html( $item->get_name() ); ?>
						</p>
					</div>
				</td>

				<td>
					<span class="ohmylms-price">
						<?php echo $maybe_by_point ? $points . ' Pts' : $order->get_formatted_line_subtotal( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</span>
				</td>
			</tr>
			<?php
		}
		?>
	</tbody>

	<tfoot>
		<?php
		$update_value_keys = array(
			'cart_subtotal',
			'order_total',
			'tax',
		);

		foreach ( $order->get_order_item_totals() as $key => $total ) {
			if ( 'tax' === $key && ( $maybe_by_point || $is_including_tax ) ) {
				continue;
			}

			?>
			<tr class="<?php echo esc_attr( $key ); ?>">
				<?php
				if ( in_array( $key, $update_value_keys ) && ! $maybe_by_point ) :
					?>
					<th scope="row">
						<?php
						// Allow HTML for tax label to display tax rate span.
						if ( 'tax' === $key ) {
							echo wp_kses_post( $total['label'] );
						} else {
							echo esc_html( $total['label'] );
						}
						?>
					</th>
				<?php else : ?>
					<th scope="row">
					<?php
					if ( 'tax' !== $key ) :
						echo esc_html( $total['label'] );
					endif;
					?>
					</th>
				<?php endif; ?>
				<?php if ( 'order_id' === $key ) { ?>
					<td>
						<span class="thankyou-order-id-text">
							#<?php echo wp_kses_post( $total['value'] ); ?>

							<button type="button" class="thankyou-copy-order-id">
								<svg width="21" height="22" fill="none" viewBox="0 0 21 22" xmlns="http://www.w3.org/2000/svg"><path fill="#7A8B9A" stroke="#fff" stroke-width=".2" d="M5.25 6.287h.775v1.55H5.25a.974.974 0 00-.975.976v8.75c0 .538.437.974.975.974h7a.974.974 0 00.975-.974v-.776h1.55v.776a2.526 2.526 0 01-2.525 2.525h-7a2.526 2.526 0 01-2.525-2.526v-8.75A2.526 2.526 0 015.25 6.287z"/><path fill="#7A8B9A" stroke="#fff" stroke-width=".2" d="M8.75 2.787h5.038l.204.009h.009c.591.051 1.15.308 1.572.731l1.962 1.962c.423.423.68.981.73 1.573l.002.008c.005.068.008.136.008.204v6.788a2.526 2.526 0 01-2.525 2.526h-7a2.526 2.526 0 01-2.525-2.526v-8.75A2.526 2.526 0 018.75 2.788zm5.35 1.599l-.076-.02a.975.975 0 00-.236-.028H8.75a.974.974 0 00-.975.974v8.75c0 .539.437.975.975.975h7a.974.974 0 00.975-.975V7.274c0-.08-.01-.16-.029-.236l-.02-.075h-.926a1.65 1.65 0 01-1.65-1.65v-.927z"/></svg>
							</button>
						</span>

						<span class="thankyou-copy-alert">
							<?php echo esc_html__( 'Copied!', 'ohmylms' ); ?>
						</span>
					</td>

				<?php } else { ?>
					<?php
					if ( in_array( $key, $update_value_keys ) && $maybe_by_point ) :
						if ( 'tax' === $key ) {
							continue;
						}
						?>
						<td class="ohmylms-tax-rate">
							<?php echo esc_html( $points . ' Pts' ); ?>
						</td>
					<?php else : ?>
						<td>
							<?php
							if ( 'order_total' === $key ) {
								$total           = $order->get_total();
								$formatted_total = ohmylms_price( $total );
								echo wp_kses_post( $formatted_total );
							} else {
								echo wp_kses_post( $total['value'] );
							}
							?>
						</td>
					<?php endif; ?>
				<?php } ?>
			</tr>
		<?php } ?>
	</tfoot>
</table>
<?php do_action( 'ohmylms_after_order_details', $order ); ?>
