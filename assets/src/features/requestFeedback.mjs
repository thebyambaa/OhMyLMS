/**
 * Read a localized label without coupling pure models to the WordPress loader.
 * @param {string} fallback English fallback for non-browser callers.
 * @return {string} Localized label.
 */
function label( fallback ) {
	const labels = globalThis.wp?.i18n;
	if ( fallback === 'Cancel' ) {
		return labels?.__( 'Cancel', 'ohmylms' ) || fallback;
	}
	if ( fallback === 'Continue' ) {
		return labels?.__( 'Continue', 'ohmylms' ) || fallback;
	}
	if ( fallback === 'Dismiss' ) {
		return labels?.__( 'Dismiss', 'ohmylms' ) || fallback;
	}
	return (
		labels?.__(
			'The request could not be completed. Please try again.',
			'ohmylms'
		) || fallback
	);
}
/**
 * Show a dismissible, text-only error instead of a blocking alert or developer log.
 * @param {...unknown} details Request errors or a validation message.
 */
export function showRequestError( ...details ) {
	if ( typeof document === 'undefined' ) {
		return;
	}
	const error = details.find(
		( value ) => typeof value?.message === 'string'
	);
	const message =
		error?.message ||
		( details.length === 1 && typeof details[ 0 ] === 'string'
			? details[ 0 ]
			: label(
					'The request could not be completed. Please try again.'
				) );
	document.getElementById( 'ohmylms-request-feedback' )?.remove();
	const notice = document.createElement( 'div' );
	notice.id = 'ohmylms-request-feedback';
	notice.className = 'ohmylms-request-feedback';
	notice.setAttribute( 'role', 'alert' );
	const text = document.createElement( 'p' );
	text.textContent = message;
	const dismiss = document.createElement( 'button' );
	dismiss.type = 'button';
	dismiss.textContent = label( 'Dismiss' );
	dismiss.addEventListener( 'click', () => notice.remove() );
	notice.append( text, dismiss );
	document.body.append( notice );
}
/**
 * Ask before a destructive action, preserving keyboard focus on dismissal.
 * @param {string} message Confirmation message.
 * @return {Promise<boolean>} Whether the user chose to continue.
 */
export function confirmAction( message ) {
	return new Promise( ( resolve ) => {
		const previous = document.activeElement;
		const dialog = document.createElement( 'dialog' );
		dialog.className = 'ohmylms-action-confirmation';
		const text = document.createElement( 'p' );
		text.id = 'ohmylms-action-confirmation-message';
		text.textContent = message;
		dialog.setAttribute( 'aria-labelledby', text.id );
		const cancel = document.createElement( 'button' );
		cancel.type = 'button';
		cancel.textContent = label( 'Cancel' );
		const accept = document.createElement( 'button' );
		accept.type = 'button';
		accept.textContent = label( 'Continue' );
		let confirmed = false;
		cancel.addEventListener( 'click', () => dialog.close() );
		accept.addEventListener( 'click', () => {
			confirmed = true;
			dialog.close();
		} );
		dialog.addEventListener(
			'close',
			() => {
				dialog.remove();
				previous?.focus();
				resolve( confirmed );
			},
			{ once: true }
		);
		dialog.append( text, cancel, accept );
		document.body.append( dialog );
		dialog.showModal();
		cancel.focus();
	} );
}
