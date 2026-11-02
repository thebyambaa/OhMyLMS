import { get, post, put } from '../../lib/api';
import { invalid, record } from '../../lib/authoring-validation';
import {
	objectivesPayload,
	parseLesson,
	parseObjectiveRef,
	parseObjectives,
	parseOptionPage,
	parseSkill,
	parseSummary,
	parseSyllabus,
	savePayload,
	type Lesson,
	type LessonObjectives,
} from './model';

const BASE = '/ohmylms/v1/authoring/lessons';
export async function listLessons(
	page: number,
	search: string,
	signal: AbortSignal
) {
	const row = record(
		await get< unknown >( BASE, { query: { page, search }, signal } )
	);
	if (
		! Array.isArray( row.items ) ||
		! Number.isSafeInteger( row.pages ) ||
		Number( row.pages ) < 0
	) {
		return invalid();
	}
	return { items: row.items.map( parseSummary ), pages: Number( row.pages ) };
}
export async function createLesson(
	title: string,
	requestId: string,
	signal: AbortSignal
) {
	return parseLesson(
		await post< unknown >(
			BASE,
			{ title, request_id: requestId },
			{ signal }
		)
	);
}
export async function readLesson( id: number, signal: AbortSignal ) {
	const result = parseLesson(
		await get< unknown >( `${ BASE }/${ id }`, { signal } )
	);
	return result.id === id ? result : invalid();
}
export async function saveLesson( draft: Lesson, signal: AbortSignal ) {
	const result = parseLesson(
		await put< unknown >( `${ BASE }/${ draft.id }`, savePayload( draft ), {
			signal,
		} )
	);
	return result.id === draft.id ? result : invalid();
}
export async function publishLesson( draft: Lesson, signal: AbortSignal ) {
	const result = parseLesson(
		await post< unknown >(
			`${ BASE }/${ draft.id }/publish`,
			{ revision: draft.revision, confirmed: true },
			{ signal }
		)
	);
	return result.id === draft.id ? result : invalid();
}

/**
 * Linked skills, syllabus objective mappings and the placing courses' objectives of a lesson.
 *
 * @param id     Lesson ID.
 * @param signal Cancels the request.
 * @return The validated objectives.
 */
export async function readObjectives( id: number, signal: AbortSignal ) {
	const result = parseObjectives(
		await get< unknown >( `${ BASE }/${ id }/objectives`, { signal } )
	);
	return result.id === id ? result : invalid();
}

/**
 * Replace the explicit skills and the mappings under the read revision (409 when it changed).
 *
 * @param state  Edited objectives.
 * @param signal Cancels the request.
 * @return The refreshed objectives.
 */
export async function saveObjectives(
	state: LessonObjectives,
	signal: AbortSignal
) {
	const result = parseObjectives(
		await put< unknown >(
			`${ BASE }/${ state.id }/objectives`,
			objectivesPayload( state ),
			{ signal }
		)
	);
	return result.id === state.id ? result : invalid();
}

const options = ( id: number ) => `${ BASE }/${ id }/objective-options`;

/**
 * Teaching syllabuses to browse.
 *
 * @param id     Lesson ID.
 * @param signal Cancels the request.
 * @return The syllabuses.
 */
export async function listSyllabuses( id: number, signal: AbortSignal ) {
	return parseOptionPage(
		await get< unknown >( options( id ), {
			signal,
			query: { type: 'syllabuses' },
		} ),
		parseSyllabus
	);
}

/**
 * Objectives of one syllabus, searched and paged by 20.
 *
 * @param id         Lesson ID.
 * @param syllabusId Syllabus ID.
 * @param search     Search text.
 * @param signal     Cancels the request.
 * @return A page of objectives.
 */
export async function searchObjectives(
	id: number,
	syllabusId: number,
	search: string,
	signal: AbortSignal
) {
	return parseOptionPage(
		await get< unknown >( options( id ), {
			signal,
			query: { type: 'objectives', syllabus_id: syllabusId, search },
		} ),
		parseObjectiveRef
	);
}

/**
 * Skills of the library, searched and paged by 20.
 *
 * @param id     Lesson ID.
 * @param search Search text.
 * @param signal Cancels the request.
 * @return A page of skills.
 */
export async function searchSkills(
	id: number,
	search: string,
	signal: AbortSignal
) {
	return parseOptionPage(
		await get< unknown >( options( id ), {
			signal,
			query: { type: 'skills', search },
		} ),
		parseSkill
	);
}
