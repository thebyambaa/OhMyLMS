<?php
/**
 * OhMyLMS Loop Course Meta
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/loop/course-cohort.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $course;
if ( ! $course ) {
	return;
}

if ( 'cohort-based' !== $course->get_type() ) {
	return;
}
?>

<?php
	// --- Cohort-based meta info ---
if ( method_exists( $course, 'get_type' ) && 'cohort-based' === $course->get_type() ) {

	$cohorts = $course->get_cohort();

	if ( ! empty( $cohorts ) ) {
		// Find the next or current cohort.
		$now             = current_time( 'timestamp' );
		$selected_cohort = null;
		foreach ( $cohorts as $cohort ) {
			$start = strtotime( $cohort['start_date'] );
			$end   = ! empty( $cohort['end_date'] ) ? strtotime( $cohort['end_date'] ) : false;
			if ( $start > $now ) {
				$selected_cohort = $cohort;
				break;
			} elseif ( $end && $start <= $now && $end >= $now ) {
				$selected_cohort = $cohort;
				break;
			}
		}
		if ( ! $selected_cohort ) {
			// fallback: show the last cohort.
			$selected_cohort = end( $cohorts );
		}

		if ( $selected_cohort ) {
			$start_date      = $selected_cohort['start_date'];
			$end_date        = $selected_cohort['end_date'];
			$enroll_deadline = $selected_cohort['enrollment_deadline'];
			$has_capacity    = $selected_cohort['has_capacity'];
			$capacity        = $selected_cohort['capacity'];
			// Format dates.
			$start_fmt          = $start_date ? date_i18n( get_option( 'date_format' ), strtotime( $start_date ) ) : '';
			$end_fmt            = $end_date ? date_i18n( get_option( 'date_format' ), strtotime( $end_date ) ) : '';
			$enroll_deadline_ts = ! empty( $enroll_deadline ) ? strtotime( $enroll_deadline ) : false;
			$now                = current_time( 'timestamp' );
			?>
				<ul class="cohort-meta">
					<li class="cohort-start-date">
					<?php
					if ( $start_date && strtotime( $start_date ) > $now ) {
						echo '<span style="display: inline-flex; align-items: center; gap: 4px; line-height: 1.6;">';
						include OHMYLMS_DIR . '/assets/images/icon/calendar-icon.php';
						echo esc_html__( 'Starts', 'ohmylms' ) . ' ' . esc_html( $start_fmt );
						echo '</span>';
						if ( $end_fmt ) {
							echo '<br><span style="display: inline-flex; align-items: center; gap: 4px;line-height: 1.6;">';
							include OHMYLMS_DIR . '/assets/images/icon/calendar-icon.php';
							echo esc_html__( 'Ends', 'ohmylms' ) . ' ' . esc_html( $end_fmt );
							echo '</span>';
						}
					} elseif ( $end_date && strtotime( $start_date ) <= $now && strtotime( $end_date ) >= $now ) {
						echo '<span style="display: inline-flex; align-items: center; gap: 4px;">';
						include OHMYLMS_DIR . '/assets/images/icon/tick-icon.php';
						echo esc_html__( 'In Progress', 'ohmylms' ) . ( $end_fmt ? ' – ' . esc_html( $end_fmt ) : '' );
						echo '</span>';
					} elseif ( $end_date && strtotime( $end_date ) < $now ) {
						echo '<span style="display: inline-flex; align-items: center; gap: 4px;">';
						include OHMYLMS_DIR . '/assets/images/icon/calendar-icon.php';
						echo esc_html__( 'Course Expired', 'ohmylms' ) . ' ' . esc_html( $end_fmt );
						echo '</span>';
					}
					?>
					</li>

					<li class="cohort-enrollment-status">
					<?php
					if ( $enroll_deadline_ts && $enroll_deadline_ts > $now ) {
						$time_left  = $enroll_deadline_ts - $now;
						$days_left  = floor( $time_left / DAY_IN_SECONDS );
						$hours_left = floor( ( $time_left % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );

						echo '<span style="display: inline-flex; align-items: center; gap: 4px;">';
						include OHMYLMS_DIR . '/assets/images/icon/enrollment-icon.php';

						if ( $days_left > 0 ) {
							if ( $hours_left > 0 ) {
								printf( /* translators: %1$d: number; %2$d: number. */
								esc_html__( 'Enrollment Open – %1$d days %2$d hours left', 'ohmylms' ), $days_left, $hours_left );
							} else {
								printf( /* translators: %d: number. */
								esc_html__( 'Enrollment Open – %d days left', 'ohmylms' ), $days_left );
							}
						} elseif ( $hours_left > 0 ) {
								printf( /* translators: %d: number. */
								esc_html__( 'Enrollment Open – %d hours left', 'ohmylms' ), $hours_left );
						} else {
							// Less than an hour left.
							$minutes_left = ceil( $time_left / MINUTE_IN_SECONDS );
							printf( /* translators: %d: number. */
							esc_html__( 'Enrollment Open – %d minutes left', 'ohmylms' ), $minutes_left );
						}
						echo '</span>';
					} elseif ( $enroll_deadline_ts && $enroll_deadline_ts <= $now ) {
						echo '<span style="display: inline-flex; align-items: center; gap: 4px;">';
						include OHMYLMS_DIR . '/assets/images/icon/enrollment-icon.php';
						echo esc_html__( 'Enrollment Closed', 'ohmylms' );
						echo '</span>';
					}
					?>
					</li>

					<?php
					if ( $has_capacity && $capacity ) {
						$enrolled   = $course->get_total_enrolled_users();
						$seats_left = $capacity - $enrolled;
						?>
					<li class="cohort-seats-left">
						<span class="seats-badge">
							<span style="display: inline-flex; align-items: center; gap: 4px;">
								<?php include OHMYLMS_DIR . '/assets/images/icon/user-icon-white.php'; ?>
								<span>
									<?php
									$seats_left > 0 ? printf( /* translators: %1$d: number; %2$d: number. */
									esc_html__( '%1$d/%2$d seats left', 'ohmylms' ), $seats_left, $capacity ) : printf( esc_html__( 'No seats left', 'ohmylms' ) );
									?>
								</span>
							</span>
						</span>
					</li>
					<?php } ?>
				</ul>
				<?php
		}
	}
}
?>
