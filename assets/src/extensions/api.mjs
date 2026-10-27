import apiFetch from '@wordpress/api-fetch';
/** Always use WordPress's nonce/root middleware and server authorization. */
export const getExtensionManifest = () =>
	apiFetch( { path: '/ohmylms/v1/extensions' } );
export const getMembership = ( id ) =>
	apiFetch( { path: `/ohmylms/v1/membership/${ Number( id ) }` } );
export const saveMembership = ( id, data ) =>
	apiFetch( {
		path: `/ohmylms/v1/membership${ id ? `/${ Number( id ) }` : '' }`,
		method: id ? 'PUT' : 'POST',
		data,
	} );
export function getExtensionSettings( type, id ) {
	return apiFetch( {
		path: `/ohmylms/v1/extension-settings/${ type }/${ Number( id ) }`,
	} );
}
export function saveExtensionSettings( type, id, settings ) {
	return apiFetch( {
		path: `/ohmylms/v1/extension-settings/${ type }/${ Number( id ) }`,
		method: 'PUT',
		data: { settings },
	} );
}
