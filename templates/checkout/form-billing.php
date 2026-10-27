<?php
/**
 * Template for displaying billing fields.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/checkout/form-billing.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 * @global \OhMyLMS\Commerce\Checkout $checkout
 */

defined( 'ABSPATH' ) || exit();
?>

<div class="ohmylms-billing-fields">
	<?php do_action( 'ohmylms_before_checkout_billing_form', $checkout ); ?>

	<div class="ohmylms-billing-field-wrapper">
		
		<?php
			$fields = $checkout->get_checkout_fields( 'billing' );
		foreach ( $fields as $key => $field ) {
			ecommerce_form_field( $key, $field, $checkout->get_value( $key ) );
		}
		?>
	</div>

	<?php do_action( 'ohmylms_after_checkout_billing_form', $checkout ); ?>
</div>

<?php if ( ! is_user_logged_in() && $checkout->is_registration_enabled() ) : ?>
	<div class="ohmylms-account-fields">
<!--		<p class="form-row form-row-wide create-account">-->
<!--			<label class="ohmylms-form__label ohmylms-form__label-for-checkbox checkbox">-->
<!--				<input class="ohmylms-form__input ohmylms-form__input-checkbox input-checkbox" id="createaccount" --><?php // checked( ( true === $checkout->get_value( 'createaccount' ) || ( true === apply_filters( 'ohmylms_create_account_default_checked', false ) ) ), true );. ?><!-- type="checkbox" name="createaccount" value="1" />-->
<!--				<span>--><?php // esc_html_e( 'Create an account?', 'ohmylms' );. ?><!--</span>-->
<!--			</label>-->
<!--		</p>-->

		<?php do_action( 'ohmylms_before_checkout_registration_form', $checkout ); ?>

		<?php if ( $checkout->get_checkout_fields( 'account' ) ) : ?>

			<div class="create-account">
				<?php foreach ( $checkout->get_checkout_fields( 'account' ) as $key => $field ) : ?>
					<?php ecommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?>
				<?php endforeach; ?>
			</div>

		<?php endif; ?>

		<?php do_action( 'ohmylms_after_checkout_registration_form', $checkout ); ?>
	</div>
<?php endif; ?>
