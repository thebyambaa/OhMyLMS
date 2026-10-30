import { del, get, post, put } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	invalidResponse,
	parseCourseRef,
	parseMember,
	parseMembership,
	parseMembershipDetail,
	parseOptions,
	positiveId,
	record,
} from './model';
import type { MembershipInput } from './types';

const BASE = '/ohmylms/v1/memberships';
const itemPath = ( id: number ) => `${ BASE }/${ positiveId( id ) }`;

/** Rows per page in the plan list. */
export const PER_PAGE = 20;

/** Rows per page in the member list. */
export const MEMBERS_PER_PAGE = 20;

/** Courses per page in the course search. */
export const COURSES_PER_PAGE = 10;

/**
 * List plans, newest first.
 *
 * @param page   Page number, from 1.
 * @param search Name search; empty for all.
 * @param status `all`, `draft` or `publish`.
 * @param signal Cancels the request.
 * @return Validated plans and pagination totals.
 */
export function listMemberships(
	page: number,
	search: string,
	status: string,
	signal?: AbortSignal
) {
	return getCollection( BASE, parseMembership, {
		signal,
		query: {
			page,
			per_page: PER_PAGE,
			search,
			status,
			orderby: 'created_at',
			order: 'desc',
		},
	} );
}

/**
 * Read one plan with its courses and rules.
 *
 * @param id     Plan id.
 * @param signal Cancels the request.
 * @return The validated plan.
 */
export async function getMembership( id: number, signal?: AbortSignal ) {
	return parseMembershipDetail(
		await get< unknown >( itemPath( id ), {
			signal,
			query: { context: 'edit' },
		} )
	);
}

/**
 * Read the server's choices, currency settings and limits.
 *
 * @param signal Cancels the request.
 * @return Validated options.
 */
export async function getOptions( signal?: AbortSignal ) {
	return parseOptions(
		await get< unknown >( `${ BASE }/options`, { signal } )
	);
}

/**
 * Create a draft plan with only a name.
 *
 * @param name   Plan name.
 * @param signal Cancels the request.
 * @return The created plan.
 */
export async function createMembership( name: string, signal?: AbortSignal ) {
	return parseMembershipDetail(
		await post< unknown >( BASE, { name: name.trim() }, { signal } )
	);
}

/**
 * Save the editable fields of a plan.
 *
 * @param id     Plan id.
 * @param input  Validated payload.
 * @param signal Cancels the request.
 * @return The saved plan as the server stored it.
 */
export async function updateMembership(
	id: number,
	input: MembershipInput,
	signal?: AbortSignal
) {
	return parseMembershipDetail(
		await put< unknown >( itemPath( id ), input, { signal } )
	);
}

/**
 * Permanently delete a plan (refused while it has active members).
 *
 * @param id     Plan id.
 * @param signal Cancels the request.
 */
export async function deleteMembership( id: number, signal?: AbortSignal ) {
	const response = record(
		await del< unknown >( itemPath( id ), { signal } )
	);
	if ( true !== response.deleted ) {
		throw invalidResponse();
	}
}

/**
 * Duplicate a plan as a draft without members.
 *
 * @param id     Plan to copy.
 * @param signal Cancels the request.
 * @return The new plan.
 */
export async function duplicateMembership( id: number, signal?: AbortSignal ) {
	return parseMembershipDetail(
		await post< unknown >( `${ itemPath( id ) }/duplicate`, undefined, {
			signal,
		} )
	);
}

/**
 * List the members of a plan (read only).
 *
 * @param id     Plan id.
 * @param page   Page number, from 1.
 * @param signal Cancels the request.
 * @return Validated members and totals.
 */
export function listMembers( id: number, page: number, signal?: AbortSignal ) {
	return getCollection( `${ itemPath( id ) }/members`, parseMember, {
		signal,
		query: { page, per_page: MEMBERS_PER_PAGE },
	} );
}

/**
 * Search courses that can be added to a plan.
 *
 * @param search Title search.
 * @param page   Page number, from 1.
 * @param signal Cancels the request.
 * @return Validated courses and totals.
 */
export function listCourses(
	search: string,
	page: number,
	signal?: AbortSignal
) {
	return getCollection( `${ BASE }/courses`, parseCourseRef, {
		signal,
		query: { search, page, per_page: COURSES_PER_PAGE },
	} );
}
