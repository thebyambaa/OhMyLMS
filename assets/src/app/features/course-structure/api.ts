import { get, post, put } from '../../lib/api';
import { parseCourse, parseOutline, type Outline } from './model';
import {
	invalid,
	positiveId,
	record,
	text,
} from '../../lib/authoring-validation';

const BASE = '/ohmylms/v1';
export async function listCourses(
	page: number,
	search: string,
	signal?: AbortSignal
) {
	const row = record(
		await get< unknown >( `${ BASE }/authoring/courses`, {
			query: { page, search },
			signal,
		} )
	);
	if (
		! Array.isArray( row.items ) ||
		! Number.isSafeInteger( row.pages ) ||
		Number( row.pages ) < 0 ||
		! Number.isSafeInteger( row.total ) ||
		Number( row.total ) < 0
	) {
		return invalid();
	}
	return {
		items: row.items.map( parseCourse ),
		pages: Number( row.pages ),
		total: Number( row.total ),
	};
}
export async function createCourse( title: string, signal?: AbortSignal ) {
	return parseCourse(
		await post< unknown >(
			`${ BASE }/authoring/courses`,
			{ title },
			{ signal }
		)
	);
}
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
export async function findLessons(
	id: number,
	search: string,
	page: number,
	signal?: AbortSignal
) {
	const row = record(
		await get< unknown >( `${ BASE }/courses/${ id }/structure/resources`, {
			query: { type: 'lesson', search, page },
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
