<?php
/**
 * Template for displaying dashboard of student profile
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/profile/tab/progress-course.php
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 * @global \OhMyLMS\Data\Student $student
 * @global \OhMyLMS\Data\Course $course
 */

defined( 'ABSPATH' ) || exit();
$get_courses     = $student->get_progress_course();
$no_course_found = false;
?>

<?php if ( ! empty( $get_courses ) ) { ?>
	<div class="ohmylms-dashboard-courses">
		<?php
		foreach ( $get_courses as $course ) :

			?>
			<?php
			if ( $student->is_course_in_progress( $course->get_id() ) && $student->get_course_completed_points( $course->get_id() ) > 0 ) :
				?>
				<?php
				$no_course_found = true;
				ohmylms_get_template(
					'profile/loop/course.php',
					array(
						'student' => $student,
						'course'  => $course,
					)
				);
				?>
			<?php endif; ?>
		<?php endforeach; ?>
		<?php if ( false == $no_course_found ) : ?>
			<div class="no-course-data">
				<?php include OHMYLMS_DIR . '/assets/images/icon/no-course-found-image.php'; ?>
				<p>
					<?php echo esc_html__( 'No In-Progress Courses.', 'ohmylms' ); ?>
				</p>
			</div>
		<?php endif; ?>
	</div>
<?php } else { ?>
	<div class="no-course-data">
		<?php include OHMYLMS_DIR . '/assets/images/icon/no-course-found-image.php'; ?>
		<p>
			<?php echo esc_html__( 'No In-Progress Courses.', 'ohmylms' ); ?>
		</p>
	</div>
<?php } ?>
