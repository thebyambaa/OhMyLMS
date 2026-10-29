/**
 * Plain text of an HTML string, with entities decoded and tags removed.
 *
 * WordPress returns titles and names HTML-encoded (`Tom &amp; Jerry`), so they are decoded before
 * they are shown or put into a form field. The string is parsed into a detached document, which
 * runs no scripts. Anything that is not a string, or fails to parse, gives an empty string.
 *
 * @param {unknown} value HTML or plain text.
 * @return {string} Decoded text.
 */
export function decodeHtmlEntities( value ) {
	try {
		if ( 'string' !== typeof value ) {
			return '';
		}
		return (
			new window.DOMParser().parseFromString( value, 'text/html' )
				.documentElement.textContent || ''
		);
	} catch {
		return '';
	}
}
