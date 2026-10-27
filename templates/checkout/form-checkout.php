<?php
/**
 * Template for displaying checkout form.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/checkout/form.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 * @global \OhMyLMS\Commerce\Checkout $checkout
 */

defined( 'ABSPATH' ) || exit();

// do_action( 'ohmylms_account_header' );.

/**
 * Hook: ohmylms_before_checkout_form_start.
 */
do_action( 'ohmylms_before_checkout_form_start', $checkout );

// if ( !$checkout->is_guest_checkout_enabled() || $checkout->is_registration_enabled() ||  ! is_user_logged_in() ) {
// echo esc_html( apply_filters( 'ohmylms_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'ohmylms' ) ) );
// return;
// }.
?>
<div class="ohmylms-checkout-form-outer">
	<div class="ohmylms-container">
		<div class="ohmylms-checkout-form-wrapper">
			<div class="ohmylms-checkout-form-left">
				<?php do_action( 'ohmylms_before_checkout_form', $checkout ); ?>

				<form method="post" id="ohmylms-checkout-form" name="ohmylms-checkout" class="ohmylms-checkout-form checkout" action="/" enctype="multipart/form-data">
					<?php
					if ( $checkout->get_checkout_fields() ) :
						/**
						 * Hook: ohmylms_checkout_contact.
						 *
						 * This hook is triggered to display the contact details section.
						 * You can use this hook to add custom content or modify the contact section.
						 *
						 * Example usage:
						 * add_action( 'ohmylms_checkout_contact', 'my_custom_function' );
						 * function my_custom_function() {
						 *     // Your custom code here
						 * }
						 *
						 * @since 1.0.0
						 * @hook ohmylms_checkout_contact
						 * @param \OhMyLMS\Commerce\Checkout $checkout The checkout instance.
						 * @return void
						 */
						do_action( 'ohmylms_checkout_contact' );

						/**
						 * Hook: ohmylms_checkout_before_billing.
						 *
						 * This hook is triggered before the billing details section.
						 * You can use this hook to add custom content or modify the billing section.
						 *
						 * Example usage:
						 * add_action( 'ohmylms_checkout_before_billing', 'my_custom_function' );
						 * function my_custom_function() {
						 *     // Your custom code here
						 * }
						 *
						 * @since 1.0.0
						 * @hook ohmylms_checkout_before_billing
						 * @param \OhMyLMS\Commerce\Checkout $checkout The checkout instance.
						 * @return void
						 */
						do_action( 'ohmylms_checkout_before_billing' );
						?>

						<div class="ohmylms-checkout-customer-details" id="customer_details">
							<div class="ohmylms-billing-info">
								<?php do_action( 'ohmylms_checkout_billing' ); ?>
							</div>
						</div>

						<?php do_action( 'ohmylms_checkout_after_billing' ); ?>

					<?php endif; ?>

					<input type="hidden" name="action" value="ohmylms_checkout">
					<input type="hidden" name="student_id" value="<?php echo esc_attr( get_current_user_id() ); ?>">
				</form>
			</div>

			<div class="ohmylms-checkout-form-right">
				<div class="ohmylms-checkout-order-review">
					<h3 class="ohmylms-checkout-title order-review-title">
						<?php esc_html_e( 'Order Summary', 'ohmylms' ); ?>
					</h3>

					<div class="ohmylms-checkout-review-order" id="order_review">
						<?php do_action( 'ohmylms_checkout_order_review', $checkout ); ?>
					</div>
				</div>

				<?php do_action( 'ohmylms_checkout_after_order_review' ); ?>
			</div>
		</div>

	</div>
	<!-- container end -->
</div>

<?php do_action( 'ohmylms_after_checkout_form', $checkout ); ?>
