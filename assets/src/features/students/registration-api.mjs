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
