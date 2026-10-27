<?php
/**
 * Template for displaying settings of student profile
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/profile/settings.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 * @global \OhMyLMS\Data\Student $student
 */

defined( 'ABSPATH' ) || exit();

do_action( 'ohmylms_before_edit_account_form' );

?>

<div class="ohmylms-student-profile-tab-content student-settings">
	<h4 class="profile-tab-title">
		<?php echo esc_html__( 'Settings', 'ohmylms' ); ?>
	</h4>

	<form action="" method="post">
		<?php do_action( 'ohmylms_edit_account_form_start' ); ?>

		<div class="ohmylms-student-account account-username">
			<h6 class="account-title">
				<?php echo esc_html__( 'Account', 'ohmylms' ); ?>
			</h6>

			<div class="ohmylms-form-wrapper">
				<div class="ohmylms-form-group first-name half-width">
					<label>
						<?php echo esc_html__( 'First Name', 'ohmylms' ); ?>
						<span class="required">*</span>
					</label>

					<span class="ohmylms-input-wrapper">
						<input type="text" name="first_name" placeholder="<?php echo esc_attr__( 'Enter your first name', 'ohmylms' ); ?>" value="<?php echo esc_attr( $student->get_first_name() ); ?>">
					</span>
				</div>

				<div class="ohmylms-form-group last-name half-width">
					<label>
						<?php echo esc_html__( 'Last Name', 'ohmylms' ); ?>
						<span class="required">*</span>
					</label>

					<span class="ohmylms-input-wrapper">
						<input type="text" name="last_name" placeholder="<?php echo esc_attr__( 'Enter your last name', 'ohmylms' ); ?>" value="<?php echo esc_attr( $student->get_last_name() ); ?>">
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
			</div>
		</div>

		<div class="ohmylms-student-account account-password">
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

			<!--		<div class="ohmylms-password-strength">-->
			<!--			<ul>-->
			<!--				<li class="password-strength matched">--><?php // echo __( 'Password strength: weak.', 'ohmylms' );. ?><!--</li>-->
			<!--				<li class="password-characters">--><?php // echo __( 'Password least 8-12 characters long.', 'ohmylms' );. ?><!--</li>-->
			<!--				<li class="password-number">--><?php // echo __( 'Contains a number or symbol.', 'ohmylms' );. ?><!--</li>-->
			<!--				<li class="password-case">--><?php // echo __( 'Uppercase letters (A-Z); Lowercase letters (a-z).', 'ohmylms' );. ?><!--</li>-->
			<!--			</ul>-->
			<!--		</div>-->
		</div>

		<?php wp_nonce_field( 'save_account_details', 'save-account-details-nonce' ); ?>
		<input type="hidden" name="action" value="save_account_details">

		<?php ohmylms_get_template( 'global/form-submit.php' ); ?>

		<?php do_action( 'ohmylms_edit_account_form_end' ); ?>
	</form>
</div>

