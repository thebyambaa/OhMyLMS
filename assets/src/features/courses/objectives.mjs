import { restUrl } from '../restUrl.mjs';

/**
 * Show the source identity consistently in the picker and selected targets.
 * @param {Object} row Syllabus record.
 * @return {string} Source label.
 */
export const syllabusLabel = ( row ) =>
	[ row.code, row.name, row.version, row.grade, row.subject ]
		.filter( Boolean )
		.join( ' · ' );

const LAST_KEY = 'ohmylmsLastObjectiveSyllabus';

/**
 * The edition to open on: the one this course already uses most, then the one chosen last time, then
 * the first. Opening on an unrelated edition made authors hunt for the right one.
 * @param {Object[]} syllabuses Editions on offer.
 * @param {Object[]} mappings   Objectives already on the course.
 * @param {Storage}  storage    Where the last choice is kept.
 */
export function defaultSyllabusId( syllabuses, mappings, storage ) {
	const counts = new Map();
	for ( const row of mappings || [] ) {
		counts.set(
			row.syllabus_id,
			( counts.get( row.syllabus_id ) || 0 ) + 1
		);
	}
	const known = new Set( syllabuses.map( ( row ) => row.id ) );
	const used = [ ...counts.entries() ]
		.filter( ( [ id ] ) => known.has( id ) )
		.sort( ( a, b ) => b[ 1 ] - a[ 1 ] )[ 0 ];
	if ( used ) {
		return String( used[ 0 ] );
	}
	let last = '';
	try {
		last = storage?.getItem( LAST_KEY ) || '';
	} catch {
		last = '';
	}
	if ( last && known.has( Number( last ) ) ) {
		return last;
	}
	return String( syllabuses[ 0 ]?.id || '' );
}

/**
 * Remember the edition for next time; storage can be unavailable.
 * @param {string}  id      Edition ID.
 * @param {Storage} storage Where to keep it.
 */
export function rememberSyllabus( id, storage ) {
	try {
		storage?.setItem( LAST_KEY, String( id ) );
	} catch {
		// Remembering the edition is a convenience only.
	}
}

/**
 * Stable identity includes the syllabus because a skill can occur in several.
 * @param row
 */
export const objectiveKey = ( row ) => `${ row.syllabus_id }:${ row.term_id }`;
export const objectiveReferences = ( rows ) =>
	rows.map( ( row ) => ( {
		syllabus_id: row.syllabus_id,
		term_id: row.term_id,
	} ) );

/**
 * Filter the full official wording as well as codes, titles and topics.
 * @param rows
 * @param search
 * @param topic
 */
export function filterObjectives( rows, search = '', topic = '' ) {
	const words = search
		.trim()
		.toLocaleLowerCase()
		.split( /\s+/ )
		.filter( Boolean );
	return rows.filter( ( row ) => {
		const text =
			`${ row.code } ${ row.name } ${ row.objective } ${ row.topic }`.toLocaleLowerCase();
		return (
			( ! topic || row.topic === topic ) &&
			words.every( ( word ) => text.includes( word ) )
		);
	} );
}

/**
 * Use the localized REST root on subdirectory and plain-permalink sites.
 * @param courseId
 * @param options
 */
export function objectiveRequest( courseId, options = {} ) {
	const { syllabusId, ...settings } = options;
	const path = `/ohmylms/v1/courses/${ courseId }/syllabus-objectives${ syllabusId ? `?syllabus_id=${ Number( syllabusId ) }` : '' }`;
	const root = window.ohmylms_params?.api_url;
	return window.wp.apiFetch( {
		...settings,
		...( root ? { url: restUrl( root, path ) } : { path } ),
	} );
}
