<?php
/**
 * Learner view of home-learning parent links, with removal.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Family\Service as Family;
use OhMyLMS\Student\FamilyActions;

defined( 'ABSPATH' ) || exit;

$family_guardians = Family::guardians();
$family_flash     = FamilyActions::flash();
if ( ! $family_guardians && ! $family_flash ) {
	return;
}
?>
<section class="oml-hub-card">
	<h2><?php esc_html_e( 'Parents (home learning)', 'ohmylms' ); ?></h2>
	<?php if ( $family_flash ) : ?>
		<p role="status"><?php echo esc_html( ! empty( $family_flash['error'] ) ? $family_flash['error'] : $family_flash['message'] ); ?></p>
	<?php endif; ?>
	<?php foreach ( $family_guardians as $family_guardian ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php FamilyActions::fields( 'revoke_link' ); ?>
			<input type="hidden" name="link_id" value="<?php echo esc_attr( $family_guardian['link_id'] ); ?>">
			<input type="hidden" name="return" value="learner">
			<strong><?php echo esc_html( $family_guardian['display_name'] ); ?></strong>
			<label><input type="checkbox" name="confirm" value="yes" required> <?php esc_html_e( 'Confirm removal', 'ohmylms' ); ?></label>
			<button type="submit" class="oml-hub-pill"><?php esc_html_e( 'Remove connection', 'ohmylms' ); ?></button>
		</form>
	<?php endforeach; ?>
</section>
