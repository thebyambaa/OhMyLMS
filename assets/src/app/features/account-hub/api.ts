import { del, get, post, put } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	confirmed,
	parseChildren,
	parseClass,
	parseInvitation,
	parseMember,
	parseParent,
	parsePerson,
	parseSchool,
	parseSchoolDetail,
	parseTeacher,
	positiveId,
} from './model';
import { PER_PAGE, SEARCH_PER_PAGE } from './paging';
import type {
	ClassInput,
	ClassRole,
	NewClassInput,
	SchoolInput,
	YearInput,
} from './types';

const BASE = '/ohmylms/v1/account-hub';
const school = ( id: number ) => `${ BASE }/schools/${ positiveId( id ) }`;
const classPath = ( id: number ) => `${ BASE }/classes/${ positiveId( id ) }`;
const parent = ( id: number ) => `${ BASE }/parents/${ positiveId( id ) }`;

export { PER_PAGE, SEARCH_PER_PAGE };

/** Class status filter values. */
export type ClassFilter = 'all' | 'active' | 'archived';

/**
 * List schools by name.
 *
 * @param page   Page number, from 1.
 * @param search Name search.
 * @param signal Cancels the request.
 * @return Validated schools and totals.
 */
export function listSchools(
	page: number,
	search: string,
	signal?: AbortSignal
) {
	return getCollection( `${ BASE }/schools`, parseSchool, {
		signal,
		query: { page, per_page: PER_PAGE, search },
	} );
}

/**
 * Read one school with its academic years.
 *
 * @param id     School id.
 * @param signal Cancels the request.
 * @return The school.
 */
export async function getSchool( id: number, signal?: AbortSignal ) {
	return parseSchoolDetail(
		await get< unknown >( school( id ), { signal } )
	);
}

/**
 * Create a school.
 *
 * @param input  Name and time zone.
 * @param signal Cancels the request.
 * @return The created school.
 */
export async function createSchool( input: SchoolInput, signal?: AbortSignal ) {
	return parseSchoolDetail(
		await post< unknown >(
			`${ BASE }/schools`,
			{ name: input.name.trim(), timezone: input.timezone.trim() },
			{ signal }
		)
	);
}

/**
 * Save a school. A stale `version` is rejected with 409.
 *
 * @param id      School id.
 * @param input   Name and time zone.
 * @param version Version the form was loaded from.
 * @param signal  Cancels the request.
 * @return The saved school.
 */
export async function updateSchool(
	id: number,
	input: SchoolInput,
	version: string,
	signal?: AbortSignal
) {
	return parseSchoolDetail(
		await put< unknown >(
			school( id ),
			{
				name: input.name.trim(),
				timezone: input.timezone.trim(),
				version,
			},
			{ signal }
		)
	);
}

/**
 * Add an academic year to a school.
 *
 * @param id     School id.
 * @param input  Label and dates.
 * @param signal Cancels the request.
 * @return The school with its years.
 */
export async function createYear(
	id: number,
	input: YearInput,
	signal?: AbortSignal
) {
	return parseSchoolDetail(
		await post< unknown >(
			`${ school( id ) }/years`,
			{ ...input, label: input.label.trim() },
			{ signal }
		)
	);
}

/**
 * List one school's students (roster search).
 *
 * @param id     School id.
 * @param search Name search.
 * @param signal Cancels the request.
 * @return Validated people and totals.
 */
export function listSchoolStudents(
	id: number,
	search: string,
	signal?: AbortSignal
) {
	return getCollection( `${ school( id ) }/students`, parsePerson, {
		signal,
		query: { page: 1, per_page: SEARCH_PER_PAGE, search },
	} );
}

/**
 * List classes, optionally of one school.
 *
 * @param page     Page number, from 1.
 * @param search   Name search.
 * @param status   Status filter.
 * @param schoolId Only this school, when given.
 * @param signal   Cancels the request.
 * @return Validated classes and totals.
 */
export function listClasses(
	page: number,
	search: string,
	status: ClassFilter,
	schoolId?: number,
	signal?: AbortSignal
) {
	return getCollection( `${ BASE }/classes`, parseClass, {
		signal,
		query: {
			page,
			per_page: PER_PAGE,
			search,
			status,
			...( undefined === schoolId
				? {}
				: { school_id: positiveId( schoolId ) } ),
		},
	} );
}

/**
 * Create a class in a school.
 *
 * @param schoolId School id.
 * @param input    Class fields and academic year.
 * @param signal   Cancels the request.
 * @return The created class.
 */
export async function createClass(
	schoolId: number,
	input: NewClassInput,
	signal?: AbortSignal
) {
	return parseClass(
		await post< unknown >(
			`${ school( schoolId ) }/classes`,
			{
				name: input.name.trim(),
				subject: input.subject.trim(),
				grade: input.grade.trim(),
				academic_year_id: positiveId( input.academic_year_id ),
			},
			{ signal }
		)
	);
}

/**
 * Read one class.
 *
 * @param id     Class id.
 * @param signal Cancels the request.
 * @return The class.
 */
export async function getClass( id: number, signal?: AbortSignal ) {
	return parseClass( await get< unknown >( classPath( id ), { signal } ) );
}

/**
 * Save a class. A stale `version` is rejected with 409.
 *
 * @param id      Class id.
 * @param input   Name, subject and grade.
 * @param version Version the form was loaded from.
 * @param signal  Cancels the request.
 * @return The saved class.
 */
export async function updateClass(
	id: number,
	input: ClassInput,
	version: string,
	signal?: AbortSignal
) {
	return parseClass(
		await put< unknown >(
			classPath( id ),
			{
				name: input.name.trim(),
				subject: input.subject.trim(),
				grade: input.grade.trim(),
				version,
			},
			{ signal }
		)
	);
}

/**
 * Archive a class (classes are never deleted).
 *
 * @param id     Class id.
 * @param signal Cancels the request.
 * @return The archived class.
 */
export async function archiveClass( id: number, signal?: AbortSignal ) {
	return parseClass(
		await post< unknown >( `${ classPath( id ) }/archive`, undefined, {
			signal,
		} )
	);
}

/**
 * Restore an archived class.
 *
 * @param id     Class id.
 * @param signal Cancels the request.
 * @return The restored class.
 */
export async function restoreClass( id: number, signal?: AbortSignal ) {
	return parseClass(
		await post< unknown >( `${ classPath( id ) }/restore`, undefined, {
			signal,
		} )
	);
}

/**
 * List a class's members, teachers first.
 *
 * @param id     Class id.
 * @param page   Page number, from 1.
 * @param signal Cancels the request.
 * @return Validated members and totals.
 */
export function listMembers( id: number, page: number, signal?: AbortSignal ) {
	return getCollection( `${ classPath( id ) }/members`, parseMember, {
		signal,
		query: { page, per_page: PER_PAGE },
	} );
}

/**
 * Search the school roster for people who can join a class.
 *
 * @param id     Class id.
 * @param role   Role to join as.
 * @param search Name search.
 * @param signal Cancels the request.
 * @return Validated people and totals.
 */
export function listCandidates(
	id: number,
	role: ClassRole,
	search: string,
	signal?: AbortSignal
) {
	return getCollection( `${ classPath( id ) }/candidates`, parsePerson, {
		signal,
		query: { role, search, page: 1, per_page: SEARCH_PER_PAGE },
	} );
}

/**
 * Add a roster person to a class.
 *
 * @param id     Class id.
 * @param userId Person.
 * @param role   Class role.
 * @param signal Cancels the request.
 * @return The membership.
 */
export async function addMember(
	id: number,
	userId: number,
	role: ClassRole,
	signal?: AbortSignal
) {
	return parseMember(
		await post< unknown >(
			`${ classPath( id ) }/members`,
			{ user_id: positiveId( userId ), role },
			{ signal }
		)
	);
}

/**
 * Remove a person from a class.
 *
 * @param id     Class id.
 * @param userId Person.
 * @param role   Class role.
 * @param signal Cancels the request.
 */
export async function removeMember(
	id: number,
	userId: number,
	role: ClassRole,
	signal?: AbortSignal
) {
	confirmed(
		await del< unknown >(
			`${ classPath( id ) }/members/${ positiveId( userId ) }`,
			{ signal, query: { role } }
		),
		'removed'
	);
}

/**
 * List teachers.
 *
 * @param page   Page number, from 1.
 * @param search Name (or email) search.
 * @param signal Cancels the request.
 * @return Validated teachers and totals.
 */
export function listTeachers(
	page: number,
	search: string,
	signal?: AbortSignal
) {
	return getCollection( `${ BASE }/teachers`, parseTeacher, {
		signal,
		query: { page, per_page: PER_PAGE, search },
	} );
}

/**
 * List parents.
 *
 * @param page   Page number, from 1.
 * @param search Name (or email) search.
 * @param signal Cancels the request.
 * @return Validated parents and totals.
 */
export function listParents(
	page: number,
	search: string,
	signal?: AbortSignal
) {
	return getCollection( `${ BASE }/parents`, parseParent, {
		signal,
		query: { page, per_page: PER_PAGE, search },
	} );
}

/**
 * Read a parent's linked children and pending invitations.
 *
 * @param id     Parent user id.
 * @param signal Cancels the request.
 * @return Children and pending invitations.
 */
export async function getChildren( id: number, signal?: AbortSignal ) {
	return parseChildren(
		await get< unknown >( `${ parent( id ) }/children`, { signal } )
	);
}

/**
 * Invite a parent to link with a student of a school. The link becomes active when the parent accepts.
 *
 * @param id        Parent user id.
 * @param schoolId  School id.
 * @param studentId Student user id.
 * @param signal    Cancels the request.
 * @return The single-use invitation.
 */
export async function linkChild(
	id: number,
	schoolId: number,
	studentId: number,
	signal?: AbortSignal
) {
	return parseInvitation(
		await post< unknown >(
			`${ parent( id ) }/children`,
			{
				school_id: positiveId( schoolId ),
				student_id: positiveId( studentId ),
			},
			{ signal }
		)
	);
}

/**
 * Revoke an active parent-child link.
 *
 * @param id     Parent user id.
 * @param linkId Link id.
 * @param signal Cancels the request.
 */
export async function unlinkChild(
	id: number,
	linkId: number,
	signal?: AbortSignal
) {
	confirmed(
		await del< unknown >(
			`${ parent( id ) }/children/${ positiveId( linkId ) }`,
			{ signal }
		),
		'revoked'
	);
}

/**
 * Revoke a pending guardian invitation.
 *
 * @param id           Parent user id.
 * @param invitationId Invitation id.
 * @param signal       Cancels the request.
 */
export async function revokeInvitation(
	id: number,
	invitationId: number,
	signal?: AbortSignal
) {
	confirmed(
		await del< unknown >(
			`${ parent( id ) }/invitations/${ positiveId( invitationId ) }`,
			{ signal }
		),
		'revoked'
	);
}
