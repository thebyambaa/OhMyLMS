/** Pure helpers for assessment (exam) settings. Server times are UTC "Y-m-d H:i:s". */

const pad = ( value ) => String( value ).padStart( 2, '0' );

/**
 * UTC "2026-10-02 08:00:00" -> local "2026-10-02T16:00" for <input type="datetime-local">.
 * @param utc
 */
export function utcToLocalInput( utc ) {
	if ( ! utc ) {
		return '';
	}
	const date = new Date( `${ String( utc ).replace( ' ', 'T' ) }Z` );
	if ( Number.isNaN( date.getTime() ) ) {
		return '';
	}
	return `${ date.getFullYear() }-${ pad( date.getMonth() + 1 ) }-${ pad( date.getDate() ) }T${ pad( date.getHours() ) }:${ pad( date.getMinutes() ) }`;
}

/**
 * Local datetime-local value -> ISO UTC string the server accepts ("" clears).
 * @param local
 */
export function localInputToUtc( local ) {
	if ( ! local ) {
		return '';
	}
	const date = new Date( local );
	return Number.isNaN( date.getTime() )
		? ''
		: date.toISOString().replace( /\.\d{3}Z$/, 'Z' );
}

/**
 * Move a question into a section (removing it from any other) or out of all sections (index -1).
 * @param sections
 * @param questionId
 * @param index
 */
export function assignQuestion( sections, questionId, index ) {
	const id = Number( questionId );
	const next = ( sections || [] ).map( ( section ) => ( {
		...section,
		questions: ( section.questions || [] ).filter(
			( value ) => Number( value ) !== id
		),
		marks: Object.fromEntries(
			Object.entries( section.marks || {} ).filter(
				( [ key ] ) => Number( key ) !== id
			)
		),
	} ) );
	if ( index >= 0 && next[ index ] ) {
		next[ index ].questions = [ ...next[ index ].questions, id ];
	}
	return next;
}

export function sectionOf( sections, questionId ) {
	return ( sections || [] ).findIndex( ( section ) =>
		( section.questions || [] )
			.map( Number )
			.includes( Number( questionId ) )
	);
}

export function setSlotMarks( sections, questionId, marks ) {
	const index = sectionOf( sections, questionId );
	if ( index < 0 ) {
		return sections;
	}
	return sections.map( ( section, position ) => {
		if ( position !== index ) {
			return section;
		}
		const copy = { ...( section.marks || {} ) };
		if ( marks === '' || marks === null || marks === undefined ) {
			delete copy[ questionId ];
		} else {
			copy[ questionId ] = Number( marks );
		}
		return { ...section, marks: copy };
	} );
}

/**
 * Accommodation rows <-> server map {user_id: seconds}.
 * @param map
 */
export function accommodationRows( map ) {
	return Object.entries( map || {} ).map( ( [ userId, seconds ] ) => ( {
		userId: String( userId ),
		minutes: String( Math.round( Number( seconds ) / 60 ) ),
	} ) );
}
export function accommodationMap( rows ) {
	const result = {};
	for ( const row of rows || [] ) {
		const minutes = Number( row.minutes );
		if ( Number( row.userId ) > 0 && minutes > 0 ) {
			result[ Number( row.userId ) ] = Math.round( minutes * 60 );
		}
	}
	return result;
}

/**
 * Quiz ID from the admin hash route (#/quiz-edit/123).
 * @param hash
 */
export function quizIdFromHash( hash ) {
	const match = String( hash || '' ).match( /quiz-edit\/(\d+)/ );
	return match ? Number( match[ 1 ] ) : 0;
}

/** Skill level display helpers. */
const SHORT = {
	'not-started': '–',
	attempted: 'A',
	familiar: 'F',
	proficient: 'P',
	mastered: 'M',
};
export const levelShort = ( level ) => SHORT[ level ] || '·';
export const levelClass = ( level ) =>
	level ? `ohmylms-skill-${ level }` : '';

/**
 * Authoring syntax for an inline check (rendered and graded by the server).
 * @param uuid
 */
export const inlineShortcode = ( uuid ) =>
	`[ohmylms_question uuid="${ uuid }"]`;
