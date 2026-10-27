<?php
/**
 * Template for displaying course content in popup.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/course-popup-content.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

defined( 'ABSPATH' ) || exit();

?>
<div data-item-id="course-<?php echo esc_attr( the_ID() ); ?>" class="ohmylms-course-card-popup">
	<?php
		/**
		 * Hook: ohmylms_course_card_popup.
		 *
		 * @hooked: ohmylms_loop_course_title (5).
		 * @hooked: ohmylms_loop_course_update (10).
		 * @hooked: ohmylms_loop_course_meta (15).
		 * @hooked: ohmylms_loop_course_description (20).
		 * @hooked: ohmylms_loop_course_add_to_cart (25).
		 */
		do_action( 'ohmylms_course_card_popup', get_the_ID() );
	?>
</div>
