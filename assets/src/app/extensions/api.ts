import apiFetch from '@wordpress/api-fetch';

/**
 * REST helpers exposed to extensions as `window.ohmylms.extensions.api`. They use WordPress api-fetch, so
 * its nonce and root middleware apply; the server authorizes every request.
 */

/** Definitions of every registered extension (`GET /ohmylms/v1/extensions`); no saved private data. */
export function getExtensionManifest(): Promise< unknown > {
	return apiFetch( { path: '/ohmylms/v1/extensions' } );
}

/**
 * Saved extension settings of one content item. Requires `edit_post` on that item.
 *
 * @param type Content type, for example `course` or `membership`.
 * @param id   Content id.
 * @return The `{ settings }` response.
 */
export function getExtensionSettings(
	type: string,
	id: number
): Promise< unknown > {
	return apiFetch( {
		path: `/ohmylms/v1/extension-settings/${ encodeURIComponent(
			type
		) }/${ Number( id ) }`,
	} );
}

/**
 * Replace the extension settings of one content item. Namespaces that are omitted are retained.
 *
 * @param type     Content type.
 * @param id       Content id.
 * @param settings Settings keyed by extension namespace.
 * @return The saved `{ settings }` response.
 */
export function saveExtensionSettings(
	type: string,
	id: number,
	settings: Record< string, unknown >
): Promise< unknown > {
	return apiFetch( {
		path: `/ohmylms/v1/extension-settings/${ encodeURIComponent(
			type
		) }/${ Number( id ) }`,
		method: 'PUT',
		data: { settings },
	} );
}
