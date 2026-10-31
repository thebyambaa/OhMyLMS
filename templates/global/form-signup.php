<?php
/**
 * Signup form
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/global/form-signup.php.
 *
 * @package OhMyLMS\Templates
 * * @version  1.0.0
 * * @global \OhMyLMS\Commerce\Checkout $checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>
<?php ohmylms_get_template( 'global/ohmylms-celebration.php' ); ?>

<form class="ohmylms-login-signup ohmylms-form-signup signup" method="post" <?php echo ( $hidden ) ? 'style="display:none;"' : ''; ?>
	data-wp-interactive="ohmylms/forms"
	data-wp-context='{"consent":false}'
	data-wp-init--fold="callbacks.foldFilled"
	data-wp-init--consent="callbacks.syncConsent"
	data-wp-on--focusin="actions.fieldFocus"
	data-wp-on--focusout="actions.fieldBlur"
	data-wp-on--input="actions.fieldInput">

	<?php do_action( 'ohmylms_signup_form_start' ); ?>

	<?php echo ( $message ) ? wpautop( wptexturize( $message ) ) : ''; // @codingStandardsIgnoreLine ?>
	<?php
	if ( defined( 'OHMYLMS_SOURCE_ASSETS' ) && OHMYLMS_SOURCE_ASSETS ) :
		?>
		<div data-ohmylms-registration-fields style="display:contents"><?php endif; ?>

	<div class="ohmylms-form-row ohmylms-form-names-row">
		<p class="ohmylms-form-row row-first-name">
			<span class="ohmylms-input-wrapper">
				<label for="signup-first-name" class="ohmylms-input-label">
					<?php esc_html_e( 'First Name', 'ohmylms' ); ?>&nbsp;
					<span class="required" aria-hidden="true">*</span>
				</label>

				<input type="text" class="ohmylms-input-text" name="first_name" id="signup-first-name" required aria-required="true" />
			</span>
		</p>

		<p class="ohmylms-form-row row-last-name">
			<span class="ohmylms-input-wrapper">
				<label for="signup-last-name" class="ohmylms-input-label">
					<?php esc_html_e( 'Last Name', 'ohmylms' ); ?>&nbsp;
					<span class="required" aria-hidden="true">*</span>
				</label>

				<input type="text" class="ohmylms-input-text" name="last_name" id="signup-last-name" required aria-required="true" />
			</span>
		</p>
	</div>

	<p class="ohmylms-form-row row-email">
		<span class="ohmylms-input-wrapper">
			<label for="signup-email" class="ohmylms-input-label">
				<?php esc_html_e( 'Email', 'ohmylms' ); ?>&nbsp;
				<span class="required" aria-hidden="true">*</span>

				<span class="screen-reader-text">
					<?php esc_html_e( 'Required', 'ohmylms' ); ?>
				</span>
			</label>

			<input type="email" class="ohmylms-input-text email" name="email" id="signup-email" aria-required="true" required pattern="^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$" />
		</span>
	</p>

	<p class="ohmylms-form-row row-password">
		<span class="ohmylms-input-wrapper">
			<label for="signup-password" class="ohmylms-input-label">
				<?php esc_html_e( 'Password', 'ohmylms' ); ?>&nbsp;
				<span class="required" aria-hidden="true">*</span>

				<span class="screen-reader-text">
					<?php esc_html_e( 'Required', 'ohmylms' ); ?>
				</span>
			</label>

			<span class="ohmylms-password-show" data-wp-context='{"revealed":false}'>
				<input class="ohmylms-input-text password" type="password" name="password" id="signup-password" autocomplete="new-password" required aria-required="true" data-wp-bind--type="state.passwordType">

				<label class="show-password-icon" tabindex="0" role="button" aria-pressed="false" aria-label="<?php esc_attr_e( 'Show password', 'ohmylms' ); ?>"
					data-wp-bind--aria-pressed="context.revealed"
					data-wp-on--click="actions.togglePassword"
					data-wp-on--keydown="actions.passwordKey">
					<input type="checkbox" name="show-password-checkbox" class="show-password-checkbox" aria-hidden="true" tabindex="-1" data-wp-bind--checked="context.revealed">

					<span class="show-password" aria-hidden="true">
						<span class="eye-on" data-wp-style--display="state.eyeOnDisplay">
							<?php
								require OHMYLMS_DIR . '/assets/images/icon/eye-icon2.php';
							?>
						</span>

						<span class="eye-off" data-wp-style--display="state.eyeOffDisplay">
							<?php
								require OHMYLMS_DIR . '/assets/images/icon/eye-off-icon.php';
							?>
						</span>
					</span>
				</label>
			</span>
		</span>
	</p>

	<p class="ohmylms-form-row row-phone">
		<span class="ohmylms-input-wrapper">
			<label for="signup-phone" class="ohmylms-input-label">
				<?php esc_html_e( 'Phone Number', 'ohmylms' ); ?>
			</label>

			<input type="text" class="ohmylms-input-text phone" name="phone" id="signup-phone" inputmode="tel" autocomplete="tel" data-wp-on--input="actions.filterPhone" />
		</span>
	</p>

	<p class="ohmylms-form-row row-country">
		<span class="ohmylms-input-wrapper">
			<label for="signup-country" class="ohmylms-input-label">
				<?php esc_html_e( 'Country', 'ohmylms' ); ?>
			</label>

			<select name="country" id="signup-country" class="ohmylms-input-text">
				<option value=""><?php esc_html_e( '— Select country —', 'ohmylms' ); ?></option>
				<?php foreach ( ohmylms_get_countries() as $country ) : ?>
					<option value="<?php echo esc_attr( $country['code'] ); ?>"><?php echo esc_html( $country['title'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</span>
	</p>

	<?php
	if ( defined( 'OHMYLMS_SOURCE_ASSETS' ) && OHMYLMS_SOURCE_ASSETS ) :
		?>
		</div><?php endif; ?>
	<?php do_action( 'ohmylms_signup_form' ); ?>

	<p class="ohmylms-form-row privacy-policy-row">
		<label for="tnc-accept" class="ohmylms-checkbox ohmylms-form-login-rememberme" tabindex="0" data-wp-on--keydown="actions.checkboxKey">
			<input class="ohmylms-form__input ohmylms-form__input-checkbox" name="tnc-accept" type="checkbox" id="tnc-accept" required data-wp-on--change="actions.consentChange" />

			<span class="ohmylms-checkbox-text">
				<span class="checkedbox" aria-hidden="true" id="tnc-accept-label">
					<svg width="11" height="8" fill="none" viewBox="0 0 11 8" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" fill-rule="evenodd" stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width=".5" d="M9.845 1.157c.207.21.207.55 0 .76L5.3 6.528c-.62.63-1.626.63-2.247 0L1.155 4.602a.543.543 0 010-.76.524.524 0 01.749 0L3.802 5.77c.207.21.542.21.749 0l4.545-4.612a.524.524 0 01.749 0z" clip-rule="evenodd"/></svg>
				</span>
				<?php
					printf(
						wp_kses_post(
							/* translators: %1$s: text value. */
							__( 'By signing up, you agree to our <a href="%1$s" target="_blank">Privacy Policy</a>.', 'ohmylms' )
						),
						esc_url( get_permalink( get_option( 'wp_page_for_privacy_policy' ) ) )
					);
					?>
			</span>
		</label>
	</p>

	<p class="ohmylms-form-row form-submit-btn">
		<?php wp_nonce_field( 'ohmylms-signup', 'ohmylms-signup-nonce' ); ?>
		<input type="hidden" name="action" value="ohmylms_signup">
		<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect_to ); ?>" />

		<?php
		if ( defined( 'OHMYLMS_SOURCE_ASSETS' ) && OHMYLMS_SOURCE_ASSETS ) :
			?>
			<span data-ohmylms-registration-submit style="display:contents"><?php endif; ?>
		<button type="submit" class="ohmylms-button ohmylms-form-signup-submit" name="signup" value="<?php esc_attr_e( 'Sign Up', 'ohmylms' ); ?>" disabled data-wp-bind--disabled="state.consentMissing">
			<?php esc_html_e( 'Sign Up', 'ohmylms' ); ?>
		</button>
		<?php
		if ( defined( 'OHMYLMS_SOURCE_ASSETS' ) && OHMYLMS_SOURCE_ASSETS ) :
			?>
			</span><?php endif; ?>
	</p>

	<p class="ohmylms-form-row dont-have-account">
		<?php echo esc_html__( 'Already have an account?', 'ohmylms' ); ?>
		<a href="#" class="ohmylms-show-login-form">
			<?php esc_html_e( 'Log In', 'ohmylms' ); ?>
		</a>
	</p>

	<?php do_action( 'ohmylms_signup_form_end' ); ?>

</form>

