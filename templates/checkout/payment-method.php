<?php
/**
 * Template for displaying available payment methods.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/checkout/payment-method.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<?php
$cart_items = \OhMyLMS\Commerce\ecommerce()->cart->get_cart();
foreach ( $cart_items as $cart_item_key => $cart_item ) {
	if ( isset( $cart_item['type'] ) && 'ohmylms-membership' === $cart_item['type'] ) {
		?>
		<input type="hidden" id="membership_id" name="membership_id" value="<?php echo esc_attr( $cart_item['data']->get_id() ); ?>">
		<?php
	}
	if ( isset( $cart_item['type'] ) && 'ohmylms-course' === $cart_item['type'] ) {
		?>
		<input type="hidden" id="course_id" name="course_id" value="<?php echo esc_attr( $cart_item['data']->get_id() ); ?>">
		<?php
	}
}

?>

<li class="ohmylms-single-payment ohmylms_payment_method payment_method_<?php echo esc_attr( $gateway->id ); ?> <?php echo esc_attr( $is_first ? 'open' : '' ); ?> ">
	<label class="ohmylms-radiobtn" for="payment_method_<?php echo esc_attr( $gateway->id ); ?>">
		<input id="payment_method_<?php echo esc_attr( $gateway->id ); ?>" type="radio" class="input-radio" name="payment_method" value="<?php echo esc_attr( $gateway->id ); ?>" data-order_button_text="<?php echo esc_attr( $gateway->order_button_text ); ?>" />

		<span class="ohmylms-radiobtn-text">
			<span class="radiobox">
				<svg width="11" height="9" fill="none" viewBox="0 0 11 9" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" d="M9.39.78L4.055 6.19 1.61 3.71a.934.934 0 00-1.334 0 .966.966 0 000 1.354L3.388 8.22a.937.937 0 001.334 0l6.002-6.087a.966.966 0 000-1.353.934.934 0 00-1.334 0z"/></svg>
			</span>
			<?php echo esc_html( $gateway->get_title() ); ?>

			<?php
			if ( 'stripe' === $gateway->id ) {
				?>
				<img src="<?php echo esc_url( OHMYLMS_URL . '/assets/images/payment-method-card.webp' ); ?>" alt="credit card icon">
				<?php
			}
			?>
		</span>
	</label>

	<?php if ( $gateway->has_fields() ) : ?>
		<div class="payment_box payment_method_<?php echo esc_attr( $gateway->id ); ?>">
			<?php $gateway->payment_fields(); ?>
		</div>
	<?php endif; ?>
</li>
