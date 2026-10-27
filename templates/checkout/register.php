<?php
/**
 * Template for displaying account registration form.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/checkout/register.php
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

defined( 'ABSPATH' ) || exit();
?>

<div id="checkout-account-register" class="ohmylms-checkout-block left">
	<h4><?php esc_html_e( 'Sign up', 'ohmylms' ); ?></h4>

	<ul class="ohmylms-form-fields">
		<?php do_action( 'ohmylms_before_registration_fields' ); ?>

		<li class="form-field">
			<label for="reg_email"><?php esc_html_e( 'Email address', 'ohmylms' ); ?>&nbsp;<span class="required">*</span></label>
			<input id ="reg_email" name="reg_email" type="text" placeholder="<?php esc_attr_e( 'Email', 'ohmylms' ); ?>" autocomplete="email" value="">
		</li>
		<li class="form-field">
			<label for="reg_username"><?php esc_html_e( 'Username', 'ohmylms' ); ?>&nbsp;<span class="required">*</span></label>
			<input id ="reg_username" name="reg_username" type="text" placeholder="<?php esc_attr_e( 'Username', 'ohmylms' ); ?>" autocomplete="username" value="">
		</li>
		<li class="form-field">
			<label for="reg_password"><?php esc_html_e( 'Password', 'ohmylms' ); ?>&nbsp;<span class="required">*</span></label>
			<input id ="reg_password" name="reg_password" type="password" placeholder="<?php esc_attr_e( 'Password', 'ohmylms' ); ?>" autocomplete="new-password">
		</li>
		<li class="form-field">
			<label for="reg_password2"><?php esc_html_e( 'Confirm Password', 'ohmylms' ); ?>&nbsp;<span class="required">*</span></label>
			<input id ="reg_password2" name="reg_password2" type="password" placeholder="<?php esc_attr_e( 'Password', 'ohmylms' ); ?>" autocomplete="off">
		</li>

		<?php do_action( 'ohmylms_after_registration_fields' ); ?>
	</ul>

	<?php
	// Add hook of WordPress.
	do_action( 'register_form' );
	?>

</div>

