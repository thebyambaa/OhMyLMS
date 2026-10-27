<?php
/**
 * Template for displaying billing information of student profile
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/profile/billing-information.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

defined( 'ABSPATH' ) || exit();
?>

<div class="ohmylms-student-profile-tab-content student-billing">
	<h4 class="profile-tab-title">
		<?php echo esc_html__( 'Billing Information ', 'ohmylms' ); ?>
	</h4>

	<div class="ohmylms-billing-box">
		<div class="ohmylms-form-group firstname half-width">
			<label>
				<?php echo esc_html__( 'First Name', 'ohmylms' ); ?>
			</label>

			<span class="ohmylms-input-wrapper">
				<input type="text" name="firstname" placeholder="Enter first name">
			</span>
		</div>

		<div class="ohmylms-form-group lastname half-width">
			<label>
				<?php echo esc_html__( 'Last Name', 'ohmylms' ); ?>
			</label>

			<span class="ohmylms-input-wrapper">
				<input type="text" name="lastname" placeholder="Enter last name">
			</span>
		</div>

		<div class="ohmylms-form-group email">
			<label>
				<?php echo esc_html__( 'Email', 'ohmylms' ); ?>
			</label>

			<span class="ohmylms-input-wrapper">
				<input type="email" name="email" placeholder="Enter email address">
			</span>
		</div>

		<div class="ohmylms-form-group address-line1">
			<label>
				<?php echo esc_html__( 'Address line 1', 'ohmylms' ); ?>
			</label>

			<span class="ohmylms-input-wrapper">
				<input type="text" name="address-line1" placeholder="Street address, P. O. box">
			</span>
		</div>

		<div class="ohmylms-form-group address-line2">
			<label>
				<?php echo esc_html__( 'Address line 2 (optional)', 'ohmylms' ); ?>
			</label>

			<span class="ohmylms-input-wrapper">
				<input type="text" name="address-line2" placeholder="Street address, P. O. box">
			</span>
		</div>

		<div class="ohmylms-form-group city half-width">
			<label>
				<?php echo esc_html__( 'City', 'ohmylms' ); ?>
			</label>

			<span class="ohmylms-input-wrapper">
				<input type="text" name="city" placeholder="Enter your city">
			</span>
		</div>

		<div class="ohmylms-form-group state half-width">
			<label>
				<?php echo esc_html__( 'State', 'ohmylms' ); ?>
			</label>

			<span class="ohmylms-input-wrapper">
				<select name="state" id="state">
					<option value="arizona">Arizona</option>
					<option value="arizona">Arizona</option>
					<option value="arizona">Arizona</option>
					<option value="arizona">Arizona</option>
					<option value="arizona">Arizona</option>
					<option value="arizona">Arizona</option>
				</select>
			</span>
		</div>

		<div class="ohmylms-form-group zip-code half-width">
			<label>
				<?php echo esc_html__( 'ZIP / Postal code', 'ohmylms' ); ?>
			</label>

			<span class="ohmylms-input-wrapper">
				<input type="text" name="zip-code" placeholder="Enter zip code">
			</span>
		</div>

		<div class="ohmylms-form-group country half-width">
			<label>
				<?php echo esc_html__( 'Country', 'ohmylms' ); ?>
			</label>

			<span class="ohmylms-input-wrapper">
				<select name="country" id="country">
					<option value="bangladesh">Bangladesh</option>
					<option value="bangladesh">Bangladesh</option>
					<option value="bangladesh">Bangladesh</option>
					<option value="bangladesh">Bangladesh</option>
					<option value="bangladesh">Bangladesh</option>
				</select>
			</span>
		</div>

	</div>

	<?php ohmylms_get_template( 'global/form-submit.php' ); ?>

</div>
