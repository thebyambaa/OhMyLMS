<?php
/**
 * The template for displaying lesson's Assignment content
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-lesson/content-assignment.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 * @global \OhMyLMS\Data\Lesson $lesson
 * @global \OhMyLMS\Data\Student $student
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( 'ohmylms-lesson' === get_post_type() ) {
	$lesson = ohmylms_get_lesson( get_the_ID() );
} elseif ( get_post_type() === 'ohmylms-quiz' ) {
	$lesson = ohmylms_get_quiz( get_the_ID() );
} elseif ( 'ohmylms-session' === get_post_type() ) {
	$lesson = ohmylms_get_session( get_the_ID() );
} else {
	$lesson = ohmylms_get_assignment( get_the_ID() );
}
$lesson_id  = $lesson->get_id();
$current_id = get_the_ID();
$student    = new \OhMyLMS\Data\Student( get_current_user_id() );
$course_id  = ohmylms_get_course_by_content_id( $lesson_id );
$get_type   = 'ohmylms-session' === get_post_type() ? 'session' : $lesson->get_type();
$is_checked = $student->maybe_completed( $lesson_id ) ? 'checked' : '';
if ( 'ohmylms-session' === get_post_type() ) {
	$meeting_start_date     = get_post_meta( $lesson_id, '_start_date', true );
	$timezone               = get_post_meta( $lesson_id, '_timezone', true );
	$meeting_duration       = get_post_meta( $lesson_id, '_duration', true );
	$meeting_platform       = get_post_meta( $lesson_id, '_platform', true );
	$show_button_on_session = false;
	error_log(
		print_r(
			array(
				'meeting_start_date' => $meeting_start_date,
				'timezone'           => $timezone,
				'meeting_duration'   => $meeting_duration,
				'meeting_platform'   => $meeting_platform,
			),
			true
		)
	);

	if ( 'googlemeet' === $meeting_platform ) {
		if ( $meeting_start_date && $timezone ) {
			$event_data = get_post_meta( $lesson_id, '_googlemeet_event_data', true );
			$start_str  = $event_data['start']['dateTime'] ?? '';
			$end_str    = $event_data['end']['dateTime'] ?? '';

			if ( $end_str ) {
				try {
					// Parse the end datetime from Google Meet event (ISO 8601 format).
					$end_dt     = new DateTime( $end_str );
					$current_dt = new DateTime( 'now', new DateTimeZone( $timezone ) );

					// Show "Mark as Complete" button if current time is past the end time.
					if ( $current_dt >= $end_dt ) {
						$show_button_on_session = true;
					}
				} catch ( Exception $e ) {
					error_log( 'Error parsing Google Meet end date: ' . $e->getMessage() );
				}
			}
		}
	} elseif ( $meeting_start_date && $meeting_duration && $timezone ) {
			$start_dt = new DateTime( $meeting_start_date, new DateTimeZone( $timezone ) );
			$end_dt   = clone $start_dt;
			$end_dt->modify( "+$meeting_duration minutes" );
			$current_dt = new DateTime( 'now', new DateTimeZone( $timezone ) );
		if ( $current_dt >= $end_dt ) {
			$show_button_on_session = true;
		}
	}
}
?>

<div class="ohmylms-lesson-navigation">
	<?php
	if ( 'assignment' == $get_type ) {
		$submission_count   = count( $lesson->get_submission( $student->get_id() ) );
		$allowed_submission = $lesson->get_number_of_files();
		$is_allow           = $lesson->get_allow_upload_files();
		$student            = new \OhMyLMS\Data\Student( get_current_user_id() );
		$deadline           = false;
		if ( $student ) {
			$deadline = $student->get_assignment_remaining_time( $lesson->get_id() );
		}
		?>
		<div class="assignment-quiz-navigation assignment-navigation">
			<?php if ( $is_allow && ! empty( ohmylms_get_next_content_permalink( $lesson_id ) ) ) { ?>
				<a href="<?php echo esc_url( ohmylms_get_next_content_permalink( $lesson_id ) ); ?>" class="skip">
					<?php echo esc_html__( 'Skip to Next', 'ohmylms' ); ?>
				</a>
			<?php } ?>

			<?php if ( $is_allow ) : ?>
				<?php if ( 0 === (int) $submission_count ) : ?>
					<?php if ( 0 !== $deadline ) : ?>
						<button type="button" class="start-submit-assignment ohmylms-button" aria-label="Start Assignment">
							<?php echo esc_html__( 'Start Assignment Submit', 'ohmylms' ); ?>
						</button>
					<?php else : ?>
						<button type="button" class="start-not-submit-assignment ohmylms-button" aria-label="Start Assignment" disabled>
							<?php echo esc_html__( 'Start Assignment Submit', 'ohmylms' ); ?>
						</button>
					<?php endif; ?>
				<?php elseif ( (int) $submission_count > 0 && (int) $submission_count < (int) $allowed_submission ) : ?>

					<?php if ( 0 !== $deadline ) : ?>
						<button type="button" class="start-submit-assignment ohmylms-button" aria-label="Start Assignment">
							<?php echo esc_html__( 'Try Again', 'ohmylms' ); ?>
						</button>
					<?php else : ?>
						<button type="button" class="start-not-submit-assignment ohmylms-button" aria-label="Start Assignment" disabled>
							<?php echo esc_html__( 'Try Again', 'ohmylms' ); ?>
						</button>
					<?php endif; ?>


					<div class="default-navigation">
						<input type="hidden" id="ohmylms-lesson-id" value="<?php echo esc_attr( get_the_ID() ); ?>">
						<input type="hidden" id="ohmylms-student-id" value="<?php echo esc_attr( get_current_user_id() ); ?>">

						<?php if ( $is_checked && $student->maybe_enrolled( $course_id ) ) : ?>
							<label for="ohmylms-completed-lesson" class="ohmylms-checkbox" tabindex="0">
								<input type="checkbox" name="lesson-completed" value="" id="ohmylms-completed-lesson" <?php echo $is_checked; ?>  <?php echo $is_checked ? 'disabled' : ''; ?> aria-required="true" aria-labelledby="completed-lesson" >
								<span class="ohmylms-checkbox-text">
									<span class="checkedbox" aria-hidden="true" id="completed-lesson">
										<svg width="10" height="8" fill="none" viewBox="0 0 10 8" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" d="M8.373.818L3.745 5.446 1.623 3.325A.818.818 0 00.466 4.482l2.7 2.7a.818.818 0 001.157 0L9.53 1.975A.818.818 0 008.373.818z"/></svg>
									</span>
									<?php echo $is_checked ? esc_html__( 'Completed', 'ohmylms' ) : esc_html__( 'Mark as Complete', 'ohmylms' ); ?>
								</span>
							</label>
						<?php endif; ?>

						<?php if ( ! empty( ohmylms_get_next_content_permalink( $lesson_id ) ) ) { ?>
							<a href="<?php echo esc_url( ohmylms_get_next_content_permalink( $lesson_id ) ); ?>" class="next-lesson ohmylms-button" lesson-id="<?php echo esc_attr( $lesson_id ); ?>">
								<?php echo esc_html__( 'Next Lesson', 'ohmylms' ); ?>
							</a>
						<?php } ?>
					</div>

				<?php else : ?>
					<div class="default-navigation">
						<input type="hidden" id="ohmylms-lesson-id" value="<?php echo esc_attr( get_the_ID() ); ?>">
						<input type="hidden" id="ohmylms-student-id" value="<?php echo esc_attr( get_current_user_id() ); ?>">
						<?php if ( $is_checked && $student->maybe_enrolled( $course_id ) ) : ?>
							<label class="ohmylms-checkbox">
								<input type="checkbox" name="lesson-completed" value="" id="ohmylms-completed-lesson" <?php echo $is_checked; ?>  <?php echo $is_checked ? 'disabled' : ''; ?> >
								<span class="ohmylms-checkbox-text">
									<span class="checkedbox">
										<svg width="10" height="8" fill="none" viewBox="0 0 10 8" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" d="M8.373.818L3.745 5.446 1.623 3.325A.818.818 0 00.466 4.482l2.7 2.7a.818.818 0 001.157 0L9.53 1.975A.818.818 0 008.373.818z"/></svg>
									</span>
									<?php echo $is_checked ? esc_html__( 'Completed', 'ohmylms' ) : esc_html__( 'Mark as Complete', 'ohmylms' ); ?>
								</span>
							</label>
						<?php endif; ?>

						<?php if ( ! empty( ohmylms_get_next_content_permalink( $lesson_id ) ) ) { ?>
							<a href="<?php echo esc_url( ohmylms_get_next_content_permalink( $lesson_id ) ); ?>" class="next-lesson ohmylms-button" lesson-id="<?php echo esc_attr( $lesson_id ); ?>">
								<?php echo esc_html__( 'Next Lesson', 'ohmylms' ); ?>
							</a>
						<?php } ?>
					</div>
				<?php endif; ?>
			<?php else : ?>
				<div class="default-navigation">
					<input type="hidden" id="ohmylms-lesson-id" value="<?php echo esc_attr( get_the_ID() ); ?>">
					<input type="hidden" id="ohmylms-student-id" value="<?php echo esc_attr( get_current_user_id() ); ?>">
					<?php if ( ! empty( ohmylms_get_next_content_permalink( $lesson_id ) ) ) { ?>
						<a href="<?php echo esc_url( ohmylms_get_next_content_permalink( $lesson_id ) ); ?>" class="next-lesson ohmylms-button" lesson-id="<?php echo esc_attr( $lesson_id ); ?>">
							<?php echo esc_html__( 'Next Lesson', 'ohmylms' ); ?>
						</a>
					<?php } ?>
				</div>
			<?php endif; ?>
		</div>
	<?php } ?>

	<?php
	if ( 'quiz' == $get_type ) {
		$currentUrl = ohmylms_get_pretty_content_permalink( $lesson_id );
		// Admins and authors previewing a quiz can retry without limit and never see it as completed.
		$is_quiz_preview = \OhMyLMS\Quiz\Submission::is_preview( $lesson_id, get_current_user_id() );
		if ( $is_quiz_preview ) {
			$is_checked = '';
		}
		?>
		<div class="assignment-quiz-navigation quiz-navigation">
			<?php if ( ! empty( ohmylms_get_next_content_permalink( $lesson_id ) ) ) { ?>
				<a href="<?php echo esc_url( ohmylms_get_next_content_permalink( $lesson_id ) ); ?>" class="skip">
					<?php echo esc_html__( 'Skip to Next', 'ohmylms' ); ?>
				</a>
			<?php } ?>

			<form action="" method="post">
				<input type="hidden" name="action" value="quiz-action">
				<input type="hidden" name="ohmylms_quiz_id" value="<?php echo esc_attr( get_the_ID() ); ?>">
				<input type="hidden" name="ohmylms_student_id" value="<?php echo esc_attr( get_current_user_id() ); ?>">
				<?php wp_nonce_field( 'save_quiz_attempt', 'save-quiz-attempt-nonce' ); ?>
				

				<?php if ( $is_quiz_preview || ( $lesson->get_take_attempts() > 0 && $lesson->get_take_attempts() > $lesson->count_total_attempt( $student->get_id(), $course_id ) ) ) { ?>
					<button type="submit" class="start-submit-quiz ohmylms-button <?php echo esc_attr( $lesson->count_total_attempt( $student->get_id(), $course_id ) > 1 ? 'quiz-taken' : '' ); ?>">
						<?php echo esc_html__( 'Start Quiz', 'ohmylms' ); ?>
					</button>
				<?php } else { ?>
					<button type="button" class="start-submit-quiz ohmylms-button" disabled >
						<?php echo esc_html__( 'Start Quiz', 'ohmylms' ); ?>
					</button>
				<?php } ?>
			</form>

			<div class="default-navigation" style="display: <?php echo esc_attr( ( 'quiz' === $get_type && $is_checked ) || ( 'quiz' !== $get_type ) ? 'flex' : 'none' ); ?>" >
				<input type="hidden" id="ohmylms-lesson-id" value="<?php echo esc_attr( get_the_ID() ); ?>">
				<input type="hidden" id="ohmylms-student-id" value="<?php echo esc_attr( get_current_user_id() ); ?>">
				
				<?php if ( $student->maybe_enrolled( $course_id ) ) : ?>
					<label for="ohmylms-completed-lesson" class="ohmylms-checkbox" tabindex="0">
						<input type="checkbox" name="lesson-completed" value="" id="ohmylms-completed-lesson" <?php echo $is_checked; ?>  <?php echo $is_checked ? 'disabled' : ''; ?> aria-required="true" aria-labelledby="completed-lesson" >
						<span class="ohmylms-checkbox-text">
							<span class="checkedbox" aria-hidden="true" id="completed-lesson">
								<svg width="10" height="8" fill="none" viewBox="0 0 10 8" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" d="M8.373.818L3.745 5.446 1.623 3.325A.818.818 0 00.466 4.482l2.7 2.7a.818.818 0 001.157 0L9.53 1.975A.818.818 0 008.373.818z"/></svg>
							</span>
							<?php echo $is_checked ? esc_html__( 'Completed', 'ohmylms' ) : esc_html__( 'Mark as Complete', 'ohmylms' ); ?>
						</span>
					</label>
				<?php endif; ?>

				<?php if ( ! empty( ohmylms_get_next_content_permalink( $lesson_id ) ) ) { ?>
					<a href="<?php echo esc_url( ohmylms_get_next_content_permalink( $lesson_id ) ); ?>" class="next-lesson ohmylms-button" lesson-id="<?php echo esc_attr( $lesson_id ); ?>">
						<?php echo esc_html__( 'Next Lesson', 'ohmylms' ); ?>
					</a>
				<?php } ?>
			</div>
		</div>

		<!-- quiz attempts notice -->
		<?php
		if ( $is_quiz_preview || ( $lesson->get_take_attempts() > 0 && $lesson->get_take_attempts() > $lesson->count_total_attempt( $student->get_id(), $course_id ) ) ) {

		} else {
			?>
				<p class="ohmylms-quiz-notice">
					<svg width="16" height="16" fill="none" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg"><g fill="#A1A1AA" clip-path="url(#clip0_3836_17160)"><path d="M8 16a8 8 0 118-8 8.009 8.009 0 01-8 8zM8 1.333A6.667 6.667 0 1014.667 8 6.674 6.674 0 008 1.333z"/><path d="M8 12.667A.667.667 0 017.333 12V6.667a.667.667 0 011.334 0V12a.667.667 0 01-.667.667zM8.667 4a.667.667 0 11-1.334 0 .667.667 0 011.334 0z"/></g><defs><clipPath id="clip0_3836_17160"><path fill="#fff" d="M0 0h16v16H0z" transform="matrix(1 0 0 -1 0 16)"/></clipPath></defs></svg>
					<?php
					$attempts     = $lesson->get_take_attempts();
					$attempt_text = $attempts > 1 ? __( 'attempts', 'ohmylms' ) : __( 'attempt', 'ohmylms' );
					printf( /* translators: %1$d: number; %2$s: text value. */
						__( 'You have already taken %1$d %2$s.', 'ohmylms' ),
						$attempts,
						$attempt_text
					);
					?>
				</p>
			<?php
		}
		?>
	<?php } ?>

	<?php if ( 'quiz' != $get_type && 'assignment' != $get_type ) { ?>
		<div class="default-navigation">
			<input type="hidden" id="ohmylms-lesson-id" value="<?php echo esc_attr( get_the_ID() ); ?>">
			<input type="hidden" id="ohmylms-student-id" value="<?php echo esc_attr( get_current_user_id() ); ?>">
			
			<?php if ( $student->maybe_enrolled( $course_id ) ) : ?> 
				<?php if ( 'session' === $get_type ) : ?>
					<?php if ( $show_button_on_session ) : ?>
						<label for="ohmylms-completed-lesson" class="ohmylms-checkbox" tabindex="0">
							<input type="checkbox" name="lesson-completed" value="" id="ohmylms-completed-lesson" <?php echo $is_checked; ?>  <?php echo $is_checked ? 'disabled' : ''; ?> aria-required="true" aria-labelledby="completed-lesson" >

							<span class="ohmylms-checkbox-text">
								<span class="checkedbox" aria-hidden="true" id="completed-lesson">
									<svg width="10" height="8" fill="none" viewBox="0 0 10 8" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" d="M8.373.818L3.745 5.446 1.623 3.325A.818.818 0 00.466 4.482l2.7 2.7a.818.818 0 001.157 0L9.53 1.975A.818.818 0 008.373.818z"/></svg>
								</span>
								<?php echo $is_checked ? esc_html__( 'Completed', 'ohmylms' ) : esc_html__( 'Mark as Complete', 'ohmylms' ); ?>
							</span>
						</label>
					<?php endif ?>
				<?php else : ?>
					<label for="ohmylms-completed-lesson" class="ohmylms-checkbox" tabindex="0">
						<input type="checkbox" name="lesson-completed" value="" id="ohmylms-completed-lesson" <?php echo $is_checked; ?>  <?php echo $is_checked ? 'disabled' : ''; ?> aria-required="true" aria-labelledby="completed-lesson" >

						<span class="ohmylms-checkbox-text">
							<span class="checkedbox" aria-hidden="true" id="completed-lesson">
								<svg width="10" height="8" fill="none" viewBox="0 0 10 8" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" d="M8.373.818L3.745 5.446 1.623 3.325A.818.818 0 00.466 4.482l2.7 2.7a.818.818 0 001.157 0L9.53 1.975A.818.818 0 008.373.818z"/></svg>
							</span>
							<?php echo $is_checked ? esc_html__( 'Completed', 'ohmylms' ) : esc_html__( 'Mark as Complete', 'ohmylms' ); ?>
						</span>
					</label>
				<?php endif ?>
			<?php endif ?>

			<?php if ( ! empty( ohmylms_get_next_content_permalink( $lesson_id ) ) ) { ?>
				<a href="<?php echo esc_url( ohmylms_get_next_content_permalink( $lesson_id ) ); ?>" class="next-lesson ohmylms-button" lesson-id="<?php echo esc_attr( $lesson_id ); ?>">
					<?php echo esc_html__( 'Next Lesson', 'ohmylms' ); ?>
				</a>
			<?php } ?>
		</div>
	<?php } ?>
</div>
