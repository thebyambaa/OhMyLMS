<?php
/**
 * OhMyLMS Loop Duration
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/loop/duration.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $course;
$course_settings = get_post_meta( $course->get_id(), 'ohmylms_course_settings', true );
?>
<?php if ( is_array( $course_settings ) && isset( $course_settings['duration'] ) ) : ?>
	<div class="duration"><?php echo esc_html__( 'Duration: ', 'ohmylms' ) . $course_settings['duration'] . __( ' Weeks', 'ohmylms' ); ?></div>
<?php endif; ?>
