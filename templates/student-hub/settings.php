<?php
/**
 * Settings: account, appearance, notifications and connections. Each card saves on its own and its
 * Save button stays disabled until something changes.
 * Override in yourtheme/ohmylms/student-hub/settings.php.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Data\Student;
use OhMyLMS\Student\Hub;
use OhMyLMS\Student\LearnerProfile;
use OhMyLMS\Student\SettingsSave;

defined( 'ABSPATH' ) || exit;

$hub_user     = wp_get_current_user();
$hub_student  = new Student( $hub_user->ID );
$hub_flash    = SettingsSave::flash( $hub_user->ID );
$hub_action   = admin_url( 'admin-post.php' );
$teacher_mode = ! empty( $teacher_mode );
$parent_mode  = ! empty( $parent_mode );
$school_mode  = ! empty( $school_mode );
/**
 * Opening tag of a card form with its nonce and card name.
 *
 * @param string $card Card key.
 */
$hub_open = static function ( $card ) use ( $hub_action, $teacher_mode, $parent_mode, $school_mode ) {
	echo '<form class="oml-hub-card oml-hub-settings-card" method="post" action="' . esc_url( $hub_action ) . '" data-hub-dirty autocomplete="off">';
	echo '<input type="hidden" name="action" value="' . esc_attr( SettingsSave::ACTION ) . '">';
	echo '<input type="hidden" name="card" value="' . esc_attr( $card ) . '">';
	echo '<input type="hidden" name="hub_role" value="' . esc_attr( $teacher_mode ? 'teacher' : ( $parent_mode ? 'parent' : ( $school_mode ? 'school' : 'learner' ) ) ) . '">';
	Hub::nonce_field( SettingsSave::ACTION . '_' . $card );
};
/**
 * Save button, disabled until the form differs from how it loaded.
 */
$hub_save = static function () {
	echo '<p class="oml-hub-actions"><button type="submit" class="oml-hub-button oml-hub-save" data-hub-save disabled>' . esc_html__( 'Save changes', 'ohmylms' ) . '</button></p>';
};
?>
<section class="oml-hub-banner">
	<h1><?php esc_html_e( 'Settings', 'ohmylms' ); ?></h1>
	<p><?php esc_html_e( 'Manage your account, how the workspace looks and who you are connected to.', 'ohmylms' ); ?></p>
</section>

<?php if ( $hub_flash ) : ?>
	<div class="oml-hub-notice <?php echo $hub_flash['saved'] ? 'is-success' : 'is-error'; ?>" role="status">
		<?php if ( $hub_flash['saved'] ) : ?>
			<p class="oml-hub-notice-line"><?php esc_html_e( 'Your changes were saved.', 'ohmylms' ); ?></p>
		<?php else : ?>
			<?php foreach ( $hub_flash['errors'] as $hub_error ) : ?>
				<p class="oml-hub-notice-line"><?php echo esc_html( $hub_error ); ?></p>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
<?php endif; ?>

<?php if ( 'account' === $view ) : ?>
	<div class="oml-hub-settings">
		<?php $hub_open( 'account' ); ?>
			<h2><?php esc_html_e( 'Basics', 'ohmylms' ); ?></h2>
			<div class="oml-hub-field"><label for="hub-first"><?php esc_html_e( 'First name', 'ohmylms' ); ?></label><input id="hub-first" name="first_name" type="text" maxlength="60" autocomplete="given-name" value="<?php echo esc_attr( $hub_student->get_first_name() ); ?>"></div>
			<div class="oml-hub-field"><label for="hub-last"><?php esc_html_e( 'Last name', 'ohmylms' ); ?></label><input id="hub-last" name="last_name" type="text" maxlength="60" autocomplete="family-name" value="<?php echo esc_attr( $hub_student->get_last_name() ); ?>"></div>
			<div class="oml-hub-field"><label for="hub-display"><?php esc_html_e( 'Display name', 'ohmylms' ); ?></label><input id="hub-display" name="display_name" type="text" required maxlength="60" value="<?php echo esc_attr( $hub_user->display_name ); ?>"></div>
			<div class="oml-hub-field"><label for="hub-phone"><?php esc_html_e( 'Phone number', 'ohmylms' ); ?></label><input id="hub-phone" name="phone" type="tel" maxlength="40" autocomplete="tel" inputmode="tel" placeholder="+976 9911 2233" value="<?php echo esc_attr( $hub_student->get_phone() ); ?>"><small><?php esc_html_e( 'Include the country code if you are outside Mongolia.', 'ohmylms' ); ?></small></div>
			<?php if ( ! $teacher_mode && ! $parent_mode && ! $school_mode ) : ?>
				<?php $hub_details = LearnerProfile::get( $hub_user->ID ); ?>
				<div class="oml-hub-field"><label for="hub-province"><?php esc_html_e( 'Province', 'ohmylms' ); ?></label><?php LearnerProfile::select( 'hub-province', 'province', LearnerProfile::provinces(), $hub_details['province'], __( 'Choose your province', 'ohmylms' ) ); ?></div>
				<div class="oml-hub-field"><label for="hub-school"><?php esc_html_e( 'School name', 'ohmylms' ); ?></label><input id="hub-school" name="school_name" type="text" maxlength="<?php echo esc_attr( LearnerProfile::MAX_SCHOOL ); ?>" autocomplete="organization" value="<?php echo esc_attr( $hub_details['school_name'] ); ?>"></div>
				<div class="oml-hub-field"><label for="hub-grade"><?php esc_html_e( 'Grade', 'ohmylms' ); ?></label><?php LearnerProfile::select( 'hub-grade', 'grade', LearnerProfile::grades(), $hub_details['grade'], __( 'Choose your grade', 'ohmylms' ) ); ?></div>
			<?php endif; ?>
			<p><small><?php esc_html_e( 'Change your email address in the Email address card below.', 'ohmylms' ); ?></small></p>
			<?php $hub_save(); ?>
		</form>

		<?php $hub_open( 'about' ); ?>
			<h2><?php esc_html_e( 'About you', 'ohmylms' ); ?></h2>
			<div class="oml-hub-field"><label for="hub-bio"><?php esc_html_e( 'Bio', 'ohmylms' ); ?></label><textarea id="hub-bio" name="bio" rows="3" maxlength="1000"><?php echo esc_textarea( $hub_student->get_bio() ); ?></textarea></div>
			<div class="oml-hub-field"><label for="hub-interests"><?php esc_html_e( 'Interests', 'ohmylms' ); ?></label><input id="hub-interests" name="interests" type="text" maxlength="300" value="<?php echo esc_attr( $hub_student->get_interest() ); ?>"></div>
			<div class="oml-hub-field"><label for="hub-country"><?php esc_html_e( 'Country', 'ohmylms' ); ?></label><input id="hub-country" name="country" type="text" maxlength="80" autocomplete="country-name" value="<?php echo esc_attr( $hub_student->get_country() ); ?>"></div>
			<div class="oml-hub-field"><label for="hub-whatsapp"><?php esc_html_e( 'WhatsApp', 'ohmylms' ); ?></label><input id="hub-whatsapp" name="whatsapp" type="tel" maxlength="40" value="<?php echo esc_attr( $hub_student->get_whatsapp() ); ?>"></div>
			<div class="oml-hub-field"><label for="hub-address"><?php esc_html_e( 'Address', 'ohmylms' ); ?></label><input id="hub-address" name="address" type="text" maxlength="200" autocomplete="street-address" value="<?php echo esc_attr( $hub_student->get_address() ); ?>"></div>
			<?php $hub_save(); ?>
		</form>

		<?php $hub_open( 'email' ); ?>
			<h2><?php esc_html_e( 'Email address', 'ohmylms' ); ?></h2>
			<div class="oml-hub-field"><label for="hub-email"><?php esc_html_e( 'Email', 'ohmylms' ); ?></label><input id="hub-email" name="email" type="email" required autocomplete="email" value="<?php echo esc_attr( $hub_user->user_email ); ?>"></div>
			<div class="oml-hub-field"><label for="hub-email-pass"><?php esc_html_e( 'Current password', 'ohmylms' ); ?></label><input id="hub-email-pass" name="password_current" type="password" autocomplete="current-password"><small><?php esc_html_e( 'Required to change your email address.', 'ohmylms' ); ?></small></div>
			<?php $hub_save(); ?>
		</form>

		<?php $hub_open( 'password' ); ?>
			<h2><?php esc_html_e( 'Password', 'ohmylms' ); ?></h2>
			<div class="oml-hub-field"><label for="hub-pass-current"><?php esc_html_e( 'Current password', 'ohmylms' ); ?></label><input id="hub-pass-current" name="password_current" type="password" autocomplete="current-password"></div>
			<div class="oml-hub-field"><label for="hub-pass-new"><?php esc_html_e( 'New password', 'ohmylms' ); ?></label><input id="hub-pass-new" name="password1" type="password" minlength="<?php echo esc_attr( SettingsSave::MIN_PASS ); ?>" maxlength="<?php echo esc_attr( SettingsSave::MAX_PASS ); ?>" autocomplete="new-password"></div>
			<div class="oml-hub-field"><label for="hub-pass-confirm"><?php esc_html_e( 'Confirm new password', 'ohmylms' ); ?></label><input id="hub-pass-confirm" name="password2" type="password" autocomplete="new-password"></div>
			<?php $hub_save(); ?>
		</form>
	</div>
<?php elseif ( 'appearance' === $view ) : ?>
	<section class="oml-hub-card oml-hub-settings-card" aria-labelledby="hub-appearance-heading">
		<h2 id="hub-appearance-heading"><?php esc_html_e( 'Appearance', 'ohmylms' ); ?></h2>
		<p><?php esc_html_e( 'Choose how the workspace looks. This applies straight away and is remembered on this device only.', 'ohmylms' ); ?></p>
		<div class="oml-hub-theme oml-hub-theme-large" role="group" aria-label="<?php esc_attr_e( 'Appearance on this device', 'ohmylms' ); ?>">
			<button type="button" data-hub-theme-set="system"><?php esc_html_e( 'Match my device', 'ohmylms' ); ?></button>
			<button type="button" data-hub-theme-set="light"><?php esc_html_e( 'Light', 'ohmylms' ); ?></button>
			<button type="button" data-hub-theme-set="dark"><?php esc_html_e( 'Dark', 'ohmylms' ); ?></button>
		</div>
	</section>
<?php elseif ( 'notifications' === $view ) : ?>
	<?php
	$hub_prefs = array(
		'chapter'            => array( __( 'New unit released', 'ohmylms' ), $hub_student->get_notification_chapter() ),
		'new_course_content' => array( __( 'New course content', 'ohmylms' ), $hub_student->get_notification_new_course_content() ),
		'direct_messages'    => array( __( 'Direct messages', 'ohmylms' ), $hub_student->get_notification_direct_messages() ),
		'comments_replies'   => array( __( 'Comments, replies and mentions', 'ohmylms' ), $hub_student->get_notification_comments_replies() ),
	);
	$hub_open( 'notifications' );
	?>
		<h2><?php esc_html_e( 'Notifications', 'ohmylms' ); ?></h2>
		<p><?php esc_html_e( 'Choose what you would like to hear about.', 'ohmylms' ); ?></p>
		<?php foreach ( $hub_prefs as $hub_key => $hub_pref ) : ?>
			<label class="oml-hub-check"><input type="checkbox" name="notification_<?php echo esc_attr( $hub_key ); ?>" value="1" <?php checked( 'on', $hub_pref[1] ); ?>><span><?php echo esc_html( $hub_pref[0] ); ?></span></label>
		<?php endforeach; ?>
		<?php $hub_save(); ?>
	</form>
<?php else : ?>
	<?php $hub_classes = Hub::learner_classes(); ?>
	<section class="oml-hub-card oml-hub-settings-card" aria-labelledby="hub-connections-heading">
		<h2 id="hub-connections-heading"><?php esc_html_e( 'Connections', 'ohmylms' ); ?></h2>
		<?php
		$school_result = \OhMyLMS\Student\SchoolActions::flash();
		if ( $school_result ) :
			?>
			<p class="oml-hub-notice" role="status"><?php echo esc_html( $school_result['error'] ?? $school_result['message'] ?? '' ); ?></p>
		<?php endif; ?>
		<?php foreach ( \OhMyLMS\Schools\Lifecycle::memberships() as $connection_school ) : ?>
			<form method="post" action="<?php echo esc_url( $hub_action ); ?>">
				<h3><?php echo esc_html( $connection_school['name'] ); ?></h3>
				<?php \OhMyLMS\Student\SchoolActions::fields( 'leave_school', $connection_school['id'], 'account' ); ?>
				<p><?php esc_html_e( 'Leaving removes your school and class access while keeping your account and learning history. A sole school administrator must appoint another administrator first. School-managed students must contact their administrator.', 'ohmylms' ); ?></p>
				<label class="oml-hub-check"><input type="checkbox" name="confirm" value="yes" required><span><?php esc_html_e( 'I want to leave this school.', 'ohmylms' ); ?></span></label>
				<p class="oml-hub-actions"><button type="submit" class="oml-hub-button"><?php esc_html_e( 'Leave school', 'ohmylms' ); ?></button></p>
			</form>
		<?php endforeach; ?>
		<?php if ( ! $hub_classes ) : ?>
			<p><?php echo esc_html( $teacher_mode ? __( 'Your teacher classes are listed in My classes.', 'ohmylms' ) : __( 'You are not in any class yet. Join with the invitation your teacher gave you.', 'ohmylms' ) ); ?></p>
		<?php endif; ?>
		<?php foreach ( $hub_classes as $hub_class ) : ?>
			<?php $hub_people = Hub::supporters( (int) $hub_class['id'] ); ?>
			<div class="oml-hub-list-item">
				<h3><?php echo esc_html( $hub_class['name'] ); ?> <small>&middot; <?php echo esc_html( $hub_class['school_name'] ); ?></small></h3>
				<p><strong><?php esc_html_e( 'Teachers', 'ohmylms' ); ?>:</strong> <?php echo esc_html( $hub_people['teachers'] ? implode( ', ', $hub_people['teachers'] ) : __( 'None to display', 'ohmylms' ) ); ?></p>
				<p><strong><?php esc_html_e( 'Parents / guardians', 'ohmylms' ); ?>:</strong> <?php echo esc_html( $hub_people['guardians'] ? implode( ', ', $hub_people['guardians'] ) : __( 'None to display', 'ohmylms' ) ); ?></p>
			</div>
		<?php endforeach; ?>
		<p><a class="oml-hub-button" href="<?php echo esc_url( Hub::url( 'classes', 'join' ) ); ?>"><?php esc_html_e( 'Join a class', 'ohmylms' ); ?></a></p>
	</section>
<?php endif; ?>
