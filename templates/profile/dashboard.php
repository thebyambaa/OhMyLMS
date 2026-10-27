<?php
/**
 * Template for displaying dashboard of student profile
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/profile/dashboard.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

defined( 'ABSPATH' ) || exit();
?>


<div class="ohmylms-dashboard-wrapper">
	<?php

	/**
	 * Action hook before displaying the dashboard content.
	 *
	 * @since 1.0.0
	 */
	do_action( 'ohmylms_lms_student_profile_before_dashboard_content' );

	/**
	 * Display the dashboard content.
	 *
	 * @since 1.0.0
	 */
	do_action( 'ohmylms_lms_student_profile_dashboard_content' );


	/**
	 * Action hook after displaying the dashboard content.
	 *
	 * @since 1.0.0
	 */
	do_action( 'ohmylms_lms_student_profile_after_dashboard_content' );

	?>
</div>
