<?php
/**
 * Template for displaying contact fields.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/checkout/form-contact.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 * @global \OhMyLMS\Commerce\Checkout $checkout
 */

defined( 'ABSPATH' ) || exit();
?>

<div class="ohmylms-billing-contact">
	<?php do_action( 'ohmylms_before_checkout_contact_form', $checkout ); ?>
	<p class="ohmylms-form-row validate-required ohmylms-folded" id="email_field">
		<span class="ohmylms-input-wrapper">
			<?php
			$fields = $checkout->get_checkout_fields( 'contact' );
			foreach ( $fields as $key => $field ) {
				ecommerce_form_field( $key, $field, $checkout->get_value( $key ) );
			}
			?>
		</span>
	</p>
</div>
