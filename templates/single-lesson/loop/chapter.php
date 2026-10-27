<?php
/**
 * The template for displaying lesson's Assignment content
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-lesson/content-assignment.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 * @global \OhMyLMS\Data\Chapter $chapter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
$chapter_lessons    = $chapter->get_lessons( 'objects' );
$chapter_id         = $chapter->get_id();
$chapter_title      = $chapter->get_name();
$current_chapter_id = ohmylms_get_chapter_id_by_content_id( get_the_ID() );
$is_active          = $current_chapter_id == $chapter_id ? 'active' : '';
$display            = $current_chapter_id == $chapter_id ? 'block' : 'none';
ohmylms_enqueue_interactivity_module( 'ohmylms/ui' );
?>


<div class="ohmylms-accordion-item <?php echo esc_attr( $is_active ); ?>" data-wp-interactive="ohmylms/ui" <?php echo wp_interactivity_data_wp_context( array( 'open' => (bool) $is_active ) ); ?> data-wp-class--active="context.open">
	<div class="ohmylms-accordion-head" role="button" tabindex="0" aria-expanded="<?php echo esc_attr( $is_active ? 'true' : 'false' ); ?>" data-wp-bind--aria-expanded="context.open" data-wp-on--click="actions.toggle" data-wp-on--keydown="actions.keyToggle" aria-controls="lms-accordion-body-<?php echo esc_attr( $chapter_id ); ?>" id="lms-accordion-head-<?php echo esc_attr( $chapter_id ); ?>">
		<?php
		$is_lesson_completed = $chapter->maybe_all_content_is_completed( get_current_user_id() );
		$lessonProgress      = $chapter->total_completion_rate( get_current_user_id() );
		?>
		<p class="ohmylms-accordion-title">
			<?php echo esc_html( $chapter_title ); ?>
			<span class="publish-date">Released on <?php echo date( 'M d, Y', strtotime( $chapter->get_date_created() ) ); ?></span>
		</p>
		<div class="lesson-content-progrss">
			<?php if ( $is_lesson_completed ) { ?>
				<svg class="course-completed" width="34" height="34" fill="none" viewBox="0 0 34 34" xmlns="http://www.w3.org/2000/svg"><rect width="33" height="33" x=".5" y=".5" stroke="#19AA32" rx="16.5"/><rect width="20" height="20" x="7" y="7" fill="#19AA32" rx="10"/><path fill="#fff" d="M20.373 13.818l-4.628 4.628-2.122-2.121a.818.818 0 00-1.157 1.157l2.7 2.7a.818.818 0 001.157 0l5.207-5.207a.818.818 0 10-1.157-1.157z"/></svg>
				<?php
			} else {
				?>
				<span class="ohmylms-circle-progressbar">
					<?php echo ohmylms_circular_progressbar( 36, $lessonProgress, 2, '#E2E4EA', '#19AA32' ); ?>
					<small><?php echo esc_html( $lessonProgress . '%' ); ?></small>
				</span>
				<?php
			}
			?>
		</div>
	</div>

	<div class="ohmylms-accordion-body" id="lms-accordion-body-<?php echo esc_attr( $chapter_id ); ?>" role="region" aria-labelledby="lms-accordion-head-<?php echo esc_attr( $chapter_id ); ?>" style="display: <?php echo esc_attr( $display ); ?>" data-wp-style--display="state.display">
		<ul class="ohmylms-lesson-list">
			<?php
			foreach ( $chapter_lessons as $lesson ) {
				ohmylms_get_template( 'single-lesson/loop/lesson.php', array( 'lesson' => $lesson ) );
			}
			?>
		</ul>
	</div>
</div>
