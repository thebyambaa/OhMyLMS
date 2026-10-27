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
	</div>
<?php endwhile; // end of the loop. ?>
