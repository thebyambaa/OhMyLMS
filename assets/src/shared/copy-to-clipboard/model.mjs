export const RESET_DELAY = 2000;

/**
 * Whether the asynchronous Clipboard API can be used.
 *
 * @param {Object|undefined} navigatorLike Browser `navigator`.
 * @param {Object|undefined} windowLike    Browser `window`.
 * @return {boolean} True when the Clipboard API is available in a secure context.
 */
export function canUseClipboardApi( navigatorLike, windowLike ) {
	return !! ( navigatorLike?.clipboard && windowLike?.isSecureContext );
}

/**
 * Copy text, using the Clipboard API when possible and a hidden textarea with
 * `execCommand( 'copy' )` otherwise. Rejects when copying throws.
 *
 * @param {string} text                  Text to copy.
 * @param {Object} environment           Browser objects.
 * @param {Object} environment.navigator Browser `navigator`.
 * @param {Object} environment.window    Browser `window`.
 * @param {Object} environment.document  Browser `document`.
 * @return {Promise<void>} Resolves once the text was copied.
 */
export async function copyText( text, { navigator, window, document } ) {
	if ( canUseClipboardApi( navigator, window ) ) {
		await navigator.clipboard.writeText( text );
		return;
	}

	const textarea = document.createElement( 'textarea' );
	textarea.value = text;
	textarea.style.position = 'fixed';
	textarea.style.opacity = '0';
	document.body.appendChild( textarea );
	textarea.focus();
	textarea.select();
	document.execCommand( 'copy' );
	document.body.removeChild( textarea );
}
