const request = ( options ) => window.wp.apiFetch( options );
const base = '/ohmylms/v1';
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
 * REST calls for curriculum administration. Each accepts an injected request function for tests.
 * @param fetch
 */
export const loadTree = ( fetch = request ) =>
	fetch( { path: `${ base }/curriculum/tree` } );
export const loadItem = ( id, fetch = request ) =>
	fetch( { path: `${ base }/curriculum/items/${ id }` } );
export const createItem = ( data, fetch = request ) =>
	fetch( { path: `${ base }/curriculum/items`, method: 'POST', data } );
export const updateItem = ( id, data, fetch = request ) =>
	fetch( {
		path: `${ base }/curriculum/items/${ id }`,
		method: 'PUT',
		data,
	} );
export const moveItem = ( id, parentId, position, fetch = request ) =>
	fetch( {
		path: `${ base }/curriculum/items/${ id }/move`,
		method: 'POST',
		data:
			position === undefined || position === null
				? { parent_id: parentId }
				: { parent_id: parentId, position },
	} );
/**
 * children: '' (leaf), 'promote' or 'delete'; confirm must be true when the server asks.
 * @param id
 * @param root0
 * @param root0.children
 * @param root0.confirm
 * @param fetch
 */
export const deleteItem = (
	id,
	{ children = '', confirm = false } = {},
	fetch = request
) =>
	fetch( {
		path: `${ base }/curriculum/items/${ id }`,
		method: 'DELETE',
		data: { children, confirm },
	} );
export const addLink = ( id, objectType, objectId, fetch = request ) =>
	fetch( {
		path: `${ base }/curriculum/items/${ id }/links`,
		method: 'POST',
		data: { object_type: objectType, object_id: objectId },
	} );
export const removeLink = ( id, objectType, objectId, fetch = request ) =>
	fetch( {
		path: `${ base }/curriculum/items/${ id }/links`,
		method: 'DELETE',
		data: { object_type: objectType, object_id: objectId },
	} );
export const searchTargets = ( type, search, fetch = request ) =>
	fetch( {
		path: `${ base }/curriculum/link-targets${ query( { type, search } ) }`,
	} );

/**
 * A syllabus's skill groups and skills. Every write answers with the syllabus's fresh outline and the item tree.
 * @param id
 */
const syllabus = ( id ) => `${ base }/curriculum/items/${ id }/syllabus`;
export const loadSyllabus = ( id, fetch = request ) =>
	fetch( { path: syllabus( id ) } );
export const saveSyllabusSettings = ( id, data, fetch = request ) =>
	fetch( { path: `${ syllabus( id ) }/settings`, method: 'PUT', data } );
export async function publishSyllabus( id, fetch = request ) {
	try {
		return await fetch( {
			path: `${ syllabus( id ) }/publish`,
			method: 'POST',
		} );
	} catch ( cause ) {
		if ( Array.isArray( cause?.data?.errors ) ) {
			cause.message = cause.data.errors.join( ' ' );
		}
		throw cause;
	}
}
export const addGroup = ( id, data, fetch = request ) =>
	fetch( { path: `${ syllabus( id ) }/groups`, method: 'POST', data } );
export const updateGroup = ( id, group, data, fetch = request ) =>
	fetch( {
		path: `${ syllabus( id ) }/groups/${ group }`,
		method: 'PUT',
		data,
	} );
export const moveGroup = ( id, group, itemId, position, fetch = request ) =>
	fetch( {
		path: `${ syllabus( id ) }/groups/${ group }/move`,
		method: 'POST',
		data: { item_id: itemId, position },
	} );
export const deleteGroup = ( id, group, confirm = false, fetch = request ) =>
	fetch( {
		path: `${ syllabus( id ) }/groups/${ group }`,
		method: 'DELETE',
		data: { confirm },
	} );
/**
 * data is {name, code, description}, or {term_id} to place an existing library skill.
 * @param id
 * @param group
 * @param data
 * @param fetch
 */
export const addSkill = ( id, group, data, fetch = request ) =>
	fetch( {
		path: `${ syllabus( id ) }/groups/${ group }/skills`,
		method: 'POST',
		data,
	} );
export const updateSkill = ( id, term, data, fetch = request ) =>
	fetch( {
		path: `${ syllabus( id ) }/skills/${ term }`,
		method: 'PUT',
		data,
	} );
export const removeSkill = ( id, group, term, fetch = request ) =>
	fetch( {
		path: `${ syllabus( id ) }/groups/${ group }/skills/${ term }`,
		method: 'DELETE',
	} );
export const moveSkill = (
	id,
	group,
	term,
	toGroup,
	position,
	fetch = request
) =>
	fetch( {
		path: `${ syllabus( id ) }/groups/${ group }/skills/${ term }/move`,
		method: 'POST',
		data: { group_id: toGroup, position },
	} );
/**
 * Check (dryRun) or apply rows read from a CSV.
 * @param id
 * @param rows
 * @param dryRun
 * @param fetch
 */
export const importRows = ( id, rows, dryRun, fetch = request ) =>
	fetch( {
		path: `${ syllabus( id ) }/import`,
		method: 'POST',
		data: { rows, dry_run: dryRun },
	} );
/**
 * A syllabus is also a course: give it one if it has none and make the course match. Answers like a write.
 * @param id
 * @param fetch
 */
export const ensureCourse = ( id, fetch = request ) =>
	fetch( { path: `${ syllabus( id ) }/course`, method: 'POST' } );

/**
 * Choose which skills the course requires and at what target: `skills` is `[{term_id, required?, target?}]`. Answers like a write.
 * @param id
 * @param skills
 * @param fetch
 */
export const setCourseSkills = ( id, skills, fetch = request ) =>
	fetch( {
		path: `${ syllabus( id ) }/course/skills`,
		method: 'PUT',
		data: { skills },
	} );

/** What a skill owns. Lessons are tagged to the skill, questions are mapped to it in the question bank. */
const skillBase = `${ base }/skills`;
export const loadSkill = ( id, fetch = request ) =>
	fetch( { path: `${ skillBase }/${ id }` } );
/**
 * Replace the set of lessons tagged to a skill.
 * @param id
 * @param lessonIds
 * @param fetch
 */
export const setSkillLessons = ( id, lessonIds, fetch = request ) =>
	fetch( {
		path: `${ skillBase }/${ id }/lessons`,
		method: 'PUT',
		data: { lesson_ids: lessonIds },
	} );
/**
 * Lessons by search text, or titles for known IDs: `[{id, title}]`.
 * @param root0
 * @param root0.search
 * @param root0.include
 * @param fetch
 */
export const lessonTargets = (
	{ search = '', include = [] } = {},
	fetch = request
) =>
	fetch( {
		path: `${ skillBase }/link-targets${ query( { type: 'lesson', search, include: include.join( ',' ) } ) }`,
	} );
/**
 * The questions mapped to a skill: `{items, total}`.
 * @param id
 * @param perPage
 * @param fetch
 */
export const skillQuestions = ( id, perPage = 8, fetch = request ) =>
	fetch( {
		path: `${ base }/question-bank${ query( { skill: id, per_page: perPage } ) }`,
	} );

export const saveMapping = ( data, fetch = request ) =>
	fetch( { path: `${ base }/skill-mappings`, method: 'PUT', data } );
export const removeMapping = ( specificId, sharedId, fetch = request ) =>
	fetch( {
		path: `${ base }/skill-mappings`,
		method: 'DELETE',
		data: { specific_id: specificId, shared_id: sharedId },
	} );
