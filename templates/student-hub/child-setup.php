<?php
/**
 * Child credential setup from a private link; the token is only a form value.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Student\FamilyActions;

defined( 'ABSPATH' ) || exit;
?>
<div class="ohmylms-student-hub oml-hub-signin">
	<h2><?php esc_html_e( 'Choose a password', 'ohmylms' ); ?></h2>
	<p><?php esc_html_e( 'Your parent created this account for you. Choose a password to finish.', 'ohmylms' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" autocomplete="off">
		<input type="hidden" name="action" value="<?php echo esc_attr( FamilyActions::SETUP_ACTION ); ?>">
		<input type="hidden" name="token" value="<?php echo esc_attr( $setup_token ); ?>">
		<?php wp_nonce_field( FamilyActions::SETUP_ACTION ); ?>
		<div class="oml-hub-field"><label for="child-pass"><?php esc_html_e( 'Password (12–128 characters)', 'ohmylms' ); ?></label><input id="child-pass" name="password" type="password" required minlength="12" maxlength="128" autocomplete="new-password"></div>
		<div class="oml-hub-field"><label for="child-pass2"><?php esc_html_e( 'Repeat password', 'ohmylms' ); ?></label><input id="child-pass2" name="password2" type="password" required minlength="12" maxlength="128" autocomplete="new-password"></div>
		<p class="oml-hub-actions"><button type="submit" class="oml-hub-button"><?php esc_html_e( 'Save password', 'ohmylms' ); ?></button></p>
	</form>
</div>
