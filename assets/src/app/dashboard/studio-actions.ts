import { api, coreApi } from './api';

/*
 * Create, rename and remove for the Studio. Each call goes to an existing plugin endpoint, so the
 * checks wp-admin relies on still apply: permissions, enrolled students, quiz attempts, units that
 * still hold items, and units used by a published program. Removing moves a course or quiz to the
 * WordPress trash; it does not delete permanently.
 */

const REVISION = 'X-OhMyLMS-Structure-Revision';

/**
 * The course structure changes under optimistic locking: read the revision, then send it back.
 *
 * @param courseId Course.
 */
async function revisionOf( courseId: number ) {
	const body = await coreApi< { structure_revision?: string } >(
		`/courses/${ courseId }/structure`
	);
	return body.structure_revision || '';
}

/**
 * Create a draft course.
 *
 * @param title Course title.
 */
export function createCourse( title: string ) {
	return coreApi< { id: number } >( '/courses', {
		method: 'POST',
		body: JSON.stringify( { title } ),
	} );
}

/**
 * Rename a course.
 *
 * @param id    Course.
 * @param title New title.
 */
export function renameCourse( id: number, title: string ) {
	return coreApi( `/courses/${ id }`, {
		method: 'POST',
		body: JSON.stringify( { title } ),
	} );
}

/**
 * Move a course to the trash.
 *
 * @param id Course.
 */
export function trashCourse( id: number ) {
	return coreApi( `/courses/${ id }`, { method: 'DELETE' } );
}

/**
 * Rename a unit.
 *
 * @param courseId Course holding the unit.
 * @param unitId   Unit.
 * @param name     New name.
 */
export async function renameUnit(
	courseId: number,
	unitId: number,
	name: string
) {
	return coreApi( `/chapters/${ unitId }`, {
		method: 'POST',
		headers: { [ REVISION ]: await revisionOf( courseId ) },
		body: JSON.stringify( { name } ),
	} );
}

/**
 * Delete an empty unit. The server refuses units that still hold items or belong to a published program.
 *
 * @param courseId Course holding the unit.
 * @param unitId   Unit.
 */
export async function deleteUnit( courseId: number, unitId: number ) {
	return coreApi( `/courses/${ courseId }/structure/units/${ unitId }`, {
		method: 'DELETE',
		headers: { [ REVISION ]: await revisionOf( courseId ) },
	} );
}

/**
 * Take an item out of a unit. The lesson or quiz itself is kept.
 *
 * @param courseId Course holding the unit.
 * @param unitId   Unit.
 * @param itemId   Lesson or quiz.
 */
export async function removeItem(
	courseId: number,
	unitId: number,
	itemId: number
) {
	return coreApi( `/chapters/${ unitId }/content/${ itemId }`, {
		method: 'DELETE',
		headers: { [ REVISION ]: await revisionOf( courseId ) },
	} );
}

/**
 * Rename a lesson or quiz.
 *
 * @param kind  `quiz` or anything else for a lesson.
 * @param id    Lesson or quiz.
 * @param title New title.
 */
export function renameItem( kind: string, id: number, title: string ) {
	return kind === 'quiz'
		? renameQuiz( id, title )
		: coreApi( `/lessons/${ id }`, {
				method: 'POST',
				body: JSON.stringify( { name: title } ),
			} );
}

/**
 * Create a draft quiz.
 *
 * @param title Quiz title.
 */
export function createQuiz( title: string ) {
	return coreApi< { id: number } >( '/quizzes', {
		method: 'POST',
		body: JSON.stringify( { title } ),
	} );
}

/**
 * Rename a quiz.
 *
 * @param id    Quiz.
 * @param title New title.
 */
export function renameQuiz( id: number, title: string ) {
	return coreApi( `/quizzes/${ id }`, {
		method: 'POST',
		body: JSON.stringify( { title } ),
	} );
}

/**
 * Move a quiz to the trash. Quizzes are never deleted permanently from here.
 *
 * @param id Quiz.
 */
export function trashQuiz( id: number ) {
	return coreApi( `/quizzes/${ id }`, { method: 'DELETE' } );
}

/**
 * Remove a saved question from a quiz. The question list carries its own revision.
 *
 * @param quizId     Quiz.
 * @param questionId Question.
 */
export async function deleteQuestion( quizId: number, questionId: number ) {
	const list = await coreApi< { revision: string } >(
		`/quizzes/${ quizId }/questions`
	);
	return coreApi(
		`/quizzes/${ quizId }/questions/${ questionId }?revision=${ encodeURIComponent(
			list.revision
		) }`,
		{ method: 'DELETE' }
	);
}

/**
 * Rename a skill, keeping its parent.
 *
 * @param id     Skill term.
 * @param name   New name.
 * @param parent Parent term, 0 for none.
 */
export function renameSkill( id: number, name: string, parent: number ) {
	return api( '/studio/skills', {
		method: 'POST',
		body: JSON.stringify( { id, name, parent } ),
	} );
}

/**
 * Delete a skill. The server allows this for administrators only.
 *
 * @param id Skill term.
 */
export function deleteSkill( id: number ) {
	return coreApi( `/skills/${ id }`, { method: 'DELETE' } );
}
