import { del, get, patch, post } from '../../../lib/api';
import { getCollection } from '../../../lib/api/collection';
import {
	COHORT_PER_PAGE,
	parseCandidate,
	parseChoice,
	parseCohort,
	parseMember,
	parseSync,
	invalidCohortResponse,
	type CohortScope,
} from './model';

const BASE = '/ohmylms/v1/cohorts';
function asList( value: unknown ): unknown[] {
	if ( ! Array.isArray( value ) ) {
		throw invalidCohortResponse();
	}
	return value;
}

const route = ( id: number ) =>
	`${ BASE }/${ Math.max( 1, Math.trunc( id ) ) }`;

export interface CohortQuery {
	page: number;
	search: string;
	status: 'any' | 'active' | 'archived';
}

export function listCohorts( query: CohortQuery, signal?: AbortSignal ) {
	return getCollection( BASE, parseCohort, {
		signal,
		query: {
			page: query.page,
			per_page: COHORT_PER_PAGE,
			search: query.search || undefined,
			status: query.status,
		},
	} );
}
export async function getCohort( id: number, signal?: AbortSignal ) {
	return parseCohort( await get< unknown >( route( id ), { signal } ) );
}
export async function createCohort(
	body: Record< string, unknown >,
	signal?: AbortSignal
) {
	return parseCohort( await post< unknown >( BASE, body, { signal } ) );
}
export async function updateCohort(
	id: number,
	body: Record< string, unknown >,
	signal?: AbortSignal
) {
	return parseCohort(
		await patch< unknown >( route( id ), body, { signal } )
	);
}
export async function deleteCohort( id: number, signal?: AbortSignal ) {
	await del< unknown >( route( id ), { signal } );
}
export function listMembers(
	id: number,
	page: number,
	search: string,
	signal?: AbortSignal
) {
	return getCollection( `${ route( id ) }/members`, parseMember, {
		signal,
		query: { page, per_page: COHORT_PER_PAGE, search: search || undefined },
	} );
}
export async function addMember(
	id: number,
	userId: number,
	signal?: AbortSignal
) {
	return parseCohort(
		await post< unknown >(
			`${ route( id ) }/members`,
			{ user_id: userId },
			{ signal }
		)
	);
}
export async function removeMember(
	id: number,
	userId: number,
	signal?: AbortSignal
) {
	return parseCohort(
		await del< unknown >( `${ route( id ) }/members/${ userId }`, {
			signal,
		} )
	);
}
export async function syncCohort( id: number, signal?: AbortSignal ) {
	return parseSync(
		await post< unknown >(
			`${ route( id ) }/sync`,
			{ confirm: true },
			{ signal }
		)
	);
}
export async function searchTargets(
	scope: CohortScope,
	search: string,
	signal?: AbortSignal
) {
	const rows = await get< unknown >( `${ BASE }/targets`, {
		signal,
		query: { scope, search },
	} );
	return asList( rows ).map( parseChoice );
}
export async function searchCandidates(
	id: number,
	search: string,
	signal?: AbortSignal
) {
	const rows = await get< unknown >( `${ route( id ) }/candidates`, {
		signal,
		query: { search },
	} );
	return asList( rows ).map( parseCandidate );
}
