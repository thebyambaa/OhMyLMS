/**
 * admin-ajax URL for the sign-up request: the URL localized with the registration script
 * (`ohmylmsRegistration.ajaxUrl`), else the `ohmylms/interactivity` module config every OhMyLMS page
 * prints, else the legacy page globals. Never a relative "undefined".
 *
 * @param {Object}   [win] Window.
 * @param {Document} [doc] Document.
 * @return {string} URL, '' when the page has none.
 */
export function registrationUrl(
	win = globalThis.window,
	doc = globalThis.document
) {
	let moduleUrl = '';
	try {
		const data = doc?.getElementById(
			'wp-script-module-data-ohmylms/interactivity'
		)?.textContent;
		moduleUrl = data ? JSON.parse( data )?.ajaxUrl || '' : '';
	} catch {
		moduleUrl = '';
	}
	const url =
		win?.ohmylmsRegistration?.ajaxUrl ||
		moduleUrl ||
		win?.ohmylms_frontend_params?.ajax_url ||
		win?.ohmylms_checkout_params?.ajax_url ||
		'';
	return typeof url === 'string' ? url : '';
}

/**
 * Keep PHP hook fields and the existing nonce/action in the request.
 * @param url
 * @param formData
 * @param fetch
 */
export async function submitRegistration(
	url,
	formData,
	fetch = globalThis.fetch
) {
	if ( typeof url !== 'string' || ! url ) {
		throw Error( 'Registration could not be completed. Please try again.' );
	}
	const response = await fetch( url, {
		method: 'POST',
		credentials: 'same-origin',
		body: formData,
	} );
	let result;
	try {
		result = await response.json();
	} catch {
		throw Error( 'Registration could not be completed. Please try again.' );
	}
	if (
		! response.ok ||
		! [ 'success', 'pending_verification' ].includes( result?.status )
	) {
		throw Error(
			result?.message ||
				'Registration could not be completed. Please try again.'
		);
	}
	return result;
}
export function registrationRedirect( value, origin ) {
	if ( typeof value !== 'string' || ! value.trim() ) {
		return null;
	}
	try {
		const target = new URL( value, origin );
		return target.origin === new URL( origin ).origin ? target.href : null;
	} catch {
		return null;
	}
}
