// @ts-check
import apiFetch from '@wordpress/api-fetch';

/**
 * REST transport of the native editors and blocks: WordPress api-fetch with the site's REST root and
 * nonce middleware. A failure rejects with the parsed REST error (`code`, `message`, `data`) unchanged,
 * which is what the callers read. Every helper module takes it as the default of an injectable
 * `fetch` argument so Node tests can record requests.
 *
 * @param {import('@wordpress/api-fetch').APIFetchOptions} options Request options.
 * @return {Promise<Object>} Parsed response body.
 */
export const request = ( options ) =>
	/** @type {Promise<Object>} */ ( apiFetch( options ) );

/**
 * Query string for a REST path. `undefined`, `null` and empty values are left out.
 *
 * @param {Record<string, unknown>} [params] Parameters.
 * @return {string} `?a=1&b=2`, or an empty string when nothing is set.
 */
export const queryString = ( params ) => {
	const search = new URLSearchParams();
	for ( const [ key, value ] of Object.entries( params || {} ) ) {
		if ( value !== undefined && value !== null && value !== '' ) {
			search.set( key, String( value ) );
		}
	}
	const text = search.toString();
	return text ? `?${ text }` : '';
};
