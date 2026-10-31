<?php
/**
 * The template for displaying lesson's quiz
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-lesson/content-quiz.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
$current_post_type = get_post_type();
ohmylms_enqueue_interactivity_module( 'ohmylms/ui' );
?>

<?php
/**
 * hook: ohmylms_before_lesson_main_content.
 *
 * hooked: ohmylms_show_toast_notices (5).
 */
do_action( 'ohmylms_before_lesson_main_content' );
?>

<section class="ohmylms-lesson-details" data-wp-interactive="ohmylms/ui" <?php echo wp_interactivity_data_wp_context( array( 'sidebarOpen' => false ) ); ?> data-wp-class--open-sidebar="context.sidebarOpen" data-wp-on-document--click="actions.outside" data-wp-on-document--keydown="actions.escape">
	<?php ohmylms_get_template( 'global/ohmylms-celebration.php' ); ?>
	
	<span class="ohmylms-lesson-details-hamburger" role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Open lesson navigation', 'ohmylms' ); ?>" aria-expanded="false" data-wp-bind--aria-expanded="context.sidebarOpen" data-wp-on--click="actions.openSidebar" data-wp-on--keydown="actions.sidebarKey">
		<?php require OHMYLMS_DIR . '/assets/images/icon/hamburger-icon.php'; ?>
	</span>
	
	<div class="ohmylms-container">
		<div class="ohmylms-lesson-content-wrapper">
			<div class="ohmylms-lesson-content">
				<?php
				$content_drip_protection_message = apply_filters( 'ohmylms_drip_protection_message', '', get_the_ID(), $current_post_type, get_current_user_id() );
				if ( empty( $content_drip_protection_message ) ) {
					if ( 'ohmylms-lesson' === $current_post_type ) {
						ohmylms_get_template( 'single-lesson/content-lesson.php' );
					}

					if ( 'ohmylms-assignment' === $current_post_type ) {
						ohmylms_get_template( 'single-lesson/content-assignment.php' );
					}

					if ( 'ohmylms-quiz' === $current_post_type ) {
						ohmylms_get_template( 'single-lesson/content-quiz.php', array( 'quiz' => ohmylms_get_quiz( get_the_ID() ) ) );
					}
					if ( 'ohmylms-session' === $current_post_type ) {
						ohmylms_get_template( 'single-lesson/content-session' );
					}

					ohmylms_get_template( 'single-lesson/content-navigation.php' );
				} else {
					echo '<p class="ohmylms-drip-notice" role="status">' . esc_html( $content_drip_protection_message ) . '</p>';
				}
				?>
			</div>

			<?php ohmylms_get_template( 'single-lesson/sidebar/sidebar.php' ); ?>
		</div>
	</div>
</section>

<?php
/**
 * ohmylms_after_lesson_main_content hook.
 */
do_action( 'ohmylms_after_lesson_main_content' );
?>
