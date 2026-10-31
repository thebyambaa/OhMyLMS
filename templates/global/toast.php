<?php
/**
 * The template for displaying toast notice
 *
 * The page's single toast live region, driven by the `ohmylms/ui` store. Show a notice with
 * `toast( message, { type } )` from `ohmylms/interactivity`, or dispatch an `ohmylms:toast` event.
 * Only the first rendered copy is output.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/global/toast.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>

<div class="ohmylms-toast" id="ohmylms-toast"
	data-wp-interactive="ohmylms/ui"
	data-wp-init="callbacks.toastRegion"
	data-wp-class--active="state.toast.visible"
	data-wp-class--toast-success="state.toastSuccess"
	data-wp-class--toast-warning="state.toastWarning"
	data-wp-class--toast-danger="state.toastDanger"
	data-wp-on--mouseenter="actions.pauseToast"
	data-wp-on--mouseleave="actions.resumeToast"
	data-wp-on--focusin="actions.pauseToast"
	data-wp-on--focusout="actions.resumeToast"
	data-wp-on--keydown="actions.toastKeydown"
	role="status" aria-live="polite" aria-atomic="true">
	<div class="ohmylms-toast-content" data-wp-bind--hidden="state.toastHidden" hidden>
		<span class="ohmylms-message" data-wp-text="state.toast.message"></span>
		<button type="button" class="ohmylms-close" id="ohmylms-close-toast" data-wp-on--click="actions.dismissToast" aria-label="<?php esc_attr_e( 'Dismiss notification', 'ohmylms' ); ?>">
			<svg width="14" height="14" fill="none" viewBox="0 0 14 14" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path stroke="#19AA32" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 1L1 13M1 1l12 12"/></svg>
		</button>
	</div>
</div>
