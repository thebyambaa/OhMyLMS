<?php
/**
 * Template for displaying payment option.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/checkout/payment.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

defined( 'ABSPATH' ) || exit();

$cart               = \OhMyLMS\Commerce\ecommerce()->cart->get_cart();
$has_membership     = false;
$by_point           = false;
$is_recurring_daily = false;
foreach ( $cart as $cart_item_key => $cart_item ) {
	// Check if cart item data exists.
	if ( ! isset( $cart_item['data'] ) || ! is_object( $cart_item['data'] ) ) {
		continue;
	}

	if ( get_post_type( $cart_item['data']->get_id() ) == 'ohmylms-membership' ) {
		$membership = ohmylms_get_membership( $cart_item['data']->get_id() );
		if ( $membership && $membership->get_subscription_period() !== 'one_time' ) {
			$has_membership = true;
			if ( $membership->get_subscription_period() === 'day' ) {
				$is_recurring_daily = true;
			}
		}
	}
	if ( isset( $cart_item['purchase_by'] ) && 'point' === $cart_item['purchase_by'] ) {
		$by_point = true;
	}
}

?>
	<div id="ohmylms-payment" class="ohmylms-checkout-payment">

		<?php if ( ! $by_point && \OhMyLMS\Commerce\ecommerce()->cart->needs_payment() ) : ?>
			<div class="ohmylms-payment-method-wrapper">
				<h3 class="ohmylms-checkout-title">
					<?php esc_html_e( 'Payment Information', 'ohmylms' ); ?>
				</h3>

				<ul class="ohmylms-payment-methods">
					<?php
					if ( ! empty( $available_gateways ) ) {
						$is_first = true;
						foreach ( $available_gateways as $gateway ) {
							if ( $has_membership && ! $gateway->subscription_support ) {
								continue;
							}

							ohmylms_get_template(
								'checkout/payment-method.php',
								array(
									'gateway'  => $gateway,
									'is_first' => $is_first,
								)
							);
							$is_first = false;
						}
					} else {
						echo '<li class="no-payment-method-text">';
							echo esc_html( 'Sorry, it seems that there are no available payment methods. Please contact us if you require assistance or wish to make alternate arrangements.' );
						echo '</li>';
					}
					?>
				</ul>
			</div>
		<?php endif; ?>

		<div class="ohmylms-place-order">
			<?php do_action( 'ohmylms_review_order_before_submit' ); ?>

			<input type="hidden" name="action" value="ohmylms_checkout">
			<?php do_action( 'ohmylms_review_order_after_submit' ); ?>
			<?php wp_nonce_field( 'ohmylms-process_checkout', 'ohmylms-process-checkout-nonce' ); ?>

			<button
				type="submit"
				class="ohmylms-button ohmylms-place-order-button"
				name="ohmylms_checkout_place_order"
				aria-label="Complete Checkout"
			>
				<?php echo esc_html__( 'Complete Checkout', 'ohmylms' ); ?>
				<span class="ohmylms-loader"></span>
			</button>

			<?php ohmylms_get_template( 'checkout/terms.php' ); ?>
		</div>
	</div>
<?php

