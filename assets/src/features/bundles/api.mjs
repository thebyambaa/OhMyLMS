const request = ( options ) => window.wp.apiFetch( options );
const base = '/ohmylms/v1/bundles';
const query = ( params ) => {
	const search = new URLSearchParams();
	for ( const [ key, value ] of Object.entries( params || {} ) ) {
		if ( value !== undefined && value !== null && value !== '' ) {
			search.set( key, String( value ) );
		}
	}
	const text = search.toString();
	return text ? `?${ text }` : '';
};

/**
 * REST calls for Course Bundle administration. Each accepts an injected request function for tests.
 * @param fetch
 */
export const listBundles = ( fetch = request ) => fetch( { path: base } );
export const loadBundle = ( id, fetch = request ) =>
	fetch( { path: `${ base }/${ id }` } );
export const createBundle = ( data, fetch = request ) =>
	fetch( { path: base, method: 'POST', data } );
export const updateBundle = ( id, data, fetch = request ) =>
	fetch( { path: `${ base }/${ id }`, method: 'PUT', data } );
export const setMembers = ( id, items, expectedUpdatedAt, fetch = request ) =>
	fetch( {
		path: `${ base }/${ id }/items`,
		method: 'PUT',
		data: expectedUpdatedAt
			? { items, expected_updated_at: expectedUpdatedAt }
			: { items },
	} );
export const publishBundle = ( id, published, fetch = request ) =>
	fetch( {
		path: `${ base }/${ id }/publish`,
		method: 'POST',
		data: { published },
	} );
export const deleteBundle = ( id, force = false, fetch = request ) =>
	fetch( { path: `${ base }/${ id }`, method: 'DELETE', data: { force } } );
export const searchMembers = ( type, search, fetch = request ) =>
	fetch( { path: `${ base }/targets${ query( { type, search } ) }` } );
