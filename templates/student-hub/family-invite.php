<?php
/**
 * Learner decision page for a guardian invitation; the token only selects a candidate.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Family\Service as Family;
use OhMyLMS\Student\FamilyActions;

defined( 'ABSPATH' ) || exit;

$family_preview = Family::preview( $family_token );
?>
<div class="ohmylms-student-hub oml-hub-signin">
	<?php if ( ! $family_preview ) : ?>
		<h2><?php esc_html_e( 'This invitation is not available', 'ohmylms' ); ?></h2>
		<p><?php esc_html_e( 'It may have expired, been used, or been sent to a different account. Sign in with the email address it was sent to.', 'ohmylms' ); ?></p>
	<?php else : ?>
		<h2><?php esc_html_e( 'Connect with a parent?', 'ohmylms' ); ?></h2>
		<p>
			<?php
			/* translators: %s: parent's name. */
			echo esc_html( sprintf( __( '%s asked to connect to your account for home learning. They will see that you are linked. A school never sees this link.', 'ohmylms' ), $family_preview['guardian'] ) );
			?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( FamilyActions::INVITE ); ?>">
			<input type="hidden" name="token" value="<?php echo esc_attr( $family_token ); ?>">
			<?php wp_nonce_field( FamilyActions::INVITE ); ?>
			<p class="oml-hub-actions">
				<button type="submit" name="decision" value="accept" class="oml-hub-button"><?php esc_html_e( 'Accept', 'ohmylms' ); ?></button>
				<button type="submit" name="decision" value="decline" class="oml-hub-pill"><?php esc_html_e( 'Decline', 'ohmylms' ); ?></button>
			</p>
		</form>
	<?php endif; ?>
</div>
