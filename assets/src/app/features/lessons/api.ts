import { get, post, put } from '../../lib/api';
import { invalid, record } from '../../lib/authoring-validation';
import { parseLesson, parseSummary, savePayload, type Lesson } from './model';

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
