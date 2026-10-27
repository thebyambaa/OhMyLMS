<?php
/**
 * The template for displaying single course sidebar's pricebox
 *
 * This template can be overridden by copying it to yourtheme/single-course/widgets/pricebox.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $course;

$current_student_id = get_current_user_id();
$student            = new \OhMyLMS\Data\Student( $current_student_id );
if ( ! $student ) {
	return;
}
$maybe_enrolled = $student->maybe_enrolled( $course->get_id() );
if ( $maybe_enrolled ) {
	return;
}
?>

<!-- course price widget -->
<div class="ohmylms-sidebar-widget with-gray-color ohmylms-widget-pricebox">
	<div class="price-discount-area">
		<?php echo wp_kses_post( $course->get_price_html() ); ?>

		<?php

		if ( $course->is_on_sale() && $course->validate_on_sale() ) {
			?>
			<span class="special-deal-tag">
				<?php echo esc_html__( 'Special Sale', 'ohmylms' ); ?>
			</span>
		<?php } ?>

	</div>

	<div class="ohmylms-btn-area">
		<?php
		if ( $maybe_enrolled ) {
			ohmylms_get_template( 'single-course/continue-course.php' );
		} elseif ( $course->get_type() === 'cohort-based' ) {
				$cohorts               = $course->get_cohort();
				$has_capacity          = false;
				$has_active_enrollment = false;
				$all_expired           = true;
				$current_time          = current_time( 'timestamp' );

			foreach ( $cohorts as $cohort ) {

				// Check capacity for this cohort.
				if ( empty( $cohort['has_capacity'] ) ||
					( ! empty( $cohort['has_capacity'] ) && $cohort['has_capacity'] &&
						! empty( $cohort['capacity'] ) &&
						(int) ( $cohort['capacity'] ) > $course->get_total_enrolled_users() ) ) {
					$has_capacity = true;
				}

				// Check enrollment deadline.
				if ( ! empty( $cohort['enrollment_deadline'] ) ) {
					$enrollment_end = strtotime( $cohort['enrollment_deadline'] );

					if ( $enrollment_end > $current_time ) {
						$has_active_enrollment = true;
					}
				} else {
					// If enrollment deadline is not set, consider it as active enrollment.
					$has_active_enrollment = true;
				}

				// Check if any cohort is not expired.
				if ( ! empty( $cohort['end_date'] ) ) {
					if ( strtotime( $cohort['end_date'] ) >= $current_time ) {
						$all_expired = false;
					}
				} else {
					// If no end date, consider not expired.
					$all_expired = false;
				}
			}

				// Determine what template to show based on conditions.
			if ( ! $has_active_enrollment || $all_expired ) {
				ohmylms_get_template( 'single-course/exceed-deadline.php', $args );
			} elseif ( ! $has_capacity ) {
				ohmylms_get_template( 'single-course/exceed-capacity.php', $args );
			} else {
				// All conditions are met, show add to cart.
				ohmylms_get_template( 'single-course/add-to-cart.php', $args );
			}
		} elseif ( ! $course->get_has_capacity() || ( $course->get_has_capacity() && $course->get_capacity() > $course->get_total_enrolled_users() ) ) {
				ohmylms_get_template( 'single-course/add-to-cart.php', $args );
		} else {
			ohmylms_get_template( 'single-course/exceed-capacity.php', $args );
		}

		?>
	</div>

	<!-- <p class="discount-countdown">
		This offer ends in
		<span class="ohmylms-course-discount-timer">
			<span class="hour">00</span>h : <span class="min">00</span>m : <span class="second">00</span>s
		</span>
	</p> -->
</div>
<!-- /.sidebar single widget -->
