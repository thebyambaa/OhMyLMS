<?php
/**
 * Template for displaying dashboard of student profile
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/profile/tab/enrolled-course.php
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 * @global \OhMyLMS\Data\Student $student
 * @global \OhMyLMS\Data\Course $course
 */

defined( 'ABSPATH' ) || exit();

$get_courses       = $student->get_courses();
$enrolled_students = 0;
?>

<?php if ( ! empty( $get_courses ) ) { ?>
	<div class="ohmylms-dashboard-courses">
		<?php
		foreach ( $get_courses as $course ) :
			?>
			<?php if ( $student->maybe_enrolled( $course->get_id() ) ) : ?>
				<?php
				++$enrolled_students;
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

		<?php if ( ! $enrolled_students ) : ?>
			<div class="no-course-data">
				<?php include OHMYLMS_DIR . '/assets/images/icon/no-course-found-image.php'; ?>
				<p>
					<?php echo esc_html__( 'No Enrolled Courses.', 'ohmylms' ); ?>
				</p>
			</div>
		<?php endif; ?>
	</div>
<?php } else { ?>
	<div class="no-course-data">
		<?php include OHMYLMS_DIR . '/assets/images/icon/no-course-found-image.php'; ?>
		<p>
			<?php echo esc_html__( 'No Enrolled Courses.', 'ohmylms' ); ?>
		</p>
	</div>
<?php } ?>
