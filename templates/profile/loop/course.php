<?php
/**
 * Template for displaying dashboard of student profile
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/profile/name.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 * @global \OhMyLMS\Data\Student $student
 * @global \OhMyLMS\Data\Course $course
 */

defined( 'ABSPATH' ) || exit();
$class_completed = $student->is_course_completed( $course->get_id() ) ? 'ohmylms-course-completed' : '';
$certificate_url = get_current_user_id() === (int) $student->get_id() ? $student->get_course_certificate_url( $course->get_id() ) : '';
?>

<div class="ohmylms-dashboard-single-course <?php echo esc_attr( $class_completed ); ?>">
	<div class="course-info-wrapper">
		<figure>
			<img src="<?php echo esc_url( $course->get_thumbnail_url() ); ?>" alt="<?php echo esc_attr( $course->get_name() ); ?>" class="ohmylms-image">
		</figure>

		<div class="course-info">
			<a href="<?php echo esc_url( $course->get_permalink() ); ?>" class="ohmylms-course-title"><?php echo esc_html( $course->get_name() ); ?></a>

			<?php if ( ! $student->is_course_completed( $course->get_id() ) ) { ?>
				<div class="ohmylms-progressbar">
				<span class="ohmylms-progressbar-outer">
					<span class="ohmylms-progressbar-inner" style="width: <?php echo esc_attr( $student->get_course_progress_percentage( $course->get_id() ) ); ?>%;"></span>
				</span>

					<p class="progressbar-title">
						<?php
						if ( $student->get_course_progress_percentage( $course->get_id() ) == 0 ) {
							echo esc_html( $student->get_course_completed_points( $course->get_id() ) . '/' . $student->get_course_total_points( $course->get_id() ) );
						} else {
							echo esc_html( $student->get_course_completed_points( $course->get_id() ) . '/' . $student->get_course_total_points( $course->get_id() ) . ' (' . $student->get_course_progress_percentage( $course->get_id() ) . '%)' );
						}
						?>
					</p>
					<?php if ( $student->get_course_time_remaining( $course->get_id() ) ) : ?>
					<!-- <p class="time-remaining">
						<?php // include(OHMYLMS_DIR . '/assets/images/icon/clock-icon.php');. ?>
						<?php // echo esc_html( $student->get_course_time_remaining($course->get_id()) );. ?>
					</p> -->
					<?php endif; ?>
				</div>
			<?php } ?>


			<?php if ( $student->is_course_completed( $course->get_id() ) ) : ?>
				<span class="completed-date">
					<?php include OHMYLMS_DIR . '/assets/images/icon/calendar-icon.php'; ?>
					Completed <?php echo date_i18n( 'F j, Y', strtotime( $student->get_course_completed_date( $course->get_id() ) ) ); ?>
				</span>
			<?php endif; ?>

		</div>
	</div>

	<div class="ohmylms-btn-area">
		<?php if ( $student->is_course_in_progress( $course->get_id() ) || $student->maybe_enrolled( $course->get_id() ) && ! $student->is_course_completed( $course->get_id() ) ) : ?>
			<?php if ( $student->get_course_completed_points( $course->get_id() ) > 0 ) : ?>
				<a href="<?php echo esc_url( $student->get_course_resume_url( $course->get_id() ) ); ?>" class="ohmylms-button" aria-label="Resume course">
					Resume Course
				</a>
			<?php else : ?>
				<a href="<?php echo esc_url( $student->get_course_resume_url( $course->get_id() ) ); ?>" class="ohmylms-button" aria-label="Resume course">
					Start Course
				</a>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( $certificate_url ) : ?>
			<a class="ohmylms-button" href="<?php echo esc_url( $certificate_url ); ?>">
				<?php include OHMYLMS_DIR . '/assets/images/icon/download-icon.php'; ?>
				<?php esc_html_e( 'Download Certificate', 'ohmylms' ); ?>
			</a>
		<?php endif; ?>

		

		<?php do_action( 'ohmylms_course_card_after_buttons', $course, $student ); ?>
	</div>
</div>
