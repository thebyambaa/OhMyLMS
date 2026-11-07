import { del, get, put } from '../../lib/api';
import { createAssignment } from '../assignments/public';
import { createQuiz } from '../quizzes/public';
import { createLesson } from '../lessons';
import { parseOutline, type Outline } from './model';
import {
	invalid,
	positiveId,
	record,
	text,
} from '../../lib/authoring-validation';

const BASE = '/ohmylms/v1';

/** Activities an outline unit can hold through the resource picker. */
export type ResourceType = 'lesson' | 'quiz' | 'assignment';

export async function readOutline( id: number, signal?: AbortSignal ) {
	const result = parseOutline(
		await get< unknown >( `${ BASE }/courses/${ id }/structure`, {
			signal,
		} )
	);
	return result.course_id === id ? result : invalid();
}
export async function saveOutline(
	outline: Outline,
	data: unknown,
	signal?: AbortSignal
) {
	const result = parseOutline(
		await put< unknown >(
			`${ BASE }/courses/${ outline.course_id }/structure`,
			data,
			{
				signal,
				headers: { 'X-OhMyLMS-Structure-Revision': outline.revision },
			}
		)
	);
	return result.course_id === outline.course_id ? result : invalid();
}

/**
 * Delete a saved unit with the outline's read token. The server refuses units that the published
 * program or native placements still use; draft activities of the unit become unplaced.
 *
 * @param outline Saved outline the unit belongs to.
 * @param unit    Saved unit ID.
 * @param signal  Cancels the request.
 * @return The fresh outline.
 */
export async function deleteUnit(
	outline: Outline,
	unit: number,
	signal?: AbortSignal
) {
	const result = parseOutline(
		await del< unknown >(
			`${ BASE }/courses/${
				outline.course_id
			}/structure/units/${ positiveId( unit ) }`,
			{
				signal,
				headers: { 'X-OhMyLMS-Structure-Revision': outline.revision },
			}
		)
	);
	return result.course_id === outline.course_id ? result : invalid();
}

/**
 * Search editable lessons, quizzes or assignments not yet placed in the course.
 *
 * @param id          Course ID.
 * @param type        Resource type.
 * @param search      Title search.
 * @param page        Page number, from 1.
 * @param signal      Cancels the request.
 * @param bank        Content Bank folder and tag filters (term IDs as text).
 * @param bank.folder Folder ID.
 * @param bank.tag    Tag ID.
 * @return Validated resources and page count.
 */
export async function findResources(
	id: number,
	type: ResourceType,
	search: string,
	page: number,
	signal?: AbortSignal,
	bank: { folder?: string; tag?: string } = {}
) {
	const row = record(
		await get< unknown >( `${ BASE }/courses/${ id }/structure/resources`, {
			query: {
				type,
				search,
				page,
				bank_folder: bank.folder || undefined,
				bank_tag: bank.tag || undefined,
			},
			signal,
		} )
	);
	if (
		! Array.isArray( row.items ) ||
		! Number.isSafeInteger( row.pages ) ||
		Number( row.pages ) < 0
	) {
		return invalid();
	}
	return {
		pages: Number( row.pages ),
		items: row.items.map( ( value ) => {
			const item = record( value );
			return { id: positiveId( item.id ), title: text( item.title ) };
		} ),
	};
}

/**
 * Create a named draft lesson, quiz or assignment through its own API. Placement is saved with the outline.
 *
 * @param type      Resource type.
 * @param title     Title.
 * @param signal    Cancels the request.
 * @param requestId Idempotency token required when creating a lesson.
 * @return The new resource.
 */
export async function createResource(
	type: ResourceType,
	title: string,
	signal?: AbortSignal,
	requestId?: string
) {
	if ( 'lesson' === type ) {
		const lesson = await createLesson(
			title,
			requestId ?? invalid(),
			signal ?? new AbortController().signal
		);
		return { id: lesson.id, title: lesson.title };
	}
	const item =
		'quiz' === type
			? await createQuiz( title, signal )
			: await createAssignment( title, signal );
	return { id: item.id, title: item.title };
}
