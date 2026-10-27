<?php
/**
 * Exam top bar: exam title, exit and a Review and Submit shortcut.
 *
 * @package OhMyLMS
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ohmylms-quiz-header ohmylms-exam-bar">
	<div class="ohmylms-exam-bar-title">
		<span class="ohmylms-exam-bar-name"><?php echo esc_html( $quiz_name ); ?></span>
		<?php if ( $is_preview ) : ?>
			<small><?php esc_html_e( 'Preview of saved exam · Responses are not recorded', 'ohmylms' ); ?></small>
		<?php endif; ?>
	</div>
	<div class="ohmylms-exam-bar-actions">
		<a href="#" class="quiz-page-close ohmylms-exam-exit"><?php esc_html_e( 'Exit', 'ohmylms' ); ?></a>
		<button type="button" class="ohmylms-exam-review" data-wp-on--click="actions.reviewSubmit" data-wp-bind--disabled="context.submitting">
			<?php esc_html_e( 'Review and Submit', 'ohmylms' ); ?>
			<svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
		</button>
	</div>
</div>
