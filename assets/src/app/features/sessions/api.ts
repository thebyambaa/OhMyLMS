import { del, get, patch, post } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	buildQuery,
	invalidResponse,
	parseAttendee,
	parseCourseChoice,
	parseDeleted,
	parseProvider,
	parseSession,
	parseSummary,
	positiveId,
	toPayload,
} from './model';
import type { SessionDraft, SessionQuery } from './types';

const BASE = '/ohmylms/v1/sessions';
const route = ( id: number ) => `${ BASE }/${ positiveId( id ) }`;

function list< T >( value: unknown, parse: ( item: unknown ) => T ): T[] {
	if ( ! Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value.map( parse );
}

export function listSessions( query: SessionQuery, signal?: AbortSignal ) {
	return getCollection( BASE, parseSummary, {
		signal,
		query: buildQuery( query ),
	} );
}
export async function getSession( id: number, signal?: AbortSignal ) {
	return parseSession( await get< unknown >( route( id ), { signal } ) );
}
export async function createSession(
	draft: SessionDraft,
	signal?: AbortSignal
) {
	return parseSession(
		await post< unknown >( BASE, toPayload( draft, false ), { signal } )
	);
}
export async function updateSession(
	id: number,
	draft: SessionDraft,
	signal?: AbortSignal
) {
	return parseSession(
		await patch< unknown >( route( id ), toPayload( draft, true ), {
			signal,
		} )
	);
}
export async function deleteSession( id: number, signal?: AbortSignal ) {
	return parseDeleted( await del< unknown >( route( id ), { signal } ) );
}
export function listAttendees(
	id: number,
	page: number,
	signal?: AbortSignal
) {
	return getCollection( `${ route( id ) }/attendees`, parseAttendee, {
		signal,
		query: { page, per_page: 20 },
	} );
}
export async function getProviders( signal?: AbortSignal ) {
	return list(
		await get< unknown >( `${ BASE }/providers`, { signal } ),
		parseProvider
	);
}
export async function searchCourses( search: string, signal?: AbortSignal ) {
	const term = search.trim();
	return list(
		await get< unknown >( `${ BASE }/courses`, {
			signal,
			query: term ? { search: term } : {},
		} ),
		parseCourseChoice
	);
}
