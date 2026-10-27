<?php
/**
 * Template for displaying place order button in mobile devices.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/checkout/mobile-place-order-button.php
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 * @global \OhMyLMS\Commerce\Checkout $checkout
 */

defined( 'ABSPATH' ) || exit;

?>

<div class="ohmylms-mobile-order-button">
	<div class="ohmylms-mobile-order-button-container">
		<div class="mobile-order-button-price">
			<p class="review-toggle-title">
				<?php echo esc_html__( 'Total (01 course)' ); ?>
			</p>

			<span class="total-price">
				<?php
				if ( isset( $_GET['membership_id'] ) ) {
					$membership_id = sanitize_text_field( wp_unslash( $_GET['membership_id'] ));
					$total         = MembershipHelper::get_plan_price( $membership_id );
				} else {
					$cart_items = \OhMyLMS\Commerce\ecommerce()->cart->get_cart_contents();
					$total      = \OhMyLMS\Commerce\ecommerce()->cart->get_total( $cart_items );
				}

				echo wp_kses(
					ohmylms_price( $total ),
					array(
						'span'   => array( 'class' => array() ),
						'b'      => array(),
						'strong' => array(),
					)
				);
				?>
			</span>
		</div>
		
		<button
			type="submit"
			class="ohmylms-button ohmylms-place-order-button"
			name="ohmylms_checkout_place_order"
			aria-label="Complete Checkout"
		>
			<?php echo esc_html__( 'Complete Checkout', 'ohmylms' ); ?>
			<span class="ohmylms-loader"></span>
		</button>
	</div>
</div>
