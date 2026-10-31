<?php
/**
 * Template for displaying profile layout of student profile
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/profile/profile-layout.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

defined( 'ABSPATH' ) || exit();

?>

<div class="ohmylms-student-profile"
	data-wp-interactive="ohmylms/ui"
	data-wp-context='{"drawerOpen":false,"drawerPanel":".ohmylms-student-profile-sidebar","drawerInside":".ohmylms-student-profile-sidebar,.ohmylms-hamburger"}'
	data-wp-class--open-sidebar="context.drawerOpen"
	data-wp-on-document--click="actions.drawerOutside"
	data-wp-on-document--keydown="actions.drawerEscape">
	<span class="ohmylms-hamburger" aria-label="<?php esc_attr_e( 'Menu', 'ohmylms' ); ?>"
		role="button" tabindex="0" aria-controls="ohmylms-profile-sidebar" aria-expanded="false"
		data-wp-bind--aria-expanded="context.drawerOpen"
		data-wp-on--click="actions.drawerToggle"
		data-wp-on--keydown="actions.drawerKey">
		<svg width="14" height="11"  fill="none" viewBox="0 0 14 11" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" stroke="#21212F" stroke-width=".1" d="M13.125 1.872H.875C.411 1.872.05 1.482.05.96.05.439.411.05.875.05h12.25c.464 0 .825.39.825.91 0 .522-.361.912-.825.912zm0 4.484H.875c-.464 0-.825-.39-.825-.91 0-.522.361-.912.825-.912h12.25c.464 0 .825.39.825.911s-.361.91-.825.91zm0 4.483H.875c-.464 0-.825-.39-.825-.911 0-.522.361-.911.825-.911h12.25c.464 0 .825.39.825.91 0 .522-.361.912-.825.912z"/></svg>
	</span>

	<div class="ohmylms-student-profile-wrapper">

		<?php do_action( 'ohmylms_before_layout_header' ); ?>

		<?php do_action( 'ohmylms_account_navigation' ); ?>

		<div class="ohmylms-student-profile-sidebar-content">
			<?php do_action( 'ohmylms_profile_layout_content' ); ?>
		</div>
		<?php do_action( 'ohmylms_after_layout_footer' ); ?>

	</div>
</div>
