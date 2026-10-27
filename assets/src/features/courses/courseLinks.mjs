/**
 * Advisory links between courses (sub-course, prerequisite, "Get ready for"), managed through
 * GET/PUT /ohmylms/v1/courses/{id}/links. Pure helpers plus a REST client with an injectable fetch.
 */
export const LINK_TYPES = [ 'prerequisite', 'subcourse', 'get_ready' ];

export const MAX_LINKS = 50;

const request = ( options ) => window.wp.apiFetch( options );

/**
 * Stable identity of a link: a course may be linked once per type.
 * @param {Object} link Link.
 * @return {string} Key.
 */
export const linkKey = ( link ) => `${ link.course_id }:${ link.type }`;

/**
 * Add a link at the end; duplicates, self links and the limit are refused.
 * @param {Object[]} links    Current links.
 * @param {number}   courseId Course being edited.
 * @param {Object}   link     {course_id, type, title?}.
 * @return {Object[]} New list (the same list when refused).
 */
export function addLink( links, courseId, link ) {
	const related = Number( link.course_id );
	if (
		! related ||
		related === Number( courseId ) ||
		links.length >= MAX_LINKS ||
		links.some( ( entry ) => linkKey( entry ) === linkKey( link ) )
	) {
		return links;
	}
	return [ ...links, { ...link, course_id: related } ];
}

export const removeLink = ( links, key ) =>
	links.filter( ( link ) => linkKey( link ) !== key );

/**
 * The hub strip a link appears in: sub-courses have their own; prerequisites and "Get ready for"
 * suggestions share "Get ready for this course".
 * @param {Object} link Link.
 * @return {string} Strip.
 */
export const stripOf = ( link ) =>
	link.type === 'subcourse' ? 'subcourse' : 'get_ready';

/**
 * The link a move would swap with: the nearest one in the same strip in that direction. Swapping
 * with a neighbour of another strip would change nothing a learner can see.
 * @param {Object[]} links     Links.
 * @param {string}   key       Key of the link to move.
 * @param {number}   direction -1 (up) or 1 (down).
 * @return {number} Index to swap with, or -1 when the link cannot move.
 */
function swapIndex( links, key, direction ) {
	const index = links.findIndex( ( link ) => linkKey( link ) === key );
	if ( index < 0 ) {
		return -1;
	}
	for (
		let other = index + direction;
		other >= 0 && other < links.length;
		other += direction
	) {
		if ( stripOf( links[ other ] ) === stripOf( links[ index ] ) ) {
			return other;
		}
	}
	return -1;
}

export const canMoveLink = ( links, key, direction ) =>
	swapIndex( links, key, direction ) >= 0;

export function moveLink( links, key, direction ) {
	const other = swapIndex( links, key, direction );
	if ( other < 0 ) {
		return links;
	}
	const index = links.findIndex( ( link ) => linkKey( link ) === key );
	const next = [ ...links ];
	[ next[ index ], next[ other ] ] = [ next[ other ], next[ index ] ];
	return next;
}

/**
 * The body the server expects: order is the array order.
 * @param {Object[]} links Links.
 * @return {Object[]} [{course_id, type}].
 */
export const linkPayload = ( links ) =>
	links.map( ( link ) => ( { course_id: link.course_id, type: link.type } ) );

export const loadLinks = ( courseId, fetch = request ) =>
	fetch( { path: `/ohmylms/v1/courses/${ courseId }/links` } );

export const saveLinks = ( courseId, links, fetch = request ) =>
	fetch( {
		path: `/ohmylms/v1/courses/${ courseId }/links`,
		method: 'PUT',
		data: { links: linkPayload( links ) },
	} );

/**
 * Courses matching a search, as [{id, title, status}], without the course being edited.
 * @param {number}                               courseId Course being edited.
 * @param {string}                               search   Search text.
 * @param {(request: Object) => Promise<Object>} fetch    Request function.
 * @return {Promise<Object[]>} Matches.
 */
export async function searchCourses( courseId, search, fetch = request ) {
	const query = new URLSearchParams( {
		per_page: '10',
		search,
		exclude: String( courseId ),
		post_status: 'any',
	} );
	const result = await fetch( {
		path: `/ohmylms/v1/courses?${ query.toString() }`,
	} );
	const rows = Array.isArray( result )
		? result
		: result?.courses || result?.data || result?.items || [];
	return rows.map( ( row ) => ( {
		id: Number( row.id ),
		title: row.name || row.title?.rendered || row.title || `#${ row.id }`,
		status: row.status || row.post_status || '',
	} ) );
}
