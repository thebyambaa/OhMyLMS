<?php
/**
 * Template for displaying profile edit of student profile
 *
 * The cover and profile photo uploads and the repeatable skill and link rows are driven by the
 * `ohmylms/account` store; the form itself posts without JavaScript.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/profile/profile-edit.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.1.0
 * @global \OhMyLMS\Data\Student $student
 */

defined( 'ABSPATH' ) || exit();
do_action( 'ohmylms_before_edit_account_form' );

?>

<?php
$profile_images        = \OhMyLMS\Extensions\Account::profile_context( $student );
$student_profile_photo = $profile_images['avatar'];
?>
<div class="ohmylms-student-profile-tab-content student-profile student-profile-edit"
	data-wp-interactive="ohmylms/account"
	<?php echo wp_interactivity_data_wp_context( $profile_images ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core helper escapes the attribute. ?>
	data-wp-class--is-busy="state.isBusy">
	<h4 class="profile-tab-title">
		<?php echo esc_html__( 'Edit Profile', 'ohmylms' ); ?>
	</h4>

	<div class="ohmylms-profile-cover-area">
		<div class="ohmylms-profile-cover" tabindex="0">
			<figure>
				<img src="<?php echo esc_url( $profile_images['cover'] ); ?>" alt="" id="student-cover-image" data-wp-bind--src="context.cover">
			</figure>

			<div class="hoverlay" tabindex="0">
				<label for="cover_image_input" role="button" aria-label="<?php esc_attr_e( 'Change cover image', 'ohmylms' ); ?>" class="cover-action-btn cover-edit" tabindex="0" data-wp-on--keydown="actions.pickKey">
					<input type="file" id="cover_image_input" style="display: none;" accept="image/*" data-wp-on--change="actions.uploadCover">
					<?php require OHMYLMS_DIR . '/assets/images/icon/edit-icon.php'; ?>
					<?php echo esc_html__( 'Edit', 'ohmylms' ); ?>
				</label>

				<button type="button" aria-label="<?php esc_attr_e( 'Remove cover image', 'ohmylms' ); ?>" class="cover-action-btn cover-delete" id="cover-image-delete" style="<?php echo esc_attr( $profile_images['hasCover'] ? 'display: flex;' : 'display: none;' ); ?>" data-wp-on--click="actions.deleteCover" data-wp-style--display="state.coverDeleteDisplay" data-wp-bind--disabled="state.isBusy">
					<?php require OHMYLMS_DIR . '/assets/images/icon/delete-icon.php'; ?>
					<?php echo esc_html__( 'Delete', 'ohmylms' ); ?>
				</button>
			</div>

			<div class="ohmylms-profile-avatar" tabindex="0">
				<figure>
					<img class="student-profile-photo" id="student-profile-photo" alt="<?php esc_attr_e( 'Profile photo', 'ohmylms' ); ?>"
						<?php echo $student_profile_photo ? 'src="' . esc_url( $student_profile_photo ) . '"' : 'hidden'; ?>
						data-wp-bind--src="context.avatar" data-wp-bind--hidden="state.avatarHidden">
					<span class="ohmylms-profile-initials" aria-hidden="true" data-wp-bind--hidden="state.initialsHidden"<?php echo $student_profile_photo ? ' hidden' : ''; ?>><?php echo esc_html( ohmylms_get_initials( $student->get_first_name(), $student->get_last_name() ) ); ?></span>
				</figure>

				<label for="profile_photo_input" class="edit-avatar" tabindex="0" role="button" aria-label="<?php esc_attr_e( 'Change profile photo', 'ohmylms' ); ?>" data-wp-on--keydown="actions.pickKey">
					<input type="file" id="profile_photo_input" style="display: none;" accept="image/*" data-wp-on--change="actions.uploadAvatar">
					<?php require OHMYLMS_DIR . '/assets/images/icon/camera-icon.php'; ?>
					<small><?php echo esc_html__( 'Recomanded size 400 x 400 px', 'ohmylms' ); ?></small>
				</label>
			</div>
		</div>

		<form action="" method="post">
			<?php do_action( 'ohmylms_edit_account_form_start' ); ?>

			<div class="ohmylms-student-basic-info">
				<h6 class="account-title">
					<?php echo esc_html__( 'Account', 'ohmylms' ); ?>
				</h6>

				<div class="ohmylms-form-wrapper">
					<div class="ohmylms-form-group firstname half-width">
						<label>
							<?php echo esc_html__( 'First Name', 'ohmylms' ); ?>
						</label>

						<span class="ohmylms-input-wrapper">
							<input type="text" name="first_name" placeholder="<?php echo esc_attr( 'Enter first name', 'ohmylms' ); ?>" value="<?php echo esc_attr( $student->get_first_name() ); ?>">
						</span>
					</div>

					<div class="ohmylms-form-group lastname half-width">
						<label>
							<?php echo esc_html__( 'Last Name', 'ohmylms' ); ?>
						</label>

						<span class="ohmylms-input-wrapper">
							<input type="text" name="last_name" placeholder="<?php echo esc_attr( 'Enter last name', 'ohmylms' ); ?>" value="<?php echo esc_attr( $student->get_last_name() ); ?>">
						</span>
					</div>

					<div class="ohmylms-form-group display-name">
						<label>
							<?php echo esc_html__( 'Display Name', 'ohmylms' ); ?>
							<span class="required">*</span>
						</label>

						<span class="ohmylms-input-wrapper">
							<input type="text" name="display_name" placeholder="<?php echo esc_attr__( 'Enter your display name', 'ohmylms' ); ?>" value="<?php echo esc_attr( $student->get_display_name() ); ?>">
						</span>
					</div>

					<div class="ohmylms-form-group email">
						<label>
							<?php echo esc_html__( 'Email', 'ohmylms' ); ?>
							<span class="required">*</span>
						</label>

						<span class="ohmylms-input-wrapper">
							<input type="email" name="email" placeholder="<?php echo esc_attr__( 'Enter your email address', 'ohmylms' ); ?>" value="<?php echo esc_attr( $student->get_email() ); ?>">
						</span>
					</div>

					<div class="ohmylms-form-group address-line1">
						<label>
							<?php echo esc_html__( 'Address', 'ohmylms' ); ?>
						</label>

						<span class="ohmylms-input-wrapper">
							<input type="text" name="address" placeholder="<?php echo esc_attr( 'Street address, P. O. box', 'ohmylms' ); ?>" value="<?php echo esc_attr( $student->get_address() ); ?>">
						</span>
					</div>

					<div class="ohmylms-form-group phone half-width">
						<label>
							<?php echo esc_html__( 'Phone Number', 'ohmylms' ); ?>
						</label>

						<span class="ohmylms-input-wrapper">
							<input type="text" name="phone" placeholder="<?php echo esc_attr__( 'Phone number', 'ohmylms' ); ?>" value="<?php echo esc_attr( $student->get_phone() ); ?>">
						</span>
					</div>

					<?php $learner_details = \OhMyLMS\Student\LearnerProfile::get( $student->get_id() ); ?>
					<div class="ohmylms-form-group province half-width">
						<label>
							<?php echo esc_html__( 'Province', 'ohmylms' ); ?>
						</label>

						<span class="ohmylms-input-wrapper">
							<?php \OhMyLMS\Student\LearnerProfile::select( 'profile-province', 'province', \OhMyLMS\Student\LearnerProfile::provinces(), $learner_details['province'], __( 'Choose your province', 'ohmylms' ) ); ?>
						</span>
					</div>

					<div class="ohmylms-form-group school-name half-width">
						<label>
							<?php echo esc_html__( 'School name', 'ohmylms' ); ?>
						</label>

						<span class="ohmylms-input-wrapper">
							<input type="text" name="school_name" maxlength="<?php echo esc_attr( \OhMyLMS\Student\LearnerProfile::MAX_SCHOOL ); ?>" value="<?php echo esc_attr( $learner_details['school_name'] ); ?>">
						</span>
					</div>

					<div class="ohmylms-form-group grade half-width">
						<label>
							<?php echo esc_html__( 'Grade', 'ohmylms' ); ?>
						</label>

						<span class="ohmylms-input-wrapper">
							<?php \OhMyLMS\Student\LearnerProfile::select( 'profile-grade', 'grade', \OhMyLMS\Student\LearnerProfile::grades(), $learner_details['grade'], __( 'Choose your grade', 'ohmylms' ) ); ?>
						</span>
					</div>

					<div class="ohmylms-form-group whatsapp half-width">
						<label>
							<?php echo esc_html__( 'WhatsApp', 'ohmylms' ); ?>
						</label>

						<span class="ohmylms-input-wrapper">
							<input type="text" name="whatsapp" placeholder="<?php echo esc_attr__( 'WhatsApp number', 'ohmylms' ); ?>" value="<?php echo esc_attr( $student->get_whatsapp() ); ?>">
						</span>
					</div>

					<div class="ohmylms-form-group country">
						<label>
							<?php echo esc_html__( 'Country', 'ohmylms' ); ?>
						</label>

						<span class="ohmylms-input-wrapper">
							<select name="country">
								<option value=""><?php echo esc_html__( '— Select country —', 'ohmylms' ); ?></option>
								<?php
								$student_country = $student->get_country();
								foreach ( ohmylms_get_countries() as $country ) {
									printf(
										'<option value="%s"%s>%s</option>',
										esc_attr( $country['code'] ),
										selected( $student_country, $country['code'], false ),
										esc_html( $country['title'] )
									);
								}
								?>
							</select>
						</span>
					</div>

					<div class="ohmylms-form-group bio" id="bio">
						<label>
							<?php echo esc_html__( 'Headline/Bio', 'ohmylms' ); ?>
						</label>

						<span class="ohmylms-input-wrapper">
							<textarea name="bio" id=""><?php echo esc_textarea( $student->get_bio() ); ?></textarea>
						</span>
					</div>

					<div class="ohmylms-form-group interests">
						<label>
							<?php echo esc_html__( 'Learning Interests', 'ohmylms' ); ?>
						</label>

						<span class="ohmylms-input-wrapper">
							<textarea name="interests" id=""><?php echo esc_textarea( $student->get_interest() ); ?></textarea>
						</span>
					</div>

					<!-- Skills Section -->
					<div class="ohmylms-form-group skills-section" id="skills">
						<label>
							<?php echo esc_html__( 'Skills', 'ohmylms' ); ?>
						</label>
						<div class="ohmylms-dynamic-fields" id="skills-container" data-wp-on--click="actions.removeRow">
							<?php
							$skills = $student->get_skills(); // We'll need to implement this method.
							if ( ! empty( $skills ) && is_array( $skills ) ) {
								foreach ( $skills as $index => $skill ) {
									?>
									<div class="ohmylms-dynamic-field-row">
										<span class="ohmylms-input-wrapper">
											<input type="text" name="skills[]" placeholder="<?php echo esc_attr( 'Enter a skill', 'ohmylms' ); ?>" value="<?php echo esc_attr( $skill ); ?>">
										</span>
										<button type="button" class="ohmylms-remove-field" aria-label="<?php echo esc_attr__( 'Remove skill', 'ohmylms' ); ?>">×</button>
									</div>
									<?php
								}
							} else {
								?>
								<div class="ohmylms-dynamic-field-row">
									<span class="ohmylms-input-wrapper">
										<input type="text" name="skills[]" placeholder="<?php echo esc_attr( 'Enter a skill', 'ohmylms' ); ?>">
									</span>
									<button type="button" class="ohmylms-remove-field" aria-label="<?php echo esc_attr__( 'Remove skill', 'ohmylms' ); ?>">×</button>
								</div>
								<?php
							}
							?>
						</div>
						<button type="button" class="ohmylms-button ohmylms-add-field" data-target="skills-container" data-template="skills-template" data-wp-on--click="actions.addRow">
							<?php echo esc_html__( 'Add Another Skill', 'ohmylms' ); ?>
						</button>
					</div>

					<!-- Links Section -->
					<div class="ohmylms-form-group links-section"  id="social-links">
						<label>
							<?php echo esc_html__( 'Social Links', 'ohmylms' ); ?>
						</label>
						<div class="ohmylms-dynamic-fields" id="links-container" data-wp-on--click="actions.removeRow">
							<?php
							$links = $student->get_social_links(); // We'll need to implement this method.
							if ( ! empty( $links ) && is_array( $links ) ) {
								foreach ( $links as $index => $link ) {
									?>
									<div class="ohmylms-dynamic-field-row ohmylms-link-row">
										<span class="ohmylms-input-wrapper ohmylms-link-label">
											<input type="text" name="link_labels[]" placeholder="<?php echo esc_attr( 'Label (e.g., LinkedIn, Facebook)', 'ohmylms' ); ?>" value="<?php echo esc_attr( $link['label'] ?? '' ); ?>">
										</span>
										<span class="ohmylms-input-wrapper ohmylms-link-url">
											<input type="url" name="link_urls[]" placeholder="<?php echo esc_attr( 'https://example.com', 'ohmylms' ); ?>" value="<?php echo esc_attr( $link['url'] ?? '' ); ?>">
										</span>
										<button type="button" class="ohmylms-remove-field" aria-label="<?php echo esc_attr__( 'Remove link', 'ohmylms' ); ?>">×</button>
									</div>
									<?php
								}
							} else {
								?>
								<div class="ohmylms-dynamic-field-row ohmylms-link-row">
									<span class="ohmylms-input-wrapper ohmylms-link-label">
										<input type="text" name="link_labels[]" placeholder="<?php echo esc_attr( 'Label (e.g., LinkedIn, Facebook)', 'ohmylms' ); ?>">
									</span>
									<span class="ohmylms-input-wrapper ohmylms-link-url">
										<input type="url" name="link_urls[]" placeholder="<?php echo esc_attr( 'https://example.com', 'ohmylms' ); ?>">
									</span>
									<button type="button" class="ohmylms-remove-field" aria-label="<?php echo esc_attr__( 'Remove link', 'ohmylms' ); ?>">×</button>
								</div>
								<?php
							}
							?>
						</div>
						<button type="button" class="ohmylms-button ohmylms-add-field" data-target="links-container" data-template="links-template" data-wp-on--click="actions.addRow">
							<?php echo esc_html__( 'Add Another Link', 'ohmylms' ); ?>
						</button>
					</div>
				</div>
			</div>

			<div class="ohmylms-student-account">
				<h6 class="account-title">
					<?php echo esc_html__( 'Password', 'ohmylms' ); ?>
				</h6>

				<div class="ohmylms-form-wrapper">
					<!-- current password -->
					<div class="ohmylms-form-group password">
						<label for="current-password">
							<?php echo esc_html__( 'Current Password', 'ohmylms' ); ?>
						</label>

						<span class="ohmylms-password-show">
							<input class="ohmylms-input-text password" type="password" name="password_current" id="current-password">

							<label class="show-password-icon" tabindex="0">
								<input type="checkbox" name="show-password-checkbox" class="show-password-checkbox" aria-required="true" aria-hidden="true">
								
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
					</div>

					<!-- new password -->
					<div class="ohmylms-form-group new-password">
						<label for="new-password">
							<?php echo esc_html__( 'New Password', 'ohmylms' ); ?>
						</label>

						<span class="ohmylms-password-show">
							<input class="ohmylms-input-text password" type="password" name="password1" id="new-password">

							<label class="show-password-icon" tabindex="0">
								<input type="checkbox" name="show-password-checkbox" class="show-password-checkbox" aria-required="true" aria-hidden="true">
								
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
					</div>

					<!-- confirm password -->
					<div class="ohmylms-form-group confirm-password">
						<label for="confirm-password">
							<?php echo esc_html__( 'Confirm Password', 'ohmylms' ); ?>
						</label>

						<span class="ohmylms-password-show">
							<input class="ohmylms-input-text password" type="password" name="password2" id="confirm-password">

							<label class="show-password-icon" tabindex="0">
								<input type="checkbox" name="show-password-checkbox" class="show-password-checkbox" aria-required="true" aria-hidden="true">
								
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
					</div>
				</div>

				<!-- <div class="ohmylms-password-strength">
					<ul>
						<li class="password-strength matched">
							<?php // echo __( 'Password strength: weak.', 'ohmylms' );. ?>
						</li>

						<li class="password-characters">
							<?php // echo __( 'Password least 8-12 characters long.', 'ohmylms' );. ?>
						</li>

						<li class="password-number">
							<?php // echo __( 'Contains a number or symbol.', 'ohmylms' );. ?>
						</li>

						<li class="password-case">
							<?php // echo __( 'Uppercase letters (A-Z); Lowercase letters (a-z).', 'ohmylms' );. ?>
						</li>
					</ul>
				</div> -->
			</div>

			<?php wp_nonce_field( 'save_account_details', 'save-account-details-nonce' ); ?>

			<input type="hidden" name="action" value="save_account_details">

			<?php ohmylms_get_template( 'global/form-submit.php' ); ?>

			<?php do_action( 'ohmylms_edit_account_form_end' ); ?>
		</form>

	</div>
</div>

<?php do_action( 'ohmylms_after_edit_account_form' ); ?>

<!-- Hidden templates for dynamic fields -->
<template id="skills-template">
	<div class="ohmylms-dynamic-field-row">
		<span class="ohmylms-input-wrapper">
			<input type="text" name="skills[]" placeholder="<?php echo esc_attr( 'Enter a skill', 'ohmylms' ); ?>">
		</span>
		<button type="button" class="ohmylms-remove-field" aria-label="<?php echo esc_attr__( 'Remove skill', 'ohmylms' ); ?>">×</button>
	</div>
</template>

<template id="links-template">
	<div class="ohmylms-dynamic-field-row ohmylms-link-row">
		<span class="ohmylms-input-wrapper ohmylms-link-label">
			<input type="text" name="link_labels[]" placeholder="<?php echo esc_attr( 'Label (e.g., LinkedIn, Facebook)', 'ohmylms' ); ?>">
		</span>
		<span class="ohmylms-input-wrapper ohmylms-link-url">
			<input type="url" name="link_urls[]" placeholder="<?php echo esc_attr( 'https://example.com', 'ohmylms' ); ?>">
		</span>
		<button type="button" class="ohmylms-remove-field" aria-label="<?php echo esc_attr__( 'Remove link', 'ohmylms' ); ?>">×</button>
	</div>
</template>

<style>
/* Styles for dynamic fields */
.ohmylms-dynamic-fields {
	margin-bottom: 10px;
}

.ohmylms-dynamic-field-row {
	display: flex;
	align-items: center;
	margin-bottom: 10px;
	gap: 10px;
}

.ohmylms-link-row .ohmylms-input-wrapper {
	flex: 1;
}

.ohmylms-link-label {
	flex: 0 0 30% !important;
}

.ohmylms-link-url {
	flex: 1 !important;
}

.ohmylms-remove-field {
	background: #f1f1f1;
	color: #666;
	border: 1px solid #ddd;
	border-radius: 50%;
	width: 32px;
	height: 32px;
	cursor: pointer;
	font-size: 18px;
	line-height: 1;
	flex-shrink: 0;
	display: flex;
	align-items: center;
	justify-content: center;
	transition: all 0.2s ease;
}

.ohmylms-remove-field:hover {
	background: #e74c3c;
	color: white;
	border-color: #e74c3c;
}

.ohmylms-add-field {
	margin-top: 10px;
}

.skills-section .ohmylms-input-wrapper,
.links-section .ohmylms-input-wrapper {
	flex: 1;
}
</style>
