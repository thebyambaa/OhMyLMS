<?php
/**
 * Password setup for a school activation invitation; the token is only a form value.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Student\SchoolActivation;

defined( 'ABSPATH' ) || exit;
?>
<div class="ohmylms-student-hub oml-hub-signin">
	<h2><?php esc_html_e( 'Choose a password', 'ohmylms' ); ?></h2>
	<p><?php esc_html_e( 'Your school created this account for you. Choose a password to finish.', 'ohmylms' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" autocomplete="off">
		<input type="hidden" name="action" value="<?php echo esc_attr( SchoolActivation::ACTION ); ?>">
		<input type="hidden" name="token" value="<?php echo esc_attr( $activation_token ); ?>">
		<?php wp_nonce_field( SchoolActivation::ACTION ); ?>
		<div class="oml-hub-field"><label for="school-pass"><?php esc_html_e( 'Password (12–128 characters)', 'ohmylms' ); ?></label><input id="school-pass" name="password" type="password" required minlength="12" maxlength="128" autocomplete="new-password"></div>
		<div class="oml-hub-field"><label for="school-pass2"><?php esc_html_e( 'Repeat password', 'ohmylms' ); ?></label><input id="school-pass2" name="password2" type="password" required minlength="12" maxlength="128" autocomplete="new-password"></div>
		<p class="oml-hub-actions"><button type="submit" class="oml-hub-button"><?php esc_html_e( 'Save password', 'ohmylms' ); ?></button></p>
	</form>
</div>
