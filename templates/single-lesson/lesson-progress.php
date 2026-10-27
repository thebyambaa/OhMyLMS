<?php
/**
 * The template for displaying single course level
 *
 * This template can be overridden by copying it to yourtheme/single-lesson/duration.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 * @global \OhMyLMS\Data\Student $student
 */

use OhMyLMS\Data\Student;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


$course_id                = ohmylms_get_course_by_content_id( get_the_ID() );
$student                  = new Student( get_current_user_id() );
$over_all_completion_rate = $student->get_over_all_completion_rate( $course_id );
?>
<section class="ohmylms-lesson-progressbar">
	<div class="ohmylms-container">
		<div class="lesson-progressbar-wrapper">
			<div class="lesson-single-progressbar ohmylms-progressbar overall-progress">
				<p class="progressbar-title">
					<span><?php echo esc_html( 'Overall progress', 'ohmylms' ); ?></span>
					<span><?php echo esc_html( $over_all_completion_rate ); ?>%</span>
				</p>

				<span class="ohmylms-progressbar-outer">
					<span class="ohmylms-progressbar-inner" style="width: <?php echo esc_attr( $over_all_completion_rate ); ?>%;" ></span>
				</span>
			</div>

			<div class="lesson-single-progressbar ohmylms-progressbar quiz-progress">
				<?php $quizProgress = $student->get_quiz_completion_rate( $course_id ); ?>

				<p class="progressbar-title">
					<span>Quiz</span>
					<span>
						<?php echo esc_html( $quizProgress . '%' ); ?>
					</span>
				</p>

				<div class="ohmylms-circle-progressbar">
					<?php
						echo ohmylms_circular_progressbar( 45, $quizProgress, 6, '#EAEDF4', '#5B65F5' );
					?>
				</div>
			</div>

			<div class="lesson-single-progressbar ohmylms-progressbar assignment-progress">
				<?php $quizProgress = $student->get_assignment_completion_rate( $course_id ); ?>

				<p class="progressbar-title">
					<span>Assignments</span>
					<span>
						<?php echo esc_html( $quizProgress . '%' ); ?>
					</span>
				</p>

				<div class="ohmylms-circle-progressbar">
					<?php
						echo ohmylms_circular_progressbar( 45, $quizProgress, 6, '#EAEDF4', '#FF811A' );
					?>
				</div>
			</div>

		</div>
	</div>
</section>
