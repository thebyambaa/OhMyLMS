import { del, get, patch, post, put } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import {
	invalidResponse,
	parseAssignment,
	parseAssignmentDetail,
	parseCourseChoice,
	parseSubmission,
	parseSubmissionDetail,
	positiveId,
	record,
} from './model';
import type {
	AssignmentInput,
	GradeInput,
	StatusFilter,
	SubmissionFilter,
} from './types';

const BASE = '/ohmylms/v1/assignments';
const itemPath = ( id: number ) => `${ BASE }/${ positiveId( id ) }`;
const submissionPath = ( id: number, submissionId: number ) =>
	`${ itemPath( id ) }/submissions/${ positiveId( submissionId ) }`;

/** Rows per page in the assignment list. */
export const PER_PAGE = 20;

/** Rows per page in the submission list. */
export const SUBMISSIONS_PER_PAGE = 20;

/** Courses offered by the list filter. */
export const COURSES_PER_PAGE = 50;

/** Filters of the assignment list. */
export interface ListFilters {
	search: string;
	status: StatusFilter;
	/** Course ID, or 0 for every course. */
	course: number;
}

/**
 * List assignments, newest first.
 *
 * @param page    Page number, from 1.
 * @param filters Search, status and course.
 * @param signal  Cancels the request.
 * @return Validated assignments and pagination totals.
 */
export function listAssignments(
	page: number,
	filters: ListFilters,
	signal?: AbortSignal
) {
	return getCollection( BASE, parseAssignment, {
		signal,
		query: {
			page,
			per_page: PER_PAGE,
			search: filters.search,
			status: filters.status,
			...( filters.course ? { course: filters.course } : {} ),
			orderby: 'created_at',
			order: 'desc',
		},
	} );
}

/**
 * Courses the list can be filtered by (the first page, alphabetical).
 *
 * @param signal Cancels the request.
 * @return Validated courses and totals.
 */
export function listCourses( signal?: AbortSignal ) {
	return getCollection( `${ BASE }/courses`, parseCourseChoice, {
		signal,
		query: { page: 1, per_page: COURSES_PER_PAGE },
	} );
}

/**
 * Read one assignment with instructions and placements.
 *
 * @param id     Assignment ID.
 * @param signal Cancels the request.
 * @return The validated assignment.
 */
export async function getAssignment( id: number, signal?: AbortSignal ) {
	const result = parseAssignmentDetail(
		await get< unknown >( itemPath( id ), {
			signal,
			query: { context: 'edit' },
		} )
	);
	if ( result.id !== id ) {
		throw invalidResponse();
	}
	return result;
}

/**
 * Create a draft assignment with only a title.
 *
 * @param title  Title.
 * @param signal Cancels the request.
 * @return The created assignment.
 */
export async function createAssignment( title: string, signal?: AbortSignal ) {
	return parseAssignmentDetail(
		await post< unknown >( BASE, { title: title.trim() }, { signal } )
	);
}

/**
 * Save the editable fields. A copy changed by someone else since `base_modified` answers 409.
 *
 * @param id     Assignment ID.
 * @param input  Validated payload.
 * @param signal Cancels the request.
 * @return The saved assignment as the server stored it.
 */
export async function updateAssignment(
	id: number,
	input: AssignmentInput,
	signal?: AbortSignal
) {
	const result = parseAssignmentDetail(
		await put< unknown >( itemPath( id ), input, { signal } )
	);
	if ( result.id !== id ) {
		throw invalidResponse();
	}
	return result;
}

/**
 * Move an assignment to the trash (refused while it has submissions).
 *
 * @param id     Assignment ID.
 * @param signal Cancels the request.
 */
export async function trashAssignment( id: number, signal?: AbortSignal ) {
	const response = record(
		await del< unknown >( itemPath( id ), { signal } )
	);
	if ( true !== response.trashed && true !== response.deleted ) {
		throw invalidResponse();
	}
}

/**
 * List the submissions of an assignment, newest first.
 *
 * @param id     Assignment ID.
 * @param page   Page number, from 1.
 * @param status `all`, `submitted` or `graded`.
 * @param signal Cancels the request.
 * @return Validated submissions and totals.
 */
export function listSubmissions(
	id: number,
	page: number,
	status: SubmissionFilter,
	signal?: AbortSignal
) {
	return getCollection( `${ itemPath( id ) }/submissions`, parseSubmission, {
		signal,
		query: { page, per_page: SUBMISSIONS_PER_PAGE, status },
	} );
}

/**
 * Read one submission with text, feedback and attachments.
 *
 * @param id           Assignment ID.
 * @param submissionId Submission ID.
 * @param signal       Cancels the request.
 * @return The validated submission.
 */
export async function getSubmission(
	id: number,
	submissionId: number,
	signal?: AbortSignal
) {
	const result = parseSubmissionDetail(
		await get< unknown >( submissionPath( id, submissionId ), { signal } )
	);
	if ( result.id !== submissionId || result.assignment_id !== id ) {
		throw invalidResponse();
	}
	return result;
}

/**
 * Grade a submission. The learner gets the same status, hooks and email as from the legacy screen.
 *
 * @param id           Assignment ID.
 * @param submissionId Submission ID.
 * @param input        Score and feedback.
 * @param signal       Cancels the request.
 * @return The graded submission.
 */
export async function gradeSubmission(
	id: number,
	submissionId: number,
	input: GradeInput,
	signal?: AbortSignal
) {
	const result = parseSubmissionDetail(
		await patch< unknown >( submissionPath( id, submissionId ), input, {
			signal,
		} )
	);
	if ( result.id !== submissionId || 'graded' !== result.status ) {
		throw invalidResponse();
	}
	return result;
}
