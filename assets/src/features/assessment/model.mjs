/** Pure helpers for assessment (exam) settings. Server times are UTC "Y-m-d H:i:s". */

/**
 * A section of an assessment, includes/Assessment/AssessmentSettings.php.
 *
 * @typedef {Object} AssessmentSection
 * @property {string}                 title       Title.
 * @property {(number|string)[]}      [questions] Question IDs, in order.
 * @property {Record<string, number>} [marks]     Slot marks by question ID.
 * @property {boolean}                [new_page]  Starts a new page.
 */

/**
 * Assessment rules of a quiz, includes/Assessment/AssessmentSettings.php.
 *
 * @typedef {Object} AssessmentSettings
 * @property {string}                             kind             Assessment kind, such as `quiz` or `exam`.
 * @property {'immediate'|'after_close'|'manual'} feedback_release When learners see results.
 * @property {boolean}                            released         Results released.
 * @property {string}                             available_from   Opening time, UTC (empty: no limit).
 * @property {string}                             available_until  Closing time, UTC (empty: no limit).
 * @property {number}                             grace_seconds    Grace period.
 * @property {AssessmentSection[]}                sections         Sections.
 * @property {Record<string, number>}             accommodations   Extra seconds by user ID.
 */

/**
 * An editable extra time row.
 *
 * @typedef {Object} AccommodationRow
 * @property {string} userId  Learner user ID.
 * @property {string} minutes Extra minutes.
 */

/** @param {number} value Number. */
const pad = ( value ) => String( value ).padStart( 2, '0' );

/**
 * UTC "2026-10-02 08:00:00" -> local "2026-10-02T16:00" for <input type="datetime-local">.
 * @param {string|null|undefined} utc Server time.
 * @return {string} Input value.
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
 * @param {string} local Input value.
 * @return {string} Server time.
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
 * @param {AssessmentSection[]|null|undefined} sections   Sections.
 * @param {number|string}                      questionId Question ID.
 * @param {number}                             index      Section index, -1 for none.
 * @return {AssessmentSection[]} Updated sections.
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

/**
 * @param {AssessmentSection[]|null|undefined} sections   Sections.
 * @param {number|string}                      questionId Question ID.
 * @return {number} Index of the section holding the question, -1 for none.
 */
export function sectionOf( sections, questionId ) {
	return ( sections || [] ).findIndex( ( section ) =>
		( section.questions || [] )
			.map( Number )
			.includes( Number( questionId ) )
	);
}

/**
 * @param {AssessmentSection[]}          sections   Sections.
 * @param {number|string}                questionId Question ID.
 * @param {number|string|null|undefined} marks      Slot marks; empty clears them.
 * @return {AssessmentSection[]} Updated sections.
 */
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
 * @param {Record<string, number|string>|null|undefined} map Extra seconds by user ID.
 * @return {AccommodationRow[]} Rows.
 */
export function accommodationRows( map ) {
	return Object.entries( map || {} ).map( ( [ userId, seconds ] ) => ( {
		userId: String( userId ),
		minutes: String( Math.round( Number( seconds ) / 60 ) ),
	} ) );
}
/**
 * @param {AccommodationRow[]|null|undefined} rows Rows.
 * @return {Record<string, number>} Extra seconds by user ID.
 */
export function accommodationMap( rows ) {
	/** @type {Record<string, number>} */
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
 * @param {string|null|undefined} hash Location hash.
 * @return {number} Quiz ID, 0 when the route has none.
 */
export function quizIdFromHash( hash ) {
	const match = String( hash || '' ).match( /quiz-edit\/(\d+)/ );
	return match ? Number( match[ 1 ] ) : 0;
}

/**
 * Skill level display helpers.
 *
 * @type {Record<string, string>}
 */
const SHORT = {
	'not-started': '–',
	attempted: 'A',
	familiar: 'F',
	proficient: 'P',
	mastered: 'M',
};
/** @param {string} level Skill level. */
export const levelShort = ( level ) => SHORT[ level ] || '·';
/** @param {string|null|undefined} level Skill level. */
export const levelClass = ( level ) =>
	level ? `ohmylms-skill-${ level }` : '';

/**
 * Authoring syntax for an inline check (rendered and graded by the server).
 * @param {string} uuid Question UUID.
 */
export const inlineShortcode = ( uuid ) =>
	`[ohmylms_question uuid="${ uuid }"]`;
