<?php
/**
 * Reactive page progress for every player design and pagination mode.
 *
 * @package OhMyLMS
 */

defined( 'ABSPATH' ) || exit;
if ( ! empty( $paper_presentation ) ) {
	?>
	<div class="ohmylms-paper-progress" aria-label="<?php esc_attr_e( 'Assessment progress', 'ohmylms' ); ?>">
		<span><strong data-wp-text="state.answeredCount">0</strong> / <?php echo esc_html( $question_count ); ?> <?php esc_html_e( 'answered', 'ohmylms' ); ?></span>
		<span data-wp-bind--hidden="!context.timed" hidden><span data-wp-text="state.timeLabel"></span></span>
		<button type="button" class="ohmylms-button outline" data-wp-on--click="actions.reviewPaper"><?php esc_html_e( 'Review unanswered', 'ohmylms' ); ?></button>
	</div>
	<?php
	return;
}
?>
<div class="ohmylms-player-progress" aria-label="<?php esc_attr_e( 'Quiz progress', 'ohmylms' ); ?>">
	<span class="ohmylms-player-page" data-wp-text="state.pageLabel"><?php echo esc_html( isset( $layout ) && 'one_question_per_page' === $layout ? sprintf( /* translators: 1: current question, 2: total questions */ __( 'Question %1$d of %2$d', 'ohmylms' ), 1, $total_pages ) : '1 / ' . $total_pages ); ?></span>
	<div class="ohmylms-player-track" role="progressbar" aria-label="<?php esc_attr_e( 'Page progress', 'ohmylms' ); ?>" aria-valuemin="1" aria-valuemax="<?php echo esc_attr( $total_pages ); ?>" aria-valuenow="1" data-wp-bind--aria-valuenow="context.page">
		<span data-wp-style--width="state.pageWidth"></span>
	</div>
</div>
