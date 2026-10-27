const base = '/ohmylms/v1/syllabus-comparison';
const request = ( options ) => window.wp.apiFetch( options );

/**
 * Recover local proposals after admin navigation.
 * @param {string} key Draft key.
 * @return {Object} Stored proposal.
 */
export function readLocalDraft( key ) {
	try {
		return JSON.parse(
			window.sessionStorage.getItem( `ohmylms-comparison-${ key }` ) ||
				'{}'
		);
	} catch {
		return {};
	}
}

/**
 * Keep proposals local until an explicit save.
 * @param {string} key  Draft key.
 * @param {Object} data Proposal.
 */
export function saveLocalDraft( key, data ) {
	try {
		window.sessionStorage.setItem(
			`ohmylms-comparison-${ key }`,
			JSON.stringify( data )
		);
	} catch {
		/* The active draft remains in memory when storage is unavailable. */
	}
}

/**
 * Read a bounded comparison collection.
 * @param {string} route  API path.
 * @param {Object} params Filters.
 * @param          fetch  Request implementation.
 * @return {Promise} Response.
 */
export function readComparison( route, params = {}, fetch = request ) {
	const query = new URLSearchParams();
	for ( const [ key, value ] of Object.entries( params ) ) {
		if ( value !== '' && value !== undefined ) {
			query.set( key, String( value ) );
		}
	}
	return fetch( { path: `${ base }/${ route }?${ query }` } );
}

/**
 * Explicit administrator save.
 * @param {string} route  API path.
 * @param {Object} data   Write payload.
 * @param {string} method HTTP method.
 * @param          fetch  Request implementation.
 * @return {Promise} Response.
 */
export function writeComparison(
	route,
	data,
	method = 'POST',
	fetch = request
) {
	return fetch( { path: `${ base }/${ route }`, method, data } );
}

/**
 * Preview the selected objective-to-skill pairs without changing source IDs.
 * @param {Array}  objectives Selected source objectives.
 * @param {Object} shared     Shared target.
 * @param {string} relation   Relationship.
 * @param {string} note       Scope notes.
 * @return {Array} Explicit save payloads.
 */
export function mappingPreview( objectives, shared, relation, note ) {
	if ( ! shared?.id ) {
		return [];
	}
	return [
		...new Map(
			objectives.map( ( row ) => [ row.term_id, row ] )
		).values(),
	].map( ( row ) => ( {
		specific_id: row.term_id,
		shared_id: shared.id,
		relation,
		note,
		expected_revision:
			row.maps_to.find( ( mapping ) => mapping.shared_id === shared.id )
				?.revision || '',
	} ) );
}
