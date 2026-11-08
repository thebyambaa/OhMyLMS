<?php
/**
 * Managed-child recovery and progressively enhanced learning choices.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Family\Service as Family;
use OhMyLMS\Student\FamilyActions;

defined( 'ABSPATH' ) || exit;

if ( ! Family::is_managed( $family_kid['learner_user_id'] ) ) {
	return;
}
$family_choice = Family::choices( $family_kid['learner_user_id'] );
$family_prefix = 'family-choice-' . absint( $family_kid['learner_user_id'] );
?>
<details class="oml-hub-family-choices">
	<summary><?php esc_html_e( 'Password setup and recovery', 'ohmylms' ); ?></summary>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php FamilyActions::fields( 'issue_setup' ); ?>
		<input type="hidden" name="child_id" value="<?php echo esc_attr( $family_kid['learner_user_id'] ); ?>">
		<p><?php esc_html_e( 'Create a private link for your child to set or reset their password. You will not see or choose the password.', 'ohmylms' ); ?></p>
		<p><?php esc_html_e( 'The link expires in 48 hours and replaces older unused setup/reset links. Their current password keeps working until they use the new link. No email is sent.', 'ohmylms' ); ?></p>
		<label class="oml-hub-family-confirm"><input type="checkbox" name="confirm" value="yes" required> <?php esc_html_e( 'Replace older setup/reset links for this child', 'ohmylms' ); ?></label>
		<p class="oml-hub-actions"><button type="submit" class="oml-hub-pill"><?php esc_html_e( 'Create private setup/reset link', 'ohmylms' ); ?></button></p>
	</form>
</details>
<details class="oml-hub-family-choices">
	<summary><?php esc_html_e( 'Learning choices', 'ohmylms' ); ?></summary>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php FamilyActions::fields( 'update_choices' ); ?>
		<input type="hidden" name="child_id" value="<?php echo esc_attr( $family_kid['learner_user_id'] ); ?>">
		<p><?php esc_html_e( 'Use a grade that fits your child. You can leave it blank or change it later.', 'ohmylms' ); ?></p>
		<div class="oml-hub-field"><label for="<?php echo esc_attr( $family_prefix ); ?>-grade"><?php esc_html_e( 'Grade (optional)', 'ohmylms' ); ?></label><input id="<?php echo esc_attr( $family_prefix ); ?>-grade" name="grade" type="text" maxlength="50" value="<?php echo esc_attr( $family_choice['grade'] ); ?>"></div>
		<div class="oml-hub-actions">
			<button type="submit" class="oml-hub-button"><?php esc_html_e( 'Save choices', 'ohmylms' ); ?></button>
		</div>
	</form>
</details>
