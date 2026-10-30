<?php
/**
 * The template for displaying lesson's quiz
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-lesson/content-quiz.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 * @global \OhMyLMS\Data\Quiz $quiz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
$course_id = ohmylms_get_course_by_content_id( get_the_ID() );
// Admins and the quiz's author are previewing: nothing they submit is saved.
$is_preview = \OhMyLMS\Quiz\Submission::is_preview( get_the_ID(), get_current_user_id() );
?>

<?php while ( have_posts() ) : ?>
	<?php the_post(); ?>
	<div class="ohmylms-lesson-content-body content-type-quiz">
		<h1><?php echo esc_html( get_the_title() ); ?></h1>

		<?php echo \OhMyLMS\Quiz\Intro::render( $quiz ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by the intro. ?>

		<ul class="ohmylms-assignment-quiz-meta">
			<li class="duratioin">
				<strong><?php echo esc_html__( 'Questions: ', 'ohmylms' ); ?></strong>
				<?php echo esc_html( $quiz->get_total_question() ); ?>
			</li>

			<?php if ( $quiz->get_timer() ) : ?>
			<li class="duratioin">
				<strong><?php echo esc_html__( 'Duration: ', 'ohmylms' ); ?></strong>
				<?php echo esc_html( $quiz->get_timer() . ' minutes' ); ?>
			</li>
			<?php endif; ?>

			<li class="total-marks">
				<strong><?php echo esc_html__( 'Total Marks: ', 'ohmylms' ); ?></strong>
				<?php echo esc_html( $quiz->get_total_marks() ); ?>
			</li>

			<li class="pass-mark">
				<strong><?php echo esc_html__( 'Passing Mark: ', 'ohmylms' ); ?></strong>
				<?php echo esc_html( $quiz->get_passing_grade() ); ?>
			</li>
		</ul>

		<div class="ohmylms-wysiwyg-content">
			<?php
				the_content();
			?>
		</div>
		
		<?php if ( $is_preview ) : ?>
			<?php $preview_result = \OhMyLMS\Quiz\Submission::take_preview_result( get_the_ID(), get_current_user_id() ); ?>
			<p class="ohmylms-quiz-notice" style="margin:16px 0;padding:12px 16px;border-radius:8px;background:#F4F5F7;color:#4B5563;font-size:14px;line-height:1.5;">
				<?php esc_html_e( 'Preview mode: you can edit this quiz, so your attempts and results are not saved. You can retake it as often as you like.', 'ohmylms' ); ?>
			</p>
			<?php if ( $preview_result ) : ?>
				<div class="ohmylms-quiz-preview-result" style="margin:0 0 24px;padding:16px;border:1px solid #E2E4EA;border-radius:8px;font-size:14px;line-height:1.6;">
					<h3 style="margin:0 0 8px;font-size:16px;"><?php esc_html_e( 'Last preview result', 'ohmylms' ); ?></h3>
					<p style="margin:4px 0;">
						<strong><?php esc_html_e( 'Score: ', 'ohmylms' ); ?></strong>
						<?php echo esc_html( \OhMyLMS\Assessment\Scoring::display( $preview_result['total'] ) . ' / ' . \OhMyLMS\Assessment\Scoring::display( $preview_result['max'] ) ); ?>
					</p>
					<p style="margin:4px 0;">
						<strong><?php esc_html_e( 'Correct answers: ', 'ohmylms' ); ?></strong>
						<?php echo esc_html( $preview_result['correct'] . ' / ' . $preview_result['questions'] ); ?>
					</p>
					<p style="margin:4px 0;">
						<strong><?php esc_html_e( 'Result: ', 'ohmylms' ); ?></strong>
						<?php
						if ( 'in-review' === $preview_result['status'] ) {
							echo '<span class="pending">' . esc_html__( 'Needs manual grading (not available in preview)', 'ohmylms' ) . '</span>';
						} elseif ( $preview_result['total'] >= $preview_result['passing'] ) {
							echo '<span class="passed">' . esc_html__( 'Pass', 'ohmylms' ) . '</span>';
						} else {
							echo '<span class="failed">' . esc_html__( 'Fail', 'ohmylms' ) . '</span>';
						}
						?>
					</p>
				</div>
			<?php endif; ?>
		<?php else : ?>
		<div class="ohmylms-table ohmylms-quiz-table">
			<div class="ohmylms-tr ohmylms-head">
				<div class="ohmylms-th date"><?php esc_html_e( 'Date', 'ohmylms' ); ?></div>
				<div class="ohmylms-th question"><?php esc_html_e( 'Question', 'ohmylms' ); ?></div>
				<div class="ohmylms-th total-marks"><?php esc_html_e( 'Total Marks', 'ohmylms' ); ?></div>
				<div class="ohmylms-th earned-marks"><?php esc_html_e( 'Earned Marks', 'ohmylms' ); ?></div>
				<div class="ohmylms-th status"><?php esc_html_e( 'Status', 'ohmylms' ); ?></div>
			</div>

			<?php
			$quiz_attempts = $quiz->get_all_quiz_attempts( get_current_user_id(), $course_id );

			if ( ! empty( $quiz_attempts ) ) {
				foreach ( $quiz_attempts as $attempt ) {
					// Judge each attempt by the marks and passing grade it was taken under.
					$basis  = \OhMyLMS\Assessment\AttemptReport::basis( $attempt['quiz_attempt_id'], $quiz );
					$earned = (float) ( $attempt['total_achieved_marks'] ?? 0 );
					// Exams may hold back marks until results are released.
					$feedback = \OhMyLMS\Assessment\Schema::ready() ? \OhMyLMS\Assessment\AssessmentSettings::feedback_visible( $attempt['quiz_attempt_id'] ) : true;
					?>
					<div class="ohmylms-tr">
						<div class="ohmylms-td-handle" role="button">
							<svg width="10" height="6" fill="none" viewBox="0 0 10 6" xmlns="http://www.w3.org/2000/svg"><path stroke="#A1A1AA" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M1 1l4 4 4-4"/></svg>
						</div>

						<div class="ohmylms-td date">
							<?php echo date( 'M j, Y g:i a', strtotime( $attempt['start_date'] ) ); ?>
						</div>

						<div class="ohmylms-td question">
							<?php echo esc_html( $attempt['total_answers_count'] ); ?>
						</div>
						<div class="ohmylms-td total-marks">
							<?php echo esc_html( \OhMyLMS\Assessment\Scoring::display( $basis['max'] ) ); ?>
						</div>

						<div class="ohmylms-td earned-marks">
							<?php echo $feedback ? esc_html( \OhMyLMS\Assessment\Scoring::display( $earned ) ) : '—'; ?>
						</div>

						<div class="ohmylms-td status">
							<?php
							if ( ! $feedback && 'in-progress' !== $attempt['status'] ) {
								echo '<span class="pending">' . esc_html__( 'Submitted — results not released yet', 'ohmylms' ) . '</span>';
							} elseif ( 'in-review' === $attempt['status'] ) {
								echo '<span class="pending">' . esc_html__( 'Awaiting review', 'ohmylms' ) . '</span>';
							} elseif ( 'in-progress' === $attempt['status'] ) {
									echo '<span class="pending">' . esc_html__( 'In progress', 'ohmylms' ) . '</span>';
							} elseif ( $earned >= $basis['max'] && $basis['max'] > 0 ) {
								echo '<span class="passed">' . esc_html__( 'Pass', 'ohmylms' ) . '</span>';
							} elseif ( $earned >= $basis['passing'] ) {
								echo '<span class="passed">' . esc_html__( 'Pass', 'ohmylms' ) . '</span>';
							} elseif ( $earned < $basis['passing'] ) {
								echo '<span class="failed">' . esc_html__( 'Fail', 'ohmylms' ) . '</span>';
							} elseif ( empty( $attempt['end_date'] ) && '0000-00-00 00:00:00' !== $attempt['end_date'] ) {
								echo '<span class="pending">' . esc_html__( 'Pending', 'ohmylms' ) . '</span>';
							}
							?>
						</div>

						<div class="ohmylms-mobile-td">
							<div class="ohmylms-td question" data-title="<?php echo esc_attr__( 'Question', 'ohmylms' ); ?>">
								<?php echo esc_html( $attempt['total_answers_count'] ); ?>
							</div>

							<div class="ohmylms-td total-marks" data-title="<?php echo esc_attr__( 'Total Marks', 'ohmylms' ); ?>">
								<?php echo esc_html( \OhMyLMS\Assessment\Scoring::display( $basis['max'] ) ); ?>
							</div>

							<div class="ohmylms-td earned-marks" data-title="<?php echo esc_attr__( 'Earned Marks', 'ohmylms' ); ?>">
								<?php echo $feedback ? esc_html( \OhMyLMS\Assessment\Scoring::display( $earned ) ) : '—'; ?>
							</div>
						</div>
					</div>

					<?php
				}
			} else {
				?>
				<div class="ohmylms-tr no-data">
					<?php
						include OHMYLMS_DIR . '/assets/images/icon/no-review-image.php';
						echo esc_html__( 'No Data Found.', 'ohmylms' );
					?>
				</div>
				<?php
			}
			?>


		</div>

			<?php
			// The learner's score and answer review, shared with the Quiz Player block and the shortcode.
			// The newest attempt is shown unless the address asks for another one (?review=ID).
			echo \OhMyLMS\Quiz\AnswerReview::render( $quiz, (array) $quiz_attempts ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by the review template.
			?>
		<?php endif; ?>
	</div>
<?php endwhile; // end of the loop. ?>
