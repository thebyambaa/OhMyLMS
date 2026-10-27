<?php
/**
 * The template for displaying lesson's Assignment content
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-lesson/content-assignment.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 * @global \OhMyLMS\Data\Lesson $lesson
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( \OhMyLMS\Student\PlayerContext::active() ) {
	return;
}

if ( get_post_type() === 'ohmylms-lesson' ) {
	$lesson = ohmylms_get_lesson( get_the_ID() );
} elseif ( get_post_type() === 'ohmylms-quiz' ) {
	$lesson = ohmylms_get_quiz( get_the_ID() );
} elseif ( 'ohmylms-session' === get_post_type() ) {
	$lesson = ohmylms_get_session( get_the_ID() );
} else {
	$lesson = ohmylms_get_assignment( get_the_ID() );
}
$course_id = ohmylms_get_course_by_content_id( $lesson->get_id() );
$course    = ohmylms_get_course( $course_id );
if ( ! $course ) {
	return; // Course not found, exit the function.
}
$course_id = $course->get_id();
$chapters  = $course->get_chapters( 'objects' );
?>


<aside class="ohmylms-lesson-sidebar">
	<ul class="ohmylms-lesson-tab-nav">
		<li class="active" data-target="#lesson-content"><?php esc_html_e( 'Course Content', 'ohmylms' ); ?></li>
	</ul>

	<div class="ohmylms-lesson-tab-content">
		<div class="ohmylms-lesson-single-tab-content lesson-content active" id="lesson-content">
			<div class="ohmylms-lesson-sidebar-accordion">
				<!-- First item should be active. First items accordion .ohmylms-accordion-body should be active -->
				<?php
				foreach ( $chapters as $chapter ) {
					ohmylms_get_template( 'single-lesson/loop/chapter.php', array( 'chapter' => $chapter ) );
				}
				?>
			</div>
		</div>

		<div class="ohmylms-lesson-single-tab-content lesson-comment" id="lesson-comment">

		</div>
	</div>

</aside>
