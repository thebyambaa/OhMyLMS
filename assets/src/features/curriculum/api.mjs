import { request } from '../rest.mjs';

const base = '/ohmylms/v1';

/**
 * Curriculum reads of the native skill picker. Each accepts an injected request function for tests.
 * @param fetch
 */
export const loadTree = ( fetch = request ) =>
	fetch( { path: `${ base }/curriculum/tree` } );

/**
 * A syllabus's skill groups and skills.
 * @param id
 * @param fetch
 */
export const loadSyllabus = ( id, fetch = request ) =>
	fetch( { path: `${ base }/curriculum/items/${ id }/syllabus` } );
