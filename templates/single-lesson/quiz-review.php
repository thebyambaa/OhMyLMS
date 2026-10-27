<?php
/**
 * The learner's score and answers next to the correct answers, shown on the quiz result screen.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-lesson/quiz-review.php.
 *
 * @package OhMyLMS\Templates
 * @version 1.1.0
 * @global array[]      $entries  Review entries from \OhMyLMS\Quiz\AnswerReview::for_attempt().
 * @global array|null   $summary  Score and verdict counts from \OhMyLMS\Quiz\AnswerReview::summary().
 * @global array[]|null $attempts Attempts the learner may review, newest first.
 * @global array|null   $attempt  The attempt being reviewed.
 * @global array[]|null $retry    Missed questions that can be practised, from \OhMyLMS\Quiz\AnswerReview::retry_items().
 * @global bool|null    $retrying Whether the practice list is open.
 * @global string|null  $anchor   Unique element ID of this review, so several quizzes can share a page.
 * @global string|null  $intro    Sentence shown under the heading instead of the default attempt line.
 */

use OhMyLMS\Assessment\Scoring;
use OhMyLMS\Quiz\AnswerReview;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( empty( $entries ) ) {
	return;
}

$summary  = isset( $summary ) && is_array( $summary ) ? $summary : null;
$attempts = isset( $attempts ) && is_array( $attempts ) ? $attempts : array();
$attempt  = isset( $attempt ) && is_array( $attempt ) ? $attempt : null;
$retry    = isset( $retry ) && is_array( $retry ) ? $retry : array();
$retrying = ! empty( $retrying );
$intro    = isset( $intro ) && is_string( $intro ) ? $intro : '';
$anchor   = isset( $anchor ) && is_string( $anchor ) && '' !== $anchor ? sanitize_html_class( $anchor ) : 'ohmylms-answer-review';

wp_enqueue_style( 'ohmylms-answer-review', plugins_url( 'assets/css/answer-review.css', OHMYLMS_FILE ), array(), OHMYLMS_VERSION );

$verdict_labels  = array(
	'correct'    => __( 'Correct', 'ohmylms' ),
	'partial'    => __( 'Partly correct', 'ohmylms' ),
	'incorrect'  => __( 'Incorrect', 'ohmylms' ),
	'pending'    => __( 'Waiting for your teacher to mark this', 'ohmylms' ),
	'unanswered' => __( 'Not answered', 'ohmylms' ),
	'ungraded'   => __( 'Not marked', 'ohmylms' ),
);
$headlines       = array(
	'pass'   => __( 'You passed', 'ohmylms' ),
	'fail'   => __( 'Not passed yet', 'ohmylms' ),
	'review' => __( 'Awaiting review', 'ohmylms' ),
);
$attempt_results = array(
	'pass'   => __( 'Passed', 'ohmylms' ),
	'fail'   => __( 'Not passed', 'ohmylms' ),
	'review' => __( 'Awaiting review', 'ohmylms' ),
);
$latest_id       = $attempts ? (int) $attempts[0]['quiz_attempt_id'] : 0;
$stamp           = static function ( $row ) {
	$time = ! empty( $row['start_date'] ) ? strtotime( $row['start_date'] ) : 0;
	return $time ? date( 'M j, Y g:i a', $time ) : '';
};
?>
<section class="ohmylms-answer-review" id="<?php echo esc_attr( $anchor ); ?>" aria-labelledby="<?php echo esc_attr( $anchor ); ?>-title">
	<?php if ( $summary ) : ?>
		<div class="ohmylms-review-summary is-<?php echo esc_attr( $summary['result'] ); ?>">
			<h2 class="ohmylms-review-headline"><?php echo esc_html( $headlines[ $summary['result'] ] ?? __( 'Your result', 'ohmylms' ) ); ?></h2>
			<p class="ohmylms-review-score">
				<strong>
					<?php
					/* translators: 1: marks earned, 2: marks available */
					echo esc_html( sprintf( __( '%1$s of %2$s marks', 'ohmylms' ), Scoring::display( $summary['earned'] ), Scoring::display( $summary['max'] ) ) );
					?>
				</strong>
				<?php if ( null !== $summary['percent'] ) : ?>
					<span class="ohmylms-review-percent">(<?php echo esc_html( Scoring::display( $summary['percent'] ) ); ?>%)</span>
				<?php endif; ?>
			</p>
			<?php if ( null !== $summary['percent'] ) : ?>
				<meter class="ohmylms-review-meter" min="0" max="100" value="<?php echo esc_attr( $summary['percent'] ); ?>" aria-label="<?php esc_attr_e( 'Score', 'ohmylms' ); ?>"><?php echo esc_html( $summary['percent'] ); ?>%</meter>
			<?php endif; ?>
			<?php if ( $summary['passing'] > 0 ) : ?>
				<p class="ohmylms-review-passing">
					<?php
					/* translators: %s: passing mark */
					echo esc_html( sprintf( __( 'Passing mark: %s', 'ohmylms' ), Scoring::display( $summary['passing'] ) ) );
					?>
				</p>
			<?php endif; ?>

			<ul class="ohmylms-review-counts">
				<?php
				$count_labels = array(
					/* translators: %d: number of questions */
					'correct'    => _n_noop( '%d correct', '%d correct', 'ohmylms' ),
					/* translators: %d: number of questions */
					'partial'    => _n_noop( '%d partly correct', '%d partly correct', 'ohmylms' ),
					/* translators: %d: number of questions */
					'incorrect'  => _n_noop( '%d incorrect', '%d incorrect', 'ohmylms' ),
					/* translators: %d: number of questions */
					'pending'    => _n_noop( '%d awaiting review', '%d awaiting review', 'ohmylms' ),
					/* translators: %d: number of questions */
					'unanswered' => _n_noop( '%d not answered', '%d not answered', 'ohmylms' ),
				);
				foreach ( $count_labels as $key => $noop ) :
					if ( empty( $summary['counts'][ $key ] ) ) {
						continue;
					}
					?>
					<li class="is-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( sprintf( translate_nooped_plural( $noop, $summary['counts'][ $key ], 'ohmylms' ), $summary['counts'][ $key ] ) ); ?></li>
				<?php endforeach; ?>
			</ul>

			<?php if ( ! empty( $levels ) ) : ?>
				<div class="ohmylms-review-levels">
					<h3><?php esc_html_e( 'Skill levels', 'ohmylms' ); ?></h3>
					<ul>
						<?php
						foreach ( $levels as $level ) :
							$level_before = \OhMyLMS\Performance\Levels::label( $level['before'] );
							$level_after  = \OhMyLMS\Performance\Levels::label( $level['after'] );
							?>
							<li class="is-<?php echo esc_attr( $level['change'] ); ?>">
								<strong><?php echo esc_html( $level['skill'] ); ?>:</strong>
								<?php
								if ( 'up' === $level['change'] ) {
									/* translators: 1: level before, 2: level after */
									echo esc_html( sprintf( __( 'Leveled up: %1$s → %2$s', 'ohmylms' ), $level_before, $level_after ) );
								} elseif ( 'down' === $level['change'] ) {
									/* translators: 1: level before, 2: level after */
									echo esc_html( sprintf( __( 'Leveled down: %1$s → %2$s', 'ohmylms' ), $level_before, $level_after ) );
								} else {
									/* translators: %s: level */
									echo esc_html( sprintf( __( 'Stayed at %s', 'ohmylms' ), $level_after ) );
								}
								?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( $retry && ! $retrying && $attempt ) : ?>
				<p class="ohmylms-review-actions">
					<a class="ohmylms-button ohmylms-review-retry-button" href="<?php echo esc_url( AnswerReview::url( $attempt['quiz_attempt_id'], array( 'retry' => 1 ) ) . '#' . $anchor . '-retry' ); ?>">
						<?php esc_html_e( 'Retry the questions I missed', 'ohmylms' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( count( $attempts ) > 1 && $attempt ) : ?>
		<nav class="ohmylms-review-attempts" aria-label="<?php esc_attr_e( 'Choose an attempt to review', 'ohmylms' ); ?>">
			<h3><?php esc_html_e( 'Your attempts', 'ohmylms' ); ?></h3>
			<ul>
				<?php foreach ( $attempts as $index => $row ) : ?>
					<?php $current = (int) $row['quiz_attempt_id'] === (int) $attempt['quiz_attempt_id']; ?>
					<li<?php echo $current ? ' class="is-current"' : ''; ?>>
						<a href="<?php echo esc_url( AnswerReview::url( $row['quiz_attempt_id'] ) . '#' . $anchor ); ?>"<?php echo $current ? ' aria-current="true"' : ''; ?>>
							<?php
							/* translators: 1: attempt number, 2: date and time */
							echo esc_html( sprintf( __( 'Attempt %1$d, %2$s', 'ohmylms' ), count( $attempts ) - $index, $stamp( $row ) ) );
							if ( ! empty( $row['review_result'] ) && isset( $attempt_results[ $row['review_result'] ] ) ) {
								echo esc_html( ' · ' . $attempt_results[ $row['review_result'] ] );
							}
							?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
	<?php endif; ?>

	<h2 class="ohmylms-answer-review-heading" id="<?php echo esc_attr( $anchor ); ?>-title"><?php esc_html_e( 'Review your answers', 'ohmylms' ); ?></h2>
	<p class="ohmylms-answer-review-intro">
		<?php
		if ( '' !== $intro ) {
			echo esc_html( $intro );
		} elseif ( $attempt && $latest_id && (int) $attempt['quiz_attempt_id'] !== $latest_id ) {
			/* translators: %s: date and time the attempt was started */
			echo esc_html( sprintf( __( 'This is the attempt you started on %s.', 'ohmylms' ), $stamp( $attempt ) ) );
		} else {
			esc_html_e( 'This is your most recent attempt.', 'ohmylms' );
		}
		?>
	</p>

	<ol class="ohmylms-answer-review-list">
		<?php foreach ( $entries as $entry ) : ?>
			<li class="ohmylms-answer-review-item is-<?php echo esc_attr( $entry['verdict'] ); ?>">
				<div class="ohmylms-answer-review-question"><?php echo wp_kses_post( $entry['name'] ); ?></div>

				<p class="ohmylms-answer-review-verdict">
					<strong><?php echo esc_html( $verdict_labels[ $entry['verdict'] ] ?? '' ); ?></strong>
					<?php if ( 'pending' !== $entry['verdict'] && $entry['max'] > 0 ) : ?>
						<span class="ohmylms-answer-review-marks">
							<?php
							/* translators: 1: marks earned, 2: marks available */
							echo esc_html( sprintf( __( '%1$s / %2$s marks', 'ohmylms' ), Scoring::display( $entry['awarded'] ), Scoring::display( $entry['max'] ) ) );
							?>
						</span>
					<?php endif; ?>
				</p>

				<?php if ( ! empty( $entry['parts'] ) ) : ?>
					<ol class="ohmylms-answer-review-parts">
						<?php foreach ( $entry['parts'] as $part ) : ?>
							<li class="ohmylms-answer-review-part is-<?php echo esc_attr( $part['verdict'] ); ?>">
								<p class="ohmylms-answer-review-part-title">
									<?php // "(c)" would be turned into a copyright sign by WordPress' text styling, so the opening bracket is written as an entity. ?>
									<strong><?php echo str_replace( '(', '&#40;', esc_html( $part['label'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above; only the bracket is replaced. ?></strong>
									<?php echo wp_kses_post( $part['prompt'] ); ?>
								</p>
								<p class="ohmylms-answer-review-verdict">
									<strong><?php echo esc_html( $verdict_labels[ $part['verdict'] ] ?? '' ); ?></strong>
									<?php if ( 'pending' !== $part['verdict'] && $part['max'] > 0 ) : ?>
										<span class="ohmylms-answer-review-marks">
											<?php
											/* translators: 1: marks earned, 2: marks available */
											echo esc_html( sprintf( __( '%1$s / %2$s marks', 'ohmylms' ), Scoring::display( $part['awarded'] ), Scoring::display( $part['max'] ) ) );
											?>
										</span>
									<?php endif; ?>
								</p>
								<?php if ( '' !== $part['given'] && ! empty( $part['media'] ) && preg_match( '#^https?://[^\s<>"]+$#i', $part['given'] ) ) : ?>
									<p class="ohmylms-answer-review-part-answer"><span><?php esc_html_e( 'Your answer', 'ohmylms' ); ?>:</span></p>
									<?php if ( 'video' === $part['media'] ) : ?>
										<video controls preload="none" src="<?php echo esc_url( $part['given'] ); ?>"></video>
									<?php else : ?>
										<audio controls preload="none" src="<?php echo esc_url( $part['given'] ); ?>"></audio>
									<?php endif; ?>
								<?php elseif ( '' !== $part['given'] ) : ?>
									<p class="ohmylms-answer-review-part-answer"><span><?php esc_html_e( 'Your answer', 'ohmylms' ); ?>:</span> <?php echo esc_html( $part['given'] ); ?></p>
								<?php endif; ?>
								<?php if ( $part['expected'] ) : ?>
									<p class="ohmylms-answer-review-part-answer"><span><?php esc_html_e( 'Correct answer', 'ohmylms' ); ?>:</span> <?php echo esc_html( $part['expected'] ); ?></p>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php elseif ( $entry['given'] ) : ?>
					<div class="ohmylms-answer-review-given">
						<h3><?php esc_html_e( 'Your answer', 'ohmylms' ); ?></h3>
						<ul>
							<?php foreach ( $entry['given'] as $line ) : ?>
								<?php if ( in_array( $entry['type'], array( 'audio-response', 'video-response' ), true ) && preg_match( '#^https?://[^\s<>"]+$#i', $line ) ) : ?>
									<li>
										<?php if ( 'video-response' === $entry['type'] ) : ?>
											<video controls preload="none" src="<?php echo esc_url( $line ); ?>"></video>
										<?php else : ?>
											<audio controls preload="none" src="<?php echo esc_url( $line ); ?>"></audio>
										<?php endif; ?>
									</li>
								<?php else : ?>
									<li><?php echo esc_html( $line ); ?></li>
								<?php endif; ?>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if ( $entry['expected'] ) : ?>
					<div class="ohmylms-answer-review-expected">
						<h3><?php esc_html_e( 'Correct answer', 'ohmylms' ); ?></h3>
						<ul>
							<?php foreach ( $entry['expected'] as $line ) : ?>
								<li><?php echo esc_html( $line ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if ( '' !== trim( wp_strip_all_tags( $entry['explanation'] ) ) ) : ?>
					<div class="ohmylms-answer-review-explanation">
						<h3><?php esc_html_e( 'Explanation', 'ohmylms' ); ?></h3>
						<?php echo wp_kses_post( $entry['explanation'] ); ?>
					</div>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ol>

	<?php if ( $retry && $retrying && $attempt && class_exists( '\OhMyLMS\Practice\Inline' ) ) : ?>
		<section class="ohmylms-review-retry" id="<?php echo esc_attr( $anchor ); ?>-retry" aria-labelledby="<?php echo esc_attr( $anchor ); ?>-retry-title">
			<h2 id="<?php echo esc_attr( $anchor ); ?>-retry-title"><?php esc_html_e( 'Retry the questions I missed', 'ohmylms' ); ?></h2>
			<p><?php esc_html_e( 'This is practice. Your quiz marks do not change.', 'ohmylms' ); ?></p>
			<?php
			$practised = 0;
			foreach ( $retry as $item ) {
				$check = \OhMyLMS\Practice\Inline::render( $item['uuid'] );
				if ( '' === $check ) {
					continue;
				}
				++$practised;
				/* translators: %d: question number in the attempt */
				echo '<h3 class="ohmylms-review-retry-number">' . esc_html( sprintf( __( 'Question %d', 'ohmylms' ), (int) $item['number'] ) ) . '</h3>';
				echo $check; // phpcs:ignore WordPress.Security.EscapeOutput -- markup built and escaped by the inline check.
			}
			if ( ! $practised ) {
				echo '<p>' . esc_html__( 'These questions cannot be practised right now.', 'ohmylms' ) . '</p>';
			}
			?>
			<p><a href="<?php echo esc_url( AnswerReview::url( $attempt['quiz_attempt_id'] ) . '#' . $anchor ); ?>"><?php esc_html_e( 'Done practising', 'ohmylms' ); ?></a></p>
		</section>
	<?php endif; ?>
</section>
