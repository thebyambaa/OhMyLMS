<?php
/**
 * Family home and read-only child overview for approved school links.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Family\Schema as FamilySchema;
use OhMyLMS\Family\Service as Family;
use OhMyLMS\Skills\FamilyComparison;
use OhMyLMS\Student\FamilyActions;
use OhMyLMS\Student\LearnerCatalog;
use OhMyLMS\Student\ParentWorkspace;

defined( 'ABSPATH' ) || exit;

if ( 'home_learning' === get_query_var( 'hub_scope', '' ) ) {
	ohmylms_get_template( 'student-hub/family-report.php' );
	return;
}

$family_children = ParentWorkspace::children();
$family_home     = FamilySchema::enabled() ? Family::children() : array();
$family_flash    = FamilyActions::flash();
$family_catalog  = $family_home ? LearnerCatalog::bundles() : array();
$family_pending  = FamilySchema::enabled() ? Family::invitations() : array();
$family_child_id = absint( get_query_var( 'hub_child', 0 ) );
$family_selected = $family_child_id ? ParentWorkspace::child( $family_child_id, absint( get_query_var( 'hub_school', 0 ) ) ) : null;
?>
<?php if ( $family_child_id && ! $family_selected ) : ?>
	<div class="oml-hub-empty">
		<h1><?php esc_html_e( 'This learner is not available', 'ohmylms' ); ?></h1>
		<p><?php esc_html_e( 'You can only see learners that a school currently links to your account.', 'ohmylms' ); ?></p>
		<p><a class="oml-hub-pill" href="<?php echo esc_url( ParentWorkspace::url() ); ?>">&larr; <?php esc_html_e( 'My children', 'ohmylms' ); ?></a></p>
	</div>
<?php elseif ( $family_selected ) : ?>
	<?php
	$family_page = max( 1, absint( get_query_var( 'current_page', 1 ) ) );
	$family_work = ParentWorkspace::work( $family_selected, $family_page );
	$family_done = count( array_filter( array_column( $family_work, 'completed_at' ) ) );
	$family_base = ParentWorkspace::child_url( $family_selected['student_user_id'], $family_selected['school_id'] );
	?>
	<section class="oml-hub-card oml-hub-settings-card oml-hub-class-panel">
		<a class="oml-hub-pill oml-hub-back" href="<?php echo esc_url( ParentWorkspace::url() ); ?>">&larr; <?php esc_html_e( 'My children', 'ohmylms' ); ?></a>
		<h1><?php echo esc_html( $family_selected['display_name'] ); ?></h1>
		<p><?php echo esc_html( $family_selected['school_name'] ); ?> · <?php esc_html_e( 'School-assigned work only. Independent learning is not included.', 'ohmylms' ); ?></p>
		<?php if ( ! $family_work ) : ?>
			<div class="oml-hub-empty"><h3><?php esc_html_e( 'No assigned work yet', 'ohmylms' ); ?></h3><p><?php esc_html_e( 'Work your child’s teacher assigns will appear here.', 'ohmylms' ); ?></p></div>
		<?php else : ?>
			<p>
				<?php
				/* translators: 1: completed count, 2: total count on this page. */
				echo esc_html( sprintf( __( '%1$d of %2$d assignments on this page completed.', 'ohmylms' ), $family_done, count( $family_work ) ) );
				?>
			</p>
			<ul class="oml-hub-commerce-list">
				<?php foreach ( $family_work as $family_item ) : ?>
					<li>
						<div><strong><?php echo esc_html( $family_item['title'] ); ?></strong><p><?php echo esc_html( $family_item['class_name'] ); ?><?php echo $family_item['due_at'] ? ' · ' . esc_html( sprintf( /* translators: %s: due date. */ __( 'Due %s', 'ohmylms' ), mysql2date( get_option( 'date_format' ), $family_item['due_at'] ) ) ) : ''; ?></p></div>
						<span><?php echo esc_html( $family_item['completed_at'] ? __( 'Completed', 'ohmylms' ) : __( 'Not completed', 'ohmylms' ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<nav class="oml-hub-subnav" aria-label="<?php esc_attr_e( 'Assigned work pages', 'ohmylms' ); ?>">
			<?php if ( $family_page > 1 ) : ?>
				<a class="oml-hub-pill" href="<?php echo esc_url( add_query_arg( 'current_page', $family_page - 1, $family_base ) ); ?>"><?php esc_html_e( 'Previous', 'ohmylms' ); ?></a>
			<?php endif; ?>
			<?php if ( count( $family_work ) >= 50 ) : ?>
				<a class="oml-hub-pill" href="<?php echo esc_url( add_query_arg( 'current_page', $family_page + 1, $family_base ) ); ?>"><?php esc_html_e( 'Next', 'ohmylms' ); ?></a>
			<?php endif; ?>
		</nav>
	</section>
	<?php if ( \OhMyLMS\Assessment\Schema::ready() && \OhMyLMS\Practice\Frontend::enabled() ) : ?>
		<section class="oml-hub-card oml-hub-settings-card oml-hub-class-panel">
			<h2><?php esc_html_e( 'Skills', 'ohmylms' ); ?></h2>
			<p class="oml-hub-family-meta"><?php esc_html_e( 'Skill levels are separate from grades. Your child is compared with an anonymous class average when the class is large enough.', 'ohmylms' ); ?></p>
			<?php ohmylms_get_template( 'student-hub/skill-compare.php', array( 'compare' => FamilyComparison::build( array( $family_selected ), (int) $family_selected['school_id'] ) ) ); ?>
		</section>
	<?php endif; ?>
<?php else : ?>
	<div class="oml-hub-family">
	<section class="oml-hub-banner">
		<h1><?php esc_html_e( 'My children', 'ohmylms' ); ?></h1>
		<p><?php esc_html_e( 'Follow children a school has linked to you and manage children learning at home.', 'ohmylms' ); ?></p>
	</section>
	<?php if ( $family_flash && ! empty( $family_flash['error'] ) ) : ?>
		<p class="oml-hub-notice" role="alert"><?php echo esc_html( $family_flash['error'] ); ?></p>
	<?php elseif ( $family_flash ) : ?>
		<div class="oml-hub-notice oml-hub-family-flash" role="status">
			<p class="oml-hub-family-flash-text"><strong><?php echo esc_html( $family_flash['message'] ); ?></strong></p>
			<?php if ( ! empty( $family_flash['invite_url'] ) ) : ?>
				<label class="oml-hub-family-label" for="family-invite-url"><?php esc_html_e( 'Invitation link (shown once, valid for 48 hours)', 'ohmylms' ); ?></label>
				<input id="family-invite-url" type="text" readonly value="<?php echo esc_attr( $family_flash['invite_url'] ); ?>">
			<?php endif; ?>
			<?php if ( ! empty( $family_flash['setup_url'] ) ) : ?>
				<label class="oml-hub-family-label" for="family-setup-url"><?php esc_html_e( 'Setup link (shown once, valid for 48 hours)', 'ohmylms' ); ?></label>
				<input id="family-setup-url" type="text" readonly value="<?php echo esc_attr( $family_flash['setup_url'] ); ?>">
			<?php endif; ?>
		</div>
	<?php endif; ?>
	<?php if ( ! $family_children && ! $family_home ) : ?>
		<div class="oml-hub-empty">
			<h2><?php esc_html_e( 'No children linked yet', 'ohmylms' ); ?></h2>
			<p><?php esc_html_e( 'A school can link your account to your child, or you can add or invite a child below. If you received an invitation, open its link while signed in. Your account stays private until a link is approved.', 'ohmylms' ); ?></p>
		</div>
	<?php endif; ?>
	<?php if ( $family_children ) : ?>
		<h2><?php esc_html_e( 'School-linked', 'ohmylms' ); ?></h2>
		<ul class="oml-hub-commerce-list">
			<?php foreach ( $family_children as $family_child ) : ?>
				<li><div><strong><?php echo esc_html( $family_child['display_name'] ); ?></strong><p><?php echo esc_html( $family_child['school_name'] ); ?></p></div><a class="oml-hub-pill" href="<?php echo esc_url( ParentWorkspace::child_url( $family_child['student_user_id'], $family_child['school_id'] ) ); ?>"><?php esc_html_e( 'View', 'ohmylms' ); ?></a></li>
			<?php endforeach; ?>
		</ul>
		<?php if ( count( $family_children ) >= ParentWorkspace::LIMIT ) : ?>
			<p><?php esc_html_e( 'Showing the first 100 linked learners.', 'ohmylms' ); ?></p>
		<?php endif; ?>
	<?php endif; ?>
	<?php if ( count( array_unique( array_map( 'intval', array_column( $family_children, 'student_user_id' ) ) ) ) > 1 && \OhMyLMS\Assessment\Schema::ready() && \OhMyLMS\Practice\Frontend::enabled() ) : ?>
		<section class="oml-hub-card oml-hub-settings-card">
			<h2><?php esc_html_e( 'Compare your children', 'ohmylms' ); ?></h2>
			<p class="oml-hub-family-meta"><?php esc_html_e( 'Skill levels side by side. They are separate from grades.', 'ohmylms' ); ?></p>
			<?php ohmylms_get_template( 'student-hub/skill-compare.php', array( 'compare' => FamilyComparison::build( $family_children ) ) ); ?>
		</section>
	<?php endif; ?>
	<?php if ( FamilySchema::enabled() && Family::guardian_eligible( get_current_user_id() ) ) : ?>
		<section class="oml-hub-card oml-hub-settings-card">
			<h2><?php esc_html_e( 'Home learning', 'ohmylms' ); ?></h2>
			<p><?php esc_html_e( 'Children you manage yourself. A school never sees these links, and this page never shows school work.', 'ohmylms' ); ?></p>
			<?php if ( $family_home ) : ?>
				<ul class="oml-hub-commerce-list">
					<?php foreach ( $family_home as $family_kid ) : ?>
						<li>
							<div><strong><?php echo esc_html( $family_kid['display_name'] ); ?></strong><p class="oml-hub-family-meta"><span class="oml-hub-chip"><?php esc_html_e( 'Home learning', 'ohmylms' ); ?></span></p></div>
							<div class="oml-hub-family-actions">
								<?php
								$family_report_url = add_query_arg(
									array(
										'hub_scope' => 'home_learning',
										'hub_child' => $family_kid['learner_user_id'],
									),
									ParentWorkspace::url()
								);
								?>
								<a class="oml-hub-pill" href="<?php echo esc_url( $family_report_url ); ?>"><?php esc_html_e( 'View home-learning report', 'ohmylms' ); ?></a>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php FamilyActions::fields( 'revoke_link' ); ?><input type="hidden" name="link_id" value="<?php echo esc_attr( $family_kid['link_id'] ); ?>"><label class="oml-hub-family-confirm"><input type="checkbox" name="confirm" value="yes" required> <?php esc_html_e( 'Confirm removal', 'ohmylms' ); ?></label> <button type="submit" class="oml-hub-pill oml-hub-pill-danger"><?php esc_html_e( 'Remove link', 'ohmylms' ); ?></button></form>
							</div>
							<?php if ( Family::is_managed( $family_kid['learner_user_id'] ) ) : ?>
								<?php
								ohmylms_get_template(
									'student-hub/family-managed.php',
									array(
										'family_kid'     => $family_kid,
										'family_catalog' => $family_catalog,
									)
								);
								?>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( $family_pending ) : ?>
				<h3><?php esc_html_e( 'Pending invitations', 'ohmylms' ); ?></h3>
				<ul class="oml-hub-commerce-list">
					<?php foreach ( $family_pending as $family_invite_row ) : ?>
						<li>
							<div><strong><?php echo esc_html( $family_invite_row['email'] ); ?></strong><p class="oml-hub-family-meta"><?php echo esc_html( sprintf( /* translators: %s: expiry date and time. */ __( 'Expires %s', 'ohmylms' ), get_date_from_gmt( $family_invite_row['expires_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) ); ?></p></div>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php FamilyActions::fields( 'revoke_invitation' ); ?><input type="hidden" name="invitation_id" value="<?php echo esc_attr( $family_invite_row['id'] ); ?>"><button type="submit" class="oml-hub-pill"><?php esc_html_e( 'Revoke', 'ohmylms' ); ?></button></form>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<details>
				<summary><?php esc_html_e( 'Invite a child who already has an account', 'ohmylms' ); ?></summary>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" autocomplete="off">
					<?php FamilyActions::fields( 'invite_existing' ); ?>
					<div class="oml-hub-field"><label for="family-email"><?php esc_html_e( 'Child’s email address', 'ohmylms' ); ?></label><input id="family-email" name="email" type="email" required maxlength="190"></div>
					<p><?php esc_html_e( 'Only the account with this email can accept. No email is sent; share the link yourself.', 'ohmylms' ); ?></p>
					<p class="oml-hub-actions"><button type="submit" class="oml-hub-button"><?php esc_html_e( 'Create invitation link', 'ohmylms' ); ?></button></p>
				</form>
			</details>
			<details>
				<summary><?php esc_html_e( 'Add a child', 'ohmylms' ); ?></summary>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" autocomplete="off">
					<?php FamilyActions::fields( 'create_child' ); ?>
					<div class="oml-hub-field"><label for="family-name"><?php esc_html_e( 'Child’s name', 'ohmylms' ); ?></label><input id="family-name" name="name" type="text" required maxlength="80"></div>
					<div class="oml-hub-field"><label for="family-grade"><?php esc_html_e( 'Grade (optional)', 'ohmylms' ); ?></label><input id="family-grade" name="grade" type="text" maxlength="50"></div>
					<p class="oml-hub-actions"><button type="submit" class="oml-hub-button"><?php esc_html_e( 'Add child', 'ohmylms' ); ?></button></p>
				</form>
			</details>
		</section>
	<?php endif; ?>
</div>
<?php endif; ?>
