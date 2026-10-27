<?php

/**
 * Template for displaying order review.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/checkout/review-order.php
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 * @global \OhMyLMS\Commerce\Checkout $checkout
 */

use OhMyLMS\Commerce\Includes\Tax\TaxService;

defined( 'ABSPATH' ) || exit;
$total_savings = 0;
?>

<div class="ohmylms-order-review-table-wrapper">
	<table class="ohmylms-checkout-order-review-table">
		<thead>
			<tr>
				<th class="ohmylms-course-name"><?php esc_html_e( 'Course', 'ohmylms' ); ?></th>
				<th class="ohmylms-course-total"><?php esc_html_e( 'Subtotal', 'ohmylms' ); ?></th>
			</tr>
		</thead>

		<tbody>
			<?php
				$cart_items       = \OhMyLMS\Commerce\ecommerce()->cart->get_cart();
				$_course          = null;
				$by_point         = false;
				$is_including_tax = \OhMyLMS\Commerce\Includes\Tax\TaxService::get_instance()->prices_include_tax();
			foreach ( $cart_items as $cart_item_key => $cart_item ) {
				$by_point = isset( $cart_item['purchase_by'] ) && 'point' === $cart_item['purchase_by'];
				$_course  = $cart_item['data'];
				if ( $_course ) {

					// If the course is purchased by point, we can skip the.
					?>
						<tr class="ohmylms-cart-item">
							<td class="ohmylms-course-name">
								<div class="ohmylms-course-info-wrapper">
								<?php if ( $_course->get_thumbnail_url() ) : ?>
										<figure class="ohmylms-course-thumbnail">
											<img src="<?php echo esc_url( $_course->get_thumbnail_url() ); ?>" alt="course image">
										</figure>
									<?php endif; ?>

								<div class="ohmylms-course-title" title="<?php echo esc_attr( $_course->get_name() ); ?>">
								<?php echo esc_html( $_course->get_name() ) . '&nbsp;'; ?>
								</div>
							</div>
						</td>

							<td class="ohmylms-course-total">
							<?php
							if ( $by_point ) {
								echo esc_html( $_course->get_purchase_point() ) . ' Pts';
							} else {
								$course_price = $_course->is_on_sale() && $_course->validate_on_sale() ? $_course->get_price() : $_course->get_regular_price();
								$price_html   = ohmylms_price( $course_price );
								/**
								 * Filter the course price HTML in the checkout review order table.
								 *
								 * @param string $price_html The formatted price HTML.
								 * @param float $course_price The raw price value.
								 * @param object $_course The course object.
								 */
								echo apply_filters( 'ohmylms_checkout_course_price_html', $price_html, $course_price, $_course );
							}

							?>
							</td>
						</tr>
						<?php
				}
			}
			?>
		</tbody>

		<tfoot>
			<?php
			if ( ! $_course ) {
				return;
			}
			$price = $_course->is_on_sale() && $_course->validate_on_sale()
				? $_course->get_price()
				: $_course->get_regular_price();

			ohmylms_price( $price );

				// Only show coupon form if price is greater than 0.
			if ( floatval( $price ) > 0 && ! $by_point ) :
				?>
				<tr class="coupon-tr">
					<td colspan="2">
						<form id="" method="post" class="ohmylms-checkout-coupon checkout_coupon">
							<input name="coupon_code" type="text" id="apply-coupon" class="apply-coupon" placeholder="Coupon code">
							<button type="submit" id="apply-coupon-btn" class="apply-coupon-btn">
							<?php echo esc_html__( 'Apply', 'ohmylms' ); ?>
							</button>
						</form>
					</td>
				</tr>
			<?php endif; ?>

			<tr class="cart-subtotal">
				<th><?php esc_html_e( 'Subtotal', 'ohmylms' ); ?></th>
				<td>
					<?php
					if ( ! $by_point ) {
						$cart_subtotal = \OhMyLMS\Commerce\ecommerce()->cart->get_totals_by_key( 'subtotal' );
						echo apply_filters( 'ohmylms_checkout_cart_subtotal', ohmylms_price( $cart_subtotal ), $cart_subtotal, $_course );
					} else {
						echo esc_html( $_course->get_purchase_point() ) . ' Pts';
					}
					?>
				</td>
			</tr>

			<?php
			foreach ( \OhMyLMS\Commerce\ecommerce()->cart->get_coupons() as $code => $coupon ) :
				if ( ! $coupon->get_code() ) {
					continue; // Skip if the coupon code is not valid.
				}
				?>
				<tr class="cart-discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
					<th>
						<span class="coupon-th">
							<?php ohmylmse_cart_totals_coupon_label( $coupon ); ?>
						</span>
					</th>
					<td><?php ohmylmse_cart_totals_coupon_html( $coupon ); ?></td>
				</tr>
			<?php endforeach; ?>

			<?php
			if ( \OhMyLMS\Commerce\ecommerce()->cart->get_totals_by_key( 'tax_amount' ) && ! $by_point && ! $is_including_tax ) {

				?>
				<tr class="cart-tax">
					<th>
						<?php echo esc_html( TaxService::get_instance()->get_tax_label() ); ?>
						<span class="cart-tax-span">
							<?php
							$tax_rate = \OhMyLMS\Commerce\ecommerce()->cart->get_totals_by_key( 'tax_rate' );
							echo esc_html( $tax_rate ) . '%';
							?>
						</span>
					</th>
					<td>
						<?php
						$tax_amount = \OhMyLMS\Commerce\ecommerce()->cart->get_totals_by_key( 'tax_amount' );
						echo apply_filters( 'ohmylms_checkout_order_total', ohmylms_price( $tax_amount ), $tax_amount, $_course );
						?>
					</td>
				</tr>
			<?php } ?>

			<tr class="order-total">
				<th>
					<?php esc_html_e( 'Total', 'ohmylms' ); ?>
					<?php if ( $is_including_tax ) : ?>
						<span class="total-savings"><?php printf( '( Including tax : %s )', ohmylms_price( \OhMyLMS\Commerce\ecommerce()->cart->get_totals_by_key( 'tax_amount' ) ) ); ?></span>
					<?php endif; ?>
				</th>
				<td>
					<?php
					if ( ! $by_point ) {
						$total = \OhMyLMS\Commerce\ecommerce()->cart->get_totals_by_key( 'total' );
						echo apply_filters( 'ohmylms_checkout_order_total', ohmylms_price( $total ), $total, $_course );
					} else {
						echo esc_html( $_course->get_purchase_point() ) . ' Pts';
					}

					?>
				</td>
			</tr>

			<?php do_action( 'ohmylms_after_review_order', $_course ); ?>
		</tfoot>
	</table>
</div>
