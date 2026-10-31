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
		<?php if ( ! empty( $paper_outline ) ) { ?>
			<nav class="ohmylms-paper-nav" aria-label="<?php esc_attr_e( 'Questions on this paper', 'ohmylms' ); ?>" data-wp-interactive="ohmylms/paper" data-wp-context="<?php echo esc_attr( wp_json_encode( array( 'storageKey' => 'ohmylms-paper-flags:' . (int) $quiz_id . ':' . (int) $attempt_id ) ) ); ?>" data-wp-init="callbacks.init" data-wp-watch="callbacks.watchTime" data-wp-on--keydown="actions.navKey">
				<button type="button" class="ohmylms-button outline ohmylms-paper-nav-toggle" aria-expanded="false" aria-controls="ohmylms-paper-nav-<?php echo esc_attr( (int) $quiz_id ); ?>" data-wp-on--click="actions.toggleNav" data-wp-bind--aria-expanded="state.navOpen">
					<?php esc_html_e( 'Questions', 'ohmylms' ); ?>
					<span class="ohmylms-paper-nav-flags" data-wp-bind--hidden="!state.flaggedCount" hidden>(<span data-wp-text="state.flaggedCount">0</span> ⚑)</span>
				</button>
				<div class="ohmylms-paper-nav-panel" id="ohmylms-paper-nav-<?php echo esc_attr( (int) $quiz_id ); ?>" data-wp-bind--hidden="!state.navOpen" hidden>
					<?php
					$paper_section = null;
					foreach ( $paper_outline as $paper_entry ) {
						if ( $paper_entry['section'] !== $paper_section ) {
							if ( null !== $paper_section ) {
								echo '</ol>';
							}
							$paper_section = $paper_entry['section'];
							if ( '' !== $paper_section ) {
								echo '<p class="ohmylms-paper-nav-section">' . esc_html( $paper_section ) . '</p>';
							}
							echo '<ol class="ohmylms-paper-nav-list">';
						}
						?>
						<li>
							<button type="button" class="ohmylms-paper-nav-item" data-wp-context="
							<?php
							echo esc_attr(
								wp_json_encode(
									array(
										'questionId'     => $paper_entry['id'],
										'questionNumber' => $paper_entry['number'],
										'label'          => $paper_entry['label'],
									)
								)
							);
							?>
																									" data-wp-on--click="actions.jump" data-wp-class--is-answered="state.isAnswered" data-wp-class--is-flagged="state.isFlagged">
								<?php echo esc_html( $paper_entry['label'] ); ?>
								<span class="screen-reader-text" data-wp-text="state.itemStatus"></span>
							</button>
						</li>
						<?php
					}
					echo '</ol>';
					?>
					<p class="ohmylms-paper-nav-legend"><span class="is-answered"><?php esc_html_e( 'Answered', 'ohmylms' ); ?></span> <span class="is-flagged"><?php esc_html_e( 'Flagged', 'ohmylms' ); ?></span></p>
				</div>
				<p class="screen-reader-text" role="status" aria-live="polite" data-wp-text="state.announcement"></p>
			</nav>
		<?php } ?>
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
