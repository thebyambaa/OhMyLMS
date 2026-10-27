<?php
/**
 * Template for displaying my courses of student profile
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/profile/my-courses.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

defined( 'ABSPATH' ) || exit();
$my_course_tab = apply_filters( 'ohmylms_my_course_tabs', array() );
?>
<div class="ohmylms-dashboard-wrapper">
	<h1 class="student-name"><?php echo esc_html__( 'My Courses', 'ohmylms' ); ?></h1>
	<div class="ohmylms-my-courses-tab-nav">
		<div class="ohmylms-my-courses-tab">
			<ul role="tablist" id="my-courses-tab">
				<?php foreach ( $my_course_tab as $key => $course_tab ) : ?>
					<li class="<?php echo esc_attr( 'enrolled-courses' === $key ? 'active' : '' ); ?>" role="presentation">
						<button type="button" role="tab" aria-controls="course-<?php echo esc_attr( $key ); ?>" aria-selected="<?php echo esc_attr( 'enrolled-courses' === $key ? 'true' : 'false' ); ?>" tabindex="<?php echo esc_attr( 'enrolled-courses' === $key ? '0' : '-1' ); ?>" data-target="#course-<?php echo esc_attr( $key ); ?>">
							<?php echo wp_kses_post( $course_tab['title'] ); ?>
						</button>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<div class="ohmylms-my-courses-tab-content" id="my-courses-tab-content">
			<?php
			foreach ( $my_course_tab as $key => $course_tab ) :
				$courseClass = 'ohmylms-single-tab-content course-' . esc_attr( $key );

				if ( 'enrolled-courses' === $key ) {
					$courseClass .= ' active';
				}
				$courseId = 'course-' . esc_attr( $key );

				?>

				<div class="<?php echo esc_attr( $courseClass ); ?>" id="<?php echo esc_attr( $courseId ); ?>" aria-labelledby="<?php echo esc_attr( $courseId ); ?>" role="tabpanel" tabindex="0">
					<?php
					if ( isset( $course_tab['callback'] ) ) {
						call_user_func( $course_tab['callback'], $key, $course_tab );
					}
					?>
				</div>
			<?php endforeach; ?>
		</div>

	</div>

</div>
