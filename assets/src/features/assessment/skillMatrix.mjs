/**
 * Pure helpers for the skill matrix: Khan mastery levels, unit groups and the report path.
 * The server decides each cell's level; these only name, group and shape it for display.
 */

/** Lowest to highest, as Performance Levels on the server. */
export const MATRIX_LEVELS = [
	'not-started',
	'attempted',
	'familiar',
	'proficient',
	'mastered',
];

const SHORT = {
	'not-started': '–',
	attempted: 'A',
	familiar: 'F',
	proficient: 'P',
	mastered: 'M',
};

/** Fallback names; the server's translated labels win when it sends them. */
const NAMES = {
	'not-started': 'Not started',
	attempted: 'Attempted',
	familiar: 'Familiar',
	proficient: 'Proficient',
	mastered: 'Mastered',
};

const known = ( key ) =>
	MATRIX_LEVELS.includes( key ) ? key : 'not-started';

/**
 * One letter so colour is never the only signal.
 * @param key
 */
export const levelLetter = ( key ) => SHORT[ known( key ) ];

/**
 * CSS class for a display level.
 * @param key
 */
export const levelCssClass = ( key ) => `ohmylms-mx-${ known( key ) }`;

/**
 * Report path with an optional course, class and unit.
 * @param root0
 * @param root0.courseId
 * @param root0.classId
 * @param root0.chapterId
 */
export function matrixPath( { courseId = 0, classId = 0, chapterId = 0 } ) {
	const query = [];
	if ( Number( classId ) ) {
		query.push( `class_id=${ Number( classId ) }` );
	}
	if ( Number( courseId ) ) {
		query.push( `course_id=${ Number( courseId ) }` );
	}
	if ( Number( chapterId ) ) {
		query.push( `chapter_id=${ Number( chapterId ) }` );
	}
	return `reports/skills${ query.length ? `?${ query.join( '&' ) }` : '' }`;
}

/**
 * Skills grouped under unit headers, in the server's order. Falls back to one headerless group
 * for a report from an older server that sends no groups.
 * @param matrix
 */
export function unitColumns( matrix ) {
	const byId = new Map(
		( matrix?.skills || [] ).map( ( skill ) => [ skill.id, skill ] )
	);
	if ( ! Array.isArray( matrix?.groups ) ) {
		return matrix?.skills?.length
			? [ { id: 0, name: '', skills: matrix.skills } ]
			: [];
	}
	return matrix.groups
		.map( ( group ) => ( {
			id: group.id,
			name: group.name,
			skills: group.skills
				.map( ( id ) => byId.get( id ) )
				.filter( Boolean ),
		} ) )
		.filter( ( group ) => group.skills.length );
}

/**
 * What to draw for one learner x skill cell.
 * @param cell
 * @param labels
 */
export function cellView( cell, labels = {} ) {
	const key = known( cell?.display );
	return {
		key,
		letter: levelLetter( key ),
		label: labels[ key ] || NAMES[ key ],
		reviewDue: Boolean( cell?.review_due ),
		evidence: Number( cell?.evidence ) || 0,
		independent: Number( cell?.independent_correct ) || 0,
	};
}

/**
 * Display levels for the legend, highest first.
 * @param labels
 */
export const legendLevels = ( labels = {} ) =>
	[ ...MATRIX_LEVELS ].reverse().map( ( key ) => ( {
		key,
		letter: levelLetter( key ),
		label: labels[ key ] || NAMES[ key ],
	} ) );

/**
 * Class average cell for one skill, or null when the group is too small to show.
 * @param matrix
 * @param skillId
 */
export const averageKey = ( matrix, skillId ) =>
	matrix?.average?.[ skillId ]?.key || null;
