import { del, get, post } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import { PER_PAGE, SEARCH_PER_PAGE } from './paging';
import {
	confirmed,
	invalidResponse,
	parseInvitation,
	parseSchool,
	parseSchoolDetail,
	positiveId,
	record,
} from './model';
import {
	parseClassWork,
	parseGuardian,
	parsePlan,
	parseRemoval,
	parseReport,
	parseReportExport,
	parseRolloverResult,
	parseSample,
	parseSubmissions,
	parseTitled,
} from './operationsModel';
import type {
	ClassWorkInput,
	GuardianInviteInput,
	GuardianStatusFilter,
	ReportPeriod,
	RolloverInput,
} from './types';

const BASE = '/ohmylms/v1/account-hub';
const school = ( id: number ) => `${ BASE }/schools/${ positiveId( id ) }`;
const classPath = ( id: number ) => `${ BASE }/classes/${ positiveId( id ) }`;

/** School list status filter values. */
export type SchoolFilter = 'all' | 'active' | 'archived';

/**
 * List schools by name and status.
 *
 * @param page   Page number, from 1.
 * @param search Name search.
 * @param status Status filter.
 * @param signal Cancels the request.
 * @return Validated schools and totals.
 */
export function listSchoolsByStatus(
	page: number,
	search: string,
	status: SchoolFilter,
	signal?: AbortSignal
) {
	return getCollection( `${ BASE }/schools`, parseSchool, {
		signal,
		query: { page, per_page: PER_PAGE, search, status },
	} );
}

/**
 * Archive a school (reversible; site administrators only).
 *
 * @param id     School id.
 * @param signal Cancels the request.
 * @return The archived school.
 */
export async function archiveSchool( id: number, signal?: AbortSignal ) {
	return parseSchoolDetail(
		await post< unknown >( `${ school( id ) }/archive`, undefined, {
			signal,
		} )
	);
}

/**
 * Restore an archived school.
 *
 * @param id     School id.
 * @param signal Cancels the request.
 * @return The restored school.
 */
export async function restoreSchool( id: number, signal?: AbortSignal ) {
	return parseSchoolDetail(
		await post< unknown >( `${ school( id ) }/restore`, undefined, {
			signal,
		} )
	);
}

/**
 * What deleting a school would remove.
 *
 * @param id     School id.
 * @param signal Cancels the request.
 * @return Counts and the confirmation text.
 */
export async function getRemoval( id: number, signal?: AbortSignal ) {
	return parseRemoval(
		await get< unknown >( `${ school( id ) }/removal`, { signal } )
	);
}

/**
 * Permanently delete an archived school. Accounts are kept.
 *
 * @param id      School id.
 * @param confirm The school name as typed.
 * @param signal  Cancels the request.
 */
export async function deleteSchool(
	id: number,
	confirm: string,
	signal?: AbortSignal
) {
	confirmed(
		await del< unknown >( school( id ), { signal, query: { confirm } } ),
		'deleted'
	);
}

/**
 * Guardians of a school: active links and open invitations.
 *
 * @param id      School id.
 * @param page    Page number, from 1.
 * @param search  Student or parent name.
 * @param status  Filter.
 * @param classId Only students of this class, or 0.
 * @param signal  Cancels the request.
 * @return Validated rows and totals.
 */
export function listGuardians(
	id: number,
	page: number,
	search: string,
	status: GuardianStatusFilter,
	classId: number,
	signal?: AbortSignal
) {
	return getCollection( `${ school( id ) }/guardians`, parseGuardian, {
		signal,
		query: {
			page,
			per_page: PER_PAGE,
			search,
			status,
			...( classId ? { class_id: positiveId( classId ) } : {} ),
		},
	} );
}

/**
 * Invite a guardian for a student (an existing parent or an email address).
 *
 * @param id     School id.
 * @param input  Student and parent or email.
 * @param signal Cancels the request.
 * @return The single-use invitation.
 */
export async function inviteGuardian(
	id: number,
	input: GuardianInviteInput,
	signal?: AbortSignal
) {
	const body =
		'parent_id' in input
			? {
					student_id: positiveId( input.student_id ),
					parent_id: positiveId( input.parent_id ),
					send_email: input.send_email,
				}
			: {
					student_id: positiveId( input.student_id ),
					email: input.email.trim(),
					send_email: input.send_email,
				};
	return parseInvitation(
		await post< unknown >( `${ school( id ) }/guardians`, body, {
			signal,
		} )
	);
}

/**
 * Revoke an active guardian link.
 *
 * @param id     School id.
 * @param linkId Link id.
 * @param signal Cancels the request.
 */
export async function unlinkGuardian(
	id: number,
	linkId: number,
	signal?: AbortSignal
) {
	confirmed(
		await del< unknown >(
			`${ school( id ) }/guardians/${ positiveId( linkId ) }`,
			{ signal }
		),
		'revoked'
	);
}

/**
 * Revoke an open guardian invitation.
 *
 * @param id           School id.
 * @param invitationId Invitation id.
 * @param signal       Cancels the request.
 */
export async function revokeGuardianInvitation(
	id: number,
	invitationId: number,
	signal?: AbortSignal
) {
	confirmed(
		await del< unknown >(
			`${ school( id ) }/guardian-invitations/${ positiveId(
				invitationId
			) }`,
			{ signal }
		),
		'revoked'
	);
}

/**
 * The school summary report.
 *
 * @param id     School id.
 * @param period Days of activity counted.
 * @param signal Cancels the request.
 * @return The report.
 */
export async function getReport(
	id: number,
	period: ReportPeriod,
	signal?: AbortSignal
) {
	return parseReport(
		await get< unknown >( `${ school( id ) }/report`, {
			signal,
			query: { period },
		} )
	);
}

/**
 * The school report as CSV text.
 *
 * @param id     School id.
 * @param period Days of activity counted.
 * @param signal Cancels the request.
 * @return File name, type and text.
 */
export async function exportReport(
	id: number,
	period: ReportPeriod,
	signal?: AbortSignal
) {
	return parseReportExport(
		await get< unknown >( `${ school( id ) }/report/export`, {
			signal,
			query: { period },
		} )
	);
}

function rolloverBody( input: RolloverInput ) {
	return {
		from_year_id: positiveId( input.from_year_id ),
		to_year_id: positiveId( input.to_year_id ),
		grade_map: input.grade_map.map( ( row ) => ( {
			from: row.from.trim(),
			to: row.to.trim(),
		} ) ),
		copy_teachers: input.copy_teachers,
		include_empty: Boolean( input.include_empty ),
	};
}

/**
 * Preview a year rollover; nothing is written.
 *
 * @param id     School id.
 * @param input  Years, grade map and teacher choice.
 * @param signal Cancels the request.
 * @return The plan and its review token.
 */
export async function previewRollover(
	id: number,
	input: RolloverInput,
	signal?: AbortSignal
) {
	return parsePlan(
		await post< unknown >(
			`${ school( id ) }/rollover/preview`,
			rolloverBody( input ),
			{ signal }
		)
	);
}

/**
 * Apply a previewed rollover.
 *
 * @param id     School id.
 * @param input  The previewed input.
 * @param review Review token of the preview.
 * @param signal Cancels the request.
 * @return The result.
 */
export async function applyRollover(
	id: number,
	input: RolloverInput,
	review: string,
	signal?: AbortSignal
) {
	return parseRolloverResult(
		await post< unknown >(
			`${ school( id ) }/rollover`,
			{ ...rolloverBody( input ), review },
			{ signal }
		)
	);
}

/**
 * The sample school state.
 *
 * @param signal Cancels the request.
 * @return The state.
 */
export async function getSampleSchool( signal?: AbortSignal ) {
	return parseSample(
		await get< unknown >( `${ BASE }/sample-school`, { signal } )
	);
}

/**
 * Create (or complete) the sample school.
 *
 * @param signal Cancels the request.
 * @return The state.
 */
export async function createSampleSchool( signal?: AbortSignal ) {
	return parseSample(
		await post< unknown >( `${ BASE }/sample-school`, undefined, {
			signal,
		} )
	);
}

/**
 * Remove the sample school and its marked accounts.
 *
 * @param signal Cancels the request.
 */
export async function removeSampleSchool( signal?: AbortSignal ) {
	confirmed(
		await del< unknown >( `${ BASE }/sample-school`, { signal } ),
		'removed'
	);
}

/**
 * Work assigned to a class.
 *
 * @param id     Class id.
 * @param page   Page number, from 1.
 * @param signal Cancels the request.
 * @return Validated work and totals.
 */
export function listClassWork(
	id: number,
	page: number,
	signal?: AbortSignal
) {
	return getCollection( `${ classPath( id ) }/assignments`, parseClassWork, {
		signal,
		query: { page, per_page: PER_PAGE },
	} );
}

/**
 * Assign a course or activity to the class's current students.
 *
 * @param id     Class id.
 * @param input  Course, optional activity, title, due date.
 * @param signal Cancels the request.
 * @return The new work id and recipient count.
 */
export async function assignClassWork(
	id: number,
	input: ClassWorkInput,
	signal?: AbortSignal
) {
	const item = record(
		await post< unknown >(
			`${ classPath( id ) }/assignments`,
			{
				course_id: positiveId( input.course_id ),
				...( input.content_id
					? { content_id: positiveId( input.content_id ) }
					: {} ),
				title: input.title.trim(),
				due_at: input.due_at,
				prior_completion: input.prior_completion,
			},
			{ signal }
		)
	);
	return {
		id: positiveId( item.id ),
		recipients: Number( item.recipients ),
	};
}

/**
 * Published courses to assign.
 *
 * @param id     Class id.
 * @param search Title search.
 * @param signal Cancels the request.
 * @return Validated courses and totals.
 */
export function listClassCourses(
	id: number,
	search: string,
	signal?: AbortSignal
) {
	return getCollection( `${ classPath( id ) }/courses`, parseTitled, {
		signal,
		query: { page: 1, per_page: SEARCH_PER_PAGE, search },
	} );
}

/**
 * Activities of a course.
 *
 * @param id       Class id.
 * @param courseId Course id.
 * @param signal   Cancels the request.
 * @return Activities.
 */
export async function listCourseActivities(
	id: number,
	courseId: number,
	signal?: AbortSignal
) {
	const rows = await get< unknown >(
		`${ classPath( id ) }/courses/${ positiveId( courseId ) }/activities`,
		{ signal }
	);
	if ( ! Array.isArray( rows ) ) {
		throw invalidResponse();
	}
	return rows.map( parseTitled );
}

/**
 * Written submissions to the class's work.
 *
 * @param id     Class id.
 * @param page   Page number, from 1.
 * @param signal Cancels the request.
 * @return Submissions and whether more exist.
 */
export async function listSubmissions(
	id: number,
	page: number,
	signal?: AbortSignal
) {
	return parseSubmissions(
		await get< unknown >( `${ classPath( id ) }/submissions`, {
			signal,
			query: { page },
		} )
	);
}

/**
 * Grade a submission.
 *
 * @param id           Class id.
 * @param attemptId    Submission id.
 * @param assignmentId Class work id.
 * @param score        Whole points.
 * @param note         Feedback.
 * @param signal       Cancels the request.
 */
export async function gradeSubmission(
	id: number,
	attemptId: number,
	assignmentId: number,
	score: number,
	note: string,
	signal?: AbortSignal
) {
	confirmed(
		await post< unknown >(
			`${ classPath( id ) }/submissions/${ positiveId( attemptId ) }`,
			{ assignment_id: positiveId( assignmentId ), score, note },
			{ signal }
		),
		'graded'
	);
}
