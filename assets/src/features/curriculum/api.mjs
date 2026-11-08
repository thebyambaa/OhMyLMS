import { request } from '../rest.mjs';

const base = '/ohmylms/v1';

/**
 * Curriculum reads of the native skill picker. Each accepts an injected request function for tests.
 * @param {typeof request} [fetch]
 */
export const loadTree = ( fetch = request ) =>
	fetch( { path: `${ base }/curriculum/tree` } );

/**
 * The skills one lesson (or other content) teaches, with the revision a save must quote.
 * @param {string}         kind
 * @param {number}         id
 * @param {typeof request} [fetch]
 */
export const loadContentSkills = ( kind, id, fetch = request ) =>
	fetch( { path: `${ base }/skill-workspace/content/${ kind }/${ id }` } );

/**
 * Change the skills of one piece of content: the difference between what was loaded and what is wanted.
 * @param {{content: {kind: string, id: number}, skills: {id: number}[], revision: string}} current Links as loaded.
 * @param {number[]}                                                                        wanted  Skill IDs the content should have.
 * @param {typeof request}                                                                  [fetch]
 */
export const saveContentSkills = ( current, wanted, fetch = request ) => {
	const have = current.skills.map( ( skill ) => skill.id );
	return fetch( {
		path: `${ base }/skill-workspace/content/${ current.content.kind }/${ current.content.id }`,
		method: 'PUT',
		data: {
			add: wanted.filter( ( id ) => ! have.includes( id ) ),
			remove: have.filter( ( id ) => ! wanted.includes( id ) ),
			expected_revision: current.revision,
		},
	} );
};

/**
 * A syllabus's skill groups and skills.
 * @param {number}         id
 * @param {typeof request} [fetch]
 */
export const loadSyllabus = ( id, fetch = request ) =>
	fetch( { path: `${ base }/curriculum/items/${ id }/syllabus` } );
