/**
 * Display numbers for the units (chapters) of a course: a short label such as "3" or "2A".
 * An empty label means the unit is numbered by its position. Pure helpers plus a REST client with
 * an injectable fetch; the server applies the same rule.
 */
export const UNIT_NUMBER_MAX = 10;

const PATTERN = new RegExp( `^[A-Za-z0-9.-]{1,${ UNIT_NUMBER_MAX }}$` );

const request = ( options ) => window.wp.apiFetch( options );

/**
 * Clean a typed label.
 * @param {unknown} raw Typed value.
 * @return {string|null} The label ('' when cleared) or null when it is not allowed.
 */
export function normalizeUnitNumber( raw ) {
	const label = String( raw ?? '' ).trim();
	if ( label === '' ) {
		return '';
	}
	return PATTERN.test( label ) ? label : null;
}

/**
 * The label a unit shows: its own, or its position (from 1) when it has none.
 * @param {string} stored   Stored label.
 * @param {number} position Zero-based position in the course.
 * @return {string} Label.
 */
export const unitLabel = ( stored, position ) =>
	stored ? String( stored ) : String( position + 1 );

/**
 * What to save: units whose typed label differs from the stored one, and units with an invalid label.
 * @param {Object[]} chapters Units as loaded: [{id, number}].
 * @param {Object}   edits    Typed labels by unit id.
 * @return {{changes: Object[], invalid: number[]}} Changes [{id, number}] and ids to fix first.
 */
export function unitNumberChanges( chapters, edits ) {
	const changes = [];
	const invalid = [];
	for ( const chapter of chapters ) {
		if ( ! Object.prototype.hasOwnProperty.call( edits, chapter.id ) ) {
			continue;
		}
		const number = normalizeUnitNumber( edits[ chapter.id ] );
		if ( number === null ) {
			invalid.push( chapter.id );
		} else if ( number !== ( chapter.number || '' ) ) {
			changes.push( { id: chapter.id, number } );
		}
	}
	return { changes, invalid };
}

/**
 * Store one unit's label; an empty label returns the unit to automatic numbering.
 * @param {number}                               courseId  Course.
 * @param {number}                               chapterId Unit.
 * @param {string}                               number    Label.
 * @param {(request: Object) => Promise<Object>} fetch     Request function.
 * @return {Promise<Object>} Fresh catalog.
 */
export const saveUnitNumber = (
	courseId,
	chapterId,
	number,
	fetch = request
) =>
	fetch( {
		path: `/ohmylms/v1/content-hub/catalog/${ courseId }/chapters/${ chapterId }`,
		method: 'PUT',
		data: { number },
	} );
