<?php
/**
 * Login form
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/global/form-billing.php.
 *
 * @package OhMyLMS\Templates
 * * @version  1.0.0
 * * @global \OhMyLMS\Commerce\Checkout $checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( is_user_logged_in() ) {
	return;
}

$ohmylms_google_configured    = \OhMyLMS\Services\GoogleAuthService::is_configured();
$ohmylms_google_login_url     = rest_url( 'ohmylms/v1/auth/google' );
$ohmylms_microsoft_configured = \OhMyLMS\Services\MicrosoftAuthService::is_configured();
$ohmylms_microsoft_login_url  = rest_url( 'ohmylms/v1/auth/microsoft' );
if ( ! empty( $redirect_to ) ) {
	$ohmylms_google_login_url    = add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), $ohmylms_google_login_url );
	$ohmylms_microsoft_login_url = add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), $ohmylms_microsoft_login_url );
}

?>
<form class="ohmylms-login-signup ohmylms-form-login login" method="post" <?php echo ( $hidden ) ? 'style="display:none;"' : ''; ?> >

	<?php echo ( $message ) ? wpautop( wptexturize( $message ) ) : ''; // @codingStandardsIgnoreLine ?>

	<?php if ( isset( $_GET['google_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<p class="ohmylms-form-row ohmylms-notice ohmylms-notice-error">
			<?php
			if ( 'google_link_required' === sanitize_key( wp_unslash( $_GET['google_error'] ) ) ) {
				esc_html_e( 'Sign in with your existing account first, then choose Connect Google in the OhMyLMS school and family portal.', 'ohmylms' );
			} else {
				esc_html_e( "We couldn't sign you in with Google. Please try again.", 'ohmylms' );
			}
			?>
		</p>
	<?php endif; ?>

	<?php if ( isset( $_GET['microsoft_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<p class="ohmylms-form-row ohmylms-notice ohmylms-notice-error">
			<?php
			$ohmylms_microsoft_error = sanitize_key( wp_unslash( $_GET['microsoft_error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 'microsoft_link_required' === $ohmylms_microsoft_error ) {
				esc_html_e( 'Sign in with your existing account first, then connect Microsoft from the OhMyLMS portal.', 'ohmylms' );
			} elseif ( 'microsoft_email_unverified' === $ohmylms_microsoft_error ) {
				esc_html_e( 'Please verify your email address before signing in. We sent you a verification link.', 'ohmylms' );
			} else {
				esc_html_e( "We couldn't sign you in with Microsoft. Please try again.", 'ohmylms' );
			}
			?>
		</p>
	<?php endif; ?>

	<?php if ( $ohmylms_google_configured ) : ?>
		<p class="ohmylms-form-row">
			<a href="<?php echo esc_url( $ohmylms_google_login_url ); ?>" class="ohmylms-button ohmylms-google-signin-button" style="display:flex;align-items:center;justify-content:center;gap:8px;width:100%;text-decoration:none;">
				<svg width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg">
					<path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84c-.21 1.13-.85 2.09-1.81 2.73v2.27h2.92c1.71-1.57 2.69-3.89 2.69-6.64z"/>
					<path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.17l-2.92-2.27c-.81.54-1.84.86-3.04.86-2.34 0-4.32-1.58-5.03-3.71H.96v2.33C2.44 15.98 5.48 18 9 18z"/>
					<path fill="#FBBC05" d="M3.97 10.71c-.18-.54-.28-1.11-.28-1.71s.1-1.17.28-1.71V4.96H.96C.35 6.17 0 7.55 0 9s.35 2.83.96 4.04l3.01-2.33z"/>
					<path fill="#EA4335" d="M9 3.58c1.32 0 2.5.45 3.44 1.35l2.58-2.58C13.46.89 11.43 0 9 0 5.48 0 2.44 2.02.96 4.96l3.01 2.33C4.68 5.16 6.66 3.58 9 3.58z"/>
				</svg>
				<?php esc_html_e( 'Continue with Google', 'ohmylms' ); ?>
			</a>
		</p>
	<?php endif; ?>

	<?php if ( $ohmylms_microsoft_configured ) : ?>
		<p class="ohmylms-form-row">
			<a href="<?php echo esc_url( $ohmylms_microsoft_login_url ); ?>" class="ohmylms-button ohmylms-microsoft-signin-button" style="display:flex;align-items:center;justify-content:center;gap:8px;width:100%;text-decoration:none;">
				<svg width="18" height="18" viewBox="0 0 23 23" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
					<path fill="#F25022" d="M1 1h10v10H1z"/>
					<path fill="#7FBA00" d="M12 1h10v10H12z"/>
					<path fill="#00A4EF" d="M1 12h10v10H1z"/>
					<path fill="#FFB900" d="M12 12h10v10H12z"/>
				</svg>
				<?php esc_html_e( 'Continue with Microsoft', 'ohmylms' ); ?>
			</a>
		</p>
	<?php endif; ?>

	<?php if ( $ohmylms_google_configured || $ohmylms_microsoft_configured ) : ?>
		<p class="ohmylms-form-row ohmylms-auth-divider" style="text-align:center;color:#7A8B9A;font-size:13px;">
			<?php esc_html_e( 'or', 'ohmylms' ); ?>
		</p>
	<?php endif; ?>

	<?php do_action( 'ohmylms_login_form_start' ); ?>

	<p class="ohmylms-form-row ohmylms-form-row-first row-username">
		<span class="ohmylms-input-wrapper">
			<label for="username" class="ohmylms-input-label">
				<?php esc_html_e( 'Username or email', 'ohmylms' ); ?>&nbsp;
				<span class="required" aria-hidden="true">*</span>

				<span class="screen-reader-text">
					<?php esc_html_e( 'Required', 'ohmylms' ); ?>
				</span>
			</label>

			<input type="text" class="ohmylms-input-text username" name="username" id="username" autocomplete="username" required aria-required="true" />
		</span>
	</p>

	<p class="ohmylms-form-row ohmylms-form-row-last row-password">
		<span class="ohmylms-input-wrapper">
			<label for="login-password" class="ohmylms-input-label">
				<?php esc_html_e( 'Password', 'ohmylms' ); ?>&nbsp;
				<span class="required" aria-hidden="true">*</span>

				<span class="screen-reader-text">
					<?php esc_html_e( 'Required', 'ohmylms' ); ?>
				</span>
			</label>

			<span class="ohmylms-password-show">
				<input class="ohmylms-input-text password" type="password" name="password" id="login-password" autocomplete="current-password" required aria-required="true" >

				<label class="show-password-icon" tabindex="0">
					<input type="checkbox" name="show-password-checkbox" class="show-password-checkbox" aria-required="true" aria-hidden="true" >

					<span class="show-password" aria-label="Toggle password visibility">
						<span class="eye-on">
							<?php
								require OHMYLMS_DIR . '/assets/images/icon/eye-icon2.php';
							?>
						</span>

						<span class="eye-off">
							<?php
								require OHMYLMS_DIR . '/assets/images/icon/eye-off-icon.php';
							?>
						</span>
					</span>
				</label>
			</span>
		</span>
	</p>

	<?php do_action( 'ohmylms_login_form' ); ?>

	<p class="ohmylms-form-row rememberme-row">
		<label for="rememberme" class="ohmylms-checkbox ohmylms-form-login-rememberme" tabindex="0">
			<input class="ohmylms-form__input ohmylms-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" aria-required="true" aria-labelledby="rememberme-label" />

			<span class="ohmylms-checkbox-text">
				<span class="checkedbox" aria-hidden="true" id="rememberme-label">
					<svg width="11" height="8" fill="none" viewBox="0 0 11 8" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" fill-rule="evenodd" stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width=".5" d="M9.845 1.157c.207.21.207.55 0 .76L5.3 6.528c-.62.63-1.626.63-2.247 0L1.155 4.602a.543.543 0 010-.76.524.524 0 01.749 0L3.802 5.77c.207.21.542.21.749 0l4.545-4.612a.524.524 0 01.749 0z" clip-rule="evenodd"/></svg>
				</span>
				<?php esc_html_e( 'Remember me', 'ohmylms' ); ?>
			</span>
		</label>

		<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" class="forgot-pass">
			<?php esc_html_e( 'Forgot password?', 'ohmylms' ); ?>
		</a>
	</p>

	<p class="ohmylms-form-row form-submit-btn">
		<?php wp_nonce_field( 'ohmylms-login', 'ohmylms-login-nonce' ); ?>
		<input type="hidden" name="action" value="ohmylms_login">
		<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect_to ); ?>" />

		<button type="submit" class="ohmylms-button ohmylms-form-login-submit" name="login" value="<?php esc_attr_e( 'Login', 'ohmylms' ); ?>">
			<?php esc_html_e( 'Log in', 'ohmylms' ); ?>
			<span class="ohmylms-loader"></span>
		</button>
	</p>

	<?php if ( ! is_ohmylms_checkout() ) : ?>
		<p class="ohmylms-form-row dont-have-account">
			<?php echo esc_html__( 'Don\'t have an account?', 'ohmylms' ); ?>
			<a href="#" class="ohmylms-show-signup-form">
				<?php esc_html_e( 'Sign Up', 'ohmylms' ); ?>
			</a>
		</p>
	<?php endif; ?>

	<?php do_action( 'ohmylms_login_form_end' ); ?>

</form>
