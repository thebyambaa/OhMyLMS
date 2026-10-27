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
<details class="oml-hub-family-choices" data-family-editor>
	<summary><?php esc_html_e( 'Learning choices', 'ohmylms' ); ?></summary>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-family-choices data-step-one="<?php esc_attr_e( 'Step 1 of 2: Grade', 'ohmylms' ); ?>" data-step-two="<?php esc_attr_e( 'Step 2 of 2: Collections', 'ohmylms' ); ?>">
		<?php FamilyActions::fields( 'update_choices' ); ?>
		<input type="hidden" name="child_id" value="<?php echo esc_attr( $family_kid['learner_user_id'] ); ?>">
		<p class="oml-hub-family-meta" data-family-step-status role="status" hidden></p>
		<section data-family-step="0">
			<h3 id="<?php echo esc_attr( $family_prefix ); ?>-grade-title" tabindex="-1"><?php esc_html_e( '1. Choose a grade', 'ohmylms' ); ?></h3>
			<p><?php esc_html_e( 'Use a grade that fits your child. You can leave it blank or change it later.', 'ohmylms' ); ?></p>
			<div class="oml-hub-field"><label for="<?php echo esc_attr( $family_prefix ); ?>-grade"><?php esc_html_e( 'Grade (optional)', 'ohmylms' ); ?></label><input id="<?php echo esc_attr( $family_prefix ); ?>-grade" name="grade" type="text" maxlength="50" value="<?php echo esc_attr( $family_choice['grade'] ); ?>"></div>
			<button type="button" class="oml-hub-pill" data-family-next hidden><?php esc_html_e( 'Next: choose collections', 'ohmylms' ); ?></button>
		</section>
		<section data-family-step="1">
			<h3 id="<?php echo esc_attr( $family_prefix ); ?>-collections-title" tabindex="-1"><?php esc_html_e( '2. Choose collections', 'ohmylms' ); ?></h3>
			<p><?php esc_html_e( 'These collections appear on your child’s My learning page. Following a collection does not enrol them or unlock its courses.', 'ohmylms' ); ?></p>
			<?php if ( $family_catalog ) : ?>
				<fieldset class="oml-hub-family-fieldset">
					<legend><?php esc_html_e( 'Collections to follow', 'ohmylms' ); ?></legend>
					<?php foreach ( $family_catalog as $family_bundle ) : ?>
						<label class="oml-hub-family-collection">
							<input type="checkbox" name="bundles[]" value="<?php echo esc_attr( $family_bundle['id'] ); ?>" <?php checked( in_array( (int) $family_bundle['id'], $family_choice['followed'], true ) ); ?>>
							<span><strong><?php echo esc_html( $family_bundle['title'] ); ?></strong><small><?php echo esc_html( sprintf( /* translators: %d: public course count. */ _n( '%d course', '%d courses', $family_bundle['total'], 'ohmylms' ), $family_bundle['total'] ) ); ?> · <?php echo esc_html( implode( ', ', array_column( array_slice( $family_bundle['courses'], 0, 3 ), 'title' ) ) ); ?></small></span>
						</label>
					<?php endforeach; ?>
				</fieldset>
			<?php else : ?>
				<p class="oml-hub-empty"><?php esc_html_e( 'No collections are available yet. You can save the grade and choose collections later.', 'ohmylms' ); ?></p>
			<?php endif; ?>
			<div class="oml-hub-actions">
				<button type="button" class="oml-hub-pill" data-family-back hidden><?php esc_html_e( 'Back', 'ohmylms' ); ?></button>
				<button type="submit" class="oml-hub-button"><?php esc_html_e( 'Save choices', 'ohmylms' ); ?></button>
			</div>
		</section>
		<button type="button" class="oml-hub-pill oml-hub-family-cancel" data-family-cancel hidden><?php esc_html_e( 'Cancel changes', 'ohmylms' ); ?></button>
	</form>
</details>
