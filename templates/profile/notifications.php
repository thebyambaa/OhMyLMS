<?php
/**
 * Template for displaying notifications of student profile
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/profile/notifications.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 * @global \OhMyLMS\Data\Student $student
 */

defined( 'ABSPATH' ) || exit();
?>
<div class="ohmylms-student-profile-tab-content student-notifications">
	<h4 class="profile-tab-title">
		<?php echo esc_html__( 'Notifications ', 'ohmylms' ); ?>
	</h4>

	<form action="" method="post">
		<?php do_action( 'ohmylms_notification_form_start' ); ?>

		<div class="ohmylms-notification-box">
			<div class="ohmylms-notification-settings-group experience-notifications">
				<p class="settings-group-title">
					<?php echo esc_html__( 'Experience Notifications ', 'ohmylms' ); ?>
				</p>

				<div class="single-notification-settings chapter">
					<div class="notification-settings-left">
						<h6 class="settings-title">
							<?php echo esc_html__( 'Chapter ', 'ohmylms' ); ?>
						</h6>

						<p class="settings-desc">
							<?php echo esc_html__( 'Notify me when a new Module is released. ', 'ohmylms' ); ?>
						</p>
					</div>

					<div class="notification-settings-right">
						<label class="ohmylms-switcher">
							<input type="checkbox" name="notification_chapter" value="<?php echo esc_attr( $student->get_notification_chapter() ); ?>" <?php echo $student->get_notification_chapter() === 'on' ? 'checked' : ''; ?>>
							<span class="switcher-slider"></span>
						</label>
					</div>
				</div>

				<div class="single-notification-settings new-course-content">
					<div class="notification-settings-left">
						<h6 class="settings-title">
							<?php echo esc_html__( 'New course content ', 'ohmylms' ); ?>
						</h6>

						<p class="settings-desc">
							<?php echo esc_html__( 'Notify me when new course content is shared. ', 'ohmylms' ); ?>
						</p>
					</div>

					<div class="notification-settings-right">
						<label class="ohmylms-switcher">
							<input type="checkbox" name="notification_new_course_content" value="<?php echo esc_attr( $student->get_notification_new_course_content() ); ?>" <?php echo $student->get_notification_new_course_content() === 'on' ? 'checked' : ''; ?>>
							<span class="switcher-slider"></span>
						</label>
					</div>
				</div>

				<!-- <div class="single-notification-settings modules">
					<div class="notification-settings-left">
						<h6 class="settings-title">
							<?php // echo __( 'Modules ', 'ohmylms' );. ?>
						</h6>

						<p class="settings-desc">
							<?php // echo __( 'Notify me when a new Module is released. ', 'ohmylms' );. ?>
						</p>
					</div>

					<div class="notification-settings-right">
						<label class="ohmylms-switcher">
							<input type="checkbox" name="new_course_module">
							<span class="switcher-slider"></span>
						</label>
					</div>
				</div> -->

			</div>

			<!-- <div class="ohmylms-notification-settings-group instructor-notification">
				<p class="settings-group-title">
					<?php // echo __( 'Instructor Notifications ', 'ohmylms' );. ?>
				</p>

				<div class="single-notification-settings registrations">
					<div class="notification-settings-left">
						<h6 class="settings-title">
							<?php // echo __( 'Registrations ', 'ohmylms' );. ?>
						</h6>

						<p class="settings-desc">
							<?php // echo __( 'Notify me about new learner registrations and course applications. ', 'ohmylms' );. ?>
						</p>
					</div>

					<div class="notification-settings-right">
						<label class="ohmylms-switcher">
							<input type="checkbox" name="registrations">
							<span class="switcher-slider"></span>
						</label>
					</div>
				</div>

				<div class="single-notification-settings assignments">
					<div class="notification-settings-left">
						<h6 class="settings-title">
							<?php // echo __( 'Assignments ', 'ohmylms' );. ?>
						</h6>

						<p class="settings-desc">
							<?php // echo __( 'Notify me about new assignment submissions. ', 'ohmylms' );. ?>
						</p>
					</div>

					<div class="notification-settings-right">
						<label class="ohmylms-switcher">
							<input type="checkbox" name="assignments">
							<span class="switcher-slider"></span>
						</label>
					</div>
				</div>

			</div> -->

			<div class="ohmylms-notification-settings-group community-notifications">
				<p class="settings-group-title">
					<?php echo esc_html__( 'Community Notifications ', 'ohmylms' ); ?>
				</p>

				<div class="single-notification-settings direct-messages">
					<div class="notification-settings-left">
						<h6 class="settings-title">
							<?php echo esc_html__( 'Direct messages ', 'ohmylms' ); ?>
						</h6>

						<p class="settings-desc">
							<?php echo esc_html__( 'Notify me when I receive a new direct message. ', 'ohmylms' ); ?>
						</p>
					</div>

					<div class="notification-settings-right">
						<label class="ohmylms-switcher">
							<input type="checkbox" name="notification_direct_messages" value="<?php echo esc_attr( $student->get_notification_direct_messages() ); ?>" <?php echo $student->get_notification_direct_messages() === 'on' ? 'checked' : ''; ?>>
							<span class="switcher-slider"></span>
						</label>
					</div>
				</div>

				<div class="single-notification-settings comments-replies">
					<div class="notification-settings-left">
						<h6 class="settings-title">
							<?php echo esc_html__( 'Comments, replies and mentions ', 'ohmylms' ); ?>
						</h6>

						<p class="settings-desc">
							<?php echo esc_html__( 'Notify me about new comments, replies or when I\'m mentioned. ', 'ohmylms' ); ?>
						</p>
					</div>

					<div class="notification-settings-right">
						<label class="ohmylms-switcher">
							<input type="checkbox" name="notification_comments_replies" value="<?php echo esc_attr( $student->get_notification_comments_replies() ); ?>" <?php echo $student->get_notification_comments_replies() === 'on' ? 'checked' : ''; ?>>
							<span class="switcher-slider"></span>
						</label>
					</div>
				</div>

			</div>
		</div>

		<?php wp_nonce_field( 'save_account_notification', 'save-account-notification-nonce' ); ?>

		<input type="hidden" name="action" value="save_account_notification">

		<?php ohmylms_get_template( 'global/form-submit.php' ); ?>

		<?php do_action( 'ohmylms_notification_form_end' ); ?>

	</form>
</div>

