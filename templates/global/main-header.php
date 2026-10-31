<?php
/**
 * Template for displaying dashboard of student profile
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/global/main-header.php
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 * @global \OhMyLMS\Data\Student $student
 * @global string $my_profile_url Custom URL for My Profile link (optional)
 * @global string $my_courses_url Custom URL for My Courses link (optional)
 */

defined( 'ABSPATH' ) || exit();

// Get URLs from General Settings (global) or use defaults.
$my_profile_url = ohmylms_get_nav_link_url( 'profile' );
$my_courses_url = ohmylms_get_nav_link_url( 'courses' );

?>



<header class="ohmylms-header">
	<div class="ohmylms-container">
		<div class="ohmylms-header-wrapper">
			<div class="ohmylms-header-left">
				<?php
				if ( get_custom_logo() ) {
					echo get_custom_logo();
				} else {
					echo '<a href="' . esc_url( home_url() ) . '" class="custom-logo-link">' . esc_html( get_bloginfo( 'name' ) ) . '</a>';
				}
				?>

<!--				<div class="search-box">-->
<!--                    --><?php // include(OHMYLMS_DIR . '/assets/images/icon/search-icon.php');. ?>
<!--					<input type="search" name="ohmylms-search" placeholder="Search...">-->
<!---->
<!--                    <span class="ohmylms-search-close">-->
<!--                        --><?php // include(OHMYLMS_DIR . '/assets/images/icon/cross-icon.php');. ?>
<!--                    </span>-->
<!--				</div>-->
			</div>

			<div class="ohmylms-header-right">
<!--                <button type="button" class="ohmylms-mobile-search-btn">-->
<!--                    --><?php // include(OHMYLMS_DIR . '/assets/images/icon/search-icon.php');. ?>
<!--                </button>-->
<!---->
<!--				<div class="ohmylms-notification">-->
<!--					<a href="" title="See all notifications">-->
<!--                        --><?php // include(OHMYLMS_DIR . '/assets/images/icon/notification-icon.php');. ?>
<!--						<span class="notification-count">10</span>-->
<!--					</a>-->
<!--				</div>-->

				<?php $student_profile_photo = $student->get_profile_image(); ?>
				<div class="ohmylms-user"
					data-wp-interactive="ohmylms/ui"
					data-wp-context='{"open":false}'
					data-wp-class--show-dropdown="context.open"
					data-wp-on-document--click="actions.dropdownOutside"
					data-wp-on-document--keydown="actions.dropdownEscape"
					data-wp-on--focusout="actions.dropdownFocusout">
					<a href="#" class="ohmylms-user-avatar <?php echo esc_attr( $student_profile_photo ? 'has-profile-photo' : '' ); ?>"
						role="button" aria-expanded="false" aria-controls="ohmylms-user-dropdown"
						aria-label="<?php esc_attr_e( 'Account menu', 'ohmylms' ); ?>"
						data-wp-bind--aria-expanded="context.open"
						data-wp-on--click="actions.dropdownToggle"
						data-wp-on--keydown="actions.dropdownKeydown">
						<?php

						if ( $student_profile_photo ) {
							echo '<img class="student-profile-photo" src="' . esc_url( $student_profile_photo ) . '" alt="Student Profile Photo" id="student-profile-photo">';
						} else {
							echo esc_html( ohmylms_get_initials( $student->get_first_name(), $student->get_last_name() ) );
						}
						?>
					</a>

					<ul class="ohmylms-user-dropdown" id="ohmylms-user-dropdown">
						<li>
							<a href="<?php echo esc_url( ohmylms_get_account_endpoint_url( 'dashboard' ) ); ?>" class="dashboard-link asdfads">
								<?php require OHMYLMS_DIR . '/assets/images/icon/home-icon.php'; ?>
								<?php echo esc_html__( 'Dashboard', 'ohmylms' ); ?>
							</a>
						</li>

						<li>
							<a href="<?php echo esc_url( $my_profile_url ); ?>" class="my-profile-link">
								<?php require OHMYLMS_DIR . '/assets/images/icon/user-circle-outline-icon.php'; ?>
								<?php echo esc_html__( 'My Profile', 'ohmylms' ); ?>
							</a>
						</li>

						<li>
							<a href="<?php echo esc_url( $my_courses_url ); ?>" class="my-course-link">
								<?php require OHMYLMS_DIR . '/assets/images/icon/course-icon.php'; ?>
								<?php echo esc_html__( 'My Courses', 'ohmylms' ); ?>
							</a>
						</li>

<!--                        <li>-->
<!--                            <a href="--><?php // echo esc_url( ohmylms_get_account_endpoint_url( 'settings' ) );. ?><!--" class="settings-link">-->
<!--                                --><?php // include(OHMYLMS_DIR . '/assets/images/icon/settings-icon.php');. ?>
<!--                                --><?php // echo __( 'Settings', 'ohmylms' );. ?>
<!--                            </a>-->
<!--                        </li>-->

						<?php do_action( 'ohmylms_header_user_dropdown_items' ); ?>

						<li>
							<a href="<?php echo esc_url( ohmylms_get_account_endpoint_url( 'customer-logout' ) ); ?>" class="logout-link">
								<?php require OHMYLMS_DIR . '/assets/images/icon/logout-icon.php'; ?>
								<?php echo esc_html__( 'Log Out', 'ohmylms' ); ?>
							</a>
						</li>
					</ul>

				</div>
			</div>

		</div>
	</div>
</header>
