<?php
/**
 * Exam footer controls on the left: mark for review and clear response.
 *
 * @package OhMyLMS
 */

defined( 'ABSPATH' ) || exit;
?>
<button type="button" class="ohmylms-exam-mark" data-wp-on--click="actions.toggleMarkCurrent" data-wp-class--is-on="state.isCurrentMarked">
	<svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M6 3h12v18l-6-4-6 4z"/></svg>
	<span data-wp-text="state.currentMarkLabel"><?php esc_html_e( 'Mark for review', 'ohmylms' ); ?></span>
</button>
<button type="button" class="ohmylms-exam-clear" data-wp-on--click="actions.clearCurrent">
	<?php esc_html_e( 'Clear Response', 'ohmylms' ); ?>
</button>
