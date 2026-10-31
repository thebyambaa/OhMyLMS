<?php
/**
 * The learner's submissions of an assignment, each with its grade, feedback and comment thread.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-lesson/assignment-submissions.php.
 *
 * @package OhMyLMS\Templates
 * @version 1.2.22
 * @global \OhMyLMS\Data\Assignment $assignment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use OhMyLMS\Assignment\Comments;

$learner_id = get_current_user_id();
if ( ! $learner_id || empty( $assignment ) ) {
	return;
}

$rows = $assignment->get_submission( $learner_id );
if ( empty( $rows ) ) {
	return;
}
usort(
	$rows,
	static function ( $a, $b ) {
		return (int) $b['id'] <=> (int) $a['id'];
	}
);

$comments_on = Comments::enabled( $assignment->get_id() );
$max_score   = (int) $assignment->get_total_points();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only result of the comment form redirect.
$form_result = isset( $_GET['ohmylms_comment'] ) ? sanitize_key( wp_unslash( $_GET['ohmylms_comment'] ) ) : '';
$messages    = array(
	'added'                                   => __( 'Your comment was posted.', 'ohmylms' ),
	'ohmylms_assignment_invalid_comment'      => __( 'Write a comment before posting.', 'ohmylms' ),
	'ohmylms_assignment_comments_disabled'    => __( 'Comments are switched off for this assignment.', 'ohmylms' ),
	'ohmylms_assignment_comment_rate_limited' => __( 'Wait a few seconds before posting another comment.', 'ohmylms' ),
);
?>
<section class="ohmylms-assignment-submissions" aria-labelledby="ohmylms-assignment-submissions-title">
	<h2 id="ohmylms-assignment-submissions-title" class="ohmylms-assignment-section-title"><?php esc_html_e( 'Your submissions', 'ohmylms' ); ?></h2>
	<?php if ( $form_result ) : ?>
		<p class="ohmylms-assignment-comment-result" role="status"><?php echo esc_html( $messages[ $form_result ] ?? __( 'The comment could not be posted.', 'ohmylms' ) ); ?></p>
	<?php endif; ?>
	<?php foreach ( $rows as $row ) : ?>
		<?php
		$submission_id = (int) $row['id'];
		$graded        = 'submitted' !== (string) $row['status'];
		$thread        = Comments::page( $submission_id );
		?>
		<article class="ohmylms-assignment-submission" id="ohmylms-submission-<?php echo esc_attr( $submission_id ); ?>">
			<h3>
				<?php
				printf(
					/* translators: %s: submission date. */
					esc_html__( 'Submitted %s', 'ohmylms' ),
					esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( get_gmt_from_date( (string) $row['start_date'] ) . ' UTC' ) ) )
				);
				?>
			</h3>
			<p class="ohmylms-assignment-submission-status">
				<?php if ( $graded ) : ?>
					<?php
					printf(
						/* translators: 1: score, 2: maximum score, 3: Passed or Failed. */
						esc_html__( 'Graded: %1$d / %2$d (%3$s)', 'ohmylms' ),
						(int) $row['score'],
						(int) $max_score,
						'passed' === $row['status'] ? esc_html__( 'Passed', 'ohmylms' ) : esc_html__( 'Not passed', 'ohmylms' )
					);
					?>
				<?php else : ?>
					<?php esc_html_e( 'Waiting for a grade', 'ohmylms' ); ?>
				<?php endif; ?>
			</p>
			<?php if ( $graded && '' !== trim( (string) $row['note'] ) ) : ?>
				<div class="ohmylms-assignment-feedback">
					<strong><?php esc_html_e( 'Feedback', 'ohmylms' ); ?></strong>
					<?php echo wp_kses_post( wpautop( (string) $row['note'] ) ); ?>
				</div>
			<?php endif; ?>

			<?php if ( $thread['items'] || $comments_on ) : ?>
				<div class="ohmylms-assignment-comments">
					<h4><?php esc_html_e( 'Comments', 'ohmylms' ); ?></h4>
					<?php if ( $thread['items'] ) : ?>
						<ol class="ohmylms-assignment-comment-list">
							<?php foreach ( $thread['items'] as $note ) : ?>
								<li class="ohmylms-assignment-comment is-<?php echo esc_attr( $note['role'] ); ?>">
									<p class="ohmylms-assignment-comment-meta">
										<strong><?php echo esc_html( $note['author']['display_name'] ); ?></strong>
										<?php if ( 'instructor' === $note['role'] ) : ?>
											<span>(<?php esc_html_e( 'Instructor', 'ohmylms' ); ?>)</span>
										<?php endif; ?>
										<time datetime="<?php echo esc_attr( $note['created_at'] ); ?>"><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $note['created_at'] ) ) ); ?></time>
									</p>
									<div class="ohmylms-assignment-comment-body"><?php echo wp_kses_post( wpautop( esc_html( $note['body'] ) ) ); ?></div>
								</li>
							<?php endforeach; ?>
						</ol>
					<?php else : ?>
						<p><?php esc_html_e( 'No comments yet.', 'ohmylms' ); ?></p>
					<?php endif; ?>

					<?php if ( $comments_on ) : ?>
						<form class="ohmylms-assignment-comment-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<label for="ohmylms-comment-<?php echo esc_attr( $submission_id ); ?>"><?php esc_html_e( 'Add a comment', 'ohmylms' ); ?></label>
							<textarea id="ohmylms-comment-<?php echo esc_attr( $submission_id ); ?>" name="comment" rows="3" maxlength="<?php echo esc_attr( Comments::MAX_LENGTH ); ?>" required></textarea>
							<input type="hidden" name="action" value="ohmylms_assignment_comment">
							<input type="hidden" name="assignment_id" value="<?php echo esc_attr( $assignment->get_id() ); ?>">
							<input type="hidden" name="submission_id" value="<?php echo esc_attr( $submission_id ); ?>">
							<?php wp_nonce_field( 'ohmylms_assignment_comment_' . $submission_id, 'ohmylms_comment_nonce' ); ?>
							<button type="submit" class="ohmylms-button"><?php esc_html_e( 'Post comment', 'ohmylms' ); ?></button>
						</form>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</article>
	<?php endforeach; ?>
</section>
